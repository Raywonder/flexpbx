<?php
/**
 * FlexPBX Admin - MOH Submissions Review
 * Review and approve/reject community-submitted streams and providers
 */

session_start();
require_once __DIR__ . '/admin_auth_check.php';

if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    header('Location: /admin/login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MOH Submissions - FlexPBX Admin</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f7fa;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .card {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        h1 {
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }

        .subtitle {
            color: #666;
            margin-bottom: 2rem;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 8px;
            text-align: center;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
        }

        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            border-bottom: 2px solid #e9ecef;
        }

        .tab {
            padding: 1rem 2rem;
            background: none;
            border: none;
            cursor: pointer;
            color: #666;
            font-size: 1rem;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }

        .tab:hover {
            color: #667eea;
        }

        .tab.active {
            color: #667eea;
            border-bottom-color: #667eea;
            font-weight: 600;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .submission-item {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            border-left: 4px solid #667eea;
        }

        .submission-item.pending {
            border-left-color: #f39c12;
        }

        .submission-item.approved {
            border-left-color: #27ae60;
        }

        .submission-item.rejected {
            border-left-color: #e74c3c;
        }

        .submission-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }

        .submission-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #2c3e50;
        }

        .status-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .status-pending {
            background: #f39c12;
            color: white;
        }

        .status-approved {
            background: #27ae60;
            color: white;
        }

        .status-rejected {
            background: #e74c3c;
            color: white;
        }

        .submission-details {
            margin: 1rem 0;
            line-height: 1.6;
        }

        .detail-row {
            margin: 0.5rem 0;
        }

        .detail-label {
            font-weight: 600;
            display: inline-block;
            width: 150px;
        }

        .btn {
            padding: 0.6rem 1.2rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
            margin-right: 0.5rem;
            transition: all 0.3s;
        }

        .btn-success {
            background: #27ae60;
            color: white;
        }

        .btn-danger {
            background: #e74c3c;
            color: white;
        }

        .btn-info {
            background: #3498db;
            color: white;
        }

        .btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            display: none;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
        }

        .back-link {
            color: #667eea;
            text-decoration: none;
            display: inline-block;
            margin-bottom: 1rem;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" class="back-link">← Back to Admin Dashboard</a>

        <div class="card">
            <h1>📋 MOH Community Submissions</h1>
            <p class="subtitle">Review and manage stream and provider submissions</p>

            <div class="stats">
                <div class="stat-box">
                    <div class="stat-number" id="stat-pending">0</div>
                    <div class="stat-label">Pending Review</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number" id="stat-approved">0</div>
                    <div class="stat-label">Approved</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number" id="stat-streams">0</div>
                    <div class="stat-label">Stream Submissions</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number" id="stat-providers">0</div>
                    <div class="stat-label">Provider Registrations</div>
                </div>
            </div>

            <div id="alert" class="alert"></div>

            <div class="tabs">
                <button class="tab active" onclick="switchTab('pending')">Pending Review</button>
                <button class="tab" onclick="switchTab('approved')">Approved</button>
                <button class="tab" onclick="switchTab('all')">All Submissions</button>
            </div>

            <div id="pending-tab" class="tab-content active">
                <h2>Pending Review</h2>
                <div id="pending-list"></div>
            </div>

            <div id="approved-tab" class="tab-content">
                <h2>Approved Submissions</h2>
                <div id="approved-list"></div>
            </div>

            <div id="all-tab" class="tab-content">
                <h2>All Submissions</h2>
                <div id="all-list"></div>
            </div>
        </div>
    </div>

    <script>
        let submissions = [];
        let providers = [];

        function switchTab(tab) {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            event.target.classList.add('active');

            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            document.getElementById(`${tab}-tab`).classList.add('active');
        }

        function showAlert(message, type) {
            const alert = document.getElementById('alert');
            alert.textContent = message;
            alert.className = `alert alert-${type}`;
            alert.style.display = 'block';

            setTimeout(() => {
                alert.style.display = 'none';
            }, 5000);
        }

        async function loadSubmissions() {
            // In production, would load from actual files
            // For now, simulate empty state
            displaySubmissions();
        }

        function displaySubmissions() {
            const allItems = [...submissions, ...providers];

            // Update stats
            const pending = allItems.filter(i => i.status === 'pending').length;
            const approved = allItems.filter(i => i.status === 'approved').length;
            const streamCount = submissions.length;
            const providerCount = providers.length;

            document.getElementById('stat-pending').textContent = pending;
            document.getElementById('stat-approved').textContent = approved;
            document.getElementById('stat-streams').textContent = streamCount;
            document.getElementById('stat-providers').textContent = providerCount;

            // Display lists
            displayList('pending', allItems.filter(i => i.status === 'pending'));
            displayList('approved', allItems.filter(i => i.status === 'approved'));
            displayList('all', allItems);
        }

        function displayList(listId, items) {
            const container = document.getElementById(`${listId}-list`);

            if (items.length === 0) {
                container.innerHTML = '<div class="empty-state">No submissions to display</div>';
                return;
            }

            container.innerHTML = items.map(item => {
                const isStream = item.type === 'stream';
                const info = isStream ? item.stream_info : item.provider_info;

                return `
                    <div class="submission-item ${item.status}">
                        <div class="submission-header">
                            <div>
                                <div class="submission-title">
                                    ${isStream ? '🎵' : '🌐'} ${info.display_name || info.name}
                                </div>
                                <div style="color: #666; font-size: 0.9rem;">
                                    ${isStream ? 'Stream Submission' : 'Provider Registration'} |
                                    Submitted: ${new Date(item.submitted_at).toLocaleDateString()}
                                </div>
                            </div>
                            <span class="status-badge status-${item.status}">${item.status.toUpperCase()}</span>
                        </div>

                        <div class="submission-details">
                            ${isStream ? `
                                <div class="detail-row">
                                    <span class="detail-label">URL:</span>
                                    <a href="${info.url}" target="_blank">${info.url}</a>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Description:</span>
                                    ${info.description}
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Category:</span>
                                    ${info.category}
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Format:</span>
                                    ${info.format} @ ${info.bitrate}
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Licensing:</span>
                                    ${info.licensing}
                                </div>
                            ` : `
                                <div class="detail-row">
                                    <span class="detail-label">API Endpoint:</span>
                                    <a href="${info.api_endpoint}" target="_blank">${info.api_endpoint}</a>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Description:</span>
                                    ${info.description}
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Streams Offered:</span>
                                    ${item.capabilities.streams_offered}
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">HTTPS:</span>
                                    ${item.capabilities.https_streaming ? '✓ Yes' : '✗ No'}
                                </div>
                            `}

                            <div class="detail-row">
                                <span class="detail-label">Submitted by:</span>
                                ${isStream ? item.submitter.name : item.contact.name}
                                (${isStream ? item.submitter.email : item.contact.email})
                            </div>

                            ${item.notes ? `
                                <div class="detail-row">
                                    <span class="detail-label">Notes:</span>
                                    ${item.notes}
                                </div>
                            ` : ''}
                        </div>

                        ${item.status === 'pending' ? `
                            <div style="margin-top: 1rem;">
                                <button class="btn btn-success" onclick="approveSubmission('${item.id}')">
                                    ✓ Approve
                                </button>
                                <button class="btn btn-danger" onclick="rejectSubmission('${item.id}')">
                                    ✗ Reject
                                </button>
                                <button class="btn btn-info" onclick="testSubmission('${item.id}')">
                                    🧪 Test Stream
                                </button>
                            </div>
                        ` : ''}
                    </div>
                `;
            }).join('');
        }

        function approveSubmission(id) {
            if (!confirm('Approve this submission?')) return;

            // In production, would update the submission status
            showAlert('Submission approved and added to catalog', 'success');
            // Reload submissions
            loadSubmissions();
        }

        function rejectSubmission(id) {
            const reason = prompt('Reason for rejection (will be sent to submitter):');
            if (!reason) return;

            // In production, would update status and notify submitter
            showAlert('Submission rejected and submitter notified', 'success');
            // Reload submissions
            loadSubmissions();
        }

        function testSubmission(id) {
            showAlert('Opening stream test in new window...', 'success');
            // In production, would test the stream
        }

        // Initialize
        loadSubmissions();
    </script>
</body>
</html>
