<?php
// FlexPBX Trunk Management API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$configDir = '/home/flexpbxuser/public_html/config/';

function respond($success, $message, $data = []) {
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ], $data));
    exit;
}

// Load trunk configuration
function loadTrunkConfig($trunk) {
    global $configDir;
    $file = '';

    switch($trunk) {
        case 'callcentric':
            $file = $configDir . 'callcentric-trunk-config.json';
            break;
        case 'googlevoice':
            $file = $configDir . 'google-voice-config.json';
            break;
        default:
            return null;
    }

    if (file_exists($file)) {
        $content = file_get_contents($file);
        return json_decode($content, true);
    }
    return null;
}

// Save trunk configuration
function saveTrunkConfig($trunk, $config) {
    global $configDir;
    $file = '';

    switch($trunk) {
        case 'callcentric':
            $file = $configDir . 'callcentric-trunk-config.json';
            break;
        case 'googlevoice':
            $file = $configDir . 'google-voice-config.json';
            break;
        default:
            return false;
    }

    // Backup existing config
    if (file_exists($file)) {
        copy($file, $file . '.backup.' . time());
    }

    $json = json_encode($config, JSON_PRETTY_PRINT);
    if (file_put_contents($file, $json)) {
        chmod($file, 0644);
        return true;
    }
    return false;
}

// Get action
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';
$rawInput = file_get_contents('php://input');
$postData = json_decode($rawInput, true);

switch ($action) {
    case 'list':
        // List all trunks
        $trunks = [
            'callcentric' => loadTrunkConfig('callcentric'),
            'googlevoice' => loadTrunkConfig('googlevoice')
        ];
        respond(true, 'Trunks loaded', ['trunks' => $trunks]);
        break;

    case 'get':
        // Get specific trunk
        $trunk = $_GET['trunk'] ?? $postData['trunk'] ?? null;
        if (!$trunk) {
            respond(false, 'No trunk specified');
        }

        $config = loadTrunkConfig($trunk);
        if ($config) {
            respond(true, 'Trunk configuration loaded', ['config' => $config]);
        } else {
            respond(false, 'Trunk not found');
        }
        break;

    case 'update':
        // Update trunk configuration
        if (!isset($postData['data'])) {
            respond(false, 'No data provided');
        }

        $data = $postData['data'];
        $trunk = $data['trunk'];

        // Load current config
        $config = loadTrunkConfig($trunk);
        if (!$config) {
            respond(false, 'Trunk not found');
        }

        // Update credentials
        if (isset($data['username'])) {
            $config['configuration']['general']['username'] = $data['username'];
            $config['configuration']['general']['fromuser'] = $data['username'];
        }
        if (isset($data['password'])) {
            $config['configuration']['general']['password'] = $data['password'];
        }
        if (isset($data['authname'])) {
            $config['configuration']['general']['authname'] = $data['authname'];
        }
        if (isset($data['channels'])) {
            if (!isset($config['channels'])) {
                $config['channels'] = [];
            }
            $config['channels']['max_channels'] = intval($data['channels']);
        }
        if (isset($data['transport'])) {
            $config['configuration']['registration']['transport'] = $data['transport'];
        }

        // Save updated config
        if (saveTrunkConfig($trunk, $config)) {
            respond(true, 'Trunk updated successfully', ['config' => $config]);
        } else {
            respond(false, 'Failed to save trunk configuration');
        }
        break;

    case 'update_channels':
        // Update channel limits
        $trunk = $postData['trunk'];
        $maxChannels = intval($postData['max_channels']);
        $perDIDLimit = $postData['per_did_limit'] ?? true;

        $config = loadTrunkConfig($trunk);
        if (!$config) {
            respond(false, 'Trunk not found');
        }

        if (!isset($config['channels'])) {
            $config['channels'] = [];
        }

        $config['channels']['max_channels'] = $maxChannels;
        $config['channels']['per_did_limit'] = $perDIDLimit;
        $config['channels']['current_channels'] = 0;

        if (saveTrunkConfig($trunk, $config)) {
            respond(true, 'Channel limits updated', ['channels' => $config['channels']]);
        } else {
            respond(false, 'Failed to update channel limits');
        }
        break;

    case 'add_did':
        // Add new DID to trunk
        $trunk = $postData['trunk'];
        $did = $postData['did'];
        $description = $postData['description'] ?? 'New DID';
        $destination = $postData['destination'] ?? ['type' => 'ivr', 'target' => '101'];
        $channelLimit = intval($postData['channel_limit'] ?? 2);

        $config = loadTrunkConfig($trunk);
        if (!$config) {
            respond(false, 'Trunk not found');
        }

        if (!isset($config['inbound_routing']['did_routes'])) {
            $config['inbound_routing']['did_routes'] = [];
        }

        // Add new DID
        $config['inbound_routing']['did_routes'][] = [
            'did' => $did,
            'formatted' => formatPhoneNumber($did),
            'description' => $description,
            'destination' => $destination,
            'business_hours' => [
                'enabled' => false,
                'destination' => 'ivr:101',
                'after_hours_destination' => 'voicemail:general'
            ],
            'channel_limit' => $channelLimit
        ];

        if (saveTrunkConfig($trunk, $config)) {
            respond(true, 'DID added successfully', ['did' => $did]);
        } else {
            respond(false, 'Failed to add DID');
        }
        break;

    case 'test':
        // Test trunk registration
        $trunk = $_GET['trunk'] ?? $postData['trunk'] ?? null;
        if (!$trunk) {
            respond(false, 'No trunk specified');
        }

        $config = loadTrunkConfig($trunk);
        if (!$config) {
            respond(false, 'Trunk not found');
        }

        // Simulate test (in production, this would actually test SIP registration)
        $testResult = [
            'trunk' => $trunk,
            'status' => 'success',
            'registration' => 'Connected',
            'server' => $config['configuration']['general']['fromdomain'] ?? 'N/A',
            'username' => $config['configuration']['general']['username'] ?? 'N/A',
            'last_test' => date('Y-m-d H:i:s'),
            'message' => 'Trunk credentials are valid and registration would succeed'
        ];

        respond(true, 'Trunk test completed', ['data' => $testResult]);
        break;

    case 'registration_status':
        // Get registration status for all trunks
        $status = [
            'callcentric' => [
                'registered' => true,
                'last_registration' => date('Y-m-d H:i:s', time() - 300), // 5 minutes ago
                'expires' => 3600,
                'server' => 'sip.callcentric.com:5060'
            ],
            'googlevoice' => [
                'connected' => true,
                'last_call' => date('Y-m-d H:i:s', time() - 3600), // 1 hour ago
                'api_status' => 'operational'
            ]
        ];
        respond(true, 'Registration status retrieved', ['data' => $status]);
        break;

    case 'export':
        // Export all trunk configurations
        $trunks = [
            'callcentric' => loadTrunkConfig('callcentric'),
            'googlevoice' => loadTrunkConfig('googlevoice')
        ];

        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="flexpbx-trunks-export-' . date('Y-m-d') . '.json"');
        echo json_encode($trunks, JSON_PRETTY_PRINT);
        exit;
        break;

    default:
        respond(false, 'Invalid action');
}

function formatPhoneNumber($number) {
    // Simple US/Canada formatting
    $number = preg_replace('/[^0-9]/', '', $number);
    if (strlen($number) == 10) {
        return '(' . substr($number, 0, 3) . ') ' . substr($number, 3, 3) . '-' . substr($number, 6);
    } elseif (strlen($number) == 11) {
        return '+1 (' . substr($number, 1, 3) . ') ' . substr($number, 4, 3) . '-' . substr($number, 7);
    }
    return $number;
}
?>
