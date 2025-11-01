<?php
/**
 * FlexPBX User Portal - Logout
 * Securely logs out the user and clears session data
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get extension for cleanup before destroying session
$extension = $_SESSION['user_extension'] ?? null;

// Clear remember me cookie if it exists
if (isset($_COOKIE['flexpbx_remember_user'])) {
    setcookie(
        'flexpbx_remember_user',
        '',
        time() - 3600,
        '/',
        '',
        isset($_SERVER['HTTPS']),
        true
    );
}

// If remember token exists, remove it from user file
if ($extension && isset($_COOKIE['flexpbx_remember_user'])) {
    $user_file = '/home/flexpbxuser/users/user_' . $extension . '.json';
    if (file_exists($user_file)) {
        $user_data = json_decode(file_get_contents($user_file), true);

        // Clear all remember tokens for security
        if (isset($user_data['remember_tokens'])) {
            unset($user_data['remember_tokens']);
            file_put_contents($user_file, json_encode($user_data, JSON_PRETTY_PRINT));
        }
    }
}

// Clear all session variables
session_unset();

// Destroy the session
session_destroy();

// Clear session cookie
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

// Redirect to login page with logout message
header('Location: /user-portal/login.php?logout=success');
exit;
