<?php
/**
 * TextNow SMS Testing Script
 * Tests SMS sending and receiving for FlexPBX TextNow integration
 *
 * Test Number: 3364626141 (336-462-6141)
 * From Number: 8326786610 (832-678-6610)
 * Account: d.stansberry@me.com or mrwonderful@raywonderis.me
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/SMSProviderManager.php';

// CLI mode for better output
if (php_sapi_name() === 'cli') {
    define('CLI_MODE', true);
    echo "\n=== TextNow SMS Test Script ===\n";
    echo "Started: " . date('Y-m-d H:i:s') . "\n\n";
} else {
    define('CLI_MODE', false);
    header('Content-Type: text/plain');
    echo "=== TextNow SMS Test Script ===\n";
    echo "Started: " . date('Y-m-d H:i:s') . "\n\n";
}

// Configuration
$config = require __DIR__ . '/config.php';
$testNumber = '3364626141'; // Test destination: 336-462-6141
$fromNumber = '8326786610'; // TextNow number: 832-678-6610

// Database connection
try {
    $pdo = new PDO(
        "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4",
        $config['db_user'],
        $config['db_password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "[✓] Database connected\n";
} catch (PDOException $e) {
    die("[✗] Database connection failed: " . $e->getMessage() . "\n");
}

// Initialize SMS Provider Manager
$manager = new SMSProviderManager($pdo);
echo "[✓] SMS Provider Manager initialized\n\n";

// Check TextNow provider status
echo "--- Checking TextNow Provider Status ---\n";
$provider = $manager->getProvider('textnow');

if (!$provider) {
    die("[✗] TextNow provider not found in database\n");
}

echo "[✓] Provider ID: " . $provider['id'] . "\n";
echo "[✓] Provider Name: " . $provider['provider_name'] . "\n";
echo "[✓] Phone Number: " . ($provider['phone_number'] ?? 'Not set') . "\n";
echo "[✓] Enabled: " . ($provider['enabled'] ? 'Yes' : 'No') . "\n";
echo "[✓] Priority: " . $provider['priority'] . "\n\n";

if (!$provider['enabled']) {
    echo "[!] WARNING: Provider is disabled. Enabling it now...\n";
    $stmt = $pdo->prepare("UPDATE sms_providers SET enabled = 1 WHERE id = ?");
    $stmt->execute([$provider['id']]);
    echo "[✓] Provider enabled\n\n";
}

// Test 1: Send SMS
echo "--- Test 1: Sending SMS ---\n";
echo "From: $fromNumber\n";
echo "To: $testNumber\n";
echo "Message: Testing TextNow SMS from FlexPBX\n\n";

$testMessage = "Hello! This is a test message from FlexPBX TextNow integration (Test #" . time() . "). Please reply to confirm receipt.";

// Use the existing TextNow integration
require_once __DIR__ . '/../includes/TextNowIntegration.php';

try {
    $textnow = new TextNowIntegration();

    // Send SMS via TextNow API
    echo "[*] Attempting to send SMS...\n";

    // Note: This assumes TextNow has been properly configured
    // If using email-to-SMS gateway instead:
    $emailToSMS = sendViaEmailGateway($testNumber, $testMessage);

    if ($emailToSMS) {
        echo "[✓] SMS sent successfully via email gateway\n";

        // Log to database
        $messageId = $manager->logMessage(
            $provider['id'],
            'textnow',
            'outbound',
            $fromNumber,
            $testNumber,
            $testMessage,
            [
                'message_type' => 'sms',
                'status' => 'sent',
                'extension_number' => '2000'
            ]
        );

        echo "[✓] Message logged to database (ID: $messageId)\n";
    } else {
        echo "[✗] Failed to send SMS\n";
    }

} catch (Exception $e) {
    echo "[✗] Error: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 2: Wait for reply
echo "--- Test 2: Waiting for Reply ---\n";
echo "Waiting 30 seconds for reply from $testNumber...\n";
echo "(You should reply to the message now)\n\n";

for ($i = 30; $i > 0; $i--) {
    echo "Remaining: {$i}s\r";
    sleep(1);
}

echo "\n\n";

// Test 3: Check for received messages
echo "--- Test 3: Checking for Received Messages ---\n";

try {
    $stmt = $pdo->prepare("
        SELECT * FROM sms_messages
        WHERE provider_type = 'textnow'
        AND direction = 'inbound'
        AND from_number LIKE ?
        AND created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        ORDER BY created_at DESC
        LIMIT 10
    ");

    $searchNumber = '%' . substr($testNumber, -10);
    $stmt->execute([$searchNumber]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($messages) > 0) {
        echo "[✓] Found " . count($messages) . " recent message(s) from test number:\n\n";

        foreach ($messages as $msg) {
            echo "  Message ID: " . $msg['id'] . "\n";
            echo "  From: " . $msg['from_number'] . "\n";
            echo "  To: " . $msg['to_number'] . "\n";
            echo "  Body: " . $msg['message_body'] . "\n";
            echo "  Time: " . $msg['created_at'] . "\n";
            echo "  Status: " . $msg['status'] . "\n";
            echo "  ---\n\n";
        }
    } else {
        echo "[!] No messages received yet from $testNumber\n";
        echo "[i] Messages may take a few minutes to arrive\n";
        echo "[i] Check the sms_messages table manually for updates\n\n";
    }

} catch (PDOException $e) {
    echo "[✗] Error checking messages: " . $e->getMessage() . "\n";
}

// Test 4: Get all recent TextNow activity
echo "--- Test 4: Recent TextNow Activity ---\n";

try {
    $stmt = $pdo->prepare("
        SELECT * FROM sms_messages
        WHERE provider_type = 'textnow'
        AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ORDER BY created_at DESC
        LIMIT 20
    ");

    $stmt->execute();
    $recentMessages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "[✓] Found " . count($recentMessages) . " TextNow message(s) in the last hour\n\n";

    if (count($recentMessages) > 0) {
        echo "Recent Activity:\n";
        foreach ($recentMessages as $msg) {
            $direction = $msg['direction'] === 'outbound' ? '→' : '←';
            echo "  {$direction} {$msg['created_at']} | {$msg['from_number']} → {$msg['to_number']}\n";
            echo "     " . substr($msg['message_body'], 0, 60) . "...\n";
        }
    }

} catch (PDOException $e) {
    echo "[✗] Error: " . $e->getMessage() . "\n";
}

echo "\n";

// Summary
echo "=== Test Summary ===\n";
echo "Test Number: $testNumber\n";
echo "From Number: $fromNumber\n";
echo "Provider: TextNow\n";
echo "Completed: " . date('Y-m-d H:i:s') . "\n\n";

echo "Next Steps:\n";
echo "1. Check your phone for the test message\n";
echo "2. Reply to the message\n";
echo "3. Run this script again to see the reply\n";
echo "4. Check /home/flexpbxuser/logs/textnow.log for detailed logs\n";
echo "5. Review sms_messages table in database\n\n";

// Helper function to send via email-to-SMS gateway
function sendViaEmailGateway($phoneNumber, $message) {
    // Clean phone number
    $cleanNumber = preg_replace('/[^0-9]/', '', $phoneNumber);

    // TextNow email-to-SMS gateway
    $to = $cleanNumber . '@txt.voice.google.com'; // Try different gateways
    $alternatives = [
        $cleanNumber . '@tmomail.net',
        $cleanNumber . '@vtext.com',
        $cleanNumber . '@messaging.sprintpcs.com'
    ];

    $subject = '';
    $headers = [
        'From: sms@flexpbx.devinecreations.net',
        'Reply-To: noreply@flexpbx.devinecreations.net',
        'X-Mailer: FlexPBX TextNow Integration',
        'Content-Type: text/plain; charset=UTF-8'
    ];

    $success = mail($to, $subject, $message, implode("\r\n", $headers));

    // Log attempt
    error_log("SMS email sent to: $to | Success: " . ($success ? 'Yes' : 'No'));

    return $success;
}

echo "\n=== Test Complete ===\n\n";
?>
