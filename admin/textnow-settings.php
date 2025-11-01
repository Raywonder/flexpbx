<?php
require_once __DIR__ . '/admin_auth_check.php';
/**
 * FlexPBX TextNow Integration Settings
 * Complete TextNow configuration interface for FlexPBX
 */

session_start();

// Auth check
$is_admin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

if (!$is_admin) {
    header('Location: /admin/login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TextNow Integration - FlexPBX Admin</title>
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
            padding: 20px 30px;
            border-radius: 10px 10px 0 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            color: #333;
            font-size: 24px;
        }

        .header .logo {
            font-size: 28px;
            font-weight: bold;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .back-btn {
            padding: 10px 20px;
            background: #f5f5f5;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            color: #333;
            font-size: 14px;
        }

        .back-btn:hover {
            background: #e0e0e0;
        }

        .nav {
            background: white;
            padding: 15px 30px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            gap: 10px;
        }

        .nav-btn {
            padding: 10px 20px;
            background: #f5f5f5;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
        }

        .nav-btn:hover {
            background: #e0e0e0;
        }

        .nav-btn.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .content {
            background: white;
            padding: 30px;
            border-radius: 0 0 10px 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
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

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .checkbox-group input[type="checkbox"] {
            width: auto;
        }

        .btn {
            padding: 12px 24px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }

        .btn-secondary {
            background: #6c757d;
        }

        .btn-danger {
            background: #dc3545;
        }

        .btn-success {
            background: #28a745;
        }

        .alert {
            padding: 15px;
            border-radius: 5px;
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .stat-card h3 {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 10px;
        }

        .stat-card .value {
            font-size: 32px;
            font-weight: bold;
        }

        .message-list,
        .call-list {
            max-height: 500px;
            overflow-y: auto;
        }

        .message-item,
        .call-item {
            padding: 15px;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            margin-bottom: 10px;
            transition: all 0.3s;
        }

        .message-item:hover,
        .call-item:hover {
            background: #f8f9fa;
            border-color: #667eea;
        }

        .message-header,
        .call-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .message-body {
            color: #666;
            margin-bottom: 8px;
        }

        .message-time,
        .call-time {
            font-size: 12px;
            color: #999;
        }

        .direction-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }

        .inbound {
            background: #d4edda;
            color: #155724;
        }

        .outbound {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .status-failed {
            background: #f8d7da;
            color: #721c24;
        }

        .status-in-progress {
            background: #fff3cd;
            color: #856404;
        }

        .media-preview {
            display: flex;
            gap: 10px;
            margin-top: 10px;
            flex-wrap: wrap;
        }

        .media-item {
            width: 100px;
            height: 100px;
            border-radius: 5px;
            object-fit: cover;
            cursor: pointer;
            border: 2px solid #e0e0e0;
        }

        .media-item:hover {
            border-color: #667eea;
        }

        .number-list {
            list-style: none;
        }

        .number-item {
            padding: 15px;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .number-info {
            flex: 1;
        }

        .number-phone {
            font-size: 18px;
            font-weight: 500;
            margin-bottom: 5px;
        }

        .number-name {
            font-size: 14px;
            color: #666;
        }

        .number-actions {
            display: flex;
            gap: 10px;
        }

        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
        }

        .webhook-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
        }

        .webhook-info h4 {
            margin-bottom: 10px;
        }

        .webhook-url {
            background: white;
            padding: 10px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 13px;
            word-break: break-all;
            margin-bottom: 5px;
        }

        .loading {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        .loading::after {
            content: '...';
            animation: dots 1.5s infinite;
        }

        @keyframes dots {
            0%, 20% { content: '.'; }
            40% { content: '..'; }
            60%, 100% { content: '...'; }
        }

        .filters {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .filters input,
        .filters select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .connection-status {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }

        .status-connected {
            background: #28a745;
        }

        .status-disconnected {
            background: #dc3545;
        }

        .status-checking {
            background: #ffc107;
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <div class="logo">FlexPBX</div>
                <h1>TextNow Integration</h1>
            </div>
            <a href="/admin/dashboard.php" class="back-btn">Back to Dashboard</a>
        </div>

        <div class="nav">
            <button class="nav-btn active" onclick="showTab('config')">Configuration</button>
            <button class="nav-btn" onclick="showTab('calls')">Voice Calls</button>
            <button class="nav-btn" onclick="showTab('messages')">Messages</button>
            <button class="nav-btn" onclick="showTab('numbers')">Phone Numbers</button>
            <button class="nav-btn" onclick="showTab('statistics')">Statistics</button>
        </div>

        <div class="content">
            <div id="alert" class="alert"></div>

            <!-- Configuration Tab -->
            <div id="config-tab" class="tab-content active">
                <h2 style="margin-bottom: 20px;">TextNow API Configuration</h2>

                <div id="connection-status" class="connection-status">
                    <div class="status-indicator status-checking"></div>
                    <span>Checking connection...</span>
                </div>

                <form id="config-form">
                    <div class="form-group">
                        <label for="api_key">API Key *</label>
                        <input type="text" id="api_key" name="api_key" required>
                        <small>Your TextNow API key from the developer dashboard</small>
                    </div>

                    <div class="form-group">
                        <label for="api_secret">API Secret *</label>
                        <input type="password" id="api_secret" name="api_secret" required>
                        <small>Your TextNow API secret (keep this secure)</small>
                    </div>

                    <div class="form-group">
                        <label for="textnow_number">TextNow Phone Number</label>
                        <input type="tel" id="textnow_number" name="textnow_number" placeholder="+15551234567">
                        <small>Your TextNow phone number in E.164 format</small>
                    </div>

                    <div class="form-group">
                        <label for="webhook_url">Webhook Base URL</label>
                        <input type="url" id="webhook_url" name="webhook_url" value="https://flexpbx.devinecreations.net/api/textnow.php">
                        <small>Base URL for receiving webhooks from TextNow</small>
                    </div>

                    <div class="form-group">
                        <label for="rate_limit_per_minute">Rate Limit (requests per minute)</label>
                        <input type="number" id="rate_limit_per_minute" name="rate_limit_per_minute" value="60" min="1" max="1000">
                        <small>Maximum API requests per minute</small>
                    </div>

                    <div class="form-group checkbox-group">
                        <input type="checkbox" id="record_calls" name="record_calls">
                        <label for="record_calls">Record all calls automatically</label>
                    </div>

                    <div class="form-group checkbox-group">
                        <input type="checkbox" id="enabled" name="enabled" checked>
                        <label for="enabled">Enable TextNow integration</label>
                    </div>

                    <button type="submit" class="btn">Save Configuration</button>
                    <button type="button" class="btn btn-secondary" onclick="testConnection()">Test Connection</button>
                </form>

                <div class="webhook-info">
                    <h4>Webhook Endpoints</h4>
                    <p>Configure these URLs in your TextNow dashboard:</p>
                    <div class="webhook-url">Inbound Calls: https://flexpbx.devinecreations.net/api/textnow.php?action=inbound_call</div>
                    <div class="webhook-url">Inbound SMS: https://flexpbx.devinecreations.net/api/textnow.php?action=inbound_sms</div>
                    <div class="webhook-url">Inbound MMS: https://flexpbx.devinecreations.net/api/textnow.php?action=inbound_mms</div>
                    <div class="webhook-url">Status Callbacks: https://flexpbx.devinecreations.net/api/textnow.php?action=status_callback</div>
                </div>
            </div>

            <!-- Voice Calls Tab -->
            <div id="calls-tab" class="tab-content">
                <h2 style="margin-bottom: 20px;">Voice Calls</h2>

                <div class="filters">
                    <input type="tel" id="call-filter-number" placeholder="Filter by number">
                    <select id="call-filter-status">
                        <option value="">All statuses</option>
                        <option value="completed">Completed</option>
                        <option value="in-progress">In Progress</option>
                        <option value="failed">Failed</option>
                    </select>
                    <button class="btn btn-small" onclick="loadCalls()">Apply Filters</button>
                    <button class="btn btn-success btn-small" onclick="showMakeCallDialog()">Make Call</button>
                </div>

                <div id="calls-list" class="call-list">
                    <div class="loading">Loading calls</div>
                </div>
            </div>

            <!-- Messages Tab -->
            <div id="messages-tab" class="tab-content">
                <h2 style="margin-bottom: 20px;">SMS & MMS Messages</h2>

                <div class="filters">
                    <input type="tel" id="message-filter-number" placeholder="Filter by number">
                    <input type="date" id="message-filter-start" placeholder="Start date">
                    <input type="date" id="message-filter-end" placeholder="End date">
                    <button class="btn btn-small" onclick="loadMessages()">Apply Filters</button>
                    <button class="btn btn-success btn-small" onclick="showSendMessageDialog()">Send SMS</button>
                    <button class="btn btn-success btn-small" onclick="showSendMMSDialog()">Send MMS</button>
                </div>

                <div id="messages-list" class="message-list">
                    <div class="loading">Loading messages</div>
                </div>
            </div>

            <!-- Phone Numbers Tab -->
            <div id="numbers-tab" class="tab-content">
                <h2 style="margin-bottom: 20px;">Phone Numbers</h2>

                <button class="btn btn-success" onclick="showSearchNumbersDialog()" style="margin-bottom: 20px;">Search Available Numbers</button>

                <div id="numbers-list">
                    <div class="loading">Loading phone numbers</div>
                </div>
            </div>

            <!-- Statistics Tab -->
            <div id="statistics-tab" class="tab-content">
                <h2 style="margin-bottom: 20px;">Usage Statistics</h2>

                <div class="stats-grid" id="stats-grid">
                    <div class="stat-card">
                        <h3>Total Calls</h3>
                        <div class="value" id="stat-total-calls">-</div>
                    </div>
                    <div class="stat-card">
                        <h3>Total Messages</h3>
                        <div class="value" id="stat-total-messages">-</div>
                    </div>
                    <div class="stat-card">
                        <h3>Inbound Calls</h3>
                        <div class="value" id="stat-inbound-calls">-</div>
                    </div>
                    <div class="stat-card">
                        <h3>Outbound Calls</h3>
                        <div class="value" id="stat-outbound-calls">-</div>
                    </div>
                    <div class="stat-card">
                        <h3>Inbound Messages</h3>
                        <div class="value" id="stat-inbound-messages">-</div>
                    </div>
                    <div class="stat-card">
                        <h3>Outbound Messages</h3>
                        <div class="value" id="stat-outbound-messages">-</div>
                    </div>
                </div>

                <button class="btn" onclick="loadStatistics()">Refresh Statistics</button>
            </div>
        </div>
    </div>

    <script>
        let currentTab = 'config';

        // Show tab
        function showTab(tab) {
            currentTab = tab;
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.nav-btn').forEach(el => el.classList.remove('active'));

            document.getElementById(tab + '-tab').classList.add('active');
            event.target.classList.add('active');

            // Load data for tab
            if (tab === 'calls') loadCalls();
            if (tab === 'messages') loadMessages();
            if (tab === 'numbers') loadNumbers();
            if (tab === 'statistics') loadStatistics();
        }

        // Show alert
        function showAlert(message, type = 'success') {
            const alert = document.getElementById('alert');
            alert.className = 'alert alert-' + type + ' show';
            alert.textContent = message;

            setTimeout(() => {
                alert.classList.remove('show');
            }, 5000);
        }

        // Load configuration
        async function loadConfig() {
            try {
                const response = await fetch('/api/textnow.php?action=get_config');
                const data = await response.json();

                if (data.success && data.config) {
                    document.getElementById('api_key').value = data.config.api_key || '';
                    document.getElementById('api_secret').value = data.config.api_secret || '';
                    document.getElementById('textnow_number').value = data.config.textnow_number || '';
                    document.getElementById('webhook_url').value = data.config.webhook_url || '';
                    document.getElementById('rate_limit_per_minute').value = data.config.rate_limit_per_minute || 60;
                    document.getElementById('record_calls').checked = data.config.record_calls || false;
                    document.getElementById('enabled').checked = data.config.enabled !== false;
                }
            } catch (error) {
                console.error('Error loading config:', error);
            }
        }

        // Save configuration
        document.getElementById('config-form').addEventListener('submit', async (e) => {
            e.preventDefault();

            const formData = new FormData(e.target);
            formData.append('action', 'save_config');

            try {
                const response = await fetch('/api/textnow.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    showAlert('Configuration saved successfully');
                    testConnection();
                } else {
                    showAlert(data.message || 'Failed to save configuration', 'error');
                }
            } catch (error) {
                showAlert('Error saving configuration: ' + error.message, 'error');
            }
        });

        // Test connection
        async function testConnection() {
            const statusEl = document.getElementById('connection-status');
            statusEl.innerHTML = '<div class="status-indicator status-checking"></div><span>Testing connection...</span>';

            try {
                const response = await fetch('/api/textnow.php?action=test_connection');
                const data = await response.json();

                if (data.success) {
                    statusEl.innerHTML = '<div class="status-indicator status-connected"></div><span>Connected successfully - ' + data.phone_numbers + ' phone number(s)</span>';
                } else {
                    statusEl.innerHTML = '<div class="status-indicator status-disconnected"></div><span>Connection failed: ' + (data.error || 'Unknown error') + '</span>';
                }
            } catch (error) {
                statusEl.innerHTML = '<div class="status-indicator status-disconnected"></div><span>Connection error: ' + error.message + '</span>';
            }
        }

        // Load calls
        async function loadCalls() {
            const listEl = document.getElementById('calls-list');
            listEl.innerHTML = '<div class="loading">Loading calls</div>';

            const number = document.getElementById('call-filter-number')?.value || '';
            const status = document.getElementById('call-filter-status')?.value || '';

            let url = '/api/textnow.php?action=call_history&limit=50';
            if (number) url += '&number=' + encodeURIComponent(number);
            if (status) url += '&status=' + encodeURIComponent(status);

            try {
                const response = await fetch(url);
                const data = await response.json();

                if (data.success && data.calls) {
                    if (data.calls.length === 0) {
                        listEl.innerHTML = '<p>No calls found</p>';
                        return;
                    }

                    listEl.innerHTML = data.calls.map(call => `
                        <div class="call-item">
                            <div class="call-header">
                                <span>
                                    <span class="direction-badge ${call.direction}">${call.direction}</span>
                                    ${call.from} → ${call.to}
                                </span>
                                <span class="status-badge status-${call.status}">${call.status}</span>
                            </div>
                            <div class="call-time">
                                ${new Date(call.created_at).toLocaleString()}
                                ${call.duration ? ' - Duration: ' + Math.floor(call.duration / 60) + 'm ' + (call.duration % 60) + 's' : ''}
                            </div>
                        </div>
                    `).join('');
                } else {
                    listEl.innerHTML = '<p>Failed to load calls</p>';
                }
            } catch (error) {
                listEl.innerHTML = '<p>Error loading calls: ' + error.message + '</p>';
            }
        }

        // Load messages
        async function loadMessages() {
            const listEl = document.getElementById('messages-list');
            listEl.innerHTML = '<div class="loading">Loading messages</div>';

            const number = document.getElementById('message-filter-number')?.value || '';
            const startDate = document.getElementById('message-filter-start')?.value || '';
            const endDate = document.getElementById('message-filter-end')?.value || '';

            let url = '/api/textnow.php?action=list_messages&limit=50';
            if (number) url += '&to=' + encodeURIComponent(number);
            if (startDate) url += '&start_date=' + encodeURIComponent(startDate);
            if (endDate) url += '&end_date=' + encodeURIComponent(endDate);

            try {
                const response = await fetch(url);
                const data = await response.json();

                if (data.success && data.messages) {
                    if (data.messages.length === 0) {
                        listEl.innerHTML = '<p>No messages found</p>';
                        return;
                    }

                    listEl.innerHTML = data.messages.map(msg => `
                        <div class="message-item">
                            <div class="message-header">
                                <span>
                                    <span class="direction-badge ${msg.direction}">${msg.direction}</span>
                                    ${msg.from} → ${msg.to}
                                </span>
                                ${msg.type === 'mms' ? '<span style="color: #667eea;">MMS</span>' : '<span>SMS</span>'}
                            </div>
                            <div class="message-body">${msg.body || '(no text)'}</div>
                            ${msg.media_urls && msg.media_urls.length ? `
                                <div class="media-preview">
                                    ${msg.media_urls.map(url => `<img src="${url}" class="media-item" onclick="window.open('${url}')">`).join('')}
                                </div>
                            ` : ''}
                            <div class="message-time">${new Date(msg.created_at).toLocaleString()}</div>
                        </div>
                    `).join('');
                } else {
                    listEl.innerHTML = '<p>Failed to load messages</p>';
                }
            } catch (error) {
                listEl.innerHTML = '<p>Error loading messages: ' + error.message + '</p>';
            }
        }

        // Load phone numbers
        async function loadNumbers() {
            const listEl = document.getElementById('numbers-list');
            listEl.innerHTML = '<div class="loading">Loading phone numbers</div>';

            try {
                const response = await fetch('/api/textnow.php?action=list_numbers');
                const data = await response.json();

                if (data.success && data.numbers) {
                    if (data.numbers.length === 0) {
                        listEl.innerHTML = '<p>No phone numbers found</p>';
                        return;
                    }

                    listEl.innerHTML = '<ul class="number-list">' + data.numbers.map(num => `
                        <li class="number-item">
                            <div class="number-info">
                                <div class="number-phone">${num.phone_number}</div>
                                <div class="number-name">${num.friendly_name || 'No name'}</div>
                            </div>
                            <div class="number-actions">
                                <button class="btn btn-danger btn-small" onclick="releaseNumber('${num.phone_number}')">Release</button>
                            </div>
                        </li>
                    `).join('') + '</ul>';
                } else {
                    listEl.innerHTML = '<p>Failed to load phone numbers</p>';
                }
            } catch (error) {
                listEl.innerHTML = '<p>Error loading phone numbers: ' + error.message + '</p>';
            }
        }

        // Load statistics
        async function loadStatistics() {
            try {
                const response = await fetch('/api/textnow.php?action=get_statistics');
                const data = await response.json();

                if (data.success && data.statistics) {
                    const stats = data.statistics;
                    document.getElementById('stat-total-calls').textContent = stats.total_calls || 0;
                    document.getElementById('stat-total-messages').textContent = stats.total_messages || 0;
                    document.getElementById('stat-inbound-calls').textContent = stats.inbound_calls || 0;
                    document.getElementById('stat-outbound-calls').textContent = stats.outbound_calls || 0;
                    document.getElementById('stat-inbound-messages').textContent = stats.inbound_messages || 0;
                    document.getElementById('stat-outbound-messages').textContent = stats.outbound_messages || 0;
                }
            } catch (error) {
                showAlert('Error loading statistics: ' + error.message, 'error');
            }
        }

        // Show dialogs
        function showMakeCallDialog() {
            const to = prompt('Enter phone number to call (E.164 format):');
            if (to) makeCall(to);
        }

        function showSendMessageDialog() {
            const to = prompt('Enter phone number (E.164 format):');
            if (to) {
                const message = prompt('Enter message:');
                if (message) sendSMS(to, message);
            }
        }

        function showSendMMSDialog() {
            alert('MMS sending requires file upload. Use the API endpoint directly or implement a file upload dialog.');
        }

        function showSearchNumbersDialog() {
            const areaCode = prompt('Enter area code (optional):');
            searchNumbers(areaCode);
        }

        // API actions
        async function makeCall(to) {
            const formData = new FormData();
            formData.append('action', 'make_call');
            formData.append('to', to);

            try {
                const response = await fetch('/api/textnow.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    showAlert('Call initiated successfully');
                    loadCalls();
                } else {
                    showAlert(data.message || 'Failed to make call', 'error');
                }
            } catch (error) {
                showAlert('Error making call: ' + error.message, 'error');
            }
        }

        async function sendSMS(to, message) {
            const formData = new FormData();
            formData.append('action', 'send_sms');
            formData.append('to', to);
            formData.append('message', message);

            try {
                const response = await fetch('/api/textnow.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    showAlert('SMS sent successfully');
                    loadMessages();
                } else {
                    showAlert(data.message || 'Failed to send SMS', 'error');
                }
            } catch (error) {
                showAlert('Error sending SMS: ' + error.message, 'error');
            }
        }

        async function releaseNumber(number) {
            if (!confirm('Are you sure you want to release this number?')) return;

            const formData = new FormData();
            formData.append('action', 'release_number');
            formData.append('phone_number', number);

            try {
                const response = await fetch('/api/textnow.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    showAlert('Number released successfully');
                    loadNumbers();
                } else {
                    showAlert(data.message || 'Failed to release number', 'error');
                }
            } catch (error) {
                showAlert('Error releasing number: ' + error.message, 'error');
            }
        }

        async function searchNumbers(areaCode) {
            let url = '/api/textnow.php?action=search_numbers&limit=10';
            if (areaCode) url += '&area_code=' + encodeURIComponent(areaCode);

            try {
                const response = await fetch(url);
                const data = await response.json();

                if (data.success && data.available_numbers) {
                    if (data.available_numbers.length === 0) {
                        alert('No numbers found');
                        return;
                    }

                    const numberList = data.available_numbers.map(num => num.phone_number).join('\n');
                    alert('Available numbers:\n\n' + numberList + '\n\nUse the API to purchase a number.');
                } else {
                    alert('Failed to search numbers');
                }
            } catch (error) {
                alert('Error searching numbers: ' + error.message);
            }
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            loadConfig();
            testConnection();
        });
    </script>
</body>
</html>
