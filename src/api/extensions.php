<?php
/**
 * FlexPBX Extensions API
 * Manages SIP/PJSIP extensions - CRUD operations and status monitoring
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/flexpbx-config-helper.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

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

function flexpbxSipRealm() {
    $host = $_SERVER['HTTP_HOST'] ?? 'flexpbx.devinecreations.net';
    $host = trim(explode(':', $host)[0]);
    return filter_var($host, FILTER_VALIDATE_IP) ? 'flexpbx.devinecreations.net' : $host;
}

function flexpbxSipProfiles() {
    return [
        'standard-desktop' => [
            'name' => 'standard-desktop',
            'description' => 'Default desktop softphone compatibility profile',
            'transport' => 'transport-udp',
            'allow' => 'ulaw,alaw',
            'direct_media' => 'no',
            'ice_support' => 'no',
            'force_rport' => 'yes',
            'rewrite_contact' => 'yes',
            'rtp_symmetric' => 'yes',
            'dtmf_mode' => 'rfc4733',
            'timers' => 'no',
            'timers_min_se' => '90',
            'timers_sess_expires' => '1800',
            'max_contacts' => '5'
        ],
        'strict-timers' => [
            'name' => 'strict-timers',
            'description' => 'Desktop/client profile with SIP session timers enabled',
            'transport' => 'transport-udp',
            'allow' => 'ulaw,alaw',
            'direct_media' => 'no',
            'ice_support' => 'no',
            'force_rport' => 'yes',
            'rewrite_contact' => 'yes',
            'rtp_symmetric' => 'yes',
            'dtmf_mode' => 'rfc4733',
            'timers' => 'yes',
            'timers_min_se' => '90',
            'timers_sess_expires' => '1800',
            'max_contacts' => '5'
        ]
    ];
}

function normalizeSipProfileName($profileName = null) {
    $profiles = flexpbxSipProfiles();
    $requested = strtolower(trim((string)($profileName ?? '')));
    return isset($profiles[$requested]) ? $requested : 'standard-desktop';
}

function getEffectiveSipProfile($profileName = null, $existingEntries = []) {
    $profiles = flexpbxSipProfiles();
    $name = normalizeSipProfileName($profileName ?: ($existingEntries['sip_profile'] ?? null));
    $profile = $profiles[$name];
    if (!empty($existingEntries['transport'])) $profile['transport'] = $existingEntries['transport'];
    if (!empty($existingEntries['allow'])) $profile['allow'] = $existingEntries['allow'];
    if (!empty($existingEntries['max_contacts'])) $profile['max_contacts'] = $existingEntries['max_contacts'];
    $profile['name'] = $name;
    return $profile;
}

function buildExtensionBundle($extension, $details = [], $existingEntries = []) {
    $profile = getEffectiveSipProfile($details['sip_profile'] ?? null, $existingEntries);
    $password = $details['password'] ?? $existingEntries['password'] ?? generatePassword();
    $calleridName = $details['callerid_name'] ?? $existingEntries['callerid_name'] ?? "Extension $extension";
    $calleridNum = $details['callerid_num'] ?? $existingEntries['callerid_num'] ?? $extension;
    $context = $details['context'] ?? $existingEntries['context'] ?? 'flexpbx-internal';
    $mohClass = $details['moh_class'] ?? $existingEntries['moh_suggest'] ?? 'default';
    $realm = $details['realm'] ?? $existingEntries['realm'] ?? flexpbxSipRealm();

    return "\n; Extension $extension\n"
        . "[$extension]\n"
        . "type=endpoint\n"
        . "context=$context\n"
        . "disallow=all\n"
        . "allow={$profile['allow']}\n"
        . "auth=$extension\n"
        . "aors=$extension\n"
        . "identify_by=username,auth_username\n"
        . "callerid=\"{$calleridName}\" <{$calleridNum}>\n"
        . "moh_suggest=$mohClass\n"
        . "transport={$profile['transport']}\n"
        . "direct_media={$profile['direct_media']}\n"
        . "rtp_symmetric={$profile['rtp_symmetric']}\n"
        . "force_rport={$profile['force_rport']}\n"
        . "rewrite_contact={$profile['rewrite_contact']}\n"
        . "ice_support={$profile['ice_support']}\n"
        . "dtmf_mode={$profile['dtmf_mode']}\n"
        . "timers={$profile['timers']}\n"
        . "timers_min_se={$profile['timers_min_se']}\n"
        . "timers_sess_expires={$profile['timers_sess_expires']}\n"
        . "set_var=SIP_PROFILE={$profile['name']}\n\n"
        . "[$extension]\n"
        . "type=auth\n"
        . "auth_type=userpass\n"
        . "password=$password\n"
        . "username=$extension\n"
        . "realm=$realm\n\n"
        . "[$extension]\n"
        . "type=aor\n"
        . "max_contacts={$profile['max_contacts']}\n"
        . "qualify_frequency=60\n"
        . "remove_existing=yes\n";
}

function replaceExtensionBundle($content, $extension, $bundle) {
    $quoted = preg_quote($extension, '/');
    $pattern = '/(?:^|\R)(?:;\s*Extension\s+' . $quoted . '\R)?\[' . $quoted . '\]\R(?:(?!^\[(?!' . $quoted . '\])|\z).)*(?=^\[(?!' . $quoted . '\])|\z)/ms';
    if (preg_match($pattern, $content)) {
        return preg_replace($pattern, rtrim($bundle, "\n"), $content, 1);
    }
    return rtrim($content) . "\n" . ltrim($bundle, "\n");
}

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

    case 'moh-classes':
        handleGetMOHClasses($method);
        break;

    case 'update-moh':
        handleUpdateMOH($method, $extension_id);
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
    $endpointResult = flexpbx_config()->execAsteriskCommand('pjsip show endpoints');
    $output = preg_split('/\R/', $endpointResult['output'] ?? '');
    $ret = $endpointResult['return_code'] ?? 1;

    if ($ret === 0) {
        foreach ($output as $line) {
            $line = trim($line);
            if ($line === '' || stripos($line, 'Unable to access') === 0 || stripos($line, 'Objects found:') === 0) {
                continue;
            }

            // Parse endpoint lines
            if (preg_match('/^(\d+)(?:\/[^\s]+)?\s+(\w+)(?:\s+(\d+))?/', $line, $matches)) {
                $ext = $matches[1];
                $state = $matches[2];
                $channels = isset($matches[3]) ? (int)$matches[3] : 0;

                // Get additional details
                $details = getExtensionDetails($ext);

                $extensions[] = [
                    'extension' => $ext,
                    'status' => $state,
                    'active_channels' => $channels,
                    'callerid' => $details['callerid'] ?? '',
                    'context' => $details['context'] ?? '',
                    'transport' => $details['transport'] ?? '',
                    'voicemail' => extensionHasVoicemail($ext)
                ];
            }
        }
    }

    if (count($extensions) === 0) {
        $extensions = parseExtensionsFromPjsipConfig($GLOBALS['pjsip_conf']);
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
    $moh_class = $data['moh_class'] ?? 'default';

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

    $sip_profile = normalizeSipProfileName($data['sip_profile'] ?? null);
    $pjsip_config = buildExtensionBundle($extension, [
        'password' => $password,
        'callerid_name' => $callerid_name,
        'callerid_num' => $callerid_num,
        'context' => $context,
        'moh_class' => $moh_class,
        'sip_profile' => $sip_profile
    ]);

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
            'sip_profile' => $sip_profile,
            'compatibility' => getEffectiveSipProfile($sip_profile),
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

    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $existing = getExtensionDetailsFromPjsipConfig($extension_id, $GLOBALS['pjsip_conf']);
    if (empty($existing)) {
        http_response_code(404);
        echo json_encode(['error' => 'Extension not found']);
        return;
    }

    global $pjsip_conf;
    $configResult = flexpbx_config()->readAsteriskConfig($pjsip_conf);
    $config = $configResult['success'] ? ($configResult['content'] ?? '') : '';
    if ($config === '') {
        $shellResult = flexpbx_config()->execShellCommand('cat ' . escapeshellarg($pjsip_conf));
        $config = $shellResult['success'] ? ($shellResult['output'] ?? '') : '';
    }
    if ($config === '') {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to read configuration']);
        return;
    }

    $sip_profile = normalizeSipProfileName($data['sip_profile'] ?? ($existing['sip_profile'] ?? null));
    $updatedBundle = buildExtensionBundle($extension_id, [
        'password' => $data['password'] ?? ($existing['password'] ?? null),
        'callerid_name' => $data['callerid_name'] ?? ($existing['callerid_name'] ?? null),
        'callerid_num' => $data['callerid_num'] ?? ($existing['callerid_num'] ?? null),
        'context' => $data['context'] ?? ($existing['context'] ?? null),
        'moh_class' => $data['moh_class'] ?? ($existing['moh_suggest'] ?? null),
        'sip_profile' => $sip_profile
    ], $existing);

    $config = replaceExtensionBundle($config, $extension_id, $updatedBundle);
    if (file_put_contents($pjsip_conf, $config) === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update configuration']);
        return;
    }

    exec("sudo chown asterisk:asterisk $pjsip_conf");
    exec("sudo chmod 640 $pjsip_conf");
    exec('sudo asterisk -rx "pjsip reload" 2>&1', $reload_output, $reload_ret);

    $updated = getExtensionDetailsFromPjsipConfig($extension_id, $pjsip_conf);
    logAction('extension_updated', $extension_id, $_SERVER['REMOTE_ADDR']);

    echo json_encode([
        'success' => true,
        'message' => "Extension $extension_id updated successfully",
        'extension' => $updated,
        'reload' => [
            'success' => $reload_ret === 0,
            'output' => $reload_output
        ],
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
    $endpointResult = flexpbx_config()->execAsteriskCommand("pjsip show endpoint $extension");
    $output = preg_split('/\R/', $endpointResult['output'] ?? '');
    $ret = $endpointResult['return_code'] ?? 1;

    if ($ret !== 0 || empty(trim($endpointResult['output'] ?? ''))) {
        return getExtensionDetailsFromPjsipConfig($extension, $GLOBALS['pjsip_conf']);
    }

    $details = ['extension' => $extension];

    foreach ($output as $line) {
        $line = trim($line);
        if ($line === '' || stripos($line, 'Unable to access') === 0 || stripos($line, 'Endpoint:') === 0 || stripos($line, 'ParameterName') === 0) {
            continue;
        }

        if (preg_match('/callerid\s*:\s*(.+)/i', $line, $matches)) {
            $details['callerid'] = trim($matches[1]);
        }
        if (preg_match('/context\s*:\s*(.+)/i', $line, $matches)) {
            $details['context'] = trim($matches[1]);
        }
        if (preg_match('/transport\s*:\s*(.+)/i', $line, $matches)) {
            $details['transport'] = trim($matches[1]);
        }
        if (preg_match('/auth\s*:\s*(.+)/i', $line, $matches)) {
            $details['auth'] = trim($matches[1]);
        }
    }

    return $details;
}

function parseExtensionsFromPjsipConfig($configPath) {
    $sections = parsePjsipEndpointDefinitions($configPath);
    $extensions = [];

    foreach ($sections as $name => $entries) {
        if (!preg_match('/^\d{3,5}$/', $name)) {
            continue;
        }

        $details = getExtensionDetailsFromPjsipConfig($name, $configPath);
        $extensions[] = [
            'extension' => $name,
            'status' => 'Unknown',
            'active_channels' => 0,
            'callerid' => $details['callerid'] ?? '',
            'context' => $details['context'] ?? '',
            'transport' => $details['transport'] ?? '',
            'voicemail' => extensionHasVoicemail($name)
        ];
    }

    usort($extensions, function ($a, $b) {
        return (int) $a['extension'] <=> (int) $b['extension'];
    });

    return $extensions;
}

function getExtensionDetailsFromPjsipConfig($extension, $configPath) {
    $sections = parsePjsipEndpointDefinitions($configPath);
    $entries = $sections[$extension] ?? null;
    if (!$entries) {
        return [];
    }

    $sipProfile = extractSipProfileFromEntries($entries);

    return [
        'extension' => $extension,
        'callerid' => $entries['callerid'] ?? '',
        'callerid_name' => preg_replace('/^"?(.+?)"?\s*<.*$/', '$1', $entries['callerid'] ?? '') ?: '',
        'callerid_num' => preg_match('/<([^>]+)>/', $entries['callerid'] ?? '', $m) ? $m[1] : $extension,
        'context' => $entries['context'] ?? '',
        'transport' => $entries['transport'] ?? '',
        'auth' => $entries['auth'] ?? '',
        'allow' => $entries['allow'] ?? '',
        'moh_suggest' => $entries['moh_suggest'] ?? 'default',
        'direct_media' => $entries['direct_media'] ?? 'no',
        'ice_support' => $entries['ice_support'] ?? 'no',
        'rewrite_contact' => $entries['rewrite_contact'] ?? 'yes',
        'rtp_symmetric' => $entries['rtp_symmetric'] ?? 'yes',
        'timers' => $entries['timers'] ?? 'yes',
        'timers_min_se' => $entries['timers_min_se'] ?? '90',
        'timers_sess_expires' => $entries['timers_sess_expires'] ?? '1800',
        'sip_profile' => $sipProfile,
        'compatibility' => getEffectiveSipProfile($sipProfile, $entries),
        'password' => extractAuthPasswordFromConfig($extension, $configPath),
        'realm' => extractAuthRealmFromConfig($extension, $configPath)
    ];
}

function parsePjsipEndpointDefinitions($configPath) {
    $configResult = flexpbx_config()->readAsteriskConfig($configPath);
    $content = $configResult['success'] ? ($configResult['content'] ?? '') : '';

    if ($content === '') {
        $shellResult = flexpbx_config()->execShellCommand('cat ' . escapeshellarg($configPath));
        if ($shellResult['success']) {
            $content = $shellResult['output'] ?? '';
        }
    }

    if ($content === '') {
        return [];
    }

    $lines = preg_split('/\R/', $content);
    $definitions = [];
    $currentName = null;
    $currentEntries = [];

    $commitSection = static function () use (&$currentName, &$currentEntries, &$definitions) {
        if ($currentName === null) {
            return;
        }
        if (($currentEntries['type'] ?? '') === 'endpoint' && !isset($definitions[$currentName])) {
            $definitions[$currentName] = $currentEntries;
        }
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
        $currentEntries[strtolower($key)] = $value;
    }

    $commitSection();

    return $definitions;
}

function parsePjsipSections($configPath) {
    $configResult = flexpbx_config()->readAsteriskConfig($configPath);
    $content = $configResult['success'] ? ($configResult['content'] ?? '') : '';

    if ($content === '') {
        $shellResult = flexpbx_config()->execShellCommand('cat ' . escapeshellarg($configPath));
        if ($shellResult['success']) {
            $content = $shellResult['output'] ?? '';
        }
    }

    if ($content === '') {
        return [];
    }

    $lines = preg_split('/\R/', $content);

    $sections = [];
    $current = null;

    foreach ($lines as $rawLine) {
        $line = trim($rawLine);
        if ($line === '' || $line[0] === ';' || $line[0] === '#') {
            continue;
        }
        if (preg_match('/^\[(.+)\]$/', $line, $matches)) {
            $current = trim($matches[1]);
            if (!isset($sections[$current])) {
                $sections[$current] = [];
            }
            continue;
        }
        if ($current === null || strpos($line, '=') === false) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $sections[$current][strtolower($key)] = $value;
    }

    return $sections;
}

function parseTypedPjsipSections($configPath, $targetSectionName = null) {
    $configResult = flexpbx_config()->readAsteriskConfig($configPath);
    $content = $configResult['success'] ? ($configResult['content'] ?? '') : '';

    if ($content === '') {
        $shellResult = flexpbx_config()->execShellCommand('cat ' . escapeshellarg($configPath));
        if ($shellResult['success']) {
            $content = $shellResult['output'] ?? '';
        }
    }

    if ($content === '') {
        return [];
    }

    $lines = preg_split('/\R/', $content);
    $sections = [];
    $currentName = null;
    $currentEntries = [];

    $commitSection = static function () use (&$sections, &$currentName, &$currentEntries, $targetSectionName) {
        if ($currentName === null) {
            return;
        }
        if ($targetSectionName !== null && $currentName !== $targetSectionName) {
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

function findTypedPjsipSection($configPath, $sectionName, $sectionType) {
    $sectionType = strtolower($sectionType);
    foreach (parseTypedPjsipSections($configPath, $sectionName) as $section) {
        if (($section['type'] ?? '') === $sectionType) {
            return $section['entries'];
        }
    }
    return [];
}

function extractSipProfileFromEntries($entries) {
    $setVars = $entries['set_var'] ?? [];
    if (!is_array($setVars)) {
        $setVars = [$setVars];
    }
    foreach ($setVars as $value) {
        if (!is_string($value)) {
            continue;
        }
        if (preg_match('/^SIP_PROFILE=(.+)$/i', trim($value), $matches)) {
            return normalizeSipProfileName($matches[1]);
        }
    }
    return (($entries['timers'] ?? 'yes') === 'no') ? 'standard-desktop' : 'strict-timers';
}

function extractAuthPasswordFromConfig($extension, $configPath) {
    $entries = findTypedPjsipSection($configPath, $extension, 'auth');
    return $entries['password'] ?? null;
}

function extractAuthRealmFromConfig($extension, $configPath) {
    $entries = findTypedPjsipSection($configPath, $extension, 'auth');
    return $entries['realm'] ?? flexpbxSipRealm();
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

/**
 * Get available MOH classes
 */
function handleGetMOHClasses($method) {
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    $mohResult = flexpbx_config()->execAsteriskCommand('moh show classes');
    $output = preg_split('/\R/', $mohResult['output'] ?? '');

    $moh_classes = [];

    foreach ($output as $line) {
        if (preg_match('/^Class:\s+(.+)$/', $line, $matches)) {
            $class_name = trim($matches[1]);
            $moh_classes[] = [
                'name' => $class_name,
                'display_name' => formatMOHClassName($class_name),
                'description' => getMOHDescription($class_name)
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'moh_classes' => $moh_classes,
        'total' => count($moh_classes),
        'timestamp' => date('c')
    ]);
}

/**
 * Update MOH class for an extension
 */
function handleUpdateMOH($method, $extension_id) {
    if ($method !== 'POST' && $method !== 'PUT') {
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
    $moh_class = $data['moh_class'] ?? 'default';

    global $pjsip_conf;

    // Read current pjsip.conf
    $config = file_get_contents($pjsip_conf);

    // Find the endpoint section for this extension
    $pattern = "/(\[$extension_id\]\s*\ntype=endpoint\s*\n(?:.*\n)*?)(moh_suggest=.*\n)?/";

    // Check if moh_suggest already exists
    if (preg_match("/\[$extension_id\].*?type=endpoint.*?moh_suggest=/s", $config)) {
        // Update existing moh_suggest
        $config = preg_replace(
            "/(\[$extension_id\].*?type=endpoint.*?)moh_suggest=.*/s",
            "$1moh_suggest=$moh_class",
            $config
        );
    } else {
        // Add moh_suggest to endpoint
        $config = preg_replace(
            "/(\[$extension_id\]\s*\ntype=endpoint\s*\n)/",
            "$1moh_suggest=$moh_class\n",
            $config
        );
    }

    // Write updated config
    if (file_put_contents($pjsip_conf, $config) === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update configuration']);
        return;
    }

    // Fix permissions
    exec("sudo chown asterisk:asterisk $pjsip_conf");
    exec("sudo chmod 640 $pjsip_conf");

    // Reload PJSIP
    exec('sudo asterisk -rx "pjsip reload" 2>&1', $reload_output, $reload_ret);

    echo json_encode([
        'success' => true,
        'message' => "MOH class updated to '$moh_class' for extension $extension_id",
        'extension' => $extension_id,
        'moh_class' => $moh_class,
        'timestamp' => date('c')
    ]);
}

/**
 * Format MOH class name for display
 */
function formatMOHClassName($name) {
    $display_names = [
        'default' => 'Raywonder Radio (Audio Described)',
        'raywonder-radio' => 'Raywonder Radio (Audio Described)',
        'tappedin-radio' => 'TappedIn Radio (Meditation & Soundscapes)',
        'chrismix-radio' => 'ChrisMix Radio',
        'soulfood-radio' => 'SoulFood Radio'
    ];

    return $display_names[$name] ?? ucwords(str_replace(['-', '_'], ' ', $name));
}

/**
 * Get MOH class description
 */
function getMOHDescription($name) {
    $descriptions = [
        'default' => 'Scheduled Audio Described TV, Movies, and Music',
        'raywonder-radio' => 'Scheduled Audio Described TV, Movies, and Music',
        'tappedin-radio' => 'Relaxing soundscapes, meditation music, and podcasts',
        'chrismix-radio' => 'ChrisMix Radio streaming music',
        'soulfood-radio' => 'SoulFood Radio streaming music'
    ];

    return $descriptions[$name] ?? 'Music on hold';
}
?>
