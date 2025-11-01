<?php
/**
 * FlexPBX Voicemail Settings
 * User interface for managing voicemail configuration
 */

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_extension']) || empty($_SESSION['user_extension'])) {
    header('Location: /user-portal/');
    exit;
}

$current_extension = $_SESSION['user_extension'];

// Handle form submissions
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'change_password':
            $old_password = $_POST['old_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            if ($new_password === $confirm_password && strlen($new_password) >= 4) {
                // TODO: Implement password change logic
                $success_message = "Voicemail password updated successfully!";
            } else {
                $error_message = "Passwords don't match or are too short (minimum 4 digits)";
            }
            break;

        case 'update_settings':
            // TODO: Implement settings update logic
            $success_message = "Voicemail settings updated successfully!";
            break;

        case 'update_email':
            $email = $_POST['email'] ?? '';
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                // TODO: Implement email update logic
                $success_message = "Email notification address updated successfully!";
            } else {
                $error_message = "Invalid email address";
            }
            break;
    }
}

// Get current voicemail settings
// TODO: Read from voicemail.conf
$vm_settings = [
    'enabled' => true,
    'password' => str_repeat('*', 4),
    'email' => 'test@flexpbx.devinecreations.net',
    'attach_audio' => true,
    'delete_after_email' => false,
    'envelope' => true,
    'saycid' => true,
    'sayduration' => true,
    'review' => true,
    'operator' => true,
    'callback' => true,
    'dialout' => true,
    'new_messages' => 0,
    'old_messages' => 0
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voicemail Settings - FlexPBX</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        .header {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        .header h1 {
            color: #333;
            margin-bottom: 10px;
        }
        .back-link {
            color: #667eea;
            text-decoration: none;
            display: inline-block;
            margin-bottom: 10px;
        }
        .back-link:hover {
            text-decoration: underline;
        }
        .card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        .card h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
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
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="password"],
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }
        .form-group small {
            display: block;
            margin-top: 5px;
            color: #666;
            font-size: 12px;
        }
        .toggle-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        .toggle-info {
            flex: 1;
        }
        .toggle-info h4 {
            color: #333;
            margin-bottom: 5px;
        }
        .toggle-info p {
            color: #666;
            font-size: 14px;
        }
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }
        .toggle-switch input {
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
            transition: .4s;
            border-radius: 24px;
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        input:checked + .slider {
            background-color: #28a745;
        }
        input:checked + .slider:before {
            transform: translateX(26px);
        }
        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-block;
            text-decoration: none;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }
        .btn-secondary {
            background: #6c757d;
        }
        .btn-danger {
            background: #dc3545;
        }
        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #2196f3;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .info-box h4 {
            color: #0066cc;
            margin-bottom: 10px;
        }
        .info-box ul {
            margin-left: 20px;
            line-height: 1.8;
            color: #333;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-box {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        .stat-box .number {
            font-size: 36px;
            font-weight: bold;
            color: #667eea;
        }
        .stat-box .label {
            color: #666;
            font-size: 14px;
            margin-top: 5px;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="/user-portal/" class="back-link">← Back to Portal</a>

        <div class="header">
            <h1>📬 Voicemail Settings</h1>
            <p style="color: #666; margin-top: 5px;">Extension <?= htmlspecialchars($current_extension) ?></p>
        </div>

        <?php if ($success_message): ?>
        <div class="alert alert-success">
            ✓ <?= htmlspecialchars($success_message) ?>
        </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
        <div class="alert alert-error">
            ⚠️ <?= htmlspecialchars($error_message) ?>
        </div>
        <?php endif; ?>

        <!-- Voicemail Status -->
        <div class="card">
            <h2>📊 Voicemail Status</h2>
            <div class="stats-grid">
                <div class="stat-box">
                    <div class="number"><?= $vm_settings['new_messages'] ?></div>
                    <div class="label">New Messages</div>
                </div>
                <div class="stat-box">
                    <div class="number"><?= $vm_settings['old_messages'] ?></div>
                    <div class="label">Old Messages</div>
                </div>
                <div class="stat-box">
                    <div class="number" style="color: <?= $vm_settings['enabled'] ? '#28a745' : '#dc3545' ?>;">
                        <?= $vm_settings['enabled'] ? '✓' : '✗' ?>
                    </div>
                    <div class="label">Voicemail Status</div>
                </div>
            </div>

            <div class="info-box">
                <h4>📞 Quick Access</h4>
                <ul>
                    <li>Access voicemail: Dial <strong>*97</strong> from your extension</li>
                    <li>Check voicemail remotely: Dial <strong>*97</strong> and enter your extension when prompted</li>
                    <li>Password: <?= $vm_settings['password'] ?> (change below for security)</li>
                </ul>
            </div>
        </div>

        <!-- Change Password -->
        <div class="card">
            <h2>🔒 Change Voicemail Password</h2>
            <form method="POST">
                <input type="hidden" name="action" value="change_password">
                <div class="form-group">
                    <label for="old_password">Current Password</label>
                    <input type="password" id="old_password" name="old_password" placeholder="Enter current password" required>
                </div>
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" placeholder="Enter new password (4+ digits)" required>
                    <small>Choose a 4-6 digit PIN for voicemail access</small>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password" required>
                </div>
                <button type="submit" class="btn" aria-label="Update voicemail password">Update Password</button>
            </form>
        </div>

        <!-- Email Notifications -->
        <div class="card">
            <h2>📧 Email Notifications</h2>
            <form method="POST">
                <input type="hidden" name="action" value="update_email">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($vm_settings['email']) ?>" placeholder="your@email.com">
                    <small>Receive voicemail notifications at this address</small>
                </div>

                <div class="toggle-container">
                    <div class="toggle-info">
                        <h4>Attach Audio Files</h4>
                        <p>Include voicemail audio as email attachment</p>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" name="attach_audio" <?= $vm_settings['attach_audio'] ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                </div>

                <div class="toggle-container">
                    <div class="toggle-info">
                        <h4>Delete After Email</h4>
                        <p>Automatically delete voicemail after sending email</p>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" name="delete_after_email" <?= $vm_settings['delete_after_email'] ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                </div>

                <button type="submit" class="btn" aria-label="Save email notification settings">Save Email Settings</button>
            </form>
        </div>

        <!-- Voicemail Features -->
        <div class="card">
            <h2>⚙️ Voicemail Features</h2>
            <p style="color: #666; margin-bottom: 20px;">Enable or disable voicemail features to customize your experience</p>

            <form method="POST">
                <input type="hidden" name="action" value="update_settings">

                <div class="toggle-container">
                    <div class="toggle-info">
                        <h4>Envelope Information</h4>
                        <p>Hear date and time when message was left (before playing message)</p>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" name="envelope" <?= $vm_settings['envelope'] ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                </div>

                <div class="toggle-container">
                    <div class="toggle-info">
                        <h4>Say Caller ID</h4>
                        <p>Announce caller's phone number before playing message</p>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" name="saycid" <?= $vm_settings['saycid'] ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                </div>

                <div class="toggle-container">
                    <div class="toggle-info">
                        <h4>Say Message Duration</h4>
                        <p>Announce message length before playing</p>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" name="sayduration" <?= $vm_settings['sayduration'] ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                </div>

                <div class="toggle-container">
                    <div class="toggle-info">
                        <h4>Review Before Sending</h4>
                        <p>Allow callers to review/re-record their message before saving</p>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" name="review" <?= $vm_settings['review'] ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                </div>

                <div class="toggle-container">
                    <div class="toggle-info">
                        <h4>Operator Access (Press 0)</h4>
                        <p>Allow callers to press 0 to reach an operator</p>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" name="operator" <?= $vm_settings['operator'] ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                </div>

                <div class="toggle-container">
                    <div class="toggle-info">
                        <h4>Callback Feature</h4>
                        <p>Enable option to call back the person who left voicemail</p>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" name="callback" <?= $vm_settings['callback'] ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                </div>

                <div class="toggle-container">
                    <div class="toggle-info">
                        <h4>Dial Out from Voicemail</h4>
                        <p>Allow dialing external numbers from voicemail menu (option 4)</p>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" name="dialout" <?= $vm_settings['dialout'] ? 'checked' : '' ?>>
                        <span class="slider"></span>
                    </label>
                </div>

                <button type="submit" class="btn" aria-label="Save voicemail feature settings">Save Feature Settings</button>
            </form>
        </div>

        <!-- Voicemail Messages -->
        <div class="card">
            <h2>💬 Voicemail Messages</h2>
            <p style="color: #666; margin-bottom: 20px;">Manage your voicemail messages</p>

            <div class="info-box">
                <h4>ℹ️ How to Access Messages</h4>
                <ul>
                    <li>Dial <strong>*97</strong> from your extension</li>
                    <li>Follow the voice prompts to listen to, delete, or save messages</li>
                    <li>Press 1 to listen to new messages</li>
                    <li>Press 2 to change folders</li>
                    <li>Press 0 to access mailbox options</li>
                </ul>
            </div>

            <div class="action-buttons">
                <button class="btn" onclick="alert('Call *97 from your phone to access voicemail')" aria-label="Show how to access voicemail by phone">Access Voicemail</button>
                <a href="/user-portal/voicemail.php" class="btn btn-secondary" aria-label="Play and manage voicemail messages">📬 Play Messages</a>
            </div>
        </div>

        <!-- Greetings -->
        <div class="card">
            <h2>🎙️ Voicemail Greetings</h2>
            <p style="color: #666; margin-bottom: 20px;">Manage your voicemail greetings</p>

            <div class="info-box">
                <h4>📝 Recording Greetings</h4>
                <ul>
                    <li>Dial <strong>*97</strong> and enter your mailbox</li>
                    <li>Press <strong>0</strong> for mailbox options</li>
                    <li>Press <strong>1</strong> to record unavailable greeting</li>
                    <li>Press <strong>2</strong> to record busy greeting</li>
                    <li>Press <strong>3</strong> to record your name</li>
                    <li>Press <strong>4</strong> to record temporary greeting</li>
                </ul>
            </div>

            <div class="action-buttons">
                <a href="my-recordings.php" class="btn" aria-label="Upload custom voicemail greetings">Upload Custom Greetings</a>
                <button class="btn btn-secondary" onclick="alert('Call *97 then press 0 to record greetings by phone')" aria-label="Show how to record greetings by phone">Record by Phone</button>
            </div>
        </div>

        <!-- Help -->
        <div class="card">
            <h2>❓ Need Help?</h2>
            <div class="info-box">
                <h4>Common Questions</h4>
                <ul>
                    <li><strong>How do I reset my password?</strong> Use the "Change Voicemail Password" section above</li>
                    <li><strong>How do I access voicemail?</strong> Dial *97 from your extension</li>
                    <li><strong>Can I check voicemail remotely?</strong> Yes! Dial *97 and enter your extension when prompted</li>
                    <li><strong>How do I delete messages?</strong> Access voicemail (*97) and press 7 to delete current message</li>
                    <li><strong>Email not working?</strong> Make sure your email address is correct above</li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>
