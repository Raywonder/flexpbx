<?php
/**
 * FlexPBX Jellyfin Music On Hold API
 * Manages Jellyfin streaming for Asterisk MOH
 *
 * Endpoints:
 * - list_libraries - List all Jellyfin music libraries
 * - list_playlists - List all playlists
 * - get_stream_url - Get stream URL for folder/playlist
 * - add_moh_class - Add MOH class with Jellyfin source
 * - list_moh_classes - List all MOH classes
 * - test_stream - Test stream connectivity
 * - import_playlist - Import .pls or .m3u playlist
 *
 * @version 1.0.0
 * @created 2025-10-19
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

// Configuration
$jellyfin_host = getenv('JELLYFIN_HOST') ?: 'localhost';
$jellyfin_port = getenv('JELLYFIN_PORT') ?: '8096';
$jellyfin_api_key = getenv('JELLYFIN_API_KEY') ?: '';
$jellyfin_base_url = "http://{$jellyfin_host}:{$jellyfin_port}";

// Get request path
$path = $_GET['path'] ?? '';

// Route requests
switch ($path) {
    case 'list_libraries':
        listJellyfinLibraries();
        break;

    case 'list_playlists':
        listJellyfinPlaylists();
        break;

    case 'get_stream_url':
        getStreamUrl();
        break;

    case 'add_moh_class':
        addMohClass();
        break;

    case 'list_moh_classes':
        listMohClasses();
        break;

    case 'test_stream':
        testStream();
        break;

    case 'import_playlist':
        importPlaylist();
        break;

    case 'get_config':
        getConfig();
        break;

    case 'save_config':
        saveConfig();
        break;

    default:
        echo json_encode([
            'success' => false,
            'message' => 'Invalid endpoint',
            'available_endpoints' => [
                'list_libraries', 'list_playlists', 'get_stream_url',
                'add_moh_class', 'list_moh_classes', 'test_stream',
                'import_playlist', 'get_config', 'save_config'
            ]
        ]);
}

/**
 * List all Jellyfin music libraries
 */
function listJellyfinLibraries() {
    global $jellyfin_base_url, $jellyfin_api_key;

    if (empty($jellyfin_api_key)) {
        echo json_encode([
            'success' => false,
            'message' => 'Jellyfin API key not configured',
            'hint' => 'Set JELLYFIN_API_KEY environment variable or configure in admin panel'
        ]);
        return;
    }

    $url = "{$jellyfin_base_url}/Items?IncludeItemTypes=MusicArtist,MusicAlbum,Folder&Recursive=true&api_key={$jellyfin_api_key}";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
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
            'message' => 'Failed to connect to Jellyfin',
            'http_code' => $http_code
        ]);
    }
}

/**
 * List all Jellyfin playlists
 */
function listJellyfinPlaylists() {
    global $jellyfin_base_url, $jellyfin_api_key;

    if (empty($jellyfin_api_key)) {
        echo json_encode(['success' => false, 'message' => 'API key not configured']);
        return;
    }

    $url = "{$jellyfin_base_url}/Playlists?api_key={$jellyfin_api_key}";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);

    echo json_encode([
        'success' => true,
        'playlists' => $data['Items'] ?? []
    ]);
}

/**
 * Get stream URL for Jellyfin item or playlist file
 */
function getStreamUrl() {
    global $jellyfin_base_url, $jellyfin_api_key;

    $type = $_POST['type'] ?? $_GET['type'] ?? 'jellyfin'; // jellyfin, pls, m3u
    $source = $_POST['source'] ?? $_GET['source'] ?? '';

    if ($type === 'jellyfin') {
        $item_id = $source;
        $stream_url = "{$jellyfin_base_url}/Audio/{$item_id}/stream?api_key={$jellyfin_api_key}";

        echo json_encode([
            'success' => true,
            'stream_url' => $stream_url,
            'type' => 'jellyfin'
        ]);
    } elseif ($type === 'pls') {
        // Parse .pls file
        $stream_url = parsePlsFile($source);
        echo json_encode([
            'success' => true,
            'stream_url' => $stream_url,
            'type' => 'pls'
        ]);
    } elseif ($type === 'm3u') {
        // Parse .m3u file
        $stream_url = parseM3uFile($source);
        echo json_encode([
            'success' => true,
            'stream_url' => $stream_url,
            'type' => 'm3u'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid type']);
    }
}

/**
 * Parse .pls playlist file
 */
function parsePlsFile($file_path) {
    if (!file_exists($file_path)) {
        return null;
    }

    $content = file_get_contents($file_path);
    $lines = explode("\n", $content);

    foreach ($lines as $line) {
        if (preg_match('/^File\d+=(.+)$/', trim($line), $matches)) {
            return trim($matches[1]);
        }
    }

    return null;
}

/**
 * Parse .m3u playlist file
 */
function parseM3uFile($file_path) {
    if (!file_exists($file_path)) {
        return null;
    }

    $content = file_get_contents($file_path);
    $lines = explode("\n", $content);

    foreach ($lines as $line) {
        $line = trim($line);
        if (!empty($line) && !str_starts_with($line, '#')) {
            return $line;
        }
    }

    return null;
}

/**
 * Add MOH class to Asterisk
 */
function addMohClass() {
    $name = $_POST['name'] ?? '';
    $type = $_POST['type'] ?? 'custom'; // files, custom, quietmp3
    $source = $_POST['source'] ?? '';
    $description = $_POST['description'] ?? '';

    if (empty($name) || empty($source)) {
        echo json_encode(['success' => false, 'message' => 'Name and source required']);
        return;
    }

    // Read current musiconhold.conf
    $moh_conf = '/etc/asterisk/musiconhold.conf';
    $current_config = file_exists($moh_conf) ? file_get_contents($moh_conf) : '';

    // Create new MOH class configuration
    $new_class = "\n[{$name}]\n";

    if ($type === 'custom') {
        // For streaming (Jellyfin, .pls, .m3u)
        $new_class .= "mode=custom\n";
        $new_class .= "application=/usr/bin/mpg123 -q -r 8000 --mono -s \"{$source}\"\n";
    } elseif ($type === 'files') {
        // For local files
        $new_class .= "mode=files\n";
        $new_class .= "directory={$source}\n";
        $new_class .= "sort=random\n";
    }

    if (!empty($description)) {
        $new_class .= "; {$description}\n";
    }

    // Append to config
    $updated_config = $current_config . $new_class;

    // Write to temp file first
    $temp_file = '/tmp/musiconhold.conf.tmp';
    file_put_contents($temp_file, $updated_config);

    // Copy with sudo
    exec("sudo cp {$temp_file} {$moh_conf} 2>&1", $output, $return_code);
    exec("sudo chown asterisk:asterisk {$moh_conf} 2>&1");
    exec("sudo chmod 640 {$moh_conf} 2>&1");

    if ($return_code === 0) {
        // Reload MOH module
        exec("sudo asterisk -rx 'moh reload' 2>&1", $reload_output);

        echo json_encode([
            'success' => true,
            'message' => 'MOH class added successfully',
            'name' => $name,
            'reload_output' => implode("\n", $reload_output)
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update configuration',
            'output' => implode("\n", $output)
        ]);
    }
}

/**
 * List all MOH classes
 */
function listMohClasses() {
    exec("sudo asterisk -rx 'moh show classes' 2>&1", $output);

    $classes = [];
    foreach ($output as $line) {
        if (preg_match('/^Class:\s+(\S+)/', $line, $matches)) {
            $classes[] = $matches[1];
        }
    }

    echo json_encode([
        'success' => true,
        'classes' => $classes,
        'raw_output' => implode("\n", $output)
    ]);
}

/**
 * Test stream connectivity
 */
function testStream() {
    $url = $_POST['url'] ?? $_GET['url'] ?? '';

    if (empty($url)) {
        echo json_encode(['success' => false, 'message' => 'URL required']);
        return;
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    echo json_encode([
        'success' => $http_code === 200,
        'http_code' => $http_code,
        'content_type' => $content_type,
        'url' => $url,
        'reachable' => $http_code === 200
    ]);
}

/**
 * Import playlist file (.pls or .m3u)
 */
function importPlaylist() {
    $file_path = $_POST['file_path'] ?? '';

    if (empty($file_path) || !file_exists($file_path)) {
        echo json_encode(['success' => false, 'message' => 'File not found']);
        return;
    }

    $ext = pathinfo($file_path, PATHINFO_EXTENSION);

    if ($ext === 'pls') {
        $stream_url = parsePlsFile($file_path);
    } elseif ($ext === 'm3u') {
        $stream_url = parseM3uFile($file_path);
    } else {
        echo json_encode(['success' => false, 'message' => 'Unsupported file type']);
        return;
    }

    if ($stream_url) {
        echo json_encode([
            'success' => true,
            'stream_url' => $stream_url,
            'file_type' => $ext,
            'file_path' => $file_path
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to parse playlist']);
    }
}

/**
 * Get current configuration
 */
function getConfig() {
    $config_file = __DIR__ . '/../config/jellyfin-moh.json';

    if (file_exists($config_file)) {
        $config = json_decode(file_get_contents($config_file), true);
    } else {
        $config = [
            'jellyfin_host' => 'localhost',
            'jellyfin_port' => '8096',
            'jellyfin_api_key' => '',
            'jellyfin_user_id' => '',
            'default_moh_class' => 'default',
            'playlist_directories' => [
                '/home/dom/apps/media/streams',
                '/home/dom/apps/media/playlists'
            ]
        ];
    }

    echo json_encode([
        'success' => true,
        'config' => $config
    ]);
}

/**
 * Save configuration
 */
function saveConfig() {
    $config = json_decode(file_get_contents('php://input'), true);

    if (!$config) {
        echo json_encode(['success' => false, 'message' => 'Invalid configuration data']);
        return;
    }

    $config_file = __DIR__ . '/../config/jellyfin-moh.json';
    $config_dir = dirname($config_file);

    if (!is_dir($config_dir)) {
        mkdir($config_dir, 0755, true);
    }

    if (file_put_contents($config_file, json_encode($config, JSON_PRETTY_PRINT))) {
        echo json_encode([
            'success' => true,
            'message' => 'Configuration saved successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to save configuration'
        ]);
    }
}
