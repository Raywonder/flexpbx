<?php
/**
 * TextNow API Endpoint for FlexPBX
 * Handles all TextNow operations through REST API
 * Supports Voice Calls, SMS, and MMS
 */

// Determine if this is a webhook request (no session needed)
$webhookActions = ['inbound_call', 'inbound_sms', 'inbound_mms', 'status_callback'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$isWebhook = in_array($action, $webhookActions);

// Only require authentication for non-webhook requests
if (!$isWebhook) {
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
}

header('Content-Type: application/json');

require_once(__DIR__ . '/../includes/TextNowIntegration.php');

try {
    $textnow = new TextNowIntegration();

    switch ($action) {
        // ==================== CONFIGURATION ====================
        case 'save_config':
            $config = [
                'api_key' => $_POST['api_key'] ?? '',
                'api_secret' => $_POST['api_secret'] ?? '',
                'textnow_number' => $_POST['textnow_number'] ?? '',
                'webhook_url' => $_POST['webhook_url'] ?? '',
                'record_calls' => isset($_POST['record_calls']) && $_POST['record_calls'] === 'true',
                'rate_limit_per_minute' => intval($_POST['rate_limit_per_minute'] ?? 60),
                'enabled' => isset($_POST['enabled']) && $_POST['enabled'] === 'true'
            ];

            if ($textnow->saveConfig($config)) {
                echo json_encode([
                    'success' => true,
                    'message' => 'TextNow configuration saved successfully'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to save configuration'
                ]);
            }
            break;

        case 'get_config':
            $configFile = '/home/flexpbxuser/config/textnow_config.json';
            if (file_exists($configFile)) {
                $config = json_decode(file_get_contents($configFile), true);
                // Hide sensitive data
                if (isset($config['api_key'])) {
                    $config['api_key'] = '••••••••' . substr($config['api_key'], -4);
                }
                if (isset($config['api_secret'])) {
                    $config['api_secret'] = '••••••••';
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
            $result = $textnow->testConnection();
            echo json_encode($result);
            break;

        // ==================== VOICE CALLS ====================
        case 'make_call':
            $to = $_POST['to'] ?? $_GET['to'] ?? '';
            $from = $_POST['from'] ?? $_GET['from'] ?? null;
            $callbackUrl = $_POST['callback_url'] ?? null;

            if (!$to) {
                echo json_encode(['success' => false, 'message' => 'To number required']);
                exit;
            }

            $result = $textnow->makeCall($to, $from, $callbackUrl);
            echo json_encode([
                'success' => true,
                'call' => $result
            ]);
            break;

        case 'get_call':
            $callId = $_GET['call_id'] ?? '';
            if (!$callId) {
                echo json_encode(['success' => false, 'message' => 'Call ID required']);
                exit;
            }

            $result = $textnow->getCall($callId);
            echo json_encode([
                'success' => true,
                'call' => $result
            ]);
            break;

        case 'list_calls':
            $filters = [];
            if (isset($_GET['status'])) $filters['status'] = $_GET['status'];
            if (isset($_GET['from'])) $filters['from'] = $_GET['from'];
            if (isset($_GET['to'])) $filters['to'] = $_GET['to'];
            if (isset($_GET['limit'])) $filters['limit'] = intval($_GET['limit']);
            if (isset($_GET['start_date'])) $filters['start_date'] = $_GET['start_date'];
            if (isset($_GET['end_date'])) $filters['end_date'] = $_GET['end_date'];

            $result = $textnow->listCalls($filters);
            echo json_encode([
                'success' => true,
                'calls' => $result['calls'] ?? [],
                'total' => count($result['calls'] ?? [])
            ]);
            break;

        case 'hangup_call':
            $callId = $_POST['call_id'] ?? '';
            if (!$callId) {
                echo json_encode(['success' => false, 'message' => 'Call ID required']);
                exit;
            }

            $result = $textnow->hangupCall($callId);
            echo json_encode([
                'success' => true,
                'message' => 'Call hung up',
                'call' => $result
            ]);
            break;

        case 'call_history':
            $number = $_GET['number'] ?? null;
            $limit = intval($_GET['limit'] ?? 50);

            $history = $textnow->getCallHistory($number, $limit);
            echo json_encode([
                'success' => true,
                'calls' => $history,
                'total' => count($history)
            ]);
            break;

        case 'get_recording':
            $callId = $_GET['call_id'] ?? '';
            if (!$callId) {
                echo json_encode(['success' => false, 'message' => 'Call ID required']);
                exit;
            }

            $url = $textnow->getCallRecording($callId);
            echo json_encode([
                'success' => true,
                'recording_url' => $url
            ]);
            break;

        case 'download_recording':
            $callId = $_GET['call_id'] ?? '';
            if (!$callId) {
                echo json_encode(['success' => false, 'message' => 'Call ID required']);
                exit;
            }

            $path = $textnow->downloadCallRecording($callId);
            echo json_encode([
                'success' => true,
                'file_path' => $path,
                'download_url' => str_replace('/home/flexpbxuser/public_html', '', $path)
            ]);
            break;

        // ==================== SMS ====================
        case 'send_sms':
            $to = $_POST['to'] ?? $_GET['to'] ?? '';
            $body = $_POST['message'] ?? $_POST['body'] ?? $_GET['message'] ?? '';
            $from = $_POST['from'] ?? $_GET['from'] ?? null;

            if (!$to || !$body) {
                echo json_encode(['success' => false, 'message' => 'To and body/message required']);
                exit;
            }

            $result = $textnow->sendSMS($to, $body, $from);
            echo json_encode([
                'success' => true,
                'message' => $result
            ]);
            break;

        // ==================== MMS ====================
        case 'send_mms':
            $to = $_POST['to'] ?? '';
            $body = $_POST['body'] ?? $_POST['message'] ?? '';
            $from = $_POST['from'] ?? null;

            if (!$to) {
                echo json_encode(['success' => false, 'message' => 'To number required']);
                exit;
            }

            // Handle media URLs from POST
            $mediaUrls = [];
            if (isset($_POST['media_urls'])) {
                $mediaUrls = is_array($_POST['media_urls']) ? $_POST['media_urls'] : json_decode($_POST['media_urls'], true);
            }

            // Handle file uploads
            if (isset($_FILES['media'])) {
                $files = $_FILES['media'];

                // Handle multiple files
                if (is_array($files['name'])) {
                    for ($i = 0; $i < count($files['name']); $i++) {
                        if ($files['error'][$i] === UPLOAD_ERR_OK) {
                            $tmpPath = $files['tmp_name'][$i];
                            $url = $textnow->uploadMedia($tmpPath);
                            if ($url) {
                                $mediaUrls[] = $url;
                            }
                        }
                    }
                } else {
                    if ($files['error'] === UPLOAD_ERR_OK) {
                        $url = $textnow->uploadMedia($files['tmp_name']);
                        if ($url) {
                            $mediaUrls[] = $url;
                        }
                    }
                }
            }

            if (empty($mediaUrls)) {
                echo json_encode(['success' => false, 'message' => 'No media provided for MMS']);
                exit;
            }

            $result = $textnow->sendMMS($to, $body, $mediaUrls, $from);
            echo json_encode([
                'success' => true,
                'message' => $result
            ]);
            break;

        case 'upload_media':
            if (!isset($_FILES['file'])) {
                echo json_encode(['success' => false, 'message' => 'No file uploaded']);
                exit;
            }

            $file = $_FILES['file'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['success' => false, 'message' => 'File upload error']);
                exit;
            }

            $url = $textnow->uploadMedia($file['tmp_name']);
            echo json_encode([
                'success' => true,
                'media_url' => $url
            ]);
            break;

        // ==================== MESSAGES ====================
        case 'get_message':
            $messageId = $_GET['message_id'] ?? '';
            if (!$messageId) {
                echo json_encode(['success' => false, 'message' => 'Message ID required']);
                exit;
            }

            $result = $textnow->getMessage($messageId);
            echo json_encode([
                'success' => true,
                'message' => $result
            ]);
            break;

        case 'list_messages':
            $filters = [];
            if (isset($_GET['from'])) $filters['from'] = $_GET['from'];
            if (isset($_GET['to'])) $filters['to'] = $_GET['to'];
            if (isset($_GET['limit'])) $filters['limit'] = intval($_GET['limit']);
            if (isset($_GET['start_date'])) $filters['start_date'] = $_GET['start_date'];
            if (isset($_GET['end_date'])) $filters['end_date'] = $_GET['end_date'];

            $result = $textnow->listMessages($filters);
            echo json_encode([
                'success' => true,
                'messages' => $result['messages'] ?? [],
                'total' => count($result['messages'] ?? [])
            ]);
            break;

        case 'message_history':
            $number = $_GET['number'] ?? '';
            $limit = intval($_GET['limit'] ?? 50);

            if (!$number) {
                echo json_encode(['success' => false, 'message' => 'Number required']);
                exit;
            }

            $history = $textnow->getMessageHistory($number, $limit);
            echo json_encode([
                'success' => true,
                'messages' => $history,
                'total' => count($history)
            ]);
            break;

        case 'delete_message':
            $messageId = $_POST['message_id'] ?? '';
            if (!$messageId) {
                echo json_encode(['success' => false, 'message' => 'Message ID required']);
                exit;
            }

            $textnow->deleteMessage($messageId);
            echo json_encode([
                'success' => true,
                'message' => 'Message deleted successfully'
            ]);
            break;

        // ==================== PHONE NUMBERS ====================
        case 'search_numbers':
            $areaCode = $_GET['area_code'] ?? null;
            $filters = [];
            if (isset($_GET['contains'])) $filters['contains'] = $_GET['contains'];
            if (isset($_GET['state'])) $filters['state'] = $_GET['state'];
            if (isset($_GET['limit'])) $filters['limit'] = intval($_GET['limit']);

            $result = $textnow->searchAvailableNumbers($areaCode, $filters);
            echo json_encode([
                'success' => true,
                'available_numbers' => $result['numbers'] ?? []
            ]);
            break;

        case 'purchase_number':
            $phoneNumber = $_POST['phone_number'] ?? '';
            if (!$phoneNumber) {
                echo json_encode(['success' => false, 'message' => 'Phone number required']);
                exit;
            }

            $result = $textnow->purchaseNumber($phoneNumber);
            echo json_encode([
                'success' => true,
                'message' => 'Number purchased successfully',
                'number' => $result
            ]);
            break;

        case 'list_numbers':
            $result = $textnow->listPhoneNumbers();
            echo json_encode([
                'success' => true,
                'numbers' => $result['numbers'] ?? []
            ]);
            break;

        case 'update_number':
            $phoneNumber = $_POST['phone_number'] ?? '';
            if (!$phoneNumber) {
                echo json_encode(['success' => false, 'message' => 'Phone number required']);
                exit;
            }

            $config = [];
            if (isset($_POST['webhook_url'])) $config['webhook_url'] = $_POST['webhook_url'];
            if (isset($_POST['friendly_name'])) $config['friendly_name'] = $_POST['friendly_name'];

            $result = $textnow->updatePhoneNumber($phoneNumber, $config);
            echo json_encode([
                'success' => true,
                'message' => 'Number updated successfully',
                'number' => $result
            ]);
            break;

        case 'release_number':
            $phoneNumber = $_POST['phone_number'] ?? '';
            if (!$phoneNumber) {
                echo json_encode(['success' => false, 'message' => 'Phone number required']);
                exit;
            }

            $textnow->releasePhoneNumber($phoneNumber);
            echo json_encode([
                'success' => true,
                'message' => 'Number released successfully'
            ]);
            break;

        // ==================== ACCOUNT INFO ====================
        case 'get_account':
            $result = $textnow->getAccount();
            echo json_encode([
                'success' => true,
                'account' => $result
            ]);
            break;

        case 'get_usage':
            $startDate = $_GET['start_date'] ?? null;
            $endDate = $_GET['end_date'] ?? null;

            $result = $textnow->getUsage($startDate, $endDate);
            echo json_encode([
                'success' => true,
                'usage' => $result
            ]);
            break;

        case 'get_statistics':
            $stats = $textnow->getStatistics();
            echo json_encode([
                'success' => true,
                'statistics' => $stats
            ]);
            break;

        // ==================== WEBHOOK HANDLERS ====================
        case 'inbound_call':
            // Validate webhook signature
            $payload = file_get_contents('php://input');
            $signature = $_SERVER['HTTP_X_TEXTNOW_SIGNATURE'] ?? '';

            if (!$textnow->validateWebhook($payload, $signature)) {
                http_response_code(401);
                echo json_encode(['error' => 'Invalid webhook signature']);
                exit;
            }

            $data = json_decode($payload, true);
            $result = $textnow->handleInboundCall($data);

            echo json_encode([
                'success' => true,
                'call' => $result
            ]);
            break;

        case 'inbound_sms':
            // Validate webhook signature
            $payload = file_get_contents('php://input');
            $signature = $_SERVER['HTTP_X_TEXTNOW_SIGNATURE'] ?? '';

            if (!$textnow->validateWebhook($payload, $signature)) {
                http_response_code(401);
                echo json_encode(['error' => 'Invalid webhook signature']);
                exit;
            }

            $data = json_decode($payload, true);
            $result = $textnow->handleInboundSMS($data);

            echo json_encode([
                'success' => true,
                'message' => $result
            ]);
            break;

        case 'inbound_mms':
            // Validate webhook signature
            $payload = file_get_contents('php://input');
            $signature = $_SERVER['HTTP_X_TEXTNOW_SIGNATURE'] ?? '';

            if (!$textnow->validateWebhook($payload, $signature)) {
                http_response_code(401);
                echo json_encode(['error' => 'Invalid webhook signature']);
                exit;
            }

            $data = json_decode($payload, true);
            $result = $textnow->handleInboundMMS($data);

            echo json_encode([
                'success' => true,
                'message' => $result
            ]);
            break;

        case 'status_callback':
            // Validate webhook signature
            $payload = file_get_contents('php://input');
            $signature = $_SERVER['HTTP_X_TEXTNOW_SIGNATURE'] ?? '';

            if (!$textnow->validateWebhook($payload, $signature)) {
                http_response_code(401);
                echo json_encode(['error' => 'Invalid webhook signature']);
                exit;
            }

            $data = json_decode($payload, true);
            $result = $textnow->handleStatusCallback($data);

            echo json_encode([
                'success' => true,
                'data' => $result
            ]);
            break;

        default:
            echo json_encode([
                'success' => false,
                'message' => 'Unknown action',
                'available_actions' => [
                    'Configuration' => ['save_config', 'get_config', 'test_connection'],
                    'Voice Calls' => ['make_call', 'get_call', 'list_calls', 'hangup_call', 'call_history', 'get_recording', 'download_recording'],
                    'SMS' => ['send_sms', 'get_message', 'list_messages', 'message_history', 'delete_message'],
                    'MMS' => ['send_mms', 'upload_media'],
                    'Phone Numbers' => ['search_numbers', 'purchase_number', 'list_numbers', 'update_number', 'release_number'],
                    'Account' => ['get_account', 'get_usage', 'get_statistics'],
                    'Webhooks' => ['inbound_call', 'inbound_sms', 'inbound_mms', 'status_callback']
                ]
            ]);
            break;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
