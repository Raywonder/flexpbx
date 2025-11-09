<?php
/**
 * FlexPBX Dial Plan API
 * Provides dial plan rules for SIP client auto-configuration
 * Created: October 16, 2025
 * API Pattern: Query parameter format (?path=[action])
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$path = $_GET['path'] ?? 'rules';
$format = $_GET['format'] ?? 'json'; // json, groundwire, linphone, zoiper

switch ($path) {
    case '':
    case 'rules':
        handleDialRules($format);
        break;

    case 'patterns':
        handleDialPatterns();
        break;

    case 'emergency':
        handleEmergencyNumbers();
        break;

    case 'feature_codes':
        handleFeatureCodes();
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Invalid path']);
        break;
}

/**
 * Get dial rules in various formats
 */
function handleDialRules($format) {
    $rules = [
        'extensions' => [
            'pattern' => '2xxx',
            'description' => '4-digit extensions (2000-2999)',
            'regex' => '^2[0-9]{3}$',
            'min_length' => 4,
            'max_length' => 4
        ],
        'feature_codes' => [
            'pattern' => '*xx',
            'description' => 'Feature codes (*97 voicemail, *45 queue login, etc.)',
            'regex' => '^\*[0-9]{2}$',
            'min_length' => 3,
            'max_length' => 3,
            'codes' => [
                '*97' => 'Voicemail Access',
                '*45' => 'Queue Agent Login',
                '*46' => 'Queue Agent Logout',
                '*65' => 'Call Recording On',
                '*66' => 'Call Recording Off'
            ]
        ],
        'us_canada' => [
            'pattern' => '1[2-9]xxxxxxxxx',
            'description' => 'US/Canada 11-digit numbers (1-NPA-NXX-XXXX)',
            'regex' => '^1[2-9][0-9]{2}[2-9][0-9]{6}$',
            'min_length' => 11,
            'max_length' => 11
        ],
        'international' => [
            'pattern' => '011xxxxxxxxxxx',
            'description' => 'International calls (011 + country code + number)',
            'regex' => '^011[0-9]{7,15}$',
            'min_length' => 10,
            'max_length' => 18
        ]
    ];

    // Format for different SIP clients
    switch ($format) {
        case 'groundwire':
            // Groundwire dial plan format
            echo json_encode([
                'success' => true,
                'dial_plan' => '(2xxx|*xx|1[2-9]xxxxxxxxx|011xxxxxxxxxxx)',
                'rules' => $rules,
                'format' => 'groundwire'
            ]);
            break;

        case 'linphone':
            // Linphone dial plan format
            $linphone_rules = [];
            foreach ($rules as $key => $rule) {
                $linphone_rules[] = [
                    'pattern' => $rule['regex'],
                    'description' => $rule['description']
                ];
            }
            echo json_encode([
                'success' => true,
                'dial_rules' => $linphone_rules,
                'format' => 'linphone'
            ]);
            break;

        case 'zoiper':
            // Zoiper format
            echo json_encode([
                'success' => true,
                'dial_plan' => [
                    'timeout' => 3,
                    'rules' => array_map(function($rule) {
                        return [
                            'pattern' => $rule['pattern'],
                            'min' => $rule['min_length'],
                            'max' => $rule['max_length']
                        ];
                    }, $rules)
                ],
                'format' => 'zoiper'
            ]);
            break;

        default: // json
            echo json_encode([
                'success' => true,
                'dial_rules' => $rules,
                'combined_pattern' => '(2xxx|*xx|1[2-9]xxxxxxxxx|011xxxxxxxxxxx)',
                'inter_digit_timeout' => 3,
                'timestamp' => date('c')
            ]);
            break;
    }
}

/**
 * Get detailed dial patterns
 */
function handleDialPatterns() {
    echo json_encode([
        'success' => true,
        'patterns' => [
            'local_extensions' => [
                'range' => '2000-2999',
                'pattern' => '2xxx',
                'length' => 4,
                'type' => 'extension',
                'examples' => ['2000', '2001', '2006']
            ],
            'feature_codes' => [
                'range' => '*00-*99',
                'pattern' => '*xx',
                'length' => 3,
                'type' => 'feature',
                'examples' => ['*97', '*45', '*46']
            ],
            'us_local' => [
                'pattern' => '[2-9]xxxxxx',
                'length' => 7,
                'type' => 'outbound',
                'description' => '7-digit local (requires area code prefix)'
            ],
            'us_long_distance' => [
                'pattern' => '1[2-9]xxxxxxxxx',
                'length' => 11,
                'type' => 'outbound',
                'description' => 'US/Canada long distance'
            ],
            'international' => [
                'pattern' => '011xxxxxxxxxxx',
                'length' => '10-18',
                'type' => 'outbound',
                'description' => 'International dialing'
            ]
        ],
        'timestamp' => date('c')
    ]);
}

/**
 * Get emergency numbers
 */
function handleEmergencyNumbers() {
    echo json_encode([
        'success' => true,
        'emergency_numbers' => [
            '911' => [
                'description' => 'Emergency Services (US/Canada)',
                'type' => 'emergency',
                'enabled' => false,
                'note' => 'Currently blocked for safety - configure E911 first'
            ],
            '112' => [
                'description' => 'International Emergency',
                'type' => 'emergency',
                'enabled' => false
            ]
        ],
        'warning' => 'Emergency calling requires proper E911 configuration and address verification',
        'timestamp' => date('c')
    ]);
}

/**
 * Get feature codes
 */
function handleFeatureCodes() {
    echo json_encode([
        'success' => true,
        'feature_codes' => [
            'voicemail' => [
                'code' => '*97',
                'description' => 'Check your voicemail',
                'enabled' => true
            ],
            'queue_login' => [
                'code' => '*45',
                'description' => 'Login to call queue as agent',
                'enabled' => true
            ],
            'queue_logout' => [
                'code' => '*46',
                'description' => 'Logout from call queue',
                'enabled' => true
            ],
            'call_recording_on' => [
                'code' => '*65',
                'description' => 'Enable call recording',
                'enabled' => false
            ],
            'call_recording_off' => [
                'code' => '*66',
                'description' => 'Disable call recording',
                'enabled' => false
            ],
            'do_not_disturb' => [
                'code' => '*78',
                'description' => 'Enable Do Not Disturb',
                'enabled' => false
            ],
            'do_not_disturb_off' => [
                'code' => '*79',
                'description' => 'Disable Do Not Disturb',
                'enabled' => false
            ],
            'call_forward' => [
                'code' => '*72',
                'description' => 'Enable call forwarding',
                'enabled' => false
            ],
            'call_forward_off' => [
                'code' => '*73',
                'description' => 'Disable call forwarding',
                'enabled' => false
            ]
        ],
        'timestamp' => date('c')
    ]);
}
?>
