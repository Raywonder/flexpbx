<?php
require_once __DIR__ . '/admin_auth_check.php';
/**
 * FlexPBX Department Management
 * Admin interface for managing departments, hierarchies, and settings
 */

session_start();

// Check if user is logged in and has admin role
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /admin/login.php');
    exit;
}

// Check for admin/superadmin/manager role
$admin_role = $_SESSION['admin_role'] ?? 'user';
$allowed_roles = ['superadmin', 'super_admin', 'admin', 'manager'];
if (!in_array($admin_role, $allowed_roles)) {
    die('Access denied. Admin, Manager, or SuperAdmin role required.');
}

$current_admin_username = $_SESSION['admin_username'] ?? 'admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Department Management - FlexPBX Admin</title>
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
            max-width: 1600px;
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

        .btn-sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.85rem;
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

        .department-tree {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 1.5rem;
            background: #f9f9f9;
        }

        .dept-node {
            margin-bottom: 1rem;
            border-left: 3px solid #667eea;
            padding-left: 1rem;
        }

        .dept-node.child {
            margin-left: 2rem;
            border-left-color: #4CAF50;
        }

        .dept-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: white;
            border-radius: 6px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 0.5rem;
        }

        .dept-info {
            flex: 1;
        }

        .dept-name {
            font-size: 1.2rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 0.3rem;
        }

        .dept-meta {
            display: flex;
            gap: 15px;
            font-size: 0.9rem;
            color: #666;
        }

        .dept-actions {
            display: flex;
            gap: 5px;
        }

        .badge {
            display: inline-block;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .badge-active {
            background: #4CAF50;
            color: white;
        }

        .badge-inactive {
            background: #f44336;
            color: white;
        }

        .badge-single {
            background: #2196F3;
            color: white;
        }

        .badge-team {
            background: #ff9800;
            color: white;
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
        }

        table tr:hover {
            background: #f9f9f9;
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
            max-width: 700px;
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
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
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

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
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

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .checkbox-group input[type="checkbox"] {
            width: auto;
        }

        .loading {
            text-align: center;
            padding: 2rem;
            color: #666;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }

            .dept-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .dept-actions {
                width: 100%;
                justify-content: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>
                <span>🏢</span>
                Department Management
            </h1>
            <div class="header-actions">
                <button class="btn btn-primary" onclick="openCreateDepartmentModal()">+ New Department</button>
                <button class="btn btn-secondary" onclick="refreshData()">🔄 Refresh</button>
                <a href="/admin/dashboard.html" class="btn btn-secondary">← Dashboard</a>
            </div>
        </div>

        <div class="content">
            <div id="alerts"></div>

            <!-- Statistics -->
            <div class="stats-grid" id="stats-container">
                <div class="loading">Loading statistics...</div>
            </div>

            <div class="tabs">
                <button class="tab active" data-tab="hierarchy">Department Hierarchy</button>
                <button class="tab" data-tab="list">Department List</button>
                <button class="tab" data-tab="managers">Managers</button>
                <button class="tab" data-tab="settings">Settings</button>
            </div>

            <!-- Hierarchy Tab -->
            <div id="tab-hierarchy" class="tab-content active">
                <div class="search-filter">
                    <input type="text" id="search-hierarchy" placeholder="Search departments...">
                    <select id="filter-status">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div id="department-tree" class="department-tree">
                    <div class="loading">Loading departments...</div>
                </div>
            </div>

            <!-- List Tab -->
            <div id="tab-list" class="tab-content">
                <div class="search-filter">
                    <input type="text" id="search-list" placeholder="Search departments...">
                    <select id="filter-list-status">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                    <select id="filter-manager-type">
                        <option value="">All Types</option>
                        <option value="single">Single Manager</option>
                        <option value="team">Team Managed</option>
                    </select>
                </div>
                <div id="departments-table-container">
                    <div class="loading">Loading departments...</div>
                </div>
            </div>

            <!-- Managers Tab -->
            <div id="tab-managers" class="tab-content">
                <div class="search-filter">
                    <input type="text" id="search-managers" placeholder="Search managers...">
                    <select id="filter-manager-role">
                        <option value="">All Roles</option>
                        <option value="manager">Manager</option>
                        <option value="assistant_manager">Assistant Manager</option>
                        <option value="supervisor">Supervisor</option>
                        <option value="team_lead">Team Lead</option>
                    </select>
                </div>
                <div id="managers-table-container">
                    <div class="loading">Loading managers...</div>
                </div>
            </div>

            <!-- Settings Tab -->
            <div id="tab-settings" class="tab-content">
                <h2>Department Settings</h2>
                <p style="margin-bottom: 1.5rem; color: #666;">Configure global department settings and defaults.</p>
                <div id="settings-container">
                    <div class="alert alert-info">
                        Select a department to configure its settings.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create/Edit Department Modal -->
    <div id="modal-department" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modal-department-title">Create Department</h2>
                <button class="close-modal" onclick="closeModal('modal-department')">&times;</button>
            </div>
            <form id="form-department" onsubmit="handleDepartmentSubmit(event)">
                <input type="hidden" id="dept-id" name="id">

                <div class="form-group">
                    <label for="dept-name">Department Name *</label>
                    <input type="text" id="dept-name" name="name" required placeholder="e.g., Sales, Support, Technical">
                </div>

                <div class="form-group">
                    <label for="dept-description">Description</label>
                    <textarea id="dept-description" name="description" placeholder="Brief description of the department"></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="dept-parent">Parent Department</label>
                        <select id="dept-parent" name="parent_id">
                            <option value="">None (Top Level)</option>
                        </select>
                        <small>Create hierarchical structure</small>
                    </div>

                    <div class="form-group">
                        <label for="dept-manager-type">Management Type *</label>
                        <select id="dept-manager-type" name="manager_type" required>
                            <option value="single">Single Manager</option>
                            <option value="team">Team Management</option>
                        </select>
                        <small>How this department is managed</small>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="dept-status">Status *</label>
                        <select id="dept-status" name="status" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="dept-timezone">Timezone</label>
                        <select id="dept-timezone" name="timezone">
                            <option value="America/New_York">Eastern Time</option>
                            <option value="America/Chicago">Central Time</option>
                            <option value="America/Denver">Mountain Time</option>
                            <option value="America/Los_Angeles">Pacific Time</option>
                        </select>
                    </div>
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('modal-department')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Department</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Assign Manager Modal -->
    <div id="modal-assign-manager" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Assign Manager</h2>
                <button class="close-modal" onclick="closeModal('modal-assign-manager')">&times;</button>
            </div>
            <form id="form-assign-manager" onsubmit="handleAssignManager(event)">
                <input type="hidden" id="assign-dept-id" name="department_id">

                <div class="form-group">
                    <label>Department</label>
                    <input type="text" id="assign-dept-name" disabled>
                </div>

                <div class="form-group">
                    <label for="assign-username">Select User *</label>
                    <select id="assign-username" name="username" required onchange="handleUserSelect(this)">
                        <option value="">-- Select User --</option>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="assign-extension">Extension</label>
                        <input type="text" id="assign-extension" name="extension" placeholder="Auto-filled from user">
                    </div>

                    <div class="form-group">
                        <label for="assign-role">Manager Role *</label>
                        <select id="assign-role" name="role" required>
                            <option value="manager">Manager</option>
                            <option value="assistant_manager">Assistant Manager</option>
                            <option value="supervisor">Supervisor</option>
                            <option value="team_lead">Team Lead</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="assign-primary" name="is_primary" value="1">
                        <label for="assign-primary">Primary Manager</label>
                    </div>
                    <small>Primary manager has full control over department</small>
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('modal-assign-manager')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Assign Manager</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Global state
        let departments = [];
        let managers = [];
        let users = [];
        let stats = {};

        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            initializeTabs();
            loadAllData();
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
            document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
            document.querySelector(`[data-tab="${tabName}"]`).classList.add('active');
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            document.getElementById(`tab-${tabName}`).classList.add('active');
        }

        // Load all data
        async function loadAllData() {
            await Promise.all([
                loadDepartments(),
                loadManagers(),
                loadUsers(),
                loadStats()
            ]);
        }

        // Load departments
        async function loadDepartments() {
            try {
                const response = await fetch('/api/department-management.php?action=list_departments');
                const data = await response.json();

                if (data.success) {
                    departments = data.departments;
                    renderDepartmentHierarchy();
                    renderDepartmentsList();
                    populateParentDropdown();
                } else {
                    showAlert('error', 'Failed to load departments: ' + data.message);
                }
            } catch (error) {
                console.error('Error loading departments:', error);
                showAlert('error', 'Failed to load departments');
            }
        }

        // Load managers
        async function loadManagers() {
            try {
                const response = await fetch('/api/department-management.php?action=list_managers');
                const data = await response.json();

                if (data.success) {
                    managers = data.managers;
                    renderManagersList();
                } else {
                    showAlert('error', 'Failed to load managers: ' + data.message);
                }
            } catch (error) {
                console.error('Error loading managers:', error);
                showAlert('error', 'Failed to load managers');
            }
        }

        // Load users
        async function loadUsers() {
            try {
                const response = await fetch('/api/role-management.php?action=list_users');
                const data = await response.json();

                if (data.success) {
                    users = data.users;
                    populateUserDropdown();
                }
            } catch (error) {
                console.error('Error loading users:', error);
            }
        }

        // Load statistics
        async function loadStats() {
            try {
                const response = await fetch('/api/department-management.php?action=get_stats');
                const data = await response.json();

                if (data.success) {
                    stats = data.stats;
                    renderStats();
                }
            } catch (error) {
                console.error('Error loading stats:', error);
            }
        }

        // Render department hierarchy
        function renderDepartmentHierarchy() {
            const container = document.getElementById('department-tree');
            const topLevel = departments.filter(d => !d.parent_id);

            if (topLevel.length === 0) {
                container.innerHTML = '<p style="text-align: center; color: #666;">No departments found. Create one to get started!</p>';
                return;
            }

            let html = '';
            topLevel.forEach(dept => {
                html += renderDepartmentNode(dept);
            });

            container.innerHTML = html;
        }

        // Render department node (recursive)
        function renderDepartmentNode(dept, isChild = false) {
            const statusBadge = dept.status === 'active' ?
                '<span class="badge badge-active">Active</span>' :
                '<span class="badge badge-inactive">Inactive</span>';
            const typeBadge = dept.manager_type === 'single' ?
                '<span class="badge badge-single">Single Manager</span>' :
                '<span class="badge badge-team">Team Managed</span>';

            let html = `
                <div class="dept-node ${isChild ? 'child' : ''}">
                    <div class="dept-header">
                        <div class="dept-info">
                            <div class="dept-name">${escapeHtml(dept.name)}</div>
                            <div class="dept-meta">
                                ${statusBadge}
                                ${typeBadge}
                                <span>Managers: ${dept.manager_count || 0}</span>
                                <span>Teams: ${dept.team_count || 0}</span>
                            </div>
                        </div>
                        <div class="dept-actions">
                            <button class="btn btn-primary btn-sm" onclick='openAssignManagerModal(${JSON.stringify(dept)})'>
                                Assign Manager
                            </button>
                            <button class="btn btn-warning btn-sm" onclick='editDepartment(${JSON.stringify(dept)})'>
                                Edit
                            </button>
                            <button class="btn btn-danger btn-sm" onclick="deleteDepartment(${dept.id})">
                                Delete
                            </button>
                        </div>
                    </div>
            `;

            // Add child departments
            const children = departments.filter(d => d.parent_id === dept.id);
            if (children.length > 0) {
                children.forEach(child => {
                    html += renderDepartmentNode(child, true);
                });
            }

            html += '</div>';
            return html;
        }

        // Render departments list
        function renderDepartmentsList() {
            const container = document.getElementById('departments-table-container');

            if (departments.length === 0) {
                container.innerHTML = '<p style="text-align: center; padding: 2rem; color: #666;">No departments found</p>';
                return;
            }

            let html = `
                <table>
                    <thead>
                        <tr>
                            <th>Department</th>
                            <th>Parent</th>
                            <th>Type</th>
                            <th>Managers</th>
                            <th>Teams</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            departments.forEach(dept => {
                const statusBadge = dept.status === 'active' ?
                    '<span class="badge badge-active">Active</span>' :
                    '<span class="badge badge-inactive">Inactive</span>';
                const typeBadge = dept.manager_type === 'single' ?
                    '<span class="badge badge-single">Single</span>' :
                    '<span class="badge badge-team">Team</span>';
                const parent = departments.find(d => d.id === dept.parent_id);

                html += `
                    <tr>
                        <td><strong>${escapeHtml(dept.name)}</strong></td>
                        <td>${parent ? escapeHtml(parent.name) : '-'}</td>
                        <td>${typeBadge}</td>
                        <td>${dept.manager_count || 0}</td>
                        <td>${dept.team_count || 0}</td>
                        <td>${statusBadge}</td>
                        <td>
                            <div class="dept-actions">
                                <button class="btn btn-primary btn-sm" onclick='openAssignManagerModal(${JSON.stringify(dept)})'>Assign</button>
                                <button class="btn btn-warning btn-sm" onclick='editDepartment(${JSON.stringify(dept)})'>Edit</button>
                                <button class="btn btn-danger btn-sm" onclick="deleteDepartment(${dept.id})">Delete</button>
                            </div>
                        </td>
                    </tr>
                `;
            });

            html += '</tbody></table>';
            container.innerHTML = html;
        }

        // Render managers list
        function renderManagersList() {
            const container = document.getElementById('managers-table-container');

            if (managers.length === 0) {
                container.innerHTML = '<p style="text-align: center; padding: 2rem; color: #666;">No managers assigned</p>';
                return;
            }

            let html = `
                <table>
                    <thead>
                        <tr>
                            <th>Manager</th>
                            <th>Extension</th>
                            <th>Department</th>
                            <th>Role</th>
                            <th>Primary</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            managers.forEach(mgr => {
                const statusBadge = mgr.status === 'active' ?
                    '<span class="badge badge-active">Active</span>' :
                    '<span class="badge badge-inactive">Inactive</span>';

                html += `
                    <tr>
                        <td><strong>${escapeHtml(mgr.username)}</strong></td>
                        <td>${escapeHtml(mgr.extension || '-')}</td>
                        <td>${escapeHtml(mgr.department_name || 'Unknown')}</td>
                        <td>${escapeHtml(mgr.role)}</td>
                        <td>${mgr.is_primary ? 'Yes' : 'No'}</td>
                        <td>${statusBadge}</td>
                        <td>
                            <button class="btn btn-danger btn-sm" onclick="removeManager(${mgr.id})">Remove</button>
                        </td>
                    </tr>
                `;
            });

            html += '</tbody></table>';
            container.innerHTML = html;
        }

        // Render statistics
        function renderStats() {
            const container = document.getElementById('stats-container');
            let html = `
                <div class="stat-card">
                    <h3>${stats.total_departments || 0}</h3>
                    <p>Total Departments</p>
                </div>
                <div class="stat-card">
                    <h3>${stats.active_departments || 0}</h3>
                    <p>Active Departments</p>
                </div>
                <div class="stat-card">
                    <h3>${stats.total_managers || 0}</h3>
                    <p>Total Managers</p>
                </div>
                <div class="stat-card">
                    <h3>${stats.total_teams || 0}</h3>
                    <p>Total Teams</p>
                </div>
            `;
            container.innerHTML = html;
        }

        // Modal functions
        function openCreateDepartmentModal() {
            document.getElementById('modal-department-title').textContent = 'Create Department';
            document.getElementById('form-department').reset();
            document.getElementById('dept-id').value = '';
            document.getElementById('modal-department').classList.add('active');
        }

        function editDepartment(dept) {
            document.getElementById('modal-department-title').textContent = 'Edit Department';
            document.getElementById('dept-id').value = dept.id;
            document.getElementById('dept-name').value = dept.name;
            document.getElementById('dept-description').value = dept.description || '';
            document.getElementById('dept-parent').value = dept.parent_id || '';
            document.getElementById('dept-manager-type').value = dept.manager_type;
            document.getElementById('dept-status').value = dept.status;
            document.getElementById('modal-department').classList.add('active');
        }

        function openAssignManagerModal(dept) {
            document.getElementById('assign-dept-id').value = dept.id;
            document.getElementById('assign-dept-name').value = dept.name;
            document.getElementById('form-assign-manager').reset();
            document.getElementById('modal-assign-manager').classList.add('active');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        // Populate dropdowns
        function populateParentDropdown() {
            const select = document.getElementById('dept-parent');
            const currentId = document.getElementById('dept-id').value;

            let html = '<option value="">None (Top Level)</option>';
            departments.forEach(dept => {
                if (dept.id != currentId) {
                    html += `<option value="${dept.id}">${escapeHtml(dept.name)}</option>`;
                }
            });
            select.innerHTML = html;
        }

        function populateUserDropdown() {
            const select = document.getElementById('assign-username');
            let html = '<option value="">-- Select User --</option>';

            users.forEach(user => {
                html += `<option value="${escapeHtml(user.username)}" data-extension="${escapeHtml(user.extension || '')}">${escapeHtml(user.username)} ${user.extension ? '(Ext: ' + user.extension + ')' : ''}</option>`;
            });

            select.innerHTML = html;
        }

        function handleUserSelect(select) {
            const selectedOption = select.options[select.selectedIndex];
            const extension = selectedOption.getAttribute('data-extension');
            document.getElementById('assign-extension').value = extension || '';
        }

        // Form handlers
        async function handleDepartmentSubmit(event) {
            event.preventDefault();
            const formData = new FormData(event.target);
            const data = {
                action: formData.get('id') ? 'update_department' : 'create_department',
                id: formData.get('id'),
                name: formData.get('name'),
                description: formData.get('description'),
                parent_id: formData.get('parent_id'),
                manager_type: formData.get('manager_type'),
                status: formData.get('status'),
                timezone: formData.get('timezone')
            };

            try {
                const response = await fetch('/api/department-management.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });

                const result = await response.json();
                if (result.success) {
                    showAlert('success', result.message);
                    closeModal('modal-department');
                    await loadAllData();
                } else {
                    showAlert('error', result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('error', 'Failed to save department');
            }
        }

        async function handleAssignManager(event) {
            event.preventDefault();
            const formData = new FormData(event.target);
            const data = {
                action: 'assign_manager',
                department_id: formData.get('department_id'),
                username: formData.get('username'),
                extension: formData.get('extension'),
                role: formData.get('role'),
                is_primary: formData.get('is_primary') ? 1 : 0
            };

            try {
                const response = await fetch('/api/department-management.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });

                const result = await response.json();
                if (result.success) {
                    showAlert('success', result.message);
                    closeModal('modal-assign-manager');
                    await loadAllData();
                } else {
                    showAlert('error', result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('error', 'Failed to assign manager');
            }
        }

        async function deleteDepartment(id) {
            if (!confirm('Are you sure you want to delete this department? This action cannot be undone.')) {
                return;
            }

            try {
                const response = await fetch('/api/department-management.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'delete_department', id })
                });

                const result = await response.json();
                if (result.success) {
                    showAlert('success', result.message);
                    await loadAllData();
                } else {
                    showAlert('error', result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('error', 'Failed to delete department');
            }
        }

        async function removeManager(id) {
            if (!confirm('Remove this manager assignment?')) {
                return;
            }

            try {
                const response = await fetch('/api/department-management.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'remove_manager', id })
                });

                const result = await response.json();
                if (result.success) {
                    showAlert('success', result.message);
                    await loadAllData();
                } else {
                    showAlert('error', result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('error', 'Failed to remove manager');
            }
        }

        // Utility functions
        function refreshData() {
            showAlert('info', 'Refreshing data...');
            loadAllData();
        }

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

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>
