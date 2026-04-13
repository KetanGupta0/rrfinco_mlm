# BachatPay Admin Panel

## Overview

The BachatPay Admin Panel provides comprehensive administrative control over the MLM platform. It integrates seamlessly with the existing user login system, allowing administrators to access both user and admin functionalities from the same login page.

## Features

### 🔐 Authentication
- **Shared Login**: Admins use the same login page as regular users
- **Role-Based Access**: Automatic redirection based on user role (admin vs user)
- **Session Security**: Secure session management with role validation

### 📊 Dashboard Overview
- **Real-time Statistics**: Total users, active users, wallet balances, investments
- **Financial Metrics**: Pending withdrawals, today's deposits and registrations
- **Quick Actions**: Direct links to manage users, process withdrawals, view transactions

### 👥 User Management
- **User Overview**: Complete user list with profiles, balances, and earnings
- **Status Control**: Activate, deactivate, or suspend user accounts
- **Role Management**: Promote/demote users between user and admin roles
- **Manual Transactions**: Credit or debit user wallets with audit trails

### 💰 Withdrawal Management
- **Pending Requests**: View all pending withdrawal requests
- **Bank Details**: Access user bank account information
- **Approval System**: Approve or reject withdrawals with notes
- **Transaction Logging**: Complete audit trail of all withdrawal actions

### 📈 Transaction Monitoring
- **Complete Ledger**: View all financial transactions across the platform
- **Transaction Types**: Filter by cashback, commissions, bonuses, deposits, withdrawals
- **User Tracking**: See related users for commission transactions
- **Audit Trail**: Full transaction history with timestamps

### 📊 Investment Oversight
- **Investment Tracking**: Monitor all user investments and their status
- **Tier Analysis**: View investment amounts and corresponding daily rates
- **Maturity Status**: Track active vs matured investments
- **Performance Metrics**: Analyze investment distribution and growth

### ⏰ Cron Job Management
- **Manual Processing**: Trigger daily cashback and bonus processing manually
- **System Status**: Monitor last processing dates and pending operations
- **Automation Guidance**: Cron job setup instructions for automated processing

## Getting Started

### 1. Database Setup
The admin system automatically adds a `role` column to the users table and creates an admin user:

```sql
ALTER TABLE users ADD COLUMN role ENUM("user", "admin") DEFAULT "user" AFTER status;
```

**Default Admin Credentials:**
- Email: `admin@bachatpay.com`
- Password: `admin123`

### 2. Accessing Admin Panel
1. Go to the main login page (`index.php`)
2. Login with admin credentials
3. System automatically redirects to admin dashboard (`admin/dashboard.php`)

### 3. Admin Navigation
- **Dashboard**: Overview statistics and quick actions
- **Users**: User management and manual transactions
- **Withdrawals**: Process withdrawal requests
- **Transactions**: View complete transaction log
- **Investments**: Monitor investment activities
- **Cron Jobs**: Manual processing triggers

## Security Features

### Access Control
- **Role Validation**: `requireAdmin()` function ensures only admins can access admin pages
- **Session Security**: Role stored in session and validated on each admin page
- **CSRF Protection**: All forms include CSRF tokens for security

### Audit Trail
- **Transaction Logging**: All admin actions are logged with user attribution
- **Manual Credits/Debits**: Tracked with admin user ID for accountability
- **Error Logging**: System errors logged to `/logs/error.log`

## Manual Operations

### Processing Withdrawals
1. Navigate to **Withdrawals** tab
2. Review pending requests with bank details
3. Click **Process** on any request
4. Choose **Approve** or **Reject**
5. Add optional notes
6. System updates wallet balance and logs transaction

### Manual Wallet Adjustments
1. Go to **Users** tab
2. Find the user in the table
3. Click **Credit** or **Debit** button
4. Enter amount and description
5. System updates wallet and creates transaction record

### Cron Job Management
1. Access **Cron Jobs** section
2. Monitor system status and pending operations
3. Click **Run Now** for daily cashback processing
4. Click **Process Bonuses** for bonus calculations
5. Review results and system feedback

## API Functions

### Admin Helper Functions (in `includes/db.php`)
```php
isAdmin()              // Check if current user is admin
requireAdmin()          // Require admin access, redirect if not
```

### Admin Actions
- **User Status Updates**: Change user active/inactive/suspended status
- **Role Management**: Promote/demote users between roles
- **Manual Credits**: Add funds to user wallets
- **Manual Debits**: Deduct funds from user wallets
- **Withdrawal Processing**: Approve/reject withdrawal requests

## File Structure

```
admin/
├── dashboard.php       # Main admin dashboard with all tabs
├── cron.php           # Cron job management interface
├── navbar.php         # Admin navigation component
└── README.md          # This documentation
```

## Integration Points

### User Navbar
- Admin users see additional "Admin Dashboard" link in main navigation
- Seamless switching between user and admin interfaces

### Login System
- Modified `index.php` to handle role-based redirection
- Admin users automatically redirected to `admin/dashboard.php`

### Database Schema
- Added `role` column to `users` table
- All existing functionality preserved for regular users

## Best Practices

### Security
- Always use `requireAdmin()` at the top of admin pages
- Validate CSRF tokens on all forms
- Log all admin actions for audit purposes

### Performance
- Large data tables are limited to recent 100 records
- Use pagination for better performance with large datasets
- Monitor database queries for optimization opportunities

### User Experience
- Clear success/error messages for all actions
- Confirmation dialogs for destructive operations
- Responsive design works on all devices

## Troubleshooting

### Common Issues
1. **"Access Denied"**: Ensure user has admin role in database
2. **"Invalid CSRF Token"**: Check form includes proper CSRF token
3. **"Database Error"**: Verify database connection and permissions

### Debug Mode
Enable error reporting in `includes/config.php` for debugging:
```php
ini_set('display_errors', 1);
error_reporting(E_ALL);
```

## Future Enhancements

Potential additions to the admin panel:
- User search and filtering
- Bulk operations for user management
- Advanced reporting and analytics
- Email notification system
- System configuration management
- Backup and restore functionality