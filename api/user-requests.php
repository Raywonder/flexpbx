<?php
/**
 * FlexPBX User Requests API
 * Manage profile updates and DID requests from users
 */

header('Content-Type: application/json');

// Require admin authentication (basic implementation)
session_start();
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    echo json_encode(['error' => 'Admin authentication required']);
    exit;
}

$action = $_GET['action'] ?? 'list';

// List pending profile updates
if ($action === 'list_profile_updates') {
    $updates_dir = '/home/flexpbxuser/profile_updates';
    $updates = [];

    if (is_dir($updates_dir)) {
        $files = glob($updates_dir . '/profile_*.json');
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if ($data && $data['status'] === 'pending') {
                $data['request_id'] = basename($file, '.json');
                $updates[] = $data;
            }
        }
    }

    echo json_encode([
        'success' => true,
        'count' => count($updates),
        'updates' => $updates
    ]);
    exit;
}

// List pending DID requests
if ($action === 'list_did_requests') {
    $did_dir = '/home/flexpbxuser/did_requests';
    $requests = [];

    if (is_dir($did_dir)) {
        $files = glob($did_dir . '/did_*.json');
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if ($data && $data['status'] === 'pending') {
                $data['request_id'] = basename($file, '.json');
                $requests[] = $data;
            }
        }
    }

    echo json_encode([
        'success' => true,
        'count' => count($requests),
        'requests' => $requests
    ]);
    exit;
}

// Approve profile update
if ($action === 'approve_profile_update') {
    $request_id = $_POST['request_id'] ?? '';
    $updates_dir = '/home/flexpbxuser/profile_updates';
    $file = $updates_dir . '/' . $request_id . '.json';

    if (!file_exists($file)) {
        http_response_code(404);
        echo json_encode(['error' => 'Request not found']);
        exit;
    }

    $data = json_decode(file_get_contents($file), true);

    // Update pjsip.conf with new display name
    $pjsip_file = '/etc/asterisk/pjsip.conf';
    if (file_exists($pjsip_file)) {
        $content = file_get_contents($pjsip_file);
        $extension = $data['extension'];
        $new_display = $data['display_name'];

        // Update callerid in endpoint section
        $pattern = '/(\[' . preg_quote($extension) . '\][^\[]*callerid=")[^"]+(")/s';
        $replacement = '${1}' . $new_display . ' <' . $extension . '>${2}';
        $content = preg_replace($pattern, $replacement, $content);

        file_put_contents($pjsip_file, $content);
        exec('asterisk -rx "pjsip reload"');
    }

    // Update voicemail.conf with new email
    $voicemail_file = '/etc/asterisk/voicemail.conf';
    if (file_exists($voicemail_file) && !empty($data['email'])) {
        $vm_content = file_get_contents($voicemail_file);
        $pattern = '/^(' . preg_quote($extension) . '\s*=>[^,]+,)([^,]+,)([^\n]+)/m';
        $replacement = '${1}' . $new_display . ',${2}' . $data['email'];
        $vm_content = preg_replace($pattern, $replacement, $vm_content);

        file_put_contents($voicemail_file, $vm_content);
        exec('asterisk -rx "voicemail reload"');
    }

    // Mark as approved
    $data['status'] = 'approved';
    $data['approved_at'] = date('Y-m-d H:i:s');
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));

    echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
    exit;
}

// Deny request
if ($action === 'deny_request') {
    $request_id = $_POST['request_id'] ?? '';
    $type = $_POST['type'] ?? 'profile'; // profile or did
    $reason = $_POST['reason'] ?? 'No reason provided';

    $dir = ($type === 'did') ? '/home/flexpbxuser/did_requests' : '/home/flexpbxuser/profile_updates';
    $file = $dir . '/' . $request_id . '.json';

    if (!file_exists($file)) {
        http_response_code(404);
        echo json_encode(['error' => 'Request not found']);
        exit;
    }

    $data = json_decode(file_get_contents($file), true);
    $data['status'] = 'denied';
    $data['denied_at'] = date('Y-m-d H:i:s');
    $data['denial_reason'] = $reason;
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));

    echo json_encode(['success' => true, 'message' => 'Request denied']);
    exit;
}

// Default: list all pending requests
echo json_encode([
    'endpoints' => [
        'list_profile_updates' => 'GET /api/user-requests.php?action=list_profile_updates',
        'list_did_requests' => 'GET /api/user-requests.php?action=list_did_requests',
        'approve_profile_update' => 'POST /api/user-requests.php?action=approve_profile_update&request_id=...',
        'deny_request' => 'POST /api/user-requests.php?action=deny_request&request_id=...&type=...&reason=...'
    ]
]);
