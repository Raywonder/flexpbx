<?php
/**
 * FlexPBX User Portal - Change Password
 * Allows logged-in users to change their password
 */

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: /user-portal/login.php');
    exit;
}

$extension = $_SESSION['user_extension'] ?? '';
$users_dir = '/home/flexpbxuser/users';
$error = '';
$success = '';

// Find user file
$user_file = null;
$user_data = null;

if (is_numeric($extension)) {
    $possible_file = $users_dir . '/user_' . $extension . '.json';
    if (file_exists($possible_file)) {
        $user_file = $possible_file;
        $user_data = json_decode(file_get_contents($user_file), true);
    }
}

if (!$user_data && file_exists($users_dir)) {
    $files = glob($users_dir . '/user_*.json');
    foreach ($files as $file) {
        $temp_data = json_decode(file_get_contents($file), true);
        if ((isset($temp_data['extension']) && $temp_data['extension'] === $extension) ||
            (isset($temp_data['username']) && $temp_data['username'] === $extension)) {
            $user_file = $file;
            $user_data = $temp_data;
            break;
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validate current password
    if (empty($current_password)) {
        $error = 'Current password is required.';
    } elseif (!password_verify($current_password, $user_data['password'])) {
        $error = 'Current password is incorrect.';
    } elseif (empty($new_password)) {
        $error = 'New password is required.';
    } elseif (strlen($new_password) < 8) {
        $error = 'New password must be at least 8 characters long.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New passwords do not match.';
    } elseif ($new_password === $current_password) {
        $error = 'New password must be different from current password.';
    } else {
        // Update password in user file
        $user_data['password'] = password_hash($new_password, PASSWORD_DEFAULT);
        $user_data['password_changed_date'] = date('Y-m-d H:i:s');
        $user_data['password_changed_by'] = 'user';
        $user_data['updated_at'] = date('Y-m-d H:i:s');

        if (file_put_contents($user_file, json_encode($user_data, JSON_PRETTY_PRINT))) {
            // Also update in database if available
            $config = include('/home/flexpbxuser/public_html/api/config.php');
            try {
                $pdo = new PDO(
                    "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4",
                    $config['db_user'],
                    $config['db_password'],
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );

                $stmt = $pdo->prepare("
                    UPDATE users
                    SET password_hash = SHA2(?, 256), updated_at = NOW()
                    WHERE extension = ? OR username = ?
                ");
                $stmt->execute([$new_password, $extension, $extension]);
            } catch (Exception $e) {
                error_log("Database update failed in change-password.php: " . $e->getMessage());
            }

            // Send confirmation email if email is set
            if (!empty($user_data['email']) && filter_var($user_data['email'], FILTER_VALIDATE_EMAIL)) {
                $to = $user_data['email'];
                $subject = "FlexPBX - Password Changed";
                $message = "Hello " . ($user_data['full_name'] ?? $extension) . ",\n\n";
                $message .= "Your FlexPBX password has been successfully changed.\n\n";
                $message .= "Extension: $extension\n";
                $message .= "Changed on: " . date('Y-m-d H:i:s') . "\n";
                $message .= "IP Address: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . "\n\n";
                $message .= "If you did not make this change, please contact your administrator immediately.\n\n";
                $message .= "Best regards,\nFlexPBX System";

                @mail($to, $subject, $message, "From: noreply@flexpbx.devinecreations.net");
            }

            $success = 'Password changed successfully!';
        } else {
            $error = 'Failed to update password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - FlexPBX User Portal</title>
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
            padding: 20px;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
        }

        .header {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }

        h1 {
            color: #333;
            margin-bottom: 10px;
        }

        .subtitle {
            color: #666;
            font-size: 14px;
        }

        .card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            margin-bottom: 20px;
        }

        .alert {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
        }

        .alert-error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }

        .alert-success {
            background: #efe;
            color: #3c3;
            border: 1px solid #cfc;
        }

        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 4px;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #2c3e50;
        }

        .form-group input {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.2);
        }

        .form-group small {
            display: block;
            margin-top: 0.3rem;
            color: #666;
            font-size: 0.85rem;
        }

        .btn {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            padding: 0.8rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            width: 100%;
            transition: all 0.3s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
            margin-top: 1rem;
        }

        .password-requirements {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
        }

        .password-requirements h3 {
            font-size: 0.9rem;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }

        .password-requirements ul {
            margin-left: 1.5rem;
            color: #666;
            font-size: 0.85rem;
        }

        .password-requirements li {
            margin-bottom: 0.3rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔐 Change Password</h1>
            <p class="subtitle">Extension <?= htmlspecialchars($extension) ?></p>
        </div>

        <div class="card">
            <div class="info-box">
                <strong>Security Tip:</strong> Choose a strong password that you haven't used before. Your password will be encrypted and stored securely.
            </div>

            <?php if ($error): ?>
            <div class="alert alert-error" role="alert">
                ⚠️ <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="alert alert-success" role="alert">
                ✓ <?= htmlspecialchars($success) ?>
            </div>
            <?php endif; ?>

            <div class="password-requirements">
                <h3>Password Requirements:</h3>
                <ul>
                    <li>Minimum 8 characters</li>
                    <li>Must be different from your current password</li>
                    <li>Recommended: Mix of letters, numbers, and symbols</li>
                </ul>
            </div>

            <form method="POST">
                <div class="form-group">
                    <label for="current_password">Current Password *</label>
                    <input
                        type="password"
                        id="current_password"
                        name="current_password"
                        required
                        autofocus
                        autocomplete="current-password"
                    >
                </div>

                <div class="form-group">
                    <label for="new_password">New Password *</label>
                    <input
                        type="password"
                        id="new_password"
                        name="new_password"
                        required
                        minlength="8"
                        autocomplete="new-password"
                    >
                    <small>At least 8 characters</small>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm New Password *</label>
                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        required
                        minlength="8"
                        autocomplete="new-password"
                    >
                    <small>Re-enter your new password</small>
                </div>

                <button type="submit" name="change_password" class="btn" aria-label="Submit password change">Change Password</button>
                <a href="/user-portal/" class="btn btn-secondary" aria-label="Cancel and return to dashboard">Cancel</a>
            </form>
        </div>
    </div>

    <script>
        // Validate password match on form submit
        document.querySelector('form')?.addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;

            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('New passwords do not match. Please check and try again.');
                document.getElementById('confirm_password').focus();
            }
        });
    </script>
</body>
</html>
