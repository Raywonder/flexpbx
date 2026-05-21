<?php
/**
 * Flex PBX modern login API.
 *
 * Accepts a username, extension number, or email address with the extension
 * password. Legacy plaintext admin-file passwords are intentionally not
 * accepted. Admin access is represented by normal user roles, not a separate
 * legacy admin account.
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
    respond(405, false, 'Use POST to sign in.');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$identifier = trim((string)($input['identifier'] ?? $input['username'] ?? $input['extension'] ?? ''));
$password = (string)($input['password'] ?? '');
$accountType = strtolower(trim((string)($input['account_type'] ?? 'user')));
$client = trim((string)($input['client'] ?? ''));

if ($identifier === '' || $password === '') {
    respond(400, false, 'Enter your username or extension and password.');
}

if (!in_array($accountType, ['user', 'admin'], true)) {
    respond(400, false, 'Choose user or admin sign in.');
}

$user = findModernUser($identifier);
if ($user === null || !verifyModernPassword($password, $user)) {
    auditLogin($identifier, $accountType, false, $client);
    respond(401, false, 'That username, extension, or password did not match.');
}

$role = strtolower((string)($user['role'] ?? 'user'));
if ($accountType === 'admin' && !in_array($role, ['admin', 'super_admin', 'administrator'], true)) {
    auditLogin($identifier, $accountType, false, $client);
    respond(403, false, 'That account is not allowed to use admin sign in.');
}

$extension = (string)($user['extension'] ?? $user['extension_number'] ?? $user['username'] ?? $identifier);
$username = (string)($user['username'] ?? $extension);
$email = (string)($user['email'] ?? '');
$fullName = (string)($user['full_name'] ?? $user['display_name'] ?? '');
$sipPassword = (string)($user['sip_password'] ?? $user['extension_password'] ?? $user['secret'] ?? '');
$token = issueSessionToken($extension, $username, $role, $client);
touchUserLogin($extension, $username);
auditLogin($identifier, $accountType, true, $client);

respond(200, true, 'Signed in.', [
    'account_type' => $accountType,
    'extension' => $extension,
    'username' => $username,
    'email' => $email,
    'full_name' => $fullName,
    'role' => $role,
    'session_token' => $token,
    'sip_password' => $sipPassword,
    'auth_methods' => configuredAuthMethods(),
    'feature_codes' => configuredFeatureCodes(),
    'email_setup_required' => needsEmailSetup($email),
    'sip_settings' => [
        'server' => $_SERVER['HTTP_HOST'] ?? 'pbx.tappedin.fm',
        'port' => 5060,
        'transport' => 'UDP'
    ]
]);

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

function findModernUser($identifier) {
    $identifierLower = strtolower($identifier);

    $fileUser = findUserFile($identifierLower);
    $dbUser = findDatabaseUser($identifier);

    if ($dbUser !== null) {
        return array_merge($fileUser ?? [], $dbUser);
    }

    return $fileUser;
}

function findUserFile($identifierLower) {
    $usersDir = getenv('FLEXPBX_USERS_DIR') ?: '/home/flexpbxuser/users';
    if (!is_dir($usersDir)) {
        return null;
    }

    foreach (glob($usersDir . '/user_*.json') ?: [] as $file) {
        $data = json_decode((string)file_get_contents($file), true);
        if (!is_array($data)) {
            continue;
        }

        $candidates = [
            strtolower((string)($data['extension'] ?? '')),
            strtolower((string)($data['username'] ?? '')),
            strtolower((string)($data['email'] ?? ''))
        ];
        if (in_array($identifierLower, $candidates, true)) {
            $data['_file'] = $file;
            return $data;
        }
    }

    return null;
}

function findDatabaseUser($identifier) {
    $configPath = __DIR__ . '/config.php';
    if (!is_file($configPath)) {
        return null;
    }

    $config = include $configPath;
    if (!is_array($config)) {
        return null;
    }

    try {
        $pdo = new PDO(
            "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4",
            $config['db_user'],
            $config['db_password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $stmt = $pdo->prepare("
            SELECT username, extension, email, full_name, role, password_hash, is_active
            FROM users
            WHERE username = ? OR extension = ? OR email = ?
            LIMIT 1
        ");
        $stmt->execute([$identifier, $identifier, $identifier]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && (int)($user['is_active'] ?? 1) === 1) {
            return $user;
        }

        $stmt = $pdo->prepare("
            SELECT
                COALESCE(u.username, e.extension_number) AS username,
                e.extension_number AS extension,
                COALESCE(u.email, e.email) AS email,
                COALESCE(u.full_name, e.display_name) AS full_name,
                COALESCE(u.role, 'user') AS role,
                e.password_hash,
                IFNULL(e.status, 'active') AS status
            FROM extensions e
            LEFT JOIN users u ON u.id = e.user_id
            WHERE e.extension_number = ? OR u.username = ? OR u.email = ?
            LIMIT 1
        ");
        $stmt->execute([$identifier, $identifier, $identifier]);
        $extension = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($extension && strtolower((string)($extension['status'] ?? 'active')) === 'active') {
            return $extension;
        }
    } catch (Exception $e) {
        error_log('Flex PBX login database lookup failed: ' . $e->getMessage());
    }

    return null;
}

function verifyModernPassword($password, $user) {
    $hashes = [];
    foreach (['password_hash', 'password', 'sip_password', 'extension_password', 'secret'] as $key) {
        if (!empty($user[$key]) && is_string($user[$key])) {
            $hashes[] = $user[$key];
        }
    }

    foreach ($hashes as $hash) {
        if (password_get_info($hash)['algo'] !== 0 && password_verify($password, $hash)) {
            return true;
        }

        if (strlen($hash) === 64 && hash_equals(strtolower($hash), hash('sha256', $password))) {
            return true;
        }

        if (!str_starts_with($hash, '$') && hash_equals($hash, $password)) {
            return true;
        }
    }

    return false;
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

function touchUserLogin($extension, $username) {
    $user = findUserFile(strtolower($extension));
    if (!is_array($user) || empty($user['_file'])) {
        $user = findUserFile(strtolower($username));
    }
    if (is_array($user) && !empty($user['_file'])) {
        $file = $user['_file'];
        unset($user['_file']);
        $user['last_login'] = date('Y-m-d H:i:s');
        $user['last_login_ip'] = $_SERVER['REMOTE_ADDR'] ?? '';
        @file_put_contents($file, json_encode($user, JSON_PRETTY_PRINT), LOCK_EX);
        @chmod($file, 0640);
    }
}

function configuredAuthMethods() {
    $methods = ['password'];
    if (getenv('FLEXPBX_SSO_LOGIN_URL')) {
        $methods[] = 'sso';
    }
    if (getenv('FLEXPBX_PAIRING_ENABLED') !== '0') {
        $methods[] = 'pairing_code';
    }
    return $methods;
}

function configuredFeatureCodes() {
    return [
        'queue_toggle' => getenv('FLEXPBX_QUEUE_TOGGLE_CODE') ?: '*45',
        'queue_login' => getenv('FLEXPBX_QUEUE_LOGIN_CODE') ?: '*45',
        'queue_logout' => getenv('FLEXPBX_QUEUE_LOGOUT_CODE') ?: (getenv('FLEXPBX_QUEUE_TOGGLE_CODE') ?: '*45'),
        'voicemail' => getenv('FLEXPBX_VOICEMAIL_CODE') ?: '*97',
        'dnd_toggle' => getenv('FLEXPBX_DND_TOGGLE_CODE') ?: '*76',
        'call_screening_toggle' => getenv('FLEXPBX_CALL_SCREENING_TOGGLE_CODE') ?: '*56'
    ];
}

function needsEmailSetup($email) {
    $placeholderEmails = [
        '',
        'user@example.com',
        'admin@example.com',
        'noemail@localhost',
        'user@localhost',
        'test@test.com',
        'changeme@example.com',
        'administrator@localhost'
    ];

    return empty($email) || in_array(strtolower(trim($email)), $placeholderEmails, true);
}

function auditLogin($identifier, $accountType, $success, $client) {
    $logDir = getenv('FLEXPBX_AUTH_LOG_DIR') ?: '/home/flexpbxuser/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0750, true);
    }
    $entry = [
        'timestamp' => date('c'),
        'identifier_hash' => hash('sha256', strtolower((string)$identifier)),
        'account_type' => $accountType,
        'success' => $success,
        'client' => $client,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? ''
    ];
    @file_put_contents($logDir . '/flexpbx-auth.log', json_encode($entry) . "\n", FILE_APPEND | LOCK_EX);
}
