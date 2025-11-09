<?php
/**
 * FlexBot API Endpoint
 * Natural language interface for checklist management
 *
 * Version: 1.0
 * Compatible with: FlexPBX v1.2+
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/flexbot-config.php';
require_once __DIR__ . '/../includes/FlexBot.php';

// Verify API key or session
$api_key = $_GET['api_key'] ?? $_POST['api_key'] ?? '';
$is_api_auth = ($api_key === $config['api_key']);

session_start();
$is_session_auth = ($_SESSION['logged_in'] ?? false) || ($_SESSION['admin_logged_in'] ?? false);

if (!$is_api_auth && !$is_session_auth) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'help';
$user_id = $_SESSION['user_id'] ?? 0;
$user_role = $_SESSION['user_role'] ?? 'user';

// Initialize FlexBot
$flexbot_config = $flexbot_config ?? [];
$flexBot = new FlexBot($pdo, $flexbot_config);

try {
    switch ($action) {
        case 'chat':
        case 'ask':
            chat();
            break;

        case 'format_note':
            formatNote();
            break;

        case 'suggest_next':
            suggestNext();
            break;

        case 'update_notes':
            updateNotes();
            break;

        case 'status':
            getStatus();
            break;

        case 'help':
            showHelp();
            break;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Chat with FlexBot
 */
function chat() {
    global $flexBot, $user_id, $user_role;

    $message = $_POST['message'] ?? $_GET['message'] ?? '';

    if (empty($message)) {
        throw new Exception('message parameter is required');
    }

    $response = $flexBot->processCommand($message, $user_id, $user_role);

    echo json_encode($response);
}

/**
 * Format a note
 */
function formatNote() {
    global $flexBot;

    $content = $_POST['content'] ?? '';
    $noteType = $_POST['note_type'] ?? 'info';

    if (empty($content)) {
        throw new Exception('content parameter is required');
    }

    $response = $flexBot->formatNote($content, $noteType);

    echo json_encode($response);
}

/**
 * Suggest next action
 */
function suggestNext() {
    global $flexBot, $user_id;

    $checklistType = $_GET['checklist_type'] ?? 'system_setup';

    $response = $flexBot->suggestNextAction($checklistType, $user_id);

    echo json_encode($response);
}

/**
 * Update all notes with AI formatting
 */
function updateNotes() {
    global $flexBot, $user_role;

    // Only admins can trigger bulk updates
    if ($user_role !== 'admin') {
        throw new Exception('Admin access required');
    }

    $checklistType = $_POST['checklist_type'] ?? 'system_setup';

    $response = $flexBot->updateChecklistNotes($checklistType);

    echo json_encode($response);
}

/**
 * Get FlexBot status
 */
function getStatus() {
    global $flexBot;

    $status = $flexBot->getStatus();

    echo json_encode([
        'success' => true,
        'status' => $status
    ]);
}

/**
 * Show help information
 */
function showHelp() {
    echo json_encode([
        'success' => true,
        'endpoints' => [
            'chat' => [
                'method' => 'POST',
                'params' => ['message' => 'Your question or command'],
                'description' => 'Chat with FlexBot using natural language'
            ],
            'format_note' => [
                'method' => 'POST',
                'params' => ['content' => 'Note text', 'note_type' => 'info|warning|success|danger'],
                'description' => 'Format and enhance a note'
            ],
            'suggest_next' => [
                'method' => 'GET',
                'params' => ['checklist_type' => 'system_setup'],
                'description' => 'Get AI suggestion for next checklist item'
            ],
            'update_notes' => [
                'method' => 'POST',
                'params' => ['checklist_type' => 'system_setup'],
                'description' => 'Bulk update all notes with AI formatting (admin only)'
            ],
            'status' => [
                'method' => 'GET',
                'params' => [],
                'description' => 'Get FlexBot system status'
            ]
        ],
        'examples' => [
            'Ask question' => 'POST /api/flexbot.php?action=chat with message="How do I configure email?"',
            'Format note' => 'POST /api/flexbot.php?action=format_note with content="Configure SMTP settings"',
            'Get suggestion' => 'GET /api/flexbot.php?action=suggest_next&checklist_type=system_setup'
        ]
    ]);
}
