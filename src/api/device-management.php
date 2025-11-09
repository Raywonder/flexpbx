<?php
/**
 * FlexPBX Device Management API
 * Manage remembered devices and tokens
 * Supports PHP 8.0+
 */

// PHP version check
if (version_compare(PHP_VERSION, '8.0.0', '<')) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'PHP 8.0 or higher required. Current version: ' . PHP_VERSION
    ]);
    exit;
}

session_start();
header('Content-Type: application/json');

// Check authentication
$is_admin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
$is_user = isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;

if (!$is_admin && !$is_user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

/**
 * Get list of remembered devices
 */
if ($action === 'list') {
    if ($is_admin) {
        $username = $_SESSION['admin_username'];
        $account_file = "/home/flexpbxuser/admins/admin_{$username}.json";
        $account_type = 'admin';
    } else {
        $extension = $_SESSION['user_extension'];
        $account_file = "/home/flexpbxuser/users/user_{$extension}.json";
        $account_type = 'user';
    }

    if (!file_exists($account_file)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Account not found']);
        exit;
    }

    $account_data = json_decode(file_get_contents($account_file), true);
    $remember_tokens = $account_data['remember_tokens'] ?? [];

    // Clean up expired tokens
    $active_tokens = [];
    $current_time = time();

    foreach ($remember_tokens as $token) {
        if (isset($token['expires']) && $token['expires'] > $current_time) {
            $active_tokens[] = [
                'created' => date('Y-m-d H:i:s', $token['created']),
                'expires' => date('Y-m-d H:i:s', $token['expires']),
                'ip' => $token['ip'] ?? 'unknown',
                'user_agent' => $token['user_agent'] ?? 'unknown',
                'is_current' => ($_SERVER['REMOTE_ADDR'] === ($token['ip'] ?? '')) &&
                              ($_SERVER['HTTP_USER_AGENT'] === ($token['user_agent'] ?? ''))
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'account_type' => $account_type,
        'devices' => $active_tokens,
        'total_devices' => count($active_tokens)
    ]);
    exit;
}

/**
 * Revoke a specific device
 */
if ($action === 'revoke') {
    $ip = $_POST['ip'] ?? '';
    $user_agent = $_POST['user_agent'] ?? '';

    if (empty($ip) || empty($user_agent)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'IP and user_agent required']);
        exit;
    }

    if ($is_admin) {
        $username = $_SESSION['admin_username'];
        $account_file = "/home/flexpbxuser/admins/admin_{$username}.json";
        $cookie_name = 'flexpbx_remember_admin';
    } else {
        $extension = $_SESSION['user_extension'];
        $account_file = "/home/flexpbxuser/users/user_{$extension}.json";
        $cookie_name = 'flexpbx_remember_user';
    }

    if (!file_exists($account_file)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Account not found']);
        exit;
    }

    $account_data = json_decode(file_get_contents($account_file), true);
    $remember_tokens = $account_data['remember_tokens'] ?? [];

    // Filter out the token matching the IP and user agent
    $updated_tokens = array_filter($remember_tokens, function($token) use ($ip, $user_agent) {
        return !($token['ip'] === $ip && $token['user_agent'] === $user_agent);
    });

    // Re-index array
    $account_data['remember_tokens'] = array_values($updated_tokens);

    // Save updated account data
    file_put_contents($account_file, json_encode($account_data, JSON_PRETTY_PRINT));

    // If this is the current device, delete the cookie
    if ($_SERVER['REMOTE_ADDR'] === $ip && $_SERVER['HTTP_USER_AGENT'] === $user_agent) {
        setcookie($cookie_name, '', time() - 3600, '/');
    }

    echo json_encode([
        'success' => true,
        'message' => 'Device revoked successfully',
        'devices_remaining' => count($updated_tokens)
    ]);
    exit;
}

/**
 * Revoke all devices
 */
if ($action === 'revoke_all') {
    if ($is_admin) {
        $username = $_SESSION['admin_username'];
        $account_file = "/home/flexpbxuser/admins/admin_{$username}.json";
        $cookie_name = 'flexpbx_remember_admin';
    } else {
        $extension = $_SESSION['user_extension'];
        $account_file = "/home/flexpbxuser/users/user_{$extension}.json";
        $cookie_name = 'flexpbx_remember_user';
    }

    if (!file_exists($account_file)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Account not found']);
        exit;
    }

    $account_data = json_decode(file_get_contents($account_file), true);

    // Remove all remember tokens
    $account_data['remember_tokens'] = [];

    // Save updated account data
    file_put_contents($account_file, json_encode($account_data, JSON_PRETTY_PRINT));

    // Delete the cookie
    setcookie($cookie_name, '', time() - 3600, '/');

    echo json_encode([
        'success' => true,
        'message' => 'All devices revoked successfully'
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'error' => 'Invalid action']);
