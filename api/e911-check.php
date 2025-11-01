#!/usr/bin/env php
<?php
/**
 * FlexPBX E911 Verification AGI Script
 * Called by Asterisk before connecting emergency calls
 * Checks if user has E911 configured and returns status
 */

// AGI environment variables
$agi = [];
while (!feof(STDIN)) {
    $line = trim(fgets(STDIN));
    if ($line === '') break;

    $parts = explode(':', $line, 2);
    if (count($parts) === 2) {
        $agi[trim($parts[0])] = trim($parts[1]);
    }
}

// Get caller extension
$caller_id = $agi['agi_callerid'] ?? '';
preg_match('/<(\d+)>/', $caller_id, $matches);
$extension = $matches[1] ?? $agi['agi_extension'] ?? '';

// Log for debugging
error_log("E911 Check: Extension {$extension} attempting emergency call");

// Function to send AGI command
function agi_command($command) {
    fwrite(STDOUT, $command . "\n");
    fflush(STDOUT);
    $response = fgets(STDIN);
    return trim($response);
}

// Check if E911 address is configured
$e911_file = "/home/flexpbxuser/e911_addresses/e911_{$extension}.json";

if (!file_exists($e911_file)) {
    // No E911 configured - BLOCK CALL
    error_log("E911 Check: BLOCKED - No E911 address for extension {$extension}");

    agi_command("SET VARIABLE E911_STATUS \"NOT_CONFIGURED\"");
    agi_command("SET VARIABLE E911_EXTENSION \"{$extension}\"");
    exit(0);
}

// Load E911 address
$e911_data = json_decode(file_get_contents($e911_file), true);

if (!$e911_data || !$e911_data['emergency_enabled']) {
    // E911 not enabled - BLOCK CALL
    error_log("E911 Check: BLOCKED - E911 not enabled for extension {$extension}");

    agi_command("SET VARIABLE E911_STATUS \"NOT_ENABLED\"");
    agi_command("SET VARIABLE E911_EXTENSION \"{$extension}\"");
    exit(0);
}

// E911 configured - ALLOW CALL
error_log("E911 Check: ALLOWED - E911 configured for extension {$extension}");

// Set variables for dialplan
agi_command("SET VARIABLE E911_STATUS \"CONFIGURED\"");
agi_command("SET VARIABLE E911_EXTENSION \"{$extension}\"");
agi_command("SET VARIABLE E911_STREET \"{$e911_data['street']}\"");
agi_command("SET VARIABLE E911_CITY \"{$e911_data['city']}\"");
agi_command("SET VARIABLE E911_STATE \"{$e911_data['state']}\"");
agi_command("SET VARIABLE E911_POSTAL \"{$e911_data['postal_code']}\"");

// Create full address for TTS
$full_address = "{$e911_data['street']}, {$e911_data['city']}, {$e911_data['state']} {$e911_data['postal_code']}";
agi_command("SET VARIABLE E911_FULL_ADDRESS \"{$full_address}\"");

// Log emergency call attempt
$log_entry = [
    'timestamp' => date('Y-m-d H:i:s'),
    'extension' => $extension,
    'caller_id' => $caller_id,
    'address' => $full_address,
    'status' => 'ALLOWED'
];

$log_file = '/home/flexpbxuser/logs/e911_calls.log';
$log_dir = dirname($log_file);
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

file_put_contents(
    $log_file,
    json_encode($log_entry) . "\n",
    FILE_APPEND
);

exit(0);
?>
