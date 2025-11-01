<?php
/**
 * FlexPBX User Status API
 * Real-time user status, SIP registration, call state, location
 * Created: October 17, 2025
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$path = $_GET['path'] ?? '';
$extension = $_GET['extension'] ?? null;

switch ($path) {
    case 'registration':
        handleRegistration($extension);
        break;
    
    case 'call_status':
        handleCallStatus($extension);
        break;
    
    case 'full_status':
        handleFullStatus($extension);
        break;
    
    case 'network_info':
        handleNetworkInfo();
        break;
    
    default:
        respond(false, 'Invalid path', null, 404);
        break;
}

/**
 * Get SIP registration status
 */
function handleRegistration($extension) {
    if (!$extension) {
        respond(false, 'Extension required');
        return;
    }

    exec("sudo -u asterisk /usr/sbin/asterisk -rx 'pjsip show endpoint $extension' 2>&1", $output);
    
    $status = [
        'extension' => $extension,
        'registered' => false,
        'contact' => null,
        'user_agent' => null,
        'ip_address' => null,
        'port' => null,
        'registration_time' => null
    ];

    foreach ($output as $line) {
        // Check if endpoint is available (registered)
        if (preg_match('/Status\s*:\s*(.+)/', $line, $matches)) {
            $endpointStatus = trim($matches[1]);
            $status['registered'] = (stripos($endpointStatus, 'Avail') !== false);
        }

        // Get contact URI (contains IP and port) and check for Avail status
        if (preg_match('/Contact\s*:\s*(.+)/', $line, $matches)) {
            $contact = trim($matches[1]);
            $status['contact'] = $contact;

            // Check if contact line contains "Avail" status
            if (stripos($contact, 'Avail') !== false) {
                $status['registered'] = true;
            }

            // Extract IP and port from contact
            if (preg_match('/sip:.+@([0-9.:]+):?([0-9]+)?/', $contact, $ipMatch)) {
                $status['ip_address'] = $ipMatch[1];
                $status['port'] = $ipMatch[2] ?? '5060';
            }
        }
    }

    // Get detailed contact info including User-Agent
    exec("sudo -u asterisk /usr/sbin/asterisk -rx 'pjsip show contact $extension' 2>&1", $contactOutput);
    
    foreach ($contactOutput as $line) {
        if (preg_match('/user_agent\s*:\s*(.+)/', $line, $matches)) {
            $status['user_agent'] = trim($matches[1]);
        }
        if (preg_match('/reg_expire\s*:\s*(.+)/', $line, $matches)) {
            $status['registration_time'] = trim($matches[1]);
        }
    }

    // Detect client type from User-Agent
    $status['client_name'] = detectSIPClient($status['user_agent']);
    $status['network_type'] = detectNetworkType($status['ip_address']);

    respond(true, 'Registration status retrieved', $status);
}

/**
 * Get call status for extension
 */
function handleCallStatus($extension) {
    if (!$extension) {
        respond(false, 'Extension required');
        return;
    }

    exec("sudo -u asterisk /usr/sbin/asterisk -rx 'core show channels concise' 2>&1", $output);
    
    $callStatus = [
        'extension' => $extension,
        'in_call' => false,
        'call_count' => 0,
        'active_calls' => []
    ];

    foreach ($output as $line) {
        if (stripos($line, "PJSIP/$extension") !== false) {
            $parts = explode('!', $line);
            
            $callStatus['in_call'] = true;
            $callStatus['call_count']++;
            
            $callStatus['active_calls'][] = [
                'channel' => $parts[0] ?? 'Unknown',
                'state' => $parts[5] ?? 'Unknown',
                'duration' => $parts[11] ?? '0',
                'caller_id' => $parts[7] ?? 'Unknown'
            ];
        }
    }

    respond(true, 'Call status retrieved', $callStatus);
}

/**
 * Get full status (registration + calls + network)
 */
function handleFullStatus($extension) {
    if (!$extension) {
        respond(false, 'Extension required');
        return;
    }

    // Get registration info
    exec("sudo -u asterisk /usr/sbin/asterisk -rx 'pjsip show endpoint $extension' 2>&1", $output);
    
    $status = [
        'extension' => $extension,
        'online' => false,
        'registered' => false,
        'in_call' => false,
        'client_info' => [
            'name' => 'Unknown',
            'user_agent' => null,
            'ip_address' => null,
            'port' => null
        ],
        'network' => [
            'type' => 'Unknown',
            'location' => 'Unknown'
        ],
        'call_info' => [
            'active_calls' => 0,
            'calls' => []
        ],
        'last_updated' => date('c')
    ];

    // Parse endpoint info
    foreach ($output as $line) {
        if (preg_match('/Status\s*:\s*(.+)/', $line, $matches)) {
            $endpointStatus = trim($matches[1]);
            $status['registered'] = (stripos($endpointStatus, 'Avail') !== false);
            $status['online'] = $status['registered'];
        }

        if (preg_match('/Contact\s*:\s*(.+)/', $line, $matches)) {
            $contact = trim($matches[1]);

            // Check if contact line contains "Avail" status
            if (stripos($contact, 'Avail') !== false) {
                $status['registered'] = true;
                $status['online'] = true;
            }

            if (preg_match('/sip:.+@([0-9.:]+):?([0-9]+)?/', $contact, $ipMatch)) {
                $status['client_info']['ip_address'] = $ipMatch[1];
                $status['client_info']['port'] = $ipMatch[2] ?? '5060';
            }
        }
    }

    // Get User-Agent
    exec("sudo -u asterisk /usr/sbin/asterisk -rx 'pjsip show contact $extension' 2>&1", $contactOutput);
    foreach ($contactOutput as $line) {
        if (preg_match('/user_agent\s*:\s*(.+)/', $line, $matches)) {
            $status['client_info']['user_agent'] = trim($matches[1]);
            $status['client_info']['name'] = detectSIPClient($status['client_info']['user_agent']);
        }
    }

    // Detect network type
    if ($status['client_info']['ip_address']) {
        $status['network']['type'] = detectNetworkType($status['client_info']['ip_address']);
        $status['network']['location'] = getIPLocation($status['client_info']['ip_address']);
    }

    // Get call status
    exec("sudo -u asterisk /usr/sbin/asterisk -rx 'core show channels concise' 2>&1", $channelOutput);
    foreach ($channelOutput as $line) {
        if (stripos($line, "PJSIP/$extension") !== false) {
            $status['in_call'] = true;
            $status['call_info']['active_calls']++;
            
            $parts = explode('!', $line);
            $status['call_info']['calls'][] = [
                'channel' => $parts[0] ?? 'Unknown',
                'state' => $parts[5] ?? 'Unknown',
                'duration' => $parts[11] ?? '0',
                'caller_id' => $parts[7] ?? 'Unknown'
            ];
        }
    }

    respond(true, 'Full status retrieved', $status);
}

/**
 * Get network information about current connection
 */
function handleNetworkInfo() {
    $clientIP = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    
    $info = [
        'ip_address' => $clientIP,
        'network_type' => detectNetworkType($clientIP),
        'location' => getIPLocation($clientIP),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
        'timestamp' => date('c')
    ];

    respond(true, 'Network info retrieved', $info);
}

/**
 * Detect SIP client from User-Agent
 */
function detectSIPClient($userAgent) {
    if (!$userAgent) return 'Unknown';

    $clients = [
        'Groundwire' => 'Groundwire',
        'Linphone' => 'Linphone',
        'Zoiper' => 'Zoiper',
        'Bria' => 'Bria',
        'MicroSIP' => 'MicroSIP',
        'Telephone' => 'Telephone',
        'CSipSimple' => 'CSipSimple',
        'Asterisk PBX' => 'Asterisk',
        'FPBX' => 'FreePBX',
        'SIP.js' => 'WebRTC (Browser)',
        'JsSIP' => 'WebRTC (Browser)'
    ];

    foreach ($clients as $pattern => $name) {
        if (stripos($userAgent, $pattern) !== false) {
            return $name;
        }
    }

    return $userAgent;
}

/**
 * Detect network type (Tailscale, WireGuard, Public)
 */
function detectNetworkType($ip) {
    if (!$ip) return 'Unknown';

    // Tailscale: 100.64.0.0/10
    if (preg_match('/^100\.(6[4-9]|[7-9]\d|1[0-1]\d|12[0-7])\./', $ip)) {
        return 'Tailscale';
    }

    // WireGuard: Common ranges (configurable)
    // Check common WireGuard ranges: 10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16
    if (preg_match('/^10\./', $ip)) {
        return 'WireGuard/VPN';
    }

    // Private IPs (likely local network or VPN)
    if (preg_match('/^(10|172\.1[6-9]|172\.2[0-9]|172\.3[0-1]|192\.168)\./', $ip)) {
        return 'Private Network';
    }

    // Localhost
    if ($ip === '127.0.0.1' || $ip === '::1') {
        return 'Localhost';
    }

    return 'Public Internet';
}

/**
 * Get location info from IP (basic implementation)
 */
function getIPLocation($ip) {
    if (!$ip || $ip === '127.0.0.1' || $ip === '::1') {
        return 'Local';
    }

    // For private IPs
    if (preg_match('/^(10|172\.1[6-9]|172\.2[0-9]|172\.3[0-1]|192\.168|100\.[6-9])\./', $ip)) {
        return 'Private Network';
    }

    // For public IPs, you could integrate with ip-api.com or similar
    // For now, just return the IP
    return "Public ($ip)";
}

function respond($success, $message, $data = null, $httpCode = 200) {
    http_response_code($httpCode);

    $response = [
        'success' => $success,
        'message' => $message,
        'timestamp' => date('c')
    ];

    if ($data !== null) {
        $response = array_merge($response, $data);
    }

    echo json_encode($response, JSON_PRETTY_PRINT);
    exit;
}
?>
