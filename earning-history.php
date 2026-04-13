<?php
/**
 * BachatPay - Earnings History
 * Comprehensive view of all earnings (ROI, Bonuses, Commissions, Cashbacks)
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

// Build query for earnings only
$where = "user_id = ? AND (transaction_type LIKE '%cashback%' OR transaction_type LIKE '%commission%' OR transaction_type LIKE '%bonus%' OR transaction_type LIKE '%roi%')";
$params = [$user['user_id']];

if ($type_filter !== 'all') {
    $where .= " AND transaction_type LIKE ?";
    $params[] = '%' . $type_filter . '%';
}

if (!empty($search)) {
    $where .= " AND description LIKE ?";
    $params[] = '%' . $search . '%';
}

// Determine sort order
$order_by = match($sort_by) {
    'date_asc' => 'transaction_date ASC',
    'amount_desc' => 'amount DESC',
    'amount_asc' => 'amount ASC',
    default => 'transaction_date DESC'
};

// Get earnings
$stmt = $pdo->prepare("
    SELECT *, DATE(transaction_date) as earning_date, MONTH(transaction_date) as month 
    FROM transactions 
    WHERE $where 
    ORDER BY $order_by 
    LIMIT 200
");
$stmt->execute($params);
$earnings = $stmt->fetchAll();

// Calculate totals by type
$totals = ['cashback' => 0, 'commission' => 0, 'bonus' => 0, 'roi' => 0];
foreach ($earnings as $earn) {
    if (strpos($earn['transaction_type'], 'cashback') !== false) $totals['cashback'] += $earn['amount'];
    elseif (strpos($earn['transaction_type'], 'commission') !== false) $totals['commission'] += $earn['amount'];
    elseif (strpos($earn['transaction_type'], 'bonus') !== false) $totals['bonus'] += $earn['amount'];
    elseif (strpos($earn['transaction_type'], 'roi') !== false) $totals['roi'] += $earn['amount'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Earnings History - BachatPay</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <?php $currentPage = 'earning-history'; require_once __DIR__ . '/includes/navbar.php'; ?>

    <div class="lg:ml-64 px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-4xl font-bold text-gray-900 mb-2">
                <i class="fas fa-chart-line text-blue-600 mr-3"></i>Earnings History
            </h1>
            <p class="text-gray-600">Track all your income from various sources</p>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">Total Earned</p>
                        <p class="text-2xl font-bold text-green-600 mt-2">
                            <?php echo formatCurrency(array_sum($totals)); ?>
                        </p>
                    </div>
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-coins text-green-600 text-2xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">Cashback</p>
                        <p class="text-2xl font-bold text-blue-600 mt-2"><?php echo formatCurrency($totals['cashback']); ?></p>
                    </div>
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-coins text-blue-600 text-2xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">Commission</p>
                        <p class="text-2xl font-bold text-purple-600 mt-2"><?php echo formatCurrency($totals['commission']); ?></p>
                    </div>
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-sitemap text-purple-600 text-2xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">Bonus</p>
                        <p class="text-2xl font-bold text-orange-600 mt-2"><?php echo formatCurrency($totals['bonus']); ?></p>
                    </div>
                    <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-gift text-orange-600 text-2xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">ROI</p>
                        <p class="text-2xl font-bold text-indigo-600 mt-2"><?php echo formatCurrency($totals['roi']); ?></p>
                    </div>
                    <div class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-percentage text-indigo-600 text-2xl"></i>
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
                        <option value="all" <?php echo $type_filter === 'all' ? 'selected' : ''; ?>>All Types</option>
                        <option value="cashback" <?php echo $type_filter === 'cashback' ? 'selected' : ''; ?>>Cashback</option>
                        <option value="commission" <?php echo $type_filter === 'commission' ? 'selected' : ''; ?>>Commission</option>
                        <option value="bonus" <?php echo $type_filter === 'bonus' ? 'selected' : ''; ?>>Bonus</option>
                        <option value="roi" <?php echo $type_filter === 'roi' ? 'selected' : ''; ?>>ROI</option>
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
                    <input type="text" name="search" placeholder="Search description..." value="<?php echo htmlspecialchars($search); ?>"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>

                <div class="flex items-end">
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 rounded transition">
                        <i class="fas fa-filter mr-2"></i>Filter
                    </button>
                </div>
            </form>
        </div>

        <!-- Earnings Table -->
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
                        <?php if (empty($earnings)): ?>
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                                <i class="fas fa-inbox text-4xl mb-2 block opacity-50"></i>
                                No earnings found
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($earnings as $earn): 
                                $icon = 'fas-coins';
                                $typeColor = 'bg-gray-100 text-gray-800';
                                
                                if (strpos($earn['transaction_type'], 'cashback') !== false) {
                                    $icon = 'fas-coins';
                                    $typeColor = 'bg-blue-100 text-blue-800';
                                } elseif (strpos($earn['transaction_type'], 'commission') !== false) {
                                    $icon = 'fas-sitemap';
                                    $typeColor = 'bg-purple-100 text-purple-800';
                                } elseif (strpos($earn['transaction_type'], 'bonus') !== false) {
                                    $icon = 'fas-gift';
                                    $typeColor = 'bg-orange-100 text-orange-800';
                                } elseif (strpos($earn['transaction_type'], 'roi') !== false) {
                                    $icon = 'fas-percentage';
                                    $typeColor = 'bg-indigo-100 text-indigo-800';
                                }
                                
                                $statusColor = $earn['status'] === 'completed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800';
                            ?>
                            <tr class="border-b hover:bg-gray-50 transition">
                                <td class="px-4 py-3">
                                    <span class="font-semibold text-gray-900"><?php echo date('M d, Y', strtotime($earn['transaction_date'])); ?></span><br>
                                    <span class="text-xs text-gray-500"><?php echo date('H:i', strtotime($earn['transaction_date'])); ?></span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="px-3 py-1 rounded-full text-xs font-semibold <?php echo $typeColor; ?>">
                                        <i class="fas <?php echo $icon; ?> mr-1"></i><?php echo ucfirst(str_replace('_', ' ', $earn['transaction_type'])); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    <?php echo htmlspecialchars(substr($earn['description'], 0, 50)) . (strlen($earn['description']) > 50 ? '...' : ''); ?>
                                </td>
                                <td class="px-4 py-3 font-bold text-green-600">
                                    +<?php echo formatCurrency($earn['amount']); ?>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="px-3 py-1 rounded-full text-xs font-semibold <?php echo $statusColor; ?>">
                                        <?php echo ucfirst($earn['status']); ?>
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
