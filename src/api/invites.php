<?php
/**
 * FlexPBX Invite System API
 * Manages user and admin invitations
 *
 * Endpoints:
 * - POST ?action=send_invite - Send invitation email
 * - GET  ?action=verify_invite&token=X - Verify invitation token
 * - POST ?action=accept_invite - Accept invitation and create account
 * - GET  ?action=list_invites - List all pending invites (admin only)
 * - POST ?action=revoke_invite - Revoke an invitation (admin only)
 */

header('Content-Type: application/json');
session_start();

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$invites_dir = '/home/flexpbxuser/invites';

// Create invites directory if it doesn't exist
if (!is_dir($invites_dir)) {
    mkdir($invites_dir, 0750, true);
}

/**
 * Send invitation
 */
if ($action === 'send_invite' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check admin authentication
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Admin access required']);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    $email = $data['email'] ?? '';
    $role = $data['role'] ?? 'user'; // user, admin, supervisor, agent
    $full_name = $data['full_name'] ?? '';
    $extension = $data['extension'] ?? null; // Optional pre-assigned extension
    $message = $data['message'] ?? ''; // Optional personal message

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid email address']);
        exit;
    }

    // Generate unique invitation token
    $invite_token = bin2hex(random_bytes(32));
    $invite_id = uniqid('invite_');
    $created_by = $_SESSION['admin_username'];
    $created_at = time();
    $expires_at = $created_at + (7 * 24 * 60 * 60); // 7 days

    // Create invitation record
    $invitation = [
        'id' => $invite_id,
        'token' => password_hash($invite_token, PASSWORD_DEFAULT),
        'email' => $email,
        'role' => $role,
        'full_name' => $full_name,
        'extension' => $extension,
        'message' => $message,
        'created_by' => $created_by,
        'created_at' => $created_at,
        'expires_at' => $expires_at,
        'status' => 'pending', // pending, accepted, revoked, expired
        'accepted_at' => null,
        'accepted_by_ip' => null
    ];

    // Save invitation
    $invite_file = $invites_dir . '/' . $invite_id . '.json';
    file_put_contents($invite_file, json_encode($invitation, JSON_PRETTY_PRINT));

    // Send invitation email
    $invite_url = 'https://' . $_SERVER['HTTP_HOST'] . '/invite.php?token=' . urlencode($invite_token) . '&id=' . urlencode($invite_id);

    $email_subject = 'You\'re Invited to FlexPBX';

    $email_body = "Hello" . ($full_name ? " {$full_name}" : "") . ",\n\n";
    $email_body .= "You've been invited to join FlexPBX as a {$role}.\n\n";

    if ($message) {
        $email_body .= "Message from {$created_by}:\n{$message}\n\n";
    }

    $email_body .= "To accept this invitation and create your account, please click the link below:\n\n";
    $email_body .= $invite_url . "\n\n";
    $email_body .= "This invitation will expire in 7 days (" . date('Y-m-d H:i:s', $expires_at) . ").\n\n";

    if ($extension) {
        $email_body .= "Your extension: {$extension}\n\n";
    }

    $email_body .= "If you did not expect this invitation, you can safely ignore this email.\n\n";
    $email_body .= "---\n";
    $email_body .= "FlexPBX - Flexible PBX System\n";
    $email_body .= "https://" . $_SERVER['HTTP_HOST'];

    $headers = "From: noreply@" . $_SERVER['HTTP_HOST'] . "\r\n";
    $headers .= "Reply-To: {$created_by}@" . $_SERVER['HTTP_HOST'] . "\r\n";
    $headers .= "X-Mailer: FlexPBX";

    $email_sent = mail($email, $email_subject, $email_body, $headers);

    echo json_encode([
        'success' => true,
        'invite_id' => $invite_id,
        'invite_url' => $invite_url,
        'expires_at' => date('Y-m-d H:i:s', $expires_at),
        'email_sent' => $email_sent
    ]);
    exit;
}

/**
 * Verify invitation token
 */
if ($action === 'verify_invite' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $token = $_GET['token'] ?? '';
    $invite_id = $_GET['id'] ?? '';

    if (empty($token) || empty($invite_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing token or invite ID']);
        exit;
    }

    $invite_file = $invites_dir . '/' . $invite_id . '.json';

    if (!file_exists($invite_file)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Invitation not found']);
        exit;
    }

    $invitation = json_decode(file_get_contents($invite_file), true);

    // Check if expired
    if ($invitation['expires_at'] < time()) {
        $invitation['status'] = 'expired';
        file_put_contents($invite_file, json_encode($invitation, JSON_PRETTY_PRINT));

        http_response_code(410);
        echo json_encode(['success' => false, 'error' => 'Invitation expired']);
        exit;
    }

    // Check if already accepted
    if ($invitation['status'] === 'accepted') {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Invitation already accepted']);
        exit;
    }

    // Check if revoked
    if ($invitation['status'] === 'revoked') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invitation revoked']);
        exit;
    }

    // Verify token
    if (!password_verify($token, $invitation['token'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Invalid invitation token']);
        exit;
    }

    // Return invitation details (without sensitive data)
    echo json_encode([
        'success' => true,
        'invite' => [
            'id' => $invitation['id'],
            'email' => $invitation['email'],
            'role' => $invitation['role'],
            'full_name' => $invitation['full_name'],
            'extension' => $invitation['extension'],
            'message' => $invitation['message'],
            'created_by' => $invitation['created_by'],
            'expires_at' => date('Y-m-d H:i:s', $invitation['expires_at'])
        ]
    ]);
    exit;
}

/**
 * Accept invitation and create account
 */
if ($action === 'accept_invite' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    $token = $data['token'] ?? '';
    $invite_id = $data['invite_id'] ?? '';
    $username = $data['username'] ?? '';
    $password = $data['password'] ?? '';
    $full_name = $data['full_name'] ?? '';

    if (empty($token) || empty($invite_id) || empty($username) || empty($password)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        exit;
    }

    $invite_file = $invites_dir . '/' . $invite_id . '.json';

    if (!file_exists($invite_file)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Invitation not found']);
        exit;
    }

    $invitation = json_decode(file_get_contents($invite_file), true);

    // Verify invitation
    if ($invitation['expires_at'] < time()) {
        http_response_code(410);
        echo json_encode(['success' => false, 'error' => 'Invitation expired']);
        exit;
    }

    if ($invitation['status'] !== 'pending') {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Invitation already processed']);
        exit;
    }

    if (!password_verify($token, $invitation['token'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Invalid invitation token']);
        exit;
    }

    // Create account based on role
    if ($invitation['role'] === 'admin') {
        // Create admin account
        $admins_dir = '/home/flexpbxuser/admins';
        $admin_file = $admins_dir . '/admin_' . $username . '.json';

        if (file_exists($admin_file)) {
            http_response_code(409);
            echo json_encode(['success' => false, 'error' => 'Username already exists']);
            exit;
        }

        $admin_data = [
            'username' => $username,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'full_name' => $full_name ?: $invitation['full_name'],
            'email' => $invitation['email'],
            'role' => 'admin',
            'created_at' => date('Y-m-d H:i:s'),
            'created_via' => 'invitation',
            'invited_by' => $invitation['created_by'],
            'status' => 'active'
        ];

        file_put_contents($admin_file, json_encode($admin_data, JSON_PRETTY_PRINT));
        $account_file = $admin_file;

    } else {
        // Create user account
        $users_dir = '/home/flexpbxuser/users';

        // Determine extension
        $extension = $invitation['extension'];
        if (!$extension) {
            // Auto-assign next available extension
            $extension = findNextAvailableExtension($users_dir);
        }

        $user_file = $users_dir . '/user_' . $extension . '.json';

        if (file_exists($user_file)) {
            http_response_code(409);
            echo json_encode(['success' => false, 'error' => 'Extension already exists']);
            exit;
        }

        $user_data = [
            'extension' => $extension,
            'username' => $username,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'full_name' => $full_name ?: $invitation['full_name'],
            'email' => $invitation['email'],
            'role' => $invitation['role'],
            'created_at' => date('Y-m-d H:i:s'),
            'created_via' => 'invitation',
            'invited_by' => $invitation['created_by'],
            'status' => 'active',
            'voicemail_enabled' => true,
            'voicemail_pin' => rand(1000, 9999)
        ];

        file_put_contents($user_file, json_encode($user_data, JSON_PRETTY_PRINT));
        $account_file = $user_file;
    }

    // Mark invitation as accepted
    $invitation['status'] = 'accepted';
    $invitation['accepted_at'] = time();
    $invitation['accepted_by_ip'] = $_SERVER['REMOTE_ADDR'];
    file_put_contents($invite_file, json_encode($invitation, JSON_PRETTY_PRINT));

    echo json_encode([
        'success' => true,
        'message' => 'Account created successfully',
        'role' => $invitation['role'],
        'username' => $username,
        'extension' => $invitation['role'] === 'user' ? $extension : null
    ]);
    exit;
}

/**
 * List all invitations (admin only)
 */
if ($action === 'list_invites' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Admin access required']);
        exit;
    }

    $invite_files = glob($invites_dir . '/invite_*.json');
    $invitations = [];

    foreach ($invite_files as $file) {
        $invitation = json_decode(file_get_contents($file), true);

        // Don't include token hash
        unset($invitation['token']);

        $invitations[] = $invitation;
    }

    // Sort by created_at (newest first)
    usort($invitations, function($a, $b) {
        return $b['created_at'] - $a['created_at'];
    });

    echo json_encode([
        'success' => true,
        'invitations' => $invitations,
        'total' => count($invitations)
    ]);
    exit;
}

/**
 * Revoke invitation (admin only)
 */
if ($action === 'revoke_invite' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Admin access required']);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $invite_id = $data['invite_id'] ?? '';

    if (empty($invite_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing invite ID']);
        exit;
    }

    $invite_file = $invites_dir . '/' . $invite_id . '.json';

    if (!file_exists($invite_file)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Invitation not found']);
        exit;
    }

    $invitation = json_decode(file_get_contents($invite_file), true);
    $invitation['status'] = 'revoked';
    $invitation['revoked_at'] = time();
    $invitation['revoked_by'] = $_SESSION['admin_username'];

    file_put_contents($invite_file, json_encode($invitation, JSON_PRETTY_PRINT));

    echo json_encode([
        'success' => true,
        'message' => 'Invitation revoked'
    ]);
    exit;
}

/**
 * Find next available extension
 */
function findNextAvailableExtension($users_dir) {
    $start = 2000;
    $end = 2999;

    $existing_files = glob($users_dir . '/user_*.json');
    $used_extensions = [];

    foreach ($existing_files as $file) {
        if (preg_match('/user_(\d+)\.json$/', $file, $matches)) {
            $used_extensions[] = (int)$matches[1];
        }
    }

    for ($ext = $start; $ext <= $end; $ext++) {
        if (!in_array($ext, $used_extensions)) {
            return $ext;
        }
    }

    return null; // No available extensions
}

http_response_code(400);
echo json_encode(['success' => false, 'error' => 'Invalid action']);
