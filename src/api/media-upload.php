<?php
// FlexPBX Media Upload API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$baseDir = '/home/flexpbxuser/public_html';
$mediaDir = $baseDir . '/media';

// Allowed file types
$allowedTypes = [
    'audio/wav' => 'wav',
    'audio/x-wav' => 'wav',
    'audio/wave' => 'wav',
    'audio/mpeg' => 'mp3',
    'audio/mp3' => 'mp3',
    'audio/gsm' => 'gsm',
    'application/octet-stream' => 'wav' // Some browsers send this for WAV
];

function respond($success, $message, $data = []) {
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ], $data));
    exit;
}

// Get action
$action = $_POST['action'] ?? 'upload';

if ($action === 'upload') {
    // Check if file was uploaded
    if (!isset($_FILES['file'])) {
        respond(false, 'No file uploaded');
    }

    $file = $_FILES['file'];
    $type = $_POST['type'] ?? 'sounds'; // sounds, moh, recordings
    $customFilename = $_POST['filename'] ?? null;

    // Validate type
    if (!in_array($type, ['sounds', 'moh', 'recordings'])) {
        respond(false, 'Invalid media type');
    }

    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        respond(false, 'Upload error: ' . $file['error']);
    }

    // Validate file type
    $mimeType = mime_content_type($file['tmp_name']);
    if (!isset($allowedTypes[$mimeType])) {
        respond(false, 'Invalid file type. Only WAV, MP3, and GSM files are allowed. Detected: ' . $mimeType);
    }

    // Create target directory if it doesn't exist
    $targetDir = $mediaDir . '/' . $type;
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }

    // Determine filename
    if ($customFilename) {
        $filename = basename($customFilename);
    } else {
        $filename = basename($file['name']);
    }

    // Sanitize filename
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);

    // Ensure proper extension
    $ext = $allowedTypes[$mimeType];
    if (!preg_match('/\.' . $ext . '$/i', $filename)) {
        $filename .= '.' . $ext;
    }

    $targetPath = $targetDir . '/' . $filename;

    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        chmod($targetPath, 0644);

        // Get file info
        $fileInfo = [
            'filename' => $filename,
            'path' => '/media/' . $type . '/' . $filename,
            'size' => filesize($targetPath),
            'type' => $type,
            'mime_type' => $mimeType,
            'url' => 'https://flexpbx.devinecreations.net/media/' . $type . '/' . $filename
        ];

        respond(true, 'File uploaded successfully', $fileInfo);
    } else {
        respond(false, 'Failed to move uploaded file');
    }
}

respond(false, 'Invalid action');
?>
