<?php
/**
 * FlexPBX User Portal - SMS Inbox
 * View SMS messages received via Twilio
 */

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: /user-portal/login.php');
    exit;
}

$extension = $_SESSION['user_extension'] ?? '';

// Load SMS messages for this extension
$sms_dir = '/home/flexpbxuser/sms_messages';
$sms_file = $sms_dir . '/extension_' . $extension . '.json';

$messages = [];
if (file_exists($sms_file)) {
    $messages = json_decode(file_get_contents($sms_file), true) ?? [];
    // Sort by newest first
    $messages = array_reverse($messages);
}

// Format phone number
function format_phone($number) {
    $clean = preg_replace('/[^0-9]/', '', $number);
    if (strlen($clean) === 11 && $clean[0] === '1') {
        $clean = substr($clean, 1);
    }
    if (strlen($clean) === 10) {
        return '(' . substr($clean, 0, 3) . ') ' . substr($clean, 3, 3) . '-' . substr($clean, 6, 4);
    }
    return $number;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMS Inbox - FlexPBX</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container { max-width: 900px; margin: 0 auto; }
        .header {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        h1 { color: #333; margin-bottom: 10px; }
        .subtitle { color: #666; font-size: 14px; }
        .card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            margin-bottom: 20px;
        }
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 4px;
        }
        .warning-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 4px;
            color: #856404;
        }
        .message-item {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            border-left: 4px solid #667eea;
        }
        .message-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        .message-from {
            font-weight: 600;
            color: #2c3e50;
            font-size: 1.1rem;
        }
        .message-time {
            color: #666;
            font-size: 0.9rem;
        }
        .message-body {
            color: #333;
            line-height: 1.6;
            margin-top: 0.5rem;
        }
        .message-meta {
            font-size: 0.85rem;
            color: #999;
            margin-top: 0.5rem;
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
            transition: all 0.3s ease;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }
        .btn-secondary {
            background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
        }
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #666;
        }
        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>💬 SMS Inbox</h1>
            <p class="subtitle">Extension <?= htmlspecialchars($extension) ?> - Text Messages</p>
        </div>

        <div class="card">
            <?php if (!file_exists($sms_file) || empty($messages)): ?>
                <div class="warning-box">
                    <strong>⚙️ SMS Integration Setup Required</strong><br>
                    To receive SMS messages here, you need to configure Twilio webhook.<br>
                    See setup instructions below.
                </div>

                <div class="empty-state">
                    <div class="empty-state-icon">📭</div>
                    <h2>No Messages Yet</h2>
                    <p>SMS messages received via Twilio will appear here.</p>
                </div>

                <div class="info-box">
                    <h3 style="margin-bottom: 0.5rem;">📱 How to Enable SMS</h3>
                    <ol style="margin-left: 1.5rem; line-height: 1.8;">
                        <li>Sign up at <a href="https://www.twilio.com/try-twilio" target="_blank">twilio.com</a></li>
                        <li>Get a phone number or port your Google Voice number</li>
                        <li>Configure webhook URL: <code>https://flexpbx.devinecreations.net/api/twilio-sms-webhook.php</code></li>
                        <li>Add the number to your "Forwarded Numbers" settings</li>
                    </ol>
                </div>
            <?php else: ?>
                <h2 style="margin-bottom: 1.5rem;">📥 Received Messages (<?= count($messages) ?>)</h2>

                <?php foreach ($messages as $msg): ?>
                <div class="message-item">
                    <div class="message-header">
                        <div class="message-from">
                            📱 <?= htmlspecialchars(format_phone($msg['from'])) ?>
                        </div>
                        <div class="message-time">
                            <?= date('M j, Y g:i A', strtotime($msg['received_at'])) ?>
                        </div>
                    </div>
                    <div class="message-body">
                        <?= nl2br(htmlspecialchars($msg['body'])) ?>
                    </div>
                    <div class="message-meta">
                        To: <?= htmlspecialchars(format_phone($msg['to'])) ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div style="text-align: center; margin-top: 2rem;">
            <a href="/user-portal/" class="btn btn-secondary" aria-label="Return to user dashboard">Back to Dashboard</a>
        </div>
    </div>
</body>
</html>
