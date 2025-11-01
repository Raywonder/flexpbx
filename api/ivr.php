<?php
/**
 * FlexPBX IVR (Auto Attendant) Management API
 *
 * Endpoints:
 * - GET    ?path=list              - List all IVR menus
 * - GET    ?path=get&id=X          - Get single IVR menu details
 * - POST   ?path=create            - Create new IVR menu
 * - PUT    ?path=update&id=X       - Update IVR configuration
 * - DELETE ?path=delete&id=X       - Delete IVR menu
 * - GET    ?path=options&ivr_id=X  - Get IVR menu options
 * - POST   ?path=add-option        - Add menu option
 * - PUT    ?path=update-option&id=X - Update menu option
 * - DELETE ?path=remove-option&id=X - Remove menu option
 * - GET    ?path=statistics&ivr_id=X - Get IVR usage statistics
 * - POST   ?path=apply-config      - Generate Asterisk dialplan and reload
 * - POST   ?path=upload-audio      - Upload audio file for IVR
 * - GET    ?path=audio-files       - List available audio files
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Load database configuration
$config = require __DIR__ . '/config.php';

try {
    $pdo = new PDO(
        "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4",
        $config['db_user'],
        $config['db_password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// Load permission helper
require_once __DIR__ . '/permission-helper.php';
$permissionHelper = new PermissionHelper();

$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

// Route requests
switch ($path) {
    case 'list':
        handleListIVRs();
        break;

    case 'get':
        handleGetIVR();
        break;

    case 'create':
        handleCreateIVR();
        break;

    case 'update':
        handleUpdateIVR();
        break;

    case 'delete':
        handleDeleteIVR();
        break;

    case 'options':
        handleGetOptions();
        break;

    case 'add-option':
        handleAddOption();
        break;

    case 'update-option':
        handleUpdateOption();
        break;

    case 'remove-option':
        handleRemoveOption();
        break;

    case 'statistics':
        handleGetStatistics();
        break;

    case 'apply-config':
        handleApplyConfig();
        break;

    case 'upload-audio':
        handleUploadAudio();
        break;

    case 'audio-files':
        handleListAudioFiles();
        break;

    default:
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Endpoint not found']);
        break;
}

/**
 * List all IVR menus
 */
function handleListIVRs() {
    global $pdo;

    try {
        $stmt = $pdo->query("
            SELECT
                i.*,
                COUNT(o.id) as option_count
            FROM ivr_menus i
            LEFT JOIN ivr_options o ON i.id = o.ivr_menu_id AND o.enabled = 1
            GROUP BY i.id
            ORDER BY i.ivr_number
        ");

        $ivrs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => $ivrs
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Get single IVR menu with all details
 */
function handleGetIVR() {
    global $pdo;

    $id = $_GET['id'] ?? null;
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'IVR ID required']);
        return;
    }

    try {
        // Get IVR details
        $stmt = $pdo->prepare("SELECT * FROM ivr_menus WHERE id = ?");
        $stmt->execute([$id]);
        $ivr = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$ivr) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'IVR not found']);
            return;
        }

        // Get options
        $stmt = $pdo->prepare("
            SELECT * FROM ivr_options
            WHERE ivr_menu_id = ?
            ORDER BY sort_order, digit
        ");
        $stmt->execute([$id]);
        $ivr['options'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => $ivr
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Create new IVR menu
 */
function handleCreateIVR() {
    global $pdo;

    $data = json_decode(file_get_contents('php://input'), true);

    // Validate required fields
    if (empty($data['ivr_number']) || empty($data['ivr_name'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'IVR number and name are required']);
        return;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO ivr_menus (
                ivr_number, ivr_name, description,
                greeting_type, greeting_text, greeting_file,
                timeout, invalid_retries,
                invalid_destination_type, invalid_destination_value,
                timeout_destination_type, timeout_destination_value,
                direct_dial_enabled, enabled
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $data['ivr_number'],
            $data['ivr_name'],
            $data['description'] ?? null,
            $data['greeting_type'] ?? 'recording',
            $data['greeting_text'] ?? null,
            $data['greeting_file'] ?? null,
            $data['timeout'] ?? 10,
            $data['invalid_retries'] ?? 3,
            $data['invalid_destination_type'] ?? 'operator',
            $data['invalid_destination_value'] ?? '0',
            $data['timeout_destination_type'] ?? 'operator',
            $data['timeout_destination_value'] ?? '0',
            $data['direct_dial_enabled'] ?? 0,
            $data['enabled'] ?? 1
        ]);

        $ivrId = $pdo->lastInsertId();

        echo json_encode([
            'success' => true,
            'message' => 'IVR menu created successfully',
            'ivr_id' => $ivrId
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Update IVR menu
 */
function handleUpdateIVR() {
    global $pdo;

    $id = $_GET['id'] ?? null;
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'IVR ID required']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    try {
        $stmt = $pdo->prepare("
            UPDATE ivr_menus SET
                ivr_number = ?,
                ivr_name = ?,
                description = ?,
                greeting_type = ?,
                greeting_text = ?,
                greeting_file = ?,
                timeout = ?,
                invalid_retries = ?,
                invalid_destination_type = ?,
                invalid_destination_value = ?,
                timeout_destination_type = ?,
                timeout_destination_value = ?,
                direct_dial_enabled = ?,
                enabled = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $data['ivr_number'],
            $data['ivr_name'],
            $data['description'] ?? null,
            $data['greeting_type'] ?? 'recording',
            $data['greeting_text'] ?? null,
            $data['greeting_file'] ?? null,
            $data['timeout'] ?? 10,
            $data['invalid_retries'] ?? 3,
            $data['invalid_destination_type'] ?? 'operator',
            $data['invalid_destination_value'] ?? '0',
            $data['timeout_destination_type'] ?? 'operator',
            $data['timeout_destination_value'] ?? '0',
            $data['direct_dial_enabled'] ?? 0,
            $data['enabled'] ?? 1,
            $id
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'IVR menu updated successfully'
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Delete IVR menu
 */
function handleDeleteIVR() {
    global $pdo;

    $id = $_GET['id'] ?? null;
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'IVR ID required']);
        return;
    }

    try {
        // Delete IVR (options will be deleted automatically due to CASCADE)
        $stmt = $pdo->prepare("DELETE FROM ivr_menus WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode([
            'success' => true,
            'message' => 'IVR menu deleted successfully'
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Get IVR menu options
 */
function handleGetOptions() {
    global $pdo;

    $ivrId = $_GET['ivr_id'] ?? null;
    if (!$ivrId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'IVR ID required']);
        return;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT * FROM ivr_options
            WHERE ivr_menu_id = ?
            ORDER BY sort_order, digit
        ");
        $stmt->execute([$ivrId]);
        $options = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => $options
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Add menu option
 */
function handleAddOption() {
    global $pdo;

    $data = json_decode(file_get_contents('php://input'), true);

    // Validate required fields
    if (empty($data['ivr_menu_id']) || !isset($data['digit'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'IVR menu ID and digit are required']);
        return;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO ivr_options (
                ivr_menu_id, digit, option_description,
                destination_type, destination_value,
                enabled, sort_order
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $data['ivr_menu_id'],
            $data['digit'],
            $data['option_description'] ?? null,
            $data['destination_type'] ?? 'extension',
            $data['destination_value'] ?? null,
            $data['enabled'] ?? 1,
            $data['sort_order'] ?? 0
        ]);

        $optionId = $pdo->lastInsertId();

        echo json_encode([
            'success' => true,
            'message' => 'Menu option added successfully',
            'option_id' => $optionId
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Update menu option
 */
function handleUpdateOption() {
    global $pdo;

    $id = $_GET['id'] ?? null;
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Option ID required']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    try {
        $stmt = $pdo->prepare("
            UPDATE ivr_options SET
                digit = ?,
                option_description = ?,
                destination_type = ?,
                destination_value = ?,
                enabled = ?,
                sort_order = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $data['digit'],
            $data['option_description'] ?? null,
            $data['destination_type'] ?? 'extension',
            $data['destination_value'] ?? null,
            $data['enabled'] ?? 1,
            $data['sort_order'] ?? 0,
            $id
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'Menu option updated successfully'
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Remove menu option
 */
function handleRemoveOption() {
    global $pdo;

    $id = $_GET['id'] ?? null;
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Option ID required']);
        return;
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM ivr_options WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode([
            'success' => true,
            'message' => 'Menu option removed successfully'
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Get IVR usage statistics
 */
function handleGetStatistics() {
    global $pdo;

    $ivrId = $_GET['ivr_id'] ?? null;
    $days = $_GET['days'] ?? 30;

    try {
        if ($ivrId) {
            // Get statistics for specific IVR
            $stmt = $pdo->prepare("
                SELECT * FROM ivr_statistics
                WHERE ivr_menu_id = ?
                AND date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                ORDER BY date DESC
            ");
            $stmt->execute([$ivrId, $days]);
        } else {
            // Get statistics for all IVRs
            $stmt = $pdo->prepare("
                SELECT
                    s.*,
                    i.ivr_name,
                    i.ivr_number
                FROM ivr_statistics s
                JOIN ivr_menus i ON s.ivr_menu_id = i.id
                WHERE s.date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                ORDER BY s.date DESC, i.ivr_number
            ");
            $stmt->execute([$days]);
        }

        $statistics = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => $statistics
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Generate Asterisk dialplan configuration and reload
 */
function handleApplyConfig() {
    global $pdo;

    try {
        // Get all enabled IVR menus
        $stmt = $pdo->query("SELECT * FROM ivr_menus WHERE enabled = 1 ORDER BY ivr_number");
        $ivrs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Generate dialplan
        $dialplan = "; FlexPBX IVR Configuration\n";
        $dialplan .= "; Auto-generated on " . date('Y-m-d H:i:s') . "\n";
        $dialplan .= "; DO NOT EDIT MANUALLY - Changes will be overwritten\n\n";

        $dialplan .= "[flexpbx-ivr]\n";
        $dialplan .= "; Main IVR context - all IVR menus start here\n\n";

        foreach ($ivrs as $ivr) {
            // Get options for this IVR
            $optStmt = $pdo->prepare("
                SELECT * FROM ivr_options
                WHERE ivr_menu_id = ? AND enabled = 1
                ORDER BY sort_order, digit
            ");
            $optStmt->execute([$ivr['id']]);
            $options = $optStmt->fetchAll(PDO::FETCH_ASSOC);

            $dialplan .= generateIVRDialplan($ivr, $options);
        }

        // Write configuration file
        $configFile = '/etc/asterisk/extensions_ivr.conf';
        $backupFile = $configFile . '.backup-' . date('YmdHis');

        // Backup existing file if it exists
        if (file_exists($configFile)) {
            copy($configFile, $backupFile);
        }

        // Write new configuration
        if (file_put_contents($configFile, $dialplan) === false) {
            throw new Exception("Failed to write configuration file");
        }

        // Set proper permissions using permission helper
        global $permissionHelper;
        $permResult = $permissionHelper->autoFix([
            'config_file' => $configFile,
            'module' => 'ivr'
        ]);

        if (!$permResult['success']) {
            throw new Exception("Failed to set permissions: " . implode(', ', $permResult['errors']));
        }

        // Reload Asterisk dialplan
        exec('sudo asterisk -rx "dialplan reload" 2>&1', $output, $returnCode);

        if ($returnCode !== 0) {
            throw new Exception("Failed to reload dialplan: " . implode("\n", $output));
        }

        echo json_encode([
            'success' => true,
            'message' => 'IVR configuration applied successfully',
            'dialplan_reload' => implode("\n", $output),
            'backup_file' => $backupFile
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

/**
 * Generate dialplan for a single IVR menu
 */
function generateIVRDialplan($ivr, $options) {
    $dialplan = "; IVR Menu {$ivr['ivr_number']} - {$ivr['ivr_name']}\n";
    $dialplan .= "exten => {$ivr['ivr_number']},1,NoOp(IVR: {$ivr['ivr_name']})\n";
    $dialplan .= "same => n,Answer()\n";
    $dialplan .= "same => n,Wait(1)\n";

    // Set timeout and invalid retry counters
    $dialplan .= "same => n,Set(IVR_RETRIES=0)\n";
    $dialplan .= "same => n,Set(IVR_TIMEOUT={$ivr['timeout']})\n";
    $dialplan .= "same => n,Set(IVR_MAX_RETRIES={$ivr['invalid_retries']})\n";

    // Play greeting
    $dialplan .= "same => n(greeting),NoOp(Playing IVR Greeting)\n";

    if ($ivr['greeting_type'] === 'recording' && $ivr['greeting_file']) {
        $dialplan .= "same => n,Background({$ivr['greeting_file']})\n";
    } elseif ($ivr['greeting_type'] === 'tts' && $ivr['greeting_text']) {
        // TTS would require additional setup with festival or Google TTS
        $dialplan .= "same => n,Playback(silence/1)\n";
        $dialplan .= "same => n,NoOp(TTS: {$ivr['greeting_text']})\n";
    }

    // Wait for digit
    $dialplan .= "same => n,WaitExten({$ivr['timeout']})\n";

    // Add option handlers
    foreach ($options as $option) {
        $digit = $option['digit'];
        $dialplan .= "\n; Option {$digit}: {$option['option_description']}\n";
        $dialplan .= "exten => {$digit},1,NoOp(IVR {$ivr['ivr_number']} Option {$digit})\n";
        $dialplan .= "same => n," . generateDestination($option['destination_type'], $option['destination_value']) . "\n";
    }

    // Handle * to repeat menu
    $dialplan .= "\nexten => *,1,NoOp(Repeat Menu)\n";
    $dialplan .= "same => n,Goto({$ivr['ivr_number']},greeting)\n";

    // Handle timeout
    $dialplan .= "\nexten => t,1,NoOp(Timeout - no input received)\n";
    $dialplan .= "same => n,Set(IVR_RETRIES=\$[\${IVR_RETRIES} + 1])\n";
    $dialplan .= "same => n,GotoIf(\$[\${IVR_RETRIES} >= \${IVR_MAX_RETRIES}]?timeout_dest:retry)\n";
    $dialplan .= "same => n(retry),Playback(silence/1)\n";
    $dialplan .= "same => n,Goto({$ivr['ivr_number']},greeting)\n";
    $dialplan .= "same => n(timeout_dest)," . generateDestination($ivr['timeout_destination_type'], $ivr['timeout_destination_value']) . "\n";

    // Handle invalid input
    $dialplan .= "\nexten => i,1,NoOp(Invalid option)\n";
    $dialplan .= "same => n,Set(IVR_RETRIES=\$[\${IVR_RETRIES} + 1])\n";
    $dialplan .= "same => n,GotoIf(\$[\${IVR_RETRIES} >= \${IVR_MAX_RETRIES}]?invalid_dest:retry)\n";
    $dialplan .= "same => n(retry),Playback(invalid)\n";
    $dialplan .= "same => n,Goto({$ivr['ivr_number']},greeting)\n";
    $dialplan .= "same => n(invalid_dest)," . generateDestination($ivr['invalid_destination_type'], $ivr['invalid_destination_value']) . "\n";

    // Direct dial support (if enabled)
    if ($ivr['direct_dial_enabled']) {
        $dialplan .= "\n; Direct dial extensions (2XXX-9XXX)\n";
        $dialplan .= "exten => _[2-9]XXX,1,NoOp(Direct dial to \${EXTEN})\n";
        $dialplan .= "same => n,Goto(flexpbx-internal,\${EXTEN},1)\n";
    }

    $dialplan .= "\n";

    return $dialplan;
}

/**
 * Generate destination command based on type and value
 */
function generateDestination($type, $value) {
    switch ($type) {
        case 'extension':
            return "Goto(flexpbx-internal,{$value},1)";

        case 'queue':
            return "Goto(flexpbx-queues,{$value},1)";

        case 'ringgroup':
            return "Goto(flexpbx-ring-groups,{$value},1)";

        case 'ivr':
            return "Goto(flexpbx-ivr,{$value},1)";

        case 'conference':
            return "Goto(flexpbx-ring-groups,{$value},1)";

        case 'voicemail':
            return "Voicemail({$value}@flexpbx,su)";

        case 'operator':
            return "Goto(flexpbx-internal,0,1)";

        case 'hangup':
            return "Hangup()";

        default:
            return "Hangup()";
    }
}

/**
 * Upload audio file for IVR
 */
function handleUploadAudio() {
    // Check if file was uploaded
    if (!isset($_FILES['audio_file'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No file uploaded']);
        return;
    }

    $file = $_FILES['audio_file'];
    $uploadDir = '/var/lib/asterisk/sounds/custom/';

    // Create directory if it doesn't exist
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Validate file type
    $allowedTypes = ['audio/wav', 'audio/x-wav', 'audio/mpeg', 'audio/mp3'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedTypes)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid file type. Only WAV and MP3 files allowed.']);
        return;
    }

    // Generate safe filename
    $originalName = pathinfo($file['name'], PATHINFO_FILENAME);
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $safeFilename = preg_replace('/[^a-z0-9_-]/i', '_', $originalName);
    $filename = $safeFilename . '.' . $extension;
    $filepath = $uploadDir . $filename;

    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        // Set proper permissions using permission helper
        global $permissionHelper;
        $permissionHelper->autoFix(['audio_file' => $filepath]);

        // If it's MP3, convert to WAV
        if (strtolower($extension) === 'mp3') {
            $wavFile = $uploadDir . $safeFilename . '.wav';
            exec("sox {$filepath} -r 8000 -c 1 {$wavFile} 2>&1", $output, $returnCode);

            if ($returnCode === 0) {
                // Fix permissions on converted file
                $permissionHelper->autoFix(['audio_file' => $wavFile]);

                echo json_encode([
                    'success' => true,
                    'message' => 'Audio file uploaded and converted successfully',
                    'filename' => 'custom/' . $safeFilename,
                    'original_file' => $filename,
                    'converted_file' => $safeFilename . '.wav'
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'message' => 'Audio file uploaded (conversion failed, manual conversion required)',
                    'filename' => 'custom/' . $safeFilename,
                    'error' => 'sox conversion failed'
                ]);
            }
        } else {
            echo json_encode([
                'success' => true,
                'message' => 'Audio file uploaded successfully',
                'filename' => 'custom/' . $safeFilename
            ]);
        }
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to save uploaded file']);
    }
}

/**
 * List available audio files
 */
function handleListAudioFiles() {
    $soundsDir = '/var/lib/asterisk/sounds/';
    $customDir = $soundsDir . 'custom/';

    $audioFiles = [];

    // List custom files
    if (is_dir($customDir)) {
        $files = scandir($customDir);
        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'wav') {
                $audioFiles[] = [
                    'category' => 'Custom',
                    'filename' => 'custom/' . pathinfo($file, PATHINFO_FILENAME),
                    'display_name' => pathinfo($file, PATHINFO_FILENAME),
                    'size' => filesize($customDir . $file)
                ];
            }
        }
    }

    // List some common system sounds
    $systemSounds = [
        'welcome',
        'press-1',
        'press-2',
        'invalid',
        'goodbye',
        'thank-you-for-calling',
        'pls-hold-while-try',
        'hold-tone'
    ];

    foreach ($systemSounds as $sound) {
        $audioFiles[] = [
            'category' => 'System',
            'filename' => $sound,
            'display_name' => ucwords(str_replace('-', ' ', $sound)),
            'size' => null
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => $audioFiles
    ]);
}
