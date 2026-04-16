<?php
/**
 * Admin - System Configuration (Enhanced UI)
 * Manage all platform configuration settings
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/common.php';

requireAdmin();

$pageTitle = 'System Configuration';
$message = '';
$error = '';
$csrf_token = generateCSRFToken();
$config = [];

// Load configuration from database
function getSystemConfiguration($pdo) {
    try {
        $stmt = $pdo->query('SELECT config_key, config_value, config_type FROM system_config ORDER BY config_key');
        $rows = $stmt->fetchAll();
        $config = [];
        foreach ($rows as $row) {
            $config[$row['config_key']] = [
                'value' => $row['config_value'],
                'type' => $row['config_type']
            ];
        }
        return $config;
    } catch (PDOException $e) {
        logError('Get system config', $e->getMessage());
        return [];
    }
}

$config = getSystemConfiguration($pdo);

// Handle configuration update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    
    if (!validateCSRFToken($token)) {
        $error = 'Invalid CSRF token';
    } else {
        handleConfigurationUpdate($pdo);
        $config = getSystemConfiguration($pdo);
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
</head>
<body class="bg-gray-50">
    <?php require_once __DIR__ . '/navbar.php'; ?>
    
    <!-- Main Content -->
    <main class="admin-content">
        <!-- Page Header -->
        <div class="admin-page-header">
            <div class="admin-page-title">
                <h2><i class="fas fa-cog mr-2"></i>System Configuration</h2>
                <p>Configure platform settings, cashback tiers, bonuses, and payment gateways</p>
            </div>
            <div class="page-tools screen-only">
                <button type="button" class="btn btn-secondary" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
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

        <!-- Configuration Sections -->
        <form method="POST" id="configForm">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

            <!-- 1. Daily Cashback Tiers -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-layer-group mr-2"></i>Daily Cashback Tiers</h3>
                </div>
                <div class="card-body">
                    <p style="color: var(--color-gray-600); margin-bottom: 1.5rem; font-size: 0.9rem;">
                        Configure investment amount tiers and their corresponding daily cashback percentages.
                    </p>

                    <div class="table-responsive">
                        <table class="table" style="margin-bottom: 0;">
                            <thead>
                                <tr>
                                    <th>Tier</th>
                                    <th>Minimum Amount (₹)</th>
                                    <th>Maximum Amount (₹)</th>
                                    <th>Daily Rate (%)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php for ($i = 1; $i <= 4; $i++): ?>
                                    <tr>
                                        <td><strong>Tier <?php echo $i; ?></strong></td>
                                        <td>
                                            <input type="number" step="0.01" 
                                                   name="cashback_tier<?php echo $i; ?>_min"
                                                   class="form-input"
                                                   value="<?php echo htmlspecialchars($config["cashback_tier{$i}_min"]['value'] ?? ''); ?>">
                                        </td>
                                        <td>
                                            <input type="number" step="0.01" 
                                                   name="cashback_tier<?php echo $i; ?>_max"
                                                   class="form-input"
                                                   value="<?php echo htmlspecialchars($config["cashback_tier{$i}_max"]['value'] ?? ''); ?>">
                                        </td>
                                        <td>
                                            <input type="number" step="0.0001" 
                                                   name="cashback_tier<?php echo $i; ?>_daily"
                                                   class="form-input"
                                                   value="<?php echo htmlspecialchars($config["cashback_tier{$i}_daily"]['value'] ?? ''); ?>">
                                        </td>
                                    </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- 2. Bonus 1 Configuration -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-gift mr-2"></i>Bonus 1 - Maintenance Bonus</h3>
                </div>
                <div class="card-body">
                    <p style="color: var(--color-gray-600); margin-bottom: 1.5rem; font-size: 0.9rem;">
                        Configure the maintenance bonus settings. Users get this bonus if they maintain the minimum balance for the specified days.
                    </p>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Bonus Percentage (%)</label>
                            <input type="number" step="0.01" 
                                   name="bonus1_percentage"
                                   class="form-input"
                                   value="<?php echo htmlspecialchars($config['bonus1_percentage']['value'] ?? ''); ?>">
                            <small style="color: var(--color-gray-500);">Extra percentage on monthly cashback</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Minimum Balance (₹)</label>
                            <input type="number" step="0.01" 
                                   name="bonus1_min_balance"
                                   class="form-input"
                                   value="<?php echo htmlspecialchars($config['bonus1_min_balance']['value'] ?? ''); ?>">
                            <small style="color: var(--color-gray-500);">Minimum balance to qualify</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Maintenance Days</label>
                            <input type="number" 
                                   name="bonus1_maintenance_days"
                                   class="form-input"
                                   value="<?php echo htmlspecialchars($config['bonus1_maintenance_days']['value'] ?? ''); ?>">
                            <small style="color: var(--color-gray-500);">Days to maintain balance</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. Bonus 2 Configuration -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-star mr-2"></i>Bonus 2 - Weekly Challenge Bonus</h3>
                </div>
                <div class="card-body">
                    <p style="color: var(--color-gray-600); margin-bottom: 1.5rem; font-size: 0.9rem;">
                        Configure weekly business milestone bonuses for users.
                    </p>

                    <div class="form-row">
                        <!-- Challenge 1 -->
                        <div style="padding: 1.5rem; background: var(--color-gray-50); border-radius: 0.5rem; border-left: 4px solid var(--color-primary);">
                            <h4 style="margin-bottom: 1rem; font-weight: 600;">Challenge 1</h4>

                            <div class="form-group">
                                <label class="form-label">Challenge Amount (₹)</label>
                                <input type="number" step="0.01" 
                                       name="bonus2_threshold1_amount"
                                       class="form-input"
                                       value="<?php echo htmlspecialchars($config['bonus2_threshold1_amount']['value'] ?? ''); ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label">Bonus Percentage (%)</label>
                                <input type="number" step="0.01" 
                                       name="bonus2_threshold1_percentage"
                                       class="form-input"
                                       value="<?php echo htmlspecialchars($config['bonus2_threshold1_percentage']['value'] ?? ''); ?>">
                            </div>
                        </div>

                        <!-- Challenge 2 -->
                        <div style="padding: 1.5rem; background: var(--color-gray-50); border-radius: 0.5rem; border-left: 4px solid var(--color-warning);">
                            <h4 style="margin-bottom: 1rem; font-weight: 600;">Challenge 2</h4>

                            <div class="form-group">
                                <label class="form-label">Challenge Amount (₹)</label>
                                <input type="number" step="0.01" 
                                       name="bonus2_threshold2_amount"
                                       class="form-input"
                                       value="<?php echo htmlspecialchars($config['bonus2_threshold2_amount']['value'] ?? ''); ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label">Bonus Percentage (%)</label>
                                <input type="number" step="0.01" 
                                       name="bonus2_threshold2_percentage"
                                       class="form-input"
                                       value="<?php echo htmlspecialchars($config['bonus2_threshold2_percentage']['value'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 4. Joining Bonus -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-hand-holding-usd mr-2"></i>Joining Bonus</h3>
                </div>
                <div class="card-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Joining Bonus Amount (₹)</label>
                            <input type="number" step="0.01" 
                                   name="joining_bonus_amount"
                                   class="form-input"
                                   value="<?php echo htmlspecialchars($config['joining_bonus_amount']['value'] ?? ''); ?>">
                            <small style="color: var(--color-gray-500);">Amount credited to new users on registration</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 5. Withdrawal Limits -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-money-bill-wave mr-2"></i>Withdrawal Limits</h3>
                </div>
                <div class="card-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Minimum Withdrawal (₹)</label>
                            <input type="number" step="0.01" 
                                   name="min_withdrawal"
                                   class="form-input"
                                   value="<?php echo htmlspecialchars($config['min_withdrawal']['value'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Maximum Withdrawal (₹)</label>
                            <input type="number" step="0.01" 
                                   name="max_withdrawal"
                                   class="form-input"
                                   value="<?php echo htmlspecialchars($config['max_withdrawal']['value'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Daily Withdrawal Limit (₹)</label>
                            <input type="number" step="0.01" 
                                   name="daily_withdrawal_limit"
                                   class="form-input"
                                   value="<?php echo htmlspecialchars($config['daily_withdrawal_limit']['value'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- 6. Payment Gateways -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-credit-card mr-2"></i>Payment Gateways</h3>
                </div>
                <div class="card-body">
                    <p style="color: var(--color-gray-600); margin-bottom: 1.5rem; font-size: 0.9rem;">
                        Enable or disable payment gateway options for users.
                    </p>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;">
                        <?php
                        $gateways = [
                            'stripe_enabled' => ['name' => 'Stripe', 'icon' => 'fas-stripe'],
                            'razorpay_enabled' => ['name' => 'Razorpay', 'icon' => 'fas-rupee-sign'],
                            'paypal_enabled' => ['name' => 'PayPal', 'icon' => 'fas-paypal'],
                            'bank_transfer_enabled' => ['name' => 'Bank Transfer', 'icon' => 'fas-university'],
                            'upi_enabled' => ['name' => 'UPI', 'icon' => 'fas-mobile-alt'],
                        ];
                        ?>
                        <?php foreach ($gateways as $key => $gateway): ?>
                            <div style="padding: 1rem; background: var(--color-gray-50); border-radius: 0.5rem; border: 1px solid var(--color-gray-200); display: flex; align-items: center; justify-content: space-between;">
                                <div>
                                    <i class="fas <?php echo $gateway['icon']; ?>" style="font-size: 1.5rem; color: var(--color-primary); margin-bottom: 0.5rem;"></i>
                                    <strong><?php echo $gateway['name']; ?></strong>
                                </div>
                                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                    <input type="hidden" name="<?php echo $key; ?>" value="0">
                                    <input type="checkbox" name="<?php echo $key; ?>" value="1"
                                           <?php echo ($config[$key]['value'] ?? 0) ? 'checked' : ''; ?>
                                           style="width: 20px; height: 20px;">
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- 7. Platform Settings -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-sliders-h mr-2"></i>Platform Settings</h3>
                </div>
                <div class="card-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Platform Name</label>
                            <input type="text" 
                                   name="platform_name"
                                   class="form-input"
                                   value="<?php echo htmlspecialchars($config['platform_name']['value'] ?? 'BachatPay'); ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Support Email</label>
                            <input type="email" 
                                   name="support_email"
                                   class="form-input"
                                   value="<?php echo htmlspecialchars($config['support_email']['value'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 0.75rem; cursor: pointer; padding: 0.75rem; background: var(--color-gray-50); border-radius: 0.5rem;">
                            <input type="hidden" name="email_notifications_enabled" value="0">
                            <input type="checkbox" name="email_notifications_enabled" value="1"
                                   <?php echo ($config['email_notifications_enabled']['value'] ?? 0) ? 'checked' : ''; ?>>
                            <span><strong>Enable Email Notifications</strong></span>
                        </label>
                        <small style="color: var(--color-gray-500); display: block; margin-top: 0.5rem;">
                            Allow system to send email notifications to users
                        </small>
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="card" style="text-align: center;">
                <button type="submit" class="btn btn-primary btn-large" style="padding: 1rem 3rem; font-size: 1rem;">
                    <i class="fas fa-save mr-2"></i> Save Configuration
                </button>
            </div>
        </form>
    </main>

    <script>
        // Form submission confirmation
        document.getElementById('configForm').addEventListener('submit', function(e) {
            if (!confirm('Save all configuration changes?')) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>

<?php

function handleConfigurationUpdate($pdo) {
    global $message, $error;

    try {
        // Prepare configuration keys and values
        $configValues = [];

        // Cashback tiers
        for ($i = 1; $i <= 4; $i++) {
            $configValues["cashback_tier{$i}_min"] = $_POST["cashback_tier{$i}_min"] ?? '';
            $configValues["cashback_tier{$i}_max"] = $_POST["cashback_tier{$i}_max"] ?? '';
            $configValues["cashback_tier{$i}_daily"] = $_POST["cashback_tier{$i}_daily"] ?? '';
        }

        // Bonus settings
        $configValues['bonus1_percentage'] = $_POST['bonus1_percentage'] ?? '';
        $configValues['bonus1_min_balance'] = $_POST['bonus1_min_balance'] ?? '';
        $configValues['bonus1_maintenance_days'] = $_POST['bonus1_maintenance_days'] ?? '';

        $configValues['bonus2_threshold1_amount'] = $_POST['bonus2_threshold1_amount'] ?? '';
        $configValues['bonus2_threshold1_percentage'] = $_POST['bonus2_threshold1_percentage'] ?? '';
        $configValues['bonus2_threshold2_amount'] = $_POST['bonus2_threshold2_amount'] ?? '';
        $configValues['bonus2_threshold2_percentage'] = $_POST['bonus2_threshold2_percentage'] ?? '';

        // Other settings
        $configValues['joining_bonus_amount'] = $_POST['joining_bonus_amount'] ?? '';
        $configValues['min_withdrawal'] = $_POST['min_withdrawal'] ?? '';
        $configValues['max_withdrawal'] = $_POST['max_withdrawal'] ?? '';
        $configValues['daily_withdrawal_limit'] = $_POST['daily_withdrawal_limit'] ?? '';

        // Payment gateways (checkboxes)
        $configValues['stripe_enabled'] = ($_POST['stripe_enabled'] ?? '0') === '1' ? 1 : 0;
        $configValues['razorpay_enabled'] = ($_POST['razorpay_enabled'] ?? '0') === '1' ? 1 : 0;
        $configValues['paypal_enabled'] = ($_POST['paypal_enabled'] ?? '0') === '1' ? 1 : 0;
        $configValues['bank_transfer_enabled'] = ($_POST['bank_transfer_enabled'] ?? '0') === '1' ? 1 : 0;
        $configValues['upi_enabled'] = ($_POST['upi_enabled'] ?? '0') === '1' ? 1 : 0;

        // Platform settings
        $configValues['platform_name'] = $_POST['platform_name'] ?? 'BachatPay';
        $configValues['support_email'] = $_POST['support_email'] ?? '';
        $configValues['email_notifications_enabled'] = ($_POST['email_notifications_enabled'] ?? '0') === '1' ? 1 : 0;

        // Update all values
        foreach ($configValues as $key => $value) {
            $stmt = $pdo->prepare('
                INSERT INTO system_config (config_key, config_value, updated_by, updated_at)
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                config_value = VALUES(config_value),
                updated_by = VALUES(updated_by),
                updated_at = NOW()
            ');
            $stmt->execute([$key, $value, $_SESSION['user_id']]);
        }

        $message = 'System configuration updated successfully';
        recordAuditLog($_SESSION['user_id'], 'update_system_config', 'system_config', 0, null, null);
    } catch (PDOException $e) {
        $error = 'Failed to update configuration: ' . $e->getMessage();
        logError('Admin update config', $e->getMessage());
    }
}
?>
