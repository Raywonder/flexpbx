<?php
/**
 * FlexPBX Mattermost Channels Configuration
 * Admin interface for managing Mattermost integration
 *
 * @author FlexPBX Development Team
 * @version 1.0.0
 * @created 2025-11-06
 */

session_start();

// Check if user is authenticated
if (!isset($_SESSION['admin_username'])) {
    header('Location: /admin/login.php');
    exit();
}

// Load configuration
$config = require_once(__DIR__ . '/../api/config.php');

// Connect to database
try {
    $db = new PDO(
        "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4",
        $config['db_user'],
        $config['db_password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}

// Get current configuration
$stmt = $db->query("SELECT * FROM mattermost_config ORDER BY id DESC LIMIT 1");
$mattermostConfig = $stmt->fetch();

// Get configured channels
$stmt = $db->query("SELECT * FROM mattermost_channels ORDER BY sort_order ASC, channel_display_name ASC");
$channels = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mattermost Channel Configuration - FlexPBX Admin</title>
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
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .header p {
            opacity: 0.9;
            font-size: 16px;
        }

        .nav-buttons {
            padding: 20px 30px;
            background: #f8f9fa;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .nav-buttons a,
        .nav-buttons button {
            padding: 10px 20px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            text-decoration: none;
            color: #333;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .nav-buttons a:hover,
        .nav-buttons button:hover {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        .nav-buttons .active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        .content {
            padding: 30px;
        }

        .section {
            display: none;
            animation: fadeIn 0.3s ease;
        }

        .section.active {
            display: block;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .form-group {
            margin-bottom: 25px;
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
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-group .help-text {
            font-size: 13px;
            color: #666;
            margin-top: 5px;
        }

        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-block;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: none;
        }

        .alert.show {
            display: block;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .card {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .card h3 {
            margin-bottom: 15px;
            color: #333;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-badge.connected {
            background: #d4edda;
            color: #155724;
        }

        .status-badge.disconnected {
            background: #f8d7da;
            color: #721c24;
        }

        .channel-list {
            display: grid;
            gap: 15px;
        }

        .channel-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
        }

        .channel-info {
            flex: 1;
        }

        .channel-info h4 {
            margin-bottom: 5px;
            color: #333;
        }

        .channel-info p {
            color: #666;
            font-size: 13px;
        }

        .channel-actions {
            display: flex;
            gap: 10px;
        }

        .channel-actions button {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .loading {
            text-align: center;
            padding: 40px;
        }

        .loading::after {
            content: '';
            display: inline-block;
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        @media (max-width: 768px) {
            .grid-2 {
                grid-template-columns: 1fr;
            }

            .nav-buttons {
                flex-direction: column;
            }

            .channel-item {
                flex-direction: column;
                align-items: flex-start;
            }

            .channel-actions {
                margin-top: 10px;
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Mattermost Integration</h1>
            <p>Configure and manage Mattermost channels for FlexPBX</p>
        </div>

        <div class="nav-buttons">
            <button class="active" onclick="showSection('connection')">Connection Settings</button>
            <button onclick="showSection('channels')">Channel Management</button>
            <button onclick="showSection('sync')">Sync Channels</button>
            <button onclick="showSection('advanced')">Advanced Settings</button>
            <a href="/admin/dashboard.php">Back to Dashboard</a>
        </div>

        <div class="content">
            <!-- Alerts -->
            <div id="alert-container"></div>

            <!-- Connection Settings Section -->
            <div id="section-connection" class="section active">
                <div class="card">
                    <h3>Connection Status</h3>
                    <div id="connection-status">
                        <?php if ($mattermostConfig && $mattermostConfig['access_token']): ?>
                            <span class="status-badge connected">Connected</span>
                            <p style="margin-top: 10px;">Server: <?php echo htmlspecialchars($mattermostConfig['server_url']); ?></p>
                        <?php else: ?>
                            <span class="status-badge disconnected">Not Configured</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card">
                    <h3>Mattermost Server Configuration</h3>
                    <form id="config-form">
                        <div class="form-group">
                            <label for="server_url">Server URL</label>
                            <input type="url" id="server_url" name="server_url"
                                   value="<?php echo htmlspecialchars($mattermostConfig['server_url'] ?? 'https://chat.tappedin.fm'); ?>"
                                   placeholder="https://chat.tappedin.fm" required>
                            <div class="help-text">Enter your Mattermost server URL (without trailing slash)</div>
                        </div>

                        <div class="form-group">
                            <label for="access_token">Personal Access Token</label>
                            <input type="password" id="access_token" name="access_token"
                                   value="<?php echo htmlspecialchars($mattermostConfig['access_token'] ?? ''); ?>"
                                   placeholder="Enter personal access token">
                            <div class="help-text">Create a personal access token in Mattermost: Account Settings > Security > Personal Access Tokens</div>
                        </div>

                        <div class="form-group">
                            <label for="bot_token">Bot Token (Optional)</label>
                            <input type="password" id="bot_token" name="bot_token"
                                   value="<?php echo htmlspecialchars($mattermostConfig['bot_token'] ?? ''); ?>"
                                   placeholder="Enter bot token (optional)">
                            <div class="help-text">Use a bot account for posting messages (optional)</div>
                        </div>

                        <div class="grid-2">
                            <div class="form-group">
                                <label for="poll_interval">Poll Interval (seconds)</label>
                                <input type="number" id="poll_interval" name="poll_interval"
                                       value="<?php echo $mattermostConfig['poll_interval'] ?? 5; ?>"
                                       min="1" max="60">
                                <div class="help-text">How often to check for new messages</div>
                            </div>

                            <div class="form-group">
                                <label>
                                    <input type="checkbox" id="enable_notifications" name="enable_notifications"
                                           <?php echo ($mattermostConfig['enable_notifications'] ?? true) ? 'checked' : ''; ?>>
                                    Enable Notifications
                                </label>
                            </div>
                        </div>

                        <button type="button" class="btn btn-secondary" onclick="testConnection()">Test Connection</button>
                        <button type="submit" class="btn btn-primary">Save Configuration</button>
                    </form>
                </div>
            </div>

            <!-- Channel Management Section -->
            <div id="section-channels" class="section">
                <div class="card">
                    <h3>Configured Channels</h3>
                    <div id="channel-list" class="channel-list">
                        <?php if (empty($channels)): ?>
                            <p>No channels configured yet. Use the "Sync Channels" tab to import channels from Mattermost.</p>
                        <?php else: ?>
                            <?php foreach ($channels as $channel): ?>
                                <div class="channel-item" data-channel-id="<?php echo htmlspecialchars($channel['channel_id']); ?>">
                                    <div class="channel-info">
                                        <h4><?php echo htmlspecialchars($channel['channel_display_name']); ?></h4>
                                        <p>
                                            Name: <?php echo htmlspecialchars($channel['channel_name']); ?> |
                                            Type: <?php echo htmlspecialchars($channel['channel_type']); ?> |
                                            <?php echo $channel['is_visible'] ? '<span class="status-badge connected">Visible</span>' : '<span class="status-badge disconnected">Hidden</span>'; ?>
                                            <?php echo $channel['is_default'] ? ' | <strong>Default</strong>' : ''; ?>
                                        </p>
                                    </div>
                                    <div class="channel-actions">
                                        <button class="btn btn-secondary" onclick="editChannel('<?php echo htmlspecialchars($channel['channel_id']); ?>')">Edit</button>
                                        <button class="btn btn-danger" onclick="toggleChannelVisibility('<?php echo htmlspecialchars($channel['channel_id']); ?>')">
                                            <?php echo $channel['is_visible'] ? 'Hide' : 'Show'; ?>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Sync Channels Section -->
            <div id="section-sync" class="section">
                <div class="card">
                    <h3>Sync Channels from Mattermost</h3>
                    <p style="margin-bottom: 20px;">Import channels from your Mattermost server. Select which channels you want to make available in FlexPBX.</p>

                    <button class="btn btn-primary" onclick="loadTeams()">Load Teams & Channels</button>

                    <div id="teams-container" style="margin-top: 20px;"></div>
                </div>
            </div>

            <!-- Advanced Settings Section -->
            <div id="section-advanced" class="section">
                <div class="card">
                    <h3>Advanced Settings</h3>

                    <div class="form-group">
                        <label>Message Cache</label>
                        <button class="btn btn-secondary" onclick="clearMessageCache()">Clear Message Cache</button>
                        <div class="help-text">Clear locally cached messages</div>
                    </div>

                    <div class="form-group">
                        <label>Activity Log</label>
                        <button class="btn btn-secondary" onclick="viewActivityLog()">View Activity Log</button>
                        <div class="help-text">View integration activity and debugging information</div>
                    </div>

                    <div class="form-group">
                        <label>Reset Configuration</label>
                        <button class="btn btn-danger" onclick="resetConfiguration()">Reset All Settings</button>
                        <div class="help-text">WARNING: This will delete all Mattermost configuration and channel settings</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Show/hide sections
        function showSection(sectionName) {
            const sections = document.querySelectorAll('.section');
            sections.forEach(section => section.classList.remove('active'));

            const buttons = document.querySelectorAll('.nav-buttons button');
            buttons.forEach(btn => btn.classList.remove('active'));

            document.getElementById('section-' + sectionName).classList.add('active');
            event.target.classList.add('active');
        }

        // Show alert
        function showAlert(message, type = 'success') {
            const alertContainer = document.getElementById('alert-container');
            const alert = document.createElement('div');
            alert.className = `alert alert-${type} show`;
            alert.textContent = message;

            alertContainer.innerHTML = '';
            alertContainer.appendChild(alert);

            setTimeout(() => {
                alert.classList.remove('show');
                setTimeout(() => alert.remove(), 300);
            }, 5000);
        }

        // Test connection
        async function testConnection() {
            const serverUrl = document.getElementById('server_url').value;
            const token = document.getElementById('access_token').value;

            if (!serverUrl || !token) {
                showAlert('Please enter server URL and access token', 'error');
                return;
            }

            try {
                const response = await fetch('/api/mattermost-integration.php?action=test_connection', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        server_url: serverUrl,
                        token: token
                    })
                });

                const result = await response.json();

                if (result.success) {
                    showAlert('Connection successful! Connected as: ' + result.user.username, 'success');
                } else {
                    showAlert('Connection failed: ' + result.message, 'error');
                }
            } catch (error) {
                showAlert('Error testing connection: ' + error.message, 'error');
            }
        }

        // Save configuration
        document.getElementById('config-form').addEventListener('submit', async (e) => {
            e.preventDefault();

            const formData = {
                server_url: document.getElementById('server_url').value,
                access_token: document.getElementById('access_token').value,
                bot_token: document.getElementById('bot_token').value,
                poll_interval: document.getElementById('poll_interval').value,
                enable_notifications: document.getElementById('enable_notifications').checked,
                created_by: '<?php echo $_SESSION['admin_username']; ?>'
            };

            try {
                const response = await fetch('/api/mattermost-integration.php?action=save_config', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(formData)
                });

                const result = await response.json();

                if (result.success) {
                    showAlert('Configuration saved successfully!', 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert('Failed to save configuration: ' + result.error, 'error');
                }
            } catch (error) {
                showAlert('Error saving configuration: ' + error.message, 'error');
            }
        });

        // Load teams and channels
        async function loadTeams() {
            const teamsContainer = document.getElementById('teams-container');
            teamsContainer.innerHTML = '<div class="loading"></div>';

            try {
                const response = await fetch('/api/mattermost-integration.php?action=get_teams');
                const result = await response.json();

                if (result.success) {
                    let html = '';

                    for (const team of result.teams) {
                        html += `<div class="card">
                            <h4>${team.display_name}</h4>
                            <button class="btn btn-secondary" onclick="loadChannelsForTeam('${team.id}', '${team.display_name}')">Load Channels</button>
                            <div id="channels-${team.id}" style="margin-top: 15px;"></div>
                        </div>`;
                    }

                    teamsContainer.innerHTML = html;
                } else {
                    teamsContainer.innerHTML = '<p>Error loading teams: ' + result.error + '</p>';
                }
            } catch (error) {
                teamsContainer.innerHTML = '<p>Error: ' + error.message + '</p>';
            }
        }

        // Load channels for team
        async function loadChannelsForTeam(teamId, teamName) {
            const channelsContainer = document.getElementById('channels-' + teamId);
            channelsContainer.innerHTML = '<div class="loading"></div>';

            try {
                const response = await fetch('/api/mattermost-integration.php?action=get_channels&team_id=' + teamId);
                const result = await response.json();

                if (result.success) {
                    let html = '<div class="channel-list" style="margin-top: 15px;">';

                    for (const channel of result.channels) {
                        html += `<div class="channel-item">
                            <div class="channel-info">
                                <h4>${channel.display_name}</h4>
                                <p>${channel.name} | Type: ${channel.type}</p>
                            </div>
                            <div class="channel-actions">
                                <button class="btn btn-success" onclick="importChannel('${teamId}', '${teamName}', '${channel.id}', '${channel.name}', '${channel.display_name}', '${channel.type}')">Import</button>
                            </div>
                        </div>`;
                    }

                    html += '</div>';
                    channelsContainer.innerHTML = html;
                } else {
                    channelsContainer.innerHTML = '<p>Error loading channels: ' + result.error + '</p>';
                }
            } catch (error) {
                channelsContainer.innerHTML = '<p>Error: ' + error.message + '</p>';
            }
        }

        // Import channel
        async function importChannel(teamId, teamName, channelId, channelName, displayName, channelType) {
            try {
                const response = await fetch('/api/mattermost-integration.php?action=save_channel', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        channel_id: channelId,
                        channel_name: channelName,
                        channel_display_name: displayName,
                        team_id: teamId,
                        team_name: teamName,
                        channel_type: channelType,
                        is_visible: true
                    })
                });

                const result = await response.json();

                if (result.success) {
                    showAlert('Channel imported successfully!', 'success');
                } else {
                    showAlert('Failed to import channel: ' + result.error, 'error');
                }
            } catch (error) {
                showAlert('Error importing channel: ' + error.message, 'error');
            }
        }

        // Toggle channel visibility
        async function toggleChannelVisibility(channelId) {
            // Implementation would go here
            showAlert('Feature coming soon', 'info');
        }

        // Edit channel
        function editChannel(channelId) {
            // Implementation would go here
            showAlert('Feature coming soon', 'info');
        }

        // Clear message cache
        async function clearMessageCache() {
            if (!confirm('Are you sure you want to clear the message cache?')) return;
            showAlert('Feature coming soon', 'info');
        }

        // View activity log
        function viewActivityLog() {
            showAlert('Feature coming soon', 'info');
        }

        // Reset configuration
        async function resetConfiguration() {
            if (!confirm('Are you sure you want to reset all Mattermost configuration? This cannot be undone!')) return;
            showAlert('Feature coming soon', 'info');
        }
    </script>
</body>
</html>
