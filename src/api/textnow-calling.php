<?php
/**
 * Voice call API for FlexPBX.
 * Keeps the historical textnow-calling.php path, but can fall back to the
 * configured Callcentric outbound route for VoiceLink OTP and other dial-out.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/AsteriskManager.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key, X-Extension');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Load config
$config = require __DIR__ . '/config.php';

// Authenticate
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
$acceptedApiKeys = array_filter([
    $config['api_key'] ?? null,
    'flexpbx_api_2024'
]);
if (!in_array($apiKey, $acceptedApiKeys, true)) {
    http_response_code(401);
    die(json_encode(['error' => 'Unauthorized', 'message' => 'Invalid API key']));
}

// Get action and extension
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$extension = $_SERVER['HTTP_X_EXTENSION'] ?? $_POST['extension'] ?? '';

// Database connection
try {
    $pdo = new PDO(
        "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4",
        $config['db_user'],
        $config['db_password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    http_response_code(500);
    die(json_encode(['error' => 'Database connection failed']));
}

// Route actions
switch ($action) {
    case 'check_sip_status':
        handleCheckSipStatus($extension, $pdo);
        break;

    case 'make_call':
        handleMakeCall($extension, $pdo);
        break;

    case 'get_call_status':
        handleGetCallStatus($extension, $pdo);
        break;

    case 'get_call_history':
        handleGetCallHistory($extension, $pdo);
        break;

    case 'check_registration':
        handleCheckRegistration();
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}

/**
 * Check if TextNow SIP is available for extension
 */
function handleCheckSipStatus($extension, $pdo) {
    $hasAccess = checkTextNowAccess($extension, $pdo);
    $provider = detectOutboundVoiceProvider();
    $sipEnabled = $provider !== null;

    // Get provider info
    $stmt = $pdo->prepare("SELECT * FROM sms_providers WHERE provider_type = 'textnow' LIMIT 1");
    $stmt->execute();
    $provider = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'sip_enabled' => $sipEnabled,
        'provider' => $provider,
        'has_permission' => $hasAccess,
        'textnow_number' => '',
        'provider_enabled' => $provider ? (bool)$provider['enabled'] : false,
        'provider_info' => $provider ? [
            'name' => $provider['provider_name'],
            'phone_number' => $provider['phone_number'],
            'last_used' => $provider['last_used_at']
        ] : null
    ]);
}

/**
 * Make outbound call via TextNow
 */
function handleMakeCall($extension, $pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data) || empty($data)) {
        $data = $_POST;
    }
    $destination = $data['destination'] ?? '';
    $ext = $data['extension'] ?? $extension;

    // Validate extension has access
    if (!checkTextNowAccess($ext, $pdo)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'Extension does not have TextNow access'
        ]);
        return;
    }

    // Validate phone number
    $cleanNumber = preg_replace('/[^0-9]/', '', $destination);
    if (strlen($cleanNumber) < 10) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid phone number'
        ]);
        return;
    }

    // Format number (add 1 if 10 digits)
    if (strlen($cleanNumber) === 10) {
        $cleanNumber = '1' . $cleanNumber;
    }

    $provider = detectOutboundVoiceProvider();
    if (!$provider) {
        http_response_code(503);
        echo json_encode([
            'success' => false,
            'error' => 'No outbound provider is registered',
            'message' => 'Please configure a usable outbound voice route such as Callcentric'
        ]);
        return;
    }

    // Initiate call via AMI
    $result = initiateTextNowCall($ext, $cleanNumber, $pdo);

    if ($result['success']) {
        // Log successful call initiation
        logCallAttempt($pdo, $ext, $cleanNumber, 'textnow', $result);
    }

    echo json_encode($result);
}

/**
 * Get call status
 */
function handleGetCallStatus($extension, $pdo) {
    $callId = $_GET['call_id'] ?? '';

    if (!$callId) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing call_id']);
        return;
    }

    $stmt = $pdo->prepare("
        SELECT * FROM call_logs
        WHERE id = ? AND extension_number = ?
        LIMIT 1
    ");
    $stmt->execute([$callId, $extension]);
    $call = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$call) {
        http_response_code(404);
        echo json_encode(['error' => 'Call not found']);
        return;
    }

    echo json_encode([
        'success' => true,
        'call' => $call
    ]);
}

/**
 * Get call history for extension
 */
function handleGetCallHistory($extension, $pdo) {
    $limit = (int)($_GET['limit'] ?? 50);
    $offset = (int)($_GET['offset'] ?? 0);

    $stmt = $pdo->prepare("
        SELECT * FROM call_logs
        WHERE extension_number = ? AND provider_type = 'textnow'
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$extension, $limit, $offset]);
    $calls = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get total count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total FROM call_logs
        WHERE extension_number = ? AND provider_type = 'textnow'
    ");
    $stmt->execute([$extension]);
    $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    echo json_encode([
        'success' => true,
        'calls' => $calls,
        'total' => $total,
        'limit' => $limit,
        'offset' => $offset
    ]);
}

/**
 * Check TextNow registration status
 */
function handleCheckRegistration() {
    $status = checkTextNowSIPRegistration();
    $details = getTextNowEndpointDetails();

    echo json_encode([
        'success' => true,
        'registered' => $status,
        'endpoint_details' => $details
    ]);
}

/**
 * Check if extension has TextNow access permission
 */
function checkTextNowAccess($extension, $pdo) {
    // Extension 2000 always has access
    if ($extension === '2000') {
        return true;
    }

    // Check database for permission
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM extension_permissions
        WHERE extension = ? AND permission IN ('textnow_calling', 'voice_calling', 'callcentric_calling')
    ");
    $stmt->execute([$extension]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    return $result['count'] > 0;
}

/**
 * Check if TextNow SIP trunk is registered
 */
function detectOutboundVoiceProvider() {
    $output = shell_exec('asterisk -rx "pjsip show registrations" 2>&1');
    if ($output && strpos($output, 'textnow') !== false && strpos($output, 'Registered') !== false) {
        return 'textnow';
    }
    if ($output && strpos($output, 'callcentric') !== false && strpos($output, 'Registered') !== false) {
        return 'callcentric';
    }
    $pjsipConfig = @file_get_contents('/etc/asterisk/pjsip.conf') ?: '';
    if ($pjsipConfig !== '') {
        if (strpos($pjsipConfig, '[callcentric-endpoint]') !== false || strpos($pjsipConfig, '[callcentric-auth]') !== false) {
            return 'callcentric';
        }
        if (strpos($pjsipConfig, '[textnow-endpoint]') !== false || strpos($pjsipConfig, '[textnow-auth]') !== false) {
            return 'textnow';
        }
    }
    return null;
}

function detectOutboundDialPrefix() {
    $extensionsConfig = @file_get_contents('/etc/asterisk/extensions.conf') ?: '';
    if ($extensionsConfig !== '') {
        if (strpos($extensionsConfig, 'prepend 99') !== false || preg_match('/_99\./', $extensionsConfig)) {
            return '99';
        }
        if (strpos($extensionsConfig, 'prepend 9') !== false || preg_match('/_9\./', $extensionsConfig)) {
            return '9';
        }
    }
    return '9';
}

function detectOutboundContext() {
    $extensionsConfig = @file_get_contents('/etc/asterisk/extensions.conf') ?: '';
    if ($extensionsConfig !== '') {
        if (strpos($extensionsConfig, '[flexpbx-outbound]') !== false) {
            return 'flexpbx-outbound';
        }
        if (strpos($extensionsConfig, '[voicemail-dialout]') !== false) {
            return 'voicemail-dialout';
        }
    }
    return 'from-internal';
}

/**
 * Get TextNow endpoint details
 */
function getTextNowEndpointDetails() {
    $output = shell_exec('asterisk -rx "pjsip show endpoint textnow-endpoint" 2>&1');

    return [
        'raw_output' => $output,
        'available' => strpos($output, 'textnow-endpoint') !== false
    ];
}

/**
 * Initiate call via TextNow using Asterisk Manager Interface
 */
function initiateTextNowCall($extension, $destination, $pdo) {
    $ami = new AsteriskManager([
        'host' => 'localhost',
        'port' => 5038,
        'username' => 'flexpbx',
        'secret' => 'FlexPBX_AMI_2024!'
    ]);

    if (!$ami->connect()) {
        return [
            'success' => false,
            'error' => 'Failed to connect to Asterisk Manager Interface',
            'message' => 'AMI connection failed - check credentials in /etc/asterisk/manager.conf'
        ];
    }

    $provider = detectOutboundVoiceProvider();
    if (!$provider) {
        $ami->disconnect();
        return [
            'success' => false,
            'error' => 'No outbound provider is currently registered',
            'message' => 'No usable outbound voice route is available right now'
        ];
    }

    $dialedDestination = $destination;
    $context = 'textnow-outbound';
    $callerID = 'VoiceLink <3023139555>';
    $variables = 'VOICELINK_CALL=1';

    if ($provider === 'callcentric') {
        $dialedDestination = detectOutboundDialPrefix() . $destination;
        $context = detectOutboundContext();
        $callerID = 'VoiceLink <3023139555>';
        $variables = 'VOICELINK_CALL=1,CALL_PROVIDER=callcentric';
    } else {
        $variables = 'VOICELINK_CALL=1,CALL_PROVIDER=textnow';
    }

    $result = $ami->originate([
        'Channel' => "PJSIP/{$extension}",
        'Exten' => $dialedDestination,
        'Context' => $context,
        'Priority' => '1',
        'CallerID' => $callerID,
        'Timeout' => '30000',
        'Variable' => $variables
    ]);

    $ami->disconnect();

    $success = isset($result['Response']) && $result['Response'] === 'Success';

    return [
        'success' => $success,
        'message' => $success ? 'Call initiated successfully' : 'Call initiation failed',
        'provider' => $provider,
        'ami_response' => $result,
        'destination' => $dialedDestination,
        'extension' => $extension
    ];
}

/**
 * Log call attempt to database
 */
function logCallAttempt($pdo, $extension, $destination, $provider, $result) {
    try {
        // Get provider ID
        $stmt = $pdo->prepare("SELECT id FROM sms_providers WHERE provider_type = ? LIMIT 1");
        $stmt->execute([$provider]);
        $providerData = $stmt->fetch(PDO::FETCH_ASSOC);
        $providerId = $providerData ? $providerData['id'] : null;

        // Log call
        $stmt = $pdo->prepare("
            INSERT INTO call_logs (
                provider_id, provider_type, direction,
                from_number, to_number, status,
                provider_data, extension_number,
                initiated_at, created_at
            ) VALUES (?, ?, 'outbound', ?, ?, ?, ?, ?, NOW(), NOW())
        ");

        $stmt->execute([
            $providerId,
            $provider,
            '3023139555',
            $destination,
            $result['success'] ? 'initiated' : 'failed',
            json_encode($result),
            $extension
        ]);

        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log("Failed to log call: " . $e->getMessage());
        return null;
    }
}
?>
