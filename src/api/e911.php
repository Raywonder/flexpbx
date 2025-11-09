<?php
/**
 * FlexPBX E911 Address Management API
 * Handles E911 emergency address configuration for users
 */

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Check authentication
$is_admin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
$is_user = isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;
$user_extension = $_SESSION['user_extension'] ?? null;

if (!$is_admin && !$is_user) {
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

require_once(__DIR__ . '/../includes/TwilioIntegration.php');

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$users_dir = '/home/flexpbxuser/users';
$e911_dir = '/home/flexpbxuser/e911';  // Changed to match dialplan
$log_file = '/home/flexpbxuser/logs/e911.log';

// Ensure E911 directory exists
if (!is_dir($e911_dir)) {
    mkdir($e911_dir, 0755, true);
    chown($e911_dir, 'asterisk');
    chgrp($e911_dir, 'asterisk');
}

// Logging function
function logE911($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[{$timestamp}] {$message}\n", FILE_APPEND);
}

try {
    switch ($action) {
        // ==================== GET E911 ADDRESS ====================
        case 'get_address':
            $extension = $_GET['extension'] ?? $user_extension;

            // Users can only view their own address unless admin
            if (!$is_admin && $extension !== $user_extension) {
                echo json_encode(['success' => false, 'message' => 'Permission denied']);
                exit;
            }

            $address_file = $e911_dir . '/ext_' . $extension . '.json';  // Changed to match dialplan

            if (file_exists($address_file)) {
                $address = json_decode(file_get_contents($address_file), true);
                echo json_encode([
                    'success' => true,
                    'address' => $address
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'No E911 address configured'
                ]);
            }
            break;

        // ==================== SAVE E911 ADDRESS ====================
        case 'save_address':
            $extension = $_POST['extension'] ?? $user_extension;

            // Users can only update their own address unless admin
            if (!$is_admin && $extension !== $user_extension) {
                echo json_encode(['success' => false, 'message' => 'Permission denied']);
                exit;
            }

            // Validate required fields
            $required_fields = ['street', 'city', 'state', 'postal_code'];
            foreach ($required_fields as $field) {
                if (empty($_POST[$field])) {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Missing required field: ' . $field
                    ]);
                    exit;
                }
            }

            $address_data = [
                'extension' => $extension,
                'street' => htmlspecialchars($_POST['street']),
                'street2' => htmlspecialchars($_POST['street2'] ?? ''),
                'city' => htmlspecialchars($_POST['city']),
                'state' => htmlspecialchars($_POST['state']),
                'postal_code' => htmlspecialchars($_POST['postal_code']),
                'country' => htmlspecialchars($_POST['country'] ?? 'US'),
                'friendly_name' => htmlspecialchars($_POST['friendly_name'] ?? ''),
                'emergency_enabled' => isset($_POST['emergency_enabled']) && $_POST['emergency_enabled'] === 'true',
                'validated' => false,
                'twilio_address_sid' => null,
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => $is_admin ? ($_SESSION['admin_username'] ?? 'admin') : ($_SESSION['user_username'] ?? $extension)
            ];

            // Try to register with Twilio if enabled
            if ($address_data['emergency_enabled']) {
                try {
                    $twilio = new TwilioIntegration();

                    // Create address in Twilio
                    $twilioAddress = $twilio->createEmergencyAddress(
                        $address_data['friendly_name'] ?: "E911 - Ext {$extension}",
                        $address_data['street'],
                        $address_data['city'],
                        $address_data['state'],
                        $address_data['postal_code'],
                        $address_data['country']
                    );

                    if ($twilioAddress && isset($twilioAddress['sid'])) {
                        $address_data['twilio_address_sid'] = $twilioAddress['sid'];
                        $address_data['validated'] = $twilioAddress['validated'] ?? false;
                    }
                } catch (Exception $e) {
                    // Log error but continue saving locally
                    error_log("Twilio E911 registration failed: " . $e->getMessage());
                }
            }

            $address_file = $e911_dir . '/ext_' . $extension . '.json';  // Changed to match dialplan

            if (file_put_contents($address_file, json_encode($address_data, JSON_PRETTY_PRINT))) {
                // Set proper permissions for Asterisk
                chmod($address_file, 0644);

                // Update user file with E911 flag
                $user_file = $users_dir . '/user_' . $extension . '.json';
                if (file_exists($user_file)) {
                    $user_data = json_decode(file_get_contents($user_file), true);
                    $user_data['e911_configured'] = true;
                    $user_data['e911_updated_at'] = date('Y-m-d H:i:s');
                    file_put_contents($user_file, json_encode($user_data, JSON_PRETTY_PRINT));
                }

                logE911("E911 address saved for extension {$extension} by " . ($is_admin ? 'admin' : 'user'));

                echo json_encode([
                    'success' => true,
                    'message' => 'E911 address saved successfully. You can now dial 911.',
                    'address' => $address_data,
                    'can_dial_911' => true
                ]);
            } else {
                logE911("Failed to save E911 address for extension {$extension}");
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to save E911 address'
                ]);
            }
            break;

        // ==================== DELETE E911 ADDRESS ====================
        case 'delete_address':
            $extension = $_POST['extension'] ?? $user_extension;

            // Only admins can delete
            if (!$is_admin) {
                echo json_encode(['success' => false, 'message' => 'Permission denied']);
                exit;
            }

            $address_file = $e911_dir . '/ext_' . $extension . '.json';  // Changed to match dialplan

            if (file_exists($address_file)) {
                // Load address to get Twilio SID
                $address = json_decode(file_get_contents($address_file), true);

                // Try to delete from Twilio
                if (!empty($address['twilio_address_sid'])) {
                    try {
                        $twilio = new TwilioIntegration();
                        $twilio->deleteEmergencyAddress($address['twilio_address_sid']);
                    } catch (Exception $e) {
                        error_log("Twilio E911 deletion failed: " . $e->getMessage());
                    }
                }

                unlink($address_file);

                // Update user file
                $user_file = $users_dir . '/user_' . $extension . '.json';
                if (file_exists($user_file)) {
                    $user_data = json_decode(file_get_contents($user_file), true);
                    $user_data['e911_configured'] = false;
                    file_put_contents($user_file, json_encode($user_data, JSON_PRETTY_PRINT));
                }

                echo json_encode([
                    'success' => true,
                    'message' => 'E911 address deleted successfully'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'E911 address not found'
                ]);
            }
            break;

        // ==================== LIST ALL E911 ADDRESSES (Admin only) ====================
        case 'list_all':
            if (!$is_admin) {
                echo json_encode(['success' => false, 'message' => 'Permission denied']);
                exit;
            }

            $addresses = [];
            $files = glob($e911_dir . '/ext_*.json');  // Changed to match dialplan

            foreach ($files as $file) {
                $address = json_decode(file_get_contents($file), true);
                if ($address) {
                    // Get user info
                    $user_file = $users_dir . '/user_' . $address['extension'] . '.json';
                    if (file_exists($user_file)) {
                        $user = json_decode(file_get_contents($user_file), true);
                        $address['user_name'] = $user['full_name'] ?? $user['username'] ?? 'Unknown';
                        $address['user_email'] = $user['email'] ?? '';
                    }
                    $addresses[] = $address;
                }
            }

            echo json_encode([
                'success' => true,
                'addresses' => $addresses,
                'total' => count($addresses)
            ]);
            break;

        // ==================== VALIDATE ADDRESS ====================
        case 'validate_address':
            if (!$is_admin) {
                echo json_encode(['success' => false, 'message' => 'Permission denied']);
                exit;
            }

            $extension = $_POST['extension'] ?? null;
            if (!$extension) {
                echo json_encode(['success' => false, 'message' => 'Extension required']);
                exit;
            }

            $address_file = $e911_dir . '/ext_' . $extension . '.json';  // Changed to match dialplan

            if (!file_exists($address_file)) {
                echo json_encode(['success' => false, 'message' => 'Address not found']);
                exit;
            }

            $address = json_decode(file_get_contents($address_file), true);

            // Validate with Twilio
            try {
                $twilio = new TwilioIntegration();

                if ($address['twilio_address_sid']) {
                    // Get validation status
                    $twilioAddress = $twilio->getEmergencyAddress($address['twilio_address_sid']);
                    $address['validated'] = $twilioAddress['validated'] ?? false;
                } else {
                    // Create new address in Twilio
                    $twilioAddress = $twilio->createEmergencyAddress(
                        $address['friendly_name'] ?: "E911 - Ext {$extension}",
                        $address['street'],
                        $address['city'],
                        $address['state'],
                        $address['postal_code'],
                        $address['country']
                    );

                    $address['twilio_address_sid'] = $twilioAddress['sid'];
                    $address['validated'] = $twilioAddress['validated'] ?? false;
                }

                file_put_contents($address_file, json_encode($address, JSON_PRETTY_PRINT));

                echo json_encode([
                    'success' => true,
                    'validated' => $address['validated'],
                    'address' => $address
                ]);
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Validation failed: ' . $e->getMessage()
                ]);
            }
            break;

        // ==================== GET STATS (Admin only) ====================
        case 'get_stats':
            if (!$is_admin) {
                echo json_encode(['success' => false, 'message' => 'Permission denied']);
                exit;
            }

            $files = glob($e911_dir . '/ext_*.json');  // Changed to match dialplan
            $total = count($files);
            $validated = 0;
            $enabled = 0;

            foreach ($files as $file) {
                $address = json_decode(file_get_contents($file), true);
                if ($address) {
                    if ($address['validated']) $validated++;
                    if ($address['emergency_enabled']) $enabled++;
                }
            }

            // Get total users
            $user_files = glob($users_dir . '/user_*.json');
            $total_users = count($user_files);

            echo json_encode([
                'success' => true,
                'stats' => [
                    'total_addresses' => $total,
                    'validated' => $validated,
                    'enabled' => $enabled,
                    'total_users' => $total_users,
                    'coverage_percent' => $total_users > 0 ? round(($total / $total_users) * 100, 1) : 0
                ]
            ]);
            break;

        default:
            echo json_encode([
                'success' => false,
                'message' => 'Unknown action',
                'available_actions' => [
                    'get_address' => 'Get E911 address for extension',
                    'save_address' => 'Save E911 address',
                    'delete_address' => 'Delete E911 address (admin only)',
                    'list_all' => 'List all E911 addresses (admin only)',
                    'validate_address' => 'Validate address with Twilio (admin only)',
                    'get_stats' => 'Get E911 statistics (admin only)'
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
