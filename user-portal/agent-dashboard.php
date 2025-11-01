<?php
session_start();

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$extension = $_SESSION['extension'] ?? null;
$username = $_SESSION['username'] ?? 'Agent';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agent Dashboard - FlexPBX</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
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

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        .grid {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        @media (max-width: 768px) {
            .grid {
                grid-template-columns: 1fr;
            }
        }

        .card {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 1.5rem;
        }

        .status-control {
            text-align: center;
        }

        .status-indicator {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            margin: 0 auto 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            transition: all 0.3s;
        }

        .status-available {
            background: linear-gradient(135deg, #22c55e, #16a34a);
            box-shadow: 0 0 30px rgba(34, 197, 94, 0.5);
        }

        .status-break {
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            box-shadow: 0 0 30px rgba(251, 191, 36, 0.5);
        }

        .status-offline {
            background: linear-gradient(135deg, #64748b, #475569);
        }

        .status-buttons {
            display: grid;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .btn {
            padding: 0.75rem 1rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
        }

        .btn-success {
            background: #22c55e;
            color: white;
        }

        .btn-warning {
            background: #fbbf24;
            color: #1e293b;
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-secondary {
            background: #334155;
            color: #e2e8f0;
        }

        .btn:hover {
            transform: scale(1.02);
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .timer {
            font-size: 2rem;
            font-weight: 700;
            text-align: center;
            margin: 1rem 0;
            color: #fff;
        }

        .queue-list {
            margin-top: 1rem;
        }

        .queue-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            margin-bottom: 0.5rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #667eea;
        }

        .stat-label {
            font-size: 0.875rem;
            color: #94a3b8;
            margin-top: 0.5rem;
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

        .badge-warning {
            background: rgba(251, 191, 36, 0.2);
            color: #fbbf24;
        }

        h2, h3 {
            margin-bottom: 1rem;
        }

        .disposition-form {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 1.5rem;
            display: none;
        }

        .disposition-form.active {
            display: block;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #94a3b8;
        }

        select, textarea, input {
            width: 100%;
            background: #0f172a;
            border: 1px solid #334155;
            color: #e2e8f0;
            padding: 0.75rem;
            border-radius: 8px;
        }

        textarea {
            min-height: 100px;
            resize: vertical;
        }

        .current-call-info {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            display: none;
        }

        .current-call-info.active {
            display: block;
        }

        .call-info-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="top-bar">
        <div class="logo">Agent Dashboard</div>
        <div class="user-info">
            <span><?php echo htmlspecialchars($username); ?> (Ext: <?php echo htmlspecialchars($extension); ?>)</span>
            <a href="index.php" class="btn btn-secondary" style="width: auto;">Portal Home</a>
        </div>
    </div>

    <div class="container">
        <div class="grid">
            <!-- Status Control Panel -->
            <div>
                <div class="card status-control">
                    <h3>Your Status</h3>
                    <div class="status-indicator" id="status-indicator">
                        <span id="status-icon">⏸</span>
                    </div>
                    <h2 id="status-text">Offline</h2>
                    <div class="timer" id="status-timer">00:00:00</div>

                    <div class="status-buttons">
                        <button class="btn btn-success" onclick="setStatus('available')">
                            Ready
                        </button>
                        <button class="btn btn-warning" onclick="setStatus('break')">
                            Break
                        </button>
                        <button class="btn btn-warning" onclick="setStatus('lunch')">
                            Lunch
                        </button>
                        <button class="btn btn-warning" onclick="setStatus('meeting')">
                            Meeting
                        </button>
                        <button class="btn btn-danger" onclick="setStatus('offline')">
                            Go Offline
                        </button>
                    </div>
                </div>

                <div class="card" style="margin-top: 1rem;">
                    <h3>My Queues</h3>
                    <div class="queue-list" id="queue-list">
                        <p>Loading queues...</p>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div>
                <!-- Current Call Info -->
                <div class="current-call-info" id="current-call-info">
                    <h3>Current Call</h3>
                    <div class="call-info-item">
                        <span>Caller:</span>
                        <strong id="call-caller">-</strong>
                    </div>
                    <div class="call-info-item">
                        <span>Duration:</span>
                        <strong id="call-duration">00:00</strong>
                    </div>
                    <div class="call-info-item">
                        <span>Queue:</span>
                        <strong id="call-queue">-</strong>
                    </div>
                </div>

                <!-- Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value" id="calls-today">0</div>
                        <div class="stat-label">Calls Today</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value" id="avg-talk-time">0:00</div>
                        <div class="stat-label">Avg Talk Time</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value" id="total-talk-time">0:00</div>
                        <div class="stat-label">Total Talk Time</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value" id="answered-calls">0</div>
                        <div class="stat-label">Answered</div>
                    </div>
                </div>

                <!-- Call Disposition Form -->
                <div class="disposition-form" id="disposition-form">
                    <h3>Call Disposition</h3>
                    <form id="disposition-form-element" onsubmit="submitDisposition(event)">
                        <div class="form-group">
                            <label>Disposition Code</label>
                            <select id="disposition-code" required>
                                <option value="">Select...</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Notes</label>
                            <textarea id="disposition-notes" placeholder="Call notes..."></textarea>
                        </div>
                        <div class="form-group">
                            <label>
                                <input type="checkbox" id="follow-up"> Requires Follow-up
                            </label>
                        </div>
                        <div class="form-group" id="follow-up-date-group" style="display: none;">
                            <label>Follow-up Date</label>
                            <input type="date" id="follow-up-date">
                        </div>
                        <button type="submit" class="btn btn-success">Submit Disposition</button>
                    </form>
                </div>

                <!-- Recent Calls -->
                <div class="card">
                    <h3>Recent Calls</h3>
                    <div id="recent-calls">
                        <p>No recent calls</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const API_BASE = '/api/callcenter.php';
        const EXTENSION = '<?php echo $extension; ?>';

        let currentStatus = 'offline';
        let statusStartTime = null;
        let timerInterval = null;

        // Format time
        function formatTime(seconds) {
            const h = Math.floor(seconds / 3600);
            const m = Math.floor((seconds % 3600) / 60);
            const s = seconds % 60;
            return `${h.toString().padStart(2, '0')}:${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
        }

        // Update timer
        function updateTimer() {
            if (!statusStartTime) return;

            const elapsed = Math.floor((Date.now() - statusStartTime) / 1000);
            document.getElementById('status-timer').textContent = formatTime(elapsed);
        }

        // Set agent status
        async function setStatus(status) {
            try {
                const response = await fetch(`${API_BASE}?path=agent/set-status`, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        extension: EXTENSION,
                        status: status
                    })
                });

                const data = await response.json();

                if (data.success) {
                    currentStatus = status;
                    statusStartTime = Date.now();
                    updateStatusDisplay(status);

                    if (status === 'available') {
                        // Show disposition form when available
                        document.getElementById('disposition-form').classList.add('active');
                    }
                } else {
                    alert('Error: ' + data.error);
                }
            } catch (error) {
                console.error('Error setting status:', error);
                alert('Failed to update status');
            }
        }

        // Update status display
        function updateStatusDisplay(status) {
            const indicator = document.getElementById('status-indicator');
            const text = document.getElementById('status-text');
            const icon = document.getElementById('status-icon');

            indicator.className = 'status-indicator';

            switch(status) {
                case 'available':
                case 'ready':
                    indicator.classList.add('status-available');
                    text.textContent = 'Available';
                    icon.textContent = '✓';
                    break;
                case 'break':
                case 'lunch':
                case 'meeting':
                    indicator.classList.add('status-break');
                    text.textContent = status.charAt(0).toUpperCase() + status.slice(1);
                    icon.textContent = '⏸';
                    break;
                default:
                    indicator.classList.add('status-offline');
                    text.textContent = 'Offline';
                    icon.textContent = '○';
            }
        }

        // Load agent status
        async function loadAgentStatus() {
            try {
                const response = await fetch(`${API_BASE}?path=agent/status&extension=${EXTENSION}`);
                const data = await response.json();

                if (data.success) {
                    currentStatus = data.agent_status.status;
                    updateStatusDisplay(currentStatus);

                    // Render queues
                    const queues = data.agent_status.queues || [];
                    const queueList = document.getElementById('queue-list');

                    if (queues.length === 0) {
                        queueList.innerHTML = '<p>Not assigned to any queues</p>';
                    } else {
                        queueList.innerHTML = queues.map(q => `
                            <div class="queue-item">
                                <span>${q.queue_name}</span>
                                <span class="badge ${q.paused ? 'badge-warning' : 'badge-success'}">
                                    ${q.paused ? 'Paused' : 'Active'}
                                </span>
                            </div>
                        `).join('');
                    }
                }
            } catch (error) {
                console.error('Error loading status:', error);
            }
        }

        // Load statistics
        async function loadStatistics() {
            try {
                const response = await fetch(`${API_BASE}?path=agent/statistics&extension=${EXTENSION}&period=today`);
                const data = await response.json();

                if (data.success) {
                    const stats = data.statistics;

                    document.getElementById('calls-today').textContent = stats.total_calls;
                    document.getElementById('answered-calls').textContent = stats.answered_calls;
                    document.getElementById('avg-talk-time').textContent = formatTime(Math.round(stats.avg_talk_time));
                    document.getElementById('total-talk-time').textContent = formatTime(stats.total_talk_time);
                }
            } catch (error) {
                console.error('Error loading statistics:', error);
            }
        }

        // Load disposition codes
        async function loadDispositionCodes() {
            try {
                const response = await fetch(`${API_BASE}?path=disposition/codes`);
                const data = await response.json();

                if (data.success) {
                    const select = document.getElementById('disposition-code');
                    select.innerHTML = '<option value="">Select...</option>' +
                        data.disposition_codes.map(code =>
                            `<option value="${code.code}">${code.name}</option>`
                        ).join('');
                }
            } catch (error) {
                console.error('Error loading disposition codes:', error);
            }
        }

        // Submit disposition
        async function submitDisposition(event) {
            event.preventDefault();

            const code = document.getElementById('disposition-code').value;
            const notes = document.getElementById('disposition-notes').value;
            const followUp = document.getElementById('follow-up').checked;
            const followUpDate = document.getElementById('follow-up-date').value;

            try {
                const response = await fetch(`${API_BASE}?path=disposition/submit`, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        extension: EXTENSION,
                        disposition_code: code,
                        notes: notes,
                        follow_up: followUp,
                        follow_up_date: followUpDate
                    })
                });

                const data = await response.json();

                if (data.success) {
                    alert('Disposition saved');
                    document.getElementById('disposition-form-element').reset();
                    document.getElementById('disposition-form').classList.remove('active');
                } else {
                    alert('Error: ' + data.error);
                }
            } catch (error) {
                console.error('Error submitting disposition:', error);
                alert('Failed to save disposition');
            }
        }

        // Toggle follow-up date
        document.getElementById('follow-up').addEventListener('change', function() {
            document.getElementById('follow-up-date-group').style.display =
                this.checked ? 'block' : 'none';
        });

        // Initial load
        loadAgentStatus();
        loadStatistics();
        loadDispositionCodes();

        // Start timer
        timerInterval = setInterval(updateTimer, 1000);

        // Auto-refresh
        setInterval(() => {
            loadAgentStatus();
            loadStatistics();
        }, 30000);
    </script>
</body>
</html>
