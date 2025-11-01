<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FlexPBX - Queue Manager</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 30px;
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
        }
        .login-form {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: 500;
        }
        input[type="text"],
        input[type="password"],
        select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }
        .btn-primary {
            background: #667eea;
            color: white;
        }
        .btn-primary:hover {
            background: #5568d3;
        }
        .btn-success {
            background: #28a745;
            color: white;
        }
        .btn-success:hover {
            background: #218838;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .btn-danger:hover {
            background: #c82333;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
        .status-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
        }
        .status-online {
            border-left-color: #28a745;
        }
        .status-offline {
            border-left-color: #dc3545;
        }
        .status-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 10px;
        }
        .status-info {
            color: #666;
            margin: 5px 0;
        }
        .queue-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            flex-wrap: wrap;
        }
        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
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
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .feature-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }
        .feature-title {
            font-weight: 600;
            margin-bottom: 10px;
            color: #333;
        }
        .feature-desc {
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
        }
        .dial-codes {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .dial-codes h3 {
            color: #856404;
            margin-bottom: 10px;
        }
        .code-list {
            list-style: none;
            padding: 0;
        }
        .code-list li {
            padding: 8px 0;
            color: #856404;
            font-family: monospace;
        }
        .code-list strong {
            color: #333;
        }
    </style>
</head>
<body>
    <div class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <div>
                <h1 style="margin: 0 0 5px 0;">FlexPBX Queue Manager</h1>
                <p class="subtitle" style="margin: 0;">Manage your queue membership and call settings</p>
            </div>
            <a href="/user-portal/" style="background: #667eea; color: white; padding: 10px 20px; text-decoration: none; border-radius: 6px; font-weight: 600;">← User Portal</a>
        </div>

        <?php
        // Simple session-based authentication
        session_start();

        // Handle login
        if (isset($_POST['login'])) {
            $extension = $_POST['extension'] ?? '';
            $password = $_POST['password'] ?? '';

            // Simple password check (in production, hash passwords!)
            $valid_extensions = [
                '2000' => 'FlexPBX2000!',
                '2001' => 'FlexPBX2001!',
                '2002' => 'FlexPBX2002!',
                '2003' => 'FlexPBX2003!'
            ];

            if (isset($valid_extensions[$extension]) && $valid_extensions[$extension] === $password) {
                $_SESSION['extension'] = $extension;
                $_SESSION['logged_in'] = true;
                echo '<div class="alert alert-success">Logged in as extension ' . htmlspecialchars($extension) . '</div>';
            } else {
                echo '<div class="alert alert-error">Invalid extension or password</div>';
            }
        }

        // Handle logout
        if (isset($_GET['logout'])) {
            session_destroy();
            header('Location: queue-manager.php');
            exit;
        }

        // Check if logged in
        $is_logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'];
        $extension = $_SESSION['extension'] ?? '';

        if (!$is_logged_in) {
            // Show login form
            ?>
            <div class="login-form">
                <h2>Login</h2>
                <form method="POST">
                    <div class="form-group">
                        <label>Extension</label>
                        <select name="extension" required>
                            <option value="">Select Extension...</option>
                            <option value="2000">2000 - Admin</option>
                            <option value="2001">2001 - Test User</option>
                            <option value="2002">2002 - Demo</option>
                            <option value="2003">2003 - Support</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" required>
                    </div>
                    <button type="submit" name="login" class="btn btn-primary">Login</button>
                </form>
            </div>
            <?php
        } else {
            // Show queue management interface
            ?>
            <div class="status-card status-online">
                <div class="status-title">Logged in as Extension <?php echo htmlspecialchars($extension); ?></div>
                <div class="status-info">Use the dial codes below or manage your queue status here</div>
                <div class="queue-actions">
                    <a href="?logout" class="btn btn-secondary">Logout</a>
                </div>
            </div>

            <div class="dial-codes">
                <h3>Quick Dial Codes</h3>
                <ul class="code-list">
                    <li><strong>*45</strong> - Login to Support Queue (start receiving calls)</li>
                    <li><strong>*46</strong> - Logout from Support Queue (stop receiving calls)</li>
                    <li><strong>*47</strong> - Check Queue Status</li>
                    <li><strong>9196</strong> - Echo Test</li>
                    <li><strong>2000-2003</strong> - Dial other extensions</li>
                </ul>
            </div>

            <h2>Queue Management</h2>
            <div class="feature-grid">
                <div class="feature-card">
                    <div class="feature-title">Join Support Queue</div>
                    <div class="feature-desc">Start receiving inbound calls from customers</div>
                    <p><strong>Dial *45</strong> from your phone to join the support queue. You'll hear a confirmation message.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-title">Leave Support Queue</div>
                    <div class="feature-desc">Stop receiving calls (break/lunch/end of shift)</div>
                    <p><strong>Dial *46</strong> from your phone to leave the queue. You'll hear a confirmation message.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-title">Queue Status</div>
                    <div class="feature-desc">Check how many agents are logged in</div>
                    <p><strong>Dial *47</strong> from your phone to hear the current queue status.</p>
                </div>
            </div>

            <div class="alert alert-info" style="margin-top: 30px;">
                <strong>How It Works:</strong> When you dial <strong>*45</strong> to login, you'll start receiving calls from the main support queue.
                Inbound calls will ring all logged-in agents simultaneously (ringall strategy). The first agent to answer gets the call.
                When you're on break or done for the day, dial <strong>*46</strong> to logout and stop receiving calls.
            </div>

            <h2>Extension Settings</h2>
            <div class="feature-grid">
                <div class="feature-card">
                    <div class="feature-title">Your Extension: <?php echo htmlspecialchars($extension); ?></div>
                    <div class="feature-desc">
                        <strong>SIP Username:</strong> <?php echo htmlspecialchars($extension); ?><br>
                        <strong>Server:</strong> flexpbx.devinecreations.net<br>
                        <strong>Port:</strong> 5060
                    </div>
                </div>

                <div class="feature-card">
                    <div class="feature-title">Call Features</div>
                    <div class="feature-desc">
                        ✓ Queue login/logout<br>
                        ✓ Extension-to-extension dialing<br>
                        ✓ Echo test available<br>
                        ✓ Inbound call handling
                    </div>
                </div>
            </div>

            <div class="alert alert-info" style="margin-top: 30px;">
                <strong>Coming Soon:</strong> Web-based call controls, real-time queue statistics, call history, and one-click login/logout buttons.
            </div>
            <?php
        }
        ?>
    </div>
</body>
</html>
