-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Apr 16, 2026 at 06:21 PM
-- Server version: 11.8.6-MariaDB-log
-- PHP Version: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u362349964_bachat_pay_mlm`
--

-- --------------------------------------------------------

--
-- Table structure for table `badges`
--

CREATE TABLE `badges` (
  `badge_id` int(11) NOT NULL,
  `badge_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `criteria_type` enum('total_investment','total_earnings','team_size','tenure_days') NOT NULL,
  `criteria_value` decimal(15,2) NOT NULL,
  `badge_icon` varchar(100) DEFAULT NULL,
  `badge_color` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `badges`
--

INSERT INTO `badges` (`badge_id`, `badge_name`, `description`, `criteria_type`, `criteria_value`, `badge_icon`, `badge_color`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Bronze Member', 'Achieved by investing ₹1,00,000', 'total_investment', 100000.00, NULL, '#CD7F32', 1, '2026-04-10 08:13:55', NULL),
(2, 'Silver Member', 'Achieved by investing ₹5,00,000', 'total_investment', 500000.00, NULL, '#C0C0C0', 1, '2026-04-10 08:13:55', NULL),
(3, 'Gold Member', 'Achieved by investing ₹10,00,000', 'total_investment', 1000000.00, NULL, '#FFD700', 1, '2026-04-10 08:13:55', NULL),
(4, 'Platinum Member', 'Achieved by earning ₹1,00,000', 'total_earnings', 100000.00, NULL, '#E5E4E2', 1, '2026-04-10 08:13:55', NULL),
(5, 'Team Leader', 'Built a team of 10+ members', 'team_size', 10.00, NULL, '#FF6B6B', 1, '2026-04-10 08:13:55', NULL),
(6, 'Team Manager', 'Built a team of 50+ members', 'team_size', 50.00, NULL, '#4ECDC4', 1, '2026-04-10 08:13:55', NULL),
(7, 'Early Bird', 'Member for 30+ days', 'tenure_days', 30.00, NULL, '#FFE66D', 1, '2026-04-10 08:13:55', NULL),
(8, 'Veteran', 'Member for 365+ days', 'tenure_days', 365.00, NULL, '#95E1D3', 1, '2026-04-10 08:13:55', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `bonus_1_tracking`
--

CREATE TABLE `bonus_1_tracking` (
  `bonus_1_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `tracked_month` varchar(7) DEFAULT NULL COMMENT 'YYYY-MM format',
  `maintenance_start_date` date DEFAULT NULL,
  `maintenance_end_date` date DEFAULT NULL,
  `min_balance_required` decimal(15,2) DEFAULT 2000.00,
  `min_balance_maintained` decimal(15,2) DEFAULT 0.00,
  `is_qualified` tinyint(1) DEFAULT 0,
  `base_monthly_cashback` decimal(15,2) DEFAULT NULL,
  `bonus_amount` decimal(15,2) DEFAULT 0.00,
  `bonus_credited_date` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `bonus_1_tracking`
--

INSERT INTO `bonus_1_tracking` (`bonus_1_id`, `user_id`, `tracked_month`, `maintenance_start_date`, `maintenance_end_date`, `min_balance_required`, `min_balance_maintained`, `is_qualified`, `base_monthly_cashback`, `bonus_amount`, `bonus_credited_date`) VALUES
(1, 8, '2026-04', NULL, NULL, 2000.00, 0.00, 1, 0.00, 0.00, '2026-04-13 15:56:56');

-- --------------------------------------------------------

--
-- Table structure for table `bonus_2_tracking`
--

CREATE TABLE `bonus_2_tracking` (
  `bonus_2_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `week_start_date` date NOT NULL,
  `week_end_date` date NOT NULL,
  `business_amount` decimal(15,2) DEFAULT 0.00,
  `bonus_percentage` decimal(3,1) DEFAULT 0.0 COMMENT '10% or 20%',
  `bonus_amount` decimal(15,2) DEFAULT 0.00,
  `is_qualified` tinyint(1) DEFAULT 0,
  `bonus_credited_date` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `daily_cashback`
--

CREATE TABLE `daily_cashback` (
  `cashback_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `investment_id` int(11) NOT NULL,
  `daily_amount` decimal(15,2) NOT NULL,
  `monthly_cumulative` decimal(15,2) DEFAULT NULL,
  `cashback_date` date NOT NULL,
  `credit_date` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `daily_cashback`
--

INSERT INTO `daily_cashback` (`cashback_id`, `user_id`, `investment_id`, `daily_amount`, `monthly_cumulative`, `cashback_date`, `credit_date`) VALUES
(1, 8, 1, 1.20, NULL, '2026-04-15', '2026-04-15 20:33:49');

-- --------------------------------------------------------

--
-- Table structure for table `deposits`
--

CREATE TABLE `deposits` (
  `deposit_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `payment_gateway` varchar(50) DEFAULT NULL COMMENT 'stripe, paypal, razorpay, etc',
  `transaction_id` varchar(100) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `status` enum('pending','completed','failed','cancelled') DEFAULT 'pending',
  `deposited_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `deposits`
--

INSERT INTO `deposits` (`deposit_id`, `user_id`, `amount`, `payment_gateway`, `transaction_id`, `description`, `status`, `deposited_at`, `created_at`) VALUES
(4, 8, 5000.00, 'stripe', 'TXN1776078695', NULL, 'completed', NULL, '2026-04-13 11:11:35'),
(5, 8, 10000.00, 'upi', 'TXN1776236055', NULL, 'completed', NULL, '2026-04-15 06:54:15'),
(6, 8, 350000.00, 'paypal', 'TXN1776236098', NULL, 'completed', NULL, '2026-04-15 06:54:58'),
(7, 8, 1000.00, 'bank_transfer', 'TXN1776284332', NULL, 'completed', NULL, '2026-04-15 20:18:52'),
(8, 8, 500.00, 'stripe', 'TXN1776285571', NULL, 'completed', NULL, '2026-04-15 20:39:31');

-- --------------------------------------------------------

--
-- Table structure for table `investments`
--

CREATE TABLE `investments` (
  `investment_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `investment_amount` decimal(15,2) NOT NULL,
  `daily_rate` decimal(5,4) NOT NULL COMMENT '0.10%, 0.12%, 0.14%, 0.16%',
  `investment_date` datetime DEFAULT current_timestamp(),
  `last_cashback_processed` date DEFAULT NULL,
  `status` enum('active','inactive','matured') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `investments`
--

INSERT INTO `investments` (`investment_id`, `user_id`, `investment_amount`, `daily_rate`, `investment_date`, `last_cashback_processed`, `status`) VALUES
(1, 8, 27000.00, 0.0012, '2026-04-15 20:25:21', '2026-04-15', 'active'),
(2, 8, 1200.00, 0.0010, '2026-04-15 20:25:46', '2026-04-15', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `investment_plans`
--

CREATE TABLE `investment_plans` (
  `plan_id` int(11) NOT NULL,
  `plan_name` varchar(100) NOT NULL,
  `min_amount` decimal(15,2) NOT NULL,
  `max_amount` decimal(15,2) NOT NULL,
  `daily_percentage` decimal(5,4) NOT NULL,
  `monthly_percentage` decimal(5,2) NOT NULL,
  `duration_days` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `investment_plans`
--

INSERT INTO `investment_plans` (`plan_id`, `plan_name`, `min_amount`, `max_amount`, `daily_percentage`, `monthly_percentage`, `duration_days`, `description`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Silver Plan', 1000.00, 25000.00, 0.1000, 3.00, 365, 'Entry level plan with 0.10% daily returns', 1, '2026-04-10 08:13:55', NULL),
(2, 'Gold Plan', 26000.00, 100000.00, 0.1200, 3.60, 365, 'Mid-level plan with 0.12% daily returns', 1, '2026-04-10 08:13:55', NULL),
(3, 'Platinum Plan', 101000.00, 500000.00, 0.1400, 4.20, 365, 'Premium plan with 0.14% daily returns', 1, '2026-04-10 08:13:55', NULL),
(4, 'Diamond Plan', 500001.00, 999999999.00, 0.1600, 4.80, 365, 'Elite plan with 0.16% daily returns', 1, '2026-04-10 08:13:55', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `joining_bonus_tracker`
--

CREATE TABLE `joining_bonus_tracker` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `total_bonus` decimal(10,2) DEFAULT 1200.00,
  `released_amount` decimal(10,2) DEFAULT 0.00,
  `first_release_done` tinyint(1) DEFAULT 0,
  `next_release_at` datetime NOT NULL,
  `last_release_at` datetime DEFAULT NULL,
  `is_completed` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `joining_bonus_tracker`
--

INSERT INTO `joining_bonus_tracker` (`id`, `user_id`, `total_bonus`, `released_amount`, `first_release_done`, `next_release_at`, `last_release_at`, `is_completed`, `created_at`) VALUES
(1, 5, 1200.00, 100.00, 1, '2026-05-01 09:01:24', '2026-04-13 09:30:51', 0, '2026-04-01 09:01:24'),
(2, 6, 1200.00, 100.00, 1, '2026-05-15 10:48:12', '2026-04-15 05:18:12', 0, '2026-04-13 10:24:30'),
(3, 7, 1200.00, 100.00, 1, '2026-05-15 10:48:12', '2026-04-15 05:18:12', 0, '2026-04-13 11:07:26'),
(4, 8, 1200.00, 100.00, 1, '2026-05-13 11:11:15', '2026-04-15 05:18:12', 0, '2026-04-13 11:11:15'),
(5, 9, 1200.00, 0.00, 0, '2026-04-17 01:57:28', NULL, 0, '2026-04-15 20:27:28'),
(6, 10, 1200.00, 0.00, 0, '2026-04-17 01:59:00', NULL, 0, '2026-04-15 20:29:00'),
(7, 11, 1200.00, 0.00, 0, '2026-04-17 02:00:32', NULL, 0, '2026-04-15 20:30:32'),
(8, 12, 1200.00, 0.00, 0, '2026-04-17 02:01:23', NULL, 0, '2026-04-15 20:31:23');

-- --------------------------------------------------------

--
-- Table structure for table `plan_investments`
--

CREATE TABLE `plan_investments` (
  `invest_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `investment_amount` decimal(15,2) NOT NULL,
  `daily_percentage` decimal(5,4) NOT NULL,
  `monthly_percentage` decimal(5,2) NOT NULL,
  `start_date` datetime DEFAULT current_timestamp(),
  `maturity_date` datetime DEFAULT NULL,
  `matured_at` datetime DEFAULT NULL,
  `total_earned` decimal(15,2) DEFAULT 0.00,
  `status` enum('active','matured','cancelled') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `plan_investments`
--

INSERT INTO `plan_investments` (`invest_id`, `user_id`, `plan_id`, `investment_amount`, `daily_percentage`, `monthly_percentage`, `start_date`, `maturity_date`, `matured_at`, `total_earned`, `status`) VALUES
(1, 8, 2, 27000.00, 0.1200, 3.60, '2026-04-15 20:25:21', '2027-04-16 01:55:21', NULL, 0.00, 'active'),
(2, 8, 1, 1200.00, 0.1000, 3.00, '2026-04-15 20:25:46', '2027-04-16 01:55:46', NULL, 0.00, 'active');

-- --------------------------------------------------------

--
-- Table structure for table `referrals`
--

CREATE TABLE `referrals` (
  `referral_id` int(11) NOT NULL,
  `sponsor_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `level` int(3) NOT NULL COMMENT '1-30 levels',
  `commission_percentage` decimal(5,2) NOT NULL,
  `referral_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `referrals`
--

INSERT INTO `referrals` (`referral_id`, `sponsor_id`, `member_id`, `level`, `commission_percentage`, `referral_date`) VALUES
(15, 8, 9, 1, 25.00, '2026-04-15 20:27:28'),
(16, 9, 10, 1, 25.00, '2026-04-15 20:29:00'),
(17, 8, 10, 2, 10.00, '2026-04-15 20:29:00'),
(18, 8, 11, 1, 25.00, '2026-04-15 20:30:32'),
(19, 11, 12, 1, 25.00, '2026-04-15 20:31:23'),
(20, 8, 12, 2, 10.00, '2026-04-15 20:31:23');

-- --------------------------------------------------------

--
-- Table structure for table `support_replies`
--

CREATE TABLE `support_replies` (
  `reply_id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL COMMENT 'user_id of sender',
  `message` text NOT NULL,
  `is_admin_reply` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `support_tickets`
--

CREATE TABLE `support_tickets` (
  `ticket_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `category` varchar(50) DEFAULT NULL,
  `status` enum('open','in_progress','closed','reopened') DEFAULT 'open',
  `priority` enum('low','medium','high','critical') DEFAULT 'medium',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `closed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `support_tickets`
--

INSERT INTO `support_tickets` (`ticket_id`, `user_id`, `subject`, `message`, `category`, `status`, `priority`, `created_at`, `updated_at`, `closed_at`) VALUES
(1, 8, 'fdgdsf', 'sdfgbvcxvbdbhdfdf', 'other', 'open', '', '2026-04-15 20:33:05', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `transaction_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `transaction_type` varchar(100) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `wallet_balance` decimal(15,2) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `status` enum('pending','completed','cancelled') DEFAULT 'completed',
  `related_user_id` int(11) DEFAULT NULL COMMENT 'For commission: who generated the income',
  `transaction_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `type` enum('daily_cashback','bonus_1','bonus_2','level_commission','withdrawal','manual_credit','manual_debit','deposit','investment','cashback','commission','bonus','roi') DEFAULT NULL COMMENT 'Legacy field'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`transaction_id`, `user_id`, `transaction_type`, `amount`, `wallet_balance`, `description`, `status`, `related_user_id`, `transaction_date`, `type`) VALUES
(13, 8, 'joining_bonus', 1200.00, 0.00, 'Joining bonus assigned (₹1200)', 'completed', NULL, '2026-04-13 11:11:15', NULL),
(14, 8, 'deposit', 5000.00, 5000.00, 'Deposit via Stripe', 'completed', NULL, '2026-04-13 11:11:35', NULL),
(15, 8, 'withdrawal', 1000.00, 4000.00, 'Bank withdrawal request', 'completed', NULL, '2026-04-13 11:12:00', NULL),
(16, 8, 'withdrawal', -1000.00, 3000.00, 'UPI withdrawal request (sss.kkk) | Withdrawal Completed', 'completed', NULL, '2026-04-13 12:03:45', NULL),
(17, 8, 'bonus_1', 0.00, 3000.00, 'Bonus 1: 20% Maintenance Bonus (2026-04)', 'completed', NULL, '2026-04-13 15:56:56', NULL),
(20, 8, 'joining_bonus', 100.00, 3000.00, 'Transferred to usable wallet (Joining Bonus)', 'completed', NULL, '2026-04-15 05:18:12', NULL),
(21, 8, 'deposit', 10000.00, 13000.00, 'Deposit via Upi', 'completed', NULL, '2026-04-15 06:54:15', NULL),
(22, 8, 'deposit', 350000.00, 363000.00, 'Deposit via Paypal', 'completed', NULL, '2026-04-15 06:54:58', NULL),
(23, 8, 'deposit', 1000.00, 364000.00, 'Deposit via Bank_transfer', 'completed', NULL, '2026-04-15 20:18:52', NULL),
(24, 8, 'withdrawal', -1000.00, 363000.00, 'Bank withdrawal request', 'completed', NULL, '2026-04-15 20:21:16', NULL),
(25, 8, 'withdrawal', -1000.00, 362000.00, 'Bank withdrawal request | Withdrawal Rejected | Withdrawal Rejected', '', NULL, '2026-04-15 20:21:29', NULL),
(26, 8, 'withdrawal', -1500.00, 360500.00, 'UPI withdrawal request (test@upi) | Withdrawal Completed - sdfgdsfg', 'completed', NULL, '2026-04-15 20:21:45', NULL),
(27, 8, 'manual_debit', 27000.00, 333500.00, 'Investment in Gold Plan', 'completed', NULL, '2026-04-15 20:25:21', NULL),
(28, 8, 'manual_debit', 1200.00, 332300.00, 'Investment in Silver Plan', 'completed', NULL, '2026-04-15 20:25:46', NULL),
(29, 9, 'joining_bonus', 1200.00, 0.00, 'Joining bonus assigned (₹1200)', 'completed', NULL, '2026-04-15 20:27:28', NULL),
(30, 10, 'joining_bonus', 1200.00, 0.00, 'Joining bonus assigned (₹1200)', 'completed', NULL, '2026-04-15 20:29:00', NULL),
(31, 11, 'joining_bonus', 1200.00, 0.00, 'Joining bonus assigned (₹1200)', 'completed', NULL, '2026-04-15 20:30:32', NULL),
(32, 12, 'joining_bonus', 1200.00, 0.00, 'Joining bonus assigned (₹1200)', 'completed', NULL, '2026-04-15 20:31:23', NULL),
(33, 8, 'daily_cashback', 32.40, 332332.40, 'Daily Cashback @ 0.12%', 'completed', NULL, '2026-04-15 20:33:49', NULL),
(34, 8, 'daily_cashback', 1.20, 332333.60, 'Daily Cashback @ 0.1%', 'completed', NULL, '2026-04-15 20:33:49', NULL),
(35, 8, 'daily_cashback', 32.40, 332366.00, 'Daily Cashback @ 0.12%', 'completed', NULL, '2026-04-15 20:33:55', NULL),
(36, 8, 'daily_cashback', 1.20, 332367.20, 'Daily Cashback @ 0.1%', 'completed', NULL, '2026-04-15 20:33:55', NULL),
(37, 8, 'deposit', 500.00, 332867.20, 'Deposit via Stripe', 'completed', NULL, '2026-04-15 20:39:31', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `sponsor_id` int(11) DEFAULT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `city` varchar(50) DEFAULT NULL,
  `state` varchar(50) DEFAULT NULL,
  `bank_account` varchar(20) DEFAULT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `ifsc_code` varchar(20) DEFAULT NULL,
  `wallet_balance` decimal(15,2) DEFAULT 0.00,
  `total_cashback_earned` decimal(15,2) DEFAULT 0.00,
  `total_commission_earned` decimal(15,2) DEFAULT 0.00,
  `total_downline_business` decimal(15,2) DEFAULT 0.00,
  `active_investment` decimal(15,2) DEFAULT 0.00,
  `registration_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` datetime DEFAULT NULL,
  `status` enum('active','inactive','suspended') DEFAULT 'active',
  `role` enum('user','admin') DEFAULT 'user',
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `email`, `password`, `sponsor_id`, `first_name`, `last_name`, `phone`, `city`, `state`, `bank_account`, `bank_name`, `ifsc_code`, `wallet_balance`, `total_cashback_earned`, `total_commission_earned`, `total_downline_business`, `active_investment`, `registration_date`, `last_login`, `status`, `role`, `reset_token`, `reset_expires`) VALUES
(4, 'admin', 'admin@bachatpay.com', '$2y$10$gMb1YOZzjT/XHaH1ut6cP.tis3jBZBp3BJ9FjAPlsVyd4.h8zk51G', NULL, 'Test', 'Admin', '', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-04-13 05:11:23', '2026-04-16 14:08:30', 'active', 'admin', NULL, NULL),
(8, 'demouser', 'demo@bachatpay.com', '$2y$10$B8jQhSK1IW3hK7zsLaY9tuVGgJXJQzc41XHPSs1cgQ1.2Rfd1jNfG', NULL, 'Demo', 'User', '+919952145235', NULL, NULL, NULL, NULL, NULL, 332867.20, 67.20, 0.00, 0.00, 0.00, '2026-04-13 11:11:15', '2026-04-15 20:37:31', 'active', 'user', NULL, NULL),
(9, 'seconduser', 'candyluwolfs@gmail.com', '$2y$10$sHmjqro5Atwilet/ZxJ58OVQ44SWFOB6R/6/JNMMCEWaC0DMcC4ZW', 8, 'Candy', 'Wolf', '+919952145235', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-04-15 20:27:28', '2026-04-15 20:28:20', 'active', 'user', NULL, NULL),
(10, 'thirduser', 'candyluwolfss@gmail.com', '$2y$10$sQNGEdAZYCjMQVD3nvJBvOINnlC2z/1aSmofo660LBAX4r3P3wUEW', 9, 'Veeky', 'Kumar', '+919952145235', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-04-15 20:29:00', NULL, 'active', 'user', NULL, NULL),
(11, 'demouser2', 'candyluwolfsss@gmail.com', '$2y$10$uHsZvlz5ucUcqXf/xnwGve6dOhhaBceFvjfNVaQNPPAvsVVJ9MLeS', 8, 'Demo', 'User2', '+919952145235', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-04-15 20:30:32', NULL, 'active', 'user', NULL, NULL),
(12, 'demouser3', 'candyluwolfssss@gmail.com', '$2y$10$NndRP.hh7bG.Gt4TGwZuH.v3CHgYZ5iC1gIj/8raYDQ4kkxOyiYkW', 11, 'Demo', 'User3', '+919952145235', NULL, NULL, NULL, NULL, NULL, 0.00, 0.00, 0.00, 0.00, 0.00, '2026-04-15 20:31:23', NULL, 'active', 'user', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_badges`
--

CREATE TABLE `user_badges` (
  `user_badge_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `badge_id` int(11) NOT NULL,
  `earned_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_bank_accounts`
--

CREATE TABLE `user_bank_accounts` (
  `bank_account_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `account_holder_name` varchar(100) DEFAULT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `account_number` varchar(20) DEFAULT NULL,
  `ifsc_code` varchar(20) DEFAULT NULL,
  `upi_id` varchar(100) DEFAULT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_bank_accounts`
--

INSERT INTO `user_bank_accounts` (`bank_account_id`, `user_id`, `account_holder_name`, `bank_name`, `account_number`, `ifsc_code`, `upi_id`, `is_primary`, `created_at`, `updated_at`) VALUES
(3, 8, 'Candy Wolf', 'DEMO', '11111111111', 'HR00000FG', NULL, 1, '2026-04-13 11:11:46', '2026-04-15 20:40:11'),
(4, 8, 'test bank', 'test', '3333333333', 'HR00000FH', NULL, 0, '2026-04-15 20:20:24', '2026-04-15 20:40:11');

-- --------------------------------------------------------

--
-- Table structure for table `withdrawal_requests`
--

CREATE TABLE `withdrawal_requests` (
  `withdrawal_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `bank_account_id` int(11) DEFAULT NULL,
  `upi_id` varchar(100) DEFAULT NULL,
  `status` enum('pending','completed','rejected') DEFAULT 'pending',
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `method` enum('bank','upi') DEFAULT 'bank'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `withdrawal_requests`
--

INSERT INTO `withdrawal_requests` (`withdrawal_id`, `user_id`, `amount`, `bank_account_id`, `upi_id`, `status`, `requested_at`, `completed_at`, `notes`, `method`) VALUES
(3, 8, 1000.00, 3, NULL, 'completed', '2026-04-13 11:12:00', '2026-04-13 11:17:47', NULL, 'bank'),
(4, 8, 1000.00, NULL, 'sss.kkk', 'completed', '2026-04-13 12:03:45', '2026-04-13 12:05:12', NULL, 'upi'),
(5, 8, 1000.00, 4, NULL, 'rejected', '2026-04-15 20:21:16', '2026-04-15 20:23:51', NULL, 'bank'),
(6, 8, 1000.00, 3, NULL, 'rejected', '2026-04-15 20:21:29', '2026-04-15 20:23:26', NULL, 'bank'),
(7, 8, 1500.00, NULL, 'test@upi', 'completed', '2026-04-15 20:21:45', '2026-04-15 20:22:55', NULL, 'upi');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `badges`
--
ALTER TABLE `badges`
  ADD PRIMARY KEY (`badge_id`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indexes for table `bonus_1_tracking`
--
ALTER TABLE `bonus_1_tracking`
  ADD PRIMARY KEY (`bonus_1_id`),
  ADD UNIQUE KEY `monthly_tracking` (`user_id`,`tracked_month`),
  ADD KEY `idx_user` (`user_id`);

--
-- Indexes for table `bonus_2_tracking`
--
ALTER TABLE `bonus_2_tracking`
  ADD PRIMARY KEY (`bonus_2_id`),
  ADD UNIQUE KEY `weekly_tracking` (`user_id`,`week_start_date`),
  ADD KEY `idx_user` (`user_id`);

--
-- Indexes for table `daily_cashback`
--
ALTER TABLE `daily_cashback`
  ADD PRIMARY KEY (`cashback_id`),
  ADD UNIQUE KEY `unique_daily` (`user_id`,`cashback_date`),
  ADD KEY `investment_id` (`investment_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_date` (`cashback_date`);

--
-- Indexes for table `deposits`
--
ALTER TABLE `deposits`
  ADD PRIMARY KEY (`deposit_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_date` (`created_at`);

--
-- Indexes for table `investments`
--
ALTER TABLE `investments`
  ADD PRIMARY KEY (`investment_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `investment_plans`
--
ALTER TABLE `investment_plans`
  ADD PRIMARY KEY (`plan_id`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indexes for table `joining_bonus_tracker`
--
ALTER TABLE `joining_bonus_tracker`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `plan_investments`
--
ALTER TABLE `plan_investments`
  ADD PRIMARY KEY (`invest_id`),
  ADD KEY `plan_id` (`plan_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_date` (`start_date`);

--
-- Indexes for table `referrals`
--
ALTER TABLE `referrals`
  ADD PRIMARY KEY (`referral_id`),
  ADD UNIQUE KEY `unique_relationship` (`sponsor_id`,`member_id`),
  ADD KEY `idx_sponsor` (`sponsor_id`),
  ADD KEY `idx_member` (`member_id`),
  ADD KEY `idx_level` (`level`);

--
-- Indexes for table `support_replies`
--
ALTER TABLE `support_replies`
  ADD PRIMARY KEY (`reply_id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `idx_ticket` (`ticket_id`),
  ADD KEY `idx_date` (`created_at`);

--
-- Indexes for table `support_tickets`
--
ALTER TABLE `support_tickets`
  ADD PRIMARY KEY (`ticket_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_date` (`created_at`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`transaction_id`),
  ADD KEY `related_user_id` (`related_user_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_type` (`transaction_type`),
  ADD KEY `idx_date` (`transaction_date`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_sponsor` (`sponsor_id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_username` (`username`);

--
-- Indexes for table `user_badges`
--
ALTER TABLE `user_badges`
  ADD PRIMARY KEY (`user_badge_id`),
  ADD UNIQUE KEY `unique_user_badge` (`user_id`,`badge_id`),
  ADD KEY `badge_id` (`badge_id`),
  ADD KEY `idx_user` (`user_id`);

--
-- Indexes for table `user_bank_accounts`
--
ALTER TABLE `user_bank_accounts`
  ADD PRIMARY KEY (`bank_account_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_primary` (`is_primary`);

--
-- Indexes for table `withdrawal_requests`
--
ALTER TABLE `withdrawal_requests`
  ADD PRIMARY KEY (`withdrawal_id`),
  ADD KEY `bank_account_id` (`bank_account_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_date` (`requested_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `badges`
--
ALTER TABLE `badges`
  MODIFY `badge_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `bonus_1_tracking`
--
ALTER TABLE `bonus_1_tracking`
  MODIFY `bonus_1_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `bonus_2_tracking`
--
ALTER TABLE `bonus_2_tracking`
  MODIFY `bonus_2_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `daily_cashback`
--
ALTER TABLE `daily_cashback`
  MODIFY `cashback_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `deposits`
--
ALTER TABLE `deposits`
  MODIFY `deposit_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `investments`
--
ALTER TABLE `investments`
  MODIFY `investment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `investment_plans`
--
ALTER TABLE `investment_plans`
  MODIFY `plan_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `joining_bonus_tracker`
--
ALTER TABLE `joining_bonus_tracker`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `plan_investments`
--
ALTER TABLE `plan_investments`
  MODIFY `invest_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `referrals`
--
ALTER TABLE `referrals`
  MODIFY `referral_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `support_replies`
--
ALTER TABLE `support_replies`
  MODIFY `reply_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `support_tickets`
--
ALTER TABLE `support_tickets`
  MODIFY `ticket_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `transaction_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `user_badges`
--
ALTER TABLE `user_badges`
  MODIFY `user_badge_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `user_bank_accounts`
--
ALTER TABLE `user_bank_accounts`
  MODIFY `bank_account_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `withdrawal_requests`
--
ALTER TABLE `withdrawal_requests`
  MODIFY `withdrawal_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bonus_1_tracking`
--
ALTER TABLE `bonus_1_tracking`
  ADD CONSTRAINT `bonus_1_tracking_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `bonus_2_tracking`
--
ALTER TABLE `bonus_2_tracking`
  ADD CONSTRAINT `bonus_2_tracking_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `daily_cashback`
--
ALTER TABLE `daily_cashback`
  ADD CONSTRAINT `daily_cashback_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `daily_cashback_ibfk_2` FOREIGN KEY (`investment_id`) REFERENCES `investments` (`investment_id`) ON DELETE CASCADE;

--
-- Constraints for table `deposits`
--
ALTER TABLE `deposits`
  ADD CONSTRAINT `deposits_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `investments`
--
ALTER TABLE `investments`
  ADD CONSTRAINT `investments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `plan_investments`
--
ALTER TABLE `plan_investments`
  ADD CONSTRAINT `plan_investments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `plan_investments_ibfk_2` FOREIGN KEY (`plan_id`) REFERENCES `investment_plans` (`plan_id`);

--
-- Constraints for table `referrals`
--
ALTER TABLE `referrals`
  ADD CONSTRAINT `referrals_ibfk_1` FOREIGN KEY (`sponsor_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `referrals_ibfk_2` FOREIGN KEY (`member_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `support_replies`
--
ALTER TABLE `support_replies`
  ADD CONSTRAINT `support_replies_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `support_tickets` (`ticket_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `support_replies_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `support_tickets`
--
ALTER TABLE `support_tickets`
  ADD CONSTRAINT `support_tickets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `transactions_ibfk_2` FOREIGN KEY (`related_user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`sponsor_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `user_badges`
--
ALTER TABLE `user_badges`
  ADD CONSTRAINT `user_badges_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_badges_ibfk_2` FOREIGN KEY (`badge_id`) REFERENCES `badges` (`badge_id`) ON DELETE CASCADE;

--
-- Constraints for table `user_bank_accounts`
--
ALTER TABLE `user_bank_accounts`
  ADD CONSTRAINT `user_bank_accounts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `withdrawal_requests`
--
ALTER TABLE `withdrawal_requests`
  ADD CONSTRAINT `withdrawal_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `withdrawal_requests_ibfk_2` FOREIGN KEY (`bank_account_id`) REFERENCES `user_bank_accounts` (`bank_account_id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
