<?php
/**
 * FlexPBX Mattermost Integration API
 * Provides comprehensive Mattermost API integration for FlexPBX
 *
 * @author FlexPBX Development Team
 * @version 1.0.0
 * @created 2025-11-06
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// CORS Headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Load configuration
$config = require_once(__DIR__ . '/config.php');

/**
 * Mattermost API Integration Class
 */
class MattermostIntegration {
    private $db;
    private $config;
    private $serverUrl;
    private $accessToken;
    private $botToken;

    public function __construct($config) {
        $this->config = $config;
        $this->connectDatabase();
        $this->loadConfiguration();
    }

    /**
     * Connect to database
     */
    private function connectDatabase() {
        try {
            $this->db = new PDO(
                "mysql:host={$this->config['db_host']};dbname={$this->config['db_name']};charset=utf8mb4",
                $this->config['db_user'],
                $this->config['db_password'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            $this->sendError('Database connection failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Load Mattermost configuration from database
     */
    private function loadConfiguration() {
        try {
            $stmt = $this->db->query("SELECT * FROM mattermost_config ORDER BY id DESC LIMIT 1");
            $config = $stmt->fetch();

            if ($config) {
                $this->serverUrl = rtrim($config['server_url'], '/');
                $this->accessToken = $config['access_token'];
                $this->botToken = $config['bot_token'];
            } else {
                // Default configuration
                $this->serverUrl = 'https://chat.tappedin.fm';
                $this->accessToken = null;
                $this->botToken = null;
            }
        } catch (PDOException $e) {
            $this->sendError('Failed to load configuration: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Make API request to Mattermost
     */
    private function makeRequest($endpoint, $method = 'GET', $data = null, $useBot = false) {
        $url = $this->serverUrl . '/api/v4' . $endpoint;
        $token = $useBot ? $this->botToken : $this->accessToken;

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['error' => 'cURL Error: ' . $error, 'http_code' => 0];
        }

        $result = json_decode($response, true);
        $result['http_code'] = $httpCode;

        return $result;
    }

    /**
     * Test connection to Mattermost server
     */
    public function testConnection($serverUrl, $token) {
        $url = rtrim($serverUrl, '/') . '/api/v4/users/me';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'message' => 'Connection error: ' . $error];
        }

        if ($httpCode === 200) {
            $user = json_decode($response, true);
            return [
                'success' => true,
                'message' => 'Connected successfully',
                'user' => $user
            ];
        } else {
            return ['success' => false, 'message' => 'Authentication failed', 'http_code' => $httpCode];
        }
    }

    /**
     * Get all teams
     */
    public function getTeams() {
        $result = $this->makeRequest('/users/me/teams');

        if (isset($result['error'])) {
            return ['success' => false, 'error' => $result['error']];
        }

        return ['success' => true, 'teams' => $result];
    }

    /**
     * Get channels for a team
     */
    public function getChannels($teamId) {
        $result = $this->makeRequest("/users/me/teams/{$teamId}/channels");

        if (isset($result['error'])) {
            return ['success' => false, 'error' => $result['error']];
        }

        return ['success' => true, 'channels' => $result];
    }

    /**
     * Get channel by ID
     */
    public function getChannel($channelId) {
        $result = $this->makeRequest("/channels/{$channelId}");

        if (isset($result['error'])) {
            return ['success' => false, 'error' => $result['error']];
        }

        return ['success' => true, 'channel' => $result];
    }

    /**
     * Get messages from a channel
     */
    public function getMessages($channelId, $page = 0, $perPage = 60) {
        $result = $this->makeRequest("/channels/{$channelId}/posts?page={$page}&per_page={$perPage}");

        if (isset($result['error'])) {
            return ['success' => false, 'error' => $result['error']];
        }

        // Cache messages
        $this->cacheMessages($channelId, $result);

        return ['success' => true, 'posts' => $result];
    }

    /**
     * Post a message to a channel
     */
    public function postMessage($channelId, $message, $userId = null) {
        $data = [
            'channel_id' => $channelId,
            'message' => $message
        ];

        $result = $this->makeRequest('/posts', 'POST', $data);

        if (isset($result['error']) || $result['http_code'] !== 201) {
            return ['success' => false, 'error' => $result['error'] ?? 'Failed to post message'];
        }

        // Log activity
        $this->logActivity('post_message', $userId, $channelId, ['message_id' => $result['id']]);

        return ['success' => true, 'post' => $result];
    }

    /**
     * Get user information
     */
    public function getUser($userId) {
        $result = $this->makeRequest("/users/{$userId}");

        if (isset($result['error'])) {
            return ['success' => false, 'error' => $result['error']];
        }

        return ['success' => true, 'user' => $result];
    }

    /**
     * Get current authenticated user
     */
    public function getCurrentUser() {
        $result = $this->makeRequest('/users/me');

        if (isset($result['error'])) {
            return ['success' => false, 'error' => $result['error']];
        }

        return ['success' => true, 'user' => $result];
    }

    /**
     * Search messages
     */
    public function searchMessages($teamId, $terms) {
        $data = ['terms' => $terms];
        $result = $this->makeRequest("/teams/{$teamId}/posts/search", 'POST', $data);

        if (isset($result['error'])) {
            return ['success' => false, 'error' => $result['error']];
        }

        return ['success' => true, 'results' => $result];
    }

    /**
     * Cache messages for offline access
     */
    private function cacheMessages($channelId, $posts) {
        try {
            if (!isset($posts['posts']) || !is_array($posts['posts'])) {
                return;
            }

            $stmt = $this->db->prepare("
                INSERT INTO mattermost_message_cache
                (message_id, channel_id, user_id, username, message_text, create_at, update_at, delete_at, file_ids, metadata)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    message_text = VALUES(message_text),
                    update_at = VALUES(update_at),
                    delete_at = VALUES(delete_at),
                    cached_at = CURRENT_TIMESTAMP
            ");

            foreach ($posts['posts'] as $post) {
                $stmt->execute([
                    $post['id'],
                    $channelId,
                    $post['user_id'] ?? null,
                    isset($posts['users'][$post['user_id']]) ? $posts['users'][$post['user_id']]['username'] : null,
                    $post['message'],
                    $post['create_at'],
                    $post['update_at'],
                    $post['delete_at'] ?? 0,
                    isset($post['file_ids']) ? json_encode($post['file_ids']) : null,
                    json_encode($post['metadata'] ?? [])
                ]);
            }
        } catch (PDOException $e) {
            error_log('Failed to cache messages: ' . $e->getMessage());
        }
    }

    /**
     * Log activity
     */
    private function logActivity($actionType, $userId, $channelId, $details = []) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO mattermost_activity_log (action_type, user_id, channel_id, details, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $actionType,
                $userId,
                $channelId,
                json_encode($details),
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
        } catch (PDOException $e) {
            error_log('Failed to log activity: ' . $e->getMessage());
        }
    }

    /**
     * Save configuration
     */
    public function saveConfiguration($data) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO mattermost_config (server_url, access_token, bot_token, default_team_id, default_channel_id, enable_notifications, enable_websocket, poll_interval, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    server_url = VALUES(server_url),
                    access_token = VALUES(access_token),
                    bot_token = VALUES(bot_token),
                    default_team_id = VALUES(default_team_id),
                    default_channel_id = VALUES(default_channel_id),
                    enable_notifications = VALUES(enable_notifications),
                    enable_websocket = VALUES(enable_websocket),
                    poll_interval = VALUES(poll_interval),
                    updated_at = CURRENT_TIMESTAMP
            ");

            $stmt->execute([
                $data['server_url'],
                $data['access_token'] ?? null,
                $data['bot_token'] ?? null,
                $data['default_team_id'] ?? null,
                $data['default_channel_id'] ?? null,
                $data['enable_notifications'] ?? true,
                $data['enable_websocket'] ?? false,
                $data['poll_interval'] ?? 5,
                $data['created_by'] ?? 'system'
            ]);

            return ['success' => true, 'message' => 'Configuration saved successfully'];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => 'Failed to save configuration: ' . $e->getMessage()];
        }
    }

    /**
     * Get visible channels from database
     */
    public function getVisibleChannels() {
        try {
            $stmt = $this->db->query("
                SELECT * FROM mattermost_channels
                WHERE is_visible = 1
                ORDER BY sort_order ASC, channel_display_name ASC
            ");

            return ['success' => true, 'channels' => $stmt->fetchAll()];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => 'Failed to get channels: ' . $e->getMessage()];
        }
    }

    /**
     * Save channel to database
     */
    public function saveChannel($channelData) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO mattermost_channels
                (channel_id, channel_name, channel_display_name, team_id, team_name, channel_type, is_visible, is_default, allowed_roles, sort_order, description)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    channel_display_name = VALUES(channel_display_name),
                    is_visible = VALUES(is_visible),
                    is_default = VALUES(is_default),
                    allowed_roles = VALUES(allowed_roles),
                    sort_order = VALUES(sort_order),
                    description = VALUES(description),
                    last_synced = CURRENT_TIMESTAMP
            ");

            $stmt->execute([
                $channelData['channel_id'],
                $channelData['channel_name'],
                $channelData['channel_display_name'],
                $channelData['team_id'],
                $channelData['team_name'] ?? null,
                $channelData['channel_type'] ?? 'O',
                $channelData['is_visible'] ?? true,
                $channelData['is_default'] ?? false,
                isset($channelData['allowed_roles']) ? json_encode($channelData['allowed_roles']) : null,
                $channelData['sort_order'] ?? 0,
                $channelData['description'] ?? null
            ]);

            return ['success' => true, 'message' => 'Channel saved successfully'];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => 'Failed to save channel: ' . $e->getMessage()];
        }
    }

    /**
     * Send JSON response
     */
    private function sendResponse($data, $code = 200) {
        http_response_code($code);
        echo json_encode($data, JSON_PRETTY_PRINT);
        exit();
    }

    /**
     * Send error response
     */
    private function sendError($message, $code = 400) {
        $this->sendResponse(['success' => false, 'error' => $message], $code);
    }

    /**
     * Handle API requests
     */
    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        $requestUri = $_SERVER['REQUEST_URI'];
        $action = $_GET['action'] ?? $_POST['action'] ?? null;

        if (!$action) {
            $this->sendError('Action parameter is required');
        }

        // Handle different actions
        switch ($action) {
            case 'test_connection':
                $serverUrl = $_POST['server_url'] ?? null;
                $token = $_POST['token'] ?? null;

                if (!$serverUrl || !$token) {
                    $this->sendError('Server URL and token are required');
                }

                $result = $this->testConnection($serverUrl, $token);
                $this->sendResponse($result);
                break;

            case 'get_teams':
                $result = $this->getTeams();
                $this->sendResponse($result);
                break;

            case 'get_channels':
                $teamId = $_GET['team_id'] ?? null;

                if (!$teamId) {
                    $this->sendError('Team ID is required');
                }

                $result = $this->getChannels($teamId);
                $this->sendResponse($result);
                break;

            case 'get_messages':
                $channelId = $_GET['channel_id'] ?? null;
                $page = $_GET['page'] ?? 0;
                $perPage = $_GET['per_page'] ?? 60;

                if (!$channelId) {
                    $this->sendError('Channel ID is required');
                }

                $result = $this->getMessages($channelId, $page, $perPage);
                $this->sendResponse($result);
                break;

            case 'post_message':
                $input = json_decode(file_get_contents('php://input'), true);
                $channelId = $input['channel_id'] ?? null;
                $message = $input['message'] ?? null;
                $userId = $input['user_id'] ?? null;

                if (!$channelId || !$message) {
                    $this->sendError('Channel ID and message are required');
                }

                $result = $this->postMessage($channelId, $message, $userId);
                $this->sendResponse($result);
                break;

            case 'get_user':
                $userId = $_GET['user_id'] ?? null;

                if (!$userId) {
                    $this->sendError('User ID is required');
                }

                $result = $this->getUser($userId);
                $this->sendResponse($result);
                break;

            case 'get_current_user':
                $result = $this->getCurrentUser();
                $this->sendResponse($result);
                break;

            case 'search_messages':
                $teamId = $_POST['team_id'] ?? null;
                $terms = $_POST['terms'] ?? null;

                if (!$teamId || !$terms) {
                    $this->sendError('Team ID and search terms are required');
                }

                $result = $this->searchMessages($teamId, $terms);
                $this->sendResponse($result);
                break;

            case 'save_config':
                $input = json_decode(file_get_contents('php://input'), true);
                $result = $this->saveConfiguration($input);
                $this->sendResponse($result);
                break;

            case 'get_visible_channels':
                $result = $this->getVisibleChannels();
                $this->sendResponse($result);
                break;

            case 'save_channel':
                $input = json_decode(file_get_contents('php://input'), true);
                $result = $this->saveChannel($input);
                $this->sendResponse($result);
                break;

            default:
                $this->sendError('Invalid action: ' . $action);
        }
    }
}

// Initialize and handle request
try {
    $mattermost = new MattermostIntegration($config);
    $mattermost->handleRequest();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}
