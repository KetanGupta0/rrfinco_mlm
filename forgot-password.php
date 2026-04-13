<?php
/**
 * BachatPay Forgot Password Page
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

// Check if already logged in
if (isLoggedIn()) {
    redirect('dashboard.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $csrf_token = $_POST['csrf_token'] ?? '';

    // Verify CSRF token
    if (!validateCSRFToken($csrf_token)) {
        $error = 'Invalid request. Please try again.';
    } elseif (empty($email)) {
        $error = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            // Check if user exists
            $stmt = $pdo->prepare('SELECT user_id, username FROM users WHERE email = ? AND status = "active"');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                // Generate reset token
                $resetToken = generateResetToken();
                
                // Get expiry time from database to avoid timezone issues
                $stmt = $pdo->prepare('SELECT DATE_ADD(NOW(), INTERVAL 1 HOUR) as expiry_time');
                $stmt->execute();
                $timeResult = $stmt->fetch();
                $expires = $timeResult['expiry_time'];

                // Store token in database
                $stmt = $pdo->prepare('UPDATE users SET reset_token = ?, reset_expires = ? WHERE user_id = ?');
                $stmt->execute([$resetToken, $expires, $user['user_id']]);

                // Send reset email
                if (sendPasswordResetEmail($email, $resetToken)) {
                    $success = 'Password reset instructions have been sent to your email address. Please check your inbox and follow the link to reset your password.';
                    logError('Password reset requested', 'User: ' . $user['username'] . ' (' . $email . ')');
                } else {
                    $error = 'Failed to send reset email. Please try again later.';
                }
            } else {
                // Don't reveal if email exists or not for security
                $success = 'If an account with that email address exists, password reset instructions have been sent.';
            }
        } catch (PDOException $e) {
            $error = 'An error occurred. Please try again later.';
            logError('Forgot password error', $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - BachatPay MLM Platform</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .animate-float {
            animation: float 3s ease-in-out infinite;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="w-full max-w-md">
            <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
                <!-- Header -->
                <div class="gradient-bg p-8 text-white text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-white/20 rounded-full mb-4">
                        <i class="fas fa-key text-2xl"></i>
                    </div>
                    <h1 class="text-2xl font-bold">Forgot Password</h1>
                    <p class="text-white/80 mt-2">Reset your account password</p>
                </div>

                <!-- Form -->
                <div class="p-8">
                    <?php if ($error): ?>
                    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6" role="alert">
                        <div class="flex items-center">
                            <i class="fas fa-exclamation-circle mr-3"></i>
                            <span><?php echo htmlspecialchars($error); ?></span>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle mr-3"></i>
                            <span><?php echo htmlspecialchars($success); ?></span>
                        </div>
                    </div>
                    <?php endif; ?>

                    <form method="POST" action="" class="space-y-6">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                        <div>
                            <label for="email" class="block text-sm font-semibold text-gray-900 mb-2">
                                <i class="fas fa-envelope mr-2 text-gray-500"></i>Email Address
                            </label>
                            <input
                                type="email"
                                id="email"
                                name="email"
                                required
                                placeholder="Enter your registered email"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition"
                                value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                            <p class="mt-1 text-sm text-gray-500">We'll send password reset instructions to this email</p>
                        </div>

                        <button
                            type="submit"
                            class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-lg transition duration-200 shadow-lg hover:shadow-xl">
                            <i class="fas fa-paper-plane mr-2"></i> Send Reset Instructions
                        </button>
                    </form>

                    <div class="mt-8 pt-6 border-t border-gray-200 text-center">
                        <p class="text-gray-600">
                            Remember your password?
                            <a href="index.php" class="text-blue-600 font-bold hover:underline ml-1">
                                <i class="fas fa-sign-in-alt mr-1"></i>Back to Login
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>