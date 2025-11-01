<?php
/**
 * FlexPBX Call Recording Management API
 * Manage call recording settings and access recordings
 * Created: October 17, 2025
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Configuration file path
define('CONFIG_FILE', '/home/flexpbxuser/call_recording_config.json');
define('RECORDING_PATH', '/var/spool/asterisk/monitor');

$path = $_GET['path'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// Get POST data
$postData = [];
if ($method === 'POST') {
    $postData = json_decode(file_get_contents('php://input'), true) ?? [];
}

switch ($path) {
    case 'config':
        handleConfig($method, $postData);
        break;

    case 'extension_config':
        handleExtensionConfig($method, $postData);
        break;

    case 'list':
        handleListRecordings($postData);
        break;

    case 'download':
        handleDownloadRecording();
        break;

    case 'delete':
        handleDeleteRecording($postData);
        break;

    case 'stats':
        handleStats();
        break;

    default:
        respond(false, 'Invalid path', null, 404);
        break;
}

/**
 * Get or update global recording configuration
 */
function handleConfig($method, $data) {
    $config = loadConfig();

    if ($method === 'GET') {
        respond(true, 'Recording configuration', $config);
    } elseif ($method === 'POST') {
        // Update global settings
        if (isset($data['global_settings'])) {
            $config['global_settings'] = array_merge(
                $config['global_settings'],
                $data['global_settings']
            );
        }

        saveConfig($config);
        respond(true, 'Configuration updated', $config);
    }
}

/**
 * Get or update extension-specific recording configuration
 */
function handleExtensionConfig($method, $data) {
    $config = loadConfig();
    $extension = $data['extension'] ?? null;

    if (!$extension) {
        respond(false, 'Extension required', null, 400);
    }

    if ($method === 'GET') {
        $extConfig = $config['extension_settings'][$extension] ?? getDefaultExtensionConfig();
        respond(true, "Configuration for extension $extension", $extConfig);
    } elseif ($method === 'POST') {
        // Update extension settings
        $config['extension_settings'][$extension] = array_merge(
            $config['extension_settings'][$extension] ?? getDefaultExtensionConfig(),
            $data['settings'] ?? []
        );

        saveConfig($config);
        respond(true, "Configuration updated for extension $extension",
                $config['extension_settings'][$extension]);
    }
}

/**
 * List call recordings
 */
function handleListRecordings($data) {
    $extension = $data['extension'] ?? null;
    $limit = $data['limit'] ?? 50;
    $offset = $data['offset'] ?? 0;

    if (!is_dir(RECORDING_PATH)) {
        respond(true, 'No recordings found', ['recordings' => [], 'total' => 0]);
    }

    // Find all recording files
    $files = glob(RECORDING_PATH . '/*.wav');
    if (!$files) {
        respond(true, 'No recordings found', ['recordings' => [], 'total' => 0]);
    }

    // Sort by modification time (newest first)
    usort($files, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });

    $recordings = [];
    foreach ($files as $file) {
        $filename = basename($file);

        // Parse filename (format: YYYYMMDD-HHMMSS-src-dst-uniqueid.wav)
        $parts = explode('-', pathinfo($filename, PATHINFO_FILENAME));

        if (count($parts) >= 3) {
            $date = $parts[0] ?? '';
            $time = $parts[1] ?? '';
            $src = $parts[2] ?? '';
            $dst = $parts[3] ?? '';

            // Filter by extension if specified
            if ($extension && $src !== $extension && $dst !== $extension) {
                continue;
            }

            // Get file info
            $stat = stat($file);
            $duration = getAudioDuration($file);

            $recordings[] = [
                'filename' => $filename,
                'date' => substr($date, 0, 4) . '-' . substr($date, 4, 2) . '-' . substr($date, 6, 2),
                'time' => substr($time, 0, 2) . ':' . substr($time, 2, 2) . ':' . substr($time, 4, 2),
                'source' => $src,
                'destination' => $dst,
                'duration' => $duration,
                'size' => $stat['size'],
                'size_formatted' => formatBytes($stat['size']),
                'timestamp' => $stat['mtime']
            ];
        }
    }

    // Apply pagination
    $total = count($recordings);
    $recordings = array_slice($recordings, $offset, $limit);

    respond(true, 'Recordings retrieved', [
        'recordings' => $recordings,
        'total' => $total,
        'limit' => $limit,
        'offset' => $offset
    ]);
}

/**
 * Download a recording
 */
function handleDownloadRecording() {
    $filename = $_GET['filename'] ?? '';

    if (empty($filename)) {
        respond(false, 'Filename required', null, 400);
    }

    // Security: prevent directory traversal
    $filename = basename($filename);
    $filepath = RECORDING_PATH . '/' . $filename;

    if (!file_exists($filepath)) {
        respond(false, 'Recording not found', null, 404);
    }

    // Send file
    header('Content-Type: audio/wav');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($filepath));
    readfile($filepath);
    exit;
}

/**
 * Delete a recording
 */
function handleDeleteRecording($data) {
    $filename = $data['filename'] ?? '';

    if (empty($filename)) {
        respond(false, 'Filename required', null, 400);
    }

    // Security: prevent directory traversal
    $filename = basename($filename);
    $filepath = RECORDING_PATH . '/' . $filename;

    if (!file_exists($filepath)) {
        respond(false, 'Recording not found', null, 404);
    }

    if (unlink($filepath)) {
        respond(true, 'Recording deleted', ['filename' => $filename]);
    } else {
        respond(false, 'Failed to delete recording', null, 500);
    }
}

/**
 * Get recording statistics
 */
function handleStats() {
    if (!is_dir(RECORDING_PATH)) {
        respond(true, 'Recording statistics', [
            'total_recordings' => 0,
            'total_size' => 0,
            'total_duration' => 0
        ]);
    }

    $files = glob(RECORDING_PATH . '/*.wav');
    $totalSize = 0;
    $totalDuration = 0;

    foreach ($files as $file) {
        $totalSize += filesize($file);
        $totalDuration += getAudioDuration($file);
    }

    respond(true, 'Recording statistics', [
        'total_recordings' => count($files),
        'total_size' => $totalSize,
        'total_size_formatted' => formatBytes($totalSize),
        'total_duration' => $totalDuration,
        'total_duration_formatted' => formatDuration($totalDuration),
        'storage_path' => RECORDING_PATH
    ]);
}

/**
 * Helper functions
 */
function loadConfig() {
    if (!file_exists(CONFIG_FILE)) {
        return getDefaultConfig();
    }

    $config = json_decode(file_get_contents(CONFIG_FILE), true);
    return $config ?? getDefaultConfig();
}

function saveConfig($config) {
    file_put_contents(CONFIG_FILE, json_encode($config, JSON_PRETTY_PRINT));
    chmod(CONFIG_FILE, 0644);
}

function getDefaultConfig() {
    return [
        'global_settings' => [
            'enabled' => true,
            'default_mode' => 'auto',
            'min_duration_seconds' => 5,
            'recording_format' => 'wav',
            'storage_path' => RECORDING_PATH,
            'max_retention_days' => 90
        ],
        'extension_settings' => [],
        'recording_modes' => [
            'auto' => 'Automatic recording for all calls',
            'manual' => 'Manual recording triggered by user',
            'off' => 'No recording'
        ]
    ];
}

function getDefaultExtensionConfig() {
    return [
        'mode' => 'auto',
        'record_incoming' => true,
        'record_outgoing' => true,
        'min_duration' => 5
    ];
}

function getAudioDuration($filepath) {
    // Use soxi to get duration if available
    $output = shell_exec('soxi -D ' . escapeshellarg($filepath) . ' 2>/dev/null');
    if ($output !== null && is_numeric(trim($output))) {
        return (int)round(trim($output));
    }

    // Fallback: estimate based on file size (rough estimate for 8kHz mono WAV)
    $size = filesize($filepath);
    return (int)round($size / (8000 * 2)); // 8kHz, 16-bit
}

function formatBytes($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    }
    return $bytes . ' B';
}

function formatDuration($seconds) {
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $secs = $seconds % 60;

    if ($hours > 0) {
        return sprintf('%dh %dm %ds', $hours, $minutes, $secs);
    } elseif ($minutes > 0) {
        return sprintf('%dm %ds', $minutes, $secs);
    }
    return sprintf('%ds', $secs);
}

function respond($success, $message, $data = null, $httpCode = 200) {
    http_response_code($httpCode);
    $response = [
        'success' => $success,
        'message' => $message,
        'timestamp' => date('c')
    ];

    if ($data !== null) {
        $response = array_merge($response, is_array($data) ? $data : ['data' => $data]);
    }

    echo json_encode($response);
    exit;
}
