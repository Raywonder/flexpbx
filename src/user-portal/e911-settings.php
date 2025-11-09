<?php
/**
 * FlexPBX User Portal - E911 Settings
 * Manage emergency address information
 */

// Require authentication
require_once __DIR__ . '/user_auth_check.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E911 Settings - FlexPBX</title>
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

        .container {
            max-width: 800px;
            margin: 0 auto;
        }

        .card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            margin-bottom: 20px;
        }

        h1 {
            color: #333;
            margin-bottom: 10px;
        }

        .breadcrumb {
            color: #666;
            font-size: 14px;
            margin-bottom: 30px;
        }

        .breadcrumb a {
            color: #667eea;
            text-decoration: none;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }

        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/user_header.php'; ?>

    <div class="container">
        <div class="card">
            <h1>E911 Emergency Settings</h1>
            <div class="breadcrumb">
                <a href="/user-portal/">Dashboard</a> / E911 Settings
            </div>

            <div class="alert">
                <strong>Important:</strong> E911 settings are managed by your system administrator. Contact your admin to update your emergency address information.
            </div>

            <p style="margin-top: 20px;">
                Your extension is registered with the emergency services system. In case of emergency, dial 911 from any registered device.
            </p>

            <p style="margin-top: 15px;">
                <strong>Extension:</strong> <?php echo htmlspecialchars($user_extension); ?>
            </p>

            <div style="margin-top: 30px;">
                <a href="/user-portal/" class="btn">Back to Dashboard</a>
            </div>
        </div>
    </div>
</body>
</html>
