<?php
/**
 * FlexPBX Universal Checklist Manager API
 * Supports all checklist types, roles, and features
 *
 * Version: 2.0
 * Compatible with: FlexPBX v1.2+
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/config.php';

// Verify API key or session
$api_key = $_GET['api_key'] ?? $_POST['api_key'] ?? '';
$is_api_auth = ($api_key === $config['api_key']);

session_start();
$is_session_auth = ($_SESSION['logged_in'] ?? false) || ($_SESSION['admin_logged_in'] ?? false);

if (!$is_api_auth && !$is_session_auth) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';
$user_role = $_SESSION['user_role'] ?? 'guest';
$user_id = $_SESSION['user_id'] ?? 0;

try {
    switch ($action) {
        case 'list':
            listChecklists();
            break;

        case 'get_by_type':
            getChecklistByType();
            break;

        case 'get_my_checklists':
            getMyChecklists();
            break;

        case 'complete':
            completeChecklistItem();
            break;

        case 'uncomplete':
            uncompleteChecklistItem();
            break;

        case 'assign':
            assignChecklist();
            break;

        case 'get_categories':
            getCategories();
            break;

        case 'get_types':
            getChecklistTypes();
            break;

        case 'progress':
            getProgress();
            break;

        case 'create_instance':
            createChecklistInstance();
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function listChecklists() {
    global $pdo, $user_role;

    $stmt = $pdo->query("
        SELECT
            ci.*,
            ct.type_name,
            ct.type_key,
            cc.category_name,
            cc.icon,
            cc.color
        FROM checklist_items ci
        LEFT JOIN checklist_types ct ON ci.checklist_type_id = ct.id
        LEFT JOIN checklist_categories cc ON ct.category_id = cc.id
        WHERE (
            ci.assigned_to_role IS NULL
            OR ci.assigned_to_role = '{$user_role}'
            OR FIND_IN_SET('{$user_role}', ci.assigned_to_role) > 0
        )
        ORDER BY cc.display_order, ct.display_order, ci.check_order
    ");

    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $items
    ]);
}

function getChecklistByType() {
    global $pdo, $user_role;

    $type_key = $_GET['type_key'] ?? $_POST['type_key'] ?? '';

    if (empty($type_key)) {
        throw new Exception('type_key is required');
    }

    // Get checklist type
    $type_stmt = $pdo->prepare("
        SELECT id, type_name, type_description, allowed_roles
        FROM checklist_types
        WHERE type_key = ?
    ");
    $type_stmt->execute([$type_key]);
    $type = $type_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$type) {
        throw new Exception('Checklist type not found');
    }

    // Check if user has access
    $allowed_roles = explode(',', $type['allowed_roles']);
    if (!in_array($user_role, $allowed_roles) && $user_role !== 'admin') {
        throw new Exception('Access denied to this checklist type');
    }

    // Get items
    $items_stmt = $pdo->prepare("
        SELECT *
        FROM checklist_items
        WHERE checklist_type_id = ?
        ORDER BY check_order ASC
    ");
    $items_stmt->execute([$type['id']]);
    $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get progress
    $total = count($items);
    $completed = count(array_filter($items, fn($item) => $item['is_completed']));
    $required = count(array_filter($items, fn($item) => $item['is_required']));
    $required_completed = count(array_filter($items, fn($item) => $item['is_required'] && $item['is_completed']));

    echo json_encode([
        'success' => true,
        'data' => [
            'type' => $type,
            'items' => $items,
            'progress' => [
                'total' => $total,
                'completed' => $completed,
                'required' => $required,
                'required_completed' => $required_completed,
                'progress_percent' => $total > 0 ? round(($completed / $total) * 100) : 0,
                'required_progress_percent' => $required > 0 ? round(($required_completed / $required) * 100) : 0
            ]
        ]
    ]);
}

function getMyChecklists() {
    global $pdo, $user_id, $user_role;

    $stmt = $pdo->prepare("
        SELECT
            ci.*,
            ct.type_name,
            ct.type_key,
            cc.category_name,
            cc.icon,
            cc.color
        FROM checklist_items ci
        LEFT JOIN checklist_types ct ON ci.checklist_type_id = ct.id
        LEFT JOIN checklist_categories cc ON ct.category_id = cc.id
        WHERE (
            ci.assigned_to_user_id = ?
            OR ci.assigned_to_role = ?
            OR FIND_IN_SET(?, ci.assigned_to_role) > 0
        )
        AND ci.is_completed = 0
        ORDER BY ci.priority DESC, ci.due_date ASC, cc.display_order
    ");

    $stmt->execute([$user_id, $user_role, $user_role]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $items,
        'count' => count($items)
    ]);
}

function completeChecklistItem() {
    global $pdo, $user_id;

    $check_key = $_POST['check_key'] ?? '';
    $completion_note = $_POST['completion_note'] ?? '';
    $completed_by = $_SESSION['username'] ?? 'system';

    if (empty($check_key)) {
        throw new Exception('check_key is required');
    }

    $stmt = $pdo->prepare("
        UPDATE checklist_items
        SET is_completed = 1,
            completed_at = NOW(),
            completed_by = ?,
            completion_note = ?
        WHERE check_key = ?
    ");

    $stmt->execute([$completed_by, $completion_note, $check_key]);

    echo json_encode([
        'success' => true,
        'message' => 'Item marked as complete'
    ]);
}

function uncompleteChecklistItem() {
    global $pdo;

    $check_key = $_POST['check_key'] ?? '';

    if (empty($check_key)) {
        throw new Exception('check_key is required');
    }

    $stmt = $pdo->prepare("
        UPDATE checklist_items
        SET is_completed = 0,
            completed_at = NULL,
            completed_by = NULL,
            completion_note = NULL
        WHERE check_key = ?
    ");

    $stmt->execute([$check_key]);

    echo json_encode([
        'success' => true,
        'message' => 'Item marked as incomplete'
    ]);
}

function assignChecklist() {
    global $pdo;

    $check_key = $_POST['check_key'] ?? '';
    $assign_to_user_id = $_POST['assign_to_user_id'] ?? null;
    $assign_to_role = $_POST['assign_to_role'] ?? null;
    $due_date = $_POST['due_date'] ?? null;
    $priority = $_POST['priority'] ?? 'medium';

    if (empty($check_key)) {
        throw new Exception('check_key is required');
    }

    $stmt = $pdo->prepare("
        UPDATE checklist_items
        SET assigned_to_user_id = ?,
            assigned_to_role = ?,
            due_date = ?,
            priority = ?
        WHERE check_key = ?
    ");

    $stmt->execute([$assign_to_user_id, $assign_to_role, $due_date, $priority, $check_key]);

    echo json_encode([
        'success' => true,
        'message' => 'Checklist assigned successfully'
    ]);
}

function getCategories() {
    global $pdo;

    $stmt = $pdo->query("
        SELECT * FROM checklist_categories
        ORDER BY display_order ASC
    ");

    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $categories
    ]);
}

function getChecklistTypes() {
    global $pdo, $user_role;

    $category_id = $_GET['category_id'] ?? null;

    $sql = "
        SELECT
            ct.*,
            cc.category_name,
            cc.icon,
            cc.color
        FROM checklist_types ct
        LEFT JOIN checklist_categories cc ON ct.category_id = cc.id
        WHERE (
            ct.allowed_roles LIKE '%{$user_role}%'
            OR '{$user_role}' = 'admin'
        )
    ";

    if ($category_id) {
        $sql .= " AND ct.category_id = " . (int)$category_id;
    }

    $sql .= " ORDER BY cc.display_order, ct.display_order";

    $stmt = $pdo->query($sql);
    $types = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $types
    ]);
}

function getProgress() {
    global $pdo, $user_id, $user_role;

    $type_key = $_GET['type_key'] ?? null;

    $sql = "
        SELECT
            COUNT(*) as total,
            SUM(is_completed) as completed,
            SUM(CASE WHEN is_required = 1 THEN 1 ELSE 0 END) as required,
            SUM(CASE WHEN is_required = 1 AND is_completed = 1 THEN 1 ELSE 0 END) as required_completed
        FROM checklist_items ci
    ";

    if ($type_key) {
        $sql .= "
            LEFT JOIN checklist_types ct ON ci.checklist_type_id = ct.id
            WHERE ct.type_key = '" . $pdo->quote($type_key) . "'
        ";
    } else {
        $sql .= "
            WHERE (
                ci.assigned_to_user_id = {$user_id}
                OR ci.assigned_to_role = '{$user_role}'
            )
        ";
    }

    $stmt = $pdo->query($sql);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => [
            'total' => (int)$result['total'],
            'completed' => (int)$result['completed'],
            'required' => (int)$result['required'],
            'required_completed' => (int)$result['required_completed'],
            'progress_percent' => $result['total'] > 0 ? round(($result['completed'] / $result['total']) * 100) : 0,
            'required_progress_percent' => $result['required'] > 0 ? round(($result['required_completed'] / $result['required']) * 100) : 0
        ]
    ]);
}

function createChecklistInstance() {
    global $pdo, $user_id;

    $type_key = $_POST['type_key'] ?? '';
    $assign_to_user_id = $_POST['assign_to_user_id'] ?? $user_id;
    $due_date = $_POST['due_date'] ?? null;

    if (empty($type_key)) {
        throw new Exception('type_key is required');
    }

    // Get type ID
    $type_stmt = $pdo->prepare("SELECT id FROM checklist_types WHERE type_key = ?");
    $type_stmt->execute([$type_key]);
    $type_id = $type_stmt->fetchColumn();

    if (!$type_id) {
        throw new Exception('Checklist type not found');
    }

    // Clone template items for this user
    $pdo->exec("
        INSERT INTO checklist_items (
            check_key,
            check_name,
            check_description,
            is_required,
            check_order,
            checklist_type_id,
            assigned_to_user_id,
            assigned_to_role,
            due_date,
            priority
        )
        SELECT
            CONCAT(check_key, '_', {$assign_to_user_id}, '_', UNIX_TIMESTAMP()) as check_key,
            check_name,
            check_description,
            is_required,
            check_order,
            checklist_type_id,
            {$assign_to_user_id} as assigned_to_user_id,
            assigned_to_role,
            " . ($due_date ? "'{$due_date}'" : "NULL") . " as due_date,
            priority
        FROM checklist_items
        WHERE checklist_type_id = {$type_id}
        AND assigned_to_user_id IS NULL
    ");

    echo json_encode([
        'success' => true,
        'message' => 'Checklist instance created for user',
        'user_id' => $assign_to_user_id
    ]);
}
