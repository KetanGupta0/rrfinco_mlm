<?php
/**
 * BachatPay Reset Password Page
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

// Check if already logged in
if (isLoggedIn()) {
    redirect('dashboard.php');
}

$error = '';
$success = '';
$token = $_GET['token'] ?? '';
$user = null;

// Validate token on page load
if (!empty($token)) {
    $user = validateResetToken($token);
    if (!$user) {
        $error = 'Invalid or expired reset link. Please request a new password reset.';
    }
} else {
    $error = 'Invalid reset link. Please request a new password reset.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';

    // Verify CSRF token
    if (!validateCSRFToken($csrf_token)) {
        $error = 'Invalid request. Please try again.';
    } elseif (empty($password)) {
        $error = 'Please enter a new password.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        try {
            // Hash new password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Update password and clear reset token
            $stmt = $pdo->prepare('UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE user_id = ?');
            $stmt->execute([$hashed_password, $user['user_id']]);

            $success = 'Password updated successfully! You can now log in with your new password.';
            logError('Password reset completed', 'User: ' . $user['username'] . ' (' . $user['email'] . ')');

            // Clear the user variable to hide the form
            $user = null;

        } catch (PDOException $e) {
            $error = 'Failed to update password. Please try again.';
            logError('Password reset failed', $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - BachatPay MLM Platform</title>
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
                        <i class="fas fa-lock text-2xl"></i>
                    </div>
                    <h1 class="text-2xl font-bold">Reset Password</h1>
                    <p class="text-white/80 mt-2">Create a new password for your account</p>
                </div>

                <!-- Content -->
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

                    <?php if ($user): ?>
                    <!-- Password Reset Form -->
                    <form method="POST" action="" class="space-y-6">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                        <div>
                            <label for="password" class="block text-sm font-semibold text-gray-900 mb-2">
                                <i class="fas fa-key mr-2 text-gray-500"></i>New Password
                            </label>
                            <input
                                type="password"
                                id="password"
                                name="password"
                                required
                                minlength="8"
                                placeholder="Enter new password"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
                            <p class="mt-1 text-sm text-gray-500">Must be at least 8 characters long</p>
                        </div>

                        <div>
                            <label for="confirm_password" class="block text-sm font-semibold text-gray-900 mb-2">
                                <i class="fas fa-key mr-2 text-gray-500"></i>Confirm Password
                            </label>
                            <input
                                type="password"
                                id="confirm_password"
                                name="confirm_password"
                                required
                                minlength="8"
                                placeholder="Confirm new password"
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
                        </div>

                        <button
                            type="submit"
                            class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-lg transition duration-200 shadow-lg hover:shadow-xl">
                            <i class="fas fa-save mr-2"></i> Update Password
                        </button>
                    </form>
                    <?php else: ?>
                    <!-- Success or Error State -->
                    <div class="text-center">
                        <p class="text-gray-600 mb-6">
                            <?php echo $success ? 'Your password has been successfully updated.' : 'The reset link is invalid or has expired.'; ?>
                        </p>
                        <a href="index.php" class="inline-flex items-center bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg transition duration-200 shadow-lg hover:shadow-xl">
                            <i class="fas fa-sign-in-alt mr-2"></i> Go to Login
                        </a>
                    </div>
                    <?php endif; ?>

                    <div class="mt-8 pt-6 border-t border-gray-200 text-center">
                        <p class="text-gray-600">
                            Need help?
                            <a href="forgot-password.php" class="text-blue-600 font-bold hover:underline ml-1">
                                <i class="fas fa-question-circle mr-1"></i>Request New Reset Link
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($user): ?>
    <script>
        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            const button = document.querySelector('button[type="submit"]');

            if (password !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
                button.disabled = true;
            } else {
                this.setCustomValidity('');
                button.disabled = false;
            }
        });

        document.getElementById('password').addEventListener('input', function() {
            const confirmPassword = document.getElementById('confirm_password');
            if (confirmPassword.value) {
                const event = new Event('input', { bubbles: true });
                confirmPassword.dispatchEvent(event);
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>