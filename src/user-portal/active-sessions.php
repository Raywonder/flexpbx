<?php
/**
 * FlexPBX User Portal - Active Sessions
 * View and manage devices/browsers currently logged into your account
 */

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: /user-portal/login.php');
    exit;
}

$user_extension = $_SESSION['user_extension'] ?? null;
$user_username = $_SESSION['user_username'] ?? 'User';

// Load user data to get remember tokens (active sessions)
$user_file = '/home/flexpbxuser/users/user_' . $user_extension . '.json';
$sessions = [];
$current_session_id = session_id();

if (file_exists($user_file)) {
    $user_data = json_decode(file_get_contents($user_file), true);

    // Get remember tokens (persistent sessions)
    if (isset($user_data['remember_tokens']) && is_array($user_data['remember_tokens'])) {
        foreach ($user_data['remember_tokens'] as $index => $token) {
            // Only show non-expired tokens
            if (isset($token['expires']) && $token['expires'] > time()) {
                $sessions[] = [
                    'id' => $index,
                    'type' => 'Persistent',
                    'ip' => $token['ip'] ?? 'Unknown',
                    'user_agent' => $token['user_agent'] ?? 'Unknown',
                    'created' => $token['created'] ?? time(),
                    'expires' => $token['expires'] ?? time(),
                    'last_activity' => $token['last_activity'] ?? $token['created'] ?? time(),
                    'is_current' => false // Remember tokens are separate from current session
                ];
            }
        }
    }
}

// Add current session
$sessions[] = [
    'id' => 'current',
    'type' => 'Current Session',
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
    'created' => $_SESSION['login_time'] ?? time(),
    'expires' => time() + 1800, // 30 minutes from now
    'last_activity' => time(),
    'is_current' => true
];

// Handle session termination
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'revoke' && isset($_POST['session_id'])) {
        $session_id = $_POST['session_id'];

        if ($session_id === 'current') {
            // Logout current session
            session_destroy();
            header('Location: /user-portal/login.php');
            exit;
        } else {
            // Revoke remember token
            if (file_exists($user_file)) {
                $user_data = json_decode(file_get_contents($user_file), true);

                if (isset($user_data['remember_tokens'][$session_id])) {
                    unset($user_data['remember_tokens'][$session_id]);

                    // Reindex array
                    $user_data['remember_tokens'] = array_values($user_data['remember_tokens']);

                    file_put_contents($user_file, json_encode($user_data, JSON_PRETTY_PRINT));

                    // Reload page to show updated list
                    header('Location: /user-portal/active-sessions.php?revoked=1');
                    exit;
                }
            }
        }
    } elseif ($_POST['action'] === 'revoke_all') {
        // Revoke all sessions except current
        if (file_exists($user_file)) {
            $user_data = json_decode(file_get_contents($user_file), true);
            $user_data['remember_tokens'] = [];
            file_put_contents($user_file, json_encode($user_data, JSON_PRETTY_PRINT));

            header('Location: /user-portal/active-sessions.php?revoked_all=1');
            exit;
        }
    }
}

// Helper function to parse user agent
function parseUserAgent($ua) {
    $browser = 'Unknown Browser';
    $os = 'Unknown OS';

    // Detect OS
    if (preg_match('/windows/i', $ua)) $os = 'Windows';
    elseif (preg_match('/mac os x/i', $ua)) $os = 'macOS';
    elseif (preg_match('/linux/i', $ua)) $os = 'Linux';
    elseif (preg_match('/android/i', $ua)) $os = 'Android';
    elseif (preg_match('/iphone|ipad|ipod/i', $ua)) $os = 'iOS';

    // Detect Browser
    if (preg_match('/edg/i', $ua)) $browser = 'Edge';
    elseif (preg_match('/chrome/i', $ua)) $browser = 'Chrome';
    elseif (preg_match('/safari/i', $ua)) $browser = 'Safari';
    elseif (preg_match('/firefox/i', $ua)) $browser = 'Firefox';
    elseif (preg_match('/opera|opr/i', $ua)) $browser = 'Opera';

    return "$browser on $os";
}

// Helper function to get device icon
function getDeviceIcon($ua) {
    if (preg_match('/mobile|android|iphone|ipad/i', $ua)) {
        return '📱';
    } elseif (preg_match('/tablet|ipad/i', $ua)) {
        return '📱';
    } else {
        return '💻';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Active Sessions - FlexPBX User Portal</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f5f7fa;
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
        }

        .page-header {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .page-header h1 {
            color: #2c3e50;
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }

        .page-header .subtitle {
            color: #666;
            font-size: 0.95rem;
        }

        .user-badge {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-left: 0.5rem;
        }

        .nav-buttons {
            display: flex;
            gap: 0.75rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .nav-button {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 1.2rem;
            border: none;
            border-radius: 8px;
            background: white;
            color: #2c3e50;
            text-decoration: none;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
        }

        .nav-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .info-card {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
        }

        .info-card h3 {
            color: #1976d2;
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
        }

        .info-card p {
            color: #555;
            line-height: 1.6;
        }

        .sessions-container {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .sessions-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #e2e8f0;
        }

        .sessions-header h2 {
            color: #2c3e50;
            font-size: 1.3rem;
        }

        .btn {
            padding: 0.6rem 1.2rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .btn-small {
            padding: 0.4rem 0.8rem;
            font-size: 0.85rem;
        }

        .session-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            border: 2px solid #e2e8f0;
            position: relative;
        }

        .session-card.current {
            border-color: #667eea;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
        }

        .session-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .session-title {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .session-title .icon {
            font-size: 2rem;
        }

        .session-info h3 {
            color: #2c3e50;
            font-size: 1.1rem;
            margin-bottom: 0.25rem;
        }

        .session-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-current {
            background: #667eea;
            color: white;
        }

        .badge-persistent {
            background: #28a745;
            color: white;
        }

        .session-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
        }

        .detail-label {
            font-size: 0.8rem;
            color: #666;
            margin-bottom: 0.25rem;
        }

        .detail-value {
            color: #2c3e50;
            font-weight: 500;
        }

        .session-actions {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e2e8f0;
        }

        @media (max-width: 768px) {
            .session-header {
                flex-direction: column;
                gap: 1rem;
            }

            .session-details {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <h1>📱 Active Sessions</h1>
            <p class="subtitle">
                Extension <?= htmlspecialchars($user_extension) ?>
                <span class="user-badge"><?= htmlspecialchars($user_username) ?></span>
            </p>
        </div>

        <!-- Navigation -->
        <div class="nav-buttons">
            <a href="/user-portal/" class="nav-button">🏠 Dashboard</a>
            <a href="/user-portal/settings.php" class="nav-button">⚙️ Settings</a>
            <a href="/user-portal/help.php" class="nav-button">❓ Help</a>
        </div>

        <?php if (isset($_GET['revoked'])): ?>
        <div class="alert alert-success">
            ✅ Session revoked successfully
        </div>
        <?php endif; ?>

        <?php if (isset($_GET['revoked_all'])): ?>
        <div class="alert alert-success">
            ✅ All other sessions have been revoked
        </div>
        <?php endif; ?>

        <!-- Info Card -->
        <div class="info-card">
            <h3>🔒 Manage Your Sessions</h3>
            <p>View all devices and browsers currently logged into your FlexPBX account. You can revoke access from any session if you don't recognize the device or location.</p>
        </div>

        <!-- Sessions List -->
        <div class="sessions-container">
            <div class="sessions-header">
                <h2>Your Active Sessions (<?= count($sessions) ?>)</h2>
                <?php if (count($sessions) > 1): ?>
                <form method="POST" style="display: inline;" onsubmit="return confirm('Revoke all other sessions? You will remain logged in on this device.')">
                    <input type="hidden" name="action" value="revoke_all">
                    <button type="submit" class="btn btn-danger btn-small">Revoke All Others</button>
                </form>
                <?php endif; ?>
            </div>

            <?php foreach ($sessions as $session): ?>
            <div class="session-card <?= $session['is_current'] ? 'current' : '' ?>">
                <div class="session-header">
                    <div class="session-title">
                        <div class="icon"><?= getDeviceIcon($session['user_agent']) ?></div>
                        <div class="session-info">
                            <h3><?= parseUserAgent($session['user_agent']) ?></h3>
                            <span class="session-badge <?= $session['is_current'] ? 'badge-current' : 'badge-persistent' ?>">
                                <?= htmlspecialchars($session['type']) ?>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="session-details">
                    <div class="detail-item">
                        <span class="detail-label">IP Address</span>
                        <span class="detail-value"><?= htmlspecialchars($session['ip']) ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">First Login</span>
                        <span class="detail-value"><?= date('M j, Y g:i A', $session['created']) ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Last Activity</span>
                        <span class="detail-value"><?= date('M j, Y g:i A', $session['last_activity']) ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Expires</span>
                        <span class="detail-value"><?= date('M j, Y g:i A', $session['expires']) ?></span>
                    </div>
                </div>

                <div class="session-actions">
                    <form method="POST" style="display: inline;" onsubmit="return confirm('<?= $session['is_current'] ? 'Logout from this session?' : 'Revoke access from this session?' ?>')">
                        <input type="hidden" name="action" value="revoke">
                        <input type="hidden" name="session_id" value="<?= htmlspecialchars($session['id']) ?>">
                        <button type="submit" class="btn btn-danger btn-small">
                            <?= $session['is_current'] ? '🚪 Logout' : '🔓 Revoke Access' ?>
                        </button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Navigation (Bottom) -->
        <div class="nav-buttons" style="margin-top: 2rem;">
            <a href="/user-portal/" class="nav-button">🏠 Dashboard</a>
            <a href="/user-portal/settings.php" class="nav-button">⚙️ Settings</a>
            <a href="/user-portal/help.php" class="nav-button">❓ Help</a>
        </div>
    </div>
</body>
</html>
