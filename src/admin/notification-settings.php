<?php
require_once __DIR__ . '/admin_auth_check.php';
/**
 * FlexPBX Admin Portal - Notification Settings
 * Manage push notifications and email preferences for admins
 */

session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /admin/login.php');
    exit;
}

$admin_username = $_SESSION['admin_username'] ?? '';
$linked_extension = $_SESSION['linked_extension'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notification Settings - FlexPBX Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container { max-width: 800px; margin: 0 auto; }
        .header {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        h1 { color: #333; margin-bottom: 10px; }
        .subtitle { color: #666; font-size: 14px; }
        .card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            margin-bottom: 20px;
        }
        .card h2 {
            color: #2c3e50;
            font-size: 1.2rem;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #eee;
        }
        .setting-group {
            padding: 1rem 0;
            border-bottom: 1px solid #eee;
        }
        .setting-group:last-child {
            border-bottom: none;
        }
        .setting-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        .setting-title {
            font-weight: 600;
            color: #2c3e50;
        }
        .setting-description {
            font-size: 0.9rem;
            color: #666;
            margin-top: 0.3rem;
        }
        .toggle-switch {
            position: relative;
            width: 60px;
            height: 30px;
        }
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 30px;
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        input:checked + .slider {
            background-color: #4ade80;
        }
        input:checked + .slider:before {
            transform: translateX(30px);
        }
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }
        .status-online { background-color: #4ade80; }
        .status-offline { background-color: #ef4444; }
        .status-box {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
        }
        .btn {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            padding: 0.8rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }
        .btn-secondary {
            background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
        }
        .alert {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
        }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .alert-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔔 Admin Notification Settings</h1>
            <p class="subtitle">Admin: <?= htmlspecialchars($admin_username) ?></p>
        </div>

        <?php if ($linked_extension): ?>
        <!-- SIP Status Card (only if extension linked) -->
        <div class="card">
            <h2>📱 SIP Registration Status</h2>
            <div class="status-box" id="sip-status-box" aria-live="polite" aria-atomic="true">
                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <span class="status-indicator status-offline" id="status-indicator" role="status"></span>
                        <span id="status-text">Checking...</span>
                    </div>
                    <button onclick="checkStatus()" class="btn" style="padding: 0.5rem 1rem; font-size: 0.9rem;" aria-label="Refresh SIP registration status">Refresh</button>
                </div>
                <div id="status-details" style="margin-top: 1rem; font-size: 0.9rem; color: #666; display: none;" aria-live="polite"></div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Push Notifications Card -->
        <div class="card">
            <h2>📲 Push Notifications</h2>

            <div id="push-not-supported" class="alert alert-warning" style="display: none;">
                ⚠️ Push notifications are not supported on this device/browser.
            </div>

            <div id="push-permission-denied" class="alert alert-warning" style="display: none;">
                ⚠️ Notification permission denied. Please enable notifications in your browser settings.
            </div>

            <div class="setting-group">
                <div class="setting-header">
                    <div>
                        <div class="setting-title">Enable Push Notifications</div>
                        <div class="setting-description">Receive notifications on this device even when browser is closed</div>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" id="push-enabled" onchange="togglePushNotifications(this.checked)">
                        <span class="slider"></span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Email Notifications Card -->
        <div class="card">
            <h2>📧 Email Notifications</h2>

            <div class="setting-group">
                <div class="setting-header">
                    <div>
                        <div class="setting-title">Enable Email Notifications</div>
                        <div class="setting-description">Receive notifications via email</div>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" id="email-enabled" onchange="updatePreference('email_notifications_enabled', this.checked)">
                        <span class="slider"></span>
                    </label>
                </div>
            </div>

            <div class="setting-group">
                <div class="setting-header">
                    <div>
                        <div class="setting-title">System Alerts</div>
                        <div class="setting-description">Get notified about system maintenance, updates, and critical alerts</div>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" id="notify-system-alerts" onchange="updatePreference('notify_system_alerts', this.checked)">
                        <span class="slider"></span>
                    </label>
                </div>
            </div>

            <?php if ($linked_extension): ?>
            <div class="setting-group">
                <div class="setting-header">
                    <div>
                        <div class="setting-title">Voicemail Notifications</div>
                        <div class="setting-description">Get notified about new voicemails on linked extension <?= htmlspecialchars($linked_extension) ?></div>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" id="notify-voicemail" onchange="updatePreference('notify_voicemail', this.checked)">
                        <span class="slider"></span>
                    </label>
                </div>
            </div>

            <div class="setting-group">
                <div class="setting-header">
                    <div>
                        <div class="setting-title">Missed Call Notifications</div>
                        <div class="setting-description">Get notified about missed calls on linked extension</div>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" id="notify-missed-calls" onchange="updatePreference('notify_missed_calls', this.checked)">
                        <span class="slider"></span>
                    </label>
                </div>
            </div>

            <div class="setting-group">
                <div class="setting-header">
                    <div>
                        <div class="setting-title">SIP Status Changes</div>
                        <div class="setting-description">Get notified when extension registers/unregisters</div>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" id="notify-sip-status" onchange="updatePreference('notify_sip_status', this.checked)">
                        <span class="slider"></span>
                    </label>
                </div>
            </div>
            <?php endif; ?>

            <div class="setting-group">
                <div class="setting-header">
                    <div>
                        <div class="setting-title">Login Notifications</div>
                        <div class="setting-description">Get notified when you login to the admin dashboard</div>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" id="notify-login" onchange="updatePreference('notify_login', this.checked)">
                        <span class="slider"></span>
                    </label>
                </div>
            </div>

            <div class="setting-group">
                <div class="setting-header">
                    <div>
                        <div class="setting-title">Logout Notifications</div>
                        <div class="setting-description">Get notified when you logout from the admin dashboard</div>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" id="notify-logout" onchange="updatePreference('notify_logout', this.checked)">
                        <span class="slider"></span>
                    </label>
                </div>
            </div>

            <?php if (!$linked_extension): ?>
            <div class="alert alert-info">
                ℹ️ <strong>Link an extension</strong> to receive voicemail and call notifications. <a href="link-extension.php" style="color: #0c5460; text-decoration: underline;">Link Extension</a>
            </div>
            <?php endif; ?>
        </div>

        <div style="text-align: center; margin-top: 2rem;">
            <a href="/admin/dashboard.html" class="btn btn-secondary">Back to Dashboard</a>
        </div>
    </div>

    <script>
        const adminUsername = '<?= addslashes($admin_username) ?>';
        const linkedExtension = <?= $linked_extension ? "'".addslashes($linked_extension)."'" : 'null' ?>;
        let statusInterval = null;

        // Detect Safari
        const isSafari = /^((?!chrome|android).)*safari/i.test(navigator.userAgent);

        // Check if push notifications are supported
        const pushSupported = ('serviceWorker' in navigator) && ('PushManager' in window) && ('Notification' in window);

        // Check if user is interacting with form elements
        function isUserInteracting() {
            const activeElement = document.activeElement;
            return activeElement && (
                activeElement.tagName === 'INPUT' ||
                activeElement.tagName === 'TEXTAREA' ||
                activeElement.tagName === 'SELECT' ||
                activeElement.isContentEditable
            );
        }

        // Start status polling with smart pausing
        function startStatusPolling() {
            if (!linkedExtension) return;

            statusInterval = setInterval(() => {
                // Only refresh if page is visible and user not interacting with forms
                if (!document.hidden && !isUserInteracting()) {
                    checkStatus();
                }
            }, 60000); // Increased to 60 seconds for accessibility
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', async () => {
            // Load preferences
            await loadPreferences();

            // Check SIP status if extension linked
            if (linkedExtension) {
                await checkStatus();
                startStatusPolling();

                // Pause/resume polling when page visibility changes
                document.addEventListener('visibilitychange', () => {
                    if (document.hidden && statusInterval) {
                        clearInterval(statusInterval);
                        statusInterval = null;
                    } else if (!document.hidden && !statusInterval) {
                        startStatusPolling();
                        checkStatus(); // Immediate check when page becomes visible
                    }
                });
            }

            // Check push notification support
            if (!pushSupported) {
                const warningDiv = document.getElementById('push-not-supported');
                warningDiv.innerHTML = '⚠️ Push notifications are not supported on this device/browser.';
                warningDiv.style.display = 'block';
                document.getElementById('push-enabled').disabled = true;
            }
        });

        // Load notification preferences
        async function loadPreferences() {
            try {
                const response = await fetch(`/api/notification-subscribe.php?action=get_preferences&identifier=${adminUsername}&account_type=admin`);
                const data = await response.json();

                if (data.success && data.preferences) {
                    document.getElementById('push-enabled').checked = data.preferences.push_notifications_enabled || false;
                    document.getElementById('email-enabled').checked = data.preferences.email_notifications_enabled !== false;
                    document.getElementById('notify-system-alerts').checked = data.preferences.notify_system_alerts !== false;
                    document.getElementById('notify-login').checked = data.preferences.notify_login || false;
                    document.getElementById('notify-logout').checked = data.preferences.notify_logout || false;

                    if (linkedExtension) {
                        document.getElementById('notify-voicemail').checked = data.preferences.notify_voicemail !== false;
                        document.getElementById('notify-missed-calls').checked = data.preferences.notify_missed_calls !== false;
                        document.getElementById('notify-sip-status').checked = data.preferences.notify_sip_status !== false;
                    }
                }
            } catch (error) {
                console.error('Failed to load preferences:', error);
            }
        }

        // Check SIP registration status
        async function checkStatus() {
            if (!linkedExtension) return;

            try {
                const response = await fetch(`/api/sip-status.php?extension=${linkedExtension}`);
                const data = await response.json();

                if (data.success) {
                    const indicator = document.getElementById('status-indicator');
                    const statusText = document.getElementById('status-text');
                    const details = document.getElementById('status-details');

                    if (data.registered) {
                        indicator.className = 'status-indicator status-online';
                        statusText.textContent = 'Online';

                        let detailsHtml = `<strong>Registered</strong> - ${data.device_count} device(s) connected<br>`;
                        detailsHtml += `Last checked: ${data.last_checked}`;

                        if (data.on_call) {
                            detailsHtml += `<br><strong>On Call:</strong> ${data.call_count} active call(s)`;
                        }

                        details.innerHTML = detailsHtml;
                        details.style.display = 'block';
                    } else {
                        indicator.className = 'status-indicator status-offline';
                        statusText.textContent = 'Offline';
                        details.innerHTML = 'No SIP clients registered';
                        details.style.display = 'block';
                    }
                }
            } catch (error) {
                console.error('Failed to check status:', error);
                document.getElementById('status-text').textContent = 'Error checking status';
            }
        }

        // Toggle push notifications
        async function togglePushNotifications(enabled) {
            if (!pushSupported) {
                return;
            }

            if (enabled) {
                // Request permission
                let permission;
                try {
                    permission = await Notification.requestPermission();
                } catch (error) {
                    console.error('Permission request failed:', error);
                    document.getElementById('push-enabled').checked = false;
                    alert('⚠️ Failed to request notification permission. Please check your browser settings and ensure notifications are allowed.');
                    return;
                }

                if (permission !== 'granted') {
                    document.getElementById('push-enabled').checked = false;
                    document.getElementById('push-permission-denied').style.display = 'block';
                    return;
                }

                document.getElementById('push-permission-denied').style.display = 'none';

                // Register service worker and subscribe
                try {
                    const registration = await navigator.serviceWorker.register('/service-worker.js');
                    // Wait for service worker to be ready/active
                    await navigator.serviceWorker.ready;
                    const subscription = await registration.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: urlBase64ToUint8Array('YOUR_VAPID_PUBLIC_KEY_HERE')
                    });

                    // Send subscription to server
                    const response = await fetch('/api/notification-subscribe.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            action: 'subscribe',
                            account_type: 'admin',
                            identifier: adminUsername,
                            subscription: subscription.toJSON()
                        })
                    });

                    const result = await response.json();
                    if (result.success) {
                        alert('✓ Push notifications enabled!');
                    } else {
                        throw new Error(result.error || 'Server error');
                    }
                } catch (error) {
                    console.error('Failed to enable push notifications:', error);
                    document.getElementById('push-enabled').checked = false;

                    let errorMsg = 'Failed to enable push notifications. ';
                    if (error.message && error.message.includes('VAPID')) {
                        errorMsg += 'Server configuration issue. Contact your administrator.';
                    } else {
                        errorMsg += error.message || 'Please try again or check your browser notification settings.';
                    }
                    alert(errorMsg);
                }
            } else {
                // Unsubscribe
                try {
                    const registration = await navigator.serviceWorker.ready;
                    const subscription = await registration.pushManager.getSubscription();

                    if (subscription) {
                        await subscription.unsubscribe();
                    }

                    await fetch('/api/notification-subscribe.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            action: 'unsubscribe',
                            account_type: 'admin',
                            identifier: adminUsername
                        })
                    });

                    alert('✓ Push notifications disabled');
                } catch (error) {
                    console.error('Failed to disable push notifications:', error);
                }
            }
        }

        // Update notification preference
        async function updatePreference(key, value) {
            try {
                const response = await fetch('/api/notification-subscribe.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'update_preferences',
                        account_type: 'admin',
                        identifier: adminUsername,
                        [key]: value
                    })
                });

                const data = await response.json();

                if (data.success) {
                    console.log('Preference updated:', key, value);
                } else {
                    console.error('Failed to update preference:', data.error);
                }
            } catch (error) {
                console.error('Failed to update preference:', error);
            }
        }

        // Convert VAPID key
        function urlBase64ToUint8Array(base64String) {
            const padding = '='.repeat((4 - base64String.length % 4) % 4);
            const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
            const rawData = window.atob(base64);
            return Uint8Array.from([...rawData].map(char => char.charCodeAt(0)));
        }

        // Cleanup on page unload
        window.addEventListener('beforeunload', () => {
            if (statusInterval) {
                clearInterval(statusInterval);
            }
        });
    </script>
</body>
</html>
