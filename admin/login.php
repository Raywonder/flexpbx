<?php
/**
 * FlexPBX Admin Portal - Login
 * Admin authentication with email setup detection
 */

session_start();

// Get redirect and timeout parameters
$redirect_url = $_GET['redirect'] ?? '/admin/dashboard.php';
$timeout_message = isset($_GET['timeout']) ? 'Your session has timed out due to inactivity. Please login again.' : '';

// Security messages
$security_message = '';
if (isset($_GET['security'])) {
    switch ($_GET['security']) {
        case 'ip_changed':
            $security_message = 'Your IP address changed during the session. Please login again for security.';
            break;
        case 'ua_changed':
            $security_message = 'Your browser fingerprint changed. Please login again for security.';
            break;
    }
}

// Logout message
$logout_message = '';
if (isset($_GET['logout']) && $_GET['logout'] === 'success') {
    $logout_message = 'You have been successfully logged out.';
}

// Store redirect in session for after login
if (!empty($redirect_url) && $redirect_url !== '/admin/dashboard.php' && $redirect_url !== '/admin/dashboard.html') {
    $_SESSION['redirect_after_login'] = $redirect_url;
}

// Check for remember me cookie
if (!isset($_SESSION['admin_logged_in']) && isset($_COOKIE['flexpbx_remember_admin'])) {
    $cookie_data = explode(':', $_COOKIE['flexpbx_remember_admin'], 2);
    if (count($cookie_data) === 2) {
        list($username, $token) = $cookie_data;
        $admin_file = '/home/flexpbxuser/admins/admin_' . $username . '.json';

        if (file_exists($admin_file)) {
            $admin_data = json_decode(file_get_contents($admin_file), true);
            if (isset($admin_data['remember_tokens'])) {
                // Check if token is valid
                foreach ($admin_data['remember_tokens'] as $stored_token) {
                    if (isset($stored_token['expires']) && $stored_token['expires'] > time()) {
                        if (password_verify($token, $stored_token['token'])) {
                            // Valid token - auto login
                            $_SESSION['admin_logged_in'] = true;
                            $_SESSION['admin_username'] = $admin_data['username'];
                            $_SESSION['admin_role'] = $admin_data['role'] ?? 'admin';
                            $_SESSION['admin_full_name'] = $admin_data['full_name'] ?? $admin_data['username'];
                            $_SESSION['email_setup_complete'] = !empty($admin_data['email']);
                            break;
                        }
                    }
                }
            }
        }
    }
}

// Redirect if already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    // Check if email setup is needed
    if (!isset($_SESSION['email_setup_complete'])) {
        header('Location: /admin/setup-email.php');
    } else {
        // Redirect to stored URL or default
        $redirect = $_SESSION['redirect_after_login'] ?? '/admin/dashboard.php';
        unset($_SESSION['redirect_after_login']);
        header('Location: ' . $redirect);
    }
    exit;
}

$admins_dir = '/home/flexpbxuser/admins';
$login_error = '';

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $identifier = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $authenticated = false;
    $admin_data = null;

    // Try to find admin by username
    $admin_file = $admins_dir . '/admin_' . $identifier . '.json';
    if (file_exists($admin_file)) {
        $admin_data = json_decode(file_get_contents($admin_file), true);
        if (isset($admin_data['password']) && password_verify($password, $admin_data['password'])) {
            $authenticated = true;
        }
    }

    // If not found by username, search by email
    if (!$authenticated && file_exists($admins_dir)) {
        $admin_files = glob($admins_dir . '/admin_*.json');
        foreach ($admin_files as $file) {
            $temp_data = json_decode(file_get_contents($file), true);
            if ((isset($temp_data['username']) && $temp_data['username'] === $identifier) ||
                (isset($temp_data['email']) && strtolower($temp_data['email']) === strtolower($identifier))) {
                if (isset($temp_data['password']) && password_verify($password, $temp_data['password'])) {
                    $admin_data = $temp_data;
                    $authenticated = true;
                    break;
                }
            }
        }
    }

    if ($authenticated && $admin_data) {
        // Set session variables
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $admin_data['username'];
        $_SESSION['admin_role'] = $admin_data['role'] ?? 'admin';
        $_SESSION['admin_full_name'] = $admin_data['full_name'] ?? $admin_data['username'];

        // Handle "Stay logged in" checkbox
        $stay_logged_in = isset($_POST['remember_login']) && $_POST['remember_login'] == '1';
        $_SESSION['remember_login'] = $stay_logged_in;
        $_SESSION['last_activity'] = time();

        if ($stay_logged_in) {
            // Extended session - no idle timeout
            $_SESSION['idle_timeout'] = 0; // No timeout
            $_SESSION['session_expires'] = time() + (30 * 24 * 60 * 60); // 30 days
        } else {
            // Short session with 30-minute idle timeout
            $_SESSION['idle_timeout'] = 1800; // 30 minutes
            $_SESSION['session_expires'] = time() + 1800;
        }

        // Handle Remember Me (persistent cookie)
        if (isset($_POST['remember_me']) && $_POST['remember_me'] == '1') {
            // Generate secure remember token
            $remember_token = bin2hex(random_bytes(32));
            $remember_expiry = time() + (30 * 24 * 60 * 60); // 30 days

            // Store token in admin data
            if (!isset($admin_data['remember_tokens'])) {
                $admin_data['remember_tokens'] = [];
            }
            $admin_data['remember_tokens'][] = [
                'token' => password_hash($remember_token, PASSWORD_DEFAULT),
                'created' => time(),
                'expires' => $remember_expiry,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ];

            // Set secure cookie
            setcookie(
                'flexpbx_remember_admin',
                $admin_data['username'] . ':' . $remember_token,
                $remember_expiry,
                '/',
                '',
                isset($_SERVER['HTTPS']),
                true // HttpOnly
            );
        }

        // Update last login in file
        $admin_data['last_login'] = date('Y-m-d H:i:s');
        $admin_data['last_login_ip'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $admin_data['last_login_user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        file_put_contents($admin_file, json_encode($admin_data, JSON_PRETTY_PRINT));

        // Check if email setup is needed
        $admin_email = $admin_data['email'] ?? '';
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

        $needs_email_setup = empty($admin_email) || in_array(strtolower($admin_email), $placeholder_emails);

        if ($needs_email_setup) {
            // Redirect to email setup
            header('Location: /admin/setup-email.php');
            exit;
        } else {
            // Email is set, redirect to stored URL or default
            $_SESSION['email_setup_complete'] = true;
            $_SESSION['admin_email'] = $admin_email;

            $redirect = $_SESSION['redirect_after_login'] ?? '/admin/dashboard.php';
            unset($_SESSION['redirect_after_login']);
            header('Location: ' . $redirect);
            exit;
        }
    } else {
        $login_error = 'Invalid username or password';
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: /admin/login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - FlexPBX</title>
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

        .login-container {
            background: white;
            border-radius: 12px;
            padding: 2.5rem;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            max-width: 450px;
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
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
            color: white;
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            margin-top: 0.5rem;
            letter-spacing: 0.5px;
        }

        .alert {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
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

        .links {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.9rem;
        }

        .links a {
            color: #667eea;
            text-decoration: none;
        }

        .links a:hover {
            text-decoration: underline;
        }

        .icon {
            font-size: 3.5rem;
            text-align: center;
            margin-bottom: 1rem;
        }

        .security-note {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 0.8rem;
            margin-bottom: 1.5rem;
            border-radius: 4px;
            font-size: 0.85rem;
            color: #1565c0;
        }

        .default-creds {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
        }

        .default-creds strong {
            color: #856404;
        }

        .default-creds code {
            background: #f8f9fa;
            padding: 0.2rem 0.4rem;
            border-radius: 3px;
            font-family: monospace;
            color: #333;
        }

        .loading-creds {
            background: #e3f2fd;
            border: 1px solid #2196f3;
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .loading-creds strong {
            color: #1565c0;
        }

        #temp-password-display {
            font-size: 1.1rem;
            font-weight: bold;
            color: #2c3e50;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="icon">🔐</div>

        <div class="header">
            <h1>FlexPBX Admin Login</h1>
            <p>Administrator Control Panel</p>
            <span class="admin-badge">RESTRICTED ACCESS</span>
        </div>

        <div class="default-creds" id="temp-password-box" style="display: none;">
            <strong>🔒 Auto-Generated Temporary Password:</strong><br>
            Username: <code>admin</code><br>
            Password: <code id="temp-password-display">Loading...</code>
            <button type="button" onclick="copyPassword()" style="margin-left: 10px; padding: 4px 8px; font-size: 0.8rem; cursor: pointer;">Copy</button>
            <br>
            <small id="expiry-timer" style="color: #856404; font-weight: bold;"></small>
            <br>
            <small style="color: #666;">This password expires in 15 minutes for security. Set your email after login to use your own password.</small>
        </div>

        <div class="loading-creds" id="loading-box">
            <strong>🔐 Generating secure temporary password...</strong><br>
            <small>Please wait...</small>
        </div>

        <div class="security-note">
            🔒 This is a secure admin area. All login attempts are logged and monitored.
        </div>

        <?php if ($logout_message): ?>
        <div class="alert" role="alert" style="background: #d4edda; color: #155724; border: 1px solid #4ade80;">
            ✓ <?= htmlspecialchars($logout_message) ?>
        </div>
        <?php endif; ?>

        <?php if ($security_message): ?>
        <div class="alert" role="alert" style="background: #fff3cd; color: #856404; border: 1px solid #ffc107;">
            🔒 <?= htmlspecialchars($security_message) ?>
        </div>
        <?php endif; ?>

        <?php if ($timeout_message): ?>
        <div class="alert" role="alert" style="background: #fff3cd; color: #856404; border: 1px solid #ffc107;">
            ⏱️ <?= htmlspecialchars($timeout_message) ?>
        </div>
        <?php endif; ?>

        <?php if ($login_error): ?>
        <div class="alert" role="alert">
            ⚠️ <?= htmlspecialchars($login_error) ?>
        </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="username">Username</label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    placeholder="Enter your admin username"
                    required
                    autofocus
                    autocomplete="username"
                >
                <small>You can also use your email address</small>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div style="position: relative;">
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Enter your password"
                        required
                        autocomplete="current-password"
                        style="padding-right: 45px;"
                    >
                    <button
                        type="button"
                        id="togglePassword"
                        onclick="togglePasswordVisibility()"
                        style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; font-size: 1.2rem; padding: 0; color: #666;"
                        aria-label="Toggle password visibility"
                        tabindex="-1"
                    >
                        <span id="toggleIcon">👁️</span>
                    </button>
                </div>
            </div>

            <div class="form-group" style="margin-bottom: 1rem;">
                <label style="display: flex; align-items: center; cursor: pointer; font-weight: normal;">
                    <input
                        type="checkbox"
                        name="remember_login"
                        id="remember_login"
                        value="1"
                        checked
                        style="margin-right: 0.5rem; cursor: pointer;"
                    >
                    <span>Stay logged in?</span>
                </label>
                <small style="color: #666; display: block; margin-top: 0.3rem; margin-left: 1.5rem;">
                    <strong>If checked:</strong> Session lasts 30 days on trusted networks, 7 days on public networks<br>
                    <strong>If unchecked:</strong> Auto-logout after 30 minutes of inactivity
                </small>
            </div>

            <div class="form-group" style="margin-bottom: 1rem;">
                <label style="display: flex; align-items: center; cursor: pointer; font-weight: normal;">
                    <input
                        type="checkbox"
                        name="remember_me"
                        id="remember_me"
                        value="1"
                        style="margin-right: 0.5rem; cursor: pointer;"
                    >
                    <span>Remember me on this device</span>
                </label>
                <small style="color: #666; display: block; margin-top: 0.3rem; margin-left: 1.5rem;">Keep me logged in even after browser closes (30 days)</small>
            </div>

            <button type="submit" name="login" class="btn">Login to Admin Panel</button>
        </form>

        <div class="links">
            <a href="forgot-password.php">Forgot Password?</a> |
            <a href="/admin/dashboard.html">Dashboard</a> |
            <a href="/">Back to Home</a>
        </div>
    </div>

    <script>
        let tempPasswordData = null;
        let countdownInterval = null;

        // Toggle password visibility
        function togglePasswordVisibility() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.textContent = '🙈';
                passwordInput.setAttribute('aria-label', 'Password visible');
            } else {
                passwordInput.type = 'password';
                toggleIcon.textContent = '👁️';
                passwordInput.setAttribute('aria-label', 'Password hidden');
            }
        }

        // Keyboard shortcut: Alt+V to toggle password visibility
        document.addEventListener('keydown', (e) => {
            if (e.altKey && e.key === 'v') {
                e.preventDefault();
                togglePasswordVisibility();
            }
        });

        // Fetch temporary password on page load
        async function fetchTempPassword() {
            try {
                const response = await fetch('/api/generate-temp-password.php?username=admin&account_type=admin');
                const data = await response.json();

                if (data.success && data.show_temp_password) {
                    tempPasswordData = data;

                    // Hide loading, show password
                    document.getElementById('loading-box').style.display = 'none';
                    document.getElementById('temp-password-box').style.display = 'block';
                    document.getElementById('temp-password-display').textContent = data.password;

                    // Auto-fill password field
                    document.getElementById('password').value = data.password;

                    // Start countdown timer
                    startCountdown(data.expires);
                } else {
                    // Account already configured
                    document.getElementById('loading-box').style.display = 'none';
                }
            } catch (error) {
                console.error('Failed to fetch temp password:', error);
                document.getElementById('loading-box').innerHTML = '<strong>⚠️ Failed to generate password</strong><br><small>Please contact administrator</small>';
            }
        }

        // Start countdown timer
        function startCountdown(expiryTimestamp) {
            const timerElement = document.getElementById('expiry-timer');

            countdownInterval = setInterval(() => {
                const now = Math.floor(Date.now() / 1000);
                const remaining = expiryTimestamp - now;

                if (remaining <= 0) {
                    clearInterval(countdownInterval);
                    timerElement.textContent = '⚠️ Password expired! Refresh page for new password.';
                    timerElement.style.color = '#c33';

                    // Disable form
                    document.getElementById('password').value = '';
                    document.getElementById('password').placeholder = 'Password expired - refresh page';
                    document.querySelector('button[name="login"]').disabled = true;
                } else {
                    const minutes = Math.floor(remaining / 60);
                    const seconds = remaining % 60;
                    timerElement.textContent = `⏱️ Expires in: ${minutes}m ${seconds}s`;

                    // Warning when under 2 minutes
                    if (remaining < 120) {
                        timerElement.style.color = '#ff6b6b';
                    }
                }
            }, 1000);
        }

        // Copy password to clipboard
        function copyPassword() {
            const password = document.getElementById('temp-password-display').textContent;
            navigator.clipboard.writeText(password).then(() => {
                const btn = event.target;
                const originalText = btn.textContent;
                btn.textContent = '✓ Copied!';
                setTimeout(() => {
                    btn.textContent = originalText;
                }, 2000);
            }).catch(err => {
                console.error('Failed to copy:', err);
                alert('Password: ' + password);
            });
        }

        // Load temp password on page load
        window.addEventListener('DOMContentLoaded', () => {
            fetchTempPassword();
        });

        // Clean up interval on page unload
        window.addEventListener('beforeunload', () => {
            if (countdownInterval) {
                clearInterval(countdownInterval);
            }
        });
    </script>
</body>
</html>
