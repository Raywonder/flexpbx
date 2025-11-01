<?php
/**
 * FlexPBX Auto-Provisioning System
 *
 * Comprehensive automatic user provisioning with:
 * - Extension creation in database and Asterisk
 * - Voicemail configuration
 * - DID assignment (shared main DID or personal)
 * - Feature enablement (recording, notifications, accessibility)
 * - Welcome email with credentials
 * - Complete audit logging
 *
 * @author FlexPBX
 * @version 1.0.0
 * @created 2025-10-17
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/EmailService.php';
require_once __DIR__ . '/AsteriskManager.php';
require_once __DIR__ . '/ProvisioningSettings.php';

class AutoProvisioning {

    private $db;
    private $emailService;
    private $asteriskManager;
    private $mainDID = '3023139555'; // Main CallCentric DID (fallback)
    private $logger;
    private $settings = [];

    /**
     * Constructor
     */
    public function __construct() {
        global $db;
        $this->db = $db;
        $this->emailService = new EmailService();
        $this->loadSettings();
    }

    /**
     * Load settings from provisioning_settings table
     */
    private function loadSettings() {
        // Load all settings into cache
        $this->settings = ProvisioningSettings::getAllByCategory();

        // Set main DID from settings
        $this->mainDID = ProvisioningSettings::get('main_did', '3023139555');

        // Log settings load for debugging
        error_log("AutoProvisioning: Loaded settings from database. Main DID: " . $this->mainDID);
    }

    /**
     * Main provisioning function - creates complete user account
     *
     * @param string $username Username for login
     * @param string $email User email address
     * @param string $extension Extension number (or null to auto-assign)
     * @param string $password SIP password (or null to auto-generate)
     * @param string $full_name User's full name
     * @param string $role User role (admin, user, operator)
     * @param array $options Additional options
     * @return array Result with success/failure and details
     */
    public function provisionNewUser(
        $username,
        $email,
        $extension = null,
        $password = null,
        $full_name = '',
        $role = 'user',
        $options = []
    ) {
        $this->db->beginTransaction();

        try {
            // Step 1: Validate inputs
            $validation = $this->validateUserInputs($username, $email, $extension);
            if (!$validation['success']) {
                throw new Exception($validation['error']);
            }

            // Step 2: Auto-assign extension if not provided
            if (!$extension) {
                $extension = $this->getNextAvailableExtension();
            }

            // Step 3: Auto-generate secure password if not provided
            if (!$password) {
                $password = $this->generateSecurePassword();
            }

            // Step 4: Generate voicemail PIN
            $voicemail_pin = $this->generateVoicemailPin();

            // Step 5: Create user account in database
            $user_id = $this->createUserAccount($username, $email, $full_name, $role);
            $this->log('create_user', $extension, "Created user account: $username (ID: $user_id)", 'success');

            // Step 6: Create extension in database
            $this->createExtension($extension, $full_name, $email, $password, $user_id);
            $this->log('create_extension', $extension, "Created extension: $extension", 'success');

            // Step 7: Create PJSIP configuration
            $this->createPJSIPEndpoint($extension, $password, $full_name);
            $this->log('create_pjsip', $extension, "Created PJSIP endpoint for extension: $extension", 'success');

            // Step 8: Create voicemail mailbox
            $this->createVoicemailMailbox($extension, $voicemail_pin, $full_name, $email);
            $this->log('create_voicemail', $extension, "Created voicemail mailbox for extension: $extension", 'success');

            // Step 9: Enable all features by default
            $this->enableAllFeatures($extension, $user_id, $voicemail_pin);
            $this->log('enable_features', $extension, "Enabled all features for extension: $extension", 'success');

            // Step 10: Assign main DID (shared)
            $this->assignMainDID($extension, $user_id);
            $this->log('assign_did', $extension, "Assigned main DID {$this->mainDID} to extension: $extension", 'success');

            // Step 11: Create dialplan entry
            $this->createDialplanEntry($extension, $full_name);
            $this->log('create_dialplan', $extension, "Created dialplan entry for extension: $extension", 'success');

            // Step 12: Create notification preferences
            $this->createNotificationPreferences($user_id, $extension);
            $this->log('create_notifications', $extension, "Created notification preferences for extension: $extension", 'success');

            // Step 13: Reload Asterisk configuration
            $this->reloadAsteriskConfig();
            $this->log('reload_asterisk', $extension, "Reloaded Asterisk configuration", 'success');

            // Step 14: Send welcome email
            $credentials = [
                'username' => $username,
                'extension' => $extension,
                'password' => $password,
                'voicemail_pin' => $voicemail_pin,
                'did' => $this->mainDID,
                'server' => 'flexpbx.devinecreations.net',
                'email' => $email
            ];

            if ($options['send_email'] ?? true) {
                $this->sendWelcomeEmail($email, $full_name, $credentials);
                $this->log('send_email', $extension, "Sent welcome email to: $email", 'success');
            }

            // Commit transaction
            $this->db->commit();

            return [
                'success' => true,
                'message' => 'User provisioned successfully',
                'user_id' => $user_id,
                'extension' => $extension,
                'credentials' => $credentials
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->log('provision_failed', $extension ?? 'unknown', "Provisioning failed: " . $e->getMessage(), 'failed');

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Validate user inputs
     *
     * @param string $username
     * @param string $email
     * @param string|null $extension
     * @return array
     */
    private function validateUserInputs($username, $email, $extension) {
        // Check username availability
        $stmt = $this->db->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            return ['success' => false, 'error' => 'Username already exists'];
        }

        // Check email availability
        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            return ['success' => false, 'error' => 'Email already exists'];
        }

        // Check extension availability if provided
        if ($extension) {
            $stmt = $this->db->prepare("SELECT id FROM extensions WHERE extension_number = ?");
            $stmt->execute([$extension]);
            if ($stmt->fetch()) {
                return ['success' => false, 'error' => 'Extension already in use'];
            }
        }

        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Invalid email format'];
        }

        return ['success' => true];
    }

    /**
     * Get next available extension number
     *
     * @return string
     */
    private function getNextAvailableExtension() {
        // Use ProvisioningSettings helper to get next extension
        $next_ext = ProvisioningSettings::getNextExtension();

        if ($next_ext === null) {
            // Fallback to old method
            $next_ext = 3000;
            while ($this->isExtensionInUse($next_ext)) {
                $next_ext++;
            }
        }

        return (string)$next_ext;
    }

    /**
     * Check if extension is in use
     *
     * @param string $extension
     * @return bool
     */
    private function isExtensionInUse($extension) {
        $stmt = $this->db->prepare("SELECT id FROM extensions WHERE extension_number = ?");
        $stmt->execute([$extension]);
        return (bool)$stmt->fetch();
    }

    /**
     * Generate secure password
     *
     * @return string
     */
    private function generateSecurePassword() {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';
        for ($i = 0; $i < 16; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $password;
    }

    /**
     * Generate voicemail PIN
     *
     * @return string
     */
    private function generateVoicemailPin() {
        $pin_length = ProvisioningSettings::get('default_voicemail_pin_length', 4);
        $max_value = pow(10, $pin_length) - 1;
        return str_pad(random_int(0, $max_value), $pin_length, '0', STR_PAD_LEFT);
    }

    /**
     * Create user account in database
     *
     * @param string $username
     * @param string $email
     * @param string $full_name
     * @param string $role
     * @return int User ID
     */
    private function createUserAccount($username, $email, $full_name, $role) {
        $password_hash = password_hash($this->generateSecurePassword(), PASSWORD_DEFAULT);
        $api_key = bin2hex(random_bytes(32));

        $stmt = $this->db->prepare("
            INSERT INTO users (username, email, password_hash, full_name, role, is_active, api_key)
            VALUES (?, ?, ?, ?, ?, 1, ?)
        ");

        $stmt->execute([$username, $email, $password_hash, $full_name, $role, $api_key]);
        return $this->db->lastInsertId();
    }

    /**
     * Create extension in database
     *
     * @param string $extension
     * @param string $display_name
     * @param string $email
     * @param string $password
     * @param int $user_id
     */
    private function createExtension($extension, $display_name, $email, $password, $user_id) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $this->db->prepare("
            INSERT INTO extensions (extension_number, display_name, email, password_hash, user_id, status, voicemail_enabled)
            VALUES (?, ?, ?, ?, ?, 'active', 1)
        ");

        $stmt->execute([$extension, $display_name, $email, $password_hash, $user_id]);
    }

    /**
     * Create PJSIP endpoint configuration
     *
     * @param string $extension
     * @param string $password
     * @param string $display_name
     */
    private function createPJSIPEndpoint($extension, $password, $display_name) {
        $config = "; Extension $extension - $display_name - Auto-provisioned " . date('Y-m-d H:i:s') . "\n";
        $config .= "[$extension](user_defaults)\n";
        $config .= "auth = $extension\n";
        $config .= "aors = $extension\n";
        $config .= "callerid = \"$display_name\" <$extension>\n\n";

        $config .= "[$extension](auth_defaults)\n";
        $config .= "username = $extension\n";
        $config .= "password = $password\n\n";

        $config .= "[$extension](aor_defaults)\n";
        $config .= "max_contacts = 5\n\n";

        // Append to pjsip.conf
        $pjsip_file = '/etc/asterisk/pjsip.conf';
        file_put_contents($pjsip_file, $config, FILE_APPEND);
    }

    /**
     * Create voicemail mailbox
     *
     * @param string $extension
     * @param string $pin
     * @param string $full_name
     * @param string $email
     */
    private function createVoicemailMailbox($extension, $pin, $full_name, $email) {
        $voicemail_conf = "/etc/asterisk/voicemail.conf";

        // Create mailbox entry
        $mailbox = "$extension => $pin,$full_name,$email,,attach=yes|delete=yes|saycid=yes\n";

        // Find [flexpbx] context and append
        $content = file_get_contents($voicemail_conf);

        // Check if [flexpbx] context exists
        if (strpos($content, '[flexpbx]') !== false) {
            // Append to existing context
            $content .= $mailbox;
        } else {
            // Create context
            $content .= "\n[flexpbx]\n$mailbox";
        }

        file_put_contents($voicemail_conf, $content);
    }

    /**
     * Enable all features for user based on provisioning settings
     *
     * @param string $extension
     * @param int $user_id
     * @param string $voicemail_pin
     */
    public function enableAllFeatures($extension, $user_id, $voicemail_pin = null) {
        // Load feature settings from database
        $voicemail_enabled = ProvisioningSettings::get('auto_enable_voicemail', true) ? 1 : 0;
        $email_notifications = ProvisioningSettings::get('auto_enable_email_notifications', true) ? 1 : 0;
        $mastodon_notifications = ProvisioningSettings::get('auto_enable_mastodon_notifications', false) ? 1 : 0;
        $call_recording = ProvisioningSettings::get('auto_enable_call_recording', true) ? 1 : 0;
        $accessibility = ProvisioningSettings::get('auto_enable_accessibility', true) ? 1 : 0;
        $department = ProvisioningSettings::get('default_department', 'General');

        $stmt = $this->db->prepare("
            INSERT INTO extension_features (
                extension,
                user_id,
                voicemail_enabled,
                voicemail_pin,
                email_notifications,
                mastodon_notifications,
                call_recording_enabled,
                accessibility_enabled,
                department
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                voicemail_enabled = VALUES(voicemail_enabled),
                email_notifications = VALUES(email_notifications),
                mastodon_notifications = VALUES(mastodon_notifications),
                call_recording_enabled = VALUES(call_recording_enabled),
                accessibility_enabled = VALUES(accessibility_enabled),
                department = VALUES(department)
        ");

        $stmt->execute([
            $extension,
            $user_id,
            $voicemail_enabled,
            $voicemail_pin,
            $email_notifications,
            $mastodon_notifications,
            $call_recording,
            $accessibility,
            $department
        ]);
    }

    /**
     * Assign main shared DID to user
     *
     * @param string $extension
     * @param int $user_id
     * @return array Assignment details
     */
    public function assignMainDID($extension, $user_id = null) {
        $stmt = $this->db->prepare("
            INSERT INTO user_dids (user_id, extension, did_number, is_primary, is_shared, did_type)
            VALUES (?, ?, ?, 1, 1, 'shared')
        ");

        $stmt->execute([$user_id, $extension, $this->mainDID]);

        return [
            'extension' => $extension,
            'did' => $this->mainDID,
            'type' => 'shared',
            'assigned' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Create dialplan entry for extension
     *
     * @param string $extension
     * @param string $display_name
     */
    private function createDialplanEntry($extension, $display_name) {
        $dialplan = "; Extension $extension - $display_name - Auto-provisioned " . date('Y-m-d H:i:s') . "\n";
        $dialplan .= "exten => $extension,1,NoOp(Calling $display_name at extension $extension)\n";
        $dialplan .= "same => n,Dial(PJSIP/$extension,30,Tt)\n";
        $dialplan .= "same => n,Voicemail($extension@flexpbx,su)\n";
        $dialplan .= "same => n,Hangup()\n\n";

        // Append to extensions.conf in [from-internal] context
        // Note: In production, you'd want to insert this in the right context
        $extensions_file = '/etc/asterisk/extensions.conf';
        file_put_contents($extensions_file, $dialplan, FILE_APPEND);
    }

    /**
     * Create notification preferences for user based on provisioning settings
     *
     * @param int $user_id
     * @param string $extension
     */
    private function createNotificationPreferences($user_id, $extension) {
        // Load notification settings from database
        $email_enabled = ProvisioningSettings::get('auto_enable_email_notifications', true) ? 1 : 0;
        $notify_voicemail = ProvisioningSettings::get('notify_voicemail_default', true) ? 1 : 0;
        $notify_missed_calls = ProvisioningSettings::get('notify_missed_calls_default', true) ? 1 : 0;
        $notify_recordings = ProvisioningSettings::get('auto_enable_call_recording', true) ? 1 : 0;
        $mastodon_enabled = ProvisioningSettings::get('auto_enable_mastodon_notifications', false) ? 1 : 0;

        $stmt = $this->db->prepare("
            INSERT INTO user_notification_preferences (
                user_id,
                extension,
                email_enabled,
                notify_missed_calls,
                notify_voicemail,
                notify_recordings,
                mastodon_enabled
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $user_id,
            $extension,
            $email_enabled,
            $notify_missed_calls,
            $notify_voicemail,
            $notify_recordings,
            $mastodon_enabled
        ]);
    }

    /**
     * Reload Asterisk configuration based on settings
     */
    private function reloadAsteriskConfig() {
        // Check if auto-reload is enabled
        $auto_reload = ProvisioningSettings::get('asterisk_reload_on_provision', true);

        if (!$auto_reload) {
            error_log("AutoProvisioning: Asterisk reload skipped (disabled in settings)");
            return;
        }

        // Reload PJSIP
        exec('asterisk -rx "pjsip reload" 2>&1', $output, $return);

        // Reload dialplan
        exec('asterisk -rx "dialplan reload" 2>&1', $output, $return);

        // Reload voicemail
        exec('asterisk -rx "voicemail reload" 2>&1', $output, $return);
    }

    /**
     * Generate and send welcome email
     *
     * @param string $email
     * @param string $full_name
     * @param array $credentials
     * @return bool
     */
    public function sendWelcomeEmail($email, $full_name, $credentials) {
        $subject = "Welcome to FlexPBX - Your Account is Ready!";

        $body_html = $this->generateWelcomeEmailHTML($full_name, $credentials);
        $body_text = $this->generateWelcomeEmailText($full_name, $credentials);

        return $this->emailService->sendEmail(
            $email,
            $subject,
            $body_html,
            null,
            [],
            null,
            false, // Send immediately
            1, // High priority
            $full_name
        );
    }

    /**
     * Generate HTML welcome email
     *
     * @param string $full_name
     * @param array $credentials
     * @return string
     */
    private function generateWelcomeEmailHTML($full_name, $credentials) {
        return "
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #2c3e50; color: white; padding: 20px; text-align: center; }
        .content { background: #f9f9f9; padding: 30px; margin: 20px 0; border-radius: 5px; }
        .credentials { background: white; padding: 20px; border-left: 4px solid #3498db; margin: 20px 0; }
        .credential-item { margin: 10px 0; }
        .credential-label { font-weight: bold; color: #2c3e50; }
        .credential-value { color: #e74c3c; font-family: monospace; font-size: 14px; }
        .features { background: white; padding: 20px; margin: 20px 0; }
        .feature-item { padding: 10px 0; border-bottom: 1px solid #eee; }
        .footer { text-align: center; color: #7f8c8d; font-size: 12px; padding: 20px; }
        .button { display: inline-block; padding: 12px 30px; background: #3498db; color: white; text-decoration: none; border-radius: 5px; margin: 10px 0; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>Welcome to FlexPBX!</h1>
        </div>

        <div class='content'>
            <h2>Hello {$full_name},</h2>
            <p>Your FlexPBX account has been successfully created and is ready to use! Below are your account credentials and important information.</p>

            <div class='credentials'>
                <h3>Your Account Credentials</h3>
                <div class='credential-item'>
                    <span class='credential-label'>Username:</span>
                    <span class='credential-value'>{$credentials['username']}</span>
                </div>
                <div class='credential-item'>
                    <span class='credential-label'>Extension:</span>
                    <span class='credential-value'>{$credentials['extension']}</span>
                </div>
                <div class='credential-item'>
                    <span class='credential-label'>SIP Password:</span>
                    <span class='credential-value'>{$credentials['password']}</span>
                </div>
                <div class='credential-item'>
                    <span class='credential-label'>Voicemail PIN:</span>
                    <span class='credential-value'>{$credentials['voicemail_pin']}</span>
                </div>
                <div class='credential-item'>
                    <span class='credential-label'>Phone Number (DID):</span>
                    <span class='credential-value'>{$credentials['did']}</span>
                </div>
                <div class='credential-item'>
                    <span class='credential-label'>Server:</span>
                    <span class='credential-value'>{$credentials['server']}</span>
                </div>
            </div>

            <div class='features'>
                <h3>Enabled Features</h3>
                <div class='feature-item'>✓ <strong>Voicemail</strong> - Check voicemail from any phone by dialing *97</div>
                <div class='feature-item'>✓ <strong>Email Notifications</strong> - Receive voicemail and call notifications via email</div>
                <div class='feature-item'>✓ <strong>Call Recording</strong> - All calls can be recorded for quality assurance</div>
                <div class='feature-item'>✓ <strong>Accessibility Features</strong> - Full support for accessibility tools</div>
                <div class='feature-item'>✓ <strong>Web Portal Access</strong> - Manage your account online</div>
            </div>

            <h3>Getting Started</h3>
            <p>To start using your FlexPBX phone system:</p>
            <ol>
                <li>Download a SIP client (we recommend Groundwire for iOS or Android)</li>
                <li>Configure your phone app with the credentials above</li>
                <li>Start making and receiving calls!</li>
            </ol>

            <p style='text-align: center;'>
                <a href='https://{$credentials['server']}/user-portal/' class='button'>Access Web Portal</a>
            </p>

            <h3>Important Notes</h3>
            <ul>
                <li>Your phone number ({$credentials['did']}) is currently a <strong>shared number</strong> with other users</li>
                <li>You can request your own dedicated phone number from your dashboard</li>
                <li>Keep your credentials secure - never share your password</li>
                <li>Change your voicemail PIN on first use by dialing *97</li>
            </ul>
        </div>

        <div class='footer'>
            <p>If you have any questions or need assistance, please contact support.</p>
            <p>&copy; 2025 FlexPBX. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
        ";
    }

    /**
     * Generate plain text welcome email
     *
     * @param string $full_name
     * @param array $credentials
     * @return string
     */
    private function generateWelcomeEmailText($full_name, $credentials) {
        return "
WELCOME TO FLEXPBX!

Hello {$full_name},

Your FlexPBX account has been successfully created and is ready to use!

YOUR ACCOUNT CREDENTIALS
-------------------------
Username:        {$credentials['username']}
Extension:       {$credentials['extension']}
SIP Password:    {$credentials['password']}
Voicemail PIN:   {$credentials['voicemail_pin']}
Phone Number:    {$credentials['did']}
Server:          {$credentials['server']}

ENABLED FEATURES
----------------
✓ Voicemail - Check voicemail from any phone by dialing *97
✓ Email Notifications - Receive voicemail and call notifications via email
✓ Call Recording - All calls can be recorded for quality assurance
✓ Accessibility Features - Full support for accessibility tools
✓ Web Portal Access - Manage your account online

GETTING STARTED
---------------
1. Download a SIP client (we recommend Groundwire for iOS or Android)
2. Configure your phone app with the credentials above
3. Start making and receiving calls!

Access Web Portal: https://{$credentials['server']}/user-portal/

IMPORTANT NOTES
---------------
- Your phone number ({$credentials['did']}) is currently a shared number with other users
- You can request your own dedicated phone number from your dashboard
- Keep your credentials secure - never share your password
- Change your voicemail PIN on first use by dialing *97

If you have any questions or need assistance, please contact support.

© 2025 FlexPBX. All rights reserved.
        ";
    }

    /**
     * Log provisioning action
     *
     * @param string $action
     * @param string $extension
     * @param string $details
     * @param string $status
     */
    private function log($action, $extension, $details, $status = 'success') {
        $stmt = $this->db->prepare("
            INSERT INTO auto_provisioning_log (extension, action, details, status)
            VALUES (?, ?, ?, ?)
        ");

        $stmt->execute([$extension, $action, $details, $status]);
    }

    /**
     * Get provisioning log for extension
     *
     * @param string $extension
     * @param int $limit
     * @return array
     */
    public function getProvisioningLog($extension, $limit = 50) {
        $stmt = $this->db->prepare("
            SELECT * FROM auto_provisioning_log
            WHERE extension = ?
            ORDER BY timestamp DESC
            LIMIT ?
        ");

        $stmt->execute([$extension, $limit]);
        return $stmt->fetchAll();
    }

    /**
     * Get all DIDs for a user
     *
     * @param int $user_id
     * @return array
     */
    public function getUserDIDs($user_id) {
        $stmt = $this->db->prepare("
            SELECT * FROM user_dids
            WHERE user_id = ?
            ORDER BY is_primary DESC, assigned_date ASC
        ");

        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    }

    /**
     * Request new DID for user
     *
     * @param int $user_id
     * @param string $extension
     * @param string $area_code
     * @param string $type
     * @return int Request ID
     */
    public function requestNewDID($user_id, $extension, $area_code = null, $type = 'new') {
        $stmt = $this->db->prepare("
            INSERT INTO did_request_queue (user_id, extension, requested_area_code, request_type, status)
            VALUES (?, ?, ?, ?, 'pending')
        ");

        $stmt->execute([$user_id, $extension, $area_code, $type]);
        return $this->db->lastInsertId();
    }
}
