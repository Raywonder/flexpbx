<?php
require_once __DIR__ . '/admin_auth_check.php';
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$allowed_roles = ['superadmin', 'admin'];
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
    <title>Queue Management - FlexPBX</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #0f172a; color: #e2e8f0; min-height: 100vh; }
        .top-bar { background: #1e293b; border-bottom: 1px solid #334155; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; }
        .logo { font-size: 1.5rem; font-weight: 700; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .nav { display: flex; gap: 1rem; }
        .nav a { color: #94a3b8; text-decoration: none; padding: 0.5rem 1rem; border-radius: 8px; transition: all 0.3s; }
        .nav a:hover, .nav a.active { background: rgba(102, 126, 234, 0.2); color: #fff; }
        .container { max-width: 1600px; margin: 0 auto; padding: 2rem; }
        .card { background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 1.5rem; margin-bottom: 2rem; }
        .btn { padding: 0.75rem 1.5rem; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.3s; }
        .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .btn-secondary { background: #334155; color: #e2e8f0; }
        .btn-sm { padding: 0.5rem 1rem; font-size: 0.875rem; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; }
        .form-group { margin-bottom: 1rem; }
        label { display: block; margin-bottom: 0.5rem; color: #94a3b8; font-size: 0.875rem; }
        input, select, textarea { width: 100%; background: #0f172a; border: 1px solid #334155; color: #e2e8f0; padding: 0.75rem; border-radius: 8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th { text-align: left; padding: 0.75rem; color: #94a3b8; font-size: 0.875rem; text-transform: uppercase; border-bottom: 1px solid #334155; }
        td { padding: 0.75rem; border-bottom: 1px solid #334155; }
        tr:hover { background: rgba(255, 255, 255, 0.05); }
        .badge { display: inline-block; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; }
        .badge-success { background: rgba(34, 197, 94, 0.2); color: #22c55e; }
        .badge-info { background: rgba(102, 126, 234, 0.2); color: #667eea; }
        h2 { margin-bottom: 1rem; }
        .tabs { display: flex; gap: 0.5rem; margin-bottom: 1rem; border-bottom: 1px solid #334155; }
        .tab { padding: 0.75rem 1.5rem; cursor: pointer; border-bottom: 2px solid transparent; transition: all 0.3s; }
        .tab.active { border-bottom-color: #667eea; color: #667eea; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .action-buttons { display: flex; gap: 0.5rem; }
    </style>
</head>
<body>
    <div class="top-bar">
        <div class="logo">Queue Management</div>
        <div class="nav">
            <a href="callcenter-dashboard.php">Dashboard</a>
            <a href="queue-management.php" class="active">Queue Management</a>
            <a href="supervisor-dashboard.php">Supervisor Tools</a>
            <a href="dashboard-live.php">Main Dashboard</a>
        </div>
    </div>

    <div class="container">
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h2>Manage Queues</h2>
                <button class="btn btn-primary" onclick="showCreateQueue()">Create New Queue</button>
            </div>

            <div class="tabs">
                <div class="tab active" onclick="switchTab('queues-list')">Queues</div>
                <div class="tab" onclick="switchTab('create-queue')">Create/Edit Queue</div>
                <div class="tab" onclick="switchTab('assign-agents')">Assign Agents</div>
            </div>

            <!-- Queues List -->
            <div class="tab-content active" id="queues-list">
                <table>
                    <thead>
                        <tr>
                            <th>Queue Name</th>
                            <th>Description</th>
                            <th>Strategy</th>
                            <th>Agents</th>
                            <th>Waiting</th>
                            <th>SLA</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="queues-tbody">
                        <tr><td colspan="7" style="text-align: center; padding: 2rem;">Loading...</td></tr>
                    </tbody>
                </table>
            </div>

            <!-- Create/Edit Queue -->
            <div class="tab-content" id="create-queue">
                <form id="queue-form" onsubmit="saveQueue(event)">
                    <input type="hidden" id="queue-id">

                    <div class="form-grid">
                        <div class="form-group">
                            <label>Queue Name *</label>
                            <input type="text" id="queue-name" required pattern="[a-z0-9-]+" placeholder="support-queue">
                        </div>

                        <div class="form-group">
                            <label>Description</label>
                            <input type="text" id="queue-description" placeholder="Customer Support Queue">
                        </div>

                        <div class="form-group">
                            <label>Strategy *</label>
                            <select id="queue-strategy" required>
                                <option value="ringall">Ring All (simultaneously)</option>
                                <option value="rrmemory">Round Robin Memory</option>
                                <option value="leastrecent">Least Recent</option>
                                <option value="fewestcalls">Fewest Calls</option>
                                <option value="random">Random</option>
                                <option value="linear">Linear (sequential)</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Department</label>
                            <input type="text" id="queue-department" placeholder="Sales">
                        </div>

                        <div class="form-group">
                            <label>Timeout (seconds)</label>
                            <input type="number" id="queue-timeout" value="30" min="5" max="300">
                        </div>

                        <div class="form-group">
                            <label>Retry Delay (seconds)</label>
                            <input type="number" id="queue-retry" value="5" min="1" max="60">
                        </div>

                        <div class="form-group">
                            <label>Wrap-up Time (seconds)</label>
                            <input type="number" id="queue-wrapup" value="15" min="0" max="300">
                        </div>

                        <div class="form-group">
                            <label>Max Queue Length</label>
                            <input type="number" id="queue-maxlen" value="0" min="0" placeholder="0 = unlimited">
                        </div>

                        <div class="form-group">
                            <label>Service Level (seconds)</label>
                            <input type="number" id="queue-servicelevel" value="60" min="10">
                        </div>

                        <div class="form-group">
                            <label>SLA Target (seconds)</label>
                            <input type="number" id="queue-sla" value="60" min="10">
                        </div>

                        <div class="form-group">
                            <label>Weight</label>
                            <input type="number" id="queue-weight" value="0" min="0">
                        </div>

                        <div class="form-group">
                            <label>Member Delay</label>
                            <input type="number" id="queue-memberdelay" value="0" min="0">
                        </div>
                    </div>

                    <h3 style="margin: 2rem 0 1rem;">Announcements</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Announce Hold Time</label>
                            <select id="queue-announce-holdtime">
                                <option value="yes">Yes</option>
                                <option value="no">No</option>
                                <option value="once">Once</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Announce Position</label>
                            <select id="queue-announce-position">
                                <option value="yes">Yes</option>
                                <option value="no">No</option>
                                <option value="limit">Limit</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Announce Frequency (seconds)</label>
                            <input type="number" id="queue-announce-freq" value="0" min="0">
                        </div>

                        <div class="form-group">
                            <label>Periodic Announce</label>
                            <input type="text" id="queue-periodic-announce" placeholder="custom-announce">
                        </div>
                    </div>

                    <h3 style="margin: 2rem 0 1rem;">Behavior</h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Join Empty Queue</label>
                            <select id="queue-joinempty">
                                <option value="yes">Yes</option>
                                <option value="no">No</option>
                                <option value="strict">Strict</option>
                                <option value="loose">Loose</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Leave When Empty</label>
                            <select id="queue-leavewhenempty">
                                <option value="no">No</option>
                                <option value="yes">Yes</option>
                                <option value="strict">Strict</option>
                                <option value="loose">Loose</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Ring In Use</label>
                            <select id="queue-ringinuse">
                                <option value="yes">Yes</option>
                                <option value="no">No</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Timeout Restart</label>
                            <select id="queue-timeoutrestart">
                                <option value="no">No</option>
                                <option value="yes">Yes</option>
                            </select>
                        </div>
                    </div>

                    <div style="margin-top: 2rem;">
                        <button type="submit" class="btn btn-primary">Save Queue</button>
                        <button type="button" class="btn btn-secondary" onclick="switchTab('queues-list')">Cancel</button>
                    </div>
                </form>
            </div>

            <!-- Assign Agents -->
            <div class="tab-content" id="assign-agents">
                <div class="form-grid" style="margin-bottom: 2rem;">
                    <div class="form-group">
                        <label>Select Queue</label>
                        <select id="assign-queue" onchange="loadQueueMembers()">
                            <option value="">-- Select Queue --</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Add Agent</label>
                        <select id="assign-extension">
                            <option value="">-- Select Agent --</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Penalty</label>
                        <input type="number" id="assign-penalty" value="0" min="0">
                    </div>

                    <div class="form-group" style="display: flex; align-items: flex-end;">
                        <button class="btn btn-primary" onclick="addAgentToQueue()">Add Agent</button>
                    </div>
                </div>

                <h3>Current Members</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Agent</th>
                            <th>Extension</th>
                            <th>Penalty</th>
                            <th>Calls Taken</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="members-tbody">
                        <tr><td colspan="6" style="text-align: center;">Select a queue to view members</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        const API_BASE = '/api/callcenter.php';

        function switchTab(tabId) {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));

            event.target.classList.add('active');
            document.getElementById(tabId).classList.add('active');

            if (tabId === 'assign-agents') {
                loadQueuesForAssignment();
                loadExtensionsForAssignment();
            }
        }

        async function loadQueues() {
            try {
                const response = await fetch(`${API_BASE}?path=queues/statistics`);
                const data = await response.json();

                if (data.success) {
                    renderQueues(data.statistics);
                }
            } catch (error) {
                console.error('Error:', error);
            }
        }

        function renderQueues(queues) {
            const tbody = document.getElementById('queues-tbody');

            if (!queues || queues.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align: center;">No queues configured</td></tr>';
                return;
            }

            tbody.innerHTML = queues.map(q => `
                <tr>
                    <td>${q.queue_name}</td>
                    <td>${q.description || '-'}</td>
                    <td><span class="badge badge-info">${q.strategy}</span></td>
                    <td>${q.agents_total}</td>
                    <td>${q.calls_waiting}</td>
                    <td>${Math.round(q.sla_compliance)}%</td>
                    <td class="action-buttons">
                        <button class="btn btn-secondary btn-sm" onclick="editQueue('${q.queue_name}')">Edit</button>
                    </td>
                </tr>
            `).join('');
        }

        function showCreateQueue() {
            document.getElementById('queue-form').reset();
            document.getElementById('queue-id').value = '';
            switchTab('create-queue');
        }

        async function saveQueue(event) {
            event.preventDefault();

            const queueData = {
                queue_name: document.getElementById('queue-name').value,
                description: document.getElementById('queue-description').value,
                strategy: document.getElementById('queue-strategy').value,
                department: document.getElementById('queue-department').value,
                timeout: document.getElementById('queue-timeout').value,
                retry: document.getElementById('queue-retry').value,
                wrapuptime: document.getElementById('queue-wrapup').value,
                maxlen: document.getElementById('queue-maxlen').value,
                servicelevel: document.getElementById('queue-servicelevel').value,
                sla_seconds: document.getElementById('queue-sla').value,
                weight: document.getElementById('queue-weight').value,
                memberdelay: document.getElementById('queue-memberdelay').value,
                announce_holdtime: document.getElementById('queue-announce-holdtime').value,
                announce_position: document.getElementById('queue-announce-position').value,
                announce_frequency: document.getElementById('queue-announce-freq').value,
                periodic_announce: document.getElementById('queue-periodic-announce').value,
                joinempty: document.getElementById('queue-joinempty').value,
                leavewhenempty: document.getElementById('queue-leavewhenempty').value,
                ringinuse: document.getElementById('queue-ringinuse').value,
                timeoutrestart: document.getElementById('queue-timeoutrestart').value
            };

            try {
                const response = await fetch(`${API_BASE}?path=queues/create`, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(queueData)
                });

                const data = await response.json();

                if (data.success) {
                    alert('Queue saved successfully! You need to reload Asterisk for changes to take effect.');
                    switchTab('queues-list');
                    loadQueues();
                } else {
                    alert('Error: ' + data.error);
                }
            } catch (error) {
                alert('Failed to save queue');
            }
        }

        async function loadQueuesForAssignment() {
            try {
                const response = await fetch(`${API_BASE}?path=queues/list`);
                const data = await response.json();

                if (data.success) {
                    const select = document.getElementById('assign-queue');
                    select.innerHTML = '<option value="">-- Select Queue --</option>' +
                        data.queues.map(q => `<option value="${q.name}">${q.name}</option>`).join('');
                }
            } catch (error) {
                console.error('Error:', error);
            }
        }

        async function loadExtensionsForAssignment() {
            // This would load from extensions API
            const select = document.getElementById('assign-extension');
            select.innerHTML = '<option value="">-- Select Agent --</option>';
        }

        async function loadQueueMembers() {
            const queue = document.getElementById('assign-queue').value;
            if (!queue) return;

            try {
                const response = await fetch(`${API_BASE}?path=queues/members&queue=${queue}`);
                const data = await response.json();

                if (data.success) {
                    renderMembers(data.members);
                }
            } catch (error) {
                console.error('Error:', error);
            }
        }

        function renderMembers(members) {
            const tbody = document.getElementById('members-tbody');

            if (!members || members.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align: center;">No members in this queue</td></tr>';
                return;
            }

            tbody.innerHTML = members.map(m => `
                <tr>
                    <td>${m.full_name || m.username || '-'}</td>
                    <td>${m.extension || '-'}</td>
                    <td>${m.penalty}</td>
                    <td>${m.calls_taken}</td>
                    <td><span class="badge ${m.paused ? 'badge-warning' : 'badge-success'}">${m.status_text}</span></td>
                    <td>
                        <button class="btn btn-secondary btn-sm" onclick="removeAgentFromQueue('${m.location}')">Remove</button>
                    </td>
                </tr>
            `).join('');
        }

        async function addAgentToQueue() {
            const queue = document.getElementById('assign-queue').value;
            const extension = document.getElementById('assign-extension').value;
            const penalty = document.getElementById('assign-penalty').value;

            if (!queue || !extension) {
                alert('Please select queue and agent');
                return;
            }

            try {
                const response = await fetch(`${API_BASE}?path=queues/add-member`, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({queue, extension, penalty})
                });

                const data = await response.json();

                if (data.success) {
                    alert('Agent added to queue');
                    loadQueueMembers();
                } else {
                    alert('Error: ' + data.error);
                }
            } catch (error) {
                alert('Failed to add agent');
            }
        }

        async function removeAgentFromQueue(interface) {
            const queue = document.getElementById('assign-queue').value;
            const extension = interface.split('/')[1];

            if (!confirm('Remove this agent from the queue?')) return;

            try {
                const response = await fetch(`${API_BASE}?path=queues/remove-member`, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({queue, extension})
                });

                const data = await response.json();

                if (data.success) {
                    alert('Agent removed from queue');
                    loadQueueMembers();
                } else {
                    alert('Error: ' + data.error);
                }
            } catch (error) {
                alert('Failed to remove agent');
            }
        }

        // Initial load
        loadQueues();
    </script>
</body>
</html>
