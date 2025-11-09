<?php
/**
 * FlexPBX Role Synchronization - Multi-Platform Integration
 * Syncs FlexPBX roles with WordPress, Composr CMS, and WHMCS
 */

require_once __DIR__ . '/roles.php';

/**
 * Role Mapping Configuration
 * Maps FlexPBX roles to equivalent roles in other systems
 */
$ROLE_MAPPINGS = [
    'wordpress' => [
        'superadmin' => 'administrator',
        'admin' => 'administrator',
        'manager' => 'editor',
        'support' => 'author',
        'developer' => 'contributor',
        'user' => 'subscriber',
        'guest' => 'subscriber'
    ],

    'composr' => [
        'superadmin' => 1,  // Admin group ID
        'admin' => 1,        // Admin group ID
        'manager' => 2,      // Staff group ID
        'support' => 3,      // Support group ID
        'developer' => 4,    // Developer group ID
        'user' => 5,         // Member group ID
        'guest' => 6         // Guest group ID
    ],

    'whmcs' => [
        'superadmin' => 'Full Administrator',
        'admin' => 'Full Administrator',
        'manager' => 'Support Manager',
        'support' => 'Support',
        'developer' => 'Support',
        'user' => 'Client',
        'guest' => 'Client'
    ]
];

/**
 * Get FlexPBX role from WordPress role
 */
function getFlexPBXRoleFromWordPress($wp_role) {
    global $ROLE_MAPPINGS;
    $mapping = array_flip($ROLE_MAPPINGS['wordpress']);
    return $mapping[$wp_role] ?? 'user';
}

/**
 * Get WordPress role from FlexPBX role
 */
function getWordPressRoleFromFlexPBX($flexpbx_role) {
    global $ROLE_MAPPINGS;
    return $ROLE_MAPPINGS['wordpress'][$flexpbx_role] ?? 'subscriber';
}

/**
 * Get FlexPBX role from Composr group ID
 */
function getFlexPBXRoleFromComposr($group_id) {
    global $ROLE_MAPPINGS;
    $mapping = array_flip($ROLE_MAPPINGS['composr']);
    return $mapping[$group_id] ?? 'user';
}

/**
 * Get Composr group ID from FlexPBX role
 */
function getComposrGroupFromFlexPBX($flexpbx_role) {
    global $ROLE_MAPPINGS;
    return $ROLE_MAPPINGS['composr'][$flexpbx_role] ?? 5;
}

/**
 * Get FlexPBX role from WHMCS role
 */
function getFlexPBXRoleFromWHMCS($whmcs_role) {
    global $ROLE_MAPPINGS;
    $mapping = array_flip($ROLE_MAPPINGS['whmcs']);
    return $mapping[$whmcs_role] ?? 'user';
}

/**
 * Get WHMCS role from FlexPBX role
 */
function getWHMCSRoleFromFlexPBX($flexpbx_role) {
    global $ROLE_MAPPINGS;
    return $ROLE_MAPPINGS['whmcs'][$flexpbx_role] ?? 'Client';
}

/**
 * Sync user role to WordPress
 * Requires WordPress database connection
 */
function syncRoleToWordPress($user_id, $flexpbx_role, $wp_db_config = null) {
    if (!$wp_db_config) {
        return ['success' => false, 'message' => 'WordPress database config not provided'];
    }

    try {
        $wp_pdo = new PDO(
            "mysql:host={$wp_db_config['host']};dbname={$wp_db_config['dbname']}",
            $wp_db_config['username'],
            $wp_db_config['password']
        );

        $wp_role = getWordPressRoleFromFlexPBX($flexpbx_role);
        $meta_key = $wp_db_config['prefix'] . 'capabilities';
        $meta_value = serialize([$wp_role => true]);

        // Update WordPress user meta
        $stmt = $wp_pdo->prepare("
            UPDATE {$wp_db_config['prefix']}usermeta
            SET meta_value = ?
            WHERE user_id = ? AND meta_key = ?
        ");

        $stmt->execute([$meta_value, $user_id, $meta_key]);

        return [
            'success' => true,
            'message' => "Role synced to WordPress: $wp_role",
            'wp_role' => $wp_role
        ];

    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'WordPress sync failed: ' . $e->getMessage()];
    }
}

/**
 * Sync user role to Composr
 * Requires Composr database connection
 */
function syncRoleToComposr($user_id, $flexpbx_role, $composr_db_config = null) {
    if (!$composr_db_config) {
        return ['success' => false, 'message' => 'Composr database config not provided'];
    }

    try {
        $composr_pdo = new PDO(
            "mysql:host={$composr_db_config['host']};dbname={$composr_db_config['dbname']}",
            $composr_db_config['username'],
            $composr_db_config['password']
        );

        $group_id = getComposrGroupFromFlexPBX($flexpbx_role);

        // Update Composr user group
        $stmt = $composr_pdo->prepare("
            UPDATE {$composr_db_config['prefix']}members
            SET m_primary_group = ?
            WHERE id = ?
        ");

        $stmt->execute([$group_id, $user_id]);

        return [
            'success' => true,
            'message' => "Role synced to Composr: Group $group_id",
            'group_id' => $group_id
        ];

    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Composr sync failed: ' . $e->getMessage()];
    }
}

/**
 * Sync user role to WHMCS
 * Requires WHMCS database connection
 */
function syncRoleToWHMCS($user_id, $flexpbx_role, $whmcs_db_config = null) {
    if (!$whmcs_db_config) {
        return ['success' => false, 'message' => 'WHMCS database config not provided'];
    }

    try {
        $whmcs_pdo = new PDO(
            "mysql:host={$whmcs_db_config['host']};dbname={$whmcs_db_config['dbname']}",
            $whmcs_db_config['username'],
            $whmcs_db_config['password']
        );

        $whmcs_role = getWHMCSRoleFromFlexPBX($flexpbx_role);

        // For WHMCS admins
        if (in_array($flexpbx_role, ['superadmin', 'admin', 'manager', 'support'])) {
            $stmt = $whmcs_pdo->prepare("
                UPDATE tbladmins
                SET roleid = (SELECT id FROM tbladminroles WHERE name = ? LIMIT 1)
                WHERE id = ?
            ");

            $stmt->execute([$whmcs_role, $user_id]);

            return [
                'success' => true,
                'message' => "Role synced to WHMCS: $whmcs_role",
                'whmcs_role' => $whmcs_role
            ];
        }

        return ['success' => true, 'message' => 'User is client, no role sync needed'];

    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'WHMCS sync failed: ' . $e->getMessage()];
    }
}

/**
 * Sync role across all platforms
 */
function syncRoleToAllPlatforms($user_id, $flexpbx_role, $configs = []) {
    $results = [
        'flexpbx_role' => $flexpbx_role,
        'syncs' => []
    ];

    // Sync to WordPress
    if (isset($configs['wordpress'])) {
        $results['syncs']['wordpress'] = syncRoleToWordPress($user_id, $flexpbx_role, $configs['wordpress']);
    }

    // Sync to Composr
    if (isset($configs['composr'])) {
        $results['syncs']['composr'] = syncRoleToComposr($user_id, $flexpbx_role, $configs['composr']);
    }

    // Sync to WHMCS
    if (isset($configs['whmcs'])) {
        $results['syncs']['whmcs'] = syncRoleToWHMCS($user_id, $flexpbx_role, $configs['whmcs']);
    }

    return $results;
}

/**
 * Get role from any platform and determine FlexPBX role
 */
function unifyRole($platform, $platform_role) {
    switch ($platform) {
        case 'wordpress':
            return getFlexPBXRoleFromWordPress($platform_role);

        case 'composr':
            return getFlexPBXRoleFromComposr($platform_role);

        case 'whmcs':
            return getFlexPBXRoleFromWHMCS($platform_role);

        case 'flexpbx':
        default:
            return $platform_role;
    }
}

/**
 * API endpoint for role synchronization
 */
if (isset($_GET['api'])) {
    header('Content-Type: application/json');

    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'sync':
            $user_id = $_POST['user_id'] ?? 0;
            $flexpbx_role = $_POST['role'] ?? 'user';
            $configs = $_POST['configs'] ?? [];

            $result = syncRoleToAllPlatforms($user_id, $flexpbx_role, $configs);
            echo json_encode(['success' => true, 'data' => $result]);
            break;

        case 'unify':
            $platform = $_POST['platform'] ?? '';
            $platform_role = $_POST['role'] ?? '';

            $flexpbx_role = unifyRole($platform, $platform_role);
            echo json_encode(['success' => true, 'flexpbx_role' => $flexpbx_role]);
            break;

        case 'mappings':
            echo json_encode(['success' => true, 'mappings' => $ROLE_MAPPINGS]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    exit;
}
?>
