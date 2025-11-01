<?php
/**
 * FlexPBX Bug Tracker - Role Configuration
 * Defines user roles and their permissions
 */

// User Roles Definition
$ROLES = [
    'superadmin' => [
        'name' => 'Super Administrator',
        'level' => 100,
        'permissions' => [
            'view_all_bugs' => true,
            'create_bug' => true,
            'edit_own_bug' => true,
            'edit_any_bug' => true,
            'delete_bug' => true,
            'assign_bugs' => true,
            'change_status' => true,
            'view_analytics' => true,
            'export_data' => true,
            'manage_users' => true,
            'manage_roles' => true,
            'access_admin_panel' => true,
            'bulk_operations' => true,
            'view_internal_notes' => true,
            'add_internal_notes' => true
        ],
        'dashboard' => '/admin/bug-tracker.php',
        'description' => 'Full system access with all permissions'
    ],

    'admin' => [
        'name' => 'Administrator',
        'level' => 90,
        'permissions' => [
            'view_all_bugs' => true,
            'create_bug' => true,
            'edit_own_bug' => true,
            'edit_any_bug' => true,
            'delete_bug' => false,
            'assign_bugs' => true,
            'change_status' => true,
            'view_analytics' => true,
            'export_data' => true,
            'manage_users' => false,
            'manage_roles' => false,
            'access_admin_panel' => true,
            'bulk_operations' => true,
            'view_internal_notes' => true,
            'add_internal_notes' => true
        ],
        'dashboard' => '/admin/bug-tracker.php',
        'description' => 'Admin access with bug management permissions'
    ],

    'manager' => [
        'name' => 'Manager',
        'level' => 70,
        'permissions' => [
            'view_all_bugs' => true,
            'create_bug' => true,
            'edit_own_bug' => true,
            'edit_any_bug' => true,
            'delete_bug' => false,
            'assign_bugs' => true,
            'change_status' => true,
            'view_analytics' => true,
            'export_data' => true,
            'manage_users' => false,
            'manage_roles' => false,
            'access_admin_panel' => true,
            'bulk_operations' => false,
            'view_internal_notes' => true,
            'add_internal_notes' => true
        ],
        'dashboard' => '/admin/bug-tracker.php',
        'description' => 'Manage team bugs and view reports'
    ],

    'support' => [
        'name' => 'Support Staff',
        'level' => 60,
        'permissions' => [
            'view_all_bugs' => true,
            'create_bug' => true,
            'edit_own_bug' => true,
            'edit_any_bug' => false,
            'delete_bug' => false,
            'assign_bugs' => false,
            'change_status' => true,
            'view_analytics' => false,
            'export_data' => false,
            'manage_users' => false,
            'manage_roles' => false,
            'access_admin_panel' => false,
            'bulk_operations' => false,
            'view_internal_notes' => true,
            'add_internal_notes' => true
        ],
        'dashboard' => '/bugtracker/support-dashboard.php',
        'description' => 'View and respond to bug reports'
    ],

    'developer' => [
        'name' => 'Developer',
        'level' => 50,
        'permissions' => [
            'view_all_bugs' => true,
            'create_bug' => true,
            'edit_own_bug' => true,
            'edit_any_bug' => false,
            'delete_bug' => false,
            'assign_bugs' => false,
            'change_status' => true,
            'view_analytics' => true,
            'export_data' => false,
            'manage_users' => false,
            'manage_roles' => false,
            'access_admin_panel' => false,
            'bulk_operations' => false,
            'view_internal_notes' => true,
            'add_internal_notes' => true
        ],
        'dashboard' => '/bugtracker/developer-dashboard.php',
        'description' => 'Fix bugs and update status'
    ],

    'user' => [
        'name' => 'User',
        'level' => 20,
        'permissions' => [
            'view_all_bugs' => false,
            'create_bug' => true,
            'edit_own_bug' => true,
            'edit_any_bug' => false,
            'delete_bug' => false,
            'assign_bugs' => false,
            'change_status' => false,
            'view_analytics' => false,
            'export_data' => false,
            'manage_users' => false,
            'manage_roles' => false,
            'access_admin_panel' => false,
            'bulk_operations' => false,
            'view_internal_notes' => false,
            'add_internal_notes' => false
        ],
        'dashboard' => '/bugtracker/user-dashboard.php',
        'description' => 'Submit and view own bug reports'
    ],

    'guest' => [
        'name' => 'Guest',
        'level' => 10,
        'permissions' => [
            'view_all_bugs' => false,
            'create_bug' => false,
            'edit_own_bug' => false,
            'edit_any_bug' => false,
            'delete_bug' => false,
            'assign_bugs' => false,
            'change_status' => false,
            'view_analytics' => false,
            'export_data' => false,
            'manage_users' => false,
            'manage_roles' => false,
            'access_admin_panel' => false,
            'bulk_operations' => false,
            'view_internal_notes' => false,
            'add_internal_notes' => false
        ],
        'dashboard' => '/bug-tracker/index.html',
        'description' => 'Read-only access to public bug list'
    ]
];

/**
 * Get role information
 */
function getRole($role_name) {
    global $ROLES;
    return $ROLES[$role_name] ?? $ROLES['guest'];
}

/**
 * Check if user has permission
 */
function hasPermission($role_name, $permission) {
    $role = getRole($role_name);
    return $role['permissions'][$permission] ?? false;
}

/**
 * Get role level
 */
function getRoleLevel($role_name) {
    $role = getRole($role_name);
    return $role['level'] ?? 0;
}

/**
 * Compare roles
 */
function isRoleHigherThan($role1, $role2) {
    return getRoleLevel($role1) > getRoleLevel($role2);
}

/**
 * Get user's role from session
 */
function getUserRole() {
    // Admin users
    if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
        return $_SESSION['admin_role'] ?? 'admin';
    }

    // Regular users
    if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true) {
        return $_SESSION['user_role'] ?? 'user';
    }

    // Not logged in
    return 'guest';
}

/**
 * Get appropriate dashboard for user's role
 */
function getRoleDashboard($role_name = null) {
    if ($role_name === null) {
        $role_name = getUserRole();
    }
    $role = getRole($role_name);
    return $role['dashboard'];
}

/**
 * Require minimum role level
 */
function requireRole($required_role) {
    $user_role = getUserRole();
    $required_level = getRoleLevel($required_role);
    $user_level = getRoleLevel($user_role);

    if ($user_level < $required_level) {
        http_response_code(403);
        die("Access denied. Required role: $required_role");
    }
}

/**
 * Require specific permission
 */
function requirePermission($permission) {
    $user_role = getUserRole();
    if (!hasPermission($user_role, $permission)) {
        http_response_code(403);
        die("Access denied. Missing permission: $permission");
    }
}

/**
 * Get all available roles
 */
function getAllRoles() {
    global $ROLES;
    return $ROLES;
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['admin_logged_in']) || isset($_SESSION['user_logged_in']);
}

/**
 * Get user identity
 */
function getUserIdentity() {
    if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
        return [
            'type' => 'admin',
            'username' => $_SESSION['admin_username'] ?? 'admin',
            'full_name' => $_SESSION['admin_full_name'] ?? 'Administrator',
            'role' => $_SESSION['admin_role'] ?? 'admin',
            'email' => $_SESSION['admin_email'] ?? ''
        ];
    }

    if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true) {
        return [
            'type' => 'user',
            'username' => $_SESSION['user_username'] ?? 'user',
            'extension' => $_SESSION['user_extension'] ?? '',
            'role' => $_SESSION['user_role'] ?? 'user',
            'email' => $_SESSION['user_email'] ?? ''
        ];
    }

    return [
        'type' => 'guest',
        'username' => 'guest',
        'role' => 'guest'
    ];
}
?>
