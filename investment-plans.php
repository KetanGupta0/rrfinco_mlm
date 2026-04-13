<?php
/**
 * BachatPay - Investment Plans Page
 * View and switch between investment plans
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if (!isLoggedIn()) redirect('index.php');
$user = getCurrentUser();
if (!$user) { session_destroy(); redirect('index.php'); }

$csrf_token = generateCSRFToken();

// Get all available plans
$stmt = $pdo->query('SELECT * FROM investment_plans WHERE is_active = 1 ORDER BY min_amount ASC');
$plans = $stmt->fetchAll();

// Get user's current active plans
$stmt = $pdo->prepare('
    SELECT pi.*, ip.plan_name 
    FROM plan_investments pi 
    JOIN investment_plans ip ON pi.plan_id = ip.plan_id 
    WHERE pi.user_id = ? AND pi.status = "active"
    ORDER BY pi.start_date DESC
');
$stmt->execute([$user['user_id']]);
$activePlans = $stmt->fetchAll();

// Create a lookup array of active plan IDs for quick checking
$activePlanIds = array_column($activePlans, 'plan_id');

// Handle plan investment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['invest_plan'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request';
    } else {
        $plan_id = (int)$_POST['plan_id'];
        $amount = (float)$_POST['amount'];
        
        // Verify plan exists and amount is valid
        $stmt = $pdo->prepare('SELECT * FROM investment_plans WHERE plan_id = ? AND is_active = 1');
        $stmt->execute([$plan_id]);
        $plan = $stmt->fetch();
        
        if (!$plan) {
            $error = 'Invalid plan selected';
        } elseif ($amount < $plan['min_amount'] || $amount > $plan['max_amount']) {
            $error = 'Amount must be between ' . formatCurrency($plan['min_amount']) . ' and ' . formatCurrency($plan['max_amount']);
        } elseif ($user['wallet_balance'] < $amount) {
            $error = 'Insufficient wallet balance';
        } else {
            // Check if user already has an active investment in this same plan
            $existingInvestment = hasActivePlanInvestment($user['user_id'], $plan_id);
            
            if ($existingInvestment) {
                $startDate = date('M d, Y', strtotime($existingInvestment['start_date']));
                $maturityDate = $existingInvestment['maturity_date'] ? date('M d, Y', strtotime($existingInvestment['maturity_date'])) : 'Never';
                $error = 'You already have an active investment in this plan. You can only have one active investment per plan type. Your current investment started on ' . $startDate . ' and matures on ' . $maturityDate . '.';
            } else {
                try {
                    $pdo->beginTransaction();
                    
                    // Create plan investment
                    $maturity = $plan['duration_days'] ? date('Y-m-d H:i:s', strtotime("+{$plan['duration_days']} days")) : null;
                    $stmt = $pdo->prepare('
                        INSERT INTO plan_investments (user_id, plan_id, investment_amount, daily_percentage, monthly_percentage, maturity_date, status)
                        VALUES (?, ?, ?, ?, ?, ?, "active")
                    ');
                    $stmt->execute([$user['user_id'], $plan_id, $amount, $plan['daily_percentage'], $plan['monthly_percentage'], $maturity]);

                    // Also create a legacy investment record so cashback and level commission functions can run
                    $stmt = $pdo->prepare('
                        INSERT INTO investments (user_id, investment_amount, daily_rate, investment_date, status)
                        VALUES (?, ?, ?, NOW(), "active")
                    ');
                    $stmt->execute([$user['user_id'], $amount, $plan['daily_percentage'] / 100]);
                    
                    // Deduct from wallet
                    updateWalletBalance($user['user_id'], $amount, 'sub');
                    
                    // Record transaction
                    recordTransaction($user['user_id'], 'manual_debit', $amount, 'Investment in ' . $plan['plan_name']);
                    
                    $pdo->commit();
                    $success = 'Plan investment successful! Amount deducted from wallet.';
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $error = 'Investment failed. Please try again.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Investment Plans - BachatPay</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <?php $currentPage = 'investment-plans'; require_once __DIR__ . '/includes/navbar.php'; ?>

    <div class="lg:ml-64 px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-4xl font-bold text-gray-900 mb-2">
                <i class="fas fa-chart-bar text-blue-600 mr-3"></i>Investment Plans
            </h1>
            <p class="text-gray-600">Choose and invest in plans that suit your portfolio</p>
        </div>

        <?php if (isset($success)): ?>
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6">
            <i class="fas fa-check-circle mr-2"></i><?php echo $success; ?>
        </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
            <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error; ?>
        </div>
        <?php endif; ?>

        <!-- Your Active Plans -->
        <?php if (!empty($activePlans)): ?>
        <div class="mb-12">
            <h2 class="text-2xl font-bold text-gray-900 mb-4">
                <i class="fas fa-check-circle text-green-600 mr-2"></i>Your Active Plans
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($activePlans as $active): ?>
                <div class="bg-white rounded-lg shadow p-6 border-l-4 border-green-500">
                    <h3 class="text-xl font-bold text-gray-900 mb-2"><?php echo htmlspecialchars($active['plan_name']); ?></h3>
                    <div class="space-y-2 text-sm">
                        <p><strong>Amount:</strong> <?php echo formatCurrency($active['investment_amount']); ?></p>
                        <p><strong>Daily Returns:</strong> <?php echo formatPercentage($active['daily_percentage']); ?></p>
                        <p><strong>Total Earned:</strong> <?php echo formatCurrency($active['total_earned']); ?></p>
                        <p><strong>Started:</strong> <?php echo date('M d, Y', strtotime($active['start_date'])); ?></p>
                        <p><strong>Status:</strong> <span class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs font-semibold">Active</span></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Available Plans -->
        <h2 class="text-2xl font-bold text-gray-900 mb-4">
            <i class="fas fa-list text-blue-600 mr-2"></i>Available Plans
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <?php foreach ($plans as $plan): 
                $isAlreadyActive = in_array($plan['plan_id'], $activePlanIds);
                $activePlanDetails = null;
                if ($isAlreadyActive) {
                    foreach ($activePlans as $active) {
                        if ($active['plan_id'] == $plan['plan_id']) {
                            $activePlanDetails = $active;
                            break;
                        }
                    }
                }
            ?>
            <div class="bg-white rounded-lg shadow hover:shadow-lg transition p-6 <?php echo $isAlreadyActive ? 'border-2 border-orange-200 bg-orange-50' : ''; ?>">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-xl font-bold text-gray-900"><?php echo htmlspecialchars($plan['plan_name']); ?></h3>
                    <?php if ($isAlreadyActive): ?>
                        <span class="bg-orange-100 text-orange-800 px-2 py-1 rounded text-xs font-semibold">Active</span>
                    <?php endif; ?>
                </div>
                <p class="text-sm text-gray-600 mb-4"><?php echo htmlspecialchars($plan['description']); ?></p>
                <div class="space-y-2 text-sm mb-4 border-t pt-4">
                    <div class="flex justify-between">
                        <span>Min Amount:</span>
                        <span class="font-semibold"><?php echo formatCurrency($plan['min_amount']); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span>Max Amount:</span>
                        <span class="font-semibold"><?php echo formatCurrency($plan['max_amount']); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span>Daily Return:</span>
                        <span class="font-semibold text-green-600"><?php echo formatPercentage($plan['daily_percentage']); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span>Monthly Return:</span>
                        <span class="font-semibold text-blue-600"><?php echo formatPercentage($plan['monthly_percentage']); ?></span>
                    </div>
                </div>
                
                <?php if ($isAlreadyActive && $activePlanDetails): ?>
                    <div class="bg-orange-100 border border-orange-200 rounded p-3 mb-4">
                        <p class="text-xs text-orange-800 mb-1"><strong>Current Investment:</strong></p>
                        <p class="text-xs text-orange-800">Amount: <?php echo formatCurrency($activePlanDetails['investment_amount']); ?></p>
                        <p class="text-xs text-orange-800">Started: <?php echo date('M d, Y', strtotime($activePlanDetails['start_date'])); ?></p>
                        <p class="text-xs text-orange-800">Matures: <?php echo $activePlanDetails['maturity_date'] ? date('M d, Y', strtotime($activePlanDetails['maturity_date'])) : 'Never'; ?></p>
                    </div>
                    <button disabled class="w-full bg-gray-400 cursor-not-allowed text-white font-bold py-2 rounded">
                        <i class="fas fa-lock mr-2"></i>Already Active
                    </button>
                <?php else: ?>
                    <button onclick="openInvestModal(<?php echo $plan['plan_id']; ?>, '<?php echo htmlspecialchars($plan['plan_name']); ?>', <?php echo $plan['min_amount']; ?>, <?php echo $plan['max_amount']; ?>)" 
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 rounded transition">
                        <i class="fas fa-arrow-right mr-2"></i>Invest Now
                    </button>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Investment Modal -->
    <div id="investModal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center p-4 z-50">
        <div class="bg-white rounded-lg p-6 max-w-md w-full">
            <h3 class="text-2xl font-bold text-gray-900 mb-4" id="modalPlanName">Invest in Plan</h3>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="invest_plan" value="1">
                <input type="hidden" name="plan_id" id="modalPlanId">
                
                <div class="mb-4">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Investment Amount</label>
                    <input type="number" name="amount" id="investAmount" step="0.01" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <p class="text-xs text-gray-500 mt-1">Min: <span id="minAmount"></span> | Max: <span id="maxAmount"></span></p>
                </div>
                
                <div class="mb-4">
                    <p class="text-sm"><strong>Available Balance:</strong> <?php echo formatCurrency($user['wallet_balance']); ?></p>
                </div>
                
                <div class="flex space-x-4">
                    <button type="button" onclick="closeInvestModal()" class="flex-1 bg-gray-400 hover:bg-gray-500 text-white font-bold py-2 rounded">
                        Cancel
                    </button>
                    <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 rounded">
                        Invest Now
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openInvestModal(planId, planName, minAmount, maxAmount) {
            document.getElementById('modalPlanId').value = planId;
            document.getElementById('modalPlanName').textContent = 'Invest in ' + planName;
            document.getElementById('minAmount').textContent = '<?php echo '₹'; ?>' + minAmount.toLocaleString('en-IN');
            document.getElementById('maxAmount').textContent = '<?php echo '₹'; ?>' + maxAmount.toLocaleString('en-IN');
            document.getElementById('investAmount').min = minAmount;
            document.getElementById('investAmount').max = maxAmount;
            document.getElementById('investModal').classList.remove('hidden');
        }
        
        function closeInvestModal() {
            document.getElementById('investModal').classList.add('hidden');
        }
    </script>
</body>
</html>
