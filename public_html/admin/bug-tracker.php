<?php
/**
 * FlexPBX Bug Tracker
 * Simple, accessible bug tracking system
 */

session_start();

$bugs_dir = '/home/flexpbxuser/bugs';
@mkdir($bugs_dir, 0755, true);

$success_message = '';
$error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'submit_bug':
            $bug_data = [
                'id' => time() . '_' . rand(1000, 9999),
                'timestamp' => date('Y-m-d H:i:s'),
                'title' => $_POST['title'] ?? '',
                'description' => $_POST['description'] ?? '',
                'steps' => $_POST['steps'] ?? '',
                'expected' => $_POST['expected'] ?? '',
                'actual' => $_POST['actual'] ?? '',
                'severity' => $_POST['severity'] ?? 'medium',
                'category' => $_POST['category'] ?? 'other',
                'reporter_name' => $_POST['reporter_name'] ?? '',
                'reporter_email' => $_POST['reporter_email'] ?? '',
                'reporter_extension' => $_POST['reporter_extension'] ?? '',
                'environment' => $_POST['environment'] ?? '',
                'status' => 'new',
                'assigned_to' => '',
                'notes' => '',
                'resolved_date' => ''
            ];

            $bug_file = $bugs_dir . '/bug_' . $bug_data['id'] . '.json';
            file_put_contents($bug_file, json_encode($bug_data, JSON_PRETTY_PRINT));
            $success_message = "Bug report submitted! ID: " . $bug_data['id'];
            break;

        case 'update_status':
            $bug_id = $_POST['bug_id'] ?? '';
            $bug_file = $bugs_dir . '/bug_' . $bug_id . '.json';
            if (file_exists($bug_file)) {
                $bug_data = json_decode(file_get_contents($bug_file), true);
                $bug_data['status'] = $_POST['status'] ?? $bug_data['status'];
                $bug_data['assigned_to'] = $_POST['assigned_to'] ?? $bug_data['assigned_to'];
                $bug_data['notes'] = $_POST['notes'] ?? $bug_data['notes'];
                if ($bug_data['status'] === 'resolved' || $bug_data['status'] === 'closed') {
                    $bug_data['resolved_date'] = date('Y-m-d H:i:s');
                }
                file_put_contents($bug_file, json_encode($bug_data, JSON_PRETTY_PRINT));
                $success_message = "Bug updated successfully!";
            }
            break;
    }
}

// Get all bugs
function getAllBugs($bugs_dir) {
    $bugs = [];
    $files = glob($bugs_dir . '/bug_*.json');
    foreach ($files as $file) {
        $bug = json_decode(file_get_contents($file), true);
        $bugs[] = $bug;
    }
    // Sort by timestamp, newest first
    usort($bugs, function($a, $b) {
        return strtotime($b['timestamp']) - strtotime($a['timestamp']);
    });
    return $bugs;
}

$all_bugs = getAllBugs($bugs_dir);

// Filter bugs
$filter_status = $_GET['status'] ?? 'all';
$filter_severity = $_GET['severity'] ?? 'all';

$filtered_bugs = array_filter($all_bugs, function($bug) use ($filter_status, $filter_severity) {
    $status_match = $filter_status === 'all' || $bug['status'] === $filter_status;
    $severity_match = $filter_severity === 'all' || $bug['severity'] === $filter_severity;
    return $status_match && $severity_match;
});

// Statistics
$total_bugs = count($all_bugs);
$new_bugs = count(array_filter($all_bugs, fn($b) => $b['status'] === 'new'));
$open_bugs = count(array_filter($all_bugs, fn($b) => $b['status'] === 'open' || $b['status'] === 'in_progress'));
$resolved_bugs = count(array_filter($all_bugs, fn($b) => $b['status'] === 'resolved' || $b['status'] === 'closed'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bug Tracker - FlexPBX</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
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
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        .header h1 {
            color: #333;
            margin-bottom: 10px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        .stat-box {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        .stat-box .number {
            font-size: 36px;
            font-weight: bold;
            color: #667eea;
        }
        .stat-box .label {
            color: #666;
            font-size: 14px;
            margin-top: 5px;
        }
        .card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        .card h2 {
            color: #333;
            margin-bottom: 20px;
        }
        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }
        .btn-secondary {
            background: #6c757d;
        }
        .btn-success {
            background: #28a745;
        }
        .btn-danger {
            background: #dc3545;
        }
        .btn-small {
            padding: 5px 10px;
            font-size: 12px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        .form-group small {
            display: block;
            margin-top: 5px;
            color: #666;
            font-size: 12px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        table thead {
            background: #f8f9fa;
        }
        table th {
            padding: 12px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid #dee2e6;
        }
        table td {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
        }
        table tbody tr:hover {
            background: #f8f9fa;
        }
        .status-badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-new {
            background: #e7f3ff;
            color: #0066cc;
        }
        .status-open {
            background: #fff3cd;
            color: #856404;
        }
        .status-in_progress {
            background: #cfe2ff;
            color: #084298;
        }
        .status-resolved {
            background: #d1e7dd;
            color: #0f5132;
        }
        .status-closed {
            background: #e2e3e5;
            color: #41464b;
        }
        .severity-critical {
            background: #f8d7da;
            color: #721c24;
        }
        .severity-high {
            background: #fff3cd;
            color: #856404;
        }
        .severity-medium {
            background: #cfe2ff;
            color: #084298;
        }
        .severity-low {
            background: #d1e7dd;
            color: #0f5132;
        }
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
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
        .filters {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .filter-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .filter-group label {
            font-weight: 600;
            color: #333;
        }
        .filter-group select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
        }
        .back-link {
            color: #667eea;
            text-decoration: none;
            display: inline-block;
            margin-bottom: 10px;
        }
        .back-link:hover {
            text-decoration: underline;
        }
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #e2e8f0;
        }
        .tab {
            padding: 12px 24px;
            background: none;
            border: none;
            color: #666;
            font-weight: 600;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }
        .tab.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }
        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background: white;
            border-radius: 12px;
            padding: 30px;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            width: 90%;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.html" class="back-link">← Back to Dashboard</a>

        <div class="header">
            <h1>🐛 Bug Tracker</h1>
            <p style="color: #666; margin-top: 5px;">Track and manage bugs, issues, and feature requests</p>

            <div class="stats-grid">
                <div class="stat-box">
                    <div class="number"><?= $total_bugs ?></div>
                    <div class="label">Total Bugs</div>
                </div>
                <div class="stat-box">
                    <div class="number" style="color: #0066cc;"><?= $new_bugs ?></div>
                    <div class="label">New</div>
                </div>
                <div class="stat-box">
                    <div class="number" style="color: #ffc107;"><?= $open_bugs ?></div>
                    <div class="label">Open/In Progress</div>
                </div>
                <div class="stat-box">
                    <div class="number" style="color: #28a745;"><?= $resolved_bugs ?></div>
                    <div class="label">Resolved</div>
                </div>
            </div>
        </div>

        <?php if ($success_message): ?>
        <div class="alert alert-success">
            ✓ <?= htmlspecialchars($success_message) ?>
        </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
        <div class="alert alert-error">
            ⚠️ <?= htmlspecialchars($error_message) ?>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="tabs">
                <button class="tab active" onclick="switchTab('all-bugs')">All Bugs</button>
                <button class="tab" onclick="switchTab('submit-bug')">Submit New Bug</button>
                <button class="tab" onclick="switchTab('my-bugs')">My Reported Bugs</button>
            </div>

            <!-- All Bugs Tab -->
            <div id="all-bugs" class="tab-content active">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2 style="margin: 0;">Bug List</h2>
                    <button class="btn btn-secondary btn-small" onclick="exportBugs()">Export CSV</button>
                </div>

                <form method="GET" class="filters">
                    <div class="filter-group">
                        <label for="status">Status:</label>
                        <select name="status" id="status" onchange="this.form.submit()">
                            <option value="all" <?= $filter_status === 'all' ? 'selected' : '' ?>>All</option>
                            <option value="new" <?= $filter_status === 'new' ? 'selected' : '' ?>>New</option>
                            <option value="open" <?= $filter_status === 'open' ? 'selected' : '' ?>>Open</option>
                            <option value="in_progress" <?= $filter_status === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                            <option value="resolved" <?= $filter_status === 'resolved' ? 'selected' : '' ?>>Resolved</option>
                            <option value="closed" <?= $filter_status === 'closed' ? 'selected' : '' ?>>Closed</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="severity">Severity:</label>
                        <select name="severity" id="severity" onchange="this.form.submit()">
                            <option value="all" <?= $filter_severity === 'all' ? 'selected' : '' ?>>All</option>
                            <option value="critical" <?= $filter_severity === 'critical' ? 'selected' : '' ?>>Critical</option>
                            <option value="high" <?= $filter_severity === 'high' ? 'selected' : '' ?>>High</option>
                            <option value="medium" <?= $filter_severity === 'medium' ? 'selected' : '' ?>>Medium</option>
                            <option value="low" <?= $filter_severity === 'low' ? 'selected' : '' ?>>Low</option>
                        </select>
                    </div>
                </form>

                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Severity</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th>Reported</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($filtered_bugs)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; color: #666;">No bugs found. This is good news!</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($filtered_bugs as $bug): ?>
                        <tr>
                            <td><strong><?= substr($bug['id'], -8) ?></strong></td>
                            <td><?= htmlspecialchars($bug['title']) ?></td>
                            <td><span class="status-badge severity-<?= $bug['severity'] ?>"><?= ucfirst($bug['severity']) ?></span></td>
                            <td><?= ucfirst($bug['category']) ?></td>
                            <td><span class="status-badge status-<?= $bug['status'] ?>"><?= ucwords(str_replace('_', ' ', $bug['status'])) ?></span></td>
                            <td><?= date('M d, Y', strtotime($bug['timestamp'])) ?></td>
                            <td>
                                <button class="btn btn-small" onclick="viewBug('<?= $bug['id'] ?>')">View</button>
                                <button class="btn btn-small btn-secondary" onclick="updateBug('<?= $bug['id'] ?>')">Update</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Submit Bug Tab -->
            <div id="submit-bug" class="tab-content">
                <h2>Submit New Bug Report</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="submit_bug">

                    <div class="form-group">
                        <label for="title">Bug Title *</label>
                        <input type="text" id="title" name="title" required>
                        <small>Brief description of the issue</small>
                    </div>

                    <div class="form-group">
                        <label for="description">Description *</label>
                        <textarea id="description" name="description" required></textarea>
                        <small>Detailed description of the problem</small>
                    </div>

                    <div class="form-group">
                        <label for="steps">Steps to Reproduce *</label>
                        <textarea id="steps" name="steps" required placeholder="1. First step&#10;2. Second step&#10;3. Third step"></textarea>
                        <small>List the steps to recreate the issue</small>
                    </div>

                    <div class="form-group">
                        <label for="expected">Expected Behavior</label>
                        <textarea id="expected" name="expected" placeholder="What should happen..."></textarea>
                    </div>

                    <div class="form-group">
                        <label for="actual">Actual Behavior</label>
                        <textarea id="actual" name="actual" placeholder="What actually happens..."></textarea>
                    </div>

                    <div class="form-group">
                        <label for="severity">Severity *</label>
                        <select id="severity" name="severity" required>
                            <option value="low">Low - Minor issue, workaround available</option>
                            <option value="medium" selected>Medium - Notable issue, impacts some users</option>
                            <option value="high">High - Major issue, impacts many users</option>
                            <option value="critical">Critical - System down or unusable</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="category">Category *</label>
                        <select id="category" name="category" required>
                            <option value="audio">Audio Quality</option>
                            <option value="calls">Call Functionality</option>
                            <option value="voicemail">Voicemail</option>
                            <option value="transfers">Call Transfers</option>
                            <option value="feature_codes">Feature Codes</option>
                            <option value="web_interface">Web Interface</option>
                            <option value="accessibility">Accessibility</option>
                            <option value="documentation">Documentation</option>
                            <option value="performance">Performance</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="reporter_name">Your Name *</label>
                        <input type="text" id="reporter_name" name="reporter_name" required>
                    </div>

                    <div class="form-group">
                        <label for="reporter_email">Your Email *</label>
                        <input type="email" id="reporter_email" name="reporter_email" required>
                    </div>

                    <div class="form-group">
                        <label for="reporter_extension">Your Extension (if applicable)</label>
                        <input type="text" id="reporter_extension" name="reporter_extension" placeholder="e.g., 2001">
                    </div>

                    <div class="form-group">
                        <label for="environment">Environment Details</label>
                        <textarea id="environment" name="environment" placeholder="Device: Windows 10&#10;Browser: Chrome 118&#10;Softphone: Zoiper&#10;etc..."></textarea>
                        <small>OS, browser, softphone, etc.</small>
                    </div>

                    <button type="submit" class="btn">Submit Bug Report</button>
                </form>
            </div>

            <!-- My Bugs Tab -->
            <div id="my-bugs" class="tab-content">
                <h2>My Reported Bugs</h2>
                <p style="color: #666;">Enter your email to view bugs you've reported:</p>
                <div class="form-group" style="max-width: 400px; margin-top: 20px;">
                    <input type="email" id="my_email" placeholder="your@email.com">
                    <button class="btn" onclick="filterMyBugs()" style="margin-top: 10px;">View My Bugs</button>
                </div>
                <div id="my-bugs-list" style="margin-top: 20px;"></div>
            </div>
        </div>
    </div>

    <script>
        function switchTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });

            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }

        function viewBug(bugId) {
            // TODO: Implement modal view
            window.location.href = `bug-details.php?id=${bugId}`;
        }

        function updateBug(bugId) {
            const newStatus = prompt('Enter new status (new/open/in_progress/resolved/closed):');
            if (newStatus) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="bug_id" value="${bugId}">
                    <input type="hidden" name="status" value="${newStatus}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function exportBugs() {
            alert('CSV export functionality coming soon!');
        }

        function filterMyBugs() {
            const email = document.getElementById('my_email').value;
            if (!email) {
                alert('Please enter your email address');
                return;
            }
            // TODO: Implement filtering by email
            alert('Filtering by email: ' + email + '\n\nFull functionality coming soon!');
        }
    </script>
</body>
</html>
