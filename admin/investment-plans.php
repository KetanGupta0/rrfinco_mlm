<?php
/**
 * Admin - Investment Plans Management (Enhanced UI)
 * Manage investment plans: Create, Edit, Delete, Activate/Deactivate
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/common.php';

requireAdmin();

$pageTitle = 'Investment Plans Management';
$message = '';
$error = '';
$csrf_token = generateCSRFToken();
$editingPlan = null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $token = $_POST['csrf_token'] ?? '';
    
    if (!validateCSRFToken($token)) {
        $error = 'Invalid CSRF token';
    } else {
        switch ($action) {
            case 'create':
                handleCreatePlan($pdo);
                break;
            case 'edit':
                handleEditPlan($pdo);
                break;
            case 'delete':
                handleDeletePlan($pdo);
                break;
            case 'toggle_status':
                handleToggleStatus($pdo);
                break;
        }
    }
}

// Get tab parameter
$tab = $_GET['tab'] ?? 'list';

$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$sortMap = [
    'min_amount' => 'p.min_amount',
    'max_amount' => 'p.max_amount',
    'plan_name' => 'p.plan_name',
    'daily_percentage' => 'p.daily_percentage',
    'created_at' => 'p.created_at',
];
$sortKey = adminGetSortableColumn($_GET['sort'] ?? 'min_amount', $sortMap, 'min_amount');
$sortDir = adminGetSortDirection($_GET['dir'] ?? 'asc', 'asc');

// Get all investment plans
try {
    $where = ['1=1'];
    $params = [];

    if ($search !== '') {
        $where[] = '(p.plan_name LIKE ? OR COALESCE(p.description, "") LIKE ?)';
        $like = adminLike($search);
        array_push($params, $like, $like);
    }

    if ($statusFilter === 'active') {
        $where[] = 'p.is_active = 1';
    } elseif ($statusFilter === 'inactive') {
        $where[] = 'p.is_active = 0';
    }

    $stmt = $pdo->prepare('
        SELECT p.*, COUNT(pi.invest_id) AS active_subscriptions
        FROM investment_plans p
        LEFT JOIN plan_investments pi ON pi.plan_id = p.plan_id AND pi.status = "active"
        WHERE ' . implode(' AND ', $where) . '
        GROUP BY p.plan_id
        ORDER BY ' . $sortMap[$sortKey] . ' ' . $sortDir
    );
    $stmt->execute($params);
    $plans = $stmt->fetchAll();
} catch (PDOException $e) {
    logError('Admin investment plans', $e->getMessage());
    $plans = [];
    $error = 'Failed to load investment plans';
}

// Get plan to edit if in edit mode
if ($tab === 'edit' && isset($_GET['plan_id'])) {
    try {
        $stmt = $pdo->prepare('SELECT * FROM investment_plans WHERE plan_id = ?');
        $stmt->execute([(int)$_GET['plan_id']]);
        $editingPlan = $stmt->fetch();
    } catch (PDOException $e) {
        logError('Get plan for edit', $e->getMessage());
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - BachatPay Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <style>
        .hidden { display: none; }
        .sm\:inline { display: inline; }
        @media (max-width: 640px) {
            .sm\:inline { display: none; }
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php require_once __DIR__ . '/navbar.php'; ?>
    
    <!-- Main Content -->
    <main class="admin-content">
        <!-- Page Header -->
        <div class="admin-page-header">
            <div class="admin-page-title">
                <h2><i class="fas fa-credit-card mr-2"></i>Investment Plans Management</h2>
                <p>Create, edit, and manage investment plans for the platform</p>
            </div>
            <div class="page-tools screen-only">
                <?php if ($tab === 'list' || $tab === 'edit'): ?>
                    <button type="button" class="btn btn-secondary" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if ($message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($message); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <!-- Tabs -->
        <div class="tabs">
            <a href="?tab=list" class="tab-btn <?php echo ($tab === 'list' || $tab === 'edit') ? 'active' : ''; ?>">
                <i class="fas fa-list"></i> All Plans
            </a>
            <a href="?tab=create" class="tab-btn <?php echo ($tab === 'create') ? 'active' : ''; ?>">
                <i class="fas fa-plus"></i> Create New Plan
            </a>
        </div>

        <!-- List View -->
        <?php if ($tab === 'list' || $tab === 'edit'): ?>
            <div class="card filters-card screen-only">
                <div class="card-header">
                    <div>
                        <h3>Find Plans Faster</h3>
                        <p>Search plan names or descriptions, then sort the report before printing.</p>
                    </div>
                </div>
                <form method="get" class="filters-form">
                    <input type="hidden" name="tab" value="list">
                    <div class="form-group">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" class="form-input" value="<?php echo htmlspecialchars($search); ?>" placeholder="Plan name or description">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All</option>
                            <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Apply</button>
                        <a href="?tab=list" class="btn btn-secondary">Reset</a>
                    </div>
                </form>
            </div>
            <div class="card">
                <div class="card-header">
                    <h3>Investment Plans</h3>
                    <span class="badge badge-primary"><?php echo count($plans); ?> Plans</span>
                </div>
                
                <?php if (empty($plans)): ?>
                    <div class="text-center py-12">
                        <i class="fas fa-inbox text-gray-400" style="font-size: 3rem;"></i>
                        <p class="text-gray-500 mt-4">No investment plans found. Create one to get started.</p>
                        <a href="?tab=create" class="btn btn-primary mt-4">
                            <i class="fas fa-plus"></i> Create Plan
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th><a class="table-sort <?php echo $sortKey === 'plan_name' ? 'active' : ''; ?>" href="<?php echo adminQueryString(['sort' => 'plan_name', 'dir' => $sortKey === 'plan_name' && $sortDir === 'asc' ? 'desc' : 'asc']); ?>">Plan Name <i class="fas fa-sort"></i></a></th>
                                    <th><a class="table-sort <?php echo $sortKey === 'min_amount' ? 'active' : ''; ?>" href="<?php echo adminQueryString(['sort' => 'min_amount', 'dir' => $sortKey === 'min_amount' && $sortDir === 'asc' ? 'desc' : 'asc']); ?>">Amount Range <i class="fas fa-sort"></i></a></th>
                                    <th><a class="table-sort <?php echo $sortKey === 'daily_percentage' ? 'active' : ''; ?>" href="<?php echo adminQueryString(['sort' => 'daily_percentage', 'dir' => $sortKey === 'daily_percentage' && $sortDir === 'asc' ? 'desc' : 'asc']); ?>">Daily Rate <i class="fas fa-sort"></i></a></th>
                                    <th>Monthly Rate</th>
                                    <th>Duration</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($plans as $plan): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($plan['plan_name']); ?></strong>
                                            <span class="table-subtext"><?php echo (int) $plan['active_subscriptions']; ?> active subscriptions</span>
                                        </td>
                                        <td>
                                            <span class="badge badge-info">₹<?php echo number_format($plan['min_amount'], 0); ?></span>
                                            to
                                            <span class="badge badge-info">₹<?php echo number_format($plan['max_amount'], 0); ?></span>
                                        </td>
                                        <td>
                                            <strong><?php echo formatPercentage($plan['daily_percentage'], 2); ?></strong>
                                        </td>
                                        <td>
                                            <?php echo number_format($plan['monthly_percentage'], 2); ?>%
                                        </td>
                                        <td>
                                            <?php echo $plan['duration_days'] ? htmlspecialchars($plan['duration_days']) . ' days' : '<span class="badge badge-primary">Unlimited</span>'; ?>
                                        </td>
                                        <td>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="plan_id" value="<?php echo $plan['plan_id']; ?>">
                                                <button type="submit" class="badge <?php echo $plan['is_active'] ? 'badge-success' : 'badge-danger'; ?>" style="cursor: pointer; border: none; padding: 0.5rem;">
                                                    <?php echo $plan['is_active'] ? 'Active' : 'Inactive'; ?>
                                                </button>
                                            </form>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="?tab=edit&plan_id=<?php echo $plan['plan_id']; ?>" class="btn btn-primary btn-small">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this plan? This cannot be undone.');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="plan_id" value="<?php echo $plan['plan_id']; ?>">
                                                    <button type="submit" class="btn btn-danger btn-small">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Create / Edit View -->
        <?php if ($tab === 'create' || $tab === 'edit'): ?>
            <div class="card" style="max-width: 800px;">
                <div class="card-header">
                    <h3><?php echo ($tab === 'edit' && $editingPlan) ? 'Edit Investment Plan' : 'Create New Investment Plan'; ?></h3>
                </div>

                <form method="POST" class="card-body">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" name="action" value="<?php echo ($tab === 'edit' && $editingPlan) ? 'edit' : 'create'; ?>">
                    <?php if ($tab === 'edit' && $editingPlan): ?>
                        <input type="hidden" name="plan_id" value="<?php echo $editingPlan['plan_id']; ?>">
                    <?php endif; ?>

                    <!-- Plan Name -->
                    <div class="form-group">
                        <label class="form-label required">Plan Name</label>
                        <input type="text" name="plan_name" required class="form-input" 
                               placeholder="e.g., Platinum Plan"
                               value="<?php echo $editingPlan ? htmlspecialchars($editingPlan['plan_name']) : ''; ?>">
                        <small class="form-error" style="display: none;"></small>
                    </div>

                    <!-- Amount Range -->
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label required">Minimum Amount (₹)</label>
                            <input type="number" step="0.01" name="min_amount" required class="form-input" 
                                   placeholder="1000"
                                   value="<?php echo $editingPlan ? $editingPlan['min_amount'] : ''; ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label required">Maximum Amount (₹)</label>
                            <input type="number" step="0.01" name="max_amount" required class="form-input" 
                                   placeholder="25000"
                                   value="<?php echo $editingPlan ? $editingPlan['max_amount'] : ''; ?>">
                        </div>
                    </div>

                    <!-- Percentages -->
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label required">Daily Percentage (%)</label>
                            <input type="number" step="0.0001" name="daily_percentage" required class="form-input" 
                                   placeholder="0.0010"
                                   value="<?php echo $editingPlan ? $editingPlan['daily_percentage'] : ''; ?>">
                            <small style="color: var(--color-gray-500);">Daily return rate</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label required">Monthly Percentage (%)</label>
                            <input type="number" step="0.01" name="monthly_percentage" required class="form-input" 
                                   placeholder="3.00"
                                   value="<?php echo $editingPlan ? $editingPlan['monthly_percentage'] : ''; ?>">
                            <small style="color: var(--color-gray-500);">Monthly return rate</small>
                        </div>
                    </div>

                    <!-- Duration -->
                    <div class="form-group">
                        <label class="form-label">Duration (days)</label>
                        <input type="number" name="duration_days" class="form-input" 
                               placeholder="365 (leave empty for unlimited)"
                               value="<?php echo $editingPlan ? ($editingPlan['duration_days'] ?? '') : '365'; ?>">
                        <small style="color: var(--color-gray-500);">Investment validity period</small>
                    </div>

                    <!-- Description -->
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-textarea" 
                                  placeholder="Plan description for users"><?php echo $editingPlan ? htmlspecialchars($editingPlan['description'] ?? '') : ''; ?></textarea>
                    </div>

                    <!-- Action Buttons -->
                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> <?php echo ($tab === 'edit' && $editingPlan) ? 'Update Plan' : 'Create Plan'; ?>
                        </button>
                        <a href="?tab=list" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </main>

    <script>
        // Form validation
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const minAmount = form.querySelector('input[name="min_amount"]');
                const maxAmount = form.querySelector('input[name="max_amount"]');
                
                if (minAmount && maxAmount) {
                    const min = parseFloat(minAmount.value);
                    const max = parseFloat(maxAmount.value);
                    
                    if (min >= max) {
                        e.preventDefault();
                        alert('Minimum amount must be less than maximum amount');
                        return false;
                    }
                }
            });
        });
    </script>
</body>
</html>

<?php

function handleCreatePlan($pdo) {
    global $message, $error;
    
    $plan_name = trim($_POST['plan_name'] ?? '');
    $min_amount = (float)($_POST['min_amount'] ?? 0);
    $max_amount = (float)($_POST['max_amount'] ?? 0);
    $daily_percentage = (float)($_POST['daily_percentage'] ?? 0);
    $monthly_percentage = (float)($_POST['monthly_percentage'] ?? 0);
    $duration_days = (int)($_POST['duration_days'] ?? 0) ?: null;
    $description = trim($_POST['description'] ?? '');

    if (empty($plan_name)) {
        $error = 'Plan name is required';
        return;
    }
    if ($min_amount <= 0 || $max_amount <= 0 || $min_amount >= $max_amount) {
        $error = 'Invalid amount range';
        return;
    }
    if ($daily_percentage <= 0) {
        $error = 'Daily percentage must be greater than 0';
        return;
    }

    try {
        $stmt = $pdo->prepare('
            INSERT INTO investment_plans 
            (plan_name, min_amount, max_amount, daily_percentage, monthly_percentage, duration_days, description, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1)
        ');
        $stmt->execute([$plan_name, $min_amount, $max_amount, $daily_percentage, $monthly_percentage, $duration_days, $description]);
        
        $message = 'Investment plan created successfully';
        recordAuditLog($_SESSION['user_id'], 'create_investment_plan', 'investment_plans', $pdo->lastInsertId(), null, ['name' => $plan_name]);
        
        header('Location: investment-plans.php?tab=list&message=created');
        exit;
    } catch (PDOException $e) {
        $error = 'Failed to create investment plan: ' . $e->getMessage();
        logError('Admin create plan', $e->getMessage());
    }
}

function handleEditPlan($pdo) {
    global $message, $error;
    
    $plan_id = (int)($_POST['plan_id'] ?? 0);
    
    if ($plan_id <= 0) {
        $error = 'Invalid plan ID';
        return;
    }

    $plan_name = trim($_POST['plan_name'] ?? '');
    $min_amount = (float)($_POST['min_amount'] ?? 0);
    $max_amount = (float)($_POST['max_amount'] ?? 0);
    $daily_percentage = (float)($_POST['daily_percentage'] ?? 0);
    $monthly_percentage = (float)($_POST['monthly_percentage'] ?? 0);
    $duration_days = (int)($_POST['duration_days'] ?? 0) ?: null;
    $description = trim($_POST['description'] ?? '');

    if (empty($plan_name)) {
        $error = 'Plan name is required';
        return;
    }
    if ($min_amount <= 0 || $max_amount <= 0 || $min_amount >= $max_amount) {
        $error = 'Invalid amount range';
        return;
    }

    try {
        $stmt = $pdo->prepare('
            UPDATE investment_plans 
            SET plan_name = ?, min_amount = ?, max_amount = ?, daily_percentage = ?, 
                monthly_percentage = ?, duration_days = ?, description = ?, updated_at = NOW()
            WHERE plan_id = ?
        ');
        $stmt->execute([$plan_name, $min_amount, $max_amount, $daily_percentage, $monthly_percentage, $duration_days, $description, $plan_id]);
        
        $message = 'Investment plan updated successfully';
        recordAuditLog($_SESSION['user_id'], 'edit_investment_plan', 'investment_plans', $plan_id, null, ['name' => $plan_name]);
        
        header('Location: investment-plans.php?tab=list&message=updated');
        exit;
    } catch (PDOException $e) {
        $error = 'Failed to update investment plan: ' . $e->getMessage();
        logError('Admin edit plan', $e->getMessage());
    }
}

function handleDeletePlan($pdo) {
    global $message, $error;
    
    $plan_id = (int)($_POST['plan_id'] ?? 0);
    
    if ($plan_id <= 0) {
        $error = 'Invalid plan ID';
        return;
    }

    try {
        // Check if plan has active investments
        $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM plan_investments WHERE plan_id = ? AND status = "active"');
        $stmt->execute([$plan_id]);
        $result = $stmt->fetch();
        
        if ($result['count'] > 0) {
            $error = 'Cannot delete plan with active investments. Deactivate the plan first or wait for investments to expire.';
            return;
        }

        $stmt = $pdo->prepare('DELETE FROM investment_plans WHERE plan_id = ?');
        $stmt->execute([$plan_id]);
        
        $message = 'Investment plan deleted successfully';
        recordAuditLog($_SESSION['user_id'], 'delete_investment_plan', 'investment_plans', $plan_id, null, null);
        
        header('Location: investment-plans.php?tab=list&message=deleted');
        exit;
    } catch (PDOException $e) {
        $error = 'Failed to delete investment plan: ' . $e->getMessage();
        logError('Admin delete plan', $e->getMessage());
    }
}

function handleToggleStatus($pdo) {
    global $message, $error;
    
    $plan_id = (int)($_POST['plan_id'] ?? 0);
    
    if ($plan_id <= 0) {
        $error = 'Invalid plan ID';
        return;
    }

    try {
        $stmt = $pdo->prepare('UPDATE investment_plans SET is_active = NOT is_active WHERE plan_id = ?');
        $stmt->execute([$plan_id]);
        
        $message = 'Investment plan status updated successfully';
        recordAuditLog($_SESSION['user_id'], 'toggle_investment_plan_status', 'investment_plans', $plan_id, null, null);
    } catch (PDOException $e) {
        $error = 'Failed to update investment plan status';
        logError('Admin toggle plan status', $e->getMessage());
    }
}
?>
