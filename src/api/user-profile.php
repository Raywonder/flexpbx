<?php
/**
 * FlexPBX User Profile API
 * Provides user profile information for external integrations (HubNode, mobile apps, etc.)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Get requested extension or current user
$extension = $_GET['extension'] ?? $_SESSION['user_extension'] ?? '';
$action = $_GET['action'] ?? 'get_profile';

if (empty($extension)) {
    http_response_code(400);
    echo json_encode(['error' => 'Extension parameter required']);
    exit;
}

// Validate extension format
if (!preg_match('/^\d{4}$/', $extension)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid extension format (must be 4 digits)']);
    exit;
}

// Get profile data
function getProfile($extension) {
    $profile = [
        'extension' => $extension,
        'display_name' => null,
        'email' => null,
        'assigned_dids' => [],
        'registration_status' => 'offline',
        'has_voicemail' => false,
        'voicemail_count' => 0
    ];

    // Get display name from pjsip.conf
    $pjsip_file = '/etc/asterisk/pjsip.conf';
    if (file_exists($pjsip_file)) {
        $content = file_get_contents($pjsip_file);
        $pattern = '/\[' . preg_quote($extension) . '\][^\[]*callerid="([^"]+)"/s';
        if (preg_match($pattern, $content, $matches)) {
            $profile['display_name'] = $matches[1];
        }
    }

    // Get email from voicemail.conf
    $voicemail_file = '/etc/asterisk/voicemail.conf';
    if (file_exists($voicemail_file)) {
        $vm_content = file_get_contents($voicemail_file);
        $pattern = '/^' . preg_quote($extension) . '\s*=>[^,]+,([^,]+),([^\n]+)/m';
        if (preg_match($pattern, $vm_content, $matches)) {
            $profile['email'] = trim($matches[2]);
            $profile['has_voicemail'] = true;
        }
    }

    // Get assigned DIDs from extensions.conf
    $extensions_file = '/etc/asterisk/extensions.conf';
    if (file_exists($extensions_file)) {
        $ext_content = file_get_contents($extensions_file);
        $pattern = '/exten\s*=>\s*(\d{10,}),.*Dial\(PJSIP\/' . preg_quote($extension) . '[,\)]/m';
        if (preg_match_all($pattern, $ext_content, $matches)) {
            $profile['assigned_dids'] = $matches[1];
        }
    }

    // Get registration status from Asterisk
    $cmd = "asterisk -rx 'pjsip show endpoint " . escapeshellarg($extension) . "'";
    $output = shell_exec($cmd);
    if (strpos($output, 'Avail') !== false || strpos($output, 'InUse') !== false) {
        $profile['registration_status'] = 'online';
    }

    // Get voicemail count
    $vm_dir = "/var/spool/asterisk/voicemail/flexpbx/$extension/INBOX";
    if (is_dir($vm_dir)) {
        $profile['voicemail_count'] = count(glob($vm_dir . '/msg*.txt'));
    }

    return $profile;
}

// Get full profile
if ($action === 'get_profile') {
    $profile = getProfile($extension);
    echo json_encode([
        'success' => true,
        'profile' => $profile
    ]);
    exit;
}

// Get only DIDs
if ($action === 'get_dids') {
    $profile = getProfile($extension);
    echo json_encode([
        'success' => true,
        'extension' => $extension,
        'dids' => $profile['assigned_dids'],
        'count' => count($profile['assigned_dids'])
    ]);
    exit;
}

// Get registration status
if ($action === 'get_status') {
    $profile = getProfile($extension);
    echo json_encode([
        'success' => true,
        'extension' => $extension,
        'status' => $profile['registration_status'],
        'has_voicemail' => $profile['has_voicemail'],
        'voicemail_count' => $profile['voicemail_count']
    ]);
    exit;
}

// List all active extensions
if ($action === 'list_extensions') {
    $pjsip_file = '/etc/asterisk/pjsip.conf';
    $extensions = [];

    if (file_exists($pjsip_file)) {
        $content = file_get_contents($pjsip_file);
        // Match endpoint definitions like [2000]
        preg_match_all('/^\[(\d{4})\]$/m', $content, $matches);

        foreach ($matches[1] as $ext) {
            $extensions[] = getProfile($ext);
        }
    }

    echo json_encode([
        'success' => true,
        'count' => count($extensions),
        'extensions' => $extensions
    ]);
    exit;
}

// Default: show available endpoints
echo json_encode([
    'endpoints' => [
        'get_profile' => 'GET /api/user-profile.php?extension=2000&action=get_profile',
        'get_dids' => 'GET /api/user-profile.php?extension=2000&action=get_dids',
        'get_status' => 'GET /api/user-profile.php?extension=2000&action=get_status',
        'list_extensions' => 'GET /api/user-profile.php?action=list_extensions'
    ]
]);
