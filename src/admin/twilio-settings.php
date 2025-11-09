<?php
require_once __DIR__ . '/admin_auth_check.php';
/**
 * FlexPBX Twilio Integration Settings
 * Complete Twilio configuration interface for FlexPBX
 */

session_start();

// Auth check
$is_admin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

if (!$is_admin) {
    header('Location: /admin/login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>External Integrations - FlexPBX Admin</title>
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
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            background: white;
            padding: 20px 30px;
            border-radius: 10px 10px 0 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            color: #333;
            font-size: 24px;
        }

        .header .logo {
            font-size: 28px;
            font-weight: bold;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .nav {
            background: white;
            padding: 15px 30px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            gap: 10px;
        }

        .nav-btn {
            padding: 10px 20px;
            background: #f5f5f5;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
        }

        .nav-btn:hover {
            background: #e0e0e0;
        }

        .nav-btn.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .content {
            background: white;
            padding: 30px;
            border-radius: 0 0 10px 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        .form-group small {
            display: block;
            margin-top: 5px;
            color: #666;
            font-size: 12px;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .btn-primary:hover {
            opacity: 0.9;
        }

        .btn-secondary {
            background: #f5f5f5;
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

        .btn-warning {
            background: #f59e0b;
            color: white;
        }

        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #6ee7b7;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #93c5fd;
        }

        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fcd34d;
        }

        .card {
            background: #f9fafb;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #e5e7eb;
        }

        .card h3 {
            margin-bottom: 15px;
            color: #333;
            font-size: 18px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .stat-card h4 {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
        }

        .stat-card .value {
            font-size: 24px;
            font-weight: bold;
            color: #333;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        table th,
        table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }

        table th {
            background: #f9fafb;
            font-weight: 600;
            color: #374151;
        }

        table tr:hover {
            background: #f9fafb;
        }

        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-error {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-info {
            background: #dbeafe;
            color: #1e40af;
        }

        .loading {
            display: none;
            text-align: center;
            padding: 20px;
            color: #666;
        }

        .loading.active {
            display: block;
        }

        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .back-link {
            display: inline-block;
            padding: 10px 20px;
            background: #f5f5f5;
            color: #333;
            text-decoration: none;
            border-radius: 5px;
            transition: all 0.3s;
        }

        .back-link:hover {
            background: #e0e0e0;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .checkbox-group input[type="checkbox"] {
            width: auto;
        }

        .hidden {
            display: none;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-header h3 {
            margin: 0;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <div class="logo">FlexPBX</div>
                <h1>External Integrations</h1>
                <p style="color: #666; margin-top: 5px;">Manage Twilio, Google Voice, and other third-party integrations</p>
            </div>
            <a href="/admin/dashboard.php" class="back-link">← Back to Dashboard</a>
        </div>

        <div class="nav">
            <button class="nav-btn active" onclick="switchTab('configuration')">Twilio Config</button>
            <button class="nav-btn" onclick="switchTab('numbers')">Phone Numbers</button>
            <button class="nav-btn" onclick="switchTab('calls')">Call Logs</button>
            <button class="nav-btn" onclick="switchTab('sms')">SMS Messages</button>
            <button class="nav-btn" onclick="switchTab('webhooks')">Webhooks</button>
            <button class="nav-btn" onclick="switchTab('account')">Account Info</button>
            <button class="nav-btn" onclick="switchTab('googlevoice')">Google Voice</button>
        </div>

        <div class="content">
            <!-- Configuration Tab -->
            <div id="configuration" class="tab-content active">
                <div id="config-alert"></div>

                <div class="card">
                    <h3>🔑 Twilio API Credentials</h3>
                    <form id="config-form">
                        <div class="form-group">
                            <label>Account SID *</label>
                            <input type="text" id="account_sid" name="account_sid" placeholder="ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" required>
                            <small>Your Twilio Account SID from the Twilio Console</small>
                        </div>

                        <div class="form-group">
                            <label>Auth Token *</label>
                            <input type="password" id="auth_token" name="auth_token" placeholder="Enter your Twilio Auth Token" required>
                            <small>Your Twilio Auth Token (keep this secure!)</small>
                        </div>

                        <div class="form-group">
                            <label>Twilio Phone Number</label>
                            <input type="tel" id="twilio_number" name="twilio_number" placeholder="+1234567890">
                            <small>Your default Twilio phone number in E.164 format</small>
                        </div>

                        <div class="form-group">
                            <label>Default TwiML URL</label>
                            <input type="url" id="default_twiml_url" name="default_twiml_url" placeholder="https://flexpbx.devinecreations.net/api/twilio-webhook.php?type=voice">
                            <small>URL for handling incoming calls</small>
                        </div>

                        <div class="form-group">
                            <label>Webhook URL</label>
                            <input type="url" id="webhook_url" name="webhook_url" placeholder="https://flexpbx.devinecreations.net/api/twilio-webhook.php">
                            <small>Base webhook URL for Twilio callbacks</small>
                        </div>

                        <div class="form-group">
                            <div class="checkbox-group">
                                <input type="checkbox" id="enabled" name="enabled" checked>
                                <label for="enabled" style="margin: 0;">Enable Twilio Integration</label>
                            </div>
                        </div>

                        <div class="button-group">
                            <button type="submit" class="btn btn-primary">💾 Save Configuration</button>
                            <button type="button" class="btn btn-success" onclick="testConnection()">🔌 Test Connection</button>
                            <button type="button" class="btn btn-secondary" onclick="loadConfig()">🔄 Reload</button>
                        </div>
                    </form>
                </div>

                <div class="card">
                    <h3>📊 Connection Status</h3>
                    <div id="connection-status">
                        <p style="color: #666;">Click "Test Connection" to verify your Twilio credentials</p>
                    </div>
                </div>
            </div>

            <!-- Phone Numbers Tab -->
            <div id="numbers" class="tab-content">
                <div id="numbers-alert"></div>

                <div class="card">
                    <h3>🔍 Search Available Numbers</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Area Code</label>
                            <input type="text" id="search_area_code" placeholder="212" maxlength="3">
                        </div>
                        <div class="form-group">
                            <label>Contains Pattern</label>
                            <input type="text" id="search_contains" placeholder="555">
                        </div>
                        <div class="form-group" style="display: flex; align-items: flex-end;">
                            <button class="btn btn-primary" onclick="searchNumbers()" style="width: 100%;">🔍 Search Numbers</button>
                        </div>
                    </div>
                    <div id="available-numbers"></div>
                </div>

                <div class="card">
                    <h3>📱 Your Phone Numbers</h3>
                    <button class="btn btn-secondary" onclick="loadPhoneNumbers()">🔄 Refresh</button>
                    <div id="owned-numbers"></div>
                </div>
            </div>

            <!-- Calls Tab -->
            <div id="calls" class="tab-content">
                <div id="calls-alert"></div>

                <div class="card">
                    <h3>📞 Make Test Call</h3>
                    <form id="make-call-form">
                        <div class="form-row">
                            <div class="form-group">
                                <label>To Number *</label>
                                <input type="tel" id="call_to" name="to" placeholder="+1234567890" required>
                            </div>
                            <div class="form-group">
                                <label>From Number</label>
                                <input type="tel" id="call_from" name="from" placeholder="Leave empty for default">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-success">📞 Make Call</button>
                    </form>
                </div>

                <div class="card">
                    <h3>📋 Recent Calls</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Filter by Status</label>
                            <select id="call_status_filter" onchange="loadCalls()">
                                <option value="">All</option>
                                <option value="queued">Queued</option>
                                <option value="ringing">Ringing</option>
                                <option value="in-progress">In Progress</option>
                                <option value="completed">Completed</option>
                                <option value="failed">Failed</option>
                            </select>
                        </div>
                        <div class="form-group" style="display: flex; align-items: flex-end;">
                            <button class="btn btn-secondary" onclick="loadCalls()" style="width: 100%;">🔄 Refresh</button>
                        </div>
                    </div>
                    <div id="calls-list"></div>
                </div>
            </div>

            <!-- SMS Tab -->
            <div id="sms" class="tab-content">
                <div id="sms-alert"></div>

                <div class="card">
                    <h3>✉️ Send Test SMS</h3>
                    <form id="send-sms-form">
                        <div class="form-row">
                            <div class="form-group">
                                <label>To Number *</label>
                                <input type="tel" id="sms_to" name="to" placeholder="+1234567890" required>
                            </div>
                            <div class="form-group">
                                <label>From Number</label>
                                <input type="tel" id="sms_from" name="from" placeholder="Leave empty for default">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Message *</label>
                            <textarea id="sms_body" name="body" rows="3" placeholder="Your message here..." required></textarea>
                        </div>
                        <button type="submit" class="btn btn-success">📤 Send SMS</button>
                    </form>
                </div>

                <div class="card">
                    <h3>💬 Recent Messages</h3>
                    <button class="btn btn-secondary" onclick="loadMessages()">🔄 Refresh</button>
                    <div id="messages-list"></div>
                </div>
            </div>

            <!-- Webhooks Tab -->
            <div id="webhooks" class="tab-content">
                <div class="card">
                    <h3>🔗 Webhook Configuration</h3>
                    <p style="margin-bottom: 20px; color: #666;">Configure these URLs in your Twilio Console for each phone number:</p>

                    <div class="form-group">
                        <label>Voice Webhook URL</label>
                        <input type="text" readonly value="https://flexpbx.devinecreations.net/api/twilio-webhook.php?type=voice">
                        <small>Use this URL for incoming voice calls</small>
                    </div>

                    <div class="form-group">
                        <label>SMS Webhook URL</label>
                        <input type="text" readonly value="https://flexpbx.devinecreations.net/api/twilio-webhook.php?type=sms">
                        <small>Use this URL for incoming SMS messages</small>
                    </div>

                    <div class="form-group">
                        <label>Status Callback URL</label>
                        <input type="text" readonly value="https://flexpbx.devinecreations.net/api/twilio-webhook.php?type=status">
                        <small>Use this URL for call status updates</small>
                    </div>

                    <div class="form-group">
                        <label>Recording Callback URL</label>
                        <input type="text" readonly value="https://flexpbx.devinecreations.net/api/twilio-webhook.php?type=recording">
                        <small>Use this URL for recording notifications</small>
                    </div>
                </div>

                <div class="card">
                    <h3>📜 Recent Webhook Logs</h3>
                    <button class="btn btn-secondary" onclick="loadWebhookLogs()">🔄 Refresh</button>
                    <div id="webhook-logs"></div>
                </div>
            </div>

            <!-- Account Info Tab -->
            <div id="account" class="tab-content">
                <div id="account-alert"></div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <h4>Account Status</h4>
                        <div class="value" id="account-status">-</div>
                    </div>
                    <div class="stat-card">
                        <h4>Account Balance</h4>
                        <div class="value" id="account-balance">$-</div>
                    </div>
                    <div class="stat-card">
                        <h4>Account Type</h4>
                        <div class="value" id="account-type">-</div>
                    </div>
                </div>

                <div class="card">
                    <h3>📊 Usage Statistics</h3>
                    <div class="form-group">
                        <label>Period</label>
                        <select id="usage-period" onchange="loadUsage()">
                            <option value="today">Today</option>
                            <option value="yesterday">Yesterday</option>
                            <option value="this-month">This Month</option>
                            <option value="last-month">Last Month</option>
                        </select>
                    </div>
                    <div id="usage-stats"></div>
                </div>

                <div class="card">
                    <h3>ℹ️ Account Details</h3>
                    <div id="account-details"></div>
                </div>
            </div>

            <!-- Google Voice Tab -->
            <div id="googlevoice" class="tab-content">
                <div id="gv-alert"></div>

                <div class="card">
                    <h3>☁️ Google Voice Configuration</h3>
                    <form id="gv-config-form">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Google Voice Number</label>
                                <input type="tel" id="gv_number" name="gv_number" placeholder="+12813015784">
                                <small>Your Google Voice phone number in E.164 format</small>
                            </div>
                            <div class="form-group">
                                <label>Display Name</label>
                                <input type="text" id="gv_display_name" name="display_name" placeholder="FlexPBX Main Line">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Default Extension</label>
                                <input type="text" id="gv_extension" name="default_extension" placeholder="2000">
                                <small>Extension to forward incoming calls to</small>
                            </div>
                            <div class="form-group">
                                <label>After Hours Destination</label>
                                <select id="gv_after_hours" name="after_hours_destination">
                                    <option value="voicemail">Voicemail</option>
                                    <option value="extension">Extension</option>
                                    <option value="hangup">Hangup</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Business Start Time</label>
                                <input type="time" id="gv_business_start" name="business_start" value="09:00">
                            </div>
                            <div class="form-group">
                                <label>Business End Time</label>
                                <input type="time" id="gv_business_end" name="business_end" value="18:00">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Greeting Message</label>
                            <textarea id="gv_greeting" name="greeting_message" rows="2" placeholder="Thank you for calling FlexPBX. Please leave a message after the tone."></textarea>
                        </div>

                        <div class="form-group">
                            <label>Auto-Reply SMS Message</label>
                            <textarea id="gv_auto_reply" name="auto_reply_message" rows="2" placeholder="Thank you for contacting FlexPBX! We'll respond during business hours."></textarea>
                        </div>

                        <h4 style="margin: 20px 0 15px 0; color: #333;">Features</h4>

                        <div class="form-row">
                            <div class="form-group">
                                <div class="checkbox-group">
                                    <input type="checkbox" id="gv_enabled" name="enabled" checked>
                                    <label for="gv_enabled" style="margin: 0;">Enable Google Voice</label>
                                </div>
                            </div>
                            <div class="form-group">
                                <div class="checkbox-group">
                                    <input type="checkbox" id="gv_sms" name="enable_sms" checked>
                                    <label for="gv_sms" style="margin: 0;">Enable SMS</label>
                                </div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <div class="checkbox-group">
                                    <input type="checkbox" id="gv_voicemail" name="enable_voicemail" checked>
                                    <label for="gv_voicemail" style="margin: 0;">Enable Voicemail</label>
                                </div>
                            </div>
                            <div class="form-group">
                                <div class="checkbox-group">
                                    <input type="checkbox" id="gv_transcribe" name="auto_transcribe" checked>
                                    <label for="gv_transcribe" style="margin: 0;">Auto Transcribe</label>
                                </div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <div class="checkbox-group">
                                    <input type="checkbox" id="gv_recording" name="enable_call_recording">
                                    <label for="gv_recording" style="margin: 0;">Enable Call Recording</label>
                                </div>
                            </div>
                            <div class="form-group">
                                <div class="checkbox-group">
                                    <input type="checkbox" id="gv_screening" name="enable_call_screening">
                                    <label for="gv_screening" style="margin: 0;">Enable Call Screening</label>
                                </div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <div class="checkbox-group">
                                    <input type="checkbox" id="gv_email_notify" name="email_notifications" checked>
                                    <label for="gv_email_notify" style="margin: 0;">Email Notifications</label>
                                </div>
                            </div>
                            <div class="form-group">
                                <div class="checkbox-group">
                                    <input type="checkbox" id="gv_auto_reply_enabled" name="auto_reply_enabled">
                                    <label for="gv_auto_reply_enabled" style="margin: 0;">Auto-Reply to SMS</label>
                                </div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <div class="checkbox-group">
                                    <input type="checkbox" id="gv_business_hours" name="business_hours_only" checked>
                                    <label for="gv_business_hours" style="margin: 0;">Business Hours Only</label>
                                </div>
                            </div>
                        </div>

                        <h4 style="margin: 20px 0 15px 0; color: #333;">Rate Limits</h4>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Daily Call Limit</label>
                                <input type="number" id="gv_call_limit" name="call_limit_daily" value="1000">
                            </div>
                            <div class="form-group">
                                <label>Daily SMS Limit</label>
                                <input type="number" id="gv_sms_limit" name="sms_limit_daily" value="500">
                            </div>
                        </div>

                        <div class="button-group">
                            <button type="submit" class="btn btn-primary">💾 Save Configuration</button>
                            <button type="button" class="btn btn-success" onclick="testGoogleVoice()">🔌 Test Connection</button>
                            <button type="button" class="btn btn-secondary" onclick="loadGoogleVoiceConfig()">🔄 Reload</button>
                        </div>
                    </form>
                </div>

                <div class="card">
                    <h3>🔐 OAuth2 Setup</h3>
                    <p style="margin-bottom: 15px; color: #666;">
                        Google Voice requires OAuth2 authentication. Follow these steps to set up:
                    </p>
                    <ol style="margin-left: 20px; color: #666; line-height: 1.8;">
                        <li>Go to <a href="https://console.cloud.google.com" target="_blank" style="color: #667eea;">Google Cloud Console</a></li>
                        <li>Create a new project or select an existing one</li>
                        <li>Enable the Google Voice API</li>
                        <li>Create OAuth 2.0 credentials (Web application)</li>
                        <li>Add authorized redirect URI: <code style="background: #f5f5f5; padding: 2px 6px; border-radius: 3px;">https://flexpbx.devinecreations.net/api/oauth/google-callback</code></li>
                        <li>Save your Client ID and Client Secret below</li>
                    </ol>

                    <form id="gv-oauth-form" style="margin-top: 20px;">
                        <div class="form-group">
                            <label>Client ID</label>
                            <input type="text" id="gv_client_id" name="client_id" placeholder="Your Google OAuth Client ID">
                        </div>

                        <div class="form-group">
                            <label>Client Secret</label>
                            <input type="password" id="gv_client_secret" name="client_secret" placeholder="Your Google OAuth Client Secret">
                        </div>

                        <div class="button-group">
                            <button type="submit" class="btn btn-primary">💾 Save OAuth Credentials</button>
                            <button type="button" class="btn btn-success" onclick="authorizeGoogleVoice()">🔐 Authorize Access</button>
                        </div>
                    </form>
                </div>

                <div class="card">
                    <h3>📊 Google Voice Status</h3>
                    <div id="gv-status">
                        <p style="color: #666;">Configuration not loaded yet. Click "Reload" or save your settings.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Number Details Modal -->
    <div id="number-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Phone Number Configuration</h3>
                <button class="close-modal" onclick="closeModal('number-modal')">&times;</button>
            </div>
            <div id="number-modal-content"></div>
        </div>
    </div>

    <script>
        let currentTab = 'configuration';
        let twilioConfig = {};

        // Tab Switching
        function switchTab(tabName) {
            currentTab = tabName;

            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });

            document.querySelectorAll('.nav-btn').forEach(btn => {
                btn.classList.remove('active');
            });

            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');

            // Load data for specific tabs
            if (tabName === 'numbers') {
                loadPhoneNumbers();
            } else if (tabName === 'calls') {
                loadCalls();
            } else if (tabName === 'sms') {
                loadMessages();
            } else if (tabName === 'account') {
                loadAccountInfo();
            } else if (tabName === 'googlevoice') {
                loadGoogleVoiceConfig();
            }
        }

        // Configuration Functions
        async function loadConfig() {
            try {
                const response = await fetch('/api/twilio.php?action=get_config');
                const data = await response.json();

                if (data.success) {
                    twilioConfig = data.config;
                    document.getElementById('account_sid').value = data.config.account_sid || '';
                    document.getElementById('auth_token').value = data.config.auth_token || '';
                    document.getElementById('twilio_number').value = data.config.twilio_number || '';
                    document.getElementById('default_twiml_url').value = data.config.default_twiml_url || '';
                    document.getElementById('webhook_url').value = data.config.webhook_url || '';
                    document.getElementById('enabled').checked = data.config.enabled || false;
                } else {
                    showAlert('config-alert', 'No configuration found. Please enter your Twilio credentials.', 'info');
                }
            } catch (error) {
                showAlert('config-alert', 'Error loading configuration: ' + error.message, 'error');
            }
        }

        document.getElementById('config-form').addEventListener('submit', async (e) => {
            e.preventDefault();

            const formData = new FormData(e.target);
            formData.append('action', 'save_config');
            formData.append('enabled', document.getElementById('enabled').checked);

            try {
                const response = await fetch('/api/twilio.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    showAlert('config-alert', data.message, 'success');
                    loadConfig();
                } else {
                    showAlert('config-alert', data.message, 'error');
                }
            } catch (error) {
                showAlert('config-alert', 'Error saving configuration: ' + error.message, 'error');
            }
        });

        async function testConnection() {
            const statusDiv = document.getElementById('connection-status');
            statusDiv.innerHTML = '<p style="color: #666;">Testing connection...</p>';

            try {
                const response = await fetch('/api/twilio.php?action=test_connection');
                const data = await response.json();

                if (data.success) {
                    statusDiv.innerHTML = `
                        <div class="alert alert-success">
                            ✅ Connection successful!<br>
                            <strong>Account:</strong> ${data.account}<br>
                            <strong>Status:</strong> ${data.status}
                        </div>
                    `;
                } else {
                    statusDiv.innerHTML = `
                        <div class="alert alert-error">
                            ❌ Connection failed: ${data.error}
                        </div>
                    `;
                }
            } catch (error) {
                statusDiv.innerHTML = `
                    <div class="alert alert-error">
                        ❌ Error testing connection: ${error.message}
                    </div>
                `;
            }
        }

        // Phone Numbers Functions
        async function searchNumbers() {
            const areaCode = document.getElementById('search_area_code').value;
            const contains = document.getElementById('search_contains').value;
            const resultsDiv = document.getElementById('available-numbers');

            resultsDiv.innerHTML = '<div class="loading active">Searching for available numbers...</div>';

            try {
                let url = '/api/twilio.php?action=search_numbers';
                if (areaCode) url += `&area_code=${areaCode}`;
                if (contains) url += `&contains=${contains}`;

                const response = await fetch(url);
                const data = await response.json();

                if (data.success && data.available_numbers.length > 0) {
                    let html = '<table><thead><tr><th>Phone Number</th><th>Locality</th><th>Region</th><th>Actions</th></tr></thead><tbody>';

                    data.available_numbers.forEach(number => {
                        html += `
                            <tr>
                                <td><strong>${number.phone_number}</strong></td>
                                <td>${number.locality || '-'}</td>
                                <td>${number.region || '-'}</td>
                                <td>
                                    <button class="btn btn-success" onclick="purchaseNumber('${number.phone_number}')">
                                        💳 Purchase
                                    </button>
                                </td>
                            </tr>
                        `;
                    });

                    html += '</tbody></table>';
                    resultsDiv.innerHTML = html;
                } else {
                    resultsDiv.innerHTML = '<p style="color: #666; padding: 20px;">No numbers found matching your search criteria.</p>';
                }
            } catch (error) {
                resultsDiv.innerHTML = `<div class="alert alert-error">Error searching numbers: ${error.message}</div>`;
            }
        }

        async function purchaseNumber(phoneNumber) {
            if (!confirm(`Purchase ${phoneNumber}?\n\nThis will charge your Twilio account.`)) {
                return;
            }

            try {
                const formData = new FormData();
                formData.append('action', 'purchase_number');
                formData.append('phone_number', phoneNumber);
                formData.append('voice_url', document.getElementById('default_twiml_url').value);
                formData.append('sms_url', document.getElementById('webhook_url').value + '?type=sms');

                const response = await fetch('/api/twilio.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    showAlert('numbers-alert', `Successfully purchased ${phoneNumber}!`, 'success');
                    loadPhoneNumbers();
                    searchNumbers(); // Refresh search results
                } else {
                    showAlert('numbers-alert', data.message || 'Failed to purchase number', 'error');
                }
            } catch (error) {
                showAlert('numbers-alert', 'Error purchasing number: ' + error.message, 'error');
            }
        }

        async function loadPhoneNumbers() {
            const numbersDiv = document.getElementById('owned-numbers');
            numbersDiv.innerHTML = '<div class="loading active">Loading phone numbers...</div>';

            try {
                const response = await fetch('/api/twilio.php?action=list_numbers');
                const data = await response.json();

                if (data.success && data.numbers.length > 0) {
                    let html = '<table><thead><tr><th>Phone Number</th><th>Friendly Name</th><th>Capabilities</th><th>Actions</th></tr></thead><tbody>';

                    data.numbers.forEach(number => {
                        const capabilities = [];
                        if (number.capabilities.voice) capabilities.push('📞 Voice');
                        if (number.capabilities.SMS) capabilities.push('💬 SMS');
                        if (number.capabilities.MMS) capabilities.push('📷 MMS');

                        html += `
                            <tr>
                                <td><strong>${number.phone_number}</strong></td>
                                <td>${number.friendly_name || '-'}</td>
                                <td>${capabilities.join(' ')}</td>
                                <td>
                                    <button class="btn btn-secondary" onclick="configureNumber('${number.sid}', '${number.phone_number}')">
                                        ⚙️ Configure
                                    </button>
                                    <button class="btn btn-danger" onclick="releaseNumber('${number.sid}', '${number.phone_number}')">
                                        🗑️ Release
                                    </button>
                                </td>
                            </tr>
                        `;
                    });

                    html += '</tbody></table>';
                    numbersDiv.innerHTML = html;
                } else {
                    numbersDiv.innerHTML = '<p style="color: #666; padding: 20px;">No phone numbers found. Search and purchase a number above.</p>';
                }
            } catch (error) {
                numbersDiv.innerHTML = `<div class="alert alert-error">Error loading phone numbers: ${error.message}</div>`;
            }
        }

        async function releaseNumber(sid, phoneNumber) {
            if (!confirm(`Release ${phoneNumber}?\n\nThis number will be permanently removed from your account.`)) {
                return;
            }

            try {
                const formData = new FormData();
                formData.append('action', 'release_number');
                formData.append('number_sid', sid);

                const response = await fetch('/api/twilio.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    showAlert('numbers-alert', `Successfully released ${phoneNumber}`, 'success');
                    loadPhoneNumbers();
                } else {
                    showAlert('numbers-alert', data.message || 'Failed to release number', 'error');
                }
            } catch (error) {
                showAlert('numbers-alert', 'Error releasing number: ' + error.message, 'error');
            }
        }

        function configureNumber(sid, phoneNumber) {
            const modal = document.getElementById('number-modal');
            const content = document.getElementById('number-modal-content');

            content.innerHTML = `
                <form id="update-number-form">
                    <input type="hidden" name="number_sid" value="${sid}">

                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="text" value="${phoneNumber}" readonly>
                    </div>

                    <div class="form-group">
                        <label>Friendly Name</label>
                        <input type="text" name="friendly_name" placeholder="My Office Line">
                    </div>

                    <div class="form-group">
                        <label>Voice URL</label>
                        <input type="url" name="voice_url" placeholder="https://your-domain.com/voice">
                    </div>

                    <div class="form-group">
                        <label>SMS URL</label>
                        <input type="url" name="sms_url" placeholder="https://your-domain.com/sms">
                    </div>

                    <div class="button-group">
                        <button type="submit" class="btn btn-primary">💾 Update</button>
                        <button type="button" class="btn btn-secondary" onclick="closeModal('number-modal')">Cancel</button>
                    </div>
                </form>
            `;

            document.getElementById('update-number-form').addEventListener('submit', async (e) => {
                e.preventDefault();

                const formData = new FormData(e.target);
                formData.append('action', 'update_number');

                try {
                    const response = await fetch('/api/twilio.php', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();

                    if (data.success) {
                        showAlert('numbers-alert', 'Phone number updated successfully!', 'success');
                        closeModal('number-modal');
                        loadPhoneNumbers();
                    } else {
                        alert('Error: ' + (data.message || 'Failed to update number'));
                    }
                } catch (error) {
                    alert('Error updating number: ' + error.message);
                }
            });

            modal.classList.add('active');
        }

        // Calls Functions
        document.getElementById('make-call-form').addEventListener('submit', async (e) => {
            e.preventDefault();

            const formData = new FormData(e.target);
            formData.append('action', 'make_call');

            try {
                const response = await fetch('/api/twilio.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    showAlert('calls-alert', `Call initiated! SID: ${data.call.sid}`, 'success');
                    loadCalls();
                } else {
                    showAlert('calls-alert', data.message || 'Failed to make call', 'error');
                }
            } catch (error) {
                showAlert('calls-alert', 'Error making call: ' + error.message, 'error');
            }
        });

        async function loadCalls() {
            const callsDiv = document.getElementById('calls-list');
            const status = document.getElementById('call_status_filter').value;

            callsDiv.innerHTML = '<div class="loading active">Loading calls...</div>';

            try {
                let url = '/api/twilio.php?action=list_calls&limit=20';
                if (status) url += `&status=${status}`;

                const response = await fetch(url);
                const data = await response.json();

                if (data.success && data.calls.length > 0) {
                    let html = '<table><thead><tr><th>From</th><th>To</th><th>Status</th><th>Duration</th><th>Date</th></tr></thead><tbody>';

                    data.calls.forEach(call => {
                        const statusClass = call.status === 'completed' ? 'success' :
                                          call.status === 'failed' ? 'error' : 'info';

                        html += `
                            <tr>
                                <td>${call.from}</td>
                                <td>${call.to}</td>
                                <td><span class="badge badge-${statusClass}">${call.status}</span></td>
                                <td>${call.duration || 0}s</td>
                                <td>${new Date(call.date_created).toLocaleString()}</td>
                            </tr>
                        `;
                    });

                    html += '</tbody></table>';
                    callsDiv.innerHTML = html;
                } else {
                    callsDiv.innerHTML = '<p style="color: #666; padding: 20px;">No calls found.</p>';
                }
            } catch (error) {
                callsDiv.innerHTML = `<div class="alert alert-error">Error loading calls: ${error.message}</div>`;
            }
        }

        // SMS Functions
        document.getElementById('send-sms-form').addEventListener('submit', async (e) => {
            e.preventDefault();

            const formData = new FormData(e.target);
            formData.append('action', 'send_sms');

            try {
                const response = await fetch('/api/twilio.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    showAlert('sms-alert', `SMS sent! SID: ${data.message.sid}`, 'success');
                    e.target.reset();
                    loadMessages();
                } else {
                    showAlert('sms-alert', data.message || 'Failed to send SMS', 'error');
                }
            } catch (error) {
                showAlert('sms-alert', 'Error sending SMS: ' + error.message, 'error');
            }
        });

        async function loadMessages() {
            const messagesDiv = document.getElementById('messages-list');
            messagesDiv.innerHTML = '<div class="loading active">Loading messages...</div>';

            try {
                const response = await fetch('/api/twilio.php?action=list_messages&limit=20');
                const data = await response.json();

                if (data.success && data.messages.length > 0) {
                    let html = '<table><thead><tr><th>From</th><th>To</th><th>Body</th><th>Status</th><th>Date</th></tr></thead><tbody>';

                    data.messages.forEach(msg => {
                        const statusClass = msg.status === 'delivered' ? 'success' :
                                          msg.status === 'failed' ? 'error' : 'info';

                        html += `
                            <tr>
                                <td>${msg.from}</td>
                                <td>${msg.to}</td>
                                <td>${msg.body.substring(0, 50)}${msg.body.length > 50 ? '...' : ''}</td>
                                <td><span class="badge badge-${statusClass}">${msg.status}</span></td>
                                <td>${new Date(msg.date_created).toLocaleString()}</td>
                            </tr>
                        `;
                    });

                    html += '</tbody></table>';
                    messagesDiv.innerHTML = html;
                } else {
                    messagesDiv.innerHTML = '<p style="color: #666; padding: 20px;">No messages found.</p>';
                }
            } catch (error) {
                messagesDiv.innerHTML = `<div class="alert alert-error">Error loading messages: ${error.message}</div>`;
            }
        }

        // Account Functions
        async function loadAccountInfo() {
            try {
                const response = await fetch('/api/twilio.php?action=get_account');
                const data = await response.json();

                if (data.success) {
                    const account = data.account;
                    document.getElementById('account-status').textContent = account.status || '-';
                    document.getElementById('account-type').textContent = account.type || '-';

                    let html = '<table>';
                    html += `<tr><td><strong>Account SID:</strong></td><td>${account.sid}</td></tr>`;
                    html += `<tr><td><strong>Friendly Name:</strong></td><td>${account.friendly_name || '-'}</td></tr>`;
                    html += `<tr><td><strong>Status:</strong></td><td>${account.status}</td></tr>`;
                    html += `<tr><td><strong>Type:</strong></td><td>${account.type}</td></tr>`;
                    html += `<tr><td><strong>Created:</strong></td><td>${new Date(account.date_created).toLocaleString()}</td></tr>`;
                    html += '</table>';

                    document.getElementById('account-details').innerHTML = html;
                }

                // Load balance
                const balanceResponse = await fetch('/api/twilio.php?action=get_balance');
                const balanceData = await balanceResponse.json();

                if (balanceData.success) {
                    document.getElementById('account-balance').textContent =
                        `$${balanceData.balance.balance || '0.00'}`;
                }

                // Load usage
                loadUsage();
            } catch (error) {
                showAlert('account-alert', 'Error loading account info: ' + error.message, 'error');
            }
        }

        async function loadUsage() {
            const period = document.getElementById('usage-period').value;
            const usageDiv = document.getElementById('usage-stats');

            usageDiv.innerHTML = '<div class="loading active">Loading usage statistics...</div>';

            try {
                const response = await fetch(`/api/twilio.php?action=get_usage&category=${period}`);
                const data = await response.json();

                if (data.success && data.usage.usage_records) {
                    let html = '<table><thead><tr><th>Category</th><th>Count</th><th>Usage</th><th>Price</th></tr></thead><tbody>';

                    data.usage.usage_records.forEach(record => {
                        html += `
                            <tr>
                                <td>${record.category}</td>
                                <td>${record.count}</td>
                                <td>${record.usage} ${record.usage_unit}</td>
                                <td>$${record.price}</td>
                            </tr>
                        `;
                    });

                    html += '</tbody></table>';
                    usageDiv.innerHTML = html;
                } else {
                    usageDiv.innerHTML = '<p style="color: #666; padding: 20px;">No usage data available for this period.</p>';
                }
            } catch (error) {
                usageDiv.innerHTML = `<div class="alert alert-error">Error loading usage: ${error.message}</div>`;
            }
        }

        // Webhook Functions
        async function loadWebhookLogs() {
            const logsDiv = document.getElementById('webhook-logs');
            logsDiv.innerHTML = '<div class="loading active">Loading webhook logs...</div>';

            try {
                const response = await fetch('/home/flexpbxuser/logs/twilio-webhook.log');
                const text = await response.text();

                if (text) {
                    const lines = text.split('\n').filter(l => l.trim()).slice(-50).reverse();
                    let html = '<div style="background: #f5f5f5; padding: 15px; border-radius: 5px; max-height: 400px; overflow-y: auto; font-family: monospace; font-size: 12px;">';
                    lines.forEach(line => {
                        html += line + '<br>';
                    });
                    html += '</div>';
                    logsDiv.innerHTML = html;
                } else {
                    logsDiv.innerHTML = '<p style="color: #666; padding: 20px;">No webhook logs found.</p>';
                }
            } catch (error) {
                logsDiv.innerHTML = '<p style="color: #666; padding: 20px;">Unable to load webhook logs. Log file may not exist yet.</p>';
            }
        }

        // Google Voice Functions
        async function loadGoogleVoiceConfig() {
            try {
                const response = await fetch('/modules/google-voice.php?path=config');
                const data = await response.json();

                if (data.success && data.config) {
                    const config = data.config;
                    document.getElementById('gv_number').value = config.primary_number || '';
                    document.getElementById('gv_display_name').value = config.display_name || '';
                    document.getElementById('gv_extension').value = config.default_destination || '';
                    document.getElementById('gv_after_hours').value = config.after_hours_destination || 'voicemail';
                    document.getElementById('gv_business_start').value = config.business_start || '09:00';
                    document.getElementById('gv_business_end').value = config.business_end || '18:00';
                    document.getElementById('gv_greeting').value = config.greeting_message || '';
                    document.getElementById('gv_auto_reply').value = config.auto_reply_message || '';
                    document.getElementById('gv_call_limit').value = config.call_limit_daily || 1000;
                    document.getElementById('gv_sms_limit').value = config.sms_limit_daily || 500;

                    // Checkboxes
                    document.getElementById('gv_enabled').checked = config.enabled || false;
                    document.getElementById('gv_sms').checked = config.enable_sms || false;
                    document.getElementById('gv_voicemail').checked = config.enable_voicemail || false;
                    document.getElementById('gv_transcribe').checked = config.auto_transcribe || false;
                    document.getElementById('gv_recording').checked = config.enable_call_recording || false;
                    document.getElementById('gv_screening').checked = config.enable_call_screening || false;
                    document.getElementById('gv_email_notify').checked = config.email_notifications || false;
                    document.getElementById('gv_auto_reply_enabled').checked = config.auto_reply_enabled || false;
                    document.getElementById('gv_business_hours').checked = config.business_hours_only || false;

                    showAlert('gv-alert', 'Configuration loaded successfully', 'success');
                } else {
                    showAlert('gv-alert', 'No configuration found. Using defaults.', 'info');
                }
            } catch (error) {
                showAlert('gv-alert', 'Error loading configuration: ' + error.message, 'error');
            }
        }

        document.getElementById('gv-config-form').addEventListener('submit', async (e) => {
            e.preventDefault();

            const formData = new FormData(e.target);
            const config = {
                primary_number: document.getElementById('gv_number').value,
                display_name: document.getElementById('gv_display_name').value,
                default_destination: document.getElementById('gv_extension').value,
                after_hours_destination: document.getElementById('gv_after_hours').value,
                business_start: document.getElementById('gv_business_start').value,
                business_end: document.getElementById('gv_business_end').value,
                greeting_message: document.getElementById('gv_greeting').value,
                auto_reply_message: document.getElementById('gv_auto_reply').value,
                call_limit_daily: parseInt(document.getElementById('gv_call_limit').value),
                sms_limit_daily: parseInt(document.getElementById('gv_sms_limit').value),
                enabled: document.getElementById('gv_enabled').checked,
                enable_sms: document.getElementById('gv_sms').checked,
                enable_voicemail: document.getElementById('gv_voicemail').checked,
                auto_transcribe: document.getElementById('gv_transcribe').checked,
                enable_call_recording: document.getElementById('gv_recording').checked,
                enable_call_screening: document.getElementById('gv_screening').checked,
                email_notifications: document.getElementById('gv_email_notify').checked,
                auto_reply_enabled: document.getElementById('gv_auto_reply_enabled').checked,
                business_hours_only: document.getElementById('gv_business_hours').checked
            };

            try {
                const response = await fetch('/modules/google-voice.php?path=config', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(config)
                });

                const data = await response.json();

                if (data.success) {
                    showAlert('gv-alert', 'Google Voice configuration saved successfully!', 'success');
                    updateGoogleVoiceStatus(config);
                } else {
                    showAlert('gv-alert', data.message || 'Failed to save configuration', 'error');
                }
            } catch (error) {
                showAlert('gv-alert', 'Error saving configuration: ' + error.message, 'error');
            }
        });

        document.getElementById('gv-oauth-form').addEventListener('submit', async (e) => {
            e.preventDefault();

            const oauth = {
                client_id: document.getElementById('gv_client_id').value,
                client_secret: document.getElementById('gv_client_secret').value,
                provider: 'google_voice'
            };

            try {
                const response = await fetch('/modules/google-voice.php?path=oauth', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(oauth)
                });

                const data = await response.json();

                if (data.success) {
                    showAlert('gv-alert', 'OAuth credentials saved successfully!', 'success');
                } else {
                    showAlert('gv-alert', data.message || 'Failed to save OAuth credentials', 'error');
                }
            } catch (error) {
                showAlert('gv-alert', 'Error saving OAuth credentials: ' + error.message, 'error');
            }
        });

        async function testGoogleVoice() {
            const statusDiv = document.getElementById('gv-status');
            statusDiv.innerHTML = '<p style="color: #666;">Testing Google Voice connection...</p>';

            try {
                const response = await fetch('/modules/google-voice.php?path=test');
                const data = await response.json();

                if (data.success) {
                    statusDiv.innerHTML = `
                        <div class="alert alert-success">
                            ✅ Connection successful!<br>
                            <strong>Number:</strong> ${data.number || 'Not configured'}<br>
                            <strong>Status:</strong> ${data.status || 'Active'}
                        </div>
                    `;
                } else {
                    statusDiv.innerHTML = `
                        <div class="alert alert-error">
                            ❌ Connection failed: ${data.error || 'Unknown error'}
                        </div>
                    `;
                }
            } catch (error) {
                statusDiv.innerHTML = `
                    <div class="alert alert-error">
                        ❌ Error testing connection: ${error.message}
                    </div>
                `;
            }
        }

        function authorizeGoogleVoice() {
            const clientId = document.getElementById('gv_client_id').value;
            if (!clientId) {
                showAlert('gv-alert', 'Please save your OAuth credentials first', 'error');
                return;
            }

            // Redirect to Google OAuth
            const redirectUri = encodeURIComponent('https://flexpbx.devinecreations.net/api/oauth/google-callback');
            const scope = encodeURIComponent('https://www.googleapis.com/auth/voice');
            const authUrl = `https://accounts.google.com/o/oauth2/v2/auth?client_id=${clientId}&redirect_uri=${redirectUri}&response_type=code&scope=${scope}&access_type=offline&prompt=consent`;

            window.open(authUrl, '_blank');
        }

        function updateGoogleVoiceStatus(config) {
            const statusDiv = document.getElementById('gv-status');
            let html = '<table>';
            html += `<tr><td><strong>Status:</strong></td><td><span class="badge badge-${config.enabled ? 'success' : 'error'}">${config.enabled ? 'Enabled' : 'Disabled'}</span></td></tr>`;
            html += `<tr><td><strong>Number:</strong></td><td>${config.primary_number || 'Not configured'}</td></tr>`;
            html += `<tr><td><strong>Display Name:</strong></td><td>${config.display_name || '-'}</td></tr>`;
            html += `<tr><td><strong>SMS:</strong></td><td>${config.enable_sms ? '✅ Enabled' : '❌ Disabled'}</td></tr>`;
            html += `<tr><td><strong>Voicemail:</strong></td><td>${config.enable_voicemail ? '✅ Enabled' : '❌ Disabled'}</td></tr>`;
            html += `<tr><td><strong>Business Hours:</strong></td><td>${config.business_start} - ${config.business_end}</td></tr>`;
            html += '</table>';
            statusDiv.innerHTML = html;
        }

        // Helper Functions
        function showAlert(elementId, message, type) {
            const alertDiv = document.getElementById(elementId);
            alertDiv.innerHTML = `<div class="alert alert-${type}">${message}</div>`;
            setTimeout(() => {
                alertDiv.innerHTML = '';
            }, 5000);
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            loadConfig();
        });
    </script>
</body>
</html>
