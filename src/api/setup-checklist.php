<?php
/**
 * FlexPBX Setup Checklist API
 * Manages setup progress and auto-maintenance mode
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/config.php';

// Verify API key
$api_key = $_GET['api_key'] ?? $_POST['api_key'] ?? '';
if ($api_key !== $config['api_key']) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid API key']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'status';

try {
    switch ($action) {
        case 'status':
            getSetupStatus();
            break;

        case 'complete':
            completeCheckItem();
            break;

        case 'uncomplete':
            uncompleteCheckItem();
            break;

        case 'progress':
            getProgress();
            break;

        case 'required_incomplete':
            getRequiredIncomplete();
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

function getSetupStatus() {
    global $pdo;

    $stmt = $pdo->query("
        SELECT
            check_key,
            check_name,
            check_description,
            is_completed,
            is_required,
            completed_at,
            completed_by,
            check_order
        FROM setup_checklist
        ORDER BY check_order ASC
    ");

    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total = count($items);
    $completed = count(array_filter($items, fn($item) => $item['is_completed']));
    $required = count(array_filter($items, fn($item) => $item['is_required']));
    $required_completed = count(array_filter($items, fn($item) => $item['is_required'] && $item['is_completed']));

    $is_setup_complete = ($required_completed === $required);

    // Check if maintenance mode should be auto-enabled
    $maintenance_stmt = $pdo->query("
        SELECT is_active, maintenance_mode_type
        FROM system_maintenance
        ORDER BY id DESC
        LIMIT 1
    ");
    $maintenance = $maintenance_stmt->fetch(PDO::FETCH_ASSOC);

    $should_be_in_maintenance = !$is_setup_complete &&
        (!$maintenance || $maintenance['maintenance_mode_type'] === 'auto');

    echo json_encode([
        'success' => true,
        'data' => [
            'items' => $items,
            'total' => $total,
            'completed' => $completed,
            'required' => $required,
            'required_completed' => $required_completed,
            'setup_complete' => $is_setup_complete,
            'progress_percent' => $total > 0 ? round(($completed / $total) * 100) : 0,
            'required_progress_percent' => $required > 0 ? round(($required_completed / $required) * 100) : 0,
            'maintenance_active' => $maintenance['is_active'] ?? 0,
            'should_be_in_maintenance' => $should_be_in_maintenance
        ]
    ]);
}

function completeCheckItem() {
    global $pdo;

    $check_key = $_POST['check_key'] ?? '';
    $completed_by = $_POST['completed_by'] ?? 'system';

    if (empty($check_key)) {
        throw new Exception('check_key is required');
    }

    $stmt = $pdo->prepare("
        UPDATE setup_checklist
        SET is_completed = 1,
            completed_at = NOW(),
            completed_by = :completed_by
        WHERE check_key = :check_key
    ");

    $stmt->execute([
        'check_key' => $check_key,
        'completed_by' => $completed_by
    ]);

    // Check if all required items are now complete
    $required_stmt = $pdo->query("
        SELECT COUNT(*) as required_count,
               SUM(is_completed) as completed_count
        FROM setup_checklist
        WHERE is_required = 1
    ");

    $result = $required_stmt->fetch(PDO::FETCH_ASSOC);
    $all_required_complete = ($result['required_count'] == $result['completed_count']);

    // Auto-disable maintenance mode if setup is complete
    if ($all_required_complete) {
        $pdo->exec("
            UPDATE system_maintenance
            SET is_active = 0,
                disabled_at = NOW(),
                maintenance_message = 'Setup completed successfully'
            WHERE maintenance_mode_type = 'auto'
        ");
    }

    echo json_encode([
        'success' => true,
        'message' => 'Check item marked as complete',
        'setup_complete' => $all_required_complete,
        'maintenance_disabled' => $all_required_complete
    ]);
}

function uncompleteCheckItem() {
    global $pdo;

    $check_key = $_POST['check_key'] ?? '';

    if (empty($check_key)) {
        throw new Exception('check_key is required');
    }

    $stmt = $pdo->prepare("
        UPDATE setup_checklist
        SET is_completed = 0,
            completed_at = NULL,
            completed_by = NULL
        WHERE check_key = :check_key
    ");

    $stmt->execute(['check_key' => $check_key]);

    // Re-enable maintenance mode if required items are incomplete
    $pdo->exec("
        UPDATE system_maintenance
        SET is_active = 1,
            enabled_at = NOW(),
            maintenance_message = 'Setup in progress'
        WHERE maintenance_mode_type = 'auto'
    ");

    echo json_encode([
        'success' => true,
        'message' => 'Check item marked as incomplete',
        'maintenance_enabled' => true
    ]);
}

function getProgress() {
    global $pdo;

    $stmt = $pdo->query("
        SELECT
            COUNT(*) as total,
            SUM(is_completed) as completed,
            SUM(CASE WHEN is_required = 1 THEN 1 ELSE 0 END) as required,
            SUM(CASE WHEN is_required = 1 AND is_completed = 1 THEN 1 ELSE 0 END) as required_completed
        FROM setup_checklist
    ");

    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => [
            'total' => (int)$result['total'],
            'completed' => (int)$result['completed'],
            'required' => (int)$result['required'],
            'required_completed' => (int)$result['required_completed'],
            'progress_percent' => $result['total'] > 0 ? round(($result['completed'] / $result['total']) * 100) : 0,
            'required_progress_percent' => $result['required'] > 0 ? round(($result['required_completed'] / $result['required']) * 100) : 0,
            'setup_complete' => ($result['required'] == $result['required_completed'])
        ]
    ]);
}

function getRequiredIncomplete() {
    global $pdo;

    $stmt = $pdo->query("
        SELECT check_key, check_name, check_description
        FROM setup_checklist
        WHERE is_required = 1 AND is_completed = 0
        ORDER BY check_order ASC
    ");

    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $items,
        'count' => count($items)
    ]);
}
