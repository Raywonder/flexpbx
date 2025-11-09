<?php
// FlexPBX Conference Bridge API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

function respond($success, $message, $data = []) {
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ], $data));
    exit;
}

function executeAsteriskCommand($command) {
    $output = shell_exec("sudo asterisk -rx \"$command\" 2>&1");
    return $output;
}

function getActiveConferences() {
    $output = executeAsteriskCommand("confbridge list");
    $conferences = [];

    $lines = explode("\n", trim($output));
    foreach ($lines as $line) {
        if (preg_match('/^(\S+)\s+(\d+)\s+(\d+)\s+(\S+)\s+(\S+)/', $line, $matches)) {
            $conferences[] = [
                'conference' => $matches[1],
                'participants' => (int)$matches[2],
                'marked' => (int)$matches[3],
                'locked' => $matches[4] === 'locked',
                'muted' => $matches[5] === 'muted'
            ];
        }
    }

    return $conferences;
}

function getConferenceParticipants($conference) {
    $output = executeAsteriskCommand("confbridge list $conference");
    $participants = [];

    $lines = explode("\n", trim($output));
    foreach ($lines as $line) {
        if (preg_match('/^(\S+)\s+(\S+)\s+(\S+)\s+(\S+)\s+(\S+)/', $line, $matches)) {
            // Skip header line
            if ($matches[1] === 'Channel') continue;

            $participants[] = [
                'channel' => $matches[1],
                'user_profile' => $matches[2],
                'bridge_profile' => $matches[3],
                'menu' => $matches[4],
                'caller_id' => $matches[5]
            ];
        }
    }

    return $participants;
}

function kickParticipant($conference, $channel) {
    $output = executeAsteriskCommand("confbridge kick $conference $channel");
    return strpos($output, 'kicked') !== false;
}

function muteParticipant($conference, $channel) {
    $output = executeAsteriskCommand("confbridge mute $conference $channel");
    return strpos($output, 'muted') !== false;
}

function unmuteParticipant($conference, $channel) {
    $output = executeAsteriskCommand("confbridge unmute $conference $channel");
    return strpos($output, 'unmuted') !== false;
}

function lockConference($conference) {
    $output = executeAsteriskCommand("confbridge lock $conference");
    return strpos($output, 'locked') !== false;
}

function unlockConference($conference) {
    $output = executeAsteriskCommand("confbridge unlock $conference");
    return strpos($output, 'unlocked') !== false;
}

// Parse request
$method = $_SERVER['REQUEST_METHOD'];
$path = isset($_GET['path']) ? $_GET['path'] : '';
$data = json_decode(file_get_contents('php://input'), true);

// Route the request
switch ($method) {
    case 'GET':
        if (empty($path)) {
            // List all active conferences
            $conferences = getActiveConferences();
            respond(true, 'Active conferences retrieved', ['conferences' => $conferences]);
        } else {
            // Get participants for specific conference
            $participants = getConferenceParticipants($path);
            respond(true, "Participants retrieved for conference: $path", ['participants' => $participants]);
        }
        break;

    case 'POST':
        $action = isset($data['action']) ? $data['action'] : '';
        $conference = isset($data['conference']) ? $data['conference'] : '';
        $channel = isset($data['channel']) ? $data['channel'] : '';

        switch ($action) {
            case 'kick':
                if (empty($conference) || empty($channel)) {
                    respond(false, 'Conference and channel are required');
                }
                $result = kickParticipant($conference, $channel);
                respond($result, $result ? 'Participant kicked' : 'Failed to kick participant');
                break;

            case 'mute':
                if (empty($conference) || empty($channel)) {
                    respond(false, 'Conference and channel are required');
                }
                $result = muteParticipant($conference, $channel);
                respond($result, $result ? 'Participant muted' : 'Failed to mute participant');
                break;

            case 'unmute':
                if (empty($conference) || empty($channel)) {
                    respond(false, 'Conference and channel are required');
                }
                $result = unmuteParticipant($conference, $channel);
                respond($result, $result ? 'Participant unmuted' : 'Failed to unmute participant');
                break;

            case 'lock':
                if (empty($conference)) {
                    respond(false, 'Conference is required');
                }
                $result = lockConference($conference);
                respond($result, $result ? 'Conference locked' : 'Failed to lock conference');
                break;

            case 'unlock':
                if (empty($conference)) {
                    respond(false, 'Conference is required');
                }
                $result = unlockConference($conference);
                respond($result, $result ? 'Conference unlocked' : 'Failed to unlock conference');
                break;

            default:
                respond(false, 'Invalid action');
        }
        break;

    default:
        respond(false, 'Method not allowed');
}
