<?php
/**
 * FlexPBX Admin Logout
 * Securely logs out admin user and clears all session data
 */

session_start();

// Store username for logging before destroying session
$admin_username = $_SESSION['admin_username'] ?? 'Unknown';
$client_ip = $_SESSION['client_ip'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$network_type = $_SESSION['network_name'] ?? 'Unknown Network';

// Log the logout
error_log("FlexPBX: Admin logout - User: {$admin_username}, IP: {$client_ip}, Network: {$network_type}");

// Clear remember me cookie if it exists
if (isset($_COOKIE['flexpbx_remember_admin'])) {
    setcookie(
        'flexpbx_remember_admin',
        '',
        time() - 3600,
        '/',
        '',
        isset($_SERVER['HTTPS']),
        true
    );
}

// Unset all session variables
$_SESSION = array();

// Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(
        session_name(),
        '',
        time() - 3600,
        '/',
        '',
        isset($_SERVER['HTTPS']),
        true
    );
}

// Destroy the session
session_destroy();

// Redirect to login with logout message
header('Location: /admin/login.php?logout=success');
exit;
