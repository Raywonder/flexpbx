<?php
/**
 * FlexPBX Storage Configuration API
 * Manages storage paths for backups, encryption, and data
 * Allows admins to configure multiple storage drives
 *
 * Endpoints:
 * - GET  ?action=get_config - Get current storage configuration
 * - POST ?action=update_config - Update storage configuration
 * - GET  ?action=list_drives - List available drives/mount points
 * - POST ?action=test_path - Test if a path is writable
 */

header('Content-Type: application/json');
session_start();

// Check admin authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Admin access required']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$config_file = '/home/flexpbxuser/config/storage_config.json';
$config_dir = dirname($config_file);

// Ensure config directory exists
if (!is_dir($config_dir)) {
    mkdir($config_dir, 0750, true);
}

/**
 * Get current storage configuration
 */
if ($action === 'get_config') {
    if (!file_exists($config_file)) {
        // Create default configuration
        $default_config = [
            'version' => '1.0',
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $_SESSION['admin_username'],
            'storage_locations' => [
                'backups' => [
                    'primary' => '/home/flexpbxuser/backup',
                    'secondary' => [],
                    'auto_rotate' => true,
                    'max_size_gb' => 100
                ],
                'encryption' => [
                    'primary' => '/home/flexpbxuser/config',
                    'secondary' => [],
                    'auto_rotate' => false
                ],
                'recordings' => [
                    'primary' => '/home/flexpbxuser/public_html/uploads/recordings',
                    'secondary' => [],
                    'auto_rotate' => false,
                    'max_size_gb' => 500
                ],
                'voicemail' => [
                    'primary' => '/var/spool/asterisk/voicemail',
                    'secondary' => [],
                    'auto_rotate' => false,
                    'max_size_gb' => 50
                ],
                'logs' => [
                    'primary' => '/var/log/asterisk',
                    'secondary' => [],
                    'auto_rotate' => true,
                    'max_size_gb' => 20
                ],
                'temp' => [
                    'primary' => '/tmp/flexpbx',
                    'secondary' => [],
                    'auto_rotate' => false,
                    'max_size_gb' => 10
                ]
            ],
            'drive_priorities' => [],
            'notifications' => [
                'notify_on_full' => true,
                'notify_threshold' => 90
            ]
        ];

        file_put_contents($config_file, json_encode($default_config, JSON_PRETTY_PRINT));
        chmod($config_file, 0600);

        $config = $default_config;
    } else {
        $config = json_decode(file_get_contents($config_file), true);
    }

    echo json_encode([
        'success' => true,
        'config' => $config
    ]);
    exit;
}

/**
 * Update storage configuration
 */
if ($action === 'update_config' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['storage_locations'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing storage_locations']);
        exit;
    }

    // Validate all paths before saving
    $validation_errors = [];

    foreach ($data['storage_locations'] as $type => $config) {
        // Validate primary path
        if (isset($config['primary'])) {
            $path = $config['primary'];

            // Check if path exists or can be created
            if (!file_exists($path)) {
                if (!mkdir($path, 0750, true)) {
                    $validation_errors[] = "Cannot create directory: {$path}";
                }
            }

            // Check if writable
            if (!is_writable($path)) {
                $validation_errors[] = "Path not writable: {$path}";
            }
        }

        // Validate secondary paths
        if (isset($config['secondary']) && is_array($config['secondary'])) {
            foreach ($config['secondary'] as $secondary_path) {
                if (!file_exists($secondary_path)) {
                    if (!mkdir($secondary_path, 0750, true)) {
                        $validation_errors[] = "Cannot create directory: {$secondary_path}";
                    }
                }

                if (!is_writable($secondary_path)) {
                    $validation_errors[] = "Path not writable: {$secondary_path}";
                }
            }
        }
    }

    if (!empty($validation_errors)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Path validation failed',
            'validation_errors' => $validation_errors
        ]);
        exit;
    }

    // Save configuration
    $config = [
        'version' => '1.0',
        'updated_at' => date('Y-m-d H:i:s'),
        'updated_by' => $_SESSION['admin_username'],
        'storage_locations' => $data['storage_locations'],
        'drive_priorities' => $data['drive_priorities'] ?? [],
        'notifications' => $data['notifications'] ?? [
            'notify_on_full' => true,
            'notify_threshold' => 90
        ]
    ];

    file_put_contents($config_file, json_encode($config, JSON_PRETTY_PRINT));
    chmod($config_file, 0600);

    echo json_encode([
        'success' => true,
        'message' => 'Storage configuration updated',
        'config' => $config
    ]);
    exit;
}

/**
 * List available drives and mount points
 */
if ($action === 'list_drives') {
    $drives = [];

    // Get mounted filesystems
    exec('df -h --output=source,target,size,used,avail,pcent 2>/dev/null', $output, $return_code);

    if ($return_code === 0 && count($output) > 1) {
        // Skip header
        array_shift($output);

        foreach ($output as $line) {
            $parts = preg_split('/\s+/', trim($line));

            if (count($parts) >= 6) {
                $filesystem = $parts[0];
                $mount_point = $parts[1];
                $size = $parts[2];
                $used = $parts[3];
                $available = $parts[4];
                $use_percent = rtrim($parts[5], '%');

                // Skip special filesystems
                if (strpos($filesystem, '/dev/') === 0 || $mount_point === '/' || strpos($mount_point, '/home') === 0 || strpos($mount_point, '/mnt') === 0) {
                    $drives[] = [
                        'filesystem' => $filesystem,
                        'mount_point' => $mount_point,
                        'size' => $size,
                        'used' => $used,
                        'available' => $available,
                        'use_percent' => (int)$use_percent,
                        'writable' => is_writable($mount_point)
                    ];
                }
            }
        }
    }

    // Add common paths that might exist
    $common_paths = [
        '/home/flexpbxuser/backup',
        '/home/flexpbxuser/config',
        '/var/spool/asterisk',
        '/var/log/asterisk',
        '/tmp'
    ];

    foreach ($common_paths as $path) {
        if (file_exists($path)) {
            $disk_info = disk_free_space($path);
            $disk_total = disk_total_space($path);

            $drives[] = [
                'filesystem' => 'N/A',
                'mount_point' => $path,
                'size' => formatBytes($disk_total),
                'used' => formatBytes($disk_total - $disk_info),
                'available' => formatBytes($disk_info),
                'use_percent' => round((($disk_total - $disk_info) / $disk_total) * 100),
                'writable' => is_writable($path),
                'is_common_path' => true
            ];
        }
    }

    // Remove duplicates based on mount_point
    $unique_drives = [];
    $seen = [];

    foreach ($drives as $drive) {
        if (!in_array($drive['mount_point'], $seen)) {
            $unique_drives[] = $drive;
            $seen[] = $drive['mount_point'];
        }
    }

    echo json_encode([
        'success' => true,
        'drives' => $unique_drives,
        'total_drives' => count($unique_drives)
    ]);
    exit;
}

/**
 * Test if a path is writable
 */
if ($action === 'test_path' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $path = $data['path'] ?? '';

    if (empty($path)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Path required']);
        exit;
    }

    $exists = file_exists($path);
    $writable = false;
    $can_create = false;
    $disk_space = null;

    if ($exists) {
        $writable = is_writable($path);

        $disk_free = disk_free_space($path);
        $disk_total = disk_total_space($path);

        $disk_space = [
            'free' => formatBytes($disk_free),
            'free_bytes' => $disk_free,
            'total' => formatBytes($disk_total),
            'total_bytes' => $disk_total,
            'use_percent' => round((($disk_total - $disk_free) / $disk_total) * 100)
        ];
    } else {
        // Try to create the directory
        $can_create = @mkdir($path, 0750, true);

        if ($can_create) {
            $writable = is_writable($path);

            $disk_free = disk_free_space($path);
            $disk_total = disk_total_space($path);

            $disk_space = [
                'free' => formatBytes($disk_free),
                'free_bytes' => $disk_free,
                'total' => formatBytes($disk_total),
                'total_bytes' => $disk_total,
                'use_percent' => round((($disk_total - $disk_free) / $disk_total) * 100)
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'path' => $path,
        'exists' => $exists,
        'writable' => $writable,
        'can_create' => $can_create,
        'disk_space' => $disk_space,
        'status' => $writable ? 'OK' : ($can_create ? 'Created' : 'Error')
    ]);
    exit;
}

/**
 * Format bytes to human readable
 */
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];

    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }

    return round($bytes, $precision) . ' ' . $units[$i];
}

http_response_code(400);
echo json_encode(['success' => false, 'error' => 'Invalid action']);
