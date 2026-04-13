<?php
/**
 * BachatPay - Badges/Achievements Page
 * Display earned badges and admin-configurable badge system
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if (!isLoggedIn()) redirect('index.php');
$user = getCurrentUser();
if (!$user) { session_destroy(); redirect('index.php'); }

// Assign badges automatically if the user qualifies
$awardedBadges = assignUserBadges($user['user_id']);

// Get all badges
$stmt = $pdo->query('SELECT * FROM badges WHERE is_active = 1 ORDER BY badge_id ASC');
$all_badges = $stmt->fetchAll();

// Get user's earned badges
$stmt = $pdo->prepare('
    SELECT ub.*, b.* 
    FROM user_badges ub 
    JOIN badges b ON ub.badge_id = b.badge_id 
    WHERE ub.user_id = ? 
    ORDER BY ub.earned_at DESC
');
$stmt->execute([$user['user_id']]);
$earned_badges = $stmt->fetchAll();

// Get user's earned badge IDs for comparison
$earned_badge_ids = array_column($earned_badges, 'badge_id');

// Get user stats for badge progress
$stmt = $pdo->prepare('
    SELECT 
        (SELECT COALESCE(SUM(investment_amount), 0) FROM plan_investments WHERE user_id = ?) as total_investment,
        (SELECT COUNT(*) FROM users WHERE sponsor_id = ?) as referral_count
');
$stmt->execute([$user['user_id'], $user['user_id']]);
$user_stats = $stmt->fetch();

// Get total earnings from stored user totals
$stmt = $pdo->prepare('
    SELECT COALESCE(total_cashback_earned, 0) + COALESCE(total_commission_earned, 0) as total_earnings 
    FROM users 
    WHERE user_id = ?
');
$stmt->execute([$user['user_id']]);
$earnings_result = $stmt->fetch();

$badge_icons = [
    'bronze' => 'fas-medal',
    'silver' => 'fas-star',
    'gold' => 'fas-crown',
    'platinum' => 'fas-gem',
    'diamond' => 'fas-ring',
    'elite' => 'fas-trophy',
    'master' => 'fas-fire',
    'veteran' => 'fas-shield-alt'
];

$badge_colors = [
    'bronze' => 'text-amber-600 bg-amber-50',
    'silver' => 'text-gray-600 bg-gray-50',
    'gold' => 'text-yellow-600 bg-yellow-50',
    'platinum' => 'text-blue-600 bg-blue-50',
    'diamond' => 'text-cyan-600 bg-cyan-50',
    'elite' => 'text-red-600 bg-red-50',
    'master' => 'text-orange-600 bg-orange-50',
    'veteran' => 'text-purple-600 bg-purple-50'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Badges & Achievements - BachatPay</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <?php $currentPage = 'badges'; require_once __DIR__ . '/includes/navbar.php'; ?>

    <div class="lg:ml-64 px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-4xl font-bold text-gray-900 mb-2">
                <i class="fas fa-trophy text-yellow-500 mr-3"></i>Badges & Achievements
            </h1>
            <p class="text-gray-600">Earn badges by reaching milestones in your BachatPay journey</p>
        </div>

        <!-- Earned Badges Section -->
        <div class="mb-12">
            <h2 class="text-2xl font-bold text-gray-900 mb-6">
                <i class="fas fa-check-circle text-green-600 mr-2"></i>Your Earned Badges (<?php echo count($earned_badges); ?>)
            </h2>
            <?php if (empty($earned_badges)): ?>
            <div class="bg-white rounded-lg shadow p-12 text-center">
                <i class="fas fa-inbox text-6xl text-gray-300 mb-4"></i>
                <p class="text-gray-600 text-lg">No badges earned yet. Keep working towards your goals!</p>
            </div>
            <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <?php foreach ($earned_badges as $badge): 
                    $key = strtolower(str_replace(' ', '_', $badge['badge_name']));
                    $icon = $badge_icons[$key] ?? 'fas-star';
                    $color = $badge_colors[$key] ?? 'text-blue-600 bg-blue-50';
                ?>
                <div class="bg-white rounded-lg shadow p-6 border-t-4 border-green-500">
                    <div class="text-center">
                        <div class="text-6xl mb-4">
                            <i class="fas <?php echo $icon; ?> <?php echo $color; ?> p-4 rounded-lg text-4xl"></i>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900"><?php echo htmlspecialchars($badge['badge_name']); ?></h3>
                        <p class="text-sm text-gray-600 mt-2"><?php echo htmlspecialchars($badge['description']); ?></p>
                        <p class="text-xs text-gray-500 mt-3">
                            <i class="fas fa-calendar mr-1"></i>Earned: <?php echo date('M d, Y', strtotime($badge['earned_at'])); ?>
                        </p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- All Available Badges -->
        <h2 class="text-2xl font-bold text-gray-900 mb-6">
            <i class="fas fa-star text-yellow-500 mr-2"></i>All Available Badges
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-12">
            <?php foreach ($all_badges as $badge): 
                $key = strtolower(str_replace(' ', '_', $badge['badge_name']));
                $icon = $badge_icons[$key] ?? 'fas-star';
                $color = $badge_colors[$key] ?? 'text-blue-600';
                $earned = in_array($badge['badge_id'], $earned_badge_ids);
            ?>
            <div class="bg-white rounded-lg shadow p-6 <?php echo $earned ? 'border-l-4 border-green-500' : 'opacity-70'; ?>">
                <div class="text-center">
                    <div class="text-4xl mb-4">
                        <i class="fas <?php echo $icon; ?> <?php echo $color; ?>"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900"><?php echo htmlspecialchars($badge['badge_name']); ?></h3>
                    <p class="text-sm text-gray-600 mt-2"><?php echo htmlspecialchars($badge['description']); ?></p>
                    
                    <!-- Badge Criteria -->
                    <div class="mt-4 text-sm bg-gray-50 p-3 rounded-lg">
                        <p class="font-semibold text-gray-700">Requirement:</p>
                        <p class="text-gray-600">
                            <?php 
                                $criteria = $badge['criteria_type'];
                                $value = number_format($badge['criteria_value']);
                                
                                if ($criteria === 'total_investment') {
                                    echo "Total Investment: ₹" . $value;
                                } elseif ($criteria === 'total_earnings') {
                                    echo "Total Earnings: ₹" . $value;
                                } elseif ($criteria === 'team_size') {
                                    echo "Team Members: " . $value;
                                } elseif ($criteria === 'tenure_days') {
                                    echo "Account Age: " . $value . " days";
                                }
                            ?>
                        </p>
                    </div>

                    <!-- Status -->
                    <div class="mt-4">
                        <?php if ($earned): ?>
                        <span class="inline-block bg-green-100 text-green-800 px-3 py-1 rounded-full text-xs font-semibold">
                            <i class="fas fa-check-circle mr-1"></i>Earned
                        </span>
                        <?php else: ?>
                        <span class="inline-block bg-gray-100 text-gray-800 px-3 py-1 rounded-full text-xs font-semibold">
                            <i class="fas fa-lock mr-1"></i>Locked
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Progress Tracker -->
        <div class="bg-white rounded-lg shadow p-8">
            <h3 class="text-2xl font-bold text-gray-900 mb-6">
                <i class="fas fa-chart-line text-blue-600 mr-2"></i>Your Progress
            </h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div>
                    <h4 class="font-semibold text-gray-700 mb-3">Total Investment</h4>
                    <div class="bg-gray-100 rounded-lg h-8 overflow-hidden">
                        <div class="bg-blue-600 h-full" style="width: <?php echo min(100, ($user_stats['total_investment'] ?? 0) / 1000); ?>%"></div>
                    </div>
                    <p class="text-sm text-gray-600 mt-2">
                        ₹<?php echo number_format($user_stats['total_investment'] ?? 0, 0); ?> / ₹100,000
                    </p>
                </div>

                <div>
                    <h4 class="font-semibold text-gray-700 mb-3">Total Earnings</h4>
                    <div class="bg-gray-100 rounded-lg h-8 overflow-hidden">
                        <div class="bg-green-600 h-full" style="width: <?php echo min(100, ($earnings_result['total_earnings'] ?? 0) / 1000); ?>%"></div>
                    </div>
                    <p class="text-sm text-gray-600 mt-2">
                        ₹<?php echo number_format($earnings_result['total_earnings'] ?? 0, 0); ?> / ₹100,000
                    </p>
                </div>

                <div>
                    <h4 class="font-semibold text-gray-700 mb-3">Team Members</h4>
                    <div class="bg-gray-100 rounded-lg h-8 overflow-hidden">
                        <div class="bg-purple-600 h-full" style="width: <?php echo min(100, ($user_stats['referral_count'] ?? 0) * 10); ?>%"></div>
                    </div>
                    <p class="text-sm text-gray-600 mt-2">
                        <?php echo $user_stats['referral_count'] ?? 0; ?> / 100 Members
                    </p>
                </div>

                <div>
                    <h4 class="font-semibold text-gray-700 mb-3">Account Age</h4>
                    <div class="bg-gray-100 rounded-lg h-8 overflow-hidden">
                        <div class="bg-orange-600 h-full" style="width: <?php echo min(100, ((time() - strtotime($user['registration_date'])) / (365 * 24 * 60 * 60)) * 10); ?>%"></div>
                    </div>
                    <p class="text-sm text-gray-600 mt-2">
                        <?php 
                        if (!empty($user['registration_date'])) {
                            $days = (time() - strtotime($user['registration_date'])) / (24 * 60 * 60);
                            echo floor($days);
                        } else {
                            echo "0"; // Default value if date is missing
                        }
                        ?> days / 365 days
                    </p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
