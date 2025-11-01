<?php
/**
 * FlexPBX SMS Provider Manager
 * Manages multiple SMS/voice providers (TextNow, Google Voice, Twilio)
 * Handles encryption, configuration, and unified API access
 */

class SMSProviderManager {
    private $pdo;
    private $encryptionKey;
    private $encryptionMethod = 'AES-256-CBC';

    public function __construct($pdo, $encryptionKey = null) {
        $this->pdo = $pdo;
        $this->encryptionKey = $encryptionKey ?? $this->getDefaultEncryptionKey();
    }

    /**
     * Get default encryption key (should be stored securely)
     */
    private function getDefaultEncryptionKey() {
        // In production, this should be in environment variables or secure config
        $keyFile = __DIR__ . '/../../config/.sms_encryption_key';

        if (file_exists($keyFile)) {
            return trim(file_get_contents($keyFile));
        }

        // Generate new key if doesn't exist
        $key = bin2hex(random_bytes(32));
        @mkdir(dirname($keyFile), 0755, true);
        file_put_contents($keyFile, $key);
        chmod($keyFile, 0600);

        return $key;
    }

    /**
     * Encrypt sensitive data
     */
    public function encrypt($data) {
        if (empty($data)) {
            return null;
        }

        $iv = random_bytes(openssl_cipher_iv_length($this->encryptionMethod));
        $encrypted = openssl_encrypt(
            $data,
            $this->encryptionMethod,
            $this->encryptionKey,
            0,
            $iv
        );

        return base64_encode($encrypted . '::' . $iv);
    }

    /**
     * Decrypt sensitive data
     */
    public function decrypt($data) {
        if (empty($data)) {
            return null;
        }

        $parts = explode('::', base64_decode($data), 2);
        if (count($parts) !== 2) {
            return null;
        }

        list($encrypted, $iv) = $parts;

        return openssl_decrypt(
            $encrypted,
            $this->encryptionMethod,
            $this->encryptionKey,
            0,
            $iv
        );
    }

    /**
     * Get provider by ID or type
     */
    public function getProvider($idOrType) {
        if (is_numeric($idOrType)) {
            $stmt = $this->pdo->prepare("SELECT * FROM sms_providers WHERE id = ?");
            $stmt->execute([$idOrType]);
        } else {
            $stmt = $this->pdo->prepare("SELECT * FROM sms_providers WHERE provider_type = ? AND enabled = 1 LIMIT 1");
            $stmt->execute([$idOrType]);
        }

        $provider = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($provider && $provider['account_data']) {
            $provider['account_data'] = json_decode($this->decrypt($provider['account_data']), true);
        }

        return $provider;
    }

    /**
     * Get all enabled providers
     */
    public function getAllProviders($enabledOnly = true) {
        $query = "SELECT * FROM sms_providers";
        if ($enabledOnly) {
            $query .= " WHERE enabled = 1";
        }
        $query .= " ORDER BY priority DESC, provider_name";

        $stmt = $this->pdo->query($query);
        $providers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($providers as &$provider) {
            if ($provider['account_data']) {
                $provider['account_data'] = json_decode($this->decrypt($provider['account_data']), true);
            }
        }

        return $providers;
    }

    /**
     * Save or update provider configuration
     */
    public function saveProvider($type, $config, $phoneNumber = null) {
        // Encrypt sensitive data
        $encryptedData = $this->encrypt(json_encode($config));

        $stmt = $this->pdo->prepare("
            INSERT INTO sms_providers (provider_type, account_data, phone_number, enabled, updated_at)
            VALUES (?, ?, ?, 1, NOW())
            ON DUPLICATE KEY UPDATE
                account_data = VALUES(account_data),
                phone_number = VALUES(phone_number),
                enabled = 1,
                updated_at = NOW()
        ");

        return $stmt->execute([$type, $encryptedData, $phoneNumber]);
    }

    /**
     * Update provider configuration
     */
    public function updateProviderConfig($providerId, $config) {
        $encryptedData = $this->encrypt(json_encode($config));

        $stmt = $this->pdo->prepare("
            UPDATE sms_providers
            SET account_data = ?, updated_at = NOW()
            WHERE id = ?
        ");

        return $stmt->execute([$encryptedData, $providerId]);
    }

    /**
     * Enable/disable provider
     */
    public function setProviderEnabled($providerId, $enabled) {
        $stmt = $this->pdo->prepare("
            UPDATE sms_providers
            SET enabled = ?, updated_at = NOW()
            WHERE id = ?
        ");

        return $stmt->execute([$enabled ? 1 : 0, $providerId]);
    }

    /**
     * Set default provider
     */
    public function setDefaultProvider($providerId) {
        $this->pdo->beginTransaction();

        try {
            // Unset all defaults
            $this->pdo->exec("UPDATE sms_providers SET is_default = 0");

            // Set new default
            $stmt = $this->pdo->prepare("UPDATE sms_providers SET is_default = 1 WHERE id = ?");
            $stmt->execute([$providerId]);

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return false;
        }
    }

    /**
     * Get default provider
     */
    public function getDefaultProvider() {
        $stmt = $this->pdo->query("
            SELECT * FROM sms_providers
            WHERE is_default = 1 AND enabled = 1
            LIMIT 1
        ");

        $provider = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$provider) {
            // If no default, get highest priority enabled provider
            $stmt = $this->pdo->query("
                SELECT * FROM sms_providers
                WHERE enabled = 1
                ORDER BY priority DESC
                LIMIT 1
            ");
            $provider = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if ($provider && $provider['account_data']) {
            $provider['account_data'] = json_decode($this->decrypt($provider['account_data']), true);
        }

        return $provider;
    }

    /**
     * Log SMS message
     */
    public function logMessage($providerId, $providerType, $direction, $from, $to, $body, $options = []) {
        $stmt = $this->pdo->prepare("
            INSERT INTO sms_messages (
                provider_id, provider_type, direction, message_type,
                from_number, to_number, message_body, media_urls,
                message_sid, status, provider_data,
                extension_id, extension_number, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        return $stmt->execute([
            $providerId,
            $providerType,
            $direction,
            $options['message_type'] ?? 'sms',
            $from,
            $to,
            $body,
            isset($options['media_urls']) ? json_encode($options['media_urls']) : null,
            $options['message_sid'] ?? null,
            $options['status'] ?? 'pending',
            isset($options['provider_data']) ? json_encode($options['provider_data']) : null,
            $options['extension_id'] ?? null,
            $options['extension_number'] ?? null
        ]);
    }

    /**
     * Update message status
     */
    public function updateMessageStatus($messageId, $status, $errorMessage = null) {
        $stmt = $this->pdo->prepare("
            UPDATE sms_messages
            SET status = ?, error_message = ?, updated_at = NOW()
            WHERE id = ?
        ");

        return $stmt->execute([$status, $errorMessage, $messageId]);
    }

    /**
     * Log call
     */
    public function logCall($providerId, $providerType, $direction, $from, $to, $options = []) {
        $stmt = $this->pdo->prepare("
            INSERT INTO call_logs (
                provider_id, provider_type, direction,
                from_number, to_number, status, call_sid,
                provider_data, extension_id, extension_number,
                initiated_at, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");

        return $stmt->execute([
            $providerId,
            $providerType,
            $direction,
            $from,
            $to,
            $options['status'] ?? 'queued',
            $options['call_sid'] ?? null,
            isset($options['provider_data']) ? json_encode($options['provider_data']) : null,
            $options['extension_id'] ?? null,
            $options['extension_number'] ?? null
        ]);
    }

    /**
     * Update call status
     */
    public function updateCallStatus($callId, $status, $duration = null) {
        $sql = "UPDATE call_logs SET status = ?, updated_at = NOW()";
        $params = [$status];

        if ($duration !== null) {
            $sql .= ", duration = ?";
            $params[] = $duration;
        }

        if ($status === 'completed') {
            $sql .= ", ended_at = NOW()";
        }

        $sql .= " WHERE id = ?";
        $params[] = $callId;

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Log webhook
     */
    public function logWebhook($providerId, $providerType, $webhookType, $requestData, $responseData = null) {
        $stmt = $this->pdo->prepare("
            INSERT INTO webhook_logs (
                provider_id, provider_type, webhook_type,
                http_method, remote_ip, request_headers,
                request_body, query_params, response_status,
                response_body, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        return $stmt->execute([
            $providerId,
            $providerType,
            $webhookType,
            $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
            $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN',
            json_encode(getallheaders()),
            json_encode($requestData),
            json_encode($_GET),
            $responseData['status'] ?? 200,
            isset($responseData['body']) ? json_encode($responseData['body']) : null
        ]);
    }

    /**
     * Get statistics for provider
     */
    public function getProviderStats($providerId, $startDate = null, $endDate = null) {
        $where = "provider_id = ?";
        $params = [$providerId];

        if ($startDate) {
            $where .= " AND created_at >= ?";
            $params[] = $startDate;
        }

        if ($endDate) {
            $where .= " AND created_at <= ?";
            $params[] = $endDate;
        }

        // Message stats
        $stmt = $this->pdo->prepare("
            SELECT
                COUNT(*) as total_messages,
                SUM(CASE WHEN direction = 'outbound' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN direction = 'inbound' THEN 1 ELSE 0 END) as received,
                SUM(CASE WHEN message_type = 'mms' THEN 1 ELSE 0 END) as mms_count
            FROM sms_messages
            WHERE $where
        ");
        $stmt->execute($params);
        $messageStats = $stmt->fetch(PDO::FETCH_ASSOC);

        // Call stats
        $stmt = $this->pdo->prepare("
            SELECT
                COUNT(*) as total_calls,
                SUM(CASE WHEN direction = 'outbound' THEN 1 ELSE 0 END) as made,
                SUM(CASE WHEN direction = 'inbound' THEN 1 ELSE 0 END) as received,
                SUM(duration) as total_duration,
                AVG(duration) as avg_duration
            FROM call_logs
            WHERE $where
        ");
        $stmt->execute($params);
        $callStats = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'messages' => $messageStats,
            'calls' => $callStats
        ];
    }

    /**
     * Rate limiting check
     */
    public function checkRateLimit($providerId) {
        $provider = $this->getProvider($providerId);

        if (!$provider) {
            return false;
        }

        // Check messages sent in last minute
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count
            FROM sms_messages
            WHERE provider_id = ?
            AND direction = 'outbound'
            AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)
        ");
        $stmt->execute([$providerId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result['count'] < ($provider['rate_limit_per_minute'] ?? 60);
    }

    /**
     * Clean old logs
     */
    public function cleanOldLogs($daysToKeep = 90) {
        $stmt = $this->pdo->prepare("
            DELETE FROM webhook_logs
            WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");

        return $stmt->execute([$daysToKeep]);
    }
}
