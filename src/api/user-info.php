<?php
/**
 * FlexPBX User Information API
 *
 * Provides user account information including DIDs, features, extensions
 *
 * Endpoints:
 * - GET /api/user-info.php?path=my_dids&user_id={id} - Get user's DIDs
 * - GET /api/user-info.php?path=my_extension&user_id={id} - Get user's extension info
 * - GET /api/user-info.php?path=my_features&user_id={id} - Get enabled features
 * - GET /api/user-info.php?path=my_profile&user_id={id} - Get full user profile
 * - POST /api/user-info.php?path=request_did - Request new DID
 *
 * @author FlexPBX
 * @version 1.0.0
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/AutoProvisioning.php';

// Get path
$path = $_GET['path'] ?? 'my_profile';

// Get user ID from session or parameter
// TODO: Implement proper session management
$user_id = $_GET['user_id'] ?? $_POST['user_id'] ?? $_SESSION['user_id'] ?? null;

if (!$user_id && $path !== 'test') {
    response(['error' => 'User not authenticated'], 401);
}

// Initialize auto-provisioning
$provisioning = new AutoProvisioning();

// Handle different paths
switch ($path) {
    case 'my_dids':
        handleMyDIDs($user_id);
        break;

    case 'my_extension':
        handleMyExtension($user_id);
        break;

    case 'my_features':
        handleMyFeatures($user_id);
        break;

    case 'my_profile':
        handleMyProfile($user_id);
        break;

    case 'request_did':
        handleRequestDID($user_id);
        break;

    case 'test':
        handleTest();
        break;

    default:
        response(['error' => 'Invalid path'], 400);
}

/**
 * Get user's DIDs
 */
function handleMyDIDs($user_id) {
    global $db;

    // Get user's DIDs
    $stmt = $db->prepare("
        SELECT
            ud.*,
            e.extension_number,
            e.display_name
        FROM user_dids ud
        LEFT JOIN extensions e ON ud.extension = e.extension_number
        WHERE ud.user_id = ?
        ORDER BY ud.is_primary DESC, ud.assigned_date ASC
    ");

    $stmt->execute([$user_id]);
    $dids = $stmt->fetchAll();

    // Get main DID from config
    $stmt = $db->prepare("SELECT config_value FROM system_config WHERE config_key = 'main_did_number'");
    $stmt->execute();
    $main_did = $stmt->fetch()['config_value'] ?? '3023139555';

    // If user has no DIDs, they should be assigned the main DID
    if (empty($dids)) {
        $message = "No DIDs assigned. Contact administrator to assign the main shared DID ({$main_did}).";
    } else {
        $message = null;
    }

    // Categorize DIDs
    $shared_dids = array_filter($dids, function($did) {
        return $did['is_shared'] == 1;
    });

    $personal_dids = array_filter($dids, function($did) {
        return $did['is_shared'] == 0;
    });

    response([
        'success' => true,
        'user_id' => $user_id,
        'dids' => [
            'all' => $dids,
            'shared' => array_values($shared_dids),
            'personal' => array_values($personal_dids)
        ],
        'main_did' => $main_did,
        'has_personal_did' => !empty($personal_dids),
        'message' => $message
    ]);
}

/**
 * Get user's extension information
 */
function handleMyExtension($user_id) {
    global $db;

    $stmt = $db->prepare("
        SELECT
            e.*,
            ef.voicemail_enabled,
            ef.voicemail_pin,
            ef.email_notifications,
            ef.mastodon_notifications,
            ef.call_recording_enabled,
            ef.accessibility_enabled,
            ef.department
        FROM extensions e
        LEFT JOIN extension_features ef ON e.extension_number = ef.extension
        WHERE e.user_id = ?
    ");

    $stmt->execute([$user_id]);
    $extension = $stmt->fetch();

    if (!$extension) {
        response(['error' => 'No extension found for user'], 404);
    }

    // Don't expose password hash
    unset($extension['password_hash']);

    response([
        'success' => true,
        'extension' => $extension
    ]);
}

/**
 * Get user's enabled features
 */
function handleMyFeatures($user_id) {
    global $db;

    // Get extension first
    $stmt = $db->prepare("SELECT extension_number FROM extensions WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $ext_data = $stmt->fetch();

    if (!$ext_data) {
        response(['error' => 'No extension found for user'], 404);
    }

    $extension = $ext_data['extension_number'];

    // Get features
    $stmt = $db->prepare("SELECT * FROM extension_features WHERE extension = ?");
    $stmt->execute([$extension]);
    $features = $stmt->fetch();

    if (!$features) {
        response(['error' => 'No features configured for extension'], 404);
    }

    // Get notification preferences
    $stmt = $db->prepare("SELECT * FROM user_notification_preferences WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $notifications = $stmt->fetch();

    response([
        'success' => true,
        'extension' => $extension,
        'features' => $features,
        'notifications' => $notifications,
        'summary' => [
            'voicemail' => (bool)$features['voicemail_enabled'],
            'email_notifications' => (bool)$features['email_notifications'],
            'mastodon_notifications' => (bool)$features['mastodon_notifications'],
            'call_recording' => (bool)$features['call_recording_enabled'],
            'accessibility' => (bool)$features['accessibility_enabled'],
            'department' => $features['department']
        ]
    ]);
}

/**
 * Get full user profile
 */
function handleMyProfile($user_id) {
    global $db;

    // Get user info
    $stmt = $db->prepare("
        SELECT
            u.id,
            u.username,
            u.email,
            u.full_name,
            u.role,
            u.is_active,
            u.last_login,
            u.created_at
        FROM users u
        WHERE u.id = ?
    ");

    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        response(['error' => 'User not found'], 404);
    }

    // Get extension
    $stmt = $db->prepare("
        SELECT
            e.extension_number,
            e.display_name,
            e.email as extension_email,
            e.status,
            e.last_registered,
            e.registration_ip
        FROM extensions e
        WHERE e.user_id = ?
    ");

    $stmt->execute([$user_id]);
    $extension = $stmt->fetch();

    // Get DIDs
    $stmt = $db->prepare("
        SELECT * FROM user_dids
        WHERE user_id = ?
        ORDER BY is_primary DESC
    ");

    $stmt->execute([$user_id]);
    $dids = $stmt->fetchAll();

    // Get features
    $features = null;
    if ($extension) {
        $stmt = $db->prepare("SELECT * FROM extension_features WHERE extension = ?");
        $stmt->execute([$extension['extension_number']]);
        $features = $stmt->fetch();
    }

    response([
        'success' => true,
        'profile' => [
            'user' => $user,
            'extension' => $extension,
            'dids' => $dids,
            'features' => $features
        ]
    ]);
}

/**
 * Request new DID
 */
function handleRequestDID($user_id) {
    global $db, $provisioning;

    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);

    // Get user's extension
    $stmt = $db->prepare("SELECT extension_number FROM extensions WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $ext_data = $stmt->fetch();

    if (!$ext_data) {
        response(['error' => 'No extension found for user'], 404);
    }

    $extension = $ext_data['extension_number'];

    // Create DID request
    $request_id = $provisioning->requestNewDID(
        $user_id,
        $extension,
        $data['area_code'] ?? null,
        $data['type'] ?? 'new'
    );

    response([
        'success' => true,
        'message' => 'DID request submitted successfully',
        'request_id' => $request_id,
        'extension' => $extension
    ]);
}

/**
 * Test endpoint
 */
function handleTest() {
    response([
        'success' => true,
        'message' => 'User Info API is working',
        'timestamp' => date('Y-m-d H:i:s'),
        'available_endpoints' => [
            'my_dids' => 'GET - Get user DIDs',
            'my_extension' => 'GET - Get extension info',
            'my_features' => 'GET - Get enabled features',
            'my_profile' => 'GET - Get full profile',
            'request_did' => 'POST - Request new DID'
        ]
    ]);
}

/**
 * Send JSON response
 */
function response($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit();
}
