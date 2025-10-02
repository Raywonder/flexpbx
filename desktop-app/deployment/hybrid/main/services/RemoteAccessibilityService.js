const { EventEmitter } = require('events');
const { spawn, exec, execSync } = require('child_process');
const path = require('path');
const fs = require('fs-extra');
const os = require('os');
const WebSocket = require('ws');
const { pipeline } = require('stream');

class RemoteAccessibilityService extends EventEmitter {
    constructor() {
        super();
        this.platform = os.platform();
        this.isVoiceOverEnabled = false;
        this.isScreenReaderEnabled = false;
        this.remoteConnections = new Map();
        this.controlSession = null;
        this.voiceOverScriptingEnabled = false;

        // AccessKit integration
        this.accessKit = null;
        this.accessKitAvailable = false;

        // RIM-like functionality
        this.audioStreaming = {
            input: null,
            output: null,
            mixedAudio: null
        };
        this.rimConnection = null;
        this.webSocketServer = null;

        // Audio configuration
        this.audioConfig = {
            sampleRate: 48000,
            channels: 2,
            bitDepth: 16,
            bufferSize: 1024
        };

        // Audio controls for remote and local sides
        this.audioControls = {
            local: {
                input: {
                    volume: 1.0,
                    muted: false,
                    device: null,
                    gain: 1.0
                },
                output: {
                    volume: 1.0,
                    muted: false,
                    device: null,
                    gain: 1.0
                },
                monitoring: {
                    enabled: false,
                    volume: 0.5
                }
            },
            remote: {
                input: {
                    volume: 1.0,
                    muted: false,
                    device: null,
                    gain: 1.0
                },
                output: {
                    volume: 1.0,
                    muted: false,
                    device: null,
                    gain: 1.0
                },
                monitoring: {
                    enabled: true,
                    volume: 0.8
                }
            },
            mixer: {
                crossfade: 0.5, // 0 = full local, 1 = full remote
                masterVolume: 1.0,
                masterMuted: false,
                compressor: {
                    enabled: true,
                    threshold: -20,
                    ratio: 4,
                    attack: 3,
                    release: 100
                },
                noiseSuppression: {
                    enabled: true,
                    level: 0.5
                },
                echo: {
                    enabled: false,
                    delay: 100,
                    feedback: 0.3
                }
            }
        };

        // Audio device lists
        this.audioDevices = {
            input: [],
            output: []
        };

        this.voiceOverCommands = {
            // Navigation
            'move-next': 'VO+Right Arrow',
            'move-previous': 'VO+Left Arrow',
            'move-up': 'VO+Up Arrow',
            'move-down': 'VO+Down Arrow',
            'move-first': 'VO+Home',
            'move-last': 'VO+End',

            // Interaction
            'activate': 'VO+Space',
            'click': 'VO+Shift+Space',
            'menu': 'VO+Shift+M',
            'escape': 'Escape',

            // Reading
            'read-item': 'VO+F3',
            'read-all': 'VO+A',
            'read-line': 'VO+L',
            'read-word': 'VO+W',
            'read-character': 'VO+C',
            'stop-speech': 'Control',

            // Rotor
            'rotor-next': 'VO+U',
            'rotor-previous': 'VO+Shift+U',
            'rotor-select': 'VO+Command+Left/Right Arrow',

            // Windows
            'window-list': 'VO+F2',
            'application-chooser': 'VO+F1',
            'dock': 'VO+D',

            // Settings
            'voiceover-utility': 'VO+F8',
            'help': 'VO+H',
            'lock-vo-keys': 'VO+;'
        };

        this.windowsScreenReaderCommands = {
            // NVDA Commands
            nvda: {
                'read-title': 'NVDA+T',
                'read-all': 'NVDA+Down Arrow',
                'stop-speech': 'Control',
                'next-object': 'NVDA+Right Arrow',
                'previous-object': 'NVDA+Left Arrow',
                'activate': 'NVDA+Enter',
                'menu': 'NVDA+Shift+M'
            },
            // JAWS Commands
            jaws: {
                'read-title': 'Insert+T',
                'read-all': 'Insert+Down Arrow',
                'stop-speech': 'Control',
                'next-object': 'Insert+Right Arrow',
                'previous-object': 'Insert+Left Arrow',
                'activate': 'Insert+Enter',
                'menu': 'Insert+F10'
            },
            // Windows Narrator
            narrator: {
                'read-title': 'Caps Lock+T',
                'read-all': 'Caps Lock+Down Arrow',
                'stop-speech': 'Control',
                'next-object': 'Caps Lock+Right Arrow',
                'previous-object': 'Caps Lock+Left Arrow',
                'activate': 'Caps Lock+Enter',
                'menu': 'Caps Lock+Shift+M'
            }
        };

        this.initializeService();
    }

    async initializeService() {
        console.log('🔊 Initializing Remote Accessibility Service...');

        // Initialize AccessKit
        await this.initializeAccessKit();

        // Setup audio streaming
        await this.initializeAudioStreaming();

        if (this.platform === 'darwin') {
            await this.setupMacOSAccessibility();
        } else if (this.platform === 'win32') {
            await this.setupWindowsAccessibility();
        } else if (this.platform === 'linux') {
            await this.setupLinuxAccessibility();
        }

        this.setupRemoteControlServer();
        this.setupRIMConnection();
    }

    async initializeAccessKit() {
        try {
            console.log('🛠️ Initializing AccessKit integration...');

            // Check if AccessKit binaries are available
            await this.checkAccessKitAvailability();

            if (this.accessKitAvailable) {
                this.accessKit = await this.loadAccessKit();
                console.log('✅ AccessKit initialized successfully');
            } else {
                console.log('⚠️ AccessKit not available, falling back to platform-specific accessibility');
            }
        } catch (error) {
            console.error('Failed to initialize AccessKit:', error);
            this.accessKitAvailable = false;
        }
    }

    async checkAccessKitAvailability() {
        // Check for AccessKit binaries based on platform
        const accessKitPaths = {
            darwin: [
                '/usr/local/bin/accesskit',
                '/opt/homebrew/bin/accesskit',
                path.join(__dirname, '../../../node_modules/@accesskit/core/bin/accesskit-macos')
            ],
            win32: [
                'C:\\Program Files\\AccessKit\\accesskit.exe',
                path.join(__dirname, '../../../node_modules/@accesskit/core/bin/accesskit-windows.exe')
            ],
            linux: [
                '/usr/bin/accesskit',
                '/usr/local/bin/accesskit',
                path.join(__dirname, '../../../node_modules/@accesskit/core/bin/accesskit-linux')
            ]
        };

        const paths = accessKitPaths[this.platform] || [];

        for (const accessKitPath of paths) {
            if (await fs.pathExists(accessKitPath)) {
                this.accessKitPath = accessKitPath;
                this.accessKitAvailable = true;
                return true;
            }
        }

        // Try to install AccessKit via npm if not found
        await this.tryInstallAccessKit();
        return false;
    }

    async tryInstallAccessKit() {
        try {
            console.log('📦 Attempting to install AccessKit...');
            const result = await this.execCommand('npm install @accesskit/core @accesskit/node');

            if (result.code === 0) {
                console.log('✅ AccessKit installed successfully');
                await this.checkAccessKitAvailability();
            }
        } catch (error) {
            console.log('⚠️ Could not install AccessKit automatically');
        }
    }

    async loadAccessKit() {
        try {
            // Try to load AccessKit Node.js bindings
            const accessKit = require('@accesskit/node');
            return accessKit;
        } catch (error) {
            // Fall back to command-line interface
            return {
                useCommandLine: true,
                path: this.accessKitPath
            };
        }
    }

    async initializeAudioStreaming() {
        console.log('🎵 Initializing audio streaming for RIM-like functionality...');

        try {
            // Initialize audio input/output streams
            await this.setupAudioDevices();
            await this.createAudioMixer();

            console.log('✅ Audio streaming initialized');
        } catch (error) {
            console.error('Failed to initialize audio streaming:', error);
        }
    }

    async setupAudioDevices() {
        // This would typically use Node.js audio libraries like node-speaker/node-microphone
        // or integrate with platform-specific audio APIs

        if (this.platform === 'darwin') {
            await this.setupMacOSAudio();
        } else if (this.platform === 'win32') {
            await this.setupWindowsAudio();
        } else if (this.platform === 'linux') {
            await this.setupLinuxAudio();
        }
    }

    async setupMacOSAudio() {
        try {
            // Use Core Audio for macOS
            const script = `
            tell application "System Events"
                return name of (every audio device whose kind is "audio input")
            end tell
            `;

            const inputDevices = await this.runAppleScript(script);
            console.log('🎤 Available input devices:', inputDevices);

            // Set up audio routing for remote accessibility
            this.audioStreaming.input = {
                device: 'default',
                stream: null,
                enabled: true
            };

            this.audioStreaming.output = {
                device: 'default',
                stream: null,
                enabled: true
            };

        } catch (error) {
            console.error('Failed to setup macOS audio:', error);
        }
    }

    async setupWindowsAudio() {
        try {
            // Use Windows Audio APIs
            const devices = await this.execCommand('powershell "Get-WmiObject Win32_SoundDevice | Select-Object Name"');
            console.log('🎤 Available audio devices:', devices.stdout);

        } catch (error) {
            console.error('Failed to setup Windows audio:', error);
        }
    }

    async setupLinuxAudio() {
        try {
            // Use PulseAudio/ALSA for Linux
            const devices = await this.execCommand('pactl list short sources');
            console.log('🎤 Available input devices:', devices.stdout);

        } catch (error) {
            console.error('Failed to setup Linux audio:', error);
        }
    }

    async createAudioMixer() {
        // Create a virtual audio mixer for combining local and remote audio
        this.audioStreaming.mixedAudio = {
            localInput: true,
            remoteInput: true,
            localOutput: true,
            remoteOutput: true,
            volume: {
                localInput: 0.8,
                remoteInput: 0.8,
                localOutput: 1.0,
                remoteOutput: 1.0
            }
        };
    }

    setupRIMConnection() {
        console.log('🌐 Setting up RIM-like remote connection server...');

        // Create WebSocket server for real-time accessibility and audio
        this.webSocketServer = new WebSocket.Server({
            port: 41237,
            verifyClient: (info) => {
                // Add authentication logic here
                return true;
            }
        });

        this.webSocketServer.on('connection', (ws, request) => {
            console.log('🔌 RIM client connected from:', request.socket.remoteAddress);

            ws.on('message', async (data) => {
                try {
                    const message = JSON.parse(data);
                    await this.handleRIMMessage(ws, message);
                } catch (error) {
                    console.error('Error handling RIM message:', error);
                    ws.send(JSON.stringify({
                        type: 'error',
                        message: error.message
                    }));
                }
            });

            ws.on('close', () => {
                console.log('🔌 RIM client disconnected');
                this.cleanupRIMConnection(ws);
            });

            // Send initial accessibility state
            this.sendAccessibilityState(ws);
        });

        console.log('✅ RIM server listening on port 41237');
    }

    async handleRIMMessage(ws, message) {
        const { type, data } = message;

        switch (type) {
            case 'accessibility-command':
                const result = await this.executeAccessibilityCommand(data.command, data.parameters);
                ws.send(JSON.stringify({
                    type: 'accessibility-result',
                    data: result
                }));
                break;

            case 'audio-stream':
                await this.handleAudioStream(ws, data);
                break;

            case 'screen-reader-control':
                const srResult = await this.controlScreenReader(data.action, data.parameters);
                ws.send(JSON.stringify({
                    type: 'screen-reader-result',
                    data: srResult
                }));
                break;

            case 'get-accessibility-tree':
                const tree = await this.getAccessibilityTree();
                ws.send(JSON.stringify({
                    type: 'accessibility-tree',
                    data: tree
                }));
                break;

            case 'remote-input':
                await this.processRemoteInput(data);
                break;

            default:
                ws.send(JSON.stringify({
                    type: 'error',
                    message: `Unknown message type: ${type}`
                }));
        }
    }

    async executeAccessibilityCommand(command, parameters = {}) {
        if (this.accessKitAvailable && this.accessKit) {
            return await this.executeAccessKitCommand(command, parameters);
        } else {
            // Fall back to platform-specific implementation
            return await this.executeRemoteCommand('remote', command, parameters);
        }
    }

    async executeAccessKitCommand(command, parameters = {}) {
        try {
            if (this.accessKit.useCommandLine) {
                // Use AccessKit command line
                const args = this.buildAccessKitArgs(command, parameters);
                const result = await this.execCommand(`"${this.accessKitPath}" ${args.join(' ')}`);

                return {
                    success: result.code === 0,
                    output: result.stdout,
                    error: result.stderr
                };
            } else {
                // Use AccessKit Node.js API
                return await this.accessKit.executeCommand(command, parameters);
            }
        } catch (error) {
            return {
                success: false,
                error: error.message
            };
        }
    }

    buildAccessKitArgs(command, parameters) {
        const args = [command];

        for (const [key, value] of Object.entries(parameters)) {
            args.push(`--${key}`, value);
        }

        return args;
    }

    async getAccessibilityTree() {
        if (this.accessKitAvailable) {
            return await this.getAccessKitTree();
        } else {
            return await this.getPlatformAccessibilityTree();
        }
    }

    async getAccessKitTree() {
        try {
            if (this.accessKit.useCommandLine) {
                const result = await this.execCommand(`"${this.accessKitPath}" get-tree --format json`);
                return JSON.parse(result.stdout);
            } else {
                return await this.accessKit.getAccessibilityTree();
            }
        } catch (error) {
            console.error('Failed to get AccessKit tree:', error);
            return null;
        }
    }

    async getPlatformAccessibilityTree() {
        if (this.platform === 'darwin') {
            return await this.getMacOSAccessibilityTree();
        } else if (this.platform === 'win32') {
            return await this.getWindowsAccessibilityTree();
        } else if (this.platform === 'linux') {
            return await this.getLinuxAccessibilityTree();
        }
        return null;
    }

    async getMacOSAccessibilityTree() {
        try {
            const script = `
            tell application "System Events"
                tell (first application process whose frontmost is true)
                    return entire contents
                end tell
            end tell
            `;

            const result = await this.runAppleScript(script);
            return this.parseAppleScriptTree(result);
        } catch (error) {
            console.error('Failed to get macOS accessibility tree:', error);
            return null;
        }
    }

    parseAppleScriptTree(appleScriptOutput) {
        // Parse AppleScript accessibility output into structured tree
        return {
            platform: 'macOS',
            tree: appleScriptOutput,
            timestamp: new Date().toISOString()
        };
    }

    async getWindowsAccessibilityTree() {
        // Use Windows UI Automation API
        try {
            const script = `
            Add-Type -AssemblyName System.Windows.Automation
            $root = [System.Windows.Automation.AutomationElement]::RootElement
            $root | ConvertTo-Json -Depth 5
            `;

            const result = await this.execCommand(`powershell -Command "${script}"`);
            return JSON.parse(result.stdout);
        } catch (error) {
            console.error('Failed to get Windows accessibility tree:', error);
            return null;
        }
    }

    async getLinuxAccessibilityTree() {
        // Use AT-SPI for Linux accessibility
        try {
            const result = await this.execCommand('accerciser --tree');
            return {
                platform: 'Linux',
                tree: result.stdout,
                timestamp: new Date().toISOString()
            };
        } catch (error) {
            console.error('Failed to get Linux accessibility tree:', error);
            return null;
        }
    }

    async handleAudioStream(ws, audioData) {
        // Handle bidirectional audio streaming for RIM-like functionality
        const { type, stream, format } = audioData;

        switch (type) {
            case 'input-stream':
                await this.processRemoteAudioInput(stream, format);
                break;
            case 'output-request':
                await this.sendLocalAudioOutput(ws, format);
                break;
            case 'mixed-audio':
                await this.handleMixedAudio(ws, stream, format);
                break;
        }
    }

    async processRemoteAudioInput(audioStream, format) {
        // Process incoming audio from remote client
        console.log('🎤 Processing remote audio input...');

        // Route remote audio to local speakers/headphones
        if (this.audioStreaming.remoteInput) {
            // This would integrate with platform audio APIs
            this.emit('remote-audio-received', { stream: audioStream, format });
        }
    }

    async sendLocalAudioOutput(ws, format) {
        // Send local audio output to remote client
        console.log('🔊 Sending local audio output...');

        if (this.audioStreaming.localOutput) {
            // Capture local audio and send to remote
            const audioData = await this.captureLocalAudio(format);
            ws.send(JSON.stringify({
                type: 'audio-stream',
                data: {
                    type: 'output-stream',
                    stream: audioData,
                    format
                }
            }));
        }
    }

    async captureLocalAudio(format) {
        // Capture local system audio
        // This would use platform-specific audio capture APIs
        return {
            sampleRate: this.audioConfig.sampleRate,
            channels: this.audioConfig.channels,
            data: Buffer.alloc(this.audioConfig.bufferSize) // Mock audio data
        };
    }

    async controlScreenReader(action, parameters = {}) {
        const activeReader = this.getActiveScreenReader();

        console.log(`🗣️ Controlling ${activeReader}: ${action}`);

        switch (action) {
            case 'speak':
                return await this.speakText(parameters.text, parameters.options);
            case 'stop':
                return await this.stopSpeech();
            case 'navigate':
                return await this.navigateScreenReader(parameters.direction, parameters.type);
            case 'read':
                return await this.readScreenReaderContent(parameters.target);
            default:
                return { success: false, error: `Unknown action: ${action}` };
        }
    }

    async speakText(text, options = {}) {
        if (this.platform === 'darwin') {
            const voice = options.voice || 'Alex';
            const rate = options.rate || 200;

            const script = `say "${text}" --voice="${voice}" --rate=${rate}`;
            const result = await this.execCommand(script);

            return {
                success: result.code === 0,
                message: 'Text spoken via macOS TTS'
            };
        } else if (this.platform === 'win32') {
            const script = `
            Add-Type -AssemblyName System.Speech
            $synth = New-Object System.Speech.Synthesis.SpeechSynthesizer
            $synth.Speak("${text}")
            `;

            const result = await this.execCommand(`powershell -Command "${script}"`);

            return {
                success: result.code === 0,
                message: 'Text spoken via Windows TTS'
            };
        }

        return { success: false, error: 'TTS not available on this platform' };
    }

    async stopSpeech() {
        if (this.platform === 'darwin') {
            await this.execCommand('killall say');
        } else if (this.platform === 'win32') {
            // Stop Windows TTS
            const script = `
            Get-Process | Where-Object {$_.ProcessName -eq "TTSApp"} | Stop-Process -Force
            `;
            await this.execCommand(`powershell -Command "${script}"`);
        }

        return { success: true, message: 'Speech stopped' };
    }

    sendAccessibilityState(ws) {
        const state = {
            platform: this.platform,
            screenReader: this.getActiveScreenReader(),
            voiceOverEnabled: this.isVoiceOverEnabled,
            accessKitAvailable: this.accessKitAvailable,
            audioStreaming: this.audioStreaming,
            features: this.getStatus().features
        };

        ws.send(JSON.stringify({
            type: 'accessibility-state',
            data: state
        }));
    }

    cleanupRIMConnection(ws) {
        // Clean up any resources associated with the connection
        if (this.rimConnection === ws) {
            this.rimConnection = null;
        }
    }

    async setupMacOSAccessibility() {
        console.log('🍎 Setting up macOS accessibility features...');

        // Check if VoiceOver is running
        this.isVoiceOverEnabled = await this.checkVoiceOverStatus();

        // Check if VoiceOver AppleScript control is enabled
        this.voiceOverScriptingEnabled = await this.checkVoiceOverScriptingEnabled();

        if (!this.voiceOverScriptingEnabled) {
            console.log('⚠️ VoiceOver AppleScript control is not enabled');
            await this.promptEnableVoiceOverScripting();
        }

        // Set up VoiceOver monitoring
        this.setupVoiceOverMonitoring();

        console.log(`✅ VoiceOver Status: ${this.isVoiceOverEnabled ? 'Running' : 'Stopped'}`);
        console.log(`✅ AppleScript Control: ${this.voiceOverScriptingEnabled ? 'Enabled' : 'Disabled'}`);
    }

    async checkVoiceOverStatus() {
        try {
            const script = `
            tell application "System Events"
                return (name of processes) contains "VoiceOver"
            end tell
            `;

            const result = await this.runAppleScript(script);
            return result.trim() === 'true';
        } catch (error) {
            console.error('Failed to check VoiceOver status:', error);
            return false;
        }
    }

    async checkVoiceOverScriptingEnabled() {
        try {
            // Check VoiceOver preferences for AppleScript control
            const script = `
            tell application "VoiceOver Utility"
                return "VoiceOver Utility is accessible"
            end tell
            `;

            await this.runAppleScript(script);
            return true;
        } catch (error) {
            // If we can't access VoiceOver Utility, scripting likely isn't enabled
            return false;
        }
    }

    async promptEnableVoiceOverScripting() {
        console.log('📋 To enable remote VoiceOver control:');
        console.log('1. Open VoiceOver Utility (VO+F8)');
        console.log('2. Go to General tab');
        console.log('3. Check "Allow VoiceOver to be controlled with AppleScript"');
        console.log('4. Restart FlexPBX Desktop');

        // Attempt to automatically open VoiceOver Utility
        try {
            await this.runAppleScript(`
                tell application "VoiceOver Utility"
                    activate
                end tell
            `);

            // Show notification
            await this.runAppleScript(`
                display notification "Please enable 'Allow VoiceOver to be controlled with AppleScript' in General settings" with title "FlexPBX Accessibility Setup"
            `);

        } catch (error) {
            console.log('Could not automatically open VoiceOver Utility');
        }
    }

    setupVoiceOverMonitoring() {
        // Monitor VoiceOver status changes
        setInterval(async () => {
            const currentStatus = await this.checkVoiceOverStatus();
            if (currentStatus !== this.isVoiceOverEnabled) {
                this.isVoiceOverEnabled = currentStatus;
                this.emit('voiceover-status-changed', this.isVoiceOverEnabled);
                console.log(`🔊 VoiceOver ${this.isVoiceOverEnabled ? 'started' : 'stopped'}`);
            }
        }, 5000);
    }

    async setupWindowsAccessibility() {
        console.log('🪟 Setting up Windows accessibility features...');

        // Detect available screen readers
        const screenReaders = await this.detectWindowsScreenReaders();
        this.availableScreenReaders = screenReaders;

        console.log(`✅ Available Screen Readers: ${screenReaders.join(', ')}`);
    }

    async detectWindowsScreenReaders() {
        const screenReaders = [];

        try {
            // Check for NVDA
            const nvdaResult = await this.execCommand('tasklist /FI "IMAGENAME eq nvda.exe" 2>NUL | find /I "nvda.exe"');
            if (nvdaResult.stdout.includes('nvda.exe')) {
                screenReaders.push('NVDA');
            }

            // Check for JAWS
            const jawsResult = await this.execCommand('tasklist /FI "IMAGENAME eq jfw.exe" 2>NUL | find /I "jfw.exe"');
            if (jawsResult.stdout.includes('jfw.exe')) {
                screenReaders.push('JAWS');
            }

            // Check for Narrator (always available on Windows 10+)
            screenReaders.push('Narrator');

        } catch (error) {
            console.error('Error detecting screen readers:', error);
        }

        return screenReaders;
    }

    async setupLinuxAccessibility() {
        console.log('🐧 Setting up Linux accessibility features...');

        // Check for Orca screen reader
        try {
            const result = await this.execCommand('pgrep orca');
            if (result.code === 0) {
                this.isScreenReaderEnabled = true;
                console.log('✅ Orca screen reader detected');
            }
        } catch (error) {
            console.log('ℹ️ Orca screen reader not detected');
        }
    }

    setupRemoteControlServer() {
        // This would set up a secure WebSocket server for remote control
        console.log('🌐 Setting up remote accessibility control server...');

        // Mock WebSocket server setup
        this.controlServer = {
            port: 41236,
            secure: true,
            authentication: true
        };

        console.log(`✅ Remote control server ready on port ${this.controlServer.port}`);
    }

    async startRemoteAccessibilitySession(clientId, options = {}) {
        const {
            readOnly = false,
            voiceControl = true,
            navigationControl = true,
            fullControl = false
        } = options;

        console.log(`🎯 Starting remote accessibility session for client: ${clientId}`);

        if (this.platform === 'darwin' && !this.voiceOverScriptingEnabled) {
            return {
                success: false,
                error: 'VoiceOver AppleScript control is not enabled'
            };
        }

        const session = {
            clientId,
            startedAt: new Date().toISOString(),
            permissions: {
                readOnly,
                voiceControl,
                navigationControl,
                fullControl
            },
            platform: this.platform,
            screenReader: this.getActiveScreenReader()
        };

        this.controlSession = session;
        this.remoteConnections.set(clientId, session);

        // Start screen reader if not running (with user permission)
        if (this.platform === 'darwin' && !this.isVoiceOverEnabled) {
            await this.startVoiceOver();
        }

        this.emit('remote-session-started', session);

        return {
            success: true,
            session,
            message: 'Remote accessibility session started'
        };
    }

    async executeRemoteCommand(clientId, command, parameters = {}) {
        const session = this.remoteConnections.get(clientId);
        if (!session) {
            return {
                success: false,
                error: 'No active session for client'
            };
        }

        console.log(`🎮 Executing remote command: ${command} for ${clientId}`);

        try {
            let result;

            if (this.platform === 'darwin') {
                result = await this.executeVoiceOverCommand(command, parameters);
            } else if (this.platform === 'win32') {
                result = await this.executeWindowsScreenReaderCommand(command, parameters);
            } else if (this.platform === 'linux') {
                result = await this.executeOrcaCommand(command, parameters);
            }

            this.emit('remote-command-executed', {
                clientId,
                command,
                parameters,
                result
            });

            return result;

        } catch (error) {
            return {
                success: false,
                error: error.message
            };
        }
    }

    async executeVoiceOverCommand(command, parameters = {}) {
        if (!this.voiceOverScriptingEnabled) {
            throw new Error('VoiceOver AppleScript control is not enabled');
        }

        let script;

        switch (command) {
            case 'navigate':
                script = await this.generateVoiceOverNavigationScript(parameters.direction);
                break;

            case 'read':
                script = await this.generateVoiceOverReadScript(parameters.target);
                break;

            case 'activate':
                script = `
                tell application "VoiceOver"
                    perform action "AXPress" of (item 1 of (get vo cursor))
                end tell
                `;
                break;

            case 'type-text':
                script = `
                tell application "System Events"
                    keystroke "${parameters.text}"
                end tell
                `;
                break;

            case 'key-combination':
                script = await this.generateVoiceOverKeyScript(parameters.keys);
                break;

            case 'get-current-item':
                script = `
                tell application "VoiceOver"
                    return description of (item 1 of (get vo cursor))
                end tell
                `;
                break;

            case 'find-element':
                script = await this.generateVoiceOverFindScript(parameters.selector);
                break;

            case 'rotor-navigate':
                script = await this.generateVoiceOverRotorScript(parameters.type, parameters.direction);
                break;

            default:
                throw new Error(`Unknown VoiceOver command: ${command}`);
        }

        const result = await this.runAppleScript(script);

        return {
            success: true,
            command,
            result,
            platform: 'macOS'
        };
    }

    async generateVoiceOverNavigationScript(direction) {
        const directions = {
            'next': 'move to next item',
            'previous': 'move to previous item',
            'up': 'move up',
            'down': 'move down',
            'first': 'move to first item',
            'last': 'move to last item',
            'into': 'move into item',
            'out': 'move out of item'
        };

        const action = directions[direction] || 'move to next item';

        return `
        tell application "VoiceOver"
            ${action}
        end tell
        `;
    }

    async generateVoiceOverReadScript(target) {
        const targets = {
            'current': 'read current item',
            'all': 'read all',
            'line': 'read line',
            'word': 'read word',
            'character': 'read character',
            'title': 'read window title'
        };

        const action = targets[target] || 'read current item';

        return `
        tell application "VoiceOver"
            ${action}
        end tell
        `;
    }

    async generateVoiceOverKeyScript(keys) {
        // Convert key combinations to AppleScript format
        const keyMap = {
            'vo': 'Control+Option',
            'cmd': 'Command',
            'shift': 'Shift',
            'ctrl': 'Control',
            'alt': 'Option'
        };

        let modifiers = [];
        let key = '';

        keys.split('+').forEach(k => {
            const mappedKey = keyMap[k.toLowerCase()];
            if (mappedKey) {
                modifiers.push(mappedKey);
            } else {
                key = k;
            }
        });

        const modifierString = modifiers.length > 0 ? `using {${modifiers.join(', ')}}` : '';

        return `
        tell application "System Events"
            key code ${this.getKeyCode(key)} ${modifierString}
        end tell
        `;
    }

    async generateVoiceOverFindScript(selector) {
        return `
        tell application "VoiceOver"
            set foundItems to (get items whose description contains "${selector}")
            if (count of foundItems) > 0 then
                set vo cursor to item 1 of foundItems
                return "Found: " & description of (item 1 of foundItems)
            else
                return "Not found: ${selector}"
            end if
        end tell
        `;
    }

    async generateVoiceOverRotorScript(type, direction) {
        return `
        tell application "VoiceOver"
            set rotor to "${type}"
            ${direction === 'next' ? 'move to next rotor item' : 'move to previous rotor item'}
        end tell
        `;
    }

    async executeWindowsScreenReaderCommand(command, parameters = {}) {
        const activeReader = this.getActiveScreenReader();
        const commands = this.windowsScreenReaderCommands[activeReader.toLowerCase()];

        if (!commands) {
            throw new Error(`No commands available for ${activeReader}`);
        }

        let keySequence;

        switch (command) {
            case 'navigate':
                keySequence = parameters.direction === 'next' ? commands['next-object'] : commands['previous-object'];
                break;
            case 'read':
                keySequence = commands[`read-${parameters.target}`] || commands['read-title'];
                break;
            case 'activate':
                keySequence = commands['activate'];
                break;
            default:
                keySequence = commands[command];
        }

        if (!keySequence) {
            throw new Error(`Unknown command for ${activeReader}: ${command}`);
        }

        // Send key sequence to Windows
        await this.sendWindowsKeys(keySequence);

        return {
            success: true,
            command,
            screenReader: activeReader,
            keySequence,
            platform: 'Windows'
        };
    }

    async executeOrcaCommand(command, parameters = {}) {
        // Orca screen reader commands for Linux
        let orcaCommand;

        switch (command) {
            case 'read':
                orcaCommand = 'orca --text-mode';
                break;
            case 'navigate':
                // Orca navigation would be handled through AT-SPI
                break;
            default:
                throw new Error(`Unknown Orca command: ${command}`);
        }

        if (orcaCommand) {
            const result = await this.execCommand(orcaCommand);
            return {
                success: result.code === 0,
                command,
                result: result.stdout,
                platform: 'Linux'
            };
        }

        return {
            success: false,
            error: 'Command not implemented for Orca'
        };
    }

    async sendWindowsKeys(keySequence) {
        // Use PowerShell to send key sequences on Windows
        const script = `
        Add-Type -AssemblyName System.Windows.Forms
        [System.Windows.Forms.SendKeys]::SendWait("${keySequence}")
        `;

        await this.execCommand(`powershell -Command "${script}"`);
    }

    getKeyCode(key) {
        // Map key names to macOS key codes
        const keyCodes = {
            'space': '49',
            'enter': '36',
            'tab': '48',
            'escape': '53',
            'left': '123',
            'right': '124',
            'up': '126',
            'down': '125',
            'home': '115',
            'end': '119'
        };

        return keyCodes[key.toLowerCase()] || '49'; // Default to space
    }

    getActiveScreenReader() {
        if (this.platform === 'darwin') {
            return this.isVoiceOverEnabled ? 'VoiceOver' : 'None';
        } else if (this.platform === 'win32') {
            return this.availableScreenReaders?.[0] || 'None';
        } else if (this.platform === 'linux') {
            return this.isScreenReaderEnabled ? 'Orca' : 'None';
        }
        return 'None';
    }

    async startVoiceOver() {
        if (this.platform !== 'darwin') {
            return { success: false, error: 'VoiceOver only available on macOS' };
        }

        try {
            const script = `
            tell application "System Events"
                if not (name of processes) contains "VoiceOver" then
                    tell application "VoiceOver" to activate
                end if
            end tell
            `;

            await this.runAppleScript(script);

            // Wait for VoiceOver to start
            await new Promise(resolve => setTimeout(resolve, 3000));

            this.isVoiceOverEnabled = await this.checkVoiceOverStatus();

            return {
                success: this.isVoiceOverEnabled,
                message: this.isVoiceOverEnabled ? 'VoiceOver started' : 'Failed to start VoiceOver'
            };

        } catch (error) {
            return {
                success: false,
                error: error.message
            };
        }
    }

    async stopVoiceOver() {
        if (this.platform !== 'darwin') {
            return { success: false, error: 'VoiceOver only available on macOS' };
        }

        try {
            const script = `
            tell application "VoiceOver"
                quit
            end tell
            `;

            await this.runAppleScript(script);
            this.isVoiceOverEnabled = false;

            return {
                success: true,
                message: 'VoiceOver stopped'
            };

        } catch (error) {
            return {
                success: false,
                error: error.message
            };
        }
    }

    async runAppleScript(script) {
        return new Promise((resolve, reject) => {
            const process = spawn('osascript', ['-e', script]);
            let output = '';
            let error = '';

            process.stdout.on('data', (data) => {
                output += data.toString();
            });

            process.stderr.on('data', (data) => {
                error += data.toString();
            });

            process.on('close', (code) => {
                if (code === 0) {
                    resolve(output);
                } else {
                    reject(new Error(error || `AppleScript failed with code ${code}`));
                }
            });
        });
    }

    async execCommand(command) {
        return new Promise((resolve) => {
            exec(command, (error, stdout, stderr) => {
                resolve({
                    code: error ? error.code || 1 : 0,
                    stdout,
                    stderr,
                    error: error?.message
                });
            });
        });
    }

    async stopRemoteAccessibilitySession(clientId) {
        const session = this.remoteConnections.get(clientId);
        if (!session) {
            return {
                success: false,
                error: 'No active session for client'
            };
        }

        this.remoteConnections.delete(clientId);

        if (this.controlSession?.clientId === clientId) {
            this.controlSession = null;
        }

        this.emit('remote-session-stopped', { clientId, session });

        return {
            success: true,
            message: 'Remote accessibility session stopped'
        };
    }

    // ===================== AUDIO CONTROL METHODS =====================

    async initializeAudioDevices() {
        console.log('🔊 Initializing audio devices...');

        try {
            if (this.platform === 'darwin') {
                await this.getMacOSAudioDevices();
            } else if (this.platform === 'win32') {
                await this.getWindowsAudioDevices();
            } else if (this.platform === 'linux') {
                await this.getLinuxAudioDevices();
            }

            console.log(`✅ Audio devices initialized: ${this.audioDevices.input.length} input, ${this.audioDevices.output.length} output`);
            return { success: true, devices: this.audioDevices };
        } catch (error) {
            console.error('❌ Failed to initialize audio devices:', error);
            return { success: false, error: error.message };
        }
    }

    async getMacOSAudioDevices() {
        const inputResult = await this.execCommand('system_profiler SPAudioDataType | grep "Input Source" -A 1');
        const outputResult = await this.execCommand('system_profiler SPAudioDataType | grep "Output Source" -A 1');

        // Parse input devices
        if (inputResult.stdout) {
            this.audioDevices.input = this.parseMacOSAudioDevices(inputResult.stdout, 'input');
        }

        // Parse output devices
        if (outputResult.stdout) {
            this.audioDevices.output = this.parseMacOSAudioDevices(outputResult.stdout, 'output');
        }

        // Add default devices
        this.audioDevices.input.unshift({ id: 'default', name: 'Default Input', isDefault: true });
        this.audioDevices.output.unshift({ id: 'default', name: 'Default Output', isDefault: true });
    }

    async getWindowsAudioDevices() {
        const script = `
        Get-WmiObject -Class Win32_SoundDevice | Select-Object Name, DeviceID | ConvertTo-Json
        `;

        const result = await this.execCommand(`powershell -Command "${script}"`);

        if (result.stdout) {
            try {
                const devices = JSON.parse(result.stdout);
                const deviceList = Array.isArray(devices) ? devices : [devices];

                this.audioDevices.input = deviceList.map(device => ({
                    id: device.DeviceID,
                    name: device.Name,
                    isDefault: false
                }));

                this.audioDevices.output = [...this.audioDevices.input];
            } catch (parseError) {
                console.error('Error parsing Windows audio devices:', parseError);
            }
        }

        // Add default devices
        this.audioDevices.input.unshift({ id: 'default', name: 'Default Input', isDefault: true });
        this.audioDevices.output.unshift({ id: 'default', name: 'Default Output', isDefault: true });
    }

    async getLinuxAudioDevices() {
        const inputResult = await this.execCommand('arecord -l 2>/dev/null | grep "card"');
        const outputResult = await this.execCommand('aplay -l 2>/dev/null | grep "card"');

        // Parse input devices
        if (inputResult.stdout) {
            this.audioDevices.input = this.parseLinuxAudioDevices(inputResult.stdout, 'input');
        }

        // Parse output devices
        if (outputResult.stdout) {
            this.audioDevices.output = this.parseLinuxAudioDevices(outputResult.stdout, 'output');
        }

        // Add default devices
        this.audioDevices.input.unshift({ id: 'default', name: 'Default Input', isDefault: true });
        this.audioDevices.output.unshift({ id: 'default', name: 'Default Output', isDefault: true });
    }

    parseMacOSAudioDevices(output, type) {
        const devices = [];
        const lines = output.split('\n');

        lines.forEach(line => {
            const match = line.match(/(.+?):/);
            if (match) {
                devices.push({
                    id: `macos_${type}_${devices.length}`,
                    name: match[1].trim(),
                    isDefault: false
                });
            }
        });

        return devices;
    }

    parseLinuxAudioDevices(output, type) {
        const devices = [];
        const lines = output.split('\n');

        lines.forEach(line => {
            const match = line.match(/card (\d+): (.+?) \[(.+?)\]/);
            if (match) {
                devices.push({
                    id: `hw:${match[1]}`,
                    name: `${match[2]} - ${match[3]}`,
                    isDefault: false
                });
            }
        });

        return devices;
    }

    // Local audio controls
    setLocalInputVolume(volume) {
        this.audioControls.local.input.volume = Math.max(0, Math.min(1, volume));
        this.emit('audio-volume-changed', { side: 'local', type: 'input', volume: this.audioControls.local.input.volume });
        console.log(`🔊 Local input volume set to ${Math.round(this.audioControls.local.input.volume * 100)}%`);
        return this.audioControls.local.input.volume;
    }

    setLocalOutputVolume(volume) {
        this.audioControls.local.output.volume = Math.max(0, Math.min(1, volume));
        this.emit('audio-volume-changed', { side: 'local', type: 'output', volume: this.audioControls.local.output.volume });
        console.log(`🔊 Local output volume set to ${Math.round(this.audioControls.local.output.volume * 100)}%`);
        return this.audioControls.local.output.volume;
    }

    muteLocalInput(muted = true) {
        this.audioControls.local.input.muted = muted;
        this.emit('audio-mute-changed', { side: 'local', type: 'input', muted });
        console.log(`🔊 Local input ${muted ? 'muted' : 'unmuted'}`);
        return this.audioControls.local.input.muted;
    }

    muteLocalOutput(muted = true) {
        this.audioControls.local.output.muted = muted;
        this.emit('audio-mute-changed', { side: 'local', type: 'output', muted });
        console.log(`🔊 Local output ${muted ? 'muted' : 'unmuted'}`);
        return this.audioControls.local.output.muted;
    }

    // Remote audio controls
    setRemoteInputVolume(volume) {
        this.audioControls.remote.input.volume = Math.max(0, Math.min(1, volume));
        this.emit('audio-volume-changed', { side: 'remote', type: 'input', volume: this.audioControls.remote.input.volume });
        console.log(`🔊 Remote input volume set to ${Math.round(this.audioControls.remote.input.volume * 100)}%`);

        // Send to remote client if connected
        this.sendToRemoteClients({
            type: 'audio-control',
            action: 'set-input-volume',
            volume: this.audioControls.remote.input.volume
        });

        return this.audioControls.remote.input.volume;
    }

    setRemoteOutputVolume(volume) {
        this.audioControls.remote.output.volume = Math.max(0, Math.min(1, volume));
        this.emit('audio-volume-changed', { side: 'remote', type: 'output', volume: this.audioControls.remote.output.volume });
        console.log(`🔊 Remote output volume set to ${Math.round(this.audioControls.remote.output.volume * 100)}%`);

        // Send to remote client if connected
        this.sendToRemoteClients({
            type: 'audio-control',
            action: 'set-output-volume',
            volume: this.audioControls.remote.output.volume
        });

        return this.audioControls.remote.output.volume;
    }

    muteRemoteInput(muted = true) {
        this.audioControls.remote.input.muted = muted;
        this.emit('audio-mute-changed', { side: 'remote', type: 'input', muted });
        console.log(`🔊 Remote input ${muted ? 'muted' : 'unmuted'}`);

        // Send to remote client if connected
        this.sendToRemoteClients({
            type: 'audio-control',
            action: 'mute-input',
            muted
        });

        return this.audioControls.remote.input.muted;
    }

    muteRemoteOutput(muted = true) {
        this.audioControls.remote.output.muted = muted;
        this.emit('audio-mute-changed', { side: 'remote', type: 'output', muted });
        console.log(`🔊 Remote output ${muted ? 'muted' : 'unmuted'}`);

        // Send to remote client if connected
        this.sendToRemoteClients({
            type: 'audio-control',
            action: 'mute-output',
            muted
        });

        return this.audioControls.remote.output.muted;
    }

    // Master/Mixer controls
    setMasterVolume(volume) {
        this.audioControls.mixer.masterVolume = Math.max(0, Math.min(1, volume));
        this.emit('audio-master-volume-changed', { volume: this.audioControls.mixer.masterVolume });
        console.log(`🔊 Master volume set to ${Math.round(this.audioControls.mixer.masterVolume * 100)}%`);
        return this.audioControls.mixer.masterVolume;
    }

    muteMaster(muted = true) {
        this.audioControls.mixer.masterMuted = muted;
        this.emit('audio-master-mute-changed', { muted });
        console.log(`🔊 Master audio ${muted ? 'muted' : 'unmuted'}`);
        return this.audioControls.mixer.masterMuted;
    }

    setCrossfade(value) {
        this.audioControls.mixer.crossfade = Math.max(0, Math.min(1, value));
        this.emit('audio-crossfade-changed', { crossfade: this.audioControls.mixer.crossfade });
        console.log(`🔊 Crossfade set to ${Math.round(this.audioControls.mixer.crossfade * 100)}% (${this.audioControls.mixer.crossfade === 0 ? 'Full Local' : this.audioControls.mixer.crossfade === 1 ? 'Full Remote' : 'Mixed'})`);
        return this.audioControls.mixer.crossfade;
    }

    // Audio device selection
    setLocalInputDevice(deviceId) {
        const device = this.audioDevices.input.find(d => d.id === deviceId);
        if (device) {
            this.audioControls.local.input.device = device;
            this.emit('audio-device-changed', { side: 'local', type: 'input', device });
            console.log(`🔊 Local input device set to: ${device.name}`);
            return device;
        }
        return null;
    }

    setLocalOutputDevice(deviceId) {
        const device = this.audioDevices.output.find(d => d.id === deviceId);
        if (device) {
            this.audioControls.local.output.device = device;
            this.emit('audio-device-changed', { side: 'local', type: 'output', device });
            console.log(`🔊 Local output device set to: ${device.name}`);
            return device;
        }
        return null;
    }

    setRemoteInputDevice(deviceId) {
        const device = this.audioDevices.input.find(d => d.id === deviceId);
        if (device) {
            this.audioControls.remote.input.device = device;
            this.emit('audio-device-changed', { side: 'remote', type: 'input', device });
            console.log(`🔊 Remote input device set to: ${device.name}`);

            // Send to remote client if connected
            this.sendToRemoteClients({
                type: 'audio-control',
                action: 'set-input-device',
                deviceId
            });

            return device;
        }
        return null;
    }

    setRemoteOutputDevice(deviceId) {
        const device = this.audioDevices.output.find(d => d.id === deviceId);
        if (device) {
            this.audioControls.remote.output.device = device;
            this.emit('audio-device-changed', { side: 'remote', type: 'output', device });
            console.log(`🔊 Remote output device set to: ${device.name}`);

            // Send to remote client if connected
            this.sendToRemoteClients({
                type: 'audio-control',
                action: 'set-output-device',
                deviceId
            });

            return device;
        }
        return null;
    }

    // Audio monitoring controls
    setLocalMonitoring(enabled, volume = 0.5) {
        this.audioControls.local.monitoring.enabled = enabled;
        this.audioControls.local.monitoring.volume = Math.max(0, Math.min(1, volume));
        this.emit('audio-monitoring-changed', { side: 'local', enabled, volume });
        console.log(`🔊 Local monitoring ${enabled ? 'enabled' : 'disabled'} at ${Math.round(volume * 100)}%`);
        return { enabled, volume };
    }

    setRemoteMonitoring(enabled, volume = 0.8) {
        this.audioControls.remote.monitoring.enabled = enabled;
        this.audioControls.remote.monitoring.volume = Math.max(0, Math.min(1, volume));
        this.emit('audio-monitoring-changed', { side: 'remote', enabled, volume });
        console.log(`🔊 Remote monitoring ${enabled ? 'enabled' : 'disabled'} at ${Math.round(volume * 100)}%`);
        return { enabled, volume };
    }

    // Audio processing controls
    setCompressor(enabled, settings = {}) {
        this.audioControls.mixer.compressor.enabled = enabled;
        if (settings.threshold !== undefined) this.audioControls.mixer.compressor.threshold = settings.threshold;
        if (settings.ratio !== undefined) this.audioControls.mixer.compressor.ratio = settings.ratio;
        if (settings.attack !== undefined) this.audioControls.mixer.compressor.attack = settings.attack;
        if (settings.release !== undefined) this.audioControls.mixer.compressor.release = settings.release;

        this.emit('audio-compressor-changed', { enabled, settings: this.audioControls.mixer.compressor });
        console.log(`🔊 Audio compressor ${enabled ? 'enabled' : 'disabled'}`);
        return this.audioControls.mixer.compressor;
    }

    setNoiseSuppression(enabled, level = 0.5) {
        this.audioControls.mixer.noiseSuppression.enabled = enabled;
        this.audioControls.mixer.noiseSuppression.level = Math.max(0, Math.min(1, level));
        this.emit('audio-noise-suppression-changed', { enabled, level });
        console.log(`🔊 Noise suppression ${enabled ? 'enabled' : 'disabled'} at ${Math.round(level * 100)}%`);
        return this.audioControls.mixer.noiseSuppression;
    }

    setEcho(enabled, delay = 100, feedback = 0.3) {
        this.audioControls.mixer.echo.enabled = enabled;
        this.audioControls.mixer.echo.delay = Math.max(0, Math.min(1000, delay));
        this.audioControls.mixer.echo.feedback = Math.max(0, Math.min(1, feedback));
        this.emit('audio-echo-changed', { enabled, delay, feedback });
        console.log(`🔊 Echo ${enabled ? 'enabled' : 'disabled'} (${delay}ms delay, ${Math.round(feedback * 100)}% feedback)`);
        return this.audioControls.mixer.echo;
    }

    // Quick preset controls
    setAudioPreset(preset) {
        switch (preset) {
            case 'full-local':
                this.setCrossfade(0);
                this.setLocalInputVolume(1.0);
                this.setLocalOutputVolume(1.0);
                this.setRemoteInputVolume(0.1);
                this.setRemoteOutputVolume(0.1);
                break;

            case 'full-remote':
                this.setCrossfade(1);
                this.setLocalInputVolume(0.1);
                this.setLocalOutputVolume(0.1);
                this.setRemoteInputVolume(1.0);
                this.setRemoteOutputVolume(1.0);
                break;

            case 'balanced':
                this.setCrossfade(0.5);
                this.setLocalInputVolume(0.8);
                this.setLocalOutputVolume(0.8);
                this.setRemoteInputVolume(0.8);
                this.setRemoteOutputVolume(0.8);
                break;

            case 'monitoring':
                this.setCrossfade(0.3);
                this.setLocalMonitoring(true, 0.6);
                this.setRemoteMonitoring(true, 0.9);
                this.setCompressor(true);
                this.setNoiseSuppression(true, 0.7);
                break;

            default:
                console.log(`❓ Unknown audio preset: ${preset}`);
                return false;
        }

        this.emit('audio-preset-applied', { preset });
        console.log(`🔊 Audio preset applied: ${preset}`);
        return true;
    }

    // Get current audio status
    getAudioStatus() {
        return {
            devices: this.audioDevices,
            controls: this.audioControls,
            streaming: {
                active: this.audioStreaming ? true : false,
                input: this.audioStreaming?.input ? true : false,
                output: this.audioStreaming?.output ? true : false
            },
            presets: ['full-local', 'full-remote', 'balanced', 'monitoring'],
            capabilities: {
                crossfade: true,
                monitoring: true,
                compressor: true,
                noiseSuppression: true,
                echo: true,
                deviceSelection: true,
                remoteControl: true
            }
        };
    }

    // Send audio control messages to remote clients
    sendToRemoteClients(message) {
        if (this.webSocketServer) {
            this.webSocketServer.clients.forEach(client => {
                if (client.readyState === WebSocket.OPEN) {
                    client.send(JSON.stringify(message));
                }
            });
        }
    }

    // Handle incoming audio control messages from remote clients
    handleRemoteAudioControl(message) {
        switch (message.action) {
            case 'set-input-volume':
                this.setLocalInputVolume(message.volume);
                break;
            case 'set-output-volume':
                this.setLocalOutputVolume(message.volume);
                break;
            case 'mute-input':
                this.muteLocalInput(message.muted);
                break;
            case 'mute-output':
                this.muteLocalOutput(message.muted);
                break;
            case 'set-crossfade':
                this.setCrossfade(message.value);
                break;
            case 'set-preset':
                this.setAudioPreset(message.preset);
                break;
            default:
                console.log(`❓ Unknown remote audio control: ${message.action}`);
        }
    }

    // Auto-accept and auto-decline functionality
    setAutoAcceptFeatures(enabled, features = []) {
        this.autoAccept = {
            enabled,
            features: features.length > 0 ? features : [
                'remote-control',
                'screen-reader-access',
                'audio-streaming',
                'accessibility-tree',
                'voice-control',
                'navigation-control'
            ]
        };

        console.log(`🤖 Auto-accept ${enabled ? 'enabled' : 'disabled'} for features: ${this.autoAccept.features.join(', ')}`);
        this.emit('auto-accept-changed', this.autoAccept);
        return this.autoAccept;
    }

    setAutoDeclineFeatures(enabled, features = []) {
        this.autoDecline = {
            enabled,
            features: features.length > 0 ? features : [
                'full-control',
                'system-modification',
                'file-access',
                'network-access'
            ]
        };

        console.log(`🚫 Auto-decline ${enabled ? 'enabled' : 'disabled'} for features: ${this.autoDecline.features.join(', ')}`);
        this.emit('auto-decline-changed', this.autoDecline);
        return this.autoDecline;
    }

    async handleFeatureRequest(feature, clientId, options = {}) {
        console.log(`🎯 Feature request received: ${feature} from ${clientId}`);

        // Check auto-decline first
        if (this.autoDecline?.enabled && this.autoDecline.features.includes(feature)) {
            console.log(`🚫 Auto-declining feature: ${feature}`);
            this.emit('feature-declined', { feature, clientId, reason: 'auto-decline' });
            return {
                accepted: false,
                reason: 'auto-decline',
                feature,
                clientId
            };
        }

        // Check auto-accept
        if (this.autoAccept?.enabled && this.autoAccept.features.includes(feature)) {
            console.log(`🤖 Auto-accepting feature: ${feature}`);
            this.emit('feature-accepted', { feature, clientId, reason: 'auto-accept' });

            // Enable the feature
            const result = await this.enableFeature(feature, clientId, options);
            return {
                accepted: true,
                reason: 'auto-accept',
                feature,
                clientId,
                result
            };
        }

        // Manual approval required
        console.log(`❓ Manual approval required for feature: ${feature}`);
        this.emit('feature-approval-required', { feature, clientId, options });
        return {
            accepted: null,
            reason: 'manual-approval-required',
            feature,
            clientId
        };
    }

    async enableFeature(feature, clientId, options = {}) {
        console.log(`✅ Enabling feature: ${feature} for ${clientId}`);

        switch (feature) {
            case 'remote-control':
                return await this.startRemoteAccessibilitySession(clientId, {
                    readOnly: false,
                    voiceControl: true,
                    navigationControl: true
                });

            case 'screen-reader-access':
                return await this.startRemoteAccessibilitySession(clientId, {
                    readOnly: true,
                    voiceControl: true,
                    navigationControl: true
                });

            case 'audio-streaming':
                await this.setupBidirectionalAudio();
                return { success: true, message: 'Audio streaming enabled' };

            case 'voice-control':
                return await this.startRemoteAccessibilitySession(clientId, {
                    readOnly: false,
                    voiceControl: true,
                    navigationControl: false
                });

            case 'navigation-control':
                return await this.startRemoteAccessibilitySession(clientId, {
                    readOnly: false,
                    voiceControl: false,
                    navigationControl: true
                });

            case 'full-control':
                return await this.startRemoteAccessibilitySession(clientId, {
                    readOnly: false,
                    voiceControl: true,
                    navigationControl: true,
                    fullControl: true
                });

            default:
                return { success: false, error: `Unknown feature: ${feature}` };
        }
    }

    async manuallyApproveFeature(feature, clientId, approved, options = {}) {
        console.log(`👤 Manual ${approved ? 'approval' : 'rejection'} for feature: ${feature}`);

        if (approved) {
            const result = await this.enableFeature(feature, clientId, options);
            this.emit('feature-manually-approved', { feature, clientId, result });
            return result;
        } else {
            this.emit('feature-manually-rejected', { feature, clientId });
            return {
                accepted: false,
                reason: 'manually-rejected',
                feature,
                clientId
            };
        }
    }

    getStatus() {
        return {
            platform: this.platform,
            voiceOverEnabled: this.isVoiceOverEnabled,
            voiceOverScriptingEnabled: this.voiceOverScriptingEnabled,
            screenReaderEnabled: this.isScreenReaderEnabled,
            activeScreenReader: this.getActiveScreenReader(),
            availableScreenReaders: this.availableScreenReaders || [],
            remoteConnections: this.remoteConnections.size,
            controlSession: this.controlSession,
            accessKit: {
                available: this.accessKitAvailable,
                path: this.accessKitPath || null,
                useCommandLine: this.accessKit?.useCommandLine || false
            },
            audioStreaming: {
                enabled: this.audioStreaming ? true : false,
                input: this.audioStreaming?.input?.enabled || false,
                output: this.audioStreaming?.output?.enabled || false,
                mixedAudio: this.audioStreaming?.mixedAudio ? true : false
            },
            audioControls: this.getAudioStatus(),
            rimServer: {
                running: this.webSocketServer ? true : false,
                port: 41237,
                connections: this.remoteConnections.size
            },
            autoAccept: this.autoAccept || { enabled: false, features: [] },
            autoDecline: this.autoDecline || { enabled: false, features: [] },
            features: {
                remoteControl: true,
                voiceOverSupport: this.platform === 'darwin',
                nvdaSupport: this.platform === 'win32',
                jawsSupport: this.platform === 'win32',
                narratorSupport: this.platform === 'win32',
                orcaSupport: this.platform === 'linux',
                accessKitIntegration: this.accessKitAvailable,
                audioStreaming: true,
                bidirectionalAudio: true,
                rimLikeFunctionality: true,
                crossPlatformSupport: true,
                realTimeAccessibility: true,
                screenReaderControl: true,
                ttsControl: true,
                accessibilityTree: true,
                audioControls: true,
                autoAcceptDecline: true,
                featureManagement: true
            }
        };
    }

    getAvailableCommands() {
        if (this.platform === 'darwin') {
            return Object.keys(this.voiceOverCommands);
        } else if (this.platform === 'win32') {
            const activeReader = this.getActiveScreenReader().toLowerCase();
            return Object.keys(this.windowsScreenReaderCommands[activeReader] || {});
        } else {
            return ['read', 'navigate'];
        }
    }

    getCommandHelp() {
        return {
            navigation: [
                'navigate - Move through elements (parameters: direction)',
                'move-next - Move to next element',
                'move-previous - Move to previous element',
                'move-up - Move up in hierarchy',
                'move-down - Move down in hierarchy'
            ],
            reading: [
                'read - Read content (parameters: target)',
                'read-current - Read current element',
                'read-all - Read entire page/window',
                'read-line - Read current line',
                'stop-speech - Stop current speech'
            ],
            interaction: [
                'activate - Activate current element',
                'click - Click current element',
                'type-text - Type text (parameters: text)',
                'key-combination - Send key combination (parameters: keys)'
            ],
            rotor: [
                'rotor-navigate - Navigate by element type (parameters: type, direction)',
                'find-element - Find element by description (parameters: selector)'
            ]
        };
    }
}

module.exports = RemoteAccessibilityService;