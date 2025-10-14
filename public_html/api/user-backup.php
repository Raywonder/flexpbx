<?php
/**
 * FlexPBX User Backup API
 * Allows users to backup and restore their own data with quota limits
 *
 * Actions:
 * - create: Create new backup of user's data
 * - list: List user's backups
 * - download: Download a backup
 * - delete: Delete a backup
 * - restore: Restore from a backup
 * - get_quota: Get user's backup quota and usage
 */

session_start();
header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$extension = $_SESSION['user_extension'] ?? '';
$username = $_SESSION['user_username'] ?? $extension;

// Configuration
$user_backups_dir = "/home/flexpbxuser/user_backups/$extension";
$user_file = "/home/flexpbxuser/users/$extension.json";

// Default quota (can be overridden by admin)
$DEFAULT_QUOTA = [
    'max_backups' => 5,
    'max_total_size_mb' => 100,
    'max_backup_size_mb' => 50
];

// Load user data
if (!file_exists($user_file)) {
    echo json_encode(['success' => false, 'error' => 'User not found']);
    exit;
}

$user_data = json_decode(file_get_contents($user_file), true);

// Get user's backup quota (from user data or use defaults)
$quota = $user_data['backup_quota'] ?? $DEFAULT_QUOTA;

/**
 * Calculate current backup usage
 */
function get_backup_usage($backups_dir) {
    if (!is_dir($backups_dir)) {
        return ['count' => 0, 'total_size_bytes' => 0, 'total_size_mb' => 0];
    }

    $backups = glob("$backups_dir/*.json");
    $total_size = 0;

    foreach ($backups as $backup) {
        if (file_exists($backup)) {
            $total_size += filesize($backup);
        }
    }

    return [
        'count' => count($backups),
        'total_size_bytes' => $total_size,
        'total_size_mb' => round($total_size / (1024 * 1024), 2)
    ];
}

/**
 * Create user backup
 */
function create_user_backup($extension, $user_data, $backups_dir, $quota) {
    // Check quota
    $usage = get_backup_usage($backups_dir);

    if ($usage['count'] >= $quota['max_backups']) {
        return ['success' => false, 'error' => "Backup limit reached ({$quota['max_backups']} backups). Delete old backups or contact admin for more quota."];
    }

    if ($usage['total_size_mb'] >= $quota['max_total_size_mb']) {
        return ['success' => false, 'error' => "Storage limit reached ({$quota['max_total_size_mb']} MB). Delete old backups or contact admin for more quota."];
    }

    // Create backups directory if it doesn't exist
    if (!is_dir($backups_dir)) {
        mkdir($backups_dir, 0750, true);
        chmod($backups_dir, 0750);
    }

    // Create backup
    $timestamp = date('Y-m-d_H-i-s');
    $backup_file = "$backups_dir/user_{$extension}_{$timestamp}.json";

    // Backup data
    $backup_data = [
        'version' => '1.0',
        'backup_type' => 'user_data',
        'created' => date('Y-m-d H:i:s'),
        'extension' => $extension,
        'username' => $user_data['username'] ?? '',
        'user_data' => $user_data,
        'voicemail_dir' => "/var/spool/asterisk/voicemail/flexpbx/$extension"
    ];

    // Check if voicemail exists and include count
    $voicemail_dir = "/var/spool/asterisk/voicemail/flexpbx/$extension";
    if (is_dir($voicemail_dir)) {
        $vm_count = count(glob("$voicemail_dir/*/msg*.{wav,WAV,gsm}", GLOB_BRACE));
        $backup_data['voicemail_message_count'] = $vm_count;
    }

    file_put_contents($backup_file, json_encode($backup_data, JSON_PRETTY_PRINT));
    chmod($backup_file, 0640);

    $size_mb = round(filesize($backup_file) / (1024 * 1024), 2);

    return [
        'success' => true,
        'backup_file' => basename($backup_file),
        'created' => date('Y-m-d H:i:s'),
        'size_mb' => $size_mb
    ];
}

/**
 * List user's backups
 */
function list_user_backups($backups_dir) {
    if (!is_dir($backups_dir)) {
        return ['success' => true, 'backups' => []];
    }

    $backups = glob("$backups_dir/*.json");
    $backup_list = [];

    foreach ($backups as $backup) {
        $data = json_decode(file_get_contents($backup), true);
        $backup_list[] = [
            'filename' => basename($backup),
            'created' => $data['created'] ?? '',
            'size_mb' => round(filesize($backup) / (1024 * 1024), 2),
            'voicemail_count' => $data['voicemail_message_count'] ?? 0
        ];
    }

    // Sort by newest first
    usort($backup_list, function($a, $b) {
        return strtotime($b['created']) - strtotime($a['created']);
    });

    return ['success' => true, 'backups' => $backup_list];
}

/**
 * Delete a backup
 */
function delete_user_backup($backups_dir, $filename) {
    // Sanitize filename
    $filename = basename($filename);
    $backup_file = "$backups_dir/$filename";

    if (!file_exists($backup_file)) {
        return ['success' => false, 'error' => 'Backup not found'];
    }

    if (!preg_match('/^user_\d+_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.json$/', $filename)) {
        return ['success' => false, 'error' => 'Invalid backup filename'];
    }

    unlink($backup_file);

    return ['success' => true, 'message' => 'Backup deleted'];
}

/**
 * Restore from backup
 */
function restore_user_backup($backups_dir, $filename, $user_file) {
    // Sanitize filename
    $filename = basename($filename);
    $backup_file = "$backups_dir/$filename";

    if (!file_exists($backup_file)) {
        return ['success' => false, 'error' => 'Backup not found'];
    }

    $backup_data = json_decode(file_get_contents($backup_file), true);

    if (!isset($backup_data['user_data'])) {
        return ['success' => false, 'error' => 'Invalid backup format'];
    }

    // Restore user data
    $restored_data = $backup_data['user_data'];

    // Keep current password if not in backup (security)
    $current_data = json_decode(file_get_contents($user_file), true);
    if (!isset($restored_data['password'])) {
        $restored_data['password'] = $current_data['password'];
    }

    // Update restore timestamp
    $restored_data['restored_from_backup'] = $backup_data['created'];
    $restored_data['restored_at'] = date('Y-m-d H:i:s');

    file_put_contents($user_file, json_encode($restored_data, JSON_PRETTY_PRINT));

    return [
        'success' => true,
        'message' => 'Data restored from backup',
        'backup_date' => $backup_data['created']
    ];
}

// Handle request
$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'create':
        $result = create_user_backup($extension, $user_data, $user_backups_dir, $quota);
        echo json_encode($result);
        break;

    case 'list':
        $result = list_user_backups($user_backups_dir);
        echo json_encode($result);
        break;

    case 'get_quota':
        $usage = get_backup_usage($user_backups_dir);
        echo json_encode([
            'success' => true,
            'quota' => $quota,
            'usage' => $usage,
            'remaining_backups' => $quota['max_backups'] - $usage['count'],
            'remaining_mb' => $quota['max_total_size_mb'] - $usage['total_size_mb']
        ]);
        break;

    case 'delete':
        $filename = $_REQUEST['filename'] ?? '';
        if (empty($filename)) {
            echo json_encode(['success' => false, 'error' => 'Filename required']);
            break;
        }
        $result = delete_user_backup($user_backups_dir, $filename);
        echo json_encode($result);
        break;

    case 'download':
        $filename = $_REQUEST['filename'] ?? '';
        if (empty($filename)) {
            echo json_encode(['success' => false, 'error' => 'Filename required']);
            break;
        }

        $filename = basename($filename);
        $backup_file = "$user_backups_dir/$filename";

        if (!file_exists($backup_file)) {
            echo json_encode(['success' => false, 'error' => 'Backup not found']);
            break;
        }

        // Send file for download
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($backup_file));
        readfile($backup_file);
        exit;

    case 'restore':
        $filename = $_REQUEST['filename'] ?? '';
        if (empty($filename)) {
            echo json_encode(['success' => false, 'error' => 'Filename required']);
            break;
        }
        $result = restore_user_backup($user_backups_dir, $filename, $user_file);
        echo json_encode($result);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}
