/**
 * Cross-Platform Speech Service
 * Handles text-to-speech with platform-specific optimizations
 */

const { spawn, exec } = require('child_process');
const os = require('os');
const path = require('path');

class CrossPlatformSpeech {
    constructor() {
        this.platform = os.platform();
        this.isInitialized = false;
        this.voices = {};
        this.settings = {
            rate: 200,          // Speech rate (words per minute)
            volume: 0.8,        // Volume (0.0 to 1.0)
            voice: null,        // Selected voice
            pitch: 1.0          // Voice pitch
        };

        this.platformCommands = {
            darwin: {
                command: 'say',
                listVoices: 'say -v ?',
                testCommand: 'which say'
            },
            win32: {
                command: 'powershell',
                listVoices: 'Get-WmiObject -Class Win32_SpeechVoice | Select-Object Name',
                testCommand: 'powershell Get-Command Speak-Text -ErrorAction SilentlyContinue'
            },
            linux: {
                command: 'espeak',
                listVoices: 'espeak --voices',
                testCommand: 'which espeak'
            }
        };

        this.eventMessages = {
            holdMusicStarted: "Hold music streaming has started",
            holdMusicStopped: "Hold music streaming has stopped",
            clientConnected: "New client connected to hold music server",
            clientDisconnected: "Client disconnected from hold music server",
            serverStarted: "FlexPBX services have started successfully",
            serverStopped: "FlexPBX services have been stopped",
            callIncoming: "Incoming call received",
            callAnswered: "Call has been answered",
            callEnded: "Call has ended",
            serviceError: "Service error detected",
            portConflict: "Port conflict resolved automatically",
            systemReady: "All FlexPBX systems are ready and operational"
        };
    }

    async init() {
        console.log(`🔊 Initializing Cross-Platform Speech for ${this.platform}...`);

        try {
            await this.detectSystemCapabilities();
            await this.loadSystemVoices();
            await this.optimizePlatformSettings();

            this.isInitialized = true;
            console.log('✅ Cross-Platform Speech initialized successfully');

            // Test speech with welcome message
            this.queueSpeech('systemReady');

            return true;
        } catch (error) {
            console.error('❌ Failed to initialize speech system:', error);
            return false;
        }
    }

    async detectSystemCapabilities() {
        const platformConfig = this.platformCommands[this.platform];

        if (!platformConfig) {
            throw new Error(`Unsupported platform: ${this.platform}`);
        }

        return new Promise((resolve, reject) => {
            exec(platformConfig.testCommand, (error, stdout, stderr) => {
                if (error) {
                    console.log(`⚠️ TTS not available on ${this.platform}, using fallback`);
                    this.useFallback = true;
                    resolve();
                } else {
                    console.log(`✅ TTS system detected on ${this.platform}`);
                    resolve();
                }
            });
        });
    }

    async loadSystemVoices() {
        if (this.useFallback) return;

        const platformConfig = this.platformCommands[this.platform];

        return new Promise((resolve) => {
            exec(platformConfig.listVoices, (error, stdout, stderr) => {
                if (!error && stdout) {
                    this.parseVoices(stdout);
                    console.log(`📢 Loaded ${Object.keys(this.voices).length} system voices`);
                }
                resolve();
            });
        });
    }

    parseVoices(voiceData) {
        switch (this.platform) {
            case 'darwin':
                this.parseMacOSVoices(voiceData);
                break;
            case 'win32':
                this.parseWindowsVoices(voiceData);
                break;
            case 'linux':
                this.parseLinuxVoices(voiceData);
                break;
        }
    }

    parseMacOSVoices(voiceData) {
        const lines = voiceData.split('\n').filter(line => line.trim());

        this.voices = {
            Alex: { name: 'Alex', language: 'en_US', quality: 'high', gender: 'male' },
            Samantha: { name: 'Samantha', language: 'en_US', quality: 'high', gender: 'female' },
            Victoria: { name: 'Victoria', language: 'en_UK', quality: 'high', gender: 'female' },
            Daniel: { name: 'Daniel', language: 'en_UK', quality: 'high', gender: 'male' },
            Karen: { name: 'Karen', language: 'en_AU', quality: 'high', gender: 'female' },
            Veena: { name: 'Veena', language: 'en_IN', quality: 'high', gender: 'female' }
        };

        // Parse actual system voices if available
        lines.forEach(line => {
            const match = line.match(/^(\w+)\s+(.+)/);
            if (match) {
                const [, name, description] = match;
                this.voices[name] = {
                    name,
                    description: description.trim(),
                    quality: 'system',
                    available: true
                };
            }
        });

        // Set default voice for professional announcements
        this.settings.voice = 'Alex';
    }

    parseWindowsVoices(voiceData) {
        // Parse Windows SAPI voices
        this.voices = {
            'Microsoft Zira': { name: 'Microsoft Zira', language: 'en_US', gender: 'female' },
            'Microsoft David': { name: 'Microsoft David', language: 'en_US', gender: 'male' },
            'Microsoft Mark': { name: 'Microsoft Mark', language: 'en_US', gender: 'male' }
        };

        this.settings.voice = 'Microsoft Zira';
    }

    parseLinuxVoices(voiceData) {
        // Parse espeak voices
        this.voices = {
            'en': { name: 'English', language: 'en', variant: 'default' },
            'en+f3': { name: 'English Female', language: 'en', variant: 'female' },
            'en+m1': { name: 'English Male', language: 'en', variant: 'male' }
        };

        this.settings.voice = 'en+f3';
    }

    async optimizePlatformSettings() {
        switch (this.platform) {
            case 'darwin':
                // macOS optimizations
                this.settings.rate = 200;  // Optimal for professional speech
                this.settings.volume = 0.7; // Good balance with system sounds
                break;

            case 'win32':
                // Windows optimizations
                this.settings.rate = 0;     // Windows uses different rate scale
                this.settings.volume = 0.8;
                break;

            case 'linux':
                // Linux optimizations
                this.settings.rate = 180;   // espeak words per minute
                this.settings.volume = 0.8;
                break;
        }

        console.log(`⚙️ Platform settings optimized for ${this.platform}`);
    }

    speak(messageKey, customMessage = null) {
        if (!this.isInitialized) {
            console.log('⚠️ Speech system not initialized');
            return;
        }

        const message = customMessage || this.eventMessages[messageKey] || messageKey;

        if (this.useFallback) {
            console.log(`🔊 [SPEECH]: ${message}`);
            return;
        }

        this.speakPlatformSpecific(message);
    }

    speakPlatformSpecific(message) {
        switch (this.platform) {
            case 'darwin':
                this.speakMacOS(message);
                break;
            case 'win32':
                this.speakWindows(message);
                break;
            case 'linux':
                this.speakLinux(message);
                break;
            default:
                console.log(`🔊 [SPEECH]: ${message}`);
        }
    }

    speakMacOS(message) {
        const args = [
            '-v', this.settings.voice || 'Alex',
            '-r', this.settings.rate.toString()
        ];

        // Add volume control if supported
        if (this.settings.volume !== 1.0) {
            args.push('--data-format=LEI16@22050');
        }

        args.push(message);

        const process = spawn('say', args);

        process.on('error', (error) => {
            console.error('❌ macOS speech error:', error.message);
        });

        process.on('close', (code) => {
            if (code !== 0) {
                console.error(`❌ macOS speech failed with code ${code}`);
            }
        });

        console.log(`🔊 [macOS]: ${message}`);
    }

    speakWindows(message) {
        const script = `
            Add-Type -AssemblyName System.Speech
            $synth = New-Object System.Speech.Synthesis.SpeechSynthesizer
            $synth.Volume = ${Math.round(this.settings.volume * 100)}
            $synth.Rate = ${this.settings.rate}
            if ("${this.settings.voice}") {
                $synth.SelectVoice("${this.settings.voice}")
            }
            $synth.Speak("${message.replace(/"/g, '""')}")
            $synth.Dispose()
        `;

        const process = spawn('powershell', ['-Command', script]);

        process.on('error', (error) => {
            console.error('❌ Windows speech error:', error.message);
        });

        console.log(`🔊 [Windows]: ${message}`);
    }

    speakLinux(message) {
        const args = [
            '-v', this.settings.voice || 'en+f3',
            '-s', this.settings.rate.toString(),
            '-a', Math.round(this.settings.volume * 200).toString(),
            message
        ];

        const process = spawn('espeak', args);

        process.on('error', (error) => {
            console.error('❌ Linux speech error:', error.message);
            // Fallback to festival if espeak fails
            this.tryFestival(message);
        });

        console.log(`🔊 [Linux]: ${message}`);
    }

    tryFestival(message) {
        const process = spawn('festival', ['--tts'], { stdio: 'pipe' });

        process.stdin.write(message);
        process.stdin.end();

        process.on('error', () => {
            console.log(`🔊 [Fallback]: ${message}`);
        });
    }

    // Event-specific speech methods
    announceHoldMusicEvent(event, details = {}) {
        switch (event) {
            case 'started':
                this.queueSpeech('holdMusicStarted', `Hold music started for ${details.category || 'default'} category`);
                break;
            case 'stopped':
                this.queueSpeech('holdMusicStopped');
                break;
            case 'clientJoined':
                this.queueSpeech('clientConnected', `Client connected. ${details.totalClients} clients now listening`);
                break;
            case 'clientLeft':
                this.queueSpeech('clientDisconnected', `Client disconnected. ${details.totalClients} clients remaining`);
                break;
        }
    }

    announceSystemEvent(event, details = {}) {
        switch (event) {
            case 'startup':
                this.queueSpeech('serverStarted');
                break;
            case 'shutdown':
                this.queueSpeech('serverStopped');
                break;
            case 'error':
                this.queueSpeech('serviceError', `Error in ${details.service}: ${details.error}`);
                break;
            case 'portResolved':
                this.queueSpeech('portConflict', `Port conflict resolved. ${details.service} moved to port ${details.newPort}`);
                break;
            case 'ready':
                this.queueSpeech('systemReady');
                break;
        }
    }

    // Settings management
    updateSettings(newSettings) {
        this.settings = { ...this.settings, ...newSettings };
        console.log('⚙️ Speech settings updated:', this.settings);
    }

    getAvailableVoices() {
        return this.voices;
    }

    getSettings() {
        return { ...this.settings };
    }

    // Test speech functionality
    testSpeech() {
        const testMessage = `FlexPBX speech system test for ${this.platform}. Current voice: ${this.settings.voice}. Rate: ${this.settings.rate}. Volume: ${this.settings.volume}.`;
        this.speak(null, testMessage);
    }

    // Queue management for multiple speech events
    speakQueue = [];
    isSpeaking = false;

    queueSpeech(messageKey, customMessage = null) {
        this.speakQueue.push({ messageKey, customMessage });
        this.processQueue();
    }

    processQueue() {
        if (this.isSpeaking || this.speakQueue.length === 0) return;

        this.isSpeaking = true;
        const { messageKey, customMessage } = this.speakQueue.shift();

        this.speak(messageKey, customMessage);

        // Estimate speech duration and process next item
        const message = customMessage || this.eventMessages[messageKey] || messageKey;
        const estimatedDuration = (message.length / 10) * 1000; // Rough estimate

        setTimeout(() => {
            this.isSpeaking = false;
            this.processQueue();
        }, estimatedDuration);
    }
}

module.exports = CrossPlatformSpeech;