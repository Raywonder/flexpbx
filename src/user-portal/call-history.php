<?php
/**
 * FlexPBX User Portal - Call History
 * View complete call history with details
 */

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: /user-portal/login.php');
    exit;
}

$extension = $_SESSION['user_extension'] ?? 'Unknown';
$username = $_SESSION['user_username'] ?? $extension;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Call History - FlexPBX User Portal</title>
    <link rel="stylesheet" href="/css/style.css">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f5f7fa;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
        }

        .header h1 {
            margin: 0 0 0.5rem 0;
            font-size: 2rem;
        }

        .header p {
            margin: 0;
            opacity: 0.9;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .stat-card h3 {
            margin: 0 0 0.5rem 0;
            font-size: 0.9rem;
            color: #666;
            font-weight: 600;
            text-transform: uppercase;
        }

        .stat-card .value {
            font-size: 2rem;
            font-weight: 700;
            color: #667eea;
        }

        .stat-card .label {
            font-size: 0.85rem;
            color: #999;
            margin-top: 0.25rem;
        }

        .filter-bar {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .filter-bar select, .filter-bar input {
            padding: 0.6rem 1rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.95rem;
        }

        .filter-bar button {
            padding: 0.6rem 1.5rem;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }

        .filter-bar button:hover {
            background: #5568d3;
        }

        .calls-table {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f8f9fa;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-bottom: 2px solid #e0e0e0;
        }

        td {
            padding: 1rem;
            border-bottom: 1px solid #f0f0f0;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .direction-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .direction-inbound {
            background: #e3f2fd;
            color: #1976d2;
        }

        .direction-outbound {
            background: #f3e5f5;
            color: #7b1fa2;
        }

        .direction-internal {
            background: #fff3e0;
            color: #f57c00;
        }

        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-answered {
            background: #c8e6c9;
            color: #2e7d32;
        }

        .status-no-answer {
            background: #ffccbc;
            color: #d84315;
        }

        .status-busy {
            background: #ffe0b2;
            color: #e65100;
        }

        .status-failed {
            background: #ffcdd2;
            color: #c62828;
        }

        .loading {
            text-align: center;
            padding: 3rem;
            color: #666;
        }

        .no-data {
            text-align: center;
            padding: 3rem;
            color: #999;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            padding: 1.5rem;
        }

        .pagination button {
            padding: 0.5rem 1rem;
            background: white;
            border: 1px solid #ddd;
            border-radius: 6px;
            cursor: pointer;
        }

        .pagination button:hover {
            background: #f0f0f0;
        }

        .pagination button.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        .pagination button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .nav-buttons {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn:hover {
            background: #667eea;
            color: white;
        }

        .duration {
            font-family: 'Courier New', monospace;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📞 Call History</h1>
            <p>Extension <?= htmlspecialchars($extension) ?> - <?= htmlspecialchars($username) ?></p>
        </div>

        <div class="nav-buttons">
            <a href="/user-portal/" class="btn">← Back to Dashboard</a>
            <a href="/user-portal/my-recordings.php" class="btn">🎙️ Recordings</a>
        </div>

        <!-- Statistics -->
        <div class="stats-grid" id="stats-grid">
            <div class="stat-card">
                <h3>Total Calls</h3>
                <div class="value" id="stat-total">-</div>
                <div class="label">All time</div>
            </div>
            <div class="stat-card">
                <h3>Inbound</h3>
                <div class="value" id="stat-inbound">-</div>
                <div class="label">Received calls</div>
            </div>
            <div class="stat-card">
                <h3>Outbound</h3>
                <div class="value" id="stat-outbound">-</div>
                <div class="label">Made calls</div>
            </div>
            <div class="stat-card">
                <h3>Answered</h3>
                <div class="value" id="stat-answered">-</div>
                <div class="label">Successful</div>
            </div>
            <div class="stat-card">
                <h3>Missed</h3>
                <div class="value" id="stat-missed">-</div>
                <div class="label">No answer</div>
            </div>
            <div class="stat-card">
                <h3>Total Talk Time</h3>
                <div class="value" id="stat-talk-time" style="font-size: 1.5rem;">-</div>
                <div class="label">All conversations</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-bar">
            <select id="filter-direction">
                <option value="all">All Directions</option>
                <option value="inbound">Inbound Only</option>
                <option value="outbound">Outbound Only</option>
                <option value="internal">Internal Only</option>
            </select>
            <select id="filter-status">
                <option value="all">All Status</option>
                <option value="ANSWERED">Answered</option>
                <option value="NO ANSWER">No Answer</option>
                <option value="BUSY">Busy</option>
                <option value="FAILED">Failed</option>
            </select>
            <input type="date" id="filter-date-from" placeholder="From Date">
            <input type="date" id="filter-date-to" placeholder="To Date">
            <button onclick="applyFilters()">Apply Filters</button>
            <button onclick="clearFilters()" style="background: #999;">Clear</button>
        </div>

        <!-- Call History Table -->
        <div class="calls-table">
            <table>
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Direction</th>
                        <th>Number</th>
                        <th>Name</th>
                        <th>Duration</th>
                        <th>Talk Time</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="calls-tbody">
                    <tr>
                        <td colspan="7" class="loading">Loading call history...</td>
                    </tr>
                </tbody>
            </table>

            <!-- Pagination -->
            <div class="pagination" id="pagination">
                <button id="prev-page" onclick="prevPage()" disabled>← Previous</button>
                <span id="page-info">Page 1</span>
                <button id="next-page" onclick="nextPage()">Next →</button>
            </div>
        </div>
    </div>

    <script>
        const extension = '<?= addslashes($extension) ?>';
        let allCalls = [];
        let filteredCalls = [];
        let currentPage = 1;
        const itemsPerPage = 50;

        // Load call history and stats
        async function loadCallHistory() {
            try {
                const response = await fetch(`/api/call-history.php?action=list&extension=${extension}&limit=1000`);
                const data = await response.json();

                if (data.success) {
                    allCalls = data.calls;
                    filteredCalls = [...allCalls];
                    renderCalls();
                    loadStats();
                } else {
                    document.getElementById('calls-tbody').innerHTML = `
                        <tr><td colspan="7" class="no-data">❌ ${data.error}</td></tr>
                    `;
                }
            } catch (error) {
                console.error('Failed to load call history:', error);
                document.getElementById('calls-tbody').innerHTML = `
                    <tr><td colspan="7" class="no-data">❌ Failed to load call history</td></tr>
                `;
            }
        }

        // Load statistics
        async function loadStats() {
            try {
                const response = await fetch(`/api/call-history.php?action=stats&extension=${extension}`);
                const data = await response.json();

                if (data.success) {
                    const stats = data.stats;
                    document.getElementById('stat-total').textContent = stats.total_calls;
                    document.getElementById('stat-inbound').textContent = stats.inbound_calls;
                    document.getElementById('stat-outbound').textContent = stats.outbound_calls;
                    document.getElementById('stat-answered').textContent = stats.answered_calls;
                    document.getElementById('stat-missed').textContent = stats.missed_calls;
                    document.getElementById('stat-talk-time').textContent = stats.total_talk_time_formatted;
                }
            } catch (error) {
                console.error('Failed to load stats:', error);
            }
        }

        // Render calls table
        function renderCalls() {
            const tbody = document.getElementById('calls-tbody');

            if (filteredCalls.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="no-data">No calls found</td></tr>';
                return;
            }

            const start = (currentPage - 1) * itemsPerPage;
            const end = start + itemsPerPage;
            const pageCalls = filteredCalls.slice(start, end);

            tbody.innerHTML = pageCalls.map(call => `
                <tr>
                    <td>${call.date}<br><small style="color: #999;">${call.time}</small></td>
                    <td><span class="direction-badge direction-${call.direction}">${call.direction}</span></td>
                    <td><strong>${call.other_party}</strong></td>
                    <td>${call.caller_name || '-'}</td>
                    <td class="duration">${formatDuration(call.duration)}</td>
                    <td class="duration">${formatDuration(call.talk_time)}</td>
                    <td><span class="status-badge status-${call.disposition.toLowerCase().replace(' ', '-')}">${call.disposition}</span></td>
                </tr>
            `).join('');

            // Update pagination
            const totalPages = Math.ceil(filteredCalls.length / itemsPerPage);
            document.getElementById('page-info').textContent = `Page ${currentPage} of ${totalPages}`;
            document.getElementById('prev-page').disabled = currentPage === 1;
            document.getElementById('next-page').disabled = currentPage === totalPages;
        }

        // Apply filters
        function applyFilters() {
            const direction = document.getElementById('filter-direction').value;
            const status = document.getElementById('filter-status').value;
            const dateFrom = document.getElementById('filter-date-from').value;
            const dateTo = document.getElementById('filter-date-to').value;

            filteredCalls = allCalls.filter(call => {
                if (direction !== 'all' && call.direction !== direction) return false;
                if (status !== 'all' && call.disposition !== status) return false;
                if (dateFrom && call.date < dateFrom) return false;
                if (dateTo && call.date > dateTo) return false;
                return true;
            });

            currentPage = 1;
            renderCalls();
        }

        // Clear filters
        function clearFilters() {
            document.getElementById('filter-direction').value = 'all';
            document.getElementById('filter-status').value = 'all';
            document.getElementById('filter-date-from').value = '';
            document.getElementById('filter-date-to').value = '';
            filteredCalls = [...allCalls];
            currentPage = 1;
            renderCalls();
        }

        // Pagination
        function nextPage() {
            const totalPages = Math.ceil(filteredCalls.length / itemsPerPage);
            if (currentPage < totalPages) {
                currentPage++;
                renderCalls();
                window.scrollTo(0, 0);
            }
        }

        function prevPage() {
            if (currentPage > 1) {
                currentPage--;
                renderCalls();
                window.scrollTo(0, 0);
            }
        }

        // Format duration
        function formatDuration(seconds) {
            if (!seconds) return '0s';
            const hours = Math.floor(seconds / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);
            const secs = seconds % 60;

            if (hours > 0) {
                return `${hours}h ${minutes}m ${secs}s`;
            } else if (minutes > 0) {
                return `${minutes}m ${secs}s`;
            } else {
                return `${secs}s`;
            }
        }

        // Load on page load
        loadCallHistory();
    </script>

    <!-- Accessibility Tools -->
    <script src="/js/accessibility.js"></script>
</body>
</html>
