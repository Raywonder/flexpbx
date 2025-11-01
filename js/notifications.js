/**
 * FlexPBX Notifications System
 * Client-side notification handler
 * Created: October 17, 2025
 */

class FlexPBXNotifications {
    constructor(options = {}) {
        this.pingInterval = options.pingInterval || 30000; // 30 seconds
        this.reminderInterval = options.reminderInterval || 1800000; // 30 minutes
        this.checkInterval = options.checkInterval || 60000; // 1 minute
        this.sessionStartTime = Date.now();
        this.lastReminderTime = Date.now();
        this.pingTimer = null;
        this.checkTimer = null;
        this.reminderTimer = null;

        this.init();
    }

    init() {
        // Start keep-alive ping
        this.startPing();

        // Start notification checking
        this.startNotificationCheck();

        // Start session reminders
        this.startSessionReminders();

        // Show login notification
        this.sendLoginNotification();

        // Handle page visibility changes
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                this.checkNotifications();
            }
        });
    }

    /**
     * Keep session alive with ping
     */
    startPing() {
        this.ping(); // Initial ping

        this.pingTimer = setInterval(() => {
            this.ping();
        }, this.pingInterval);
    }

    async ping() {
        try {
            const response = await fetch('/api/notifications.php?path=ping');
            const data = await response.json();

            if (data.success && data.logged_in) {
                // Update session info in UI if element exists
                this.updateSessionInfo(data);

                // Check for new notifications
                if (data.unread_notifications > 0) {
                    this.showNotificationBadge(data.unread_notifications);
                }
            } else {
                // Session expired
                this.handleSessionExpired();
            }
        } catch (error) {
            console.error('Ping failed:', error);
        }
    }

    /**
     * Check for new notifications
     */
    startNotificationCheck() {
        this.checkNotifications(); // Initial check

        this.checkTimer = setInterval(() => {
            this.checkNotifications();
        }, this.checkInterval);
    }

    async checkNotifications() {
        try {
            const response = await fetch('/api/notifications.php?path=check');
            const data = await response.json();

            if (data.success) {
                this.displayNotifications(data.notifications);
                this.showNotificationBadge(data.unread_count);
            }
        } catch (error) {
            console.error('Notification check failed:', error);
        }
    }

    /**
     * Session reminder alerts
     */
    startSessionReminders() {
        this.reminderTimer = setInterval(() => {
            const now = Date.now();
            const sessionDuration = Math.floor((now - this.sessionStartTime) / 60000); // minutes

            if (sessionDuration > 0 && sessionDuration % 30 === 0) {
                this.showSessionReminder(sessionDuration);
            }
        }, this.reminderInterval);
    }

    showSessionReminder(duration) {
        const hours = Math.floor(duration / 60);
        const minutes = duration % 60;

        let message = `You've been logged in for `;
        if (hours > 0) {
            message += `${hours} hour${hours > 1 ? 's' : ''} `;
        }
        if (minutes > 0 || hours === 0) {
            message += `${minutes} minute${minutes !== 1 ? 's' : ''}`;
        }

        this.showToast('Session Active', message, 'info');
    }

    /**
     * Send login notification
     */
    async sendLoginNotification() {
        const now = new Date().toLocaleTimeString();
        const message = `You logged in at ${now}. Your session is active.`;

        this.showToast('Welcome!', message, 'success');
    }

    /**
     * Display notifications
     */
    displayNotifications(notifications) {
        const container = document.getElementById('notificationContainer');
        if (!container) return;

        const unread = notifications.filter(n => !n.read);

        if (unread.length > 0) {
            unread.forEach(notif => {
                if (!this.hasShownNotification(notif.id)) {
                    this.showToast(notif.title, notif.message, notif.type);
                    this.markAsShown(notif.id);
                }
            });
        }
    }

    /**
     * Show toast notification
     */
    showToast(title, message, type = 'info') {
        // Create toast container if it doesn't exist
        let container = document.getElementById('toastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toastContainer';
            container.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 9999;
                max-width: 350px;
            `;
            document.body.appendChild(container);
        }

        // Create toast
        const toast = document.createElement('div');
        toast.className = `alert alert-${this.getBootstrapType(type)} alert-dismissible fade show`;
        toast.style.cssText = `
            margin-bottom: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        `;

        const icon = this.getIcon(type);

        toast.innerHTML = `
            <strong>${icon} ${title}</strong><br>
            <small>${message}</small>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

        container.appendChild(toast);

        // Auto-remove after 5 seconds
        setTimeout(() => {
            toast.remove();
        }, 5000);
    }

    /**
     * Show notification badge
     */
    showNotificationBadge(count) {
        const badge = document.getElementById('notificationBadge');
        if (badge) {
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count;
                badge.style.display = 'inline-block';
            } else {
                badge.style.display = 'none';
            }
        }
    }

    /**
     * Update session info in UI
     */
    updateSessionInfo(data) {
        const sessionDuration = document.getElementById('sessionDuration');
        if (sessionDuration) {
            const minutes = Math.floor(data.session_duration / 60);
            const hours = Math.floor(minutes / 60);
            const remainingMinutes = minutes % 60;

            let text = '';
            if (hours > 0) {
                text = `${hours}h ${remainingMinutes}m`;
            } else {
                text = `${minutes}m`;
            }
            sessionDuration.textContent = text;
        }
    }

    /**
     * Mark notification as read
     */
    async markAsRead(notifId) {
        try {
            await fetch('/api/notifications.php?path=mark_read', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id=${encodeURIComponent(notifId)}`
            });
        } catch (error) {
            console.error('Failed to mark notification as read:', error);
        }
    }

    /**
     * Handle session expired
     */
    handleSessionExpired() {
        clearInterval(this.pingTimer);
        clearInterval(this.checkTimer);
        clearInterval(this.reminderTimer);

        alert('Your session has expired. Please log in again.');
        window.location.href = '/user-portal/login.php';
    }

    /**
     * Helper: Check if notification was already shown
     */
    hasShownNotification(notifId) {
        const shown = JSON.parse(localStorage.getItem('shownNotifications') || '[]');
        return shown.includes(notifId);
    }

    /**
     * Helper: Mark notification as shown
     */
    markAsShown(notifId) {
        const shown = JSON.parse(localStorage.getItem('shownNotifications') || '[]');
        shown.push(notifId);
        // Keep only last 100
        const trimmed = shown.slice(-100);
        localStorage.setItem('shownNotifications', JSON.stringify(trimmed));
    }

    /**
     * Helper: Get Bootstrap alert type
     */
    getBootstrapType(type) {
        const types = {
            'success': 'success',
            'error': 'danger',
            'warning': 'warning',
            'info': 'info'
        };
        return types[type] || 'info';
    }

    /**
     * Helper: Get icon for notification type
     */
    getIcon(type) {
        const icons = {
            'success': '✅',
            'error': '❌',
            'warning': '⚠️',
            'info': 'ℹ️'
        };
        return icons[type] || 'ℹ️';
    }

    /**
     * Cleanup on page unload
     */
    destroy() {
        clearInterval(this.pingTimer);
        clearInterval(this.checkTimer);
        clearInterval(this.reminderTimer);
    }
}

// Auto-initialize if on user or admin portal
if (window.location.pathname.includes('/user-portal/') ||
    window.location.pathname.includes('/admin/')) {

    document.addEventListener('DOMContentLoaded', () => {
        // Check if user is logged in (you can add more checks here)
        window.flexNotifications = new FlexPBXNotifications();

        // Cleanup on page unload
        window.addEventListener('beforeunload', () => {
            if (window.flexNotifications) {
                window.flexNotifications.destroy();
            }
        });
    });
}
