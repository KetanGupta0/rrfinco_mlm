<?php
/**
 * Admin Dashboard - Complete Admin Panel
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Require admin access
requireAdmin();

$pageTitle = 'Admin Dashboard';
$activeTab = $_GET['tab'] ?? 'overview';

// Include admin navbar
require_once __DIR__ . '/navbar.php';

// Handle actions
$action = $_GET['action'] ?? '';
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? '';
    if (!validateCSRFToken($csrf_token)) {
        $error = 'Invalid CSRF token';
        logError('Admin update user invalid CSRF token');
    } else {
        switch ($action) {
            case 'update_user_status':
                $user_id = (int)($_POST['user_id'] ?? 0);
                $status = $_POST['status'] ?? '';
                if (in_array($status, ['active', 'inactive', 'suspended'])) {
                    try {
                        $stmt = $pdo->prepare('UPDATE users SET status = ? WHERE user_id = ?');
                        $stmt->execute([$status, $user_id]);
                        $message = 'User status updated successfully';
                    } catch (PDOException $e) {
                        $error = 'Failed to update user status';
                        logError('Admin update user status', $e->getMessage());
                    }
                }
                break;

            case 'update_user_role':
                $user_id = (int)($_POST['user_id'] ?? 0);
                $role = $_POST['role'] ?? '';
                if (in_array($role, ['user', 'admin'])) {
                    try {
                        $stmt = $pdo->prepare('UPDATE users SET role = ? WHERE user_id = ?');
                        $stmt->execute([$role, $user_id]);
                        $message = 'User role updated successfully';
                    } catch (PDOException $e) {
                        $error = 'Failed to update user role';
                        logError('Admin update user role', $e->getMessage());
                    }
                }
                break;

            case 'process_withdrawal':
                $withdrawal_id = (int)($_POST['withdrawal_id'] ?? 0);
                $status = $_POST['status'] ?? '';
                $notes = trim($_POST['notes'] ?? '');

                if (in_array($status, ['completed', 'rejected'])) {
                    try {
                        $pdo->beginTransaction();

                        // 1. Update withdrawal_requests
                        $stmt = $pdo->prepare("
                            UPDATE withdrawal_requests 
                            SET status = ?, completed_at = NOW()
                            WHERE withdrawal_id = ?
                        ");
                        $stmt->execute([$status, $withdrawal_id]);
                        
                        // 2. Update transaction
                        $extraNote = !empty($notes) ? " - " . $notes : "";
                        
                        $stmt = $pdo->prepare("
                            UPDATE transactions t
                            JOIN withdrawal_requests w ON t.user_id = w.user_id
                            SET 
                                t.status = ?,
                                t.description = CONCAT(
                                    COALESCE(t.description, ''), 
                                    ' | Withdrawal ', ?, 
                                    ?
                                )
                            WHERE 
                                w.withdrawal_id = ?
                                AND t.transaction_type = 'withdrawal'
                                AND t.amount = -w.amount
                            ORDER BY t.transaction_date DESC
                            LIMIT 1
                        ");
                        
                        $stmt->execute([
                            $status,
                            ucfirst($status),
                            $extraNote,
                            $withdrawal_id
                        ]);
                        
                        $pdo->commit();
                        $message = 'Withdrawal request processed successfully';
                    } catch (PDOException $e) {
                        $pdo->rollBack();
                        $error = 'Failed to process withdrawal';
                        logError('Admin process withdrawal', $e->getMessage());
                    }
                }
                break;

            case 'manual_credit':
                $user_id = (int)($_POST['user_id'] ?? 0);
                $amount = (float)($_POST['amount'] ?? 0);
                $description = trim($_POST['description'] ?? '');

                if ($amount > 0 && !empty($description)) {
                    try {
                        updateWalletBalance($user_id, $amount, 'add');
                        recordTransaction($user_id, 'manual_credit', $amount, $description, $_SESSION['user_id']);
                        $message = 'Manual credit added successfully';
                    } catch (PDOException $e) {
                        $error = 'Failed to add manual credit';
                        logError('Admin manual credit', $e->getMessage());
                    }
                } else {
                    $error = 'Invalid amount or description';
                }
                break;

            case 'manual_debit':
                $user_id = (int)($_POST['user_id'] ?? 0);
                $amount = (float)($_POST['amount'] ?? 0);
                $description = trim($_POST['description'] ?? '');

                if ($amount > 0 && !empty($description)) {
                    try {
                        updateWalletBalance($user_id, $amount, 'sub');
                        recordTransaction($user_id, 'manual_debit', -$amount, $description, $_SESSION['user_id']);
                        $message = 'Manual debit processed successfully';
                    } catch (PDOException $e) {
                        $error = 'Failed to process manual debit';
                        logError('Admin manual debit', $e->getMessage());
                    }
                } else {
                    $error = 'Invalid amount or description';
                }
                break;
            
            case 'delete_transaction':
                $transaction_id = (int)($_POST['transaction_id'] ?? 0);
                try {
                    $stmt = $pdo->prepare('DELETE FROM transactions WHERE transaction_id = ?');
                    $stmt->execute([$transaction_id]);
                    $message = 'Transaction deleted successfully';
                } catch (PDOException $e) {
                    $error = 'Failed to delete transaction';
                    logError('Admin delete transaction', $e->getMessage());
                }
                break;
        }
    }
}

// Get statistics for overview
try {
    $stats = [];

    // User statistics
    $stmt = $pdo->query('SELECT COUNT(*) as total_users FROM users WHERE role = "user"');
    $stats['total_users'] = $stmt->fetch()['total_users'];

    $stmt = $pdo->query('SELECT COUNT(*) as active_users FROM users WHERE status = "active" and role = "user"');
    $stats['active_users'] = $stmt->fetch()['active_users'];

    $stmt = $pdo->query('SELECT COUNT(*) as admin_users FROM users WHERE role = "admin"');
    $stats['admin_users'] = $stmt->fetch()['admin_users'];

    // Financial statistics
    $stmt = $pdo->query('SELECT SUM(wallet_balance) as total_wallet FROM users');
    $stats['total_wallet_balance'] = $stmt->fetch()['total_wallet'] ?? 0;

    $stmt = $pdo->query('SELECT SUM(investment_amount) as total_investments FROM investments WHERE status = "active"');
    $stats['total_investments'] = $stmt->fetch()['total_investments'] ?? 0;

    $stmt = $pdo->query('SELECT COUNT(*) as pending_withdrawals FROM withdrawal_requests WHERE status = "pending"');
    $stats['pending_withdrawals'] = $stmt->fetch()['pending_withdrawals'];

    $stmt = $pdo->query('SELECT SUM(amount) as total_pending_amount FROM withdrawal_requests WHERE status = "pending"');
    $stats['pending_withdrawal_amount'] = $stmt->fetch()['total_pending_amount'] ?? 0;

    // Recent activity
    $stmt = $pdo->query('SELECT COUNT(*) as today_registrations FROM users WHERE DATE(registration_date) = CURDATE()');
    $stats['today_registrations'] = $stmt->fetch()['today_registrations'];

    $stmt = $pdo->query('SELECT SUM(amount) as today_deposits FROM deposits WHERE DATE(created_at) = CURDATE() AND status = "completed"');
    $stats['today_deposits'] = $stmt->fetch()['today_deposits'] ?? 0;

} catch (PDOException $e) {
    logError('Admin dashboard stats', $e->getMessage());
    $stats = array_fill_keys(['total_users', 'active_users', 'admin_users', 'total_wallet_balance',
        'total_investments', 'pending_withdrawals', 'pending_withdrawal_amount',
        'today_registrations', 'today_deposits'], 0);
}

// Get data based on active tab
$data = [];
switch ($activeTab) {
    case 'users':
        try {
            $stmt = $pdo->query('SELECT user_id, username, email, first_name, last_name, phone, wallet_balance,
                total_cashback_earned, total_commission_earned, registration_date, last_login, status, role
                FROM users ORDER BY registration_date DESC LIMIT 100');
            $data['users'] = $stmt->fetchAll();
        } catch (PDOException $e) {
            $data['users'] = [];
        }
        break;

    case 'withdrawals':
        try {
            $stmt = $pdo->prepare('SELECT w.*, u.username, u.email, b.account_holder_name, b.bank_name,
                b.account_number, b.ifsc_code
                FROM withdrawal_requests w
                JOIN users u ON w.user_id = u.user_id
                LEFT JOIN user_bank_accounts b ON w.bank_account_id = b.bank_account_id
                ORDER BY w.requested_at DESC LIMIT 100');
            $stmt->execute();
            $data['withdrawals'] = $stmt->fetchAll();
        } catch (PDOException $e) {
            $data['withdrawals'] = [];
        }
        break;

    case 'transactions':
        try {
            $stmt = $pdo->prepare('SELECT t.*, u.username, ru.username as related_username
                FROM transactions t
                JOIN users u ON t.user_id = u.user_id
                LEFT JOIN users ru ON t.related_user_id = ru.user_id
                ORDER BY t.transaction_date DESC LIMIT 200');
            $stmt->execute();
            $data['transactions'] = $stmt->fetchAll();
        } catch (PDOException $e) {
            $data['transactions'] = [];
        }
        break;

    case 'investments':
        try {
            $stmt = $pdo->prepare('SELECT i.*, u.username, u.email
                FROM investments i
                JOIN users u ON i.user_id = u.user_id
                ORDER BY i.investment_date DESC LIMIT 100');
            $stmt->execute();
            $data['investments'] = $stmt->fetchAll();
        } catch (PDOException $e) {
            $data['investments'] = [];
        }
        break;

    case 'update_admin_password':
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // Get current admin user
        $admin_user = getCurrentUser();

        // Validate current password
        if (empty($current_password)) {
            $error = 'Please enter your current password.';
        } elseif (!password_verify($current_password, $admin_user['password'])) {
            $error = 'Current password is incorrect.';
        } elseif (empty($new_password)) {
            $error = 'Please enter a new password.';
        } elseif (strlen($new_password) < 8) {
            $error = 'New password must be at least 8 characters long.';
        } elseif ($new_password !== $confirm_password) {
            $error = 'New password and confirmation do not match.';
        } elseif (password_verify($new_password, $admin_user['password'])) {
            $error = 'New password must be different from your current password.';
        } else {
            try {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE user_id = ?');
                $stmt->execute([$hashed_password, $admin_user['user_id']]);

                $message = 'Password updated successfully.';
                logError('Admin password changed', 'Admin ' . $admin_user['user_id'] . ' (' . $admin_user['username'] . ') changed password');
            } catch (PDOException $e) {
                $error = 'Failed to update password';
                logError('Admin password update failed', $e->getMessage());
            }
        }
        break;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - BachatPay Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100">
    <!-- Admin Navigation -->
    <?php require_once __DIR__ . '/navbar.php'; ?>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Messages -->
        <?php if ($message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Tab Content -->
        <?php if ($activeTab === 'overview'): ?>
            <!-- Overview Dashboard -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- User Stats -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center">
                                    <span class="text-white text-sm font-bold">U</span>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Total Users</dt>
                                    <dd class="text-lg font-medium text-gray-900"><?php echo number_format($stats['total_users']); ?></dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-green-500 rounded-md flex items-center justify-center">
                                    <span class="text-white text-sm font-bold">A</span>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Active Users</dt>
                                    <dd class="text-lg font-medium text-gray-900"><?php echo number_format($stats['active_users']); ?></dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Financial Stats -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-yellow-500 rounded-md flex items-center justify-center">
                                    <span class="text-white text-sm font-bold">₹</span>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Total Wallet Balance</dt>
                                    <dd class="text-lg font-medium text-gray-900">₹<?php echo number_format($stats['total_wallet_balance'], 2); ?></dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-purple-500 rounded-md flex items-center justify-center">
                                    <span class="text-white text-sm font-bold">I</span>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Total Investments</dt>
                                    <dd class="text-lg font-medium text-gray-900">₹<?php echo number_format($stats['total_investments'], 2); ?></dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Withdrawal Stats -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-orange-500 rounded-md flex items-center justify-center">
                                    <span class="text-white text-sm font-bold">W</span>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Pending Withdrawals</dt>
                                    <dd class="text-lg font-medium text-gray-900"><?php echo number_format($stats['pending_withdrawals']); ?></dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-red-500 rounded-md flex items-center justify-center">
                                    <span class="text-white text-sm font-bold">₹</span>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Pending Amount</dt>
                                    <dd class="text-lg font-medium text-gray-900">₹<?php echo number_format($stats['pending_withdrawal_amount'], 2); ?></dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Activity Stats -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-indigo-500 rounded-md flex items-center justify-center">
                                    <span class="text-white text-sm font-bold">R</span>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Today's Registrations</dt>
                                    <dd class="text-lg font-medium text-gray-900"><?php echo number_format($stats['today_registrations']); ?></dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-teal-500 rounded-md flex items-center justify-center">
                                    <span class="text-white text-sm font-bold">D</span>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Today's Deposits</dt>
                                    <dd class="text-lg font-medium text-gray-900">₹<?php echo number_format($stats['today_deposits'], 2); ?></dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="bg-white shadow rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Quick Actions</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <a href="?tab=users" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded text-center">
                        Manage Users
                    </a>
                    <a href="?tab=withdrawals" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded text-center">
                        Process Withdrawals
                    </a>
                    <a href="?tab=transactions" class="bg-purple-500 hover:bg-purple-600 text-white px-4 py-2 rounded text-center">
                        View Transactions
                    </a>
                </div>
            </div>

        <?php elseif ($activeTab === 'users'): ?>
            <!-- Users Management -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">User Management</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Wallet</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Earnings</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($data['users'])): ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                                        No users found.
                                    </td>
                                </tr>
                            <?php else: ?>
                            <?php foreach ($data['users'] as $user): ?>
                                <?php if($user['role'] !== 'admin'): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($user['username']); ?></div>
                                            <div class="text-sm text-gray-500">ID: <?php echo $user['user_id']; ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($user['email']); ?></div>
                                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            ₹<?php echo number_format($user['wallet_balance'], 2); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            ₹<?php echo number_format($user['total_cashback_earned'] + $user['total_commission_earned'], 2); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                <input type="hidden" name="action" value="update_user_status">
                                                <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                <select name="status" onchange="this.form.submit()" class="text-sm border rounded px-2 py-1">
                                                    <option value="active" <?php echo $user['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                                    <option value="inactive" <?php echo $user['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                                    <option value="suspended" <?php echo $user['status'] === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                                </select>
                                            </form>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                                <input type="hidden" name="action" value="update_user_role">
                                                <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                <select name="role" onchange="this.form.submit()" class="text-sm border rounded px-2 py-1">
                                                    <option value="user" <?php echo $user['role'] === 'user' ? 'selected' : ''; ?>>User</option>
                                                    <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                                </select>
                                            </form>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <button onclick="showManualCreditModal(<?php echo $user['user_id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')" class="text-green-600 hover:text-green-900 mr-2">Credit</button>
                                            <button onclick="showManualDebitModal(<?php echo $user['user_id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')" class="text-red-600 hover:text-red-900">Debit</button>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php elseif ($activeTab === 'withdrawals'): ?>
            <!-- Withdrawals Management -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Withdrawal Requests</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bank Details</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Requested</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($data['withdrawals'])): ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                                        No withdrawal requests found.
                                    </td>
                                </tr>
                            <?php else: ?>
                            <?php foreach ($data['withdrawals'] as $withdrawal): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($withdrawal['username']); ?></div>
                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($withdrawal['email']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    ₹<?php echo number_format($withdrawal['amount'], 2); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php if ($withdrawal['account_holder_name'] && $withdrawal['method'] === 'bank'): ?>
                                        <div><?php echo htmlspecialchars($withdrawal['account_holder_name']); ?></div>
                                        <div><?php echo htmlspecialchars($withdrawal['bank_name']); ?></div>
                                        <div><?php echo htmlspecialchars($withdrawal['account_number']); ?></div>
                                        <div><?php echo htmlspecialchars($withdrawal['ifsc_code']); ?></div>
                                    <?php else: ?>
                                        No bank details
                                    <?php endif; ?>
                                    <?php if ($withdrawal['method'] === 'upi'): ?>
                                        <div class="text-sm text-gray-500">
                                            UPI: <?php echo htmlspecialchars($withdrawal['upi_id']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('M d, Y H:i', strtotime($withdrawal['requested_at'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                        <?php echo $withdrawal['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' :
                                                 ($withdrawal['status'] === 'completed' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'); ?>">
                                        <?php echo ucfirst($withdrawal['status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <?php if ($withdrawal['status'] === 'pending'): ?>
                                        <button onclick="showProcessWithdrawalModal(<?php echo $withdrawal['withdrawal_id']; ?>, '<?php echo htmlspecialchars($withdrawal['username']); ?>', <?php echo $withdrawal['amount']; ?>)" class="text-blue-600 hover:text-blue-900">
                                            Process
                                        </button>
                                    <?php else: ?>
                                        <span class="text-gray-400">Processed</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php elseif ($activeTab === 'transactions'): ?>
            <!-- Transactions Log -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Transaction Log</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($data['transactions'])): ?>
                                <tr>
                                    <td colspan="7" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                                        No transactions found.
                                    </td>
                                </tr>
                            <?php else: ?>
                            <?php foreach ($data['transactions'] as $transaction): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($transaction['username']); ?></div>
                                    <?php if ($transaction['related_username']): ?>
                                        <div class="text-sm text-gray-500">Related: <?php echo htmlspecialchars($transaction['related_username']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php 
                                    $type = $transaction['transaction_type'];
                                    $isJoiningBonus = in_array($type, ['joining_bonus', 'joining_bonus_added']);
                                    
                                    if ($isJoiningBonus) {
                                        $badgeColor = 'bg-gray-100 text-gray-800';
                                        $label = $type === 'joining_bonus_added' ? 'Joining Bonus Assigned' : 'Joining Bonus Transfer';
                                    } else {
                                        $badgeColor = 'bg-blue-100 text-blue-800';
                                        $label = ucfirst(str_replace('_', ' ', $type));
                                    }
                                    ?>
                                    
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $badgeColor; ?>">
                                        <?php echo htmlspecialchars($label); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium
                                <?php 
                                if ($isJoiningBonus) {
                                    echo 'text-gray-600';
                                } else {
                                    echo $transaction['amount'] >= 0 ? 'text-green-600' : 'text-red-600';
                                }
                                ?>">
                                    ₹<?php echo number_format(abs($transaction['amount']), 2); ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    <?php echo htmlspecialchars($transaction['description']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('M d, Y H:i', strtotime($transaction['transaction_date'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <?php if($transaction['transaction_type'] === 'manual_credit' || $transaction['transaction_type'] === 'manual_debit'): ?>
                                    <form method="post" onsubmit="return confirm('Are you sure you want to delete this transaction?')">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                        <input type="hidden" name="action" value="delete_transaction">
                                        <input type="hidden" name="transaction_id" value="<?php echo $transaction['transaction_id']; ?>">
                                        <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                                    </form>
                                    <?php else: ?>
                                        <span class="text-gray-400">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php elseif ($activeTab === 'investments'): ?>
            <!-- Investments Overview -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Investment Overview</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Daily Rate</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($data['investments'])): ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">
                                        No investments found.
                                    </td>
                                </tr>
                            <?php else: ?>
                            <?php foreach ($data['investments'] as $investment): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($investment['username']); ?></div>
                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($investment['email']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    ₹<?php echo number_format($investment['investment_amount'], 2); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo number_format($investment['daily_rate'] * 100, 2); ?>%
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('M d, Y', strtotime($investment['investment_date'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                        <?php echo $investment['status'] === 'active' ? 'bg-green-100 text-green-800' :
                                                 ($investment['status'] === 'matured' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'); ?>">
                                        <?php echo ucfirst($investment['status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
            </div>
        
        <?php endif; ?>
    </div>

    <!-- Manual Credit Modal -->
    <div id="manualCreditModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Manual Credit</h3>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="manual_credit">
                    <input type="hidden" name="user_id" id="credit_user_id">

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">User</label>
                        <span id="credit_username" class="text-sm text-gray-500"></span>
                    </div>

                    <div class="mb-4">
                        <label for="credit_amount" class="block text-sm font-medium text-gray-700">Amount (₹)</label>
                        <input type="number" step="0.01" min="0" name="amount" id="credit_amount" required
                               class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2">
                    </div>

                    <div class="mb-4">
                        <label for="credit_description" class="block text-sm font-medium text-gray-700">Description</label>
                        <textarea name="description" id="credit_description" required
                                  class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2" rows="3"></textarea>
                    </div>

                    <div class="flex justify-end space-x-2">
                        <button type="button" onclick="closeModal('manualCreditModal')"
                                class="px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600">Credit Amount</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Manual Debit Modal -->
    <div id="manualDebitModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Manual Debit</h3>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="manual_debit">
                    <input type="hidden" name="user_id" id="debit_user_id">

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">User</label>
                        <span id="debit_username" class="text-sm text-gray-500"></span>
                    </div>

                    <div class="mb-4">
                        <label for="debit_amount" class="block text-sm font-medium text-gray-700">Amount (₹)</label>
                        <input type="number" step="0.01" min="0" name="amount" id="debit_amount" required
                               class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2">
                    </div>

                    <div class="mb-4">
                        <label for="debit_description" class="block text-sm font-medium text-gray-700">Description</label>
                        <textarea name="description" id="debit_description" required
                                  class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2" rows="3"></textarea>
                    </div>

                    <div class="flex justify-end space-x-2">
                        <button type="button" onclick="closeModal('manualDebitModal')"
                                class="px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600">Debit Amount</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Process Withdrawal Modal -->
    <div id="processWithdrawalModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Process Withdrawal</h3>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="process_withdrawal">
                    <input type="hidden" name="withdrawal_id" id="withdrawal_id">

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">User</label>
                        <span id="withdrawal_username" class="text-sm text-gray-500"></span>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Amount</label>
                        <span id="withdrawal_amount" class="text-sm text-gray-500"></span>
                    </div>

                    <div class="mb-4">
                        <label for="withdrawal_status" class="block text-sm font-medium text-gray-700">Action</label>
                        <select name="status" id="withdrawal_status" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2">
                            <option value="completed">Approve & Complete</option>
                            <option value="rejected">Reject</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label for="withdrawal_notes" class="block text-sm font-medium text-gray-700">Notes (Optional)</label>
                        <textarea name="notes" id="withdrawal_notes"
                                  class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2" rows="3"
                                  placeholder="Add any notes about this withdrawal..."></textarea>
                    </div>

                    <div class="flex justify-end space-x-2">
                        <button type="button" onclick="closeModal('processWithdrawalModal')"
                                class="px-4 py-2 bg-gray-300 text-gray-700 rounded hover:bg-gray-400">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">Process Withdrawal</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function showManualCreditModal(userId, username) {
            document.getElementById('credit_user_id').value = userId;
            document.getElementById('credit_username').textContent = username;
            document.getElementById('manualCreditModal').classList.remove('hidden');
        }

        function showManualDebitModal(userId, username) {
            document.getElementById('debit_user_id').value = userId;
            document.getElementById('debit_username').textContent = username;
            document.getElementById('manualDebitModal').classList.remove('hidden');
        }

        function showProcessWithdrawalModal(withdrawalId, username, amount) {
            document.getElementById('withdrawal_id').value = withdrawalId;
            document.getElementById('withdrawal_username').textContent = username;
            document.getElementById('withdrawal_amount').textContent = '₹' + amount.toLocaleString();
            document.getElementById('processWithdrawalModal').classList.remove('hidden');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('bg-gray-600')) {
                event.target.classList.add('hidden');
            }
        }
    </script>
</body>
</html>