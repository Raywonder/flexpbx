<?php
/**
 * Flex Phone device authorization page.
 *
 * Email links land here to confirm a pending Flex Phone device token. The
 * native client then calls complete_device_authorization with the same token.
 */

session_start();

$token = cleanToken($_GET['token'] ?? '');
$email = strtolower(trim((string)($_GET['email'] ?? '')));
$code = preg_replace('/\D+/', '', (string)($_GET['code'] ?? ''));

$status = 'error';
$title = 'Flex Phone authorization';
$heading = 'Flex Phone authorization';
$message = 'That Flex Phone authorization link is invalid or incomplete.';
$details = [];
$primaryUrl = '/user-portal/login.php';
$primaryLabel = 'Open user portal login';
$secondaryUrl = '';
$secondaryLabel = '';

if ($code !== '' && $token !== '' && $email === '') {
    [$status, $heading, $message, $details] = handlePairingCode($token, $code);
    $primaryUrl = '/flexphone/';
    $primaryLabel = 'Open Flex Phone web client';
} elseif ($token !== '') {
    [$status, $heading, $message, $details, $primaryUrl, $primaryLabel, $secondaryUrl, $secondaryLabel] = handleDeviceAuthorization($token, $email);
}

function handleDeviceAuthorization($token, $email) {
    $record = readDeviceAuthorization($token);
    if (!is_array($record)) {
        return ['error', 'Authorization link not found', 'This Flex Phone link was not found. It may have expired or already been replaced by a newer request.', [], '/user-portal/login.php', 'Open user portal login', '', ''];
    }

    if ((int)($record['expires'] ?? 0) < time()) {
        deleteDeviceAuthorization($token);
        return ['error', 'Authorization link expired', 'This Flex Phone link has expired. Return to Flex Phone and request a new confirmation email.', [], '/user-portal/login.php', 'Open user portal login', '', ''];
    }

    $recordEmail = strtolower((string)($record['email'] ?? ''));
    if ($email !== '' && !hash_equals($recordEmail, $email)) {
        return ['error', 'Email mismatch', 'This confirmation link belongs to a different email address.', [], '/user-portal/login.php', 'Open user portal login', '', ''];
    }

    $record['confirmed'] = true;
    $record['confirmed_at'] = time();
    $record['confirmed_ip'] = $_SERVER['REMOTE_ADDR'] ?? '';
    writeDeviceAuthorization($token, $record);

    $user = findUserByEmail($recordEmail);
    $details = [
        'Confirmed email: ' . $recordEmail,
        'Device id: ' . safeDisplay((string)($record['device_id'] ?? 'unknown'))
    ];

    if (is_array($user)) {
        $extension = (string)($user['extension'] ?? $user['extension_number'] ?? '');
        $username = (string)($user['username'] ?? $extension);
        if ($extension !== '') {
            $details[] = 'Extension: ' . $extension;
            $_SESSION['user_extension'] = $extension;
            $_SESSION['user_username'] = $username;
            $_SESSION['user_logged_in'] = true;
            $_SESSION['email_setup_complete'] = true;
            $_SESSION['last_activity'] = time();
            $_SESSION['idle_timeout'] = 1800;
            $_SESSION['session_expires'] = time() + 1800;
        }

        $passwordUrl = createPasswordSetupUrl($user, $recordEmail);
        return [
            'success',
            'Device authorized',
            'This device is authorized. Return to Flex Phone and choose Finish sign in. You can also open the user portal now to check your profile or set your password.',
            $details,
            '/user-portal/',
            'Open your user portal',
            $passwordUrl,
            $passwordUrl === '' ? '' : 'Set or change portal password'
        ];
    }

    return [
        'warning',
        'Email confirmed, extension not assigned yet',
        'Your email address is confirmed, but no extension account is assigned to it yet. Complete the sign-up request so an administrator can approve the extension, username, name, and password setup.',
        $details,
        '/user-portal/signup.php?email=' . urlencode($recordEmail) . '&source=flexphone',
        'Request an extension account',
        '/user-portal/login.php',
        'Open user portal login'
    ];
}

function handlePairingCode($token, $code) {
    $file = pairingDir() . '/pair_' . $token . '.json';
    if (!is_readable($file)) {
        return ['error', 'Pairing link not found', 'This pairing link was not found or has expired.', []];
    }

    $record = json_decode((string)file_get_contents($file), true);
    if (!is_array($record) || (int)($record['expires'] ?? 0) < time()) {
        @unlink($file);
        return ['error', 'Pairing link expired', 'This pairing link has expired. Create a new pairing code from Flex Phone.', []];
    }

    if (!passwordlessHashEquals((string)($record['code_hash'] ?? ''), hash('sha256', $code))) {
        return ['error', 'Pairing code mismatch', 'This pairing code does not match the link.', []];
    }

    return [
        'success',
        'Pairing code ready',
        'Open Flex Phone on the device you want to link and enter this pairing code.',
        ['Pairing code: ' . $code, 'Extension: ' . safeDisplay((string)($record['extension'] ?? 'unknown'))]
    ];
}

function cleanToken($value) {
    $token = trim((string)$value);
    return preg_match('/^[A-Fa-f0-9]{48}$/', $token) ? $token : '';
}

function deviceAuthorizationDir() {
    return getenv('FLEXPBX_DEVICE_AUTH_DIR') ?: '/home/flexpbxuser/device_authorizations';
}

function pairingDir() {
    return getenv('FLEXPBX_PAIRING_DIR') ?: '/home/flexpbxuser/pairing_codes';
}

function usersDir() {
    return getenv('FLEXPBX_USERS_DIR') ?: '/home/flexpbxuser/users';
}

function resetTokenDir() {
    return getenv('FLEXPBX_RESET_TOKEN_DIR') ?: '/home/flexpbxuser/reset_tokens';
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

    @file_put_contents(deviceAuthorizationFile($token), json_encode($record, JSON_PRETTY_PRINT), LOCK_EX);
    @chmod(deviceAuthorizationFile($token), 0640);
}

function deleteDeviceAuthorization($token) {
    @unlink(deviceAuthorizationFile($token));
}

function deviceAuthorizationFile($token) {
    return deviceAuthorizationDir() . '/device_' . hash('sha256', $token) . '.json';
}

function findUserByEmail($email) {
    $needle = strtolower(trim((string)$email));
    foreach (glob(usersDir() . '/user_*.json') ?: [] as $file) {
        if (!is_readable($file)) {
            continue;
        }

        $user = json_decode((string)file_get_contents($file), true);
        if (!is_array($user)) {
            continue;
        }

        if (strtolower((string)($user['email'] ?? '')) === $needle) {
            $user['_file'] = $file;
            return $user;
        }
    }

    return findUserByEmailFromDatabase($needle);
}

function findUserByEmailFromDatabase($email) {
    $configPath = __DIR__ . '/../config/config.php';
    if (!is_file($configPath)) {
        return null;
    }

    require $configPath;
    $host = $DB_HOST ?? 'localhost';
    $port = $DB_PORT ?? '3306';
    $dbname = $DB_NAME ?? 'flexpbxuser_flexpbx';
    $dbuser = $DB_USER ?? 'flexpbxuser_flexpbxserver';
    $dbpass = $DB_PASS ?? '';

    try {
        $pdo = new PDO(
            "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
            $dbuser,
            $dbpass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );

        $stmt = $pdo->prepare("
            SELECT
                COALESCE(u.username, e.extension_number) AS username,
                e.extension_number AS extension,
                COALESCE(u.email, e.email) AS email,
                COALESCE(u.full_name, e.display_name) AS full_name,
                COALESCE(u.role, 'user') AS role,
                IFNULL(e.status, 'active') AS status
            FROM extensions e
            LEFT JOIN users u ON u.id = e.user_id
            WHERE LOWER(COALESCE(u.email, e.email)) = ?
            LIMIT 1
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if (!$user || strtolower((string)($user['status'] ?? 'active')) !== 'active') {
            return null;
        }

        $extension = (string)($user['extension'] ?? '');
        return [
            'username' => (string)($user['username'] ?? $extension),
            'extension' => $extension,
            'extension_number' => $extension,
            'email' => (string)($user['email'] ?? ''),
            'full_name' => (string)($user['full_name'] ?? ''),
            'role' => (string)($user['role'] ?? 'user')
        ];
    } catch (Throwable $e) {
        error_log('Flex Phone link email database lookup failed: ' . $e->getMessage());
        return null;
    }
}

function createPasswordSetupUrl($user, $email) {
    $extension = (string)($user['extension'] ?? $user['extension_number'] ?? '');
    if ($extension === '') {
        return '';
    }

    $dir = resetTokenDir();
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }

    $token = bin2hex(random_bytes(24));
    $record = [
        'type' => 'user',
        'extension' => $extension,
        'email' => $email,
        'created' => time(),
        'expires' => time() + 3600,
        'source' => 'flexphone_link'
    ];

    @file_put_contents($dir . '/token_' . $token . '.json', json_encode($record, JSON_PRETTY_PRINT), LOCK_EX);
    @chmod($dir . '/token_' . $token . '.json', 0640);
    return '/user-portal/reset-password.php?token=' . urlencode($token);
}

function passwordlessHashEquals($a, $b) {
    return is_string($a) && is_string($b) && hash_equals($a, $b);
}

function safeDisplay($value) {
    $value = trim((string)$value);
    return $value === '' ? 'unknown' : $value;
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?></title>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f7fb;
            color: #17202a;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        main {
            width: min(680px, 100%);
            background: #ffffff;
            border: 1px solid #cfd9e6;
            border-radius: 8px;
            padding: 28px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        }
        .status {
            font-weight: 700;
            margin-bottom: 12px;
        }
        .success { color: #0f6b2f; }
        .warning { color: #8a5a00; }
        .error { color: #9f1d20; }
        h1 {
            font-size: 1.8rem;
            margin: 0 0 12px;
        }
        p {
            line-height: 1.55;
        }
        ul {
            line-height: 1.7;
        }
        a.button {
            display: inline-block;
            margin: 8px 8px 0 0;
            padding: 12px 16px;
            border-radius: 6px;
            background: #1f5fa8;
            color: #fff;
            text-decoration: none;
            font-weight: 700;
        }
        a.secondary {
            background: #4f5f6f;
        }
    </style>
</head>
<body>
    <main aria-labelledby="page-title">
        <div class="status <?= e($status) ?>" role="status"><?= e(ucfirst($status)) ?></div>
        <h1 id="page-title"><?= e($heading) ?></h1>
        <p><?= e($message) ?></p>

        <?php if ($details): ?>
        <h2>Details</h2>
        <ul>
            <?php foreach ($details as $detail): ?>
            <li><?= e($detail) ?></li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>

        <p>
            <a class="button" href="<?= e($primaryUrl) ?>"><?= e($primaryLabel) ?></a>
            <?php if ($secondaryUrl !== '' && $secondaryLabel !== ''): ?>
            <a class="button secondary" href="<?= e($secondaryUrl) ?>"><?= e($secondaryLabel) ?></a>
            <?php endif; ?>
        </p>

        <p>If you are using the Windows Flex Phone app, return to the app after this page and choose Finish sign in.</p>
    </main>
</body>
</html>
