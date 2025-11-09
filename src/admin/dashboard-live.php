<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FlexPBX Live Dashboard</title>
    <?php
    session_start();

    // Check authentication
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header('Location: login.php');
        exit;
    }

    // Check role (admin, superadmin, or manager)
    $allowed_roles = ['superadmin', 'admin', 'manager'];
    $user_role = $_SESSION['admin_role'] ?? 'guest';

    if (!in_array($user_role, $allowed_roles)) {
        header('Location: login.php?error=insufficient_permissions');
        exit;
    }
    ?>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            min-height: 100vh;
        }

        .top-bar {
            background: #1e293b;
            border-bottom: 1px solid #334155;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .status-indicators {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .status-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 0.5rem;
        }

        .status-dot.online {
            background: #22c55e;
            box-shadow: 0 0 10px #22c55e;
            animation: pulse 2s infinite;
        }

        .status-dot.offline {
            background: #ef4444;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .card {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 1.5rem;
            transition: all 0.3s ease;
        }

        .card:hover {
            border-color: #667eea;
            transform: translateY(-2px);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .card-title {
            font-size: 0.875rem;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .card-value {
            font-size: 2rem;
            font-weight: 700;
            color: #fff;
        }

        .card-subtitle {
            font-size: 0.875rem;
            color: #64748b;
        }

        .metric-positive {
            color: #22c55e;
        }

        .metric-negative {
            color: #ef4444;
        }

        .table-container {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 1.5rem;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            padding: 0.75rem;
            color: #94a3b8;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 1px solid #334155;
        }

        td {
            padding: 0.75rem;
            border-bottom: 1px solid #334155;
        }

        tr:last-child td {
            border-bottom: none;
        }

        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-success {
            background: rgba(34, 197, 94, 0.2);
            color: #22c55e;
        }

        .badge-error {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }

        .badge-warning {
            background: rgba(251, 191, 36, 0.2);
            color: #fbbf24;
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: #334155;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 0.5rem;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea, #764ba2);
            transition: width 0.3s ease;
        }

        .refresh-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: transform 0.2s;
        }

        .refresh-btn:hover {
            transform: scale(1.05);
        }

        .loading {
            text-align: center;
            padding: 2rem;
            color: #64748b;
        }

        .error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid #ef4444;
            color: #ef4444;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="top-bar">
        <div class="logo">FlexPBX Live Dashboard</div>
        <div class="status-indicators">
            <div>
                <span class="status-dot online" id="asterisk-status"></span>
                <span id="asterisk-text">Asterisk</span>
            </div>
            <button class="refresh-btn" onclick="refreshDashboard()">Refresh</button>
        </div>
    </div>

    <div class="container">
        <div id="error-container"></div>

        <!-- Key Metrics -->
        <div class="grid">
            <div class="card">
                <div class="card-header">
                    <div class="card-title">Active Calls</div>
                </div>
                <div class="card-value" id="active-calls">--</div>
                <div class="card-subtitle">Current active calls</div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="card-title">Total Extensions</div>
                </div>
                <div class="card-value" id="total-extensions">--</div>
                <div class="card-subtitle">Registered extensions</div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="card-title">Today's Calls</div>
                </div>
                <div class="card-value" id="today-calls">--</div>
                <div class="card-subtitle" id="answer-rate">Answer rate: --</div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="card-title">System Health</div>
                </div>
                <div class="card-value" id="system-health">--</div>
                <div class="card-subtitle">Overall status</div>
            </div>
        </div>

        <!-- Resource Usage -->
        <div class="grid">
            <div class="card">
                <div class="card-header">
                    <div class="card-title">CPU Usage</div>
                    <div class="card-subtitle" id="cpu-percent">--</div>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" id="cpu-progress" style="width: 0%"></div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="card-title">Memory Usage</div>
                    <div class="card-subtitle" id="memory-percent">--</div>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" id="memory-progress" style="width: 0%"></div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="card-title">Disk Usage</div>
                    <div class="card-subtitle" id="disk-percent">--</div>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" id="disk-progress" style="width: 0%"></div>
                </div>
            </div>
        </div>

        <!-- Recent Calls -->
        <div class="table-container">
            <h2 style="margin-bottom: 1rem;">Recent Calls</h2>
            <table id="recent-calls-table">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>From</th>
                        <th>To</th>
                        <th>Duration</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="recent-calls-body">
                    <tr><td colspan="5" class="loading">Loading...</td></tr>
                </tbody>
            </table>
        </div>

        <!-- Extensions Status -->
        <div class="table-container" style="margin-top: 1.5rem;">
            <h2 style="margin-bottom: 1rem;">Extensions</h2>
            <table id="extensions-table">
                <thead>
                    <tr>
                        <th>Extension</th>
                        <th>Name</th>
                        <th>Status</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody id="extensions-body">
                    <tr><td colspan="4" class="loading">Loading...</td></tr>
                </tbody>
            </table>
        </div>

        <!-- API Keys -->
        <div class="table-container" style="margin-top: 1.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h2>Active API Keys</h2>
                <button class="refresh-btn" onclick="loadAPIKeys()">Refresh Keys</button>
            </div>
            <table id="api-keys-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Key Source</th>
                        <th>Client ID</th>
                        <th>Created</th>
                        <th>Last Used</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="api-keys-body">
                    <tr><td colspan="6" class="loading">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        const API_BASE = '/api';

        async function fetchAPI(endpoint) {
            try {
                const response = await fetch(`${API_BASE}/${endpoint}`);
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                return await response.json();
            } catch (error) {
                console.error(`API Error (${endpoint}):`, error);
                return null;
            }
        }

        async function loadSystemHealth() {
            const data = await fetchAPI('system.php?path=health');
            if (!data || !data.success) return;

            const health = data.health;
            document.getElementById('system-health').textContent = health.overall_status;
            document.getElementById('active-calls').textContent = health.statistics?.active_calls || 0;

            // Update Asterisk status
            const asteriskStatus = health.services?.asterisk?.healthy;
            const statusDot = document.getElementById('asterisk-status');
            const statusText = document.getElementById('asterisk-text');

            if (asteriskStatus) {
                statusDot.className = 'status-dot online';
                statusText.textContent = 'Asterisk Online';
            } else {
                statusDot.className = 'status-dot offline';
                statusText.textContent = 'Asterisk Offline';
            }
        }

        async function loadSystemResources() {
            const data = await fetchAPI('system.php?path=resources');
            if (!data || !data.success) return;

            const res = data.resources;

            // CPU
            if (res.cpu) {
                document.getElementById('cpu-percent').textContent = `${res.cpu.usage_percent}%`;
                document.getElementById('cpu-progress').style.width = `${res.cpu.usage_percent}%`;
            }

            // Memory
            if (res.memory) {
                document.getElementById('memory-percent').textContent = `${res.memory.usage_percent}%`;
                document.getElementById('memory-progress').style.width = `${res.memory.usage_percent}%`;
            }

            // Disk
            if (res.disk) {
                document.getElementById('disk-percent').textContent = `${res.disk.usage_percent}%`;
                document.getElementById('disk-progress').style.width = `${res.disk.usage_percent}%`;
            }
        }

        async function loadExtensions() {
            const data = await fetchAPI('extensions.php?path=list');
            if (!data || !data.success) {
                document.getElementById('extensions-body').innerHTML =
                    '<tr><td colspan="4" class="error">Failed to load extensions</td></tr>';
                return;
            }

            document.getElementById('total-extensions').textContent = data.extensions?.length || 0;

            const tbody = document.getElementById('extensions-body');
            if (!data.extensions || data.extensions.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4">No extensions found</td></tr>';
                return;
            }

            tbody.innerHTML = data.extensions.map(ext => `
                <tr>
                    <td>${ext.extension}</td>
                    <td>${ext.name || 'N/A'}</td>
                    <td>
                        <span class="badge ${ext.registered ? 'badge-success' : 'badge-error'}">
                            ${ext.registered ? 'Registered' : 'Unregistered'}
                        </span>
                    </td>
                    <td>${ext.contact || 'N/A'}</td>
                </tr>
            `).join('');
        }

        async function loadRecentCalls() {
            const data = await fetchAPI('call-logs.php?path=recent&limit=10');
            if (!data || !data.success) {
                document.getElementById('recent-calls-body').innerHTML =
                    '<tr><td colspan="5" class="error">Failed to load calls</td></tr>';
                return;
            }

            const tbody = document.getElementById('recent-calls-body');
            if (!data.recent_calls || data.recent_calls.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5">No recent calls</td></tr>';
                return;
            }

            tbody.innerHTML = data.recent_calls.map(call => `
                <tr>
                    <td>${new Date(call.calldate).toLocaleTimeString()}</td>
                    <td>${call.src}</td>
                    <td>${call.dst}</td>
                    <td>${call.duration_formatted || call.duration + 's'}</td>
                    <td>
                        <span class="badge ${call.disposition === 'ANSWERED' ? 'badge-success' : 'badge-warning'}">
                            ${call.disposition}
                        </span>
                    </td>
                </tr>
            `).join('');
        }

        async function loadCallStatistics() {
            const data = await fetchAPI('call-logs.php?path=statistics&period=today');
            if (!data || !data.success) return;

            const stats = data.statistics;
            document.getElementById('today-calls').textContent = stats.total_calls || 0;
            document.getElementById('answer-rate').textContent =
                `Answer rate: ${stats.answer_rate || 0}%`;
        }

        async function loadAPIKeys() {
            // Mock data for now - will be replaced with actual API endpoint
            const mockKeys = [
                {
                    username: 'admin',
                    source: 'FlexPBX Built-in',
                    client_id: 'desktop-app-001',
                    created_at: new Date().toISOString(),
                    last_used: new Date().toISOString(),
                    is_active: true
                },
                {
                    username: 'user1',
                    source: 'HubNode Gateway',
                    client_id: 'mobile-app-002',
                    created_at: new Date(Date.now() - 86400000).toISOString(),
                    last_used: new Date(Date.now() - 3600000).toISOString(),
                    is_active: true
                },
                {
                    username: 'user2',
                    source: 'FlexPBX Built-in',
                    client_id: 'web-client-003',
                    created_at: new Date(Date.now() - 172800000).toISOString(),
                    last_used: new Date(Date.now() - 7200000).toISOString(),
                    is_active: true
                }
            ];

            const tbody = document.getElementById('api-keys-body');

            if (mockKeys.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6">No active API keys</td></tr>';
                return;
            }

            tbody.innerHTML = mockKeys.map(key => {
                const created = new Date(key.created_at);
                const lastUsed = new Date(key.last_used);
                const timeSinceUse = Date.now() - lastUsed.getTime();
                const hoursAgo = Math.floor(timeSinceUse / 3600000);

                const sourceColor = key.source.includes('HubNode') ? 'badge-warning' : 'badge-success';

                return `
                    <tr>
                        <td>${key.username}</td>
                        <td><span class="badge ${sourceColor}">${key.source}</span></td>
                        <td>${key.client_id}</td>
                        <td>${created.toLocaleDateString()} ${created.toLocaleTimeString()}</td>
                        <td>${hoursAgo < 1 ? 'Just now' : hoursAgo + 'h ago'}</td>
                        <td>
                            <span class="badge ${key.is_active ? 'badge-success' : 'badge-error'}">
                                ${key.is_active ? 'Active' : 'Revoked'}
                            </span>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        async function refreshDashboard() {
            document.getElementById('error-container').innerHTML = '';

            try {
                await Promise.all([
                    loadSystemHealth(),
                    loadSystemResources(),
                    loadExtensions(),
                    loadRecentCalls(),
                    loadCallStatistics(),
                    loadAPIKeys()
                ]);
            } catch (error) {
                document.getElementById('error-container').innerHTML =
                    `<div class="error">Error loading dashboard: ${error.message}</div>`;
            }
        }

        // Initial load
        refreshDashboard();

        // Auto-refresh every 30 seconds
        setInterval(refreshDashboard, 30000);
    </script>
</body>
</html>
