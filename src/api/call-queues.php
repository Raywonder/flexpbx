<?php
/**
 * FlexPBX Call Queue Management API
 * Manage call queues, agents, and monitor queue performance
 *
 * @version 1.0.0
 * @package FlexPBX
 */

require_once __DIR__ . '/auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Authentication
$auth = checkAuth();
if (!$auth['authenticated']) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Database connection
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
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

switch ($path) {
    case '':
    case 'list':
        handleListQueues($method);
        break;

    case 'create':
        handleCreateQueue($method);
        break;

    case 'update':
        handleUpdateQueue($method);
        break;

    case 'delete':
        handleDeleteQueue($method);
        break;

    case 'members':
        handleQueueMembers($method);
        break;

    case 'add-member':
        handleAddMember($method);
        break;

    case 'remove-member':
        handleRemoveMember($method);
        break;

    case 'pause-member':
        handlePauseMember($method);
        break;

    case 'unpause-member':
        handleUnpauseMember($method);
        break;

    case 'live-status':
        handleLiveStatus($method);
        break;

    case 'statistics':
        handleStatistics($method);
        break;

    case 'callback-requests':
        handleCallbackRequests($method);
        break;

    case 'request-callback':
        handleRequestCallback($method);
        break;

    case 'update-callback':
        handleUpdateCallback($method);
        break;

    case 'apply-config':
        handleApplyConfig($method);
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found']);
        break;
}

/**
 * List all queues
 */
function handleListQueues($method) {
    global $pdo;

    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    try {
        ensureQueueCallbackTables($pdo);
        $stmt = $pdo->query("
            SELECT q.*, COUNT(DISTINCT qm.id) as member_count,
                   COUNT(DISTINCT CASE WHEN qcr.status IN ('pending', 'scheduled', 'offered') THEN qcr.id END) as pending_callback_count
            FROM call_queues q
            LEFT JOIN queue_members qm ON q.id = qm.queue_id
            LEFT JOIN queue_callback_requests qcr ON q.id = qcr.queue_id
            GROUP BY q.id
            ORDER BY q.queue_number ASC
        ");
        $queues = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'queues' => $queues,
            'total' => count($queues),
            'timestamp' => date('c')
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

/**
 * Create new queue
 */
function handleCreateQueue($method) {
    global $pdo;

    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    // Validate required fields
    if (empty($data['queue_number']) || empty($data['queue_name'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Queue number and name are required']);
        return;
    }

    try {
        ensureQueueCallbackTables($pdo);
        $stmt = $pdo->prepare("
            INSERT INTO call_queues
            (queue_number, queue_name, strategy, timeout, retry, max_wait_time, max_callers,
             announce_frequency, announce_position, announce_holdtime, music_class,
             join_announcement, periodic_announce, periodic_announce_frequency,
             callback_enabled, auto_pause, autopause_delay, wrap_up_time, service_level, enabled)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $data['queue_number'],
            $data['queue_name'],
            $data['strategy'] ?? 'ringall',
            $data['timeout'] ?? 15,
            $data['retry'] ?? 5,
            $data['max_wait_time'] ?? 0,
            $data['max_callers'] ?? 0,
            $data['announce_frequency'] ?? 90,
            $data['announce_position'] ?? 'yes',
            $data['announce_holdtime'] ?? 'yes',
            $data['music_class'] ?? 'default',
            $data['join_announcement'] ?? null,
            $data['periodic_announce'] ?? null,
            $data['periodic_announce_frequency'] ?? 0,
            isset($data['callback_enabled']) ? (int)$data['callback_enabled'] : 0,
            $data['auto_pause'] ?? 'no',
            $data['autopause_delay'] ?? 0,
            $data['wrap_up_time'] ?? 0,
            $data['service_level'] ?? 60,
            isset($data['enabled']) ? (int)$data['enabled'] : 1
        ]);

        $queue_id = $pdo->lastInsertId();

        echo json_encode([
            'success' => true,
            'message' => 'Queue created successfully',
            'queue_id' => $queue_id,
            'timestamp' => date('c')
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

/**
 * Update queue
 */
function handleUpdateQueue($method) {
    global $pdo;

    if ($method !== 'PUT') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    $queue_id = $_GET['id'] ?? null;
    if (!$queue_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Queue ID required']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    try {
        ensureQueueCallbackTables($pdo);
        $fields = [];
        $values = [];

        $allowed_fields = [
            'queue_number', 'queue_name', 'strategy', 'timeout', 'retry',
            'max_wait_time', 'max_callers', 'announce_frequency', 'announce_position',
            'announce_holdtime', 'music_class', 'join_announcement', 'periodic_announce',
            'periodic_announce_frequency', 'callback_enabled', 'auto_pause',
            'autopause_delay', 'wrap_up_time', 'service_level', 'enabled'
        ];

        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = ?";
                $values[] = $data[$field];
            }
        }

        if (empty($fields)) {
            http_response_code(400);
            echo json_encode(['error' => 'No fields to update']);
            return;
        }

        $values[] = $queue_id;
        $sql = "UPDATE call_queues SET " . implode(', ', $fields) . " WHERE id = ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);

        echo json_encode([
            'success' => true,
            'message' => 'Queue updated successfully',
            'timestamp' => date('c')
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

/**
 * Delete queue
 */
function handleDeleteQueue($method) {
    global $pdo;

    if ($method !== 'DELETE') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    $queue_id = $_GET['id'] ?? null;
    if (!$queue_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Queue ID required']);
        return;
    }

    try {
        ensureQueueCallbackTables($pdo);
        $stmt = $pdo->prepare("DELETE FROM call_queues WHERE id = ?");
        $stmt->execute([$queue_id]);

        echo json_encode([
            'success' => true,
            'message' => 'Queue deleted successfully',
            'timestamp' => date('c')
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

/**
 * Get queue members
 */
function handleQueueMembers($method) {
    global $pdo;

    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    $queue_id = $_GET['queue_id'] ?? null;
    if (!$queue_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Queue ID required']);
        return;
    }

    try {
        ensureQueueCallbackTables($pdo);
        $stmt = $pdo->prepare("SELECT * FROM queue_members WHERE queue_id = ? ORDER BY penalty ASC, member_extension ASC");
        $stmt->execute([$queue_id]);
        $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'members' => $members,
            'total' => count($members),
            'timestamp' => date('c')
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

/**
 * Add member to queue
 */
function handleAddMember($method) {
    global $pdo;

    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['queue_id']) || empty($data['member_extension'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Queue ID and member extension required']);
        return;
    }

    try {
        ensureQueueCallbackTables($pdo);
        $stmt = $pdo->prepare("
            INSERT INTO queue_members
            (queue_id, member_type, member_extension, member_name, penalty, state_interface, paused)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $data['queue_id'],
            $data['member_type'] ?? 'extension',
            $data['member_extension'],
            $data['member_name'] ?? null,
            $data['penalty'] ?? 0,
            $data['state_interface'] ?? "PJSIP/{$data['member_extension']}",
            0
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Member added successfully',
            'timestamp' => date('c')
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

/**
 * Remove member from queue
 */
function handleRemoveMember($method) {
    global $pdo;

    if ($method !== 'DELETE') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    $member_id = $_GET['id'] ?? null;
    if (!$member_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Member ID required']);
        return;
    }

    try {
        ensureQueueCallbackTables($pdo);
        $stmt = $pdo->prepare("DELETE FROM queue_members WHERE id = ?");
        $stmt->execute([$member_id]);

        echo json_encode([
            'success' => true,
            'message' => 'Member removed successfully',
            'timestamp' => date('c')
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

/**
 * Pause queue member
 */
function handlePauseMember($method) {
    global $pdo;

    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $member_id = $data['member_id'] ?? null;
    $reason = $data['reason'] ?? 'Manually paused';

    if (!$member_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Member ID required']);
        return;
    }

    try {
        ensureQueueCallbackTables($pdo);
        $stmt = $pdo->prepare("UPDATE queue_members SET paused = 1, paused_reason = ? WHERE id = ?");
        $stmt->execute([$reason, $member_id]);

        // Also pause in Asterisk
        $member_stmt = $pdo->prepare("SELECT queue_id, member_extension FROM queue_members WHERE id = ?");
        $member_stmt->execute([$member_id]);
        $member = $member_stmt->fetch(PDO::FETCH_ASSOC);

        if ($member) {
            $queue_stmt = $pdo->prepare("SELECT queue_number FROM call_queues WHERE id = ?");
            $queue_stmt->execute([$member['queue_id']]);
            $queue = $queue_stmt->fetch(PDO::FETCH_ASSOC);

            if ($queue) {
                $interface = "PJSIP/{$member['member_extension']}";
                exec("sudo asterisk -rx 'queue pause member $interface queue {$queue['queue_number']} reason \"$reason\"' 2>&1", $output);
            }
        }

        echo json_encode([
            'success' => true,
            'message' => 'Member paused successfully',
            'timestamp' => date('c')
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

/**
 * Unpause queue member
 */
function handleUnpauseMember($method) {
    global $pdo;

    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $member_id = $data['member_id'] ?? null;

    if (!$member_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Member ID required']);
        return;
    }

    try {
        ensureQueueCallbackTables($pdo);
        $stmt = $pdo->prepare("UPDATE queue_members SET paused = 0, paused_reason = NULL WHERE id = ?");
        $stmt->execute([$member_id]);

        // Also unpause in Asterisk
        $member_stmt = $pdo->prepare("SELECT queue_id, member_extension FROM queue_members WHERE id = ?");
        $member_stmt->execute([$member_id]);
        $member = $member_stmt->fetch(PDO::FETCH_ASSOC);

        if ($member) {
            $queue_stmt = $pdo->prepare("SELECT queue_number FROM call_queues WHERE id = ?");
            $queue_stmt->execute([$member['queue_id']]);
            $queue = $queue_stmt->fetch(PDO::FETCH_ASSOC);

            if ($queue) {
                $interface = "PJSIP/{$member['member_extension']}";
                exec("sudo asterisk -rx 'queue unpause member $interface queue {$queue['queue_number']}' 2>&1", $output);
            }
        }

        echo json_encode([
            'success' => true,
            'message' => 'Member unpaused successfully',
            'timestamp' => date('c')
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

function ensureQueueCallbackTables($pdo) {
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS queue_callback_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            queue_id INT NOT NULL,
            caller_number VARCHAR(32) NOT NULL,
            caller_name VARCHAR(120) DEFAULT NULL,
            caller_extension VARCHAR(32) DEFAULT NULL,
            requested_channel ENUM('ivr', 'agent', 'api', 'voicemail', 'admin') DEFAULT 'api',
            callback_number VARCHAR(32) NOT NULL,
            status ENUM('pending', 'offered', 'scheduled', 'processing', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
            priority INT DEFAULT 0,
            notes TEXT DEFAULT NULL,
            queue_position_at_request INT DEFAULT NULL,
            estimated_wait_seconds INT DEFAULT NULL,
            scheduled_for TIMESTAMP NULL DEFAULT NULL,
            processed_at TIMESTAMP NULL DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_queue_callback_queue (queue_id),
            INDEX idx_queue_callback_status (status),
            INDEX idx_queue_callback_number (callback_number)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $ensured = true;
}

function normalizeCallbackNumber($raw) {
    $digits = preg_replace('/\D+/', '', (string)$raw);
    if ($digits === '') {
        return null;
    }
    if (strlen($digits) === 10) {
        return '1' . $digits;
    }
    if (strlen($digits) === 11 && $digits[0] === '1') {
        return $digits;
    }
    return null;
}

function queueAllowsCallback($pdo, $queueId) {
    $stmt = $pdo->prepare("SELECT id, queue_name, queue_number, callback_enabled FROM call_queues WHERE id = ? LIMIT 1");
    $stmt->execute([$queueId]);
    $queue = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$queue) {
        return [false, null, 'Queue not found'];
    }
    if ((int)$queue['callback_enabled'] !== 1) {
        return [false, $queue, 'Callback is not enabled for this queue'];
    }
    return [true, $queue, null];
}

function handleCallbackRequests($method) {
    global $pdo;

    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    ensureQueueCallbackTables($pdo);

    $queueId = $_GET['queue_id'] ?? null;
    $status = $_GET['status'] ?? null;

    try {
        $conditions = [];
        $values = [];

        if ($queueId) {
            $conditions[] = 'qcr.queue_id = ?';
            $values[] = $queueId;
        }
        if ($status) {
            $conditions[] = 'qcr.status = ?';
            $values[] = $status;
        }

        $where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';
        $stmt = $pdo->prepare("
            SELECT qcr.*, q.queue_name, q.queue_number
            FROM queue_callback_requests qcr
            INNER JOIN call_queues q ON q.id = qcr.queue_id
            $where
            ORDER BY
                CASE qcr.status
                    WHEN 'processing' THEN 0
                    WHEN 'scheduled' THEN 1
                    WHEN 'pending' THEN 2
                    WHEN 'offered' THEN 3
                    ELSE 4
                END,
                qcr.created_at ASC
        ");
        $stmt->execute($values);
        $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'requests' => $requests,
            'total' => count($requests),
            'timestamp' => date('c')
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

function handleRequestCallback($method) {
    global $pdo;

    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    ensureQueueCallbackTables($pdo);
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data) || empty($data)) {
        $data = $_POST;
    }

    $queueId = $data['queue_id'] ?? null;
    $callbackNumber = normalizeCallbackNumber($data['callback_number'] ?? $data['caller_number'] ?? null);
    $callerNumber = normalizeCallbackNumber($data['caller_number'] ?? $data['callback_number'] ?? null);

    if (!$queueId || !$callbackNumber) {
        http_response_code(400);
        echo json_encode(['error' => 'Queue ID and callback number are required']);
        return;
    }

    [$allowed, $queue, $reason] = queueAllowsCallback($pdo, $queueId);
    if (!$allowed) {
        http_response_code(400);
        echo json_encode(['error' => $reason]);
        return;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO queue_callback_requests
            (queue_id, caller_number, caller_name, caller_extension, requested_channel, callback_number,
             status, priority, notes, queue_position_at_request, estimated_wait_seconds, scheduled_for)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $status = !empty($data['scheduled_for']) ? 'scheduled' : 'pending';
        $stmt->execute([
            $queueId,
            $callerNumber ?? $callbackNumber,
            $data['caller_name'] ?? null,
            $data['caller_extension'] ?? null,
            $data['requested_channel'] ?? 'api',
            $callbackNumber,
            $status,
            (int)($data['priority'] ?? 0),
            $data['notes'] ?? null,
            isset($data['queue_position_at_request']) ? (int)$data['queue_position_at_request'] : null,
            isset($data['estimated_wait_seconds']) ? (int)$data['estimated_wait_seconds'] : null,
            !empty($data['scheduled_for']) ? $data['scheduled_for'] : null
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Callback request queued successfully',
            'request_id' => $pdo->lastInsertId(),
            'queue' => [
                'id' => $queue['id'],
                'name' => $queue['queue_name'],
                'number' => $queue['queue_number']
            ],
            'timestamp' => date('c')
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

function handleUpdateCallback($method) {
    global $pdo;

    if (!in_array($method, ['POST', 'PUT'], true)) {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    ensureQueueCallbackTables($pdo);
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data) || empty($data)) {
        $data = $_POST;
    }

    $requestId = $data['request_id'] ?? ($_GET['id'] ?? null);
    if (!$requestId) {
        http_response_code(400);
        echo json_encode(['error' => 'Callback request ID is required']);
        return;
    }

    $fields = [];
    $values = [];
    $allowedFields = ['status', 'notes', 'priority', 'scheduled_for'];

    foreach ($allowedFields as $field) {
        if (array_key_exists($field, $data)) {
            $fields[] = "$field = ?";
            $values[] = $data[$field];
        }
    }
    if (!empty($data['status']) && in_array($data['status'], ['processing', 'completed', 'failed', 'cancelled'], true)) {
        $fields[] = 'processed_at = ?';
        $values[] = date('Y-m-d H:i:s');
    }

    if (empty($fields)) {
        http_response_code(400);
        echo json_encode(['error' => 'No callback fields to update']);
        return;
    }

    try {
        $values[] = $requestId;
        $stmt = $pdo->prepare("UPDATE queue_callback_requests SET " . implode(', ', $fields) . " WHERE id = ?");
        $stmt->execute($values);

        echo json_encode([
            'success' => true,
            'message' => 'Callback request updated',
            'timestamp' => date('c')
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

/**
 * Get live queue status from Asterisk
 */
/**
 * Get detailed SIP registration info for queue members
 */
function getSipRegistrationInfo() {
    $sip_info = [];

    // Get endpoint status
    exec('sudo asterisk -rx "pjsip show endpoints" 2>&1', $endpoints_output);

    // Get contact details (includes user-agent)
    exec('sudo asterisk -rx "pjsip show contacts" 2>&1', $contacts_output);

    // Parse endpoints
    foreach ($endpoints_output as $line) {
        if (preg_match('/^\s*(\d+)\s+.*\s+(Avail|Unavail)/', $line, $matches)) {
            $extension = $matches[1];
            $status = ($matches[2] === 'Avail') ? 'registered' : 'unregistered';

            $sip_info[$extension] = [
                'extension' => $extension,
                'status' => $status,
                'user_agent' => 'Unknown',
                'os' => 'Unknown',
                'ip' => null,
                'registered_at' => null
            ];
        }
    }

    // Parse contacts for user-agent
    $current_ext = null;
    foreach ($contacts_output as $line) {
        // Contact line: "2000/sip:2000@192.168.1.100:5060"
        if (preg_match('/^\s*(\d+)\/sip:.*@([^:]+):/', $line, $matches)) {
            $current_ext = $matches[1];
            if (isset($sip_info[$current_ext])) {
                $sip_info[$current_ext]['ip'] = $matches[2];
            }
        }

        // UserAgent line: "UserAgent: Groundwire 6.2.1 rv:2006 (iPhone; iOS 17.4.1)"
        if ($current_ext && preg_match('/UserAgent:\s*(.+)/', $line, $matches)) {
            $user_agent = trim($matches[1]);

            if (isset($sip_info[$current_ext])) {
                $sip_info[$current_ext]['user_agent'] = $user_agent;
                $sip_info[$current_ext]['os'] = detectOS($user_agent);
            }
        }

        // Expiry line: "Expiry: 300"
        if ($current_ext && preg_match('/Expiry:\s*(\d+)/', $line, $matches)) {
            if (isset($sip_info[$current_ext])) {
                $expiry = (int)$matches[1];
                $sip_info[$current_ext]['registered_at'] = date('c', time() - $expiry);
            }
        }
    }

    return $sip_info;
}

/**
 * Detect OS from SIP User-Agent string
 */
function detectOS($user_agent) {
    $user_agent_lower = strtolower($user_agent);

    if (preg_match('/iphone|ipad|ios/', $user_agent_lower)) {
        return 'iOS';
    } elseif (preg_match('/android/', $user_agent_lower)) {
        return 'Android';
    } elseif (preg_match('/windows|win32|win64/', $user_agent_lower)) {
        return 'Windows';
    } elseif (preg_match('/mac\s*os|macos|darwin/', $user_agent_lower)) {
        return 'macOS';
    } elseif (preg_match('/linux/', $user_agent_lower)) {
        return 'Linux';
    } else {
        return 'Unknown';
    }
}

/**
 * Detect SIP client name from User-Agent
 */
function detectSipClient($user_agent) {
    if (preg_match('/groundwire/i', $user_agent)) {
        return 'Groundwire';
    } elseif (preg_match('/zoiper/i', $user_agent)) {
        return 'Zoiper';
    } elseif (preg_match('/linphone/i', $user_agent)) {
        return 'Linphone';
    } elseif (preg_match('/bria|counterpath/i', $user_agent)) {
        return 'Bria';
    } elseif (preg_match('/microsip/i', $user_agent)) {
        return 'MicroSIP';
    } elseif (preg_match('/asterisk/i', $user_agent)) {
        return 'Asterisk PBX';
    } elseif (preg_match('/telephone/i', $user_agent)) {
        return 'Telephone';
    } elseif (preg_match('/sipnetic/i', $user_agent)) {
        return 'Sipnetic';
    } else {
        return 'Unknown';
    }
}

function handleLiveStatus($method) {
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    // Get SIP registration info first
    $sip_info = getSipRegistrationInfo();

    // Get queue status from Asterisk
    exec('sudo asterisk -rx "queue show" 2>&1', $output);

    $queues = [];
    $current_queue = null;

    foreach ($output as $line) {
        $line = trim($line);

        // Queue name line: "8000 has 0 calls (max unlimited) in 'ringall' strategy..."
        if (preg_match('/^(\d+)\s+has\s+(\d+)\s+calls.*\'(\w+)\'\s+strategy/', $line, $matches)) {
            if ($current_queue) {
                $queues[] = $current_queue;
            }

            $current_queue = [
                'queue' => $matches[1],
                'calls_waiting' => (int)$matches[2],
                'strategy' => $matches[3],
                'members' => []
            ];
        }

        // Member line: "PJSIP/2000 (ringinuse disabled) (dynamic) (Not in use) has taken no calls yet"
        if ($current_queue && preg_match('/^\s+(PJSIP\/(\d+)|Local\/.+)\s+.*\(([^)]+)\)\s+has\s+taken\s+(\d+)\s+calls/', $line, $matches)) {
            $interface = $matches[1];
            $extension = $matches[2] ?? null;
            $status = $matches[3];
            $calls_taken = (int)$matches[4];

            $member = [
                'interface' => $interface,
                'extension' => $extension,
                'status' => $status,
                'calls_taken' => $calls_taken,
                'sip_registered' => false,
                'user_agent' => 'Unknown',
                'os' => 'Unknown',
                'client' => 'Unknown',
                'ip' => null
            ];

            // Add SIP registration info if available
            if ($extension && isset($sip_info[$extension])) {
                $member['sip_registered'] = ($sip_info[$extension]['status'] === 'registered');
                $member['user_agent'] = $sip_info[$extension]['user_agent'];
                $member['os'] = $sip_info[$extension]['os'];
                $member['client'] = detectSipClient($sip_info[$extension]['user_agent']);
                $member['ip'] = $sip_info[$extension]['ip'];
                $member['registered_at'] = $sip_info[$extension]['registered_at'];
            }

            $current_queue['members'][] = $member;
        }
    }

    if ($current_queue) {
        $queues[] = $current_queue;
    }

    echo json_encode([
        'success' => true,
        'queues' => $queues,
        'timestamp' => date('c')
    ]);
}

/**
 * Get queue statistics
 */
function handleStatistics($method) {
    global $pdo;

    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    $queue_id = $_GET['queue_id'] ?? null;

    try {
        if ($queue_id) {
            // Statistics for specific queue
            $stmt = $pdo->prepare("
                SELECT * FROM queue_statistics
                WHERE queue_id = ?
                ORDER BY date DESC
                LIMIT 30
            ");
            $stmt->execute([$queue_id]);
        } else {
            // Statistics for all queues (today)
            $stmt = $pdo->query("
                SELECT qs.*, cq.queue_name
                FROM queue_statistics qs
                JOIN call_queues cq ON qs.queue_id = cq.id
                WHERE qs.date = CURDATE()
            ");
        }

        $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'statistics' => $stats,
            'timestamp' => date('c')
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

/**
 * Apply queue configuration to Asterisk
 */
function handleApplyConfig($method) {
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    $result = applyQueueConfig();

    echo json_encode([
        'success' => $result['success'],
        'message' => $result['message'],
        'output' => $result['output'] ?? '',
        'timestamp' => date('c')
    ]);
}

// Helper function
function applyQueueConfig() {
    global $pdo;

    try {
        // Get all queues
        $stmt = $pdo->query("SELECT * FROM call_queues WHERE enabled = 1");
        $queues = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Generate queues.conf
        $config = "; FlexPBX Call Queue Configuration\n";
        $config .= "; Generated: " . date('Y-m-d H:i:s') . "\n";
        $config .= "; DO NOT EDIT MANUALLY - Use FlexPBX Admin Panel\n\n";

        $config .= "[general]\n";
        $config .= "persistentmembers = yes\n";
        $config .= "autofill = yes\n";
        $config .= "monitor-type = MixMonitor\n";
        $config .= "shared_lastcall = yes\n\n";

        foreach ($queues as $queue) {
            $config .= "[{$queue['queue_number']}]\n";
            $config .= "strategy = {$queue['strategy']}\n";
            $config .= "timeout = {$queue['timeout']}\n";
            $config .= "retry = {$queue['retry']}\n";

            if ($queue['max_wait_time'] > 0) {
                $config .= "maxlen = {$queue['max_callers']}\n";
            }

            $config .= "announce-frequency = {$queue['announce_frequency']}\n";
            $config .= "announce-position = {$queue['announce_position']}\n";
            $config .= "announce-holdtime = {$queue['announce_holdtime']}\n";
            $config .= "musicclass = {$queue['music_class']}\n";

            if (!empty($queue['join_announcement'])) {
                $config .= "joinempty = yes\n";
            }

            if (!empty($queue['periodic_announce'])) {
                $config .= "periodic-announce = {$queue['periodic_announce']}\n";
                $config .= "periodic-announce-frequency = {$queue['periodic_announce_frequency']}\n";
            }

            $config .= "autopause = {$queue['auto_pause']}\n";

            if ($queue['autopause_delay'] > 0) {
                $config .= "autopausedelay = {$queue['autopause_delay']}\n";
            }

            if ($queue['wrap_up_time'] > 0) {
                $config .= "wrapuptime = {$queue['wrap_up_time']}\n";
            }

            $config .= "servicelevel = {$queue['service_level']}\n";

            // Get members for this queue
            $member_stmt = $pdo->prepare("SELECT * FROM queue_members WHERE queue_id = ? ORDER BY penalty ASC, member_extension ASC");
            $member_stmt->execute([$queue['id']]);
            $members = $member_stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($members as $member) {
                $interface = $member['member_type'] === 'extension'
                    ? "PJSIP/{$member['member_extension']}"
                    : "Local/{$member['member_extension']}@from-internal";

                $config .= "member => $interface";

                if ($member['penalty'] > 0) {
                    $config .= ",{$member['penalty']}";
                }

                if (!empty($member['member_name'])) {
                    $config .= ",{$member['member_name']}";
                }

                $config .= "\n";
            }

            $config .= "\n";
        }

        // Write configuration
        $config_file = '/etc/asterisk/queues.conf';
        file_put_contents($config_file, $config);

        // Set permissions
        exec("sudo chown asterisk:asterisk $config_file");
        exec("sudo chmod 640 $config_file");

        // Reload Asterisk queues
        exec('sudo asterisk -rx "queue reload all" 2>&1', $output, $return_code);

        return [
            'success' => $return_code === 0,
            'message' => $return_code === 0 ? 'Queue configuration applied' : 'Failed to apply configuration',
            'output' => implode("\n", $output)
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Error applying configuration: ' . $e->getMessage(),
            'output' => ''
        ];
    }
}

?>
