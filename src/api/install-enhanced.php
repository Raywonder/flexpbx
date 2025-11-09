<?php
/**
 * FlexPBX Web-Based Quick Installer v1.1 - Enhanced Edition
 * Complete PBX installation with Asterisk, Extensions, Trunks, and Google Voice
 *
 * NEW Features in v1.1:
 * - Asterisk detection and installation
 * - Extension management and creation
 * - SIP trunk configuration (CallCentric, Google Voice)
 * - Google Voice OAuth2 integration
 * - Inbound routing and DID management
 * - Complete accessibility support (WCAG 2.1 AA)
 * - Port auto-detection with visual feedback
 * - Database connection retry flow
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(300); // 5 minutes

class FlexPBXInstallerEnhanced {
    private $step;
    private $errors = [];
    private $warnings = [];
    private $success = [];

    public function __construct() {
        $this->step = $_GET['step'] ?? 'welcome';
    }

    public function run() {
        // Check if installer has already been run
        if ($this->step === 'welcome' && $this->isAlreadyInstalled()) {
            $this->showReinstallOptions();
            return;
        }

        switch ($this->step) {
            case 'welcome':
                $this->showWelcome();
                break;
            case 'requirements':
                $this->checkRequirements();
                break;
            case 'asterisk':
                $this->checkAsterisk();
                break;
            case 'database':
                $this->configureDatabaseStep();
                break;
            case 'extensions':
                $this->configureExtensions();
                break;
            case 'trunks':
                $this->configureTrunks();
                break;
            case 'googlevoice':
                $this->configureGoogleVoice();
                break;
            case 'routing':
                $this->configureInboundRouting();
                break;
            case 'install':
                $this->performInstallation();
                break;
            case 'complete':
                $this->showComplete();
                break;
            case 'repair':
                $this->repairTables();
                break;
            case 'reinstall':
                $this->performReinstallation();
                break;
            default:
                $this->showWelcome();
        }
    }

    private function showWelcome() {
        $this->renderHeader('FlexPBX Installation - Welcome');
        ?>
        <div class="welcome-section">
            <h2>🚀 Welcome to FlexPBX Enhanced Installer v1.1</h2>
            <p>This installer will set up your complete FlexPBX server with Asterisk PBX, extensions, trunks, Google Voice integration, and multi-client management.</p>

            <div class="features-list">
                <h3>✨ Features Being Installed:</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                    <div class="feature-card">
                        <h4>📞 Asterisk PBX</h4>
                        <ul>
                            <li>Automatic detection & installation</li>
                            <li>PJSIP configuration</li>
                            <li>AMI (Manager Interface) setup</li>
                        </ul>
                    </div>
                    <div class="feature-card">
                        <h4>👤 Extension Management</h4>
                        <ul>
                            <li>Create SIP extensions (2000-2099)</li>
                            <li>Voicemail configuration</li>
                            <li>Display names & passwords</li>
                        </ul>
                    </div>
                    <div class="feature-card">
                        <h4>🌐 SIP Trunks</h4>
                        <ul>
                            <li>CallCentric trunk setup</li>
                            <li>Google Voice integration</li>
                            <li>Custom SIP trunk support</li>
                        </ul>
                    </div>
                    <div class="feature-card">
                        <h4>📱 Google Voice</h4>
                        <ul>
                            <li>OAuth2 authentication</li>
                            <li>SMS & voice calling</li>
                            <li>API credentials setup</li>
                        </ul>
                    </div>
                    <div class="feature-card">
                        <h4>📍 Inbound Routing</h4>
                        <ul>
                            <li>DID management</li>
                            <li>Destination configuration</li>
                            <li>Business hours routing</li>
                        </ul>
                    </div>
                    <div class="feature-card">
                        <h4>🔗 Client Management</h4>
                        <ul>
                            <li>Multi-client connections</li>
                            <li>Auto-link authorization</li>
                            <li>Update distribution</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="install-info">
                <h3>📋 Installation Steps:</h3>
                <ol>
                    <li>✅ System requirements check</li>
                    <li>📞 Asterisk detection & setup</li>
                    <li>🗄️ Database configuration</li>
                    <li>👤 Extension creation (2000-2005)</li>
                    <li>🌐 SIP trunk configuration</li>
                    <li>📱 Google Voice integration (optional)</li>
                    <li>📍 Inbound routing setup</li>
                    <li>⚙️ Final installation & testing</li>
                </ol>
            </div>

            <div class="action-buttons">
                <a href="?step=requirements" class="btn btn-primary">Start Installation →</a>
            </div>
        </div>
        <?php
        $this->renderFooter();
    }

    private function checkRequirements() {
        $this->renderHeader('FlexPBX Installation - Requirements Check');

        $requirements = [
            'PHP Version' => [
                'check' => version_compare(PHP_VERSION, '7.4.0', '>='),
                'current' => PHP_VERSION,
                'required' => '7.4.0+',
                'critical' => true
            ],
            'PDO Extension' => [
                'check' => extension_loaded('pdo'),
                'current' => extension_loaded('pdo') ? 'Installed' : 'Missing',
                'required' => 'Required',
                'critical' => true
            ],
            'PDO MySQL' => [
                'check' => extension_loaded('pdo_mysql'),
                'current' => extension_loaded('pdo_mysql') ? 'Installed' : 'Missing',
                'required' => 'Required',
                'critical' => true
            ],
            'JSON Extension' => [
                'check' => extension_loaded('json'),
                'current' => extension_loaded('json') ? 'Installed' : 'Missing',
                'required' => 'Required',
                'critical' => true
            ],
            'cURL Extension' => [
                'check' => extension_loaded('curl'),
                'current' => extension_loaded('curl') ? 'Installed' : 'Missing',
                'required' => 'Recommended',
                'critical' => false
            ],
            'File Permissions' => [
                'check' => is_writable('.'),
                'current' => is_writable('.') ? 'Writable' : 'Not Writable',
                'required' => 'Writable',
                'critical' => true
            ]
        ];

        $canContinue = true;
        ?>
        <div class="requirements-section">
            <h2>🔍 System Requirements Check</h2>

            <table class="requirements-table">
                <thead>
                    <tr>
                        <th>Requirement</th>
                        <th>Current</th>
                        <th>Required</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requirements as $name => $req): ?>
                    <tr class="<?= $req['check'] ? 'success' : ($req['critical'] ? 'error' : 'warning') ?>">
                        <td><?= $name ?></td>
                        <td><?= $req['current'] ?></td>
                        <td><?= $req['required'] ?></td>
                        <td>
                            <?php if ($req['check']): ?>
                                <span class="status-icon">✅ Pass</span>
                            <?php elseif (!$req['critical']): ?>
                                <span class="status-icon">⚠️ Warning</span>
                            <?php else: ?>
                                <span class="status-icon">❌ Failed</span>
                                <?php $canContinue = false; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if (!$canContinue): ?>
                <div class="error-message">
                    ❌ Critical requirements not met. Please contact your hosting provider to install missing PHP extensions.
                </div>
            <?php endif; ?>

            <div class="action-buttons">
                <a href="?step=welcome" class="btn btn-secondary">← Back</a>
                <?php if ($canContinue): ?>
                    <a href="?step=asterisk" class="btn btn-primary">Continue to Asterisk Check →</a>
                <?php endif; ?>
            </div>
        </div>
        <?php
        $this->renderFooter();
    }

    private function checkAsterisk() {
        $this->renderHeader('FlexPBX Installation - Asterisk Detection');

        // Check if Asterisk is installed
        exec('which asterisk 2>/dev/null', $output, $return_code);
        $asteriskInstalled = ($return_code === 0);

        $asteriskVersion = null;
        $asteriskRunning = false;

        if ($asteriskInstalled) {
            exec('asterisk -V 2>/dev/null', $versionOutput);
            $asteriskVersion = $versionOutput[0] ?? 'Unknown';

            exec('systemctl is-active asterisk 2>/dev/null', $statusOutput, $statusCode);
            $asteriskRunning = ($statusCode === 0 && $statusOutput[0] === 'active');
        }

        // Store Asterisk status in session
        $_SESSION['asterisk_installed'] = $asteriskInstalled;
        $_SESSION['asterisk_version'] = $asteriskVersion;
        $_SESSION['asterisk_running'] = $asteriskRunning;

        ?>
        <div class="asterisk-section">
            <h2>📞 Asterisk PBX Detection</h2>

            <div class="status-box <?= $asteriskInstalled ? 'success' : 'warning' ?>">
                <?php if ($asteriskInstalled): ?>
                    <h3>✅ Asterisk Detected</h3>
                    <p><strong>Version:</strong> <?= htmlspecialchars($asteriskVersion) ?></p>
                    <p><strong>Status:</strong> <?= $asteriskRunning ? '🟢 Running' : '🔴 Stopped' ?></p>

                    <?php if (!$asteriskRunning): ?>
                        <div class="warning-box">
                            <p>⚠️ Asterisk is installed but not running. Would you like to start it?</p>
                            <button onclick="startAsterisk()" class="btn btn-warning">Start Asterisk</button>
                            <div id="asterisk-start-result"></div>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <h3>⚠️ Asterisk Not Found</h3>
                    <p>Asterisk is not installed on this server. You have two options:</p>

                    <div class="install-options">
                        <div class="option-card">
                            <h4>Option 1: Install Asterisk Now</h4>
                            <p>We can attempt to install Asterisk automatically in the background.</p>
                            <button onclick="installAsterisk()" class="btn btn-primary">Install Asterisk</button>
                            <div id="asterisk-install-result"></div>
                            <div id="asterisk-install-progress" style="display: none;">
                                <div class="progress-bar">
                                    <div class="progress-fill" id="install-progress"></div>
                                </div>
                                <p id="install-status">Installing Asterisk... This may take 5-10 minutes.</p>
                            </div>
                        </div>

                        <div class="option-card">
                            <h4>Option 2: Install Manually</h4>
                            <p>Follow these steps to install Asterisk manually:</p>
                            <ol>
                                <li>SSH into your server</li>
                                <li>Run: <code>dnf install asterisk -y</code> (RHEL/AlmaLinux)</li>
                                <li>Or: <code>apt-get install asterisk -y</code> (Ubuntu/Debian)</li>
                                <li>Start service: <code>systemctl start asterisk</code></li>
                                <li>Reload this page</li>
                            </ol>
                            <a href="?step=asterisk" class="btn btn-secondary">🔄 Reload Page</a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="action-buttons">
                <a href="?step=requirements" class="btn btn-secondary">← Back to Requirements</a>
                <?php if ($asteriskInstalled): ?>
                    <a href="?step=database" class="btn btn-primary">Continue to Database Setup →</a>
                <?php endif; ?>
            </div>
        </div>

        <script>
        function startAsterisk() {
            document.getElementById('asterisk-start-result').innerHTML = '<p>⏳ Starting Asterisk...</p>';

            fetch('?action=start_asterisk', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('asterisk-start-result').innerHTML = '<p class="success">✅ ' + data.message + '</p>';
                    setTimeout(() => location.reload(), 2000);
                } else {
                    document.getElementById('asterisk-start-result').innerHTML = '<p class="error">❌ ' + data.error + '</p>';
                }
            });
        }

        function installAsterisk() {
            document.getElementById('asterisk-install-progress').style.display = 'block';
            document.getElementById('install-progress').style.width = '10%';

            fetch('?action=install_asterisk', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('install-progress').style.width = '100%';
                    document.getElementById('install-status').innerHTML = '✅ ' + data.message;
                    setTimeout(() => location.reload(), 3000);
                } else {
                    document.getElementById('install-status').innerHTML = '❌ Installation failed: ' + data.error;
                }
            });
        }
        </script>
        <?php
        $this->renderFooter();
    }

    // NOTE: The rest of the methods will be added in the next part
    // This file is being built incrementally due to size

    private function renderHeader($title) {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?= htmlspecialchars($title) ?></title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 20px; }
                .container { max-width: 1200px; margin: 0 auto; background: white; border-radius: 12px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); padding: 40px; }
                h2 { color: #2d3748; margin-bottom: 20px; font-size: 32px; }
                h3 { color: #4a5568; margin-top: 30px; margin-bottom: 15px; }
                h4 { color: #2d3748; margin-bottom: 10px; }
                .feature-card, .option-card { background: #f7fafc; border: 2px solid #e2e8f0; border-radius: 8px; padding: 20px; margin-bottom: 15px; }
                .status-box { padding: 30px; border-radius: 8px; margin: 20px 0; }
                .status-box.success { background: #c6f6d5; border: 2px solid #48bb78; }
                .status-box.warning { background: #fef5e7; border: 2px solid #ecc94b; }
                .warning-box { background: #fff3cd; border: 1px solid #ffc107; padding: 15px; border-radius: 8px; margin: 15px 0; }
                .btn { display: inline-block; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: 600; cursor: pointer; border: none; font-size: 16px; transition: all 0.3s; }
                .btn-primary { background: #4299e1; color: white; }
                .btn-primary:hover { background: #3182ce; }
                .btn-secondary { background: #a0aec0; color: white; }
                .btn-secondary:hover { background: #718096; }
                .btn-warning { background: #ed8936; color: white; }
                .btn-warning:hover { background: #dd6b20; }
                .action-buttons { margin-top: 30px; display: flex; gap: 15px; }
                .requirements-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                .requirements-table th, .requirements-table td { padding: 12px; text-align: left; border-bottom: 1px solid #e2e8f0; }
                .requirements-table th { background: #f7fafc; font-weight: 600; }
                .requirements-table tr.success { background: #f0fff4; }
                .requirements-table tr.error { background: #fff5f5; }
                .requirements-table tr.warning { background: #fffaf0; }
                .progress-bar { width: 100%; height: 30px; background: #e2e8f0; border-radius: 15px; overflow: hidden; margin: 20px 0; }
                .progress-fill { height: 100%; background: linear-gradient(90deg, #4299e1, #667eea); transition: width 0.5s; }
                code { background: #2d3748; color: #48bb78; padding: 2px 8px; border-radius: 4px; font-family: 'Courier New', monospace; }
                ol { margin-left: 30px; line-height: 1.8; }
                ul { margin-left: 25px; line-height: 1.6; }
                .success { color: #22543d; }
                .error { color: #c53030; }
            </style>
        </head>
        <body>
            <div class="container">
        <?php
    }

    private function renderFooter() {
        ?>
            </div>
        </body>
        </html>
        <?php
    }

    private function isAlreadyInstalled() {
        return file_exists('config.php') && filesize('config.php') > 100;
    }

    private function showReinstallOptions() {
        $this->renderHeader('FlexPBX - Already Installed');
        ?>
        <div class="reinstall-section">
            <h2>⚠️ FlexPBX Already Installed</h2>
            <p>FlexPBX appears to be already installed. What would you like to do?</p>

            <div style="margin-top: 30px; display: grid; gap: 20px;">
                <div class="option-card">
                    <h4>🔧 Repair Installation</h4>
                    <p>Fix database tables and restore configuration</p>
                    <a href="?step=repair" class="btn btn-warning">Repair</a>
                </div>

                <div class="option-card">
                    <h4>🔄 Update Installation</h4>
                    <p>Add missing tables and update existing configuration</p>
                    <a href="?step=reinstall&mode=update" class="btn btn-primary">Update</a>
                </div>

                <div class="option-card">
                    <h4>🆕 Fresh Installation</h4>
                    <p>Complete reinstallation (existing data will be preserved where possible)</p>
                    <a href="?step=reinstall&mode=fresh" class="btn btn-secondary">Reinstall</a>
                </div>
            </div>
        </div>
        <?php
        $this->renderFooter();
    }
}

// Handle AJAX actions
if (isset($_GET['action']) || isset($_POST['action'])) {
    $action = $_GET['action'] ?? $_POST['action'];

    if ($action === 'start_asterisk') {
        header('Content-Type: application/json');
        exec('systemctl start asterisk 2>&1', $output, $return_code);
        if ($return_code === 0) {
            echo json_encode(['success' => true, 'message' => 'Asterisk started successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to start Asterisk: ' . implode(' ', $output)]);
        }
        exit;
    }

    if ($action === 'install_asterisk') {
        header('Content-Type: application/json');
        // Detect OS and install Asterisk
        exec('cat /etc/os-release | grep "^ID=" | cut -d= -f2', $osOutput);
        $os = trim(str_replace('"', '', $osOutput[0] ?? ''));

        if (in_array($os, ['rhel', 'centos', 'almalinux', 'rocky'])) {
            exec('dnf install asterisk -y 2>&1', $output, $return_code);
        } elseif (in_array($os, ['ubuntu', 'debian'])) {
            exec('apt-get update && apt-get install asterisk -y 2>&1', $output, $return_code);
        } else {
            echo json_encode(['success' => false, 'error' => 'Unsupported OS: ' . $os]);
            exit;
        }

        if ($return_code === 0) {
            exec('systemctl enable asterisk && systemctl start asterisk');
            echo json_encode(['success' => true, 'message' => 'Asterisk installed and started successfully']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Installation failed: ' . implode(' ', $output)]);
        }
        exit;
    }
}

// Run the installer
$installer = new FlexPBXInstallerEnhanced();
$installer->run();
?>
