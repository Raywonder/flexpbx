const path = require('path');
const fs = require('fs-extra');

class SoundManager {
    constructor() {
        this.enabled = true;
        this.volume = 0.7;
        this.soundsDir = path.join(__dirname, '../../../assets/sounds');
        this.sounds = {
            connected: 'connected.wav',
            connectionLost: 'connection lost.wav',
            disconnect: 'disconnect.wav',
            fileTransferComplete: 'file transfer complete.wav',
            message: 'message.wav',
            reconnected: 'reconnected.wav',
            doorClosing: 'system/door-closing-fx.wav',
            messageSend: 'notifications/message-send.wav',
            messageReceive: 'notifications/message-receive.wav'
        };

        this.audioInstances = new Map();
        this.ensureSoundsExist();
    }

    async ensureSoundsExist() {
        try {
            const soundsExist = await fs.pathExists(this.soundsDir);
            if (!soundsExist) {
                console.log('⚠️ Sounds directory not found:', this.soundsDir);
                this.enabled = false;
                return;
            }

            // Check if sound files exist
            for (const [soundName, fileName] of Object.entries(this.sounds)) {
                const soundPath = path.join(this.soundsDir, fileName);
                const exists = await fs.pathExists(soundPath);
                if (!exists) {
                    console.log(`⚠️ Sound file not found: ${fileName}`);
                    delete this.sounds[soundName];
                }
            }

            console.log('🔊 Sound Manager initialized with sounds:', Object.keys(this.sounds));
        } catch (error) {
            console.error('Error checking sounds:', error);
            this.enabled = false;
        }
    }

    async playSound(soundName, options = {}) {
        if (!this.enabled) {
            return { success: false, reason: 'Sound disabled' };
        }

        const fileName = this.sounds[soundName];
        if (!fileName) {
            console.log(`⚠️ Sound not found: ${soundName}`);
            return { success: false, reason: 'Sound not found' };
        }

        const soundPath = path.join(this.soundsDir, fileName);

        try {
            // On macOS, use afplay for WAV files
            if (process.platform === 'darwin') {
                return await this.playMacOSSound(soundPath, options);
            } else if (process.platform === 'linux') {
                return await this.playLinuxSound(soundPath, options);
            } else if (process.platform === 'win32') {
                return await this.playWindowsSound(soundPath, options);
            } else {
                return { success: false, reason: 'Platform not supported' };
            }
        } catch (error) {
            console.error(`Error playing sound ${soundName}:`, error);
            return { success: false, error: error.message };
        }
    }

    async playMacOSSound(soundPath, options = {}) {
        const { spawn } = require('child_process');
        const { volume = this.volume, background = true } = options;

        return new Promise((resolve) => {
            // Use afplay with volume control
            const args = [soundPath];
            if (volume < 1.0) {
                args.push('--volume', volume.toString());
            }

            const player = spawn('afplay', args, {
                detached: background,
                stdio: background ? 'ignore' : ['ignore', 'pipe', 'pipe']
            });

            if (background) {
                player.unref();
                resolve({ success: true, message: 'Sound playing in background' });
            } else {
                player.on('close', (code) => {
                    resolve({
                        success: code === 0,
                        message: code === 0 ? 'Sound played successfully' : 'Sound playback failed'
                    });
                });

                player.on('error', (error) => {
                    resolve({ success: false, error: error.message });
                });
            }
        });
    }

    async playLinuxSound(soundPath, options = {}) {
        const { spawn } = require('child_process');
        const { background = true } = options;

        return new Promise((resolve) => {
            // Try different Linux audio players
            const players = ['paplay', 'aplay', 'mpg123', 'mpv'];
            let playerFound = false;

            for (const playerCmd of players) {
                try {
                    const player = spawn(playerCmd, [soundPath], {
                        detached: background,
                        stdio: background ? 'ignore' : ['ignore', 'pipe', 'pipe']
                    });

                    playerFound = true;

                    if (background) {
                        player.unref();
                        resolve({ success: true, message: `Sound playing with ${playerCmd}` });
                    } else {
                        player.on('close', (code) => {
                            resolve({
                                success: code === 0,
                                message: code === 0 ? `Sound played with ${playerCmd}` : 'Sound playback failed'
                            });
                        });

                        player.on('error', (error) => {
                            resolve({ success: false, error: error.message });
                        });
                    }
                    break;
                } catch (error) {
                    continue;
                }
            }

            if (!playerFound) {
                resolve({ success: false, reason: 'No audio player found on Linux' });
            }
        });
    }

    async playWindowsSound(soundPath, options = {}) {
        const { spawn } = require('child_process');
        const { background = true } = options;

        return new Promise((resolve) => {
            // Use Windows Media Player or PowerShell
            const player = spawn('powershell', [
                '-c',
                `(New-Object Media.SoundPlayer '${soundPath}').PlaySync();`
            ], {
                detached: background,
                stdio: background ? 'ignore' : ['ignore', 'pipe', 'pipe']
            });

            if (background) {
                player.unref();
                resolve({ success: true, message: 'Sound playing with PowerShell' });
            } else {
                player.on('close', (code) => {
                    resolve({
                        success: code === 0,
                        message: code === 0 ? 'Sound played with PowerShell' : 'Sound playback failed'
                    });
                });

                player.on('error', (error) => {
                    resolve({ success: false, error: error.message });
                });
            }
        });
    }

    // Specific sound methods for common FlexPBX actions
    async playConnectionSound() {
        console.log('🔊 Playing connection sound...');
        return await this.playSound('connected');
    }

    async playConnectionLostSound() {
        console.log('🔊 Playing connection lost sound...');
        return await this.playSound('connectionLost');
    }

    async playDisconnectSound() {
        console.log('🔊 Playing disconnect sound...');
        return await this.playSound('disconnect');
    }

    async playFileTransferCompleteSound() {
        console.log('🔊 Playing file transfer complete sound...');
        return await this.playSound('fileTransferComplete');
    }

    async playMessageSound() {
        console.log('🔊 Playing message sound...');
        return await this.playSound('message');
    }

    async playReconnectedSound() {
        console.log('🔊 Playing reconnected sound...');
        return await this.playSound('reconnected');
    }

    async playDoorClosingSound() {
        console.log('🔊 Playing door closing FX sound...');
        return await this.playSound('doorClosing');
    }

    async playMessageSendSound() {
        console.log('🔊 Playing message send sound...');
        return await this.playSound('messageSend');
    }

    async playMessageReceiveSound() {
        console.log('🔊 Playing message receive sound...');
        return await this.playSound('messageReceive');
    }

    // Background service specific sounds
    async playServiceStartedSound() {
        return await this.playConnectionSound();
    }

    async playServiceStoppedSound() {
        return await this.playDisconnectSound();
    }

    async playServiceErrorSound() {
        return await this.playConnectionLostSound();
    }

    async playBackgroundModeSound() {
        return await this.playMessageSound();
    }

    async playCopyPartyStartedSound() {
        return await this.playReconnectedSound();
    }

    async playDeploymentCompleteSound() {
        return await this.playFileTransferCompleteSound();
    }

    // Action-specific sounds
    async playLogoutSound() {
        console.log('🔊 Playing logout action sound...');
        return await this.playDoorClosingSound();
    }

    async playSessionEndSound() {
        console.log('🔊 Playing session end action sound...');
        return await this.playDoorClosingSound();
    }

    // Configuration methods
    setEnabled(enabled) {
        this.enabled = enabled;
        console.log(`🔊 Sound Manager ${enabled ? 'enabled' : 'disabled'}`);
    }

    setVolume(volume) {
        this.volume = Math.max(0, Math.min(1, volume));
        console.log(`🔊 Volume set to ${Math.round(this.volume * 100)}%`);
    }

    getStatus() {
        return {
            enabled: this.enabled,
            volume: this.volume,
            soundsDir: this.soundsDir,
            availableSounds: Object.keys(this.sounds),
            platform: process.platform
        };
    }

    // Test all sounds
    async testAllSounds(delay = 2000) {
        console.log('🔊 Testing all available sounds...');

        for (const soundName of Object.keys(this.sounds)) {
            console.log(`Testing sound: ${soundName}`);
            await this.playSound(soundName, { background: false });

            if (delay > 0) {
                await new Promise(resolve => setTimeout(resolve, delay));
            }
        }

        console.log('🔊 Sound test completed');
    }

    // Get sound mappings for UI
    getSoundMappings() {
        return {
            backgroundServices: {
                started: 'connected',
                stopped: 'disconnect',
                error: 'connectionLost',
                backgroundMode: 'message'
            },
            copyParty: {
                started: 'reconnected',
                stopped: 'disconnect',
                uploadComplete: 'fileTransferComplete'
            },
            connections: {
                connected: 'connected',
                disconnected: 'disconnect',
                lost: 'connectionLost',
                reconnected: 'reconnected'
            },
            deployments: {
                complete: 'fileTransferComplete',
                started: 'connected',
                failed: 'connectionLost'
            },
            general: {
                notification: 'message',
                success: 'connected',
                error: 'connectionLost',
                info: 'message'
            },
            actions: {
                doorClosing: 'doorClosing',
                logout: 'doorClosing',
                sessionEnd: 'doorClosing'
            },
            messaging: {
                send: 'messageSend',
                receive: 'messageReceive',
                notification: 'messageReceive'
            }
        };
    }
}

module.exports = SoundManager;