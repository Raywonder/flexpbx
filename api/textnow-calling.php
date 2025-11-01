<?php
/**
 * TextNow Calling API for FlexPBX
 * Handles SIP calling through TextNow trunk
 * Integrates with Flexphone WebUI
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
if ($apiKey !== $config['api_key']) {
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
    $sipEnabled = checkTextNowSIPRegistration();

    // Get provider info
    $stmt = $pdo->prepare("SELECT * FROM sms_providers WHERE provider_type = 'textnow' LIMIT 1");
    $stmt->execute();
    $provider = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'sip_enabled' => $sipEnabled,
        'has_permission' => $hasAccess,
        'textnow_number' => '8326786610',
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

    // Check if TextNow trunk is available
    if (!checkTextNowSIPRegistration()) {
        http_response_code(503);
        echo json_encode([
            'success' => false,
            'error' => 'TextNow trunk is not registered',
            'message' => 'Please configure TextNow SIP credentials'
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
        WHERE extension = ? AND permission = 'textnow_calling'
    ");
    $stmt->execute([$extension]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    return $result['count'] > 0;
}

/**
 * Check if TextNow SIP trunk is registered
 */
function checkTextNowSIPRegistration() {
    // Use asterisk CLI to check registration
    $output = shell_exec('asterisk -rx "pjsip show registrations" 2>&1');

    if (!$output) {
        return false;
    }

    // Check if textnow-registration is present and registered
    return (strpos($output, 'textnow') !== false &&
            strpos($output, 'Registered') !== false);
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
        'username' => 'admin',
        'secret' => 'flexpbx_ami_secret'
    ]);

    if (!$ami->connect()) {
        return [
            'success' => false,
            'error' => 'Failed to connect to Asterisk Manager Interface',
            'message' => 'AMI connection failed - check credentials in /etc/asterisk/manager.conf'
        ];
    }

    // Originate call
    $result = $ami->originate([
        'Channel' => "PJSIP/{$extension}",
        'Exten' => $destination,
        'Context' => 'textnow-outbound',
        'Priority' => '1',
        'CallerID' => "TextNow <8326786610>",
        'Timeout' => '30000',
        'Variable' => 'TEXTNOW_CALL=1'
    ]);

    $ami->disconnect();

    $success = isset($result['Response']) && $result['Response'] === 'Success';

    return [
        'success' => $success,
        'message' => $success ? 'Call initiated successfully' : 'Call initiation failed',
        'ami_response' => $result,
        'destination' => $destination,
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
            '8326786610',
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
