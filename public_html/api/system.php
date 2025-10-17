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
