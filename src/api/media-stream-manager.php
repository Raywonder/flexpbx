<?php
/**
 * Media Stream Manager API
 * Manages Icecast and Jellyfin streaming services
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if admin is logged in (FlexPBX authentication)
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required. Please login to FlexPBX admin panel.'
    ]);
    exit;
}

// Update last activity
$_SESSION['last_activity'] = time();

header('Content-Type: application/json');

// Configuration
$ICECAST_HOST = 'localhost';
$ICECAST_PORT = 8003;
$ICECAST_ADMIN_USER = 'admin';
$ICECAST_ADMIN_PASS = 'hackme';

$JELLYFIN_HOST = 'localhost';
$JELLYFIN_PORT = 8096;

$STREAM_SCRIPT = '/home/dom/apps/jellyfin-icecast-stream-advanced.sh';
$STREAM_LOG = '/home/dom/apps/jellyfin-icecast-stream.log';
$NOW_PLAYING = '/home/dom/apps/jellyfin-now-playing.json';
$QUEUE_FILE = '/home/dom/apps/jellyfin-queue.json';

// Helper Functions
function executeCommand($command) {
    exec($command . ' 2>&1', $output, $return_code);
    return [
        'success' => $return_code === 0,
        'output' => implode("\n", $output),
        'return_code' => $return_code
    ];
}

function getIcecastStatus() {
    global $ICECAST_HOST, $ICECAST_PORT;

    $url = "http://{$ICECAST_HOST}:{$ICECAST_PORT}/status-json.xsl";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200) {
        $data = json_decode($response, true);
        return $data['icestats'] ?? null;
    }

    return null;
}

function getJellyfinStatus() {
    global $JELLYFIN_HOST, $JELLYFIN_PORT;

    $url = "http://{$JELLYFIN_HOST}:{$JELLYFIN_PORT}/System/Info/Public";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200) {
        return json_decode($response, true);
    }

    return null;
}

function getNowPlaying() {
    global $NOW_PLAYING;

    if (file_exists($NOW_PLAYING)) {
        $content = file_get_contents($NOW_PLAYING);
        return json_decode($content, true);
    }

    return null;
}

function getStreamQueue() {
    global $QUEUE_FILE;

    if (file_exists($QUEUE_FILE)) {
        $content = file_get_contents($QUEUE_FILE);
        return json_decode($content, true);
    }

    return [];
}

function getMediaLibraryStats() {
    $stats = [
        'music_tracks' => 0,
        'tv_episodes' => 0,
        'movies' => 0,
        'audiobooks' => 0,
        'last_updated' => date('Y-m-d H:i:s')
    ];

    // Count music tracks
    $output = executeCommand("find /home/dom/apps/media/music -type f \\( -iname '*.mp3' -o -iname '*.m4a' \\) 2>/dev/null | wc -l");
    $stats['music_tracks'] = (int)trim($output['output']);

    // Count TV episodes
    $output = executeCommand("find /home/dom/apps/media/AudioDescribedContent/TV -type f \\( -iname '*.mp3' -o -iname '*.mp4' -o -iname '*.mkv' \\) 2>/dev/null | wc -l");
    $stats['tv_episodes'] = (int)trim($output['output']);

    // Count movies
    $output = executeCommand("find /home/dom/apps/media/AudioDescribedContent/Movies -type f \\( -iname '*.mp3' -o -iname '*.mp4' -o -iname '*.mkv' \\) 2>/dev/null | wc -l");
    $stats['movies'] = (int)trim($output['output']);

    // Count audiobooks
    $output = executeCommand("find /home/dom/apps/media/books* -type f \\( -iname '*.mp3' -o -iname '*.m4a' \\) 2>/dev/null | wc -l");
    $stats['audiobooks'] = (int)trim($output['output']);

    return $stats;
}

// Handle API Requests
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'status':
        // Get comprehensive status
        $status = [
            'icecast' => getIcecastStatus(),
            'jellyfin' => getJellyfinStatus(),
            'now_playing' => getNowPlaying(),
            'queue' => getStreamQueue(),
            'library_stats' => getMediaLibraryStats(),
            'stream_processes' => []
        ];

        // Check stream processes
        $result = executeCommand("ps aux | grep 'jellyfin-icecast-stream' | grep -v grep");
        if ($result['success']) {
            $lines = array_filter(explode("\n", $result['output']));
            $status['stream_processes'] = count($lines);
        }

        echo json_encode([
            'success' => true,
            'status' => $status
        ]);
        break;

    case 'start_stream':
        // Start Icecast stream
        $result = executeCommand("cd /home/dom/apps && nohup ./jellyfin-icecast-stream-advanced.sh > /dev/null 2>&1 &");

        sleep(2);
        $check = executeCommand("ps aux | grep 'jellyfin-icecast-stream' | grep -v grep");

        echo json_encode([
            'success' => !empty($check['output']),
            'message' => !empty($check['output']) ? 'Stream started successfully' : 'Failed to start stream'
        ]);
        break;

    case 'stop_stream':
        // Stop all stream processes
        executeCommand("pkill -9 -f 'jellyfin-icecast-stream'");
        executeCommand("pkill -9 -f 'jellyfin-stream-concat'");

        sleep(1);
        $check = executeCommand("ps aux | grep 'jellyfin-icecast-stream' | grep -v grep");

        echo json_encode([
            'success' => empty($check['output']),
            'message' => empty($check['output']) ? 'Stream stopped successfully' : 'Failed to stop all processes'
        ]);
        break;

    case 'restart_stream':
        // Restart stream
        executeCommand("pkill -9 -f 'jellyfin-icecast-stream'");
        executeCommand("pkill -9 -f 'jellyfin-stream-concat'");
        sleep(2);
        executeCommand("cd /home/dom/apps && nohup ./jellyfin-icecast-stream-advanced.sh > /dev/null 2>&1 &");
        sleep(2);

        $check = executeCommand("ps aux | grep 'jellyfin-icecast-stream' | grep -v grep");

        echo json_encode([
            'success' => !empty($check['output']),
            'message' => !empty($check['output']) ? 'Stream restarted successfully' : 'Failed to restart stream'
        ]);
        break;

    case 'skip_track':
        // Skip current track by killing FFmpeg
        executeCommand("pkill -f 'jellyfin-stream-concat'");

        echo json_encode([
            'success' => true,
            'message' => 'Track skipped, moving to next...'
        ]);
        break;

    case 'get_log':
        // Get recent log entries
        $lines = (int)($_GET['lines'] ?? 50);
        $result = executeCommand("tail -n {$lines} " . escapeshellarg($STREAM_LOG));

        echo json_encode([
            'success' => true,
            'log' => $result['output']
        ]);
        break;

    case 'add_to_queue':
        // Add item to queue
        $type = $_POST['type'] ?? '';
        $count = (int)($_POST['count'] ?? 1);
        $title = $_POST['title'] ?? '';
        $show = $_POST['show'] ?? '';
        $season = $_POST['season'] ?? '';

        if (empty($type)) {
            echo json_encode(['success' => false, 'message' => 'Type required']);
            exit;
        }

        $queue = getStreamQueue();

        $item = [
            'type' => $type,
            'count' => $count,
            'title' => $title,
            'with_intros' => true
        ];

        if (!empty($show)) $item['show'] = $show;
        if (!empty($season)) $item['season'] = $season;

        $queue[] = $item;

        file_put_contents($QUEUE_FILE, json_encode($queue, JSON_PRETTY_PRINT));

        echo json_encode([
            'success' => true,
            'message' => 'Item added to queue',
            'queue_length' => count($queue)
        ]);
        break;

    case 'clear_queue':
        // Clear queue
        file_put_contents($QUEUE_FILE, '[]');

        echo json_encode([
            'success' => true,
            'message' => 'Queue cleared'
        ]);
        break;

    case 'scan_media':
        // Run media compatibility scan
        $result = executeCommand("/home/dom/apps/media-compatibility-checker.sh scan");

        echo json_encode([
            'success' => $result['success'],
            'output' => $result['output']
        ]);
        break;

    case 'convert_wav':
        // Convert WAV files
        $result = executeCommand("/home/dom/apps/convert-problem-media.sh convert-wav > /tmp/wav-conversion.log 2>&1 &");

        echo json_encode([
            'success' => true,
            'message' => 'WAV conversion started in background. Check /tmp/wav-conversion.log for progress.'
        ]);
        break;

    case 'restart_icecast':
        // Restart Icecast server
        $result = executeCommand("systemctl restart icecast 2>&1");

        echo json_encode([
            'success' => $result['success'],
            'message' => $result['success'] ? 'Icecast restarted' : 'Failed to restart Icecast',
            'output' => $result['output']
        ]);
        break;

    case 'restart_jellyfin':
        // Restart Jellyfin server
        $result = executeCommand("systemctl restart jellyfin 2>&1");

        echo json_encode([
            'success' => $result['success'],
            'message' => $result['success'] ? 'Jellyfin restarted' : 'Failed to restart Jellyfin',
            'output' => $result['output']
        ]);
        break;

    default:
        echo json_encode([
            'success' => false,
            'message' => 'Unknown action',
            'available_actions' => [
                'status', 'start_stream', 'stop_stream', 'restart_stream',
                'skip_track', 'get_log', 'add_to_queue', 'clear_queue',
                'scan_media', 'convert_wav', 'restart_icecast', 'restart_jellyfin'
            ]
        ]);
        break;
}
?>
