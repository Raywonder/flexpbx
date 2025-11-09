<?php
/**
 * Twilio SMS Webhook
 * Receives SMS messages from Twilio and routes them to extensions
 */

// Log all incoming requests for debugging
$log_file = '/home/flexpbxuser/logs/twilio_sms.log';
$log_entry = date('Y-m-d H:i:s') . " - Incoming SMS\n";
$log_entry .= "POST Data: " . json_encode($_POST) . "\n";
$log_entry .= "Headers: " . json_encode(getallheaders()) . "\n\n";
@file_put_contents($log_file, $log_entry, FILE_APPEND);

// Twilio sends SMS data as POST parameters
$from = $_POST['From'] ?? '';        // Sender's phone number
$to = $_POST['To'] ?? '';            // Your Twilio number
$body = $_POST['Body'] ?? '';        // SMS message text
$sms_sid = $_POST['SmsSid'] ?? '';   // Unique message ID

// Clean phone numbers (remove +1, spaces, etc)
$from_clean = preg_replace('/[^0-9]/', '', $from);
$to_clean = preg_replace('/[^0-9]/', '', $to);

// Find which extension this number belongs to
$users_dir = '/home/flexpbxuser/users';
$target_extension = null;
$target_email = null;

if (is_dir($users_dir)) {
    $user_files = glob($users_dir . '/user_*.json');

    foreach ($user_files as $file) {
        $user_data = json_decode(file_get_contents($file), true);

        // Check if this extension has this number as a forwarded number
        if (isset($user_data['forwarded_numbers'])) {
            foreach ($user_data['forwarded_numbers'] as $forwarded) {
                $forwarded_clean = preg_replace('/[^0-9]/', '', $forwarded['number']);

                // If the SMS was sent TO one of their forwarded numbers
                if ($forwarded_clean === $to_clean) {
                    $target_extension = $user_data['extension'];
                    $target_email = $user_data['email'] ?? null;
                    break 2;
                }
            }
        }
    }
}

// Format the phone number for display
function format_phone($number) {
    $clean = preg_replace('/[^0-9]/', '', $number);
    if (strlen($clean) === 11 && $clean[0] === '1') {
        $clean = substr($clean, 1);
    }
    if (strlen($clean) === 10) {
        return '(' . substr($clean, 0, 3) . ') ' . substr($clean, 3, 3) . '-' . substr($clean, 6, 4);
    }
    return $number;
}

// Store SMS in database or file
$sms_dir = '/home/flexpbxuser/sms_messages';
if (!is_dir($sms_dir)) {
    mkdir($sms_dir, 0750, true);
}

$sms_data = [
    'sms_sid' => $sms_sid,
    'from' => $from,
    'to' => $to,
    'body' => $body,
    'extension' => $target_extension,
    'received_at' => date('Y-m-d H:i:s'),
    'read' => false
];

// Save to extension's SMS file
if ($target_extension) {
    $extension_sms_file = $sms_dir . '/extension_' . $target_extension . '.json';

    $messages = [];
    if (file_exists($extension_sms_file)) {
        $messages = json_decode(file_get_contents($extension_sms_file), true) ?? [];
    }

    $messages[] = $sms_data;
    file_put_contents($extension_sms_file, json_encode($messages, JSON_PRETTY_PRINT));
    chmod($extension_sms_file, 0640);

    // Send email notification
    if ($target_email && filter_var($target_email, FILTER_VALIDATE_EMAIL)) {
        $subject = 'New SMS: ' . format_phone($from);
        $message = "You received a new text message:\n\n";
        $message .= "From: " . format_phone($from) . "\n";
        $message .= "To: " . format_phone($to) . "\n";
        $message .= "Message: " . $body . "\n\n";
        $message .= "---\n";
        $message .= "Login to view and reply: https://flexpbx.devinecreations.net/user-portal/\n";

        $headers = "From: FlexPBX SMS <noreply@devinecreations.net>\r\n";
        $headers .= "Reply-To: support@devinecreations.net\r\n";

        @mail($target_email, $subject, $message, $headers);
    }

    // Send push notification if enabled
    // TODO: Implement push notification for SMS
}

// Respond to Twilio with TwiML (optional auto-reply)
header('Content-Type: text/xml');
echo '<?xml version="1.0" encoding="UTF-8"?>';
echo '<Response>';
// Optionally send an auto-reply:
// echo '<Message>Thank you for your message. We will respond shortly.</Message>';
echo '</Response>';
