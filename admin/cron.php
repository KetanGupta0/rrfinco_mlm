<?php
/**
 * Admin Cron Job Management
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Require admin access
requireAdmin();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($csrf_token)) {
        $error = 'Invalid CSRF token';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'run_daily_cashback') {
            try {
                // Run daily cashback processing
                $processed = processDailyCashback();

                if ($processed > 0) {
                    $message = "Daily cashback processed successfully for {$processed} users";
                } else {
                    $message = "No users were eligible for daily cashback processing today";
                }

                // Log the manual cron execution
                logError('Admin Manual Cron', "Daily cashback processed for {$processed} users by admin {$_SESSION['username']}");

            } catch (Exception $e) {
                $error = 'Failed to process daily cashback: ' . $e->getMessage();
                logError('Admin Manual Cron Error', $e->getMessage());
            }
        }

        if ($action === 'run_bonus_processing') {
            try {
                // Process bonus 1 (maintenance bonus)
                $bonus1Processed = 0;
                $currentMonth = date('Y-m');

                // Get all users who might qualify for bonus 1
                $stmt = $pdo->query('SELECT user_id FROM users WHERE status = "active"');
                $users = $stmt->fetchAll(PDO::FETCH_COLUMN);

                foreach ($users as $userId) {
                    if (checkBonus1Qualification($userId, $currentMonth)) {
                        creditBonus1($userId, $currentMonth);
                        $bonus1Processed++;
                    }
                }

                // Process bonus 2 (weekly challenge)
                $bonus2Processed = 0;
                $weekStart = date('Y-m-d', strtotime('monday this week'));

                foreach ($users as $userId) {
                    if (checkBonus2Qualification($userId, $weekStart)) {
                        creditBonus2($userId, $weekStart);
                        $bonus2Processed++;
                    }
                }

                $message = "Bonus processing completed - Bonus 1: {$bonus1Processed} users, Bonus 2: {$bonus2Processed} users";

            } catch (Exception $e) {
                $error = 'Failed to process bonuses: ' . $e->getMessage();
                logError('Admin Bonus Processing Error', $e->getMessage());
            }
        }

        if ($action === 'assign_badges') {
            $awarded = assignBadgesForAllUsers();
            $count = count(array_filter($awarded));
            $message = 'Badge assignment completed. Users awarded badges: ' . $count;
        }

        if ($action === 'sync_referrals') {
            $stmt = $pdo->query('SELECT user_id, sponsor_id FROM users WHERE sponsor_id IS NOT NULL');
            $created = 0;
            while ($row = $stmt->fetch()) {
                createReferralRecordsForUser($row['user_id'], $row['sponsor_id']);
                $created++;
            }
            $message = 'Referral sync completed for ' . $created . ' users.';
        }

        if ($action === 'sync_investments') {
            $created = syncPlanInvestmentsToLegacyInvestments();
            $message = 'Legacy investment sync completed. Created ' . $created . ' missing investment records.';
        }

    }
}

// Get cron job status information
try {
    $cronStats = [];

    // Last cashback processing date
    $stmt = $pdo->query('SELECT MAX(cashback_date) as last_processed FROM daily_cashback');
    $cronStats['last_cashback_date'] = $stmt->fetch()['last_processed'] ?? 'Never';

    // Today's cashback count
    $stmt = $pdo->prepare('SELECT COUNT(*) as today_count FROM daily_cashback WHERE cashback_date = CURDATE()');
    $stmt->execute();
    $cronStats['today_cashback_count'] = $stmt->fetch()['today_count'];

    // Pending bonus 1 processing
    $stmt = $pdo->query('SELECT COUNT(*) as pending_bonus1 FROM bonus_1_tracking WHERE is_qualified = 1 AND bonus_credited_date IS NULL');
    $cronStats['pending_bonus1'] = $stmt->fetch()['pending_bonus1'];

    // Pending bonus 2 processing
    $stmt = $pdo->query('SELECT COUNT(*) as pending_bonus2 FROM bonus_2_tracking WHERE is_qualified = 1 AND bonus_credited_date IS NULL');
    $cronStats['pending_bonus2'] = $stmt->fetch()['pending_bonus2'];

} catch (PDOException $e) {
    $cronStats = array_fill_keys(['last_cashback_date', 'today_cashback_count', 'pending_bonus1', 'pending_bonus2'], 0);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cron Job Management - BachatPay Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <!-- Admin Navigation -->
    <?php require_once __DIR__ . '/navbar.php'; ?>

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="mb-6">
            <h1 class="text-3xl font-bold text-gray-900">Cron Job Management</h1>
            <p class="text-gray-600">Manually trigger automated processes and monitor system status</p>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- System Status -->
        <div class="bg-white shadow rounded-lg p-6 mb-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">System Status</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-gray-50 p-4 rounded">
                    <h3 class="font-medium text-gray-900">Last Cashback Processing</h3>
                    <p class="text-2xl font-bold text-blue-600"><?php echo $cronStats['last_cashback_date']; ?></p>
                </div>
                <div class="bg-gray-50 p-4 rounded">
                    <h3 class="font-medium text-gray-900">Today's Cashback Records</h3>
                    <p class="text-2xl font-bold text-green-600"><?php echo number_format($cronStats['today_cashback_count']); ?></p>
                </div>
                <div class="bg-gray-50 p-4 rounded">
                    <h3 class="font-medium text-gray-900">Pending Bonus 1</h3>
                    <p class="text-2xl font-bold text-yellow-600"><?php echo number_format($cronStats['pending_bonus1']); ?></p>
                </div>
                <div class="bg-gray-50 p-4 rounded">
                    <h3 class="font-medium text-gray-900">Pending Bonus 2</h3>
                    <p class="text-2xl font-bold text-purple-600"><?php echo number_format($cronStats['pending_bonus2']); ?></p>
                </div>
            </div>
        </div>

        <!-- Manual Cron Jobs -->
        <div class="bg-white shadow rounded-lg p-6 mb-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Manual Process Triggers</h2>
            <div class="space-y-4">
                <!-- Daily Cashback Processing -->
                <div class="border border-gray-200 rounded p-4">
                    <div class="flex justify-between items-center">
                        <div>
                            <h3 class="font-medium text-gray-900">Daily Cashback Processing</h3>
                            <p class="text-sm text-gray-600">Credit daily earnings to all active investments based on their tier rates</p>
                        </div>
                        <form method="POST" class="inline">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="run_daily_cashback">
                            <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded"
                                    onclick="return confirm('Are you sure you want to run daily cashback processing?')">
                                Run Now
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Bonus Processing -->
                <div class="border border-gray-200 rounded p-4">
                    <div class="flex justify-between items-center">
                        <div>
                            <h3 class="font-medium text-gray-900">Bonus Processing</h3>
                            <p class="text-sm text-gray-600">Process maintenance bonuses (Bonus 1) and weekly challenge bonuses (Bonus 2)</p>
                        </div>
                        <form method="POST" class="inline">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="run_bonus_processing">
                            <button type="submit" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded"
                                    onclick="return confirm('Are you sure you want to process bonuses?')">
                                Process Bonuses
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Badge Assignment -->
                <div class="border border-gray-200 rounded p-4">
                    <div class="flex justify-between items-center">
                        <div>
                            <h3 class="font-medium text-gray-900">Badge Assignment</h3>
                            <p class="text-sm text-gray-600">Assign badges to users based on their achievements</p>
                        </div>
                        <form method="POST" class="inline">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="assign_badges">
                            <button type="submit" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded"
                                    onclick="return confirm('Are you sure you want to assign badges?')">
                                Assign Badges
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Danger Zone -->
                <div class="border border-red-400 rounded p-4 bg-red-50">
                    <div class="flex justify-between items-center">
                        <div>
                            <h3 class="font-medium text-gray-900">Danger Zone</h3>
                            <p class="text-sm text-gray-600">Use with caution - these actions can modify critical data</p>
                        </div>
                    </div>
                    <div class="mt-2">
                        <form method="POST" class="inline">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="sync_referrals">
                            <button type="submit" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded"
                                    onclick="return confirm('This will sync referral records for all users. Are you sure?')">
                                Sync Referrals
                            </button>
                        </form>
                        <form method="POST" class="inline">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                            <input type="hidden" name="action" value="sync_investments">
                            <button type="submit" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded"
                                    onclick="return confirm('This will sync plan investments to legacy investments. Are you sure?')">
                                Sync Plan Investments
                            </button>
                        </form>
                    </div>
                </div>

            </div>
        </div>

        <!-- Cron Job Information -->
        <div class="bg-white shadow rounded-lg p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Automated Processes</h2>
            <div class="space-y-4">
                <div class="border-l-4 border-blue-500 pl-4">
                    <h3 class="font-medium text-gray-900">Daily Cashback (Recommended: Daily at 00:01)</h3>
                    <p class="text-sm text-gray-600">Automatically credits daily earnings to all active investments. Should run once per day.</p>
                    <code class="text-xs bg-gray-100 p-1 rounded mt-1 block">0 1 * * * php /path/to/cron/daily_cashback.php</code>
                </div>

                <div class="border-l-4 border-green-500 pl-4">
                    <h3 class="font-medium text-gray-900">Bonus Processing (Recommended: Weekly on Monday)</h3>
                    <p class="text-sm text-gray-600">Processes maintenance and challenge bonuses. Should run weekly.</p>
                    <code class="text-xs bg-gray-100 p-1 rounded mt-1 block">0 2 * * 1 php /path/to/cron/bonus_processing.php</code>
                </div>

                <div class="border-l-4 border-yellow-500 pl-4">
                    <h3 class="font-medium text-gray-900">Database Cleanup (Recommended: Monthly)</h3>
                    <p class="text-sm text-gray-600">Archives old logs and cleans up temporary data. Should run monthly.</p>
                    <code class="text-xs bg-gray-100 p-1 rounded mt-1 block">0 3 1 * * php /path/to/cron/cleanup.php</code>
                </div>
            </div>
        </div>
    </div>
</body>
</html>