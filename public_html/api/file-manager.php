<?php
// FlexPBX File Manager API - For Development/Testing
// Upload to: /home/flexpbxuser/public_html/api/file-manager.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$baseDir = '/home/flexpbxuser/public_html';
$uploadsDir = $baseDir . '/uploads';

// Ensure uploads directory exists
if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0755, true);
}

$action = $_POST['action'] ?? $_GET['action'] ?? 'status';

switch ($action) {
    case 'status':
        echo json_encode([
            'success' => true,
            'message' => 'File Manager API is active',
            'base_dir' => $baseDir,
            'uploads_dir' => $uploadsDir,
            'php_version' => PHP_VERSION,
            'server_time' => date('Y-m-d H:i:s'),
            'available_actions' => [
                'status' => 'Show this status',
                'list' => 'List files in directory',
                'read' => 'Read file contents',
                'write' => 'Write file contents',
                'move' => 'Move file from uploads to target',
                'copy' => 'Copy file',
                'delete' => 'Delete file',
                'mkdir' => 'Create directory',
                'config_check' => 'Check config.php files',
                'test_db' => 'Test database connection',
                'upload' => 'Upload file via POST'
            ]
        ]);
        break;

    case 'list':
        $path = $_POST['path'] ?? $_GET['path'] ?? '';
        $fullPath = $baseDir . '/' . ltrim($path, '/');

        if (!is_dir($fullPath) || !realpath($fullPath) || strpos(realpath($fullPath), $baseDir) !== 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid directory']);
            break;
        }

        $files = [];
        $items = scandir($fullPath);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $itemPath = $fullPath . '/' . $item;
            $files[] = [
                'name' => $item,
                'type' => is_dir($itemPath) ? 'directory' : 'file',
                'size' => is_file($itemPath) ? filesize($itemPath) : 0,
                'modified' => date('Y-m-d H:i:s', filemtime($itemPath)),
                'permissions' => substr(sprintf('%o', fileperms($itemPath)), -4)
            ];
        }

        echo json_encode([
            'success' => true,
            'path' => $path,
            'files' => $files
        ]);
        break;

    case 'read':
        $file = $_POST['file'] ?? $_GET['file'] ?? '';
        $fullPath = $baseDir . '/' . ltrim($file, '/');

        if (!file_exists($fullPath) || !realpath($fullPath) || strpos(realpath($fullPath), $baseDir) !== 0) {
            echo json_encode(['success' => false, 'error' => 'File not found or access denied']);
            break;
        }

        $content = file_get_contents($fullPath);
        echo json_encode([
            'success' => true,
            'file' => $file,
            'content' => $content,
            'size' => strlen($content)
        ]);
        break;

    case 'write':
        $file = $_POST['file'] ?? '';
        $content = $_POST['content'] ?? '';
        $fullPath = $baseDir . '/' . ltrim($file, '/');

        // Security check
        if (!$fullPath || strpos($fullPath, '..') !== false) {
            echo json_encode(['success' => false, 'error' => 'Invalid file path']);
            break;
        }

        // Ensure directory exists
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $result = file_put_contents($fullPath, $content);
        chmod($fullPath, 0644);

        echo json_encode([
            'success' => $result !== false,
            'file' => $file,
            'bytes_written' => $result,
            'message' => $result !== false ? 'File written successfully' : 'Failed to write file'
        ]);
        break;

    case 'move':
        $source = $_POST['source'] ?? '';
        $target = $_POST['target'] ?? '';

        $sourcePath = $uploadsDir . '/' . ltrim($source, '/');
        $targetPath = $baseDir . '/' . ltrim($target, '/');

        if (!file_exists($sourcePath)) {
            echo json_encode(['success' => false, 'error' => 'Source file not found']);
            break;
        }

        // Ensure target directory exists
        $targetDir = dirname($targetPath);
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $result = rename($sourcePath, $targetPath);
        if ($result) {
            chmod($targetPath, 0644);
        }

        echo json_encode([
            'success' => $result,
            'source' => $source,
            'target' => $target,
            'message' => $result ? 'File moved successfully' : 'Failed to move file'
        ]);
        break;

    case 'config_check':
        $configFiles = [
            'api/config.php',
            'config.php',
            'admin/config.php'
        ];

        $configs = [];
        foreach ($configFiles as $configFile) {
            $fullPath = $baseDir . '/' . $configFile;
            if (file_exists($fullPath)) {
                $content = file_get_contents($fullPath);
                $configs[$configFile] = [
                    'exists' => true,
                    'size' => strlen($content),
                    'modified' => date('Y-m-d H:i:s', filemtime($fullPath)),
                    'content_preview' => substr($content, 0, 500) . (strlen($content) > 500 ? '...' : '')
                ];
            } else {
                $configs[$configFile] = ['exists' => false];
            }
        }

        echo json_encode([
            'success' => true,
            'config_files' => $configs
        ]);
        break;

    case 'test_db':
        $configFile = $baseDir . '/api/config.php';
        if (!file_exists($configFile)) {
            echo json_encode(['success' => false, 'error' => 'Config file not found']);
            break;
        }

        // Parse config file to extract DB credentials
        $content = file_get_contents($configFile);
        $dbHost = $dbName = $dbUser = $dbPass = '';

        if (preg_match('/\$db_host\s*=\s*[\'"]([^\'"]*)[\'"]/', $content, $matches)) {
            $dbHost = $matches[1];
        }
        if (preg_match('/\$db_name\s*=\s*[\'"]([^\'"]*)[\'"]/', $content, $matches)) {
            $dbName = $matches[1];
        }
        if (preg_match('/\$db_user\s*=\s*[\'"]([^\'"]*)[\'"]/', $content, $matches)) {
            $dbUser = $matches[1];
        }
        if (preg_match('/\$db_pass\s*=\s*[\'"]([^\'"]*)[\'"]/', $content, $matches)) {
            $dbPass = $matches[1];
        }

        try {
            $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPass);
            echo json_encode([
                'success' => true,
                'message' => 'Database connection successful',
                'host' => $dbHost,
                'database' => $dbName,
                'user' => $dbUser
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => 'Database connection failed: ' . $e->getMessage(),
                'host' => $dbHost,
                'database' => $dbName,
                'user' => $dbUser
            ]);
        }
        break;

    case 'upload':
        if (!isset($_FILES['file'])) {
            echo json_encode(['success' => false, 'error' => 'No file uploaded']);
            break;
        }

        $file = $_FILES['file'];
        $targetPath = $uploadsDir . '/' . basename($file['name']);

        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            chmod($targetPath, 0644);
            echo json_encode([
                'success' => true,
                'message' => 'File uploaded successfully',
                'filename' => basename($file['name']),
                'size' => $file['size'],
                'upload_path' => 'uploads/' . basename($file['name'])
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Upload failed']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
}
?>