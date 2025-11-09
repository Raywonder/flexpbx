<?php
/**
 * FlexPBX Announcements API
 * Manages system announcements for admin and user portals
 *
 * @version 1.0.0
 * @date 2025-11-06
 */

header('Content-Type: application/json');

// Session and authentication
session_start();

// Load database
require_once __DIR__ . '/../config/database.php';

// Check if user is authenticated
$is_admin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'];
$is_user = isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'];

if (!$is_admin && !$is_user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Get current user info
$current_user = $is_admin ? ($_SESSION['admin_username'] ?? null) : ($_SESSION['username'] ?? null);
$current_role = $is_admin ? ($_SESSION['admin_role'] ?? 'admin') : 'user';

// Handle request method
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($method) {
        case 'GET':
            handleGet($pdo, $action, $current_user, $current_role, $is_admin);
            break;

        case 'POST':
            handlePost($pdo, $action, $current_user, $current_role, $is_admin);
            break;

        case 'PUT':
            handlePut($pdo, $current_user, $is_admin);
            break;

        case 'DELETE':
            handleDelete($pdo, $current_user, $is_admin);
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * Handle GET requests
 */
function handleGet($pdo, $action, $user, $role, $is_admin) {
    switch ($action) {
        case 'active':
            // Get active announcements for current user
            getActiveAnnouncements($pdo, $user, $role);
            break;

        case 'all':
            // Admin only: Get all announcements
            if (!$is_admin) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Admin access required']);
                return;
            }
            getAllAnnouncements($pdo);
            break;

        case 'templates':
            // Get announcement templates
            if (!$is_admin) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Admin access required']);
                return;
            }
            getTemplates($pdo);
            break;

        case 'analytics':
            // Get analytics for an announcement
            if (!$is_admin) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Admin access required']);
                return;
            }
            $id = $_GET['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Announcement ID required']);
                return;
            }
            getAnalytics($pdo, $id);
            break;

        case 'count':
            // Get unread announcement count
            getUnreadCount($pdo, $user, $role);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
}

/**
 * Handle POST requests
 */
function handlePost($pdo, $action, $user, $role, $is_admin) {
    $data = json_decode(file_get_contents('php://input'), true);

    switch ($action) {
        case 'create':
            // Admin only: Create new announcement
            if (!$is_admin) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Admin access required']);
                return;
            }
            createAnnouncement($pdo, $data, $user);
            break;

        case 'viewed':
            // Mark announcement as viewed
            $id = $data['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Announcement ID required']);
                return;
            }
            markViewed($pdo, $id, $user);
            break;

        case 'dismiss':
            // Dismiss announcement
            $id = $data['id'] ?? null;
            if (!$id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Announcement ID required']);
                return;
            }
            dismissAnnouncement($pdo, $id, $user);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
}

/**
 * Handle PUT requests
 */
function handlePut($pdo, $user, $is_admin) {
    if (!$is_admin) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Admin access required']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? null;

    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Announcement ID required']);
        return;
    }

    updateAnnouncement($pdo, $id, $data);
}

/**
 * Handle DELETE requests
 */
function handleDelete($pdo, $user, $is_admin) {
    if (!$is_admin) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Admin access required']);
        return;
    }

    $id = $_GET['id'] ?? null;

    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Announcement ID required']);
        return;
    }

    deleteAnnouncement($pdo, $id);
}

/**
 * Get active announcements for current user
 */
function getActiveAnnouncements($pdo, $user, $role) {
    $now = date('Y-m-d H:i:s');

    $sql = "SELECT a.*,
            CASE WHEN av.dismissed_at IS NOT NULL THEN 1 ELSE 0 END as is_dismissed,
            CASE WHEN av.viewed_at IS NOT NULL THEN 1 ELSE 0 END as is_viewed
            FROM announcements a
            LEFT JOIN announcement_views av ON a.id = av.announcement_id AND av.user_id = :user
            WHERE a.is_active = 1
            AND (a.start_date IS NULL OR a.start_date <= :now1)
            AND (a.end_date IS NULL OR a.end_date >= :now2)
            AND (a.target_roles IS NULL OR JSON_CONTAINS(a.target_roles, :role, '$'))
            AND (av.dismissed_at IS NULL OR a.is_dismissible = 0)
            ORDER BY
                FIELD(a.priority, 'urgent', 'high', 'normal', 'low'),
                a.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'user' => $user,
        'now1' => $now,
        'now2' => $now,
        'role' => json_encode($role)
    ]);

    $announcements = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'announcements' => $announcements,
        'count' => count($announcements)
    ]);
}

/**
 * Get all announcements (admin only)
 */
function getAllAnnouncements($pdo) {
    $sql = "SELECT a.*,
            (SELECT COUNT(*) FROM announcement_views av WHERE av.announcement_id = a.id) as view_count,
            (SELECT COUNT(*) FROM announcement_views av WHERE av.announcement_id = a.id AND av.dismissed_at IS NOT NULL) as dismiss_count
            FROM announcements a
            ORDER BY a.created_at DESC";

    $stmt = $pdo->query($sql);
    $announcements = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'announcements' => $announcements
    ]);
}

/**
 * Get announcement templates
 */
function getTemplates($pdo) {
    $sql = "SELECT * FROM announcement_templates ORDER BY name";
    $stmt = $pdo->query($sql);
    $templates = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'templates' => $templates
    ]);
}

/**
 * Get analytics for an announcement
 */
function getAnalytics($pdo, $id) {
    // Get announcement details
    $stmt = $pdo->prepare("SELECT * FROM announcements WHERE id = ?");
    $stmt->execute([$id]);
    $announcement = $stmt->fetch();

    if (!$announcement) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Announcement not found']);
        return;
    }

    // Get view statistics
    $stmt = $pdo->prepare("
        SELECT
            COUNT(DISTINCT user_id) as unique_views,
            COUNT(DISTINCT CASE WHEN dismissed_at IS NOT NULL THEN user_id END) as dismissals,
            MIN(viewed_at) as first_view,
            MAX(viewed_at) as last_view
        FROM announcement_views
        WHERE announcement_id = ?
    ");
    $stmt->execute([$id]);
    $stats = $stmt->fetch();

    // Get view timeline
    $stmt = $pdo->prepare("
        SELECT DATE(viewed_at) as date, COUNT(*) as views
        FROM announcement_views
        WHERE announcement_id = ?
        GROUP BY DATE(viewed_at)
        ORDER BY date
    ");
    $stmt->execute([$id]);
    $timeline = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'announcement' => $announcement,
        'statistics' => $stats,
        'timeline' => $timeline
    ]);
}

/**
 * Get unread announcement count
 */
function getUnreadCount($pdo, $user, $role) {
    $now = date('Y-m-d H:i:s');

    $sql = "SELECT COUNT(*) as count
            FROM announcements a
            LEFT JOIN announcement_views av ON a.id = av.announcement_id AND av.user_id = :user
            WHERE a.is_active = 1
            AND (a.start_date IS NULL OR a.start_date <= :now1)
            AND (a.end_date IS NULL OR a.end_date >= :now2)
            AND (a.target_roles IS NULL OR JSON_CONTAINS(a.target_roles, :role, '$'))
            AND av.viewed_at IS NULL";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'user' => $user,
        'now1' => $now,
        'now2' => $now,
        'role' => json_encode($role)
    ]);

    $result = $stmt->fetch();

    echo json_encode([
        'success' => true,
        'count' => (int)$result['count']
    ]);
}

/**
 * Create new announcement
 */
function createAnnouncement($pdo, $data, $user) {
    $required = ['title', 'content', 'announcement_type', 'priority'];
    foreach ($required as $field) {
        if (!isset($data[$field])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Missing required field: $field"]);
            return;
        }
    }

    $sql = "INSERT INTO announcements
            (title, content, announcement_type, priority, target_roles, start_date, end_date,
             is_active, is_dismissible, show_banner, show_popup, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $data['title'],
        $data['content'],
        $data['announcement_type'],
        $data['priority'],
        isset($data['target_roles']) ? json_encode($data['target_roles']) : null,
        $data['start_date'] ?? null,
        $data['end_date'] ?? null,
        $data['is_active'] ?? 1,
        $data['is_dismissible'] ?? 1,
        $data['show_banner'] ?? 0,
        $data['show_popup'] ?? 0,
        $user
    ]);

    $id = $pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'message' => 'Announcement created successfully',
        'id' => $id
    ]);
}

/**
 * Update announcement
 */
function updateAnnouncement($pdo, $id, $data) {
    $sql = "UPDATE announcements SET
            title = ?, content = ?, announcement_type = ?, priority = ?,
            target_roles = ?, start_date = ?, end_date = ?,
            is_active = ?, is_dismissible = ?, show_banner = ?, show_popup = ?
            WHERE id = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $data['title'],
        $data['content'],
        $data['announcement_type'],
        $data['priority'],
        isset($data['target_roles']) ? json_encode($data['target_roles']) : null,
        $data['start_date'] ?? null,
        $data['end_date'] ?? null,
        $data['is_active'] ?? 1,
        $data['is_dismissible'] ?? 1,
        $data['show_banner'] ?? 0,
        $data['show_popup'] ?? 0,
        $id
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Announcement updated successfully'
    ]);
}

/**
 * Delete announcement
 */
function deleteAnnouncement($pdo, $id) {
    $stmt = $pdo->prepare("DELETE FROM announcements WHERE id = ?");
    $stmt->execute([$id]);

    echo json_encode([
        'success' => true,
        'message' => 'Announcement deleted successfully'
    ]);
}

/**
 * Mark announcement as viewed
 */
function markViewed($pdo, $id, $user) {
    $sql = "INSERT INTO announcement_views (announcement_id, user_id, viewed_at)
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE viewed_at = NOW()";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id, $user]);

    echo json_encode([
        'success' => true,
        'message' => 'Marked as viewed'
    ]);
}

/**
 * Dismiss announcement
 */
function dismissAnnouncement($pdo, $id, $user) {
    // Check if dismissible
    $stmt = $pdo->prepare("SELECT is_dismissible FROM announcements WHERE id = ?");
    $stmt->execute([$id]);
    $announcement = $stmt->fetch();

    if (!$announcement || !$announcement['is_dismissible']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Announcement cannot be dismissed']);
        return;
    }

    $sql = "INSERT INTO announcement_views (announcement_id, user_id, viewed_at, dismissed_at)
            VALUES (?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE dismissed_at = NOW()";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id, $user]);

    echo json_encode([
        'success' => true,
        'message' => 'Announcement dismissed'
    ]);
}
