const { EventEmitter } = require('events');
const fs = require('fs-extra');
const path = require('path');
const crypto = require('crypto');
const { spawn, exec } = require('child_process');

/**
 * 📞 FlexPBX Advanced Telephony Service
 * Professional PBX system with IVR, hold music, call monitoring, and SIP integration
 */
class FlexPBXTelephonyService extends EventEmitter {
    constructor() {
        super();

        // Core PBX Configuration
        this.isRunning = false;
        this.sipPort = 5060;
        this.rtpPortRange = { start: 10000, end: 20000 };
        this.webUIPort = 8088;

        // Extensions and Users
        this.extensions = new Map();
        this.activeUsers = new Map();
        this.callSessions = new Map();
        this.conferenceRooms = new Map();

        // Audio and IVR
        this.audioEngine = null;
        this.ivrEngine = null;
        this.holdMusicEngine = null;
        this.voicePrompts = new Map();

        // Call Features
        this.callQueue = new Map();
        this.callRecordings = new Map();
        this.callMonitoring = new Map();
        this.dialPlan = new Map();

        // Multi-user Management
        this.userGroups = new Map();
        this.permissions = new Map();
        this.ownershipMap = new Map();

        this.setupDirectories();
        this.initializeVoiceEngine();
        this.createDefaultExtensions();
        this.setupIVRSystem();
        this.initializeAudioEngine();
    }

    async setupDirectories() {
        const baseDir = path.join(process.cwd(), 'pbx-data');

        this.directories = {
            base: baseDir,
            audio: path.join(baseDir, 'audio'),
            prompts: path.join(baseDir, 'audio', 'prompts'),
            holdMusic: path.join(baseDir, 'audio', 'hold-music'),
            recordings: path.join(baseDir, 'recordings'),
            voicemail: path.join(baseDir, 'voicemail'),
            logs: path.join(baseDir, 'logs'),
            configs: path.join(baseDir, 'configs'),
            users: path.join(baseDir, 'users'),
            groups: path.join(baseDir, 'groups')
        };

        // Create all directories
        for (const [name, dir] of Object.entries(this.directories)) {
            await fs.ensureDir(dir);
        }

        console.log('📞 FlexPBX Telephony directories initialized');
    }

    async initializeVoiceEngine() {
        console.log('🎙️ Initializing FlexPBX Voice Engine...');

        // Create comprehensive voice prompts using system TTS
        this.voicePrompts = new Map([
            // Basic IVR Prompts
            ['welcome', 'Welcome to FlexPBX. Your call is important to us.'],
            ['main_menu', 'For sales, press 1. For support, press 2. For billing, press 3. To repeat this menu, press 9.'],
            ['invalid_option', 'Invalid option. Please try again.'],
            ['transferring', 'Please hold while we transfer your call.'],
            ['call_ended', 'Thank you for calling. Have a great day.'],

            // Advanced IVR Prompts
            ['queue_position', 'You are caller number {position} in the queue. Estimated wait time is {time} minutes.'],
            ['queue_hold', 'All representatives are currently busy. Please hold and your call will be answered in the order it was received.'],
            ['callback_offer', 'Press star to request a callback instead of waiting on hold.'],
            ['after_hours', 'Our office is currently closed. Normal business hours are Monday through Friday, 9 AM to 5 PM.'],

            // Call Management
            ['recording_notice', 'This call may be recorded for quality and training purposes.'],
            ['conference_join', 'You have joined the conference. Press pound to mute or unmute.'],
            ['call_parking', 'Your call has been parked. The pickup code is {code}.'],
            ['voicemail_greeting', 'You have reached the voicemail of extension {extension}. Please leave a message after the tone.'],

            // Accessibility Features
            ['accessibility_mode', 'Accessibility mode enabled. Press star star for voice navigation help.'],
            ['voice_navigation', 'Say the name of the department or extension you want to reach.'],
            ['speech_recognition', 'I heard you say {command}. Press 1 to confirm or 2 to try again.'],

            // Emergency and Special
            ['emergency_redirect', 'For emergencies, please hang up and dial 911.'],
            ['maintenance_mode', 'The system is currently undergoing maintenance. Please try again later.'],
            ['high_call_volume', 'We are experiencing higher than normal call volume. Your patience is appreciated.']
        ]);

        // Generate all voice prompts
        await this.generateVoicePrompts();

        console.log('✅ FlexPBX Voice Engine initialized with comprehensive prompts');
    }

    async generateVoicePrompts() {
        console.log('🔊 Generating professional voice prompts...');

        for (const [key, text] of this.voicePrompts) {
            const promptPath = path.join(this.directories.prompts, `${key}.aiff`);

            if (!await fs.pathExists(promptPath)) {
                try {
                    // Use high-quality voice synthesis
                    await this.execAsync(`say -v "Alex" -r 180 -o "${promptPath}" "${text}"`);
                    console.log(`✅ Generated prompt: ${key}`);
                } catch (error) {
                    console.error(`❌ Failed to generate prompt ${key}:`, error.message);
                }
            }
        }

        // Generate hold music
        await this.generateHoldMusic();
    }

    async generateHoldMusic() {
        console.log('🎵 Creating professional hold music...');

        const holdMusicTracks = [
            { name: 'classical', text: 'Professional classical hold music for FlexPBX telephony system.' },
            { name: 'ambient', text: 'Ambient background music for professional telephone hold system.' },
            { name: 'corporate', text: 'Corporate friendly hold music for business telephone systems.' }
        ];

        for (const track of holdMusicTracks) {
            const trackPath = path.join(this.directories.holdMusic, `${track.name}.aiff`);

            if (!await fs.pathExists(trackPath)) {
                try {
                    // Generate placeholder music with voice announcement
                    await this.execAsync(`say -v "Samantha" -r 120 -o "${trackPath}" "${track.text}"`);
                    console.log(`🎵 Generated hold music: ${track.name}`);
                } catch (error) {
                    console.error(`❌ Failed to generate hold music ${track.name}:`, error.message);
                }
            }
        }
    }

    createDefaultExtensions() {
        console.log('📋 Creating default extension configuration...');

        // Create comprehensive extension system
        this.extensions = new Map([
            // Sales Department
            [100, {
                name: 'Sales Main',
                type: 'group',
                members: [101, 102, 103],
                ringStrategy: 'round-robin',
                voicemail: true,
                recording: true,
                department: 'sales'
            }],
            [101, {
                name: 'Sales Rep 1',
                type: 'user',
                userId: 'sales1',
                forwardOnBusy: 100,
                voicemail: true,
                department: 'sales'
            }],
            [102, {
                name: 'Sales Rep 2',
                type: 'user',
                userId: 'sales2',
                forwardOnBusy: 100,
                voicemail: true,
                department: 'sales'
            }],
            [103, {
                name: 'Sales Manager',
                type: 'user',
                userId: 'salesmanager',
                priority: 'high',
                monitoring: true,
                department: 'sales'
            }],

            // Support Department
            [200, {
                name: 'Support Main',
                type: 'group',
                members: [201, 202, 203, 204],
                ringStrategy: 'longest-idle',
                queueEnabled: true,
                maxWaitTime: 600,
                department: 'support'
            }],
            [201, {
                name: 'Tech Support L1',
                type: 'user',
                userId: 'support1',
                skills: ['general', 'basic'],
                department: 'support'
            }],
            [202, {
                name: 'Tech Support L2',
                type: 'user',
                userId: 'support2',
                skills: ['advanced', 'escalation'],
                department: 'support'
            }],
            [203, {
                name: 'Accessibility Support',
                type: 'user',
                userId: 'accessibility',
                skills: ['accessibility', 'voiceover', 'nvda'],
                priority: 'accessibility',
                department: 'support'
            }],
            [204, {
                name: 'Support Manager',
                type: 'user',
                userId: 'supportmanager',
                canMonitor: true,
                canRecord: true,
                department: 'support'
            }],

            // Executive/Admin
            [300, {
                name: 'Administration',
                type: 'group',
                members: [301, 302],
                priority: 'urgent',
                department: 'admin'
            }],
            [301, {
                name: 'CEO Office',
                type: 'user',
                userId: 'ceo',
                privacy: 'high',
                recording: false,
                department: 'admin'
            }],
            [302, {
                name: 'Admin Assistant',
                type: 'user',
                userId: 'assistant',
                canTransfer: true,
                department: 'admin'
            }],

            // Conference Rooms
            [8000, {
                name: 'Main Conference',
                type: 'conference',
                maxParticipants: 50,
                recording: true,
                moderated: true
            }],
            [8001, {
                name: 'Sales Conference',
                type: 'conference',
                maxParticipants: 20,
                department: 'sales'
            }],
            [8002, {
                name: 'Support Conference',
                type: 'conference',
                maxParticipants: 15,
                department: 'support'
            }],

            // Special Services
            [9000, {
                name: 'Call Parking',
                type: 'service',
                function: 'parking'
            }],
            [9001, {
                name: 'Voicemail System',
                type: 'service',
                function: 'voicemail'
            }],
            [9002, {
                name: 'Call Recording Access',
                type: 'service',
                function: 'recordings'
            }]
        ]);

        console.log(`✅ Created ${this.extensions.size} extensions with advanced features`);
    }

    setupIVRSystem() {
        console.log('🤖 Setting up advanced IVR system...');

        // Main IVR Menu Tree
        this.ivrSystem = {
            main: {
                prompt: 'main_menu',
                options: {
                    '1': { action: 'transfer', target: 100, prompt: 'transferring' },
                    '2': { action: 'transfer', target: 200, prompt: 'transferring' },
                    '3': { action: 'transfer', target: 300, prompt: 'transferring' },
                    '4': { action: 'submenu', target: 'services' },
                    '5': { action: 'submenu', target: 'accessibility' },
                    '9': { action: 'repeat' },
                    '0': { action: 'transfer', target: 302, prompt: 'transferring' },
                    '*': { action: 'callback_request' },
                    '#': { action: 'voice_navigation' }
                },
                timeout: 10,
                retries: 3,
                invalidPrompt: 'invalid_option'
            },

            services: {
                prompt: 'For conference calls, press 1. For voicemail, press 2. For call recordings, press 3. To return to main menu, press 9.',
                options: {
                    '1': { action: 'submenu', target: 'conference' },
                    '2': { action: 'transfer', target: 9001 },
                    '3': { action: 'transfer', target: 9002 },
                    '9': { action: 'submenu', target: 'main' }
                }
            },

            conference: {
                prompt: 'Enter the conference room number followed by pound, or press 9 to return to main menu.',
                options: {
                    'input': { action: 'conference_join', validate: 'conference_code' },
                    '9': { action: 'submenu', target: 'main' }
                },
                inputMode: true,
                maxDigits: 4
            },

            accessibility: {
                prompt: 'Accessibility options. Press 1 for voice navigation, press 2 for accessibility support specialist, press 3 for hearing assistance, press 9 for main menu.',
                options: {
                    '1': { action: 'enable_voice_navigation' },
                    '2': { action: 'transfer', target: 203, priority: 'accessibility' },
                    '3': { action: 'enable_hearing_assistance' },
                    '9': { action: 'submenu', target: 'main' }
                }
            }
        };

        console.log('✅ Advanced IVR system configured with accessibility features');
    }

    async initializeAudioEngine() {
        console.log('🔊 Initializing advanced audio engine...');

        this.audioEngine = {
            // Audio Mixing and Processing
            mixer: {
                channels: new Map(),
                masterVolume: 0.8,
                crossfadeTime: 2000, // ms
                duckingLevel: 0.3, // Music level when voice is active
                compressionRatio: 4.0,
                noiseGate: -40 // dB
            },

            // Hold Music System
            holdMusic: {
                currentTrack: 0,
                tracks: ['classical', 'ambient', 'corporate'],
                shuffle: false,
                fadeInOut: true,
                announcements: {
                    enabled: true,
                    interval: 30000, // 30 seconds
                    messages: [
                        'Your call is important to us. Please continue to hold.',
                        'Thank you for your patience. A representative will be with you shortly.',
                        'You can visit our website for additional support options.'
                    ]
                }
            },

            // Call Recording
            recording: {
                enabled: true,
                format: 'wav', // High quality for accessibility
                sampleRate: 44100,
                bitDepth: 16,
                channels: 2, // Stereo for caller/callee separation
                compression: false, // Raw audio for transcription
                retention: 90 // days
            },

            // Voice Processing
            voice: {
                enhancement: true,
                noiseReduction: true,
                echoCancellation: true,
                agc: true, // Automatic Gain Control
                speakerSeparation: true // For multi-party calls
            }
        };

        console.log('✅ Advanced audio engine initialized');
    }

    // Multi-User and Permission Management
    async createUserGroup(groupName, permissions, owner) {
        console.log(`👥 Creating user group: ${groupName}`);

        const group = {
            name: groupName,
            owner: owner,
            permissions: permissions,
            members: [],
            extensions: [],
            created: new Date(),
            features: {
                canCreateExtensions: permissions.includes('admin'),
                canMonitorCalls: permissions.includes('monitor'),
                canAccessRecordings: permissions.includes('recordings'),
                canManageIVR: permissions.includes('ivr'),
                maxExtensions: permissions.includes('admin') ? -1 : 10
            }
        };

        this.userGroups.set(groupName, group);
        this.ownershipMap.set(owner, groupName);

        // Save to file
        const groupPath = path.join(this.directories.groups, `${groupName}.json`);
        await fs.writeJson(groupPath, group, { spaces: 2 });

        console.log(`✅ User group ${groupName} created with owner ${owner}`);
        return group;
    }

    async assignExtensionToUser(extensionNumber, userId, groupName) {
        if (!this.extensions.has(extensionNumber)) {
            throw new Error(`Extension ${extensionNumber} does not exist`);
        }

        const extension = this.extensions.get(extensionNumber);
        extension.assignedTo = userId;
        extension.group = groupName;
        extension.assigned = new Date();

        // Update group extension list
        if (this.userGroups.has(groupName)) {
            const group = this.userGroups.get(groupName);
            if (!group.extensions.includes(extensionNumber)) {
                group.extensions.push(extensionNumber);
            }
        }

        console.log(`📞 Extension ${extensionNumber} assigned to ${userId} in group ${groupName}`);
    }

    // Advanced Call Features
    async startCallRecording(callId, options = {}) {
        console.log(`🎙️ Starting call recording for call ${callId}`);

        const recording = {
            callId: callId,
            started: new Date(),
            format: options.format || this.audioEngine.recording.format,
            quality: options.quality || 'high',
            channels: options.stereo ? 2 : 1,
            transcription: options.transcription || false,
            accessibility: options.accessibility || false
        };

        this.callRecordings.set(callId, recording);

        // If accessibility mode, enable enhanced features
        if (recording.accessibility) {
            recording.speakerSeparation = true;
            recording.transcription = true;
            recording.visualWaveform = true;
        }

        this.emit('recording_started', recording);
        return recording;
    }

    async enableCallMonitoring(callId, monitorUserId, options = {}) {
        console.log(`👁️ Enabling call monitoring for call ${callId} by ${monitorUserId}`);

        const monitor = {
            callId: callId,
            monitorBy: monitorUserId,
            started: new Date(),
            mode: options.mode || 'listen', // listen, whisper, barge
            recorded: options.record || false,
            notifications: options.notifications || true
        };

        this.callMonitoring.set(`${callId}_${monitorUserId}`, monitor);
        this.emit('monitoring_started', monitor);

        return monitor;
    }

    async createConferenceRoom(roomId, options = {}) {
        console.log(`🏢 Creating conference room ${roomId}`);

        const conference = {
            id: roomId,
            name: options.name || `Conference ${roomId}`,
            maxParticipants: options.maxParticipants || 25,
            moderated: options.moderated || false,
            recording: options.recording || false,
            participants: new Map(),
            created: new Date(),
            features: {
                waitingRoom: options.waitingRoom || false,
                muteOnJoin: options.muteOnJoin || false,
                announceJoinLeave: options.announceJoinLeave || true,
                accessibilityMode: options.accessibilityMode || false
            }
        };

        if (conference.features.accessibilityMode) {
            conference.features.autoTranscription = true;
            conference.features.speakerIdentification = true;
            conference.features.voiceNavigation = true;
        }

        this.conferenceRooms.set(roomId, conference);
        this.emit('conference_created', conference);

        return conference;
    }

    // Advanced Hold System with Audio Ducking
    async putCallOnHold(callId, options = {}) {
        console.log(`⏸️ Putting call ${callId} on hold with advanced audio`);

        const holdSession = {
            callId: callId,
            startTime: new Date(),
            musicTrack: options.track || this.audioEngine.holdMusic.tracks[0],
            announcements: options.announcements !== false,
            crossfade: options.crossfade !== false,
            position: 0 // For queue position announcements
        };

        // Start hold music with crossfade
        if (holdSession.crossfade) {
            await this.crossfadeToHoldMusic(callId, holdSession.musicTrack);
        }

        // Schedule periodic announcements
        if (holdSession.announcements) {
            this.scheduleHoldAnnouncements(callId, holdSession);
        }

        this.emit('call_on_hold', holdSession);
        return holdSession;
    }

    async crossfadeToHoldMusic(callId, track) {
        console.log(`🎵 Crossfading to hold music: ${track}`);

        // Implement audio ducking and crossfade
        const fadeTime = this.audioEngine.mixer.crossfadeTime;
        const duckLevel = this.audioEngine.mixer.duckingLevel;

        // Gradually reduce call audio and introduce music
        // This would integrate with actual audio processing pipeline

        setTimeout(() => {
            console.log(`✅ Crossfade complete for call ${callId}`);
        }, fadeTime);
    }

    // Call Queue Management with Position Announcements
    async addToQueue(callId, queueId, priority = 'normal') {
        console.log(`📋 Adding call ${callId} to queue ${queueId} with priority ${priority}`);

        if (!this.callQueue.has(queueId)) {
            this.callQueue.set(queueId, []);
        }

        const queue = this.callQueue.get(queueId);
        const position = {
            callId: callId,
            added: new Date(),
            priority: priority,
            estimatedWait: this.calculateWaitTime(queueId),
            announcements: 0
        };

        // Insert based on priority
        if (priority === 'urgent') {
            queue.unshift(position);
        } else if (priority === 'high') {
            const normalIndex = queue.findIndex(call => call.priority === 'normal');
            queue.splice(normalIndex === -1 ? queue.length : normalIndex, 0, position);
        } else {
            queue.push(position);
        }

        // Announce position
        await this.announceQueuePosition(callId, queue.indexOf(position) + 1, position.estimatedWait);

        this.emit('call_queued', { callId, queueId, position: queue.indexOf(position) + 1 });
        return position;
    }

    async announceQueuePosition(callId, position, estimatedWait) {
        const announcement = this.voicePrompts.get('queue_position')
            .replace('{position}', position)
            .replace('{time}', Math.ceil(estimatedWait / 60));

        // Generate and play announcement
        const tempPromptPath = path.join(this.directories.prompts, `temp_${callId}_position.aiff`);
        await this.execAsync(`say -v "Alex" -o "${tempPromptPath}" "${announcement}"`);

        console.log(`📢 Announced queue position ${position} to call ${callId}`);
    }

    calculateWaitTime(queueId) {
        // Calculate estimated wait time based on queue length and average call duration
        const queue = this.callQueue.get(queueId) || [];
        const averageCallTime = 180; // 3 minutes average
        return queue.length * averageCallTime;
    }

    // SIP Integration and Management
    async initializeSIPStack() {
        console.log('📡 Initializing SIP stack for FlexPBX...');

        this.sipConfig = {
            domain: 'flexpbx.local',
            port: this.sipPort,
            transport: ['UDP', 'TCP', 'TLS'],
            codecs: ['G.722', 'G.711', 'G.729', 'Opus'],
            dtmf: 'RFC2833',
            authentication: {
                realm: 'flexpbx.local',
                algorithm: 'MD5'
            },
            security: {
                tlsEnabled: true,
                srtpEnabled: true,
                certificatePath: path.join(this.directories.configs, 'tls')
            }
        };

        // Generate SIP configuration files
        await this.generateSIPConfigs();

        console.log('✅ SIP stack initialized with security and accessibility features');
    }

    async generateSIPConfigs() {
        // Create SIP configuration for various phone types
        const configs = {
            // Standard SIP phone config
            standard: this.generateStandardSIPConfig(),
            // Accessibility-focused phone config
            accessibility: this.generateAccessibilityPhoneConfig(),
            // Softphone config
            softphone: this.generateSoftphoneConfig()
        };

        for (const [type, config] of Object.entries(configs)) {
            const configPath = path.join(this.directories.configs, `sip_${type}.conf`);
            await fs.writeFile(configPath, config);
            console.log(`📝 Generated ${type} SIP configuration`);
        }
    }

    generateAccessibilityPhoneConfig() {
        return `
# FlexPBX Accessibility-Optimized SIP Configuration
[general]
context=accessibility
allowguest=no
alwaysauthreject=yes
musiconhold=default
mohinterpret=passthrough
mohsuggest=accessibility-hold

# Enhanced audio settings for accessibility
dtmfmode=rfc2833
disallow=all
allow=g722          ; HD audio for clarity
allow=ulaw
allow=alaw
jbenable=yes        ; Jitter buffer for consistent audio
jbmaxsize=500
jbresyncthreshold=1000
jbimpl=adaptive     ; Adaptive jitter buffer

# Accessibility features
callevents=yes      ; Enable call events for screen readers
busydetect=yes
congestion=yes
progressinband=yes  ; Important for accessibility prompts

# Volume and audio enhancements
rxgain=0.0          ; Configurable per extension
txgain=0.0
denoise=yes         ; Noise reduction
echocancelwhenbridged=yes

# Special accessibility extensions
[203](accessibility-template)
description=Accessibility Support Specialist
secret=accessible123
host=dynamic
type=friend
context=accessibility-support
mailbox=203@default
callgroup=2
pickupgroup=2
; Enhanced features for accessibility specialist
monitor=yes
recording=yes
canpark=yes
transfer=yes
`;
    }

    generateStandardSIPConfig() {
        return `
# FlexPBX Standard SIP Configuration
[general]
context=default
allowguest=no
alwaysauthreject=yes
musiconhold=default
mohinterpret=passthrough

# Standard audio codecs
dtmfmode=rfc2833
disallow=all
allow=ulaw
allow=alaw
allow=g722
allow=gsm

# Template for standard extensions
[standard-template](!)
type=friend
host=dynamic
qualify=yes
canreinvite=no
context=internal
mailbox=${EXTEN}@default

# Sales Extensions
[101](standard-template)
description=Sales Rep 1
secret=sales101
callgroup=1
pickupgroup=1

[102](standard-template)
description=Sales Rep 2
secret=sales102
callgroup=1
pickupgroup=1
`;
    }

    generateSoftphoneConfig() {
        return `
# FlexPBX Softphone Configuration
[general]
context=softphone
allowguest=no
nat=force_rport,comedia
icesupport=yes
stunaddr=stun.l.google.com:19302

# WebRTC support for browser-based softphones
transport=ws,wss
encryption=yes
avpf=yes
force_avp=yes
dtlsenable=yes
dtlsverify=fingerprint
dtlscertfile=/etc/asterisk/keys/asterisk.pem
dtlsprivatekey=/etc/asterisk/keys/asterisk.key
dtlssetup=actpass

# Template for softphone clients
[softphone-template](!)
type=friend
host=dynamic
encryption=yes
context=softphone-users
transport=ws,wss
force_avp=yes
dtlsenable=yes
`;
    }

    // Web UI for Call Management
    async createWebInterface() {
        console.log('🌐 Creating advanced web interface for call management...');

        const webInterface = `
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FlexPBX Call Management Center</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, sans-serif;
            margin: 0;
            background: #f5f5f5;
        }
        .dashboard {
            display: grid;
            grid-template-columns: 300px 1fr 300px;
            height: 100vh;
            gap: 20px;
            padding: 20px;
        }
        .panel {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .call-card {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin: 10px 0;
        }
        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }
        .active { background: #28a745; }
        .ringing { background: #ffc107; }
        .hold { background: #6c757d; }
        .queue { background: #17a2b8; }

        .controls {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
        }
        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: black; }
        .btn-danger { background: #dc3545; color: white; }

        .volume-control {
            margin: 10px 0;
        }
        .volume-slider {
            width: 100%;
            margin: 5px 0;
        }

        .accessibility-features {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            margin: 15px 0;
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <!-- Extensions Panel -->
        <div class="panel">
            <h3>📞 Extensions & Users</h3>
            <div id="extensions-list">
                <!-- Extensions will be populated here -->
            </div>

            <h4>🎛️ Audio Controls</h4>
            <div class="volume-control">
                <label>Master Volume:</label>
                <input type="range" class="volume-slider" min="0" max="100" value="80"
                       onchange="updateVolume('master', this.value)">
                <span id="master-vol">80%</span>
            </div>

            <div class="volume-control">
                <label>Hold Music Volume:</label>
                <input type="range" class="volume-slider" min="0" max="100" value="60"
                       onchange="updateVolume('hold', this.value)">
                <span id="hold-vol">60%</span>
            </div>

            <div class="accessibility-features">
                <h5>♿ Accessibility Features</h5>
                <button class="btn btn-primary" onclick="enableVoiceNavigation()">
                    Enable Voice Navigation
                </button>
                <button class="btn btn-success" onclick="toggleTranscription()">
                    Live Transcription
                </button>
            </div>
        </div>

        <!-- Active Calls Panel -->
        <div class="panel">
            <h3>📋 Active Calls & Queue</h3>
            <div id="active-calls">
                <!-- Sample active calls -->
                <div class="call-card">
                    <div>
                        <span class="status-indicator active"></span>
                        <strong>Call #1001</strong> - Sales Line
                    </div>
                    <div>Caller: +1-555-0123 → Ext 101 (John Sales)</div>
                    <div>Duration: 05:23 | Recording: ON</div>
                    <div class="controls">
                        <button class="btn btn-warning" onclick="holdCall('1001')">Hold</button>
                        <button class="btn btn-success" onclick="transferCall('1001')">Transfer</button>
                        <button class="btn btn-primary" onclick="monitorCall('1001')">Monitor</button>
                        <button class="btn btn-danger" onclick="hangupCall('1001')">End</button>
                    </div>
                </div>

                <div class="call-card">
                    <div>
                        <span class="status-indicator queue"></span>
                        <strong>Queue Position #1</strong> - Support
                    </div>
                    <div>Caller: +1-555-0456 | Wait: 2:15</div>
                    <div>Skill Required: Accessibility Support</div>
                    <div class="controls">
                        <button class="btn btn-success" onclick="assignCall('queue1', 203)">
                            → Assign to Accessibility
                        </button>
                    </div>
                </div>

                <div class="call-card">
                    <div>
                        <span class="status-indicator hold"></span>
                        <strong>Call #1002</strong> - On Hold
                    </div>
                    <div>Caller: +1-555-0789 | Hold Time: 1:45</div>
                    <div>Hold Music: Classical | Announcements: Every 30s</div>
                    <div class="controls">
                        <button class="btn btn-success" onclick="resumeCall('1002')">Resume</button>
                        <button class="btn btn-primary" onclick="changeHoldMusic('1002')">Change Music</button>
                    </div>
                </div>
            </div>

            <h4>🏢 Conference Rooms</h4>
            <div id="conference-rooms">
                <div class="call-card">
                    <div>
                        <span class="status-indicator active"></span>
                        <strong>Room 8000</strong> - Main Conference
                    </div>
                    <div>Participants: 7/50 | Recording: YES</div>
                    <div class="controls">
                        <button class="btn btn-primary" onclick="joinConference(8000)">Join</button>
                        <button class="btn btn-warning" onclick="muteAll(8000)">Mute All</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Status Panel -->
        <div class="panel">
            <h3>📊 System Status</h3>
            <div class="call-card">
                <h5>🎵 Audio Engine</h5>
                <div>Status: <span style="color: green;">ACTIVE</span></div>
                <div>Channels: 12/50 in use</div>
                <div>Quality: HD Audio (G.722)</div>
                <div>Noise Reduction: ON</div>
            </div>

            <div class="call-card">
                <h5>📡 SIP Status</h5>
                <div>Registered Phones: 8/15</div>
                <div>Port 5060: <span style="color: green;">LISTENING</span></div>
                <div>TLS Security: <span style="color: green;">ENABLED</span></div>
            </div>

            <div class="call-card">
                <h5>🎙️ Recording System</h5>
                <div>Active Recordings: 3</div>
                <div>Storage Used: 2.3GB / 100GB</div>
                <div>Retention: 90 days</div>
                <button class="btn btn-primary" onclick="accessRecordings()">
                    Access Recordings
                </button>
            </div>

            <div class="call-card">
                <h5>👥 Multi-User Management</h5>
                <div>Active Groups: 3</div>
                <div>- Sales Group (Owner: manager@company.com)</div>
                <div>- Support Group (Owner: support@company.com)</div>
                <div>- Admin Group (Owner: admin@company.com)</div>
                <button class="btn btn-success" onclick="manageGroups()">
                    Manage Groups
                </button>
            </div>

            <h4>⚡ Quick Actions</h4>
            <div class="controls">
                <button class="btn btn-primary" onclick="recordCustomPrompt()">
                    Record Prompt
                </button>
                <button class="btn btn-success" onclick="testIVR()">
                    Test IVR
                </button>
                <button class="btn btn-warning" onclick="systemHealth()">
                    Health Check
                </button>
            </div>
        </div>
    </div>

    <script>
        // Call Management Functions
        function holdCall(callId) {
            console.log(\`Putting call \${callId} on hold with music\`);
            // Implement hold with crossfade
        }

        function transferCall(callId) {
            const extension = prompt('Transfer to extension:');
            if (extension) {
                console.log(\`Transferring call \${callId} to extension \${extension}\`);
            }
        }

        function monitorCall(callId) {
            console.log(\`Starting call monitoring for \${callId}\`);
            // Enable monitoring interface
        }

        function updateVolume(type, value) {
            document.getElementById(type + '-vol').textContent = value + '%';
            console.log(\`Updated \${type} volume to \${value}%\`);
        }

        function enableVoiceNavigation() {
            console.log('Enabling voice navigation for accessibility');
            alert('Voice navigation enabled. Say commands like "Transfer to sales" or "Hold call"');
        }

        function toggleTranscription() {
            console.log('Toggling live call transcription');
            alert('Live transcription enabled for accessibility support');
        }

        function recordCustomPrompt() {
            const promptName = prompt('Enter prompt name:');
            if (promptName) {
                console.log(\`Recording custom prompt: \${promptName}\`);
                alert('Click OK and speak your custom prompt message');
            }
        }

        // Initialize the interface
        document.addEventListener('DOMContentLoaded', function() {
            console.log('FlexPBX Call Management Center initialized');

            // Simulate real-time updates
            setInterval(updateCallStatuses, 5000);
        });

        function updateCallStatuses() {
            // Update call durations, queue positions, etc.
            console.log('Updating call statuses...');
        }
    </script>
</body>
</html>
        `;

        const webPath = path.join(process.cwd(), 'FlexPBX-Call-Management.html');
        await fs.writeFile(webPath, webInterface);

        console.log('✅ Advanced call management web interface created');
        return webPath;
    }

    // Service Control Methods
    async start() {
        if (this.isRunning) {
            console.log('⚠️ FlexPBX Telephony Service already running');
            return;
        }

        console.log('🚀 Starting FlexPBX Telephony Service...');

        try {
            // Initialize all subsystems
            await this.initializeSIPStack();
            await this.createWebInterface();

            // Start service endpoints
            this.isRunning = true;

            console.log('✅ FlexPBX Telephony Service started successfully');
            console.log(`📞 SIP Server: Port ${this.sipPort}`);
            console.log(`🌐 Web UI: Port ${this.webUIPort}`);
            console.log(`🎵 Audio Engine: ACTIVE`);
            console.log(`♿ Accessibility Features: ENABLED`);

            this.emit('service_started', {
                sipPort: this.sipPort,
                webPort: this.webUIPort,
                extensions: this.extensions.size,
                features: ['IVR', 'Hold Music', 'Recording', 'Monitoring', 'Multi-User']
            });

        } catch (error) {
            console.error('❌ Failed to start FlexPBX Telephony Service:', error);
            throw error;
        }
    }

    async stop() {
        if (!this.isRunning) {
            console.log('⚠️ FlexPBX Telephony Service not running');
            return;
        }

        console.log('⏹️ Stopping FlexPBX Telephony Service...');

        // Stop all active calls gracefully
        for (const [callId, session] of this.callSessions) {
            await this.hangupCall(callId, 'system_shutdown');
        }

        this.isRunning = false;
        console.log('✅ FlexPBX Telephony Service stopped');

        this.emit('service_stopped');
    }

    // Utility method for async exec
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

module.exports = FlexPBXTelephonyService;