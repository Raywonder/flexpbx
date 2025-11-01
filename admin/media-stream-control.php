<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Media Stream Control - FlexPBX Admin</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            padding: 20px;
            min-height: 100vh;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header {
            background: rgba(255, 255, 255, 0.95);
            padding: 20px 30px;
            border-radius: 15px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .header h1 {
            color: #1e3c72;
            font-size: 28px;
            margin-bottom: 5px;
        }

        .header p {
            color: #666;
            font-size: 14px;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .card h2 {
            color: #1e3c72;
            font-size: 20px;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e0e0e0;
        }

        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
            animation: pulse 2s infinite;
        }

        .status-indicator.online {
            background: #4caf50;
        }

        .status-indicator.offline {
            background: #f44336;
            animation: none;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .stat-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .stat-row:last-child {
            border-bottom: none;
        }

        .stat-label {
            color: #666;
            font-size: 14px;
        }

        .stat-value {
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }

        .button-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 10px;
            margin-top: 15px;
        }

        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .btn-primary {
            background: #4caf50;
            color: white;
        }

        .btn-danger {
            background: #f44336;
            color: white;
        }

        .btn-warning {
            background: #ff9800;
            color: white;
        }

        .btn-info {
            background: #2196f3;
            color: white;
        }

        .btn-secondary {
            background: #757575;
            color: white;
        }

        .now-playing {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin-top: 15px;
        }

        .now-playing h3 {
            font-size: 16px;
            margin-bottom: 10px;
            opacity: 0.9;
        }

        .now-playing .track-name {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .now-playing .track-type {
            font-size: 14px;
            opacity: 0.8;
        }

        .queue-item {
            background: #f5f5f5;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 10px;
            border-left: 4px solid #2196f3;
        }

        .queue-item-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }

        .queue-item-details {
            font-size: 13px;
            color: #666;
        }

        .log-container {
            background: #1e1e1e;
            color: #00ff00;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            padding: 15px;
            border-radius: 8px;
            max-height: 400px;
            overflow-y: auto;
            line-height: 1.5;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #666;
            font-size: 14px;
            font-weight: 600;
        }

        .form-group select,
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }

        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 14px;
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

        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #2196f3;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .hidden {
            display: none;
        }

        .back-link {
            color: #2196f3;
            text-decoration: none;
            font-size: 14px;
            display: inline-block;
            margin-bottom: 15px;
        }

        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" class="back-link">← Back to Dashboard</a>

        <div class="header">
            <h1>🎵 Media Stream Control Center</h1>
            <p>Manage Icecast and Jellyfin streaming services</p>
        </div>

        <div id="alert-container"></div>

        <!-- Status Grid -->
        <div class="grid">
            <!-- Icecast Status -->
            <div class="card">
                <h2><span class="status-indicator" id="icecast-status"></span> Icecast Server</h2>
                <div class="stat-row">
                    <span class="stat-label">Status:</span>
                    <span class="stat-value" id="icecast-server-status">Checking...</span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Listeners:</span>
                    <span class="stat-value" id="icecast-listeners">-</span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Stream Bitrate:</span>
                    <span class="stat-value" id="icecast-bitrate">-</span>
                </div>
                <div class="button-group">
                    <button class="btn btn-warning" onclick="restartIcecast()">Restart Server</button>
                </div>
            </div>

            <!-- Jellyfin Status -->
            <div class="card">
                <h2><span class="status-indicator" id="jellyfin-status"></span> Jellyfin Server</h2>
                <div class="stat-row">
                    <span class="stat-label">Status:</span>
                    <span class="stat-value" id="jellyfin-server-status">Checking...</span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Version:</span>
                    <span class="stat-value" id="jellyfin-version">-</span>
                </div>
                <div class="button-group">
                    <button class="btn btn-warning" onclick="restartJellyfin()">Restart Server</button>
                </div>
            </div>

            <!-- Stream Control -->
            <div class="card">
                <h2><span class="status-indicator" id="stream-status"></span> Stream Control</h2>
                <div class="stat-row">
                    <span class="stat-label">Active Processes:</span>
                    <span class="stat-value" id="stream-processes">0</span>
                </div>
                <div class="button-group">
                    <button class="btn btn-primary" onclick="startStream()">▶️ Start</button>
                    <button class="btn btn-danger" onclick="stopStream()">⏹️ Stop</button>
                    <button class="btn btn-warning" onclick="restartStream()">🔄 Restart</button>
                    <button class="btn btn-info" onclick="skipTrack()">⏭️ Skip</button>
                </div>
            </div>

            <!-- Media Library Stats -->
            <div class="card">
                <h2>📚 Media Library</h2>
                <div class="stat-row">
                    <span class="stat-label">Music Tracks:</span>
                    <span class="stat-value" id="lib-music">-</span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">TV Episodes:</span>
                    <span class="stat-value" id="lib-tv">-</span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Movies:</span>
                    <span class="stat-value" id="lib-movies">-</span>
                </div>
                <div class="stat-row">
                    <span class="stat-label">Audiobooks:</span>
                    <span class="stat-value" id="lib-audiobooks">-</span>
                </div>
                <div class="button-group">
                    <button class="btn btn-info" onclick="scanMedia()">Scan Media</button>
                    <button class="btn btn-secondary" onclick="convertWAV()">Convert WAV</button>
                </div>
            </div>
        </div>

        <!-- Now Playing -->
        <div class="card">
            <h2>🎧 Now Playing</h2>
            <div id="now-playing-container">
                <div class="spinner"></div>
            </div>
        </div>

        <!-- Queue Management -->
        <div class="grid">
            <div class="card">
                <h2>📋 Stream Queue</h2>
                <div id="queue-container">
                    <div class="spinner"></div>
                </div>
                <div class="button-group">
                    <button class="btn btn-danger" onclick="clearQueue()">Clear Queue</button>
                </div>
            </div>

            <!-- Add to Queue -->
            <div class="card">
                <h2>➕ Add to Queue</h2>
                <div class="form-group">
                    <label>Content Type:</label>
                    <select id="queue-type" onchange="toggleQueueFields()">
                        <option value="music">Music</option>
                        <option value="tv">TV Show</option>
                        <option value="movie">Movie</option>
                        <option value="doctor-who">Audio Drama</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Count:</label>
                    <input type="number" id="queue-count" value="1" min="1" max="20">
                </div>
                <div class="form-group">
                    <label>Title (Optional):</label>
                    <input type="text" id="queue-title" placeholder="e.g., Random Music">
                </div>
                <button class="btn btn-primary" onclick="addToQueue()" style="width: 100%">Add to Queue</button>
            </div>
        </div>

        <!-- Stream Log -->
        <div class="card">
            <h2>📋 Stream Log (Last 50 lines)</h2>
            <div id="log-container" class="log-container">
                Loading log...
            </div>
            <div class="button-group">
                <button class="btn btn-info" onclick="refreshLog()">Refresh Log</button>
            </div>
        </div>
    </div>

    <script>
        let refreshInterval = null;

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            refreshStatus();
            refreshInterval = setInterval(refreshStatus, 5000); // Refresh every 5 seconds
        });

        function showAlert(message, type = 'success') {
            const alertContainer = document.getElementById('alert-container');
            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;
            alert.textContent = message;
            alertContainer.appendChild(alert);

            setTimeout(() => {
                alert.remove();
            }, 5000);
        }

        async function apiCall(action, params = {}) {
            try {
                const formData = new FormData();
                formData.append('action', action);
                for (const [key, value] of Object.entries(params)) {
                    formData.append(key, value);
                }

                const response = await fetch('../api/media-stream-manager.php', {
                    method: 'POST',
                    body: formData
                });

                return await response.json();
            } catch (error) {
                console.error('API call failed:', error);
                return { success: false, message: 'Network error' };
            }
        }

        async function refreshStatus() {
            const data = await apiCall('status');

            if (data.success && data.status) {
                const status = data.status;

                // Icecast Status
                if (status.icecast && status.icecast.source) {
                    document.getElementById('icecast-status').className = 'status-indicator online';
                    document.getElementById('icecast-server-status').textContent = 'Online';

                    const source = Array.isArray(status.icecast.source) ? status.icecast.source[0] : status.icecast.source;
                    document.getElementById('icecast-listeners').textContent = source.listeners || '0';
                    document.getElementById('icecast-bitrate').textContent = (source.bitrate || 192) + ' kbps';
                } else {
                    document.getElementById('icecast-status').className = 'status-indicator offline';
                    document.getElementById('icecast-server-status').textContent = 'Offline';
                }

                // Jellyfin Status
                if (status.jellyfin) {
                    document.getElementById('jellyfin-status').className = 'status-indicator online';
                    document.getElementById('jellyfin-server-status').textContent = 'Online';
                    document.getElementById('jellyfin-version').textContent = status.jellyfin.Version || 'Unknown';
                } else {
                    document.getElementById('jellyfin-status').className = 'status-indicator offline';
                    document.getElementById('jellyfin-server-status').textContent = 'Offline';
                }

                // Stream Status
                const processes = status.stream_processes || 0;
                document.getElementById('stream-processes').textContent = processes;
                if (processes > 0) {
                    document.getElementById('stream-status').className = 'status-indicator online';
                } else {
                    document.getElementById('stream-status').className = 'status-indicator offline';
                }

                // Library Stats
                if (status.library_stats) {
                    document.getElementById('lib-music').textContent = status.library_stats.music_tracks.toLocaleString();
                    document.getElementById('lib-tv').textContent = status.library_stats.tv_episodes.toLocaleString();
                    document.getElementById('lib-movies').textContent = status.library_stats.movies.toLocaleString();
                    document.getElementById('lib-audiobooks').textContent = status.library_stats.audiobooks.toLocaleString();
                }

                // Now Playing
                if (status.now_playing) {
                    const np = status.now_playing;
                    document.getElementById('now-playing-container').innerHTML = `
                        <div class="now-playing">
                            <h3>Currently Playing:</h3>
                            <div class="track-name">${np.track || 'Nothing'}</div>
                            <div class="track-type">Type: ${np.type || 'Unknown'} | Started: ${np.started_at || 'Unknown'}</div>
                        </div>
                    `;
                } else {
                    document.getElementById('now-playing-container').innerHTML = `
                        <div class="now-playing">
                            <div class="track-name">No track playing</div>
                        </div>
                    `;
                }

                // Queue
                if (status.queue && status.queue.length > 0) {
                    let queueHTML = '';
                    status.queue.forEach((item, index) => {
                        queueHTML += `
                            <div class="queue-item">
                                <div class="queue-item-title">#${index + 1} - ${item.title || item.type}</div>
                                <div class="queue-item-details">Type: ${item.type} | Count: ${item.count || 1}</div>
                            </div>
                        `;
                    });
                    document.getElementById('queue-container').innerHTML = queueHTML;
                } else {
                    document.getElementById('queue-container').innerHTML = '<p style="color: #999; text-align: center; padding: 20px;">Queue is empty</p>';
                }
            }
        }

        async function refreshLog() {
            const data = await apiCall('get_log', { lines: 50 });
            if (data.success) {
                document.getElementById('log-container').textContent = data.log || 'No log entries';
            }
        }

        // Auto-refresh log initially
        setTimeout(refreshLog, 1000);

        async function startStream() {
            showAlert('Starting stream...', 'success');
            const data = await apiCall('start_stream');
            showAlert(data.message, data.success ? 'success' : 'error');
            setTimeout(refreshStatus, 2000);
        }

        async function stopStream() {
            if (!confirm('Are you sure you want to stop the stream?')) return;
            showAlert('Stopping stream...', 'success');
            const data = await apiCall('stop_stream');
            showAlert(data.message, data.success ? 'success' : 'error');
            setTimeout(refreshStatus, 2000);
        }

        async function restartStream() {
            showAlert('Restarting stream...', 'success');
            const data = await apiCall('restart_stream');
            showAlert(data.message, data.success ? 'success' : 'error');
            setTimeout(refreshStatus, 3000);
        }

        async function skipTrack() {
            showAlert('Skipping to next track...', 'success');
            const data = await apiCall('skip_track');
            showAlert(data.message, data.success ? 'success' : 'error');
            setTimeout(refreshStatus, 2000);
        }

        async function restartIcecast() {
            if (!confirm('Are you sure you want to restart Icecast? This will disconnect all listeners.')) return;
            showAlert('Restarting Icecast...', 'success');
            const data = await apiCall('restart_icecast');
            showAlert(data.message, data.success ? 'success' : 'error');
            setTimeout(refreshStatus, 5000);
        }

        async function restartJellyfin() {
            if (!confirm('Are you sure you want to restart Jellyfin?')) return;
            showAlert('Restarting Jellyfin...', 'success');
            const data = await apiCall('restart_jellyfin');
            showAlert(data.message, data.success ? 'success' : 'error');
            setTimeout(refreshStatus, 10000);
        }

        async function scanMedia() {
            showAlert('Starting media scan...', 'success');
            const data = await apiCall('scan_media');
            showAlert('Media scan complete. Check logs for details.', data.success ? 'success' : 'error');
        }

        async function convertWAV() {
            if (!confirm('This will convert all WAV files to MP3 in the background. Continue?')) return;
            showAlert('Starting WAV conversion...', 'success');
            const data = await apiCall('convert_wav');
            showAlert(data.message, data.success ? 'success' : 'error');
        }

        async function addToQueue() {
            const type = document.getElementById('queue-type').value;
            const count = document.getElementById('queue-count').value;
            const title = document.getElementById('queue-title').value;

            const data = await apiCall('add_to_queue', { type, count, title });
            showAlert(data.message, data.success ? 'success' : 'error');

            if (data.success) {
                document.getElementById('queue-title').value = '';
                document.getElementById('queue-count').value = '1';
                setTimeout(refreshStatus, 1000);
            }
        }

        async function clearQueue() {
            if (!confirm('Are you sure you want to clear the entire queue?')) return;
            const data = await apiCall('clear_queue');
            showAlert(data.message, data.success ? 'success' : 'error');
            setTimeout(refreshStatus, 1000);
        }

        function toggleQueueFields() {
            // Could add custom fields based on type in the future
        }
    </script>
</body>
</html>
