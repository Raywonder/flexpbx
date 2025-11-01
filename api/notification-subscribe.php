<?php
/**
 * FlexPBX - Push Notification Subscription API
 * Manages push notification subscriptions for users and admins
 *
 * @requires PHP 8.0+
 * @recommended PHP 8.1 or 8.2
 */

// Check PHP version (minimum 8.0)
if (version_compare(PHP_VERSION, '8.0.0', '<')) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'PHP 8.0 or higher required',
        'current_version' => PHP_VERSION,
        'minimum_version' => '8.0.0',
        'recommended_versions' => ['8.1', '8.2']
    ]);
    exit;
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

session_start();

$users_dir = '/home/flexpbxuser/users';
$admins_dir = '/home/flexpbxuser/admins';
$subscriptions_dir = '/home/flexpbxuser/push_subscriptions';

// Ensure subscriptions directory exists
if (!file_exists($subscriptions_dir)) {
    mkdir($subscriptions_dir, 0750, true);
}

// Get request data
$input = file_get_contents('php://input');
$data = json_decode($input, true) ?: $_POST;

$action = $_GET['action'] ?? $data['action'] ?? 'subscribe';
$account_type = $_GET['account_type'] ?? $data['account_type'] ?? 'user';
$identifier = $_GET['identifier'] ?? $data['identifier'] ?? $_SESSION['user_extension'] ?? $_SESSION['admin_username'] ?? '';

// Subscribe to push notifications
if ($action === 'subscribe' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $subscription = $data['subscription'] ?? null;

    if (!$subscription) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Subscription data required']);
        exit;
    }

    if (!$identifier) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'User identifier required']);
        exit;
    }

    // Save subscription
    $sub_data = [
        'account_type' => $account_type,
        'identifier' => $identifier,
        'subscription' => $subscription,
        'created' => time(),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ];

    $sub_file = $subscriptions_dir . '/' . $account_type . '_' . $identifier . '_' . md5(json_encode($subscription)) . '.json';
    file_put_contents($sub_file, json_encode($sub_data, JSON_PRETTY_PRINT));
    chmod($sub_file, 0640);

    // Update user/admin preferences
    if ($account_type === 'admin') {
        $account_file = $admins_dir . '/admin_' . $identifier . '.json';
    } else {
        $account_file = $users_dir . '/user_' . $identifier . '.json';
    }

    if (file_exists($account_file)) {
        $account_data = json_decode(file_get_contents($account_file), true);
        $account_data['push_notifications_enabled'] = true;
        $account_data['push_subscription_date'] = date('Y-m-d H:i:s');
        file_put_contents($account_file, json_encode($account_data, JSON_PRETTY_PRINT));
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Push notifications enabled',
        'subscription_id' => basename($sub_file, '.json')
    ]);
    exit;
}

// Unsubscribe from push notifications
if ($action === 'unsubscribe' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $subscription_id = $data['subscription_id'] ?? null;

    if ($subscription_id) {
        $sub_file = $subscriptions_dir . '/' . $subscription_id . '.json';
        if (file_exists($sub_file)) {
            unlink($sub_file);
        }
    } else {
        // Remove all subscriptions for this user
        $pattern = $subscriptions_dir . '/' . $account_type . '_' . $identifier . '_*.json';
        $files = glob($pattern);
        foreach ($files as $file) {
            unlink($file);
        }
    }

    // Update user/admin preferences
    if ($account_type === 'admin') {
        $account_file = $admins_dir . '/admin_' . $identifier . '.json';
    } else {
        $account_file = $users_dir . '/user_' . $identifier . '.json';
    }

    if (file_exists($account_file)) {
        $account_data = json_decode(file_get_contents($account_file), true);
        $account_data['push_notifications_enabled'] = false;
        $account_data['push_unsubscribe_date'] = date('Y-m-d H:i:s');
        file_put_contents($account_file, json_encode($account_data, JSON_PRETTY_PRINT));
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Push notifications disabled'
    ]);
    exit;
}

// Get notification preferences
if ($action === 'get_preferences' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($account_type === 'admin') {
        $account_file = $admins_dir . '/admin_' . $identifier . '.json';
    } else {
        $account_file = $users_dir . '/user_' . $identifier . '.json';
    }

    if (!file_exists($account_file)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Account not found']);
        exit;
    }

    $account_data = json_decode(file_get_contents($account_file), true);

    $preferences = [
        'push_notifications_enabled' => $account_data['push_notifications_enabled'] ?? false,
        'email_notifications_enabled' => $account_data['email_notifications_enabled'] ?? true,
        'notify_voicemail' => $account_data['notify_voicemail'] ?? true,
        'notify_missed_calls' => $account_data['notify_missed_calls'] ?? true,
        'notify_sip_status' => $account_data['notify_sip_status'] ?? true,
        'notify_system_alerts' => $account_data['notify_system_alerts'] ?? ($account_type === 'admin'),
        'notify_login' => $account_data['notify_login'] ?? false,
        'notify_logout' => $account_data['notify_logout'] ?? false,
        'message_sounds_enabled' => $account_data['message_sounds_enabled'] ?? true
    ];

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'preferences' => $preferences
    ]);
    exit;
}

// Update notification preferences
if ($action === 'update_preferences' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($account_type === 'admin') {
        $account_file = $admins_dir . '/admin_' . $identifier . '.json';
    } else {
        $account_file = $users_dir . '/user_' . $identifier . '.json';
    }

    if (!file_exists($account_file)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Account not found']);
        exit;
    }

    $account_data = json_decode(file_get_contents($account_file), true);

    // Update preferences
    if (isset($data['push_notifications_enabled'])) {
        $account_data['push_notifications_enabled'] = (bool)$data['push_notifications_enabled'];
    }
    if (isset($data['email_notifications_enabled'])) {
        $account_data['email_notifications_enabled'] = (bool)$data['email_notifications_enabled'];
    }
    if (isset($data['notify_voicemail'])) {
        $account_data['notify_voicemail'] = (bool)$data['notify_voicemail'];
    }
    if (isset($data['notify_missed_calls'])) {
        $account_data['notify_missed_calls'] = (bool)$data['notify_missed_calls'];
    }
    if (isset($data['notify_sip_status'])) {
        $account_data['notify_sip_status'] = (bool)$data['notify_sip_status'];
    }
    if (isset($data['notify_system_alerts'])) {
        $account_data['notify_system_alerts'] = (bool)$data['notify_system_alerts'];
    }
    if (isset($data['notify_login'])) {
        $account_data['notify_login'] = (bool)$data['notify_login'];
    }
    if (isset($data['notify_logout'])) {
        $account_data['notify_logout'] = (bool)$data['notify_logout'];
    }
    if (isset($data['message_sounds_enabled'])) {
        $account_data['message_sounds_enabled'] = (bool)$data['message_sounds_enabled'];
    }

    $account_data['notification_preferences_updated'] = date('Y-m-d H:i:s');

    file_put_contents($account_file, json_encode($account_data, JSON_PRETTY_PRINT));

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Notification preferences updated',
        'preferences' => [
            'push_notifications_enabled' => $account_data['push_notifications_enabled'] ?? false,
            'email_notifications_enabled' => $account_data['email_notifications_enabled'] ?? true,
            'notify_voicemail' => $account_data['notify_voicemail'] ?? true,
            'notify_missed_calls' => $account_data['notify_missed_calls'] ?? true,
            'notify_sip_status' => $account_data['notify_sip_status'] ?? true,
            'notify_system_alerts' => $account_data['notify_system_alerts'] ?? false,
            'notify_login' => $account_data['notify_login'] ?? false,
            'notify_logout' => $account_data['notify_logout'] ?? false,
            'message_sounds_enabled' => $account_data['message_sounds_enabled'] ?? true
        ]
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'error' => 'Invalid action']);
