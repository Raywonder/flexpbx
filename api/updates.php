<?php
/**
 * FlexPBX Update Distribution API
 * Handles update checking and distribution to client installations
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';

// Get request method and path
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

// Verify API key
function verifyApiKey($conn, $api_key) {
    $stmt = $conn->prepare("
        SELECT client_id, server_url, flexpbx_version, status
        FROM flexpbx_clients
        WHERE api_key = ? AND status = 'active'
    ");
    $stmt->bind_param('s', $api_key);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        // Update last check-in time
        $update_stmt = $conn->prepare("UPDATE flexpbx_clients SET last_check_in = NOW() WHERE client_id = ?");
        $update_stmt->bind_param('s', $row['client_id']);
        $update_stmt->execute();

        return $row;
    }

    return false;
}

// Compare version strings
function compareVersions($version1, $version2) {
    return version_compare($version1, $version2);
}

// Route requests
switch ($path) {
    case 'check':
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }

        $api_key = $_SERVER['HTTP_X_API_KEY'] ?? null;
        if (!$api_key) {
            http_response_code(401);
            echo json_encode(['error' => 'API key required']);
            exit;
        }

        $client = verifyApiKey($conn, $api_key);
        if (!$client) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid API key']);
            exit;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $current_version = $data['current_version'] ?? $client['flexpbx_version'] ?? '1.0';
        $update_channel = $data['update_channel'] ?? 'stable';

        // Find available updates
        $stmt = $conn->prepare("
            SELECT *
            FROM flexpbx_update_releases
            WHERE release_channel = ?
              AND active = TRUE
              AND version > ?
            ORDER BY released_at DESC
            LIMIT 1
        ");

        $stmt->bind_param('ss', $update_channel, $current_version);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($update = $result->fetch_assoc()) {
            // Update available
            echo json_encode([
                'update_available' => true,
                'current_version' => $current_version,
                'latest_version' => $update['version'],
                'release_type' => $update['release_type'],
                'release_channel' => $update['release_channel'],
                'release_notes' => $update['release_notes'],
                'download_url' => $update['download_url'],
                'file_size' => $update['file_size'],
                'checksum_md5' => $update['checksum_md5'],
                'checksum_sha256' => $update['checksum_sha256'],
                'required' => (bool)$update['required'],
                'released_at' => $update['released_at']
            ]);
        } else {
            // No updates available
            echo json_encode([
                'update_available' => false,
                'current_version' => $current_version,
                'message' => 'You are running the latest version'
            ]);
        }
        break;

    case 'download':
        $api_key = $_SERVER['HTTP_X_API_KEY'] ?? null;
        if (!$api_key) {
            http_response_code(401);
            echo json_encode(['error' => 'API key required']);
            exit;
        }

        $client = verifyApiKey($conn, $api_key);
        if (!$client) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid API key']);
            exit;
        }

        $version = $_GET['version'] ?? null;
        if (!$version) {
            http_response_code(400);
            echo json_encode(['error' => 'Version parameter required']);
            exit;
        }

        // Get update information
        $stmt = $conn->prepare("
            SELECT *
            FROM flexpbx_update_releases
            WHERE version = ? AND active = TRUE
        ");

        $stmt->bind_param('s', $version);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($update = $result->fetch_assoc()) {
            // Log download
            $log_stmt = $conn->prepare("
                INSERT INTO flexpbx_client_updates
                (client_id, update_version, update_status, started_at)
                VALUES (?, ?, 'downloading', NOW())
            ");

            $log_stmt->bind_param('ss', $client['client_id'], $version);
            $log_stmt->execute();

            // Return download information
            echo json_encode([
                'success' => true,
                'version' => $update['version'],
                'download_url' => $update['download_url'],
                'file_size' => $update['file_size'],
                'checksum_md5' => $update['checksum_md5'],
                'checksum_sha256' => $update['checksum_sha256']
            ]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Update version not found']);
        }
        break;

    case 'report':
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }

        $api_key = $_SERVER['HTTP_X_API_KEY'] ?? null;
        if (!$api_key) {
            http_response_code(401);
            echo json_encode(['error' => 'API key required']);
            exit;
        }

        $client = verifyApiKey($conn, $api_key);
        if (!$client) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid API key']);
            exit;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $version = $data['version'] ?? null;
        $status = $data['status'] ?? null;
        $error_message = $data['error_message'] ?? null;

        if (!$version || !$status) {
            http_response_code(400);
            echo json_encode(['error' => 'Version and status required']);
            exit;
        }

        // Update status in database
        if ($status === 'completed') {
            // Update client's current version
            $update_client = $conn->prepare("
                UPDATE flexpbx_clients
                SET flexpbx_version = ?,
                    last_update = NOW()
                WHERE client_id = ?
            ");
            $update_client->bind_param('ss', $version, $client['client_id']);
            $update_client->execute();
        }

        // Update installation record
        $update_log = $conn->prepare("
            UPDATE flexpbx_client_updates
            SET update_status = ?,
                completed_at = NOW(),
                error_message = ?
            WHERE client_id = ? AND update_version = ?
            ORDER BY id DESC
            LIMIT 1
        ");

        $update_log->bind_param('ssss', $status, $error_message, $client['client_id'], $version);
        $update_log->execute();

        echo json_encode([
            'success' => true,
            'message' => 'Update status reported successfully'
        ]);
        break;

    case 'list':
        // List available updates
        $channel = $_GET['channel'] ?? 'stable';

        $stmt = $conn->prepare("
            SELECT version, release_type, release_channel, release_notes, file_size, released_at
            FROM flexpbx_update_releases
            WHERE release_channel = ? AND active = TRUE
            ORDER BY released_at DESC
        ");

        $stmt->bind_param('s', $channel);
        $stmt->execute();
        $result = $stmt->get_result();

        $updates = [];
        while ($row = $result->fetch_assoc()) {
            $updates[] = $row;
        }

        echo json_encode([
            'success' => true,
            'channel' => $channel,
            'count' => count($updates),
            'updates' => $updates
        ]);
        break;

    default:
        http_response_code(404);
        echo json_encode([
            'error' => 'Invalid endpoint',
            'available_endpoints' => [
                'check' => 'POST - Check for available updates (requires API key)',
                'download' => 'GET - Get download URL for specific version (requires API key)',
                'report' => 'POST - Report update installation status (requires API key)',
                'list' => 'GET - List all available updates'
            ]
        ]);
}
