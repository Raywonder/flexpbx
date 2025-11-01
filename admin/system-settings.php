<?php
require_once __DIR__ . '/admin_auth_check.php';
/**
 * FlexPBX System Settings
 * Configure Asterisk integration mode and other system settings
 */

session_start();

// Simple auth check (replace with your actual auth)
$is_admin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

// Configuration file
$config_file = '/home/flexpbxuser/flexpbx-config.json';

// Load current config
$config = [];
if (file_exists($config_file)) {
    $config = json_decode(file_get_contents($config_file), true) ?: [];
}

// Default settings
$defaults = [
    'asterisk_mode' => 'secure', // 'secure' or 'power_user'
    'allow_config_writes' => false,
    'api_mode' => 'enabled',
    'debug_mode' => false
];

$config = array_merge($defaults, $config);

$message = '';
$message_type = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'update_mode') {
        $new_mode = $_POST['asterisk_mode'] ?? 'secure';
        $confirm = $_POST['confirm_change'] ?? '';

        if ($new_mode === 'power_user' && $confirm !== 'yes') {
            $message = 'You must check the confirmation box to enable Power User Mode.';
            $message_type = 'error';
        } else {
            $config['asterisk_mode'] = $new_mode;
            $config['allow_config_writes'] = ($new_mode === 'power_user');
            $config['last_modified'] = date('Y-m-d H:i:s');
            $config['modified_by'] = $_SESSION['admin_username'] ?? 'admin';

            // Save config
            if (file_put_contents($config_file, json_encode($config, JSON_PRETTY_PRINT))) {
                chmod($config_file, 0640);

                // Execute permission change script
                $script = '/usr/local/bin/flexpbx-toggle-permissions';
                $mode_arg = escapeshellarg($new_mode);
                exec("sudo $script $mode_arg 2>&1", $output, $return_var);

                if ($return_var === 0) {
                    $message = "Mode changed to: " . strtoupper($new_mode) . ". Permissions updated successfully.";
                    $message_type = 'success';
                } else {
                    $message = "Config saved but permission update failed. Output: " . implode("\n", $output);
                    $message_type = 'warning';
                }
            } else {
                $message = 'Failed to save configuration.';
                $message_type = 'error';
            }
        }
    }
}

// Get current permission status
$asterisk_conf_perms = fileperms('/etc/asterisk/extensions.conf');
$is_writable = (($asterisk_conf_perms & 0x0080) || ($asterisk_conf_perms & 0x0010)); // owner or group write
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - FlexPBX Admin</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
        }

        .header {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }

        h1 {
            color: #333;
            margin-bottom: 10px;
        }

        .subtitle {
            color: #666;
            font-size: 14px;
        }

        .card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
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

        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffc107;
        }

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .mode-option {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .mode-option:hover {
            border-color: #667eea;
            background: #f8f9ff;
        }

        .mode-option.active {
            border-color: #667eea;
            background: #f0f3ff;
        }

        .mode-option input[type="radio"] {
            margin-right: 10px;
        }

        .mode-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }

        .mode-description {
            color: #666;
            font-size: 14px;
            line-height: 1.5;
        }

        .mode-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
        }

        .badge-secure {
            background: #4ade80;
            color: white;
        }

        .badge-advanced {
            background: #f59e0b;
            color: white;
        }

        .warning-box {
            background: #fff3cd;
            border: 2px solid #ffc107;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }

        .warning-box h3 {
            color: #856404;
            margin-bottom: 10px;
        }

        .warning-box ul {
            margin-left: 20px;
            color: #856404;
        }

        .warning-box li {
            margin-bottom: 8px;
        }

        .checkbox-container {
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .checkbox-container label {
            display: flex;
            align-items: center;
            cursor: pointer;
        }

        .checkbox-container input[type="checkbox"] {
            margin-right: 10px;
            width: 20px;
            height: 20px;
        }

        .status-table {
            width: 100%;
            margin: 20px 0;
            border-collapse: collapse;
        }

        .status-table th,
        .status-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }

        .status-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }

        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }

        .status-active {
            background: #4ade80;
        }

        .status-inactive {
            background: #ef4444;
        }

        .btn {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }

        .btn-secondary {
            background: #6c757d;
        }

        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .back-link {
            text-align: center;
            margin-top: 20px;
        }

        .back-link a {
            color: white;
            text-decoration: none;
            opacity: 0.9;
        }

        .back-link a:hover {
            opacity: 1;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⚙️ System Settings</h1>
            <p class="subtitle">Configure Asterisk integration mode and system behavior</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <h2>Asterisk Integration Mode</h2>
            <p style="color: #666; margin: 10px 0 20px 0;">Choose how FlexPBX interacts with Asterisk configuration files.</p>

            <div class="alert alert-info">
                <strong>Current Status:</strong><br>
                Mode: <strong><?php echo strtoupper($config['asterisk_mode']); ?></strong><br>
                Config Files: <?php echo $is_writable ? '<strong>Writable</strong>' : '<strong>Read-Only</strong>'; ?>
            </div>

            <form method="POST" id="settingsForm">
                <input type="hidden" name="action" value="update_mode">

                <label class="mode-option <?php echo $config['asterisk_mode'] === 'secure' ? 'active' : ''; ?>" onclick="selectMode('secure')">
                    <input type="radio" name="asterisk_mode" value="secure" <?php echo $config['asterisk_mode'] === 'secure' ? 'checked' : ''; ?>>
                    <div>
                        <div class="mode-title">
                            🔒 Secure Mode (Recommended)
                            <span class="mode-badge badge-secure">DEFAULT</span>
                        </div>
                        <div class="mode-description">
                            <strong>How it works:</strong>
                            <ul style="margin-top: 8px; margin-left: 20px;">
                                <li>Asterisk config files are <strong>read-only</strong> (640 permissions)</li>
                                <li>FlexPBX uses <strong>API and CLI commands</strong> for changes</li>
                                <li>Changes made via Asterisk ARI/AMI interfaces</li>
                                <li>Automatic validation and reload</li>
                                <li><strong>More secure</strong> - no direct file manipulation</li>
                            </ul>
                            <strong style="display: block; margin-top: 10px;">Best for:</strong> Production systems, standard users, managed hosting
                        </div>
                    </div>
                </label>

                <label class="mode-option <?php echo $config['asterisk_mode'] === 'power_user' ? 'active' : ''; ?>" onclick="selectMode('power_user')">
                    <input type="radio" name="asterisk_mode" value="power_user" <?php echo $config['asterisk_mode'] === 'power_user' ? 'checked' : ''; ?>>
                    <div>
                        <div class="mode-title">
                            ⚡ Power User Mode
                            <span class="mode-badge badge-advanced">ADVANCED</span>
                        </div>
                        <div class="mode-description">
                            <strong>How it works:</strong>
                            <ul style="margin-top: 8px; margin-left: 20px;">
                                <li>Asterisk config files are <strong>group-writable</strong> (660 permissions)</li>
                                <li>FlexPBX can <strong>directly edit</strong> config files</li>
                                <li>Supports both API and direct file access</li>
                                <li>Manual reloads required after file edits</li>
                                <li>Full control over configuration</li>
                            </ul>
                            <strong style="display: block; margin-top: 10px;">Best for:</strong> Power users, development, full customization
                        </div>
                    </div>
                </label>

                <div id="warningBox" style="display: <?php echo $config['asterisk_mode'] === 'secure' ? 'none' : 'block'; ?>;">
                    <div class="warning-box">
                        <h3>⚠️ Warning: Power User Mode</h3>
                        <p>Enabling this mode will:</p>
                        <ul>
                            <li>Grant <strong>write access</strong> to Asterisk configuration files</li>
                            <li>Allow direct file editing (bypass validation)</li>
                            <li>Require manual reloads after changes</li>
                            <li>Potentially cause service disruption if misconfigured</li>
                            <li>Reduce security (web server can modify PBX configs)</li>
                        </ul>
                        <p style="margin-top: 15px;"><strong>Only enable this if you understand the risks and need direct file access.</strong></p>
                    </div>

                    <div class="checkbox-container">
                        <label>
                            <input type="checkbox" name="confirm_change" value="yes" id="confirmCheckbox">
                            <strong>I understand the risks and want to enable Power User Mode</strong>
                        </label>
                    </div>
                </div>

                <div class="btn-group">
                    <button type="submit" class="btn" id="saveButton">Save Changes</button>
                    <a href="dashboard.html" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>

        <div class="card">
            <h2>Current Permissions Status</h2>
            <table class="status-table">
                <thead>
                    <tr>
                        <th>File/Resource</th>
                        <th>Permissions</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>/etc/asterisk/extensions.conf</td>
                        <td><?php echo substr(sprintf('%o', $asterisk_conf_perms), -3); ?></td>
                        <td>
                            <span class="status-indicator <?php echo $is_writable ? 'status-active' : 'status-inactive'; ?>"></span>
                            <?php echo $is_writable ? 'Writable' : 'Read-Only'; ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Asterisk CLI Access</td>
                        <td>sudo enabled</td>
                        <td>
                            <span class="status-indicator status-active"></span>
                            Available
                        </td>
                    </tr>
                    <tr>
                        <td>Group Membership</td>
                        <td>flexpbxuser in asterisk group</td>
                        <td>
                            <span class="status-indicator status-active"></span>
                            Active
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="back-link">
            <a href="dashboard.html">← Back to Admin Dashboard</a>
        </div>
    </div>

    <script>
        function selectMode(mode) {
            // Update radio button
            document.querySelectorAll('input[name="asterisk_mode"]').forEach(radio => {
                radio.checked = (radio.value === mode);
            });

            // Update active class
            document.querySelectorAll('.mode-option').forEach(option => {
                option.classList.remove('active');
            });
            event.currentTarget.classList.add('active');

            // Show/hide warning
            const warningBox = document.getElementById('warningBox');
            const confirmCheckbox = document.getElementById('confirmCheckbox');
            const saveButton = document.getElementById('saveButton');

            if (mode === 'power_user') {
                warningBox.style.display = 'block';
                confirmCheckbox.checked = false;
            } else {
                warningBox.style.display = 'none';
            }
        }

        // Form validation
        document.getElementById('settingsForm').addEventListener('submit', function(e) {
            const mode = document.querySelector('input[name="asterisk_mode"]:checked').value;
            const confirm = document.getElementById('confirmCheckbox');

            if (mode === 'power_user' && !confirm.checked) {
                e.preventDefault();
                alert('Please confirm that you understand the risks before enabling Power User Mode.');
                return false;
            }

            if (mode === 'power_user') {
                const userConfirm = window.confirm(
                    'Are you sure you want to enable Power User Mode?\n\n' +
                    'This will grant write access to Asterisk configuration files.\n\n' +
                    'Click OK to proceed or Cancel to go back.'
                );

                if (!userConfirm) {
                    e.preventDefault();
                    return false;
                }
            }
        });
    </script>
</body>
</html>
