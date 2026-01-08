<?php
/**
 * FlexPBX Backup Administration Page v2
 * Supports .flx (config) and .flxx (full system) backup formats
 *
 * @package FlexPBX
 */

require_once dirname(__FILE__) . '/../modules/backup/FlexPBX_Backup.php';
require_once dirname(__FILE__) . '/../includes/auth.php';

// Require login
requireLogin();

$backup = new FlexPBX_Backup();
$config = json_decode(file_get_contents(dirname(__FILE__) . '/../config/backup-config.json'), true);

// Handle actions
$message = '';
$error = '';
$action_result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'create_backup':
            $format = $_POST['format'] ?? 'flxx';
            $options = [
                'components' => $_POST['components'] ?? null,
                'upload_remote' => isset($_POST['upload_remote'])
            ];
            $result = $backup->createBackup($format, $options);
            if ($result['status'] ?? false) {
                $message = "Backup created: " . basename($result['archive_path']) . " ({$result['size_formatted']})";
            } else {
                $error = "Backup failed: " . ($result['message'] ?? 'Unknown error');
            }
            break;

        case 'restore_backup':
            $path = $_POST['backup_path'] ?? '';
            $options = [
                'components' => $_POST['restore_components'] ?? null,
                'backup_existing' => isset($_POST['backup_existing'])
            ];
            $result = $backup->restoreBackup($path, $options);
            if ($result['status']) {
                $message = "Restore completed. Components: " . implode(', ', array_keys($result['restored_components']));
                if ($result['asterisk_restarted'] ?? false) {
                    $message .= " (Asterisk restarted)";
                }
            } else {
                $error = "Restore failed: " . ($result['error'] ?? 'Unknown error');
            }
            break;

        case 'delete_backup':
            $path = $_POST['backup_path'] ?? '';
            $result = $backup->deleteBackup($path);
            if ($result['status']) {
                $message = "Backup deleted successfully";
            } else {
                $error = "Delete failed: " . ($result['error'] ?? 'Unknown error');
            }
            break;

        case 'schedule_backup':
            $format = $_POST['schedule_format'] ?? 'flxx';
            $schedule = $_POST['schedule_frequency'] ?? 'daily';
            $options = ['upload_remote' => isset($_POST['schedule_upload_remote'])];
            $result = $backup->scheduleBackup($format, $schedule, $options);
            if ($result['status']) {
                $message = "Backup scheduled: {$schedule}";
            }
            break;
    }
}

$backups = $backup->listBackups();
$stats = $backup->getStorageStats();
$remote_config = $config['storage_options']['remote_cloud'] ?? [];
$backup_contents = $config['backup_contents'] ?? [];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Backup Management - FlexPBX</title>
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .backup-card { background: #fff; border-radius: 8px; padding: 20px; margin: 15px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .backup-card h2 { margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .format-tabs { display: flex; gap: 10px; margin-bottom: 15px; }
        .format-tab { padding: 10px 20px; border: 2px solid #ddd; border-radius: 6px; cursor: pointer; transition: all 0.2s; }
        .format-tab:hover { border-color: #4CAF50; }
        .format-tab.active { border-color: #4CAF50; background: #e8f5e9; }
        .format-tab .badge { font-size: 10px; padding: 2px 6px; border-radius: 3px; margin-left: 5px; }
        .format-tab .badge.config { background: #2196F3; color: #fff; }
        .format-tab .badge.full { background: #4CAF50; color: #fff; }
        .storage-bar { background: #e0e0e0; border-radius: 4px; height: 24px; overflow: hidden; position: relative; }
        .storage-used { height: 100%; transition: width 0.3s; display: flex; align-items: center; justify-content: flex-end; padding-right: 8px; color: #fff; font-size: 12px; }
        .storage-used.flx { background: #2196F3; }
        .storage-used.flxx { background: #4CAF50; }
        .component-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 10px; }
        .component-item { padding: 10px; border: 1px solid #ddd; border-radius: 4px; }
        .component-item.config { border-left: 3px solid #2196F3; }
        .component-item.data { border-left: 3px solid #4CAF50; }
        .component-item label { display: flex; align-items: center; gap: 8px; cursor: pointer; }
        .backup-table { width: 100%; border-collapse: collapse; }
        .backup-table th, .backup-table td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        .backup-table tr:hover { background: #f9f9f9; }
        .btn { padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; }
        .btn-primary { background: #4CAF50; color: #fff; }
        .btn-secondary { background: #666; color: #fff; }
        .btn-warning { background: #ff9800; color: #fff; }
        .btn-danger { background: #f44336; color: #fff; }
        .btn-sm { padding: 4px 8px; font-size: 12px; }
        .alert { padding: 15px; border-radius: 4px; margin-bottom: 15px; }
        .alert-success { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
        .alert-error { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }
        .modal { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; z-index: 1000; }
        .modal.active { display: flex; }
        .modal-content { background: #fff; border-radius: 8px; padding: 25px; max-width: 600px; width: 90%; max-height: 80vh; overflow-y: auto; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
        .modal-close { background: none; border: none; font-size: 24px; cursor: pointer; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; }
        .stat-box { text-align: center; padding: 15px; background: #f5f5f5; border-radius: 8px; }
        .stat-box .value { font-size: 24px; font-weight: bold; color: #333; }
        .stat-box .label { font-size: 12px; color: #666; }
        .format-badge { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: bold; }
        .format-badge.flx { background: #2196F3; color: #fff; }
        .format-badge.flxx { background: #4CAF50; color: #fff; }
        .schedule-options { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin: 15px 0; }
        .schedule-option { padding: 10px; border: 2px solid #ddd; border-radius: 6px; text-align: center; cursor: pointer; }
        .schedule-option:hover, .schedule-option.selected { border-color: #4CAF50; background: #e8f5e9; }
    </style>
</head>
<body>
    <div class="admin-container">
        <h1>Backup Management</h1>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Storage Overview -->
        <div class="backup-card">
            <h2>Storage Overview</h2>
            <div class="stats-grid">
                <div class="stat-box">
                    <div class="value"><?php echo $stats['local']['total_count']; ?></div>
                    <div class="label">Total Backups</div>
                </div>
                <div class="stat-box">
                    <div class="value"><?php echo $stats['local']['flx']['count']; ?></div>
                    <div class="label">Config (.flx)</div>
                </div>
                <div class="stat-box">
                    <div class="value"><?php echo $stats['local']['flxx']['count']; ?></div>
                    <div class="label">Full System (.flxx)</div>
                </div>
                <div class="stat-box">
                    <div class="value"><?php echo $stats['local']['total_size_formatted']; ?></div>
                    <div class="label">Total Size</div>
                </div>
            </div>

            <h3 style="margin-top: 20px;">Local Storage</h3>
            <div class="storage-bar" style="margin-bottom: 10px;">
                <?php
                $flx_percent = $stats['local']['total_size'] > 0 ? ($stats['local']['flx']['size'] / $stats['local']['total_size']) * 100 : 0;
                $flxx_percent = $stats['local']['total_size'] > 0 ? ($stats['local']['flxx']['size'] / $stats['local']['total_size']) * 100 : 0;
                ?>
                <div class="storage-used flx" style="width: <?php echo $flx_percent; ?>%; position: absolute;">
                    <?php if ($flx_percent > 10): ?><?php echo $stats['local']['flx']['size_formatted']; ?><?php endif; ?>
                </div>
                <div class="storage-used flxx" style="width: <?php echo $flxx_percent; ?>%; margin-left: <?php echo $flx_percent; ?>%;">
                    <?php if ($flxx_percent > 10): ?><?php echo $stats['local']['flxx']['size_formatted']; ?><?php endif; ?>
                </div>
            </div>
            <div style="display: flex; gap: 20px; font-size: 12px;">
                <span><span style="display: inline-block; width: 12px; height: 12px; background: #2196F3; border-radius: 2px;"></span> Config (.flx): <?php echo $stats['local']['flx']['size_formatted']; ?></span>
                <span><span style="display: inline-block; width: 12px; height: 12px; background: #4CAF50; border-radius: 2px;"></span> Full (.flxx): <?php echo $stats['local']['flxx']['size_formatted']; ?></span>
            </div>

            <?php if ($remote_config['enabled'] ?? false): ?>
            <h3 style="margin-top: 20px;">Remote Storage</h3>
            <p>Plan: <strong><?php echo ucfirst($remote_config['current_plan'] ?? 'Free'); ?></strong></p>
            <div class="storage-bar">
                <div class="storage-used flxx" style="width: <?php echo $stats['remote']['percent_used'] ?? 0; ?>%;">
                    <?php echo $stats['remote']['used_formatted'] ?? '0 B'; ?> / <?php echo $stats['remote']['limit_formatted'] ?? '5 GB'; ?>
                </div>
            </div>
            <?php else: ?>
            <div style="margin-top: 20px; padding: 15px; background: #fff3e0; border-radius: 4px;">
                <strong>Remote Storage Available</strong>
                <p style="margin: 5px 0;">Enable cloud backup storage for off-site protection.</p>
                <a href="backup-subscribe.php" class="btn btn-primary">Enable Remote Storage</a>
            </div>
            <?php endif; ?>
        </div>

        <!-- Create Backup -->
        <div class="backup-card">
            <h2>Create New Backup</h2>
            <form method="post" id="backup-form">
                <input type="hidden" name="action" value="create_backup">
                <input type="hidden" name="format" id="backup-format" value="flxx">

                <h4>Backup Format</h4>
                <div class="format-tabs">
                    <div class="format-tab" data-format="flx" onclick="selectFormat('flx')">
                        <strong>Config Only</strong> <span class="badge config">.flx</span>
                        <div style="font-size: 12px; color: #666;">Asterisk config, database, app settings</div>
                    </div>
                    <div class="format-tab active" data-format="flxx" onclick="selectFormat('flxx')">
                        <strong>Full System</strong> <span class="badge full">.flxx</span>
                        <div style="font-size: 12px; color: #666;">Everything including recordings, voicemail, sounds</div>
                    </div>
                </div>

                <h4>Components to Include</h4>
                <div class="component-grid">
                    <?php
                    $config_keys = ['asterisk_config', 'flexpbx_app', 'database', 'cdr'];
                    foreach ($backup_contents as $key => $content):
                        $is_config = in_array($key, $config_keys);
                    ?>
                    <div class="component-item <?php echo $is_config ? 'config' : 'data'; ?>">
                        <label>
                            <input type="checkbox" name="components[]" value="<?php echo $key; ?>"
                                class="component-checkbox <?php echo $is_config ? 'config-component' : 'data-component'; ?>"
                                <?php echo ($content['enabled'] ?? false) ? 'checked' : ''; ?>
                                <?php echo ($content['required'] ?? false) ? 'disabled checked' : ''; ?>>
                            <?php echo htmlspecialchars($content['description']); ?>
                            <?php if ($content['requires_plan'] ?? false): ?>
                                <span style="font-size: 10px; color: #ff9800;">(<?php echo ucfirst($content['requires_plan']); ?>)</span>
                            <?php endif; ?>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div style="margin-top: 15px;">
                    <label>
                        <input type="checkbox" name="upload_remote" <?php echo ($remote_config['enabled'] ?? false) ? '' : 'disabled'; ?>>
                        Upload to remote storage <?php echo ($remote_config['enabled'] ?? false) ? '' : '(not enabled)'; ?>
                    </label>
                </div>

                <div style="margin-top: 20px;">
                    <button type="submit" class="btn btn-primary">Create Backup</button>
                </div>
            </form>
        </div>

        <!-- Schedule Automated Backups -->
        <div class="backup-card">
            <h2>Scheduled Backups</h2>
            <form method="post">
                <input type="hidden" name="action" value="schedule_backup">

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div>
                        <h4>Backup Format</h4>
                        <select name="schedule_format" class="form-control" style="width: 100%; padding: 8px;">
                            <option value="flx">Config Only (.flx)</option>
                            <option value="flxx" selected>Full System (.flxx)</option>
                        </select>
                    </div>
                    <div>
                        <h4>Frequency</h4>
                        <select name="schedule_frequency" class="form-control" style="width: 100%; padding: 8px;">
                            <option value="hourly">Hourly</option>
                            <option value="daily" selected>Daily (2:00 AM)</option>
                            <option value="weekly">Weekly (Sunday 2:00 AM)</option>
                            <option value="monthly">Monthly (1st, 2:00 AM)</option>
                        </select>
                    </div>
                </div>

                <div style="margin-top: 15px;">
                    <label>
                        <input type="checkbox" name="schedule_upload_remote" <?php echo ($remote_config['enabled'] ?? false) ? '' : 'disabled'; ?>>
                        Also upload to remote storage
                    </label>
                </div>

                <div style="margin-top: 15px;">
                    <button type="submit" class="btn btn-secondary">Save Schedule</button>
                </div>
            </form>
        </div>

        <!-- Existing Backups -->
        <div class="backup-card">
            <h2>Existing Backups</h2>

            <div style="margin-bottom: 15px;">
                <button class="btn btn-sm" onclick="filterBackups('all')">All</button>
                <button class="btn btn-sm" onclick="filterBackups('flx')">Config (.flx)</button>
                <button class="btn btn-sm" onclick="filterBackups('flxx')">Full (.flxx)</button>
            </div>

            <table class="backup-table">
                <thead>
                    <tr>
                        <th>Backup Name</th>
                        <th>Format</th>
                        <th>Size</th>
                        <th>Created</th>
                        <th>Components</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($backups)): ?>
                        <tr><td colspan="6" style="text-align: center; color: #666;">No backups found</td></tr>
                    <?php else: ?>
                        <?php foreach ($backups as $b): ?>
                            <tr class="backup-row" data-format="<?php echo $b['format']; ?>">
                                <td>
                                    <?php echo htmlspecialchars($b['name']); ?>
                                    <?php if ($b['hostname'] !== 'unknown'): ?>
                                        <div style="font-size: 11px; color: #666;"><?php echo htmlspecialchars($b['hostname']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="format-badge <?php echo $b['format']; ?>">
                                        <?php echo strtoupper($b['format']); ?>
                                    </span>
                                </td>
                                <td><?php echo $b['size_formatted']; ?></td>
                                <td><?php echo $b['created_formatted']; ?></td>
                                <td>
                                    <?php
                                    $comp_count = count($b['components'] ?? []);
                                    echo $comp_count . ' component' . ($comp_count !== 1 ? 's' : '');
                                    ?>
                                </td>
                                <td>
                                    <a href="backup-api.php?action=download&path=<?php echo urlencode($b['path']); ?>" class="btn btn-sm">Download</a>
                                    <button class="btn btn-sm btn-warning" onclick="showRestoreModal('<?php echo htmlspecialchars($b['path']); ?>', '<?php echo $b['format']; ?>')">Restore</button>
                                    <button class="btn btn-sm btn-danger" onclick="confirmDelete('<?php echo htmlspecialchars($b['path']); ?>')">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Restore Modal -->
    <div id="restore-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Restore Backup</h3>
                <button class="modal-close" onclick="closeRestoreModal()">&times;</button>
            </div>
            <form method="post" id="restore-form">
                <input type="hidden" name="action" value="restore_backup">
                <input type="hidden" name="backup_path" id="restore-path">

                <div class="alert alert-warning" style="background: #fff3e0; border-color: #ffcc80; color: #e65100;">
                    <strong>Warning:</strong> Restoring will overwrite existing configuration. A pre-restore backup will be created automatically.
                </div>

                <h4>Select Components to Restore</h4>
                <div id="restore-components" class="component-grid">
                    <!-- Populated by JavaScript -->
                </div>

                <div style="margin-top: 15px;">
                    <label>
                        <input type="checkbox" name="backup_existing" checked>
                        Create backup of existing files before restore
                    </label>
                </div>

                <div style="margin-top: 20px; display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-warning">Restore Selected</button>
                    <button type="button" class="btn btn-secondary" onclick="closeRestoreModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="delete-modal" class="modal">
        <div class="modal-content" style="max-width: 400px;">
            <h3>Confirm Delete</h3>
            <p>Are you sure you want to delete this backup? This cannot be undone.</p>
            <form method="post" id="delete-form">
                <input type="hidden" name="action" value="delete_backup">
                <input type="hidden" name="backup_path" id="delete-path">
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn btn-danger">Delete</button>
                    <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Backup components definitions
        var configComponents = ['asterisk_config', 'flexpbx_app', 'database', 'cdr'];
        var dataComponents = ['voicemail', 'moh', 'sounds', 'recordings', 'fax', 'system_logs'];

        var allComponents = {
            'asterisk_config': 'Asterisk Configuration',
            'flexpbx_app': 'FlexPBX Application',
            'database': 'Database',
            'cdr': 'Call Detail Records',
            'voicemail': 'Voicemail Messages',
            'moh': 'Music on Hold',
            'sounds': 'Custom Sounds',
            'recordings': 'Call Recordings',
            'fax': 'Fax Documents',
            'system_logs': 'System Logs'
        };

        function selectFormat(format) {
            document.getElementById('backup-format').value = format;

            // Update tab styling
            document.querySelectorAll('.format-tab').forEach(tab => {
                tab.classList.toggle('active', tab.dataset.format === format);
            });

            // Update component checkboxes
            document.querySelectorAll('.component-checkbox').forEach(cb => {
                var isConfig = cb.classList.contains('config-component');

                if (format === 'flx') {
                    // Config only - check config components, uncheck data
                    cb.checked = isConfig;
                } else {
                    // Full system - check all enabled by default
                    cb.checked = true;
                }
            });
        }

        function filterBackups(format) {
            document.querySelectorAll('.backup-row').forEach(row => {
                if (format === 'all' || row.dataset.format === format) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        function showRestoreModal(path, format) {
            document.getElementById('restore-path').value = path;

            // Build component checkboxes
            var container = document.getElementById('restore-components');
            container.innerHTML = '';

            var components = format === 'flx' ? configComponents : Object.keys(allComponents);

            components.forEach(function(key) {
                var isConfig = configComponents.includes(key);
                var div = document.createElement('div');
                div.className = 'component-item ' + (isConfig ? 'config' : 'data');
                div.innerHTML = '<label><input type="checkbox" name="restore_components[]" value="' + key + '" checked> ' + allComponents[key] + '</label>';
                container.appendChild(div);
            });

            document.getElementById('restore-modal').classList.add('active');
        }

        function closeRestoreModal() {
            document.getElementById('restore-modal').classList.remove('active');
        }

        function confirmDelete(path) {
            document.getElementById('delete-path').value = path;
            document.getElementById('delete-modal').classList.add('active');
        }

        function closeDeleteModal() {
            document.getElementById('delete-modal').classList.remove('active');
        }

        // Close modals on outside click
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>
