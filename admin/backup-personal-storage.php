<?php
/**
 * FlexPBX Personal Remote Storage Configuration
 * Configure your Devine Creations Web Server as a backup destination
 *
 * @package FlexPBX
 */

require_once dirname(__FILE__) . '/../includes/auth.php';

// Require login
requireLogin();

$config_file = dirname(__FILE__) . '/../config/backup-config.json';
$config = json_decode(file_get_contents($config_file), true);

$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'save_config':
            $config['storage_options']['personal_remote'] = [
                'enabled' => isset($_POST['enabled']),
                'host' => $_POST['host'] ?? '',
                'port' => (int)($_POST['port'] ?? 3780),
                'use_ssl' => isset($_POST['use_ssl']),
                'license_key' => $_POST['license_key'] ?? '',
                'auto_backup' => isset($_POST['auto_backup']),
                'backup_format' => $_POST['backup_format'] ?? 'flxx'
            ];

            file_put_contents($config_file, json_encode($config, JSON_PRETTY_PRINT));
            $message = 'Personal Remote Storage settings saved';
            break;

        case 'test_connection':
            require_once dirname(__FILE__) . '/../modules/backup/PersonalRemoteStorage.php';

            $storage = new PersonalRemoteStorage([
                'host' => $_POST['host'] ?? '',
                'port' => (int)($_POST['port'] ?? 3780),
                'use_ssl' => isset($_POST['use_ssl']),
                'license_key' => $_POST['license_key'] ?? ''
            ]);

            $result = $storage->verifyConnection();

            if ($result['connected']) {
                $message = "Connected! Server version: {$result['server_version']}, Storage available: {$result['storage_available_formatted']}";
                if (!$result['license_valid']) {
                    $error = "Warning: License not validated for backup storage feature";
                }
            } else {
                $error = "Connection failed. Check your settings and ensure Devine Creations Web Server is running.";
            }
            break;
    }
}

$personal_config = $config['storage_options']['personal_remote'] ?? [];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Personal Remote Storage - FlexPBX</title>
    <link rel="stylesheet" href="../css/admin.css">
    <style>
        .config-card { background: #fff; border-radius: 8px; padding: 25px; margin: 15px 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .config-card h2 { margin-top: 0; color: #333; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 500; color: #555; }
        .form-group input[type="text"], .form-group input[type="number"], .form-group select {
            width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;
        }
        .form-group input:focus, .form-group select:focus { border-color: #4CAF50; outline: none; }
        .form-row { display: grid; grid-template-columns: 2fr 1fr; gap: 15px; }
        .checkbox-group { margin: 15px 0; }
        .checkbox-group label { display: flex; align-items: center; gap: 8px; cursor: pointer; }
        .btn { padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; }
        .btn-primary { background: #4CAF50; color: #fff; }
        .btn-secondary { background: #666; color: #fff; }
        .btn-test { background: #2196F3; color: #fff; }
        .alert { padding: 15px; border-radius: 4px; margin-bottom: 15px; }
        .alert-success { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
        .alert-error { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }
        .info-box { background: #e3f2fd; border: 1px solid #90caf9; border-radius: 4px; padding: 15px; margin-bottom: 20px; }
        .info-box h4 { margin-top: 0; color: #1565c0; }
        .steps-list { counter-reset: step; padding-left: 0; }
        .steps-list li { list-style: none; counter-increment: step; padding: 8px 0 8px 35px; position: relative; }
        .steps-list li::before {
            content: counter(step);
            position: absolute; left: 0; top: 8px;
            background: #4CAF50; color: #fff;
            width: 24px; height: 24px;
            border-radius: 50%; text-align: center; line-height: 24px; font-size: 12px;
        }
        .download-btn { display: inline-block; padding: 12px 24px; background: #4CAF50; color: #fff; text-decoration: none; border-radius: 4px; margin: 5px; }
        .download-btn:hover { background: #45a049; }
        .platform-icon { font-size: 20px; margin-right: 8px; }
    </style>
</head>
<body>
    <div class="admin-container">
        <h1>Personal Remote Storage</h1>
        <p>Store FlexPBX backups on your own Windows or Mac computer using the Devine Creations Web Server app.</p>

        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Getting Started -->
        <div class="config-card">
            <h2>Getting Started</h2>

            <div class="info-box">
                <h4>What is Personal Remote Storage?</h4>
                <p>Personal Remote Storage allows you to store your FlexPBX backups on your own computer instead of cloud storage. Your backups stay on your local network, giving you full control over your data.</p>
            </div>

            <h3>How to Set Up</h3>
            <ol class="steps-list">
                <li>Download and install the <strong>Devine Creations Web Server</strong> app on your Windows or Mac computer</li>
                <li>Launch the app and note your computer's IP address and port (shown in the app)</li>
                <li>Enter a valid license key in the app (required for backup storage feature)</li>
                <li>Enter the connection details below and click "Test Connection"</li>
                <li>Once connected, enable Personal Remote Storage and save your settings</li>
            </ol>

            <h3>Download Devine Creations Web Server</h3>
            <div style="margin: 15px 0;">
                <a href="https://devine-creations.com/downloads/devine-webserver/Devine%20Creations%20Web%20Server-1.0.0.dmg" class="download-btn">
                    <span class="platform-icon"></span> macOS (Intel)
                </a>
                <a href="https://devine-creations.com/downloads/devine-webserver/Devine%20Creations%20Web%20Server-1.0.0-arm64.dmg" class="download-btn">
                    <span class="platform-icon"></span> macOS (Apple Silicon)
                </a>
                <a href="https://devine-creations.com/downloads/devine-webserver/Devine%20Creations%20Web%20Server%20Setup%201.0.0.exe" class="download-btn">
                    <span class="platform-icon"></span> Windows
                </a>
            </div>
        </div>

        <!-- Configuration -->
        <div class="config-card">
            <h2>Connection Settings</h2>

            <form method="post" id="storage-form">
                <input type="hidden" name="action" value="save_config">

                <div class="checkbox-group">
                    <label>
                        <input type="checkbox" name="enabled" <?php echo ($personal_config['enabled'] ?? false) ? 'checked' : ''; ?>>
                        <strong>Enable Personal Remote Storage</strong>
                    </label>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Server Address (IP or Hostname)</label>
                        <input type="text" name="host" value="<?php echo htmlspecialchars($personal_config['host'] ?? ''); ?>"
                            placeholder="e.g., 192.168.1.100 or my-mac.local">
                        <small style="color: #666;">Find this in the Devine Creations Web Server app under "Network"</small>
                    </div>
                    <div class="form-group">
                        <label>Port</label>
                        <input type="number" name="port" value="<?php echo $personal_config['port'] ?? 3780; ?>"
                            placeholder="3780">
                    </div>
                </div>

                <div class="form-group">
                    <label>License Key</label>
                    <input type="text" name="license_key" value="<?php echo htmlspecialchars($personal_config['license_key'] ?? ''); ?>"
                        placeholder="Enter your Devine Creations license key">
                    <small style="color: #666;">Your license must include the "Backup Storage" feature. <a href="https://devine-creations.com/licenses" target="_blank">Get a license</a></small>
                </div>

                <div class="checkbox-group">
                    <label>
                        <input type="checkbox" name="use_ssl" <?php echo ($personal_config['use_ssl'] ?? false) ? 'checked' : ''; ?>>
                        Use HTTPS (SSL) connection
                    </label>
                    <small style="color: #666; display: block; margin-left: 26px;">Enable if your Web Server is configured with SSL</small>
                </div>

                <hr style="margin: 20px 0;">

                <h3>Backup Settings</h3>

                <div class="checkbox-group">
                    <label>
                        <input type="checkbox" name="auto_backup" <?php echo ($personal_config['auto_backup'] ?? false) ? 'checked' : ''; ?>>
                        Automatically upload backups to Personal Remote Storage
                    </label>
                </div>

                <div class="form-group">
                    <label>Default Backup Format</label>
                    <select name="backup_format">
                        <option value="flx" <?php echo ($personal_config['backup_format'] ?? 'flxx') === 'flx' ? 'selected' : ''; ?>>
                            Config Only (.flx) - Smaller, faster backups
                        </option>
                        <option value="flxx" <?php echo ($personal_config['backup_format'] ?? 'flxx') === 'flxx' ? 'selected' : ''; ?>>
                            Full System (.flxx) - Complete backup including recordings
                        </option>
                    </select>
                </div>

                <div style="margin-top: 20px; display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary">Save Settings</button>
                    <button type="button" class="btn btn-test" onclick="testConnection()">Test Connection</button>
                </div>
            </form>
        </div>

        <!-- Status -->
        <div class="config-card" id="status-card" style="display: <?php echo ($personal_config['enabled'] ?? false) ? 'block' : 'none'; ?>;">
            <h2>Storage Status</h2>
            <div id="status-content">
                <p>Checking connection...</p>
            </div>
        </div>
    </div>

    <script>
        function testConnection() {
            var form = document.getElementById('storage-form');
            var formData = new FormData(form);
            formData.set('action', 'test_connection');

            fetch('backup-personal-storage.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.text())
            .then(html => {
                // Reload page to show result
                location.reload();
            })
            .catch(err => {
                alert('Connection test failed: ' + err.message);
            });
        }

        // Check status on page load if enabled
        document.addEventListener('DOMContentLoaded', function() {
            var enabled = document.querySelector('input[name="enabled"]').checked;
            if (enabled) {
                checkStatus();
            }
        });

        function checkStatus() {
            var host = document.querySelector('input[name="host"]').value;
            var port = document.querySelector('input[name="port"]').value;
            var license = document.querySelector('input[name="license_key"]').value;

            if (!host || !license) {
                document.getElementById('status-content').innerHTML = '<p style="color: #666;">Enter connection details and save to see status.</p>';
                return;
            }

            fetch('backup-api.php?action=personal_storage_status')
            .then(r => r.json())
            .then(data => {
                if (data.connected) {
                    document.getElementById('status-content').innerHTML = `
                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px;">
                            <div style="text-align: center; padding: 15px; background: #f5f5f5; border-radius: 8px;">
                                <div style="font-size: 24px; font-weight: bold; color: #4CAF50;">${data.backup_count || 0}</div>
                                <div style="font-size: 12px; color: #666;">Backups Stored</div>
                            </div>
                            <div style="text-align: center; padding: 15px; background: #f5f5f5; border-radius: 8px;">
                                <div style="font-size: 24px; font-weight: bold; color: #2196F3;">${data.used_formatted || '0 B'}</div>
                                <div style="font-size: 12px; color: #666;">Space Used</div>
                            </div>
                            <div style="text-align: center; padding: 15px; background: #f5f5f5; border-radius: 8px;">
                                <div style="font-size: 24px; font-weight: bold; color: #666;">${data.available_formatted || 'Unknown'}</div>
                                <div style="font-size: 12px; color: #666;">Available</div>
                            </div>
                        </div>
                        <p style="margin-top: 15px; color: #4CAF50;">Connected to ${data.server_name || host}</p>
                    `;
                } else {
                    document.getElementById('status-content').innerHTML = '<p style="color: #f44336;">Not connected. Check your settings.</p>';
                }
            })
            .catch(err => {
                document.getElementById('status-content').innerHTML = '<p style="color: #f44336;">Unable to check status.</p>';
            });
        }
    </script>
</body>
</html>
