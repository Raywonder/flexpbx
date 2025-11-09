<?php
/**
 * FlexPBX Notifications System Test Script
 * Demonstrates all notification features
 *
 * @version 1.0.0
 * @date 2025-11-06
 */

header('Content-Type: text/html; charset=utf-8');

// Load the notification helper
require_once __DIR__ . '/notification-helper.php';

// Create notifier instance
$notifier = new NotificationHelper();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FlexPBX Notifications Test</title>
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
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }

        h1 {
            color: #333;
            margin-bottom: 10px;
        }

        .subtitle {
            color: #666;
            font-size: 14px;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }

        .card h2 {
            color: #333;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card p {
            color: #666;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .btn {
            width: 100%;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
            background: #667eea;
            color: white;
        }

        .btn:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }

        .result {
            margin-top: 15px;
            padding: 15px;
            border-radius: 8px;
            font-size: 13px;
            display: none;
        }

        .result.success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #10b981;
            display: block;
        }

        .result.error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #ef4444;
            display: block;
        }

        .form-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            color: #555;
            font-weight: 500;
            font-size: 14px;
        }

        input, select, textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        textarea {
            resize: vertical;
            min-height: 80px;
        }

        .code-block {
            background: #f9fafb;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            overflow-x: auto;
            margin-top: 15px;
        }

        .info-box {
            background: #dbeafe;
            border: 1px solid #3b82f6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }

        .info-box h3 {
            color: #1e40af;
            margin-bottom: 10px;
            font-size: 16px;
        }

        .info-box ul {
            margin-left: 20px;
            color: #1e3a8a;
            font-size: 14px;
        }

        .info-box li {
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔔 FlexPBX Notifications System Test</h1>
            <p class="subtitle">Test and demonstrate all notification features</p>
        </div>

        <div class="info-box">
            <h3>Quick Test Instructions:</h3>
            <ul>
                <li>Click any "Send Test" button below to send a notification</li>
                <li>Check the notification bell icon (top right) to see notifications</li>
                <li>Visit <a href="/user-portal/notifications.php">/user-portal/notifications.php</a> to view all notifications</li>
                <li>Visit <a href="/admin/notifications-center.php">/admin/notifications-center.php</a> for admin center</li>
                <li>Default test user: admin (change in forms below if needed)</li>
            </ul>
        </div>

        <div class="grid">
            <!-- Test 1: Missed Call -->
            <div class="card">
                <h2>📞 Missed Call</h2>
                <p>Send a missed call notification</p>
                <form id="test-missed-call">
                    <div class="form-group">
                        <label>Extension</label>
                        <input type="text" name="extension" value="admin" required>
                    </div>
                    <div class="form-group">
                        <label>Caller ID</label>
                        <input type="text" name="caller_id" value="555-1234" required>
                    </div>
                    <button type="submit" class="btn">Send Test Notification</button>
                </form>
                <div class="result" id="result-missed-call"></div>
            </div>

            <!-- Test 2: Voicemail -->
            <div class="card">
                <h2>📧 Voicemail</h2>
                <p>Send a voicemail notification</p>
                <form id="test-voicemail">
                    <div class="form-group">
                        <label>Extension</label>
                        <input type="text" name="extension" value="admin" required>
                    </div>
                    <div class="form-group">
                        <label>Caller ID</label>
                        <input type="text" name="caller_id" value="555-5678" required>
                    </div>
                    <button type="submit" class="btn">Send Test Notification</button>
                </form>
                <div class="result" id="result-voicemail"></div>
            </div>

            <!-- Test 3: SMS -->
            <div class="card">
                <h2>💬 SMS Message</h2>
                <p>Send an SMS notification</p>
                <form id="test-sms">
                    <div class="form-group">
                        <label>Extension</label>
                        <input type="text" name="extension" value="admin" required>
                    </div>
                    <div class="form-group">
                        <label>Sender</label>
                        <input type="text" name="sender" value="555-9999" required>
                    </div>
                    <div class="form-group">
                        <label>Message Preview</label>
                        <textarea name="message">Hello! This is a test SMS message.</textarea>
                    </div>
                    <button type="submit" class="btn">Send Test Notification</button>
                </form>
                <div class="result" id="result-sms"></div>
            </div>

            <!-- Test 4: System Alert -->
            <div class="card">
                <h2>⚠️ System Alert</h2>
                <p>Send a system alert (urgent)</p>
                <form id="test-alert">
                    <div class="form-group">
                        <label>Alert Title</label>
                        <input type="text" name="title" value="High CPU Usage" required>
                    </div>
                    <div class="form-group">
                        <label>Alert Message</label>
                        <textarea name="message">Server CPU usage has reached 95%. Immediate attention required.</textarea>
                    </div>
                    <button type="submit" class="btn">Send Test Notification</button>
                </form>
                <div class="result" id="result-alert"></div>
            </div>

            <!-- Test 5: Announcement -->
            <div class="card">
                <h2>📢 Announcement</h2>
                <p>Send announcement to all users</p>
                <form id="test-announcement">
                    <div class="form-group">
                        <label>Title</label>
                        <input type="text" name="title" value="Scheduled Maintenance" required>
                    </div>
                    <div class="form-group">
                        <label>Message</label>
                        <textarea name="message">System maintenance scheduled for tonight at 11 PM. Expected downtime: 2 hours.</textarea>
                    </div>
                    <div class="form-group">
                        <label>Priority</label>
                        <select name="priority">
                            <option value="low">Low</option>
                            <option value="normal">Normal</option>
                            <option value="high" selected>High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                    <button type="submit" class="btn">Send Test Notification</button>
                </form>
                <div class="result" id="result-announcement"></div>
            </div>

            <!-- Test 6: Task Assignment -->
            <div class="card">
                <h2>📋 Task Assignment</h2>
                <p>Assign a task to a user</p>
                <form id="test-task">
                    <div class="form-group">
                        <label>Assign To (Extension)</label>
                        <input type="text" name="extension" value="admin" required>
                    </div>
                    <div class="form-group">
                        <label>Task Title</label>
                        <input type="text" name="title" value="Review Customer Request" required>
                    </div>
                    <div class="form-group">
                        <label>Task Details</label>
                        <textarea name="message">Please review and approve customer request #12345</textarea>
                    </div>
                    <button type="submit" class="btn">Send Test Notification</button>
                </form>
                <div class="result" id="result-task"></div>
            </div>

            <!-- Test 7: Custom Notification -->
            <div class="card">
                <h2>🔔 Custom Notification</h2>
                <p>Send a custom notification</p>
                <form id="test-custom">
                    <div class="form-group">
                        <label>Type</label>
                        <select name="type">
                            <option value="system">System</option>
                            <option value="call">Call</option>
                            <option value="voicemail">Voicemail</option>
                            <option value="sms">SMS</option>
                            <option value="alert">Alert</option>
                            <option value="message">Message</option>
                            <option value="task">Task</option>
                            <option value="announcement">Announcement</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Target User ID</label>
                        <input type="text" name="target_user" value="admin" required>
                    </div>
                    <div class="form-group">
                        <label>Title</label>
                        <input type="text" name="title" value="Custom Notification" required>
                    </div>
                    <div class="form-group">
                        <label>Message</label>
                        <textarea name="message">This is a custom test notification.</textarea>
                    </div>
                    <div class="form-group">
                        <label>Priority</label>
                        <select name="priority">
                            <option value="low">Low</option>
                            <option value="normal" selected>Normal</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                    <button type="submit" class="btn">Send Test Notification</button>
                </form>
                <div class="result" id="result-custom"></div>
            </div>

            <!-- Test 8: Role-Based -->
            <div class="card">
                <h2>👥 Role-Based Notification</h2>
                <p>Send to all users with specific role</p>
                <form id="test-role">
                    <div class="form-group">
                        <label>Target Role</label>
                        <select name="role">
                            <option value="admin">Admin</option>
                            <option value="moderator">Moderator</option>
                            <option value="user">User</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Title</label>
                        <input type="text" name="title" value="Important Update" required>
                    </div>
                    <div class="form-group">
                        <label>Message</label>
                        <textarea name="message">This notification is for all admins.</textarea>
                    </div>
                    <button type="submit" class="btn">Send Test Notification</button>
                </form>
                <div class="result" id="result-role"></div>
            </div>
        </div>

        <!-- API Documentation -->
        <div class="card">
            <h2>📖 API Documentation</h2>
            <p>For complete documentation, see: <code>/home/flexpbxuser/NOTIFICATIONS_SYSTEM_COMPLETE.md</code></p>

            <h3 style="margin-top: 20px; margin-bottom: 10px;">Quick API Examples:</h3>

            <div class="code-block">
// Using NotificationHelper class<br>
$notifier = new NotificationHelper();<br>
<br>
// Send missed call<br>
$notifier->sendMissedCall('2000', '555-1234');<br>
<br>
// Send voicemail<br>
$notifier->sendVoicemail('2000', '555-1234', 'vm_12345');<br>
<br>
// Send announcement<br>
$notifier->sendAnnouncement('System Update', 'Maintenance tonight', 'high');<br>
            </div>
        </div>
    </div>

    <script>
        // Handle form submissions
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', async function(e) {
                e.preventDefault();

                const formId = this.id;
                const resultDiv = document.getElementById('result-' + formId.replace('test-', ''));
                const formData = new FormData(this);

                // Show loading
                resultDiv.className = 'result';
                resultDiv.textContent = 'Sending...';
                resultDiv.style.display = 'block';

                try {
                    let notification = {};

                    // Build notification based on form type
                    if (formId === 'test-missed-call') {
                        notification = {
                            notification_type: 'call',
                            title: 'Missed Call',
                            message: `You missed a call from ${formData.get('caller_id')} at ${new Date().toLocaleTimeString()}`,
                            target_user_id: formData.get('extension'),
                            icon: 'phone-missed',
                            priority: 'normal',
                            link_url: '/user-portal/call-history.php'
                        };
                    } else if (formId === 'test-voicemail') {
                        notification = {
                            notification_type: 'voicemail',
                            title: 'New Voicemail',
                            message: `You have a new voicemail from ${formData.get('caller_id')}`,
                            target_user_id: formData.get('extension'),
                            icon: 'voicemail',
                            priority: 'high',
                            link_url: '/user-portal/voicemail.php'
                        };
                    } else if (formId === 'test-sms') {
                        notification = {
                            notification_type: 'sms',
                            title: 'New SMS Message',
                            message: `From ${formData.get('sender')}: ${formData.get('message').substring(0, 50)}`,
                            target_user_id: formData.get('extension'),
                            icon: 'message',
                            priority: 'normal',
                            link_url: '/user-portal/sms-inbox.php'
                        };
                    } else if (formId === 'test-alert') {
                        notification = {
                            notification_type: 'alert',
                            title: formData.get('title'),
                            message: formData.get('message'),
                            target_user_id: 'admin',
                            icon: 'alert-triangle',
                            priority: 'urgent'
                        };
                    } else if (formId === 'test-announcement') {
                        notification = {
                            notification_type: 'announcement',
                            title: formData.get('title'),
                            message: formData.get('message'),
                            icon: 'megaphone',
                            priority: formData.get('priority')
                        };
                    } else if (formId === 'test-task') {
                        notification = {
                            notification_type: 'task',
                            title: formData.get('title'),
                            message: formData.get('message'),
                            target_user_id: formData.get('extension'),
                            icon: 'clipboard',
                            priority: 'normal'
                        };
                    } else if (formId === 'test-custom') {
                        notification = {
                            notification_type: formData.get('type'),
                            title: formData.get('title'),
                            message: formData.get('message'),
                            target_user_id: formData.get('target_user'),
                            priority: formData.get('priority')
                        };
                    } else if (formId === 'test-role') {
                        notification = {
                            notification_type: 'system',
                            title: formData.get('title'),
                            message: formData.get('message'),
                            target_role: formData.get('role'),
                            priority: 'normal'
                        };
                    }

                    // Send notification
                    const response = await fetch('/api/notifications-manager.php?action=send', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(notification)
                    });

                    const result = await response.json();

                    if (result.success) {
                        resultDiv.className = 'result success';
                        resultDiv.textContent = `✓ Success! Sent to ${result.recipients_count} recipient(s). Notification ID: ${result.notification_id}`;
                    } else {
                        resultDiv.className = 'result error';
                        resultDiv.textContent = `✗ Error: ${result.error}`;
                    }
                } catch (error) {
                    resultDiv.className = 'result error';
                    resultDiv.textContent = `✗ Error: ${error.message}`;
                }
            });
        });
    </script>
</body>
</html>
