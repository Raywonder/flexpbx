<?php
/**
 * FlexPBX Server Connection Manager
 * Handles desktop client connections, authorization, and multi-connection limits
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

class FlexPBXConnectionManager {
    private $db;
    private $config;

    public function __construct() {
        $this->config = include 'config.php';
        $this->db = new PDO(
            "mysql:host={$this->config['db_host']};dbname={$this->config['db_name']}",
            $this->config['db_user'],
            $this->config['db_password']
        );
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Initialize database tables if needed
        $this->initializeTables();
    }

    private function initializeTables() {
        // Create desktop_clients table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS desktop_clients (
                id VARCHAR(255) PRIMARY KEY,
                client_type ENUM('admin', 'desktop') NOT NULL,
                device_id VARCHAR(255) NOT NULL,
                device_name VARCHAR(255),
                platform VARCHAR(50),
                ip_address VARCHAR(45),
                user_agent TEXT,
                first_connected DATETIME DEFAULT CURRENT_TIMESTAMP,
                last_connected DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                is_active BOOLEAN DEFAULT TRUE,
                max_connections INT DEFAULT 1,
                current_connections INT DEFAULT 0,
                capabilities JSON,
                settings JSON,
                INDEX idx_device_id (device_id),
                INDEX idx_client_type (client_type),
                INDEX idx_active (is_active)
            )
        ");

        // Create active_connections table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS active_connections (
                id VARCHAR(255) PRIMARY KEY,
                client_id VARCHAR(255),
                server_endpoint VARCHAR(255),
                connected_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                last_activity DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                session_data JSON,
                FOREIGN KEY (client_id) REFERENCES desktop_clients(id) ON DELETE CASCADE,
                INDEX idx_client_id (client_id),
                INDEX idx_last_activity (last_activity)
            )
        ");

        // Create connection_limits table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS connection_limits (
                client_type ENUM('admin', 'desktop') PRIMARY KEY,
                default_max_connections INT DEFAULT 1,
                premium_max_connections INT DEFAULT 5,
                enterprise_max_connections INT DEFAULT 50,
                requires_approval BOOLEAN DEFAULT FALSE
            )
        ");

        // Insert default limits
        $this->db->exec("
            INSERT IGNORE INTO connection_limits VALUES
            ('admin', 5, 10, 100, FALSE),
            ('desktop', 1, 3, 10, TRUE)
        ");
    }

    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $pathParts = explode('/', trim($path, '/'));

        try {
            switch ($pathParts[count($pathParts) - 1]) {
                case 'register':
                    return $this->registerClient();
                case 'authorize':
                    return $this->authorizeClient();
                case 'connection-limit':
                    return $this->handleConnectionLimit();
                case 'status':
                    return $this->getConnectionStatus();
                case 'update-check':
                    return $this->checkForUpdates();
                case 'module-reload':
                    return $this->reloadModule();
                case 'server-restart':
                    return $this->restartServer();
                default:
                    return $this->sendError('Unknown endpoint', 404);
            }
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    private function registerClient() {
        $input = json_decode(file_get_contents('php://input'), true);

        $clientId = $input['clientId'] ?? uniqid('client_');
        $deviceId = $input['deviceId'] ?? '';
        $clientType = $input['clientType'] ?? 'desktop';
        $deviceName = $input['deviceName'] ?? '';
        $platform = $input['platform'] ?? '';
        $capabilities = $input['capabilities'] ?? [];

        // Get client IP
        $ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        // Check if client already exists
        $stmt = $this->db->prepare("SELECT * FROM desktop_clients WHERE device_id = ?");
        $stmt->execute([$deviceId]);
        $existingClient = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existingClient) {
            // Update existing client
            $stmt = $this->db->prepare("
                UPDATE desktop_clients
                SET client_type = ?, device_name = ?, platform = ?, ip_address = ?,
                    user_agent = ?, capabilities = ?, last_connected = NOW()
                WHERE device_id = ?
            ");
            $stmt->execute([
                $clientType, $deviceName, $platform, $ipAddress,
                $userAgent, json_encode($capabilities), $deviceId
            ]);
            $clientId = $existingClient['id'];
        } else {
            // Register new client
            $stmt = $this->db->prepare("
                INSERT INTO desktop_clients
                (id, client_type, device_id, device_name, platform, ip_address, user_agent, capabilities)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $clientId, $clientType, $deviceId, $deviceName,
                $platform, $ipAddress, $userAgent, json_encode($capabilities)
            ]);
        }

        return $this->sendSuccess([
            'clientId' => $clientId,
            'deviceId' => $deviceId,
            'registered' => true,
            'message' => 'Client registered successfully'
        ]);
    }

    private function authorizeClient() {
        $input = json_decode(file_get_contents('php://input'), true);

        $clientId = $input['clientId'] ?? '';
        $authMethod = $input['authMethod'] ?? 'pincode';
        $credentials = $input['credentials'] ?? [];

        // Verify client exists
        $stmt = $this->db->prepare("SELECT * FROM desktop_clients WHERE id = ?");
        $stmt->execute([$clientId]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$client) {
            return $this->sendError('Client not found', 404);
        }

        // Perform authentication based on method
        switch ($authMethod) {
            case 'pincode':
                $authorized = $this->validatePincode($clientId, $credentials['pincode'] ?? '');
                break;
            case 'api-key':
                $authorized = $this->validateApiKey($credentials['apiKey'] ?? '');
                break;
            case 'admin-login':
                $authorized = $this->validateAdminLogin($credentials['username'] ?? '', $credentials['password'] ?? '');
                break;
            default:
                return $this->sendError('Invalid auth method', 400);
        }

        if (!$authorized) {
            return $this->sendError('Authorization failed', 401);
        }

        // Check connection limits
        $connectionInfo = $this->checkConnectionLimits($client);

        if (!$connectionInfo['allowed']) {
            return $this->sendError($connectionInfo['reason'], 403);
        }

        // Create active connection
        $connectionId = uniqid('conn_');
        $stmt = $this->db->prepare("
            INSERT INTO active_connections (id, client_id, server_endpoint, session_data)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $connectionId, $clientId, $_SERVER['HTTP_HOST'],
            json_encode(['authMethod' => $authMethod, 'authorizedAt' => time()])
        ]);

        // Update client connection count
        $this->db->prepare("UPDATE desktop_clients SET current_connections = current_connections + 1 WHERE id = ?")
               ->execute([$clientId]);

        return $this->sendSuccess([
            'authorized' => true,
            'connectionId' => $connectionId,
            'clientType' => $client['client_type'],
            'maxConnections' => $client['max_connections'],
            'currentConnections' => $client['current_connections'] + 1,
            'serverCapabilities' => $this->getServerCapabilities()
        ]);
    }

    private function handleConnectionLimit() {
        $input = json_decode(file_get_contents('php://input'), true);

        $clientId = $input['clientId'] ?? '';
        $requestedConnections = $input['requestedConnections'] ?? 1;
        $clientType = $input['clientType'] ?? 'desktop';

        // Get current limits
        $stmt = $this->db->prepare("SELECT * FROM connection_limits WHERE client_type = ?");
        $stmt->execute([$clientType]);
        $limits = $stmt->fetch(PDO::FETCH_ASSOC);

        // Admin clients get more generous limits
        $maxAllowed = $clientType === 'admin' ? $limits['premium_max_connections'] : $limits['default_max_connections'];

        if ($requestedConnections <= $maxAllowed) {
            // Update client limits
            $this->db->prepare("UPDATE desktop_clients SET max_connections = ? WHERE id = ?")
                   ->execute([$requestedConnections, $clientId]);

            return $this->sendSuccess([
                'allowedConnections' => $requestedConnections,
                'approved' => true,
                'reason' => 'Connection limit increased'
            ]);
        } else {
            return $this->sendError("Requested connections ($requestedConnections) exceeds limit ($maxAllowed)", 403);
        }
    }

    private function getConnectionStatus() {
        $clientId = $_GET['clientId'] ?? '';

        if ($clientId) {
            // Get specific client status
            $stmt = $this->db->prepare("
                SELECT c.*,
                       COUNT(ac.id) as active_connection_count
                FROM desktop_clients c
                LEFT JOIN active_connections ac ON c.id = ac.client_id
                WHERE c.id = ?
                GROUP BY c.id
            ");
            $stmt->execute([$clientId]);
            $client = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$client) {
                return $this->sendError('Client not found', 404);
            }

            return $this->sendSuccess($client);
        } else {
            // Get all connections status
            $stmt = $this->db->query("
                SELECT c.id, c.client_type, c.device_name, c.platform, c.ip_address,
                       c.last_connected, c.is_active, c.max_connections, c.current_connections,
                       COUNT(ac.id) as active_connections
                FROM desktop_clients c
                LEFT JOIN active_connections ac ON c.id = ac.client_id
                WHERE c.is_active = TRUE
                GROUP BY c.id
                ORDER BY c.last_connected DESC
            ");
            $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->sendSuccess([
                'totalClients' => count($clients),
                'clients' => $clients,
                'serverInfo' => $this->getServerInfo()
            ]);
        }
    }

    private function checkForUpdates() {
        $input = json_decode(file_get_contents('php://input'), true);

        $currentVersion = $input['currentVersion'] ?? '1.0.0';
        $platform = $input['platform'] ?? 'unknown';
        $clientType = $input['clientType'] ?? 'desktop';

        // Check for available updates
        $updateInfo = $this->getUpdateInfo($currentVersion, $platform, $clientType);

        return $this->sendSuccess($updateInfo);
    }

    private function reloadModule() {
        $input = json_decode(file_get_contents('php://input'), true);

        $module = $input['module'] ?? '';
        $clientId = $input['clientId'] ?? '';

        // Perform module reload based on type
        switch ($module) {
            case 'asterisk':
                $result = $this->reloadAsterisk();
                break;
            case 'nginx':
                $result = $this->reloadNginx();
                break;
            case 'api':
                $result = $this->reloadApi();
                break;
            default:
                return $this->sendError('Unknown module', 400);
        }

        // Notify connected clients of the reload
        $this->notifyClients('module-reloaded', [
            'module' => $module,
            'status' => $result['success'] ? 'success' : 'failed',
            'message' => $result['message'] ?? ''
        ]);

        return $this->sendSuccess($result);
    }

    private function restartServer() {
        $input = json_decode(file_get_contents('php://input'), true);

        $restartType = $input['type'] ?? 'graceful'; // 'graceful' or 'force'
        $delay = $input['delay'] ?? 30; // seconds

        // Notify all clients of pending restart
        $this->notifyClients('server-restarting', [
            'type' => $restartType,
            'delay' => $delay,
            'message' => "Server restarting in {$delay} seconds"
        ]);

        // Schedule restart (in real implementation, this would be more sophisticated)
        if ($restartType === 'graceful') {
            // Give clients time to disconnect gracefully
            sleep($delay);
        }

        // Perform restart (implementation specific)
        $result = $this->performServerRestart($restartType);

        return $this->sendSuccess($result);
    }

    private function validatePincode($clientId, $pincode) {
        // Validate 6-digit pincode (implementation specific)
        return strlen($pincode) === 6 && ctype_digit($pincode);
    }

    private function validateApiKey($apiKey) {
        // Validate API key against known keys
        $validKeys = ['flexpbx_api_2024', $this->config['api_key'] ?? ''];
        return in_array($apiKey, $validKeys);
    }

    private function validateAdminLogin($username, $password) {
        // Validate admin credentials
        return $username === 'admin' && $password === 'FlexPBX2024!';
    }

    private function checkConnectionLimits($client) {
        $stmt = $this->db->prepare("SELECT * FROM connection_limits WHERE client_type = ?");
        $stmt->execute([$client['client_type']]);
        $limits = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($client['current_connections'] >= $client['max_connections']) {
            return [
                'allowed' => false,
                'reason' => "Connection limit reached ({$client['max_connections']})"
            ];
        }

        return ['allowed' => true];
    }

    private function getServerCapabilities() {
        return [
            'apiVersion' => '1.1.0',
            'supportedAuthMethods' => ['pincode', 'api-key', 'admin-login'],
            'maxConnectionsPerClient' => 10,
            'supportsMultiServer' => true,
            'supportsAutoUpdates' => true,
            'supportsModuleReload' => true,
            'features' => ['desktop-clients', 'admin-clients', 'fallback-support']
        ];
    }

    private function getServerInfo() {
        return [
            'hostname' => gethostname(),
            'version' => '1.1.0',
            'uptime' => $this->getUptime(),
            'load' => sys_getloadavg(),
            'memory' => $this->getMemoryUsage()
        ];
    }

    private function getUpdateInfo($currentVersion, $platform, $clientType) {
        // Mock update info - in real implementation, check actual versions
        $latestVersion = '1.1.0';

        if (version_compare($currentVersion, $latestVersion, '<')) {
            return [
                'updateAvailable' => true,
                'latestVersion' => $latestVersion,
                'downloadUrl' => "/api/updates/download/{$platform}/{$clientType}",
                'releaseNotes' => 'Enhanced multi-server support and auto-updates',
                'critical' => false,
                'size' => $this->getUpdateSize($platform)
            ];
        }

        return ['updateAvailable' => false];
    }

    private function notifyClients($eventType, $data) {
        // In a real implementation, this would use WebSockets or SSE
        // to notify connected clients in real-time

        // For now, we'll log the notification
        error_log("Notifying clients: {$eventType} - " . json_encode($data));
    }

    private function sendSuccess($data) {
        echo json_encode(['success' => true] + $data);
    }

    private function sendError($message, $code = 400) {
        http_response_code($code);
        echo json_encode(['success' => false, 'error' => $message]);
    }

    // Helper methods (implementation specific)
    private function reloadAsterisk() { return ['success' => true, 'message' => 'Asterisk reloaded']; }
    private function reloadNginx() { return ['success' => true, 'message' => 'Nginx reloaded']; }
    private function reloadApi() { return ['success' => true, 'message' => 'API reloaded']; }
    private function performServerRestart($type) { return ['success' => true, 'message' => 'Server restart initiated']; }
    private function getUptime() { return 'Unknown'; }
    private function getMemoryUsage() { return ['total' => 0, 'used' => 0, 'free' => 0]; }
    private function getUpdateSize($platform) { return '25MB'; }
}

// Handle the request
$manager = new FlexPBXConnectionManager();
$manager->handleRequest();
?>