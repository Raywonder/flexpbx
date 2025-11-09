<?php
/**
 * Twilio Webhook Handler for FlexPBX
 * Handles incoming calls, SMS, and status callbacks from Twilio
 */

header('Content-Type: text/xml');

require_once(__DIR__ . '/../includes/TwilioIntegration.php');

$logFile = '/home/flexpbxuser/logs/twilio-webhook.log';

// Log webhook data
function logWebhook($message) {
    global $logFile;
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[{$timestamp}] {$message}\n", FILE_APPEND);
}

// Get request data
$webhookType = $_GET['type'] ?? 'voice';
$postData = $_POST;

logWebhook("Webhook Type: {$webhookType}");
logWebhook("Data: " . json_encode($postData));

try {
    $twilio = new TwilioIntegration();

    // Validate Twilio signature (security)
    $signature = $_SERVER['HTTP_X_TWILIO_SIGNATURE'] ?? '';
    $url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";

    if ($signature && !$twilio->validateRequest($url, $postData, $signature)) {
        logWebhook("SECURITY: Invalid Twilio signature");
        http_response_code(403);
        exit('Invalid signature');
    }

    switch ($webhookType) {
        case 'voice':
            // Incoming voice call
            $from = $_POST['From'] ?? '';
            $to = $_POST['To'] ?? '';
            $callSid = $_POST['CallSid'] ?? '';

            logWebhook("Incoming call from {$from} to {$to}");

            // Generate TwiML response
            $actions = [
                [
                    'verb' => 'Say',
                    'attributes' => ['voice' => 'alice'],
                    'content' => 'Welcome to Flex P B X. Please hold while we connect your call.'
                ],
                [
                    'verb' => 'Dial',
                    'attributes' => ['timeout' => '30', 'callerId' => $from],
                    'content' => 'sip:2000@flexpbx.devinecreations.net' // Route to extension 2000
                ]
            ];

            echo $twilio->generateTwiML($actions);
            break;

        case 'sms':
            // Incoming SMS
            $from = $_POST['From'] ?? '';
            $to = $_POST['To'] ?? '';
            $body = $_POST['Body'] ?? '';
            $messageSid = $_POST['MessageSid'] ?? '';

            logWebhook("Incoming SMS from {$from}: {$body}");

            // Save message to database (implement as needed)
            // ...

            // Auto-reply TwiML
            $response = [
                [
                    'verb' => 'Message',
                    'content' => 'Thank you for your message. We will respond shortly.'
                ]
            ];

            echo $twilio->generateTwiML($response);
            break;

        case 'status':
            // Call status callback
            $callSid = $_POST['CallSid'] ?? '';
            $callStatus = $_POST['CallStatus'] ?? '';
            $duration = $_POST['CallDuration'] ?? 0;

            logWebhook("Call {$callSid} status: {$callStatus}, duration: {$duration}s");

            // Log call to database
            // ...

            echo '<?xml version="1.0" encoding="UTF-8"?><Response></Response>';
            break;

        case 'recording':
            // Recording completed
            $recordingSid = $_POST['RecordingSid'] ?? '';
            $recordingUrl = $_POST['RecordingUrl'] ?? '';
            $callSid = $_POST['CallSid'] ?? '';

            logWebhook("Recording {$recordingSid} completed for call {$callSid}");

            // Save recording info
            // ...

            echo '<?xml version="1.0" encoding="UTF-8"?><Response></Response>';
            break;

        default:
            logWebhook("Unknown webhook type: {$webhookType}");
            echo '<?xml version="1.0" encoding="UTF-8"?><Response></Response>';
            break;
    }

} catch (Exception $e) {
    logWebhook("ERROR: " . $e->getMessage());
    echo '<?xml version="1.0" encoding="UTF-8"?><Response><Say>An error occurred. Please try again later.</Say><Hangup/></Response>';
}
?>
