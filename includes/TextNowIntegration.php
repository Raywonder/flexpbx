<?php
/**
 * TextNow API Integration for FlexPBX
 * Full TextNow API integration including Voice Calls, SMS, and MMS
 *
 * @version 1.0
 * @author FlexPBX System
 */

class TextNowIntegration {
    private $apiKey;
    private $apiSecret;
    private $textNowNumber;
    private $baseUrl = 'https://api.textnow.com/v1';
    private $config;
    private $logFile;
    private $dbFile;
    private $rateLimitFile;

    /**
     * Constructor
     * @param array $config Configuration array with TextNow credentials
     */
    public function __construct($config = null) {
        $this->logFile = '/home/flexpbxuser/logs/textnow.log';
        $this->dbFile = '/home/flexpbxuser/config/textnow_messages.json';
        $this->rateLimitFile = '/home/flexpbxuser/config/textnow_ratelimit.json';

        if ($config) {
            $this->config = $config;
            $this->apiKey = $config['api_key'] ?? null;
            $this->apiSecret = $config['api_secret'] ?? null;
            $this->textNowNumber = $config['textnow_number'] ?? null;
        } else {
            $this->loadConfig();
        }
    }

    /**
     * Load TextNow configuration from file
     */
    private function loadConfig() {
        $configFile = '/home/flexpbxuser/config/textnow_config.json';

        if (file_exists($configFile)) {
            $config = json_decode(file_get_contents($configFile), true);
            $this->apiKey = $config['api_key'] ?? null;
            $this->apiSecret = $config['api_secret'] ?? null;
            $this->textNowNumber = $config['textnow_number'] ?? null;
            $this->config = $config;
        }
    }

    /**
     * Save TextNow configuration
     */
    public function saveConfig($config) {
        $configFile = '/home/flexpbxuser/config/textnow_config.json';

        // Create config directory if doesn't exist
        $configDir = dirname($configFile);
        if (!is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }

        $config['updated_at'] = date('Y-m-d H:i:s');

        if (file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT))) {
            $this->config = $config;
            $this->apiKey = $config['api_key'];
            $this->apiSecret = $config['api_secret'];
            $this->textNowNumber = $config['textnow_number'] ?? null;
            return true;
        }

        return false;
    }

    /**
     * Log message
     */
    private function log($message, $level = 'INFO') {
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [{$level}] {$message}\n";
        file_put_contents($this->logFile, $logMessage, FILE_APPEND);
    }

    /**
     * Check rate limiting
     */
    private function checkRateLimit($action = 'api_call') {
        if (!file_exists($this->rateLimitFile)) {
            $this->resetRateLimit();
        }

        $limits = json_decode(file_get_contents($this->rateLimitFile), true);
        $currentMinute = date('Y-m-d H:i');

        if (!isset($limits[$currentMinute])) {
            // New minute, reset counters
            $limits = [$currentMinute => []];
        }

        $count = $limits[$currentMinute][$action] ?? 0;
        $maxRequests = $this->config['rate_limit_per_minute'] ?? 60;

        if ($count >= $maxRequests) {
            throw new Exception("Rate limit exceeded for {$action}. Max {$maxRequests} per minute.");
        }

        $limits[$currentMinute][$action] = $count + 1;
        file_put_contents($this->rateLimitFile, json_encode($limits, JSON_PRETTY_PRINT));

        return true;
    }

    /**
     * Reset rate limit counters
     */
    private function resetRateLimit() {
        file_put_contents($this->rateLimitFile, json_encode([date('Y-m-d H:i') => []], JSON_PRETTY_PRINT));
    }

    /**
     * Make API request to TextNow
     */
    private function request($endpoint, $method = 'GET', $data = null) {
        if (!$this->apiKey || !$this->apiSecret) {
            throw new Exception('TextNow credentials not configured');
        }

        // Check rate limiting
        $this->checkRateLimit('api_call');

        $url = $this->baseUrl . $endpoint;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // Set authentication headers
        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'X-TextNow-Secret: ' . $this->apiSecret,
            'Content-Type: application/json',
            'Accept: application/json'
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

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
        } elseif ($method === 'GET' && $data) {
            $url .= '?' . http_build_query($data);
            curl_setopt($ch, CURLOPT_URL, $url);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            $this->log("API Error: {$error}", 'ERROR');
            throw new Exception("TextNow API Error: {$error}");
        }

        $result = json_decode($response, true);

        if ($httpCode >= 400) {
            $errorMsg = $result['message'] ?? $result['error'] ?? 'Unknown error';
            $this->log("API Error {$httpCode}: {$errorMsg}", 'ERROR');
            throw new Exception("TextNow API Error: {$errorMsg}");
        }

        return $result;
    }

    // ==================== VOICE CALLS ====================

    /**
     * Make an outbound call
     */
    public function makeCall($to, $from = null, $callbackUrl = null) {
        $from = $from ?? $this->textNowNumber;

        if (!$from) {
            throw new Exception('From number not specified');
        }

        $data = [
            'to' => $this->formatPhoneNumber($to),
            'from' => $this->formatPhoneNumber($from),
            'callback_url' => $callbackUrl ?? ($this->config['webhook_url'] ?? ''),
            'record' => $this->config['record_calls'] ?? false
        ];

        $this->log("Making call from {$from} to {$to}");
        $result = $this->request('/calls', 'POST', $data);

        // Store call record in database
        $this->storeCallRecord($result);

        return $result;
    }

    /**
     * Get call details
     */
    public function getCall($callId) {
        return $this->request("/calls/{$callId}");
    }

    /**
     * List calls with filters
     */
    public function listCalls($filters = []) {
        $params = [
            'limit' => $filters['limit'] ?? 50,
            'offset' => $filters['offset'] ?? 0
        ];

        if (isset($filters['from'])) {
            $params['from'] = $this->formatPhoneNumber($filters['from']);
        }
        if (isset($filters['to'])) {
            $params['to'] = $this->formatPhoneNumber($filters['to']);
        }
        if (isset($filters['status'])) {
            $params['status'] = $filters['status'];
        }
        if (isset($filters['start_date'])) {
            $params['start_date'] = $filters['start_date'];
        }
        if (isset($filters['end_date'])) {
            $params['end_date'] = $filters['end_date'];
        }

        return $this->request('/calls', 'GET', $params);
    }

    /**
     * Update call (modify in progress)
     */
    public function updateCall($callId, $action) {
        $data = ['action' => $action]; // Actions: hangup, transfer, mute, unmute
        return $this->request("/calls/{$callId}", 'PUT', $data);
    }

    /**
     * Hangup call
     */
    public function hangupCall($callId) {
        return $this->updateCall($callId, 'hangup');
    }

    /**
     * Get call recording URL
     */
    public function getCallRecording($callId) {
        $call = $this->getCall($callId);
        return $call['recording_url'] ?? null;
    }

    /**
     * Download call recording
     */
    public function downloadCallRecording($callId, $savePath = null) {
        $recordingUrl = $this->getCallRecording($callId);

        if (!$recordingUrl) {
            throw new Exception("No recording available for call {$callId}");
        }

        if (!$savePath) {
            $savePath = "/home/flexpbxuser/public_html/uploads/recordings/textnow_{$callId}.mp3";
        }

        $dir = dirname($savePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $ch = curl_init($recordingUrl);
        $fp = fopen($savePath, 'w');
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_exec($ch);
        curl_close($ch);
        fclose($fp);

        $this->log("Downloaded recording for call {$callId} to {$savePath}");
        return $savePath;
    }

    // ==================== SMS MESSAGING ====================

    /**
     * Send SMS message
     */
    public function sendSMS($to, $body, $from = null) {
        $from = $from ?? $this->textNowNumber;

        if (!$from) {
            throw new Exception('From number not specified');
        }

        if (strlen($body) > 1600) {
            throw new Exception('Message exceeds maximum length of 1600 characters');
        }

        $data = [
            'to' => $this->formatPhoneNumber($to),
            'from' => $this->formatPhoneNumber($from),
            'body' => $body
        ];

        $this->log("Sending SMS from {$from} to {$to}");
        $result = $this->request('/messages', 'POST', $data);

        // Store message in database
        $this->storeMessage($result);

        return $result;
    }

    /**
     * Send MMS message with media
     */
    public function sendMMS($to, $body, $mediaUrls = [], $from = null) {
        $from = $from ?? $this->textNowNumber;

        if (!$from) {
            throw new Exception('From number not specified');
        }

        if (!is_array($mediaUrls)) {
            $mediaUrls = [$mediaUrls];
        }

        // Validate media URLs and file types
        foreach ($mediaUrls as $url) {
            if (!$this->isValidMediaUrl($url)) {
                throw new Exception("Invalid media URL: {$url}");
            }
        }

        $data = [
            'to' => $this->formatPhoneNumber($to),
            'from' => $this->formatPhoneNumber($from),
            'body' => $body,
            'media_urls' => $mediaUrls
        ];

        $this->log("Sending MMS from {$from} to {$to} with " . count($mediaUrls) . " media file(s)");
        $result = $this->request('/messages', 'POST', $data);

        // Store message in database
        $this->storeMessage($result);

        return $result;
    }

    /**
     * Upload media for MMS
     */
    public function uploadMedia($filePath) {
        if (!file_exists($filePath)) {
            throw new Exception("File not found: {$filePath}");
        }

        // Validate file type
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'video/mp4', 'audio/mpeg', 'audio/wav'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filePath);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedTypes)) {
            throw new Exception("Invalid file type: {$mimeType}. Allowed types: " . implode(', ', $allowedTypes));
        }

        // Check file size (max 5MB)
        $fileSize = filesize($filePath);
        if ($fileSize > 5 * 1024 * 1024) {
            throw new Exception("File too large. Maximum size is 5MB.");
        }

        $ch = curl_init($this->baseUrl . '/media');

        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'X-TextNow-Secret: ' . $this->apiSecret
        ];

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);

        $cfile = new CURLFile($filePath, $mimeType, basename($filePath));
        curl_setopt($ch, CURLOPT_POSTFIELDS, ['file' => $cfile]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            throw new Exception("Failed to upload media: HTTP {$httpCode}");
        }

        $result = json_decode($response, true);
        $this->log("Uploaded media: " . ($result['url'] ?? 'unknown'));

        return $result['url'] ?? null;
    }

    /**
     * Get message details
     */
    public function getMessage($messageId) {
        return $this->request("/messages/{$messageId}");
    }

    /**
     * List messages with filters
     */
    public function listMessages($filters = []) {
        $params = [
            'limit' => $filters['limit'] ?? 50,
            'offset' => $filters['offset'] ?? 0
        ];

        if (isset($filters['from'])) {
            $params['from'] = $this->formatPhoneNumber($filters['from']);
        }
        if (isset($filters['to'])) {
            $params['to'] = $this->formatPhoneNumber($filters['to']);
        }
        if (isset($filters['start_date'])) {
            $params['start_date'] = $filters['start_date'];
        }
        if (isset($filters['end_date'])) {
            $params['end_date'] = $filters['end_date'];
        }

        return $this->request('/messages', 'GET', $params);
    }

    /**
     * Get message history for a specific number
     */
    public function getMessageHistory($number, $limit = 50) {
        $filters = [
            'to' => $number,
            'limit' => $limit
        ];

        $sent = $this->listMessages($filters);

        $filters['to'] = null;
        $filters['from'] = $number;
        $received = $this->listMessages($filters);

        // Merge and sort by timestamp
        $messages = array_merge($sent['messages'] ?? [], $received['messages'] ?? []);
        usort($messages, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        return array_slice($messages, 0, $limit);
    }

    /**
     * Delete message
     */
    public function deleteMessage($messageId) {
        $this->log("Deleting message: {$messageId}");
        return $this->request("/messages/{$messageId}", 'DELETE');
    }

    // ==================== PHONE NUMBER MANAGEMENT ====================

    /**
     * List available phone numbers
     */
    public function searchAvailableNumbers($areaCode = null, $filters = []) {
        $params = [
            'limit' => $filters['limit'] ?? 10
        ];

        if ($areaCode) {
            $params['area_code'] = $areaCode;
        }
        if (isset($filters['contains'])) {
            $params['contains'] = $filters['contains'];
        }
        if (isset($filters['state'])) {
            $params['state'] = $filters['state'];
        }

        return $this->request('/available-numbers', 'GET', $params);
    }

    /**
     * Purchase phone number
     */
    public function purchaseNumber($phoneNumber) {
        $data = [
            'phone_number' => $this->formatPhoneNumber($phoneNumber)
        ];

        $this->log("Purchasing number: {$phoneNumber}");
        return $this->request('/phone-numbers', 'POST', $data);
    }

    /**
     * List owned phone numbers
     */
    public function listPhoneNumbers() {
        return $this->request('/phone-numbers');
    }

    /**
     * Update phone number configuration
     */
    public function updatePhoneNumber($phoneNumber, $config) {
        $formattedNumber = $this->formatPhoneNumber($phoneNumber);
        return $this->request("/phone-numbers/{$formattedNumber}", 'PUT', $config);
    }

    /**
     * Release phone number
     */
    public function releasePhoneNumber($phoneNumber) {
        $formattedNumber = $this->formatPhoneNumber($phoneNumber);
        $this->log("Releasing number: {$formattedNumber}");
        return $this->request("/phone-numbers/{$formattedNumber}", 'DELETE');
    }

    // ==================== WEBHOOK HANDLING ====================

    /**
     * Validate webhook signature
     */
    public function validateWebhook($payload, $signature) {
        if (!$this->apiSecret) {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $payload, $this->apiSecret);
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Process inbound call webhook
     */
    public function handleInboundCall($data) {
        $this->log("Received inbound call from: " . ($data['from'] ?? 'unknown'));

        $callRecord = [
            'id' => $data['call_id'] ?? uniqid('call_'),
            'from' => $data['from'] ?? '',
            'to' => $data['to'] ?? '',
            'direction' => 'inbound',
            'status' => $data['status'] ?? 'ringing',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $this->storeCallRecord($callRecord);

        // Check if this number is linked to an extension
        $extension = $this->findExtensionForNumber($data['to'] ?? '');

        if ($extension) {
            // Forward to Asterisk extension
            return $this->forwardCallToAsterisk($callRecord, $extension);
        }

        return $callRecord;
    }

    /**
     * Process inbound SMS webhook
     */
    public function handleInboundSMS($data) {
        $this->log("Received inbound SMS from: " . ($data['from'] ?? 'unknown'));

        $message = [
            'id' => $data['message_id'] ?? uniqid('msg_'),
            'from' => $data['from'] ?? '',
            'to' => $data['to'] ?? '',
            'body' => $data['body'] ?? '',
            'direction' => 'inbound',
            'type' => 'sms',
            'created_at' => date('Y-m-d H:i:s')
        ];

        $this->storeMessage($message);

        // Notify user if they have an extension linked to this number
        $extension = $this->findExtensionForNumber($data['to'] ?? '');
        if ($extension) {
            $this->notifyExtension($extension, $message);
        }

        return $message;
    }

    /**
     * Process inbound MMS webhook
     */
    public function handleInboundMMS($data) {
        $this->log("Received inbound MMS from: " . ($data['from'] ?? 'unknown') . " with " . count($data['media_urls'] ?? []) . " media file(s)");

        $message = [
            'id' => $data['message_id'] ?? uniqid('msg_'),
            'from' => $data['from'] ?? '',
            'to' => $data['to'] ?? '',
            'body' => $data['body'] ?? '',
            'media_urls' => $data['media_urls'] ?? [],
            'direction' => 'inbound',
            'type' => 'mms',
            'created_at' => date('Y-m-d H:i:s')
        ];

        $this->storeMessage($message);

        // Download and store media files
        foreach ($message['media_urls'] as $url) {
            $this->downloadAndStoreMedia($url, $message['id']);
        }

        // Notify user
        $extension = $this->findExtensionForNumber($data['to'] ?? '');
        if ($extension) {
            $this->notifyExtension($extension, $message);
        }

        return $message;
    }

    /**
     * Process status callback webhook
     */
    public function handleStatusCallback($data) {
        $this->log("Status callback for: " . ($data['call_id'] ?? $data['message_id'] ?? 'unknown'));

        if (isset($data['call_id'])) {
            // Update call record
            $this->updateCallRecord($data['call_id'], [
                'status' => $data['status'] ?? 'unknown',
                'duration' => $data['duration'] ?? 0,
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        } elseif (isset($data['message_id'])) {
            // Update message status
            $this->updateMessageStatus($data['message_id'], [
                'status' => $data['status'] ?? 'unknown',
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        }

        return $data;
    }

    // ==================== DATABASE OPERATIONS ====================

    /**
     * Store call record
     */
    private function storeCallRecord($call) {
        $dbDir = dirname($this->dbFile);
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0755, true);
        }

        $db = $this->loadDatabase();

        if (!isset($db['calls'])) {
            $db['calls'] = [];
        }

        $db['calls'][$call['id']] = $call;

        // Keep only last 1000 calls
        if (count($db['calls']) > 1000) {
            $db['calls'] = array_slice($db['calls'], -1000, null, true);
        }

        $this->saveDatabase($db);
    }

    /**
     * Update call record
     */
    private function updateCallRecord($callId, $updates) {
        $db = $this->loadDatabase();

        if (isset($db['calls'][$callId])) {
            $db['calls'][$callId] = array_merge($db['calls'][$callId], $updates);
            $this->saveDatabase($db);
        }
    }

    /**
     * Store message
     */
    private function storeMessage($message) {
        $dbDir = dirname($this->dbFile);
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0755, true);
        }

        $db = $this->loadDatabase();

        if (!isset($db['messages'])) {
            $db['messages'] = [];
        }

        $db['messages'][$message['id']] = $message;

        // Keep only last 5000 messages
        if (count($db['messages']) > 5000) {
            $db['messages'] = array_slice($db['messages'], -5000, null, true);
        }

        $this->saveDatabase($db);
    }

    /**
     * Update message status
     */
    private function updateMessageStatus($messageId, $updates) {
        $db = $this->loadDatabase();

        if (isset($db['messages'][$messageId])) {
            $db['messages'][$messageId] = array_merge($db['messages'][$messageId], $updates);
            $this->saveDatabase($db);
        }
    }

    /**
     * Load database
     */
    private function loadDatabase() {
        if (!file_exists($this->dbFile)) {
            return ['calls' => [], 'messages' => []];
        }

        return json_decode(file_get_contents($this->dbFile), true) ?? ['calls' => [], 'messages' => []];
    }

    /**
     * Save database
     */
    private function saveDatabase($db) {
        file_put_contents($this->dbFile, json_encode($db, JSON_PRETTY_PRINT));
    }

    // ==================== HELPER METHODS ====================

    /**
     * Format phone number to E.164
     */
    public function formatPhoneNumber($number, $defaultCountryCode = '+1') {
        // Remove all non-numeric characters
        $number = preg_replace('/[^0-9]/', '', $number);

        // Add country code if not present
        if (strlen($number) === 10) {
            $number = $defaultCountryCode . $number;
        } elseif (strlen($number) === 11 && substr($number, 0, 1) === '1') {
            $number = '+' . $number;
        } elseif (substr($number, 0, 1) !== '+') {
            $number = '+' . $number;
        }

        return $number;
    }

    /**
     * Validate media URL
     */
    private function isValidMediaUrl($url) {
        // Check if URL is valid
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        // Check file extension
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'mp3', 'wav', 'webp'];
        $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));

        return in_array($extension, $allowedExtensions);
    }

    /**
     * Download and store media
     */
    private function downloadAndStoreMedia($url, $messageId) {
        $extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);
        $filename = "textnow_{$messageId}_" . uniqid() . ".{$extension}";
        $savePath = "/home/flexpbxuser/public_html/uploads/mms/{$filename}";

        $dir = dirname($savePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $ch = curl_init($url);
        $fp = fopen($savePath, 'w');
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_exec($ch);
        curl_close($ch);
        fclose($fp);

        $this->log("Downloaded MMS media to {$savePath}");
        return $savePath;
    }

    /**
     * Find extension linked to phone number
     */
    private function findExtensionForNumber($phoneNumber) {
        $userDir = '/home/flexpbxuser/users';

        if (!is_dir($userDir)) {
            return null;
        }

        $formattedNumber = $this->formatPhoneNumber($phoneNumber);

        // Search through user files
        $files = glob($userDir . '/user_*.json');
        foreach ($files as $file) {
            $user = json_decode(file_get_contents($file), true);

            if (isset($user['textnow_number']) &&
                $this->formatPhoneNumber($user['textnow_number']) === $formattedNumber) {
                return $user['extension'] ?? null;
            }
        }

        return null;
    }

    /**
     * Forward call to Asterisk extension
     */
    private function forwardCallToAsterisk($call, $extension) {
        // This would integrate with Asterisk AMI or AGI
        $this->log("Forwarding call {$call['id']} to extension {$extension}");

        // For now, just log the action
        // In production, this would use Asterisk Manager Interface or AGI

        return [
            'action' => 'forwarded',
            'extension' => $extension,
            'call' => $call
        ];
    }

    /**
     * Notify extension of new message
     */
    private function notifyExtension($extension, $message) {
        $this->log("Notifying extension {$extension} of new message");

        // This could trigger:
        // 1. Push notification
        // 2. Email notification
        // 3. Desktop app notification
        // 4. Asterisk voicemail notification

        return true;
    }

    /**
     * Test API connection
     */
    public function testConnection() {
        try {
            $numbers = $this->listPhoneNumbers();
            return [
                'success' => true,
                'phone_numbers' => count($numbers['numbers'] ?? []),
                'status' => 'connected'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get account information
     */
    public function getAccount() {
        return $this->request('/account');
    }

    /**
     * Get usage statistics
     */
    public function getUsage($startDate = null, $endDate = null) {
        $params = [];

        if ($startDate) {
            $params['start_date'] = $startDate;
        }
        if ($endDate) {
            $params['end_date'] = $endDate;
        }

        return $this->request('/usage', 'GET', $params);
    }

    /**
     * Get call history from local database
     */
    public function getCallHistory($number = null, $limit = 50) {
        $db = $this->loadDatabase();
        $calls = $db['calls'] ?? [];

        if ($number) {
            $formattedNumber = $this->formatPhoneNumber($number);
            $calls = array_filter($calls, function($call) use ($formattedNumber) {
                return $call['from'] === $formattedNumber || $call['to'] === $formattedNumber;
            });
        }

        // Sort by date, newest first
        usort($calls, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        return array_slice($calls, 0, $limit);
    }

    /**
     * Get statistics
     */
    public function getStatistics() {
        $db = $this->loadDatabase();

        $stats = [
            'total_calls' => count($db['calls'] ?? []),
            'total_messages' => count($db['messages'] ?? []),
            'inbound_calls' => 0,
            'outbound_calls' => 0,
            'inbound_messages' => 0,
            'outbound_messages' => 0,
            'total_call_duration' => 0
        ];

        foreach ($db['calls'] ?? [] as $call) {
            if ($call['direction'] === 'inbound') {
                $stats['inbound_calls']++;
            } else {
                $stats['outbound_calls']++;
            }
            $stats['total_call_duration'] += ($call['duration'] ?? 0);
        }

        foreach ($db['messages'] ?? [] as $message) {
            if ($message['direction'] === 'inbound') {
                $stats['inbound_messages']++;
            } else {
                $stats['outbound_messages']++;
            }
        }

        return $stats;
    }
}
?>
