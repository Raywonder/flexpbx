<?php
/**
 * FlexPBX Call Center API
 * Manages queue operations, agent status, and supervisor functions
 */

header('Content-Type: application/json');
session_start();

// Load configuration
$config = require_once(__DIR__ . '/config.php');

// Database connection
try {
    $pdo = new PDO(
        "mysql:host={$config['db_host']};dbname={$config['db_name']}",
        $config['db_user'],
        $config['db_password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

// Load AMI class
require_once(__DIR__ . '/../includes/AsteriskManager.php');

// Authentication check
function checkAuth($pdo, $requiredRole = null) {
    if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_logged_in'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Authentication required']);
        exit;
    }

    if ($requiredRole) {
        $userRole = $_SESSION['admin_role'] ?? $_SESSION['user_role'] ?? 'user';
        $allowedRoles = is_array($requiredRole) ? $requiredRole : [$requiredRole];

        if (!in_array($userRole, $allowedRoles)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Insufficient permissions']);
            exit;
        }
    }
}

// Get AMI connection
function getAMI() {
    try {
        $ami = new AsteriskManager('localhost', 5038, 'admin', 'admin');
        $ami->connect();
        return $ami;
    } catch (Exception $e) {
        throw new Exception("AMI connection failed: " . $e->getMessage());
    }
}

// Get request method and path
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

try {
    // Route handling
    switch ($path) {

        // ============================================================
        // QUEUE OPERATIONS
        // ============================================================

        case 'queues/list':
            checkAuth($pdo);

            if ($method === 'GET') {
                $ami = getAMI();
                $queues = $ami->getQueueStatus();

                // Enhance with database info
                foreach ($queues as $name => &$queue) {
                    $stmt = $pdo->prepare("SELECT * FROM call_queues WHERE queue_name = ?");
                    $stmt->execute([$name]);
                    $dbQueue = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($dbQueue) {
                        $queue['description'] = $dbQueue['description'];
                        $queue['sla_seconds'] = $dbQueue['sla_seconds'];
                        $queue['department'] = $dbQueue['department'];
                    }
                }

                echo json_encode([
                    'success' => true,
                    'queues' => array_values($queues)
                ]);
            }
            break;

        case 'queues/statistics':
            checkAuth($pdo);

            if ($method === 'GET') {
                $queueName = $_GET['queue'] ?? null;
                $ami = getAMI();

                $queues = $ami->getQueueStatus($queueName);
                $statistics = [];

                foreach ($queues as $name => $queue) {
                    $members = $ami->getQueueMembers($name);

                    $availableAgents = 0;
                    $onCallAgents = 0;
                    $pausedAgents = 0;

                    foreach ($members as $member) {
                        if ($member['paused'] == 1) {
                            $pausedAgents++;
                        } elseif ($member['in_call'] == 1) {
                            $onCallAgents++;
                        } else {
                            $availableAgents++;
                        }
                    }

                    $slaCompliance = 0;
                    if ($queue['completed'] > 0) {
                        $slaCompliance = ($queue['service_level_perf'] / $queue['completed']) * 100;
                    }

                    $abandonRate = 0;
                    $totalCalls = $queue['completed'] + $queue['abandoned'];
                    if ($totalCalls > 0) {
                        $abandonRate = ($queue['abandoned'] / $totalCalls) * 100;
                    }

                    $statistics[] = [
                        'queue_name' => $name,
                        'calls_waiting' => $queue['calls'],
                        'avg_hold_time' => $queue['holdtime'],
                        'avg_talk_time' => $queue['talktime'],
                        'calls_completed' => $queue['completed'],
                        'calls_abandoned' => $queue['abandoned'],
                        'sla_compliance' => round($slaCompliance, 2),
                        'abandon_rate' => round($abandonRate, 2),
                        'agents_total' => count($members),
                        'agents_available' => $availableAgents,
                        'agents_on_call' => $onCallAgents,
                        'agents_paused' => $pausedAgents,
                        'strategy' => $queue['strategy']
                    ];
                }

                echo json_encode([
                    'success' => true,
                    'statistics' => $queueName ? $statistics[0] : $statistics
                ]);
            }
            break;

        case 'queues/members':
            checkAuth($pdo);

            if ($method === 'GET') {
                $queueName = $_GET['queue'] ?? null;

                if (!$queueName) {
                    throw new Exception("Queue name required");
                }

                $ami = getAMI();
                $members = $ami->getQueueMembers($queueName);

                // Enhance with database info
                foreach ($members as &$member) {
                    // Extract extension from interface (e.g., PJSIP/2000 -> 2000)
                    preg_match('/\/(\d+)/', $member['location'], $matches);
                    $extension = $matches[1] ?? null;

                    if ($extension) {
                        $stmt = $pdo->prepare("SELECT * FROM users WHERE extension = ?");
                        $stmt->execute([$extension]);
                        $user = $stmt->fetch(PDO::FETCH_ASSOC);

                        if ($user) {
                            $member['user_id'] = $user['id'];
                            $member['username'] = $user['username'];
                            $member['full_name'] = $user['full_name'];
                            $member['email'] = $user['email'];
                        }

                        $member['extension'] = $extension;
                    }

                    // Get status text
                    $member['status_text'] = $member['paused'] == 1 ? 'Paused' :
                                            ($member['in_call'] == 1 ? 'On Call' : 'Available');
                }

                echo json_encode([
                    'success' => true,
                    'queue' => $queueName,
                    'members' => $members
                ]);
            }
            break;

        case 'queues/add-member':
            checkAuth($pdo, ['admin', 'superadmin', 'supervisor']);

            if ($method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                $queue = $data['queue'] ?? null;
                $extension = $data['extension'] ?? null;
                $interface = $data['interface'] ?? "PJSIP/{$extension}";
                $memberName = $data['member_name'] ?? '';
                $penalty = $data['penalty'] ?? 0;

                if (!$queue || !$extension) {
                    throw new Exception("Queue and extension required");
                }

                $ami = getAMI();
                $result = $ami->addQueueMember($queue, $interface, $memberName, $penalty);

                // Log to database
                $stmt = $pdo->prepare("
                    INSERT INTO queue_member_log (queue_name, extension, action, action_by, created_at)
                    VALUES (?, ?, 'added', ?, NOW())
                ");
                $stmt->execute([$queue, $extension, $_SESSION['username'] ?? 'system']);

                echo json_encode([
                    'success' => true,
                    'message' => 'Member added to queue',
                    'result' => $result
                ]);
            }
            break;

        case 'queues/remove-member':
            checkAuth($pdo, ['admin', 'superadmin', 'supervisor']);

            if ($method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                $queue = $data['queue'] ?? null;
                $extension = $data['extension'] ?? null;
                $interface = $data['interface'] ?? "PJSIP/{$extension}";

                if (!$queue || !$extension) {
                    throw new Exception("Queue and extension required");
                }

                $ami = getAMI();
                $result = $ami->removeQueueMember($queue, $interface);

                // Log to database
                $stmt = $pdo->prepare("
                    INSERT INTO queue_member_log (queue_name, extension, action, action_by, created_at)
                    VALUES (?, ?, 'removed', ?, NOW())
                ");
                $stmt->execute([$queue, $extension, $_SESSION['username'] ?? 'system']);

                echo json_encode([
                    'success' => true,
                    'message' => 'Member removed from queue',
                    'result' => $result
                ]);
            }
            break;

        // ============================================================
        // AGENT OPERATIONS
        // ============================================================

        case 'agent/status':
            checkAuth($pdo);

            if ($method === 'GET') {
                $extension = $_GET['extension'] ?? $_SESSION['extension'] ?? null;

                if (!$extension) {
                    throw new Exception("Extension required");
                }

                // Get current status from database
                $stmt = $pdo->prepare("
                    SELECT * FROM agent_status
                    WHERE extension = ?
                    ORDER BY updated_at DESC
                    LIMIT 1
                ");
                $stmt->execute([$extension]);
                $status = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$status) {
                    // Create default status
                    $stmt = $pdo->prepare("
                        INSERT INTO agent_status (extension, status, reason, updated_at)
                        VALUES (?, 'offline', '', NOW())
                    ");
                    $stmt->execute([$extension]);

                    $status = [
                        'extension' => $extension,
                        'status' => 'offline',
                        'reason' => '',
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                }

                // Get queue memberships
                $ami = getAMI();
                $allQueues = $ami->getQueueStatus();
                $memberQueues = [];

                foreach ($allQueues as $queueName => $queue) {
                    $members = $ami->getQueueMembers($queueName);
                    foreach ($members as $member) {
                        if (strpos($member['location'], $extension) !== false) {
                            $memberQueues[] = [
                                'queue_name' => $queueName,
                                'paused' => $member['paused'],
                                'calls_taken' => $member['calls_taken']
                            ];
                        }
                    }
                }

                $status['queues'] = $memberQueues;

                echo json_encode([
                    'success' => true,
                    'agent_status' => $status
                ]);
            }
            break;

        case 'agent/set-status':
            checkAuth($pdo);

            if ($method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                $extension = $data['extension'] ?? $_SESSION['extension'] ?? null;
                $status = $data['status'] ?? null; // available, break, lunch, meeting, offline
                $reason = $data['reason'] ?? '';

                if (!$extension || !$status) {
                    throw new Exception("Extension and status required");
                }

                // Update database
                $stmt = $pdo->prepare("
                    INSERT INTO agent_status (extension, status, reason, updated_at)
                    VALUES (?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE
                        status = VALUES(status),
                        reason = VALUES(reason),
                        updated_at = NOW()
                ");
                $stmt->execute([$extension, $status, $reason]);

                // Pause/unpause in all queues based on status
                $ami = getAMI();
                $interface = "PJSIP/{$extension}";
                $shouldPause = !in_array($status, ['available', 'ready']);

                // Get all queues this agent is in
                $allQueues = $ami->getQueueStatus();
                foreach ($allQueues as $queueName => $queue) {
                    $ami->pauseQueueMember($queueName, $interface, $shouldPause, $reason);
                }

                echo json_encode([
                    'success' => true,
                    'message' => 'Agent status updated',
                    'status' => $status,
                    'paused' => $shouldPause
                ]);
            }
            break;

        case 'agent/statistics':
            checkAuth($pdo);

            if ($method === 'GET') {
                $extension = $_GET['extension'] ?? $_SESSION['extension'] ?? null;
                $period = $_GET['period'] ?? 'today'; // today, week, month

                if (!$extension) {
                    throw new Exception("Extension required");
                }

                // Calculate date range
                $startDate = date('Y-m-d 00:00:00');
                switch ($period) {
                    case 'week':
                        $startDate = date('Y-m-d 00:00:00', strtotime('-7 days'));
                        break;
                    case 'month':
                        $startDate = date('Y-m-d 00:00:00', strtotime('-30 days'));
                        break;
                }

                // Get call statistics
                $stmt = $pdo->prepare("
                    SELECT
                        COUNT(*) as total_calls,
                        SUM(CASE WHEN disposition = 'ANSWERED' THEN 1 ELSE 0 END) as answered_calls,
                        AVG(CASE WHEN disposition = 'ANSWERED' THEN duration ELSE NULL END) as avg_duration,
                        AVG(CASE WHEN disposition = 'ANSWERED' THEN billsec ELSE NULL END) as avg_talk_time,
                        SUM(billsec) as total_talk_time
                    FROM cdr
                    WHERE dst = ? AND calldate >= ?
                ");
                $stmt->execute([$extension, $startDate]);
                $stats = $stmt->fetch(PDO::FETCH_ASSOC);

                // Get disposition data
                $stmt = $pdo->prepare("
                    SELECT
                        COUNT(*) as count,
                        disposition_code,
                        disposition_notes
                    FROM call_dispositions
                    WHERE extension = ? AND created_at >= ?
                    GROUP BY disposition_code
                ");
                $stmt->execute([$extension, $startDate]);
                $dispositions = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode([
                    'success' => true,
                    'period' => $period,
                    'statistics' => [
                        'total_calls' => (int)$stats['total_calls'],
                        'answered_calls' => (int)$stats['answered_calls'],
                        'avg_duration' => round($stats['avg_duration'] ?? 0, 2),
                        'avg_talk_time' => round($stats['avg_talk_time'] ?? 0, 2),
                        'total_talk_time' => (int)$stats['total_talk_time'],
                        'dispositions' => $dispositions
                    ]
                ]);
            }
            break;

        case 'agent/pause':
            checkAuth($pdo);

            if ($method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                $extension = $data['extension'] ?? $_SESSION['extension'] ?? null;
                $queue = $data['queue'] ?? null;
                $paused = $data['paused'] ?? true;
                $reason = $data['reason'] ?? '';

                if (!$extension) {
                    throw new Exception("Extension required");
                }

                $ami = getAMI();
                $interface = "PJSIP/{$extension}";

                if ($queue) {
                    // Pause in specific queue
                    $result = $ami->pauseQueueMember($queue, $interface, $paused, $reason);
                } else {
                    // Pause in all queues
                    $allQueues = $ami->getQueueStatus();
                    foreach ($allQueues as $queueName => $queueData) {
                        $ami->pauseQueueMember($queueName, $interface, $paused, $reason);
                    }
                    $result = ['Response' => 'Success'];
                }

                echo json_encode([
                    'success' => true,
                    'message' => $paused ? 'Agent paused' : 'Agent unpaused',
                    'result' => $result
                ]);
            }
            break;

        // ============================================================
        // SUPERVISOR OPERATIONS
        // ============================================================

        case 'supervisor/agents':
            checkAuth($pdo, ['admin', 'superadmin', 'supervisor']);

            if ($method === 'GET') {
                $department = $_GET['department'] ?? null;

                // Get all agents
                $sql = "
                    SELECT
                        u.id, u.username, u.full_name, u.email, u.extension,
                        ast.status, ast.reason, ast.updated_at,
                        GROUP_CONCAT(qm.queue_name) as queues
                    FROM users u
                    LEFT JOIN agent_status ast ON u.extension = ast.extension
                    LEFT JOIN queue_members qm ON u.extension = qm.extension
                    WHERE u.role IN ('agent', 'supervisor')
                ";

                if ($department) {
                    $sql .= " AND u.department = ?";
                }

                $sql .= " GROUP BY u.id ORDER BY u.full_name";

                $stmt = $pdo->prepare($sql);
                if ($department) {
                    $stmt->execute([$department]);
                } else {
                    $stmt->execute();
                }

                $agents = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Get real-time status from AMI
                $ami = getAMI();
                $channels = $ami->getActiveChannels();

                foreach ($agents as &$agent) {
                    $agent['on_call'] = false;
                    $agent['current_call'] = null;

                    foreach ($channels as $channel) {
                        if (strpos($channel['channel'], $agent['extension']) !== false) {
                            $agent['on_call'] = true;
                            $agent['current_call'] = [
                                'channel' => $channel['channel'],
                                'caller_id' => $channel['caller_id_num'],
                                'duration' => $channel['duration'],
                                'state' => $channel['channel_state_desc']
                            ];
                            break;
                        }
                    }
                }

                echo json_encode([
                    'success' => true,
                    'agents' => $agents
                ]);
            }
            break;

        case 'supervisor/listen':
            checkAuth($pdo, ['admin', 'superadmin', 'supervisor']);

            if ($method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                $supervisorExt = $data['supervisor_extension'] ?? $_SESSION['extension'] ?? null;
                $targetChannel = $data['target_channel'] ?? null;

                if (!$supervisorExt || !$targetChannel) {
                    throw new Exception("Supervisor extension and target channel required");
                }

                $ami = getAMI();
                $spyChannel = "PJSIP/{$supervisorExt}";
                $result = $ami->chanSpy($spyChannel, $targetChannel, 'q');

                echo json_encode([
                    'success' => true,
                    'message' => 'Listening initiated',
                    'result' => $result
                ]);
            }
            break;

        case 'supervisor/whisper':
            checkAuth($pdo, ['admin', 'superadmin', 'supervisor']);

            if ($method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                $supervisorExt = $data['supervisor_extension'] ?? $_SESSION['extension'] ?? null;
                $targetChannel = $data['target_channel'] ?? null;

                if (!$supervisorExt || !$targetChannel) {
                    throw new Exception("Supervisor extension and target channel required");
                }

                $ami = getAMI();
                $spyChannel = "PJSIP/{$supervisorExt}";
                $result = $ami->whisper($spyChannel, $targetChannel);

                echo json_encode([
                    'success' => true,
                    'message' => 'Whisper mode initiated',
                    'result' => $result
                ]);
            }
            break;

        case 'supervisor/barge':
            checkAuth($pdo, ['admin', 'superadmin', 'supervisor']);

            if ($method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                $supervisorExt = $data['supervisor_extension'] ?? $_SESSION['extension'] ?? null;
                $targetChannel = $data['target_channel'] ?? null;

                if (!$supervisorExt || !$targetChannel) {
                    throw new Exception("Supervisor extension and target channel required");
                }

                $ami = getAMI();
                $spyChannel = "PJSIP/{$supervisorExt}";
                $result = $ami->barge($spyChannel, $targetChannel);

                echo json_encode([
                    'success' => true,
                    'message' => 'Barge mode initiated',
                    'result' => $result
                ]);
            }
            break;

        case 'supervisor/force-status':
            checkAuth($pdo, ['admin', 'superadmin', 'supervisor']);

            if ($method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                $extension = $data['extension'] ?? null;
                $status = $data['status'] ?? null;
                $reason = $data['reason'] ?? 'Forced by supervisor';

                if (!$extension || !$status) {
                    throw new Exception("Extension and status required");
                }

                // Update database
                $stmt = $pdo->prepare("
                    INSERT INTO agent_status (extension, status, reason, updated_at)
                    VALUES (?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE
                        status = VALUES(status),
                        reason = VALUES(reason),
                        updated_at = NOW()
                ");
                $stmt->execute([$extension, $status, $reason]);

                // Pause/unpause in queues
                $ami = getAMI();
                $interface = "PJSIP/{$extension}";
                $shouldPause = !in_array($status, ['available', 'ready']);

                $allQueues = $ami->getQueueStatus();
                foreach ($allQueues as $queueName => $queue) {
                    $ami->pauseQueueMember($queueName, $interface, $shouldPause, $reason);
                }

                // Log action
                $stmt = $pdo->prepare("
                    INSERT INTO supervisor_actions
                    (supervisor_id, action, target_extension, details, created_at)
                    VALUES (?, 'force_status', ?, ?, NOW())
                ");
                $stmt->execute([
                    $_SESSION['user_id'] ?? 0,
                    $extension,
                    json_encode(['status' => $status, 'reason' => $reason])
                ]);

                echo json_encode([
                    'success' => true,
                    'message' => 'Agent status forced'
                ]);
            }
            break;

        // ============================================================
        // CALL DISPOSITION
        // ============================================================

        case 'disposition/submit':
            checkAuth($pdo);

            if ($method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                $callId = $data['call_id'] ?? null;
                $extension = $data['extension'] ?? $_SESSION['extension'] ?? null;
                $dispositionCode = $data['disposition_code'] ?? null;
                $notes = $data['notes'] ?? '';
                $followUp = $data['follow_up'] ?? false;
                $followUpDate = $data['follow_up_date'] ?? null;

                if (!$extension || !$dispositionCode) {
                    throw new Exception("Extension and disposition code required");
                }

                $stmt = $pdo->prepare("
                    INSERT INTO call_dispositions
                    (call_id, extension, disposition_code, disposition_notes, follow_up, follow_up_date, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$callId, $extension, $dispositionCode, $notes, $followUp, $followUpDate]);

                echo json_encode([
                    'success' => true,
                    'message' => 'Call disposition saved',
                    'disposition_id' => $pdo->lastInsertId()
                ]);
            }
            break;

        case 'disposition/codes':
            checkAuth($pdo);

            if ($method === 'GET') {
                $stmt = $pdo->query("
                    SELECT * FROM disposition_codes
                    WHERE active = 1
                    ORDER BY category, display_order
                ");
                $codes = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode([
                    'success' => true,
                    'disposition_codes' => $codes
                ]);
            }
            break;

        // ============================================================
        // ACTIVE CALLS
        // ============================================================

        case 'calls/active':
            checkAuth($pdo);

            if ($method === 'GET') {
                $ami = getAMI();
                $channels = $ami->getActiveChannels();

                // Filter and enhance channel data
                $activeCalls = [];
                foreach ($channels as $channel) {
                    if ($channel['channel_state_desc'] === 'Up') {
                        $activeCalls[] = [
                            'channel' => $channel['channel'],
                            'caller_id' => $channel['caller_id_num'],
                            'caller_name' => $channel['caller_id_name'],
                            'extension' => $channel['extension'],
                            'duration' => $channel['duration'],
                            'context' => $channel['context'],
                            'uniqueid' => $channel['uniqueid']
                        ];
                    }
                }

                echo json_encode([
                    'success' => true,
                    'active_calls' => $activeCalls,
                    'count' => count($activeCalls)
                ]);
            }
            break;

        case 'calls/hangup':
            checkAuth($pdo, ['admin', 'superadmin', 'supervisor']);

            if ($method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);
                $channel = $data['channel'] ?? null;

                if (!$channel) {
                    throw new Exception("Channel required");
                }

                $ami = getAMI();
                $result = $ami->hangup($channel);

                echo json_encode([
                    'success' => true,
                    'message' => 'Call terminated',
                    'result' => $result
                ]);
            }
            break;

        // ============================================================
        // QUEUE MANAGEMENT (CRUD)
        // ============================================================

        case 'queues/create':
            checkAuth($pdo, ['admin', 'superadmin']);

            if ($method === 'POST') {
                $data = json_decode(file_get_contents('php://input'), true);

                $stmt = $pdo->prepare("
                    INSERT INTO call_queues
                    (queue_name, description, strategy, timeout, retry, wrapuptime,
                     maxlen, announce_frequency, min_announce_frequency, announce_holdtime,
                     announce_position, periodic_announce, periodic_announce_frequency,
                     relative_periodic_announce, random_periodic_announce, announce_round_seconds,
                     monitor_type, monitor_format, queue_youarenext, queue_thereare,
                     queue_callswaiting, queue_holdtime, queue_minutes, queue_seconds,
                     queue_thankyou, queue_reporthold, joinempty, leavewhenempty,
                     ringinuse, memberdelay, weight, timeoutrestart, servicelevel,
                     department, sla_seconds, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");

                $stmt->execute([
                    $data['queue_name'],
                    $data['description'] ?? '',
                    $data['strategy'] ?? 'ringall',
                    $data['timeout'] ?? 30,
                    $data['retry'] ?? 5,
                    $data['wrapuptime'] ?? 15,
                    $data['maxlen'] ?? 0,
                    $data['announce_frequency'] ?? 0,
                    $data['min_announce_frequency'] ?? 60,
                    $data['announce_holdtime'] ?? 'yes',
                    $data['announce_position'] ?? 'yes',
                    $data['periodic_announce'] ?? '',
                    $data['periodic_announce_frequency'] ?? 0,
                    $data['relative_periodic_announce'] ?? 'yes',
                    $data['random_periodic_announce'] ?? 'no',
                    $data['announce_round_seconds'] ?? 0,
                    $data['monitor_type'] ?? 'MixMonitor',
                    $data['monitor_format'] ?? 'wav',
                    $data['queue_youarenext'] ?? 'queue-youarenext',
                    $data['queue_thereare'] ?? 'queue-thereare',
                    $data['queue_callswaiting'] ?? 'queue-callswaiting',
                    $data['queue_holdtime'] ?? 'queue-holdtime',
                    $data['queue_minutes'] ?? 'queue-minutes',
                    $data['queue_seconds'] ?? 'queue-seconds',
                    $data['queue_thankyou'] ?? 'queue-thankyou',
                    $data['queue_reporthold'] ?? 'queue-reporthold',
                    $data['joinempty'] ?? 'yes',
                    $data['leavewhenempty'] ?? 'no',
                    $data['ringinuse'] ?? 'yes',
                    $data['memberdelay'] ?? 0,
                    $data['weight'] ?? 0,
                    $data['timeoutrestart'] ?? 'no',
                    $data['servicelevel'] ?? 60,
                    $data['department'] ?? '',
                    $data['sla_seconds'] ?? 60
                ]);

                echo json_encode([
                    'success' => true,
                    'message' => 'Queue created',
                    'queue_id' => $pdo->lastInsertId()
                ]);
            }
            break;

        default:
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'Endpoint not found',
                'path' => $path
            ]);
            break;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
