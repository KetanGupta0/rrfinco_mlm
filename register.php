<?php
/**
 * BachatPay Registration Page
 * User registration with sponsor/upline ID support
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
    // CSRF validation
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        
        $email = trim($_POST['email'] ?? '');
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';
        $phone = trim($_POST['phone'] ?? '');
        $sponsor_username = trim($_POST['sponsor_username'] ?? '');
        
        // Validation
        if (!preg_match('/^[a-z0-9]+$/', $username)) {
            $error = "Invalid username. Only lowercase letters and numbers are allowed, with no spaces.";
        } elseif (empty($username) || empty($email) || empty($password)) {
            $error = 'Please fill in all required fields';
        } elseif (strlen($password) < MIN_PASSWORD_LENGTH) {
            $error = 'Password must be at least ' . MIN_PASSWORD_LENGTH . ' characters long';
        } elseif ($password !== $password_confirm) {
            $error = 'Passwords do not match';
        } else {
            try {
                // Check if email already exists
                $stmt = $pdo->prepare('SELECT user_id FROM users WHERE email = ?');
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $error = 'Email already registered. Please use a different email or login.';
                } else {
                    // Check if username already exists
                    $stmt = $pdo->prepare('SELECT user_id FROM users WHERE username = ?');
                    $stmt->execute([$username]);
                    if ($stmt->fetch()) {
                        $error = 'Username already taken. Please choose another.';
                    } else {
                        // Get sponsor ID from username
                        $sponsor_id = null;
                        if (!empty($sponsor_username)) {
                            $stmt = $pdo->prepare('SELECT user_id FROM users WHERE username = ?');
                            $stmt->execute([$sponsor_username]);
                            $sponsor = $stmt->fetch();
                            if (!$sponsor) {
                                $error = 'Sponsor user not found. Please check the username.';
                            } else {
                                $sponsor_id = $sponsor['user_id'];
                            }
                        }
                        
                        if (empty($error)) {
                            // Create new user
                            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                            
                            $stmt = $pdo->prepare('
                                INSERT INTO users (username, email, password, first_name, last_name, phone, sponsor_id, status)
                                VALUES (?, ?, ?, ?, ?, ?, ?, "active")
                            ');
                            $stmt->execute([$username, $email, $hashedPassword, $first_name, $last_name, $phone, $sponsor_id]);
                            
                            $newUserId = $pdo->lastInsertId();
                            createReferralRecordsForUser($newUserId, $sponsor_id);
                            
                            $success = 'Registration successful! Please <a href="index.php" class="font-bold underline">login here</a> to continue.';
                        }
                    }
                }
            } catch (PDOException $e) {
                logError('Registration error', $e->getMessage());
                $error = 'Registration failed. Please try again later.';
            }
        }
    }
}

// Generate CSRF token
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - BachatPay MLM Platform</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center p-4 py-8">
        <div class="w-full max-w-4xl">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-0 bg-white rounded-2xl shadow-2xl overflow-hidden">
                <!-- Left Side - Features -->
                <div class="gradient-bg p-8 md:p-12 text-white flex flex-col justify-between hidden md:flex">
                    <div>
                        <div class="inline-flex items-center space-x-3 mb-12">
                            <div class="w-12 h-12 bg-white/20 rounded-lg flex items-center justify-center text-2xl font-bold">
                                <i class="fas fa-rocket"></i>
                            </div>
                            <h2 class="text-2xl font-bold tracking-tight">BachatPay</h2>
                        </div>
                        
                        <h3 class="text-4xl font-bold mb-6 leading-tight">Start Your MLM Journey</h3>
                        <p class="text-white/80 text-lg mb-8">Build your wealth through daily cashback and commission earnings.</p>
                        
                        <div class="space-y-4">
                            <div class="flex items-start space-x-4">
                                <div class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-star text-white"></i>
                                </div>
                                <div>
                                    <h4 class="font-bold mb-1">4-Tier Investment System</h4>
                                    <p class="text-white/70 text-sm">₹1k-25k (0.10%) up to ₹500k+ (0.16%)</p>
                                </div>
                            </div>
                            <div class="flex items-start space-x-4">
                                <div class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-network-wired text-white"></i>
                                </div>
                                <div>
                                    <h4 class="font-bold mb-1">Network Growth</h4>
                                    <p class="text-white/70 text-sm">Build your downline and earn commissions</p>
                                </div>
                            </div>
                            <div class="flex items-start space-x-4">
                                <div class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-heart text-white"></i>
                                </div>
                                <div>
                                    <h4 class="font-bold mb-1">Multiple Bonuses</h4>
                                    <p class="text-white/70 text-sm">Maintenance & Weekly Challenge bonuses</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-white/70 text-sm">
                        <p>© 2024 BachatPay. All rights reserved.</p>
                    </div>
                </div>

                <!-- Right Side - Registration Form -->
                <div class="p-8 md:p-12">
                    <div class="max-w-sm mx-auto">
                        <h1 class="text-3xl font-bold text-gray-900 mb-2">Create Account</h1>
                        <p class="text-gray-600 mb-8">Join BachatPay MLM platform today</p>

                        <?php if ($error): ?>
                        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
                            <div class="flex items-start">
                                <i class="fas fa-exclamation-circle mr-3 mt-1"></i>
                                <span><?php echo $error; ?></span>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6">
                            <div class="flex items-start">
                                <i class="fas fa-check-circle mr-3 mt-1"></i>
                                <span><?php echo $success; ?></span>
                            </div>
                        </div>
                        <?php endif; ?>

                        <form method="POST" action="" class="space-y-4">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                            <div>
                                <label for="username" class="block text-sm font-semibold text-gray-900 mb-2">Username *</label>
                                <input 
                                    type="text" 
                                    id="username" 
                                    name="username" 
                                    required
                                    placeholder="Choose a unique username"
                                    value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="first_name" class="block text-sm font-semibold text-gray-900 mb-2">First Name</label>
                                    <input 
                                        type="text" 
                                        id="first_name" 
                                        name="first_name" 
                                        placeholder="First Name"
                                        value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
                                </div>
                                <div>
                                    <label for="last_name" class="block text-sm font-semibold text-gray-900 mb-2">Last Name</label>
                                    <input 
                                        type="text" 
                                        id="last_name" 
                                        name="last_name" 
                                        placeholder="Last Name"
                                        value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
                                </div>
                            </div>

                            <div>
                                <label for="email" class="block text-sm font-semibold text-gray-900 mb-2">Email Address *</label>
                                <input 
                                    type="email" 
                                    id="email" 
                                    name="email" 
                                    required
                                    placeholder="you@example.com"
                                    value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
                            </div>

                            <div>
                                <label for="phone" class="block text-sm font-semibold text-gray-900 mb-2">Phone Number</label>
                                <input 
                                    type="tel" 
                                    id="phone" 
                                    name="phone" 
                                    placeholder="+91 XXXXX XXXXX"
                                    value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
                            </div>

                            <div>
                                <label for="sponsor_username" class="block text-sm font-semibold text-gray-900 mb-2">Sponsor Username (Optional)</label>
                                <input 
                                    type="text" 
                                    id="sponsor_username" 
                                    name="sponsor_username" 
                                    placeholder="Your sponsor's username"
                                    value="<?php echo htmlspecialchars($_POST['sponsor_username'] ?? $_GET['sponsor_username'] ?? ''); ?>"
                                    class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
                                <p class="text-xs text-gray-600 mt-1">Leave empty if you're a root user</p>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label for="password" class="block text-sm font-semibold text-gray-900 mb-2">Password *</label>
                                    <input 
                                        type="password" 
                                        id="password" 
                                        name="password" 
                                        required
                                        placeholder="••••••••"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
                                </div>
                                <div>
                                    <label for="password_confirm" class="block text-sm font-semibold text-gray-900 mb-2">Confirm *</label>
                                    <input 
                                        type="password" 
                                        id="password_confirm" 
                                        name="password_confirm" 
                                        required
                                        placeholder="••••••••"
                                        class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none transition">
                                </div>
                            </div>

                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 text-sm text-gray-700">
                                <p class="text-xs font-semibold text-blue-900 mb-1">Password Requirements:</p>
                                <ul class="text-xs text-blue-800 space-y-1 list-disc list-inside">
                                    <li>At least <?php echo MIN_PASSWORD_LENGTH; ?> characters long</li>
                                    <li>Avoid common passwords</li>
                                </ul>
                            </div>

                            <button 
                                type="submit" 
                                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-lg transition duration-200 shadow-lg hover:shadow-xl">
                                <i class="fas fa-user-plus mr-2"></i> Create Account
                            </button>
                        </form>

                        <div class="mt-8 pt-8 border-t border-gray-200">
                            <p class="text-gray-600 text-center">
                                Already have an account?<br>
                                <a href="index.php" class="text-blue-600 font-bold hover:underline mt-2 inline-block">
                                    <i class="fas fa-sign-in-alt mr-1"></i>Sign In Here
                                </a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
