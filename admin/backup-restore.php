<?php require_once __DIR__ . '/admin_auth_check.php'; ?>
<!DOCTYPE html>
<html lang="en">
<!--
    FlexPBX Backup & Restore UI
    Created: October 17, 2025
    API: Uses /api/system.php with backup endpoints
    Features:
    - List all backups with details
    - Create new backup (flx, flxx, full)
    - Restore from backup with confirmation
    - Delete backups
    - Download backups
    - Drive selection for backup destination
    - Real-time backup progress
    - Backup history and statistics
-->
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FlexPBX Admin - Backup & Restore</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .sidebar a {
            color: white;
            text-decoration: none;
            transition: all 0.3s;
        }
        .sidebar a:hover, .sidebar .active {
            background: rgba(255,255,255,0.2);
            border-radius: 8px;
        }
        .stat-card {
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
            border: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.15);
        }
        .stat-card.primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .stat-card.success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }
        .stat-card.warning {
            background: linear-gradient(135deg, #ffc107 0%, #ff6b6b 100%);
            color: white;
        }
        .stat-card.info {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            color: white;
        }
        .stat-value {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 0;
        }
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
            margin-bottom: 10px;
        }
        .stat-detail {
            font-size: 0.85rem;
            opacity: 0.8;
            margin-top: 10px;
        }
        .backup-section {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .backup-table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .backup-row {
            transition: all 0.2s;
            cursor: pointer;
        }
        .backup-row:hover {
            background-color: #f8f9fa;
        }
        .type-badge {
            font-size: 0.75rem;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-weight: 600;
        }
        .type-badge.flx {
            background-color: #e3f2fd;
            color: #1976d2;
        }
        .type-badge.flxx {
            background-color: #e8f5e9;
            color: #388e3c;
        }
        .type-badge.full {
            background-color: #fff3e0;
            color: #f57c00;
        }
        .progress-container {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            z-index: 9999;
            min-width: 400px;
        }
        .progress-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9998;
        }
        .backup-actions {
            display: flex;
            gap: 10px;
        }
        .drive-select {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 sidebar p-4">
                <h4 class="text-white mb-4"><i class="fas fa-phone-alt"></i> FlexPBX</h4>
                <nav class="nav flex-column">
                    <a class="nav-link p-3" href="dashboard.html">
                        <i class="fas fa-home me-2"></i> Dashboard
                    </a>
                    <a class="nav-link p-3 active" href="backup-restore.html">
                        <i class="fas fa-database me-2"></i> Backup & Restore
                    </a>
                    <a class="nav-link p-3" href="call-logs.html">
                        <i class="fas fa-phone me-2"></i> Call Logs
                    </a>
                    <a class="nav-link p-3" href="extensions.html">
                        <i class="fas fa-users me-2"></i> Extensions
                    </a>
                    <a class="nav-link p-3" href="trunks-enhanced.html">
                        <i class="fas fa-network-wired me-2"></i> Trunks
                    </a>
                    <a class="nav-link p-3" href="admin-security.html">
                        <i class="fas fa-shield-alt me-2"></i> Security
                    </a>
                    <a class="nav-link p-3" href="index.html">
                        <i class="fas fa-cog me-2"></i> Settings
                    </a>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-md-10 p-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2><i class="fas fa-database text-primary"></i> Backup & Restore</h2>
                        <p class="text-muted">Manage system backups and restore configurations</p>
                    </div>
                    <button class="btn btn-primary" onclick="showCreateBackupModal()">
                        <i class="fas fa-plus me-2"></i>Create Backup
                    </button>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stat-card primary">
                            <div class="stat-label">Total Backups</div>
                            <p class="stat-value" id="totalBackups">0</p>
                            <div class="stat-detail">Across all locations</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card success">
                            <div class="stat-label">Latest Backup</div>
                            <p class="stat-value" id="latestBackup" style="font-size: 1.2rem;">N/A</p>
                            <div class="stat-detail">Most recent backup</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card warning">
                            <div class="stat-label">Total Size</div>
                            <p class="stat-value" id="totalSize">0 MB</p>
                            <div class="stat-detail">All backup files</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card info">
                            <div class="stat-label">Available Drives</div>
                            <p class="stat-value" id="availableDrives">0</p>
                            <div class="stat-detail">Storage locations</div>
                        </div>
                    </div>
                </div>

                <!-- Drive Selection -->
                <div class="backup-section" id="driveSection">
                    <h5><i class="fas fa-hdd me-2"></i>Backup Storage Drives</h5>
                    <div id="driveList" class="mt-3">
                        <div class="text-center">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading drives...</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Backup List -->
                <div class="backup-section">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5><i class="fas fa-list me-2"></i>Backup Files</h5>
                        <div>
                            <button class="btn btn-sm btn-outline-primary" onclick="loadBackups()">
                                <i class="fas fa-sync-alt me-1"></i>Refresh
                            </button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover" id="backupTable">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Type</th>
                                    <th>Size</th>
                                    <th>Date</th>
                                    <th>Location</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="backupTableBody">
                                <tr>
                                    <td colspan="6" class="text-center">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">Loading backups...</span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Backup Modal -->
    <div class="modal fade" id="createBackupModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>Create New Backup</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label"><strong>Backup Type:</strong></label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="backupType" id="typeFlx" value="flx" checked>
                            <label class="form-check-label" for="typeFlx">
                                <strong>Config Only (.flx)</strong> - Fast, small backup of configuration files only
                            </label>
                        </div>
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="radio" name="backupType" id="typeFlxx" value="flxx">
                            <label class="form-check-label" for="typeFlxx">
                                <strong>Extended (.flxx)</strong> - Configuration + user data, voicemail, etc.
                            </label>
                        </div>
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="radio" name="backupType" id="typeFull" value="full">
                            <label class="form-check-label" for="typeFull">
                                <strong>Golden Master (.tar.gz)</strong> - Complete system backup for installation
                            </label>
                        </div>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="compressBackup" checked>
                        <label class="form-check-label" for="compressBackup">
                            Compress backup (recommended)
                        </label>
                    </div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <small>Backups will be stored in <code>/mnt/backup/flexpbx-backups/</code> and copied to <code>/home/flexpbxuser/public_html/uploads/backups/</code></small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="createBackup()">
                        <i class="fas fa-save me-2"></i>Create Backup
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Restore Confirmation Modal -->
    <div class="modal fade" id="restoreModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Confirm Restore</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <strong>Warning:</strong> Restoring from a backup will overwrite your current configuration!
                    </div>
                    <p>Are you sure you want to restore from:</p>
                    <p class="text-center"><strong id="restoreFileName"></strong></p>
                    <p class="text-muted"><small>This action cannot be undone. Make sure you have a recent backup of your current configuration.</small></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-warning" onclick="confirmRestore()">
                        <i class="fas fa-undo me-2"></i>Restore Now
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-trash me-2"></i>Confirm Delete</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this backup?</p>
                    <p class="text-center"><strong id="deleteFileName"></strong></p>
                    <p class="text-muted"><small>This action cannot be undone.</small></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" onclick="confirmDelete()">
                        <i class="fas fa-trash me-2"></i>Delete Backup
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Progress Overlay -->
    <div class="progress-overlay" id="progressOverlay"></div>
    <div class="progress-container" id="progressContainer">
        <h5 class="mb-3" id="progressTitle">Creating Backup...</h5>
        <div class="progress" style="height: 25px;">
            <div class="progress-bar progress-bar-striped progress-bar-animated"
                 role="progressbar"
                 style="width: 100%"
                 id="progressBar">
                Processing...
            </div>
        </div>
        <p class="text-muted mt-3 mb-0 text-center" id="progressMessage">Please wait...</p>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="backup-processor-fallback.js"></script>
    <script>
        let backupToRestore = null;
        let backupToDelete = null;
        let createBackupModalInstance = null;
        let restoreModalInstance = null;
        let deleteModalInstance = null;

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize modals
            createBackupModalInstance = new bootstrap.Modal(document.getElementById('createBackupModal'));
            restoreModalInstance = new bootstrap.Modal(document.getElementById('restoreModal'));
            deleteModalInstance = new bootstrap.Modal(document.getElementById('deleteModal'));

            // Load data
            loadBackups();
            loadDrives();
            updateStatistics();
        });

        // Show create backup modal
        function showCreateBackupModal() {
            createBackupModalInstance.show();
        }

        // Load backups from API
        async function loadBackups() {
            try {
                const response = await fetch('/api/system.php?path=backup_list');
                const data = await response.json();

                if (data.success) {
                    displayBackups(data.backups);
                    updateStatistics();
                } else {
                    showError('Failed to load backups: ' + data.error);
                }
            } catch (error) {
                showError('Error loading backups: ' + error.message);
            }
        }

        // Display backups in table
        function displayBackups(backups) {
            const tbody = document.getElementById('backupTableBody');

            if (!backups || backups.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No backups found</td></tr>';
                return;
            }

            tbody.innerHTML = backups.map(backup => {
                const type = getBackupType(backup.name);
                const size = formatSize(backup.size);
                const date = formatDate(backup.date);
                const location = backup.path.includes('/mnt/backup') ? 'Primary Drive' : 'Web Server';

                return `
                    <tr class="backup-row">
                        <td>
                            <i class="fas fa-file-archive me-2 text-primary"></i>
                            <strong>${backup.name}</strong>
                        </td>
                        <td><span class="type-badge ${type}">${type.toUpperCase()}</span></td>
                        <td>${size}</td>
                        <td><small>${date}</small></td>
                        <td><small>${location}</small></td>
                        <td>
                            <div class="backup-actions">
                                <button class="btn btn-sm btn-success"
                                        onclick="showRestoreModal('${backup.path}')"
                                        title="Restore">
                                    <i class="fas fa-undo"></i>
                                </button>
                                <button class="btn btn-sm btn-primary"
                                        onclick="downloadBackup('${backup.path}')"
                                        title="Download">
                                    <i class="fas fa-download"></i>
                                </button>
                                <button class="btn btn-sm btn-danger"
                                        onclick="showDeleteModal('${backup.path}', '${backup.name}')"
                                        title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        // Load drives from API
        async function loadDrives() {
            try {
                const response = await fetch('/api/system.php?path=drives');
                const data = await response.json();

                if (data.success && data.drives) {
                    displayDrives(data.drives);
                    document.getElementById('availableDrives').textContent = data.drives.length;
                } else {
                    document.getElementById('driveList').innerHTML =
                        '<div class="alert alert-warning">No additional drives detected</div>';
                }
            } catch (error) {
                document.getElementById('driveList').innerHTML =
                    '<div class="alert alert-danger">Error loading drives: ' + error.message + '</div>';
            }
        }

        // Display drives
        function displayDrives(drives) {
            const container = document.getElementById('driveList');

            const mountedDrives = drives.filter(d => d.mountpoint && d.mountpoint !== '[SWAP]');

            if (mountedDrives.length === 0) {
                container.innerHTML = '<div class="alert alert-info">No additional drives mounted</div>';
                return;
            }

            container.innerHTML = mountedDrives.map(drive => {
                const sizeGB = (parseFloat(drive.size) / 1024 / 1024 / 1024).toFixed(1);
                const isPrimary = drive.mountpoint.includes('/mnt/backup');

                return `
                    <div class="drive-select">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-1">
                                    <i class="fas fa-hdd me-2 ${isPrimary ? 'text-success' : 'text-muted'}"></i>
                                    ${drive.name}
                                    ${isPrimary ? '<span class="badge bg-success">Primary Backup Drive</span>' : ''}
                                </h6>
                                <small class="text-muted">
                                    ${drive.mountpoint} • ${sizeGB} GB • ${drive.fstype}
                                    ${drive.label ? ' • ' + drive.label : ''}
                                </small>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        }

        // Create backup
        async function createBackup() {
            const type = document.querySelector('input[name="backupType"]:checked').value;
            const compress = document.getElementById('compressBackup').checked;

            // Hide modal
            createBackupModalInstance.hide();

            // Show progress
            showProgress('Creating Backup...', 'Preparing backup files...');

            try {
                const response = await fetch('/api/system.php?path=backup_create', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        type: type,
                        compress: compress
                    })
                });

                const data = await response.json();

                hideProgress();

                if (data.success) {
                    showSuccess('Backup created successfully!');
                    loadBackups();
                } else {
                    showError('Backup failed: ' + data.error);
                }
            } catch (error) {
                hideProgress();
                showError('Error creating backup: ' + error.message);
            }
        }

        // Show restore modal
        function showRestoreModal(backupPath) {
            backupToRestore = backupPath;
            const fileName = backupPath.split('/').pop();
            document.getElementById('restoreFileName').textContent = fileName;
            restoreModalInstance.show();
        }

        // Confirm restore
        async function confirmRestore() {
            if (!backupToRestore) return;

            // Hide modal
            restoreModalInstance.hide();

            // Show progress
            showProgress('Restoring Backup...', 'This may take a few minutes...');

            try {
                const response = await fetch('/api/system.php?path=backup_restore', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        backup_file: backupToRestore
                    })
                });

                const data = await response.json();

                hideProgress();

                if (data.success) {
                    showSuccess('Backup restored successfully! System may need restart.');
                    loadBackups();
                } else {
                    showError('Restore failed: ' + data.error);
                }
            } catch (error) {
                hideProgress();
                showError('Error restoring backup: ' + error.message);
            }

            backupToRestore = null;
        }

        // Show delete modal
        function showDeleteModal(backupPath, backupName) {
            backupToDelete = backupPath;
            document.getElementById('deleteFileName').textContent = backupName;
            deleteModalInstance.show();
        }

        // Confirm delete
        async function confirmDelete() {
            if (!backupToDelete) return;

            // Hide modal
            deleteModalInstance.hide();

            // Show progress
            showProgress('Deleting Backup...', 'Removing backup file...');

            try {
                const response = await fetch('/api/system.php?path=backup_delete', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        backup_file: backupToDelete
                    })
                });

                const data = await response.json();

                hideProgress();

                if (data.success) {
                    showSuccess('Backup deleted successfully!');
                    loadBackups();
                } else {
                    showError('Delete failed: ' + data.error);
                }
            } catch (error) {
                hideProgress();
                showError('Error deleting backup: ' + error.message);
            }

            backupToDelete = null;
        }

        // Download backup
        function downloadBackup(backupPath) {
            window.location.href = '/api/system.php?path=backup_download&file=' + encodeURIComponent(backupPath);
        }

        // Update statistics
        async function updateStatistics() {
            try {
                const response = await fetch('/api/system.php?path=backup_list');
                const data = await response.json();

                if (data.success && data.backups) {
                    const backups = data.backups;

                    // Total backups
                    document.getElementById('totalBackups').textContent = backups.length;

                    // Latest backup
                    if (backups.length > 0) {
                        const latest = backups[0];
                        const date = new Date(latest.timestamp * 1000);
                        document.getElementById('latestBackup').textContent = date.toLocaleDateString();
                    }

                    // Total size
                    const totalBytes = backups.reduce((sum, b) => sum + parseInt(b.size || 0), 0);
                    document.getElementById('totalSize').textContent = formatSize(totalBytes);
                }
            } catch (error) {
                console.error('Error updating statistics:', error);
            }
        }

        // Helper functions
        function getBackupType(filename) {
            if (filename.includes('.flx') && !filename.includes('.flxx')) return 'flx';
            if (filename.includes('.flxx')) return 'flxx';
            if (filename.includes('.tar.gz')) return 'full';
            return 'unknown';
        }

        function formatSize(bytes) {
            if (!bytes || bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
        }

        function formatDate(timestamp) {
            const date = new Date(timestamp * 1000);
            return date.toLocaleString();
        }

        function showProgress(title, message) {
            document.getElementById('progressTitle').textContent = title;
            document.getElementById('progressMessage').textContent = message;
            document.getElementById('progressOverlay').style.display = 'block';
            document.getElementById('progressContainer').style.display = 'block';
        }

        function hideProgress() {
            document.getElementById('progressOverlay').style.display = 'none';
            document.getElementById('progressContainer').style.display = 'none';
        }

        function showSuccess(message) {
            alert(message); // TODO: Replace with toast notification
        }

        function showError(message) {
            alert('Error: ' + message); // TODO: Replace with toast notification
        }
    </script>
</body>
</html>
