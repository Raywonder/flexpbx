const { EventEmitter } = require('events');
const fs = require('fs-extra');
const path = require('path');
const { exec } = require('child_process');

/**
 * 🎵 FlexPBX Advanced Call Center Service
 * Hold music preview, live streams, call queues, and wrap-up features
 */
class AdvancedCallCenterService extends EventEmitter {
    constructor() {
        super();

        // Hold Music and Stream Management
        this.holdMusicSources = new Map();
        this.liveStreams = new Map();
        this.audioMixer = null;

        // Call Queue Management
        this.callQueues = new Map();
        this.queueAgents = new Map();
        this.callWrapUps = new Map();

        // Preview Extensions
        this.previewExtensions = {
            '9901': 'Hold Music Preview - Classical',
            '9902': 'Hold Music Preview - Corporate',
            '9903': 'Hold Music Preview - Jazz',
            '9904': 'Hold Music Preview - Ambient',
            '9905': 'Live Stream Preview - Radio',
            '9906': 'Live Stream Preview - Music',
            '9907': 'Queue Manager Interface',
            '9908': 'Call Wrap-up System',
            '9909': 'Audio Mixer Control'
        };

        this.setupHoldMusicSystem();
        this.setupLiveStreamSystem();
        this.setupCallQueueSystem();
        this.setupCallWrapUpSystem();
    }

    setupHoldMusicSystem() {
        console.log('🎵 Setting up Advanced Hold Music System...');

        // Traditional hold music sources
        this.holdMusicSources.set('classical', {
            name: 'Classical Hold Music',
            type: 'local',
            source: 'src/assets/sounds/hold-music/classical_base.aiff',
            volume: 0.7,
            loop: true,
            crossfade: true,
            announcements: {
                enabled: true,
                interval: 30000, // 30 seconds
                volume: 0.9,
                messages: [
                    'Thank you for holding. Your call is important to us.',
                    'We appreciate your patience. A representative will be with you shortly.',
                    'Your call will be answered in the order it was received.'
                ]
            }
        });

        this.holdMusicSources.set('corporate', {
            name: 'Corporate Hold Music',
            type: 'local',
            source: 'src/assets/sounds/hold-music/corporate_base.aiff',
            volume: 0.6,
            loop: true,
            crossfade: true,
            announcements: {
                enabled: true,
                interval: 45000,
                volume: 0.9,
                messages: [
                    'Thank you for calling FlexPBX. Please continue to hold.',
                    'Visit our website at flexpbx.com for additional support options.',
                    'Your business is important to us. Please hold for the next available agent.'
                ]
            }
        });

        this.holdMusicSources.set('jazz', {
            name: 'Jazz Hold Music',
            type: 'local',
            source: 'src/assets/sounds/hold-music/jazz_base.aiff',
            volume: 0.8,
            loop: true,
            crossfade: true,
            announcements: {
                enabled: false // Music-only experience
            }
        });

        console.log(`✅ Configured ${this.holdMusicSources.size} hold music sources`);
    }

    setupLiveStreamSystem() {
        console.log('📻 Setting up Live Stream System...');

        // Live radio/music streams
        this.liveStreams.set('chrismixradio', {
            name: 'Chris Mix Radio',
            url: 'https://chrismixradio.com',
            type: 'audio_stream',
            volume: 0.5, // Lower volume for hold music
            bitRate: 128,
            format: 'mp3',
            buffering: 5000, // 5 second buffer
            fallback: 'corporate', // Fallback to local music if stream fails
            quality: 'high',
            description: 'Professional mix radio for hold music'
        });

        this.liveStreams.set('jazzradio', {
            name: 'Jazz Radio Stream',
            url: 'https://streaming.radio.co/jazz24',
            type: 'audio_stream',
            volume: 0.6,
            bitRate: 96,
            format: 'aac',
            buffering: 3000,
            fallback: 'jazz',
            quality: 'medium',
            description: 'Smooth jazz for professional environments'
        });

        this.liveStreams.set('classicalradio', {
            name: 'Classical Radio Stream',
            url: 'https://streaming.classical.org/classical',
            type: 'audio_stream',
            volume: 0.4,
            bitRate: 192,
            format: 'mp3',
            buffering: 8000,
            fallback: 'classical',
            quality: 'high',
            description: 'Classical music for elegant hold experience'
        });

        // Stream health monitoring
        this.streamHealthCheck = {
            interval: 30000, // Check every 30 seconds
            timeout: 10000, // 10 second timeout
            retryAttempts: 3,
            fallbackDelay: 2000
        };

        console.log(`✅ Configured ${this.liveStreams.size} live stream sources`);
    }

    setupCallQueueSystem() {
        console.log('📋 Setting up Advanced Call Queue System...');

        // Sales Queue
        this.callQueues.set('sales_queue', {
            name: 'Sales Queue',
            extension: '1100',
            strategy: 'round_robin', // round_robin, longest_idle, random, priority
            agents: [
                { extension: '1000', name: 'Sales Manager', skills: ['management', 'enterprise'], priority: 10 },
                { extension: '1001', name: 'Senior Sales Rep', skills: ['general', 'upselling'], priority: 8 },
                { extension: '1002', name: 'Sales Rep 2', skills: ['general', 'new_customers'], priority: 7 },
                { extension: '1003', name: 'Sales Rep 3', skills: ['general', 'follow_up'], priority: 7 }
            ],
            maxWaitTime: 300, // 5 minutes
            maxQueueSize: 20,
            announcements: {
                welcome: 'Thank you for calling our sales team. Please hold while we connect you.',
                position: 'You are caller number {position} in the queue.',
                waitTime: 'Your estimated wait time is {minutes} minutes.',
                periodic: 'Thank you for continuing to hold. Your call is important to us.'
            },
            holdMusic: 'corporate',
            callbackOption: true,
            priority: {
                'vip': 10,
                'existing_customer': 8,
                'new_customer': 5,
                'general': 3
            }
        });

        // Support Queue
        this.callQueues.set('support_queue', {
            name: 'Technical Support Queue',
            extension: '2100',
            strategy: 'skills_based', // Route based on agent skills
            agents: [
                { extension: '2000', name: 'Support Manager', skills: ['management', 'escalation'], priority: 10 },
                { extension: '2001', name: 'Senior Tech', skills: ['advanced', 'networking'], priority: 9 },
                { extension: '2002', name: 'Tech L2', skills: ['general', 'software'], priority: 7 },
                { extension: '2004', name: 'Accessibility Support', skills: ['accessibility', 'voiceover'], priority: 8 }
            ],
            maxWaitTime: 600, // 10 minutes
            maxQueueSize: 15,
            announcements: {
                welcome: 'Thank you for calling technical support. We will connect you with the best available technician.',
                position: 'You are caller number {position} in the support queue.',
                waitTime: 'Current wait time is approximately {minutes} minutes.',
                periodic: 'All our technicians are busy helping other customers. Please continue to hold.'
            },
            holdMusic: 'ambient',
            callbackOption: true,
            priority: {
                'emergency': 10,
                'accessibility': 9,
                'premium_support': 8,
                'business': 6,
                'general': 4
            }
        });

        console.log(`✅ Configured ${this.callQueues.size} call queues with advanced features`);
    }

    setupCallWrapUpSystem() {
        console.log('📝 Setting up Call Wrap-up System...');

        this.wrapUpCategories = {
            sales: {
                name: 'Sales Call Wrap-up',
                fields: [
                    { name: 'call_outcome', type: 'select', options: ['Sale Made', 'Follow-up Required', 'Not Interested', 'Callback Requested'], required: true },
                    { name: 'sale_amount', type: 'number', label: 'Sale Amount ($)', required: false },
                    { name: 'follow_up_date', type: 'date', label: 'Follow-up Date', required: false },
                    { name: 'customer_notes', type: 'textarea', label: 'Customer Notes', required: true },
                    { name: 'next_action', type: 'select', options: ['Email Quote', 'Schedule Demo', 'Send Brochure', 'Manager Follow-up'], required: false }
                ],
                autoSave: true,
                timeout: 300 // 5 minutes to complete wrap-up
            },
            support: {
                name: 'Support Call Wrap-up',
                fields: [
                    { name: 'issue_resolved', type: 'select', options: ['Resolved', 'Escalated', 'Pending', 'Reopened'], required: true },
                    { name: 'issue_category', type: 'select', options: ['Software', 'Hardware', 'Network', 'Accessibility', 'Billing'], required: true },
                    { name: 'resolution_time', type: 'number', label: 'Resolution Time (minutes)', required: true },
                    { name: 'customer_satisfaction', type: 'select', options: ['Very Satisfied', 'Satisfied', 'Neutral', 'Unsatisfied'], required: false },
                    { name: 'technical_notes', type: 'textarea', label: 'Technical Notes', required: true },
                    { name: 'follow_up_required', type: 'checkbox', label: 'Follow-up Required', required: false }
                ],
                autoSave: true,
                timeout: 600 // 10 minutes for technical wrap-up
            }
        };

        console.log('✅ Call wrap-up system configured with automated workflows');
    }

    // Hold Music Preview Functions
    async playHoldMusicPreview(source, duration = 30000) {
        console.log(`🎵 Playing hold music preview: ${source}`);

        const musicSource = this.holdMusicSources.get(source);
        if (!musicSource) {
            throw new Error(`Hold music source '${source}' not found`);
        }

        try {
            // Play preview for specified duration
            const playCommand = `afplay "${musicSource.source}" &`;
            await this.execAsync(playCommand);

            // Stop after duration
            setTimeout(async () => {
                await this.execAsync('pkill afplay');
                console.log(`⏹️ Hold music preview stopped: ${source}`);
            }, duration);

            return {
                source: source,
                name: musicSource.name,
                duration: duration,
                status: 'playing'
            };

        } catch (error) {
            console.error(`❌ Failed to play hold music preview: ${error.message}`);
            return { source, status: 'failed', error: error.message };
        }
    }

    async playLiveStreamPreview(streamKey, duration = 60000) {
        console.log(`📻 Playing live stream preview: ${streamKey}`);

        const stream = this.liveStreams.get(streamKey);
        if (!stream) {
            throw new Error(`Live stream '${streamKey}' not found`);
        }

        try {
            // Test stream connectivity first
            const streamStatus = await this.testStreamConnectivity(stream.url);
            if (!streamStatus.accessible) {
                // Fallback to local hold music
                console.log(`⚠️ Stream not accessible, using fallback: ${stream.fallback}`);
                return this.playHoldMusicPreview(stream.fallback, duration);
            }

            // Stream is accessible, play preview
            // Note: In production, use proper audio streaming library
            console.log(`✅ Live stream preview started: ${stream.name}`);
            console.log(`   URL: ${stream.url}`);
            console.log(`   Quality: ${stream.quality} (${stream.bitRate}kbps)`);
            console.log(`   Volume: ${Math.round(stream.volume * 100)}%`);

            // Simulate stream playback
            setTimeout(() => {
                console.log(`⏹️ Live stream preview stopped: ${streamKey}`);
            }, duration);

            return {
                stream: streamKey,
                name: stream.name,
                url: stream.url,
                duration: duration,
                quality: stream.quality,
                status: 'streaming'
            };

        } catch (error) {
            console.error(`❌ Failed to play live stream: ${error.message}`);
            // Fallback to local music
            return this.playHoldMusicPreview(stream.fallback, duration);
        }
    }

    async testStreamConnectivity(url) {
        console.log(`🔍 Testing stream connectivity: ${url}`);

        try {
            // Simple connectivity test (in production, use proper stream validation)
            const testCommand = `curl -s --head "${url}" | head -1`;
            const result = await this.execAsync(testCommand);

            const accessible = result.stdout.includes('200') || result.stdout.includes('OK');

            return {
                url: url,
                accessible: accessible,
                responseTime: Math.floor(Math.random() * 100) + 50, // Simulated
                status: accessible ? 'online' : 'offline'
            };

        } catch (error) {
            return {
                url: url,
                accessible: false,
                error: error.message,
                status: 'error'
            };
        }
    }

    // Call Queue Management
    async addCallToQueue(queueName, callId, callerInfo = {}) {
        console.log(`📋 Adding call ${callId} to queue: ${queueName}`);

        const queue = this.callQueues.get(queueName);
        if (!queue) {
            throw new Error(`Queue '${queueName}' not found`);
        }

        const queueEntry = {
            callId: callId,
            callerInfo: callerInfo,
            joinTime: new Date(),
            position: this.getQueuePosition(queueName),
            priority: this.calculateCallPriority(callerInfo, queue.priority),
            estimatedWait: this.calculateEstimatedWait(queueName),
            announcementsSent: 0
        };

        // Add to queue (implement actual queue storage)
        console.log(`✅ Call added to ${queue.name}:`);
        console.log(`   Position: ${queueEntry.position}`);
        console.log(`   Priority: ${queueEntry.priority}`);
        console.log(`   Estimated Wait: ${Math.ceil(queueEntry.estimatedWait / 60)} minutes`);

        // Start hold music/stream
        await this.startHoldExperience(queueName, callId);

        // Schedule announcements
        this.scheduleQueueAnnouncements(queueName, callId, queueEntry);

        this.emit('call_queued', { queueName, callId, queueEntry });
        return queueEntry;
    }

    async startHoldExperience(queueName, callId) {
        const queue = this.callQueues.get(queueName);
        const holdSource = queue.holdMusic;

        console.log(`🎵 Starting hold experience for call ${callId}: ${holdSource}`);

        // Check if it's a live stream or local music
        if (this.liveStreams.has(holdSource)) {
            const stream = this.liveStreams.get(holdSource);
            console.log(`📻 Streaming ${stream.name} for hold music`);

            // Test stream first
            const streamTest = await this.testStreamConnectivity(stream.url);
            if (!streamTest.accessible) {
                console.log(`⚠️ Stream unavailable, using fallback: ${stream.fallback}`);
                return this.startLocalHoldMusic(stream.fallback, callId);
            } else {
                console.log(`✅ Live stream active: ${stream.name}`);
                return this.startStreamHoldMusic(holdSource, callId);
            }
        } else {
            return this.startLocalHoldMusic(holdSource, callId);
        }
    }

    async startLocalHoldMusic(source, callId) {
        const musicSource = this.holdMusicSources.get(source);
        if (musicSource) {
            console.log(`🎵 Playing local hold music: ${musicSource.name}`);
            // Implement actual audio playback
            return { type: 'local', source: source, callId: callId };
        }
    }

    async startStreamHoldMusic(streamKey, callId) {
        const stream = this.liveStreams.get(streamKey);
        console.log(`📻 Starting stream hold music: ${stream.name}`);
        console.log(`   Volume: ${Math.round(stream.volume * 100)}%`);
        // Implement actual stream playback
        return { type: 'stream', source: streamKey, callId: callId };
    }

    scheduleQueueAnnouncements(queueName, callId, queueEntry) {
        const queue = this.callQueues.get(queueName);

        // Initial welcome announcement
        setTimeout(() => {
            this.playQueueAnnouncement(callId, queue.announcements.welcome);
        }, 2000);

        // Position announcement
        setTimeout(() => {
            const positionMsg = queue.announcements.position.replace('{position}', queueEntry.position);
            this.playQueueAnnouncement(callId, positionMsg);
        }, 5000);

        // Estimated wait time
        setTimeout(() => {
            const waitTime = Math.ceil(queueEntry.estimatedWait / 60);
            const waitMsg = queue.announcements.waitTime.replace('{minutes}', waitTime);
            this.playQueueAnnouncement(callId, waitMsg);
        }, 8000);

        // Periodic announcements every 30 seconds
        const periodicInterval = setInterval(() => {
            this.playQueueAnnouncement(callId, queue.announcements.periodic);
        }, 30000);

        // Store interval for cleanup
        this.queueAgents.set(callId, { interval: periodicInterval });
    }

    async playQueueAnnouncement(callId, message) {
        console.log(`📢 Queue announcement for call ${callId}: ${message}`);

        // Generate audio announcement
        const tempFile = `/tmp/announcement_${callId}_${Date.now()}.aiff`;
        try {
            await this.execAsync(`say "${message}" -o "${tempFile}"`);
            // In production, play this over the call
            console.log(`🔊 Announcement generated: ${tempFile}`);
        } catch (error) {
            console.error(`❌ Failed to generate announcement: ${error.message}`);
        }
    }

    calculateCallPriority(callerInfo, priorityRules) {
        let priority = 1;

        // Check priority rules
        for (const [rule, value] of Object.entries(priorityRules)) {
            if (callerInfo.type === rule || callerInfo.category === rule) {
                priority = Math.max(priority, value);
            }
        }

        return priority;
    }

    calculateEstimatedWait(queueName) {
        // Simplified calculation - in production, use historical data
        const baseWait = 120; // 2 minutes base
        const queuePosition = this.getQueuePosition(queueName);
        return baseWait * queuePosition;
    }

    getQueuePosition(queueName) {
        // Simulate current queue position
        return Math.floor(Math.random() * 5) + 1;
    }

    // Call Wrap-up System
    async startCallWrapUp(agentExtension, callId, department = 'support') {
        console.log(`📝 Starting call wrap-up for agent ${agentExtension}, call ${callId}`);

        const wrapUpConfig = this.wrapUpCategories[department];
        if (!wrapUpConfig) {
            throw new Error(`Wrap-up category '${department}' not found`);
        }

        const wrapUpSession = {
            sessionId: `wrapup_${callId}_${Date.now()}`,
            agentExtension: agentExtension,
            callId: callId,
            department: department,
            startTime: new Date(),
            fields: wrapUpConfig.fields,
            data: {},
            status: 'in_progress',
            timeout: wrapUpConfig.timeout
        };

        this.callWrapUps.set(wrapUpSession.sessionId, wrapUpSession);

        // Set timeout for auto-completion
        setTimeout(() => {
            if (this.callWrapUps.has(wrapUpSession.sessionId)) {
                console.log(`⏰ Call wrap-up timed out: ${wrapUpSession.sessionId}`);
                this.completeWrapUp(wrapUpSession.sessionId, { status: 'timeout' });
            }
        }, wrapUpConfig.timeout * 1000);

        console.log(`✅ Call wrap-up session started: ${wrapUpSession.sessionId}`);
        this.emit('wrapup_started', wrapUpSession);

        return wrapUpSession;
    }

    async updateWrapUpField(sessionId, fieldName, value) {
        const session = this.callWrapUps.get(sessionId);
        if (!session) {
            throw new Error(`Wrap-up session '${sessionId}' not found`);
        }

        session.data[fieldName] = value;
        session.lastUpdated = new Date();

        console.log(`📝 Updated wrap-up field: ${fieldName} = ${value}`);

        // Auto-save if enabled
        const config = this.wrapUpCategories[session.department];
        if (config.autoSave) {
            await this.saveWrapUpData(sessionId);
        }

        this.emit('wrapup_updated', { sessionId, fieldName, value });
    }

    async completeWrapUp(sessionId, finalData = {}) {
        const session = this.callWrapUps.get(sessionId);
        if (!session) {
            throw new Error(`Wrap-up session '${sessionId}' not found`);
        }

        // Merge final data
        session.data = { ...session.data, ...finalData };
        session.status = 'completed';
        session.completedTime = new Date();

        // Save final wrap-up
        await this.saveWrapUpData(sessionId);

        console.log(`✅ Call wrap-up completed: ${sessionId}`);
        this.emit('wrapup_completed', session);

        // Clean up session
        this.callWrapUps.delete(sessionId);

        return session;
    }

    async saveWrapUpData(sessionId) {
        const session = this.callWrapUps.get(sessionId);
        if (!session) return;

        const wrapUpDir = path.join(process.cwd(), 'pbx-data', 'call-wrapups');
        await fs.ensureDir(wrapUpDir);

        const fileName = `wrapup_${session.callId}_${session.agentExtension}_${Date.now()}.json`;
        const filePath = path.join(wrapUpDir, fileName);

        await fs.writeJson(filePath, session, { spaces: 2 });
        console.log(`💾 Wrap-up data saved: ${fileName}`);
    }

    // Extension Preview System
    async handlePreviewExtensionCall(extension) {
        console.log(`📞 Preview extension called: ${extension}`);

        const previewName = this.previewExtensions[extension];
        if (!previewName) {
            throw new Error(`Preview extension '${extension}' not found`);
        }

        switch (extension) {
            case '9901':
                return this.playHoldMusicPreview('classical');
            case '9902':
                return this.playHoldMusicPreview('corporate');
            case '9903':
                return this.playHoldMusicPreview('jazz');
            case '9904':
                return this.playHoldMusicPreview('ambient');
            case '9905':
                return this.playLiveStreamPreview('chrismixradio');
            case '9906':
                return this.playLiveStreamPreview('jazzradio');
            case '9907':
                return this.showQueueManagerInterface();
            case '9908':
                return this.showCallWrapUpInterface();
            case '9909':
                return this.showAudioMixerInterface();
            default:
                throw new Error(`Preview function not implemented for ${extension}`);
        }
    }

    async showQueueManagerInterface() {
        console.log('📋 Opening Queue Manager Interface...');

        const queueStats = {};
        for (const [queueName, queue] of this.callQueues) {
            queueStats[queueName] = {
                name: queue.name,
                waitingCalls: Math.floor(Math.random() * 8) + 1,
                averageWait: Math.floor(Math.random() * 300) + 60,
                activeAgents: queue.agents.length,
                longestWait: Math.floor(Math.random() * 600) + 120
            };
        }

        console.log('📊 Queue Statistics:');
        for (const [name, stats] of Object.entries(queueStats)) {
            console.log(`   ${stats.name}:`);
            console.log(`     Waiting Calls: ${stats.waitingCalls}`);
            console.log(`     Average Wait: ${Math.ceil(stats.averageWait / 60)} min`);
            console.log(`     Active Agents: ${stats.activeAgents}`);
        }

        return { interface: 'queue_manager', stats: queueStats };
    }

    async showCallWrapUpInterface() {
        console.log('📝 Opening Call Wrap-up Interface...');

        const activeWrapUps = Array.from(this.callWrapUps.values());
        console.log(`📋 Active Wrap-up Sessions: ${activeWrapUps.length}`);

        return { interface: 'call_wrapup', activeSessions: activeWrapUps.length };
    }

    async showAudioMixerInterface() {
        console.log('🎛️ Opening Audio Mixer Interface...');

        const mixerStatus = {
            holdMusicSources: Array.from(this.holdMusicSources.keys()),
            liveStreams: Array.from(this.liveStreams.keys()),
            activeSources: Math.floor(Math.random() * 3) + 1,
            masterVolume: 75,
            streamHealth: 'all_online'
        };

        console.log('🎵 Audio Mixer Status:');
        console.log(`   Hold Music Sources: ${mixerStatus.holdMusicSources.length}`);
        console.log(`   Live Streams: ${mixerStatus.liveStreams.length}`);
        console.log(`   Master Volume: ${mixerStatus.masterVolume}%`);

        return { interface: 'audio_mixer', status: mixerStatus };
    }

    // Service Management
    async start() {
        console.log('🚀 Starting Advanced Call Center Service...');

        try {
            await this.testAllStreams();
            await this.validateHoldMusicSources();

            console.log('✅ Advanced Call Center Service started');
            console.log(`🎵 Hold Music Sources: ${this.holdMusicSources.size}`);
            console.log(`📻 Live Streams: ${this.liveStreams.size}`);
            console.log(`📋 Call Queues: ${this.callQueues.size}`);
            console.log('📞 Preview Extensions: 9901-9909');

            this.emit('service_started', {
                holdMusicSources: this.holdMusicSources.size,
                liveStreams: this.liveStreams.size,
                callQueues: this.callQueues.size,
                previewExtensions: Object.keys(this.previewExtensions)
            });

        } catch (error) {
            console.error('❌ Failed to start Advanced Call Center Service:', error);
            throw error;
        }
    }

    async testAllStreams() {
        console.log('🔍 Testing all live streams...');

        for (const [streamKey, stream] of this.liveStreams) {
            const result = await this.testStreamConnectivity(stream.url);
            console.log(`📻 ${stream.name}: ${result.status}`);
        }
    }

    async validateHoldMusicSources() {
        console.log('🎵 Validating hold music sources...');

        for (const [sourceKey, source] of this.holdMusicSources) {
            const exists = await fs.pathExists(source.source);
            console.log(`🎵 ${source.name}: ${exists ? 'available' : 'missing'}`);
        }
    }

    async execAsync(command) {
        return new Promise((resolve, reject) => {
            exec(command, (error, stdout, stderr) => {
                if (error) {
                    reject(error);
                } else {
                    resolve({ stdout, stderr });
                }
            });
        });
    }
}

module.exports = AdvancedCallCenterService;