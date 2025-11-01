# FlexPBX User Portal Authentication Guide

## Quick Reference

### For New Pages

Add authentication to a new user portal page:

```php
<?php
/**
 * Your Page Title
 * Description
 */

// Require authentication
require_once __DIR__ . '/user_auth_check.php';
?>
<!DOCTYPE html>
<html>
<!-- Your HTML here -->
</html>
```

### Adding the User Header

Include the standard user header (with logout button):

```php
<?php require_once __DIR__ . '/user_auth_check.php'; ?>
<!DOCTYPE html>
<html>
<head>
    <title>Your Page</title>
</head>
<body>
    <?php include __DIR__ . '/user_header.php'; ?>
    
    <div class="container">
        <!-- Your content here -->
    </div>
</body>
</html>
```

## Available Variables

After including `user_auth_check.php`, these variables are available:

- `$user_extension` - User's extension number (e.g., "2000")
- `$user_username` - User's username
- `$user_email` - User's email address
- `$user_data` - Complete user data array
- `$is_user_logged_in` - Boolean login status

## Security Features

### Session Timeout
- Automatic timeout after 30 minutes of inactivity
- User redirected to login with "session expired" message

### Extension Verification
- Validates extension exists in system
- Prevents access if user file is deleted

### Logout Protection
- Complete session cleanup
- Cookie removal (including remember me tokens)
- Token removal from database

## Files

### Core Authentication Files
- `/user-portal/user_auth_check.php` - Authentication check
- `/user-portal/user_header.php` - User header with logout
- `/user-portal/logout.php` - Logout handler
- `/user-portal/login.php` - Login page

### Public Pages (No Auth Required)
- login.php
- logout.php
- signup.php
- forgot-password.php
- reset-password.php

## Testing Checklist

- [ ] Page redirects to login when not authenticated
- [ ] Page accessible after successful login
- [ ] Logout button works correctly
- [ ] Session timeout redirects to login
- [ ] User header displays correct information
- [ ] Network status indicator updates

## Support

For questions or issues with authentication:
- Check error_log for PHP errors
- Verify session is started
- Ensure user file exists in /home/flexpbxuser/users/
- Test with a known working account

## Examples

### Basic Protected Page
```php
<?php require_once __DIR__ . '/user_auth_check.php'; ?>
<!DOCTYPE html>
<html>
<head><title>Protected Page</title></head>
<body>
    <?php include __DIR__ . '/user_header.php'; ?>
    <h1>Welcome <?= htmlspecialchars($user_username) ?></h1>
    <p>Extension: <?= htmlspecialchars($user_extension) ?></p>
</body>
</html>
```

### Page with Custom Auth Logic
```php
<?php 
require_once __DIR__ . '/user_auth_check.php';

// Additional custom checks
if ($user_data['role'] !== 'admin') {
    header('Location: /user-portal/?error=unauthorized');
    exit;
}
?>
<!DOCTYPE html>
<!-- Your admin-only page -->
```

## Security Best Practices

1. Always use `require_once` for auth check
2. Never bypass authentication checks
3. Use `htmlspecialchars()` for output
4. Validate all user input
5. Check permissions for sensitive operations
6. Log security-relevant events
7. Keep session timeout reasonable
8. Clear sessions on logout

## Troubleshooting

### "Headers already sent" error
- Ensure no output before session_start()
- Check for whitespace before <?php tag

### Infinite redirect loop
- Verify login.php is in the exception list
- Check session variables are being set correctly

### Session lost on page reload
- Check session cookie settings
- Verify PHP session configuration
- Check server session storage

---
Last Updated: October 24, 2025
