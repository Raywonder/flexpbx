<?php
/**
 * FlexPBX Backup API
 * REST API for backup operations
 *
 * @package FlexPBX
 */

header('Content-Type: application/json');

require_once dirname(__FILE__) . '/../modules/backup/FlexPBX_Backup.php';
require_once dirname(__FILE__) . '/../includes/auth.php';

// Require authentication
if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$backup = new FlexPBX_Backup();

// Get action from request
$action = $_REQUEST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// Handle JSON body for POST requests
if ($method === 'POST' && empty($_POST)) {
    $json = file_get_contents('php://input');
    $_POST = json_decode($json, true) ?? [];
    $action = $_POST['action'] ?? $action;
}

try {
    switch ($action) {
        // List all backups
        case 'list':
            $format = $_GET['format'] ?? null;
            $include_remote = filter_var($_GET['include_remote'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $backups = $backup->listBackups($format, $include_remote);
            echo json_encode(['success' => true, 'backups' => $backups]);
            break;

        // Get backup details
        case 'details':
            $path = $_GET['path'] ?? '';
            if (empty($path)) {
                throw new Exception('Backup path required');
            }
            $details = $backup->getBackupDetails($path);
            echo json_encode(['success' => true, 'details' => $details]);
            break;

        // Create new backup
        case 'create':
            $format = $_POST['format'] ?? 'flxx';
            $options = [
                'components' => $_POST['components'] ?? null,
                'upload_remote' => filter_var($_POST['upload_remote'] ?? false, FILTER_VALIDATE_BOOLEAN)
            ];

            $result = $backup->createBackup($format, $options);
            echo json_encode(['success' => $result['status'] ?? true, 'backup' => $result]);
            break;

        // Restore from backup
        case 'restore':
            $path = $_POST['path'] ?? '';
            if (empty($path)) {
                throw new Exception('Backup path required');
            }

            $options = [
                'components' => $_POST['components'] ?? null,
                'backup_existing' => filter_var($_POST['backup_existing'] ?? true, FILTER_VALIDATE_BOOLEAN)
            ];

            $result = $backup->restoreBackup($path, $options);
            echo json_encode(['success' => $result['status'], 'result' => $result]);
            break;

        // Delete backup
        case 'delete':
            $path = $_POST['path'] ?? '';
            if (empty($path)) {
                throw new Exception('Backup path required');
            }

            $result = $backup->deleteBackup($path);
            echo json_encode($result);
            break;

        // Download backup (returns file)
        case 'download':
            $path = $_GET['path'] ?? '';
            if (empty($path) || !file_exists($path)) {
                throw new Exception('Backup not found');
            }

            // Security check - ensure path is in backup directory
            $backup_dir = '/var/backups/flexpbx';
            if (strpos(realpath($path), realpath($backup_dir)) !== 0) {
                throw new Exception('Invalid backup path');
            }

            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($path) . '"');
            header('Content-Length: ' . filesize($path));
            readfile($path);
            exit;

        // Get storage statistics
        case 'stats':
            $stats = $backup->getStorageStats();
            echo json_encode(['success' => true, 'stats' => $stats]);
            break;

        // Schedule automated backup
        case 'schedule':
            $format = $_POST['format'] ?? 'flxx';
            $schedule = $_POST['schedule'] ?? 'daily';
            $options = [
                'upload_remote' => filter_var($_POST['upload_remote'] ?? false, FILTER_VALIDATE_BOOLEAN)
            ];

            $result = $backup->scheduleBackup($format, $schedule, $options);
            echo json_encode($result);
            break;

        // Download from remote storage
        case 'download_remote':
            $backup_id = $_POST['backup_id'] ?? '';
            $format = $_POST['format'] ?? null;

            if (empty($backup_id)) {
                throw new Exception('Backup ID required');
            }

            $result = $backup->downloadFromRemote($backup_id, $format);
            echo json_encode($result);
            break;

        // Get available components for backup
        case 'components':
            $config_file = dirname(__FILE__) . '/../config/backup-config.json';
            $config = json_decode(file_get_contents($config_file), true);

            $components = [];
            foreach ($config['backup_contents'] ?? [] as $key => $comp) {
                $components[] = [
                    'key' => $key,
                    'name' => $comp['description'] ?? $key,
                    'enabled' => $comp['enabled'] ?? false,
                    'required' => $comp['required'] ?? false,
                    'category' => in_array($key, ['asterisk_config', 'flexpbx_app', 'database', 'cdr']) ? 'config' : 'data',
                    'requires_plan' => $comp['requires_plan'] ?? null
                ];
            }

            echo json_encode(['success' => true, 'components' => $components]);
            break;

        // Activate free plan
        case 'activate_free_plan':
            $config_file = dirname(__FILE__) . '/../config/backup-config.json';
            $config = json_decode(file_get_contents($config_file), true);

            $config['storage_options']['remote_cloud']['enabled'] = true;
            $config['storage_options']['remote_cloud']['current_plan'] = 'free';
            $config['storage_options']['remote_cloud']['storage_limit'] = 5 * 1024 * 1024 * 1024; // 5GB

            file_put_contents($config_file, json_encode($config, JSON_PRETTY_PRINT));

            echo json_encode(['success' => true, 'message' => 'Free plan activated']);
            break;

        // Verify backup integrity
        case 'verify':
            $path = $_GET['path'] ?? '';
            if (empty($path) || !file_exists($path)) {
                throw new Exception('Backup not found');
            }

            // Extract and check manifest
            $details = $backup->getBackupDetails($path);
            $valid = !empty($details['backup_name']) && !empty($details['contents']);

            echo json_encode([
                'success' => true,
                'valid' => $valid,
                'details' => $details
            ]);
            break;

        default:
            throw new Exception('Invalid action');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
