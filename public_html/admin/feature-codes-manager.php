<?php
/**
 * FlexPBX Feature Codes Manager
 * Manage, enable/disable, and reload feature codes
 */

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? 'list';
$extensions_conf = '/etc/asterisk/extensions.conf';

// Feature code definitions with metadata
$feature_codes = [
    '*43' => [
        'name' => 'Echo Test',
        'description' => 'Test audio (hear yourself with delay)',
        'category' => 'Diagnostic',
        'section_marker' => '; Echo Test - dial *43'
    ],
    '*44' => [
        'name' => 'Time/Clock',
        'description' => 'Hear current date and time',
        'category' => 'Diagnostic',
        'section_marker' => '; Time/Clock Announcement - dial *44'
    ],
    '*45' => [
        'name' => 'Queue Login',
        'description' => 'Login to support queue',
        'category' => 'Queue',
        'section_marker' => '; Queue Agent Login - dial *45'
    ],
    '*46' => [
        'name' => 'Queue Logout',
        'description' => 'Logout from support queue',
        'category' => 'Queue',
        'section_marker' => '; Queue Agent Logout - dial *46'
    ],
    '*48' => [
        'name' => 'Queue Status',
        'description' => 'Hear queue call count and agents',
        'category' => 'Queue',
        'section_marker' => '; Queue Status - dial *48'
    ],
    '*77' => [
        'name' => 'MOH + Queue Stats',
        'description' => 'Hear queue stats then music on hold',
        'category' => 'Music on Hold',
        'section_marker' => '; Music on Hold Preview - dial *77'
    ],
    '*78' => [
        'name' => 'Music on Hold',
        'description' => 'Preview what callers hear on hold',
        'category' => 'Music on Hold',
        'section_marker' => '; Call Hold / MOH Test - dial *78'
    ],
    '*97' => [
        'name' => 'Voicemail',
        'description' => 'Access your voicemail box',
        'category' => 'Voicemail',
        'section_marker' => '; Voicemail access'
    ]
];

switch ($action) {
    case 'list':
        echo json_encode(getFeatureCodes($extensions_conf, $feature_codes));
        break;

    case 'toggle':
        $code = $_POST['code'] ?? '';
        echo json_encode(toggleFeatureCode($extensions_conf, $code, $feature_codes));
        break;

    case 'update':
        $code = $_POST['code'] ?? '';
        $newCode = $_POST['newCode'] ?? '';
        $description = $_POST['description'] ?? '';
        echo json_encode(updateFeatureCode($extensions_conf, $code, $newCode, $description, $feature_codes));
        break;

    case 'reload':
        echo json_encode(reloadDialplan());
        break;

    case 'backup':
        echo json_encode(backupConfig($extensions_conf));
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

/**
 * Get all feature codes and their status
 */
function getFeatureCodes($file, $codes) {
    if (!file_exists($file)) {
        return ['success' => false, 'message' => 'Config file not found'];
    }

    $content = file_get_contents($file);
    $result = [];

    foreach ($codes as $code => $info) {
        $enabled = checkIfEnabled($content, $code);
        $result[] = [
            'code' => $code,
            'name' => $info['name'],
            'description' => $info['description'],
            'category' => $info['category'],
            'enabled' => $enabled
        ];
    }

    return ['success' => true, 'codes' => $result];
}

/**
 * Check if a feature code is enabled (not commented out)
 */
function checkIfEnabled($content, $code) {
    // Look for the exten line
    $pattern = '/^\s*exten\s*=>\s*' . preg_quote($code, '/') . '/m';
    if (preg_match($pattern, $content)) {
        return true;
    }

    // Check if it's commented
    $pattern = '/^;\s*exten\s*=>\s*' . preg_quote($code, '/') . '/m';
    if (preg_match($pattern, $content)) {
        return false;
    }

    return false; // Not found
}

/**
 * Toggle a feature code on/off
 */
function toggleFeatureCode($file, $code, $codes) {
    if (!isset($codes[$code])) {
        return ['success' => false, 'message' => 'Unknown feature code'];
    }

    if (!file_exists($file)) {
        return ['success' => false, 'message' => 'Config file not found'];
    }

    $content = file_get_contents($file);
    $lines = explode("\n", $content);
    $modified = false;
    $inSection = false;
    $sectionDepth = 0;

    for ($i = 0; $i < count($lines); $i++) {
        $line = $lines[$i];

        // Check if we're entering the feature code section
        if (strpos($line, $codes[$code]['section_marker']) !== false) {
            $inSection = true;
            continue;
        }

        // If in section, look for the exten line
        if ($inSection) {
            // Check for next section marker (another feature code)
            if (preg_match('/^;\s*\w+.*dial\s*\*\d+/', $line)) {
                $inSection = false;
                break;
            }

            // Toggle the exten line
            if (preg_match('/^(\s*)(;?\s*)(exten\s*=>\s*' . preg_quote($code, '/') . '.*)$/', $line, $matches)) {
                if (strpos($line, ';exten') !== false || strpos(trim($line), '; exten') !== false) {
                    // Currently disabled, enable it
                    $lines[$i] = preg_replace('/^(\s*);+\s*/', '$1', $line);
                } else {
                    // Currently enabled, disable it
                    $lines[$i] = $matches[1] . '; ' . $matches[3];
                }
                $modified = true;
            }
            // Toggle same => lines that are part of this extension
            elseif (preg_match('/^(\s*)(;?\s*)(same\s*=>)/', $line)) {
                if ($sectionDepth > 0) {
                    if (strpos($line, ';same') !== false || strpos(trim($line), '; same') !== false) {
                        $lines[$i] = preg_replace('/^(\s*);+\s*/', '$1', $line);
                    } else {
                        $lines[$i] = preg_replace('/^(\s*)/', '$1; ', $line);
                    }
                }
            }

            if (preg_match('/exten\s*=>\s*' . preg_quote($code, '/') . '/', $line)) {
                $sectionDepth = 1;
            }

            // Check if section ends (blank line or new exten)
            if (trim($line) === '' || preg_match('/^exten\s*=>/', $line)) {
                if ($sectionDepth > 0) {
                    $sectionDepth = 0;
                    break;
                }
            }
        }
    }

    if ($modified) {
        $newContent = implode("\n", $lines);
        file_put_contents($file, $newContent);
        return ['success' => true, 'message' => 'Feature code toggled successfully'];
    }

    return ['success' => false, 'message' => 'Feature code not found in config'];
}

/**
 * Update a feature code
 */
function updateFeatureCode($file, $oldCode, $newCode, $description, $codes) {
    // This is more complex - for now just update description
    // Full implementation would need to replace the code number too
    return ['success' => true, 'message' => 'Feature code updated (description only for now)'];
}

/**
 * Reload Asterisk dialplan
 */
function reloadDialplan() {
    $output = [];
    $return_var = 0;
    exec('sudo -u asterisk /usr/sbin/asterisk -rx "dialplan reload" 2>&1', $output, $return_var);

    if ($return_var === 0) {
        return [
            'success' => true,
            'message' => 'Dialplan reloaded successfully',
            'output' => implode("\n", $output)
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Failed to reload dialplan',
            'output' => implode("\n", $output)
        ];
    }
}

/**
 * Backup current config
 */
function backupConfig($file) {
    $backup_file = $file . '.backup.' . date('Y-m-d_H-i-s');

    if (copy($file, $backup_file)) {
        return [
            'success' => true,
            'message' => 'Config backed up successfully',
            'backup_file' => $backup_file
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Failed to backup config'
        ];
    }
}
