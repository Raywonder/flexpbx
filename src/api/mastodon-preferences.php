<?php
/**
 * FlexPBX Mastodon Preferences API
 * Manages user Mastodon notification preferences
 * GET: Retrieve preferences
 * POST: Save/update preferences
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle OPTIONS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Configuration
$preferences_file = '/home/tappedin/apps/mastodon-integrations/user_preferences.json';

// Ensure preferences file exists
if (!file_exists($preferences_file)) {
    file_put_contents($preferences_file, json_encode([], JSON_PRETTY_PRINT));
}

/**
 * GET - Retrieve preferences for an extension
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $extension = $_GET['extension'] ?? '';

    if (empty($extension)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Extension parameter required'
        ]);
        exit;
    }

    // Load preferences
    $preferences = loadPreferences();

    // Get extension preferences or return defaults
    if (isset($preferences[$extension])) {
        echo json_encode([
            'success' => true,
            'preferences' => $preferences[$extension],
            'timestamp' => date('c')
        ]);
    } else {
        // Return default preferences
        echo json_encode([
            'success' => true,
            'preferences' => [
                'extension' => $extension,
                'mastodon' => [
                    'enabled' => false,
                    'instance_type' => 'local',
                    'instance_url' => 'https://md.tappedin.fm',
                    'account_handle' => '',
                    'notifications' => ['mentions', 'voicemail'],
                    'post_visibility' => 'unlisted'
                ],
                'created_at' => date('c'),
                'last_updated' => date('c')
            ],
            'timestamp' => date('c')
        ]);
    }
    exit;
}

/**
 * POST - Save/update preferences
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid JSON input'
        ]);
        exit;
    }

    $extension = $data['extension'] ?? '';

    if (empty($extension)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Extension is required'
        ]);
        exit;
    }

    // Validate extension format (3-5 digits)
    if (!preg_match('/^\d{3,5}$/', $extension)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid extension format (must be 3-5 digits)'
        ]);
        exit;
    }

    // Validate mastodon configuration
    if (!isset($data['mastodon']) || !is_array($data['mastodon'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Mastodon configuration is required'
        ]);
        exit;
    }

    $mastodon = $data['mastodon'];

    // Validate required fields
    $required_fields = ['enabled', 'instance_type', 'instance_url', 'account_handle', 'notifications', 'post_visibility'];
    foreach ($required_fields as $field) {
        if (!isset($mastodon[$field])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => "Missing required field: $field"
            ]);
            exit;
        }
    }

    // Validate instance_type
    if (!in_array($mastodon['instance_type'], ['local', 'third-party'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid instance_type (must be "local" or "third-party")'
        ]);
        exit;
    }

    // Validate instance_url
    if (!filter_var($mastodon['instance_url'], FILTER_VALIDATE_URL)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid instance_url format'
        ]);
        exit;
    }

    // Validate account_handle
    if (!preg_match('/^@[\w]+(@[\w\.\-]+)?$/', $mastodon['account_handle'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid account_handle format (must be @username or @username@instance)'
        ]);
        exit;
    }

    // Validate notifications array
    if (!is_array($mastodon['notifications'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Notifications must be an array'
        ]);
        exit;
    }

    // Validate post_visibility
    $valid_visibility = ['public', 'unlisted', 'private', 'direct'];
    if (!in_array($mastodon['post_visibility'], $valid_visibility)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid post_visibility (must be public, unlisted, private, or direct)'
        ]);
        exit;
    }

    // Load existing preferences
    $preferences = loadPreferences();

    // Prepare preference data
    $preference_data = [
        'extension' => $extension,
        'mastodon' => [
            'enabled' => (bool) $mastodon['enabled'],
            'instance_type' => $mastodon['instance_type'],
            'instance_url' => rtrim($mastodon['instance_url'], '/'),
            'account_handle' => $mastodon['account_handle'],
            'notifications' => array_values(array_unique($mastodon['notifications'])),
            'post_visibility' => $mastodon['post_visibility']
        ],
        'last_updated' => date('c')
    ];

    // Add created_at if this is a new entry
    if (!isset($preferences[$extension])) {
        $preference_data['created_at'] = date('c');
    } else {
        // Preserve original created_at
        $preference_data['created_at'] = $preferences[$extension]['created_at'] ?? date('c');
    }

    // Update preferences
    $preferences[$extension] = $preference_data;

    // Save to file
    if (savePreferences($preferences)) {
        // Log the update
        logPreferenceUpdate($extension, $mastodon['enabled'], $mastodon['instance_type']);

        echo json_encode([
            'success' => true,
            'message' => 'Preferences saved successfully',
            'preferences' => $preference_data,
            'timestamp' => date('c')
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to save preferences'
        ]);
    }
    exit;
}

// Method not allowed
http_response_code(405);
echo json_encode([
    'success' => false,
    'message' => 'Method not allowed'
]);

// Helper functions

/**
 * Load preferences from JSON file
 */
function loadPreferences() {
    global $preferences_file;

    if (!file_exists($preferences_file)) {
        return [];
    }

    $content = file_get_contents($preferences_file);
    $preferences = json_decode($content, true);

    return is_array($preferences) ? $preferences : [];
}

/**
 * Save preferences to JSON file
 */
function savePreferences($preferences) {
    global $preferences_file;

    $json = json_encode($preferences, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    if ($json === false) {
        return false;
    }

    // Write to temporary file first
    $temp_file = $preferences_file . '.tmp';
    if (file_put_contents($temp_file, $json) === false) {
        return false;
    }

    // Atomic rename
    if (!rename($temp_file, $preferences_file)) {
        unlink($temp_file);
        return false;
    }

    // Set proper permissions
    chmod($preferences_file, 0644);

    return true;
}

/**
 * Log preference updates
 */
function logPreferenceUpdate($extension, $enabled, $instance_type) {
    $log_file = '/var/log/flexpbx-mastodon-preferences.log';
    $log_entry = sprintf(
        "[%s] Extension %s: Mastodon %s (%s instance) - IP: %s\n",
        date('Y-m-d H:i:s'),
        $extension,
        $enabled ? 'ENABLED' : 'DISABLED',
        $instance_type,
        $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    );

    @file_put_contents($log_file, $log_entry, FILE_APPEND);
}
?>
