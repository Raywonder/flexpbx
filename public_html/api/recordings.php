<?php
/**
 * FlexPBX Call Recordings API
 * Manage call recordings and playback
 * Created: October 16, 2025
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Configuration
define('RECORDING_BASE_DIR', '/var/spool/asterisk/monitor');
define('ALLOWED_EXTENSIONS', ['wav', 'gsm', 'mp3']);
define('MAX_RETENTION_DAYS', 90); // Auto-delete recordings older than this

$path = $_GET['path'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// Get POST data
$postData = [];
if ($method === 'POST') {
    $postData = json_decode(file_get_contents('php://input'), true) ?? [];
}

switch ($path) {
    case '':
    case 'info':
        handleInfo();
        break;

    case 'list':
        handleListRecordings($postData);
        break;

    case 'download':
        handleDownload();
        break;

    case 'delete':
        handleDelete($postData);
        break;

    case 'stats':
        handleStats();
        break;

    case 'cleanup':
        handleCleanup($postData);
        break;

    case 'search':
        handleSearch($postData);
        break;

    default:
        respond(false, 'Invalid path', null, 404);
        break;
}

/**
 * API Information
 */
function handleInfo() {
    respond(true, 'FlexPBX Call Recordings API', [
        'version' => '1.0',
        'endpoints' => [
            'list' => 'List all recordings',
            'download' => 'Download a recording',
            'delete' => 'Delete a recording',
            'stats' => 'Get recording statistics',
            'cleanup' => 'Clean up old recordings',
            'search' => 'Search recordings'
        ],
        'recording_directory' => RECORDING_BASE_DIR,
        'retention_days' => MAX_RETENTION_DAYS
    ]);
}

/**
 * List all recordings
 */
function handleListRecordings($data) {
    $type = $data['type'] ?? $_GET['type'] ?? 'all'; // all, inbound, outbound, internal
    $limit = $data['limit'] ?? $_GET['limit'] ?? 100;
    $offset = $data['offset'] ?? $_GET['offset'] ?? 0;

    $recordings = [];
    $directories = [];

    if ($type === 'all') {
        $directories = ['inbound', 'outbound', 'internal'];
    } else {
        $directories = [$type];
    }

    foreach ($directories as $dir) {
        $fullPath = RECORDING_BASE_DIR . '/' . $dir;

        if (!is_dir($fullPath)) {
            continue;
        }

        $files = glob($fullPath . '/*');

        foreach ($files as $file) {
            if (!is_file($file)) continue;

            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (!in_array($extension, ALLOWED_EXTENSIONS)) continue;

            $recordings[] = [
                'filename' => basename($file),
                'type' => $dir,
                'path' => $file,
                'size' => filesize($file),
                'size_formatted' => formatBytes(filesize($file)),
                'duration' => getAudioDuration($file),
                'format' => $extension,
                'date' => date('Y-m-d H:i:s', filemtime($file)),
                'timestamp' => filemtime($file),
                'age_days' => floor((time() - filemtime($file)) / 86400)
            ];
        }
    }

    // Sort by date (newest first)
    usort($recordings, function($a, $b) {
        return $b['timestamp'] - $a['timestamp'];
    });

    // Apply pagination
    $total = count($recordings);
    $recordings = array_slice($recordings, $offset, $limit);

    respond(true, 'Recordings retrieved', [
        'total' => $total,
        'count' => count($recordings),
        'limit' => $limit,
        'offset' => $offset,
        'recordings' => $recordings
    ]);
}

/**
 * Download a recording
 */
function handleDownload() {
    $filename = $_GET['file'] ?? null;
    $type = $_GET['type'] ?? null;

    if (!$filename || !$type) {
        respond(false, 'File and type required');
        return;
    }

    $filePath = RECORDING_BASE_DIR . '/' . $type . '/' . $filename;

    if (!file_exists($filePath)) {
        respond(false, 'Recording not found', null, 404);
        return;
    }

    // Security check
    $realPath = realpath($filePath);
    $baseDir = realpath(RECORDING_BASE_DIR);

    if (strpos($realPath, $baseDir) !== 0) {
        respond(false, 'Access denied', null, 403);
        return;
    }

    // Set headers for download
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: must-revalidate');

    readfile($filePath);
    exit;
}

/**
 * Delete a recording
 */
function handleDelete($data) {
    $filename = $data['file'] ?? null;
    $type = $data['type'] ?? null;

    if (!$filename || !$type) {
        respond(false, 'File and type required');
        return;
    }

    $filePath = RECORDING_BASE_DIR . '/' . $type . '/' . $filename;

    if (!file_exists($filePath)) {
        respond(false, 'Recording not found');
        return;
    }

    // Security check
    $realPath = realpath($filePath);
    $baseDir = realpath(RECORDING_BASE_DIR);

    if (strpos($realPath, $baseDir) !== 0) {
        respond(false, 'Access denied', null, 403);
        return;
    }

    if (unlink($filePath)) {
        respond(true, 'Recording deleted successfully');
    } else {
        respond(false, 'Failed to delete recording');
    }
}

/**
 * Get recording statistics
 */
function handleStats() {
    $stats = [
        'total_recordings' => 0,
        'total_size' => 0,
        'by_type' => [
            'inbound' => ['count' => 0, 'size' => 0],
            'outbound' => ['count' => 0, 'size' => 0],
            'internal' => ['count' => 0, 'size' => 0]
        ],
        'by_format' => [],
        'oldest_recording' => null,
        'newest_recording' => null,
        'average_size' => 0
    ];

    $directories = ['inbound', 'outbound', 'internal'];
    $allTimestamps = [];

    foreach ($directories as $dir) {
        $fullPath = RECORDING_BASE_DIR . '/' . $dir;

        if (!is_dir($fullPath)) {
            continue;
        }

        $files = glob($fullPath . '/*');

        foreach ($files as $file) {
            if (!is_file($file)) continue;

            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (!in_array($extension, ALLOWED_EXTENSIONS)) continue;

            $size = filesize($file);
            $timestamp = filemtime($file);

            $stats['total_recordings']++;
            $stats['total_size'] += $size;
            $stats['by_type'][$dir]['count']++;
            $stats['by_type'][$dir]['size'] += $size;

            if (!isset($stats['by_format'][$extension])) {
                $stats['by_format'][$extension] = ['count' => 0, 'size' => 0];
            }
            $stats['by_format'][$extension]['count']++;
            $stats['by_format'][$extension]['size'] += $size;

            $allTimestamps[] = $timestamp;
        }
    }

    if (count($allTimestamps) > 0) {
        $stats['oldest_recording'] = date('Y-m-d H:i:s', min($allTimestamps));
        $stats['newest_recording'] = date('Y-m-d H:i:s', max($allTimestamps));
        $stats['average_size'] = $stats['total_size'] / $stats['total_recordings'];
    }

    // Format sizes
    $stats['total_size_formatted'] = formatBytes($stats['total_size']);
    $stats['average_size_formatted'] = formatBytes($stats['average_size']);

    foreach ($stats['by_type'] as &$type) {
        $type['size_formatted'] = formatBytes($type['size']);
    }

    foreach ($stats['by_format'] as &$format) {
        $format['size_formatted'] = formatBytes($format['size']);
    }

    respond(true, 'Statistics retrieved', $stats);
}

/**
 * Clean up old recordings
 */
function handleCleanup($data) {
    $days = $data['days'] ?? MAX_RETENTION_DAYS;
    $dryRun = $data['dry_run'] ?? false;

    $cutoffTime = time() - ($days * 86400);
    $deleted = [];
    $wouldDelete = [];

    $directories = ['inbound', 'outbound', 'internal'];

    foreach ($directories as $dir) {
        $fullPath = RECORDING_BASE_DIR . '/' . $dir;

        if (!is_dir($fullPath)) {
            continue;
        }

        $files = glob($fullPath . '/*');

        foreach ($files as $file) {
            if (!is_file($file)) continue;

            if (filemtime($file) < $cutoffTime) {
                $info = [
                    'file' => basename($file),
                    'type' => $dir,
                    'age_days' => floor((time() - filemtime($file)) / 86400),
                    'size' => formatBytes(filesize($file))
                ];

                if ($dryRun) {
                    $wouldDelete[] = $info;
                } else {
                    if (unlink($file)) {
                        $deleted[] = $info;
                    }
                }
            }
        }
    }

    if ($dryRun) {
        respond(true, 'Cleanup simulation complete', [
            'would_delete' => count($wouldDelete),
            'files' => $wouldDelete,
            'dry_run' => true
        ]);
    } else {
        respond(true, 'Cleanup complete', [
            'deleted' => count($deleted),
            'files' => $deleted
        ]);
    }
}

/**
 * Search recordings
 */
function handleSearch($data) {
    $query = $data['query'] ?? '';
    $type = $data['type'] ?? 'all';
    $dateFrom = $data['date_from'] ?? null;
    $dateTo = $data['date_to'] ?? null;

    if (empty($query) && !$dateFrom && !$dateTo) {
        respond(false, 'Search query or date range required');
        return;
    }

    $recordings = [];
    $directories = ($type === 'all') ? ['inbound', 'outbound', 'internal'] : [$type];

    foreach ($directories as $dir) {
        $fullPath = RECORDING_BASE_DIR . '/' . $dir;

        if (!is_dir($fullPath)) {
            continue;
        }

        $files = glob($fullPath . '/*');

        foreach ($files as $file) {
            if (!is_file($file)) continue;

            $filename = basename($file);
            $timestamp = filemtime($file);

            // Check filename match
            $matchesQuery = empty($query) || stripos($filename, $query) !== false;

            // Check date range
            $matchesDate = true;
            if ($dateFrom && $timestamp < strtotime($dateFrom)) {
                $matchesDate = false;
            }
            if ($dateTo && $timestamp > strtotime($dateTo . ' 23:59:59')) {
                $matchesDate = false;
            }

            if ($matchesQuery && $matchesDate) {
                $recordings[] = [
                    'filename' => $filename,
                    'type' => $dir,
                    'path' => $file,
                    'size' => filesize($file),
                    'size_formatted' => formatBytes(filesize($file)),
                    'format' => pathinfo($file, PATHINFO_EXTENSION),
                    'date' => date('Y-m-d H:i:s', $timestamp),
                    'timestamp' => $timestamp
                ];
            }
        }
    }

    // Sort by date (newest first)
    usort($recordings, function($a, $b) {
        return $b['timestamp'] - $a['timestamp'];
    });

    respond(true, 'Search complete', [
        'query' => $query,
        'results' => count($recordings),
        'recordings' => $recordings
    ]);
}

/**
 * Get audio file duration (approximate)
 */
function getAudioDuration($file) {
    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $size = filesize($file);

    // Rough estimates based on format
    switch ($extension) {
        case 'ulaw':
        case 'gsm':
            // ~8kbps for ulaw, ~13kbps for GSM
            $bytesPerSecond = ($extension === 'ulaw') ? 8000 : 1625;
            $seconds = $size / $bytesPerSecond;
            break;
        case 'wav':
            // Assume 16-bit, 8kHz mono = 16000 bytes/sec
            $seconds = $size / 16000;
            break;
        case 'mp3':
            // Assume 64kbps average
            $seconds = ($size * 8) / 64000;
            break;
        default:
            return 'Unknown';
    }

    $minutes = floor($seconds / 60);
    $seconds = $seconds % 60;

    return sprintf('%d:%02d', $minutes, $seconds);
}

/**
 * Helper function to format bytes
 */
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}

function respond($success, $message, $data = null, $httpCode = 200) {
    http_response_code($httpCode);

    $response = [
        'success' => $success,
        'message' => $message,
        'timestamp' => date('c')
    ];

    if ($data !== null) {
        $response = array_merge($response, $data);
    }

    echo json_encode($response, JSON_PRETTY_PRINT);
    exit;
}
?>
