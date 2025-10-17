<?php
/**
 * FlexPBX System API
 * System health, backups, version info, and monitoring
 */

require_once __DIR__ . '/auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$auth = checkAuth();
if (!$auth['authenticated']) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

switch ($path) {
    case 'status':
    case 'health':
        handleSystemHealth($method);
        break;

    case 'version':
    case 'info':
        handleSystemInfo($method);
        break;

    case 'backup':
        handleBackup($method);
        break;

    case 'restore':
        handleRestore($method);
        break;

    case 'logs':
        handleLogs($method);
        break;

    case 'services':
        handleServices($method);
        break;

    case 'resources':
        handleResources($method);
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found']);
        break;
}

/**
 * System health status
 */
function handleSystemHealth($method) {
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    $health = [
        'overall_status' => 'healthy',
        'timestamp' => date('c'),
        'services' => [],
        'resources' => [],
        'issues' => []
    ];

    // Check Asterisk
    exec('sudo systemctl is-active asterisk 2>/dev/null', $asterisk_status);
    $asterisk_active = (trim($asterisk_status[0] ?? '') === 'active');

    exec('sudo asterisk -rx "core show uptime" 2>/dev/null', $uptime_output);
    $uptime = '';
    foreach ($uptime_output as $line) {
        if (preg_match('/System uptime:\s+(.+)/', $line, $matches)) {
            $uptime = $matches[1];
        }
    }

    $health['services']['asterisk'] = [
        'status' => $asterisk_active ? 'running' : 'stopped',
        'uptime' => $uptime,
        'healthy' => $asterisk_active
    ];

    // Check coturn (STUN)
    exec('sudo systemctl is-active coturn 2>/dev/null', $coturn_status);
    $coturn_active = (trim($coturn_status[0] ?? '') === 'active');

    $health['services']['coturn'] = [
        'status' => $coturn_active ? 'running' : 'stopped',
        'healthy' => $coturn_active
    ];

    // Check fail2ban
    exec('sudo systemctl is-active fail2ban 2>/dev/null', $fail2ban_status);
    $fail2ban_active = (trim($fail2ban_status[0] ?? '') === 'active');

    $health['services']['fail2ban'] = [
        'status' => $fail2ban_active ? 'running' : 'stopped',
        'healthy' => $fail2ban_active
    ];

    // Check disk space
    exec('df -h / | tail -1', $df_output);
    if (preg_match('/(\d+)%/', $df_output[0], $matches)) {
        $disk_usage = (int)$matches[1];
        $health['resources']['disk'] = [
            'usage_percent' => $disk_usage,
            'healthy' => $disk_usage < 90
        ];

        if ($disk_usage >= 90) {
            $health['issues'][] = "Disk usage critical: {$disk_usage}%";
            $health['overall_status'] = 'warning';
        }
    }

    // Check memory
    exec('free | grep Mem', $mem_output);
    if (preg_match('/Mem:\s+(\d+)\s+(\d+)/', $mem_output[0], $matches)) {
        $mem_total = (int)$matches[1];
        $mem_used = (int)$matches[2];
        $mem_percent = round(($mem_used / $mem_total) * 100);

        $health['resources']['memory'] = [
            'total_kb' => $mem_total,
            'used_kb' => $mem_used,
            'usage_percent' => $mem_percent,
            'healthy' => $mem_percent < 90
        ];

        if ($mem_percent >= 90) {
            $health['issues'][] = "Memory usage high: {$mem_percent}%";
            $health['overall_status'] = 'warning';
        }
    }

    // Check load average
    exec('uptime', $uptime_cmd);
    if (preg_match('/load average:\s+([\d.]+)/', $uptime_cmd[0], $matches)) {
        $load = (float)$matches[1];
        $health['resources']['load_average'] = [
            '1min' => $load,
            'healthy' => $load < 10
        ];

        if ($load >= 10) {
            $health['issues'][] = "High load average: $load";
            $health['overall_status'] = 'warning';
        }
    }

    // Determine overall status
    if (!$asterisk_active) {
        $health['overall_status'] = 'critical';
        $health['issues'][] = 'Asterisk service not running';
    }

    echo json_encode([
        'success' => true,
        'health' => $health
    ]);
}

/**
 * System version and info
 */
function handleSystemInfo($method) {
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    $info = [
        'flexpbx' => [
            'version' => '1.0.0',
            'release_date' => '2025-10-16',
            'build' => 'stable'
        ],
        'asterisk' => [],
        'system' => []
    ];

    // Get Asterisk version
    exec('sudo asterisk -V 2>/dev/null', $asterisk_ver);
    if (!empty($asterisk_ver[0])) {
        $info['asterisk']['version'] = trim($asterisk_ver[0]);
    }

    // Get system info
    exec('uname -r', $kernel);
    $info['system']['kernel'] = trim($kernel[0] ?? '');

    exec('cat /etc/os-release | grep PRETTY_NAME', $os);
    if (!empty($os[0]) && preg_match('/PRETTY_NAME="(.+)"/', $os[0], $matches)) {
        $info['system']['os'] = $matches[1];
    }

    exec('hostname', $hostname);
    $info['system']['hostname'] = trim($hostname[0] ?? '');

    exec('hostname -I | awk \'{print $1}\'', $ip);
    $info['system']['ip'] = trim($ip[0] ?? '');

    // Get configured extensions count
    exec('sudo asterisk -rx "pjsip show endpoints" 2>/dev/null | grep -c "Endpoint:"', $ext_count);
    $info['statistics']['total_extensions'] = (int)($ext_count[0] ?? 0);

    // Get active calls
    exec('sudo asterisk -rx "core show channels" 2>/dev/null | grep "active call"', $calls);
    if (!empty($calls[0]) && preg_match('/(\d+)\s+active call/', $calls[0], $matches)) {
        $info['statistics']['active_calls'] = (int)$matches[1];
    } else {
        $info['statistics']['active_calls'] = 0;
    }

    echo json_encode([
        'success' => true,
        'info' => $info,
        'timestamp' => date('c')
    ]);
}

/**
 * Create system backup
 */
function handleBackup($method) {
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $backup_type = $data['type'] ?? 'config'; // 'config' or 'full'
    $backup_name = 'flexpbx_backup_' . date('Y-m-d_His');

    $backup_dir = '/home/flexpbxuser/backups';
    @mkdir($backup_dir, 0755, true);

    $files_to_backup = [
        '/etc/asterisk/pjsip.conf',
        '/etc/asterisk/extensions.conf',
        '/etc/asterisk/voicemail.conf',
        '/etc/asterisk/rtp.conf',
        '/home/flexpbxuser/public_html/admin',
        '/home/flexpbxuser/public_html/api'
    ];

    if ($backup_type === 'full') {
        $files_to_backup[] = '/var/lib/asterisk/sounds';
        $files_to_backup[] = '/var/spool/asterisk/voicemail';
    }

    $backup_file = "$backup_dir/$backup_name.tar.gz";

    // Create tar archive
    $files_str = implode(' ', $files_to_backup);
    exec("sudo tar -czf $backup_file $files_str 2>&1", $tar_output, $tar_ret);

    if ($tar_ret === 0) {
        exec("sudo chown flexpbxuser:flexpbxuser $backup_file");

        $backup_size = filesize($backup_file);

        echo json_encode([
            'success' => true,
            'message' => 'Backup created successfully',
            'backup' => [
                'file' => $backup_file,
                'name' => basename($backup_file),
                'size' => $backup_size,
                'size_human' => formatBytes($backup_size),
                'type' => $backup_type,
                'timestamp' => date('c')
            ]
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'error' => 'Backup failed',
            'output' => $tar_output
        ]);
    }
}

/**
 * Restore from backup
 */
function handleRestore($method) {
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $backup_file = $data['backup_file'] ?? '';

    if (!file_exists($backup_file)) {
        http_response_code(404);
        echo json_encode(['error' => 'Backup file not found']);
        return;
    }

    // Extract backup
    exec("sudo tar -xzf $backup_file -C / 2>&1", $output, $ret);

    if ($ret === 0) {
        // Reload Asterisk configurations
        exec('sudo asterisk -rx "core reload"');

        echo json_encode([
            'success' => true,
            'message' => 'Backup restored successfully',
            'timestamp' => date('c')
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'error' => 'Restore failed',
            'output' => $output
        ]);
    }
}

/**
 * Get system logs
 */
function handleLogs($method) {
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    $log_type = $_GET['type'] ?? 'asterisk';
    $lines = (int)($_GET['lines'] ?? 100);
    $lines = min($lines, 1000); // Max 1000 lines

    $log_file = match($log_type) {
        'asterisk' => '/var/log/asterisk/messages',
        'flexpbx' => '/var/log/flexpbx.log',
        'security' => '/var/log/flexpbx-security.log',
        'extensions' => '/var/log/flexpbx-extensions.log',
        default => '/var/log/asterisk/messages'
    };

    if (!file_exists($log_file)) {
        echo json_encode([
            'success' => true,
            'logs' => [],
            'message' => 'Log file not found or empty'
        ]);
        return;
    }

    exec("tail -$lines $log_file 2>/dev/null", $log_lines);

    echo json_encode([
        'success' => true,
        'log_type' => $log_type,
        'lines' => $log_lines,
        'total_lines' => count($log_lines),
        'timestamp' => date('c')
    ]);
}

/**
 * Get services status
 */
function handleServices($method) {
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    $services = [
        'asterisk' => getServiceStatus('asterisk'),
        'coturn' => getServiceStatus('coturn'),
        'fail2ban' => getServiceStatus('fail2ban'),
        'apache2' => getServiceStatus('apache2'),
        'mysql' => getServiceStatus('mysql'),
        'nginx' => getServiceStatus('nginx')
    ];

    echo json_encode([
        'success' => true,
        'services' => $services,
        'timestamp' => date('c')
    ]);
}

/**
 * Get resource usage
 */
function handleResources($method) {
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    $resources = [];

    // CPU usage
    exec('top -bn1 | grep "Cpu(s)"', $cpu);
    if (!empty($cpu[0]) && preg_match('/([\d.]+)\s*id/', $cpu[0], $matches)) {
        $idle = (float)$matches[1];
        $resources['cpu'] = [
            'usage_percent' => round(100 - $idle, 2),
            'idle_percent' => round($idle, 2)
        ];
    }

    // Memory
    exec('free -m', $mem);
    if (isset($mem[1]) && preg_match('/Mem:\s+(\d+)\s+(\d+)\s+(\d+)/', $mem[1], $matches)) {
        $resources['memory'] = [
            'total_mb' => (int)$matches[1],
            'used_mb' => (int)$matches[2],
            'free_mb' => (int)$matches[3],
            'usage_percent' => round(($matches[2] / $matches[1]) * 100, 2)
        ];
    }

    // Disk
    exec('df -h /', $disk);
    if (isset($disk[1]) && preg_match('/(\S+)\s+(\S+)\s+(\S+)\s+(\d+)%/', $disk[1], $matches)) {
        $resources['disk'] = [
            'total' => $matches[2],
            'used' => $matches[3],
            'usage_percent' => (int)$matches[4]
        ];
    }

    // Load average
    exec('uptime', $uptime);
    if (!empty($uptime[0]) && preg_match('/load average:\s+([\d.]+),\s+([\d.]+),\s+([\d.]+)/', $uptime[0], $matches)) {
        $resources['load'] = [
            '1min' => (float)$matches[1],
            '5min' => (float)$matches[2],
            '15min' => (float)$matches[3]
        ];
    }

    echo json_encode([
        'success' => true,
        'resources' => $resources,
        'timestamp' => date('c')
    ]);
}

// Helper functions

function getServiceStatus($service) {
    exec("sudo systemctl is-active $service 2>/dev/null", $status);
    $active = (trim($status[0] ?? '') === 'active');

    exec("sudo systemctl show $service --property=ActiveEnterTimestamp --no-pager 2>/dev/null", $start_time);

    return [
        'name' => $service,
        'status' => $active ? 'running' : 'stopped',
        'active' => $active,
        'start_time' => isset($start_time[0]) ? str_replace('ActiveEnterTimestamp=', '', $start_time[0]) : ''
    ];
}

function formatBytes($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

function checkAuth() {
    session_start();
    return [
        'authenticated' => isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true,
        'username' => $_SESSION['username'] ?? null
    ];
}
?>
