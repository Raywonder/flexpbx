<?php
require_once __DIR__ . '/admin_auth_check.php';
/**
 * FlexPBX User Migration & Re-Assignment
 * Move users between extensions and departments with full data preservation
 */

session_start();

// Check authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /admin/login.php');
    exit;
}

$admin_username = $_SESSION['admin_username'] ?? 'Unknown';
$admin_role = $_SESSION['admin_role'] ?? 'admin';

// Check for admin/manager role
$allowed_roles = ['superadmin', 'super_admin', 'admin', 'manager'];
if (!in_array($admin_role, $allowed_roles)) {
    die('Access denied. Admin or Manager role required.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Migration & Re-Assignment - FlexPBX Admin</title>
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
        }

        .header h1 {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }

        .header p {
            opacity: 0.9;
            font-size: 0.95rem;
        }

        .tabs {
            display: flex;
            background: #f5f7fa;
            border-bottom: 2px solid #e0e6ed;
        }

        .tab {
            padding: 1rem 1.5rem;
            background: transparent;
            border: none;
            cursor: pointer;
            font-weight: 600;
            color: #666;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }

        .tab.active {
            color: #667eea;
            border-bottom-color: #667eea;
            background: white;
        }

        .tab:hover:not(.active) {
            background: #e9ecef;
        }

        .tab-content {
            display: none;
            padding: 2rem;
        }

        .tab-content.active {
            display: block;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #333;
        }

        .form-group .help-text {
            font-size: 0.85rem;
            color: #666;
            margin-top: 0.25rem;
        }

        input[type="text"],
        input[type="email"],
        input[type="number"],
        select,
        textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e0e6ed;
            border-radius: 6px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        input:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .checkbox-group input[type="checkbox"] {
            width: auto;
            cursor: pointer;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 1rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-success:hover {
            background: #218838;
        }

        .alert {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
        }

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border-left: 4px solid #17a2b8;
        }

        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border-left: 4px solid #ffc107;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        .migration-preview {
            background: #f8f9fa;
            border: 2px solid #e0e6ed;
            border-radius: 8px;
            padding: 1.5rem;
            margin: 1.5rem 0;
        }

        .migration-preview h3 {
            margin-bottom: 1rem;
            color: #333;
        }

        .migration-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem;
            background: white;
            border-radius: 6px;
            margin-bottom: 0.5rem;
        }

        .migration-arrow {
            font-size: 1.5rem;
            color: #667eea;
        }

        .impact-list {
            list-style: none;
            padding: 0;
        }

        .impact-list li {
            padding: 0.5rem;
            margin-bottom: 0.5rem;
            background: white;
            border-radius: 4px;
            border-left: 3px solid #667eea;
        }

        .impact-list li.warning {
            border-left-color: #ffc107;
        }

        .impact-list li.success {
            border-left-color: #28a745;
        }

        .nav-links {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
        }

        .nav-links a {
            color: #667eea;
            text-decoration: none;
            margin-right: 1rem;
            font-weight: 600;
        }

        .nav-links a:hover {
            text-decoration: underline;
        }

        .user-card {
            background: white;
            border: 2px solid #e0e6ed;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: all 0.3s;
        }

        .user-card:hover {
            border-color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .user-card.selected {
            border-color: #667eea;
            background: #f0f4ff;
        }

        .user-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .user-details {
            flex: 1;
        }

        .user-name {
            font-weight: 600;
            font-size: 1.1rem;
            color: #333;
        }

        .user-extension {
            color: #667eea;
            font-weight: 600;
            margin-top: 0.25rem;
        }

        .user-department {
            color: #666;
            font-size: 0.9rem;
            margin-top: 0.25rem;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>👥 User Migration & Re-Assignment</h1>
            <p>Move users between extensions and departments with automatic queue and data updates</p>
        </div>

        <div class="nav-links">
            <a href="/admin/dashboard.php">← Back to Dashboard</a>
            <a href="/admin/department-management.php">Department Management</a>
            <a href="/admin/send-invite.php">Invite Users</a>
            <a href="/admin/admin-extensions-management.php">Extensions Management</a>
        </div>

        <div class="tabs">
            <button class="tab active" onclick="switchTab(event, 'migrate-user')">Migrate User</button>
            <button class="tab" onclick="switchTab(event, 'change-extension')">Change Extension</button>
            <button class="tab" onclick="switchTab(event, 'move-department')">Move Department</button>
            <button class="tab" onclick="switchTab(event, 'bulk-migration')">Bulk Migration</button>
            <button class="tab" onclick="switchTab(event, 'history')">Migration History</button>
        </div>

        <!-- Tab 1: Migrate User (Combined) -->
        <div id="migrate-user" class="tab-content active">
            <div class="alert alert-info">
                <strong>ℹ️ Complete User Migration</strong><br>
                This tool migrates a user with options to change extension number, department, queue assignments, and more.
                All user data (voicemail, call history, settings) will be preserved.
            </div>

            <form id="migrateUserForm">
                <div class="form-group">
                    <label>Select User to Migrate</label>
                    <select id="migrateUserId" name="user_id" required onchange="loadUserMigrationData()">
                        <option value="">-- Select User --</option>
                        <!-- Populated via JavaScript -->
                    </select>
                </div>

                <div id="currentUserInfo" style="display: none;" class="migration-preview">
                    <h3>Current User Information</h3>
                    <div id="currentUserDetails"></div>
                </div>

                <hr style="margin: 2rem 0; border: 1px solid #e0e6ed;">

                <h3 style="margin-bottom: 1rem;">Migration Options</h3>

                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="changeExtension" name="change_extension" onchange="toggleExtensionFields()">
                        <label for="changeExtension" style="margin: 0;">Change Extension Number</label>
                    </div>
                    <div class="help-text">If unchecked, user keeps current extension number</div>
                </div>

                <div id="extensionFields" style="display: none;">
                    <div class="form-group">
                        <label>New Extension Number</label>
                        <input type="number" id="newExtension" name="new_extension" min="2000" max="9999">
                        <div class="help-text">Leave empty to auto-assign next available extension</div>
                    </div>

                    <div class="alert alert-warning">
                        <strong>⚠️ Extension Change Impact:</strong><br>
                        • User must update third-party SIP clients (softphones, desk phones)<br>
                        • FlexPhone web client auto-updates (no action needed)<br>
                        • User portal auto-updates (no action needed)<br>
                        • All queue memberships will be updated automatically
                    </div>
                </div>

                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="changeDepartment" name="change_department" onchange="toggleDepartmentFields()">
                        <label for="changeDepartment" style="margin: 0;">Move to Different Department</label>
                    </div>
                </div>

                <div id="departmentFields" style="display: none;">
                    <div class="form-group">
                        <label>New Department</label>
                        <select id="newDepartment" name="new_department">
                            <option value="">-- Select Department --</option>
                            <!-- Populated via JavaScript -->
                        </select>
                    </div>

                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="updateQueueMembership" name="update_queue_membership" checked>
                            <label for="updateQueueMembership" style="margin: 0;">Automatically update queue memberships</label>
                        </div>
                        <div class="help-text">Remove from old department queues, add to new department queues</div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Migration Reason (Optional)</label>
                    <textarea id="migrationReason" name="migration_reason" rows="3" placeholder="e.g., Promoted to sales manager, department restructure, etc."></textarea>
                </div>

                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="notifyUser" name="notify_user" checked>
                        <label for="notifyUser" style="margin: 0;">Send notification email to user</label>
                    </div>
                    <div class="help-text">User receives email with updated extension info and setup instructions</div>
                </div>

                <hr style="margin: 2rem 0; border: 1px solid #e0e6ed;">

                <div id="migrationImpactPreview" style="display: none;" class="migration-preview">
                    <h3>Migration Impact Analysis</h3>
                    <ul class="impact-list" id="impactList"></ul>
                </div>

                <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                    <button type="button" class="btn btn-secondary" onclick="previewMigration()">Preview Migration</button>
                    <button type="submit" class="btn btn-primary">Execute Migration</button>
                </div>
            </form>
        </div>

        <!-- Tab 2: Quick Extension Change -->
        <div id="change-extension" class="tab-content">
            <div class="alert alert-info">
                <strong>ℹ️ Quick Extension Change</strong><br>
                Change a user's extension number only. Department and queue memberships remain unchanged.
            </div>

            <form id="changeExtensionForm">
                <div class="form-group">
                    <label>Select User</label>
                    <select id="extensionChangeUserId" name="user_id" required>
                        <option value="">-- Select User --</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>New Extension Number</label>
                    <input type="number" id="quickNewExtension" name="new_extension" min="2000" max="9999" required>
                    <div class="help-text">Or leave empty to auto-assign</div>
                </div>

                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="preserveVoicemail" name="preserve_voicemail" checked>
                        <label for="preserveVoicemail" style="margin: 0;">Preserve voicemail messages</label>
                    </div>
                </div>

                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="notifyExtensionChange" name="notify_user" checked>
                        <label for="notifyExtensionChange" style="margin: 0;">Notify user of change</label>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">Change Extension</button>
            </form>
        </div>

        <!-- Tab 3: Move Department -->
        <div id="move-department" class="tab-content">
            <div class="alert alert-info">
                <strong>ℹ️ Department Transfer</strong><br>
                Move user to a different department. Extension number remains the same.
            </div>

            <form id="moveDepartmentForm">
                <div class="form-group">
                    <label>Select User</label>
                    <select id="deptMoveUserId" name="user_id" required>
                        <option value="">-- Select User --</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>New Department</label>
                    <select id="deptMoveNewDept" name="new_department" required>
                        <option value="">-- Select Department --</option>
                    </select>
                </div>

                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="deptUpdateQueues" name="update_queues" checked>
                        <label for="deptUpdateQueues" style="margin: 0;">Update queue memberships automatically</label>
                    </div>
                    <div class="help-text">Remove from old department queues, add to new department queues</div>
                </div>

                <div class="form-group">
                    <label>Transfer Reason</label>
                    <textarea id="deptTransferReason" name="transfer_reason" rows="3" placeholder="e.g., Department restructure, role change"></textarea>
                </div>

                <button type="submit" class="btn btn-primary">Transfer Department</button>
            </form>
        </div>

        <!-- Tab 4: Bulk Migration -->
        <div id="bulk-migration" class="tab-content">
            <div class="alert alert-info">
                <strong>ℹ️ Bulk User Migration</strong><br>
                Move multiple users at once. Useful for department mergers or reorganization.
            </div>

            <div class="form-group">
                <label>Select Users to Migrate</label>
                <div id="bulkUserSelection" class="grid">
                    <!-- User cards populated via JavaScript -->
                </div>
            </div>

            <div class="form-group">
                <label>Bulk Action</label>
                <select id="bulkAction" onchange="showBulkOptions()">
                    <option value="">-- Select Action --</option>
                    <option value="change_department">Move to Different Department</option>
                    <option value="reassign_extensions">Auto-Reassign Extensions</option>
                    <option value="update_queues">Update Queue Memberships</option>
                </select>
            </div>

            <div id="bulkActionOptions" style="display: none;">
                <!-- Options populated based on bulk action selected -->
            </div>

            <div style="margin-top: 2rem;">
                <button type="button" class="btn btn-secondary" onclick="previewBulkMigration()">Preview Bulk Migration</button>
                <button type="button" class="btn btn-primary" onclick="executeBulkMigration()">Execute Bulk Migration</button>
            </div>
        </div>

        <!-- Tab 5: Migration History -->
        <div id="history" class="tab-content">
            <div class="alert alert-info">
                <strong>ℹ️ Migration History</strong><br>
                View all user migrations, extension changes, and department transfers.
            </div>

            <div class="form-group">
                <label>Filter by</label>
                <select id="historyFilter" onchange="loadMigrationHistory()">
                    <option value="all">All Migrations</option>
                    <option value="extension_change">Extension Changes Only</option>
                    <option value="department_move">Department Moves Only</option>
                    <option value="last_30_days">Last 30 Days</option>
                </select>
            </div>

            <div id="migrationHistoryList">
                <!-- History populated via JavaScript -->
            </div>
        </div>
    </div>

    <script>
    // Tab switching
    function switchTab(evt, tabName) {
        const tabs = document.querySelectorAll('.tab');
        const tabContents = document.querySelectorAll('.tab-content');
        
        tabs.forEach(tab => tab.classList.remove('active'));
        tabContents.forEach(content => content.classList.remove('active'));
        
        evt.currentTarget.classList.add('active');
        document.getElementById(tabName).classList.add('active');
    }

    // Toggle fields
    function toggleExtensionFields() {
        const checked = document.getElementById('changeExtension').checked;
        document.getElementById('extensionFields').style.display = checked ? 'block' : 'none';
    }

    function toggleDepartmentFields() {
        const checked = document.getElementById('changeDepartment').checked;
        document.getElementById('departmentFields').style.display = checked ? 'block' : 'none';
    }

    // Load user data for migration
    function loadUserMigrationData() {
        const userId = document.getElementById('migrateUserId').value;
        if (!userId) {
            document.getElementById('currentUserInfo').style.display = 'none';
            return;
        }

        // Fetch user data via API
        fetch(`/api/user-management.php?action=get_user&user_id=${userId}`)
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    displayCurrentUserInfo(data.user);
                }
            });
    }

    function displayCurrentUserInfo(user) {
        const html = `
            <div class="user-info">
                <div class="user-details">
                    <div class="user-name">${user.name}</div>
                    <div class="user-extension">Extension: ${user.extension}</div>
                    <div class="user-department">Department: ${user.department || 'None'}</div>
                    <div style="margin-top: 0.5rem; font-size: 0.9rem; color: #666;">
                        Email: ${user.email}<br>
                        Queues: ${user.queues ? user.queues.join(', ') : 'None'}<br>
                        Voicemail: ${user.voicemail_count || 0} messages
                    </div>
                </div>
            </div>
        `;
        document.getElementById('currentUserDetails').innerHTML = html;
        document.getElementById('currentUserInfo').style.display = 'block';
    }

    // Preview migration impact
    function previewMigration() {
        const changeExt = document.getElementById('changeExtension').checked;
        const changeDept = document.getElementById('changeDepartment').checked;
        
        const impacts = [];
        
        if (changeExt) {
            impacts.push({
                text: 'Extension number will change - User must update third-party SIP clients',
                type: 'warning'
            });
            impacts.push({
                text: 'FlexPhone web client will auto-update with new extension',
                type: 'success'
            });
            impacts.push({
                text: 'User portal will show updated extension information',
                type: 'success'
            });
            impacts.push({
                text: 'All queue memberships will be updated with new extension',
                type: 'success'
            });
        } else {
            impacts.push({
                text: 'Extension number unchanged - SIP clients continue to work normally',
                type: 'success'
            });
        }
        
        if (changeDept) {
            impacts.push({
                text: 'User will be removed from old department queues',
                type: 'warning'
            });
            impacts.push({
                text: 'User will be added to new department queues',
                type: 'success'
            });
            impacts.push({
                text: 'Department-specific permissions will be updated',
                type: 'success'
            });
        }
        
        impacts.push({
            text: 'All voicemail messages will be preserved',
            type: 'success'
        });
        impacts.push({
            text: 'Call history will be maintained',
            type: 'success'
        });
        impacts.push({
            text: 'User settings and preferences will be preserved',
            type: 'success'
        });
        
        const impactHtml = impacts.map(i => 
            `<li class="${i.type}">${i.text}</li>`
        ).join('');
        
        document.getElementById('impactList').innerHTML = impactHtml;
        document.getElementById('migrationImpactPreview').style.display = 'block';
    }

    // Form submissions
    document.getElementById('migrateUserForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const data = Object.fromEntries(formData.entries());
        
        if (confirm('Are you sure you want to execute this migration? This action will update the user\'s extension, department, and queue assignments.')) {
            fetch('/api/user-management.php?action=migrate_user', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(data)
            })
            .then(r => r.json())
            .then(result => {
                if (result.success) {
                    alert('✅ Migration completed successfully!\n\n' + result.message);
                    location.reload();
                } else {
                    alert('❌ Migration failed: ' + result.error);
                }
            });
        }
    });

    // Initialize: Load users and departments
    window.addEventListener('DOMContentLoaded', function() {
        loadUsers();
        loadDepartments();
    });

    function loadUsers() {
        fetch('/api/user-management.php?action=list_users')
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    populateUserSelects(data.users);
                    populateBulkUserSelection(data.users);
                }
            });
    }

    function loadDepartments() {
        fetch('/api/departments.php?action=list')
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    populateDepartmentSelects(data.departments);
                }
            });
    }

    function populateUserSelects(users) {
        const selects = ['migrateUserId', 'extensionChangeUserId', 'deptMoveUserId'];
        selects.forEach(selectId => {
            const select = document.getElementById(selectId);
            users.forEach(user => {
                const option = document.createElement('option');
                option.value = user.id;
                option.textContent = `${user.name} (Ext: ${user.extension})`;
                select.appendChild(option);
            });
        });
    }

    function populateDepartmentSelects(departments) {
        const selects = ['newDepartment', 'deptMoveNewDept'];
        selects.forEach(selectId => {
            const select = document.getElementById(selectId);
            departments.forEach(dept => {
                const option = document.createElement('option');
                option.value = dept.id;
                option.textContent = dept.name;
                select.appendChild(option);
            });
        });
    }

    function populateBulkUserSelection(users) {
        const container = document.getElementById('bulkUserSelection');
        users.forEach(user => {
            const card = document.createElement('div');
            card.className = 'user-card';
            card.onclick = () => toggleBulkUserSelection(user.id, card);
            card.dataset.userId = user.id;
            card.innerHTML = `
                <div class="user-info">
                    <div class="user-details">
                        <div class="user-name">${user.name}</div>
                        <div class="user-extension">Ext: ${user.extension}</div>
                        <div class="user-department">${user.department || 'No Department'}</div>
                    </div>
                </div>
            `;
            container.appendChild(card);
        });
    }

    const selectedBulkUsers = new Set();

    function toggleBulkUserSelection(userId, card) {
        if (selectedBulkUsers.has(userId)) {
            selectedBulkUsers.delete(userId);
            card.classList.remove('selected');
        } else {
            selectedBulkUsers.add(userId);
            card.classList.add('selected');
        }
    }
    </script>
</body>
</html>
