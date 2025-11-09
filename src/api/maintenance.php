<?php
/**
 * FlexPBX Maintenance Mode API
 * API endpoint for managing maintenance mode from external systems (WHMCS, etc.)
 *
 * Endpoints:
 * - GET /api/maintenance.php?action=status&api_key=xxx
 * - POST /api/maintenance.php?action=enable&api_key=xxx
 * - POST /api/maintenance.php?action=disable&api_key=xxx
 * - POST /api/maintenance.php?action=update&api_key=xxx&message=xxx&title=xxx
 *
 * @version 1.0.0
 * @date 2025-11-05
 */

header('Content-Type: application/json');

// CORS headers for external access
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Load configuration
$config = require_once __DIR__ . '/config.php';

// Initialize response
$response = [
    'success' => false,
    'message' => '',
    'data' => null,
    'timestamp' => date('c')
];

// Verify API key
$provided_api_key = $_GET['api_key'] ?? $_POST['api_key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? null;

if (!$provided_api_key || $provided_api_key !== $config['api_key']) {
    http_response_code(401);
    $response['message'] = 'Invalid or missing API key';
    echo json_encode($response);
    exit;
}

// Database connection
try {
    $dsn = "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $config['db_user'], $config['db_password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    $response['message'] = 'Database connection failed';
    echo json_encode($response);
    exit;
}

// Get action
$action = $_GET['action'] ?? $_POST['action'] ?? 'status';

try {
    switch ($action) {
        case 'status':
            // Get current maintenance status
            $stmt = $pdo->query("SELECT * FROM system_maintenance WHERE id = 1 LIMIT 1");
            $maintenance = $stmt->fetch();

            if ($maintenance) {
                $response['success'] = true;
                $response['data'] = [
                    'is_active' => (bool)$maintenance['is_active'],
                    'title' => $maintenance['maintenance_title'],
                    'message' => $maintenance['maintenance_message'],
                    'allow_api_access' => (bool)$maintenance['allow_api_access'],
                    'allow_user_portal' => (bool)$maintenance['allow_user_portal_limited'],
                    'enabled_at' => $maintenance['enabled_at'],
                    'disabled_at' => $maintenance['disabled_at'],
                    'enabled_by' => $maintenance['enabled_by'],
                    'last_updated' => $maintenance['last_updated']
                ];
                $response['message'] = 'Status retrieved successfully';
            } else {
                $response['message'] = 'Maintenance settings not found';
            }
            break;

        case 'enable':
            // Enable maintenance mode
            $enabled_by = $_POST['enabled_by'] ?? 'API';

            $stmt = $pdo->prepare("
                UPDATE system_maintenance
                SET is_active = 1, enabled_at = NOW(), enabled_by = :enabled_by
                WHERE id = 1
            ");
            $stmt->execute([':enabled_by' => $enabled_by]);

            $response['success'] = true;
            $response['message'] = 'Maintenance mode enabled';
            $response['data'] = ['is_active' => true];
            break;

        case 'disable':
            // Disable maintenance mode
            $stmt = $pdo->prepare("
                UPDATE system_maintenance
                SET is_active = 0, disabled_at = NOW()
                WHERE id = 1
            ");
            $stmt->execute();

            $response['success'] = true;
            $response['message'] = 'Maintenance mode disabled';
            $response['data'] = ['is_active' => false];
            break;

        case 'update':
            // Update maintenance settings
            $updates = [];
            $params = [];

            if (isset($_POST['message'])) {
                $updates[] = "maintenance_message = :message";
                $params[':message'] = $_POST['message'];
            }

            if (isset($_POST['title'])) {
                $updates[] = "maintenance_title = :title";
                $params[':title'] = $_POST['title'];
            }

            if (isset($_POST['allow_api_access'])) {
                $updates[] = "allow_api_access = :api_access";
                $params[':api_access'] = (int)$_POST['allow_api_access'];
            }

            if (isset($_POST['allow_user_portal'])) {
                $updates[] = "allow_user_portal_limited = :user_portal";
                $params[':user_portal'] = (int)$_POST['allow_user_portal'];
            }

            if (!empty($updates)) {
                $sql = "UPDATE system_maintenance SET " . implode(', ', $updates) . " WHERE id = 1";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);

                $response['success'] = true;
                $response['message'] = 'Settings updated successfully';
            } else {
                $response['message'] = 'No valid parameters provided';
            }
            break;

        case 'lock':
        case 'unlock':
        case 'activate':
        case 'deactivate':
            // These actions can be implemented for WHMCS integration
            // For now, map them to maintenance mode
            if ($action === 'lock' || $action === 'deactivate') {
                // Same as enable maintenance
                $stmt = $pdo->prepare("
                    UPDATE system_maintenance
                    SET is_active = 1, enabled_at = NOW(), enabled_by = :enabled_by
                    WHERE id = 1
                ");
                $stmt->execute([':enabled_by' => 'WHMCS']);

                $response['success'] = true;
                $response['message'] = ucfirst($action) . ' completed - maintenance mode enabled';
            } else {
                // unlock or activate - disable maintenance
                $stmt = $pdo->prepare("
                    UPDATE system_maintenance
                    SET is_active = 0, disabled_at = NOW()
                    WHERE id = 1
                ");
                $stmt->execute();

                $response['success'] = true;
                $response['message'] = ucfirst($action) . ' completed - maintenance mode disabled';
            }
            break;

        default:
            http_response_code(400);
            $response['message'] = 'Invalid action. Supported: status, enable, disable, update, lock, unlock, activate, deactivate';
    }

} catch (PDOException $e) {
    http_response_code(500);
    $response['message'] = 'Database error: ' . $e->getMessage();
    error_log("Maintenance API Error: " . $e->getMessage());
}

// Output response
echo json_encode($response, JSON_PRETTY_PRINT);

// Log API access
error_log("Maintenance API: Action='{$action}' IP={$_SERVER['REMOTE_ADDR']} Success=" . ($response['success'] ? 'yes' : 'no'));
?>
