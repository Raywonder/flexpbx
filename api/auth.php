<?php
// FlexPBX Remote Server - Authentication API
// Version: 1.0.0

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Database configuration
$db_config = [
    'host' => 'localhost',
    'dbname' => 'flexpbx',
    'username' => 'flexpbx',
    'password' => 'your_db_password' // Will be set during installation
];

// Database connection
try {
    $pdo = new PDO(
        "mysql:host={$db_config['host']};dbname={$db_config['dbname']};charset=utf8mb4",
        $db_config['username'],
        $db_config['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed', 'success' => false]);
    exit;
}

// Get request path and method
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);
$path = str_replace('/api/auth', '', $path);
$path = trim($path, '/');
$method = $_SERVER['REQUEST_METHOD'];

// Helper functions
function generatePincode() {
    return str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
}

function generateApiKey() {
    return bin2hex(random_bytes(32));
}

function logSystemEvent($pdo, $level, $component, $message, $user_id = null, $ip_address = null, $additional_data = null) {
    $stmt = $pdo->prepare("CALL LogSystemEvent(?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $level,
        $component,
        $message,
        $user_id,
        $ip_address ?: $_SERVER['REMOTE_ADDR'],
        $additional_data ? json_encode($additional_data) : null
    ]);
}

// Route handling
switch ($path) {
    case '':
    case 'info':
        echo json_encode([
            'success' => true,
            'name' => 'FlexPBX Authentication API',
            'version' => '1.0.0',
            'endpoints' => [
                '/api/auth/device/register' => 'Register new device',
                '/api/auth/device/authorize' => 'Authorize device with pincode',
                '/api/auth/user/login' => 'User login',
                '/api/auth/user/create' => 'Create new user',
                '/api/auth/pincode/generate' => 'Generate device pincode',
                '/api/auth/status' => 'Check authentication status'
            ]
        ]);
        break;

    case 'device/register':
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed', 'success' => false]);
            break;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $device_name = $input['device_name'] ?? '';
        $device_type = $input['device_type'] ?? '';
        $device_identifier = $input['device_identifier'] ?? '';

        if (empty($device_name) || empty($device_type) || empty($device_identifier)) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields', 'success' => false]);
            break;
        }

        try {
            // Check if device already exists
            $stmt = $pdo->prepare("SELECT id FROM devices WHERE device_identifier = ?");
            $stmt->execute([$device_identifier]);

            if ($stmt->rowCount() > 0) {
                http_response_code(409);
                echo json_encode(['error' => 'Device already registered', 'success' => false]);
                break;
            }

            // Register new device
            $stmt = $pdo->prepare("
                INSERT INTO devices (device_name, device_type, device_identifier, device_info, is_active)
                VALUES (?, ?, ?, ?, TRUE)
            ");

            $device_info = json_encode([
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'registration_ip' => $_SERVER['REMOTE_ADDR'],
                'registration_time' => date('c')
            ]);

            $stmt->execute([$device_name, $device_type, $device_identifier, $device_info]);
            $device_id = $pdo->lastInsertId();

            logSystemEvent($pdo, 'INFO', 'AUTH', "Device registered: {$device_name} ({$device_type})", null, null, [
                'device_id' => $device_id,
                'device_identifier' => $device_identifier
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Device registered successfully',
                'data' => [
                    'device_id' => $device_id,
                    'status' => 'registered',
                    'next_step' => 'Generate pincode from admin panel or API'
                ]
            ]);

        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Registration failed', 'success' => false]);
        }
        break;

    case 'pincode/generate':
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed', 'success' => false]);
            break;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $device_id = $input['device_id'] ?? 0;
        $device_identifier = $input['device_identifier'] ?? '';

        if (empty($device_id) && empty($device_identifier)) {
            http_response_code(400);
            echo json_encode(['error' => 'Device ID or identifier required', 'success' => false]);
            break;
        }

        try {
            // Find device
            if (!empty($device_identifier)) {
                $stmt = $pdo->prepare("SELECT id FROM devices WHERE device_identifier = ?");
                $stmt->execute([$device_identifier]);
                $device = $stmt->fetch();
                $device_id = $device['id'] ?? 0;
            }

            if (!$device_id) {
                http_response_code(404);
                echo json_encode(['error' => 'Device not found', 'success' => false]);
                break;
            }

            // Generate pincode using stored procedure
            $stmt = $pdo->prepare("CALL GenerateDevicePincode(?)");
            $stmt->execute([$device_id]);
            $result = $stmt->fetch();

            logSystemEvent($pdo, 'INFO', 'AUTH', "Pincode generated for device ID: {$device_id}");

            echo json_encode([
                'success' => true,
                'message' => 'Pincode generated successfully',
                'data' => [
                    'pincode' => $result['pincode'],
                    'expires_at' => $result['expires_at'],
                    'expires_in_minutes' => 60
                ]
            ]);

        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Pincode generation failed', 'success' => false]);
        }
        break;

    case 'device/authorize':
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed', 'success' => false]);
            break;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $pincode = $input['pincode'] ?? '';
        $device_identifier = $input['device_identifier'] ?? '';

        if (empty($pincode) || empty($device_identifier)) {
            http_response_code(400);
            echo json_encode(['error' => 'Pincode and device identifier required', 'success' => false]);
            break;
        }

        try {
            // Authorize device using stored procedure
            $stmt = $pdo->prepare("CALL AuthorizeDevice(?, ?)");
            $stmt->execute([$pincode, $device_identifier]);
            $result = $stmt->fetch();

            if ($result['authorized']) {
                // Generate API key for authorized device
                $api_key = generateApiKey();

                $stmt = $pdo->prepare("
                    INSERT INTO api_keys (key_name, api_key, permissions, rate_limit, is_active)
                    VALUES (?, ?, ?, ?, TRUE)
                ");

                $key_name = "Device_{$device_identifier}";
                $permissions = json_encode(['device_control' => true, 'read' => true]);

                $stmt->execute([$key_name, $api_key, $permissions, 5000]);

                // Update device with API key
                $stmt = $pdo->prepare("
                    UPDATE devices
                    SET last_connected = NOW(), connection_ip = ?
                    WHERE device_identifier = ?
                ");
                $stmt->execute([$_SERVER['REMOTE_ADDR'], $device_identifier]);

                logSystemEvent($pdo, 'INFO', 'AUTH', "Device authorized: {$device_identifier}");

                echo json_encode([
                    'success' => true,
                    'message' => 'Device authorized successfully',
                    'data' => [
                        'api_key' => $api_key,
                        'authorized' => true,
                        'status' => 'active'
                    ]
                ]);
            } else {
                logSystemEvent($pdo, 'WARNING', 'AUTH', "Failed device authorization: {$device_identifier}", null, null, [
                    'pincode' => $pincode,
                    'reason' => $result['message']
                ]);

                http_response_code(401);
                echo json_encode([
                    'success' => false,
                    'error' => $result['message']
                ]);
            }

        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Authorization failed', 'success' => false]);
        }
        break;

    case 'user/login':
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed', 'success' => false]);
            break;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $username = $input['username'] ?? '';
        $password = $input['password'] ?? '';

        if (empty($username) || empty($password)) {
            http_response_code(400);
            echo json_encode(['error' => 'Username and password required', 'success' => false]);
            break;
        }

        try {
            $stmt = $pdo->prepare("
                SELECT id, username, email, full_name, role, api_key, is_active
                FROM users
                WHERE (username = ? OR email = ?) AND password_hash = SHA2(?, 256) AND is_active = TRUE
            ");
            $stmt->execute([$username, $username, $password]);
            $user = $stmt->fetch();

            if ($user) {
                // Update last login
                $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $stmt->execute([$user['id']]);

                logSystemEvent($pdo, 'INFO', 'AUTH', "User login successful: {$username}", $user['id']);

                echo json_encode([
                    'success' => true,
                    'message' => 'Login successful',
                    'data' => [
                        'user_id' => $user['id'],
                        'username' => $user['username'],
                        'email' => $user['email'],
                        'full_name' => $user['full_name'],
                        'role' => $user['role'],
                        'api_key' => $user['api_key']
                    ]
                ]);
            } else {
                logSystemEvent($pdo, 'WARNING', 'AUTH', "Failed login attempt: {$username}");

                http_response_code(401);
                echo json_encode(['error' => 'Invalid credentials', 'success' => false]);
            }

        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Login failed', 'success' => false]);
        }
        break;

    case 'user/create':
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed', 'success' => false]);
            break;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $username = $input['username'] ?? '';
        $email = $input['email'] ?? '';
        $password = $input['password'] ?? '';
        $full_name = $input['full_name'] ?? '';
        $role = $input['role'] ?? 'user';

        if (empty($username) || empty($email) || empty($password) || empty($full_name)) {
            http_response_code(400);
            echo json_encode(['error' => 'All fields required', 'success' => false]);
            break;
        }

        try {
            // Check if user exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);

            if ($stmt->rowCount() > 0) {
                http_response_code(409);
                echo json_encode(['error' => 'User already exists', 'success' => false]);
                break;
            }

            // Create user
            $api_key = generateApiKey();

            $stmt = $pdo->prepare("
                INSERT INTO users (username, email, password_hash, full_name, role, api_key)
                VALUES (?, ?, SHA2(?, 256), ?, ?, ?)
            ");

            $stmt->execute([$username, $email, $password, $full_name, $role, $api_key]);
            $user_id = $pdo->lastInsertId();

            logSystemEvent($pdo, 'INFO', 'AUTH', "User created: {$username}", $user_id);

            echo json_encode([
                'success' => true,
                'message' => 'User created successfully',
                'data' => [
                    'user_id' => $user_id,
                    'username' => $username,
                    'api_key' => $api_key
                ]
            ]);

        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'User creation failed', 'success' => false]);
        }
        break;

    case 'status':
        $headers = getallheaders();
        $api_key = $headers['X-API-Key'] ?? $_GET['api_key'] ?? '';

        if (empty($api_key)) {
            http_response_code(401);
            echo json_encode(['error' => 'API key required', 'success' => false]);
            break;
        }

        try {
            $stmt = $pdo->prepare("
                SELECT ak.key_name, ak.permissions, ak.rate_limit, ak.requests_today,
                       u.username, u.full_name, u.role
                FROM api_keys ak
                LEFT JOIN users u ON ak.user_id = u.id
                WHERE ak.api_key = ? AND ak.is_active = TRUE
            ");
            $stmt->execute([$api_key]);
            $key_info = $stmt->fetch();

            if ($key_info) {
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'authenticated' => true,
                        'key_name' => $key_info['key_name'],
                        'permissions' => json_decode($key_info['permissions'], true),
                        'rate_limit' => $key_info['rate_limit'],
                        'requests_today' => $key_info['requests_today'],
                        'user' => $key_info['username'] ? [
                            'username' => $key_info['username'],
                            'full_name' => $key_info['full_name'],
                            'role' => $key_info['role']
                        ] : null
                    ]
                ]);
            } else {
                http_response_code(401);
                echo json_encode(['error' => 'Invalid API key', 'success' => false]);
            }

        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Status check failed', 'success' => false]);
        }
        break;

    default:
        http_response_code(404);
        echo json_encode([
            'error' => 'Endpoint not found',
            'success' => false,
            'available_endpoints' => [
                '/api/auth/device/register',
                '/api/auth/device/authorize',
                '/api/auth/user/login',
                '/api/auth/user/create',
                '/api/auth/pincode/generate',
                '/api/auth/status'
            ]
        ]);
}
?>