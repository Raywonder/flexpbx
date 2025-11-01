<?php
require_once __DIR__ . '/admin_auth_check.php';
/**
 * FlexPBX Role Management System
 * Admin interface for managing user roles and permissions
 */

session_start();

// Check if user is logged in and has admin role
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /admin/login.php');
    exit;
}

// Check for admin/superadmin role
$admin_role = $_SESSION['admin_role'] ?? 'user';
$allowed_roles = ['superadmin', 'super_admin', 'admin'];
if (!in_array($admin_role, $allowed_roles)) {
    die('Access denied. Admin or SuperAdmin role required.');
}

$current_admin_username = $_SESSION['admin_username'] ?? 'admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Role Management - FlexPBX Admin</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .header-actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            display: inline-block;
            padding: 0.6rem 1.2rem;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .btn-primary {
            background: #4CAF50;
            color: white;
        }

        .btn-primary:hover {
            background: #45a049;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: white;
            color: #667eea;
        }

        .btn-secondary:hover {
            background: #f0f0f0;
        }

        .btn-danger {
            background: #f44336;
            color: white;
        }

        .btn-danger:hover {
            background: #da190b;
        }

        .btn-warning {
            background: #ff9800;
            color: white;
        }

        .btn-warning:hover {
            background: #e68900;
        }

        .content {
            padding: 2rem;
        }

        .tabs {
            display: flex;
            gap: 5px;
            border-bottom: 2px solid #e0e0e0;
            margin-bottom: 2rem;
        }

        .tab {
            padding: 1rem 1.5rem;
            cursor: pointer;
            border: none;
            background: transparent;
            color: #666;
            font-weight: 600;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
        }

        .tab.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }

        .tab:hover {
            background: #f5f5f5;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .search-filter {
            margin-bottom: 2rem;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }

        .search-filter input,
        .search-filter select {
            padding: 0.7rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.95rem;
        }

        .search-filter input {
            flex: 1;
            min-width: 250px;
        }

        .search-filter select {
            min-width: 150px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2rem;
        }

        table th,
        table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }

        table th {
            background: #f5f5f5;
            font-weight: 700;
            color: #333;
            position: sticky;
            top: 0;
        }

        table tr:hover {
            background: #f9f9f9;
        }

        .badge {
            display: inline-block;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .badge-superadmin {
            background: #ff6b6b;
            color: white;
        }

        .badge-admin {
            background: #667eea;
            color: white;
        }

        .badge-manager {
            background: #4CAF50;
            color: white;
        }

        .badge-support {
            background: #ff9800;
            color: white;
        }

        .badge-user {
            background: #2196F3;
            color: white;
        }

        .badge-guest {
            background: #9e9e9e;
            color: white;
        }

        .badge-active {
            background: #4CAF50;
            color: white;
        }

        .badge-inactive {
            background: #f44336;
            color: white;
        }

        .permissions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }

        .permission-card {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 1.5rem;
            background: white;
        }

        .permission-card h3 {
            color: #333;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .permission-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .permission-item:last-child {
            border-bottom: none;
        }

        .permission-check {
            font-size: 1.2rem;
        }

        .check-yes {
            color: #4CAF50;
        }

        .check-no {
            color: #f44336;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #e0e0e0;
        }

        .modal-header h2 {
            color: #333;
            font-size: 1.5rem;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 2rem;
            cursor: pointer;
            color: #666;
            line-height: 1;
        }

        .close-modal:hover {
            color: #333;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-group small {
            display: block;
            margin-top: 0.3rem;
            color: #666;
            font-size: 0.85rem;
        }

        .alert {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
        }

        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .alert-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .alert-info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }

        .audit-log {
            max-height: 600px;
            overflow-y: auto;
        }

        .audit-entry {
            padding: 1rem;
            border-left: 4px solid #667eea;
            margin-bottom: 1rem;
            background: #f9f9f9;
            border-radius: 4px;
        }

        .audit-entry .timestamp {
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 0.5rem;
        }

        .audit-entry .action {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.3rem;
        }

        .audit-entry .details {
            font-size: 0.9rem;
            color: #555;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .stat-card h3 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .stat-card p {
            opacity: 0.9;
            font-size: 0.95rem;
        }

        .loading {
            text-align: center;
            padding: 2rem;
            color: #666;
        }

        .loading::after {
            content: '...';
            animation: loading 1.5s infinite;
        }

        @keyframes loading {
            0%, 20% { content: '.'; }
            40% { content: '..'; }
            60%, 100% { content: '...'; }
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 0.5rem;
        }

        .checkbox-group input[type="checkbox"] {
            width: auto;
            margin: 0;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
        }

        .btn-sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.85rem;
        }

        @media (max-width: 768px) {
            .container {
                border-radius: 0;
            }

            .header {
                flex-direction: column;
                gap: 15px;
            }

            .search-filter {
                flex-direction: column;
            }

            .search-filter input,
            .search-filter select {
                width: 100%;
            }

            table {
                font-size: 0.9rem;
            }

            table th,
            table td {
                padding: 0.7rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>
                <span>👥</span>
                Role Management System
            </h1>
            <div class="header-actions">
                <button class="btn btn-secondary" onclick="refreshData()">🔄 Refresh</button>
                <a href="/admin/dashboard.html" class="btn btn-secondary">← Dashboard</a>
            </div>
        </div>

        <div class="content">
            <div id="alerts"></div>

            <div class="tabs">
                <button class="tab active" data-tab="users">Users & Roles</button>
                <button class="tab" data-tab="permissions">Permissions Matrix</button>
                <button class="tab" data-tab="audit">Audit Log</button>
                <button class="tab" data-tab="stats">Statistics</button>
            </div>

            <!-- Users & Roles Tab -->
            <div id="tab-users" class="tab-content active">
                <div class="search-filter">
                    <input type="text" id="search-users" placeholder="🔍 Search by username, email, or extension...">
                    <select id="filter-role">
                        <option value="">All Roles</option>
                        <option value="superadmin">SuperAdmin</option>
                        <option value="admin">Admin</option>
                        <option value="manager">Manager</option>
                        <option value="support">Support</option>
                        <option value="user">User</option>
                        <option value="guest">Guest</option>
                    </select>
                    <select id="filter-status">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>

                <div id="users-table-container">
                    <div class="loading">Loading users...</div>
                </div>
            </div>

            <!-- Permissions Matrix Tab -->
            <div id="tab-permissions" class="tab-content">
                <h2>Role Permissions Matrix</h2>
                <p style="margin-bottom: 1.5rem; color: #666;">View detailed permissions for each role in the system.</p>
                <div id="permissions-matrix">
                    <div class="loading">Loading permissions...</div>
                </div>
            </div>

            <!-- Audit Log Tab -->
            <div id="tab-audit" class="tab-content">
                <h2>Role Change Audit Log</h2>
                <p style="margin-bottom: 1.5rem; color: #666;">Track all role and permission changes made by administrators.</p>
                <div class="search-filter">
                    <input type="text" id="search-audit" placeholder="🔍 Search audit logs...">
                    <select id="filter-audit-action">
                        <option value="">All Actions</option>
                        <option value="role_changed">Role Changed</option>
                        <option value="extension_changed">Extension Changed</option>
                        <option value="user_created">User Created</option>
                        <option value="user_deactivated">User Deactivated</option>
                    </select>
                </div>
                <div id="audit-log" class="audit-log">
                    <div class="loading">Loading audit log...</div>
                </div>
            </div>

            <!-- Statistics Tab -->
            <div id="tab-stats" class="tab-content">
                <h2>Role Distribution Statistics</h2>
                <p style="margin-bottom: 1.5rem; color: #666;">Overview of user roles and system usage.</p>
                <div id="stats-container">
                    <div class="loading">Loading statistics...</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Change Role Modal -->
    <div id="modal-change-role" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Change User Role</h2>
                <button class="close-modal" onclick="closeModal('modal-change-role')">&times;</button>
            </div>
            <form id="form-change-role" onsubmit="handleRoleChange(event)">
                <input type="hidden" id="change-user-id" name="user_id">
                <input type="hidden" id="change-username" name="username">

                <div class="form-group">
                    <label>Current User</label>
                    <input type="text" id="change-current-user" disabled>
                </div>

                <div class="form-group">
                    <label>Current Role</label>
                    <input type="text" id="change-current-role" disabled>
                </div>

                <div class="form-group">
                    <label for="change-new-role">New Role *</label>
                    <select id="change-new-role" name="new_role" required>
                        <option value="">-- Select New Role --</option>
                        <option value="superadmin">SuperAdmin - Full system access</option>
                        <option value="admin">Admin - Manage users and settings</option>
                        <option value="manager">Manager - Manage team and extensions</option>
                        <option value="support">Support - View only, manage tickets</option>
                        <option value="user">User - Basic access</option>
                        <option value="guest">Guest - Limited read-only access</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Current Extension</label>
                    <input type="text" id="change-current-extension" disabled>
                </div>

                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="change-extension-checkbox" name="change_extension">
                        <label for="change-extension-checkbox" style="margin: 0;">Assign different extension</label>
                    </div>
                </div>

                <div class="form-group" id="new-extension-group" style="display: none;">
                    <label for="change-new-extension">New Extension</label>
                    <input type="text" id="change-new-extension" name="new_extension" placeholder="e.g., 2000">
                    <small>Leave blank to keep current extension, or enter new extension number</small>
                </div>

                <div class="form-group">
                    <label for="change-reason">Reason for Change *</label>
                    <input type="text" id="change-reason" name="reason" required placeholder="e.g., Promotion to manager role">
                    <small>This will be logged in the audit trail</small>
                </div>

                <div class="form-group">
                    <div class="alert alert-info">
                        <strong>⚠️ Important:</strong> Changing a user's role will update their system permissions immediately. The user may need to log out and back in for changes to take full effect.
                    </div>
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('modal-change-role')">Cancel</button>
                    <button type="submit" class="btn btn-primary">💾 Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Global state
        let users = [];
        let auditLog = [];
        let stats = {};

        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            initializeTabs();
            loadAllData();
            setupEventListeners();
        });

        // Tab management
        function initializeTabs() {
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    const tabName = tab.getAttribute('data-tab');
                    switchTab(tabName);
                });
            });
        }

        function switchTab(tabName) {
            // Update tab buttons
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelector(`[data-tab="${tabName}"]`).classList.add('active');

            // Update tab content
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            document.getElementById(`tab-${tabName}`).classList.add('active');
        }

        // Event listeners
        function setupEventListeners() {
            document.getElementById('search-users').addEventListener('input', filterUsers);
            document.getElementById('filter-role').addEventListener('change', filterUsers);
            document.getElementById('filter-status').addEventListener('change', filterUsers);
            document.getElementById('search-audit').addEventListener('input', filterAuditLog);
            document.getElementById('filter-audit-action').addEventListener('change', filterAuditLog);

            document.getElementById('change-extension-checkbox').addEventListener('change', (e) => {
                document.getElementById('new-extension-group').style.display =
                    e.target.checked ? 'block' : 'none';
            });
        }

        // Load all data
        async function loadAllData() {
            await Promise.all([
                loadUsers(),
                loadPermissions(),
                loadAuditLog(),
                loadStats()
            ]);
        }

        // Load users
        async function loadUsers() {
            try {
                const response = await fetch('/api/role-management.php?action=list_users');
                const data = await response.json();

                if (data.success) {
                    users = data.users;
                    renderUsersTable(users);
                } else {
                    showAlert('error', 'Failed to load users: ' + data.message);
                }
            } catch (error) {
                console.error('Error loading users:', error);
                showAlert('error', 'Failed to load users');
            }
        }

        // Render users table
        function renderUsersTable(usersToRender) {
            const container = document.getElementById('users-table-container');

            if (usersToRender.length === 0) {
                container.innerHTML = '<p style="text-align: center; padding: 2rem; color: #666;">No users found</p>';
                return;
            }

            let html = `
                <table>
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Extension</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Last Login</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            usersToRender.forEach(user => {
                const roleBadge = getRoleBadge(user.role);
                const statusBadge = user.is_active ?
                    '<span class="badge badge-active">Active</span>' :
                    '<span class="badge badge-inactive">Inactive</span>';

                html += `
                    <tr>
                        <td><strong>${escapeHtml(user.username)}</strong></td>
                        <td>${escapeHtml(user.extension || 'N/A')}</td>
                        <td>${escapeHtml(user.email || 'N/A')}</td>
                        <td>${roleBadge}</td>
                        <td>${statusBadge}</td>
                        <td>${escapeHtml(user.last_login || 'Never')}</td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn btn-primary btn-sm" onclick='openChangeRoleModal(${JSON.stringify(user)})'>
                                    Edit Role
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            });

            html += `
                    </tbody>
                </table>
            `;

            container.innerHTML = html;
        }

        // Load permissions matrix
        async function loadPermissions() {
            try {
                const response = await fetch('/api/role-management.php?action=get_permissions');
                const data = await response.json();

                if (data.success) {
                    renderPermissionsMatrix(data.permissions);
                } else {
                    showAlert('error', 'Failed to load permissions');
                }
            } catch (error) {
                console.error('Error loading permissions:', error);
                showAlert('error', 'Failed to load permissions');
            }
        }

        // Render permissions matrix
        function renderPermissionsMatrix(permissions) {
            const container = document.getElementById('permissions-matrix');
            let html = '<div class="permissions-grid">';

            for (const [role, roleData] of Object.entries(permissions)) {
                const roleBadge = getRoleBadge(role);
                html += `
                    <div class="permission-card">
                        <h3>${roleBadge} ${roleData.name}</h3>
                        <p style="color: #666; margin-bottom: 1rem; font-size: 0.9rem;">${roleData.description}</p>
                `;

                for (const [perm, hasPermission] of Object.entries(roleData.permissions)) {
                    const checkIcon = hasPermission ?
                        '<span class="permission-check check-yes">✓</span>' :
                        '<span class="permission-check check-no">✗</span>';
                    const permName = perm.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());

                    html += `
                        <div class="permission-item">
                            <span>${permName}</span>
                            ${checkIcon}
                        </div>
                    `;
                }

                html += '</div>';
            }

            html += '</div>';
            container.innerHTML = html;
        }

        // Load audit log
        async function loadAuditLog() {
            try {
                const response = await fetch('/api/role-management.php?action=get_audit_log');
                const data = await response.json();

                if (data.success) {
                    auditLog = data.audit_log;
                    renderAuditLog(auditLog);
                } else {
                    showAlert('error', 'Failed to load audit log');
                }
            } catch (error) {
                console.error('Error loading audit log:', error);
                showAlert('error', 'Failed to load audit log');
            }
        }

        // Render audit log
        function renderAuditLog(logEntries) {
            const container = document.getElementById('audit-log');

            if (logEntries.length === 0) {
                container.innerHTML = '<p style="text-align: center; padding: 2rem; color: #666;">No audit entries found</p>';
                return;
            }

            let html = '';
            logEntries.forEach(entry => {
                html += `
                    <div class="audit-entry">
                        <div class="timestamp">📅 ${escapeHtml(entry.timestamp)}</div>
                        <div class="action">${escapeHtml(entry.action)}</div>
                        <div class="details">
                            <strong>User:</strong> ${escapeHtml(entry.target_user)}<br>
                            <strong>Changed by:</strong> ${escapeHtml(entry.admin_user)}<br>
                            ${entry.details ? `<strong>Details:</strong> ${escapeHtml(entry.details)}<br>` : ''}
                            ${entry.reason ? `<strong>Reason:</strong> ${escapeHtml(entry.reason)}` : ''}
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;
        }

        // Load statistics
        async function loadStats() {
            try {
                const response = await fetch('/api/role-management.php?action=get_stats');
                const data = await response.json();

                if (data.success) {
                    stats = data.stats;
                    renderStats(stats);
                } else {
                    showAlert('error', 'Failed to load statistics');
                }
            } catch (error) {
                console.error('Error loading statistics:', error);
                showAlert('error', 'Failed to load statistics');
            }
        }

        // Render statistics
        function renderStats(statistics) {
            const container = document.getElementById('stats-container');

            let html = '<div class="stats-grid">';
            html += `
                <div class="stat-card">
                    <h3>${statistics.total_users || 0}</h3>
                    <p>Total Users</p>
                </div>
                <div class="stat-card">
                    <h3>${statistics.active_users || 0}</h3>
                    <p>Active Users</p>
                </div>
                <div class="stat-card">
                    <h3>${statistics.admins || 0}</h3>
                    <p>Administrators</p>
                </div>
                <div class="stat-card">
                    <h3>${statistics.managers || 0}</h3>
                    <p>Managers</p>
                </div>
            `;
            html += '</div>';

            html += '<h3 style="margin-top: 2rem; margin-bottom: 1rem;">Role Distribution</h3>';
            html += '<table><thead><tr><th>Role</th><th>Count</th><th>Percentage</th></tr></thead><tbody>';

            for (const [role, count] of Object.entries(statistics.role_distribution || {})) {
                const percentage = statistics.total_users > 0 ?
                    ((count / statistics.total_users) * 100).toFixed(1) : 0;
                const roleBadge = getRoleBadge(role);

                html += `
                    <tr>
                        <td>${roleBadge}</td>
                        <td>${count}</td>
                        <td>${percentage}%</td>
                    </tr>
                `;
            }

            html += '</tbody></table>';
            container.innerHTML = html;
        }

        // Filter users
        function filterUsers() {
            const search = document.getElementById('search-users').value.toLowerCase();
            const roleFilter = document.getElementById('filter-role').value;
            const statusFilter = document.getElementById('filter-status').value;

            const filtered = users.filter(user => {
                const matchesSearch = !search ||
                    user.username.toLowerCase().includes(search) ||
                    (user.email && user.email.toLowerCase().includes(search)) ||
                    (user.extension && user.extension.includes(search));

                const matchesRole = !roleFilter || user.role === roleFilter;
                const matchesStatus = !statusFilter ||
                    (statusFilter === 'active' && user.is_active) ||
                    (statusFilter === 'inactive' && !user.is_active);

                return matchesSearch && matchesRole && matchesStatus;
            });

            renderUsersTable(filtered);
        }

        // Filter audit log
        function filterAuditLog() {
            const search = document.getElementById('search-audit').value.toLowerCase();
            const actionFilter = document.getElementById('filter-audit-action').value;

            const filtered = auditLog.filter(entry => {
                const matchesSearch = !search ||
                    entry.action.toLowerCase().includes(search) ||
                    entry.target_user.toLowerCase().includes(search) ||
                    entry.admin_user.toLowerCase().includes(search) ||
                    (entry.details && entry.details.toLowerCase().includes(search));

                const matchesAction = !actionFilter || entry.action_type === actionFilter;

                return matchesSearch && matchesAction;
            });

            renderAuditLog(filtered);
        }

        // Open change role modal
        function openChangeRoleModal(user) {
            document.getElementById('change-user-id').value = user.id || '';
            document.getElementById('change-username').value = user.username;
            document.getElementById('change-current-user').value = user.username + (user.email ? ` (${user.email})` : '');
            document.getElementById('change-current-role').value = getRoleDisplayName(user.role);
            document.getElementById('change-current-extension').value = user.extension || 'None';
            document.getElementById('change-new-role').value = '';
            document.getElementById('change-extension-checkbox').checked = false;
            document.getElementById('new-extension-group').style.display = 'none';
            document.getElementById('change-new-extension').value = '';
            document.getElementById('change-reason').value = '';

            document.getElementById('modal-change-role').classList.add('active');
        }

        // Close modal
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        // Handle role change
        async function handleRoleChange(event) {
            event.preventDefault();

            const formData = new FormData(event.target);
            const data = {
                action: 'change_role',
                username: formData.get('username'),
                new_role: formData.get('new_role'),
                reason: formData.get('reason')
            };

            if (formData.get('change_extension')) {
                data.new_extension = formData.get('new_extension');
            }

            try {
                const response = await fetch('/api/role-management.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.success) {
                    showAlert('success', result.message || 'Role changed successfully');
                    closeModal('modal-change-role');
                    await loadAllData();
                } else {
                    showAlert('error', 'Failed to change role: ' + result.message);
                }
            } catch (error) {
                console.error('Error changing role:', error);
                showAlert('error', 'Failed to change role');
            }
        }

        // Refresh data
        async function refreshData() {
            showAlert('info', 'Refreshing data...');
            await loadAllData();
            showAlert('success', 'Data refreshed successfully');
        }

        // Show alert
        function showAlert(type, message) {
            const alertsContainer = document.getElementById('alerts');
            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;
            alert.textContent = message;

            alertsContainer.innerHTML = '';
            alertsContainer.appendChild(alert);

            setTimeout(() => {
                alert.remove();
            }, 5000);
        }

        // Get role badge HTML
        function getRoleBadge(role) {
            const badges = {
                'superadmin': '<span class="badge badge-superadmin">SuperAdmin</span>',
                'super_admin': '<span class="badge badge-superadmin">SuperAdmin</span>',
                'admin': '<span class="badge badge-admin">Admin</span>',
                'manager': '<span class="badge badge-manager">Manager</span>',
                'support': '<span class="badge badge-support">Support</span>',
                'user': '<span class="badge badge-user">User</span>',
                'guest': '<span class="badge badge-guest">Guest</span>'
            };
            return badges[role] || `<span class="badge">${escapeHtml(role)}</span>`;
        }

        // Get role display name
        function getRoleDisplayName(role) {
            const names = {
                'superadmin': 'SuperAdmin',
                'super_admin': 'SuperAdmin',
                'admin': 'Admin',
                'manager': 'Manager',
                'support': 'Support',
                'user': 'User',
                'guest': 'Guest'
            };
            return names[role] || role;
        }

        // Escape HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>
