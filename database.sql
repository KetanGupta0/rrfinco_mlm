-- BachatPay MLM Platform - Complete Database Schema
-- Comprehensive schema supporting all MLM business logic from BachatPay PDF

-- CREATE DATABASE IF NOT EXISTS bachatpay_db;
USE u362349964_bachat_pay_mlm;

-- ================================================================
-- PRIMARY USERS TABLE - Core user information
-- ================================================================
CREATE TABLE IF NOT EXISTS users (
    user_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    sponsor_id INT(11),
    first_name VARCHAR(50),
    last_name VARCHAR(50),
    phone VARCHAR(15),
    city VARCHAR(50),
    state VARCHAR(50),
    bank_account VARCHAR(20),
    bank_name VARCHAR(100),
    ifsc_code VARCHAR(20),
    wallet_balance DECIMAL(15, 2) DEFAULT 0.00,
    total_cashback_earned DECIMAL(15, 2) DEFAULT 0.00,
    total_commission_earned DECIMAL(15, 2) DEFAULT 0.00,
    total_downline_business DECIMAL(15, 2) DEFAULT 0.00,
    active_investment DECIMAL(15, 2) DEFAULT 0.00,
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login DATETIME,
    reset_token VARCHAR(255),
    reset_expires DATETIME,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    FOREIGN KEY (sponsor_id) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_sponsor (sponsor_id),
    INDEX idx_email (email),
    INDEX idx_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- INVESTMENTS TABLE - Track user investment amounts
-- ================================================================
CREATE TABLE IF NOT EXISTS investments (
    investment_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    investment_amount DECIMAL(15, 2) NOT NULL,
    daily_rate DECIMAL(5, 4) NOT NULL COMMENT '0.10%, 0.12%, 0.14%, 0.16%',
    investment_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_cashback_processed DATE,
    status ENUM('active', 'inactive', 'matured') DEFAULT 'active',
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- DAILY CASHBACK DATA - Track daily cashback earnings
-- ================================================================
CREATE TABLE IF NOT EXISTS daily_cashback (
    cashback_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    investment_id INT(11) NOT NULL,
    daily_amount DECIMAL(15, 2) NOT NULL,
    monthly_cumulative DECIMAL(15, 2),
    cashback_date DATE NOT NULL,
    credit_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (investment_id) REFERENCES investments(investment_id) ON DELETE CASCADE,
    UNIQUE KEY unique_daily (user_id, cashback_date),
    INDEX idx_user (user_id),
    INDEX idx_date (cashback_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- TRANSACTIONS TABLE - All financial movements (credits/debits)
-- ================================================================
CREATE TABLE IF NOT EXISTS transactions (
    transaction_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    transaction_type VARCHAR(100) NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    wallet_balance DECIMAL(15, 2) NOT NULL COMMENT 'Balance after this transaction',
    description VARCHAR(255),
    status ENUM('pending', 'completed', 'cancelled') DEFAULT 'completed',
    related_user_id INT(11) COMMENT 'For commission: who generated the income',
    transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    type ENUM('daily_cashback', 'bonus_1', 'bonus_2', 'level_commission', 'withdrawal', 'manual_credit', 'manual_debit', 'deposit', 'investment', 'cashback', 'commission', 'bonus', 'roi') COMMENT 'Legacy field',
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (related_user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_type (transaction_type),
    INDEX idx_date (transaction_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- REFERRAL/GENEALOGY TABLE - MLM tree structure (30 levels)
-- ================================================================
CREATE TABLE IF NOT EXISTS referrals (
    referral_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    sponsor_id INT(11) NOT NULL,
    member_id INT(11) NOT NULL,
    level INT(3) NOT NULL COMMENT '1-30 levels',
    commission_percentage DECIMAL(5, 2) NOT NULL,
    referral_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sponsor_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY unique_relationship (sponsor_id, member_id),
    INDEX idx_sponsor (sponsor_id),
    INDEX idx_member (member_id),
    INDEX idx_level (level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- BONUS 1 TRACKING - 30-day maintenance bonus (20% extra cashback)
-- ================================================================
CREATE TABLE IF NOT EXISTS bonus_1_tracking (
    bonus_1_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    tracked_month VARCHAR(7) COMMENT 'YYYY-MM format',
    maintenance_start_date DATE,
    maintenance_end_date DATE,
    min_balance_required DECIMAL(15, 2) DEFAULT 2000.00,
    min_balance_maintained DECIMAL(15, 2) DEFAULT 0.00,
    is_qualified TINYINT(1) DEFAULT 0,
    base_monthly_cashback DECIMAL(15, 2),
    bonus_amount DECIMAL(15, 2) DEFAULT 0.00,
    bonus_credited_date DATETIME,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY monthly_tracking (user_id, tracked_month),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- BONUS 2 TRACKING - Weekly business challenge bonus
-- ================================================================
CREATE TABLE IF NOT EXISTS bonus_2_tracking (
    bonus_2_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    week_start_date DATE NOT NULL,
    week_end_date DATE NOT NULL,
    business_amount DECIMAL(15, 2) DEFAULT 0.00,
    bonus_percentage DECIMAL(3, 1) DEFAULT 0.0 COMMENT '10% or 20%',
    bonus_amount DECIMAL(15, 2) DEFAULT 0.00,
    is_qualified TINYINT(1) DEFAULT 0,
    bonus_credited_date DATETIME,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY weekly_tracking (user_id, week_start_date),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- USER BANK ACCOUNTS - Store user's bank account details
-- ================================================================
CREATE TABLE IF NOT EXISTS user_bank_accounts (
    bank_account_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    account_holder_name VARCHAR(100) NOT NULL,
    bank_name VARCHAR(100) NOT NULL,
    account_number VARCHAR(20) NOT NULL,
    ifsc_code VARCHAR(20) NOT NULL,
    is_primary TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_primary (is_primary)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- WITHDRAWAL REQUESTS TABLE - Payout tracking
-- ================================================================
CREATE TABLE IF NOT EXISTS withdrawal_requests (
    withdrawal_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    bank_account_id INT(11),
    status ENUM('pending', 'completed', 'rejected') DEFAULT 'pending',
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME,
    notes TEXT,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (bank_account_id) REFERENCES user_bank_accounts(bank_account_id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_user (user_id),
    INDEX idx_date (requested_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- INVESTMENT PLANS - Available investment packages
-- ================================================================
CREATE TABLE IF NOT EXISTS investment_plans (
    plan_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    plan_name VARCHAR(100) NOT NULL,
    min_amount DECIMAL(15, 2) NOT NULL,
    max_amount DECIMAL(15, 2) NOT NULL,
    daily_percentage DECIMAL(5, 4) NOT NULL,
    monthly_percentage DECIMAL(5, 2) NOT NULL,
    duration_days INT(11),
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- PLAN INVESTMENTS - User's plan purchases history
-- ================================================================
CREATE TABLE IF NOT EXISTS plan_investments (
    invest_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    plan_id INT(11) NOT NULL,
    investment_amount DECIMAL(15, 2) NOT NULL,
    daily_percentage DECIMAL(5, 4) NOT NULL,
    monthly_percentage DECIMAL(5, 2) NOT NULL,
    start_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    maturity_date DATETIME,
    matured_at DATETIME,
    total_earned DECIMAL(15, 2) DEFAULT 0.00,
    status ENUM('active', 'matured', 'cancelled') DEFAULT 'active',
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES investment_plans(plan_id) ON DELETE RESTRICT,
    INDEX idx_user (user_id),
    INDEX idx_status (status),
    INDEX idx_date (start_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- DEPOSITS - User deposit records
-- ================================================================
CREATE TABLE IF NOT EXISTS deposits (
    deposit_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    payment_gateway VARCHAR(50) COMMENT 'stripe, paypal, razorpay, etc',
    transaction_id VARCHAR(100),
    description VARCHAR(255),
    status ENUM('pending', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
    deposited_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_status (status),
    INDEX idx_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- BADGES - Badge levels defined by admin
-- ================================================================
CREATE TABLE IF NOT EXISTS badges (
    badge_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    badge_name VARCHAR(100) NOT NULL,
    description TEXT,
    criteria_type ENUM('total_investment', 'total_earnings', 'team_size', 'tenure_days') NOT NULL,
    criteria_value DECIMAL(15, 2) NOT NULL,
    badge_icon VARCHAR(100),
    badge_color VARCHAR(20),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- USER BADGES - Track which badges user has earned
-- ================================================================
CREATE TABLE IF NOT EXISTS user_badges (
    user_badge_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    badge_id INT(11) NOT NULL,
    earned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (badge_id) REFERENCES badges(badge_id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_badge (user_id, badge_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- SUPPORT TICKETS - User support queries
-- ================================================================
CREATE TABLE IF NOT EXISTS support_tickets (
    ticket_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    category VARCHAR(50),
    status ENUM('open', 'in_progress', 'closed', 'reopened') DEFAULT 'open',
    priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,
    closed_at DATETIME,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_status (status),
    INDEX idx_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- SUPPORT TICKET REPLIES - Messages in support conversations
-- ================================================================
CREATE TABLE IF NOT EXISTS support_replies (
    reply_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT(11) NOT NULL,
    sender_id INT(11) NOT NULL COMMENT 'user_id of sender',
    message TEXT NOT NULL,
    is_admin_reply TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES support_tickets(ticket_id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(user_id) ON DELETE RESTRICT,
    INDEX idx_ticket (ticket_id),
    INDEX idx_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- Seed data for investment plans
-- ================================================================
INSERT INTO investment_plans (plan_name, min_amount, max_amount, daily_percentage, monthly_percentage, duration_days, description) VALUES
('Silver Plan', 1000, 25000, 0.10, 3.00, 365, 'Entry level plan with 0.10% daily returns'),
('Gold Plan', 26000, 100000, 0.12, 3.60, 365, 'Mid-level plan with 0.12% daily returns'),
('Platinum Plan', 101000, 500000, 0.14, 4.20, 365, 'Premium plan with 0.14% daily returns'),
('Diamond Plan', 500001, 999999999, 0.16, 4.80, 365, 'Elite plan with 0.16% daily returns');

-- ================================================================
-- Seed data for badges
-- ================================================================
INSERT INTO badges (badge_name, description, criteria_type, criteria_value, badge_color, is_active) VALUES
('Bronze Member', 'Achieved by investing ₹1,00,000', 'total_investment', 100000, '#CD7F32', 1),
('Silver Member', 'Achieved by investing ₹5,00,000', 'total_investment', 500000, '#C0C0C0', 1),
('Gold Member', 'Achieved by investing ₹10,00,000', 'total_investment', 1000000, '#FFD700', 1),
('Platinum Member', 'Achieved by earning ₹1,00,000', 'total_earnings', 100000, '#E5E4E2', 1),
('Team Leader', 'Built a team of 10+ members', 'team_size', 10, '#FF6B6B', 1),
('Team Manager', 'Built a team of 50+ members', 'team_size', 50, '#4ECDC4', 1),
('Early Bird', 'Member for 30+ days', 'tenure_days', 30, '#FFE66D', 1),
('Veteran', 'Member for 365+ days', 'tenure_days', 365, '#95E1D3', 1);
