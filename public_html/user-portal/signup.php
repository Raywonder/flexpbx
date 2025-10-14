<?php
/**
 * FlexPBX User Sign-Up
 * Allow new users to request extension accounts
 */

session_start();

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $extension_requested = $_POST['extension'] ?? '';
    $reason = $_POST['reason'] ?? '';

    // Validate input
    if (empty($username) || empty($email) || empty($extension_requested)) {
        $error_message = "Please fill in all required fields";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email address";
    } elseif (!preg_match('/^[0-9]{4}$/', $extension_requested)) {
        $error_message = "Extension must be 4 digits";
    } else {
        // Save signup request to file
        $signup_data = [
            'timestamp' => date('Y-m-d H:i:s'),
            'username' => htmlspecialchars($username),
            'email' => htmlspecialchars($email),
            'extension' => htmlspecialchars($extension_requested),
            'reason' => htmlspecialchars($reason),
            'status' => 'pending'
        ];

        $signup_file = '/home/flexpbxuser/signups/user_' . $extension_requested . '_' . time() . '.json';
        @mkdir('/home/flexpbxuser/signups', 0755, true);
        file_put_contents($signup_file, json_encode($signup_data, JSON_PRETTY_PRINT));

        $success_message = "Sign-up request submitted! An administrator will review and contact you at $email";

        // Clear form
        $username = '';
        $email = '';
        $extension_requested = '';
        $reason = '';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Sign-Up - FlexPBX</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
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
            text-align: center;
            color: white;
            margin-bottom: 2rem;
        }
        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }
        .signup-box {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
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
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
        }
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        .form-group small {
            display: block;
            margin-top: 0.3rem;
            color: #666;
            font-size: 0.9rem;
        }
        .btn {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }
        .alert {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
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
        .back-link {
            text-align: center;
            margin-top: 1rem;
        }
        .back-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        .back-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📱 Sign Up for FlexPBX</h1>
            <p>Request a user extension account</p>
        </div>

        <div class="signup-box">
            <h2 style="margin-bottom: 1.5rem; color: #2c3e50;">Create Your Account</h2>

            <?php if ($success_message): ?>
            <div class="alert alert-success">
                ✓ <?= $success_message ?>
            </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
            <div class="alert alert-error">
                ⚠️ <?= $error_message ?>
            </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="username">Full Name *</label>
                    <input type="text" id="username" name="username" value="<?= htmlspecialchars($username ?? '') ?>" required autofocus aria-required="true">
                    <small>Your full name for the account</small>
                </div>

                <div class="form-group">
                    <label for="email">Email Address *</label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($email ?? '') ?>" required aria-required="true">
                    <small>We'll contact you at this address</small>
                </div>

                <div class="form-group">
                    <label for="extension">Requested Extension *</label>
                    <input type="text" id="extension" name="extension" pattern="[0-9]{4}" maxlength="4" value="<?= htmlspecialchars($extension_requested ?? '') ?>" placeholder="e.g., 2004" required aria-required="true">
                    <small>4-digit extension number (2000-9999)</small>
                </div>

                <div class="form-group">
                    <label for="reason">Reason for Account (Optional)</label>
                    <textarea id="reason" name="reason" placeholder="Tell us why you need access..."><?= htmlspecialchars($reason ?? '') ?></textarea>
                    <small>Helps us process your request faster</small>
                </div>

                <button type="submit" class="btn">Submit Sign-Up Request</button>
            </form>

            <div class="back-link">
                <a href="index.php">← Back to Login</a>
            </div>
        </div>
    </div>
</body>
</html>
