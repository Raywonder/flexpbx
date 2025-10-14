<?php
/**
 * FlexPBX Admin Portal - Forgot Password
 * Sends password reset email to admin
 */

session_start();

// Configuration
$reset_tokens_dir = '/home/flexpbxuser/reset_tokens';
$admins_dir = '/home/flexpbxuser/admins';

if (!file_exists($reset_tokens_dir)) {
    mkdir($reset_tokens_dir, 0750, true);
}

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');

    if (empty($identifier)) {
        $error_message = 'Please enter your username or email address.';
    } else {
        // Search for admin by username or email
        $admin_found = false;
        $admin_data = null;
        $admin_file = '';

        // Try username first
        $possible_file = $admins_dir . '/admin_' . $identifier . '.json';
        if (file_exists($possible_file)) {
            $admin_data = json_decode(file_get_contents($possible_file), true);
            $admin_file = $possible_file;
            $admin_found = true;
        }

        // If not found by username, search all files by email
        if (!$admin_found && file_exists($admins_dir)) {
            $admin_files = glob($admins_dir . '/admin_*.json');
            foreach ($admin_files as $file) {
                $data = json_decode(file_get_contents($file), true);
                if (isset($data['email']) && strtolower($data['email']) === strtolower($identifier)) {
                    $admin_data = $data;
                    $admin_file = $file;
                    $admin_found = true;
                    break;
                }
            }
        }

        if ($admin_found && $admin_data) {
            // Generate reset token
            $token = bin2hex(random_bytes(32));
            $token_data = [
                'token' => $token,
                'username' => $admin_data['username'],
                'email' => $admin_data['email'],
                'created' => time(),
                'expires' => time() + 3600, // 1 hour expiry
                'type' => 'admin'
            ];

            $token_file = $reset_tokens_dir . '/token_' . $token . '.json';
            file_put_contents($token_file, json_encode($token_data, JSON_PRETTY_PRINT));
            chmod($token_file, 0640);

            // Generate reset link
            $reset_link = 'https://' . $_SERVER['HTTP_HOST'] . '/admin/reset-password.php?token=' . $token;

            // Send email
            $to = $admin_data['email'];
            $subject = 'FlexPBX Admin Password Reset Request';
            $message = "Hello " . $admin_data['full_name'] . ",\n\n";
            $message .= "You requested a password reset for your FlexPBX admin account.\n\n";
            $message .= "Username: " . $admin_data['username'] . "\n";
            $message .= "Role: " . $admin_data['role'] . "\n\n";
            $message .= "Click the link below to reset your password:\n";
            $message .= $reset_link . "\n\n";
            $message .= "This link will expire in 1 hour.\n\n";
            $message .= "If you did not request this reset, please ignore this email and contact support immediately.\n\n";
            $message .= "---\n";
            $message .= "FlexPBX Admin System\n";
            $message .= "https://flexpbx.devinecreations.net/admin/\n";

            $headers = "From: FlexPBX Admin <noreply@devinecreations.net>\r\n";
            $headers .= "Reply-To: support@devine-creations.com\r\n";
            $headers .= "X-Mailer: FlexPBX Admin Password Reset\r\n";

            if (mail($to, $subject, $message, $headers)) {
                $success_message = 'Password reset instructions have been sent to your email address.';
            } else {
                $error_message = 'Failed to send email. Please contact support.';
            }
        } else {
            // Don't reveal if admin exists or not (security best practice)
            $success_message = 'If an account exists with that information, password reset instructions have been sent.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - FlexPBX Admin Portal</title>
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

        input[type="text"],
        input[type="email"] {
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

        .back-link {
            text-align: center;
            margin-top: 20px;
        }

        .back-link a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
        }

        .back-link a:hover {
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
    </style>
</head>
<body>
    <div class="container">
        <h1>Forgot Password <span class="admin-badge">ADMIN</span></h1>
        <p class="subtitle">FlexPBX Admin Portal</p>

        <?php if ($success_message): ?>
            <div class="alert alert-success" role="alert">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-error" role="alert">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if (!$success_message): ?>
            <div class="info-box">
                Enter your admin username or email address. We'll send you instructions to reset your password.
            </div>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="identifier">Admin Username or Email Address</label>
                    <input
                        type="text"
                        id="identifier"
                        name="identifier"
                        required
                        placeholder="e.g., admin or admin@example.com"
                        autocomplete="username"
                    >
                    <p class="help-text">Enter your admin username or registered email address</p>
                </div>

                <button type="submit">Send Reset Instructions</button>
            </form>
        <?php endif; ?>

        <div class="back-link">
            <a href="index.html">← Back to Admin Login</a>
        </div>
    </div>
</body>
</html>
