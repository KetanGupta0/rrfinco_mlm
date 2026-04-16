<?php
/**
 * =========================================
 * BachatPay MLM - Master Cron Controller
 * =========================================
 * Handles:
 * - Daily Cashback
 * - Bonus 1 (Monthly)
 * - Bonus 2 (Weekly)
 * - Joining Bonus (New)
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

// =========================================
// 🔐 OPTIONAL SECURITY (for URL-based cron)
// =========================================
// Uncomment if using URL like: cron.php?key=secret123

/*
if (!isset($_GET['key']) || $_GET['key'] !== 'mySecret123') {
    die('Unauthorized');
}
*/

// =========================================
// 🕒 START LOGGING
// =========================================

$logFile = __DIR__ . '/logs/cron.log';

function cronLog($message) {
    global $logFile;
    $time = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$time] $message\n", FILE_APPEND);
}

cronLog("===== CRON STARTED =====");

try {

    // =========================================
    // 1. DAILY CASHBACK (Run once per day)
    // =========================================
    if (!isDailyCashbackProcessedToday()) {
        cronLog("Processing Daily Cashback...");
        processDailyCashback();
        cronLog("Daily Cashback Done");
    } else {
        cronLog("Daily Cashback Already Processed");
    }

    // =========================================
    // 2. BONUS 1 (Monthly Maintenance Bonus)
    // =========================================
    cronLog("Processing Bonus 1...");
    processBonus1ForAllUsers();
    cronLog("Bonus 1 Done");

    // =========================================
    // 3. BONUS 2 (Weekly Business Bonus)
    // =========================================
    cronLog("Processing Bonus 2...");
    processBonus2ForAllUsers();
    cronLog("Bonus 2 Done");

    // =========================================
    // 4. JOINING BONUS (NEW FEATURE)
    // =========================================
    cronLog("Processing Joining Bonus...");
    processJoiningBonus();
    cronLog("Joining Bonus Done");

    // =========================================
    // 5. OPTIONAL: Badge Assignment
    // =========================================
    cronLog("Assigning Badges...");
    assignBadgesForAllUsers();
    cronLog("Badges Assigned");

    cronLog("===== CRON COMPLETED SUCCESSFULLY =====");

} catch (Exception $e) {
    cronLog("CRON FAILED: " . $e->getMessage());
}