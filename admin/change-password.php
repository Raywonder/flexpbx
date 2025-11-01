<?php
require_once __DIR__ . '/admin_auth_check.php';
/**
 * FlexPBX Admin Portal - Change Password
 * Allows logged-in admins to change their password
 */

session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /admin/login.php');
    exit;
}

$admin_username = $_SESSION['admin_username'] ?? '';
$admins_dir = '/home/flexpbxuser/admins';
$error = '';
$success = '';

// Find admin file
$admin_file = $admins_dir . '/admin_' . $admin_username . '.json';
$admin_data = null;

if (file_exists($admin_file)) {
    $admin_data = json_decode(file_get_contents($admin_file), true);
}

if (!$admin_data) {
    $error = 'Admin account not found.';
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password']) && $admin_data) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validate current password
    if (empty($current_password)) {
        $error = 'Current password is required.';
    } elseif (!password_verify($current_password, $admin_data['password'])) {
        $error = 'Current password is incorrect.';
    } elseif (empty($new_password)) {
        $error = 'New password is required.';
    } elseif (strlen($new_password) < 10) {
        $error = 'Admin password must be at least 10 characters long.';
    } elseif (!preg_match('/[A-Z]/', $new_password)) {
        $error = 'Password must contain at least one uppercase letter.';
    } elseif (!preg_match('/[a-z]/', $new_password)) {
        $error = 'Password must contain at least one lowercase letter.';
    } elseif (!preg_match('/[0-9]/', $new_password)) {
        $error = 'Password must contain at least one number.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New passwords do not match.';
    } elseif ($new_password === $current_password) {
        $error = 'New password must be different from current password.';
    } else {
        // Update password in admin file
        $admin_data['password'] = password_hash($new_password, PASSWORD_DEFAULT);
        $admin_data['password_changed_date'] = date('Y-m-d H:i:s');
        $admin_data['password_changed_by'] = 'self';
        $admin_data['password_changed_ip'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $admin_data['updated_at'] = date('Y-m-d H:i:s');

        // Remove temp password flags
        unset($admin_data['temp_password_expires']);
        unset($admin_data['temp_password_created']);
        unset($admin_data['temp_password_ip']);

        if (file_put_contents($admin_file, json_encode($admin_data, JSON_PRETTY_PRINT))) {
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
                    WHERE username = ?
                ");
                $stmt->execute([$new_password, $admin_username]);
            } catch (Exception $e) {
                error_log("Database update failed in admin change-password.php: " . $e->getMessage());
            }

            // Send confirmation email
            if (!empty($admin_data['email']) && filter_var($admin_data['email'], FILTER_VALIDATE_EMAIL)) {
                $to = $admin_data['email'];
                $subject = "FlexPBX Admin - Password Changed";
                $message = "Hello " . ($admin_data['full_name'] ?? $admin_username) . ",\n\n";
                $message .= "Your FlexPBX administrator password has been successfully changed.\n\n";
                $message .= "Username: $admin_username\n";
                $message .= "Changed on: " . date('Y-m-d H:i:s') . "\n";
                $message .= "IP Address: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . "\n\n";
                $message .= "If you did not make this change, please secure your account immediately and contact the system administrator.\n\n";
                $message .= "Best regards,\nFlexPBX Admin System";

                @mail($to, $subject, $message, "From: admin@flexpbx.devinecreations.net");
            }

            // Log the password change
            $log_file = '/home/flexpbxuser/logs/admin_security.log';
            $log_entry = "[" . date('Y-m-d H:i:s') . "] [" . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . "] Password changed for admin: $admin_username\n";
            @file_put_contents($log_file, $log_entry, FILE_APPEND);

            $success = 'Password changed successfully! Your account is now more secure.';
        } else {
            $error = 'Failed to update password. Please check file permissions or try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - FlexPBX Admin</title>
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
            max-width: 650px;
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

        .admin-badge {
            display: inline-block;
            background: #ff6b6b;
            color: white;
            padding: 0.3rem 0.8rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-top: 0.5rem;
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

        .security-notice {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 4px;
        }

        .security-notice strong {
            color: #856404;
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
            border: 1px solid #dee2e6;
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

        .strength-indicator {
            margin-top: 0.5rem;
            padding: 0.5rem;
            border-radius: 4px;
            font-size: 0.85rem;
            display: none;
        }

        .strength-weak { background: #fee; color: #c33; display: block; }
        .strength-medium { background: #fff3cd; color: #856404; display: block; }
        .strength-strong { background: #d4edda; color: #155724; display: block; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔐 Change Administrator Password</h1>
            <p class="subtitle">User: <?= htmlspecialchars($admin_username) ?></p>
            <span class="admin-badge">ADMIN</span>
        </div>

        <div class="card">
            <div class="security-notice">
                <strong>🔒 Administrator Security:</strong> As an admin, you must use a strong password. Your password protects critical system access and should be unique and complex.
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
                <h3>Administrator Password Requirements:</h3>
                <ul>
                    <li><strong>Minimum 10 characters</strong> (more is better)</li>
                    <li>At least one <strong>uppercase letter</strong> (A-Z)</li>
                    <li>At least one <strong>lowercase letter</strong> (a-z)</li>
                    <li>At least one <strong>number</strong> (0-9)</li>
                    <li>Recommended: Include special characters (!@#$%)</li>
                    <li>Must be different from your current password</li>
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
                        minlength="10"
                        autocomplete="new-password"
                    >
                    <small>At least 10 characters with uppercase, lowercase, and numbers</small>
                    <div id="strength-indicator" class="strength-indicator"></div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm New Password *</label>
                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        required
                        minlength="10"
                        autocomplete="new-password"
                    >
                    <small>Re-enter your new password</small>
                </div>

                <button type="submit" name="change_password" class="btn">Change Admin Password</button>
                <a href="/admin/dashboard.html" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>

    <script>
        // Password strength indicator
        document.getElementById('new_password')?.addEventListener('input', function() {
            const password = this.value;
            const indicator = document.getElementById('strength-indicator');

            let strength = 0;
            if (password.length >= 10) strength++;
            if (password.length >= 14) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^a-zA-Z0-9]/.test(password)) strength++;

            indicator.className = 'strength-indicator';

            if (strength <= 2) {
                indicator.className += ' strength-weak';
                indicator.textContent = '⚠️ Weak password - add more complexity';
            } else if (strength <= 4) {
                indicator.className += ' strength-medium';
                indicator.textContent = '⚡ Medium strength - consider adding special characters';
            } else {
                indicator.className += ' strength-strong';
                indicator.textContent = '✓ Strong password';
            }
        });

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
