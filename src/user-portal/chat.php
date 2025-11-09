<?php
/**
 * FlexPBX User Chat Interface
 * User-facing Mattermost chat integration
 *
 * @author FlexPBX Development Team
 * @version 1.0.0
 * @created 2025-11-06
 */

session_start();

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header('Location: /user-portal/index.php');
    exit();
}

$username = $_SESSION['username'];
$extensionNumber = $_SESSION['extension'] ?? 'Unknown';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Chat - FlexPBX</title>
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
        }

        .page-container {
            max-width: 1600px;
            margin: 0 auto;
        }

        .page-header {
            background: white;
            padding: 20px 30px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-header h1 {
            font-size: 28px;
            color: #333;
            margin: 0;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 16px;
        }

        .user-details {
            text-align: right;
        }

        .user-details .name {
            font-weight: 600;
            color: #333;
        }

        .user-details .extension {
            font-size: 13px;
            color: #666;
        }

        .nav-links {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }

        .nav-links a {
            padding: 8px 16px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 6px;
            text-decoration: none;
            color: #333;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .nav-links a:hover {
            background: #f5f5f5;
            border-color: #667eea;
            color: #667eea;
        }

        .chat-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            height: calc(100vh - 180px);
            min-height: 500px;
        }

        .welcome-banner {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 30px;
            text-align: center;
        }

        .welcome-banner h2 {
            margin: 0 0 10px 0;
            font-size: 24px;
        }

        .welcome-banner p {
            margin: 0;
            opacity: 0.9;
        }

        .info-banner {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 15px 20px;
            margin: 20px;
            border-radius: 6px;
        }

        .info-banner h3 {
            margin: 0 0 10px 0;
            color: #333;
            font-size: 16px;
        }

        .info-banner ul {
            margin: 10px 0 0 20px;
            color: #666;
        }

        .info-banner li {
            margin-bottom: 5px;
        }

        @media (max-width: 768px) {
            body {
                padding: 10px;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .user-info {
                width: 100%;
                justify-content: space-between;
            }

            .nav-links {
                flex-direction: column;
                width: 100%;
            }

            .nav-links a {
                text-align: center;
            }

            .chat-container {
                height: calc(100vh - 250px);
            }
        }

        .loading-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 400px;
            color: #666;
        }

        .loading-state .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 20px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="page-container">
        <div class="page-header">
            <div>
                <h1>Team Chat</h1>
                <div class="nav-links">
                    <a href="/user-portal/dashboard.php">Dashboard</a>
                    <a href="/user-portal/index.php">Extension Portal</a>
                    <a href="/user-portal/logout.php">Logout</a>
                </div>
            </div>

            <div class="user-info">
                <div class="user-details">
                    <div class="name"><?php echo htmlspecialchars($username); ?></div>
                    <div class="extension">Extension: <?php echo htmlspecialchars($extensionNumber); ?></div>
                </div>
                <div class="user-avatar">
                    <?php echo strtoupper(substr($username, 0, 1)); ?>
                </div>
            </div>
        </div>

        <div class="chat-container">
            <?php
            // Check if Mattermost is configured
            $config = require_once(__DIR__ . '/../api/config.php');

            try {
                $db = new PDO(
                    "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4",
                    $config['db_user'],
                    $config['db_password'],
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );

                $stmt = $db->query("SELECT * FROM mattermost_config ORDER BY id DESC LIMIT 1");
                $mattermostConfig = $stmt->fetch();

                if ($mattermostConfig && $mattermostConfig['access_token']):
                    // Mattermost is configured, load the widget
                    include(__DIR__ . '/../includes/mattermost-widget.php');
                else:
                    // Mattermost not configured
                    ?>
                    <div class="welcome-banner">
                        <h2>Team Chat Coming Soon</h2>
                        <p>Real-time team communication integrated with FlexPBX</p>
                    </div>

                    <div class="info-banner">
                        <h3>Team Chat Features</h3>
                        <ul>
                            <li>Real-time messaging with your team members</li>
                            <li>Multiple channels for different departments</li>
                            <li>File sharing and collaboration</li>
                            <li>Integration with your phone system</li>
                            <li>Desktop and mobile notifications</li>
                        </ul>
                    </div>

                    <div class="info-banner">
                        <h3>Setup Required</h3>
                        <p>An administrator needs to configure the Mattermost integration before you can use team chat.</p>
                        <p style="margin-top: 10px;">Contact your system administrator to enable this feature.</p>
                    </div>
                <?php
                endif;

            } catch (PDOException $e) {
                ?>
                <div class="loading-state">
                    <p>Unable to load chat interface</p>
                    <p style="font-size: 14px; color: #999;">Please contact your administrator</p>
                </div>
                <?php
            }
            ?>
        </div>
    </div>

    <script>
        // Page-specific JavaScript
        console.log('FlexPBX Team Chat loaded for user: <?php echo $username; ?>');

        // Check for WebSocket support
        if (!window.WebSocket) {
            console.warn('WebSocket not supported in this browser');
        }

        // Notification permission request
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission().then(permission => {
                console.log('Notification permission:', permission);
            });
        }
    </script>
</body>
</html>
