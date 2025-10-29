<?php
/**
 * FlexPBX System Management API
 * System operations, health checks, and reload functions
 * Created: October 16, 2025
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Include HubNode Monitor integration
require_once __DIR__ . '/lib/hubnode_monitor.php';

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

    case 'active_calls':
        handleActiveCalls();
        break;

    case 'reload':
        handleReload($postData);
        break;

    case 'restart':
        handleRestart($postData);
        break;

    case 'status':
        handleStatus();
        break;

    case 'health':
        handleHealth();
        break;

    case 'backup_list':
        handleBackupList();
        break;

    case 'backup_create':
        handleBackupCreate($postData);
        break;

    case 'backup_restore':
        handleBackupRestore($postData);
        break;

    case 'backup_delete':
        handleBackupDelete($postData);
        break;

    case 'backup_download':
        handleBackupDownload();
        break;

    case 'drives':
        handleDrives();
        break;

    case 'backup_status':
        handleBackupStatus();
        break;

    case 'push_notification':
        handlePushNotification($postData);
        break;

    case 'process_backup_queue':
        handleProcessBackupQueue($postData);
        break;

    default:
        respond(false, 'Invalid path', null, 404);
        break;
}

/**
 * API Information
 */
function handleInfo() {
    respond(true, 'FlexPBX System Management API', [
        'version' => '1.0',
        'endpoints' => [
            'active_calls' => 'Get count of active calls',
            'reload' => 'Reload Asterisk configuration',
            'restart' => 'Restart Asterisk service',
            'status' => 'Get system status',
            'health' => 'System health check'
        ]
    ]);
}

/**
 * Get active calls count
 */
function handleActiveCalls() {
    exec('sudo -u asterisk /usr/sbin/asterisk -rx "core show channels" 2>&1', $output, $return_code);

    $activeCalls = 0;
    $channels = [];

    foreach ($output as $line) {
        // Look for active calls line like "2 active channels"
        if (preg_match('/(\d+)\s+active\s+channel/i', $line, $matches)) {
            $activeCalls = (int)$matches[1];
        }

        // Parse individual channels
        if (preg_match('/^(PJSIP\/\S+)\s+/', $line, $matches)) {
            $channels[] = $matches[1];
        }
    }

    respond(true, 'Active calls retrieved', [
        'active_calls' => $activeCalls,
        'channels' => $channels,
        'has_active_calls' => $activeCalls > 0
    ]);
}

/**
 * Reload Asterisk configuration
 */
function handleReload($data) {
    $module = $data['module'] ?? 'all';
    $force = $data['force'] ?? false;
    $waitForIdle = $data['wait_for_idle'] ?? true;

    // Check for active calls unless forced
    if (!$force && $waitForIdle) {
        exec('sudo -u asterisk /usr/sbin/asterisk -rx "core show channels" 2>&1', $output);

        foreach ($output as $line) {
            if (preg_match('/(\d+)\s+active\s+channel/i', $line, $matches)) {
                $activeCalls = (int)$matches[1];

                if ($activeCalls > 0) {
                    respond(false, 'Cannot reload: active calls in progress', [
                        'active_calls' => $activeCalls,
                        'suggestion' => 'Wait for calls to end or use force=true'
                    ], 409); // 409 Conflict
                    return;
                }
            }
        }
    }

    // Perform reload based on module
    $reloadCommands = [
        'all' => 'core reload',
        'pjsip' => 'pjsip reload',
        'dialplan' => 'dialplan reload',
        'voicemail' => 'voicemail reload',
        'moh' => 'moh reload',
        'queues' => 'queue reload',
        'sip' => 'sip reload'
    ];

    if (!isset($reloadCommands[$module])) {
        respond(false, 'Invalid module name', [
            'valid_modules' => array_keys($reloadCommands)
        ]);
        return;
    }

    $command = $reloadCommands[$module];
    exec('sudo -u asterisk /usr/sbin/asterisk -rx "' . $command . '" 2>&1', $output, $return_code);

    if ($return_code === 0) {
        respond(true, ucfirst($module) . ' configuration reloaded successfully', [
            'module' => $module,
            'output' => implode("\n", $output)
        ]);
    } else {
        respond(false, 'Reload failed', [
            'module' => $module,
            'output' => implode("\n", $output),
            'return_code' => $return_code
        ], 500);
    }
}

/**
 * Restart Asterisk service
 */
function handleRestart($data) {
    $force = $data['force'] ?? false;

    // Check for active calls unless forced
    if (!$force) {
        exec('sudo -u asterisk /usr/sbin/asterisk -rx "core show channels" 2>&1', $output);

        foreach ($output as $line) {
            if (preg_match('/(\d+)\s+active\s+channel/i', $line, $matches)) {
                $activeCalls = (int)$matches[1];

                if ($activeCalls > 0) {
                    respond(false, 'Cannot restart: active calls in progress', [
                        'active_calls' => $activeCalls,
                        'warning' => 'Restart will disconnect all active calls',
                        'suggestion' => 'Wait for calls to end or use force=true'
                    ], 409);
                    return;
                }
            }
        }
    }

    // Perform graceful restart
    exec('systemctl restart asterisk 2>&1', $output, $return_code);

    // Wait for Asterisk to come back up
    sleep(3);

    // Check if Asterisk is running
    exec('systemctl is-active asterisk 2>&1', $statusOutput);
    $isRunning = (trim($statusOutput[0]) === 'active');

    if ($isRunning) {
        respond(true, 'Asterisk restarted successfully', [
            'status' => 'active',
            'output' => implode("\n", $output)
        ]);
    } else {
        respond(false, 'Asterisk restart failed or not running', [
            'status' => $statusOutput[0] ?? 'unknown',
            'output' => implode("\n", $output)
        ], 500);
    }
}

/**
 * Get system status
 */
function handleStatus() {
    // Asterisk status
    exec('systemctl is-active asterisk 2>&1', $asteriskStatus);
    exec('uptime -p 2>&1', $uptime);

    // Get Asterisk uptime
    exec('sudo -u asterisk /usr/sbin/asterisk -rx "core show uptime" 2>&1', $asteriskUptime);

    // Get active calls
    exec('sudo -u asterisk /usr/sbin/asterisk -rx "core show channels concise" 2>&1 | wc -l', $callCount);

    // Get registered endpoints
    exec('sudo -u asterisk /usr/sbin/asterisk -rx "pjsip show endpoints" 2>&1 | grep -c "Avail"', $registeredEndpoints);

    // Get trunk status
    exec('sudo -u asterisk /usr/sbin/asterisk -rx "pjsip show registrations" 2>&1 | grep -c "Registered"', $registeredTrunks);

    respond(true, 'System status retrieved', [
        'asterisk' => [
            'status' => trim($asteriskStatus[0]),
            'is_running' => trim($asteriskStatus[0]) === 'active',
            'uptime' => implode("\n", $asteriskUptime)
        ],
        'system' => [
            'uptime' => trim($uptime[0])
        ],
        'telephony' => [
            'active_calls' => max(0, (int)trim($callCount[0]) - 1), // Subtract header line
            'registered_endpoints' => (int)trim($registeredEndpoints[0]),
            'registered_trunks' => (int)trim($registeredTrunks[0])
        ]
    ]);
}

/**
 * System health check
 */
function handleHealth() {
    $health = [
        'status' => 'healthy',
        'checks' => [],
        'timestamp' => date('c')
    ];

    // Check Asterisk service
    exec('systemctl is-active asterisk 2>&1', $asteriskStatus);
    $asteriskRunning = (trim($asteriskStatus[0]) === 'active');

    $health['checks']['asterisk_service'] = [
        'status' => $asteriskRunning ? 'pass' : 'fail',
        'message' => $asteriskRunning ? 'Asterisk is running' : 'Asterisk is not running'
    ];

    // Check disk space
    exec('df -h /var/spool/asterisk | tail -1 | awk \'{print $5}\' | sed \'s/%//\'', $diskUsage);
    $diskPercent = (int)trim($diskUsage[0]);

    $health['checks']['disk_space'] = [
        'status' => $diskPercent < 90 ? 'pass' : 'warn',
        'message' => "Disk usage: {$diskPercent}%",
        'value' => $diskPercent
    ];

    // Check if PJSIP is loaded
    exec('sudo -u asterisk /usr/sbin/asterisk -rx "pjsip show endpoints" 2>&1 | head -1', $pjsipCheck);
    $pjsipLoaded = !preg_match('/No such command/i', $pjsipCheck[0]);

    $health['checks']['pjsip_module'] = [
        'status' => $pjsipLoaded ? 'pass' : 'fail',
        'message' => $pjsipLoaded ? 'PJSIP module loaded' : 'PJSIP module not loaded'
    ];

    // Overall status
    $allPassed = true;
    foreach ($health['checks'] as $check) {
        if ($check['status'] === 'fail') {
            $allPassed = false;
            $health['status'] = 'unhealthy';
            break;
        } elseif ($check['status'] === 'warn' && $health['status'] === 'healthy') {
            $health['status'] = 'degraded';
        }
    }

    $httpCode = $health['status'] === 'healthy' ? 200 : ($health['status'] === 'degraded' ? 200 : 503);

    respond(true, 'Health check complete', $health, $httpCode);
}

/**
 * List all available backups
 */
function handleBackupList() {
    $backupLocations = [
        '/mnt/backup/flexpbx-backups/flx',
        '/mnt/backup/flexpbx-backups/flxx',
        '/mnt/backup/flexpbx-backups/full',
        '/home/flexpbxuser/public_html/uploads/backups/system',
        '/home/flexpbxuser/public_html/uploads/backups/api'
    ];

    $backups = [];

    foreach ($backupLocations as $location) {
        if (!is_dir($location)) {
            continue;
        }

        $files = glob("$location/*");
        foreach ($files as $file) {
            if (is_file($file)) {
                $backups[] = [
                    'name' => basename($file),
                    'path' => $file,
                    'location' => $location,
                    'size' => filesize($file),
                    'size_formatted' => formatBytes(filesize($file)),
                    'type' => detectBackupType($file),
                    'date' => date('Y-m-d H:i:s', filemtime($file)),
                    'timestamp' => filemtime($file)
                ];
            }
        }
    }

    // Sort by timestamp (newest first)
    usort($backups, function($a, $b) {
        return $b['timestamp'] - $a['timestamp'];
    });

    respond(true, 'Backups retrieved', [
        'backups' => $backups,
        'count' => count($backups)
    ]);
}

/**
 * Create a new backup
 */
function handleBackupCreate($data) {
    $type = $data['type'] ?? 'full';  // full, flx, flxx
    $compress = $data['compress'] ?? true;

    // Create request ID
    $requestId = time() . '_' . rand(1000, 9999);

    // Create queue directory
    $queueDir = '/home/flexpbxuser/.backup-queue';
    @mkdir($queueDir, 0755, true);

    // Create request file
    $requestFile = $queueDir . '/' . $requestId . '.json';
    $requestData = [
        'id' => $requestId,
        'type' => $type,
        'compress' => $compress,
        'timestamp' => date('Y-m-d H:i:s'),
        'user' => $_SERVER['REMOTE_USER'] ?? 'api'
    ];

    file_put_contents($requestFile, json_encode($requestData, JSON_PRETTY_PRINT));

    // Log to HubNode monitor
    HubNodeMonitor::logBackupEvent('queued', $type, true, [
        'request_id' => $requestId,
        'compressed' => $compress
    ]);

    respond(true, 'Backup request queued', [
        'request_id' => $requestId,
        'type' => $type,
        'compressed' => $compress,
        'status_url' => '/api/system.php?path=backup_status&request_id=' . $requestId,
        'message' => 'Backup will be processed by cron. Check status with the status_url.'
    ]);
}

/**
 * Restore from backup
 */
function handleBackupRestore($data) {
    $backupFile = $data['backup_file'] ?? '';

    if (empty($backupFile) || !file_exists($backupFile)) {
        respond(false, 'Backup file not found', null, 404);
    }

    // Verify file is in backup directory (security check)
    $allowedPaths = [
        '/mnt/backup/flexpbx-backups/',
        '/home/flexpbxuser/public_html/uploads/backups/'
    ];

    $isAllowed = false;
    foreach ($allowedPaths as $allowedPath) {
        if (strpos(realpath($backupFile), $allowedPath) === 0) {
            $isAllowed = true;
            break;
        }
    }

    if (!$isAllowed) {
        respond(false, 'Cannot restore from files outside backup directories', null, 403);
    }

    // Use flexpbx-restore script for all backup types
    $command = "sudo -u flexpbxuser /usr/local/bin/flexpbx-restore " . escapeshellarg($backupFile) . " 2>&1";

    // For tar.gz, need root permissions
    $type = detectBackupType($backupFile);
    if ($type === 'tar.gz') {
        $command = "sudo /usr/local/bin/flexpbx-restore " . escapeshellarg($backupFile) . " 2>&1";
    }

    exec("timeout 300 " . $command, $output, $returnCode);

    $success = $returnCode === 0;

    // Log to HubNode monitor
    HubNodeMonitor::logBackupEvent('restore', $type, $success, [
        'backup_file' => basename($backupFile)
    ]);

    respond($success, $success ? 'Backup restored successfully' : 'Restore failed', [
        'output' => implode("\n", $output),
        'backup_file' => basename($backupFile),
        'type' => $type,
        'return_code' => $returnCode
    ]);
}

/**
 * Delete a backup
 */
function handleBackupDelete($data) {
    $backupFile = $data['backup_file'] ?? '';

    if (empty($backupFile) || !file_exists($backupFile)) {
        respond(false, 'Backup file not found', null, 404);
    }

    // Verify file is in backup directory (security check)
    $allowedPaths = [
        '/mnt/backup/flexpbx-backups/',
        '/home/flexpbxuser/public_html/uploads/backups/'
    ];

    $isAllowed = false;
    foreach ($allowedPaths as $allowedPath) {
        if (strpos(realpath($backupFile), $allowedPath) === 0) {
            $isAllowed = true;
            break;
        }
    }

    if (!$isAllowed) {
        respond(false, 'Cannot delete files outside backup directories', null, 403);
    }

    $success = unlink($backupFile);

    // Log to HubNode monitor
    HubNodeMonitor::logBackupEvent('delete', 'backup', $success, [
        'backup_file' => basename($backupFile)
    ]);

    respond($success, $success ? 'Backup deleted' : 'Delete failed', [
        'backup_file' => basename($backupFile)
    ]);
}

/**
 * Download a backup file
 */
function handleBackupDownload() {
    $backupFile = $_GET['file'] ?? '';

    if (empty($backupFile) || !file_exists($backupFile)) {
        respond(false, 'Backup file not found', null, 404);
    }

    // Verify file is in backup directory (security check)
    $allowedPaths = [
        '/mnt/backup/flexpbx-backups/',
        '/home/flexpbxuser/public_html/uploads/backups/'
    ];

    $isAllowed = false;
    foreach ($allowedPaths as $allowedPath) {
        if (strpos(realpath($backupFile), $allowedPath) === 0) {
            $isAllowed = true;
            break;
        }
    }

    if (!$isAllowed) {
        respond(false, 'Cannot download files outside backup directories', null, 403);
    }

    // Send file
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($backupFile) . '"');
    header('Content-Length: ' . filesize($backupFile));
    readfile($backupFile);
    exit;
}

/**
 * Check backup queue status
 */
function handleBackupStatus() {
    $requestId = $_GET['request_id'] ?? '';

    if (empty($requestId)) {
        respond(false, 'Missing request_id parameter', null, 400);
    }

    $queueDir = '/home/flexpbxuser/.backup-queue';
    $statusDir = '/home/flexpbxuser/.backup-status';

    $requestFile = $queueDir . '/' . $requestId . '.json';
    $statusFile = $statusDir . '/' . $requestId . '.json';

    // Check if request is still queued
    if (file_exists($requestFile)) {
        $requestData = json_decode(file_get_contents($requestFile), true);
        respond(true, 'Backup request is queued', [
            'status' => 'queued',
            'request_id' => $requestId,
            'type' => $requestData['type'] ?? 'unknown',
            'queued_at' => $requestData['timestamp'] ?? 'unknown'
        ]);
        return;
    }

    // Check if status file exists
    if (file_exists($statusFile)) {
        $statusData = json_decode(file_get_contents($statusFile), true);
        respond(true, 'Backup request processed', $statusData);
        return;
    }

    // Request not found
    respond(false, 'Backup request not found', [
        'request_id' => $requestId,
        'message' => 'Request may have expired (status files are kept for 24 hours)'
    ], 404);
}

/**
 * Send push notification
 * Sends notifications via Discord webhook and HubNode event log
 */
function handlePushNotification($data) {
    // Validate required fields
    $target = $data['target'] ?? '';
    $message = $data['message'] ?? '';
    $priority = $data['priority'] ?? 'normal';
    $channels = $data['channels'] ?? ['discord', 'hubnode'];

    if (empty($target)) {
        respond(false, 'Missing target parameter', null, 400);
    }

    if (empty($message)) {
        respond(false, 'Missing message parameter', null, 400);
    }

    // Validate target format
    $targetType = 'unknown';
    $targetValue = $target;

    if ($target === 'global') {
        $targetType = 'global';
        $targetValue = 'all users';
    } elseif ($target === 'discord') {
        $targetType = 'discord_only';
        $targetValue = 'Discord channel';
    } elseif (strpos($target, 'extension:') === 0) {
        $targetType = 'extension';
        $targetValue = substr($target, 10); // Remove 'extension:' prefix
    }

    $results = [];

    // Send to HubNode event log (persistent record)
    if (in_array('hubnode', $channels)) {
        $success = HubNodeMonitor::logEvent('notification', 'push', [
            'target' => $target,
            'target_type' => $targetType,
            'target_value' => $targetValue,
            'message' => $message,
            'priority' => $priority
        ], true);

        $results['hubnode'] = $success ? 'logged' : 'failed';
    }

    // Send to Discord webhook (instant notification)
    if (in_array('discord', $channels)) {
        // Discord webhook URL (should match HubNode monitor)
        $discordWebhook = 'https://discord.com/api/webhooks/1391179168913555568/5hdSwsxtv-KyxyEVaXPu7jtHbKsPN4pRwg3y3KR_Lqai5YtRzZ9ynlKhXuz8HBqdXRmm';

        // Build Discord embed
        $priorityColors = [
            'normal' => 0x3b82f6,  // Blue
            'high' => 0xf59e0b,    // Orange
            'urgent' => 0xef4444   // Red
        ];

        $priorityIcons = [
            'normal' => '📢',
            'high' => '⚠️',
            'urgent' => '🚨'
        ];

        $icon = $priorityIcons[$priority] ?? '📢';
        $color = $priorityColors[$priority] ?? 0x3b82f6;

        $embed = [
            'embeds' => [[
                'title' => $icon . ' FlexPBX Notification',
                'description' => $message,
                'color' => $color,
                'fields' => [
                    [
                        'name' => 'Target',
                        'value' => $targetValue,
                        'inline' => true
                    ],
                    [
                        'name' => 'Priority',
                        'value' => ucfirst($priority),
                        'inline' => true
                    ]
                ],
                'footer' => [
                    'text' => 'FlexPBX Push Notification',
                    'icon_url' => 'https://flexpbx.devinecreations.net/favicon.ico'
                ],
                'timestamp' => date('c')
            ]]
        ];

        // Send to Discord
        $ch = curl_init($discordWebhook);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($embed),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $results['discord'] = ($httpCode === 200 || $httpCode === 204) ? 'sent' : 'failed';
    }

    // Send to Mastodon (public announcement or DM)
    if (in_array('mastodon', $channels)) {
        $mastodonVisibility = $data['mastodon_visibility'] ?? 'unlisted';

        // Call HubNode service monitor's Mastodon endpoint
        $mastodonData = [
            'event_type' => 'notification',
            'action' => 'push',
            'success' => true,
            'data' => [
                'message' => $message,
                'target' => $targetValue,
                'priority' => $priority
            ],
            'visibility' => $mastodonVisibility
        ];

        $ch = curl_init('http://localhost:5003/send_mastodon');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($mastodonData),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $responseData = json_decode($response, true);
            $results['mastodon'] = $responseData['success'] ?? false ? 'sent' : 'failed';
        } else {
            $results['mastodon'] = 'failed';
        }
    }

    respond(true, 'Notification sent', [
        'target' => $target,
        'target_type' => $targetType,
        'target_value' => $targetValue,
        'priority' => $priority,
        'channels' => $results,
        'message_preview' => substr($message, 0, 50) . (strlen($message) > 50 ? '...' : '')
    ]);
}

/**
 * List all detected drives
 */
function handleDrives() {
    exec('lsblk -b -o NAME,SIZE,FSTYPE,MOUNTPOINT,LABEL -J 2>&1', $output, $returnCode);

    $lsblkData = json_decode(implode("\n", $output), true);

    if (!$lsblkData) {
        respond(false, 'Failed to detect drives', null, 500);
    }

    $drives = [];

    foreach ($lsblkData['blockdevices'] as $device) {
        $drives[] = [
            'name' => $device['name'],
            'size' => $device['size'],
            'size_formatted' => formatBytes($device['size']),
            'fstype' => $device['fstype'] ?? 'none',
            'mountpoint' => $device['mountpoint'] ?? null,
            'label' => $device['label'] ?? ''
        ];

        // Include partitions
        if (isset($device['children'])) {
            foreach ($device['children'] as $partition) {
                $drives[] = [
                    'name' => $partition['name'],
                    'size' => $partition['size'],
                    'size_formatted' => formatBytes($partition['size']),
                    'fstype' => $partition['fstype'] ?? 'none',
                    'mountpoint' => $partition['mountpoint'] ?? null,
                    'label' => $partition['label'] ?? '',
                    'parent' => $device['name']
                ];
            }
        }
    }

    respond(true, 'Drives detected', [
        'drives' => $drives,
        'count' => count($drives)
    ]);
}

/**
 * Helper: Detect backup type from filename
 */
function detectBackupType($filename) {
    if (preg_match('/\.tar\.gz$/', $filename)) {
        return 'tar.gz';
    } elseif (preg_match('/\.flxx(\.gz)?$/', $filename)) {
        return 'flxx';
    } elseif (preg_match('/\.flx(\.gz)?$/', $filename)) {
        return 'flx';
    }
    return 'unknown';
}

/**
 * Helper: Format bytes to human readable
 */
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Process backup queue (fallback for systems without cron)
 */
function handleProcessBackupQueue($data) {
    $source = $data['source'] ?? 'api';

    // Call the backup queue processor script
    $scriptPath = '/home/flexpbxuser/public_html/scripts/process-backup-queue.php';

    if (!file_exists($scriptPath)) {
        respond(false, 'Backup processor script not found', null, 500);
        return;
    }

    // Execute the processor script
    exec("/usr/bin/php $scriptPath 2>&1", $output, $returnCode);

    $success = $returnCode === 0;

    // Parse output for processed count
    $processed = 0;
    foreach ($output as $line) {
        if (preg_match('/Processed (\d+) backup/', $line, $matches)) {
            $processed = (int)$matches[1];
        }
    }

    respond($success, $success ? 'Backup queue processed' : 'Processing failed', [
        'processed' => $processed,
        'source' => $source,
        'output' => implode("\n", array_slice($output, -5)) // Last 5 lines
    ]);
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
