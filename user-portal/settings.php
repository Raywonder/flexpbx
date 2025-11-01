<?php
/**
 * FlexPBX User Portal - Unified Settings Page
 * Consolidates all user management options into one interface
 */

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: /user-portal/login.php');
    exit;
}

$user_extension = $_SESSION['user_extension'] ?? null;
$user_username = $_SESSION['user_username'] ?? 'User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - FlexPBX User Portal</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f5f7fa;
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Header */
        .page-header {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .page-header h1 {
            color: #2c3e50;
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }

        .page-header .subtitle {
            color: #666;
            font-size: 0.95rem;
        }

        .user-badge {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-left: 0.5rem;
        }

        /* Navigation Buttons */
        .nav-buttons {
            display: flex;
            gap: 0.75rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .nav-button {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 1.2rem;
            border: none;
            border-radius: 8px;
            background: white;
            color: #2c3e50;
            text-decoration: none;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
        }

        .nav-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .nav-button.primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        /* Tabs */
        .tabs-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .tabs {
            display: flex;
            border-bottom: 2px solid #e2e8f0;
            overflow-x: auto;
            background: #f8f9fa;
        }

        .tab {
            padding: 1rem 1.5rem;
            border: none;
            background: transparent;
            color: #666;
            cursor: pointer;
            font-size: 0.95rem;
            font-weight: 600;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .tab:hover {
            background: #e9ecef;
            color: #2c3e50;
        }

        .tab.active {
            color: #667eea;
            border-bottom-color: #667eea;
            background: white;
        }

        .tab-content {
            display: none;
            padding: 2rem;
            animation: fadeIn 0.3s;
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Cards */
        .settings-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid #e2e8f0;
        }

        .settings-card h3 {
            color: #2c3e50;
            margin-bottom: 1rem;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .settings-card p {
            color: #666;
            margin-bottom: 1rem;
            line-height: 1.6;
        }

        /* Form Elements */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #2c3e50;
            font-weight: 600;
            font-size: 0.95rem;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            font-size: 0.95rem;
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 2px rgba(102, 126, 234, 0.2);
        }

        .form-group small {
            display: block;
            margin-top: 0.3rem;
            color: #666;
            font-size: 0.85rem;
        }

        /* Toggle Switch */
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
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
            transition: 0.3s;
            border-radius: 24px;
        }

        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: 0.3s;
            border-radius: 50%;
        }

        input:checked + .toggle-slider {
            background-color: #667eea;
        }

        input:checked + .toggle-slider:before {
            transform: translateX(26px);
        }

        /* Buttons */
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
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

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        /* Alert */
        .alert {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        /* Lists */
        .settings-list {
            list-style: none;
        }

        .settings-list li {
            padding: 1rem;
            background: white;
            border-radius: 6px;
            margin-bottom: 0.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .settings-list-label {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex: 1;
        }

        .settings-list-icon {
            font-size: 1.3rem;
        }

        .settings-list-text {
            flex: 1;
        }

        .settings-list-title {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.25rem;
        }

        .settings-list-desc {
            font-size: 0.85rem;
            color: #666;
        }

        /* Grid */
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .tabs {
                flex-direction: column;
            }

            .tab {
                border-bottom: 1px solid #e2e8f0;
                border-left: 3px solid transparent;
            }

            .tab.active {
                border-bottom-color: #e2e8f0;
                border-left-color: #667eea;
            }

            .settings-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <h1>⚙️ Settings</h1>
            <p class="subtitle">
                Extension <?= htmlspecialchars($user_extension) ?>
                <span class="user-badge"><?= htmlspecialchars($user_username) ?></span>
            </p>
        </div>

        <!-- Navigation Buttons (Top) -->
        <div class="nav-buttons">
            <a href="/user-portal/" class="nav-button">🏠 Dashboard</a>
            <a href="/user-portal/messages.php" class="nav-button">💬 Messages</a>
            <a href="/user-portal/voicemail.php" class="nav-button">📬 Voicemail</a>
            <a href="/user-portal/help.php" class="nav-button">❓ Help</a>
            <a href="/user-portal/login.php?logout=1" class="nav-button">🚪 Logout</a>
        </div>

        <!-- Tabs Container -->
        <div class="tabs-container">
            <!-- Tab Navigation -->
            <div class="tabs">
                <button class="tab active" data-tab="profile">👤 Profile</button>
                <button class="tab" data-tab="notifications">🔔 Notifications</button>
                <button class="tab" data-tab="devices">📱 Devices</button>
                <button class="tab" data-tab="voicemail">📬 Voicemail</button>
                <button class="tab" data-tab="forwarding">📞 Call Forwarding</button>
                <button class="tab" data-tab="security">🔒 Security</button>
                <button class="tab" data-tab="integrations">🔗 Integrations</button>
                <button class="tab" data-tab="accessibility">♿ Accessibility</button>
            </div>

            <!-- Profile Tab -->
            <div class="tab-content active" id="profile">
                <h2 style="margin-bottom: 1.5rem; color: #2c3e50;">Profile Settings</h2>

                <div class="settings-card">
                    <h3>👤 Personal Information</h3>
                    <p>Update your account information and contact details.</p>
                    <a href="/user-portal/profile-settings.php" class="btn btn-primary">Manage Profile</a>
                </div>

                <div class="settings-card">
                    <h3>📧 Email Address</h3>
                    <p>Update your email address for notifications and account recovery.</p>
                    <a href="/user-portal/setup-email.php" class="btn btn-primary">Update Email</a>
                </div>

                <div class="settings-card">
                    <h3>🔑 Change Password</h3>
                    <p>Update your account password to keep your extension secure.</p>
                    <a href="/user-portal/change-password.php" class="btn btn-primary">Change Password</a>
                </div>

                <div class="settings-card">
                    <h3>📞 My DID Number</h3>
                    <p>View and manage your Direct Inward Dial (DID) number settings.</p>
                    <a href="/user-portal/my-did.php" class="btn btn-primary">Manage DID</a>
                </div>
            </div>

            <!-- Notifications Tab -->
            <div class="tab-content" id="notifications">
                <h2 style="margin-bottom: 1.5rem; color: #2c3e50;">Notification Settings</h2>

                <div class="settings-card">
                    <h3>🔔 Push Notifications</h3>
                    <p>Configure browser and mobile push notifications for calls, messages, and voicemails.</p>
                    <a href="/user-portal/notification-settings.php" class="btn btn-primary">Manage Notifications</a>
                </div>

                <div class="settings-card">
                    <h3>📧 Email Notifications</h3>
                    <p>Choose which events trigger email notifications (voicemail, missed calls, etc.).</p>
                    <a href="/user-portal/email-notification-preferences.php" class="btn btn-primary">Email Preferences</a>
                </div>

                <div class="settings-card">
                    <h3>🎵 Sound Notifications</h3>
                    <p>Configure audio alerts for incoming calls, messages, and system events.</p>
                    <a href="/user-portal/notification-settings.php" class="btn btn-primary">Sound Settings</a>
                </div>
            </div>

            <!-- Devices Tab -->
            <div class="tab-content" id="devices">
                <h2 style="margin-bottom: 1.5rem; color: #2c3e50;">Device Management</h2>

                <div class="settings-card">
                    <h3>📱 Registered Devices</h3>
                    <p>View and manage devices connected to your extension (desk phones, softphones, mobile apps).</p>
                    <a href="/user-portal/manage-devices.php" class="btn btn-primary">Manage Devices</a>
                </div>

                <div class="settings-card">
                    <h3>🖥️ SIP Configuration</h3>
                    <p>View SIP server settings and configuration for manual device setup.</p>
                    <div style="margin-top: 1rem; padding: 1rem; background: white; border-radius: 6px;">
                        <p><strong>Server:</strong> flexpbx.devinecreations.net</p>
                        <p><strong>Port:</strong> 5060 (UDP)</p>
                        <p><strong>Extension:</strong> <?= htmlspecialchars($user_extension) ?></p>
                        <p><strong>Password:</strong> [Contact admin]</p>
                    </div>
                </div>

                <div class="settings-card">
                    <h3>📞 FlexPhone Web Client</h3>
                    <p>Make and receive calls directly from your browser without any software installation.</p>
                    <a href="/flexphone/" class="btn btn-primary">Launch FlexPhone</a>
                </div>
            </div>

            <!-- Voicemail Tab -->
            <div class="tab-content" id="voicemail">
                <h2 style="margin-bottom: 1.5rem; color: #2c3e50;">Voicemail Settings</h2>

                <div class="settings-card">
                    <h3>📬 Voicemail Configuration</h3>
                    <p>Configure voicemail PIN, greetings, and playback options.</p>
                    <a href="/user-portal/voicemail-settings.php" class="btn btn-primary">Manage Voicemail</a>
                </div>

                <div class="settings-card">
                    <h3>🎙️ Custom Greetings</h3>
                    <p>Upload and manage custom voicemail greetings and recordings.</p>
                    <a href="/user-portal/my-recordings.php" class="btn btn-primary">Manage Recordings</a>
                </div>

                <div class="settings-card">
                    <h3>📨 Voicemail Messages</h3>
                    <p>View, listen to, and manage your voicemail messages.</p>
                    <a href="/user-portal/voicemail.php" class="btn btn-primary">View Messages</a>
                </div>
            </div>

            <!-- Forwarding Tab -->
            <div class="tab-content" id="forwarding">
                <h2 style="margin-bottom: 1.5rem; color: #2c3e50;">Call Forwarding</h2>

                <div class="settings-card">
                    <h3>📞 Forwarded Numbers</h3>
                    <p>Forward calls to external numbers when you're unavailable or prefer to use a different device.</p>
                    <a href="/user-portal/forwarded-numbers.php" class="btn btn-primary">Manage Forwarding</a>
                </div>

                <div class="settings-card">
                    <h3>⏰ Call Routing Rules</h3>
                    <p>Set up time-based routing rules and conditional call forwarding.</p>
                    <button class="btn btn-primary" disabled>Coming Soon</button>
                </div>
            </div>

            <!-- Security Tab -->
            <div class="tab-content" id="security">
                <h2 style="margin-bottom: 1.5rem; color: #2c3e50;">Security & Privacy</h2>

                <div class="settings-card">
                    <h3>🔒 Password Security</h3>
                    <p>Change your password and enable additional security measures.</p>
                    <a href="/user-portal/change-password.php" class="btn btn-primary">Change Password</a>
                </div>

                <div class="settings-card">
                    <h3>🔐 Two-Factor Authentication</h3>
                    <p>Add an extra layer of security to your account with 2FA.</p>
                    <button class="btn btn-primary" disabled>Coming Soon</button>
                </div>

                <div class="settings-card">
                    <h3>📱 Active Sessions</h3>
                    <p>View and manage devices currently logged into your account.</p>
                    <a href="/user-portal/active-sessions.php" class="btn btn-primary">Manage Sessions</a>
                </div>

                <div class="settings-card">
                    <h3>🔓 Reset Password</h3>
                    <p>Lost your password? Request a password reset via email.</p>
                    <a href="/user-portal/forgot-password.php" class="btn btn-primary">Reset Password</a>
                </div>
            </div>

            <!-- Integrations Tab -->
            <div class="tab-content" id="integrations">
                <h2 style="margin-bottom: 1.5rem; color: #2c3e50;">Integrations</h2>

                <div class="settings-card">
                    <h3>🐘 Mastodon Integration</h3>
                    <p>Connect your Mastodon account to share call logs and receive notifications.</p>
                    <a href="/user-portal/mastodon-preferences.php" class="btn btn-primary">Manage Mastodon</a>
                </div>

                <div class="settings-card">
                    <h3>💬 SMS Messaging</h3>
                    <p>Send and receive SMS messages through your extension.</p>
                    <a href="/user-portal/sms-messaging.php" class="btn btn-primary">SMS Messages</a>
                </div>

                <div class="settings-card">
                    <h3>📇 Contact Manager</h3>
                    <p>Manage your contacts and phonebook entries.</p>
                    <a href="/user-portal/contact-manager.php" class="btn btn-primary">Manage Contacts</a>
                </div>

                <div class="settings-card">
                    <h3>☁️ Cloud Storage</h3>
                    <p>Connect cloud storage for voicemail and recording backups.</p>
                    <button class="btn btn-primary" disabled>Coming Soon</button>
                </div>
            </div>

            <!-- Accessibility Tab -->
            <div class="tab-content" id="accessibility">
                <h2 style="margin-bottom: 1.5rem; color: #2c3e50;">Accessibility</h2>

                <div class="settings-card">
                    <h3>♿ Accessibility Features</h3>
                    <p>Request special accessibility features such as screen reader optimizations, high contrast mode, or custom keyboard shortcuts.</p>
                    <a href="/user-portal/accessibility-request.php" class="btn btn-primary">Request Accessibility Features</a>
                </div>

                <div class="settings-card">
                    <h3>🎨 Display Preferences</h3>
                    <p>Adjust font sizes, contrast, and color schemes for better readability.</p>
                    <button class="btn btn-primary" disabled>Coming Soon</button>
                </div>

                <div class="settings-card">
                    <h3>⌨️ Keyboard Shortcuts</h3>
                    <p>View and customize keyboard shortcuts for faster navigation.</p>
                    <button class="btn btn-primary" disabled>Coming Soon</button>
                </div>
            </div>
        </div>

        <!-- Navigation Buttons (Bottom) -->
        <div class="nav-buttons" style="margin-top: 2rem;">
            <a href="/user-portal/" class="nav-button">🏠 Dashboard</a>
            <a href="/user-portal/messages.php" class="nav-button">💬 Messages</a>
            <a href="/user-portal/voicemail.php" class="nav-button">📬 Voicemail</a>
            <a href="/user-portal/help.php" class="nav-button">❓ Help</a>
            <a href="/user-portal/login.php?logout=1" class="nav-button">🚪 Logout</a>
        </div>
    </div>

    <script>
        // Tab switching functionality
        const tabs = document.querySelectorAll('.tab');
        const tabContents = document.querySelectorAll('.tab-content');

        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                const targetTab = tab.dataset.tab;

                // Remove active class from all tabs and contents
                tabs.forEach(t => t.classList.remove('active'));
                tabContents.forEach(tc => tc.classList.remove('active'));

                // Add active class to clicked tab and corresponding content
                tab.classList.add('active');
                document.getElementById(targetTab).classList.add('active');

                // Save to localStorage
                localStorage.setItem('lastSettingsTab', targetTab);
            });
        });

        // Restore last viewed tab
        window.addEventListener('DOMContentLoaded', () => {
            const lastTab = localStorage.getItem('lastSettingsTab');
            if (lastTab) {
                const tab = document.querySelector(`[data-tab="${lastTab}"]`);
                if (tab) {
                    tab.click();
                }
            }
        });
    </script>

    <!-- Accessibility Tools -->
    <script src="/js/accessibility.js"></script>
</body>
</html>
