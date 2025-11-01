<?php
/**
 * FlexPBX Licensing System API
 * Generate, validate, and manage software licenses with payment integration
 *
 * @requires PHP 8.0+
 * @recommended PHP 8.1 or 8.2
 */

// Check PHP version (minimum 8.0)
if (version_compare(PHP_VERSION, '8.0.0', '<')) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'PHP 8.0 or higher required',
        'current_version' => PHP_VERSION,
        'minimum_version' => '8.0.0',
        'recommended_versions' => ['8.1', '8.2']
    ]);
    exit;
}

session_start();
header('Content-Type: application/json');

$config_file = '/home/flexpbxuser/config/licensing_config.json';
$licenses_file = '/home/flexpbxuser/config/licenses.json';
$installations_file = '/home/flexpbxuser/config/installations.json';

// Ensure config directory exists
@mkdir(dirname($config_file), 0755, true);

// Initialize configuration
if (!file_exists($config_file)) {
    $default_config = [
        'encryption_key' => bin2hex(random_bytes(32)),
        'license_types' => [
            'trial' => [
                'name' => 'Trial License',
                'duration_days' => 30,
                'max_extensions' => 10,
                'max_trunks' => 2,
                'features' => ['basic_pbx', 'voicemail', 'call_forwarding'],
                'price' => 0,
                'billing_cycle' => 'once',
                'renewable' => false
            ],
            'starter_monthly' => [
                'name' => 'Starter Plan (Monthly)',
                'duration_days' => 30,
                'max_extensions' => 25,
                'max_trunks' => 5,
                'features' => ['basic_pbx', 'voicemail', 'call_forwarding', 'ivr', 'call_recording'],
                'price' => 29.99,
                'billing_cycle' => 'monthly',
                'renewable' => true,
                'auto_renew' => true
            ],
            'starter' => [
                'name' => 'Starter Plan (Annual)',
                'duration_days' => 365,
                'max_extensions' => 25,
                'max_trunks' => 5,
                'features' => ['basic_pbx', 'voicemail', 'call_forwarding', 'ivr', 'call_recording'],
                'price' => 299.00,
                'billing_cycle' => 'yearly',
                'renewable' => true,
                'auto_renew' => true
            ],
            'professional_monthly' => [
                'name' => 'Professional Plan (Monthly)',
                'duration_days' => 30,
                'max_extensions' => 100,
                'max_trunks' => 15,
                'features' => ['basic_pbx', 'voicemail', 'call_forwarding', 'ivr', 'call_recording', 'queues', 'conference', 'analytics'],
                'price' => 89.99,
                'billing_cycle' => 'monthly',
                'renewable' => true,
                'auto_renew' => true
            ],
            'professional' => [
                'name' => 'Professional Plan (Annual)',
                'duration_days' => 365,
                'max_extensions' => 100,
                'max_trunks' => 15,
                'features' => ['basic_pbx', 'voicemail', 'call_forwarding', 'ivr', 'call_recording', 'queues', 'conference', 'analytics'],
                'price' => 899.00,
                'billing_cycle' => 'yearly',
                'renewable' => true,
                'auto_renew' => true
            ],
            'enterprise_monthly' => [
                'name' => 'Enterprise Plan (Monthly)',
                'duration_days' => 30,
                'max_extensions' => -1, // Unlimited
                'max_trunks' => -1, // Unlimited
                'features' => ['all'],
                'price' => 249.99,
                'billing_cycle' => 'monthly',
                'renewable' => true,
                'auto_renew' => true
            ],
            'enterprise' => [
                'name' => 'Enterprise Plan (Annual)',
                'duration_days' => 365,
                'max_extensions' => -1, // Unlimited
                'max_trunks' => -1, // Unlimited
                'features' => ['all'],
                'price' => 2499.00,
                'billing_cycle' => 'yearly',
                'renewable' => true,
                'auto_renew' => true
            ],
            'lifetime' => [
                'name' => 'Lifetime License',
                'duration_days' => -1, // Never expires
                'max_extensions' => -1,
                'max_trunks' => -1,
                'features' => ['all'],
                'price' => 9999.00,
                'renewable' => false
            ]
        ]
    ];
    file_put_contents($config_file, json_encode($default_config, JSON_PRETTY_PRINT));
}

// Initialize licenses storage
if (!file_exists($licenses_file)) {
    file_put_contents($licenses_file, json_encode([], JSON_PRETTY_PRINT));
}

// Initialize installations storage
if (!file_exists($installations_file)) {
    file_put_contents($installations_file, json_encode([], JSON_PRETTY_PRINT));
}

// Load data
function loadConfig() {
    global $config_file;
    return json_decode(file_get_contents($config_file), true);
}

function loadLicenses() {
    global $licenses_file;
    return json_decode(file_get_contents($licenses_file), true) ?: [];
}

function saveLicenses($licenses) {
    global $licenses_file;
    file_put_contents($licenses_file, json_encode($licenses, JSON_PRETTY_PRINT));
}

function loadInstallations() {
    global $installations_file;
    return json_decode(file_get_contents($installations_file), true) ?: [];
}

function saveInstallations($installations) {
    global $installations_file;
    file_put_contents($installations_file, json_encode($installations, JSON_PRETTY_PRINT));
}

// Get action from request
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'generate':
        generateLicense();
        break;

    case 'validate':
        validateLicense();
        break;

    case 'activate':
        activateLicense();
        break;

    case 'deactivate':
        deactivateLicense();
        break;

    case 'check_installation':
        checkInstallation();
        break;

    case 'get_license_info':
        getLicenseInfo();
        break;

    case 'list_licenses':
        listLicenses();
        break;

    case 'revoke':
        revokeLicense();
        break;

    case 'renew':
        renewLicense();
        break;

    case 'get_plans':
        getPlans();
        break;

    case 'transfer':
        transferLicense();
        break;

    case 'get_stats':
        getStats();
        break;

    default:
        echo json_encode([
            'success' => false,
            'error' => 'Invalid action',
            'available_actions' => [
                'generate', 'validate', 'activate', 'deactivate', 'check_installation',
                'get_license_info', 'list_licenses', 'revoke', 'renew', 'get_plans', 'transfer', 'get_stats'
            ]
        ]);
}

/**
 * Generate a new license key
 */
function generateLicense() {
    $license_type = $_POST['license_type'] ?? 'trial';
    $customer_email = $_POST['customer_email'] ?? '';
    $customer_name = $_POST['customer_name'] ?? '';
    $order_id = $_POST['order_id'] ?? '';
    $payment_method = $_POST['payment_method'] ?? 'manual';

    if (empty($customer_email)) {
        echo json_encode(['success' => false, 'error' => 'Customer email required']);
        return;
    }

    $config = loadConfig();
    $licenses = loadLicenses();

    if (!isset($config['license_types'][$license_type])) {
        echo json_encode(['success' => false, 'error' => 'Invalid license type']);
        return;
    }

    $license_info = $config['license_types'][$license_type];

    // Generate license key
    $license_key = generateLicenseKey($license_type);

    // Calculate expiry
    $issued_at = time();
    $duration = $license_info['duration_days'];
    $expires_at = ($duration === -1) ? -1 : ($issued_at + ($duration * 86400));

    // Create license record
    $license_data = [
        'license_key' => $license_key,
        'license_type' => $license_type,
        'license_name' => $license_info['name'],
        'customer_email' => $customer_email,
        'customer_name' => $customer_name,
        'order_id' => $order_id,
        'payment_method' => $payment_method,
        'issued_at' => $issued_at,
        'expires_at' => $expires_at,
        'status' => 'active',
        'max_extensions' => $license_info['max_extensions'],
        'max_trunks' => $license_info['max_trunks'],
        'features' => $license_info['features'],
        'renewable' => $license_info['renewable'],
        'installations' => [],
        'max_installations' => 1, // Can be increased for enterprise
        'activation_count' => 0,
        'last_validation' => null,
        'notes' => ''
    ];

    $licenses[$license_key] = $license_data;
    saveLicenses($licenses);

    // Log generation
    logLicenseActivity($license_key, 'generated', [
        'type' => $license_type,
        'customer' => $customer_email
    ]);

    echo json_encode([
        'success' => true,
        'license_key' => $license_key,
        'license_type' => $license_type,
        'expires_at' => $expires_at,
        'expires_in_days' => $duration,
        'message' => 'License generated successfully'
    ]);
}

/**
 * Validate a license key
 */
function validateLicense() {
    $license_key = $_POST['license_key'] ?? $_GET['license_key'] ?? '';
    $installation_id = $_POST['installation_id'] ?? $_GET['installation_id'] ?? '';

    if (empty($license_key)) {
        echo json_encode(['success' => false, 'error' => 'License key required', 'valid' => false]);
        return;
    }

    $licenses = loadLicenses();

    if (!isset($licenses[$license_key])) {
        echo json_encode(['success' => false, 'error' => 'Invalid license key', 'valid' => false]);
        return;
    }

    $license = $licenses[$license_key];

    // Check if revoked
    if ($license['status'] === 'revoked') {
        echo json_encode(['success' => false, 'error' => 'License has been revoked', 'valid' => false, 'status' => 'revoked']);
        return;
    }

    // Check expiry
    $now = time();
    $is_expired = false;
    if ($license['expires_at'] !== -1 && $now > $license['expires_at']) {
        $is_expired = true;
        $licenses[$license_key]['status'] = 'expired';
        saveLicenses($licenses);
    }

    // Check if installation_id is registered
    $installation_registered = false;
    if ($installation_id && in_array($installation_id, $license['installations'])) {
        $installation_registered = true;
    }

    // Update last validation
    $licenses[$license_key]['last_validation'] = $now;
    saveLicenses($licenses);

    echo json_encode([
        'success' => true,
        'valid' => !$is_expired && $license['status'] === 'active',
        'license_key' => $license_key,
        'license_type' => $license['license_type'],
        'customer_name' => $license['customer_name'],
        'status' => $is_expired ? 'expired' : $license['status'],
        'expires_at' => $license['expires_at'],
        'days_remaining' => $license['expires_at'] === -1 ? -1 : max(0, ceil(($license['expires_at'] - $now) / 86400)),
        'max_extensions' => $license['max_extensions'],
        'max_trunks' => $license['max_trunks'],
        'features' => $license['features'],
        'installation_registered' => $installation_registered,
        'installations_count' => count($license['installations']),
        'max_installations' => $license['max_installations']
    ]);
}

/**
 * Activate license for an installation
 */
function activateLicense() {
    $license_key = $_POST['license_key'] ?? '';
    $installation_id = $_POST['installation_id'] ?? '';
    $server_info = $_POST['server_info'] ?? '';

    if (empty($license_key) || empty($installation_id)) {
        echo json_encode(['success' => false, 'error' => 'License key and installation ID required']);
        return;
    }

    $licenses = loadLicenses();
    $installations = loadInstallations();

    if (!isset($licenses[$license_key])) {
        echo json_encode(['success' => false, 'error' => 'Invalid license key']);
        return;
    }

    $license = $licenses[$license_key];

    // Check if already activated on this installation
    if (in_array($installation_id, $license['installations'])) {
        echo json_encode([
            'success' => true,
            'message' => 'License already activated on this installation',
            'activated' => true
        ]);
        return;
    }

    // Check max installations
    if (count($license['installations']) >= $license['max_installations']) {
        echo json_encode([
            'success' => false,
            'error' => 'Maximum installations reached for this license',
            'max_installations' => $license['max_installations'],
            'current_installations' => count($license['installations'])
        ]);
        return;
    }

    // Add installation
    $licenses[$license_key]['installations'][] = $installation_id;
    $licenses[$license_key]['activation_count']++;
    saveLicenses($licenses);

    // Record installation details
    $installations[$installation_id] = [
        'installation_id' => $installation_id,
        'license_key' => $license_key,
        'server_info' => $server_info,
        'activated_at' => time(),
        'last_check' => time(),
        'status' => 'active'
    ];
    saveInstallations($installations);

    logLicenseActivity($license_key, 'activated', [
        'installation_id' => $installation_id
    ]);

    echo json_encode([
        'success' => true,
        'activated' => true,
        'message' => 'License activated successfully',
        'installation_id' => $installation_id
    ]);
}

/**
 * Deactivate license from an installation
 */
function deactivateLicense() {
    $license_key = $_POST['license_key'] ?? '';
    $installation_id = $_POST['installation_id'] ?? '';

    if (empty($license_key) || empty($installation_id)) {
        echo json_encode(['success' => false, 'error' => 'License key and installation ID required']);
        return;
    }

    $licenses = loadLicenses();
    $installations = loadInstallations();

    if (!isset($licenses[$license_key])) {
        echo json_encode(['success' => false, 'error' => 'Invalid license key']);
        return;
    }

    // Remove installation
    $key = array_search($installation_id, $licenses[$license_key]['installations']);
    if ($key !== false) {
        unset($licenses[$license_key]['installations'][$key]);
        $licenses[$license_key]['installations'] = array_values($licenses[$license_key]['installations']);
        saveLicenses($licenses);

        // Update installation record
        if (isset($installations[$installation_id])) {
            $installations[$installation_id]['status'] = 'deactivated';
            $installations[$installation_id]['deactivated_at'] = time();
            saveInstallations($installations);
        }

        logLicenseActivity($license_key, 'deactivated', [
            'installation_id' => $installation_id
        ]);

        echo json_encode([
            'success' => true,
            'message' => 'License deactivated successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Installation not found for this license'
        ]);
    }
}

/**
 * Check installation status
 */
function checkInstallation() {
    $installation_id = $_GET['installation_id'] ?? '';

    if (empty($installation_id)) {
        echo json_encode(['success' => false, 'error' => 'Installation ID required']);
        return;
    }

    $installations = loadInstallations();

    if (!isset($installations[$installation_id])) {
        echo json_encode([
            'success' => false,
            'error' => 'Installation not found',
            'registered' => false
        ]);
        return;
    }

    $installation = $installations[$installation_id];

    // Update last check
    $installations[$installation_id]['last_check'] = time();
    saveInstallations($installations);

    echo json_encode([
        'success' => true,
        'registered' => true,
        'installation' => $installation
    ]);
}

/**
 * Get license information
 */
function getLicenseInfo() {
    $license_key = $_GET['license_key'] ?? '';

    if (empty($license_key)) {
        echo json_encode(['success' => false, 'error' => 'License key required']);
        return;
    }

    $licenses = loadLicenses();

    if (!isset($licenses[$license_key])) {
        echo json_encode(['success' => false, 'error' => 'License not found']);
        return;
    }

    echo json_encode([
        'success' => true,
        'license' => $licenses[$license_key]
    ]);
}

/**
 * List all licenses (admin only)
 */
function listLicenses() {
    // TODO: Add authentication check
    $licenses = loadLicenses();

    echo json_encode([
        'success' => true,
        'licenses' => array_values($licenses),
        'count' => count($licenses)
    ]);
}

/**
 * Revoke a license
 */
function revokeLicense() {
    $license_key = $_POST['license_key'] ?? '';
    $reason = $_POST['reason'] ?? '';

    if (empty($license_key)) {
        echo json_encode(['success' => false, 'error' => 'License key required']);
        return;
    }

    $licenses = loadLicenses();

    if (!isset($licenses[$license_key])) {
        echo json_encode(['success' => false, 'error' => 'License not found']);
        return;
    }

    $licenses[$license_key]['status'] = 'revoked';
    $licenses[$license_key]['revoked_at'] = time();
    $licenses[$license_key]['revoke_reason'] = $reason;
    saveLicenses($licenses);

    logLicenseActivity($license_key, 'revoked', ['reason' => $reason]);

    echo json_encode([
        'success' => true,
        'message' => 'License revoked successfully'
    ]);
}

/**
 * Renew a license
 */
function renewLicense() {
    $license_key = $_POST['license_key'] ?? '';
    $payment_id = $_POST['payment_id'] ?? '';

    if (empty($license_key)) {
        echo json_encode(['success' => false, 'error' => 'License key required']);
        return;
    }

    $licenses = loadLicenses();
    $config = loadConfig();

    if (!isset($licenses[$license_key])) {
        echo json_encode(['success' => false, 'error' => 'License not found']);
        return;
    }

    $license = $licenses[$license_key];

    if (!$license['renewable']) {
        echo json_encode(['success' => false, 'error' => 'License is not renewable']);
        return;
    }

    $license_type = $license['license_type'];
    $duration = $config['license_types'][$license_type]['duration_days'];

    // Extend expiry from current expiry or now, whichever is later
    $base_time = max(time(), $license['expires_at']);
    $new_expiry = $base_time + ($duration * 86400);

    $licenses[$license_key]['expires_at'] = $new_expiry;
    $licenses[$license_key]['status'] = 'active';
    $licenses[$license_key]['renewed_at'] = time();
    $licenses[$license_key]['renewal_payment_id'] = $payment_id;
    saveLicenses($licenses);

    logLicenseActivity($license_key, 'renewed', ['new_expiry' => $new_expiry]);

    echo json_encode([
        'success' => true,
        'message' => 'License renewed successfully',
        'new_expiry' => $new_expiry,
        'days_added' => $duration
    ]);
}

/**
 * Get available plans
 */
function getPlans() {
    $config = loadConfig();

    echo json_encode([
        'success' => true,
        'plans' => $config['license_types']
    ]);
}

/**
 * Transfer license to another customer
 */
function transferLicense() {
    $license_key = $_POST['license_key'] ?? '';
    $new_email = $_POST['new_email'] ?? '';
    $new_name = $_POST['new_name'] ?? '';

    if (empty($license_key) || empty($new_email)) {
        echo json_encode(['success' => false, 'error' => 'License key and new email required']);
        return;
    }

    $licenses = loadLicenses();

    if (!isset($licenses[$license_key])) {
        echo json_encode(['success' => false, 'error' => 'License not found']);
        return;
    }

    $old_email = $licenses[$license_key]['customer_email'];

    $licenses[$license_key]['customer_email'] = $new_email;
    $licenses[$license_key]['customer_name'] = $new_name;
    $licenses[$license_key]['transferred_at'] = time();
    $licenses[$license_key]['previous_owner'] = $old_email;
    saveLicenses($licenses);

    logLicenseActivity($license_key, 'transferred', [
        'from' => $old_email,
        'to' => $new_email
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'License transferred successfully'
    ]);
}

/**
 * Get licensing statistics
 */
function getStats() {
    $licenses = loadLicenses();
    $installations = loadInstallations();

    $stats = [
        'total_licenses' => count($licenses),
        'active_licenses' => 0,
        'expired_licenses' => 0,
        'revoked_licenses' => 0,
        'total_installations' => count($installations),
        'active_installations' => 0,
        'by_type' => []
    ];

    foreach ($licenses as $license) {
        // Count by status
        if ($license['status'] === 'active') $stats['active_licenses']++;
        if ($license['status'] === 'expired') $stats['expired_licenses']++;
        if ($license['status'] === 'revoked') $stats['revoked_licenses']++;

        // Count by type
        $type = $license['license_type'];
        if (!isset($stats['by_type'][$type])) {
            $stats['by_type'][$type] = 0;
        }
        $stats['by_type'][$type]++;
    }

    foreach ($installations as $installation) {
        if ($installation['status'] === 'active') {
            $stats['active_installations']++;
        }
    }

    echo json_encode([
        'success' => true,
        'stats' => $stats
    ]);
}

/**
 * Generate a unique license key
 */
function generateLicenseKey($type) {
    $prefix = strtoupper(substr($type, 0, 3));
    $random = strtoupper(bin2hex(random_bytes(12)));

    // Format: XXX-XXXX-XXXX-XXXX-XXXX-XXXX
    $key = $prefix . '-' .
           substr($random, 0, 4) . '-' .
           substr($random, 4, 4) . '-' .
           substr($random, 8, 4) . '-' .
           substr($random, 12, 4) . '-' .
           substr($random, 16, 4);

    return $key;
}

/**
 * Log license activity
 */
function logLicenseActivity($license_key, $action, $details = []) {
    $log_file = '/home/flexpbxuser/logs/license_activity.log';
    @mkdir(dirname($log_file), 0755, true);

    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'license_key' => $license_key,
        'action' => $action,
        'details' => $details,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ];

    file_put_contents($log_file, json_encode($log_entry) . "\n", FILE_APPEND);
}
?>
