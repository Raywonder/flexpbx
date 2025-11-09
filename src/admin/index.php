<?php
/**
 * FlexPBX Admin Portal - Entry Point
 * Routes to dashboard if logged in, login page if not
 *
 * @version 2.0.0
 * @updated 2025-10-24
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if admin is logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    // User is logged in, redirect to dashboard
    header('Location: /admin/dashboard.php');
    exit;
} else {
    // User is not logged in, redirect to login
    header('Location: /admin/login.php');
    exit;
}
