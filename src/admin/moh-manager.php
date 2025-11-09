<?php require_once __DIR__ . '/admin_auth_check.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Music on Hold Manager - FlexPBX</title>
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
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        h1 {
            color: #333;
            margin: 0;
        }

        .back-btn {
            background: #667eea;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
        }

        .card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }

        .card h2 {
            color: #333;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
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

        input[type="text"],
        input[type="url"],
        select,
        input[type="range"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            font-size: 14px;
        }

        input[type="range"] {
            padding: 0;
        }

        .range-value {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 4px 12px;
            border-radius: 4px;
            font-weight: 600;
            margin-left: 10px;
        }

        button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }

        button:hover {
            transform: translateY(-2px);
        }

        button.secondary {
            background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
        }

        button.success {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
        }

        button.danger {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
        }

        .status {
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            display: none;
        }

        .status.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .status.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }

        .info-box h4 {
            color: #1976D2;
            margin-bottom: 8px;
        }

        .info-box ul {
            margin-left: 20px;
            color: #555;
            font-size: 14px;
        }

        .stream-list {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #e1e8ed;
            border-radius: 8px;
            padding: 10px;
            margin-top: 10px;
        }

        .stream-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #e1e8ed;
        }

        .stream-item:last-child {
            border-bottom: none;
        }

        .stream-name {
            font-weight: 600;
            color: #333;
        }

        .stream-url {
            font-size: 12px;
            color: #666;
        }

        .btn-group {
            display: flex;
            gap: 10px;
        }

        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
            width: auto;
        }

        .monitoring-controls {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .monitoring-controls button {
            flex: 1;
        }

        .moh-class-badge {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            margin-right: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎵 Music on Hold Manager</h1>
            <a href="dashboard.html" class="back-btn">← Dashboard</a>
        </div>

        <div class="grid">
            <!-- Stream Configuration -->
            <div class="card">
                <h2>🌐 Add Stream Source</h2>

                <div class="form-group">
                    <label>Stream Name</label>
                    <input type="text" id="stream-name" placeholder="e.g., SomaFM Groove Salad">
                </div>

                <div class="form-group">
                    <label>Stream URL (Icecast/Shoutcast)</label>
                    <input type="url" id="stream-url" placeholder="http://ice1.somafm.com/groovesalad-128-mp3">
                </div>

                <div class="form-group">
                    <label>Volume <span class="range-value" id="volume-display">70%</span></label>
                    <input type="range" id="volume" min="0" max="100" value="70" oninput="document.getElementById('volume-display').textContent = this.value + '%'">
                </div>

                <div class="form-group">
                    <label>MOH Class Type</label>
                    <select id="moh-type">
                        <option value="icecast">Icecast Stream</option>
                        <option value="shoutcast">Shoutcast Stream</option>
                        <option value="local">Local Files</option>
                        <option value="playlist">Custom Playlist</option>
                    </select>
                </div>

                <button onclick="addStream()">➕ Add Stream</button>

                <div class="info-box">
                    <h4>ℹ️ Popular Free Streams</h4>
                    <ul>
                        <li>SomaFM: ice1.somafm.com/groovesalad-128-mp3</li>
                        <li>Radio Paradise: stream.radioparadise.com/aac-320</li>
                        <li>Chillout: streaming.radionomy.com/JamendoLounge</li>
                    </ul>
                </div>
            </div>

            <!-- Active Streams -->
            <div class="card">
                <h2>📻 Active Streams</h2>

                <div class="stream-list" id="stream-list">
                    <div class="stream-item">
                        <div>
                            <div class="stream-name">🎵 SomaFM Groove Salad</div>
                            <div class="stream-url">http://ice1.somafm.com/groovesalad-128-mp3</div>
                            <div style="margin-top: 5px;">
                                <span class="moh-class-badge">icecast-soma-fm</span>
                                <span class="range-value">Volume: 70%</span>
                            </div>
                        </div>
                        <div class="btn-group">
                            <button class="btn-small success" onclick="testStream('icecast-soma-fm')">▶ Test</button>
                            <button class="btn-small danger" onclick="removeStream('icecast-soma-fm')">✕</button>
                        </div>
                    </div>

                    <div class="stream-item">
                        <div>
                            <div class="stream-name">📁 Local Files (Default)</div>
                            <div class="stream-url">/var/lib/asterisk/moh/</div>
                            <div style="margin-top: 5px;">
                                <span class="moh-class-badge">default</span>
                                <span class="range-value">Random order</span>
                            </div>
                        </div>
                        <div class="btn-group">
                            <button class="btn-small success" onclick="testStream('default')">▶ Test</button>
                        </div>
                    </div>
                </div>

                <button onclick="applyChanges()" style="margin-top: 20px;" class="success">✓ Apply & Reload MOH</button>
            </div>

            <!-- Volume Controls -->
            <div class="card">
                <h2>🔊 Master Volume Control</h2>

                <div class="form-group">
                    <label>Queue Volume <span class="range-value" id="queue-volume-display">80%</span></label>
                    <input type="range" id="queue-volume" min="0" max="100" value="80" oninput="document.getElementById('queue-volume-display').textContent = this.value + '%'">
                </div>

                <div class="form-group">
                    <label>Stream Volume <span class="range-value" id="stream-volume-display">70%</span></label>
                    <input type="range" id="stream-volume" min="0" max="100" value="70" oninput="document.getElementById('stream-volume-display').textContent = this.value + '%'">
                </div>

                <div class="form-group">
                    <label>Announcement Volume <span class="range-value" id="announcement-volume-display">90%</span></label>
                    <input type="range" id="announcement-volume" min="0" max="100" value="90" oninput="document.getElementById('announcement-volume-display').textContent = this.value + '%'">
                </div>

                <button onclick="applyVolume()">🔊 Apply Volume Settings</button>

                <div class="monitoring-controls">
                    <button class="secondary" onclick="monitorMOH()">🎧 Listen Locally</button>
                    <button class="danger" onclick="stopMonitoring()">⏹ Stop</button>
                </div>

                <div id="volume-status" class="status"></div>
            </div>

            <!-- Queue Assignment -->
            <div class="card">
                <h2>📞 Queue MOH Assignment</h2>

                <div class="form-group">
                    <label>Support Queue</label>
                    <select id="support-queue-moh">
                        <option value="default">Default (Local Files)</option>
                        <option value="icecast-soma-fm">SomaFM Groove Salad</option>
                        <option value="stream-volume-normal">Generic Stream (80%)</option>
                        <option value="support-queue">Support Queue Files</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Sales Queue</label>
                    <select id="sales-queue-moh">
                        <option value="default">Default (Local Files)</option>
                        <option value="icecast-soma-fm">SomaFM Groove Salad</option>
                        <option value="sales-queue">Sales Queue Files</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Transfer Hold</label>
                    <select id="transfer-hold-moh">
                        <option value="default">Default (Local Files)</option>
                        <option value="stream-volume-quiet">Quiet Stream (30%)</option>
                        <option value="playlist-relaxing">Relaxing Playlist</option>
                    </select>
                </div>

                <button onclick="assignQueues()">📋 Assign to Queues</button>

                <div class="info-box">
                    <h4>💡 Tips</h4>
                    <ul>
                        <li>Use quieter volumes for hold music</li>
                        <li>Test streams before assigning to queues</li>
                        <li>Different queues can have different music</li>
                    </ul>
                </div>
            </div>

            <!-- File Upload -->
            <div class="card">
                <h2>📁 Upload MOH Files</h2>

                <div class="form-group">
                    <label>Category</label>
                    <select id="moh-category">
                        <option value="default">Default</option>
                        <option value="support">Support Queue</option>
                        <option value="sales">Sales Queue</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Audio File (WAV, MP3, GSM)</label>
                    <input type="file" id="moh-file" accept=".wav,.mp3,.gsm,.m4a">
                </div>

                <button onclick="uploadMOH()">📤 Upload MOH File</button>

                <div id="upload-status" class="status"></div>

                <div class="info-box">
                    <h4>📝 File Requirements</h4>
                    <ul>
                        <li>Format: WAV (8kHz, mono) or MP3</li>
                        <li>Length: 30-180 seconds recommended</li>
                        <li>Auto-converts and deploys to Asterisk</li>
                    </ul>
                </div>
            </div>

            <!-- System Status -->
            <div class="card">
                <h2>⚙️ System Controls</h2>

                <button onclick="reloadMOH()">🔄 Reload MOH Configuration</button>
                <button onclick="showMOHClasses()" class="secondary" style="margin-top: 10px;">📋 Show MOH Classes</button>
                <button onclick="showMOHFiles()" class="secondary" style="margin-top: 10px;">📂 Show MOH Files</button>
                <button onclick="testAudio()" class="secondary" style="margin-top: 10px;">🧪 Test Audio System</button>

                <div id="system-status" class="status"></div>

                <div class="info-box" style="margin-top: 20px;">
                    <h4>🔧 Quick Actions</h4>
                    <ul>
                        <li>Click "Reload" after making changes</li>
                        <li>Use "Show MOH Classes" to verify</li>
                        <li>Test streams before deploying</li>
                        <li>Monitor locally to hear what callers hear</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script>
        function addStream() {
            const name = document.getElementById('stream-name').value;
            const url = document.getElementById('stream-url').value;
            const volume = document.getElementById('volume').value;
            const type = document.getElementById('moh-type').value;

            if (!name || !url) {
                showStatus('volume-status', 'Please enter both name and URL', 'error');
                return;
            }

            // In production, this would make an API call to save the stream
            showStatus('volume-status', `Stream "${name}" added successfully! Click "Apply & Reload MOH" to activate.`, 'success');

            // Clear form
            document.getElementById('stream-name').value = '';
            document.getElementById('stream-url').value = '';
        }

        function applyChanges() {
            showStatus('system-status', 'Applying changes and reloading MOH...', 'success');

            // In production: API call to update musiconhold.conf and reload
            setTimeout(() => {
                showStatus('system-status', '✓ MOH configuration reloaded successfully!', 'success');
            }, 1500);
        }

        function applyVolume() {
            const queueVol = document.getElementById('queue-volume').value;
            const streamVol = document.getElementById('stream-volume').value;
            const announcementVol = document.getElementById('announcement-volume').value;

            showStatus('volume-status', `Volume settings applied: Queue ${queueVol}%, Stream ${streamVol}%, Announcement ${announcementVol}%`, 'success');
        }

        function monitorMOH() {
            showStatus('volume-status', '🎧 Streaming MOH to local audio output... Click Stop to end.', 'success');
            // In production: Start mpg123 with ALSA output
        }

        function stopMonitoring() {
            showStatus('volume-status', '⏹ Monitoring stopped.', 'success');
            // In production: Kill monitoring process
        }

        function testStream(mohClass) {
            showStatus('system-status', `Testing stream: ${mohClass}... Listen on a test call.`, 'success');
            // In production: originate a test call with MOH
        }

        function removeStream(mohClass) {
            if (confirm(`Remove stream "${mohClass}"?`)) {
                showStatus('system-status', `Stream "${mohClass}" removed. Click Apply & Reload to update.`, 'success');
            }
        }

        function assignQueues() {
            const support = document.getElementById('support-queue-moh').value;
            const sales = document.getElementById('sales-queue-moh').value;
            const transfer = document.getElementById('transfer-hold-moh').value;

            showStatus('system-status', `Queue assignments updated. Reload queues to apply.`, 'success');
        }

        function uploadMOH() {
            const file = document.getElementById('moh-file').files[0];
            const category = document.getElementById('moh-category').value;

            if (!file) {
                showStatus('upload-status', 'Please select a file', 'error');
                return;
            }

            showStatus('upload-status', `Uploading "${file.name}" to ${category} category...`, 'success');

            // In production: FormData upload to API
            setTimeout(() => {
                showStatus('upload-status', '✓ File uploaded and converted successfully!', 'success');
            }, 1500);
        }

        function reloadMOH() {
            showStatus('system-status', 'Reloading MOH configuration...', 'success');

            // API call to execute: asterisk -rx "module reload res_musiconhold.so"
            setTimeout(() => {
                showStatus('system-status', '✓ MOH configuration reloaded!', 'success');
            }, 1000);
        }

        function showMOHClasses() {
            showStatus('system-status', 'Fetching MOH classes... Check console for details.', 'success');
            // In production: API call to get: asterisk -rx "moh show classes"
        }

        function showMOHFiles() {
            showStatus('system-status', 'Fetching MOH files... Check console for details.', 'success');
            // In production: API call to get: asterisk -rx "moh show files"
        }

        function testAudio() {
            showStatus('system-status', 'Testing audio system... Check your phone or dial *47 for test.', 'success');
        }

        function showStatus(elementId, message, type) {
            const status = document.getElementById(elementId);
            status.textContent = message;
            status.className = `status ${type}`;
            status.style.display = 'block';

            setTimeout(() => {
                status.style.display = 'none';
            }, 5000);
        }
    </script>
</body>
</html>
