<?php
/**
 * Mastodon Authentication API
 * OAuth2 flow and authentication endpoint
 *
 * Version: 1.0
 * Compatible with: FlexPBX v1.2+
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/MastodonAuth.php';

$action = $_GET['action'] ?? 'login';

// Initialize Mastodon Auth
$mastodonConfig = require __DIR__ . '/../config/mastodon-config.php';
$mastodonAuth = new MastodonAuth($pdo, $mastodonConfig);

try {
    switch ($action) {
        case 'login':
            initiateLogin();
            break;

        case 'callback':
            handleCallback();
            break;

        case 'link':
            linkAccount();
            break;

        case 'unlink':
            unlinkAccount();
            break;

        case 'status':
            getStatus();
            break;

        case 'instances':
            listInstances();
            break;

        case 'register_instance':
            registerInstance();
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Initiate Mastodon login
 */
function initiateLogin() {
    global $mastodonAuth;

    $instanceUrl = $_GET['instance'] ?? 'https://md.tappedin.fm';
    $state = bin2hex(random_bytes(16));

    // Store state in session for verification
    session_start();
    $_SESSION['mastodon_oauth_state'] = $state;
    $_SESSION['mastodon_instance'] = $instanceUrl;

    $authUrl = $mastodonAuth->getAuthorizationUrl($instanceUrl, $state);

    if ($authUrl) {
        // Redirect to Mastodon
        header('Location: ' . $authUrl);
        exit;
    }

    echo json_encode([
        'success' => false,
        'error' => 'Failed to generate authorization URL'
    ]);
}

/**
 * Handle OAuth2 callback from Mastodon
 */
function handleCallback() {
    global $mastodonAuth, $pdo;

    session_start();

    $code = $_GET['code'] ?? null;
    $state = $_GET['state'] ?? null;
    $error = $_GET['error'] ?? null;

    // Check for errors
    if ($error) {
        die("Authorization error: " . htmlspecialchars($error));
    }

    // Verify state
    if (!$state || $state !== ($_SESSION['mastodon_oauth_state'] ?? '')) {
        die("Invalid state parameter");
    }

    $instanceUrl = $_SESSION['mastodon_instance'] ?? 'https://md.tappedin.fm';

    // Exchange code for token
    $tokenResponse = $mastodonAuth->getAccessToken($code, $instanceUrl);

    if (!$tokenResponse['success']) {
        die("Failed to get access token: " . $tokenResponse['error']);
    }

    // Get user profile
    $profileResponse = $mastodonAuth->getUserProfile($tokenResponse['access_token'], $instanceUrl);

    if (!$profileResponse['success']) {
        die("Failed to get user profile: " . $profileResponse['error']);
    }

    $profile = $profileResponse['profile'];

    // Authenticate or create user
    $authResult = $mastodonAuth->authenticateUser($profile['id'], $instanceUrl);

    if ($authResult['success']) {
        // Set session
        $_SESSION['user_id'] = $authResult['user']['id'];
        $_SESSION['username'] = $authResult['user']['username'];
        $_SESSION['logged_in'] = true;
        $_SESSION['auth_method'] = 'mastodon';

        // Show success page
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Login Successful - FlexPBX</title>
            <style>
                body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
                .success { color: #28a745; font-size: 24px; }
                .info { color: #6c757d; margin: 20px 0; }
            </style>
        </head>
        <body>
            <h1 class="success">✓ Login Successful!</h1>
            <p class="info">Welcome, <?= htmlspecialchars($profile['display_name']) ?>!</p>
            <p>You are now logged in via Mastodon.</p>
            <?php if (isset($authResult['extension'])): ?>
                <p><strong>Your Extension:</strong> <?= htmlspecialchars($authResult['extension']) ?>@flexpbx.devinecreations.net</p>
            <?php endif; ?>
            <p><a href="/user-portal/">Continue to User Portal →</a></p>
        </body>
        </html>
        <?php
        exit;
    }

    die("Authentication failed: " . $authResult['error']);
}

/**
 * Link Mastodon account to existing FlexPBX user
 */
function linkAccount() {
    global $mastodonAuth;

    session_start();

    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        exit;
    }

    $userId = $_SESSION['user_id'];
    $code = $_POST['code'] ?? null;
    $instanceUrl = $_POST['instance'] ?? 'https://md.tappedin.fm';

    if (!$code) {
        throw new Exception('Authorization code required');
    }

    // Exchange code for token
    $tokenResponse = $mastodonAuth->getAccessToken($code, $instanceUrl);

    if (!$tokenResponse['success']) {
        throw new Exception($tokenResponse['error']);
    }

    // Get user profile
    $profileResponse = $mastodonAuth->getUserProfile($tokenResponse['access_token'], $instanceUrl);

    if (!$profileResponse['success']) {
        throw new Exception($profileResponse['error']);
    }

    $profile = $profileResponse['profile'];

    // Link account
    $result = $mastodonAuth->linkAccount(
        $userId,
        $profile['id'],
        $instanceUrl,
        $tokenResponse['access_token'],
        $profile
    );

    echo json_encode($result);
}

/**
 * Unlink Mastodon account
 */
function unlinkAccount() {
    global $pdo;

    session_start();

    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        exit;
    }

    $userId = $_SESSION['user_id'];

    // Remove linked account
    $stmt = $pdo->prepare("DELETE FROM mastodon_linked_accounts WHERE user_id = ?");
    $stmt->execute([$userId]);

    // Remove tokens
    $stmt = $pdo->prepare("DELETE FROM mastodon_oauth_tokens WHERE user_id = ?");
    $stmt->execute([$userId]);

    echo json_encode([
        'success' => true,
        'message' => 'Mastodon account unlinked'
    ]);
}

/**
 * Get authentication status
 */
function getStatus() {
    global $pdo;

    session_start();

    if (!isset($_SESSION['user_id'])) {
        echo json_encode([
            'success' => true,
            'logged_in' => false
        ]);
        exit;
    }

    $userId = $_SESSION['user_id'];

    // Get linked accounts
    $stmt = $pdo->prepare("
        SELECT instance_url, username, display_name, is_primary
        FROM mastodon_linked_accounts
        WHERE user_id = ?
    ");
    $stmt->execute([$userId]);
    $linkedAccounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'logged_in' => true,
        'user_id' => $userId,
        'auth_method' => $_SESSION['auth_method'] ?? 'local',
        'linked_accounts' => $linkedAccounts
    ]);
}

/**
 * List available Mastodon instances
 */
function listInstances() {
    global $pdo;

    $stmt = $pdo->query("
        SELECT instance_url, is_default, is_trusted
        FROM mastodon_instances
        ORDER BY is_default DESC, instance_url ASC
    ");
    $instances = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'instances' => $instances
    ]);
}

/**
 * Register new Mastodon instance
 */
function registerInstance() {
    global $mastodonAuth;

    session_start();

    // Only admins can register instances
    if (($_SESSION['user_role'] ?? '') !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Admin access required']);
        exit;
    }

    $instanceUrl = $_POST['instance_url'] ?? null;

    if (!$instanceUrl) {
        throw new Exception('instance_url parameter required');
    }

    $result = $mastodonAuth->registerApplication($instanceUrl);

    echo json_encode($result);
}
