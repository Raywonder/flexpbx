<?php
/**
 * FlexPBX User Portal - Reset Password
 * Allows user to set new password using reset token
 */

session_start();

// Configuration
$reset_tokens_dir = '/home/flexpbxuser/reset_tokens';
$users_dir = '/home/flexpbxuser/users';

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
        } elseif ($token_data['type'] !== 'user') {
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
    } elseif (strlen($new_password) < 8) {
        $error_message = 'Password must be at least 8 characters long.';
    } else {
        // Find user file
        $extension = $token_data['extension'];
        $user_file = $users_dir . '/user_' . $extension . '.json';

        if (!file_exists($user_file)) {
            $error_message = 'User account not found.';
        } else {
            // Update password in file
            $user_data = json_decode(file_get_contents($user_file), true);
            $user_data['password'] = password_hash($new_password, PASSWORD_DEFAULT);
            $user_data['password_reset_date'] = date('Y-m-d H:i:s');

            // Also update in database if it exists
            try {
                $config = include('/home/flexpbxuser/public_html/api/config.php');
                $pdo = new PDO(
                    "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4",
                    $config['db_user'],
                    $config['db_password'],
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );

                // Update password in users table (using SHA2 for compatibility with existing system)
                $stmt = $pdo->prepare("UPDATE users SET password_hash = SHA2(?, 256), updated_at = NOW() WHERE extension = ? OR username = ?");
                $stmt->execute([$new_password, $extension, $extension]);
            } catch (Exception $e) {
                // Database update failed, but continue with file update
                error_log("Database password update failed: " . $e->getMessage());
            }

            if (file_put_contents($user_file, json_encode($user_data, JSON_PRETTY_PRINT))) {
                // Delete token so it can't be reused
                unlink($token_file);

                // Send confirmation email
                $to = $token_data['email'];
                $subject = 'FlexPBX Password Changed Successfully';
                $message = "Hello,\n\n";
                $message .= "Your FlexPBX password has been successfully changed.\n\n";
                $message .= "Extension: " . $extension . "\n";
                $message .= "Changed: " . date('Y-m-d H:i:s') . "\n\n";
                $message .= "If you did not make this change, please contact support immediately.\n\n";
                $message .= "You can now login with your new password at:\n";
                $message .= "https://flexpbx.devinecreations.net/user-portal/\n\n";
                $message .= "---\n";
                $message .= "FlexPBX System\n";

                $headers = "From: FlexPBX <noreply@devinecreations.net>\r\n";
                $headers .= "Reply-To: support@devine-creations.com\r\n";

                mail($to, $subject, $message, $headers);

                $success_message = 'Your password has been reset successfully! You can now login with your new password.';
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
    <title>Reset Password - FlexPBX User Portal</title>
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

        .info-box {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 14px;
            color: #856404;
        }

        .user-info {
            background: #e3f2fd;
            border: 1px solid #90caf9;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .user-info strong {
            color: #1565c0;
        }

        .password-requirements {
            background: #f5f5f5;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 13px;
        }

        .password-requirements ul {
            margin-left: 20px;
            margin-top: 8px;
        }

        .password-requirements li {
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Reset Password</h1>
        <p class="subtitle">FlexPBX User Portal</p>

        <?php if ($success_message): ?>
            <div class="alert alert-success" role="alert">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
            <div class="login-link">
                <a href="index.php">→ Go to Login</a>
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
            <div class="user-info">
                <strong>Resetting password for:</strong><br>
                Extension <?php echo htmlspecialchars($token_data['extension']); ?><br>
                <?php echo htmlspecialchars($token_data['email']); ?>
            </div>

            <div class="password-requirements">
                <strong>Password Requirements:</strong>
                <ul>
                    <li>Minimum 8 characters</li>
                    <li>Use a strong, unique password</li>
                    <li>Don't reuse old passwords</li>
                </ul>
            </div>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input
                        type="password"
                        id="new_password"
                        name="new_password"
                        required
                        minlength="8"
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
                        minlength="8"
                        autocomplete="new-password"
                    >
                    <p class="help-text">Enter the same password again to confirm</p>
                </div>

                <button type="submit">Reset Password</button>
            </form>

            <div class="login-link">
                <a href="index.php">← Back to Login</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
