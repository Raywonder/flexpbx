<?php
/**
 * FlexPBX Module Management API v2.0
 * Database-driven modular feature system
 * ALL installations register with main server
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

function verifyApiKey($conn, $api_key) {
    $stmt = $conn->prepare("
        SELECT client_id, server_url, status
        FROM flexpbx_clients
        WHERE api_key = ? AND status = 'active'
    ");
    $stmt->bind_param('s', $api_key);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $update = $conn->prepare("UPDATE flexpbx_clients SET last_check_in = NOW() WHERE client_id = ?");
        $update->bind_param('s', $row['client_id']);
        $update->execute();
        return $row;
    }
    return false;
}

switch ($path) {
    case 'available':
        // List all available modules (no API key required for browsing)
        $category = $_GET['category'] ?? null;
        $api_key = $_SERVER['HTTP_X_API_KEY'] ?? null;
        $client_id = null;

        if ($api_key) {
            $client = verifyApiKey($conn, $api_key);
            if ($client) {
                $client_id = $client['client_id'];
            }
        }

        $sql = "SELECT m.*, 
                CASE WHEN cm.id IS NOT NULL THEN TRUE ELSE FALSE END as is_installed,
                cm.is_enabled, cm.installed_version
                FROM flexpbx_modules m
                LEFT JOIN flexpbx_client_modules cm ON m.module_key = cm.module_key AND cm.client_id = ?
                WHERE m.status = 'stable'";

        if ($category) {
            $sql .= " AND m.category = ?";
        }

        $sql .= " ORDER BY m.is_required DESC, m.category, m.module_name";

        $stmt = $conn->prepare($sql);
        if ($category) {
            $stmt->bind_param('ss', $client_id, $category);
        } else {
            $stmt->bind_param('s', $client_id);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        $modules = [];
        while ($row = $result->fetch_assoc()) {
            $row['dependencies'] = json_decode($row['dependencies'] ?? '[]', true);
            $row['is_installed'] = (bool)$row['is_installed'];
            $row['is_enabled'] = isset($row['is_enabled']) ? (bool)$row['is_enabled'] : null;
            $modules[] = $row;
        }

        echo json_encode(['success' => true, 'count' => count($modules), 'modules' => $modules]);
        break;

    case 'install':
        $api_key = $_SERVER['HTTP_X_API_KEY'] ?? null;
        if (!$api_key) {
            http_response_code(401);
            echo json_encode(['error' => 'API key required']);
            exit;
        }

        $client = verifyApiKey($conn, $api_key);
        if (!$client) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid API key']);
            exit;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $module_key = $data['module_key'] ?? null;

        $check = $conn->prepare("SELECT * FROM flexpbx_modules WHERE module_key = ?");
        $check->bind_param('s', $module_key);
        $check->execute();
        $module = $check->get_result()->fetch_assoc();

        if (!$module) {
            http_response_code(404);
            echo json_encode(['error' => 'Module not found']);
            exit;
        }

        $install = $conn->prepare("
            INSERT INTO flexpbx_client_modules (client_id, module_key, is_enabled, installed_version, config_data)
            VALUES (?, ?, TRUE, ?, ?)
        ");

        $config = json_encode($data['config'] ?? []);
        $install->bind_param('ssss', $client['client_id'], $module_key, $module['version'], $config);

        if ($install->execute()) {
            echo json_encode(['success' => true, 'message' => 'Module installed', 'module' => $module['module_name']]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Installation failed']);
        }
        break;

    case 'enable':
    case 'disable':
        $api_key = $_SERVER['HTTP_X_API_KEY'] ?? null;
        if (!$api_key) {
            http_response_code(401);
            echo json_encode(['error' => 'API key required']);
            exit;
        }

        $client = verifyApiKey($conn, $api_key);
        if (!$client) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid API key']);
            exit;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $module_key = $data['module_key'] ?? null;

        $check = $conn->prepare("SELECT is_required FROM flexpbx_modules WHERE module_key = ?");
        $check->bind_param('s', $module_key);
        $check->execute();
        $mod = $check->get_result()->fetch_assoc();

        if ($path === 'disable' && $mod['is_required']) {
            http_response_code(400);
            echo json_encode(['error' => 'Cannot disable required module']);
            exit;
        }

        $enabled = ($path === 'enable') ? 1 : 0;
        $update = $conn->prepare("
            UPDATE flexpbx_client_modules SET is_enabled = ?, last_updated = NOW()
            WHERE client_id = ? AND module_key = ?
        ");
        $update->bind_param('iss', $enabled, $client['client_id'], $module_key);

        if ($update->execute()) {
            echo json_encode(['success' => true, 'module' => $module_key, 'is_enabled' => (bool)$enabled]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Update failed']);
        }
        break;

    case 'installed':
        $api_key = $_SERVER['HTTP_X_API_KEY'] ?? null;
        if (!$api_key) {
            http_response_code(401);
            echo json_encode(['error' => 'API key required']);
            exit;
        }

        $client = verifyApiKey($conn, $api_key);
        if (!$client) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid API key']);
            exit;
        }

        $stmt = $conn->prepare("
            SELECT cm.*, m.module_name, m.module_description, m.category, m.is_required, m.version as latest_version
            FROM flexpbx_client_modules cm
            JOIN flexpbx_modules m ON cm.module_key = m.module_key
            WHERE cm.client_id = ?
            ORDER BY m.is_required DESC, m.category
        ");

        $stmt->bind_param('s', $client['client_id']);
        $stmt->execute();
        $result = $stmt->get_result();

        $modules = [];
        while ($row = $result->fetch_assoc()) {
            $row['is_enabled'] = (bool)$row['is_enabled'];
            $row['update_available'] = version_compare($row['latest_version'], $row['installed_version'], '>');
            $modules[] = $row;
        }

        echo json_encode(['success' => true, 'count' => count($modules), 'modules' => $modules]);
        break;

    default:
        echo json_encode([
            'error' => 'Invalid endpoint',
            'endpoints' => ['available', 'installed', 'install', 'enable', 'disable']
        ]);
}
