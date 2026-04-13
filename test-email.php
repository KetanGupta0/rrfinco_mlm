<?php
/**
 * Email Testing Script
 * This script tests the email functionality to ensure it's working correctly
 */

// Include required files
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Start session for CSRF token
session_start();

$message = '';
$testResult = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = '<div class="alert alert-danger">Invalid CSRF token</div>';
    } else {
        $testEmail = trim($_POST['test_email'] ?? '');

        if (empty($testEmail)) {
            $message = '<div class="alert alert-warning">Please enter an email address</div>';
        } elseif (!filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
            $message = '<div class="alert alert-danger">Invalid email format</div>';
        } else {
            // Generate a test token
            $testToken = bin2hex(random_bytes(32));

            // Test the password reset email function
            $result = sendPasswordResetEmail($testEmail, $testToken);

            if ($result) {
                $testResult = '<div class="alert alert-success">
                    <strong>Test Email Sent Successfully!</strong><br>
                    Check your email at: <strong>' . htmlspecialchars($testEmail) . '</strong><br>
                    <small>The email contains a password reset link (though it won\'t work for actual password reset)</small>
                </div>';
            } else {
                $testResult = '<div class="alert alert-danger">
                    <strong>Email Sending Failed</strong><br>
                    Check the logs for more details. Make sure your SMTP settings are configured correctly.
                </div>';
            }
        }
    }
}

// Generate CSRF token
$csrfToken = generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Test - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .test-container { max-width: 600px; margin: 50px auto; }
        .card { box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
    </style>
</head>
<body>
    <div class="container test-container">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">
                    <i class="fas fa-envelope"></i> Email Functionality Test
                </h4>
            </div>
            <div class="card-body">
                <?php if ($message): ?>
                    <?php echo $message; ?>
                <?php endif; ?>

                <?php if ($testResult): ?>
                    <?php echo $testResult; ?>
                <?php endif; ?>

                <div class="alert alert-info">
                    <strong>Email Configuration Status:</strong>
                    <ul class="mb-0 mt-2">
                        <li>Email Sending: <strong><?php echo MAIL_ENABLED ? 'ENABLED' : 'DISABLED'; ?></strong></li>
                        <li>SMTP Host: <strong><?php echo SMTP_HOST ?: 'Not configured'; ?></strong></li>
                        <li>SMTP Port: <strong><?php echo SMTP_PORT ?: 'Not configured'; ?></strong></li>
                        <li>SMTP Username: <strong><?php echo SMTP_USERNAME ? 'Configured' : 'Not configured'; ?></strong></li>
                        <li>SMTP Encryption: <strong><?php echo SMTP_ENCRYPTION ?: 'None'; ?></strong></li>
                    </ul>
                </div>

                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

                    <div class="mb-3">
                        <label for="test_email" class="form-label">Test Email Address</label>
                        <input type="email" class="form-control" id="test_email" name="test_email"
                               placeholder="Enter your email address" required>
                        <div class="form-text">
                            Enter a valid email address where you can receive test emails.
                        </div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Send Test Email
                        </button>
                    </div>
                </form>

                <hr>

                <div class="alert alert-warning">
                    <strong>Note:</strong> This test sends a password reset email template.
                    The reset link won't work for actual password reset, but it will verify that your email configuration is working.
                </div>

                <div class="mt-3">
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://kit.fontawesome.com/your-fontawesome-kit.js" crossorigin="anonymous"></script>
</body>
</html>