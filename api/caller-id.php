<?php
// FlexPBX Caller ID Management API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$pjsipConfigFile = '/etc/asterisk/pjsip.conf';
$configDir = '/home/flexpbxuser/public_html/config/';

function respond($success, $message, $data = []) {
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ], $data));
    exit;
}

function executeAsteriskCommand($command) {
    $output = shell_exec("sudo asterisk -rx \"$command\" 2>&1");
    return $output;
}

// Get trunk caller ID configuration
function getTrunkCallerID($endpoint) {
    global $pjsipConfigFile;

    $content = file_get_contents($pjsipConfigFile);
    $lines = explode("\n", $content);

    $inEndpoint = false;
    $callerID = null;
    $trustInbound = false;
    $trustOutbound = false;

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === "[$endpoint]") {
            $inEndpoint = true;
            continue;
        }

        if ($inEndpoint && preg_match('/^\[/', $line)) {
            // Reached next section
            break;
        }

        if ($inEndpoint) {
            if (preg_match('/^callerid=(.+)/', $line, $matches)) {
                $callerID = $matches[1];
            } elseif (preg_match('/^;callerid=(.+)/', $line, $matches)) {
                $callerID = '(commented) ' . $matches[1];
            } elseif (preg_match('/^trust_id_inbound=(.+)/', $line, $matches)) {
                $trustInbound = strtolower(trim($matches[1])) === 'yes' || strtolower(trim($matches[1])) === 'true';
            } elseif (preg_match('/^trust_id_outbound=(.+)/', $line, $matches)) {
                $trustOutbound = strtolower(trim($matches[1])) === 'yes' || strtolower(trim($matches[1])) === 'true';
            }
        }
    }

    return [
        'endpoint' => $endpoint,
        'caller_id' => $callerID,
        'trust_id_inbound' => $trustInbound,
        'trust_id_outbound' => $trustOutbound,
        'passthrough_enabled' => $trustInbound && (strpos($callerID, '(commented)') !== false || $callerID === null)
    ];
}

// Set trunk caller ID passthrough
function setTrunkCallerIDPassthrough($endpoint, $enable, $outboundCallerID = null) {
    global $pjsipConfigFile;

    // Backup the file
    $backupFile = $pjsipConfigFile . '.backup-' . date('YmdHis');
    copy($pjsipConfigFile, $backupFile);

    $content = file_get_contents($pjsipConfigFile);
    $lines = explode("\n", $content);
    $newLines = [];

    $inEndpoint = false;
    $modified = false;

    foreach ($lines as $line) {
        $trimmedLine = trim($line);

        if ($trimmedLine === "[$endpoint]") {
            $inEndpoint = true;
            $newLines[] = $line;
            continue;
        }

        if ($inEndpoint && preg_match('/^\[/', $trimmedLine)) {
            // Reached next section
            $inEndpoint = false;
        }

        if ($inEndpoint) {
            // Handle callerid line
            if (preg_match('/^callerid=/', $trimmedLine) || preg_match('/^;callerid=/', $trimmedLine)) {
                if ($enable) {
                    // Comment out the line to enable passthrough
                    if (!preg_match('/^;/', $trimmedLine)) {
                        $newLines[] = '; Caller ID commented out to allow passthrough from trunk';
                        $newLines[] = ';' . $trimmedLine;
                        $modified = true;
                        continue;
                    }
                } else {
                    // Uncomment or set caller ID for outbound
                    if ($outboundCallerID) {
                        $newLines[] = "callerid=$outboundCallerID";
                        $modified = true;
                        continue;
                    } elseif (preg_match('/^;callerid=(.+)/', $trimmedLine, $matches)) {
                        $newLines[] = 'callerid=' . $matches[1];
                        $modified = true;
                        continue;
                    }
                }
            }

            // Handle trust_id_inbound
            if (preg_match('/^trust_id_inbound=/', $trimmedLine)) {
                if ($enable) {
                    $newLines[] = 'trust_id_inbound=yes';
                    $modified = true;
                    continue;
                }
            }
        }

        $newLines[] = $line;
    }

    if (!$modified) {
        return [
            'success' => false,
            'message' => "Endpoint [$endpoint] not found or no changes needed",
            'backup' => $backupFile
        ];
    }

    // Write the new content
    $newContent = implode("\n", $newLines);
    file_put_contents($pjsipConfigFile, $newContent);

    // Fix permissions
    chown($pjsipConfigFile, 'asterisk');
    chgrp($pjsipConfigFile, 'asterisk');
    chmod($pjsipConfigFile, 0640);

    // Reload PJSIP
    $reloadOutput = executeAsteriskCommand('pjsip reload');

    return [
        'success' => true,
        'message' => 'Caller ID configuration updated',
        'backup' => $backupFile,
        'reload_output' => $reloadOutput
    ];
}

// Get all trunk endpoints
function getTrunkEndpoints() {
    $output = executeAsteriskCommand('pjsip list endpoints');
    $endpoints = [];

    $lines = explode("\n", trim($output));
    foreach ($lines as $line) {
        $line = trim($line);
        if (preg_match('/^Endpoint:\s+(\S+)/', $line, $matches)) {
            $endpoint = trim($matches[1]);
            // Skip extension endpoints (numeric)
            if (!is_numeric($endpoint)) {
                $endpoints[] = $endpoint;
            }
        }
    }

    return $endpoints;
}

// Parse request
$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents('php://input'), true);

// Route the request
switch ($method) {
    case 'GET':
        $endpoint = isset($_GET['endpoint']) ? $_GET['endpoint'] : null;

        if ($endpoint) {
            // Get specific trunk caller ID config
            $config = getTrunkCallerID($endpoint);
            respond(true, 'Caller ID configuration retrieved', $config);
        } else {
            // List all trunk endpoints
            $endpoints = getTrunkEndpoints();
            $configs = [];
            foreach ($endpoints as $ep) {
                $configs[$ep] = getTrunkCallerID($ep);
            }
            respond(true, 'All trunk configurations retrieved', ['trunks' => $configs]);
        }
        break;

    case 'POST':
    case 'PUT':
        $endpoint = isset($data['endpoint']) ? $data['endpoint'] : null;
        $enable = isset($data['enable_passthrough']) ? (bool)$data['enable_passthrough'] : true;
        $outboundCallerID = isset($data['outbound_caller_id']) ? $data['outbound_caller_id'] : null;

        if (empty($endpoint)) {
            respond(false, 'Endpoint is required');
        }

        $result = setTrunkCallerIDPassthrough($endpoint, $enable, $outboundCallerID);
        respond($result['success'], $result['message'], $result);
        break;

    default:
        respond(false, 'Method not allowed');
}
