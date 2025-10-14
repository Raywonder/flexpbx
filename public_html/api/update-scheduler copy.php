<?php
// FlexPBX Update Scheduler API
// Version: 1.0.0 - Server-side update management and scheduling

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Update-Key');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Configuration
$configFile = '/home/flexpbxuser/public_html/api/update-config.json';
$clientsFile = '/home/flexpbxuser/public_html/api/connected-clients.json';
$updatesDir = '/home/flexpbxuser/public_html/downloads/';
$changelogFile = '/home/flexpbxuser/public_html/downloads/CHANGELOG.md';

// Default update configuration
$defaultConfig = [
    'server_version' => '2.0.0',
    'minimum_client_version' => '1.9.0',
    'current_client_version' => '2.0.0',
    'update_policy' => 'ask', // ask, auto, manual, scheduled
    'scheduled_update_time' => '02:00', // 24-hour format
    'scheduled_update_days' => ['sunday'], // days of week
    'compatibility_matrix' => [
        '2.0.0' => ['1.9.0', '1.9.5', '2.0.0'], // server version -> compatible client versions
        '1.9.0' => ['1.8.0', '1.9.0']
    ],
    'update_channels' => [
        'stable' => '2.0.0',
        'beta' => '2.1.0-beta.1',
        'dev' => '2.2.0-dev.1'
    ],
    'app_types' => [
        'desktop-admin' => [
            'current_version' => '2.0.0',
            'minimum_version' => '1.9.0',
            'download_path' => 'desktop-apps/',
            'auto_update_enabled' => true
        ],
        'flexphone' => [
            'current_version' => '1.5.0',
            'minimum_version' => '1.4.0',
            'download_path' => 'mobile-apps/',
            'auto_update_enabled' => true
        ],
        'public-desktop' => [
            'current_version' => '1.0.0',
            'minimum_version' => '0.9.0',
            'download_path' => 'public-apps/',
            'auto_update_enabled' => false
        ]
    ],
    'forced_update_threshold' => 5, // major version difference
    'notification_settings' => [
        'advance_notice_days' => 7,
        'reminder_intervals' => [24, 12, 2, 1], // hours before update
        'emergency_update_immediate' => true
    ],
    'rollback_enabled' => true,
    'backup_before_update' => true,
    'last_update_check' => null,
    'last_scheduled_update' => null,
    'changelog_enabled' => true,
    'maintenance_windows' => [
        'preferred_start' => '02:00',
        'preferred_end' => '06:00',
        'allowed_days' => ['sunday', 'saturday']
    ]
];

// Get current configuration
function getUpdateConfig() {
    global $configFile, $defaultConfig;

    if (file_exists($configFile)) {
        $config = json_decode(file_get_contents($configFile), true);
        return array_merge($defaultConfig, $config);
    }

    return $defaultConfig;
}

// Save configuration
function saveUpdateConfig($config) {
    global $configFile;
    return file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT));
}

// Get connected clients
function getConnectedClients() {
    global $clientsFile;

    if (file_exists($clientsFile)) {
        return json_decode(file_get_contents($clientsFile), true) ?: [];
    }

    return [];
}

// Save connected clients
function saveConnectedClients($clients) {
    global $clientsFile;
    return file_put_contents($clientsFile, json_encode($clients, JSON_PRETTY_PRINT));
}

// Check if update is compatible
function isVersionCompatible($serverVersion, $clientVersion, $compatibilityMatrix) {
    if (!isset($compatibilityMatrix[$serverVersion])) {
        return false;
    }

    return in_array($clientVersion, $compatibilityMatrix[$serverVersion]);
}

// Calculate update urgency
function calculateUpdateUrgency($currentVersion, $latestVersion) {
    $current = explode('.', $currentVersion);
    $latest = explode('.', $latestVersion);

    $majorDiff = intval($latest[0]) - intval($current[0]);
    $minorDiff = intval($latest[1] ?? 0) - intval($current[1] ?? 0);

    if ($majorDiff >= 2) return 'critical';
    if ($majorDiff >= 1) return 'important';
    if ($minorDiff >= 3) return 'recommended';

    return 'optional';
}

// Parse markdown changelog to structured data
function parseChangelogMarkdown($markdown, $targetVersion = null) {
    $releases = [];
    $lines = explode("\n", $markdown);
    $currentRelease = null;

    foreach ($lines as $line) {
        $line = trim($line);

        // Match version headers like "## [2.0.0] - 2025-10-13"
        if (preg_match('/^##\s+\[?([^\]]+)\]?\s*-?\s*(.*)/', $line, $matches)) {
            if ($currentRelease) {
                $releases[] = $currentRelease;
            }

            $version = $matches[1];
            $date = trim($matches[2]);

            $currentRelease = [
                'version' => $version,
                'date' => $date,
                'features' => [],
                'fixes' => [],
                'changes' => [],
                'breaking' => []
            ];

            // If target version specified, only include that version and newer
            if ($targetVersion && version_compare($version, $targetVersion, '<')) {
                break;
            }
        }
        // Match feature bullets
        elseif (preg_match('/^[\*\-]\s*\*\*(?:Added?|New|Feature)\*\*:?\s*(.+)/', $line, $matches)) {
            if ($currentRelease) {
                $currentRelease['features'][] = trim($matches[1]);
            }
        }
        // Match fix bullets
        elseif (preg_match('/^[\*\-]\s*\*\*(?:Fixed?|Bug\s*Fix)\*\*:?\s*(.+)/', $line, $matches)) {
            if ($currentRelease) {
                $currentRelease['fixes'][] = trim($matches[1]);
            }
        }
        // Match breaking changes
        elseif (preg_match('/^[\*\-]\s*\*\*(?:Breaking|BREAKING)\*\*:?\s*(.+)/', $line, $matches)) {
            if ($currentRelease) {
                $currentRelease['breaking'][] = trim($matches[1]);
            }
        }
        // Match other changes
        elseif (preg_match('/^[\*\-]\s*\*\*(?:Changed?|Update)\*\*:?\s*(.+)/', $line, $matches)) {
            if ($currentRelease) {
                $currentRelease['changes'][] = trim($matches[1]);
            }
        }
        // Match generic bullets
        elseif (preg_match('/^[\*\-]\s+(.+)/', $line, $matches)) {
            if ($currentRelease) {
                $currentRelease['changes'][] = trim($matches[1]);
            }
        }
    }

    if ($currentRelease) {
        $releases[] = $currentRelease;
    }

    return $releases;
}

// Generate cron job for scheduled updates
function generateCronJob($time, $days) {
    $hour = substr($time, 0, 2);
    $minute = substr($time, 3, 2);

    $dayNumbers = [
        'sunday' => 0, 'monday' => 1, 'tuesday' => 2, 'wednesday' => 3,
        'thursday' => 4, 'friday' => 5, 'saturday' => 6
    ];

    $cronDays = [];
    foreach ($days as $day) {
        if (isset($dayNumbers[$day])) {
            $cronDays[] = $dayNumbers[$day];
        }
    }

    $cronExpression = "$minute $hour * * " . implode(',', $cronDays);

    return [
        'expression' => $cronExpression,
        'command' => "curl -s https://{$_SERVER['HTTP_HOST']}/api/update-scheduler.php?action=trigger_scheduled_update",
        'description' => "FlexPBX scheduled update check"
    ];
}

// Process API actions
$action = $_GET['action'] ?? $_POST['action'] ?? 'status';

try {
    switch ($action) {
        case 'status':
            $config = getUpdateConfig();
            $clients = getConnectedClients();

            echo json_encode([
                'success' => true,
                'server_version' => $config['server_version'],
                'current_client_version' => $config['current_client_version'],
                'minimum_client_version' => $config['minimum_client_version'],
                'update_policy' => $config['update_policy'],
                'scheduled_update_time' => $config['scheduled_update_time'],
                'connected_clients' => count($clients),
                'clients_needing_update' => array_filter($clients, function($client) use ($config) {
                    return version_compare($client['version'], $config['current_client_version'], '<');
                }),
                'last_update_check' => $config['last_update_check'],
                'next_scheduled_update' => $config['last_scheduled_update']
            ]);
            break;

        case 'get_config':
            echo json_encode([
                'success' => true,
                'config' => getUpdateConfig()
            ]);
            break;

        case 'update_config':
            $newConfig = json_decode(file_get_contents('php://input'), true);
            if (!$newConfig) {
                throw new Exception('Invalid configuration data');
            }

            $currentConfig = getUpdateConfig();
            $updatedConfig = array_merge($currentConfig, $newConfig);

            if (saveUpdateConfig($updatedConfig)) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Configuration updated successfully',
                    'config' => $updatedConfig
                ]);
            } else {
                throw new Exception('Failed to save configuration');
            }
            break;

        case 'check_client_updates':
            $clientVersion = $_GET['client_version'] ?? $_POST['client_version'] ?? '';
            $clientId = $_GET['client_id'] ?? $_POST['client_id'] ?? uniqid('client_');
            $appType = $_GET['app_type'] ?? $_POST['app_type'] ?? 'desktop-admin';

            if (empty($clientVersion)) {
                throw new Exception('Client version required');
            }

            $config = getUpdateConfig();
            $clients = getConnectedClients();

            // Update client registry
            $clients[$clientId] = [
                'version' => $clientVersion,
                'app_type' => $appType,
                'last_seen' => date('c'),
                'ip' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
            ];
            saveConnectedClients($clients);

            // Get app-specific version info
            $appConfig = $config['app_types'][$appType] ?? $config['app_types']['desktop-admin'];
            $latestVersion = $appConfig['current_version'];
            $minimumVersion = $appConfig['minimum_version'];

            $isCompatible = isVersionCompatible($config['server_version'], $clientVersion, $config['compatibility_matrix']);
            $updateAvailable = version_compare($clientVersion, $latestVersion, '<');
            $urgency = calculateUpdateUrgency($clientVersion, $latestVersion);

            echo json_encode([
                'success' => true,
                'client_version' => $clientVersion,
                'latest_version' => $latestVersion,
                'server_version' => $config['server_version'],
                'app_type' => $appType,
                'update_available' => $updateAvailable,
                'update_required' => !$isCompatible,
                'update_urgency' => $urgency,
                'update_policy' => $config['update_policy'],
                'download_url' => "https://{$_SERVER['HTTP_HOST']}/downloads/{$appConfig['download_path']}",
                'compatibility_status' => $isCompatible ? 'compatible' : 'incompatible',
                'scheduled_update_time' => $config['scheduled_update_time'],
                'advance_notice_days' => $config['notification_settings']['advance_notice_days'],
                'can_defer_update' => $urgency !== 'critical',
                'auto_update_enabled' => $appConfig['auto_update_enabled'],
                'changelog_url' => "https://{$_SERVER['HTTP_HOST']}/downloads/CHANGELOG.md",
                'message' => $updateAvailable
                    ? "Update available: v{$latestVersion} (Urgency: $urgency)"
                    : "You are running the latest version"
            ]);
            break;

        case 'get_changelog':
            $version = $_GET['version'] ?? $_POST['version'] ?? '';
            $format = $_GET['format'] ?? $_POST['format'] ?? 'json';

            global $changelogFile;

            if (!file_exists($changelogFile)) {
                throw new Exception('Changelog not available');
            }

            $changelog = file_get_contents($changelogFile);

            if ($format === 'json') {
                // Parse markdown changelog to JSON format
                $releases = parseChangelogMarkdown($changelog, $version);
                echo json_encode([
                    'success' => true,
                    'version' => $version,
                    'releases' => $releases,
                    'full_changelog_url' => "https://{$_SERVER['HTTP_HOST']}/downloads/CHANGELOG.md"
                ]);
            } else {
                // Return raw markdown
                header('Content-Type: text/markdown');
                echo $changelog;
            }
            break;

        case 'schedule_update':
            $time = $_POST['time'] ?? '02:00';
            $days = $_POST['days'] ?? ['sunday'];
            $policy = $_POST['policy'] ?? 'ask';

            $config = getUpdateConfig();
            $config['scheduled_update_time'] = $time;
            $config['scheduled_update_days'] = $days;
            $config['update_policy'] = $policy;

            if (saveUpdateConfig($config)) {
                $cronJob = generateCronJob($time, $days);

                echo json_encode([
                    'success' => true,
                    'message' => 'Update schedule configured',
                    'scheduled_time' => $time,
                    'scheduled_days' => $days,
                    'update_policy' => $policy,
                    'cron_expression' => $cronJob['expression'],
                    'cron_command' => $cronJob['command']
                ]);
            } else {
                throw new Exception('Failed to save schedule');
            }
            break;

        case 'trigger_scheduled_update':
            $config = getUpdateConfig();
            $clients = getConnectedClients();

            $outdatedClients = array_filter($clients, function($client) use ($config) {
                return version_compare($client['version'], $config['current_client_version'], '<');
            });

            $config['last_scheduled_update'] = date('c');
            $config['last_update_check'] = date('c');
            saveUpdateConfig($config);

            echo json_encode([
                'success' => true,
                'message' => 'Scheduled update check completed',
                'clients_notified' => count($outdatedClients),
                'outdated_clients' => array_keys($outdatedClients),
                'timestamp' => date('c')
            ]);
            break;

        case 'get_cron_setup':
            $config = getUpdateConfig();
            $cronJob = generateCronJob($config['scheduled_update_time'], $config['scheduled_update_days']);

            echo json_encode([
                'success' => true,
                'cron_expression' => $cronJob['expression'],
                'cron_command' => $cronJob['command'],
                'installation_instructions' => [
                    'Add to crontab with: crontab -e',
                    'Add line: ' . $cronJob['expression'] . ' ' . $cronJob['command'],
                    'Save and exit',
                    'Verify with: crontab -l'
                ],
                'example_crontab_entry' => $cronJob['expression'] . ' ' . $cronJob['command'] . ' # ' . $cronJob['description']
            ]);
            break;

        default:
            throw new Exception('Unknown action: ' . $action);
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'action' => $action
    ]);
}
?>