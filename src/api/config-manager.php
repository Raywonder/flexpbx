<?php
/**
 * FlexPBX Configuration Manager API
 * Secure config file management outside web root
 *
 * @requires PHP 8.0+
 * @recommended PHP 8.1 or 8.2
 */

// Check PHP version (minimum 8.0)
if (version_compare(PHP_VERSION, '8.0.0', '<')) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'PHP 8.0 or higher required',
        'current_version' => PHP_VERSION,
        'minimum_version' => '8.0.0',
        'recommended_versions' => ['8.1', '8.2']
    ]);
    exit;
}

session_start();
header('Content-Type: application/json');

// TODO: Add authentication check
// For now, allowing access - add proper auth before production

// Define secure config directory (outside web root)
define('SECURE_CONFIG_DIR', '/home/flexpbxuser/config/');
define('PUBLIC_CONFIG_DIR', '/home/flexpbxuser/public_html/config/');

// Allowed config files mapping
$ALLOWED_FILES = [
    // Secure configs (outside web root)
    'payment_config.json' => SECURE_CONFIG_DIR,
    'licensing_config.json' => SECURE_CONFIG_DIR,
    'extensions-config.json' => SECURE_CONFIG_DIR,
    'callcentric-trunk-config.json' => SECURE_CONFIG_DIR,
    'google-voice-config.json' => SECURE_CONFIG_DIR,
    'transactions.json' => SECURE_CONFIG_DIR,
    'licenses.json' => SECURE_CONFIG_DIR,
    'installations.json' => SECURE_CONFIG_DIR,

    // Public configs (can stay in public_html/config)
    'jellyfin-integration.json' => PUBLIC_CONFIG_DIR,
    'conference-music.json' => PUBLIC_CONFIG_DIR,
    'conference-control.json' => PUBLIC_CONFIG_DIR,
    'presence-status.json' => PUBLIC_CONFIG_DIR,
    'inbound-routes.json' => PUBLIC_CONFIG_DIR,
    'service_status.json' => PUBLIC_CONFIG_DIR
];

// Get action (PHP 8.0+ null coalescing operator)
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$file = $_GET['file'] ?? $_POST['file'] ?? '';

// Validate file
if (!isset($ALLOWED_FILES[$file])) {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid or unauthorized file',
        'allowed_files' => array_keys($ALLOWED_FILES)
    ]);
    exit;
}

$file_path = $ALLOWED_FILES[$file] . $file;

switch ($action) {
    case 'check':
        checkFile($file_path);
        break;

    case 'read':
        readFile($file_path);
        break;

    case 'write':
        writeFile($file_path);
        break;

    case 'list':
        listFiles();
        break;

    case 'backup':
        backupFile($file_path);
        break;

    default:
        echo json_encode([
            'success' => false,
            'error' => 'Invalid action',
            'available_actions' => ['check', 'read', 'write', 'list', 'backup']
        ]);
}

/**
 * Check if file exists
 */
function checkFile($file_path) {
    echo json_encode([
        'success' => true,
        'exists' => file_exists($file_path),
        'path' => $file_path,
        'readable' => is_readable($file_path),
        'writable' => is_writable($file_path),
        'size' => file_exists($file_path) ? filesize($file_path) : 0,
        'modified' => file_exists($file_path) ? filemtime($file_path) : null
    ]);
}

/**
 * Read config file
 */
function readFile($file_path) {
    if (!file_exists($file_path)) {
        echo json_encode([
            'success' => false,
            'error' => 'File does not exist',
            'path' => $file_path
        ]);
        return;
    }

    $content = file_get_contents($file_path);

    // Try to parse as JSON
    $json_content = json_decode($content, true);

    if (json_last_error() === JSON_ERROR_NONE) {
        echo json_encode([
            'success' => true,
            'content' => $json_content,
            'raw' => $content,
            'path' => $file_path
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'content' => $content,
            'raw' => $content,
            'path' => $file_path,
            'warning' => 'Not valid JSON'
        ]);
    }
}

/**
 * Write config file
 */
function writeFile($file_path) {
    $content = $_POST['content'] ?? '';

    if (empty($content)) {
        echo json_encode([
            'success' => false,
            'error' => 'No content provided'
        ]);
        return;
    }

    // Validate JSON
    $json_test = json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid JSON: ' . json_last_error_msg()
        ]);
        return;
    }

    // Backup existing file
    if (file_exists($file_path)) {
        $backup_path = $file_path . '.backup.' . date('Y-m-d_H-i-s');
        copy($file_path, $backup_path);
    }

    // Ensure directory exists
    $dir = dirname($file_path);
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }

    // Write file
    $result = file_put_contents($file_path, $content);

    if ($result !== false) {
        // Set secure permissions
        if (strpos($file_path, SECURE_CONFIG_DIR) !== false) {
            chmod($file_path, 0600);
        } else {
            chmod($file_path, 0644);
        }

        echo json_encode([
            'success' => true,
            'message' => 'Configuration saved successfully',
            'path' => $file_path,
            'bytes_written' => $result
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to write file'
        ]);
    }
}

/**
 * List all config files
 */
function listFiles() {
    global $ALLOWED_FILES;

    $files = [];

    foreach ($ALLOWED_FILES as $file => $dir) {
        $path = $dir . $file;
        $files[] = [
            'file' => $file,
            'path' => $path,
            'exists' => file_exists($path),
            'size' => file_exists($path) ? filesize($path) : 0,
            'modified' => file_exists($path) ? date('Y-m-d H:i:s', filemtime($path)) : null,
            'secure' => $dir === SECURE_CONFIG_DIR
        ];
    }

    echo json_encode([
        'success' => true,
        'files' => $files,
        'secure_dir' => SECURE_CONFIG_DIR,
        'public_dir' => PUBLIC_CONFIG_DIR
    ]);
}

/**
 * Create backup of file
 */
function backupFile($file_path) {
    if (!file_exists($file_path)) {
        echo json_encode([
            'success' => false,
            'error' => 'File does not exist'
        ]);
        return;
    }

    $backup_dir = '/home/flexpbxuser/backups/config/';
    @mkdir($backup_dir, 0700, true);

    $filename = basename($file_path);
    $backup_path = $backup_dir . $filename . '.' . date('Y-m-d_H-i-s') . '.backup';

    if (copy($file_path, $backup_path)) {
        chmod($backup_path, 0600);

        echo json_encode([
            'success' => true,
            'message' => 'Backup created successfully',
            'backup_path' => $backup_path
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to create backup'
        ]);
    }
}