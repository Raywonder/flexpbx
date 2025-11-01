# FlexPBX Email Setup System

## Overview

The FlexPBX email setup system ensures that all users and administrators have a valid email address configured for password resets, notifications, and system alerts.

## Features

- **First-Time Login Detection**: Automatically detects if email is missing or has a placeholder value
- **Interstitial Email Setup**: Prompts users to set their email before accessing the dashboard
- **API Integration**: Returns `email_setup_required` flag in API responses
- **Dual Storage**: Saves email to both JSON files and MySQL database
- **Email Confirmation**: Sends confirmation email after setting email address

## How It Works

### For Users (Extensions)

1. User logs in with extension number, username, or existing email
2. System checks if email is set and valid
3. If email is missing or placeholder:
   - Redirect to `/user-portal/setup-email.php`
   - User enters and confirms email address
   - System validates and saves email
   - User is redirected to dashboard
4. If email is already set:
   - User proceeds directly to dashboard

### For Administrators

1. Admin logs in with username or email
2. System checks if email is set and valid
3. If email is missing or placeholder:
   - Redirect to `/admin/setup-email.php`
   - Admin enters and confirms email address
   - System validates and saves email
   - Admin is redirected to dashboard
4. If email is already set:
   - Admin proceeds directly to dashboard

## Placeholder Email Detection

The system detects the following as placeholder/invalid emails:

- Empty/null values
- `user@example.com`
- `admin@example.com`
- `noemail@localhost`
- `user@localhost`
- `test@test.com`
- `changeme@example.com`
- `administrator@localhost`

## API Integration

### Login Endpoint (`/api/login.php`)

When authenticating via API, the response includes:

```json
{
  "success": true,
  "account_type": "user",
  "email_setup_required": true,
  "setup_url": "/user-portal/setup-email.php",
  "message": "Authentication successful. Please set your email address."
}
```

### Email Setup Required Flag

- `email_setup_required`: Boolean indicating if email setup is needed
- `setup_url`: URL to the email setup page
- `message`: Helpful message for the client application

Client applications should check this flag and prompt the user to set their email before proceeding.

## Admin Account Setup - For Dominique

### First-Time Admin Login

**Username**: `admin`
**Password**: `FlexPBX2024!Admin`
**Login URL**: https://flexpbx.devinecreations.net/admin/login.php

### Steps to Set Your Email

1. Go to https://flexpbx.devinecreations.net/admin/login.php
2. Login with credentials above
3. You will be automatically redirected to the email setup page
4. Enter your email: `webmaster@raywonderis.me`
5. Confirm your email by entering it again
6. Click "Save Administrator Email"
7. Check your email for confirmation
8. You will be redirected to the admin dashboard

### What Happens After Email Setup

- Your email is saved to `/home/flexpbxuser/admins/admin_admin.json`
- Your email is also saved to the database (if available)
- You receive a confirmation email
- Future password resets will use this email
- System notifications will be sent to this email

## File Structure

### User Portal Files

- `/user-portal/index.php` - User login with email detection
- `/user-portal/setup-email.php` - Email setup page for users
- `/user-portal/forgot-password.php` - Password reset request
- `/user-portal/reset-password.php` - Password reset form

### Admin Portal Files

- `/admin/login.php` - Admin login with email detection
- `/admin/setup-email.php` - Email setup page for admins
- `/admin/forgot-password.php` - Admin password reset request
- `/admin/reset-password.php` - Admin password reset form

### API Files

- `/api/login.php` - Unified authentication API
- `/api/flexpbx-config-helper.php` - Configuration helper

### Data Storage

- `/home/flexpbxuser/users/user_*.json` - User account files
- `/home/flexpbxuser/admins/admin_*.json` - Admin account files
- MySQL database `users` table - Synced user/admin data

## Security Features

### Email Validation

- Must be valid email format
- Cannot be a placeholder value
- Must match confirmation field
- Cannot be empty

### Session Management

- `email_setup_complete` session variable tracks completion
- Prevents bypassing email setup by direct URL access
- Session is required for all protected pages

### Data Storage

- Passwords are hashed with bcrypt (PHP `PASSWORD_DEFAULT`)
- Email addresses are stored in plain text for notifications
- Admin files have 640 permissions (readable only by owner and group)
- All changes are logged with timestamp

## Skipping Email Setup

Users and admins can skip email setup by clicking "Skip for now", but this is **not recommended** because:

- Password reset will not work
- Voicemail notifications will not be sent
- System alerts will not be received
- Account recovery will be difficult

## Testing the System

### Test User Login

1. Login to user portal with extension 2006
2. Username: `walterharper` or `2006`
3. Password: `Review.1121`
4. Email is already set, should go directly to dashboard

### Test Admin Login (Dominique)

1. Go to https://flexpbx.devinecreations.net/admin/login.php
2. Username: `admin`
3. Password: `FlexPBX2024!Admin`
4. Should be redirected to email setup
5. Enter `webmaster@raywonderis.me`
6. Should receive confirmation email
7. Should be redirected to dashboard

### Test API Login

```bash
curl -X POST https://flexpbx.devinecreations.net/api/login.php \
  -H "Content-Type: application/json" \
  -d '{
    "username": "admin",
    "password": "FlexPBX2024!Admin",
    "account_type": "admin"
  }'
```

Expected response:
```json
{
  "success": true,
  "account_type": "admin",
  "email_setup_required": true,
  "setup_url": "/admin/setup-email.php",
  "message": "Authentication successful. Please set your administrator email address."
}
```

## Troubleshooting

### Email Setup Page Shows Error "User file not found"

- Check that `/home/flexpbxuser/users/` or `/home/flexpbxuser/admins/` directory exists
- Check that user/admin JSON file exists
- Check file permissions (should be 640)

### Email Not Saving

- Check PHP `file_put_contents()` permissions
- Check that directory is writable by `flexpbxuser`
- Check error logs: `/home/flexpbxuser/logs/error_log`

### Confirmation Email Not Sent

- Check that PHP `mail()` function is enabled
- Check mail logs: `/var/log/maillog`
- Verify SMTP settings in `/etc/postfix/main.cf`
- Test with: `php -r "mail('test@example.com', 'Test', 'Test message');"`

### Session Not Persisting

- Check that PHP sessions are enabled
- Check session save path: `session.save_path` in `php.ini`
- Check that `/tmp` or session directory is writable

### Database Sync Failed

- Check database credentials in `/home/flexpbxuser/public_html/api/config.php`
- Check that MySQL is running: `systemctl status mysqld`
- Check database connection: `mysql -u flexpbx_user -p`
- Review error logs for database connection errors

## Future Enhancements

- [ ] Email verification via confirmation link
- [ ] Two-factor authentication option
- [ ] Email change notifications
- [ ] Admin approval for email changes
- [ ] Email domain whitelist/blacklist
- [ ] SMS notification option as alternative to email

## Related Documentation

- [Password Reset System](PASSWORD_RESET_SYSTEM.md)
- [Admin Access Info](../ADMIN_ACCESS_INFO.md)
- [User Portal Guide](USER_PORTAL_UPDATED.md)
- [API Documentation](../api/README.md)

---

**Last Updated**: October 14, 2025
**System Version**: FlexPBX v1.0
**Author**: Claude Code Assistant
