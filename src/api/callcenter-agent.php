<?php
/**
 * FlexPBX Call Center Agent API
 * Complete call center functionality per user with role-based access
 */

header('Content-Type: application/json');

session_start();
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$extension = $_SESSION['user_extension'] ?? null;
$user_role = $_SESSION['user_role'] ?? 'user';

if (!$extension) {
    echo json_encode(['success' => false, 'error' => 'No extension found']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'status';

// Wrap-up codes configuration
$wrap_up_codes = [
    'RESOLVED' => 'Issue Resolved',
    'CALLBACK' => 'Callback Required',
    'ESCALATED' => 'Escalated to Supervisor',
    'INFO_ONLY' => 'Information Only',
    'VOICEMAIL' => 'Left Voicemail',
    'NO_ANSWER' => 'Customer No Answer',
    'WRONG_NUMBER' => 'Wrong Number',
    'SPAM' => 'Spam/Unwanted',
    'TRANSFERRED' => 'Transferred to Another Department',
    'TECHNICAL' => 'Technical Issue',
    'BILLING' => 'Billing Issue',
    'SALES' => 'Sales Inquiry',
    'SUPPORT' => 'Support Request',
    'OTHER' => 'Other'
];

/**
 * Get agent status in all queues
 */
function getAgentStatus($extension) {
    $status = [
        'extension' => $extension,
        'queues' => [],
        'logged_in' => false,
        'available' => false,
        'paused' => false,
        'in_call' => false,
        'calls_taken_today' => 0,
        'total_calls_taken' => 0,
        'avg_talk_time' => 0,
        'status_time' => time()
    ];

    // Get queue membership from Asterisk
    exec('sudo asterisk -rx "queue show" 2>&1', $output);

    $current_queue = null;
    foreach ($output as $line) {
        // Queue name line
        if (preg_match('/^(\w+)\s+has\s+(\d+)\s+calls/', $line, $matches)) {
            $current_queue = $matches[1];
            continue;
        }

        // Member line for this extension
        if ($current_queue && preg_match('/PJSIP\/' . preg_quote($extension) . '\s.*\(([^)]+)\)/', $line, $matches)) {
            $member_status = $matches[1];

            // Parse status
            $is_available = (strpos($member_status, 'Not in use') !== false);
            $is_paused = (strpos($member_status, 'paused') !== false);
            $is_in_use = (strpos($member_status, 'In use') !== false || strpos($member_status, 'Ringing') !== false);
            $is_unavailable = (strpos($member_status, 'Unavailable') !== false);

            // Parse calls taken
            $calls_taken = 0;
            if (preg_match('/has taken (\d+) calls?/', $line, $call_matches)) {
                $calls_taken = (int)$call_matches[1];
            }

            $status['queues'][] = [
                'name' => $current_queue,
                'status' => $member_status,
                'available' => $is_available,
                'paused' => $is_paused,
                'in_call' => $is_in_use,
                'unavailable' => $is_unavailable,
                'calls_taken' => $calls_taken
            ];

            $status['logged_in'] = true;
            if ($is_available) $status['available'] = true;
            if ($is_paused) $status['paused'] = true;
            if ($is_in_use) $status['in_call'] = true;
            $status['total_calls_taken'] += $calls_taken;
        }
    }

    return $status;
}

/**
 * Login to queue
 */
function loginToQueue($extension, $queue = 'support') {
    // Add member to queue
    $penalty = 0;
    $paused = 0;
    $interface = "PJSIP/{$extension}";

    exec("sudo asterisk -rx \"queue add member {$interface} to {$queue} penalty {$penalty} paused {$paused}\" 2>&1", $output, $return);

    $success = false;
    foreach ($output as $line) {
        if (strpos($line, 'Added') !== false || strpos($line, 'Already') !== false) {
            $success = true;
            break;
        }
    }

    // Log the login event
    logAgentEvent($extension, $queue, 'LOGIN');

    return [
        'success' => $success,
        'message' => $success ? "Logged in to queue: {$queue}" : "Failed to login to queue",
        'queue' => $queue,
        'extension' => $extension,
        'output' => implode("\n", $output)
    ];
}

/**
 * Logout from queue
 */
function logoutFromQueue($extension, $queue = 'support') {
    $interface = "PJSIP/{$extension}";

    exec("sudo asterisk -rx \"queue remove member {$interface} from {$queue}\" 2>&1", $output, $return);

    $success = false;
    foreach ($output as $line) {
        if (strpos($line, 'Removed') !== false) {
            $success = true;
            break;
        }
    }

    // Log the logout event
    logAgentEvent($extension, $queue, 'LOGOUT');

    return [
        'success' => $success,
        'message' => $success ? "Logged out from queue: {$queue}" : "Failed to logout from queue",
        'queue' => $queue,
        'extension' => $extension
    ];
}

/**
 * Pause/Unpause in queue
 */
function togglePause($extension, $queue = 'support', $paused = true, $reason = '') {
    $interface = "PJSIP/{$extension}";
    $pause_value = $paused ? 'true' : 'false';

    exec("sudo asterisk -rx \"queue pause member {$interface} queue {$queue} reason {$reason} {$pause_value}\" 2>&1", $output, $return);

    $success = false;
    foreach ($output as $line) {
        if (strpos($line, 'paused') !== false || strpos($line, 'unpaused') !== false) {
            $success = true;
            break;
        }
    }

    // Log the pause event
    logAgentEvent($extension, $queue, $paused ? 'PAUSE' : 'UNPAUSE', $reason);

    return [
        'success' => $success,
        'message' => $paused ? "Paused in queue: {$queue}" : "Unpaused in queue: {$queue}",
        'paused' => $paused,
        'reason' => $reason,
        'queue' => $queue
    ];
}

/**
 * Get available queues
 */
function getAvailableQueues() {
    exec('sudo asterisk -rx "queue show" 2>&1', $output);

    $queues = [];
    foreach ($output as $line) {
        if (preg_match('/^(\w+)\s+has\s+(\d+)\s+calls.*in\s+\'([^\']+)\'\s+strategy/', $line, $matches)) {
            $queues[] = [
                'name' => $matches[1],
                'calls_waiting' => (int)$matches[2],
                'strategy' => $matches[3]
            ];
        }
    }

    return $queues;
}

/**
 * Submit wrap-up code for last call
 */
function submitWrapUp($extension, $wrap_code, $notes = '', $call_id = null) {
    global $wrap_up_codes;

    if (!isset($wrap_up_codes[$wrap_code])) {
        return ['success' => false, 'error' => 'Invalid wrap-up code'];
    }

    $wrap_up_file = "/home/flexpbxuser/callcenter/wrapups/{$extension}_" . date('Y-m-d') . ".json";
    $wrap_up_dir = dirname($wrap_up_file);

    if (!is_dir($wrap_up_dir)) {
        mkdir($wrap_up_dir, 0755, true);
    }

    $wrap_ups = [];
    if (file_exists($wrap_up_file)) {
        $wrap_ups = json_decode(file_get_contents($wrap_up_file), true) ?: [];
    }

    $wrap_ups[] = [
        'timestamp' => time(),
        'datetime' => date('Y-m-d H:i:s'),
        'extension' => $extension,
        'wrap_code' => $wrap_code,
        'wrap_label' => $wrap_up_codes[$wrap_code],
        'notes' => $notes,
        'call_id' => $call_id
    ];

    file_put_contents($wrap_up_file, json_encode($wrap_ups, JSON_PRETTY_PRINT));

    return [
        'success' => true,
        'message' => 'Wrap-up code submitted',
        'wrap_code' => $wrap_code,
        'wrap_label' => $wrap_up_codes[$wrap_code]
    ];
}

/**
 * Get wrap-up codes
 */
function getWrapUpCodes() {
    global $wrap_up_codes;
    return [
        'success' => true,
        'codes' => $wrap_up_codes
    ];
}

/**
 * Get agent statistics
 */
function getAgentStats($extension, $date = null) {
    if (!$date) {
        $date = date('Y-m-d');
    }

    $stats = [
        'calls_answered' => 0,
        'calls_missed' => 0,
        'total_talk_time' => 0,
        'avg_talk_time' => 0,
        'wrap_ups' => [],
        'login_time' => 0,
        'pause_time' => 0,
        'available_time' => 0
    ];

    // Get wrap-ups for today
    $wrap_up_file = "/home/flexpbxuser/callcenter/wrapups/{$extension}_{$date}.json";
    if (file_exists($wrap_up_file)) {
        $stats['wrap_ups'] = json_decode(file_get_contents($wrap_up_file), true) ?: [];
        $stats['calls_answered'] = count($stats['wrap_ups']);
    }

    // Get talk time from CDR
    $cdr_file = '/var/log/asterisk/cdr-csv/Master.csv';
    if (file_exists($cdr_file)) {
        $handle = fopen($cdr_file, 'r');
        while (($data = fgetcsv($handle)) !== false) {
            if (count($data) < 17) continue;

            $dst = $data[2];
            $start = $data[9];
            $billsec = (int)$data[13];
            $disposition = $data[14];

            // Check if call was to this extension and answered today
            if ($dst === $extension && $disposition === 'ANSWERED') {
                $call_date = date('Y-m-d', strtotime($start));
                if ($call_date === $date) {
                    $stats['total_talk_time'] += $billsec;
                }
            }
        }
        fclose($handle);

        if ($stats['calls_answered'] > 0) {
            $stats['avg_talk_time'] = round($stats['total_talk_time'] / $stats['calls_answered']);
        }
    }

    // Get login/pause times from event log
    $event_log = "/home/flexpbxuser/callcenter/events/{$extension}_{$date}.log";
    if (file_exists($event_log)) {
        $events = file($event_log, FILE_IGNORE_NEW_LINES);
        $last_login = null;
        $last_pause = null;

        foreach ($events as $event) {
            $parts = explode('|', $event);
            if (count($parts) < 3) continue;

            $timestamp = strtotime($parts[0]);
            $action = $parts[2];

            if ($action === 'LOGIN') {
                $last_login = $timestamp;
            } elseif ($action === 'LOGOUT' && $last_login) {
                $stats['login_time'] += ($timestamp - $last_login);
                $last_login = null;
            } elseif ($action === 'PAUSE') {
                $last_pause = $timestamp;
            } elseif ($action === 'UNPAUSE' && $last_pause) {
                $stats['pause_time'] += ($timestamp - $last_pause);
                $last_pause = null;
            }
        }

        // If still logged in, count time until now
        if ($last_login) {
            $stats['login_time'] += (time() - $last_login);
        }
        if ($last_pause) {
            $stats['pause_time'] += (time() - $last_pause);
        }

        $stats['available_time'] = $stats['login_time'] - $stats['pause_time'];
    }

    // Format times
    $stats['login_time_formatted'] = formatDuration($stats['login_time']);
    $stats['pause_time_formatted'] = formatDuration($stats['pause_time']);
    $stats['available_time_formatted'] = formatDuration($stats['available_time']);
    $stats['total_talk_time_formatted'] = formatDuration($stats['total_talk_time']);
    $stats['avg_talk_time_formatted'] = formatDuration($stats['avg_talk_time']);

    return [
        'success' => true,
        'stats' => $stats,
        'date' => $date
    ];
}

/**
 * Get queue statistics (for supervisors)
 */
function getQueueStats($queue = 'support') {
    exec("sudo asterisk -rx \"queue show {$queue}\" 2>&1", $output);

    $stats = [
        'queue' => $queue,
        'calls_waiting' => 0,
        'members_total' => 0,
        'members_available' => 0,
        'members_paused' => 0,
        'members_in_call' => 0,
        'longest_wait' => 0,
        'avg_hold_time' => 0,
        'avg_talk_time' => 0,
        'service_level' => 0,
        'members' => []
    ];

    foreach ($output as $line) {
        // Queue stats line
        if (preg_match('/has\s+(\d+)\s+calls.*\((\d+)s holdtime,\s+(\d+)s talktime\).*SL:([0-9.]+)%/', $line, $matches)) {
            $stats['calls_waiting'] = (int)$matches[1];
            $stats['avg_hold_time'] = (int)$matches[2];
            $stats['avg_talk_time'] = (int)$matches[3];
            $stats['service_level'] = (float)$matches[4];
        }

        // Member lines
        if (preg_match('/PJSIP\/(\d+).*\(([^)]+)\).*has taken (\d+) calls?/', $line, $matches)) {
            $ext = $matches[1];
            $status = $matches[2];
            $calls = (int)$matches[3];

            $is_available = (strpos($status, 'Not in use') !== false);
            $is_paused = (strpos($status, 'paused') !== false);
            $is_in_call = (strpos($status, 'In use') !== false || strpos($status, 'Ringing') !== false);

            $stats['members'][] = [
                'extension' => $ext,
                'status' => $status,
                'available' => $is_available,
                'paused' => $is_paused,
                'in_call' => $is_in_call,
                'calls_taken' => $calls
            ];

            $stats['members_total']++;
            if ($is_available) $stats['members_available']++;
            if ($is_paused) $stats['members_paused']++;
            if ($is_in_call) $stats['members_in_call']++;
        }
    }

    return [
        'success' => true,
        'stats' => $stats
    ];
}

/**
 * Log agent event to file
 */
function logAgentEvent($extension, $queue, $event, $reason = '') {
    $date = date('Y-m-d');
    $event_file = "/home/flexpbxuser/callcenter/events/{$extension}_{$date}.log";
    $event_dir = dirname($event_file);

    if (!is_dir($event_dir)) {
        mkdir($event_dir, 0755, true);
    }

    $log_entry = date('Y-m-d H:i:s') . "|{$queue}|{$event}|{$reason}\n";
    file_put_contents($event_file, $log_entry, FILE_APPEND);
}

/**
 * Format duration
 */
function formatDuration($seconds) {
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $secs = $seconds % 60;

    if ($hours > 0) {
        return sprintf('%dh %dm %ds', $hours, $minutes, $secs);
    } elseif ($minutes > 0) {
        return sprintf('%dm %ds', $minutes, $secs);
    } else {
        return sprintf('%ds', $secs);
    }
}

// Handle API requests
switch ($action) {
    case 'status':
        echo json_encode(getAgentStatus($extension));
        break;

    case 'login':
        $queue = $_POST['queue'] ?? 'support';
        echo json_encode(loginToQueue($extension, $queue));
        break;

    case 'logout':
        $queue = $_POST['queue'] ?? 'support';
        echo json_encode(logoutFromQueue($extension, $queue));
        break;

    case 'pause':
        $queue = $_POST['queue'] ?? 'support';
        $reason = $_POST['reason'] ?? '';
        echo json_encode(togglePause($extension, $queue, true, $reason));
        break;

    case 'unpause':
        $queue = $_POST['queue'] ?? 'support';
        echo json_encode(togglePause($extension, $queue, false));
        break;

    case 'queues':
        echo json_encode(['success' => true, 'queues' => getAvailableQueues()]);
        break;

    case 'wrapup_codes':
        echo json_encode(getWrapUpCodes());
        break;

    case 'submit_wrapup':
        $wrap_code = $_POST['wrap_code'] ?? '';
        $notes = $_POST['notes'] ?? '';
        $call_id = $_POST['call_id'] ?? null;
        echo json_encode(submitWrapUp($extension, $wrap_code, $notes, $call_id));
        break;

    case 'stats':
        $date = $_GET['date'] ?? date('Y-m-d');
        echo json_encode(getAgentStats($extension, $date));
        break;

    case 'queue_stats':
        // Supervisor only
        if ($user_role !== 'admin' && $user_role !== 'supervisor') {
            echo json_encode(['success' => false, 'error' => 'Access denied']);
            break;
        }
        $queue = $_GET['queue'] ?? 'support';
        echo json_encode(getQueueStats($queue));
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}
