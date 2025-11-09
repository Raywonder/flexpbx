<?php
/**
 * FlexPBX User Portal - Voicemail
 * Manage voicemail messages and settings
 */

// Require authentication
require_once __DIR__ . '/user_auth_check.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voicemail - FlexPBX User Portal</title>
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

        .header h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 10px;
        }

        .header .breadcrumb {
            color: #666;
            font-size: 14px;
        }

        .header .breadcrumb a {
            color: #667eea;
            text-decoration: none;
        }

        .tabs {
            background: white;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }

        .tab-buttons {
            display: flex;
            border-bottom: 2px solid #e0e0e0;
        }

        .tab-button {
            flex: 1;
            padding: 15px 20px;
            background: white;
            border: none;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            color: #666;
            transition: all 0.3s ease;
        }

        .tab-button.active {
            color: #667eea;
            border-bottom: 3px solid #667eea;
            background: #f8f9fa;
        }

        .tab-button:hover {
            background: #f8f9fa;
        }

        .tab-content {
            display: none;
            padding: 30px;
        }

        .tab-content.active {
            display: block;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
        }

        .stat-card .number {
            font-size: 36px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stat-card .label {
            font-size: 14px;
            opacity: 0.9;
        }

        .card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .card h3 {
            color: #333;
            margin-bottom: 20px;
            font-size: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }

        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="password"],
        .form-group input[type="number"],
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }

        .checkbox-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
            margin-right: 10px;
            cursor: pointer;
        }

        .checkbox-group label {
            margin: 0;
            cursor: pointer;
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
            text-decoration: none;
            display: inline-block;
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

        .message-list {
            margin-top: 20px;
        }

        .message-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .message-item.unread {
            background: #e7f3ff;
            border-left: 4px solid #667eea;
        }

        .message-info {
            flex: 1;
        }

        .message-from {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .message-date {
            font-size: 12px;
            color: #666;
        }

        .message-duration {
            font-size: 14px;
            color: #667eea;
            margin-right: 15px;
        }

        .message-actions {
            display: flex;
            gap: 10px;
        }

        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
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

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
        }

        .empty-state svg {
            width: 80px;
            height: 80px;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        .loading {
            text-align: center;
            padding: 40px;
            color: #667eea;
        }

        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .help-text {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📞 Voicemail</h1>
            <div class="breadcrumb">
                <a href="/user-portal/">Dashboard</a> / Voicemail
            </div>
        </div>

        <div class="tabs">
            <div class="tab-buttons">
                <button class="tab-button active" onclick="switchTab('messages')">Messages</button>
                <button class="tab-button" onclick="switchTab('settings')">Settings</button>
                <button class="tab-button" onclick="switchTab('greetings')">Greetings</button>
            </div>

            <!-- Messages Tab -->
            <div id="messages-tab" class="tab-content active">
                <div class="stats-grid" id="stats-grid">
                    <!-- Statistics will be loaded here -->
                </div>

                <div class="card">
                    <h3>Voicemail Messages</h3>
                    <div class="form-group">
                        <label>Folder:</label>
                        <select id="folder-select" onchange="loadMessages()">
                            <option value="INBOX">Inbox (New)</option>
                            <option value="Old">Old Messages</option>
                            <option value="Work">Work</option>
                            <option value="Family">Family</option>
                            <option value="Friends">Friends</option>
                        </select>
                    </div>
                    <div id="messages-list" class="message-list">
                        <div class="loading">
                            <div class="spinner"></div>
                            <p>Loading messages...</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Settings Tab -->
            <div id="settings-tab" class="tab-content">
                <div class="card">
                    <h3>Voicemail Settings</h3>
                    <div id="settings-alerts"></div>

                    <form id="settings-form">
                        <div class="form-group">
                            <label>Voicemail PIN:</label>
                            <input type="password" id="vm-pin" placeholder="4-10 digit PIN" maxlength="10">
                            <div class="help-text">PIN for checking voicemail via phone (dial *97)</div>
                        </div>

                        <div class="form-group">
                            <label>Email Address:</label>
                            <input type="email" id="vm-email" placeholder="your@email.com">
                            <div class="help-text">Receive voicemail notifications at this address</div>
                        </div>

                        <div class="form-group">
                            <label>Timezone:</label>
                            <select id="vm-timezone">
                                <option value="central">Central Time</option>
                                <option value="eastern">Eastern Time</option>
                                <option value="pacific">Pacific Time</option>
                                <option value="mountain">Mountain Time</option>
                            </select>
                        </div>

                        <div class="checkbox-group">
                            <input type="checkbox" id="vm-attach" checked>
                            <label for="vm-attach">Attach audio file to email notifications</label>
                        </div>

                        <div class="checkbox-group">
                            <input type="checkbox" id="vm-delete">
                            <label for="vm-delete">Delete voicemail after sending email</label>
                        </div>

                        <button type="submit" class="btn">Save Settings</button>
                    </form>
                </div>

                <div class="card">
                    <h3>Change Voicemail PIN</h3>
                    <form id="pin-change-form">
                        <div class="form-group">
                            <label>New PIN:</label>
                            <input type="password" id="new-pin" placeholder="4-10 digits" maxlength="10" pattern="[0-9]{4,10}">
                        </div>

                        <div class="form-group">
                            <label>Confirm New PIN:</label>
                            <input type="password" id="confirm-pin" placeholder="Confirm PIN" maxlength="10" pattern="[0-9]{4,10}">
                        </div>

                        <button type="submit" class="btn">Change PIN</button>
                    </form>
                </div>
            </div>

            <!-- Greetings Tab -->
            <div id="greetings-tab" class="tab-content">
                <div class="card">
                    <h3>Voicemail Greetings</h3>
                    <div id="greetings-list">
                        <div class="loading">
                            <div class="spinner"></div>
                            <p>Loading greetings...</p>
                        </div>
                    </div>
                </div>

                <div class="alert alert-info">
                    <strong>Tip:</strong> You can record custom greetings by calling into your voicemail (*97) and selecting option 0 for mailbox options.
                </div>
            </div>
        </div>
    </div>

    <script>
        // Get current extension from session (passed from PHP)
        let currentExtension = null;
        let currentContext = 'flexpbx';

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Get user info from session
            fetch('/api/user-info.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.extension) {
                        currentExtension = data.extension;
                        loadStatistics();
                        loadMessages();
                        loadSettings();
                        loadGreetings();
                    } else {
                        window.location.href = '/user-portal/';
                    }
                })
                .catch(error => {
                    console.error('Error getting user info:', error);
                    showAlert('settings-alerts', 'Unable to load user information', 'error');
                });
        });

        function switchTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab-button').forEach(btn => {
                btn.classList.remove('active');
            });

            // Show selected tab
            document.getElementById(tabName + '-tab').classList.add('active');
            event.target.classList.add('active');

            // Load data if needed
            if (tabName === 'messages') {
                loadMessages();
            } else if (tabName === 'greetings') {
                loadGreetings();
            }
        }

        function loadStatistics() {
            if (!currentExtension) return;

            fetch(`/api/voicemail.php?path=statistics&mailbox=${currentExtension}&context=${currentContext}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const stats = data.statistics;
                        document.getElementById('stats-grid').innerHTML = `
                            <div class="stat-card">
                                <div class="number">${stats.inbox_count || 0}</div>
                                <div class="label">New Messages</div>
                            </div>
                            <div class="stat-card">
                                <div class="number">${stats.old_count || 0}</div>
                                <div class="label">Saved Messages</div>
                            </div>
                            <div class="stat-card">
                                <div class="number">${stats.total_messages || 0}</div>
                                <div class="label">Total Messages</div>
                            </div>
                            <div class="stat-card">
                                <div class="number">${stats.storage_used_formatted || '0 B'}</div>
                                <div class="label">Storage Used</div>
                            </div>
                        `;
                    }
                })
                .catch(error => console.error('Error loading statistics:', error));
        }

        function loadMessages() {
            if (!currentExtension) return;

            const folder = document.getElementById('folder-select').value;
            document.getElementById('messages-list').innerHTML = `
                <div class="loading">
                    <div class="spinner"></div>
                    <p>Loading messages...</p>
                </div>
            `;

            fetch(`/api/voicemail.php?path=messages&mailbox=${currentExtension}&context=${currentContext}&folder=${folder}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const messages = data.messages;
                        if (messages.length === 0) {
                            document.getElementById('messages-list').innerHTML = `
                                <div class="empty-state">
                                    <p>No messages in ${folder}</p>
                                </div>
                            `;
                        } else {
                            let html = '';
                            messages.forEach(msg => {
                                const unreadClass = !msg.read && folder === 'INBOX' ? 'unread' : '';
                                html += `
                                    <div class="message-item ${unreadClass}">
                                        <div class="message-info">
                                            <div class="message-from">${msg.from || 'Unknown'}</div>
                                            <div class="message-date">${msg.origdate || 'Unknown date'}</div>
                                        </div>
                                        <div class="message-duration">${msg.duration_formatted || '0s'}</div>
                                        <div class="message-actions">
                                            <button class="btn btn-small" onclick="playMessage('${msg.id}', '${folder}')">▶ Play</button>
                                            ${folder === 'INBOX' ? `<button class="btn btn-secondary btn-small" onclick="markAsRead('${msg.id}')">Archive</button>` : ''}
                                            <button class="btn btn-danger btn-small" onclick="deleteMessage('${msg.id}', '${folder}')">Delete</button>
                                        </div>
                                    </div>
                                `;
                            });
                            document.getElementById('messages-list').innerHTML = html;
                        }
                        // Refresh statistics
                        loadStatistics();
                    }
                })
                .catch(error => {
                    console.error('Error loading messages:', error);
                    document.getElementById('messages-list').innerHTML = `
                        <div class="alert alert-error">Failed to load messages</div>
                    `;
                });
        }

        function loadSettings() {
            if (!currentExtension) return;

            fetch(`/api/voicemail.php?path=settings&mailbox=${currentExtension}&context=${currentContext}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const settings = data.settings;
                        document.getElementById('vm-pin').placeholder = 'Current PIN: ' + '*'.repeat(4);
                        document.getElementById('vm-email').value = settings.email || '';
                        document.getElementById('vm-timezone').value = settings.timezone || 'central';
                        document.getElementById('vm-attach').checked = settings.attach === 'yes';
                        document.getElementById('vm-delete').checked = settings.delete === 'yes';
                    }
                })
                .catch(error => console.error('Error loading settings:', error));
        }

        function loadGreetings() {
            if (!currentExtension) return;

            document.getElementById('greetings-list').innerHTML = `
                <div class="loading">
                    <div class="spinner"></div>
                    <p>Loading greetings...</p>
                </div>
            `;

            fetch(`/api/voicemail.php?path=greetings&mailbox=${currentExtension}&context=${currentContext}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const greetings = data.greetings;
                        if (greetings.length === 0) {
                            document.getElementById('greetings-list').innerHTML = `
                                <div class="empty-state">
                                    <p>No custom greetings recorded yet</p>
                                    <p style="margin-top: 10px; font-size: 14px;">Call *97 to record your greetings</p>
                                </div>
                            `;
                        } else {
                            let html = '';
                            greetings.forEach(greeting => {
                                html += `
                                    <div class="message-item">
                                        <div class="message-info">
                                            <div class="message-from">${greeting.description}</div>
                                            <div class="message-date">Modified: ${greeting.modified}</div>
                                        </div>
                                        <div class="message-actions">
                                            <button class="btn btn-small" onclick="playGreeting('${greeting.type}')">▶ Play</button>
                                        </div>
                                    </div>
                                `;
                            });
                            document.getElementById('greetings-list').innerHTML = html;
                        }
                    }
                })
                .catch(error => {
                    console.error('Error loading greetings:', error);
                    document.getElementById('greetings-list').innerHTML = `
                        <div class="alert alert-error">Failed to load greetings</div>
                    `;
                });
        }

        // Form handlers
        document.getElementById('settings-form').addEventListener('submit', function(e) {
            e.preventDefault();

            const settings = {
                email: document.getElementById('vm-email').value,
                timezone: document.getElementById('vm-timezone').value,
                attach: document.getElementById('vm-attach').checked ? 'yes' : 'no',
                delete: document.getElementById('vm-delete').checked ? 'yes' : 'no'
            };

            fetch(`/api/voicemail.php?path=update&mailbox=${currentExtension}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ...settings, context: currentContext })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('settings-alerts', 'Settings updated successfully!', 'success');
                } else {
                    showAlert('settings-alerts', 'Failed to update settings: ' + (data.error || 'Unknown error'), 'error');
                }
            })
            .catch(error => {
                showAlert('settings-alerts', 'Error updating settings: ' + error.message, 'error');
            });
        });

        document.getElementById('pin-change-form').addEventListener('submit', function(e) {
            e.preventDefault();

            const newPin = document.getElementById('new-pin').value;
            const confirmPin = document.getElementById('confirm-pin').value;

            if (newPin !== confirmPin) {
                showAlert('settings-alerts', 'PINs do not match!', 'error');
                return;
            }

            if (!/^[0-9]{4,10}$/.test(newPin)) {
                showAlert('settings-alerts', 'PIN must be 4-10 digits', 'error');
                return;
            }

            fetch(`/api/voicemail.php?path=update&mailbox=${currentExtension}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ pin: newPin, context: currentContext })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('settings-alerts', 'PIN changed successfully!', 'success');
                    document.getElementById('new-pin').value = '';
                    document.getElementById('confirm-pin').value = '';
                } else {
                    showAlert('settings-alerts', 'Failed to change PIN: ' + (data.error || 'Unknown error'), 'error');
                }
            })
            .catch(error => {
                showAlert('settings-alerts', 'Error changing PIN: ' + error.message, 'error');
            });
        });

        function playMessage(messageId, folder) {
            window.open(`/voicemail/${currentContext}/${currentExtension}/${folder}/msg${messageId}.wav`, '_blank');
        }

        function playGreeting(type) {
            window.open(`/voicemail/${currentContext}/${currentExtension}/${type}.wav`, '_blank');
        }

        function markAsRead(messageId) {
            fetch(`/api/voicemail.php?path=mark-read&mailbox=${currentExtension}&context=${currentContext}&message_id=${messageId}`, {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadMessages();
                    loadStatistics();
                } else {
                    alert('Failed to mark message as read: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => alert('Error: ' + error.message));
        }

        function deleteMessage(messageId, folder) {
            if (!confirm('Are you sure you want to delete this message?')) {
                return;
            }

            fetch(`/api/voicemail.php?path=delete-message&mailbox=${currentExtension}&context=${currentContext}&message_id=${messageId}&folder=${folder}`, {
                method: 'DELETE'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadMessages();
                    loadStatistics();
                } else {
                    alert('Failed to delete message: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => alert('Error: ' + error.message));
        }

        function showAlert(containerId, message, type) {
            const container = document.getElementById(containerId);
            const alertClass = type === 'success' ? 'alert-success' : 'alert-error';
            container.innerHTML = `<div class="alert ${alertClass}">${message}</div>`;
            setTimeout(() => { container.innerHTML = ''; }, 5000);
        }
    </script>
</body>
</html>
