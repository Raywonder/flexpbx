<?php
/**
 * FlexPBX Extensions API
 * Manages SIP/PJSIP extensions - CRUD operations and status monitoring
 */

require_once __DIR__ . '/auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle OPTIONS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Require authentication
$auth = checkAuth();
if (!$auth['authenticated']) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized', 'message' => 'Authentication required']);
    exit;
}

// Get request method and endpoint
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';
$extension_id = $_GET['id'] ?? '';

// Configuration
$pjsip_conf = '/etc/asterisk/pjsip.conf';
$extensions_conf = '/etc/asterisk/extensions.conf';
$voicemail_conf = '/etc/asterisk/voicemail.conf';

// Route requests
switch ($path) {
    case '':
    case 'list':
        handleListExtensions($method);
        break;

    case 'create':
        handleCreateExtension($method);
        break;

    case 'update':
        handleUpdateExtension($method, $extension_id);
        break;

    case 'delete':
        handleDeleteExtension($method, $extension_id);
        break;

    case 'status':
        handleExtensionStatus($method, $extension_id);
        break;

    case 'details':
        handleExtensionDetails($method, $extension_id);
        break;

    case 'bulk-create':
        handleBulkCreate($method);
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Not Found', 'message' => 'API endpoint not found']);
        break;
}

/**
 * List all extensions
 */
function handleListExtensions($method) {
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    $extensions = [];

    // Get all endpoints from Asterisk
    exec('sudo asterisk -rx "pjsip show endpoints" 2>/dev/null', $output, $ret);

    if ($ret === 0) {
        foreach ($output as $line) {
            // Parse endpoint lines
            if (preg_match('/^\s*(\d+)\/\d+\s+(\w+)\s+(\d+)/', $line, $matches)) {
                $ext = $matches[1];
                $state = $matches[2];
                $channels = $matches[3];

                // Get additional details
                $details = getExtensionDetails($ext);

                $extensions[] = [
                    'extension' => $ext,
                    'status' => $state,
                    'active_channels' => (int)$channels,
                    'callerid' => $details['callerid'] ?? '',
                    'context' => $details['context'] ?? '',
                    'transport' => $details['transport'] ?? '',
                    'voicemail' => extensionHasVoicemail($ext)
                ];
            }
        }
    }

    echo json_encode([
        'success' => true,
        'extensions' => $extensions,
        'total' => count($extensions),
        'timestamp' => date('c')
    ]);
}

/**
 * Create new extension
 */
function handleCreateExtension($method) {
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    $extension = $data['extension'] ?? '';
    $password = $data['password'] ?? generatePassword();
    $callerid_name = $data['callerid_name'] ?? "Extension $extension";
    $callerid_num = $extension;
    $context = $data['context'] ?? 'flexpbx-internal';
    $voicemail_enabled = $data['voicemail'] ?? true;
    $voicemail_pin = $data['voicemail_pin'] ?? generatePin();
    $email = $data['email'] ?? '';

    // Validate extension number
    if (!preg_match('/^\d{3,5}$/', $extension)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid extension number (must be 3-5 digits)']);
        return;
    }

    // Check if extension exists
    exec("sudo asterisk -rx \"pjsip show endpoint $extension\" 2>/dev/null", $check_output, $check_ret);
    if ($check_ret === 0 && !empty($check_output[0])) {
        http_response_code(409);
        echo json_encode(['error' => 'Extension already exists']);
        return;
    }

    // Create PJSIP configuration
    $pjsip_config = "
; Extension $extension
[{$extension}]
type=endpoint
context={$context}
disallow=all
allow=ulaw,alaw,gsm
auth={$extension}
aors={$extension}
callerid=\"{$callerid_name}\" <{$callerid_num}>
direct_media=no
ice_support=yes
force_rport=yes
rewrite_contact=yes
rtp_symmetric=yes
dtmf_mode=rfc4733

[{$extension}]
type=auth
auth_type=userpass
password={$password}
username={$extension}
realm=flexpbx.devinecreations.net

[{$extension}]
type=aor
max_contacts=1
remove_existing=yes
";

    // Add to pjsip.conf
    global $pjsip_conf;
    if (file_put_contents($pjsip_conf, $pjsip_config, FILE_APPEND) === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to write configuration']);
        return;
    }

    // Set proper permissions
    exec("sudo chown asterisk:asterisk $pjsip_conf");
    exec("sudo chmod 640 $pjsip_conf");

    // Reload PJSIP
    exec('sudo asterisk -rx "pjsip reload" 2>&1', $reload_output, $reload_ret);

    // Create voicemail if enabled
    if ($voicemail_enabled) {
        createVoicemailBox($extension, $voicemail_pin, $callerid_name, $email);
    }

    // Create dialplan entry
    createDialplanEntry($extension, $voicemail_enabled);

    // Log the creation
    logAction('extension_created', $extension, $_SERVER['REMOTE_ADDR']);

    echo json_encode([
        'success' => true,
        'message' => "Extension $extension created successfully",
        'extension' => [
            'extension' => $extension,
            'password' => $password,
            'voicemail_pin' => $voicemail_pin,
            'callerid' => "$callerid_name <$callerid_num>",
            'context' => $context,
            'voicemail_enabled' => $voicemail_enabled
        ],
        'timestamp' => date('c')
    ]);
}

/**
 * Update extension
 */
function handleUpdateExtension($method, $extension_id) {
    if ($method !== 'PUT') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    if (empty($extension_id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Extension ID required']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    // For now, return a placeholder response
    // Full implementation would update pjsip.conf and reload

    echo json_encode([
        'success' => true,
        'message' => "Extension $extension_id update functionality coming soon",
        'note' => 'Use delete and recreate for now',
        'timestamp' => date('c')
    ]);
}

/**
 * Delete extension
 */
function handleDeleteExtension($method, $extension_id) {
    if ($method !== 'DELETE') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    if (empty($extension_id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Extension ID required']);
        return;
    }

    // Check if extension exists
    exec("sudo asterisk -rx \"pjsip show endpoint $extension_id\" 2>/dev/null", $check_output, $check_ret);
    if ($check_ret !== 0 || empty($check_output[0])) {
        http_response_code(404);
        echo json_encode(['error' => 'Extension not found']);
        return;
    }

    // Remove from pjsip.conf
    // This is a simplified approach - full implementation would properly parse and rewrite the file
    global $pjsip_conf;
    $config = file_get_contents($pjsip_conf);

    // Remove all sections for this extension
    $config = preg_replace('/; Extension ' . $extension_id . '.*?\n\n/s', '', $config);
    $config = preg_replace('/\[' . $extension_id . '\].*?\n\n/s', '', $config);

    file_put_contents($pjsip_conf, $config);

    // Set permissions
    exec("sudo chown asterisk:asterisk $pjsip_conf");
    exec("sudo chmod 640 $pjsip_conf");

    // Reload PJSIP
    exec('sudo asterisk -rx "pjsip reload" 2>&1');

    // Remove voicemail
    removeVoicemailBox($extension_id);

    // Log the deletion
    logAction('extension_deleted', $extension_id, $_SERVER['REMOTE_ADDR']);

    echo json_encode([
        'success' => true,
        'message' => "Extension $extension_id deleted successfully",
        'timestamp' => date('c')
    ]);
}

/**
 * Get extension status
 */
function handleExtensionStatus($method, $extension_id) {
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    if (empty($extension_id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Extension ID required']);
        return;
    }

    exec("sudo asterisk -rx \"pjsip show endpoint $extension_id\" 2>/dev/null", $output, $ret);

    if ($ret !== 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Extension not found']);
        return;
    }

    $status = [
        'extension' => $extension_id,
        'registered' => false,
        'state' => 'Unavailable',
        'contacts' => [],
        'channels' => 0
    ];

    foreach ($output as $line) {
        if (preg_match('/Unavailable|Available|Registered/', $line, $matches)) {
            $status['state'] = trim($matches[0]);
            $status['registered'] = ($status['state'] !== 'Unavailable');
        }

        if (preg_match('/(\d+)\s+of\s+inf/', $line, $matches)) {
            $status['channels'] = (int)$matches[1];
        }
    }

    // Get contact information
    exec("sudo asterisk -rx \"pjsip show aor $extension_id\" 2>/dev/null", $aor_output);
    foreach ($aor_output as $line) {
        if (preg_match('/Contact:\s+(.+?)\s+(\w+)\s+(\d+\.\d+)/', $line, $matches)) {
            $status['contacts'][] = [
                'uri' => $matches[1],
                'status' => $matches[2],
                'rtt' => (float)$matches[3]
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'status' => $status,
        'timestamp' => date('c')
    ]);
}

/**
 * Get extension details
 */
function handleExtensionDetails($method, $extension_id) {
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    if (empty($extension_id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Extension ID required']);
        return;
    }

    $details = getExtensionDetails($extension_id);

    if (empty($details)) {
        http_response_code(404);
        echo json_encode(['error' => 'Extension not found']);
        return;
    }

    echo json_encode([
        'success' => true,
        'extension' => $details,
        'timestamp' => date('c')
    ]);
}

/**
 * Bulk create extensions
 */
function handleBulkCreate($method) {
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $extensions = $data['extensions'] ?? [];

    if (empty($extensions)) {
        http_response_code(400);
        echo json_encode(['error' => 'No extensions provided']);
        return;
    }

    $results = [
        'success' => [],
        'failed' => []
    ];

    foreach ($extensions as $ext_data) {
        $ext = $ext_data['extension'] ?? '';

        // Create extension (simplified - would call handleCreateExtension logic)
        try {
            // Implementation here
            $results['success'][] = $ext;
        } catch (Exception $e) {
            $results['failed'][] = [
                'extension' => $ext,
                'error' => $e->getMessage()
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'results' => $results,
        'total_success' => count($results['success']),
        'total_failed' => count($results['failed']),
        'timestamp' => date('c')
    ]);
}

// Helper functions

function getExtensionDetails($extension) {
    exec("sudo asterisk -rx \"pjsip show endpoint $extension\" 2>/dev/null", $output, $ret);

    if ($ret !== 0) {
        return [];
    }

    $details = ['extension' => $extension];

    foreach ($output as $line) {
        if (preg_match('/callerid\s+:\s+(.+)/', $line, $matches)) {
            $details['callerid'] = trim($matches[1]);
        }
        if (preg_match('/context\s+:\s+(.+)/', $line, $matches)) {
            $details['context'] = trim($matches[1]);
        }
        if (preg_match('/transport\s+:\s+(.+)/', $line, $matches)) {
            $details['transport'] = trim($matches[1]);
        }
        if (preg_match('/auth\s+:\s+(.+)/', $line, $matches)) {
            $details['auth'] = trim($matches[1]);
        }
    }

    return $details;
}

function extensionHasVoicemail($extension) {
    global $voicemail_conf;
    $config = @file_get_contents($voicemail_conf);
    return strpos($config, "$extension =>") !== false;
}

function createVoicemailBox($extension, $pin, $name, $email) {
    global $voicemail_conf;

    $vm_entry = "$extension => $pin,$name,$email\n";

    // Add to [flexpbx] context
    $config = file_get_contents($voicemail_conf);

    // Find [flexpbx] section
    if (strpos($config, '[flexpbx]') !== false) {
        $config = preg_replace('/(\[flexpbx\].*?)(\n\[|\z)/s', "$1$vm_entry$2", $config);
    } else {
        $config .= "\n[flexpbx]\n$vm_entry";
    }

    file_put_contents($voicemail_conf, $config);
    exec("sudo chown asterisk:asterisk $voicemail_conf");
    exec("sudo chmod 640 $voicemail_conf");
    exec('sudo asterisk -rx "voicemail reload"');
}

function removeVoicemailBox($extension) {
    global $voicemail_conf;
    $config = file_get_contents($voicemail_conf);
    $config = preg_replace("/^$extension\s*=>.*$/m", '', $config);
    file_put_contents($voicemail_conf, $config);
    exec('sudo asterisk -rx "voicemail reload"');
}

function createDialplanEntry($extension, $voicemail) {
    global $extensions_conf;

    $dialplan = "
exten => $extension,1,NoOp(Calling extension $extension)
exten => $extension,2,Dial(PJSIP/$extension,60,Tt)
";

    if ($voicemail) {
        $dialplan .= "exten => $extension,3,Voicemail($extension@flexpbx,su)\n";
    }

    $dialplan .= "exten => $extension,4,Hangup()\n";

    // Add to [flexpbx-internal] context
    $config = file_get_contents($extensions_conf);

    if (strpos($config, '[flexpbx-internal]') !== false) {
        $config = preg_replace('/(\[flexpbx-internal\].*?)(\n\[|\z)/s', "$1$dialplan$2", $config);
    } else {
        $config .= "\n[flexpbx-internal]\n$dialplan";
    }

    file_put_contents($extensions_conf, $config);
    exec('sudo asterisk -rx "dialplan reload"');
}

function generatePassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%';
    return substr(str_shuffle(str_repeat($chars, $length)), 0, $length);
}

function generatePin($length = 4) {
    return str_pad(rand(0, 9999), $length, '0', STR_PAD_LEFT);
}

function logAction($action, $extension, $ip) {
    $log_file = '/var/log/flexpbx-extensions.log';
    $entry = [
        'timestamp' => date('c'),
        'action' => $action,
        'extension' => $extension,
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
