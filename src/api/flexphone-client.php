<?php
/**
 * Flex Phone control API.
 *
 * Server-backed actions for the native Flex Phone client: waiting calls,
 * voicemail, server recordings, presence, and device pairing codes.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, false, 'Use POST for Flex Phone actions.');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    respond(400, false, 'Invalid JSON request.');
}

$action = strtolower(trim((string)($input['action'] ?? '')));
if (in_array($action, ['request_extension', 'complete_device_authorization'], true)) {
    deviceAuthorization($action, $input);
}

$session = requireSession();
$extension = trim((string)($input['extension'] ?? $session['extension'] ?? ''));
if ($extension === '' || !preg_match('/^\d{3,6}$/', $extension)) {
    respond(400, false, 'Choose a valid extension.');
}

if (($session['extension'] ?? '') !== $extension && !isAdminRole((string)($session['role'] ?? ''))) {
    respond(403, false, 'That extension is not available from this login.');
}

switch ($action) {
    case 'pairing_code':
        createPairingCode($extension, $session);
        break;
    case 'waiting_calls':
        waitingCalls($extension);
        break;
    case 'presence':
        presence();
        break;
    case 'presence_action':
        presenceAction($extension, $input);
        break;
    case 'voicemail':
        voicemail($extension);
        break;
    case 'recordings':
        recordings($extension);
        break;
    case 'toggle_recording':
        toggleRecording($extension);
        break;
    case 'device_status':
        deviceStatus($extension, $session, $input);
        break;
    case 'list_devices':
        listDevices($session);
        break;
    default:
        respond(400, false, 'Unsupported Flex Phone action.');
}

function deviceAuthorization($action, $input) {
    $email = strtolower(trim((string)($input['email'] ?? '')));
    $deviceId = sanitizeDeviceId((string)($input['device_id'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $deviceId === '') {
        respond(400, false, 'Enter a valid email address before linking Flex Phone.');
    }

    if ($action === 'complete_device_authorization') {
        completeDeviceAuthorization($email, $deviceId, $input);
    }

    $user = findUserByEmail($email);
    $token = bin2hex(random_bytes(24));
    $url = publicBaseUrl() . '/flexphone/link?token=' . urlencode($token) . '&email=' . urlencode($email);
    $record = [
        'token_hash' => hash('sha256', $token),
        'email' => $email,
        'old_email' => (string)($user['email'] ?? ''),
        'extension' => (string)($user['extension'] ?? $user['extension_number'] ?? ''),
        'username' => (string)($user['username'] ?? ''),
        'device_id' => $deviceId,
        'confirmed' => false,
        'created' => time(),
        'expires' => time() + 1800,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? ''
    ];
    writeDeviceAuthorization($token, $record);

    @mail(
        $email,
        'Confirm your Flex Phone sign in',
        buildDeviceAuthorizationEmail($url, $email, $user),
        'From: Flex PBX <noreply@' . mailDomain() . ">\r\n" .
        'Content-Type: text/plain; charset=UTF-8'
    );

    $extra = [
        'email' => $email,
        'old_email' => (string)($user['email'] ?? ''),
        'token_url' => $url,
        'authorization_url' => $url,
        'portal_url' => publicBaseUrl() . '/user-portal/',
        'signup_url' => publicBaseUrl() . '/user-portal/signup.php?email=' . urlencode($email) . '&source=flexphone',
        'device_id' => $deviceId,
        'expires_at' => date('c', $record['expires'])
    ];

    respond(200, true, 'Confirmation email sent. Open the email link to authorize this device, then return to Flex Phone and choose Finish sign in.', $extra);
}

function completeDeviceAuthorization($email, $deviceId, $input) {
    $token = authorizationTokenFromInput($input);
    if ($token === '') {
        respond(400, false, 'Open the email confirmation link first, then choose Finish sign in from Flex Phone.');
    }

    $record = readDeviceAuthorization($token);
    if (!is_array($record)) {
        respond(404, false, 'That Flex Phone confirmation link is invalid or has expired. Request a new email link.');
    }

    if ((int)($record['expires'] ?? 0) < time()) {
        deleteDeviceAuthorization($token);
        respond(410, false, 'That Flex Phone confirmation link has expired. Request a new email link.');
    }

    if (!hash_equals(strtolower((string)($record['email'] ?? '')), $email)) {
        respond(403, false, 'That confirmation link belongs to a different email address.');
    }

    if (!hash_equals((string)($record['device_id'] ?? ''), $deviceId)) {
        respond(403, false, 'That confirmation link was created for a different device.');
    }

    if (empty($record['confirmed'])) {
        respond(409, false, 'Check your email and open the Flex Phone confirmation link before finishing sign in.', [
            'token_url' => publicBaseUrl() . '/flexphone/link?token=' . urlencode($token) . '&email=' . urlencode($email),
            'authorization_url' => publicBaseUrl() . '/flexphone/link?token=' . urlencode($token) . '&email=' . urlencode($email)
        ]);
    }

    $user = findUserByEmail($email);
    if (!is_array($user)) {
        respond(404, false, 'Your email is confirmed, but no extension account is assigned yet. Complete the user portal sign-up or ask an administrator to approve the extension.', [
            'signup_url' => publicBaseUrl() . '/user-portal/signup.php?email=' . urlencode($email) . '&source=flexphone'
        ]);
    }

    $extension = (string)($user['extension'] ?? $user['extension_number'] ?? '');
    $username = (string)($user['username'] ?? $extension);
    $role = (string)($user['role'] ?? 'user');
    $sipPassword = (string)($user['sip_password'] ?? $user['extension_password'] ?? $user['secret'] ?? '');
    if ($extension === '' || $sipPassword === '') {
        respond(409, false, 'Your email is confirmed, but this extension is missing phone credentials. Open the user portal to finish password setup or contact an administrator.', [
            'portal_url' => publicBaseUrl() . '/user-portal/',
            'password_setup_url' => publicBaseUrl() . '/user-portal/forgot-password.php'
        ]);
    }

    $record['completed'] = time();
    writeDeviceAuthorization($token, $record);

    respond(200, true, 'Email confirmed. Flex Phone can sign in now.', [
        'email' => $email,
        'old_email' => (string)($record['old_email'] ?? ''),
        'extension' => $extension,
        'username' => $username,
        'full_name' => (string)($user['full_name'] ?? $user['display_name'] ?? ''),
        'role' => $role,
        'sip_password' => $sipPassword,
        'session_token' => issueSessionToken($extension, $username, $role, 'Flex Phone'),
        'portal_url' => publicBaseUrl() . '/user-portal/',
        'sip_settings' => flexPhoneSipSettings()
    ]);
}

function deviceStatus($extension, $session, $input) {
    $dir = getenv('FLEXPBX_DEVICE_STATUS_DIR') ?: '/home/flexpbxuser/device_status';
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }

    $remoteIp = $_SERVER['REMOTE_ADDR'] ?? '';
    $deviceId = sanitizeDeviceId((string)($input['device_id'] ?? 'unknown'));
    $record = [
        'extension' => $extension,
        'username' => (string)($session['username'] ?? $extension),
        'device_id' => $deviceId,
        'device_name' => safeText((string)($input['device_name'] ?? 'Unknown device'), 80),
        'app_version' => safeText((string)($input['app_version'] ?? ''), 32),
        'os' => safeText((string)($input['os'] ?? ''), 120),
        'ip' => $remoteIp,
        'network_type' => classifyNetwork($remoteIp),
        'user_agent' => safeText($_SERVER['HTTP_USER_AGENT'] ?? '', 180),
        'queue_state' => in_array(($input['queue_state'] ?? ''), ['in', 'out'], true) ? $input['queue_state'] : 'out',
        'queue_state_changed_at' => safeText((string)($input['queue_state_changed_at'] ?? ''), 40),
        'queue_state_age_seconds' => max(0, (int)($input['queue_state_age_seconds'] ?? 0)),
        'line_count' => max(0, min(8, (int)($input['line_count'] ?? 0))),
        'active_line' => max(0, min(8, (int)($input['active_line'] ?? 0))),
        'active_call_count' => max(0, min(8, (int)($input['active_call_count'] ?? 0))),
        'registered_at' => safeText((string)($input['registered_at'] ?? ''), 40),
        'last_seen' => date('c'),
        'online' => true,
        'lines' => sanitizeLines($input['lines'] ?? [])
    ];

    @file_put_contents($dir . '/device_' . $deviceId . '.json', json_encode($record, JSON_PRETTY_PRINT), LOCK_EX);
    @chmod($dir . '/device_' . $deviceId . '.json', 0640);
    respond(200, true, 'Device status updated.');
}

function listDevices($session) {
    if (!isAdminRole((string)($session['role'] ?? ''))) {
        respond(403, false, 'Admin access is required.');
    }

    respond(200, true, 'Devices loaded.', ['devices' => loadDeviceRecords()]);
}

function requireSession() {
    global $input;

    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    $token = '';
    if (preg_match('/Bearer\s+([A-Fa-f0-9]{64})/', $header, $matches)) {
        $token = $matches[1];
    } elseif (is_array($input) && preg_match('/^[A-Fa-f0-9]{64}$/', (string)($input['session_token'] ?? ''))) {
        $token = (string)$input['session_token'];
    }

    if ($token === '') {
        respond(401, false, 'Sign in again before using this feature.');
    }

    $tokenHash = hash('sha256', $token);
    $file = sessionDir() . '/session_' . $tokenHash . '.json';
    if (!is_file($file)) {
        respond(401, false, 'Sign in again before using this feature.');
    }

    $session = json_decode((string)file_get_contents($file), true);
    if (!is_array($session) || (int)($session['expires'] ?? 0) < time()) {
        @unlink($file);
        respond(401, false, 'Sign in again before using this feature.');
    }

    $session['expires'] = time() + 86400;
    @file_put_contents($file, json_encode($session, JSON_PRETTY_PRINT), LOCK_EX);
    @chmod($file, 0640);
    return $session;
}

function sessionDir() {
    return getenv('FLEXPBX_SESSION_DIR') ?: '/home/flexpbxuser/sessions';
}

function isAdminRole($role) {
    return in_array(strtolower($role), ['admin', 'super_admin', 'administrator'], true);
}

function createPairingCode($extension, $session) {
    $code = (string)random_int(100000, 999999);
    $token = bin2hex(random_bytes(24));
    $dir = getenv('FLEXPBX_PAIRING_DIR') ?: '/home/flexpbxuser/pairing_codes';
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }

    $record = [
        'code_hash' => hash('sha256', $code),
        'token' => $token,
        'extension' => $extension,
        'username' => $session['username'] ?? $extension,
        'created' => time(),
        'expires' => time() + 600,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? ''
    ];
    @file_put_contents($dir . '/pair_' . $token . '.json', json_encode($record, JSON_PRETTY_PRINT), LOCK_EX);
    @chmod($dir . '/pair_' . $token . '.json', 0640);

    $host = $_SERVER['HTTP_HOST'] ?? 'pbx.tappedin.fm';
    respond(200, true, 'Use this pairing code in Flex Phone within 10 minutes.', [
        'pairing_code' => $code,
        'pairing_url' => 'https://' . $host . '/flexphone/link?code=' . urlencode($code) . '&token=' . urlencode($token)
    ]);
}

function waitingCalls($extension) {
    $output = asterisk('core show channels concise');
    $calls = [];
    foreach ($output as $line) {
        if (stripos($line, 'Ring') === false && stripos($line, 'Wait') === false) {
            continue;
        }
        if (stripos($line, 'PJSIP/' . $extension) === false && stripos($line, 'Queue') === false) {
            continue;
        }

        $parts = explode('!', $line);
        $number = preg_replace('/\D+/', '', $parts[7] ?? $parts[0] ?? '');
        $calls[] = [
            'display_name' => callerNameFromChannel($line),
            'number' => $number,
            'last4' => $number !== '' ? substr($number, -4) : '',
            'state' => $parts[4] ?? 'Waiting'
        ];
    }

    respond(200, true, count($calls) ? 'Calls are waiting.' : 'No calls are waiting.', ['calls' => $calls]);
}

function presence() {
    $contacts = implode("\n", asterisk('pjsip show contacts'));
    $channels = implode("\n", asterisk('core show channels concise'));
    $users = loadUsers();
    $people = [];
    foreach ($users as $user) {
        $extension = (string)($user['extension'] ?? $user['username'] ?? '');
        if ($extension === '') {
            continue;
        }
        $online = stripos($contacts, 'Aor/' . $extension) !== false || stripos($contacts, 'Contact:  ' . $extension) !== false;
        $inCall = stripos($channels, 'PJSIP/' . $extension) !== false;
        $people[] = [
            'extension' => $extension,
            'display_name' => (string)($user['full_name'] ?? $user['display_name'] ?? $user['username'] ?? $extension),
            'role' => (string)($user['role'] ?? 'user'),
            'status' => $inCall ? 'In a call' : ($online ? 'Available' : 'Offline')
        ];
    }

    respond(200, true, 'Presence loaded.', ['people' => $people]);
}

function presenceAction($extension, $input) {
    $target = trim((string)($input['target_extension'] ?? ''));
    if ($target === '' || !preg_match('/^\d{3,6}$/', $target)) {
        respond(400, false, 'Choose a valid person.');
    }

    $presenceAction = strtolower(trim((string)($input['presence_action'] ?? '')));
    $message = safeText((string)($input['message'] ?? ''), 1000);

    switch ($presenceAction) {
        case 'call':
            $result = asterisk('channel originate PJSIP/' . $extension . ' extension ' . $target . '@from-internal');
            respond(200, true, 'Call request sent.', ['details' => $result]);
            break;
        case 'intercom':
            $result = asterisk('channel originate PJSIP/' . $extension . ' extension *80' . $target . '@from-internal');
            respond(200, true, 'Intercom request sent.', ['details' => $result]);
            break;
        case 'voicemail':
            $result = asterisk('channel originate PJSIP/' . $extension . ' application VoiceMail ' . $target . '@default');
            if ($message !== '') {
                saveInternalMessage($extension, $target, $message, 'voicemail_note');
            }
            respond(200, true, 'Voicemail request sent.', ['details' => $result]);
            break;
        case 'text':
            if ($message === '') {
                respond(400, false, 'Enter a message first.');
            }
            saveInternalMessage($extension, $target, $message, 'text');
            respond(200, true, 'Message saved for that person.');
            break;
        default:
            respond(400, false, 'Choose a valid action.');
    }
}

function voicemail($extension) {
    $base = getenv('FLEXPBX_VOICEMAIL_SPOOL') ?: '/var/spool/asterisk/voicemail/flexpbx';
    $messages = [];
    foreach (['INBOX', 'Old'] as $folder) {
        $dir = $base . '/' . $extension . '/' . $folder;
        foreach (glob($dir . '/msg*.txt') ?: [] as $txt) {
            $meta = parseAsteriskMetadata($txt);
            $messages[] = [
                'caller' => $meta['callerid'] ?? $meta['origcallerid'] ?? 'Unknown caller',
                'date' => $meta['origdate'] ?? date('Y-m-d H:i:s', filemtime($txt)),
                'folder' => $folder,
                'url' => '/voicemail/flexpbx/' . rawurlencode($extension) . '/' . rawurlencode($folder) . '/' . basename($txt, '.txt') . '.wav'
            ];
        }
    }

    respond(200, true, count($messages) ? 'Voicemail loaded.' : 'No voicemail messages were found.', ['voicemails' => $messages]);
}

function recordings($extension) {
    $roots = array_filter([
        getenv('FLEXPBX_RECORDINGS_DIR') ?: '/var/spool/asterisk/monitor',
        '/home/flexpbxuser/recordings'
    ]);
    $items = [];
    foreach ($roots as $root) {
        if (!is_dir($root)) {
            continue;
        }
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file->isFile() || !preg_match('/\.(wav|mp3|gsm)$/i', $file->getFilename())) {
                continue;
            }
            if (strpos($file->getFilename(), $extension) === false && strpos($file->getPathname(), '/' . $extension . '/') === false) {
                continue;
            }
            $items[] = [
                'name' => $file->getFilename(),
                'date' => date('Y-m-d H:i:s', $file->getMTime()),
                'url' => '/recordings/' . rawurlencode($file->getFilename())
            ];
        }
    }

    usort($items, fn($a, $b) => strcmp($b['date'], $a['date']));
    respond(200, true, count($items) ? 'Recordings loaded.' : 'No recordings were found.', ['recordings' => array_slice($items, 0, 50)]);
}

function toggleRecording($extension) {
    $channels = asterisk('core show channels concise');
    $target = null;
    foreach ($channels as $line) {
        if (stripos($line, 'PJSIP/' . $extension) !== false) {
            $target = explode('!', $line)[0] ?? null;
            break;
        }
    }

    if (!$target) {
        respond(409, false, 'There is no active server call to record for this extension.');
    }

    $file = '/var/spool/asterisk/monitor/flexphone-' . $extension . '-' . date('Ymd-His') . '.wav';
    $result = asterisk('mixmonitor start ' . escapeshellarg($target) . ' ' . escapeshellarg($file));
    respond(200, true, 'Server recording was requested for the active call.', ['details' => $result]);
}

function loadDeviceRecords() {
    $dir = getenv('FLEXPBX_DEVICE_STATUS_DIR') ?: '/home/flexpbxuser/device_status';
    $records = [];
    foreach (glob($dir . '/device_*.json') ?: [] as $file) {
        if (!is_readable($file)) {
            continue;
        }
        $data = json_decode((string)@file_get_contents($file), true);
        if (!is_array($data)) {
            continue;
        }
        $lastSeen = strtotime($data['last_seen'] ?? '') ?: 0;
        $data['online'] = $lastSeen > (time() - 120);
        $records[] = $data;
    }
    usort($records, fn($a, $b) => strcmp($b['last_seen'] ?? '', $a['last_seen'] ?? ''));
    return $records;
}

function saveInternalMessage($fromExtension, $targetExtension, $message, $kind) {
    $dir = getenv('FLEXPBX_INTERNAL_MESSAGES_DIR') ?: '/home/flexpbxuser/internal_messages';
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }

    $record = [
        'from_extension' => $fromExtension,
        'target_extension' => $targetExtension,
        'kind' => $kind,
        'message' => $message,
        'created_at' => date('c'),
        'delivered' => false
    ];
    $file = $dir . '/message_' . $targetExtension . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.json';
    @file_put_contents($file, json_encode($record, JSON_PRETTY_PRINT), LOCK_EX);
    @chmod($file, 0640);
}

function sanitizeLines($lines) {
    if (!is_array($lines)) {
        return [];
    }
    $clean = [];
    foreach (array_slice($lines, 0, 8) as $line) {
        if (!is_array($line)) {
            continue;
        }
        $clean[] = [
            'line' => max(1, min(8, (int)($line['line'] ?? 1))),
            'state' => safeText((string)($line['state'] ?? ''), 24),
            'remote' => safeText((string)($line['remote'] ?? ''), 80),
            'active' => !empty($line['active'])
        ];
    }
    return $clean;
}

function sanitizeDeviceId($deviceId) {
    $value = preg_replace('/[^A-Za-z0-9_.-]/', '-', $deviceId);
    return substr($value ?: 'unknown', 0, 96);
}

function safeText($value, $max) {
    return substr(trim($value), 0, $max);
}

function classifyNetwork($ip) {
    if (preg_match('/^100\.(6[4-9]|[7-9][0-9]|1[01][0-9]|12[0-7])\./', $ip)) {
        return 'tailnet';
    }
    if (preg_match('/^(10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[01])\.)/', $ip) || $ip === '127.0.0.1' || $ip === '::1') {
        return 'lan';
    }
    return 'public';
}

function asterisk($command) {
    $safe = str_replace("'", "'\\''", $command);
    exec("sudo asterisk -rx '" . $safe . "' 2>&1", $output);
    return $output ?: [];
}

function callerNameFromChannel($line) {
    if (preg_match('/"([^"]+)"/', $line, $matches)) {
        return $matches[1];
    }
    return 'Caller';
}

function loadUsers() {
    $users = [];
    $dir = getenv('FLEXPBX_USERS_DIR') ?: '/home/flexpbxuser/users';
    foreach (glob($dir . '/user_*.json') ?: [] as $file) {
        $data = json_decode((string)file_get_contents($file), true);
        if (is_array($data)) {
            $users[] = $data;
        }
    }
    return $users;
}

function findUserByEmail($email) {
    $needle = strtolower(trim($email));
    foreach (loadUsers() as $user) {
        if (strtolower((string)($user['email'] ?? '')) === $needle) {
            return $user;
        }
    }
    return null;
}

function deviceAuthorizationDir() {
    return getenv('FLEXPBX_DEVICE_AUTH_DIR') ?: '/home/flexpbxuser/device_authorizations';
}

function authorizationTokenFromInput($input) {
    $token = trim((string)($input['token'] ?? ''));
    if ($token === '') {
        $tokenUrl = trim((string)($input['token_url'] ?? $input['authorization_url'] ?? ''));
        if ($tokenUrl !== '') {
            $parts = parse_url($tokenUrl);
            if (is_array($parts) && !empty($parts['query'])) {
                parse_str((string)$parts['query'], $query);
                $token = trim((string)($query['token'] ?? ''));
            }
        }
    }

    return preg_match('/^[A-Fa-f0-9]{48}$/', $token) ? $token : '';
}

function readDeviceAuthorization($token) {
    $file = deviceAuthorizationFile($token);
    if (!is_readable($file)) {
        return null;
    }

    $data = json_decode((string)file_get_contents($file), true);
    return is_array($data) ? $data : null;
}

function writeDeviceAuthorization($token, $record) {
    $dir = deviceAuthorizationDir();
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }

    $file = deviceAuthorizationFile($token);
    @file_put_contents($file, json_encode($record, JSON_PRETTY_PRINT), LOCK_EX);
    @chmod($file, 0640);
}

function deleteDeviceAuthorization($token) {
    @unlink(deviceAuthorizationFile($token));
}

function deviceAuthorizationFile($token) {
    return deviceAuthorizationDir() . '/device_' . hash('sha256', $token) . '.json';
}

function publicBaseUrl() {
    $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? 'pbx.tappedin.fm';
    $host = preg_replace('/[^A-Za-z0-9.\-:]/', '', (string)$host) ?: 'pbx.tappedin.fm';
    return 'https://' . $host;
}

function mailDomain() {
    $host = parse_url(publicBaseUrl(), PHP_URL_HOST) ?: 'pbx.tappedin.fm';
    return preg_replace('/[^A-Za-z0-9.\-]/', '', (string)$host) ?: 'pbx.tappedin.fm';
}

function flexPhoneSipSettings() {
    $publicHost = flexPhonePublicSipHost();
    $routes = [
        [
            'label' => 'Public SIP',
            'server' => $publicHost,
            'host' => $publicHost,
            'port' => 5060,
            'transport' => 'UDP',
            'route_type' => 'public',
            'preferred' => true
        ],
        [
            'label' => 'Secure Headscale server',
            'server' => '100.64.0.2',
            'host' => '100.64.0.2',
            'port' => 5060,
            'transport' => 'UDP',
            'route_type' => 'headscale',
            'preferred' => false
        ],
        [
            'label' => 'Secure Headscale PBX node',
            'server' => '100.64.0.3',
            'host' => '100.64.0.3',
            'port' => 5060,
            'transport' => 'UDP',
            'route_type' => 'headscale',
            'preferred' => false
        ]
    ];

    return [
        'server' => $publicHost,
        'host' => $publicHost,
        'port' => 5060,
        'transport' => 'UDP',
        'routes' => $routes,
        'fallbacks' => array_slice($routes, 1)
    ];
}

function flexPhonePublicSipHost() {
    $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? 'pbx.tappedin.fm';
    $host = strtolower(preg_replace('/[^A-Za-z0-9.\-:]/', '', (string)$host) ?: 'pbx.tappedin.fm');
    $host = preg_replace('/:\d+$/', '', $host) ?: 'pbx.tappedin.fm';
    $allowed = [
        'pbx.devinecreations.net',
        'pbx.tappedin.fm',
        'flexpbx.devinecreations.net'
    ];

    return in_array($host, $allowed, true) ? $host : 'pbx.tappedin.fm';
}

function buildDeviceAuthorizationEmail($url, $email, $user) {
    $lines = [
        'Flex Phone sign-in confirmation',
        '',
        'Someone entered this email address in Flex Phone to link a Windows device.',
        '',
        'Open this secure link to confirm the device:',
        $url,
        ''
    ];

    if (is_array($user)) {
        $extension = (string)($user['extension'] ?? $user['extension_number'] ?? '');
        if ($extension !== '') {
            $lines[] = 'This will link the device to extension ' . $extension . '.';
            $lines[] = '';
        }
        $lines[] = 'After the page says the device is authorized, return to Flex Phone and choose Finish sign in.';
    } else {
        $lines[] = 'This email is confirmed, but no extension account is assigned yet.';
        $lines[] = 'The confirmation page will offer the user portal sign-up path so an administrator can approve the extension.';
    }

    $lines[] = '';
    $lines[] = 'If you did not request this, ignore this email.';
    $lines[] = 'Email: ' . $email;
    return implode("\n", $lines);
}

function issueSessionToken($extension, $username, $role, $client) {
    $token = bin2hex(random_bytes(32));
    $dir = getenv('FLEXPBX_SESSION_DIR') ?: '/home/flexpbxuser/sessions';
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }

    $record = [
        'token_hash' => hash('sha256', $token),
        'extension' => $extension,
        'username' => $username,
        'role' => $role,
        'client' => $client,
        'created' => time(),
        'expires' => time() + 86400,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? ''
    ];
    @file_put_contents($dir . '/session_' . hash('sha256', $token) . '.json', json_encode($record, JSON_PRETTY_PRINT), LOCK_EX);
    @chmod($dir . '/session_' . hash('sha256', $token) . '.json', 0640);
    return $token;
}

function parseAsteriskMetadata($file) {
    $data = [];
    foreach (file($file, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        if (strpos($line, '=') === false) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $data[strtolower(trim($key))] = trim($value);
    }
    return $data;
}

function respond($status, $success, $message, $extra = []) {
    http_response_code($status);
    $payload = array_merge([
        'success' => $success,
        'message' => $message
    ], $extra);
    if (!$success) {
        $payload['error'] = $message;
    }
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}
