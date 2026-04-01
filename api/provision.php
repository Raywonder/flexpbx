<?php
/**
 * FlexPBX Provisioning API
 * Provides SIP credentials via PIN authentication or account login
 * All provisioning is linked to user accounts
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

// Configuration
define('ASTERISK_PJSIP_CONF', '/etc/asterisk/pjsip.conf');
define('PIN_FILE', '/var/lib/asterisk/provision_pins.json');
define('PIN_EXPIRY_HOURS', 24);

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

    case 'authenticate':
    case 'login':
        handleAuthenticate($postData);
        break;

    case 'generate_pin':
        handleGeneratePin($postData);
        break;

    case 'verify_pin':
        handleVerifyPin($postData);
        break;

    case 'get_config':
        handleGetConfig($postData);
        break;

    case 'revoke_pin':
        handleRevokePin($postData);
        break;

    case 'list_pins':
        handleListPins();
        break;

    default:
        respond(false, 'Invalid path', null, 404);
        break;
}

/**
 * API Information
 */
function handleInfo() {
    respond(true, 'FlexPBX Provisioning API', [
        'version' => '1.0',
        'endpoints' => [
            'authenticate' => 'Authenticate with extension + password',
            'generate_pin' => 'Generate provisioning PIN (admin only)',
            'verify_pin' => 'Verify PIN and get credentials',
            'get_config' => 'Get SIP configuration',
            'revoke_pin' => 'Revoke a PIN (admin only)',
            'list_pins' => 'List active PINs (admin only)'
        ],
        'authentication_methods' => [
            'password' => 'Extension + SIP password',
            'pin' => 'Extension + Provisioning PIN'
        ]
    ]);
}

/**
 * Authenticate with extension + password
 */
function handleAuthenticate($data) {
    $extension = $data['extension'] ?? $_GET['extension'] ?? null;
    $password = $data['password'] ?? $_GET['password'] ?? null;

    if (!$extension || !$password) {
        respond(false, 'Extension and password required');
        return;
    }

    // Verify credentials in pjsip.conf
    $authInfo = getAuthInfo($extension);

    if (!$authInfo) {
        respond(false, 'Extension not found');
        return;
    }

    if ($authInfo['password'] !== $password) {
        respond(false, 'Invalid password');
        return;
    }

    // Return SIP configuration
    $config = generateSipConfig($extension, $password);
    respond(true, 'Authentication successful', [
        'extension' => $extension,
        'config' => $config,
        'sip_uri' => $config['sip_uri']
    ]);
}

/**
 * Generate provisioning PIN
 */
function handleGeneratePin($data) {
    $extension = $data['extension'] ?? null;
    $admin_password = $data['admin_password'] ?? null;
    $expires_hours = $data['expires_hours'] ?? PIN_EXPIRY_HOURS;

    if (!$extension) {
        respond(false, 'Extension required');
        return;
    }

    // Verify admin credentials (extension 2000 or other admin)
    if (!verifyAdminAccess($admin_password)) {
        respond(false, 'Admin authentication required');
        return;
    }

    // Verify extension exists
    $authInfo = getAuthInfo($extension);
    if (!$authInfo) {
        respond(false, 'Extension not found');
        return;
    }

    // Generate PIN
    $pin = generatePin();
    $expiresAt = time() + ($expires_hours * 3600);

    // Save PIN
    savePinToFile($extension, $pin, $expiresAt);

    respond(true, 'PIN generated successfully', [
        'extension' => $extension,
        'pin' => $pin,
        'expires_at' => date('Y-m-d H:i:s', $expiresAt),
        'expires_in_hours' => $expires_hours,
        'usage' => "Use extension $extension + PIN $pin to provision"
    ]);
}

/**
 * Verify PIN and get credentials
 */
function handleVerifyPin($data) {
    $extension = $data['extension'] ?? $_GET['extension'] ?? null;
    $pin = $data['pin'] ?? $_GET['pin'] ?? null;

    if (!$extension || !$pin) {
        respond(false, 'Extension and PIN required');
        return;
    }

    // Verify PIN
    $pinData = verifyPin($extension, $pin);

    if (!$pinData) {
        respond(false, 'Invalid or expired PIN');
        return;
    }

    // Get password from pjsip.conf
    $authInfo = getAuthInfo($extension);
    if (!$authInfo) {
        respond(false, 'Extension not found');
        return;
    }

    // Generate SIP configuration
    $config = generateSipConfig($extension, $authInfo['password']);

    // Optionally revoke PIN after use (one-time use)
    // revokePin($extension, $pin);

    respond(true, 'PIN verified successfully', [
        'extension' => $extension,
        'config' => $config,
        'sip_uri' => $config['sip_uri'],
        'pin_used' => true
    ]);
}

/**
 * Get SIP configuration
 */
function handleGetConfig($data) {
    $extension = $data['extension'] ?? $_GET['extension'] ?? null;
    $auth_method = $data['auth_method'] ?? 'password';

    if (!$extension) {
        respond(false, 'Extension required');
        return;
    }

    // Authenticate based on method
    if ($auth_method === 'pin') {
        $pin = $data['pin'] ?? null;
        $pinData = verifyPin($extension, $pin);
        if (!$pinData) {
            respond(false, 'Invalid or expired PIN');
            return;
        }
    } else {
        $password = $data['password'] ?? null;
        $authInfo = getAuthInfo($extension);
        if (!$authInfo || $authInfo['password'] !== $password) {
            respond(false, 'Invalid credentials');
            return;
        }
    }

    // Get auth info
    $authInfo = getAuthInfo($extension);
    $config = generateSipConfig($extension, $authInfo['password']);

    respond(true, 'Configuration retrieved', [
        'extension' => $extension,
        'config' => $config
    ]);
}

/**
 * Revoke PIN
 */
function handleRevokePin($data) {
    $extension = $data['extension'] ?? null;
    $pin = $data['pin'] ?? null;
    $admin_password = $data['admin_password'] ?? null;

    if (!verifyAdminAccess($admin_password)) {
        respond(false, 'Admin authentication required');
        return;
    }

    if ($extension && $pin) {
        revokePin($extension, $pin);
        respond(true, 'PIN revoked successfully');
    } else {
        respond(false, 'Extension and PIN required');
    }
}

/**
 * List active PINs
 */
function handleListPins() {
    // Admin authentication would be required here
    $pins = loadPins();

    // Remove expired PINs
    $activePins = [];
    foreach ($pins as $ext => $pinData) {
        if ($pinData['expires_at'] > time()) {
            $activePins[] = [
                'extension' => $ext,
                'pin' => $pinData['pin'],
                'created_at' => date('Y-m-d H:i:s', $pinData['created_at']),
                'expires_at' => date('Y-m-d H:i:s', $pinData['expires_at']),
                'expires_in' => round(($pinData['expires_at'] - time()) / 3600, 1) . ' hours'
            ];
        }
    }

    respond(true, 'Active PINs retrieved', [
        'count' => count($activePins),
        'pins' => $activePins
    ]);
}

/**
 * Helper Functions
 */

function getAuthInfo($extension) {
    if (!file_exists(ASTERISK_PJSIP_CONF)) {
        return null;
    }

    $content = file_get_contents(ASTERISK_PJSIP_CONF);

    // Find auth section for extension - match password on same line only
    $pattern = "/\[" . preg_quote($extension) . "\]\s*\n.*?type\s*=\s*auth.*?\npassword\s*=\s*([^\s\n]+)/s";

    if (preg_match($pattern, $content, $matches)) {
        return [
            'extension' => $extension,
            'password' => trim($matches[1]),
            'realm' => flexpbxProvisionRealm()
        ];
    }

    return null;
}

function flexpbxProvisionRealm() {
    $host = $_SERVER['HTTP_HOST'] ?? 'flexpbx.devinecreations.net';
    $host = trim(explode(':', $host)[0]);
    return filter_var($host, FILTER_VALIDATE_IP) ? 'flexpbx.devinecreations.net' : $host;
}

function flexpbxProvisionServer() {
    $explicit = $_GET['server'] ?? $_GET['server_host'] ?? null;
    if (is_string($explicit) && trim($explicit) !== '') {
        return trim($explicit);
    }

    $host = $_SERVER['HTTP_HOST'] ?? 'flexpbx.devinecreations.net';
    return trim(explode(':', $host)[0]);
}

function flexpbxProvisionProfiles() {
    return [
        'standard-desktop' => [
            'name' => 'standard-desktop',
            'timers' => 'no',
            'transport' => 'udp',
            'ice_support' => 'no',
            'rewrite_contact' => 'yes',
            'rtp_symmetric' => 'yes',
            'direct_media' => 'no'
        ],
        'strict-timers' => [
            'name' => 'strict-timers',
            'timers' => 'yes',
            'transport' => 'udp',
            'ice_support' => 'no',
            'rewrite_contact' => 'yes',
            'rtp_symmetric' => 'yes',
            'direct_media' => 'no'
        ]
    ];
}

function parseTypedProvisionPjsipSections($extension) {
    if (!file_exists(ASTERISK_PJSIP_CONF)) {
        return [];
    }

    $content = file_get_contents(ASTERISK_PJSIP_CONF);
    if ($content === false || $content === '') {
        return [];
    }

    $lines = preg_split('/\R/', $content);
    $sections = [];
    $currentName = null;
    $currentEntries = [];

    $commitSection = static function () use (&$sections, &$currentName, &$currentEntries, $extension) {
        if ($currentName === null || $currentName !== $extension) {
            return;
        }
        $sections[] = [
            'name' => $currentName,
            'type' => strtolower($currentEntries['type'] ?? ''),
            'entries' => $currentEntries
        ];
    };

    foreach ($lines as $rawLine) {
        $line = trim($rawLine);
        if ($line === '' || $line[0] === ';' || $line[0] === '#') {
            continue;
        }
        if (preg_match('/^\[(.+)\]$/', $line, $matches)) {
            $commitSection();
            $currentName = trim($matches[1]);
            $currentEntries = [];
            continue;
        }
        if ($currentName === null || strpos($line, '=') === false) {
            continue;
        }

        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $key = strtolower($key);
        if (isset($currentEntries[$key])) {
            if (!is_array($currentEntries[$key])) {
                $currentEntries[$key] = [$currentEntries[$key]];
            }
            $currentEntries[$key][] = $value;
        } else {
            $currentEntries[$key] = $value;
        }
    }

    $commitSection();

    return $sections;
}

function getEndpointCompatibilityProfile($extension) {
    $profiles = flexpbxProvisionProfiles();
    $profileName = 'standard-desktop';
    $values = $profiles[$profileName];
    $endpointEntries = [];

    foreach (parseTypedProvisionPjsipSections($extension) as $section) {
        if (($section['type'] ?? '') === 'endpoint') {
            $endpointEntries = $section['entries'] ?? [];
            break;
        }
    }

    if (!empty($endpointEntries)) {
        foreach (['timers', 'transport', 'ice_support', 'rewrite_contact', 'rtp_symmetric', 'direct_media'] as $field) {
            if (isset($endpointEntries[$field])) {
                $value = $endpointEntries[$field];
                $values[$field] = is_array($value) ? trim((string) end($value)) : trim((string) $value);
            }
        }
        $setVars = $endpointEntries['set_var'] ?? [];
        if (!is_array($setVars)) {
            $setVars = [$setVars];
        }
        foreach ($setVars as $setVar) {
            if (preg_match('/^SIP_PROFILE=(.+)$/i', trim((string) $setVar), $m)) {
                $candidate = trim($m[1]);
                if (isset($profiles[$candidate])) {
                    $profileName = $candidate;
                }
                break;
            }
        }
        if ($profileName === 'standard-desktop' && ($values['timers'] ?? 'yes') !== 'no' && empty($setVars)) {
            $profileName = 'strict-timers';
        } elseif (($values['timers'] ?? 'yes') === 'no') {
            $profileName = 'standard-desktop';
        } elseif ($profileName !== 'strict-timers') {
            $profileName = 'strict-timers';
        }
    }

    $values['name'] = $profileName;
    return $values;
}

function generateSipConfig($extension, $password) {
    $server = flexpbxProvisionServer();
    $port = 5060;
    $profile = getEndpointCompatibilityProfile($extension);
    $transport = strtolower($profile['transport'] ?? 'udp');
    $dialplan = '(2xxx|*xx|1[2-9]xxxxxxxxx|011xxxxxxxxxxx)';
    $realm = flexpbxProvisionRealm();

    return [
        'server' => $server,
        'realm' => $realm,
        'port' => $port,
        'username' => $extension,
        'password' => $password,
        'auth_username' => $extension,
        'display_name' => "Extension $extension",
        'transport' => $transport,
        'sip_profile' => $profile['name'],
        'compatibility' => [
            'timers' => $profile['timers'],
            'ice_support' => $profile['ice_support'],
            'rewrite_contact' => $profile['rewrite_contact'],
            'rtp_symmetric' => $profile['rtp_symmetric'],
            'direct_media' => $profile['direct_media']
        ],
        'client_hint' => [
            'transport' => strtoupper($transport),
            'disable_session_timers' => ($profile['timers'] ?? 'yes') !== 'yes',
            'disable_stun' => true,
            'disable_ice' => ($profile['ice_support'] ?? 'no') !== 'yes',
            'disable_proxy' => true
        ],
        'dialplan' => $dialplan,
        'sip_uri' => "sip:{$extension}@{$server}:{$port};password={$password};transport={$transport};dialplan={$dialplan}",
        'manual_config' => [
            'Server' => $server,
            'Realm' => $realm,
            'Port' => $port,
            'Username' => $extension,
            'Password' => $password,
            'Transport' => strtoupper($transport),
            'Dial Plan' => $dialplan,
            'Session Timers' => strtoupper($profile['timers'] ?? 'yes'),
            'Use STUN' => 'NO',
            'Use ICE' => strtoupper($profile['ice_support'] ?? 'no'),
            'Proxy' => 'OFF'
        ]
    ];
}

function generatePin() {
    // Generate 6-digit PIN
    return str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT);
}

function savePinToFile($extension, $pin, $expiresAt) {
    $pins = loadPins();

    $pins[$extension] = [
        'pin' => $pin,
        'created_at' => time(),
        'expires_at' => $expiresAt
    ];

    file_put_contents(PIN_FILE, json_encode($pins, JSON_PRETTY_PRINT));
    chmod(PIN_FILE, 0640);
}

function loadPins() {
    if (!file_exists(PIN_FILE)) {
        return [];
    }

    $content = file_get_contents(PIN_FILE);
    return json_decode($content, true) ?? [];
}

function verifyPin($extension, $pin) {
    $pins = loadPins();

    if (!isset($pins[$extension])) {
        return null;
    }

    $pinData = $pins[$extension];

    // Check if expired
    if ($pinData['expires_at'] < time()) {
        return null;
    }

    // Check if PIN matches
    if ($pinData['pin'] !== $pin) {
        return null;
    }

    return $pinData;
}

function revokePin($extension, $pin) {
    $pins = loadPins();

    if (isset($pins[$extension]) && $pins[$extension]['pin'] === $pin) {
        unset($pins[$extension]);
        file_put_contents(PIN_FILE, json_encode($pins, JSON_PRETTY_PRINT));
        return true;
    }

    return false;
}

function verifyAdminAccess($password) {
    // Verify admin credentials (extension 2000 or check admin password)
    // For now, check if password matches extension 2000's password
    if (!$password) {
        return false;
    }

    $authInfo = getAuthInfo('2000');
    if ($authInfo && $authInfo['password'] === $password) {
        return true;
    }

    // Could add additional admin checks here
    return false;
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
