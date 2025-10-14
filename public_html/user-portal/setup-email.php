<?php
/**
 * FlexPBX User Portal - Email Setup
 * Prompts user to set email on first login if not configured
 */

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_extension']) || empty($_SESSION['user_extension'])) {
    header('Location: /user-portal/');
    exit;
}

$extension = $_SESSION['user_extension'];
$users_dir = '/home/flexpbxuser/users';
$user_file = null;
$user_data = null;
$error = '';
$success = '';

// Find user file
if (is_numeric($extension)) {
    $possible_file = $users_dir . '/user_' . $extension . '.json';
    if (file_exists($possible_file)) {
        $user_file = $possible_file;
    }
}

// If not found, search by username
if (!$user_file && file_exists($users_dir)) {
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

// If user file not found, load from database
$config = include('/home/flexpbxuser/public_html/api/config.php');

if (!$user_data) {
    try {
        $pdo = new PDO(
            "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4",
            $config['db_user'],
            $config['db_password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $stmt = $pdo->prepare("SELECT * FROM users WHERE extension = ? OR username = ? LIMIT 1");
        $stmt->execute([$extension, $extension]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Database error in setup-email.php: " . $e->getMessage());
    }
}

if (!$user_data) {
    // Create minimal user data if not found
    $user_data = [
        'extension' => $extension,
        'username' => $extension,
        'email' => '',
        'full_name' => 'User ' . $extension
    ];
}

// Check if email setup is already complete
$needs_email_setup = false;
$current_email = $user_data['email'] ?? '';

// List of placeholder/default email values that indicate email needs setup
$placeholder_emails = [
    '',
    'user@example.com',
    'admin@example.com',
    'noemail@localhost',
    'user@localhost',
    'test@test.com',
    'changeme@example.com'
];

if (empty($current_email) || in_array(strtolower($current_email), $placeholder_emails)) {
    $needs_email_setup = true;
}

// If email is already set and valid, redirect to dashboard
if (!$needs_email_setup && isset($_SESSION['email_setup_complete'])) {
    header('Location: /user-portal/');
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
        // Save email to user file
        if ($user_file) {
            $user_data['email'] = $new_email;
            $user_data['email_verified'] = false;
            $user_data['email_set_date'] = date('Y-m-d H:i:s');

            if (file_put_contents($user_file, json_encode($user_data, JSON_PRETTY_PRINT))) {
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
                        WHERE extension = ? OR username = ?
                    ");
                    $stmt->execute([$new_email, $extension, $extension]);
                } catch (Exception $e) {
                    error_log("Database update failed in setup-email.php: " . $e->getMessage());
                }

                // Send confirmation email
                $subject = "FlexPBX - Email Address Confirmed";
                $message = "Hello " . ($user_data['full_name'] ?? $extension) . ",\n\n";
                $message .= "Your email address has been successfully set for extension $extension.\n\n";
                $message .= "Email: $new_email\n";
                $message .= "Set on: " . date('Y-m-d H:i:s') . "\n\n";
                $message .= "You will now receive notifications at this email address.\n\n";
                $message .= "If you did not make this change, please contact your administrator immediately.\n\n";
                $message .= "Best regards,\nFlexPBX System";

                @mail($new_email, $subject, $message, "From: noreply@flexpbx.devinecreations.net");

                // Mark email setup as complete in session
                $_SESSION['email_setup_complete'] = true;
                $_SESSION['user_email'] = $new_email;

                // Redirect to dashboard after 2 seconds
                header("Refresh: 2; url=/user-portal/");
            } else {
                $error = 'Failed to save email address. Please try again.';
            }
        } else {
            $error = 'User file not found. Please contact your administrator.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Email - FlexPBX User Portal</title>
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
            max-width: 500px;
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

        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 4px;
        }

        .info-box strong {
            color: #1976d2;
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
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="icon">📧</div>

        <div class="header">
            <h1>Setup Your Email Address</h1>
            <p>Extension <?= htmlspecialchars($extension) ?></p>
        </div>

        <div class="info-box">
            <strong>Why set an email?</strong><br>
            Your email address is used for password resets, voicemail notifications, and important system alerts.
        </div>

        <?php if ($error): ?>
        <div class="alert alert-error" role="alert">
            ⚠️ <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="alert alert-success" role="alert">
            ✓ <?= htmlspecialchars($success) ?>
            <br><small>Redirecting to dashboard...</small>
        </div>
        <?php else: ?>
        <form method="POST">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="your.email@example.com"
                    required
                    autofocus
                    value="<?= htmlspecialchars($current_email && !in_array(strtolower($current_email), $placeholder_emails) ? $current_email : '') ?>"
                >
                <small>Enter a valid email address you have access to</small>
            </div>

            <div class="form-group">
                <label for="confirm_email">Confirm Email Address</label>
                <input
                    type="email"
                    id="confirm_email"
                    name="confirm_email"
                    placeholder="your.email@example.com"
                    required
                >
                <small>Re-enter your email address to confirm</small>
            </div>

            <button type="submit" name="set_email" class="btn">Save Email Address</button>
        </form>

        <div class="skip-link">
            <a href="/user-portal/">Skip for now (not recommended)</a>
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
