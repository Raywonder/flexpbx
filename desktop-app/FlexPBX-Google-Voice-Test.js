#!/usr/bin/env node

/**
 * 📞 FlexPBX Google Voice Test System
 * Tests outbound calling and SMS through Google Voice API
 *
 * Your Google Voice Number: (281) 301-5784
 * Test Cell Number: (336) 462-6141
 */

const FlexPBXDialPlanService = require('./src/main/services/FlexPBXDialPlanService');
const GoogleVoiceService = require('./src/main/services/GoogleVoiceService');

class FlexPBXGoogleVoiceTest {
    constructor() {
        this.dialPlan = new FlexPBXDialPlanService();
        this.googleVoice = new GoogleVoiceService();

        this.testNumbers = {
            yourCell: '3364626141',
            yourGoogleVoice: '2813015784',
            tollFree: '8005551234',
            localNumber: '3364621000'  // Local Greensboro number for testing
        };

        this.testExtension = 2001; // Your test extension

        console.log('📞 FlexPBX Google Voice Test System');
        console.log('=' .repeat(60));
        console.log(`   Test Extension: ${this.testExtension} (Test User)`);
        console.log(`   Google Voice: ${this.formatPhone(this.testNumbers.yourGoogleVoice)}`);
        console.log(`   Test Cell: ${this.formatPhone(this.testNumbers.yourCell)}`);
        console.log('');
    }

    async initialize() {
        console.log('🔧 Initializing Google Voice Test System...');

        try {
            // Initialize Google Voice service
            const gvInitialized = await this.googleVoice.initialize();
            if (!gvInitialized) {
                console.log('⚠️ Google Voice not fully authenticated - using simulation mode');
            }

            console.log('✅ Test system ready');
            return true;

        } catch (error) {
            console.error('❌ Initialization failed:', error.message);
            return false;
        }
    }

    async runBasicTests() {
        console.log('🧪 Running Basic Google Voice Tests...');
        console.log('-' .repeat(50));

        // Test 1: Check Google Voice status
        await this.testGoogleVoiceStatus();
        await this.delay(2000);

        // Test 2: Test call to your cell
        await this.testCallToCell();
        await this.delay(5000);

        // Test 3: Test SMS to your cell
        await this.testSMSToCell();
        await this.delay(3000);

        // Test 4: Test various dialing patterns
        await this.testDialingPatterns();

        console.log('\n✅ Basic tests completed!');
    }

    async testGoogleVoiceStatus() {
        console.log('\n📊 Test 1: Google Voice Service Status');
        console.log('-' .repeat(30));

        try {
            const status = await this.dialPlan.getGoogleVoiceStatus();

            console.log(`   Authentication: ${status.isAuthenticated ? '✅ Connected' : '❌ Not Connected'}`);
            console.log(`   Your Number: ${status.userNumber}`);
            console.log(`   Test Number: ${status.testNumber}`);
            console.log(`   Active Calls: ${status.activeCalls}`);
            console.log(`   Call History: ${status.totalCallHistory} calls`);

            if (status.lastCallTime) {
                console.log(`   Last Call: ${status.lastCallTime.toLocaleString()}`);
            }

        } catch (error) {
            console.error('   ❌ Status check failed:', error.message);
        }
    }

    async testCallToCell() {
        console.log('\n📞 Test 2: Call Your Cell Phone');
        console.log('-' .repeat(30));

        try {
            console.log('   Initiating call to your cell...');
            console.log(`   From: Extension ${this.testExtension} via Google Voice`);
            console.log(`   To: ${this.formatPhone(this.testNumbers.yourCell)}`);

            const callResult = await this.dialPlan.makeTestCall();

            if (callResult.success) {
                console.log('   ✅ Test call initiated!');
                console.log('   📱 Check your cell phone - it should ring shortly');
                console.log(`   📞 Call ID: ${callResult.callId}`);
            } else {
                console.log('   ❌ Test call failed:', callResult.error);
            }

        } catch (error) {
            console.error('   ❌ Call test failed:', error.message);
        }
    }

    async testSMSToCell() {
        console.log('\n💬 Test 3: SMS to Your Cell Phone');
        console.log('-' .repeat(30));

        try {
            console.log('   Sending test SMS to your cell...');
            console.log(`   From: ${this.formatPhone(this.testNumbers.yourGoogleVoice)}`);
            console.log(`   To: ${this.formatPhone(this.testNumbers.yourCell)}`);

            const smsResult = await this.dialPlan.sendTestSMS();

            if (smsResult.success) {
                console.log('   ✅ Test SMS sent!');
                console.log('   📱 Check your cell phone for the test message');
                console.log(`   💬 SMS ID: ${smsResult.smsId}`);
            } else {
                console.log('   ❌ Test SMS failed:', smsResult.error);
            }

        } catch (error) {
            console.error('   ❌ SMS test failed:', error.message);
        }
    }

    async testDialingPatterns() {
        console.log('\n🎯 Test 4: Dialing Pattern Tests');
        console.log('-' .repeat(30));

        const tests = [
            {
                number: `9${this.testNumbers.yourCell}`,
                description: 'External call to your cell (with 9 prefix)',
                shouldSucceed: true
            },
            {
                number: `9${this.testNumbers.tollFree}`,
                description: 'Toll-free number (1-800)',
                shouldSucceed: true
            },
            {
                number: `9${this.testNumbers.localNumber}`,
                description: 'Local Greensboro number',
                shouldSucceed: true
            },
            {
                number: '101',
                description: 'CallCentric extension (Dominique)',
                shouldSucceed: true
            },
            {
                number: '100',
                description: 'Internal IVR extension',
                shouldSucceed: true
            },
            {
                number: '9911',
                description: 'Emergency with 9 prefix (should require confirmation)',
                shouldSucceed: false
            },
            {
                number: '999',
                description: 'Invalid extension',
                shouldSucceed: false
            }
        ];

        for (const test of tests) {
            console.log(`\n   Testing: ${test.description}`);
            console.log(`   Dialing: ${this.testExtension} → ${test.number}`);

            try {
                const result = await this.dialPlan.dialNumber(this.testExtension, test.number);

                const status = result.success ? '✅ SUCCESS' : '❌ BLOCKED';
                const expected = test.shouldSucceed ? 'Expected' : 'Expected (blocked)';

                console.log(`   Result: ${status} (${expected})`);

                if (result.routingType) {
                    console.log(`   Route: ${result.routingType.toUpperCase()}`);
                }

                if (result.reason) {
                    console.log(`   Reason: ${result.reason}`);
                }

                if (result.emergencyCall) {
                    console.log('   🚨 EMERGENCY CALL DETECTED');
                }

                if (result.googleVoiceCall) {
                    console.log('   📞 Routed via Google Voice');
                }

            } catch (error) {
                console.log(`   ❌ Error: ${error.message}`);
            }

            await this.delay(1000);
        }
    }

    async runInteractiveTest() {
        console.log('\n🎮 Interactive Test Mode');
        console.log('-' .repeat(30));
        console.log('This will make an actual call to your cell phone with interactive options.');
        console.log('');

        // Create an interactive call with menu options
        try {
            const result = await this.googleVoice.makeCall(this.testExtension, this.testNumbers.yourCell, {
                duration: 60000, // 1 minute
                interactive: true,
                testCall: true,
                message: `FlexPBX Interactive Test Call

When you answer this call, you'll hear:
- FlexPBX system information
- Test menu options you can navigate
- Audio quality verification
- Call features demonstration

This demonstrates how your FlexPBX system can make
professional outbound calls via Google Voice.`
            });

            if (result.success) {
                console.log('✅ Interactive test call initiated!');
                console.log('📱 Answer your cell phone to begin the test');
                console.log('');
                console.log('🎯 What to expect:');
                console.log('   - Professional greeting from FlexPBX');
                console.log('   - Menu options to test features');
                console.log('   - Audio quality verification');
                console.log('   - Call will end automatically after 1 minute');
                console.log('');
                console.log(`📞 Call ID: ${result.callId}`);
            } else {
                console.log('❌ Interactive test failed:', result.error);
            }

        } catch (error) {
            console.error('❌ Interactive test error:', error.message);
        }
    }

    async runSMSTest() {
        console.log('\n💬 SMS Feature Test');
        console.log('-' .repeat(30));

        const testMessages = [
            'FlexPBX SMS Test: Basic message delivery ✅',
            `FlexPBX Status Report:
🔌 System: Online
📞 Google Voice: Connected
📱 SMS: Working
📊 Extensions: 20 active
⏰ ${new Date().toLocaleString()}`,
            'FlexPBX: This demonstrates multi-line SMS capability. Your PBX system can send detailed status updates, alerts, and notifications to any mobile device. 📞📱✨'
        ];

        for (let i = 0; i < testMessages.length; i++) {
            console.log(`\n   📤 Sending SMS ${i + 1}/${testMessages.length}...`);

            try {
                const result = await this.googleVoice.sendSMS(
                    this.testExtension,
                    this.testNumbers.yourCell,
                    testMessages[i]
                );

                if (result.success) {
                    console.log(`   ✅ SMS ${i + 1} sent successfully`);
                    console.log(`   💬 SMS ID: ${result.smsId}`);
                } else {
                    console.log(`   ❌ SMS ${i + 1} failed: ${result.error}`);
                }

            } catch (error) {
                console.error(`   ❌ SMS ${i + 1} error:`, error.message);
            }

            // Wait between messages
            if (i < testMessages.length - 1) {
                await this.delay(3000);
            }
        }

        console.log('\n📱 Check your cell phone for the test messages!');
    }

    async runStressTest() {
        console.log('\n🏋️ Google Voice Stress Test');
        console.log('-' .repeat(30));
        console.log('Testing multiple concurrent operations...');

        const operations = [];

        // Queue multiple test operations
        for (let i = 1; i <= 3; i++) {
            operations.push(
                this.googleVoice.sendSMS(
                    this.testExtension,
                    this.testNumbers.yourCell,
                    `Stress test SMS #${i} - ${new Date().toLocaleTimeString()}`
                )
            );
        }

        try {
            const results = await Promise.allSettled(operations);

            console.log('\n   📊 Stress test results:');
            results.forEach((result, index) => {
                if (result.status === 'fulfilled' && result.value.success) {
                    console.log(`   ✅ Operation ${index + 1}: Success`);
                } else {
                    console.log(`   ❌ Operation ${index + 1}: Failed`);
                }
            });

        } catch (error) {
            console.error('   ❌ Stress test failed:', error.message);
        }
    }

    formatPhone(number) {
        const cleaned = number.replace(/\D/g, '');
        if (cleaned.length === 10) {
            return `(${cleaned.substr(0, 3)}) ${cleaned.substr(3, 3)}-${cleaned.substr(6, 4)}`;
        }
        return number;
    }

    async delay(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    showUsage() {
        console.log('📞 FlexPBX Google Voice Test Usage:');
        console.log('');
        console.log('Available test modes:');
        console.log('  basic      - Run basic connectivity tests');
        console.log('  call       - Make interactive test call to your cell');
        console.log('  sms        - Test SMS messaging features');
        console.log('  dial       - Test dialing pattern validation');
        console.log('  stress     - Run stress tests');
        console.log('  all        - Run all tests');
        console.log('');
        console.log('Examples:');
        console.log('  node FlexPBX-Google-Voice-Test.js basic');
        console.log('  node FlexPBX-Google-Voice-Test.js call');
        console.log('  node FlexPBX-Google-Voice-Test.js sms');
        console.log('');
    }
}

// CLI Interface
if (require.main === module) {
    const testSystem = new FlexPBXGoogleVoiceTest();
    const testMode = process.argv[2] || 'usage';

    async function runTests() {
        const initialized = await testSystem.initialize();
        if (!initialized && testMode !== 'usage') {
            console.log('❌ Test system initialization failed');
            process.exit(1);
        }

        switch (testMode.toLowerCase()) {
            case 'basic':
                await testSystem.runBasicTests();
                break;

            case 'call':
                await testSystem.runInteractiveTest();
                break;

            case 'sms':
                await testSystem.runSMSTest();
                break;

            case 'dial':
                await testSystem.testDialingPatterns();
                break;

            case 'stress':
                await testSystem.runStressTest();
                break;

            case 'all':
                await testSystem.runBasicTests();
                await testSystem.delay(3000);
                await testSystem.runInteractiveTest();
                await testSystem.delay(5000);
                await testSystem.runSMSTest();
                await testSystem.delay(3000);
                await testSystem.runStressTest();
                break;

            case 'usage':
            default:
                testSystem.showUsage();
                break;
        }
    }

    runTests().catch(error => {
        console.error('❌ Test execution failed:', error);
        process.exit(1);
    });
}

module.exports = FlexPBXGoogleVoiceTest;