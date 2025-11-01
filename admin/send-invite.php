<?php
require_once __DIR__ . '/admin_auth_check.php';
/**
 * FlexPBX Admin Portal - Send Invitation
 * Invite users and admins to the system
 */

session_start();

// Check authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /admin/login.php');
    exit;
}

$admin_username = $_SESSION['admin_username'] ?? 'Unknown';
$admin_role = $_SESSION['admin_role'] ?? 'admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send Invitation - FlexPBX Admin Portal</title>
    <link rel="stylesheet" href="/css/style.css">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f5f7fa;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 900px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .header {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .header h1 {
            margin: 0 0 0.5rem 0;
            color: #2c3e50;
        }

        .header .subtitle {
            color: #666;
            font-size: 0.95rem;
        }

        .admin-badge {
            display: inline-block;
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
            color: white;
            padding: 0.3rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 700;
            margin-left: 0.5rem;
        }

        .tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }

        .tab {
            padding: 0.75rem 1.5rem;
            background: white;
            border: none;
            border-radius: 8px 8px 0 0;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .tab.active {
            background: #2196f3;
            color: white;
        }

        .tab:hover:not(.active) {
            background: #f8f9fa;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .card {
            background: white;
            border-radius: 8px;
            padding: 2rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .card h2 {
            margin: 0 0 1.5rem 0;
            color: #2c3e50;
            font-size: 1.3rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #2c3e50;
            font-weight: 600;
        }

        .form-group .description {
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 0.5rem;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            font-size: 0.95rem;
            font-family: inherit;
            transition: border-color 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: #2196f3;
        }

        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }

        select.form-control {
            cursor: pointer;
        }

        .role-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .role-card {
            padding: 1rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .role-card:hover {
            border-color: #2196f3;
            background: #f8f9fa;
        }

        .role-card.selected {
            border-color: #2196f3;
            background: #e3f2fd;
        }

        .role-card input[type="radio"] {
            display: none;
        }

        .role-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .role-title {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.25rem;
        }

        .role-desc {
            font-size: 0.85rem;
            color: #666;
        }

        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.95rem;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
        }

        .btn-primary {
            background: #2196f3;
            color: white;
        }

        .btn-primary:hover:not(:disabled) {
            background: #1976d2;
        }

        .btn-primary:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn-danger {
            background: #f44336;
            color: white;
        }

        .btn-danger:hover {
            background: #d32f2f;
        }

        .actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
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

        .invite-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .invite-item {
            padding: 1rem;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            margin-bottom: 0.75rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .invite-info {
            flex: 1;
        }

        .invite-email {
            font-weight: 600;
            color: #2c3e50;
        }

        .invite-meta {
            font-size: 0.85rem;
            color: #666;
            margin-top: 0.25rem;
        }

        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 0.5rem;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-accepted {
            background: #d4edda;
            color: #155724;
        }

        .status-expired {
            background: #f8d7da;
            color: #721c24;
        }

        .status-revoked {
            background: #e0e0e0;
            color: #666;
        }

        .copy-btn {
            padding: 0.5rem 1rem;
            background: #2196f3;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.85rem;
            margin-left: 1rem;
        }

        .copy-btn:hover {
            background: #1976d2;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>✉️ Invite Users & Admins</h1>
            <p class="subtitle">
                Admin: <?= htmlspecialchars($admin_username) ?>
                <span class="admin-badge"><?= strtoupper(htmlspecialchars($admin_role)) ?></span>
            </p>
        </div>

        <div class="tabs">
            <button class="tab active" onclick="switchTab('send')">Send Invitation</button>
            <button class="tab" onclick="switchTab('manage')">Manage Invitations</button>
        </div>

        <div id="alert-container"></div>

        <!-- Send Invitation Tab -->
        <div id="tab-send" class="tab-content active">
            <div class="card">
                <h2>Send New Invitation</h2>

                <form id="invite-form" onsubmit="sendInvite(event)">
                    <!-- Email -->
                    <div class="form-group">
                        <label for="email">Email Address *</label>
                        <input
                            type="email"
                            id="email"
                            class="form-control"
                            placeholder="user@example.com"
                            required
                        >
                    </div>

                    <!-- Role -->
                    <div class="form-group">
                        <label>Select Role *</label>
                        <div class="description">Choose what level of access the invitee will have</div>

                        <div class="role-cards">
                            <label class="role-card selected">
                                <input type="radio" name="role" value="user" checked>
                                <div class="role-icon">👤</div>
                                <div class="role-title">User</div>
                                <div class="role-desc">Standard user with extension</div>
                            </label>

                            <label class="role-card">
                                <input type="radio" name="role" value="agent">
                                <div class="role-icon">📞</div>
                                <div class="role-title">Agent</div>
                                <div class="role-desc">Call center agent</div>
                            </label>

                            <label class="role-card">
                                <input type="radio" name="role" value="supervisor">
                                <div class="role-icon">👔</div>
                                <div class="role-title">Supervisor</div>
                                <div class="role-desc">Team supervisor</div>
                            </label>

                            <label class="role-card">
                                <input type="radio" name="role" value="admin">
                                <div class="role-icon">⭐</div>
                                <div class="role-title">Admin</div>
                                <div class="role-desc">Full system access</div>
                            </label>
                        </div>
                    </div>

                    <!-- Full Name -->
                    <div class="form-group">
                        <label for="full_name">Full Name (Optional)</label>
                        <input
                            type="text"
                            id="full_name"
                            class="form-control"
                            placeholder="John Doe"
                        >
                    </div>

                    <!-- Extension (for users only) -->
                    <div class="form-group" id="extension-group" style="display: none;">
                        <label for="extension">Pre-assign Extension (Optional)</label>
                        <div class="description">Leave blank to auto-assign next available extension</div>
                        <input
                            type="text"
                            id="extension"
                            class="form-control"
                            placeholder="2000-2999"
                            pattern="[2-9]\d{3}"
                        >
                    </div>

                    <!-- Personal Message -->
                    <div class="form-group">
                        <label for="message">Personal Message (Optional)</label>
                        <textarea
                            id="message"
                            class="form-control"
                            placeholder="Add a personal message to the invitation email..."
                        ></textarea>
                    </div>

                    <!-- Actions -->
                    <div class="actions">
                        <a href="/admin/dashboard.html" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary" id="send-btn">
                            📧 Send Invitation
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Manage Invitations Tab -->
        <div id="tab-manage" class="tab-content">
            <div class="card">
                <h2>Pending & Recent Invitations</h2>

                <div id="invites-list">
                    <p style="text-align: center; color: #999;">Loading invitations...</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Switch tabs
        function switchTab(tabName) {
            document.querySelectorAll('.tab').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));

            event.target.classList.add('active');
            document.getElementById('tab-' + tabName).classList.add('active');

            if (tabName === 'manage') {
                loadInvitations();
            }
        }

        // Role card selection
        document.querySelectorAll('.role-card').forEach(card => {
            card.addEventListener('click', function() {
                document.querySelectorAll('.role-card').forEach(el => el.classList.remove('selected'));
                this.classList.add('selected');
                this.querySelector('input[type="radio"]').checked = true;

                // Show/hide extension field based on role
                const role = this.querySelector('input[type="radio"]').value;
                const extensionGroup = document.getElementById('extension-group');

                if (role === 'user' || role === 'agent') {
                    extensionGroup.style.display = 'block';
                } else {
                    extensionGroup.style.display = 'none';
                }
            });
        });

        // Send invitation
        async function sendInvite(event) {
            event.preventDefault();

            const email = document.getElementById('email').value.trim();
            const role = document.querySelector('input[name="role"]:checked').value;
            const full_name = document.getElementById('full_name').value.trim();
            const extension = document.getElementById('extension').value.trim();
            const message = document.getElementById('message').value.trim();

            const sendBtn = document.getElementById('send-btn');
            sendBtn.disabled = true;
            sendBtn.textContent = 'Sending...';

            try {
                const response = await fetch('/api/invites.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'send_invite',
                        email: email,
                        role: role,
                        full_name: full_name,
                        extension: extension || null,
                        message: message
                    })
                });

                const data = await response.json();

                if (data.success) {
                    showAlert(`✓ Invitation sent to ${email}! ${data.email_sent ? 'Email delivered.' : 'Email queued.'}`, 'success');

                    // Show invite URL
                    showAlert(`Invite URL: <input type="text" value="${data.invite_url}" readonly style="width: 100%; padding: 0.5rem; margin-top: 0.5rem;" onclick="this.select()">`, 'success');

                    // Reset form
                    document.getElementById('invite-form').reset();
                    document.querySelectorAll('.role-card').forEach(el => el.classList.remove('selected'));
                    document.querySelector('.role-card').classList.add('selected');
                    document.getElementById('extension-group').style.display = 'none';
                } else {
                    showAlert('Failed to send invitation: ' + data.error, 'error');
                }
            } catch (error) {
                console.error('Failed to send invitation:', error);
                showAlert('Failed to send invitation. Please try again.', 'error');
            } finally {
                sendBtn.disabled = false;
                sendBtn.textContent = '📧 Send Invitation';
            }
        }

        // Load invitations
        async function loadInvitations() {
            try {
                const response = await fetch('/api/invites.php?action=list_invites');
                const data = await response.json();

                if (data.success) {
                    renderInvitations(data.invitations);
                } else {
                    document.getElementById('invites-list').innerHTML = '<p style="text-align: center; color: #999;">Failed to load invitations</p>';
                }
            } catch (error) {
                console.error('Failed to load invitations:', error);
                document.getElementById('invites-list').innerHTML = '<p style="text-align: center; color: #999;">Failed to load invitations</p>';
            }
        }

        // Render invitations
        function renderInvitations(invitations) {
            const container = document.getElementById('invites-list');

            if (invitations.length === 0) {
                container.innerHTML = '<p style="text-align: center; color: #999;">No invitations yet</p>';
                return;
            }

            let html = '<ul class="invite-list">';

            invitations.forEach(invite => {
                const statusClass = 'status-' + invite.status;
                const created = new Date(invite.created_at * 1000).toLocaleString();
                const expires = new Date(invite.expires_at * 1000).toLocaleString();

                html += `
                    <li class="invite-item">
                        <div class="invite-info">
                            <div class="invite-email">
                                ${invite.email}
                                <span class="status-badge ${statusClass}">${invite.status}</span>
                            </div>
                            <div class="invite-meta">
                                Role: ${invite.role} • Created: ${created} • Expires: ${expires}
                            </div>
                        </div>
                        ${invite.status === 'pending' ? `<button class="btn btn-danger btn-sm" onclick="revokeInvite('${invite.id}')">Revoke</button>` : ''}
                    </li>
                `;
            });

            html += '</ul>';
            container.innerHTML = html;
        }

        // Revoke invitation
        async function revokeInvite(inviteId) {
            if (!confirm('Are you sure you want to revoke this invitation?')) {
                return;
            }

            try {
                const response = await fetch('/api/invites.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'revoke_invite',
                        invite_id: inviteId
                    })
                });

                const data = await response.json();

                if (data.success) {
                    showAlert('✓ Invitation revoked', 'success');
                    loadInvitations();
                } else {
                    showAlert('Failed to revoke invitation: ' + data.error, 'error');
                }
            } catch (error) {
                console.error('Failed to revoke invitation:', error);
                showAlert('Failed to revoke invitation. Please try again.', 'error');
            }
        }

        // Show alert
        function showAlert(message, type) {
            const container = document.getElementById('alert-container');
            const alertClass = type === 'success' ? 'alert-success' : 'alert-error';

            const alertDiv = document.createElement('div');
            alertDiv.className = `alert ${alertClass}`;
            alertDiv.innerHTML = message;

            container.appendChild(alertDiv);

            // Auto-hide after 5 seconds
            setTimeout(() => {
                alertDiv.remove();
            }, 5000);
        }
    </script>
</body>
</html>
