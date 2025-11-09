<?php
/**
 * FlexPBX Client Management API - Auto-Setup Version
 * Automatically creates database tables, handles registration, updates, and module distribution
 * No manual setup required - fully self-contained
 */

header('Content-Type: application/json');

// Database connection (tries to use existing config, falls back to defaults)
function getDbConnection() {
    $config_file = __DIR__ . '/../config/database.php';

    if (file_exists($config_file)) {
        require_once $config_file;
        // Assuming config sets $db_host, $db_user, $db_pass, $db_name
        if (isset($db_host, $db_user, $db_pass, $db_name)) {
            $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
        }
    }

    // Fallback to environment or defaults
    if (!isset($conn) || $conn->connect_error) {
        $conn = new mysqli('localhost', 'flexpbxuser', '', 'flexpbxuser_flexpbx');
    }

    if ($conn->connect_error) {
        return null;
    }

    return $conn;
}

// Auto-create database tables if they don't exist
function ensureTablesExist($conn) {
    // Check if tables exist
    $tables_exist = true;
    $result = $conn->query("SHOW TABLES LIKE 'flexpbx_clients'");
    if ($result->num_rows == 0) {
        $tables_exist = false;
    }

    if (!$tables_exist) {
        // Create flexpbx_clients table
        $sql = "CREATE TABLE IF NOT EXISTS flexpbx_clients (
            id INT AUTO_INCREMENT PRIMARY KEY,
            client_id VARCHAR(64) UNIQUE NOT NULL,
            api_key VARCHAR(128) UNIQUE NOT NULL,
            server_url VARCHAR(255) NOT NULL,
            server_ip VARCHAR(45),
            server_name VARCHAR(255),
            installation_type ENUM('remote', 'local', 'docker') DEFAULT 'remote',
            flexpbx_version VARCHAR(20),
            php_version VARCHAR(20),
            distro_id VARCHAR(50),
            distro_version VARCHAR(50),
            admin_email VARCHAR(255),
            admin_name VARCHAR(255),
            status ENUM('active', 'suspended', 'inactive', 'pending') DEFAULT 'active',
            features JSON,
            metadata JSON,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_sync TIMESTAMP NULL,
            last_activity TIMESTAMP NULL,
            last_check_in TIMESTAMP NULL,
            INDEX idx_api_key (api_key),
            INDEX idx_client_id (client_id),
            INDEX idx_status (status),
            INDEX idx_last_check_in (last_check_in)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        if (!$conn->query($sql)) {
            error_log("Failed to create flexpbx_clients table: " . $conn->error);
            return false;
        }

        // Create flexpbx_client_activity table
        $sql = "CREATE TABLE IF NOT EXISTS flexpbx_client_activity (
            id INT AUTO_INCREMENT PRIMARY KEY,
            client_id VARCHAR(64) NOT NULL,
            activity_type VARCHAR(100) NOT NULL,
            activity_data JSON,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_client_id (client_id),
            INDEX idx_activity_type (activity_type),
            INDEX idx_created_at (created_at),
            FOREIGN KEY (client_id)
                REFERENCES flexpbx_clients(client_id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        if (!$conn->query($sql)) {
            error_log("Failed to create flexpbx_client_activity table: " . $conn->error);
            return false;
        }
    }

    return true;
}

// Generate secure API key
function generateApiKey($length = 64) {
    return bin2hex(random_bytes($length / 2));
}

// Generate client ID
function generateClientId() {
    return 'flexpbx_' . bin2hex(random_bytes(16));
}

// Log client activity
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

// Verify API key
function verifyApiKey($conn, $api_key) {
    $stmt = $conn->prepare("
        SELECT client_id, server_url, status, flexpbx_version
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

// Get available updates for client
function getAvailableUpdates($current_version) {
    $updates = [];

    // Check for available FlexPBX versions
    $versions = [
        '1.0' => [
            'version' => '1.0',
            'release_date' => '2025-10-19',
            'type' => 'stable',
            'download_url' => 'https://flexpbx.devinecreations.net/downloads/FlexPBX-Master-Server-v1.0.tar.gz',
            'size' => 931840,
            'md5' => file_exists(__DIR__ . '/../downloads/FlexPBX-Master-Server-v1.0.tar.gz.md5') ?
                     file_get_contents(__DIR__ . '/../downloads/FlexPBX-Master-Server-v1.0.tar.gz.md5') : null
        ],
        '1.1' => [
            'version' => '1.1',
            'release_date' => '2025-10-24',
            'type' => 'stable',
            'download_url' => 'https://flexpbx.devinecreations.net/downloads/FlexPBX-Master-Server-v1.1.tar.gz',
            'size' => 1021952,
            'md5' => file_exists(__DIR__ . '/../downloads/FlexPBX-Master-Server-v1.1.tar.gz.md5') ?
                     file_get_contents(__DIR__ . '/../downloads/FlexPBX-Master-Server-v1.1.tar.gz.md5') : null,
            'features' => [
                'Backup Queue Processor',
                'Module Repository API',
                'TextNow Integration',
                'Network-aware Authentication',
                'Stay Logged In Feature',
                'Enhanced Dashboard'
            ]
        ]
    ];

    // Find newer versions
    foreach ($versions as $ver => $info) {
        if (version_compare($ver, $current_version, '>')) {
            $updates[] = $info;
        }
    }

    return [
        'current_version' => $current_version,
        'latest_version' => '1.1',
        'updates_available' => count($updates) > 0,
        'updates' => $updates
    ];
}

// Get available modules
function getAvailableModules() {
    return [
        'success' => true,
        'modules' => [
            [
                'id' => 'bug-tracker',
                'name' => 'Bug Tracker',
                'version' => '1.0',
                'category' => 'management',
                'description' => 'Bug tracking and issue management',
                'download_url' => 'https://flexpbx.devinecreations.net/api/module-repository.php?action=download&module=bug-tracker'
            ],
            [
                'id' => 'textnow',
                'name' => 'TextNow Integration',
                'version' => '1.0',
                'category' => 'integrations',
                'description' => 'TextNow SMS/MMS/Voice integration',
                'download_url' => 'https://flexpbx.devinecreations.net/api/module-repository.php?action=download&module=textnow'
            ],
            [
                'id' => 'moh-provider',
                'name' => 'Music on Hold Provider',
                'version' => '1.0',
                'category' => 'communication',
                'description' => 'Custom music on hold management',
                'download_url' => 'https://flexpbx.devinecreations.net/api/module-repository.php?action=download&module=moh-provider'
            ]
        ]
    ];
}

// Main request handler
$conn = getDbConnection();

if (!$conn) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database connection failed',
        'message' => 'Please check database configuration'
    ]);
    exit;
}

// Auto-create tables if needed
if (!ensureTablesExist($conn)) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database setup failed',
        'message' => 'Could not create required tables'
    ]);
    exit;
}

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_GET['path'] ?? '';

// Handle different actions
switch ($action) {
    case 'register':
        // Auto-register new client
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit;
        }

        $data = json_decode(file_get_contents('php://input'), true);

        // Generate client ID and API key automatically
        $client_id = generateClientId();
        $api_key = generateApiKey();

        // Prepare client data
        $server_url = $data['server_url'] ?? 'unknown';
        $server_ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $installation_type = $data['installation_type'] ?? 'remote';
        $version = $data['version'] ?? '1.0';
        $php_version = $data['php_version'] ?? PHP_VERSION;
        $distro_id = $data['distro_id'] ?? null;
        $distro_version = $data['distro_version'] ?? null;
        $admin_email = $data['admin_email'] ?? 'admin@localhost';
        $admin_name = $data['admin_name'] ?? null;
        $features_json = json_encode($data['features'] ?? []);
        $metadata_json = json_encode($data['metadata'] ?? []);

        // Insert client record
        $stmt = $conn->prepare("
            INSERT INTO flexpbx_clients (
                client_id, api_key, server_url, server_ip,
                installation_type, flexpbx_version, php_version,
                distro_id, distro_version, admin_email, admin_name,
                status, features, metadata
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, ?)
        ");

        $stmt->bind_param(
            'sssssssssssss',
            $client_id, $api_key, $server_url, $server_ip,
            $installation_type, $version, $php_version,
            $distro_id, $distro_version, $admin_email, $admin_name,
            $features_json, $metadata_json
        );

        if ($stmt->execute()) {
            logClientActivity($conn, $client_id, 'registration', $data);

            echo json_encode([
                'success' => true,
                'client_id' => $client_id,
                'api_key' => $api_key,
                'message' => 'Client registered successfully - save your API key!',
                'master_server' => [
                    'url' => 'https://flexpbx.devinecreations.net',
                    'api_endpoint' => '/api/clients-auto.php',
                    'update_endpoint' => '/api/clients-auto.php?action=check_updates',
                    'module_endpoint' => '/api/clients-auto.php?action=modules'
                ]
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Registration failed: ' . $conn->error]);
        }
        break;

    case 'check_updates':
    case 'updates':
        // Check for available updates
        $api_key = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? null;

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

        $current_version = $client['flexpbx_version'] ?? '1.0';
        $updates = getAvailableUpdates($current_version);

        logClientActivity($conn, $client['client_id'], 'check_updates', [
            'current_version' => $current_version
        ]);

        echo json_encode($updates);
        break;

    case 'modules':
    case 'module_list':
        // Get available modules
        $api_key = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? null;

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

        $modules = getAvailableModules();

        logClientActivity($conn, $client['client_id'], 'module_list', []);

        echo json_encode($modules);
        break;

    case 'heartbeat':
    case 'ping':
        // Client heartbeat/ping
        $api_key = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? null;

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

        if ($method === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);

            // Update client information
            $stmt = $conn->prepare("
                UPDATE flexpbx_clients
                SET flexpbx_version = ?,
                    metadata = ?,
                    last_check_in = NOW()
                WHERE client_id = ?
            ");

            $version = $data['version'] ?? $client['flexpbx_version'];
            $metadata_json = json_encode($data['metadata'] ?? []);

            $stmt->bind_param('sss', $version, $metadata_json, $client['client_id']);
            $stmt->execute();
        }

        logClientActivity($conn, $client['client_id'], 'heartbeat', []);

        // Check if updates available
        $updates = getAvailableUpdates($client['flexpbx_version']);

        echo json_encode([
            'success' => true,
            'status' => 'active',
            'message' => 'Heartbeat received',
            'client_id' => $client['client_id'],
            'current_version' => $client['flexpbx_version'],
            'updates_available' => $updates['updates_available'],
            'latest_version' => $updates['latest_version']
        ]);
        break;

    case 'status':
    case 'info':
        // Public endpoint - no auth required
        echo json_encode([
            'success' => true,
            'service' => 'FlexPBX Client Management API',
            'version' => '1.1',
            'auto_setup' => true,
            'endpoints' => [
                'register' => 'POST /api/clients-auto.php?action=register - Auto-register new client',
                'check_updates' => 'GET /api/clients-auto.php?action=check_updates - Check for updates',
                'modules' => 'GET /api/clients-auto.php?action=modules - Get available modules',
                'heartbeat' => 'POST /api/clients-auto.php?action=heartbeat - Send heartbeat',
                'status' => 'GET /api/clients-auto.php?action=status - API status'
            ],
            'features' => [
                'Auto database setup',
                'Auto client registration',
                'Update checking',
                'Module distribution',
                'Heartbeat monitoring'
            ]
        ]);
        break;

    default:
        // Default response - API info
        echo json_encode([
            'success' => true,
            'message' => 'FlexPBX Client Management API - Auto-Setup Enabled',
            'version' => '1.1',
            'actions' => [
                'register' => 'Register new client (no manual setup required)',
                'check_updates' => 'Check for FlexPBX updates',
                'modules' => 'Get available modules',
                'heartbeat' => 'Send client heartbeat',
                'status' => 'Get API status'
            ],
            'usage' => 'Add ?action=<action_name> to use specific endpoint'
        ]);
        break;
}

$conn->close();
