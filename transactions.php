<?php
/**
 * BachatPay - All Transactions Page
 * Unified view of all transaction types (deposits, withdrawals, earnings, investments)
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if (!isLoggedIn()) redirect('index.php');
$user = getCurrentUser();
if (!$user) { session_destroy(); redirect('index.php'); }

// Get filter parameters
$type_filter = $_GET['type'] ?? 'all';
$sort_by = $_GET['sort_by'] ?? 'date_desc';
$search = $_GET['search'] ?? '';

// Build query
$where = 'user_id = ?';
$params = [$user['user_id']];

if ($type_filter !== 'all') {
    $where .= ' AND transaction_type = ?';
    $params[] = $type_filter;
}

if (!empty($search)) {
    $where .= ' AND description LIKE ?';
    $params[] = '%' . $search . '%';
}

// Determine sort order
$order_by = match($sort_by) {
    'date_asc' => 'transaction_date ASC',
    'amount_desc' => 'amount DESC',
    'amount_asc' => 'amount ASC',
    default => 'transaction_date DESC'
};

// Get all transactions
$stmt = $pdo->prepare("
    SELECT * FROM transactions 
    WHERE $where 
    ORDER BY $order_by 
    LIMIT 500
");
$stmt->execute($params);
$transactions = $stmt->fetchAll();

// Get unique transaction types
$stmt = $pdo->prepare('SELECT DISTINCT transaction_type FROM transactions WHERE user_id = ? ORDER BY transaction_type');
$stmt->execute([$user['user_id']]);
$types_result = $stmt->fetchAll();
$transaction_types = ['all' => 'All Types'] + array_column($types_result, null, 'transaction_type');

// Calculate totals
$total_in = 0;
$total_out = 0;

foreach ($transactions as $txn) {
    if (strpos($txn['transaction_type'], 'deposit') !== false || 
        strpos($txn['transaction_type'], 'cashback') !== false ||
        strpos($txn['transaction_type'], 'commission') !== false ||
        strpos($txn['transaction_type'], 'bonus') !== false ||
        strpos($txn['transaction_type'], 'credit') !== false) {
        $total_in += $txn['amount'];
    } else {
        $total_out += $txn['amount'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Transactions - BachatPay</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <?php $currentPage = 'transactions'; require_once __DIR__ . '/includes/navbar.php'; ?>

    <div class="lg:ml-64 px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-4xl font-bold text-gray-900 mb-2">
                <i class="fas fa-exchange-alt text-blue-600 mr-3"></i>All Transactions
            </h1>
            <p class="text-gray-600">Complete transaction history including deposits, withdrawals, and earnings</p>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">Total In</p>
                        <p class="text-3xl font-bold text-green-600 mt-2"><?php echo formatCurrency($total_in); ?></p>
                    </div>
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-arrow-down text-green-600 text-2xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">Total Out</p>
                        <p class="text-3xl font-bold text-red-600 mt-2"><?php echo formatCurrency($total_out); ?></p>
                    </div>
                    <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-arrow-up text-red-600 text-2xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">Net Flow</p>
                        <p class="text-3xl font-bold <?php echo ($total_in - $total_out) >= 0 ? 'text-blue-600' : 'text-red-600'; ?> mt-2">
                            <?php echo formatCurrency($total_in - $total_out); ?>
                        </p>
                    </div>
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-balance-scale text-blue-600 text-2xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">Total Count</p>
                        <p class="text-3xl font-bold text-purple-600 mt-2"><?php echo count($transactions); ?></p>
                    </div>
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-list text-purple-600 text-2xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters & Search -->
        <div class="bg-white rounded-lg shadow p-6 mb-8">
            <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Type</label>
                    <select name="type" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <?php foreach ($transaction_types as $key => $label): ?>
                        <option value="<?php echo $key; ?>" <?php echo $type_filter === $key ? 'selected' : ''; ?>>
                            <?php echo is_array($label) ? ucfirst(str_replace('_', ' ', $key)) : $label; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Sort By</label>
                    <select name="sort_by" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="date_desc" <?php echo $sort_by === 'date_desc' ? 'selected' : ''; ?>>Newest First</option>
                        <option value="date_asc" <?php echo $sort_by === 'date_asc' ? 'selected' : ''; ?>>Oldest First</option>
                        <option value="amount_desc" <?php echo $sort_by === 'amount_desc' ? 'selected' : ''; ?>>Highest Amount</option>
                        <option value="amount_asc" <?php echo $sort_by === 'amount_asc' ? 'selected' : ''; ?>>Lowest Amount</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Search</label>
                    <input type="text" name="search" placeholder="Search transactions..." value="<?php echo htmlspecialchars($search); ?>"
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
                            <th class="px-4 py-3 text-right text-sm font-semibold text-gray-700">Amount</th>
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
                                // Determine icon and color
                                if (strpos($txn['transaction_type'], 'deposit') !== false) {
                                    $icon = 'fas-arrow-down';
$typeColor = 'bg-green-100 text-green-800';
                                    $amountColor = 'text-green-600';
                                    $prefix = '+';
                                } elseif (strpos($txn['transaction_type'], 'cashback') !== false) {
                                    $icon = 'fas-coins';
                                    $typeColor = 'bg-blue-100 text-blue-800';
                                    $amountColor = 'text-blue-600';
                                    $prefix = '+';
                                } elseif (strpos($txn['transaction_type'], 'commission') !== false) {
                                    $icon = 'fas-sitemap';
                                    $typeColor = 'bg-purple-100 text-purple-800';
                                    $amountColor = 'text-purple-600';
                                    $prefix = '+';
                                } elseif (strpos($txn['transaction_type'], 'bonus') !== false) {
                                    $icon = 'fas-gift';
                                    $typeColor = 'bg-orange-100 text-orange-800';
                                    $amountColor = 'text-orange-600';
                                    $prefix = '+';
                                } else {
                                    $icon = 'fas-arrow-up';
                                    $typeColor = 'bg-red-100 text-red-800';
                                    $amountColor = 'text-red-600';
                                    $prefix = '-';
                                }
                                
                                $statusColor = $txn['status'] === 'completed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800';
                            ?>
                            <tr class="border-b hover:bg-gray-50 transition">
                                <td class="px-4 py-3">
                                    <span class="font-semibold text-gray-900"><?php echo date('M d, Y', strtotime($txn['transaction_date'])); ?></span><br>
                                    <span class="text-xs text-gray-500"><?php echo date('H:i', strtotime($txn['transaction_date'])); ?></span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="px-3 py-1 rounded-full text-xs font-semibold <?php echo $typeColor; ?>">
                                        <i class="fas <?php echo $icon; ?> mr-1"></i><?php echo ucfirst(str_replace('_', ' ', $txn['transaction_type'])); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <?php echo htmlspecialchars(substr($txn['description'] ?? 'N/A', 0, 50)) . (strlen($txn['description'] ?? '') > 50 ? '...' : ''); ?>
                                </td>
                                <td class="px-4 py-3 font-bold text-right <?php echo $amountColor; ?>">
                                    <?php echo $prefix; ?><?php echo formatCurrency($txn['amount']); ?>
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
    </div>
</body>
</html>
