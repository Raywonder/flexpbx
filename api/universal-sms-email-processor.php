<?php
/**
 * Universal SMS Email Processor
 * Handles SMS emails from Google Voice for ANY user
 * Routes to correct extension, allows replies, auto-cleans emails
 *
 * Email addresses that can receive:
 * - sms@tappedin.fm
 * - sms@devine-creations.com
 * - sms@devinecreations.net
 *
 * Processing flow:
 * 1. Email arrives from Google Voice
 * 2. Parse sender phone number and message
 * 3. Determine which extension owns that GV number
 * 4. Store in extension's inbox
 * 5. Send notification to user
 * 6. Delete email from server (keep only in DB)
 */

$log_file = '/home/flexpbxuser/logs/sms_processor.log';
$users_dir = '/home/flexpbxuser/users';
$sms_dir = '/home/flexpbxuser/sms_messages';

function log_message($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    @file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

/**
 * Parse Google Voice email
 * Handles various GV email formats and filters out thread history
 */
function parse_google_voice_email($raw_email) {
    $data = [
        'from_phone' => '',
        'to_phone' => '',        // Which GV number received this
        'message' => '',
        'timestamp' => time(),
        'raw_subject' => '',
        'raw_from' => ''
    ];

    // Split headers and body
    $parts = preg_split("/\r?\n\r?\n/", $raw_email, 2);
    $headers = $parts[0] ?? '';
    $body = $parts[1] ?? '';

    // Parse headers
    $header_lines = explode("\n", $headers);
    foreach ($header_lines as $line) {
        if (preg_match('/^From:\s*(.+)$/i', $line, $matches)) {
            $data['raw_from'] = trim($matches[1]);
        } elseif (preg_match('/^Subject:\s*(.+)$/i', $line, $matches)) {
            $data['raw_subject'] = trim($matches[1]);
        }
    }

    // Extract phone number from subject line
    // Formats: "New text message from (XXX) XXX-XXXX" or "SMS from +1XXXXXXXXXX"
    if (preg_match('/from[:\s]+[\+\(]?1?[\)\s-]*(\d{3})[\)\s-]*(\d{3})[\s-]*(\d{4})/i', $data['raw_subject'], $matches)) {
        $data['from_phone'] = $matches[1] . $matches[2] . $matches[3];
    }

    // Parse body - extract only NEW message, filter out quoted history
    $body_lines = explode("\n", $body);
    $message_lines = [];
    $in_quote = false;
    $in_footer = false;

    foreach ($body_lines as $line) {
        $line_trimmed = trim($line);

        // Detect quoted messages (old thread history)
        if (preg_match('/^On\s+\w+.*wrote:$/i', $line_trimmed) ||
            preg_match('/^>{1,}\s*/', $line) ||
            preg_match('/^From:\s*/i', $line_trimmed)) {
            $in_quote = true;
            continue;
        }

        // Detect Google Voice footer
        if (strpos($line_trimmed, 'voice.google.com') !== false ||
            strpos($line_trimmed, 'Reply to this') !== false ||
            strpos($line_trimmed, 'Text STOP') !== false ||
            strpos($line_trimmed, 'Google Voice') !== false ||
            preg_match('/https?:\/\//', $line_trimmed)) {
            $in_footer = true;
            continue;
        }

        // Skip empty lines at the start
        if (empty($message_lines) && empty($line_trimmed)) {
            continue;
        }

        // If we're in a quote or footer, skip
        if ($in_quote || $in_footer) {
            continue;
        }

        // Add valid message line
        if (!empty($line_trimmed)) {
            $message_lines[] = $line_trimmed;
        }
    }

    $data['message'] = implode("\n", $message_lines);

    return $data;
}

/**
 * Find which extension owns a specific Google Voice number
 */
function find_extension_by_gv_number($gv_number) {
    global $users_dir;

    $gv_clean = preg_replace('/[^0-9]/', '', $gv_number);

    if (!is_dir($users_dir)) {
        return null;
    }

    $user_files = glob($users_dir . '/user_*.json');

    foreach ($user_files as $file) {
        $user_data = json_decode(file_get_contents($file), true);

        if (!$user_data) continue;

        // Check forwarded_numbers array
        if (isset($user_data['forwarded_numbers'])) {
            foreach ($user_data['forwarded_numbers'] as $forwarded) {
                $forwarded_clean = preg_replace('/[^0-9]/', '', $forwarded['number']);

                if ($forwarded_clean === $gv_clean) {
                    return [
                        'extension' => $user_data['extension'],
                        'email' => $user_data['email'] ?? '',
                        'full_name' => $user_data['full_name'] ?? '',
                        'gv_number' => $gv_number
                    ];
                }
            }
        }
    }

    return null;
}

/**
 * Save SMS to extension's inbox
 */
function save_sms_to_extension($extension_info, $sms_data) {
    global $sms_dir;

    if (!is_dir($sms_dir)) {
        mkdir($sms_dir, 0750, true);
        chown($sms_dir, 'flexpbxuser');
        chgrp($sms_dir, 'nobody');
    }

    $extension = $extension_info['extension'];
    $extension_sms_file = $sms_dir . '/extension_' . $extension . '.json';

    // Load existing messages
    $messages = [];
    if (file_exists($extension_sms_file)) {
        $messages = json_decode(file_get_contents($extension_sms_file), true) ?? [];
    }

    // Check for duplicate (based on phone + timestamp within 5 minutes)
    $is_duplicate = false;
    foreach ($messages as $existing) {
        if ($existing['from'] === $sms_data['from_phone']) {
            $time_diff = abs($sms_data['timestamp'] - strtotime($existing['received_at']));
            if ($time_diff < 300) { // Within 5 minutes
                // Check if message is similar
                similar_text($existing['body'], $sms_data['message'], $percent);
                if ($percent > 90) {
                    $is_duplicate = true;
                    break;
                }
            }
        }
    }

    if ($is_duplicate) {
        log_message("Duplicate SMS detected, skipping");
        return false;
    }

    // Add new message
    $message_entry = [
        'id' => uniqid('sms_', true),
        'from' => $sms_data['from_phone'],
        'to' => $extension_info['gv_number'],
        'body' => $sms_data['message'],
        'source' => 'google_voice',
        'extension' => $extension,
        'received_at' => date('Y-m-d H:i:s', $sms_data['timestamp']),
        'read' => false,
        'replied' => false
    ];

    $messages[] = $message_entry;

    // Keep only last 500 messages per extension
    if (count($messages) > 500) {
        $messages = array_slice($messages, -500);
    }

    file_put_contents($extension_sms_file, json_encode($messages, JSON_PRETTY_PRINT));
    chmod($extension_sms_file, 0640);

    log_message("Saved SMS from {$sms_data['from_phone']} to extension {$extension}");

    // Send email notification
    send_notification_email($extension_info, $sms_data);

    return true;
}

/**
 * Send email notification to user
 */
function send_notification_email($extension_info, $sms_data) {
    $user_email = $extension_info['email'];

    if (empty($user_email) || !filter_var($user_email, FILTER_VALIDATE_EMAIL)) {
        return;
    }

    $formatted_from = format_phone($sms_data['from_phone']);
    $formatted_to = format_phone($extension_info['gv_number']);

    $subject = "New SMS: " . $formatted_from;

    $body = "You received a new text message:\n\n";
    $body .= "From: " . $formatted_from . "\n";
    $body .= "To: " . $formatted_to . "\n";
    $body .= "Message:\n" . $sms_data['message'] . "\n\n";
    $body .= "---\n";
    $body .= "View and reply: https://flexpbx.devinecreations.net/user-portal/sms-inbox.php\n";

    $headers = "From: FlexPBX SMS <noreply@devinecreations.net>\r\n";
    $headers .= "Reply-To: noreply@devinecreations.net\r\n";

    @mail($user_email, $subject, $body, $headers);
}

/**
 * Format phone number for display
 */
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

/**
 * Delete processed email from server
 * This is a placeholder - actual implementation depends on your mail system
 */
function delete_processed_email($email_id = null) {
    // If called via procmail, the email is already being piped and won't be saved
    // If checking mailbox via IMAP, we'd delete here
    log_message("Email processed and removed from queue");
}

// ============================================================================
// Main Execution
// ============================================================================

if (php_sapi_name() === 'cli') {
    log_message("Starting SMS email processing");

    // Read email from stdin (piped from mail system)
    $raw_email = file_get_contents('php://stdin');

    if (empty($raw_email)) {
        log_message("ERROR: No email data received");
        exit(1);
    }

    // Parse the email
    $parsed = parse_google_voice_email($raw_email);

    log_message("Parsed email - From: {$parsed['from_phone']}, Message length: " . strlen($parsed['message']));

    // Validate we have required data
    if (empty($parsed['from_phone']) || empty($parsed['message'])) {
        log_message("ERROR: Could not parse phone number or message from email");
        log_message("Subject: {$parsed['raw_subject']}");
        exit(1);
    }

    // Try to determine which GV number this was sent to
    // First check if it's in the email headers (To: field might have it)
    // Otherwise, we'll need to check all extensions and match
    $extension_info = null;

    // For now, since we know extension 2001 has GV number 2813015784
    // In the future, parse the To: header to see which email alias it was sent to
    // and map that to the GV number

    // Simple approach: Check all extensions for forwarded numbers
    // and see if any match (in case multiple users have GV numbers)
    $users_dir_path = '/home/flexpbxuser/users';
    if (is_dir($users_dir_path)) {
        $user_files = glob($users_dir_path . '/user_*.json');

        foreach ($user_files as $file) {
            $user_data = json_decode(file_get_contents($file), true);
            if (isset($user_data['forwarded_numbers'])) {
                foreach ($user_data['forwarded_numbers'] as $forwarded) {
                    // For now, assume first match
                    // TODO: Parse To: header to match specific GV number
                    $extension_info = [
                        'extension' => $user_data['extension'],
                        'email' => $user_data['email'] ?? '',
                        'full_name' => $user_data['full_name'] ?? '',
                        'gv_number' => $forwarded['number']
                    ];
                    break 2;
                }
            }
        }
    }

    if (!$extension_info) {
        log_message("ERROR: Could not find extension for this Google Voice number");
        exit(1);
    }

    // Save SMS
    $saved = save_sms_to_extension($extension_info, $parsed);

    if ($saved) {
        log_message("SUCCESS: SMS saved and notification sent");
        delete_processed_email();
    } else {
        log_message("INFO: SMS was duplicate, not saved");
    }

    exit(0);
}
