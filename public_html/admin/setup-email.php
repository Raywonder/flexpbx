<?php
/**
 * FlexPBX Admin Portal - Email Setup
 * Prompts admin to set email on first login if not configured
 */

session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_username']) || empty($_SESSION['admin_username'])) {
    header('Location: /admin/');
    exit;
}

$admin_username = $_SESSION['admin_username'];
$admins_dir = '/home/flexpbxuser/admins';
$admin_file = null;
$admin_data = null;
$error = '';
$success = '';

// Ensure admins directory exists
if (!file_exists($admins_dir)) {
    mkdir($admins_dir, 0755, true);
}

// Find admin file
$possible_file = $admins_dir . '/admin_' . $admin_username . '.json';
if (file_exists($possible_file)) {
    $admin_file = $possible_file;
    $admin_data = json_decode(file_get_contents($admin_file), true);
}

// If not found, search all admin files
if (!$admin_data && file_exists($admins_dir)) {
    $files = glob($admins_dir . '/admin_*.json');
    foreach ($files as $file) {
        $temp_data = json_decode(file_get_contents($file), true);
        if ((isset($temp_data['username']) && $temp_data['username'] === $admin_username) ||
            (isset($temp_data['email']) && $temp_data['email'] === $admin_username)) {
            $admin_file = $file;
            $admin_data = $temp_data;
            break;
        }
    }
}

// If admin file not found, load from database or create
$config = include('/home/flexpbxuser/public_html/api/config.php');

if (!$admin_data) {
    try {
        $pdo = new PDO(
            "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4",
            $config['db_user'],
            $config['db_password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $stmt = $pdo->prepare("
            SELECT * FROM users
            WHERE username = ? AND role IN ('admin', 'super_admin', 'administrator')
            LIMIT 1
        ");
        $stmt->execute([$admin_username]);
        $admin_data = $stmt->fetch(PDO::FETCH_ASSOC);

        // If found in database, create admin file
        if ($admin_data && !$admin_file) {
            $admin_file = $admins_dir . '/admin_' . $admin_username . '.json';
        }
    } catch (Exception $e) {
        error_log("Database error in admin setup-email.php: " . $e->getMessage());
    }
}

if (!$admin_data) {
    // Create minimal admin data if not found
    $admin_data = [
        'username' => $admin_username,
        'email' => '',
        'full_name' => 'Administrator',
        'role' => 'admin'
    ];
    $admin_file = $admins_dir . '/admin_' . $admin_username . '.json';
}

// Check if email setup is already complete
$needs_email_setup = false;
$current_email = $admin_data['email'] ?? '';

// List of placeholder/default email values that indicate email needs setup
$placeholder_emails = [
    '',
    'user@example.com',
    'admin@example.com',
    'noemail@localhost',
    'user@localhost',
    'test@test.com',
    'changeme@example.com',
    'administrator@localhost'
];

if (empty($current_email) || in_array(strtolower($current_email), $placeholder_emails)) {
    $needs_email_setup = true;
}

// If email is already set and valid, redirect to dashboard
if (!$needs_email_setup && isset($_SESSION['email_setup_complete'])) {
    header('Location: /admin/dashboard.html');
    exit;
}

// Handle email submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_email'])) {
    $new_email = trim($_POST['email'] ?? '');
    $confirm_email = trim($_POST['confirm_email'] ?? '');

    // Validate email
    if (empty($new_email)) {
        $error = 'Email address is required.';
    } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif ($new_email !== $confirm_email) {
        $error = 'Email addresses do not match.';
    } elseif (in_array(strtolower($new_email), $placeholder_emails)) {
        $error = 'Please enter a real email address, not a placeholder.';
    } else {
        // Save email to admin file
        if ($admin_file) {
            $admin_data['email'] = $new_email;
            $admin_data['email_verified'] = false;
            $admin_data['email_set_date'] = date('Y-m-d H:i:s');
            $admin_data['updated_at'] = date('Y-m-d H:i:s');

            if (file_put_contents($admin_file, json_encode($admin_data, JSON_PRETTY_PRINT))) {
                $success = 'Email address saved successfully!';

                // Also save to database if available
                try {
                    $pdo = new PDO(
                        "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4",
                        $config['db_user'],
                        $config['db_password'],
                        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                    );

                    $stmt = $pdo->prepare("
                        UPDATE users
                        SET email = ?, updated_at = NOW()
                        WHERE username = ?
                    ");
                    $stmt->execute([$new_email, $admin_username]);
                } catch (Exception $e) {
                    error_log("Database update failed in admin setup-email.php: " . $e->getMessage());
                }

                // Send confirmation email
                $subject = "FlexPBX Admin - Email Address Confirmed";
                $message = "Hello " . ($admin_data['full_name'] ?? $admin_username) . ",\n\n";
                $message .= "Your administrator email address has been successfully set.\n\n";
                $message .= "Username: $admin_username\n";
                $message .= "Email: $new_email\n";
                $message .= "Set on: " . date('Y-m-d H:i:s') . "\n\n";
                $message .= "You will now receive system notifications and password reset emails at this address.\n\n";
                $message .= "If you did not make this change, please secure your account immediately.\n\n";
                $message .= "Best regards,\nFlexPBX Admin System";

                @mail($new_email, $subject, $message, "From: admin@flexpbx.devinecreations.net");

                // Mark email setup as complete in session
                $_SESSION['email_setup_complete'] = true;
                $_SESSION['admin_email'] = $new_email;

                // Redirect to dashboard after 2 seconds
                header("Refresh: 2; url=/admin/dashboard.html");
            } else {
                $error = 'Failed to save email address. Please check file permissions.';
            }
        } else {
            $error = 'Admin configuration error. Please contact system administrator.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Email - FlexPBX Admin</title>
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
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .setup-container {
            background: white;
            border-radius: 12px;
            padding: 2.5rem;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            max-width: 550px;
            width: 100%;
        }

        .header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .header h1 {
            color: #2c3e50;
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }

        .header p {
            color: #666;
            font-size: 0.95rem;
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

        .info-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 4px;
        }

        .info-box strong {
            color: #856404;
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

        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }

        .skip-link {
            text-align: center;
            margin-top: 1rem;
        }

        .skip-link a {
            color: #667eea;
            text-decoration: none;
            font-size: 0.9rem;
        }

        .skip-link a:hover {
            text-decoration: underline;
        }

        .icon {
            font-size: 3rem;
            text-align: center;
            margin-bottom: 1rem;
        }

        .requirement-list {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
        }

        .requirement-list ul {
            margin-left: 1.5rem;
            color: #555;
        }

        .requirement-list li {
            margin-bottom: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="icon">🔐</div>

        <div class="header">
            <h1>Setup Administrator Email</h1>
            <p>User: <?= htmlspecialchars($admin_username) ?></p>
            <span class="admin-badge">ADMIN</span>
        </div>

        <div class="info-box">
            <strong>Administrator Email Required</strong><br>
            As an administrator, you must set a valid email address for security purposes. This email will be used for password resets, system alerts, and security notifications.
        </div>

        <div class="requirement-list">
            <strong>Your email will be used for:</strong>
            <ul>
                <li>Password reset requests</li>
                <li>Critical system alerts</li>
                <li>Security notifications</li>
                <li>System backup reports</li>
                <li>Admin account recovery</li>
            </ul>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-error" role="alert">
            ⚠️ <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="alert alert-success" role="alert">
            ✓ <?= htmlspecialchars($success) ?>
            <br><small>Redirecting to admin dashboard...</small>
        </div>
        <?php else: ?>
        <form method="POST">
            <div class="form-group">
                <label for="email">Administrator Email Address *</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="admin@yourdomain.com"
                    required
                    autofocus
                    value="<?= htmlspecialchars($current_email && !in_array(strtolower($current_email), $placeholder_emails) ? $current_email : '') ?>"
                >
                <small>Use a professional email address you have access to</small>
            </div>

            <div class="form-group">
                <label for="confirm_email">Confirm Email Address *</label>
                <input
                    type="email"
                    id="confirm_email"
                    name="confirm_email"
                    placeholder="admin@yourdomain.com"
                    required
                >
                <small>Re-enter your email address to confirm</small>
            </div>

            <button type="submit" name="set_email" class="btn">Save Administrator Email</button>
        </form>

        <div class="skip-link">
            <a href="/admin/dashboard.html">Skip for now (not recommended for admins)</a>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Validate email match on form submit
        document.querySelector('form')?.addEventListener('submit', function(e) {
            const email = document.getElementById('email').value;
            const confirmEmail = document.getElementById('confirm_email').value;

            if (email !== confirmEmail) {
                e.preventDefault();
                alert('Email addresses do not match. Please check and try again.');
                document.getElementById('confirm_email').focus();
            }
        });
    </script>
</body>
</html>
