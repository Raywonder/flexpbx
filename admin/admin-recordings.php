<?php require_once __DIR__ . '/admin_auth_check.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Call Recordings - FlexPBX Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .main-container {
            max-width: 1600px;
            margin: 0 auto;
        }

        .header-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #667eea;
        }

        .stat-label {
            color: #666;
            font-size: 14px;
            margin-top: 5px;
        }

        .recordings-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .recording-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            margin-bottom: 12px;
            transition: transform 0.2s;
        }

        .recording-item:hover {
            transform: translateX(5px);
            background: #e3f2fd;
        }

        .recording-info {
            display: flex;
            align-items: center;
            gap: 15px;
            flex: 1;
        }

        .recording-icon {
            font-size: 28px;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
        }

        .recording-icon.inbound {
            background: #e3f2fd;
            color: #1976d2;
        }

        .recording-icon.outbound {
            background: #fff3cd;
            color: #ff6b6b;
        }

        .recording-icon.internal {
            background: #d4edda;
            color: #28a745;
        }

        .recording-details {
            flex: 1;
        }

        .recording-filename {
            font-weight: 600;
            color: #333;
            margin-bottom: 4px;
        }

        .recording-meta {
            color: #666;
            font-size: 13px;
        }

        .btn-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
        }

        .filter-bar {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .empty-state {
            text-align: center;
            padding: 60px;
            color: #999;
        }

        audio {
            width: 100%;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="header-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1><i class="fas fa-microphone me-3"></i>Call Recordings</h1>
                    <p class="text-muted mb-0">Manage and review call recordings</p>
                </div>
                <div>
                    <button class="btn btn-outline-danger me-2" onclick="showCleanupModal()">
                        <i class="fas fa-broom me-2"></i>Cleanup Old
                    </button>
                    <button class="btn btn-outline-primary me-2" onclick="loadRecordings()">
                        <i class="fas fa-sync me-2"></i>Refresh
                    </button>
                    <a href="dashboard.html" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back
                    </a>
                </div>
            </div>
        </div>

        <div class="stats-grid" id="statsContainer">
            <div class="text-center">
                <div class="spinner-border text-primary" role="status"></div>
            </div>
        </div>

        <div class="filter-bar">
            <div class="row align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Type</label>
                    <select class="form-select" id="filterType" onchange="loadRecordings()">
                        <option value="all">All Recordings</option>
                        <option value="inbound">Inbound Only</option>
                        <option value="outbound">Outbound Only</option>
                        <option value="internal">Internal Only</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input type="text" class="form-control" id="searchQuery" placeholder="Search filename..." onkeyup="debounceSearch()">
                </div>
                <div class="col-md-2">
                    <label class="form-label">From Date</label>
                    <input type="date" class="form-control" id="dateFrom" onchange="loadRecordings()">
                </div>
                <div class="col-md-2">
                    <label class="form-label">To Date</label>
                    <input type="date" class="form-control" id="dateTo" onchange="loadRecordings()">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-outline-secondary w-100" onclick="clearFilters()">
                        <i class="fas fa-times me-2"></i>Clear
                    </button>
                </div>
            </div>
        </div>

        <div class="recordings-card">
            <h5 class="mb-4">Recordings</h5>
            <div id="recordingsContainer">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Cleanup Modal -->
    <div class="modal fade" id="cleanupModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Cleanup Old Recordings</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        This will permanently delete recordings older than the specified number of days.
                    </div>
                    <div class="mb-3">
                        <label for="cleanupDays" class="form-label">Delete recordings older than (days)</label>
                        <input type="number" class="form-control" id="cleanupDays" value="90" min="1">
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="dryRun" checked>
                        <label class="form-check-label" for="dryRun">
                            Dry run (preview only, don't delete)
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" onclick="performCleanup()">
                        <i class="fas fa-broom me-2"></i>Cleanup
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let searchTimeout;

        document.addEventListener('DOMContentLoaded', () => {
            loadStats();
            loadRecordings();
        });

        async function loadStats() {
            try {
                const response = await fetch('/api/recordings.php?path=stats');
                const data = await response.json();

                if (data.success) {
                    renderStats(data);
                }
            } catch (error) {
                console.error('Failed to load stats:', error);
            }
        }

        function renderStats(data) {
            const container = document.getElementById('statsContainer');
            container.innerHTML = `
                <div class="stat-card">
                    <div class="stat-value">${data.total_recordings}</div>
                    <div class="stat-label">Total Recordings</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">${data.total_size_formatted}</div>
                    <div class="stat-label">Total Size</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">${data.by_type.inbound.count}</div>
                    <div class="stat-label">Inbound Calls</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">${data.by_type.outbound.count}</div>
                    <div class="stat-label">Outbound Calls</div>
                </div>
            `;
        }

        async function loadRecordings() {
            const type = document.getElementById('filterType').value;

            try {
                const response = await fetch(`/api/recordings.php?path=list&type=${type}&limit=100`);
                const data = await response.json();

                if (data.success) {
                    renderRecordings(data.recordings);
                } else {
                    showAlert('Error: ' + data.message, 'danger');
                }
            } catch (error) {
                showAlert('Failed to load recordings: ' + error.message, 'danger');
            }
        }

        function renderRecordings(recordings) {
            const container = document.getElementById('recordingsContainer');

            if (recordings.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-microphone-slash fa-4x mb-3" style="color: #ddd;"></i>
                        <h4>No Recordings Found</h4>
                        <p>Recordings will appear here when calls are made</p>
                    </div>
                `;
                return;
            }

            let html = '';
            recordings.forEach(rec => {
                const iconClass = rec.type;
                const icon = rec.type === 'inbound' ? 'fa-phone-volume' :
                            rec.type === 'outbound' ? 'fa-phone' : 'fa-phone-alt';

                html += `
                    <div class="recording-item">
                        <div class="recording-info">
                            <div class="recording-icon ${iconClass}">
                                <i class="fas ${icon}"></i>
                            </div>
                            <div class="recording-details">
                                <div class="recording-filename">${rec.filename}</div>
                                <div class="recording-meta">
                                    <span class="badge bg-${rec.type === 'inbound' ? 'primary' : rec.type === 'outbound' ? 'warning' : 'success'}">
                                        ${rec.type.toUpperCase()}
                                    </span>
                                    <span class="ms-2">${rec.date}</span>
                                    <span class="ms-2">${rec.size_formatted}</span>
                                    ${rec.duration ? `<span class="ms-2"><i class="fas fa-clock me-1"></i>${rec.duration}</span>` : ''}
                                    <span class="ms-2 text-muted">${rec.age_days} days old</span>
                                </div>
                            </div>
                        </div>
                        <div class="btn-group">
                            <a href="/api/recordings.php?path=download&file=${encodeURIComponent(rec.filename)}&type=${rec.type}"
                               class="btn btn-sm btn-outline-primary" download>
                                <i class="fas fa-download"></i>
                            </a>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteRecording('${rec.type}', '${rec.filename}')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;
        }

        async function deleteRecording(type, filename) {
            if (!confirm(`Delete recording "${filename}"?`)) return;

            try {
                const response = await fetch('/api/recordings.php?path=delete', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ type, file: filename })
                });

                const data = await response.json();

                if (data.success) {
                    showAlert('Recording deleted', 'success');
                    loadRecordings();
                    loadStats();
                } else {
                    showAlert('Error: ' + data.message, 'danger');
                }
            } catch (error) {
                showAlert('Failed to delete: ' + error.message, 'danger');
            }
        }

        function showCleanupModal() {
            const modal = new bootstrap.Modal(document.getElementById('cleanupModal'));
            modal.show();
        }

        async function performCleanup() {
            const days = document.getElementById('cleanupDays').value;
            const dryRun = document.getElementById('dryRun').checked;

            try {
                const response = await fetch('/api/recordings.php?path=cleanup', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ days: parseInt(days), dry_run: dryRun })
                });

                const data = await response.json();

                if (data.success) {
                    if (dryRun) {
                        showAlert(`Would delete ${data.would_delete} recordings`, 'info');
                    } else {
                        showAlert(`Deleted ${data.deleted} recordings`, 'success');
                        loadRecordings();
                        loadStats();
                    }
                    bootstrap.Modal.getInstance(document.getElementById('cleanupModal')).hide();
                } else {
                    showAlert('Error: ' + data.message, 'danger');
                }
            } catch (error) {
                showAlert('Cleanup failed: ' + error.message, 'danger');
            }
        }

        function clearFilters() {
            document.getElementById('filterType').value = 'all';
            document.getElementById('searchQuery').value = '';
            document.getElementById('dateFrom').value = '';
            document.getElementById('dateTo').value = '';
            loadRecordings();
        }

        function debounceSearch() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                performSearch();
            }, 500);
        }

        async function performSearch() {
            const query = document.getElementById('searchQuery').value;
            const type = document.getElementById('filterType').value;
            const dateFrom = document.getElementById('dateFrom').value;
            const dateTo = document.getElementById('dateTo').value;

            if (!query && !dateFrom && !dateTo) {
                loadRecordings();
                return;
            }

            try {
                const response = await fetch('/api/recordings.php?path=search', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ query, type, date_from: dateFrom, date_to: dateTo })
                });

                const data = await response.json();

                if (data.success) {
                    renderRecordings(data.recordings);
                } else {
                    showAlert('Error: ' + data.message, 'danger');
                }
            } catch (error) {
                showAlert('Search failed: ' + error.message, 'danger');
            }
        }

        function showAlert(message, type) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3`;
            alertDiv.style.zIndex = '9999';
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(alertDiv);

            setTimeout(() => alertDiv.remove(), 5000);
        }
    </script>
</body>
</html>
