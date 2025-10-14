# Auto-Generated Temporary Password System

## Overview

FlexPBX uses an auto-generated temporary password system for first-time admin login. This enhances security by eliminating static default passwords.

## How It Works

### For First-Time Login

1. **User visits admin login page** (`/admin/login.php`)
2. **JavaScript fetches temporary password** from API endpoint
3. **API generates secure password** (e.g., `Admin4319`)
4. **Password is displayed on page** with countdown timer
5. **Password auto-fills** in the login form
6. **User logs in** within 15 minutes
7. **After login**, user sets their email and can use password reset

### Security Features

- **Time-Limited**: Passwords expire after 15 minutes
- **One-Time Use**: New password generated each time page loads
- **Auto-Rotation**: Old passwords automatically cleaned up
- **Session-Based**: Temporary password only shown for accounts needing setup
- **Visual Countdown**: Timer shows remaining time
- **Auto-Disable**: Form disables when password expires

## API Endpoint

### `/api/generate-temp-password.php`

**Request:**
```bash
GET /api/generate-temp-password.php?username=admin&account_type=admin
```

**Response (when temp password needed):**
```json
{
  "success": true,
  "show_temp_password": true,
  "username": "admin",
  "password": "Admin4319",
  "expires": 1760444762,
  "expires_in_seconds": 900,
  "expires_at": "2025-10-14 12:26:02",
  "message": "Temporary password generated. Valid for 15 minutes."
}
```

**Response (when account already configured):**
```json
{
  "success": false,
  "show_temp_password": false,
  "message": "Account already configured. Use your personal password."
}
```

### When Temporary Password is Generated

Temporary password is ONLY generated when:
- Admin account has default/placeholder email
- Email is one of: `admin@example.com`, `noemail@localhost`, etc.
- Account has never been configured with real email

Once email is set, the temporary password system is disabled and user must use password reset.

## File Structure

### API Files
- `/api/generate-temp-password.php` - Password generation endpoint
- `/temp_passwords/` - Temporary password storage (auto-cleanup)

### Admin Files
- `/admins/admin_*.json` - Admin account files with temp password hash

### JavaScript Integration
- Login page fetches password on `DOMContentLoaded`
- Countdown timer updates every second
- Auto-disables form on expiry
- Copy-to-clipboard functionality

## Password Format

**Pattern**: `Admin` + 4-digit random number

**Examples**:
- `Admin1234`
- `Admin5678`
- `Admin9012`

**Security**: Uses PHP `password_hash()` with `PASSWORD_DEFAULT` (bcrypt)

## Expiry and Cleanup

### Expiry Time
- **Duration**: 15 minutes (900 seconds)
- **Warning**: Timer turns red under 2 minutes
- **Expiry Action**: Form disables, user must refresh

### Automatic Cleanup
- Expired passwords are deleted on next API call
- Cleanup runs before generating new password
- Files older than expiry time are removed

### Storage Location
```
/home/flexpbxuser/temp_passwords/temp_admin_TIMESTAMP.json
```

### Cleanup Script
```bash
# Manual cleanup (if needed)
find /home/flexpbxuser/temp_passwords -name "temp_*.json" -mmin +15 -delete
```

## UI/UX Flow

### Loading State
```
🔐 Generating secure temporary password...
Please wait...
```

### Active State
```
🔒 Auto-Generated Temporary Password:
Username: admin
Password: Admin4319 [Copy]
⏱️ Expires in: 14m 32s
This password expires in 15 minutes for security.
```

### Expired State
```
⚠️ Password expired! Refresh page for new password.
[Form disabled]
```

### Account Configured
```
[No temporary password box shown]
[User must use forgot password if needed]
```

## JavaScript Functions

### `fetchTempPassword()`
- Fetches password from API
- Updates UI with password and timer
- Auto-fills password field
- Handles errors gracefully

### `startCountdown(expiryTimestamp)`
- Updates timer every second
- Formats as `Xm Xs`
- Shows warning when < 2 minutes
- Disables form on expiry

### `copyPassword()`
- Copies password to clipboard
- Shows confirmation feedback
- Fallback to alert if clipboard fails

## Security Considerations

### Why This Approach?

1. **No Static Passwords**: Documentation can't contain working passwords
2. **Time-Limited Access**: Window of vulnerability is 15 minutes
3. **Auto-Rotation**: Each visit gets new password
4. **One-Time Setup**: Only works until email is configured
5. **Logged Access**: All login attempts are logged

### Potential Attacks Mitigated

- **Documentation Leak**: No static passwords in docs
- **Shoulder Surfing**: Password expires quickly
- **Credential Stuffing**: Password changes constantly
- **Unauthorized Access**: Must have access to login page

### Remaining Considerations

- **Network Interception**: Use HTTPS (already implemented)
- **XSS Attacks**: Sanitize all user input (already implemented)
- **CSRF**: Add CSRF tokens (future enhancement)
- **Rate Limiting**: Limit API calls per IP (future enhancement)

## Troubleshooting

### Password Not Appearing

**Issue**: Loading box stays visible
**Causes**:
- API endpoint not accessible
- JavaScript error in console
- Network connectivity issue

**Solutions**:
```bash
# Check API accessibility
curl https://flexpbx.devinecreations.net/api/generate-temp-password.php?username=admin

# Check JavaScript console in browser
# Check Apache error logs
tail -f /var/log/apache2/error_log
```

### Password Doesn't Work

**Issue**: Login fails with correct password
**Causes**:
- Password expired during login attempt
- Caps Lock enabled
- Browser auto-fill using old password

**Solutions**:
- Refresh page for new password
- Check caps lock
- Clear browser cache and cookies
- Manually type password

### Timer Not Counting Down

**Issue**: Timer shows but doesn't update
**Causes**:
- JavaScript error
- Browser compatibility issue
- Page refresh needed

**Solutions**:
- Check browser console for errors
- Try different browser (Chrome, Firefox, Safari)
- Hard refresh: Ctrl+Shift+R (or Cmd+Shift+R on Mac)

### API Returns "Account Already Configured"

**Issue**: No password shown, but user needs to login
**Cause**: Email has been set, temp password system disabled

**Solution**:
- Use "Forgot Password" link
- Or manually reset password in admin JSON file
- Or update email to placeholder in JSON file to re-enable temp password

## Implementation Checklist

- [x] Create `/api/generate-temp-password.php` endpoint
- [x] Create `/temp_passwords/` directory with proper permissions
- [x] Update admin login page with JavaScript integration
- [x] Add countdown timer functionality
- [x] Add copy-to-clipboard feature
- [x] Implement automatic cleanup of expired passwords
- [x] Add visual feedback for expiry
- [x] Auto-fill password field
- [x] Disable form on expiry
- [x] Test with first-time admin login
- [x] Document API and system behavior

## Future Enhancements

- [ ] Add rate limiting to prevent brute force
- [ ] Log all temp password generations
- [ ] Email notification when temp password is generated
- [ ] Support for multiple admin accounts
- [ ] Configurable expiry time
- [ ] Admin panel to view active temp passwords
- [ ] TOTP/2FA option for admins
- [ ] Password strength indicator
- [ ] Custom password format options

## Related Documentation

- [Email Setup System](EMAIL_SETUP_SYSTEM.md)
- [Password Reset System](PASSWORD_RESET_SYSTEM.md)
- [Admin Access Info](../ADMIN_ACCESS_INFO.md)
- [Security Best Practices](SECURITY_BEST_PRACTICES.md)

---

**Last Updated**: October 14, 2025
**System Version**: FlexPBX v1.0
**Security Level**: High
