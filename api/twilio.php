<?php
/**
 * Twilio API Endpoint for FlexPBX
 * Handles all Twilio operations through REST API
 */

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

// Update activity
$_SESSION['last_activity'] = time();

header('Content-Type: application/json');

require_once(__DIR__ . '/../includes/TwilioIntegration.php');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    $twilio = new TwilioIntegration();

    switch ($action) {
        // ==================== CONFIGURATION ====================
        case 'save_config':
            $config = [
                'account_sid' => $_POST['account_sid'] ?? '',
                'auth_token' => $_POST['auth_token'] ?? '',
                'twilio_number' => $_POST['twilio_number'] ?? '',
                'default_twiml_url' => $_POST['default_twiml_url'] ?? '',
                'webhook_url' => $_POST['webhook_url'] ?? '',
                'enabled' => isset($_POST['enabled']) && $_POST['enabled'] === 'true'
            ];

            if ($twilio->saveConfig($config)) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Twilio configuration saved successfully'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to save configuration'
                ]);
            }
            break;

        case 'get_config':
            $configFile = '/home/flexpbxuser/config/twilio_config.json';
            if (file_exists($configFile)) {
                $config = json_decode(file_get_contents($configFile), true);
                // Hide sensitive data
                if (isset($config['auth_token'])) {
                    $config['auth_token'] = '••••••••' . substr($config['auth_token'], -4);
                }
                echo json_encode([
                    'success' => true,
                    'config' => $config
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'No configuration found'
                ]);
            }
            break;

        case 'test_connection':
            $result = $twilio->testConnection();
            echo json_encode($result);
            break;

        // ==================== CALLS ====================
        case 'make_call':
            $to = $_POST['to'] ?? '';
            $from = $_POST['from'] ?? null;
            $twimlUrl = $_POST['twiml_url'] ?? null;

            if (!$to) {
                echo json_encode(['success' => false, 'message' => 'To number required']);
                exit;
            }

            $to = $twilio->formatPhoneNumber($to);
            if ($from) {
                $from = $twilio->formatPhoneNumber($from);
            }

            $result = $twilio->makeCall($to, $from, $twimlUrl);
            echo json_encode([
                'success' => true,
                'call' => $result
            ]);
            break;

        case 'get_call':
            $callSid = $_GET['call_sid'] ?? '';
            if (!$callSid) {
                echo json_encode(['success' => false, 'message' => 'Call SID required']);
                exit;
            }

            $result = $twilio->getCall($callSid);
            echo json_encode([
                'success' => true,
                'call' => $result
            ]);
            break;

        case 'list_calls':
            $filters = [];
            if (isset($_GET['status'])) $filters['Status'] = $_GET['status'];
            if (isset($_GET['from'])) $filters['From'] = $_GET['from'];
            if (isset($_GET['to'])) $filters['To'] = $_GET['to'];
            if (isset($_GET['limit'])) $filters['PageSize'] = $_GET['limit'];

            $result = $twilio->listCalls($filters);
            echo json_encode([
                'success' => true,
                'calls' => $result['calls'] ?? [],
                'total' => count($result['calls'] ?? [])
            ]);
            break;

        case 'hangup_call':
            $callSid = $_POST['call_sid'] ?? '';
            if (!$callSid) {
                echo json_encode(['success' => false, 'message' => 'Call SID required']);
                exit;
            }

            $result = $twilio->hangupCall($callSid);
            echo json_encode([
                'success' => true,
                'message' => 'Call hung up',
                'call' => $result
            ]);
            break;

        // ==================== SMS ====================
        case 'send_sms':
            $to = $_POST['to'] ?? '';
            $body = $_POST['body'] ?? '';
            $from = $_POST['from'] ?? null;
            $mediaUrl = $_POST['media_url'] ?? null;

            if (!$to || !$body) {
                echo json_encode(['success' => false, 'message' => 'To and body required']);
                exit;
            }

            $to = $twilio->formatPhoneNumber($to);
            if ($from) {
                $from = $twilio->formatPhoneNumber($from);
            }

            $result = $twilio->sendSMS($to, $body, $from, $mediaUrl);
            echo json_encode([
                'success' => true,
                'message' => $result
            ]);
            break;

        case 'get_message':
            $messageSid = $_GET['message_sid'] ?? '';
            if (!$messageSid) {
                echo json_encode(['success' => false, 'message' => 'Message SID required']);
                exit;
            }

            $result = $twilio->getMessage($messageSid);
            echo json_encode([
                'success' => true,
                'message' => $result
            ]);
            break;

        case 'list_messages':
            $filters = [];
            if (isset($_GET['from'])) $filters['From'] = $_GET['from'];
            if (isset($_GET['to'])) $filters['To'] = $_GET['to'];
            if (isset($_GET['limit'])) $filters['PageSize'] = $_GET['limit'];

            $result = $twilio->listMessages($filters);
            echo json_encode([
                'success' => true,
                'messages' => $result['messages'] ?? [],
                'total' => count($result['messages'] ?? [])
            ]);
            break;

        // ==================== PHONE NUMBERS ====================
        case 'search_numbers':
            $areaCode = $_GET['area_code'] ?? '';
            $filters = [];
            if ($areaCode) $filters['AreaCode'] = $areaCode;
            if (isset($_GET['contains'])) $filters['Contains'] = $_GET['contains'];

            $result = $twilio->searchAvailableNumbers('US', $filters);
            echo json_encode([
                'success' => true,
                'available_numbers' => $result['available_phone_numbers'] ?? []
            ]);
            break;

        case 'purchase_number':
            $phoneNumber = $_POST['phone_number'] ?? '';
            if (!$phoneNumber) {
                echo json_encode(['success' => false, 'message' => 'Phone number required']);
                exit;
            }

            $config = [];
            if (isset($_POST['voice_url'])) $config['VoiceUrl'] = $_POST['voice_url'];
            if (isset($_POST['sms_url'])) $config['SmsUrl'] = $_POST['sms_url'];
            if (isset($_POST['friendly_name'])) $config['FriendlyName'] = $_POST['friendly_name'];

            $result = $twilio->purchaseNumber($phoneNumber, $config);
            echo json_encode([
                'success' => true,
                'message' => 'Number purchased successfully',
                'number' => $result
            ]);
            break;

        case 'list_numbers':
            $result = $twilio->listPhoneNumbers();
            echo json_encode([
                'success' => true,
                'numbers' => $result['incoming_phone_numbers'] ?? []
            ]);
            break;

        case 'update_number':
            $numberSid = $_POST['number_sid'] ?? '';
            if (!$numberSid) {
                echo json_encode(['success' => false, 'message' => 'Number SID required']);
                exit;
            }

            $config = [];
            if (isset($_POST['voice_url'])) $config['VoiceUrl'] = $_POST['voice_url'];
            if (isset($_POST['sms_url'])) $config['SmsUrl'] = $_POST['sms_url'];
            if (isset($_POST['friendly_name'])) $config['FriendlyName'] = $_POST['friendly_name'];

            $result = $twilio->updatePhoneNumber($numberSid, $config);
            echo json_encode([
                'success' => true,
                'message' => 'Number updated successfully',
                'number' => $result
            ]);
            break;

        case 'release_number':
            $numberSid = $_POST['number_sid'] ?? '';
            if (!$numberSid) {
                echo json_encode(['success' => false, 'message' => 'Number SID required']);
                exit;
            }

            $twilio->releasePhoneNumber($numberSid);
            echo json_encode([
                'success' => true,
                'message' => 'Number released successfully'
            ]);
            break;

        // ==================== RECORDINGS ====================
        case 'list_recordings':
            $filters = [];
            if (isset($_GET['call_sid'])) $filters['CallSid'] = $_GET['call_sid'];
            if (isset($_GET['limit'])) $filters['PageSize'] = $_GET['limit'];

            $result = $twilio->listRecordings($filters);
            echo json_encode([
                'success' => true,
                'recordings' => $result['recordings'] ?? []
            ]);
            break;

        case 'get_recording':
            $recordingSid = $_GET['recording_sid'] ?? '';
            if (!$recordingSid) {
                echo json_encode(['success' => false, 'message' => 'Recording SID required']);
                exit;
            }

            $result = $twilio->getRecording($recordingSid);
            $result['download_url'] = $twilio->getRecordingUrl($recordingSid);

            echo json_encode([
                'success' => true,
                'recording' => $result
            ]);
            break;

        case 'delete_recording':
            $recordingSid = $_POST['recording_sid'] ?? '';
            if (!$recordingSid) {
                echo json_encode(['success' => false, 'message' => 'Recording SID required']);
                exit;
            }

            $twilio->deleteRecording($recordingSid);
            echo json_encode([
                'success' => true,
                'message' => 'Recording deleted successfully'
            ]);
            break;

        // ==================== ACCOUNT INFO ====================
        case 'get_account':
            $result = $twilio->getAccount();
            echo json_encode([
                'success' => true,
                'account' => $result
            ]);
            break;

        case 'get_balance':
            $result = $twilio->getBalance();
            echo json_encode([
                'success' => true,
                'balance' => $result
            ]);
            break;

        case 'get_usage':
            $category = $_GET['category'] ?? 'today';
            $result = $twilio->getUsage($category);
            echo json_encode([
                'success' => true,
                'usage' => $result
            ]);
            break;

        // ==================== TWIML GENERATION ====================
        case 'generate_twiml':
            $actions = json_decode($_POST['actions'] ?? '[]', true);
            if (!$actions) {
                echo json_encode(['success' => false, 'message' => 'Actions required']);
                exit;
            }

            $twiml = $twilio->generateTwiML($actions);
            echo json_encode([
                'success' => true,
                'twiml' => $twiml
            ]);
            break;

        default:
            echo json_encode([
                'success' => false,
                'message' => 'Unknown action',
                'available_actions' => [
                    'Configuration' => ['save_config', 'get_config', 'test_connection'],
                    'Calls' => ['make_call', 'get_call', 'list_calls', 'hangup_call'],
                    'SMS' => ['send_sms', 'get_message', 'list_messages'],
                    'Numbers' => ['search_numbers', 'purchase_number', 'list_numbers', 'update_number', 'release_number'],
                    'Recordings' => ['list_recordings', 'get_recording', 'delete_recording'],
                    'Account' => ['get_account', 'get_balance', 'get_usage'],
                    'TwiML' => ['generate_twiml']
                ]
            ]);
            break;
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
