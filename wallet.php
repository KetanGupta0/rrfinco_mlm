<?php
/**
 * BachatPay - Wallet Page
 * View current wallet balance and deposit money
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

$user = getCurrentUser();
if (!$user) {
    session_destroy();
    redirect('index.php');
}

$csrf_token = generateCSRFToken();

// Get wallet summary
$stmt = $pdo->prepare('
    SELECT 
        wallet_balance,
        total_cashback_earned,
        total_commission_earned,
        (SELECT COALESCE(SUM(amount), 0) FROM deposits WHERE user_id = ? AND status = "completed") as total_deposits,
        (SELECT COALESCE(SUM(amount), 0) FROM withdrawal_requests WHERE user_id = ? AND status = "completed") as total_withdrawn
    FROM users WHERE user_id = ?
');
$stmt->execute([$user['user_id'], $user['user_id'], $user['user_id']]);
$wallet = $stmt->fetch();

// Get recent wallet transactions
$stmt = $pdo->prepare('
    SELECT * FROM transactions 
    WHERE user_id = ? 
    ORDER BY transaction_date DESC 
    LIMIT 20
');
$stmt->execute([$user['user_id']]);
$transactions = $stmt->fetchAll();
$currentPage = 'wallet';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Wallet - BachatPay</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .gradient-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .wallet-card { transition: all 0.3s ease; }
        .wallet-card:hover { transform: translateY(-5px); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); }
    </style>
</head>
<body class="bg-gray-50">
    <?php require_once __DIR__ . '/includes/navbar.php'; ?>

    <div class="lg:ml-64 px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-4xl font-bold text-gray-900 mb-2">
                <i class="fas fa-wallet text-blue-600 mr-3"></i>My Wallet
            </h1>
            <p class="text-gray-600">Manage your wallet balance and view transactions</p>
        </div>

        <!-- Wallet Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Current Balance -->
            <div class="bg-white rounded-lg shadow p-6 wallet-card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">Current Balance</p>
                        <p class="text-3xl font-bold text-gray-900 mt-2">
                            <?php echo formatCurrency($wallet['wallet_balance']); ?>
                        </p>
                    </div>
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-piggy-bank text-blue-600 text-2xl"></i>
                    </div>
                </div>
                <p class="text-xs text-gray-500 mt-4">Available for withdrawal</p>
            </div>

            <!-- Total Deposits -->
            <div class="bg-white rounded-lg shadow p-6 wallet-card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">Total Deposits</p>
                        <p class="text-3xl font-bold text-green-600 mt-2">
                            <?php echo formatCurrency($wallet['total_deposits']); ?>
                        </p>
                    </div>
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-arrow-down text-green-600 text-2xl"></i>
                    </div>
                </div>
                <p class="text-xs text-gray-500 mt-4">Money added</p>
            </div>

            <!-- Total Withdrawn -->
            <div class="bg-white rounded-lg shadow p-6 wallet-card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">Total Withdrawn</p>
                        <p class="text-3xl font-bold text-red-600 mt-2">
                            <?php echo formatCurrency($wallet['total_withdrawn']); ?>
                        </p>
                    </div>
                    <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-arrow-up text-red-600 text-2xl"></i>
                    </div>
                </div>
                <p class="text-xs text-gray-500 mt-4">Money withdrawn</p>
            </div>

            <!-- Total Earned -->
            <div class="bg-white rounded-lg shadow p-6 wallet-card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">Total Earned</p>
                        <p class="text-3xl font-bold text-purple-600 mt-2">
                            <?php echo formatCurrency($wallet['total_cashback_earned'] + $wallet['total_commission_earned']); ?>
                        </p>
                    </div>
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-chart-line text-purple-600 text-2xl"></i>
                    </div>
                </div>
                <p class="text-xs text-gray-500 mt-4">Cashback + Commissions</p>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
            <a href="deposit.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg text-center transition">
                <i class="fas fa-plus-circle mr-2"></i>Deposit Money
            </a>
            <a href="payout.php" class="bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-6 rounded-lg text-center transition">
                <i class="fas fa-money-bill-wave mr-2"></i>Request Payout
            </a>
            <a href="wallet-history.php" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-3 px-6 rounded-lg text-center transition">
                <i class="fas fa-history mr-2"></i>View History
            </a>
        </div>

        <!-- Recent Transactions -->
        <div class="bg-white rounded-lg shadow">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-2xl font-bold text-gray-900">
                    <i class="fas fa-list mr-3 text-blue-600"></i>Recent Transactions
                </h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Description</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-gray-700 uppercase">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($transactions as $txn): 
                            // Force type to string to prevent null deprecation errors
                            $type = $txn['type'] ?? ''; 
                            $status = $txn['status'] ?? '';
                            $isDebit = strpos($type, 'withdrawal') !== false;
                            
                            $statusColor = $status === 'credited' ? 'bg-green-100 text-green-800' : 
                                        ($status === 'debited' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800');
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4"><?php echo !empty($txn['transaction_date']) ? date('M d, Y H:i', strtotime($txn['transaction_date'])) : 'N/A'; ?></td>
                            <td class="px-6 py-4 font-medium">
                                <?php if (strpos($type, 'cashback') !== false): ?>
                                    <span class="text-blue-600"><i class="fas fa-coins mr-1"></i>Cashback</span>
                                <?php elseif (strpos($type, 'commission') !== false): ?>
                                    <span class="text-purple-600"><i class="fas fa-sitemap mr-1"></i>Commission</span>
                                <?php elseif (strpos($type, 'bonus') !== false): ?>
                                    <span class="text-orange-600"><i class="fas fa-gift mr-1"></i>Bonus</span>
                                <?php elseif ($type == 'withdrawal'): ?>
                                    <span class="text-red-600"><i class="fas fa-arrow-up mr-1"></i>Withdrawal</span>
                                <?php else: ?>
                                    <span><?php echo ucfirst(str_replace('_', ' ', $type)); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-gray-600"><?php echo htmlspecialchars($txn['description'] ?? 'N/A'); ?></td>
                            <td class="px-6 py-4 text-right font-semibold <?php echo $isDebit ? 'text-red-600' : 'text-green-600'; ?>">
                                <?php echo $isDebit ? '-' : '+'; ?><?php echo formatCurrency($txn['amount'] ?? 0); ?>
                            </td>
                            <td class="px-6 py-4">
                                <span class="px-3 py-1 rounded-full text-xs font-semibold <?php echo $statusColor; ?>">
                                    <?php echo ucfirst($status); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
