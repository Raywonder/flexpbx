<?php
/**
 * FlexPBX Feature Codes Management API
 * Enable/disable/reset feature codes
 * Created: October 17, 2025
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$config_file = '/home/flexpbxuser/feature_codes.json';
$path = $_GET['path'] ?? '';

switch ($path) {
    case 'list':
        handleList();
        break;

    case 'toggle':
        handleToggle();
        break;

    case 'reset':
        handleReset();
        break;

    case 'apply':
        handleApply();
        break;

    default:
        respond(false, 'Invalid path', null, 404);
        break;
}

/**
 * List all feature codes
 */
function handleList() {
    global $config_file;

    if (!file_exists($config_file)) {
        respond(false, 'Configuration file not found', null, 404);
        return;
    }

    $config = json_decode(file_get_contents($config_file), true);

    if (!$config) {
        respond(false, 'Failed to parse configuration', null, 500);
        return;
    }

    respond(true, 'Feature codes retrieved', $config);
}

/**
 * Toggle feature code enabled/disabled
 */
function handleToggle() {
    global $config_file;

    $code = $_POST['code'] ?? $_GET['code'] ?? null;

    if (!$code) {
        respond(false, 'Feature code required');
        return;
    }

    if (!file_exists($config_file)) {
        respond(false, 'Configuration file not found', null, 404);
        return;
    }

    $config = json_decode(file_get_contents($config_file), true);

    if (!isset($config['feature_codes'][$code])) {
        respond(false, 'Feature code not found', null, 404);
        return;
    }

    // Toggle enabled status
    $config['feature_codes'][$code]['enabled'] = !$config['feature_codes'][$code]['enabled'];

    // Save configuration
    if (file_put_contents($config_file, json_encode($config, JSON_PRETTY_PRINT))) {
        respond(true, 'Feature code toggled', [
            'code' => $code,
            'enabled' => $config['feature_codes'][$code]['enabled']
        ]);
    } else {
        respond(false, 'Failed to save configuration', null, 500);
    }
}

/**
 * Reset feature code to default
 */
function handleReset() {
    global $config_file;

    $code = $_POST['code'] ?? $_GET['code'] ?? null;

    if (!$code) {
        respond(false, 'Feature code required');
        return;
    }

    if (!file_exists($config_file)) {
        respond(false, 'Configuration file not found', null, 404);
        return;
    }

    $config = json_decode(file_get_contents($config_file), true);

    if (!isset($config['feature_codes'][$code])) {
        respond(false, 'Feature code not found', null, 404);
        return;
    }

    // Feature code is already at default in JSON, just need to regenerate extensions.conf
    respond(true, 'Feature code reset to default', [
        'code' => $code,
        'name' => $config['feature_codes'][$code]['name']
    ]);
}

/**
 * Apply configuration - regenerate extensions.conf feature codes section
 */
function handleApply() {
    global $config_file;

    if (!file_exists($config_file)) {
        respond(false, 'Configuration file not found', null, 404);
        return;
    }

    $config = json_decode(file_get_contents($config_file), true);

    // Generate feature codes section
    $feature_section = "\n; ==================================================\n";
    $feature_section .= "; FEATURE CODES - Managed by FlexPBX Admin\n";
    $feature_section .= "; Last Updated: " . date('Y-m-d H:i:s') . "\n";
    $feature_section .= "; ==================================================\n\n";

    foreach ($config['feature_codes'] as $code => $details) {
        if ($details['enabled']) {
            $feature_section .= "; " . $details['name'] . " - " . $details['description'] . "\n";
            $feature_section .= $details['default_config'] . "\n\n";
        } else {
            $feature_section .= "; DISABLED: " . $details['name'] . " (" . $code . ")\n";
            $feature_section .= "; " . $details['description'] . "\n\n";
        }
    }

    // Read current extensions.conf
    $extensions_file = '/etc/asterisk/extensions.conf';
    $extensions_content = file_get_contents($extensions_file);

    // Find and replace feature codes section
    $start_marker = '; ==================================================';
    $end_marker = '; Outbound Calling';

    $start_pos = strpos($extensions_content, $start_marker);
    if ($start_pos === false) {
        // No marker found, append at end of flexpbx-internal context
        respond(false, 'Feature codes section marker not found', null, 500);
        return;
    }

    $end_pos = strpos($extensions_content, $end_marker, $start_pos);
    if ($end_pos === false) {
        respond(false, 'End marker not found', null, 500);
        return;
    }

    // Replace section
    $new_content = substr($extensions_content, 0, $start_pos) .
                   $feature_section .
                   substr($extensions_content, $end_pos);

    // Backup current file
    copy($extensions_file, $extensions_file . '.backup-' . date('Ymd-His'));

    // Write new configuration
    if (file_put_contents($extensions_file, $new_content)) {
        // Reload dialplan
        exec("sudo -u asterisk /usr/sbin/asterisk -rx 'dialplan reload' 2>&1", $output, $return_code);

        respond(true, 'Feature codes applied and dialplan reloaded', [
            'reload_output' => implode("\n", $output),
            'return_code' => $return_code
        ]);
    } else {
        respond(false, 'Failed to write extensions.conf', null, 500);
    }
}

function respond($success, $message, $data = null, $httpCode = 200) {
    http_response_code($httpCode);

    $response = [
        'success' => $success,
        'message' => $message,
        'timestamp' => date('c')
    ];

    if ($data !== null) {
        if (is_array($data)) {
            $response = array_merge($response, $data);
        } else {
            $response['data'] = $data;
        }
    }

    echo json_encode($response, JSON_PRETTY_PRINT);
    exit;
}
?>
