<?php
/**
 * FlexPBX Extension Availability Checker
 * Checks if an extension is available and suggests next available
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Get requested extension or auto-suggest
$requested = $_GET['extension'] ?? '';
$action = $_GET['action'] ?? 'check'; // check or suggest

// Get list of existing extensions from pjsip.conf
$pjsip_file = '/etc/asterisk/pjsip.conf';
$existing_extensions = [];

if (file_exists($pjsip_file)) {
    $content = file_get_contents($pjsip_file);
    // Match endpoint definitions like [2000]
    preg_match_all('/^\[(\d{4})\]$/m', $content, $matches);
    $existing_extensions = $matches[1];
}

// Also check pending signups
$signups_dir = '/home/flexpbxuser/signups';
$pending_extensions = [];

if (is_dir($signups_dir)) {
    $signup_files = glob($signups_dir . '/user_*.json');
    foreach ($signup_files as $file) {
        $data = json_decode(file_get_contents($file), true);
        if ($data && $data['status'] === 'pending') {
            $pending_extensions[] = $data['extension'];
        }
    }
}

// Combine all taken extensions
$taken_extensions = array_merge($existing_extensions, $pending_extensions);
$taken_extensions = array_unique($taken_extensions);

// Check if requested extension is available
if ($action === 'check' && !empty($requested)) {
    if (!preg_match('/^\d{4}$/', $requested)) {
        echo json_encode([
            'available' => false,
            'message' => 'Extension must be 4 digits',
            'extension' => $requested
        ]);
        exit;
    }

    $is_available = !in_array($requested, $taken_extensions);

    echo json_encode([
        'available' => $is_available,
        'message' => $is_available ? 'Extension is available' : 'Extension already taken',
        'extension' => $requested
    ]);
    exit;
}

// Suggest next available extension (sequential)
if ($action === 'suggest') {
    $start_range = 100; // Start at 100
    $end_range = 9999;

    for ($i = $start_range; $i <= $end_range; $i++) {
        $ext = (string)$i;
        if (!in_array($ext, $taken_extensions)) {
            echo json_encode([
                'available' => true,
                'suggested' => $ext,
                'message' => "Extension $ext is available",
                'type' => 'sequential'
            ]);
            exit;
        }
    }

    echo json_encode([
        'available' => false,
        'message' => 'No extensions available in range 100-9999'
    ]);
    exit;
}

// Random extension suggestion
if ($action === 'random') {
    $start_range = 100;
    $end_range = 9999;

    // Get all available extensions
    $available_extensions = [];
    for ($i = $start_range; $i <= $end_range; $i++) {
        $ext = (string)$i;
        if (!in_array($ext, $taken_extensions)) {
            $available_extensions[] = $ext;
        }
    }

    if (!empty($available_extensions)) {
        // Pick random from available
        $random_ext = $available_extensions[array_rand($available_extensions)];

        echo json_encode([
            'available' => true,
            'suggested' => $random_ext,
            'message' => "Random extension $random_ext is available",
            'type' => 'random',
            'total_available' => count($available_extensions)
        ]);
        exit;
    }

    echo json_encode([
        'available' => false,
        'message' => 'No extensions available in range 100-9999'
    ]);
    exit;
}

// List all taken extensions
echo json_encode([
    'taken' => $taken_extensions,
    'count' => count($taken_extensions),
    'available_range' => '2004-9999'
]);
