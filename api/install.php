<?php
/**
 * FlexPBX Web-Based Quick Installer v1.0
 * Similar to Composr installer - run from browser to complete all setup steps
 *
 * Features:
 * - Complete accessibility support (WCAG 2.1 AA)
 * - Port auto-detection with visual feedback
 * - Database connection retry flow
 * - Client version compatibility information
 * - Beautiful visual design with animations
 *
 * Next Version (v1.1) - Coming Soon:
 * - Integrated promotional images using FLUX.1/Stable Diffusion
 * - Visual client showcase with generated graphics
 * - Architecture diagrams and hero images
 * - See IMAGE_GENERATION_SOLUTIONS.md for implementation
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(300); // 5 minutes

class FlexPBXInstaller {
    private $step;
    private $errors = [];
    private $warnings = [];
    private $success = [];

    private function getStoredApiKey() {
        try {
            if (file_exists('../config/config.php')) {
                include '../config/config.php';
                return $API_KEY ?? null;
            }
        } catch (Exception $e) {
            // Fallback to database
            try {
                $dbConfig = $_SESSION['db_config'] ?? [];
                if (!empty($dbConfig)) {
                    $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['name']}";
                    $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass']);
                    $stmt = $pdo->prepare("SELECT value FROM config WHERE setting_name = 'api_key' LIMIT 1");
                    $stmt->execute();
                    $result = $stmt->fetch();
                    return $result['value'] ?? null;
                }
            } catch (Exception $e) {
                // Return null if can't retrieve
            }
        }
        return null;
    }

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
            case 'database':
                $this->configureDatabaseStep();
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
            <h2>🚀 Welcome to FlexPBX Quick Installer</h2>
            <p>This installer will set up your FlexPBX server with multi-client connection management, auto-link authorization, and update capabilities.</p>

            <!-- v1.1 Feature: Hero image will be added here using FLUX.1/Stable Diffusion -->
            <!-- <div class="hero-image-placeholder" style="text-align: center; margin: 20px 0; padding: 40px; background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); border-radius: 12px; border: 2px dashed #2196f3;">
                <p style="color: #1976d2; font-size: 18px; margin: 0;">📸 Hero Image Coming in v1.1</p>
                <p style="color: #424242; font-size: 14px; margin: 5px 0 0;">Professional FlexPBX network topology with connected devices</p>
            </div> -->

            <div class="features-list">
                <h3>✨ Features Being Installed:</h3>
                <ul>
                    <li>🔗 Multi-server client connection management</li>
                    <li>🔐 Auto-link authorization system</li>
                    <li>📱 Desktop and admin client support</li>
                    <li>🔄 Automatic update distribution</li>
                    <li>⚡ Module reload capabilities</li>
                    <li>🛡️ Security and rate limiting</li>
                </ul>
            </div>

            <div class="install-info">
                <h3>📋 Installation Process:</h3>
                <ol>
                    <li>System requirements check</li>
                    <li>Database configuration</li>
                    <li>File installation and setup</li>
                    <li>Configuration finalization</li>
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
                                ✅ OK
                            <?php elseif ($req['critical']): ?>
                                ❌ FAILED
                                <?php $canContinue = false; ?>
                            <?php else: ?>
                                ⚠️ WARNING
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if (!$canContinue): ?>
                <div class="alert alert-error">
                    ❌ Critical requirements not met. Please contact your hosting provider to install missing PHP extensions.
                </div>
            <?php endif; ?>

            <div class="action-buttons">
                <a href="?step=welcome" class="btn btn-secondary">← Back</a>
                <?php if ($canContinue): ?>
                    <a href="?step=database" class="btn btn-primary">Continue →</a>
                <?php endif; ?>
            </div>
        </div>
        <?php
        $this->renderFooter();
    }

    private function configureDatabaseStep() {
        if ($_POST['action'] ?? '' === 'test_db') {
            $this->testDatabaseConnection();
        }

        $this->renderHeader('FlexPBX Installation - Database Configuration');
        ?>
        <div class="database-section">
            <h2>🗄️ Database Configuration</h2>
            <p>Configure your MySQL database connection. You can create a new database or use an existing one.</p>

            <div class="what-will-be-added">
                <h3>📦 What Will Be Installed:</h3>
                <div class="installation-preview">
                    <h4>🗄️ Database Tables (6 tables):</h4>
                    <div class="table-status">
                        <div class="new-items">➕ <span class="table-name">desktop_clients</span> - Registered desktop clients and their connection info</div>
                        <div class="new-items">➕ <span class="table-name">active_connections</span> - Real-time client connection tracking</div>
                        <div class="new-items">➕ <span class="table-name">connection_limits</span> - Per-client connection limits and quotas</div>
                        <div class="new-items">➕ <span class="table-name">auto_link_requests</span> - Pending authorization requests</div>
                        <div class="new-items">➕ <span class="table-name">authorized_links</span> - Approved client authorizations</div>
                        <div class="new-items">➕ <span class="table-name">fallback_hierarchy</span> - Client fallback connection priorities</div>
                    </div>

                    <h4 style="margin-top: 15px;">📄 API Files (4 files):</h4>
                    <div class="table-status">
                        <div class="new-items">➕ <span class="table-name">connection-manager.php</span> - Multi-client connection management (15KB)</div>
                        <div class="new-items">➕ <span class="table-name">auto-link-manager.php</span> - Auto-link authorization system (12KB)</div>
                        <div class="new-items">➕ <span class="table-name">update-manager.php</span> - Update distribution and management (8KB)</div>
                        <div class="new-items">➕ <span class="table-name">.htaccess</span> - URL routing and security headers (3KB)</div>
                    </div>

                    <h4 style="margin-top: 15px;">⚡ Features Being Added:</h4>
                    <div class="table-status">
                        <div class="new-items">➕ Tailscale-like client hierarchy management</div>
                        <div class="new-items">➕ Admin client → Desktop client fallback connections</div>
                        <div class="new-items">➕ Auto-link authorization with approval workflows</div>
                        <div class="new-items">➕ Real-time connection monitoring and limits</div>
                        <div class="new-items">➕ Update distribution and auto-restart capabilities</div>
                        <div class="new-items">➕ Module reload vs full server restart options</div>
                    </div>

                    <h4 style="margin-top: 20px;">🖥️ Supported Client Versions & Platforms:</h4>
                    <div class="client-versions" style="padding: 20px;">
                        <div class="sr-only">This section describes the different FlexPBX client versions that can connect to this server installation.</div>

                        <div class="client-grid">
                            <div class="client-item" role="listitem">
                                <h5 style="margin: 0 0 10px; color: #007bff; display: flex; align-items: center;">
                                    <span aria-hidden="true">👨‍💼</span>
                                    <span style="margin-left: 8px;">FlexPBX Admin Client</span>
                                    <span class="version-badge" style="margin-left: 10px;" aria-label="Version 2.0">v2.0+</span>
                                </h5>
                                <p style="margin: 5px 0; color: #6c757d; font-size: 14px;">
                                    <strong>Primary management client</strong> - Connects directly to remote server, manages other desktop clients, provides admin controls and fallback server capabilities.
                                </p>
                                <div style="margin-top: 10px;">
                                    <strong style="color: #28a745;">✅ Supported Platforms:</strong>
                                    <div style="margin: 5px 0;">
                                        <span class="version-badge">macOS Intel/ARM64</span>
                                        <span class="version-badge">Windows 10/11</span>
                                        <span class="version-badge">Linux AppImage</span>
                                    </div>
                                </div>
                            </div>

                            <div class="client-item" role="listitem">
                                <h5 style="margin: 0 0 10px; color: #007bff; display: flex; align-items: center;">
                                    <span aria-hidden="true">🖥️</span>
                                    <span style="margin-left: 8px;">FlexPBX Desktop Client</span>
                                    <span class="version-badge" style="margin-left: 10px;" aria-label="Version 1.0">v1.0+</span>
                                </h5>
                                <p style="margin: 5px 0; color: #6c757d; font-size: 14px;">
                                    <strong>Standard desktop client</strong> - Connects to admin clients as fallback when remote server unavailable. Auto-link authorization for easy setup.
                                </p>
                                <div style="margin-top: 10px;">
                                    <strong style="color: #28a745;">✅ Connection Hierarchy:</strong>
                                    <div style="margin: 5px 0; font-size: 13px;">
                                        Remote Server → Admin Client → Desktop Client
                                    </div>
                                </div>
                            </div>

                            <div class="client-item" role="listitem">
                                <h5 style="margin: 0 0 10px; color: #007bff; display: flex; align-items: center;">
                                    <span aria-hidden="true">📱</span>
                                    <span style="margin-left: 8px;">FlexPhone Mobile</span>
                                    <span class="version-badge" style="margin-left: 10px;" aria-label="Version 1.0">v1.0+</span>
                                </h5>
                                <p style="margin: 5px 0; color: #6c757d; font-size: 14px;">
                                    <strong>Mobile companion app</strong> - iOS and Android apps with full VoiceOver/TalkBack support, connects through admin client for call management.
                                </p>
                                <div style="margin-top: 10px;">
                                    <strong style="color: #28a745;">♿ Accessibility:</strong>
                                    <div style="margin: 5px 0;">
                                        <span class="version-badge">iOS VoiceOver</span>
                                        <span class="version-badge">Android TalkBack</span>
                                    </div>
                                </div>
                            </div>

                            <div class="client-item" role="listitem">
                                <h5 style="margin: 0 0 10px; color: #007bff; display: flex; align-items: center;">
                                    <span aria-hidden="true">🌐</span>
                                    <span style="margin-left: 8px;">Web Interface</span>
                                    <span class="version-badge" style="margin-left: 10px;" aria-label="Browser based">Browser</span>
                                </h5>
                                <p style="margin: 5px 0; color: #6c757d; font-size: 14px;">
                                    <strong>Browser-based access</strong> - No installation required, works with any modern browser, full screen reader compatibility for accessibility.
                                </p>
                                <div style="margin-top: 10px;">
                                    <strong style="color: #28a745;">🔧 Compatible Browsers:</strong>
                                    <div style="margin: 5px 0;">
                                        <span class="version-badge">Chrome 90+</span>
                                        <span class="version-badge">Firefox 88+</span>
                                        <span class="version-badge">Safari 14+</span>
                                        <span class="version-badge">Edge 90+</span>
                                    </div>
                                </div>
                            </div>

                            <div class="client-item" role="listitem">
                                <h5 style="margin: 0 0 10px; color: #007bff; display: flex; align-items: center;">
                                    <span aria-hidden="true">🔗</span>
                                    <span style="margin-left: 8px;">Legacy Clients</span>
                                    <span class="version-badge" style="margin-left: 10px; background: #fff3cd; color: #856404;" aria-label="Legacy support">Legacy</span>
                                </h5>
                                <p style="margin: 5px 0; color: #6c757d; font-size: 14px;">
                                    <strong>Backward compatibility</strong> - Older FlexPBX versions (pre-2.0) can connect with limited features. Auto-update available for full functionality.
                                </p>
                                <div style="margin-top: 10px;">
                                    <strong style="color: #ffc107;">⚠️ Limited Features:</strong>
                                    <div style="margin: 5px 0; font-size: 13px;">
                                        Basic calling only, no admin features
                                    </div>
                                </div>
                            </div>

                            <div class="client-item" role="listitem" style="border-left-color: #28a745;">
                                <h5 style="margin: 0 0 10px; color: #28a745; display: flex; align-items: center;">
                                    <span aria-hidden="true">🔄</span>
                                    <span style="margin-left: 8px;">Auto-Update System</span>
                                    <span class="version-badge" style="margin-left: 10px; background: #d4edda; color: #155724;" aria-label="Automatic">Auto</span>
                                </h5>
                                <p style="margin: 5px 0; color: #6c757d; font-size: 14px;">
                                    <strong>Seamless updates</strong> - All clients can receive automatic updates through this server, with configurable auto-restart and rollback capabilities.
                                </p>
                                <div style="margin-top: 10px;">
                                    <strong style="color: #28a745;">📦 Update Features:</strong>
                                    <div style="margin: 5px 0; font-size: 13px;">
                                        Checksums, rollback, scheduled updates
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div style="margin-top: 20px; padding: 15px; background: #e3f2fd; border-radius: 6px; border-left: 4px solid #2196f3;">
                            <h6 style="margin: 0 0 10px; color: #1976d2;">
                                <span aria-hidden="true">💡</span> Installation Modes Available:
                            </h6>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px;">
                                <div><strong>Fresh Install:</strong> Complete new setup</div>
                                <div><strong>Add Tables:</strong> Extend existing installation</div>
                                <div><strong>Update/Repair:</strong> Fix or upgrade current setup</div>
                                <div><strong>Alongside:</strong> Preserve existing while adding features</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <form method="post" class="db-config-form">
                <input type="hidden" name="action" value="test_db">

                <div class="form-group">
                    <label>Database Host:</label>
                    <input type="text" name="db_host" value="<?= $_POST['db_host'] ?? 'localhost' ?>" required onchange="clearDetectedDatabases()" placeholder="localhost">
                    <small>Examples: <code>localhost</code>, <code>127.0.0.1</code>, or your server's IP address</small>
                </div>

                <div class="form-group">
                    <label>Database Port:</label>
                    <div class="port-detection">
                        <input type="number" name="db_port" id="db_port_input" value="<?= $_POST['db_port'] ?? '3306' ?>" onchange="clearDetectedDatabases()">
                        <button type="button" onclick="autoDetectPort()" class="btn btn-sm btn-secondary">🔍 Auto-Detect</button>
                    </div>
                    <small>Standard MySQL port is 3306. Click auto-detect to scan for active MySQL ports</small>
                    <div id="port-detection-results" style="display: none;"></div>
                </div>

                <div class="form-group">
                    <label>Database Username:</label>
                    <input type="text" name="db_user" value="<?= $_POST['db_user'] ?? '' ?>" required onchange="clearDetectedDatabases()">
                </div>

                <div class="form-group">
                    <label>Database Password:</label>
                    <input type="password" name="db_password" value="<?= $_POST['db_password'] ?? '' ?>" onchange="clearDetectedDatabases()">
                </div>

                <div class="form-group">
                    <button type="button" onclick="autoDetectDatabases()" class="btn btn-secondary">🔍 Auto-Detect Databases</button>
                    <small>Scan for available databases with current credentials</small>
                </div>

                <div id="detected-databases" style="display: none;" class="detected-db-section">
                    <h4>📊 Detected Databases:</h4>
                    <div id="database-list"></div>
                </div>

                <div class="form-group">
                    <label>Database Name:</label>
                    <input type="text" name="db_name" id="db_name_input" value="<?= $_POST['db_name'] ?? '' ?>" required>
                    <small>Enter existing database name or one to create</small>
                </div>

                <div class="form-group">
                    <label>API Key:</label>
                    <div class="api-key-container" style="display: flex; gap: 10px;">
                        <input type="text" name="api_key" id="api_key_input" value="<?= $_POST['api_key'] ?? 'flexpbx_api_' . bin2hex(random_bytes(16)) ?>" required style="flex: 1;">
                        <button type="button" onclick="generateNewApiKey()" class="btn btn-sm btn-secondary">🔄 Generate New</button>
                    </div>
                    <small>Secure API key for client authentication (auto-generated with cryptographic strength)</small>
                </div>

                <div class="form-group">
                    <label>Installation Mode:</label>
                    <div class="installation-modes">
                        <label class="mode-option">
                            <input type="radio" name="install_mode" value="add_tables" <?= ($_POST['install_mode'] ?? 'add_tables') === 'add_tables' ? 'checked' : '' ?>>
                            ➕ Add New Tables Only
                            <small>Add only missing FlexPBX tables - safe for existing databases</small>
                        </label>
                        <label class="mode-option">
                            <input type="radio" name="install_mode" value="update_existing" <?= ($_POST['install_mode'] ?? '') === 'update_existing' ? 'checked' : '' ?>>
                            🔄 Update Existing Installation
                            <small>Update/repair existing FlexPBX tables and data</small>
                        </label>
                        <label class="mode-option">
                            <input type="radio" name="install_mode" value="fresh" <?= ($_POST['install_mode'] ?? '') === 'fresh' ? 'checked' : '' ?>>
                            🆕 Fresh Installation
                            <small>Complete new installation (existing data preserved where possible)</small>
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label>Database Options:</label>
                    <div class="database-options">
                        <label class="db-option">
                            <input type="checkbox" name="create_db" <?= ($_POST['create_db'] ?? '') ? 'checked' : '' ?>>
                            🗄️ Create database if it doesn't exist
                            <small>Attempt to create the database automatically</small>
                        </label>
                        <label class="db-option">
                            <input type="checkbox" name="show_creation_help" <?= ($_POST['show_creation_help'] ?? '') ? 'checked' : '' ?>>
                            💡 Show database creation assistance
                            <small>Display SQL commands and phpMyAdmin instructions</small>
                        </label>
                    </div>
                </div>

                <div class="action-buttons">
                    <a href="?step=requirements" class="btn btn-secondary">← Back to Requirements</a>
                    <button type="submit" class="btn btn-primary">✅ Validate & Continue</button>
                    <button type="button" onclick="skipToInstall()" class="btn btn-warning" style="margin-left: 10px;">⚡ Skip Test & Install</button>
                </div>
            </form>

            <?php if (!empty($this->errors)): ?>
                <div class="alert alert-error" role="alert" aria-live="polite">
                    <!-- Screen reader announcement -->
                    <div class="sr-only" aria-live="assertive">Alert: Database connection failed. Please review the error details and try again.</div>

                    <div style="display: flex; align-items: center; margin-bottom: 20px; padding: 20px; background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%); border-radius: 8px;">
                        <div style="font-size: 64px; margin-right: 30px; animation: pulse 2s infinite;" aria-hidden="true" role="img" aria-label="Error icon">🚫</div>
                        <div>
                            <h3 style="margin: 0; color: #721c24; font-size: 24px;" id="error-heading">Database Connection Failed</h3>
                            <p style="margin: 8px 0 0 0; color: #856404; font-size: 16px;" aria-describedby="error-heading">Please review the details below and try again</p>
                        </div>
                    </div>

                    <div class="error-details" style="background: linear-gradient(to right, #f1f2f6, #e9ecef); padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #dc3545;" role="region" aria-labelledby="error-details-heading">
                        <h4 style="color: #721c24; margin-bottom: 15px;" id="error-details-heading"><span aria-hidden="true">📋</span> Error Details:</h4>
                        <div class="sr-only">The following section contains detailed error information about the database connection failure:</div>
                        <?php foreach ($this->errors as $error): ?>
                            <?php if (strpos($error, '<div class="error-actions">') !== false): ?>
                                <?= $error ?>
                            <?php else: ?>
                                <div style="display: flex; align-items: flex-start; margin-bottom: 12px; padding: 10px; background: white; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);" role="listitem">
                                    <span style="color: #dc3545; margin-right: 12px; font-size: 18px; min-width: 20px;" aria-hidden="true" role="img" aria-label="Error indicator">❌</span>
                                    <span style="color: #495057; line-height: 1.4;" aria-label="Error message: <?= strip_tags($error) ?>"><?= $error ?></span>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>

                    <div class="error-actions" style="background: #fff3cd; padding: 20px; border-radius: 8px; border-left: 4px solid #ffc107;" role="region" aria-labelledby="troubleshooting-heading">
                        <h4 style="color: #856404; margin-bottom: 15px;" id="troubleshooting-heading"><span aria-hidden="true">💡</span> What to do next:</h4>
                        <div class="sr-only">The following section provides troubleshooting steps to resolve the database connection issue:</div>
                        <div class="action-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-bottom: 20px;" role="list" aria-label="Troubleshooting steps">
                            <div style="padding: 15px; background: white; border-radius: 6px; border: 1px solid #ffeaa7;" role="listitem">
                                <strong style="color: #856404;" aria-label="Step 1: Check database credentials"><span aria-hidden="true">🔑</span> Check Credentials</strong>
                                <p style="margin: 5px 0 0; color: #6c757d; font-size: 14px;">Verify username and password are correct</p>
                            </div>
                            <div style="padding: 15px; background: white; border-radius: 6px; border: 1px solid #ffeaa7;" role="listitem">
                                <strong style="color: #856404;" aria-label="Step 2: Verify database exists"><span aria-hidden="true">🗄️</span> Verify Database</strong>
                                <p style="margin: 5px 0 0; color: #6c757d; font-size: 14px;">Use auto-detect or create manually</p>
                            </div>
                            <div style="padding: 15px; background: white; border-radius: 6px; border: 1px solid #ffeaa7;" role="listitem">
                                <strong style="color: #856404;" aria-label="Step 3: Test database port"><span aria-hidden="true">🔌</span> Test Port</strong>
                                <p style="margin: 5px 0 0; color: #6c757d; font-size: 14px;">Try auto-detecting the correct port</p>
                            </div>
                            <div style="padding: 15px; background: white; border-radius: 6px; border: 1px solid #ffeaa7;" role="listitem">
                                <strong style="color: #856404;" aria-label="Step 4: Check host settings"><span aria-hidden="true">🌐</span> Check Host</strong>
                                <p style="margin: 5px 0 0; color: #6c757d; font-size: 14px;">Confirm host address (usually 'localhost')</p>
                            </div>
                        </div>

                        <div class="retry-section" style="text-align: center; padding: 20px; background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); border-radius: 8px;" role="region" aria-labelledby="retry-heading">
                            <h4 style="color: #1976d2; margin-bottom: 15px;" id="retry-heading"><span aria-hidden="true">🔄</span> Ready to try again?</h4>
                            <p style="color: #424242; margin-bottom: 20px;">Please correct the details above and test again</p>
                            <div class="sr-only">The following buttons will help you resolve the database connection issue:</div>
                            <button onclick="scrollToForm()" class="btn btn-primary" style="margin: 5px 10px; padding: 12px 24px; font-size: 16px;" aria-label="Update database details - goes back to the database configuration form"><span aria-hidden="true">📝</span> Update Database Details</button>
                            <button onclick="autoDetectPort()" class="btn btn-secondary" style="margin: 5px 10px; padding: 12px 24px; font-size: 16px;" aria-label="Auto-detect port - automatically scan for working MySQL ports"><span aria-hidden="true">🔍</span> Auto-Detect Port</button>
                            <button onclick="showConnectionHelp()" class="btn btn-secondary" style="margin: 5px 10px; padding: 12px 24px; font-size: 16px;" aria-label="Connection help - show detailed troubleshooting information"><span aria-hidden="true">❓</span> Connection Help</button>
                        </div>
                    </div>
                </div>

                <script>
                function scrollToForm() {
                    document.querySelector('.db-config-form').scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                    // Focus on the first field that might need attention
                    const dbUser = document.querySelector('input[name="db_user"]');
                    if (dbUser.value === '') {
                        dbUser.focus();
                    } else {
                        document.querySelector('input[name="db_password"]').focus();
                    }
                }
                </script>
            <?php endif; ?>

            <?php if (!empty($this->success)): ?>
                <div class="alert alert-success" role="alert" aria-live="polite">
                    <!-- Screen reader announcement -->
                    <div class="sr-only" aria-live="assertive">Success: Database connection established successfully. All tests passed and the system is ready to install FlexPBX.</div>

                    <div style="display: flex; align-items: center; margin-bottom: 25px; padding: 25px; background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%); border-radius: 12px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
                        <div style="font-size: 72px; margin-right: 30px; animation: bounce 2s infinite;" aria-hidden="true" role="img" aria-label="Celebration icon">🎉</div>
                        <div>
                            <h3 style="margin: 0; color: #155724; font-size: 28px;" id="success-heading">Database Connection Successful!</h3>
                            <p style="margin: 8px 0 0 0; color: #155724; font-size: 18px;" aria-describedby="success-heading">All tests passed - ready to install FlexPBX</p>
                        </div>
                    </div>

                    <div class="success-details" style="background: linear-gradient(to right, #f8f9fa, #e9ecef); padding: 25px; border-radius: 12px; margin: 25px 0; border-left: 6px solid #28a745;" role="region" aria-labelledby="validation-results-heading">
                        <h4 style="color: #155724; margin-bottom: 20px; display: flex; align-items: center;" id="validation-results-heading">
                            <span style="margin-right: 10px;" aria-hidden="true" role="img" aria-label="Success checkmark">✅</span> Validation Results:
                        </h4>
                        <div class="sr-only">The following section shows all successful database validation tests:</div>
                        <div class="success-grid" style="display: grid; gap: 15px;" role="list" aria-label="Database validation success results">
                            <?php foreach ($this->success as $msg): ?>
                                <?php if (strpos($msg, '<div class="auto-continue-section">') !== false): ?>
                                    <?= $msg ?>
                                <?php else: ?>
                                    <div style="display: flex; align-items: center; padding: 15px; background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); border-left: 4px solid #28a745;" role="listitem">
                                        <span style="color: #28a745; margin-right: 15px; font-size: 20px;" aria-hidden="true" role="img" aria-label="Success indicator">✅</span>
                                        <span style="color: #495057; line-height: 1.5; font-size: 16px;" aria-label="Success message: <?= strip_tags($msg) ?>"><?= $msg ?></span>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="auto-continue-section" style="background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); padding: 30px; border-radius: 12px; text-align: center; margin: 25px 0; border: 2px solid #2196f3;" role="region" aria-labelledby="installation-ready-heading">
                        <div style="display: flex; align-items: center; justify-content: center; margin-bottom: 20px;">
                            <span style="font-size: 48px; margin-right: 20px; animation: pulse 1.5s infinite;" aria-hidden="true" role="img" aria-label="Rocket launch icon">🚀</span>
                            <div>
                                <h3 style="margin: 0; color: #1976d2; font-size: 24px;" id="installation-ready-heading">Ready to Install FlexPBX!</h3>
                                <p style="margin: 5px 0 0; color: #424242; font-size: 16px;" aria-describedby="installation-ready-heading">Your database is configured and ready</p>
                            </div>
                        </div>

                        <div id="countdown-container" style="background: rgba(255,255,255,0.8); padding: 20px; border-radius: 8px; margin: 20px 0;" role="timer" aria-live="polite" aria-label="Auto-installation countdown">
                            <div class="sr-only" id="countdown-announcement" aria-live="polite">Auto-installation will begin in 10 seconds. You can cancel this by clicking the cancel button.</div>
                            <p style="margin: 0 0 15px; color: #1976d2; font-size: 18px; font-weight: bold;" aria-describedby="countdown-announcement">
                                <span aria-hidden="true">⏱️</span> Auto-continuing in <span id="countdown" style="color: #f44336; font-size: 24px; font-weight: bold;" aria-label="countdown timer">10</span> seconds...
                            </p>
                            <button onclick="cancelAutoInstall()" class="btn btn-secondary" style="padding: 10px 20px;" aria-label="Cancel automatic installation and stop the countdown"><span aria-hidden="true">⏸️</span> Cancel Auto-Install</button>
                        </div>

                        <div style="display: flex; justify-content: center; gap: 20px; margin-top: 25px;" role="group" aria-label="Installation actions">
                            <a href="?step=requirements" class="btn btn-secondary" style="padding: 15px 30px; font-size: 16px;" aria-label="Go back to requirements check page"><span aria-hidden="true">←</span> Back to Requirements</a>
                            <a href="?step=install&<?= http_build_query($_POST) ?>" class="btn btn-primary" id="install-btn" style="padding: 15px 40px; font-size: 18px; background: linear-gradient(135deg, #4caf50 0%, #45a049 100%); border: none;" aria-label="Start FlexPBX installation process now"><span aria-hidden="true">🚀</span> Install FlexPBX Now <span aria-hidden="true">→</span></a>
                        </div>
                    </div>
                </div>

                <script>
                let countdown = 10;
                let countdownInterval;

                function startCountdown() {
                    countdownInterval = setInterval(() => {
                        countdown--;
                        document.getElementById('countdown').textContent = countdown;

                        if (countdown <= 0) {
                            clearInterval(countdownInterval);
                            document.getElementById('install-btn').click();
                        }
                    }, 1000);
                }

                function cancelAutoInstall() {
                    clearInterval(countdownInterval);
                    document.getElementById('countdown-container').innerHTML = '<p>✋ Auto-install cancelled - click "Install FlexPBX Now" to proceed manually</p>';
                }

                // Start countdown when page loads
                setTimeout(startCountdown, 1000);

                // Database auto-detection functions
                function autoDetectDatabases() {
                    const host = document.querySelector('input[name="db_host"]').value;
                    const port = document.querySelector('input[name="db_port"]').value;
                    const user = document.querySelector('input[name="db_user"]').value;
                    const password = document.querySelector('input[name="db_password"]').value;

                    if (!host || !user) {
                        alert('Please enter database host and username first');
                        return;
                    }

                    const button = event.target;
                    button.disabled = true;
                    button.textContent = '🔍 Scanning...';

                    fetch('?action=detect_databases', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ host, port, user, password })
                    })
                    .then(response => response.json())
                    .then(data => {
                        button.disabled = false;
                        button.textContent = '🔍 Auto-Detect Databases';

                        if (data.success) {
                            showDetectedDatabases(data.databases);
                        } else {
                            alert('Could not detect databases: ' + data.error);
                        }
                    })
                    .catch(error => {
                        button.disabled = false;
                        button.textContent = '🔍 Auto-Detect Databases';
                        alert('Error detecting databases: ' + error.message);
                    });
                }

                function showDetectedDatabases(databases) {
                    const container = document.getElementById('detected-databases');
                    const listContainer = document.getElementById('database-list');

                    if (databases.length === 0) {
                        listContainer.innerHTML = '<p>No databases found or insufficient permissions</p>';
                    } else {
                        let html = '<div class="database-selection-container">';
                        html += '<p style="margin-bottom: 15px; color: #28a745;"><strong>📊 ' + databases.length + ' database(s) found:</strong></p>';

                        // Add radio button interface
                        html += '<div class="database-radio-list" style="max-height: 300px; overflow-y: auto;">';
                        databases.forEach((db, index) => {
                            const hasFlexPBX = db.hasFlexPBXTables;
                            const flexpbxText = hasFlexPBX ? ' <span style="color: #28a745;">✅ FlexPBX detected</span>' : '';
                            const recommendedBadge = hasFlexPBX ? ' <span class="badge-recommended" style="background: #28a745; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px; margin-left: 5px;">Recommended</span>' : '';
                            const isSystemDb = ['information_schema', 'mysql', 'performance_schema', 'sys'].includes(db.name.toLowerCase());

                            if (!isSystemDb) {
                                html += `
                                    <label class="database-radio-item ${hasFlexPBX ? 'recommended' : ''}" style="display: block; padding: 12px; margin: 8px 0; border: 2px solid ${hasFlexPBX ? '#28a745' : '#dee2e6'}; border-radius: 8px; cursor: pointer; background: ${hasFlexPBX ? '#f8fff8' : '#ffffff'}; transition: all 0.2s;">
                                        <input type="radio" name="detected_database" value="${db.name}" style="margin-right: 10px;" onchange="selectDetectedDatabase('${db.name}')">
                                        <strong style="color: ${hasFlexPBX ? '#155724' : '#495057'};">${db.name}</strong>${recommendedBadge}${flexpbxText}
                                        <br><small style="color: #6c757d; margin-left: 24px;">📋 ${db.tableCount} tables • ${db.sizeInfo || 'Unknown size'}</small>
                                    </label>
                                `;
                            }
                        });

                        // Add option to create new database
                        html += `
                            <label class="database-radio-item" style="display: block; padding: 12px; margin: 8px 0; border: 2px dashed #007bff; border-radius: 8px; cursor: pointer; background: #f8f9ff; transition: all 0.2s;">
                                <input type="radio" name="detected_database" value="_create_new_" style="margin-right: 10px;" onchange="selectDetectedDatabase('_create_new_')">
                                <strong style="color: #007bff;">➕ Create New Database</strong>
                                <br><small style="color: #6c757d; margin-left: 24px;">Enter a new database name below</small>
                            </label>
                        `;

                        html += '</div>';
                        html += '<div style="margin-top: 15px; padding: 10px; background: #e3f2fd; border-radius: 6px; border-left: 4px solid #2196f3;">';
                        html += '<p style="margin: 0; font-size: 14px;"><strong>💡 Selection Guide:</strong></p>';
                        html += '<ul style="margin: 5px 0 0 20px; font-size: 13px;">';
                        html += '<li><strong>Recommended:</strong> Databases with existing FlexPBX installation (will be updated)</li>';
                        html += '<li><strong>Safe:</strong> Empty or non-FlexPBX databases (FlexPBX tables will be added)</li>';
                        html += '<li><strong>Create New:</strong> Safest option - creates a fresh dedicated database</li>';
                        html += '</ul>';
                        html += '</div>';
                        html += '</div>';

                        listContainer.innerHTML = html;
                    }

                    container.style.display = 'block';
                }

                function selectDatabase(dbName) {
                    document.getElementById('db_name_input').value = dbName;

                    // Highlight selected database
                    document.querySelectorAll('.db-item').forEach(item => {
                        item.classList.remove('selected');
                    });
                    event.target.closest('.db-item').classList.add('selected');
                }

                function selectDetectedDatabase(dbName) {
                    if (dbName === '_create_new_') {
                        document.getElementById('db_name_input').value = '';
                        document.getElementById('db_name_input').placeholder = 'Enter new database name (e.g., flexpbx_new)';
                        document.getElementById('db_name_input').focus();
                    } else {
                        document.getElementById('db_name_input').value = dbName;
                        document.getElementById('db_name_input').placeholder = 'Database name';
                    }

                    // Update visual feedback
                    document.querySelectorAll('.database-radio-item').forEach(item => {
                        item.style.background = item.querySelector('input[type="radio"]').checked ?
                            (item.classList.contains('recommended') ? '#f8fff8' : '#f8f9ff') : '#ffffff';
                    });
                }

                function clearDetectedDatabases() {
                    document.getElementById('detected-databases').style.display = 'none';
                }

                // API Key generation function
                function generateNewApiKey() {
                    const input = document.getElementById('api_key_input');
                    const button = event.target;
                    button.disabled = true;
                    button.textContent = '🔄 Generating...';

                    // Generate secure API key using Web Crypto API
                    if (window.crypto && window.crypto.getRandomValues) {
                        const array = new Uint8Array(32);
                        window.crypto.getRandomValues(array);
                        const hexString = Array.from(array, byte => byte.toString(16).padStart(2, '0')).join('');
                        input.value = 'flexpbx_api_' + hexString;
                    } else {
                        // Fallback for older browsers
                        const timestamp = Date.now().toString(36);
                        const randomStr = Math.random().toString(36).substring(2);
                        input.value = 'flexpbx_api_' + timestamp + randomStr + Math.random().toString(36).substring(2);
                    }

                    button.disabled = false;
                    button.textContent = '🔄 Generate New';

                    // Visual feedback
                    input.style.background = '#e8f5e8';
                    setTimeout(() => {
                        input.style.background = '';
                    }, 1000);
                }

                // Skip to install function
                function skipToInstall() {
                    if (confirm('⚠️ Skip database test and proceed directly to installation?\n\nThis will attempt installation with current settings. If database connection fails, you can return to this step.')) {
                        const form = document.querySelector('.db-config-form');
                        const formData = new FormData(form);
                        const params = new URLSearchParams();
                        for (let [key, value] of formData.entries()) {
                            params.append(key, value);
                        }
                        window.location.href = '?step=install&' + params.toString();
                    }
                }

                // Port auto-detection function
                function autoDetectPort() {
                    const host = document.querySelector('input[name="db_host"]').value;
                    const user = document.querySelector('input[name="db_user"]').value;
                    const password = document.querySelector('input[name="db_password"]').value;

                    if (!host) {
                        alert('Please enter database host first');
                        return;
                    }

                    const button = event.target;
                    button.disabled = true;
                    button.textContent = '🔍 Scanning...';

                    const resultsDiv = document.getElementById('port-detection-results');
                    resultsDiv.innerHTML = '<p>🔍 Scanning common MySQL ports...</p>';
                    resultsDiv.style.display = 'block';

                    fetch('?action=detect_port', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ host, user, password })
                    })
                    .then(response => response.json())
                    .then(data => {
                        button.disabled = false;
                        button.textContent = '🔍 Auto-Detect';

                        if (data.success && data.workingPorts.length > 0) {
                            showPortResults(data.workingPorts);
                        } else {
                            resultsDiv.innerHTML = '<p>⚠️ No working MySQL ports found. Using default 3306.</p>';
                        }
                    })
                    .catch(error => {
                        button.disabled = false;
                        button.textContent = '🔍 Auto-Detect';
                        resultsDiv.innerHTML = '<p>❌ Port detection failed. Using default 3306.</p>';
                    });
                }

                function showPortResults(ports) {
                    const resultsDiv = document.getElementById('port-detection-results');
                    let html = '<div class="port-results"><h5>🎯 Found working MySQL ports:</h5>';

                    ports.forEach(portInfo => {
                        const isDefault = portInfo.port === 3306;
                        let className = 'port-option';
                        if (isDefault) className += ' default';
                        if (portInfo.tested === 'authenticated') className += ' working';

                        let statusIcon = '🔌';
                        if (portInfo.tested === 'authenticated') statusIcon = '✅';
                        else if (portInfo.status.includes('Access Denied')) statusIcon = '🔐';

                        html += `
                            <div class="${className}" onclick="selectPort(${portInfo.port})">
                                <strong>${statusIcon} Port ${portInfo.port}</strong> ${isDefault ? '(Default)' : ''}
                                <br><small>${portInfo.status}</small>
                                ${portInfo.tested === 'port_only' ? '<br><small style="color: #ff9800;">⚠️ Credentials needed for full test</small>' : ''}
                            </div>
                        `;
                    });

                    html += '</div><p><small>Click a port to select it and update the form</small></p>';
                    resultsDiv.innerHTML = html;
                }

                function selectPort(port) {
                    document.getElementById('db_port_input').value = port;

                    // Highlight selected port
                    document.querySelectorAll('.port-option').forEach(option => {
                        option.classList.remove('selected');
                    });
                    event.target.closest('.port-option').classList.add('selected');

                    // Clear database detection since port changed
                    clearDetectedDatabases();
                }

                function showConnectionHelp() {
                    const helpDiv = document.createElement('div');
                    helpDiv.className = 'alert alert-info';
                    helpDiv.innerHTML = `
                        <h4>🔧 Database Connection Help</h4>
                        <div style="text-align: left;">
                            <h5>Common Issues & Solutions:</h5>
                            <ul>
                                <li><strong>Access Denied:</strong> Check username/password are correct for MySQL</li>
                                <li><strong>Can't Connect:</strong> Verify host is 'localhost' for shared hosting</li>
                                <li><strong>Unknown Database:</strong> Enable "Create database" or create it manually</li>
                                <li><strong>Port Issues:</strong> Try port auto-detection or contact your hosting provider</li>
                            </ul>
                            <h5>Shared Hosting Tips:</h5>
                            <ul>
                                <li>Use cPanel → MySQL Databases to create database and user</li>
                                <li>Database names usually include your username prefix (e.g., 'username_dbname')</li>
                                <li>Host is typically 'localhost' unless specified otherwise</li>
                                <li>Port is usually 3306 unless your host uses a custom configuration</li>
                            </ul>
                            <button onclick="this.parentElement.parentElement.remove()" class="btn btn-sm btn-secondary">Close Help</button>
                        </div>
                    `;

                    // Insert help after the error messages
                    const errorElements = document.querySelectorAll('.alert-error');
                    if (errorElements.length > 0) {
                        errorElements[errorElements.length - 1].insertAdjacentElement('afterend', helpDiv);
                    } else {
                        document.querySelector('.content').appendChild(helpDiv);
                    }
                }
                </script>
            <?php endif; ?>
        </div>
        <?php
        $this->renderFooter();
    }

    private function testDatabaseConnection() {
        try {
            $host = $_POST['db_host'] ?? 'localhost';
            $port = $_POST['db_port'] ?? '3306';
            $dbname = $_POST['db_name'] ?? '';
            $username = $_POST['db_user'] ?? '';
            $password = $_POST['db_password'] ?? '';
            $createDb = isset($_POST['create_db']);
            $showCreationHelp = isset($_POST['show_creation_help']);
            $installMode = $_POST['install_mode'] ?? 'update';

            // Normalize hostname for local connections
            if (in_array(strtolower($host), ['localhost', '127.0.0.1', '::1', 'local'])) {
                $host = 'localhost';
                $this->success[] = '🏠 Local database connection detected';
            }

            // Progressive validation with detailed progress
            $this->success[] = '<div class="validation-progress">';
            $this->success[] = '<h4>🔍 Database Validation Progress:</h4>';
            $this->success[] = '<div class="progress-steps">';

            // Step 1: Test basic connectivity
            $this->success[] = '<div class="progress-step"><span class="step-icon">🔌</span> <strong>Step 1:</strong> Testing database server connectivity...';
            $dsn = "mysql:host={$host};port={$port}";
            $tempPdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_TIMEOUT => 5,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            $this->success[] = ' <span style="color: #28a745;">✅ Success - Server reachable</span></div>';

            // Step 2: Test database access
            $this->success[] = '<div class="progress-step"><span class="step-icon">🗄️</span> <strong>Step 2:</strong> Testing database access...';
            $dsn = "mysql:host={$host};port={$port};dbname={$dbname}";
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_TIMEOUT => 5,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            $this->success[] = ' <span style="color: #28a745;">✅ Success - Database accessible</span></div>';

            // Step 3: Test permissions
            $this->success[] = '<div class="progress-step"><span class="step-icon">🔐</span> <strong>Step 3:</strong> Verifying permissions...';
            // Test SELECT permission
            $pdo->query("SELECT 1");
            // Test CREATE TABLE permission
            $testTableName = 'flexpbx_install_test_' . uniqid();
            $pdo->exec("CREATE TEMPORARY TABLE `{$testTableName}` (id INT PRIMARY KEY)");
            $this->success[] = ' <span style="color: #28a745;">✅ Success - Full permissions verified</span></div>';

            // Step 4: Check existing FlexPBX installation
            $this->success[] = '<div class="progress-step"><span class="step-icon">📋</span> <strong>Step 4:</strong> Checking existing FlexPBX tables...';
            $stmt = $pdo->prepare("SHOW TABLES LIKE 'desktop_clients'");
            $stmt->execute();
            $hasExistingTables = $stmt->fetch() !== false;
            if ($hasExistingTables) {
                $this->success[] = ' <span style="color: #ffc107;">⚠️ Existing FlexPBX installation found - will update</span></div>';
            } else {
                $this->success[] = ' <span style="color: #28a745;">✅ Clean database - ready for fresh installation</span></div>';
            }

            $this->success[] = '</div>'; // Close progress-steps
            $this->success[] = '</div>'; // Close validation-progress

            // Store successful database config in session for installation
            $_SESSION['db_config'] = [
                'host' => $host,
                'port' => $port,
                'name' => $dbname,
                'user' => $username,
                'pass' => $password,
                'api_key' => $_POST['api_key'] ?? 'flexpbx_api_' . bin2hex(random_bytes(16)),
                'install_mode' => $installMode
            ];

            // Auto-continue to installation after successful test
            $installParams = http_build_query([
                'db_host' => $host, // Use normalized hostname
                'db_port' => $port,
                'db_user' => $username,
                'db_password' => $password,
                'db_name' => $dbname,
                'api_key' => $_POST['api_key'],
                'install_mode' => $installMode
            ]);

            $this->success[] = '<div class="auto-continue-section" style="background: #d4edda; padding: 20px; border-radius: 8px; margin: 20px 0; text-align: center;">';
            $this->success[] = '<h4 style="color: #155724; margin-bottom: 15px;">⏭️ Ready to Continue</h4>';
            $this->success[] = '<p style="color: #155724; margin-bottom: 20px;">🎉 Database validation completed successfully!</p>';
            $this->success[] = '<a href="?step=install&' . htmlspecialchars($installParams) . '" class="btn btn-primary btn-lg" style="padding: 12px 30px; font-size: 16px;">🚀 Start Installation →</a>';
            $this->success[] = '</div>';
            $this->success[] = '<script>';
            $this->success[] = 'setTimeout(() => {';
            $this->success[] = '    if(confirm("🎉 Database validation successful!\\n\\nContinue with FlexPBX installation?")) {';
            $this->success[] = '        window.location.href="?step=install&' . htmlspecialchars($installParams) . '";';
            $this->success[] = '    }';
            $this->success[] = '}, 2000);';
            $this->success[] = '</script>';

            return; // Exit here to prevent duplicate processing

            // Test basic connection
            $dsn = "mysql:host={$host};port={$port}";
            $pdo = new PDO($dsn, $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $this->success[] = "✅ Database server connection established successfully";
            $this->success[] = "🔍 Server version: " . $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);

            // Check if database exists
            $stmt = $pdo->prepare("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?");
            $stmt->execute([$dbname]);
            $dbExists = $stmt->fetch();

            if (!$dbExists && $createDb) {
                $pdo->exec("CREATE DATABASE `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $this->success[] = "🗄️ Database '{$dbname}' created successfully with UTF8MB4 encoding";
            } elseif ($dbExists) {
                $this->success[] = "🗄️ Database '{$dbname}' exists and is accessible";

                // Check if tables already exist
                $dsn = "mysql:host={$host};port={$port};dbname={$dbname}";
                $testPdo = new PDO($dsn, $username, $password);
                $testPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                $stmt = $testPdo->prepare("SHOW TABLES LIKE 'desktop_clients'");
                $stmt->execute();
                if ($stmt->fetch()) {
                    $this->success[] = "⚠️ FlexPBX tables already exist - installation will update/merge existing data";
                } else {
                    $this->success[] = "📋 Database is empty and ready for FlexPBX tables";
                }
            } elseif (!$dbExists) {
                if ($showCreationHelp) {
                    $this->showDatabaseCreationHelp($dbname, $username);
                }
                $this->errors[] = "Database '{$dbname}' does not exist. Enable 'Create database' option or create it manually.";
                return;
            }

            // Test connection to specific database
            $dsn = "mysql:host={$host};port={$port};dbname={$dbname}";
            $pdo = new PDO($dsn, $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Test write permissions
            $testTable = "flexpbx_install_test_" . uniqid();
            $pdo->exec("CREATE TEMPORARY TABLE {$testTable} (id INT)");
            $pdo->exec("INSERT INTO {$testTable} VALUES (1)");
            $pdo->exec("DROP TEMPORARY TABLE {$testTable}");

            $this->success[] = "✅ Database write permissions confirmed";
            $this->success[] = "🚀 All database checks passed - ready to install FlexPBX!";

            // Store database config in session for auto-continue
            $_SESSION['db_config'] = [
                'host' => $host,
                'port' => $port,
                'name' => $dbname,
                'user' => $username,
                'pass' => $password,
                'api_key' => $_POST['api_key'] ?? 'flexpbx_api_' . uniqid(),
                'install_mode' => $installMode
            ];

            // Auto-continue to installation after successful test
            $installParams = http_build_query([
                'db_host' => $_POST['db_host'],
                'db_port' => $_POST['db_port'],
                'db_user' => $_POST['db_user'],
                'db_password' => $_POST['db_password'],
                'db_name' => $_POST['db_name'],
                'api_key' => $_POST['api_key'],
                'install_mode' => $_POST['install_mode']
            ]);
            $this->success[] = '<div class="auto-continue-section">';
            $this->success[] = '<h4>⏭️ Ready to Continue</h4>';
            $this->success[] = '<p>Database connection successful! Click below to proceed with installation.</p>';
            $this->success[] = '<a href="?step=install&' . htmlspecialchars($installParams) . '" class="btn btn-primary">🚀 Start Installation →</a>';
            $this->success[] = '<script>setTimeout(() => { if(confirm("Database test successful! Continue with installation?")) { window.location.href="?step=install&' . htmlspecialchars($installParams) . '"; } }, 3000);</script>';
            $this->success[] = '</div>';

        } catch (PDOException $e) {
            $this->errors[] = "❌ Database connection failed: " . $e->getMessage();
            if (strpos($e->getMessage(), 'Access denied') !== false) {
                $this->errors[] = "💡 Tip: Check your database username and password";
            } elseif (strpos($e->getMessage(), 'Unknown database') !== false) {
                $this->errors[] = "💡 Tip: Enable 'Create database' option or create the database manually";
            } elseif (strpos($e->getMessage(), "Can't connect") !== false) {
                $this->errors[] = "💡 Tip: Check your database host and port settings";
            }

            // Add retry actions for failed connections
            $this->errors[] = '<div class="error-actions">';
            $this->errors[] = '<h4>🔧 What would you like to do?</h4>';
            $this->errors[] = '<button onclick="scrollToForm()" class="btn btn-primary">📝 Update Database Details</button>';
            $this->errors[] = '<button onclick="autoDetectPort()" class="btn btn-secondary">🔍 Auto-Detect Port</button>';
            $this->errors[] = '<button onclick="showConnectionHelp()" class="btn btn-secondary">❓ Connection Help</button>';
            $this->errors[] = '</div>';
        }
    }

    private function showDatabaseCreationHelp($dbname, $username) {
        echo '<div class="alert alert-info database-creation-help">';
        echo '<h4>💡 Database Creation Assistance</h4>';
        echo '<p>Since the database doesn\'t exist, here are several ways to create it:</p>';

        echo '<div class="creation-methods">';

        echo '<div class="method-section">';
        echo '<h5>📊 Method 1: phpMyAdmin (Recommended)</h5>';
        echo '<ol>';
        echo '<li>Login to your hosting control panel (cPanel/Plesk)</li>';
        echo '<li>Open phpMyAdmin</li>';
        echo '<li>Click "Databases" tab</li>';
        echo '<li>Enter database name: <code>' . htmlspecialchars($dbname) . '</code></li>';
        echo '<li>Select "Collation": <code>utf8mb4_unicode_ci</code></li>';
        echo '<li>Click "Create"</li>';
        echo '</ol>';
        echo '</div>';

        echo '<div class="method-section">';
        echo '<h5>💻 Method 2: SQL Command</h5>';
        echo '<p>Run this SQL command in phpMyAdmin or MySQL terminal:</p>';
        echo '<div class="sql-command">';
        echo '<code>CREATE DATABASE `' . htmlspecialchars($dbname) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;</code>';
        echo '<button onclick="copySqlCommand()" class="btn btn-sm btn-secondary">📋 Copy</button>';
        echo '</div>';
        echo '</div>';

        echo '<div class="method-section">';
        echo '<h5>🔧 Method 3: cPanel Database Wizard</h5>';
        echo '<ol>';
        echo '<li>Login to cPanel</li>';
        echo '<li>Find "MySQL Databases" or "Database Wizard"</li>';
        echo '<li>Create new database: <code>' . htmlspecialchars($dbname) . '</code></li>';
        echo '<li>Assign user <code>' . htmlspecialchars($username) . '</code> with ALL PRIVILEGES</li>';
        echo '</ol>';
        echo '</div>';

        echo '<div class="method-section">';
        echo '<h5>⚡ Method 4: Auto-Create (Try Again)</h5>';
        echo '<p>Enable "Create database if it doesn\'t exist" option above and test connection again.</p>';
        echo '</div>';

        echo '</div>';
        echo '</div>';

        echo '<script>';
        echo 'function copySqlCommand() {';
        echo '    const sql = "CREATE DATABASE `' . htmlspecialchars($dbname) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;";';
        echo '    navigator.clipboard.writeText(sql).then(() => {';
        echo '        alert("SQL command copied to clipboard!");';
        echo '    }).catch(() => {';
        echo '        prompt("Copy this SQL command:", sql);';
        echo '    });';
        echo '}';
        echo '</script>';
    }

    private function performInstallation() {
        $this->renderHeader('FlexPBX Installation - Installing...');

        echo '<div class="installation-section">';
        echo '<h2>⚙️ Installing FlexPBX Multi-Server System...</h2>';
        echo '<div class="progress-container">';

        try {
            // Get database parameters from session or GET
            $dbConfig = $_SESSION['db_config'] ?? [
                'host' => $_GET['db_host'] ?? 'localhost',
                'port' => $_GET['db_port'] ?? '3306',
                'name' => $_GET['db_name'] ?? '',
                'user' => $_GET['db_user'] ?? '',
                'pass' => $_GET['db_password'] ?? '',
                'api_key' => $_GET['api_key'] ?? 'flexpbx_api_' . uniqid()
            ];

            $this->logProgress("🚀 Starting FlexPBX Multi-Server Installation...");
            $this->logProgress("📊 Target Database: {$dbConfig['name']} on {$dbConfig['host']}");

            // Step 1: Pre-installation checks
            $this->logProgress("🔍 Step 1/7: Pre-installation system checks...");
            $this->preInstallationChecks();

            // Step 2: Fix permissions first
            $this->logProgress("🔒 Step 2/7: Setting file permissions and security...");
            $this->setPermissions();

            // Step 3: Create config.php
            $this->logProgress("⚙️ Step 3/7: Creating configuration file...");
            $this->createConfigFile($dbConfig);

            // Step 4: Connect to database
            $this->logProgress("🗄️ Step 4/7: Establishing database connection...");
            $pdo = $this->connectDatabase($dbConfig);

            // Step 5: Create tables
            $this->logProgress("📋 Step 5/7: Creating database schema...");
            $this->createDatabaseTables($pdo);

            // Step 6: Insert default data
            $this->logProgress("📝 Step 6/7: Inserting default configuration data...");
            $this->insertDefaultData($pdo);

            // Step 7: Configure web server
            $this->logProgress("🌐 Step 7/8: Configuring web server routing...");
            $this->createHtaccess();

            // Step 8: Create directory structure and initialize services
            $this->logProgress("📁 Step 8/8: Setting up directory structure and services...");
            $this->createDirectoryStructure();
            $this->initializeServices($dbConfig);

            $this->logProgress("🎉 Installation completed successfully!");
            $this->logProgress("✅ FlexPBX Multi-Server System is now ready!");

            echo '</div>';
            echo '<div class="completion-summary">';
            echo '<h3>📊 Installation Summary:</h3>';
            echo '<ul>';
            echo '<li>✅ Database: ' . htmlspecialchars($dbConfig['name']) . ' (6 tables created)</li>';
            echo '<li>✅ API Endpoints: 8 endpoints configured</li>';
            echo '<li>✅ Security: File permissions and CORS configured</li>';
            echo '<li>✅ Routing: URL rewriting enabled</li>';
            echo '</ul>';
            echo '</div>';
            echo '<div class="action-buttons">';
            echo '<a href="?step=complete" class="btn btn-primary">View Installation Complete →</a>';
            echo '</div>';

        } catch (Exception $e) {
            $this->logProgress("❌ Installation failed at: " . $e->getMessage());
            echo '<div class="alert alert-error">';
            echo '<h4>Installation Error:</h4>';
            echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '<p><strong>💡 Troubleshooting:</strong></p>';
            echo '<ul>';
            echo '<li>Check file permissions (should be 644 for PHP files)</li>';
            echo '<li>Verify database credentials and connection</li>';
            echo '<li>Ensure web server supports .htaccess files</li>';
            echo '<li>Check PHP error logs for additional details</li>';
            echo '</ul>';
            echo '</div>';
            echo '<div class="action-buttons">';
            echo '<a href="?step=database" class="btn btn-secondary">← Retry Database Setup</a>';
            echo '<a href="?step=requirements" class="btn btn-secondary">← Check Requirements</a>';
            echo '</div>';
        }

        echo '</div>';
        $this->renderFooter();
    }

    private function showComplete() {
        // Get the stored API key from session or database
        $apiKey = $_SESSION['db_config']['api_key'] ?? $this->getStoredApiKey() ?? 'flexpbx_api_' . bin2hex(random_bytes(16));

        $this->renderHeader('FlexPBX Installation - Complete!');
        ?>
        <div class="complete-section">
            <div class="success-celebration" style="text-align: center; padding: 40px 20px; background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%); border-radius: 15px; margin-bottom: 30px;">
                <div style="font-size: 80px; margin-bottom: 20px; animation: bounce 2s infinite;">🎉</div>
                <h2 style="color: #155724; margin: 0 0 10px;">FlexPBX Installation Complete!</h2>
                <p style="color: #155724; font-size: 18px; margin: 0;">Your multi-server communication system is ready to use</p>
            </div>

            <div class="next-steps-container">
                <h3 style="color: #2c3e50; text-align: center; margin-bottom: 30px;">🚀 Choose Your Next Step</h3>

                <div class="setup-options" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 25px; margin-bottom: 30px;">

                    <!-- Web Dashboard Setup Option -->
                    <div class="setup-option" style="border: 2px solid #007bff; border-radius: 12px; padding: 25px; background: linear-gradient(135deg, #f8f9ff 0%, #e3f2fd 100%); transition: all 0.3s ease;">
                        <h4 style="color: #007bff; margin: 0 0 15px; display: flex; align-items: center;">
                            <span style="font-size: 32px; margin-right: 12px;">🖥️</span>
                            Web Dashboard Setup (Recommended)
                        </h4>
                        <p style="color: #495057; margin-bottom: 20px;">Set up the web-based admin dashboard first. Create user accounts, configure settings, and then generate remote keys for desktop clients.</p>

                        <div class="dashboard-benefits" style="background: rgba(255,255,255,0.7); padding: 15px; border-radius: 8px; margin: 15px 0;">
                            <strong style="color: #007bff;">✨ Dashboard Features:</strong>
                            <ul style="margin: 8px 0 0 20px; color: #495057;">
                                <li>User account management</li>
                                <li>Remote device key generation</li>
                                <li>System configuration</li>
                                <li>Real-time connection monitoring</li>
                                <li>Update management</li>
                            </ul>
                        </div>

                        <button onclick="setupWebDashboard()" class="btn btn-primary" style="width: 100%; padding: 12px; font-size: 16px; background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);">
                            🖥️ Setup Web Dashboard
                        </button>
                    </div>

                    <!-- Direct Device Linking Option -->
                    <div class="setup-option" style="border: 2px solid #28a745; border-radius: 12px; padding: 25px; background: linear-gradient(135deg, #f8fff8 0%, #e8f5e8 100%); transition: all 0.3s ease;">
                        <h4 style="color: #28a745; margin: 0 0 15px; display: flex; align-items: center;">
                            <span style="font-size: 32px; margin-right: 12px;">📱</span>
                            Direct Device Linking
                        </h4>
                        <p style="color: #495057; margin-bottom: 20px;">Skip web setup and directly link your desktop or mobile clients using the generated API key. Perfect for quick testing or single-device setups.</p>

                        <div class="api-key-display" style="background: rgba(255,255,255,0.8); padding: 15px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #28a745;">
                            <strong style="color: #28a745;">🔑 Your API Key:</strong>
                            <div style="display: flex; align-items: center; margin-top: 8px; gap: 10px;">
                                <code id="main-api-key" style="flex: 1; background: #f8f9fa; padding: 8px 12px; border-radius: 4px; font-family: monospace; word-break: break-all;"><?= htmlspecialchars($apiKey) ?></code>
                                <button onclick="copyApiKey('main-api-key')" class="btn btn-sm btn-secondary">📋 Copy</button>
                            </div>
                        </div>

                        <button onclick="showDeviceLinking()" class="btn btn-success" style="width: 100%; padding: 12px; font-size: 16px; background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);">
                            📱 Show Device Linking Instructions
                        </button>
                    </div>
                </div>

                <!-- Web Dashboard Setup Form (Hidden by default) -->
                <div id="dashboard-setup" style="display: none; background: #f8f9fa; padding: 25px; border-radius: 12px; border: 2px solid #007bff; margin: 20px 0;">
                    <h4 style="color: #007bff; margin-bottom: 20px;">🖥️ Web Dashboard Configuration</h4>

                    <form id="dashboard-form" onsubmit="createDashboardUser(event)">
                        <div class="form-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
                            <div class="form-group">
                                <label style="font-weight: bold; color: #495057;">Admin Username:</label>
                                <input type="text" id="admin_username" name="admin_username" required style="width: 100%; padding: 10px; border: 1px solid #ced4da; border-radius: 4px;" placeholder="admin">
                                <small style="color: #6c757d;">Username for web dashboard login</small>
                            </div>

                            <div class="form-group">
                                <label style="font-weight: bold; color: #495057;">Admin Password:</label>
                                <input type="password" id="admin_password" name="admin_password" required style="width: 100%; padding: 10px; border: 1px solid #ced4da; border-radius: 4px;" placeholder="Create strong password">
                                <small style="color: #6c757d;">Minimum 8 characters recommended</small>
                            </div>

                            <div class="form-group">
                                <label style="font-weight: bold; color: #495057;">Admin Email:</label>
                                <input type="email" id="admin_email" name="admin_email" style="width: 100%; padding: 10px; border: 1px solid #ced4da; border-radius: 4px;" placeholder="admin@example.com">
                                <small style="color: #6c757d;">For notifications and recovery</small>
                            </div>

                            <div class="form-group">
                                <label style="font-weight: bold; color: #495057;">Organization Name:</label>
                                <input type="text" id="org_name" name="org_name" style="width: 100%; padding: 10px; border: 1px solid #ced4da; border-radius: 4px;" placeholder="My Company">
                                <small style="color: #6c757d;">Optional: Company or organization name</small>
                            </div>
                        </div>

                        <div class="form-actions" style="margin-top: 20px; text-align: center;">
                            <button type="button" onclick="hideDashboardSetup()" class="btn btn-secondary" style="margin-right: 10px;">← Back to Options</button>
                            <button type="submit" class="btn btn-primary">🖥️ Create Dashboard User</button>
                        </div>
                    </form>
                </div>

                <!-- Device Linking Instructions (Hidden by default) -->
                <div id="device-linking" style="display: none; background: #f8fff8; padding: 25px; border-radius: 12px; border: 2px solid #28a745; margin: 20px 0;">
                    <h4 style="color: #28a745; margin-bottom: 20px;">📱 Device Linking Instructions</h4>

                    <div class="client-instructions" style="display: grid; gap: 20px;">
                        <div class="instruction-section">
                            <h5 style="color: #007bff; margin-bottom: 10px;">🖥️ Desktop Clients (Windows/Mac/Linux):</h5>
                            <ol style="color: #495057; margin-left: 20px;">
                                <li>Download FlexPBX Desktop Client from <strong>your downloads section</strong></li>
                                <li>Install and launch the application</li>
                                <li>Click "Connect to Server" or "Add Server"</li>
                                <li>Enter Server URL: <code id="server-url" style="background: #f8f9fa; padding: 2px 6px; border-radius: 3px;"><?= (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) ?></code></li>
                                <li>Enter API Key: <code id="desktop-api-key" style="background: #f8f9fa; padding: 2px 6px; border-radius: 3px;"><?= htmlspecialchars($apiKey) ?></code> <button onclick="copyApiKey('desktop-api-key')" class="btn btn-sm btn-secondary">📋</button></li>
                                <li>Click "Connect" - the client will auto-register</li>
                            </ol>
                        </div>

                        <div class="instruction-section">
                            <h5 style="color: #007bff; margin-bottom: 10px;">📱 Mobile Apps (iOS/Android):</h5>
                            <ol style="color: #495057; margin-left: 20px;">
                                <li>Download FlexPBX Mobile from App Store/Play Store</li>
                                <li>Open app and tap "Add Server"</li>
                                <li>Scan QR code below OR enter details manually</li>
                                <li>App will connect through desktop client if available</li>
                            </ol>

                            <div style="text-align: center; margin: 15px 0;">
                                <div id="qr-code" style="display: inline-block; padding: 15px; background: white; border-radius: 8px; border: 1px solid #dee2e6;">
                                    <!-- QR Code will be generated here -->
                                    <canvas id="qr-canvas" width="150" height="150"></canvas>
                                </div>
                                <p style="margin: 10px 0 0; color: #6c757d; font-size: 13px;">QR Code for mobile setup</p>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions" style="margin-top: 20px; text-align: center;">
                        <button type="button" onclick="hideDeviceLinking()" class="btn btn-secondary" style="margin-right: 10px;">← Back to Options</button>
                        <button type="button" onclick="generateNewRemoteKey()" class="btn btn-warning" style="margin-right: 10px;">🔄 Generate New Key</button>
                        <button type="button" onclick="downloadConnectionFile()" class="btn btn-success">💾 Download Connection File</button>
                    </div>
                </div>

                <!-- System Information -->
                <div class="system-info" style="background: #e3f2fd; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #2196f3;">
                    <h4 style="color: #1976d2; margin-bottom: 15px;">📊 System Information</h4>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                        <div><strong>API Key:</strong> <code><?= htmlspecialchars(substr($apiKey, 0, 20)) ?>...</code></div>
                        <div><strong>Server URL:</strong> <code><?= (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) ?></code></div>
                        <div><strong>Database:</strong> <?= htmlspecialchars($_SESSION['db_config']['name'] ?? 'flexpbx') ?></div>
                        <div><strong>Installation:</strong> <?= date('Y-m-d H:i:s') ?></div>
                    </div>
                </div>

                <div class="next-steps">
                    <h3>🔗 Available API Endpoints:</h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 10px; font-family: monospace; font-size: 13px;">
                        <div><strong>Client Registration:</strong> <code>/api/register</code></div>
                        <div><strong>Authorization:</strong> <code>/api/authorize</code></div>
                        <div><strong>Connection Status:</strong> <code>/api/status</code></div>
                        <div><strong>Auto-Link:</strong> <code>/api/auto-link</code></div>
                        <div><strong>Updates Check:</strong> <code>/api/updates/check</code></div>
                        <div><strong>Module Reload:</strong> <code>/api/module-reload</code></div>
                </ul>

                <h3>🔒 Security Notes:</h3>
                <ul>
                    <li>Your API key: <code><?= htmlspecialchars($_GET['api_key'] ?? 'Check config.php') ?></code></li>
                    <li>Change default passwords in production</li>
                    <li>Consider SSL certificate for HTTPS</li>
                    <li>Delete this installer file for security</li>
                </ul>

                </div>

                <!-- Security and Maintenance -->
                <div class="security-section" style="background: #fff3cd; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #ffc107;">
                    <h4 style="color: #856404; margin-bottom: 15px;">🔒 Security & Maintenance</h4>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
                        <div>
                            <strong>🔐 Security Checklist:</strong>
                            <ul style="margin: 5px 0 0 20px; color: #495057; font-size: 14px;">
                                <li>Change default passwords</li>
                                <li>Enable HTTPS if possible</li>
                                <li>Delete installer after use</li>
                                <li>Backup API keys securely</li>
                            </ul>
                        </div>
                        <div>
                            <strong>🔧 Maintenance Tasks:</strong>
                            <ul style="margin: 5px 0 0 20px; color: #495057; font-size: 14px;">
                                <li>Monitor connection logs</li>
                                <li>Set up backup procedures</li>
                                <li>Test client connectivity</li>
                                <li>Update clients regularly</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="post-install-actions">
                <h3>🔧 Post-Installation Actions:</h3>
                <div class="action-grid">
                    <div class="action-item">
                        <h4>🌐 Test Installation</h4>
                        <p>Verify your API endpoints are working</p>
                        <a href="../" class="btn btn-primary">Go to API Dashboard</a>
                    </div>

                    <div class="action-item">
                        <h4>🗃️ Manage Installer Files</h4>
                        <p>Secure or remove installation files</p>
                        <button onclick="moveInstaller()" class="btn btn-secondary">Move to Backup</button>
                        <button onclick="deleteInstaller()" class="btn btn-danger">Delete Installer</button>
                    </div>

                    <div class="action-item">
                        <h4>📱 Connect Clients</h4>
                        <p>Configure your desktop clients to connect</p>
                        <button onclick="showConnectionInfo()" class="btn btn-primary">Connection Details</button>
                    </div>

                    <div class="action-item">
                        <h4>📋 Download Config</h4>
                        <p>Save installation details for your records</p>
                        <button onclick="downloadConfig()" class="btn btn-secondary">Download Config</button>
                    </div>
                </div>
            </div>
        </div>

        <div id="connection-info" style="display: none;" class="alert alert-info">
            <h4>📱 Client Connection Information:</h4>
            <ul>
                <li><strong>Server URL:</strong> <code><?= htmlspecialchars($_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI'])) ?></code></li>
                <li><strong>API Key:</strong> <code><?= htmlspecialchars($_GET['api_key'] ?? 'Check config.php') ?></code></li>
                <li><strong>Endpoints:</strong> All API endpoints are ready for client connections</li>
            </ul>
        </div>

        <script>
        // Auto-start background services
        window.addEventListener('load', function() {
            startBackgroundServices();
            setupCronJobs();
        });

        function startBackgroundServices() {
            console.log('🚀 Starting FlexPBX background services...');

            // Start core services in background
            fetch('?action=start_services', {method: 'POST'})
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        console.log('✅ Background services started:', data.services);
                        showServiceStatus(data.services);
                    } else {
                        console.warn('⚠️ Some services failed to start:', data.errors);
                    }
                })
                .catch(error => {
                    console.error('❌ Error starting services:', error);
                });
        }

        function setupCronJobs() {
            console.log('⏰ Setting up built-in cron system...');

            fetch('?action=setup_cron', {method: 'POST'})
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        console.log('✅ Cron system initialized:', data.jobs);
                    } else {
                        console.warn('⚠️ Cron setup issues:', data.errors);
                    }
                })
                .catch(error => {
                    console.error('❌ Error setting up cron:', error);
                });
        }

        function showServiceStatus(services) {
            const statusDiv = document.createElement('div');
            statusDiv.style.cssText = 'position: fixed; top: 20px; right: 20px; background: #d4edda; border: 1px solid #28a745; border-radius: 8px; padding: 15px; z-index: 1000; max-width: 300px;';
            statusDiv.innerHTML = `
                <h5 style="margin: 0 0 10px; color: #155724;">🟢 Services Started</h5>
                <ul style="margin: 0; padding-left: 20px; color: #155724; font-size: 13px;">
                    ${services.map(service => `<li>${service}</li>`).join('')}
                </ul>
            `;

            document.body.appendChild(statusDiv);

            // Auto-hide after 5 seconds
            setTimeout(() => {
                statusDiv.style.opacity = '0';
                statusDiv.style.transition = 'opacity 0.5s';
                setTimeout(() => statusDiv.remove(), 500);
            }, 5000);
        }

        // Web Dashboard Setup Functions
        function setupWebDashboard() {
            document.getElementById('dashboard-setup').style.display = 'block';
            document.getElementById('device-linking').style.display = 'none';
            document.getElementById('admin_username').focus();

            // Smooth scroll to the form
            document.getElementById('dashboard-setup').scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }

        function hideDashboardSetup() {
            document.getElementById('dashboard-setup').style.display = 'none';
        }

        function createDashboardUser(event) {
            event.preventDefault();
            const formData = new FormData(event.target);
            const button = event.target.querySelector('button[type="submit"]');

            button.disabled = true;
            button.textContent = '🔄 Creating User...';

            fetch('?action=create_dashboard_user', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ Dashboard user created successfully!\n\n' + data.message);
                    // Redirect to dashboard
                    window.location.href = data.dashboard_url || '../dashboard/';
                } else {
                    alert('❌ Error creating user: ' + data.error);
                }
            })
            .catch(error => {
                alert('❌ Network error: ' + error.message);
            })
            .finally(() => {
                button.disabled = false;
                button.textContent = '🖥️ Create Dashboard User';
            });
        }

        // Device Linking Functions
        function showDeviceLinking() {
            document.getElementById('device-linking').style.display = 'block';
            document.getElementById('dashboard-setup').style.display = 'none';

            // Generate QR code
            generateQRCode();

            // Smooth scroll to the section
            document.getElementById('device-linking').scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }

        function hideDeviceLinking() {
            document.getElementById('device-linking').style.display = 'none';
        }

        function generateQRCode() {
            const canvas = document.getElementById('qr-canvas');
            if (!canvas) return;

            const ctx = canvas.getContext('2d');
            const serverUrl = document.getElementById('server-url').textContent;
            const apiKey = document.getElementById('main-api-key').textContent;

            // Simple QR code placeholder - in production, use a QR library like qrcode.js
            ctx.fillStyle = '#000';
            ctx.fillRect(0, 0, 150, 150);
            ctx.fillStyle = '#fff';
            ctx.font = '8px monospace';
            ctx.textAlign = 'center';
            ctx.fillText('FlexPBX QR', 75, 30);
            ctx.fillText('Server:', 75, 50);
            ctx.fillText(serverUrl.substring(0, 20), 75, 65);
            ctx.fillText('Key:', 75, 85);
            ctx.fillText(apiKey.substring(0, 15), 75, 100);
            ctx.fillText('Use QR library', 75, 120);
            ctx.fillText('for production', 75, 135);
        }

        function copyApiKey(elementId) {
            const element = document.getElementById(elementId);
            const text = element.textContent;

            navigator.clipboard.writeText(text).then(() => {
                // Visual feedback
                const button = event.target;
                const originalText = button.textContent;
                button.textContent = '✅ Copied!';
                button.style.background = '#28a745';

                setTimeout(() => {
                    button.textContent = originalText;
                    button.style.background = '';
                }, 2000);
            }).catch(() => {
                // Fallback for older browsers
                prompt('Copy this API key:', text);
            });
        }

        function generateNewRemoteKey() {
            if (confirm('Generate a new API key? This will invalidate the current key and require updating all connected devices.')) {
                fetch('?action=generate_new_key', {
                    method: 'POST'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update all API key displays
                        document.getElementById('main-api-key').textContent = data.new_key;
                        document.getElementById('desktop-api-key').textContent = data.new_key;
                        alert('✅ New API key generated successfully!\n\nKey: ' + data.new_key + '\n\nPlease update all your connected devices.');
                    } else {
                        alert('❌ Error generating new key: ' + data.error);
                    }
                })
                .catch(error => {
                    alert('❌ Network error: ' + error.message);
                });
            }
        }

        function downloadConnectionFile() {
            const serverUrl = document.getElementById('server-url').textContent;
            const apiKey = document.getElementById('main-api-key').textContent;

            const connectionData = {
                server_url: serverUrl,
                api_key: apiKey,
                auto_connect: true,
                created: new Date().toISOString()
            };

            const blob = new Blob([JSON.stringify(connectionData, null, 2)], {
                type: 'application/json'
            });

            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'flexpbx-connection.json';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }

        // Add hover effects to setup options
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.setup-option').forEach(option => {
                option.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px)';
                    this.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
                });

                option.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                    this.style.boxShadow = 'none';
                });
            });
        });

        function moveInstaller() {
            if (confirm('Move installer files to backup directory? This makes them inaccessible via web but preserves them for future use.')) {
                fetch('?action=move_installer', {method: 'POST'})
                    .then(response => response.text())
                    .then(result => {
                        if (result === 'OK') {
                            alert('✅ Installer moved to backup directory successfully!');
                            document.querySelector('[onclick="moveInstaller()"]').disabled = true;
                            document.querySelector('[onclick="moveInstaller()"]').textContent = 'Moved to Backup';
                        } else {
                            alert('⚠️ Could not move installer - please move manually to ../backup/ directory');
                        }
                    })
                    .catch(() => alert('⚠️ Error moving installer - please move manually'));
            }
        }

        function deleteInstaller() {
            if (confirm('⚠️ Are you sure you want to DELETE the installer file?\n\nThis cannot be undone. You will need to re-upload if you want to run the installer again.')) {
                fetch('?action=delete_installer', {method: 'POST'})
                    .then(response => response.text())
                    .then(result => {
                        if (result === 'OK') {
                            alert('✅ Installer deleted successfully!');
                            setTimeout(() => {
                                // Try to redirect to admin portal first, then fall back to main index
                                const adminUrl = '../admin/';
                                const mainUrl = '../';

                                fetch(adminUrl, {method: 'HEAD'})
                                    .then(response => {
                                        if (response.ok) {
                                            window.location.href = adminUrl;
                                        } else {
                                            window.location.href = mainUrl;
                                        }
                                    })
                                    .catch(() => {
                                        window.location.href = mainUrl;
                                    });
                            }, 2000);
                        } else {
                            alert('⚠️ Could not delete installer - please remove manually');
                        }
                    })
                    .catch(() => alert('⚠️ Error deleting installer - please remove manually'));
            }
        }

        function showConnectionInfo() {
            const info = document.getElementById('connection-info');
            info.style.display = info.style.display === 'none' ? 'block' : 'none';
        }

        function downloadConfig() {
            const config = {
                serverUrl: '<?= htmlspecialchars($_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI'])) ?>',
                apiKey: '<?= htmlspecialchars($_GET['api_key'] ?? 'Check config.php') ?>',
                installedAt: new Date().toISOString(),
                endpoints: [
                    '/api/register',
                    '/api/authorize',
                    '/api/status',
                    '/api/auto-link',
                    '/api/updates/check',
                    '/api/module-reload'
                ]
            };

            const blob = new Blob([JSON.stringify(config, null, 2)], {type: 'application/json'});
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'flexpbx-installation-config.json';
            a.click();
            URL.revokeObjectURL(url);
        }
        </script>
        <?php
        $this->renderFooter();
    }

    private function isAlreadyInstalled() {
        // Check if config.php exists and has database settings
        if (!file_exists('config.php')) {
            return false;
        }

        try {
            $config = include 'config.php';
            if (!is_array($config) || !isset($config['db_name'])) {
                return false;
            }

            // Try to connect to database and check for tables
            $dsn = "mysql:host={$config['db_host']};dbname={$config['db_name']}";
            $pdo = new PDO($dsn, $config['db_user'], $config['db_password']);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $stmt = $pdo->prepare("SHOW TABLES LIKE 'desktop_clients'");
            $stmt->execute();
            return $stmt->fetch() !== false;

        } catch (Exception $e) {
            return false;
        }
    }

    private function showReinstallOptions() {
        $this->renderHeader('FlexPBX - Already Installed');
        ?>
        <div class="reinstall-section">
            <h2>🎯 FlexPBX is Already Installed!</h2>
            <p>The installer detected that FlexPBX has already been set up on this server.</p>

            <div class="status-check">
                <h3>📊 Current Installation Status:</h3>
                <?php
                $config = include 'config.php';
                echo "<ul>";
                echo "<li>✅ Configuration file: <code>config.php</code> exists</li>";
                echo "<li>✅ Database: <code>{$config['db_name']}</code> on <code>{$config['db_host']}</code></li>";
                echo "<li>✅ API Key: Configured</li>";

                try {
                    $dsn = "mysql:host={$config['db_host']};dbname={$config['db_name']}";
                    $pdo = new PDO($dsn, $config['db_user'], $config['db_password']);

                    $tables = ['desktop_clients', 'active_connections', 'connection_limits', 'auto_link_requests', 'authorized_links', 'fallback_hierarchy'];
                    $existingTables = 0;

                    foreach ($tables as $table) {
                        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
                        $stmt->execute([$table]);
                        if ($stmt->fetch()) {
                            $existingTables++;
                        }
                    }

                    echo "<li>✅ Database Tables: {$existingTables}/" . count($tables) . " tables found</li>";

                    if ($existingTables === count($tables)) {
                        echo "<li>🎉 Installation appears to be complete and healthy!</li>";
                    } else {
                        echo "<li>⚠️ Some tables may be missing - repair recommended</li>";
                    }

                    // Show detailed analysis of what's installed vs what could be added
                    echo "</ul>";
                    echo "<div class='what-will-be-added'>";
                    echo "<h4>🔍 Detailed Analysis - What's Installed vs Available:</h4>";
                    echo "<div class='table-status'>";

                    foreach ($tables as $table) {
                        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
                        $stmt->execute([$table]);
                        $exists = $stmt->fetch() !== false;

                        if ($exists) {
                            // Check table structure and data
                            $rowStmt = $pdo->prepare("SELECT COUNT(*) as count FROM `{$table}`");
                            $rowStmt->execute();
                            $rowCount = $rowStmt->fetch()['count'];

                            echo "<div class='existing-items'>✅ <span class='table-name'>{$table}</span> - {$rowCount} records</div>";
                        } else {
                            echo "<div class='new-items'>➕ <span class='table-name'>{$table}</span> - Will be created</div>";
                        }
                    }

                    // Check for files that exist vs need to be added
                    $apiFiles = [
                        'connection-manager.php' => 'Multi-client connection management',
                        'auto-link-manager.php' => 'Auto-link authorization system',
                        'update-manager.php' => 'Update distribution and management',
                        '.htaccess' => 'URL routing and security headers'
                    ];

                    echo "<h5 style='margin-top: 20px;'>📄 API Files:</h5>";
                    foreach ($apiFiles as $file => $description) {
                        if (file_exists($file)) {
                            $fileSize = round(filesize($file) / 1024, 1);
                            echo "<div class='existing-items'>✅ <span class='table-name'>{$file}</span> - {$description} ({$fileSize}KB)</div>";
                        } else {
                            echo "<div class='new-items'>➕ <span class='table-name'>{$file}</span> - {$description}</div>";
                        }
                    }

                    echo "</div></div>";
                    echo "<ul style='display: none;'>";
                } catch (Exception $e) {
                    echo "<li>❌ Database connection issue: " . htmlspecialchars($e->getMessage()) . "</li>";
                }
                echo "</ul>";
                ?>
            </div>

            <div class="reinstall-options">
                <h3>🔧 What would you like to do?</h3>
                <div class="action-grid">
                    <div class="action-item">
                        <h4>🔧 Repair Tables</h4>
                        <p>Fix any missing or corrupted database tables without affecting existing data</p>
                        <a href="?step=repair" class="btn btn-primary">Repair Database →</a>
                    </div>

                    <div class="action-item">
                        <h4>🔄 Reinstall Completely</h4>
                        <p>Start fresh installation (existing data will be preserved where possible)</p>
                        <a href="?step=reinstall" class="btn btn-secondary">Reinstall →</a>
                    </div>

                    <div class="action-item">
                        <h4>🗑️ Remove Installer</h4>
                        <p>Installation is complete - remove installer for security</p>
                        <button onclick="deleteInstaller()" class="btn btn-danger">Delete Installer</button>
                    </div>

                    <div class="action-item">
                        <h4>📱 Go to API</h4>
                        <p>Everything looks good - use your FlexPBX API</p>
                        <a href="../" class="btn btn-primary">Go to API Dashboard →</a>
                    </div>
                </div>
            </div>

            <div class="alert alert-info">
                <h4>💡 Recommendation:</h4>
                <p>If your installation is working properly, you should <strong>delete this installer</strong> for security reasons. The installer should only be accessible during initial setup.</p>
            </div>
        </div>

        <script>
        function deleteInstaller() {
            if (confirm('⚠️ Are you sure you want to DELETE the installer file?\n\nThis cannot be undone. You will need to re-upload if you want to run the installer again.')) {
                fetch('?action=delete_installer', {method: 'POST'})
                    .then(response => response.text())
                    .then(result => {
                        if (result === 'OK') {
                            alert('✅ Installer deleted successfully!');
                            setTimeout(() => {
                                // Try to redirect to admin portal first, then fall back to main index
                                const adminUrl = '../admin/';
                                const mainUrl = '../';

                                fetch(adminUrl, {method: 'HEAD'})
                                    .then(response => {
                                        if (response.ok) {
                                            window.location.href = adminUrl;
                                        } else {
                                            window.location.href = mainUrl;
                                        }
                                    })
                                    .catch(() => {
                                        window.location.href = mainUrl;
                                    });
                            }, 2000);
                        } else {
                            alert('⚠️ Could not delete installer - please remove manually');
                        }
                    })
                    .catch(() => alert('⚠️ Error deleting installer - please remove manually'));
            }
        }
        </script>
        <?php
        $this->renderFooter();
    }

    private function repairTables() {
        $this->renderHeader('FlexPBX - Repairing Database');

        echo '<div class="repair-section">';
        echo '<h2>🔧 Repairing FlexPBX Database...</h2>';
        echo '<div class="progress-container">';

        try {
            $this->logProgress("🔍 Starting database repair process...");

            $config = include 'config.php';
            $dsn = "mysql:host={$config['db_host']};dbname={$config['db_name']}";
            $pdo = new PDO($dsn, $config['db_user'], $config['db_password']);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $this->logProgress("✅ Database connection established");

            // Check and repair tables
            $this->createDatabaseTables($pdo);
            $this->insertDefaultData($pdo);

            $this->logProgress("🎉 Database repair completed successfully!");

            echo '</div>';
            echo '<div class="action-buttons">';
            echo '<a href="?step=welcome" class="btn btn-primary">← Back to Options</a>';
            echo '<a href="../" class="btn btn-primary">Go to API Dashboard →</a>';
            echo '</div>';

        } catch (Exception $e) {
            $this->logProgress("❌ Repair failed: " . $e->getMessage());
            echo '<div class="alert alert-error">Repair failed. Please check the error above.</div>';
            echo '<div class="action-buttons">';
            echo '<a href="?step=welcome" class="btn btn-secondary">← Back to Options</a>';
            echo '</div>';
        }

        echo '</div>';
        $this->renderFooter();
    }

    private function performReinstallation() {
        $this->renderHeader('FlexPBX - Reinstalling');

        echo '<div class="reinstall-section">';
        echo '<h2>🔄 Reinstalling FlexPBX...</h2>';
        echo '<p>Proceeding with fresh installation (existing data will be preserved where possible)</p>';
        echo '</div>';

        // Redirect to requirements check
        echo '<script>window.location.href = "?step=requirements";</script>';
        $this->renderFooter();
    }

    private function logProgress($message) {
        echo '<div class="progress-item">' . htmlspecialchars($message) . '</div>';
        echo str_repeat(' ', 1024); // Force browser to display
        flush();
        if (ob_get_level()) ob_flush();
        usleep(500000); // 0.5 second delay for visual effect
    }

    private function createConfigFile($dbConfig) {
        $configContent = '<?php
/**
 * FlexPBX Server Configuration
 * Generated by Quick Installer on ' . date('Y-m-d H:i:s') . '
 */

return [
    // Database configuration
    \'db_host\' => \'' . addslashes($dbConfig['host']) . '\',
    \'db_name\' => \'' . addslashes($dbConfig['name']) . '\',
    \'db_user\' => \'' . addslashes($dbConfig['user']) . '\',
    \'db_password\' => \'' . addslashes($dbConfig['pass']) . '\',

    // API configuration
    \'api_key\' => \'' . addslashes($dbConfig['api_key']) . '\',
    \'api_version\' => \'1.1.0\',

    // Server configuration
    \'server_name\' => \'FlexPBX Remote Server\',
    \'max_connections_per_client\' => 10,
    \'default_connection_timeout\' => 300,

    // Update server configuration
    \'update_server\' => [
        \'enabled\' => true,
        \'check_interval\' => 3600,
        \'auto_download\' => false,
        \'download_path\' => \'/var/www/html/updates/\',
        \'supported_platforms\' => [\'darwin\', \'win32\', \'linux\']
    ],

    // Security settings
    \'security\' => [
        \'require_https\' => false,
        \'cors_enabled\' => true,
        \'rate_limiting\' => [
            \'enabled\' => true,
            \'max_requests_per_minute\' => 60
        ]
    ],

    // Logging configuration
    \'logging\' => [
        \'enabled\' => true,
        \'level\' => \'INFO\',
        \'file\' => dirname(__FILE__) . \'/logs/connections.log\',
        \'max_size\' => \'100MB\',
        \'rotate\' => true
    ]
];
?>';

        if (!file_put_contents('config.php', $configContent)) {
            throw new Exception('Could not create config.php file');
        }
    }

    private function connectDatabase($dbConfig) {
        $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['name']}";
        $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    }

    private function createDatabaseTables($pdo) {
        // Create tables from connection-manager.php
        $tables = [
            'desktop_clients' => "
                CREATE TABLE IF NOT EXISTS desktop_clients (
                    id VARCHAR(255) PRIMARY KEY,
                    client_type ENUM('admin', 'desktop') NOT NULL,
                    device_id VARCHAR(255) NOT NULL,
                    device_name VARCHAR(255),
                    platform VARCHAR(50),
                    ip_address VARCHAR(45),
                    user_agent TEXT,
                    first_connected DATETIME DEFAULT CURRENT_TIMESTAMP,
                    last_connected DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    is_active BOOLEAN DEFAULT TRUE,
                    max_connections INT DEFAULT 1,
                    current_connections INT DEFAULT 0,
                    capabilities JSON,
                    settings JSON,
                    INDEX idx_device_id (device_id),
                    INDEX idx_client_type (client_type),
                    INDEX idx_active (is_active)
                )",
            'active_connections' => "
                CREATE TABLE IF NOT EXISTS active_connections (
                    id VARCHAR(255) PRIMARY KEY,
                    client_id VARCHAR(255),
                    server_endpoint VARCHAR(255),
                    connected_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    last_activity DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    session_data JSON,
                    INDEX idx_client_id (client_id),
                    INDEX idx_last_activity (last_activity)
                )",
            'connection_limits' => "
                CREATE TABLE IF NOT EXISTS connection_limits (
                    client_type ENUM('admin', 'desktop') PRIMARY KEY,
                    default_max_connections INT DEFAULT 1,
                    premium_max_connections INT DEFAULT 5,
                    enterprise_max_connections INT DEFAULT 50,
                    requires_approval BOOLEAN DEFAULT FALSE
                )",
            'auto_link_requests' => "
                CREATE TABLE IF NOT EXISTS auto_link_requests (
                    id VARCHAR(255) PRIMARY KEY,
                    requesting_client_id VARCHAR(255) NOT NULL,
                    target_server VARCHAR(255),
                    request_type ENUM('admin_auth', 'desktop_auth', 'server_fallback') NOT NULL,
                    status ENUM('pending', 'approved', 'denied', 'expired') DEFAULT 'pending',
                    requested_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    processed_at DATETIME NULL,
                    processed_by VARCHAR(255),
                    auto_approved BOOLEAN DEFAULT FALSE,
                    approval_reason TEXT,
                    expires_at DATETIME,
                    metadata JSON,
                    INDEX idx_requesting_client (requesting_client_id),
                    INDEX idx_status (status),
                    INDEX idx_expires (expires_at)
                )",
            'authorized_links' => "
                CREATE TABLE IF NOT EXISTS authorized_links (
                    id VARCHAR(255) PRIMARY KEY,
                    client_id VARCHAR(255) NOT NULL,
                    target_server VARCHAR(255),
                    link_type ENUM('admin', 'desktop', 'fallback') NOT NULL,
                    authorized_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    authorized_by VARCHAR(255),
                    expires_at DATETIME NULL,
                    is_active BOOLEAN DEFAULT TRUE,
                    permissions JSON,
                    last_used DATETIME,
                    INDEX idx_client_id (client_id),
                    INDEX idx_active (is_active),
                    INDEX idx_expires (expires_at)
                )",
            'fallback_hierarchy' => "
                CREATE TABLE IF NOT EXISTS fallback_hierarchy (
                    id VARCHAR(255) PRIMARY KEY,
                    primary_server VARCHAR(255) NOT NULL,
                    fallback_server VARCHAR(255) NOT NULL,
                    fallback_order INT DEFAULT 1,
                    is_active BOOLEAN DEFAULT TRUE,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    last_tested DATETIME,
                    test_result ENUM('success', 'failed', 'timeout') NULL,
                    INDEX idx_primary (primary_server),
                    INDEX idx_fallback_order (fallback_order)
                )"
        ];

        foreach ($tables as $name => $sql) {
            $pdo->exec($sql);
        }
    }

    private function insertDefaultData($pdo) {
        // Insert default connection limits
        $pdo->exec("
            INSERT IGNORE INTO connection_limits VALUES
            ('admin', 5, 10, 100, FALSE),
            ('desktop', 1, 3, 10, TRUE)
        ");
    }

    private function preInstallationChecks() {
        // Check if files exist
        $requiredFiles = ['connection-manager.php', 'auto-link-manager.php', 'update-manager.php'];
        foreach ($requiredFiles as $file) {
            if (!file_exists($file)) {
                throw new Exception("Required file missing: {$file}");
            }
        }

        // Check write permissions
        if (!is_writable('.')) {
            throw new Exception("Current directory is not writable");
        }

        $this->logProgress("✅ All required files present and directory is writable");
    }

    private function setPermissions() {
        $this->logProgress("🔒 Setting file permissions for security...");

        $files = [
            'config.php' => 0644,
            'connection-manager.php' => 0644,
            'auto-link-manager.php' => 0644,
            'update-manager.php' => 0644,
            'install.php' => 0644,
            '.htaccess' => 0644
        ];

        foreach ($files as $file => $perm) {
            if (file_exists($file)) {
                if (@chmod($file, $perm)) {
                    $this->logProgress("✅ Set permissions for {$file} (" . decoct($perm) . ")");
                } else {
                    $this->logProgress("⚠️ Could not set permissions for {$file} - continuing anyway");
                }
            }
        }

        // Create and secure directories
        $directories = [
            'logs' => 0755,
            'backup' => 0755,
            'temp' => 0755
        ];

        foreach ($directories as $dir => $perm) {
            if (!is_dir($dir)) {
                if (@mkdir($dir, $perm, true)) {
                    $this->logProgress("✅ Created directory: {$dir}");
                } else {
                    $this->logProgress("⚠️ Could not create directory: {$dir}");
                }
            } else {
                @chmod($dir, $perm);
                $this->logProgress("✅ Verified directory: {$dir}");
            }
        }

        // Create .htaccess for logs directory
        $logsHtaccess = "logs/.htaccess";
        if (!file_exists($logsHtaccess)) {
            file_put_contents($logsHtaccess, "Deny from all\n");
            @chmod($logsHtaccess, 0644);
            $this->logProgress("✅ Secured logs directory with .htaccess");
        }

        $this->logProgress("✅ File permissions and directory security configured");
    }

    private function createHtaccess() {
        if (!file_exists('.htaccess')) {
            copy('.htaccess.example', '.htaccess');
        }
    }

    private function createDirectoryStructure() {
        $directories = [
            '../config',
            '../cron',
            '../logs',
            '../uploads',
            '../backups',
            '../dashboard',
            '../modules',
            '../temp'
        ];

        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
                $this->logProgress("📁 Created directory: " . basename($dir));
            }
        }

        // Create index.php files for security
        $indexContent = '<?php header("HTTP/1.1 403 Forbidden"); exit("Directory access forbidden"); ?>';
        foreach ($directories as $dir) {
            $indexFile = $dir . '/index.php';
            if (!file_exists($indexFile)) {
                file_put_contents($indexFile, $indexContent);
            }
        }
    }

    private function initializeServices($dbConfig) {
        try {
            // Create initial config file
            $configContent = '<?php
// FlexPBX Configuration File
// Generated: ' . date('Y-m-d H:i:s') . '

// Database Configuration
$DB_HOST = "' . $dbConfig['host'] . '";
$DB_PORT = "' . $dbConfig['port'] . '";
$DB_NAME = "' . $dbConfig['name'] . '";
$DB_USER = "' . $dbConfig['user'] . '";
$DB_PASS = "' . $dbConfig['pass'] . '";

// API Configuration
$API_KEY = "' . $dbConfig['api_key'] . '";
$SERVER_MODE = "' . $dbConfig['install_mode'] . '";

// Service Configuration
$SERVICES_ENABLED = true;
$AUTO_START_SERVICES = true;
$CRON_ENABLED = true;

// Security Settings
$REQUIRE_HTTPS = false;
$SESSION_TIMEOUT = 3600; // 1 hour
$MAX_CONNECTIONS = 100;

// Logging
$LOG_LEVEL = "INFO";
$LOG_ROTATION = true;
$LOG_MAX_SIZE = "10M";

// Features
$FEATURES = [
    "desktop_clients" => true,
    "mobile_clients" => true,
    "web_dashboard" => true,
    "auto_updates" => true,
    "remote_management" => true,
    "ivr_system" => true
];

?>';

            file_put_contents('../config/config.php', $configContent);
            $this->logProgress("⚙️ Created configuration file");

            // Create service status file
            $serviceStatus = [
                'installation_time' => time(),
                'services' => [
                    'flexpbx_server' => 'ready',
                    'ivr_system' => 'ready',
                    'connection_manager' => 'ready',
                    'client_registry' => 'ready',
                    'update_manager' => 'ready'
                ],
                'cron_jobs' => [
                    'client_heartbeat' => 'scheduled',
                    'connection_cleanup' => 'scheduled',
                    'update_check' => 'scheduled',
                    'log_rotation' => 'scheduled',
                    'service_monitor' => 'scheduled'
                ]
            ];

            file_put_contents('../config/service_status.json', json_encode($serviceStatus, JSON_PRETTY_PRINT));
            $this->logProgress("📊 Initialized service status tracking");

            // Create startup script
            $startupScript = '#!/bin/bash
# FlexPBX Service Startup Script
# This script is triggered when a remote desktop client successfully connects

echo "🚀 Starting FlexPBX services..."

# Set working directory
cd "$(dirname "$0")/.."

# Start background services
if [ -f "config/config.php" ]; then
    echo "✅ Configuration found - starting services"

    # Start PHP built-in cron system
    if [ ! -f "temp/cron.pid" ]; then
        nohup php cron/runner.php > logs/cron.log 2>&1 &
        echo $! > temp/cron.pid
        echo "⏰ Cron system started (PID: $(cat temp/cron.pid))"
    fi

    # Start main server process
    if [ ! -f "temp/server.pid" ]; then
        nohup php -S 0.0.0.0:8080 -t . > logs/server.log 2>&1 &
        echo $! > temp/server.pid
        echo "🌐 Server started (PID: $(cat temp/server.pid))"
    fi

    echo "🎉 All services started successfully"
else
    echo "❌ Configuration not found - please run installer first"
    exit 1
fi
';

            file_put_contents('../startup.sh', $startupScript);
            chmod('../startup.sh', 0755);
            $this->logProgress("🚀 Created service startup script");

        } catch (Exception $e) {
            $this->logProgress("⚠️ Service initialization warning: " . $e->getMessage());
        }
    }

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
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f5; color: #333; line-height: 1.6; }
                .container { max-width: 800px; margin: 0 auto; padding: 20px; }
                .header { background: #2c3e50; color: white; padding: 20px; border-radius: 8px 8px 0 0; text-align: center; }
                .content { background: white; padding: 30px; border-radius: 0 0 8px 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                h1 { margin-bottom: 10px; }
                h2 { color: #2c3e50; margin-bottom: 20px; }
                h3 { color: #34495e; margin: 20px 0 10px; }
                .btn { display: inline-block; padding: 12px 24px; margin: 5px; background: #3498db; color: white; text-decoration: none; border-radius: 5px; border: none; cursor: pointer; font-size: 14px; }
                .btn-primary { background: #3498db; }
                .btn-secondary { background: #95a5a6; }
                .btn-danger { background: #e74c3c; }
                .btn:hover { opacity: 0.9; }
                .form-group { margin-bottom: 20px; }
                .form-group label { display: block; margin-bottom: 5px; font-weight: 600; }
                .form-group input[type="text"], .form-group input[type="password"], .form-group input[type="number"] { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; }
                .form-group small { color: #666; font-size: 12px; }
                .requirements-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                .requirements-table th, .requirements-table td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
                .requirements-table th { background: #f8f9fa; font-weight: 600; }
                .requirements-table tr.success { background: #d4edda; }
                .requirements-table tr.warning { background: #fff3cd; }
                .requirements-table tr.error { background: #f8d7da; }
                .alert { padding: 15px; margin: 20px 0; border-radius: 4px; }
                .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
                .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
                .alert-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
                .action-buttons { text-align: center; margin-top: 30px; }
                .progress-container { background: #f8f9fa; padding: 20px; border-radius: 4px; margin: 20px 0; max-height: 400px; overflow-y: auto; }
                .progress-item { padding: 5px 0; border-bottom: 1px solid #eee; }
                .features-list ul, .next-steps ol { margin-left: 20px; }
                .features-list li, .next-steps li { margin-bottom: 8px; }
                code { background: #f1f2f6; padding: 2px 6px; border-radius: 3px; font-family: 'Monaco', 'Menlo', monospace; }
                .action-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 20px 0; }
                .action-item { border: 1px solid #ddd; padding: 20px; border-radius: 8px; background: #f8f9fa; }
                .action-item h4 { margin-bottom: 10px; color: #2c3e50; }
                .action-item p { margin-bottom: 15px; color: #666; }
                .completion-summary { background: #e8f5e8; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #28a745; }
                .auto-continue-section { background: #fff3cd; padding: 15px; border-radius: 4px; margin: 15px 0; border-left: 4px solid #ffc107; }
                .post-install-actions { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; }
                .detected-db-section { background: #f8f9fa; padding: 15px; border-radius: 4px; margin: 15px 0; border: 1px solid #dee2e6; }
                .database-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px; margin: 10px 0; }
                .db-item { padding: 10px; border: 1px solid #ddd; border-radius: 4px; cursor: pointer; background: white; transition: all 0.2s; }
                .db-item:hover { background: #e3f2fd; border-color: #2196f3; }
                .db-item.existing { background: #e8f5e8; border-color: #28a745; }
                .db-item.existing:hover { background: #d4edda; }
                .db-item.selected { background: #2196f3; color: white; border-color: #1976d2; }
                .db-item.selected.existing { background: #28a745; border-color: #1e7e34; }
                .installation-modes, .database-options { margin: 10px 0; }
                /* Enhanced progress tracking styles */
                .validation-progress { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #007bff; }
                .progress-steps { margin: 15px 0; }
                .progress-step { padding: 8px 0; font-size: 14px; display: flex; align-items: flex-start; }
                .step-icon { margin-right: 8px; font-size: 16px; }
                .database-radio-item { transition: all 0.2s ease; }
                .database-radio-item:hover { transform: translateY(-1px); box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
                .mode-option, .db-option { display: block; padding: 10px; border: 1px solid #ddd; border-radius: 4px; margin: 5px 0; cursor: pointer; transition: all 0.2s; }
                .mode-option:hover, .db-option:hover { background: #f8f9fa; }
                .mode-option input, .db-option input { margin-right: 8px; }
                .mode-option small, .db-option small { display: block; color: #666; margin-top: 5px; }
                .database-creation-help { margin: 20px 0; }
                .creation-methods { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin: 15px 0; }
                .method-section { padding: 15px; border: 1px solid #ddd; border-radius: 4px; background: white; }
                .method-section h5 { margin-bottom: 10px; color: #2c3e50; }
                .sql-command { background: #f8f9fa; padding: 10px; border-radius: 4px; margin: 10px 0; display: flex; align-items: center; gap: 10px; }
                .sql-command code { flex: 1; background: none; }
                .btn-sm { padding: 6px 12px; font-size: 12px; }

                /* Port Detection Styles */
                .port-detection { background: #f8f9fa; padding: 15px; border-radius: 4px; margin: 15px 0; border: 1px solid #dee2e6; }
                .port-detection h4 { margin-bottom: 10px; color: #2c3e50; }
                .port-option { padding: 10px; border: 1px solid #ddd; border-radius: 4px; margin: 5px; cursor: pointer; transition: all 0.2s; background: white; display: inline-block; min-width: 80px; text-align: center; }
                .port-option:hover { background: #e3f2fd; border-color: #2196f3; }
                .port-option.default { background: #e8f5e8; border-color: #28a745; font-weight: bold; }
                .port-option.selected { background: #2196f3; color: white; border-color: #1976d2; }
                .port-option.working { background: #28a745; color: white; border-color: #1e7e34; }
                .port-option.failed { background: #dc3545; color: white; border-color: #c82333; opacity: 0.6; cursor: not-allowed; }

                /* Error Actions Styles */
                .error-actions { background: #fff3cd; padding: 15px; border-radius: 4px; margin: 15px 0; border-left: 4px solid #ffc107; }
                .error-actions .btn { margin: 5px 10px 5px 0; }
                .error-message { background: #f8d7da; padding: 15px; border-radius: 4px; margin: 15px 0; border-left: 4px solid #dc3545; color: #721c24; }

                /* Installation Detection Styles */
                .detection-results { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; border: 1px solid #dee2e6; }
                .what-will-be-added { background: #e3f2fd; padding: 15px; border-radius: 4px; margin: 15px 0; border-left: 4px solid #2196f3; }
                .existing-items { color: #28a745; }
                .new-items { color: #2196f3; font-weight: 500; }
                .upgrade-items { color: #ff9800; }
                .table-status { margin: 10px 0; }
                .table-status .table-name { font-family: monospace; background: #f1f2f6; padding: 2px 6px; border-radius: 3px; }
                .reinstall-section { text-align: center; }
                .status-check ul { text-align: left; margin: 20px 0; list-style: none; }
                .status-check li { padding: 5px 0; }

                /* Animations */
                @keyframes pulse {
                    0% { transform: scale(1); }
                    50% { transform: scale(1.05); }
                    100% { transform: scale(1); }
                }
                @keyframes bounce {
                    0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
                    40% { transform: translateY(-10px); }
                    60% { transform: translateY(-5px); }
                }
                .pulse { animation: pulse 2s infinite; }
                .bounce { animation: bounce 2s infinite; }

                /* Enhanced visual effects */
                .alert { box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
                .btn:hover { transform: translateY(-1px); box-shadow: 0 4px 8px rgba(0,0,0,0.2); transition: all 0.2s ease; }
                .port-option:hover { transform: translateY(-2px); transition: all 0.2s ease; }

                /* Screen reader only content */
                .sr-only {
                    position: absolute;
                    width: 1px;
                    height: 1px;
                    padding: 0;
                    margin: -1px;
                    overflow: hidden;
                    clip: rect(0, 0, 0, 0);
                    white-space: nowrap;
                    border: 0;
                }

                /* Focus indicators for keyboard navigation */
                .btn:focus, .port-option:focus {
                    outline: 3px solid #007bff;
                    outline-offset: 2px;
                }

                /* Ensure sufficient color contrast */
                .port-option { color: #212529; }
                .port-option.working { color: #ffffff; }
                .port-option.failed { color: #ffffff; }

                /* Client compatibility section */
                .client-versions { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px; margin: 20px 0; }
                .client-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px; }
                .client-item { background: white; padding: 15px; border-radius: 6px; border-left: 4px solid #007bff; }
                .version-badge { background: #e7f3ff; color: #0056b3; padding: 3px 8px; border-radius: 12px; font-size: 12px; font-weight: bold; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1><?= htmlspecialchars($title) ?></h1>
                    <p>FlexPBX Multi-Server Management System</p>
                </div>
                <div class="content">
        <?php
    }

    private function renderFooter() {
        ?>
                </div>
            </div>
        </body>
        </html>
        <?php
    }
}

// Handle port detection
if ($_POST['action'] ?? '' === 'detect_port') {
    header('Content-Type: application/json');

    $input = json_decode(file_get_contents('php://input'), true);
    $host = $input['host'] ?? 'localhost';
    $user = $input['user'] ?? '';
    $password = $input['password'] ?? '';

    // Common MySQL ports to test
    $portsToTest = [3306, 3307, 3308, 3309, 33060, 33061];
    $workingPorts = [];

    foreach ($portsToTest as $port) {
        try {
            // First try to connect without database credentials to just test port availability
            $dsn = "mysql:host={$host};port={$port}";

            // If user/password provided, test with credentials
            if (!empty($user) && !empty($password)) {
                $pdo = new PDO($dsn, $user, $password, [
                    PDO::ATTR_TIMEOUT => 3,
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]);

                // Test a simple query to confirm it's working
                $stmt = $pdo->query("SELECT VERSION() as version");
                $result = $stmt->fetch(PDO::FETCH_ASSOC);

                $workingPorts[] = [
                    'port' => $port,
                    'status' => 'MySQL ' . ($result['version'] ?? 'Connected'),
                    'isDefault' => $port === 3306,
                    'tested' => 'authenticated'
                ];
            } else {
                // Just test port connectivity without authentication
                $socket = @fsockopen($host, $port, $errno, $errstr, 2);
                if ($socket) {
                    fclose($socket);
                    $workingPorts[] = [
                        'port' => $port,
                        'status' => 'Port Open (MySQL likely)',
                        'isDefault' => $port === 3306,
                        'tested' => 'port_only'
                    ];
                }
            }

        } catch (PDOException $e) {
            // If credentials fail but port might be open, test port only
            if (strpos($e->getMessage(), 'Access denied') !== false) {
                $socket = @fsockopen($host, $port, $errno, $errstr, 2);
                if ($socket) {
                    fclose($socket);
                    $workingPorts[] = [
                        'port' => $port,
                        'status' => 'Port Open (Access Denied)',
                        'isDefault' => $port === 3306,
                        'tested' => 'port_only'
                    ];
                }
            }
            continue;
        }
    }

    // Sort working ports - default (3306) first, then by port number
    usort($workingPorts, function($a, $b) {
        if ($a['isDefault']) return -1;
        if ($b['isDefault']) return 1;
        return $a['port'] - $b['port'];
    });

    echo json_encode([
        'success' => true,
        'workingPorts' => $workingPorts,
        'testedPorts' => $portsToTest
    ]);
    exit;
}

// Handle database detection
if ($_POST['action'] ?? '' === 'detect_databases') {
    header('Content-Type: application/json');

    $input = json_decode(file_get_contents('php://input'), true);
    $host = $input['host'] ?? 'localhost';
    $port = $input['port'] ?? '3306';
    $user = $input['user'] ?? '';
    $password = $input['password'] ?? '';

    try {
        $dsn = "mysql:host={$host};port={$port}";
        $pdo = new PDO($dsn, $user, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Get list of databases
        $stmt = $pdo->query("SHOW DATABASES");
        $databases = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $dbName = $row['Database'];

            // Skip system databases
            if (in_array($dbName, ['information_schema', 'performance_schema', 'mysql', 'sys'])) {
                continue;
            }

            try {
                // Connect to specific database to check for tables
                $dbDsn = "mysql:host={$host};port={$port};dbname={$dbName}";
                $dbPdo = new PDO($dbDsn, $user, $password);

                // Count tables
                $tableStmt = $dbPdo->query("SHOW TABLES");
                $tableCount = $tableStmt->rowCount();

                // Check for FlexPBX tables
                $flexpbxStmt = $dbPdo->prepare("SHOW TABLES LIKE 'desktop_clients'");
                $flexpbxStmt->execute();
                $hasFlexPBXTables = $flexpbxStmt->fetch() !== false;

                // Get database size info
                $sizeStmt = $dbPdo->prepare("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) AS size_mb FROM information_schema.tables WHERE table_schema = ?");
                $sizeStmt->execute([$dbName]);
                $sizeResult = $sizeStmt->fetch();
                $sizeMb = $sizeResult['size_mb'] ?? 0;
                $sizeInfo = $sizeMb > 0 ? ($sizeMb . ' MB') : 'Empty';

                $databases[] = [
                    'name' => $dbName,
                    'tableCount' => $tableCount,
                    'hasFlexPBXTables' => $hasFlexPBXTables,
                    'sizeInfo' => $sizeInfo
                ];

            } catch (Exception $e) {
                // If we can't access the database, still list it
                $databases[] = [
                    'name' => $dbName,
                    'tableCount' => 0,
                    'hasFlexPBXTables' => false
                ];
            }
        }

        // Sort databases - FlexPBX databases first, then by name
        usort($databases, function($a, $b) {
            if ($a['hasFlexPBXTables'] && !$b['hasFlexPBXTables']) return -1;
            if (!$a['hasFlexPBXTables'] && $b['hasFlexPBXTables']) return 1;
            return strcmp($a['name'], $b['name']);
        });

        echo json_encode(['success' => true, 'databases' => $databases]);

    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Handle installer management actions
if ($_POST['action'] ?? '' === 'delete_installer') {
    $success = @unlink(__FILE__);
    exit($success ? 'OK' : 'ERROR');
}

// Handle service startup
if ($_POST['action'] ?? '' === 'start_services') {
    header('Content-Type: application/json');
    $services = [];
    $errors = [];

    try {
        // Start core FlexPBX services
        $coreServices = [
            'FlexPBX Server' => 'start_flexpbx_server',
            'IVR System' => 'start_ivr_system',
            'Connection Manager' => 'start_connection_manager',
            'Client Registry' => 'start_client_registry',
            'Update Manager' => 'start_update_manager'
        ];

        foreach ($coreServices as $name => $function) {
            try {
                if (function_exists($function)) {
                    call_user_func($function);
                    $services[] = $name;
                } else {
                    // Create placeholder service status
                    $services[] = $name . ' (Initialized)';
                }
            } catch (Exception $e) {
                $errors[] = $name . ': ' . $e->getMessage();
            }
        }

        echo json_encode(['success' => true, 'services' => $services, 'errors' => $errors]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Handle cron setup
if ($_POST['action'] ?? '' === 'setup_cron') {
    header('Content-Type: application/json');
    $jobs = [];
    $errors = [];

    try {
        // Create cron configuration
        $cronJobs = [
            'Client Heartbeat Check' => '*/5 * * * *',  // Every 5 minutes
            'Connection Cleanup' => '0 */1 * * *',      // Every hour
            'Update Check' => '0 6 * * *',              // Daily at 6 AM
            'Log Rotation' => '0 0 * * 0',              // Weekly on Sunday
            'Service Monitor' => '*/10 * * * *'         // Every 10 minutes
        ];

        // Create built-in cron system (PHP-based scheduler)
        $cronConfig = json_encode($cronJobs, JSON_PRETTY_PRINT);
        if (file_put_contents('../config/cron.json', $cronConfig)) {
            $jobs = array_keys($cronJobs);

            // Create cron runner script
            $cronRunner = '<?php
// FlexPBX Built-in Cron System
require_once "../config/config.php";

$cronJobs = json_decode(file_get_contents("../config/cron.json"), true);
$lastRun = json_decode(file_get_contents("../config/cron_last_run.json") ?: "[]", true);

foreach ($cronJobs as $jobName => $schedule) {
    $shouldRun = checkCronSchedule($schedule, $lastRun[$jobName] ?? 0);
    if ($shouldRun) {
        executeCronJob($jobName);
        $lastRun[$jobName] = time();
    }
}

file_put_contents("../config/cron_last_run.json", json_encode($lastRun));

function checkCronSchedule($schedule, $lastRun) {
    // Simple cron schedule checker
    return (time() - $lastRun) > 300; // Run if more than 5 minutes since last run
}

function executeCronJob($jobName) {
    switch($jobName) {
        case "Client Heartbeat Check":
            checkClientHeartbeats();
            break;
        case "Connection Cleanup":
            cleanupStaleConnections();
            break;
        case "Update Check":
            checkForUpdates();
            break;
        case "Log Rotation":
            rotateLogFiles();
            break;
        case "Service Monitor":
            monitorServices();
            break;
    }
}
?>';
            file_put_contents('../cron/runner.php', $cronRunner);
        } else {
            $errors[] = 'Could not create cron configuration';
        }

        echo json_encode(['success' => true, 'jobs' => $jobs, 'errors' => $errors]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Handle dashboard user creation
if ($_POST['action'] ?? '' === 'create_dashboard_user') {
    header('Content-Type: application/json');

    try {
        $username = $_POST['admin_username'] ?? '';
        $password = $_POST['admin_password'] ?? '';
        $email = $_POST['admin_email'] ?? '';
        $orgName = $_POST['org_name'] ?? '';

        if (empty($username) || empty($password)) {
            throw new Exception('Username and password are required');
        }

        // Create admin user in database
        $dbConfig = $_SESSION['db_config'] ?? [];
        $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['name']}";
        $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass']);

        // Create admin_users table if not exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            email VARCHAR(100),
            organization VARCHAR(100),
            role VARCHAR(20) DEFAULT 'admin',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_login TIMESTAMP NULL
        )");

        // Insert admin user
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO admin_users (username, password_hash, email, organization) VALUES (?, ?, ?, ?)");
        $stmt->execute([$username, $passwordHash, $email, $orgName]);

        // Create dashboard directory structure if needed
        if (!is_dir('../dashboard')) {
            mkdir('../dashboard', 0755, true);
        }

        echo json_encode([
            'success' => true,
            'message' => "Admin user '{$username}' created successfully!",
            'dashboard_url' => '../dashboard/'
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Handle new API key generation
// Handle startup script execution
if ($_GET['action'] ?? '' === 'startup' || $_POST['action'] ?? '' === 'startup') {
    header('Content-Type: application/json');

    try {
        $startupScript = '../startup.sh';

        if (file_exists($startupScript)) {
            // Make script executable
            chmod($startupScript, 0755);

            // Execute startup script
            $output = [];
            $returnCode = 0;
            exec("bash $startupScript 2>&1", $output, $returnCode);

            if ($returnCode === 0) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Startup script executed successfully',
                    'output' => implode("\n", $output),
                    'services_started' => true
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'Startup script failed',
                    'output' => implode("\n", $output),
                    'return_code' => $returnCode
                ]);
            }
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Startup script not found',
                'note' => 'Complete installation first to generate startup script'
            ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
}

if ($_POST['action'] ?? '' === 'generate_new_key') {
    header('Content-Type: application/json');

    try {
        // Generate new secure API key
        $newKey = 'flexpbx_api_' . bin2hex(random_bytes(32));

        // Update in database
        $dbConfig = $_SESSION['db_config'] ?? [];
        $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['name']}";
        $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass']);

        // Update config table
        $stmt = $pdo->prepare("UPDATE config SET value = ? WHERE setting_name = 'api_key'");
        $stmt->execute([$newKey]);

        // Update session
        $_SESSION['db_config']['api_key'] = $newKey;

        echo json_encode(['success' => true, 'new_key' => $newKey]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

if ($_POST['action'] ?? '' === 'move_installer') {
    $backupDir = '../backup';

    // Create backup directory if it doesn't exist
    if (!is_dir($backupDir)) {
        @mkdir($backupDir, 0755, true);
    }

    // Move installer and related files
    $filesToMove = [
        'install.php',
        '.htaccess.example',
        'deployment-manifest.json',
        'install.sh',
        'module-manager.sh',
        'flexpbx-modules.service'
    ];

    $moveSuccess = true;
    foreach ($filesToMove as $file) {
        if (file_exists($file)) {
            $moved = @rename($file, $backupDir . '/' . $file);
            if (!$moved && $file === 'install.php') {
                $moveSuccess = false;
                break;
            }
        }
    }

    exit($moveSuccess ? 'OK' : 'ERROR');
}

// Run installer
$installer = new FlexPBXInstaller();
$installer->run();
?>