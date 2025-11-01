document.addEventListener('DOMContentLoaded', () => {
    const socket = io();
    let currentExtension = null;
    let isScreenReaderMode = true;
    let voiceAnnouncementsEnabled = true;

    const statusContainer = document.getElementById('status-container');
    const ariaLiveRegion = document.getElementById('aria-live-region');
    const ariaAlertRegion = document.getElementById('aria-alert-region');

    socket.on('connect', () => {
        updateStatus('Connected to PBX server', 'success');
        announceToScreenReader('Connected to PBX server');
    });

    socket.on('disconnect', () => {
        updateStatus('Disconnected from PBX server', 'error');
        announceToScreenReader('Disconnected from PBX server', true);
    });

    socket.on('registered', (data) => {
        currentExtension = data.extension;
        updateStatus(`Registered as extension ${data.extension}`, 'success');
        announceToScreenReader(`Registered as extension ${data.extension}`);
    });

    socket.on('incoming-call', (data) => {
        handleIncomingCall(data);
    });

    socket.on('accessibility-response', (data) => {
        console.log('Accessibility feature status:', data);
    });

    document.getElementById('make-call-btn').addEventListener('click', () => {
        initiateCall();
    });

    document.getElementById('check-voicemail-btn').addEventListener('click', () => {
        checkVoicemail();
    });

    document.getElementById('conference-btn').addEventListener('click', () => {
        joinConference();
    });

    document.getElementById('directory-btn').addEventListener('click', () => {
        openDirectory();
    });

    document.addEventListener('keydown', (e) => {
        if (e.altKey) {
            switch(e.key.toLowerCase()) {
                case 'c':
                    e.preventDefault();
                    initiateCall();
                    break;
                case 'a':
                    e.preventDefault();
                    answerCall();
                    break;
                case 'e':
                    e.preventDefault();
                    endCall();
                    break;
                case 'm':
                    e.preventDefault();
                    toggleMute();
                    break;
                case 'h':
                    e.preventDefault();
                    toggleHold();
                    break;
                case 't':
                    e.preventDefault();
                    transferCall();
                    break;
                case 'v':
                    e.preventDefault();
                    checkVoicemail();
                    break;
                case 's':
                    e.preventDefault();
                    toggleAccessibilityPanel();
                    break;
            }
        }
    });

    function updateStatus(message, type = 'info') {
        statusContainer.innerHTML = `<p class="${type}-message">${message}</p>`;
        statusContainer.setAttribute('aria-live', 'polite');
    }

    function announceToScreenReader(message, urgent = false) {
        if (!isScreenReaderMode) return;

        const region = urgent ? ariaAlertRegion : ariaLiveRegion;
        region.textContent = message;

        setTimeout(() => {
            region.textContent = '';
        }, 100);
    }

    function playAudioFeedback(type) {
        if (!document.getElementById('audio-feedback').checked) return;

        const audio = new Audio();
        switch(type) {
            case 'success':
                audio.src = '/sounds/success.mp3';
                break;
            case 'error':
                audio.src = '/sounds/error.mp3';
                break;
            case 'ring':
                audio.src = '/sounds/ring.mp3';
                break;
            case 'dtmf':
                audio.src = '/sounds/dtmf.mp3';
                break;
        }
        audio.play().catch(e => console.error('Audio playback failed:', e));
    }

    function speakMessage(message) {
        if (!voiceAnnouncementsEnabled || !('speechSynthesis' in window)) return;

        const utterance = new SpeechSynthesisUtterance(message);
        const voiceSpeed = parseInt(document.getElementById('voice-speed')?.value || '150');
        utterance.rate = voiceSpeed / 150;
        utterance.pitch = 1;
        utterance.volume = 1;

        speechSynthesis.speak(utterance);
    }

    function initiateCall() {
        const phoneNumber = prompt('Enter phone number or extension:');
        if (!phoneNumber) return;

        socket.emit('make-call', {
            from: currentExtension,
            to: phoneNumber,
            accessibility: {
                screenReader: isScreenReaderMode,
                voiceSpeed: document.getElementById('voice-speed')?.value
            }
        });

        updateStatus(`Calling ${phoneNumber}...`, 'info');
        announceToScreenReader(`Initiating call to ${phoneNumber}`);
        playAudioFeedback('ring');
        speakMessage(`Calling ${phoneNumber}`);
    }

    function handleIncomingCall(data) {
        updateStatus(`Incoming call from ${data.from}`, 'warning');
        announceToScreenReader(`Incoming call from ${data.from}`, true);
        playAudioFeedback('ring');
        speakMessage(`Incoming call from ${data.from}`);

        if (confirm(`Accept call from ${data.from}?`)) {
            answerCall(data);
        } else {
            rejectCall(data);
        }
    }

    function answerCall(callData) {
        socket.emit('answer-call', {
            callId: callData?.callId,
            extension: currentExtension
        });

        updateStatus('Call connected', 'success');
        announceToScreenReader('Call answered and connected');
        playAudioFeedback('success');
        speakMessage('Call connected');
    }

    function endCall() {
        socket.emit('end-call', {
            extension: currentExtension
        });

        updateStatus('Call ended', 'info');
        announceToScreenReader('Call ended');
        speakMessage('Call ended');
    }

    function rejectCall(callData) {
        socket.emit('reject-call', {
            callId: callData.callId,
            extension: currentExtension
        });

        updateStatus('Call rejected', 'info');
        announceToScreenReader('Call rejected');
    }

    function toggleMute() {
        socket.emit('toggle-mute', {
            extension: currentExtension
        });

        const muted = !document.body.dataset.muted;
        document.body.dataset.muted = muted;

        const status = muted ? 'Microphone muted' : 'Microphone unmuted';
        updateStatus(status, 'info');
        announceToScreenReader(status);
        speakMessage(status);
    }

    function toggleHold() {
        socket.emit('toggle-hold', {
            extension: currentExtension
        });

        const onHold = !document.body.dataset.onHold;
        document.body.dataset.onHold = onHold;

        const status = onHold ? 'Call on hold' : 'Call resumed';
        updateStatus(status, 'info');
        announceToScreenReader(status);
        speakMessage(status);
    }

    function transferCall() {
        const transferTo = prompt('Transfer to extension:');
        if (!transferTo) return;

        socket.emit('transfer-call', {
            from: currentExtension,
            to: transferTo
        });

        updateStatus(`Transferring call to ${transferTo}...`, 'info');
        announceToScreenReader(`Transferring call to extension ${transferTo}`);
        speakMessage(`Transferring to ${transferTo}`);
    }

    function checkVoicemail() {
        socket.emit('check-voicemail', {
            extension: currentExtension
        });

        updateStatus('Checking voicemail...', 'info');
        announceToScreenReader('Checking voicemail messages');
        speakMessage('Checking voicemail');
    }

    socket.on('voicemail-status', (data) => {
        const message = `You have ${data.count} voicemail ${data.count === 1 ? 'message' : 'messages'}`;
        updateStatus(message, 'info');
        announceToScreenReader(message);
        speakMessage(message);
    });

    function joinConference() {
        const conferenceRoom = prompt('Enter conference room number:');
        if (!conferenceRoom) return;

        socket.emit('join-conference', {
            extension: currentExtension,
            room: conferenceRoom
        });

        updateStatus(`Joining conference room ${conferenceRoom}...`, 'info');
        announceToScreenReader(`Joining conference room ${conferenceRoom}`);
        speakMessage(`Joining conference ${conferenceRoom}`);
    }

    function openDirectory() {
        socket.emit('get-directory', {
            extension: currentExtension
        });

        updateStatus('Loading directory...', 'info');
        announceToScreenReader('Opening directory');
    }

    socket.on('directory-data', (data) => {
        displayDirectory(data.extensions);
    });

    function displayDirectory(extensions) {
        const directoryHTML = extensions.map(ext => `
            <div class="directory-entry" tabindex="0" role="button"
                 aria-label="Call ${ext.name} at extension ${ext.number}"
                 onclick="callExtension('${ext.number}')">
                <span class="ext-name">${ext.name}</span>
                <span class="ext-number">${ext.number}</span>
                <span class="ext-status" data-status-text="${ext.status}">${ext.status}</span>
            </div>
        `).join('');

        const directoryModal = document.createElement('div');
        directoryModal.className = 'focus-trap';
        directoryModal.innerHTML = `
            <div class="focus-trap-content" role="dialog" aria-label="Extension Directory">
                <h2>Extension Directory</h2>
                <div class="directory-list" role="list">
                    ${directoryHTML}
                </div>
                <button onclick="closeDirectory()" class="btn-secondary">Close</button>
            </div>
        `;

        document.body.appendChild(directoryModal);
        directoryModal.querySelector('button').focus();

        announceToScreenReader(`Directory loaded with ${extensions.length} extensions`);
    }

    window.callExtension = function(extension) {
        socket.emit('make-call', {
            from: currentExtension,
            to: extension
        });

        closeDirectory();
        updateStatus(`Calling extension ${extension}...`, 'info');
        announceToScreenReader(`Calling extension ${extension}`);
    };

    window.closeDirectory = function() {
        const modal = document.querySelector('.focus-trap');
        if (modal) {
            modal.remove();
        }
    };

    function toggleAccessibilityPanel() {
        const panel = document.getElementById('accessibility-panel');
        const menuBtn = document.getElementById('accessibility-menu-btn');
        const isHidden = panel.hasAttribute('hidden');

        if (isHidden) {
            panel.removeAttribute('hidden');
            menuBtn.setAttribute('aria-expanded', 'true');
            panel.querySelector('input, select, button').focus();
            announceToScreenReader('Accessibility panel opened');
        } else {
            panel.setAttribute('hidden', '');
            menuBtn.setAttribute('aria-expanded', 'false');
            menuBtn.focus();
            announceToScreenReader('Accessibility panel closed');
        }
    }

    const extensionPrompt = prompt('Enter your extension number:');
    if (extensionPrompt) {
        socket.emit('register', {
            extension: extensionPrompt,
            accessibility: {
                screenReader: isScreenReaderMode,
                voiceAnnouncements: voiceAnnouncementsEnabled
            }
        });
    }

    if ('serviceWorker' in navigator && 'PushManager' in window) {
        navigator.serviceWorker.register('/sw.js')
            .then(registration => {
                console.log('Service Worker registered:', registration);
            })
            .catch(error => {
                console.error('Service Worker registration failed:', error);
            });
    }
});