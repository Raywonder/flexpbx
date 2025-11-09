<?php
/**
 * FlexPBX Log Management API
 * Manages Asterisk, Coturn, and system logs with AI training mode support
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/config.php';

// Verify API key or admin session
$api_key = $_GET['api_key'] ?? $_POST['api_key'] ?? '';
$is_api_auth = ($api_key === $config['api_key']);

session_start();
$is_admin = ($_SESSION['admin_logged_in'] ?? false);

if (!$is_api_auth && !$is_admin) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Admin authentication required']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'status';

try {
    switch ($action) {
        case 'status':
            getLogStatus();
            break;

        case 'get_settings':
            getLogSettings();
            break;

        case 'update_settings':
            updateLogSettings();
            break;

        case 'cleanup':
            cleanupLogs();
            break;

        case 'rotate':
            rotateLogs();
            break;

        case 'get_ai_training_status':
            getAITrainingStatus();
            break;

        case 'toggle_ai_training':
            toggleAITraining();
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
}

function getLogStatus() {
    $logs = [
        'asterisk' => [
            'path' => '/var/log/asterisk',
            'name' => 'Asterisk PBX',
            'type' => 'pbx'
        ],
        'coturn' => [
            'path' => '/var/log/coturn',
            'name' => 'STUN/TURN Server',
            'type' => 'voip'
        ],
        'mysql' => [
            'path' => '/var/log/mysql',
            'name' => 'MySQL/MariaDB',
            'type' => 'database'
        ],
        'system' => [
            'path' => '/var/log',
            'name' => 'System Logs',
            'type' => 'system'
        ]
    ];

    $status = [];
    foreach ($logs as $key => $log) {
        $size_mb = shell_exec("du -sm " . escapeshellarg($log['path']) . " 2>/dev/null | cut -f1");
        $size_mb = intval(trim($size_mb));
        $size_gb = round($size_mb / 1024, 2);

        $level = 'ok';
        if ($size_gb > 10) {
            $level = 'critical';
        } elseif ($size_gb > 5) {
            $level = 'warning';
        }

        $status[$key] = [
            'name' => $log['name'],
            'type' => $log['type'],
            'size_mb' => $size_mb,
            'size_gb' => $size_gb,
            'size_display' => $size_gb < 1 ? $size_mb . ' MB' : $size_gb . ' GB',
            'level' => $level,
            'path' => $log['path']
        ];
    }

    // Get AI training mode status
    $ai_training_file = '/home/flexpbxuser/config/ai-training-mode.json';
    $ai_training_enabled = false;
    if (file_exists($ai_training_file)) {
        $ai_config = json_decode(file_get_contents($ai_training_file), true);
        $ai_training_enabled = $ai_config['enabled'] ?? false;
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'logs' => $status,
            'ai_training_mode' => $ai_training_enabled,
            'timestamp' => date('c')
        ]
    ]);
}

function getLogSettings() {
    global $pdo;

    $stmt = $pdo->query("SELECT * FROM system_settings WHERE setting_key LIKE 'log_%'");
    $settings = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = json_decode($row['setting_value'], true);
    }

    echo json_encode([
        'success' => true,
        'settings' => $settings
    ]);
}

function updateLogSettings() {
    global $pdo;

    $settings = json_decode(file_get_contents('php://input'), true);

    foreach ($settings as $key => $value) {
        $stmt = $pdo->prepare("
            INSERT INTO system_settings (setting_key, setting_value, updated_at)
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()
        ");
        $json_value = json_encode($value);
        $stmt->execute([$key, $json_value, $json_value]);
    }

    echo json_encode(['success' => true, 'message' => 'Settings updated']);
}

function cleanupLogs() {
    $service = $_POST['service'] ?? 'all';
    $output = [];

    if ($service === 'all' || $service === 'asterisk') {
        exec('/usr/bin/sudo /usr/bin/logrotate -f /etc/logrotate.d/asterisk 2>&1', $out);
        $output['asterisk'] = implode("\n", $out);
    }

    if ($service === 'all' || $service === 'coturn') {
        exec('/usr/bin/sudo /usr/bin/logrotate -f /etc/logrotate.d/coturn 2>&1', $out);
        $output['coturn'] = implode("\n", $out);
    }

    // Run cleanup script
    exec('/usr/local/bin/cleanup-oversized-logs.sh 2>&1', $out);
    $output['cleanup'] = implode("\n", $out);

    echo json_encode([
        'success' => true,
        'message' => 'Log cleanup completed',
        'output' => $output
    ]);
}

function rotateLogs() {
    $service = $_POST['service'] ?? '';

    if (empty($service)) {
        throw new Exception('Service parameter required');
    }

    $command = '';
    if ($service === 'asterisk') {
        $command = 'asterisk -rx "logger reload"';
    } elseif ($service === 'coturn') {
        $command = 'systemctl reload coturn';
    }

    if ($command) {
        exec('/usr/bin/sudo ' . $command . ' 2>&1', $output);
        echo json_encode([
            'success' => true,
            'message' => 'Log rotation triggered',
            'output' => implode("\n", $output)
        ]);
    } else {
        throw new Exception('Invalid service');
    }
}

function getAITrainingStatus() {
    $config_file = '/home/flexpbxuser/config/ai-training-mode.json';

    if (file_exists($config_file)) {
        $config = json_decode(file_get_contents($config_file), true);
    } else {
        $config = [
            'enabled' => false,
            'services' => [],
            'retention_days' => 30,
            'trained_logs_path' => '/home/flexpbxuser/ai-training-data/processed'
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => $config
    ]);
}

function toggleAITraining() {
    $enabled = filter_var($_POST['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $services = $_POST['services'] ?? ['asterisk', 'coturn'];
    $retention_days = intval($_POST['retention_days'] ?? 30);

    $config = [
        'enabled' => $enabled,
        'services' => $services,
        'retention_days' => $retention_days,
        'trained_logs_path' => '/home/flexpbxuser/ai-training-data/processed',
        'updated_at' => date('c'),
        'updated_by' => $_SESSION['admin_username'] ?? 'system'
    ];

    $config_dir = '/home/flexpbxuser/config';
    if (!is_dir($config_dir)) {
        mkdir($config_dir, 0755, true);
    }

    file_put_contents($config_dir . '/ai-training-mode.json', json_encode($config, JSON_PRETTY_PRINT));

    // Update logrotate configs based on AI training mode
    if ($enabled) {
        // Extend retention for AI training
        updateLogrotateConfig('asterisk', $retention_days);
        updateLogrotateConfig('coturn', $retention_days);
    } else {
        // Reset to normal retention
        updateLogrotateConfig('asterisk', 7);
        updateLogrotateConfig('coturn', 3);
    }

    echo json_encode([
        'success' => true,
        'message' => 'AI training mode ' . ($enabled ? 'enabled' : 'disabled'),
        'config' => $config
    ]);
}

function updateLogrotateConfig($service, $retention_days) {
    // This would update the logrotate config files
    // Implementation depends on your system permissions
    $config_file = "/etc/logrotate.d/$service";
    // Add logic to update rotation config with new retention
}
