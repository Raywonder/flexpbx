<?php require_once __DIR__ . '/admin_auth_check.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IVR Builder - FlexPBX Admin</title>
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
            max-width: 1400px;
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

        .nav-links {
            margin-top: 15px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .nav-link {
            display: inline-block;
            padding: 8px 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 13px;
            transition: transform 0.2s;
        }

        .nav-link:hover {
            transform: translateY(-2px);
        }

        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .tab {
            background: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .tab.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .tab-content {
            display: none;
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }

        .tab-content.active {
            display: block;
        }

        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
        }

        .btn-danger {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
        }

        .btn-success {
            background: linear-gradient(135deg, #4ade80 0%, #22c55e 100%);
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
        }

        input[type="text"],
        input[type="number"],
        select,
        textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            font-family: inherit;
        }

        textarea {
            min-height: 100px;
            resize: vertical;
        }

        input:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            border: 2px solid #e0e0e0;
        }

        .card h3 {
            color: #333;
            margin-bottom: 10px;
        }

        .card p {
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-success {
            background: #4ade80;
            color: white;
        }

        .badge-danger {
            background: #ef4444;
            color: white;
        }

        .badge-warning {
            background: #f59e0b;
            color: white;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #4ade80;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #ef4444;
        }

        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #3b82f6;
        }

        .option-list {
            margin-top: 10px;
        }

        .option-item {
            background: white;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .option-details {
            flex: 1;
        }

        .option-actions {
            display: flex;
            gap: 10px;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            overflow-y: auto;
        }

        .modal-content {
            background: white;
            margin: 50px auto;
            padding: 30px;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #eee;
        }

        .close {
            font-size: 28px;
            font-weight: bold;
            color: #999;
            cursor: pointer;
            line-height: 1;
        }

        .close:hover {
            color: #333;
        }

        .tooltip {
            position: relative;
            display: inline-block;
            cursor: help;
            color: #667eea;
            margin-left: 5px;
        }

        .tooltip .tooltiptext {
            visibility: hidden;
            width: 250px;
            background-color: #333;
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 8px;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            margin-left: -125px;
            opacity: 0;
            transition: opacity 0.3s;
            font-size: 12px;
            line-height: 1.4;
        }

        .tooltip:hover .tooltiptext {
            visibility: visible;
            opacity: 1;
        }

        .template-item {
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .template-item:hover {
            border-color: #667eea;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.2);
        }

        .template-item.selected {
            border-color: #667eea;
            background: #f0f4ff;
        }

        .template-category {
            display: inline-block;
            padding: 4px 8px;
            background: #667eea;
            color: white;
            border-radius: 4px;
            font-size: 11px;
            margin-right: 10px;
        }

        .audio-upload-area {
            border: 2px dashed #ddd;
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            margin: 20px 0;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .audio-upload-area:hover {
            border-color: #667eea;
            background: #f0f4ff;
        }

        .audio-file-list {
            max-height: 300px;
            overflow-y: auto;
            margin-top: 15px;
        }

        .audio-file-item {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .code-block {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 15px;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            overflow-x: auto;
            margin: 10px 0;
        }

        .two-col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        @media (max-width: 768px) {
            .two-col {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📱 IVR Builder (Auto Attendant)</h1>
            <p class="subtitle">Create professional phone menus with custom routing and audio prompts</p>
            <div class="nav-links">
                <a href="dashboard.html" class="nav-link">🏠 Dashboard</a>
                <a href="call-queues.html" class="nav-link">📞 Call Queues</a>
                <a href="call-parking.html" class="nav-link">🅿️ Call Parking</a>
                <a href="ring-groups.html" class="nav-link">🔄 Ring Groups</a>
                <a href="callcenter-dashboard.php" class="nav-link">📊 Call Center</a>
            </div>
        </div>

        <div class="tabs">
            <button class="tab active" onclick="switchTab('overview')">📋 Overview</button>
            <button class="tab" onclick="switchTab('manage')">🛠️ Manage IVRs</button>
            <button class="tab" onclick="switchTab('templates')">📄 Templates</button>
            <button class="tab" onclick="switchTab('audio')">🎙️ Audio Files</button>
            <button class="tab" onclick="switchTab('statistics')">📊 Statistics</button>
            <button class="tab" onclick="switchTab('help')">❓ How to Use</button>
        </div>

        <!-- Overview Tab -->
        <div id="overview-tab" class="tab-content active">
            <h2 style="margin-bottom: 20px;">IVR Menus Overview</h2>

            <div id="overview-stats" class="grid">
                <div class="card">
                    <h3>Total IVR Menus</h3>
                    <p style="font-size: 36px; font-weight: bold; color: #667eea;" id="total-ivrs">0</p>
                </div>
                <div class="card">
                    <h3>Active Menus</h3>
                    <p style="font-size: 36px; font-weight: bold; color: #4ade80;" id="active-ivrs">0</p>
                </div>
                <div class="card">
                    <h3>Custom Audio Files</h3>
                    <p style="font-size: 36px; font-weight: bold; color: #f59e0b;" id="total-audio">0</p>
                </div>
            </div>

            <div id="ivr-list"></div>

            <div style="margin-top: 20px;">
                <button class="btn" onclick="showCreateIVRModal()">➕ Create New IVR Menu</button>
                <button class="btn btn-secondary" onclick="switchTab('templates')">📄 Use Template</button>
            </div>
        </div>

        <!-- Manage IVRs Tab -->
        <div id="manage-tab" class="tab-content">
            <h2 style="margin-bottom: 20px;">Manage IVR Menus</h2>

            <div id="manage-ivr-list"></div>

            <div style="margin-top: 30px;">
                <button class="btn" onclick="showCreateIVRModal()">➕ Create New IVR</button>
                <button class="btn btn-success" onclick="applyIVRConfig()">✅ Apply Configuration to Asterisk</button>
            </div>
        </div>

        <!-- Templates Tab -->
        <div id="templates-tab" class="tab-content">
            <h2 style="margin-bottom: 20px;">IVR Templates</h2>
            <p style="margin-bottom: 20px;">Choose from pre-built templates or create your own custom templates</p>

            <div style="margin-bottom: 30px;">
                <button class="btn" onclick="showCreateTemplateModal()">➕ Create Custom Template</button>
                <button class="btn btn-secondary" onclick="loadMyTemplates()">📁 My Templates</button>
                <button class="btn btn-secondary" onclick="loadSystemTemplates()">📚 System Templates</button>
            </div>

            <div id="template-filter" style="margin-bottom: 20px;">
                <select id="template-category-filter" onchange="filterTemplates()" style="width: 200px;">
                    <option value="all">All Categories</option>
                    <option value="business">Business</option>
                    <option value="professional">Professional</option>
                    <option value="support">Support/Help Desk</option>
                    <option value="medical">Medical</option>
                    <option value="legal">Legal</option>
                    <option value="ecommerce">E-commerce</option>
                    <option value="personal">Personal</option>
                    <option value="custom">My Custom Templates</option>
                </select>
            </div>

            <div id="templates-list"></div>
        </div>

        <!-- Audio Files Tab -->
        <div id="audio-tab" class="tab-content">
            <h2 style="margin-bottom: 20px;">Audio File Management</h2>

            <div class="audio-upload-area" onclick="document.getElementById('audio-file-input').click()">
                <h3>🎙️ Upload Audio File</h3>
                <p>Click here or drag and drop WAV/MP3 files</p>
                <p style="font-size: 12px; color: #999;">Recommended: WAV 16-bit 8kHz mono</p>
                <input type="file" id="audio-file-input" accept=".wav,.mp3" style="display: none;" onchange="uploadAudioFile(this)">
            </div>

            <h3 style="margin-top: 30px;">Available Audio Files</h3>

            <div id="audio-files-list" class="audio-file-list"></div>
        </div>

        <!-- Statistics Tab -->
        <div id="statistics-tab" class="tab-content">
            <h2 style="margin-bottom: 20px;">IVR Usage Statistics</h2>

            <div class="form-group">
                <label>Select IVR Menu:</label>
                <select id="stats-ivr-select" onchange="loadIVRStatistics()">
                    <option value="">All IVRs</option>
                </select>
            </div>

            <div class="form-group">
                <label>Time Period:</label>
                <select id="stats-period" onchange="loadIVRStatistics()">
                    <option value="7">Last 7 Days</option>
                    <option value="30" selected>Last 30 Days</option>
                    <option value="90">Last 90 Days</option>
                </select>
            </div>

            <div id="statistics-content"></div>
        </div>

        <!-- Help Tab -->
        <div id="help-tab" class="tab-content">
            <h2 style="margin-bottom: 20px;">How to Use the IVR Builder</h2>

            <h3>What is an IVR (Interactive Voice Response)?</h3>
            <p style="margin-bottom: 20px;">
                An IVR is an automated phone menu system that allows callers to interact with your phone system using their phone's keypad.
                Callers hear prompts like "Press 1 for Sales, Press 2 for Support" and are routed to the appropriate destination.
            </p>

            <h3>Quick Start Guide</h3>
            <ol style="margin-left: 20px; margin-bottom: 20px;">
                <li><strong>Create IVR Menu:</strong> Click "Create New IVR" and give it a number (e.g., 5000) and name</li>
                <li><strong>Choose Greeting:</strong> Upload an audio file or use text-to-speech for the main prompt</li>
                <li><strong>Add Menu Options:</strong> Configure what happens when callers press 1-9, 0, *, or #</li>
                <li><strong>Set Destinations:</strong> Route options to extensions, queues, ring groups, or other IVRs</li>
                <li><strong>Configure Timeouts:</strong> Set what happens if caller doesn't press anything or presses invalid key</li>
                <li><strong>Apply Configuration:</strong> Push settings to Asterisk to activate the IVR</li>
                <li><strong>Route Calls to IVR:</strong> Use inbound routing to send DIDs to your IVR number</li>
            </ol>

            <h3>IVR Configuration Options</h3>

            <div class="card" style="margin-bottom: 15px;">
                <h4>Greeting Types</h4>
                <ul style="margin-left: 20px;">
                    <li><strong>Recording:</strong> Upload WAV file with your menu prompt</li>
                    <li><strong>Text-to-Speech (TTS):</strong> Enter text, system converts to speech (requires TTS engine)</li>
                    <li><strong>None:</strong> No greeting, go straight to menu (advanced)</li>
                </ul>
            </div>

            <div class="card" style="margin-bottom: 15px;">
                <h4>Menu Options (Digits)</h4>
                <ul style="margin-left: 20px;">
                    <li><strong>0-9:</strong> Standard menu options</li>
                    <li><strong>* (Star):</strong> Typically used to repeat menu or go back</li>
                    <li><strong># (Pound):</strong> Can be used for additional options</li>
                </ul>
            </div>

            <div class="card" style="margin-bottom: 15px;">
                <h4>Destination Types</h4>
                <ul style="margin-left: 20px;">
                    <li><strong>Extension:</strong> Route to specific extension (e.g., 2000)</li>
                    <li><strong>Queue:</strong> Route to call queue (e.g., 5000 for Sales)</li>
                    <li><strong>Ring Group:</strong> Ring multiple extensions</li>
                    <li><strong>Another IVR:</strong> Create nested menus (submenu)</li>
                    <li><strong>Conference:</strong> Conference room</li>
                    <li><strong>Voicemail:</strong> Leave message in mailbox</li>
                    <li><strong>Operator:</strong> Default operator (extension 0)</li>
                    <li><strong>Hangup:</strong> End call</li>
                </ul>
            </div>

            <div class="card" style="margin-bottom: 15px;">
                <h4>Direct Dial</h4>
                <p>Enable "Direct Dial" to allow callers to dial extension numbers directly from the IVR (e.g., dial 2000 to reach ext 2000).</p>
            </div>

            <h3>Best Practices</h3>
            <ul style="margin-left: 20px; margin-bottom: 20px;">
                <li>Keep menus simple - 4-5 options maximum for main menu</li>
                <li>Always provide option 0 for operator</li>
                <li>Use clear, professional audio recordings</li>
                <li>Test your IVR after making changes</li>
                <li>Set appropriate timeouts (8-10 seconds recommended)</li>
                <li>Use nested IVRs for complex routing (main menu → department submenu)</li>
                <li>Include a "repeat menu" option (usually *)</li>
            </ul>

            <h3>Audio File Guidelines</h3>
            <div class="code-block">
Recommended Format:
- WAV 16-bit 8kHz mono (Asterisk standard)
- Clear, professional voice
- No background music (unless desired)
- Consistent volume levels

Recording Example:
"Thank you for calling [Company Name].
For sales, press 1.
For support, press 2.
For billing, press 3.
To speak with an operator, press 0."
            </div>

            <h3>Using Templates</h3>
            <p style="margin-bottom: 20px;">
                Templates provide pre-configured IVR setups for common business scenarios. You can use them as-is or customize them
                to your needs. You can also save your own configurations as custom templates for reuse.
            </p>

            <h3>Testing Your IVR</h3>
            <ol style="margin-left: 20px;">
                <li>Apply configuration to Asterisk</li>
                <li>Dial the IVR number from an extension (e.g., dial 5000)</li>
                <li>Listen to the greeting</li>
                <li>Test each menu option</li>
                <li>Verify destinations are correct</li>
                <li>Test timeout and invalid input handling</li>
            </ol>

            <h3>Common Use Cases</h3>

            <div class="card" style="margin-bottom: 15px;">
                <h4>Simple Business Menu</h4>
                <p>Main IVR with options for sales, support, and billing. Each routes to appropriate queue or extension.</p>
            </div>

            <div class="card" style="margin-bottom: 15px;">
                <h4>Multi-Level Menu</h4>
                <p>Main menu routes to department-specific sub-menus. For example, "Press 1 for Sales" → Sales submenu with product categories.</p>
            </div>

            <div class="card" style="margin-bottom: 15px;">
                <h4>After-Hours Routing</h4>
                <p>Combined with Time Conditions, route to IVR during business hours, voicemail after hours.</p>
            </div>

            <div class="card" style="margin-bottom: 15px;">
                <h4>International Menu</h4>
                <p>Language selection: "For English, press 1. Para Español, oprima 2" → Route to language-specific IVRs.</p>
            </div>
        </div>
    </div>

    <!-- Create/Edit IVR Modal -->
    <div id="ivr-modal" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
                <h2 id="ivr-modal-title">Create New IVR Menu</h2>
                <span class="close" onclick="closeIVRModal()">&times;</span>
            </div>

            <div id="ivr-form">
                <input type="hidden" id="ivr-id">

                <div class="two-col">
                    <div class="form-group">
                        <label>IVR Number (Extension) *</label>
                        <input type="text" id="ivr-number" placeholder="e.g., 5000" required>
                    </div>

                    <div class="form-group">
                        <label>IVR Name *</label>
                        <input type="text" id="ivr-name" placeholder="e.g., Main Menu" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea id="ivr-description" placeholder="Description of this IVR menu"></textarea>
                </div>

                <h3 style="margin-top: 20px;">Greeting Configuration</h3>

                <div class="form-group">
                    <label>Greeting Type</label>
                    <select id="ivr-greeting-type" onchange="toggleGreetingFields()">
                        <option value="recording">Audio Recording</option>
                        <option value="tts">Text-to-Speech</option>
                        <option value="none">No Greeting</option>
                    </select>
                </div>

                <div class="form-group" id="greeting-file-group">
                    <label>Audio File</label>
                    <select id="ivr-greeting-file">
                        <option value="">Select audio file...</option>
                    </select>
                </div>

                <div class="form-group" id="greeting-text-group" style="display: none;">
                    <label>Greeting Text (TTS)</label>
                    <textarea id="ivr-greeting-text" placeholder="Enter text to be converted to speech"></textarea>
                </div>

                <h3 style="margin-top: 20px;">Menu Behavior</h3>

                <div class="two-col">
                    <div class="form-group">
                        <label>Timeout (seconds)
                            <span class="tooltip">ℹ️
                                <span class="tooltiptext">How long to wait for caller input before timeout</span>
                            </span>
                        </label>
                        <input type="number" id="ivr-timeout" value="10" min="3" max="30">
                    </div>

                    <div class="form-group">
                        <label>Invalid Retries
                            <span class="tooltip">ℹ️
                                <span class="tooltiptext">How many times to allow invalid input before routing to destination</span>
                            </span>
                        </label>
                        <input type="number" id="ivr-invalid-retries" value="3" min="1" max="10">
                    </div>
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" id="ivr-direct-dial"> Enable Direct Dial
                        <span class="tooltip">ℹ️
                            <span class="tooltiptext">Allow callers to dial extension numbers directly (2XXX-9XXX)</span>
                        </span>
                    </label>
                </div>

                <h3 style="margin-top: 20px;">Timeout & Invalid Destinations</h3>

                <div class="two-col">
                    <div class="form-group">
                        <label>Timeout Destination</label>
                        <select id="ivr-timeout-type" onchange="toggleTimeoutValue()">
                            <option value="operator">Operator (0)</option>
                            <option value="extension">Extension</option>
                            <option value="queue">Queue</option>
                            <option value="voicemail">Voicemail</option>
                            <option value="hangup">Hangup</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Value</label>
                        <input type="text" id="ivr-timeout-value" placeholder="Destination value">
                    </div>
                </div>

                <div class="two-col">
                    <div class="form-group">
                        <label>Invalid Destination</label>
                        <select id="ivr-invalid-type" onchange="toggleInvalidValue()">
                            <option value="operator">Operator (0)</option>
                            <option value="extension">Extension</option>
                            <option value="queue">Queue</option>
                            <option value="voicemail">Voicemail</option>
                            <option value="hangup">Hangup</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Value</label>
                        <input type="text" id="ivr-invalid-value" placeholder="Destination value">
                    </div>
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" id="ivr-enabled" checked> Enabled
                    </label>
                </div>

                <div style="margin-top: 30px; display: flex; gap: 10px;">
                    <button class="btn" onclick="saveIVR()">💾 Save IVR</button>
                    <button class="btn btn-secondary" onclick="closeIVRModal()">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit IVR Options Modal -->
    <div id="options-modal" class="modal">
        <div class="modal-content" style="max-width: 900px;">
            <div class="modal-header">
                <h2>Configure Menu Options</h2>
                <span class="close" onclick="closeOptionsModal()">&times;</span>
            </div>

            <div id="current-ivr-info" style="margin-bottom: 20px; padding: 15px; background: #f0f4ff; border-radius: 6px;">
                <strong>IVR:</strong> <span id="current-ivr-name"></span> (<span id="current-ivr-number"></span>)
            </div>

            <button class="btn" onclick="showAddOptionForm()" style="margin-bottom: 20px;">➕ Add Menu Option</button>

            <div id="add-option-form" style="display: none; background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                <h3>Add New Option</h3>

                <input type="hidden" id="current-ivr-id">

                <div class="two-col">
                    <div class="form-group">
                        <label>Digit (0-9, *, #) *</label>
                        <select id="option-digit">
                            <option value="0">0 - Operator</option>
                            <option value="1">1</option>
                            <option value="2">2</option>
                            <option value="3">3</option>
                            <option value="4">4</option>
                            <option value="5">5</option>
                            <option value="6">6</option>
                            <option value="7">7</option>
                            <option value="8">8</option>
                            <option value="9">9</option>
                            <option value="*">* - Star (Repeat)</option>
                            <option value="#"># - Pound</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Description</label>
                        <input type="text" id="option-description" placeholder="e.g., Sales Department">
                    </div>
                </div>

                <div class="two-col">
                    <div class="form-group">
                        <label>Destination Type *</label>
                        <select id="option-dest-type" onchange="toggleOptionDestValue()">
                            <option value="extension">Extension</option>
                            <option value="queue">Queue</option>
                            <option value="ringgroup">Ring Group</option>
                            <option value="ivr">Another IVR</option>
                            <option value="conference">Conference</option>
                            <option value="voicemail">Voicemail</option>
                            <option value="operator">Operator (0)</option>
                            <option value="hangup">Hangup</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Destination Value *</label>
                        <input type="text" id="option-dest-value" placeholder="e.g., 2000">
                    </div>
                </div>

                <div style="display: flex; gap: 10px;">
                    <button class="btn" onclick="addMenuOption()">➕ Add Option</button>
                    <button class="btn btn-secondary" onclick="hideAddOptionForm()">Cancel</button>
                </div>
            </div>

            <h3>Current Menu Options</h3>
            <div id="options-list" class="option-list"></div>
        </div>
    </div>

    <!-- Create Custom Template Modal -->
    <div id="create-template-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Create Custom IVR Template</h2>
                <span class="close" onclick="closeCreateTemplateModal()">&times;</span>
            </div>

            <div class="form-group">
                <label>Template Name *</label>
                <input type="text" id="template-name" placeholder="e.g., My Company Main Menu">
            </div>

            <div class="form-group">
                <label>Category *</label>
                <select id="template-category">
                    <option value="custom">Custom</option>
                    <option value="business">Business</option>
                    <option value="professional">Professional</option>
                    <option value="support">Support/Help Desk</option>
                    <option value="personal">Personal</option>
                </select>
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea id="template-description" placeholder="Describe this template and when to use it"></textarea>
            </div>

            <div class="form-group">
                <label>Base this template on existing IVR:</label>
                <select id="template-base-ivr">
                    <option value="">Create from scratch</option>
                </select>
            </div>

            <div style="margin-top: 30px; display: flex; gap: 10px;">
                <button class="btn" onclick="saveCustomTemplate()">💾 Save Template</button>
                <button class="btn btn-secondary" onclick="closeCreateTemplateModal()">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        const API_BASE = '/api/ivr.php';
        let currentIVRs = [];
        let currentAudioFiles = [];
        let customTemplates = [];

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            loadIVRs();
            loadAudioFiles();
            loadSystemTemplates();
        });

        function switchTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });

            // Deactivate all tab buttons
            document.querySelectorAll('.tab').forEach(btn => {
                btn.classList.remove('active');
            });

            // Show selected tab
            document.getElementById(tabName + '-tab').classList.add('active');

            // Activate selected tab button
            event.target.classList.add('active');

            // Load data for specific tabs
            if (tabName === 'statistics') {
                populateStatsIVRSelect();
            } else if (tabName === 'audio') {
                loadAudioFiles();
            }
        }

        function loadIVRs() {
            fetch(API_BASE + '?path=list')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        currentIVRs = data.data;
                        displayIVROverview(data.data);
                        displayManageIVRs(data.data);
                        populateIVRSelects(data.data);
                    }
                })
                .catch(error => console.error('Error loading IVRs:', error));
        }

        function displayIVROverview(ivrs) {
            const totalIVRs = ivrs.length;
            const activeIVRs = ivrs.filter(ivr => ivr.enabled == 1).length;

            document.getElementById('total-ivrs').textContent = totalIVRs;
            document.getElementById('active-ivrs').textContent = activeIVRs;

            const listHtml = ivrs.length > 0 ? `
                <h3 style="margin-top: 30px;">Your IVR Menus</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Number</th>
                            <th>Name</th>
                            <th>Options</th>
                            <th>Greeting</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${ivrs.map(ivr => `
                            <tr>
                                <td><strong>${ivr.ivr_number}</strong></td>
                                <td>${ivr.ivr_name}</td>
                                <td>${ivr.option_count} options</td>
                                <td>${ivr.greeting_type}</td>
                                <td>
                                    ${ivr.enabled == 1
                                        ? '<span class="badge badge-success">Active</span>'
                                        : '<span class="badge badge-danger">Disabled</span>'}
                                </td>
                                <td>
                                    <button class="btn" onclick="editIVR(${ivr.id})" style="padding: 6px 12px; font-size: 12px;">Edit</button>
                                    <button class="btn" onclick="configureOptions(${ivr.id})" style="padding: 6px 12px; font-size: 12px;">Options</button>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            ` : '<p style="margin-top: 20px; color: #666;">No IVR menus configured yet. Click "Create New IVR Menu" to get started.</p>';

            document.getElementById('ivr-list').innerHTML = listHtml;
        }

        function displayManageIVRs(ivrs) {
            const html = ivrs.length > 0 ? ivrs.map(ivr => `
                <div class="card" style="margin-bottom: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: start;">
                        <div style="flex: 1;">
                            <h3>${ivr.ivr_name} (${ivr.ivr_number})
                                ${ivr.enabled == 1
                                    ? '<span class="badge badge-success">Active</span>'
                                    : '<span class="badge badge-danger">Disabled</span>'}
                            </h3>
                            <p>${ivr.description || 'No description'}</p>
                            <p style="font-size: 13px; color: #666;">
                                <strong>Greeting:</strong> ${ivr.greeting_type} |
                                <strong>Options:</strong> ${ivr.option_count} configured |
                                <strong>Timeout:</strong> ${ivr.timeout}s
                            </p>
                        </div>
                        <div style="display: flex; flex-direction: column; gap: 8px;">
                            <button class="btn" onclick="editIVR(${ivr.id})" style="padding: 8px 16px; font-size: 13px;">✏️ Edit</button>
                            <button class="btn btn-success" onclick="configureOptions(${ivr.id})" style="padding: 8px 16px; font-size: 13px;">⚙️ Options</button>
                            <button class="btn btn-danger" onclick="deleteIVR(${ivr.id})" style="padding: 8px 16px; font-size: 13px;">🗑️ Delete</button>
                        </div>
                    </div>
                </div>
            `).join('') : '<p style="color: #666;">No IVR menus configured.</p>';

            document.getElementById('manage-ivr-list').innerHTML = html;
        }

        function showCreateIVRModal() {
            document.getElementById('ivr-modal-title').textContent = 'Create New IVR Menu';
            document.getElementById('ivr-id').value = '';
            document.getElementById('ivr-form').reset();
            document.getElementById('ivr-enabled').checked = true;
            populateAudioFileSelect();
            document.getElementById('ivr-modal').style.display = 'block';
        }

        function closeIVRModal() {
            document.getElementById('ivr-modal').style.display = 'none';
        }

        function toggleGreetingFields() {
            const greetingType = document.getElementById('ivr-greeting-type').value;
            document.getElementById('greeting-file-group').style.display = greetingType === 'recording' ? 'block' : 'none';
            document.getElementById('greeting-text-group').style.display = greetingType === 'tts' ? 'block' : 'none';
        }

        function populateAudioFileSelect() {
            const select = document.getElementById('ivr-greeting-file');
            const html = '<option value="">Select audio file...</option>' +
                currentAudioFiles.map(file => `<option value="${file.filename}">${file.display_name}</option>`).join('');
            select.innerHTML = html;
        }

        function saveIVR() {
            const id = document.getElementById('ivr-id').value;
            const data = {
                ivr_number: document.getElementById('ivr-number').value,
                ivr_name: document.getElementById('ivr-name').value,
                description: document.getElementById('ivr-description').value,
                greeting_type: document.getElementById('ivr-greeting-type').value,
                greeting_file: document.getElementById('ivr-greeting-file').value,
                greeting_text: document.getElementById('ivr-greeting-text').value,
                timeout: parseInt(document.getElementById('ivr-timeout').value),
                invalid_retries: parseInt(document.getElementById('ivr-invalid-retries').value),
                timeout_destination_type: document.getElementById('ivr-timeout-type').value,
                timeout_destination_value: document.getElementById('ivr-timeout-value').value,
                invalid_destination_type: document.getElementById('ivr-invalid-type').value,
                invalid_destination_value: document.getElementById('ivr-invalid-value').value,
                direct_dial_enabled: document.getElementById('ivr-direct-dial').checked ? 1 : 0,
                enabled: document.getElementById('ivr-enabled').checked ? 1 : 0
            };

            const url = id ? API_BASE + '?path=update&id=' + id : API_BASE + '?path=create';
            const method = id ? 'PUT' : 'POST';

            fetch(url, {
                method: method,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        alert('IVR saved successfully!');
                        closeIVRModal();
                        loadIVRs();
                    } else {
                        alert('Error: ' + result.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to save IVR');
                });
        }

        function editIVR(id) {
            fetch(API_BASE + '?path=get&id=' + id)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const ivr = data.data;
                        document.getElementById('ivr-modal-title').textContent = 'Edit IVR Menu';
                        document.getElementById('ivr-id').value = ivr.id;
                        document.getElementById('ivr-number').value = ivr.ivr_number;
                        document.getElementById('ivr-name').value = ivr.ivr_name;
                        document.getElementById('ivr-description').value = ivr.description || '';
                        document.getElementById('ivr-greeting-type').value = ivr.greeting_type;
                        document.getElementById('ivr-greeting-file').value = ivr.greeting_file || '';
                        document.getElementById('ivr-greeting-text').value = ivr.greeting_text || '';
                        document.getElementById('ivr-timeout').value = ivr.timeout;
                        document.getElementById('ivr-invalid-retries').value = ivr.invalid_retries;
                        document.getElementById('ivr-timeout-type').value = ivr.timeout_destination_type;
                        document.getElementById('ivr-timeout-value').value = ivr.timeout_destination_value || '';
                        document.getElementById('ivr-invalid-type').value = ivr.invalid_destination_type;
                        document.getElementById('ivr-invalid-value').value = ivr.invalid_destination_value || '';
                        document.getElementById('ivr-direct-dial').checked = ivr.direct_dial_enabled == 1;
                        document.getElementById('ivr-enabled').checked = ivr.enabled == 1;

                        populateAudioFileSelect();
                        toggleGreetingFields();
                        document.getElementById('ivr-modal').style.display = 'block';
                    }
                })
                .catch(error => console.error('Error loading IVR:', error));
        }

        function deleteIVR(id) {
            if (!confirm('Are you sure you want to delete this IVR menu? This cannot be undone.')) {
                return;
            }

            fetch(API_BASE + '?path=delete&id=' + id, { method: 'DELETE' })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        alert('IVR deleted successfully');
                        loadIVRs();
                    } else {
                        alert('Error: ' + result.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to delete IVR');
                });
        }

        function configureOptions(ivrId) {
            // Load IVR details
            fetch(API_BASE + '?path=get&id=' + ivrId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const ivr = data.data;
                        document.getElementById('current-ivr-id').value = ivr.id;
                        document.getElementById('current-ivr-name').textContent = ivr.ivr_name;
                        document.getElementById('current-ivr-number').textContent = ivr.ivr_number;

                        displayOptions(ivr.options || []);
                        document.getElementById('options-modal').style.display = 'block';
                    }
                })
                .catch(error => console.error('Error loading IVR options:', error));
        }

        function closeOptionsModal() {
            document.getElementById('options-modal').style.display = 'none';
            hideAddOptionForm();
        }

        function showAddOptionForm() {
            document.getElementById('add-option-form').style.display = 'block';
        }

        function hideAddOptionForm() {
            document.getElementById('add-option-form').style.display = 'none';
            document.getElementById('option-digit').value = '0';
            document.getElementById('option-description').value = '';
            document.getElementById('option-dest-type').value = 'extension';
            document.getElementById('option-dest-value').value = '';
        }

        function displayOptions(options) {
            const html = options.length > 0 ? options.map(opt => `
                <div class="option-item">
                    <div class="option-details">
                        <strong>Digit ${opt.digit}:</strong> ${opt.option_description || 'No description'}
                        <br>
                        <span style="font-size: 13px; color: #666;">
                            Routes to: ${opt.destination_type} → ${opt.destination_value}
                            ${opt.enabled == 1 ? '<span class="badge badge-success">Active</span>' : '<span class="badge badge-danger">Disabled</span>'}
                        </span>
                    </div>
                    <div class="option-actions">
                        <button class="btn btn-danger" onclick="deleteOption(${opt.id})" style="padding: 6px 12px; font-size: 12px;">🗑️ Remove</button>
                    </div>
                </div>
            `).join('') : '<p style="color: #666;">No menu options configured. Add options to route callers.</p>';

            document.getElementById('options-list').innerHTML = html;
        }

        function addMenuOption() {
            const ivrId = document.getElementById('current-ivr-id').value;
            const data = {
                ivr_menu_id: parseInt(ivrId),
                digit: document.getElementById('option-digit').value,
                option_description: document.getElementById('option-description').value,
                destination_type: document.getElementById('option-dest-type').value,
                destination_value: document.getElementById('option-dest-value').value,
                enabled: 1
            };

            fetch(API_BASE + '?path=add-option', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        alert('Menu option added successfully!');
                        hideAddOptionForm();
                        configureOptions(ivrId);
                    } else {
                        alert('Error: ' + result.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to add option');
                });
        }

        function deleteOption(optionId) {
            if (!confirm('Remove this menu option?')) {
                return;
            }

            fetch(API_BASE + '?path=remove-option&id=' + optionId, { method: 'DELETE' })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        const ivrId = document.getElementById('current-ivr-id').value;
                        configureOptions(ivrId);
                    } else {
                        alert('Error: ' + result.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to remove option');
                });
        }

        function applyIVRConfig() {
            if (!confirm('Apply IVR configuration to Asterisk? This will reload the dialplan.')) {
                return;
            }

            fetch(API_BASE + '?path=apply-config', { method: 'POST' })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        alert('IVR configuration applied successfully!\n\n' + result.message);
                        loadIVRs();
                    } else {
                        alert('Error: ' + result.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to apply configuration');
                });
        }

        function loadAudioFiles() {
            fetch(API_BASE + '?path=audio-files')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        currentAudioFiles = data.data;
                        document.getElementById('total-audio').textContent = data.data.filter(f => f.category === 'Custom').length;
                        displayAudioFiles(data.data);
                    }
                })
                .catch(error => console.error('Error loading audio files:', error));
        }

        function displayAudioFiles(files) {
            const customFiles = files.filter(f => f.category === 'Custom');
            const systemFiles = files.filter(f => f.category === 'System');

            let html = '<h4>Custom Audio Files</h4>';
            html += customFiles.length > 0 ? customFiles.map(file => `
                <div class="audio-file-item">
                    <div>
                        <strong>${file.display_name}</strong>
                        <br>
                        <span style="font-size: 12px; color: #666;">${file.filename}</span>
                    </div>
                    <span class="badge badge-success">Custom</span>
                </div>
            `).join('') : '<p style="margin: 10px 0; color: #666;">No custom audio files uploaded.</p>';

            html += '<h4 style="margin-top: 30px;">System Audio Files (Built-in)</h4>';
            html += systemFiles.map(file => `
                <div class="audio-file-item">
                    <strong>${file.display_name}</strong>
                    <span class="badge" style="background: #95a5a6;">System</span>
                </div>
            `).join('');

            document.getElementById('audio-files-list').innerHTML = html;
        }

        function uploadAudioFile(input) {
            const file = input.files[0];
            if (!file) return;

            const formData = new FormData();
            formData.append('audio_file', file);

            fetch(API_BASE + '?path=upload-audio', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        alert('Audio file uploaded successfully!\n\n' + result.message);
                        loadAudioFiles();
                    } else {
                        alert('Error: ' + result.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to upload audio file');
                });

            input.value = '';
        }

        function populateStatsIVRSelect() {
            const select = document.getElementById('stats-ivr-select');
            select.innerHTML = '<option value="">All IVRs</option>' +
                currentIVRs.map(ivr => `<option value="${ivr.id}">${ivr.ivr_name} (${ivr.ivr_number})</option>`).join('');
        }

        function loadIVRStatistics() {
            const ivrId = document.getElementById('stats-ivr-select').value;
            const period = document.getElementById('stats-period').value;

            let url = API_BASE + '?path=statistics&days=' + period;
            if (ivrId) {
                url += '&ivr_id=' + ivrId;
            }

            fetch(url)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayStatistics(data.data);
                    }
                })
                .catch(error => console.error('Error loading statistics:', error));
        }

        function displayStatistics(stats) {
            if (stats.length === 0) {
                document.getElementById('statistics-content').innerHTML = '<p style="color: #666;">No statistics available for the selected period.</p>';
                return;
            }

            const html = `
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>IVR</th>
                            <th>Total Calls</th>
                            <th>Timeouts</th>
                            <th>Invalid</th>
                            <th>Direct Dial</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${stats.map(stat => `
                            <tr>
                                <td>${stat.date}</td>
                                <td>${stat.ivr_name || 'IVR ' + stat.ivr_menu_id}</td>
                                <td><strong>${stat.total_calls}</strong></td>
                                <td>${stat.timeout_count}</td>
                                <td>${stat.invalid_count}</td>
                                <td>${stat.direct_dial_count}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            `;

            document.getElementById('statistics-content').innerHTML = html;
        }

        function populateIVRSelects(ivrs) {
            const baseIVRSelect = document.getElementById('template-base-ivr');
            if (baseIVRSelect) {
                baseIVRSelect.innerHTML = '<option value="">Create from scratch</option>' +
                    ivrs.map(ivr => `<option value="${ivr.id}">${ivr.ivr_name} (${ivr.ivr_number})</option>`).join('');
            }
        }

        function loadSystemTemplates() {
            // System templates (built-in)
            const systemTemplates = [
                {
                    id: 'simple-business',
                    name: 'Simple Business (3 Options)',
                    category: 'business',
                    description: 'Basic menu with Sales, Support, and Billing',
                    options_count: 4,
                    type: 'system'
                },
                {
                    id: 'professional-business',
                    name: 'Professional Business (5 Options)',
                    category: 'professional',
                    description: 'Comprehensive menu with multiple departments',
                    options_count: 6,
                    type: 'system'
                },
                {
                    id: 'support-helpdesk',
                    name: 'IT Help Desk',
                    category: 'support',
                    description: 'Technical support menu with priority levels',
                    options_count: 6,
                    type: 'system'
                },
                {
                    id: 'customer-support-callback',
                    name: 'Customer Support with Callback',
                    category: 'support',
                    description: 'Support menu with callback option',
                    options_count: 5,
                    type: 'system'
                },
                {
                    id: 'personal-home-office',
                    name: 'Personal/Home Office',
                    category: 'personal',
                    description: 'Simple menu for home-based business',
                    options_count: 3,
                    type: 'system'
                },
                {
                    id: 'ecommerce-store',
                    name: 'Online Store',
                    category: 'ecommerce',
                    description: 'E-commerce support menu',
                    options_count: 5,
                    type: 'system'
                },
                {
                    id: 'medical-office',
                    name: 'Medical Office',
                    category: 'medical',
                    description: 'Healthcare practice menu',
                    options_count: 6,
                    type: 'system'
                },
                {
                    id: 'law-office',
                    name: 'Law Office',
                    category: 'legal',
                    description: 'Legal practice menu',
                    options_count: 5,
                    type: 'system'
                }
            ];

            displayTemplates(systemTemplates);
        }

        function displayTemplates(templates) {
            const html = templates.length > 0 ? templates.map(tmpl => `
                <div class="template-item" onclick="selectTemplate('${tmpl.id}', '${tmpl.type}')">
                    <span class="template-category">${tmpl.category}</span>
                    <h4>${tmpl.name}</h4>
                    <p style="margin: 8px 0; color: #666; font-size: 14px;">${tmpl.description}</p>
                    <p style="font-size: 12px; color: #999;">
                        ${tmpl.options_count} menu options configured
                        ${tmpl.type === 'custom' ? ' | <strong>Your Template</strong>' : ''}
                    </p>
                </div>
            `).join('') : '<p style="color: #666;">No templates available in this category.</p>';

            document.getElementById('templates-list').innerHTML = html;
        }

        function selectTemplate(templateId, type) {
            if (type === 'system') {
                if (confirm('Apply this system template to create a new IVR?')) {
                    applySystemTemplate(templateId);
                }
            } else {
                if (confirm('Apply this custom template to create a new IVR?')) {
                    applyCustomTemplate(templateId);
                }
            }
        }

        function applySystemTemplate(templateId) {
            alert('Template application coming soon!\n\nYou can manually create an IVR based on this template using the scripts in:\n/home/flexpbxuser/IVR_MENU_SCRIPTS_AND_TEMPLATES.md');
        }

        function showCreateTemplateModal() {
            populateIVRSelects(currentIVRs);
            document.getElementById('create-template-modal').style.display = 'block';
        }

        function closeCreateTemplateModal() {
            document.getElementById('create-template-modal').style.display = 'none';
        }

        function saveCustomTemplate() {
            const name = document.getElementById('template-name').value;
            const category = document.getElementById('template-category').value;
            const description = document.getElementById('template-description').value;
            const baseIVR = document.getElementById('template-base-ivr').value;

            if (!name) {
                alert('Please enter a template name');
                return;
            }

            // Save to localStorage for now (future: save to database)
            const template = {
                id: 'custom-' + Date.now(),
                name: name,
                category: category,
                description: description,
                base_ivr_id: baseIVR,
                type: 'custom',
                created_at: new Date().toISOString()
            };

            let templates = JSON.parse(localStorage.getItem('custom_ivr_templates') || '[]');
            templates.push(template);
            localStorage.setItem('custom_ivr_templates', JSON.stringify(templates));

            alert('Custom template saved successfully!');
            closeCreateTemplateModal();
        }

        function loadMyTemplates() {
            const templates = JSON.parse(localStorage.getItem('custom_ivr_templates') || '[]');
            if (templates.length === 0) {
                document.getElementById('templates-list').innerHTML = '<p style="color: #666;">You haven\'t created any custom templates yet. Click "Create Custom Template" to get started.</p>';
            } else {
                displayTemplates(templates);
            }
        }

        function filterTemplates() {
            const category = document.getElementById('template-category-filter').value;
            if (category === 'custom') {
                loadMyTemplates();
            } else {
                loadSystemTemplates();
            }
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>
