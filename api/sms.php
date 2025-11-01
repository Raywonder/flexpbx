<?php
/**
 * FlexPBX SMS Management API
 * Sends and receives SMS via email-to-SMS gateways
 *
 * Endpoints:
 * - GET    ?path=messages&extension=X    - Get SMS messages for extension
 * - GET    ?path=conversation&phone=X    - Get conversation with specific number
 * - POST   ?path=send                    - Send SMS message
 * - GET    ?path=templates&extension=X   - Get SMS templates
 * - POST   ?path=create-template         - Create SMS template
 * - DELETE ?path=delete-template&id=X    - Delete template
 * - GET    ?path=phone-numbers&extension=X - Get linked phone numbers
 * - POST   ?path=link-phone              - Link phone number to extension
 * - DELETE ?path=unlink-phone&id=X       - Unlink phone number
 * - POST   ?path=process-incoming        - Process incoming SMS from email
 * - GET    ?path=carriers                - Get list of supported carriers
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Load database configuration
$config = require __DIR__ . '/config.php';

try {
    $pdo = new PDO(
        "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4",
        $config['db_user'],
        $config['db_password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

// Route requests
switch ($path) {
    case 'messages':
        handleGetMessages();
        break;

    case 'conversation':
        handleGetConversation();
        break;

    case 'send':
        handleSendSMS();
        break;

    case 'templates':
        handleGetTemplates();
        break;

    case 'create-template':
        handleCreateTemplate();
        break;

    case 'delete-template':
        handleDeleteTemplate();
        break;

    case 'phone-numbers':
        handleGetPhoneNumbers();
        break;

    case 'link-phone':
        handleLinkPhone();
        break;

    case 'unlink-phone':
        handleUnlinkPhone();
        break;

    case 'process-incoming':
        handleProcessIncoming();
        break;

    case 'carriers':
        handleGetCarriers();
        break;

    default:
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid endpoint',
            'available_endpoints' => [
                'messages', 'conversation', 'send', 'templates',
                'create-template', 'delete-template', 'phone-numbers',
                'link-phone', 'unlink-phone', 'process-incoming', 'carriers'
            ]
        ]);
        break;
}

/**
 * Get SMS messages for an extension
 */
function handleGetMessages() {
    global $pdo;

    $extension = $_GET['extension'] ?? null;
    if (!$extension) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Extension number required']);
        return;
    }

    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    $direction = $_GET['direction'] ?? null;

    try {
        $query = "
            SELECT
                m.*,
                p.phone_number as my_phone_number,
                p.carrier
            FROM sms_messages m
            LEFT JOIN extension_phone_numbers p ON m.extension_number = p.extension_number
            WHERE m.extension_number = ?
        ";

        $params = [$extension];

        if ($direction) {
            $query .= " AND m.direction = ?";
            $params[] = $direction;
        }

        $query .= " ORDER BY m.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get count
        $countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM sms_messages WHERE extension_number = ?");
        $countStmt->execute([$extension]);
        $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

        echo json_encode([
            'success' => true,
            'data' => $messages,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Get conversation with specific phone number
 */
function handleGetConversation() {
    global $pdo;

    $extension = $_GET['extension'] ?? null;
    $phone = $_GET['phone'] ?? null;

    if (!$extension || !$phone) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Extension and phone number required']);
        return;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT * FROM sms_messages
            WHERE extension_number = ?
            AND (from_number = ? OR to_number = ?)
            ORDER BY created_at ASC
        ");
        $stmt->execute([$extension, $phone, $phone]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => $messages,
            'extension' => $extension,
            'phone' => $phone
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Send SMS message via email gateway
 */
function handleSendSMS() {
    global $pdo;

    $data = json_decode(file_get_contents('php://input'), true);

    // Validate required fields
    if (empty($data['extension']) || empty($data['to_number']) || empty($data['message'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Extension, to_number, and message are required']);
        return;
    }

    $extension = $data['extension'];
    $toNumber = preg_replace('/[^0-9]/', '', $data['to_number']);
    $message = $data['message'];
    $carrierGateway = $data['carrier_gateway'] ?? null;

    try {
        // Get extension's phone number
        $stmt = $pdo->prepare("
            SELECT * FROM extension_phone_numbers
            WHERE extension_number = ? AND enabled = 1
            LIMIT 1
        ");
        $stmt->execute([$extension]);
        $phoneConfig = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$phoneConfig) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'No phone number linked to this extension']);
            return;
        }

        $fromNumber = $phoneConfig['phone_number'];

        // Determine carrier gateway
        if (!$carrierGateway) {
            // Try to auto-detect or use default
            $carrierGateway = detectCarrierGateway($toNumber);
            if (!$carrierGateway) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Carrier gateway required. Please specify carrier.',
                    'carriers' => getSupportedCarriers()
                ]);
                return;
            }
        }

        $toEmail = $toNumber . '@' . $carrierGateway;

        // Insert message record
        $insertStmt = $pdo->prepare("
            INSERT INTO sms_messages (
                extension_id, extension_number, phone_number,
                direction, message_body, from_number, to_number,
                carrier_gateway, status
            ) VALUES (?, ?, ?, 'outbound', ?, ?, ?, ?, 'pending')
        ");

        $insertStmt->execute([
            0, // extension_id (placeholder)
            $extension,
            $fromNumber,
            $message,
            $fromNumber,
            $toNumber,
            $carrierGateway
        ]);

        $messageId = $pdo->lastInsertId();

        // Send email
        $emailResult = sendSMSViaEmail($toEmail, $message, $fromNumber);

        if ($emailResult['success']) {
            // Update status
            $updateStmt = $pdo->prepare("
                UPDATE sms_messages
                SET status = 'sent', sent_at = NOW(), email_message_id = ?
                WHERE id = ?
            ");
            $updateStmt->execute([$emailResult['message_id'] ?? null, $messageId]);

            echo json_encode([
                'success' => true,
                'message' => 'SMS sent successfully',
                'sms_id' => $messageId,
                'to_email' => $toEmail
            ]);
        } else {
            // Update status to failed
            $updateStmt = $pdo->prepare("
                UPDATE sms_messages SET status = 'failed' WHERE id = ?
            ");
            $updateStmt->execute([$messageId]);

            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Failed to send SMS: ' . $emailResult['error']
            ]);
        }

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Send SMS via email gateway
 */
function sendSMSViaEmail($toEmail, $message, $fromNumber) {
    // Use the system's email configuration
    $fromEmail = 'sms@raywonderis.me';
    $fromName = 'FlexPBX SMS';

    // Truncate message to 160 characters (SMS limit)
    $message = substr($message, 0, 160);

    $headers = [
        'From: ' . $fromName . ' <' . $fromEmail . '>',
        'Reply-To: ' . $fromEmail,
        'X-Mailer: FlexPBX SMS Gateway',
        'Content-Type: text/plain; charset=UTF-8'
    ];

    $subject = ''; // Most carriers ignore subject for SMS

    $success = mail($toEmail, $subject, $message, implode("\r\n", $headers));

    if ($success) {
        return [
            'success' => true,
            'message_id' => null // PHP mail() doesn't return message ID
        ];
    } else {
        return [
            'success' => false,
            'error' => 'mail() function failed'
        ];
    }
}

/**
 * Get SMS templates for extension
 */
function handleGetTemplates() {
    global $pdo;

    $extension = $_GET['extension'] ?? null;
    if (!$extension) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Extension number required']);
        return;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT * FROM sms_templates
            WHERE extension_number = ? OR is_shared = 1
            ORDER BY template_name
        ");
        $stmt->execute([$extension]);
        $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => $templates
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Create SMS template
 */
function handleCreateTemplate() {
    global $pdo;

    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['extension']) || empty($data['template_name']) || empty($data['message_body'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Extension, template_name, and message_body required']);
        return;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO sms_templates (extension_number, template_name, message_body, is_shared)
            VALUES (?, ?, ?, ?)
        ");

        $stmt->execute([
            $data['extension'],
            $data['template_name'],
            $data['message_body'],
            $data['is_shared'] ?? 0
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Template created successfully',
            'template_id' => $pdo->lastInsertId()
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Delete SMS template
 */
function handleDeleteTemplate() {
    global $pdo;

    $id = $_GET['id'] ?? null;
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Template ID required']);
        return;
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM sms_templates WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode([
            'success' => true,
            'message' => 'Template deleted successfully'
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Get linked phone numbers for extension
 */
function handleGetPhoneNumbers() {
    global $pdo;

    $extension = $_GET['extension'] ?? null;
    if (!$extension) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Extension number required']);
        return;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT * FROM extension_phone_numbers
            WHERE extension_number = ?
            ORDER BY is_primary DESC, phone_number
        ");
        $stmt->execute([$extension]);
        $phones = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => $phones
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Link phone number to extension
 */
function handleLinkPhone() {
    global $pdo;

    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['extension']) || empty($data['phone_number']) || empty($data['carrier'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Extension, phone_number, and carrier required']);
        return;
    }

    $carriers = getSupportedCarriers();
    $carrierGateway = $carriers[$data['carrier']] ?? null;

    if (!$carrierGateway) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid carrier',
            'supported_carriers' => array_keys($carriers)
        ]);
        return;
    }

    $phoneNumber = preg_replace('/[^0-9]/', '', $data['phone_number']);
    $smsEmail = $phoneNumber . '@' . $carrierGateway;

    try {
        $stmt = $pdo->prepare("
            INSERT INTO extension_phone_numbers
            (extension_number, phone_number, carrier, carrier_gateway, sms_email, is_primary, enabled)
            VALUES (?, ?, ?, ?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE
                carrier = VALUES(carrier),
                carrier_gateway = VALUES(carrier_gateway),
                sms_email = VALUES(sms_email),
                enabled = 1
        ");

        $stmt->execute([
            $data['extension'],
            $phoneNumber,
            $data['carrier'],
            $carrierGateway,
            $smsEmail,
            $data['is_primary'] ?? 0
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Phone number linked successfully',
            'sms_email' => $smsEmail
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Unlink phone number
 */
function handleUnlinkPhone() {
    global $pdo;

    $id = $_GET['id'] ?? null;
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Phone number ID required']);
        return;
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM extension_phone_numbers WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode([
            'success' => true,
            'message' => 'Phone number unlinked successfully'
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Process incoming SMS from email
 */
function handleProcessIncoming() {
    global $pdo;

    $data = json_decode(file_get_contents('php://input'), true);

    // This would be called by an email webhook or cron job
    // Parse email and extract SMS details

    if (empty($data['from_email']) || empty($data['message_body'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'from_email and message_body required']);
        return;
    }

    try {
        // Extract phone number from email (e.g., 3162726712@vtext.com)
        $fromEmail = $data['from_email'];
        if (preg_match('/^(\d+)@/', $fromEmail, $matches)) {
            $fromNumber = $matches[1];
        } else {
            throw new Exception('Invalid SMS email format');
        }

        // Find which extension this SMS belongs to
        $stmt = $pdo->prepare("
            SELECT * FROM extension_phone_numbers
            WHERE phone_number = ?
            LIMIT 1
        ");

        // Try to match by checking if message was sent to our SMS address
        // This is tricky - we'd need to parse the To: field
        $toNumber = $data['to_number'] ?? null;

        if (!$toNumber) {
            throw new Exception('Cannot determine destination number');
        }

        $stmt->execute([$toNumber]);
        $phoneConfig = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$phoneConfig) {
            throw new Exception('No extension linked to destination number');
        }

        // Insert incoming message
        $insertStmt = $pdo->prepare("
            INSERT INTO sms_messages (
                extension_id, extension_number, phone_number,
                direction, message_body, from_number, to_number,
                status, received_at, email_message_id
            ) VALUES (?, ?, ?, 'inbound', ?, ?, ?, 'received', NOW(), ?)
        ");

        $insertStmt->execute([
            0,
            $phoneConfig['extension_number'],
            $phoneConfig['phone_number'],
            $data['message_body'],
            $fromNumber,
            $phoneConfig['phone_number'],
            $data['message_id'] ?? null
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Incoming SMS processed',
            'sms_id' => $pdo->lastInsertId()
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Get supported carriers
 */
function handleGetCarriers() {
    echo json_encode([
        'success' => true,
        'carriers' => getSupportedCarriers()
    ]);
}

/**
 * Get supported carrier gateways
 */
function getSupportedCarriers() {
    return [
        'Verizon' => 'vtext.com',
        'AT&T' => 'txt.att.net',
        'T-Mobile' => 'tmomail.net',
        'Sprint' => 'messaging.sprintpcs.com',
        'Boost Mobile' => 'sms.myboostmobile.com',
        'Cricket' => 'sms.cricketwireless.net',
        'Metro PCS' => 'mymetropcs.com',
        'US Cellular' => 'email.uscc.net',
        'Virgin Mobile' => 'vmobl.com',
        'Google Fi' => 'msg.fi.google.com',
        'Ting' => 'message.ting.com'
    ];
}

/**
 * Detect carrier gateway (placeholder - would need external API)
 */
function detectCarrierGateway($phoneNumber) {
    // This would require a phone number lookup API
    // For now, return null to require manual selection
    return null;
}
