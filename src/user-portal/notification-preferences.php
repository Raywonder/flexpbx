<?php
/**
 * FlexPBX User Notification Preferences
 * Manage notification settings and delivery methods
 */

require_once __DIR__ . '/user_auth_check.php';
require_once __DIR__ . '/user_header.php';
?>

<style>
    .container {
        max-width: 900px;
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

    .preferences-card {
        background: white;
        border-radius: 12px;
        padding: 30px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        margin-bottom: 20px;
    }

    .preferences-card h2 {
        color: #333;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .section {
        margin-bottom: 30px;
    }

    .section-title {
        font-weight: 600;
        color: #333;
        margin-bottom: 15px;
        font-size: 16px;
    }

    .preference-item {
        padding: 15px;
        border: 2px solid #f0f0f0;
        border-radius: 8px;
        margin-bottom: 10px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: all 0.3s;
    }

    .preference-item:hover {
        border-color: #667eea;
        background: #f9fafb;
    }

    .preference-info {
        flex: 1;
    }

    .preference-label {
        font-weight: 600;
        color: #333;
        margin-bottom: 5px;
    }

    .preference-description {
        font-size: 13px;
        color: #666;
    }

    .toggle-switch {
        position: relative;
        width: 50px;
        height: 26px;
    }

    .toggle-switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .toggle-slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #ccc;
        transition: .4s;
        border-radius: 26px;
    }

    .toggle-slider:before {
        position: absolute;
        content: "";
        height: 20px;
        width: 20px;
        left: 3px;
        bottom: 3px;
        background-color: white;
        transition: .4s;
        border-radius: 50%;
    }

    input:checked + .toggle-slider {
        background-color: #667eea;
    }

    input:checked + .toggle-slider:before {
        transform: translateX(24px);
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

    input[type="time"], select {
        width: 100%;
        padding: 12px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-size: 14px;
        transition: border-color 0.3s;
    }

    input:focus, select:focus {
        outline: none;
        border-color: #667eea;
    }

    .quiet-hours-section {
        background: #f9fafb;
        padding: 20px;
        border-radius: 8px;
        margin-top: 15px;
        display: none;
    }

    .quiet-hours-section.active {
        display: block;
    }

    .time-inputs {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
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
        margin-left: 10px;
    }

    .btn-secondary:hover {
        background: #e0e0e0;
    }

    .actions {
        display: flex;
        justify-content: flex-end;
        margin-top: 30px;
        padding-top: 20px;
        border-top: 2px solid #f0f0f0;
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
        padding: 40px;
        color: #666;
    }

    .notification-type-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 10px;
    }

    .delivery-method-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 10px;
    }
</style>

<div class="container">
    <div class="header">
        <h1>⚙️ Notification Preferences</h1>
        <p class="subtitle">Customize how and when you receive notifications</p>
    </div>

    <div id="alert-container"></div>

    <div class="preferences-card">
        <h2>🔔 Notification Settings</h2>

        <form id="preferences-form">
            <!-- Notification Types -->
            <div class="section">
                <div class="section-title">Notification Types</div>
                <div class="notification-type-grid">
                    <div class="preference-item">
                        <div class="preference-info">
                            <div class="preference-label">System</div>
                            <div class="preference-description">System updates and maintenance</div>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="notif_type_system" checked>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="preference-item">
                        <div class="preference-info">
                            <div class="preference-label">Calls</div>
                            <div class="preference-description">Missed calls and call events</div>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="notif_type_call" checked>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="preference-item">
                        <div class="preference-info">
                            <div class="preference-label">Voicemail</div>
                            <div class="preference-description">New voicemail messages</div>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="notif_type_voicemail" checked>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="preference-item">
                        <div class="preference-info">
                            <div class="preference-label">SMS</div>
                            <div class="preference-description">SMS and text messages</div>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="notif_type_sms" checked>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="preference-item">
                        <div class="preference-info">
                            <div class="preference-label">Alerts</div>
                            <div class="preference-description">Important system alerts</div>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="notif_type_alert" checked>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="preference-item">
                        <div class="preference-info">
                            <div class="preference-label">Messages</div>
                            <div class="preference-description">Chat and instant messages</div>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="notif_type_message" checked>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="preference-item">
                        <div class="preference-info">
                            <div class="preference-label">Tasks</div>
                            <div class="preference-description">Task assignments and updates</div>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="notif_type_task" checked>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Delivery Methods -->
            <div class="section">
                <div class="section-title">Delivery Methods</div>
                <div class="delivery-method-grid">
                    <div class="preference-item">
                        <div class="preference-info">
                            <div class="preference-label">Web Notifications</div>
                            <div class="preference-description">In-app notifications</div>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="delivery_web" checked disabled>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="preference-item">
                        <div class="preference-info">
                            <div class="preference-label">Desktop Notifications</div>
                            <div class="preference-description">Browser notifications</div>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="desktop_enabled" id="desktop-enabled">
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="preference-item">
                        <div class="preference-info">
                            <div class="preference-label">Email Notifications</div>
                            <div class="preference-description">Send to your email</div>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="email_enabled" checked>
                            <span class="toggle-slider"></span>
                        </label>
                    </div>

                    <div class="preference-item">
                        <div class="preference-info">
                            <div class="preference-label">SMS Notifications</div>
                            <div class="preference-description">Send to your phone</div>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="sms_enabled">
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Additional Settings -->
            <div class="section">
                <div class="section-title">Additional Settings</div>

                <div class="preference-item">
                    <div class="preference-info">
                        <div class="preference-label">Sound Notifications</div>
                        <div class="preference-description">Play sound when new notification arrives</div>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" name="sound_enabled" checked>
                        <span class="toggle-slider"></span>
                    </label>
                </div>

                <div class="preference-item">
                    <div class="preference-info">
                        <div class="preference-label">Quiet Hours</div>
                        <div class="preference-description">Mute notifications during specific hours</div>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" name="quiet_hours_enabled" id="quiet-hours-toggle">
                        <span class="toggle-slider"></span>
                    </label>
                </div>

                <div class="quiet-hours-section" id="quiet-hours-section">
                    <div class="time-inputs">
                        <div class="form-group">
                            <label>Start Time</label>
                            <input type="time" name="quiet_hours_start" value="22:00">
                        </div>
                        <div class="form-group">
                            <label>End Time</label>
                            <input type="time" name="quiet_hours_end" value="08:00">
                        </div>
                    </div>
                </div>
            </div>

            <div class="actions">
                <button type="button" class="btn btn-secondary" onclick="loadPreferences()">Reset</button>
                <button type="submit" class="btn btn-primary">Save Preferences</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Handle quiet hours toggle
    document.getElementById('quiet-hours-toggle').addEventListener('change', function() {
        const section = document.getElementById('quiet-hours-section');
        if (this.checked) {
            section.classList.add('active');
        } else {
            section.classList.remove('active');
        }
    });

    // Handle desktop notifications permission
    document.getElementById('desktop-enabled').addEventListener('change', function() {
        if (this.checked && 'Notification' in window) {
            if (Notification.permission === 'default') {
                Notification.requestPermission().then(permission => {
                    if (permission !== 'granted') {
                        this.checked = false;
                        showAlert('Desktop notifications permission denied', 'error');
                    }
                });
            } else if (Notification.permission === 'denied') {
                this.checked = false;
                showAlert('Desktop notifications are blocked. Please enable them in your browser settings.', 'error');
            }
        }
    });

    // Load user preferences
    function loadPreferences() {
        fetch('/api/notifications-manager.php?action=get_preferences')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const prefs = data.preferences;

                    // Set notification types
                    if (prefs.notification_types) {
                        Object.keys(prefs.notification_types).forEach(type => {
                            const checkbox = document.querySelector(`input[name="notif_type_${type}"]`);
                            if (checkbox) {
                                checkbox.checked = prefs.notification_types[type];
                            }
                        });
                    }

                    // Set delivery methods
                    document.querySelector('input[name="email_enabled"]').checked = prefs.email_enabled || false;
                    document.querySelector('input[name="sms_enabled"]').checked = prefs.sms_enabled || false;
                    document.querySelector('input[name="desktop_enabled"]').checked = prefs.desktop_enabled || false;

                    // Set additional settings
                    document.querySelector('input[name="sound_enabled"]').checked = prefs.sound_enabled || true;

                    // Set quiet hours
                    const quietHoursToggle = document.querySelector('input[name="quiet_hours_enabled"]');
                    quietHoursToggle.checked = prefs.quiet_hours_enabled || false;

                    if (prefs.quiet_hours_enabled) {
                        document.getElementById('quiet-hours-section').classList.add('active');
                        if (prefs.quiet_hours_start) {
                            document.querySelector('input[name="quiet_hours_start"]').value = prefs.quiet_hours_start;
                        }
                        if (prefs.quiet_hours_end) {
                            document.querySelector('input[name="quiet_hours_end"]').value = prefs.quiet_hours_end;
                        }
                    }
                }
            })
            .catch(error => {
                console.error('Error loading preferences:', error);
                showAlert('Error loading preferences', 'error');
            });
    }

    // Save preferences
    document.getElementById('preferences-form').addEventListener('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);

        // Build notification types object
        const notificationTypes = {
            system: formData.get('notif_type_system') === 'on',
            call: formData.get('notif_type_call') === 'on',
            voicemail: formData.get('notif_type_voicemail') === 'on',
            sms: formData.get('notif_type_sms') === 'on',
            alert: formData.get('notif_type_alert') === 'on',
            message: formData.get('notif_type_message') === 'on',
            task: formData.get('notif_type_task') === 'on'
        };

        // Build delivery methods object
        const deliveryMethods = {
            web: true // Always enabled
        };

        // Build preferences object
        const preferences = {
            notification_types: notificationTypes,
            delivery_methods: deliveryMethods,
            email_enabled: formData.get('email_enabled') === 'on',
            sms_enabled: formData.get('sms_enabled') === 'on',
            desktop_enabled: formData.get('desktop_enabled') === 'on',
            sound_enabled: formData.get('sound_enabled') === 'on',
            quiet_hours_enabled: formData.get('quiet_hours_enabled') === 'on',
            quiet_hours_start: formData.get('quiet_hours_start') || null,
            quiet_hours_end: formData.get('quiet_hours_end') || null
        };

        showAlert('Saving preferences...', 'info');

        fetch('/api/notifications-manager.php?action=update_preferences', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(preferences)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('Preferences saved successfully!', 'success');
            } else {
                showAlert('Error: ' + data.error, 'error');
            }
        })
        .catch(error => {
            showAlert('Error saving preferences: ' + error.message, 'error');
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
    loadPreferences();
</script>

<?php require_once __DIR__ . '/user_footer.php'; ?>
