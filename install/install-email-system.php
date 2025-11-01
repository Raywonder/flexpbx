<?php
/**
 * FlexPBX Email System Installation Script
 *
 * This script installs and configures the comprehensive email system
 *
 * @author FlexPBX
 * @version 1.0.0
 * @created 2025-10-17
 */

// Security check - remove this file after installation
if (file_exists(__DIR__ . '/.email_system_installed')) {
    die('Email system is already installed. Remove this file manually if you need to reinstall.');
}

require_once __DIR__ . '/../includes/db.php';

$errors = [];
$warnings = [];
$success = [];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FlexPBX Email System Installation</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }

        .header h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .header p {
            font-size: 16px;
            opacity: 0.9;
        }

        .content {
            padding: 40px;
        }

        .step {
            margin-bottom: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }

        .step h2 {
            color: #2c3e50;
            font-size: 20px;
            margin-bottom: 15px;
        }

        .step-result {
            margin-top: 10px;
            padding: 10px;
            border-radius: 4px;
        }

        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 15px;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 15px;
        }

        .warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 15px;
        }

        .info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 15px;
        }

        .btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn:hover {
            background: #5568d3;
        }

        .btn-success {
            background: #27ae60;
        }

        .btn-success:hover {
            background: #229954;
        }

        pre {
            background: #2c3e50;
            color: #ecf0f1;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
            font-size: 13px;
            line-height: 1.6;
        }

        .checklist {
            list-style: none;
            padding: 0;
        }

        .checklist li {
            padding: 8px 0;
            padding-left: 30px;
            position: relative;
        }

        .checklist li:before {
            content: "✓";
            position: absolute;
            left: 0;
            color: #27ae60;
            font-weight: bold;
            font-size: 18px;
        }

        .footer {
            background: #f8f9fa;
            padding: 20px 40px;
            text-align: center;
            color: #666;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>FlexPBX Email System Installation</h1>
            <p>Comprehensive Email Configuration & Notification System</p>
        </div>

        <div class="content">
            <?php
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install'])) {
                echo '<h2>Installation Progress</h2>';

                // Step 1: Create database tables
                echo '<div class="step">';
                echo '<h2>Step 1: Creating Database Tables</h2>';
                try {
                    $schema = file_get_contents(__DIR__ . '/../database/email_config_schema.sql');

                    // Split by semicolon and execute each statement
                    $statements = array_filter(array_map('trim', explode(';', $schema)));

                    foreach ($statements as $statement) {
                        if (!empty($statement) &&
                            !preg_match('/^(--|CREATE OR REPLACE VIEW)/', $statement)) {
                            $db->exec($statement);
                        }
                    }

                    echo '<div class="success">✓ Database tables created successfully</div>';
                    $success[] = 'Database schema installed';
                } catch (Exception $e) {
                    echo '<div class="error">✗ Error creating database tables: ' . htmlspecialchars($e->getMessage()) . '</div>';
                    $errors[] = 'Database creation failed';
                }
                echo '</div>';

                // Step 2: Create directories
                echo '<div class="step">';
                echo '<h2>Step 2: Creating Required Directories</h2>';
                $directories = [
                    __DIR__ . '/../config',
                    __DIR__ . '/../logs',
                ];

                foreach ($directories as $dir) {
                    if (!is_dir($dir)) {
                        if (mkdir($dir, 0755, true)) {
                            echo '<div class="success">✓ Created directory: ' . basename($dir) . '</div>';
                        } else {
                            echo '<div class="error">✗ Failed to create directory: ' . basename($dir) . '</div>';
                            $errors[] = 'Directory creation failed';
                        }
                    } else {
                        echo '<div class="info">Directory already exists: ' . basename($dir) . '</div>';
                    }
                }
                echo '</div>';

                // Step 3: Generate encryption key
                echo '<div class="step">';
                echo '<h2>Step 3: Generating Encryption Key</h2>';
                $key_file = __DIR__ . '/../config/email_encryption.key';
                if (!file_exists($key_file)) {
                    $key = bin2hex(random_bytes(32));
                    if (file_put_contents($key_file, $key)) {
                        chmod($key_file, 0600);
                        echo '<div class="success">✓ Encryption key generated successfully</div>';
                        $success[] = 'Encryption key created';
                    } else {
                        echo '<div class="error">✗ Failed to generate encryption key</div>';
                        $errors[] = 'Encryption key generation failed';
                    }
                } else {
                    echo '<div class="info">Encryption key already exists</div>';
                }
                echo '</div>';

                // Step 4: Set file permissions
                echo '<div class="step">';
                echo '<h2>Step 4: Setting File Permissions</h2>';
                $files_to_chmod = [
                    __DIR__ . '/../includes/EmailService.php' => 0644,
                    __DIR__ . '/../admin/email-settings.php' => 0644,
                    __DIR__ . '/../user-portal/email-notification-preferences.php' => 0644,
                    __DIR__ . '/../api/email-config.php' => 0644,
                ];

                foreach ($files_to_chmod as $file => $permission) {
                    if (file_exists($file)) {
                        if (chmod($file, $permission)) {
                            echo '<div class="success">✓ Set permissions for: ' . basename($file) . '</div>';
                        } else {
                            echo '<div class="warning">⚠ Could not set permissions for: ' . basename($file) . '</div>';
                            $warnings[] = 'Permission setting failed for ' . basename($file);
                        }
                    }
                }
                echo '</div>';

                // Step 5: Verify installation
                echo '<div class="step">';
                echo '<h2>Step 5: Verifying Installation</h2>';

                $tables_to_check = [
                    'email_system_config',
                    'email_templates',
                    'email_queue',
                    'email_log',
                    'user_notification_preferences',
                    'email_rate_limit',
                    'email_bounces',
                    'email_digest_queue'
                ];

                $all_tables_exist = true;
                foreach ($tables_to_check as $table) {
                    $stmt = $db->query("SHOW TABLES LIKE '$table'");
                    if ($stmt->rowCount() > 0) {
                        echo '<div class="success">✓ Table verified: ' . $table . '</div>';
                    } else {
                        echo '<div class="error">✗ Table missing: ' . $table . '</div>';
                        $all_tables_exist = false;
                        $errors[] = "Table $table not found";
                    }
                }

                if ($all_tables_exist) {
                    echo '<div class="success">✓ All database tables verified successfully</div>';
                }
                echo '</div>';

                // Create installation marker
                if (empty($errors)) {
                    file_put_contents(__DIR__ . '/.email_system_installed', date('Y-m-d H:i:s'));

                    echo '<div class="step">';
                    echo '<h2>Installation Complete!</h2>';
                    echo '<div class="success">';
                    echo '<h3>✓ Email System Installed Successfully</h3>';
                    echo '<p>The FlexPBX Email System has been installed and is ready to use.</p>';
                    echo '</div>';

                    echo '<h3>Next Steps:</h3>';
                    echo '<ul class="checklist">';
                    echo '<li>Configure SMTP settings in Admin → Email Settings</li>';
                    echo '<li>Customize email templates as needed</li>';
                    echo '<li>Test email functionality using the test email feature</li>';
                    echo '<li>Set up a cron job to process the email queue regularly</li>';
                    echo '<li>Configure user notification preferences</li>';
                    echo '</ul>';

                    echo '<h3>Cron Job Configuration:</h3>';
                    echo '<p>Add this to your crontab to process email queue every 5 minutes:</p>';
                    echo '<pre>*/5 * * * * php ' . __DIR__ . '/../scripts/process-email-queue.php</pre>';

                    echo '<div style="margin-top: 30px; text-align: center;">';
                    echo '<a href="../admin/email-settings.php" class="btn btn-success">Go to Email Settings</a>';
                    echo '</div>';
                    echo '</div>';
                }

                // Show summary
                if (!empty($errors) || !empty($warnings)) {
                    echo '<div class="step">';
                    echo '<h2>Installation Summary</h2>';

                    if (!empty($errors)) {
                        echo '<div class="error">';
                        echo '<h3>Errors (' . count($errors) . '):</h3>';
                        echo '<ul>';
                        foreach ($errors as $error) {
                            echo '<li>' . htmlspecialchars($error) . '</li>';
                        }
                        echo '</ul>';
                        echo '</div>';
                    }

                    if (!empty($warnings)) {
                        echo '<div class="warning">';
                        echo '<h3>Warnings (' . count($warnings) . '):</h3>';
                        echo '<ul>';
                        foreach ($warnings as $warning) {
                            echo '<li>' . htmlspecialchars($warning) . '</li>';
                        }
                        echo '</ul>';
                        echo '</div>';
                    }
                    echo '</div>';
                }

            } else {
                // Installation form
                ?>
                <div class="info">
                    <h3>Welcome to the Email System Installation</h3>
                    <p>This wizard will install the comprehensive email notification system for FlexPBX.</p>
                </div>

                <h2>System Requirements</h2>
                <div class="step">
                    <ul class="checklist">
                        <li>PHP 7.4 or higher</li>
                        <li>MySQL 5.7 or higher</li>
                        <li>OpenSSL extension enabled</li>
                        <li>Write permissions for config and logs directories</li>
                        <li>SMTP server access (for sending emails)</li>
                    </ul>
                </div>

                <h2>What Will Be Installed</h2>
                <div class="step">
                    <h3>Database Tables:</h3>
                    <ul class="checklist">
                        <li>email_system_config - SMTP and system configuration</li>
                        <li>email_templates - Customizable email templates</li>
                        <li>email_queue - Email sending queue with retry logic</li>
                        <li>email_log - Complete email sending history</li>
                        <li>user_notification_preferences - User notification settings</li>
                        <li>email_rate_limit - Rate limiting for email sending</li>
                        <li>email_bounces - Bounce tracking and suppression</li>
                        <li>email_digest_queue - Digest email queue</li>
                    </ul>

                    <h3>Default Email Templates:</h3>
                    <ul class="checklist">
                        <li>Welcome Email - New user account welcome</li>
                        <li>Password Reset - Password reset requests</li>
                        <li>Voicemail Notification - New voicemail alerts</li>
                        <li>Missed Call - Missed call notifications</li>
                        <li>Extension Changed - Extension modification alerts</li>
                        <li>Security Alert - Security event notifications</li>
                        <li>Call Recording Available - Recording notifications</li>
                        <li>Test Email - System testing template</li>
                    </ul>

                    <h3>Features:</h3>
                    <ul class="checklist">
                        <li>Queue-based email sending with retry logic</li>
                        <li>Template system with variable substitution</li>
                        <li>User notification preferences management</li>
                        <li>Email digest functionality (hourly/daily)</li>
                        <li>Rate limiting and bounce tracking</li>
                        <li>Comprehensive logging and statistics</li>
                        <li>Admin and user interfaces</li>
                        <li>RESTful API for integration</li>
                    </ul>
                </div>

                <h2>Default Configuration</h2>
                <div class="step">
                    <p><strong>From Email:</strong> services@devine-creations.com</p>
                    <p><strong>Reply-To:</strong> support@devine-creations.com</p>
                    <p><strong>Max Retry Attempts:</strong> 3</p>
                    <p><strong>Rate Limit:</strong> 100 emails per hour</p>
                    <p style="margin-top: 10px; color: #666; font-size: 14px;">
                        These can be customized after installation in the admin settings.
                    </p>
                </div>

                <div class="warning">
                    <strong>Important:</strong> Make sure you have database access and write permissions
                    before proceeding with the installation.
                </div>

                <form method="POST" action="" style="text-align: center; margin-top: 30px;">
                    <button type="submit" name="install" class="btn">Start Installation</button>
                </form>
                <?php
            }
            ?>
        </div>

        <div class="footer">
            <p>FlexPBX Email System v1.0.0 &copy; 2025</p>
            <p>Installation Script - Remove this file after installation</p>
        </div>
    </div>
</body>
</html>
