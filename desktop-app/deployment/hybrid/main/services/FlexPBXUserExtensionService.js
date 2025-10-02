const { EventEmitter } = require('events');
const fs = require('fs-extra');
const path = require('path');
const crypto = require('crypto');

/**
 * 📞 FlexPBX User Extension Service
 * Creates and manages internal FlexPBX extensions for direct SIP testing
 */
class FlexPBXUserExtensionService extends EventEmitter {
    constructor() {
        super();

        // FlexPBX Internal SIP Server Configuration
        this.sipServer = {
            domain: 'flexpbx.local',
            port: 5070, // Different from CallCentric to avoid conflicts
            transport: ['UDP', 'TCP'],
            realm: 'flexpbx.internal'
        };

        // User Extensions Database
        this.userExtensions = new Map();
        this.activeRegistrations = new Map();
        this.callSessions = new Map();

        this.setupUserExtensions();
        this.initializeSIPServer();
    }

    setupUserExtensions() {
        console.log('👤 Setting up FlexPBX user extensions...');

        // Create test user extension for direct FlexPBX testing
        const testUserExtension = {
            extension: '2001',
            username: 'testuser',
            password: 'FlexPBX2001!',
            displayName: 'FlexPBX Test User',
            email: 'testuser@flexpbx.local',
            department: 'testing',
            permissions: {
                makeInternalCalls: true,
                makeExternalCalls: true,
                receiveInternalCalls: true,
                receiveExternalCalls: true,
                accessVoicemail: true,
                recordCalls: true,
                transferCalls: true,
                conference: true,
                monitoring: false,
                administration: false
            },
            features: {
                callWaiting: true,
                callForwarding: true,
                doNotDisturb: false,
                simultaneousRing: false,
                voicemailToEmail: true,
                mobileApp: true
            },
            sipSettings: {
                codec: ['G722', 'PCMU', 'PCMA'],
                dtmf: 'RFC2833',
                nat: true,
                directMedia: false,
                qualify: 'yes',
                canReinvite: false
            },
            created: new Date(),
            lastLogin: null,
            status: 'active'
        };

        // Create admin extension for testing advanced features
        const adminExtension = {
            extension: '2000',
            username: 'admin',
            password: 'FlexPBXAdmin2000!',
            displayName: 'FlexPBX Administrator',
            email: 'admin@flexpbx.local',
            department: 'administration',
            permissions: {
                makeInternalCalls: true,
                makeExternalCalls: true,
                receiveInternalCalls: true,
                receiveExternalCalls: true,
                accessVoicemail: true,
                recordCalls: true,
                transferCalls: true,
                conference: true,
                monitoring: true,
                administration: true,
                systemSettings: true,
                userManagement: true
            },
            features: {
                callWaiting: true,
                callForwarding: true,
                doNotDisturb: false,
                simultaneousRing: true,
                voicemailToEmail: true,
                mobileApp: true,
                priorityRing: true
            },
            sipSettings: {
                codec: ['G722', 'PCMU', 'PCMA', 'G729'],
                dtmf: 'RFC2833',
                nat: true,
                directMedia: false,
                qualify: 'yes',
                canReinvite: false,
                encryption: 'optional'
            },
            created: new Date(),
            lastLogin: null,
            status: 'active'
        };

        // Create demo extension for customer testing
        const demoExtension = {
            extension: '2002',
            username: 'demo',
            password: 'FlexPBXDemo2002!',
            displayName: 'FlexPBX Demo User',
            email: 'demo@flexpbx.local',
            department: 'demo',
            permissions: {
                makeInternalCalls: true,
                makeExternalCalls: false, // Limited for demo
                receiveInternalCalls: true,
                receiveExternalCalls: true,
                accessVoicemail: true,
                recordCalls: false,
                transferCalls: true,
                conference: true,
                monitoring: false,
                administration: false
            },
            features: {
                callWaiting: true,
                callForwarding: false,
                doNotDisturb: false,
                simultaneousRing: false,
                voicemailToEmail: false,
                mobileApp: true
            },
            sipSettings: {
                codec: ['G722', 'PCMU'],
                dtmf: 'RFC2833',
                nat: true,
                directMedia: false,
                qualify: 'yes'
            },
            created: new Date(),
            lastLogin: null,
            status: 'active'
        };

        // Add extensions to the system
        this.userExtensions.set('2001', testUserExtension);
        this.userExtensions.set('2000', adminExtension);
        this.userExtensions.set('2002', demoExtension);

        console.log(`✅ Created ${this.userExtensions.size} FlexPBX user extensions`);
        this.displayExtensionInfo();
    }

    displayExtensionInfo() {
        console.log('\n📋 FlexPBX User Extensions Created:');
        console.log('=' .repeat(70));

        for (const [extNum, ext] of this.userExtensions) {
            console.log(`\n📞 Extension ${extNum} - ${ext.displayName}`);
            console.log(`   Username: ${ext.username}`);
            console.log(`   Password: ${ext.password}`);
            console.log(`   SIP URI: sip:${ext.username}@${this.sipServer.domain}:${this.sipServer.port}`);
            console.log(`   Department: ${ext.department}`);
            console.log(`   Features: ${Object.keys(ext.features).filter(f => ext.features[f]).join(', ')}`);
        }

        console.log('\n🔧 SIP Server Configuration:');
        console.log(`   Domain: ${this.sipServer.domain}`);
        console.log(`   Port: ${this.sipServer.port}`);
        console.log(`   Realm: ${this.sipServer.realm}`);
        console.log(`   Transports: ${this.sipServer.transport.join(', ')}`);
    }

    initializeSIPServer() {
        console.log('📡 Initializing FlexPBX Internal SIP Server...');

        this.sipServerConfig = {
            // Basic SIP server settings
            general: {
                context: 'flexpbx-internal',
                allowguest: 'no',
                alwaysauthreject: 'yes',
                musiconhold: 'default',
                mohinterpret: 'passthrough',
                realm: this.sipServer.realm,
                udpbindaddr: `0.0.0.0:${this.sipServer.port}`,
                tcpbindaddr: `0.0.0.0:${this.sipServer.port}`,
                transport: 'udp,tcp'
            },

            // Audio codecs
            codecs: {
                disallow: 'all',
                allow: ['g722', 'ulaw', 'alaw'],
                dtmfmode: 'rfc2833',
                nat: 'force_rport,comedia',
                directmedia: 'no',
                qualify: 'yes'
            },

            // Security settings
            security: {
                encryption: 'yes',
                tlsenable: 'yes',
                tlsbindaddr: `0.0.0.0:${this.sipServer.port + 1}`,
                tlscertfile: 'flexpbx.pem',
                tlsprivatekey: 'flexpbx.key'
            }
        };

        this.generateSIPConfigurations();
        console.log('✅ FlexPBX Internal SIP Server ready');
    }

    generateSIPConfigurations() {
        console.log('📝 Generating SIP configuration files...');

        // Generate main SIP configuration
        const sipConf = this.generateMainSipConfig();
        this.saveSipConfig('sip.conf', sipConf);

        // Generate extension configurations
        const extensionsConf = this.generateExtensionsConfig();
        this.saveSipConfig('extensions.conf', extensionsConf);

        // Generate users configuration
        const usersConf = this.generateUsersConfig();
        this.saveSipConfig('users.conf', usersConf);

        console.log('✅ SIP configuration files generated');
    }

    generateMainSipConfig() {
        return `; FlexPBX Internal SIP Configuration
; Generated automatically - do not edit manually

[general]
context=${this.sipServerConfig.general.context}
allowguest=${this.sipServerConfig.general.allowguest}
alwaysauthreject=${this.sipServerConfig.general.alwaysauthreject}
musiconhold=${this.sipServerConfig.general.musiconhold}
realm=${this.sipServerConfig.general.realm}
udpbindaddr=${this.sipServerConfig.general.udpbindaddr}
tcpbindaddr=${this.sipServerConfig.general.tcpbindaddr}
transport=${this.sipServerConfig.general.transport}

; Codec settings
disallow=${this.sipServerConfig.codecs.disallow}
allow=${this.sipServerConfig.codecs.allow.join(',')}
dtmfmode=${this.sipServerConfig.codecs.dtmfmode}
nat=${this.sipServerConfig.codecs.nat}
directmedia=${this.sipServerConfig.codecs.directmedia}
qualify=${this.sipServerConfig.codecs.qualify}

; Security
encryption=${this.sipServerConfig.security.encryption}
tlsenable=${this.sipServerConfig.security.tlsenable}
tlsbindaddr=${this.sipServerConfig.security.tlsbindaddr}

; Template for FlexPBX users
[flexpbx-user-template](!)
type=friend
host=dynamic
qualify=yes
canreinvite=no
context=flexpbx-internal
dtmfmode=rfc2833
nat=force_rport,comedia
directmedia=no
disallow=all
allow=g722,ulaw,alaw
`;
    }

    generateExtensionsConfig() {
        const extensions = Array.from(this.userExtensions.values());

        let config = `; FlexPBX Extensions Configuration
; Dialplan for internal extensions

[flexpbx-internal]
; Internal extension dialing
`;

        for (const ext of extensions) {
            config += `exten => ${ext.extension},1,Dial(SIP/${ext.username},20)\n`;
            config += `exten => ${ext.extension},2,Voicemail(${ext.extension}@flexpbx)\n`;
            config += `exten => ${ext.extension},3,Hangup()\n\n`;
        }

        config += `; Voicemail access
exten => *97,1,VoicemailMain(\${CALLERID(num)}@flexpbx)
exten => *97,2,Hangup()

; Conference rooms
exten => 8000,1,ConfBridge(8000,flexpbx_conference)
exten => 8001,1,ConfBridge(8001,flexpbx_conference)

; Echo test
exten => 9196,1,Answer()
exten => 9196,2,Echo()
exten => 9196,3,Hangup()

; Time/Date
exten => *60,1,Answer()
exten => *60,2,SayUnixTime()
exten => *60,3,Hangup()
`;

        return config;
    }

    generateUsersConfig() {
        let config = `; FlexPBX Users Configuration
; SIP user definitions

`;

        for (const [extNum, ext] of this.userExtensions) {
            config += `[${ext.username}](flexpbx-user-template)
secret=${ext.password}
callerid="${ext.displayName}" <${ext.extension}>
mailbox=${ext.extension}@flexpbx
context=flexpbx-internal

`;
        }

        return config;
    }

    async saveSipConfig(filename, content) {
        const configDir = path.join(process.cwd(), 'pbx-data', 'sip-configs');
        await fs.ensureDir(configDir);

        const configPath = path.join(configDir, filename);
        await fs.writeFile(configPath, content);
        console.log(`📝 Saved: ${filename}`);
    }

    // SIP Client Configuration Generators
    generateClientConfig(extension, clientType = 'generic') {
        const ext = this.userExtensions.get(extension);
        if (!ext) {
            throw new Error(`Extension ${extension} not found`);
        }

        const configs = {
            generic: this.generateGenericSipConfig(ext),
            telephone: this.generateTelephoneConfig(ext),
            zoiper: this.generateZoiperConfig(ext),
            bria: this.generateBriaConfig(ext),
            xlite: this.generateXLiteConfig(ext)
        };

        return configs[clientType] || configs.generic;
    }

    generateGenericSipConfig(ext) {
        return `# FlexPBX Extension ${ext.extension} - Generic SIP Configuration
# User: ${ext.displayName}

[Account Settings]
Display Name: ${ext.displayName}
Username: ${ext.username}
Password: ${ext.password}
Domain: ${this.sipServer.domain}
Proxy: ${this.sipServer.domain}:${this.sipServer.port}
Register: Yes

[Network Settings]
Transport: UDP (Primary), TCP (Secondary)
Local Port: Auto
NAT Traversal: Yes
STUN Server: (Optional)

[Audio Settings]
Preferred Codecs: G.722, PCMU, PCMA
DTMF Method: RFC2833
Echo Cancellation: Yes
Noise Suppression: Yes

[Features]
Call Waiting: ${ext.features.callWaiting ? 'Yes' : 'No'}
Call Transfer: ${ext.permissions.transferCalls ? 'Yes' : 'No'}
Conference: ${ext.permissions.conference ? 'Yes' : 'No'}
Voicemail: ${ext.permissions.accessVoicemail ? 'Yes' : 'No'}
Call Recording: ${ext.permissions.recordCalls ? 'Yes' : 'No'}

[Security]
Encryption: Optional
TLS: Optional (Port ${this.sipServer.port + 1})
`;
    }

    generateTelephoneConfig(ext) {
        return `# Telephone App Configuration - FlexPBX Extension ${ext.extension}
# User: ${ext.displayName}

Server: ${this.sipServer.domain}:${this.sipServer.port}
Username: ${ext.username}
Password: ${ext.password}
Full Name: ${ext.displayName}
Domain: ${this.sipServer.domain}
Re-register Time: 300

# Advanced Settings
Substitute Plus Character: No
Plus Character Substitution:
Use DNS SRV: No
Outbound Proxy:
STUN Server:

# Audio
Use G.711u codec: Yes
Use G.711a codec: Yes
Use G.722 codec: Yes
Use G.729 codec: No
`;
    }

    generateZoiperConfig(ext) {
        return `# Zoiper Configuration - FlexPBX Extension ${ext.extension}
# Export this as XML or manual configuration

Account name: ${ext.displayName}
Host: ${this.sipServer.domain}
Port: ${this.sipServer.port}
Username: ${ext.username}
Password: ${ext.password}
Caller ID: ${ext.extension}
Auth name: ${ext.username}
Outbound proxy: ${this.sipServer.domain}:${this.sipServer.port}
Domain: ${this.sipServer.domain}

# Codecs (in priority order)
1. G722/16000 (HD Audio)
2. PCMU/8000
3. PCMA/8000

# DTMF: RFC2833
# Transport: UDP
# Registration: 300 seconds
`;
    }

    // User Management Functions
    async authenticateUser(username, password) {
        console.log(`🔐 Authenticating user: ${username}`);

        for (const ext of this.userExtensions.values()) {
            if (ext.username === username && ext.password === password) {
                ext.lastLogin = new Date();
                console.log(`✅ Authentication successful for ${ext.displayName}`);
                return {
                    success: true,
                    extension: ext.extension,
                    displayName: ext.displayName,
                    permissions: ext.permissions
                };
            }
        }

        console.log(`❌ Authentication failed for ${username}`);
        return { success: false, error: 'Invalid credentials' };
    }

    async registerExtension(username, contact, userAgent = 'Unknown') {
        console.log(`📞 Registering extension for user: ${username}`);

        const auth = await this.authenticateUser(username, 'registration-request');
        if (!auth.success) {
            // Find extension by username for registration
            for (const ext of this.userExtensions.values()) {
                if (ext.username === username) {
                    const registration = {
                        username: username,
                        extension: ext.extension,
                        contact: contact,
                        userAgent: userAgent,
                        registered: new Date(),
                        expires: new Date(Date.now() + 3600000), // 1 hour
                        status: 'registered'
                    };

                    this.activeRegistrations.set(username, registration);
                    console.log(`✅ Extension ${ext.extension} registered successfully`);

                    this.emit('extension_registered', registration);
                    return { success: true, registration };
                }
            }
        }

        return { success: false, error: 'Registration failed' };
    }

    getExtensionStatus(extension) {
        const ext = this.userExtensions.get(extension);
        if (!ext) return null;

        const registration = this.activeRegistrations.get(ext.username);
        const isRegistered = registration && registration.expires > new Date();

        return {
            extension: extension,
            displayName: ext.displayName,
            username: ext.username,
            status: isRegistered ? 'registered' : 'offline',
            lastSeen: ext.lastLogin,
            contact: registration?.contact || null,
            permissions: ext.permissions,
            features: ext.features
        };
    }

    getAllExtensionStatuses() {
        const statuses = {};
        for (const [extNum] of this.userExtensions) {
            statuses[extNum] = this.getExtensionStatus(extNum);
        }
        return statuses;
    }

    // Service Control
    async start() {
        console.log('🚀 Starting FlexPBX User Extension Service...');

        try {
            // Create SIP server directories
            await this.ensureDirectories();

            // Generate SSL certificates if needed
            await this.generateSSLCertificates();

            console.log('✅ FlexPBX User Extension Service started');
            console.log(`📞 Internal SIP Server: ${this.sipServer.domain}:${this.sipServer.port}`);
            console.log(`👤 User Extensions: ${this.userExtensions.size} active`);

            this.emit('service_started', {
                sipServer: this.sipServer,
                extensions: Array.from(this.userExtensions.keys())
            });

        } catch (error) {
            console.error('❌ Failed to start User Extension Service:', error);
            throw error;
        }
    }

    async ensureDirectories() {
        const dirs = [
            'pbx-data/sip-configs',
            'pbx-data/certificates',
            'pbx-data/voicemail',
            'pbx-data/recordings',
            'pbx-data/logs'
        ];

        for (const dir of dirs) {
            await fs.ensureDir(path.join(process.cwd(), dir));
        }
    }

    async generateSSLCertificates() {
        const certDir = path.join(process.cwd(), 'pbx-data', 'certificates');
        const certPath = path.join(certDir, 'flexpbx.pem');

        if (!await fs.pathExists(certPath)) {
            console.log('🔐 Generating SSL certificates for secure SIP...');
            // In production, generate real certificates
            // For now, create placeholder
            await fs.writeFile(certPath, '# FlexPBX SSL Certificate Placeholder');
            console.log('✅ SSL certificates prepared');
        }
    }

    async stop() {
        console.log('⏹️ Stopping FlexPBX User Extension Service...');

        // Clear active registrations
        this.activeRegistrations.clear();

        console.log('✅ FlexPBX User Extension Service stopped');
        this.emit('service_stopped');
    }
}

module.exports = FlexPBXUserExtensionService;