<?php
require_once __DIR__ . '/admin_auth_check.php';
/**
 * FlexPBX Admin Portal - Broadcast Messaging
 * Send messages to multiple users at once
 */

session_start();

// Check authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /admin/login.php');
    exit;
}

$admin_username = $_SESSION['admin_username'] ?? 'Unknown';
$admin_role = $_SESSION['admin_role'] ?? 'admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Broadcast Message - FlexPBX Admin Portal</title>
    <link rel="stylesheet" href="/css/style.css">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f5f7fa;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 900px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .header {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .header h1 {
            margin: 0 0 0.5rem 0;
            color: #2c3e50;
        }

        .header .subtitle {
            color: #666;
            font-size: 0.95rem;
        }

        .admin-badge {
            display: inline-block;
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
            color: white;
            padding: 0.3rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 700;
            margin-left: 0.5rem;
        }

        .card {
            background: white;
            border-radius: 8px;
            padding: 2rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .card h2 {
            margin: 0 0 1.5rem 0;
            color: #2c3e50;
            font-size: 1.3rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #2c3e50;
            font-weight: 600;
        }

        .form-group .description {
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 0.5rem;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            font-size: 0.95rem;
            font-family: inherit;
            transition: border-color 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: #2196f3;
        }

        textarea.form-control {
            min-height: 150px;
            resize: vertical;
        }

        .recipient-options {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .recipient-option {
            display: flex;
            align-items: center;
            padding: 1rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .recipient-option:hover {
            border-color: #2196f3;
            background: #f8f9fa;
        }

        .recipient-option input[type="radio"] {
            margin-right: 1rem;
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .recipient-option.selected {
            border-color: #2196f3;
            background: #e3f2fd;
        }

        .recipient-label {
            flex: 1;
        }

        .recipient-label-title {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.25rem;
        }

        .recipient-label-desc {
            font-size: 0.85rem;
            color: #666;
        }

        .custom-recipients {
            display: none;
            margin-top: 1rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 6px;
        }

        .custom-recipients.show {
            display: block;
        }

        .custom-recipients label {
            font-size: 0.9rem;
            color: #666;
        }

        .extensions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 0.5rem;
            margin-top: 0.5rem;
            max-height: 200px;
            overflow-y: auto;
            padding: 0.5rem;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
        }

        .extension-checkbox {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .extension-checkbox input {
            cursor: pointer;
        }

        .extension-checkbox label {
            cursor: pointer;
            font-size: 0.9rem;
            color: #666;
        }

        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.95rem;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
        }

        .btn-primary {
            background: #2196f3;
            color: white;
        }

        .btn-primary:hover:not(:disabled) {
            background: #1976d2;
        }

        .btn-primary:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
        }

        .alert {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
        }

        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .alert-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .alert-info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }

        .char-counter {
            text-align: right;
            font-size: 0.85rem;
            color: #666;
            margin-top: 0.25rem;
        }

        .preview-card {
            background: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            padding: 1rem;
            margin-top: 1rem;
        }

        .preview-card h4 {
            margin: 0 0 0.5rem 0;
            color: #2c3e50;
            font-size: 0.95rem;
        }

        .preview-content {
            font-size: 0.9rem;
            color: #666;
            white-space: pre-wrap;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📢 Broadcast Message</h1>
            <p class="subtitle">
                Admin: <?= htmlspecialchars($admin_username) ?>
                <span class="admin-badge"><?= strtoupper(htmlspecialchars($admin_role)) ?></span>
            </p>
        </div>

        <div id="alert-container"></div>

        <div class="card">
            <h2>Compose Broadcast Message</h2>

            <form id="broadcast-form" onsubmit="sendBroadcast(event)">
                <!-- Subject -->
                <div class="form-group">
                    <label for="subject">Message Subject</label>
                    <input
                        type="text"
                        id="subject"
                        class="form-control"
                        placeholder="System Announcement"
                        value="System Announcement"
                        required
                    >
                </div>

                <!-- Recipients -->
                <div class="form-group">
                    <label>Recipients</label>
                    <div class="description">Choose who will receive this message</div>

                    <div class="recipient-options">
                        <div class="recipient-option selected" onclick="selectRecipientType('all')">
                            <input type="radio" name="recipients" value="all" checked id="recipients-all">
                            <div class="recipient-label">
                                <div class="recipient-label-title">👥 All Users</div>
                                <div class="recipient-label-desc">Send to everyone on the system</div>
                            </div>
                        </div>

                        <div class="recipient-option" onclick="selectRecipientType('custom')">
                            <input type="radio" name="recipients" value="custom" id="recipients-custom">
                            <div class="recipient-label">
                                <div class="recipient-label-title">🎯 Specific Users</div>
                                <div class="recipient-label-desc">Choose individual recipients</div>
                            </div>
                        </div>
                    </div>

                    <div class="custom-recipients" id="custom-recipients">
                        <label>Select Users:</label>
                        <div class="extensions-grid" id="extensions-list">
                            <p style="text-align: center; color: #999;">Loading users...</p>
                        </div>
                    </div>
                </div>

                <!-- Message -->
                <div class="form-group">
                    <label for="message">Message Content</label>
                    <textarea
                        id="message"
                        class="form-control"
                        placeholder="Enter your message here..."
                        required
                        oninput="updateCharCounter()"
                    ></textarea>
                    <div class="char-counter" id="char-counter">0 characters</div>
                </div>

                <!-- Preview -->
                <div class="preview-card">
                    <h4>Preview</h4>
                    <div class="preview-content" id="preview-content">Your message will appear here...</div>
                </div>

                <!-- Actions -->
                <div class="actions">
                    <a href="/admin/dashboard.html" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary" id="send-btn">
                        📤 Send Broadcast
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let availableUsers = [];

        // Load users on page load
        document.addEventListener('DOMContentLoaded', () => {
            loadUsers();
            updatePreview();
        });

        // Load available users
        async function loadUsers() {
            try {
                const response = await fetch('/api/extensions.php?action=list');
                const data = await response.json();

                if (data.success) {
                    availableUsers = data.extensions || [];
                    renderUsersList();
                } else {
                    // Fallback: try another endpoint
                    console.log('Trying alternate endpoint for users...');
                    availableUsers = [];
                }
            } catch (error) {
                console.error('Failed to load users:', error);
                availableUsers = [];
            }
        }

        // Render users list
        function renderUsersList() {
            const container = document.getElementById('extensions-list');

            if (availableUsers.length === 0) {
                container.innerHTML = '<p style="text-align: center; color: #999;">No users found</p>';
                return;
            }

            let html = '';
            availableUsers.forEach(user => {
                html += `
                    <div class="extension-checkbox">
                        <input type="checkbox" id="user-${user.extension}" value="${user.extension}">
                        <label for="user-${user.extension}">${user.extension} - ${user.name || user.username}</label>
                    </div>
                `;
            });

            container.innerHTML = html;
        }

        // Select recipient type
        function selectRecipientType(type) {
            // Update radio buttons
            document.getElementById('recipients-all').checked = (type === 'all');
            document.getElementById('recipients-custom').checked = (type === 'custom');

            // Update visual state
            document.querySelectorAll('.recipient-option').forEach(el => {
                el.classList.remove('selected');
            });
            event.currentTarget.classList.add('selected');

            // Show/hide custom recipients
            const customContainer = document.getElementById('custom-recipients');
            if (type === 'custom') {
                customContainer.classList.add('show');
            } else {
                customContainer.classList.remove('show');
            }
        }

        // Update character counter and preview
        function updateCharCounter() {
            const message = document.getElementById('message').value;
            const counter = document.getElementById('char-counter');
            counter.textContent = `${message.length} characters`;
            updatePreview();
        }

        // Update preview
        function updatePreview() {
            const subject = document.getElementById('subject').value;
            const message = document.getElementById('message').value;
            const preview = document.getElementById('preview-content');

            if (message) {
                preview.innerHTML = `<strong>${subject}</strong><br><br>${message}`;
            } else {
                preview.textContent = 'Your message will appear here...';
            }
        }

        // Update preview when subject changes
        document.getElementById('subject').addEventListener('input', updatePreview);

        // Send broadcast
        async function sendBroadcast(event) {
            event.preventDefault();

            const subject = document.getElementById('subject').value.trim();
            const message = document.getElementById('message').value.trim();
            const recipientType = document.querySelector('input[name="recipients"]:checked').value;

            if (!subject || !message) {
                showAlert('Please fill in all required fields', 'error');
                return;
            }

            let recipients = 'all';

            if (recipientType === 'custom') {
                // Get selected extensions
                const checkboxes = document.querySelectorAll('#extensions-list input[type="checkbox"]:checked');
                recipients = Array.from(checkboxes).map(cb => cb.value);

                if (recipients.length === 0) {
                    showAlert('Please select at least one recipient', 'error');
                    return;
                }
            }

            const sendBtn = document.getElementById('send-btn');
            sendBtn.disabled = true;
            sendBtn.textContent = 'Sending...';

            try {
                const response = await fetch('/api/messages.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'broadcast',
                        subject: subject,
                        message: message,
                        recipients: recipients
                    })
                });

                const data = await response.json();

                if (data.success) {
                    showAlert(`✓ Broadcast sent successfully to ${data.sent_to} user(s)`, 'success');

                    // Reset form
                    document.getElementById('broadcast-form').reset();
                    updatePreview();
                    updateCharCounter();

                    // Redirect after 2 seconds
                    setTimeout(() => {
                        window.location.href = '/admin/dashboard.html';
                    }, 2000);
                } else {
                    showAlert('Failed to send broadcast: ' + data.error, 'error');
                }
            } catch (error) {
                console.error('Failed to send broadcast:', error);
                showAlert('Failed to send broadcast. Please try again.', 'error');
            } finally {
                sendBtn.disabled = false;
                sendBtn.textContent = '📤 Send Broadcast';
            }
        }

        // Show alert
        function showAlert(message, type) {
            const container = document.getElementById('alert-container');
            const alertClass = type === 'success' ? 'alert-success' : (type === 'error' ? 'alert-error' : 'alert-info');

            container.innerHTML = `
                <div class="alert ${alertClass}">
                    ${message}
                </div>
            `;

            // Auto-hide after 5 seconds
            setTimeout(() => {
                container.innerHTML = '';
            }, 5000);
        }
    </script>
</body>
</html>
