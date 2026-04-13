<?php
/**
 * Professional Navbar Component
 * Reusable navbar for all pages with responsive design
 */

// This file should be included at the top of every page after session setup
// Usage: <?php require_once __DIR__ . '/navbar.php';

if (!function_exists('buildNavbar')) {
    function buildNavbar($currentPage = 'dashboard') {
        global $user;
        
        $navGroups = [
            'primary' => [
                'label' => null,
                'items' => [
                    'dashboard' => ['icon' => 'fas-home', 'label' => 'Dashboard', 'url' => 'dashboard.php'],
                ]
            ],
            'wallet' => [
                'label' => 'Wallet & Transactions',
                'items' => [
                    'wallet' => ['icon' => 'fas-wallet', 'label' => 'Wallet', 'url' => 'wallet.php'],
                    'wallet-history' => ['icon' => 'fas-history', 'label' => 'Wallet History', 'url' => 'wallet-history.php'],
                    'deposit' => ['icon' => 'fas-plus-circle', 'label' => 'Deposit', 'url' => 'deposit.php'],
                    'payout' => ['icon' => 'fas-arrow-up', 'label' => 'Withdraw', 'url' => 'payout.php'],
                    'payout-history' => ['icon' => 'fas-history', 'label' => 'Withdrawal History', 'url' => 'payout-history.php'],
                    'transactions' => ['icon' => 'fas-exchange-alt', 'label' => 'Transactions', 'url' => 'transactions.php'],
                ]
            ],
            'investments' => [
                'label' => 'Investments',
                'items' => [
                    'investment-plans' => ['icon' => 'fas-chart-bar', 'label' => 'Plans', 'url' => 'investment-plans.php'],
                    'plan-history' => ['icon' => 'fas-history', 'label' => 'Plan History', 'url' => 'plan-history.php'],
                ]
            ],
            'network' => [
                'label' => 'Network & Earnings',
                'items' => [
                    'team' => ['icon' => 'fas-sitemap', 'label' => 'Team', 'url' => 'team.php'],
                    'earning-history' => ['icon' => 'fas-chart-line', 'label' => 'Earning History', 'url' => 'earning-history.php'],
                ]
            ],
            'achievements' => [
                'label' => 'Achievements',
                'items' => [
                    'badges' => ['icon' => 'fas-trophy', 'label' => 'Badges', 'url' => 'badges.php'],
                ]
            ],
            'account' => [
                'label' => 'Account',
                'items' => [
                    'settings' => ['icon' => 'fas-cogs', 'label' => 'Settings', 'url' => 'settings.php'],
                    'support-tickets' => ['icon' => 'fas-headset', 'label' => 'Support', 'url' => 'support-tickets.php'],
                ]
            ],
        ];

        // Add admin navigation if user is admin
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
            $navGroups['admin'] = [
                'label' => 'Administration',
                'items' => [
                    'admin-dashboard' => ['icon' => 'fas-tachometer-alt', 'label' => 'Admin Dashboard', 'url' => 'admin/dashboard.php'],
                ]
            ];
        }
        
        return [
            'groups' => $navGroups,
            'current' => $currentPage,
            'user' => $user
        ];
    }
}
?>

<!-- Mobile Menu Button (Hidden on Desktop) -->
<button id="mobileMenuBtn" class="fixed bottom-6 right-6 lg:hidden bg-blue-600 text-white p-4 rounded-full shadow-lg z-40" onclick="toggleMobileMenu()">
    <i class="fas fa-bars text-xl"></i>
</button>

<!-- Responsive Sidebar Navigation -->
<div id="sidebar" class="fixed left-0 top-0 h-screen w-64 bg-white shadow-lg z-50 transform -translate-x-full lg:translate-x-0 transition-transform duration-300">
    <!-- Close Button (Mobile Only) -->
    <button id="closeSidebarBtn" class="lg:hidden absolute top-4 right-4 text-gray-600 hover:text-gray-900" onclick="toggleMobileMenu()">
        <i class="fas fa-times text-2xl"></i>
    </button>

    <!-- Brand -->
    <div class="p-6 border-b">
        <div class="flex items-center space-x-3">
            <!-- <div class="w-10 h-10 bg-gradient-to-br from-blue-600 to-purple-600 rounded-lg flex items-center justify-center text-white font-bold">
                BP
            </div> -->
            <img src="../bachat-pay-mlm/assets/images/logos/logo.png" alt="Bachat Pay Logo" class="w-12 h-12">
            <div>
                <h1 class="text-lg font-bold text-gray-900">BachatPay</h1>
                <p class="text-xs text-gray-500">MLM Platform</p>
            </div>
        </div>
    </div>

    <!-- Navigation Items -->
    <nav class="p-4 space-y-2 overflow-y-auto scrollbar-thin max-h-[calc(100vh-180px)]">
        <?php 
        $navbar = buildNavbar($currentPage ?? 'dashboard');
        foreach ($navbar['groups'] as $groupKey => $group): 
        ?>
            <?php if ($group['label']): ?>
                <div class="px-4 pt-4 pb-2">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider"><?php echo $group['label']; ?></p>
                </div>
            <?php endif; ?>
            
            <?php foreach ($group['items'] as $key => $item): 
                $isActive = $navbar['current'] === $key;
            ?>
                <a href="<?php echo $item['url']; ?>" 
                   class="block px-4 py-3 rounded-lg transition <?php echo $isActive ? 'bg-blue-600 text-white' : 'text-gray-700 hover:bg-gray-100'; ?>">
                    <i class="fas <?php echo $item['icon']; ?> mr-3 w-5"></i>
                    <span><?php echo $item['label']; ?></span>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>

    <!-- Sidebar Footer -->
    <div class="absolute bottom-0 w-full p-4 border-t">
        <a href="logout.php" class="block w-full px-4 py-3 bg-red-600 text-white rounded-lg text-center font-medium hover:bg-red-700 transition">
            <i class="fas fa-sign-out-alt mr-2"></i>Logout
        </a>
    </div>
</div>

<!-- Top Navigation Bar -->
<nav class="bg-white shadow-md sticky top-0 z-30">
    <div class="p-4 flex justify-between items-center">
        <!-- Left Side -->
        <div class="flex items-center space-x-4">
            <button id="toggleSidebarBtn" class="lg:hidden text-gray-600 hover:text-gray-900" onclick="toggleSidebar()">
                <i class="fas fa-bars text-2xl"></i>
            </button>
            <div class="hidden sm:block">
                <h2 class="text-sm font-bold text-gray-900">Dashboard</h2>
                <p class="text-xs text-gray-500">MLM Financial Management</p>
            </div>
        </div>

        <!-- Right Side - User Profile & Balance -->
        <div class="flex items-center space-x-6">
            <!-- Balance Display -->
            <div class="hidden md:block text-right">
                <p class="text-xs text-gray-600">Wallet Balance</p>
                <p class="text-lg font-bold text-green-600"><?php echo formatCurrency($user['wallet_balance'] ?? 0); ?></p>
            </div>

            <!-- User Menu -->
            <div class="relative group">
                <button class="flex items-center space-x-2 px-4 py-2 rounded-lg hover:bg-gray-100 transition">
                    <div class="w-10 h-10 bg-blue-600 text-white rounded-full flex items-center justify-center font-bold">
                        <?php echo strtoupper(substr($user['username'] ?? 'U', 0, 1)); ?>
                    </div>
                    <div class="hidden sm:block text-left">
                        <p class="text-sm font-bold text-gray-900"><?php echo htmlspecialchars($user['first_name'] ?? $user['username'] ?? 'User'); ?></p>
                        <p class="text-xs text-gray-500">@<?php echo htmlspecialchars($user['username'] ?? 'user'); ?></p>
                    </div>
                    <i class="fas fa-chevron-down text-gray-600"></i>
                </button>

                <!-- Dropdown Menu -->
                <div class="absolute right-0 mt-0 w-48 bg-white rounded-lg shadow-xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200">
                    <div class="p-4 border-b">
                        <p class="text-sm font-bold text-gray-900"><?php echo htmlspecialchars($user['first_name'] ?? 'User'); ?></p>
                        <p class="text-xs text-gray-500"><?php echo htmlspecialchars($user['email'] ?? ''); ?></p>
                    </div>
                    <a href="wallet.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                        <i class="fas fa-wallet mr-2"></i>Wallet
                    </a>
                    <a href="team.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                        <i class="fas fa-users mr-2"></i>Team
                    </a>
                    <a href="badges.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                        <i class="fas fa-trophy mr-2"></i>Achievements
                    </a>
                    <a href="support-tickets.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                        <i class="fas fa-question-circle mr-2"></i>Help
                    </a>
                    <a href="settings.php?tab=profile" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                        <i class="fas fa-cog mr-2"></i>Settings
                    </a>
                    <a href="logout.php" class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50 border-t">
                        <i class="fas fa-sign-out-alt mr-2"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </div>
</nav>

<!-- Main Content Wrapper (Add this class to main content) -->
<div class="lg:ml-64">
    <!-- Page content goes here -->
</div>

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('-translate-x-full');
}

function toggleMobileMenu() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('-translate-x-full');
}

// Close sidebar when clicking on a nav item (mobile)
document.querySelectorAll('#sidebar a').forEach(link => {
    link.addEventListener('click', () => {
        if (window.innerWidth < 1024) {
            toggleMobileMenu();
        }
    });
});

// Close sidebar when clicking outside (mobile)
document.addEventListener('click', (e) => {
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.getElementById('toggleSidebarBtn');
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    
    if (window.innerWidth < 1024 && 
        !sidebar.contains(e.target) && 
        !toggleBtn?.contains(e.target) &&
        !mobileMenuBtn?.contains(e.target)) {
        sidebar.classList.add('-translate-x-full');
    }
});
</script>
