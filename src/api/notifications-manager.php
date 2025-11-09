<?php
/**
 * FlexPBX Comprehensive Notifications Manager API
 * Database-backed role-based notification system
 *
 * @version 1.0.0
 * @date 2025-11-06
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// Prevent direct access
define('FLEXPBX_INIT', true);

// Load database configuration
require_once __DIR__ . '/../config/database.php';

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

// Get user info from session/auth
session_start();
$current_user_id = $_SESSION['user_id'] ?? $_SESSION['user_extension'] ?? $_GET['user_id'] ?? null;
$current_user_role = $_SESSION['role'] ?? $_SESSION['admin_logged_in'] ? 'admin' : 'user';

try {
    switch ($action) {
        case 'send':
            handleSendNotification($pdo, $current_user_id, $current_user_role);
            break;

        case 'list':
            handleListNotifications($pdo, $current_user_id);
            break;

        case 'count':
            handleCountUnread($pdo, $current_user_id);
            break;

        case 'mark_read':
            handleMarkRead($pdo, $current_user_id);
            break;

        case 'mark_unread':
            handleMarkUnread($pdo, $current_user_id);
            break;

        case 'dismiss':
            handleDismiss($pdo, $current_user_id);
            break;

        case 'bulk_read':
            handleBulkRead($pdo, $current_user_id);
            break;

        case 'bulk_dismiss':
            handleBulkDismiss($pdo, $current_user_id);
            break;

        case 'delete':
            handleDeleteNotification($pdo, $current_user_id, $current_user_role);
            break;

        case 'get_preferences':
            handleGetPreferences($pdo, $current_user_id);
            break;

        case 'update_preferences':
            handleUpdatePreferences($pdo, $current_user_id);
            break;

        case 'get_stats':
            handleGetStats($pdo, $current_user_role);
            break;

        case 'scheduled':
            handleScheduledNotifications($pdo, $current_user_role);
            break;

        default:
            throw new Exception('Invalid action');
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Send a new notification
 */
function handleSendNotification($pdo, $user_id, $user_role) {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

    // Validate required fields
    if (empty($input['title']) || empty($input['notification_type'])) {
        throw new Exception('Title and notification_type are required');
    }

    // Start transaction
    $pdo->beginTransaction();

    try {
        // Insert notification
        $stmt = $pdo->prepare("INSERT INTO notifications
            (notification_type, title, message, icon, link_url, priority,
             target_user_id, target_role, target_group, created_by,
             expires_at, is_scheduled, scheduled_for, metadata)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt->execute([
            $input['notification_type'],
            $input['title'],
            $input['message'] ?? null,
            $input['icon'] ?? null,
            $input['link_url'] ?? null,
            $input['priority'] ?? 'normal',
            $input['target_user_id'] ?? null,
            $input['target_role'] ?? null,
            $input['target_group'] ?? null,
            $user_id,
            $input['expires_at'] ?? null,
            $input['is_scheduled'] ?? false,
            $input['scheduled_for'] ?? null,
            json_encode($input['metadata'] ?? [])
        ]);

        $notification_id = $pdo->lastInsertId();

        // Determine recipients
        $recipients = [];

        if (!empty($input['target_user_id'])) {
            // Single user
            $recipients[] = $input['target_user_id'];
        } elseif (!empty($input['target_role'])) {
            // All users with specific role
            $recipients = getUsersByRole($pdo, $input['target_role']);
        } elseif (!empty($input['target_group'])) {
            // All users in group
            $recipients = getUsersByGroup($pdo, $input['target_group']);
        } else {
            // All users (system-wide notification)
            $recipients = getAllUsers($pdo);
        }

        // Create deliveries for each recipient
        $delivery_stmt = $pdo->prepare("INSERT INTO notification_deliveries
            (notification_id, user_id, delivered_via)
            VALUES (?, ?, ?)");

        $total_recipients = 0;
        foreach ($recipients as $recipient_id) {
            $delivery_method = $input['delivery_method'] ?? 'web';
            $delivery_stmt->execute([$notification_id, $recipient_id, $delivery_method]);
            $total_recipients++;
        }

        // Create stats record
        $stats_stmt = $pdo->prepare("INSERT INTO notification_stats
            (notification_id, total_recipients)
            VALUES (?, ?)");
        $stats_stmt->execute([$notification_id, $total_recipients]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'notification_id' => $notification_id,
            'recipients_count' => $total_recipients,
            'message' => 'Notification sent successfully'
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * List notifications for a user
 */
function handleListNotifications($pdo, $user_id) {
    if (!$user_id) {
        throw new Exception('User ID required');
    }

    $limit = (int)($_GET['limit'] ?? 20);
    $offset = (int)($_GET['offset'] ?? 0);
    $filter_type = $_GET['type'] ?? null;
    $filter_priority = $_GET['priority'] ?? null;
    $show_read = $_GET['show_read'] ?? 'false';

    $sql = "SELECT
        n.id,
        n.notification_type,
        n.title,
        n.message,
        n.icon,
        n.link_url,
        n.priority,
        n.created_at,
        n.expires_at,
        nd.is_read,
        nd.read_at,
        nd.is_dismissed,
        nd.dismissed_at,
        nd.id as delivery_id
    FROM notifications n
    INNER JOIN notification_deliveries nd ON n.id = nd.notification_id
    WHERE nd.user_id = ?
        AND nd.is_dismissed = FALSE
        AND (n.expires_at IS NULL OR n.expires_at > NOW())";

    $params = [$user_id];

    if ($show_read === 'false') {
        $sql .= " AND nd.is_read = FALSE";
    }

    if ($filter_type) {
        $sql .= " AND n.notification_type = ?";
        $params[] = $filter_type;
    }

    if ($filter_priority) {
        $sql .= " AND n.priority = ?";
        $params[] = $filter_priority;
    }

    $sql .= " ORDER BY n.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $notifications = $stmt->fetchAll();

    // Get total count
    $count_sql = "SELECT COUNT(*) as total
        FROM notifications n
        INNER JOIN notification_deliveries nd ON n.id = nd.notification_id
        WHERE nd.user_id = ?
            AND nd.is_dismissed = FALSE
            AND (n.expires_at IS NULL OR n.expires_at > NOW())";

    if ($show_read === 'false') {
        $count_sql .= " AND nd.is_read = FALSE";
    }

    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute([$user_id]);
    $total = $count_stmt->fetch()['total'];

    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'total' => (int)$total,
        'limit' => $limit,
        'offset' => $offset
    ]);
}

/**
 * Count unread notifications
 */
function handleCountUnread($pdo, $user_id) {
    if (!$user_id) {
        throw new Exception('User ID required');
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) as unread_count
        FROM notification_deliveries nd
        INNER JOIN notifications n ON nd.notification_id = n.id
        WHERE nd.user_id = ?
            AND nd.is_read = FALSE
            AND nd.is_dismissed = FALSE
            AND (n.expires_at IS NULL OR n.expires_at > NOW())");

    $stmt->execute([$user_id]);
    $result = $stmt->fetch();

    echo json_encode([
        'success' => true,
        'unread_count' => (int)$result['unread_count']
    ]);
}

/**
 * Mark notification as read
 */
function handleMarkRead($pdo, $user_id) {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

    if (!isset($input['delivery_id']) && !isset($input['notification_id'])) {
        throw new Exception('delivery_id or notification_id required');
    }

    $sql = "UPDATE notification_deliveries
        SET is_read = TRUE, read_at = NOW()
        WHERE user_id = ?";

    $params = [$user_id];

    if (isset($input['delivery_id'])) {
        $sql .= " AND id = ?";
        $params[] = $input['delivery_id'];
    } else {
        $sql .= " AND notification_id = ?";
        $params[] = $input['notification_id'];
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    // Update stats
    updateNotificationStats($pdo);

    echo json_encode([
        'success' => true,
        'message' => 'Notification marked as read'
    ]);
}

/**
 * Mark notification as unread
 */
function handleMarkUnread($pdo, $user_id) {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

    if (!isset($input['delivery_id'])) {
        throw new Exception('delivery_id required');
    }

    $stmt = $pdo->prepare("UPDATE notification_deliveries
        SET is_read = FALSE, read_at = NULL
        WHERE user_id = ? AND id = ?");

    $stmt->execute([$user_id, $input['delivery_id']]);

    echo json_encode([
        'success' => true,
        'message' => 'Notification marked as unread'
    ]);
}

/**
 * Dismiss notification
 */
function handleDismiss($pdo, $user_id) {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

    if (!isset($input['delivery_id'])) {
        throw new Exception('delivery_id required');
    }

    $stmt = $pdo->prepare("UPDATE notification_deliveries
        SET is_dismissed = TRUE, dismissed_at = NOW()
        WHERE user_id = ? AND id = ?");

    $stmt->execute([$user_id, $input['delivery_id']]);

    echo json_encode([
        'success' => true,
        'message' => 'Notification dismissed'
    ]);
}

/**
 * Bulk mark as read
 */
function handleBulkRead($pdo, $user_id) {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

    if (empty($input['delivery_ids'])) {
        throw new Exception('delivery_ids array required');
    }

    $placeholders = str_repeat('?,', count($input['delivery_ids']) - 1) . '?';
    $sql = "UPDATE notification_deliveries
        SET is_read = TRUE, read_at = NOW()
        WHERE user_id = ? AND id IN ($placeholders)";

    $params = array_merge([$user_id], $input['delivery_ids']);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode([
        'success' => true,
        'updated_count' => $stmt->rowCount()
    ]);
}

/**
 * Bulk dismiss
 */
function handleBulkDismiss($pdo, $user_id) {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

    if (empty($input['delivery_ids'])) {
        throw new Exception('delivery_ids array required');
    }

    $placeholders = str_repeat('?,', count($input['delivery_ids']) - 1) . '?';
    $sql = "UPDATE notification_deliveries
        SET is_dismissed = TRUE, dismissed_at = NOW()
        WHERE user_id = ? AND id IN ($placeholders)";

    $params = array_merge([$user_id], $input['delivery_ids']);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode([
        'success' => true,
        'dismissed_count' => $stmt->rowCount()
    ]);
}

/**
 * Delete notification (admin only)
 */
function handleDeleteNotification($pdo, $user_id, $user_role) {
    if ($user_role !== 'admin') {
        throw new Exception('Admin access required');
    }

    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

    if (!isset($input['notification_id'])) {
        throw new Exception('notification_id required');
    }

    $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ?");
    $stmt->execute([$input['notification_id']]);

    echo json_encode([
        'success' => true,
        'message' => 'Notification deleted'
    ]);
}

/**
 * Get user preferences
 */
function handleGetPreferences($pdo, $user_id) {
    if (!$user_id) {
        throw new Exception('User ID required');
    }

    $stmt = $pdo->prepare("SELECT * FROM notification_preferences WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $preferences = $stmt->fetch();

    if (!$preferences) {
        // Return default preferences
        $preferences = [
            'user_id' => $user_id,
            'notification_types' => json_encode([
                'system' => true,
                'call' => true,
                'voicemail' => true,
                'sms' => true,
                'alert' => true,
                'message' => true,
                'task' => true
            ]),
            'delivery_methods' => json_encode(['web' => true]),
            'quiet_hours_enabled' => false,
            'sound_enabled' => true,
            'desktop_enabled' => false,
            'email_enabled' => true,
            'sms_enabled' => false
        ];
    } else {
        // Decode JSON fields
        $preferences['notification_types'] = json_decode($preferences['notification_types'], true);
        $preferences['delivery_methods'] = json_decode($preferences['delivery_methods'], true);
    }

    echo json_encode([
        'success' => true,
        'preferences' => $preferences
    ]);
}

/**
 * Update user preferences
 */
function handleUpdatePreferences($pdo, $user_id) {
    if (!$user_id) {
        throw new Exception('User ID required');
    }

    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

    $stmt = $pdo->prepare("INSERT INTO notification_preferences
        (user_id, notification_types, delivery_methods, quiet_hours_enabled,
         quiet_hours_start, quiet_hours_end, sound_enabled, desktop_enabled,
         email_enabled, sms_enabled)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        notification_types = VALUES(notification_types),
        delivery_methods = VALUES(delivery_methods),
        quiet_hours_enabled = VALUES(quiet_hours_enabled),
        quiet_hours_start = VALUES(quiet_hours_start),
        quiet_hours_end = VALUES(quiet_hours_end),
        sound_enabled = VALUES(sound_enabled),
        desktop_enabled = VALUES(desktop_enabled),
        email_enabled = VALUES(email_enabled),
        sms_enabled = VALUES(sms_enabled)");

    $stmt->execute([
        $user_id,
        json_encode($input['notification_types'] ?? []),
        json_encode($input['delivery_methods'] ?? ['web' => true]),
        $input['quiet_hours_enabled'] ?? false,
        $input['quiet_hours_start'] ?? null,
        $input['quiet_hours_end'] ?? null,
        $input['sound_enabled'] ?? true,
        $input['desktop_enabled'] ?? false,
        $input['email_enabled'] ?? true,
        $input['sms_enabled'] ?? false
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Preferences updated successfully'
    ]);
}

/**
 * Get notification statistics (admin only)
 */
function handleGetStats($pdo, $user_role) {
    if ($user_role !== 'admin') {
        throw new Exception('Admin access required');
    }

    // Overall stats
    $overall = $pdo->query("SELECT
        COUNT(DISTINCT n.id) as total_notifications,
        SUM(ns.total_recipients) as total_deliveries,
        SUM(ns.total_read) as total_read,
        SUM(ns.total_dismissed) as total_dismissed
        FROM notifications n
        LEFT JOIN notification_stats ns ON n.id = ns.notification_id")->fetch();

    // Stats by type
    $by_type = $pdo->query("SELECT
        notification_type,
        COUNT(*) as count
        FROM notifications
        GROUP BY notification_type")->fetchAll();

    // Recent activity (last 7 days)
    $recent = $pdo->query("SELECT
        DATE(created_at) as date,
        COUNT(*) as count
        FROM notifications
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date DESC")->fetchAll();

    echo json_encode([
        'success' => true,
        'stats' => [
            'overall' => $overall,
            'by_type' => $by_type,
            'recent_activity' => $recent
        ]
    ]);
}

/**
 * Get scheduled notifications (admin only)
 */
function handleScheduledNotifications($pdo, $user_role) {
    if ($user_role !== 'admin') {
        throw new Exception('Admin access required');
    }

    $stmt = $pdo->query("SELECT * FROM notifications
        WHERE is_scheduled = TRUE AND scheduled_for > NOW()
        ORDER BY scheduled_for ASC");

    $notifications = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'scheduled_notifications' => $notifications
    ]);
}

/**
 * Helper: Get users by role
 */
function getUsersByRole($pdo, $role) {
    $stmt = $pdo->prepare("SELECT username FROM admins WHERE role = ?");
    $stmt->execute([$role]);
    return array_column($stmt->fetchAll(), 'username');
}

/**
 * Helper: Get users by group
 */
function getUsersByGroup($pdo, $group) {
    // Implement based on your group/tenant structure
    return [];
}

/**
 * Helper: Get all users
 */
function getAllUsers($pdo) {
    $stmt = $pdo->query("SELECT username FROM admins");
    return array_column($stmt->fetchAll(), 'username');
}

/**
 * Helper: Update notification stats
 */
function updateNotificationStats($pdo) {
    $pdo->exec("UPDATE notification_stats ns
        SET
            total_read = (SELECT COUNT(*) FROM notification_deliveries
                WHERE notification_id = ns.notification_id AND is_read = TRUE),
            total_dismissed = (SELECT COUNT(*) FROM notification_deliveries
                WHERE notification_id = ns.notification_id AND is_dismissed = TRUE)");
}
?>
