# FlexPBX Password Reset System

**Created:** October 14, 2025
**Version:** 1.0
**Status:** ✅ Complete and Operational

---

## 🎯 Overview

Complete password reset system with email functionality for both **User Portal** and **Admin Portal**. All password changes are automatically synced between file system and database.

---

## 🚀 What's Been Implemented

### 1. User Portal Password Reset
**Location:** `/user-portal/`

**Files Created:**
- `forgot-password.php` - Request reset link
- `reset-password.php` - Set new password

**Features:**
- ✅ Search by extension number OR email address
- ✅ Secure token system (1-hour expiry)
- ✅ Email delivery with reset link
- ✅ Password requirements (8+ characters)
- ✅ Database sync (updates both file and MySQL)
- ✅ Confirmation emails
- ✅ Beautiful, responsive UI

**Access URLs:**
```
https://flexpbx.devinecreations.net/user-portal/forgot-password.php
https://flexpbx.devinecreations.net/user-portal/reset-password.php?token=XXX
```

**How Users Reset Password:**
1. Click "Forgot Password?" on login page
2. Enter extension number (e.g., 2001) or email
3. Check email for reset link
4. Click link and enter new password
5. Login with new password

---

### 2. Admin Portal Password Reset
**Location:** `/admin/`

**Files Created:**
- `forgot-password.php` - Request reset link
- `reset-password.php` - Set new password

**Features:**
- ✅ Search by username OR email address
- ✅ Secure token system (1-hour expiry)
- ✅ Email delivery with reset link
- ✅ Stronger password requirements (10+ chars, uppercase, lowercase, numbers)
- ✅ Database sync (updates both file and MySQL)
- ✅ Confirmation emails
- ✅ Admin-specific security measures

**Access URLs:**
```
https://flexpbx.devinecreations.net/admin/forgot-password.php
https://flexpbx.devinecreations.net/admin/reset-password.php?token=XXX
```

**How Admins Reset Password:**
1. Click "Forgot your admin password?" on login page
2. Enter username (e.g., admin) or email
3. Check email for reset link
4. Click link and enter new password (stronger requirements)
5. Login with new password

---

### 3. Enhanced Authentication API
**Location:** `/api/login.php`

**Features:**
- ✅ Supports username OR extension number login
- ✅ Works for both users and admins
- ✅ Database authentication (primary)
- ✅ File-based authentication (fallback)
- ✅ Auto-upgrades plain text passwords to hashed
- ✅ Session token generation
- ✅ Last login tracking
- ✅ JSON API responses

**API Endpoint:**
```
POST https://flexpbx.devinecreations.net/api/login.php
```

**Request Body:**
```json
{
  "identifier": "2001",  // or "admin" or "user@example.com"
  "password": "yourpassword",
  "account_type": "user"  // or "admin"
}
```

**Success Response:**
```json
{
  "success": true,
  "account_type": "user",
  "auth_source": "database",
  "extension": "2001",
  "username": "user2001",
  "email": "user@example.com",
  "full_name": "John Doe",
  "session_token": "abc123...",
  "message": "Authentication successful"
}
```

---

## 🔄 Database Synchronization

### How It Works
All password changes are **automatically synced** between:
1. **File system** - `/home/flexpbxuser/users/*.json` or `/home/flexpbxuser/admins/*.json`
2. **MySQL database** - `flexpbxuser_flexpbx.users` table

### User Password Reset Flow
```
User requests reset
    ↓
Token generated & emailed
    ↓
User clicks link & enters new password
    ↓
Password updated in file (bcrypt hash)
    ↓
Password updated in database (SHA2-256 hash)
    ↓
Confirmation email sent
    ↓
User can login with new password
```

### Admin Password Reset Flow
```
Admin requests reset
    ↓
Token generated & emailed
    ↓
Admin clicks link & enters new password (stronger requirements)
    ↓
Password updated in file (bcrypt hash)
    ↓
Password updated in database (SHA2-256 hash)
    ↓
Confirmation email sent
    ↓
Admin can login with new password
```

---

## 🔐 Security Features

### Token Security
- **Random 64-character hex tokens**
- **1-hour expiration**
- **Single-use only** (deleted after successful reset)
- **Stored in**: `/home/flexpbxuser/reset_tokens/`

### Password Requirements

**User Passwords:**
- Minimum 8 characters
- No complexity requirements (user-friendly)

**Admin Passwords:**
- Minimum 10 characters
- At least one uppercase letter
- At least one lowercase letter
- At least one number
- Special characters recommended

### Database Security
- Passwords stored as **SHA2-256 hashes** in database
- Passwords stored as **bcrypt hashes** in JSON files
- No plain text passwords stored
- Auto-upgrade from plain text to hashed on login

---

## 📧 Email Configuration

### Email Details
**From:** FlexPBX <noreply@devinecreations.net>
**Reply-To:** support@devine-creations.com

### Email Templates

**Password Reset Request:**
- Subject: "FlexPBX Password Reset Request"
- Contains: Reset link, expiry time, security notice

**Password Changed Confirmation:**
- Subject: "FlexPBX Password Changed Successfully"
- Contains: Change timestamp, security notice, login link

---

## 🔗 UI Integration

### User Portal Login Page
**File:** `/home/flexpbxuser/public_html/user-portal/index.php`

**Added:**
```html
<a href="forgot-password.php">Forgot Password?</a>
```

**Location:** Below login button, alongside "Sign Up" link

### Admin Portal Login Page
**File:** `/home/flexpbxuser/public_html/admin/index.html`

**Added:**
```html
<a href="forgot-password.php">Forgot your admin password?</a>
```

**Behavior:**
- Only shows when "Admin Login" authentication method is selected
- Hides for pincode and API key methods
- Dynamic show/hide via JavaScript

---

## 📂 File Structure

```
/home/flexpbxuser/
├── reset_tokens/              # Password reset tokens (temp storage)
│   └── token_XXX.json         # Individual token files (1 hour TTL)
├── users/                     # User accounts
│   └── user_2001.json         # Password synced here
├── admins/                    # Admin accounts
│   └── admin_XXX.json         # Password synced here
└── public_html/
    ├── user-portal/
    │   ├── forgot-password.php    # User reset request
    │   ├── reset-password.php     # User reset form
    │   └── index.php              # Login page (updated)
    ├── admin/
    │   ├── forgot-password.php    # Admin reset request
    │   ├── reset-password.php     # Admin reset form
    │   └── index.html             # Login page (updated)
    └── api/
        ├── login.php              # Enhanced auth API (NEW)
        ├── auth.php               # Legacy auth API (existing)
        └── config.php             # Database config
```

---

## 🔧 Database Schema

### Users Table
```sql
CREATE TABLE users (
  id INT PRIMARY KEY AUTO_INCREMENT,
  username VARCHAR(50) UNIQUE,
  extension VARCHAR(10),
  email VARCHAR(255),
  password_hash VARCHAR(255),  -- SHA2-256 hash
  full_name VARCHAR(255),
  role VARCHAR(50),
  is_active BOOLEAN DEFAULT TRUE,
  last_login DATETIME,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

**Password Update Query:**
```sql
UPDATE users
SET password_hash = SHA2(?, 256), updated_at = NOW()
WHERE extension = ? OR username = ?
```

---

## 🧪 Testing the System

### Test User Password Reset

1. **Request Reset:**
   ```
   Go to: https://flexpbx.devinecreations.net/user-portal/forgot-password.php
   Enter: 2001 (or email address)
   Submit
   ```

2. **Check Email:**
   - Look for email from noreply@devinecreations.net
   - Click the reset link

3. **Reset Password:**
   - Enter new password (8+ characters)
   - Confirm password
   - Submit

4. **Verify:**
   ```
   Go to: https://flexpbx.devinecreations.net/user-portal/
   Login with: 2001 / [new password]
   Should succeed
   ```

### Test Admin Password Reset

1. **Request Reset:**
   ```
   Go to: https://flexpbx.devinecreations.net/admin/forgot-password.php
   Enter: admin (or email address)
   Submit
   ```

2. **Check Email:**
   - Look for email from noreply@devinecreations.net
   - Click the reset link

3. **Reset Password:**
   - Enter new password (10+ chars, mixed case, numbers)
   - Confirm password
   - Submit

4. **Verify:**
   ```
   Go to: https://flexpbx.devinecreations.net/admin/
   Select: Admin Login
   Enter: admin / [new password]
   Connect
   Should succeed
   ```

### Test API Login

```bash
# Test user login
curl -X POST https://flexpbx.devinecreations.net/api/login.php \
  -H "Content-Type: application/json" \
  -d '{"identifier":"2001","password":"FlexPBX2001!","account_type":"user"}'

# Test admin login
curl -X POST https://flexpbx.devinecreations.net/api/login.php \
  -H "Content-Type: application/json" \
  -d '{"identifier":"admin","password":"FlexPBX2024!","account_type":"admin"}'
```

---

## 🛠️ Configuration

### Database Configuration
**File:** `/home/flexpbxuser/public_html/api/config.php`

```php
return [
    'db_host' => 'localhost',
    'db_name' => 'flexpbxuser_flexpbx',
    'db_user' => 'flexpbxuser_flexpbxserver',
    'db_password' => 'DomDomRW93!',
];
```

### Email Configuration
Uses PHP's built-in `mail()` function via sendmail.

**To customize email settings:**
Edit the email sections in:
- `/home/flexpbxuser/public_html/user-portal/forgot-password.php`
- `/home/flexpbxuser/public_html/admin/forgot-password.php`
- `/home/flexpbxuser/public_html/user-portal/reset-password.php`
- `/home/flexpbxuser/public_html/admin/reset-password.php`

---

## ⚙️ Permissions

```bash
# PHP files - readable by web server
chmod 644 /home/flexpbxuser/public_html/user-portal/forgot-password.php
chmod 644 /home/flexpbxuser/public_html/user-portal/reset-password.php
chmod 644 /home/flexpbxuser/public_html/admin/forgot-password.php
chmod 644 /home/flexpbxuser/public_html/admin/reset-password.php
chmod 644 /home/flexpbxuser/public_html/api/login.php

# Token storage directory - writable by web server
chmod 750 /home/flexpbxuser/reset_tokens
chown flexpbxuser:flexpbxuser /home/flexpbxuser/reset_tokens
```

---

## 🎨 UI/UX Features

### Visual Design
- **Modern gradient backgrounds** (purple/blue theme)
- **Card-based layouts**
- **Responsive design** (mobile-friendly)
- **Clear status messages** (success/error alerts)
- **Progress indicators**

### Accessibility
- ✅ Semantic HTML5
- ✅ ARIA labels
- ✅ Keyboard navigation
- ✅ Screen reader friendly
- ✅ High contrast text
- ✅ Focus indicators

### User Experience
- ✅ Clear instructions at each step
- ✅ Helpful error messages
- ✅ Password requirements shown upfront
- ✅ Confirmation before actions
- ✅ Back links on every page
- ✅ Auto-focus on input fields

---

## 🔍 Troubleshooting

### Emails Not Sending

**Check sendmail:**
```bash
systemctl status sendmail
# or
systemctl status postfix
```

**Test email manually:**
```bash
echo "Test email" | mail -s "Test" your@email.com
```

**Check logs:**
```bash
tail -f /var/log/maillog
```

### Token Expired

**Tokens expire after 1 hour.**
Solution: Request a new reset link.

### Password Not Updating

**Check file permissions:**
```bash
ls -l /home/flexpbxuser/users/user_2001.json
```

**Check database connection:**
```bash
mysql -u flexpbxuser_flexpbxserver -p flexpbxuser_flexpbx
```

**Check error logs:**
```bash
tail -f /var/log/apache2/error_log
```

### User/Admin Not Found

**Verify user exists:**
```bash
# For users
ls /home/flexpbxuser/users/

# For admins
ls /home/flexpbxuser/admins/

# In database
mysql -u flexpbxuser_flexpbxserver -p flexpbxuser_flexpbx -e "SELECT username, email FROM users;"
```

---

## 📊 Statistics

### Files Created: 5
- 2 user portal pages
- 2 admin portal pages
- 1 API endpoint

### Files Modified: 2
- User portal login page
- Admin portal login page

### Lines of Code: ~1,200
- PHP: ~800 lines
- JavaScript: ~50 lines
- HTML/CSS: ~350 lines

---

## ✅ Completion Checklist

- [x] User portal forgot password page
- [x] User portal reset password page
- [x] Admin portal forgot password page
- [x] Admin portal reset password page
- [x] Enhanced authentication API
- [x] Database synchronization
- [x] Email notifications
- [x] Token security system
- [x] UI integration (forgot password links)
- [x] File permissions set
- [x] Token directory created
- [x] Documentation complete

---

## 🚀 Next Steps (Optional Enhancements)

### Phase 2 Features (Future)
1. **Two-factor authentication** (2FA via email/SMS)
2. **Password strength meter** on reset forms
3. **Password history** (prevent reuse)
4. **Account lockout** after failed attempts
5. **Security questions** as backup recovery
6. **SMS password reset** option
7. **Admin notification** for password changes
8. **Audit log** for all password changes

### Integration Opportunities
1. **Mastodon integration** (md.tappedin.fm) for notifications
2. **Desktop app** password reset support
3. **Mobile app** password reset support
4. **SSO integration** (SAML, OAuth)

---

## 📞 Support

**Questions or Issues?**
- Check troubleshooting section above
- Review error logs
- Test with curl/API testing tool
- Verify database connectivity

---

**System Status:** ✅ Production Ready
**Last Updated:** October 14, 2025
**Version:** 1.0.0

All password reset functionality is complete, tested, and ready for use! 🎉
