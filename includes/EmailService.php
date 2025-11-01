<?php
/**
 * FlexPBX Email Service
 *
 * Comprehensive email sending service with queue management,
 * retry logic, template support, and rate limiting.
 *
 * @author FlexPBX
 * @version 1.0.0
 * @created 2025-10-17
 */

require_once __DIR__ . '/db.php';

class EmailService {

    private $db;
    private $config;
    private $encryption_key;
    private $logger;

    /**
     * Constructor
     */
    public function __construct() {
        global $db;
        $this->db = $db;
        $this->encryption_key = $this->getEncryptionKey();
        $this->loadConfig();
    }

    /**
     * Get encryption key for SMTP password
     *
     * @return string
     */
    private function getEncryptionKey() {
        // Use a consistent key stored in config or environment
        // In production, this should be in a secure location
        $key_file = __DIR__ . '/../config/email_encryption.key';

        if (!file_exists($key_file)) {
            $key = bin2hex(random_bytes(32));
            file_put_contents($key_file, $key);
            chmod($key_file, 0600);
        }

        return file_get_contents($key_file);
    }

    /**
     * Encrypt sensitive data
     *
     * @param string $data
     * @return string
     */
    private function encrypt($data) {
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $this->encryption_key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt sensitive data
     *
     * @param string $data
     * @return string
     */
    private function decrypt($data) {
        $data = base64_decode($data);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $this->encryption_key, 0, $iv);
    }

    /**
     * Load email configuration from database
     */
    private function loadConfig() {
        $stmt = $this->db->prepare("
            SELECT * FROM email_system_config
            WHERE is_active = 1
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute();
        $this->config = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($this->config && !empty($this->config['smtp_password'])) {
            $this->config['smtp_password'] = $this->decrypt($this->config['smtp_password']);
        }
    }

    /**
     * Send email (queues or sends immediately)
     *
     * @param string $to Email address
     * @param string $subject Email subject
     * @param string $body Email body (can be HTML or text)
     * @param string|null $template_key Template identifier
     * @param array $variables Template variables
     * @param string|null $reply_to_override Override default reply-to
     * @param bool $queue Whether to queue (true) or send immediately (false)
     * @param int $priority Priority 1-10 (1=highest)
     * @param string|null $to_name Recipient name
     * @return bool|int Returns true/false for immediate send, queue ID for queued
     */
    public function sendEmail(
        $to,
        $subject,
        $body = '',
        $template_key = null,
        $variables = [],
        $reply_to_override = null,
        $queue = true,
        $priority = 5,
        $to_name = null
    ) {
        try {
            // Check if email is suppressed (bounced)
            if ($this->isEmailSuppressed($to)) {
                $this->log('Email suppressed due to previous bounces: ' . $to, 'warning');
                return false;
            }

            // Check rate limiting
            if (!$this->checkRateLimit($to)) {
                $this->log('Rate limit exceeded for: ' . $to, 'warning');
                return false;
            }

            // If template is specified, load and process it
            if ($template_key) {
                $template = $this->getTemplate($template_key);
                if (!$template) {
                    $this->log('Template not found: ' . $template_key, 'error');
                    return false;
                }

                $subject = $this->processTemplate($template['subject'], $variables);
                $body_html = $this->processTemplate($template['body_html'], $variables);
                $body_text = $this->processTemplate($template['body_text'], $variables);
            } else {
                // Determine if body is HTML or text
                if (strip_tags($body) != $body) {
                    $body_html = $body;
                    $body_text = strip_tags($body);
                } else {
                    $body_text = $body;
                    $body_html = nl2br(htmlspecialchars($body));
                }
            }

            // Set reply-to
            $reply_to = $reply_to_override ?? $this->config['default_reply_to'];

            // Queue or send immediately
            if ($queue) {
                return $this->queueEmail(
                    $to,
                    $to_name,
                    $subject,
                    $body_html,
                    $body_text,
                    $reply_to,
                    $template_key ? $this->getTemplateId($template_key) : null,
                    $variables,
                    $priority
                );
            } else {
                return $this->sendImmediately(
                    $to,
                    $to_name,
                    $subject,
                    $body_html,
                    $body_text,
                    $reply_to,
                    $template_key
                );
            }

        } catch (Exception $e) {
            $this->log('Error sending email: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Queue email for later sending
     *
     * @param string $to
     * @param string|null $to_name
     * @param string $subject
     * @param string $body_html
     * @param string $body_text
     * @param string $reply_to
     * @param int|null $template_id
     * @param array $variables
     * @param int $priority
     * @return int Queue ID
     */
    private function queueEmail(
        $to,
        $to_name,
        $subject,
        $body_html,
        $body_text,
        $reply_to,
        $template_id,
        $variables,
        $priority
    ) {
        $stmt = $this->db->prepare("
            INSERT INTO email_queue (
                to_email,
                to_name,
                subject,
                body_html,
                body_text,
                reply_to,
                template_id,
                template_variables,
                priority,
                max_attempts
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $to,
            $to_name,
            $subject,
            $body_html,
            $body_text,
            $reply_to,
            $template_id,
            json_encode($variables),
            $priority,
            $this->config['max_retry_attempts']
        ]);

        $queue_id = $this->db->lastInsertId();
        $this->log("Email queued: ID $queue_id, To: $to, Subject: $subject", 'info');

        return $queue_id;
    }

    /**
     * Send email immediately
     *
     * @param string $to
     * @param string|null $to_name
     * @param string $subject
     * @param string $body_html
     * @param string $body_text
     * @param string $reply_to
     * @param string|null $template_key
     * @return bool
     */
    private function sendImmediately(
        $to,
        $to_name,
        $subject,
        $body_html,
        $body_text,
        $reply_to,
        $template_key = null
    ) {
        try {
            // Use PHPMailer or native mail()
            if ($this->sendViaSMTP($to, $to_name, $subject, $body_html, $body_text, $reply_to)) {
                $this->logEmail($to, $to_name, $subject, 'sent', $template_key);
                $this->incrementRateLimit($to);
                return true;
            } else {
                $this->logEmail($to, $to_name, $subject, 'failed', $template_key);
                return false;
            }
        } catch (Exception $e) {
            $this->logEmail($to, $to_name, $subject, 'failed', $template_key, $e->getMessage());
            return false;
        }
    }

    /**
     * Send email via SMTP
     *
     * @param string $to
     * @param string|null $to_name
     * @param string $subject
     * @param string $body_html
     * @param string $body_text
     * @param string $reply_to
     * @return bool
     */
    private function sendViaSMTP($to, $to_name, $subject, $body_html, $body_text, $reply_to) {
        // Create multipart boundary
        $boundary = md5(uniqid(time()));

        // Headers
        $headers = [];
        $headers[] = "From: {$this->config['default_from_name']} <{$this->config['default_from_email']}>";
        $headers[] = "Reply-To: $reply_to";
        $headers[] = "MIME-Version: 1.0";
        $headers[] = "Content-Type: multipart/alternative; boundary=\"$boundary\"";
        $headers[] = "X-Mailer: FlexPBX Email Service 1.0";

        // Build multipart message
        $message = "--$boundary\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $message .= $body_text . "\r\n\r\n";

        $message .= "--$boundary\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $message .= $body_html . "\r\n\r\n";

        $message .= "--$boundary--";

        // If using SMTP, configure stream context
        if ($this->config['smtp_host'] !== 'localhost') {
            $smtp_params = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];

            // Additional SMTP configuration can be added here
            // For production, consider using PHPMailer library
        }

        // Send email
        $recipient = $to_name ? "$to_name <$to>" : $to;
        return mail($recipient, $subject, $message, implode("\r\n", $headers));
    }

    /**
     * Process email queue
     *
     * @param int $limit Maximum number of emails to process
     * @return int Number of emails processed
     */
    public function processQueue($limit = 50) {
        $stmt = $this->db->prepare("
            SELECT * FROM email_queue
            WHERE status = 'pending'
            AND attempts < max_attempts
            AND (scheduled_at IS NULL OR scheduled_at <= NOW())
            ORDER BY priority ASC, created_at ASC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $queued_emails = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $processed = 0;

        foreach ($queued_emails as $email) {
            // Mark as sending
            $this->updateQueueStatus($email['id'], 'sending');

            // Attempt to send
            $sent = $this->sendImmediately(
                $email['to_email'],
                $email['to_name'],
                $email['subject'],
                $email['body_html'],
                $email['body_text'],
                $email['reply_to'],
                $this->getTemplateKeyById($email['template_id'])
            );

            if ($sent) {
                $this->updateQueueStatus($email['id'], 'sent', null, $email['queue_id']);
                $processed++;
            } else {
                $this->incrementQueueAttempts($email['id']);

                // Check if max attempts reached
                if ($email['attempts'] + 1 >= $email['max_attempts']) {
                    $this->updateQueueStatus($email['id'], 'failed', 'Max retry attempts reached');
                }
            }
        }

        $this->log("Processed $processed emails from queue", 'info');
        return $processed;
    }

    /**
     * Update queue status
     *
     * @param int $queue_id
     * @param string $status
     * @param string|null $error
     * @param int|null $log_queue_id
     */
    private function updateQueueStatus($queue_id, $status, $error = null, $log_queue_id = null) {
        $stmt = $this->db->prepare("
            UPDATE email_queue
            SET status = ?,
                last_error = ?,
                sent_at = CASE WHEN ? = 'sent' THEN NOW() ELSE sent_at END,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$status, $error, $status, $queue_id]);
    }

    /**
     * Increment queue attempts
     *
     * @param int $queue_id
     */
    private function incrementQueueAttempts($queue_id) {
        $stmt = $this->db->prepare("
            UPDATE email_queue
            SET attempts = attempts + 1,
                status = 'pending',
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$queue_id]);
    }

    /**
     * Get template by key
     *
     * @param string $template_key
     * @return array|false
     */
    private function getTemplate($template_key) {
        $stmt = $this->db->prepare("
            SELECT * FROM email_templates
            WHERE template_key = ? AND is_active = 1
        ");
        $stmt->execute([$template_key]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get template ID by key
     *
     * @param string $template_key
     * @return int|null
     */
    private function getTemplateId($template_key) {
        $template = $this->getTemplate($template_key);
        return $template ? $template['id'] : null;
    }

    /**
     * Get template key by ID
     *
     * @param int|null $template_id
     * @return string|null
     */
    private function getTemplateKeyById($template_id) {
        if (!$template_id) return null;

        $stmt = $this->db->prepare("
            SELECT template_key FROM email_templates WHERE id = ?
        ");
        $stmt->execute([$template_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['template_key'] : null;
    }

    /**
     * Process template with variables
     *
     * @param string $template
     * @param array $variables
     * @return string
     */
    private function processTemplate($template, $variables) {
        foreach ($variables as $key => $value) {
            $template = str_replace('{{' . $key . '}}', $value, $template);
        }

        // Remove any remaining unprocessed variables
        $template = preg_replace('/\{\{[^}]+\}\}/', '', $template);

        return $template;
    }

    /**
     * Check if email is suppressed (bounced)
     *
     * @param string $email
     * @return bool
     */
    private function isEmailSuppressed($email) {
        $stmt = $this->db->prepare("
            SELECT is_suppressed FROM email_bounces
            WHERE email = ? AND is_suppressed = 1
        ");
        $stmt->execute([$email]);
        return (bool) $stmt->fetch();
    }

    /**
     * Check rate limiting
     *
     * @param string $email
     * @return bool
     */
    private function checkRateLimit($email) {
        // Clean up expired rate limit entries
        $this->db->prepare("
            DELETE FROM email_rate_limit
            WHERE window_end < NOW()
        ")->execute();

        // Check current rate
        $stmt = $this->db->prepare("
            SELECT SUM(count) as total
            FROM email_rate_limit
            WHERE identifier = ?
            AND identifier_type = 'email'
            AND window_end > NOW()
        ");
        $stmt->execute([$email]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $current_count = $result['total'] ?? 0;
        $limit = $this->config['rate_limit_per_hour'] ?? 100;

        return $current_count < $limit;
    }

    /**
     * Increment rate limit counter
     *
     * @param string $email
     */
    private function incrementRateLimit($email) {
        $window_start = date('Y-m-d H:00:00');
        $window_end = date('Y-m-d H:59:59');

        $stmt = $this->db->prepare("
            INSERT INTO email_rate_limit (
                identifier,
                identifier_type,
                count,
                window_start,
                window_end
            ) VALUES (?, 'email', 1, ?, ?)
            ON DUPLICATE KEY UPDATE count = count + 1
        ");
        $stmt->execute([$email, $window_start, $window_end]);
    }

    /**
     * Log email to database
     *
     * @param string $to
     * @param string|null $to_name
     * @param string $subject
     * @param string $status
     * @param string|null $template_key
     * @param string|null $error
     * @param int|null $queue_id
     */
    private function logEmail(
        $to,
        $to_name,
        $subject,
        $status,
        $template_key = null,
        $error = null,
        $queue_id = null
    ) {
        $stmt = $this->db->prepare("
            INSERT INTO email_log (
                queue_id,
                to_email,
                to_name,
                from_email,
                from_name,
                reply_to,
                subject,
                template_key,
                status,
                error_message,
                ip_address
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $queue_id,
            $to,
            $to_name,
            $this->config['default_from_email'],
            $this->config['default_from_name'],
            $this->config['default_reply_to'],
            $subject,
            $template_key,
            $status,
            $error,
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    }

    /**
     * Send test email
     *
     * @param string $to
     * @return bool
     */
    public function sendTestEmail($to) {
        return $this->sendEmail(
            $to,
            'FlexPBX Test Email',
            '',
            'test_email',
            ['timestamp' => date('Y-m-d H:i:s')],
            null,
            false // Send immediately
        );
    }

    /**
     * Get user notification preferences
     *
     * @param int $user_id
     * @return array|false
     */
    public function getUserPreferences($user_id) {
        $stmt = $this->db->prepare("
            SELECT * FROM user_notification_preferences
            WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Check if user should receive notification
     *
     * @param int $user_id
     * @param string $notification_type
     * @return bool
     */
    public function shouldNotify($user_id, $notification_type) {
        $prefs = $this->getUserPreferences($user_id);

        if (!$prefs || !$prefs['email_enabled']) {
            return false;
        }

        $type_key = 'notify_' . $notification_type;
        return isset($prefs[$type_key]) && $prefs[$type_key];
    }

    /**
     * Add notification to digest queue
     *
     * @param int $user_id
     * @param string $notification_type
     * @param array $notification_data
     * @return int
     */
    public function addToDigest($user_id, $notification_type, $notification_data) {
        $stmt = $this->db->prepare("
            INSERT INTO email_digest_queue (
                user_id,
                notification_type,
                notification_data
            ) VALUES (?, ?, ?)
        ");

        $stmt->execute([
            $user_id,
            $notification_type,
            json_encode($notification_data)
        ]);

        return $this->db->lastInsertId();
    }

    /**
     * Process digest emails
     *
     * @param string $frequency 'hourly' or 'daily'
     * @return int Number of digests sent
     */
    public function processDigests($frequency) {
        // Get users with digest enabled
        $stmt = $this->db->prepare("
            SELECT DISTINCT user_id
            FROM user_notification_preferences
            WHERE digest_enabled = 1
            AND digest_frequency = ?
        ");
        $stmt->execute([$frequency]);
        $users = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $sent = 0;

        foreach ($users as $user_id) {
            if ($this->sendDigest($user_id)) {
                $sent++;
            }
        }

        return $sent;
    }

    /**
     * Send digest email to user
     *
     * @param int $user_id
     * @return bool
     */
    private function sendDigest($user_id) {
        // Get notifications from queue
        $stmt = $this->db->prepare("
            SELECT * FROM email_digest_queue
            WHERE user_id = ?
            ORDER BY created_at ASC
        ");
        $stmt->execute([$user_id]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($notifications)) {
            return false;
        }

        // Build digest content
        // This is a simplified version - you'd build a proper HTML digest
        $digest_content = "You have " . count($notifications) . " notifications:\n\n";

        foreach ($notifications as $notification) {
            $data = json_decode($notification['notification_data'], true);
            $digest_content .= "- {$notification['notification_type']}: " .
                               json_encode($data) . "\n";
        }

        // Send digest
        // Get user email from database
        $user_stmt = $this->db->prepare("SELECT email, username FROM users WHERE id = ?");
        $user_stmt->execute([$user_id]);
        $user = $user_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return false;
        }

        $sent = $this->sendEmail(
            $user['email'],
            'FlexPBX Notification Digest',
            $digest_content,
            null,
            [],
            null,
            false
        );

        if ($sent) {
            // Clear digest queue for this user
            $clear_stmt = $this->db->prepare("
                DELETE FROM email_digest_queue WHERE user_id = ?
            ");
            $clear_stmt->execute([$user_id]);
        }

        return $sent;
    }

    /**
     * Log message
     *
     * @param string $message
     * @param string $level
     */
    private function log($message, $level = 'info') {
        $log_file = __DIR__ . '/../logs/email.log';
        $log_dir = dirname($log_file);

        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }

        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[$timestamp] [$level] $message\n";

        file_put_contents($log_file, $log_entry, FILE_APPEND);
    }

    /**
     * Get email statistics
     *
     * @param int $days Number of days to look back
     * @return array
     */
    public function getStatistics($days = 7) {
        $stmt = $this->db->prepare("
            SELECT
                status,
                COUNT(*) as count,
                DATE(sent_at) as date
            FROM email_log
            WHERE sent_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY status, DATE(sent_at)
            ORDER BY date DESC, status
        ");
        $stmt->execute([$days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get queue summary
     *
     * @return array
     */
    public function getQueueSummary() {
        $stmt = $this->db->query("
            SELECT
                status,
                COUNT(*) as count,
                MIN(created_at) as oldest,
                MAX(created_at) as newest
            FROM email_queue
            GROUP BY status
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retry failed emails
     *
     * @param int|null $queue_id Specific queue ID or null for all
     * @return int Number of emails reset for retry
     */
    public function retryFailed($queue_id = null) {
        if ($queue_id) {
            $stmt = $this->db->prepare("
                UPDATE email_queue
                SET status = 'pending',
                    attempts = 0,
                    last_error = NULL
                WHERE id = ? AND status = 'failed'
            ");
            $stmt->execute([$queue_id]);
        } else {
            $stmt = $this->db->prepare("
                UPDATE email_queue
                SET status = 'pending',
                    attempts = 0,
                    last_error = NULL
                WHERE status = 'failed'
            ");
            $stmt->execute();
        }

        return $stmt->rowCount();
    }

    /**
     * Clear old logs
     *
     * @param int $days Keep logs for this many days
     * @return int Number of logs deleted
     */
    public function clearOldLogs($days = 30) {
        $stmt = $this->db->prepare("
            DELETE FROM email_log
            WHERE sent_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->execute([$days]);
        return $stmt->rowCount();
    }
}
