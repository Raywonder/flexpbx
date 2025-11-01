/**
 * 🔊 FlexPhone Audio Device Service
 * Manages audio input/output device enumeration and selection
 */

const EventEmitter = require('events');

class AudioDeviceService extends EventEmitter {
    constructor() {
        super();
        this.availableDevices = {
            audioInput: [],
            audioOutput: []
        };
        this.selectedDevices = {
            audioInput: null,
            audioOutput: null
        };
        this.isInitialized = false;
        this.permissionGranted = false;
    }

    async init() {
        try {
            console.log('🔊 Initializing Audio Device Service...');

            // Check if mediaDevices API is available
            if (!navigator.mediaDevices || !navigator.mediaDevices.enumerateDevices) {
                throw new Error('MediaDevices API not supported');
            }

            // Request microphone permission first
            await this.requestPermissions();

            // Enumerate devices
            await this.enumerateDevices();

            // Listen for device changes
            this.setupDeviceChangeListener();

            this.isInitialized = true;
            console.log('✅ Audio Device Service initialized');
            this.emit('initialized');

            return true;
        } catch (error) {
            console.error('❌ Failed to initialize Audio Device Service:', error);
            return false;
        }
    }

    async requestPermissions() {
        try {
            console.log('🎤 Requesting audio permissions...');

            // Request microphone access to enable device enumeration with labels
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });

            // Stop the stream immediately - we just needed permission
            stream.getTracks().forEach(track => track.stop());

            this.permissionGranted = true;
            console.log('✅ Audio permissions granted');

        } catch (error) {
            console.warn('⚠️ Audio permissions denied:', error);
            this.permissionGranted = false;
            throw error;
        }
    }

    async enumerateDevices() {
        try {
            console.log('🔍 Enumerating audio devices...');

            const devices = await navigator.mediaDevices.enumerateDevices();

            // Filter and categorize devices
            this.availableDevices.audioInput = devices.filter(device =>
                device.kind === 'audioinput'
            ).map(device => ({
                deviceId: device.deviceId,
                label: device.label || `Microphone ${device.deviceId.substr(0, 8)}`,
                kind: device.kind
            }));

            this.availableDevices.audioOutput = devices.filter(device =>
                device.kind === 'audiooutput'
            ).map(device => ({
                deviceId: device.deviceId,
                label: device.label || `Speaker ${device.deviceId.substr(0, 8)}`,
                kind: device.kind
            }));

            // Set default devices if none selected
            if (!this.selectedDevices.audioInput && this.availableDevices.audioInput.length > 0) {
                this.selectedDevices.audioInput = this.availableDevices.audioInput[0];
            }

            if (!this.selectedDevices.audioOutput && this.availableDevices.audioOutput.length > 0) {
                this.selectedDevices.audioOutput = this.availableDevices.audioOutput[0];
            }

            console.log(`🎤 Found ${this.availableDevices.audioInput.length} audio input devices`);
            console.log(`🔊 Found ${this.availableDevices.audioOutput.length} audio output devices`);

            // Emit devices updated event
            this.emit('devicesUpdated', this.availableDevices);

            return this.availableDevices;

        } catch (error) {
            console.error('❌ Failed to enumerate devices:', error);
            throw error;
        }
    }

    setupDeviceChangeListener() {
        if (navigator.mediaDevices && navigator.mediaDevices.addEventListener) {
            navigator.mediaDevices.addEventListener('devicechange', () => {
                console.log('🔄 Audio devices changed, re-enumerating...');
                this.enumerateDevices();
            });
        }
    }

    getAvailableDevices() {
        return this.availableDevices;
    }

    getSelectedDevices() {
        return this.selectedDevices;
    }

    async setInputDevice(deviceId) {
        try {
            const device = this.availableDevices.audioInput.find(d => d.deviceId === deviceId);
            if (!device) {
                throw new Error(`Input device ${deviceId} not found`);
            }

            // Test the device by creating a stream
            const stream = await navigator.mediaDevices.getUserMedia({
                audio: { deviceId: { exact: deviceId } }
            });

            // Stop test stream
            stream.getTracks().forEach(track => track.stop());

            this.selectedDevices.audioInput = device;
            console.log(`🎤 Selected input device: ${device.label}`);

            this.emit('inputDeviceChanged', device);
            return true;

        } catch (error) {
            console.error('❌ Failed to set input device:', error);
            return false;
        }
    }

    async setOutputDevice(deviceId) {
        try {
            const device = this.availableDevices.audioOutput.find(d => d.deviceId === deviceId);
            if (!device) {
                throw new Error(`Output device ${deviceId} not found`);
            }

            this.selectedDevices.audioOutput = device;
            console.log(`🔊 Selected output device: ${device.label}`);

            this.emit('outputDeviceChanged', device);
            return true;

        } catch (error) {
            console.error('❌ Failed to set output device:', error);
            return false;
        }
    }

    async getUserMediaWithSelectedDevice() {
        try {
            const constraints = {
                audio: true
            };

            // Use selected input device if available
            if (this.selectedDevices.audioInput) {
                constraints.audio = {
                    deviceId: { exact: this.selectedDevices.audioInput.deviceId },
                    echoCancellation: true,
                    noiseSuppression: true,
                    autoGainControl: true
                };
            }

            const stream = await navigator.mediaDevices.getUserMedia(constraints);
            console.log('🎤 Got media stream with selected device');

            return stream;

        } catch (error) {
            console.error('❌ Failed to get user media with selected device:', error);
            throw error;
        }
    }

    async testDevice(deviceId, kind) {
        try {
            if (kind === 'audioinput') {
                const stream = await navigator.mediaDevices.getUserMedia({
                    audio: { deviceId: { exact: deviceId } }
                });

                // Analyze audio levels for 2 seconds
                const audioContext = new (window.AudioContext || window.webkitAudioContext)();
                const analyser = audioContext.createAnalyser();
                const microphone = audioContext.createMediaStreamSource(stream);

                microphone.connect(analyser);
                analyser.fftSize = 256;

                const bufferLength = analyser.frequencyBinCount;
                const dataArray = new Uint8Array(bufferLength);

                let maxLevel = 0;
                const testDuration = 2000; // 2 seconds
                const startTime = Date.now();

                return new Promise((resolve) => {
                    const checkLevel = () => {
                        analyser.getByteFrequencyData(dataArray);
                        const average = dataArray.reduce((sum, value) => sum + value, 0) / bufferLength;
                        maxLevel = Math.max(maxLevel, average);

                        if (Date.now() - startTime < testDuration) {
                            requestAnimationFrame(checkLevel);
                        } else {
                            // Cleanup
                            stream.getTracks().forEach(track => track.stop());
                            audioContext.close();

                            resolve({
                                working: maxLevel > 5, // Threshold for detecting audio
                                level: maxLevel
                            });
                        }
                    };

                    checkLevel();
                });

            } else {
                // For output devices, we can't really test without playing audio
                return { working: true, level: 0 };
            }

        } catch (error) {
            console.error('❌ Device test failed:', error);
            return { working: false, level: 0, error: error.message };
        }
    }

    // Get constraints for getUserMedia with selected devices
    getMediaConstraints(includeVideo = false) {
        const constraints = {
            audio: true,
            video: includeVideo
        };

        if (this.selectedDevices.audioInput) {
            constraints.audio = {
                deviceId: { exact: this.selectedDevices.audioInput.deviceId },
                echoCancellation: true,
                noiseSuppression: true,
                autoGainControl: true
            };
        }

        return constraints;
    }
}

module.exports = AudioDeviceService;