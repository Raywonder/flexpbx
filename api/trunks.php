<?php
/**
 * FlexPBX Trunks API
 * Manages SIP trunks - CRUD operations and registration status
 */

require_once __DIR__ . '/auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
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
$trunk_id = $_GET['id'] ?? '';

$pjsip_conf = '/etc/asterisk/pjsip.conf';
$extensions_conf = '/etc/asterisk/extensions.conf';

switch ($path) {
    case '':
    case 'list':
        handleListTrunks($method);
        break;

    case 'create':
        handleCreateTrunk($method);
        break;

    case 'update':
        handleUpdateTrunk($method, $trunk_id);
        break;

    case 'delete':
        handleDeleteTrunk($method, $trunk_id);
        break;

    case 'status':
        handleTrunkStatus($method, $trunk_id);
        break;

    case 'details':
        handleTrunkDetails($method, $trunk_id);
        break;

    case 'test':
        handleTestTrunk($method, $trunk_id);
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found']);
        break;
}

/**
 * List all trunks
 */
function handleListTrunks($method) {
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    $trunks = [];

    // Get all registrations
    exec('sudo asterisk -rx "pjsip show registrations" 2>/dev/null', $output, $ret);

    if ($ret === 0) {
        foreach ($output as $line) {
            // Parse trunk registration lines
            if (preg_match('/^\s*(\S+)\s+\S+\s+(\w+)/', $line, $matches)) {
                $trunk_name = $matches[1];
                $state = $matches[2];

                // Skip if it's an extension (numeric only)
                if (preg_match('/^\d+$/', $trunk_name)) {
                    continue;
                }

                $details = getTrunkDetails($trunk_name);

                $trunks[] = [
                    'name' => $trunk_name,
                    'status' => $state,
                    'registered' => ($state === 'Registered'),
                    'type' => $details['type'] ?? 'Unknown',
                    'server' => $details['server'] ?? '',
                    'username' => $details['username'] ?? ''
                ];
            }
        }
    }

    // Also check for non-registering trunks (inbound only)
    exec('sudo asterisk -rx "pjsip show endpoints" 2>/dev/null', $endpoint_output);
    foreach ($endpoint_output as $line) {
        if (preg_match('/^\s*(\S+)\//', $line, $matches)) {
            $endpoint_name = $matches[1];

            // Skip extensions and already listed trunks
            if (preg_match('/^\d+$/', $endpoint_name)) {
                continue;
            }

            $already_listed = false;
            foreach ($trunks as $trunk) {
                if ($trunk['name'] === $endpoint_name) {
                    $already_listed = true;
                    break;
                }
            }

            if (!$already_listed) {
                $details = getTrunkDetails($endpoint_name);
                $trunks[] = [
                    'name' => $endpoint_name,
                    'status' => 'No-Registration',
                    'registered' => false,
                    'type' => 'Inbound-Only',
                    'server' => $details['server'] ?? '',
                    'username' => $details['username'] ?? ''
                ];
            }
        }
    }

    echo json_encode([
        'success' => true,
        'trunks' => $trunks,
        'total' => count($trunks),
        'timestamp' => date('c')
    ]);
}

/**
 * Create new trunk
 */
function handleCreateTrunk($method) {
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    $trunk_name = $data['name'] ?? '';
    $type = $data['type'] ?? 'peer'; // 'peer', 'register', 'both'
    $server = $data['server'] ?? '';
    $username = $data['username'] ?? '';
    $password = $data['password'] ?? '';
    $from_user = $data['from_user'] ?? $username;
    $from_domain = $data['from_domain'] ?? $server;
    $codecs = $data['codecs'] ?? ['ulaw', 'alaw', 'gsm'];
    $context = $data['context'] ?? 'from-trunk';

    // Validate trunk name
    if (empty($trunk_name) || !preg_match('/^[a-z0-9_-]+$/i', $trunk_name)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid trunk name (alphanumeric, dash, underscore only)']);
        return;
    }

    // Check if trunk exists
    exec("sudo asterisk -rx \"pjsip show endpoint $trunk_name\" 2>/dev/null", $check, $check_ret);
    if ($check_ret === 0 && !empty($check[0])) {
        http_response_code(409);
        echo json_encode(['error' => 'Trunk already exists']);
        return;
    }

    $codecs_str = implode(',', $codecs);

    // Build PJSIP configuration
    $pjsip_config = "
; Trunk: $trunk_name
[{$trunk_name}]
type=registration
outbound_auth={$trunk_name}-auth
server_uri=sip:{$server}
client_uri=sip:{$username}@{$server}
retry_interval=60

[{$trunk_name}-auth]
type=auth
auth_type=userpass
username={$username}
password={$password}

[{$trunk_name}]
type=aor
contact=sip:{$server}
qualify_frequency=60

[{$trunk_name}]
type=endpoint
context={$context}
disallow=all
allow={$codecs_str}
outbound_auth={$trunk_name}-auth
aors={$trunk_name}
from_user={$from_user}
from_domain={$from_domain}
direct_media=no
ice_support=yes
force_rport=yes
rewrite_contact=yes

[{$trunk_name}-identify]
type=identify
endpoint={$trunk_name}
match={$server}
";

    // Add to pjsip.conf
    global $pjsip_conf;
    if (file_put_contents($pjsip_conf, $pjsip_config, FILE_APPEND) === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to write configuration']);
        return;
    }

    // Set permissions
    exec("sudo chown asterisk:asterisk $pjsip_conf");
    exec("sudo chmod 640 $pjsip_conf");

    // Reload PJSIP
    exec('sudo asterisk -rx "pjsip reload" 2>&1', $reload_output);

    // Create basic dialplan entry
    createTrunkDialplan($trunk_name, $context);

    logAction('trunk_created', $trunk_name, $_SERVER['REMOTE_ADDR']);

    echo json_encode([
        'success' => true,
        'message' => "Trunk $trunk_name created successfully",
        'trunk' => [
            'name' => $trunk_name,
            'type' => $type,
            'server' => $server,
            'username' => $username,
            'context' => $context
        ],
        'timestamp' => date('c')
    ]);
}

/**
 * Update trunk
 */
function handleUpdateTrunk($method, $trunk_id) {
    if ($method !== 'PUT') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    if (empty($trunk_id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Trunk ID required']);
        return;
    }

    // Placeholder - full implementation would parse and update pjsip.conf
    echo json_encode([
        'success' => true,
        'message' => "Trunk update functionality coming soon",
        'note' => 'Use delete and recreate for now',
        'timestamp' => date('c')
    ]);
}

/**
 * Delete trunk
 */
function handleDeleteTrunk($method, $trunk_id) {
    if ($method !== 'DELETE') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    if (empty($trunk_id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Trunk ID required']);
        return;
    }

    // Remove from pjsip.conf
    global $pjsip_conf;
    $config = file_get_contents($pjsip_conf);

    // Remove trunk sections
    $config = preg_replace('/; Trunk: ' . preg_quote($trunk_id) . '.*?\n\n/s', '', $config);
    $config = preg_replace('/\[' . preg_quote($trunk_id) . '.*?\].*?\n\n/s', '', $config);

    file_put_contents($pjsip_conf, $config);

    exec("sudo chown asterisk:asterisk $pjsip_conf");
    exec("sudo chmod 640 $pjsip_conf");
    exec('sudo asterisk -rx "pjsip reload"');

    logAction('trunk_deleted', $trunk_id, $_SERVER['REMOTE_ADDR']);

    echo json_encode([
        'success' => true,
        'message' => "Trunk $trunk_id deleted successfully",
        'timestamp' => date('c')
    ]);
}

/**
 * Get trunk status
 */
function handleTrunkStatus($method, $trunk_id) {
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    if (empty($trunk_id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Trunk ID required']);
        return;
    }

    $status = [
        'trunk' => $trunk_id,
        'registered' => false,
        'state' => 'Unknown',
        'server' => '',
        'last_register' => ''
    ];

    exec("sudo asterisk -rx \"pjsip show registration $trunk_id\" 2>/dev/null", $output);

    foreach ($output as $line) {
        if (preg_match('/Status\s+:\s+(\w+)/', $line, $matches)) {
            $status['state'] = $matches[1];
            $status['registered'] = ($matches[1] === 'Registered');
        }
        if (preg_match('/Server URI\s+:\s+(.+)/', $line, $matches)) {
            $status['server'] = trim($matches[1]);
        }
    }

    // Check endpoint status
    exec("sudo asterisk -rx \"pjsip show endpoint $trunk_id\" 2>/dev/null", $ep_output);
    $endpoint_exists = false;
    foreach ($ep_output as $line) {
        if (preg_match('/Endpoint:/', $line)) {
            $endpoint_exists = true;
            break;
        }
    }

    echo json_encode([
        'success' => true,
        'status' => $status,
        'endpoint_configured' => $endpoint_exists,
        'timestamp' => date('c')
    ]);
}

/**
 * Get trunk details
 */
function handleTrunkDetails($method, $trunk_id) {
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    if (empty($trunk_id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Trunk ID required']);
        return;
    }

    $details = getTrunkDetails($trunk_id);

    if (empty($details)) {
        http_response_code(404);
        echo json_encode(['error' => 'Trunk not found']);
        return;
    }

    echo json_encode([
        'success' => true,
        'trunk' => $details,
        'timestamp' => date('c')
    ]);
}

/**
 * Test trunk connectivity
 */
function handleTestTrunk($method, $trunk_id) {
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    if (empty($trunk_id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Trunk ID required']);
        return;
    }

    $test_results = [
        'trunk' => $trunk_id,
        'tests' => []
    ];

    // Test 1: Check registration
    exec("sudo asterisk -rx \"pjsip show registration $trunk_id\" 2>/dev/null", $reg_output);
    $registered = false;
    foreach ($reg_output as $line) {
        if (preg_match('/Status\s+:\s+Registered/', $line)) {
            $registered = true;
            break;
        }
    }
    $test_results['tests']['registration'] = [
        'passed' => $registered,
        'message' => $registered ? 'Trunk is registered' : 'Trunk not registered'
    ];

    // Test 2: Check endpoint
    exec("sudo asterisk -rx \"pjsip show endpoint $trunk_id\" 2>/dev/null", $ep_output, $ep_ret);
    $endpoint_ok = ($ep_ret === 0 && !empty($ep_output));
    $test_results['tests']['endpoint'] = [
        'passed' => $endpoint_ok,
        'message' => $endpoint_ok ? 'Endpoint configured' : 'Endpoint not found'
    ];

    // Test 3: Check AOR
    exec("sudo asterisk -rx \"pjsip show aor $trunk_id\" 2>/dev/null", $aor_output, $aor_ret);
    $aor_ok = ($aor_ret === 0 && !empty($aor_output));
    $test_results['tests']['aor'] = [
        'passed' => $aor_ok,
        'message' => $aor_ok ? 'AOR configured' : 'AOR not found'
    ];

    // Overall result
    $all_passed = $test_results['tests']['registration']['passed'] &&
                  $test_results['tests']['endpoint']['passed'] &&
                  $test_results['tests']['aor']['passed'];

    echo json_encode([
        'success' => true,
        'overall_status' => $all_passed ? 'healthy' : 'issues_detected',
        'results' => $test_results,
        'timestamp' => date('c')
    ]);
}

// Helper functions

function getTrunkDetails($trunk_name) {
    exec("sudo asterisk -rx \"pjsip show endpoint $trunk_name\" 2>/dev/null", $output, $ret);

    if ($ret !== 0) {
        return [];
    }

    $details = ['name' => $trunk_name];

    foreach ($output as $line) {
        if (preg_match('/context\s+:\s+(.+)/', $line, $matches)) {
            $details['context'] = trim($matches[1]);
        }
        if (preg_match('/outbound_auth\s+:\s+(.+)/', $line, $matches)) {
            $details['auth'] = trim($matches[1]);
        }
        if (preg_match('/from_user\s+:\s+(.+)/', $line, $matches)) {
            $details['from_user'] = trim($matches[1]);
        }
        if (preg_match('/from_domain\s+:\s+(.+)/', $line, $matches)) {
            $details['from_domain'] = trim($matches[1]);
        }
    }

    // Get auth details
    if (isset($details['auth'])) {
        exec("sudo asterisk -rx \"pjsip show auth {$details['auth']}\" 2>/dev/null", $auth_output);
        foreach ($auth_output as $line) {
            if (preg_match('/username\s+:\s+(.+)/', $line, $matches)) {
                $details['username'] = trim($matches[1]);
            }
        }
    }

    // Get registration info
    exec("sudo asterisk -rx \"pjsip show registration $trunk_name\" 2>/dev/null", $reg_output);
    foreach ($reg_output as $line) {
        if (preg_match('/Server URI\s+:\s+sip:(.+?)(:|\s|$)/', $line, $matches)) {
            $details['server'] = trim($matches[1]);
            $details['type'] = 'Outbound';
        }
        if (preg_match('/Status\s+:\s+(.+)/', $line, $matches)) {
            $details['status'] = trim($matches[1]);
        }
    }

    return $details;
}

function createTrunkDialplan($trunk_name, $context) {
    global $extensions_conf;

    $dialplan = "
; Trunk: $trunk_name inbound routing
[{$context}]
exten => _X.,1,NoOp(Inbound from $trunk_name: \${CALLERID(all)})
exten => _X.,2,Goto(flexpbx-internal,\${EXTEN},1)
";

    $config = file_get_contents($extensions_conf);

    // Check if context already exists
    if (strpos($config, "[$context]") === false) {
        file_put_contents($extensions_conf, $dialplan, FILE_APPEND);
        exec('sudo asterisk -rx "dialplan reload"');
    }
}

function logAction($action, $trunk, $ip) {
    $log_file = '/var/log/flexpbx-trunks.log';
    $entry = [
        'timestamp' => date('c'),
        'action' => $action,
        'trunk' => $trunk,
        'ip' => $ip,
        'user' => $_SESSION['username'] ?? 'unknown'
    ];
    file_put_contents($log_file, json_encode($entry) . "\n", FILE_APPEND);
}

function checkAuth() {
    session_start();
    return [
        'authenticated' => isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true,
        'username' => $_SESSION['username'] ?? null
    ];
}
?>
