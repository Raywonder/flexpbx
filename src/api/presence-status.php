<?php
/**
 * FlexPBX User Presence & Status API
 * Manage online/offline status with custom voice prompts
 *
 * Features:
 * - User presence tracking (online, offline, away, busy, DND)
 * - Device-specific status (per device or all devices)
 * - Custom voice prompts for status changes
 * - Login/logout announcements
 * - Global and per-user prompt management
 * - Browser logout detection
 * - Multi-device management
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$config_file = '/home/flexpbxuser/public_html/config/presence-status.json';
$prompts_dir = '/var/lib/asterisk/sounds/custom/presence/';

$path = $_GET['path'] ?? '';

// Route requests
switch ($path) {
    // User Status
    case 'set_status':
        setUserStatus();
        break;
    case 'get_status':
        getUserStatus();
        break;
    case 'get_all_statuses':
        getAllStatuses();
        break;
    case 'set_device_status':
        setDeviceStatus();
        break;
    case 'get_user_devices':
        getUserDevices();
        break;

    // Presence Events
    case 'user_login':
        userLogin();
        break;
    case 'user_logout':
        userLogout();
        break;
    case 'browser_logout':
        browserLogout();
        break;
    case 'logout_device':
        logoutDevice();
        break;
    case 'logout_all_devices':
        logoutAllDevices();
        break;

    // Voice Prompts
    case 'get_prompt_types':
        getPromptTypes();
        break;
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
    case 'get_user_prompts':
        getUserPrompts();
        break;
    case 'get_global_prompts':
        getGlobalPrompts();
        break;

    // Announcements
    case 'play_status_announcement':
        playStatusAnnouncement();
        break;
    case 'notify_status_change':
        notifyStatusChange();
        break;

    // Configuration
    case 'get_config':
        getConfig();
        break;
    case 'save_config':
        saveConfig();
        break;
    case 'get_settings':
        getSettings();
        break;
    case 'update_settings':
        updateSettings();
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid path']);
        break;
}

// ==================== USER STATUS ====================

/**
 * Set user status (online, offline, away, busy, DND)
 */
function setUserStatus() {
    $extension = $_POST['extension'] ?? '';
    $status = $_POST['status'] ?? 'offline';
    $message = $_POST['message'] ?? '';
    $play_announcement = filter_var($_POST['play_announcement'] ?? 'true', FILTER_VALIDATE_BOOLEAN);

    if (empty($extension)) {
        echo json_encode(['success' => false, 'error' => 'Extension required']);
        return;
    }

    $valid_statuses = ['online', 'offline', 'away', 'busy', 'dnd'];
    if (!in_array($status, $valid_statuses)) {
        echo json_encode(['success' => false, 'error' => 'Invalid status']);
        return;
    }

    $config = loadConfig();

    // Update user status
    if (!isset($config['users'][$extension])) {
        $config['users'][$extension] = [];
    }

    $old_status = $config['users'][$extension]['status'] ?? 'offline';

    $config['users'][$extension]['status'] = $status;
    $config['users'][$extension]['status_message'] = $message;
    $config['users'][$extension]['last_update'] = date('c');
    $config['users'][$extension]['updated_by'] = $_SERVER['REMOTE_ADDR'];

    // Track status history
    if (!isset($config['users'][$extension]['history'])) {
        $config['users'][$extension]['history'] = [];
    }

    $config['users'][$extension]['history'][] = [
        'from' => $old_status,
        'to' => $status,
        'timestamp' => date('c'),
        'message' => $message
    ];

    saveConfigData($config);

    // Play announcement if requested
    if ($play_announcement && $status !== $old_status) {
        playStatusAnnouncement($extension, $status);
    }

    echo json_encode([
        'success' => true,
        'extension' => $extension,
        'status' => $status,
        'old_status' => $old_status,
        'message' => $message,
        'announcement_played' => $play_announcement
    ]);
}

/**
 * Get user status
 */
function getUserStatus() {
    $extension = $_GET['extension'] ?? '';

    if (empty($extension)) {
        echo json_encode(['success' => false, 'error' => 'Extension required']);
        return;
    }

    $config = loadConfig();
    $user_data = $config['users'][$extension] ?? [];

    $status = $user_data['status'] ?? 'offline';
    $devices = $user_data['devices'] ?? [];

    // Check if any device is online
    $any_online = false;
    foreach ($devices as $device) {
        if ($device['status'] === 'online') {
            $any_online = true;
            break;
        }
    }

    echo json_encode([
        'success' => true,
        'extension' => $extension,
        'status' => $status,
        'status_message' => $user_data['status_message'] ?? '',
        'last_update' => $user_data['last_update'] ?? null,
        'devices' => $devices,
        'any_device_online' => $any_online,
        'total_devices' => count($devices)
    ]);
}

/**
 * Get all user statuses
 */
function getAllStatuses() {
    $config = loadConfig();

    $statuses = [];
    foreach ($config['users'] as $extension => $user_data) {
        $statuses[] = [
            'extension' => $extension,
            'status' => $user_data['status'] ?? 'offline',
            'status_message' => $user_data['status_message'] ?? '',
            'last_update' => $user_data['last_update'] ?? null,
            'device_count' => count($user_data['devices'] ?? [])
        ];
    }

    echo json_encode([
        'success' => true,
        'users' => $statuses,
        'total_users' => count($statuses)
    ]);
}

/**
 * Set device-specific status
 */
function setDeviceStatus() {
    $extension = $_POST['extension'] ?? '';
    $device_id = $_POST['device_id'] ?? '';
    $status = $_POST['status'] ?? 'offline';
    $device_info = $_POST['device_info'] ?? '';

    if (empty($extension) || empty($device_id)) {
        echo json_encode(['success' => false, 'error' => 'Extension and device_id required']);
        return;
    }

    $config = loadConfig();

    if (!isset($config['users'][$extension])) {
        $config['users'][$extension] = ['devices' => []];
    }

    if (!isset($config['users'][$extension]['devices'])) {
        $config['users'][$extension]['devices'] = [];
    }

    $config['users'][$extension]['devices'][$device_id] = [
        'status' => $status,
        'device_info' => $device_info,
        'last_update' => date('c'),
        'ip_address' => $_SERVER['REMOTE_ADDR']
    ];

    // Update overall user status based on devices
    $any_online = false;
    foreach ($config['users'][$extension]['devices'] as $device) {
        if ($device['status'] === 'online') {
            $any_online = true;
            break;
        }
    }

    $config['users'][$extension]['status'] = $any_online ? 'online' : 'offline';

    saveConfigData($config);

    echo json_encode([
        'success' => true,
        'extension' => $extension,
        'device_id' => $device_id,
        'status' => $status,
        'overall_status' => $config['users'][$extension]['status']
    ]);
}

/**
 * Get user's devices
 */
function getUserDevices() {
    $extension = $_GET['extension'] ?? '';

    if (empty($extension)) {
        echo json_encode(['success' => false, 'error' => 'Extension required']);
        return;
    }

    $config = loadConfig();
    $devices = $config['users'][$extension]['devices'] ?? [];

    echo json_encode([
        'success' => true,
        'extension' => $extension,
        'devices' => $devices,
        'total_devices' => count($devices)
    ]);
}

// ==================== PRESENCE EVENTS ====================

/**
 * Handle user login
 */
function userLogin() {
    $extension = $_POST['extension'] ?? '';
    $device_id = $_POST['device_id'] ?? uniqid('device_');
    $device_info = $_POST['device_info'] ?? '';
    $play_announcement = filter_var($_POST['play_announcement'] ?? 'true', FILTER_VALIDATE_BOOLEAN);

    if (empty($extension)) {
        echo json_encode(['success' => false, 'error' => 'Extension required']);
        return;
    }

    $config = loadConfig();

    if (!isset($config['users'][$extension])) {
        $config['users'][$extension] = [
            'devices' => [],
            'history' => [],
            'login_count' => 0
        ];
    }

    // Add/update device
    $config['users'][$extension]['devices'][$device_id] = [
        'status' => 'online',
        'device_info' => $device_info,
        'logged_in_at' => date('c'),
        'last_activity' => date('c'),
        'ip_address' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
    ];

    // Update overall status
    $config['users'][$extension]['status'] = 'online';
    $config['users'][$extension]['login_count']++;
    $config['users'][$extension]['last_login'] = date('c');

    // Add to history
    $config['users'][$extension]['history'][] = [
        'event' => 'login',
        'device_id' => $device_id,
        'timestamp' => date('c'),
        'ip_address' => $_SERVER['REMOTE_ADDR']
    ];

    saveConfigData($config);

    // Play login announcement
    if ($play_announcement) {
        playLoginAnnouncement($extension, $device_id);
    }

    // Send login notification if enabled
    sendLoginLogoutNotification($extension, 'login', $device_id);

    echo json_encode([
        'success' => true,
        'extension' => $extension,
        'device_id' => $device_id,
        'status' => 'online',
        'announcement_played' => $play_announcement,
        'message' => "User {$extension} logged in on device {$device_id}"
    ]);
}

/**
 * Handle user logout
 */
function userLogout() {
    $extension = $_POST['extension'] ?? '';
    $device_id = $_POST['device_id'] ?? '';
    $logout_all = filter_var($_POST['logout_all'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
    $play_announcement = filter_var($_POST['play_announcement'] ?? 'true', FILTER_VALIDATE_BOOLEAN);

    if (empty($extension)) {
        echo json_encode(['success' => false, 'error' => 'Extension required']);
        return;
    }

    $config = loadConfig();

    if (!isset($config['users'][$extension])) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        return;
    }

    if ($logout_all) {
        // Logout all devices
        $device_count = count($config['users'][$extension]['devices'] ?? []);
        $config['users'][$extension]['devices'] = [];
        $config['users'][$extension]['status'] = 'offline';

        $config['users'][$extension]['history'][] = [
            'event' => 'logout_all',
            'device_count' => $device_count,
            'timestamp' => date('c')
        ];

        $message = "Logged out from all {$device_count} devices";
    } else if (!empty($device_id)) {
        // Logout specific device
        if (isset($config['users'][$extension]['devices'][$device_id])) {
            unset($config['users'][$extension]['devices'][$device_id]);

            $config['users'][$extension]['history'][] = [
                'event' => 'logout',
                'device_id' => $device_id,
                'timestamp' => date('c')
            ];
        }

        // Update overall status
        $any_online = false;
        foreach ($config['users'][$extension]['devices'] ?? [] as $device) {
            if ($device['status'] === 'online') {
                $any_online = true;
                break;
            }
        }

        $config['users'][$extension]['status'] = $any_online ? 'online' : 'offline';
        $message = "Logged out from device {$device_id}";
    } else {
        echo json_encode(['success' => false, 'error' => 'device_id required unless logout_all is true']);
        return;
    }

    $config['users'][$extension]['last_logout'] = date('c');
    saveConfigData($config);

    // Play logout announcement
    if ($play_announcement) {
        playLogoutAnnouncement($extension, $device_id, $logout_all);
    }

    // Send logout notification if enabled
    sendLoginLogoutNotification($extension, 'logout', $device_id, $logout_all);

    echo json_encode([
        'success' => true,
        'extension' => $extension,
        'device_id' => $device_id,
        'logout_all' => $logout_all,
        'status' => $config['users'][$extension]['status'],
        'announcement_played' => $play_announcement,
        'message' => $message
    ]);
}

/**
 * Handle browser logout detection
 */
function browserLogout() {
    $extension = $_POST['extension'] ?? '';
    $device_id = $_POST['device_id'] ?? '';

    if (empty($extension) || empty($device_id)) {
        echo json_encode(['success' => false, 'error' => 'Extension and device_id required']);
        return;
    }

    $config = loadConfig();

    if (isset($config['users'][$extension]['devices'][$device_id])) {
        $config['users'][$extension]['devices'][$device_id]['status'] = 'offline';
        $config['users'][$extension]['devices'][$device_id]['browser_closed_at'] = date('c');

        $config['users'][$extension]['history'][] = [
            'event' => 'browser_logout',
            'device_id' => $device_id,
            'timestamp' => date('c'),
            'detection' => 'beforeunload'
        ];

        // Check if any other devices are online
        $any_online = false;
        foreach ($config['users'][$extension]['devices'] as $device) {
            if ($device['status'] === 'online') {
                $any_online = true;
                break;
            }
        }

        $config['users'][$extension]['status'] = $any_online ? 'online' : 'offline';

        saveConfigData($config);

        // Play browser logout announcement
        playBrowserLogoutAnnouncement($extension, $device_id);

        // Send logout notification if enabled
        sendLoginLogoutNotification($extension, 'logout', $device_id, false);
    }

    echo json_encode([
        'success' => true,
        'extension' => $extension,
        'device_id' => $device_id,
        'event' => 'browser_logout',
        'overall_status' => $config['users'][$extension]['status'] ?? 'offline'
    ]);
}

/**
 * Logout specific device
 */
function logoutDevice() {
    $_POST['logout_all'] = 'false';
    userLogout();
}

/**
 * Logout all devices for user
 */
function logoutAllDevices() {
    $_POST['logout_all'] = 'true';
    userLogout();
}

// ==================== VOICE PROMPTS ====================

/**
 * Get available prompt types
 */
function getPromptTypes() {
    $types = [
        'login' => 'User logged in announcement',
        'logout' => 'User logged out announcement',
        'browser_logout' => 'Browser closed/logout announcement',
        'logout_all' => 'Logged out from all devices',
        'online' => 'User came online',
        'offline' => 'User went offline',
        'away' => 'User is away',
        'busy' => 'User is busy',
        'dnd' => 'User enabled Do Not Disturb',
        'device_login' => 'Device-specific login',
        'device_logout' => 'Device-specific logout'
    ];

    echo json_encode([
        'success' => true,
        'prompt_types' => $types
    ]);
}

/**
 * List all custom prompts
 */
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
        'count' => count($prompts),
        'directory' => $prompts_dir
    ]);
}

/**
 * Upload custom prompt
 */
function uploadPrompt() {
    global $prompts_dir;

    if (!isset($_FILES['file'])) {
        echo json_encode(['success' => false, 'error' => 'No file uploaded']);
        return;
    }

    if (!is_dir($prompts_dir)) {
        mkdir($prompts_dir, 0755, true);
    }

    $file = $_FILES['file'];
    $prompt_type = $_POST['prompt_type'] ?? 'custom';
    $scope = $_POST['scope'] ?? 'global'; // global or user-specific

    $filename = basename($file['name']);
    $target = $prompts_dir . $filename;

    if (move_uploaded_file($file['tmp_name'], $target)) {
        chmod($target, 0644);
        chown($target, 'asterisk');

        echo json_encode([
            'success' => true,
            'filename' => $filename,
            'path' => $target,
            'prompt_type' => $prompt_type,
            'scope' => $scope,
            'message' => 'Prompt uploaded successfully'
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Upload failed']);
    }
}

/**
 * Assign prompt to user or globally
 */
function assignPrompt() {
    $extension = $_POST['extension'] ?? 'global';
    $prompt_type = $_POST['prompt_type'] ?? '';
    $prompt_file = $_POST['prompt_file'] ?? '';

    if (empty($prompt_type)) {
        echo json_encode(['success' => false, 'error' => 'Prompt type required']);
        return;
    }

    $config = loadConfig();

    if ($extension === 'global') {
        // Assign globally
        if (!isset($config['global_prompts'])) {
            $config['global_prompts'] = [];
        }
        $config['global_prompts'][$prompt_type] = $prompt_file;
    } else {
        // Assign to specific user
        if (!isset($config['users'][$extension]['prompts'])) {
            $config['users'][$extension]['prompts'] = [];
        }
        $config['users'][$extension]['prompts'][$prompt_type] = $prompt_file;
    }

    saveConfigData($config);

    echo json_encode([
        'success' => true,
        'extension' => $extension,
        'prompt_type' => $prompt_type,
        'prompt_file' => $prompt_file,
        'scope' => $extension === 'global' ? 'global' : 'user'
    ]);
}

/**
 * Delete prompt file
 */
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

/**
 * Get user-specific prompts
 */
function getUserPrompts() {
    $extension = $_GET['extension'] ?? '';

    if (empty($extension)) {
        echo json_encode(['success' => false, 'error' => 'Extension required']);
        return;
    }

    $config = loadConfig();
    $user_prompts = $config['users'][$extension]['prompts'] ?? [];
    $global_prompts = $config['global_prompts'] ?? [];

    echo json_encode([
        'success' => true,
        'extension' => $extension,
        'user_prompts' => $user_prompts,
        'global_prompts' => $global_prompts,
        'combined' => array_merge($global_prompts, $user_prompts) // User prompts override global
    ]);
}

/**
 * Get global prompts
 */
function getGlobalPrompts() {
    $config = loadConfig();
    $global_prompts = $config['global_prompts'] ?? [];

    echo json_encode([
        'success' => true,
        'global_prompts' => $global_prompts
    ]);
}

// ==================== ANNOUNCEMENTS ====================

/**
 * Play status change announcement
 */
function playStatusAnnouncement($extension, $status) {
    $config = loadConfig();

    // Get prompt file (user-specific or global)
    $prompt_file = $config['users'][$extension]['prompts'][$status]
        ?? $config['global_prompts'][$status]
        ?? null;

    if ($prompt_file) {
        // Play via Asterisk to all devices
        $devices = getDeviceChannels($extension);

        foreach ($devices as $channel) {
            $cmd = "sudo asterisk -rx \"channel originate {$channel} application Playback custom/presence/{$prompt_file}\"";
            shell_exec($cmd . ' 2>&1');
        }

        return true;
    }

    return false;
}

/**
 * Play login announcement
 */
function playLoginAnnouncement($extension, $device_id) {
    playStatusAnnouncement($extension, 'login');
}

/**
 * Play logout announcement
 */
function playLogoutAnnouncement($extension, $device_id, $logout_all) {
    $type = $logout_all ? 'logout_all' : 'logout';
    playStatusAnnouncement($extension, $type);
}

/**
 * Play browser logout announcement
 */
function playBrowserLogoutAnnouncement($extension, $device_id) {
    playStatusAnnouncement($extension, 'browser_logout');
}

/**
 * Notify other users of status change
 */
function notifyStatusChange() {
    $extension = $_POST['extension'] ?? '';
    $status = $_POST['status'] ?? '';
    $notify_extensions = json_decode($_POST['notify_extensions'] ?? '[]', true);

    if (empty($extension)) {
        echo json_encode(['success' => false, 'error' => 'Extension required']);
        return;
    }

    $notified = [];
    foreach ($notify_extensions as $target_ext) {
        $devices = getDeviceChannels($target_ext);

        foreach ($devices as $channel) {
            // Play notification to target extension
            $cmd = "sudo asterisk -rx \"channel originate {$channel} application Playback custom/presence/status-change\"";
            shell_exec($cmd . ' 2>&1');
            $notified[] = $target_ext;
        }
    }

    echo json_encode([
        'success' => true,
        'extension' => $extension,
        'status' => $status,
        'notified' => array_unique($notified),
        'count' => count(array_unique($notified))
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

function getSettings() {
    $config = loadConfig();
    $settings = $config['settings'] ?? [
        'enable_announcements' => true,
        'enable_login_prompts' => true,
        'enable_logout_prompts' => true,
        'enable_status_change_prompts' => true,
        'auto_logout_inactive' => true,
        'inactive_timeout' => 1800, // 30 minutes
        'track_browser_logout' => true
    ];

    echo json_encode(['success' => true, 'settings' => $settings]);
}

function updateSettings() {
    $settings = json_decode($_POST['settings'] ?? '{}', true);

    if (empty($settings)) {
        echo json_encode(['success' => false, 'error' => 'Settings required']);
        return;
    }

    $config = loadConfig();
    $config['settings'] = array_merge($config['settings'] ?? [], $settings);
    saveConfigData($config);

    echo json_encode(['success' => true, 'settings' => $config['settings']]);
}

// ==================== HELPERS ====================

function loadConfig() {
    global $config_file;

    if (!file_exists($config_file)) {
        return [
            'users' => [],
            'global_prompts' => [],
            'settings' => [
                'enable_announcements' => true,
                'enable_login_prompts' => true,
                'enable_logout_prompts' => true
            ]
        ];
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

function getDeviceChannels($extension) {
    $output = shell_exec("sudo asterisk -rx 'pjsip show endpoint {$extension}' 2>&1");

    // Parse active channels for the extension
    $channels = [];

    if (preg_match_all('/Contact:\s+([^\s]+)\s+([^\s]+)/', $output, $matches)) {
        foreach ($matches[1] as $contact) {
            if (strpos($contact, 'sip:') === 0) {
                $channels[] = "PJSIP/{$extension}";
                break;
            }
        }
    }

    return $channels;
}

/**
 * Send login/logout notification if user has enabled it
 */
function sendLoginLogoutNotification($extension, $event_type, $device_id = '', $logout_all = false) {
    $users_dir = '/home/flexpbxuser/users';
    $admins_dir = '/home/flexpbxuser/admins';

    // Try to load user account
    $user_file = $users_dir . '/user_' . $extension . '.json';
    $account_data = null;
    $account_type = 'user';

    if (file_exists($user_file)) {
        $account_data = json_decode(file_get_contents($user_file), true);
    } else {
        // Try admin account
        $admin_file = $admins_dir . '/admin_' . $extension . '.json';
        if (file_exists($admin_file)) {
            $account_data = json_decode(file_get_contents($admin_file), true);
            $account_type = 'admin';
        }
    }

    if (!$account_data) {
        return; // No account found
    }

    // Check if login/logout notifications are enabled
    $notify_enabled = false;
    if ($event_type === 'login') {
        $notify_enabled = $account_data['notify_login'] ?? false;
    } else {
        $notify_enabled = $account_data['notify_logout'] ?? false;
    }

    if (!$notify_enabled) {
        return; // Notifications not enabled
    }

    // Get user email
    $email = $account_data['email'] ?? null;
    if (!$email) {
        return; // No email configured
    }

    // Prepare notification message
    $device_info = $device_id ? " on device {$device_id}" : '';
    $logout_info = $logout_all ? ' from all devices' : $device_info;

    if ($event_type === 'login') {
        $subject = "Login Alert - Extension {$extension}";
        $message = "User with extension {$extension} logged in{$device_info}.\n\n";
        $message .= "Time: " . date('Y-m-d H:i:s') . "\n";
        $message .= "IP Address: " . ($_SERVER['REMOTE_ADDR'] ?? 'Unknown') . "\n";
        $message .= "User Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown') . "\n";
    } else {
        $subject = "Logout Alert - Extension {$extension}";
        $message = "User with extension {$extension} logged out{$logout_info}.\n\n";
        $message .= "Time: " . date('Y-m-d H:i:s') . "\n";
    }

    $message .= "\nThis is an automated notification from FlexPBX.\n";
    $message .= "To disable these notifications, visit your notification settings.";

    // Send email notification if enabled
    if ($account_data['email_notifications_enabled'] ?? true) {
        $headers = "From: FlexPBX Notifications <noreply@flexpbx.devinecreations.net>\r\n";
        $headers .= "Reply-To: noreply@flexpbx.devinecreations.net\r\n";
        $headers .= "X-Mailer: FlexPBX Presence System\r\n";

        @mail($email, $subject, $message, $headers);
    }

    // TODO: Send push notification if enabled
    // This would require integration with the push notification system
    // and would send a browser notification to all subscribed devices

    return true;
}
