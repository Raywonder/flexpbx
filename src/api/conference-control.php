<?php
/**
 * FlexPBX Comprehensive Conference Control API
 * Complete conference management system
 *
 * Features:
 * - Room management (create, delete, lock, unlock)
 * - Participant control (mute, unmute, kick, volume)
 * - Music control (start, stop, change class)
 * - Recording control (start, stop, pause)
 * - PIN management (set, remove, verify)
 * - Custom prompts (upload, assign, manage)
 * - Room settings (max participants, waiting room, etc.)
 * - Statistics and monitoring
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$config_file = '/home/flexpbxuser/public_html/config/conference-control.json';
$prompts_dir = '/var/lib/asterisk/sounds/custom/conference/';

$path = $_GET['path'] ?? '';

// Route requests
switch ($path) {
    // Room Management
    case 'list_rooms':
        listRooms();
        break;
    case 'get_room_info':
        getRoomInfo();
        break;
    case 'lock_room':
        lockRoom();
        break;
    case 'unlock_room':
        unlockRoom();
        break;
    case 'end_conference':
        endConference();
        break;

    // Participant Management
    case 'list_participants':
        listParticipants();
        break;
    case 'mute_participant':
        muteParticipant();
        break;
    case 'unmute_participant':
        unmuteParticipant();
        break;
    case 'kick_participant':
        kickParticipant();
        break;
    case 'kick_last':
        kickLast();
        break;
    case 'set_volume':
        setVolume();
        break;

    // Music Control
    case 'start_music':
        startMusic();
        break;
    case 'stop_music':
        stopMusic();
        break;
    case 'change_music':
        changeMusic();
        break;

    // Recording Control
    case 'start_recording':
        startRecording();
        break;
    case 'stop_recording':
        stopRecording();
        break;
    case 'pause_recording':
        pauseRecording();
        break;
    case 'list_recordings':
        listRecordings();
        break;

    // PIN Management
    case 'set_pin':
        setPin();
        break;
    case 'remove_pin':
        removePin();
        break;
    case 'verify_pin':
        verifyPin();
        break;

    // Room Settings
    case 'get_room_settings':
        getRoomSettings();
        break;
    case 'update_room_settings':
        updateRoomSettings();
        break;
    case 'set_max_participants':
        setMaxParticipants();
        break;
    case 'toggle_waiting_room':
        toggleWaitingRoom();
        break;
    case 'admit_from_waiting':
        admitFromWaiting();
        break;

    // Custom Prompts
    case 'list_prompts':
        listPrompts();
        break;
    case 'upload_prompt':
        uploadPrompt();
        break;
    case 'assign_prompt':
        assignPrompt();
        break;
    case 'delete_prompt':
        deletePrompt();
        break;
    case 'get_prompt_types':
        getPromptTypes();
        break;

    // Statistics
    case 'get_stats':
        getStats();
        break;
    case 'get_room_history':
        getRoomHistory();
        break;

    // Configuration
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

// ==================== ROOM MANAGEMENT ====================

function listRooms() {
    $output = shell_exec("sudo asterisk -rx 'confbridge list' 2>&1");
    $rooms = parseRoomList($output);

    $config = loadConfig();
    foreach ($rooms as &$room) {
        $room_id = $room['room'];
        $room['settings'] = $config['rooms'][$room_id] ?? [];
        $room['has_pin'] = !empty($config['rooms'][$room_id]['pin']);
        $room['max_participants'] = $config['rooms'][$room_id]['max_participants'] ?? 0;
    }

    echo json_encode([
        'success' => true,
        'rooms' => $rooms,
        'total' => count($rooms)
    ]);
}

function getRoomInfo() {
    $room = $_GET['room'] ?? '';
    if (empty($room)) {
        echo json_encode(['success' => false, 'error' => 'Room required']);
        return;
    }

    $output = shell_exec("sudo asterisk -rx 'confbridge list {$room}' 2>&1");
    $participants = parseParticipantList($output);

    $config = loadConfig();
    $settings = $config['rooms'][$room] ?? [];

    echo json_encode([
        'success' => true,
        'room' => $room,
        'participants' => $participants,
        'participant_count' => count($participants),
        'settings' => $settings,
        'has_pin' => !empty($settings['pin']),
        'locked' => $settings['locked'] ?? false,
        'recording' => $settings['recording'] ?? false,
        'music_playing' => $settings['music_playing'] ?? false
    ]);
}

function lockRoom() {
    $room = $_POST['room'] ?? '';
    if (empty($room)) {
        echo json_encode(['success' => false, 'error' => 'Room required']);
        return;
    }

    $output = shell_exec("sudo asterisk -rx 'confbridge lock {$room}' 2>&1");

    $config = loadConfig();
    $config['rooms'][$room]['locked'] = true;
    $config['rooms'][$room]['locked_at'] = date('c');
    saveConfigData($config);

    echo json_encode([
        'success' => true,
        'room' => $room,
        'action' => 'locked',
        'message' => "Room {$room} is now locked"
    ]);
}

function unlockRoom() {
    $room = $_POST['room'] ?? '';
    if (empty($room)) {
        echo json_encode(['success' => false, 'error' => 'Room required']);
        return;
    }

    $output = shell_exec("sudo asterisk -rx 'confbridge unlock {$room}' 2>&1");

    $config = loadConfig();
    $config['rooms'][$room]['locked'] = false;
    $config['rooms'][$room]['unlocked_at'] = date('c');
    saveConfigData($config);

    echo json_encode([
        'success' => true,
        'room' => $room,
        'action' => 'unlocked',
        'message' => "Room {$room} is now unlocked"
    ]);
}

function endConference() {
    $room = $_POST['room'] ?? '';
    if (empty($room)) {
        echo json_encode(['success' => false, 'error' => 'Room required']);
        return;
    }

    $output = shell_exec("sudo asterisk -rx 'confbridge kick {$room} all' 2>&1");

    $config = loadConfig();
    $config['rooms'][$room]['ended_at'] = date('c');
    $config['rooms'][$room]['locked'] = false;
    saveConfigData($config);

    echo json_encode([
        'success' => true,
        'room' => $room,
        'action' => 'ended',
        'message' => "All participants removed from room {$room}"
    ]);
}

// ==================== PARTICIPANT MANAGEMENT ====================

function listParticipants() {
    $room = $_GET['room'] ?? '';
    if (empty($room)) {
        echo json_encode(['success' => false, 'error' => 'Room required']);
        return;
    }

    $output = shell_exec("sudo asterisk -rx 'confbridge list {$room}' 2>&1");
    $participants = parseParticipantList($output);

    echo json_encode([
        'success' => true,
        'room' => $room,
        'participants' => $participants,
        'count' => count($participants)
    ]);
}

function muteParticipant() {
    $room = $_POST['room'] ?? '';
    $channel = $_POST['channel'] ?? '';

    if (empty($room) || empty($channel)) {
        echo json_encode(['success' => false, 'error' => 'Room and channel required']);
        return;
    }

    $output = shell_exec("sudo asterisk -rx 'confbridge mute {$room} {$channel}' 2>&1");

    echo json_encode([
        'success' => true,
        'room' => $room,
        'channel' => $channel,
        'action' => 'muted'
    ]);
}

function unmuteParticipant() {
    $room = $_POST['room'] ?? '';
    $channel = $_POST['channel'] ?? '';

    if (empty($room) || empty($channel)) {
        echo json_encode(['success' => false, 'error' => 'Room and channel required']);
        return;
    }

    $output = shell_exec("sudo asterisk -rx 'confbridge unmute {$room} {$channel}' 2>&1");

    echo json_encode([
        'success' => true,
        'room' => $room,
        'channel' => $channel,
        'action' => 'unmuted'
    ]);
}

function kickParticipant() {
    $room = $_POST['room'] ?? '';
    $channel = $_POST['channel'] ?? '';

    if (empty($room) || empty($channel)) {
        echo json_encode(['success' => false, 'error' => 'Room and channel required']);
        return;
    }

    $output = shell_exec("sudo asterisk -rx 'confbridge kick {$room} {$channel}' 2>&1");

    echo json_encode([
        'success' => true,
        'room' => $room,
        'channel' => $channel,
        'action' => 'kicked'
    ]);
}

function kickLast() {
    $room = $_POST['room'] ?? '';
    if (empty($room)) {
        echo json_encode(['success' => false, 'error' => 'Room required']);
        return;
    }

    $output = shell_exec("sudo asterisk -rx 'confbridge kick {$room} last' 2>&1");

    echo json_encode([
        'success' => true,
        'room' => $room,
        'action' => 'kicked_last'
    ]);
}

function setVolume() {
    $room = $_POST['room'] ?? '';
    $channel = $_POST['channel'] ?? '';
    $direction = $_POST['direction'] ?? 'listening'; // listening or talking
    $level = $_POST['level'] ?? 0;

    if (empty($room) || empty($channel)) {
        echo json_encode(['success' => false, 'error' => 'Room and channel required']);
        return;
    }

    $cmd = ($direction === 'talking') ? 'decrease_talking_volume' : 'decrease_listening_volume';
    if ($level > 0) {
        $cmd = ($direction === 'talking') ? 'increase_talking_volume' : 'increase_listening_volume';
    }

    $output = shell_exec("sudo asterisk -rx 'confbridge {$cmd} {$room} {$channel}' 2>&1");

    echo json_encode([
        'success' => true,
        'room' => $room,
        'channel' => $channel,
        'direction' => $direction,
        'level' => $level
    ]);
}

// ==================== MUSIC CONTROL ====================

function startMusic() {
    $room = $_POST['room'] ?? '';
    $class = $_POST['music_class'] ?? 'default';

    if (empty($room)) {
        echo json_encode(['success' => false, 'error' => 'Room required']);
        return;
    }

    $output = shell_exec("sudo asterisk -rx 'confbridge moh {$room} start {$class}' 2>&1");

    $config = loadConfig();
    $config['rooms'][$room]['music_playing'] = true;
    $config['rooms'][$room]['music_class'] = $class;
    saveConfigData($config);

    echo json_encode([
        'success' => true,
        'room' => $room,
        'action' => 'music_started',
        'music_class' => $class
    ]);
}

function stopMusic() {
    $room = $_POST['room'] ?? '';

    if (empty($room)) {
        echo json_encode(['success' => false, 'error' => 'Room required']);
        return;
    }

    $output = shell_exec("sudo asterisk -rx 'confbridge moh {$room} stop' 2>&1");

    $config = loadConfig();
    $config['rooms'][$room]['music_playing'] = false;
    saveConfigData($config);

    echo json_encode([
        'success' => true,
        'room' => $room,
        'action' => 'music_stopped'
    ]);
}

function changeMusic() {
    $room = $_POST['room'] ?? '';
    $class = $_POST['music_class'] ?? 'default';

    if (empty($room)) {
        echo json_encode(['success' => false, 'error' => 'Room required']);
        return;
    }

    // Stop current music
    shell_exec("sudo asterisk -rx 'confbridge moh {$room} stop' 2>&1");

    // Start new music
    $output = shell_exec("sudo asterisk -rx 'confbridge moh {$room} start {$class}' 2>&1");

    $config = loadConfig();
    $config['rooms'][$room]['music_class'] = $class;
    saveConfigData($config);

    echo json_encode([
        'success' => true,
        'room' => $room,
        'action' => 'music_changed',
        'music_class' => $class
    ]);
}

// ==================== RECORDING CONTROL ====================

function startRecording() {
    $room = $_POST['room'] ?? '';

    if (empty($room)) {
        echo json_encode(['success' => false, 'error' => 'Room required']);
        return;
    }

    $filename = "/var/spool/asterisk/monitor/conference-{$room}-" . date('Ymd-His') . ".wav";
    $output = shell_exec("sudo asterisk -rx 'confbridge record start {$room} {$filename}' 2>&1");

    $config = loadConfig();
    $config['rooms'][$room]['recording'] = true;
    $config['rooms'][$room]['recording_file'] = $filename;
    $config['rooms'][$room]['recording_started'] = date('c');
    saveConfigData($config);

    echo json_encode([
        'success' => true,
        'room' => $room,
        'action' => 'recording_started',
        'filename' => $filename
    ]);
}

function stopRecording() {
    $room = $_POST['room'] ?? '';

    if (empty($room)) {
        echo json_encode(['success' => false, 'error' => 'Room required']);
        return;
    }

    $output = shell_exec("sudo asterisk -rx 'confbridge record stop {$room}' 2>&1");

    $config = loadConfig();
    $config['rooms'][$room]['recording'] = false;
    $config['rooms'][$room]['recording_stopped'] = date('c');
    saveConfigData($config);

    echo json_encode([
        'success' => true,
        'room' => $room,
        'action' => 'recording_stopped'
    ]);
}

function pauseRecording() {
    $room = $_POST['room'] ?? '';

    if (empty($room)) {
        echo json_encode(['success' => false, 'error' => 'Room required']);
        return;
    }

    $output = shell_exec("sudo asterisk -rx 'confbridge record pause {$room}' 2>&1");

    echo json_encode([
        'success' => true,
        'room' => $room,
        'action' => 'recording_paused'
    ]);
}

function listRecordings() {
    $room = $_GET['room'] ?? '';

    $pattern = empty($room)
        ? "/var/spool/asterisk/monitor/conference-*"
        : "/var/spool/asterisk/monitor/conference-{$room}-*";

    $files = glob($pattern);

    $recordings = [];
    foreach ($files as $file) {
        $recordings[] = [
            'filename' => basename($file),
            'path' => $file,
            'size' => filesize($file),
            'modified' => date('Y-m-d H:i:s', filemtime($file))
        ];
    }

    echo json_encode([
        'success' => true,
        'recordings' => $recordings,
        'count' => count($recordings)
    ]);
}

// ==================== PIN MANAGEMENT ====================

function setPin() {
    $room = $_POST['room'] ?? '';
    $pin = $_POST['pin'] ?? '';

    if (empty($room) || empty($pin)) {
        echo json_encode(['success' => false, 'error' => 'Room and PIN required']);
        return;
    }

    $config = loadConfig();
    $config['rooms'][$room]['pin'] = password_hash($pin, PASSWORD_DEFAULT);
    $config['rooms'][$room]['pin_set_at'] = date('c');
    saveConfigData($config);

    echo json_encode([
        'success' => true,
        'room' => $room,
        'message' => 'PIN set successfully'
    ]);
}

function removePin() {
    $room = $_POST['room'] ?? '';

    if (empty($room)) {
        echo json_encode(['success' => false, 'error' => 'Room required']);
        return;
    }

    $config = loadConfig();
    unset($config['rooms'][$room]['pin']);
    $config['rooms'][$room]['pin_removed_at'] = date('c');
    saveConfigData($config);

    echo json_encode([
        'success' => true,
        'room' => $room,
        'message' => 'PIN removed successfully'
    ]);
}

function verifyPin() {
    $room = $_POST['room'] ?? '';
    $pin = $_POST['pin'] ?? '';

    if (empty($room) || empty($pin)) {
        echo json_encode(['success' => false, 'error' => 'Room and PIN required']);
        return;
    }

    $config = loadConfig();
    $stored_pin = $config['rooms'][$room]['pin'] ?? '';

    if (empty($stored_pin)) {
        echo json_encode(['success' => false, 'error' => 'No PIN set for this room']);
        return;
    }

    $valid = password_verify($pin, $stored_pin);

    echo json_encode([
        'success' => true,
        'valid' => $valid
    ]);
}

// ==================== ROOM SETTINGS ====================

function getRoomSettings() {
    $room = $_GET['room'] ?? '';

    if (empty($room)) {
        echo json_encode(['success' => false, 'error' => 'Room required']);
        return;
    }

    $config = loadConfig();
    $settings = $config['rooms'][$room] ?? [];

    echo json_encode([
        'success' => true,
        'room' => $room,
        'settings' => $settings
    ]);
}

function updateRoomSettings() {
    $room = $_POST['room'] ?? '';
    $settings = json_decode($_POST['settings'] ?? '{}', true);

    if (empty($room)) {
        echo json_encode(['success' => false, 'error' => 'Room required']);
        return;
    }

    $config = loadConfig();
    if (!isset($config['rooms'][$room])) {
        $config['rooms'][$room] = [];
    }

    $config['rooms'][$room] = array_merge($config['rooms'][$room], $settings);
    $config['rooms'][$room]['updated_at'] = date('c');
    saveConfigData($config);

    echo json_encode([
        'success' => true,
        'room' => $room,
        'settings' => $config['rooms'][$room]
    ]);
}

function setMaxParticipants() {
    $room = $_POST['room'] ?? '';
    $max = intval($_POST['max_participants'] ?? 0);

    if (empty($room)) {
        echo json_encode(['success' => false, 'error' => 'Room required']);
        return;
    }

    $config = loadConfig();
    $config['rooms'][$room]['max_participants'] = $max;
    saveConfigData($config);

    echo json_encode([
        'success' => true,
        'room' => $room,
        'max_participants' => $max
    ]);
}

function toggleWaitingRoom() {
    $room = $_POST['room'] ?? '';
    $enabled = filter_var($_POST['enabled'] ?? 'false', FILTER_VALIDATE_BOOLEAN);

    if (empty($room)) {
        echo json_encode(['success' => false, 'error' => 'Room required']);
        return;
    }

    $config = loadConfig();
    $config['rooms'][$room]['waiting_room_enabled'] = $enabled;
    saveConfigData($config);

    echo json_encode([
        'success' => true,
        'room' => $room,
        'waiting_room_enabled' => $enabled
    ]);
}

function admitFromWaiting() {
    $room = $_POST['room'] ?? '';
    $channel = $_POST['channel'] ?? '';

    if (empty($room) || empty($channel)) {
        echo json_encode(['success' => false, 'error' => 'Room and channel required']);
        return;
    }

    // This would require custom Asterisk dialplan logic
    echo json_encode([
        'success' => true,
        'room' => $room,
        'channel' => $channel,
        'action' => 'admitted',
        'note' => 'Requires custom dialplan implementation'
    ]);
}

// ==================== CUSTOM PROMPTS ====================

function listPrompts() {
    global $prompts_dir;

    if (!is_dir($prompts_dir)) {
        mkdir($prompts_dir, 0755, true);
    }

    $files = glob($prompts_dir . '*.{wav,gsm,ulaw}', GLOB_BRACE);
    $prompts = [];

    foreach ($files as $file) {
        $prompts[] = [
            'filename' => basename($file),
            'path' => $file,
            'size' => filesize($file),
            'modified' => date('Y-m-d H:i:s', filemtime($file))
        ];
    }

    echo json_encode([
        'success' => true,
        'prompts' => $prompts,
        'count' => count($prompts)
    ]);
}

function uploadPrompt() {
    global $prompts_dir;

    if (!isset($_FILES['file'])) {
        echo json_encode(['success' => false, 'error' => 'No file uploaded']);
        return;
    }

    $file = $_FILES['file'];
    $prompt_type = $_POST['prompt_type'] ?? 'custom';
    $room = $_POST['room'] ?? '';

    $filename = basename($file['name']);
    $target = $prompts_dir . $filename;

    if (move_uploaded_file($file['tmp_name'], $target)) {
        chmod($target, 0644);

        echo json_encode([
            'success' => true,
            'filename' => $filename,
            'path' => $target,
            'prompt_type' => $prompt_type
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Upload failed']);
    }
}

function assignPrompt() {
    $room = $_POST['room'] ?? '';
    $prompt_type = $_POST['prompt_type'] ?? '';
    $prompt_file = $_POST['prompt_file'] ?? '';

    if (empty($room) || empty($prompt_type)) {
        echo json_encode(['success' => false, 'error' => 'Room and prompt type required']);
        return;
    }

    $config = loadConfig();
    if (!isset($config['rooms'][$room]['prompts'])) {
        $config['rooms'][$room]['prompts'] = [];
    }

    $config['rooms'][$room]['prompts'][$prompt_type] = $prompt_file;
    saveConfigData($config);

    echo json_encode([
        'success' => true,
        'room' => $room,
        'prompt_type' => $prompt_type,
        'prompt_file' => $prompt_file
    ]);
}

function deletePrompt() {
    global $prompts_dir;

    $filename = $_POST['filename'] ?? '';

    if (empty($filename)) {
        echo json_encode(['success' => false, 'error' => 'Filename required']);
        return;
    }

    $file = $prompts_dir . basename($filename);

    if (file_exists($file)) {
        unlink($file);
        echo json_encode(['success' => true, 'message' => 'Prompt deleted']);
    } else {
        echo json_encode(['success' => false, 'error' => 'File not found']);
    }
}

function getPromptTypes() {
    $types = [
        'welcome' => 'Welcome message when joining',
        'alone' => 'Message when participant is alone',
        'first' => 'Message for first participant',
        'join' => 'Someone joined announcement',
        'leave' => 'Someone left announcement',
        'locked' => 'Conference is locked message',
        'full' => 'Conference is full message',
        'invalid_pin' => 'Invalid PIN message',
        'muted' => 'You are muted announcement',
        'unmuted' => 'You are unmuted announcement',
        'recording' => 'Recording started announcement',
        'countdown' => 'Countdown before ending',
        'goodbye' => 'Goodbye message'
    ];

    echo json_encode([
        'success' => true,
        'prompt_types' => $types
    ]);
}

// ==================== STATISTICS ====================

function getStats() {
    $config = loadConfig();

    $total_rooms = count($config['rooms']);
    $active_rooms = 0;
    $total_participants = 0;
    $locked_rooms = 0;
    $recording_rooms = 0;

    foreach ($config['rooms'] as $room => $settings) {
        // Get active participants
        $output = shell_exec("sudo asterisk -rx 'confbridge list {$room}' 2>&1");
        $participants = parseParticipantList($output);

        if (count($participants) > 0) {
            $active_rooms++;
            $total_participants += count($participants);
        }

        if (!empty($settings['locked'])) $locked_rooms++;
        if (!empty($settings['recording'])) $recording_rooms++;
    }

    echo json_encode([
        'success' => true,
        'stats' => [
            'total_rooms' => $total_rooms,
            'active_rooms' => $active_rooms,
            'total_participants' => $total_participants,
            'locked_rooms' => $locked_rooms,
            'recording_rooms' => $recording_rooms
        ]
    ]);
}

function getRoomHistory() {
    $room = $_GET['room'] ?? '';

    if (empty($room)) {
        echo json_encode(['success' => false, 'error' => 'Room required']);
        return;
    }

    $config = loadConfig();
    $history = $config['rooms'][$room]['history'] ?? [];

    echo json_encode([
        'success' => true,
        'room' => $room,
        'history' => $history
    ]);
}

// ==================== CONFIGURATION ====================

function getConfig() {
    $config = loadConfig();
    echo json_encode(['success' => true, 'config' => $config]);
}

function saveConfig() {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data) {
        echo json_encode(['success' => false, 'error' => 'Invalid data']);
        return;
    }

    saveConfigData($data);
    echo json_encode(['success' => true]);
}

// ==================== HELPERS ====================

function loadConfig() {
    global $config_file;

    if (!file_exists($config_file)) {
        return ['rooms' => [], 'global_settings' => []];
    }

    return json_decode(file_get_contents($config_file), true) ?: [];
}

function saveConfigData($config) {
    global $config_file;

    $dir = dirname($config_file);
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }

    file_put_contents($config_file, json_encode($config, JSON_PRETTY_PRINT));
    chmod($config_file, 0600);
}

function parseRoomList($output) {
    $rooms = [];
    $lines = explode("\n", $output);

    foreach ($lines as $line) {
        if (preg_match('/^\s*(\S+)\s+(\d+)\s+(\d+)\s+(\S+)/', $line, $matches)) {
            $rooms[] = [
                'room' => $matches[1],
                'users' => (int)$matches[2],
                'marked' => (int)$matches[3],
                'locked' => ($matches[4] === 'Yes')
            ];
        }
    }

    return $rooms;
}

function parseParticipantList($output) {
    $participants = [];
    $lines = explode("\n", $output);

    foreach ($lines as $line) {
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
