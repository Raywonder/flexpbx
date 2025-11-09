<?php
require_once __DIR__ . '/admin_auth_check.php';

// Set page title for header
$page_title = 'FlexPBX Notification Center';

// Include the admin header
require_once __DIR__ . '/includes/admin_header.php';
?>
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
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px 20px 20px;
        }

        .header {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }

        h1 {
            color: #333;
            margin-bottom: 10px;
        }

        .subtitle {
            color: #666;
            font-size: 14px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .stat-card h3 {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 0.5rem;
        }

        .stat-card .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #667eea;
        }

        .main-content {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 20px;
            margin-bottom: 20px;
        }

        .card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }

        .card h2 {
            margin-bottom: 20px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
        }

        input, select, textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        textarea {
            resize: vertical;
            min-height: 100px;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5568d3;
        }

        .btn-secondary {
            background: #f0f0f0;
            color: #333;
        }

        .btn-secondary:hover {
            background: #e0e0e0;
        }

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .notification-list {
            max-height: 600px;
            overflow-y: auto;
        }

        .notification-item {
            padding: 15px;
            border: 2px solid #f0f0f0;
            border-radius: 8px;
            margin-bottom: 10px;
            display: flex;
            gap: 15px;
            align-items: start;
            transition: all 0.3s;
        }

        .notification-item:hover {
            border-color: #667eea;
            background: #f9fafb;
        }

        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }

        .icon-system { background: #3b82f6; color: white; }
        .icon-call { background: #10b981; color: white; }
        .icon-voicemail { background: #f59e0b; color: white; }
        .icon-sms { background: #8b5cf6; color: white; }
        .icon-alert { background: #ef4444; color: white; }
        .icon-message { background: #06b6d4; color: white; }
        .icon-task { background: #ec4899; color: white; }

        .notification-content {
            flex: 1;
        }

        .notification-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .notification-message {
            color: #666;
            font-size: 14px;
            margin-bottom: 5px;
        }

        .notification-meta {
            display: flex;
            gap: 10px;
            font-size: 12px;
            color: #999;
        }

        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }

        .badge-urgent { background: #fee2e2; color: #dc2626; }
        .badge-high { background: #fef3c7; color: #d97706; }
        .badge-normal { background: #dbeafe; color: #2563eb; }
        .badge-low { background: #e5e7eb; color: #6b7280; }

        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #f0f0f0;
        }

        .tab {
            padding: 12px 20px;
            cursor: pointer;
            border: none;
            background: none;
            color: #666;
            font-weight: 500;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }

        .tab.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }

        .tab:hover {
            color: #667eea;
        }

        .filter-bar {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .filter-bar select {
            width: auto;
            min-width: 150px;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #10b981;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #ef4444;
        }

        .loading {
            text-align: center;
            padding: 20px;
            color: #666;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
        }

        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 10px;
        }

        .scheduled-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .scheduled-item {
            padding: 12px;
            border-left: 4px solid #667eea;
            background: #f9fafb;
            margin-bottom: 10px;
            border-radius: 4px;
        }

        .scheduled-time {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }

        @media (max-width: 1024px) {
            .main-content {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="container">
        <div class="header">
            <h1>📢 Notification Center</h1>
            <p class="subtitle">Manage and send notifications to users, roles, and groups</p>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Sent</h3>
                <div class="stat-value" id="stat-total">0</div>
            </div>
            <div class="stat-card">
                <h3>Total Delivered</h3>
                <div class="stat-value" id="stat-delivered">0</div>
            </div>
            <div class="stat-card">
                <h3>Total Read</h3>
                <div class="stat-value" id="stat-read">0</div>
            </div>
            <div class="stat-card">
                <h3>Scheduled</h3>
                <div class="stat-value" id="stat-scheduled">0</div>
            </div>
        </div>

        <div class="main-content">
            <!-- Send Notification Form -->
            <div class="card">
                <h2>📤 Send Notification</h2>

                <div id="alert-container"></div>

                <form id="notification-form">
                    <div class="form-group">
                        <label>Notification Type *</label>
                        <select name="notification_type" required>
                            <option value="system">System</option>
                            <option value="announcement">Announcement</option>
                            <option value="alert">Alert</option>
                            <option value="call">Call</option>
                            <option value="voicemail">Voicemail</option>
                            <option value="sms">SMS</option>
                            <option value="message">Message</option>
                            <option value="task">Task</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Title *</label>
                        <input type="text" name="title" required placeholder="Notification title">
                    </div>

                    <div class="form-group">
                        <label>Message</label>
                        <textarea name="message" placeholder="Notification message (optional)"></textarea>
                    </div>

                    <div class="form-group">
                        <label>Priority</label>
                        <select name="priority">
                            <option value="low">Low</option>
                            <option value="normal" selected>Normal</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Target</label>
                        <select name="target_type" id="target-type">
                            <option value="all">All Users</option>
                            <option value="role">Specific Role</option>
                            <option value="user">Specific User</option>
                            <option value="group">Specific Group</option>
                        </select>
                    </div>

                    <div class="form-group" id="target-role-group" style="display: none;">
                        <label>Role</label>
                        <select name="target_role">
                            <option value="">Select Role</option>
                            <option value="admin">Admin</option>
                            <option value="moderator">Moderator</option>
                            <option value="user">User</option>
                        </select>
                    </div>

                    <div class="form-group" id="target-user-group" style="display: none;">
                        <label>User ID</label>
                        <input type="text" name="target_user_id" placeholder="Enter user ID">
                    </div>

                    <div class="form-group" id="target-group-group" style="display: none;">
                        <label>Group Name</label>
                        <input type="text" name="target_group" placeholder="Enter group name">
                    </div>

                    <div class="form-group">
                        <label>Icon (optional)</label>
                        <input type="text" name="icon" placeholder="bell, phone, mail, alert, etc.">
                    </div>

                    <div class="form-group">
                        <label>Link URL (optional)</label>
                        <input type="url" name="link_url" placeholder="https://example.com/page">
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="is_scheduled" id="schedule-checkbox">
                            Schedule for later
                        </label>
                    </div>

                    <div class="form-group" id="schedule-group" style="display: none;">
                        <label>Schedule Time</label>
                        <input type="datetime-local" name="scheduled_for">
                    </div>

                    <button type="submit" class="btn btn-primary">Send Notification</button>
                </form>
            </div>

            <!-- Scheduled Notifications -->
            <div class="card">
                <h2>🕐 Scheduled</h2>
                <div class="scheduled-list" id="scheduled-list">
                    <div class="loading">Loading scheduled notifications...</div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="card">
            <h2>📊 Recent Activity</h2>

            <div class="tabs">
                <button class="tab active" data-tab="by-type">By Type</button>
                <button class="tab" data-tab="by-date">By Date</button>
            </div>

            <div id="tab-by-type" class="tab-content">
                <canvas id="chart-by-type" width="400" height="200"></canvas>
            </div>

            <div id="tab-by-date" class="tab-content" style="display: none;">
                <canvas id="chart-by-date" width="400" height="200"></canvas>
            </div>
        </div>
    </div>

    <script>
        // Handle target type change
        document.getElementById('target-type').addEventListener('change', function() {
            const value = this.value;
            document.getElementById('target-role-group').style.display = value === 'role' ? 'block' : 'none';
            document.getElementById('target-user-group').style.display = value === 'user' ? 'block' : 'none';
            document.getElementById('target-group-group').style.display = value === 'group' ? 'block' : 'none';
        });

        // Handle schedule checkbox
        document.getElementById('schedule-checkbox').addEventListener('change', function() {
            document.getElementById('schedule-group').style.display = this.checked ? 'block' : 'none';
        });

        // Handle tab clicks
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', function() {
                const tabName = this.dataset.tab;

                // Update active tab
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');

                // Show/hide tab content
                document.querySelectorAll('.tab-content').forEach(content => {
                    content.style.display = 'none';
                });
                document.getElementById('tab-' + tabName).style.display = 'block';
            });
        });

        // Load statistics
        function loadStats() {
            fetch('/api/notifications-manager.php?action=get_stats')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const stats = data.stats.overall;
                        document.getElementById('stat-total').textContent = stats.total_notifications || 0;
                        document.getElementById('stat-delivered').textContent = stats.total_deliveries || 0;
                        document.getElementById('stat-read').textContent = stats.total_read || 0;

                        // Load scheduled count
                        loadScheduled();
                    }
                })
                .catch(error => console.error('Error loading stats:', error));
        }

        // Load scheduled notifications
        function loadScheduled() {
            fetch('/api/notifications-manager.php?action=scheduled')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const scheduled = data.scheduled_notifications;
                        document.getElementById('stat-scheduled').textContent = scheduled.length;

                        const container = document.getElementById('scheduled-list');

                        if (scheduled.length === 0) {
                            container.innerHTML = '<div class="empty-state"><div class="empty-state-icon">📭</div><p>No scheduled notifications</p></div>';
                        } else {
                            container.innerHTML = scheduled.map(item => `
                                <div class="scheduled-item">
                                    <div class="notification-title">${item.title}</div>
                                    <div class="scheduled-time">📅 ${new Date(item.scheduled_for).toLocaleString()}</div>
                                </div>
                            `).join('');
                        }
                    }
                })
                .catch(error => console.error('Error loading scheduled:', error));
        }

        // Handle form submission
        document.getElementById('notification-form').addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const data = {};

            formData.forEach((value, key) => {
                if (key === 'is_scheduled') {
                    data[key] = true;
                } else {
                    data[key] = value;
                }
            });

            // Set target based on target_type
            const targetType = data.target_type;
            delete data.target_type;

            if (targetType === 'role') {
                data.target_role = data.target_role || null;
                delete data.target_user_id;
                delete data.target_group;
            } else if (targetType === 'user') {
                data.target_user_id = data.target_user_id || null;
                delete data.target_role;
                delete data.target_group;
            } else if (targetType === 'group') {
                data.target_group = data.target_group || null;
                delete data.target_role;
                delete data.target_user_id;
            } else {
                // All users
                delete data.target_role;
                delete data.target_user_id;
                delete data.target_group;
            }

            showAlert('Sending notification...', 'info');

            fetch('/api/notifications-manager.php?action=send', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    showAlert(`Notification sent to ${result.recipients_count} recipient(s)!`, 'success');
                    this.reset();
                    loadStats();
                } else {
                    showAlert('Error: ' + result.error, 'error');
                }
            })
            .catch(error => {
                showAlert('Error sending notification: ' + error.message, 'error');
            });
        });

        // Show alert
        function showAlert(message, type) {
            const alertContainer = document.getElementById('alert-container');
            const alertClass = type === 'success' ? 'alert-success' : 'alert-error';

            alertContainer.innerHTML = `<div class="alert ${alertClass}">${message}</div>`;

            if (type === 'success') {
                setTimeout(() => {
                    alertContainer.innerHTML = '';
                }, 3000);
            }
        }

        // Initialize
        loadStats();
    </script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
