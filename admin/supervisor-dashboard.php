<?php
require_once __DIR__ . '/admin_auth_check.php';
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

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
    <title>Supervisor Dashboard - FlexPBX</title>
    <link rel="stylesheet" href="callcenter-dashboard.php" type="text/css">
    <style>
        /* Reuse styles from callcenter-dashboard.php */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #0f172a; color: #e2e8f0; min-height: 100vh; }
        .top-bar { background: #1e293b; border-bottom: 1px solid #334155; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 100; }
        .logo { font-size: 1.5rem; font-weight: 700; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .nav { display: flex; gap: 1rem; }
        .nav a { color: #94a3b8; text-decoration: none; padding: 0.5rem 1rem; border-radius: 8px; transition: all 0.3s; }
        .nav a:hover, .nav a.active { background: rgba(102, 126, 234, 0.2); color: #fff; }
        .container { max-width: 1600px; margin: 0 auto; padding: 2rem; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .card { background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 1.5rem; }
        .table-container { background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 1.5rem; overflow-x: auto; margin-bottom: 2rem; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 0.75rem; color: #94a3b8; font-size: 0.875rem; text-transform: uppercase; border-bottom: 1px solid #334155; }
        td { padding: 0.75rem; border-bottom: 1px solid #334155; }
        tr:hover { background: rgba(255, 255, 255, 0.05); }
        .btn { padding: 0.5rem 1rem; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s; }
        .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .btn-secondary { background: #334155; color: #e2e8f0; }
        .btn-sm { padding: 0.25rem 0.5rem; font-size: 0.75rem; }
        .badge { display: inline-block; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
        .badge-success { background: rgba(34, 197, 94, 0.2); color: #22c55e; }
        .badge-warning { background: rgba(251, 191, 36, 0.2); color: #fbbf24; }
        .badge-info { background: rgba(102, 126, 234, 0.2); color: #667eea; }
        .status-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; margin-right: 0.5rem; }
        .status-available { background: #22c55e; box-shadow: 0 0 8px #22c55e; }
        .status-on-call { background: #3b82f6; box-shadow: 0 0 8px #3b82f6; }
        .status-paused { background: #fbbf24; box-shadow: 0 0 8px #fbbf24; }
        .status-offline { background: #64748b; }
        h2 { margin-bottom: 1rem; font-size: 1.5rem; }
        .action-buttons { display: flex; gap: 0.5rem; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.8); z-index: 1000; align-items: center; justify-content: center; }
        .modal.active { display: flex; }
        .modal-content { background: #1e293b; border-radius: 12px; padding: 2rem; max-width: 500px; width: 90%; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
        .close { cursor: pointer; font-size: 1.5rem; }
        .form-group { margin-bottom: 1rem; }
        label { display: block; margin-bottom: 0.5rem; color: #94a3b8; }
        select, input { width: 100%; background: #0f172a; border: 1px solid #334155; color: #e2e8f0; padding: 0.75rem; border-radius: 8px; }
    </style>
</head>
<body>
    <div class="top-bar">
        <div class="logo">Supervisor Dashboard</div>
        <div class="nav">
            <a href="callcenter-dashboard.php">Dashboard</a>
            <a href="queue-management.php">Queue Management</a>
            <a href="supervisor-dashboard.php" class="active">Supervisor Tools</a>
            <a href="dashboard-live.php">Main Dashboard</a>
        </div>
    </div>

    <div class="container">
        <!-- Team Overview -->
        <div class="grid">
            <div class="card">
                <h3>Total Agents</h3>
                <div style="font-size: 2rem; font-weight: 700; color: #fff;" id="total-agents">--</div>
            </div>
            <div class="card">
                <h3>Available</h3>
                <div style="font-size: 2rem; font-weight: 700; color: #22c55e;" id="available-agents">--</div>
            </div>
            <div class="card">
                <h3>On Call</h3>
                <div style="font-size: 2rem; font-weight: 700; color: #3b82f6;" id="on-call-agents">--</div>
            </div>
            <div class="card">
                <h3>Paused</h3>
                <div style="font-size: 2rem; font-weight: 700; color: #fbbf24;" id="paused-agents">--</div>
            </div>
        </div>

        <!-- Agent Table -->
        <div class="table-container">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h2>Team Monitoring</h2>
                <button class="btn btn-primary" onclick="refreshAgents()">Refresh</button>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Agent</th>
                        <th>Extension</th>
                        <th>Status</th>
                        <th>Current Activity</th>
                        <th>Duration</th>
                        <th>Queues</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="agents-tbody">
                    <tr><td colspan="7" style="text-align: center; padding: 2rem; color: #64748b;">Loading...</td></tr>
                </tbody>
            </table>
        </div>

        <!-- Active Calls Monitoring -->
        <div class="table-container">
            <h2>Active Calls Monitoring</h2>
            <table>
                <thead>
                    <tr>
                        <th>Agent</th>
                        <th>Caller</th>
                        <th>Duration</th>
                        <th>Queue</th>
                        <th>Channel</th>
                        <th>Monitor Actions</th>
                    </tr>
                </thead>
                <tbody id="calls-tbody">
                    <tr><td colspan="6" style="text-align: center; padding: 2rem; color: #64748b;">No active calls</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Force Status Modal -->
    <div class="modal" id="status-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Change Agent Status</h3>
                <span class="close" onclick="closeModal('status-modal')">&times;</span>
            </div>
            <form id="status-form" onsubmit="forceStatusChange(event)">
                <input type="hidden" id="target-extension">
                <div class="form-group">
                    <label>Agent: <span id="target-agent-name"></span></label>
                </div>
                <div class="form-group">
                    <label>New Status</label>
                    <select id="new-status" required>
                        <option value="available">Available</option>
                        <option value="break">Break</option>
                        <option value="lunch">Lunch</option>
                        <option value="meeting">Meeting</option>
                        <option value="offline">Offline</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Reason</label>
                    <input type="text" id="status-reason" placeholder="Reason for change...">
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Update Status</button>
            </form>
        </div>
    </div>

    <script>
        const API_BASE = '/api/callcenter.php';
        let allAgents = [];

        function formatTime(seconds) {
            const m = Math.floor(seconds / 60);
            const s = seconds % 60;
            return `${m}:${s.toString().padStart(2, '0')}`;
        }

        async function refreshAgents() {
            try {
                const response = await fetch(`${API_BASE}?path=supervisor/agents`);
                const data = await response.json();

                if (data.success) {
                    allAgents = data.agents;
                    renderAgents(data.agents);
                    updateCounts(data.agents);
                    updateActiveCalls(data.agents);
                }
            } catch (error) {
                console.error('Error:', error);
            }
        }

        function renderAgents(agents) {
            const tbody = document.getElementById('agents-tbody');

            if (!agents || agents.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align: center;">No agents found</td></tr>';
                return;
            }

            tbody.innerHTML = agents.map(agent => {
                let statusClass = 'status-offline';
                let statusText = 'Offline';
                let activity = '-';

                if (agent.on_call) {
                    statusClass = 'status-on-call';
                    statusText = 'On Call';
                    activity = agent.current_call ? `Call: ${agent.current_call.caller_id}` : 'On Call';
                } else if (agent.status === 'available') {
                    statusClass = 'status-available';
                    statusText = 'Available';
                    activity = 'Ready';
                } else if (agent.status) {
                    statusClass = 'status-paused';
                    statusText = agent.status.charAt(0).toUpperCase() + agent.status.slice(1);
                    activity = agent.reason || statusText;
                }

                const duration = agent.current_call ? formatTime(agent.current_call.duration) : '-';

                return `
                    <tr>
                        <td>${agent.full_name || agent.username}</td>
                        <td>${agent.extension}</td>
                        <td>
                            <span class="status-dot ${statusClass}"></span>
                            ${statusText}
                        </td>
                        <td>${activity}</td>
                        <td>${duration}</td>
                        <td>${agent.queues || '-'}</td>
                        <td class="action-buttons">
                            ${agent.on_call ? `
                                <button class="btn btn-secondary btn-sm" onclick="listenToCall('${agent.current_call.channel}')">Listen</button>
                                <button class="btn btn-secondary btn-sm" onclick="whisperToCall('${agent.current_call.channel}')">Whisper</button>
                                <button class="btn btn-secondary btn-sm" onclick="bargeIntoCall('${agent.current_call.channel}')">Barge</button>
                            ` : ''}
                            <button class="btn btn-secondary btn-sm" onclick="openStatusModal('${agent.extension}', '${agent.full_name}')">Force Status</button>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        function updateCounts(agents) {
            let total = agents.length;
            let available = 0;
            let onCall = 0;
            let paused = 0;

            agents.forEach(agent => {
                if (agent.on_call) {
                    onCall++;
                } else if (agent.status === 'available') {
                    available++;
                } else if (agent.status && agent.status !== 'offline') {
                    paused++;
                }
            });

            document.getElementById('total-agents').textContent = total;
            document.getElementById('available-agents').textContent = available;
            document.getElementById('on-call-agents').textContent = onCall;
            document.getElementById('paused-agents').textContent = paused;
        }

        function updateActiveCalls(agents) {
            const tbody = document.getElementById('calls-tbody');
            const activeCalls = agents.filter(a => a.on_call && a.current_call);

            if (activeCalls.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align: center;">No active calls</td></tr>';
                return;
            }

            tbody.innerHTML = activeCalls.map(agent => {
                const call = agent.current_call;
                return `
                    <tr>
                        <td>${agent.full_name}</td>
                        <td>${call.caller_id}</td>
                        <td>${formatTime(call.duration)}</td>
                        <td>-</td>
                        <td>${call.channel}</td>
                        <td class="action-buttons">
                            <button class="btn btn-secondary btn-sm" onclick="listenToCall('${call.channel}')">Listen</button>
                            <button class="btn btn-secondary btn-sm" onclick="whisperToCall('${call.channel}')">Whisper</button>
                            <button class="btn btn-secondary btn-sm" onclick="bargeIntoCall('${call.channel}')">Barge</button>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        async function listenToCall(channel) {
            if (!confirm('Start listening to this call?')) return;

            try {
                const response = await fetch(`${API_BASE}?path=supervisor/listen`, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({target_channel: channel})
                });

                const data = await response.json();
                if (data.success) {
                    alert('Listen mode activated. Answer your phone.');
                } else {
                    alert('Error: ' + data.error);
                }
            } catch (error) {
                alert('Failed to activate listen mode');
            }
        }

        async function whisperToCall(channel) {
            if (!confirm('Start whisper mode? Only the agent will hear you.')) return;

            try {
                const response = await fetch(`${API_BASE}?path=supervisor/whisper`, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({target_channel: channel})
                });

                const data = await response.json();
                if (data.success) {
                    alert('Whisper mode activated. Answer your phone.');
                } else {
                    alert('Error: ' + data.error);
                }
            } catch (error) {
                alert('Failed to activate whisper mode');
            }
        }

        async function bargeIntoCall(channel) {
            if (!confirm('Barge into this call? Both parties will hear you.')) return;

            try {
                const response = await fetch(`${API_BASE}?path=supervisor/barge`, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({target_channel: channel})
                });

                const data = await response.json();
                if (data.success) {
                    alert('Barge mode activated. Answer your phone.');
                } else {
                    alert('Error: ' + data.error);
                }
            } catch (error) {
                alert('Failed to activate barge mode');
            }
        }

        function openStatusModal(extension, name) {
            document.getElementById('target-extension').value = extension;
            document.getElementById('target-agent-name').textContent = name;
            document.getElementById('status-modal').classList.add('active');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        async function forceStatusChange(event) {
            event.preventDefault();

            const extension = document.getElementById('target-extension').value;
            const status = document.getElementById('new-status').value;
            const reason = document.getElementById('status-reason').value;

            try {
                const response = await fetch(`${API_BASE}?path=supervisor/force-status`, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        extension: extension,
                        status: status,
                        reason: reason || 'Forced by supervisor'
                    })
                });

                const data = await response.json();
                if (data.success) {
                    alert('Agent status updated');
                    closeModal('status-modal');
                    refreshAgents();
                } else {
                    alert('Error: ' + data.error);
                }
            } catch (error) {
                alert('Failed to update status');
            }
        }

        // Initial load
        refreshAgents();

        // Auto-refresh every 15 seconds
        setInterval(refreshAgents, 15000);
    </script>
</body>
</html>
