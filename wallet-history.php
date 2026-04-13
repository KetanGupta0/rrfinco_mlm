<?php
/**
 * BachatPay - Wallet Transaction History
 * Detailed view of wallet deposits, withdrawals, and earnings
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if (!isLoggedIn()) redirect('index.php');
$user = getCurrentUser();
if (!$user) { session_destroy(); redirect('index.php'); }

// Get filter parameters
$type_filter = $_GET['type'] ?? 'all';
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Build query based on filters
$where = 'user_id = ?';
$params = [$user['user_id']];

if ($type_filter !== 'all') {
    $where .= ' AND transaction_type = ?';
    $params[] = $type_filter;
}

$where .= ' AND DATE(transaction_date) BETWEEN ? AND ?';
$params[] = $start_date;
$params[] = $end_date;

// Get filtered transactions
$stmt = $pdo->prepare("
    SELECT * FROM transactions 
    WHERE $where 
    ORDER BY transaction_date DESC 
    LIMIT 100
");
$stmt->execute($params);
$transactions = $stmt->fetchAll();

// Calculate stats for the period
$deposited = 0;
$withdrawn = 0;
$earned_cashback = 0;
$earned_commission = 0;

foreach ($transactions as $txn) {
    if (strpos($txn['transaction_type'], 'deposit') !== false) {
        $deposited += $txn['amount'];
    } elseif (strpos($txn['transaction_type'], 'withdrawal') !== false || strpos($txn['transaction_type'], 'debit') !== false) {
        $withdrawn += $txn['amount'];
    } elseif (strpos($txn['transaction_type'], 'cashback') !== false) {
        $earned_cashback += $txn['amount'];
    } elseif (strpos($txn['transaction_type'], 'commission') !== false) {
        $earned_commission += $txn['amount'];
    }
}

$transaction_types = [
    'all' => 'All Transactions',
    'deposit' => 'Deposits',
    'withdrawal' => 'Withdrawals',
    'cashback' => 'Cashback',
    'commission' => 'Commission',
    'bonus' => 'Bonus'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wallet History - BachatPay</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <?php $currentPage = 'wallet-history'; require_once __DIR__ . '/includes/navbar.php'; ?>

    <div class="lg:ml-64 px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-4xl font-bold text-gray-900 mb-2">
                <i class="fas fa-history text-blue-600 mr-3"></i>Wallet History
            </h1>
            <p class="text-gray-600">View all your wallet transactions</p>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">Total Deposited</p>
                        <p class="text-2xl font-bold text-green-600 mt-2"><?php echo formatCurrency($deposited); ?></p>
                    </div>
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-arrow-down text-green-600 text-2xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">Total Withdrawn</p>
                        <p class="text-2xl font-bold text-red-600 mt-2"><?php echo formatCurrency($withdrawn); ?></p>
                    </div>
                    <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-arrow-up text-red-600 text-2xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">Cashback Earned</p>
                        <p class="text-2xl font-bold text-blue-600 mt-2"><?php echo formatCurrency($earned_cashback); ?></p>
                    </div>
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-coins text-blue-600 text-2xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">Commission Earned</p>
                        <p class="text-2xl font-bold text-purple-600 mt-2"><?php echo formatCurrency($earned_commission); ?></p>
                    </div>
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-sitemap text-purple-600 text-2xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow p-6 mb-8">
            <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Transaction Type</label>
                    <select name="type" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <?php foreach ($transaction_types as $key => $label): ?>
                        <option value="<?php echo $key; ?>" <?php echo $type_filter === $key ? 'selected' : ''; ?>>
                            <?php echo $label; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Start Date</label>
                    <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" 
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">End Date</label>
                    <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" 
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>

                <div class="flex items-end">
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 rounded transition">
                        <i class="fas fa-filter mr-2"></i>Filter
                    </button>
                </div>
            </form>
        </div>

        <!-- Transactions Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-100 border-b">
                        <tr>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Date</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Type</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Description</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Amount</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($transactions)): ?>
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                                <i class="fas fa-inbox text-4xl mb-2 block opacity-50"></i>
                                No transactions found
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($transactions as $txn): 
                                // Color coding
                                if (strpos($txn['transaction_type'], 'cashback') !== false) {
                                    $typeColor = 'bg-blue-100 text-blue-800';
                                    $icon = 'fas-coins';
                                } elseif (strpos($txn['transaction_type'], 'commission') !== false) {
                                    $typeColor = 'bg-purple-100 text-purple-800';
                                    $icon = 'fas-sitemap';
                                } elseif (strpos($txn['transaction_type'], 'bonus') !== false) {
                                    $typeColor = 'bg-orange-100 text-orange-800';
                                    $icon = 'fas-gift';
                                } elseif (strpos($txn['transaction_type'], 'withdrawal') !== false || strpos($txn['transaction_type'], 'debit') !== false) {
                                    $typeColor = 'bg-red-100 text-red-800';
                                    $icon = 'fas-arrow-up';
                                    $amountColor = 'text-red-600';
                                } else {
                                    $typeColor = 'bg-green-100 text-green-800';
                                    $icon = 'fas-arrow-down';
                                    $amountColor = 'text-green-600';
                                }
                                $amountColor = $amountColor ?? 'text-gray-900';
                                
                                $statusColor = $txn['status'] === 'completed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800';
                            ?>
                            <tr class="border-b hover:bg-gray-50 transition">
                                <td class="px-4 py-3 text-sm">
                                    <span class="font-semibold"><?php echo date('M d, Y', strtotime($txn['transaction_date'])); ?></span><br>
                                    <span class="text-xs text-gray-500"><?php echo date('H:i', strtotime($txn['transaction_date'])); ?></span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="px-3 py-1 rounded-full text-xs font-semibold <?php echo $typeColor; ?>">
                                        <i class="fas <?php echo $icon; ?> mr-1"></i><?php echo ucfirst(str_replace('_', ' ', $txn['transaction_type'])); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    <?php echo htmlspecialchars(substr($txn['description'] ?? 'N/A', 0, 40)) . (strlen($txn['description'] ?? '') > 40 ? '...' : ''); ?>
                                </td>
                                <td class="px-4 py-3 font-semibold <?php echo $amountColor; ?>">
                                    <?php echo strpos($txn['transaction_type'], 'withdrawal') !== false || strpos($txn['transaction_type'], 'debit') !== false ? '-' : '+'; ?><?php echo formatCurrency($txn['amount']); ?>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="px-3 py-1 rounded-full text-xs font-semibold <?php echo $statusColor; ?>">
                                        <?php echo ucfirst($txn['status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Quick Navigation -->
        <div class="mt-8 flex flex-wrap gap-4 justify-center sm:justify-start">
            <a href="wallet.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded transition">
                <i class="fas fa-wallet mr-2"></i>My Wallet
            </a>
            <a href="deposit.php" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded transition">
                <i class="fas fa-plus mr-2"></i>Deposit Funds
            </a>
        </div>
    </div>
</body>
</html>
