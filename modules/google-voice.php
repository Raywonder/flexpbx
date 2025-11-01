<?php
/**
 * FlexPBX Google Voice Integration Module
 * Handles Google Voice API integration, OAuth2, SMS, voicemail, and call routing
 *
 * @package FlexPBX
 * @module GoogleVoice
 * @version 2.1.0
 * @author Devine Creations LLC
 */

require_once '../config.php';
require_once '../auth.php';

class GoogleVoiceModule {
    private $db;
    private $config;
    private $oauth_credentials;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->loadConfiguration();
        $this->initializeOAuth();
    }

    /**
     * Load Google Voice configuration from database
     */
    private function loadConfiguration() {
        $stmt = $this->db->prepare("SELECT * FROM google_voice_config WHERE id = 1");
        $stmt->execute();
        $this->config = $stmt->fetch(PDO::FETCH_ASSOC);

        // Default configuration if none exists
        if (!$this->config) {
            $this->createDefaultConfiguration();
        }
    }

    /**
     * Create default Google Voice configuration
     */
    private function createDefaultConfiguration() {
        $default_config = [
            'enabled' => false,
            'primary_number' => '12813015784',
            'display_name' => 'FlexPBX Main Line',
            'enable_sms' => true,
            'enable_voicemail' => true,
            'enable_call_forwarding' => false,
            'auto_transcribe' => true,
            'email_notifications' => true,
            'sms_notifications' => false,
            'forward_extension' => null,
            'greeting_message' => 'Thank you for calling FlexPBX. Please leave a message after the tone.',
            'default_destination' => '101',
            'enable_call_screening' => false,
            'enable_call_recording' => true,
            'business_start' => '09:00',
            'business_end' => '18:00',
            'after_hours_destination' => 'voicemail',
            'call_limit_daily' => 1000,
            'sms_limit_daily' => 500,
            'retry_attempts' => 3,
            'auto_reply_enabled' => false,
            'auto_reply_message' => 'Thank you for contacting FlexPBX! We\'ll respond during business hours (Mon-Fri 9 AM - 6 PM EST).',
            'business_hours_only' => true
        ];

        $stmt = $this->db->prepare("INSERT INTO google_voice_config (config_data, created_at, updated_at) VALUES (?, NOW(), NOW())");
        $stmt->execute([json_encode($default_config)]);

        $this->config = [
            'id' => $this->db->lastInsertId(),
            'config_data' => json_encode($default_config),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Initialize OAuth2 credentials
     */
    private function initializeOAuth() {
        $stmt = $this->db->prepare("SELECT * FROM oauth_credentials WHERE provider = 'google_voice' AND active = 1");
        $stmt->execute();
        $this->oauth_credentials = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Handle API requests
     */
    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = $_GET['path'] ?? '';

        header('Content-Type: application/json');

        try {
            switch ($method) {
                case 'GET':
                    $this->handleGetRequest($path);
                    break;
                case 'POST':
                    $this->handlePostRequest($path);
                    break;
                case 'PUT':
                    $this->handlePutRequest($path);
                    break;
                case 'DELETE':
                    $this->handleDeleteRequest($path);
                    break;
                default:
                    http_response_code(405);
                    echo json_encode(['error' => 'Method not allowed']);
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Handle GET requests
     */
    private function handleGetRequest($path) {
        switch ($path) {
            case 'status':
                echo json_encode($this->getConnectionStatus());
                break;
            case 'usage-stats':
                echo json_encode($this->getUsageStats());
                break;
            case 'voicemails':
                echo json_encode($this->getVoicemails());
                break;
            case 'sms-conversations':
                echo json_encode($this->getSMSConversations());
                break;
            case 'call-logs':
                echo json_encode($this->getCallLogs());
                break;
            case 'config':
                echo json_encode($this->getConfiguration());
                break;
            default:
                if (preg_match('/^sms\/(.+)$/', $path, $matches)) {
                    $phoneNumber = urldecode($matches[1]);
                    echo json_encode($this->getSMSConversation($phoneNumber));
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Endpoint not found']);
                }
        }
    }

    /**
     * Handle POST requests
     */
    private function handlePostRequest($path) {
        $input = json_decode(file_get_contents('php://input'), true);

        switch ($path) {
            case 'auth-url':
                echo json_encode($this->generateAuthUrl($input));
                break;
            case 'callback':
                echo json_encode($this->handleOAuthCallback($input));
                break;
            case 'disconnect':
                echo json_encode($this->disconnectAccount());
                break;
            case 'refresh-token':
                echo json_encode($this->refreshAccessToken());
                break;
            case 'sync':
                echo json_encode($this->syncWithGoogleVoice());
                break;
            case 'sms/send':
                echo json_encode($this->sendSMS($input));
                break;
            case 'test-connection':
                echo json_encode($this->testConnection());
                break;
            default:
                if (preg_match('/^voicemail\/(.+)\/play$/', $path, $matches)) {
                    $vmId = $matches[1];
                    $this->playVoicemail($vmId);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Endpoint not found']);
                }
        }
    }

    /**
     * Generate OAuth2 authorization URL
     */
    private function generateAuthUrl($params) {
        $client_id = $_ENV['GOOGLE_OAUTH_CLIENT_ID'] ?? 'your-client-id';
        $redirect_uri = $params['redirect_uri'] ?? '';
        $scopes = implode(' ', $params['scopes'] ?? [
            'https://www.googleapis.com/auth/voice',
            'https://www.googleapis.com/auth/voice.sms'
        ]);

        $state = bin2hex(random_bytes(16));
        $_SESSION['oauth_state'] = $state;

        $auth_url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id' => $client_id,
            'redirect_uri' => $redirect_uri,
            'scope' => $scopes,
            'response_type' => 'code',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state
        ]);

        return [
            'success' => true,
            'auth_url' => $auth_url,
            'state' => $state
        ];
    }

    /**
     * Handle OAuth2 callback
     */
    private function handleOAuthCallback($params) {
        $code = $params['code'] ?? '';
        $state = $params['state'] ?? '';

        if (empty($code) || $state !== $_SESSION['oauth_state']) {
            return ['success' => false, 'error' => 'Invalid OAuth callback'];
        }

        // Exchange code for tokens
        $token_url = 'https://oauth2.googleapis.com/token';
        $token_data = [
            'client_id' => $_ENV['GOOGLE_OAUTH_CLIENT_ID'],
            'client_secret' => $_ENV['GOOGLE_OAUTH_CLIENT_SECRET'],
            'redirect_uri' => $params['redirect_uri'],
            'grant_type' => 'authorization_code',
            'code' => $code
        ];

        $response = $this->makeHttpRequest($token_url, $token_data);
        $tokens = json_decode($response, true);

        if (!isset($tokens['access_token'])) {
            return ['success' => false, 'error' => 'Failed to obtain access token'];
        }

        // Store tokens in database
        $this->storeOAuthTokens($tokens);

        // Get user info
        $user_info = $this->getUserInfo($tokens['access_token']);

        return [
            'success' => true,
            'message' => 'Google Voice connected successfully',
            'user_info' => $user_info
        ];
    }

    /**
     * Store OAuth tokens in database
     */
    private function storeOAuthTokens($tokens) {
        $stmt = $this->db->prepare("
            INSERT INTO oauth_credentials (provider, access_token, refresh_token, expires_in, scope, created_at, updated_at, active)
            VALUES ('google_voice', ?, ?, ?, ?, NOW(), NOW(), 1)
            ON DUPLICATE KEY UPDATE
            access_token = VALUES(access_token),
            refresh_token = VALUES(refresh_token),
            expires_in = VALUES(expires_in),
            scope = VALUES(scope),
            updated_at = NOW(),
            active = 1
        ");

        $stmt->execute([
            $tokens['access_token'],
            $tokens['refresh_token'] ?? null,
            $tokens['expires_in'] ?? 3600,
            $tokens['scope'] ?? 'voice'
        ]);
    }

    /**
     * Get user information from Google API
     */
    private function getUserInfo($access_token) {
        $url = 'https://www.googleapis.com/oauth2/v2/userinfo?access_token=' . $access_token;
        $response = $this->makeHttpRequest($url, null, 'GET');
        return json_decode($response, true);
    }

    /**
     * Get connection status
     */
    private function getConnectionStatus() {
        if (!$this->oauth_credentials) {
            return [
                'connected' => false,
                'status' => 'disconnected',
                'message' => 'Google Voice not connected'
            ];
        }

        // Check if token is still valid
        $token_age = time() - strtotime($this->oauth_credentials['updated_at']);
        $expires_in = $this->oauth_credentials['expires_in'] ?? 3600;

        if ($token_age > $expires_in) {
            return [
                'connected' => false,
                'status' => 'expired',
                'message' => 'Access token expired'
            ];
        }

        return [
            'connected' => true,
            'status' => 'connected',
            'primary_number' => $this->formatPhoneNumber($this->getConfigValue('primary_number')),
            'account_email' => $this->oauth_credentials['user_email'] ?? 'Unknown',
            'last_sync' => $this->oauth_credentials['updated_at'],
            'expires_at' => date('Y-m-d H:i:s', strtotime($this->oauth_credentials['updated_at']) + $expires_in)
        ];
    }

    /**
     * Get usage statistics
     */
    private function getUsageStats() {
        $today = date('Y-m-d');

        // Get call statistics
        $stmt = $this->db->prepare("
            SELECT
                COUNT(*) as calls_today,
                SUM(CASE WHEN direction = 'inbound' THEN 1 ELSE 0 END) as inbound_calls,
                SUM(CASE WHEN direction = 'outbound' THEN 1 ELSE 0 END) as outbound_calls,
                SUM(CASE WHEN answered = 0 THEN 1 ELSE 0 END) as missed_calls
            FROM google_voice_calls
            WHERE DATE(call_time) = ?
        ");
        $stmt->execute([$today]);
        $call_stats = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get SMS statistics
        $stmt = $this->db->prepare("
            SELECT
                COUNT(*) as sms_today,
                SUM(CASE WHEN direction = 'in' THEN 1 ELSE 0 END) as sms_received,
                SUM(CASE WHEN direction = 'out' THEN 1 ELSE 0 END) as sms_sent
            FROM google_voice_sms
            WHERE DATE(message_time) = ?
        ");
        $stmt->execute([$today]);
        $sms_stats = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get voicemail count
        $stmt = $this->db->prepare("SELECT COUNT(*) as voicemail_count FROM google_voice_voicemails WHERE is_read = 0");
        $stmt->execute();
        $vm_stats = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'calls_today' => (int)$call_stats['calls_today'],
            'inbound_calls' => (int)$call_stats['inbound_calls'],
            'outbound_calls' => (int)$call_stats['outbound_calls'],
            'missed_calls' => (int)$call_stats['missed_calls'],
            'sms_today' => (int)$sms_stats['sms_today'],
            'sms_received' => (int)$sms_stats['sms_received'],
            'sms_sent' => (int)$sms_stats['sms_sent'],
            'voicemail_count' => (int)$vm_stats['voicemail_count'],
            'call_limit_daily' => (int)$this->getConfigValue('call_limit_daily'),
            'sms_limit_daily' => (int)$this->getConfigValue('sms_limit_daily')
        ];
    }

    /**
     * Send SMS message
     */
    private function sendSMS($params) {
        if (!$this->oauth_credentials) {
            return ['success' => false, 'error' => 'Google Voice not connected'];
        }

        $to = $this->cleanPhoneNumber($params['to']);
        $message = $params['message'] ?? '';

        if (empty($to) || empty($message)) {
            return ['success' => false, 'error' => 'Missing phone number or message'];
        }

        // Check daily SMS limit
        $today_count = $this->getTodaySMSCount();
        $daily_limit = (int)$this->getConfigValue('sms_limit_daily');

        if ($today_count >= $daily_limit) {
            return ['success' => false, 'error' => 'Daily SMS limit reached'];
        }

        // Send SMS via Google Voice API
        $result = $this->sendSMSViaAPI($to, $message);

        if ($result['success']) {
            // Store in database
            $stmt = $this->db->prepare("
                INSERT INTO google_voice_sms (phone_number, message_text, direction, message_time, status)
                VALUES (?, ?, 'out', NOW(), 'sent')
            ");
            $stmt->execute([$to, $message]);
        }

        return $result;
    }

    /**
     * Send SMS via Google Voice API
     */
    private function sendSMSViaAPI($to, $message) {
        // This would integrate with the actual Google Voice API
        // For now, return a simulated response

        $url = 'https://www.google.com/voice/api/sms/send';
        $data = [
            'phoneNumber' => $to,
            'text' => $message,
            'id' => bin2hex(random_bytes(8))
        ];

        $headers = [
            'Authorization: Bearer ' . $this->oauth_credentials['access_token'],
            'Content-Type: application/json'
        ];

        try {
            $response = $this->makeHttpRequest($url, $data, 'POST', $headers);
            $result = json_decode($response, true);

            return [
                'success' => true,
                'message' => 'SMS sent successfully',
                'message_id' => $result['id'] ?? uniqid()
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to send SMS: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get configuration value
     */
    private function getConfigValue($key) {
        if (!$this->config) return null;

        $config_data = json_decode($this->config['config_data'], true);
        return $config_data[$key] ?? null;
    }

    /**
     * Make HTTP request
     */
    private function makeHttpRequest($url, $data = null, $method = 'POST', $headers = []) {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'FlexPBX Google Voice Module/2.1.0'
        ]);

        if ($method === 'POST' && $data) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($data) ? http_build_query($data) : $data);
        }

        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception('HTTP request failed: ' . $error);
        }

        if ($http_code >= 400) {
            throw new Exception('HTTP error: ' . $http_code);
        }

        return $response;
    }

    /**
     * Format phone number for display
     */
    private function formatPhoneNumber($number) {
        $number = preg_replace('/\D/', '', $number);
        if (strlen($number) === 11 && $number[0] === '1') {
            $number = substr($number, 1);
        }
        return sprintf('(%s) %s-%s', substr($number, 0, 3), substr($number, 3, 3), substr($number, 6, 4));
    }

    /**
     * Clean phone number (remove formatting)
     */
    private function cleanPhoneNumber($number) {
        return preg_replace('/\D/', '', $number);
    }

    /**
     * Get today's SMS count
     */
    private function getTodaySMSCount() {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM google_voice_sms WHERE DATE(message_time) = CURDATE() AND direction = 'out'");
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }
}

// Handle the request
if ($_SERVER['REQUEST_METHOD']) {
    session_start();

    // Check authentication
    if (!isset($_SESSION['authenticated']) || !$_SESSION['authenticated']) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        exit;
    }

    $module = new GoogleVoiceModule();
    $module->handleRequest();
}
?>