#!/usr/bin/env php
<?php
/**
 * FlexPBX SMS Email Daemon
 * Continuously monitors SMS inbox and processes incoming messages
 *
 * This runs as a systemd service and:
 * - Polls IMAP inbox every 5 seconds
 * - Extracts phone numbers from carrier email addresses
 * - Routes messages to correct extensions
 * - Stores in database
 * - Auto-deletes processed emails
 * - Keeps history in database only
 */

// Prevent timeout
set_time_limit(0);
ini_set('max_execution_time', 0);

// Load database configuration from FlexPBX config
$config_file = __DIR__ . '/../api/config.php';
if (file_exists($config_file)) {
    $config = require $config_file;
    define('DB_HOST', $config['db_host']);
    define('DB_NAME', $config['db_name']);
    define('DB_USER', $config['db_user']);
    define('DB_PASS', $config['db_password']);
} else {
    // Fallback configuration
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'flexpbx');
    define('DB_USER', 'root');
    define('DB_PASS', '');
}

define('IMAP_SERVER', 'dvc.raywonderis.me');
define('IMAP_PORT', 993);
define('IMAP_USER', 'sms@raywonderis.me');
define('IMAP_PASS', 'FlexPBX_SMS_2025!');
define('IMAP_MAILBOX', 'INBOX');

define('POLL_INTERVAL', 5); // seconds
define('LOG_FILE', '/var/log/flexpbx-sms-daemon.log');

// Logging function
function log_message($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] [$level] $message\n";

    // Console output
    echo $log_entry;

    // File output
    file_put_contents(LOG_FILE, $log_entry, FILE_APPEND);
}

// Database connection
function get_db_connection() {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        return $pdo;
    } catch (PDOException $e) {
        log_message("Database connection failed: " . $e->getMessage(), 'ERROR');
        return null;
    }
}

// Connect to IMAP
function connect_imap() {
    $mailbox_string = '{' . IMAP_SERVER . ':' . IMAP_PORT . '/imap/ssl/novalidate-cert}' . IMAP_MAILBOX;

    $imap = @imap_open($mailbox_string, IMAP_USER, IMAP_PASS);

    if (!$imap) {
        log_message("IMAP connection failed: " . imap_last_error(), 'ERROR');
        return null;
    }

    return $imap;
}

// Extract phone number from email address
function extract_phone_number($email_address) {
    // Carrier emails format: 1234567890@carrier.com
    if (preg_match('/(\d{10,11})@/', $email_address, $matches)) {
        return $matches[1];
    }
    return null;
}

// Find extension for phone number
function find_extension_for_phone($pdo, $phone_number) {
    try {
        $stmt = $pdo->prepare("
            SELECT extension_number, phone_number, carrier
            FROM extension_phone_numbers
            WHERE phone_number = ? AND enabled = 1
            LIMIT 1
        ");
        $stmt->execute([$phone_number]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        log_message("Database query failed: " . $e->getMessage(), 'ERROR');
        return null;
    }
}

// Store incoming SMS
function store_incoming_sms($pdo, $extension_config, $from_number, $message_body, $email_id) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO sms_messages (
                extension_id,
                extension_number,
                phone_number,
                direction,
                message_body,
                from_number,
                to_number,
                carrier_gateway,
                status,
                received_at,
                email_message_id
            ) VALUES (
                0,
                ?,
                ?,
                'inbound',
                ?,
                ?,
                ?,
                ?,
                'received',
                NOW(),
                ?
            )
        ");

        $stmt->execute([
            $extension_config['extension_number'],
            $extension_config['phone_number'],
            $message_body,
            $from_number,
            $extension_config['phone_number'],
            $extension_config['carrier'],
            $email_id
        ]);

        log_message("Stored SMS: From $from_number to ext {$extension_config['extension_number']}", 'INFO');
        return true;

    } catch (PDOException $e) {
        log_message("Failed to store SMS: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

// Process single email
function process_email($imap, $pdo, $email_number) {
    // Get email header
    $header = imap_headerinfo($imap, $email_number);

    if (!$header) {
        log_message("Failed to get header for email #$email_number", 'ERROR');
        return false;
    }

    // Extract sender email
    $from_email = $header->from[0]->mailbox . '@' . $header->from[0]->host;

    // Extract phone number from sender
    $from_number = extract_phone_number($from_email);

    if (!$from_number) {
        log_message("Could not extract phone number from: $from_email", 'WARN');
        // Delete non-SMS email
        imap_delete($imap, $email_number);
        return false;
    }

    // Get email body
    $body = imap_body($imap, $email_number);

    // Clean up body - remove quoted-printable encoding
    $body = quoted_printable_decode($body);
    $body = trim($body);

    // Limit to 1000 characters (SMS can be concatenated)
    $body = substr($body, 0, 1000);

    if (empty($body)) {
        log_message("Empty message body from $from_number", 'WARN');
        imap_delete($imap, $email_number);
        return false;
    }

    log_message("Received SMS from $from_number: " . substr($body, 0, 50) . "...", 'INFO');

    // Find which extension this is for
    // The email was sent TO our SMS inbox, but we need to find who owns the conversation
    // Look for the most recent outbound message to this number
    try {
        $stmt = $pdo->prepare("
            SELECT DISTINCT extension_number, phone_number
            FROM sms_messages
            WHERE to_number = ? OR from_number = ?
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$from_number, $from_number]);
        $extension_config = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$extension_config) {
            // No existing conversation - try to match by phone number in extension_phone_numbers
            $stmt = $pdo->prepare("
                SELECT extension_number, phone_number, carrier
                FROM extension_phone_numbers
                WHERE enabled = 1
                LIMIT 1
            ");
            $stmt->execute();
            $extension_config = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$extension_config) {
                log_message("No extension found for SMS from $from_number", 'WARN');
                imap_delete($imap, $email_number);
                return false;
            }
        }

    } catch (PDOException $e) {
        log_message("Database error: " . $e->getMessage(), 'ERROR');
        return false;
    }

    // Store the SMS
    $message_id = $header->message_id ?? 'email-' . time() . '-' . $email_number;
    $success = store_incoming_sms($pdo, $extension_config, $from_number, $body, $message_id);

    if ($success) {
        // Delete email after successful processing
        imap_delete($imap, $email_number);
        log_message("Processed and deleted email #$email_number", 'INFO');
        return true;
    }

    return false;
}

// Main daemon loop
function run_daemon() {
    log_message("FlexPBX SMS Email Daemon starting...", 'INFO');
    log_message("IMAP Server: " . IMAP_SERVER . ":" . IMAP_PORT, 'INFO');
    log_message("Poll Interval: " . POLL_INTERVAL . " seconds", 'INFO');

    $pdo = get_db_connection();
    if (!$pdo) {
        log_message("Cannot start daemon - database connection failed", 'ERROR');
        exit(1);
    }

    $consecutive_errors = 0;
    $max_consecutive_errors = 10;

    while (true) {
        try {
            // Connect to IMAP
            $imap = connect_imap();

            if (!$imap) {
                $consecutive_errors++;
                log_message("IMAP connection failed (error count: $consecutive_errors)", 'ERROR');

                if ($consecutive_errors >= $max_consecutive_errors) {
                    log_message("Too many consecutive errors, exiting", 'ERROR');
                    exit(1);
                }

                sleep(POLL_INTERVAL * 2); // Wait longer on error
                continue;
            }

            // Reset error counter on successful connection
            $consecutive_errors = 0;

            // Check for new messages
            $message_count = imap_num_msg($imap);

            if ($message_count > 0) {
                log_message("Found $message_count message(s) in inbox", 'INFO');

                // Process each message
                for ($i = 1; $i <= $message_count; $i++) {
                    process_email($imap, $pdo, $i);
                }

                // Expunge deleted messages
                imap_expunge($imap);
                log_message("Inbox cleaned up", 'INFO');
            }

            // Close IMAP connection
            imap_close($imap);

            // Wait before next poll
            sleep(POLL_INTERVAL);

        } catch (Exception $e) {
            log_message("Unexpected error: " . $e->getMessage(), 'ERROR');
            $consecutive_errors++;

            if ($consecutive_errors >= $max_consecutive_errors) {
                log_message("Too many consecutive errors, exiting", 'ERROR');
                exit(1);
            }

            sleep(POLL_INTERVAL * 2);
        }
    }
}

// Signal handlers for graceful shutdown
function signal_handler($signal) {
    log_message("Received signal $signal, shutting down gracefully...", 'INFO');
    exit(0);
}

pcntl_signal(SIGTERM, 'signal_handler');
pcntl_signal(SIGINT, 'signal_handler');

// Start daemon
run_daemon();
