<?php
require_once __DIR__ . '/admin_auth_check.php';
session_start();

// Check authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Check role
$allowed_roles = ['superadmin', 'admin', 'supervisor'];
$user_role = $_SESSION['admin_role'] ?? 'guest';

if (!in_array($user_role, $allowed_roles)) {
    header('Location: login.php?error=insufficient_permissions');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Call Center Dashboard - FlexPBX</title>
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
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .nav {
            display: flex;
            gap: 1rem;
        }

        .nav a {
            color: #94a3b8;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            transition: all 0.3s;
        }

        .nav a:hover, .nav a.active {
            background: rgba(102, 126, 234, 0.2);
            color: #fff;
        }

        .container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 2rem;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
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
            font-size: 2.5rem;
            font-weight: 700;
            color: #fff;
        }

        .card-subtitle {
            font-size: 0.875rem;
            color: #64748b;
            margin-top: 0.5rem;
        }

        .metric-good {
            color: #22c55e;
        }

        .metric-warning {
            color: #fbbf24;
        }

        .metric-bad {
            color: #ef4444;
        }

        .queue-grid {
            display: grid;
            gap: 1rem;
        }

        .queue-card {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 1.5rem;
        }

        .queue-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .queue-name {
            font-size: 1.25rem;
            font-weight: 600;
        }

        .queue-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .stat-item {
            text-align: center;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
        }

        .stat-label {
            font-size: 0.75rem;
            color: #94a3b8;
            margin-top: 0.25rem;
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

        .badge-info {
            background: rgba(102, 126, 234, 0.2);
            color: #667eea;
        }

        .table-container {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 1.5rem;
            overflow-x: auto;
            margin-bottom: 2rem;
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

        tr:hover {
            background: rgba(255, 255, 255, 0.05);
        }

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: scale(1.05);
        }

        .btn-secondary {
            background: #334155;
            color: #e2e8f0;
        }

        .btn-secondary:hover {
            background: #475569;
        }

        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }

        .refresh-indicator {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: #94a3b8;
            font-size: 0.875rem;
        }

        .spinner {
            width: 16px;
            height: 16px;
            border: 2px solid #334155;
            border-top-color: #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 0.5rem;
        }

        .status-available {
            background: #22c55e;
            box-shadow: 0 0 8px #22c55e;
        }

        .status-on-call {
            background: #3b82f6;
            box-shadow: 0 0 8px #3b82f6;
        }

        .status-paused {
            background: #fbbf24;
            box-shadow: 0 0 8px #fbbf24;
        }

        .status-offline {
            background: #64748b;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .alert-warning {
            background: rgba(251, 191, 36, 0.1);
            border: 1px solid #fbbf24;
            color: #fbbf24;
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid #ef4444;
            color: #ef4444;
        }

        .loading {
            text-align: center;
            padding: 3rem;
            color: #64748b;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        h2 {
            margin-bottom: 1rem;
            font-size: 1.5rem;
        }

        .filter-bar {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }

        select, input {
            background: #0f172a;
            border: 1px solid #334155;
            color: #e2e8f0;
            padding: 0.5rem 1rem;
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <div class="top-bar">
        <div class="logo">Call Center Dashboard</div>
        <div class="nav">
            <a href="callcenter-dashboard.php" class="active">Dashboard</a>
            <a href="queue-management.php">Queue Management</a>
            <a href="supervisor-dashboard.php">Supervisor Tools</a>
            <a href="dashboard-live.php">Main Dashboard</a>
        </div>
    </div>

    <div class="container">
        <div id="alerts-container"></div>

        <!-- Key Metrics -->
        <div class="grid">
            <div class="card">
                <div class="card-header">
                    <div class="card-title">Calls Waiting</div>
                    <span id="refresh-indicator" class="refresh-indicator" style="display: none;">
                        <div class="spinner"></div>
                    </span>
                </div>
                <div class="card-value" id="total-waiting">--</div>
                <div class="card-subtitle">Across all queues</div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="card-title">Available Agents</div>
                </div>
                <div class="card-value metric-good" id="available-agents">--</div>
                <div class="card-subtitle" id="agent-breakdown">-- on call / -- paused</div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="card-title">Avg Wait Time</div>
                </div>
                <div class="card-value" id="avg-wait-time">--</div>
                <div class="card-subtitle">Average hold time</div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="card-title">SLA Compliance</div>
                </div>
                <div class="card-value" id="sla-compliance">--</div>
                <div class="card-subtitle">Service level performance</div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="card-title">Abandonment Rate</div>
                </div>
                <div class="card-value" id="abandon-rate">--</div>
                <div class="card-subtitle">Calls abandoned today</div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="card-title">Active Calls</div>
                </div>
                <div class="card-value metric-good" id="active-calls">--</div>
                <div class="card-subtitle">Calls in progress</div>
            </div>
        </div>

        <!-- Queue Statistics -->
        <div class="table-container">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h2>Queue Performance</h2>
                <button class="btn btn-primary" onclick="refreshData()">Refresh</button>
            </div>
            <div id="queues-container" class="loading">Loading queues...</div>
        </div>

        <!-- Agent Status -->
        <div class="table-container">
            <h2>Agent Status</h2>
            <div class="filter-bar">
                <select id="status-filter" onchange="filterAgents()">
                    <option value="all">All Status</option>
                    <option value="available">Available</option>
                    <option value="on-call">On Call</option>
                    <option value="paused">Paused</option>
                    <option value="offline">Offline</option>
                </select>
                <select id="queue-filter" onchange="filterAgents()">
                    <option value="all">All Queues</option>
                </select>
            </div>
            <table id="agents-table">
                <thead>
                    <tr>
                        <th>Agent</th>
                        <th>Extension</th>
                        <th>Status</th>
                        <th>Queues</th>
                        <th>Calls Taken</th>
                        <th>Current Call</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="agents-body">
                    <tr><td colspan="7" class="loading">Loading agents...</td></tr>
                </tbody>
            </table>
        </div>

        <!-- Active Calls -->
        <div class="table-container">
            <h2>Active Calls</h2>
            <table id="calls-table">
                <thead>
                    <tr>
                        <th>Channel</th>
                        <th>From</th>
                        <th>To</th>
                        <th>Duration</th>
                        <th>Context</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="calls-body">
                    <tr><td colspan="6" class="loading">Loading calls...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        const API_BASE = '/api/callcenter.php';
        let allAgents = [];
        let allQueues = [];

        // Format time in seconds to readable format
        function formatTime(seconds) {
            if (!seconds || seconds === 0) return '0s';
            const mins = Math.floor(seconds / 60);
            const secs = seconds % 60;
            if (mins === 0) return `${secs}s`;
            return `${mins}m ${secs}s`;
        }

        // Format percentage
        function formatPercent(value) {
            return `${Math.round(value)}%`;
        }

        // Get metric class based on value
        function getMetricClass(value, thresholds) {
            if (value >= thresholds.good) return 'metric-good';
            if (value >= thresholds.warning) return 'metric-warning';
            return 'metric-bad';
        }

        // Load queue statistics
        async function loadQueues() {
            try {
                const response = await fetch(`${API_BASE}?path=queues/statistics`);
                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.error || 'Failed to load queues');
                }

                allQueues = data.statistics;
                renderQueues(data.statistics);
                updateMetrics(data.statistics);
                populateQueueFilter(data.statistics);
            } catch (error) {
                console.error('Error loading queues:', error);
                document.getElementById('queues-container').innerHTML =
                    `<div class="alert alert-danger">Error: ${error.message}</div>`;
            }
        }

        // Render queues
        function renderQueues(queues) {
            const container = document.getElementById('queues-container');

            if (!queues || queues.length === 0) {
                container.innerHTML = '<p>No queues configured</p>';
                return;
            }

            container.innerHTML = queues.map(queue => {
                const slaClass = getMetricClass(queue.sla_compliance, {good: 90, warning: 75});
                const abandonClass = getMetricClass(100 - queue.abandon_rate, {good: 95, warning: 90});

                return `
                    <div class="queue-card">
                        <div class="queue-header">
                            <div class="queue-name">${queue.queue_name}</div>
                            <div>
                                <span class="badge badge-info">${queue.strategy}</span>
                                ${queue.calls_waiting > 0 ? `<span class="badge badge-warning">${queue.calls_waiting} waiting</span>` : ''}
                            </div>
                        </div>
                        <div class="queue-stats">
                            <div class="stat-item">
                                <div class="stat-value">${queue.calls_waiting}</div>
                                <div class="stat-label">Waiting</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value">${formatTime(queue.avg_hold_time)}</div>
                                <div class="stat-label">Avg Wait</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value">${formatTime(queue.avg_talk_time)}</div>
                                <div class="stat-label">Avg Talk</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value ${slaClass}">${formatPercent(queue.sla_compliance)}</div>
                                <div class="stat-label">SLA</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value ${abandonClass}">${formatPercent(queue.abandon_rate)}</div>
                                <div class="stat-label">Abandon</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value metric-good">${queue.agents_available}</div>
                                <div class="stat-label">Available</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value">${queue.agents_on_call}</div>
                                <div class="stat-label">On Call</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value">${queue.agents_paused}</div>
                                <div class="stat-label">Paused</div>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        }

        // Update top metrics
        function updateMetrics(queues) {
            let totalWaiting = 0;
            let totalAvailable = 0;
            let totalOnCall = 0;
            let totalPaused = 0;
            let totalHoldTime = 0;
            let totalCompleted = 0;
            let totalAbandoned = 0;
            let totalSLA = 0;

            queues.forEach(queue => {
                totalWaiting += queue.calls_waiting;
                totalAvailable += queue.agents_available;
                totalOnCall += queue.agents_on_call;
                totalPaused += queue.agents_paused;
                totalHoldTime += queue.avg_hold_time;
                totalCompleted += queue.calls_completed;
                totalAbandoned += queue.calls_abandoned;
                totalSLA += queue.sla_compliance;
            });

            const avgHoldTime = queues.length > 0 ? totalHoldTime / queues.length : 0;
            const avgSLA = queues.length > 0 ? totalSLA / queues.length : 0;
            const totalCalls = totalCompleted + totalAbandoned;
            const abandonRate = totalCalls > 0 ? (totalAbandoned / totalCalls) * 100 : 0;

            document.getElementById('total-waiting').textContent = totalWaiting;
            document.getElementById('available-agents').textContent = totalAvailable;
            document.getElementById('agent-breakdown').textContent = `${totalOnCall} on call / ${totalPaused} paused`;
            document.getElementById('avg-wait-time').textContent = formatTime(Math.round(avgHoldTime));

            const slaElement = document.getElementById('sla-compliance');
            slaElement.textContent = formatPercent(avgSLA);
            slaElement.className = 'card-value ' + getMetricClass(avgSLA, {good: 90, warning: 75});

            const abandonElement = document.getElementById('abandon-rate');
            abandonElement.textContent = formatPercent(abandonRate);
            abandonElement.className = 'card-value ' + getMetricClass(100 - abandonRate, {good: 95, warning: 90});

            // Check for alerts
            checkAlerts(queues, totalWaiting, avgHoldTime);
        }

        // Check for alerts
        function checkAlerts(queues, totalWaiting, avgHoldTime) {
            const alertsContainer = document.getElementById('alerts-container');
            const alerts = [];

            if (totalWaiting > 10) {
                alerts.push({
                    type: 'warning',
                    message: `High call volume: ${totalWaiting} calls waiting across all queues`
                });
            }

            if (avgHoldTime > 120) {
                alerts.push({
                    type: 'danger',
                    message: `Long wait times: Average hold time is ${formatTime(Math.round(avgHoldTime))}`
                });
            }

            queues.forEach(queue => {
                if (queue.agents_available === 0 && queue.calls_waiting > 0) {
                    alerts.push({
                        type: 'danger',
                        message: `No available agents in ${queue.queue_name} (${queue.calls_waiting} waiting)`
                    });
                }
            });

            if (alerts.length > 0) {
                alertsContainer.innerHTML = alerts.map(alert =>
                    `<div class="alert alert-${alert.type}">${alert.message}</div>`
                ).join('');
            } else {
                alertsContainer.innerHTML = '';
            }
        }

        // Load agents
        async function loadAgents() {
            try {
                const response = await fetch(`${API_BASE}?path=supervisor/agents`);
                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.error || 'Failed to load agents');
                }

                allAgents = data.agents;
                renderAgents(data.agents);
            } catch (error) {
                console.error('Error loading agents:', error);
                document.getElementById('agents-body').innerHTML =
                    `<tr><td colspan="7" class="alert alert-danger">Error: ${error.message}</td></tr>`;
            }
        }

        // Render agents
        function renderAgents(agents) {
            const tbody = document.getElementById('agents-body');

            if (!agents || agents.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7">No agents found</td></tr>';
                return;
            }

            tbody.innerHTML = agents.map(agent => {
                let statusClass = 'status-offline';
                let statusText = 'Offline';

                if (agent.on_call) {
                    statusClass = 'status-on-call';
                    statusText = 'On Call';
                } else if (agent.status === 'available') {
                    statusClass = 'status-available';
                    statusText = 'Available';
                } else if (agent.status === 'break' || agent.status === 'lunch') {
                    statusClass = 'status-paused';
                    statusText = agent.status.charAt(0).toUpperCase() + agent.status.slice(1);
                }

                const currentCall = agent.on_call && agent.current_call ?
                    `${agent.current_call.caller_id} (${formatTime(agent.current_call.duration)})` : '-';

                return `
                    <tr>
                        <td>${agent.full_name || agent.username}</td>
                        <td>${agent.extension}</td>
                        <td>
                            <span class="status-dot ${statusClass}"></span>
                            ${statusText}
                        </td>
                        <td>${agent.queues || 'None'}</td>
                        <td>-</td>
                        <td>${currentCall}</td>
                        <td class="action-buttons">
                            ${agent.on_call ? `
                                <button class="btn btn-secondary btn-sm" onclick="listenToCall('${agent.current_call?.channel}')">Listen</button>
                            ` : ''}
                        </td>
                    </tr>
                `;
            }).join('');
        }

        // Load active calls
        async function loadActiveCalls() {
            try {
                const response = await fetch(`${API_BASE}?path=calls/active`);
                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.error || 'Failed to load calls');
                }

                document.getElementById('active-calls').textContent = data.count;
                renderActiveCalls(data.active_calls);
            } catch (error) {
                console.error('Error loading calls:', error);
            }
        }

        // Render active calls
        function renderActiveCalls(calls) {
            const tbody = document.getElementById('calls-body');

            if (!calls || calls.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6">No active calls</td></tr>';
                return;
            }

            tbody.innerHTML = calls.map(call => `
                <tr>
                    <td>${call.channel}</td>
                    <td>${call.caller_id} (${call.caller_name})</td>
                    <td>${call.extension}</td>
                    <td>${formatTime(call.duration)}</td>
                    <td>${call.context}</td>
                    <td class="action-buttons">
                        <button class="btn btn-secondary btn-sm" onclick="listenToCall('${call.channel}')">Listen</button>
                        <?php if ($user_role === 'superadmin' || $user_role === 'admin'): ?>
                        <button class="btn btn-secondary btn-sm" onclick="hangupCall('${call.channel}')">Hangup</button>
                        <?php endif; ?>
                    </td>
                </tr>
            `).join('');
        }

        // Populate queue filter
        function populateQueueFilter(queues) {
            const filter = document.getElementById('queue-filter');
            const currentValue = filter.value;

            filter.innerHTML = '<option value="all">All Queues</option>' +
                queues.map(q => `<option value="${q.queue_name}">${q.queue_name}</option>`).join('');

            filter.value = currentValue;
        }

        // Filter agents
        function filterAgents() {
            const statusFilter = document.getElementById('status-filter').value;
            const queueFilter = document.getElementById('queue-filter').value;

            let filtered = allAgents;

            if (statusFilter !== 'all') {
                filtered = filtered.filter(agent => {
                    if (statusFilter === 'on-call') return agent.on_call;
                    return agent.status === statusFilter;
                });
            }

            if (queueFilter !== 'all') {
                filtered = filtered.filter(agent =>
                    agent.queues && agent.queues.includes(queueFilter)
                );
            }

            renderAgents(filtered);
        }

        // Listen to call
        function listenToCall(channel) {
            if (confirm('Start listening to this call?')) {
                fetch(`${API_BASE}?path=supervisor/listen`, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({target_channel: channel})
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        alert('Listen mode activated. Answer your phone.');
                    } else {
                        alert('Error: ' + data.error);
                    }
                });
            }
        }

        // Hangup call
        function hangupCall(channel) {
            if (confirm('Terminate this call?')) {
                fetch(`${API_BASE}?path=calls/hangup`, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({channel: channel})
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        alert('Call terminated');
                        refreshData();
                    } else {
                        alert('Error: ' + data.error);
                    }
                });
            }
        }

        // Refresh all data
        async function refreshData() {
            document.getElementById('refresh-indicator').style.display = 'flex';

            await Promise.all([
                loadQueues(),
                loadAgents(),
                loadActiveCalls()
            ]);

            document.getElementById('refresh-indicator').style.display = 'none';
        }

        // Initial load
        refreshData();

        // Auto-refresh every 15 seconds
        setInterval(refreshData, 15000);
    </script>
</body>
</html>
