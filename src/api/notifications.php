<?php
/**
 * FlexPBX Notifications API
 * Session management, alerts, and notifications
 * Created: October 17, 2025
 *
 * @requires PHP 8.0+
 * @recommended PHP 8.1 or 8.2
 */

// Check PHP version (minimum 8.0)
if (version_compare(PHP_VERSION, '8.0.0', '<')) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'PHP 8.0 or higher required',
        'current_version' => PHP_VERSION,
        'minimum_version' => '8.0.0',
        'recommended_versions' => ['8.1', '8.2']
    ]);
    exit;
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

session_start();

$path = $_GET['path'] ?? '';

switch ($path) {
    case 'ping':
        handlePing();
        break;

    case 'check':
        handleCheck();
        break;

    case 'send':
        handleSend();
        break;

    case 'mark_read':
        handleMarkRead();
        break;

    case 'logout':
        handleLogout();
        break;

    case 'settings':
        handleSettings();
        break;

    default:
        respond(false, 'Invalid path', null, 404);
        break;
}

/**
 * Keep-alive ping - updates session timestamp
 */
function handlePing() {
    if (!isset($_SESSION['user_extension']) && !isset($_SESSION['admin_logged_in'])) {
        respond(false, 'Not logged in', ['logged_in' => false], 401);
        return;
    }

    // Update last activity
    $_SESSION['last_activity'] = time();

    $user_type = isset($_SESSION['admin_logged_in']) ? 'admin' : 'user';
    $identifier = isset($_SESSION['user_extension']) ? $_SESSION['user_extension'] : 'admin';

    // Check for new notifications
    $notifications = getNotifications($identifier);
    $unread_count = countUnreadNotifications($identifier);

    respond(true, 'Session alive', [
        'logged_in' => true,
        'user_type' => $user_type,
        'identifier' => $identifier,
        'session_duration' => time() - ($_SESSION['login_time'] ?? time()),
        'last_activity' => $_SESSION['last_activity'],
        'unread_notifications' => $unread_count,
        'recent_notifications' => array_slice($notifications, 0, 5)
    ]);
}

/**
 * Check for notifications
 */
function handleCheck() {
    if (!isset($_SESSION['user_extension']) && !isset($_SESSION['admin_logged_in'])) {
        respond(false, 'Not logged in', ['logged_in' => false], 401);
        return;
    }

    $identifier = isset($_SESSION['user_extension']) ? $_SESSION['user_extension'] : 'admin';
    $notifications = getNotifications($identifier);
    $unread_count = countUnreadNotifications($identifier);

    respond(true, 'Notifications retrieved', [
        'notifications' => $notifications,
        'unread_count' => $unread_count,
        'total_count' => count($notifications)
    ]);
}

/**
 * Send notification
 */
function handleSend() {
    $to = $_POST['to'] ?? $_GET['to'] ?? null;
    $type = $_POST['type'] ?? $_GET['type'] ?? 'info';
    $message = $_POST['message'] ?? $_GET['message'] ?? null;
    $title = $_POST['title'] ?? $_GET['title'] ?? 'Notification';

    if (!$to || !$message) {
        respond(false, 'Recipient and message required');
        return;
    }

    $notification = [
        'id' => uniqid('notif_'),
        'to' => $to,
        'type' => $type,
        'title' => $title,
        'message' => $message,
        'timestamp' => date('c'),
        'read' => false,
        'from' => 'system'
    ];

    if (saveNotification($notification)) {
        respond(true, 'Notification sent', $notification);
    } else {
        respond(false, 'Failed to save notification', null, 500);
    }
}

/**
 * Mark notification as read
 */
function handleMarkRead() {
    $notif_id = $_POST['id'] ?? $_GET['id'] ?? null;

    if (!$notif_id) {
        respond(false, 'Notification ID required');
        return;
    }

    if (!isset($_SESSION['user_extension']) && !isset($_SESSION['admin_logged_in'])) {
        respond(false, 'Not logged in', null, 401);
        return;
    }

    $identifier = isset($_SESSION['user_extension']) ? $_SESSION['user_extension'] : 'admin';

    if (markNotificationRead($identifier, $notif_id)) {
        respond(true, 'Notification marked as read');
    } else {
        respond(false, 'Failed to mark notification', null, 500);
    }
}

/**
 * Logout from browser session (SIP stays logged in)
 */
function handleLogout() {
    $user_type = isset($_SESSION['admin_logged_in']) ? 'admin' : 'user';
    $identifier = isset($_SESSION['user_extension']) ? $_SESSION['user_extension'] : 'admin';

    // Send logout notification
    $notification = [
        'id' => uniqid('notif_'),
        'to' => $identifier,
        'type' => 'success',
        'title' => 'Logged Out',
        'message' => 'You have been logged out of the web portal. Your SIP client remains connected.',
        'timestamp' => date('c'),
        'read' => false,
        'from' => 'system'
    ];
    saveNotification($notification);

    // Destroy session
    session_destroy();

    respond(true, 'Logged out successfully', [
        'user_type' => $user_type,
        'identifier' => $identifier,
        'message' => 'Browser session ended. SIP client still connected.'
    ]);
}

/**
 * Get/update notification settings
 */
function handleSettings() {
    if (!isset($_SESSION['user_extension']) && !isset($_SESSION['admin_logged_in'])) {
        respond(false, 'Not logged in', null, 401);
        return;
    }

    $identifier = isset($_SESSION['user_extension']) ? $_SESSION['user_extension'] : 'admin';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Update settings
        $settings = [
            'login_alerts' => $_POST['login_alerts'] ?? true,
            'session_reminders' => $_POST['session_reminders'] ?? true,
            'reminder_interval' => intval($_POST['reminder_interval'] ?? 30), // minutes
            'call_notifications' => $_POST['call_notifications'] ?? true,
            'voicemail_alerts' => $_POST['voicemail_alerts'] ?? true,
            'queue_alerts' => $_POST['queue_alerts'] ?? false
        ];

        if (saveNotificationSettings($identifier, $settings)) {
            respond(true, 'Settings updated', $settings);
        } else {
            respond(false, 'Failed to save settings', null, 500);
        }
    } else {
        // Get settings
        $settings = getNotificationSettings($identifier);
        respond(true, 'Settings retrieved', $settings);
    }
}

/**
 * Get notifications for user
 */
function getNotifications($identifier) {
    $file = "/home/flexpbxuser/notifications/{$identifier}.json";

    if (!file_exists($file)) {
        return [];
    }

    $data = json_decode(file_get_contents($file), true);
    return $data['notifications'] ?? [];
}

/**
 * Count unread notifications
 */
function countUnreadNotifications($identifier) {
    $notifications = getNotifications($identifier);
    return count(array_filter($notifications, function($n) {
        return !$n['read'];
    }));
}

/**
 * Save notification
 */
function saveNotification($notification) {
    $dir = '/home/flexpbxuser/notifications';
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }

    $file = "{$dir}/{$notification['to']}.json";

    $data = [];
    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true);
    }

    if (!isset($data['notifications'])) {
        $data['notifications'] = [];
    }

    // Add notification
    array_unshift($data['notifications'], $notification);

    // Keep only last 100 notifications
    $data['notifications'] = array_slice($data['notifications'], 0, 100);

    return file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT)) !== false;
}

/**
 * Mark notification as read
 */
function markNotificationRead($identifier, $notif_id) {
    $file = "/home/flexpbxuser/notifications/{$identifier}.json";

    if (!file_exists($file)) {
        return false;
    }

    $data = json_decode(file_get_contents($file), true);

    foreach ($data['notifications'] as &$notif) {
        if ($notif['id'] === $notif_id) {
            $notif['read'] = true;
            break;
        }
    }

    return file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT)) !== false;
}

/**
 * Get notification settings
 */
function getNotificationSettings($identifier) {
    $file = "/home/flexpbxuser/notification_settings/{$identifier}.json";

    if (!file_exists($file)) {
        // Default settings
        return [
            'login_alerts' => true,
            'session_reminders' => true,
            'reminder_interval' => 30,
            'call_notifications' => true,
            'voicemail_alerts' => true,
            'queue_alerts' => false
        ];
    }

    return json_decode(file_get_contents($file), true);
}

/**
 * Save notification settings
 */
function saveNotificationSettings($identifier, $settings) {
    $dir = '/home/flexpbxuser/notification_settings';
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }

    $file = "{$dir}/{$identifier}.json";
    return file_put_contents($file, json_encode($settings, JSON_PRETTY_PRINT)) !== false;
}

function respond($success, $message, $data = null, $httpCode = 200) {
    http_response_code($httpCode);

    $response = [
        'success' => $success,
        'message' => $message,
        'timestamp' => date('c')
    ];

    if ($data !== null) {
        if (is_array($data)) {
            $response = array_merge($response, $data);
        } else {
            $response['data'] = $data;
        }
    }

    echo json_encode($response, JSON_PRETTY_PRINT);
    exit;
}
?>
