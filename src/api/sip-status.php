<?php
/**
 * FlexPBX - SIP Registration Status API
 * Checks real-time SIP registration status via Asterisk CLI
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Require config helper
require_once(__DIR__ . '/flexpbx-config-helper.php');

$config = FlexPBXConfig::getInstance();

// Get extension to check
$extension = $_GET['extension'] ?? $_GET['identifier'] ?? '';

if (empty($extension)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Extension required']);
    exit;
}

/**
 * Check PJSIP endpoint registration status
 */
function checkPJSIPStatus($extension, $config) {
    // Get PJSIP endpoint status
    $result = $config->execAsteriskCommand("pjsip show endpoint $extension");

    if (!$result['success']) {
        return [
            'registered' => false,
            'status' => 'unknown',
            'error' => 'Failed to query Asterisk'
        ];
    }

    $output = $result['output'];

    // Parse output
    $status = [
        'registered' => false,
        'status' => 'offline',
        'contacts' => [],
        'device_state' => 'unavailable',
        'last_registration' => null
    ];

    // Check for registration
    if (preg_match('/Contacts:\s+(.+)/i', $output, $matches)) {
        $contacts_line = $matches[1];
        if (!empty(trim($contacts_line)) && !preg_match('/Not in use|0 of/', $contacts_line)) {
            $status['registered'] = true;
            $status['status'] = 'online';
        }
    }

    // Get more detailed contact info
    $contacts_result = $config->execAsteriskCommand("pjsip show contacts $extension");
    if ($contacts_result['success']) {
        $contacts_output = $contacts_result['output'];

        // Parse contacts
        $lines = explode("\n", $contacts_output);
        foreach ($lines as $line) {
            if (preg_match('/sip:(.+?)\s+\w+\s+(\d+\.\d+)/', $line, $matches)) {
                $status['contacts'][] = [
                    'uri' => $matches[1],
                    'rtt' => $matches[2] . 'ms'
                ];
            }
        }
    }

    // Get device state
    $device_result = $config->execAsteriskCommand("core show hint $extension");
    if ($device_result['success']) {
        if (preg_match('/State:\s*(\w+)/i', $device_result['output'], $matches)) {
            $state = strtolower($matches[1]);
            $status['device_state'] = $state;

            if (in_array($state, ['inuse', 'ringing'])) {
                $status['call_status'] = $state;
            }
        }
    }

    return $status;
}

/**
 * Get all registrations for an extension (including multiple devices)
 */
function getAllRegistrations($extension, $config) {
    $result = $config->execAsteriskCommand("pjsip show contacts");

    if (!$result['success']) {
        return [];
    }

    $registrations = [];
    $lines = explode("\n", $result['output']);

    foreach ($lines as $line) {
        if (strpos($line, "/$extension/") !== false || strpos($line, "$extension/") !== false) {
            if (preg_match('/(\S+\/\S+)\s+sip:(.+?)\s+(\w+)\s+(\d+\.\d+)/', $line, $matches)) {
                $registrations[] = [
                    'contact' => $matches[1],
                    'uri' => $matches[2],
                    'status' => strtolower($matches[3]),
                    'rtt' => $matches[4] . 'ms'
                ];
            }
        }
    }

    return $registrations;
}

// Check status
$status = checkPJSIPStatus($extension, $config);
$all_registrations = getAllRegistrations($extension, $config);

// Enrich status with all registrations
if (count($all_registrations) > 0) {
    $status['registered'] = true;
    $status['status'] = 'online';
    $status['devices'] = $all_registrations;
    $status['device_count'] = count($all_registrations);
}

// Get active channels
$channels_result = $config->execAsteriskCommand("core show channels");
if ($channels_result['success']) {
    $channel_count = 0;
    if (preg_match('/(\d+)\s+active\s+channel/i', $channels_result['output'], $matches)) {
        $channel_count = (int)$matches[1];
    }
    $status['active_channels'] = $channel_count;
}

// Check for active calls on this extension
$calls_result = $config->execAsteriskCommand("core show channels concise");
if ($calls_result['success']) {
    $active_calls = [];
    $lines = explode("\n", $calls_result['output']);

    foreach ($lines as $line) {
        if (strpos($line, "PJSIP/$extension") !== false) {
            $parts = explode('!', $line);
            if (count($parts) >= 4) {
                $active_calls[] = [
                    'channel' => $parts[0],
                    'context' => $parts[1],
                    'extension' => $parts[2],
                    'state' => $parts[4] ?? 'unknown'
                ];
            }
        }
    }

    if (count($active_calls) > 0) {
        $status['on_call'] = true;
        $status['active_calls'] = $active_calls;
        $status['call_count'] = count($active_calls);
    }
}

// Format response
$response = [
    'success' => true,
    'extension' => $extension,
    'status' => $status['status'],
    'registered' => $status['registered'],
    'device_state' => $status['device_state'],
    'on_call' => $status['on_call'] ?? false,
    'call_count' => $status['call_count'] ?? 0,
    'device_count' => $status['device_count'] ?? 0,
    'devices' => $status['devices'] ?? [],
    'last_checked' => date('Y-m-d H:i:s'),
    'timestamp' => time()
];

// Cache status for 5 seconds to prevent hammering Asterisk
$cache_dir = '/home/flexpbxuser/cache/sip_status';
if (!file_exists($cache_dir)) {
    mkdir($cache_dir, 0750, true);
}

$cache_file = $cache_dir . '/ext_' . $extension . '.json';
file_put_contents($cache_file, json_encode($response, JSON_PRETTY_PRINT));
chmod($cache_file, 0640);

http_response_code(200);
echo json_encode($response, JSON_PRETTY_PRINT);
