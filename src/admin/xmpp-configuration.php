<?php require_once __DIR__ . '/admin_auth_check.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>XMPP Configuration - FlexPBX Admin</title>
    <link rel="stylesheet" href="../includes/admin-styles.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container { max-width: 1400px; margin: 0 auto; }
        .header {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        h1 { color: #333; margin-bottom: 10px; }
        .subtitle { color: #666; font-size: 14px; }
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
        .form-group {
            margin-bottom: 20px;
        }
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
        .card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            border: 2px solid #e0e0e0;
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
        .code-block {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 15px;
            border-radius: 6px;
            font-family: monospace;
            overflow-x: auto;
            margin: 10px 0;
        }
        .two-col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        @media (max-width: 768px) {
            .two-col { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>💬 XMPP Configuration</h1>
            <p class="subtitle">Real-time Messaging & Presence for FlexPBX</p>
        </div>

        <div class="tabs">
            <button class="tab active" onclick="switchTab('overview')">📊 Overview</button>
            <button class="tab" onclick="switchTab('server')">🖥️ Server Settings</button>
            <button class="tab" onclick="switchTab('users')">👥 XMPP Users</button>
            <button class="tab" onclick="switchTab('presence')">📡 Presence Status</button>
            <button class="tab" onclick="switchTab('advanced')">⚙️ Advanced</button>
            <button class="tab" onclick="switchTab('help')">❓ Help</button>
        </div>

        <!-- Overview Tab -->
        <div id="overview-tab" class="tab-content active">
            <h2>XMPP Status Overview</h2>

            <div id="xmpp-status" class="card">
                <h3>Server Status</h3>
                <p id="server-status-text">
                    <span class="status-indicator status-offline"></span>
                    Checking server status...
                </p>
                <button class="btn" onclick="checkServerStatus()">🔄 Refresh Status</button>
            </div>

            <div class="two-col">
                <div class="card">
                    <h3>Connected Users</h3>
                    <p style="font-size: 36px; font-weight: bold; color: #667eea;" id="connected-users">0</p>
                </div>
                <div class="card">
                    <h3>Total XMPP Accounts</h3>
                    <p style="font-size: 36px; font-weight: bold; color: #4ade80;" id="total-accounts">0</p>
                </div>
            </div>

            <div class="alert alert-info">
                <strong>💡 Quick Setup:</strong>
                <ol style="margin-left: 20px; margin-top: 10px;">
                    <li>Install Prosody XMPP server (Server Settings tab)</li>
                    <li>Configure Asterisk XMPP module (Advanced tab)</li>
                    <li>Auto-provision accounts for extensions (XMPP Users tab)</li>
                    <li>Test messaging in web client</li>
                </ol>
            </div>
        </div>

        <!-- Server Settings Tab -->
        <div id="server-tab" class="tab-content">
            <h2>XMPP Server Configuration</h2>

            <div class="alert alert-warning">
                <strong>⚠️ Prerequisites:</strong> Prosody XMPP server must be installed. Run: <code>yum install prosody</code> or <code>apt-get install prosody</code>
            </div>

            <div class="form-group">
                <label>Server Hostname</label>
                <input type="text" id="xmpp-hostname" value="flexpbx.local" placeholder="e.g., flexpbx.local">
            </div>

            <div class="two-col">
                <div class="form-group">
                    <label>BOSH URL</label>
                    <input type="text" id="xmpp-bosh-url" value="http://localhost:5280/http-bind" placeholder="http://localhost:5280/http-bind">
                </div>

                <div class="form-group">
                    <label>WebSocket URL</label>
                    <input type="text" id="xmpp-ws-url" value="ws://localhost:5280/xmpp-websocket" placeholder="ws://localhost:5280/xmpp-websocket">
                </div>
            </div>

            <div class="two-col">
                <div class="form-group">
                    <label>Client Port (C2S)</label>
                    <input type="number" id="xmpp-c2s-port" value="5222">
                </div>

                <div class="form-group">
                    <label>Server Port (S2S)</label>
                    <input type="number" id="xmpp-s2s-port" value="5269">
                </div>
            </div>

            <div class="form-group">
                <label>
                    <input type="checkbox" id="xmpp-tls-required" checked> Require TLS/SSL
                </label>
            </div>

            <div class="form-group">
                <label>
                    <input type="checkbox" id="xmpp-auto-provision"> Auto-provision XMPP accounts for new extensions
                </label>
            </div>

            <button class="btn" onclick="saveServerSettings()">💾 Save Server Settings</button>
            <button class="btn btn-success" onclick="testConnection()">🔌 Test Connection</button>

            <h3 style="margin-top: 30px;">Prosody Installation</h3>

            <div class="code-block">
# RHEL/CentOS/AlmaLinux
yum install epel-release
yum install prosody

# Debian/Ubuntu
apt-get update
apt-get install prosody

# Start service
systemctl enable prosody
systemctl start prosody
systemctl status prosody
            </div>

            <button class="btn" onclick="generateProsodyConfig()">📄 Generate Prosody Config</button>
        </div>

        <!-- XMPP Users Tab -->
        <div id="users-tab" class="tab-content">
            <h2>XMPP User Accounts</h2>

            <div style="margin-bottom: 20px;">
                <button class="btn" onclick="autoProvisionAll()">🔄 Auto-Provision All Extensions</button>
                <button class="btn btn-success" onclick="showAddUserModal()">➕ Add Manual Account</button>
            </div>

            <div id="xmpp-users-list">
                <table>
                    <thead>
                        <tr>
                            <th>Extension</th>
                            <th>XMPP JID</th>
                            <th>Presence</th>
                            <th>Last Seen</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="users-table-body">
                        <tr>
                            <td colspan="5" style="text-align: center; color: #666;">Loading users...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Presence Status Tab -->
        <div id="presence-tab" class="tab-content">
            <h2>Real-Time Presence Status</h2>

            <div style="margin-bottom: 20px;">
                <button class="btn" onclick="refreshPresence()">🔄 Refresh</button>
            </div>

            <div id="presence-list"></div>
        </div>

        <!-- Advanced Settings Tab -->
        <div id="advanced-tab" class="tab-content">
            <h2>Advanced XMPP Configuration</h2>

            <h3>Asterisk XMPP Module</h3>

            <div class="form-group">
                <label>Enable Asterisk XMPP Integration</label>
                <label>
                    <input type="checkbox" id="asterisk-xmpp-enabled"> Load res_xmpp.so module
                </label>
            </div>

            <div class="two-col">
                <div class="form-group">
                    <label>Asterisk XMPP Username</label>
                    <input type="text" id="asterisk-xmpp-user" value="asterisk" placeholder="asterisk">
                </div>

                <div class="form-group">
                    <label>Asterisk XMPP Password</label>
                    <input type="password" id="asterisk-xmpp-pass" placeholder="Password">
                </div>
            </div>

            <h3 style="margin-top: 30px;">Message Archive Management (MAM)</h3>

            <div class="form-group">
                <label>Message Retention Period</label>
                <select id="mam-retention">
                    <option value="1d">1 Day</option>
                    <option value="1w" selected>1 Week</option>
                    <option value="1m">1 Month</option>
                    <option value="3m">3 Months</option>
                    <option value="1y">1 Year</option>
                    <option value="never">Never Delete</option>
                </select>
            </div>

            <h3 style="margin-top: 30px;">File Transfer</h3>

            <div class="form-group">
                <label>
                    <input type="checkbox" id="file-transfer-enabled" checked> Enable HTTP Upload (File Sharing)
                </label>
            </div>

            <div class="form-group">
                <label>Max File Size (MB)</label>
                <input type="number" id="max-file-size" value="10" min="1" max="100">
            </div>

            <h3 style="margin-top: 30px;">Security</h3>

            <div class="form-group">
                <label>
                    <input type="checkbox" id="c2s-encryption" checked> Require Client-to-Server Encryption
                </label>
            </div>

            <div class="form-group">
                <label>
                    <input type="checkbox" id="s2s-encryption" checked> Require Server-to-Server Encryption
                </label>
            </div>

            <div class="form-group">
                <label>TLS Protocol Version</label>
                <select id="tls-version">
                    <option value="tlsv1_2+">TLS 1.2+ (Recommended)</option>
                    <option value="tlsv1_3">TLS 1.3 Only</option>
                    <option value="tlsv1_2">TLS 1.2 Only</option>
                </select>
            </div>

            <button class="btn" onclick="saveAdvancedSettings()">💾 Save Advanced Settings</button>
            <button class="btn btn-danger" onclick="resetToDefaults()">🔄 Reset to Defaults</button>
        </div>

        <!-- Help Tab -->
        <div id="help-tab" class="tab-content">
            <h2>XMPP Help & Documentation</h2>

            <div class="card">
                <h3>What is XMPP?</h3>
                <p>XMPP (Extensible Messaging and Presence Protocol) adds real-time messaging and presence awareness to FlexPBX. Users can chat, see when others are available, and receive instant notifications.</p>
            </div>

            <div class="card">
                <h3>Features</h3>
                <ul style="margin-left: 20px;">
                    <li>Extension-to-extension instant messaging</li>
                    <li>Presence status (online, away, busy, offline)</li>
                    <li>Message history and archiving</li>
                    <li>File sharing</li>
                    <li>Group chat / conference rooms</li>
                    <li>Click-to-call from chat</li>
                    <li>Voicemail notifications via XMPP</li>
                </ul>
            </div>

            <div class="card">
                <h3>Compatible Clients</h3>
                <ul style="margin-left: 20px;">
                    <li><strong>Desktop:</strong> Pidgin, Gajim, Psi+</li>
                    <li><strong>Mobile:</strong> Conversations (Android), Siskin (iOS)</li>
                    <li><strong>Web:</strong> Built-in FlexPBX web client</li>
                </ul>
            </div>

            <div class="card">
                <h3>Setup Guide</h3>
                <ol style="margin-left: 20px;">
                    <li>Install Prosody XMPP server</li>
                    <li>Configure server settings (Server Settings tab)</li>
                    <li>Auto-provision accounts (XMPP Users tab)</li>
                    <li>Enable Asterisk XMPP module (Advanced tab)</li>
                    <li>Test connection</li>
                </ol>
            </div>

            <div class="card">
                <h3>Firewall Configuration</h3>
                <div class="code-block">
# Allow XMPP ports
firewall-cmd --permanent --add-port=5222/tcp  # Client connections
firewall-cmd --permanent --add-port=5269/tcp  # Server-to-server
firewall-cmd --permanent --add-port=5280/tcp  # BOSH/WebSocket
firewall-cmd --reload
                </div>
            </div>

            <div class="card">
                <h3>Documentation</h3>
                <p>Full documentation: <code>/home/flexpbxuser/apps/flexpbx/FLEXPBX_XMPP_INTEGRATION.md</code></p>
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

            if (tabName === 'users') {
                loadXMPPUsers();
            } else if (tabName === 'presence') {
                loadPresenceStatus();
            }
        }

        // Server status check
        function checkServerStatus() {
            fetch('/api/xmpp.php?path=status')
                .then(r => r.json())
                .then(data => {
                    const statusEl = document.getElementById('server-status-text');
                    if (data.success && data.data.connected) {
                        statusEl.innerHTML = '<span class="status-indicator status-online"></span> XMPP Server Online';
                        document.getElementById('connected-users').textContent = data.data.connected_users || 0;
                    } else {
                        statusEl.innerHTML = '<span class="status-indicator status-offline"></span> XMPP Server Offline or Not Configured';
                    }
                })
                .catch(() => {
                    document.getElementById('server-status-text').innerHTML =
                        '<span class="status-indicator status-offline"></span> Unable to check server status';
                });
        }

        // Load XMPP users
        function loadXMPPUsers() {
            fetch('/api/xmpp.php?path=list-users')
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const tbody = document.getElementById('users-table-body');
                        tbody.innerHTML = data.data.users.map(user => `
                            <tr>
                                <td>${user.extension_number}</td>
                                <td>${user.xmpp_jid}</td>
                                <td><span class="status-indicator status-${user.presence_status}"></span> ${user.presence_status}</td>
                                <td>${user.last_seen || 'Never'}</td>
                                <td>
                                    <button class="btn" onclick="resetPassword('${user.xmpp_jid}')" style="padding: 6px 12px; font-size: 12px;">Reset Password</button>
                                </td>
                            </tr>
                        `).join('');

                        document.getElementById('total-accounts').textContent = data.data.users.length;
                    }
                });
        }

        // Auto-provision all extensions
        function autoProvisionAll() {
            if (!confirm('Auto-provision XMPP accounts for all extensions? This will create accounts for extensions that don\'t have them.')) {
                return;
            }

            fetch('/api/xmpp.php?path=auto-provision', { method: 'POST' })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        alert('Provisioned ' + data.data.provisioned + ' XMPP accounts');
                        loadXMPPUsers();
                    } else {
                        alert('Error: ' + data.error);
                    }
                });
        }

        // Generate Prosody config
        function generateProsodyConfig() {
            fetch('/api/xmpp.php?path=generate-config')
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        const config = data.data.config;
                        const blob = new Blob([config], { type: 'text/plain' });
                        const url = URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = 'flexpbx.cfg.lua';
                        a.click();
                    }
                });
        }

        // Initialize
        window.addEventListener('DOMContentLoaded', () => {
            checkServerStatus();
        });
    </script>
</body>
</html>
