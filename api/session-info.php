<?php
/**
 * FlexPBX Session Info API
 * Returns current session status and time remaining
 */

header('Content-Type: application/json');

// Start session
session_start();

// Check if logged in (admin or user)
$is_admin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
$is_user = isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;

if (!$is_admin && !$is_user) {
    echo json_encode([
        'success' => false,
        'logged_in' => false,
        'message' => 'Not logged in'
    ]);
    exit;
}

// Get session type info
$session_type = $_SESSION['session_type'] ?? 'extended';
$session_type_label = $_SESSION['session_type_label'] ?? '30-Day Session';
$idle_timeout = $_SESSION['idle_timeout'] ?? 0;
$remember_login = $_SESSION['remember_login'] ?? true;

// Calculate time remaining
if ($session_type === 'idle_timeout') {
    // Idle timeout session - calculate time since last activity
    $last_activity = $_SESSION['last_activity'] ?? time();
    $time_remaining = $idle_timeout - (time() - $last_activity);
    $time_remaining = max(0, $time_remaining); // Don't go negative
} else {
    // Extended session
    $session_expires = $_SESSION['session_expires'] ?? (time() + (30 * 24 * 60 * 60));
    $time_remaining = $session_expires - time();
    $time_remaining = max(0, $time_remaining);
}

// Get user info
if ($is_admin) {
    $username = $_SESSION['admin_username'] ?? 'Unknown';
    $user_type = 'admin';
} else {
    $username = $_SESSION['user_username'] ?? $_SESSION['user_extension'] ?? 'Unknown';
    $user_type = 'user';
}

// Return session info
echo json_encode([
    'success' => true,
    'logged_in' => true,
    'user_type' => $user_type,
    'username' => $username,
    'session_type' => $session_type,
    'session_type_label' => $session_type_label,
    'remember_login' => $remember_login,
    'idle_timeout' => $idle_timeout,
    'time_remaining' => $time_remaining,
    'last_activity' => $_SESSION['last_activity'] ?? time(),
    'network_type' => $_SESSION['network_type'] ?? 'unknown',
    'network_name' => $_SESSION['network_name'] ?? 'Unknown Network'
]);
