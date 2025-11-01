<?php
/**
 * FlexPBX 2FA Voice Call Authentication API
 * Voice-based two-factor authentication via PBX callback
 */

session_start();
header('Content-Type: application/json');

$config_file = '/home/flexpbxuser/config/2fa_settings.json';
$codes_file = '/home/flexpbxuser/config/2fa_codes.json';

// Ensure config directory exists
@mkdir(dirname($config_file), 0755, true);

// Initialize configuration
if (!file_exists($config_file)) {
    $default_config = [
        'enabled' => true,
        'code_length' => 6,
        'code_expiry' => 300, // 5 minutes
        'max_attempts' => 3,
        'call_timeout' => 30,
        'prompts' => [
            'welcome' => '/var/lib/asterisk/sounds/custom/2fa-welcome.wav',
            'enter_code' => '/var/lib/asterisk/sounds/custom/2fa-enter-code.wav',
            'invalid_code' => '/var/lib/asterisk/sounds/custom/2fa-invalid.wav',
            'code_accepted' => '/var/lib/asterisk/sounds/custom/2fa-accepted.wav',
            'code_expired' => '/var/lib/asterisk/sounds/custom/2fa-expired.wav'
        ]
    ];
    file_put_contents($config_file, json_encode($default_config, JSON_PRETTY_PRINT));
}

// Initialize codes storage
if (!file_exists($codes_file)) {
    file_put_contents($codes_file, json_encode([], JSON_PRETTY_PRINT));
}

// Load config and codes
function loadConfig() {
    global $config_file;
    return json_decode(file_get_contents($config_file), true);
}

function loadCodes() {
    global $codes_file;
    return json_decode(file_get_contents($codes_file), true) ?: [];
}

function saveCodes($codes) {
    global $codes_file;
    file_put_contents($codes_file, json_encode($codes, JSON_PRETTY_PRINT));
}

// Get action from request
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'generate':
        generate2FACode();
        break;

    case 'verify':
        verify2FACode();
        break;

    case 'initiate_call':
        initiateVoiceCall();
        break;

    case 'check_status':
        checkVerificationStatus();
        break;

    case 'get_config':
        getConfig();
        break;

    case 'update_config':
        updateConfig();
        break;

    case 'upload_prompt':
        uploadPrompt();
        break;

    case 'list_prompts':
        listPrompts();
        break;

    case 'clean_expired':
        cleanExpiredCodes();
        break;

    default:
        echo json_encode([
            'success' => false,
            'error' => 'Invalid action',
            'available_actions' => [
                'generate', 'verify', 'initiate_call', 'check_status',
                'get_config', 'update_config', 'upload_prompt', 'list_prompts'
            ]
        ]);
}

/**
 * Generate a new 2FA code for a user
 */
function generate2FACode() {
    $extension = $_POST['extension'] ?? '';
    $phone_number = $_POST['phone_number'] ?? '';

    if (empty($extension)) {
        echo json_encode(['success' => false, 'error' => 'Extension required']);
        return;
    }

    $config = loadConfig();
    $codes = loadCodes();

    // Generate random code
    $code_length = $config['code_length'] ?? 6;
    $code = str_pad(rand(0, pow(10, $code_length) - 1), $code_length, '0', STR_PAD_LEFT);

    // Store code with metadata
    $code_id = uniqid('2fa_', true);
    $codes[$code_id] = [
        'extension' => $extension,
        'phone_number' => $phone_number,
        'code' => $code,
        'generated_at' => time(),
        'expires_at' => time() + ($config['code_expiry'] ?? 300),
        'verified' => false,
        'attempts' => 0,
        'max_attempts' => $config['max_attempts'] ?? 3,
        'call_initiated' => false,
        'call_status' => 'pending'
    ];

    saveCodes($codes);

    echo json_encode([
        'success' => true,
        'code_id' => $code_id,
        'code' => $code, // Only show code for testing, remove in production
        'expires_in' => $config['code_expiry'],
        'message' => 'Code generated successfully'
    ]);
}

/**
 * Initiate voice call for 2FA verification
 */
function initiateVoiceCall() {
    $code_id = $_POST['code_id'] ?? '';

    if (empty($code_id)) {
        echo json_encode(['success' => false, 'error' => 'Code ID required']);
        return;
    }

    $codes = loadCodes();

    if (!isset($codes[$code_id])) {
        echo json_encode(['success' => false, 'error' => 'Invalid code ID']);
        return;
    }

    $code_data = $codes[$code_id];

    // Check if expired
    if (time() > $code_data['expires_at']) {
        echo json_encode(['success' => false, 'error' => 'Code expired']);
        return;
    }

    // Get phone number (use extension if no phone provided)
    $phone_number = $code_data['phone_number'] ?: $code_data['extension'];

    // Create call file for Asterisk
    $call_file_content = <<<EOT
Channel: Local/{$phone_number}@from-internal
Context: 2fa-verification
Extension: s
Priority: 1
Set: CODE_ID={$code_id}
Set: EXPECTED_CODE={$code_data['code']}
Set: EXTENSION={$code_data['extension']}
MaxRetries: 0
RetryTime: 60
WaitTime: 30
EOT;

    $call_file = '/tmp/2fa_' . $code_id . '.call';
    file_put_contents($call_file, $call_file_content);

    // Move to Asterisk outgoing directory
    $outgoing_dir = '/var/spool/asterisk/outgoing/';
    if (file_exists($outgoing_dir)) {
        exec("sudo mv {$call_file} {$outgoing_dir}");

        // Update code data
        $codes[$code_id]['call_initiated'] = true;
        $codes[$code_id]['call_initiated_at'] = time();
        $codes[$code_id]['call_status'] = 'calling';
        saveCodes($codes);

        echo json_encode([
            'success' => true,
            'message' => 'Voice call initiated',
            'code_id' => $code_id
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Unable to initiate call - outgoing directory not accessible'
        ]);
    }
}

/**
 * Verify 2FA code (called from dialplan or web)
 */
function verify2FACode() {
    $code_id = $_POST['code_id'] ?? $_GET['code_id'] ?? '';
    $entered_code = $_POST['code'] ?? $_GET['code'] ?? '';

    if (empty($code_id) || empty($entered_code)) {
        echo json_encode(['success' => false, 'error' => 'Code ID and code required']);
        return;
    }

    $codes = loadCodes();

    if (!isset($codes[$code_id])) {
        echo json_encode(['success' => false, 'error' => 'Invalid code ID']);
        return;
    }

    $code_data = $codes[$code_id];

    // Check if expired
    if (time() > $code_data['expires_at']) {
        $codes[$code_id]['call_status'] = 'expired';
        saveCodes($codes);
        echo json_encode(['success' => false, 'error' => 'Code expired', 'status' => 'expired']);
        return;
    }

    // Check max attempts
    if ($code_data['attempts'] >= $code_data['max_attempts']) {
        $codes[$code_id]['call_status'] = 'max_attempts_exceeded';
        saveCodes($codes);
        echo json_encode(['success' => false, 'error' => 'Maximum attempts exceeded', 'status' => 'locked']);
        return;
    }

    // Increment attempts
    $codes[$code_id]['attempts']++;

    // Verify code
    if ($entered_code === $code_data['code']) {
        $codes[$code_id]['verified'] = true;
        $codes[$code_id]['verified_at'] = time();
        $codes[$code_id]['call_status'] = 'verified';
        saveCodes($codes);

        echo json_encode([
            'success' => true,
            'verified' => true,
            'message' => 'Code verified successfully',
            'extension' => $code_data['extension']
        ]);
    } else {
        $codes[$code_id]['call_status'] = 'invalid_code';
        $codes[$code_id]['last_attempt_at'] = time();
        saveCodes($codes);

        $remaining_attempts = $code_data['max_attempts'] - $codes[$code_id]['attempts'];

        echo json_encode([
            'success' => false,
            'verified' => false,
            'error' => 'Invalid code',
            'remaining_attempts' => $remaining_attempts
        ]);
    }
}

/**
 * Check verification status
 */
function checkVerificationStatus() {
    $code_id = $_GET['code_id'] ?? '';

    if (empty($code_id)) {
        echo json_encode(['success' => false, 'error' => 'Code ID required']);
        return;
    }

    $codes = loadCodes();

    if (!isset($codes[$code_id])) {
        echo json_encode(['success' => false, 'error' => 'Invalid code ID']);
        return;
    }

    $code_data = $codes[$code_id];

    echo json_encode([
        'success' => true,
        'code_id' => $code_id,
        'verified' => $code_data['verified'],
        'call_status' => $code_data['call_status'],
        'attempts' => $code_data['attempts'],
        'expires_in' => max(0, $code_data['expires_at'] - time())
    ]);
}

/**
 * Get configuration
 */
function getConfig() {
    $config = loadConfig();
    echo json_encode(['success' => true, 'config' => $config]);
}

/**
 * Update configuration
 */
function updateConfig() {
    $new_config = json_decode(file_get_contents('php://input'), true);

    if (!$new_config) {
        echo json_encode(['success' => false, 'error' => 'Invalid configuration data']);
        return;
    }

    global $config_file;
    file_put_contents($config_file, json_encode($new_config, JSON_PRETTY_PRINT));

    echo json_encode(['success' => true, 'message' => 'Configuration updated']);
}

/**
 * Upload custom voice prompt
 */
function uploadPrompt() {
    $prompt_type = $_POST['prompt_type'] ?? '';

    if (empty($prompt_type) || !isset($_FILES['audio_file'])) {
        echo json_encode(['success' => false, 'error' => 'Prompt type and audio file required']);
        return;
    }

    $upload_dir = '/var/lib/asterisk/sounds/custom/';
    @mkdir($upload_dir, 0755, true);

    $file = $_FILES['audio_file'];
    $filename = '2fa-' . $prompt_type . '.wav';
    $destination = $upload_dir . $filename;

    if (move_uploaded_file($file['tmp_name'], $destination)) {
        // Update config
        $config = loadConfig();
        $config['prompts'][$prompt_type] = $destination;
        global $config_file;
        file_put_contents($config_file, json_encode($config, JSON_PRETTY_PRINT));

        exec("sudo chown asterisk:asterisk {$destination}");
        exec("sudo chmod 644 {$destination}");

        echo json_encode([
            'success' => true,
            'message' => 'Prompt uploaded successfully',
            'file' => $filename
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Upload failed']);
    }
}

/**
 * List available prompts
 */
function listPrompts() {
    $config = loadConfig();
    echo json_encode([
        'success' => true,
        'prompts' => $config['prompts'] ?? []
    ]);
}

/**
 * Clean expired codes (maintenance)
 */
function cleanExpiredCodes() {
    $codes = loadCodes();
    $now = time();
    $cleaned = 0;

    foreach ($codes as $id => $data) {
        if ($now > $data['expires_at']) {
            unset($codes[$id]);
            $cleaned++;
        }
    }

    saveCodes($codes);

    echo json_encode([
        'success' => true,
        'cleaned' => $cleaned,
        'message' => "Cleaned $cleaned expired codes"
    ]);
}
?>
