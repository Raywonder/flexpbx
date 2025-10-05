/**
 * 📞 FlexPBX Dial Plan Service
 * Manages call routing with proper separation of local and external calls
 * Implements "9" prefix for external calls with emergency protections
 */

const EventEmitter = require('events');
const GoogleVoiceService = require('./GoogleVoiceService');

class FlexPBXDialPlanService extends EventEmitter {
    constructor() {
        super();

        // Initialize Google Voice service for external calls
        this.googleVoice = new GoogleVoiceService();
        this.googleVoice.initialize();

        // Local Extension Ranges
        this.localExtensions = {
            // Main System Extensions
            operator: 0,                 // Operator extension (when logged in)
            monitoring: 90,              // Call monitoring system
            ivr: 100,                    // Main IVR (internal only)

            // Department Extensions
            sales: { start: 1000, end: 1009 },        // Sales team
            support: { start: 2000, end: 2009 },      // Support team

            // Conference Rooms
            conference: { start: 8000, end: 8009 },   // Conference rooms

            // Preview/Test Extensions
            preview: { start: 9900, end: 9999 },      // Hold music preview, etc.

            // Call Queues
            queues: {
                sales: 1100,
                support: 2100,
                billing: 3100
            }
        };

        // CallCentric Extensions (External)
        this.callcentricExtensions = {
            dominique: {
                internal: 101,              // Local reference number
                external: 'YOUR_CALLCENTRIC_DID101', // Full CallCentric extension
                name: 'Dominique',
                type: 'callcentric'
            },
            flexpbx_operator: {
                internal: 102,              // Routes to logged-in operator
                external: 'YOUR_CALLCENTRIC_DID102',
                name: 'FlexPBX Operator',
                type: 'callcentric',
                routeTo: 'operator'         // Special routing to operator or IVR
            }
        };

        // Operator Management
        this.operatorStatus = {
            isLoggedIn: false,
            currentUser: null,
            loginTime: null,
            extension: null,
            monitoringEnabled: false
        };

        // Call Monitoring System
        this.callMonitoring = {
            enabled: false,
            monitoringExtension: null,
            activeCalls: new Map(),
            recordedCalls: []
        };

        // Emergency Numbers - These require special handling
        this.emergencyNumbers = [
            '911',    // US Emergency
            '999',    // UK Emergency
            '112',    // International Emergency
            '000',    // Australia Emergency
            '511',    // Traffic Information
            '211',    // Community Services
            '311',    // Non-emergency Municipal
            '411',    // Directory Assistance
            '611',    // Repair Service
            '711',    // TDD/TTY Relay
            '811',    // Dig Safe
        ];

        // Outbound Call Routing Rules
        this.dialingRules = {
            // 9 + Number = External Call
            external: {
                prefix: '9',
                requireConfirmation: true,
                emergencyProtection: true,
                allowedExtensions: [], // Can be configured per extension
                blockedNumbers: [],    // Additional blocked numbers
                maxDigits: 15,
                minDigits: 7
            },

            // Direct dial for local extensions
            local: {
                directDial: true,
                range: [100, 9999],
                emergencyBypass: false
            }
        };

        // Per-extension dialing permissions
        this.extensionPermissions = new Map();
        this.setupDefaultPermissions();

        console.log('📞 FlexPBX Dial Plan Service initialized');
        console.log(`   Local Extensions: ${this.getLocalExtensionCount()}`);
        console.log(`   CallCentric Extensions: ${Object.keys(this.callcentricExtensions).length}`);
        console.log(`   Emergency Protection: ✅ ENABLED`);
    }

    setupDefaultPermissions() {
        // Default permissions for all extensions
        const defaultPermissions = {
            canDialExternal: true,
            canDialEmergency: true,
            canDialInternational: false,
            canDialPremium: false,
            requireSupervisorAuth: false,
            maxCallDuration: 3600, // 1 hour
            allowedCountryCodes: ['1'], // US/Canada only by default
            emergencyConfirmationRequired: true
        };

        // Sales team permissions (1000-1009)
        for (let ext = 1000; ext <= 1009; ext++) {
            this.extensionPermissions.set(ext, {
                ...defaultPermissions,
                canDialInternational: true,
                maxCallDuration: 7200 // 2 hours for sales
            });
        }

        // Support team permissions (2000-2009)
        for (let ext = 2000; ext <= 2009; ext++) {
            this.extensionPermissions.set(ext, {
                ...defaultPermissions,
                canDialInternational: false,
                emergencyConfirmationRequired: false // Support can dial emergency without confirmation
            });
        }

        // Test extension (2001) - Special permissions for testing
        this.extensionPermissions.set(2001, {
            ...defaultPermissions,
            canDialInternational: true,
            canDialPremium: true,
            requireSupervisorAuth: false,
            emergencyConfirmationRequired: false,
            allowedCountryCodes: ['1', '44', '49', '33'] // US, UK, Germany, France
        });

        console.log(`✅ Configured permissions for ${this.extensionPermissions.size} extensions`);
    }

    async processDialString(fromExtension, dialedNumber) {
        const result = {
            success: false,
            routingType: null,
            destination: null,
            requiresConfirmation: false,
            emergencyCall: false,
            blocked: false,
            reason: null,
            suggestedAction: null
        };

        try {
            console.log(`📞 Processing dial: ${fromExtension} → ${dialedNumber}`);

            // Step 1: Validate caller extension
            const callerPermissions = this.getExtensionPermissions(fromExtension);
            if (!callerPermissions) {
                result.blocked = true;
                result.reason = 'Extension not found or not authorized';
                return result;
            }

            // Step 2: Analyze dialed number
            const analysis = this.analyzeDialedNumber(dialedNumber);

            // Step 3: Route based on analysis
            switch (analysis.type) {
                case 'local':
                    return await this.routeLocalCall(fromExtension, dialedNumber, analysis);

                case 'callcentric':
                    return await this.routeCallCentricCall(fromExtension, dialedNumber, analysis);

                case 'external':
                    return await this.routeExternalCall(fromExtension, dialedNumber, analysis, callerPermissions);

                case 'emergency':
                    return await this.routeEmergencyCall(fromExtension, dialedNumber, analysis, callerPermissions);

                default:
                    result.blocked = true;
                    result.reason = `Invalid dial pattern: ${dialedNumber}`;
                    result.suggestedAction = 'Check dialing instructions';
                    return result;
            }

        } catch (error) {
            console.error('❌ Dial plan processing error:', error);
            result.blocked = true;
            result.reason = 'System error processing call';
            return result;
        }
    }

    analyzeDialedNumber(dialedNumber) {
        const cleaned = dialedNumber.replace(/[^\d\*\#]/g, '');

        // Emergency numbers
        if (this.emergencyNumbers.includes(cleaned)) {
            return {
                type: 'emergency',
                originalNumber: dialedNumber,
                cleanedNumber: cleaned,
                destination: cleaned,
                priority: 'critical'
            };
        }

        // Local extensions (direct dial)
        const numericValue = parseInt(cleaned);
        if (this.isLocalExtension(numericValue)) {
            return {
                type: 'local',
                originalNumber: dialedNumber,
                cleanedNumber: cleaned,
                destination: numericValue,
                priority: 'normal'
            };
        }

        // CallCentric direct dial (101, 102)
        if (this.isCallCentricExtension(numericValue)) {
            return {
                type: 'callcentric',
                originalNumber: dialedNumber,
                cleanedNumber: cleaned,
                destination: numericValue,
                priority: 'normal'
            };
        }

        // External call (starts with 9)
        if (cleaned.startsWith('9') && cleaned.length > 1) {
            const externalNumber = cleaned.substring(1); // Remove '9' prefix

            return {
                type: 'external',
                originalNumber: dialedNumber,
                cleanedNumber: cleaned,
                destination: externalNumber,
                hasPrefix: true,
                priority: 'normal'
            };
        }

        // Unknown pattern
        return {
            type: 'unknown',
            originalNumber: dialedNumber,
            cleanedNumber: cleaned,
            destination: null,
            priority: 'low'
        };
    }

    async routeLocalCall(fromExtension, dialedNumber, analysis) {
        const result = {
            success: true,
            routingType: 'local',
            destination: analysis.destination,
            requiresConfirmation: false,
            emergencyCall: false,
            blocked: false
        };

        // Check if destination extension exists and is available
        const destinationInfo = this.getExtensionInfo(analysis.destination);
        if (!destinationInfo) {
            result.success = false;
            result.blocked = true;
            result.reason = `Extension ${analysis.destination} does not exist`;
            result.suggestedAction = 'Check extension directory';
            return result;
        }

        console.log(`📞 Local call: ${fromExtension} → ${analysis.destination} (${destinationInfo.name})`);

        result.destinationInfo = destinationInfo;
        result.callType = destinationInfo.type;

        this.emit('localCallRouted', {
            from: fromExtension,
            to: analysis.destination,
            destinationInfo,
            timestamp: new Date()
        });

        return result;
    }

    async routeCallCentricCall(fromExtension, dialedNumber, analysis) {
        const result = {
            success: true,
            routingType: 'callcentric',
            destination: analysis.destination,
            requiresConfirmation: false,
            emergencyCall: false,
            blocked: false
        };

        // Find CallCentric extension info
        const callcentricInfo = Object.values(this.callcentricExtensions)
            .find(ext => ext.internal === analysis.destination);

        if (!callcentricInfo) {
            result.success = false;
            result.blocked = true;
            result.reason = `CallCentric extension ${analysis.destination} not configured`;
            return result;
        }

        // Special handling for extension 102 (FlexPBX Operator)
        if (analysis.destination === 102 && callcentricInfo.routeTo === 'operator') {
            if (this.operatorStatus.isLoggedIn) {
                console.log(`📞 CallCentric → Operator: ${fromExtension} → ${this.operatorStatus.currentUser} (logged in on ext ${this.operatorStatus.extension})`);
                result.routingType = 'operator';
                result.operatorInfo = {
                    user: this.operatorStatus.currentUser,
                    extension: this.operatorStatus.extension,
                    loginTime: this.operatorStatus.loginTime
                };
            } else {
                console.log(`📞 CallCentric → IVR: ${fromExtension} → Main IVR (no operator logged in)`);
                result.routingType = 'ivr_fallback';
                result.destination = 100; // Route to main IVR
                result.reason = 'No operator logged in - routing to IVR';
            }
        }

        console.log(`📞 CallCentric call: ${fromExtension} → ${analysis.destination} (${callcentricInfo.name})`);

        result.destinationInfo = callcentricInfo;
        result.externalNumber = callcentricInfo.external;
        result.sipServer = 'sip.callcentric.com:5060';

        this.emit('callcentricCallRouted', {
            from: fromExtension,
            to: analysis.destination,
            destinationInfo: callcentricInfo,
            operatorStatus: this.operatorStatus,
            timestamp: new Date()
        });

        return result;
    }

    async routeExternalCall(fromExtension, dialedNumber, analysis, permissions) {
        const result = {
            success: false,
            routingType: 'external',
            destination: analysis.destination,
            requiresConfirmation: true,
            emergencyCall: false,
            blocked: false
        };

        // Check if extension can dial external
        if (!permissions.canDialExternal) {
            result.blocked = true;
            result.reason = 'Extension not authorized for external calls';
            result.suggestedAction = 'Contact administrator for external calling permissions';
            return result;
        }

        // Emergency number check in external dial
        if (this.emergencyNumbers.includes(analysis.destination)) {
            return await this.routeEmergencyCall(fromExtension, analysis.destination, analysis, permissions);
        }

        // Validate external number format
        const validation = this.validateExternalNumber(analysis.destination, permissions);
        if (!validation.valid) {
            result.blocked = true;
            result.reason = validation.reason;
            result.suggestedAction = validation.suggestion;
            return result;
        }

        // Check for premium/international numbers
        const costAnalysis = this.analyzeCallCost(analysis.destination);
        if (costAnalysis.isPremium && !permissions.canDialPremium) {
            result.blocked = true;
            result.reason = 'Premium number dialing not authorized';
            result.suggestedAction = 'Contact administrator for premium calling permissions';
            return result;
        }

        if (costAnalysis.isInternational && !permissions.canDialInternational) {
            result.blocked = true;
            result.reason = 'International dialing not authorized';
            result.suggestedAction = 'Contact administrator for international calling permissions';
            return result;
        }

        console.log(`📞 External call: ${fromExtension} → 9${analysis.destination}`);

        // Use Google Voice to make the external call
        try {
            const callResult = await this.googleVoice.makeCall(fromExtension, analysis.destination, {
                costAnalysis,
                emergencyCall: false
            });

            result.success = callResult.success;
            result.callId = callResult.callId;
            result.googleVoiceCall = true;

            if (callResult.success) {
                result.message = `External call placed via Google Voice: ${callResult.message}`;
            } else {
                result.blocked = true;
                result.reason = `Google Voice error: ${callResult.error}`;
            }

        } catch (error) {
            console.error('❌ Google Voice call failed:', error);
            result.blocked = true;
            result.reason = `External calling service unavailable: ${error.message}`;
        }

        result.costAnalysis = costAnalysis;
        result.requiresConfirmation = this.dialingRules.external.requireConfirmation;

        this.emit('externalCallRouted', {
            from: fromExtension,
            to: analysis.destination,
            costAnalysis,
            googleVoiceCall: result.googleVoiceCall,
            timestamp: new Date()
        });

        return result;
    }

    async routeEmergencyCall(fromExtension, dialedNumber, analysis, permissions) {
        const result = {
            success: true,
            routingType: 'emergency',
            destination: analysis.destination,
            requiresConfirmation: permissions.emergencyConfirmationRequired,
            emergencyCall: true,
            blocked: false,
            priority: 'critical'
        };

        console.log(`🚨 EMERGENCY CALL: ${fromExtension} → ${analysis.destination}`);

        // Log emergency call immediately
        this.emit('emergencyCall', {
            from: fromExtension,
            to: analysis.destination,
            timestamp: new Date(),
            requiresConfirmation: result.requiresConfirmation
        });

        // For real emergency calls, use Google Voice with highest priority
        if (!result.requiresConfirmation || permissions.emergencyConfirmationRequired === false) {
            try {
                const emergencyCallResult = await this.googleVoice.makeCall(fromExtension, analysis.destination, {
                    emergencyCall: true,
                    priority: 'critical',
                    duration: 300000 // 5 minutes max for emergency calls
                });

                result.success = emergencyCallResult.success;
                result.callId = emergencyCallResult.callId;
                result.googleVoiceCall = true;

                if (emergencyCallResult.success) {
                    console.log('🚨 EMERGENCY CALL CONNECTED via Google Voice');
                }

            } catch (error) {
                console.error('🚨 EMERGENCY CALL FAILED:', error);
                result.reason = `Emergency calling service error: ${error.message}`;
            }
        }

        // Emergency calls bypass most restrictions
        result.bypassRestrictions = true;
        result.logLevel = 'critical';
        result.warningMessage = 'This is an emergency call. Please confirm if this is a real emergency.';

        return result;
    }

    validateExternalNumber(number, permissions) {
        // Check length
        if (number.length < this.dialingRules.external.minDigits) {
            return {
                valid: false,
                reason: 'Number too short for external dialing',
                suggestion: 'External numbers must be at least 7 digits'
            };
        }

        if (number.length > this.dialingRules.external.maxDigits) {
            return {
                valid: false,
                reason: 'Number too long',
                suggestion: 'Maximum 15 digits allowed'
            };
        }

        // Check country code permissions
        const countryCode = this.extractCountryCode(number);
        if (countryCode && !permissions.allowedCountryCodes.includes(countryCode)) {
            return {
                valid: false,
                reason: `Country code ${countryCode} not authorized`,
                suggestion: 'Contact administrator for international permissions'
            };
        }

        return { valid: true };
    }

    analyzeCallCost(number) {
        const analysis = {
            isPremium: false,
            isInternational: false,
            isTollFree: false,
            estimatedCost: 'standard',
            countryCode: null
        };

        // US/Canada toll-free patterns
        if (/^1?8(00|33|44|55|66|77|88)\d{7}$/.test(number)) {
            analysis.isTollFree = true;
            analysis.estimatedCost = 'free';
            return analysis;
        }

        // Premium rate patterns (US)
        if (/^1?900\d{7}$/.test(number)) {
            analysis.isPremium = true;
            analysis.estimatedCost = 'premium';
            return analysis;
        }

        // International patterns
        if (number.length > 10 && !number.startsWith('1')) {
            analysis.isInternational = true;
            analysis.countryCode = this.extractCountryCode(number);
            analysis.estimatedCost = 'international';
        }

        return analysis;
    }

    extractCountryCode(number) {
        // Simple country code extraction
        if (number.startsWith('1')) return '1';  // US/Canada
        if (number.startsWith('44')) return '44'; // UK
        if (number.startsWith('49')) return '49'; // Germany
        if (number.startsWith('33')) return '33'; // France

        // Extract first 1-3 digits for other countries
        const match = number.match(/^(\d{1,3})/);
        return match ? match[1] : null;
    }

    isLocalExtension(extension) {
        // Check operator extension
        if (extension === this.localExtensions.operator) return true;

        // Check call monitoring extension (90)
        if (extension === this.localExtensions.monitoring) return true;

        // Check main IVR
        if (extension === this.localExtensions.ivr) return true;

        // Department ranges
        for (const [dept, range] of Object.entries(this.localExtensions)) {
            if (range.start && range.end) {
                if (extension >= range.start && extension <= range.end) {
                    return true;
                }
            }
        }

        // Check queue extensions
        if (Object.values(this.localExtensions.queues).includes(extension)) {
            return true;
        }

        return false;
    }

    isCallCentricExtension(extension) {
        return Object.values(this.callcentricExtensions)
            .some(ext => ext.internal === extension);
    }

    getExtensionInfo(extension) {
        // Operator extension
        if (extension === 0) {
            return {
                extension: 0,
                name: this.operatorStatus.isLoggedIn
                    ? `Operator (${this.operatorStatus.currentUser})`
                    : 'Operator (Not Logged In)',
                type: 'operator',
                department: 'system',
                available: this.operatorStatus.isLoggedIn,
                user: this.operatorStatus.currentUser,
                loginTime: this.operatorStatus.loginTime
            };
        }

        // Call monitoring (90)
        if (extension === this.localExtensions.monitoring) {
            return {
                extension: this.localExtensions.monitoring,
                name: 'Call Monitoring System',
                type: 'monitoring',
                department: 'system',
                available: true,
                enabled: this.callMonitoring.enabled
            };
        }

        // Local extensions
        if (extension === 100) {
            return {
                extension: 100,
                name: 'Main IVR',
                type: 'ivr',
                department: 'system',
                available: true
            };
        }

        // Sales team
        if (extension >= 1000 && extension <= 1009) {
            return {
                extension,
                name: `Sales ${extension === 1000 ? 'Manager' : 'Representative'}`,
                type: 'user',
                department: 'sales',
                available: true
            };
        }

        // Support team
        if (extension >= 2000 && extension <= 2009) {
            const names = {
                2000: 'Support Manager',
                2001: 'Test User (Your Extension)',
                2004: 'Accessibility Support Specialist'
            };

            return {
                extension,
                name: names[extension] || 'Technical Support',
                type: 'user',
                department: 'support',
                available: true
            };
        }

        // Conference rooms
        if (extension >= 8000 && extension <= 8009) {
            return {
                extension,
                name: `Conference Room ${extension - 8000 + 1}`,
                type: 'conference',
                department: 'system',
                available: true,
                capacity: extension === 8000 ? 50 : 20
            };
        }

        // Preview extensions
        if (extension >= 9900 && extension <= 9999) {
            const previewTypes = {
                9901: 'Classical Hold Music Preview',
                9902: 'Corporate Hold Music Preview',
                9903: 'Jazz Hold Music Preview',
                9904: 'Ambient Hold Music Preview',
                9905: 'Chris Mix Radio Stream Preview',
                9906: 'Jazz Radio Stream Preview',
                9907: 'Queue Manager Interface',
                9908: 'Call Wrap-up System',
                9909: 'Audio Mixer Control'
            };

            return {
                extension,
                name: previewTypes[extension] || 'Preview Extension',
                type: 'preview',
                department: 'system',
                available: true
            };
        }

        // Call queues
        const queueMap = {
            1100: { name: 'Sales Queue', department: 'sales' },
            2100: { name: 'Support Queue', department: 'support' },
            3100: { name: 'Billing Queue', department: 'billing' }
        };

        if (queueMap[extension]) {
            return {
                extension,
                name: queueMap[extension].name,
                type: 'queue',
                department: queueMap[extension].department,
                available: true
            };
        }

        // CallCentric extensions
        const callcentricExt = Object.values(this.callcentricExtensions)
            .find(ext => ext.internal === extension);

        if (callcentricExt) {
            return {
                extension,
                name: callcentricExt.name,
                type: 'callcentric',
                department: 'external',
                available: true,
                externalNumber: callcentricExt.external
            };
        }

        return null;
    }

    getExtensionPermissions(extension) {
        return this.extensionPermissions.get(extension) || null;
    }

    getLocalExtensionCount() {
        let count = 1; // IVR

        // Count department extensions
        count += (this.localExtensions.sales.end - this.localExtensions.sales.start + 1);
        count += (this.localExtensions.support.end - this.localExtensions.support.start + 1);
        count += (this.localExtensions.conference.end - this.localExtensions.conference.start + 1);
        count += (this.localExtensions.preview.end - this.localExtensions.preview.start + 1);

        // Count queue extensions
        count += Object.keys(this.localExtensions.queues).length;

        return count;
    }

    // Administrative methods
    updateExtensionPermissions(extension, permissions) {
        this.extensionPermissions.set(extension, {
            ...this.extensionPermissions.get(extension),
            ...permissions
        });

        console.log(`✅ Updated permissions for extension ${extension}`);
        this.emit('permissionsUpdated', { extension, permissions });
    }

    blockNumber(extension, number) {
        const permissions = this.getExtensionPermissions(extension);
        if (permissions) {
            if (!permissions.blockedNumbers) {
                permissions.blockedNumbers = [];
            }
            permissions.blockedNumbers.push(number);
            this.updateExtensionPermissions(extension, permissions);
        }
    }

    generateDialingReport(extension) {
        const permissions = this.getExtensionPermissions(extension);
        const extInfo = this.getExtensionInfo(extension);

        return {
            extension,
            name: extInfo?.name || 'Unknown',
            department: extInfo?.department || 'Unknown',
            permissions: permissions || 'No permissions set',
            canDialExternal: permissions?.canDialExternal || false,
            canDialInternational: permissions?.canDialInternational || false,
            canDialEmergency: permissions?.canDialEmergency || false,
            allowedCountries: permissions?.allowedCountryCodes || [],
            emergencyBypass: !permissions?.emergencyConfirmationRequired
        };
    }

    // Google Voice integration methods
    async makeTestCall() {
        console.log('🧪 Making test call to your cell phone via Google Voice...');
        return await this.googleVoice.makeTestCall();
    }

    async sendTestSMS() {
        console.log('🧪 Sending test SMS to your cell phone via Google Voice...');
        return await this.googleVoice.sendTestSMS();
    }

    async getGoogleVoiceStatus() {
        return await this.googleVoice.getStatus();
    }

    async dialNumber(fromExtension, dialedNumber) {
        console.log(`📞 Dialing: ${fromExtension} → ${dialedNumber}`);

        const routingResult = await this.processDialString(fromExtension, dialedNumber);

        if (routingResult.success) {
            console.log(`✅ Call routed successfully: ${routingResult.routingType}`);
            if (routingResult.message) {
                console.log(`   ${routingResult.message}`);
            }
        } else {
            console.log(`❌ Call blocked: ${routingResult.reason}`);
            if (routingResult.suggestedAction) {
                console.log(`   Suggestion: ${routingResult.suggestedAction}`);
            }
        }

        return routingResult;
    }

    // Test scenarios
    async runDialingTests() {
        console.log('🧪 Running comprehensive dialing tests...');
        console.log('=' .repeat(60));

        const tests = [
            // Local extension tests
            { from: 2001, to: '100', description: 'Call Main IVR' },
            { from: 2001, to: '1000', description: 'Call Sales Manager' },
            { from: 2001, to: '2004', description: 'Call Accessibility Support' },
            { from: 2001, to: '8000', description: 'Join Main Conference' },
            { from: 2001, to: '9901', description: 'Preview Classical Hold Music' },

            // CallCentric tests
            { from: 2001, to: '101', description: 'Call Dominique (CallCentric)' },
            { from: 2001, to: '102', description: 'Call FlexPBX Test (CallCentric)' },

            // External call tests (with 9 prefix)
            { from: 2001, to: '93364626141', description: 'Call your cell phone' },
            { from: 2001, to: '912813015784', description: 'Call your Google Voice number' },
            { from: 2001, to: '918005551234', description: 'Call toll-free number' },

            // Invalid/blocked tests
            { from: 2001, to: '9911', description: 'Emergency through external prefix (should warn)' },
            { from: 2001, to: '9900123456789', description: 'Premium number (may be blocked)' },
            { from: 2001, to: '999', description: 'Invalid extension' },
        ];

        for (const test of tests) {
            console.log(`\n📞 Test: ${test.description}`);
            console.log(`   Dialing: ${test.from} → ${test.to}`);

            try {
                const result = await this.dialNumber(test.from, test.to);
                console.log(`   Result: ${result.success ? '✅ SUCCESS' : '❌ BLOCKED'}`);
                if (result.routingType) {
                    console.log(`   Route: ${result.routingType.toUpperCase()}`);
                }
            } catch (error) {
                console.log(`   Error: ${error.message}`);
            }

            // Wait between tests
            await new Promise(resolve => setTimeout(resolve, 1000));
        }

        console.log('\n✅ Dialing tests completed!');
    }

    // Operator Management Functions
    async loginOperator(username, extension) {
        if (this.operatorStatus.isLoggedIn) {
            return {
                success: false,
                reason: `Operator ${this.operatorStatus.currentUser} already logged in`,
                suggestion: 'Log out current operator first'
            };
        }

        // Validate extension exists and is available
        const extInfo = this.getExtensionInfo(extension);
        if (!extInfo) {
            return {
                success: false,
                reason: `Extension ${extension} not found`,
                suggestion: 'Use a valid extension number'
            };
        }

        this.operatorStatus = {
            isLoggedIn: true,
            currentUser: username,
            loginTime: new Date(),
            extension: extension,
            monitoringEnabled: false
        };

        console.log(`👤 Operator Login: ${username} logged in on extension ${extension}`);

        this.emit('operatorLogin', {
            user: username,
            extension,
            timestamp: this.operatorStatus.loginTime
        });

        return {
            success: true,
            message: `Operator ${username} logged in on extension ${extension}`,
            operatorStatus: this.operatorStatus
        };
    }

    async logoutOperator() {
        if (!this.operatorStatus.isLoggedIn) {
            return {
                success: false,
                reason: 'No operator currently logged in',
                suggestion: 'Login first before attempting to logout'
            };
        }

        const loggedOutUser = this.operatorStatus.currentUser;
        const sessionDuration = new Date() - this.operatorStatus.loginTime;

        console.log(`👤 Operator Logout: ${loggedOutUser} (session: ${Math.round(sessionDuration / 60000)} minutes)`);

        // Disable monitoring if it was enabled
        if (this.callMonitoring.enabled) {
            await this.disableCallMonitoring();
        }

        this.emit('operatorLogout', {
            user: loggedOutUser,
            sessionDuration,
            timestamp: new Date()
        });

        this.operatorStatus = {
            isLoggedIn: false,
            currentUser: null,
            loginTime: null,
            extension: null,
            monitoringEnabled: false
        };

        return {
            success: true,
            message: `Operator ${loggedOutUser} logged out`,
            sessionDuration: Math.round(sessionDuration / 60000)
        };
    }

    // Call Monitoring Functions
    async enableCallMonitoring(monitoringExtension = null) {
        if (!this.operatorStatus.isLoggedIn) {
            return {
                success: false,
                reason: 'Operator must be logged in to enable call monitoring',
                suggestion: 'Login as operator first'
            };
        }

        this.callMonitoring.enabled = true;
        this.callMonitoring.monitoringExtension = monitoringExtension || this.operatorStatus.extension;
        this.operatorStatus.monitoringEnabled = true;

        console.log(`🔍 Call Monitoring ENABLED by ${this.operatorStatus.currentUser}`);
        console.log(`   Monitoring from extension: ${this.callMonitoring.monitoringExtension}`);

        this.emit('callMonitoringEnabled', {
            operator: this.operatorStatus.currentUser,
            monitoringExtension: this.callMonitoring.monitoringExtension,
            timestamp: new Date()
        });

        return {
            success: true,
            message: 'Call monitoring enabled',
            monitoringExtension: this.callMonitoring.monitoringExtension,
            operator: this.operatorStatus.currentUser
        };
    }

    async disableCallMonitoring() {
        if (!this.callMonitoring.enabled) {
            return {
                success: false,
                reason: 'Call monitoring is not currently enabled',
                suggestion: 'Enable monitoring first'
            };
        }

        const previousOperator = this.operatorStatus.currentUser;

        this.callMonitoring.enabled = false;
        this.callMonitoring.monitoringExtension = null;
        this.operatorStatus.monitoringEnabled = false;

        console.log(`🔍 Call Monitoring DISABLED by ${previousOperator}`);

        this.emit('callMonitoringDisabled', {
            operator: previousOperator,
            timestamp: new Date()
        });

        return {
            success: true,
            message: 'Call monitoring disabled',
            disabledBy: previousOperator
        };
    }

    async monitorCall(callId) {
        if (!this.callMonitoring.enabled) {
            return {
                success: false,
                reason: 'Call monitoring is not enabled',
                suggestion: 'Enable monitoring first by dialing 90'
            };
        }

        console.log(`🔍 Monitoring call: ${callId}`);

        // Add call to monitoring list
        const monitoringData = {
            callId,
            startTime: new Date(),
            operator: this.operatorStatus.currentUser,
            monitoringExtension: this.callMonitoring.monitoringExtension
        };

        this.callMonitoring.activeCalls.set(callId, monitoringData);

        this.emit('callMonitoringStarted', monitoringData);

        return {
            success: true,
            message: `Monitoring call ${callId}`,
            monitoringData
        };
    }

    // Status and Information Functions
    getOperatorStatus() {
        return {
            ...this.operatorStatus,
            callMonitoring: {
                enabled: this.callMonitoring.enabled,
                monitoringExtension: this.callMonitoring.monitoringExtension,
                activeCalls: this.callMonitoring.activeCalls.size,
                totalRecorded: this.callMonitoring.recordedCalls.length
            }
        };
    }

    getSystemStatus() {
        return {
            operator: this.getOperatorStatus(),
            localExtensions: this.getLocalExtensionCount(),
            callcentricExtensions: Object.keys(this.callcentricExtensions).length,
            googleVoice: this.googleVoice ? 'Available' : 'Not Available',
            emergencyProtection: true,
            dialingRules: {
                externalPrefix: this.dialingRules.external.prefix,
                emergencyProtection: this.dialingRules.external.emergencyProtection
            }
        };
    }

    // Test operator functionality
    async testOperatorSystem() {
        console.log('🧪 Testing Operator System...');
        console.log('=' .repeat(50));

        // Test 1: Operator login
        console.log('\n👤 Test 1: Operator Login');
        const loginResult = await this.loginOperator('TestOperator', 2001);
        console.log(`   Result: ${loginResult.success ? '✅ SUCCESS' : '❌ FAILED'}`);
        if (loginResult.message) console.log(`   Message: ${loginResult.message}`);

        // Test 2: Enable call monitoring
        console.log('\n🔍 Test 2: Enable Call Monitoring');
        const monitoringResult = await this.enableCallMonitoring();
        console.log(`   Result: ${monitoringResult.success ? '✅ SUCCESS' : '❌ FAILED'}`);
        if (monitoringResult.message) console.log(`   Message: ${monitoringResult.message}`);

        // Test 3: Test CallCentric 102 routing
        console.log('\n📞 Test 3: CallCentric 102 Routing (with operator logged in)');
        const callResult = await this.dialNumber('external', '102');
        console.log(`   Result: ${callResult.success ? '✅ SUCCESS' : '❌ FAILED'}`);
        console.log(`   Route: ${callResult.routingType?.toUpperCase()}`);

        // Test 4: Test operator extension 0
        console.log('\n📞 Test 4: Operator Extension 0');
        const operatorResult = await this.dialNumber(2001, '0');
        console.log(`   Result: ${operatorResult.success ? '✅ SUCCESS' : '❌ FAILED'}`);
        console.log(`   Route: ${operatorResult.routingType?.toUpperCase()}`);

        // Test 5: Test call monitoring access (90)
        console.log('\n🔍 Test 5: Call Monitoring Access (90)');
        const monitorAccessResult = await this.dialNumber(2001, '90');
        console.log(`   Result: ${monitorAccessResult.success ? '✅ SUCCESS' : '❌ FAILED'}`);
        console.log(`   Route: ${monitorAccessResult.routingType?.toUpperCase()}`);

        // Show system status
        console.log('\n📊 System Status:');
        const status = this.getSystemStatus();
        console.log(`   Operator: ${status.operator.isLoggedIn ? status.operator.currentUser : 'Not logged in'}`);
        console.log(`   Monitoring: ${status.operator.callMonitoring.enabled ? 'ENABLED' : 'DISABLED'}`);
        console.log(`   Extensions: ${status.localExtensions} local, ${status.callcentricExtensions} CallCentric`);

        // Test cleanup
        console.log('\n🧹 Test Cleanup');
        const logoutResult = await this.logoutOperator();
        console.log(`   Logout: ${logoutResult.success ? '✅ SUCCESS' : '❌ FAILED'}`);

        console.log('\n✅ Operator system tests completed!');
    }
}

module.exports = FlexPBXDialPlanService;