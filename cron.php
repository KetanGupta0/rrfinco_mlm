<?php
/**
 * BachatPay Cron Trigger Page
 * Public trigger for scheduled tasks like daily cashback and badge assignment.
 * NOTE: This page deliberately does not require user login so it can be used as a simple cron endpoint.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$messages = [];
$action = $_GET['action'] ?? '';

if ($action === 'daily_cashback') {
    if (isDailyCashbackProcessedToday()) {
        $messages[] = '⚠️ Daily cashback already processed today. Skipping to prevent duplicates.';
    } else {
        $success = processDailyCashback();
        $messages[] = $success ? 'Daily cashback process completed successfully.' : 'Daily cashback process failed. Check logs.';
    }
}

if ($action === 'assign_badges') {
    $awarded = assignBadgesForAllUsers();
    $count = count(array_filter($awarded));
    $messages[] = 'Badge assignment completed. Users awarded badges: ' . $count;
}

if ($action === 'bonus_1') {
    $month = getCurrentMonth();
    $results = [];
    $skipped = 0;
    
    // Get all users
    $stmt = $pdo->query('SELECT user_id FROM users WHERE status = "active"');
    $totalUsers = 0;
    while ($user = $stmt->fetch()) {
        $totalUsers++;
        $user_id = $user['user_id'];
        
        // Check if already credited this month
        if (isBonus1AlreadyCredited($user_id, $month)) {
            $skipped++;
            continue; // Skip this user, already credited
        }
        
        $result = creditBonus1($user_id, $month);
        if ($result) {
            $results[] = $user_id;
        }
    }
    $messages[] = 'Bonus 1 processing completed for ' . count($results) . ' users (skipped ' . $skipped . ' already credited).';
}

if ($action === 'bonus_2') {
    $week_start = getCurrentWeekStart();
    $results = [];
    $skipped = 0;
    
    // Get all users
    $stmt = $pdo->query('SELECT user_id FROM users WHERE status = "active"');
    $totalUsers = 0;
    while ($user = $stmt->fetch()) {
        $totalUsers++;
        $user_id = $user['user_id'];
        
        // Check if already credited this week
        if (isBonus2AlreadyCredited($user_id, $week_start)) {
            $skipped++;
            continue; // Skip this user, already credited
        }
        
        $result = creditBonus2($user_id, $week_start);
        if ($result) {
            $results[] = $user_id;
        }
    }
    $messages[] = 'Bonus 2 processing completed for ' . count($results) . ' users for week starting ' . $week_start . ' (skipped ' . $skipped . ' already credited).';
}

if ($action === 'sync_referrals') {
    $stmt = $pdo->query('SELECT user_id, sponsor_id FROM users WHERE sponsor_id IS NOT NULL');
    $created = 0;
    while ($row = $stmt->fetch()) {
        createReferralRecordsForUser($row['user_id'], $row['sponsor_id']);
        $created++;
    }
    $messages[] = 'Referral sync completed for ' . $created . ' users.';
}

if ($action === 'sync_investments') {
    $created = syncPlanInvestmentsToLegacyInvestments();
    $messages[] = 'Legacy investment sync completed. Created ' . $created . ' missing investment records.';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cron Trigger - BachatPay</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="max-w-4xl mx-auto p-6">
        <div class="bg-white rounded-3xl shadow-xl p-8">
            <h1 class="text-4xl font-bold text-gray-900 mb-4">BachatPay Cron Trigger</h1>
            <p class="text-gray-600 mb-6">Use this page to manually trigger scheduled events. No login is required.</p>

            <?php if (!empty($messages)): ?>
                <div class="space-y-3 mb-6">
                    <?php foreach ($messages as $message): ?>
                    <div class="bg-blue-50 border border-blue-200 text-blue-800 px-4 py-3 rounded-lg">
                        <i class="fas fa-info-circle mr-2"></i><?php echo htmlspecialchars($message); ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <a href="cron.php?action=daily_cashback" class="block text-center bg-blue-600 hover:bg-blue-700 text-white rounded-xl py-5 font-semibold transition">
                    <i class="fas fa-sun mr-2"></i>Process Daily Cashback
                </a>
                <a href="cron.php?action=assign_badges" class="block text-center bg-green-600 hover:bg-green-700 text-white rounded-xl py-5 font-semibold transition">
                    <i class="fas fa-award mr-2"></i>Assign Badges
                </a>
                <a href="cron.php?action=bonus_1" class="block text-center bg-yellow-600 hover:bg-yellow-700 text-white rounded-xl py-5 font-semibold transition">
                    <i class="fas fa-percent mr-2"></i>Credit Bonus 1
                </a>
                <a href="cron.php?action=bonus_2" class="block text-center bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl py-5 font-semibold transition">
                    <i class="fas fa-trophy mr-2"></i>Credit Bonus 2
                </a>
                <a href="cron.php?action=sync_referrals" class="block text-center bg-gray-700 hover:bg-gray-800 text-white rounded-xl py-5 font-semibold transition">
                    <i class="fas fa-project-diagram mr-2"></i>Sync Referral Tree
                </a>
                <a href="cron.php?action=sync_investments" class="block text-center bg-gray-500 hover:bg-gray-600 text-white rounded-xl py-5 font-semibold transition">
                    <i class="fas fa-sync-alt mr-2"></i>Sync Plan Investments
                </a>
            </div>

            <div class="mt-8 bg-gray-50 border border-gray-200 rounded-lg p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-3">Usage</h2>
                <p class="text-sm text-gray-600 leading-6">Open this page in your browser or set it as a cron URL. Use the buttons above to trigger each event manually. For automated scheduling, configure your server cron to call <code><?php echo htmlspecialchars(BASE_URL . 'cron.php?action=daily_cashback'); ?></code> daily.</p>
            </div>
        </div>
    </div>
</body>
</html>
