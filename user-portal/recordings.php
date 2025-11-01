<?php
/**
 * FlexPBX User Portal - Call Recordings
 * View, play, download, and manage call recordings
 */

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: /user-portal/login.php');
    exit;
}

$extension = $_SESSION['user_extension'] ?? 'Unknown';
$username = $_SESSION['user_username'] ?? $extension;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Call Recordings - FlexPBX User Portal</title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f5f7fa;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
        }

        .header h1 {
            margin: 0 0 0.5rem 0;
            font-size: 2rem;
        }

        .header p {
            margin: 0;
            opacity: 0.9;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .stat-card h3 {
            margin: 0 0 0.5rem 0;
            font-size: 0.9rem;
            color: #666;
            font-weight: 600;
            text-transform: uppercase;
        }

        .stat-card .value {
            font-size: 2rem;
            font-weight: 700;
            color: #667eea;
        }

        .filter-bar {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-bar select {
            padding: 0.6rem 1rem;
            border: 1px solid #ddd;
            border-radius: 6px;
        }

        .filter-bar button {
            padding: 0.6rem 1.5rem;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }

        .filter-bar button:hover {
            background: #5568d3;
        }

        .recordings-grid {
            display: grid;
            gap: 1rem;
        }

        .recording-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 1.5rem;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .recording-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .recording-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .recording-info {
            flex: 1;
        }

        .recording-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
            margin: 0 0 0.5rem 0;
        }

        .recording-meta {
            color: #666;
            font-size: 0.9rem;
        }

        .recording-actions {
            display: flex;
            gap: 0.5rem;
        }

        .action-btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.2s;
        }

        .btn-play {
            background: #4caf50;
            color: white;
        }

        .btn-play:hover {
            background: #45a049;
        }

        .btn-pause {
            background: #ff9800;
            color: white;
        }

        .btn-download {
            background: #2196f3;
            color: white;
        }

        .btn-download:hover {
            background: #0b7dda;
        }

        .btn-delete {
            background: #f44336;
            color: white;
        }

        .btn-delete:hover {
            background: #da190b;
        }

        .audio-player {
            margin-top: 1rem;
            display: none;
        }

        .audio-player.active {
            display: block;
        }

        .player-controls {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
        }

        .waveform {
            width: 100%;
            height: 80px;
            background: #e0e0e0;
            border-radius: 6px;
            margin-bottom: 1rem;
            position: relative;
            overflow: hidden;
        }

        .seek-bar {
            width: 100%;
            height: 6px;
            background: #e0e0e0;
            border-radius: 3px;
            cursor: pointer;
            margin: 1rem 0;
            position: relative;
        }

        .seek-progress {
            height: 100%;
            background: #667eea;
            border-radius: 3px;
            width: 0;
            position: relative;
        }

        .seek-handle {
            position: absolute;
            right: -8px;
            top: 50%;
            transform: translateY(-50%);
            width: 16px;
            height: 16px;
            background: white;
            border: 2px solid #667eea;
            border-radius: 50%;
            cursor: pointer;
        }

        .time-display {
            display: flex;
            justify-content: space-between;
            font-size: 0.9rem;
            color: #666;
            font-family: 'Courier New', monospace;
        }

        .playback-controls {
            display: flex;
            gap: 1rem;
            align-items: center;
            margin-top: 1rem;
        }

        .playback-btn {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            border: none;
            background: #667eea;
            color: white;
            font-size: 1.2rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .playback-btn:hover {
            background: #5568d3;
            transform: scale(1.05);
        }

        .volume-control {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .volume-slider {
            width: 100px;
        }

        .speed-control {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .direction-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .direction-internal {
            background: #fff3e0;
            color: #f57c00;
        }

        .direction-inbound {
            background: #e3f2fd;
            color: #1976d2;
        }

        .direction-outbound {
            background: #f3e5f5;
            color: #7b1fa2;
        }

        .loading {
            text-align: center;
            padding: 3rem;
            color: #666;
        }

        .no-data {
            text-align: center;
            padding: 3rem;
            color: #999;
        }

        .nav-buttons {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn:hover {
            background: #667eea;
            color: white;
        }

        @media (max-width: 768px) {
            .recording-header {
                flex-direction: column;
            }

            .recording-actions {
                margin-top: 1rem;
                width: 100%;
            }

            .action-btn {
                flex: 1;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎙️ Call Recordings</h1>
            <p>Extension <?= htmlspecialchars($extension) ?> - <?= htmlspecialchars($username) ?></p>
        </div>

        <div class="nav-buttons">
            <a href="/user-portal/" class="btn">← Back to Dashboard</a>
            <a href="/user-portal/call-history.php" class="btn">📞 Call History</a>
        </div>

        <!-- Statistics -->
        <div class="stats-grid" id="stats-grid">
            <div class="stat-card">
                <h3>Total Recordings</h3>
                <div class="value" id="stat-total">-</div>
            </div>
            <div class="stat-card">
                <h3>Internal Calls</h3>
                <div class="value" id="stat-internal">-</div>
            </div>
            <div class="stat-card">
                <h3>Inbound Calls</h3>
                <div class="value" id="stat-inbound">-</div>
            </div>
            <div class="stat-card">
                <h3>Outbound Calls</h3>
                <div class="value" id="stat-outbound">-</div>
            </div>
            <div class="stat-card">
                <h3>Total Size</h3>
                <div class="value" id="stat-size" style="font-size: 1.5rem;">-</div>
            </div>
            <div class="stat-card">
                <h3>Total Duration</h3>
                <div class="value" id="stat-duration" style="font-size: 1.5rem;">-</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-bar">
            <select id="filter-type" onchange="applyFilters()">
                <option value="all">All Types</option>
                <option value="internal">Internal Only</option>
                <option value="inbound">Inbound Only</option>
                <option value="outbound">Outbound Only</option>
            </select>
            <button onclick="refreshRecordings()">
                <i class="fas fa-sync-alt"></i> Refresh
            </button>
        </div>

        <!-- Recordings Grid -->
        <div class="recordings-grid" id="recordings-grid">
            <div class="loading">Loading recordings...</div>
        </div>
    </div>

    <script>
        const extension = '<?= addslashes($extension) ?>';
        let allRecordings = [];
        let filteredRecordings = [];
        let currentlyPlaying = null;

        // Load recordings on page load
        loadRecordings();

        async function loadRecordings() {
            try {
                const response = await fetch(`/api/call-history.php?action=list&extension=${extension}`);
                const historyData = await response.json();

                // Get recordings from monitor directories
                const recordings = await fetch(`/api/recordings.php?action=list&extension=${extension}`);
                const recordingsData = await recordings.json();

                if (recordingsData.success) {
                    allRecordings = recordingsData.recordings;
                    filteredRecordings = [...allRecordings];
                    renderRecordings();
                    loadStats();
                } else {
                    document.getElementById('recordings-grid').innerHTML = `
                        <div class="no-data">❌ ${recordingsData.error || 'Failed to load recordings'}</div>
                    `;
                }
            } catch (error) {
                console.error('Failed to load recordings:', error);
                document.getElementById('recordings-grid').innerHTML = `
                    <div class="no-data">❌ Failed to load recordings</div>
                `;
            }
        }

        async function loadStats() {
            try {
                const response = await fetch(`/api/recordings.php?action=stats&extension=${extension}`);
                const data = await response.json();

                if (data.success) {
                    const stats = data.stats;
                    document.getElementById('stat-total').textContent = stats.total_recordings;
                    document.getElementById('stat-internal').textContent = stats.internal_calls;
                    document.getElementById('stat-inbound').textContent = stats.inbound_calls;
                    document.getElementById('stat-outbound').textContent = stats.outbound_calls;
                    document.getElementById('stat-size').textContent = stats.total_size_formatted;
                    document.getElementById('stat-duration').textContent = stats.total_duration_formatted;
                }
            } catch (error) {
                console.error('Failed to load stats:', error);
            }
        }

        function renderRecordings() {
            const grid = document.getElementById('recordings-grid');

            if (filteredRecordings.length === 0) {
                grid.innerHTML = '<div class="no-data">No recordings found</div>';
                return;
            }

            grid.innerHTML = filteredRecordings.map((rec, index) => `
                <div class="recording-card" id="card-${index}">
                    <div class="recording-header">
                        <div class="recording-info">
                            <h3 class="recording-title">
                                <span class="direction-badge direction-${rec.direction}">${rec.direction}</span>
                                ${rec.direction === 'inbound' ? 'From' : 'To'}: ${rec.other_party}
                            </h3>
                            <div class="recording-meta">
                                <i class="far fa-calendar"></i> ${rec.date} |
                                <i class="far fa-clock"></i> ${rec.duration_formatted || formatDuration(rec.duration)} |
                                <i class="far fa-file-audio"></i> ${rec.size_formatted}
                            </div>
                        </div>
                        <div class="recording-actions">
                            <button class="action-btn btn-play" onclick="togglePlay(${index})" id="play-btn-${index}">
                                <i class="fas fa-play"></i> Play
                            </button>
                            <a href="${rec.url}" class="action-btn btn-download" download>
                                <i class="fas fa-download"></i>
                            </a>
                            <button class="action-btn btn-delete" onclick="deleteRecording(${index}, '${rec.filename}', '${rec.type}')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Audio Player -->
                    <div class="audio-player" id="player-${index}">
                        <div class="player-controls">
                            <div class="seek-bar" onclick="seek(event, ${index})">
                                <div class="seek-progress" id="progress-${index}">
                                    <div class="seek-handle"></div>
                                </div>
                            </div>
                            <div class="time-display">
                                <span id="current-time-${index}">0:00</span>
                                <span id="total-time-${index}">0:00</span>
                            </div>
                            <div class="playback-controls">
                                <button class="playback-btn" onclick="togglePlay(${index})">
                                    <i class="fas fa-play" id="play-icon-${index}"></i>
                                </button>
                                <button class="playback-btn" onclick="stop(${index})" style="width: 40px; height: 40px; font-size: 1rem;">
                                    <i class="fas fa-stop"></i>
                                </button>
                                <div class="volume-control">
                                    <i class="fas fa-volume-up"></i>
                                    <input type="range" min="0" max="100" value="100" class="volume-slider" id="volume-${index}" oninput="setVolume(${index})">
                                </div>
                                <div class="speed-control">
                                    <i class="fas fa-tachometer-alt"></i>
                                    <select id="speed-${index}" onchange="setSpeed(${index})">
                                        <option value="0.5">0.5x</option>
                                        <option value="0.75">0.75x</option>
                                        <option value="1" selected>1x</option>
                                        <option value="1.25">1.25x</option>
                                        <option value="1.5">1.5x</option>
                                        <option value="2">2x</option>
                                    </select>
                                </div>
                            </div>
                            <audio id="audio-${index}" src="${rec.stream_url}" preload="metadata" ontimeupdate="updateProgress(${index})" onloadedmetadata="setDuration(${index})"></audio>
                        </div>
                    </div>
                </div>
            `).join('');
        }

        function togglePlay(index) {
            const audio = document.getElementById(`audio-${index}`);
            const player = document.getElementById(`player-${index}`);
            const playBtn = document.getElementById(`play-btn-${index}`);
            const playIcon = document.getElementById(`play-icon-${index}`);

            // Stop any currently playing audio
            if (currentlyPlaying !== null && currentlyPlaying !== index) {
                stop(currentlyPlaying);
            }

            player.classList.add('active');

            if (audio.paused) {
                audio.play();
                playBtn.innerHTML = '<i class="fas fa-pause"></i> Pause';
                playBtn.classList.remove('btn-play');
                playBtn.classList.add('btn-pause');
                playIcon.className = 'fas fa-pause';
                currentlyPlaying = index;
            } else {
                audio.pause();
                playBtn.innerHTML = '<i class="fas fa-play"></i> Play';
                playBtn.classList.remove('btn-pause');
                playBtn.classList.add('btn-play');
                playIcon.className = 'fas fa-play';
                currentlyPlaying = null;
            }
        }

        function stop(index) {
            const audio = document.getElementById(`audio-${index}`);
            const playBtn = document.getElementById(`play-btn-${index}`);
            const playIcon = document.getElementById(`play-icon-${index}`);

            audio.pause();
            audio.currentTime = 0;
            playBtn.innerHTML = '<i class="fas fa-play"></i> Play';
            playBtn.classList.remove('btn-pause');
            playBtn.classList.add('btn-play');
            playIcon.className = 'fas fa-play';
            currentlyPlaying = null;
        }

        function seek(event, index) {
            const seekBar = event.currentTarget;
            const audio = document.getElementById(`audio-${index}`);
            const rect = seekBar.getBoundingClientRect();
            const percent = (event.clientX - rect.left) / rect.width;
            audio.currentTime = percent * audio.duration;
        }

        function updateProgress(index) {
            const audio = document.getElementById(`audio-${index}`);
            const progress = document.getElementById(`progress-${index}`);
            const currentTime = document.getElementById(`current-time-${index}`);

            const percent = (audio.currentTime / audio.duration) * 100;
            progress.style.width = percent + '%';
            currentTime.textContent = formatTime(audio.currentTime);

            // Auto-stop when finished
            if (audio.ended) {
                stop(index);
            }
        }

        function setDuration(index) {
            const audio = document.getElementById(`audio-${index}`);
            const totalTime = document.getElementById(`total-time-${index}`);
            totalTime.textContent = formatTime(audio.duration);
        }

        function setVolume(index) {
            const audio = document.getElementById(`audio-${index}`);
            const volumeSlider = document.getElementById(`volume-${index}`);
            audio.volume = volumeSlider.value / 100;
        }

        function setSpeed(index) {
            const audio = document.getElementById(`audio-${index}`);
            const speedSelect = document.getElementById(`speed-${index}`);
            audio.playbackRate = parseFloat(speedSelect.value);
        }

        async function deleteRecording(index, filename, type) {
            if (!confirm('Are you sure you want to delete this recording? This cannot be undone.')) {
                return;
            }

            try {
                const response = await fetch('/api/recordings.php?action=delete', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ file: filename, type: type })
                });

                const data = await response.json();

                if (data.success) {
                    alert('Recording deleted successfully');
                    refreshRecordings();
                } else {
                    alert('Failed to delete recording: ' + data.error);
                }
            } catch (error) {
                console.error('Failed to delete recording:', error);
                alert('Failed to delete recording');
            }
        }

        function applyFilters() {
            const filterType = document.getElementById('filter-type').value;

            filteredRecordings = allRecordings.filter(rec => {
                if (filterType !== 'all' && rec.type !== filterType) return false;
                return true;
            });

            renderRecordings();
        }

        function refreshRecordings() {
            loadRecordings();
        }

        function formatTime(seconds) {
            if (isNaN(seconds)) return '0:00';
            const mins = Math.floor(seconds / 60);
            const secs = Math.floor(seconds % 60);
            return `${mins}:${secs.toString().padStart(2, '0')}`;
        }

        function formatDuration(seconds) {
            const hours = Math.floor(seconds / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);
            const secs = seconds % 60;

            if (hours > 0) {
                return `${hours}h ${minutes}m`;
            } else if (minutes > 0) {
                return `${minutes}m ${secs}s`;
            } else {
                return `${secs}s`;
            }
        }
    </script>

    <!-- Accessibility Tools -->
    <script src="/js/accessibility.js"></script>
</body>
</html>
