<?php
/**
 * FlexPBX Extend Session API
 * Resets the last_activity timestamp to extend the session
 */

header('Content-Type: application/json');

// Start session
session_start();

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Only POST requests are allowed'
    ]);
    exit;
}

// Check if logged in (admin or user)
$is_admin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
$is_user = isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;

if (!$is_admin && !$is_user) {
    echo json_encode([
        'success' => false,
        'message' => 'Not logged in'
    ]);
    exit;
}

// Reset last activity timestamp
$_SESSION['last_activity'] = time();

// Update session expiry if applicable
$idle_timeout = $_SESSION['idle_timeout'] ?? 0;
if ($idle_timeout > 0) {
    $_SESSION['session_expires'] = time() + $idle_timeout;
}

// Log the extension
$username = $is_admin ? ($_SESSION['admin_username'] ?? 'Unknown') : ($_SESSION['user_extension'] ?? 'Unknown');
$user_type = $is_admin ? 'admin' : 'user';
error_log("FlexPBX: Session extended for {$user_type}: {$username}");

// Return success
echo json_encode([
    'success' => true,
    'message' => 'Session extended successfully',
    'new_last_activity' => $_SESSION['last_activity'],
    'session_expires' => $_SESSION['session_expires'] ?? null
]);
