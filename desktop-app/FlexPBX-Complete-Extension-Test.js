#!/usr/bin/env node

/**
 * 🏢 FlexPBX Complete Extension System
 * Creates 20 user extensions + main IVR system for comprehensive testing
 */

const FlexPBXUserExtensionService = require('./src/main/services/FlexPBXUserExtensionService');
const { exec } = require('child_process');
const fs = require('fs-extra');
const path = require('path');

class FlexPBXCompleteExtensionSystem {
    constructor() {
        this.extensionService = new FlexPBXUserExtensionService();
        this.mainExtension = '101'; // Main number that routes to IVR
        this.testExtension = '2001'; // Your test extension
        this.extensions = new Map();
        this.ivrSystem = null;
        this.dialPlan = new Map();

        this.setupCompleteExtensionSystem();
        this.setupMainIVRSystem();
        this.setupDialPlan();
    }

    setupCompleteExtensionSystem() {
        console.log('🏢 Creating Complete FlexPBX Extension System...');
        console.log('📞 Generating 20 user extensions + IVR system');

        // Main IVR Extension (101)
        this.extensions.set('101', {
            extension: '101',
            type: 'ivr',
            name: 'Main IVR System',
            description: 'Primary incoming call handler with full IVR menu',
            features: ['ivr', 'hold_music', 'voicemail', 'call_routing', 'queue_management']
        });

        // Your Test Extension (2001)
        this.extensions.set('2001', {
            extension: '2001',
            username: 'testuser',
            password: 'FlexPBX2001!',
            displayName: 'FlexPBX Test User (YOUR EXTENSION)',
            email: 'testuser@flexpbx.local',
            department: 'testing',
            type: 'user',
            sipServer: 'flexpbx.local:5070',
            permissions: {
                makeInternalCalls: true,
                makeExternalCalls: true,
                accessIVR: true,
                recordCalls: true,
                transferCalls: true,
                conference: true,
                monitoring: true
            }
        });

        // Sales Department (1000-1009)
        const salesTeam = [
            { name: 'Sales Manager', username: 'salesmanager', title: 'Manager' },
            { name: 'Senior Sales Rep', username: 'salesrep1', title: 'Senior Rep' },
            { name: 'Sales Rep 2', username: 'salesrep2', title: 'Sales Rep' },
            { name: 'Sales Rep 3', username: 'salesrep3', title: 'Sales Rep' },
            { name: 'Inside Sales', username: 'insidesales', title: 'Inside Sales' },
            { name: 'Outside Sales', username: 'outsidesales', title: 'Outside Sales' },
            { name: 'Sales Support', username: 'salessupport', title: 'Support' },
            { name: 'Lead Qualifier', username: 'leadqualifier', title: 'Qualifier' },
            { name: 'Account Executive', username: 'accountexec', title: 'Account Exec' },
            { name: 'Sales Assistant', username: 'salesassist', title: 'Assistant' }
        ];

        salesTeam.forEach((member, index) => {
            const ext = `100${index}`;
            this.extensions.set(ext, {
                extension: ext,
                username: member.username,
                password: `Sales${ext}!`,
                displayName: member.name,
                email: `${member.username}@flexpbx.local`,
                department: 'sales',
                title: member.title,
                type: 'user',
                sipServer: 'flexpbx.local:5070',
                callGroup: 'sales',
                pickupGroup: 'sales'
            });
        });

        // Support Department (2000-2009)
        const supportTeam = [
            { name: 'Support Manager', username: 'supportmanager', title: 'Manager' },
            { name: 'Senior Tech Support', username: 'techsupport1', title: 'Senior Tech' },
            { name: 'Tech Support L2', username: 'techsupport2', title: 'Tech L2' },
            { name: 'Tech Support L1', username: 'techsupport3', title: 'Tech L1' },
            { name: 'Accessibility Support', username: 'accessibility', title: 'Accessibility' },
            { name: 'Network Support', username: 'networksupport', title: 'Network' },
            { name: 'Software Support', username: 'softwaresupport', title: 'Software' },
            { name: 'Hardware Support', username: 'hardwaresupport', title: 'Hardware' },
            { name: 'Customer Success', username: 'customersuccess', title: 'Success' },
            { name: 'Support Assistant', username: 'supportassist', title: 'Assistant' }
        ];

        supportTeam.forEach((member, index) => {
            const ext = `200${index}`;
            this.extensions.set(ext, {
                extension: ext,
                username: member.username,
                password: `Support${ext}!`,
                displayName: member.name,
                email: `${member.username}@flexpbx.local`,
                department: 'support',
                title: member.title,
                type: 'user',
                sipServer: 'flexpbx.local:5070',
                callGroup: 'support',
                pickupGroup: 'support'
            });
        });

        // Conference Rooms (8000-8009)
        const conferenceRooms = [
            { name: 'Main Conference', capacity: 50, features: ['recording', 'transcription'] },
            { name: 'Sales Conference', capacity: 20, features: ['recording'] },
            { name: 'Support Conference', capacity: 15, features: ['recording', 'screen_share'] },
            { name: 'Executive Conference', capacity: 10, features: ['recording', 'encryption'] },
            { name: 'Training Room', capacity: 30, features: ['recording', 'breakout_rooms'] },
            { name: 'Customer Demo', capacity: 25, features: ['recording', 'screen_share'] },
            { name: 'All Hands', capacity: 100, features: ['recording', 'transcription', 'broadcast'] },
            { name: 'Board Room', capacity: 8, features: ['recording', 'encryption', 'privacy'] },
            { name: 'Interview Room', capacity: 5, features: ['recording'] },
            { name: 'Quick Sync', capacity: 10, features: [] }
        ];

        conferenceRooms.forEach((room, index) => {
            const ext = `800${index}`;
            this.extensions.set(ext, {
                extension: ext,
                name: room.name,
                type: 'conference',
                capacity: room.capacity,
                features: room.features,
                moderationRequired: room.features.includes('encryption'),
                recordingEnabled: room.features.includes('recording')
            });
        });

        console.log(`✅ Created ${this.extensions.size} extensions:`);
        console.log(`   📞 Main IVR: 101`);
        console.log(`   👤 Your Test Ext: 2001`);
        console.log(`   💼 Sales Team: 1000-1009`);
        console.log(`   🛠️ Support Team: 2000-2009`);
        console.log(`   🏢 Conference Rooms: 8000-8009`);
    }

    setupMainIVRSystem() {
        console.log('🤖 Setting up Main IVR System for Extension 101...');

        this.ivrSystem = {
            mainMenu: {
                extension: '101',
                name: 'FlexPBX Main IVR',
                greeting: 'welcome_main',
                menu: 'main_menu',
                options: {
                    '1': {
                        action: 'queue',
                        target: 'sales_queue',
                        announcement: 'Connecting you to our sales team...',
                        music: 'corporate_hold',
                        timeout: 300,
                        agents: ['1000', '1001', '1002', '1003', '1004']
                    },
                    '2': {
                        action: 'queue',
                        target: 'support_queue',
                        announcement: 'Connecting you to technical support...',
                        music: 'ambient_hold',
                        timeout: 600,
                        agents: ['2000', '2001', '2002', '2003', '2004']
                    },
                    '3': {
                        action: 'submenu',
                        target: 'billing_menu',
                        announcement: 'Accessing billing menu...'
                    },
                    '4': {
                        action: 'extension',
                        target: '2001',
                        announcement: 'Connecting to test extension...'
                    },
                    '5': {
                        action: 'submenu',
                        target: 'conference_menu',
                        announcement: 'Conference room directory...'
                    },
                    '7': {
                        action: 'submenu',
                        target: 'accessibility_menu',
                        announcement: 'Accessibility support menu...'
                    },
                    '8': {
                        action: 'directory',
                        target: 'company_directory',
                        announcement: 'Company directory...'
                    },
                    '9': {
                        action: 'repeat',
                        announcement: 'Repeating menu options...'
                    },
                    '0': {
                        action: 'operator',
                        target: '2001',
                        announcement: 'Connecting to operator...'
                    },
                    '*': {
                        action: 'voicemail',
                        target: 'general_voicemail',
                        announcement: 'Leaving general voicemail...'
                    },
                    '#': {
                        action: 'callback',
                        announcement: 'Requesting callback...'
                    }
                },
                timeout: 10,
                retries: 3,
                invalid_retry: 'invalid_selection'
            },

            subMenus: {
                billing_menu: {
                    greeting: 'billing_greeting',
                    options: {
                        '1': { action: 'extension', target: '1005', name: 'Account Billing' },
                        '2': { action: 'extension', target: '1006', name: 'Payment Processing' },
                        '3': { action: 'automated', target: 'balance_inquiry', name: 'Balance Inquiry' },
                        '9': { action: 'return', target: 'main_menu' }
                    }
                },

                conference_menu: {
                    greeting: 'conference_greeting',
                    options: {
                        '1': { action: 'conference', target: '8000', name: 'Main Conference' },
                        '2': { action: 'conference', target: '8001', name: 'Sales Conference' },
                        '3': { action: 'conference', target: '8002', name: 'Support Conference' },
                        '4': { action: 'input', target: 'conference_code', name: 'Enter Conference Code' },
                        '9': { action: 'return', target: 'main_menu' }
                    }
                },

                accessibility_menu: {
                    greeting: 'accessibility_greeting',
                    options: {
                        '1': { action: 'extension', target: '2004', name: 'Accessibility Specialist' },
                        '2': { action: 'feature', target: 'screen_reader_mode', name: 'Screen Reader Mode' },
                        '3': { action: 'feature', target: 'hearing_assistance', name: 'Hearing Assistance' },
                        '4': { action: 'feature', target: 'voice_navigation', name: 'Voice Navigation' },
                        '9': { action: 'return', target: 'main_menu' }
                    }
                }
            },

            queues: {
                sales_queue: {
                    name: 'Sales Queue',
                    strategy: 'round_robin',
                    agents: ['1000', '1001', '1002', '1003', '1004'],
                    maxWait: 300,
                    announcements: ['queue_position', 'estimated_wait'],
                    music: 'corporate_hold',
                    callbackOption: true
                },

                support_queue: {
                    name: 'Support Queue',
                    strategy: 'longest_idle',
                    agents: ['2000', '2001', '2002', '2003', '2004'],
                    maxWait: 600,
                    announcements: ['queue_position', 'estimated_wait', 'callback_offer'],
                    music: 'ambient_hold',
                    callbackOption: true,
                    priority: {
                        'accessibility': 'high',
                        'emergency': 'urgent'
                    }
                }
            }
        };

        console.log('✅ Main IVR System configured with full menu tree');
    }

    setupDialPlan() {
        console.log('📋 Setting up Dial Plan...');

        // Main IVR handling
        this.dialPlan.set('101', {
            action: 'ivr',
            target: 'main_menu',
            description: 'Main IVR System - Routes to departments and services'
        });

        // User extensions
        this.dialPlan.set('2001', {
            action: 'extension',
            target: '2001',
            description: 'Test User Extension (YOUR EXTENSION)'
        });

        // Sales team routing
        for (let i = 0; i < 10; i++) {
            const ext = `100${i}`;
            this.dialPlan.set(ext, {
                action: 'extension',
                target: ext,
                description: `Sales Team Member ${i + 1}`
            });
        }

        // Support team routing
        for (let i = 0; i < 10; i++) {
            const ext = `200${i}`;
            this.dialPlan.set(ext, {
                action: 'extension',
                target: ext,
                description: `Support Team Member ${i + 1}`
            });
        }

        // Conference rooms
        for (let i = 0; i < 10; i++) {
            const ext = `800${i}`;
            this.dialPlan.set(ext, {
                action: 'conference',
                target: ext,
                description: `Conference Room ${i + 1}`
            });
        }

        // Special codes
        this.dialPlan.set('*97', {
            action: 'voicemail',
            target: 'personal',
            description: 'Personal Voicemail Access'
        });

        this.dialPlan.set('9196', {
            action: 'echo_test',
            description: 'Echo Test Service'
        });

        this.dialPlan.set('*60', {
            action: 'time_date',
            description: 'Time and Date Service'
        });

        console.log(`✅ Dial plan configured with ${this.dialPlan.size} routes`);
    }

    async generateClientConfigurations() {
        console.log('📝 Generating SIP client configurations for all extensions...');

        const configDir = path.join(process.cwd(), 'sip-client-configs');
        await fs.ensureDir(configDir);

        // Generate config for your test extension (2001)
        const testExtConfig = this.generateTestUserConfig();
        await fs.writeFile(
            path.join(configDir, 'FlexPBX-Test-Extension-2001.txt'),
            testExtConfig
        );

        // Generate configs for all user extensions
        for (const [extNum, ext] of this.extensions) {
            if (ext.type === 'user' && ext.username) {
                const config = this.generateUserSipConfig(ext);
                await fs.writeFile(
                    path.join(configDir, `FlexPBX-Extension-${extNum}-${ext.username}.txt`),
                    config
                );
            }
        }

        console.log('✅ SIP client configurations generated in ./sip-client-configs/');
    }

    generateTestUserConfig() {
        const ext = this.extensions.get('2001');
        return `# 🎯 FlexPBX Test Extension 2001 - YOUR EXTENSION
# ================================================
# Use this configuration in your SIP client to test FlexPBX

[Account Information]
Display Name: ${ext.displayName}
Username: ${ext.username}
Password: ${ext.password}
Extension: 2001

[Server Settings]
SIP Server: flexpbx.local
Port: 5070
Domain: flexpbx.local
Transport: UDP (Primary), TCP (Secondary)
Registration: Required

[Complete SIP URI]
sip:${ext.username}@flexpbx.local:5070

[Audio Settings]
Preferred Codecs: G.722 (HD), PCMU, PCMA
DTMF Method: RFC2833
Echo Cancellation: Enabled
Noise Suppression: Enabled

[Testing Scenarios]
1. Call 101 → Main IVR System
   - Test full IVR menu navigation
   - Try all menu options (1-9, *, 0, #)
   - Test hold music and announcements

2. Call Sales Team: 1000-1009
   - Test direct extension dialing
   - Try call transfer between sales reps

3. Call Support Team: 2000-2009
   - Test queue system
   - Try accessibility support (2004)

4. Conference Rooms: 8000-8009
   - Join conference rooms
   - Test conference features

5. Special Codes:
   - *97: Voicemail access
   - 9196: Echo test
   - *60: Time/date service

[IVR Testing Guide]
When you call 101:
- Press 1: Sales queue with corporate hold music
- Press 2: Support queue with ambient hold music
- Press 3: Billing submenu
- Press 4: Direct to your extension (2001)
- Press 5: Conference room directory
- Press 7: Accessibility support menu
- Press 8: Company directory
- Press 9: Repeat menu
- Press 0: Operator (routes to 2001)
- Press *: General voicemail
- Press #: Request callback

[Expected Features]
✅ Full duplex audio (both directions)
✅ HD audio quality (G.722 codec)
✅ DTMF tone transmission
✅ Call hold with music
✅ Call transfer capabilities
✅ Conference room access
✅ Voicemail system
✅ Queue position announcements
✅ Professional hold music
✅ Accessibility features
`;
    }

    generateUserSipConfig(ext) {
        return `# FlexPBX Extension ${ext.extension} - ${ext.displayName}
# Department: ${ext.department || 'General'}

[Account Settings]
Display Name: ${ext.displayName}
Username: ${ext.username}
Password: ${ext.password}
Extension: ${ext.extension}

[Server Settings]
SIP Server: flexpbx.local:5070
Domain: flexpbx.local
Transport: UDP

[Features Available]
- Internal calling: All extensions
- External calling: ${ext.permissions?.makeExternalCalls ? 'Yes' : 'No'}
- Call recording: ${ext.permissions?.recordCalls ? 'Yes' : 'No'}
- Call transfer: ${ext.permissions?.transferCalls ? 'Yes' : 'No'}
- Conference: ${ext.permissions?.conference ? 'Yes' : 'No'}
- Voicemail: ${ext.permissions?.accessVoicemail ? 'Yes' : 'No'}

[Department Contacts]
${ext.department === 'sales' ? 'Sales Team: 1000-1009' : ''}
${ext.department === 'support' ? 'Support Team: 2000-2009' : ''}

[Quick Dial Codes]
- 101: Main IVR
- *97: Personal voicemail
- 9196: Echo test
- *60: Time/date
`;
    }

    async createWebInterface() {
        console.log('🌐 Creating comprehensive web interface...');

        const webInterface = `
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FlexPBX Complete Extension System</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #667eea, #764ba2);
            margin: 0;
            padding: 20px;
            color: #333;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
        }

        .header h1 {
            color: #2c3e50;
            font-size: 2.5em;
            margin-bottom: 10px;
        }

        .extension-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }

        .extension-card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            border-left: 4px solid #007bff;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .extension-card.test {
            border-left-color: #28a745;
            background: #e8f5e8;
        }

        .extension-card.ivr {
            border-left-color: #dc3545;
            background: #fce8e6;
        }

        .extension-card.sales {
            border-left-color: #ffc107;
            background: #fff9e6;
        }

        .extension-card.support {
            border-left-color: #17a2b8;
            background: #e6f9fc;
        }

        .extension-card.conference {
            border-left-color: #6f42c1;
            background: #f0e6fc;
        }

        .ext-number {
            font-size: 1.5em;
            font-weight: bold;
            color: #2c3e50;
        }

        .ext-name {
            font-size: 1.1em;
            margin: 5px 0;
            color: #495057;
        }

        .ext-details {
            font-size: 0.9em;
            color: #6c757d;
            margin: 10px 0;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9em;
            margin: 2px;
            transition: all 0.2s ease;
        }

        .btn-primary { background: #007bff; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-info { background: #17a2b8; color: white; }

        .btn:hover {
            transform: translateY(-1px);
        }

        .ivr-flow {
            background: #2d3748;
            color: #68d391;
            padding: 20px;
            border-radius: 12px;
            font-family: 'Monaco', 'Courier New', monospace;
            margin: 30px 0;
        }

        .test-instructions {
            background: #e3f2fd;
            border: 2px solid #2196f3;
            border-radius: 12px;
            padding: 25px;
            margin: 30px 0;
        }

        .department-section {
            margin: 40px 0;
        }

        .department-title {
            font-size: 1.5em;
            font-weight: bold;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #dee2e6;
        }

        @media (max-width: 768px) {
            .extension-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📞 FlexPBX Complete Extension System</h1>
            <p>20 Extensions + Main IVR System Ready for Testing</p>
        </div>

        <div class="test-instructions">
            <h2>🎯 Your Test Extension: 2001</h2>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 20px 0;">
                <div>
                    <h4>SIP Client Settings:</h4>
                    <ul>
                        <li><strong>Server:</strong> flexpbx.local:5070</li>
                        <li><strong>Username:</strong> testuser</li>
                        <li><strong>Password:</strong> FlexPBX2001!</li>
                        <li><strong>Extension:</strong> 2001</li>
                    </ul>
                </div>
                <div>
                    <h4>Test Scenarios:</h4>
                    <ul>
                        <li>Call <strong>101</strong> → Main IVR System</li>
                        <li>Try all IVR options (1-9, *, 0, #)</li>
                        <li>Test hold music and queues</li>
                        <li>Call direct extensions</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="department-section">
            <div class="department-title">🎯 Main IVR System (Extension 101)</div>
            <div class="extension-card ivr">
                <div class="ext-number">101</div>
                <div class="ext-name">Main IVR System</div>
                <div class="ext-details">
                    <strong>Call Flow:</strong> Routes to departments with hold music and voicemail<br>
                    <strong>Features:</strong> Full menu tree, queue management, callback options
                </div>
                <div class="ivr-flow">
Main IVR Menu (Dial 101):
├── Press 1: Sales Queue (Corporate Hold Music)
├── Press 2: Support Queue (Ambient Hold Music)
├── Press 3: Billing Submenu
├── Press 4: Test Extension (2001)
├── Press 5: Conference Directory
├── Press 7: Accessibility Support
├── Press 8: Company Directory
├── Press 9: Repeat Menu
├── Press 0: Operator (2001)
├── Press *: General Voicemail
└── Press #: Request Callback
                </div>
                <button class="btn btn-primary" onclick="testIVR()">Test IVR System</button>
                <button class="btn btn-success" onclick="dialExtension('101')">Call 101</button>
            </div>
        </div>

        <div class="department-section">
            <div class="department-title">👤 Your Test Extension</div>
            <div class="extension-card test">
                <div class="ext-number">2001</div>
                <div class="ext-name">FlexPBX Test User (YOUR EXTENSION)</div>
                <div class="ext-details">
                    <strong>Username:</strong> testuser<br>
                    <strong>Password:</strong> FlexPBX2001!<br>
                    <strong>SIP Server:</strong> flexpbx.local:5070<br>
                    <strong>Features:</strong> Full access, recording, transfer, conference
                </div>
                <button class="btn btn-success" onclick="downloadConfig('2001')">Download Config</button>
                <button class="btn btn-info" onclick="testExtension('2001')">Test Features</button>
            </div>
        </div>

        <div class="department-section">
            <div class="department-title">💼 Sales Department (1000-1009)</div>
            <div class="extension-grid" id="sales-extensions">
                <!-- Sales extensions populated by JavaScript -->
            </div>
        </div>

        <div class="department-section">
            <div class="department-title">🛠️ Support Department (2000-2009)</div>
            <div class="extension-grid" id="support-extensions">
                <!-- Support extensions populated by JavaScript -->
            </div>
        </div>

        <div class="department-section">
            <div class="department-title">🏢 Conference Rooms (8000-8009)</div>
            <div class="extension-grid" id="conference-rooms">
                <!-- Conference rooms populated by JavaScript -->
            </div>
        </div>
    </div>

    <script>
        // Extension data
        const extensions = ${JSON.stringify(Array.from(this.extensions.entries()), null, 2)};

        function populateExtensions() {
            const salesContainer = document.getElementById('sales-extensions');
            const supportContainer = document.getElementById('support-extensions');
            const conferenceContainer = document.getElementById('conference-rooms');

            extensions.forEach(([extNum, ext]) => {
                if (ext.department === 'sales') {
                    salesContainer.appendChild(createExtensionCard(extNum, ext, 'sales'));
                } else if (ext.department === 'support') {
                    supportContainer.appendChild(createExtensionCard(extNum, ext, 'support'));
                } else if (ext.type === 'conference') {
                    conferenceContainer.appendChild(createConferenceCard(extNum, ext));
                }
            });
        }

        function createExtensionCard(extNum, ext, type) {
            const card = document.createElement('div');
            card.className = \`extension-card \${type}\`;

            card.innerHTML = \`
                <div class="ext-number">\${extNum}</div>
                <div class="ext-name">\${ext.displayName}</div>
                <div class="ext-details">
                    <strong>Username:</strong> \${ext.username}<br>
                    <strong>Title:</strong> \${ext.title}<br>
                    <strong>Email:</strong> \${ext.email}
                </div>
                <button class="btn btn-primary" onclick="dialExtension('\${extNum}')">Call</button>
                <button class="btn btn-info" onclick="downloadConfig('\${extNum}')">Config</button>
                <button class="btn btn-warning" onclick="testFeatures('\${extNum}')">Test</button>
            \`;

            return card;
        }

        function createConferenceCard(extNum, conf) {
            const card = document.createElement('div');
            card.className = 'extension-card conference';

            card.innerHTML = \`
                <div class="ext-number">\${extNum}</div>
                <div class="ext-name">\${conf.name}</div>
                <div class="ext-details">
                    <strong>Capacity:</strong> \${conf.capacity} participants<br>
                    <strong>Features:</strong> \${conf.features.join(', ')}
                </div>
                <button class="btn btn-primary" onclick="joinConference('\${extNum}')">Join</button>
                <button class="btn btn-success" onclick="testConference('\${extNum}')">Test</button>
            \`;

            return card;
        }

        // Interactive functions
        function testIVR() {
            console.log('Testing IVR system...');
            speak('Testing FlexPBX IVR system. Dial 101 to access the main menu.');
        }

        function dialExtension(ext) {
            console.log(\`Dialing extension \${ext}\`);
            speak(\`Dialing extension \${ext}\`);
        }

        function downloadConfig(ext) {
            console.log(\`Downloading config for extension \${ext}\`);
            speak(\`Downloading SIP configuration for extension \${ext}\`);
        }

        function testExtension(ext) {
            console.log(\`Testing extension \${ext}\`);
            speak(\`Testing all features for extension \${ext}\`);
        }

        function joinConference(room) {
            console.log(\`Joining conference room \${room}\`);
            speak(\`Joining conference room \${room}\`);
        }

        function speak(text) {
            if ('speechSynthesis' in window) {
                const utterance = new SpeechSynthesisUtterance(text);
                utterance.rate = 1.1;
                speechSynthesis.speak(utterance);
            }
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            populateExtensions();
            speak('FlexPBX complete extension system loaded. 20 extensions plus main IVR ready for testing.');
        });
    </script>
</body>
</html>
        `;

        await fs.writeFile(
            path.join(process.cwd(), 'FlexPBX-Complete-Extension-System.html'),
            webInterface
        );

        console.log('✅ Web interface created: FlexPBX-Complete-Extension-System.html');
    }

    async runCompleteTest() {
        console.log('🚀 Starting FlexPBX Complete Extension System Test');
        console.log('=' .repeat(70));

        await this.announceSystem();
        await this.generateClientConfigurations();
        await this.createWebInterface();
        await this.displaySystemOverview();
        await this.announceTestInstructions();
    }

    async announceSystem() {
        const announcement = `
FlexPBX Complete Extension System ready.
Created 20 user extensions plus main IVR system.
Extension 2001 is your test extension.
Dial 101 for main IVR with full menu and hold music.
All systems ready for comprehensive testing.
        `.trim();

        console.log('🎙️ System Announcement:');
        console.log(announcement);

        try {
            await this.execAsync(`say "${announcement}"`);
        } catch (error) {
            console.log('💬 Voice announcement not available');
        }
    }

    displaySystemOverview() {
        console.log('\n📊 FLEXPBX SYSTEM OVERVIEW');
        console.log('=' .repeat(70));

        console.log('\n🎯 YOUR TEST EXTENSION:');
        console.log('   Extension: 2001');
        console.log('   Username: testuser');
        console.log('   Password: FlexPBX2001!');
        console.log('   SIP Server: flexpbx.local:5070');

        console.log('\n📞 MAIN IVR SYSTEM (Extension 101):');
        console.log('   ├── Press 1: Sales Queue (Corporate Hold Music)');
        console.log('   ├── Press 2: Support Queue (Ambient Hold Music)');
        console.log('   ├── Press 3: Billing Submenu');
        console.log('   ├── Press 4: Your Test Extension (2001)');
        console.log('   ├── Press 5: Conference Directory');
        console.log('   ├── Press 7: Accessibility Support');
        console.log('   ├── Press 8: Company Directory');
        console.log('   ├── Press 9: Repeat Menu');
        console.log('   ├── Press 0: Operator (routes to 2001)');
        console.log('   ├── Press *: General Voicemail');
        console.log('   └── Press #: Request Callback');

        console.log('\n👥 DEPARTMENTS:');
        console.log('   💼 Sales Team: Extensions 1000-1009');
        console.log('   🛠️ Support Team: Extensions 2000-2009');
        console.log('   🏢 Conference Rooms: Extensions 8000-8009');

        console.log('\n🎵 FEATURES TO TEST:');
        console.log('   ✅ Full IVR navigation with hold music');
        console.log('   ✅ Queue systems with position announcements');
        console.log('   ✅ Extension-to-extension calling');
        console.log('   ✅ Call transfer and conference');
        console.log('   ✅ Voicemail system');
        console.log('   ✅ DTMF tone transmission');
        console.log('   ✅ Professional hold music');
        console.log('   ✅ Accessibility features');
    }

    async announceTestInstructions() {
        console.log('\n🧪 TEST INSTRUCTIONS:');
        console.log('=' .repeat(50));
        console.log('1. Configure your SIP client with Extension 2001 credentials');
        console.log('2. Call 101 to test the main IVR system');
        console.log('3. Try all IVR menu options (1-9, *, 0, #)');
        console.log('4. Test hold music and queue announcements');
        console.log('5. Call direct extensions to test internal calling');
        console.log('6. Test conference rooms (8000-8009)');
        console.log('7. Try voicemail and special services');

        const finalAnnouncement = 'FlexPBX complete extension system ready for testing. Your extension is 2001. Dial 101 for main IVR with full professional features.';

        try {
            await this.execAsync(`say "${finalAnnouncement}"`);
        } catch (error) {
            console.log('✅ System ready for testing!');
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

// Run the complete system setup
if (require.main === module) {
    const extensionSystem = new FlexPBXCompleteExtensionSystem();
    extensionSystem.runCompleteTest().then(() => {
        console.log('\n🎉 FlexPBX Complete Extension System ready!');
        console.log('📄 Check the generated files:');
        console.log('   - FlexPBX-Complete-Extension-System.html');
        console.log('   - ./sip-client-configs/ directory');
        console.log('\n🚀 Ready for comprehensive testing!');
        process.exit(0);
    }).catch(error => {
        console.error('\n💥 System setup failed:', error);
        process.exit(1);
    });
}

module.exports = FlexPBXCompleteExtensionSystem;