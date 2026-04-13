<?php
/**
 * Admin Navbar Component
 * Specialized navbar for admin panel
 */

// Determine current page
$currentPage = 'dashboard';
if (isset($_GET['tab'])) {
    $currentPage = $_GET['tab'];
} elseif (basename($_SERVER['PHP_SELF']) === 'cron.php') {
    $currentPage = 'cron';
}

function buildAdminNavbar($currentPage = 'dashboard') {
    $navItems = [
        'dashboard' => ['icon' => 'fas-tachometer-alt', 'label' => 'Dashboard', 'url' => 'dashboard.php'],
        'users' => ['icon' => 'fas-users', 'label' => 'Users', 'url' => 'dashboard.php?tab=users'],
        'withdrawals' => ['icon' => 'fas-money-bill-wave', 'label' => 'Withdrawals', 'url' => 'dashboard.php?tab=withdrawals'],
        'transactions' => ['icon' => 'fas-exchange-alt', 'label' => 'Transactions', 'url' => 'dashboard.php?tab=transactions'],
        'investments' => ['icon' => 'fas-chart-bar', 'label' => 'Investments', 'url' => 'dashboard.php?tab=investments'],
        // 'password' => ['icon' => 'fas-key', 'label' => 'Password', 'url' => 'dashboard.php?tab=password'],
        'cron' => ['icon' => 'fas-clock', 'label' => 'Cron Jobs', 'url' => 'cron.php'],
    ];

    return [
        'items' => $navItems,
        'current' => $currentPage
    ];
}
?>

<!-- Admin Navigation Bar -->
<div class="bg-blue-600 text-white shadow-lg">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center py-4">
            <div class="flex items-center">
                <h1 class="text-2xl font-bold">BachatPay Admin Panel</h1>
            </div>
            <div class="flex items-center space-x-4">
                <span class="text-sm">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                <a href="../logout.php" class="bg-red-500 hover:bg-red-600 px-4 py-2 rounded text-sm">Logout</a>
                <a href="../dashboard.php" class="bg-green-500 hover:bg-green-600 px-4 py-2 rounded text-sm">User Panel</a>
            </div>
        </div>
    </div>
</div>

<!-- Admin Sub Navigation -->
<div class="bg-white shadow">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <nav class="flex space-x-8">
            <?php
            $navbar = buildAdminNavbar($currentPage);
            foreach ($navbar['items'] as $key => $item):
                $isActive = $navbar['current'] === $key;
            ?>
                <a href="<?php echo $item['url']; ?>"
                   class="py-4 px-1 border-b-2 font-medium text-sm <?php echo $isActive ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?>">
                    <i class="fas <?php echo $item['icon']; ?> mr-2"></i>
                    <?php echo $item['label']; ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </div>
</div>