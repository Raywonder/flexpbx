<?php
/**
 * FlexPBX Jellyfin Integration API
 * Generates and manages Jellyfin API keys for FlexPBX servers
 * Provides secure access to Jellyfin media for MOH streaming
 *
 * Endpoints:
 * - generate_api_key - Generate new Jellyfin API key for FlexPBX
 * - list_api_keys - List all FlexPBX API keys
 * - revoke_api_key - Revoke an API key
 * - test_connection - Test Jellyfin connectivity
 * - get_libraries - Get music libraries (authenticated)
 * - get_stream_url - Get authenticated stream URL
 * - proxy_stream - Proxy Jellyfin stream through FlexPBX
 *
 * @version 1.0.0
 * @created 2025-10-19
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

// Jellyfin configuration
$jellyfin_config = loadJellyfinConfig();
$jellyfin_base_url = "http://{$jellyfin_config['host']}:{$jellyfin_config['port']}";

// Get request path
$path = $_GET['path'] ?? '';

// Route requests
switch ($path) {
    case 'generate_api_key':
        generateApiKey();
        break;

    case 'list_api_keys':
        listApiKeys();
        break;

    case 'revoke_api_key':
        revokeApiKey();
        break;

    case 'test_connection':
        testConnection();
        break;

    case 'get_libraries':
        getLibraries();
        break;

    case 'get_stream_url':
        getStreamUrl();
        break;

    case 'proxy_stream':
        proxyStream();
        break;

    case 'authenticate':
        authenticateJellyfin();
        break;

    default:
        echo json_encode([
            'success' => false,
            'message' => 'Invalid endpoint',
            'available_endpoints' => [
                'generate_api_key', 'list_api_keys', 'revoke_api_key',
                'test_connection', 'get_libraries', 'get_stream_url',
                'proxy_stream', 'authenticate'
            ]
        ]);
}

/**
 * Load Jellyfin configuration
 */
function loadJellyfinConfig() {
    $config_file = __DIR__ . '/../config/jellyfin-integration.json';

    if (file_exists($config_file)) {
        $config = json_decode(file_get_contents($config_file), true);
    } else {
        // Default configuration
        $config = [
            'host' => 'localhost',
            'port' => '8096',
            'admin_username' => '',
            'admin_password' => '',
            'access_token' => '',
            'user_id' => '',
            'api_keys' => []
        ];

        // Save default config
        saveJellyfinConfig($config);
    }

    return $config;
}

/**
 * Save Jellyfin configuration
 */
function saveJellyfinConfig($config) {
    $config_file = __DIR__ . '/../config/jellyfin-integration.json';
    $config_dir = dirname($config_file);

    if (!is_dir($config_dir)) {
        mkdir($config_dir, 0755, true);
    }

    file_put_contents($config_file, json_encode($config, JSON_PRETTY_PRINT));
    chmod($config_file, 0600); // Secure permissions
}

/**
 * Authenticate with Jellyfin and get access token
 */
function authenticateJellyfin() {
    global $jellyfin_base_url, $jellyfin_config;

    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Username and password required']);
        return;
    }

    // Authenticate with Jellyfin
    $auth_url = "{$jellyfin_base_url}/Users/AuthenticateByName";

    $auth_data = [
        'Username' => $username,
        'Pw' => $password
    ];

    $ch = curl_init($auth_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($auth_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-Emby-Authorization: MediaBrowser Client="FlexPBX", Device="Server", DeviceId="flexpbx-server", Version="1.0.0"'
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200) {
        $auth_result = json_decode($response, true);

        // Save credentials and token
        $jellyfin_config['admin_username'] = $username;
        $jellyfin_config['admin_password'] = $password; // In production, hash this
        $jellyfin_config['access_token'] = $auth_result['AccessToken'];
        $jellyfin_config['user_id'] = $auth_result['User']['Id'];

        saveJellyfinConfig($jellyfin_config);

        echo json_encode([
            'success' => true,
            'message' => 'Authentication successful',
            'access_token' => $auth_result['AccessToken'],
            'user_id' => $auth_result['User']['Id'],
            'user_name' => $auth_result['User']['Name']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Authentication failed',
            'http_code' => $http_code
        ]);
    }
}

/**
 * Generate FlexPBX API key for Jellyfin access
 */
function generateApiKey() {
    global $jellyfin_config;

    $server_name = $_POST['server_name'] ?? 'FlexPBX Server ' . date('Y-m-d');
    $description = $_POST['description'] ?? 'Music On Hold streaming';

    // Generate secure API key
    $api_key = bin2hex(random_bytes(32)); // 64-character hex string

    // Store API key
    $key_data = [
        'key' => $api_key,
        'server_name' => $server_name,
        'description' => $description,
        'created_at' => date('c'),
        'last_used' => null,
        'active' => true
    ];

    $jellyfin_config['api_keys'][] = $key_data;
    saveJellyfinConfig($jellyfin_config);

    echo json_encode([
        'success' => true,
        'message' => 'API key generated successfully',
        'api_key' => $api_key,
        'server_name' => $server_name,
        'created_at' => $key_data['created_at']
    ]);
}

/**
 * List all FlexPBX API keys
 */
function listApiKeys() {
    global $jellyfin_config;

    // Don't expose full keys, only show partial
    $keys = array_map(function($key) {
        return [
            'key_preview' => substr($key['key'], 0, 8) . '...' . substr($key['key'], -8),
            'server_name' => $key['server_name'],
            'description' => $key['description'],
            'created_at' => $key['created_at'],
            'last_used' => $key['last_used'],
            'active' => $key['active']
        ];
    }, $jellyfin_config['api_keys'] ?? []);

    echo json_encode([
        'success' => true,
        'api_keys' => $keys,
        'total' => count($keys)
    ]);
}

/**
 * Revoke an API key
 */
function revokeApiKey() {
    global $jellyfin_config;

    $api_key = $_POST['api_key'] ?? '';

    if (empty($api_key)) {
        echo json_encode(['success' => false, 'message' => 'API key required']);
        return;
    }

    // Find and deactivate key
    $found = false;
    foreach ($jellyfin_config['api_keys'] as &$key) {
        if ($key['key'] === $api_key) {
            $key['active'] = false;
            $found = true;
            break;
        }
    }

    if ($found) {
        saveJellyfinConfig($jellyfin_config);

        echo json_encode([
            'success' => true,
            'message' => 'API key revoked successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'API key not found'
        ]);
    }
}

/**
 * Test Jellyfin connection
 */
function testConnection() {
    global $jellyfin_base_url;

    $test_url = "{$jellyfin_base_url}/System/Info/Public";

    $ch = curl_init($test_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200) {
        $server_info = json_decode($response, true);

        echo json_encode([
            'success' => true,
            'message' => 'Connection successful',
            'server_name' => $server_info['ServerName'] ?? 'Unknown',
            'version' => $server_info['Version'] ?? 'Unknown',
            'id' => $server_info['Id'] ?? 'Unknown'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Connection failed',
            'http_code' => $http_code
        ]);
    }
}

/**
 * Get music libraries with authentication
 */
function getLibraries() {
    global $jellyfin_base_url, $jellyfin_config;

    // Check for FlexPBX API key or Jellyfin access token
    $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $api_key = $_GET['api_key'] ?? '';

    if (!empty($api_key)) {
        // Validate FlexPBX API key
        if (!validateFlexPBXApiKey($api_key)) {
            echo json_encode(['success' => false, 'message' => 'Invalid API key']);
            return;
        }

        // Update last used timestamp
        updateApiKeyUsage($api_key);
    }

    // Use stored access token
    $access_token = $jellyfin_config['access_token'] ?? '';
    $user_id = $jellyfin_config['user_id'] ?? '';

    if (empty($access_token)) {
        echo json_encode(['success' => false, 'message' => 'Not authenticated with Jellyfin']);
        return;
    }

    // Get music libraries
    $url = "{$jellyfin_base_url}/Users/{$user_id}/Items?IncludeItemTypes=MusicArtist,MusicAlbum,Folder&Recursive=true";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "X-MediaBrowser-Token: {$access_token}"
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200) {
        $data = json_decode($response, true);

        echo json_encode([
            'success' => true,
            'libraries' => $data['Items'] ?? [],
            'total' => $data['TotalRecordCount'] ?? 0
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to fetch libraries',
            'http_code' => $http_code
        ]);
    }
}

/**
 * Get authenticated stream URL
 */
function getStreamUrl() {
    global $jellyfin_base_url, $jellyfin_config;

    $item_id = $_GET['item_id'] ?? $_POST['item_id'] ?? '';
    $api_key = $_GET['api_key'] ?? '';

    if (empty($item_id)) {
        echo json_encode(['success' => false, 'message' => 'Item ID required']);
        return;
    }

    // Validate FlexPBX API key
    if (!empty($api_key) && !validateFlexPBXApiKey($api_key)) {
        echo json_encode(['success' => false, 'message' => 'Invalid API key']);
        return;
    }

    $access_token = $jellyfin_config['access_token'] ?? '';

    // Generate stream URL with authentication
    $stream_url = "{$jellyfin_base_url}/Audio/{$item_id}/stream.mp3?static=true&api_key={$access_token}";

    // Alternative: Use proxy URL for better security
    $proxy_url = "http://" . $_SERVER['HTTP_HOST'] . "/api/jellyfin-integration.php?path=proxy_stream&item_id={$item_id}&api_key={$api_key}";

    echo json_encode([
        'success' => true,
        'stream_url' => $stream_url,
        'proxy_url' => $proxy_url,
        'item_id' => $item_id
    ]);
}

/**
 * Proxy Jellyfin stream through FlexPBX (for better security)
 */
function proxyStream() {
    global $jellyfin_base_url, $jellyfin_config;

    $item_id = $_GET['item_id'] ?? '';
    $api_key = $_GET['api_key'] ?? '';

    // Validate FlexPBX API key
    if (!validateFlexPBXApiKey($api_key)) {
        http_response_code(403);
        echo 'Forbidden';
        return;
    }

    $access_token = $jellyfin_config['access_token'] ?? '';
    $stream_url = "{$jellyfin_base_url}/Audio/{$item_id}/stream.mp3?static=true&api_key={$access_token}";

    // Stream the audio
    header('Content-Type: audio/mpeg');
    header('X-Content-Duration: -1'); // Unknown duration
    header('Cache-Control: no-cache');

    $ch = curl_init($stream_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
    curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_exec($ch);
    curl_close($ch);
}

/**
 * Validate FlexPBX API key
 */
function validateFlexPBXApiKey($api_key) {
    global $jellyfin_config;

    foreach ($jellyfin_config['api_keys'] ?? [] as $key) {
        if ($key['key'] === $api_key && $key['active'] === true) {
            return true;
        }
    }

    return false;
}

/**
 * Update API key last used timestamp
 */
function updateApiKeyUsage($api_key) {
    global $jellyfin_config;

    foreach ($jellyfin_config['api_keys'] as &$key) {
        if ($key['key'] === $api_key) {
            $key['last_used'] = date('c');
            break;
        }
    }

    saveJellyfinConfig($jellyfin_config);
}
