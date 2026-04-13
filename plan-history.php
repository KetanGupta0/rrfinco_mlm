<?php
/**
 * BachatPay - Plan Investment History
 * View all past and current plan investments with earnings
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if (!isLoggedIn()) redirect('index.php');
$user = getCurrentUser();
if (!$user) { session_destroy(); redirect('index.php'); }

// Get all plan investments
$stmt = $pdo->prepare('
    SELECT pi.*, ip.plan_name, ip.daily_percentage, ip.monthly_percentage
    FROM plan_investments pi 
    JOIN investment_plans ip ON pi.plan_id = ip.plan_id 
    WHERE pi.user_id = ?
    ORDER BY pi.start_date DESC
');
$stmt->execute([$user['user_id']]);
$investments = $stmt->fetchAll();

// Calculate totals
$total_invested = 0;
$total_earned = 0;
$active_count = 0;
$matured_count = 0;

foreach ($investments as $inv) {
    $total_invested += $inv['investment_amount'];
    $total_earned += $inv['total_earned'];
    if ($inv['status'] === 'active') $active_count++;
    else $matured_count++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plan Investment History - BachatPay</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <?php $currentPage = 'plan-history'; require_once __DIR__ . '/includes/navbar.php'; ?>

    <div class="lg:ml-64 px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-4xl font-bold text-gray-900 mb-2">
                <i class="fas fa-history text-blue-600 mr-3"></i>Plan Investment History
            </h1>
            <p class="text-gray-600">Track all your investment plans and earnings</p>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">Total Invested</p>
                        <p class="text-3xl font-bold text-blue-600 mt-2"><?php echo formatCurrency($total_invested); ?></p>
                    </div>
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-arrow-down text-blue-600 text-2xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">Total Earned</p>
                        <p class="text-3xl font-bold text-green-600 mt-2"><?php echo formatCurrency($total_earned); ?></p>
                    </div>
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-arrow-up text-green-600 text-2xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">Active Plans</p>
                        <p class="text-3xl font-bold text-orange-600 mt-2"><?php echo $active_count; ?></p>
                    </div>
                    <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-play text-orange-600 text-2xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">Matured Plans</p>
                        <p class="text-3xl font-bold text-purple-600 mt-2"><?php echo $matured_count; ?></p>
                    </div>
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-check text-purple-600 text-2xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Investments Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-100 border-b">
                        <tr>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Plan Name</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Amount</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Daily %</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Start Date</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Maturity Date</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Earned</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($investments)): ?>
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                                <i class="fas fa-inbox text-4xl mb-2 block opacity-50"></i>
                                No investment plans yet
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($investments as $inv): ?>
                            <tr class="border-b hover:bg-gray-50 transition">
                                <td class="px-4 py-3">
                                    <span class="font-semibold text-gray-900"><?php echo htmlspecialchars($inv['plan_name']); ?></span>
                                </td>
                                <td class="px-4 py-3 font-semibold text-blue-600">
                                    <?php echo formatCurrency($inv['investment_amount']); ?>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="text-green-600"><?php echo formatPercentage($inv['daily_percentage']); ?></span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    <?php echo date('M d, Y', strtotime($inv['start_date'])); ?>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    <?php echo $inv['maturity_date'] ? date('M d, Y', strtotime($inv['maturity_date'])) : '—'; ?>
                                </td>
                                <td class="px-4 py-3 font-semibold text-green-600">
                                    <?php echo formatCurrency($inv['total_earned']); ?>
                                </td>
                                <td class="px-4 py-3">
                                    <?php if ($inv['status'] === 'active'): ?>
                                        <span class="px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">
                                            <i class="fas fa-circle text-xs mr-1"></i>Active
                                        </span>
                                    <?php else: ?>
                                        <span class="px-3 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-800">
                                            <i class="fas fa-check-circle text-xs mr-1"></i>Matured
                                        </span>
                                    <?php endif; ?>
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
            <a href="investment-plans.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded transition">
                <i class="fas fa-plus mr-2"></i>Invest in New Plan
            </a>
            <a href="dashboard.php" class="bg-gray-400 hover:bg-gray-500 text-white font-bold py-2 px-4 rounded transition">
                <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
            </a>
        </div>
    </div>
</body>
</html>
