<?php
/**
 * FlexPBX Email Configuration API
 *
 * RESTful API for email system configuration and management
 *
 * @author FlexPBX
 * @version 1.0.0
 * @created 2025-10-17
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/EmailService.php';

// Start session for authentication
session_start();

// Parse request
$method = $_SERVER['REQUEST_METHOD'];
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);
$query = parse_url($request_uri, PHP_URL_QUERY);

// Parse query parameters
parse_str($query ?? '', $query_params);

// Get request body for POST/PUT/PATCH
$input = json_decode(file_get_contents('php://input'), true) ?? [];

// Merge GET and POST data
$data = array_merge($query_params, $input, $_POST);

// Initialize response
$response = [
    'success' => false,
    'data' => null,
    'error' => null
];

try {
    // Authentication check
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Authentication required', 401);
    }

    $user_id = $_SESSION['user_id'];
    $is_admin = $_SESSION['role'] === 'admin';

    // Initialize email service
    $emailService = new EmailService();

    // Route request
    $action = $data['action'] ?? '';

    switch ($action) {
        // =====================================
        // ADMIN ENDPOINTS
        // =====================================

        case 'get_config':
            if (!$is_admin) {
                throw new Exception('Admin access required', 403);
            }
            $response['data'] = getConfig();
            $response['success'] = true;
            break;

        case 'update_config':
            if (!$is_admin) {
                throw new Exception('Admin access required', 403);
            }
            $response['data'] = updateConfig($data);
            $response['success'] = true;
            break;

        case 'send_test':
            if (!$is_admin) {
                throw new Exception('Admin access required', 403);
            }
            $email = filter_var($data['email'] ?? '', FILTER_VALIDATE_EMAIL);
            if (!$email) {
                throw new Exception('Valid email address required');
            }
            $result = $emailService->sendTestEmail($email);
            $response['success'] = $result;
            $response['data'] = ['sent' => $result];
            break;

        case 'get_templates':
            if (!$is_admin) {
                throw new Exception('Admin access required', 403);
            }
            $response['data'] = getTemplates();
            $response['success'] = true;
            break;

        case 'get_template':
            if (!$is_admin) {
                throw new Exception('Admin access required', 403);
            }
            $template_id = intval($data['template_id'] ?? 0);
            if (!$template_id) {
                throw new Exception('Template ID required');
            }
            $response['data'] = getTemplate($template_id);
            $response['success'] = true;
            break;

        case 'update_template':
            if (!$is_admin) {
                throw new Exception('Admin access required', 403);
            }
            $response['data'] = updateTemplate($data);
            $response['success'] = true;
            break;

        case 'get_queue':
            if (!$is_admin) {
                throw new Exception('Admin access required', 403);
            }
            $limit = intval($data['limit'] ?? 50);
            $status = $data['status'] ?? null;
            $response['data'] = getQueue($limit, $status);
            $response['success'] = true;
            break;

        case 'retry_failed':
            if (!$is_admin) {
                throw new Exception('Admin access required', 403);
            }
            $queue_id = isset($data['queue_id']) ? intval($data['queue_id']) : null;
            $count = $emailService->retryFailed($queue_id);
            $response['success'] = true;
            $response['data'] = ['retried' => $count];
            break;

        case 'get_logs':
            if (!$is_admin) {
                throw new Exception('Admin access required', 403);
            }
            $limit = intval($data['limit'] ?? 100);
            $offset = intval($data['offset'] ?? 0);
            $response['data'] = getLogs($limit, $offset);
            $response['success'] = true;
            break;

        case 'get_statistics':
            if (!$is_admin) {
                throw new Exception('Admin access required', 403);
            }
            $days = intval($data['days'] ?? 7);
            $response['data'] = $emailService->getStatistics($days);
            $response['success'] = true;
            break;

        case 'process_queue':
            if (!$is_admin) {
                throw new Exception('Admin access required', 403);
            }
            $limit = intval($data['limit'] ?? 50);
            $count = $emailService->processQueue($limit);
            $response['success'] = true;
            $response['data'] = ['processed' => $count];
            break;

        // =====================================
        // USER ENDPOINTS
        // =====================================

        case 'get_preferences':
            $response['data'] = getUserPreferences($user_id);
            $response['success'] = true;
            break;

        case 'update_preferences':
            $response['data'] = updateUserPreferences($user_id, $data);
            $response['success'] = true;
            break;

        case 'preview_email':
            $template_key = $data['template_key'] ?? '';
            $variables = $data['variables'] ?? [];
            if (!$template_key) {
                throw new Exception('Template key required');
            }
            $response['data'] = previewEmail($template_key, $variables);
            $response['success'] = true;
            break;

        case 'send_notification':
            // Send email notification
            $to = filter_var($data['to'] ?? '', FILTER_VALIDATE_EMAIL);
            $template_key = $data['template_key'] ?? '';
            $variables = $data['variables'] ?? [];

            if (!$to || !$template_key) {
                throw new Exception('Email address and template key required');
            }

            // Check if user should receive this notification
            $notification_type = $data['notification_type'] ?? '';
            if ($notification_type && !$emailService->shouldNotify($user_id, $notification_type)) {
                $response['success'] = true;
                $response['data'] = ['sent' => false, 'reason' => 'User preferences disabled'];
                break;
            }

            $queue_id = $emailService->sendEmail(
                $to,
                $data['subject'] ?? '',
                $data['body'] ?? '',
                $template_key,
                $variables,
                $data['reply_to'] ?? null,
                $data['queue'] ?? true
            );

            $response['success'] = true;
            $response['data'] = ['sent' => true, 'queue_id' => $queue_id];
            break;

        default:
            throw new Exception('Invalid action specified');
    }

} catch (Exception $e) {
    $response['success'] = false;
    $response['error'] = $e->getMessage();

    // Set appropriate HTTP status code
    $code = $e->getCode();
    if ($code === 401) {
        http_response_code(401);
    } elseif ($code === 403) {
        http_response_code(403);
    } elseif ($code === 404) {
        http_response_code(404);
    } else {
        http_response_code(400);
    }
}

// Send response
echo json_encode($response, JSON_PRETTY_PRINT);
exit;

// =====================================
// HELPER FUNCTIONS
// =====================================

/**
 * Get email configuration
 */
function getConfig() {
    global $db;

    $stmt = $db->query("
        SELECT id, smtp_host, smtp_port, smtp_security, smtp_username,
               default_from_email, default_from_name, default_reply_to,
               max_retry_attempts, send_timeout, rate_limit_per_hour,
               is_active, created_at, updated_at
        FROM email_system_config
        WHERE is_active = 1
        ORDER BY id DESC
        LIMIT 1
    ");

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Update email configuration
 */
function updateConfig($data) {
    global $db;

    // Get encryption key
    $key_file = __DIR__ . '/../config/email_encryption.key';
    if (!file_exists($key_file)) {
        $key = bin2hex(random_bytes(32));
        @mkdir(dirname($key_file), 0755, true);
        file_put_contents($key_file, $key);
        chmod($key_file, 0600);
    }
    $encryption_key = file_get_contents($key_file);

    // Encrypt password if provided
    $encrypted_password = null;
    if (!empty($data['smtp_password'])) {
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($data['smtp_password'], 'AES-256-CBC', $encryption_key, 0, $iv);
        $encrypted_password = base64_encode($iv . $encrypted);
    }

    // Check if config exists
    $stmt = $db->query("SELECT COUNT(*) FROM email_system_config");
    $exists = $stmt->fetchColumn() > 0;

    if ($exists) {
        $sql = "UPDATE email_system_config SET
            smtp_host = ?,
            smtp_port = ?,
            smtp_security = ?,
            smtp_username = ?,
            " . ($encrypted_password ? "smtp_password = ?," : "") . "
            default_from_email = ?,
            default_from_name = ?,
            default_reply_to = ?,
            max_retry_attempts = ?,
            send_timeout = ?,
            rate_limit_per_hour = ?,
            updated_at = NOW()
            WHERE id = (SELECT id FROM (SELECT id FROM email_system_config ORDER BY id DESC LIMIT 1) AS tmp)";

        $params = [
            $data['smtp_host'],
            intval($data['smtp_port']),
            $data['smtp_security'],
            $data['smtp_username']
        ];

        if ($encrypted_password) {
            $params[] = $encrypted_password;
        }

        $params = array_merge($params, [
            $data['default_from_email'],
            $data['default_from_name'],
            $data['default_reply_to'],
            intval($data['max_retry_attempts'] ?? 3),
            intval($data['send_timeout'] ?? 30),
            intval($data['rate_limit_per_hour'] ?? 100)
        ]);
    } else {
        $sql = "INSERT INTO email_system_config (
            smtp_host, smtp_port, smtp_security, smtp_username, smtp_password,
            default_from_email, default_from_name, default_reply_to,
            max_retry_attempts, send_timeout, rate_limit_per_hour
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $params = [
            $data['smtp_host'],
            intval($data['smtp_port']),
            $data['smtp_security'],
            $data['smtp_username'],
            $encrypted_password ?? '',
            $data['default_from_email'],
            $data['default_from_name'],
            $data['default_reply_to'],
            intval($data['max_retry_attempts'] ?? 3),
            intval($data['send_timeout'] ?? 30),
            intval($data['rate_limit_per_hour'] ?? 100)
        ];
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    return ['updated' => true];
}

/**
 * Get all templates
 */
function getTemplates() {
    global $db;

    $stmt = $db->query("
        SELECT * FROM email_templates
        WHERE is_active = 1
        ORDER BY category, template_name
    ");

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get single template
 */
function getTemplate($template_id) {
    global $db;

    $stmt = $db->prepare("
        SELECT * FROM email_templates WHERE id = ?
    ");
    $stmt->execute([$template_id]);

    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Update template
 */
function updateTemplate($data) {
    global $db;

    $stmt = $db->prepare("
        UPDATE email_templates
        SET subject = ?,
            body_html = ?,
            body_text = ?,
            updated_at = NOW()
        WHERE id = ?
    ");

    $stmt->execute([
        $data['subject'],
        $data['body_html'],
        $data['body_text'],
        intval($data['template_id'])
    ]);

    return ['updated' => true];
}

/**
 * Get email queue
 */
function getQueue($limit, $status = null) {
    global $db;

    $sql = "SELECT * FROM email_queue";
    $params = [];

    if ($status) {
        $sql .= " WHERE status = ?";
        $params[] = $status;
    }

    $sql .= " ORDER BY priority ASC, created_at ASC LIMIT ?";
    $params[] = $limit;

    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get email logs
 */
function getLogs($limit, $offset) {
    global $db;

    $stmt = $db->prepare("
        SELECT * FROM email_log
        ORDER BY sent_at DESC
        LIMIT ? OFFSET ?
    ");

    $stmt->execute([$limit, $offset]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get user notification preferences
 */
function getUserPreferences($user_id) {
    global $db;

    $stmt = $db->prepare("
        SELECT * FROM user_notification_preferences
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);

    $prefs = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$prefs) {
        // Create default preferences
        $stmt = $db->prepare("
            INSERT INTO user_notification_preferences (
                user_id,
                email_enabled,
                notify_voicemail,
                notify_missed_call,
                notify_extension_change,
                notify_security_alert,
                notify_call_recording,
                notify_fax,
                notify_conference
            ) VALUES (?, 1, 1, 1, 1, 1, 0, 1, 0)
        ");
        $stmt->execute([$user_id]);

        // Fetch newly created preferences
        $stmt = $db->prepare("
            SELECT * FROM user_notification_preferences WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);
        $prefs = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    return $prefs;
}

/**
 * Update user notification preferences
 */
function updateUserPreferences($user_id, $data) {
    global $db;

    $stmt = $db->prepare("
        UPDATE user_notification_preferences
        SET email_enabled = ?,
            notify_voicemail = ?,
            notify_missed_call = ?,
            notify_extension_change = ?,
            notify_security_alert = ?,
            notify_call_recording = ?,
            notify_fax = ?,
            notify_conference = ?,
            digest_enabled = ?,
            digest_frequency = ?,
            digest_time = ?,
            updated_at = NOW()
        WHERE user_id = ?
    ");

    $stmt->execute([
        intval($data['email_enabled'] ?? 1),
        intval($data['notify_voicemail'] ?? 1),
        intval($data['notify_missed_call'] ?? 1),
        intval($data['notify_extension_change'] ?? 1),
        intval($data['notify_security_alert'] ?? 1),
        intval($data['notify_call_recording'] ?? 0),
        intval($data['notify_fax'] ?? 1),
        intval($data['notify_conference'] ?? 0),
        intval($data['digest_enabled'] ?? 0),
        $data['digest_frequency'] ?? 'immediate',
        $data['digest_time'] ?? '09:00:00',
        $user_id
    ]);

    return ['updated' => true];
}

/**
 * Preview email with variables
 */
function previewEmail($template_key, $variables) {
    global $db;

    $stmt = $db->prepare("
        SELECT * FROM email_templates
        WHERE template_key = ? AND is_active = 1
    ");
    $stmt->execute([$template_key]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$template) {
        throw new Exception('Template not found', 404);
    }

    // Process template variables
    $subject = $template['subject'];
    $body_html = $template['body_html'];
    $body_text = $template['body_text'];

    foreach ($variables as $key => $value) {
        $subject = str_replace('{{' . $key . '}}', $value, $subject);
        $body_html = str_replace('{{' . $key . '}}', $value, $body_html);
        $body_text = str_replace('{{' . $key . '}}', $value, $body_text);
    }

    return [
        'subject' => $subject,
        'body_html' => $body_html,
        'body_text' => $body_text,
        'template' => $template
    ];
}
