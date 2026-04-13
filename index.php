<?php
/**
 * BachatPay Login Page
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

// Check if already logged in
if (isLoggedIn()) {
    redirect('dashboard.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password';
    } else {
        try {
            $stmt = $pdo->prepare('SELECT user_id, username, email, password, role FROM users WHERE email = ? AND status = "active"');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                
                // Update last login
                $stmt = $pdo->prepare('UPDATE users SET last_login = NOW() WHERE user_id = ?');
                $stmt->execute([$user['user_id']]);
                
                // Redirect based on role
                if ($user['role'] === 'admin') {
                    redirect('admin/dashboard.php');
                } else {
                    redirect('dashboard.php');
                }
            } else {
                $error = 'Invalid email or password';
            }
        } catch (PDOException $e) {
            $error = 'Login error. Please try again later.';
            logError('Login error', $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - BachatPay MLM Platform</title>
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
        <div class="w-full max-w-4xl">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-0 bg-white rounded-2xl shadow-2xl overflow-hidden">
                <!-- Left Side - Branding -->
                <div class="gradient-bg p-8 md:p-12 text-white flex flex-col justify-between hidden md:flex">
                    <div>
                        <div class="inline-flex items-center space-x-3 mb-12">
                            <div class="w-12 h-12 bg-white/20 rounded-lg flex items-center justify-center text-2xl font-bold">
                                <!-- <i class="fas fa-coins"></i> -->
                                <img src="<?= BASE_URL.'/assets/images/logos/logo.png' ?>" alt="Bachat Pay Logo" class="w-12 h-12">
                            </div>
                            <h2 class="text-2xl font-bold tracking-tight">BachatPay</h2>
                        </div>
                        
                        <h3 class="text-4xl font-bold mb-6 leading-tight">Earn Daily Cashback & Commissions</h3>
                        <p class="text-white/80 text-lg mb-8">Join our MLM platform and start earning through investment returns and network commissions.</p>
                        
                        <div class="space-y-4">
                            <div class="flex items-start space-x-4">
                                <div class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-check text-white"></i>
                                </div>
                                <div>
                                    <h4 class="font-bold mb-1">0.10% to 0.16% Daily Cashback</h4>
                                    <p class="text-white/70 text-sm">Tiered returns based on your investment</p>
                                </div>
                            </div>
                            <div class="flex items-start space-x-4">
                                <div class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-check text-white"></i>
                                </div>
                                <div>
                                    <h4 class="font-bold mb-1">30-Level Commission Structure</h4>
                                    <p class="text-white/70 text-sm">Earn from your entire downline network</p>
                                </div>
                            </div>
                            <div class="flex items-start space-x-4">
                                <div class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-check text-white"></i>
                                </div>
                                <div>
                                    <h4 class="font-bold mb-1">Bonus 1 & Bonus 2</h4>
                                    <p class="text-white/70 text-sm">Earn up to 40% extra on your returns</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-white/70 text-sm">
                        <p>© 2024 BachatPay. All rights reserved.</p>
                    </div>
                </div>

                <!-- Right Side - Login Form -->
                <div class="p-8 md:p-12">
                    <div class="max-w-sm mx-auto">
                        <h1 class="text-3xl font-bold text-gray-900 mb-2">Welcome Back</h1>
                        <p class="text-gray-600 mb-8">Sign in to access your dashboard</p>

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

                        <form method="POST" action="" class="space-y-5">
                            <div>
                                <label for="email" class="block text-sm font-semibold text-gray-900 mb-2">Email Address</label>
                                <input 
                                    type="email" 
                                    id="email" 
                                    name="email" 
                                    required
                                    placeholder="you@example.com"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
                            </div>

                            <div>
                                <label for="password" class="block text-sm font-semibold text-gray-900 mb-2">Password</label>
                                <input 
                                    type="password" 
                                    id="password" 
                                    name="password" 
                                    required
                                    placeholder="••••••••"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
                            </div>
                            <div class="flex justify-end mb-4">
                                <a href="forgot-password.php" class="text-sm text-blue-600 hover:underline">
                                    <i class="fas fa-key mr-1"></i>Forgot Password?
                                </a>
                            </div>
                            <button 
                                type="submit" 
                                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-lg transition duration-200 shadow-lg hover:shadow-xl">
                                <i class="fas fa-sign-in-alt mr-2"></i> Sign In
                            </button>
                        </form>

                        <div class="mt-8 pt-8 border-t border-gray-200">
                            <p class="text-gray-600 text-center">
                                Don't have an account yet?<br>
                                <a href="register.php" class="text-blue-600 font-bold hover:underline mt-2 inline-block">
                                    <i class="fas fa-user-plus mr-1"></i>Create Account
                                </a>
                            </p>
                        </div>

                        <!-- Demo Credentials -->
                        <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg text-sm text-gray-700">
                            <p class="font-semibold mb-2"><i class="fas fa-info-circle mr-2 text-blue-600"></i> Demo Admin Credentials</p>
                            <p class="text-xs mb-1"><strong>Email:</strong> admin@bachatpay.com</p>
                            <p class="text-xs"><strong>Password:</strong> 12345678</p>
                            <br>    
                            <p class="font-semibold mb-2"><i class="fas fa-info-circle mr-2 text-blue-600"></i> Demo User Credentials</p>
                            <p class="text-xs mb-1"><strong>Email:</strong> demo@bachatpay.com</p>
                            <p class="text-xs"><strong>Password:</strong> demo123</p>
                            
                            
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
