<?php
/**
 * Mattermost Setup Helper
 * Quick setup wizard for Mattermost integration
 */

session_start();

// Check if user is authenticated
if (!isset($_SESSION['admin_username'])) {
    header('Location: /admin/login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mattermost Setup Helper - FlexPBX</title>
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

        .wizard-container {
            max-width: 800px;
            width: 100%;
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }

        .wizard-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }

        .wizard-header h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .wizard-header p {
            opacity: 0.9;
            font-size: 16px;
        }

        .wizard-content {
            padding: 40px;
        }

        .step {
            margin-bottom: 40px;
            padding-bottom: 40px;
            border-bottom: 2px solid #f0f0f0;
        }

        .step:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .step-number {
            display: inline-block;
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 50%;
            text-align: center;
            line-height: 40px;
            font-weight: bold;
            font-size: 20px;
            margin-bottom: 15px;
        }

        .step h2 {
            color: #333;
            margin-bottom: 15px;
            font-size: 24px;
        }

        .step p {
            color: #666;
            line-height: 1.6;
            margin-bottom: 15px;
        }

        .code-block {
            background: #2c2d30;
            color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            overflow-x: auto;
            margin: 15px 0;
        }

        .code-block .comment {
            color: #6c757d;
        }

        .code-block .highlight {
            color: #ffc107;
        }

        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 15px 20px;
            margin: 15px 0;
            border-radius: 4px;
        }

        .success-box {
            background: #d4edda;
            border-left: 4px solid #28a745;
            padding: 15px 20px;
            margin: 15px 0;
            border-radius: 4px;
        }

        .warning-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px 20px;
            margin: 15px 0;
            border-radius: 4px;
        }

        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 16px;
            margin-right: 10px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #6c757d;
        }

        .credentials {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 20px;
            border-radius: 8px;
            margin: 15px 0;
        }

        .credentials strong {
            display: block;
            margin-bottom: 5px;
            color: #333;
        }

        .credentials code {
            background: white;
            padding: 5px 10px;
            border-radius: 4px;
            display: inline-block;
            margin-top: 5px;
            font-size: 14px;
            color: #667eea;
        }

        ol {
            margin-left: 20px;
            color: #666;
        }

        ol li {
            margin-bottom: 10px;
            line-height: 1.6;
        }

        .button-group {
            margin-top: 30px;
            text-align: center;
            padding-top: 30px;
            border-top: 2px solid #f0f0f0;
        }
    </style>
</head>
<body>
    <div class="wizard-container">
        <div class="wizard-header">
            <h1>Mattermost Setup Wizard</h1>
            <p>Quick guide to configure Mattermost integration in FlexPBX</p>
        </div>

        <div class="wizard-content">
            <div class="success-box">
                <strong>Good news!</strong> All required files and database tables have been created. Follow the steps below to complete the setup.
            </div>

            <!-- Step 1 -->
            <div class="step">
                <div class="step-number">1</div>
                <h2>Create Mattermost Access Token</h2>
                <p>You need a personal access token to authenticate FlexPBX with Mattermost.</p>

                <div class="credentials">
                    <strong>Mattermost Server:</strong>
                    <code>https://chat.tappedin.fm</code>

                    <strong style="margin-top: 15px;">Login Credentials:</strong>
                    <code>webmaster@tappedin.fm</code>
                    <code>WebMaster2024!TappedIn</code>
                </div>

                <p><strong>Steps to create token:</strong></p>
                <ol>
                    <li>Open <a href="https://chat.tappedin.fm" target="_blank">https://chat.tappedin.fm</a> in a new tab</li>
                    <li>Log in with the credentials above</li>
                    <li>Click your profile picture (top right corner)</li>
                    <li>Go to <strong>Account Settings</strong></li>
                    <li>Click <strong>Security</strong> in the left menu</li>
                    <li>Scroll down to <strong>Personal Access Tokens</strong></li>
                    <li>Click <strong>Create New Token</strong></li>
                    <li>Enter description: <code>FlexPBX Integration</code></li>
                    <li>Click <strong>Save</strong></li>
                    <li><strong>IMPORTANT:</strong> Copy the token immediately (you'll only see it once!)</li>
                </ol>

                <div class="warning-box">
                    <strong>Important:</strong> Save the token in a secure location. You will need it in the next step.
                </div>
            </div>

            <!-- Step 2 -->
            <div class="step">
                <div class="step-number">2</div>
                <h2>Configure FlexPBX Connection</h2>
                <p>Enter your Mattermost server details and access token in FlexPBX.</p>

                <p><strong>Configuration values:</strong></p>
                <div class="credentials">
                    <strong>Server URL:</strong>
                    <code>https://chat.tappedin.fm</code>

                    <strong style="margin-top: 15px;">Access Token:</strong>
                    <code>[Paste the token from Step 1]</code>

                    <strong style="margin-top: 15px;">Poll Interval:</strong>
                    <code>5</code> seconds (recommended)

                    <strong style="margin-top: 15px;">Enable Notifications:</strong>
                    <code>✓ Checked</code>
                </div>

                <div class="info-box">
                    <strong>Test the connection</strong> after saving to ensure everything works correctly.
                </div>
            </div>

            <!-- Step 3 -->
            <div class="step">
                <div class="step-number">3</div>
                <h2>Import Channels</h2>
                <p>Select which Mattermost channels you want to embed in FlexPBX.</p>

                <ol>
                    <li>Go to the <strong>Sync Channels</strong> tab</li>
                    <li>Click <strong>Load Teams & Channels</strong></li>
                    <li>For each team, click <strong>Load Channels</strong></li>
                    <li>Click <strong>Import</strong> next to channels you want</li>
                    <li>Recommended: Import at least one general/public channel</li>
                </ol>

                <div class="info-box">
                    <strong>Tip:</strong> Start with 2-3 channels. You can always import more later.
                </div>
            </div>

            <!-- Step 4 -->
            <div class="step">
                <div class="step-number">4</div>
                <h2>Set Default Channel</h2>
                <p>Choose which channel will be displayed by default when users access the chat.</p>

                <ol>
                    <li>Go to the <strong>Channel Management</strong> tab</li>
                    <li>Click <strong>Edit</strong> on your preferred default channel</li>
                    <li>Check the <strong>Set as Default</strong> option</li>
                    <li>Adjust visibility and access settings if needed</li>
                    <li>Click <strong>Save</strong></li>
                </ol>
            </div>

            <!-- Step 5 -->
            <div class="step">
                <div class="step-number">5</div>
                <h2>Test User Access</h2>
                <p>Verify that users can access the chat interface.</p>

                <ol>
                    <li>Open the user portal in a private/incognito window</li>
                    <li>Go to: <code>https://flexpbx.devinecreations.net/user-portal/chat.php</code></li>
                    <li>Log in with a test extension</li>
                    <li>Verify the chat interface loads correctly</li>
                    <li>Try sending a test message</li>
                    <li>Check if the message appears in Mattermost</li>
                </ol>

                <div class="success-box">
                    <strong>Success!</strong> If you can send and receive messages, the integration is working correctly.
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="button-group">
                <a href="https://chat.tappedin.fm" target="_blank" class="btn">
                    Open Mattermost
                </a>
                <a href="/admin/mattermost-channels.php" class="btn">
                    Start Configuration
                </a>
                <a href="/admin/dashboard.php" class="btn btn-secondary">
                    Back to Dashboard
                </a>
            </div>
        </div>
    </div>

    <script>
        console.log('Mattermost Setup Helper loaded');
    </script>
</body>
</html>
