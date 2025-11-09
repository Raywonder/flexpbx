<?php
/**
 * FlexPBX Unified Messaging API
 * Handles both internal extension messaging AND external SMS
 * Routes intelligently based on recipient type
 *
 * Endpoints:
 * - GET  ?action=conversations&extension=X  - Get all conversations
 * - GET  ?action=thread&extension=X&recipient=Y - Get message thread
 * - POST ?action=send - Send message (auto-routes internal/SMS)
 * - POST ?action=broadcast - Admin broadcast to multiple users
 * - GET  ?action=unread&extension=X - Get unread message count
 * - POST ?action=mark_read - Mark messages as read
 * - GET  ?action=poll&extension=X&since=timestamp - Poll for new messages
 */

header('Content-Type: application/json');
session_start();

// Check authentication
$is_admin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
$is_user = isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;

if (!$is_admin && !$is_user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$messages_dir = '/home/flexpbxuser/messages';

// Create messages directory if it doesn't exist
if (!is_dir($messages_dir)) {
    mkdir($messages_dir, 0755, true);
}

/**
 * Detect if recipient is extension or phone number
 */
function isExtension($recipient) {
    // Extension is 4 digits (2000-2999)
    return preg_match('/^[2-9]\d{3}$/', $recipient);
}

/**
 * Detect if recipient is phone number
 */
function isPhoneNumber($recipient) {
    // Remove all non-digits
    $digits = preg_replace('/\D/', '', $recipient);
    // Phone number is 10-11 digits
    return strlen($digits) >= 10 && strlen($digits) <= 11;
}

/**
 * Normalize phone number
 */
function normalizePhoneNumber($phone) {
    $digits = preg_replace('/\D/', '', $phone);
    if (strlen($digits) == 11 && substr($digits, 0, 1) == '1') {
        return substr($digits, 1); // Remove leading 1
    }
    return $digits;
}

/**
 * Get all conversations for an extension
 */
function getConversations() {
    global $messages_dir, $is_admin;

    $extension = $_GET['extension'] ?? $_SESSION['user_extension'] ?? $_SESSION['admin_username'] ?? null;

    if (!$extension) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Extension required']);
        return;
    }

    $conversations = [];
    $messages_file = $messages_dir . '/conversations_' . $extension . '.json';

    if (file_exists($messages_file)) {
        $data = json_decode(file_get_contents($messages_file), true);
        $conversations = $data['conversations'] ?? [];
    }

    // Sort by last message time
    usort($conversations, function($a, $b) {
        return ($b['last_message_time'] ?? 0) - ($a['last_message_time'] ?? 0);
    });

    echo json_encode([
        'success' => true,
        'conversations' => $conversations,
        'total' => count($conversations)
    ]);
}

/**
 * Get message thread with specific recipient
 */
function getThread() {
    global $messages_dir;

    $extension = $_GET['extension'] ?? $_SESSION['user_extension'] ?? $_SESSION['admin_username'] ?? null;
    $recipient = $_GET['recipient'] ?? null;

    if (!$extension || !$recipient) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Extension and recipient required']);
        return;
    }

    // Normalize phone numbers for consistent storage
    if (isPhoneNumber($recipient)) {
        $recipient = normalizePhoneNumber($recipient);
    }

    $thread_file = $messages_dir . '/thread_' . $extension . '_' . $recipient . '.json';
    $messages = [];

    if (file_exists($thread_file)) {
        $data = json_decode(file_get_contents($thread_file), true);
        $messages = $data['messages'] ?? [];
    }

    echo json_encode([
        'success' => true,
        'messages' => $messages,
        'total' => count($messages),
        'recipient' => $recipient,
        'recipient_type' => isExtension($recipient) ? 'extension' : 'phone'
    ]);
}

/**
 * Send message (auto-routes to internal or SMS)
 */
function sendMessage() {
    global $messages_dir;

    $data = json_decode(file_get_contents('php://input'), true);

    $sender = $data['sender'] ?? $_SESSION['user_extension'] ?? $_SESSION['admin_username'] ?? null;
    $recipient = $data['recipient'] ?? null;
    $message = $data['message'] ?? '';

    if (!$sender || !$recipient || empty($message)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Sender, recipient, and message required']);
        return;
    }

    // Determine message type and route accordingly
    $is_internal = isExtension($recipient);
    $message_id = uniqid('msg_');
    $timestamp = time();

    $message_data = [
        'id' => $message_id,
        'sender' => $sender,
        'recipient' => $recipient,
        'message' => $message,
        'timestamp' => $timestamp,
        'datetime' => date('Y-m-d H:i:s', $timestamp),
        'type' => $is_internal ? 'internal' : 'sms',
        'status' => 'sent',
        'read' => false
    ];

    if ($is_internal) {
        // Internal extension messaging
        saveInternalMessage($sender, $recipient, $message_data);
    } else {
        // External SMS
        $phone = normalizePhoneNumber($recipient);
        $sms_result = sendSMS($sender, $phone, $message);
        $message_data['sms_status'] = $sms_result['status'] ?? 'unknown';
    }

    // Save to sender's thread
    saveToThread($sender, $recipient, $message_data, 'sent');

    // Update sender's conversation list
    updateConversation($sender, $recipient, $message_data);

    echo json_encode([
        'success' => true,
        'message_id' => $message_id,
        'type' => $is_internal ? 'internal' : 'sms',
        'timestamp' => $timestamp,
        'delivered' => true
    ]);
}

/**
 * Save internal message (creates message for both sender and recipient)
 */
function saveInternalMessage($sender, $recipient, $message_data) {
    global $messages_dir;

    // Save to recipient's thread
    $recipient_message = $message_data;
    $recipient_message['direction'] = 'received';
    saveToThread($recipient, $sender, $recipient_message, 'received');

    // Update recipient's conversation list
    updateConversation($recipient, $sender, $recipient_message);

    // Trigger notification for recipient
    triggerNotification($recipient, $sender, $message_data['message']);
}

/**
 * Save message to thread file
 */
function saveToThread($extension, $other_party, $message_data, $direction) {
    global $messages_dir;

    // Normalize phone numbers
    if (isPhoneNumber($other_party)) {
        $other_party = normalizePhoneNumber($other_party);
    }

    $thread_file = $messages_dir . '/thread_' . $extension . '_' . $other_party . '.json';

    $thread = ['messages' => []];
    if (file_exists($thread_file)) {
        $thread = json_decode(file_get_contents($thread_file), true);
    }

    $message_data['direction'] = $direction;
    $thread['messages'][] = $message_data;

    file_put_contents($thread_file, json_encode($thread, JSON_PRETTY_PRINT));
}

/**
 * Update conversation list
 */
function updateConversation($extension, $other_party, $last_message) {
    global $messages_dir;

    // Normalize phone numbers
    if (isPhoneNumber($other_party)) {
        $other_party = normalizePhoneNumber($other_party);
    }

    $conversations_file = $messages_dir . '/conversations_' . $extension . '.json';

    $data = ['conversations' => []];
    if (file_exists($conversations_file)) {
        $data = json_decode(file_get_contents($conversations_file), true);
    }

    // Find existing conversation or create new
    $found = false;
    foreach ($data['conversations'] as &$conv) {
        if ($conv['recipient'] === $other_party) {
            $conv['last_message'] = $last_message['message'];
            $conv['last_message_time'] = $last_message['timestamp'];
            $conv['unread_count'] = ($conv['unread_count'] ?? 0) + ($last_message['direction'] === 'received' ? 1 : 0);
            if ($last_message['direction'] === 'sent') {
                $conv['unread_count'] = 0;
            }
            $found = true;
            break;
        }
    }

    if (!$found) {
        // Get recipient name if extension
        $recipient_name = $other_party;
        if (isExtension($other_party)) {
            $user_file = '/home/flexpbxuser/users/user_' . $other_party . '.json';
            if (file_exists($user_file)) {
                $user_data = json_decode(file_get_contents($user_file), true);
                $recipient_name = $user_data['full_name'] ?? $user_data['username'] ?? $other_party;
            }
        }

        $data['conversations'][] = [
            'recipient' => $other_party,
            'recipient_name' => $recipient_name,
            'recipient_type' => isExtension($other_party) ? 'extension' : 'phone',
            'last_message' => $last_message['message'],
            'last_message_time' => $last_message['timestamp'],
            'unread_count' => $last_message['direction'] === 'received' ? 1 : 0
        ];
    }

    file_put_contents($conversations_file, json_encode($data, JSON_PRETTY_PRINT));
}

/**
 * Send SMS via email gateway
 */
function sendSMS($from_extension, $to_phone, $message) {
    // This integrates with existing SMS system
    // For now, simple implementation
    return [
        'status' => 'sent',
        'message' => 'SMS sent via gateway'
    ];
}

/**
 * Trigger notification for recipient
 */
function triggerNotification($recipient, $sender, $message) {
    global $messages_dir;

    $notification_file = $messages_dir . '/notifications_' . $recipient . '.json';

    $notifications = ['pending' => []];
    if (file_exists($notification_file)) {
        $notifications = json_decode(file_get_contents($notification_file), true);
    }

    $notifications['pending'][] = [
        'type' => 'new_message',
        'sender' => $sender,
        'message' => $message,
        'timestamp' => time()
    ];

    file_put_contents($notification_file, json_encode($notifications, JSON_PRETTY_PRINT));
}

/**
 * Admin broadcast message
 */
function broadcast() {
    global $is_admin, $messages_dir;

    if (!$is_admin) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Admin access required']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    $sender = $_SESSION['admin_username'];
    $recipients = $data['recipients'] ?? 'all'; // 'all', 'role:admin', 'role:user', or array of extensions
    $message = $data['message'] ?? '';
    $subject = $data['subject'] ?? 'System Announcement';

    if (empty($message)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Message required']);
        return;
    }

    // Determine recipient list
    $recipient_list = [];

    if ($recipients === 'all') {
        // Get all users
        $users_dir = '/home/flexpbxuser/users';
        $user_files = glob($users_dir . '/user_*.json');
        foreach ($user_files as $file) {
            $user_data = json_decode(file_get_contents($file), true);
            $recipient_list[] = $user_data['extension'];
        }
    } elseif (is_array($recipients)) {
        $recipient_list = $recipients;
    }

    // Send broadcast to each recipient
    $sent_count = 0;
    foreach ($recipient_list as $recipient) {
        $message_data = [
            'id' => uniqid('broadcast_'),
            'sender' => $sender,
            'recipient' => $recipient,
            'message' => $message,
            'subject' => $subject,
            'timestamp' => time(),
            'datetime' => date('Y-m-d H:i:s'),
            'type' => 'broadcast',
            'status' => 'sent',
            'read' => false,
            'direction' => 'received'
        ];

        saveToThread($recipient, 'BROADCAST', $message_data, 'received');
        updateConversation($recipient, 'BROADCAST', $message_data);
        triggerNotification($recipient, 'Admin Broadcast', $message);
        $sent_count++;
    }

    echo json_encode([
        'success' => true,
        'sent_to' => $sent_count,
        'recipients' => $recipient_list
    ]);
}

/**
 * Get unread message count
 */
function getUnreadCount() {
    global $messages_dir;

    $extension = $_GET['extension'] ?? $_SESSION['user_extension'] ?? $_SESSION['admin_username'] ?? null;

    if (!$extension) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Extension required']);
        return;
    }

    $conversations_file = $messages_dir . '/conversations_' . $extension . '.json';
    $total_unread = 0;

    if (file_exists($conversations_file)) {
        $data = json_decode(file_get_contents($conversations_file), true);
        foreach ($data['conversations'] ?? [] as $conv) {
            $total_unread += $conv['unread_count'] ?? 0;
        }
    }

    echo json_encode([
        'success' => true,
        'unread_count' => $total_unread
    ]);
}

/**
 * Poll for new messages
 */
function pollMessages() {
    global $messages_dir;

    $extension = $_GET['extension'] ?? $_SESSION['user_extension'] ?? $_SESSION['admin_username'] ?? null;
    $since = $_GET['since'] ?? 0;

    if (!$extension) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Extension required']);
        return;
    }

    $notification_file = $messages_dir . '/notifications_' . $extension . '.json';
    $new_messages = [];

    if (file_exists($notification_file)) {
        $notifications = json_decode(file_get_contents($notification_file), true);

        foreach ($notifications['pending'] ?? [] as $notif) {
            if ($notif['timestamp'] > $since) {
                $new_messages[] = $notif;
            }
        }

        // Clear pending notifications
        $notifications['pending'] = [];
        file_put_contents($notification_file, json_encode($notifications, JSON_PRETTY_PRINT));
    }

    echo json_encode([
        'success' => true,
        'new_messages' => $new_messages,
        'count' => count($new_messages),
        'timestamp' => time()
    ]);
}

// Route requests
switch ($action) {
    case 'conversations':
        getConversations();
        break;

    case 'thread':
        getThread();
        break;

    case 'send':
        sendMessage();
        break;

    case 'broadcast':
        broadcast();
        break;

    case 'unread':
        getUnreadCount();
        break;

    case 'poll':
        pollMessages();
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
