<?php
/**
 * BachatPay Database Configuration
 * PDO Connection Setup
 */

// Database credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'bachatpay_db');
define('DB_PORT', 3306);

// Establish PDO connection
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    die('Database Connection Error: ' . $e->getMessage());
}

// Session configuration
session_start();
define('SESSION_TIMEOUT', 3600); // 1 hour

// App constants
define('APP_NAME', 'BachatPay MLM');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'http://localhost/bachat-pay-mlm/');

// Security constants
define('MIN_PASSWORD_LENGTH', 6);
define('SESSION_NAME', 'bachatpay_session');

// MLM Business Rules
define('MAINTENANCE_BONUS_PERCENTAGE', 0.20); // 20%
define('MAINTENANCE_MIN_BALANCE', 2000.00);
define('MAINTENANCE_DAYS', 30);

// Withdrawal limits
define('MIN_WITHDRAWAL', 500);
define('MAX_WITHDRAWAL', 999999);

// Email Configuration
define('MAIL_ENABLED', true); // Set to false to disable email sending
define('MAIL_FROM_EMAIL', 'noreply@bachatpay.com');
define('MAIL_FROM_NAME', 'BachatPay MLM');
define('MAIL_REPLY_TO', 'support@bachatpay.com');

// SMTP Configuration (for production use)
define('SMTP_HOST', 'smtp.gmail.com'); // Change to your SMTP host
define('SMTP_PORT', 587); // 587 for TLS, 465 for SSL, 25 for non-secure
define('SMTP_USERNAME', 'your-email@gmail.com'); // Your SMTP username
define('SMTP_PASSWORD', 'your-app-password'); // Your SMTP password/app password
define('SMTP_ENCRYPTION', 'tls'); // 'tls', 'ssl', or '' for none
define('SMTP_AUTH', false); // Set to false if no authentication required

// Email Templates
define('EMAIL_TEMPLATE_LOGO', BASE_URL . 'assets/images/logos/logo.png');
define('EMAIL_TEMPLATE_COLOR', '#667eea'); // Primary brand color

// Tax (if applicable)
define('WITHDRAWAL_TAX_PERCENTAGE', 0);
// Error reporting (disable in production)
define('DEBUG_MODE', true);


if (DEBUG_MODE === true) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
}
?>
