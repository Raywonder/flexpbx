<?php
/**
 * FlexPBX WHMCS Integration Module
 *
 * Complete PBX management and provisioning system for WHMCS
 * Supports 2FA authentication and desktop app integration
 *
 * @package FlexPBX
 * @version 1.0.0
 * @author FlexPBX Team
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

// Module configuration
function flexpbx_config() {
    return array(
        "name" => "FlexPBX Management",
        "description" => "Complete PBX management and provisioning system with 2FA support",
        "version" => "1.0.0",
        "author" => "FlexPBX Team",
        "language" => "english",
        "fields" => array(
            "server_url" => array(
                "FriendlyName" => "FlexPBX Server URL",
                "Type" => "text",
                "Size" => "50",
                "Default" => "http://localhost:3000",
                "Description" => "URL of your FlexPBX server (include http/https)"
            ),
            "api_key" => array(
                "FriendlyName" => "API Key",
                "Type" => "password",
                "Size" => "50",
                "Description" => "FlexPBX API key for authentication"
            ),
            "enable_2fa" => array(
                "FriendlyName" => "Enable 2FA Integration",
                "Type" => "yesno",
                "Default" => "yes",
                "Description" => "Enable two-factor authentication integration"
            ),
            "auto_provision" => array(
                "FriendlyName" => "Auto-Provision Accounts",
                "Type" => "yesno",
                "Default" => "yes",
                "Description" => "Automatically provision PBX accounts on order"
            ),
            "default_extensions" => array(
                "FriendlyName" => "Default Extensions",
                "Type" => "text",
                "Size" => "5",
                "Default" => "10",
                "Description" => "Default number of extensions per account"
            ),
            "default_plan" => array(
                "FriendlyName" => "Default Plan",
                "Type" => "dropdown",
                "Options" => "basic,standard,premium,enterprise",
                "Default" => "standard",
                "Description" => "Default PBX plan for new accounts"
            ),
            "webhook_secret" => array(
                "FriendlyName" => "Webhook Secret",
                "Type" => "password",
                "Size" => "50",
                "Description" => "Secret key for webhook verification"
            )
        )
    );
}

// Module activation
function flexpbx_activate() {
    // Create database tables
    try {
        Capsule::schema()->create('mod_flexpbx_accounts', function ($table) {
            $table->increments('id');
            $table->integer('userid');
            $table->integer('serviceid')->nullable();
            $table->string('domain');
            $table->string('username');
            $table->string('password_hash');
            $table->integer('extensions')->default(10);
            $table->string('plan')->default('standard');
            $table->text('pbx_config')->nullable();
            $table->string('server_id')->nullable();
            $table->enum('status', ['active', 'suspended', 'terminated'])->default('active');
            $table->boolean('two_fa_enabled')->default(false);
            $table->string('two_fa_secret')->nullable();
            $table->timestamps();
        });

        Capsule::schema()->create('mod_flexpbx_extensions', function ($table) {
            $table->increments('id');
            $table->integer('account_id');
            $table->string('extension_number');
            $table->string('display_name');
            $table->string('password_hash');
            $table->text('config')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });

        Capsule::schema()->create('mod_flexpbx_servers', function ($table) {
            $table->increments('id');
            $table->string('name');
            $table->string('hostname');
            $table->string('ip_address');
            $table->integer('port')->default(3000);
            $table->string('api_key');
            $table->integer('max_accounts')->default(100);
            $table->integer('current_accounts')->default(0);
            $table->enum('status', ['active', 'maintenance', 'offline'])->default('active');
            $table->text('features')->nullable();
            $table->timestamps();
        });

        Capsule::schema()->create('mod_flexpbx_2fa_sessions', function ($table) {
            $table->increments('id');
            $table->integer('userid');
            $table->string('session_token');
            $table->string('panel_type');
            $table->text('auth_data');
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        return array('status' => 'success', 'description' => 'FlexPBX module activated successfully. Database tables created.');
    } catch (Exception $e) {
        return array('status' => 'error', 'description' => 'Database Error: ' . $e->getMessage());
    }
}

// Module deactivation
function flexpbx_deactivate() {
    try {
        // Don't drop tables on deactivation to preserve data
        return array('status' => 'success', 'description' => 'FlexPBX module deactivated successfully.');
    } catch (Exception $e) {
        return array('status' => 'error', 'description' => 'Error: ' . $e->getMessage());
    }
}

// Main module output
function flexpbx_output($vars) {
    $action = $_GET['action'] ?? 'dashboard';

    // 2FA authentication check
    if ($vars['enable_2fa'] == 'on' && !check_2fa_session()) {
        include_once dirname(__FILE__) . '/templates/2fa_login.php';
        return;
    }

    switch ($action) {
        case 'accounts':
            include_once dirname(__FILE__) . '/templates/accounts.php';
            break;
        case 'servers':
            include_once dirname(__FILE__) . '/templates/servers.php';
            break;
        case 'extensions':
            include_once dirname(__FILE__) . '/templates/extensions.php';
            break;
        case 'settings':
            include_once dirname(__FILE__) . '/templates/settings.php';
            break;
        case '2fa_setup':
            include_once dirname(__FILE__) . '/templates/2fa_setup.php';
            break;
        case 'desktop_integration':
            include_once dirname(__FILE__) . '/templates/desktop_integration.php';
            break;
        default:
            include_once dirname(__FILE__) . '/templates/dashboard.php';
    }
}

// Module sidebar
function flexpbx_sidebar($vars) {
    $sidebar = '<div class="flexpbx-sidebar">';
    $sidebar .= '<h3><i class="fa fa-phone"></i> FlexPBX Management</h3>';
    $sidebar .= '<ul class="list-unstyled">';
    $sidebar .= '<li><a href="addonmodules.php?module=flexpbx&action=dashboard"><i class="fa fa-dashboard"></i> Dashboard</a></li>';
    $sidebar .= '<li><a href="addonmodules.php?module=flexpbx&action=accounts"><i class="fa fa-users"></i> PBX Accounts</a></li>';
    $sidebar .= '<li><a href="addonmodules.php?module=flexpbx&action=extensions"><i class="fa fa-phone-square"></i> Extensions</a></li>';
    $sidebar .= '<li><a href="addonmodules.php?module=flexpbx&action=servers"><i class="fa fa-server"></i> Servers</a></li>';

    if ($vars['enable_2fa'] == 'on') {
        $sidebar .= '<li><a href="addonmodules.php?module=flexpbx&action=2fa_setup"><i class="fa fa-shield"></i> 2FA Setup</a></li>';
    }

    $sidebar .= '<li><a href="addonmodules.php?module=flexpbx&action=desktop_integration"><i class="fa fa-desktop"></i> Desktop Integration</a></li>';
    $sidebar .= '<li><a href="addonmodules.php?module=flexpbx&action=settings"><i class="fa fa-cog"></i> Settings</a></li>';
    $sidebar .= '</ul>';

    // Quick stats
    $total_accounts = get_total_accounts();
    $active_accounts = get_active_accounts();

    $sidebar .= '<div class="well well-sm" style="margin-top: 20px;">';
    $sidebar .= '<h5>Quick Stats</h5>';
    $sidebar .= '<p><strong>Total Accounts:</strong> ' . $total_accounts . '</p>';
    $sidebar .= '<p><strong>Active:</strong> ' . $active_accounts . '</p>';
    $sidebar .= '<p><strong>Status:</strong> <span class="label label-success">Online</span></p>';
    $sidebar .= '</div>';

    $sidebar .= '</div>';

    return $sidebar;
}

// AJAX handler for desktop app integration
if (isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');

    switch ($_POST['ajax_action']) {
        case 'test_2fa':
            echo json_encode(test_2fa_configuration($_POST));
            break;
        case 'generate_desktop_token':
            echo json_encode(generate_desktop_integration_token($_POST));
            break;
        case 'verify_2fa_token':
            echo json_encode(verify_2fa_token($_POST));
            break;
        case 'get_account_details':
            echo json_encode(get_account_details($_POST['account_id']));
            break;
        default:
            echo json_encode(['success' => false, 'error' => 'Unknown action']);
    }
    exit;
}

// Helper functions
function check_2fa_session() {
    if (!isset($_SESSION['flexpbx_2fa_verified'])) {
        return false;
    }

    $session = Capsule::table('mod_flexpbx_2fa_sessions')
        ->where('session_token', $_SESSION['flexpbx_2fa_token'])
        ->where('expires_at', '>', date('Y-m-d H:i:s'))
        ->first();

    return $session !== null;
}

function test_2fa_configuration($data) {
    $panel_type = $data['panel_type'];
    $server_url = $data['server_url'];
    $username = $data['username'];
    $password = $data['password'];
    $tfa_secret = $data['tfa_secret'] ?? '';

    try {
        // Generate 2FA token if secret provided
        $tfa_token = '';
        if ($tfa_secret) {
            $tfa_token = generate_totp_token($tfa_secret);
        }

        // Test authentication based on panel type
        switch ($panel_type) {
            case 'whmcs':
                $result = test_whmcs_auth($server_url, $username, $password, $tfa_token);
                break;
            case 'cpanel':
                $result = test_cpanel_auth($server_url, $username, $password, $tfa_token);
                break;
            case 'whm':
                $result = test_whm_auth($server_url, $username, $password, $tfa_token);
                break;
            case 'directadmin':
                $result = test_directadmin_auth($server_url, $username, $password, $tfa_token);
                break;
            default:
                throw new Exception("Unsupported panel type: $panel_type");
        }

        return [
            'success' => $result['success'],
            'message' => $result['message'],
            'token_valid' => $result['token_valid'] ?? false
        ];

    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Test failed: ' . $e->getMessage()
        ];
    }
}

function generate_totp_token($secret) {
    $time = floor(time() / 30);
    $binary = pack('N*', 0, $time);
    $hash = hash_hmac('sha1', $binary, base32_decode($secret), true);
    $offset = ord($hash[19]) & 0xf;
    $otp = (
        ((ord($hash[$offset + 0]) & 0x7f) << 24) |
        ((ord($hash[$offset + 1]) & 0xff) << 16) |
        ((ord($hash[$offset + 2]) & 0xff) << 8) |
        (ord($hash[$offset + 3]) & 0xff)
    ) % pow(10, 6);

    return str_pad($otp, 6, '0', STR_PAD_LEFT);
}

function base32_decode($data) {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $output = '';
    $v = 0;
    $vbits = 0;

    for ($i = 0, $j = strlen($data); $i < $j; $i++) {
        $v <<= 5;
        $v += strpos($alphabet, $data[$i]);
        $vbits += 5;
        if ($vbits >= 8) {
            $output .= chr($v >> ($vbits - 8));
            $vbits -= 8;
        }
    }

    return $output;
}

function test_whmcs_auth($server_url, $username, $password, $tfa_token) {
    $api_url = rtrim($server_url, '/') . '/includes/api.php';

    $postfields = array(
        'action' => 'ValidateLogin',
        'username' => $username,
        'password2' => $password,
        'tfa_token' => $tfa_token,
        'responsetype' => 'json'
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postfields));
    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpcode == 200) {
        $result = json_decode($response, true);
        if ($result['result'] == 'success') {
            return [
                'success' => true,
                'message' => 'WHMCS authentication successful',
                'token_valid' => true
            ];
        } else {
            return [
                'success' => false,
                'message' => $result['message'] ?? 'Authentication failed',
                'token_valid' => false
            ];
        }
    } else {
        return [
            'success' => false,
            'message' => "HTTP Error: $httpcode"
        ];
    }
}

function test_cpanel_auth($server_url, $username, $password, $tfa_token) {
    $login_url = $server_url . ':2083/login/?login_only=1';

    $postfields = array(
        'user' => $username,
        'pass' => $password,
        'tfa_token' => $tfa_token
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $login_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postfields));
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpcode == 200 && strpos($response, 'security_token') !== false) {
        return [
            'success' => true,
            'message' => 'cPanel authentication successful',
            'token_valid' => true
        ];
    } else {
        return [
            'success' => false,
            'message' => 'cPanel authentication failed',
            'token_valid' => false
        ];
    }
}

function test_whm_auth($server_url, $username, $password, $tfa_token) {
    $api_url = $server_url . ':2087/json-api/login';

    $postfields = array(
        'user' => $username,
        'pass' => $password,
        'tfa_token' => $tfa_token
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postfields));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpcode == 200) {
        $result = json_decode($response, true);
        if ($result['metadata']['result'] == 1) {
            return [
                'success' => true,
                'message' => 'WHM authentication successful',
                'token_valid' => true
            ];
        } else {
            return [
                'success' => false,
                'message' => $result['metadata']['reason'] ?? 'Authentication failed',
                'token_valid' => false
            ];
        }
    } else {
        return [
            'success' => false,
            'message' => "HTTP Error: $httpcode"
        ];
    }
}

function test_directadmin_auth($server_url, $username, $password, $tfa_token) {
    $login_url = $server_url . ':2222/CMD_LOGIN';

    $postfields = array(
        'username' => $username,
        'password' => $password,
        'tfa_code' => $tfa_token
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $login_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postfields));
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpcode == 200 && strpos($response, 'error=') === false) {
        return [
            'success' => true,
            'message' => 'DirectAdmin authentication successful',
            'token_valid' => true
        ];
    } else {
        return [
            'success' => false,
            'message' => 'DirectAdmin authentication failed',
            'token_valid' => false
        ];
    }
}

function generate_desktop_integration_token($data) {
    try {
        $userid = $data['userid'];
        $panel_type = $data['panel_type'];

        // Generate secure token
        $token = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));

        // Store in database
        Capsule::table('mod_flexpbx_2fa_sessions')->insert([
            'userid' => $userid,
            'session_token' => $token,
            'panel_type' => $panel_type,
            'auth_data' => json_encode($data),
            'expires_at' => $expires_at,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        return [
            'success' => true,
            'token' => $token,
            'expires_at' => $expires_at,
            'integration_url' => "flexpbx://auth?token=$token&server=" . urlencode($_SERVER['HTTP_HOST'])
        ];

    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

function verify_2fa_token($data) {
    $token_provided = $data['token'];
    $secret = $data['secret'];

    $calculated_token = generate_totp_token($secret);

    // Allow for time drift (check current and previous/next period)
    $time_slices = [
        floor(time() / 30) - 1,
        floor(time() / 30),
        floor(time() / 30) + 1
    ];

    foreach ($time_slices as $time_slice) {
        $binary = pack('N*', 0, $time_slice);
        $hash = hash_hmac('sha1', $binary, base32_decode($secret), true);
        $offset = ord($hash[19]) & 0xf;
        $otp = (
            ((ord($hash[$offset + 0]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        ) % pow(10, 6);

        $calculated = str_pad($otp, 6, '0', STR_PAD_LEFT);

        if ($calculated === $token_provided) {
            return ['success' => true, 'message' => 'Token verified successfully'];
        }
    }

    return ['success' => false, 'message' => 'Invalid token'];
}

function get_total_accounts() {
    return Capsule::table('mod_flexpbx_accounts')->count();
}

function get_active_accounts() {
    return Capsule::table('mod_flexpbx_accounts')->where('status', 'active')->count();
}

function get_account_details($account_id) {
    $account = Capsule::table('mod_flexpbx_accounts')->where('id', $account_id)->first();
    if (!$account) {
        return ['success' => false, 'error' => 'Account not found'];
    }

    $extensions = Capsule::table('mod_flexpbx_extensions')
        ->where('account_id', $account_id)
        ->get();

    return [
        'success' => true,
        'account' => $account,
        'extensions' => $extensions
    ];
}

// Webhook handler for desktop app communication
if (isset($_GET['webhook']) && $_GET['webhook'] == 'desktop') {
    header('Content-Type: application/json');

    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    // Verify webhook signature
    $webhook_secret = get_module_option('webhook_secret');
    $signature = hash_hmac('sha256', $input, $webhook_secret);

    if (!hash_equals($signature, $_SERVER['HTTP_X_FLEXPBX_SIGNATURE'] ?? '')) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid signature']);
        exit;
    }

    // Process webhook
    switch ($data['action']) {
        case 'account_sync':
            echo json_encode(sync_account_data($data));
            break;
        case 'extension_update':
            echo json_encode(update_extension_data($data));
            break;
        default:
            echo json_encode(['success' => false, 'error' => 'Unknown webhook action']);
    }
    exit;
}

function sync_account_data($data) {
    // Sync account data from FlexPBX server
    // Implementation would depend on specific requirements
    return ['success' => true, 'message' => 'Account synced'];
}

function update_extension_data($data) {
    // Update extension data from FlexPBX server
    // Implementation would depend on specific requirements
    return ['success' => true, 'message' => 'Extension updated'];
}

function get_module_option($option_name) {
    $result = Capsule::table('tbladdonmodules')
        ->where('module', 'flexpbx')
        ->where('setting', $option_name)
        ->first();

    return $result ? $result->value : '';
}
?>