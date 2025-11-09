<?php
/**
 * FlexPBX AI Training Data Source Manager
 * Granular control over what data AI models can access and train on
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/config.php';

session_start();
$is_admin = ($_SESSION['admin_logged_in'] ?? false);
$api_key = $_GET['api_key'] ?? $_POST['api_key'] ?? '';

if (!$is_admin && $api_key !== $config['api_key']) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Admin authentication required']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'get_sources';

try {
    switch ($action) {
        case 'get_sources':
            getDataSources();
            break;
        case 'update_source':
            updateDataSource();
            break;
        case 'get_training_status':
            getTrainingStatus();
            break;
        case 'export_training_data':
            exportTrainingData();
            break;
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function getDataSources() {
    $sources = [
        'system' => [
            'name' => 'System Configuration',
            'sources' => [
                'extensions' => [
                    'name' => 'Extension Configurations',
                    'description' => 'SIP/PJSIP extension settings, credentials, and configurations',
                    'enabled' => true,
                    'privacy_level' => 'high',
                    'data_types' => ['config', 'settings'],
                    'estimated_size' => '< 1MB'
                ],
                'trunks' => [
                    'name' => 'Trunk Configurations',
                    'description' => 'Outbound trunk settings and provider information',
                    'enabled' => true,
                    'privacy_level' => 'high',
                    'data_types' => ['config', 'provider_info'],
                    'estimated_size' => '< 1MB'
                ],
                'dialplan' => [
                    'name' => 'Dialplan Rules',
                    'description' => 'Call routing logic and patterns',
                    'enabled' => true,
                    'privacy_level' => 'medium',
                    'data_types' => ['routing', 'patterns'],
                    'estimated_size' => '< 5MB'
                ],
                'system_settings' => [
                    'name' => 'System Settings',
                    'description' => 'General FlexPBX configuration and preferences',
                    'enabled' => true,
                    'privacy_level' => 'low',
                    'data_types' => ['config'],
                    'estimated_size' => '< 1MB'
                ]
            ]
        ],
        'logs' => [
            'name' => 'System Logs',
            'sources' => [
                'asterisk_logs' => [
                    'name' => 'Asterisk PBX Logs',
                    'description' => 'Call logs, debug info, and system events',
                    'enabled' => true,
                    'privacy_level' => 'medium',
                    'data_types' => ['events', 'errors', 'debug'],
                    'estimated_size' => '1-10GB depending on retention'
                ],
                'coturn_logs' => [
                    'name' => 'STUN/TURN Server Logs',
                    'description' => 'WebRTC connection logs and NAT traversal data',
                    'enabled' => true,
                    'privacy_level' => 'medium',
                    'data_types' => ['connections', 'webrtc'],
                    'estimated_size' => '1-20GB depending on usage'
                ],
                'cdr' => [
                    'name' => 'Call Detail Records',
                    'description' => 'Detailed call information (numbers, duration, status)',
                    'enabled' => false,
                    'privacy_level' => 'very_high',
                    'data_types' => ['call_records', 'billing'],
                    'estimated_size' => '< 100MB'
                ]
            ]
        ],
        'voice' => [
            'name' => 'Voice & Audio',
            'sources' => [
                'recordings' => [
                    'name' => 'Call Recordings',
                    'description' => 'Recorded calls and voicemails',
                    'enabled' => false,
                    'privacy_level' => 'very_high',
                    'data_types' => ['audio', 'voice'],
                    'estimated_size' => 'Varies greatly'
                ],
                'ivr_prompts' => [
                    'name' => 'IVR Audio Prompts',
                    'description' => 'Menu prompts and announcements',
                    'enabled' => true,
                    'privacy_level' => 'low',
                    'data_types' => ['audio', 'prompts'],
                    'estimated_size' => '< 500MB'
                ],
                'music_on_hold' => [
                    'name' => 'Music on Hold',
                    'description' => 'On-hold audio files',
                    'enabled' => false,
                    'privacy_level' => 'low',
                    'data_types' => ['audio'],
                    'estimated_size' => '< 1GB'
                ]
            ]
        ],
        'user_data' => [
            'name' => 'User Data',
            'sources' => [
                'user_profiles' => [
                    'name' => 'User Profiles',
                    'description' => 'User account information and preferences',
                    'enabled' => false,
                    'privacy_level' => 'very_high',
                    'data_types' => ['personal_info', 'preferences'],
                    'estimated_size' => '< 10MB'
                ],
                'user_activity' => [
                    'name' => 'User Activity Logs',
                    'description' => 'Login history and system usage patterns',
                    'enabled' => true,
                    'privacy_level' => 'medium',
                    'data_types' => ['activity', 'patterns'],
                    'estimated_size' => '< 50MB'
                ]
            ]
        ],
        'communications' => [
            'name' => 'Communications',
            'sources' => [
                'sms_messages' => [
                    'name' => 'SMS Messages',
                    'description' => 'Text message content and metadata',
                    'enabled' => false,
                    'privacy_level' => 'very_high',
                    'data_types' => ['messages', 'content'],
                    'estimated_size' => '< 100MB'
                ],
                'faxes' => [
                    'name' => 'Fax Documents',
                    'description' => 'Sent and received fax images',
                    'enabled' => false,
                    'privacy_level' => 'very_high',
                    'data_types' => ['documents', 'images'],
                    'estimated_size' => 'Varies'
                ]
            ]
        ],
        'performance' => [
            'name' => 'Performance Metrics',
            'sources' => [
                'system_metrics' => [
                    'name' => 'System Performance',
                    'description' => 'CPU, memory, disk usage statistics',
                    'enabled' => true,
                    'privacy_level' => 'low',
                    'data_types' => ['metrics', 'stats'],
                    'estimated_size' => '< 100MB'
                ],
                'call_quality' => [
                    'name' => 'Call Quality Metrics',
                    'description' => 'Jitter, latency, packet loss statistics',
                    'enabled' => true,
                    'privacy_level' => 'low',
                    'data_types' => ['metrics', 'quality'],
                    'estimated_size' => '< 50MB'
                ]
            ]
        ]
    ];

    // Load saved preferences
    $config_file = '/home/flexpbxuser/config/ai-training-sources.json';
    if (file_exists($config_file)) {
        $saved_config = json_decode(file_get_contents($config_file), true);
        // Merge saved settings with defaults
        foreach ($saved_config as $category => $category_sources) {
            if (isset($sources[$category])) {
                foreach ($category_sources['sources'] as $source_key => $source_settings) {
                    if (isset($sources[$category]['sources'][$source_key])) {
                        $sources[$category]['sources'][$source_key]['enabled'] = $source_settings['enabled'] ?? false;
                    }
                }
            }
        }
    }

    echo json_encode([
        'success' => true,
        'data' => $sources,
        'privacy_notice' => 'All data is processed locally on your system. You are responsible for compliance with privacy laws and your terms of service.',
        'config_file' => $config_file
    ]);
}

function updateDataSource() {
    $category = $_POST['category'] ?? '';
    $source = $_POST['source'] ?? '';
    $enabled = filter_var($_POST['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);

    if (empty($category) || empty($source)) {
        throw new Exception('Category and source required');
    }

    $config_file = '/home/flexpbxuser/config/ai-training-sources.json';
    $config = [];

    if (file_exists($config_file)) {
        $config = json_decode(file_get_contents($config_file), true) ?: [];
    }

    if (!isset($config[$category])) {
        $config[$category] = ['sources' => []];
    }

    $config[$category]['sources'][$source] = [
        'enabled' => $enabled,
        'updated_at' => date('c'),
        'updated_by' => $_SESSION['admin_username'] ?? 'system'
    ];

    $config_dir = dirname($config_file);
    if (!is_dir($config_dir)) {
        mkdir($config_dir, 0755, true);
    }

    file_put_contents($config_file, json_encode($config, JSON_PRETTY_PRINT));

    echo json_encode([
        'success' => true,
        'message' => 'Data source updated',
        'config' => $config
    ]);
}

function getTrainingStatus() {
    $config_file = '/home/flexpbxuser/config/ai-training-sources.json';
    $sources_config = [];

    if (file_exists($config_file)) {
        $sources_config = json_decode(file_get_contents($config_file), true);
    }

    $enabled_count = 0;
    $total_count = 0;

    foreach ($sources_config as $category) {
        if (isset($category['sources'])) {
            foreach ($category['sources'] as $source) {
                $total_count++;
                if ($source['enabled'] ?? false) {
                    $enabled_count++;
                }
            }
        }
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'enabled_sources' => $enabled_count,
            'total_sources' => $total_count,
            'last_training' => 'Never', // Placeholder for actual training tracking
            'training_in_progress' => false
        ]
    ]);
}

function exportTrainingData() {
    // This would export enabled data sources for AI training
    // Implementation depends on specific AI framework being used
    echo json_encode([
        'success' => true,
        'message' => 'Training data export functionality - to be implemented based on AI framework'
    ]);
}
