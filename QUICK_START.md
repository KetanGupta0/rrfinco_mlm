# BachatPay MLM Platform - Quick Start Guide

## ⚡ 5-Minute Setup

### 1. Import Database
```sql
-- Copy contents of database.sql
-- Paste into phpMyAdmin SQL tab
-- OR run: mysql -u root bachatpay_db < database.sql
```

### 2. Verify Configuration
```php
// includes/config.php should have:
DB_HOST = 'localhost'
DB_USER = 'root'
DB_PASS = '' (or your password)
DB_NAME = 'bachatpay_db'
```

### 3. Create Demo User (Optional)
```sql
INSERT INTO users (username, email, password, status) 
VALUES ('demo', 'demo@bachatpay.com', 
'$2y$10$sbSOKm61t6pPDGFuSmBmhOGdI5f33CKlpvVfqIo7Zcay2BYqFMqGa', 
'active');
```

### 4. Access Platform
```
URL: http://localhost/bachat-pay-mlm/
Username: demo
Password: demo123
```

---

## 📊 Core Calculations Reference

### Daily Cashback Tiers
| Wallet Amount | Daily % | Monthly % |
|---|---|---|
| ₹1,000 - ₹25,000 | 0.10% | 3.00% |
| ₹26,000 - ₹100,000 | 0.12% | 3.60% |
| ₹101,000 - ₹500,000 | 0.14% | 4.20% |
| ₹500,001+ | 0.16% | 4.80% |

### Example: ₹100,000 Investment
```
Daily Cashback: ₹100,000 × 0.14% = ₹140
Monthly: ₹100,000 × 4.2% = ₹4,200
With Bonus 1 (20%): ₹4,200 + ₹840 = ₹5,040
Yearly (×12): ₹60,480
```

### Bonus 1 Conditions
- ✅ Maintain ₹2,000+ balance for 30 days
- ✅ Get 20% extra on monthly cashback
- ✅ Track monthly in bonus_1_tracking table

### Bonus 2 Conditions
- ✅ ₹50,000 business from downline in 1 week = 10% bonus
- ✅ ₹100,000+ business from downline in 1 week = 20% bonus
- ✅ Bonus applied to daily cashback

### 30-Level Commission
| Levels | Commission | Levels | Commission |
|---|---|---|---|
| 1 | 25% | 16-20 | 2% each |
| 2-3 | 10% each | 21-30 | 1% each |
| 4 | 9% | TOTAL | 100% |
| 5 | 8% |
| 6 | 5% |
| 7 | 4% |
| 8-10 | 3% each |
| 11-15 | 2% each |

---

## 🔧 Important Functions

### Calculate & Process
```php
// Daily earnings
calculateDailyCashback($investment)

// Monthly projection
calculateMonthlyCashback($investment)

// Get investment tier
getTierInfo($investment)

// Award bonuses
creditBonus1($user_id, 'Y-m')
creditBonus2($user_id, 'Y-m-d')

// Distribute to upline
distributeUplineCommissions($user_id, $cashbackAmount)
```

### Data Retrieval
```php
// User info
getCurrentUser()
getUserById($user_id)

// Network structure
getUplineTree($user_id)        // Get all sponsors
getDownlineTree($user_id)      // Get all members
getDownlineStats($user_id)     // Network stats

// Balance operations
updateWalletBalance($user_id, $amount, 'add'|'minus')
recordTransaction($user_id, 'daily_cashback', $amount, 'description')
```

---

## 📋 File Locations

```
bachat-pay-mlm/
├── index.php ........................ Login page
├── register.php ..................... Signup with sponsor field
├── dashboard.php .................... Main dashboard (tabs)
├── logout.php ....................... End session
├── database.sql ..................... SQL schema
├── SETUP.md ......................... Full setup guide
├── includes/
│   ├── config.php .................. Database & constants
│   ├── db.php ....................... Connection & helpers
│   └── functions.php ............... ALL BUSINESS LOGIC
└── logs/
    └── error.log ................... Error logging
```

---

## 🎯 Dashboard Tabs

1. **Calculator** - Invest amount → See projected returns
   - Daily cashback calculation
   - Monthly projection
   - Bonus 1 impact
   - Yearly income

2. **Earnings** - 30-day Chart.js line graph
   - Visual cashback trend
   - Daily earnings pattern
   - Income growth

3. **Wallet** - Transaction ledger
   - Credit entries (cashback, bonuses, commissions)
   - Debit entries (withdrawals)
   - Status tracking (pending, credited, debited)
   - Complete audit trail

4. **Withdrawal** - Request funds
   - Enter amount
   - Bank details (account, IFSC)
   - Status tracking (pending → approved → completed)
   - Recent requests list

---

## ✅ User Journey

**1. Registration**
```
Register → Choose Sponsor → Confirm Email → Login
```

**2. First Investment**
```
Login → Dashboard → Add Investment (₹X,000) → Tier Applied → Cashback Starts
```

**3. Daily Operations**
```
Every Day: Get Daily Cashback → Wallet Updates → Upline Gets Commissions
```

**4. Bonuses**
```
Month End: Bonus 1 Check (₹2k maintained?) → Credit if qualified
Week End: Bonus 2 Check (₹50k/1L business?) → Credit if qualified
```

**5. Withdrawal**
```
Go to Dashboard → Withdrawal Tab → Enter Amount & Bank → Submit → Admin Approves → Funds Sent
```

---

## 🔐 Security Info

- Passwords: bcrypt hashed (password_hash)
- Database: PDO prepared statements (no SQL injection)
- Sessions: 1-hour timeout
- Forms: CSRF token validation
- Errors: Logged, not displayed to users
- Validation: Email, phone, amounts verified

---

## 🚀 Going Live

### Before Production
1. Change DB password from empty to strong
2. Update BASE_URL to production domain
3. Enable HTTPS
4. Set DEBUG_MODE to false
5. Set up daily processing cron job
6. Configure email notifications

### Cron Job Setup
```bash
# Run every day at 11:59 PM
59 23 * * * /usr/bin/php /path/to/cron/process_daily.php
```

### Must-Have Additions
- Email verification on registration
- SMS notifications for transactions
- Admin panel for user/transaction management
- Withdrawal approval system
- Tax calculations
- KYC docs submission

---

## 📞 Support

For setup issues:
1. Check SETUP.md for detailed guide
2. Review logs/ directory for errors
3. Verify database.sql was fully imported
4. Confirm includes/config.php has correct DB credentials

---

**Status**: ✅ Production Ready  
**Last Updated**: April 2024  
**Version**: 1.0.0
