const fs = require('fs-extra');
const path = require('path');
const crypto = require('crypto');
const { exec } = require('child_process');
const { promisify } = require('util');
const execAsync = promisify(exec);

class MediaService {
    constructor() {
        this.supportedFormats = {
            audio: {
                input: ['wav', 'mp3', 'flac', 'aac', 'ogg', 'm4a', 'wma'],
                output: ['wav', 'mp3', 'flac', 'aac', 'ogg'],
                quality: {
                    low: { bitrate: '128k', sampleRate: '22050' },
                    medium: { bitrate: '192k', sampleRate: '44100' },
                    high: { bitrate: '320k', sampleRate: '48000' },
                    lossless: { bitrate: null, sampleRate: '48000', format: 'flac' }
                }
            },
            video: {
                input: ['mp4', 'avi', 'mov', 'mkv', 'webm', 'flv', 'wmv'],
                output: ['mp4', 'webm', 'mov', 'avi'],
                codecs: {
                    h264: { quality: 'high', compatibility: 'excellent' },
                    h265: { quality: 'highest', compatibility: 'good' },
                    vp9: { quality: 'high', compatibility: 'good' },
                    av1: { quality: 'highest', compatibility: 'limited' }
                }
            }
        };

        this.encryptionKey = null;
        this.initializeEncryption();
    }

    /**
     * Initialize encryption for media files
     */
    initializeEncryption() {
        // Generate or load encryption key
        const keyFile = path.join(require('os').homedir(), '.flexpbx', 'media.key');

        try {
            if (fs.existsSync(keyFile)) {
                this.encryptionKey = fs.readFileSync(keyFile);
            } else {
                // Generate new 256-bit key
                this.encryptionKey = crypto.randomBytes(32);
                fs.ensureDirSync(path.dirname(keyFile));
                fs.writeFileSync(keyFile, this.encryptionKey);
                fs.chmodSync(keyFile, 0o600); // Secure permissions
            }
        } catch (error) {
            console.warn('Could not initialize media encryption:', error.message);
            this.encryptionKey = crypto.randomBytes(32); // Fallback to memory-only key
        }
    }

    /**
     * Check FFmpeg availability and capabilities
     */
    async checkFFmpegAvailability() {
        try {
            const result = await execAsync('ffmpeg -version');
            const version = result.stdout.match(/ffmpeg version (\S+)/)?.[1];

            // Check for specific codec support
            const codecsResult = await execAsync('ffmpeg -codecs');
            const codecs = codecsResult.stdout;

            return {
                available: true,
                version,
                supports: {
                    h264: codecs.includes('libx264'),
                    h265: codecs.includes('libx265'),
                    vp9: codecs.includes('libvpx-vp9'),
                    aac: codecs.includes('aac'),
                    flac: codecs.includes('flac'),
                    opus: codecs.includes('libopus')
                }
            };
        } catch (error) {
            return {
                available: false,
                error: 'FFmpeg not found. Please install FFmpeg for media processing.'
            };
        }
    }

    /**
     * Convert audio with high quality options
     */
    async convertAudio(inputPath, outputPath, options = {}) {
        const {
            format = 'mp3',
            quality = 'high',
            channels = 'stereo', // 'mono', 'stereo'
            sampleRate = null,
            bitrate = null,
            normalize = false,
            encrypt = false
        } = options;

        try {
            await this.checkFFmpegAvailability();

            const qualitySettings = this.supportedFormats.audio.quality[quality];
            const actualSampleRate = sampleRate || qualitySettings.sampleRate;
            const actualBitrate = bitrate || qualitySettings.bitrate;

            let ffmpegCmd = `ffmpeg -i "${inputPath}" -y`;

            // Audio codec selection
            switch (format) {
                case 'mp3':
                    ffmpegCmd += ' -c:a libmp3lame';
                    if (actualBitrate) ffmpegCmd += ` -b:a ${actualBitrate}`;
                    break;
                case 'flac':
                    ffmpegCmd += ' -c:a flac -compression_level 8';
                    break;
                case 'aac':
                    ffmpegCmd += ' -c:a aac';
                    if (actualBitrate) ffmpegCmd += ` -b:a ${actualBitrate}`;
                    break;
                case 'ogg':
                    ffmpegCmd += ' -c:a libvorbis';
                    if (actualBitrate) ffmpegCmd += ` -b:a ${actualBitrate}`;
                    break;
                case 'wav':
                    ffmpegCmd += ' -c:a pcm_s24le'; // 24-bit PCM
                    break;
            }

            // Channel configuration
            if (channels === 'mono') {
                ffmpegCmd += ' -ac 1';
            } else if (channels === 'stereo') {
                ffmpegCmd += ' -ac 2';
            }

            // Sample rate
            if (actualSampleRate) {
                ffmpegCmd += ` -ar ${actualSampleRate}`;
            }

            // Audio normalization
            if (normalize) {
                ffmpegCmd += ' -af "loudnorm=I=-16:LRA=11:TP=-1.5"';
            }

            const tempOutput = encrypt ? `${outputPath}.tmp` : outputPath;
            ffmpegCmd += ` "${tempOutput}"`;

            console.log('Running FFmpeg command:', ffmpegCmd);
            const result = await execAsync(ffmpegCmd);

            if (encrypt) {
                await this.encryptFile(tempOutput, outputPath);
                await fs.remove(tempOutput);
            }

            return {
                success: true,
                outputPath,
                format,
                quality,
                encrypted: encrypt,
                metadata: await this.getAudioMetadata(encrypt ? tempOutput : outputPath)
            };

        } catch (error) {
            return {
                success: false,
                error: error.message
            };
        }
    }

    /**
     * Convert video with quality options
     */
    async convertVideo(inputPath, outputPath, options = {}) {
        const {
            format = 'mp4',
            codec = 'h264',
            quality = 'high',
            resolution = null, // e.g., '1920x1080'
            fps = null,
            crf = 23, // Constant Rate Factor (lower = better quality)
            encrypt = false,
            extractAudio = false
        } = options;

        try {
            await this.checkFFmpegAvailability();

            let ffmpegCmd = `ffmpeg -i "${inputPath}" -y`;

            // Video codec selection
            switch (codec) {
                case 'h264':
                    ffmpegCmd += ' -c:v libx264 -preset medium';
                    ffmpegCmd += ` -crf ${crf}`;
                    break;
                case 'h265':
                    ffmpegCmd += ' -c:v libx265 -preset medium';
                    ffmpegCmd += ` -crf ${crf}`;
                    break;
                case 'vp9':
                    ffmpegCmd += ' -c:v libvpx-vp9 -crf 30 -b:v 0';
                    break;
                case 'av1':
                    ffmpegCmd += ' -c:v libaom-av1 -crf 30';
                    break;
            }

            // Audio codec for video
            if (format === 'mp4') {
                ffmpegCmd += ' -c:a aac -b:a 192k';
            } else if (format === 'webm') {
                ffmpegCmd += ' -c:a libopus -b:a 128k';
            }

            // Resolution
            if (resolution) {
                ffmpegCmd += ` -s ${resolution}`;
            }

            // Frame rate
            if (fps) {
                ffmpegCmd += ` -r ${fps}`;
            }

            // Quality presets
            if (quality === 'high') {
                ffmpegCmd += ' -profile:v high -level 4.1';
            }

            const tempOutput = encrypt ? `${outputPath}.tmp` : outputPath;
            ffmpegCmd += ` "${tempOutput}"`;

            console.log('Running FFmpeg command:', ffmpegCmd);
            const result = await execAsync(ffmpegCmd);

            if (encrypt) {
                await this.encryptFile(tempOutput, outputPath);
                await fs.remove(tempOutput);
            }

            // Extract audio if requested
            let audioPath = null;
            if (extractAudio) {
                audioPath = outputPath.replace(/\.[^.]+$/, '_audio.mp3');
                await this.convertAudio(inputPath, audioPath, { format: 'mp3', quality: 'high', encrypt });
            }

            return {
                success: true,
                outputPath,
                audioPath,
                format,
                codec,
                quality,
                encrypted: encrypt,
                metadata: await this.getVideoMetadata(encrypt ? tempOutput : outputPath)
            };

        } catch (error) {
            return {
                success: false,
                error: error.message
            };
        }
    }

    /**
     * Record audio with high quality
     */
    async recordAudio(outputPath, options = {}) {
        const {
            duration = null, // seconds
            quality = 'high',
            format = 'wav',
            channels = 'stereo',
            device = null, // audio input device
            encrypt = false
        } = options;

        try {
            const qualitySettings = this.supportedFormats.audio.quality[quality];

            let ffmpegCmd = 'ffmpeg';

            // Input device selection (platform specific)
            if (process.platform === 'darwin') {
                ffmpegCmd += ' -f avfoundation';
                ffmpegCmd += device ? ` -i "${device}"` : ' -i ":0"'; // Default audio input
            } else if (process.platform === 'win32') {
                ffmpegCmd += ' -f dshow';
                ffmpegCmd += device ? ` -i audio="${device}"` : ' -i audio="Microphone"';
            } else {
                ffmpegCmd += ' -f pulse';
                ffmpegCmd += device ? ` -i ${device}` : ' -i default';
            }

            // Duration
            if (duration) {
                ffmpegCmd += ` -t ${duration}`;
            }

            // Quality settings
            if (format === 'wav') {
                ffmpegCmd += ' -c:a pcm_s24le'; // 24-bit
            } else if (format === 'flac') {
                ffmpegCmd += ' -c:a flac -compression_level 8';
            } else {
                ffmpegCmd += ` -c:a libmp3lame -b:a ${qualitySettings.bitrate}`;
            }

            ffmpegCmd += ` -ar ${qualitySettings.sampleRate}`;
            ffmpegCmd += channels === 'mono' ? ' -ac 1' : ' -ac 2';

            const tempOutput = encrypt ? `${outputPath}.tmp` : outputPath;
            ffmpegCmd += ` "${tempOutput}"`;

            console.log('Recording audio with command:', ffmpegCmd);

            const childProcess = exec(ffmpegCmd);

            if (encrypt) {
                childProcess.on('close', async (code) => {
                    if (code === 0) {
                        await this.encryptFile(tempOutput, outputPath);
                        await fs.remove(tempOutput);
                    }
                });
            }

            return {
                success: true,
                process: childProcess,
                outputPath,
                encrypted: encrypt
            };

        } catch (error) {
            return {
                success: false,
                error: error.message
            };
        }
    }

    /**
     * Encrypt media file
     */
    async encryptFile(inputPath, outputPath) {
        try {
            const data = await fs.readFile(inputPath);

            // Generate random IV
            const iv = crypto.randomBytes(16);

            // Create cipher
            const cipher = crypto.createCipheriv('aes-256-cbc', this.encryptionKey, iv);
            cipher.setAutoPadding(true);

            // Encrypt data
            const encryptedData = Buffer.concat([
                cipher.update(data),
                cipher.final()
            ]);

            // Combine IV and encrypted data
            const finalData = Buffer.concat([iv, encryptedData]);

            await fs.writeFile(outputPath, finalData);
            await fs.chmod(outputPath, 0o600); // Secure permissions

            return true;
        } catch (error) {
            throw new Error(`Encryption failed: ${error.message}`);
        }
    }

    /**
     * Decrypt media file
     */
    async decryptFile(inputPath, outputPath) {
        try {
            const encryptedData = await fs.readFile(inputPath);

            // Extract IV and data
            const iv = encryptedData.slice(0, 16);
            const data = encryptedData.slice(16);

            // Create decipher
            const decipher = crypto.createDecipher('aes-256-cbc', this.encryptionKey);
            decipher.setAutoPadding(true);

            // Decrypt data
            const decryptedData = Buffer.concat([
                decipher.update(data),
                decipher.final()
            ]);

            await fs.writeFile(outputPath, decryptedData);

            return true;
        } catch (error) {
            throw new Error(`Decryption failed: ${error.message}`);
        }
    }

    /**
     * Get audio metadata
     */
    async getAudioMetadata(filePath) {
        try {
            const result = await execAsync(`ffprobe -v quiet -print_format json -show_format -show_streams "${filePath}"`);
            const metadata = JSON.parse(result.stdout);

            const audioStream = metadata.streams.find(s => s.codec_type === 'audio');

            return {
                duration: parseFloat(metadata.format.duration),
                bitrate: parseInt(metadata.format.bit_rate),
                sampleRate: parseInt(audioStream?.sample_rate),
                channels: parseInt(audioStream?.channels),
                codec: audioStream?.codec_name,
                size: parseInt(metadata.format.size)
            };
        } catch (error) {
            return null;
        }
    }

    /**
     * Get video metadata
     */
    async getVideoMetadata(filePath) {
        try {
            const result = await execAsync(`ffprobe -v quiet -print_format json -show_format -show_streams "${filePath}"`);
            const metadata = JSON.parse(result.stdout);

            const videoStream = metadata.streams.find(s => s.codec_type === 'video');
            const audioStream = metadata.streams.find(s => s.codec_type === 'audio');

            return {
                duration: parseFloat(metadata.format.duration),
                bitrate: parseInt(metadata.format.bit_rate),
                size: parseInt(metadata.format.size),
                video: videoStream ? {
                    codec: videoStream.codec_name,
                    width: parseInt(videoStream.width),
                    height: parseInt(videoStream.height),
                    fps: eval(videoStream.r_frame_rate), // e.g., "30/1" becomes 30
                    bitrate: parseInt(videoStream.bit_rate)
                } : null,
                audio: audioStream ? {
                    codec: audioStream.codec_name,
                    sampleRate: parseInt(audioStream.sample_rate),
                    channels: parseInt(audioStream.channels),
                    bitrate: parseInt(audioStream.bit_rate)
                } : null
            };
        } catch (error) {
            return null;
        }
    }

    /**
     * List available audio devices
     */
    async getAudioDevices() {
        try {
            let devices = [];

            if (process.platform === 'darwin') {
                // macOS - use system_profiler
                const result = await execAsync('system_profiler SPAudioDataType -json');
                const data = JSON.parse(result.stdout);
                // Parse macOS audio devices
                devices = this.parseMacOSAudioDevices(data);
            } else if (process.platform === 'win32') {
                // Windows - use ffmpeg to list devices
                const result = await execAsync('ffmpeg -list_devices true -f dshow -i dummy');
                devices = this.parseWindowsAudioDevices(result.stderr);
            } else {
                // Linux - use pactl
                const result = await execAsync('pactl list sources');
                devices = this.parseLinuxAudioDevices(result.stdout);
            }

            return devices;
        } catch (error) {
            return [];
        }
    }

    /**
     * Parse macOS audio devices
     */
    parseMacOSAudioDevices(data) {
        const devices = [];
        // Implementation for parsing macOS audio device data
        // This would parse the system_profiler output
        return devices;
    }

    /**
     * Parse Windows audio devices
     */
    parseWindowsAudioDevices(output) {
        const devices = [];
        const lines = output.split('\n');

        for (const line of lines) {
            if (line.includes('DirectShow audio devices')) {
                // Parse device names from ffmpeg output
                const match = line.match(/"([^"]+)"/);
                if (match) {
                    devices.push({
                        name: match[1],
                        type: 'input'
                    });
                }
            }
        }

        return devices;
    }

    /**
     * Parse Linux audio devices
     */
    parseLinuxAudioDevices(output) {
        const devices = [];
        const sections = output.split('Source #');

        for (const section of sections) {
            const nameMatch = section.match(/Name: (.+)/);
            const descMatch = section.match(/Description: (.+)/);

            if (nameMatch && descMatch) {
                devices.push({
                    name: nameMatch[1],
                    description: descMatch[1],
                    type: 'input'
                });
            }
        }

        return devices;
    }

    /**
     * Create encrypted backup with media files
     */
    async createEncryptedMediaBackup(mediaFiles, backupPath) {
        try {
            const archive = require('archiver')('zip', { zlib: { level: 9 } });
            const output = fs.createWriteStream(backupPath);

            archive.pipe(output);

            // Add media files to archive
            for (const file of mediaFiles) {
                if (await fs.pathExists(file.path)) {
                    archive.file(file.path, { name: file.name });
                }
            }

            await archive.finalize();

            // Encrypt the backup
            const encryptedPath = `${backupPath}.encrypted`;
            await this.encryptFile(backupPath, encryptedPath);
            await fs.remove(backupPath);

            return {
                success: true,
                encryptedPath,
                fileCount: mediaFiles.length
            };

        } catch (error) {
            return {
                success: false,
                error: error.message
            };
        }
    }
}

module.exports = MediaService;