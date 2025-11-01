<?php
/**
 * FlexPBX User Portal - Mastodon Notification Preferences
 * Configure Mastodon instance and notification settings
 */

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: /user-portal/login.php');
    exit;
}

$extension = $_SESSION['user_extension'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mastodon Preferences - FlexPBX</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #6364ff 0%, #563acc 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container { max-width: 900px; margin: 0 auto; }
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
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #6364ff;
        }
        .form-help {
            font-size: 0.85rem;
            color: #666;
            margin-top: 0.3rem;
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
            background-color: #6364ff;
        }
        input:checked + .slider:before {
            transform: translateX(30px);
        }
        .checkbox-group {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-top: 0.5rem;
        }
        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .checkbox-item input[type="checkbox"] {
            width: auto;
            cursor: pointer;
        }
        .checkbox-item label {
            font-weight: normal;
            cursor: pointer;
            margin: 0;
        }
        .btn {
            display: inline-block;
            background: linear-gradient(135deg, #6364ff 0%, #563acc 100%);
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
        .btn-success {
            background: linear-gradient(135deg, #4ade80 0%, #22c55e 100%);
        }
        .alert {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
            display: none;
        }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .button-group {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
        }
        .visibility-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 0.5rem;
        }
        .visibility-option {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 1rem;
            cursor: pointer;
            transition: all 0.3s;
        }
        .visibility-option:hover {
            border-color: #6364ff;
        }
        .visibility-option input[type="radio"] {
            margin-right: 0.5rem;
        }
        .visibility-option.selected {
            border-color: #6364ff;
            background: #f0f0ff;
        }
        .instance-type-selector {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .instance-type-option {
            flex: 1;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 1.5rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        .instance-type-option:hover {
            border-color: #6364ff;
        }
        .instance-type-option.selected {
            border-color: #6364ff;
            background: #f0f0ff;
        }
        .instance-type-option input[type="radio"] {
            display: none;
        }
        .instance-type-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        .third-party-config {
            display: none;
            margin-top: 1rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .mastodon-logo {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><span class="mastodon-logo">🐘</span> Mastodon Preferences</h1>
            <p class="subtitle">Extension <?= htmlspecialchars($extension) ?></p>
        </div>

        <!-- Alerts -->
        <div id="alert-success" class="alert alert-success"></div>
        <div id="alert-error" class="alert alert-error"></div>

        <!-- Main Settings Card -->
        <div class="card">
            <h2>Enable Mastodon Notifications</h2>
            <div class="setting-group">
                <div class="setting-header">
                    <div>
                        <div class="setting-title">Enable Mastodon Integration</div>
                        <div class="setting-description">Receive PBX notifications via Mastodon</div>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" id="mastodon-enabled" aria-label="Toggle Mastodon notifications">
                        <span class="slider"></span>
                    </label>
                </div>
            </div>
        </div>

        <!-- Instance Configuration -->
        <div class="card" id="instance-config" style="display: none;">
            <h2>Mastodon Instance Configuration</h2>

            <div class="instance-type-selector">
                <label class="instance-type-option" id="local-option">
                    <input type="radio" name="instance-type" value="local" checked>
                    <div class="instance-type-icon">🏠</div>
                    <div class="setting-title">Local Instance</div>
                    <div class="setting-description">md.tappedin.fm</div>
                </label>

                <label class="instance-type-option" id="third-party-option">
                    <input type="radio" name="instance-type" value="third-party">
                    <div class="instance-type-icon">🌐</div>
                    <div class="setting-title">Third-Party Instance</div>
                    <div class="setting-description">Use your own Mastodon server</div>
                </label>
            </div>

            <!-- Third-party configuration (hidden by default) -->
            <div id="third-party-config" class="third-party-config">
                <div class="form-group">
                    <label for="instance-url">Instance URL</label>
                    <input type="url" id="instance-url" placeholder="https://mastodon.social" aria-label="Mastodon instance URL">
                    <div class="form-help">Enter your Mastodon instance URL (e.g., mastodon.social, mas.to)</div>
                </div>

                <div class="form-group">
                    <label for="account-handle">Your Mastodon Handle</label>
                    <input type="text" id="account-handle" placeholder="@username@mastodon.social" aria-label="Mastodon account handle">
                    <div class="form-help">Your full Mastodon handle including instance (e.g., @user@mastodon.social)</div>
                </div>

                <div class="alert alert-info" style="display: block;">
                    <strong>Setup Required:</strong> To receive notifications on a third-party instance, you'll need to provide API credentials. Contact your FlexPBX administrator for setup instructions.
                </div>
            </div>

            <!-- Local instance handle -->
            <div id="local-config" style="margin-top: 1rem;">
                <div class="form-group">
                    <label for="local-account-handle">Your md.tappedin.fm Handle</label>
                    <input type="text" id="local-account-handle" placeholder="@username" aria-label="Local Mastodon handle">
                    <div class="form-help">Your username on md.tappedin.fm (e.g., @admin)</div>
                </div>
            </div>
        </div>

        <!-- Notification Types -->
        <div class="card" id="notification-types" style="display: none;">
            <h2>Notification Types</h2>
            <div class="setting-description" style="margin-bottom: 1rem;">Choose which PBX events trigger Mastodon notifications</div>

            <div class="setting-group">
                <div class="checkbox-item">
                    <input type="checkbox" id="notify-mentions" value="mentions" checked disabled>
                    <label for="notify-mentions">Mastodon Mentions (Always enabled)</label>
                </div>
            </div>

            <div class="setting-group">
                <div class="checkbox-item">
                    <input type="checkbox" id="notify-dms" value="dms">
                    <label for="notify-dms">Direct Messages</label>
                </div>
            </div>

            <div class="setting-group">
                <div class="checkbox-item">
                    <input type="checkbox" id="notify-calls" value="calls">
                    <label for="notify-calls">Incoming Calls</label>
                </div>
            </div>

            <div class="setting-group">
                <div class="checkbox-item">
                    <input type="checkbox" id="notify-voicemail" value="voicemail">
                    <label for="notify-voicemail">Voicemail Messages</label>
                </div>
            </div>

            <div class="setting-group">
                <div class="checkbox-item">
                    <input type="checkbox" id="notify-missed-calls" value="missed_call">
                    <label for="notify-missed-calls">Missed Calls</label>
                </div>
            </div>

            <div class="setting-group">
                <div class="checkbox-item">
                    <input type="checkbox" id="notify-system" value="system">
                    <label for="notify-system">System Alerts</label>
                </div>
            </div>
        </div>

        <!-- Post Visibility -->
        <div class="card" id="visibility-settings" style="display: none;">
            <h2>Post Visibility</h2>
            <div class="setting-description" style="margin-bottom: 1rem;">Control who can see your notification posts</div>

            <div class="visibility-options">
                <label class="visibility-option" data-value="public">
                    <input type="radio" name="visibility" value="public">
                    <div class="setting-title">🌍 Public</div>
                    <div class="setting-description">Visible to everyone</div>
                </label>

                <label class="visibility-option selected" data-value="unlisted">
                    <input type="radio" name="visibility" value="unlisted" checked>
                    <div class="setting-title">🔓 Unlisted</div>
                    <div class="setting-description">Not on public timelines</div>
                </label>

                <label class="visibility-option" data-value="private">
                    <input type="radio" name="visibility" value="private">
                    <div class="setting-title">🔒 Followers-only</div>
                    <div class="setting-description">Only your followers</div>
                </label>

                <label class="visibility-option" data-value="direct">
                    <input type="radio" name="visibility" value="direct">
                    <div class="setting-title">✉️ Direct</div>
                    <div class="setting-description">Only mentioned users</div>
                </label>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="button-group">
            <button class="btn btn-success" onclick="savePreferences()">Save Preferences</button>
            <a href="/user-portal/" class="btn btn-secondary">Back to Dashboard</a>
        </div>
    </div>

    <script>
        const extension = '<?= addslashes($extension) ?>';

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', () => {
            loadPreferences();
            setupEventListeners();
        });

        // Setup event listeners
        function setupEventListeners() {
            // Master toggle
            document.getElementById('mastodon-enabled').addEventListener('change', function() {
                toggleMastodonSettings(this.checked);
            });

            // Instance type selection
            document.querySelectorAll('input[name="instance-type"]').forEach(radio => {
                radio.addEventListener('change', function() {
                    updateInstanceTypeUI(this.value);
                });
            });

            // Visibility option selection
            document.querySelectorAll('.visibility-option').forEach(option => {
                option.addEventListener('click', function() {
                    const radio = this.querySelector('input[type="radio"]');
                    radio.checked = true;
                    document.querySelectorAll('.visibility-option').forEach(opt => opt.classList.remove('selected'));
                    this.classList.add('selected');
                });
            });

            // Instance type option selection
            document.querySelectorAll('.instance-type-option').forEach(option => {
                option.addEventListener('click', function() {
                    const radio = this.querySelector('input[type="radio"]');
                    radio.checked = true;
                    document.querySelectorAll('.instance-type-option').forEach(opt => opt.classList.remove('selected'));
                    this.classList.add('selected');
                    updateInstanceTypeUI(radio.value);
                });
            });
        }

        // Toggle visibility of Mastodon settings
        function toggleMastodonSettings(enabled) {
            const cards = ['instance-config', 'notification-types', 'visibility-settings'];
            cards.forEach(id => {
                document.getElementById(id).style.display = enabled ? 'block' : 'none';
            });
        }

        // Update UI based on instance type
        function updateInstanceTypeUI(type) {
            const thirdPartyConfig = document.getElementById('third-party-config');
            const localConfig = document.getElementById('local-config');

            if (type === 'third-party') {
                thirdPartyConfig.style.display = 'block';
                localConfig.style.display = 'none';
            } else {
                thirdPartyConfig.style.display = 'none';
                localConfig.style.display = 'block';
            }
        }

        // Load user preferences
        async function loadPreferences() {
            try {
                const response = await fetch(`/api/mastodon-preferences.php?extension=${extension}`);
                const data = await response.json();

                if (data.success && data.preferences) {
                    const prefs = data.preferences;
                    const mastodon = prefs.mastodon || {};

                    // Set master toggle
                    document.getElementById('mastodon-enabled').checked = mastodon.enabled || false;
                    toggleMastodonSettings(mastodon.enabled || false);

                    // Set instance type
                    const instanceType = mastodon.instance_type || 'local';
                    document.querySelector(`input[name="instance-type"][value="${instanceType}"]`).checked = true;
                    document.getElementById(`${instanceType}-option`).classList.add('selected');
                    updateInstanceTypeUI(instanceType);

                    // Set instance URL and handle
                    if (instanceType === 'third-party') {
                        document.getElementById('instance-url').value = mastodon.instance_url || '';
                        document.getElementById('account-handle').value = mastodon.account_handle || '';
                    } else {
                        document.getElementById('local-account-handle').value = mastodon.account_handle?.replace('@md.tappedin.fm', '') || '';
                    }

                    // Set notification types
                    const notifications = mastodon.notifications || ['mentions'];
                    document.querySelectorAll('input[type="checkbox"][id^="notify-"]').forEach(checkbox => {
                        if (checkbox.value && checkbox.value !== 'mentions') {
                            checkbox.checked = notifications.includes(checkbox.value);
                        }
                    });

                    // Set visibility
                    const visibility = mastodon.post_visibility || 'unlisted';
                    const visibilityRadio = document.querySelector(`input[name="visibility"][value="${visibility}"]`);
                    if (visibilityRadio) {
                        visibilityRadio.checked = true;
                        document.querySelectorAll('.visibility-option').forEach(opt => opt.classList.remove('selected'));
                        visibilityRadio.closest('.visibility-option').classList.add('selected');
                    }
                }
            } catch (error) {
                console.error('Failed to load preferences:', error);
                showAlert('error', 'Failed to load preferences. Please try again.');
            }
        }

        // Save preferences
        async function savePreferences() {
            try {
                const instanceType = document.querySelector('input[name="instance-type"]:checked').value;

                let instanceUrl = 'https://md.tappedin.fm';
                let accountHandle = '';

                if (instanceType === 'third-party') {
                    instanceUrl = document.getElementById('instance-url').value.trim();
                    accountHandle = document.getElementById('account-handle').value.trim();

                    if (!instanceUrl || !accountHandle) {
                        showAlert('error', 'Please provide both instance URL and account handle for third-party instances.');
                        return;
                    }
                } else {
                    const localHandle = document.getElementById('local-account-handle').value.trim();
                    accountHandle = localHandle.startsWith('@') ? localHandle : '@' + localHandle;
                    if (!accountHandle.includes('@md.tappedin.fm')) {
                        accountHandle += '@md.tappedin.fm';
                    }
                }

                // Collect notification types
                const notifications = ['mentions']; // Always include mentions
                document.querySelectorAll('input[type="checkbox"][id^="notify-"]:checked').forEach(checkbox => {
                    if (checkbox.value && checkbox.value !== 'mentions') {
                        notifications.push(checkbox.value);
                    }
                });

                const preferences = {
                    extension: extension,
                    mastodon: {
                        enabled: document.getElementById('mastodon-enabled').checked,
                        instance_type: instanceType,
                        instance_url: instanceUrl,
                        account_handle: accountHandle,
                        notifications: notifications,
                        post_visibility: document.querySelector('input[name="visibility"]:checked').value
                    }
                };

                const response = await fetch('/api/mastodon-preferences.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(preferences)
                });

                const data = await response.json();

                if (data.success) {
                    showAlert('success', 'Preferences saved successfully!');
                    setTimeout(() => {
                        window.location.href = '/user-portal/';
                    }, 2000);
                } else {
                    showAlert('error', data.message || 'Failed to save preferences.');
                }
            } catch (error) {
                console.error('Failed to save preferences:', error);
                showAlert('error', 'Failed to save preferences. Please try again.');
            }
        }

        // Show alert message
        function showAlert(type, message) {
            const alertId = type === 'success' ? 'alert-success' : 'alert-error';
            const alertEl = document.getElementById(alertId);
            alertEl.textContent = message;
            alertEl.style.display = 'block';

            setTimeout(() => {
                alertEl.style.display = 'none';
            }, 5000);
        }
    </script>
</body>
</html>
