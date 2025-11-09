<?php
/**
 * FlexPBX Notifications Dropdown Component
 * Real-time notification bell with dropdown
 *
 * Usage: Include this file in your header
 * <?php require_once __DIR__ . '/../includes/notifications-dropdown.php'; ?>
 */

// Get user ID from session
$user_id = $_SESSION['user_id'] ?? $_SESSION['user_extension'] ?? null;

if (!$user_id) {
    return; // Don't show if user is not logged in
}
?>

<style>
    .notification-bell-container {
        position: relative;
        display: inline-block;
    }

    .notification-bell {
        position: relative;
        cursor: pointer;
        font-size: 24px;
        color: #333;
        transition: transform 0.3s;
        padding: 8px;
    }

    .notification-bell:hover {
        transform: scale(1.1);
    }

    .notification-badge {
        position: absolute;
        top: 0;
        right: 0;
        background: #ef4444;
        color: white;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        font-weight: bold;
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0%, 100% {
            transform: scale(1);
        }
        50% {
            transform: scale(1.1);
        }
    }

    .notification-dropdown {
        position: absolute;
        top: 100%;
        right: 0;
        background: white;
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        width: 400px;
        max-height: 500px;
        display: none;
        z-index: 1000;
        margin-top: 10px;
    }

    .notification-dropdown.active {
        display: block;
        animation: slideDown 0.3s ease-out;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .notification-dropdown-header {
        padding: 20px;
        border-bottom: 2px solid #f0f0f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .notification-dropdown-header h3 {
        margin: 0;
        font-size: 18px;
        color: #333;
    }

    .mark-all-read-btn {
        background: #667eea;
        color: white;
        border: none;
        padding: 6px 12px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 12px;
        font-weight: 600;
        transition: background 0.3s;
    }

    .mark-all-read-btn:hover {
        background: #5568d3;
    }

    .notification-dropdown-list {
        max-height: 350px;
        overflow-y: auto;
    }

    .notification-dropdown-item {
        padding: 15px 20px;
        border-bottom: 1px solid #f0f0f0;
        display: flex;
        gap: 12px;
        cursor: pointer;
        transition: background 0.3s;
    }

    .notification-dropdown-item:hover {
        background: #f9fafb;
    }

    .notification-dropdown-item.unread {
        background: #eff6ff;
    }

    .notification-dropdown-item.unread::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        bottom: 0;
        width: 3px;
        background: #3b82f6;
    }

    .notification-dropdown-icon {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        flex-shrink: 0;
    }

    .notif-icon-system { background: #3b82f6; color: white; }
    .notif-icon-call { background: #10b981; color: white; }
    .notif-icon-voicemail { background: #f59e0b; color: white; }
    .notif-icon-sms { background: #8b5cf6; color: white; }
    .notif-icon-alert { background: #ef4444; color: white; }
    .notif-icon-message { background: #06b6d4; color: white; }
    .notif-icon-task { background: #ec4899; color: white; }
    .notif-icon-announcement { background: #3b82f6; color: white; }

    .notification-dropdown-content {
        flex: 1;
        min-width: 0;
    }

    .notification-dropdown-title {
        font-weight: 600;
        font-size: 14px;
        color: #333;
        margin-bottom: 4px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .notification-dropdown-message {
        font-size: 13px;
        color: #666;
        margin-bottom: 4px;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .notification-dropdown-time {
        font-size: 11px;
        color: #999;
    }

    .notification-dropdown-footer {
        padding: 15px 20px;
        border-top: 2px solid #f0f0f0;
        text-align: center;
    }

    .view-all-link {
        color: #667eea;
        text-decoration: none;
        font-weight: 600;
        font-size: 14px;
        transition: color 0.3s;
    }

    .view-all-link:hover {
        color: #5568d3;
    }

    .notification-empty {
        padding: 40px 20px;
        text-align: center;
        color: #999;
    }

    .notification-empty-icon {
        font-size: 48px;
        margin-bottom: 10px;
    }

    .notification-loading {
        padding: 30px;
        text-align: center;
        color: #666;
    }

    /* Scrollbar styling */
    .notification-dropdown-list::-webkit-scrollbar {
        width: 6px;
    }

    .notification-dropdown-list::-webkit-scrollbar-track {
        background: #f1f1f1;
    }

    .notification-dropdown-list::-webkit-scrollbar-thumb {
        background: #888;
        border-radius: 3px;
    }

    .notification-dropdown-list::-webkit-scrollbar-thumb:hover {
        background: #555;
    }
</style>

<div class="notification-bell-container">
    <div class="notification-bell" onclick="toggleNotificationDropdown()">
        🔔
        <span class="notification-badge" id="notification-badge" style="display: none;">0</span>
    </div>

    <div class="notification-dropdown" id="notification-dropdown">
        <div class="notification-dropdown-header">
            <h3>Notifications</h3>
            <button class="mark-all-read-btn" onclick="markAllNotificationsRead()">Mark all read</button>
        </div>

        <div class="notification-dropdown-list" id="notification-dropdown-list">
            <div class="notification-loading">Loading notifications...</div>
        </div>

        <div class="notification-dropdown-footer">
            <a href="/user-portal/notifications.php" class="view-all-link">View All Notifications →</a>
        </div>
    </div>
</div>

<script>
    // Global variables
    let notificationDropdownOpen = false;
    let notificationRefreshInterval = null;

    // Toggle dropdown
    function toggleNotificationDropdown() {
        notificationDropdownOpen = !notificationDropdownOpen;
        const dropdown = document.getElementById('notification-dropdown');

        if (notificationDropdownOpen) {
            dropdown.classList.add('active');
            loadDropdownNotifications();
        } else {
            dropdown.classList.remove('active');
        }
    }

    // Close dropdown when clicking outside
    document.addEventListener('click', function(event) {
        const container = document.querySelector('.notification-bell-container');
        if (container && !container.contains(event.target) && notificationDropdownOpen) {
            notificationDropdownOpen = false;
            document.getElementById('notification-dropdown').classList.remove('active');
        }
    });

    // Load notifications for dropdown
    function loadDropdownNotifications() {
        const params = new URLSearchParams({
            action: 'list',
            limit: 10,
            offset: 0,
            show_read: 'false'
        });

        fetch('/api/notifications-manager.php?' + params)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    renderDropdownNotifications(data.notifications);
                } else {
                    document.getElementById('notification-dropdown-list').innerHTML =
                        '<div class="notification-empty"><div class="notification-empty-icon">⚠️</div><p>Error loading notifications</p></div>';
                }
            })
            .catch(error => {
                console.error('Error loading notifications:', error);
                document.getElementById('notification-dropdown-list').innerHTML =
                    '<div class="notification-empty"><div class="notification-empty-icon">⚠️</div><p>Error loading notifications</p></div>';
            });
    }

    // Render notifications in dropdown
    function renderDropdownNotifications(notifications) {
        const container = document.getElementById('notification-dropdown-list');

        if (notifications.length === 0) {
            container.innerHTML = `
                <div class="notification-empty">
                    <div class="notification-empty-icon">📭</div>
                    <p>No new notifications</p>
                </div>
            `;
            return;
        }

        container.innerHTML = notifications.map(notif => {
            const icon = getNotificationIcon(notif.notification_type);
            const timeAgo = formatNotificationTime(notif.created_at);
            const isUnread = !notif.is_read;

            return `
                <div class="notification-dropdown-item ${isUnread ? 'unread' : ''}"
                     onclick="handleNotificationClick(${notif.delivery_id}, '${notif.link_url || ''}')"
                     style="position: relative;">
                    <div class="notification-dropdown-icon notif-icon-${notif.notification_type}">
                        ${icon}
                    </div>
                    <div class="notification-dropdown-content">
                        <div class="notification-dropdown-title">${escapeHtml(notif.title)}</div>
                        ${notif.message ? `<div class="notification-dropdown-message">${escapeHtml(notif.message)}</div>` : ''}
                        <div class="notification-dropdown-time">${timeAgo}</div>
                    </div>
                </div>
            `;
        }).join('');
    }

    // Get icon for notification type
    function getNotificationIcon(type) {
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

    // Format notification time
    function formatNotificationTime(timestamp) {
        const now = new Date();
        const time = new Date(timestamp);
        const diff = Math.floor((now - time) / 1000);

        if (diff < 60) return 'Just now';
        if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
        if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';
        return time.toLocaleDateString();
    }

    // Handle notification click
    function handleNotificationClick(deliveryId, linkUrl) {
        // Mark as read
        fetch('/api/notifications-manager.php?action=mark_read', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ delivery_id: deliveryId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateNotificationBadge();
                if (linkUrl) {
                    window.location.href = linkUrl;
                }
            }
        });
    }

    // Mark all as read
    function markAllNotificationsRead() {
        const unreadItems = document.querySelectorAll('.notification-dropdown-item.unread');
        if (unreadItems.length === 0) {
            alert('No unread notifications');
            return;
        }

        const deliveryIds = Array.from(unreadItems).map(item => {
            const onclickAttr = item.getAttribute('onclick');
            const match = onclickAttr.match(/handleNotificationClick\((\d+)/);
            return match ? parseInt(match[1]) : null;
        }).filter(id => id !== null);

        if (deliveryIds.length === 0) return;

        fetch('/api/notifications-manager.php?action=bulk_read', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ delivery_ids: deliveryIds })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadDropdownNotifications();
                updateNotificationBadge();
            }
        });
    }

    // Update notification badge count
    function updateNotificationBadge() {
        fetch('/api/notifications-manager.php?action=count')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const badge = document.getElementById('notification-badge');
                    const count = data.unread_count;

                    if (count > 0) {
                        badge.textContent = count > 99 ? '99+' : count;
                        badge.style.display = 'flex';
                    } else {
                        badge.style.display = 'none';
                    }
                }
            })
            .catch(error => console.error('Error updating badge:', error));
    }

    // Escape HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Initialize and start auto-refresh
    function initNotificationDropdown() {
        updateNotificationBadge();

        // Auto-refresh every 30 seconds
        notificationRefreshInterval = setInterval(updateNotificationBadge, 30000);
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initNotificationDropdown);
    } else {
        initNotificationDropdown();
    }
</script>
