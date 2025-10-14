<?php
/**
 * FlexPBX Admin Portal - Reset Password
 * Allows admin to set new password using reset token
 */

session_start();

// Configuration
$reset_tokens_dir = '/home/flexpbxuser/reset_tokens';
$admins_dir = '/home/flexpbxuser/admins';

$success_message = '';
$error_message = '';
$token_valid = false;
$token_data = null;

// Get token from URL
$token = $_GET['token'] ?? '';

if (empty($token)) {
    $error_message = 'Invalid or missing reset token.';
} else {
    // Verify token
    $token_file = $reset_tokens_dir . '/token_' . $token . '.json';

    if (!file_exists($token_file)) {
        $error_message = 'Invalid reset token. It may have already been used.';
    } else {
        $token_data = json_decode(file_get_contents($token_file), true);

        // Check if token is expired
        if (time() > $token_data['expires']) {
            $error_message = 'This reset link has expired. Please request a new one.';
            unlink($token_file); // Delete expired token
        } elseif ($token_data['type'] !== 'admin') {
            $error_message = 'Invalid token type.';
        } else {
            $token_valid = true;
        }
    }
}

// Process password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token_valid) {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($new_password) || empty($confirm_password)) {
        $error_message = 'Please fill in all fields.';
    } elseif ($new_password !== $confirm_password) {
        $error_message = 'Passwords do not match.';
    } elseif (strlen($new_password) < 10) {
        $error_message = 'Admin password must be at least 10 characters long.';
    } elseif (!preg_match('/[A-Z]/', $new_password)) {
        $error_message = 'Password must contain at least one uppercase letter.';
    } elseif (!preg_match('/[a-z]/', $new_password)) {
        $error_message = 'Password must contain at least one lowercase letter.';
    } elseif (!preg_match('/[0-9]/', $new_password)) {
        $error_message = 'Password must contain at least one number.';
    } else {
        // Find admin file
        $username = $token_data['username'];
        $admin_file = $admins_dir . '/admin_' . $username . '.json';

        if (!file_exists($admin_file)) {
            $error_message = 'Admin account not found.';
        } else {
            // Update password in file
            $admin_data = json_decode(file_get_contents($admin_file), true);
            $admin_data['password'] = password_hash($new_password, PASSWORD_DEFAULT);
            $admin_data['password_reset_date'] = date('Y-m-d H:i:s');

            // Also update in database if it exists
            try {
                $config = include('/home/flexpbxuser/public_html/api/config.php');
                $pdo = new PDO(
                    "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4",
                    $config['db_user'],
                    $config['db_password'],
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );

                // Update password in users/admins table (using SHA2 for compatibility)
                $stmt = $pdo->prepare("UPDATE users SET password_hash = SHA2(?, 256), updated_at = NOW() WHERE username = ? AND role IN ('admin', 'super_admin')");
                $stmt->execute([$new_password, $username]);
            } catch (Exception $e) {
                // Database update failed, but continue with file update
                error_log("Database admin password update failed: " . $e->getMessage());
            }

            if (file_put_contents($admin_file, json_encode($admin_data, JSON_PRETTY_PRINT))) {
                // Delete token so it can't be reused
                unlink($token_file);

                // Send confirmation email
                $to = $token_data['email'];
                $subject = 'FlexPBX Admin Password Changed Successfully';
                $message = "Hello " . $admin_data['full_name'] . ",\n\n";
                $message .= "Your FlexPBX admin password has been successfully changed.\n\n";
                $message .= "Username: " . $username . "\n";
                $message .= "Role: " . $admin_data['role'] . "\n";
                $message .= "Changed: " . date('Y-m-d H:i:s') . "\n\n";
                $message .= "If you did not make this change, please contact support immediately.\n\n";
                $message .= "You can now login with your new password at:\n";
                $message .= "https://flexpbx.devinecreations.net/admin/\n\n";
                $message .= "---\n";
                $message .= "FlexPBX Admin System\n";

                $headers = "From: FlexPBX Admin <noreply@devinecreations.net>\r\n";
                $headers .= "Reply-To: support@devine-creations.com\r\n";

                mail($to, $subject, $message, $headers);

                $success_message = 'Your admin password has been reset successfully! You can now login with your new password.';
                $token_valid = false; // Hide form
            } else {
                $error_message = 'Failed to update password. Please try again or contact support.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - FlexPBX Admin Portal</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 12px;
            padding: 40px;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }

        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }

        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }

        .admin-badge {
            display: inline-block;
            background: #ff6b6b;
            color: white;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            color: #333;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
        }

        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        input:focus {
            outline: none;
            border-color: #667eea;
        }

        .help-text {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }

        button {
            width: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 14px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }

        .login-link {
            text-align: center;
            margin-top: 20px;
        }

        .login-link a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        .admin-info {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .admin-info strong {
            color: #856404;
        }

        .password-requirements {
            background: #f5f5f5;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 13px;
        }

        .password-requirements strong {
            color: #ff6b6b;
        }

        .password-requirements ul {
            margin-left: 20px;
            margin-top: 8px;
        }

        .password-requirements li {
            margin-bottom: 5px;
        }

        .security-note {
            background: #ffe5e5;
            border: 1px solid #ff6b6b;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 20px;
            font-size: 13px;
            color: #c92a2a;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Reset Password <span class="admin-badge">ADMIN</span></h1>
        <p class="subtitle">FlexPBX Admin Portal</p>

        <?php if ($success_message): ?>
            <div class="alert alert-success" role="alert">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
            <div class="login-link">
                <a href="index.html">→ Go to Admin Login</a>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-error" role="alert">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
            <?php if (!$token_valid): ?>
                <div class="login-link">
                    <a href="forgot-password.php">Request New Reset Link</a>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($token_valid && !$success_message): ?>
            <div class="admin-info">
                <strong>Resetting password for admin:</strong><br>
                Username: <?php echo htmlspecialchars($token_data['username']); ?><br>
                <?php echo htmlspecialchars($token_data['email']); ?>
            </div>

            <div class="security-note">
                <strong>⚠️ Admin Security:</strong> Use a strong password that you don't use anywhere else.
            </div>

            <div class="password-requirements">
                <strong>Admin Password Requirements:</strong>
                <ul>
                    <li>Minimum 10 characters (stronger than user passwords)</li>
                    <li>At least one uppercase letter</li>
                    <li>At least one lowercase letter</li>
                    <li>At least one number</li>
                    <li>Special characters recommended</li>
                </ul>
            </div>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="new_password">New Admin Password</label>
                    <input
                        type="password"
                        id="new_password"
                        name="new_password"
                        required
                        minlength="10"
                        autocomplete="new-password"
                    >
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        required
                        minlength="10"
                        autocomplete="new-password"
                    >
                    <p class="help-text">Enter the same password again to confirm</p>
                </div>

                <button type="submit">Reset Admin Password</button>
            </form>

            <div class="login-link">
                <a href="index.html">← Back to Admin Login</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
