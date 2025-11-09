<?php
/**
 * FlexPBX User Portal - Authentication Check
 * Include this at the top of every user portal page that requires authentication
 *
 * Usage:
 * require_once __DIR__ . '/user_auth_check.php';
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
$is_user_logged_in = isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;
$user_extension = $_SESSION['user_extension'] ?? null;

// Redirect to login if not authenticated
if (!$is_user_logged_in || empty($user_extension)) {
    // Store the current page URL for redirect after login
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '/user-portal/';

    // Clear any partial session data
    session_unset();

    // Redirect to login page
    header('Location: /user-portal/login.php');
    exit;
}

// Verify the extension exists in the system
$user_file = '/home/flexpbxuser/users/user_' . $user_extension . '.json';
if (!file_exists($user_file)) {
    // Extension no longer exists - force logout
    session_unset();
    session_destroy();
    header('Location: /user-portal/login.php?error=account_not_found');
    exit;
}

// Optional: Load user data for use in the page
$user_data = json_decode(file_get_contents($user_file), true);
$user_username = $_SESSION['user_username'] ?? $user_data['username'] ?? $user_extension;
$user_email = $user_data['email'] ?? '';

/**
 * Session Timeout with Idle Timeout Support
 */

// Check if "Stay logged in" is enabled
$remember_login = $_SESSION['remember_login'] ?? true; // Default to true for existing sessions
$idle_timeout = $_SESSION['idle_timeout'] ?? 0;

if (!$remember_login && $idle_timeout > 0) {
    // User opted for short session with idle timeout (30 minutes)
    if (isset($_SESSION['last_activity'])) {
        $elapsed_time = time() - $_SESSION['last_activity'];

        if ($elapsed_time > $idle_timeout) {
            // Session timed out due to inactivity
            error_log("FlexPBX: User session idle timeout for extension: {$user_extension} (inactive for {$elapsed_time}s)");

            // Destroy session
            session_unset();
            session_destroy();

            // Redirect to login with timeout message
            header('Location: /user-portal/login.php?error=session_expired');
            exit;
        }
    }

    // Update last activity timestamp for idle timeout tracking
    $_SESSION['last_activity'] = time();

    // Calculate session info
    $session_time_remaining = $idle_timeout - (time() - ($_SESSION['last_activity'] ?? time()));
    $session_type = 'idle_timeout';
    $session_type_label = '30-Min Idle Timeout';
    $session_type_color = '#fbbf24'; // Yellow
} else {
    // Extended session (30 days)
    $timeout_duration = 30 * 24 * 60 * 60; // 30 days

    if (isset($_SESSION['last_activity'])) {
        $elapsed_time = time() - $_SESSION['last_activity'];

        if ($elapsed_time > $timeout_duration) {
            // Session timed out
            error_log("FlexPBX: User session timeout for extension: {$user_extension}");

            // Destroy session
            session_unset();
            session_destroy();

            // Redirect to login with timeout message
            header('Location: /user-portal/login.php?error=session_expired');
            exit;
        }
    }

    // Update last activity timestamp
    $_SESSION['last_activity'] = time();

    // Calculate session info
    $session_time_remaining = $timeout_duration - (time() - ($_SESSION['last_activity'] ?? time()));
    $session_type = 'extended';
    $session_type_label = '30-Day Session';
    $session_type_color = '#4ade80'; // Green
}

// Store session info for JavaScript access
$_SESSION['session_type'] = $session_type;
$_SESSION['session_type_label'] = $session_type_label;
$_SESSION['session_time_remaining'] = $session_time_remaining;
