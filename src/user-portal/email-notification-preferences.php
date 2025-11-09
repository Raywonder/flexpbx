<?php
/**
 * FlexPBX Email Notification Preferences
 *
 * User interface for managing email notification preferences
 * Integrated with comprehensive email system
 *
 * @author FlexPBX
 * @version 1.0.0
 * @created 2025-10-17
 */

session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/EmailService.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$emailService = new EmailService();
$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save_preferences') {
        $result = savePreferences($user_id, $_POST);
        if ($result['success']) {
            $message = 'Notification preferences saved successfully!';
        } else {
            $error = 'Error saving preferences: ' . $result['error'];
        }
    } elseif ($_POST['action'] === 'send_test') {
        $user = getUserInfo($user_id);
        if ($user && $user['email']) {
            if ($emailService->sendTestEmail($user['email'])) {
                $message = "Test email sent to {$user['email']} successfully!";
            } else {
                $error = "Failed to send test email";
            }
        } else {
            $error = "No email address found for your account";
        }
    }
}

// Get current preferences
$preferences = getUserPreferences($user_id);
$user_info = getUserInfo($user_id);

/**
 * Get user preferences
 */
function getUserPreferences($user_id) {
    global $db;

    $stmt = $db->prepare("
        SELECT * FROM user_notification_preferences
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $prefs = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$prefs) {
        // Create default preferences
        $stmt = $db->prepare("
            INSERT INTO user_notification_preferences (
                user_id,
                email_enabled,
                notify_voicemail,
                notify_missed_call,
                notify_extension_change,
                notify_security_alert,
                notify_call_recording,
                notify_fax,
                notify_conference,
                digest_enabled,
                digest_frequency
            ) VALUES (?, 1, 1, 1, 1, 1, 0, 1, 0, 0, 'immediate')
        ");
        $stmt->execute([$user_id]);

        // Fetch the newly created preferences
        $stmt = $db->prepare("
            SELECT * FROM user_notification_preferences
            WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);
        $prefs = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    return $prefs;
}

/**
 * Get user info
 */
function getUserInfo($user_id) {
    global $db;

    $stmt = $db->prepare("
        SELECT id, username, email, extension FROM users WHERE id = ?
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Save user preferences
 */
function savePreferences($user_id, $data) {
    global $db;

    try {
        $stmt = $db->prepare("
            UPDATE user_notification_preferences
            SET email_enabled = ?,
                notify_voicemail = ?,
                notify_missed_call = ?,
                notify_extension_change = ?,
                notify_security_alert = ?,
                notify_call_recording = ?,
                notify_fax = ?,
                notify_conference = ?,
                digest_enabled = ?,
                digest_frequency = ?,
                digest_time = ?,
                updated_at = NOW()
            WHERE user_id = ?
        ");

        $stmt->execute([
            isset($data['email_enabled']) ? 1 : 0,
            isset($data['notify_voicemail']) ? 1 : 0,
            isset($data['notify_missed_call']) ? 1 : 0,
            isset($data['notify_extension_change']) ? 1 : 0,
            isset($data['notify_security_alert']) ? 1 : 0,
            isset($data['notify_call_recording']) ? 1 : 0,
            isset($data['notify_fax']) ? 1 : 0,
            isset($data['notify_conference']) ? 1 : 0,
            isset($data['digest_enabled']) ? 1 : 0,
            $data['digest_frequency'] ?? 'immediate',
            $data['digest_time'] ?? '09:00:00',
            $user_id
        ]);

        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Notification Settings - FlexPBX</title>
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
            padding: 20px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
        }

        .header {
            background: white;
            padding: 25px;
            border-radius: 8px 8px 0 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .header h1 {
            color: #2c3e50;
            font-size: 28px;
            margin-bottom: 10px;
        }

        .user-info {
            color: #7f8c8d;
            font-size: 14px;
        }

        .main-content {
            background: white;
            padding: 30px;
            border-radius: 0 0 8px 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
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

        .section {
            margin-bottom: 30px;
            padding-bottom: 30px;
            border-bottom: 1px solid #e0e0e0;
        }

        .section:last-child {
            border-bottom: none;
        }

        h2 {
            color: #34495e;
            font-size: 20px;
            margin-bottom: 15px;
        }

        .description {
            color: #7f8c8d;
            font-size: 14px;
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .toggle-group {
            margin-bottom: 15px;
        }

        .toggle-switch {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 6px;
            margin-bottom: 10px;
            transition: background 0.3s;
        }

        .toggle-switch:hover {
            background: #e9ecef;
        }

        .toggle-label {
            flex: 1;
        }

        .toggle-label strong {
            display: block;
            color: #2c3e50;
            margin-bottom: 3px;
        }

        .toggle-label span {
            font-size: 13px;
            color: #7f8c8d;
        }

        .toggle-input {
            position: relative;
            width: 50px;
            height: 26px;
        }

        .toggle-input input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: 0.4s;
            border-radius: 34px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: 0.4s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: #3498db;
        }

        input:checked + .slider:before {
            transform: translateX(24px);
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
        }

        select, input[type="time"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            font-family: inherit;
        }

        .button-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
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

        .btn-secondary {
            background: #95a5a6;
        }

        .btn-secondary:hover {
            background: #7f8c8d;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: white;
            text-decoration: none;
            font-weight: 600;
            padding: 10px 20px;
            background: rgba(255,255,255,0.2);
            border-radius: 4px;
            transition: background 0.3s;
        }

        .back-link:hover {
            background: rgba(255,255,255,0.3);
        }

        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }

        .info-box strong {
            color: #1976d2;
            display: block;
            margin-bottom: 5px;
        }

        .info-box p {
            color: #555;
            font-size: 14px;
            line-height: 1.6;
            margin: 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" class="back-link">← Back to Dashboard</a>

        <div class="header">
            <h1>Email Notification Settings</h1>
            <div class="user-info">
                <?= htmlspecialchars($user_info['username']) ?>
                <?php if ($user_info['extension']): ?>
                    | Extension: <?= htmlspecialchars($user_info['extension']) ?>
                <?php endif; ?>
                <?php if ($user_info['email']): ?>
                    | Email: <?= htmlspecialchars($user_info['email']) ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="main-content">
            <?php if ($message): ?>
                <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if (!$user_info['email']): ?>
                <div class="alert alert-error">
                    You don't have an email address configured. Please contact your administrator to add an email address to your account.
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="action" value="save_preferences">

                <!-- Master Toggle -->
                <div class="section">
                    <div class="toggle-switch">
                        <div class="toggle-label">
                            <strong>Enable Email Notifications</strong>
                            <span>Master switch for all email notifications</span>
                        </div>
                        <label class="toggle-input">
                            <input type="checkbox" name="email_enabled" <?= $preferences['email_enabled'] ? 'checked' : '' ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>

                <!-- Notification Types -->
                <div class="section">
                    <h2>Notification Types</h2>
                    <p class="description">Choose which events should trigger email notifications</p>

                    <div class="toggle-group">
                        <div class="toggle-switch">
                            <div class="toggle-label">
                                <strong>Voicemail</strong>
                                <span>Get notified when you receive a new voicemail message</span>
                            </div>
                            <label class="toggle-input">
                                <input type="checkbox" name="notify_voicemail" <?= $preferences['notify_voicemail'] ? 'checked' : '' ?>>
                                <span class="slider"></span>
                            </label>
                        </div>

                        <div class="toggle-switch">
                            <div class="toggle-label">
                                <strong>Missed Calls</strong>
                                <span>Get notified when you miss a call</span>
                            </div>
                            <label class="toggle-input">
                                <input type="checkbox" name="notify_missed_call" <?= $preferences['notify_missed_call'] ? 'checked' : '' ?>>
                                <span class="slider"></span>
                            </label>
                        </div>

                        <div class="toggle-switch">
                            <div class="toggle-label">
                                <strong>Extension Changes</strong>
                                <span>Get notified when your extension settings are modified</span>
                            </div>
                            <label class="toggle-input">
                                <input type="checkbox" name="notify_extension_change" <?= $preferences['notify_extension_change'] ? 'checked' : '' ?>>
                                <span class="slider"></span>
                            </label>
                        </div>

                        <div class="toggle-switch">
                            <div class="toggle-label">
                                <strong>Security Alerts</strong>
                                <span>Get notified about security events (failed logins, suspicious activity)</span>
                            </div>
                            <label class="toggle-input">
                                <input type="checkbox" name="notify_security_alert" <?= $preferences['notify_security_alert'] ? 'checked' : '' ?>>
                                <span class="slider"></span>
                            </label>
                        </div>

                        <div class="toggle-switch">
                            <div class="toggle-label">
                                <strong>Call Recordings</strong>
                                <span>Get notified when a new call recording is available</span>
                            </div>
                            <label class="toggle-input">
                                <input type="checkbox" name="notify_call_recording" <?= $preferences['notify_call_recording'] ? 'checked' : '' ?>>
                                <span class="slider"></span>
                            </label>
                        </div>

                        <div class="toggle-switch">
                            <div class="toggle-label">
                                <strong>Fax Messages</strong>
                                <span>Get notified when you receive a fax</span>
                            </div>
                            <label class="toggle-input">
                                <input type="checkbox" name="notify_fax" <?= $preferences['notify_fax'] ? 'checked' : '' ?>>
                                <span class="slider"></span>
                            </label>
                        </div>

                        <div class="toggle-switch">
                            <div class="toggle-label">
                                <strong>Conference Calls</strong>
                                <span>Get notified about conference call invitations and updates</span>
                            </div>
                            <label class="toggle-input">
                                <input type="checkbox" name="notify_conference" <?= $preferences['notify_conference'] ? 'checked' : '' ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Digest Settings -->
                <div class="section">
                    <h2>Digest Settings</h2>
                    <p class="description">Instead of receiving individual emails, you can receive a summary digest</p>

                    <div class="info-box">
                        <strong>What is a digest?</strong>
                        <p>A digest combines multiple notifications into a single email, reducing inbox clutter while keeping you informed.</p>
                    </div>

                    <div class="toggle-switch">
                        <div class="toggle-label">
                            <strong>Enable Digest Mode</strong>
                            <span>Receive notifications as a summary instead of individual emails</span>
                        </div>
                        <label class="toggle-input">
                            <input type="checkbox" name="digest_enabled" id="digest_enabled" <?= $preferences['digest_enabled'] ? 'checked' : '' ?>>
                            <span class="slider"></span>
                        </label>
                    </div>

                    <div id="digest_options" style="<?= !$preferences['digest_enabled'] ? 'display:none;' : '' ?>">
                        <div class="form-group">
                            <label>Digest Frequency</label>
                            <select name="digest_frequency">
                                <option value="immediate" <?= $preferences['digest_frequency'] === 'immediate' ? 'selected' : '' ?>>Immediate (no digest)</option>
                                <option value="hourly" <?= $preferences['digest_frequency'] === 'hourly' ? 'selected' : '' ?>>Hourly</option>
                                <option value="daily" <?= $preferences['digest_frequency'] === 'daily' ? 'selected' : '' ?>>Daily</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Daily Digest Time (for daily digest only)</label>
                            <input type="time" name="digest_time" value="<?= htmlspecialchars($preferences['digest_time'] ?? '09:00:00') ?>">
                        </div>
                    </div>
                </div>

                <div class="button-group">
                    <button type="submit" class="btn btn-success">Save Preferences</button>
                    <?php if ($user_info['email']): ?>
                        <button type="submit" name="action" value="send_test" class="btn-secondary">Send Test Email</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Show/hide digest options based on toggle
        document.getElementById('digest_enabled').addEventListener('change', function() {
            const digestOptions = document.getElementById('digest_options');
            if (this.checked) {
                digestOptions.style.display = 'block';
            } else {
                digestOptions.style.display = 'none';
            }
        });
    </script>
</body>
</html>
