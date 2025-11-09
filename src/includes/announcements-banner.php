<?php
/**
 * FlexPBX Announcements Banner Component
 * Displays active announcements as banners or widgets
 *
 * @version 1.0.0
 * @date 2025-11-06
 */

// Check if user is authenticated
$is_authenticated = (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in']) ||
                     (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in']);

if (!$is_authenticated) {
    return;
}

$current_user = isset($_SESSION['admin_logged_in']) ? ($_SESSION['admin_username'] ?? null) : ($_SESSION['username'] ?? null);
$current_role = isset($_SESSION['admin_logged_in']) ? ($_SESSION['admin_role'] ?? 'admin') : 'user';
?>

<style>
    .announcements-banner-container {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        z-index: 9999;
        pointer-events: none;
    }

    .announcement-banner {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 15px 20px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 15px;
        pointer-events: auto;
        animation: slideDown 0.5s ease;
        margin-bottom: 2px;
    }

    @keyframes slideDown {
        from {
            transform: translateY(-100%);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    .announcement-banner.urgent {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        animation: pulse-urgent 2s infinite;
    }

    @keyframes pulse-urgent {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.9; }
    }

    .announcement-banner.high {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    }

    .announcement-banner.maintenance {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    }

    .announcement-banner.alert {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    }

    .announcement-banner.feature {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    }

    .announcement-banner-icon {
        font-size: 24px;
        flex-shrink: 0;
    }

    .announcement-banner-content {
        flex: 1;
        min-width: 0;
    }

    .announcement-banner-title {
        font-weight: 700;
        font-size: 16px;
        margin: 0 0 5px 0;
    }

    .announcement-banner-text {
        font-size: 14px;
        opacity: 0.95;
        line-height: 1.4;
        margin: 0;
    }

    .announcement-banner-actions {
        display: flex;
        gap: 10px;
        flex-shrink: 0;
    }

    .announcement-banner-btn {
        background: rgba(255, 255, 255, 0.2);
        border: 1px solid rgba(255, 255, 255, 0.3);
        color: white;
        padding: 6px 12px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 13px;
        font-weight: 600;
        transition: all 0.3s;
        white-space: nowrap;
    }

    .announcement-banner-btn:hover {
        background: rgba(255, 255, 255, 0.3);
        transform: translateY(-1px);
    }

    .announcement-banner-close {
        background: none;
        border: none;
        color: white;
        font-size: 24px;
        cursor: pointer;
        padding: 0;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
        transition: all 0.3s;
        flex-shrink: 0;
    }

    .announcement-banner-close:hover {
        background: rgba(255, 255, 255, 0.2);
    }

    /* Sidebar Widget */
    .announcements-widget {
        position: fixed;
        bottom: 20px;
        right: 20px;
        width: 380px;
        max-height: 500px;
        background: white;
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        overflow: hidden;
        z-index: 9998;
        display: none;
        flex-direction: column;
        animation: slideUp 0.5s ease;
    }

    @keyframes slideUp {
        from {
            transform: translateY(100%);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    .announcements-widget.visible {
        display: flex;
    }

    .announcements-widget-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 15px 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .announcements-widget-title {
        font-weight: 700;
        font-size: 16px;
        margin: 0;
    }

    .announcements-widget-close {
        background: none;
        border: none;
        color: white;
        font-size: 24px;
        cursor: pointer;
        padding: 0;
        width: 28px;
        height: 28px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 4px;
        transition: all 0.3s;
    }

    .announcements-widget-close:hover {
        background: rgba(255, 255, 255, 0.2);
    }

    .announcements-widget-body {
        overflow-y: auto;
        flex: 1;
        padding: 15px;
    }

    .announcement-card-widget {
        background: #f8f9fa;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 12px;
        border-left: 4px solid #667eea;
    }

    .announcement-card-widget.urgent {
        border-left-color: #ef4444;
        background: #fef2f2;
    }

    .announcement-card-widget.high {
        border-left-color: #f59e0b;
        background: #fffbeb;
    }

    .announcement-card-widget-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 10px;
    }

    .announcement-card-widget-title {
        font-weight: 700;
        font-size: 14px;
        color: #333;
        margin: 0;
        flex: 1;
    }

    .announcement-card-widget-badge {
        font-size: 10px;
        padding: 3px 8px;
        border-radius: 10px;
        font-weight: 600;
        text-transform: uppercase;
        margin-left: 8px;
        white-space: nowrap;
    }

    .badge-urgent { background: #ef4444; color: white; }
    .badge-high { background: #f59e0b; color: white; }
    .badge-normal { background: #3b82f6; color: white; }
    .badge-low { background: #6c757d; color: white; }

    .announcement-card-widget-content {
        font-size: 13px;
        color: #666;
        line-height: 1.5;
        margin-bottom: 10px;
    }

    .announcement-card-widget-actions {
        display: flex;
        gap: 8px;
        justify-content: flex-end;
    }

    .announcement-card-widget-btn {
        background: #667eea;
        color: white;
        border: none;
        padding: 6px 12px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 12px;
        font-weight: 600;
        transition: all 0.3s;
    }

    .announcement-card-widget-btn:hover {
        background: #5568d3;
        transform: translateY(-1px);
    }

    .announcement-card-widget-btn.secondary {
        background: #6c757d;
    }

    .announcement-card-widget-btn.secondary:hover {
        background: #5a6268;
    }

    /* Popup Modal */
    .announcement-popup-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.7);
        z-index: 10000;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 20px;
        animation: fadeIn 0.3s ease;
    }

    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    .announcement-popup-overlay.visible {
        display: flex;
    }

    .announcement-popup {
        background: white;
        border-radius: 12px;
        max-width: 600px;
        width: 100%;
        max-height: 80vh;
        overflow-y: auto;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        animation: popIn 0.3s ease;
    }

    @keyframes popIn {
        from {
            transform: scale(0.9);
            opacity: 0;
        }
        to {
            transform: scale(1);
            opacity: 1;
        }
    }

    .announcement-popup-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 20px;
        border-radius: 12px 12px 0 0;
    }

    .announcement-popup-header.urgent {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    }

    .announcement-popup-header.high {
        background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    }

    .announcement-popup-title {
        font-size: 20px;
        font-weight: 700;
        margin: 0 0 10px 0;
    }

    .announcement-popup-meta {
        font-size: 13px;
        opacity: 0.9;
    }

    .announcement-popup-body {
        padding: 25px;
        color: #333;
        line-height: 1.7;
    }

    .announcement-popup-footer {
        padding: 15px 25px;
        border-top: 1px solid #e0e0e0;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }

    .announcement-popup-btn {
        padding: 10px 20px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 600;
        font-size: 14px;
        transition: all 0.3s;
    }

    .announcement-popup-btn.primary {
        background: #667eea;
        color: white;
    }

    .announcement-popup-btn.primary:hover {
        background: #5568d3;
        transform: translateY(-2px);
    }

    .announcement-popup-btn.secondary {
        background: #e0e0e0;
        color: #333;
    }

    .announcement-popup-btn.secondary:hover {
        background: #d0d0d0;
    }

    @media (max-width: 768px) {
        .announcement-banner {
            flex-direction: column;
            align-items: stretch;
            padding: 12px 15px;
        }

        .announcement-banner-actions {
            margin-top: 10px;
        }

        .announcements-widget {
            width: calc(100% - 40px);
            right: 20px;
            left: 20px;
        }
    }
</style>

<div id="announcementsBannerContainer" class="announcements-banner-container"></div>
<div id="announcementsWidget" class="announcements-widget"></div>
<div id="announcementPopupOverlay" class="announcement-popup-overlay"></div>

<script>
(function() {
    const currentUser = <?php echo json_encode($current_user); ?>;
    const currentRole = <?php echo json_encode($current_role); ?>;
    let activeAnnouncements = [];
    let shownPopups = JSON.parse(localStorage.getItem('shownAnnouncementPopups') || '[]');

    // Load active announcements
    async function loadAnnouncements() {
        try {
            const response = await fetch('/api/announcements.php?action=active');
            const data = await response.json();

            if (data.success) {
                activeAnnouncements = data.announcements;
                displayAnnouncements();
            }
        } catch (error) {
            console.error('Error loading announcements:', error);
        }
    }

    // Display announcements
    function displayAnnouncements() {
        // Display banners
        const banners = activeAnnouncements.filter(ann => ann.show_banner && !ann.is_dismissed);
        displayBanners(banners);

        // Show popups
        const popups = activeAnnouncements.filter(ann =>
            ann.show_popup && !ann.is_viewed && !shownPopups.includes(ann.id)
        );
        if (popups.length > 0) {
            showPopup(popups[0]);
        }

        // Update widget
        const widgetAnnouncements = activeAnnouncements.filter(ann => !ann.show_banner && !ann.is_dismissed);
        if (widgetAnnouncements.length > 0) {
            updateWidget(widgetAnnouncements);
        }
    }

    // Display banners
    function displayBanners(announcements) {
        const container = document.getElementById('announcementsBannerContainer');
        container.innerHTML = '';

        announcements.forEach(ann => {
            const banner = createBanner(ann);
            container.appendChild(banner);
        });
    }

    // Create banner element
    function createBanner(announcement) {
        const banner = document.createElement('div');
        banner.className = `announcement-banner ${announcement.priority} ${announcement.announcement_type}`;
        banner.setAttribute('data-id', announcement.id);

        const icon = getIcon(announcement.announcement_type);

        banner.innerHTML = `
            <div class="announcement-banner-icon">${icon}</div>
            <div class="announcement-banner-content">
                <div class="announcement-banner-title">${escapeHtml(announcement.title)}</div>
                <div class="announcement-banner-text">${stripHtml(announcement.content).substring(0, 150)}...</div>
            </div>
            <div class="announcement-banner-actions">
                <button class="announcement-banner-btn" onclick="announcementActions.viewFull(${announcement.id})">
                    View Full
                </button>
            </div>
            ${announcement.is_dismissible ? `
                <button class="announcement-banner-close" onclick="announcementActions.dismiss(${announcement.id})">&times;</button>
            ` : ''}
        `;

        // Mark as viewed
        markViewed(announcement.id);

        return banner;
    }

    // Update sidebar widget
    function updateWidget(announcements) {
        const widget = document.getElementById('announcementsWidget');

        widget.innerHTML = `
            <div class="announcements-widget-header">
                <h3 class="announcements-widget-title">📢 Announcements (${announcements.length})</h3>
                <button class="announcements-widget-close" onclick="announcementActions.closeWidget()">&times;</button>
            </div>
            <div class="announcements-widget-body">
                ${announcements.map(ann => createWidgetCard(ann)).join('')}
            </div>
        `;
    }

    // Create widget card
    function createWidgetCard(announcement) {
        return `
            <div class="announcement-card-widget ${announcement.priority}">
                <div class="announcement-card-widget-header">
                    <h4 class="announcement-card-widget-title">${escapeHtml(announcement.title)}</h4>
                    <span class="announcement-card-widget-badge badge-${announcement.priority}">${announcement.priority}</span>
                </div>
                <div class="announcement-card-widget-content">
                    ${stripHtml(announcement.content).substring(0, 120)}...
                </div>
                <div class="announcement-card-widget-actions">
                    <button class="announcement-card-widget-btn" onclick="announcementActions.viewFull(${announcement.id})">
                        Read More
                    </button>
                    ${announcement.is_dismissible ? `
                        <button class="announcement-card-widget-btn secondary" onclick="announcementActions.dismiss(${announcement.id})">
                            Dismiss
                        </button>
                    ` : ''}
                </div>
            </div>
        `;
    }

    // Show popup
    function showPopup(announcement) {
        const overlay = document.getElementById('announcementPopupOverlay');

        overlay.innerHTML = `
            <div class="announcement-popup">
                <div class="announcement-popup-header ${announcement.priority}">
                    <h2 class="announcement-popup-title">${escapeHtml(announcement.title)}</h2>
                    <div class="announcement-popup-meta">
                        ${announcement.announcement_type} • ${announcement.priority} priority
                    </div>
                </div>
                <div class="announcement-popup-body">
                    ${announcement.content}
                </div>
                <div class="announcement-popup-footer">
                    ${announcement.is_dismissible ? `
                        <button class="announcement-popup-btn secondary" onclick="announcementActions.dismissPopup(${announcement.id})">
                            Don't Show Again
                        </button>
                    ` : ''}
                    <button class="announcement-popup-btn primary" onclick="announcementActions.closePopup(${announcement.id})">
                        Got It
                    </button>
                </div>
            </div>
        `;

        overlay.classList.add('visible');
        markViewed(announcement.id);
        shownPopups.push(announcement.id);
        localStorage.setItem('shownAnnouncementPopups', JSON.stringify(shownPopups));
    }

    // Mark as viewed
    async function markViewed(id) {
        try {
            await fetch('/api/announcements.php?action=viewed', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id })
            });
        } catch (error) {
            console.error('Error marking viewed:', error);
        }
    }

    // Dismiss announcement
    async function dismiss(id) {
        try {
            const response = await fetch('/api/announcements.php?action=dismiss', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id })
            });

            const data = await response.json();

            if (data.success) {
                // Remove from display
                const banner = document.querySelector(`.announcement-banner[data-id="${id}"]`);
                if (banner) {
                    banner.style.animation = 'slideUp 0.3s ease reverse';
                    setTimeout(() => banner.remove(), 300);
                }

                // Reload announcements
                loadAnnouncements();
            }
        } catch (error) {
            console.error('Error dismissing announcement:', error);
        }
    }

    // Global announcement actions
    window.announcementActions = {
        viewFull: function(id) {
            const ann = activeAnnouncements.find(a => a.id === id);
            if (ann) showPopup(ann);
        },

        dismiss: function(id) {
            dismiss(id);
        },

        dismissPopup: function(id) {
            dismiss(id);
            document.getElementById('announcementPopupOverlay').classList.remove('visible');
        },

        closePopup: function(id) {
            document.getElementById('announcementPopupOverlay').classList.remove('visible');
        },

        closeWidget: function() {
            document.getElementById('announcementsWidget').classList.remove('visible');
        },

        showWidget: function() {
            document.getElementById('announcementsWidget').classList.add('visible');
        }
    };

    // Utility functions
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function stripHtml(html) {
        const div = document.createElement('div');
        div.innerHTML = html;
        return div.textContent || div.innerText || '';
    }

    function getIcon(type) {
        const icons = {
            system: '⚙️',
            maintenance: '🔧',
            feature: '✨',
            alert: '⚠️',
            news: '📰'
        };
        return icons[type] || '📢';
    }

    // Load announcements on page load
    document.addEventListener('DOMContentLoaded', loadAnnouncements);

    // Refresh announcements every 5 minutes
    setInterval(loadAnnouncements, 5 * 60 * 1000);
})();
</script>
