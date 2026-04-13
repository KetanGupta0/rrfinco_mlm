<?php
/**
 * BachatPay - Team Area / Downline Management
 * View referrals, level-wise breakdown, and downline tree (up to 30 levels)
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if (!isLoggedIn()) redirect('index.php');
$user = getCurrentUser();
if (!$user) { session_destroy(); redirect('index.php'); }

$currentUserName = trim((($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')));
if (empty($currentUserName)) {
    $currentUserName = $user['username'];
}

$inviteLink = getReferralInviteLink($user['username']);
$view = $_GET['view'] ?? 'referrals';

// Get direct referrals
$stmt = $pdo->prepare( "
    SELECT user_id, username, email, registration_date, wallet_balance,
        COALESCE(NULLIF(TRIM(CONCAT(IFNULL(first_name, ''), ' ', IFNULL(last_name, ''))), ''), username) as full_name,
        (SELECT SUM(investment_amount) FROM plan_investments WHERE user_id = users.user_id) as total_investment
    FROM users
    WHERE sponsor_id = ?
    ORDER BY registration_date DESC
");
$stmt->execute([$user['user_id']]);
$direct_referrals = $stmt->fetchAll();

// Get level-wise breakdown
$stmt = $pdo->prepare("
    WITH RECURSIVE team_tree AS (
        SELECT user_id, sponsor_id, 1 as level, username, email,
            COALESCE(NULLIF(TRIM(CONCAT(IFNULL(first_name, ''), ' ', IFNULL(last_name, ''))), ''), username) as full_name,
            (SELECT SUM(investment_amount) FROM plan_investments WHERE user_id = users.user_id) as investment
        FROM users
        WHERE sponsor_id = ?

        UNION ALL

        SELECT u.user_id, u.sponsor_id, tt.level + 1, u.username, u.email,
            COALESCE(NULLIF(TRIM(CONCAT(IFNULL(u.first_name, ''), ' ', IFNULL(u.last_name, ''))), ''), u.username) as full_name,
            (SELECT SUM(investment_amount) FROM plan_investments WHERE user_id = u.user_id)
        FROM users u
        JOIN team_tree tt ON u.sponsor_id = tt.user_id
        WHERE tt.level < 30
    )
    SELECT level, COUNT(*) as member_count, SUM(investment) as total_investment
    FROM team_tree
    GROUP BY level
    ORDER BY level ASC
");
$stmt->execute([$user['user_id']]);
$level_breakdown = $stmt->fetchAll();

// Get total team counts
$total_direct = count($direct_referrals);
$stmt = $pdo->prepare('
    WITH RECURSIVE team_tree AS (
        SELECT user_id, sponsor_id, 1 as level
        FROM users 
        WHERE sponsor_id = ?

        UNION ALL

        SELECT u.user_id, u.sponsor_id, tt.level + 1
        FROM users u
        JOIN team_tree tt ON u.sponsor_id = tt.user_id 
        WHERE tt.level < 30
    )
    SELECT COUNT(*) as total, SUM(CASE WHEN level = 1 THEN 1 ELSE 0 END) as direct
    FROM team_tree
');
$stmt->execute([$user['user_id']]);
$team_counts = $stmt->fetch();

// Recursive function to build downline tree
function buildDownlineTree($pdo, $sponsor_id, $level = 1, $maxLevel = 30) {
    if ($level > $maxLevel) return '';
    
    $stmt = $pdo->prepare("
        SELECT user_id, username,
            COALESCE(NULLIF(TRIM(CONCAT(IFNULL(first_name, ''), ' ', IFNULL(last_name, ''))), ''), username) as full_name,
            (SELECT SUM(investment_amount) FROM plan_investments WHERE user_id = users.user_id) as investment
        FROM users 
        WHERE sponsor_id = ?
        ORDER BY registration_date DESC
        LIMIT 100
    ");
    $stmt->execute([$sponsor_id]);
    $members = $stmt->fetchAll();
    
    if (empty($members)) return '';
    
    $html = '<ul style="margin-left: ' . ($level * 20) . 'px; margin-top: 10px;">';
    foreach ($members as $member) {
        $html .= '<li class="py-2 border-l pl-4" style="border-left: 2px solid #ddd;">';
        $html .= '<div class="flex justify-between items-center">';
        $html .= '<span class="font-semibold text-gray-900">' . htmlspecialchars($member['full_name']) . '</span>';
        $html .= '<span class="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded">Level ' . $level . '</span>';
        $html .= '</div>';
        $html .= '<p class="text-xs text-gray-600">@' . htmlspecialchars($member['username']) . '</p>';
        $html .= '<p class="text-xs text-green-600 font-semibold">Invested: ₹' . number_format($member['investment'] ?? 0) . '</p>';
        $html .= buildDownlineTree($pdo, $member['user_id'], $level + 1, $maxLevel);
        $html .= '</li>';
    }
    $html .= '</ul>';
    
    return $html;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Area - BachatPay</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <?php $currentPage = 'team'; require_once __DIR__ . '/includes/navbar.php'; ?>

    <div class="lg:ml-64 px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-4xl font-bold text-gray-900 mb-2">
                <i class="fas fa-sitemap text-blue-600 mr-3"></i>Team Area
            </h1>
            <p class="text-gray-600">Manage and view your MLM network</p>
        </div>

        <!-- Invite Link -->
        <div class="mb-8 bg-white rounded-lg shadow p-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <p class="text-sm text-gray-600">Your referral invite link</p>
                <p class="font-semibold text-blue-700 break-all"><?php echo htmlspecialchars($inviteLink); ?></p>
            </div>
            <button onclick="navigator.clipboard.writeText('<?php echo htmlspecialchars($inviteLink); ?>')" class="inline-flex items-center justify-center px-5 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                <i class="fas fa-copy mr-2"></i>Copy Invite Link
            </button>
        </div>

        <!-- Team Stats -->
        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">Total Team</p>
                        <p class="text-3xl font-bold text-blue-600 mt-2"><?php echo $team_counts['total'] ?? 0; ?></p>
                    </div>
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-users text-blue-600 text-2xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">Direct Referrals</p>
                        <p class="text-3xl font-bold text-green-600 mt-2"><?php echo $total_direct; ?></p>
                    </div>
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-user-plus text-green-600 text-2xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">Indirect Members</p>
                        <p class="text-3xl font-bold text-purple-600 mt-2"><?php echo ($team_counts['total'] ?? 0) - $total_direct; ?></p>
                    </div>
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-project-diagram text-purple-600 text-2xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-600 text-sm">Commission Level</p>
                        <p class="text-3xl font-bold text-orange-600 mt-2"><?php echo count($level_breakdown); ?></p>
                    </div>
                    <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-chart-bar text-orange-600 text-2xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab Navigation -->
        <div class="bg-white rounded-lg shadow mb-8">
            <div class="flex border-b">
                <a href="?view=referrals" class="flex-1 px-6 py-4 text-center font-semibold border-b-2 <?php echo $view === 'referrals' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-600 hover:text-gray-900'; ?>">
                    <i class="fas fa-user-check mr-2"></i>Direct Referrals
                </a>
                <a href="?view=levels" class="flex-1 px-6 py-4 text-center font-semibold border-b-2 <?php echo $view === 'levels' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-600 hover:text-gray-900'; ?>">
                    <i class="fas fa-layer-group mr-2"></i>Level View
                </a>
                <a href="?view=downline" class="flex-1 px-6 py-4 text-center font-semibold border-b-2 <?php echo $view === 'downline' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-600 hover:text-gray-900'; ?>">
                    <i class="fas fa-sitemap mr-2"></i>Downline Tree
                </a>
            </div>
        </div>

        <!-- Content -->
        <?php if ($view === 'referrals'): ?>
            <!-- Direct Referrals -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Name</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Username</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Email</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Joined</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Investment</th>
                                <th class="px-6 py-3 text-left text-sm font-semibold text-gray-700">Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($direct_referrals)): ?>
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                                    <i class="fas fa-inbox text-4xl mb-2 block opacity-50"></i>
                                    No direct referrals yet
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($direct_referrals as $ref): ?>
                                <tr class="border-b hover:bg-gray-50 transition">
                                    <td class="px-6 py-3 font-semibold text-gray-900"><?php echo htmlspecialchars($ref['full_name']); ?></td>
                                    <td class="px-6 py-3 text-sm text-gray-600">@<?php echo htmlspecialchars($ref['username']); ?></td>
                                    <td class="px-6 py-3 text-sm text-gray-600"><?php echo htmlspecialchars($ref['email']); ?></td>
                                    <td class="px-6 py-3 text-sm text-gray-600"><?php echo date('M d, Y', strtotime($ref['registration_date'])); ?></td>
                                    <td class="px-6 py-3 font-semibold text-blue-600"><?php echo formatCurrency($ref['total_investment'] ?? 0); ?></td>
                                    <td class="px-6 py-3 font-semibold text-green-600"><?php echo formatCurrency($ref['wallet_balance']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php elseif ($view === 'levels'): ?>
            <!-- Level-wise Breakdown -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <?php foreach ($level_breakdown as $level): ?>
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">
                        <i class="fas fa-level-up-alt text-blue-600 mr-2"></i>Level <?php echo $level['level']; ?>
                    </h3>
                    <div class="space-y-3">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Members:</span>
                            <span class="text-2xl font-bold text-blue-600"><?php echo $level['member_count']; ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-600">Total Investment:</span>
                            <span class="text-xl font-bold text-green-600"><?php echo formatCurrency($level['total_investment'] ?? 0); ?></span>
                        </div>
                        <div class="pt-3 border-t mt-3">
                            <p class="text-xs text-gray-500">Avg Investment:</p>
                            <p class="font-semibold text-lg text-gray-900">
                                <?php echo formatCurrency(($level['total_investment'] ?? 0) / max(1, $level['member_count'])); ?>
                            </p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

        <?php else: ?>
            <!-- Downline Tree View -->
            <div class="bg-white rounded-lg shadow p-8">
                <div class="text-lg font-bold text-gray-900 mb-6">
                    <i class="fas fa-sitemap text-purple-600 mr-2"></i>Your Downline Network (Up to 30 Levels)
                </div>
                <div class="text-gray-600 mb-6">
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center font-bold mr-3">
                            <i class="fas fa-user text-sm"></i>
                        </div>
                        <span class="font-semibold"><?php echo htmlspecialchars($currentUserName); ?></span>
                        <span class="text-gray-500 ml-2">(You)</span>
                    </div>
                </div>

                <?php
                $downlineTree = buildDownlineTree($pdo, $user['user_id']);
                if (empty($downlineTree)): ?>
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-inbox text-4xl mb-2 block opacity-50"></i>
                    No downline members yet
                </div>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <?php echo $downlineTree; ?>
                </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
