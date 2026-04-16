<?php
/**
 * Shared admin navbar.
 */

$currentPage = $_GET['tab'] ?? 'dashboard';
$basename = basename($_SERVER['PHP_SELF']);

if ($basename === 'dashboard.php') {
    $currentPage = $_GET['tab'] ?? 'dashboard';
} elseif ($basename === 'investment-plans.php') {
    $currentPage = 'plans';
} elseif ($basename === 'badges.php') {
    $currentPage = 'badges';
} elseif ($basename === 'support-tickets.php') {
    $currentPage = 'support';
} elseif ($basename === 'configuration.php') {
    $currentPage = 'config';
} elseif ($basename === 'cron.php') {
    $currentPage = 'cron';
}

$adminNavItems = [
    'dashboard' => ['label' => 'Overview', 'icon' => 'fa-chart-pie', 'url' => 'dashboard.php'],
    'users' => ['label' => 'Users', 'icon' => 'fa-users', 'url' => 'dashboard.php?tab=users'],
    'withdrawals' => ['label' => 'Withdrawals', 'icon' => 'fa-money-bill-transfer', 'url' => 'dashboard.php?tab=withdrawals'],
    'transactions' => ['label' => 'Transactions', 'icon' => 'fa-arrow-right-arrow-left', 'url' => 'dashboard.php?tab=transactions'],
    'investments' => ['label' => 'Investments', 'icon' => 'fa-layer-group', 'url' => 'dashboard.php?tab=investments'],
    'plans' => ['label' => 'Plans', 'icon' => 'fa-credit-card', 'url' => 'investment-plans.php'],
    'badges' => ['label' => 'Badges', 'icon' => 'fa-medal', 'url' => 'badges.php'],
    'support' => ['label' => 'Support', 'icon' => 'fa-headset', 'url' => 'support-tickets.php'],
    'config' => ['label' => 'Configuration', 'icon' => 'fa-sliders', 'url' => 'configuration.php'],
    'cron' => ['label' => 'Cron', 'icon' => 'fa-clock-rotate-left', 'url' => 'cron.php'],
];
?>
<header class="admin-header">
    <div class="admin-header-top">
        <div class="admin-header-brand">
            <span class="admin-header-logo"><i class="fa-solid fa-shield-halved"></i></span>
            <div>
                <h1>BachatPay Admin Panel</h1>
                <p>Operations, reporting, and platform control</p>
            </div>
        </div>

        <button class="hamburger-btn" id="adminNavToggle" type="button" aria-label="Toggle admin navigation">
            <i class="fa-solid fa-bars"></i>
        </button>

        <div class="admin-header-user">
            <div class="user-info">
                <span class="user-name"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></span>
                <span class="user-role"><i class="fa-solid fa-star"></i>Administrator</span>
            </div>
            <div class="admin-header-actions">
                <a href="../dashboard.php" class="btn btn-ghost btn-small">
                    <i class="fa-solid fa-house"></i> User Panel
                </a>
                <a href="../logout.php" class="btn btn-danger btn-small">
                    <i class="fa-solid fa-right-from-bracket"></i> Logout
                </a>
            </div>
        </div>
    </div>

    <nav class="admin-nav" id="adminNav">
        <div class="admin-nav-inner">
            <?php foreach ($adminNavItems as $key => $item): ?>
                <a href="<?php echo htmlspecialchars($item['url']); ?>"
                   class="admin-nav-item <?php echo $currentPage === $key ? 'active' : ''; ?>">
                    <i class="fa-solid <?php echo htmlspecialchars($item['icon']); ?>"></i>
                    <span><?php echo htmlspecialchars($item['label']); ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </nav>
</header>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const toggle = document.getElementById('adminNavToggle');
    const nav = document.getElementById('adminNav');

    if (!toggle || !nav) {
        return;
    }

    toggle.addEventListener('click', function () {
        nav.classList.toggle('is-open');
    });

    window.addEventListener('resize', function () {
        if (window.innerWidth > 768) {
            nav.classList.remove('is-open');
        }
    });
});
</script>
