<?php
/**
 * FlexPBX Voicemail API
 * Manage voicemail boxes, messages, and settings
 */

require_once __DIR__ . '/auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$auth = checkAuth();
if (!$auth['authenticated']) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';
$mailbox = $_GET['mailbox'] ?? '';
$message_id = $_GET['message_id'] ?? '';

// Voicemail configuration
$voicemail_conf = '/etc/asterisk/voicemail.conf';
$voicemail_spool = '/var/spool/asterisk/voicemail';

switch ($path) {
    case '':
    case 'list':
        handleListMailboxes($method);
        break;

    case 'create':
        handleCreateMailbox($method);
        break;

    case 'delete':
        handleDeleteMailbox($method, $mailbox);
        break;

    case 'update':
        handleUpdateMailbox($method, $mailbox);
        break;

    case 'messages':
        handleListMessages($method, $mailbox);
        break;

    case 'message':
        handleMessageDetails($method, $mailbox, $message_id);
        break;

    case 'delete-message':
        handleDeleteMessage($method, $mailbox, $message_id);
        break;

    case 'mark-read':
        handleMarkRead($method, $mailbox, $message_id);
        break;

    case 'statistics':
        handleStatistics($method, $mailbox);
        break;

    case 'greetings':
        handleGreetings($method, $mailbox);
        break;

    case 'settings':
        handleSettings($method, $mailbox);
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found']);
        break;
}

/**
 * List all voicemail boxes
 */
function handleListMailboxes($method) {
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    $mailboxes = getVoicemailBoxes();

    echo json_encode([
        'success' => true,
        'mailboxes' => $mailboxes,
        'total' => count($mailboxes),
        'timestamp' => date('c')
    ]);
}

/**
 * Create new voicemail box
 */
function handleCreateMailbox($method) {
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    global $voicemail_conf, $voicemail_spool;

    $data = json_decode(file_get_contents('php://input'), true);

    // Validate required fields
    $mailbox = $data['mailbox'] ?? '';
    $pin = $data['pin'] ?? '';
    $name = $data['name'] ?? '';
    $email = $data['email'] ?? '';

    if (empty($mailbox) || empty($pin) || empty($name)) {
        http_response_code(400);
        echo json_encode(['error' => 'Mailbox number, PIN, and name are required']);
        return;
    }

    // Validate mailbox format
    if (!preg_match('/^\d{3,5}$/', $mailbox)) {
        http_response_code(400);
        echo json_encode(['error' => 'Mailbox must be 3-5 digits']);
        return;
    }

    // Validate PIN format
    if (!preg_match('/^\d{4,10}$/', $pin)) {
        http_response_code(400);
        echo json_encode(['error' => 'PIN must be 4-10 digits']);
        return;
    }

    // Check if mailbox already exists
    $existing = getVoicemailBoxes();
    foreach ($existing as $box) {
        if ($box['mailbox'] === $mailbox) {
            http_response_code(409);
            echo json_encode(['error' => 'Mailbox already exists']);
            return;
        }
    }

    $context = $data['context'] ?? 'flexpbx';
    $timezone = $data['timezone'] ?? 'central';
    $attach = $data['attach'] ?? 'yes';
    $delete = $data['delete'] ?? 'no';
    $maxmsg = $data['maxmsg'] ?? '100';
    $maxsecs = $data['maxsecs'] ?? '300';

    // Build voicemail entry
    $options = [];
    if (!empty($email)) {
        $options[] = "email=$email";
    }
    $options[] = "tz=$timezone";
    $options[] = "attach=$attach";
    $options[] = "delete=$delete";
    $options[] = "maxmsg=$maxmsg";
    $options[] = "maxsecs=$maxsecs";

    $options_str = '|' . implode('|', $options);
    $voicemail_entry = "\n{$mailbox} => {$pin},{$name}{$options_str}\n";

    // Find the context section or create it
    $config_content = file_get_contents($voicemail_conf);

    if (strpos($config_content, "[$context]") !== false) {
        // Context exists, append to it
        $pattern = "/(\[$context\][^\[]*)/";
        $config_content = preg_replace($pattern, "$1$voicemail_entry", $config_content);
    } else {
        // Context doesn't exist, create it
        $config_content .= "\n[$context]$voicemail_entry";
    }

    // Write configuration
    if (file_put_contents($voicemail_conf, $config_content) === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to write voicemail configuration']);
        return;
    }

    // Set permissions
    exec("sudo chown asterisk:asterisk $voicemail_conf");
    exec("sudo chmod 640 $voicemail_conf");

    // Create mailbox directories
    $mailbox_dir = "$voicemail_spool/$context/$mailbox";
    @mkdir($mailbox_dir, 0755, true);
    @mkdir("$mailbox_dir/INBOX", 0755, true);
    @mkdir("$mailbox_dir/Old", 0755, true);
    @mkdir("$mailbox_dir/tmp", 0755, true);

    exec("sudo chown -R asterisk:asterisk $voicemail_spool/$context");

    // Reload voicemail
    exec('sudo asterisk -rx "voicemail reload" 2>&1', $reload_output);

    echo json_encode([
        'success' => true,
        'message' => 'Voicemail box created successfully',
        'mailbox' => [
            'mailbox' => $mailbox,
            'name' => $name,
            'context' => $context,
            'email' => $email,
            'created' => true
        ],
        'reload_output' => $reload_output,
        'timestamp' => date('c')
    ]);
}

/**
 * Delete voicemail box
 */
function handleDeleteMailbox($method, $mailbox) {
    if ($method !== 'DELETE') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    global $voicemail_conf, $voicemail_spool;

    if (empty($mailbox)) {
        http_response_code(400);
        echo json_encode(['error' => 'Mailbox number required']);
        return;
    }

    $context = $_GET['context'] ?? 'flexpbx';

    // Read configuration
    $config_content = file_get_contents($voicemail_conf);
    $lines = explode("\n", $config_content);
    $new_lines = [];
    $in_target_context = false;
    $found = false;

    foreach ($lines as $line) {
        $trimmed = trim($line);

        // Check if we're entering the target context
        if (preg_match('/^\[(.+)\]$/', $trimmed, $matches)) {
            $current_context = $matches[1];
            $in_target_context = ($current_context === $context);
            $new_lines[] = $line;
            continue;
        }

        // If in target context and this is the mailbox line, skip it
        if ($in_target_context && preg_match("/^$mailbox\s*=>/", $trimmed)) {
            $found = true;
            continue; // Skip this line (delete it)
        }

        $new_lines[] = $line;
    }

    if (!$found) {
        http_response_code(404);
        echo json_encode(['error' => 'Mailbox not found']);
        return;
    }

    // Write updated configuration
    file_put_contents($voicemail_conf, implode("\n", $new_lines));
    exec("sudo chown asterisk:asterisk $voicemail_conf");
    exec("sudo chmod 640 $voicemail_conf");

    // Delete mailbox directory and messages
    $mailbox_dir = "$voicemail_spool/$context/$mailbox";
    if (is_dir($mailbox_dir)) {
        exec("sudo rm -rf " . escapeshellarg($mailbox_dir));
    }

    // Reload voicemail
    exec('sudo asterisk -rx "voicemail reload" 2>&1', $reload_output);

    echo json_encode([
        'success' => true,
        'message' => 'Voicemail box deleted successfully',
        'mailbox' => $mailbox,
        'context' => $context,
        'reload_output' => $reload_output,
        'timestamp' => date('c')
    ]);
}

/**
 * Update voicemail box settings
 */
function handleUpdateMailbox($method, $mailbox) {
    if ($method !== 'PUT') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    global $voicemail_conf;

    if (empty($mailbox)) {
        http_response_code(400);
        echo json_encode(['error' => 'Mailbox number required']);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $context = $data['context'] ?? 'flexpbx';

    // Read current configuration
    $config_content = file_get_contents($voicemail_conf);
    $lines = explode("\n", $config_content);
    $new_lines = [];
    $in_target_context = false;
    $found = false;

    foreach ($lines as $line) {
        $trimmed = trim($line);

        // Check if we're entering a context
        if (preg_match('/^\[(.+)\]$/', $trimmed, $matches)) {
            $current_context = $matches[1];
            $in_target_context = ($current_context === $context);
            $new_lines[] = $line;
            continue;
        }

        // If in target context and this is the mailbox line, update it
        if ($in_target_context && preg_match("/^$mailbox\s*=>\s*(.+)/", $trimmed, $matches)) {
            $found = true;

            // Parse current settings
            $parts = explode(',', $matches[1], 2);
            $current_pin = trim($parts[0]);

            // Get name and options
            $name_and_options = isset($parts[1]) ? $parts[1] : '';
            $option_parts = explode('|', $name_and_options);
            $current_name = trim($option_parts[0]);

            // Use provided values or keep current
            $new_pin = $data['pin'] ?? $current_pin;
            $new_name = $data['name'] ?? $current_name;
            $email = $data['email'] ?? '';
            $timezone = $data['timezone'] ?? 'central';
            $attach = $data['attach'] ?? 'yes';
            $delete = $data['delete'] ?? 'no';

            // Build new options
            $options = [];
            if (!empty($email)) {
                $options[] = "email=$email";
            }
            $options[] = "tz=$timezone";
            $options[] = "attach=$attach";
            $options[] = "delete=$delete";

            $options_str = '|' . implode('|', $options);
            $new_line = "$mailbox => $new_pin,$new_name$options_str";
            $new_lines[] = $new_line;
            continue;
        }

        $new_lines[] = $line;
    }

    if (!$found) {
        http_response_code(404);
        echo json_encode(['error' => 'Mailbox not found']);
        return;
    }

    // Write updated configuration
    file_put_contents($voicemail_conf, implode("\n", $new_lines));
    exec("sudo chown asterisk:asterisk $voicemail_conf");
    exec("sudo chmod 640 $voicemail_conf");

    // Reload voicemail
    exec('sudo asterisk -rx "voicemail reload" 2>&1', $reload_output);

    echo json_encode([
        'success' => true,
        'message' => 'Voicemail box updated successfully',
        'mailbox' => $mailbox,
        'context' => $context,
        'reload_output' => $reload_output,
        'timestamp' => date('c')
    ]);
}

/**
 * List messages for a mailbox
 */
function handleListMessages($method, $mailbox) {
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    if (empty($mailbox)) {
        http_response_code(400);
        echo json_encode(['error' => 'Mailbox number required']);
        return;
    }

    global $voicemail_spool;
    $context = $_GET['context'] ?? 'flexpbx';
    $folder = $_GET['folder'] ?? 'INBOX'; // INBOX, Old, Work, Family, Friends

    $messages = getMessages($context, $mailbox, $folder);

    echo json_encode([
        'success' => true,
        'mailbox' => $mailbox,
        'context' => $context,
        'folder' => $folder,
        'messages' => $messages,
        'total' => count($messages),
        'timestamp' => date('c')
    ]);
}

/**
 * Get message details
 */
function handleMessageDetails($method, $mailbox, $message_id) {
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    if (empty($mailbox) || empty($message_id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Mailbox and message ID required']);
        return;
    }

    global $voicemail_spool;
    $context = $_GET['context'] ?? 'flexpbx';
    $folder = $_GET['folder'] ?? 'INBOX';

    $message_file = "$voicemail_spool/$context/$mailbox/$folder/msg{$message_id}.txt";

    if (!file_exists($message_file)) {
        http_response_code(404);
        echo json_encode(['error' => 'Message not found']);
        return;
    }

    $message = parseMessageFile($message_file, $message_id);

    // Check if audio file exists
    $audio_file = "$voicemail_spool/$context/$mailbox/$folder/msg{$message_id}.wav";
    $message['has_audio'] = file_exists($audio_file);
    if ($message['has_audio']) {
        $message['audio_size'] = filesize($audio_file);
        $message['audio_url'] = "/voicemail/$context/$mailbox/$folder/msg{$message_id}.wav";
    }

    echo json_encode([
        'success' => true,
        'message' => $message,
        'timestamp' => date('c')
    ]);
}

/**
 * Delete message
 */
function handleDeleteMessage($method, $mailbox, $message_id) {
    if ($method !== 'DELETE') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    if (empty($mailbox) || empty($message_id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Mailbox and message ID required']);
        return;
    }

    global $voicemail_spool;
    $context = $_GET['context'] ?? 'flexpbx';
    $folder = $_GET['folder'] ?? 'INBOX';

    $message_dir = "$voicemail_spool/$context/$mailbox/$folder";
    $files_deleted = [];

    // Delete all message files (txt, wav, WAV)
    $extensions = ['txt', 'wav', 'WAV', 'gsm', 'g729'];
    foreach ($extensions as $ext) {
        $file = "$message_dir/msg{$message_id}.$ext";
        if (file_exists($file)) {
            unlink($file);
            $files_deleted[] = basename($file);
        }
    }

    if (empty($files_deleted)) {
        http_response_code(404);
        echo json_encode(['error' => 'Message not found']);
        return;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Message deleted successfully',
        'mailbox' => $mailbox,
        'message_id' => $message_id,
        'files_deleted' => $files_deleted,
        'timestamp' => date('c')
    ]);
}

/**
 * Mark message as read (move to Old folder)
 */
function handleMarkRead($method, $mailbox, $message_id) {
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    if (empty($mailbox) || empty($message_id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Mailbox and message ID required']);
        return;
    }

    global $voicemail_spool;
    $context = $_GET['context'] ?? 'flexpbx';

    $inbox_dir = "$voicemail_spool/$context/$mailbox/INBOX";
    $old_dir = "$voicemail_spool/$context/$mailbox/Old";

    // Ensure Old directory exists
    @mkdir($old_dir, 0755, true);

    $files_moved = [];
    $extensions = ['txt', 'wav', 'WAV', 'gsm', 'g729'];

    foreach ($extensions as $ext) {
        $source = "$inbox_dir/msg{$message_id}.$ext";
        if (file_exists($source)) {
            // Find next available number in Old folder
            $next_num = 0;
            while (file_exists("$old_dir/msg" . str_pad($next_num, 4, '0', STR_PAD_LEFT) . ".$ext")) {
                $next_num++;
            }
            $dest_num = str_pad($next_num, 4, '0', STR_PAD_LEFT);
            $dest = "$old_dir/msg{$dest_num}.$ext";

            rename($source, $dest);
            $files_moved[] = basename($source) . ' -> ' . basename($dest);
        }
    }

    if (empty($files_moved)) {
        http_response_code(404);
        echo json_encode(['error' => 'Message not found in INBOX']);
        return;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Message marked as read',
        'mailbox' => $mailbox,
        'message_id' => $message_id,
        'files_moved' => $files_moved,
        'timestamp' => date('c')
    ]);
}

/**
 * Get voicemail statistics
 */
function handleStatistics($method, $mailbox) {
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    global $voicemail_spool;
    $context = $_GET['context'] ?? 'flexpbx';

    if (!empty($mailbox)) {
        // Statistics for specific mailbox
        $stats = getMailboxStatistics($context, $mailbox);
    } else {
        // Statistics for all mailboxes
        $mailboxes = getVoicemailBoxes();
        $stats = [
            'total_mailboxes' => count($mailboxes),
            'mailboxes' => []
        ];

        foreach ($mailboxes as $box) {
            $stats['mailboxes'][] = getMailboxStatistics($box['context'], $box['mailbox']);
        }
    }

    echo json_encode([
        'success' => true,
        'statistics' => $stats,
        'timestamp' => date('c')
    ]);
}

/**
 * Manage greetings
 */
function handleGreetings($method, $mailbox) {
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    if (empty($mailbox)) {
        http_response_code(400);
        echo json_encode(['error' => 'Mailbox number required']);
        return;
    }

    global $voicemail_spool;
    $context = $_GET['context'] ?? 'flexpbx';
    $mailbox_dir = "$voicemail_spool/$context/$mailbox";

    $greetings = [];

    // Check for different greeting types
    $greeting_types = [
        'unavail' => 'Unavailable greeting',
        'busy' => 'Busy greeting',
        'greet' => 'Custom greeting',
        'temp' => 'Temporary greeting'
    ];

    foreach ($greeting_types as $type => $description) {
        $greeting_file = "$mailbox_dir/$type.wav";
        if (file_exists($greeting_file)) {
            $greetings[] = [
                'type' => $type,
                'description' => $description,
                'file' => basename($greeting_file),
                'size' => filesize($greeting_file),
                'modified' => date('c', filemtime($greeting_file)),
                'url' => "/voicemail/$context/$mailbox/$type.wav"
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'mailbox' => $mailbox,
        'context' => $context,
        'greetings' => $greetings,
        'total' => count($greetings),
        'timestamp' => date('c')
    ]);
}

/**
 * Get mailbox settings
 */
function handleSettings($method, $mailbox) {
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    if (empty($mailbox)) {
        http_response_code(400);
        echo json_encode(['error' => 'Mailbox number required']);
        return;
    }

    $context = $_GET['context'] ?? 'flexpbx';
    $mailboxes = getVoicemailBoxes();

    foreach ($mailboxes as $box) {
        if ($box['mailbox'] === $mailbox && $box['context'] === $context) {
            echo json_encode([
                'success' => true,
                'mailbox' => $mailbox,
                'context' => $context,
                'settings' => $box,
                'timestamp' => date('c')
            ]);
            return;
        }
    }

    http_response_code(404);
    echo json_encode(['error' => 'Mailbox not found']);
}

// Helper functions

function getVoicemailBoxes() {
    global $voicemail_conf;

    if (!file_exists($voicemail_conf)) {
        return [];
    }

    $mailboxes = [];
    $lines = file($voicemail_conf, FILE_IGNORE_NEW_LINES);
    $current_context = '';

    foreach ($lines as $line) {
        $trimmed = trim($line);

        // Skip comments and empty lines
        if (empty($trimmed) || $trimmed[0] === ';') {
            continue;
        }

        // Check for context header
        if (preg_match('/^\[(.+)\]$/', $trimmed, $matches)) {
            $current_context = $matches[1];
            continue;
        }

        // Parse mailbox entry: mailbox => pin,name|options
        if (preg_match('/^(\d+)\s*=>\s*(.+)/', $trimmed, $matches)) {
            $mailbox_num = $matches[1];
            $config = $matches[2];

            // Split by comma: pin,name|options
            $parts = explode(',', $config, 2);
            $pin = trim($parts[0]);

            if (!isset($parts[1])) {
                continue;
            }

            // Split name and options
            $name_and_options = $parts[1];
            $option_parts = explode('|', $name_and_options);
            $name = trim($option_parts[0]);

            // Parse options
            $options = [
                'email' => '',
                'timezone' => 'central',
                'attach' => 'yes',
                'delete' => 'no',
                'maxmsg' => '100',
                'maxsecs' => '300'
            ];

            for ($i = 1; $i < count($option_parts); $i++) {
                $option = trim($option_parts[$i]);
                if (strpos($option, '=') !== false) {
                    list($key, $value) = explode('=', $option, 2);
                    $options[trim($key)] = trim($value);
                }
            }

            $mailboxes[] = [
                'mailbox' => $mailbox_num,
                'context' => $current_context,
                'pin' => $pin,
                'name' => $name,
                'email' => $options['email'],
                'timezone' => $options['tz'] ?? $options['timezone'],
                'attach' => $options['attach'],
                'delete' => $options['delete'],
                'maxmsg' => $options['maxmsg'],
                'maxsecs' => $options['maxsecs']
            ];
        }
    }

    return $mailboxes;
}

function getMessages($context, $mailbox, $folder) {
    global $voicemail_spool;

    $folder_dir = "$voicemail_spool/$context/$mailbox/$folder";

    if (!is_dir($folder_dir)) {
        return [];
    }

    $messages = [];
    $files = glob("$folder_dir/msg*.txt");

    foreach ($files as $file) {
        $basename = basename($file, '.txt');
        $msg_num = str_replace('msg', '', $basename);

        $message = parseMessageFile($file, $msg_num);
        $messages[] = $message;
    }

    // Sort by date descending (newest first)
    usort($messages, function($a, $b) {
        return strtotime($b['origdate']) - strtotime($a['origdate']);
    });

    return $messages;
}

function parseMessageFile($file, $msg_num) {
    $content = file_get_contents($file);
    $lines = explode("\n", $content);

    $message = [
        'id' => $msg_num,
        'from' => '',
        'callerid' => '',
        'origdate' => '',
        'origtime' => '',
        'duration' => 0,
        'flag' => '',
        'read' => false
    ];

    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || $line[0] === ';') {
            continue;
        }

        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = strtolower(trim($key));
            $value = trim($value);

            switch ($key) {
                case 'callerid':
                    $message['callerid'] = $value;
                    // Parse caller ID for display
                    if (preg_match('/"([^"]+)"\s*<([^>]+)>/', $value, $matches)) {
                        $message['from'] = $matches[1] . ' (' . $matches[2] . ')';
                    } else {
                        $message['from'] = $value;
                    }
                    break;
                case 'origdate':
                    $message['origdate'] = $value;
                    break;
                case 'origtime':
                    $message['origtime'] = $value;
                    $message['duration'] = (int)$value;
                    $message['duration_formatted'] = formatDuration((int)$value);
                    break;
                case 'flag':
                    $message['flag'] = $value;
                    $message['read'] = (strtolower($value) === 'read');
                    break;
            }
        }
    }

    return $message;
}

function getMailboxStatistics($context, $mailbox) {
    global $voicemail_spool;

    $mailbox_dir = "$voicemail_spool/$context/$mailbox";

    $stats = [
        'mailbox' => $mailbox,
        'context' => $context,
        'inbox_count' => 0,
        'old_count' => 0,
        'total_messages' => 0,
        'unread_messages' => 0,
        'total_duration' => 0,
        'storage_used' => 0
    ];

    // Count INBOX messages
    $inbox_dir = "$mailbox_dir/INBOX";
    if (is_dir($inbox_dir)) {
        $inbox_files = glob("$inbox_dir/msg*.txt");
        $stats['inbox_count'] = count($inbox_files);
        $stats['unread_messages'] = $stats['inbox_count'];
    }

    // Count Old messages
    $old_dir = "$mailbox_dir/Old";
    if (is_dir($old_dir)) {
        $old_files = glob("$old_dir/msg*.txt");
        $stats['old_count'] = count($old_files);
    }

    $stats['total_messages'] = $stats['inbox_count'] + $stats['old_count'];

    // Calculate storage used
    if (is_dir($mailbox_dir)) {
        exec("du -sb " . escapeshellarg($mailbox_dir) . " 2>/dev/null", $du_output);
        if (!empty($du_output[0])) {
            $parts = explode("\t", $du_output[0]);
            $stats['storage_used'] = (int)$parts[0];
            $stats['storage_used_formatted'] = formatBytes($stats['storage_used']);
        }
    }

    return $stats;
}

function formatDuration($seconds) {
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $secs = $seconds % 60;

    if ($hours > 0) {
        return sprintf('%dh %dm %ds', $hours, $minutes, $secs);
    } elseif ($minutes > 0) {
        return sprintf('%dm %ds', $minutes, $secs);
    } else {
        return sprintf('%ds', $secs);
    }
}

function formatBytes($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

function checkAuth() {
    session_start();
    return [
        'authenticated' => isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true,
        'username' => $_SESSION['username'] ?? null
    ];
}
?>
