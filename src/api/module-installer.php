<?php
/**
 * FlexPBX Module Installer API
 * Handles automatic module download and installation
 * NO FTP/SSH REQUIRED - All via web interface
 *
 * Version: 1.0
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/config.php';

// Verify API key or session
$api_key = $_GET['api_key'] ?? $_POST['api_key'] ?? '';
$is_api_auth = ($api_key === $config['api_key']);

session_start();
$is_session_auth = $_SESSION['admin_logged_in'] ?? false;

if (!$is_api_auth && !$is_session_auth) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

// Configuration
define('MASTER_SERVER', 'https://flexpbx.devinecreations.net');
define('TEMP_DIR', '/home/flexpbxuser/tmp/modules');
define('INSTALL_LOG', '/home/flexpbxuser/logs/module-installer.log');

// CopyParty Configuration
define('COPYPARTY_ENABLED', true);
define('COPYPARTY_SERVER', 'https://files.devinecreations.net');
define('COPYPARTY_BASE_PATH', '/flexpbx-modules');
define('COPYPARTY_USERNAME', 'flexpbx-public');
define('COPYPARTY_PASSWORD', 'flexpbx2025');

// Transport Protocol Priorities (will try in order)
define('TRANSPORT_PROTOCOLS', ['copyparty', 'https', 'ftp', 'sftp', 'scp']);

try {
    switch ($action) {
        case 'list':
            listModules();
            break;

        case 'install':
            installModule();
            break;

        case 'uninstall':
            uninstallModule();
            break;

        case 'updates':
            checkUpdates();
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    logMessage("ERROR: " . $e->getMessage());
}

/**
 * List available modules from master server
 */
function listModules() {
    global $pdo;

    // Hardcoded module list (in production, fetch from master server API)
    $availableModules = [
        [
            'key' => 'checklist-system',
            'name' => 'Universal Checklist System',
            'version' => '2.0.0',
            'category' => 'required',
            'size' => '9.0 KB',
            'download_url' => MASTER_SERVER . '/downloads/modules/required/checklist-system-2.0.0.tar.gz'
        ],
        [
            'key' => 'flexbot',
            'name' => 'FlexBot AI Assistant',
            'version' => '1.0.0',
            'category' => 'optional',
            'size' => '13 KB',
            'download_url' => MASTER_SERVER . '/downloads/modules/optional/flexbot-1.0.0.tar.gz'
        ],
        [
            'key' => 'mastodon-auth',
            'name' => 'Mastodon Authentication',
            'version' => '1.0.0',
            'category' => 'optional',
            'size' => '12 KB',
            'download_url' => MASTER_SERVER . '/downloads/modules/optional/mastodon-auth-1.0.0.tar.gz'
        ]
    ];

    // Check which are installed
    $installedModules = getInstalledModules($pdo);
    $installedKeys = array_column($installedModules, 'module_key');

    foreach ($availableModules as &$module) {
        $module['installed'] = in_array($module['key'], $installedKeys);

        if ($module['installed']) {
            // Check for updates
            foreach ($installedModules as $installed) {
                if ($installed['module_key'] === $module['key']) {
                    $module['current_version'] = $installed['module_version'];
                    $module['update_available'] = version_compare($module['version'], $installed['module_version'], '>');
                }
            }
        }
    }

    echo json_encode([
        'success' => true,
        'data' => $availableModules,
        'server' => MASTER_SERVER
    ]);
}

/**
 * Install a module automatically
 * Downloads, extracts, and installs without FTP/SSH
 */
function installModule() {
    global $pdo;

    $moduleKey = $_GET['module'] ?? $_POST['module'] ?? '';

    if (empty($moduleKey)) {
        throw new Exception('Module key required');
    }

    logMessage("Starting installation of module: {$moduleKey}");

    // Get module info
    $module = getModuleInfo($moduleKey);
    if (!$module) {
        throw new Exception('Module not found');
    }

    // Create temp directory
    if (!file_exists(TEMP_DIR)) {
        mkdir(TEMP_DIR, 0755, true);
    }

    $tarballPath = TEMP_DIR . '/' . $moduleKey . '-' . $module['version'] . '.tar.gz';
    $extractDir = TEMP_DIR . '/' . $moduleKey . '-' . $module['version'];

    // Step 1: Download module from master server
    logMessage("Downloading from: {$module['download_url']}");

    if (!downloadFile($module['download_url'], $tarballPath)) {
        throw new Exception('Failed to download module');
    }

    logMessage("Downloaded successfully: " . filesize($tarballPath) . " bytes");

    // Step 2: Extract tarball
    logMessage("Extracting tarball...");

    if (!extractTarball($tarballPath, TEMP_DIR)) {
        throw new Exception('Failed to extract module');
    }

    logMessage("Extracted successfully");

    // Step 3: Read module.json for installation instructions
    $moduleJsonPath = $extractDir . '/module.json';
    if (!file_exists($moduleJsonPath)) {
        throw new Exception('module.json not found in package');
    }

    $moduleConfig = json_decode(file_get_contents($moduleJsonPath), true);
    logMessage("Module config loaded: " . $moduleConfig['module_info']['name']);

    // Step 4: Install database schema
    $sqlPath = $extractDir . '/install.sql';
    if (file_exists($sqlPath)) {
        logMessage("Installing database schema...");
        installDatabaseSchema($pdo, $sqlPath);
        logMessage("Database schema installed");
    }

    // Step 5: Copy files to destination
    logMessage("Copying module files...");
    copyModuleFiles($extractDir, $moduleConfig);
    logMessage("Files copied successfully");

    // Step 6: Set file permissions
    logMessage("Setting file permissions...");
    setModulePermissions($moduleConfig);
    logMessage("Permissions set");

    // Step 7: Register module in database
    registerModule($pdo, $moduleKey, $module['version'], $moduleConfig);
    logMessage("Module registered in database");

    // Step 8: Cleanup
    cleanupInstallation($tarballPath, $extractDir);
    logMessage("Installation completed successfully");

    echo json_encode([
        'success' => true,
        'message' => 'Module installed successfully',
        'module' => $moduleKey,
        'version' => $module['version']
    ]);
}

/**
 * Download file using best available transport method
 */
function downloadFile($url, $destination) {
    $moduleFilename = basename($url);

    // Try each transport protocol in priority order
    foreach (TRANSPORT_PROTOCOLS as $protocol) {
        logMessage("Attempting download via {$protocol}...");

        $result = false;
        switch ($protocol) {
            case 'copyparty':
                $result = downloadViaCopyParty($moduleFilename, $destination);
                break;
            case 'https':
            case 'http':
                $result = downloadViaHTTP($url, $destination);
                break;
            case 'ftp':
                $result = downloadViaFTP($url, $destination);
                break;
            case 'sftp':
                $result = downloadViaSFTP($url, $destination);
                break;
            case 'scp':
                $result = downloadViaSCP($url, $destination);
                break;
        }

        if ($result) {
            logMessage("✓ Download successful via {$protocol}");
            return true;
        } else {
            logMessage("✗ Failed via {$protocol}, trying next method...");
        }
    }

    logMessage("ERROR: All download methods failed");
    return false;
}

/**
 * Download via CopyParty server (preferred method)
 */
function downloadViaCopyParty($filename, $destination) {
    if (!COPYPARTY_ENABLED) return false;

    $url = COPYPARTY_SERVER . COPYPARTY_BASE_PATH . '/' . $filename;

    $ch = curl_init($url);
    $fp = fopen($destination, 'w+');

    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERPWD, COPYPARTY_USERNAME . ':' . COPYPARTY_PASSWORD);

    $success = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);
    fclose($fp);

    return $success && $httpCode === 200;
}

/**
 * Download via HTTP/HTTPS
 */
function downloadViaHTTP($url, $destination) {
    $ch = curl_init($url);
    $fp = fopen($destination, 'w+');

    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $success = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);
    fclose($fp);

    return $success && $httpCode === 200;
}

/**
 * Download via FTP
 */
function downloadViaFTP($url, $destination) {
    if (!function_exists('ftp_connect')) {
        logMessage("FTP extension not available");
        return false;
    }

    // Parse FTP URL: ftp://user:pass@host/path/file
    $urlParts = parse_url($url);
    if (!isset($urlParts['host'])) return false;

    $conn = ftp_connect($urlParts['host'], $urlParts['port'] ?? 21, 30);
    if (!$conn) return false;

    $username = $urlParts['user'] ?? 'anonymous';
    $password = $urlParts['pass'] ?? 'anonymous@';

    if (!@ftp_login($conn, $username, $password)) {
        ftp_close($conn);
        return false;
    }

    ftp_pasv($conn, true);
    $result = @ftp_get($conn, $destination, $urlParts['path'], FTP_BINARY);
    ftp_close($conn);

    return $result;
}

/**
 * Download via SFTP (requires SSH2 extension)
 */
function downloadViaSFTP($url, $destination) {
    if (!function_exists('ssh2_connect')) {
        logMessage("SSH2 extension not available");
        return false;
    }

    $urlParts = parse_url($url);
    if (!isset($urlParts['host'])) return false;

    $conn = @ssh2_connect($urlParts['host'], $urlParts['port'] ?? 22);
    if (!$conn) return false;

    $username = $urlParts['user'] ?? 'root';
    $password = $urlParts['pass'] ?? '';

    if (!@ssh2_auth_password($conn, $username, $password)) {
        return false;
    }

    $sftp = @ssh2_sftp($conn);
    if (!$sftp) return false;

    $remoteFile = ssh2_sftp_stream($sftp, $urlParts['path'], 'r');
    if (!$remoteFile) return false;

    $localFile = fopen($destination, 'w');
    if (!$localFile) {
        fclose($remoteFile);
        return false;
    }

    $result = stream_copy_to_stream($remoteFile, $localFile);
    fclose($remoteFile);
    fclose($localFile);

    return $result !== false;
}

/**
 * Download via SCP (fallback using system command)
 */
function downloadViaSCP($url, $destination) {
    $urlParts = parse_url($url);
    if (!isset($urlParts['host'])) return false;

    $username = $urlParts['user'] ?? 'root';
    $host = $urlParts['host'];
    $port = $urlParts['port'] ?? 22;
    $remotePath = $urlParts['path'];

    // Build SCP command with password authentication disabled (requires SSH key)
    $command = sprintf(
        'scp -P %d -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null %s@%s:%s %s 2>&1',
        $port,
        escapeshellarg($username),
        escapeshellarg($host),
        escapeshellarg($remotePath),
        escapeshellarg($destination)
    );

    exec($command, $output, $returnVar);

    if ($returnVar !== 0) {
        logMessage("SCP command failed: " . implode("\n", $output));
        return false;
    }

    return file_exists($destination) && filesize($destination) > 0;
}

/**
 * Extract tarball using PHP
 */
function extractTarball($tarballPath, $destination) {
    try {
        $phar = new PharData($tarballPath);
        $phar->extractTo($destination, null, true);
        return true;
    } catch (Exception $e) {
        logMessage("PharData extraction failed: " . $e->getMessage());

        // Fallback to system tar command
        $command = "cd " . escapeshellarg($destination) . " && tar -xzf " . escapeshellarg($tarballPath) . " 2>&1";
        exec($command, $output, $returnVar);

        if ($returnVar !== 0) {
            logMessage("tar command failed: " . implode("\n", $output));
            return false;
        }

        return true;
    }
}

/**
 * Install database schema from SQL file
 */
function installDatabaseSchema($pdo, $sqlPath) {
    $sql = file_get_contents($sqlPath);

    // Execute SQL (handle multiple statements)
    try {
        $pdo->exec($sql);
    } catch (PDOException $e) {
        logMessage("Database error: " . $e->getMessage());
        throw new Exception('Failed to install database schema: ' . $e->getMessage());
    }
}

/**
 * Copy module files to their destinations
 */
function copyModuleFiles($extractDir, $moduleConfig) {
    $basePath = '/home/flexpbxuser';

    foreach ($moduleConfig['files'] as $file) {
        $source = $extractDir . '/' . $file['source'];
        $destination = $basePath . $file['destination'];

        if (!file_exists($source)) {
            logMessage("WARNING: Source file not found: {$source}");
            continue;
        }

        // Create destination directory if needed
        $destDir = dirname($destination);
        if (!file_exists($destDir)) {
            mkdir($destDir, 0755, true);
        }

        // Copy file
        if (copy($source, $destination)) {
            logMessage("Copied: {$file['source']} -> {$file['destination']}");
        } else {
            throw new Exception("Failed to copy: {$file['source']}");
        }
    }
}

/**
 * Set file permissions
 */
function setModulePermissions($moduleConfig) {
    $basePath = '/home/flexpbxuser';

    foreach ($moduleConfig['files'] as $file) {
        if (!isset($file['permissions'])) continue;

        $destination = $basePath . $file['destination'];
        if (file_exists($destination)) {
            chmod($destination, octdec($file['permissions']));
        }
    }
}

/**
 * Register module in database
 */
function registerModule($pdo, $moduleKey, $version, $config) {
    try {
        // Create table if doesn't exist
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS installed_modules (
                id INT PRIMARY KEY AUTO_INCREMENT,
                module_key VARCHAR(100) UNIQUE NOT NULL,
                module_name VARCHAR(255) NOT NULL,
                module_version VARCHAR(50) NOT NULL,
                category VARCHAR(50) NOT NULL,
                is_required TINYINT(1) DEFAULT 0,
                installed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");

        // Insert or update module record
        $stmt = $pdo->prepare("
            INSERT INTO installed_modules (module_key, module_name, module_version, category, is_required)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                module_version = VALUES(module_version),
                updated_at = NOW()
        ");

        $stmt->execute([
            $moduleKey,
            $config['module_info']['name'],
            $version,
            $config['module_info']['category'],
            $config['module_info']['required'] ? 1 : 0
        ]);
    } catch (PDOException $e) {
        logMessage("Failed to register module: " . $e->getMessage());
    }
}

/**
 * Cleanup installation files
 */
function cleanupInstallation($tarballPath, $extractDir) {
    if (file_exists($tarballPath)) {
        unlink($tarballPath);
    }

    if (file_exists($extractDir)) {
        deleteDirectory($extractDir);
    }
}

/**
 * Recursively delete directory
 */
function deleteDirectory($dir) {
    if (!file_exists($dir)) return;

    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        is_dir($path) ? deleteDirectory($path) : unlink($path);
    }
    rmdir($dir);
}

/**
 * Get module information
 */
function getModuleInfo($moduleKey) {
    $modules = [
        'checklist-system' => [
            'key' => 'checklist-system',
            'version' => '2.0.0',
            'download_url' => MASTER_SERVER . '/downloads/modules/required/checklist-system-2.0.0.tar.gz'
        ],
        'flexbot' => [
            'key' => 'flexbot',
            'version' => '1.0.0',
            'download_url' => MASTER_SERVER . '/downloads/modules/optional/flexbot-1.0.0.tar.gz'
        ],
        'mastodon-auth' => [
            'key' => 'mastodon-auth',
            'version' => '1.0.0',
            'download_url' => MASTER_SERVER . '/downloads/modules/optional/mastodon-auth-1.0.0.tar.gz'
        ]
    ];

    return $modules[$moduleKey] ?? null;
}

/**
 * Get installed modules from database
 */
function getInstalledModules($pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM installed_modules ORDER BY installed_at DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Check for module updates
 */
function checkUpdates() {
    global $pdo;

    $installed = getInstalledModules($pdo);
    $available = []; // Would fetch from master server

    $updates = [];
    // Compare versions and build update list

    echo json_encode([
        'success' => true,
        'updates' => $updates
    ]);
}

/**
 * Uninstall a module
 */
function uninstallModule() {
    global $pdo;

    $moduleKey = $_GET['module'] ?? $_POST['module'] ?? '';

    if (empty($moduleKey)) {
        throw new Exception('Module key required');
    }

    // Check if module is required
    $stmt = $pdo->prepare("SELECT is_required FROM installed_modules WHERE module_key = ?");
    $stmt->execute([$moduleKey]);
    $module = $stmt->fetch();

    if ($module && $module['is_required']) {
        throw new Exception('Cannot uninstall required module');
    }

    // Remove from database
    $stmt = $pdo->prepare("DELETE FROM installed_modules WHERE module_key = ?");
    $stmt->execute([$moduleKey]);

    logMessage("Module uninstalled: {$moduleKey}");

    echo json_encode([
        'success' => true,
        'message' => 'Module uninstalled successfully'
    ]);
}

/**
 * Log messages to file
 */
function logMessage($message) {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] {$message}\n";
    file_put_contents(INSTALL_LOG, $logEntry, FILE_APPEND);
}
