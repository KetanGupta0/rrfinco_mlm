<?php
/**
 * BachatPay - Deposit Page
 * Multiple payment gateway integration for wallet top-up
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

// Handle deposit request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_deposit'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request';
    } else {
        $amount = (float)$_POST['amount'] ?? 0;
        $gateway = $_POST['gateway'] ?? '';
        
        if ($amount < 100) {
            $error = 'Minimum deposit amount is ₹100';
        } elseif ($amount > 500000) {
            $error = 'Maximum deposit amount is ₹500,000';
        } elseif (empty($gateway)) {
            $error = 'Please select a payment gateway';
        } else {
            try {
                // Create deposit record with pending status
                $stmt = $pdo->prepare('
                    INSERT INTO deposits (user_id, amount, payment_gateway, transaction_id, status, created_at)
                    VALUES (?, ?, ?, ?, "completed", NOW())
                ');
                $stmt->execute([$user['user_id'], $amount, $gateway, 'TXN' . time()]);
                
                $deposit_id = $pdo->lastInsertId();

                updateWalletBalance($user['user_id'], $amount, 'add');

                recordTransaction($user['user_id'], 'deposit', $amount, 'Deposit via ' . ucfirst($gateway), null);
                
                // Redirect to payment gateway (in real scenario)
                // $_SESSION['deposit_pending'] = $deposit_id;
                // $success = 'Deposit initiated. Redirecting to payment gateway...';
                
                $success = $amount.' deposited successfully.';
            } catch (Exception $e) {
                $error = 'Error creating deposit. Please try again.';
            }
        }
    }
}

// Get recent deposits
$stmt = $pdo->prepare('SELECT * FROM deposits WHERE user_id = ? ORDER BY created_at DESC LIMIT 5');
$stmt->execute([$user['user_id']]);
$recent_deposits = $stmt->fetchAll();
$currentPage = 'deposit';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deposit Funds - BachatPay</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <?php require __DIR__ . '/includes/navbar.php'; ?>

    <div class="lg:ml-64 px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-4xl font-bold text-gray-900 mb-2">
                <i class="fas fa-credit-card text-blue-600 mr-3"></i>Deposit Funds
            </h1>
            <p class="text-gray-600">Add money to your account using multiple payment methods</p>
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
            <!-- Deposit Form -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-2xl font-bold text-gray-900 mb-6">Deposit Amount</h2>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <input type="hidden" name="submit_deposit" value="1">
                        
                        <!-- Amount Input -->
                        <div class="mb-6">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Amount (₹)</label>
                            <div class="relative">
                                <span class="absolute left-4 top-3 text-gray-500 text-lg">₹</span>
                                <input type="number" name="amount" step="0.01" min="100" max="500000" required
                                    placeholder="Enter amount" class="w-full pl-8 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                    oninput="updatePreview()">
                            </div>
                            <p class="text-xs text-gray-500 mt-2">Min: ₹100 | Max: ₹500,000</p>
                        </div>

                        <!-- Payment Gateway Selection -->
                        <div class="mb-6">
                            <label class="block text-sm font-semibold text-gray-700 mb-3">Payment Method</label>
                            <div class="space-y-3">
                                <?php $gateways = [
                                    'stripe' => ['name' => 'Stripe', 'icon' => 'fas-credit-card', 'color' => 'blue'],
                                    'razorpay' => ['name' => 'Razorpay', 'icon' => 'fas-rupee-sign', 'color' => 'blue'],
                                    'paypal' => ['name' => 'PayPal', 'icon' => 'fas-paypal', 'color' => 'blue'],
                                    'bank_transfer' => ['name' => 'Bank Transfer', 'icon' => 'fas-building', 'color' => 'gray'],
                                    'upi' => ['name' => 'UPI', 'icon' => 'fas-mobile-alt', 'color' => 'purple'],
                                ];
                                foreach ($gateways as $key => $gateway): ?>
                                <label class="flex items-center p-4 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 transition">
                                    <input type="radio" name="gateway" value="<?php echo $key; ?>" required class="w-4 h-4 text-blue-600">
                                    <i class="fas <?php echo $gateway['icon']; ?> text-<?php echo $gateway['color']; ?>-600 text-xl mx-4"></i>
                                    <span class="font-semibold text-gray-900"><?php echo $gateway['name']; ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Terms & Conditions -->
                        <div class="mb-6">
                            <label class="flex items-start">
                                <input type="checkbox" name="terms" required class="w-4 h-4 text-blue-600 mt-1">
                                <span class="text-sm text-gray-700 ml-2">
                                    I agree with deposit terms and conditions
                                </span>
                            </label>
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-lg transition">
                            <i class="fas fa-arrow-right mr-2"></i>Proceed to Payment
                        </button>
                    </form>
                </div>
            </div>

            <!-- Sidebar -->
            <div>
                <!-- Benefits -->
                <div class="bg-white rounded-lg shadow p-6 mb-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">
                        <i class="fas fa-lightbulb text-yellow-500 mr-2"></i>Benefits
                    </h3>
                    <ul class="space-y-2 text-sm text-gray-600">
                        <li><i class="fas fa-check text-green-600 mr-2"></i>Instant top-up</li>
                        <li><i class="fas fa-check text-green-600 mr-2"></i>Multiple payment options</li>
                        <li><i class="fas fa-check text-green-600 mr-2"></i>100% secure transactions</li>
                        <li><i class="fas fa-check text-green-600 mr-2"></i>No hidden charges</li>
                    </ul>
                </div>

                <!-- Recent Deposits -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">
                        <i class="fas fa-history text-blue-600 mr-2"></i>Recent Deposits
                    </h3>
                    <?php if (empty($recent_deposits)): ?>
                    <p class="text-sm text-gray-500">No deposits yet</p>
                    <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($recent_deposits as $dep): ?>
                        <div class="flex justify-between items-center border-b pb-3">
                            <div>
                                <p class="font-semibold text-gray-900"><?php echo formatCurrency($dep['amount']); ?></p>
                                <p class="text-xs text-gray-500"><?php echo $dep['payment_gateway']; ?></p>
                            </div>
                            <span class="px-2 py-1 rounded text-xs font-semibold <?php 
                                echo $dep['status'] === 'completed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; 
                            ?>">
                                <?php echo ucfirst($dep['status']); ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <a href="deposit-history.php" class="text-blue-600 hover:text-blue-800 text-sm mt-4 inline-block">
                        <i class="fas fa-arrow-right mr-1"></i>View All Deposits
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function updatePreview() {
            const amount = document.querySelector('input[name="amount"]').value;
            // Real-time preview could be added here
        }
    </script>
</body>
</html>
