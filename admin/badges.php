<?php
/**
 * Admin - Badges Management (Enhanced UI)
 * Manage achievement badges: Create, Edit, Delete, Activate/Deactivate
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/common.php';

requireAdmin();

$pageTitle = 'Badges Management';
$message = '';
$error = '';
$csrf_token = generateCSRFToken();
$editingBadge = null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $token = $_POST['csrf_token'] ?? '';
    
    if (!validateCSRFToken($token)) {
        $error = 'Invalid CSRF token';
    } else {
        switch ($action) {
            case 'create':
                handleCreateBadge($pdo);
                break;
            case 'edit':
                handleEditBadge($pdo);
                break;
            case 'delete':
                handleDeleteBadge($pdo);
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
$criteriaFilter = $_GET['criteria_type'] ?? '';

// Get badge to edit if in edit mode
if ($tab === 'edit' && isset($_GET['badge_id'])) {
    try {
        $stmt = $pdo->prepare('SELECT * FROM badges WHERE badge_id = ?');
        $stmt->execute([(int)$_GET['badge_id']]);
        $editingBadge = $stmt->fetch();
    } catch (PDOException $e) {
        logError('Get badge for edit', $e->getMessage());
    }
}

$criteriaTypes = [
    'total_investment' => 'Total Investment Amount',
    'total_earnings' => 'Total Earnings',
    'team_size' => 'Team Size',
    'tenure_days' => 'Membership Tenure (Days)'
];

$sortMap = [
    'badge_name' => 'b.badge_name',
    'criteria_type' => 'b.criteria_type',
    'criteria_value' => 'b.criteria_value',
    'created_at' => 'b.created_at',
];
$sortKey = adminGetSortableColumn($_GET['sort'] ?? 'badge_name', $sortMap, 'badge_name');
$sortDir = adminGetSortDirection($_GET['dir'] ?? 'asc', 'asc');

// Get all badges
try {
    $where = ['1=1'];
    $params = [];

    if ($search !== '') {
        $where[] = '(b.badge_name LIKE ? OR COALESCE(b.description, "") LIKE ?)';
        $like = adminLike($search);
        array_push($params, $like, $like);
    }

    if ($statusFilter === 'active') {
        $where[] = 'b.is_active = 1';
    } elseif ($statusFilter === 'inactive') {
        $where[] = 'b.is_active = 0';
    }

    if (isset($criteriaTypes[$criteriaFilter])) {
        $where[] = 'b.criteria_type = ?';
        $params[] = $criteriaFilter;
    }

    $stmt = $pdo->prepare('
        SELECT b.*, COUNT(ub.user_id) AS assigned_count
        FROM badges b
        LEFT JOIN user_badges ub ON ub.badge_id = b.badge_id
        WHERE ' . implode(' AND ', $where) . '
        GROUP BY b.badge_id
        ORDER BY ' . $sortMap[$sortKey] . ' ' . $sortDir
    );
    $stmt->execute($params);
    $badges = $stmt->fetchAll();
} catch (PDOException $e) {
    logError('Admin badges', $e->getMessage());
    $badges = [];
    $error = 'Failed to load badges';
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
        .color-preview { 
            width: 80px; 
            height: 80px; 
            border-radius: 0.75rem;
            border: 3px solid var(--color-gray-300);
            display: inline-block;
            vertical-align: middle;
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
                <h2><i class="fas fa-medal mr-2"></i>Badges Management</h2>
                <p>Create and manage achievement badges for users</p>
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
                <i class="fas fa-list"></i> All Badges
            </a>
            <a href="?tab=create" class="tab-btn <?php echo ($tab === 'create') ? 'active' : ''; ?>">
                <i class="fas fa-plus"></i> Create New Badge
            </a>
        </div>

        <!-- List View -->
        <?php if ($tab === 'list' || $tab === 'edit'): ?>
            <div class="card filters-card screen-only">
                <div class="card-header">
                    <div>
                        <h3>Filter Badges</h3>
                        <p>Search badge titles, narrow by criteria type, and print the final set if needed.</p>
                    </div>
                </div>
                <form method="get" class="filters-form">
                    <input type="hidden" name="tab" value="list">
                    <div class="form-group">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" class="form-input" value="<?php echo htmlspecialchars($search); ?>" placeholder="Badge name or description">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All</option>
                            <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Criteria Type</label>
                        <select name="criteria_type" class="form-select">
                            <option value="">All</option>
                            <?php foreach ($criteriaTypes as $key => $label): ?>
                                <option value="<?php echo htmlspecialchars($key); ?>" <?php echo $criteriaFilter === $key ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                            <?php endforeach; ?>
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
                    <h3>Achievement Badges</h3>
                    <span class="badge badge-primary"><?php echo count($badges); ?> Badges</span>
                </div>
                
                <?php if (empty($badges)): ?>
                    <div class="text-center py-12">
                        <i class="fas fa-star text-gray-400" style="font-size: 3rem;"></i>
                        <p class="text-gray-500 mt-4">No badges created yet. Create one to get started.</p>
                        <a href="?tab=create" class="btn btn-primary mt-4">
                            <i class="fas fa-plus"></i> Create Badge
                        </a>
                    </div>
                <?php else: ?>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 1.5rem;">
                        <?php foreach ($badges as $badge): ?>
                            <div class="card" style="position: relative; overflow: hidden;">
                                <!-- Status Badge -->
                                <div style="position: absolute; top: 1rem; right: 1rem; z-index: 10;">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="badge_id" value="<?php echo $badge['badge_id']; ?>">
                                        <button type="submit" class="badge <?php echo $badge['is_active'] ? 'badge-success' : 'badge-danger'; ?>" style="cursor: pointer; border: none;">
                                            <?php echo $badge['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </button>
                                    </form>
                                </div>

                                <!-- Badge Color Preview -->
                                <?php $badgeColor = !empty($badge['badge_color']) ? $badge['badge_color'] : '#3b82f6'; ?>
                                <div class="color-preview" style="background-color: <?php echo htmlspecialchars($badgeColor); ?>;"></div>

                                <!-- Badge Info -->
                                <h3 style="font-size: 1.1rem; font-weight: 600; margin-top: 1rem;">
                                    <?php echo htmlspecialchars($badge['badge_name']); ?>
                                </h3>

                                <p style="color: var(--color-gray-600); font-size: 0.9rem; margin: 0.5rem 0;">
                                    <strong>Criteria:</strong> <?php echo htmlspecialchars($criteriaTypes[$badge['criteria_type']] ?? $badge['criteria_type']); ?>
                                </p>

                                <p style="color: var(--color-gray-600); font-size: 0.9rem; margin: 0.5rem 0;">
                                    <strong>Value:</strong> <?php echo htmlspecialchars($badge['criteria_value']); ?>
                                </p>

                                <p class="table-subtext">Assigned to <?php echo (int) $badge['assigned_count']; ?> users</p>

                                <p style="color: var(--color-gray-500); font-size: 0.85rem; margin-top: 0.75rem;">
                                    <?php echo htmlspecialchars(mb_strimwidth($badge['description'] ?? '', 0, 100, '...')); ?>
                                </p>

                                <!-- Actions -->
                                <div class="btn-group" style="margin-top: 1rem;">
                                    <a href="?tab=edit&badge_id=<?php echo $badge['badge_id']; ?>" class="btn btn-primary btn-small" style="flex: 1;">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <form method="POST" style="display: inline; flex: 1;" onsubmit="return confirm('Delete this badge? This cannot be undone.');">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="badge_id" value="<?php echo $badge['badge_id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-small" style="width: 100%;">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Create / Edit View -->
        <?php if ($tab === 'create' || $tab === 'edit'): ?>
            <div class="card" style="max-width: 800px;">
                <div class="card-header">
                    <h3><?php echo ($tab === 'edit' && $editingBadge) ? 'Edit Badge' : 'Create New Badge'; ?></h3>
                </div>

                <form method="POST" class="card-body">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" name="action" value="<?php echo ($tab === 'edit' && $editingBadge) ? 'edit' : 'create'; ?>">
                    <?php if ($tab === 'edit' && $editingBadge): ?>
                        <input type="hidden" name="badge_id" value="<?php echo $editingBadge['badge_id']; ?>">
                    <?php endif; ?>

                    <!-- Badge Name -->
                    <div class="form-group">
                        <label class="form-label required">Badge Name</label>
                        <input type="text" name="badge_name" required class="form-input" 
                               placeholder="e.g., Gold Member"
                               value="<?php echo $editingBadge ? htmlspecialchars($editingBadge['badge_name']) : ''; ?>">
                    </div>

                    <!-- Badge Color -->
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label required">Badge Color</label>
                            <div class="color-picker-wrapper">
                                <input type="color" name="color" required class="form-input" style="max-width: 100px; height: 50px;"
                                       id="colorPicker"
                                       value="<?php echo $editingBadge ? $editingBadge['badge_color'] : '#3b82f6'; ?>">
                                <div class="color-preview" id="colorPreview" style="background-color: <?php echo $editingBadge ? $editingBadge['badge_color'] : '#3b82f6'; ?>;"></div>
                                <span id="colorValue" style="margin-left: 1rem; font-weight: 500;">
                                    <?php echo $editingBadge ? strtoupper(ltrim($editingBadge['badge_color'], '#')) : '3B82F6'; ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Criteria -->
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label required">Criteria Type</label>
                            <select name="criteria_type" required class="form-select" id="criteriaType">
                                <option value="">Select criteria type</option>
                                <?php foreach ($criteriaTypes as $key => $label): ?>
                                    <option value="<?php echo htmlspecialchars($key); ?>" 
                                            <?php echo ($editingBadge && $editingBadge['criteria_type'] === $key) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label required" id="criteriaValueLabel">Criteria Value</label>
                            <input type="number" step="0.01" name="criteria_value" required class="form-input" 
                                   id="criteriaValue"
                                   placeholder="Enter value"
                                   value="<?php echo $editingBadge ? $editingBadge['criteria_value'] : ''; ?>">
                            <small id="criteriaHint" style="color: var(--color-gray-500); display: none;"></small>
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-textarea" 
                                  placeholder="Badge description for users"><?php echo $editingBadge ? htmlspecialchars($editingBadge['description'] ?? '') : ''; ?></textarea>
                    </div>

                    <!-- Action Buttons -->
                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> <?php echo ($tab === 'edit' && $editingBadge) ? 'Update Badge' : 'Create Badge'; ?>
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
        // Color picker
        const colorPicker = document.getElementById('colorPicker');
        const colorPreview = document.getElementById('colorPreview');
        const colorValue = document.getElementById('colorValue');

        if (colorPicker) {
            colorPicker.addEventListener('change', function() {
                const color = this.value.substring(1).toUpperCase();
                colorPreview.style.backgroundColor = this.value;
                colorValue.textContent = color;
            });
        }

        // Criteria type labels
        const criteriaHints = {
            'total_investment': 'Amount in ₹',
            'total_earnings': 'Amount in ₹',
            'team_size': 'Number of team members',
            'tenure_days': 'Number of days'
        };

        const criteriaType = document.getElementById('criteriaType');
        const criteriaHint = document.getElementById('criteriaHint');
        const criteriaValueLabel = document.getElementById('criteriaValueLabel');

        if (criteriaType) {
            criteriaType.addEventListener('change', function() {
                if (this.value && criteriaHints[this.value]) {
                    criteriaHint.textContent = criteriaHints[this.value];
                    criteriaHint.style.display = 'block';
                }
            });

            // Trigger on page load
            if (criteriaType.value) {
                criteriaType.dispatchEvent(new Event('change'));
            }
        }
    </script>
</body>
</html>

<?php

function handleCreateBadge($pdo) {
    global $message, $error;
    
    $badge_name = trim($_POST['badge_name'] ?? '');
    $color = trim($_POST['color'] ?? '#3b82f6');
    $criteria_type = trim($_POST['criteria_type'] ?? '');
    $criteria_value = (float)($_POST['criteria_value'] ?? 0);
    $description = trim($_POST['description'] ?? '');

    if (empty($badge_name)) {
        $error = 'Badge name is required';
        return;
    }
    if (empty($criteria_type)) {
        $error = 'Criteria type is required';
        return;
    }
    if ($criteria_value <= 0) {
        $error = 'Criteria value must be greater than 0';
        return;
    }

    try {
        $stmt = $pdo->prepare('
            INSERT INTO badges 
            (badge_name, badge_color, criteria_type, criteria_value, description, is_active)
            VALUES (?, ?, ?, ?, ?, 1)
        ');
        $stmt->execute([$badge_name, $color, $criteria_type, $criteria_value, $description]);
        
        $message = 'Badge created successfully';
        recordAuditLog($_SESSION['user_id'], 'create_badge', 'badges', $pdo->lastInsertId(), null, ['name' => $badge_name]);
        
        header('Location: badges.php?tab=list&message=created');
        exit;
    } catch (PDOException $e) {
        $error = 'Failed to create badge: ' . $e->getMessage();
        logError('Admin create badge', $e->getMessage());
    }
}

function handleEditBadge($pdo) {
    global $message, $error;
    
    $badge_id = (int)($_POST['badge_id'] ?? 0);
    
    if ($badge_id <= 0) {
        $error = 'Invalid badge ID';
        return;
    }

    $badge_name = trim($_POST['badge_name'] ?? '');
    $color = trim($_POST['color'] ?? '#3b82f6');
    $criteria_type = trim($_POST['criteria_type'] ?? '');
    $criteria_value = (float)($_POST['criteria_value'] ?? 0);
    $description = trim($_POST['description'] ?? '');

    if (empty($badge_name)) {
        $error = 'Badge name is required';
        return;
    }
    if (empty($criteria_type)) {
        $error = 'Criteria type is required';
        return;
    }

    try {
        $stmt = $pdo->prepare('
            UPDATE badges 
            SET badge_name = ?, badge_color = ?, criteria_type = ?, criteria_value = ?, 
                description = ?, updated_at = NOW()
            WHERE badge_id = ?
        ');
        $stmt->execute([$badge_name, $color, $criteria_type, $criteria_value, $description, $badge_id]);
        
        $message = 'Badge updated successfully';
        recordAuditLog($_SESSION['user_id'], 'edit_badge', 'badges', $badge_id, null, ['name' => $badge_name]);
        
        header('Location: badges.php?tab=list&message=updated');
        exit;
    } catch (PDOException $e) {
        $error = 'Failed to update badge: ' . $e->getMessage();
        logError('Admin edit badge', $e->getMessage());
    }
}

function handleDeleteBadge($pdo) {
    global $message, $error;
    
    $badge_id = (int)($_POST['badge_id'] ?? 0);
    
    if ($badge_id <= 0) {
        $error = 'Invalid badge ID';
        return;
    }

    try {
        // Check if badge is assigned to any users
        $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM user_badges WHERE badge_id = ?');
        $stmt->execute([$badge_id]);
        $result = $stmt->fetch();
        
        if ($result['count'] > 0) {
            $error = 'Cannot delete badge that is assigned to users. Remove assignments first.';
            return;
        }

        $stmt = $pdo->prepare('DELETE FROM badges WHERE badge_id = ?');
        $stmt->execute([$badge_id]);
        
        $message = 'Badge deleted successfully';
        recordAuditLog($_SESSION['user_id'], 'delete_badge', 'badges', $badge_id, null, null);
        
        header('Location: badges.php?tab=list&message=deleted');
        exit;
    } catch (PDOException $e) {
        $error = 'Failed to delete badge: ' . $e->getMessage();
        logError('Admin delete badge', $e->getMessage());
    }
}

function handleToggleStatus($pdo) {
    global $message, $error;
    
    $badge_id = (int)($_POST['badge_id'] ?? 0);
    
    if ($badge_id <= 0) {
        $error = 'Invalid badge ID';
        return;
    }

    try {
        $stmt = $pdo->prepare('UPDATE badges SET is_active = NOT is_active WHERE badge_id = ?');
        $stmt->execute([$badge_id]);
        
        $message = 'Badge status updated successfully';
        recordAuditLog($_SESSION['user_id'], 'toggle_badge_status', 'badges', $badge_id, null, null);
    } catch (PDOException $e) {
        $error = 'Failed to update badge status';
        logError('Admin toggle badge status', $e->getMessage());
    }
}
?>
