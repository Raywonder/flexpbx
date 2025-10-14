<?php
/**
 * FlexPBX User Portal
 * Self-service portal for extension users to manage their settings
 */

session_start();

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_extension']) && !empty($_SESSION['user_extension']);

// Redirect to login page if not logged in
if (!$is_logged_in && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /user-portal/login.php');
    exit;
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $identifier = trim($_POST['extension'] ?? '');
    $password = $_POST['password'] ?? '';

    $users_dir = '/home/flexpbxuser/users';
    $authenticated = false;
    $user_data = null;

    // Try to find and authenticate user from JSON files
    if (is_numeric($identifier)) {
        // Try by extension number
        $user_file = $users_dir . '/user_' . $identifier . '.json';
        if (file_exists($user_file)) {
            $user_data = json_decode(file_get_contents($user_file), true);
            if (isset($user_data['password']) && password_verify($password, $user_data['password'])) {
                $authenticated = true;
            }
        }
    }

    // If not found, search by username or email
    if (!$authenticated && file_exists($users_dir)) {
        $files = glob($users_dir . '/user_*.json');
        foreach ($files as $file) {
            $temp_data = json_decode(file_get_contents($file), true);
            if ((isset($temp_data['username']) && $temp_data['username'] === $identifier) ||
                (isset($temp_data['extension']) && $temp_data['extension'] === $identifier) ||
                (isset($temp_data['email']) && $temp_data['email'] === $identifier)) {
                if (isset($temp_data['password']) && password_verify($password, $temp_data['password'])) {
                    $user_data = $temp_data;
                    $authenticated = true;
                    break;
                }
            }
        }
    }

    if ($authenticated && $user_data) {
        $_SESSION['user_extension'] = $user_data['extension'] ?? $identifier;
        $_SESSION['user_username'] = $user_data['username'] ?? $identifier;
        $_SESSION['user_logged_in'] = true;

        // Check if email setup is needed
        $user_email = $user_data['email'] ?? '';
        $placeholder_emails = [
            '',
            'user@example.com',
            'admin@example.com',
            'noemail@localhost',
            'user@localhost',
            'test@test.com',
            'changeme@example.com'
        ];

        $needs_email_setup = empty($user_email) || in_array(strtolower($user_email), $placeholder_emails);

        if ($needs_email_setup) {
            // Redirect to email setup
            header('Location: /user-portal/setup-email.php');
            exit;
        } else {
            // Email is set, continue to dashboard
            $_SESSION['email_setup_complete'] = true;
            header('Location: /user-portal/');
            exit;
        }
    } else {
        $login_error = "Invalid extension/username or password";
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: /user-portal/');
    exit;
}

$current_extension = $_SESSION['user_extension'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FlexPBX User Portal</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
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
        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        .login-box, .dashboard-box {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            max-width: 500px;
            margin: 0 auto;
        }
        .dashboard-box {
            max-width: 1000px;
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
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .btn.secondary {
            background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
            width: auto;
        }
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }
        .card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
            border: 1px solid #e2e8f0;
        }
        .card h3 {
            color: #2c3e50;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .card-icon {
            font-size: 1.5rem;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.8rem;
            padding: 0.5rem 0;
            border-bottom: 1px solid #e2e8f0;
        }
        .info-label {
            font-weight: 600;
            color: #666;
        }
        .info-value {
            color: #2c3e50;
        }
        .status-badge {
            display: inline-block;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }
        .status-badge.online {
            background: #d4edda;
            color: #155724;
        }
        .status-badge.offline {
            background: #f8d7da;
            color: #721c24;
        }
        .back-link {
            text-align: center;
            margin-top: 1rem;
        }
        .back-link a {
            color: white;
            text-decoration: none;
            opacity: 0.9;
        }
        .back-link a:hover {
            opacity: 1;
            text-decoration: underline;
        }
        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        .user-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #e2e8f0;
        }
        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📞 FlexPBX User Portal</h1>
            <p>Manage Your Extension Settings</p>
        </div>

        <?php if (!$is_logged_in): ?>
        <!-- Login Form -->
        <div class="login-box">
            <h2 style="margin-bottom: 1.5rem; color: #2c3e50; text-align: center;">Login to Your Extension</h2>

            <?php if (isset($login_error)): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 1rem; border-radius: 6px; margin-bottom: 1rem; border: 1px solid #f5c6cb;">
                ⚠️ <?= htmlspecialchars($login_error) ?>
            </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="extension">Extension Number</label>
                    <input type="text" id="extension" name="extension" placeholder="e.g., 2001" required autofocus>
                    <small style="color: #666; font-size: 0.9rem;">Enter your 4-digit extension number</small>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter your password" required>
                </div>

                <button type="submit" name="login" class="btn">Login</button>
            </form>

            <div class="back-link">
                <a href="/">← Back to Home</a> |
                <a href="signup.php">Sign Up for Account</a> |
                <a href="forgot-password.php">Forgot Password?</a>
            </div>
        </div>
        <?php else: ?>
        <!-- User Dashboard -->
        <div class="dashboard-box">
            <div class="user-header">
                <div class="user-info">
                    <div class="user-avatar"><?= substr($current_extension, -2) ?></div>
                    <div>
                        <h2 style="color: #2c3e50;">Extension <?= htmlspecialchars($current_extension) ?></h2>
                        <p style="color: #666;">User Account</p>
                    </div>
                </div>
                <a href="?logout=1" class="btn secondary">Logout</a>
            </div>

            <div class="dashboard-grid">
                <!-- Extension Status -->
                <div class="card">
                    <h3><span class="card-icon">📱</span> Extension Status</h3>
                    <div class="info-row">
                        <span class="info-label">Extension:</span>
                        <span class="info-value"><?= htmlspecialchars($current_extension) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">SIP Status:</span>
                        <span class="status-badge" id="sip-status-badge">⚪ Checking...</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Devices Connected:</span>
                        <span class="info-value" id="device-count">-</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Last Checked:</span>
                        <span class="info-value" id="last-checked">-</span>
                    </div>
                    <div style="margin-top: 1rem;">
                        <a href="notification-settings.php" class="btn secondary" style="text-align: center;">⚙️ Notification Settings</a>
                    </div>
                </div>

                <script>
                    // Check SIP status on load and periodically
                    const userExtension = '<?= addslashes($current_extension) ?>';
                    let statusCheckInterval = null;

                    async function checkSIPStatus() {
                        try {
                            const response = await fetch(`/api/sip-status.php?extension=${userExtension}`);
                            const data = await response.json();

                            if (data.success) {
                                const badge = document.getElementById('sip-status-badge');
                                const deviceCount = document.getElementById('device-count');
                                const lastChecked = document.getElementById('last-checked');

                                if (data.registered) {
                                    badge.className = 'status-badge online';
                                    badge.textContent = '🟢 Online';

                                    if (data.on_call) {
                                        badge.textContent = '📞 On Call';
                                    }
                                } else {
                                    badge.className = 'status-badge offline';
                                    badge.textContent = '🔴 Offline';
                                }

                                deviceCount.textContent = data.device_count || '0';
                                lastChecked.textContent = new Date(data.last_checked).toLocaleTimeString();
                            }
                        } catch (error) {
                            console.error('Failed to check SIP status:', error);
                        }
                    }

                    // Initial check
                    checkSIPStatus();

                    // Check every 30 seconds
                    statusCheckInterval = setInterval(checkSIPStatus, 30000);

                    // Cleanup on page unload
                    window.addEventListener('beforeunload', () => {
                        if (statusCheckInterval) {
                            clearInterval(statusCheckInterval);
                        }
                    });
                </script>

                <!-- SIP Settings -->
                <div class="card">
                    <h3><span class="card-icon">🔧</span> SIP Settings</h3>
                    <div class="info-row">
                        <span class="info-label">Server:</span>
                        <span class="info-value">flexpbx.devinecreations.net</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Port:</span>
                        <span class="info-value">5060</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Transport:</span>
                        <span class="info-value">UDP</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Codec:</span>
                        <span class="info-value">ulaw, alaw</span>
                    </div>
                    <button class="btn" style="margin-top: 1rem;">View Full Configuration</button>
                </div>

                <!-- Queue Management -->
                <div class="card">
                    <h3><span class="card-icon">🎧</span> Queue Management</h3>
                    <div class="info-row">
                        <span class="info-label">Queue Status:</span>
                        <span class="status-badge offline">Logged Out</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Login Code:</span>
                        <span class="info-value">Dial *45</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Logout Code:</span>
                        <span class="info-value">Dial *46</span>
                    </div>
                    <div class="action-buttons">
                        <a href="/queue-manager.php" class="btn" style="text-align: center;">Open Queue Manager</a>
                    </div>
                </div>

                <!-- My Recordings -->
                <div class="card">
                    <h3><span class="card-icon">🎙️</span> My Recordings</h3>
                    <div class="info-row">
                        <span class="info-label">Voicemail Greetings:</span>
                        <span class="info-value">Manage</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Personal Messages:</span>
                        <span class="info-value">Upload</span>
                    </div>
                    <div class="action-buttons">
                        <a href="my-recordings.php" class="btn" style="text-align: center;">Manage Recordings</a>
                    </div>
                </div>

                <!-- Voicemail Settings -->
                <div class="card">
                    <h3><span class="card-icon">📬</span> Voicemail Settings</h3>
                    <div class="info-row">
                        <span class="info-label">Status:</span>
                        <span class="status-badge online">✓ Enabled</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">New Messages:</span>
                        <span class="info-value">0</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Access Code:</span>
                        <span class="info-value">Dial *97</span>
                    </div>
                    <div class="action-buttons">
                        <a href="voicemail-settings.php" class="btn" style="text-align: center;">Manage Voicemail</a>
                    </div>
                </div>

                <!-- Call Statistics -->
                <div class="card">
                    <h3><span class="card-icon">📊</span> Call Statistics</h3>
                    <div class="info-row">
                        <span class="info-label">Calls Today:</span>
                        <span class="info-value">0</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Calls This Week:</span>
                        <span class="info-value">0</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Total Duration:</span>
                        <span class="info-value">0 minutes</span>
                    </div>
                    <button class="btn" style="margin-top: 1rem;">View Call History</button>
                </div>

                <!-- Quick Actions -->
                <div class="card">
                    <h3><span class="card-icon">⚡</span> Quick Actions</h3>
                    <div style="display: flex; flex-direction: column; gap: 0.8rem;">
                        <a href="change-password.php" class="btn" style="text-align: center;">Change Password</a>
                        <a href="setup-email.php" class="btn secondary" style="text-align: center;">Update Email</a>
                        <a href="forgot-password.php" class="btn secondary" style="text-align: center;">Reset Password</a>
                    </div>
                </div>

                <!-- Help & Support -->
                <div class="card">
                    <h3><span class="card-icon">❓</span> Quick Dial Codes</h3>
                    <div style="line-height: 1.8; color: #666;">
                        <p><strong>Queue Management:</strong></p>
                        <p>• Login to queue: Dial *45</p>
                        <p>• Logout from queue: Dial *46</p>
                        <p>• Queue status: Dial *47</p>
                        <p style="margin-top: 1rem;"><strong>Testing & Other:</strong></p>
                        <p>• Echo test: Dial 9196</p>
                        <p>• Extensions: Dial 2000-2003</p>
                    </div>
                    <a href="/queue-manager.php" class="btn" style="margin-top: 1rem; text-align: center;">Queue Manager Guide</a>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
