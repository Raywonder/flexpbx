<?php
/**
 * FlexPBX Inbound Routing API
 * Updated: October 16, 2025
 * API Pattern: Uses query parameter format (?path=[action])
 * Changes:
 * - Migrated from action= to path= query parameter
 * - Added delete and reload endpoints
 * - Enhanced routing types (IVR, Queue, Extension, Voicemail, Conference, Announcement, Time-based)
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$configFile = '/home/flexpbxuser/public_html/config/inbound-routes.json';

function respond($success, $message, $data = []) {
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ], $data));
    exit;
}

// Load current configuration
function loadConfig() {
    global $configFile;
    if (file_exists($configFile)) {
        $content = file_get_contents($configFile);
        return json_decode($content, true);
    }

    // Return default configuration
    return [
        'routes' => [
            'callcentric' => [
                'trunk_id' => 'cc_primary',
                'did' => '[YOUR_CALLCENTRIC_NUMBER]',
                'type' => 'ivr',
                'settings' => [
                    'menu' => '101',
                    'greeting' => 'main-greeting.wav'
                ],
                'active' => true,
                'updated' => date('Y-m-d H:i:s')
            ],
            'googlevoice' => [
                'trunk_id' => 'gv_primary',
                'did' => '12813015784',
                'type' => 'ivr',
                'settings' => [
                    'menu' => '101',
                    'greeting' => 'main-greeting.wav'
                ],
                'active' => true,
                'updated' => date('Y-m-d H:i:s')
            ]
        ],
        'default_route' => 'ivr:101',
        'business_hours' => [
            'enabled' => false,
            'timezone' => 'America/New_York',
            'schedule' => [
                'monday' => ['09:00', '17:00'],
                'tuesday' => ['09:00', '17:00'],
                'wednesday' => ['09:00', '17:00'],
                'thursday' => ['09:00', '17:00'],
                'friday' => ['09:00', '17:00']
            ]
        ]
    ];
}

// Save configuration
function saveConfig($config) {
    global $configFile;

    // Create backup
    if (file_exists($configFile)) {
        copy($configFile, $configFile . '.backup.' . time());
    }

    $json = json_encode($config, JSON_PRETTY_PRINT);
    if (file_put_contents($configFile, $json)) {
        chmod($configFile, 0644);
        return true;
    }
    return false;
}

// Get path for new query parameter format
$path = $_GET['path'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$rawInput = file_get_contents('php://input');
$postData = json_decode($rawInput, true);

switch ($path) {
    case '':
    case 'list':
    case 'get':
        // Get current configuration
        $config = loadConfig();
        respond(true, 'Configuration loaded', ['config' => $config]);
        break;

    case 'create':
    case 'update':
    case 'save':
        // Save route configuration
        if (!isset($postData['config'])) {
            respond(false, 'No configuration provided');
        }

        $routeConfig = $postData['config'];
        $trunk = $routeConfig['trunk'];
        $type = $routeConfig['type'];
        $settings = $routeConfig['settings'];

        // Load existing config
        $config = loadConfig();

        // Update route
        if (!isset($config['routes'][$trunk])) {
            respond(false, 'Invalid trunk: ' . $trunk);
        }

        $config['routes'][$trunk]['type'] = $type;
        $config['routes'][$trunk]['settings'] = $settings;
        $config['routes'][$trunk]['updated'] = date('Y-m-d H:i:s');

        // Save
        if (saveConfig($config)) {
            respond(true, 'Route saved successfully', ['route' => $config['routes'][$trunk]]);
        } else {
            respond(false, 'Failed to save configuration');
        }
        break;

    case 'test':
        // Test a route
        $trunk = $_GET['trunk'] ?? $_POST['trunk'] ?? null;
        if (!$trunk) {
            respond(false, 'No trunk specified');
        }

        $config = loadConfig();
        if (!isset($config['routes'][$trunk])) {
            respond(false, 'Trunk not found');
        }

        $route = $config['routes'][$trunk];
        respond(true, 'Route test', [
            'trunk' => $trunk,
            'route' => $route,
            'test_result' => [
                'status' => 'success',
                'message' => 'Route is valid and will direct calls to: ' . $route['type']
            ]
        ]);
        break;

    case 'set_default':
        // Set default route for unmatched calls
        $defaultRoute = $_POST['default_route'] ?? $postData['default_route'] ?? null;
        if (!$defaultRoute) {
            respond(false, 'No default route specified');
        }

        $config = loadConfig();
        $config['default_route'] = $defaultRoute;

        if (saveConfig($config)) {
            respond(true, 'Default route updated', ['default_route' => $defaultRoute]);
        } else {
            respond(false, 'Failed to update default route');
        }
        break;

    case 'options':
    case 'get_options':
        // Get available routing options
        respond(true, 'Routing options', [
            'types' => [
                'ivr' => 'IVR Menu / Auto-Attendant',
                'queue' => 'Call Queue / Ring Group',
                'conference' => 'Conference Bridge',
                'extension' => 'Direct to Extension',
                'voicemail' => 'Voicemail',
                'announcement' => 'Play Announcement',
                'time_condition' => 'Time-Based Routing'
            ],
            'ivr_menus' => ['101' => 'Main IVR', '102' => 'After Hours', '103' => 'Sales IVR'],
            'queues' => [
                'sales-queue' => 'Sales Department',
                'tech-support' => 'Technical Support',
                'accessibility-support' => 'Accessibility Support',
                'general' => 'General Queue'
            ],
            'conferences' => [
                '6000' => 'Main Conference Room',
                '6001' => 'Sales Meeting Room',
                '6002' => 'Support Team Room'
            ],
            'extensions' => [
                '2001' => 'Senior Tech Support',
                '2000' => 'Support Manager',
                '1000' => 'Sales Manager'
            ]
        ]);
        break;

    case 'delete':
        // Delete a route
        $routeId = $_GET['id'] ?? $postData['id'] ?? null;
        if (!$routeId) {
            respond(false, 'No route ID specified');
        }

        $config = loadConfig();
        if (!isset($config['routes'][$routeId])) {
            respond(false, 'Route not found');
        }

        unset($config['routes'][$routeId]);

        if (saveConfig($config)) {
            respond(true, 'Route deleted successfully');
        } else {
            respond(false, 'Failed to delete route');
        }
        break;

    case 'reload':
        // Reload Asterisk dialplan
        exec('sudo -u asterisk /usr/sbin/asterisk -rx "dialplan reload"', $output, $returnCode);

        if ($returnCode === 0) {
            respond(true, 'Dialplan reloaded successfully', ['output' => implode("\n", $output)]);
        } else {
            respond(false, 'Failed to reload dialplan', ['output' => implode("\n", $output)]);
        }
        break;

    default:
        respond(false, 'Invalid path: ' . $path);
}
?>
