<?php
/**
 * Mastodon Authentication Integration for FlexPBX
 * OAuth2 authentication and profile linking
 *
 * Version: 1.0
 * Compatible with: FlexPBX v1.2+
 */

class MastodonAuth {

    private $pdo;
    private $config;
    private $defaultInstance = 'https://md.tappedin.fm';

    public function __construct($pdo, $config = []) {
        $this->pdo = $pdo;
        $this->config = array_merge([
            'default_instance' => $this->defaultInstance,
            'client_id' => null,
            'client_secret' => null,
            'redirect_uri' => null,
            'auto_create_users' => true,
            'auto_assign_extensions' => true,
            'enable_fallback_auth' => true,
            'sync_profile_data' => true
        ], $config);
    }

    /**
     * Register OAuth2 application with Mastodon instance
     */
    public function registerApplication($instanceUrl = null) {
        $instanceUrl = $instanceUrl ?? $this->config['default_instance'];
        $instanceUrl = rtrim($instanceUrl, '/');

        $data = [
            'client_name' => 'FlexPBX Authentication',
            'redirect_uris' => $this->config['redirect_uri'] ?? $this->getCallbackUrl(),
            'scopes' => 'read:accounts read:follows',
            'website' => 'https://flexpbx.devinecreations.net'
        ];

        $ch = curl_init($instanceUrl . '/api/v1/apps');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $app = json_decode($response, true);

            // Store credentials
            $stmt = $this->pdo->prepare("
                INSERT INTO mastodon_instances (
                    instance_url,
                    client_id,
                    client_secret,
                    is_default,
                    created_at
                ) VALUES (?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    client_id = VALUES(client_id),
                    client_secret = VALUES(client_secret)
            ");
            $stmt->execute([
                $instanceUrl,
                $app['client_id'],
                $app['client_secret'],
                $instanceUrl === $this->defaultInstance ? 1 : 0
            ]);

            return [
                'success' => true,
                'client_id' => $app['client_id'],
                'client_secret' => $app['client_secret']
            ];
        }

        return [
            'success' => false,
            'error' => 'Failed to register application with Mastodon'
        ];
    }

    /**
     * Get OAuth2 authorization URL
     */
    public function getAuthorizationUrl($instanceUrl = null, $state = null) {
        $instanceUrl = $instanceUrl ?? $this->config['default_instance'];
        $instanceUrl = rtrim($instanceUrl, '/');

        // Get client credentials for this instance
        $stmt = $this->pdo->prepare("
            SELECT client_id, client_secret
            FROM mastodon_instances
            WHERE instance_url = ?
        ");
        $stmt->execute([$instanceUrl]);
        $instance = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$instance) {
            // Auto-register if not found
            $reg = $this->registerApplication($instanceUrl);
            if (!$reg['success']) {
                return null;
            }
            $instance = ['client_id' => $reg['client_id']];
        }

        $params = [
            'client_id' => $instance['client_id'],
            'redirect_uri' => $this->getCallbackUrl(),
            'response_type' => 'code',
            'scope' => 'read:accounts read:follows'
        ];

        if ($state) {
            $params['state'] = $state;
        }

        return $instanceUrl . '/oauth/authorize?' . http_build_query($params);
    }

    /**
     * Exchange authorization code for access token
     */
    public function getAccessToken($code, $instanceUrl = null) {
        $instanceUrl = $instanceUrl ?? $this->config['default_instance'];
        $instanceUrl = rtrim($instanceUrl, '/');

        // Get client credentials
        $stmt = $this->pdo->prepare("
            SELECT client_id, client_secret
            FROM mastodon_instances
            WHERE instance_url = ?
        ");
        $stmt->execute([$instanceUrl]);
        $instance = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$instance) {
            return ['success' => false, 'error' => 'Instance not registered'];
        }

        $data = [
            'client_id' => $instance['client_id'],
            'client_secret' => $instance['client_secret'],
            'redirect_uri' => $this->getCallbackUrl(),
            'grant_type' => 'authorization_code',
            'code' => $code
        ];

        $ch = curl_init($instanceUrl . '/oauth/token');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $token = json_decode($response, true);
            return [
                'success' => true,
                'access_token' => $token['access_token'],
                'token_type' => $token['token_type'],
                'scope' => $token['scope'],
                'created_at' => $token['created_at']
            ];
        }

        return [
            'success' => false,
            'error' => 'Failed to exchange code for token'
        ];
    }

    /**
     * Get Mastodon user profile
     */
    public function getUserProfile($accessToken, $instanceUrl) {
        $instanceUrl = rtrim($instanceUrl, '/');

        $ch = curl_init($instanceUrl . '/api/v1/accounts/verify_credentials');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $profile = json_decode($response, true);
            return [
                'success' => true,
                'profile' => $profile
            ];
        }

        return [
            'success' => false,
            'error' => 'Failed to fetch user profile'
        ];
    }

    /**
     * Link Mastodon account to FlexPBX user
     */
    public function linkAccount($userId, $mastodonId, $instanceUrl, $accessToken, $profile) {
        // Store OAuth token
        $stmt = $this->pdo->prepare("
            INSERT INTO mastodon_oauth_tokens (
                user_id,
                instance_url,
                access_token,
                expires_at,
                created_at
            ) VALUES (?, ?, ?, NULL, NOW())
            ON DUPLICATE KEY UPDATE
                access_token = VALUES(access_token)
        ");
        $stmt->execute([$userId, $instanceUrl, $accessToken]);

        // Link account
        $stmt = $this->pdo->prepare("
            INSERT INTO mastodon_linked_accounts (
                user_id,
                mastodon_id,
                instance_url,
                username,
                display_name,
                avatar_url,
                profile_url,
                is_primary,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())
            ON DUPLICATE KEY UPDATE
                username = VALUES(username),
                display_name = VALUES(display_name),
                avatar_url = VALUES(avatar_url),
                last_sync = NOW()
        ");
        $stmt->execute([
            $userId,
            $mastodonId,
            $instanceUrl,
            $profile['username'],
            $profile['display_name'],
            $profile['avatar'],
            $profile['url']
        ]);

        // Sync profile data if enabled
        if ($this->config['sync_profile_data']) {
            $this->syncProfileData($userId, $profile);
        }

        return ['success' => true, 'message' => 'Account linked successfully'];
    }

    /**
     * Authenticate user via Mastodon (primary auth)
     */
    public function authenticateUser($mastodonId, $instanceUrl) {
        // Check if account is linked
        $stmt = $this->pdo->prepare("
            SELECT u.*, mla.is_primary
            FROM mastodon_linked_accounts mla
            JOIN users u ON mla.user_id = u.id
            WHERE mla.mastodon_id = ? AND mla.instance_url = ?
        ");
        $stmt->execute([$mastodonId, $instanceUrl]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Update last login
            $this->pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")
                ->execute([$user['id']]);

            return [
                'success' => true,
                'user' => $user,
                'auth_method' => 'mastodon_primary'
            ];
        }

        // Auto-create user if enabled
        if ($this->config['auto_create_users']) {
            return $this->createUserFromMastodon($mastodonId, $instanceUrl);
        }

        return [
            'success' => false,
            'error' => 'No linked FlexPBX account found'
        ];
    }

    /**
     * Create FlexPBX user from Mastodon profile
     */
    private function createUserFromMastodon($mastodonId, $instanceUrl) {
        // Get Mastodon profile
        $stmt = $this->pdo->prepare("
            SELECT * FROM mastodon_oauth_tokens
            WHERE instance_url = ?
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$instanceUrl]);
        $token = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$token) {
            return ['success' => false, 'error' => 'No valid token'];
        }

        $profileData = $this->getUserProfile($token['access_token'], $instanceUrl);
        if (!$profileData['success']) {
            return $profileData;
        }

        $profile = $profileData['profile'];

        // Generate extension number if enabled
        $extension = null;
        if ($this->config['auto_assign_extensions']) {
            $extension = $this->generateExtension();
        }

        // Create user
        $username = $profile['username'] . '@mastodon';
        $email = $extension ? $extension . '@flexpbx.devinecreations.net' : null;

        $stmt = $this->pdo->prepare("
            INSERT INTO users (
                username,
                email,
                display_name,
                extension,
                auth_method,
                created_at
            ) VALUES (?, ?, ?, ?, 'mastodon', NOW())
        ");
        $stmt->execute([
            $username,
            $email,
            $profile['display_name'],
            $extension
        ]);

        $userId = $this->pdo->lastInsertId();

        // Link account
        $this->linkAccount($userId, $mastodonId, $instanceUrl, $token['access_token'], $profile);

        return [
            'success' => true,
            'user_id' => $userId,
            'extension' => $extension,
            'auth_method' => 'mastodon_primary',
            'message' => 'Account created from Mastodon profile'
        ];
    }

    /**
     * Fallback authentication (when Mastodon unreachable)
     */
    public function fallbackAuth($userId) {
        if (!$this->config['enable_fallback_auth']) {
            return ['success' => false, 'error' => 'Fallback auth disabled'];
        }

        // Log fallback attempt
        $stmt = $this->pdo->prepare("
            INSERT INTO auth_fallback_log (
                user_id,
                reason,
                created_at
            ) VALUES (?, 'mastodon_unreachable', NOW())
        ");
        $stmt->execute([$userId]);

        // Get user
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            return [
                'success' => true,
                'user' => $user,
                'auth_method' => 'fallback',
                'message' => 'Authenticated via fallback (Mastodon unavailable)'
            ];
        }

        return ['success' => false, 'error' => 'User not found'];
    }

    /**
     * Sync profile data between Mastodon and FlexPBX
     */
    private function syncProfileData($userId, $profile) {
        $stmt = $this->pdo->prepare("
            UPDATE users
            SET display_name = ?,
                avatar_url = ?,
                last_sync = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $profile['display_name'],
            $profile['avatar'],
            $userId
        ]);
    }

    /**
     * Generate unique extension number
     */
    private function generateExtension() {
        // Get next available extension (starting from 1000)
        $stmt = $this->pdo->query("
            SELECT MAX(CAST(extension AS UNSIGNED)) as max_ext
            FROM users
            WHERE extension REGEXP '^[0-9]+$'
        ");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $nextExt = ($result['max_ext'] ?? 999) + 1;

        return str_pad($nextExt, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Get callback URL
     */
    private function getCallbackUrl() {
        return $this->config['redirect_uri'] ?? 'https://flexpbx.devinecreations.net/api/mastodon-auth.php?action=callback';
    }

    /**
     * Check if Mastodon instance is reachable
     */
    public function isInstanceReachable($instanceUrl) {
        $ch = curl_init($instanceUrl . '/api/v1/instance');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode === 200;
    }
}
