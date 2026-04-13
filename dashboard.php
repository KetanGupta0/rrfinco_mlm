<?php
/**
 * BachatPay MLM Platform - User Dashboard
 * Complete financial dashboard with all features - OPTIMIZED WITH NAVBAR
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

// Verify user is logged in
if (!isLoggedIn()) {
    redirect('index.php');
}

// Get current user data
$user = getCurrentUser();
if (!$user) {
    session_destroy();
    redirect('index.php');
}

$currentPage = 'dashboard';

// Generate CSRF token
$csrf_token = generateCSRFToken();

// Get user summary data
$summary = generateUserSummary($user['user_id']);
$downlineStats = getDownlineStats($user['user_id']);

// Fetch real earning data from database for last 30 days
$last30Days = getLast30DaysEarnings($user['user_id']);

// Get transactions for wallet ledger
$stmt = $pdo->prepare('
    SELECT * FROM transactions 
    WHERE user_id = ? 
    ORDER BY transaction_date DESC 
    LIMIT 10
');
$stmt->execute([$user['user_id']]);
$transactions = $stmt->fetchAll() ?? [];

// Get withdrawal requests
$stmt = $pdo->prepare('
    SELECT wr.* FROM withdrawal_requests wr
    WHERE wr.user_id = ? 
    ORDER BY wr.requested_at DESC 
    LIMIT 5
');
$stmt->execute([$user['user_id']]);
$withdrawalRequests = $stmt->fetchAll() ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BachatPay - User Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .dashboard-card {
            transition: all 0.3s ease;
        }
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }
        .stat-number {
            font-size: 1.875rem;
            font-weight: bold;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .gradient-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php require_once __DIR__ . '/includes/navbar.php'; ?>

    <div class="lg:ml-64 p-4 sm:p-6 lg:p-8">
        <!-- Page Header -->
        <div class="mb-8">
            <h1 class="text-4xl font-bold text-gray-900 mb-2">Dashboard</h1>
            <p class="text-gray-600">Welcome back! Here's your financial overview and earnings tracking</p>
        </div>

        <!-- STAT CARDS - 4 Column Grid Responsive -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Active Investment Card -->
            <div class="bg-white rounded-lg shadow p-6 dashboard-card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm font-medium">Active Investment</p>
                        <div class="stat-number mt-2">
                            <?php echo formatCurrency(($summary['active_investment']['investment_amount'] ?? 0)); ?>
                        </div>
                    </div>
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center text-blue-600 text-2xl">
                        <i class="fas fa-wallet"></i>
                    </div>
                </div>
            </div>

            <!-- Wallet Balance Card -->
            <div class="bg-white rounded-lg shadow p-6 dashboard-card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm font-medium">Wallet Balance</p>
                        <div class="stat-number mt-2">
                            <?php echo formatCurrency($user['wallet_balance'] ?? 0); ?>
                        </div>
                    </div>
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center text-green-600 text-2xl">
                        <i class="fas fa-piggy-bank"></i>
                    </div>
                </div>
            </div>

            <!-- Monthly Cashback Card -->
            <div class="bg-white rounded-lg shadow p-6 dashboard-card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm font-medium">Monthly Cashback</p>
                        <div class="stat-number mt-2">
                            <?php echo formatCurrency($summary['monthly_cashback'] ?? 0); ?>
                        </div>
                    </div>
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center text-purple-600 text-2xl">
                        <i class="fas fa-coins"></i>
                    </div>
                </div>
            </div>

            <!-- Downline Business Card -->
            <div class="bg-white rounded-lg shadow p-6 dashboard-card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm font-medium">Downline Business</p>
                        <div class="stat-number mt-2">
                            <?php echo formatCurrency($downlineStats['total_downline_business'] ?? 0); ?>
                        </div>
                    </div>
                    <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center text-orange-600 text-2xl">
                        <i class="fas fa-chart-line"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- BONUS STATUS CARDS - 2 Column Responsive -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- Bonus 1 Card -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold text-gray-900">Bonus 1 - Maintenance</h3>
                    <?php $bonus1Qualified = $summary['bonus_1']['qualifies'] ?? false; ?>
                    <span class="px-3 py-1 rounded-full text-xs font-bold <?php echo $bonus1Qualified ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                        <?php echo $bonus1Qualified ? '✓ QUALIFIED' : '○ PENDING'; ?>
                    </span>
                </div>
                <div class="space-y-3">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Required Balance:</span>
                        <span class="font-semibold"><?php echo formatCurrency($summary['bonus_1']['min_balance_required'] ?? 2000); ?></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Current Balance:</span>
                        <span class="font-semibold"><?php echo formatCurrency($summary['bonus_1']['current_balance'] ?? 0); ?></span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2 mt-2">
                        <?php $progress = min(100, (($summary['bonus_1']['current_balance'] ?? 0) / ($summary['bonus_1']['min_balance_required'] ?? 1)) * 100); ?>
                        <div class="bg-green-500 h-2 rounded-full transition-all" style="width: <?php echo $progress; ?>%"></div>
                    </div>
                    <p class="text-xs text-gray-500 mt-2"><i class="fas fa-info-circle mr-1"></i>20% extra cashback when qualified</p>
                </div>
            </div>

            <!-- Bonus 2 Card -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold text-gray-900">Bonus 2 - Weekly Challenge</h3>
                    <?php $bonus2Qualified = $summary['bonus_2']['qualifies'] ?? false; ?>
                    <span class="px-3 py-1 rounded-full text-xs font-bold <?php echo $bonus2Qualified ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'; ?>">
                        <?php echo $bonus2Qualified ? '✓ ELIGIBLE' : '○ UPCOMING'; ?>
                    </span>
                </div>
                <div class="space-y-3">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">This Week Business:</span>
                        <span class="font-semibold"><?php echo formatCurrency($summary['bonus_2']['total_business'] ?? 0); ?></span>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div class="text-center p-3 bg-blue-50 rounded-lg">
                            <p class="text-xs text-gray-600 mb-1">Tier 1 Target</p>
                            <p class="font-bold text-blue-600">₹50,000</p>
                        </div>
                        <div class="text-center p-3 bg-purple-50 rounded-lg">
                            <p class="text-xs text-gray-600 mb-1">Tier 2 Target</p>
                            <p class="font-bold text-purple-600">₹1,00,000</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TABS SECTION -->
        <div class="bg-white rounded-lg shadow">
            <!-- Tab Buttons -->
            <div class="border-b border-gray-200 flex flex-wrap">
                <button class="tab-button flex-1 sm:flex-none px-4 sm:px-6 py-4 border-b-2 border-blue-500 font-medium text-gray-700 active text-sm sm:text-base" onclick="switchTab(event, 'calculator')">
                    <i class="fas fa-calculator mr-2"></i><span class="hidden sm:inline">Calculator</span>
                </button>
                <button class="tab-button flex-1 sm:flex-none px-4 sm:px-6 py-4 border-b-2 border-transparent font-medium text-gray-700 hover:border-gray-300 text-sm sm:text-base" onclick="switchTab(event, 'earnings')">
                    <i class="fas fa-chart-line mr-2"></i><span class="hidden sm:inline">Earnings</span>
                </button>
                <button class="tab-button flex-1 sm:flex-none px-4 sm:px-6 py-4 border-b-2 border-transparent font-medium text-gray-700 hover:border-gray-300 text-sm sm:text-base" onclick="switchTab(event, 'wallet')">
                    <i class="fas fa-exchange-alt mr-2"></i><span class="hidden sm:inline">Wallet</span>
                </button>
                <button class="tab-button flex-1 sm:flex-none px-4 sm:px-6 py-4 border-b-2 border-transparent font-medium text-gray-700 hover:border-gray-300 text-sm sm:text-base" onclick="switchTab(event, 'withdrawal')">
                    <i class="fas fa-money-bill-wave mr-2"></i><span class="hidden sm:inline">Withdraw</span>
                </button>
            </div>

            <div class="p-4 sm:p-6">
                <!-- Calculator Tab -->
                <div id="calculator" class="tab-content active">
                    <h3 class="text-xl font-bold mb-6">Investment Return Calculator</h3>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Investment Amount (₹)</label>
                            <input type="number" id="calcInvestment" placeholder="Enter amount" value="100000" 
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                   oninput="calculateReturns()">
                            <div class="grid grid-cols-2 gap-4 mt-4">
                                <div class="p-4 bg-blue-50 rounded-lg">
                                    <p class="text-xs text-gray-600">Daily Rate</p>
                                    <p class="text-lg font-bold text-blue-600" id="dailyRate">0.14%</p>
                                </div>
                                <div class="p-4 bg-green-50 rounded-lg">
                                    <p class="text-xs text-gray-600">Monthly Rate</p>
                                    <p class="text-lg font-bold text-green-600" id="monthlyRate">4.20%</p>
                                </div>
                            </div>
                        </div>
                        <div class="p-6 bg-gradient-to-br from-purple-50 to-blue-50 rounded-lg">
                            <h4 class="font-bold mb-4 text-gray-900">Projected Returns</h4>
                            <div class="space-y-3">
                                <div class="flex justify-between">
                                    <span class="text-gray-700">Daily Cashback:</span>
                                    <span class="font-bold text-purple-600" id="dailyCashback">₹140.00</span>
                                </div>
                                <div class="border-t pt-3 flex justify-between">
                                    <span class="text-gray-700">Monthly Cashback:</span>
                                    <span class="font-bold text-lg text-purple-700" id="monthlyCashback">₹4,200.00</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-700">With Bonus 1 (20%):</span>
                                    <span class="font-bold text-blue-600" id="withBonus1">₹5,040.00</span>
                                </div>
                                <div class="border-t pt-3">
                                    <p class="text-xs text-gray-600 mb-2">Yearly (with bonuses)</p>
                                    <p class="text-2xl font-bold text-green-600" id="yearlyIncome">₹60,480.00</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Earnings Tab -->
                <div id="earnings" class="tab-content">
                    <h3 class="text-xl font-bold mb-6">30-Day Earnings Trend</h3>
                    <div class="w-full overflow-auto">
                        <canvas id="earningsChart" style="max-height: 400px; min-width: 300px;"></canvas>
                    </div>
                </div>

                <!-- Wallet Tab -->
                <div id="wallet" class="tab-content">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-xl font-bold">Transaction Ledger</h3>
                        <a href="wallet.php" class="text-blue-600 hover:text-blue-800 text-sm">View All →</a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-700 uppercase">Date</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-700 uppercase">Type</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-700 uppercase hidden sm:table-cell">Description</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-700 uppercase">Amount</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-700 uppercase">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                <?php if (empty($transactions)): ?>
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                                        <i class="fas fa-inbox text-3xl mb-2 block opacity-50"></i>
                                        No transactions yet
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($transactions as $txn): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 text-sm"><?php echo date('M d, Y', strtotime($txn['transaction_date'])); ?></td>
                                        <td class="px-4 py-3 font-medium text-sm"><?php echo ucfirst(str_replace('_', ' ', $txn['transaction_type'])); ?></td>
                                        <td class="px-4 py-3 text-sm hidden sm:table-cell text-gray-600"><?php echo htmlspecialchars(substr($txn['description'] ?? '', 0, 30)); ?></td>
                                        <td class="px-4 py-3 text-right font-semibold text-sm <?php echo strpos($txn['transaction_type'], 'withdrawal') !== false ? 'text-red-600' : 'text-green-600'; ?>">
                                            <?php echo strpos($txn['transaction_type'], 'withdrawal') !== false ? '-' : '+'; ?><?php echo formatCurrency($txn['amount']); ?>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="px-2 py-1 rounded text-xs font-semibold <?php echo $txn['status'] === 'completed' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
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

                <!-- Withdrawal Tab -->
                <div id="withdrawal" class="tab-content">
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        <div class="lg:col-span-2">
                            <h3 class="text-xl font-bold mb-6">Quick Withdrawal</h3>
                            <div class="bg-blue-50 p-6 rounded-lg mb-6 border border-blue-200">
                                <p class="text-sm text-blue-800">
                                    <i class="fas fa-info-circle mr-2"></i>
                                    <strong>Available Balance:</strong> <?php echo formatCurrency($user['wallet_balance']); ?>
                                </p>
                            </div>
                            <a href="payout.php" class="inline-block w-full sm:w-auto px-6 py-3 bg-blue-600 text-white font-bold rounded-lg hover:bg-blue-700 text-center transition">
                                <i class="fas fa-arrow-up mr-2"></i>Request Withdrawal
                            </a>
                        </div>

                        <div>
                            <h4 class="text-lg font-bold mb-4">Recent Requests</h4>
                            <?php if (empty($withdrawalRequests)): ?>
                            <p class="text-gray-500 text-center py-8">No withdrawal requests yet</p>
                            <?php else: ?>
                            <div class="space-y-3">
                                <?php foreach ($withdrawalRequests as $req): 
                                    $statusColor = match($req['status']) {
                                        'completed' => 'bg-green-100 text-green-800',
                                        'pending' => 'bg-yellow-100 text-yellow-800',
                                        'rejected' => 'bg-red-100 text-red-800',
                                        default => 'bg-gray-100 text-gray-800'
                                    };
                                ?>
                                <div class="p-4 border rounded-lg hover:shadow-md transition">
                                    <div class="flex justify-between mb-2">
                                        <span class="font-semibold text-gray-900"><?php echo formatCurrency($req['amount']); ?></span>
                                        <span class="text-xs px-2 py-1 rounded-full <?php echo $statusColor; ?>">
                                            <?php echo ucfirst($req['status']); ?>
                                        </span>
                                    </div>
                                    <p class="text-xs text-gray-600"><?php echo date('M d, Y', strtotime($req['requested_at'])); ?></p>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Action Links -->
        <div class="mt-8 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
            <a href="wallet.php" class="bg-white rounded-lg p-4 text-center hover:shadow-md transition">
                <i class="fas fa-wallet text-2xl text-blue-600 mb-2"></i>
                <p class="text-sm font-medium text-gray-900">My Wallet</p>
            </a>
            <a href="deposit.php" class="bg-white rounded-lg p-4 text-center hover:shadow-md transition">
                <i class="fas fa-plus-circle text-2xl text-green-600 mb-2"></i>
                <p class="text-sm font-medium text-gray-900">Deposit</p>
            </a>
            <a href="investment-plans.php" class="bg-white rounded-lg p-4 text-center hover:shadow-md transition">
                <i class="fas fa-chart-bar text-2xl text-purple-600 mb-2"></i>
                <p class="text-sm font-medium text-gray-900">Invest</p>
            </a>
            <a href="team.php" class="bg-white rounded-lg p-4 text-center hover:shadow-md transition">
                <i class="fas fa-sitemap text-2xl text-orange-600 mb-2"></i>
                <p class="text-sm font-medium text-gray-900">Team</p>
            </a>
            <a href="earning-history.php" class="bg-white rounded-lg p-4 text-center hover:shadow-md transition">
                <i class="fas fa-chart-line text-2xl text-indigo-600 mb-2"></i>
                <p class="text-sm font-medium text-gray-900">Earnings</p>
            </a>
        </div>
    </div>

    <script>
        function switchTab(event, tabName) {
            event.preventDefault();
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-button').forEach(el => {
                el.classList.remove('border-blue-500');
                el.classList.add('border-transparent');
            });
            document.getElementById(tabName).classList.add('active');
            event.target.closest('.tab-button').classList.remove('border-transparent');
            event.target.closest('.tab-button').classList.add('border-blue-500');
        }

        function calculateReturns() {
            const investment = parseFloat(document.getElementById('calcInvestment').value) || 0;
            let dailyRate = 0.001;
            if (investment >= 26000 && investment <= 100000) dailyRate = 0.0012;
            else if (investment >= 101000 && investment <= 500000) dailyRate = 0.0014;
            else if (investment > 500000) dailyRate = 0.0016;

            const monthlyRate = dailyRate * 30;
            const dailyCashback = investment * dailyRate;
            const monthlyCashback = investment * monthlyRate;
            const bonus1 = monthlyCashback * 0.20;
            const totalMonthly = monthlyCashback + bonus1;
            const yearly = totalMonthly * 12;

            document.getElementById('dailyRate').textContent = (dailyRate * 100).toFixed(2) + '%';
            document.getElementById('monthlyRate').textContent = (monthlyRate * 100).toFixed(2) + '%';
            document.getElementById('dailyCashback').textContent = '₹' + dailyCashback.toLocaleString('en-IN', {minimumFractionDigits: 2});
            document.getElementById('monthlyCashback').textContent = '₹' + monthlyCashback.toLocaleString('en-IN', {minimumFractionDigits: 2});
            document.getElementById('withBonus1').textContent = '₹' + totalMonthly.toLocaleString('en-IN', {minimumFractionDigits: 2});
            document.getElementById('yearlyIncome').textContent = '₹' + yearly.toLocaleString('en-IN', {minimumFractionDigits: 2});
        }

        document.addEventListener('DOMContentLoaded', function() {
            const earningsData = <?php echo json_encode($last30Days); ?>;
            const ctx = document.getElementById('earningsChart');
            if (ctx) {
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: earningsData.map(d => d.date),
                        datasets: [{
                            label: 'Daily Cashback (₹)',
                            data: earningsData.map(d => d.amount),
                            borderColor: 'rgb(99, 102, 241)',
                            backgroundColor: 'rgba(99, 102, 241, 0.1)',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4,
                            pointRadius: 4,
                            pointBackgroundColor: 'rgb(99, 102, 241)',
                            pointBorderWidth: 2,
                            pointHoverRadius: 6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: {
                                display: true,
                                labels: { font: { size: 12, weight: 'bold' } }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return '₹' + value;
                                    }
                                }
                            }
                        }
                    }
                });
            }
            calculateReturns();
        });
    </script>
</body>
</html>
