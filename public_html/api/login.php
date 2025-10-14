<?php
/**
 * FlexPBX Enhanced Login API
 * Supports username/extension login for users and admins
 * Syncs with both file system and database
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST.']);
    exit();
}

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Fallback to POST data if JSON parsing fails
if (!$data) {
    $data = $_POST;
}

$identifier = trim($data['username'] ?? $data['extension'] ?? $data['identifier'] ?? '');
$password = $data['password'] ?? '';
$account_type = strtolower($data['account_type'] ?? 'user'); // 'user' or 'admin'

// Validate input
if (empty($identifier) || empty($password)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Username/extension and password are required.'
    ]);
    exit();
}

// Configuration
$users_dir = '/home/flexpbxuser/users';
$admins_dir = '/home/flexpbxuser/admins';
$config = include('/home/flexpbxuser/public_html/api/config.php');

$authenticated = false;
$user_data = null;
$auth_source = 'none';

/**
 * Try Database Authentication First
 */
try {
    $pdo = new PDO(
        "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4",
        $config['db_user'],
        $config['db_password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    if ($account_type === 'user') {
        // Try user login via database
        $stmt = $pdo->prepare("
            SELECT id, username, extension, email, full_name, role, is_active
            FROM users
            WHERE (username = ? OR extension = ? OR email = ?)
            AND password_hash = SHA2(?, 256)
            AND is_active = TRUE
            AND role = 'user'
        ");
        $stmt->execute([$identifier, $identifier, $identifier, $password]);
        $db_user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($db_user) {
            $user_data = $db_user;
            $authenticated = true;
            $auth_source = 'database';

            // Update last login
            $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$db_user['id']]);
        }
    } elseif ($account_type === 'admin') {
        // Try admin login via database
        $stmt = $pdo->prepare("
            SELECT id, username, email, full_name, role, is_active
            FROM users
            WHERE (username = ? OR email = ?)
            AND password_hash = SHA2(?, 256)
            AND is_active = TRUE
            AND role IN ('admin', 'super_admin', 'administrator')
        ");
        $stmt->execute([$identifier, $identifier, $password]);
        $db_user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($db_user) {
            $user_data = $db_user;
            $authenticated = true;
            $auth_source = 'database';

            // Update last login
            $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$db_user['id']]);
        }
    }
} catch (Exception $e) {
    // Database unavailable, will try file-based auth
    error_log("Database auth failed, trying file-based: " . $e->getMessage());
}

/**
 * Fallback to File-Based Authentication
 */
if (!$authenticated) {
    if ($account_type === 'user') {
        // Try to find user by extension (numeric) or username (string)
        $user_file = null;

        if (is_numeric($identifier)) {
            // Search by extension
            $possible_file = $users_dir . '/user_' . $identifier . '.json';
            if (file_exists($possible_file)) {
                $user_file = $possible_file;
            }
        }

        // If not found, search by username or email
        if (!$user_file && file_exists($users_dir)) {
            $files = glob($users_dir . '/user_*.json');
            foreach ($files as $file) {
                $temp_data = json_decode(file_get_contents($file), true);
                if ((isset($temp_data['username']) && $temp_data['username'] === $identifier) ||
                    (isset($temp_data['extension']) && $temp_data['extension'] === $identifier) ||
                    (isset($temp_data['email']) && $temp_data['email'] === $identifier)) {
                    $user_file = $file;
                    break;
                }
            }
        }

        if ($user_file && file_exists($user_file)) {
            $file_data = json_decode(file_get_contents($user_file), true);

            // Verify password
            if (isset($file_data['password'])) {
                // Support both hashed and plain text passwords
                if (password_verify($password, $file_data['password'])) {
                    $authenticated = true;
                    $user_data = $file_data;
                    $auth_source = 'file';
                } elseif ($file_data['password'] === $password) {
                    // Plain text match (upgrade to hash)
                    $authenticated = true;
                    $user_data = $file_data;
                    $auth_source = 'file';

                    // Upgrade to hashed password
                    $file_data['password'] = password_hash($password, PASSWORD_DEFAULT);
                    file_put_contents($user_file, json_encode($file_data, JSON_PRETTY_PRINT));
                }
            }

            if ($authenticated) {
                // Update last login in file
                $file_data['last_login'] = date('Y-m-d H:i:s');
                $file_data['last_login_ip'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                file_put_contents($user_file, json_encode($file_data, JSON_PRETTY_PRINT));
            }
        }
    } elseif ($account_type === 'admin') {
        // Try admin file
        $admin_file = $admins_dir . '/admin_' . $identifier . '.json';

        if (file_exists($admin_file)) {
            $file_data = json_decode(file_get_contents($admin_file), true);

            // Verify password
            if (isset($file_data['password'])) {
                if (password_verify($password, $file_data['password'])) {
                    $authenticated = true;
                    $user_data = $file_data;
                    $auth_source = 'file';
                } elseif ($file_data['password'] === $password) {
                    // Plain text match (upgrade to hash)
                    $authenticated = true;
                    $user_data = $file_data;
                    $auth_source = 'file';

                    // Upgrade to hashed password
                    $file_data['password'] = password_hash($password, PASSWORD_DEFAULT);
                    file_put_contents($admin_file, json_encode($file_data, JSON_PRETTY_PRINT));
                }
            }

            if ($authenticated) {
                // Update last login
                $file_data['last_login'] = date('Y-m-d H:i:s');
                $file_data['last_login_ip'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                file_put_contents($admin_file, json_encode($file_data, JSON_PRETTY_PRINT));
            }
        }
    }
}

/**
 * Check if email setup is required
 */
function needsEmailSetup($email) {
    // List of placeholder/default email values
    $placeholder_emails = [
        '',
        'user@example.com',
        'admin@example.com',
        'noemail@localhost',
        'user@localhost',
        'test@test.com',
        'changeme@example.com',
        'administrator@localhost'
    ];

    return empty($email) || in_array(strtolower(trim($email)), $placeholder_emails);
}

/**
 * Return Response
 */
if ($authenticated && $user_data) {
    http_response_code(200);

    $user_email = $user_data['email'] ?? '';
    $email_setup_required = needsEmailSetup($user_email);

    $response = [
        'success' => true,
        'account_type' => $account_type,
        'auth_source' => $auth_source,
        'session_token' => bin2hex(random_bytes(32)),
        'message' => 'Authentication successful',
        'email_setup_required' => $email_setup_required
    ];

    if ($account_type === 'user') {
        $response['extension'] = $user_data['extension'] ?? $user_data['username'];
        $response['username'] = $user_data['username'] ?? $user_data['extension'];
        $response['email'] = $user_email;
        $response['full_name'] = $user_data['full_name'] ?? '';
        $response['sip_settings'] = [
            'server' => 'flexpbx.devinecreations.net',
            'port' => 5060,
            'transport' => 'UDP'
        ];

        // Add helpful message if email setup is required
        if ($email_setup_required) {
            $response['message'] = 'Authentication successful. Please set your email address.';
            $response['setup_url'] = '/user-portal/setup-email.php';
        }
    } else {
        $response['username'] = $user_data['username'];
        $response['email'] = $user_email;
        $response['full_name'] = $user_data['full_name'] ?? '';
        $response['role'] = $user_data['role'] ?? 'administrator';

        // Add helpful message if email setup is required
        if ($email_setup_required) {
            $response['message'] = 'Authentication successful. Please set your administrator email address.';
            $response['setup_url'] = '/admin/setup-email.php';
        }
    }

    echo json_encode($response);
} else {
    // Authentication failed
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid username/extension or password.',
        'account_type' => $account_type
    ]);
}
