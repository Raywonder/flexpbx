<?php
/**
 * FlexPBX Music on Hold (MOH) API
 * Manage MOH classes and audio files
 * Created: October 16, 2025
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Configuration
define('MOH_BASE_DIR', '/var/lib/asterisk/moh');
define('MOH_CONF_FILE', '/etc/asterisk/musiconhold.conf');
define('ALLOWED_FORMATS', ['ulaw', 'gsm', 'wav', 'mp3']);
define('MAX_FILE_SIZE', 50 * 1024 * 1024); // 50MB

$path = $_GET['path'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// Get POST data
$postData = [];
if ($method === 'POST') {
    $postData = json_decode(file_get_contents('php://input'), true) ?? [];
}

switch ($path) {
    case '':
    case 'info':
        handleInfo();
        break;

    case 'classes':
        handleListClasses();
        break;

    case 'files':
        handleListFiles($postData);
        break;

    case 'upload':
        handleUpload();
        break;

    case 'delete':
        handleDelete($postData);
        break;

    case 'create_class':
        handleCreateClass($postData);
        break;

    case 'delete_class':
        handleDeleteClass($postData);
        break;

    case 'reload':
        handleReload();
        break;

    case 'test':
        handleTestMOH($postData);
        break;

    default:
        respond(false, 'Invalid path', null, 404);
        break;
}

/**
 * API Information
 */
function handleInfo() {
    respond(true, 'FlexPBX Music on Hold API', [
        'version' => '1.0',
        'endpoints' => [
            'classes' => 'List all MOH classes',
            'files' => 'List files in a class',
            'upload' => 'Upload audio file to class',
            'delete' => 'Delete audio file',
            'create_class' => 'Create new MOH class',
            'delete_class' => 'Delete MOH class',
            'reload' => 'Reload MOH configuration',
            'test' => 'Test MOH playback'
        ],
        'allowed_formats' => ALLOWED_FORMATS,
        'max_file_size' => MAX_FILE_SIZE
    ]);
}

/**
 * List all MOH classes
 */
function handleListClasses() {
    exec('sudo -u asterisk /usr/sbin/asterisk -rx "moh show classes" 2>&1', $output, $return_code);

    $classes = [];
    $currentClass = null;

    foreach ($output as $line) {
        if (preg_match('/^Class:\s+(.+)$/', $line, $matches)) {
            $currentClass = trim($matches[1]);
            $classes[$currentClass] = [
                'name' => $currentClass,
                'mode' => '',
                'directory' => '',
                'file_count' => 0,
                'files' => []
            ];
        } elseif ($currentClass && preg_match('/^\s+Mode:\s+(.+)$/', $line, $matches)) {
            $classes[$currentClass]['mode'] = trim($matches[1]);
        } elseif ($currentClass && preg_match('/^\s+Directory:\s+(.+)$/', $line, $matches)) {
            $directory = trim($matches[1]);
            $classes[$currentClass]['directory'] = $directory;

            // Count files in directory
            if (is_dir($directory)) {
                $files = glob($directory . '/*');
                $classes[$currentClass]['file_count'] = count($files);

                foreach ($files as $file) {
                    $classes[$currentClass]['files'][] = [
                        'name' => basename($file),
                        'size' => filesize($file),
                        'size_formatted' => formatBytes(filesize($file)),
                        'modified' => date('Y-m-d H:i:s', filemtime($file))
                    ];
                }
            }
        }
    }

    respond(true, 'MOH classes retrieved', [
        'count' => count($classes),
        'classes' => array_values($classes)
    ]);
}

/**
 * List files in a class
 */
function handleListFiles($data) {
    $className = $data['class'] ?? $_GET['class'] ?? null;

    if (!$className) {
        respond(false, 'Class name required');
        return;
    }

    $directory = MOH_BASE_DIR . '/' . $className;

    if (!is_dir($directory)) {
        respond(false, 'Class directory not found');
        return;
    }

    $files = [];
    $audioFiles = glob($directory . '/*');

    foreach ($audioFiles as $file) {
        if (!is_file($file)) continue;

        $files[] = [
            'name' => basename($file),
            'path' => $file,
            'size' => filesize($file),
            'size_formatted' => formatBytes(filesize($file)),
            'format' => pathinfo($file, PATHINFO_EXTENSION),
            'modified' => date('Y-m-d H:i:s', filemtime($file)),
            'is_playing' => false // TODO: Check if currently playing
        ];
    }

    respond(true, 'Files retrieved', [
        'class' => $className,
        'directory' => $directory,
        'count' => count($files),
        'files' => $files
    ]);
}

/**
 * Upload audio file to MOH class
 */
function handleUpload() {
    if (!isset($_FILES['file'])) {
        respond(false, 'No file uploaded');
        return;
    }

    $className = $_POST['class'] ?? null;

    if (!$className) {
        respond(false, 'Class name required');
        return;
    }

    $file = $_FILES['file'];
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    // Validate file format
    if (!in_array($extension, ALLOWED_FORMATS)) {
        respond(false, 'Invalid file format. Allowed: ' . implode(', ', ALLOWED_FORMATS));
        return;
    }

    // Validate file size
    if ($file['size'] > MAX_FILE_SIZE) {
        respond(false, 'File too large. Max: ' . formatBytes(MAX_FILE_SIZE));
        return;
    }

    $directory = MOH_BASE_DIR . '/' . $className;

    // Create directory if doesn't exist
    if (!is_dir($directory)) {
        mkdir($directory, 0755, true);
        chown($directory, 'asterisk');
        chgrp($directory, 'asterisk');
    }

    $destination = $directory . '/' . basename($file['name']);

    if (move_uploaded_file($file['tmp_name'], $destination)) {
        chmod($destination, 0644);
        chown($destination, 'asterisk');
        chgrp($destination, 'asterisk');

        // Reload MOH
        exec('sudo -u asterisk /usr/sbin/asterisk -rx "moh reload" 2>&1');

        respond(true, 'File uploaded successfully', [
            'file' => basename($file['name']),
            'class' => $className,
            'size' => formatBytes($file['size'])
        ]);
    } else {
        respond(false, 'Failed to save file');
    }
}

/**
 * Delete audio file
 */
function handleDelete($data) {
    $className = $data['class'] ?? null;
    $fileName = $data['file'] ?? null;

    if (!$className || !$fileName) {
        respond(false, 'Class and file name required');
        return;
    }

    $filePath = MOH_BASE_DIR . '/' . $className . '/' . $fileName;

    if (!file_exists($filePath)) {
        respond(false, 'File not found');
        return;
    }

    if (unlink($filePath)) {
        // Reload MOH
        exec('sudo -u asterisk /usr/sbin/asterisk -rx "moh reload" 2>&1');

        respond(true, 'File deleted successfully');
    } else {
        respond(false, 'Failed to delete file');
    }
}

/**
 * Create new MOH class
 */
function handleCreateClass($data) {
    $className = $data['name'] ?? null;
    $mode = $data['mode'] ?? 'files';

    if (!$className) {
        respond(false, 'Class name required');
        return;
    }

    // Validate class name (alphanumeric, dash, underscore only)
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $className)) {
        respond(false, 'Invalid class name. Use only letters, numbers, dash, and underscore');
        return;
    }

    // Create directory
    $directory = MOH_BASE_DIR . '/' . $className;

    if (is_dir($directory)) {
        respond(false, 'Class already exists');
        return;
    }

    mkdir($directory, 0755, true);
    chown($directory, 'asterisk');
    chgrp($directory, 'asterisk');

    // Add to musiconhold.conf
    $config = file_get_contents(MOH_CONF_FILE);
    $newClass = "\n[$className]\n";
    $newClass .= "mode=$mode\n";
    $newClass .= "directory=$directory\n";
    $newClass .= "sort=random\n";
    $newClass .= "format=ulaw\n";

    file_put_contents(MOH_CONF_FILE, $config . $newClass);
    chown(MOH_CONF_FILE, 'asterisk');
    chgrp(MOH_CONF_FILE, 'asterisk');
    chmod(MOH_CONF_FILE, 0644);

    // Reload MOH
    exec('sudo -u asterisk /usr/sbin/asterisk -rx "moh reload" 2>&1');

    respond(true, 'MOH class created successfully', [
        'name' => $className,
        'directory' => $directory
    ]);
}

/**
 * Delete MOH class
 */
function handleDeleteClass($data) {
    $className = $data['name'] ?? null;

    if (!$className) {
        respond(false, 'Class name required');
        return;
    }

    // Don't allow deleting default class
    if ($className === 'default') {
        respond(false, 'Cannot delete default class');
        return;
    }

    $directory = MOH_BASE_DIR . '/' . $className;

    // Remove directory and files
    if (is_dir($directory)) {
        exec("rm -rf " . escapeshellarg($directory));
    }

    // Remove from musiconhold.conf
    $config = file_get_contents(MOH_CONF_FILE);
    $pattern = "/\n\[$className\]\n(?:[^\[]*\n)*/";
    $newConfig = preg_replace($pattern, "\n", $config);

    file_put_contents(MOH_CONF_FILE, $newConfig);
    chown(MOH_CONF_FILE, 'asterisk');
    chgrp(MOH_CONF_FILE, 'asterisk');

    // Reload MOH
    exec('sudo -u asterisk /usr/sbin/asterisk -rx "moh reload" 2>&1');

    respond(true, 'MOH class deleted successfully');
}

/**
 * Reload MOH configuration
 */
function handleReload() {
    exec('sudo -u asterisk /usr/sbin/asterisk -rx "moh reload" 2>&1', $output, $return_code);

    if ($return_code === 0) {
        respond(true, 'MOH configuration reloaded', [
            'output' => implode("\n", $output)
        ]);
    } else {
        respond(false, 'Failed to reload MOH', [
            'output' => implode("\n", $output)
        ], 500);
    }
}

/**
 * Test MOH playback
 */
function handleTestMOH($data) {
    $className = $data['class'] ?? 'default';

    exec('sudo -u asterisk /usr/sbin/asterisk -rx "moh show classes" 2>&1', $output);

    $classExists = false;
    foreach ($output as $line) {
        if (preg_match('/^Class:\s+' . preg_quote($className) . '$/i', $line)) {
            $classExists = true;
            break;
        }
    }

    if ($classExists) {
        respond(true, 'MOH class is available for playback', [
            'class' => $className,
            'note' => 'MOH will play when calls are placed on hold'
        ]);
    } else {
        respond(false, 'MOH class not found');
    }
}

/**
 * Helper function to format bytes
 */
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}

function respond($success, $message, $data = null, $httpCode = 200) {
    http_response_code($httpCode);

    $response = [
        'success' => $success,
        'message' => $message,
        'timestamp' => date('c')
    ];

    if ($data !== null) {
        $response = array_merge($response, $data);
    }

    echo json_encode($response, JSON_PRETTY_PRINT);
    exit;
}
?>
