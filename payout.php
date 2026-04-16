<?php
/**
 * BachatPay - Payout/Withdrawal Page
 * Request withdrawal to bank account
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if (!isLoggedIn()) redirect('index.php');
$user = getCurrentUser();
if (!$user) { session_destroy(); redirect('index.php'); }

$csrf_token = generateCSRFToken();
$error = '';
$success = '';

// Get user's saved bank accounts
$stmt = $pdo->prepare('SELECT * FROM user_bank_accounts WHERE user_id = ? AND is_primary = 1 LIMIT 1');
$stmt->execute([$user['user_id']]);
$primary_bank = $stmt->fetch();

// Handle withdrawal request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_payout'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request';
    } else {
        $amount = (float)$_POST['amount'] ?? 0;
        $method = $_POST['withdrawal_method'] ?? 'bank';

        $bank_account_id = null;
        $upi_id = null;
        
        if ($method === 'bank') {
            $bank_account_id = (int)($_POST['bank_account_id'] ?? 0);
        
            if ($bank_account_id === 0) {
                $error = 'Please select a bank account';
            }
        
        } elseif ($method === 'upi') {
            $upi_id = trim($_POST['upi_id'] ?? '');
        
            if (empty($upi_id)) {
                $error = 'Please enter UPI ID';
            } elseif (!preg_match('/^[\w.\-]{2,256}@[a-zA-Z]{2,64}$/', $upi_id)) {
                $error = 'Invalid UPI ID format';
            }
        }
        
        if ($amount < 1000) {
            $error = 'Minimum withdrawal amount is ₹1,000';
        } elseif ($amount > $user['wallet_balance']) {
            $error = 'Insufficient wallet balance';
        } elseif ($method === 'bank' && empty($bank_account_id)) {
            $error = 'Please select a bank account';
        } elseif ($method === 'upi' && empty($upi_id)) {
            $error = 'Please enter UPI ID';
        } else {
            try {
                $pdo->beginTransaction();
                
                // Create withdrawal request
                $stmt = $pdo->prepare('
                    INSERT INTO withdrawal_requests 
                    (user_id, amount, bank_account_id, upi_id, method, status, requested_at)
                    VALUES (?, ?, ?, ?, ?, "pending", NOW())
                ');
                // Ensure only one method is used
                if ($method === 'upi') {
                    $bank_account_id = null;
                } else {
                    $upi_id = null;
                }
                $stmt->execute([
                    $user['user_id'],
                    $amount,
                    $bank_account_id,
                    $upi_id,
                    $method
                ]);
                
                // Deduct from wallet
                updateWalletBalance($user['user_id'], $amount, 'sub');
                
                // Record transaction
                $desc = $method === 'upi' 
                    ? "UPI withdrawal request ({$upi_id})" 
                    : "Bank withdrawal request";
                
                recordTransaction($user['user_id'], 'withdrawal', -$amount, $desc);
                
                $pdo->commit();
                $success = 'Withdrawal request submitted successfully. It will be processed within 2-3 business days.';
                
                // Refresh user data
                $user = getCurrentUser();
            } catch (Exception $e) {
                $pdo->rollBack();
                logError('Withdrawal Error', $e->getMessage());
                $error = 'Error processing withdrawal. Please try again.';
            }
        }
    }
}

// Get user's bank accounts
$stmt = $pdo->prepare('SELECT * FROM user_bank_accounts WHERE user_id = ? ORDER BY is_primary DESC');
$stmt->execute([$user['user_id']]);
$bank_accounts = $stmt->fetchAll();

// Get recent payouts
$stmt = $pdo->prepare('SELECT * FROM withdrawal_requests WHERE user_id = ? ORDER BY requested_at DESC LIMIT 5');
$stmt->execute([$user['user_id']]);
$recent_payouts = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Withdraw Funds - BachatPay</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <?php $currentPage = 'payout'; require_once __DIR__ . '/includes/navbar.php'; ?>

    <div class="lg:ml-64 px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-4xl font-bold text-gray-900 mb-2">
                <i class="fas fa-arrow-up text-red-600 mr-3"></i>Withdraw Funds
            </h1>
            <p class="text-gray-600">Request a withdrawal to your bank account</p>
        </div>

        <?php if ($success): ?>
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6">
            <i class="fas fa-check-circle mr-2"></i><?php echo $success; ?>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
            <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error; ?>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Withdrawal Form -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-2xl font-bold text-gray-900 mb-6">Withdrawal Details</h2>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <input type="hidden" name="request_payout" value="1">
                        
                        <!-- Amount -->
                        <div class="mb-6">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Withdrawal Amount (₹)</label>
                            <div class="relative">
                                <span class="absolute left-4 top-3 text-gray-500 text-lg">₹</span>
                                <input type="number" name="amount" step="100" min="1000" max="<?php echo $user['wallet_balance']; ?>" required
                                    placeholder="0" class="w-full pl-8 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            </div>
                            <p class="text-xs text-gray-500 mt-2">Min: ₹1,000 | Available: <?php echo formatCurrency($user['wallet_balance']); ?></p>
                        </div>
                        
                        <!-- Withdrawal Method -->
                        <div class="mb-6">
                            <label class="block text-sm font-semibold text-gray-700 mb-3">Withdrawal Method</label>
                        
                            <label class="flex items-center mb-2">
                                <input type="radio" name="withdrawal_method" value="bank" checked class="mr-2">
                                Bank Account
                            </label>
                        
                            <label class="flex items-center">
                                <input type="radio" name="withdrawal_method" value="upi" class="mr-2">
                                UPI ID
                            </label>
                        </div>

                        <!-- Bank Account Selection -->
                        <div id="bankSection">
                            <div class="mb-6">
                                <label class="block text-sm font-semibold text-gray-700 mb-3">Bank Account</label>
                                <?php if (empty($bank_accounts)): ?>
                                <div class="bg-yellow-50 border border-yellow-200 p-4 rounded-lg text-center">
                                    <p class="text-yellow-700 text-sm mb-3">No bank account added yet</p>
                                    <a href="settings.php?tab=bank-accounts" class="text-blue-600 hover:text-blue-800 font-semibold">
                                        <i class="fas fa-plus mr-1"></i>Add Bank Account
                                    </a>
                                </div>
                                <?php else: ?>
                                <div class="space-y-3">
                                    <?php foreach ($bank_accounts as $acc): ?>
                                    <label class="flex items-center p-4 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 transition">
                                        <input type="radio" name="bank_account_id" value="<?php echo $acc['bank_account_id']; ?>" 
                                            <?php echo $acc['is_primary'] ? 'checked' : ''; ?> class="w-4 h-4 text-blue-600">
                                        <div class="ml-4 flex-1">
                                            <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($acc['account_holder_name']); ?></p>
                                            <p class="text-sm text-gray-600"><?php echo htmlspecialchars($acc['bank_name']); ?></p>
                                            <p class="text-sm text-gray-500">****<?php echo substr($acc['account_number'], -4); ?></p>
                                        </div>
                                        <?php if ($acc['is_primary']): ?>
                                        <span class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs font-semibold">Primary</span>
                                        <?php endif; ?>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- UPI Selection -->
                        <div id="upiSection" class="mb-6 hidden">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">UPI ID</label>
                            <input type="text" name="upi_id" placeholder="example@upi"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg">
                        </div>

                        <!-- Terms -->
                        <div class="mb-6">
                            <label class="flex items-start">
                                <input type="checkbox" name="terms" required class="w-4 h-4 text-blue-600 mt-1">
                                <span class="text-sm text-gray-700 ml-2">
                                    I confirm withdrawal details and agree to terms
                                </span>
                            </label>
                        </div>

                        <!-- Submit Button -->
                        <?php if (!empty($bank_accounts) || true): ?>
                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-lg transition">
                            <i class="fas fa-arrow-up mr-2"></i>Request Withdrawal
                        </button>
                        <?php endif; ?>
                    </form>

                    <!-- Info Box -->
                    <div class="mt-8 bg-blue-50 border border-blue-200 p-4 rounded-lg">
                        <h3 class="font-semibold text-blue-900 mb-2">
                            <i class="fas fa-info-circle mr-2"></i>Withdrawal Information
                        </h3>
                        <ul class="text-sm text-blue-800 space-y-1">
                            <li><i class="fas fa-check mr-2"></i>Processing time: 2-3 business days</li>
                            <li><i class="fas fa-check mr-2"></i>No withdrawal fees</li>
                            <li><i class="fas fa-check mr-2"></i>Direct bank transfer</li>
                            <li><i class="fas fa-check mr-2"></i>Minimum amount: ₹1,000</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div>
                <!-- Balance Card -->
                <div class="bg-white rounded-lg shadow p-6 mb-6">
                    <h3 class="text-sm font-semibold text-gray-700 mb-2">Available Balance</h3>
                    <p class="text-4xl font-bold text-green-600">
                        <?php echo formatCurrency($user['wallet_balance']); ?>
                    </p>
                    <p class="text-xs text-gray-500 mt-2">
                        You can withdraw up to <?php echo formatCurrency(min($user['wallet_balance'], 500000)); ?>
                    </p>
                </div>

                <!-- Recent Withdrawals -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">
                        <i class="fas fa-history text-blue-600 mr-2"></i>Recent Withdrawals
                    </h3>
                    <?php if (empty($recent_payouts)): ?>
                    <p class="text-sm text-gray-500">No withdrawals yet</p>
                    <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($recent_payouts as $payout): 
                            $statusColor = match($payout['status']) {
                                'completed' => 'bg-green-100 text-green-800',
                                'pending' => 'bg-yellow-100 text-yellow-800',
                                'rejected' => 'bg-red-100 text-red-800',
                                default => 'bg-gray-100 text-gray-800'
                            };
                        ?>
                        <div class="flex justify-between items-center border-b pb-3">
                            <div>
                                <p class="font-semibold text-gray-900"><?php echo formatCurrency($payout['amount']); ?></p>
                                <p class="text-xs text-gray-500"><?php echo date('M d, Y', strtotime($payout['requested_at'])); ?></p>
                            </div>
                            <span class="px-2 py-1 rounded text-xs font-semibold <?php echo $statusColor; ?>">
                                <?php echo ucfirst($payout['status']); ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <a href="payout-history.php" class="text-blue-600 hover:text-blue-800 text-sm mt-4 inline-block">
                        <i class="fas fa-arrow-right mr-1"></i>View All Withdrawals
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <script>
    document.querySelectorAll('input[name="withdrawal_method"]').forEach(el => {
        el.addEventListener('change', function() {
            if (this.value === 'upi') {
                document.getElementById('upiSection').classList.remove('hidden');
                document.getElementById('bankSection').classList.add('hidden');
            } else {
                document.getElementById('bankSection').classList.remove('hidden');
                document.getElementById('upiSection').classList.add('hidden');
            }
        });
    });
    </script>
</body>
</html>
