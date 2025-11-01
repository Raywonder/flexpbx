<?php
/**
 * FlexPBX Client Management API
 * Handles client registration, authentication, and management for master server
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';

// Get request method and path
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

// Helper function to generate secure API key
function generateApiKey($length = 64) {
    return bin2hex(random_bytes($length / 2));
}

// Helper function to generate client ID
function generateClientId() {
    return 'flexpbx_' . bin2hex(random_bytes(16));
}

// Helper function to log client activity
function logClientActivity($conn, $client_id, $activity_type, $activity_data = []) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

    $stmt = $conn->prepare("
        INSERT INTO flexpbx_client_activity
        (client_id, activity_type, activity_data, ip_address, user_agent)
        VALUES (?, ?, ?, ?, ?)
    ");

    $data_json = json_encode($activity_data);
    $stmt->bind_param('sssss', $client_id, $activity_type, $data_json, $ip, $user_agent);
    $stmt->execute();
}

// Verify API key for authenticated requests
function verifyApiKey($conn, $api_key) {
    $stmt = $conn->prepare("
        SELECT client_id, server_url, status
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

// Route requests
switch ($path) {
    case 'register':
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }

        $data = json_decode(file_get_contents('php://input'), true);

        // Validate required fields
        $required = ['server_url', 'admin_email'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                http_response_code(400);
                echo json_encode(['error' => "Missing required field: $field"]);
                exit;
            }
        }

        // Generate client ID and API key
        $client_id = generateClientId();
        $api_key = generateApiKey();

        // Insert client record
        $stmt = $conn->prepare("
            INSERT INTO flexpbx_clients (
                client_id,
                api_key,
                server_url,
                server_ip,
                installation_type,
                flexpbx_version,
                php_version,
                distro_id,
                distro_version,
                admin_email,
                admin_name,
                status,
                features,
                metadata
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, ?)
        ");

        $server_ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $installation_type = $data['installation_type'] ?? 'remote';
        $version = $data['version'] ?? '1.0';
        $php_version = $data['php_version'] ?? null;
        $distro_id = $data['distro_id'] ?? null;
        $distro_version = $data['distro_version'] ?? null;
        $admin_name = $data['admin_name'] ?? null;
        $features_json = json_encode($data['features'] ?? []);
        $metadata_json = json_encode($data['metadata'] ?? []);

        $stmt->bind_param(
            'sssssssssssss',
            $client_id,
            $api_key,
            $data['server_url'],
            $server_ip,
            $installation_type,
            $version,
            $php_version,
            $distro_id,
            $distro_version,
            $data['admin_email'],
            $admin_name,
            $features_json,
            $metadata_json
        );

        if ($stmt->execute()) {
            logClientActivity($conn, $client_id, 'registration', $data);

            echo json_encode([
                'success' => true,
                'client_id' => $client_id,
                'api_key' => $api_key,
                'message' => 'Client registered successfully',
                'master_server' => [
                    'url' => 'https://flexpbx.devinecreations.net',
                    'api_endpoint' => '/api/clients.php',
                    'update_endpoint' => '/api/updates.php'
                ]
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to register client: ' . $conn->error]);
        }
        break;

    case 'heartbeat':
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

        // Update client information
        $stmt = $conn->prepare("
            UPDATE flexpbx_clients
            SET flexpbx_version = ?,
                metadata = ?,
                last_check_in = NOW()
            WHERE client_id = ?
        ");

        $version = $data['version'] ?? null;
        $metadata_json = json_encode($data['metadata'] ?? []);

        $stmt->bind_param('sss', $version, $metadata_json, $client['client_id']);
        $stmt->execute();

        logClientActivity($conn, $client['client_id'], 'heartbeat', $data);

        echo json_encode([
            'success' => true,
            'status' => 'active',
            'message' => 'Heartbeat received'
        ]);
        break;

    case 'list':
        // List all registered clients (admin only - would need auth check)
        $stmt = $conn->prepare("
            SELECT
                client_id,
                server_url,
                server_ip,
                installation_type,
                flexpbx_version,
                distro_id,
                distro_version,
                admin_email,
                status,
                last_check_in,
                created_at
            FROM flexpbx_clients
            ORDER BY created_at DESC
        ");

        $stmt->execute();
        $result = $stmt->get_result();

        $clients = [];
        while ($row = $result->fetch_assoc()) {
            $clients[] = $row;
        }

        echo json_encode([
            'success' => true,
            'count' => count($clients),
            'clients' => $clients
        ]);
        break;

    case 'info':
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

        // Get full client information
        $stmt = $conn->prepare("
            SELECT *
            FROM flexpbx_clients
            WHERE client_id = ?
        ");

        $stmt->bind_param('s', $client['client_id']);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            // Decode JSON fields
            $row['features'] = json_decode($row['features'] ?? '{}', true);
            $row['metadata'] = json_decode($row['metadata'] ?? '{}', true);

            // Don't expose API key in response
            unset($row['api_key']);

            echo json_encode([
                'success' => true,
                'client' => $row
            ]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Client not found']);
        }
        break;

    case 'activity':
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

        $limit = (int)($_GET['limit'] ?? 100);

        // Get client activity log
        $stmt = $conn->prepare("
            SELECT *
            FROM flexpbx_client_activity
            WHERE client_id = ?
            ORDER BY created_at DESC
            LIMIT ?
        ");

        $stmt->bind_param('si', $client['client_id'], $limit);
        $stmt->execute();
        $result = $stmt->get_result();

        $activity = [];
        while ($row = $result->fetch_assoc()) {
            $row['activity_data'] = json_decode($row['activity_data'] ?? '{}', true);
            $activity[] = $row;
        }

        echo json_encode([
            'success' => true,
            'count' => count($activity),
            'activity' => $activity
        ]);
        break;

    default:
        http_response_code(404);
        echo json_encode([
            'error' => 'Invalid endpoint',
            'available_endpoints' => [
                'register' => 'POST - Register new client installation',
                'heartbeat' => 'POST - Send heartbeat (requires API key)',
                'info' => 'GET - Get client information (requires API key)',
                'list' => 'GET - List all clients',
                'activity' => 'GET - Get client activity log (requires API key)'
            ]
        ]);
}
