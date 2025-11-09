<?php
/**
 * FlexPBX User Signup API
 *
 * Handles user signup requests and admin approval
 *
 * Endpoints:
 * - POST /api/user-signup.php?action=submit - Submit signup request
 * - POST /api/user-signup.php?action=approve - Admin approves signup (auto-provisions)
 * - POST /api/user-signup.php?action=reject - Admin rejects signup
 * - GET  /api/user-signup.php?action=list - List pending signups (admin only)
 * - GET  /api/user-signup.php?action=get&id={id} - Get signup details
 *
 * @author FlexPBX
 * @version 1.0.0
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/AutoProvisioning.php';

// Get action
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

// Initialize auto-provisioning
$provisioning = new AutoProvisioning();

// Handle different actions
switch ($action) {
    case 'submit':
        handleSubmit();
        break;

    case 'approve':
        handleApprove();
        break;

    case 'reject':
        handleReject();
        break;

    case 'list':
        handleList();
        break;

    case 'get':
        handleGet();
        break;

    default:
        response(['error' => 'Invalid action'], 400);
}

/**
 * Handle signup submission
 */
function handleSubmit() {
    global $db;

    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);

    // Validate required fields
    $required = ['username', 'email', 'full_name'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            response(['error' => "Missing required field: $field"], 400);
        }
    }

    // Validate email format
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        response(['error' => 'Invalid email format'], 400);
    }

    // Check if username already exists
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$data['username']]);
    if ($stmt->fetch()) {
        response(['error' => 'Username already exists'], 409);
    }

    // Check if email already exists
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$data['email']]);
    if ($stmt->fetch()) {
        response(['error' => 'Email already registered'], 409);
    }

    // Check if email already has pending request
    $stmt = $db->prepare("SELECT id FROM user_signup_requests WHERE email = ? AND status = 'pending'");
    $stmt->execute([$data['email']]);
    if ($stmt->fetch()) {
        response(['error' => 'You already have a pending signup request'], 409);
    }

    // Insert signup request
    $stmt = $db->prepare("
        INSERT INTO user_signup_requests (
            username,
            email,
            full_name,
            requested_extension,
            phone_number,
            company_name,
            status
        ) VALUES (?, ?, ?, ?, ?, ?, 'pending')
    ");

    try {
        $stmt->execute([
            $data['username'],
            $data['email'],
            $data['full_name'],
            $data['requested_extension'] ?? null,
            $data['phone_number'] ?? null,
            $data['company_name'] ?? null
        ]);

        $request_id = $db->lastInsertId();

        // TODO: Send email to admins about new signup request

        response([
            'success' => true,
            'message' => 'Signup request submitted successfully',
            'request_id' => $request_id
        ]);

    } catch (Exception $e) {
        response(['error' => 'Failed to submit signup request: ' . $e->getMessage()], 500);
    }
}

/**
 * Handle signup approval (triggers auto-provisioning)
 */
function handleApprove() {
    global $db, $provisioning;

    // Check admin authorization
    // TODO: Implement proper admin authentication
    // For now, we'll skip this check but in production this should be enforced

    // Get request ID
    $request_id = $_POST['request_id'] ?? $_GET['request_id'] ?? null;
    if (!$request_id) {
        response(['error' => 'Missing request_id'], 400);
    }

    // Get signup request
    $stmt = $db->prepare("SELECT * FROM user_signup_requests WHERE id = ?");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch();

    if (!$request) {
        response(['error' => 'Signup request not found'], 404);
    }

    if ($request['status'] !== 'pending') {
        response(['error' => 'Signup request already processed'], 400);
    }

    // Auto-provision user
    $result = $provisioning->provisionNewUser(
        $request['username'],
        $request['email'],
        $request['requested_extension'], // Can be null for auto-assignment
        null, // Auto-generate password
        $request['full_name'],
        'user', // Default role
        ['send_email' => true]
    );

    if ($result['success']) {
        // Update signup request status
        $stmt = $db->prepare("
            UPDATE user_signup_requests
            SET status = 'approved',
                approved_by = ?,
                approved_at = NOW(),
                approval_notes = ?
            WHERE id = ?
        ");

        $approval_notes = "Auto-provisioned: Extension {$result['extension']}";
        $stmt->execute([null, $approval_notes, $request_id]); // TODO: Get actual admin user ID

        response([
            'success' => true,
            'message' => 'User approved and provisioned successfully',
            'user_id' => $result['user_id'],
            'extension' => $result['extension'],
            'credentials' => [
                'username' => $result['credentials']['username'],
                'extension' => $result['credentials']['extension'],
                'did' => $result['credentials']['did']
                // Don't return password in API response for security
            ]
        ]);
    } else {
        // Update request with error
        $stmt = $db->prepare("
            UPDATE user_signup_requests
            SET approval_notes = ?
            WHERE id = ?
        ");

        $stmt->execute(["Provisioning failed: {$result['error']}", $request_id]);

        response([
            'success' => false,
            'error' => 'Failed to provision user: ' . $result['error']
        ], 500);
    }
}

/**
 * Handle signup rejection
 */
function handleReject() {
    global $db;

    // Check admin authorization
    // TODO: Implement proper admin authentication

    // Get request ID and reason
    $request_id = $_POST['request_id'] ?? $_GET['request_id'] ?? null;
    $reason = $_POST['reason'] ?? 'No reason provided';

    if (!$request_id) {
        response(['error' => 'Missing request_id'], 400);
    }

    // Get signup request
    $stmt = $db->prepare("SELECT * FROM user_signup_requests WHERE id = ?");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch();

    if (!$request) {
        response(['error' => 'Signup request not found'], 404);
    }

    if ($request['status'] !== 'pending') {
        response(['error' => 'Signup request already processed'], 400);
    }

    // Update request status
    $stmt = $db->prepare("
        UPDATE user_signup_requests
        SET status = 'rejected',
            approved_by = ?,
            approved_at = NOW(),
            approval_notes = ?
        WHERE id = ?
    ");

    $stmt->execute([null, $reason, $request_id]); // TODO: Get actual admin user ID

    // TODO: Send rejection email to user

    response([
        'success' => true,
        'message' => 'Signup request rejected'
    ]);
}

/**
 * List signup requests
 */
function handleList() {
    global $db;

    // Check admin authorization
    // TODO: Implement proper admin authentication

    // Get status filter
    $status = $_GET['status'] ?? 'pending';
    $limit = (int)($_GET['limit'] ?? 50);
    $offset = (int)($_GET['offset'] ?? 0);

    // Build query
    $query = "
        SELECT *
        FROM user_signup_requests
        WHERE 1=1
    ";

    $params = [];

    if ($status !== 'all') {
        $query .= " AND status = ?";
        $params[] = $status;
    }

    $query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $requests = $stmt->fetchAll();

    // Get total count
    $count_query = "SELECT COUNT(*) as total FROM user_signup_requests WHERE 1=1";
    $count_params = [];

    if ($status !== 'all') {
        $count_query .= " AND status = ?";
        $count_params[] = $status;
    }

    $count_stmt = $db->prepare($count_query);
    $count_stmt->execute($count_params);
    $total = $count_stmt->fetch()['total'];

    response([
        'success' => true,
        'requests' => $requests,
        'total' => $total,
        'limit' => $limit,
        'offset' => $offset
    ]);
}

/**
 * Get single signup request
 */
function handleGet() {
    global $db;

    $request_id = $_GET['id'] ?? null;

    if (!$request_id) {
        response(['error' => 'Missing request ID'], 400);
    }

    $stmt = $db->prepare("SELECT * FROM user_signup_requests WHERE id = ?");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch();

    if (!$request) {
        response(['error' => 'Signup request not found'], 404);
    }

    response([
        'success' => true,
        'request' => $request
    ]);
}

/**
 * Send JSON response
 */
function response($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit();
}
