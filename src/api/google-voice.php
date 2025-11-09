<?php
/**
 * FlexPBX Google Voice Integration API
 * Handles Google Voice SMS/Voice communication via OAuth2
 *
 * Endpoints:
 * - GET  ?action=get_config           - Get Google Voice configuration
 * - POST ?action=save_config          - Save Google Voice configuration
 * - POST ?action=save_oauth           - Save OAuth credentials
 * - GET  ?action=test_connection      - Test Google Voice API connection
 * - GET  ?action=authorize            - Start OAuth authorization flow
 * - GET  ?action=oauth_callback       - OAuth callback handler
 * - POST ?action=send_sms             - Send SMS message
 * - GET  ?action=list_messages        - Get message history
 * - GET  ?action=list_calls           - Get call logs
 * - POST ?action=make_call            - Initiate outbound call
 * - GET  ?action=get_voicemails       - Get voicemails
 * - GET  ?action=get_statistics       - Get usage statistics
 * - POST ?action=inbound_sms          - Webhook for incoming SMS
 * - POST ?action=inbound_call         - Webhook for incoming calls
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Load configuration and dependencies
$config = require __DIR__ . '/config.php';
require_once __DIR__ . '/includes/SMSProviderManager.php';

// Database connection
try {
    $pdo = new PDO(
        "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4",
        $config['db_user'],
        $config['db_password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

$providerManager = new SMSProviderManager($pdo);
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Route requests
switch ($action) {
    case 'get_config':
        handleGetConfig();
        break;
    case 'save_config':
        handleSaveConfig();
        break;
    case 'save_oauth':
        handleSaveOAuth();
        break;
    case 'test_connection':
        handleTestConnection();
        break;
    case 'authorize':
        handleAuthorize();
        break;
    case 'oauth_callback':
        handleOAuthCallback();
        break;
    case 'send_sms':
        handleSendSMS();
        break;
    case 'list_messages':
        handleListMessages();
        break;
    case 'list_calls':
        handleListCalls();
        break;
    case 'make_call':
        handleMakeCall();
        break;
    case 'get_voicemails':
        handleGetVoicemails();
        break;
    case 'get_statistics':
        handleGetStatistics();
        break;
    // Webhooks
    case 'inbound_sms':
        handleInboundSMS();
        break;
    case 'inbound_call':
        handleInboundCall();
        break;
    default:
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}

/**
 * Get Google Voice configuration
 */
function handleGetConfig() {
    global $providerManager;

    $provider = $providerManager->getProvider('google_voice');

    if (!$provider) {
        echo json_encode([
            'success' => true,
            'config' => getDefaultConfig()
        ]);
        return;
    }

    $accountData = $provider['account_data'] ?? [];

    echo json_encode([
        'success' => true,
        'config' => array_merge(getDefaultConfig(), [
            'primary_number' => $provider['phone_number'] ?? '',
            'enabled' => $provider['enabled'] == 1,
            'display_name' => $accountData['display_name'] ?? '',
            'default_destination' => $accountData['default_destination'] ?? '',
            'after_hours_destination' => $accountData['after_hours_destination'] ?? 'voicemail',
            'business_start' => $accountData['business_start'] ?? '09:00',
            'business_end' => $accountData['business_end'] ?? '18:00',
            'greeting_message' => $accountData['greeting_message'] ?? '',
            'auto_reply_message' => $accountData['auto_reply_message'] ?? '',
            'call_limit_daily' => $provider['daily_call_limit'] ?? 1000,
            'sms_limit_daily' => $provider['daily_sms_limit'] ?? 500,
            'enable_sms' => $accountData['enable_sms'] ?? true,
            'enable_voicemail' => $accountData['enable_voicemail'] ?? true,
            'auto_transcribe' => $accountData['auto_transcribe'] ?? true,
            'enable_call_recording' => $accountData['enable_call_recording'] ?? false,
            'enable_call_screening' => $accountData['enable_call_screening'] ?? false,
            'email_notifications' => $accountData['email_notifications'] ?? true,
            'auto_reply_enabled' => $accountData['auto_reply_enabled'] ?? false,
            'business_hours_only' => $accountData['business_hours_only'] ?? true,
            'has_oauth' => !empty($accountData['oauth_refresh_token'])
        ])
    ]);
}

/**
 * Save Google Voice configuration
 */
function handleSaveConfig() {
    global $providerManager, $pdo;

    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['primary_number'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Primary phone number required']);
        return;
    }

    $config = [
        'display_name' => $data['display_name'] ?? '',
        'default_destination' => $data['default_destination'] ?? '',
        'after_hours_destination' => $data['after_hours_destination'] ?? 'voicemail',
        'business_start' => $data['business_start'] ?? '09:00',
        'business_end' => $data['business_end'] ?? '18:00',
        'greeting_message' => $data['greeting_message'] ?? '',
        'auto_reply_message' => $data['auto_reply_message'] ?? '',
        'enable_sms' => $data['enable_sms'] ?? true,
        'enable_voicemail' => $data['enable_voicemail'] ?? true,
        'auto_transcribe' => $data['auto_transcribe'] ?? true,
        'enable_call_recording' => $data['enable_call_recording'] ?? false,
        'enable_call_screening' => $data['enable_call_screening'] ?? false,
        'email_notifications' => $data['email_notifications'] ?? true,
        'auto_reply_enabled' => $data['auto_reply_enabled'] ?? false,
        'business_hours_only' => $data['business_hours_only'] ?? true
    ];

    try {
        $providerManager->saveProvider('google_voice', $config, $data['primary_number']);

        // Update limits
        $stmt = $pdo->prepare("
            UPDATE sms_providers
            SET daily_call_limit = ?, daily_sms_limit = ?, enabled = ?
            WHERE provider_type = 'google_voice'
        ");
        $stmt->execute([
            $data['call_limit_daily'] ?? 1000,
            $data['sms_limit_daily'] ?? 500,
            $data['enabled'] ?? false
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Google Voice configuration saved successfully'
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Save OAuth credentials
 */
function handleSaveOAuth() {
    global $providerManager, $pdo;

    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['client_id']) || empty($data['client_secret'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Client ID and Secret required']);
        return;
    }

    try {
        $provider = $providerManager->getProvider('google_voice');

        if (!$provider) {
            // Create provider first
            $providerManager->saveProvider('google_voice', [], null);
            $provider = $providerManager->getProvider('google_voice');
        }

        $stmt = $pdo->prepare("
            INSERT INTO sms_provider_config (provider_id, oauth_client_id, oauth_client_secret)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE
                oauth_client_id = VALUES(oauth_client_id),
                oauth_client_secret = VALUES(oauth_client_secret)
        ");

        $stmt->execute([
            $provider['id'],
            $data['client_id'],
            $providerManager->encrypt($data['client_secret'])
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'OAuth credentials saved successfully'
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Test Google Voice connection
 */
function handleTestConnection() {
    global $providerManager;

    $provider = $providerManager->getProvider('google_voice');

    if (!$provider) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Google Voice not configured']);
        return;
    }

    $accountData = $provider['account_data'] ?? [];

    if (empty($accountData['oauth_refresh_token'])) {
        echo json_encode([
            'success' => false,
            'error' => 'OAuth not authorized. Please authorize access first.'
        ]);
        return;
    }

    // Try to get account info
    $result = makeGoogleVoiceRequest('/settings', 'GET', [], $provider);

    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'number' => $provider['phone_number'] ?? 'Not configured',
            'status' => 'Connected'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => $result['error'] ?? 'Connection failed'
        ]);
    }
}

/**
 * Start OAuth authorization
 */
function handleAuthorize() {
    global $pdo, $providerManager;

    $provider = $providerManager->getProvider('google_voice');

    if (!$provider) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Provider not configured']);
        return;
    }

    // Get OAuth credentials
    $stmt = $pdo->prepare("SELECT * FROM sms_provider_config WHERE provider_id = ?");
    $stmt->execute([$provider['id']]);
    $oauthConfig = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$oauthConfig || empty($oauthConfig['oauth_client_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'OAuth credentials not configured']);
        return;
    }

    $redirectUri = 'https://flexpbx.devinecreations.net/api/google-voice.php?action=oauth_callback';
    $scope = 'https://www.googleapis.com/auth/voice';

    $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
        'client_id' => $oauthConfig['oauth_client_id'],
        'redirect_uri' => $redirectUri,
        'response_type' => 'code',
        'scope' => $scope,
        'access_type' => 'offline',
        'prompt' => 'consent'
    ]);

    // Return redirect URL
    echo json_encode([
        'success' => true,
        'auth_url' => $authUrl
    ]);
}

/**
 * OAuth callback handler
 */
function handleOAuthCallback() {
    global $pdo, $providerManager;

    $code = $_GET['code'] ?? '';

    if (empty($code)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Authorization code missing']);
        return;
    }

    $provider = $providerManager->getProvider('google_voice');
    $stmt = $pdo->prepare("SELECT * FROM sms_provider_config WHERE provider_id = ?");
    $stmt->execute([$provider['id']]);
    $oauthConfig = $stmt->fetch(PDO::FETCH_ASSOC);

    // Exchange code for tokens
    $tokenUrl = 'https://oauth2.googleapis.com/token';
    $redirectUri = 'https://flexpbx.devinecreations.net/api/google-voice.php?action=oauth_callback';

    $ch = curl_init($tokenUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'code' => $code,
        'client_id' => $oauthConfig['oauth_client_id'],
        'client_secret' => $providerManager->decrypt($oauthConfig['oauth_client_secret']),
        'redirect_uri' => $redirectUri,
        'grant_type' => 'authorization_code'
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode == 200) {
        $tokens = json_decode($response, true);

        // Save tokens
        $stmt = $pdo->prepare("
            UPDATE sms_provider_config
            SET oauth_refresh_token = ?,
                oauth_access_token = ?,
                oauth_token_expires_at = DATE_ADD(NOW(), INTERVAL ? SECOND)
            WHERE provider_id = ?
        ");

        $stmt->execute([
            $providerManager->encrypt($tokens['refresh_token']),
            $providerManager->encrypt($tokens['access_token']),
            $tokens['expires_in'],
            $provider['id']
        ]);

        // Also store in account_data
        $accountData = $provider['account_data'] ?? [];
        $accountData['oauth_refresh_token'] = $tokens['refresh_token'];
        $providerManager->updateProviderConfig($provider['id'], $accountData);

        // Redirect back to settings page
        header('Location: /admin/admin-google-voice.php?oauth=success');
        exit;
    } else {
        echo json_encode(['success' => false, 'error' => 'Token exchange failed']);
    }
}

/**
 * Send SMS via Google Voice
 */
function handleSendSMS() {
    global $providerManager;

    $provider = $providerManager->getProvider('google_voice');

    if (!$provider || !$provider['enabled']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Google Voice provider not enabled']);
        return;
    }

    $to = $_POST['to'] ?? '';
    $message = $_POST['message'] ?? $_POST['body'] ?? '';
    $from = $_POST['from'] ?? $provider['phone_number'];

    if (empty($to) || empty($message)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'To number and message required']);
        return;
    }

    $result = makeGoogleVoiceRequest('/messages', 'POST', [
        'to' => formatPhoneNumber($to),
        'text' => $message
    ], $provider);

    if ($result['success']) {
        $messageId = $providerManager->logMessage(
            $provider['id'],
            'google_voice',
            'outbound',
            $from,
            $to,
            $message,
            [
                'status' => 'sent',
                'message_sid' => $result['data']['id'] ?? null,
                'provider_data' => $result['data']
            ]
        );

        echo json_encode([
            'success' => true,
            'message_id' => $messageId,
            'provider_message_id' => $result['data']['id'] ?? null
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => $result['error'] ?? 'Failed to send SMS'
        ]);
    }
}

/**
 * List messages
 */
function handleListMessages() {
    global $pdo, $providerManager;

    $provider = $providerManager->getProvider('google_voice');

    if (!$provider) {
        echo json_encode(['success' => true, 'messages' => []]);
        return;
    }

    $limit = (int)($_GET['limit'] ?? 50);

    $stmt = $pdo->prepare("
        SELECT * FROM sms_messages
        WHERE provider_id = ?
        ORDER BY created_at DESC
        LIMIT ?
    ");

    $stmt->execute([$provider['id'], $limit]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'messages' => $messages
    ]);
}

/**
 * List calls
 */
function handleListCalls() {
    global $pdo, $providerManager;

    $provider = $providerManager->getProvider('google_voice');

    if (!$provider) {
        echo json_encode(['success' => true, 'calls' => []]);
        return;
    }

    $limit = (int)($_GET['limit'] ?? 50);

    $stmt = $pdo->prepare("
        SELECT * FROM call_logs
        WHERE provider_id = ?
        ORDER BY created_at DESC
        LIMIT ?
    ");

    $stmt->execute([$provider['id'], $limit]);
    $calls = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'calls' => $calls
    ]);
}

/**
 * Make outbound call
 */
function handleMakeCall() {
    global $providerManager;

    $provider = $providerManager->getProvider('google_voice');

    if (!$provider || !$provider['enabled']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Google Voice provider not enabled']);
        return;
    }

    $to = $_POST['to'] ?? '';
    $from = $_POST['from'] ?? $provider['phone_number'];

    if (empty($to)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'To number required']);
        return;
    }

    $result = makeGoogleVoiceRequest('/calls', 'POST', [
        'to' => formatPhoneNumber($to)
    ], $provider);

    if ($result['success']) {
        $callId = $providerManager->logCall(
            $provider['id'],
            'google_voice',
            'outbound',
            $from,
            $to,
            [
                'status' => 'initiated',
                'call_sid' => $result['data']['id'] ?? null,
                'provider_data' => $result['data']
            ]
        );

        echo json_encode([
            'success' => true,
            'call_id' => $callId,
            'provider_call_id' => $result['data']['id'] ?? null
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => $result['error'] ?? 'Failed to initiate call'
        ]);
    }
}

/**
 * Get voicemails
 */
function handleGetVoicemails() {
    global $providerManager;

    $provider = $providerManager->getProvider('google_voice');

    if (!$provider) {
        echo json_encode(['success' => true, 'voicemails' => []]);
        return;
    }

    $result = makeGoogleVoiceRequest('/voicemail', 'GET', [], $provider);

    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'voicemails' => $result['data']['messages'] ?? []
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $result['error'] ?? 'Failed to fetch voicemails'
        ]);
    }
}

/**
 * Get statistics
 */
function handleGetStatistics() {
    global $providerManager;

    $provider = $providerManager->getProvider('google_voice');

    if (!$provider) {
        echo json_encode(['success' => true, 'statistics' => []]);
        return;
    }

    $stats = $providerManager->getProviderStats($provider['id']);

    echo json_encode([
        'success' => true,
        'statistics' => [
            'total_messages' => $stats['messages']['total_messages'] ?? 0,
            'sms_sent' => $stats['messages']['sent'] ?? 0,
            'sms_received' => $stats['messages']['received'] ?? 0,
            'total_calls' => $stats['calls']['total_calls'] ?? 0,
            'calls_made' => $stats['calls']['made'] ?? 0,
            'calls_received' => $stats['calls']['received'] ?? 0,
            'total_call_duration' => $stats['calls']['total_duration'] ?? 0
        ]
    ]);
}

/**
 * Webhook: Incoming SMS
 */
function handleInboundSMS() {
    global $providerManager;

    $provider = $providerManager->getProvider('google_voice');

    if (!$provider) {
        http_response_code(404);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $from = $data['from'] ?? '';
    $to = $data['to'] ?? '';
    $body = $data['text'] ?? '';
    $messageSid = $data['id'] ?? '';

    $providerManager->logMessage(
        $provider['id'],
        'google_voice',
        'inbound',
        $from,
        $to,
        $body,
        [
            'status' => 'received',
            'message_sid' => $messageSid,
            'provider_data' => $data
        ]
    );

    $providerManager->logWebhook($provider['id'], 'google_voice', 'inbound_sms', $data);

    echo json_encode(['success' => true]);
}

/**
 * Webhook: Incoming call
 */
function handleInboundCall() {
    global $providerManager;

    $provider = $providerManager->getProvider('google_voice');

    if (!$provider) {
        http_response_code(404);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $from = $data['from'] ?? '';
    $to = $data['to'] ?? '';
    $callSid = $data['id'] ?? '';

    $providerManager->logCall(
        $provider['id'],
        'google_voice',
        'inbound',
        $from,
        $to,
        [
            'status' => 'ringing',
            'call_sid' => $callSid,
            'provider_data' => $data
        ]
    );

    $providerManager->logWebhook($provider['id'], 'google_voice', 'inbound_call', $data);

    echo json_encode(['success' => true]);
}

/**
 * Make Google Voice API request
 */
function makeGoogleVoiceRequest($endpoint, $method = 'GET', $data = [], $provider) {
    global $pdo, $providerManager;

    // Get OAuth token
    $stmt = $pdo->prepare("SELECT * FROM sms_provider_config WHERE provider_id = ?");
    $stmt->execute([$provider['id']]);
    $oauthConfig = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$oauthConfig) {
        return ['success' => false, 'error' => 'OAuth not configured'];
    }

    // Check if token needs refresh
    $now = new DateTime();
    $expiresAt = new DateTime($oauthConfig['oauth_token_expires_at'] ?? 'now');

    if ($now >= $expiresAt) {
        // Refresh token
        $refreshResult = refreshGoogleOAuthToken($provider, $oauthConfig, $pdo, $providerManager);
        if (!$refreshResult['success']) {
            return $refreshResult;
        }
        $accessToken = $refreshResult['access_token'];
    } else {
        $accessToken = $providerManager->decrypt($oauthConfig['oauth_access_token']);
    }

    $baseUrl = 'https://www.googleapis.com/voice/v1';

    $headers = [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ];

    $ch = curl_init();
    $url = $baseUrl . $endpoint;

    if ($method === 'GET' && !empty($data)) {
        $url .= '?' . http_build_query($data);
    }

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);

    curl_close($ch);

    if ($error) {
        return ['success' => false, 'error' => $error];
    }

    $responseData = json_decode($response, true);

    if ($httpCode >= 200 && $httpCode < 300) {
        return ['success' => true, 'data' => $responseData];
    } else {
        return [
            'success' => false,
            'error' => $responseData['error']['message'] ?? 'Request failed with code ' . $httpCode
        ];
    }
}

/**
 * Refresh Google OAuth token
 */
function refreshGoogleOAuthToken($provider, $oauthConfig, $pdo, $providerManager) {
    $tokenUrl = 'https://oauth2.googleapis.com/token';

    $ch = curl_init($tokenUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'client_id' => $oauthConfig['oauth_client_id'],
        'client_secret' => $providerManager->decrypt($oauthConfig['oauth_client_secret']),
        'refresh_token' => $providerManager->decrypt($oauthConfig['oauth_refresh_token']),
        'grant_type' => 'refresh_token'
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode == 200) {
        $tokens = json_decode($response, true);

        // Update tokens
        $stmt = $pdo->prepare("
            UPDATE sms_provider_config
            SET oauth_access_token = ?,
                oauth_token_expires_at = DATE_ADD(NOW(), INTERVAL ? SECOND)
            WHERE provider_id = ?
        ");

        $stmt->execute([
            $providerManager->encrypt($tokens['access_token']),
            $tokens['expires_in'],
            $provider['id']
        ]);

        return ['success' => true, 'access_token' => $tokens['access_token']];
    } else {
        return ['success' => false, 'error' => 'Token refresh failed'];
    }
}

/**
 * Format phone number to E.164
 */
function formatPhoneNumber($number) {
    $number = preg_replace('/[^0-9]/', '', $number);

    if (strlen($number) == 10) {
        $number = '1' . $number;
    }

    return '+' . $number;
}

/**
 * Get default configuration
 */
function getDefaultConfig() {
    return [
        'primary_number' => '',
        'display_name' => 'FlexPBX Main Line',
        'default_destination' => '2000',
        'after_hours_destination' => 'voicemail',
        'business_start' => '09:00',
        'business_end' => '18:00',
        'greeting_message' => 'Thank you for calling FlexPBX. Please leave a message after the tone.',
        'auto_reply_message' => 'Thank you for contacting FlexPBX! We will respond during business hours.',
        'call_limit_daily' => 1000,
        'sms_limit_daily' => 500,
        'enable_sms' => true,
        'enable_voicemail' => true,
        'auto_transcribe' => true,
        'enable_call_recording' => false,
        'enable_call_screening' => false,
        'email_notifications' => true,
        'auto_reply_enabled' => false,
        'business_hours_only' => true,
        'enabled' => false,
        'has_oauth' => false
    ];
}
