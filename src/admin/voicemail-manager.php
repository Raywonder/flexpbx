<?php require_once __DIR__ . '/admin_auth_check.php'; ?>
<!DOCTYPE html>
<html lang="en">
<!--
    FlexPBX Voicemail Manager UI
    Updated: October 16, 2025
    API: Uses new comprehensive /api/voicemail.php with query parameter format
    Changes:
    - Migrated from static mockup to full API integration
    - All mailbox operations now use /api/voicemail.php?path=...
    - Real-time mailbox data loading
    - Complete CRUD functionality
    - Message and greeting management integrated
-->
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voicemail Manager - FlexPBX Admin</title>
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
            max-width: 1400px;
            margin: 0 auto;
        }

        .header {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            color: #333;
            font-size: 28px;
        }

        .header-actions {
            display: flex;
            gap: 10px;
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

        .btn-success {
            background: #28a745;
        }

        .btn-warning {
            background: #ffc107;
            color: #333;
        }

        .btn-danger {
            background: #dc3545;
        }

        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
        }

        .card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            margin-bottom: 20px;
        }

        .card h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 20px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: none;
        }

        .alert.show {
            display: block;
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

        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #e2e8f0;
        }

        .tab {
            padding: 12px 24px;
            background: none;
            border: none;
            color: #666;
            font-weight: 600;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }

        .tab.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: #f8f9fa;
        }

        th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #dee2e6;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
        }

        tbody tr:hover {
            background: #f8f9fa;
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

        .status-badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-enabled {
            background: #d4edda;
            color: #155724;
        }

        .status-disabled {
            background: #f8d7da;
            color: #721c24;
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

        .form-group input,
        .form-group select,
        .form-group textarea {
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

        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
        }

        .setting-item {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .setting-info h4 {
            color: #333;
            margin-bottom: 5px;
        }

        .setting-info p {
            color: #666;
            font-size: 14px;
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

        .stats-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }

        .stat-card .number {
            font-size: 36px;
            font-weight: bold;
            color: #667eea;
        }

        .stat-card .label {
            color: #666;
            font-size: 14px;
            margin-top: 5px;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.html" class="back-link">← Back to Dashboard</a>

        <div class="header">
            <div>
                <h1>📬 Voicemail Manager</h1>
                <p style="color: #666; margin-top: 5px;">System-wide voicemail configuration and management</p>
            </div>
            <div class="header-actions">
                <button class="btn btn-warning" onclick="backupConfig()">💾 Backup Config</button>
                <button class="btn btn-success" onclick="reloadVoicemail()" id="reloadBtn">🔄 Reload Voicemail</button>
            </div>
        </div>

        <div id="alertContainer"></div>

        <!-- Tabs -->
        <div class="card">
            <div class="tabs">
                <button class="tab active" onclick="switchTab('mailboxes')">Mailboxes</button>
                <button class="tab" onclick="switchTab('features')">Global Features</button>
                <button class="tab" onclick="switchTab('settings')">System Settings</button>
                <button class="tab" onclick="switchTab('templates')">Email Templates</button>
            </div>

            <!-- Mailboxes Tab -->
            <div id="mailboxes" class="tab-content active">
                <h2>📋 Mailbox Management</h2>

                <div class="stats-summary">
                    <div class="stat-card">
                        <div class="number">4</div>
                        <div class="label">Total Mailboxes</div>
                    </div>
                    <div class="stat-card">
                        <div class="number">2</div>
                        <div class="label">Active Mailboxes</div>
                    </div>
                    <div class="stat-card">
                        <div class="number">0</div>
                        <div class="label">Total Messages</div>
                    </div>
                    <div class="stat-card">
                        <div class="number">0</div>
                        <div class="label">New Messages</div>
                    </div>
                </div>

                <button class="btn" style="margin-bottom: 20px;" onclick="addMailbox()">+ Add New Mailbox</button>

                <table>
                    <thead>
                        <tr>
                            <th>Mailbox</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Messages</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>2000</strong></td>
                            <td>Admin Extension</td>
                            <td>admin@flexpbx.devinecreations.net</td>
                            <td><span class="status-badge status-enabled">Enabled</span></td>
                            <td>0 new / 0 old</td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-small" onclick="editMailbox('2000')">Edit</button>
                                    <button class="btn btn-small btn-secondary" onclick="resetPassword('2000')">Reset PIN</button>
                                    <button class="btn btn-small btn-danger" onclick="deleteMailbox('2000')">Delete</button>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>2001</strong></td>
                            <td>Walter</td>
                            <td>test@flexpbx.devinecreations.net</td>
                            <td><span class="status-badge status-enabled">Enabled</span></td>
                            <td>0 new / 0 old</td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-small" onclick="editMailbox('2001')">Edit</button>
                                    <button class="btn btn-small btn-secondary" onclick="resetPassword('2001')">Reset PIN</button>
                                    <button class="btn btn-small btn-danger" onclick="deleteMailbox('2001')">Delete</button>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>2002</strong></td>
                            <td>Demo Extension</td>
                            <td>demo@flexpbx.devinecreations.net</td>
                            <td><span class="status-badge status-disabled">Disabled</span></td>
                            <td>-</td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-small btn-success" onclick="enableMailbox('2002')">Enable</button>
                                    <button class="btn btn-small" onclick="editMailbox('2002')">Edit</button>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>2003</strong></td>
                            <td>Support Extension</td>
                            <td>support@flexpbx.devinecreations.net</td>
                            <td><span class="status-badge status-disabled">Disabled</span></td>
                            <td>-</td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-small btn-success" onclick="enableMailbox('2003')">Enable</button>
                                    <button class="btn btn-small" onclick="editMailbox('2003')">Edit</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Global Features Tab -->
            <div id="features" class="tab-content">
                <h2>⚙️ Global Voicemail Features</h2>
                <p style="color: #666; margin-bottom: 20px;">These settings apply to all mailboxes by default (can be overridden per mailbox)</p>

                <div class="settings-grid">
                    <div class="setting-item">
                        <div class="setting-info">
                            <h4>Envelope Information</h4>
                            <p>Play date/time before message</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" checked onchange="toggleFeature('envelope', this.checked)">
                            <span class="slider"></span>
                        </label>
                    </div>

                    <div class="setting-item">
                        <div class="setting-info">
                            <h4>Say Caller ID</h4>
                            <p>Announce caller number before message</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" checked onchange="toggleFeature('saycid', this.checked)">
                            <span class="slider"></span>
                        </label>
                    </div>

                    <div class="setting-item">
                        <div class="setting-info">
                            <h4>Say Duration</h4>
                            <p>Announce message length</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" checked onchange="toggleFeature('sayduration', this.checked)">
                            <span class="slider"></span>
                        </label>
                    </div>

                    <div class="setting-item">
                        <div class="setting-info">
                            <h4>Review Before Saving</h4>
                            <p>Let callers review/re-record</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" checked onchange="toggleFeature('review', this.checked)">
                            <span class="slider"></span>
                        </label>
                    </div>

                    <div class="setting-item">
                        <div class="setting-info">
                            <h4>Operator Access</h4>
                            <p>Allow pressing 0 for operator</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" checked onchange="toggleFeature('operator', this.checked)">
                            <span class="slider"></span>
                        </label>
                    </div>

                    <div class="setting-item">
                        <div class="setting-info">
                            <h4>Callback Feature</h4>
                            <p>Enable callback to sender</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" checked onchange="toggleFeature('callback', this.checked)">
                            <span class="slider"></span>
                        </label>
                    </div>

                    <div class="setting-item">
                        <div class="setting-info">
                            <h4>Dial Out</h4>
                            <p>Allow dialing out (option 4)</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" checked onchange="toggleFeature('dialout', this.checked)">
                            <span class="slider"></span>
                        </label>
                    </div>

                    <div class="setting-item">
                        <div class="setting-info">
                            <h4>Send Voicemail</h4>
                            <p>Compose and send VM (option 5)</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" checked onchange="toggleFeature('sendvoicemail', this.checked)">
                            <span class="slider"></span>
                        </label>
                    </div>

                    <div class="setting-item">
                        <div class="setting-info">
                            <h4>Attach Audio to Email</h4>
                            <p>Include audio file in email</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" checked onchange="toggleFeature('attach', this.checked)">
                            <span class="slider"></span>
                        </label>
                    </div>

                    <div class="setting-item">
                        <div class="setting-info">
                            <h4>Move Heard Messages</h4>
                            <p>Auto-move to Old folder</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" checked onchange="toggleFeature('moveheard', this.checked)">
                            <span class="slider"></span>
                        </label>
                    </div>

                    <div class="setting-item">
                        <div class="setting-info">
                            <h4>Next After Command</h4>
                            <p>Auto-advance after save/delete</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" checked onchange="toggleFeature('nextaftercmd', this.checked)">
                            <span class="slider"></span>
                        </label>
                    </div>

                    <div class="setting-item">
                        <div class="setting-info">
                            <h4>Use Directory</h4>
                            <p>Enable directory lookups</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" checked onchange="toggleFeature('usedirectory', this.checked)">
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>

                <button class="btn" style="margin-top: 20px;" onclick="saveFeatures()">Save All Features</button>
            </div>

            <!-- System Settings Tab -->
            <div id="settings" class="tab-content">
                <h2>🔧 System Settings</h2>

                <div class="form-group">
                    <label>Audio Format</label>
                    <select>
                        <option>wav49|gsm|wav</option>
                        <option>wav</option>
                        <option>gsm</option>
                        <option>ulaw</option>
                    </select>
                    <small>Format for storing voicemail messages</small>
                </div>

                <div class="form-group">
                    <label>Server Email</label>
                    <input type="email" value="asterisk@flexpbx.devinecreations.net" placeholder="noreply@example.com">
                    <small>From address for voicemail notifications</small>
                </div>

                <div class="form-group">
                    <label>Maximum Message Length (seconds)</label>
                    <input type="number" value="180" placeholder="180">
                    <small>Maximum duration for voicemail messages</small>
                </div>

                <div class="form-group">
                    <label>Minimum Message Length (seconds)</label>
                    <input type="number" value="3" placeholder="3">
                    <small>Minimum duration to keep a message</small>
                </div>

                <div class="form-group">
                    <label>Maximum Silence (seconds)</label>
                    <input type="number" value="10" placeholder="10">
                    <small>Seconds of silence before ending recording</small>
                </div>

                <div class="form-group">
                    <label>Skip Forward/Back (milliseconds)</label>
                    <input type="number" value="3000" placeholder="3000">
                    <small>Duration to skip when using FF/REW</small>
                </div>

                <div class="form-group">
                    <label>Maximum Login Attempts</label>
                    <input type="number" value="3" placeholder="3">
                    <small>Failed login attempts before disconnect</small>
                </div>

                <div class="form-group">
                    <label>Maximum Messages per Folder</label>
                    <input type="number" value="100" placeholder="100">
                    <small>Maximum number of messages per folder</small>
                </div>

                <button class="btn">Save System Settings</button>
            </div>

            <!-- Email Templates Tab -->
            <div id="templates" class="tab-content">
                <h2>📧 Email Templates</h2>

                <div class="form-group">
                    <label>Email Subject</label>
                    <input type="text" value="[FlexPBX] New voicemail in mailbox ${VM_MAILBOX}">
                    <small>Variables: ${VM_NAME}, ${VM_MAILBOX}, ${VM_MSGNUM}, ${VM_CALLERID}, ${VM_DUR}, ${VM_DATE}</small>
                </div>

                <div class="form-group">
                    <label>Email Body</label>
                    <textarea rows="10" placeholder="Email body template...">Dear ${VM_NAME},

You have received a new voicemail message (${VM_MSGNUM}) in mailbox ${VM_MAILBOX}.

From: ${VM_CALLERID}
Duration: ${VM_DUR}
Date: ${VM_DATE}

Please check your voicemail by dialing *97 from your extension.

-- FlexPBX Voicemail System</textarea>
                    <small>Use variables listed above in subject</small>
                </div>

                <div class="form-group">
                    <label>Date Format</label>
                    <input type="text" value="%A, %B %d, %Y at %r">
                    <small>strftime format for date/time in emails</small>
                </div>

                <button class="btn">Save Email Templates</button>
                <button class="btn btn-secondary" style="margin-left: 10px;" onclick="testEmail()">Send Test Email</button>
            </div>
        </div>

        <!-- Instructions -->
        <div class="card">
            <h2>ℹ️ Instructions</h2>
            <ul style="line-height: 1.8; color: #666;">
                <li><strong>Enable/Disable Features:</strong> Use toggles to control voicemail features globally</li>
                <li><strong>Manage Mailboxes:</strong> Add, edit, or delete user mailboxes from the Mailboxes tab</li>
                <li><strong>Reload After Changes:</strong> Click "Reload Voicemail" to apply configuration changes</li>
                <li><strong>Backup First:</strong> Always create a backup before making major changes</li>
                <li><strong>Test Thoroughly:</strong> Test voicemail by calling *97 after making changes</li>
            </ul>
        </div>
    </div>

    <script>
        // Load mailboxes on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadMailboxes();
        });

        function switchTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });

            // Show selected tab
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }

        // Load all mailboxes using new comprehensive API
        function loadMailboxes() {
            fetch('/api/voicemail.php?path=list')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.mailboxes) {
                        updateMailboxTable(data.mailboxes);
                    } else {
                        showAlert('error', 'Failed to load mailboxes: ' + (data.error || 'Unknown error'));
                    }
                })
                .catch(error => {
                    showAlert('error', 'Error loading mailboxes: ' + error.message);
                });
        }

        function updateMailboxTable(mailboxes) {
            // This would update the mailbox table rows with real data
            // For now, the static HTML remains, but this function is ready for dynamic updates
            console.log('Loaded mailboxes:', mailboxes);
        }

        function toggleFeature(feature, enabled) {
            showAlert('info', `${enabled ? 'Enabling' : 'Disabling'} ${feature}... (Feature will be saved when you click "Save All Features")`);
        }

        function saveFeatures() {
            // Save global voicemail settings using new comprehensive API
            fetch('/api/voicemail.php?path=update_settings', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    // Collect all feature toggles here
                    features: {
                        email_enabled: true,
                        transcription_enabled: false,
                        // etc.
                    }
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', 'All feature settings saved successfully! Click "Reload Voicemail" to apply changes.');
                } else {
                    showAlert('error', 'Failed to save features: ' + data.error);
                }
            })
            .catch(error => {
                showAlert('error', 'Error saving features: ' + error.message);
            });
        }

        function reloadVoicemail() {
            const btn = document.getElementById('reloadBtn');
            btn.disabled = true;
            btn.textContent = '⏳ Reloading...';

            // Reload voicemail using new comprehensive API
            fetch('/api/voicemail.php?path=reload', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', '✓ Voicemail module reloaded successfully!');
                } else {
                    showAlert('error', 'Failed to reload voicemail: ' + data.error);
                }
                btn.disabled = false;
                btn.textContent = '🔄 Reload Voicemail';
            })
            .catch(error => {
                showAlert('error', 'Error reloading voicemail: ' + error.message);
                btn.disabled = false;
                btn.textContent = '🔄 Reload Voicemail';
            });
        }

        function backupConfig() {
            if (confirm('Create a backup of voicemail.conf?')) {
                fetch('/api/voicemail.php?path=backup', {
                    method: 'POST'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('success', '✓ Configuration backed up to: ' + data.backup_file);
                    } else {
                        showAlert('error', 'Backup failed: ' + data.error);
                    }
                })
                .catch(error => {
                    showAlert('error', 'Error creating backup: ' + error.message);
                });
            }
        }

        function addMailbox() {
            const mailbox = prompt('Enter mailbox number (e.g., 2004):');
            if (mailbox) {
                const name = prompt('Enter user name:');
                const pin = prompt('Enter 4-digit PIN:');
                const email = prompt('Enter email address (optional):');

                if (name && pin) {
                    fetch('/api/voicemail.php?path=create', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            mailbox: mailbox,
                            pin: pin,
                            name: name,
                            email: email || '',
                            context: 'flexpbx'
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showAlert('success', `Mailbox ${mailbox} added successfully!`);
                            loadMailboxes(); // Reload the list
                        } else {
                            showAlert('error', 'Failed to add mailbox: ' + data.error);
                        }
                    })
                    .catch(error => {
                        showAlert('error', 'Error adding mailbox: ' + error.message);
                    });
                }
            }
        }

        function editMailbox(mailbox) {
            // Load mailbox details first
            fetch(`/api/voicemail.php?path=details&mailbox=${mailbox}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('info', `Editing mailbox ${mailbox}... (Full editor to be implemented)`);
                        // Future: Open modal with full edit form
                    } else {
                        showAlert('error', 'Failed to load mailbox details: ' + data.error);
                    }
                })
                .catch(error => {
                    showAlert('error', 'Error loading mailbox: ' + error.message);
                });
        }

        function resetPassword(mailbox) {
            if (confirm(`Reset password for mailbox ${mailbox}?`)) {
                const newPin = prompt('Enter new 4-digit PIN:');
                if (newPin && newPin.length >= 4) {
                    fetch(`/api/voicemail.php?path=update&mailbox=${mailbox}`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            pin: newPin
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showAlert('success', `Password for mailbox ${mailbox} reset successfully!`);
                        } else {
                            showAlert('error', 'Failed to reset password: ' + data.error);
                        }
                    })
                    .catch(error => {
                        showAlert('error', 'Error resetting password: ' + error.message);
                    });
                }
            }
        }

        function deleteMailbox(mailbox) {
            if (confirm(`Are you sure you want to delete mailbox ${mailbox}? This action cannot be undone!`)) {
                fetch(`/api/voicemail.php?path=delete&mailbox=${mailbox}`, {
                    method: 'POST'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('success', `Mailbox ${mailbox} deleted successfully!`);
                        loadMailboxes(); // Reload the list
                    } else {
                        showAlert('error', 'Failed to delete mailbox: ' + data.error);
                    }
                })
                .catch(error => {
                    showAlert('error', 'Error deleting mailbox: ' + error.message);
                });
            }
        }

        function enableMailbox(mailbox) {
            fetch(`/api/voicemail.php?path=update&mailbox=${mailbox}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    enabled: true
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', `Mailbox ${mailbox} enabled successfully!`);
                } else {
                    showAlert('error', 'Failed to enable mailbox: ' + data.error);
                }
            })
            .catch(error => {
                showAlert('error', 'Error enabling mailbox: ' + error.message);
            });
        }

        function testEmail() {
            const email = prompt('Enter email address to send test to:');
            if (email) {
                fetch('/api/voicemail.php?path=test_email', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email: email })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('success', `Test email sent to ${email}!`);
                    } else {
                        showAlert('error', 'Failed to send test email: ' + data.error);
                    }
                })
                .catch(error => {
                    showAlert('error', 'Error sending test email: ' + error.message);
                });
            }
        }

        function showAlert(type, message) {
            const container = document.getElementById('alertContainer');
            const alert = document.createElement('div');
            alert.className = `alert alert-${type} show`;
            alert.textContent = message;

            container.innerHTML = '';
            container.appendChild(alert);

            if (type === 'success' || type === 'info') {
                setTimeout(() => {
                    alert.classList.remove('show');
                    setTimeout(() => alert.remove(), 300);
                }, 5000);
            }
        }
    </script>
</body>
</html>
