<?php
/**
 * FlexPBX Call Logs API
 * Access Call Detail Records (CDR) with search and filtering
 */

require_once __DIR__ . '/auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$auth = checkAuth();
if (!$auth['authenticated']) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';
$call_id = $_GET['id'] ?? '';

// CDR can be in CSV or database - check both
$cdr_csv = '/var/log/asterisk/cdr-csv/Master.csv';
$cdr_custom = '/var/log/asterisk/cdr-custom/Master.csv';

switch ($path) {
    case '':
    case 'list':
        handleListCalls($method);
        break;

    case 'details':
        handleCallDetails($method, $call_id);
        break;

    case 'search':
        handleSearchCalls($method);
        break;

    case 'statistics':
        handleStatistics($method);
        break;

    case 'export':
        handleExport($method);
        break;

    case 'recent':
        handleRecentCalls($method);
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found']);
        break;
}

/**
 * List call logs
 */
function handleListCalls($method) {
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    $limit = (int)($_GET['limit'] ?? 100);
    $limit = min($limit, 1000); // Max 1000
    $offset = (int)($_GET['offset'] ?? 0);

    $calls = getCallRecords($limit, $offset);

    echo json_encode([
        'success' => true,
        'calls' => $calls,
        'total' => count($calls),
        'limit' => $limit,
        'offset' => $offset,
        'timestamp' => date('c')
    ]);
}

/**
 * Get call details
 */
function handleCallDetails($method, $call_id) {
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    if (empty($call_id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Call ID required']);
        return;
    }

    $calls = getCallRecords(10000); // Search through large set
    $call = null;

    foreach ($calls as $record) {
        if (isset($record['uniqueid']) && $record['uniqueid'] === $call_id) {
            $call = $record;
            break;
        }
    }

    if (!$call) {
        http_response_code(404);
        echo json_encode(['error' => 'Call not found']);
        return;
    }

    echo json_encode([
        'success' => true,
        'call' => $call,
        'timestamp' => date('c')
    ]);
}

/**
 * Search calls
 */
function handleSearchCalls($method) {
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    $search_params = [
        'from' => $data['from'] ?? '',
        'to' => $data['to'] ?? '',
        'date_start' => $data['date_start'] ?? '',
        'date_end' => $data['date_end'] ?? '',
        'disposition' => $data['disposition'] ?? '', // ANSWERED, NO ANSWER, BUSY, FAILED
        'min_duration' => $data['min_duration'] ?? 0,
        'limit' => min((int)($data['limit'] ?? 100), 1000)
    ];

    $calls = getCallRecords(10000); // Get large dataset
    $filtered = [];

    foreach ($calls as $call) {
        // Apply filters
        if (!empty($search_params['from']) &&
            strpos($call['src'], $search_params['from']) === false) {
            continue;
        }

        if (!empty($search_params['to']) &&
            strpos($call['dst'], $search_params['to']) === false) {
            continue;
        }

        if (!empty($search_params['date_start'])) {
            $call_date = strtotime($call['calldate']);
            $start_date = strtotime($search_params['date_start']);
            if ($call_date < $start_date) {
                continue;
            }
        }

        if (!empty($search_params['date_end'])) {
            $call_date = strtotime($call['calldate']);
            $end_date = strtotime($search_params['date_end']);
            if ($call_date > $end_date) {
                continue;
            }
        }

        if (!empty($search_params['disposition']) &&
            $call['disposition'] !== $search_params['disposition']) {
            continue;
        }

        if ($call['duration'] < $search_params['min_duration']) {
            continue;
        }

        $filtered[] = $call;

        if (count($filtered) >= $search_params['limit']) {
            break;
        }
    }

    echo json_encode([
        'success' => true,
        'calls' => $filtered,
        'total_results' => count($filtered),
        'search_params' => $search_params,
        'timestamp' => date('c')
    ]);
}

/**
 * Get call statistics
 */
function handleStatistics($method) {
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    $period = $_GET['period'] ?? 'today'; // today, week, month, all
    $calls = getCallRecords(10000);

    $stats = [
        'period' => $period,
        'total_calls' => 0,
        'answered_calls' => 0,
        'missed_calls' => 0,
        'busy_calls' => 0,
        'failed_calls' => 0,
        'total_duration' => 0,
        'avg_duration' => 0,
        'inbound_calls' => 0,
        'outbound_calls' => 0
    ];

    $cutoff_date = match($period) {
        'today' => strtotime('today midnight'),
        'week' => strtotime('-7 days'),
        'month' => strtotime('-30 days'),
        'all' => 0,
        default => strtotime('today midnight')
    };

    $durations = [];

    foreach ($calls as $call) {
        $call_date = strtotime($call['calldate']);

        if ($call_date < $cutoff_date) {
            continue;
        }

        $stats['total_calls']++;
        $stats['total_duration'] += (int)$call['duration'];
        $durations[] = (int)$call['duration'];

        // Count by disposition
        switch ($call['disposition']) {
            case 'ANSWERED':
                $stats['answered_calls']++;
                break;
            case 'NO ANSWER':
                $stats['missed_calls']++;
                break;
            case 'BUSY':
                $stats['busy_calls']++;
                break;
            case 'FAILED':
                $stats['failed_calls']++;
                break;
        }

        // Determine direction (simple heuristic)
        if (preg_match('/^\d{10}/', $call['dst'])) {
            $stats['outbound_calls']++;
        } else {
            $stats['inbound_calls']++;
        }
    }

    if (count($durations) > 0) {
        $stats['avg_duration'] = round(array_sum($durations) / count($durations));
    }

    // Format durations
    $stats['total_duration_formatted'] = formatDuration($stats['total_duration']);
    $stats['avg_duration_formatted'] = formatDuration($stats['avg_duration']);

    // Calculate percentages
    if ($stats['total_calls'] > 0) {
        $stats['answer_rate'] = round(($stats['answered_calls'] / $stats['total_calls']) * 100, 2);
        $stats['miss_rate'] = round(($stats['missed_calls'] / $stats['total_calls']) * 100, 2);
    } else {
        $stats['answer_rate'] = 0;
        $stats['miss_rate'] = 0;
    }

    echo json_encode([
        'success' => true,
        'statistics' => $stats,
        'timestamp' => date('c')
    ]);
}

/**
 * Export call logs
 */
function handleExport($method) {
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    $format = $_GET['format'] ?? 'csv'; // csv, json, pdf
    $limit = min((int)($_GET['limit'] ?? 1000), 10000);

    $calls = getCallRecords($limit);

    if ($format === 'json') {
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="call_logs_' . date('Y-m-d') . '.json"');
        echo json_encode($calls, JSON_PRETTY_PRINT);
        return;
    }

    if ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="call_logs_' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');

        // Headers
        fputcsv($output, ['Date/Time', 'From', 'To', 'Duration', 'Disposition', 'Unique ID']);

        // Data
        foreach ($calls as $call) {
            fputcsv($output, [
                $call['calldate'],
                $call['src'],
                $call['dst'],
                formatDuration($call['duration']),
                $call['disposition'],
                $call['uniqueid']
            ]);
        }

        fclose($output);
        return;
    }

    http_response_code(400);
    echo json_encode(['error' => 'Unsupported export format']);
}

/**
 * Get recent calls (quick access)
 */
function handleRecentCalls($method) {
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    $limit = min((int)($_GET['limit'] ?? 20), 100);
    $calls = getCallRecords($limit);

    echo json_encode([
        'success' => true,
        'recent_calls' => $calls,
        'total' => count($calls),
        'timestamp' => date('c')
    ]);
}

// Helper functions

function getCallRecords($limit = 100, $offset = 0) {
    global $cdr_csv, $cdr_custom;

    $records = [];

    // Try custom CSV first, then standard
    $cdr_file = file_exists($cdr_custom) ? $cdr_custom : $cdr_csv;

    if (!file_exists($cdr_file)) {
        // Try database method
        return getCallRecordsFromDB($limit, $offset);
    }

    // Read CSV file
    $lines = file($cdr_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    // Reverse to get newest first
    $lines = array_reverse($lines);

    // Apply offset and limit
    $lines = array_slice($lines, $offset, $limit);

    foreach ($lines as $line) {
        $fields = str_getcsv($line);

        if (count($fields) < 10) {
            continue;
        }

        // Standard CDR fields
        $records[] = [
            'accountcode' => $fields[0] ?? '',
            'src' => $fields[1] ?? '',
            'dst' => $fields[2] ?? '',
            'dcontext' => $fields[3] ?? '',
            'clid' => $fields[4] ?? '',
            'channel' => $fields[5] ?? '',
            'dstchannel' => $fields[6] ?? '',
            'lastapp' => $fields[7] ?? '',
            'lastdata' => $fields[8] ?? '',
            'calldate' => $fields[9] ?? '',
            'duration' => (int)($fields[10] ?? 0),
            'billsec' => (int)($fields[11] ?? 0),
            'disposition' => $fields[12] ?? '',
            'amaflags' => $fields[13] ?? '',
            'uniqueid' => $fields[14] ?? '',
            'duration_formatted' => formatDuration((int)($fields[10] ?? 0))
        ];
    }

    return $records;
}

function getCallRecordsFromDB($limit, $offset) {
    // Placeholder for database-based CDR retrieval
    // This would connect to MySQL/PostgreSQL if Asterisk is configured to use database CDR

    return [];
}

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

function checkAuth() {
    session_start();
    return [
        'authenticated' => isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true,
        'username' => $_SESSION['username'] ?? null
    ];
}
?>
