<?php
/**
 * FlexPBX Call History API
 * Provides CDR (Call Detail Records) for user extensions
 */

header('Content-Type: application/json');

// Check authentication
session_start();
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$extension = $_SESSION['user_extension'] ?? null;
if (!$extension) {
    echo json_encode(['success' => false, 'error' => 'No extension found']);
    exit;
}

$action = $_GET['action'] ?? 'list';

/**
 * Parse CDR CSV file and return call records for specific extension
 */
function getCallHistory($extension, $limit = 100, $offset = 0) {
    $cdr_file = '/var/log/asterisk/cdr-csv/Master.csv';

    if (!file_exists($cdr_file)) {
        return ['success' => false, 'error' => 'CDR file not found'];
    }

    $calls = [];
    $handle = fopen($cdr_file, 'r');

    if ($handle) {
        $line_number = 0;
        while (($data = fgetcsv($handle)) !== false) {
            $line_number++;

            // CSV format:
            // 0: accountcode, 1: src, 2: dst, 3: dcontext, 4: clid,
            // 5: channel, 6: dstchannel, 7: lastapp, 8: lastdata,
            // 9: start, 10: answer, 11: end, 12: duration, 13: billsec,
            // 14: disposition, 15: amaflags, 16: uniqueid, 17: userfield

            if (count($data) < 17) continue;

            $src = $data[1];
            $dst = $data[2];
            $clid = $data[4];
            $start = $data[9];
            $answer = $data[10];
            $end = $data[11];
            $duration = (int)$data[12];
            $billsec = (int)$data[13];
            $disposition = $data[14];
            $uniqueid = $data[16];

            // Check if this call involves the user's extension
            $is_user_call = false;
            $call_type = 'unknown';
            $other_party = '';

            if ($src === $extension) {
                // Outbound call from this extension
                $is_user_call = true;
                $call_type = 'outbound';
                $other_party = $dst;
            } elseif ($dst === $extension) {
                // Inbound call to this extension
                $is_user_call = true;
                $call_type = 'inbound';
                $other_party = $src;
            } elseif (strpos($clid, "<$extension>") !== false) {
                // Extension mentioned in caller ID
                $is_user_call = true;
                $call_type = 'related';
                $other_party = $dst;
            }

            if (!$is_user_call) continue;

            // Parse caller ID to extract name and number
            $caller_name = '';
            $caller_number = $src;
            if (preg_match('/"([^"]+)"\s*<([^>]+)>/', $clid, $matches)) {
                $caller_name = $matches[1];
                $caller_number = $matches[2];
            }

            $calls[] = [
                'uniqueid' => $uniqueid,
                'call_type' => $call_type,
                'direction' => $call_type,
                'src' => $src,
                'dst' => $dst,
                'other_party' => $other_party,
                'caller_name' => $caller_name,
                'caller_number' => $caller_number,
                'start_time' => $start,
                'answer_time' => $answer,
                'end_time' => $end,
                'duration' => $duration,
                'billsec' => $billsec,
                'talk_time' => $billsec,
                'disposition' => $disposition,
                'status' => $disposition,
                'timestamp' => strtotime($start),
                'date' => date('Y-m-d', strtotime($start)),
                'time' => date('H:i:s', strtotime($start))
            ];
        }
        fclose($handle);
    }

    // Sort by timestamp descending (newest first)
    usort($calls, function($a, $b) {
        return $b['timestamp'] - $a['timestamp'];
    });

    // Apply pagination
    $total = count($calls);
    $calls = array_slice($calls, $offset, $limit);

    return [
        'success' => true,
        'calls' => $calls,
        'total' => $total,
        'limit' => $limit,
        'offset' => $offset,
        'extension' => $extension
    ];
}

/**
 * Get call statistics for the extension
 */
function getCallStats($extension) {
    $cdr_file = '/var/log/asterisk/cdr-csv/Master.csv';

    if (!file_exists($cdr_file)) {
        return ['success' => false, 'error' => 'CDR file not found'];
    }

    $stats = [
        'total_calls' => 0,
        'inbound_calls' => 0,
        'outbound_calls' => 0,
        'answered_calls' => 0,
        'missed_calls' => 0,
        'total_talk_time' => 0,
        'avg_talk_time' => 0,
        'today_calls' => 0,
        'week_calls' => 0,
        'month_calls' => 0
    ];

    $handle = fopen($cdr_file, 'r');
    $today = date('Y-m-d');
    $week_start = date('Y-m-d', strtotime('-7 days'));
    $month_start = date('Y-m-d', strtotime('-30 days'));

    if ($handle) {
        while (($data = fgetcsv($handle)) !== false) {
            if (count($data) < 17) continue;

            $src = $data[1];
            $dst = $data[2];
            $start = $data[9];
            $billsec = (int)$data[13];
            $disposition = $data[14];

            // Check if this call involves the user's extension
            $is_user_call = false;
            $is_inbound = false;

            if ($src === $extension) {
                $is_user_call = true;
                $is_inbound = false;
            } elseif ($dst === $extension) {
                $is_user_call = true;
                $is_inbound = true;
            }

            if (!$is_user_call) continue;

            $stats['total_calls']++;

            if ($is_inbound) {
                $stats['inbound_calls']++;
            } else {
                $stats['outbound_calls']++;
            }

            if ($disposition === 'ANSWERED') {
                $stats['answered_calls']++;
                $stats['total_talk_time'] += $billsec;
            } elseif ($disposition === 'NO ANSWER' || $disposition === 'FAILED') {
                $stats['missed_calls']++;
            }

            // Date-based stats
            $call_date = date('Y-m-d', strtotime($start));
            if ($call_date === $today) {
                $stats['today_calls']++;
            }
            if ($call_date >= $week_start) {
                $stats['week_calls']++;
            }
            if ($call_date >= $month_start) {
                $stats['month_calls']++;
            }
        }
        fclose($handle);
    }

    // Calculate average
    if ($stats['answered_calls'] > 0) {
        $stats['avg_talk_time'] = round($stats['total_talk_time'] / $stats['answered_calls']);
    }

    // Format total talk time
    $stats['total_talk_time_formatted'] = formatDuration($stats['total_talk_time']);
    $stats['avg_talk_time_formatted'] = formatDuration($stats['avg_talk_time']);

    return [
        'success' => true,
        'stats' => $stats,
        'extension' => $extension
    ];
}

/**
 * Format duration in seconds to readable format
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
    case 'list':
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
        echo json_encode(getCallHistory($extension, $limit, $offset));
        break;

    case 'stats':
        echo json_encode(getCallStats($extension));
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}
