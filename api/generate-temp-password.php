<?php
/**
 * FlexPBX - Temporary Password Generator
 * Generates one-time passwords for users and admins with default/placeholder emails
 * Enhances security by rotating passwords and limiting exposure
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Configuration
$temp_passwords_dir = '/home/flexpbxuser/temp_passwords';
$admins_dir = '/home/flexpbxuser/admins';
$users_dir = '/home/flexpbxuser/users';
$log_file = '/home/flexpbxuser/logs/temp_password.log';

// Ensure directories exist
if (!file_exists($temp_passwords_dir)) {
    mkdir($temp_passwords_dir, 0750, true);
}

if (!file_exists(dirname($log_file))) {
    mkdir(dirname($log_file), 0750, true);
}

// Logging function
function log_temp_password($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $log_entry = "[$timestamp] [$ip] $message\n";
    @file_put_contents($log_file, $log_entry, FILE_APPEND);
}

// Clean up expired passwords (older than 15 minutes)
$files = glob($temp_passwords_dir . '/temp_*.json');
$cleaned = 0;
foreach ($files as $file) {
    $data = json_decode(file_get_contents($file), true);
    if ($data && isset($data['expires']) && $data['expires'] < time()) {
        unlink($file);
        $cleaned++;
    }
}

if ($cleaned > 0) {
    log_temp_password("Cleaned up $cleaned expired temporary passwords");
}

// Get parameters
$account_type = $_GET['account_type'] ?? 'user';
$identifier = $_GET['identifier'] ?? $_GET['username'] ?? '';

if (empty($identifier)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Identifier (username or extension) required'
    ]);
    exit;
}

// Placeholder emails that indicate account needs setup
$placeholder_emails = [
    '',
    'user@example.com',
    'admin@example.com',
    'noemail@localhost',
    'user@localhost',
    'test@test.com',
    'changeme@example.com',
    'administrator@localhost'
];

$needs_temp_password = false;
$account_data = null;
$account_file = null;

// Check account type and load data
if ($account_type === 'admin') {
    // Admin account
    $admin_file = $admins_dir . '/admin_' . $identifier . '.json';

    if (file_exists($admin_file)) {
        $account_data = json_decode(file_get_contents($admin_file), true);
        $account_file = $admin_file;

        $current_email = $account_data['email'] ?? '';
        $needs_temp_password = empty($current_email) || in_array(strtolower($current_email), $placeholder_emails);
    }
} else {
    // User account - try by extension first
    if (is_numeric($identifier)) {
        $user_file = $users_dir . '/user_' . $identifier . '.json';
        if (file_exists($user_file)) {
            $account_data = json_decode(file_get_contents($user_file), true);
            $account_file = $user_file;
        }
    }

    // If not found, search by username or email
    if (!$account_data && file_exists($users_dir)) {
        $user_files = glob($users_dir . '/user_*.json');
        foreach ($user_files as $file) {
            $temp_data = json_decode(file_get_contents($file), true);
            if ((isset($temp_data['username']) && $temp_data['username'] === $identifier) ||
                (isset($temp_data['extension']) && $temp_data['extension'] === $identifier) ||
                (isset($temp_data['email']) && strtolower($temp_data['email']) === strtolower($identifier))) {
                $account_data = $temp_data;
                $account_file = $file;
                break;
            }
        }
    }

    if ($account_data) {
        $current_email = $account_data['email'] ?? '';
        $needs_temp_password = empty($current_email) || in_array(strtolower($current_email), $placeholder_emails);
    }
}

// Account not found
if (!$account_data || !$account_file) {
    log_temp_password("Account not found: $account_type / $identifier");
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'show_temp_password' => false,
        'message' => 'Account not found'
    ]);
    exit;
}

// Account already configured
if (!$needs_temp_password) {
    log_temp_password("Account already configured (no temp password needed): $account_type / $identifier");
    http_response_code(200);
    echo json_encode([
        'success' => false,
        'show_temp_password' => false,
        'message' => 'Account already configured. Use your personal password or password reset.'
    ]);
    exit;
}

// Generate temporary password based on account type
if ($account_type === 'admin') {
    $temp_password = 'Admin' . rand(1000, 9999);
} else {
    // User account - use extension or first letters of username
    if (isset($account_data['extension'])) {
        $temp_password = 'Ext' . $account_data['extension'];
    } else {
        $temp_password = 'User' . rand(1000, 9999);
    }
}

$temp_password_hash = password_hash($temp_password, PASSWORD_DEFAULT);

// Create expiry (15 minutes from now)
$expires = time() + (15 * 60);

// Save temp password data for logging
$temp_data = [
    'account_type' => $account_type,
    'identifier' => $identifier,
    'password_hash' => $temp_password_hash,
    'password_plain' => $temp_password, // Only for display in temp file
    'created' => time(),
    'expires' => $expires,
    'used' => false,
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
];

$temp_file = $temp_passwords_dir . '/temp_' . $account_type . '_' . $identifier . '_' . time() . '.json';
file_put_contents($temp_file, json_encode($temp_data, JSON_PRETTY_PRINT));
chmod($temp_file, 0640);

// Update account with temp password
$account_data['password'] = $temp_password_hash;
$account_data['temp_password_expires'] = $expires;
$account_data['temp_password_created'] = time();
$account_data['temp_password_ip'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
file_put_contents($account_file, json_encode($account_data, JSON_PRETTY_PRINT));

// Log generation
log_temp_password("Generated temporary password for $account_type: $identifier (expires: " . date('Y-m-d H:i:s', $expires) . ")");

// Return response
http_response_code(200);
echo json_encode([
    'success' => true,
    'show_temp_password' => true,
    'account_type' => $account_type,
    'identifier' => $identifier,
    'username' => $account_data['username'] ?? $identifier,
    'extension' => $account_data['extension'] ?? null,
    'password' => $temp_password,
    'expires' => $expires,
    'expires_in_seconds' => $expires - time(),
    'expires_at' => date('Y-m-d H:i:s', $expires),
    'message' => 'Temporary password generated. Valid for 15 minutes.'
]);
