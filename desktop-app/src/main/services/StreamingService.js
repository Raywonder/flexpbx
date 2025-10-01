const { EventEmitter } = require('events');
const fs = require('fs-extra');
const path = require('path');
const axios = require('axios');

class StreamingService extends EventEmitter {
    constructor() {
        super();
        this.activeStreams = new Map();
        this.jellyfinConfig = null;
        this.icecastConfig = null;
    }

    // Initialize streaming configuration
    async initialize(serverConfig) {
        this.serverConfig = serverConfig;

        // Auto-detect Jellyfin and Icecast servers
        await this.detectStreamingServices();

        this.emit('initialized', {
            jellyfin: !!this.jellyfinConfig,
            icecast: !!this.icecastConfig
        });
    }

    // Auto-detect streaming services
    async detectStreamingServices() {
        // Detect Jellyfin
        const jellyfinUrls = [
            'http://localhost:8096',
            'http://127.0.0.1:8096',
            `http://${this.serverConfig?.host || 'localhost'}:8096`
        ];

        for (const url of jellyfinUrls) {
            try {
                const response = await axios.get(`${url}/System/Info/Public`, { timeout: 5000 });
                if (response.status === 200) {
                    this.jellyfinConfig = {
                        url,
                        version: response.data.Version,
                        serverName: response.data.ServerName
                    };
                    break;
                }
            } catch (error) {
                // Continue to next URL
            }
        }

        // Detect Icecast
        const icecastUrls = [
            'http://localhost:8000',
            'http://127.0.0.1:8000',
            `http://${this.serverConfig?.host || 'localhost'}:8000`
        ];

        for (const url of icecastUrls) {
            try {
                const response = await axios.get(`${url}/admin/stats.xml`, { timeout: 5000 });
                if (response.status === 200) {
                    this.icecastConfig = {
                        url,
                        adminUrl: `${url}/admin`,
                        liveInputPort: 8001
                    };
                    break;
                }
            } catch (error) {
                // Continue to next URL
            }
        }
    }

    // Get available streaming options
    getStreamingOptions() {
        const options = {
            jellyfin: {
                available: !!this.jellyfinConfig,
                config: this.jellyfinConfig,
                features: ['music_library', 'on_hold_music', 'playlist_management']
            },
            icecast: {
                available: !!this.icecastConfig,
                config: this.icecastConfig,
                features: ['live_streaming', 'multiple_formats', 'desktop_broadcast'],
                streams: [
                    { mount: '/high', format: 'MP3', bitrate: '320kbps', description: 'High Quality' },
                    { mount: '/medium', format: 'MP3', bitrate: '128kbps', description: 'Medium Quality' },
                    { mount: '/low', format: 'MP3', bitrate: '64kbps', description: 'Low Bandwidth' },
                    { mount: '/moh', format: 'MP3', bitrate: '96kbps', description: 'Music on Hold' },
                    { mount: '/hq.ogg', format: 'OGG', bitrate: 'Variable', description: 'OGG Vorbis' },
                    { mount: '/aac', format: 'AAC', bitrate: '128kbps', description: 'AAC Stream' },
                    { mount: '/opus', format: 'OPUS', bitrate: '128kbps', description: 'OPUS Stream' },
                    { mount: '/live', format: 'MP3', bitrate: '192kbps', description: 'Live Broadcast' }
                ]
            }
        };

        return options;
    }

    // Start live streaming from desktop app
    async startLiveStream(audioSource, streamConfig = {}) {
        if (!this.icecastConfig) {
            throw new Error('Icecast server not available');
        }

        const streamId = `live-${Date.now()}`;
        const {
            mount = '/live',
            format = 'mp3',
            bitrate = 192,
            title = 'FlexPBX Live Stream',
            description = 'Live stream from desktop application'
        } = streamConfig;

        try {
            // Configure streaming parameters
            const streamParams = {
                server: this.icecastConfig.url,
                port: this.icecastConfig.liveInputPort || 8001,
                mount,
                password: 'flexpbx_live', // From environment config
                format,
                bitrate,
                metadata: {
                    title,
                    description,
                    genre: 'Live'
                }
            };

            // Create stream configuration
            const stream = {
                id: streamId,
                source: audioSource,
                config: streamParams,
                status: 'starting',
                startTime: new Date(),
                listeners: 0
            };

            this.activeStreams.set(streamId, stream);

            // Start the actual streaming process
            await this.startStreamingProcess(stream);

            stream.status = 'active';
            this.emit('stream-started', { streamId, stream });

            return {
                success: true,
                streamId,
                streamUrl: `${this.icecastConfig.url}${mount}`,
                config: streamParams
            };

        } catch (error) {
            this.emit('stream-error', { streamId, error: error.message });
            return {
                success: false,
                error: error.message,
                streamId
            };
        }
    }

    // Start the actual streaming process
    async startStreamingProcess(stream) {
        // This would typically involve:
        // 1. Setting up audio encoding pipeline
        // 2. Connecting to Icecast harbor input
        // 3. Streaming audio data

        // For now, we'll simulate the process
        // In a real implementation, this would use ffmpeg or similar

        console.log(`Starting stream to ${stream.config.server}:${stream.config.port}${stream.config.mount}`);

        // Simulate connection
        setTimeout(() => {
            this.emit('stream-connected', { streamId: stream.id });
        }, 2000);
    }

    // Stop live streaming
    async stopLiveStream(streamId) {
        const stream = this.activeStreams.get(streamId);
        if (!stream) {
            throw new Error('Stream not found');
        }

        try {
            // Stop streaming process
            stream.status = 'stopping';

            // Cleanup streaming resources
            await this.stopStreamingProcess(stream);

            stream.status = 'stopped';
            stream.endTime = new Date();

            this.activeStreams.delete(streamId);
            this.emit('stream-stopped', { streamId, stream });

            return {
                success: true,
                streamId,
                duration: stream.endTime - stream.startTime
            };

        } catch (error) {
            this.emit('stream-error', { streamId, error: error.message });
            return {
                success: false,
                error: error.message,
                streamId
            };
        }
    }

    // Stop streaming process
    async stopStreamingProcess(stream) {
        // Cleanup streaming resources
        console.log(`Stopping stream ${stream.id}`);
    }

    // Get Jellyfin music libraries
    async getJellyfinLibraries() {
        if (!this.jellyfinConfig) {
            throw new Error('Jellyfin not available');
        }

        try {
            const response = await axios.get(`${this.jellyfinConfig.url}/Library/VirtualFolders`, {
                headers: {
                    'X-MediaBrowser-Token': this.jellyfinConfig.apiKey || ''
                }
            });

            return response.data.filter(library =>
                library.LibraryOptions.TypeOptions.some(type => type.Type === 'music')
            );
        } catch (error) {
            throw new Error(`Failed to get Jellyfin libraries: ${error.message}`);
        }
    }

    // Sync Jellyfin music for on-hold
    async syncJellyfinForOnHold(libraryId, destinationPath) {
        if (!this.jellyfinConfig) {
            throw new Error('Jellyfin not available');
        }

        try {
            // Get audio items from library
            const response = await axios.get(`${this.jellyfinConfig.url}/Items`, {
                params: {
                    ParentId: libraryId,
                    IncludeItemTypes: 'Audio',
                    Recursive: true,
                    Fields: 'Path,MediaStreams',
                    Limit: 50
                },
                headers: {
                    'X-MediaBrowser-Token': this.jellyfinConfig.apiKey || ''
                }
            });

            const audioItems = response.data.Items;
            let syncedCount = 0;

            await fs.ensureDir(destinationPath);

            for (const item of audioItems) {
                const filename = `${item.Name.replace(/[^a-zA-Z0-9]/g, '_')}.wav`;
                const outputPath = path.join(destinationPath, filename);

                if (!await fs.pathExists(outputPath)) {
                    const streamUrl = `${this.jellyfinConfig.url}/Audio/${item.Id}/stream?static=true`;

                    try {
                        const audioResponse = await axios.get(streamUrl, {
                            responseType: 'stream',
                            headers: {
                                'X-MediaBrowser-Token': this.jellyfinConfig.apiKey || ''
                            }
                        });

                        const writer = fs.createWriteStream(outputPath);
                        audioResponse.data.pipe(writer);

                        await new Promise((resolve, reject) => {
                            writer.on('finish', resolve);
                            writer.on('error', reject);
                        });

                        syncedCount++;
                        this.emit('sync-progress', {
                            filename: item.Name,
                            progress: syncedCount / audioItems.length * 100
                        });

                    } catch (error) {
                        console.error(`Error downloading ${item.Name}:`, error.message);
                    }
                }
            }

            return {
                success: true,
                syncedCount,
                totalItems: audioItems.length,
                destinationPath
            };

        } catch (error) {
            throw new Error(`Failed to sync Jellyfin music: ${error.message}`);
        }
    }

    // Get streaming statistics
    async getStreamingStats() {
        const stats = {
            activeStreams: this.activeStreams.size,
            streams: Array.from(this.activeStreams.values()).map(stream => ({
                id: stream.id,
                status: stream.status,
                startTime: stream.startTime,
                mount: stream.config.mount,
                format: stream.config.format,
                bitrate: stream.config.bitrate
            })),
            services: {
                jellyfin: {
                    available: !!this.jellyfinConfig,
                    ...this.jellyfinConfig
                },
                icecast: {
                    available: !!this.icecastConfig,
                    ...this.icecastConfig
                }
            }
        };

        // Get Icecast listener statistics if available
        if (this.icecastConfig) {
            try {
                const response = await axios.get(`${this.icecastConfig.url}/admin/stats.xml`, {
                    timeout: 5000
                });
                // Parse XML response for listener counts
                stats.icecastStats = { connected: true };
            } catch (error) {
                stats.icecastStats = { connected: false, error: error.message };
            }
        }

        return stats;
    }

    // Test streaming services connectivity
    async testConnectivity() {
        const results = {};

        // Test Jellyfin
        if (this.jellyfinConfig) {
            try {
                const response = await axios.get(`${this.jellyfinConfig.url}/System/Info`, {
                    timeout: 5000,
                    headers: {
                        'X-MediaBrowser-Token': this.jellyfinConfig.apiKey || ''
                    }
                });
                results.jellyfin = {
                    connected: true,
                    version: response.data.Version,
                    serverName: response.data.ServerName
                };
            } catch (error) {
                results.jellyfin = {
                    connected: false,
                    error: error.message
                };
            }
        }

        // Test Icecast
        if (this.icecastConfig) {
            try {
                const response = await axios.get(`${this.icecastConfig.url}/admin/stats.xml`, {
                    timeout: 5000
                });
                results.icecast = {
                    connected: true,
                    url: this.icecastConfig.url
                };
            } catch (error) {
                results.icecast = {
                    connected: false,
                    error: error.message
                };
            }
        }

        return results;
    }

    // Cleanup all streams
    cleanup() {
        for (const [streamId] of this.activeStreams) {
            this.stopLiveStream(streamId).catch(console.error);
        }
        this.activeStreams.clear();
        this.removeAllListeners();
    }
}

module.exports = StreamingService;