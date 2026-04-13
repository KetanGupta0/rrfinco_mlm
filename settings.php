<?php
/**
 * BachatPay - Settings Page
 * Manage user profile and bank accounts
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if (!isLoggedIn()) redirect('index.php');
$user = getCurrentUser();
if (!$user) { session_destroy(); redirect('index.php'); }

$tab = $_GET['tab'] ?? 'profile';
$csrf_token = generateCSRFToken();
$error = '';
$success = '';

// =====================
// PROFILE UPDATE
// =====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');

        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            try {
                // Check if email is already used by another user
                $stmt = $pdo->prepare('SELECT user_id FROM users WHERE email = ? AND user_id != ?');
                $stmt->execute([$email, $user['user_id']]);
                if ($stmt->fetch()) {
                    $error = 'This email is already in use by another account.';
                } else {
                    $stmt = $pdo->prepare('
                        UPDATE users 
                        SET first_name = ?, last_name = ?, phone = ?, email = ?
                        WHERE user_id = ?
                    ');
                    $stmt->execute([$first_name, $last_name, $phone, $email, $user['user_id']]);
                    $success = 'Profile updated successfully.';
                    
                    // Refresh user data
                    $user = getCurrentUser();
                }
            } catch (PDOException $e) {
                logError('Profile update failed', $e->getMessage());
                $error = 'Unable to update profile. Please try again.';
            }
        }
    }
}

// =====================
// PASSWORD UPDATE
// =====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // Validate current password
        if (empty($current_password)) {
            $error = 'Please enter your current password.';
        } elseif (!password_verify($current_password, $user['password'])) {
            $error = 'Current password is incorrect.';
        } elseif (empty($new_password)) {
            $error = 'Please enter a new password.';
        } elseif (strlen($new_password) < 8) {
            $error = 'New password must be at least 8 characters long.';
        } elseif ($new_password !== $confirm_password) {
            $error = 'New password and confirmation do not match.';
        } elseif (password_verify($new_password, $user['password'])) {
            $error = 'New password must be different from your current password.';
        } else {
            try {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE user_id = ?');
                $stmt->execute([$hashed_password, $user['user_id']]);

                $success = 'Password updated successfully.';
                logError('Password changed', 'User ' . $user['user_id'] . ' (' . $user['username'] . ') changed password');
            } catch (PDOException $e) {
                logError('Password update failed', $e->getMessage());
                $error = 'Unable to update password. Please try again.';
            }
        }
    }
}

// =====================
// BANK ACCOUNT ACTIONS
// =====================

// Delete bank account
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_bank_account'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $bank_account_id = (int)($_POST['bank_account_id'] ?? 0);
        
        try {
            // Verify this account belongs to current user
            $stmt = $pdo->prepare('SELECT user_id FROM user_bank_accounts WHERE bank_account_id = ?');
            $stmt->execute([$bank_account_id]);
            $account = $stmt->fetch();
            
            if (!$account || $account['user_id'] != $user['user_id']) {
                $error = 'Bank account not found or does not belong to you.';
            } else {
                $pdo->beginTransaction();
                
                // Delete the account
                $stmt = $pdo->prepare('DELETE FROM user_bank_accounts WHERE bank_account_id = ?');
                $stmt->execute([$bank_account_id]);
                
                // If that was primary, set most recent as primary
                $stmt = $pdo->prepare('SELECT bank_account_id FROM user_bank_accounts WHERE user_id = ? ORDER BY created_at DESC LIMIT 1');
                $stmt->execute([$user['user_id']]);
                $nextAccount = $stmt->fetch();
                
                if ($nextAccount) {
                    $stmt = $pdo->prepare('UPDATE user_bank_accounts SET is_primary = 1 WHERE bank_account_id = ?');
                    $stmt->execute([$nextAccount['bank_account_id']]);
                }
                
                $pdo->commit();
                $success = 'Bank account deleted successfully.';
                $tab = 'bank-accounts';
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            logError('Delete bank account failed', $e->getMessage());
            $error = 'Unable to delete bank account. Please try again.';
        }
    }
}

// Set primary bank account
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_primary_bank_account'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $bank_account_id = (int)($_POST['bank_account_id'] ?? 0);
        
        try {
            // Verify this account belongs to current user
            $stmt = $pdo->prepare('SELECT user_id FROM user_bank_accounts WHERE bank_account_id = ?');
            $stmt->execute([$bank_account_id]);
            $account = $stmt->fetch();
            
            if (!$account || $account['user_id'] != $user['user_id']) {
                $error = 'Bank account not found or does not belong to you.';
            } else {
                $pdo->beginTransaction();
                
                // Remove primary from all
                $stmt = $pdo->prepare('UPDATE user_bank_accounts SET is_primary = 0 WHERE user_id = ?');
                $stmt->execute([$user['user_id']]);
                
                // Set this as primary
                $stmt = $pdo->prepare('UPDATE user_bank_accounts SET is_primary = 1 WHERE bank_account_id = ?');
                $stmt->execute([$bank_account_id]);
                
                $pdo->commit();
                $success = 'Primary bank account updated.';
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            logError('Set primary bank account failed', $e->getMessage());
            $error = 'Unable to update primary account. Please try again.';
        }
    }
}

// Add or update bank account
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['add_bank_account']) || isset($_POST['edit_bank_account']))) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $account_holder_name = trim($_POST['account_holder_name'] ?? '');
        $bank_name = trim($_POST['bank_name'] ?? '');
        $account_number = trim($_POST['account_number'] ?? '');
        $ifsc_code = trim($_POST['ifsc_code'] ?? '');
        $make_primary = isset($_POST['make_primary']) ? 1 : 0;
        $bank_account_id = isset($_POST['bank_account_id']) ? (int)($_POST['bank_account_id']) : null;

        if (empty($account_holder_name) || empty($bank_name) || empty($account_number) || empty($ifsc_code)) {
            $error = 'Please fill in all required fields.';
        } else {
            try {
                // Check for duplicate account number for this user
                $stmt = $pdo->prepare('
                    SELECT bank_account_id FROM user_bank_accounts 
                    WHERE user_id = ? AND account_number = ?
                    ' . ($bank_account_id ? 'AND bank_account_id != ?' : '')
                );
                
                if ($bank_account_id) {
                    $stmt->execute([$user['user_id'], $account_number, $bank_account_id]);
                } else {
                    $stmt->execute([$user['user_id'], $account_number]);
                }
                
                if ($stmt->fetch()) {
                    $error = 'This account number is already saved. Please use a different account number.';
                } else {
                    $pdo->beginTransaction();

                    if ($bank_account_id) {
                        // EDIT existing account
                        $stmt = $pdo->prepare('SELECT user_id FROM user_bank_accounts WHERE bank_account_id = ?');
                        $stmt->execute([$bank_account_id]);
                        $account = $stmt->fetch();
                        
                        if (!$account || $account['user_id'] != $user['user_id']) {
                            throw new Exception('Bank account not found or does not belong to you.');
                        }
                        
                        if ($make_primary) {
                            $stmt = $pdo->prepare('UPDATE user_bank_accounts SET is_primary = 0 WHERE user_id = ?');
                            $stmt->execute([$user['user_id']]);
                        }

                        $stmt = $pdo->prepare('
                            UPDATE user_bank_accounts 
                            SET account_holder_name = ?, bank_name = ?, account_number = ?, ifsc_code = ?, is_primary = ?
                            WHERE bank_account_id = ?
                        ');
                        $stmt->execute([$account_holder_name, $bank_name, $account_number, $ifsc_code, $make_primary, $bank_account_id]);
                        
                        $success = 'Bank account updated successfully.';
                    } else {
                        // ADD new account
                        if ($make_primary) {
                            $stmt = $pdo->prepare('UPDATE user_bank_accounts SET is_primary = 0 WHERE user_id = ?');
                            $stmt->execute([$user['user_id']]);
                        }

                        $stmt = $pdo->prepare('
                            INSERT INTO user_bank_accounts (user_id, account_holder_name, bank_name, account_number, ifsc_code, is_primary, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, NOW())
                        ');
                        $stmt->execute([$user['user_id'], $account_holder_name, $bank_name, $account_number, $ifsc_code, $make_primary]);
                        
                        $success = 'Bank account added successfully.';
                    }
                    
                    $pdo->commit();
                    $tab = 'bank-accounts';
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                logError('Bank account operation failed', $e->getMessage());
                $error = $e->getMessage() ?: 'Unable to save bank account. Please try again.';
            }
        }
    }
}

// Load bank accounts
$stmt = $pdo->prepare('SELECT * FROM user_bank_accounts WHERE user_id = ? ORDER BY is_primary DESC, created_at DESC');
$stmt->execute([$user['user_id']]);
$bank_accounts = $stmt->fetchAll();

// Load account for editing if account_id is provided
$edit_account = null;
if (isset($_GET['edit_account'])) {
    $account_id = (int)($_GET['edit_account']);
    $stmt = $pdo->prepare('SELECT * FROM user_bank_accounts WHERE bank_account_id = ? AND user_id = ?');
    $stmt->execute([$account_id, $user['user_id']]);
    $edit_account = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - BachatPay</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <?php $currentPage = 'settings'; require_once __DIR__ . '/includes/navbar.php'; ?>

    <div class="lg:ml-64 px-4 sm:px-6 lg:px-8 py-8">
        <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-4xl font-bold text-gray-900 mb-2">Settings</h1>
                <p class="text-gray-600">Manage your profile and bank accounts.</p>
            </div>
            <div class="space-x-2">
                <a href="settings.php?tab=profile" class="px-4 py-2 rounded-lg <?php echo $tab === 'profile' ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 border border-gray-200'; ?>">Profile</a>
                <a href="settings.php?tab=password" class="px-4 py-2 rounded-lg <?php echo $tab === 'password' ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 border border-gray-200'; ?>">Password</a>
                <a href="settings.php?tab=bank-accounts" class="px-4 py-2 rounded-lg <?php echo $tab === 'bank-accounts' ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 border border-gray-200'; ?>">Bank Accounts</a>
            </div>
        </div>

        <?php if ($success): ?>
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6 flex items-center">
            <i class="fas fa-check-circle mr-3"></i><?php echo htmlspecialchars($success); ?>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6 flex items-center">
            <i class="fas fa-exclamation-circle mr-3"></i><?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <!-- PROFILE TAB -->
        <?php if ($tab === 'profile'): ?>
            <div class="bg-white rounded-lg shadow p-8 max-w-4xl">
                <h2 class="text-2xl font-bold text-gray-900 mb-8">Edit Profile</h2>
                
                <form method="POST" class="space-y-6">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" name="update_profile" value="1">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- First Name -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">First Name</label>
                            <input type="text" name="first_name" value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>

                        <!-- Last Name -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Last Name</label>
                            <input type="text" name="last_name" value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>

                        <!-- Email -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Email Address *</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>

                        <!-- Phone -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Phone Number</label>
                            <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>

                        <!-- Username (Read-only) -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Username (Read-only)</label>
                            <input type="text" value="<?php echo htmlspecialchars($user['username']); ?>" disabled
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-100 text-gray-600">
                        </div>

                        <!-- Registration Date (Read-only) -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Member Since (Read-only)</label>
                            <input type="text" value="<?php echo date('M d, Y', strtotime($user['registration_date'])); ?>" disabled
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-100 text-gray-600">
                        </div>
                    </div>

                    <div class="pt-6 border-t">
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded-lg transition">
                            <i class="fas fa-save mr-2"></i>Save Changes
                        </button>
                    </div>
                </form>
            </div>

        <!-- PASSWORD TAB -->
        <?php elseif ($tab === 'password'): ?>
            <div class="bg-white rounded-lg shadow p-8 max-w-2xl">
                <h2 class="text-2xl font-bold text-gray-900 mb-8">Change Password</h2>

                <form method="POST" class="space-y-6">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <input type="hidden" name="update_password" value="1">

                    <!-- Current Password -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Current Password *</label>
                        <input type="password" name="current_password" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="Enter your current password">
                    </div>

                    <!-- New Password -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">New Password *</label>
                        <input type="password" name="new_password" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="Enter new password (minimum 8 characters)"
                               minlength="8">
                        <p class="text-sm text-gray-500 mt-1">Password must be at least 8 characters long</p>
                    </div>

                    <!-- Confirm New Password -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Confirm New Password *</label>
                        <input type="password" name="confirm_password" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="Confirm your new password">
                    </div>

                    <div class="pt-6 border-t">
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-8 rounded-lg transition">
                            <i class="fas fa-key mr-2"></i>Update Password
                        </button>
                    </div>
                </form>
            </div>

        <!-- BANK ACCOUNTS TAB -->
        <?php else: ?>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Bank Accounts List -->
                <div class="lg:col-span-2 bg-white rounded-lg shadow p-6">
                    <h2 class="text-2xl font-bold text-gray-900 mb-6">Saved Bank Accounts</h2>
                    
                    <?php if (empty($bank_accounts)): ?>
                        <div class="text-center py-16 text-gray-500">
                            <i class="fas fa-inbox text-4xl mb-4"></i>
                            <p class="mb-4">No bank accounts saved yet.</p>
                            <a href="settings.php?tab=bank-accounts" class="text-blue-600 hover:text-blue-800">Add your first bank account</a>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($bank_accounts as $account): ?>
                                <div class="border border-gray-200 rounded-lg p-5 hover:shadow-md transition">
                                    <div class="flex items-start justify-between mb-3">
                                        <div class="flex-1">
                                            <p class="text-sm text-gray-600"><?php echo htmlspecialchars($account['account_holder_name']); ?></p>
                                            <p class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($account['bank_name']); ?></p>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <?php if ($account['is_primary']): ?>
                                                <span class="text-xs font-semibold uppercase bg-green-100 text-green-800 px-3 py-1 rounded-full">Primary</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <p class="text-sm text-gray-600">Account: ••••••••<?php echo substr(htmlspecialchars($account['account_number']), -4); ?></p>
                                    <p class="text-sm text-gray-600 mt-1">IFSC: <?php echo htmlspecialchars($account['ifsc_code']); ?></p>
                                    <p class="text-xs text-gray-500 mt-3 mb-4">Added <?php echo date('M d, Y', strtotime($account['created_at'])); ?></p>
                                    
                                    <!-- Action Buttons -->
                                    <div class="flex gap-2 pt-3 border-t border-gray-200">
                                        <a href="settings.php?tab=bank-accounts&edit_account=<?php echo $account['bank_account_id']; ?>" 
                                           class="flex-1 text-sm text-center bg-blue-50 hover:bg-blue-100 text-blue-600 py-2 rounded transition">
                                            <i class="fas fa-edit mr-1"></i>Edit
                                        </a>
                                        
                                        <?php if (!$account['is_primary'] && count($bank_accounts) > 1): ?>
                                            <form method="POST" action="" class="flex-1">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                                <input type="hidden" name="set_primary_bank_account" value="1">
                                                <input type="hidden" name="bank_account_id" value="<?php echo $account['bank_account_id']; ?>">
                                                <button type="submit" class="w-full text-sm text-center bg-green-50 hover:bg-green-100 text-green-600 py-2 rounded transition">
                                                    <i class="fas fa-check mr-1"></i>Make Primary
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <?php if (count($bank_accounts) > 1): ?>
                                            <form method="POST" action="" class="flex-1" onsubmit="return confirm('Delete this bank account?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                                <input type="hidden" name="delete_bank_account" value="1">
                                                <input type="hidden" name="bank_account_id" value="<?php echo $account['bank_account_id']; ?>">
                                                <button type="submit" class="w-full text-sm text-center bg-red-50 hover:bg-red-100 text-red-600 py-2 rounded transition">
                                                    <i class="fas fa-trash mr-1"></i>Delete
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Add/Edit Bank Account Form -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">
                        <?php echo $edit_account ? 'Edit Bank Account' : 'Add Bank Account'; ?>
                    </h2>
                    
                    <?php if ($edit_account): ?>
                        <p class="text-sm text-gray-600 mb-4">Editing account ending in <?php echo substr($edit_account['account_number'], -4); ?></p>
                    <?php endif; ?>
                    
                    <form method="POST" action="" class="space-y-4">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <?php if ($edit_account): ?>
                            <input type="hidden" name="edit_bank_account" value="1">
                            <input type="hidden" name="bank_account_id" value="<?php echo $edit_account['bank_account_id']; ?>">
                        <?php else: ?>
                            <input type="hidden" name="add_bank_account" value="1">
                        <?php endif; ?>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Account Holder Name *</label>
                            <input type="text" name="account_holder_name" required 
                                   value="<?php echo htmlspecialchars($edit_account['account_holder_name'] ?? $_POST['account_holder_name'] ?? ''); ?>" 
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Bank Name *</label>
                            <input type="text" name="bank_name" required 
                                   value="<?php echo htmlspecialchars($edit_account['bank_name'] ?? $_POST['bank_name'] ?? ''); ?>" 
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Account Number *</label>
                            <input type="text" name="account_number" required 
                                   value="<?php echo htmlspecialchars($edit_account['account_number'] ?? $_POST['account_number'] ?? ''); ?>" 
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <p class="text-xs text-gray-500 mt-1">Note: You cannot add the same account number twice.</p>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">IFSC Code *</label>
                            <input type="text" name="ifsc_code" required 
                                   value="<?php echo htmlspecialchars($edit_account['ifsc_code'] ?? $_POST['ifsc_code'] ?? ''); ?>" 
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        </div>

                        <div>
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="make_primary" class="form-checkbox h-5 w-5 text-blue-600" 
                                       <?php echo (isset($_POST['make_primary']) || ($edit_account && $edit_account['is_primary'])) ? 'checked' : ''; ?> />
                                <span class="ml-2 text-gray-700">Set as primary</span>
                            </label>
                        </div>

                        <div class="flex gap-2 pt-4">
                            <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 rounded-lg transition">
                                <i class="fas <?php echo $edit_account ? 'fa-save' : 'fa-plus'; ?> mr-2"></i><?php echo $edit_account ? 'Update' : 'Add'; ?>
                            </button>
                            
                            <?php if ($edit_account): ?>
                                <a href="settings.php?tab=bank-accounts" class="flex-1 text-center bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 rounded-lg transition">
                                    Cancel
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
