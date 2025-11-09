<?php
/**
 * FlexPBX MCP Server Setup Page
 * Allows installation/management of MCP server after FlexPBX installation
 *
 * Location: /admin/mcp-server-setup.php
 */

session_start();
require_once '../includes/admin_header.php';

// Check if user is admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /admin/login.php');
    exit;
}

$mcpDir = '/home/flexpbxuser/mcp-server';
$pidFile = "{$mcpDir}/server.pid";
$logFile = "{$mcpDir}/logs/mcp.log";

// Get status
function getMCPStatus() {
    global $mcpDir, $pidFile;

    $status = [
        'installed' => false,
        'running' => false,
        'pid' => null,
        'node_version' => null,
        'node_installed' => false,
        'ami_configured' => false
    ];

    // Check if MCP directory exists
    if (is_dir($mcpDir)) {
        $status['installed'] = true;
    }

    // Check if .env exists and has AMI credentials
    if (file_exists("{$mcpDir}/.env")) {
        $env = file_get_contents("{$mcpDir}/.env");
        if (strpos($env, 'AMI_SECRET') !== false && strpos($env, 'AMI_USERNAME') !== false) {
            $status['ami_configured'] = true;
        }
    }

    // Check if process is running
    if (file_exists($pidFile)) {
        $pid = trim(file_get_contents($pidFile));
        if ($pid && posix_kill((int)$pid, 0)) {
            $status['running'] = true;
            $status['pid'] = $pid;
        }
    }

    // Check Node.js
    $nodeCheck = shell_exec('node --version 2>&1');
    if ($nodeCheck && preg_match('/v?(\d+\.\d+\.\d+)/', $nodeCheck, $matches)) {
        $status['node_installed'] = true;
        $status['node_version'] = $matches[1];
    }

    return $status;
}

$status = getMCPStatus();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'install') {
        // Run installation
        header('Location: /api/install.php?step=install&install_mcp=1');
        exit;
    }

    if ($action === 'start' && $status['installed']) {
        $cmd = "cd {$mcpDir} && nohup node server.js >> logs/mcp.log 2>&1 & echo $!";
        $pid = trim(shell_exec($cmd));
        if ($pid) {
            file_put_contents($pidFile, $pid);
        }
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    if ($action === 'stop' && $status['running']) {
        $pid = $status['pid'];
        posix_kill((int)$pid, SIGTERM);
        if (file_exists($pidFile)) {
            unlink($pidFile);
        }
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    if ($action === 'restart' && $status['running']) {
        $pid = $status['pid'];
        posix_kill((int)$pid, SIGTERM);
        sleep(1);
        $cmd = "cd {$mcpDir} && nohup node server.js >> logs/mcp.log 2>&1 & echo $!";
        $pid = trim(shell_exec($cmd));
        if ($pid) {
            file_put_contents($pidFile, $pid);
        }
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MCP Server Setup - FlexPBX Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2c3e50; margin-bottom: 10px; }
        .subtitle { color: #7f8c8d; margin-bottom: 30px; }
        .status-card { background: #f8f9fa; padding: 20px; border-radius: 6px; margin-bottom: 20px; }
        .status-item { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #e9ecef; }
        .status-item:last-child { border-bottom: none; }
        .badge { padding: 4px 12px; border-radius: 4px; font-size: 14px; font-weight: 600; }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .btn { display: inline-block; padding: 10px 20px; margin: 5px; background: #3498db; color: white; text-decoration: none; border-radius: 5px; border: none; cursor: pointer; font-size: 14px; }
        .btn-success { background: #28a745; }
        .btn-danger { background: #dc3545; }
        .btn-secondary { background: #6c757d; }
        .btn:hover { opacity: 0.9; }
        .section { margin: 30px 0; }
        .code-block { background: #f8f9fa; padding: 15px; border-radius: 4px; font-family: monospace; font-size: 13px; overflow-x: auto; }
        .alert { padding: 15px; border-radius: 4px; margin: 20px 0; }
        .alert-info { background: #d1ecf1; color: #0c5460; }
        .alert-warning { background: #fff3cd; color: #856404; }
        .alert-success { background: #d4edda; color: #155724; }
        .log-viewer { background: #2c3e50; color: #ecf0f1; padding: 15px; border-radius: 4px; font-family: monospace; font-size: 12px; max-height: 400px; overflow-y: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🤖 MCP Server Setup</h1>
        <p class="subtitle">AI-Assisted PBX Management via Asterisk Manager Interface</p>

        <div class="status-card">
            <h3 style="margin-bottom: 15px;">Current Status</h3>

            <div class="status-item">
                <strong>Node.js:</strong>
                <?php if ($status['node_installed']): ?>
                    <span class="badge badge-success">✓ Installed (v<?= htmlspecialchars($status['node_version']) ?>)</span>
                <?php else: ?>
                    <span class="badge badge-danger">✗ Not Installed</span>
                <?php endif; ?>
            </div>

            <div class="status-item">
                <strong>MCP Server:</strong>
                <?php if ($status['installed']): ?>
                    <span class="badge badge-success">✓ Installed</span>
                <?php else: ?>
                    <span class="badge badge-danger">✗ Not Installed</span>
                <?php endif; ?>
            </div>

            <div class="status-item">
                <strong>Server Status:</strong>
                <?php if ($status['running']): ?>
                    <span class="badge badge-success">✓ Running (PID: <?= htmlspecialchars($status['pid']) ?>)</span>
                <?php elseif ($status['installed']): ?>
                    <span class="badge badge-warning">⚠ Stopped</span>
                <?php else: ?>
                    <span class="badge badge-danger">✗ Not Running</span>
                <?php endif; ?>
            </div>

            <div class="status-item">
                <strong>AMI Configuration:</strong>
                <?php if ($status['ami_configured']): ?>
                    <span class="badge badge-success">✓ Configured</span>
                <?php else: ?>
                    <span class="badge badge-warning">⚠ Not Configured</span>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!$status['node_installed']): ?>
        <div class="alert alert-warning">
            <h4>Node.js Required</h4>
            <p>Node.js 18 or higher is required to run the MCP server.</p>
            <p><strong>Installation command:</strong></p>
            <div class="code-block">curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.0/install.sh | bash<br>
nvm install 18<br>
nvm use 18</div>
        </div>
        <?php endif; ?>

        <div class="section">
            <h3>Actions</h3>
            <div style="margin-top: 15px;">
                <?php if (!$status['installed']): ?>
                    <form method="post" style="display: inline;">
                        <input type="hidden" name="action" value="install">
                        <button type="submit" class="btn btn-success" <?= !$status['node_installed'] ? 'disabled title="Node.js required"' : '' ?>>
                            📦 Install MCP Server
                        </button>
                    </form>
                <?php else: ?>
                    <?php if ($status['running']): ?>
                        <form method="post" style="display: inline;">
                            <input type="hidden" name="action" value="stop">
                            <button type="submit" class="btn btn-danger">⏹ Stop Server</button>
                        </form>
                        <form method="post" style="display: inline;">
                            <input type="hidden" name="action" value="restart">
                            <button type="submit" class="btn btn-secondary">🔄 Restart Server</button>
                        </form>
                    <?php else: ?>
                        <form method="post" style="display: inline;">
                            <input type="hidden" name="action" value="start">
                            <button type="submit" class="btn btn-success">▶️ Start Server</button>
                        </form>
                    <?php endif; ?>

                    <button onclick="window.location.reload()" class="btn btn-secondary">🔄 Refresh Status</button>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($status['installed']): ?>
        <div class="section">
            <h3>Configuration</h3>
            <p><strong>MCP Server Directory:</strong> <code><?= htmlspecialchars($mcpDir) ?></code></p>
            <p><strong>Configuration File:</strong> <code><?= htmlspecialchars("{$mcpDir}/.env") ?></code></p>
            <p><strong>Log File:</strong> <code><?= htmlspecialchars($logFile) ?></code></p>

            <?php if (file_exists("{$mcpDir}/.env")): ?>
            <div style="margin-top: 15px;">
                <h4>Environment Variables (.env)</h4>
                <div class="code-block"><?= htmlspecialchars(file_get_contents("{$mcpDir}/.env")) ?></div>
            </div>
            <?php endif; ?>
        </div>

        <div class="section">
            <h3>Claude Desktop Integration</h3>
            <p>To use this MCP server with Claude Desktop, add the following to your Claude config file:</p>

            <p><strong>Mac:</strong> <code>~/Library/Application Support/Claude/claude_desktop_config.json</code></p>
            <p><strong>Windows:</strong> <code>%APPDATA%\Claude\claude_desktop_config.json</code></p>

            <div class="code-block">{
  "mcpServers": {
    "flexpbx-asterisk": {
      "command": "node",
      "args": ["<?= htmlspecialchars($mcpDir) ?>/server.js"]
    }
  }
}</div>
        </div>

        <?php if (file_exists($logFile)): ?>
        <div class="section">
            <h3>Recent Logs</h3>
            <div class="log-viewer">
<?php
$logs = file_get_contents($logFile);
$lines = explode("\n", $logs);
$recentLines = array_slice($lines, -50);
echo htmlspecialchars(implode("\n", $recentLines));
?>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <div class="section">
            <h3>Available MCP Tools</h3>
            <ul style="margin-left: 20px; line-height: 1.8;">
                <li><strong>list_extensions</strong> - List all SIP/PJSIP extensions in the PBX</li>
                <li><strong>get_active_calls</strong> - Get currently active calls with details</li>
                <li><strong>get_extension_status</strong> - Check registration status of an extension</li>
                <li><strong>asterisk_command</strong> - Execute any Asterisk CLI command</li>
                <li><strong>system_status</strong> - Get comprehensive system health information</li>
            </ul>
        </div>

        <div class="section">
            <h3>Example AI Queries</h3>
            <p>Once configured with Claude Desktop, you can ask:</p>
            <ul style="margin-left: 20px; line-height: 1.8;">
                <li>"List all extensions in my FlexPBX system"</li>
                <li>"Show me all active calls right now"</li>
                <li>"What's the status of extension 100?"</li>
                <li>"Check the Asterisk version and uptime"</li>
                <li>"Execute 'sip show peers' command"</li>
            </ul>
        </div>

        <div class="alert alert-info">
            <h4>📚 Documentation</h4>
            <p>Full documentation: <code>/home/flexpbxuser/documentation/MCP_SERVER_INSTALLER_INTEGRATION.md</code></p>
            <p>README: <code><?= htmlspecialchars($mcpDir) ?>/README.md</code></p>
        </div>

        <div style="text-align: center; margin-top: 30px;">
            <a href="/admin/setup-wizard.php" class="btn btn-secondary">← Back to Setup Wizard</a>
            <a href="/admin/dashboard.php" class="btn btn-secondary">Go to Dashboard →</a>
        </div>
    </div>
</body>
</html>
