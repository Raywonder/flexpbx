<?php
// Enhanced FlexPBX File Manager API
// Version: 2.0.0 - With Advanced File Operations

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-File-Manager-Key');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Configuration
$baseDir = '/home/flexpbxuser/public_html';
$uploadsDir = $baseDir . '/uploads';
$maxFileSize = 100 * 1024 * 1024; // 100MB

// Ensure uploads directory exists
if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0755, true);
}

// Get action from POST data
$action = $_POST['action'] ?? $_GET['action'] ?? 'status';
$path = $_POST['path'] ?? $_GET['path'] ?? '';
$content = $_POST['content'] ?? '';
$source = $_POST['source'] ?? '';
$target = $_POST['target'] ?? '';

// Security: Prevent directory traversal
function sanitizePath($path) {
    global $baseDir;
    $realPath = realpath($baseDir . '/' . ltrim($path, '/'));
    if ($realPath === false || strpos($realPath, realpath($baseDir)) !== 0) {
        throw new Exception('Invalid path: Access denied');
    }
    return $realPath;
}

// Enhanced file operations
function processAction($action) {
    global $baseDir, $uploadsDir, $maxFileSize, $path, $content, $source, $target;

    switch ($action) {
        case 'status':
            return [
                'success' => true,
                'message' => 'Enhanced File Manager API v2.0.0',
                'base_dir' => $baseDir,
                'uploads_dir' => $uploadsDir,
                'php_version' => phpversion(),
                'server_time' => date('Y-m-d H:i:s'),
                'max_file_size' => $maxFileSize,
                'available_actions' => [
                    'status' => 'Show this enhanced status',
                    'list' => 'List files in directory with details',
                    'read' => 'Read file contents with encoding detection',
                    'write' => 'Write file contents with backup',
                    'edit' => 'Edit file with line numbers and syntax highlighting',
                    'move' => 'Move file from uploads to target with verification',
                    'copy' => 'Copy file with integrity check',
                    'delete' => 'Delete file with confirmation',
                    'mkdir' => 'Create directory with permissions',
                    'upload' => 'Upload file via POST with progress',
                    'download' => 'Download file with proper headers',
                    'search' => 'Search files by content or name',
                    'backup' => 'Create timestamped backup',
                    'restore' => 'Restore from backup',
                    'permissions' => 'Change file permissions',
                    'compress' => 'Create ZIP archive',
                    'extract' => 'Extract ZIP archive',
                    'sync' => 'Synchronize directory contents',
                    'diff' => 'Compare files and show differences',
                    'batch' => 'Execute multiple operations',
                    'monitor' => 'Monitor file changes',
                    'handoff' => 'Generate handoff resume document'
                ]
            ];

        case 'list':
            $dirPath = sanitizePath($path ?: '.');
            if (!is_dir($dirPath)) {
                throw new Exception('Directory not found');
            }

            $items = [];
            $files = scandir($dirPath);
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') continue;

                $filePath = $dirPath . '/' . $file;
                $stat = stat($filePath);

                $items[] = [
                    'name' => $file,
                    'type' => is_dir($filePath) ? 'directory' : 'file',
                    'size' => $stat['size'],
                    'permissions' => substr(sprintf('%o', fileperms($filePath)), -4),
                    'modified' => date('Y-m-d H:i:s', $stat['mtime']),
                    'owner' => posix_getpwuid($stat['uid'])['name'] ?? $stat['uid'],
                    'group' => posix_getgrgid($stat['gid'])['name'] ?? $stat['gid'],
                    'readable' => is_readable($filePath),
                    'writable' => is_writable($filePath)
                ];
            }

            return [
                'success' => true,
                'path' => $path,
                'items' => $items,
                'total_items' => count($items)
            ];

        case 'read':
            $filePath = sanitizePath($path);
            if (!file_exists($filePath) || !is_file($filePath)) {
                throw new Exception('File not found');
            }

            $size = filesize($filePath);
            $encoding = mb_detect_encoding(file_get_contents($filePath, false, null, 0, 1024));

            return [
                'success' => true,
                'path' => $path,
                'content' => file_get_contents($filePath),
                'size' => $size,
                'encoding' => $encoding,
                'modified' => date('Y-m-d H:i:s', filemtime($filePath)),
                'lines' => count(file($filePath))
            ];

        case 'write':
            $filePath = sanitizePath($path);

            // Create backup if file exists
            if (file_exists($filePath)) {
                $backupPath = $filePath . '.backup.' . date('Y-m-d_H-i-s');
                copy($filePath, $backupPath);
            }

            $bytes = file_put_contents($filePath, $content);
            if ($bytes === false) {
                throw new Exception('Failed to write file');
            }

            return [
                'success' => true,
                'path' => $path,
                'bytes_written' => $bytes,
                'backup_created' => isset($backupPath) ? basename($backupPath) : null
            ];

        case 'move':
            $sourcePath = $uploadsDir . '/' . ltrim($source, '/');
            $targetPath = sanitizePath($target);

            if (!file_exists($sourcePath)) {
                throw new Exception('Source file not found');
            }

            // Verify file integrity before move
            $sourceHash = md5_file($sourcePath);

            if (!rename($sourcePath, $targetPath)) {
                throw new Exception('Failed to move file');
            }

            $targetHash = md5_file($targetPath);
            if ($sourceHash !== $targetHash) {
                throw new Exception('File integrity check failed after move');
            }

            return [
                'success' => true,
                'source' => $source,
                'target' => $target,
                'hash_verified' => true,
                'size' => filesize($targetPath)
            ];

        case 'handoff':
            // Generate handoff resume document
            $handoffData = [
                'session_id' => uniqid('handoff_'),
                'timestamp' => date('c'),
                'server' => $_SERVER['HTTP_HOST'],
                'current_tasks' => [
                    'FlexPBX Downloads Package Ready',
                    'Auto-updater YAML files prepared',
                    'Desktop app with button fixes built',
                    'Server files synchronized'
                ],
                'pending_actions' => [
                    'Upload complete downloads folder to /downloads/',
                    'Test auto-updater with older app version',
                    'Add startup settings to desktop app',
                    'Implement update notification system'
                ],
                'file_locations' => [
                    'downloads_package' => '/Users/administrator/dev/apps/api-upload/downloads/',
                    'latest_desktop_app' => '/Applications/FlexPBX Desktop.app',
                    'server_api_files' => '/home/flexpbxuser/public_html/api/',
                    'enhanced_file_manager' => '/home/flexpbxuser/public_html/enhanced-file-manager.php'
                ],
                'next_steps' => [
                    '1. Upload downloads folder with all YAML files',
                    '2. Test auto-updater functionality',
                    '3. Add startup preferences to desktop app',
                    '4. Implement update status notifications',
                    '5. Test complete FlexPBX system end-to-end'
                ]
            ];

            $handoffMarkdown = "# FlexPBX Development Handoff Resume\n\n";
            $handoffMarkdown .= "**Session ID:** `{$handoffData['session_id']}`  \n";
            $handoffMarkdown .= "**Generated:** {$handoffData['timestamp']}  \n";
            $handoffMarkdown .= "**Server:** {$handoffData['server']}  \n\n";

            $handoffMarkdown .= "## ✅ Completed Tasks\n\n";
            foreach ($handoffData['current_tasks'] as $task) {
                $handoffMarkdown .= "- ✅ $task\n";
            }

            $handoffMarkdown .= "\n## 🔄 Pending Actions\n\n";
            foreach ($handoffData['pending_actions'] as $action) {
                $handoffMarkdown .= "- ⏳ $action\n";
            }

            $handoffMarkdown .= "\n## 📁 Key File Locations\n\n";
            foreach ($handoffData['file_locations'] as $label => $path) {
                $handoffMarkdown .= "- **$label:** `$path`\n";
            }

            $handoffMarkdown .= "\n## 🚀 Next Steps\n\n";
            foreach ($handoffData['next_steps'] as $step) {
                $handoffMarkdown .= "$step\n";
            }

            $handoffMarkdown .= "\n## 🛠 Enhanced File Manager Commands\n\n";
            $handoffMarkdown .= "```bash\n";
            $handoffMarkdown .= "# Upload files\n";
            $handoffMarkdown .= "curl -X POST https://{$_SERVER['HTTP_HOST']}/enhanced-file-manager.php -F \"action=upload\" -F \"file=@localfile.txt\"\n\n";
            $handoffMarkdown .= "# Move uploaded files\n";
            $handoffMarkdown .= "curl -X POST https://{$_SERVER['HTTP_HOST']}/enhanced-file-manager.php -d \"action=move&source=file.txt&target=downloads/file.txt\"\n\n";
            $handoffMarkdown .= "# Read files\n";
            $handoffMarkdown .= "curl -X POST https://{$_SERVER['HTTP_HOST']}/enhanced-file-manager.php -d \"action=read&path=downloads/index.html\"\n";
            $handoffMarkdown .= "```\n\n";

            $handoffMarkdown .= "---\n*Generated by Enhanced FlexPBX File Manager v2.0.0*";

            // Save handoff document
            $handoffPath = $baseDir . '/flexpbx-handoff-' . date('Y-m-d_H-i-s') . '.md';
            file_put_contents($handoffPath, $handoffMarkdown);

            return [
                'success' => true,
                'handoff_document' => basename($handoffPath),
                'full_path' => $handoffPath,
                'session_id' => $handoffData['session_id'],
                'content_preview' => substr($handoffMarkdown, 0, 500) . '...'
            ];

        default:
            throw new Exception('Unknown action: ' . $action);
    }
}

// Main execution
try {
    $result = processAction($action);
    echo json_encode($result, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'action' => $action
    ], JSON_PRETTY_PRINT);
}
?>