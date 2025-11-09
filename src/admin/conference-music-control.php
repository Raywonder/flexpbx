<?php require_once __DIR__ . '/admin_auth_check.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conference Music Control - FlexPBX Admin</title>
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
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .header h1 {
            color: #333;
            font-size: 32px;
            margin-bottom: 10px;
        }

        .subtitle {
            color: #666;
            font-size: 16px;
        }

        .support-callout {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            margin-top: 20px;
            border-radius: 6px;
        }

        .phone {
            font-size: 20px;
            font-weight: bold;
            color: #1976d2;
            margin: 10px 0;
        }

        .main-grid {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .sidebar {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            height: fit-content;
        }

        .sidebar h2 {
            font-size: 18px;
            margin-bottom: 15px;
            color: #333;
        }

        .room-list {
            max-height: 600px;
            overflow-y: auto;
        }

        .room-item {
            padding: 12px;
            margin-bottom: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .room-item:hover {
            border-color: #667eea;
            background: #f5f5ff;
        }

        .room-item.active {
            border-color: #667eea;
            background: #667eea;
            color: white;
        }

        .room-name {
            font-weight: bold;
            font-size: 16px;
            margin-bottom: 5px;
        }

        .room-status {
            font-size: 13px;
            color: #666;
        }

        .room-item.active .room-status {
            color: rgba(255,255,255,0.9);
        }

        .music-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
            margin-left: 5px;
        }

        .music-playing {
            background: #4caf50;
            color: white;
        }

        .music-stopped {
            background: #f44336;
            color: white;
        }

        .content {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .control-panel {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .control-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 12px;
            text-align: center;
        }

        .control-card h3 {
            font-size: 18px;
            margin-bottom: 15px;
        }

        .control-value {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(102, 126, 234, 0.4);
        }

        .btn-success {
            background: #4caf50;
            color: white;
        }

        .btn-success:hover {
            background: #45a049;
        }

        .btn-danger {
            background: #f44336;
            color: white;
        }

        .btn-danger:hover {
            background: #da190b;
        }

        .btn-block {
            width: 100%;
            margin-top: 10px;
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

        .form-control {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .checkbox-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
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

        .participants-list {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
        }

        .participant-item {
            padding: 10px;
            background: white;
            border-radius: 6px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .loading {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }

        .status-active {
            background: #4caf50;
        }

        .status-inactive {
            background: #f44336;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: white;
            text-decoration: none;
            font-weight: 600;
        }

        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.html" class="back-link">← Back to Dashboard</a>

        <div class="header">
            <h1>🎵 Conference Music Control</h1>
            <p class="subtitle">A system you can help build to be the best it can be. Accessible by default.</p>

            <div class="support-callout">
                <div style="font-weight: 600; color: #1976d2;">📞 Support Hotline</div>
                <div class="phone">
                    <a href="tel:+13023139555" style="color: #1976d2; text-decoration: none;">(302) 313-9555</a>
                </div>
                <div style="font-size: 14px; color: #666;">Call our support team for immediate assistance</div>
            </div>
        </div>

        <div class="main-grid">
            <!-- Sidebar - Room List -->
            <div class="sidebar">
                <h2>Active Conference Rooms</h2>
                <button class="btn btn-primary btn-block" onclick="refreshRooms()">🔄 Refresh Rooms</button>
                <div id="roomList" class="room-list">
                    <div class="loading">
                        <div class="spinner"></div>
                        Loading rooms...
                    </div>
                </div>
            </div>

            <!-- Main Content - Room Control -->
            <div class="content">
                <div id="noRoomSelected">
                    <h2 style="text-align: center; color: #999; padding: 60px;">
                        ← Select a room from the sidebar to control music
                    </h2>
                </div>

                <div id="roomControl" style="display: none;">
                    <h2 style="margin-bottom: 20px;">
                        Room: <span id="currentRoomName"></span>
                    </h2>

                    <div id="alertContainer"></div>

                    <!-- Control Panel -->
                    <div class="control-panel">
                        <div class="control-card">
                            <h3>Participants</h3>
                            <div class="control-value" id="participantCount">0</div>
                            <div>Currently in room</div>
                        </div>

                        <div class="control-card">
                            <h3>Music Status</h3>
                            <div class="control-value" id="musicStatus">
                                <span class="status-indicator status-inactive"></span>
                                Stopped
                            </div>
                            <div id="musicClassName"></div>
                        </div>

                        <div class="control-card">
                            <h3>Auto-Music</h3>
                            <div class="control-value" id="autoMusicStatus">
                                <span class="status-indicator status-inactive"></span>
                                Disabled
                            </div>
                            <div>When alone in room</div>
                        </div>
                    </div>

                    <!-- Music Controls -->
                    <div class="form-group">
                        <label>Select Music Class</label>
                        <select id="musicClassSelect" class="form-control">
                            <option value="default">Default</option>
                        </select>
                    </div>

                    <div style="display: flex; gap: 15px; margin-bottom: 30px;">
                        <button class="btn btn-success" style="flex: 1;" onclick="startMusic()">
                            ▶️ Start Music
                        </button>
                        <button class="btn btn-danger" style="flex: 1;" onclick="stopMusic()">
                            ⏹️ Stop Music
                        </button>
                    </div>

                    <!-- Auto-Music Settings -->
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="autoMusicCheck" onchange="toggleAutoMusic()">
                            <label for="autoMusicCheck" style="margin: 0;">
                                Enable auto-music when participant is alone
                            </label>
                        </div>
                        <div style="font-size: 13px; color: #666; margin-top: 8px; margin-left: 30px;">
                            Automatically play music when only one person is in the room
                        </div>
                    </div>

                    <!-- Set Default Music Class -->
                    <div class="form-group">
                        <label>Set Default Music Class for This Room</label>
                        <div style="display: flex; gap: 10px;">
                            <select id="defaultMusicSelect" class="form-control">
                                <option value="default">Default</option>
                            </select>
                            <button class="btn btn-primary" onclick="setDefaultMusic()">
                                Save Default
                            </button>
                        </div>
                    </div>

                    <!-- Participants List -->
                    <h3 style="margin-top: 30px; margin-bottom: 15px;">Participants in Room</h3>
                    <div id="participantsList" class="participants-list">
                        <div style="text-align: center; color: #999;">No participants</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const API_BASE = '../api/conference-music.php';
        let currentRoom = null;
        let refreshInterval = null;

        // Load rooms on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadRooms();
            loadMusicClasses();

            // Auto-refresh every 10 seconds
            refreshInterval = setInterval(loadRooms, 10000);
        });

        async function loadRooms() {
            try {
                const response = await fetch(`${API_BASE}?path=list_rooms`);
                const data = await response.json();

                if (data.success) {
                    displayRooms(data.rooms);

                    // Refresh current room if selected
                    if (currentRoom) {
                        loadRoomStatus(currentRoom);
                    }
                }
            } catch (error) {
                console.error('Error loading rooms:', error);
            }
        }

        function displayRooms(rooms) {
            const container = document.getElementById('roomList');

            if (rooms.length === 0) {
                container.innerHTML = '<div style="text-align: center; color: #999; padding: 20px;">No active rooms</div>';
                return;
            }

            let html = '';
            rooms.forEach(room => {
                const isActive = (currentRoom === room.room);
                const musicBadge = room.music_playing
                    ? '<span class="music-badge music-playing">♪ Playing</span>'
                    : '<span class="music-badge music-stopped">Stopped</span>';

                html += `
                    <div class="room-item ${isActive ? 'active' : ''}" onclick="selectRoom('${room.room}')">
                        <div class="room-name">Room ${room.room} ${musicBadge}</div>
                        <div class="room-status">
                            ${room.users} participant${room.users !== 1 ? 's' : ''}
                            ${room.locked ? ' • Locked' : ''}
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;
        }

        async function loadMusicClasses() {
            try {
                const response = await fetch(`${API_BASE}?path=get_music_classes`);
                const data = await response.json();

                if (data.success) {
                    const select1 = document.getElementById('musicClassSelect');
                    const select2 = document.getElementById('defaultMusicSelect');

                    let options = '<option value="default">Default</option>';
                    data.music_classes.forEach(cls => {
                        options += `<option value="${cls.name}">${cls.name} (${cls.type})</option>`;
                    });

                    select1.innerHTML = options;
                    select2.innerHTML = options;
                }
            } catch (error) {
                console.error('Error loading music classes:', error);
            }
        }

        function selectRoom(roomId) {
            currentRoom = roomId;
            document.getElementById('noRoomSelected').style.display = 'none';
            document.getElementById('roomControl').style.display = 'block';
            document.getElementById('currentRoomName').textContent = roomId;

            loadRoomStatus(roomId);
        }

        async function loadRoomStatus(roomId) {
            try {
                const response = await fetch(`${API_BASE}?path=get_room_status&room=${roomId}`);
                const data = await response.json();

                if (data.success) {
                    updateRoomDisplay(data);
                }
            } catch (error) {
                console.error('Error loading room status:', error);
            }
        }

        function updateRoomDisplay(data) {
            // Update participant count
            document.getElementById('participantCount').textContent = data.participant_count;

            // Update music status
            const musicStatusEl = document.getElementById('musicStatus');
            if (data.music_playing) {
                musicStatusEl.innerHTML = '<span class="status-indicator status-active"></span> Playing';
                document.getElementById('musicClassName').textContent = data.music_class;
            } else {
                musicStatusEl.innerHTML = '<span class="status-indicator status-inactive"></span> Stopped';
                document.getElementById('musicClassName').textContent = '';
            }

            // Update auto-music status
            const autoMusicEl = document.getElementById('autoMusicStatus');
            document.getElementById('autoMusicCheck').checked = data.auto_music_enabled;
            if (data.auto_music_enabled) {
                autoMusicEl.innerHTML = '<span class="status-indicator status-active"></span> Enabled';
            } else {
                autoMusicEl.innerHTML = '<span class="status-indicator status-inactive"></span> Disabled';
            }

            // Update participants list
            displayParticipants(data.participants);

            // Set music class selects
            document.getElementById('musicClassSelect').value = data.music_class || 'default';
            document.getElementById('defaultMusicSelect').value = data.music_class || 'default';
        }

        function displayParticipants(participants) {
            const container = document.getElementById('participantsList');

            if (participants.length === 0) {
                container.innerHTML = '<div style="text-align: center; color: #999;">No participants</div>';
                return;
            }

            let html = '';
            participants.forEach((p, index) => {
                html += `
                    <div class="participant-item">
                        <div>
                            <strong>User ${p.user}</strong><br>
                            <span style="font-size: 13px; color: #666;">${p.channel}</span>
                        </div>
                        <div>
                            ${p.muted ? '<span style="color: #f44336;">🔇 Muted</span>' : '<span style="color: #4caf50;">🔊 Unmuted</span>'}
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;
        }

        async function startMusic() {
            if (!currentRoom) return;

            const musicClass = document.getElementById('musicClassSelect').value;

            try {
                const formData = new FormData();
                formData.append('room', currentRoom);
                formData.append('music_class', musicClass);

                const response = await fetch(`${API_BASE}?path=start_music`, {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    showAlert('success', `Music started in room ${currentRoom}`);
                    loadRoomStatus(currentRoom);
                    loadRooms();
                } else {
                    showAlert('error', data.error || 'Failed to start music');
                }
            } catch (error) {
                showAlert('error', 'Error: ' + error.message);
            }
        }

        async function stopMusic() {
            if (!currentRoom) return;

            try {
                const formData = new FormData();
                formData.append('room', currentRoom);

                const response = await fetch(`${API_BASE}?path=stop_music`, {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    showAlert('success', `Music stopped in room ${currentRoom}`);
                    loadRoomStatus(currentRoom);
                    loadRooms();
                } else {
                    showAlert('error', data.error || 'Failed to stop music');
                }
            } catch (error) {
                showAlert('error', 'Error: ' + error.message);
            }
        }

        async function toggleAutoMusic() {
            if (!currentRoom) return;

            const enabled = document.getElementById('autoMusicCheck').checked;

            try {
                const formData = new FormData();
                formData.append('room', currentRoom);
                formData.append('enabled', enabled);

                const response = await fetch(`${API_BASE}?path=toggle_auto_music`, {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    showAlert('success', `Auto-music ${enabled ? 'enabled' : 'disabled'} for room ${currentRoom}`);
                    loadRoomStatus(currentRoom);
                } else {
                    showAlert('error', data.error || 'Failed to toggle auto-music');
                }
            } catch (error) {
                showAlert('error', 'Error: ' + error.message);
            }
        }

        async function setDefaultMusic() {
            if (!currentRoom) return;

            const musicClass = document.getElementById('defaultMusicSelect').value;

            try {
                const formData = new FormData();
                formData.append('room', currentRoom);
                formData.append('music_class', musicClass);

                const response = await fetch(`${API_BASE}?path=set_room_music`, {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    showAlert('success', `Default music class set to "${musicClass}" for room ${currentRoom}`);
                    loadRoomStatus(currentRoom);
                } else {
                    showAlert('error', data.error || 'Failed to set default music');
                }
            } catch (error) {
                showAlert('error', 'Error: ' + error.message);
            }
        }

        function refreshRooms() {
            loadRooms();
            if (currentRoom) {
                loadRoomStatus(currentRoom);
            }
            showAlert('info', 'Refreshed conference rooms');
        }

        function showAlert(type, message) {
            const container = document.getElementById('alertContainer');
            const alertClass = type === 'success' ? 'alert-success' : (type === 'error' ? 'alert-error' : 'alert-info');

            container.innerHTML = `<div class="alert ${alertClass}">${message}</div>`;

            setTimeout(() => {
                container.innerHTML = '';
            }, 5000);
        }
    </script>
</body>
</html>
