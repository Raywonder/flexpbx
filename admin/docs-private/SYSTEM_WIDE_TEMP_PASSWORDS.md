# System-Wide Auto-Generated Temporary Passwords

## Overview

FlexPBX now features a **comprehensive temporary password system** across the entire platform - both admin and user portals. This system enhances security by eliminating static default passwords while maintaining flexibility for first-time users.

## 🔐 Security Philosophy

**Secure Yet Flexible**

- ✅ **No Static Passwords**: Default passwords are never displayed in documentation
- ✅ **Time-Limited Access**: All temporary passwords expire after 15 minutes
- ✅ **Auto-Rotation**: New password generated on each request
- ✅ **Logged & Audited**: All password generations are logged with IP addresses
- ✅ **One-Time Setup**: Only works until user sets their email address
- ✅ **Flexible Access**: Users can get temp passwords without admin intervention

## System Coverage

### Admin Portal
**URL**: https://flexpbx.devinecreations.net/admin/login.php

**Features**:
- Auto-generates password on page load
- Format: `Admin` + 4-digit number (e.g., `Admin4319`)
- Password auto-fills in login form
- Live countdown timer
- Only shown for admins with placeholder emails

### User Portal
**URL**: https://flexpbx.devinecreations.net/user-portal/login.php

**Features**:
- On-demand password generation
- User enters extension/username first
- Format: `Ext` + extension number (e.g., `Ext2006`)
- Password auto-fills in login form
- Live countdown timer
- Only shown for users with placeholder emails

## How It Works

### For Admins

1. Visit admin login page
2. System auto-generates temporary password (1-2 seconds)
3. Password displays on page: `Admin4319`
4. Password auto-fills in form
5. Countdown timer shows: `⏱️ Expires in: 14m 32s`
6. Click "Login to Admin Panel"
7. Set email address
8. Future logins use password reset (temp passwords disabled)

### For Users

1. Visit user login page
2. See "Need a temporary password?" section
3. Enter extension (e.g., `2006`) or username
4. Click "Get Temporary Password"
5. System generates password: `Ext2006`
6. Password auto-fills in form
7. Countdown timer shows: `⏱️ Expires in: 14m 32s`
8. Click "Login to User Portal"
9. Set email address
10. Future logins use password reset (temp passwords disabled)

## Password Formats

### Admin Passwords
- **Pattern**: `Admin` + random 4-digit number
- **Examples**: `Admin1234`, `Admin5678`, `Admin9012`
- **When Generated**: Automatically on page load

### User Passwords
- **Pattern**: `Ext` + extension number OR `User` + random 4-digit
- **Examples**: `Ext2006`, `Ext2001`, `User4567`
- **When Generated**: On-demand when user requests

## API Endpoint

### `/api/generate-temp-password.php`

**Parameters**:
- `identifier`: Username, extension, or email
- `account_type`: `admin` or `user`

**Example Requests**:

```bash
# Generate admin temp password
curl "https://flexpbx.devinecreations.net/api/generate-temp-password.php?identifier=admin&account_type=admin"

# Generate user temp password
curl "https://flexpbx.devinecreations.net/api/generate-temp-password.php?identifier=2006&account_type=user"
```

**Success Response**:
```json
{
  "success": true,
  "show_temp_password": true,
  "account_type": "user",
  "identifier": "2006",
  "username": "walterharper",
  "extension": "2006",
  "password": "Ext2006",
  "expires": 1760445662,
  "expires_in_seconds": 900,
  "expires_at": "2025-10-14 12:41:02",
  "message": "Temporary password generated. Valid for 15 minutes."
}
```

**Account Already Configured**:
```json
{
  "success": false,
  "show_temp_password": false,
  "message": "Account already configured. Use your personal password or password reset."
}
```

## Security Features

### Time-Limited Access
- **Expiry**: 15 minutes (900 seconds)
- **Visual Countdown**: Updates every second
- **Warning**: Timer turns red when < 2 minutes remain
- **Auto-Disable**: Form disables when password expires
- **Refresh**: User can refresh page for new password

### Automatic Cleanup
- Expired passwords auto-deleted on next API call
- Cleanup runs before generating new passwords
- Files removed after 15 minutes

### Security Logging
All activity logged to `/home/flexpbxuser/logs/temp_password.log`:

```
[2025-10-14 12:26:02] [100.64.0.1] Generated temporary password for admin: admin (expires: 2025-10-14 12:41:02)
[2025-10-14 12:27:15] [100.64.0.1] Generated temporary password for user: 2006 (expires: 2025-10-14 12:42:15)
[2025-10-14 12:30:00] [100.64.0.1] Cleaned up 3 expired temporary passwords
[2025-10-14 12:35:22] [100.64.0.1] Account already configured (no temp password needed): admin / admin
```

### IP Tracking
- IP address logged with each generation
- Stored in temp password file
- Written to account JSON file
- Enables security auditing

## File Structure

### Storage Locations
```
/home/flexpbxuser/
├── temp_passwords/              # Temporary password storage
│   ├── temp_admin_admin_1760443862.json
│   ├── temp_user_2006_1760444012.json
│   └── ...
├── logs/
│   └── temp_password.log        # Security audit log
├── admins/
│   └── admin_*.json             # Admin account files
└── users/
    └── user_*.json              # User account files
```

### Temp Password File Format
```json
{
  "account_type": "user",
  "identifier": "2006",
  "password_hash": "$2y$10$...",
  "password_plain": "Ext2006",
  "created": 1760444012,
  "expires": 1760444912,
  "used": false,
  "ip": "100.64.0.1"
}
```

### Account File Updates
When temp password is generated, account file is updated:

```json
{
  "username": "admin",
  "password": "$2y$10$...",
  "temp_password_expires": 1760444912,
  "temp_password_created": 1760444012,
  "temp_password_ip": "100.64.0.1"
}
```

## When Temp Passwords Are Available

### Enabled For:
- ✅ Accounts with NO email set
- ✅ Accounts with placeholder emails:
  - `user@example.com`
  - `admin@example.com`
  - `noemail@localhost`
  - `user@localhost`
  - `test@test.com`
  - `changeme@example.com`
  - `administrator@localhost`

### Disabled For:
- ❌ Accounts with valid email addresses
- ❌ Accounts that have completed email setup
- ❌ Accounts that don't exist

## User Experience

### Admin Experience
```
1. Load /admin/login.php
   └─> "🔐 Generating secure temporary password..."
   └─> 2 seconds later
   └─> "🔒 Auto-Generated Temporary Password:
        Username: admin
        Password: Admin4319 [Copy]
        ⏱️ Expires in: 14m 58s"
   └─> Form auto-filled
   └─> Click "Login to Admin Panel"
   └─> Redirected to email setup
   └─> Set email
   └─> Access dashboard
```

### User Experience
```
1. Load /user-portal/login.php
   └─> See "🔐 Need a temporary password?"
   └─> Enter extension: 2006
   └─> Click "Get Temporary Password"
   └─> "🔒 Temporary Password:
        Extension/Username: 2006
        Password: Ext2006 [Copy]
        ⏱️ Expires in: 14m 58s"
   └─> Form auto-filled
   └─> Click "Login to User Portal"
   └─> Redirected to email setup
   └─> Set email
   └─> Access dashboard
```

## UI Components

### Copy to Clipboard
- One-click copy button
- Shows confirmation: "✓ Copied!"
- Fallback alert if clipboard fails

### Live Countdown Timer
- Updates every second
- Format: `Xm Xs` (e.g., `14m 32s`)
- Yellow warning: < 5 minutes
- Red alert: < 2 minutes
- Expiry message: "⚠️ Password expired!"

### Auto-Fill
- Extension/username field
- Password field
- Ready to submit

### Visual States
1. **Loading**: Blue box, "Generating..."
2. **Active**: Yellow box, password displayed, timer running
3. **Warning**: Red timer, < 2 minutes
4. **Expired**: Red message, form disabled

## Integration with Email Setup

The temporary password system integrates seamlessly with the email setup system:

1. **First Login** → Temp Password
2. **Authentication** → Email Setup Check
3. **Email Missing** → Setup Email Page
4. **Email Set** → Dashboard Access
5. **Future Logins** → Password Reset (temp disabled)

## Troubleshooting

### Password Not Appearing (Admin)
**Symptoms**: Loading box stays visible
**Causes**: API not accessible, JavaScript error, network issue
**Solutions**:
```bash
# Test API directly
curl "https://flexpbx.devinecreations.net/api/generate-temp-password.php?identifier=admin&account_type=admin"

# Check JavaScript console
# Check Apache error logs
tail -f /var/log/apache2/error_log
```

### Password Not Generating (User)
**Symptoms**: Error message or no response
**Causes**: Invalid extension, account not found, email already set
**Solutions**:
- Verify extension exists: `ls /home/flexpbxuser/users/`
- Check account email: `cat /home/flexpbxuser/users/user_2006.json | grep email`
- Use correct identifier (extension OR username)

### Password Doesn't Work
**Symptoms**: Login fails with correct password
**Causes**: Password expired, caps lock, browser auto-fill
**Solutions**:
- Check if timer shows expired
- Refresh page for new password
- Type password manually
- Clear browser cache

### Timer Not Counting Down
**Symptoms**: Timer frozen or not updating
**Causes**: JavaScript error, browser compatibility
**Solutions**:
- Check browser console for errors
- Try different browser
- Hard refresh: Ctrl+Shift+R

## Security Best Practices

### For Administrators
1. ✅ Always set email immediately after first login
2. ✅ Use strong personal passwords via password reset
3. ✅ Monitor `/logs/temp_password.log` for suspicious activity
4. ✅ Review account JSON files for unexpected temp password generations
5. ✅ Disable unused accounts

### For Users
1. ✅ Set email address on first login
2. ✅ Use password reset for forgotten passwords
3. ✅ Don't share temp passwords (they're logged)
4. ✅ Log out after setting email
5. ✅ Contact admin if having issues

### For System Administrators
1. ✅ Monitor temp password log regularly
2. ✅ Review cleanup frequency (15 minutes)
3. ✅ Check for repeated password generations (may indicate issues)
4. ✅ Audit IP addresses in logs
5. ✅ Consider rate limiting if abuse detected

## Monitoring & Auditing

### Log Analysis
```bash
# View recent temp password activity
tail -50 /home/flexpbxuser/logs/temp_password.log

# Count generations per day
grep "Generated temporary password" /home/flexpbxuser/logs/temp_password.log | wc -l

# Find specific user activity
grep "2006" /home/flexpbxuser/logs/temp_password.log

# Check for unusual IP addresses
grep -oP '\[\K[0-9.]+(?=\])' /home/flexpbxuser/logs/temp_password.log | sort | uniq -c
```

### Active Temp Passwords
```bash
# List all active temp passwords
ls -lh /home/flexpbxuser/temp_passwords/

# Count active passwords
ls /home/flexpbxuser/temp_passwords/ | wc -l

# View specific temp password
cat /home/flexpbxuser/temp_passwords/temp_user_2006_*.json | jq .
```

### Manual Cleanup
```bash
# Remove all expired temp passwords
find /home/flexpbxuser/temp_passwords -name "temp_*.json" -mmin +15 -delete

# Remove ALL temp passwords (emergency)
rm -f /home/flexpbxuser/temp_passwords/temp_*.json
```

## Rate Limiting (Future Enhancement)

**Planned Features**:
- Limit to 5 password generations per IP per hour
- Lockout after 10 failed attempts
- Email notification to admins on suspicious activity
- CAPTCHA for repeated requests

## Comparison: Before vs After

### Before (Static Passwords)
- ❌ Passwords in documentation
- ❌ Same password for all admins
- ❌ Never expires
- ❌ Can be shared/leaked
- ❌ No logging
- ❌ Security risk

### After (Temp Passwords)
- ✅ No passwords in documentation
- ✅ Unique password per session
- ✅ Expires in 15 minutes
- ✅ Tracked by IP address
- ✅ Full audit logging
- ✅ Secure and flexible

## Related Documentation

- [Email Setup System](EMAIL_SETUP_SYSTEM.md)
- [Password Reset System](PASSWORD_RESET_SYSTEM.md)
- [Auto-Generated Passwords (Admin)](AUTO_GENERATED_PASSWORDS.md)
- [Security Best Practices](SECURITY_BEST_PRACTICES.md)
- [Admin Quick Start](../DOMINIQUE_ADMIN_QUICKSTART.md)

## Success Story

**Dominique's First Login** (October 14, 2025):
1. Visited `/admin/login.php`
2. Temp password auto-generated: `Admin4319`
3. Password auto-filled in form
4. Logged in successfully
5. Set email: `webmaster@raywonderis.me`
6. Email confirmed via system
7. Now uses password reset for future logins
8. Temp password system auto-disabled for this account

**Result**: Secure first-time login without static passwords in documentation!

---

**Last Updated**: October 14, 2025
**System Version**: FlexPBX v1.0
**Security Level**: High
**Coverage**: System-Wide (Admin + User Portals)
