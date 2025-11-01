<?php require_once __DIR__ . '/admin_auth_check.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Call Recordings - FlexPBX Admin</title>
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
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            margin-bottom: 25px;
        }

        .header h1 {
            color: #667eea;
            font-size: 28px;
            margin-bottom: 5px;
        }

        .header p {
            color: #666;
            font-size: 14px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .stat-card h3 {
            color: #999;
            font-size: 12px;
            text-transform: uppercase;
            margin-bottom: 10px;
        }

        .stat-card .value {
            color: #333;
            font-size: 24px;
            font-weight: bold;
        }

        .controls {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 25px;
        }

        .control-group {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }

        .control-group label {
            font-weight: 600;
            color: #333;
        }

        .control-group select,
        .control-group input {
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #f0f0f0;
            color: #333;
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-success {
            background: #10b981;
            color: white;
        }

        .recordings-table {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        th, td {
            padding: 15px;
            text-align: left;
        }

        tbody tr {
            border-bottom: 1px solid #f0f0f0;
        }

        tbody tr:hover {
            background: #f8f9fa;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .action-btn:hover {
            transform: scale(1.05);
        }

        .play-btn {
            background: #667eea;
            color: white;
        }

        .download-btn {
            background: #10b981;
            color: white;
        }

        .delete-btn {
            background: #ef4444;
            color: white;
        }

        .settings-panel {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 25px;
        }

        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .setting-item {
            padding: 15px;
            border: 2px solid #f0f0f0;
            border-radius: 10px;
        }

        .setting-item h4 {
            color: #667eea;
            margin-bottom: 10px;
        }

        .setting-item label {
            display: block;
            margin: 10px 0 5px 0;
            font-weight: 600;
            color: #555;
        }

        .audio-player {
            width: 100%;
            margin-top: 10px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .empty-state svg {
            width: 80px;
            height: 80px;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .loading {
            text-align: center;
            padding: 40px;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📞 Call Recordings</h1>
            <p>Manage and review call recordings with flexible configuration</p>
        </div>

        <div class="stats-grid" id="stats-grid">
            <div class="stat-card">
                <h3>Total Recordings</h3>
                <div class="value" id="total-recordings">-</div>
            </div>
            <div class="stat-card">
                <h3>Storage Used</h3>
                <div class="value" id="storage-used">-</div>
            </div>
            <div class="stat-card">
                <h3>Total Duration</h3>
                <div class="value" id="total-duration">-</div>
            </div>
        </div>

        <div class="controls">
            <div class="control-group">
                <label>Extension:</label>
                <select id="filter-extension">
                    <option value="">All Extensions</option>
                    <option value="2000">2000 - Admin</option>
                    <option value="2001">2001 - Test User</option>
                    <option value="2002">2002 - Demo</option>
                    <option value="2003">2003 - Support</option>
                    <option value="2006">2006 - Walter Harper</option>
                </select>

                <button class="btn btn-primary" onclick="loadRecordings()">🔄 Refresh</button>
                <button class="btn btn-secondary" onclick="toggleSettings()">⚙️ Settings</button>
            </div>
        </div>

        <div class="settings-panel" id="settings-panel" style="display: none;">
            <h2>Recording Settings</h2>
            <div class="settings-grid">
                <div class="setting-item">
                    <h4>Global Settings</h4>
                    <label>Recording Mode:</label>
                    <select id="global-mode">
                        <option value="auto">Auto (Record All)</option>
                        <option value="manual">Manual (User Triggered)</option>
                        <option value="off">Off (No Recording)</option>
                    </select>

                    <label>Minimum Duration (seconds):</label>
                    <input type="number" id="min-duration" min="1" max="60" value="5">

                    <button class="btn btn-success" style="margin-top: 15px;" onclick="saveGlobalSettings()">Save Global Settings</button>
                </div>

                <div class="setting-item">
                    <h4>Extension-Specific Settings</h4>
                    <label>Extension:</label>
                    <select id="ext-select">
                        <option value="2000">2000 - Admin</option>
                        <option value="2001">2001 - Test User</option>
                        <option value="2002">2002 - Demo</option>
                        <option value="2003">2003 - Support</option>
                        <option value="2006">2006 - Walter Harper</option>
                    </select>

                    <label>Recording Mode:</label>
                    <select id="ext-mode">
                        <option value="auto">Auto (Record All)</option>
                        <option value="manual">Manual</option>
                        <option value="off">Off</option>
                    </select>

                    <button class="btn btn-success" style="margin-top: 15px;" onclick="saveExtensionSettings()">Save Extension Settings</button>
                </div>
            </div>
        </div>

        <div class="recordings-table">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Time</th>
                        <th>From</th>
                        <th>To</th>
                        <th>Duration</th>
                        <th>Size</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="recordings-tbody">
                    <tr>
                        <td colspan="7" class="loading">Loading recordings...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <audio id="audio-player" class="audio-player" controls style="display: none;"></audio>

    <script>
        let currentPage = 0;
        const pageSize = 50;

        async function loadStats() {
            try {
                const response = await fetch('/api/recordings.php?path=stats');
                const data = await response.json();

                if (data.success) {
                    document.getElementById('total-recordings').textContent = data.total_recordings || 0;
                    document.getElementById('storage-used').textContent = data.total_size_formatted || '0 B';
                    document.getElementById('total-duration').textContent = data.total_duration_formatted || '0s';
                }
            } catch (error) {
                console.error('Error loading stats:', error);
            }
        }

        async function loadRecordings() {
            const tbody = document.getElementById('recordings-tbody');
            tbody.innerHTML = '<tr><td colspan="7" class="loading">Loading recordings...</td></tr>';

            const extension = document.getElementById('filter-extension').value;

            try {
                const response = await fetch('/api/recordings.php?path=list', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        extension: extension || null,
                        limit: pageSize,
                        offset: currentPage * pageSize
                    })
                });

                const data = await response.json();

                if (data.success && data.recordings.length > 0) {
                    tbody.innerHTML = data.recordings.map(rec => `
                        <tr>
                            <td>${rec.date}</td>
                            <td>${rec.time}</td>
                            <td>${rec.source}</td>
                            <td>${rec.destination}</td>
                            <td>${rec.duration}s</td>
                            <td>${rec.size_formatted}</td>
                            <td>
                                <div class="action-buttons">
                                    <button class="action-btn play-btn" onclick="playRecording('${rec.filename}')">▶️ Play</button>
                                    <button class="action-btn download-btn" onclick="downloadRecording('${rec.filename}')">⬇️</button>
                                    <button class="action-btn delete-btn" onclick="deleteRecording('${rec.filename}')">🗑️</button>
                                </div>
                            </td>
                        </tr>
                    `).join('');
                } else {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="7" class="empty-state">
                                <div>📭 No recordings found</div>
                            </td>
                        </tr>
                    `;
                }
            } catch (error) {
                console.error('Error loading recordings:', error);
                tbody.innerHTML = '<tr><td colspan="7" class="empty-state">Error loading recordings</td></tr>';
            }
        }

        function playRecording(filename) {
            const player = document.getElementById('audio-player');
            player.src = `/api/recordings.php?path=download&filename=${encodeURIComponent(filename)}`;
            player.style.display = 'block';
            player.play();
        }

        function downloadRecording(filename) {
            window.location.href = `/api/recordings.php?path=download&filename=${encodeURIComponent(filename)}`;
        }

        async function deleteRecording(filename) {
            if (!confirm(`Delete recording ${filename}?`)) return;

            try {
                const response = await fetch('/api/recordings.php?path=delete', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ filename })
                });

                const data = await response.json();
                if (data.success) {
                    alert('Recording deleted successfully');
                    loadRecordings();
                    loadStats();
                } else {
                    alert('Error deleting recording: ' + data.message);
                }
            } catch (error) {
                console.error('Error deleting recording:', error);
                alert('Error deleting recording');
            }
        }

        function toggleSettings() {
            const panel = document.getElementById('settings-panel');
            panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
            if (panel.style.display === 'block') {
                loadGlobalSettings();
            }
        }

        async function loadGlobalSettings() {
            try {
                const response = await fetch('/api/recordings.php?path=config');
                const data = await response.json();

                if (data.success && data.global_settings) {
                    document.getElementById('global-mode').value = data.global_settings.default_mode || 'auto';
                    document.getElementById('min-duration').value = data.global_settings.min_duration_seconds || 5;
                }
            } catch (error) {
                console.error('Error loading settings:', error);
            }
        }

        async function saveGlobalSettings() {
            const settings = {
                global_settings: {
                    default_mode: document.getElementById('global-mode').value,
                    min_duration_seconds: parseInt(document.getElementById('min-duration').value)
                }
            };

            try {
                const response = await fetch('/api/recordings.php?path=config', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(settings)
                });

                const data = await response.json();
                if (data.success) {
                    alert('✅ Global settings saved successfully');
                } else {
                    alert('❌ Error saving settings: ' + data.message);
                }
            } catch (error) {
                console.error('Error saving settings:', error);
                alert('❌ Error saving settings');
            }
        }

        async function saveExtensionSettings() {
            const extension = document.getElementById('ext-select').value;
            const settings = {
                extension: extension,
                settings: {
                    mode: document.getElementById('ext-mode').value
                }
            };

            try {
                const response = await fetch('/api/recordings.php?path=extension_config', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(settings)
                });

                const data = await response.json();
                if (data.success) {
                    alert(`✅ Settings saved for extension ${extension}`);
                } else {
                    alert('❌ Error saving settings: ' + data.message);
                }
            } catch (error) {
                console.error('Error saving settings:', error);
                alert('❌ Error saving settings');
            }
        }

        // Load data on page load
        window.addEventListener('DOMContentLoaded', () => {
            loadStats();
            loadRecordings();
        });
    </script>
</body>
</html>
