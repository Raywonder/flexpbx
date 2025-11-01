<?php
/**
 * FlexPBX Admin Authentication Check
 * Include this file at the top of ALL admin pages
 *
 * Features:
 * - Verifies admin is logged in
 * - Network detection (Tailscale, WireGuard, local network)
 * - Dynamic session timeout based on network
 * - Session hijacking protection
 * - IP tracking and validation
 * - CSRF token generation
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Network Detection Functions
 */

// Get client IP address (handles proxy headers)
function get_client_ip() {
    $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];

    foreach ($ip_keys as $key) {
        if (isset($_SERVER[$key])) {
            $ip = $_SERVER[$key];
            // Handle multiple IPs in X-Forwarded-For
            if (strpos($ip, ',') !== false) {
                $ips = explode(',', $ip);
                $ip = trim($ips[0]);
            }
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }

    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

// Get public IP (from headers or same as client IP)
function get_public_ip() {
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $public_ip = trim(end($ips)); // Last IP is usually the public one
        if (filter_var($public_ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return $public_ip;
        }
    }

    $client_ip = get_client_ip();
    if (filter_var($client_ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        return $client_ip;
    }

    return null; // No public IP detected
}

// Check if IP is in CIDR range
function ip_in_range($ip, $cidr) {
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        return false;
    }

    list($subnet, $mask) = explode('/', $cidr);

    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $ip_long = ip2long($ip);
        $subnet_long = ip2long($subnet);
        $mask_long = -1 << (32 - (int)$mask);
        $subnet_long &= $mask_long;
        return ($ip_long & $mask_long) == $subnet_long;
    }

    // IPv6 support would go here
    return false;
}

// Detect network type
function detect_network_type($ip) {
    $network = [
        'type' => 'public',
        'name' => 'Public Internet',
        'trusted' => false,
        'color' => '#ff6b6b'
    ];

    // Tailscale network (100.64.0.0/10)
    if (ip_in_range($ip, '100.64.0.0/10')) {
        $network = [
            'type' => 'tailscale',
            'name' => 'Tailscale VPN',
            'trusted' => true,
            'color' => '#4ade80'
        ];
    }
    // WireGuard default network (10.8.0.0/24) - configurable
    elseif (ip_in_range($ip, '10.8.0.0/24')) {
        $network = [
            'type' => 'wireguard',
            'name' => 'WireGuard VPN',
            'trusted' => true,
            'color' => '#4ade80'
        ];
    }
    // Local network ranges
    elseif (ip_in_range($ip, '192.168.0.0/16') ||
            ip_in_range($ip, '10.0.0.0/8') ||
            ip_in_range($ip, '172.16.0.0/12')) {
        $network = [
            'type' => 'local',
            'name' => 'Local Network',
            'trusted' => true,
            'color' => '#4ade80'
        ];
    }
    // Localhost
    elseif ($ip === '127.0.0.1' || $ip === '::1') {
        $network = [
            'type' => 'localhost',
            'name' => 'Localhost',
            'trusted' => true,
            'color' => '#4ade80'
        ];
    }

    return $network;
}

/**
 * Session Security
 */

// Get current connection info
$client_ip = get_client_ip();
$public_ip = get_public_ip();
$network_info = detect_network_type($client_ip);

// Store in session for easy access
$_SESSION['client_ip'] = $client_ip;
$_SESSION['public_ip'] = $public_ip;
$_SESSION['network_type'] = $network_info['type'];
$_SESSION['network_name'] = $network_info['name'];
$_SESSION['network_trusted'] = $network_info['trusted'];
$_SESSION['network_color'] = $network_info['color'];

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    // Save current URL for redirect after login
    $current_url = $_SERVER['REQUEST_URI'];

    // Redirect to login with return URL
    header('Location: /admin/login.php?redirect=' . urlencode($current_url));
    exit;
}

/**
 * Session Hijacking Protection
 */

// Check if IP changed (only enforce on public networks)
if (isset($_SESSION['login_ip'])) {
    if ($_SESSION['login_ip'] !== $client_ip && !$network_info['trusted']) {
        // IP changed on public network - potential session hijacking
        $admin_username = $_SESSION['admin_username'] ?? 'Unknown';

        error_log("FlexPBX SECURITY: IP change detected for admin {$admin_username}. Old: {$_SESSION['login_ip']}, New: {$client_ip}");

        // Destroy session
        session_destroy();

        // Redirect to login with security message
        header('Location: /admin/login.php?security=ip_changed');
        exit;
    }
} else {
    // Store login IP
    $_SESSION['login_ip'] = $client_ip;
}

// User agent validation
if (isset($_SESSION['login_user_agent'])) {
    if ($_SESSION['login_user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? '')) {
        // User agent changed - potential session hijacking
        $admin_username = $_SESSION['admin_username'] ?? 'Unknown';

        error_log("FlexPBX SECURITY: User agent change detected for admin {$admin_username}");

        // On trusted networks, just log it, don't logout
        if (!$network_info['trusted']) {
            session_destroy();
            header('Location: /admin/login.php?security=ua_changed');
            exit;
        }
    }
} else {
    $_SESSION['login_user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
}

/**
 * Dynamic Session Timeout with Idle Timeout Support
 */

// Check if "Stay logged in" is enabled
$remember_login = $_SESSION['remember_login'] ?? true; // Default to true for existing sessions
$idle_timeout = $_SESSION['idle_timeout'] ?? 0;

if (!$remember_login && $idle_timeout > 0) {
    // User opted for short session with idle timeout
    if (isset($_SESSION['last_activity'])) {
        $elapsed_time = time() - $_SESSION['last_activity'];

        if ($elapsed_time > $idle_timeout) {
            // Session timed out due to inactivity
            $admin_username = $_SESSION['admin_username'] ?? 'Unknown';

            // Log the timeout
            error_log("FlexPBX: Admin session idle timeout for user: {$admin_username} (inactive for {$elapsed_time}s)");

            // Destroy session
            session_destroy();

            // Redirect to login with timeout message
            header('Location: /admin/login.php?timeout=1');
            exit;
        }
    }

    // Update last activity timestamp for idle timeout tracking
    $_SESSION['last_activity'] = time();
} else {
    // Extended session - use network-based timeout
    // Set timeout based on network type
    if ($network_info['trusted']) {
        // 30 days for trusted networks
        $timeout_duration = 30 * 24 * 60 * 60;
    } else {
        // 7 days for public networks (reduced from 24 hours for security)
        $timeout_duration = 7 * 24 * 60 * 60;
    }

    if (isset($_SESSION['last_activity'])) {
        $elapsed_time = time() - $_SESSION['last_activity'];

        if ($elapsed_time > $timeout_duration) {
            // Session timed out
            $admin_username = $_SESSION['admin_username'] ?? 'Unknown';

            // Log the timeout
            error_log("FlexPBX: Admin session timeout for user: {$admin_username} (network: {$network_info['name']})");

            // Destroy session
            session_destroy();

            // Redirect to login with timeout message
            header('Location: /admin/login.php?timeout=1');
            exit;
        }
    }

    // Update last activity timestamp
    $_SESSION['last_activity'] = time();
}

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Helper function to verify CSRF token
function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Helper function to get CSRF token
function get_csrf_token() {
    return $_SESSION['csrf_token'] ?? '';
}

// Helper function to create CSRF hidden input
function csrf_field() {
    $token = get_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

// Set security headers
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');

// Store admin info in easily accessible variables
$admin_username = $_SESSION['admin_username'] ?? 'Unknown';
$admin_role = $_SESSION['admin_role'] ?? 'admin';
$admin_email = $_SESSION['admin_email'] ?? '';

// Calculate session info for display
if (!$remember_login && $idle_timeout > 0) {
    // Idle timeout session
    $session_time_remaining = $idle_timeout - (time() - ($_SESSION['last_activity'] ?? time()));
    $session_expires_at = date('Y-m-d H:i:s', time() + $session_time_remaining);
    $session_type = 'idle_timeout';
    $session_type_label = '30-Min Idle Timeout';
    $session_type_color = '#fbbf24'; // Yellow
} else {
    // Extended session
    $session_time_remaining = $timeout_duration - (time() - ($_SESSION['last_activity'] ?? time()));
    $session_expires_at = date('Y-m-d H:i:s', time() + $session_time_remaining);
    $session_type = 'extended';
    if ($network_info['trusted']) {
        $session_type_label = '30-Day Session';
    } else {
        $session_type_label = '7-Day Session';
    }
    $session_type_color = '#4ade80'; // Green
}

// Store session info in session for JavaScript access
$_SESSION['session_type'] = $session_type;
$_SESSION['session_type_label'] = $session_type_label;
$_SESSION['session_time_remaining'] = $session_time_remaining;
