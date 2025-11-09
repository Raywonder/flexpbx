<?php
/**
 * FlexPBX Call Parking API
 * Manage parking lots, view parked calls, retrieve parking statistics
 *
 * @version 1.0.0
 * @package FlexPBX
 * @subpackage Modules
 */

require_once __DIR__ . '/../../../public_html/api/auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

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
require_once __DIR__ . '/../../../public_html/api/config.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

switch ($path) {
    case '':
    case 'list':
        handleListParkingLots($method);
        break;

    case 'create':
        handleCreateParkingLot($method);
        break;

    case 'update':
        handleUpdateParkingLot($method);
        break;

    case 'delete':
        handleDeleteParkingLot($method);
        break;

    case 'parked-calls':
        handleParkedCalls($method);
        break;

    case 'park-call':
        handleParkCall($method);
        break;

    case 'retrieve-call':
        handleRetrieveCall($method);
        break;

    case 'history':
        handleParkingHistory($method);
        break;

    case 'statistics':
        handleParkingStatistics($method);
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
 * List all parking lots
 */
function handleListParkingLots($method) {
    global $pdo;

    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    try {
        $stmt = $pdo->query("SELECT * FROM parking_lots ORDER BY id ASC");
        $lots = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'parking_lots' => $lots,
            'total' => count($lots),
            'timestamp' => date('c')
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

/**
 * Create new parking lot
 */
function handleCreateParkingLot($method) {
    global $pdo;

    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    // Validate required fields
    $required = ['name', 'park_ext', 'park_pos'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Field '$field' is required"]);
            return;
        }
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO parking_lots
            (name, park_ext, park_pos, park_time, comeback_to_origin, comeback_context,
             comeback_dialtime, parked_play, parked_music_class, enabled)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $data['name'],
            $data['park_ext'],
            $data['park_pos'],
            $data['park_time'] ?? 300,
            isset($data['comeback_to_origin']) ? (int)$data['comeback_to_origin'] : 1,
            $data['comeback_context'] ?? 'from-internal',
            $data['comeback_dialtime'] ?? 30,
            $data['parked_play'] ?? 'caller',
            $data['parked_music_class'] ?? 'default',
            isset($data['enabled']) ? (int)$data['enabled'] : 1
        ]);

        $lot_id = $pdo->lastInsertId();

        // Apply configuration to Asterisk
        applyParkingConfig();

        echo json_encode([
            'success' => true,
            'message' => 'Parking lot created successfully',
            'lot_id' => $lot_id,
            'timestamp' => date('c')
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

/**
 * Update parking lot
 */
function handleUpdateParkingLot($method) {
    global $pdo;

    if ($method !== 'PUT') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    $lot_id = $_GET['id'] ?? null;
    if (!$lot_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Parking lot ID required']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    try {
        $fields = [];
        $values = [];

        $allowed_fields = [
            'name', 'park_ext', 'park_pos', 'park_time', 'comeback_to_origin',
            'comeback_context', 'comeback_dialtime', 'parked_play', 'parked_music_class', 'enabled'
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

        $values[] = $lot_id;
        $sql = "UPDATE parking_lots SET " . implode(', ', $fields) . " WHERE id = ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);

        // Apply configuration to Asterisk
        applyParkingConfig();

        echo json_encode([
            'success' => true,
            'message' => 'Parking lot updated successfully',
            'timestamp' => date('c')
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

/**
 * Delete parking lot
 */
function handleDeleteParkingLot($method) {
    global $pdo;

    if ($method !== 'DELETE') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    $lot_id = $_GET['id'] ?? null;
    if (!$lot_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Parking lot ID required']);
        return;
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM parking_lots WHERE id = ?");
        $stmt->execute([$lot_id]);

        // Apply configuration to Asterisk
        applyParkingConfig();

        echo json_encode([
            'success' => true,
            'message' => 'Parking lot deleted successfully',
            'timestamp' => date('c')
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

/**
 * Get currently parked calls (real-time from Asterisk)
 */
function handleParkedCalls($method) {
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    // Query Asterisk for parked calls
    exec('sudo asterisk -rx "parkedcalls show" 2>&1', $output, $return_code);

    $parked_calls = [];
    $in_list = false;

    foreach ($output as $line) {
        $line = trim($line);

        // Skip header lines
        if (strpos($line, 'Num') === 0 || strpos($line, '---') === 0 || empty($line)) {
            continue;
        }

        // Parse parked call line
        // Format: Num    Channel          Timeout Remaining   CallerID
        // Example: 701    SIP/2000-0001    45                 "John" <2000>
        if (preg_match('/^(\d+)\s+(\S+)\s+(\d+)\s+(.+)$/', $line, $matches)) {
            $parked_calls[] = [
                'space' => $matches[1],
                'channel' => $matches[2],
                'timeout_remaining' => (int)$matches[3],
                'callerid' => trim($matches[4])
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'parked_calls' => $parked_calls,
        'total' => count($parked_calls),
        'timestamp' => date('c')
    ]);
}

/**
 * Park a call (originate and park)
 */
function handleParkCall($method) {
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    $channel = $data['channel'] ?? '';
    if (empty($channel)) {
        http_response_code(400);
        echo json_encode(['error' => 'Channel required']);
        return;
    }

    // Execute park command via AMI or CLI
    $cmd = "sudo asterisk -rx 'channel redirect $channel park-dial,s,1'";
    exec($cmd . ' 2>&1', $output, $return_code);

    echo json_encode([
        'success' => $return_code === 0,
        'message' => $return_code === 0 ? 'Call parked successfully' : 'Failed to park call',
        'output' => implode("\n", $output),
        'timestamp' => date('c')
    ]);
}

/**
 * Retrieve parked call
 */
function handleRetrieveCall($method) {
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    $parking_space = $data['parking_space'] ?? '';
    $extension = $data['extension'] ?? '';

    if (empty($parking_space) || empty($extension)) {
        http_response_code(400);
        echo json_encode(['error' => 'Parking space and extension required']);
        return;
    }

    // Originate call to retrieve parked call
    $cmd = "sudo asterisk -rx 'channel originate Local/$extension@from-internal extension $parking_space@parkedcalls'";
    exec($cmd . ' 2>&1', $output, $return_code);

    echo json_encode([
        'success' => $return_code === 0,
        'message' => $return_code === 0 ? 'Call retrieved successfully' : 'Failed to retrieve call',
        'output' => implode("\n", $output),
        'timestamp' => date('c')
    ]);
}

/**
 * Get parking history
 */
function handleParkingHistory($method) {
    global $pdo;

    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

    try {
        $stmt = $pdo->prepare("
            SELECT ph.*, pl.name as lot_name
            FROM parking_history ph
            LEFT JOIN parking_lots pl ON ph.parking_lot_id = pl.id
            ORDER BY ph.park_time DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$limit, $offset]);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get total count
        $count_stmt = $pdo->query("SELECT COUNT(*) FROM parking_history");
        $total = $count_stmt->fetchColumn();

        echo json_encode([
            'success' => true,
            'history' => $history,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'timestamp' => date('c')
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

/**
 * Get parking statistics
 */
function handleParkingStatistics($method) {
    global $pdo;

    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    try {
        // Total parks today
        $stmt = $pdo->query("
            SELECT COUNT(*) as total_today
            FROM parking_history
            WHERE DATE(park_time) = CURDATE()
        ");
        $total_today = $stmt->fetchColumn();

        // Average park duration
        $stmt = $pdo->query("
            SELECT AVG(duration_parked) as avg_duration
            FROM parking_history
            WHERE duration_parked > 0
        ");
        $avg_duration = $stmt->fetchColumn();

        // Timeout rate
        $stmt = $pdo->query("
            SELECT
                SUM(timeout) as timeouts,
                COUNT(*) as total
            FROM parking_history
            WHERE park_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        $timeout_data = $stmt->fetch(PDO::FETCH_ASSOC);
        $timeout_rate = $timeout_data['total'] > 0
            ? round(($timeout_data['timeouts'] / $timeout_data['total']) * 100, 2)
            : 0;

        // Most used parking spaces
        $stmt = $pdo->query("
            SELECT parking_space, COUNT(*) as usage_count
            FROM parking_history
            WHERE park_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY parking_space
            ORDER BY usage_count DESC
            LIMIT 5
        ");
        $popular_spaces = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'statistics' => [
                'total_today' => $total_today,
                'avg_duration_seconds' => round($avg_duration ?? 0),
                'avg_duration_formatted' => formatDuration(round($avg_duration ?? 0)),
                'timeout_rate' => $timeout_rate,
                'popular_spaces' => $popular_spaces
            ],
            'timestamp' => date('c')
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

/**
 * Apply parking configuration to Asterisk
 */
function handleApplyConfig($method) {
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    $result = applyParkingConfig();

    echo json_encode([
        'success' => $result['success'],
        'message' => $result['message'],
        'output' => $result['output'],
        'timestamp' => date('c')
    ]);
}

// Helper Functions

function applyParkingConfig() {
    global $pdo;

    try {
        // Get all parking lots
        $stmt = $pdo->query("SELECT * FROM parking_lots WHERE enabled = 1");
        $lots = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Generate res_parking.conf
        $config = "; FlexPBX Call Parking Configuration\n";
        $config .= "; Generated: " . date('Y-m-d H:i:s') . "\n";
        $config .= "; DO NOT EDIT MANUALLY - Use FlexPBX Admin Panel\n\n";

        foreach ($lots as $lot) {
            $config .= "[{$lot['name']}]\n";
            $config .= "type=parking_lot\n";
            $config .= "parkext={$lot['park_ext']}\n";
            $config .= "parkpos={$lot['park_pos']}\n";
            $config .= "parkingtime={$lot['park_time']}\n";
            $config .= "comebacktoorigin=" . ($lot['comeback_to_origin'] ? 'yes' : 'no') . "\n";
            $config .= "comebackcontext={$lot['comeback_context']}\n";
            $config .= "comebackdialtime={$lot['comeback_dialtime']}\n";
            $config .= "parkedplay={$lot['parked_play']}\n";
            $config .= "parkedmusicclass={$lot['parked_music_class']}\n\n";
        }

        // Write configuration
        $config_file = '/etc/asterisk/res_parking.conf';
        file_put_contents($config_file, $config);

        // Set permissions
        exec("sudo chown asterisk:asterisk $config_file");
        exec("sudo chmod 640 $config_file");

        // Reload Asterisk parking module
        exec('sudo asterisk -rx "module reload res_parking.so" 2>&1', $output, $return_code);

        return [
            'success' => $return_code === 0,
            'message' => $return_code === 0 ? 'Parking configuration applied' : 'Failed to apply configuration',
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

function formatDuration($seconds) {
    if ($seconds < 60) {
        return $seconds . 's';
    } elseif ($seconds < 3600) {
        return floor($seconds / 60) . 'm ' . ($seconds % 60) . 's';
    } else {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        return $hours . 'h ' . $minutes . 'm';
    }
}

function checkAuth() {
    session_start();
    return [
        'authenticated' => isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true,
        'username' => $_SESSION['username'] ?? null
    ];
}
?>
