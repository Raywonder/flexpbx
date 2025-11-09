<?php
/**
 * Process Google Voice SMS from Email Forwarding
 *
 * Google Voice can forward SMS to email. This script parses those emails
 * and stores them in a format the dashboard can read.
 *
 * Setup:
 * 1. In Google Voice settings, enable "Forward messages to email"
 * 2. Forward to: sms@flexpbx.devinecreations.net (or your local address)
 * 3. Set up email forwarding/pipe to this script
 * 4. Or use procmail/fetchmail to auto-process
 */

// This script can be called in two ways:
// 1. Via pipe from mail system: cat email.txt | php process-gv-sms-email.php
// 2. Via cron to check mailbox: php process-gv-sms-email.php --check-mail

$log_file = '/home/flexpbxuser/logs/gv_sms_email.log';

function log_message($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    @file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

// Parse email from stdin (piped from mail system)
function parse_email_from_stdin() {
    $raw_email = file_get_contents('php://stdin');
    return parse_email_content($raw_email);
}

// Parse email content
function parse_email_content($raw_email) {
    $data = [
        'from' => '',
        'to' => '',
        'subject' => '',
        'body' => '',
        'phone_number' => '',
        'message' => ''
    ];

    // Split headers and body
    $parts = preg_split("/\r?\n\r?\n/", $raw_email, 2);
    $headers = $parts[0] ?? '';
    $body = $parts[1] ?? '';

    // Parse headers
    $header_lines = explode("\n", $headers);
    foreach ($header_lines as $line) {
        if (preg_match('/^From:\s*(.+)$/i', $line, $matches)) {
            $data['from'] = trim($matches[1]);
        } elseif (preg_match('/^To:\s*(.+)$/i', $line, $matches)) {
            $data['to'] = trim($matches[1]);
        } elseif (preg_match('/^Subject:\s*(.+)$/i', $line, $matches)) {
            $data['subject'] = trim($matches[1]);
        }
    }

    $data['body'] = trim($body);

    // Google Voice email format:
    // Subject: New text message from (XXX) XXX-XXXX
    // Body: Contains the actual message

    // Extract phone number from subject
    if (preg_match('/\((\d{3})\)\s*(\d{3})-(\d{4})/', $data['subject'], $matches)) {
        $data['phone_number'] = $matches[1] . $matches[2] . $matches[3];
    }

    // Extract message from body (Google Voice includes links and footers)
    // Clean up the body to get just the message
    $lines = explode("\n", $data['body']);
    $message_lines = [];

    foreach ($lines as $line) {
        $line = trim($line);
        // Skip Google Voice footer lines
        if (empty($line) ||
            strpos($line, 'voice.google.com') !== false ||
            strpos($line, 'Reply to this') !== false ||
            strpos($line, 'Text STOP') !== false ||
            strpos($line, 'Google Voice') !== false) {
            continue;
        }
        $message_lines[] = $line;
    }

    $data['message'] = implode("\n", $message_lines);

    return $data;
}

// Save SMS to extension's inbox
function save_sms_to_inbox($phone_number, $message, $metadata = []) {
    // Find which extension this Google Voice number belongs to
    $users_dir = '/home/flexpbxuser/users';
    $target_extension = null;

    if (is_dir($users_dir)) {
        $user_files = glob($users_dir . '/user_*.json');

        foreach ($user_files as $file) {
            $user_data = json_decode(file_get_contents($file), true);

            // Check forwarded numbers
            if (isset($user_data['forwarded_numbers'])) {
                foreach ($user_data['forwarded_numbers'] as $forwarded) {
                    $forwarded_clean = preg_replace('/[^0-9]/', '', $forwarded['number']);
                    // If this is their Google Voice number
                    if ($forwarded_clean === '2813015784') {
                        $target_extension = $user_data['extension'];
                        break 2;
                    }
                }
            }
        }
    }

    if (!$target_extension) {
        log_message("Could not find extension for Google Voice SMS");
        return false;
    }

    // Save to SMS directory
    $sms_dir = '/home/flexpbxuser/sms_messages';
    if (!is_dir($sms_dir)) {
        mkdir($sms_dir, 0750, true);
        chown($sms_dir, 'flexpbxuser');
        chgrp($sms_dir, 'nobody');
    }

    $extension_sms_file = $sms_dir . '/extension_' . $target_extension . '.json';

    $messages = [];
    if (file_exists($extension_sms_file)) {
        $messages = json_decode(file_get_contents($extension_sms_file), true) ?? [];
    }

    $sms_data = [
        'from' => $phone_number,
        'to' => '2813015784', // Google Voice number
        'body' => $message,
        'source' => 'google_voice',
        'extension' => $target_extension,
        'received_at' => date('Y-m-d H:i:s'),
        'read' => false,
        'metadata' => $metadata
    ];

    $messages[] = $sms_data;
    file_put_contents($extension_sms_file, json_encode($messages, JSON_PRETTY_PRINT));
    chmod($extension_sms_file, 0640);

    log_message("Saved SMS from $phone_number to extension $target_extension");

    // Send email notification to user
    $user_file = $users_dir . '/user_' . $target_extension . '.json';
    if (file_exists($user_file)) {
        $user_data = json_decode(file_get_contents($user_file), true);
        $user_email = $user_data['email'] ?? null;

        if ($user_email && filter_var($user_email, FILTER_VALIDATE_EMAIL)) {
            $formatted_phone = format_phone($phone_number);
            $subject = 'New SMS: ' . $formatted_phone;
            $email_body = "You received a new text message:\n\n";
            $email_body .= "From: " . $formatted_phone . "\n";
            $email_body .= "To: (281) 301-5784 (Google Voice)\n";
            $email_body .= "Message: " . $message . "\n\n";
            $email_body .= "---\n";
            $email_body .= "Login to view: https://flexpbx.devinecreations.net/user-portal/sms-inbox.php\n";

            $headers = "From: FlexPBX SMS <noreply@devinecreations.net>\r\n";
            @mail($user_email, $subject, $email_body, $headers);
        }
    }

    return true;
}

function format_phone($number) {
    $clean = preg_replace('/[^0-9]/', '', $number);
    if (strlen($clean) === 10) {
        return '(' . substr($clean, 0, 3) . ') ' . substr($clean, 3, 3) . '-' . substr($clean, 6, 4);
    }
    return $number;
}

// Main execution
if (php_sapi_name() === 'cli') {
    // Called from command line
    if (in_array('--check-mail', $argv ?? [])) {
        // Future: Check mailbox via IMAP
        log_message("Mailbox check requested (not yet implemented)");
    } else {
        // Read from stdin (piped email)
        log_message("Processing email from stdin");
        $email_data = parse_email_from_stdin();

        if (!empty($email_data['phone_number']) && !empty($email_data['message'])) {
            log_message("Parsed SMS from {$email_data['phone_number']}");
            save_sms_to_inbox(
                $email_data['phone_number'],
                $email_data['message'],
                [
                    'subject' => $email_data['subject'],
                    'from_email' => $email_data['from']
                ]
            );
        } else {
            log_message("Could not parse SMS from email");
        }
    }
}
