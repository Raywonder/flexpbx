<?php
/**
 * FlexPBX Conference Music Control API
 * Manage music on hold for conference rooms
 *
 * Endpoints:
 * - start_music: Start music in a conference room
 * - stop_music: Stop music in a conference room
 * - get_room_status: Get current status of conference room
 * - list_rooms: List all active conference rooms
 * - get_music_classes: Get available MOH classes
 * - set_room_music: Set default music class for a room
 * - toggle_auto_music: Enable/disable auto-music when alone
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Configuration
$asterisk_manager_host = 'localhost';
$asterisk_manager_port = 5038;
$asterisk_manager_user = 'admin';
$asterisk_manager_secret = 'FlexPBX2024!';

$config_file = '/home/flexpbxuser/public_html/config/conference-music.json';

// Get request path
$path = $_GET['path'] ?? '';

// Route requests
switch ($path) {
    case 'start_music':
        startMusic();
        break;
    case 'stop_music':
        stopMusic();
        break;
    case 'get_room_status':
        getRoomStatus();
        break;
    case 'list_rooms':
        listRooms();
        break;
    case 'get_music_classes':
        getMusicClasses();
        break;
    case 'set_room_music':
        setRoomMusic();
        break;
    case 'toggle_auto_music':
        toggleAutoMusic();
        break;
    case 'get_config':
        getConfig();
        break;
    case 'save_config':
        saveConfig();
        break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid path']);
        break;
}

/**
 * Start music in a conference room
 */
function startMusic() {
    global $config_file;

    $room = $_POST['room'] ?? '';
    $music_class = $_POST['music_class'] ?? 'default';

    if (empty($room)) {
        echo json_encode(['success' => false, 'error' => 'Room number required']);
        return;
    }

    // Execute Asterisk CLI command to start MOH in conference
    $command = "sudo asterisk -rx \"confbridge moh {$room} start {$music_class}\"";
    $output = shell_exec($command . ' 2>&1');

    // Log the action
    logMusicAction($room, 'start', $music_class);

    echo json_encode([
        'success' => true,
        'room' => $room,
        'action' => 'start',
        'music_class' => $music_class,
        'output' => $output,
        'message' => "Music started in conference room {$room}"
    ]);
}

/**
 * Stop music in a conference room
 */
function stopMusic() {
    $room = $_POST['room'] ?? '';

    if (empty($room)) {
        echo json_encode(['success' => false, 'error' => 'Room number required']);
        return;
    }

    // Execute Asterisk CLI command to stop MOH in conference
    $command = "sudo asterisk -rx \"confbridge moh {$room} stop\"";
    $output = shell_exec($command . ' 2>&1');

    // Log the action
    logMusicAction($room, 'stop', '');

    echo json_encode([
        'success' => true,
        'room' => $room,
        'action' => 'stop',
        'output' => $output,
        'message' => "Music stopped in conference room {$room}"
    ]);
}

/**
 * Get status of a conference room
 */
function getRoomStatus() {
    $room = $_GET['room'] ?? '';

    if (empty($room)) {
        echo json_encode(['success' => false, 'error' => 'Room number required']);
        return;
    }

    // Get conference participants
    $command = "sudo asterisk -rx \"confbridge list {$room}\"";
    $output = shell_exec($command . ' 2>&1');

    // Parse the output
    $participants = parseConfBridgeList($output);

    // Check if music is playing (we'll track this in config)
    $config = loadConfig();
    $music_playing = $config['rooms'][$room]['music_playing'] ?? false;
    $music_class = $config['rooms'][$room]['music_class'] ?? 'default';
    $auto_music = $config['rooms'][$room]['auto_music'] ?? false;

    echo json_encode([
        'success' => true,
        'room' => $room,
        'participant_count' => count($participants),
        'participants' => $participants,
        'music_playing' => $music_playing,
        'music_class' => $music_class,
        'auto_music_enabled' => $auto_music,
        'is_alone' => count($participants) === 1
    ]);
}

/**
 * List all active conference rooms
 */
function listRooms() {
    $command = "sudo asterisk -rx \"confbridge list\"";
    $output = shell_exec($command . ' 2>&1');

    $rooms = parseConfBridgeRooms($output);

    // Enhance with music status
    $config = loadConfig();
    foreach ($rooms as &$room) {
        $room_id = $room['room'];
        $room['music_playing'] = $config['rooms'][$room_id]['music_playing'] ?? false;
        $room['music_class'] = $config['rooms'][$room_id]['music_class'] ?? 'default';
        $room['auto_music'] = $config['rooms'][$room_id]['auto_music'] ?? false;
    }

    echo json_encode([
        'success' => true,
        'rooms' => $rooms,
        'total_rooms' => count($rooms)
    ]);
}

/**
 * Get available music on hold classes
 */
function getMusicClasses() {
    $command = "sudo asterisk -rx \"moh show classes\"";
    $output = shell_exec($command . ' 2>&1');

    $classes = parseMohClasses($output);

    echo json_encode([
        'success' => true,
        'music_classes' => $classes,
        'total_classes' => count($classes)
    ]);
}

/**
 * Set default music class for a room
 */
function setRoomMusic() {
    $room = $_POST['room'] ?? '';
    $music_class = $_POST['music_class'] ?? 'default';

    if (empty($room)) {
        echo json_encode(['success' => false, 'error' => 'Room number required']);
        return;
    }

    $config = loadConfig();

    if (!isset($config['rooms'][$room])) {
        $config['rooms'][$room] = [];
    }

    $config['rooms'][$room]['music_class'] = $music_class;
    $config['rooms'][$room]['updated_at'] = date('c');

    saveConfigData($config);

    echo json_encode([
        'success' => true,
        'room' => $room,
        'music_class' => $music_class,
        'message' => "Default music class set for room {$room}"
    ]);
}

/**
 * Toggle auto-music when alone in room
 */
function toggleAutoMusic() {
    $room = $_POST['room'] ?? '';
    $enabled = filter_var($_POST['enabled'] ?? 'false', FILTER_VALIDATE_BOOLEAN);

    if (empty($room)) {
        echo json_encode(['success' => false, 'error' => 'Room number required']);
        return;
    }

    $config = loadConfig();

    if (!isset($config['rooms'][$room])) {
        $config['rooms'][$room] = [];
    }

    $config['rooms'][$room]['auto_music'] = $enabled;
    $config['rooms'][$room]['updated_at'] = date('c');

    saveConfigData($config);

    echo json_encode([
        'success' => true,
        'room' => $room,
        'auto_music_enabled' => $enabled,
        'message' => "Auto-music " . ($enabled ? 'enabled' : 'disabled') . " for room {$room}"
    ]);
}

/**
 * Get configuration
 */
function getConfig() {
    $config = loadConfig();
    echo json_encode([
        'success' => true,
        'config' => $config
    ]);
}

/**
 * Save configuration
 */
function saveConfig() {
    $json_data = file_get_contents('php://input');
    $data = json_decode($json_data, true);

    if (!$data) {
        echo json_encode(['success' => false, 'error' => 'Invalid JSON data']);
        return;
    }

    saveConfigData($data);

    echo json_encode([
        'success' => true,
        'message' => 'Configuration saved successfully'
    ]);
}

/**
 * Helper: Load configuration
 */
function loadConfig() {
    global $config_file;

    if (!file_exists($config_file)) {
        return [
            'rooms' => [],
            'global_settings' => [
                'default_music_class' => 'default',
                'auto_music_enabled' => false
            ]
        ];
    }

    $json = file_get_contents($config_file);
    return json_decode($json, true) ?: [];
}

/**
 * Helper: Save configuration
 */
function saveConfigData($config) {
    global $config_file;

    // Ensure config directory exists
    $config_dir = dirname($config_file);
    if (!is_dir($config_dir)) {
        mkdir($config_dir, 0700, true);
    }

    file_put_contents($config_file, json_encode($config, JSON_PRETTY_PRINT));
    chmod($config_file, 0600);
}

/**
 * Helper: Log music action
 */
function logMusicAction($room, $action, $music_class) {
    $config = loadConfig();

    if (!isset($config['rooms'][$room])) {
        $config['rooms'][$room] = [];
    }

    $config['rooms'][$room]['music_playing'] = ($action === 'start');
    if ($action === 'start') {
        $config['rooms'][$room]['music_class'] = $music_class;
    }
    $config['rooms'][$room]['last_action'] = $action;
    $config['rooms'][$room]['last_action_time'] = date('c');

    saveConfigData($config);
}

/**
 * Helper: Parse confbridge list output
 */
function parseConfBridgeList($output) {
    $participants = [];
    $lines = explode("\n", $output);

    foreach ($lines as $line) {
        // Parse participant lines (skip headers)
        if (preg_match('/^\s*(\S+)\s+(\d+)\s+(\S+)\s+(\S+)\s+(\S+)/', $line, $matches)) {
            $participants[] = [
                'channel' => $matches[1],
                'user' => $matches[2],
                'profile' => $matches[3],
                'menu' => $matches[4],
                'muted' => ($matches[5] === 'Yes')
            ];
        }
    }

    return $participants;
}

/**
 * Helper: Parse confbridge rooms output
 */
function parseConfBridgeRooms($output) {
    $rooms = [];
    $lines = explode("\n", $output);

    foreach ($lines as $line) {
        // Parse room lines
        if (preg_match('/^\s*(\S+)\s+(\d+)\s+(\d+)\s+(\d+)/', $line, $matches)) {
            $rooms[] = [
                'room' => $matches[1],
                'users' => (int)$matches[2],
                'marked' => (int)$matches[3],
                'locked' => (int)$matches[4]
            ];
        }
    }

    return $rooms;
}

/**
 * Helper: Parse MOH classes
 */
function parseMohClasses($output) {
    $classes = [];
    $lines = explode("\n", $output);

    foreach ($lines as $line) {
        // Parse MOH class lines
        if (preg_match('/^\s*(\S+)\s+(\S+)/', $line, $matches)) {
            if ($matches[1] !== 'Class:') {  // Skip header
                $classes[] = [
                    'name' => $matches[1],
                    'type' => $matches[2]
                ];
            }
        }
    }

    return $classes;
}
