<?php
/**
 * FlexPBX Provisioning Settings API
 *
 * RESTful API for managing auto-provisioning settings
 *
 * Endpoints:
 * - GET ?action=get_all              - Get all settings grouped by category
 * - GET ?action=get&key=setting_key  - Get specific setting
 * - POST ?action=update              - Update settings (JSON body)
 * - POST ?action=reset               - Reset to defaults
 * - GET ?action=preview_next_extension - Get next available extension
 * - GET ?action=extension_range_info  - Get extension range statistics
 *
 * @author FlexPBX
 * @version 1.0.0
 * @created 2025-10-17
 */

header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once __DIR__ . '/../includes/ProvisioningSettings.php';

// Simple authentication check
session_start();
$is_authenticated = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
$username = $_SESSION['admin_username'] ?? 'guest';

// For API testing, you can add API key authentication here
if (!$is_authenticated) {
    // Check for API key in header
    $api_key = $_SERVER['HTTP_X_API_KEY'] ?? null;
    if ($api_key) {
        // Validate API key (implement your validation logic)
        // $is_authenticated = validateApiKey($api_key);
    }
}

/**
 * Send JSON response
 */
function sendResponse($success, $data = null, $message = '', $code = 200) {
    http_response_code($code);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('c')
    ], JSON_PRETTY_PRINT);
    exit;
}

/**
 * Handle GET requests
 */
function handleGet() {
    global $is_authenticated;

    $action = $_GET['action'] ?? 'get_all';

    // Most GET actions require authentication
    if (!$is_authenticated && !in_array($action, ['extension_range_info'])) {
        sendResponse(false, null, 'Authentication required', 401);
    }

    switch ($action) {
        case 'get_all':
            $category = $_GET['category'] ?? null;
            if ($category) {
                $settings = ProvisioningSettings::getAll($category);
                sendResponse(true, $settings, "Settings for category: $category");
            } else {
                $settings = ProvisioningSettings::getAllByCategory();
                sendResponse(true, $settings, 'All settings retrieved');
            }
            break;

        case 'get':
            $key = $_GET['key'] ?? null;
            if (!$key) {
                sendResponse(false, null, 'Missing required parameter: key', 400);
            }
            $value = ProvisioningSettings::get($key);
            sendResponse(true, ['key' => $key, 'value' => $value], 'Setting retrieved');
            break;

        case 'preview_next_extension':
            $next = ProvisioningSettings::getNextExtension();
            if ($next === null) {
                sendResponse(false, null, 'Unable to determine next extension', 500);
            }
            sendResponse(true, ['next_extension' => $next], 'Next extension available');
            break;

        case 'extension_range_info':
            $info = ProvisioningSettings::getExtensionRangeInfo();
            sendResponse(true, $info, 'Extension range information');
            break;

        case 'export':
            $json = ProvisioningSettings::exportJSON();
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="provisioning_settings_' . date('Y-m-d') . '.json"');
            echo $json;
            exit;

        default:
            sendResponse(false, null, 'Unknown action', 400);
    }
}

/**
 * Handle POST requests
 */
function handlePost() {
    global $is_authenticated, $username;

    if (!$is_authenticated) {
        sendResponse(false, null, 'Authentication required', 401);
    }

    $action = $_POST['action'] ?? $_GET['action'] ?? null;

    // Get JSON body if present
    $json_body = file_get_contents('php://input');
    $json_data = json_decode($json_body, true);

    if ($json_data && isset($json_data['action'])) {
        $action = $json_data['action'];
    }

    switch ($action) {
        case 'update':
            // Get settings to update
            $settings = $json_data['settings'] ?? $_POST['settings'] ?? null;

            if (!$settings || !is_array($settings)) {
                sendResponse(false, null, 'Invalid settings data', 400);
            }

            $updated = [];
            $failed = [];

            foreach ($settings as $key => $data) {
                if (is_array($data) && isset($data['value'], $data['type'])) {
                    $success = ProvisioningSettings::set(
                        $key,
                        $data['value'],
                        $data['type'],
                        $username
                    );

                    if ($success) {
                        $updated[] = $key;
                    } else {
                        $failed[] = $key;
                    }
                }
            }

            // Clear cache after updates
            ProvisioningSettings::clearCache();

            if (count($failed) > 0) {
                sendResponse(
                    false,
                    ['updated' => $updated, 'failed' => $failed],
                    'Some settings failed to update',
                    500
                );
            } else {
                sendResponse(
                    true,
                    ['updated' => $updated],
                    count($updated) . ' setting(s) updated successfully'
                );
            }
            break;

        case 'set':
            // Set a single setting
            $key = $json_data['key'] ?? $_POST['key'] ?? null;
            $value = $json_data['value'] ?? $_POST['value'] ?? null;
            $type = $json_data['type'] ?? $_POST['type'] ?? 'string';

            if (!$key) {
                sendResponse(false, null, 'Missing required parameter: key', 400);
            }

            $success = ProvisioningSettings::set($key, $value, $type, $username);

            if ($success) {
                ProvisioningSettings::clearCache();
                sendResponse(true, ['key' => $key, 'value' => $value], 'Setting updated');
            } else {
                sendResponse(false, null, 'Failed to update setting', 500);
            }
            break;

        case 'reset':
            $confirm = $json_data['confirm'] ?? $_POST['confirm'] ?? false;

            if ($confirm !== 'yes' && $confirm !== true) {
                sendResponse(false, null, 'Reset requires confirmation', 400);
            }

            $success = ProvisioningSettings::reset();

            if ($success) {
                sendResponse(true, null, 'Settings reset to defaults');
            } else {
                sendResponse(false, null, 'Failed to reset settings', 500);
            }
            break;

        case 'import':
            $json_import = $json_data['settings'] ?? null;

            if (!$json_import) {
                sendResponse(false, null, 'Missing settings data', 400);
            }

            $success = ProvisioningSettings::importJSON(
                json_encode($json_import),
                $username
            );

            if ($success) {
                ProvisioningSettings::clearCache();
                sendResponse(true, null, 'Settings imported successfully');
            } else {
                sendResponse(false, null, 'Failed to import settings', 500);
            }
            break;

        default:
            sendResponse(false, null, 'Unknown action', 400);
    }
}

/**
 * Handle DELETE requests
 */
function handleDelete() {
    global $is_authenticated;

    if (!$is_authenticated) {
        sendResponse(false, null, 'Authentication required', 401);
    }

    $key = $_GET['key'] ?? null;

    if (!$key) {
        sendResponse(false, null, 'Missing required parameter: key', 400);
    }

    // Delete setting by setting value to null
    $success = ProvisioningSettings::set($key, null, 'string', 'system');

    if ($success) {
        sendResponse(true, null, "Setting '$key' deleted");
    } else {
        sendResponse(false, null, 'Failed to delete setting', 500);
    }
}

// Main request handler
try {
    $method = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
        case 'GET':
            handleGet();
            break;

        case 'POST':
            handlePost();
            break;

        case 'PUT':
            // Treat PUT as POST for simplicity
            $_POST = json_decode(file_get_contents('php://input'), true) ?? [];
            handlePost();
            break;

        case 'DELETE':
            handleDelete();
            break;

        default:
            sendResponse(false, null, 'Method not allowed', 405);
    }

} catch (Exception $e) {
    error_log("Provisioning Settings API Error: " . $e->getMessage());
    sendResponse(false, null, 'Internal server error: ' . $e->getMessage(), 500);
}
