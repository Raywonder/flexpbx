<?php
/**
 * FlexPBX Admin Portal - Link Extension
 * Allows admins to link their admin account to a user extension
 */

session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /admin/login.php');
    exit;
}

$admin_username = $_SESSION['admin_username'] ?? '';
$admins_dir = '/home/flexpbxuser/admins';
$users_dir = '/home/flexpbxuser/users';
$error = '';
$success = '';

// Load admin data
$admin_file = $admins_dir . '/admin_' . $admin_username . '.json';
$admin_data = null;

if (file_exists($admin_file)) {
    $admin_data = json_decode(file_get_contents($admin_file), true);
}

// Check if already linked
$linked_extension = $admin_data['linked_extension'] ?? null;

// Handle linking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['link_extension'])) {
    $extension = trim($_POST['extension'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($extension) || empty($password)) {
        $error = 'Extension and password are required.';
    } else {
        // Find and verify extension
        $user_file = null;
        $user_data = null;

        if (is_numeric($extension)) {
            $possible_file = $users_dir . '/user_' . $extension . '.json';
            if (file_exists($possible_file)) {
                $user_file = $possible_file;
                $user_data = json_decode(file_get_contents($user_file), true);
            }
        }

        if (!$user_data) {
            // Search by username
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

        if (!$user_data) {
            $error = 'Extension not found.';
        } elseif (!password_verify($password, $user_data['password'])) {
            $error = 'Incorrect extension password.';
        } else {
            // Link the accounts
            $admin_data['linked_extension'] = $user_data['extension'];
            $admin_data['linked_username'] = $user_data['username'] ?? null;
            $admin_data['linked_date'] = date('Y-m-d H:i:s');
            file_put_contents($admin_file, json_encode($admin_data, JSON_PRETTY_PRINT));

            // Link admin to extension
            $user_data['linked_admin'] = $admin_username;
            $user_data['linked_date'] = date('Y-m-d H:i:s');
            file_put_contents($user_file, json_encode($user_data, JSON_PRETTY_PRINT));

            $success = 'Extension ' . $user_data['extension'] . ' successfully linked to your admin account!';
            $linked_extension = $user_data['extension'];
        }
    }
}

// Handle unlinking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unlink_extension'])) {
    if ($linked_extension) {
        // Remove link from admin
        unset($admin_data['linked_extension']);
        unset($admin_data['linked_username']);
        unset($admin_data['linked_date']);
        file_put_contents($admin_file, json_encode($admin_data, JSON_PRETTY_PRINT));

        // Remove link from user
        if (is_numeric($linked_extension)) {
            $user_file = $users_dir . '/user_' . $linked_extension . '.json';
            if (file_exists($user_file)) {
                $user_data = json_decode(file_get_contents($user_file), true);
                unset($user_data['linked_admin']);
                unset($user_data['linked_date']);
                file_put_contents($user_file, json_encode($user_data, JSON_PRETTY_PRINT));
            }
        }

        $success = 'Extension unlinked successfully.';
        $linked_extension = null;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Link Extension - FlexPBX Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container { max-width: 650px; margin: 0 auto; }
        .header {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        h1 { color: #333; margin-bottom: 10px; }
        .subtitle { color: #666; font-size: 14px; }
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
        .alert-error { background: #fee; color: #c33; border: 1px solid #fcc; }
        .alert-success { background: #efe; color: #3c3; border: 1px solid #cfc; }
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 4px;
        }
        .form-group { margin-bottom: 1.5rem; }
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
        .btn-danger {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
        }
        .linked-info {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            padding: 1.5rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
        }
        .linked-info h3 {
            color: #155724;
            margin-bottom: 1rem;
        }
        .linked-info p {
            color: #155724;
            margin-bottom: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔗 Link Extension to Admin Account</h1>
            <p class="subtitle">Admin: <?= htmlspecialchars($admin_username) ?></p>
        </div>

        <div class="card">
            <?php if ($error): ?>
            <div class="alert alert-error">⚠️ <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div class="alert alert-success">✓ <?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <?php if ($linked_extension): ?>
            <div class="linked-info">
                <h3>✓ Extension Linked</h3>
                <p><strong>Extension:</strong> <?= htmlspecialchars($linked_extension) ?></p>
                <p><strong>Linked:</strong> <?= htmlspecialchars($admin_data['linked_date'] ?? 'Unknown') ?></p>
                <p style="margin-top: 1rem;">
                    <a href="/user-portal/" class="btn" style="width: auto; padding: 0.6rem 1.2rem;">Switch to User Portal</a>
                </p>
            </div>

            <div class="info-box">
                Want to link a different extension? Unlink the current one first.
            </div>

            <form method="POST">
                <button type="submit" name="unlink_extension" class="btn btn-danger">Unlink Extension</button>
                <a href="/admin/dashboard.html" class="btn btn-secondary">Back to Dashboard</a>
            </form>

            <?php else: ?>
            <div class="info-box">
                <strong>Link Your Extension:</strong> If you have a user extension (e.g., 2006), you can link it to your admin account. This allows you to easily switch between admin and user portals.
            </div>

            <form method="POST">
                <div class="form-group">
                    <label for="extension">Extension Number or Username</label>
                    <input
                        type="text"
                        id="extension"
                        name="extension"
                        placeholder="e.g., 2006 or username"
                        required
                        autofocus
                    >
                </div>

                <div class="form-group">
                    <label for="password">Extension Password</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Your extension password"
                        required
                    >
                    <small style="color: #666; font-size: 0.85rem;">Enter the password for this extension to verify ownership</small>
                </div>

                <button type="submit" name="link_extension" class="btn">Link Extension</button>
                <a href="/admin/dashboard.html" class="btn btn-secondary">Cancel</a>
            </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
