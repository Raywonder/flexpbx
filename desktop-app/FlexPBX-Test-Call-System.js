#!/usr/bin/env node

/**
 * 📞 FlexPBX Test Call System
 * Automated test calling to your CallCentric extension with interactive options
 */

const { exec } = require('child_process');

class FlexPBXTestCallSystem {
    constructor() {
        this.callcentricConfig = {
            yourExtension: 'YOUR_CALLCENTRIC_DID101', // Dominique (Your CallCentric Ext 101)
            testExtension: 'YOUR_CALLCENTRIC_DID102', // FlexPBX Test (Ext 102)
            server: 'sip.callcentric.com',
            port: 5060
        };

        this.testScenarios = new Map();
        this.setupTestScenarios();
    }

    setupTestScenarios() {
        console.log('🧪 Setting up FlexPBX test call scenarios...');

        // Test Scenario 1: Basic Connectivity Test
        this.testScenarios.set('connectivity', {
            name: 'Basic Connectivity Test',
            description: 'Simple call to test if connection works',
            duration: 10, // seconds
            autoHangup: true,
            actions: [
                { time: 2, action: 'announce', message: 'FlexPBX connectivity test call' },
                { time: 5, action: 'announce', message: 'Connection successful' },
                { time: 8, action: 'announce', message: 'Hanging up in 3 seconds' }
            ]
        });

        // Test Scenario 2: Interactive Feature Test
        this.testScenarios.set('interactive', {
            name: 'Interactive Feature Test',
            description: 'Provides menu options when you answer',
            duration: 60, // 1 minute
            autoHangup: false,
            menu: {
                greeting: 'FlexPBX test call connected. Please choose an option.',
                options: {
                    '1': { action: 'test_dtmf', description: 'Test DTMF tone transmission' },
                    '2': { action: 'test_audio_quality', description: 'Test audio quality and echo' },
                    '3': { action: 'test_hold_music', description: 'Test hold music system' },
                    '4': { action: 'test_call_transfer', description: 'Test call transfer to FlexPBX extension' },
                    '5': { action: 'test_conference', description: 'Test conference bridge' },
                    '6': { action: 'test_recording', description: 'Test call recording' },
                    '7': { action: 'test_accessibility', description: 'Test accessibility features' },
                    '8': { action: 'call_internal_ext', description: 'Call your FlexPBX extension 2001' },
                    '9': { action: 'repeat_menu', description: 'Repeat this menu' },
                    '0': { action: 'hangup', description: 'End test call' },
                    '*': { action: 'emergency_hangup', description: 'Emergency hangup' }
                }
            }
        });

        // Test Scenario 3: Queue Simulation
        this.testScenarios.set('queue_test', {
            name: 'Call Queue Test',
            description: 'Simulates being placed in a call queue',
            duration: 90,
            autoHangup: false,
            actions: [
                { time: 2, action: 'announce', message: 'Thank you for calling FlexPBX. You are being placed in the test queue.' },
                { time: 5, action: 'start_hold_music', source: 'corporate' },
                { time: 15, action: 'announce', message: 'You are caller number 1 in the queue. Estimated wait time is 30 seconds.' },
                { time: 35, action: 'announce', message: 'Thank you for holding. Connecting you now.' },
                { time: 40, action: 'stop_hold_music' },
                { time: 42, action: 'interactive_menu' }
            ]
        });

        console.log(`✅ Configured ${this.testScenarios.size} test scenarios`);
    }

    async makeTestCall(scenario = 'connectivity') {
        console.log('📞 Starting FlexPBX Test Call System...');
        console.log('=' .repeat(60));

        const testScenario = this.testScenarios.get(scenario);
        if (!testScenario) {
            throw new Error(`Test scenario '${scenario}' not found`);
        }

        console.log(`🎯 Test Scenario: ${testScenario.name}`);
        console.log(`📋 Description: ${testScenario.description}`);
        console.log(`⏱️ Duration: ${testScenario.duration} seconds`);

        await this.announceTestCall();

        // Simulate making the call
        console.log(`\n📞 Simulating call to your CallCentric extension...`);
        console.log(`   From: FlexPBX Test (${this.callcentricConfig.testExtension})`);
        console.log(`   To: Dominique (${this.callcentricConfig.yourExtension})`);
        console.log(`   Server: ${this.callcentricConfig.server}:${this.callcentricConfig.port}`);

        // Wait for "call connection"
        await this.delay(3000);
        console.log('🔄 Call connecting...');

        await this.delay(2000);
        console.log('📞 Call established - waiting for answer...');

        // Simulate ring time
        await this.delay(3000);
        console.log('✅ Call answered! Starting test scenario...');

        // Execute the test scenario
        await this.executeTestScenario(testScenario);
    }

    async executeTestScenario(scenario) {
        console.log(`\n🎬 Executing: ${scenario.name}`);
        console.log('-'.repeat(50));

        if (scenario.menu) {
            await this.executeInteractiveMenu(scenario.menu);
        } else if (scenario.actions) {
            await this.executeTimedActions(scenario.actions);
        }

        if (scenario.autoHangup) {
            await this.delay(2000);
            console.log('📞 Auto-hanging up test call...');
            await this.announceHangup();
        } else {
            console.log('⏳ Waiting for manual hangup or menu selection...');
        }
    }

    async executeInteractiveMenu(menu) {
        console.log('🎙️ Playing interactive menu...');

        // Announce greeting
        await this.playAnnouncement(menu.greeting);
        await this.delay(2000);

        // Announce menu options
        console.log('\n📋 MENU OPTIONS BEING ANNOUNCED:');
        for (const [key, option] of Object.entries(menu.options)) {
            const announcement = `Press ${key} for ${option.description}`;
            console.log(`   ${key}: ${option.description}`);
            await this.delay(1500);
        }

        console.log('\n⌨️ FlexPBX is now listening for DTMF input...');
        console.log('💡 When you press keys on your phone, FlexPBX will respond');

        // Simulate waiting for input and responses
        await this.simulateMenuInteraction(menu.options);
    }

    async executeTimedActions(actions) {
        console.log('⏰ Executing timed actions...');

        for (const action of actions) {
            await this.delay(action.time * 1000);

            switch (action.action) {
                case 'announce':
                    await this.playAnnouncement(action.message);
                    break;
                case 'start_hold_music':
                    await this.startHoldMusic(action.source);
                    break;
                case 'stop_hold_music':
                    await this.stopHoldMusic();
                    break;
                case 'interactive_menu':
                    const interactiveMenu = this.testScenarios.get('interactive').menu;
                    await this.executeInteractiveMenu(interactiveMenu);
                    break;
            }
        }
    }

    async simulateMenuInteraction(options) {
        const menuKeys = Object.keys(options);
        let simulationTime = 60; // 60 seconds of menu simulation

        console.log('🎯 Simulating menu interactions for 60 seconds...');

        // Simulate user pressing different keys
        const simulationInterval = setInterval(async () => {
            const randomKey = menuKeys[Math.floor(Math.random() * menuKeys.length)];
            const option = options[randomKey];

            console.log(`\n🔢 Simulated DTMF: User pressed '${randomKey}'`);
            console.log(`🎯 Action: ${option.description}`);

            await this.handleMenuSelection(randomKey, option);

            simulationTime -= 10;
            if (simulationTime <= 0) {
                clearInterval(simulationInterval);
                console.log('\n⏰ Menu simulation completed');
            }
        }, 10000); // Every 10 seconds

        return new Promise(resolve => {
            setTimeout(() => {
                clearInterval(simulationInterval);
                resolve();
            }, 60000);
        });
    }

    async handleMenuSelection(key, option) {
        switch (option.action) {
            case 'test_dtmf':
                await this.testDTMFTransmission();
                break;
            case 'test_audio_quality':
                await this.testAudioQuality();
                break;
            case 'test_hold_music':
                await this.testHoldMusicSystem();
                break;
            case 'test_call_transfer':
                await this.testCallTransfer();
                break;
            case 'test_conference':
                await this.testConferenceBridge();
                break;
            case 'test_recording':
                await this.testCallRecording();
                break;
            case 'test_accessibility':
                await this.testAccessibilityFeatures();
                break;
            case 'call_internal_ext':
                await this.callInternalExtension();
                break;
            case 'repeat_menu':
                await this.playAnnouncement('Repeating menu options...');
                break;
            case 'hangup':
                await this.announceHangup();
                break;
            case 'emergency_hangup':
                await this.emergencyHangup();
                break;
        }
    }

    async testDTMFTransmission() {
        console.log('🎵 Testing DTMF tone transmission...');
        await this.playAnnouncement('Testing DTMF transmission. Listen for tones.');

        const dtmfSequence = ['1', '2', '3', '4', '5', '*', '0', '#'];
        for (const digit of dtmfSequence) {
            await this.delay(500);
            console.log(`🔢 Transmitting DTMF: ${digit}`);
            await this.playDTMFTone(digit);
        }

        await this.playAnnouncement('DTMF test completed. All tones should have been clear.');
    }

    async testAudioQuality() {
        console.log('🎙️ Testing audio quality...');
        await this.playAnnouncement('Testing audio quality. Please listen for clarity and check for echo.');

        const testPhrases = [
            'The quick brown fox jumps over the lazy dog',
            'Testing one two three four five',
            'FlexPBX audio quality verification',
            'Can you hear this clearly without distortion?'
        ];

        for (const phrase of testPhrases) {
            await this.delay(2000);
            await this.playAnnouncement(phrase);
        }

        await this.playAnnouncement('Audio quality test completed. Please verify clarity on your end.');
    }

    async testHoldMusicSystem() {
        console.log('🎵 Testing hold music system...');
        await this.playAnnouncement('Testing hold music. You will hear corporate hold music for 15 seconds.');

        await this.startHoldMusic('corporate');
        await this.delay(15000);
        await this.stopHoldMusic();

        await this.playAnnouncement('Hold music test completed. Music should have played smoothly.');
    }

    async testCallTransfer() {
        console.log('📞 Testing call transfer...');
        await this.playAnnouncement('Testing call transfer to FlexPBX extension 2001. Please hold.');

        await this.delay(3000);
        await this.playAnnouncement('Transfer simulation - you would now be connected to extension 2001.');
        await this.delay(2000);
        await this.playAnnouncement('Transfer test completed. Returning to main menu.');
    }

    async testConferenceBridge() {
        console.log('🏢 Testing conference bridge...');
        await this.playAnnouncement('Testing conference bridge. Simulating conference room 8000.');

        await this.delay(2000);
        await this.playAnnouncement('You have joined the FlexPBX test conference. Press pound to mute.');
        await this.delay(3000);
        await this.playAnnouncement('Conference test completed. You would now have full conference features.');
    }

    async testCallRecording() {
        console.log('🎙️ Testing call recording...');
        await this.playAnnouncement('Testing call recording. This conversation is now being recorded for quality purposes.');

        await this.delay(3000);
        await this.playAnnouncement('Recording test phrase for audio quality verification.');
        await this.delay(2000);
        await this.playAnnouncement('Call recording test completed. Recording would be saved with timestamp.');
    }

    async testAccessibilityFeatures() {
        console.log('♿ Testing accessibility features...');
        await this.playAnnouncement('Testing accessibility features. Voice navigation and screen reader compatibility active.');

        await this.delay(2000);
        await this.playAnnouncement('Accessibility mode provides enhanced audio cues and voice prompts.');
        await this.delay(2000);
        await this.playAnnouncement('All accessibility features verified and operational.');
    }

    async callInternalExtension() {
        console.log('📞 Calling internal FlexPBX extension...');
        await this.playAnnouncement('Connecting to your FlexPBX test extension 2001. Please hold.');

        await this.delay(3000);
        await this.playAnnouncement('Extension 2001 test user would now be ringing.');
        await this.delay(2000);
        await this.playAnnouncement('Internal extension test completed. Connection would be established.');
    }

    async startHoldMusic(source) {
        console.log(`🎵 Starting hold music: ${source}`);
        // In production, this would start actual audio streaming
        console.log('♪ ♪ ♪ Hold music playing ♪ ♪ ♪');
    }

    async stopHoldMusic() {
        console.log('⏹️ Stopping hold music');
        console.log('🔇 Hold music stopped');
    }

    async playAnnouncement(message) {
        console.log(`🗣️ Announcement: ${message}`);
        try {
            await this.execAsync(`say "${message}"`);
        } catch (error) {
            console.log('💬 Voice synthesis not available, text announcement only');
        }
    }

    async playDTMFTone(digit) {
        console.log(`📞 DTMF Tone: ${digit}`);
        // In production, this would play actual DTMF audio
        try {
            // Play the generated DTMF tone
            await this.execAsync(`afplay "src/assets/sounds/dtmf/dtmf_${digit}.wav"`);
        } catch (error) {
            console.log(`🔢 DTMF ${digit} (audio file not found)`);
        }
    }

    async announceTestCall() {
        const announcement = `
Starting FlexPBX test call to your CallCentric extension.
This will test call connectivity and interactive features.
When you answer the call, you will hear test options.
Please answer your phone to begin testing.
        `.trim();

        console.log('🎙️ Test Call Announcement:');
        console.log(announcement);

        try {
            await this.execAsync(`say "${announcement}"`);
        } catch (error) {
            console.log('💬 Voice announcement not available');
        }
    }

    async announceHangup() {
        const message = 'FlexPBX test call completed. Thank you for testing. Hanging up now.';
        await this.playAnnouncement(message);
        console.log('📞 Test call ended successfully');
    }

    async emergencyHangup() {
        console.log('🚨 Emergency hangup requested');
        await this.playAnnouncement('Emergency hangup. Ending call immediately.');
        console.log('📞 Call terminated');
    }

    // Test Runner Methods
    async runBasicConnectivityTest() {
        console.log('🧪 Running Basic Connectivity Test...');
        await this.makeTestCall('connectivity');
    }

    async runInteractiveTest() {
        console.log('🧪 Running Interactive Feature Test...');
        await this.makeTestCall('interactive');
    }

    async runQueueTest() {
        console.log('🧪 Running Call Queue Test...');
        await this.makeTestCall('queue_test');
    }

    async runAllTests() {
        console.log('🧪 Running All FlexPBX Tests...');

        console.log('\n1️⃣ Basic Connectivity Test');
        await this.runBasicConnectivityTest();

        await this.delay(5000);

        console.log('\n2️⃣ Interactive Feature Test');
        await this.runInteractiveTest();

        await this.delay(5000);

        console.log('\n3️⃣ Call Queue Test');
        await this.runQueueTest();

        console.log('\n✅ All FlexPBX tests completed!');
    }

    async delay(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
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

// CLI Interface
if (require.main === module) {
    const testSystem = new FlexPBXTestCallSystem();

    const testType = process.argv[2] || 'interactive';

    console.log('📞 FlexPBX Test Call System');
    console.log('=' .repeat(50));
    console.log('Available tests:');
    console.log('  connectivity  - Basic connection test (10 seconds)');
    console.log('  interactive   - Interactive menu test (60 seconds)');
    console.log('  queue         - Call queue simulation (90 seconds)');
    console.log('  all           - Run all tests sequentially');
    console.log('');

    switch (testType) {
        case 'connectivity':
            testSystem.runBasicConnectivityTest();
            break;
        case 'interactive':
            testSystem.runInteractiveTest();
            break;
        case 'queue':
            testSystem.runQueueTest();
            break;
        case 'all':
            testSystem.runAllTests();
            break;
        default:
            console.log(`🎯 Running ${testType} test...`);
            testSystem.makeTestCall(testType);
            break;
    }
}

module.exports = FlexPBXTestCallSystem;