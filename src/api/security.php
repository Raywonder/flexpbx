<?php
/**
 * FlexPBX Security API
 * Manages fail2ban, IP bans, firewall rules, and security settings
 */

require_once 'config.php';
require_once 'auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle OPTIONS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Require authentication
$auth = checkAuth();
if (!$auth['authenticated']) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized', 'message' => 'Authentication required']);
    exit;
}

// Get request method and endpoint
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

// Route requests
switch ($path) {
    case 'banned-ips':
        handleBannedIPs($method);
        break;
    
    case 'unban':
        handleUnban($method);
        break;
    
    case 'whitelist':
        handleWhitelist($method);
        break;
    
    case 'fail2ban/status':
        handleFail2banStatus($method);
        break;
    
    case 'firewall/status':
        handleFirewallStatus($method);
        break;
    
    case 'check-ip':
        handleCheckIP($method);
        break;
    
    case 'security-log':
        handleSecurityLog($method);
        break;
    
    default:
        http_response_code(404);
        echo json_encode(['error' => 'Not Found', 'message' => 'API endpoint not found']);
        break;
}

/**
 * Get list of banned IPs
 */
function handleBannedIPs($method) {
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }
    
    $bannedIPs = [];
    
    // Get fail2ban banned IPs for Asterisk
    exec('sudo fail2ban-client status asterisk 2>/dev/null', $output, $ret);
    if ($ret === 0) {
        foreach ($output as $line) {
            if (strpos($line, 'Banned IP list:') !== false) {
                $ips = explode(':', $line)[1] ?? '';
                $ipArray = array_filter(array_map('trim', explode(' ', $ips)));
                foreach ($ipArray as $ip) {
                    $bannedIPs[] = [
                        'ip' => $ip,
                        'source' => 'fail2ban-asterisk',
                        'service' => 'SIP/Asterisk',
                        'can_unban' => true
                    ];
                }
            }
        }
    }
    
    // Get CSF banned IPs
    if (file_exists('/etc/csf/csf.deny')) {
        $csfDeny = file('/etc/csf/csf.deny', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($csfDeny as $line) {
            if (strpos($line, '#') === 0 || empty(trim($line))) continue;
            
            $parts = preg_split('/\s+#/', $line, 2);
            $ip = trim($parts[0]);
            $reason = isset($parts[1]) ? trim($parts[1]) : 'No reason provided';
            
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                $bannedIPs[] = [
                    'ip' => $ip,
                    'source' => 'csf',
                    'service' => 'Firewall',
                    'reason' => $reason,
                    'can_unban' => true
                ];
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'banned_ips' => $bannedIPs,
        'total' => count($bannedIPs),
        'timestamp' => date('c')
    ]);
}

/**
 * Unban an IP address
 */
function handleUnban($method) {
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $ip = $data['ip'] ?? '';
    $source = $data['source'] ?? 'all';
    
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid IP address']);
        return;
    }
    
    $results = [];
    
    // Unban from fail2ban
    if ($source === 'all' || $source === 'fail2ban') {
        exec("sudo fail2ban-client set asterisk unbanip $ip 2>&1", $output, $ret);
        $results['fail2ban'] = [
            'success' => $ret === 0,
            'output' => implode("\n", $output)
        ];
    }
    
    // Unban from CSF
    if ($source === 'all' || $source === 'csf') {
        exec("sudo csf -dr $ip 2>&1", $output2, $ret2);
        exec("sudo csf -tr $ip 2>&1", $output3, $ret3); // Also remove temporary blocks
        $results['csf'] = [
            'success' => $ret2 === 0 || $ret3 === 0,
            'output' => implode("\n", array_merge($output2, $output3))
        ];
    }
    
    // Log the unban action
    logSecurityAction('unban', $ip, $_SERVER['REMOTE_ADDR'], $source);
    
    echo json_encode([
        'success' => true,
        'message' => "IP $ip has been unbanned",
        'ip' => $ip,
        'results' => $results,
        'timestamp' => date('c')
    ]);
}

/**
 * Manage IP whitelist
 */
function handleWhitelist($method) {
    global $auth;
    
    if ($method === 'GET') {
        // Get whitelisted IPs
        $whitelist = [];
        
        if (file_exists('/etc/csf/csf.allow')) {
            $csfAllow = file('/etc/csf/csf.allow', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($csfAllow as $line) {
                if (strpos($line, '#') === 0 && strpos($line, '##') !== 0) continue;
                if (empty(trim($line))) continue;
                
                $parts = preg_split('/\s+#/', $line, 2);
                $ip = trim($parts[0]);
                $comment = isset($parts[1]) ? trim($parts[1]) : '';
                
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    $whitelist[] = [
                        'ip' => $ip,
                        'comment' => $comment,
                        'source' => 'csf'
                    ];
                }
            }
        }
        
        echo json_encode([
            'success' => true,
            'whitelist' => $whitelist,
            'total' => count($whitelist),
            'timestamp' => date('c')
        ]);
        
    } elseif ($method === 'POST') {
        // Add IP to whitelist
        $data = json_decode(file_get_contents('php://input'), true);
        $ip = $data['ip'] ?? '';
        $comment = $data['comment'] ?? 'Added via API';
        
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid IP address']);
            return;
        }
        
        exec("sudo csf -a $ip \"$comment\" 2>&1", $output, $ret);
        
        if ($ret === 0) {
            logSecurityAction('whitelist_add', $ip, $_SERVER['REMOTE_ADDR'], $comment);
            
            echo json_encode([
                'success' => true,
                'message' => "IP $ip added to whitelist",
                'ip' => $ip,
                'timestamp' => date('c')
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'error' => 'Failed to add IP to whitelist',
                'output' => implode("\n", $output)
            ]);
        }
        
    } elseif ($method === 'DELETE') {
        // Remove IP from whitelist
        $data = json_decode(file_get_contents('php://input'), true);
        $ip = $data['ip'] ?? '';
        
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid IP address']);
            return;
        }
        
        exec("sudo csf -ar $ip 2>&1", $output, $ret);
        
        if ($ret === 0) {
            logSecurityAction('whitelist_remove', $ip, $_SERVER['REMOTE_ADDR']);
            
            echo json_encode([
                'success' => true,
                'message' => "IP $ip removed from whitelist",
                'ip' => $ip,
                'timestamp' => date('c')
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'error' => 'Failed to remove IP from whitelist',
                'output' => implode("\n", $output)
            ]);
        }
    }
}

/**
 * Get fail2ban status
 */
function handleFail2banStatus($method) {
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }
    
    exec('sudo fail2ban-client status asterisk 2>&1', $output, $ret);
    
    $status = [
        'enabled' => $ret === 0,
        'currently_failed' => 0,
        'total_failed' => 0,
        'currently_banned' => 0,
        'total_banned' => 0,
        'log_file' => '/var/log/asterisk/messages'
    ];
    
    if ($ret === 0) {
        foreach ($output as $line) {
            if (preg_match('/Currently failed:\s+(\d+)/', $line, $matches)) {
                $status['currently_failed'] = (int)$matches[1];
            }
            if (preg_match('/Total failed:\s+(\d+)/', $line, $matches)) {
                $status['total_failed'] = (int)$matches[1];
            }
            if (preg_match('/Currently banned:\s+(\d+)/', $line, $matches)) {
                $status['currently_banned'] = (int)$matches[1];
            }
            if (preg_match('/Total banned:\s+(\d+)/', $line, $matches)) {
                $status['total_banned'] = (int)$matches[1];
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'fail2ban' => $status,
        'timestamp' => date('c')
    ]);
}

/**
 * Get firewall status
 */
function handleFirewallStatus($method) {
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }
    
    exec('sudo csf -l 2>&1 | grep -c "ACCEPT"', $allowRules);
    exec('sudo csf -l 2>&1 | grep -c "DROP"', $denyRules);
    exec('sudo systemctl is-active csf', $csfStatus);
    exec('sudo systemctl is-active lfd', $lfdStatus);
    
    $status = [
        'csf_enabled' => trim($csfStatus[0] ?? '') === 'active',
        'lfd_enabled' => trim($lfdStatus[0] ?? '') === 'active',
        'allow_rules' => (int)($allowRules[0] ?? 0),
        'deny_rules' => (int)($denyRules[0] ?? 0)
    ];
    
    echo json_encode([
        'success' => true,
        'firewall' => $status,
        'timestamp' => date('c')
    ]);
}

/**
 * Check if specific IP is banned/whitelisted
 */
function handleCheckIP($method) {
    if ($method !== 'POST' && $method !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }
    
    $ip = $_GET['ip'] ?? $_POST['ip'] ?? '';
    
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid IP address']);
        return;
    }
    
    $result = [
        'ip' => $ip,
        'banned' => false,
        'whitelisted' => false,
        'details' => []
    ];
    
    // Check fail2ban
    exec("sudo fail2ban-client status asterisk 2>&1 | grep -q '$ip'", $f2bOutput, $f2bRet);
    if ($f2bRet === 0) {
        $result['banned'] = true;
        $result['details'][] = [
            'source' => 'fail2ban',
            'service' => 'asterisk',
            'status' => 'banned'
        ];
    }
    
    // Check CSF deny
    exec("sudo csf -g $ip 2>&1 | grep -i 'csf.deny'", $csfDenyOutput, $csfDenyRet);
    if ($csfDenyRet === 0) {
        $result['banned'] = true;
        $result['details'][] = [
            'source' => 'csf',
            'service' => 'firewall',
            'status' => 'banned',
            'type' => 'permanent'
        ];
    }
    
    // Check CSF allow
    exec("sudo csf -g $ip 2>&1 | grep -i 'csf.allow'", $csfAllowOutput, $csfAllowRet);
    if ($csfAllowRet === 0) {
        $result['whitelisted'] = true;
        $result['details'][] = [
            'source' => 'csf',
            'service' => 'firewall',
            'status' => 'whitelisted'
        ];
    }
    
    echo json_encode([
        'success' => true,
        'result' => $result,
        'timestamp' => date('c')
    ]);
}

/**
 * Get security log
 */
function handleSecurityLog($method) {
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }
    
    $limit = (int)($_GET['limit'] ?? 100);
    $limit = min($limit, 1000); // Max 1000 entries
    
    $logFile = '/var/log/flexpbx-security.log';
    $entries = [];
    
    if (file_exists($logFile)) {
        $lines = array_slice(file($logFile, FILE_IGNORE_NEW_LINES), -$limit);
        foreach ($lines as $line) {
            $data = json_decode($line, true);
            if ($data) {
                $entries[] = $data;
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'entries' => $entries,
        'total' => count($entries),
        'timestamp' => date('c')
    ]);
}

/**
 * Log security action
 */
function logSecurityAction($action, $target_ip, $source_ip, $details = '') {
    $logFile = '/var/log/flexpbx-security.log';
    
    $entry = [
        'timestamp' => date('c'),
        'action' => $action,
        'target_ip' => $target_ip,
        'source_ip' => $source_ip,
        'user' => $_SESSION['username'] ?? 'unknown',
        'details' => $details
    ];
    
    file_put_contents($logFile, json_encode($entry) . "\n", FILE_APPEND);
}

// Helper function for auth check
function checkAuth() {
    session_start();
    return [
        'authenticated' => isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true,
        'username' => $_SESSION['username'] ?? null,
        'role' => $_SESSION['role'] ?? null
    ];
}
