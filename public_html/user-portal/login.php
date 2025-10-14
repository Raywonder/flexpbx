<?php
/**
 * FlexPBX User Portal - Login with Temporary Password Support
 * Secure login for extension users with auto-generated passwords
 */

session_start();

// Redirect if already logged in
if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true) {
    if (!isset($_SESSION['email_setup_complete'])) {
        header('Location: /user-portal/setup-email.php');
    } else {
        header('Location: /user-portal/');
    }
    exit;
}

$users_dir = '/home/flexpbxuser/users';
$login_error = '';

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $identifier = trim($_POST['extension'] ?? '');
    $password = $_POST['password'] ?? '';

    $authenticated = false;
    $user_data = null;
    $user_file = null;

    // Try to find and authenticate user from JSON files
    if (is_numeric($identifier)) {
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
                    $user_file = $file;
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

        // Update last login
        if ($user_file) {
            $user_data['last_login'] = date('Y-m-d H:i:s');
            $user_data['last_login_ip'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            file_put_contents($user_file, json_encode($user_data, JSON_PRETTY_PRINT));
        }

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
            header('Location: /user-portal/setup-email.php');
            exit;
        } else {
            $_SESSION['email_setup_complete'] = true;
            header('Location: /user-portal/');
            exit;
        }
    } else {
        $login_error = 'Invalid extension/username or password';
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: /user-portal/login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Login - FlexPBX</title>
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

        .user-badge {
            display: inline-block;
            background: linear-gradient(135deg, #4ade80 0%, #22c55e 100%);
            color: white;
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            margin-top: 0.5rem;
            letter-spacing: 0.5px;
        }

        .temp-password-box {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
            display: none;
        }

        .temp-password-box strong {
            color: #856404;
        }

        .temp-password-box code {
            background: #f8f9fa;
            padding: 0.2rem 0.4rem;
            border-radius: 3px;
            font-family: monospace;
            color: #333;
            font-size: 1.1rem;
            font-weight: bold;
        }

        .loading-box {
            background: #e3f2fd;
            border: 1px solid #2196f3;
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .loading-box strong {
            color: #1565c0;
        }

        .temp-password-lookup {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 1.5rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
        }

        .temp-password-lookup h3 {
            color: #2c3e50;
            font-size: 1rem;
            margin-bottom: 1rem;
        }

        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 0.8rem;
            margin-bottom: 1.5rem;
            border-radius: 4px;
            font-size: 0.85rem;
            color: #1565c0;
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

        .btn-secondary {
            background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
            margin-top: 0.5rem;
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

        #expiry-timer {
            display: block;
            margin-top: 0.5rem;
            font-weight: bold;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="icon">📞</div>

        <div class="header">
            <h1>FlexPBX User Portal</h1>
            <p>Extension Login</p>
            <span class="user-badge">USER ACCESS</span>
        </div>

        <!-- Temporary Password Lookup -->
        <div class="temp-password-lookup">
            <h3>🔐 Need a temporary password?</h3>
            <p style="font-size: 0.9rem; color: #666; margin-bottom: 1rem;">
                If you haven't set your email yet, enter your extension or username below to get a temporary password:
            </p>
            <div class="form-group" style="margin-bottom: 0.5rem;">
                <input
                    type="text"
                    id="lookup-identifier"
                    placeholder="Extension (e.g., 2006) or username"
                >
            </div>
            <button type="button" onclick="fetchTempPassword()" class="btn btn-secondary">
                Get Temporary Password
            </button>
        </div>

        <!-- Loading Box -->
        <div class="loading-box" id="loading-box" style="display: none;">
            <strong>🔐 Generating secure temporary password...</strong><br>
            <small>Please wait...</small>
        </div>

        <!-- Temporary Password Display -->
        <div class="temp-password-box" id="temp-password-box">
            <strong>🔒 Temporary Password:</strong><br>
            Extension/Username: <code id="temp-identifier-display">-</code><br>
            Password: <code id="temp-password-display">-</code>
            <button type="button" onclick="copyPassword()" style="margin-left: 10px; padding: 4px 8px; font-size: 0.8rem; cursor: pointer;">Copy</button>
            <span id="expiry-timer"></span>
            <small style="display: block; margin-top: 0.5rem; color: #666;">
                This password expires in 15 minutes. Set your email after login to use your own password.
            </small>
        </div>

        <div class="info-box">
            🔒 Secure login with auto-generated temporary passwords. Enter your credentials below.
        </div>

        <?php if ($login_error): ?>
        <div class="alert" role="alert">
            ⚠️ <?= htmlspecialchars($login_error) ?>
        </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="extension">Extension / Username</label>
                <input
                    type="text"
                    id="extension"
                    name="extension"
                    placeholder="e.g., 2006 or walterharper"
                    required
                    autofocus
                    autocomplete="username"
                >
                <small>Enter your extension number, username, or email</small>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter your password"
                    required
                    autocomplete="current-password"
                >
            </div>

            <button type="submit" name="login" class="btn">Login to User Portal</button>
        </form>

        <div class="links">
            <a href="forgot-password.php">Forgot Password?</a> |
            <a href="signup.php">Sign Up</a> |
            <a href="/">Back to Home</a>
        </div>
    </div>

    <script>
        let tempPasswordData = null;
        let countdownInterval = null;

        // Fetch temporary password when requested
        async function fetchTempPassword() {
            const identifier = document.getElementById('lookup-identifier').value.trim();

            if (!identifier) {
                alert('Please enter your extension or username first');
                return;
            }

            // Show loading
            document.getElementById('loading-box').style.display = 'block';
            document.getElementById('temp-password-box').style.display = 'none';

            try {
                const response = await fetch(`/api/generate-temp-password.php?identifier=${encodeURIComponent(identifier)}&account_type=user`);
                const data = await response.json();

                document.getElementById('loading-box').style.display = 'none';

                if (data.success && data.show_temp_password) {
                    tempPasswordData = data;

                    // Show password box
                    document.getElementById('temp-password-box').style.display = 'block';
                    document.getElementById('temp-identifier-display').textContent = data.identifier;
                    document.getElementById('temp-password-display').textContent = data.password;

                    // Auto-fill login form
                    document.getElementById('extension').value = data.identifier;
                    document.getElementById('password').value = data.password;

                    // Start countdown
                    startCountdown(data.expires);
                } else {
                    alert(data.message || 'Could not generate temporary password. Account may already be configured.');
                }
            } catch (error) {
                document.getElementById('loading-box').style.display = 'none';
                console.error('Failed to fetch temp password:', error);
                alert('Failed to generate temporary password. Please try again or contact support.');
            }
        }

        // Start countdown timer
        function startCountdown(expiryTimestamp) {
            const timerElement = document.getElementById('expiry-timer');

            if (countdownInterval) {
                clearInterval(countdownInterval);
            }

            countdownInterval = setInterval(() => {
                const now = Math.floor(Date.now() / 1000);
                const remaining = expiryTimestamp - now;

                if (remaining <= 0) {
                    clearInterval(countdownInterval);
                    timerElement.textContent = '⚠️ Password expired! Get new password.';
                    timerElement.style.color = '#c33';

                    // Clear password field
                    document.getElementById('password').value = '';
                    document.getElementById('password').placeholder = 'Password expired - get new one';
                } else {
                    const minutes = Math.floor(remaining / 60);
                    const seconds = remaining % 60;
                    timerElement.textContent = `⏱️ Expires in: ${minutes}m ${seconds}s`;

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

        // Clean up interval on page unload
        window.addEventListener('beforeunload', () => {
            if (countdownInterval) {
                clearInterval(countdownInterval);
            }
        });
    </script>
</body>
</html>
