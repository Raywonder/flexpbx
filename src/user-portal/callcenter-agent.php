<?php
/**
 * FlexPBX Call Center Agent Dashboard
 * Complete call center agent interface with wrap-ups, statistics, and directory
 */

session_start();

if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: /user-portal/login.php');
    exit;
}

$extension = $_SESSION['user_extension'] ?? 'Unknown';
$username = $_SESSION['user_username'] ?? $extension;
$user_role = $_SESSION['user_role'] ?? 'user';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Call Center Agent - FlexPBX</title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f5f7fa;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 2rem;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            margin: 0;
            font-size: 2rem;
        }

        .status-indicator {
            display: flex;
            align-items: center;
            gap: 1rem;
            background: rgba(255,255,255,0.2);
            padding: 1rem 1.5rem;
            border-radius: 8px;
        }

        .status-dot {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        .status-available { background: #4caf50; }
        .status-paused { background: #ff9800; }
        .status-in-call { background: #f44336; }
        .status-offline { background: #757575; }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .card h3 {
            margin: 0 0 1rem 0;
            color: #333;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .stat-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }

        .stat {
            text-align: center;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #667eea;
        }

        .stat-label {
            font-size: 0.85rem;
            color: #666;
            margin-top: 0.25rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
            margin-top: 0.5rem;
        }

        .btn-login {
            background: #4caf50;
            color: white;
        }

        .btn-login:hover {
            background: #45a049;
        }

        .btn-logout {
            background: #f44336;
            color: white;
        }

        .btn-logout:hover {
            background: #da190b;
        }

        .btn-pause {
            background: #ff9800;
            color: white;
        }

        .btn-pause:hover {
            background: #fb8c00;
        }

        .btn-unpause {
            background: #2196f3;
            color: white;
        }

        .btn-unpause:hover {
            background: #0b7dda;
        }

        .wrap-up-form {
            display: none;
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin-top: 1rem;
        }

        .wrap-up-form.active {
            display: block;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
        }

        .form-group select, .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
        }

        .directory {
            max-height: 400px;
            overflow-y: auto;
        }

        .directory-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem;
            border-bottom: 1px solid #f0f0f0;
        }

        .directory-item:hover {
            background: #f8f9fa;
        }

        .directory-info {
            flex: 1;
        }

        .directory-name {
            font-weight: 600;
            color: #333;
        }

        .directory-ext {
            font-size: 0.9rem;
            color: #666;
        }

        .directory-actions {
            display: flex;
            gap: 0.5rem;
        }

        .icon-btn {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .icon-btn-call {
            background: #4caf50;
            color: white;
        }

        .icon-btn-call:hover {
            background: #45a049;
            transform: scale(1.1);
        }

        .icon-btn-message {
            background: #2196f3;
            color: white;
        }

        .icon-btn-message:hover {
            background: #0b7dda;
            transform: scale(1.1);
        }

        .icon-btn-transfer {
            background: #ff9800;
            color: white;
        }

        .icon-btn-transfer:hover {
            background: #fb8c00;
            transform: scale(1.1);
        }

        .queue-selector {
            margin-bottom: 1rem;
        }

        .queue-selector select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
        }

        .pause-reasons {
            display: none;
            margin-top: 1rem;
        }

        .pause-reasons.active {
            display: block;
        }

        .activity-log {
            max-height: 300px;
            overflow-y: auto;
        }

        .activity-item {
            padding: 0.75rem;
            border-left: 3px solid #667eea;
            background: #f8f9fa;
            margin-bottom: 0.5rem;
            border-radius: 4px;
        }

        .activity-time {
            font-size: 0.85rem;
            color: #666;
        }

        .activity-text {
            color: #333;
            margin-top: 0.25rem;
        }

        .nav-buttons {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .btn-nav {
            padding: 0.75rem 1.5rem;
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-nav:hover {
            background: #667eea;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>📞 Call Center Agent Dashboard</h1>
                <p>Extension <?= htmlspecialchars($extension) ?> - <?= htmlspecialchars($username) ?></p>
            </div>
            <div class="status-indicator">
                <div class="status-dot status-offline" id="status-dot"></div>
                <div>
                    <div style="font-weight: 600; font-size: 1.1rem;" id="status-text">Offline</div>
                    <div style="font-size: 0.9rem; opacity: 0.9;" id="queue-text">Not in queue</div>
                </div>
            </div>
        </div>

        <div class="nav-buttons">
            <a href="/user-portal/" class="btn-nav">← Back to Dashboard</a>
            <a href="call-history.php" class="btn-nav">📞 Call History</a>
            <a href="recordings.php" class="btn-nav">🎙️ Recordings</a>
        </div>

        <div class="grid">
            <!-- Queue Controls -->
            <div class="card">
                <h3><i class="fas fa-users"></i> Queue Controls</h3>

                <div class="queue-selector">
                    <label>Select Queue:</label>
                    <select id="queue-select">
                        <option value="support">Support</option>
                    </select>
                </div>

                <div id="queue-controls">
                    <button class="btn btn-login" onclick="loginToQueue()">
                        <i class="fas fa-sign-in-alt"></i> Login to Queue
                    </button>
                    <button class="btn btn-pause" onclick="togglePause()" style="display: none;">
                        <i class="fas fa-pause"></i> Pause
                    </button>
                    <button class="btn btn-unpause" onclick="togglePause()" style="display: none;">
                        <i class="fas fa-play"></i> Unpause
                    </button>
                    <button class="btn btn-logout" onclick="logoutFromQueue()" style="display: none;">
                        <i class="fas fa-sign-out-alt"></i> Logout from Queue
                    </button>
                </div>

                <div class="pause-reasons" id="pause-reasons">
                    <label>Pause Reason:</label>
                    <select id="pause-reason">
                        <option value="">Select reason...</option>
                        <option value="BREAK">Break</option>
                        <option value="LUNCH">Lunch</option>
                        <option value="MEETING">Meeting</option>
                        <option value="TRAINING">Training</option>
                        <option value="WRAPUP">Wrap-up Work</option>
                        <option value="TECHNICAL">Technical Issue</option>
                        <option value="OTHER">Other</option>
                    </select>
                </div>
            </div>

            <!-- Today's Statistics -->
            <div class="card">
                <h3><i class="fas fa-chart-line"></i> Today's Statistics</h3>
                <div class="stat-grid">
                    <div class="stat">
                        <div class="stat-value" id="calls-answered">0</div>
                        <div class="stat-label">Calls Answered</div>
                    </div>
                    <div class="stat">
                        <div class="stat-value" id="avg-talk-time">0s</div>
                        <div class="stat-label">Avg Talk Time</div>
                    </div>
                    <div class="stat">
                        <div class="stat-value" id="login-time">0h</div>
                        <div class="stat-label">Login Time</div>
                    </div>
                    <div class="stat">
                        <div class="stat-value" id="available-time">0h</div>
                        <div class="stat-label">Available Time</div>
                    </div>
                </div>
            </div>

            <!-- Wrap-Up -->
            <div class="card">
                <h3><i class="fas fa-clipboard-check"></i> Call Wrap-Up</h3>
                <button class="btn btn-unpause" onclick="showWrapUpForm()">
                    <i class="fas fa-edit"></i> Submit Wrap-Up
                </button>

                <div class="wrap-up-form" id="wrap-up-form">
                    <div class="form-group">
                        <label>Wrap-Up Code:</label>
                        <select id="wrap-code">
                            <option value="">Loading...</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Notes (Optional):</label>
                        <textarea id="wrap-notes" rows="3" placeholder="Add any additional notes about this call..."></textarea>
                    </div>
                    <button class="btn btn-login" onclick="submitWrapUp()">
                        <i class="fas fa-check"></i> Submit
                    </button>
                    <button class="btn btn-logout" onclick="hideWrapUpForm()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </div>
        </div>

        <!-- Extension Directory -->
        <div class="card">
            <h3><i class="fas fa-address-book"></i> Extension Directory</h3>
            <div style="margin-bottom: 1rem;">
                <input type="text" id="directory-search" placeholder="Search by name or extension..."
                       style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 6px;"
                       oninput="filterDirectory()">
            </div>
            <div class="directory" id="directory">
                <div style="text-align: center; padding: 2rem; color: #666;">
                    Loading directory...
                </div>
            </div>
        </div>

        <!-- Activity Log -->
        <div class="card">
            <h3><i class="fas fa-history"></i> Recent Activity</h3>
            <div class="activity-log" id="activity-log">
                <div style="text-align: center; padding: 2rem; color: #666;">
                    No activity yet
                </div>
            </div>
        </div>
    </div>

    <script>
        const extension = '<?= addslashes($extension) ?>';
        let agentStatus = null;
        let wrapUpCodes = {};
        let directory = [];
        let activityLog = [];
        let statusInterval = null;

        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            loadQueues();
            loadWrapUpCodes();
            loadDirectory();
            updateStatus();
            loadStats();

            // Auto-refresh status every 5 seconds
            statusInterval = setInterval(updateStatus, 5000);
        });

        // Load available queues
        async function loadQueues() {
            try {
                const response = await fetch('/api/callcenter-agent.php?action=queues');
                const data = await response.json();

                if (data.success) {
                    const select = document.getElementById('queue-select');
                    select.innerHTML = data.queues.map(q =>
                        `<option value="${q.name}">${q.name} (${q.calls_waiting} waiting)</option>`
                    ).join('');
                }
            } catch (error) {
                console.error('Failed to load queues:', error);
            }
        }

        // Update agent status
        async function updateStatus() {
            try {
                const response = await fetch('/api/callcenter-agent.php?action=status');
                const data = await response.json();

                agentStatus = data;
                updateUI(data);
            } catch (error) {
                console.error('Failed to update status:', error);
            }
        }

        // Update UI based on status
        function updateUI(status) {
            const statusDot = document.getElementById('status-dot');
            const statusText = document.getElementById('status-text');
            const queueText = document.getElementById('queue-text');

            if (status.in_call) {
                statusDot.className = 'status-dot status-in-call';
                statusText.textContent = 'In Call';
            } else if (status.paused) {
                statusDot.className = 'status-dot status-paused';
                statusText.textContent = 'Paused';
            } else if (status.available) {
                statusDot.className = 'status-dot status-available';
                statusText.textContent = 'Available';
            } else if (status.logged_in) {
                statusDot.className = 'status-dot status-offline';
                statusText.textContent = 'Logged In (Unavailable)';
            } else {
                statusDot.className = 'status-dot status-offline';
                statusText.textContent = 'Offline';
            }

            if (status.queues.length > 0) {
                queueText.textContent = status.queues.map(q => q.name).join(', ');
                showLoggedInControls();
            } else {
                queueText.textContent = 'Not in queue';
                showLoggedOutControls();
            }
        }

        // Show/hide controls
        function showLoggedInControls() {
            document.querySelector('.btn-login').style.display = 'none';
            document.querySelector('.btn-logout').style.display = 'block';

            if (agentStatus && agentStatus.paused) {
                document.querySelector('.btn-pause').style.display = 'none';
                document.querySelector('.btn-unpause').style.display = 'block';
            } else {
                document.querySelector('.btn-pause').style.display = 'block';
                document.querySelector('.btn-unpause').style.display = 'none';
            }
        }

        function showLoggedOutControls() {
            document.querySelector('.btn-login').style.display = 'block';
            document.querySelector('.btn-logout').style.display = 'none';
            document.querySelector('.btn-pause').style.display = 'none';
            document.querySelector('.btn-unpause').style.display = 'none';
        }

        // Queue actions
        async function loginToQueue() {
            const queue = document.getElementById('queue-select').value;

            try {
                const response = await fetch('/api/callcenter-agent.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=login&queue=${queue}`
                });

                const data = await response.json();

                if (data.success) {
                    addActivity('Logged in to queue: ' + queue);
                    updateStatus();
                } else {
                    alert('Failed to login: ' + data.message);
                }
            } catch (error) {
                console.error('Login failed:', error);
                alert('Login failed');
            }
        }

        async function logoutFromQueue() {
            const queue = document.getElementById('queue-select').value;

            if (!confirm('Are you sure you want to logout from the queue?')) {
                return;
            }

            try {
                const response = await fetch('/api/callcenter-agent.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=logout&queue=${queue}`
                });

                const data = await response.json();

                if (data.success) {
                    addActivity('Logged out from queue: ' + queue);
                    updateStatus();
                } else {
                    alert('Failed to logout: ' + data.message);
                }
            } catch (error) {
                console.error('Logout failed:', error);
                alert('Logout failed');
            }
        }

        async function togglePause() {
            const queue = document.getElementById('queue-select').value;
            const isPaused = agentStatus && agentStatus.paused;

            if (!isPaused) {
                // Show pause reasons
                document.getElementById('pause-reasons').classList.add('active');
                return;
            }

            const action = isPaused ? 'unpause' : 'pause';
            const reason = document.getElementById('pause-reason').value;

            try {
                const response = await fetch('/api/callcenter-agent.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=${action}&queue=${queue}&reason=${reason}`
                });

                const data = await response.json();

                if (data.success) {
                    addActivity(isPaused ? 'Unpaused' : 'Paused: ' + reason);
                    document.getElementById('pause-reasons').classList.remove('active');
                    updateStatus();
                } else {
                    alert('Failed to ' + action);
                }
            } catch (error) {
                console.error('Pause toggle failed:', error);
            }
        }

        // Load wrap-up codes
        async function loadWrapUpCodes() {
            try {
                const response = await fetch('/api/callcenter-agent.php?action=wrapup_codes');
                const data = await response.json();

                if (data.success) {
                    wrapUpCodes = data.codes;
                    const select = document.getElementById('wrap-code');
                    select.innerHTML = '<option value="">Select wrap-up code...</option>' +
                        Object.entries(data.codes).map(([code, label]) =>
                            `<option value="${code}">${label}</option>`
                        ).join('');
                }
            } catch (error) {
                console.error('Failed to load wrap-up codes:', error);
            }
        }

        // Wrap-up functions
        function showWrapUpForm() {
            document.getElementById('wrap-up-form').classList.add('active');
        }

        function hideWrapUpForm() {
            document.getElementById('wrap-up-form').classList.remove('active');
            document.getElementById('wrap-code').value = '';
            document.getElementById('wrap-notes').value = '';
        }

        async function submitWrapUp() {
            const wrapCode = document.getElementById('wrap-code').value;
            const notes = document.getElementById('wrap-notes').value;

            if (!wrapCode) {
                alert('Please select a wrap-up code');
                return;
            }

            try {
                const response = await fetch('/api/callcenter-agent.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=submit_wrapup&wrap_code=${wrapCode}&notes=${encodeURIComponent(notes)}`
                });

                const data = await response.json();

                if (data.success) {
                    addActivity('Wrap-up submitted: ' + data.wrap_label);
                    hideWrapUpForm();
                    loadStats(); // Refresh stats
                } else {
                    alert('Failed to submit wrap-up');
                }
            } catch (error) {
                console.error('Wrap-up submit failed:', error);
                alert('Failed to submit wrap-up');
            }
        }

        // Load statistics
        async function loadStats() {
            try {
                const response = await fetch('/api/callcenter-agent.php?action=stats');
                const data = await response.json();

                if (data.success) {
                    const stats = data.stats;
                    document.getElementById('calls-answered').textContent = stats.calls_answered;
                    document.getElementById('avg-talk-time').textContent = stats.avg_talk_time_formatted;
                    document.getElementById('login-time').textContent = stats.login_time_formatted;
                    document.getElementById('available-time').textContent = stats.available_time_formatted;
                }
            } catch (error) {
                console.error('Failed to load stats:', error);
            }
        }

        // Load directory
        async function loadDirectory() {
            try {
                const response = await fetch('/api/directory.php?action=list');
                const data = await response.json();

                if (data.success) {
                    directory = data.extensions;
                    renderDirectory(directory);
                }
            } catch (error) {
                console.error('Failed to load directory:', error);
                document.getElementById('directory').innerHTML = '<div style="text-align: center; padding: 2rem; color: #999;">Failed to load directory</div>';
            }
        }

        // Render directory
        function renderDirectory(extensions) {
            const directoryDiv = document.getElementById('directory');

            if (extensions.length === 0) {
                directoryDiv.innerHTML = '<div style="text-align: center; padding: 2rem; color: #999;">No extensions found</div>';
                return;
            }

            directoryDiv.innerHTML = extensions.map(ext => `
                <div class="directory-item">
                    <div class="directory-info">
                        <div class="directory-name">${ext.name || 'Extension ' + ext.extension}</div>
                        <div class="directory-ext">Ext: ${ext.extension}${ext.email ? ' • ' + ext.email : ''}</div>
                    </div>
                    <div class="directory-actions">
                        <button class="icon-btn icon-btn-call" onclick="callExtension('${ext.extension}')" title="Call">
                            <i class="fas fa-phone"></i>
                        </button>
                        <a href="sms-messaging.php?to=${ext.extension}" class="icon-btn icon-btn-message" title="Message">
                            <i class="fas fa-comment"></i>
                        </a>
                        <button class="icon-btn icon-btn-transfer" onclick="transferTo('${ext.extension}')" title="Transfer">
                            <i class="fas fa-exchange-alt"></i>
                        </button>
                    </div>
                </div>
            `).join('');
        }

        // Filter directory
        function filterDirectory() {
            const search = document.getElementById('directory-search').value.toLowerCase();
            const filtered = directory.filter(ext =>
                (ext.name && ext.name.toLowerCase().includes(search)) ||
                ext.extension.includes(search) ||
                (ext.email && ext.email.toLowerCase().includes(search))
            );
            renderDirectory(filtered);
        }

        // Directory actions
        function callExtension(ext) {
            // Open Flexphone with pre-dialed number
            window.open(`/flexphone/?dial=${ext}`, '_blank');
        }

        function transferTo(ext) {
            // TODO: Implement attended/blind transfer
            alert(`Transfer to ${ext} - Feature coming soon!`);
        }

        // Activity log
        function addActivity(text) {
            const logDiv = document.getElementById('activity-log');
            const time = new Date().toLocaleTimeString();

            const item = document.createElement('div');
            item.className = 'activity-item';
            item.innerHTML = `
                <div class="activity-time">${time}</div>
                <div class="activity-text">${text}</div>
            `;

            logDiv.insertBefore(item, logDiv.firstChild);

            // Keep only last 10 items
            while (logDiv.children.length > 10) {
                logDiv.removeChild(logDiv.lastChild);
            }
        }

        // Cleanup on page unload
        window.addEventListener('beforeunload', () => {
            if (statusInterval) {
                clearInterval(statusInterval);
            }
        });
    </script>

    <!-- Accessibility Tools -->
    <script src="/js/accessibility.js"></script>
</body>
</html>
