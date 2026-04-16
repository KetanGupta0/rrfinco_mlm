<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/common.php';

requireAdmin();

$pageTitle = 'Admin Dashboard';
$tabs = ['dashboard', 'users', 'withdrawals', 'transactions', 'investments'];
$activeTab = in_array($_GET['tab'] ?? 'dashboard', $tabs, true) ? ($_GET['tab'] ?? 'dashboard') : 'dashboard';
$csrfToken = generateCSRFToken();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        try {
            $action = $_POST['action'] ?? '';

            if ($action === 'update_user_status') {
                $stmt = $pdo->prepare('UPDATE users SET status = ? WHERE user_id = ?');
                $stmt->execute([$_POST['status'] ?? '', (int) ($_POST['user_id'] ?? 0)]);
                $message = 'User status updated successfully.';
            } elseif ($action === 'update_user_role') {
                $stmt = $pdo->prepare('UPDATE users SET role = ? WHERE user_id = ?');
                $stmt->execute([$_POST['role'] ?? '', (int) ($_POST['user_id'] ?? 0)]);
                $message = 'User role updated successfully.';
            } elseif ($action === 'manual_credit' || $action === 'manual_debit') {
                $userId = (int) ($_POST['user_id'] ?? 0);
                $amount = (float) ($_POST['amount'] ?? 0);
                $description = trim($_POST['description'] ?? '');
                if ($userId <= 0 || $amount <= 0 || $description === '') {
                    throw new RuntimeException('Amount and description are required.');
                }
                $op = $action === 'manual_credit' ? 'add' : 'sub';
                $txAmount = $action === 'manual_credit' ? $amount : -$amount;
                updateWalletBalance($userId, $amount, $op);
                recordTransaction($userId, $action, $txAmount, $description, $_SESSION['user_id']);
                $message = $action === 'manual_credit' ? 'Manual credit added successfully.' : 'Manual debit completed successfully.';
            } elseif ($action === 'process_withdrawal') {
                $withdrawalId = (int) ($_POST['withdrawal_id'] ?? 0);
                $status = $_POST['status'] ?? '';
                $notes = trim($_POST['notes'] ?? '');
                if ($withdrawalId <= 0 || !in_array($status, ['completed', 'rejected'], true)) {
                    throw new RuntimeException('Invalid withdrawal action.');
                }
                $pdo->beginTransaction();
                $stmt = $pdo->prepare('UPDATE withdrawal_requests SET status = ?, completed_at = NOW(), admin_notes = ? WHERE withdrawal_id = ?');
                $stmt->execute([$status, $notes !== '' ? $notes : null, $withdrawalId]);
                $stmt = $pdo->prepare('
                    UPDATE transactions t
                    JOIN withdrawal_requests w ON w.user_id = t.user_id
                    SET t.status = ?
                    WHERE w.withdrawal_id = ? AND t.transaction_type = "withdrawal" AND ABS(t.amount) = w.amount
                    ORDER BY t.transaction_date DESC LIMIT 1
                ');
                $stmt->execute([$status, $withdrawalId]);
                $pdo->commit();
                $message = 'Withdrawal request processed successfully.';
            } elseif ($action === 'delete_transaction') {
                $stmt = $pdo->prepare('DELETE FROM transactions WHERE transaction_id = ? AND transaction_type IN ("manual_credit", "manual_debit")');
                $stmt->execute([(int) ($_POST['transaction_id'] ?? 0)]);
                $message = 'Manual transaction deleted successfully.';
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e->getMessage() ?: 'Admin action failed.';
            logError('Admin dashboard action failed', $e->getMessage());
        }
    }
}

$stats = [
    'total_users' => 0,
    'active_users' => 0,
    'admin_users' => 0,
    'wallet_total' => 0,
    'investment_total' => 0,
    'pending_withdrawals' => 0,
    'pending_withdrawal_amount' => 0,
    'today_registrations' => 0,
    'today_deposits' => 0,
];
$chartLabels = [];
$chartValues = [];

try {
    $stats['total_users'] = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE role = "user"')->fetchColumn();
    $stats['active_users'] = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE role = "user" AND status = "active"')->fetchColumn();
    $stats['admin_users'] = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE role = "admin"')->fetchColumn();
    $stats['wallet_total'] = (float) ($pdo->query('SELECT COALESCE(SUM(wallet_balance), 0) FROM users')->fetchColumn() ?: 0);
    $stats['investment_total'] = (float) ($pdo->query('SELECT COALESCE(SUM(investment_amount), 0) FROM investments WHERE status = "active"')->fetchColumn() ?: 0);
    $stats['pending_withdrawals'] = (int) $pdo->query('SELECT COUNT(*) FROM withdrawal_requests WHERE status = "pending"')->fetchColumn();
    $stats['pending_withdrawal_amount'] = (float) ($pdo->query('SELECT COALESCE(SUM(amount), 0) FROM withdrawal_requests WHERE status = "pending"')->fetchColumn() ?: 0);
    $stats['today_registrations'] = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE DATE(registration_date) = CURDATE()')->fetchColumn();
    $stats['today_deposits'] = (float) ($pdo->query('SELECT COALESCE(SUM(amount), 0) FROM deposits WHERE DATE(created_at) = CURDATE() AND status = "completed"')->fetchColumn() ?: 0);

    $chartRows = $pdo->query('
        SELECT DATE(transaction_date) chart_date, COALESCE(SUM(amount), 0) total_amount
        FROM transactions
        WHERE transaction_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY DATE(transaction_date)
        ORDER BY chart_date ASC
    ')->fetchAll();
    foreach ($chartRows as $row) {
        $chartLabels[] = date('d M', strtotime($row['chart_date']));
        $chartValues[] = round((float) $row['total_amount'], 2);
    }
} catch (Throwable $e) {
    logError('Admin dashboard stats failed', $e->getMessage());
}

$rows = [];
$sortKey = '';
$sortDir = '';
$resultCount = 0;

if ($activeTab === 'users') {
    $search = trim($_GET['search'] ?? '');
    $status = $_GET['status'] ?? '';
    $role = $_GET['role'] ?? '';
    $sortMap = [
        'username' => 'u.username',
        'registration_date' => 'u.registration_date',
        'wallet_balance' => 'u.wallet_balance',
    ];
    $sortKey = adminGetSortableColumn($_GET['sort'] ?? 'registration_date', $sortMap, 'registration_date');
    $sortDir = adminGetSortDirection($_GET['dir'] ?? 'desc');
    $where = ['1=1'];
    $params = [];
    if ($search !== '') {
        $where[] = '(u.username LIKE ? OR u.email LIKE ? OR COALESCE(u.phone, "") LIKE ?)';
        $like = adminLike($search);
        array_push($params, $like, $like, $like);
    }
    if (in_array($status, ['active', 'inactive', 'suspended'], true)) {
        $where[] = 'u.status = ?';
        $params[] = $status;
    }
    if (in_array($role, ['user', 'admin'], true)) {
        $where[] = 'u.role = ?';
        $params[] = $role;
    }
    $stmt = $pdo->prepare('
        SELECT u.user_id, u.username, u.email, u.phone, u.wallet_balance, u.active_investment,
               u.total_cashback_earned, u.total_commission_earned, u.registration_date, u.last_login, u.status, u.role
        FROM users u
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY ' . $sortMap[$sortKey] . ' ' . $sortDir . '
        LIMIT 250
    ');
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    $resultCount = count($rows);
}

if ($activeTab === 'withdrawals') {
    $search = trim($_GET['search'] ?? '');
    $status = $_GET['status'] ?? '';
    $method = $_GET['method'] ?? '';
    $sortMap = [
        'requested_at' => 'w.requested_at',
        'amount' => 'w.amount',
        'username' => 'u.username',
    ];
    $sortKey = adminGetSortableColumn($_GET['sort'] ?? 'requested_at', $sortMap, 'requested_at');
    $sortDir = adminGetSortDirection($_GET['dir'] ?? 'desc');
    $where = ['1=1'];
    $params = [];
    if ($search !== '') {
        $where[] = '(u.username LIKE ? OR u.email LIKE ? OR COALESCE(b.account_holder_name, "") LIKE ? OR COALESCE(w.upi_id, "") LIKE ?)';
        $like = adminLike($search);
        array_push($params, $like, $like, $like, $like);
    }
    if (in_array($status, ['pending', 'completed', 'rejected'], true)) {
        $where[] = 'w.status = ?';
        $params[] = $status;
    }
    if (in_array($method, ['bank', 'upi'], true)) {
        $where[] = 'w.method = ?';
        $params[] = $method;
    }
    $stmt = $pdo->prepare('
        SELECT w.withdrawal_id, w.amount, w.method, w.status, w.requested_at, w.completed_at, w.admin_notes, w.upi_id,
               u.username, u.email, b.account_holder_name, b.bank_name
        FROM withdrawal_requests w
        JOIN users u ON u.user_id = w.user_id
        LEFT JOIN user_bank_accounts b ON b.bank_account_id = w.bank_account_id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY ' . $sortMap[$sortKey] . ' ' . $sortDir . '
        LIMIT 250
    ');
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    $resultCount = count($rows);
}

if ($activeTab === 'transactions') {
    $search = trim($_GET['search'] ?? '');
    $type = trim($_GET['type'] ?? '');
    $status = trim($_GET['status'] ?? '');
    $sortMap = [
        'transaction_date' => 't.transaction_date',
        'amount' => 't.amount',
        'transaction_type' => 't.transaction_type',
        'username' => 'u.username',
    ];
    $sortKey = adminGetSortableColumn($_GET['sort'] ?? 'transaction_date', $sortMap, 'transaction_date');
    $sortDir = adminGetSortDirection($_GET['dir'] ?? 'desc');
    $where = ['1=1'];
    $params = [];
    if ($search !== '') {
        $where[] = '(u.username LIKE ? OR u.email LIKE ? OR COALESCE(t.description, "") LIKE ?)';
        $like = adminLike($search);
        array_push($params, $like, $like, $like);
    }
    if ($type !== '') {
        $where[] = 't.transaction_type = ?';
        $params[] = $type;
    }
    if ($status !== '' && in_array($status, ['pending', 'completed', 'cancelled'], true)) {
        $where[] = 't.status = ?';
        $params[] = $status;
    }
    $stmt = $pdo->prepare('
        SELECT t.transaction_id, t.transaction_type, t.amount, t.wallet_balance, t.description, t.status, t.transaction_date,
               u.username, u.email, ru.username AS related_username
        FROM transactions t
        JOIN users u ON u.user_id = t.user_id
        LEFT JOIN users ru ON ru.user_id = t.related_user_id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY ' . $sortMap[$sortKey] . ' ' . $sortDir . '
        LIMIT 300
    ');
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    $resultCount = count($rows);
    $transactionTypes = $pdo->query('SELECT DISTINCT transaction_type FROM transactions ORDER BY transaction_type')->fetchAll(PDO::FETCH_COLUMN);
}

if ($activeTab === 'investments') {
    $search = trim($_GET['search'] ?? '');
    $status = trim($_GET['status'] ?? '');
    $sortMap = [
        'investment_date' => 'i.investment_date',
        'investment_amount' => 'i.investment_amount',
        'daily_rate' => 'i.daily_rate',
        'username' => 'u.username',
    ];
    $sortKey = adminGetSortableColumn($_GET['sort'] ?? 'investment_date', $sortMap, 'investment_date');
    $sortDir = adminGetSortDirection($_GET['dir'] ?? 'desc');
    $where = ['1=1'];
    $params = [];
    if ($search !== '') {
        $where[] = '(u.username LIKE ? OR u.email LIKE ?)';
        $like = adminLike($search);
        array_push($params, $like, $like);
    }
    if ($status !== '' && in_array($status, ['active', 'inactive', 'matured'], true)) {
        $where[] = 'i.status = ?';
        $params[] = $status;
    }
    $stmt = $pdo->prepare('
        SELECT i.investment_id, i.investment_amount, i.daily_rate, i.investment_date, i.last_cashback_processed, i.status,
               u.username, u.email
        FROM investments i
        JOIN users u ON u.user_id = i.user_id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY ' . $sortMap[$sortKey] . ' ' . $sortDir . '
        LIMIT 250
    ');
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    $resultCount = count($rows);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - BachatPay Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
</head>

<body>
    <div class="admin-shell">
        <?php require __DIR__ . '/navbar.php'; ?>
        <main class="admin-content">
            <div class="print-only">
                <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
                <p>Generated on <?php echo date('d M Y, h:i A'); ?></p>
            </div>
            <div class="admin-page-header">
                <div class="admin-page-title">
                    <h2>Admin Dashboard</h2>
                    <p>Cleaner admin workflows with responsive tables, stronger filters, and print-ready reporting.</p>
                </div>
                <?php if ($activeTab !== 'dashboard'): ?>
                    <div class="page-tools screen-only">
                        <button type="button" class="btn btn-secondary" onclick="window.print()"><i class="fa-solid fa-print"></i> Print Report</button>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($message !== ''): ?><div class="alert alert-success"><i class="fa-solid fa-circle-check"></i>
                    <div><?php echo htmlspecialchars($message); ?></div>
                </div><?php endif; ?>
            <?php if ($error !== ''): ?><div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i>
                    <div><?php echo htmlspecialchars($error); ?></div>
                </div><?php endif; ?>

            <?php if ($activeTab === 'dashboard'): ?>
                <section class="hero-panel">
                    <div class="card hero-card">
                        <div class="card-header">
                            <div>
                                <h3>Operations Snapshot</h3>
                                <p class="lead">The report tabs in this dashboard now support practical filtering and cleaner printing for routine admin work.</p>
                            </div>
                        </div>
                        <div class="hero-actions">
                            <a class="btn btn-primary" href="dashboard.php?tab=users"><i class="fa-solid fa-users"></i> User Report</a>
                            <a class="btn btn-secondary" href="dashboard.php?tab=withdrawals"><i class="fa-solid fa-money-check-dollar"></i> Withdrawal Report</a>
                            <a class="btn btn-ghost" href="cron.php"><i class="fa-solid fa-bolt"></i> Cron Center</a>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header">
                            <div>
                                <h3>Quick Links</h3>
                                <p>Management pages beyond the tabbed reports.</p>
                            </div>
                        </div>
                        <div class="quick-links">
                            <a class="quick-link" href="investment-plans.php">
                                <div><strong>Investment Plans</strong><span>Plan ranges and return setup</span></div><i class="fa-solid fa-arrow-right"></i>
                            </a>
                            <a class="quick-link" href="badges.php">
                                <div><strong>Badges</strong><span>Achievement rules and visuals</span></div><i class="fa-solid fa-arrow-right"></i>
                            </a>
                            <a class="quick-link" href="support-tickets.php">
                                <div><strong>Support Tickets</strong><span>Ticket queue and reply flow</span></div><i class="fa-solid fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </section>
                <section class="stats-grid">
                    <article class="stat-card"><span class="label">Total Users</span><span class="value"><?php echo number_format($stats['total_users']); ?></span><span class="meta"><?php echo number_format($stats['active_users']); ?> active</span></article>
                    <article class="stat-card"><span class="label">Admin Accounts</span><span class="value"><?php echo number_format($stats['admin_users']); ?></span><span class="meta">Shared login system</span></article>
                    <article class="stat-card"><span class="label">Wallet Balance</span><span class="value"><?php echo formatCurrency($stats['wallet_total']); ?></span><span class="meta">Across all users</span></article>
                    <article class="stat-card"><span class="label">Active Investments</span><span class="value"><?php echo formatCurrency($stats['investment_total']); ?></span><span class="meta">Currently earning</span></article>
                    <article class="stat-card"><span class="label">Pending Withdrawals</span><span class="value"><?php echo number_format($stats['pending_withdrawals']); ?></span><span class="meta"><?php echo formatCurrency($stats['pending_withdrawal_amount']); ?></span></article>
                    <article class="stat-card"><span class="label">Today Deposits</span><span class="value"><?php echo formatCurrency($stats['today_deposits']); ?></span><span class="meta"><?php echo number_format($stats['today_registrations']); ?> registrations today</span></article>
                </section>
                <section class="split-panel">
                    <div class="card">
                        <div class="card-header">
                            <div>
                                <h3>7 Day Cash Movement</h3>
                                <p>Recent net transaction totals.</p>
                            </div>
                        </div>
                        <div class="chart-area">
                            <canvas id="cashflowChart"></canvas>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header">
                            <div>
                                <h3>Priority Actions</h3>
                                <p>Fast follow-up areas.</p>
                            </div>
                        </div>
                        <div class="kpi-list">
                            <div class="kpi-item">
                                <div><strong>Withdrawals</strong><span class="table-subtext">Pending approvals</span></div><strong><?php echo number_format($stats['pending_withdrawals']); ?></strong>
                            </div>
                            <div class="kpi-item">
                                <div><strong>Users Joined Today</strong><span class="table-subtext">Registration count</span></div><strong><?php echo number_format($stats['today_registrations']); ?></strong>
                            </div>
                            <div class="kpi-item">
                                <div><strong>Deposits Today</strong><span class="table-subtext">Completed only</span></div><strong><?php echo formatCurrency($stats['today_deposits']); ?></strong>
                            </div>
                        </div>
                    </div>
                </section>
            <?php endif; ?>
            <?php if ($activeTab !== 'dashboard'): $sortDirNext = $sortDir === 'asc' ? 'desc' : 'asc';
            endif; ?>

            <?php if ($activeTab === 'users'): ?>
                <section class="card filters-card screen-only">
                    <div class="card-header">
                        <div>
                            <h3>User Filters</h3>
                            <p>Search by username, email, or phone and segment by status and role.</p>
                        </div>
                    </div>
                    <form method="get" class="filters-form">
                        <input type="hidden" name="tab" value="users">
                        <div class="form-group"><label class="form-label">Search</label><input class="form-input" type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Username, email, phone"></div>
                        <div class="form-group"><label class="form-label">Status</label><select class="form-select" name="status">
                                <option value="">All</option><?php foreach (['active', 'inactive', 'suspended'] as $option): ?><option value="<?php echo $option; ?>" <?php echo $status === $option ? 'selected' : ''; ?>><?php echo ucfirst($option); ?></option><?php endforeach; ?>
                            </select></div>
                        <div class="form-group"><label class="form-label">Role</label><select class="form-select" name="role">
                                <option value="">All</option><?php foreach (['user', 'admin'] as $option): ?><option value="<?php echo $option; ?>" <?php echo $role === $option ? 'selected' : ''; ?>><?php echo ucfirst($option); ?></option><?php endforeach; ?>
                            </select></div>
                        <div class="filter-actions"><button class="btn btn-primary" type="submit">Apply</button><a class="btn btn-secondary" href="dashboard.php?tab=users">Reset</a></div>
                    </form>
                </section>
                <section class="card">
                    <div class="toolbar">
                        <div>
                            <h3 style="margin:0;">Users Report</h3>
                            <div class="toolbar-meta"><?php echo number_format($resultCount); ?> records</div>
                        </div>
                    </div>
                    <div class="table-wrapper">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th><a class="table-sort <?php echo $sortKey === 'username' ? 'active' : ''; ?>" href="<?php echo adminQueryString(['sort' => 'username', 'dir' => $sortKey === 'username' ? $sortDirNext : 'asc']); ?>">User <i class="fa-solid fa-sort"></i></a></th>
                                    <th>Status</th>
                                    <th><a class="table-sort <?php echo $sortKey === 'wallet_balance' ? 'active' : ''; ?>" href="<?php echo adminQueryString(['sort' => 'wallet_balance', 'dir' => $sortKey === 'wallet_balance' ? $sortDirNext : 'desc']); ?>">Wallet <i class="fa-solid fa-sort"></i></a></th>
                                    <th><a class="table-sort <?php echo $sortKey === 'registration_date' ? 'active' : ''; ?>" href="<?php echo adminQueryString(['sort' => 'registration_date', 'dir' => $sortKey === 'registration_date' ? $sortDirNext : 'desc']); ?>">Joined <i class="fa-solid fa-sort"></i></a></th>
                                    <th class="screen-only">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$rows): ?><tr>
                                        <td colspan="5">
                                            <div class="empty-state"><i class="fa-solid fa-users-slash"></i>
                                                <div>No users found for these filters.</div>
                                            </div>
                                        </td>
                                    </tr><?php endif; ?>
                                <?php foreach ($rows as $user): ?><tr>
                                        <td><strong><?php echo htmlspecialchars($user['username']); ?></strong><span class="table-subtext"><?php echo htmlspecialchars($user['email']); ?></span><span class="table-subtext"><?php echo adminText($user['phone']); ?></span></td>
                                        <td><span class="badge badge-<?php echo adminStatusClass($user['status']); ?>"><?php echo ucfirst($user['status']); ?></span><span class="table-subtext"><?php echo ucfirst($user['role']); ?></span></td>
                                        <td><strong><?php echo formatCurrency($user['wallet_balance']); ?></strong><span class="table-subtext">Investment: <?php echo formatCurrency($user['active_investment']); ?></span><span class="table-subtext">Cashback: <?php echo formatCurrency($user['total_cashback_earned']); ?></span></td>
                                        <td><?php echo adminDateTime($user['registration_date']); ?><span class="table-subtext">Last login: <?php echo adminDateTime($user['last_login']); ?></span></td>
                                        <td class="screen-only">
                                            <div class="btn-group"><button class="btn btn-small btn-primary" type="button" onclick="openUserModal('statusModal', <?php echo (int) $user['user_id']; ?>, '<?php echo htmlspecialchars($user['username'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($user['status'], ENT_QUOTES); ?>')">Status</button><button class="btn btn-small btn-secondary" type="button" onclick="openUserModal('roleModal', <?php echo (int) $user['user_id']; ?>, '<?php echo htmlspecialchars($user['username'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($user['role'], ENT_QUOTES); ?>')">Role</button><button class="btn btn-small btn-success" type="button" onclick="openMoneyModal('creditModal', <?php echo (int) $user['user_id']; ?>, '<?php echo htmlspecialchars($user['username'], ENT_QUOTES); ?>')">Credit</button><button class="btn btn-small btn-danger" type="button" onclick="openMoneyModal('debitModal', <?php echo (int) $user['user_id']; ?>, '<?php echo htmlspecialchars($user['username'], ENT_QUOTES); ?>')">Debit</button></div>
                                        </td>
                                    </tr><?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($activeTab === 'withdrawals'): ?>
                <section class="card filters-card screen-only">
                    <div class="card-header">
                        <div>
                            <h3>Withdrawal Filters</h3>
                            <p>Search by user or payout target and segment by status and method.</p>
                        </div>
                    </div>
                    <form method="get" class="filters-form">
                        <input type="hidden" name="tab" value="withdrawals">
                        <div class="form-group"><label class="form-label">Search</label><input class="form-input" type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="User, email, account holder, UPI"></div>
                        <div class="form-group"><label class="form-label">Status</label><select class="form-select" name="status">
                                <option value="">All</option><?php foreach (['pending', 'completed', 'rejected'] as $option): ?><option value="<?php echo $option; ?>" <?php echo $status === $option ? 'selected' : ''; ?>><?php echo ucfirst($option); ?></option><?php endforeach; ?>
                            </select></div>
                        <div class="form-group"><label class="form-label">Method</label><select class="form-select" name="method">
                                <option value="">All</option><?php foreach (['bank', 'upi'] as $option): ?><option value="<?php echo $option; ?>" <?php echo $method === $option ? 'selected' : ''; ?>><?php echo strtoupper($option); ?></option><?php endforeach; ?>
                            </select></div>
                        <div class="filter-actions"><button class="btn btn-primary" type="submit">Apply</button><a class="btn btn-secondary" href="dashboard.php?tab=withdrawals">Reset</a></div>
                    </form>
                </section>
                <section class="card">
                    <div class="toolbar">
                        <div>
                            <h3 style="margin:0;">Withdrawal Report</h3>
                            <div class="toolbar-meta"><?php echo number_format($resultCount); ?> requests</div>
                        </div>
                    </div>
                    <div class="table-wrapper">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th><a class="table-sort <?php echo $sortKey === 'username' ? 'active' : ''; ?>" href="<?php echo adminQueryString(['sort' => 'username', 'dir' => $sortKey === 'username' ? $sortDirNext : 'asc']); ?>">User <i class="fa-solid fa-sort"></i></a></th>
                                    <th><a class="table-sort <?php echo $sortKey === 'amount' ? 'active' : ''; ?>" href="<?php echo adminQueryString(['sort' => 'amount', 'dir' => $sortKey === 'amount' ? $sortDirNext : 'desc']); ?>">Amount <i class="fa-solid fa-sort"></i></a></th>
                                    <th>Method</th>
                                    <th>Status</th>
                                    <th><a class="table-sort <?php echo $sortKey === 'requested_at' ? 'active' : ''; ?>" href="<?php echo adminQueryString(['sort' => 'requested_at', 'dir' => $sortKey === 'requested_at' ? $sortDirNext : 'desc']); ?>">Requested <i class="fa-solid fa-sort"></i></a></th>
                                    <th class="screen-only">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$rows): ?><tr>
                                        <td colspan="6">
                                            <div class="empty-state"><i class="fa-solid fa-money-bill-wave"></i>
                                                <div>No withdrawals found for these filters.</div>
                                            </div>
                                        </td>
                                    </tr><?php endif; ?>
                                <?php foreach ($rows as $row): ?><tr>
                                        <td><strong><?php echo htmlspecialchars($row['username']); ?></strong><span class="table-subtext"><?php echo htmlspecialchars($row['email']); ?></span><span class="table-subtext"><?php echo $row['method'] === 'bank' ? adminText($row['account_holder_name']) . ' | ' . adminText($row['bank_name']) : 'UPI: ' . adminText($row['upi_id']); ?></span></td>
                                        <td><strong><?php echo formatCurrency($row['amount']); ?></strong></td>
                                        <td><span class="badge badge-<?php echo $row['method'] === 'bank' ? 'primary' : 'info'; ?>"><?php echo strtoupper($row['method']); ?></span></td>
                                        <td><span class="badge badge-<?php echo adminStatusClass($row['status']); ?>"><?php echo ucfirst($row['status']); ?></span><?php if (!empty($row['admin_notes'])): ?><span class="table-subtext">Admin note: <?php echo htmlspecialchars($row['admin_notes']); ?></span><?php endif; ?></td>
                                        <td><?php echo adminDateTime($row['requested_at']); ?><span class="table-subtext">Completed: <?php echo adminDateTime($row['completed_at']); ?></span></td>
                                        <td class="screen-only"><?php if ($row['status'] === 'pending'): ?><button class="btn btn-small btn-primary" type="button" onclick="openWithdrawalModal(<?php echo (int) $row['withdrawal_id']; ?>, '<?php echo htmlspecialchars($row['username'], ENT_QUOTES); ?>', '<?php echo number_format((float) $row['amount'], 2, '.', ''); ?>')">Process</button><?php else: ?><span class="badge badge-muted">Processed</span><?php endif; ?></td>
                                    </tr><?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($activeTab === 'transactions'): ?>
                <section class="card filters-card screen-only">
                    <div class="card-header">
                        <div>
                            <h3>Transaction Filters</h3>
                            <p>Search the ledger and print it for reconciliation when needed.</p>
                        </div>
                    </div>
                    <form method="get" class="filters-form">
                        <input type="hidden" name="tab" value="transactions">
                        <div class="form-group"><label class="form-label">Search</label><input class="form-input" type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="User or description"></div>
                        <div class="form-group"><label class="form-label">Type</label><select class="form-select" name="type">
                                <option value="">All</option><?php foreach ($transactionTypes as $option): ?><option value="<?php echo htmlspecialchars($option); ?>" <?php echo $type === $option ? 'selected' : ''; ?>><?php echo ucwords(str_replace('_', ' ', $option)); ?></option><?php endforeach; ?>
                            </select></div>
                        <div class="form-group"><label class="form-label">Status</label><select class="form-select" name="status">
                                <option value="">All</option><?php foreach (['pending', 'completed', 'cancelled'] as $option): ?><option value="<?php echo $option; ?>" <?php echo $status === $option ? 'selected' : ''; ?>><?php echo ucfirst($option); ?></option><?php endforeach; ?>
                            </select></div>
                        <div class="filter-actions"><button class="btn btn-primary" type="submit">Apply</button><a class="btn btn-secondary" href="dashboard.php?tab=transactions">Reset</a></div>
                    </form>
                </section>
                <section class="card">
                    <div class="toolbar">
                        <div>
                            <h3 style="margin:0;">Transactions Report</h3>
                            <div class="toolbar-meta"><?php echo number_format($resultCount); ?> transactions</div>
                        </div>
                    </div>
                    <div class="table-wrapper">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th><a class="table-sort <?php echo $sortKey === 'username' ? 'active' : ''; ?>" href="<?php echo adminQueryString(['sort' => 'username', 'dir' => $sortKey === 'username' ? $sortDirNext : 'asc']); ?>">User <i class="fa-solid fa-sort"></i></a></th>
                                    <th><a class="table-sort <?php echo $sortKey === 'transaction_type' ? 'active' : ''; ?>" href="<?php echo adminQueryString(['sort' => 'transaction_type', 'dir' => $sortKey === 'transaction_type' ? $sortDirNext : 'asc']); ?>">Type <i class="fa-solid fa-sort"></i></a></th>
                                    <th><a class="table-sort <?php echo $sortKey === 'amount' ? 'active' : ''; ?>" href="<?php echo adminQueryString(['sort' => 'amount', 'dir' => $sortKey === 'amount' ? $sortDirNext : 'desc']); ?>">Amount <i class="fa-solid fa-sort"></i></a></th>
                                    <th>Description</th>
                                    <th><a class="table-sort <?php echo $sortKey === 'transaction_date' ? 'active' : ''; ?>" href="<?php echo adminQueryString(['sort' => 'transaction_date', 'dir' => $sortKey === 'transaction_date' ? $sortDirNext : 'desc']); ?>">Date <i class="fa-solid fa-sort"></i></a></th>
                                    <th class="screen-only">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$rows): ?><tr>
                                        <td colspan="6">
                                            <div class="empty-state"><i class="fa-solid fa-receipt"></i>
                                                <div>No transactions found for these filters.</div>
                                            </div>
                                        </td>
                                    </tr><?php endif; ?>
                                <?php foreach ($rows as $row): ?><tr>
                                        <td><strong><?php echo htmlspecialchars($row['username']); ?></strong><span class="table-subtext"><?php echo htmlspecialchars($row['email']); ?></span><?php if (!empty($row['related_username'])): ?><span class="table-subtext">Related: <?php echo htmlspecialchars($row['related_username']); ?></span><?php endif; ?></td>
                                        <td><span class="badge badge-primary"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $row['transaction_type']))); ?></span><span class="table-subtext"><?php echo ucfirst($row['status']); ?></span></td>
                                        <td><strong style="color: <?php echo (float) $row['amount'] >= 0 ? 'var(--success)' : 'var(--danger)'; ?>;"><?php echo formatCurrency(abs((float) $row['amount'])); ?></strong><span class="table-subtext">Wallet after: <?php echo formatCurrency($row['wallet_balance']); ?></span></td>
                                        <td><?php echo adminText($row['description']); ?></td>
                                        <td><?php echo adminDateTime($row['transaction_date']); ?></td>
                                        <td class="screen-only"><?php if (in_array($row['transaction_type'], ['manual_credit', 'manual_debit'], true)): ?><form method="post" onsubmit="return confirm('Delete this manual transaction?');"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>"><input type="hidden" name="action" value="delete_transaction"><input type="hidden" name="transaction_id" value="<?php echo (int) $row['transaction_id']; ?>"><button class="btn btn-small btn-danger" type="submit">Delete</button></form><?php else: ?><span class="badge badge-muted">Locked</span><?php endif; ?></td>
                                    </tr><?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($activeTab === 'investments'): ?>
                <section class="card filters-card screen-only">
                    <div class="card-header">
                        <div>
                            <h3>Investment Filters</h3>
                            <p>Sort investments by date, amount, or rate and print the report when needed.</p>
                        </div>
                    </div>
                    <form method="get" class="filters-form">
                        <input type="hidden" name="tab" value="investments">
                        <div class="form-group"><label class="form-label">Search</label><input class="form-input" type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Username or email"></div>
                        <div class="form-group"><label class="form-label">Status</label><select class="form-select" name="status">
                                <option value="">All</option><?php foreach (['active', 'inactive', 'matured'] as $option): ?><option value="<?php echo $option; ?>" <?php echo $status === $option ? 'selected' : ''; ?>><?php echo ucfirst($option); ?></option><?php endforeach; ?>
                            </select></div>
                        <div class="filter-actions"><button class="btn btn-primary" type="submit">Apply</button><a class="btn btn-secondary" href="dashboard.php?tab=investments">Reset</a></div>
                    </form>
                </section>
                <section class="card">
                    <div class="toolbar">
                        <div>
                            <h3 style="margin:0;">Investments Report</h3>
                            <div class="toolbar-meta"><?php echo number_format($resultCount); ?> investments</div>
                        </div>
                    </div>
                    <div class="table-wrapper">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th><a class="table-sort <?php echo $sortKey === 'username' ? 'active' : ''; ?>" href="<?php echo adminQueryString(['sort' => 'username', 'dir' => $sortKey === 'username' ? $sortDirNext : 'asc']); ?>">User <i class="fa-solid fa-sort"></i></a></th>
                                    <th><a class="table-sort <?php echo $sortKey === 'investment_amount' ? 'active' : ''; ?>" href="<?php echo adminQueryString(['sort' => 'investment_amount', 'dir' => $sortKey === 'investment_amount' ? $sortDirNext : 'desc']); ?>">Amount <i class="fa-solid fa-sort"></i></a></th>
                                    <th><a class="table-sort <?php echo $sortKey === 'daily_rate' ? 'active' : ''; ?>" href="<?php echo adminQueryString(['sort' => 'daily_rate', 'dir' => $sortKey === 'daily_rate' ? $sortDirNext : 'desc']); ?>">Daily Rate <i class="fa-solid fa-sort"></i></a></th>
                                    <th><a class="table-sort <?php echo $sortKey === 'investment_date' ? 'active' : ''; ?>" href="<?php echo adminQueryString(['sort' => 'investment_date', 'dir' => $sortKey === 'investment_date' ? $sortDirNext : 'desc']); ?>">Started <i class="fa-solid fa-sort"></i></a></th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$rows): ?><tr>
                                        <td colspan="5">
                                            <div class="empty-state"><i class="fa-solid fa-chart-line"></i>
                                                <div>No investments found for these filters.</div>
                                            </div>
                                        </td>
                                    </tr><?php endif; ?>
                                <?php foreach ($rows as $row): ?><tr>
                                        <td><strong><?php echo htmlspecialchars($row['username']); ?></strong><span class="table-subtext"><?php echo htmlspecialchars($row['email']); ?></span></td>
                                        <td><strong><?php echo formatCurrency($row['investment_amount']); ?></strong></td>
                                        <td><strong><?php echo adminPercent(((float) $row['daily_rate']) * 100, 2); ?></strong><span class="table-subtext">Last cashback: <?php echo adminDate($row['last_cashback_processed']); ?></span></td>
                                        <td><?php echo adminDateTime($row['investment_date']); ?></td>
                                        <td><span class="badge badge-<?php echo adminStatusClass($row['status']); ?>"><?php echo ucfirst($row['status']); ?></span></td>
                                    </tr><?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>
        </main>

        <div id="statusModal" class="modal" style="display:none;">
            <div class="card" style="max-width:480px;width:calc(100% - 24px);margin:0 auto;">
                <div class="card-header">
                    <h3>Update Status</h3>
                </div>
                <form method="post" class="card-body"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>"><input type="hidden" name="action" value="update_user_status"><input type="hidden" name="user_id" id="status_user_id">
                    <div class="form-group"><label class="form-label">User</label><input class="form-input" id="status_username" type="text" disabled></div>
                    <div class="form-group"><label class="form-label">Status</label><select class="form-select" name="status" id="status_value"><?php foreach (['active', 'inactive', 'suspended'] as $option): ?><option value="<?php echo $option; ?>"><?php echo ucfirst($option); ?></option><?php endforeach; ?></select></div>
                    <div class="btn-group"><button class="btn btn-primary" type="submit">Save</button><button class="btn btn-secondary" type="button" onclick="closeModal('statusModal')">Cancel</button></div>
                </form>
            </div>
        </div>
        <div id="roleModal" class="modal" style="display:none;">
            <div class="card" style="max-width:480px;width:calc(100% - 24px);margin:0 auto;">
                <div class="card-header">
                    <h3>Update Role</h3>
                </div>
                <form method="post" class="card-body"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>"><input type="hidden" name="action" value="update_user_role"><input type="hidden" name="user_id" id="role_user_id">
                    <div class="form-group"><label class="form-label">User</label><input class="form-input" id="role_username" type="text" disabled></div>
                    <div class="form-group"><label class="form-label">Role</label><select class="form-select" name="role" id="role_value"><?php foreach (['user', 'admin'] as $option): ?><option value="<?php echo $option; ?>"><?php echo ucfirst($option); ?></option><?php endforeach; ?></select></div>
                    <div class="btn-group"><button class="btn btn-primary" type="submit">Save</button><button class="btn btn-secondary" type="button" onclick="closeModal('roleModal')">Cancel</button></div>
                </form>
            </div>
        </div>
        <div id="creditModal" class="modal" style="display:none;">
            <div class="card" style="max-width:520px;width:calc(100% - 24px);margin:0 auto;">
                <div class="card-header">
                    <h3>Manual Credit</h3>
                </div>
                <form method="post" class="card-body"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>"><input type="hidden" name="action" value="manual_credit"><input type="hidden" name="user_id" id="credit_user_id">
                    <div class="form-group"><label class="form-label">User</label><input class="form-input" id="credit_username" type="text" disabled></div>
                    <div class="form-group"><label class="form-label">Amount</label><input class="form-input" type="number" min="0.01" step="0.01" name="amount" required></div>
                    <div class="form-group"><label class="form-label">Description</label><textarea class="form-textarea" name="description" required></textarea></div>
                    <div class="btn-group"><button class="btn btn-success" type="submit">Credit</button><button class="btn btn-secondary" type="button" onclick="closeModal('creditModal')">Cancel</button></div>
                </form>
            </div>
        </div>
        <div id="debitModal" class="modal" style="display:none;">
            <div class="card" style="max-width:520px;width:calc(100% - 24px);margin:0 auto;">
                <div class="card-header">
                    <h3>Manual Debit</h3>
                </div>
                <form method="post" class="card-body"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>"><input type="hidden" name="action" value="manual_debit"><input type="hidden" name="user_id" id="debit_user_id">
                    <div class="form-group"><label class="form-label">User</label><input class="form-input" id="debit_username" type="text" disabled></div>
                    <div class="form-group"><label class="form-label">Amount</label><input class="form-input" type="number" min="0.01" step="0.01" name="amount" required></div>
                    <div class="form-group"><label class="form-label">Description</label><textarea class="form-textarea" name="description" required></textarea></div>
                    <div class="btn-group"><button class="btn btn-danger" type="submit">Debit</button><button class="btn btn-secondary" type="button" onclick="closeModal('debitModal')">Cancel</button></div>
                </form>
            </div>
        </div>
        <div id="withdrawalModal" class="modal" style="display:none;">
            <div class="card" style="max-width:520px;width:calc(100% - 24px);margin:0 auto;">
                <div class="card-header">
                    <h3>Process Withdrawal</h3>
                </div>
                <form method="post" class="card-body"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>"><input type="hidden" name="action" value="process_withdrawal"><input type="hidden" name="withdrawal_id" id="withdrawal_id">
                    <div class="form-group"><label class="form-label">User</label><input class="form-input" id="withdrawal_username" type="text" disabled></div>
                    <div class="form-group"><label class="form-label">Amount</label><input class="form-input" id="withdrawal_amount" type="text" disabled></div>
                    <div class="form-group"><label class="form-label">Action</label><select class="form-select" name="status">
                            <option value="completed">Approve</option>
                            <option value="rejected">Reject</option>
                        </select></div>
                    <div class="form-group"><label class="form-label">Admin Notes</label><textarea class="form-textarea" name="notes"></textarea></div>
                    <div class="btn-group"><button class="btn btn-primary" type="submit">Submit</button><button class="btn btn-secondary" type="button" onclick="closeModal('withdrawalModal')">Cancel</button></div>
                </form>
            </div>
        </div>
    </div>
    <script>
        function showModal(id) {
            const modal = document.getElementById(id);
            if (modal) {
                modal.style.display = 'flex';
                modal.style.alignItems = 'center';
                modal.style.justifyContent = 'center';
                modal.style.position = 'fixed';
                modal.style.inset = '0';
                modal.style.background = 'rgba(16,32,51,.45)';
                modal.style.zIndex = '80';
            }
        }

        function closeModal(id) {
            const modal = document.getElementById(id);
            if (modal) {
                modal.style.display = 'none';
            }
        }

        function openUserModal(modalId, userId, username, currentValue) {
            document.getElementById(modalId === 'statusModal' ? 'status_user_id' : 'role_user_id').value = userId;
            document.getElementById(modalId === 'statusModal' ? 'status_username' : 'role_username').value = username;
            document.getElementById(modalId === 'statusModal' ? 'status_value' : 'role_value').value = currentValue;
            showModal(modalId);
        }

        function openMoneyModal(modalId, userId, username) {
            document.getElementById(modalId === 'creditModal' ? 'credit_user_id' : 'debit_user_id').value = userId;
            document.getElementById(modalId === 'creditModal' ? 'credit_username' : 'debit_username').value = username;
            showModal(modalId);
        }

        function openWithdrawalModal(withdrawalId, username, amount) {
            document.getElementById('withdrawal_id').value = withdrawalId;
            document.getElementById('withdrawal_username').value = username;
            document.getElementById('withdrawal_amount').value = '₹' + Number(amount).toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
            showModal('withdrawalModal');
        }
        window.addEventListener('click', function(event) {
            document.querySelectorAll('.modal').forEach(function(modal) {
                if (event.target === modal) {
                    closeModal(modal.id);
                }
            });
        });
        const chartCanvas = document.getElementById('cashflowChart');
        if (chartCanvas && <?php echo count($chartLabels); ?> > 0) {
            new Chart(chartCanvas, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($chartLabels); ?>,
                    datasets: [{
                        data: <?php echo json_encode($chartValues); ?>,
                        borderColor: '#0f6cbd',
                        backgroundColor: 'rgba(15,108,189,.12)',
                        fill: true,
                        tension: .35,
                        borderWidth: 3,
                        pointRadius: 4
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        }
    </script>
</body>

</html>
