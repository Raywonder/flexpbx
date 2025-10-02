const path = require('path');
const fs = require('fs-extra');

class ComposrHooksService {
    constructor() {
        this.composrDetected = false;
        this.composrVersion = null;
        this.hooksDirectory = null;
    }

    /**
     * Detect Composr installation on server
     */
    async detectComposrInstallation(sshConnection, serverPath = '/') {
        try {
            // Check for Composr-specific files
            const composrIndicators = [
                'sources/global.php',
                '_config.php',
                'info.php',
                'sources/comcode.php',
                'data_custom/modules'
            ];

            const detectionResults = [];

            for (const indicator of composrIndicators) {
                const fullPath = path.posix.join(serverPath, indicator);
                const result = await sshConnection.execCommand(`test -e "${fullPath}" && echo "exists" || echo "missing"`);

                detectionResults.push({
                    file: indicator,
                    exists: result.stdout.trim() === 'exists'
                });
            }

            // Consider Composr detected if most indicators are present
            const existingCount = detectionResults.filter(r => r.exists).length;
            this.composrDetected = existingCount >= 3;

            if (this.composrDetected) {
                // Try to detect version
                await this.detectComposrVersion(sshConnection, serverPath);

                // Set hooks directory
                this.hooksDirectory = path.posix.join(serverPath, 'sources_custom/hooks');
            }

            return {
                detected: this.composrDetected,
                version: this.composrVersion,
                indicators: detectionResults,
                hooksDirectory: this.hooksDirectory
            };
        } catch (error) {
            return {
                detected: false,
                error: error.message
            };
        }
    }

    /**
     * Detect Composr version
     */
    async detectComposrVersion(sshConnection, serverPath) {
        try {
            // Try to read version from info.php
            const infoPhpPath = path.posix.join(serverPath, 'info.php');
            const versionCheck = await sshConnection.execCommand(`grep -i "version" "${infoPhpPath}" | head -1`);

            if (versionCheck.stdout) {
                const versionMatch = versionCheck.stdout.match(/(\d+)\.(\d+)/);
                if (versionMatch) {
                    this.composrVersion = versionMatch[0];
                    return this.composrVersion;
                }
            }

            // Fallback: check for v11 specific features
            const v11Check = await sshConnection.execCommand(`test -d "${path.posix.join(serverPath, 'sources/symbol_hooks')}" && echo "v11" || echo "v10"`);
            this.composrVersion = v11Check.stdout.trim() === 'v11' ? '11.x' : '10.x';

            return this.composrVersion;
        } catch (error) {
            this.composrVersion = 'unknown';
            return this.composrVersion;
        }
    }

    /**
     * Get FlexPBX-specific hooks for Composr
     */
    getFlexPBXHooks() {
        return {
            // Authentication hooks
            authentication: {
                'auth_flexpbx.php': this.generateAuthHook(),
                'sso_flexpbx.php': this.generateSSOHook()
            },

            // Communication hooks
            communication: {
                'pbx_integration.php': this.generatePBXIntegrationHook(),
                'call_logging.php': this.generateCallLoggingHook(),
                'voicemail_notification.php': this.generateVoicemailHook()
            },

            // User management hooks
            user_management: {
                'user_presence.php': this.generateUserPresenceHook(),
                'user_directory.php': this.generateUserDirectoryHook()
            },

            // Admin hooks
            admin: {
                'flexpbx_admin_panel.php': this.generateAdminPanelHook(),
                'extension_management.php': this.generateExtensionManagementHook()
            },

            // Block hooks (UI components)
            blocks: {
                'main_pbx_status': this.generatePBXStatusBlock(),
                'side_call_history': this.generateCallHistoryBlock(),
                'main_dial_pad': this.generateDialPadBlock()
            }
        };
    }

    /**
     * Generate authentication hook
     */
    generateAuthHook() {
        return `<?php

/**
 * FlexPBX Authentication Hook for Composr
 * Integrates FlexPBX user authentication with Composr
 */

if (!defined('BYPASS_HOOKS_CODE_CHECK')) {
    if (!defined('IN_FLEXPBX_ENVIRONMENT')) {
        exit();
    }
}

/**
 * Check FlexPBX authentication status
 *
 * @param string $username
 * @param string $password
 * @return boolean
 */
function hook_auth_flexpbx_check_credentials($username, $password) {
    // Integration with FlexPBX authentication API
    try {
        $flexpbx_config = get_flexpbx_config();
        if (!$flexpbx_config) {
            return false;
        }

        $auth_result = flexpbx_authenticate_user($username, $password);

        if ($auth_result['success']) {
            // Sync user data between systems
            sync_flexpbx_user_data($auth_result['user_data']);
            return true;
        }

        return false;
    } catch (Exception $e) {
        log_warning('FlexPBX auth error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get FlexPBX user extension
 */
function get_user_extension($member_id) {
    $extensions = get_flexpbx_extensions();
    return isset($extensions[$member_id]) ? $extensions[$member_id] : null;
}

/**
 * Check if user is available for calls
 */
function is_user_available_for_calls($member_id) {
    $presence = get_user_presence_status($member_id);
    return in_array($presence, ['available', 'busy']);
}

?>`;
    }

    /**
     * Generate SSO hook
     */
    generateSSOHook() {
        return `<?php

/**
 * FlexPBX Single Sign-On Hook for Composr
 */

if (!defined('BYPASS_HOOKS_CODE_CHECK')) {
    if (!defined('IN_FLEXPBX_ENVIRONMENT')) {
        exit();
    }
}

/**
 * Handle SSO login from FlexPBX
 */
function hook_sso_flexpbx_process_login($token) {
    try {
        $user_data = verify_flexpbx_token($token);

        if ($user_data) {
            // Create or update Composr user
            $member_id = create_or_update_composr_user($user_data);

            // Set session
            $GLOBALS['FORUM_DRIVER']->forum_groupid_of_member($member_id, true);

            return array(
                'success' => true,
                'member_id' => $member_id,
                'extension' => $user_data['extension']
            );
        }

        return array('success' => false, 'error' => 'Invalid token');
    } catch (Exception $e) {
        return array('success' => false, 'error' => $e->getMessage());
    }
}

?>`;
    }

    /**
     * Generate PBX integration hook
     */
    generatePBXIntegrationHook() {
        return `<?php

/**
 * FlexPBX Core Integration Hook
 */

if (!defined('BYPASS_HOOKS_CODE_CHECK')) {
    if (!defined('IN_FLEXPBX_ENVIRONMENT')) {
        exit();
    }
}

/**
 * Handle incoming call notifications
 */
function hook_pbx_integration_incoming_call($call_data) {
    // Notify relevant Composr users about incoming calls
    $extension = $call_data['destination_extension'];
    $member_id = get_member_by_extension($extension);

    if ($member_id) {
        // Send real-time notification
        send_call_notification($member_id, $call_data);

        // Log call in Composr activity feed
        log_call_activity($member_id, $call_data);
    }
}

/**
 * Handle call completion
 */
function hook_pbx_integration_call_completed($call_data) {
    // Update call history in Composr
    store_call_record($call_data);

    // Update user availability status
    if (isset($call_data['participants'])) {
        foreach ($call_data['participants'] as $extension) {
            $member_id = get_member_by_extension($extension);
            if ($member_id) {
                update_user_availability($member_id, 'available');
            }
        }
    }
}

/**
 * Get user's call permissions
 */
function get_user_call_permissions($member_id) {
    $usergroups = $GLOBALS['FORUM_DRIVER']->get_members_groups($member_id);

    $permissions = array(
        'can_make_calls' => false,
        'can_receive_calls' => false,
        'can_transfer_calls' => false,
        'can_access_voicemail' => false,
        'max_call_duration' => 0
    );

    // Check group permissions
    foreach ($usergroups as $group_id) {
        $group_perms = get_group_flexpbx_permissions($group_id);
        if ($group_perms) {
            $permissions = array_merge($permissions, $group_perms);
        }
    }

    return $permissions;
}

?>`;
    }

    /**
     * Generate call logging hook
     */
    generateCallLoggingHook() {
        return `<?php

/**
 * FlexPBX Call Logging Hook
 */

if (!defined('BYPASS_HOOKS_CODE_CHECK')) {
    if (!defined('IN_FLEXPBX_ENVIRONMENT')) {
        exit();
    }
}

/**
 * Log call details in Composr
 */
function hook_call_logging_store_call($call_data) {
    $db = $GLOBALS['SITE_DB'];

    // Store in custom call_logs table
    $db->query_insert('call_logs', array(
        'call_id' => $call_data['call_id'],
        'caller_extension' => $call_data['caller_extension'],
        'callee_extension' => $call_data['callee_extension'],
        'start_time' => $call_data['start_time'],
        'end_time' => $call_data['end_time'],
        'duration' => $call_data['duration'],
        'call_type' => $call_data['type'], // 'inbound', 'outbound', 'internal'
        'status' => $call_data['status'], // 'completed', 'missed', 'busy'
        'recording_path' => isset($call_data['recording']) ? $call_data['recording'] : NULL
    ));

    // Update user statistics
    update_user_call_stats($call_data);
}

/**
 * Generate call reports for admin
 */
function generate_call_reports($date_from, $date_to, $extension = null) {
    $db = $GLOBALS['SITE_DB'];

    $where_clause = "start_time BETWEEN '" . $date_from . "' AND '" . $date_to . "'";
    if ($extension) {
        $where_clause .= " AND (caller_extension = '" . db_escape_string($extension) . "' OR callee_extension = '" . db_escape_string($extension) . "')";
    }

    $calls = $db->query_select('call_logs', array('*'), array($where_clause));

    return array(
        'total_calls' => count($calls),
        'completed_calls' => count(array_filter($calls, function($call) { return $call['status'] == 'completed'; })),
        'missed_calls' => count(array_filter($calls, function($call) { return $call['status'] == 'missed'; })),
        'average_duration' => array_sum(array_column($calls, 'duration')) / max(1, count($calls)),
        'calls' => $calls
    );
}

?>`;
    }

    /**
     * Generate voicemail notification hook
     */
    generateVoicemailHook() {
        return `<?php

/**
 * FlexPBX Voicemail Notification Hook
 */

if (!defined('BYPASS_HOOKS_CODE_CHECK')) {
    if (!defined('IN_FLEXPBX_ENVIRONMENT')) {
        exit();
    }
}

/**
 * Handle new voicemail notifications
 */
function hook_voicemail_notification_new_message($voicemail_data) {
    $extension = $voicemail_data['extension'];
    $member_id = get_member_by_extension($extension);

    if ($member_id) {
        // Send email notification
        send_voicemail_email_notification($member_id, $voicemail_data);

        // Create Composr notification
        create_composr_notification($member_id, 'voicemail', $voicemail_data);

        // Update voicemail count in user profile
        update_voicemail_count($member_id);
    }
}

/**
 * Send voicemail email notification
 */
function send_voicemail_email_notification($member_id, $voicemail_data) {
    $email = $GLOBALS['FORUM_DRIVER']->get_member_email_address($member_id);
    $username = $GLOBALS['FORUM_DRIVER']->get_username($member_id);

    $subject = "New Voicemail Message";
    $message = "Hello " . $username . ",\\n\\n";
    $message .= "You have received a new voicemail message:\\n\\n";
    $message .= "From: " . $voicemail_data['caller_id'] . "\\n";
    $message .= "Duration: " . format_duration($voicemail_data['duration']) . "\\n";
    $message .= "Received: " . date('Y-m-d H:i:s', $voicemail_data['timestamp']) . "\\n\\n";
    $message .= "You can listen to this message by logging into your FlexPBX account.\\n\\n";
    $message .= "Best regards,\\nFlexPBX System";

    mail_wrap($email, $subject, $message);
}

?>`;
    }

    /**
     * Generate user presence hook
     */
    generateUserPresenceHook() {
        return `<?php

/**
 * FlexPBX User Presence Hook
 */

if (!defined('BYPASS_HOOKS_CODE_CHECK')) {
    if (!defined('IN_FLEXPBX_ENVIRONMENT')) {
        exit();
    }
}

/**
 * Update user presence status
 */
function hook_user_presence_update_status($member_id, $status) {
    $db = $GLOBALS['SITE_DB'];

    // Valid statuses: 'available', 'busy', 'away', 'offline', 'dnd'
    $valid_statuses = array('available', 'busy', 'away', 'offline', 'dnd');

    if (!in_array($status, $valid_statuses)) {
        return false;
    }

    // Update in database
    $existing = $db->query_select_value_if_there('user_presence', 'member_id', array('member_id' => $member_id));

    if ($existing) {
        $db->query_update('user_presence', array(
            'status' => $status,
            'last_updated' => time()
        ), array('member_id' => $member_id));
    } else {
        $db->query_insert('user_presence', array(
            'member_id' => $member_id,
            'status' => $status,
            'last_updated' => time()
        ));
    }

    // Broadcast status change to connected clients
    broadcast_presence_update($member_id, $status);

    return true;
}

/**
 * Get user presence status
 */
function get_user_presence_status($member_id) {
    $db = $GLOBALS['SITE_DB'];

    $presence = $db->query_select_value_if_there('user_presence', 'status', array('member_id' => $member_id));

    if (!$presence) {
        return 'offline';
    }

    // Check if status is stale (older than 5 minutes)
    $last_updated = $db->query_select_value('user_presence', 'last_updated', array('member_id' => $member_id));
    if (time() - $last_updated > 300) {
        return 'away';
    }

    return $presence;
}

?>`;
    }

    /**
     * Generate user directory hook
     */
    generateUserDirectoryHook() {
        return `<?php

/**
 * FlexPBX User Directory Hook
 */

if (!defined('BYPASS_HOOKS_CODE_CHECK')) {
    if (!defined('IN_FLEXPBX_ENVIRONMENT')) {
        exit();
    }
}

/**
 * Get directory of users with extensions
 */
function hook_user_directory_get_directory($include_offline = false) {
    $db = $GLOBALS['SITE_DB'];

    $query = "
        SELECT m.id, m.m_username, m.m_email_address, e.extension, p.status
        FROM " . get_table_prefix() . "f_members m
        LEFT JOIN " . get_table_prefix() . "user_extensions e ON m.id = e.member_id
        LEFT JOIN " . get_table_prefix() . "user_presence p ON m.id = p.member_id
        WHERE e.extension IS NOT NULL
    ";

    if (!$include_offline) {
        $query .= " AND (p.status IS NULL OR p.status != 'offline')";
    }

    $query .= " ORDER BY m.m_username";

    $users = $db->query($query);

    $directory = array();
    while ($user = mysql_fetch_assoc($users)) {
        $directory[] = array(
            'member_id' => $user['id'],
            'username' => $user['m_username'],
            'email' => $user['m_email_address'],
            'extension' => $user['extension'],
            'status' => $user['status'] ?: 'offline',
            'can_receive_calls' => can_user_receive_calls($user['id'])
        );
    }

    return $directory;
}

/**
 * Search directory by name or extension
 */
function search_directory($search_term) {
    $db = $GLOBALS['SITE_DB'];

    $search_term = db_escape_string($search_term);

    $query = "
        SELECT m.id, m.m_username, e.extension, p.status
        FROM " . get_table_prefix() . "f_members m
        LEFT JOIN " . get_table_prefix() . "user_extensions e ON m.id = e.member_id
        LEFT JOIN " . get_table_prefix() . "user_presence p ON m.id = p.member_id
        WHERE e.extension IS NOT NULL
        AND (m.m_username LIKE '%" . $search_term . "%' OR e.extension LIKE '%" . $search_term . "%')
        ORDER BY m.m_username
    ";

    $results = $db->query($query);

    $matches = array();
    while ($user = mysql_fetch_assoc($results)) {
        $matches[] = array(
            'member_id' => $user['id'],
            'username' => $user['m_username'],
            'extension' => $user['extension'],
            'status' => $user['status'] ?: 'offline'
        );
    }

    return $matches;
}

?>`;
    }

    /**
     * Generate admin panel hook
     */
    generateAdminPanelHook() {
        return `<?php

/**
 * FlexPBX Admin Panel Hook
 */

if (!defined('BYPASS_HOOKS_CODE_CHECK')) {
    if (!defined('IN_FLEXPBX_ENVIRONMENT')) {
        exit();
    }
}

/**
 * Add FlexPBX admin menu items
 */
function hook_flexpbx_admin_panel_menu_items() {
    if (!has_flexpbx_admin_permission()) {
        return array();
    }

    return array(
        'flexpbx_dashboard' => array(
            'title' => 'FlexPBX Dashboard',
            'url' => build_url(array('page' => 'admin_flexpbx'), 'adminzone'),
            'icon' => 'phone'
        ),
        'flexpbx_extensions' => array(
            'title' => 'Manage Extensions',
            'url' => build_url(array('page' => 'admin_flexpbx', 'type' => 'extensions'), 'adminzone'),
            'icon' => 'users'
        ),
        'flexpbx_reports' => array(
            'title' => 'Call Reports',
            'url' => build_url(array('page' => 'admin_flexpbx', 'type' => 'reports'), 'adminzone'),
            'icon' => 'chart'
        ),
        'flexpbx_settings' => array(
            'title' => 'FlexPBX Settings',
            'url' => build_url(array('page' => 'admin_flexpbx', 'type' => 'settings'), 'adminzone'),
            'icon' => 'settings'
        )
    );
}

/**
 * Check if user has FlexPBX admin permission
 */
function has_flexpbx_admin_permission($member_id = null) {
    if ($member_id === null) {
        $member_id = get_member();
    }

    return has_specific_permission($member_id, 'manage_flexpbx');
}

?>`;
    }

    /**
     * Generate extension management hook
     */
    generateExtensionManagementHook() {
        return `<?php

/**
 * FlexPBX Extension Management Hook
 */

if (!defined('BYPASS_HOOKS_CODE_CHECK')) {
    if (!defined('IN_FLEXPBX_ENVIRONMENT')) {
        exit();
    }
}

/**
 * Assign extension to user
 */
function hook_extension_management_assign_extension($member_id, $extension) {
    $db = $GLOBALS['SITE_DB'];

    // Check if extension is already assigned
    $existing = $db->query_select_value_if_there('user_extensions', 'member_id', array('extension' => $extension));
    if ($existing && $existing != $member_id) {
        return array('success' => false, 'error' => 'Extension already assigned');
    }

    // Remove any existing extension for this user
    $db->query_delete('user_extensions', array('member_id' => $member_id));

    // Assign new extension
    $db->query_insert('user_extensions', array(
        'member_id' => $member_id,
        'extension' => $extension,
        'assigned_date' => time()
    ));

    // Sync with FlexPBX system
    sync_extension_with_flexpbx($member_id, $extension);

    return array('success' => true);
}

/**
 * Get available extensions
 */
function get_available_extensions() {
    // Get all extensions from FlexPBX system
    $all_extensions = get_flexpbx_extensions();

    // Get assigned extensions from Composr
    $db = $GLOBALS['SITE_DB'];
    $assigned = $db->query_select('user_extensions', array('extension'));
    $assigned_extensions = array_column($assigned, 'extension');

    // Return unassigned extensions
    return array_diff($all_extensions, $assigned_extensions);
}

?>`;
    }

    /**
     * Generate PBX status block
     */
    generatePBXStatusBlock() {
        return array(
            'block.php' => `<?php

/**
 * FlexPBX Status Block
 */

if (!defined('BYPASS_HOOKS_CODE_CODE_CHECK')) {
    if (!defined('IN_FLEXPBX_ENVIRONMENT')) {
        exit();
    }
}

function block_main_pbx_status($map) {
    $member_id = get_member();

    if (is_guest($member_id)) {
        return '';
    }

    $extension = get_user_extension($member_id);
    if (!$extension) {
        return '';
    }

    $status = get_user_presence_status($member_id);
    $call_stats = get_user_call_stats($member_id);

    $template = do_template('BLOCK_PBX_STATUS', array(
        'EXTENSION' => $extension,
        'STATUS' => $status,
        'MISSED_CALLS' => $call_stats['missed_calls'],
        'VOICEMAILS' => $call_stats['voicemails']
    ));

    return $template->evaluate();
}

?>`,
            'template.tpl' => `<div class="flexpbx-status-block">
    <h3>FlexPBX Status</h3>
    <div class="status-info">
        <div class="extension">Extension: {EXTENSION}</div>
        <div class="presence-status status-{STATUS}">
            Status: <span class="status-indicator"></span> {STATUS*}
        </div>
        {+START,IF,{MISSED_CALLS}}
            <div class="missed-calls">
                <a href="{$BASE_URL}/index.php?page=flexpbx&type=calls">
                    {MISSED_CALLS} missed call{+START,IF,{$NE,{MISSED_CALLS},1}}s{+END}
                </a>
            </div>
        {+END}
        {+START,IF,{VOICEMAILS}}
            <div class="voicemails">
                <a href="{$BASE_URL}/index.php?page=flexpbx&type=voicemail">
                    {VOICEMAILS} voicemail message{+START,IF,{$NE,{VOICEMAILS},1}}s{+END}
                </a>
            </div>
        {+END}
    </div>
</div>`
        );
    }

    /**
     * Generate call history block
     */
    generateCallHistoryBlock() {
        return array(
            'block.php' => `<?php

/**
 * FlexPBX Call History Block
 */

if (!defined('BYPASS_HOOKS_CODE_CODE_CHECK')) {
    if (!defined('IN_FLEXPBX_ENVIRONMENT')) {
        exit();
    }
}

function block_side_call_history($map) {
    $member_id = get_member();

    if (is_guest($member_id)) {
        return '';
    }

    $extension = get_user_extension($member_id);
    if (!$extension) {
        return '';
    }

    // Get recent calls
    $recent_calls = get_recent_calls($member_id, 5);

    $call_rows = '';
    foreach ($recent_calls as $call) {
        $call_rows .= do_template('CALL_HISTORY_ROW', array(
            'CALL_ID' => $call['call_id'],
            'OTHER_PARTY' => $call['other_party'],
            'DIRECTION' => $call['direction'],
            'DURATION' => format_duration($call['duration']),
            'TIME' => date('M j, H:i', $call['start_time'])
        ))->evaluate();
    }

    $template = do_template('BLOCK_CALL_HISTORY', array(
        'CALL_ROWS' => $call_rows
    ));

    return $template->evaluate();
}

?>`,
            'template.tpl' => `<div class="call-history-block">
    <h3>Recent Calls</h3>
    <div class="call-list">
        {CALL_ROWS}
    </div>
    <div class="view-all">
        <a href="{$BASE_URL}/index.php?page=flexpbx&type=calls">View All Calls</a>
    </div>
</div>`
        );
    }

    /**
     * Generate dial pad block
     */
    generateDialPadBlock() {
        return array(
            'block.php' => `<?php

/**
 * FlexPBX Dial Pad Block
 */

if (!defined('BYPASS_HOOKS_CODE_CODE_CHECK')) {
    if (!defined('IN_FLEXPBX_ENVIRONMENT')) {
        exit();
    }
}

function block_main_dial_pad($map) {
    $member_id = get_member();

    if (is_guest($member_id)) {
        return '';
    }

    $extension = get_user_extension($member_id);
    if (!$extension) {
        return '';
    }

    $permissions = get_user_call_permissions($member_id);
    if (!$permissions['can_make_calls']) {
        return '';
    }

    $template = do_template('BLOCK_DIAL_PAD', array(
        'EXTENSION' => $extension
    ));

    return $template->evaluate();
}

?>`,
            'template.tpl' => `<div class="dial-pad-block">
    <h3>Quick Dial</h3>
    <div class="dial-interface">
        <input type="text" id="dial-number" placeholder="Enter number or extension" />
        <div class="dial-buttons">
            <button class="dial-btn" data-digit="1">1</button>
            <button class="dial-btn" data-digit="2">2 ABC</button>
            <button class="dial-btn" data-digit="3">3 DEF</button>
            <button class="dial-btn" data-digit="4">4 GHI</button>
            <button class="dial-btn" data-digit="5">5 JKL</button>
            <button class="dial-btn" data-digit="6">6 MNO</button>
            <button class="dial-btn" data-digit="7">7 PQRS</button>
            <button class="dial-btn" data-digit="8">8 TUV</button>
            <button class="dial-btn" data-digit="9">9 WXYZ</button>
            <button class="dial-btn" data-digit="*">*</button>
            <button class="dial-btn" data-digit="0">0</button>
            <button class="dial-btn" data-digit="#">#</button>
        </div>
        <div class="call-actions">
            <button id="call-btn" class="call-button">📞 Call</button>
            <button id="clear-btn" class="clear-button">Clear</button>
        </div>
    </div>
</div>

<script>
// Dial pad functionality
document.addEventListener('DOMContentLoaded', function() {
    const dialNumber = document.getElementById('dial-number');
    const dialBtns = document.querySelectorAll('.dial-btn');
    const callBtn = document.getElementById('call-btn');
    const clearBtn = document.getElementById('clear-btn');

    dialBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            dialNumber.value += this.dataset.digit;
        });
    });

    clearBtn.addEventListener('click', function() {
        dialNumber.value = '';
    });

    callBtn.addEventListener('click', function() {
        const number = dialNumber.value.trim();
        if (number) {
            initiateCall(number);
        }
    });

    function initiateCall(number) {
        // Make AJAX call to FlexPBX API
        fetch('/flexpbx/api/call', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                from_extension: '{EXTENSION}',
                to_number: number
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                dialNumber.value = '';
                alert('Call initiated to ' + number);
            } else {
                alert('Call failed: ' + data.error);
            }
        })
        .catch(error => {
            alert('Error initiating call: ' + error);
        });
    }
});
</script>`
        );
    }

    /**
     * Install hooks on Composr server
     */
    async installFlexPBXHooks(sshConnection, serverPath) {
        if (!this.composrDetected) {
            throw new Error('Composr not detected on server');
        }

        try {
            const hooks = this.getFlexPBXHooks();
            const installResults = [];

            // Create hooks directory structure
            const baseHooksPath = path.posix.join(serverPath, 'sources_custom/hooks');
            await sshConnection.execCommand(`mkdir -p "${baseHooksPath}/blocks"`);

            // Install authentication hooks
            for (const [filename, content] of Object.entries(hooks.authentication)) {
                const filePath = path.posix.join(baseHooksPath, filename);
                await this.uploadFileContent(sshConnection, filePath, content);
                installResults.push({ type: 'authentication', file: filename, status: 'installed' });
            }

            // Install communication hooks
            for (const [filename, content] of Object.entries(hooks.communication)) {
                const filePath = path.posix.join(baseHooksPath, filename);
                await this.uploadFileContent(sshConnection, filePath, content);
                installResults.push({ type: 'communication', file: filename, status: 'installed' });
            }

            // Install user management hooks
            for (const [filename, content] of Object.entries(hooks.user_management)) {
                const filePath = path.posix.join(baseHooksPath, filename);
                await this.uploadFileContent(sshConnection, filePath, content);
                installResults.push({ type: 'user_management', file: filename, status: 'installed' });
            }

            // Install admin hooks
            for (const [filename, content] of Object.entries(hooks.admin)) {
                const filePath = path.posix.join(baseHooksPath, filename);
                await this.uploadFileContent(sshConnection, filePath, content);
                installResults.push({ type: 'admin', file: filename, status: 'installed' });
            }

            // Install block hooks
            for (const [blockName, blockFiles] of Object.entries(hooks.blocks)) {
                const blockPath = path.posix.join(baseHooksPath, 'blocks', blockName);
                await sshConnection.execCommand(`mkdir -p "${blockPath}"`);

                for (const [filename, content] of Object.entries(blockFiles)) {
                    const filePath = path.posix.join(blockPath, filename);
                    await this.uploadFileContent(sshConnection, filePath, content);
                    installResults.push({ type: 'block', block: blockName, file: filename, status: 'installed' });
                }
            }

            return {
                success: true,
                installed: installResults,
                message: `Installed ${installResults.length} FlexPBX hooks for Composr`
            };

        } catch (error) {
            return {
                success: false,
                error: error.message
            };
        }
    }

    /**
     * Upload file content via SSH
     */
    async uploadFileContent(sshConnection, filePath, content) {
        // Escape content for safe shell command
        const escapedContent = content.replace(/'/g, "'\"'\"'");

        // Write content to file
        await sshConnection.execCommand(`cat > "${filePath}" << 'EOF'
${content}
EOF`);

        // Set proper permissions
        await sshConnection.execCommand(`chmod 644 "${filePath}"`);
    }
}

module.exports = ComposrHooksService;