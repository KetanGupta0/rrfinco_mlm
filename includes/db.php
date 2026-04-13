<?php
/**
 * BachatPay Database Connection Handler
 * PDO wrapper functions for common operations
 */

// Load configuration
require_once __DIR__ . '/config.php';

// ========================================
// Session & Authentication Helpers
// ========================================

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Check if current user is admin
 */
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * Require admin access - redirect if not admin
 */
function requireAdmin() {
    if (!isLoggedIn()) {
        redirect('index.php');
    }
    if (!isAdmin()) {
        redirect('dashboard.php');
    }
}

/**
 * Get current logged-in user data
 */
function getCurrentUser() {
    global $pdo;
    if (!isLoggedIn()) return null;
    
    try {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE user_id = ? AND status = "active"');
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log('Error fetching user: ' . $e->getMessage());
        return null;
    }
}

/**
 * Redirect to specified path
 */
function redirect($path) {
    header("Location: " . BASE_URL . $path);
    exit;
}

/**
 * Check CSRF token
 */
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// ========================================
// User Management Helpers
// ========================================

/**
 * Get user by ID
 */
function getUserById($user_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE user_id = ?');
        $stmt->execute([$user_id]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log('Error fetching user by ID: ' . $e->getMessage());
        return null;
    }
}

/**
 * Get user by username or email
 */
function getUserByUsernameOrEmail($identifier) {
    global $pdo;
    try {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? OR email = ?');
        $stmt->execute([$identifier, $identifier]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log('Error fetching user: ' . $e->getMessage());
        return null;
    }
}

/**
 * Update wallet balance
 */
// function updateWalletBalance($user_id, $amount, $operation = 'add') {
//     global $pdo;
//     try {
//         if ($operation === 'add') {
//             $stmt = $pdo->prepare('UPDATE users SET wallet_balance = wallet_balance + ? WHERE user_id = ?');
//         } else {
//             $stmt = $pdo->prepare('UPDATE users SET wallet_balance = wallet_balance - ? WHERE user_id = ?');
//         }
//         return $stmt->execute([$amount, $user_id]);
//     } catch (PDOException $e) {
//         error_log('Error updating wallet: ' . $e->getMessage());
//         return false;
//     }
// }

/**
 * Record transaction
 */
// function recordTransaction($user_id, $type, $amount, $description = '', $related_user_id = null) {
//     global $pdo;
//     try {
//         $stmt = $pdo->prepare('
//             INSERT INTO transactions (user_id, type, amount, description, related_user_id, status)
//             VALUES (?, ?, ?, ?, ?, "credited")
//         ');
//         return $stmt->execute([$user_id, $type, $amount, $description, $related_user_id]);
//     } catch (PDOException $e) {
//         error_log('Error recording transaction: ' . $e->getMessage());
//         return false;
//     }
// }

// ========================================
// Error Logging
// ========================================

/**
 * Log errors to file
 */
// function logError($message, $details = '') {
//     $logFile = __DIR__ . '/../logs/error.log';
//     if (!is_dir(dirname($logFile))) {
//         mkdir(dirname($logFile), 0755, true);
//     }
//     $message = '[' . date('Y-m-d H:i:s') . '] ' . $message;
//     if ($details) {
//         $message .= ' | Details: ' . $details;
//     }
//     file_put_contents($logFile, $message . "\n", FILE_APPEND);
// }

// Initialize log directory
$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}
?>

