<?php
/**
 * FlexPBX User Notification Center
 * View and manage personal notifications
 */

require_once __DIR__ . '/user_auth_check.php';
require_once __DIR__ . '/user_header.php';
?>

<style>
    .container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
    }

    .header {
        background: white;
        border-radius: 12px;
        padding: 30px;
        margin-bottom: 30px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }

    h1 {
        color: #333;
        margin-bottom: 10px;
    }

    .subtitle {
        color: #666;
        font-size: 14px;
    }

    .filters {
        background: white;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
        align-items: center;
    }

    .filters select, .filters button {
        padding: 10px 15px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-size: 14px;
    }

    .filters select:focus {
        outline: none;
        border-color: #667eea;
    }

    .btn {
        padding: 10px 20px;
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

    .notifications-container {
        background: white;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }

    .notification-item {
        padding: 20px;
        border: 2px solid #f0f0f0;
        border-radius: 8px;
        margin-bottom: 15px;
        display: flex;
        gap: 15px;
        align-items: start;
        transition: all 0.3s;
        position: relative;
    }

    .notification-item:hover {
        border-color: #667eea;
        background: #f9fafb;
    }

    .notification-item.unread {
        background: #eff6ff;
        border-color: #3b82f6;
    }

    .notification-item.unread::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 4px;
        background: #3b82f6;
        border-radius: 8px 0 0 8px;
    }

    .notification-icon {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        flex-shrink: 0;
    }

    .icon-system { background: #3b82f6; color: white; }
    .icon-call { background: #10b981; color: white; }
    .icon-voicemail { background: #f59e0b; color: white; }
    .icon-sms { background: #8b5cf6; color: white; }
    .icon-alert { background: #ef4444; color: white; }
    .icon-message { background: #06b6d4; color: white; }
    .icon-task { background: #ec4899; color: white; }
    .icon-announcement { background: #3b82f6; color: white; }

    .notification-content {
        flex: 1;
    }

    .notification-title {
        font-weight: 600;
        font-size: 16px;
        color: #333;
        margin-bottom: 8px;
    }

    .notification-message {
        color: #666;
        font-size: 14px;
        margin-bottom: 10px;
        line-height: 1.5;
    }

    .notification-meta {
        display: flex;
        gap: 15px;
        font-size: 12px;
        color: #999;
        align-items: center;
    }

    .notification-actions {
        display: flex;
        gap: 10px;
        flex-direction: column;
    }

    .action-btn {
        background: none;
        border: none;
        cursor: pointer;
        color: #667eea;
        font-size: 20px;
        padding: 5px;
        transition: transform 0.2s;
    }

    .action-btn:hover {
        transform: scale(1.2);
    }

    .badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 600;
    }

    .badge-urgent { background: #fee2e2; color: #dc2626; }
    .badge-high { background: #fef3c7; color: #d97706; }
    .badge-normal { background: #dbeafe; color: #2563eb; }
    .badge-low { background: #e5e7eb; color: #6b7280; }

    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #999;
    }

    .empty-state-icon {
        font-size: 64px;
        margin-bottom: 15px;
    }

    .loading {
        text-align: center;
        padding: 40px;
        color: #666;
    }

    .pagination {
        display: flex;
        justify-content: center;
        gap: 10px;
        margin-top: 20px;
    }

    .pagination button {
        padding: 8px 16px;
        border: 2px solid #e0e0e0;
        background: white;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.3s;
    }

    .pagination button:hover:not(:disabled) {
        border-color: #667eea;
        color: #667eea;
    }

    .pagination button:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .stats-bar {
        display: flex;
        gap: 20px;
        margin-bottom: 20px;
        padding: 15px;
        background: #f9fafb;
        border-radius: 8px;
    }

    .stat-item {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .stat-label {
        font-size: 14px;
        color: #666;
    }

    .stat-value {
        font-size: 18px;
        font-weight: 600;
        color: #667eea;
    }

    .bulk-actions {
        display: none;
        gap: 10px;
        margin-bottom: 15px;
        padding: 15px;
        background: #f9fafb;
        border-radius: 8px;
    }

    .bulk-actions.active {
        display: flex;
    }
</style>

<div class="container">
    <div class="header">
        <h1>🔔 My Notifications</h1>
        <p class="subtitle">Stay updated with your notifications</p>
    </div>

    <!-- Stats Bar -->
    <div class="stats-bar">
        <div class="stat-item">
            <span class="stat-label">Unread:</span>
            <span class="stat-value" id="unread-count">0</span>
        </div>
        <div class="stat-item">
            <span class="stat-label">Total:</span>
            <span class="stat-value" id="total-count">0</span>
        </div>
    </div>

    <!-- Filters -->
    <div class="filters">
        <select id="filter-type">
            <option value="">All Types</option>
            <option value="system">System</option>
            <option value="announcement">Announcements</option>
            <option value="call">Calls</option>
            <option value="voicemail">Voicemail</option>
            <option value="sms">SMS</option>
            <option value="message">Messages</option>
            <option value="task">Tasks</option>
            <option value="alert">Alerts</option>
        </select>

        <select id="filter-priority">
            <option value="">All Priorities</option>
            <option value="urgent">Urgent</option>
            <option value="high">High</option>
            <option value="normal">Normal</option>
            <option value="low">Low</option>
        </select>

        <select id="show-read">
            <option value="false">Unread Only</option>
            <option value="true">All Notifications</option>
        </select>

        <button class="btn btn-primary" onclick="markAllRead()">Mark All Read</button>
        <button class="btn btn-secondary" onclick="refreshNotifications()">🔄 Refresh</button>
    </div>

    <!-- Notifications List -->
    <div class="notifications-container">
        <div id="notifications-list" class="loading">
            Loading notifications...
        </div>

        <!-- Pagination -->
        <div class="pagination" id="pagination" style="display: none;">
            <button id="prev-btn" onclick="previousPage()">← Previous</button>
            <span id="page-info">Page 1</span>
            <button id="next-btn" onclick="nextPage()">Next →</button>
        </div>
    </div>
</div>

<script>
    let currentPage = 0;
    const limit = 20;
    let currentFilter = {
        type: '',
        priority: '',
        show_read: 'false'
    };

    // Load notifications
    function loadNotifications() {
        const params = new URLSearchParams({
            action: 'list',
            limit: limit,
            offset: currentPage * limit,
            show_read: currentFilter.show_read,
            ...currentFilter
        });

        fetch('/api/notifications-manager.php?' + params)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    renderNotifications(data.notifications);
                    updatePagination(data.total);
                    updateStats();
                } else {
                    document.getElementById('notifications-list').innerHTML =
                        '<div class="empty-state"><div class="empty-state-icon">⚠️</div><p>Error loading notifications</p></div>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('notifications-list').innerHTML =
                    '<div class="empty-state"><div class="empty-state-icon">⚠️</div><p>Error loading notifications</p></div>';
            });
    }

    // Render notifications
    function renderNotifications(notifications) {
        const container = document.getElementById('notifications-list');

        if (notifications.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon">📭</div>
                    <p>No notifications found</p>
                </div>
            `;
            return;
        }

        container.innerHTML = notifications.map(notif => {
            const icon = getIconForType(notif.notification_type);
            const isUnread = !notif.is_read;
            const priorityBadge = `<span class="badge badge-${notif.priority}">${notif.priority.toUpperCase()}</span>`;
            const timeAgo = formatTimeAgo(notif.created_at);

            return `
                <div class="notification-item ${isUnread ? 'unread' : ''}" data-id="${notif.delivery_id}">
                    <div class="notification-icon icon-${notif.notification_type}">
                        ${icon}
                    </div>
                    <div class="notification-content">
                        <div class="notification-title">${notif.title}</div>
                        ${notif.message ? `<div class="notification-message">${notif.message}</div>` : ''}
                        <div class="notification-meta">
                            <span>⏰ ${timeAgo}</span>
                            ${priorityBadge}
                            ${notif.link_url ? `<a href="${notif.link_url}" style="color: #667eea;">View Details →</a>` : ''}
                        </div>
                    </div>
                    <div class="notification-actions">
                        ${isUnread ?
                            `<button class="action-btn" onclick="markAsRead(${notif.delivery_id})" title="Mark as read">✓</button>` :
                            `<button class="action-btn" onclick="markAsUnread(${notif.delivery_id})" title="Mark as unread">↩</button>`
                        }
                        <button class="action-btn" onclick="dismissNotification(${notif.delivery_id})" title="Dismiss">🗑️</button>
                    </div>
                </div>
            `;
        }).join('');
    }

    // Get icon for notification type
    function getIconForType(type) {
        const icons = {
            'system': '⚙️',
            'call': '📞',
            'voicemail': '📧',
            'sms': '💬',
            'alert': '⚠️',
            'message': '💌',
            'task': '📋',
            'announcement': '📢'
        };
        return icons[type] || '🔔';
    }

    // Format time ago
    function formatTimeAgo(timestamp) {
        const now = new Date();
        const time = new Date(timestamp);
        const diff = Math.floor((now - time) / 1000);

        if (diff < 60) return 'Just now';
        if (diff < 3600) return Math.floor(diff / 60) + ' minutes ago';
        if (diff < 86400) return Math.floor(diff / 3600) + ' hours ago';
        if (diff < 604800) return Math.floor(diff / 86400) + ' days ago';
        return time.toLocaleDateString();
    }

    // Mark as read
    function markAsRead(deliveryId) {
        fetch('/api/notifications-manager.php?action=mark_read', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ delivery_id: deliveryId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadNotifications();
            }
        });
    }

    // Mark as unread
    function markAsUnread(deliveryId) {
        fetch('/api/notifications-manager.php?action=mark_unread', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ delivery_id: deliveryId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadNotifications();
            }
        });
    }

    // Dismiss notification
    function dismissNotification(deliveryId) {
        if (!confirm('Are you sure you want to dismiss this notification?')) {
            return;
        }

        fetch('/api/notifications-manager.php?action=dismiss', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ delivery_id: deliveryId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadNotifications();
            }
        });
    }

    // Mark all as read
    function markAllRead() {
        if (!confirm('Mark all notifications as read?')) {
            return;
        }

        // Get all unread delivery IDs
        const unreadItems = document.querySelectorAll('.notification-item.unread');
        const deliveryIds = Array.from(unreadItems).map(item => parseInt(item.dataset.id));

        if (deliveryIds.length === 0) {
            alert('No unread notifications');
            return;
        }

        fetch('/api/notifications-manager.php?action=bulk_read', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ delivery_ids: deliveryIds })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadNotifications();
            }
        });
    }

    // Update stats
    function updateStats() {
        fetch('/api/notifications-manager.php?action=count')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('unread-count').textContent = data.unread_count;
                }
            });
    }

    // Update pagination
    function updatePagination(total) {
        const totalPages = Math.ceil(total / limit);
        document.getElementById('total-count').textContent = total;

        if (totalPages > 1) {
            document.getElementById('pagination').style.display = 'flex';
            document.getElementById('page-info').textContent = `Page ${currentPage + 1} of ${totalPages}`;
            document.getElementById('prev-btn').disabled = currentPage === 0;
            document.getElementById('next-btn').disabled = currentPage >= totalPages - 1;
        } else {
            document.getElementById('pagination').style.display = 'none';
        }
    }

    // Pagination
    function nextPage() {
        currentPage++;
        loadNotifications();
    }

    function previousPage() {
        currentPage--;
        loadNotifications();
    }

    // Refresh
    function refreshNotifications() {
        currentPage = 0;
        loadNotifications();
    }

    // Filter handlers
    document.getElementById('filter-type').addEventListener('change', function() {
        currentFilter.type = this.value;
        currentPage = 0;
        loadNotifications();
    });

    document.getElementById('filter-priority').addEventListener('change', function() {
        currentFilter.priority = this.value;
        currentPage = 0;
        loadNotifications();
    });

    document.getElementById('show-read').addEventListener('change', function() {
        currentFilter.show_read = this.value;
        currentPage = 0;
        loadNotifications();
    });

    // Auto-refresh every 30 seconds
    setInterval(updateStats, 30000);

    // Initialize
    loadNotifications();
</script>

<?php require_once __DIR__ . '/user_footer.php'; ?>
