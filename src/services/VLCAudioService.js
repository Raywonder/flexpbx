/**
 * 🎵 FlexPhone VLC Audio Service
 * Handles audio playback using VLC for reliable cross-platform audio support
 */

const EventEmitter = require('events');
const { spawn } = require('child_process');
const path = require('path');
const fs = require('fs');

class VLCAudioService extends EventEmitter {
    constructor() {
        super();
        this.vlcProcess = null;
        this.isInitialized = false;
        this.audioQueue = [];
        this.isPlaying = false;
        this.volume = 0.7;

        // VLC installation paths by platform
        this.vlcPaths = {
            darwin: [
                '/Applications/VLC.app/Contents/MacOS/VLC',
                '/usr/local/bin/vlc',
                '/opt/homebrew/bin/vlc'
            ],
            win32: [
                'C:\\Program Files\\VideoLAN\\VLC\\vlc.exe',
                'C:\\Program Files (x86)\\VideoLAN\\VLC\\vlc.exe',
                'vlc.exe'
            ],
            linux: [
                '/usr/bin/vlc',
                '/usr/local/bin/vlc',
                '/snap/bin/vlc'
            ]
        };

        console.log('🎵 VLCAudioService initialized');
    }

    async init() {
        try {
            console.log('🎵 Initializing VLC Audio Service...');

            // Find VLC installation
            const vlcPath = await this.findVLCInstallation();
            if (!vlcPath) {
                throw new Error('VLC not found. Please install VLC Media Player.');
            }

            this.vlcExecutable = vlcPath;
            console.log('✅ Found VLC at:', vlcPath);

            // Test VLC with a silent startup
            await this.testVLCConnection();

            this.isInitialized = true;
            console.log('✅ VLC Audio Service initialized successfully');
            this.emit('initialized');

            return true;
        } catch (error) {
            console.error('❌ Failed to initialize VLC Audio Service:', error);
            return false;
        }
    }

    async findVLCInstallation() {
        const platform = process.platform;
        const paths = this.vlcPaths[platform] || [];

        for (const vlcPath of paths) {
            try {
                if (await this.fileExists(vlcPath)) {
                    return vlcPath;
                }
            } catch (error) {
                // Continue searching
            }
        }

        return null;
    }

    async fileExists(filePath) {
        try {
            await fs.promises.access(filePath, fs.constants.F_OK);
            return true;
        } catch (error) {
            return false;
        }
    }

    async testVLCConnection() {
        return new Promise((resolve, reject) => {
            const testProcess = spawn(this.vlcExecutable, [
                '--version'
            ], {
                stdio: 'pipe'
            });

            let output = '';
            testProcess.stdout.on('data', (data) => {
                output += data.toString();
            });

            testProcess.on('close', (code) => {
                if (code === 0 && output.includes('VLC')) {
                    console.log('✅ VLC version test successful');
                    resolve();
                } else {
                    reject(new Error('VLC version test failed'));
                }
            });

            testProcess.on('error', (error) => {
                reject(error);
            });

            // Timeout after 5 seconds
            setTimeout(() => {
                testProcess.kill();
                reject(new Error('VLC test timeout'));
            }, 5000);
        });
    }

    async playWelcomeTones() {
        if (!this.isInitialized) {
            console.warn('⚠️ VLC Audio Service not initialized');
            return false;
        }

        try {
            console.log('🎵 Playing welcome tones with VLC...');

            // Generate welcome tone sequence as temporary audio files
            const toneFiles = await this.generateWelcomeToneFiles();

            // Play each tone in sequence
            for (const toneFile of toneFiles) {
                await this.playAudioFile(toneFile);
                await this.sleep(50); // Small gap between tones
            }

            // Clean up temporary files
            await this.cleanupToneFiles(toneFiles);

            console.log('✅ Welcome tones completed');
            this.emit('welcomeTonesCompleted');
            return true;

        } catch (error) {
            console.error('❌ Failed to play welcome tones:', error);
            return false;
        }
    }

    async generateWelcomeToneFiles() {
        const tones = [
            { frequency: 523, duration: 0.3 }, // C5
            { frequency: 659, duration: 0.3 }, // E5
            { frequency: 784, duration: 0.5 }  // G5
        ];

        const toneFiles = [];
        const tempDir = path.join(__dirname, '..', '..', 'temp');

        // Ensure temp directory exists
        if (!await this.fileExists(tempDir)) {
            await fs.promises.mkdir(tempDir, { recursive: true });
        }

        for (let i = 0; i < tones.length; i++) {
            const tone = tones[i];
            const toneFile = path.join(tempDir, `welcome_tone_${i}.wav`);

            await this.generateToneFile(tone.frequency, tone.duration, toneFile);
            toneFiles.push(toneFile);
        }

        return toneFiles;
    }

    async generateToneFile(frequency, duration, outputFile) {
        return new Promise((resolve, reject) => {
            // Use VLC to generate a tone
            const vlcArgs = [
                '--intf', 'dummy',
                '--dummy-quiet',
                '--no-video',
                `tone://frequency=${frequency}`,
                `--run-time=${duration}`,
                '--sout', `#transcode{acodec=s16l,channels=1,samplerate=44100}:std{access=file,mux=wav,dst=${outputFile}}`,
                'vlc://quit'
            ];

            const vlcProcess = spawn(this.vlcExecutable, vlcArgs, {
                stdio: 'pipe'
            });

            vlcProcess.on('close', (code) => {
                if (code === 0) {
                    resolve();
                } else {
                    reject(new Error(`VLC tone generation failed with code ${code}`));
                }
            });

            vlcProcess.on('error', (error) => {
                reject(error);
            });

            // Timeout after 10 seconds
            setTimeout(() => {
                vlcProcess.kill();
                reject(new Error('VLC tone generation timeout'));
            }, 10000);
        });
    }

    async playAudioFile(filePath) {
        if (!await this.fileExists(filePath)) {
            throw new Error(`Audio file not found: ${filePath}`);
        }

        return new Promise((resolve, reject) => {
            const vlcArgs = [
                '--intf', 'dummy',
                '--dummy-quiet',
                '--no-video',
                '--play-and-exit',
                `--volume=${Math.round(this.volume * 100)}`,
                filePath
            ];

            const vlcProcess = spawn(this.vlcExecutable, vlcArgs, {
                stdio: 'pipe'
            });

            vlcProcess.on('close', (code) => {
                resolve();
            });

            vlcProcess.on('error', (error) => {
                reject(error);
            });

            // Timeout after 30 seconds
            setTimeout(() => {
                vlcProcess.kill();
                reject(new Error('VLC playback timeout'));
            }, 30000);
        });
    }

    async cleanupToneFiles(toneFiles) {
        for (const toneFile of toneFiles) {
            try {
                await fs.promises.unlink(toneFile);
            } catch (error) {
                console.warn('⚠️ Failed to delete temp tone file:', toneFile);
            }
        }
    }

    async playRingtone(ringtoneType = 'default') {
        if (!this.isInitialized) {
            console.warn('⚠️ VLC Audio Service not initialized');
            return false;
        }

        try {
            console.log(`🔔 Playing ringtone: ${ringtoneType}`);

            // Generate ringtone based on type
            const ringtoneFile = await this.generateRingtoneFile(ringtoneType);

            // Play ringtone in loop until stopped
            this.currentRingtone = await this.playAudioFileLoop(ringtoneFile);

            return true;
        } catch (error) {
            console.error('❌ Failed to play ringtone:', error);
            return false;
        }
    }

    async stopRingtone() {
        if (this.currentRingtone) {
            this.currentRingtone.kill();
            this.currentRingtone = null;
            console.log('✅ Ringtone stopped');
        }
    }

    async playAudioFileLoop(filePath) {
        const vlcArgs = [
            '--intf', 'dummy',
            '--dummy-quiet',
            '--no-video',
            '--loop',
            `--volume=${Math.round(this.volume * 100)}`,
            filePath
        ];

        const vlcProcess = spawn(this.vlcExecutable, vlcArgs, {
            stdio: 'pipe'
        });

        return vlcProcess;
    }

    async generateRingtoneFile(type) {
        const tempDir = path.join(__dirname, '..', '..', 'temp');
        const ringtoneFile = path.join(tempDir, `ringtone_${type}.wav`);

        // Define ringtone patterns
        const patterns = {
            default: [
                { frequency: 440, duration: 1.0 },
                { frequency: 0, duration: 0.5 },
                { frequency: 440, duration: 1.0 },
                { frequency: 0, duration: 2.0 }
            ],
            modern: [
                { frequency: 523, duration: 0.2 },
                { frequency: 659, duration: 0.2 },
                { frequency: 784, duration: 0.3 },
                { frequency: 0, duration: 0.3 }
            ]
        };

        const pattern = patterns[type] || patterns.default;

        // Generate complex ringtone pattern (simplified for now)
        await this.generateToneFile(440, 2.0, ringtoneFile);

        return ringtoneFile;
    }

    setVolume(volume) {
        this.volume = Math.max(0, Math.min(1, volume));
        console.log(`🔊 VLC Audio volume set to ${Math.round(this.volume * 100)}%`);
        this.emit('volumeChanged', this.volume);
    }

    getVolume() {
        return this.volume;
    }

    async sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    async shutdown() {
        console.log('🎵 Shutting down VLC Audio Service...');

        if (this.currentRingtone) {
            await this.stopRingtone();
        }

        if (this.vlcProcess) {
            this.vlcProcess.kill();
            this.vlcProcess = null;
        }

        // Clean up temp directory
        const tempDir = path.join(__dirname, '..', '..', 'temp');
        try {
            const files = await fs.promises.readdir(tempDir);
            for (const file of files) {
                if (file.endsWith('.wav')) {
                    await fs.promises.unlink(path.join(tempDir, file));
                }
            }
        } catch (error) {
            // Temp dir cleanup is best effort
        }

        this.isInitialized = false;
        console.log('✅ VLC Audio Service shutdown complete');
    }
}

module.exports = VLCAudioService;