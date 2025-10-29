<?php
/**
 * FlexPBX API Authentication
 * Simple session-based authentication for APIs
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if user is authenticated
 * Returns authentication status and user info
 */
function checkAuth() {
    // Check admin session
    if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
        return [
            'authenticated' => true,
            'user_type' => 'admin',
            'username' => $_SESSION['admin_username'] ?? 'admin',
            'role' => $_SESSION['admin_role'] ?? 'admin'
        ];
    }

    // Check user session
    if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true) {
        return [
            'authenticated' => true,
            'user_type' => 'user',
            'username' => $_SESSION['user_username'] ?? 'user',
            'extension' => $_SESSION['user_extension'] ?? null,
            'role' => $_SESSION['user_role'] ?? 'user'
        ];
    }

    // Check flexpbxuser session (from bug tracker or admin panel)
    if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
        return [
            'authenticated' => true,
            'user_type' => 'authenticated',
            'username' => $_SESSION['username'] ?? 'user',
            'role' => $_SESSION['role'] ?? 'user'
        ];
    }

    // No authentication
    return [
        'authenticated' => false,
        'user_type' => null,
        'username' => null,
        'role' => 'guest'
    ];
}

/**
 * Require authentication
 * Exits with 401 if not authenticated
 */
function requireAuth() {
    $auth = checkAuth();
    if (!$auth['authenticated']) {
        http_response_code(401);
        echo json_encode([
            'error' => 'Authentication required',
            'message' => 'Please log in to access this resource',
            'success' => false
        ]);
        exit;
    }
    return $auth;
}

/**
 * Require admin role
 * Exits with 403 if not admin
 */
function requireAdmin() {
    $auth = checkAuth();
    if (!$auth['authenticated']) {
        http_response_code(401);
        echo json_encode([
            'error' => 'Authentication required',
            'success' => false
        ]);
        exit;
    }

    $admin_roles = ['superadmin', 'admin', 'manager'];
    if (!in_array($auth['role'], $admin_roles) && $auth['user_type'] !== 'admin') {
        http_response_code(403);
        echo json_encode([
            'error' => 'Admin access required',
            'success' => false
        ]);
        exit;
    }

    return $auth;
}

/**
 * Check if user is admin
 */
function isAdmin() {
    $auth = checkAuth();
    if (!$auth['authenticated']) {
        return false;
    }

    $admin_roles = ['superadmin', 'admin', 'manager'];
    return in_array($auth['role'], $admin_roles) || $auth['user_type'] === 'admin';
}

/**
 * Log API access (simple file-based logging for now)
 */
function logApiAccess($endpoint, $method, $status_code, $user = null) {
    $log_file = '/var/log/flexpbx-api.log';
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $user_str = $user ? $user['username'] : 'unauthenticated';

    $log_entry = "[$timestamp] $ip - $user_str - $method $endpoint - Status: $status_code - User-Agent: $user_agent\n";

    // Append to log file
    @file_put_contents($log_file, $log_entry, FILE_APPEND);
}

/**
 * Helper function to generate secure password
 */
function generatePassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    $password = '';
    $chars_length = strlen($chars);

    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, $chars_length - 1)];
    }

    return $password;
}

/**
 * Sanitize input
 */
function sanitizeInput($input) {
    if (is_array($input)) {
        foreach ($input as $key => $value) {
            $input[$key] = sanitizeInput($value);
        }
        return $input;
    }

    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate extension number
 */
function validateExtension($extension) {
    return preg_match('/^\d{3,5}$/', $extension);
}

/**
 * Validate SIP password
 */
function validateSipPassword($password) {
    // At least 8 characters, contains letters and numbers
    return strlen($password) >= 8 && preg_match('/[A-Za-z]/', $password) && preg_match('/[0-9]/', $password);
}

// Log this request
$auth = checkAuth();
logApiAccess(
    $_SERVER['REQUEST_URI'] ?? 'unknown',
    $_SERVER['REQUEST_METHOD'] ?? 'unknown',
    http_response_code() ?: 200,
    $auth['authenticated'] ? $auth : null
);
?>
