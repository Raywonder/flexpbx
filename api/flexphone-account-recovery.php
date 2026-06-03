<?php
/**
 * FlexPBX Flex Phone Account Recovery API
 *
 * Accepts confirmed Flex Phone requests to recover an extension login username,
 * reset an extension password, or request the current SIP password. Passwords are never returned
 * in the JSON response; delivery is email only. Password changes are limited
 * to managed extension user accounts and update both Asterisk PJSIP auth and
 * FlexPBX user records; trunk sections are intentionally out of scope.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    handleEmailConfirmation();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, false, 'POST required.');
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    respond(400, false, 'Invalid JSON request.');
}

$action = strtolower(trim((string)($input['action'] ?? '')));
$identifier = trim((string)($input['extension'] ?? $input['username'] ?? $input['identifier'] ?? ''));
$confirmed = filter_var($input['confirmed'] ?? false, FILTER_VALIDATE_BOOLEAN);
$client = trim((string)($input['client'] ?? ''));

$allowedActions = ['reset_username', 'reset_password', 'get_current_password'];
if (!in_array($action, $allowedActions, true)) {
    respond(400, false, 'Unsupported account recovery action.');
}

if ($identifier === '' || !preg_match('/^[A-Za-z0-9_.@+-]{2,80}$/', $identifier)) {
    respond(400, false, 'Enter a valid extension, username, or email address.');
}

if (!$confirmed) {
    respond(409, false, 'Please confirm this request before continuing.');
}

$user = findFlexPhoneUser($identifier);
if ($user === null || empty($user['email'])) {
    auditRecovery($action, $identifier, 'not_found_or_no_email', $client);
    respond(200, true, 'If that extension has a recovery email, instructions will be sent there.', [
        'delivery' => 'email',
        'action_taken' => 'accepted'
    ]);
}

$extension = (string)($user['extension'] ?? $user['username'] ?? $identifier);
$username = (string)($user['username'] ?? $extension);
$email = (string)$user['email'];

if ($action === 'reset_username') {
    $sent = sendRecoveryEmail(
        $email,
        'FlexPBX Username Recovery',
        "Hello,\n\nYour FlexPBX login details for extension {$extension} are:\n\nUsername: {$username}\nExtension: {$extension}\n\nIf you did not request this message, contact support immediately.\n\nFlexPBX System\n"
    );

    auditRecovery($action, $extension, $sent ? 'sent' : 'mail_failed', $client);
    respond($sent ? 200 : 500, $sent, $sent
        ? 'The username was sent to the recovery email.'
        : 'The username email could not be sent right now.', [
            'delivery' => 'email',
            'action_taken' => 'reset_username'
        ]);
}

if ($action === 'reset_password' || $action === 'get_current_password') {
    $sent = sendSensitiveRecoveryConfirmation($email, $extension, $username, $action, $client);
    auditRecovery($action, $extension, $sent ? 'confirmation_sent' : 'confirmation_mail_failed', $client);
    respond($sent ? 200 : 500, $sent, $sent
        ? 'A confirmation email was sent. Open it to finish this password request.'
        : 'The confirmation email could not be sent right now.', [
            'delivery' => 'email',
            'action_taken' => 'confirmation_required'
        ]);
}

respond(400, false, 'Unsupported account recovery action.');

function handleEmailConfirmation() {
    $token = trim((string)($_GET['token'] ?? ''));
    if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
        renderConfirmationPage('Invalid recovery confirmation link.', false);
    }

    $file = recoveryTokenFile($token);
    if (!is_file($file)) {
        renderConfirmationPage('This recovery confirmation link is invalid or has already been used.', false);
    }

    $data = json_decode(file_get_contents($file), true);
    if (!is_array($data) || (int)($data['expires'] ?? 0) < time()) {
        @unlink($file);
        renderConfirmationPage('This recovery confirmation link has expired.', false);
    }

    $action = (string)($data['action'] ?? '');
    $extension = (string)($data['extension'] ?? '');
    $username = (string)($data['username'] ?? $extension);
    $email = (string)($data['email'] ?? '');

    if ($action === 'get_current_password' && currentPasswordRecoveryAllowed()) {
        $currentPassword = readCurrentSipPassword($extension);
        if (is_string($currentPassword) && $currentPassword !== '') {
            $sent = sendRecoveryEmail(
                $email,
                'FlexPBX Current Password Request',
                "Hello,\n\nThe current SIP password for extension {$extension} is:\n\n{$currentPassword}\n\nIf you did not request this message, reset your password and contact support immediately.\n\nFlexPBX System\n"
            );
            @unlink($file);
            auditRecovery($action, $extension, $sent ? 'confirmed_current_password_sent' : 'confirmed_mail_failed', 'Flex Phone');
            renderConfirmationPage($sent
                ? 'The current password was sent to the recovery email.'
                : 'The current password email could not be sent right now.', $sent);
        }
    }

    $newPassword = generateRecoveryPassword();
    $updated = setExtensionPassword($extension, $newPassword);
    if (!$updated) {
        auditRecovery($action, $extension, 'confirmed_password_update_failed', 'Flex Phone');
        renderConfirmationPage('The extension password could not be updated right now.', false);
    }

    $sent = sendRecoveryEmail(
        $email,
        'FlexPBX New Password',
        "Hello,\n\nA new password was generated for extension {$extension}.\n\nUsername: {$username}\nExtension: {$extension}\nNew password: {$newPassword}\n\nIf you did not request this message, contact support immediately.\n\nFlexPBX System\n"
    );

    @unlink($file);
    auditRecovery($action, $extension, $sent ? 'confirmed_new_password_sent' : 'confirmed_mail_failed_after_update', 'Flex Phone');
    renderConfirmationPage($sent
        ? 'A new password was created and sent to the recovery email.'
        : 'A new password was created, but the email could not be sent. Contact support.', $sent);
}

function renderConfirmationPage($message, $success) {
    http_response_code($success ? 200 : 400);
    header('Content-Type: text/html; charset=utf-8');
    $title = $success ? 'Recovery Confirmed' : 'Recovery Not Completed';
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>' . htmlspecialchars($title, ENT_QUOTES) . '</title></head><body>';
    echo '<main><h1>' . htmlspecialchars($title, ENT_QUOTES) . '</h1><p>' . htmlspecialchars($message, ENT_QUOTES) . '</p></main>';
    echo '</body></html>';
    exit;
}

function sendSensitiveRecoveryConfirmation($email, $extension, $username, $action, $client) {
    $token = bin2hex(random_bytes(32));
    $data = [
        'token' => $token,
        'action' => $action,
        'extension' => $extension,
        'username' => $username,
        'email' => $email,
        'client' => $client,
        'created' => time(),
        'expires' => time() + 1800
    ];

    $file = recoveryTokenFile($token);
    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }

    if (file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX) === false) {
        return false;
    }
    @chmod($file, 0640);

    $link = recoveryConfirmationBaseUrl() . '?token=' . urlencode($token);
    $label = $action === 'get_current_password'
        ? 'send the current password, or create a new one if the current password cannot be read'
        : 'generate a new password';

    return sendRecoveryEmail(
        $email,
        'FlexPBX Account Recovery Confirmation',
        "Hello,\n\nFlex Phone received a request for extension {$extension}.\n\nConfirm this request to {$label}:\n{$link}\n\nThis link expires in 30 minutes. If you did not request it, ignore this message and contact support.\n\nFlexPBX System\n"
    );
}

function recoveryTokenFile($token) {
    $dir = getenv('FLEXPBX_RECOVERY_TOKEN_DIR') ?: '/home/flexpbxuser/reset_tokens';
    return rtrim($dir, '/') . '/flexphone_' . $token . '.json';
}

function recoveryConfirmationBaseUrl() {
    $host = $_SERVER['HTTP_HOST'] ?? 'pbx.devinecreations.net';
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'https';
    return $scheme . '://' . $host . '/api/flexphone-account-recovery.php';
}

function respond($status, $success, $message, $extra = []) {
    http_response_code($status);
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message
    ], $extra), JSON_UNESCAPED_SLASHES);
    exit;
}

function usersDirectory() {
    return getenv('FLEXPBX_USERS_DIR') ?: '/home/flexpbxuser/users';
}

function findFlexPhoneUser($identifier) {
    $usersDir = usersDirectory();
    if (!is_dir($usersDir)) {
        return null;
    }

    $direct = $usersDir . '/user_' . basename($identifier) . '.json';
    if (is_file($direct) && is_readable($direct)) {
        $data = json_decode((string)file_get_contents($direct), true);
        if (is_array($data)) {
            $data['_file'] = $direct;
            return $data;
        }
    }

    foreach (glob($usersDir . '/user_*.json') ?: [] as $file) {
        if (!is_readable($file)) {
            continue;
        }

        $data = json_decode((string)file_get_contents($file), true);
        if (!is_array($data)) {
            continue;
        }

        $candidates = [
            strtolower((string)($data['extension'] ?? '')),
            strtolower((string)($data['username'] ?? '')),
            strtolower((string)($data['email'] ?? ''))
        ];

        if (in_array(strtolower($identifier), $candidates, true)) {
            $data['_file'] = $file;
            return $data;
        }
    }

    return null;
}

function currentPasswordRecoveryAllowed() {
    $env = getenv('FLEXPBX_ALLOW_CURRENT_PASSWORD_RECOVERY');
    if (is_string($env) && in_array(strtolower($env), ['0', 'false', 'no'], true)) {
        return false;
    }
    return true;
}

function pjsipConfigPath() {
    return getenv('FLEXPBX_PJSIP_CONF') ?: '/etc/asterisk/pjsip.conf';
}

function readCurrentSipPassword($extension) {
    $path = pjsipConfigPath();
    if (!is_file($path)) {
        return null;
    }

    $content = file_get_contents($path);
    $quoted = preg_quote($extension, '/');
    if (preg_match('/\[' . $quoted . '\]\s*\R(?:(?!^\[).)*?type\s*=\s*auth(?:(?!^\[).)*?password\s*=\s*([^\s\R]+)/ms', $content, $matches)) {
        return trim($matches[1]);
    }

    return null;
}

function setExtensionPassword($extension, $password) {
    $managedUser = findFlexPhoneUser($extension);
    if (!is_array($managedUser)) {
        error_log("FlexPhone account recovery refused unmanaged extension: {$extension}");
        return false;
    }

    $path = pjsipConfigPath();
    if (!is_file($path)) {
        return false;
    }

    $content = file_get_contents($path);
    $quoted = preg_quote($extension, '/');
    $pattern = '/(\[' . $quoted . '\]\s*\R(?:(?!^\[).)*?type\s*=\s*auth(?:(?!^\[).)*?username\s*=\s*' . $quoted . '(?:(?!^\[).)*?password\s*=\s*)[^\s\R]+/ms';
    if (!preg_match($pattern, $content)) {
        error_log("FlexPhone account recovery refused to update non-extension or trunk-like PJSIP auth section: {$extension}");
        return false;
    }

    $updated = preg_replace_callback($pattern, function ($matches) use ($password) {
        return $matches[1] . $password;
    }, $content, 1, $count);
    if ($count < 1 || !is_string($updated)) {
        return false;
    }

    if (file_put_contents($path, $updated) === false) {
        return false;
    }

    @chown($path, 'asterisk');
    @chgrp($path, 'asterisk');
    @chmod($path, 0640);
    reloadAsteriskPjsip();
    updateUserPasswordHash($extension, $password);
    return true;
}

function updateUserPasswordHash($extension, $password) {
    $user = findFlexPhoneUser($extension);
    if (is_array($user) && !empty($user['_file'])) {
        $file = $user['_file'];
        unset($user['_file']);
        $user['password'] = password_hash($password, PASSWORD_DEFAULT);
        $user['password_reset_date'] = date('Y-m-d H:i:s');
        file_put_contents($file, json_encode($user, JSON_PRETTY_PRINT));
        @chmod($file, 0640);
    }

    $configPath = __DIR__ . '/config.php';
    if (!is_file($configPath)) {
        return;
    }

    $config = include $configPath;
    if (!is_array($config)) {
        return;
    }

    try {
        $pdo = new PDO(
            "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4",
            $config['db_user'],
            $config['db_password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $stmt = $pdo->prepare("UPDATE users SET password_hash = SHA2(?, 256), updated_at = NOW() WHERE extension = ? OR username = ?");
        $stmt->execute([$password, $extension, $extension]);
    } catch (Exception $e) {
        error_log('FlexPhone account recovery database update failed: ' . $e->getMessage());
    }
}

function generateRecoveryPassword($length = 18) {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#%+=';
    $password = '';
    $max = strlen($alphabet) - 1;
    for ($i = 0; $i < $length; $i++) {
        $password .= $alphabet[random_int(0, $max)];
    }
    return $password;
}

function sendRecoveryEmail($to, $subject, $message) {
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $sender = 'services@devine-creations.com';
    $headers = "From: Flex PBX <{$sender}>\r\n";
    $headers .= "Reply-To: support@devine-creations.com\r\n";
    $headers .= "X-Mailer: FlexPBX Flex Phone Account Recovery\r\n";
    return mail($to, $subject, $message, $headers, '-f' . $sender);
}

function reloadAsteriskPjsip() {
    exec('sudo asterisk -rx "pjsip reload" 2>&1', $output, $returnCode);
    return $returnCode === 0;
}

function auditRecovery($action, $identifier, $status, $client) {
    $logDir = getenv('FLEXPBX_RECOVERY_LOG_DIR') ?: '/home/flexpbxuser/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0750, true);
    }

    $entry = [
        'timestamp' => date('c'),
        'action' => $action,
        'identifier_hash' => hash('sha256', strtolower((string)$identifier)),
        'status' => $status,
        'client' => $client,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? ''
    ];

    @file_put_contents($logDir . '/flexphone-account-recovery.log', json_encode($entry) . "\n", FILE_APPEND | LOCK_EX);
}
