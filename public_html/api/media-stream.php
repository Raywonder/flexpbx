<?php
// FlexPBX Media Streaming API - Authenticated Access Only
// Serves media files only to authenticated PBX extensions/users

// Basic authentication check (enhance this based on your auth system)
function checkAuth() {
    // Check for PBX authentication token or basic auth
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';

    // Allow localhost/internal access
    $remoteAddr = $_SERVER['REMOTE_ADDR'];
    if (in_array($remoteAddr, ['127.0.0.1', '::1', 'localhost'])) {
        return true;
    }

    // Check for valid session or API key
    // TODO: Integrate with your actual auth system
    if (!empty($_SESSION['pbx_authenticated']) || !empty($_SERVER['PHP_AUTH_USER'])) {
        return true;
    }

    return false;
}

// Check authentication
if (!checkAuth()) {
    header('HTTP/1.1 403 Forbidden');
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Authentication required',
        'message' => 'Access to media files requires authentication'
    ]);
    exit;
}

// Get requested file
$file = $_GET['file'] ?? '';
if (empty($file)) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['error' => 'No file specified']);
    exit;
}

// Sanitize file path (prevent directory traversal)
$file = str_replace(['../', '..\\'], '', $file);
$basePath = '/home/flexpbxuser/public_html/media/';
$filePath = $basePath . $file;

// Check if file exists
if (!file_exists($filePath) || !is_file($filePath)) {
    header('HTTP/1.1 404 Not Found');
    echo json_encode(['error' => 'File not found']);
    exit;
}

// Get file info
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $filePath);
finfo_close($finfo);

$fileSize = filesize($filePath);
$fileName = basename($filePath);

// Set headers for streaming
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . $fileSize);
header('Content-Disposition: inline; filename="' . $fileName . '"');
header('Accept-Ranges: bytes');
header('Cache-Control: public, max-age=3600');

// Support range requests for audio streaming
if (isset($_SERVER['HTTP_RANGE'])) {
    $range = $_SERVER['HTTP_RANGE'];
    $range = str_replace('bytes=', '', $range);
    $range = explode('-', $range);
    $start = intval($range[0]);
    $end = isset($range[1]) && $range[1] ? intval($range[1]) : $fileSize - 1;

    header('HTTP/1.1 206 Partial Content');
    header('Content-Range: bytes ' . $start . '-' . $end . '/' . $fileSize);
    header('Content-Length: ' . ($end - $start + 1));

    $fp = fopen($filePath, 'rb');
    fseek($fp, $start);
    $buffer = 1024 * 8;
    while (!feof($fp) && ($p = ftell($fp)) <= $end) {
        if ($p + $buffer > $end) {
            $buffer = $end - $p + 1;
        }
        echo fread($fp, $buffer);
        flush();
    }
    fclose($fp);
} else {
    // Stream entire file
    readfile($filePath);
}

exit;
?>
