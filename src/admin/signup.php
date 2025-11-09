<?php
require_once __DIR__ . '/admin_auth_check.php';
/**
 * FlexPBX Admin Sign-Up
 * Allow new admins to request access
 */

session_start();

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $role = $_POST['role'] ?? '';
    $reason = $_POST['reason'] ?? '';

    // Validate input
    if (empty($username) || empty($email) || empty($role)) {
        $error_message = "Please fill in all required fields";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email address";
    } else {
        // Save signup request to file
        $signup_data = [
            'timestamp' => date('Y-m-d H:i:s'),
            'username' => htmlspecialchars($username),
            'email' => htmlspecialchars($email),
            'role' => htmlspecialchars($role),
            'reason' => htmlspecialchars($reason),
            'status' => 'pending'
        ];

        $signup_file = '/home/flexpbxuser/signups/admin_' . preg_replace('/[^a-z0-9]/i', '', $username) . '_' . time() . '.json';
        @mkdir('/home/flexpbxuser/signups', 0755, true);
        file_put_contents($signup_file, json_encode($signup_data, JSON_PRETTY_PRINT));

        $success_message = "Admin sign-up request submitted! The super admin will review and contact you at $email";

        // Clear form
        $username = '';
        $email = '';
        $role = '';
        $reason = '';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Sign-Up - FlexPBX</title>
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
        .form-group select,
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
        .warning {
            background: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔧 Admin Sign-Up - FlexPBX</h1>
            <p>Request administrator access</p>
        </div>

        <div class="signup-box">
            <h2 style="margin-bottom: 1rem; color: #2c3e50;">Request Admin Access</h2>

            <div class="warning">
                <strong>⚠️ Administrator Role:</strong> This is for PBX administrators only. If you need a user account, please <a href="../user-portal/signup.php">sign up as a user</a> instead.
            </div>

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
                    <small>Your full name</small>
                </div>

                <div class="form-group">
                    <label for="email">Email Address *</label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($email ?? '') ?>" required aria-required="true">
                    <small>We'll contact you at this address</small>
                </div>

                <div class="form-group">
                    <label for="role">Requested Role *</label>
                    <select id="role" name="role" required aria-required="true">
                        <option value="">-- Select Role --</option>
                        <option value="super-admin">Super Administrator</option>
                        <option value="admin">Administrator</option>
                        <option value="support">Support Staff</option>
                        <option value="operator">Operator</option>
                    </select>
                    <small>Choose the access level you need</small>
                </div>

                <div class="form-group">
                    <label for="reason">Reason for Admin Access *</label>
                    <textarea id="reason" name="reason" placeholder="Explain why you need administrator access..." required aria-required="true"><?= htmlspecialchars($reason ?? '') ?></textarea>
                    <small>Required for security approval</small>
                </div>

                <button type="submit" class="btn">Submit Admin Request</button>
            </form>

            <div class="back-link">
                <a href="dashboard.html">← Back to Dashboard</a> |
                <a href="../user-portal/signup.php">User Sign-Up</a>
            </div>
        </div>
    </div>
</body>
</html>
