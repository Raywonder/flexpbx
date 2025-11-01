<?php
/**
 * FlexPhone - Web Calling Client
 * Auto-login with session credentials
 */

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: /user-portal/login.php?redirect=' . urlencode('/flexphone/'));
    exit;
}

$user_extension = $_SESSION['user_extension'] ?? null;
$user_username = $_SESSION['user_username'] ?? 'User';

// Load user data to get SIP password
$user_file = '/home/flexpbxuser/users/user_' . $user_extension . '.json';
$sip_password = null;

if (file_exists($user_file)) {
    $user_data = json_decode(file_get_contents($user_file), true);
    $sip_password = $user_data['sip_password'] ?? $user_data['password_plain'] ?? null;
}

// If no SIP password available, generate error
if (!$sip_password) {
    $error_message = "SIP password not available. Please contact administrator.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FlexPhone - Web Calling Client</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .flexphone-container {
            max-width: 500px;
            margin: 50px auto;
        }

        .phone-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }

        .status-bar {
            text-align: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }

        .status-online { background: #28a745; }
        .status-offline { background: #dc3545; }
        .status-calling { background: #ffc107; animation: pulse 1.5s infinite; }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .display {
            background: #1a1a1a;
            color: #00ff00;
            padding: 20px;
            border-radius: 10px;
            font-family: 'Courier New', monospace;
            font-size: 24px;
            text-align: right;
            margin-bottom: 20px;
            min-height: 60px;
            word-break: break-all;
        }

        .keypad {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }

        .key {
            aspect-ratio: 1;
            border: none;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-size: 24px;
            font-weight: 600;
            border-radius: 50%;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .key:hover {
            transform: scale(1.05);
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }

        .key:active {
            transform: scale(0.95);
        }

        .key.backspace {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        }

        .action-buttons {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }

        .action-btn {
            padding: 15px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-call {
            background: #28a745;
            color: white;
        }

        .btn-hangup {
            background: #dc3545;
            color: white;
        }

        .btn-call:hover:not(:disabled) {
            background: #218838;
            transform: translateY(-2px);
        }

        .btn-hangup:hover:not(:disabled) {
            background: #c82333;
            transform: translateY(-2px);
        }

        .action-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .info-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #dee2e6;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .nav-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .nav-btn {
            flex: 1;
            min-width: 120px;
        }

        .alert {
            border-radius: 10px;
            margin-bottom: 20px;
        }

        @media (max-width: 576px) {
            .flexphone-container {
                margin: 20px auto;
            }

            .phone-card {
                padding: 20px;
            }

            .key {
                font-size: 20px;
            }

            .display {
                font-size: 20px;
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="flexphone-container">
        <div class="phone-card">
            <h1 class="text-center mb-3">
                <i class="fas fa-phone me-2"></i>FlexPhone
            </h1>

            <?php if (isset($error_message)): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?= htmlspecialchars($error_message) ?>
            </div>
            <?php endif; ?>

            <div class="status-bar" id="statusBar">
                <span class="status-indicator status-offline" id="statusIndicator"></span>
                <span id="statusText">Connecting...</span>
            </div>

            <div class="display" id="display">Ready</div>

            <div class="keypad">
                <button class="key" onclick="dialDigit('1')">1</button>
                <button class="key" onclick="dialDigit('2')">2</button>
                <button class="key" onclick="dialDigit('3')">3</button>
                <button class="key" onclick="dialDigit('4')">4</button>
                <button class="key" onclick="dialDigit('5')">5</button>
                <button class="key" onclick="dialDigit('6')">6</button>
                <button class="key" onclick="dialDigit('7')">7</button>
                <button class="key" onclick="dialDigit('8')">8</button>
                <button class="key" onclick="dialDigit('9')">9</button>
                <button class="key" onclick="dialDigit('*')">*</button>
                <button class="key" onclick="dialDigit('0')">0</button>
                <button class="key" onclick="dialDigit('#')">#</button>
                <button class="key backspace" onclick="backspace()" style="grid-column: span 3;">
                    <i class="fas fa-backspace"></i> Clear
                </button>
            </div>

            <div class="action-buttons">
                <button class="action-btn btn-call" onclick="makeCall()" id="callBtn">
                    <i class="fas fa-phone me-2"></i>Call
                </button>
                <button class="action-btn btn-hangup" onclick="hangUp()" disabled id="hangupBtn">
                    <i class="fas fa-phone-slash me-2"></i>Hang Up
                </button>
            </div>

            <!-- Audio Device Settings -->
            <div class="info-card" id="audio-settings-card">
                <h6 class="mb-3" style="font-weight: 600; color: #2c3e50;">
                    <i class="fas fa-volume-up me-2"></i>Audio Devices
                </h6>
                <div class="mb-3">
                    <label for="audioInput" style="font-size: 0.9rem; font-weight: 500; color: #555; display: block; margin-bottom: 0.5rem;">
                        <i class="fas fa-microphone me-1"></i>Microphone:
                    </label>
                    <select id="audioInput" class="form-select form-select-sm">
                        <option value="">Loading...</option>
                    </select>
                </div>
                <div class="mb-2">
                    <label for="audioOutput" style="font-size: 0.9rem; font-weight: 500; color: #555; display: block; margin-bottom: 0.5rem;">
                        <i class="fas fa-headphones me-1"></i>Speaker/Headphones:
                    </label>
                    <select id="audioOutput" class="form-select form-select-sm">
                        <option value="">Loading...</option>
                    </select>
                </div>
                <button class="btn btn-sm btn-outline-primary mt-2 w-100" onclick="refreshAudioDevices()">
                    <i class="fas fa-sync-alt me-2"></i>Refresh Devices
                </button>
            </div>

            <div class="info-card">
                <div class="info-row">
                    <strong>Extension:</strong>
                    <span id="userExtension"><?= htmlspecialchars($user_extension) ?></span>
                </div>
                <div class="info-row">
                    <strong>User:</strong>
                    <span id="username"><?= htmlspecialchars($user_username) ?></span>
                </div>
                <div class="info-row">
                    <strong>Client:</strong>
                    <span id="clientInfo">FlexPhone Web v1.0</span>
                </div>
                <div class="info-row">
                    <strong>Server:</strong>
                    <span>flexpbx.devinecreations.net</span>
                </div>
                <div class="info-row">
                    <strong>SIP Status:</strong>
                    <span id="sipStatus">Initializing...</span>
                </div>
            </div>

            <div class="nav-buttons">
                <a href="/user-portal/" class="btn btn-outline-secondary nav-btn">
                    <i class="fas fa-home me-2"></i>Dashboard
                </a>
                <a href="/user-portal/settings.php" class="btn btn-outline-secondary nav-btn">
                    <i class="fas fa-cog me-2"></i>Settings
                </a>
                <a href="/user-portal/help.php" class="btn btn-outline-secondary nav-btn">
                    <i class="fas fa-question-circle me-2"></i>Help
                </a>
            </div>
        </div>
    </div>

    <audio id="remoteAudio" autoplay></audio>
    <audio id="localAudio" autoplay muted></audio>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sip.js@0.21.2/dist/sip.min.js"></script>
    <script>
        // Auto-loaded from server-side session
        const extension = '<?= addslashes($user_extension) ?>';
        const sipPassword = '<?= addslashes($sip_password ?? '') ?>';
        const hasCredentials = Boolean(extension && sipPassword);

        let currentNumber = '';
        let userAgent = null;
        let session = null;
        let selectedAudioInput = localStorage.getItem('flexphone_audio_input') || 'default';
        let selectedAudioOutput = localStorage.getItem('flexphone_audio_output') || 'default';

        // Enumerate and populate audio devices
        async function refreshAudioDevices() {
            try {
                // Request permission for audio devices
                await navigator.mediaDevices.getUserMedia({ audio: true });

                const devices = await navigator.mediaDevices.enumerateDevices();
                const audioInputSelect = document.getElementById('audioInput');
                const audioOutputSelect = document.getElementById('audioOutput');

                // Clear existing options
                audioInputSelect.innerHTML = '<option value="default">Default Microphone</option>';
                audioOutputSelect.innerHTML = '<option value="default">Default Speaker</option>';

                // Populate microphones
                devices.filter(device => device.kind === 'audioinput').forEach(device => {
                    const option = document.createElement('option');
                    option.value = device.deviceId;
                    option.text = device.label || `Microphone ${audioInputSelect.length}`;
                    audioInputSelect.appendChild(option);
                });

                // Populate speakers/headphones
                devices.filter(device => device.kind === 'audiooutput').forEach(device => {
                    const option = document.createElement('option');
                    option.value = device.deviceId;
                    option.text = device.label || `Speaker ${audioOutputSelect.length}`;
                    audioOutputSelect.appendChild(option);
                });

                // Restore saved selections
                audioInputSelect.value = selectedAudioInput;
                audioOutputSelect.value = selectedAudioOutput;

                console.log('Audio devices refreshed:', devices.length, 'devices found');
            } catch (error) {
                console.error('Failed to enumerate audio devices:', error);
                alert('Unable to access audio devices. Please grant microphone permission.');
            }
        }

        // Save audio device selection
        function saveAudioDeviceSelection() {
            const audioInputSelect = document.getElementById('audioInput');
            const audioOutputSelect = document.getElementById('audioOutput');

            selectedAudioInput = audioInputSelect.value;
            selectedAudioOutput = audioOutputSelect.value;

            localStorage.setItem('flexphone_audio_input', selectedAudioInput);
            localStorage.setItem('flexphone_audio_output', selectedAudioOutput);

            // Apply to remote audio element
            if (selectedAudioOutput !== 'default') {
                const remoteAudio = document.getElementById('remoteAudio');
                if (typeof remoteAudio.setSinkId === 'function') {
                    remoteAudio.setSinkId(selectedAudioOutput).then(() => {
                        console.log('Audio output device set to:', selectedAudioOutput);
                    }).catch(err => {
                        console.error('Failed to set audio output device:', err);
                    });
                }
            }

            console.log('Audio devices saved - Input:', selectedAudioInput, 'Output:', selectedAudioOutput);
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', () => {
            // Load audio devices
            refreshAudioDevices();

            // Add change listeners
            document.getElementById('audioInput').addEventListener('change', saveAudioDeviceSelection);
            document.getElementById('audioOutput').addEventListener('change', saveAudioDeviceSelection);

            if (hasCredentials) {
                initializeWebRTC();
            } else {
                updateStatus('Credentials not available', 'offline');
                document.getElementById('sipStatus').textContent = 'Error - Contact admin';
                document.getElementById('callBtn').disabled = true;
            }
        });

        function initializeWebRTC() {
            const server = 'flexpbx.devinecreations.net';
            const wsServer = `wss://${server}:8089/ws`;

            try {
                updateStatus('Connecting to server...', 'offline');

                const uri = SIP.UserAgent.makeURI(`sip:${extension}@${server}`);
                const transportOptions = {
                    server: wsServer,
                    connectionTimeout: 10
                };

                // Build audio constraints with selected device
                const audioConstraints = selectedAudioInput === 'default'
                    ? true
                    : { deviceId: { exact: selectedAudioInput } };

                const userAgentOptions = {
                    authorizationUsername: extension,
                    authorizationPassword: sipPassword,
                    transportOptions,
                    uri,
                    displayName: `FlexPhone ${extension}`,
                    userAgentString: 'FlexPhone/1.0 WebRTC',
                    sessionDescriptionHandlerFactoryOptions: {
                        constraints: {
                            audio: audioConstraints,
                            video: false
                        }
                    }
                };

                userAgent = new SIP.UserAgent(userAgentOptions);

                userAgent.delegate = {
                    onInvite(invitation) {
                        // Handle incoming calls
                        session = invitation;
                        updateStatus(`Incoming call from ${invitation.remoteIdentity.uri.user}`, 'calling');

                        if (confirm(`Incoming call from ${invitation.remoteIdentity.uri.user}. Answer?`)) {
                            invitation.accept();
                            document.getElementById('hangupBtn').disabled = false;
                            updateDisplay(invitation.remoteIdentity.uri.user);
                        } else {
                            invitation.reject();
                        }
                    }
                };

                userAgent.start().then(() => {
                    updateStatus('Connected - Ready to call', 'online');
                    document.getElementById('sipStatus').textContent = 'Registered';
                    console.log('FlexPhone connected successfully');
                }).catch(error => {
                    console.error('Failed to connect:', error);
                    updateStatus('Connection failed', 'offline');
                    document.getElementById('sipStatus').textContent = 'Offline - ' + error.message;
                });

            } catch (error) {
                console.error('WebRTC initialization error:', error);
                updateStatus('WebRTC not available', 'offline');
                document.getElementById('sipStatus').textContent = 'Error: ' + error.message;
            }
        }

        function dialDigit(digit) {
            currentNumber += digit;
            updateDisplay(currentNumber);

            // Send DTMF if in call
            if (session && session.state === SIP.SessionState.Established) {
                try {
                    session.sessionDescriptionHandler.sendDtmf(digit);
                } catch (error) {
                    console.error('Failed to send DTMF:', error);
                }
            }
        }

        function backspace() {
            if (currentNumber.length > 0) {
                currentNumber = currentNumber.slice(0, -1);
                updateDisplay(currentNumber);
            }
        }

        function makeCall() {
            if (!currentNumber) {
                alert('Please enter a number to call');
                return;
            }

            if (!userAgent || userAgent.state !== SIP.UserAgentState.Started) {
                alert('Not connected to server. Please refresh and try again.');
                return;
            }

            const target = SIP.UserAgent.makeURI(`sip:${currentNumber}@flexpbx.devinecreations.net`);

            if (!target) {
                alert('Invalid number');
                return;
            }

            // Use selected audio input device
            const audioConstraints = selectedAudioInput === 'default'
                ? true
                : { deviceId: { exact: selectedAudioInput } };

            const inviter = new SIP.Inviter(userAgent, target, {
                sessionDescriptionHandlerOptions: {
                    constraints: {
                        audio: audioConstraints,
                        video: false
                    }
                }
            });

            session = inviter;

            inviter.invite().then(() => {
                updateStatus(`Calling ${currentNumber}...`, 'calling');
                document.getElementById('hangupBtn').disabled = false;
                document.getElementById('callBtn').disabled = true;
            }).catch(error => {
                console.error('Call failed:', error);
                updateStatus('Call failed: ' + error.message, 'online');
                alert('Call failed: ' + error.message);
            });

            inviter.stateChange.addListener((state) => {
                if (state === SIP.SessionState.Established) {
                    updateStatus(`In call with ${currentNumber}`, 'calling');

                    // Setup remote audio
                    const remoteStream = new MediaStream();
                    const peerConnection = session.sessionDescriptionHandler.peerConnection;
                    peerConnection.getReceivers().forEach(receiver => {
                        if (receiver.track) {
                            remoteStream.addTrack(receiver.track);
                        }
                    });
                    const remoteAudio = document.getElementById('remoteAudio');
                    remoteAudio.srcObject = remoteStream;

                    // Apply selected output device
                    if (selectedAudioOutput !== 'default' && typeof remoteAudio.setSinkId === 'function') {
                        remoteAudio.setSinkId(selectedAudioOutput).then(() => {
                            console.log('Output device applied to call:', selectedAudioOutput);
                        }).catch(err => {
                            console.error('Failed to set output device during call:', err);
                        });
                    }

                } else if (state === SIP.SessionState.Terminated) {
                    updateStatus('Call ended', 'online');
                    document.getElementById('hangupBtn').disabled = true;
                    document.getElementById('callBtn').disabled = false;
                    session = null;
                }
            });
        }

        function hangUp() {
            if (session) {
                try {
                    if (session.state === SIP.SessionState.Establishing) {
                        session.cancel();
                    } else {
                        session.bye();
                    }
                    updateStatus('Call ended', 'online');
                    document.getElementById('hangupBtn').disabled = true;
                    document.getElementById('callBtn').disabled = false;
                    session = null;
                } catch (error) {
                    console.error('Hangup error:', error);
                    alert('Error ending call: ' + error.message);
                }
            }
        }

        function updateDisplay(text) {
            document.getElementById('display').textContent = text || 'Ready';
        }

        function updateStatus(text, status) {
            document.getElementById('statusText').textContent = text;
            const indicator = document.getElementById('statusIndicator');
            indicator.className = `status-indicator status-${status}`;
        }

        // Keyboard functionality
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' && currentNumber.length > 0) {
                e.preventDefault();
                backspace();
            } else if (e.key === 'Enter') {
                e.preventDefault();
                makeCall();
            } else if (e.key === 'Escape' && session) {
                e.preventDefault();
                hangUp();
            } else if (/^[0-9*#]$/.test(e.key)) {
                dialDigit(e.key);
            }
        });

        // Cleanup on page unload
        window.addEventListener('beforeunload', () => {
            if (session) {
                try {
                    session.bye();
                } catch (error) {
                    console.error('Cleanup error:', error);
                }
            }
            if (userAgent) {
                try {
                    userAgent.stop();
                } catch (error) {
                    console.error('Cleanup error:', error);
                }
            }
        });
    </script>
</body>
</html>
