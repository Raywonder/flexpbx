<?php
/**
 * FlexPBX Role Management API
 * Handles all role and permission management operations
 */

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set JSON header
header('Content-Type: application/json');

// Include authentication
require_once __DIR__ . '/auth.php';

// Paths
$admins_dir = '/home/flexpbxuser/admins';
$users_dir = '/home/flexpbxuser/users';
$audit_log_file = '/home/flexpbxuser/data/role_audit_log.json';
$data_dir = '/home/flexpbxuser/data';

// Ensure data directory exists
if (!file_exists($data_dir)) {
    mkdir($data_dir, 0750, true);
}

// Define role permissions
$ROLE_DEFINITIONS = [
    'superadmin' => [
        'name' => 'Super Administrator',
        'level' => 100,
        'description' => 'Full system access with all permissions',
        'permissions' => [
            'manage_users' => true,
            'manage_roles' => true,
            'manage_extensions' => true,
            'manage_trunks' => true,
            'manage_routing' => true,
            'view_call_logs' => true,
            'manage_system' => true,
            'manage_security' => true,
            'view_audit_logs' => true,
            'manage_backups' => true,
            'full_access' => true
        ]
    ],
    'admin' => [
        'name' => 'Administrator',
        'level' => 90,
        'description' => 'Manage users and settings',
        'permissions' => [
            'manage_users' => true,
            'manage_roles' => false,
            'manage_extensions' => true,
            'manage_trunks' => true,
            'manage_routing' => true,
            'view_call_logs' => true,
            'manage_system' => true,
            'manage_security' => false,
            'view_audit_logs' => true,
            'manage_backups' => true,
            'full_access' => false
        ]
    ],
    'manager' => [
        'name' => 'Manager',
        'level' => 70,
        'description' => 'Manage team and extensions',
        'permissions' => [
            'manage_users' => false,
            'manage_roles' => false,
            'manage_extensions' => true,
            'manage_trunks' => false,
            'manage_routing' => false,
            'view_call_logs' => true,
            'manage_system' => false,
            'manage_security' => false,
            'view_audit_logs' => false,
            'manage_backups' => false,
            'full_access' => false
        ]
    ],
    'support' => [
        'name' => 'Support Staff',
        'level' => 50,
        'description' => 'View only, manage tickets',
        'permissions' => [
            'manage_users' => false,
            'manage_roles' => false,
            'manage_extensions' => false,
            'manage_trunks' => false,
            'manage_routing' => false,
            'view_call_logs' => true,
            'manage_system' => false,
            'manage_security' => false,
            'view_audit_logs' => false,
            'manage_backups' => false,
            'full_access' => false
        ]
    ],
    'user' => [
        'name' => 'User',
        'level' => 20,
        'description' => 'Basic access',
        'permissions' => [
            'manage_users' => false,
            'manage_roles' => false,
            'manage_extensions' => false,
            'manage_trunks' => false,
            'manage_routing' => false,
            'view_call_logs' => false,
            'manage_system' => false,
            'manage_security' => false,
            'view_audit_logs' => false,
            'manage_backups' => false,
            'full_access' => false
        ]
    ],
    'guest' => [
        'name' => 'Guest',
        'level' => 10,
        'description' => 'Limited read-only access',
        'permissions' => [
            'manage_users' => false,
            'manage_roles' => false,
            'manage_extensions' => false,
            'manage_trunks' => false,
            'manage_routing' => false,
            'view_call_logs' => false,
            'manage_system' => false,
            'manage_security' => false,
            'view_audit_logs' => false,
            'manage_backups' => false,
            'full_access' => false
        ]
    ]
];

// Check authentication and admin role
$auth = checkAuth();
if (!$auth['authenticated']) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required'
    ]);
    exit;
}

// Check if user has admin role
$allowed_roles = ['superadmin', 'super_admin', 'admin', 'manager'];
$user_role = $auth['role'] ?? 'user';
$has_admin_access = in_array($user_role, $allowed_roles) || $auth['user_type'] === 'admin';

if (!$has_admin_access) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Admin access required'
    ]);
    exit;
}

// Get action
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Route to appropriate handler
switch ($action) {
    case 'list_users':
        listUsers();
        break;

    case 'get_user':
        getUser();
        break;

    case 'change_role':
        changeRole();
        break;

    case 'get_permissions':
        getPermissions();
        break;

    case 'get_audit_log':
        getAuditLog();
        break;

    case 'get_stats':
        getStats();
        break;

    default:
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid action'
        ]);
        break;
}

/**
 * List all users (admins and regular users)
 */
function listUsers() {
    global $admins_dir, $users_dir;

    $all_users = [];

    // Load admin users
    if (file_exists($admins_dir)) {
        $admin_files = glob($admins_dir . '/admin_*.json');
        foreach ($admin_files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if ($data) {
                $all_users[] = [
                    'id' => basename($file, '.json'),
                    'username' => $data['username'] ?? 'unknown',
                    'email' => $data['email'] ?? '',
                    'full_name' => $data['full_name'] ?? '',
                    'role' => $data['role'] ?? 'user',
                    'extension' => $data['linked_extension'] ?? null,
                    'is_active' => $data['is_active'] ?? true,
                    'last_login' => $data['last_login'] ?? null,
                    'created_at' => $data['created_at'] ?? null,
                    'type' => 'admin'
                ];
            }
        }
    }

    // Load regular users (from extensions config or user directory)
    $extensions_config = '/home/flexpbxuser/public_html/config/extensions-config.json';
    if (file_exists($extensions_config)) {
        $config = json_decode(file_get_contents($extensions_config), true);
        if (isset($config['extensions'])) {
            foreach ($config['extensions'] as $ext => $ext_data) {
                $all_users[] = [
                    'id' => 'ext_' . $ext,
                    'username' => $ext_data['username'] ?? $ext,
                    'email' => $ext_data['email'] ?? $ext_data['voicemail_email'] ?? '',
                    'full_name' => $ext_data['display_name'] ?? '',
                    'role' => $ext_data['role'] ?? 'user',
                    'extension' => $ext,
                    'is_active' => true,
                    'last_login' => null,
                    'created_at' => null,
                    'type' => 'extension'
                ];
            }
        }
    }

    echo json_encode([
        'success' => true,
        'users' => $all_users,
        'total' => count($all_users)
    ]);
}

/**
 * Get specific user details
 */
function getUser() {
    global $admins_dir;

    $username = $_GET['username'] ?? '';
    if (empty($username)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Username required'
        ]);
        return;
    }

    // Try to find admin
    $admin_file = $admins_dir . '/admin_' . $username . '.json';
    if (file_exists($admin_file)) {
        $data = json_decode(file_get_contents($admin_file), true);
        echo json_encode([
            'success' => true,
            'user' => $data
        ]);
        return;
    }

    // Try to find in extensions
    $extensions_config = '/home/flexpbxuser/public_html/config/extensions-config.json';
    if (file_exists($extensions_config)) {
        $config = json_decode(file_get_contents($extensions_config), true);
        if (isset($config['extensions'])) {
            foreach ($config['extensions'] as $ext => $ext_data) {
                if (($ext_data['username'] ?? '') === $username || $ext === $username) {
                    echo json_encode([
                        'success' => true,
                        'user' => $ext_data
                    ]);
                    return;
                }
            }
        }
    }

    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'User not found'
    ]);
}

/**
 * Change user role
 */
function changeRole() {
    global $admins_dir, $auth, $ROLE_DEFINITIONS;

    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);
    $username = $input['username'] ?? '';
    $new_role = $input['new_role'] ?? '';
    $new_extension = $input['new_extension'] ?? null;
    $reason = $input['reason'] ?? 'No reason provided';

    // Validate input
    if (empty($username) || empty($new_role)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Username and new role are required'
        ]);
        return;
    }

    // Check if role exists
    if (!isset($ROLE_DEFINITIONS[$new_role])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid role'
        ]);
        return;
    }

    // Check if user can assign this role
    $current_user_role = $auth['role'] ?? 'user';
    $current_user_level = $ROLE_DEFINITIONS[$current_user_role]['level'] ?? 0;
    $new_role_level = $ROLE_DEFINITIONS[$new_role]['level'];

    // Only superadmin can assign superadmin role
    if ($new_role === 'superadmin' && !in_array($current_user_role, ['superadmin', 'super_admin'])) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Only SuperAdmin can assign SuperAdmin role'
        ]);
        return;
    }

    // Cannot assign role higher than own role
    if ($new_role_level >= $current_user_level && !in_array($current_user_role, ['superadmin', 'super_admin'])) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Cannot assign role equal to or higher than your own'
        ]);
        return;
    }

    // Find and update user
    $admin_file = $admins_dir . '/admin_' . $username . '.json';
    if (file_exists($admin_file)) {
        $data = json_decode(file_get_contents($admin_file), true);
        $old_role = $data['role'] ?? 'unknown';
        $old_extension = $data['linked_extension'] ?? null;

        // Update role
        $data['role'] = $new_role;
        $data['updated_at'] = date('Y-m-d H:i:s');
        $data['updated_by'] = $auth['username'];

        // Update extension if provided
        if ($new_extension !== null && $new_extension !== '') {
            $data['linked_extension'] = $new_extension;
        }

        // Save updated data
        file_put_contents($admin_file, json_encode($data, JSON_PRETTY_PRINT));

        // Log the change
        logAuditEntry([
            'action' => 'Role Changed',
            'action_type' => 'role_changed',
            'target_user' => $username,
            'admin_user' => $auth['username'],
            'details' => "Changed from {$old_role} to {$new_role}" .
                        ($new_extension && $new_extension !== $old_extension ? " | Extension: {$old_extension} → {$new_extension}" : ''),
            'reason' => $reason,
            'timestamp' => date('Y-m-d H:i:s'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);

        echo json_encode([
            'success' => true,
            'message' => "Role changed successfully from {$old_role} to {$new_role}",
            'user' => $data
        ]);
        return;
    }

    // Try to update in extensions config
    $extensions_config = '/home/flexpbxuser/public_html/config/extensions-config.json';
    if (file_exists($extensions_config)) {
        $config = json_decode(file_get_contents($extensions_config), true);
        if (isset($config['extensions'])) {
            foreach ($config['extensions'] as $ext => &$ext_data) {
                if (($ext_data['username'] ?? '') === $username || $ext === $username) {
                    $old_role = $ext_data['role'] ?? 'unknown';

                    // Update role
                    $ext_data['role'] = $new_role;
                    $ext_data['updated_at'] = date('Y-m-d H:i:s');

                    // Save config
                    file_put_contents($extensions_config, json_encode($config, JSON_PRETTY_PRINT));

                    // Log the change
                    logAuditEntry([
                        'action' => 'Role Changed',
                        'action_type' => 'role_changed',
                        'target_user' => $username,
                        'admin_user' => $auth['username'],
                        'details' => "Changed from {$old_role} to {$new_role} (Extension User)",
                        'reason' => $reason,
                        'timestamp' => date('Y-m-d H:i:s'),
                        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                    ]);

                    echo json_encode([
                        'success' => true,
                        'message' => "Role changed successfully from {$old_role} to {$new_role}",
                        'user' => $ext_data
                    ]);
                    return;
                }
            }
        }
    }

    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'User not found'
    ]);
}

/**
 * Get role permissions matrix
 */
function getPermissions() {
    global $ROLE_DEFINITIONS;

    echo json_encode([
        'success' => true,
        'permissions' => $ROLE_DEFINITIONS
    ]);
}

/**
 * Get audit log
 */
function getAuditLog() {
    global $audit_log_file;

    $log = [];
    if (file_exists($audit_log_file)) {
        $log = json_decode(file_get_contents($audit_log_file), true) ?? [];
    }

    // Sort by timestamp (newest first)
    usort($log, function($a, $b) {
        return strtotime($b['timestamp']) - strtotime($a['timestamp']);
    });

    // Limit to last 100 entries
    $log = array_slice($log, 0, 100);

    echo json_encode([
        'success' => true,
        'audit_log' => $log,
        'total' => count($log)
    ]);
}

/**
 * Get statistics
 */
function getStats() {
    global $admins_dir, $users_dir;

    $stats = [
        'total_users' => 0,
        'active_users' => 0,
        'inactive_users' => 0,
        'admins' => 0,
        'managers' => 0,
        'support' => 0,
        'users' => 0,
        'guests' => 0,
        'role_distribution' => []
    ];

    // Count admin users
    if (file_exists($admins_dir)) {
        $admin_files = glob($admins_dir . '/admin_*.json');
        foreach ($admin_files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if ($data) {
                $stats['total_users']++;
                $is_active = $data['is_active'] ?? true;

                if ($is_active) {
                    $stats['active_users']++;
                } else {
                    $stats['inactive_users']++;
                }

                $role = $data['role'] ?? 'user';

                // Count by role type
                if (in_array($role, ['superadmin', 'super_admin', 'admin'])) {
                    $stats['admins']++;
                } elseif ($role === 'manager') {
                    $stats['managers']++;
                } elseif ($role === 'support') {
                    $stats['support']++;
                } elseif ($role === 'user') {
                    $stats['users']++;
                } elseif ($role === 'guest') {
                    $stats['guests']++;
                }

                // Role distribution
                if (!isset($stats['role_distribution'][$role])) {
                    $stats['role_distribution'][$role] = 0;
                }
                $stats['role_distribution'][$role]++;
            }
        }
    }

    // Count extension users
    $extensions_config = '/home/flexpbxuser/public_html/config/extensions-config.json';
    if (file_exists($extensions_config)) {
        $config = json_decode(file_get_contents($extensions_config), true);
        if (isset($config['extensions'])) {
            foreach ($config['extensions'] as $ext_data) {
                $stats['total_users']++;
                $stats['active_users']++; // Extensions are assumed active

                $role = $ext_data['role'] ?? 'user';

                // Count by role type
                if (in_array($role, ['superadmin', 'super_admin', 'admin'])) {
                    $stats['admins']++;
                } elseif ($role === 'manager') {
                    $stats['managers']++;
                } elseif ($role === 'support') {
                    $stats['support']++;
                } elseif ($role === 'user') {
                    $stats['users']++;
                } elseif ($role === 'guest') {
                    $stats['guests']++;
                }

                // Role distribution
                if (!isset($stats['role_distribution'][$role])) {
                    $stats['role_distribution'][$role] = 0;
                }
                $stats['role_distribution'][$role]++;
            }
        }
    }

    echo json_encode([
        'success' => true,
        'stats' => $stats
    ]);
}

/**
 * Log audit entry
 */
function logAuditEntry($entry) {
    global $audit_log_file;

    $log = [];
    if (file_exists($audit_log_file)) {
        $log = json_decode(file_get_contents($audit_log_file), true) ?? [];
    }

    // Add new entry
    $log[] = $entry;

    // Keep only last 1000 entries
    if (count($log) > 1000) {
        $log = array_slice($log, -1000);
    }

    // Save log
    file_put_contents($audit_log_file, json_encode($log, JSON_PRETTY_PRINT));
}

?>
