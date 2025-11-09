<?php
require_once __DIR__ . '/admin_auth_check.php';
/**
 * FlexPBX Email Configuration Interface
 *
 * Admin interface for managing email system settings,
 * templates, queue, and logs.
 *
 * @author FlexPBX
 * @version 1.0.0
 * @created 2025-10-17
 */

session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/EmailService.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$emailService = new EmailService();
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'save_config':
                $result = saveEmailConfig($_POST);
                if ($result['success']) {
                    $message = 'Email configuration saved successfully!';
                } else {
                    $error = 'Error saving configuration: ' . $result['error'];
                }
                break;

            case 'test_email':
                $test_email = filter_var($_POST['test_email'], FILTER_VALIDATE_EMAIL);
                if ($test_email) {
                    if ($emailService->sendTestEmail($test_email)) {
                        $message = "Test email sent to $test_email successfully!";
                    } else {
                        $error = "Failed to send test email to $test_email";
                    }
                } else {
                    $error = 'Invalid email address';
                }
                break;

            case 'save_template':
                $result = saveTemplate($_POST);
                if ($result['success']) {
                    $message = 'Template saved successfully!';
                } else {
                    $error = 'Error saving template: ' . $result['error'];
                }
                break;

            case 'retry_failed':
                $count = $emailService->retryFailed();
                $message = "Reset $count failed emails for retry";
                break;

            case 'clear_old_logs':
                $days = intval($_POST['days'] ?? 30);
                $count = $emailService->clearOldLogs($days);
                $message = "Deleted $count old log entries";
                break;
        }
    }
}

// Get current configuration
$config = getCurrentConfig();
$templates = getTemplates();
$queue_summary = $emailService->getQueueSummary();
$statistics = $emailService->getStatistics(7);

/**
 * Save email configuration
 */
function saveEmailConfig($data) {
    global $db;

    try {
        // Get encryption key
        $key_file = __DIR__ . '/../config/email_encryption.key';
        if (!file_exists($key_file)) {
            $key = bin2hex(random_bytes(32));
            file_put_contents($key_file, $key);
            chmod($key_file, 0600);
        }
        $encryption_key = file_get_contents($key_file);

        // Encrypt password
        $password = $data['smtp_password'];
        if (!empty($password)) {
            $iv = random_bytes(16);
            $encrypted = openssl_encrypt($password, 'AES-256-CBC', $encryption_key, 0, $iv);
            $encrypted_password = base64_encode($iv . $encrypted);
        } else {
            // Keep existing password if not changed
            $stmt = $db->query("SELECT smtp_password FROM email_system_config ORDER BY id DESC LIMIT 1");
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            $encrypted_password = $existing['smtp_password'] ?? '';
        }

        // Check if config exists
        $stmt = $db->query("SELECT COUNT(*) FROM email_system_config");
        $exists = $stmt->fetchColumn() > 0;

        if ($exists) {
            $sql = "UPDATE email_system_config SET
                smtp_host = ?,
                smtp_port = ?,
                smtp_security = ?,
                smtp_username = ?,
                " . (!empty($password) ? "smtp_password = ?," : "") . "
                default_from_email = ?,
                default_from_name = ?,
                default_reply_to = ?,
                max_retry_attempts = ?,
                send_timeout = ?,
                rate_limit_per_hour = ?,
                is_active = 1,
                updated_at = NOW()
                WHERE id = (SELECT id FROM (SELECT id FROM email_system_config ORDER BY id DESC LIMIT 1) AS tmp)";

            $params = [
                $data['smtp_host'],
                intval($data['smtp_port']),
                $data['smtp_security'],
                $data['smtp_username']
            ];

            if (!empty($password)) {
                $params[] = $encrypted_password;
            }

            $params = array_merge($params, [
                $data['default_from_email'],
                $data['default_from_name'],
                $data['default_reply_to'],
                intval($data['max_retry_attempts']),
                intval($data['send_timeout']),
                intval($data['rate_limit_per_hour'])
            ]);
        } else {
            $sql = "INSERT INTO email_system_config (
                smtp_host, smtp_port, smtp_security, smtp_username, smtp_password,
                default_from_email, default_from_name, default_reply_to,
                max_retry_attempts, send_timeout, rate_limit_per_hour
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $params = [
                $data['smtp_host'],
                intval($data['smtp_port']),
                $data['smtp_security'],
                $data['smtp_username'],
                $encrypted_password,
                $data['default_from_email'],
                $data['default_from_name'],
                $data['default_reply_to'],
                intval($data['max_retry_attempts']),
                intval($data['send_timeout']),
                intval($data['rate_limit_per_hour'])
            ];
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Save template
 */
function saveTemplate($data) {
    global $db;

    try {
        $stmt = $db->prepare("
            UPDATE email_templates
            SET subject = ?,
                body_html = ?,
                body_text = ?,
                updated_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([
            $data['subject'],
            $data['body_html'],
            $data['body_text'],
            intval($data['template_id'])
        ]);

        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Get current configuration
 */
function getCurrentConfig() {
    global $db;

    $stmt = $db->query("
        SELECT * FROM email_system_config
        WHERE is_active = 1
        ORDER BY id DESC
        LIMIT 1
    ");

    $config = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$config) {
        // Return defaults
        $config = [
            'smtp_host' => 'localhost',
            'smtp_port' => 587,
            'smtp_security' => 'tls',
            'smtp_username' => 'services@devine-creations.com',
            'smtp_password' => '',
            'default_from_email' => 'services@devine-creations.com',
            'default_from_name' => 'FlexPBX Services',
            'default_reply_to' => 'support@devine-creations.com',
            'max_retry_attempts' => 3,
            'send_timeout' => 30,
            'rate_limit_per_hour' => 100
        ];
    }

    return $config;
}

/**
 * Get all templates
 */
function getTemplates() {
    global $db;

    $stmt = $db->query("
        SELECT * FROM email_templates
        WHERE is_active = 1
        ORDER BY category, template_name
    ");

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Settings - FlexPBX Admin</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #f5f5f5;
            color: #333;
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        h1 {
            color: #2c3e50;
            margin-bottom: 30px;
            font-size: 28px;
        }

        h2 {
            color: #34495e;
            margin: 30px 0 15px;
            font-size: 22px;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }

        h3 {
            color: #34495e;
            margin: 20px 0 10px;
            font-size: 18px;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
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

        .card {
            background: white;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #555;
        }

        input[type="text"],
        input[type="email"],
        input[type="number"],
        input[type="password"],
        select,
        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            font-family: inherit;
        }

        textarea {
            min-height: 150px;
            resize: vertical;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }

        button, .btn {
            background: #3498db;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: background 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        button:hover, .btn:hover {
            background: #2980b9;
        }

        .btn-success {
            background: #27ae60;
        }

        .btn-success:hover {
            background: #229954;
        }

        .btn-warning {
            background: #f39c12;
        }

        .btn-warning:hover {
            background: #e67e22;
        }

        .btn-danger {
            background: #e74c3c;
        }

        .btn-danger:hover {
            background: #c0392b;
        }

        .btn-secondary {
            background: #95a5a6;
        }

        .btn-secondary:hover {
            background: #7f8c8d;
        }

        .tabs {
            display: flex;
            border-bottom: 2px solid #ddd;
            margin-bottom: 20px;
        }

        .tab {
            padding: 12px 24px;
            cursor: pointer;
            border: none;
            background: transparent;
            color: #666;
            font-weight: 600;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }

        .tab.active {
            color: #3498db;
            border-bottom-color: #3498db;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }

        .stat-card h3 {
            color: white;
            margin: 0 0 10px;
            font-size: 16px;
            opacity: 0.9;
        }

        .stat-card .value {
            font-size: 32px;
            font-weight: bold;
        }

        .queue-item {
            background: #f8f9fa;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 4px;
            border-left: 4px solid #3498db;
        }

        .queue-item.failed {
            border-left-color: #e74c3c;
        }

        .queue-item.sent {
            border-left-color: #27ae60;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #555;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .template-preview {
            border: 1px solid #ddd;
            padding: 15px;
            margin-top: 10px;
            background: #f8f9fa;
            border-radius: 4px;
        }

        .small-text {
            font-size: 12px;
            color: #777;
            margin-top: 5px;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #3498db;
            text-decoration: none;
            font-weight: 600;
        }

        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" class="back-link">← Back to Admin Dashboard</a>

        <h1>Email System Configuration</h1>

        <?php if ($message): ?>
            <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="tabs">
            <button class="tab active" onclick="showTab('config')">SMTP Configuration</button>
            <button class="tab" onclick="showTab('templates')">Email Templates</button>
            <button class="tab" onclick="showTab('queue')">Email Queue</button>
            <button class="tab" onclick="showTab('logs')">Logs & Statistics</button>
        </div>

        <!-- SMTP Configuration Tab -->
        <div id="config" class="tab-content active">
            <div class="card">
                <h2>SMTP Server Settings</h2>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="save_config">

                    <div class="form-row">
                        <div class="form-group">
                            <label>SMTP Host</label>
                            <input type="text" name="smtp_host" value="<?= htmlspecialchars($config['smtp_host']) ?>" required>
                            <div class="small-text">e.g., smtp.gmail.com, mail.yourdomain.com</div>
                        </div>

                        <div class="form-group">
                            <label>SMTP Port</label>
                            <input type="number" name="smtp_port" value="<?= htmlspecialchars($config['smtp_port']) ?>" required>
                            <div class="small-text">Common: 25, 465 (SSL), 587 (TLS)</div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Security</label>
                            <select name="smtp_security" required>
                                <option value="none" <?= $config['smtp_security'] === 'none' ? 'selected' : '' ?>>None</option>
                                <option value="tls" <?= $config['smtp_security'] === 'tls' ? 'selected' : '' ?>>TLS</option>
                                <option value="ssl" <?= $config['smtp_security'] === 'ssl' ? 'selected' : '' ?>>SSL</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>SMTP Username</label>
                            <input type="text" name="smtp_username" value="<?= htmlspecialchars($config['smtp_username']) ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>SMTP Password</label>
                        <input type="password" name="smtp_password" placeholder="Leave blank to keep current password">
                        <div class="small-text">Password is encrypted in database</div>
                    </div>

                    <h3>Default Email Addresses</h3>

                    <div class="form-row">
                        <div class="form-group">
                            <label>From Email</label>
                            <input type="email" name="default_from_email" value="<?= htmlspecialchars($config['default_from_email']) ?>" required>
                            <div class="small-text">Default: services@devine-creations.com</div>
                        </div>

                        <div class="form-group">
                            <label>From Name</label>
                            <input type="text" name="default_from_name" value="<?= htmlspecialchars($config['default_from_name']) ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Reply-To Email</label>
                        <input type="email" name="default_reply_to" value="<?= htmlspecialchars($config['default_reply_to']) ?>" required>
                        <div class="small-text">Default: support@devine-creations.com</div>
                    </div>

                    <h3>Advanced Settings</h3>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Max Retry Attempts</label>
                            <input type="number" name="max_retry_attempts" value="<?= htmlspecialchars($config['max_retry_attempts']) ?>" min="1" max="10" required>
                        </div>

                        <div class="form-group">
                            <label>Send Timeout (seconds)</label>
                            <input type="number" name="send_timeout" value="<?= htmlspecialchars($config['send_timeout']) ?>" min="10" max="120" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Rate Limit (emails per hour)</label>
                        <input type="number" name="rate_limit_per_hour" value="<?= htmlspecialchars($config['rate_limit_per_hour']) ?>" min="10" max="1000" required>
                    </div>

                    <button type="submit" class="btn btn-success">Save Configuration</button>
                </form>
            </div>

            <div class="card">
                <h2>Test Email</h2>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="test_email">
                    <div class="form-group">
                        <label>Test Email Address</label>
                        <input type="email" name="test_email" placeholder="Enter email to send test" required>
                    </div>
                    <button type="submit" class="btn">Send Test Email</button>
                </form>
            </div>
        </div>

        <!-- Templates Tab -->
        <div id="templates" class="tab-content">
            <?php foreach ($templates as $template): ?>
                <div class="card">
                    <h3><?= htmlspecialchars($template['template_name']) ?></h3>
                    <div class="small-text">Key: <?= htmlspecialchars($template['template_key']) ?> | Category: <?= htmlspecialchars($template['category']) ?></div>

                    <form method="POST" action="">
                        <input type="hidden" name="action" value="save_template">
                        <input type="hidden" name="template_id" value="<?= $template['id'] ?>">

                        <div class="form-group">
                            <label>Subject</label>
                            <input type="text" name="subject" value="<?= htmlspecialchars($template['subject']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label>HTML Body</label>
                            <textarea name="body_html" required><?= htmlspecialchars($template['body_html']) ?></textarea>
                        </div>

                        <div class="form-group">
                            <label>Plain Text Body</label>
                            <textarea name="body_text" required><?= htmlspecialchars($template['body_text']) ?></textarea>
                        </div>

                        <div class="small-text">
                            Available Variables: <?= htmlspecialchars($template['available_variables']) ?>
                        </div>

                        <button type="submit" class="btn btn-success" style="margin-top: 10px;">Save Template</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Queue Tab -->
        <div id="queue" class="tab-content">
            <div class="card">
                <h2>Queue Summary</h2>

                <div class="stats-grid">
                    <?php foreach ($queue_summary as $summary): ?>
                        <div class="stat-card">
                            <h3><?= strtoupper(htmlspecialchars($summary['status'])) ?></h3>
                            <div class="value"><?= $summary['count'] ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <form method="POST" action="" style="margin-top: 20px;">
                    <input type="hidden" name="action" value="retry_failed">
                    <button type="submit" class="btn btn-warning">Retry All Failed Emails</button>
                </form>
            </div>
        </div>

        <!-- Logs Tab -->
        <div id="logs" class="tab-content">
            <div class="card">
                <h2>Email Statistics (Last 7 Days)</h2>

                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($statistics as $stat): ?>
                            <tr>
                                <td><?= htmlspecialchars($stat['date']) ?></td>
                                <td><?= htmlspecialchars($stat['status']) ?></td>
                                <td><?= htmlspecialchars($stat['count']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <h3>Clear Old Logs</h3>
                <form method="POST" action="" style="margin-top: 20px;">
                    <input type="hidden" name="action" value="clear_old_logs">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Delete logs older than (days)</label>
                            <input type="number" name="days" value="30" min="7" max="365" required>
                        </div>
                        <div class="form-group" style="display: flex; align-items: flex-end;">
                            <button type="submit" class="btn btn-danger">Clear Old Logs</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            // Hide all tabs
            const tabs = document.querySelectorAll('.tab-content');
            tabs.forEach(tab => tab.classList.remove('active'));

            // Remove active class from all tab buttons
            const tabButtons = document.querySelectorAll('.tab');
            tabButtons.forEach(btn => btn.classList.remove('active'));

            // Show selected tab
            document.getElementById(tabName).classList.add('active');

            // Add active class to clicked button
            event.target.classList.add('active');
        }
    </script>
</body>
</html>
