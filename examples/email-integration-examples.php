<?php
/**
 * FlexPBX Email System Integration Examples
 *
 * This file contains practical examples of integrating the email system
 * into existing FlexPBX features
 *
 * @author FlexPBX
 * @version 1.0.0
 * @created 2025-10-17
 */

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/EmailService.php';

// Initialize email service
$emailService = new EmailService();

// ============================================================================
// EXAMPLE 1: New User Account Creation
// ============================================================================

function sendWelcomeEmail($user_data) {
    global $emailService;

    $emailService->sendEmail(
        $user_data['email'],
        '',  // Subject from template
        '',  // Body from template
        'welcome_email',
        [
            'username' => $user_data['name'],
            'extension' => $user_data['extension'],
            'password' => $user_data['temp_password'],
            'custom_message' => 'Please login and change your password immediately for security.'
        ],
        null,  // Use default reply-to
        true   // Queue for sending
    );
}

// Usage:
// $new_user = [
//     'email' => 'newuser@example.com',
//     'name' => 'John Doe',
//     'extension' => '2001',
//     'temp_password' => 'TempPass123'
// ];
// sendWelcomeEmail($new_user);

// ============================================================================
// EXAMPLE 2: Voicemail Notification
// ============================================================================

function sendVoicemailNotification($user_id, $voicemail_data) {
    global $emailService, $db;

    // Check if user wants voicemail notifications
    if (!$emailService->shouldNotify($user_id, 'voicemail')) {
        return false; // User has disabled voicemail notifications
    }

    // Get user info
    $stmt = $db->prepare("SELECT email, username, extension FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !$user['email']) {
        return false;
    }

    // Send notification
    return $emailService->sendEmail(
        $user['email'],
        '',
        '',
        'voicemail_notification',
        [
            'username' => $user['username'],
            'caller_id' => $voicemail_data['caller_id'],
            'date_time' => date('Y-m-d H:i A', strtotime($voicemail_data['timestamp'])),
            'duration' => $voicemail_data['duration'],
            'mailbox' => $user['extension'],
            'custom_message' => 'You can listen to this message by calling *97 or logging into the user portal.'
        ],
        null,
        true,  // Queue
        3      // High priority
    );
}

// Usage:
// $voicemail = [
//     'caller_id' => '555-1234',
//     'timestamp' => '2025-10-17 10:30:00',
//     'duration' => '45 seconds'
// ];
// sendVoicemailNotification($user_id, $voicemail);

// ============================================================================
// EXAMPLE 3: Missed Call Notification
// ============================================================================

function sendMissedCallNotification($user_id, $call_data) {
    global $emailService, $db;

    // Check user preferences
    if (!$emailService->shouldNotify($user_id, 'missed_call')) {
        return false;
    }

    // Get user info
    $stmt = $db->prepare("SELECT email, username, extension FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !$user['email']) {
        return false;
    }

    // Check if digest is enabled
    $prefs = $emailService->getUserPreferences($user_id);
    if ($prefs['digest_enabled']) {
        // Add to digest queue instead of sending immediately
        return $emailService->addToDigest($user_id, 'missed_call', [
            'caller_id' => $call_data['caller_id'],
            'date_time' => $call_data['timestamp'],
            'extension' => $user['extension']
        ]);
    }

    // Send immediate notification
    return $emailService->sendEmail(
        $user['email'],
        '',
        '',
        'missed_call',
        [
            'username' => $user['username'],
            'caller_id' => $call_data['caller_id'],
            'date_time' => date('Y-m-d H:i A', strtotime($call_data['timestamp'])),
            'extension' => $user['extension'],
            'custom_message' => 'Check your call history in the user portal for more details.'
        ]
    );
}

// Usage:
// $missed_call = [
//     'caller_id' => '555-5678',
//     'timestamp' => '2025-10-17 14:15:00'
// ];
// sendMissedCallNotification($user_id, $missed_call);

// ============================================================================
// EXAMPLE 4: Security Alert (Failed Login)
// ============================================================================

function sendSecurityAlert($user_id, $alert_data) {
    global $emailService, $db;

    // Get user info
    $stmt = $db->prepare("SELECT email, username FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !$user['email']) {
        return false;
    }

    // Security alerts are always sent (ignore user preferences)
    return $emailService->sendEmail(
        $user['email'],
        '',
        '',
        'security_alert',
        [
            'username' => $user['username'],
            'alert_type' => $alert_data['type'],
            'date_time' => date('Y-m-d H:i A'),
            'ip_address' => $alert_data['ip_address'],
            'details' => $alert_data['details'],
            'custom_message' => 'If this was not you, please contact support immediately and change your password.'
        ],
        'webmaster@raywonderis.me',  // Override reply-to for security
        false,  // Send immediately (don't queue)
        1       // Highest priority
    );
}

// Usage:
// $alert = [
//     'type' => 'Failed Login Attempt',
//     'ip_address' => '192.168.1.100',
//     'details' => '5 consecutive failed login attempts within 10 minutes'
// ];
// sendSecurityAlert($user_id, $alert);

// ============================================================================
// EXAMPLE 5: Extension Changed Notification
// ============================================================================

function sendExtensionChangedNotification($user_id, $old_extension, $new_extension, $changed_by) {
    global $emailService, $db;

    if (!$emailService->shouldNotify($user_id, 'extension_change')) {
        return false;
    }

    $stmt = $db->prepare("SELECT email, username FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !$user['email']) {
        return false;
    }

    return $emailService->sendEmail(
        $user['email'],
        '',
        '',
        'extension_changed',
        [
            'username' => $user['username'],
            'old_extension' => $old_extension,
            'new_extension' => $new_extension,
            'changed_by' => $changed_by,
            'date_time' => date('Y-m-d H:i A'),
            'custom_message' => 'Please update your phone configuration with the new extension number.'
        ]
    );
}

// Usage:
// sendExtensionChangedNotification($user_id, '2001', '2005', 'Admin');

// ============================================================================
// EXAMPLE 6: Password Reset Request
// ============================================================================

function sendPasswordResetEmail($user_email, $reset_token) {
    global $emailService, $db;

    // Get user info
    $stmt = $db->prepare("SELECT id, username FROM users WHERE email = ?");
    $stmt->execute([$user_email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        return false;
    }

    // Generate reset link
    $reset_link = "https://" . $_SERVER['HTTP_HOST'] . "/user-portal/reset-password.php?token=" . $reset_token;

    return $emailService->sendEmail(
        $user_email,
        '',
        '',
        'password_reset',
        [
            'username' => $user['username'],
            'reset_link' => $reset_link,
            'expiry_hours' => '24'
        ],
        null,
        false,  // Send immediately
        2       // High priority
    );
}

// Usage:
// $reset_token = bin2hex(random_bytes(32));
// // Store token in database with expiry
// sendPasswordResetEmail('user@example.com', $reset_token);

// ============================================================================
// EXAMPLE 7: Call Recording Available
// ============================================================================

function sendCallRecordingNotification($user_id, $recording_data) {
    global $emailService, $db;

    if (!$emailService->shouldNotify($user_id, 'call_recording')) {
        return false;
    }

    $stmt = $db->prepare("SELECT email, username FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !$user['email']) {
        return false;
    }

    return $emailService->sendEmail(
        $user['email'],
        '',
        '',
        'call_recording_available',
        [
            'username' => $user['username'],
            'date_time' => date('Y-m-d H:i A', strtotime($recording_data['call_date'])),
            'duration' => $recording_data['duration'],
            'participants' => $recording_data['participants'],
            'recording_id' => $recording_data['id'],
            'custom_message' => 'You can listen to or download this recording from the user portal.'
        ]
    );
}

// Usage:
// $recording = [
//     'call_date' => '2025-10-17 15:30:00',
//     'duration' => '5 minutes 23 seconds',
//     'participants' => 'Extension 2000, 555-1234',
//     'id' => 'REC-20251017-001'
// ];
// sendCallRecordingNotification($user_id, $recording);

// ============================================================================
// EXAMPLE 8: Bulk Email to All Users
// ============================================================================

function sendBulkAnnouncement($subject, $message, $admin_name) {
    global $emailService, $db;

    // Get all users with email addresses
    $stmt = $db->query("
        SELECT u.id, u.email, u.username
        FROM users u
        JOIN user_notification_preferences unp ON unp.user_id = u.id
        WHERE u.email IS NOT NULL
        AND u.email != ''
        AND unp.email_enabled = 1
    ");

    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $queued = 0;

    foreach ($users as $user) {
        // Create a simple HTML message
        $html_body = "<html><body>";
        $html_body .= "<h1>FlexPBX System Announcement</h1>";
        $html_body .= "<p>Hello " . htmlspecialchars($user['username']) . ",</p>";
        $html_body .= "<p>" . nl2br(htmlspecialchars($message)) . "</p>";
        $html_body .= "<p>Best regards,<br>" . htmlspecialchars($admin_name) . "<br>FlexPBX Team</p>";
        $html_body .= "</body></html>";

        $text_body = "FlexPBX System Announcement\n\n";
        $text_body .= "Hello {$user['username']},\n\n";
        $text_body .= $message . "\n\n";
        $text_body .= "Best regards,\n{$admin_name}\nFlexPBX Team";

        if ($emailService->sendEmail(
            $user['email'],
            $subject,
            $html_body,
            null,  // No template
            [],
            null,
            true,  // Queue all
            5      // Normal priority
        )) {
            $queued++;
        }
    }

    return $queued;
}

// Usage:
// $count = sendBulkAnnouncement(
//     'System Maintenance Notice',
//     'The PBX system will be undergoing maintenance on Saturday from 2-4 AM. Service may be briefly interrupted.',
//     'System Administrator'
// );
// echo "Queued $count announcement emails";

// ============================================================================
// EXAMPLE 9: Custom Email with Reply-To Override
// ============================================================================

function sendCustomSupportEmail($to_email, $subject, $message, $support_email) {
    global $emailService;

    $html_body = "<html><body>";
    $html_body .= "<h2>" . htmlspecialchars($subject) . "</h2>";
    $html_body .= "<p>" . nl2br(htmlspecialchars($message)) . "</p>";
    $html_body .= "<hr>";
    $html_body .= "<p><small>This email was sent from FlexPBX Support System</small></p>";
    $html_body .= "</body></html>";

    return $emailService->sendEmail(
        $to_email,
        $subject,
        $html_body,
        null,
        [],
        $support_email,  // Override reply-to with support email
        false            // Send immediately
    );
}

// Usage:
// sendCustomSupportEmail(
//     'customer@example.com',
//     'Your Support Ticket #12345',
//     'Thank you for contacting support. Your issue has been resolved...',
//     'support@devine-creations.com'
// );

// ============================================================================
// EXAMPLE 10: Scheduled Email (Future Sending)
// ============================================================================

function scheduleEmail($to_email, $template_key, $variables, $send_datetime) {
    global $db;

    // Insert into queue with scheduled time
    $stmt = $db->prepare("
        INSERT INTO email_queue (
            to_email,
            subject,
            body_html,
            body_text,
            template_id,
            template_variables,
            status,
            scheduled_at
        ) SELECT
            ?,
            subject,
            body_html,
            body_text,
            id,
            ?,
            'pending',
            ?
        FROM email_templates
        WHERE template_key = ?
    ");

    $stmt->execute([
        $to_email,
        json_encode($variables),
        $send_datetime,
        $template_key
    ]);

    return $db->lastInsertId();
}

// Usage:
// $queue_id = scheduleEmail(
//     'user@example.com',
//     'welcome_email',
//     ['username' => 'John', 'extension' => '2001', 'password' => 'temp123'],
//     '2025-10-18 09:00:00'  // Send tomorrow at 9 AM
// );

// ============================================================================
// EXAMPLE 11: Integration with Asterisk AMI Events
// ============================================================================

function handleAsteriskEvent($event_type, $event_data) {
    global $emailService, $db;

    switch ($event_type) {
        case 'MessageWaiting':
            // New voicemail
            $mailbox = $event_data['Mailbox'];
            $count = $event_data['New'];

            if ($count > 0) {
                // Get user by mailbox/extension
                $stmt = $db->prepare("SELECT id FROM users WHERE extension = ?");
                $stmt->execute([$mailbox]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user) {
                    sendVoicemailNotification($user['id'], [
                        'caller_id' => $event_data['CallerID'] ?? 'Unknown',
                        'timestamp' => date('Y-m-d H:i:s'),
                        'duration' => $event_data['Duration'] ?? 'Unknown'
                    ]);
                }
            }
            break;

        case 'FailedAuth':
            // Failed authentication attempt
            $extension = $event_data['Extension'];
            $ip = $event_data['RemoteAddress'];

            $stmt = $db->prepare("SELECT id FROM users WHERE extension = ?");
            $stmt->execute([$extension]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                sendSecurityAlert($user['id'], [
                    'type' => 'SIP Authentication Failure',
                    'ip_address' => $ip,
                    'details' => "Failed SIP registration attempt on extension $extension from IP $ip"
                ]);
            }
            break;
    }
}

// ============================================================================
// NOTES
// ============================================================================

/*
 * These examples demonstrate common integration patterns.
 * Adapt them to your specific needs.
 *
 * Key Points:
 * 1. Always check user preferences with shouldNotify()
 * 2. Use queue=true for bulk/non-urgent emails
 * 3. Use queue=false for urgent/security emails
 * 4. Set appropriate priority (1=urgent, 10=low)
 * 5. Override reply-to when needed
 * 6. Check for email address existence
 * 7. Handle errors gracefully
 * 8. Use digest mode for high-volume notifications
 * 9. Monitor queue and logs regularly
 * 10. Test thoroughly before production
 */
