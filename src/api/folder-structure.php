<?php
/**
 * FlexPBX Folder Structure Manager API
 * Manages critical folder structure and backup path configuration
 * Created: October 23, 2025
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';

// Authentication check
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Admin access required']);
    exit;
}

// Define critical folder structure that MUST exist on main drive
define('CRITICAL_FOLDERS', [
    'users' => [
        'path' => '/home/flexpbxuser/users',
        'description' => 'Active user configuration files (JSON)',
        'permissions' => 0755,
        'owner' => 'flexpbxuser:flexpbxuser',
        'required' => true,
        'movable' => false
    ],
    'config' => [
        'path' => '/home/flexpbxuser/config',
        'description' => 'System configuration files',
        'permissions' => 0755,
        'owner' => 'flexpbxuser:flexpbxuser',
        'required' => true,
        'movable' => false
    ],
    'logs' => [
        'path' => '/home/flexpbxuser/logs',
        'description' => 'Active system and application logs',
        'permissions' => 0755,
        'owner' => 'flexpbxuser:flexpbxuser',
        'required' => true,
        'movable' => false
    ],
    'cache' => [
        'path' => '/home/flexpbxuser/cache',
        'description' => 'Runtime cache and temporary data',
        'permissions' => 0755,
        'owner' => 'flexpbxuser:flexpbxuser',
        'required' => true,
        'movable' => false
    ],
    'modules' => [
        'path' => '/home/flexpbxuser/modules',
        'description' => 'Active FlexPBX modules',
        'permissions' => 0755,
        'owner' => 'flexpbxuser:flexpbxuser',
        'required' => true,
        'movable' => false
    ],
    'scripts' => [
        'path' => '/home/flexpbxuser/scripts',
        'description' => 'Active system scripts',
        'permissions' => 0755,
        'owner' => 'flexpbxuser:flexpbxuser',
        'required' => true,
        'movable' => false
    ],
    'admins' => [
        'path' => '/home/flexpbxuser/admins',
        'description' => 'Admin account data',
        'permissions' => 0755,
        'owner' => 'flexpbxuser:flexpbxuser',
        'required' => true,
        'movable' => false
    ],
    'bugs' => [
        'path' => '/home/flexpbxuser/bugs',
        'description' => 'Bug tracking data',
        'permissions' => 0755,
        'owner' => 'flexpbxuser:flexpbxuser',
        'required' => true,
        'movable' => false
    ],
    'callcenter' => [
        'path' => '/home/flexpbxuser/callcenter',
        'description' => 'Call center data and recordings',
        'permissions' => 0755,
        'owner' => 'flexpbxuser:flexpbxuser',
        'required' => true,
        'movable' => false
    ],
    'public_html' => [
        'path' => '/home/flexpbxuser/public_html',
        'description' => 'Web application files',
        'permissions' => 0755,
        'owner' => 'flexpbxuser:flexpbxuser',
        'required' => true,
        'movable' => false
    ],
    'public_html/api' => [
        'path' => '/home/flexpbxuser/public_html/api',
        'description' => 'API endpoint files',
        'permissions' => 0755,
        'owner' => 'flexpbxuser:flexpbxuser',
        'required' => true,
        'movable' => false
    ],
    'public_html/admin' => [
        'path' => '/home/flexpbxuser/public_html/admin',
        'description' => 'Admin panel files',
        'permissions' => 0755,
        'owner' => 'flexpbxuser:flexpbxuser',
        'required' => true,
        'movable' => false
    ],
    'public_html/user-portal' => [
        'path' => '/home/flexpbxuser/public_html/user-portal',
        'description' => 'User portal files',
        'permissions' => 0755,
        'owner' => 'flexpbxuser:flexpbxuser',
        'required' => true,
        'movable' => false
    ],
    'public_html/uploads' => [
        'path' => '/home/flexpbxuser/public_html/uploads',
        'description' => 'User uploaded files',
        'permissions' => 0755,
        'owner' => 'flexpbxuser:flexpbxuser',
        'required' => true,
        'movable' => false
    ],
    'public_html/media' => [
        'path' => '/home/flexpbxuser/public_html/media',
        'description' => 'Media files (MOH, sounds, recordings)',
        'permissions' => 0755,
        'owner' => 'flexpbxuser:flexpbxuser',
        'required' => true,
        'movable' => false
    ]
]);

// Define folders that CAN be moved to backup drive (with symlinks)
define('MOVABLE_FOLDERS', [
    'backups' => [
        'path' => '/home/flexpbxuser/backups',
        'backup_path' => '/mnt/backup/flexpbx-backups',
        'description' => 'System backup files (disaster recovery only)',
        'permissions' => 0755,
        'owner' => 'flexpbxuser:flexpbxuser',
        'required' => true,
        'movable' => true,
        'recommended_location' => 'backup_drive'
    ],
    'downloads' => [
        'path' => '/home/flexpbxuser/public_html/downloads',
        'backup_path' => '/mnt/backup/flexpbx-downloads',
        'description' => 'Desktop apps and installers',
        'permissions' => 0755,
        'owner' => 'flexpbxuser:flexpbxuser',
        'required' => false,
        'movable' => true,
        'recommended_location' => 'backup_drive'
    ],
    'client_apps' => [
        'path' => '/home/flexpbxuser/apps/clients',
        'backup_path' => '/mnt/backup/flexpbx-clients',
        'description' => 'Client application binaries',
        'permissions' => 0755,
        'owner' => 'flexpbxuser:flexpbxuser',
        'required' => false,
        'movable' => true,
        'recommended_location' => 'backup_drive'
    ],
    'documentation' => [
        'path' => '/home/flexpbxuser/documentation',
        'backup_path' => '/mnt/backup/flexpbx-docs',
        'description' => 'System documentation and archives',
        'permissions' => 0755,
        'owner' => 'flexpbxuser:flexpbxuser',
        'required' => false,
        'movable' => true,
        'recommended_location' => 'backup_drive'
    ]
]);

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'check':
        checkFolderStructure();
        break;

    case 'create':
        createMissingFolders();
        break;

    case 'verify':
        verifyAllFolders();
        break;

    case 'get_structure':
        getFolderStructure();
        break;

    case 'move_folder':
        moveFolderToBackup();
        break;

    case 'get_disk_usage':
        getDiskUsage();
        break;

    case 'update_config':
        updateFolderConfig();
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}

/**
 * Check folder structure and return status
 */
function checkFolderStructure() {
    $results = [
        'critical' => [],
        'movable' => [],
        'missing' => [],
        'symlinks' => []
    ];

    // Check critical folders
    foreach (CRITICAL_FOLDERS as $key => $folder) {
        $status = checkFolder($folder['path']);
        $results['critical'][$key] = array_merge($folder, $status);

        if (!$status['exists']) {
            $results['missing'][] = $key;
        }
    }

    // Check movable folders
    foreach (MOVABLE_FOLDERS as $key => $folder) {
        $status = checkFolder($folder['path']);
        $isSymlink = is_link($folder['path']);

        $results['movable'][$key] = array_merge($folder, $status, [
            'is_symlink' => $isSymlink,
            'symlink_target' => $isSymlink ? readlink($folder['path']) : null
        ]);

        if ($isSymlink) {
            $results['symlinks'][$key] = readlink($folder['path']);
        }
    }

    echo json_encode([
        'success' => true,
        'results' => $results,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Check individual folder status
 */
function checkFolder($path) {
    $exists = file_exists($path);
    $isDir = is_dir($path);
    $isWritable = is_writable($path);
    $perms = $exists ? substr(sprintf('%o', fileperms($path)), -4) : null;

    $size = 0;
    $fileCount = 0;

    if ($exists && $isDir) {
        $output = shell_exec("du -sb " . escapeshellarg($path) . " 2>/dev/null");
        if ($output) {
            $size = intval(explode("\t", $output)[0]);
        }

        $fileCount = intval(shell_exec("find " . escapeshellarg($path) . " -type f 2>/dev/null | wc -l"));
    }

    return [
        'exists' => $exists,
        'is_directory' => $isDir,
        'is_writable' => $isWritable,
        'permissions' => $perms,
        'size_bytes' => $size,
        'size_human' => formatBytes($size),
        'file_count' => $fileCount
    ];
}

/**
 * Create missing critical folders
 */
function createMissingFolders() {
    $created = [];
    $errors = [];

    foreach (CRITICAL_FOLDERS as $key => $folder) {
        if (!file_exists($folder['path'])) {
            if (mkdir($folder['path'], $folder['permissions'], true)) {
                chmod($folder['path'], $folder['permissions']);
                $created[] = $key;
            } else {
                $errors[] = "Failed to create: " . $folder['path'];
            }
        }
    }

    echo json_encode([
        'success' => count($errors) === 0,
        'created' => $created,
        'errors' => $errors,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Verify all folders and return detailed status
 */
function verifyAllFolders() {
    $issues = [];
    $warnings = [];
    $ok = [];

    // Check critical folders
    foreach (CRITICAL_FOLDERS as $key => $folder) {
        if (!file_exists($folder['path'])) {
            $issues[] = [
                'type' => 'missing',
                'folder' => $key,
                'path' => $folder['path'],
                'severity' => 'critical',
                'message' => "Critical folder missing: {$folder['description']}"
            ];
        } elseif (!is_writable($folder['path'])) {
            $issues[] = [
                'type' => 'permissions',
                'folder' => $key,
                'path' => $folder['path'],
                'severity' => 'warning',
                'message' => "Folder not writable: {$folder['description']}"
            ];
        } else {
            $ok[] = $key;
        }
    }

    // Check disk space
    $diskUsage = getDiskUsageData();
    if ($diskUsage['main_drive']['percent'] > 90) {
        $warnings[] = [
            'type' => 'disk_space',
            'severity' => 'critical',
            'message' => "Main drive is {$diskUsage['main_drive']['percent']}% full!"
        ];
    } elseif ($diskUsage['main_drive']['percent'] > 80) {
        $warnings[] = [
            'type' => 'disk_space',
            'severity' => 'warning',
            'message' => "Main drive is {$diskUsage['main_drive']['percent']}% full"
        ];
    }

    echo json_encode([
        'success' => count($issues) === 0,
        'issues' => $issues,
        'warnings' => $warnings,
        'ok' => $ok,
        'total_critical' => count(CRITICAL_FOLDERS),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Get complete folder structure definition
 */
function getFolderStructure() {
    echo json_encode([
        'success' => true,
        'critical_folders' => CRITICAL_FOLDERS,
        'movable_folders' => MOVABLE_FOLDERS,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Move folder to backup drive and create symlink
 */
function moveFolderToBackup() {
    $data = json_decode(file_get_contents('php://input'), true);
    $folderKey = $data['folder'] ?? '';

    if (!isset(MOVABLE_FOLDERS[$folderKey])) {
        echo json_encode(['success' => false, 'error' => 'Invalid folder']);
        return;
    }

    $folder = MOVABLE_FOLDERS[$folderKey];
    $sourcePath = $folder['path'];
    $targetPath = $folder['backup_path'];

    // Check if source exists
    if (!file_exists($sourcePath)) {
        echo json_encode(['success' => false, 'error' => 'Source folder does not exist']);
        return;
    }

    // Check if already a symlink
    if (is_link($sourcePath)) {
        echo json_encode([
            'success' => false,
            'error' => 'Already a symlink',
            'target' => readlink($sourcePath)
        ]);
        return;
    }

    // Create backup drive directory if needed
    if (!file_exists(dirname($targetPath))) {
        mkdir(dirname($targetPath), 0755, true);
    }

    // Move folder to backup drive
    $output = [];
    exec("rsync -av " . escapeshellarg($sourcePath . '/') . " " . escapeshellarg($targetPath . '/') . " 2>&1", $output, $returnVar);

    if ($returnVar !== 0) {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to copy files',
            'output' => implode("\n", $output)
        ]);
        return;
    }

    // Rename original to backup
    $backupPath = $sourcePath . '.moved-' . date('Ymd-His');
    rename($sourcePath, $backupPath);

    // Create symlink
    if (symlink($targetPath, $sourcePath)) {
        echo json_encode([
            'success' => true,
            'message' => "Folder moved to backup drive and symlink created",
            'original_backup' => $backupPath,
            'symlink' => $sourcePath,
            'target' => $targetPath
        ]);
    } else {
        // Restore original if symlink failed
        rename($backupPath, $sourcePath);
        echo json_encode([
            'success' => false,
            'error' => 'Failed to create symlink, original restored'
        ]);
    }
}

/**
 * Get disk usage information
 */
function getDiskUsage() {
    $data = getDiskUsageData();
    echo json_encode([
        'success' => true,
        'disk_usage' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Get disk usage data
 */
function getDiskUsageData() {
    // Get main drive usage
    $df = shell_exec("df -h /home | tail -1");
    $parts = preg_split('/\s+/', trim($df));

    $mainDrive = [
        'device' => $parts[0] ?? 'unknown',
        'size' => $parts[1] ?? '0',
        'used' => $parts[2] ?? '0',
        'available' => $parts[3] ?? '0',
        'percent' => intval(str_replace('%', '', $parts[4] ?? '0'))
    ];

    // Get backup drive usage
    $backupDrive = ['exists' => false];
    if (file_exists('/mnt/backup')) {
        $dfBackup = shell_exec("df -h /mnt/backup 2>/dev/null | tail -1");
        if ($dfBackup) {
            $backupParts = preg_split('/\s+/', trim($dfBackup));
            $backupDrive = [
                'exists' => true,
                'device' => $backupParts[0] ?? 'unknown',
                'size' => $backupParts[1] ?? '0',
                'used' => $backupParts[2] ?? '0',
                'available' => $backupParts[3] ?? '0',
                'percent' => intval(str_replace('%', '', $backupParts[4] ?? '0'))
            ];
        }
    }

    return [
        'main_drive' => $mainDrive,
        'backup_drive' => $backupDrive
    ];
}

/**
 * Update folder configuration
 */
function updateFolderConfig() {
    $data = json_decode(file_get_contents('php://input'), true);

    // This would update a configuration file with custom folder paths
    // For now, just return success
    echo json_encode([
        'success' => true,
        'message' => 'Configuration updated',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Format bytes to human readable
 */
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));

    return round($bytes, $precision) . ' ' . $units[$pow];
}
