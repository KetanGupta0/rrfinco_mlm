<?php
/**
 * BachatPay - Deposit History Page
 * View all deposit transactions
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if (!isLoggedIn()) redirect('index.php');
$user = getCurrentUser();
if (!$user) { session_destroy(); redirect('index.php'); }

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$gateway_filter = $_GET['gateway'] ?? 'all';

// Build query
$where = 'user_id = ?';
$params = [$user['user_id']];

if ($status_filter !== 'all') {
    $where .= ' AND status = ?';
    $params[] = $status_filter;
}

if ($gateway_filter !== 'all') {
    $where .= ' AND payment_gateway = ?';
    $params[] = $gateway_filter;
}

// Get deposits
$stmt = $pdo->prepare("SELECT * FROM deposits WHERE $where ORDER BY created_at DESC");
$stmt->execute($params);
$deposits = $stmt->fetchAll();

// Calculate statistics
$total_deposits = array_sum(array_column($deposits, 'amount'));
$completed_count = count(array_filter($deposits, fn($d) => $d['status'] === 'completed'));
$pending_count = count(array_filter($deposits, fn($d) => $d['status'] === 'pending'));
$failed_count = count(array_filter($deposits, fn($d) => $d['status'] === 'failed'));

$gateways = ['stripe' => 'Stripe', 'razorpay' => 'Razorpay', 'paypal' => 'PayPal', 'bank_transfer' => 'Bank Transfer', 'upi' => 'UPI'];
$statuses = ['all' => 'All Statuses', 'pending' => 'Pending', 'completed' => 'Completed', 'failed' => 'Failed'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deposit History - BachatPay</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <?php $currentPage = 'deposit-history'; require_once __DIR__ . '/includes/navbar.php'; ?>

    <div class="lg:ml-64 px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-4xl font-bold text-gray-900 mb-2">
                <i class="fas fa-history text-blue-600 mr-3"></i>Deposit History
            </h1>
            <p class="text-gray-600">Track all your wallet deposits</p>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">Total Deposited</p>
                        <p class="text-3xl font-bold text-blue-600 mt-2"><?php echo formatCurrency($total_deposits); ?></p>
                    </div>
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-coins text-blue-600 text-2xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">Completed</p>
                        <p class="text-3xl font-bold text-green-600 mt-2"><?php echo $completed_count; ?></p>
                    </div>
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-check text-green-600 text-2xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">Pending</p>
                        <p class="text-3xl font-bold text-yellow-600 mt-2"><?php echo $pending_count; ?></p>
                    </div>
                    <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-clock text-yellow-600 text-2xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">Failed</p>
                        <p class="text-3xl font-bold text-red-600 mt-2"><?php echo $failed_count; ?></p>
                    </div>
                    <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-times text-red-600 text-2xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow p-6 mb-8">
            <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Status</label>
                    <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <?php foreach ($statuses as $key => $label): ?>
                        <option value="<?php echo $key; ?>" <?php echo $status_filter === $key ? 'selected' : ''; ?>>
                            <?php echo $label; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Payment Gateway</label>
                    <select name="gateway" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="all">All Gateways</option>
                        <?php foreach ($gateways as $key => $label): ?>
                        <option value="<?php echo $key; ?>" <?php echo $gateway_filter === $key ? 'selected' : ''; ?>>
                            <?php echo $label; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="flex items-end">
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 rounded transition">
                        <i class="fas fa-filter mr-2"></i>Filter
                    </button>
                </div>
            </form>
        </div>

        <!-- Deposits Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-100 border-b">
                        <tr>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Date</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Amount</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Gateway</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Transaction ID</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($deposits)): ?>
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                                <i class="fas fa-inbox text-4xl mb-2 block opacity-50"></i>
                                No deposits found
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($deposits as $dep): 
                                $statusColor = match($dep['status']) {
                                    'completed' => 'bg-green-100 text-green-800',
                                    'pending' => 'bg-yellow-100 text-yellow-800',
                                    'failed' => 'bg-red-100 text-red-800',
                                    default => 'bg-gray-100 text-gray-800'
                                };
                                $gatewayIcon = match($dep['payment_gateway']) {
                                    'stripe' => 'fas-credit-card',
                                    'razorpay' => 'fas-rupee-sign',
                                    'paypal' => 'fas-paypal',
                                    'bank_transfer' => 'fas-building',
                                    'upi' => 'fas-mobile-alt',
                                    default => 'fas-wallet'
                                };
                            ?>
                            <tr class="border-b hover:bg-gray-50 transition">
                                <td class="px-4 py-3">
                                    <span class="font-semibold text-gray-900"><?php echo date('M d, Y', strtotime($dep['created_at'])); ?></span><br>
                                    <span class="text-xs text-gray-500"><?php echo date('H:i', strtotime($dep['created_at'])); ?></span>
                                </td>
                                <td class="px-4 py-3 font-bold text-blue-600">
                                    <?php echo formatCurrency($dep['amount']); ?>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="flex items-center">
                                        <i class="fas <?php echo $gatewayIcon; ?> mr-2 text-blue-600"></i>
                                        <?php echo ucfirst(str_replace('_', ' ', $dep['payment_gateway'])); ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600 font-mono">
                                    <?php echo htmlspecialchars($dep['transaction_id']); ?>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="px-3 py-1 rounded-full text-xs font-semibold <?php echo $statusColor; ?>">
                                        <?php echo ucfirst($dep['status']); ?>
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
            <a href="deposit.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded transition">
                <i class="fas fa-plus mr-2"></i>New Deposit
            </a>
            <a href="wallet.php" class="bg-gray-400 hover:bg-gray-500 text-white font-bold py-2 px-4 rounded transition">
                <i class="fas fa-wallet mr-2"></i>My Wallet
            </a>
        </div>
    </div>
</body>
</html>
