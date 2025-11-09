<?php require_once __DIR__ . '/admin_auth_check.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messaging Center - FlexPBX Admin</title>
    <link rel="stylesheet" href="../includes/admin-styles.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container { max-width: 1600px; margin: 0 auto; }
        .header {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        h1 { color: #333; margin-bottom: 10px; }
        .subtitle { color: #666; font-size: 14px; }
        .nav-links {
            margin-top: 15px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .nav-link {
            display: inline-block;
            padding: 8px 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 13px;
            transition: transform 0.2s;
        }
        .nav-link:hover { transform: translateY(-2px); }
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .tab {
            background: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .tab.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .tab-content {
            display: none;
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        .tab-content.active { display: block; }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            border: 2px solid #e0e0e0;
        }
        .card h3 { color: #333; margin-bottom: 10px; }
        .card p { color: #666; font-size: 14px; }
        .stat-number {
            font-size: 36px;
            font-weight: bold;
            color: #667eea;
        }
        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }
        .btn-success { background: linear-gradient(135deg, #4ade80 0%, #22c55e 100%); }
        .btn-danger { background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%); }
        .btn-warning { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
        .form-group { margin-bottom: 20px; }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
        }
        input[type="text"],
        input[type="password"],
        input[type="number"],
        select,
        textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }
        textarea {
            min-height: 100px;
            resize: vertical;
            font-family: inherit;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
        }
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-success { background: #4ade80; color: white; }
        .badge-danger { background: #ef4444; color: white; }
        .badge-warning { background: #f59e0b; color: white; }
        .badge-info { background: #3b82f6; color: white; }
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #3b82f6;
        }
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #4ade80;
        }
        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #f59e0b;
        }
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }
        .status-online { background: #4ade80; }
        .status-offline { background: #ef4444; }
        .status-away { background: #f59e0b; }
        .two-col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        @media (max-width: 768px) {
            .two-col { grid-template-columns: 1fr; }
        }
        .message-thread {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 15px;
            background: #f8f9fa;
        }
        .message-item {
            background: white;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 10px;
            border-left: 4px solid #667eea;
        }
        .message-item.outgoing {
            border-left-color: #4ade80;
        }
        .message-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            font-size: 12px;
            color: #666;
        }
        .message-body {
            color: #333;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>💬 Messaging Center</h1>
            <p class="subtitle">Unified SMS, XMPP, and Internal Messaging Platform</p>
            <div class="nav-links">
                <a href="dashboard.php" class="nav-link">🏠 Dashboard</a>
                <a href="extensions.php" class="nav-link">📞 Extensions</a>
                <a href="ivr-builder.php" class="nav-link">📱 IVR Builder</a>
            </div>
        </div>

        <div class="tabs">
            <button class="tab active" onclick="switchTab('overview')">📊 Overview</button>
            <button class="tab" onclick="switchTab('sms')">📱 SMS</button>
            <button class="tab" onclick="switchTab('xmpp')">💬 XMPP Chat</button>
            <button class="tab" onclick="switchTab('compose')">✉️ Compose</button>
            <button class="tab" onclick="switchTab('history')">📜 Message History</button>
            <button class="tab" onclick="switchTab('providers')">🔧 Providers</button>
            <button class="tab" onclick="switchTab('settings')">⚙️ Settings</button>
        </div>

        <!-- Overview Tab -->
        <div id="overview-tab" class="tab-content active">
            <h2>Messaging Overview</h2>

            <div class="grid">
                <div class="card">
                    <h3>SMS Messages (24h)</h3>
                    <p class="stat-number" id="sms-count-24h">0</p>
                    <p>Sent/Received today</p>
                </div>
                <div class="card">
                    <h3>XMPP Messages (24h)</h3>
                    <p class="stat-number" id="xmpp-count-24h">0</p>
                    <p>Internal chat messages</p>
                </div>
                <div class="card">
                    <h3>Active Conversations</h3>
                    <p class="stat-number" id="active-conversations">0</p>
                    <p>Ongoing threads</p>
                </div>
                <div class="card">
                    <h3>Online Users</h3>
                    <p class="stat-number" id="online-users">0</p>
                    <p>Available for chat</p>
                </div>
            </div>

            <h3 style="margin-top: 30px;">Messaging Channels Status</h3>

            <div class="two-col">
                <div class="card">
                    <h3>SMS Providers</h3>
                    <div id="sms-providers-status">Loading...</div>
                </div>
                <div class="card">
                    <h3>XMPP Server</h3>
                    <div id="xmpp-server-status">
                        <p><span class="status-indicator status-offline"></span> Checking...</p>
                    </div>
                </div>
            </div>

            <div class="alert alert-info" style="margin-top: 20px;">
                <strong>💡 Quick Actions:</strong>
                <div style="margin-top: 10px; display: flex; gap: 10px; flex-wrap: wrap;">
                    <button class="btn" onclick="switchTab('compose')">✉️ Send New Message</button>
                    <button class="btn btn-success" onclick="refreshOverview()">🔄 Refresh Stats</button>
                    <button class="btn btn-warning" onclick="switchTab('providers')">🔧 Configure Providers</button>
                </div>
            </div>
        </div>

        <!-- SMS Tab -->
        <div id="sms-tab" class="tab-content">
            <h2>SMS Messaging</h2>

            <div style="margin-bottom: 20px;">
                <button class="btn" onclick="showSendSMSModal()">📱 Send SMS</button>
                <button class="btn btn-success" onclick="refreshSMSList()">🔄 Refresh</button>
            </div>

            <div class="two-col" style="margin-bottom: 20px;">
                <div class="form-group">
                    <label>Filter by Provider</label>
                    <select id="sms-provider-filter" onchange="filterSMS()">
                        <option value="all">All Providers</option>
                        <option value="twilio">Twilio</option>
                        <option value="textnow">TextNow</option>
                        <option value="googlevoice">Google Voice</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Filter by Direction</label>
                    <select id="sms-direction-filter" onchange="filterSMS()">
                        <option value="all">All Messages</option>
                        <option value="inbound">Inbound</option>
                        <option value="outbound">Outbound</option>
                    </select>
                </div>
            </div>

            <div id="sms-messages-list">
                <table>
                    <thead>
                        <tr>
                            <th>Date/Time</th>
                            <th>Direction</th>
                            <th>From</th>
                            <th>To</th>
                            <th>Message</th>
                            <th>Provider</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="sms-tbody">
                        <tr>
                            <td colspan="8" style="text-align: center; color: #666;">Loading SMS messages...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- XMPP Chat Tab -->
        <div id="xmpp-tab" class="tab-content">
            <h2>XMPP Internal Chat</h2>

            <div class="alert alert-info">
                <strong>ℹ️ XMPP Status:</strong>
                <span id="xmpp-connection-status">Not connected</span>
                <button class="btn" onclick="connectXMPP()" style="margin-left: 10px;">🔌 Connect</button>
            </div>

            <div class="two-col">
                <div class="card">
                    <h3>Roster (Contacts)</h3>
                    <div id="xmpp-roster"></div>
                </div>
                <div class="card">
                    <h3>Active Conversation</h3>
                    <div id="current-chat-jid" style="margin-bottom: 10px; font-weight: 600; color: #667eea;"></div>
                    <div class="message-thread" id="message-thread">
                        <p style="text-align: center; color: #666;">Select a contact to start chatting</p>
                    </div>
                    <div style="margin-top: 10px; display: flex; gap: 10px;">
                        <textarea id="xmpp-message-input" placeholder="Type your message..." style="flex: 1;"></textarea>
                        <button class="btn" onclick="sendXMPPMessage()">Send</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Compose Tab -->
        <div id="compose-tab" class="tab-content">
            <h2>Compose New Message</h2>

            <div class="form-group">
                <label>Message Type</label>
                <select id="compose-type" onchange="toggleComposeFields()">
                    <option value="sms">SMS</option>
                    <option value="xmpp">XMPP Chat</option>
                    <option value="both">Both (SMS + XMPP)</option>
                </select>
            </div>

            <div id="sms-compose-fields">
                <div class="two-col">
                    <div class="form-group">
                        <label>SMS Provider</label>
                        <select id="compose-sms-provider">
                            <option value="">Select provider...</option>
                            <option value="twilio">Twilio</option>
                            <option value="textnow">TextNow</option>
                            <option value="googlevoice">Google Voice</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>From Number</label>
                        <select id="compose-from-number">
                            <option value="">Select from number...</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label>To (Phone Number or Extension)</label>
                <input type="text" id="compose-to" placeholder="e.g., +15555551234 or extension 2000">
            </div>

            <div class="form-group">
                <label>Message</label>
                <textarea id="compose-message" placeholder="Type your message here..." style="min-height: 150px;"></textarea>
                <div style="margin-top: 5px; font-size: 12px; color: #666;">
                    <span id="char-count">0</span> characters
                    <span id="sms-segments" style="margin-left: 15px;"></span>
                </div>
            </div>

            <div style="display: flex; gap: 10px;">
                <button class="btn" onclick="sendComposedMessage()">📤 Send Message</button>
                <button class="btn btn-danger" onclick="clearCompose()">🗑️ Clear</button>
            </div>
        </div>

        <!-- Message History Tab -->
        <div id="history-tab" class="tab-content">
            <h2>Message History & Search</h2>

            <div class="two-col" style="margin-bottom: 20px;">
                <div class="form-group">
                    <label>Search</label>
                    <input type="text" id="history-search" placeholder="Search messages...">
                </div>
                <div class="form-group">
                    <label>Date Range</label>
                    <select id="history-date-range" onchange="loadHistory()">
                        <option value="today">Today</option>
                        <option value="week">Last 7 Days</option>
                        <option value="month" selected>Last 30 Days</option>
                        <option value="all">All Time</option>
                    </select>
                </div>
            </div>

            <div style="margin-bottom: 20px;">
                <button class="btn" onclick="loadHistory()">🔍 Search</button>
                <button class="btn btn-success" onclick="exportHistory()">📊 Export to CSV</button>
            </div>

            <div id="history-results"></div>
        </div>

        <!-- Providers Tab -->
        <div id="providers-tab" class="tab-content">
            <h2>Messaging Providers Configuration</h2>

            <div class="alert alert-warning">
                <strong>⚠️ Note:</strong> Provider configurations are stored securely. Test connections after making changes.
            </div>

            <h3>SMS Providers</h3>

            <div class="card" style="margin-bottom: 20px;">
                <h4>Twilio</h4>
                <div class="two-col">
                    <div class="form-group">
                        <label>Account SID</label>
                        <input type="text" id="twilio-sid" placeholder="ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx">
                    </div>
                    <div class="form-group">
                        <label>Auth Token</label>
                        <input type="password" id="twilio-token" placeholder="Auth token">
                    </div>
                </div>
                <div class="form-group">
                    <label>From Number</label>
                    <input type="text" id="twilio-from" placeholder="+15555551234">
                </div>
                <button class="btn" onclick="saveProvider('twilio')">💾 Save Twilio</button>
                <button class="btn btn-success" onclick="testProvider('twilio')">✅ Test Connection</button>
            </div>

            <div class="card" style="margin-bottom: 20px;">
                <h4>TextNow</h4>
                <div class="two-col">
                    <div class="form-group">
                        <label>API Key</label>
                        <input type="password" id="textnow-apikey" placeholder="API Key">
                    </div>
                    <div class="form-group">
                        <label>From Number</label>
                        <input type="text" id="textnow-from" placeholder="+15555551234">
                    </div>
                </div>
                <button class="btn" onclick="saveProvider('textnow')">💾 Save TextNow</button>
                <button class="btn btn-success" onclick="testProvider('textnow')">✅ Test Connection</button>
            </div>

            <div class="card" style="margin-bottom: 20px;">
                <h4>Google Voice</h4>
                <div class="form-group">
                    <label>OAuth Credentials JSON</label>
                    <textarea id="googlevoice-creds" placeholder="Paste OAuth credentials JSON..." style="min-height: 100px;"></textarea>
                </div>
                <button class="btn" onclick="saveProvider('googlevoice')">💾 Save Google Voice</button>
                <button class="btn btn-success" onclick="testProvider('googlevoice')">✅ Test Connection</button>
            </div>

            <h3 style="margin-top: 30px;">XMPP Configuration</h3>

            <div class="card">
                <h4>XMPP Server Settings</h4>
                <div class="form-group">
                    <label>Server Hostname</label>
                    <input type="text" id="xmpp-hostname" value="flexpbx.local" placeholder="flexpbx.local">
                </div>
                <div class="two-col">
                    <div class="form-group">
                        <label>WebSocket URL</label>
                        <input type="text" id="xmpp-ws-url" value="ws://localhost:5280/xmpp-websocket" placeholder="ws://localhost:5280/xmpp-websocket">
                    </div>
                    <div class="form-group">
                        <label>BOSH URL</label>
                        <input type="text" id="xmpp-bosh-url" value="http://localhost:5280/http-bind" placeholder="http://localhost:5280/http-bind">
                    </div>
                </div>
                <button class="btn" onclick="saveProvider('xmpp')">💾 Save XMPP Settings</button>
                <button class="btn btn-success" onclick="testXMPPConnection()">✅ Test Connection</button>
                <button class="btn btn-warning" onclick="window.location.href='xmpp-configuration.php'">⚙️ Advanced XMPP Settings</button>
            </div>
        </div>

        <!-- Settings Tab -->
        <div id="settings-tab" class="tab-content">
            <h2>Messaging Settings</h2>

            <div class="card" style="margin-bottom: 20px;">
                <h3>General Settings</h3>
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="enable-sms-notifications"> Enable SMS notifications for voicemail
                    </label>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="enable-xmpp-notifications"> Enable XMPP notifications for missed calls
                    </label>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="store-message-history"> Store message history in database
                    </label>
                </div>
                <div class="form-group">
                    <label>Message Retention Period</label>
                    <select id="message-retention">
                        <option value="7">7 days</option>
                        <option value="30" selected>30 days</option>
                        <option value="90">90 days</option>
                        <option value="365">1 year</option>
                        <option value="0">Forever</option>
                    </select>
                </div>
            </div>

            <div class="card">
                <h3>Auto-Response Settings</h3>
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="enable-auto-response"> Enable auto-response for after hours
                    </label>
                </div>
                <div class="form-group">
                    <label>Auto-Response Message</label>
                    <textarea id="auto-response-message" placeholder="Thank you for contacting us. We will respond during business hours."></textarea>
                </div>
            </div>

            <div style="margin-top: 20px;">
                <button class="btn" onclick="saveSettings()">💾 Save Settings</button>
            </div>
        </div>
    </div>

    <script>
        // Tab switching
        function switchTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.tab').forEach(btn => btn.classList.remove('active'));
            document.getElementById(tabName + '-tab').classList.add('active');
            event.target.classList.add('active');

            if (tabName === 'overview') {
                loadOverview();
            } else if (tabName === 'sms') {
                loadSMSMessages();
            } else if (tabName === 'xmpp') {
                loadXMPPRoster();
            }
        }

        // Load overview stats
        function loadOverview() {
            fetch('/api/messaging.php?path=overview')
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('sms-count-24h').textContent = data.data.sms_24h;
                        document.getElementById('xmpp-count-24h').textContent = data.data.xmpp_24h;
                        document.getElementById('active-conversations').textContent = data.data.active_threads;
                        document.getElementById('online-users').textContent = data.data.online_users;

                        // Update provider status
                        let providersHTML = '';
                        data.data.sms_providers.forEach(p => {
                            providersHTML += `<p><span class="status-indicator status-${p.active ? 'online' : 'offline'}"></span> ${p.name}: ${p.status}</p>`;
                        });
                        document.getElementById('sms-providers-status').innerHTML = providersHTML;

                        const xmppStatus = data.data.xmpp_status;
                        document.getElementById('xmpp-server-status').innerHTML =
                            `<p><span class="status-indicator status-${xmppStatus.connected ? 'online' : 'offline'}"></span> ${xmppStatus.message}</p>`;
                    }
                });
        }

        // Load SMS messages
        function loadSMSMessages() {
            fetch('/api/messaging.php?path=sms-list')
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const tbody = document.getElementById('sms-tbody');
                        tbody.innerHTML = data.data.messages.map(msg => `
                            <tr>
                                <td>${msg.timestamp}</td>
                                <td><span class="badge badge-${msg.direction === 'inbound' ? 'info' : 'success'}">${msg.direction}</span></td>
                                <td>${msg.from_number}</td>
                                <td>${msg.to_number}</td>
                                <td>${msg.message_body}</td>
                                <td>${msg.provider}</td>
                                <td><span class="badge badge-${msg.status === 'delivered' ? 'success' : 'warning'}">${msg.status}</span></td>
                                <td>
                                    <button class="btn" onclick="replyToSMS('${msg.from_number}')" style="padding: 6px 12px; font-size: 12px;">Reply</button>
                                </td>
                            </tr>
                        `).join('');
                    }
                });
        }

        // Character count for compose
        document.addEventListener('DOMContentLoaded', () => {
            const textarea = document.getElementById('compose-message');
            if (textarea) {
                textarea.addEventListener('input', () => {
                    const length = textarea.value.length;
                    document.getElementById('char-count').textContent = length;

                    const segments = Math.ceil(length / 160);
                    document.getElementById('sms-segments').textContent = segments > 0 ? `(${segments} SMS segment${segments > 1 ? 's' : ''})` : '';
                });
            }

            // Auto-load overview
            loadOverview();
        });

        // Provider save function
        function saveProvider(provider) {
            alert(`Saving ${provider} configuration...`);
            // Implementation would send data to API
        }

        // Test provider
        function testProvider(provider) {
            alert(`Testing ${provider} connection...`);
            // Implementation would test API connection
        }

        function refreshOverview() {
            loadOverview();
        }

        function clearCompose() {
            document.getElementById('compose-to').value = '';
            document.getElementById('compose-message').value = '';
        }
    </script>
</body>
</html>
