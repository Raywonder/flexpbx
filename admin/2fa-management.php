<?php require_once __DIR__ . '/admin_auth_check.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>2FA Voice Authentication Management - FlexPBX</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
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

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
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
            color: #333;
            font-size: 20px;
            margin-bottom: 15px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }

        .form-group small {
            display: block;
            margin-top: 5px;
            color: #666;
            font-size: 12px;
        }

        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-block;
            text-decoration: none;
            text-align: center;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
        }

        .btn-success {
            background: linear-gradient(135deg, #4caf50 0%, #45a049 100%);
        }

        .btn-danger {
            background: linear-gradient(135deg, #f44336 0%, #d32f2f 100%);
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .alert-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .alert-info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }

        .prompt-list {
            list-style: none;
            padding: 0;
        }

        .prompt-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .prompt-item strong {
            color: #667eea;
        }

        .settings-table {
            width: 100%;
            border-collapse: collapse;
        }

        .settings-table td {
            padding: 12px;
            border-bottom: 1px solid #e0e0e0;
        }

        .settings-table td:first-child {
            font-weight: 600;
            color: #666;
            width: 200px;
        }

        .record-controls {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }

        #recording-status {
            padding: 10px;
            background: #f8f9fa;
            border-radius: 6px;
            margin-bottom: 15px;
            display: none;
        }

        #audio-preview {
            width: 100%;
            margin-top: 10px;
        }

        .nav-links {
            margin-top: 20px;
        }

        .nav-links a {
            color: #667eea;
            text-decoration: none;
            margin-right: 15px;
        }

        .nav-links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔐 2FA Voice Authentication Management</h1>
            <p class="subtitle">Configure two-factor authentication via voice call verification</p>
            <div class="nav-links">
                <a href="dashboard.html">← Back to Dashboard</a>
            </div>
        </div>

        <div id="alert-container"></div>

        <div class="grid">
            <!-- Configuration Settings -->
            <div class="card">
                <h2>⚙️ Configuration</h2>
                <div id="config-loading">Loading configuration...</div>
                <div id="config-form" style="display: none;">
                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="2fa-enabled"> Enable 2FA Voice Authentication
                        </label>
                    </div>
                    <div class="form-group">
                        <label>Code Length</label>
                        <select id="code-length">
                            <option value="4">4 digits</option>
                            <option value="6" selected>6 digits</option>
                            <option value="8">8 digits</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Code Expiry (seconds)</label>
                        <input type="number" id="code-expiry" value="300" min="60" max="900">
                        <small>How long the code remains valid</small>
                    </div>
                    <div class="form-group">
                        <label>Maximum Attempts</label>
                        <input type="number" id="max-attempts" value="3" min="1" max="5">
                        <small>Number of failed attempts allowed</small>
                    </div>
                    <div class="form-group">
                        <label>Call Timeout (seconds)</label>
                        <input type="number" id="call-timeout" value="30" min="15" max="60">
                    </div>
                    <button class="btn" onclick="saveConfig()">Save Configuration</button>
                </div>
            </div>

            <!-- Voice Prompts -->
            <div class="card">
                <h2>🎙️ Voice Prompts</h2>
                <p style="margin-bottom: 15px; color: #666; font-size: 14px;">
                    Record or upload custom voice prompts for the 2FA system.
                </p>

                <div class="form-group">
                    <label>Prompt Type</label>
                    <select id="prompt-type">
                        <option value="welcome">Welcome Message</option>
                        <option value="enter_code">Enter Code Prompt</option>
                        <option value="invalid_code">Invalid Code Message</option>
                        <option value="code_accepted">Code Accepted Message</option>
                        <option value="code_expired">Code Expired Message</option>
                    </select>
                </div>

                <!-- Record Audio -->
                <h3 style="margin: 20px 0 10px 0; font-size: 16px;">Record from Microphone</h3>
                <div class="record-controls">
                    <button class="btn btn-success" id="record-btn" onclick="startRecording()">● Record</button>
                    <button class="btn btn-danger" id="stop-btn" onclick="stopRecording()" disabled>■ Stop</button>
                    <button class="btn btn-secondary" id="play-btn" onclick="playRecording()" disabled>▶ Play</button>
                </div>

                <div id="recording-status"></div>
                <audio id="audio-preview" controls style="display: none;"></audio>

                <!-- Upload Audio -->
                <h3 style="margin: 20px 0 10px 0; font-size: 16px;">Or Upload Audio File</h3>
                <div class="form-group">
                    <input type="file" id="audio-file" accept="audio/*,.wav,.mp3">
                    <small>Supported formats: WAV (preferred), MP3</small>
                </div>

                <button class="btn" onclick="uploadPrompt()">Upload Prompt</button>

                <!-- Current Prompts -->
                <h3 style="margin: 30px 0 10px 0; font-size: 16px;">Current Prompts</h3>
                <ul class="prompt-list" id="prompt-list">
                    <li style="padding: 10px; color: #999;">Loading prompts...</li>
                </ul>
            </div>

            <!-- Test 2FA -->
            <div class="card">
                <h2>🧪 Test 2FA System</h2>
                <p style="margin-bottom: 15px; color: #666; font-size: 14px;">
                    Test the 2FA voice call system with a test extension.
                </p>

                <div class="form-group">
                    <label>Extension to Call</label>
                    <input type="text" id="test-extension" placeholder="e.g., 2000" maxlength="4">
                </div>

                <div class="form-group">
                    <label>Phone Number (optional)</label>
                    <input type="text" id="test-phone" placeholder="e.g., 3023139555">
                    <small>Leave empty to call the extension directly</small>
                </div>

                <button class="btn btn-success" onclick="testGenerate()">1. Generate Code</button>
                <button class="btn" onclick="testInitiateCall()" id="test-call-btn" disabled>2. Initiate Call</button>

                <div id="test-results" style="margin-top: 20px; display: none;">
                    <div class="alert alert-info">
                        <strong>Generated Code:</strong> <span id="test-code" style="font-size: 24px; font-weight: bold;"></span><br>
                        <strong>Code ID:</strong> <span id="test-code-id"></span><br>
                        <strong>Expires in:</strong> <span id="test-expiry"></span> seconds
                    </div>
                    <div id="test-status"></div>
                </div>
            </div>

            <!-- Active Codes -->
            <div class="card">
                <h2>📊 System Status</h2>
                <table class="settings-table" id="status-table">
                    <tr>
                        <td>System Status</td>
                        <td id="system-status"><span style="color: #999;">Loading...</span></td>
                    </tr>
                    <tr>
                        <td>Active Codes</td>
                        <td id="active-codes">-</td>
                    </tr>
                    <tr>
                        <td>Pending Verifications</td>
                        <td id="pending-verifications">-</td>
                    </tr>
                </table>

                <div style="margin-top: 20px;">
                    <button class="btn btn-secondary" onclick="cleanExpired()">Clean Expired Codes</button>
                    <button class="btn" onclick="refreshStatus()">Refresh Status</button>
                </div>
            </div>
        </div>

        <!-- Installation Instructions -->
        <div class="card">
            <h2>📋 Installation Instructions</h2>
            <p style="margin-bottom: 15px; color: #666;">
                To enable the 2FA voice authentication system, add the following dialplan to Asterisk:
            </p>

            <div style="background: #f8f9fa; padding: 15px; border-radius: 6px; margin: 15px 0; font-family: monospace; font-size: 12px; overflow-x: auto;">
                <pre>sudo nano /etc/asterisk/extensions.conf

# Copy the dialplan from: /tmp/2fa-dialplan.txt
# Then reload Asterisk:

sudo asterisk -rx "dialplan reload"</pre>
            </div>

            <div class="alert alert-info">
                <strong>Note:</strong> The dialplan configuration file has been created at <code>/tmp/2fa-dialplan.txt</code>.
                Copy its contents to your Asterisk extensions.conf file.
            </div>
        </div>
    </div>

    <script>
        const API_BASE = '/api/2fa.php';
        let mediaRecorder;
        let audioChunks = [];
        let recordedBlob;
        let currentCodeId = null;

        // Load configuration on page load
        window.addEventListener('DOMContentLoaded', function() {
            loadConfig();
            loadPrompts();
            refreshStatus();
        });

        async function loadConfig() {
            try {
                const response = await fetch(`${API_BASE}?action=get_config`);
                const data = await response.json();

                if (data.success) {
                    const config = data.config;
                    document.getElementById('2fa-enabled').checked = config.enabled;
                    document.getElementById('code-length').value = config.code_length;
                    document.getElementById('code-expiry').value = config.code_expiry;
                    document.getElementById('max-attempts').value = config.max_attempts;
                    document.getElementById('call-timeout').value = config.call_timeout;

                    document.getElementById('config-loading').style.display = 'none';
                    document.getElementById('config-form').style.display = 'block';
                }
            } catch (error) {
                showAlert('Error loading configuration', 'error');
            }
        }

        async function saveConfig() {
            const config = {
                enabled: document.getElementById('2fa-enabled').checked,
                code_length: parseInt(document.getElementById('code-length').value),
                code_expiry: parseInt(document.getElementById('code-expiry').value),
                max_attempts: parseInt(document.getElementById('max-attempts').value),
                call_timeout: parseInt(document.getElementById('call-timeout').value),
                prompts: {} // Will be preserved from existing config
            };

            try {
                const response = await fetch(`${API_BASE}?action=update_config`, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(config)
                });

                const data = await response.json();
                if (data.success) {
                    showAlert('Configuration saved successfully', 'success');
                } else {
                    showAlert('Error saving configuration: ' + data.error, 'error');
                }
            } catch (error) {
                showAlert('Error saving configuration', 'error');
            }
        }

        async function startRecording() {
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                mediaRecorder = new MediaRecorder(stream);
                audioChunks = [];

                mediaRecorder.ondataavailable = (event) => {
                    audioChunks.push(event.data);
                };

                mediaRecorder.onstop = () => {
                    recordedBlob = new Blob(audioChunks, { type: 'audio/wav' });
                    const audioURL = URL.createObjectURL(recordedBlob);
                    document.getElementById('audio-preview').src = audioURL;
                    document.getElementById('audio-preview').style.display = 'block';
                    document.getElementById('play-btn').disabled = false;
                };

                mediaRecorder.start();
                document.getElementById('record-btn').disabled = true;
                document.getElementById('stop-btn').disabled = false;
                document.getElementById('recording-status').style.display = 'block';
                document.getElementById('recording-status').textContent = '● Recording...';
            } catch (error) {
                showAlert('Error accessing microphone: ' + error.message, 'error');
            }
        }

        function stopRecording() {
            if (mediaRecorder && mediaRecorder.state !== 'inactive') {
                mediaRecorder.stop();
                mediaRecorder.stream.getTracks().forEach(track => track.stop());
                document.getElementById('record-btn').disabled = false;
                document.getElementById('stop-btn').disabled = true;
                document.getElementById('recording-status').textContent = '✓ Recording complete';
            }
        }

        function playRecording() {
            document.getElementById('audio-preview').play();
        }

        async function uploadPrompt() {
            const promptType = document.getElementById('prompt-type').value;
            const audioFile = document.getElementById('audio-file').files[0] || recordedBlob;

            if (!audioFile) {
                showAlert('Please record or select an audio file', 'error');
                return;
            }

            const formData = new FormData();
            formData.append('prompt_type', promptType);
            formData.append('audio_file', audioFile, '2fa-' + promptType + '.wav');

            try {
                const response = await fetch(`${API_BASE}?action=upload_prompt`, {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();
                if (data.success) {
                    showAlert('Prompt uploaded successfully', 'success');
                    loadPrompts();
                    // Reset form
                    document.getElementById('audio-file').value = '';
                    document.getElementById('audio-preview').style.display = 'none';
                    recordedBlob = null;
                } else {
                    showAlert('Error uploading prompt: ' + data.error, 'error');
                }
            } catch (error) {
                showAlert('Error uploading prompt', 'error');
            }
        }

        async function loadPrompts() {
            try {
                const response = await fetch(`${API_BASE}?action=list_prompts`);
                const data = await response.json();

                if (data.success) {
                    const promptList = document.getElementById('prompt-list');
                    promptList.innerHTML = '';

                    const promptTypes = {
                        'welcome': 'Welcome Message',
                        'enter_code': 'Enter Code Prompt',
                        'invalid_code': 'Invalid Code',
                        'code_accepted': 'Code Accepted',
                        'code_expired': 'Code Expired'
                    };

                    for (const [type, path] of Object.entries(data.prompts)) {
                        const li = document.createElement('li');
                        li.className = 'prompt-item';
                        li.innerHTML = `
                            <div>
                                <strong>${promptTypes[type] || type}</strong><br>
                                <small style="color: #999;">${path}</small>
                            </div>
                        `;
                        promptList.appendChild(li);
                    }
                }
            } catch (error) {
                console.error('Error loading prompts:', error);
            }
        }

        async function testGenerate() {
            const extension = document.getElementById('test-extension').value;
            const phone = document.getElementById('test-phone').value;

            if (!extension) {
                showAlert('Please enter an extension', 'error');
                return;
            }

            try {
                const formData = new FormData();
                formData.append('extension', extension);
                if (phone) formData.append('phone_number', phone);

                const response = await fetch(`${API_BASE}?action=generate`, {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();
                if (data.success) {
                    currentCodeId = data.code_id;
                    document.getElementById('test-code').textContent = data.code;
                    document.getElementById('test-code-id').textContent = data.code_id;
                    document.getElementById('test-expiry').textContent = data.expires_in;
                    document.getElementById('test-results').style.display = 'block';
                    document.getElementById('test-call-btn').disabled = false;
                    showAlert('Code generated successfully', 'success');
                } else {
                    showAlert('Error generating code: ' + data.error, 'error');
                }
            } catch (error) {
                showAlert('Error generating code', 'error');
            }
        }

        async function testInitiateCall() {
            if (!currentCodeId) {
                showAlert('Generate a code first', 'error');
                return;
            }

            try {
                const formData = new FormData();
                formData.append('code_id', currentCodeId);

                const response = await fetch(`${API_BASE}?action=initiate_call`, {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();
                if (data.success) {
                    showAlert('Voice call initiated! Check your phone.', 'success');
                    document.getElementById('test-status').innerHTML = `
                        <div class="alert alert-info">
                            <strong>Call Status:</strong> Calling...<br>
                            The system will call the specified extension/number and ask for the verification code.
                        </div>
                    `;
                } else {
                    showAlert('Error initiating call: ' + data.error, 'error');
                }
            } catch (error) {
                showAlert('Error initiating call', 'error');
            }
        }

        async function cleanExpired() {
            try {
                const response = await fetch(`${API_BASE}?action=clean_expired`);
                const data = await response.json();

                if (data.success) {
                    showAlert(data.message, 'success');
                    refreshStatus();
                }
            } catch (error) {
                showAlert('Error cleaning expired codes', 'error');
            }
        }

        async function refreshStatus() {
            // Update system status display
            const statusEl = document.getElementById('system-status');
            statusEl.innerHTML = '<span style="color: #4caf50;">● Online</span>';
        }

        function showAlert(message, type) {
            const container = document.getElementById('alert-container');
            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;
            alert.textContent = message;
            container.appendChild(alert);

            setTimeout(() => {
                alert.remove();
            }, 5000);
        }
    </script>

    <!-- Accessibility Tools -->
    <script src="/js/accessibility.js"></script>

    <!-- Footer -->
    <div style="text-align: center; margin-top: 40px; padding: 20px;">
        <p style="color: white; opacity: 0.9; margin-bottom: 15px;">
            <a href="/admin/bug-tracker.php" style="color: white; text-decoration: underline; margin: 0 10px;">🐛 Report a Bug</a> |
            <a href="mailto:support@devine-creations.com" style="color: white; text-decoration: underline; margin: 0 10px;">📧 Support</a> |
            <a href="dashboard.html" style="color: white; text-decoration: underline; margin: 0 10px;">← Back to Dashboard</a>
        </p>
        <p style="color: white; opacity: 0.7; font-size: 0.9em;">
            Powered by <a href="https://devine-creations.com" target="_blank" style="color: white; text-decoration: underline;">Devine Creations</a> |
            <a href="https://devinecreations.net" target="_blank" style="color: white; text-decoration: underline;">devinecreations.net</a>
        </p>
    </div>
</body>
</html>
