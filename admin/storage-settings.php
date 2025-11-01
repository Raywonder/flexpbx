<?php
require_once __DIR__ . '/admin_auth_check.php';
/**
 * FlexPBX Admin Portal - Storage Settings
 * Configure storage locations across multiple drives
 */

session_start();

// Check authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /admin/login.php');
    exit;
}

$admin_username = $_SESSION['admin_username'] ?? 'Unknown';
$admin_role = $_SESSION['admin_role'] ?? 'admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Storage Settings - FlexPBX Admin Portal</title>
    <link rel="stylesheet" href="/css/style.css">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f5f7fa;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .header {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .header h1 {
            margin: 0 0 0.5rem 0;
            color: #2c3e50;
        }

        .header .subtitle {
            color: #666;
            font-size: 0.95rem;
        }

        .admin-badge {
            display: inline-block;
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
            color: white;
            padding: 0.3rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 700;
            margin-left: 0.5rem;
        }

        .card {
            background: white;
            border-radius: 8px;
            padding: 2rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .card h2 {
            margin: 0 0 1.5rem 0;
            color: #2c3e50;
            font-size: 1.3rem;
        }

        .storage-type {
            margin-bottom: 2rem;
            padding: 1.5rem;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
        }

        .storage-type-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .storage-type-title {
            font-weight: 600;
            color: #2c3e50;
            font-size: 1.1rem;
        }

        .storage-type-icon {
            font-size: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #2c3e50;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            font-size: 0.95rem;
            font-family: inherit;
            transition: border-color 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: #2196f3;
        }

        .path-input-group {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .path-input-group input {
            flex: 1;
        }

        .btn {
            display: inline-block;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
        }

        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.85rem;
        }

        .btn-primary {
            background: #2196f3;
            color: white;
        }

        .btn-primary:hover:not(:disabled) {
            background: #1976d2;
        }

        .btn-primary:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        .btn-success {
            background: #4CAF50;
            color: white;
        }

        .btn-success:hover {
            background: #45a049;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn-danger {
            background: #f44336;
            color: white;
        }

        .btn-danger:hover {
            background: #d32f2f;
        }

        .secondary-paths {
            margin-top: 1rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 6px;
        }

        .secondary-path-item {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .path-status {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 0.5rem;
        }

        .path-status-ok {
            background: #4CAF50;
            color: white;
        }

        .path-status-error {
            background: #f44336;
            color: white;
        }

        .path-status-testing {
            background: #ff9800;
            color: white;
        }

        .drives-list {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
        }

        .drive-item {
            padding: 0.75rem;
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            margin-bottom: 0.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .drive-info {
            flex: 1;
        }

        .drive-path {
            font-weight: 600;
            color: #2c3e50;
        }

        .drive-stats {
            font-size: 0.85rem;
            color: #666;
            margin-top: 0.25rem;
        }

        .usage-bar {
            height: 6px;
            background: #e0e0e0;
            border-radius: 3px;
            overflow: hidden;
            margin-top: 0.5rem;
        }

        .usage-fill {
            height: 100%;
            background: #4CAF50;
            transition: width 0.3s;
        }

        .usage-fill.warning {
            background: #ff9800;
        }

        .usage-fill.danger {
            background: #f44336;
        }

        .actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
        }

        .alert {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
        }

        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .alert-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .alert-info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .checkbox-group input[type="checkbox"] {
            cursor: pointer;
        }

        .checkbox-group label {
            cursor: pointer;
            margin: 0;
            font-weight: normal;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>💾 Storage Settings</h1>
            <p class="subtitle">
                Admin: <?= htmlspecialchars($admin_username) ?>
                <span class="admin-badge"><?= strtoupper(htmlspecialchars($admin_role)) ?></span>
            </p>
        </div>

        <div id="alert-container"></div>

        <!-- Available Drives -->
        <div class="card">
            <h2>📊 Available Drives & Mount Points</h2>
            <div id="drives-list">
                <p style="text-align: center; color: #999;">Loading drives...</p>
            </div>
        </div>

        <!-- Storage Configuration -->
        <div class="card">
            <h2>⚙️ Configure Storage Locations</h2>

            <div class="alert alert-info">
                ℹ️ Configure where different types of data are stored. You can set primary and secondary (backup) locations for each type.
            </div>

            <form id="storage-form" onsubmit="saveConfiguration(event)">
                <div id="storage-types-container">
                    <p style="text-align: center; color: #999;">Loading configuration...</p>
                </div>

                <div class="actions">
                    <a href="/admin/dashboard.html" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary" id="save-btn">
                        💾 Save Configuration
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let config = null;
        let drives = [];

        // Load on page load
        document.addEventListener('DOMContentLoaded', () => {
            loadDrives();
            loadConfiguration();
        });

        // Load available drives
        async function loadDrives() {
            try {
                const response = await fetch('/api/storage-config.php?action=list_drives');
                const data = await response.json();

                if (data.success) {
                    drives = data.drives;
                    renderDrives();
                }
            } catch (error) {
                console.error('Failed to load drives:', error);
            }
        }

        // Render drives list
        function renderDrives() {
            const container = document.getElementById('drives-list');

            if (drives.length === 0) {
                container.innerHTML = '<p style="text-align: center; color: #999;">No drives found</p>';
                return;
            }

            let html = '';
            drives.forEach(drive => {
                const usageClass = drive.use_percent > 90 ? 'danger' : (drive.use_percent > 75 ? 'warning' : '');

                html += `
                    <div class="drive-item">
                        <div class="drive-info">
                            <div class="drive-path">
                                ${drive.mount_point}
                                ${drive.writable ? '<span class="path-status path-status-ok">Writable</span>' : '<span class="path-status path-status-error">Read-Only</span>'}
                            </div>
                            <div class="drive-stats">
                                ${drive.used} used of ${drive.size} (${drive.available} available)
                            </div>
                            <div class="usage-bar">
                                <div class="usage-fill ${usageClass}" style="width: ${drive.use_percent}%"></div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-primary" onclick="useDrive('${drive.mount_point}')">
                            Use This Drive
                        </button>
                    </div>
                `;
            });

            container.innerHTML = html;
        }

        // Use a drive (copy path to clipboard for easy pasting)
        function useDrive(path) {
            navigator.clipboard.writeText(path).then(() => {
                showAlert(`✓ Path copied to clipboard: ${path}`, 'success');
            });
        }

        // Load configuration
        async function loadConfiguration() {
            try {
                const response = await fetch('/api/storage-config.php?action=get_config');
                const data = await response.json();

                if (data.success) {
                    config = data.config;
                    renderConfiguration();
                }
            } catch (error) {
                console.error('Failed to load configuration:', error);
            }
        }

        // Render configuration
        function renderConfiguration() {
            const container = document.getElementById('storage-types-container');

            const types = {
                'backups': { icon: '💾', title: 'Backups & Archives' },
                'encryption': { icon: '🔐', title: 'Encryption Keys & Crypto Storage' },
                'recordings': { icon: '🎙️', title: 'Call Recordings' },
                'voicemail': { icon: '📧', title: 'Voicemail Files' },
                'logs': { icon: '📜', title: 'System Logs' },
                'temp': { icon: '⏱️', title: 'Temporary Files' }
            };

            let html = '';

            Object.keys(types).forEach(type => {
                const typeConfig = config.storage_locations[type] || {};
                const typeInfo = types[type];

                html += `
                    <div class="storage-type">
                        <div class="storage-type-header">
                            <div>
                                <span class="storage-type-icon">${typeInfo.icon}</span>
                                <span class="storage-type-title">${typeInfo.title}</span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Primary Storage Path</label>
                            <div class="path-input-group">
                                <input
                                    type="text"
                                    class="form-control"
                                    id="${type}-primary"
                                    value="${typeConfig.primary || ''}"
                                    placeholder="/path/to/${type}"
                                    required
                                >
                                <button type="button" class="btn btn-success btn-sm" onclick="testPath('${type}', 'primary')">
                                    Test Path
                                </button>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="checkbox-group">
                                <input type="checkbox" id="${type}-auto-rotate" ${typeConfig.auto_rotate ? 'checked' : ''}>
                                <label for="${type}-auto-rotate">Enable automatic rotation to secondary locations when full</label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Maximum Size (GB)</label>
                            <input
                                type="number"
                                class="form-control"
                                id="${type}-max-size"
                                value="${typeConfig.max_size_gb || 100}"
                                min="1"
                                max="10000"
                                placeholder="100"
                            >
                        </div>

                        <div class="secondary-paths">
                            <label>Secondary Storage Locations (Optional)</label>
                            <div id="${type}-secondary-list">
                                ${(typeConfig.secondary || []).map((path, index) => `
                                    <div class="secondary-path-item">
                                        <input type="text" class="form-control" value="${path}" data-type="${type}" data-index="${index}">
                                        <button type="button" class="btn btn-danger btn-sm" onclick="removeSecondary('${type}', ${index})">Remove</button>
                                    </div>
                                `).join('')}
                            </div>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="addSecondary('${type}')">
                                ➕ Add Secondary Location
                            </button>
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;
        }

        // Test path
        async function testPath(type, pathType) {
            const inputId = `${type}-${pathType}`;
            const path = document.getElementById(inputId).value.trim();

            if (!path) {
                showAlert('Please enter a path first', 'error');
                return;
            }

            const input = document.getElementById(inputId);
            const originalValue = input.value;

            input.value = 'Testing...';
            input.disabled = true;

            try {
                const response = await fetch('/api/storage-config.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'test_path',
                        path: path
                    })
                });

                const data = await response.json();

                if (data.success) {
                    if (data.writable || data.can_create) {
                        showAlert(`✓ Path OK: ${path} (${data.disk_space.free} free)`, 'success');
                    } else {
                        showAlert(`⚠️ Path not writable: ${path}`, 'error');
                    }
                } else {
                    showAlert(`Error testing path: ${data.error}`, 'error');
                }
            } catch (error) {
                console.error('Failed to test path:', error);
                showAlert('Failed to test path', 'error');
            } finally {
                input.value = originalValue;
                input.disabled = false;
            }
        }

        // Add secondary location
        function addSecondary(type) {
            const container = document.getElementById(`${type}-secondary-list`);
            const index = container.children.length;

            const div = document.createElement('div');
            div.className = 'secondary-path-item';
            div.innerHTML = `
                <input type="text" class="form-control" placeholder="/path/to/secondary" data-type="${type}" data-index="${index}">
                <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()">Remove</button>
            `;

            container.appendChild(div);
        }

        // Remove secondary location
        function removeSecondary(type, index) {
            const container = document.getElementById(`${type}-secondary-list`);
            if (container.children[index]) {
                container.children[index].remove();
            }
        }

        // Save configuration
        async function saveConfiguration(event) {
            event.preventDefault();

            const saveBtn = document.getElementById('save-btn');
            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving...';

            const types = ['backups', 'encryption', 'recordings', 'voicemail', 'logs', 'temp'];
            const storage_locations = {};

            types.forEach(type => {
                const primary = document.getElementById(`${type}-primary`).value.trim();
                const auto_rotate = document.getElementById(`${type}-auto-rotate`).checked;
                const max_size_gb = parseInt(document.getElementById(`${type}-max-size`).value);

                // Get secondary paths
                const secondaryContainer = document.getElementById(`${type}-secondary-list`);
                const secondary = Array.from(secondaryContainer.querySelectorAll('input[type="text"]'))
                    .map(input => input.value.trim())
                    .filter(path => path.length > 0);

                storage_locations[type] = {
                    primary: primary,
                    secondary: secondary,
                    auto_rotate: auto_rotate,
                    max_size_gb: max_size_gb
                };
            });

            try {
                const response = await fetch('/api/storage-config.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'update_config',
                        storage_locations: storage_locations,
                        notifications: {
                            notify_on_full: true,
                            notify_threshold: 90
                        }
                    })
                });

                const data = await response.json();

                if (data.success) {
                    showAlert('✓ Storage configuration saved successfully', 'success');
                    config = data.config;
                } else {
                    showAlert('Failed to save: ' + (data.validation_errors || [data.error]).join(', '), 'error');
                }
            } catch (error) {
                console.error('Failed to save configuration:', error);
                showAlert('Failed to save configuration', 'error');
            } finally {
                saveBtn.disabled = false;
                saveBtn.textContent = '💾 Save Configuration';
            }
        }

        // Show alert
        function showAlert(message, type) {
            const container = document.getElementById('alert-container');
            const alertClass = type === 'success' ? 'alert-success' : (type === 'error' ? 'alert-error' : 'alert-info');

            const alertDiv = document.createElement('div');
            alertDiv.className = `alert ${alertClass}`;
            alertDiv.textContent = message;

            container.appendChild(alertDiv);

            // Auto-hide after 5 seconds
            setTimeout(() => {
                alertDiv.remove();
            }, 5000);
        }
    </script>
</body>
</html>
