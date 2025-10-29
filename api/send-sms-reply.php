<?php
/**
 * Send SMS Reply
 * Allows users to reply to SMS messages from dashboard
 *
 * Methods:
 * 1. Google Voice - Reply via email to Google Voice
 * 2. Twilio - Send via Twilio API
 */

session_start();
header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$extension = $_SESSION['user_extension'] ?? '';
$log_file = '/home/flexpbxuser/logs/sms_replies.log';

function log_message($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    @file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true);

$to_phone = $input['to'] ?? '';
$message = $input['message'] ?? '';
$reply_to_id = $input['reply_to_id'] ?? '';

// Validate input
if (empty($to_phone) || empty($message)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Phone number and message required']);
    exit;
}

// Clean phone number
$to_phone_clean = preg_replace('/[^0-9]/', '', $to_phone);

if (strlen($to_phone_clean) < 10) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid phone number']);
    exit;
}

// Get user's Google Voice number
$users_dir = '/home/flexpbxuser/users';
$user_file = $users_dir . '/user_' . $extension . '.json';

if (!file_exists($user_file)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'User not found']);
    exit;
}

$user_data = json_decode(file_get_contents($user_file), true);
$gv_number = null;

// Find Google Voice number from forwarded_numbers
if (isset($user_data['forwarded_numbers'])) {
    foreach ($user_data['forwarded_numbers'] as $forwarded) {
        if (strpos($forwarded['description'], 'Google Voice') !== false ||
            strlen(preg_replace('/[^0-9]/', '', $forwarded['number'])) === 10) {
            $gv_number = preg_replace('/[^0-9]/', '', $forwarded['number']);
            break;
        }
    }
}

if (!$gv_number) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No Google Voice number configured']);
    exit;
}

/**
 * Method 1: Send SMS via Google Voice Email Reply
 * Google Voice allows replying to SMS by replying to the notification email
 */
function send_via_google_voice_email($to_phone, $from_gv_number, $message, $user_email) {
    // Format: Send email TO the person's phone number @txt.voice.google.com
    // Google Voice will route it

    $to_email = $to_phone . '@txt.voice.google.com';
    $subject = 'SMS Reply';
    $body = $message;

    $headers = "From: " . $user_email . "\r\n";
    $headers .= "Reply-To: " . $user_email . "\r\n";

    $sent = @mail($to_email, $subject, $body, $headers);

    log_message("Attempted GV email reply to {$to_phone}: " . ($sent ? 'SUCCESS' : 'FAILED'));

    return $sent;
}

/**
 * Method 2: Send SMS via Twilio API
 */
function send_via_twilio($to_phone, $from_number, $message) {
    // Load Twilio credentials from config
    $config_file = '/home/flexpbxuser/twilio-config.json';

    if (!file_exists($config_file)) {
        return false;
    }

    $config = json_decode(file_get_contents($config_file), true);

    if (!isset($config['account_sid']) || !isset($config['auth_token']) || !isset($config['phone_number'])) {
        return false;
    }

    $account_sid = $config['account_sid'];
    $auth_token = $config['auth_token'];
    $twilio_number = $config['phone_number'];

    // Format phone numbers
    $to = '+1' . preg_replace('/[^0-9]/', '', $to_phone);
    $from = '+1' . preg_replace('/[^0-9]/', '', $twilio_number);

    // Twilio API endpoint
    $url = "https://api.twilio.com/2010-04-01/Accounts/{$account_sid}/Messages.json";

    $data = [
        'From' => $from,
        'To' => $to,
        'Body' => $message
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_USERPWD, "{$account_sid}:{$auth_token}");
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    log_message("Twilio API response: HTTP {$http_code} - {$response}");

    return ($http_code === 200 || $http_code === 201);
}

// Try sending via available methods
$sent = false;
$method_used = '';

// Try Twilio first (if configured)
if (file_exists('/home/flexpbxuser/twilio-config.json')) {
    $sent = send_via_twilio($to_phone_clean, $gv_number, $message);
    if ($sent) {
        $method_used = 'twilio';
    }
}

// Fallback to Google Voice email method
if (!$sent) {
    $user_email = $user_data['email'] ?? '';
    if (!empty($user_email)) {
        $sent = send_via_google_voice_email($to_phone_clean, $gv_number, $message, $user_email);
        if ($sent) {
            $method_used = 'google_voice_email';
        }
    }
}

if ($sent) {
    // Mark original message as replied
    if ($reply_to_id) {
        $sms_dir = '/home/flexpbxuser/sms_messages';
        $extension_sms_file = $sms_dir . '/extension_' . $extension . '.json';

        if (file_exists($extension_sms_file)) {
            $messages = json_decode(file_get_contents($extension_sms_file), true) ?? [];

            foreach ($messages as $index => $msg) {
                if (isset($msg['id']) && $msg['id'] === $reply_to_id) {
                    $messages[$index]['replied'] = true;
                    $messages[$index]['reply_sent_at'] = date('Y-m-d H:i:s');
                    break;
                }
            }

            file_put_contents($extension_sms_file, json_encode($messages, JSON_PRETTY_PRINT));
            chmod($extension_sms_file, 0640);
        }
    }

    // Store outbound message
    $sms_dir = '/home/flexpbxuser/sms_messages';
    $extension_sms_file = $sms_dir . '/extension_' . $extension . '.json';

    if (file_exists($extension_sms_file)) {
        $messages = json_decode(file_get_contents($extension_sms_file), true) ?? [];

        $outbound_message = [
            'id' => uniqid('sms_', true),
            'from' => $gv_number,
            'to' => $to_phone_clean,
            'body' => $message,
            'source' => $method_used,
            'extension' => $extension,
            'received_at' => date('Y-m-d H:i:s'),
            'direction' => 'outbound',
            'read' => true,
            'replied' => false
        ];

        $messages[] = $outbound_message;

        file_put_contents($extension_sms_file, json_encode($messages, JSON_PRETTY_PRINT));
        chmod($extension_sms_file, 0640);
    }

    log_message("SMS sent from extension {$extension} to {$to_phone_clean} via {$method_used}");

    echo json_encode([
        'success' => true,
        'message' => 'SMS sent successfully',
        'method' => $method_used
    ]);
} else {
    log_message("ERROR: Failed to send SMS from extension {$extension} to {$to_phone_clean}");

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to send SMS. Check configuration.'
    ]);
}
