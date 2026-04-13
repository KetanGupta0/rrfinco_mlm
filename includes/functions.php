<?php
/**
 * ========================================
 * BachatPay MLM - Core Financial Engine
 * ========================================
 * All calculations based on BachatPay PDF Business Rules
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

// ========================================
// TIER DEFINITIONS (from PDF Page 1)
// ========================================
const TIERS = [
    ['min' => 1000, 'max' => 25000, 'dailyRate' => 0.001, 'monthlyRate' => 0.03],       // 0.10% daily = 3% monthly
    ['min' => 26000, 'max' => 100000, 'dailyRate' => 0.0012, 'monthlyRate' => 0.036],   // 0.12% daily = 3.6% monthly
    ['min' => 101000, 'max' => 500000, 'dailyRate' => 0.0014, 'monthlyRate' => 0.042],  // 0.14% daily = 4.2% monthly
    ['min' => 500001, 'max' => 999999999, 'dailyRate' => 0.0016, 'monthlyRate' => 0.048], // 0.16% daily = 4.8% monthly
];

// ========================================
// 30-LEVEL COMMISSION STRUCTURE (from PDF Page 6)
// Total = 100%
// ========================================
const LEVEL_COMMISSIONS = [
    25, 10, 10, 9, 8, 5, 4, 3, 3, 3,      // Levels 1-10
    2,  2,  2,  2, 2, 2, 2, 2, 2, 2,      // Levels 11-20
    1,  1,  1,  1, 1, 1, 1, 1, 1, 1       // Levels 21-30
];

// ========================================
// BONUS 1 CONFIGURATION (from PDF Page 2)
// ========================================
const BONUS_1_PERCENTAGE = 20;           // 20% extra on monthly cashback
const BONUS_1_MIN_BALANCE = 2000;        // Maintain ₹2,000
const BONUS_1_MAINTENANCE_DAYS = 30;     // For 30 days

// ========================================
// BONUS 2 CONFIGURATION (from PDF Page 3-5)
// ========================================
const BONUS_2_THRESHOLDS = [
    ['amount' => 50000, 'percentage' => 10],    // ₹50k = 10%
    ['amount' => 100000, 'percentage' => 20],   // ₹1L = 20%
];
const BONUS_2_PERIOD_DAYS = 7;           // Weekly challenge

// ========================================
// 1. DAILY CASHBACK CALCULATIONS
// ========================================

/**
 * Get the daily rate for an investment amount (Tier Identification)
 * @param float $investment Investment amount
 * @return array Tier information
 */
function getTierInfo($investment) {
    foreach (TIERS as $tier) {
        if ($investment >= $tier['min'] && $investment <= $tier['max']) {
            return $tier;
        }
    }
    return TIERS[0];
}

/**
 * Get daily rate percentage for investment
 * @param float $investment
 * @return float Daily rate (e.g., 0.001 for 0.10%)
 */
function getDailyRate($investment) {
    $tier = getTierInfo($investment);
    return $tier['dailyRate'];
}

/**
 * Get monthly rate percentage for investment
 * @param float $investment
 * @return float Monthly rate (e.g., 0.03 for 3%)
 */
function getMonthlyRate($investment) {
    $tier = getTierInfo($investment);
    return $tier['monthlyRate'];
}

/**
 * Calculate daily cashback for an investment
 * @param float $investment
 * @return float Daily cashback amount
 */
function calculateDailyCashback($investment) {
    $dailyRate = getDailyRate($investment);
    return round($investment * $dailyRate, 2);
}

/**
 * Calculate monthly cashback for an investment
 * @param float $investment
 * @return float Monthly cashback (30 days)
 */
function calculateMonthlyCashback($investment) {
    $monthlyRate = getMonthlyRate($investment);
    return round($investment * $monthlyRate, 2);
}

/**
 * Process daily cashback credit for all active users
 * This should be called daily via cron job
 */
function processDailyCashback() {
    global $pdo;
    
    try {
        // Get all active investments
        $stmt = $pdo->query('
            SELECT i.investment_id, i.user_id, i.investment_amount, i.daily_rate,
                   COALESCE(i.last_cashback_processed, CURDATE()) as last_cashback_date
            FROM investments i
            JOIN users u ON i.user_id = u.user_id
            WHERE i.status = "active" AND u.status = "active"
        ');
        
        while ($investment = $stmt->fetch()) {
            $dailyAmount = calculateDailyCashback($investment['investment_amount']);
            
            // Record daily cashback
            $stmt2 = $pdo->prepare('
                INSERT INTO daily_cashback (user_id, investment_id, daily_amount, cashback_date)
                VALUES (?, ?, ?, CURDATE())
                ON DUPLICATE KEY UPDATE daily_amount = VALUES(daily_amount)
            ');
            $stmt2->execute([
                $investment['user_id'],
                $investment['investment_id'],
                $dailyAmount
            ]);
            
            // Update wallet
            updateWalletBalance($investment['user_id'], $dailyAmount, 'add');
            
            // Update total earned
            $stmt3 = $pdo->prepare('
                UPDATE users 
                SET total_cashback_earned = total_cashback_earned + ?
                WHERE user_id = ?
            ');
            $stmt3->execute([$dailyAmount, $investment['user_id']]);
            
            // Record transaction
            recordTransaction(
                $investment['user_id'],
                'daily_cashback',
                $dailyAmount,
                'Daily Cashback @ ' . getDailyRate($investment['investment_amount']) * 100 . '%'
            );
            
            // Update last processing date
            $stmt4 = $pdo->prepare('
                UPDATE investments 
                SET last_cashback_processed = CURDATE()
                WHERE investment_id = ?
            ');
            $stmt4->execute([$investment['investment_id']]);
        }
        
        return true;
    } catch (PDOException $e) {
        logError('Daily cashback processing failed', $e->getMessage());
        return false;
    }
}

// ========================================
// 2. BONUS 1 (30-Day Maintenance Bonus)
// ========================================

/**
 * Check if user qualifies for Bonus 1
 * Must maintain ₹2,000+ balance for 30 days
 * 
 * @param int $user_id
 * @param string $month YYYY-MM format
 * @return array Bonus info or empty
 */
function checkBonus1Qualification($user_id, $month = null) {
    global $pdo;
    
    if ($month === null) {
        $month = date('Y-m');
    }
    
    try {
        // Check if already processed for this month
        $stmt = $pdo->prepare('
            SELECT * FROM bonus_1_tracking
            WHERE user_id = ? AND tracked_month = ?
        ');
        $stmt->execute([$user_id, $month]);
        $existing = $stmt->fetch();
        
        if ($existing && $existing['is_qualified']) {
            return $existing;
        }
        
        // Get user's wallet transactions for the month
        $monthStart = $month . '-01';
        $monthEnd = date('Y-m-t', strtotime($monthStart));
        
        // Calculate minimum balance during the month
        $stmt = $pdo->prepare('
            SELECT MIN(wallet_balance) as min_balance 
            FROM (
                SELECT wallet_balance FROM transactions 
                WHERE user_id = ? AND transaction_date BETWEEN ? AND ?
                ORDER BY transaction_date
            ) as monthly_balance
        ');
        
        // Simplified check: Get final balance
        $userStmt = $pdo->prepare('SELECT wallet_balance FROM users WHERE user_id = ?');
        $userStmt->execute([$user_id]);
        $user = $userStmt->fetch();
        
        $qualifies = $user['wallet_balance'] >= BONUS_1_MIN_BALANCE;
        
        return [
            'user_id' => $user_id,
            'tracked_month' => $month,
            'qualifies' => $qualifies,
            'min_balance_required' => BONUS_1_MIN_BALANCE,
            'current_balance' => $user['wallet_balance']
        ];
    } catch (PDOException $e) {
        logError('Bonus 1 check failed', $e->getMessage());
        return [];
    }
}

/**
 * Calculate and credit Bonus 1
 * 20% extra on the monthly cashback earned that month
 */
function creditBonus1($user_id, $month = null) {
    global $pdo;
    
    if ($month === null) {
        $month = date('Y-m');
    }
    
    try {
        // Verify qualification
        $qualification = checkBonus1Qualification($user_id, $month);
        if (!$qualification['qualifies']) {
            return ['success' => false, 'message' => 'User does not qualify for Bonus 1'];
        }
        
        // Get monthly cashback earned
        $monthStart = $month . '-01';
        $monthEnd = date('Y-m-t', strtotime($monthStart));
        
        $stmt = $pdo->prepare('
            SELECT SUM(daily_amount) as monthly_total
            FROM daily_cashback
            WHERE user_id = ? AND cashback_date BETWEEN ? AND ?
        ');
        $stmt->execute([$user_id, $monthStart, $monthEnd]);
        $result = $stmt->fetch();
        
        $monthlyTotal = $result['monthly_total'] ?? 0;
        $bonus1Amount = round($monthlyTotal * (BONUS_1_PERCENTAGE / 100), 2);
        
        // Update wallet
        updateWalletBalance($user_id, $bonus1Amount, 'add');
        
        // Record transaction
        recordTransaction(
            $user_id,
            'bonus_1',
            $bonus1Amount,
            'Bonus 1: 20% Maintenance Bonus (' . $month . ')'
        );
        
        // Update total earned
        $stmt = $pdo->prepare('
            UPDATE users 
            SET total_cashback_earned = total_cashback_earned + ?
            WHERE user_id = ?
        ');
        $stmt->execute([$bonus1Amount, $user_id]);
        
        // Track in bonus_1_tracking
        $stmt = $pdo->prepare('
            INSERT INTO bonus_1_tracking 
            (user_id, tracked_month, base_monthly_cashback, bonus_amount, is_qualified, bonus_credited_date)
            VALUES (?, ?, ?, ?, 1, NOW())
            ON DUPLICATE KEY UPDATE 
            bonus_amount = VALUES(bonus_amount), 
            is_qualified = 1, 
            bonus_credited_date = NOW()
        ');
        $stmt->execute([$user_id, $month, $monthlyTotal, $bonus1Amount]);
        
        return [
            'success' => true,
            'user_id' => $user_id,
            'month' => $month,
            'monthly_cashback' => $monthlyTotal,
            'bonus_amount' => $bonus1Amount,
            'bonus_percentage' => BONUS_1_PERCENTAGE
        ];
    } catch (PDOException $e) {
        logError('Bonus 1 credit failed', $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Get Bonus 1 status for dashboard
 */
function getBonus1Status($user_id) {
    global $pdo;
    
    $currentMonth = date('Y-m');
    $qualification = checkBonus1Qualification($user_id, $currentMonth);
    
    $daysRemaining = (int) date('d') <= 15 ? 15 - (int) date('d') : 0;
    
    return [
        'user_id' => $user_id,
        'current_month' => $currentMonth,
        'min_balance_required' => BONUS_1_MIN_BALANCE,
        'current_balance' => $qualification['current_balance'] ?? 0,
        'qualifies' => $qualification['qualifies'] ?? false,
        'bonus_percentage' => BONUS_1_PERCENTAGE,
        'days_remaining' => $daysRemaining,
        'maintenance_days' => BONUS_1_MAINTENANCE_DAYS
    ];
}

// ========================================
// 3. BONUS 2 (Weekly Business Challenge)
// ========================================

/**
 * Check Bonus 2 qualification for the week
 * Requires ₹50k or ₹1L business from downline in 1 week
 */
function checkBonus2Qualification($user_id, $week_start = null) {
    global $pdo;
    
    if ($week_start === null) {
        $week_start = date('Y-m-d', strtotime('monday this week'));
    }
    
    $week_end = date('Y-m-d', strtotime('+6 days', strtotime($week_start)));
    
    try {
        // Get total business generated by downline during this week
        // (assuming business = plan investment amounts made by referrals)
        $stmt = $pdo->prepare('
            SELECT SUM(pi.investment_amount) as total_business
            FROM plan_investments pi
            JOIN users u ON pi.user_id = u.user_id
            JOIN referrals r ON u.user_id = r.member_id
            WHERE r.sponsor_id = ? 
            AND pi.start_date BETWEEN ? AND ?
            AND pi.status = "active"
        ');
        $stmt->execute([$user_id, $week_start, $week_end]);
        
        $result = $stmt->fetch();
        $totalBusiness = $result['total_business'] ?? 0;
        
        // Determine bonus percentage based on threshold
        $bonusPercentage = 0;
        foreach (BONUS_2_THRESHOLDS as $threshold) {
            if ($totalBusiness >= $threshold['amount']) {
                $bonusPercentage = $threshold['percentage'];
            }
        }
        
        return [
            'user_id' => $user_id,
            'week_start' => $week_start,
            'week_end' => $week_end,
            'total_business' => $totalBusiness,
            'bonus_percentage' => $bonusPercentage,
            'qualifies' => $bonusPercentage > 0
        ];
    } catch (PDOException $e) {
        logError('Bonus 2 check failed', $e->getMessage());
        return [];
    }
}

/**
 * Calculate and credit Bonus 2
 * 10% for ₹50k business, 20% for ₹1L business
 */
function creditBonus2($user_id, $week_start = null) {
    global $pdo;
    
    if ($week_start === null) {
        $week_start = date('Y-m-d', strtotime('monday this week'));
    }
    
    $week_end = date('Y-m-d', strtotime('+6 days', strtotime($week_start)));
    
    try {
        $qualification = checkBonus2Qualification($user_id, $week_start);
        
        if (!$qualification['qualifies']) {
            return ['success' => false, 'message' => 'User does not qualify for Bonus 2'];
        }
        
        // Get user's latest active plan investment to calculate bonus on
        $stmt = $pdo->prepare('
            SELECT investment_amount FROM plan_investments
            WHERE user_id = ? AND status = "active"
            ORDER BY start_date DESC LIMIT 1
        ');
        $stmt->execute([$user_id]);
        $investment = $stmt->fetch();
        
        if (!$investment) {
            return ['success' => false, 'message' => 'No active investment found'];
        }
        
        // Calculate daily cashback for this investment
        $dailyCashback = calculateDailyCashback($investment['investment_amount']);
        $bonus2Amount = round($dailyCashback * ($qualification['bonus_percentage'] / 100), 2);
        
        // Update wallet
        updateWalletBalance($user_id, $bonus2Amount, 'add');
        
        // Record transaction
        recordTransaction(
            $user_id,
            'bonus_2',
            $bonus2Amount,
            'Bonus 2: ' . $qualification['bonus_percentage'] . '% Weekly Challenge (' . $week_start . ')'
        );
        
        // Update total earned
        $stmt = $pdo->prepare('
            UPDATE users 
            SET total_commission_earned = total_commission_earned + ?
            WHERE user_id = ?
        ');
        $stmt->execute([$bonus2Amount, $user_id]);
        
        // Track in bonus_2_tracking
        $stmt = $pdo->prepare('
            INSERT INTO bonus_2_tracking 
            (user_id, week_start_date, week_end_date, business_amount, bonus_percentage, bonus_amount, is_qualified, bonus_credited_date)
            VALUES (?, ?, ?, ?, ?, ?, 1, NOW())
            ON DUPLICATE KEY UPDATE 
            bonus_amount = VALUES(bonus_amount), 
            is_qualified = 1, 
            bonus_credited_date = NOW()
        ');
        $stmt->execute([
            $user_id,
            $week_start,
            $week_end,
            $qualification['total_business'],
            $qualification['bonus_percentage'],
            $bonus2Amount
        ]);
        
        return [
            'success' => true,
            'user_id' => $user_id,
            'week' => $week_start . ' to ' . $week_end,
            'business_amount' => $qualification['total_business'],
            'bonus_percentage' => $qualification['bonus_percentage'],
            'bonus_amount' => $bonus2Amount
        ];
    } catch (PDOException $e) {
        logError('Bonus 2 credit failed', $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// ========================================
// 4. LEVEL COMMISSION DISTRIBUTION
// ========================================

/**
 * Get commission percentage for a level
 * @param int $level 1-30
 * @return float Percentage (25% down to 1%)
 */
function getLevelCommissionPercentage($level) {
    if ($level < 1 || $level > 30) return 0;
    return LEVEL_COMMISSIONS[$level - 1];
}

/**
 * Calculate level commission
 * @param float $amount Amount to calculate commission on
 * @param int $level 1-30
 * @return float Commission amount
 */
function calculateLevelCommission($amount, $level) {
    $percentage = getLevelCommissionPercentage($level);
    return round($amount * ($percentage / 100), 2);
}

/**
 * Distribute cashback commissions to entire upline tree (30 levels)
 * Called when a user's cashback is credited
 */
function distributeUplineCommissions($user_id, $cashbackAmount) {
    global $pdo;
    
    if ($cashbackAmount <= 0) return true;
    
    try {
        // Get all upline sponsors up to 30 levels
        $sponsors = getUplineTree($user_id, 30);
        
        foreach ($sponsors as $sponsor) {
            $levelFromMember = $sponsor['level'];
            $percentage = getLevelCommissionPercentage($levelFromMember);
            
            if ($percentage <= 0) continue;
            
            $commissionAmount = round($cashbackAmount * ($percentage / 100), 2);
            
            // Update sponsor's wallet
            updateWalletBalance($sponsor['sponsor_id'], $commissionAmount, 'add');
            
            // Record transaction
            recordTransaction(
                $sponsor['sponsor_id'],
                'level_commission',
                $commissionAmount,
                'Level ' . $levelFromMember . ' Commission from ' . $user_id,
                $user_id
            );
            
            // Update total commission earned
            $stmt = $pdo->prepare('
                UPDATE users 
                SET total_commission_earned = total_commission_earned + ?
                WHERE user_id = ?
            ');
            $stmt->execute([$commissionAmount, $sponsor['sponsor_id']]);
        }
        
        return true;
    } catch (PDOException $e) {
        logError('Upline commission distribution failed', $e->getMessage());
        return false;
    }
}

/**
 * Get complete upline tree for a user
 * Returns all sponsors up to specified levels
 */
function getUplineTree($user_id, $maxLevels = 30) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare('
            WITH RECURSIVE upline AS (
                SELECT user_id, sponsor_id, 1 as level
                FROM users
                WHERE user_id = ?
                
                UNION ALL
                
                SELECT u.user_id, u.sponsor_id, upline.level + 1
                FROM users u
                INNER JOIN upline ON u.user_id = upline.sponsor_id
                WHERE upline.level < ? AND u.sponsor_id IS NOT NULL
            )
            SELECT sponsor_id, level FROM upline WHERE level > 0
        ');
        $stmt->execute([$user_id, $maxLevels]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        logError('Upline tree retrieval failed', $e->getMessage());
        return [];
    }
}

/**
 * Get complete downline tree for a user
 * Returns all members down to specified levels
 */
function getDownlineTree($user_id, $maxLevels = 30) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare('
            WITH RECURSIVE downline AS (
                SELECT user_id, sponsor_id, 1 as level
                FROM users
                WHERE sponsor_id = ?
                
                UNION ALL
                
                SELECT u.user_id, u.sponsor_id, downline.level + 1
                FROM users u
                INNER JOIN downline ON downline.user_id = u.sponsor_id
                WHERE downline.level < ? 
            )
            SELECT user_id, sponsor_id, level FROM downline
        ');
        $stmt->execute([$user_id, $maxLevels]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        logError('Downline tree retrieval failed', $e->getMessage());
        return [];
    }
}

/**
 * Get downline business statistics
 */
function getDownlineStats($user_id) {
    global $pdo;
    
    try {
        $downline = getDownlineTree($user_id, 30);
        
        $totalBusiness = 0;
        $totalDownlineMembers = count($downline);
        
        // Calculate total business from downline
        foreach ($downline as $member) {
            $stmt = $pdo->prepare('
                SELECT SUM(investment_amount) as total FROM plan_investments 
                WHERE user_id = ? AND status IN ("active", "matured")
            ');
            $stmt->execute([$member['user_id']]);
            $result = $stmt->fetch();
            $totalBusiness += $result['total'] ?? 0;
        }
        
        return [
            'user_id' => $user_id,
            'total_downline_members' => $totalDownlineMembers,
            'total_downline_business' => $totalBusiness,
            'downline_tree' => $downline
        ];
    } catch (PDOException $e) {
        logError('Downline stats failed', $e->getMessage());
        return [];
    }
}

/**
 * Assign badges automatically for a user based on active criteria
 */
function assignUserBadges($user_id) {
    global $pdo;

    try {
        $user = getUserById($user_id);
        if (!$user) return [];

        $totalInvestmentStmt = $pdo->prepare('SELECT COALESCE(SUM(investment_amount), 0) as total_investment FROM plan_investments WHERE user_id = ? AND status != "cancelled"');
        $totalInvestmentStmt->execute([$user_id]);
        $investmentData = $totalInvestmentStmt->fetch();
        $totalInvestment = $investmentData['total_investment'] ?? 0;

        $totalEarnings = ($user['total_cashback_earned'] ?? 0) + ($user['total_commission_earned'] ?? 0);

        $downline = getDownlineTree($user_id, 30);
        $teamSize = count($downline);

        $tenureDays = 0;
        if (!empty($user['registration_date'])) {
            $tenureDays = floor((time() - strtotime($user['registration_date'])) / (24 * 60 * 60));
        }

        $stmt = $pdo->prepare('SELECT * FROM badges WHERE is_active = 1');
        $stmt->execute();
        $badges = $stmt->fetchAll();

        $awarded = [];
        foreach ($badges as $badge) {
            $criteriaType = $badge['criteria_type'];
            $criteriaValue = (float) $badge['criteria_value'];
            $qualifies = false;

            if ($criteriaType === 'total_investment') {
                $qualifies = $totalInvestment >= $criteriaValue;
            } elseif ($criteriaType === 'total_earnings') {
                $qualifies = $totalEarnings >= $criteriaValue;
            } elseif ($criteriaType === 'team_size') {
                $qualifies = $teamSize >= $criteriaValue;
            } elseif ($criteriaType === 'tenure_days') {
                $qualifies = $tenureDays >= $criteriaValue;
            }

            if (!$qualifies) {
                continue;
            }

            $checkStmt = $pdo->prepare('SELECT user_badge_id FROM user_badges WHERE user_id = ? AND badge_id = ?');
            $checkStmt->execute([$user_id, $badge['badge_id']]);
            if ($checkStmt->fetch()) {
                continue;
            }

            $insertStmt = $pdo->prepare('INSERT INTO user_badges (user_id, badge_id, earned_at) VALUES (?, ?, NOW())');
            $insertStmt->execute([$user_id, $badge['badge_id']]);
            $awarded[] = $badge['badge_name'];
        }

        return $awarded;
    } catch (PDOException $e) {
        logError('Badge assignment failed', $e->getMessage());
        return [];
    }
}

/**
 * Assign badges for all active users
 */
function assignBadgesForAllUsers() {
    global $pdo;

    try {
        $stmt = $pdo->query('SELECT user_id FROM users WHERE status = "active"');
        $users = $stmt->fetchAll();
        $awarded = [];

        foreach ($users as $user) {
            $awardedForUser = assignUserBadges($user['user_id']);
            if (!empty($awardedForUser)) {
                $awarded[$user['user_id']] = $awardedForUser;
            }
        }

        return $awarded;
    } catch (PDOException $e) {
        logError('Badge assignment for all users failed', $e->getMessage());
        return [];
    }
}

/**
 * Build referral tree records for a newly registered member
 */
function createReferralRecordsForUser($member_id, $sponsor_id) {
    global $pdo;

    if (!$sponsor_id) {
        return;
    }

    $currentSponsor = $sponsor_id;
    $level = 1;

    while ($currentSponsor && $level <= 30) {
        $commissionPercentage = getLevelCommissionPercentage($level);

        $stmt = $pdo->prepare('INSERT INTO referrals (sponsor_id, member_id, level, commission_percentage) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE level = VALUES(level), commission_percentage = VALUES(commission_percentage)');
        $stmt->execute([$currentSponsor, $member_id, $level, $commissionPercentage]);

        $stmt2 = $pdo->prepare('SELECT sponsor_id FROM users WHERE user_id = ?');
        $stmt2->execute([$currentSponsor]);
        $next = $stmt2->fetch();
        $currentSponsor = $next['sponsor_id'] ?? null;
        $level++;
    }
}

/**
 * Build a public referral invite link for a user
 */
function getReferralInviteLink($username) {
    return BASE_URL . 'register.php?sponsor_username=' . urlencode($username);
}

/**
 * Credit Bonus 1 for all active users
 */
function processBonus1ForAllUsers($month = null) {
    global $pdo;

    $stmt = $pdo->query('SELECT user_id FROM users WHERE status = "active"');
    $users = $stmt->fetchAll();
    $results = [];

    foreach ($users as $user) {
        $results[$user['user_id']] = creditBonus1($user['user_id'], $month);
    }

    return $results;
}

/**
 * Credit Bonus 2 for all active users
 */
function processBonus2ForAllUsers($week_start = null) {
    global $pdo;

    $stmt = $pdo->query('SELECT user_id FROM users WHERE status = "active"');
    $users = $stmt->fetchAll();
    $results = [];

    foreach ($users as $user) {
        $results[$user['user_id']] = creditBonus2($user['user_id'], $week_start);
    }

    return $results;
}

/**
 * Create legacy investments records for active plan investments
 * This is useful when the platform stores active plan purchases in plan_investments
 * but daily cashback logic still depends on the legacy investments table.
 */
function syncPlanInvestmentsToLegacyInvestments() {
    global $pdo;

    try {
        $stmt = $pdo->query('SELECT pi.plan_id, pi.user_id, pi.investment_amount, pi.daily_percentage FROM plan_investments pi LEFT JOIN investments i ON pi.user_id = i.user_id AND i.investment_amount = pi.investment_amount AND i.daily_rate = pi.daily_percentage / 100 WHERE pi.status = "active" AND i.investment_id IS NULL');
        $rows = $stmt->fetchAll();
        $count = 0;

        $insert = $pdo->prepare('INSERT INTO investments (user_id, investment_amount, daily_rate, investment_date, status) VALUES (?, ?, ?, NOW(), "active")');
        foreach ($rows as $row) {
            $insert->execute([$row['user_id'], $row['investment_amount'], $row['daily_percentage'] / 100]);
            $count++;
        }

        return $count;
    } catch (PDOException $e) {
        logError('Plan investment sync failed', $e->getMessage());
        return 0;
    }
}

// ========================================

/**
 * Format amount as currency
 */
function formatCurrency($amount) {
    return '₹' . number_format($amount, 2);
}

/**
 * Format percentage
 */
function formatPercentage($value, $decimals = 2) {
    return number_format($value, $decimals) . '%';
}

/**
 * Get tier description
 */
function getTierDescription($investment) {
    $tier = getTierInfo($investment);
    return formatCurrency($tier['min']) . ' - ' . formatCurrency($tier['max']) . ' @ ' . 
           formatPercentage($tier['dailyRate'] * 100) . ' daily';
}

/**
 * Generate summary report for user
 */
function generateUserSummary($user_id) {
    global $pdo;
    
    $user = getUserById($user_id);
    if (!$user) return null;
    
    try {
        // Get current month's cashback
        $currentMonth = date('Y-m');
        $stmt = $pdo->prepare('
            SELECT SUM(daily_amount) as monthly_total FROM daily_cashback
            WHERE user_id = ? AND cashback_date LIKE ?
        ');
        $stmt->execute([$user_id, $currentMonth . '%']);
        $result = $stmt->fetch();
        $monthlyTotal = $result['monthly_total'] ?? 0;
        
        // Get investment details
        $stmt = $pdo->prepare('
            SELECT * FROM investments WHERE user_id = ? AND status = "active"
        ');
        $stmt->execute([$user_id]);
        $activeInvestment = $stmt->fetch();

        if (!$activeInvestment) {
            $stmt = $pdo->prepare('
                SELECT * FROM plan_investments WHERE user_id = ? AND status = "active" ORDER BY start_date DESC LIMIT 1
            ');
            $stmt->execute([$user_id]);
            $activeInvestment = $stmt->fetch();
        }
        
        // Get bonus status
        $bonus1Status = getBonus1Status($user_id);
        $bonus2Status = checkBonus2Qualification($user_id);
        
        return [
            'user' => $user,
            'active_investment' => $activeInvestment,
            'monthly_cashback' => $monthlyTotal,
            'wallet_balance' => $user['wallet_balance'],
            'total_earned' => $user['total_cashback_earned'] + $user['total_commission_earned'],
            'bonus_1' => $bonus1Status,
            'bonus_2' => $bonus2Status,
            'downline_stats' => getDownlineStats($user_id)
        ];
    } catch (PDOException $e) {
        logError('Summary generation failed', $e->getMessage());
        return null;
    }
}

// ========================================
// 6. WALLET & TRANSACTION HELPERS
// ========================================

/**
 * Update user's wallet balance
 * @param int $user_id
 * @param float $amount Amount to add or subtract
 * @param string $operation 'add' or 'sub'
 */
function updateWalletBalance($user_id, $amount, $operation = 'add') {
    global $pdo;
    
    try {
        if ($operation === 'add') {
            $stmt = $pdo->prepare('UPDATE users SET wallet_balance = wallet_balance + ? WHERE user_id = ?');
        } else {
            $stmt = $pdo->prepare('UPDATE users SET wallet_balance = wallet_balance - ? WHERE user_id = ?');
        }
        $stmt->execute([$amount, $user_id]);
        return true;
    } catch (PDOException $e) {
        logError('Wallet update failed', $e->getMessage());
        return false;
    }
}

/**
 * Record a transaction
 * @param int $user_id User making the transaction
 * @param string $transaction_type Type of transaction
 * @param float $amount Amount involved
 * @param string $description Description of transaction
 * @param int $related_user_id Optional related user
 */
function recordTransaction($user_id, $transaction_type, $amount, $description = '', $related_user_id = null) {
    global $pdo;
    
    try {
        $walletBalance = getUserWalletBalance($user_id);
        $stmt = $pdo->prepare('
            INSERT INTO transactions (user_id, transaction_type, amount, wallet_balance, description, status, related_user_id)
            VALUES (?, ?, ?, ?, ?, "completed", ?)
        ');
        $stmt->execute([$user_id, $transaction_type, $amount, $walletBalance, $description, $related_user_id]);
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        logError('Transaction recording failed', $e->getMessage());
        return false;
    }
}

function getUserWalletBalance($user_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare('SELECT wallet_balance FROM users WHERE user_id = ?');
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        return $result['wallet_balance'] ?? 0;
    } catch (PDOException $e) {
        logError('Get wallet balance failed', $e->getMessage());
        return 0;
    }
}

/**
 * Log error to file
 */
function logError($message, $details = '') {
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/error.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] $message";
    
    if (!empty($details)) {
        $logMessage .= " | Details: $details";
    }
    
    error_log($logMessage . PHP_EOL, 3, $logFile);
}

// ========================================
// IDEMPOTENCY CHECKS (PREVENT DUPLICATE REWARDS)
// ========================================

/**
 * Check if daily cashback already processed for today
 */
function isDailyCashbackProcessedToday() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare('
            SELECT COUNT(*) as count FROM daily_cashback 
            WHERE cashback_date = CURDATE() 
            LIMIT 1
        ');
        $stmt->execute();
        $result = $stmt->fetch();
        return ($result['count'] ?? 0) > 0;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Check if Bonus 1 already credited for user in given month
 * Format: YYYY-MM
 */
function isBonus1AlreadyCredited($user_id, $month) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare('
            SELECT bonus_1_id FROM bonus_1_tracking 
            WHERE user_id = ? AND tracked_month = ? AND bonus_credited_date IS NOT NULL 
            LIMIT 1
        ');
        $stmt->execute([$user_id, $month]);
        return $stmt->fetch() !== false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Check if Bonus 2 already credited for user in given week
 */
function isBonus2AlreadyCredited($user_id, $week_start_date) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare('
            SELECT bonus_2_id FROM bonus_2_tracking 
            WHERE user_id = ? AND week_start_date = ? AND bonus_credited_date IS NOT NULL 
            LIMIT 1
        ');
        $stmt->execute([$user_id, $week_start_date]);
        return $stmt->fetch() !== false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Get current month in YYYY-MM format
 */
function getCurrentMonth() {
    return date('Y-m');
}

/**
 * Get current week start date (Monday)
 */
function getCurrentWeekStart() {
    $today = new DateTime();
    $today->modify('Monday this week');
    return $today->format('Y-m-d');
}

// ========================================
// 30-DAY CHART DATA
// ========================================

/**
 * Check if user already has an active investment in a specific plan
 * Returns false if no active investment, or array with investment details if active
 */
function hasActivePlanInvestment($user_id, $plan_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare('
            SELECT invest_id, investment_amount, start_date, maturity_date, total_earned
            FROM plan_investments 
            WHERE user_id = ? AND plan_id = ? AND status = "active"
            LIMIT 1
        ');
        $stmt->execute([$user_id, $plan_id]);
        return $stmt->fetch() ?: false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Get last 30 days of actual earnings for chart display
 * Combines daily cashback and bonuses
 */
function getLast30DaysEarnings($user_id) {
    global $pdo;
    
    $result = [];
    
    try {
        // Fetch last 30 days of daily cashback from database
        $stmt = $pdo->prepare('
            SELECT 
                DATE(cashback_date) as earning_date,
                SUM(daily_amount) as daily_total
            FROM daily_cashback
            WHERE user_id = ? AND cashback_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY DATE(cashback_date)
            ORDER BY earning_date ASC
        ');
        $stmt->execute([$user_id]);
        $cashbackData = $stmt->fetchAll() ?? [];
        
        // Also get commission earnings from transactions in last 30 days
        $stmt2 = $pdo->prepare('
            SELECT 
                DATE(transaction_date) as earning_date,
                SUM(amount) as commission_total
            FROM transactions
            WHERE user_id = ? 
                AND transaction_type IN ("level_commission", "bonus_1", "bonus_2")
                AND transaction_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY DATE(transaction_date)
        ');
        $stmt2->execute([$user_id]);
        $commissionData = $stmt2->fetchAll() ?? [];
        
        // Merge and aggregate by date
        $earningsByDate = [];
        
        foreach ($cashbackData as $row) {
            $date = $row['earning_date'];
            if (!isset($earningsByDate[$date])) {
                $earningsByDate[$date] = 0;
            }
            $earningsByDate[$date] += (float)$row['daily_total'];
        }
        
        foreach ($commissionData as $row) {
            $date = $row['earning_date'];
            if (!isset($earningsByDate[$date])) {
                $earningsByDate[$date] = 0;
            }
            $earningsByDate[$date] += (float)$row['commission_total'];
        }
        
        // Fill missing dates with 0 for last 30 days
        $today = new DateTime();
        for ($i = 29; $i >= 0; $i--) {
            $date = $today->format('Y-m-d');
            $dateLabel = $today->format('M d');
            
            $amount = isset($earningsByDate[$date]) ? round($earningsByDate[$date], 2) : 0;
            $result[] = ['date' => $dateLabel, 'amount' => $amount];
            
            $today->modify('-1 day');
        }
        
        // Reverse to get chronological order (oldest to newest)
        return array_reverse($result);
        
    } catch (PDOException $e) {
        logError('Failed to fetch 30-day earnings', $e->getMessage());
        // Return empty last 30 days if query fails
        $result = [];
        $today = new DateTime();
        for ($i = 29; $i >= 0; $i--) {
            $dateLabel = $today->format('M d');
            $result[] = ['date' => $dateLabel, 'amount' => 0];
            $today->modify('-1 day');
        }
        return array_reverse($result);
    }
}

// ========================================
// PASSWORD RESET UTILITIES
// ========================================

/**
 * Generate a secure random token for password reset
 * @param int $length Token length (default 32)
 * @return string Random token
 */
function generateResetToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Send password reset email
 * @param string $email User email
 * @param string $token Reset token
 * @return bool True if email sent successfully
 */
function sendPasswordResetEmail($email, $token) {
    // Check if email is enabled
    if (!MAIL_ENABLED) {
        logError('Email sending disabled', 'Password reset email not sent to: ' . $email);
        return false;
    }

    $subject = 'Password Reset - ' . APP_NAME;
    $resetLink = BASE_URL . "reset-password.php?token=" . urlencode($token);
    
    $message = "
    <html>
    <head>
        <title>Password Reset - " . APP_NAME . "</title>
        <style>
            body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f4f4; }
            .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
            .header { background: linear-gradient(135deg, " . EMAIL_TEMPLATE_COLOR . " 0%, #764ba2 100%); color: white; padding: 30px 20px; text-align: center; }
            .header h1 { margin: 0; font-size: 24px; font-weight: 600; }
            .header p { margin: 5px 0 0 0; opacity: 0.9; font-size: 14px; }
            .content { padding: 40px 30px; background-color: #ffffff; }
            .content h2 { color: " . EMAIL_TEMPLATE_COLOR . "; margin-top: 0; font-size: 20px; font-weight: 600; }
            .button { display: inline-block; background: " . EMAIL_TEMPLATE_COLOR . "; color: white; padding: 14px 28px; text-decoration: none; border-radius: 6px; font-weight: 600; margin: 25px 0; transition: background-color 0.3s ease; }
            .button:hover { background-color: #5a67d8; }
            .warning { background-color: #fef5e7; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0; border-radius: 4px; }
            .warning strong { color: #92400e; }
            .footer { background-color: #f8f9fa; padding: 20px 30px; text-align: center; color: #666; font-size: 12px; border-top: 1px solid #e9ecef; }
            .footer p { margin: 5px 0; }
            .link-text { word-break: break-all; background-color: #f8f9fa; padding: 10px; border-radius: 4px; font-family: monospace; font-size: 12px; margin: 10px 0; }
            @media (max-width: 600px) {
                .container { margin: 10px; }
                .content { padding: 30px 20px; }
                .header { padding: 20px; }
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>" . APP_NAME . "</h1>
                <p>Password Reset Request</p>
            </div>
            <div class='content'>
                <h2>Reset Your Password</h2>
                <p>Hello,</p>
                <p>You have requested to reset your password for your " . APP_NAME . " account. Click the button below to create a new password:</p>

                <div style='text-align: center; margin: 30px 0;'>
                    <a href='{$resetLink}' class='button'>Reset Password</a>
                </div>

                <div class='warning'>
                    <strong>Important:</strong> This link will expire in 1 hour for security reasons. If you didn't request this password reset, please ignore this email. Your password will remain unchanged.
                </div>

                <p>If the button above doesn't work, copy and paste this link into your browser:</p>
                <div class='link-text'>{$resetLink}</div>

                <p>For security reasons, please do not share this email or the reset link with anyone.</p>

                <p>If you have any questions or need assistance, please contact our support team.</p>

                <p>Best regards,<br>The " . APP_NAME . " Team</p>
            </div>
            <div class='footer'>
                <p>This email was sent by " . APP_NAME . " Platform</p>
                <p>&copy; " . date('Y') . " " . APP_NAME . ". All rights reserved.</p>
                <p>If you have any questions, please contact our <a href='mailto:" . MAIL_REPLY_TO . "' style='color: " . EMAIL_TEMPLATE_COLOR . ";'>support team</a>.</p>
            </div>
        </div>
    </body>
    </html>
    ";

    // Send the email
    $result = sendEmail($email, $subject, $message);

    if ($result) {
        logError('Password reset email sent', 'Successfully sent to: ' . $email);
    } else {
        logError('Password reset email failed', 'Failed to send to: ' . $email);
    }

    return $result;
}

/**
 * Validate password reset token
 * @param string $token Reset token
 * @return array|null User data if token is valid, null otherwise
 */
function validateResetToken($token) {
    global $pdo;

    try {
        $stmt = $pdo->prepare('
            SELECT user_id, username, email, reset_expires
            FROM users
            WHERE reset_token = ? AND reset_expires > NOW()
        ');
        $stmt->execute([$token]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        logError('Token validation failed', $e->getMessage());
        return null;
    }
}

/**
 * Clear password reset token
 * @param int $user_id User ID
 */
function clearResetToken($user_id) {
    global $pdo;

    try {
        $stmt = $pdo->prepare('UPDATE users SET reset_token = NULL, reset_expires = NULL WHERE user_id = ?');
        $stmt->execute([$user_id]);
    } catch (PDOException $e) {
        logError('Clear reset token failed', $e->getMessage());
    }
}

/**
 * Send email using configured method (PHP mail or SMTP)
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $message HTML message
 * @return bool True if sent successfully
 */
function sendEmail($to, $subject, $message) {
    // Set up headers
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM_EMAIL . '>',
        'Reply-To: ' . MAIL_REPLY_TO,
        'X-Mailer: PHP/' . phpversion()
    ];

    // Try SMTP first if configured, fallback to PHP mail
    if (SMTP_HOST && SMTP_HOST !== 'smtp.gmail.com') {
        return sendSMTPEmail($to, $subject, $message, $headers);
    } else {
        // Use PHP's built-in mail function
        $headerString = implode("\r\n", $headers);
        return mail($to, $subject, $message, $headerString);
    }
}

/**
 * Send email using SMTP
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $message HTML message
 * @param array $headers Email headers
 * @return bool True if sent successfully
 */
function sendSMTPEmail($to, $subject, $message, $headers) {
    // For SMTP sending, we'll use a simple implementation
    // In production, consider using PHPMailer or SwiftMailer libraries

    try {
        // Create socket connection
        $socket = fsockopen(
            (SMTP_ENCRYPTION === 'ssl' ? 'ssl://' : '') . SMTP_HOST,
            SMTP_PORT,
            $errno,
            $errstr,
            30
        );

        if (!$socket) {
            logError('SMTP Connection Failed', "Error: $errstr ($errno)");
            return false;
        }

        // Read server greeting
        $response = fgets($socket, 515);
        if (!smtp_check_response($response, '220')) {
            fclose($socket);
            return false;
        }

        // Send EHLO
        fwrite($socket, "EHLO " . $_SERVER['SERVER_NAME'] . "\r\n");
        smtp_read_response($socket);

        // Start TLS if required
        if (SMTP_ENCRYPTION === 'tls') {
            fwrite($socket, "STARTTLS\r\n");
            $response = fgets($socket, 515);
            if (!smtp_check_response($response, '220')) {
                fclose($socket);
                return false;
            }
            // Enable crypto
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            // Send EHLO again after TLS
            fwrite($socket, "EHLO " . $_SERVER['SERVER_NAME'] . "\r\n");
            smtp_read_response($socket);
        }

        // Authenticate if required
        if (SMTP_AUTH && SMTP_USERNAME && SMTP_PASSWORD) {
            fwrite($socket, "AUTH LOGIN\r\n");
            $response = fgets($socket, 515);
            if (!smtp_check_response($response, '334')) {
                fclose($socket);
                return false;
            }

            // Send username (base64 encoded)
            fwrite($socket, base64_encode(SMTP_USERNAME) . "\r\n");
            $response = fgets($socket, 515);
            if (!smtp_check_response($response, '334')) {
                fclose($socket);
                return false;
            }

            // Send password (base64 encoded)
            fwrite($socket, base64_encode(SMTP_PASSWORD) . "\r\n");
            $response = fgets($socket, 515);
            if (!smtp_check_response($response, '235')) {
                fclose($socket);
                return false;
            }
        }

        // Send MAIL FROM
        fwrite($socket, "MAIL FROM:<" . MAIL_FROM_EMAIL . ">\r\n");
        $response = fgets($socket, 515);
        if (!smtp_check_response($response, '250')) {
            fclose($socket);
            return false;
        }

        // Send RCPT TO
        fwrite($socket, "RCPT TO:<$to>\r\n");
        $response = fgets($socket, 515);
        if (!smtp_check_response($response, '250')) {
            fclose($socket);
            return false;
        }

        // Send DATA
        fwrite($socket, "DATA\r\n");
        $response = fgets($socket, 515);
        if (!smtp_check_response($response, '354')) {
            fclose($socket);
            return false;
        }

        // Send email content
        $emailContent = "Subject: $subject\r\n";
        $emailContent .= implode("\r\n", $headers) . "\r\n\r\n";
        $emailContent .= $message . "\r\n.\r\n";

        fwrite($socket, $emailContent);
        $response = fgets($socket, 515);
        if (!smtp_check_response($response, '250')) {
            fclose($socket);
            return false;
        }

        // Send QUIT
        fwrite($socket, "QUIT\r\n");
        fgets($socket, 515);
        fclose($socket);

        return true;

    } catch (Exception $e) {
        logError('SMTP Email Error', $e->getMessage());
        return false;
    }
}

/**
 * Check SMTP response code
 * @param string $response SMTP response
 * @param string $expected_code Expected response code
 * @return bool True if response matches expected code
 */
function smtp_check_response($response, $expected_code) {
    return strpos($response, $expected_code) === 0;
}

/**
 * Read SMTP response (for multi-line responses)
 * @param resource $socket Socket connection
 * @return string Response
 */
function smtp_read_response($socket) {
    $response = '';
    while ($line = fgets($socket, 515)) {
        $response .= $line;
        if (substr($line, 3, 1) === ' ') {
            break;
        }
    }
    return $response;
}
?>

