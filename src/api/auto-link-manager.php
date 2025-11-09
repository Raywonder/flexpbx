<?php
/**
 * FlexPBX Auto-Link Authorization Manager
 * Handles automatic linking and authorization of remote desktops
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

class AutoLinkManager {
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

        $this->initializeTables();
    }

    private function initializeTables() {
        // Create auto_link_requests table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS auto_link_requests (
                id VARCHAR(255) PRIMARY KEY,
                requesting_client_id VARCHAR(255) NOT NULL,
                target_server VARCHAR(255),
                request_type ENUM('admin_auth', 'desktop_auth', 'server_fallback') NOT NULL,
                status ENUM('pending', 'approved', 'denied', 'expired') DEFAULT 'pending',
                requested_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                processed_at DATETIME NULL,
                processed_by VARCHAR(255),
                auto_approved BOOLEAN DEFAULT FALSE,
                approval_reason TEXT,
                expires_at DATETIME,
                metadata JSON,
                INDEX idx_requesting_client (requesting_client_id),
                INDEX idx_status (status),
                INDEX idx_expires (expires_at)
            )
        ");

        // Create authorized_links table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS authorized_links (
                id VARCHAR(255) PRIMARY KEY,
                client_id VARCHAR(255) NOT NULL,
                target_server VARCHAR(255),
                link_type ENUM('admin', 'desktop', 'fallback') NOT NULL,
                authorized_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                authorized_by VARCHAR(255),
                expires_at DATETIME NULL,
                is_active BOOLEAN DEFAULT TRUE,
                permissions JSON,
                last_used DATETIME,
                FOREIGN KEY (client_id) REFERENCES desktop_clients(id) ON DELETE CASCADE,
                INDEX idx_client_id (client_id),
                INDEX idx_active (is_active),
                INDEX idx_expires (expires_at)
            )
        ");

        // Create fallback_hierarchy table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS fallback_hierarchy (
                id VARCHAR(255) PRIMARY KEY,
                primary_server VARCHAR(255) NOT NULL,
                fallback_server VARCHAR(255) NOT NULL,
                fallback_order INT DEFAULT 1,
                is_active BOOLEAN DEFAULT TRUE,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                last_tested DATETIME,
                test_result ENUM('success', 'failed', 'timeout') NULL,
                INDEX idx_primary (primary_server),
                INDEX idx_fallback_order (fallback_order)
            )
        ");
    }

    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $pathParts = explode('/', trim($path, '/'));

        try {
            switch (end($pathParts)) {
                case 'request-auth':
                    return $this->requestAuthorization();
                case 'approve-auth':
                    return $this->approveAuthorization();
                case 'deny-auth':
                    return $this->denyAuthorization();
                case 'auto-link':
                    return $this->performAutoLink();
                case 'fallback-setup':
                    return $this->setupFallbackHierarchy();
                case 'test-fallback':
                    return $this->testFallbackConnections();
                case 'get-authorized':
                    return $this->getAuthorizedLinks();
                case 'revoke-auth':
                    return $this->revokeAuthorization();
                default:
                    return $this->sendError('Unknown endpoint', 404);
            }
        } catch (Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    private function requestAuthorization() {
        $input = json_decode(file_get_contents('php://input'), true);

        $requestId = uniqid('auth_req_');
        $clientId = $input['clientId'] ?? '';
        $targetServer = $input['targetServer'] ?? '';
        $requestType = $input['requestType'] ?? 'desktop_auth';
        $metadata = $input['metadata'] ?? [];

        // Set expiration (default 24 hours for auth requests)
        $expiresAt = date('Y-m-d H:i:s', time() + 86400);

        // Check if auto-approval is enabled for this type
        $autoApprove = $this->shouldAutoApprove($clientId, $requestType, $targetServer);

        $stmt = $this->db->prepare("
            INSERT INTO auto_link_requests
            (id, requesting_client_id, target_server, request_type, auto_approved, expires_at, metadata, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $status = $autoApprove ? 'approved' : 'pending';
        $stmt->execute([
            $requestId, $clientId, $targetServer, $requestType,
            $autoApprove, $expiresAt, json_encode($metadata), $status
        ]);

        if ($autoApprove) {
            $this->createAuthorizedLink($clientId, $targetServer, $requestType, 'auto-approved');
        }

        $response = [
            'requestId' => $requestId,
            'status' => $status,
            'autoApproved' => $autoApprove,
            'expiresAt' => $expiresAt
        ];

        if (!$autoApprove) {
            $response['message'] = 'Authorization request pending approval';
            $this->notifyAdmins('auth-request', [
                'requestId' => $requestId,
                'clientId' => $clientId,
                'requestType' => $requestType,
                'targetServer' => $targetServer
            ]);
        }

        return $this->sendSuccess($response);
    }

    private function approveAuthorization() {
        $input = json_decode(file_get_contents('php://input'), true);

        $requestId = $input['requestId'] ?? '';
        $approverId = $input['approverId'] ?? '';
        $reason = $input['reason'] ?? 'Manually approved';

        // Get the request
        $stmt = $this->db->prepare("SELECT * FROM auto_link_requests WHERE id = ? AND status = 'pending'");
        $stmt->execute([$requestId]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$request) {
            return $this->sendError('Request not found or already processed', 404);
        }

        // Update request status
        $stmt = $this->db->prepare("
            UPDATE auto_link_requests
            SET status = 'approved', processed_at = NOW(), processed_by = ?, approval_reason = ?
            WHERE id = ?
        ");
        $stmt->execute([$approverId, $reason, $requestId]);

        // Create authorized link
        $this->createAuthorizedLink(
            $request['requesting_client_id'],
            $request['target_server'],
            $request['request_type'],
            $approverId
        );

        $this->notifyClient($request['requesting_client_id'], 'auth-approved', [
            'requestId' => $requestId,
            'targetServer' => $request['target_server']
        ]);

        return $this->sendSuccess([
            'approved' => true,
            'requestId' => $requestId,
            'reason' => $reason
        ]);
    }

    private function denyAuthorization() {
        $input = json_decode(file_get_contents('php://input'), true);

        $requestId = $input['requestId'] ?? '';
        $denierId = $input['denierId'] ?? '';
        $reason = $input['reason'] ?? 'Access denied';

        $stmt = $this->db->prepare("
            UPDATE auto_link_requests
            SET status = 'denied', processed_at = NOW(), processed_by = ?, approval_reason = ?
            WHERE id = ? AND status = 'pending'
        ");
        $stmt->execute([$denierId, $reason, $requestId]);

        if ($stmt->rowCount() === 0) {
            return $this->sendError('Request not found or already processed', 404);
        }

        return $this->sendSuccess([
            'denied' => true,
            'requestId' => $requestId,
            'reason' => $reason
        ]);
    }

    private function performAutoLink() {
        $input = json_decode(file_get_contents('php://input'), true);

        $clientId = $input['clientId'] ?? '';
        $targetServer = $input['targetServer'] ?? '';
        $linkType = $input['linkType'] ?? 'desktop';

        // Check if client is authorized for this server
        $stmt = $this->db->prepare("
            SELECT * FROM authorized_links
            WHERE client_id = ? AND target_server = ? AND is_active = TRUE
            AND (expires_at IS NULL OR expires_at > NOW())
        ");
        $stmt->execute([$clientId, $targetServer]);
        $authorization = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$authorization) {
            // Request authorization automatically
            $requestResult = $this->requestAuthorization();

            if (!json_decode($requestResult, true)['success']) {
                return $this->sendError('Auto-link failed: Authorization required', 403);
            }
        }

        // Update last used timestamp
        $stmt = $this->db->prepare("UPDATE authorized_links SET last_used = NOW() WHERE id = ?");
        $stmt->execute([$authorization['id']]);

        return $this->sendSuccess([
            'autoLinked' => true,
            'clientId' => $clientId,
            'targetServer' => $targetServer,
            'linkType' => $linkType,
            'authorization' => $authorization
        ]);
    }

    private function setupFallbackHierarchy() {
        $input = json_decode(file_get_contents('php://input'), true);

        $primaryServer = $input['primaryServer'] ?? '';
        $fallbackServers = $input['fallbackServers'] ?? [];

        // Clear existing fallback hierarchy for this primary server
        $stmt = $this->db->prepare("DELETE FROM fallback_hierarchy WHERE primary_server = ?");
        $stmt->execute([$primaryServer]);

        // Insert new fallback hierarchy
        foreach ($fallbackServers as $index => $fallbackServer) {
            $hierarchyId = uniqid('fallback_');
            $stmt = $this->db->prepare("
                INSERT INTO fallback_hierarchy (id, primary_server, fallback_server, fallback_order)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$hierarchyId, $primaryServer, $fallbackServer, $index + 1]);
        }

        return $this->sendSuccess([
            'fallbackHierarchySet' => true,
            'primaryServer' => $primaryServer,
            'fallbackServers' => $fallbackServers
        ]);
    }

    private function testFallbackConnections() {
        $input = json_decode(file_get_contents('php://input'), true);
        $primaryServer = $input['primaryServer'] ?? '';

        $stmt = $this->db->prepare("
            SELECT * FROM fallback_hierarchy
            WHERE primary_server = ? AND is_active = TRUE
            ORDER BY fallback_order
        ");
        $stmt->execute([$primaryServer]);
        $fallbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = [];
        foreach ($fallbacks as $fallback) {
            $testResult = $this->testServerConnection($fallback['fallback_server']);

            // Update test result
            $stmt = $this->db->prepare("
                UPDATE fallback_hierarchy
                SET last_tested = NOW(), test_result = ?
                WHERE id = ?
            ");
            $stmt->execute([$testResult['status'], $fallback['id']]);

            $results[] = [
                'server' => $fallback['fallback_server'],
                'order' => $fallback['fallback_order'],
                'status' => $testResult['status'],
                'responseTime' => $testResult['responseTime'] ?? null
            ];
        }

        return $this->sendSuccess([
            'primaryServer' => $primaryServer,
            'fallbackTests' => $results
        ]);
    }

    private function getAuthorizedLinks() {
        $clientId = $_GET['clientId'] ?? '';

        if ($clientId) {
            $stmt = $this->db->prepare("
                SELECT al.*, ar.request_type
                FROM authorized_links al
                LEFT JOIN auto_link_requests ar ON al.client_id = ar.requesting_client_id
                WHERE al.client_id = ? AND al.is_active = TRUE
                AND (al.expires_at IS NULL OR al.expires_at > NOW())
            ");
            $stmt->execute([$clientId]);
        } else {
            $stmt = $this->db->query("
                SELECT al.*, dc.device_name, dc.client_type
                FROM authorized_links al
                JOIN desktop_clients dc ON al.client_id = dc.id
                WHERE al.is_active = TRUE
                AND (al.expires_at IS NULL OR al.expires_at > NOW())
                ORDER BY al.authorized_at DESC
            ");
        }

        $links = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->sendSuccess([
            'authorizedLinks' => $links,
            'totalCount' => count($links)
        ]);
    }

    private function revokeAuthorization() {
        $input = json_decode(file_get_contents('php://input'), true);

        $linkId = $input['linkId'] ?? '';
        $reason = $input['reason'] ?? 'Access revoked';

        $stmt = $this->db->prepare("UPDATE authorized_links SET is_active = FALSE WHERE id = ?");
        $stmt->execute([$linkId]);

        if ($stmt->rowCount() === 0) {
            return $this->sendError('Authorization not found', 404);
        }

        return $this->sendSuccess([
            'revoked' => true,
            'linkId' => $linkId,
            'reason' => $reason
        ]);
    }

    // Helper methods
    private function shouldAutoApprove($clientId, $requestType, $targetServer) {
        // Check client permissions and server settings
        $stmt = $this->db->prepare("SELECT client_type FROM desktop_clients WHERE id = ?");
        $stmt->execute([$clientId]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);

        // Admin clients get auto-approval for most requests
        if ($client && $client['client_type'] === 'admin') {
            return true;
        }

        // Desktop clients need approval for admin actions
        if ($requestType === 'admin_auth') {
            return false;
        }

        // Auto-approve fallback connections for known clients
        return $requestType === 'server_fallback';
    }

    private function createAuthorizedLink($clientId, $targetServer, $requestType, $authorizedBy) {
        $linkId = uniqid('link_');
        $linkType = $requestType === 'admin_auth' ? 'admin' : 'desktop';

        // Set expiration based on link type
        $expiresAt = $linkType === 'admin' ?
            date('Y-m-d H:i:s', time() + (30 * 86400)) : // 30 days for admin
            null; // No expiration for desktop

        $permissions = $this->getDefaultPermissions($linkType);

        $stmt = $this->db->prepare("
            INSERT INTO authorized_links
            (id, client_id, target_server, link_type, authorized_by, expires_at, permissions)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $linkId, $clientId, $targetServer, $linkType,
            $authorizedBy, $expiresAt, json_encode($permissions)
        ]);

        return $linkId;
    }

    private function getDefaultPermissions($linkType) {
        switch ($linkType) {
            case 'admin':
                return [
                    'manage_clients' => true,
                    'module_reload' => true,
                    'server_restart' => true,
                    'view_logs' => true,
                    'update_management' => true
                ];
            case 'desktop':
                return [
                    'connect' => true,
                    'view_status' => true,
                    'update_check' => true
                ];
            default:
                return ['connect' => true];
        }
    }

    private function testServerConnection($serverUrl) {
        $startTime = microtime(true);

        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 5,
                    'method' => 'GET'
                ]
            ]);

            $response = @file_get_contents($serverUrl . '/api/status', false, $context);
            $responseTime = round((microtime(true) - $startTime) * 1000);

            return [
                'status' => $response !== false ? 'success' : 'failed',
                'responseTime' => $responseTime
            ];
        } catch (Exception $e) {
            return [
                'status' => 'failed',
                'responseTime' => round((microtime(true) - $startTime) * 1000)
            ];
        }
    }

    private function notifyAdmins($eventType, $data) {
        // Implementation for notifying admin clients
        error_log("Admin notification: {$eventType} - " . json_encode($data));
    }

    private function notifyClient($clientId, $eventType, $data) {
        // Implementation for notifying specific client
        error_log("Client notification for {$clientId}: {$eventType} - " . json_encode($data));
    }

    private function sendSuccess($data) {
        echo json_encode(['success' => true] + $data);
    }

    private function sendError($message, $code = 400) {
        http_response_code($code);
        echo json_encode(['success' => false, 'error' => $message]);
    }
}

// Handle the request
$manager = new AutoLinkManager();
$manager->handleRequest();
?>