#!/usr/bin/env node

/**
 * 📞 FlexPBX Operator System Test
 * Tests operator login, call monitoring, and CallCentric routing
 *
 * Features:
 * - Extension 0: Operator extension (when logged in)
 * - Extension 00: Call monitoring system
 * - Extension 102: CallCentric routes to operator or IVR
 */

const FlexPBXDialPlanService = require('./src/main/services/FlexPBXDialPlanService');

class FlexPBXOperatorTest {
    constructor() {
        this.dialPlan = new FlexPBXDialPlanService();

        console.log('👤 FlexPBX Operator System Test');
        console.log('=' .repeat(60));
        console.log('   Extension 0: Operator (when logged in)');
        console.log('   Extension 90: Call Monitoring System');
        console.log('   Extension 102: CallCentric → Operator/IVR');
        console.log('');
    }

    async runOperatorTests() {
        console.log('🧪 Running Operator System Tests...');
        console.log('-' .repeat(50));

        // Test 1: System status before login
        await this.testSystemStatusBeforeLogin();
        await this.delay(2000);

        // Test 2: Operator login
        await this.testOperatorLogin();
        await this.delay(2000);

        // Test 3: Call monitoring activation
        await this.testCallMonitoringActivation();
        await this.delay(2000);

        // Test 4: Extension routing tests
        await this.testExtensionRouting();
        await this.delay(2000);

        // Test 5: CallCentric 102 routing with operator logged in
        await this.testCallCentric102Routing();
        await this.delay(2000);

        // Test 6: Operator logout and IVR fallback
        await this.testOperatorLogoutAndFallback();

        console.log('\n✅ Operator system tests completed!');
    }

    async testSystemStatusBeforeLogin() {
        console.log('\n📊 Test 1: System Status Before Login');
        console.log('-' .repeat(30));

        const status = this.dialPlan.getSystemStatus();

        console.log(`   Operator Status: ${status.operator.isLoggedIn ? 'LOGGED IN' : 'NOT LOGGED IN'}`);
        console.log(`   Call Monitoring: ${status.operator.callMonitoring.enabled ? 'ENABLED' : 'DISABLED'}`);
        console.log(`   Local Extensions: ${status.localExtensions}`);
        console.log(`   CallCentric Extensions: ${status.callcentricExtensions}`);
        console.log(`   Google Voice: ${status.googleVoice}`);
        console.log(`   Emergency Protection: ${status.emergencyProtection ? 'ENABLED' : 'DISABLED'}`);

        // Test dialing 102 when no operator is logged in
        console.log('\n   Testing CallCentric 102 (no operator logged in):');
        const result = await this.dialPlan.dialNumber('external', '102');
        console.log(`   Result: ${result.success ? '✅ SUCCESS' : '❌ FAILED'}`);
        console.log(`   Route: ${result.routingType?.toUpperCase()}`);
        if (result.reason) {
            console.log(`   Reason: ${result.reason}`);
        }
    }

    async testOperatorLogin() {
        console.log('\n👤 Test 2: Operator Login');
        console.log('-' .repeat(30));

        // Test login
        console.log('   Attempting to login operator...');
        const loginResult = await this.dialPlan.loginOperator('Dominique', 2001);

        console.log(`   Login Result: ${loginResult.success ? '✅ SUCCESS' : '❌ FAILED'}`);
        if (loginResult.message) {
            console.log(`   Message: ${loginResult.message}`);
        }

        if (loginResult.success) {
            const operator = loginResult.operatorStatus;
            console.log(`   User: ${operator.currentUser}`);
            console.log(`   Extension: ${operator.extension}`);
            console.log(`   Login Time: ${operator.loginTime.toLocaleTimeString()}`);
        }

        // Test calling operator extension 0
        console.log('\n   Testing operator extension 0:');
        const operatorCallResult = await this.dialPlan.dialNumber(1000, '0');
        console.log(`   Result: ${operatorCallResult.success ? '✅ SUCCESS' : '❌ FAILED'}`);
        console.log(`   Route: ${operatorCallResult.routingType?.toUpperCase()}`);

        const extInfo = this.dialPlan.getExtensionInfo(0);
        console.log(`   Extension Info: ${extInfo.name}`);
        console.log(`   Available: ${extInfo.available ? 'YES' : 'NO'}`);
    }

    async testCallMonitoringActivation() {
        console.log('\n🔍 Test 3: Call Monitoring Activation');
        console.log('-' .repeat(30));

        // Enable call monitoring
        console.log('   Enabling call monitoring...');
        const monitoringResult = await this.dialPlan.enableCallMonitoring();

        console.log(`   Result: ${monitoringResult.success ? '✅ SUCCESS' : '❌ FAILED'}`);
        if (monitoringResult.message) {
            console.log(`   Message: ${monitoringResult.message}`);
        }

        if (monitoringResult.success) {
            console.log(`   Monitoring Extension: ${monitoringResult.monitoringExtension}`);
            console.log(`   Operator: ${monitoringResult.operator}`);
        }

        // Test calling monitoring extension 90
        console.log('\n   Testing call monitoring extension 90:');
        const monitorCallResult = await this.dialPlan.dialNumber(1000, '90');
        console.log(`   Result: ${monitorCallResult.success ? '✅ SUCCESS' : '❌ FAILED'}`);
        console.log(`   Route: ${monitorCallResult.routingType?.toUpperCase()}`);

        const monitorExtInfo = this.dialPlan.getExtensionInfo(90);
        console.log(`   Extension Info: ${monitorExtInfo.name}`);
        console.log(`   Monitoring Enabled: ${monitorExtInfo.enabled ? 'YES' : 'NO'}`);
    }

    async testExtensionRouting() {
        console.log('\n📞 Test 4: Extension Routing Tests');
        console.log('-' .repeat(30));

        const tests = [
            { extension: '0', description: 'Operator Extension (logged in)' },
            { extension: '90', description: 'Call Monitoring System' },
            { extension: '100', description: 'Main IVR' },
            { extension: '101', description: 'CallCentric Dominique' },
            { extension: '102', description: 'CallCentric Operator' },
            { extension: '1000', description: 'Sales Manager' },
            { extension: '2001', description: 'Test User (logged in operator)' },
            { extension: '9901', description: 'Hold Music Preview' }
        ];

        for (const test of tests) {
            console.log(`\n   Testing ${test.description} (${test.extension}):`);

            const result = await this.dialPlan.dialNumber(1000, test.extension);
            console.log(`   Result: ${result.success ? '✅ SUCCESS' : '❌ BLOCKED'}`);
            console.log(`   Route: ${result.routingType?.toUpperCase()}`);

            if (result.operatorInfo) {
                console.log(`   Operator: ${result.operatorInfo.user} on ext ${result.operatorInfo.extension}`);
            }

            if (result.reason) {
                console.log(`   Reason: ${result.reason}`);
            }

            await this.delay(500);
        }
    }

    async testCallCentric102Routing() {
        console.log('\n📞 Test 5: CallCentric 102 Routing (Operator Logged In)');
        console.log('-' .repeat(30));

        // Test with operator logged in
        console.log('   Testing CallCentric 102 with operator logged in:');
        const result = await this.dialPlan.dialNumber('external', '102');

        console.log(`   Result: ${result.success ? '✅ SUCCESS' : '❌ FAILED'}`);
        console.log(`   Route: ${result.routingType?.toUpperCase()}`);

        if (result.operatorInfo) {
            console.log(`   Routes to Operator: ${result.operatorInfo.user}`);
            console.log(`   Operator Extension: ${result.operatorInfo.extension}`);
            console.log(`   Login Time: ${result.operatorInfo.loginTime.toLocaleTimeString()}`);
        }

        if (result.destinationInfo) {
            console.log(`   CallCentric Info: ${result.destinationInfo.name}`);
            console.log(`   External Number: ${result.destinationInfo.external}`);
        }

        // Show current operator status
        console.log('\n   Current Operator Status:');
        const operatorStatus = this.dialPlan.getOperatorStatus();
        console.log(`   Logged In: ${operatorStatus.isLoggedIn}`);
        console.log(`   User: ${operatorStatus.currentUser}`);
        console.log(`   Extension: ${operatorStatus.extension}`);
        console.log(`   Monitoring Enabled: ${operatorStatus.callMonitoring.enabled}`);
    }

    async testOperatorLogoutAndFallback() {
        console.log('\n👤 Test 6: Operator Logout and IVR Fallback');
        console.log('-' .repeat(30));

        // Test logout
        console.log('   Logging out operator...');
        const logoutResult = await this.dialPlan.logoutOperator();

        console.log(`   Logout Result: ${logoutResult.success ? '✅ SUCCESS' : '❌ FAILED'}`);
        if (logoutResult.message) {
            console.log(`   Message: ${logoutResult.message}`);
        }

        if (logoutResult.sessionDuration) {
            console.log(`   Session Duration: ${logoutResult.sessionDuration} minutes`);
        }

        // Test CallCentric 102 routing after logout (should go to IVR)
        console.log('\n   Testing CallCentric 102 after operator logout:');
        const fallbackResult = await this.dialPlan.dialNumber('external', '102');

        console.log(`   Result: ${fallbackResult.success ? '✅ SUCCESS' : '❌ FAILED'}`);
        console.log(`   Route: ${fallbackResult.routingType?.toUpperCase()}`);

        if (fallbackResult.reason) {
            console.log(`   Reason: ${fallbackResult.reason}`);
        }

        // Test operator extension 0 after logout
        console.log('\n   Testing operator extension 0 after logout:');
        const operatorAfterLogout = await this.dialPlan.dialNumber(1000, '0');
        console.log(`   Result: ${operatorAfterLogout.success ? '✅ SUCCESS' : '❌ BLOCKED'}`);
        console.log(`   Route: ${operatorAfterLogout.routingType?.toUpperCase()}`);

        const extInfo = this.dialPlan.getExtensionInfo(0);
        console.log(`   Extension Info: ${extInfo.name}`);
        console.log(`   Available: ${extInfo.available ? 'YES' : 'NO'}`);

        // Final system status
        console.log('\n   Final System Status:');
        const finalStatus = this.dialPlan.getSystemStatus();
        console.log(`   Operator: ${finalStatus.operator.isLoggedIn ? 'LOGGED IN' : 'NOT LOGGED IN'}`);
        console.log(`   Call Monitoring: ${finalStatus.operator.callMonitoring.enabled ? 'ENABLED' : 'DISABLED'}`);
    }

    async runInteractiveOperatorDemo() {
        console.log('\n🎮 Interactive Operator Demo');
        console.log('-' .repeat(30));
        console.log('This demonstrates the complete operator workflow:');
        console.log('');

        // Step 1: Login
        console.log('📝 Step 1: Operator Login');
        const loginResult = await this.dialPlan.loginOperator('Dominique', 2001);
        console.log(`   ✅ ${loginResult.message}`);
        await this.delay(2000);

        // Step 2: Enable Monitoring
        console.log('\n🔍 Step 2: Enable Call Monitoring');
        const monitorResult = await this.dialPlan.enableCallMonitoring();
        console.log(`   ✅ ${monitorResult.message}`);
        await this.delay(2000);

        // Step 3: Demonstrate routing
        console.log('\n📞 Step 3: CallCentric 102 Routes to Operator');
        const routingResult = await this.dialPlan.dialNumber('external', '102');
        console.log(`   ✅ Incoming CallCentric call routes to operator ${routingResult.operatorInfo?.user}`);
        await this.delay(2000);

        // Step 4: Monitor a call
        console.log('\n🔍 Step 4: Monitor Active Call');
        const monitorCallResult = await this.dialPlan.monitorCall('demo-call-12345');
        if (monitorCallResult.success) {
            console.log(`   ✅ ${monitorCallResult.message}`);
        }
        await this.delay(2000);

        // Step 5: Show status
        console.log('\n📊 Step 5: System Status');
        const status = this.dialPlan.getOperatorStatus();
        console.log(`   👤 Operator: ${status.currentUser} (logged in)`);
        console.log(`   📞 Extension: ${status.extension}`);
        console.log(`   🔍 Monitoring: ${status.callMonitoring.enabled ? 'ACTIVE' : 'INACTIVE'}`);
        console.log(`   📱 Active Monitored Calls: ${status.callMonitoring.activeCalls}`);
        await this.delay(2000);

        // Step 6: Logout
        console.log('\n👋 Step 6: Operator Logout');
        const logoutResult = await this.dialPlan.logoutOperator();
        console.log(`   ✅ ${logoutResult.message}`);

        console.log('\n🎯 Demo Complete! The operator system is fully functional.');
    }

    async delay(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    showUsage() {
        console.log('👤 FlexPBX Operator Test Usage:');
        console.log('');
        console.log('Available test modes:');
        console.log('  basic      - Run basic operator system tests');
        console.log('  demo       - Interactive operator workflow demo');
        console.log('  login      - Test operator login/logout only');
        console.log('  routing    - Test CallCentric 102 routing');
        console.log('  monitoring - Test call monitoring system');
        console.log('');
        console.log('Examples:');
        console.log('  node FlexPBX-Operator-Test.js basic');
        console.log('  node FlexPBX-Operator-Test.js demo');
        console.log('  node FlexPBX-Operator-Test.js routing');
        console.log('');
    }
}

// CLI Interface
if (require.main === module) {
    const testSystem = new FlexPBXOperatorTest();
    const testMode = process.argv[2] || 'usage';

    async function runTests() {
        switch (testMode.toLowerCase()) {
            case 'basic':
                await testSystem.runOperatorTests();
                break;

            case 'demo':
                await testSystem.runInteractiveOperatorDemo();
                break;

            case 'login':
                await testSystem.testOperatorLogin();
                await testSystem.delay(2000);
                await testSystem.testOperatorLogoutAndFallback();
                break;

            case 'routing':
                await testSystem.testOperatorLogin();
                await testSystem.delay(1000);
                await testSystem.testCallCentric102Routing();
                await testSystem.delay(1000);
                await testSystem.testOperatorLogoutAndFallback();
                break;

            case 'monitoring':
                await testSystem.testOperatorLogin();
                await testSystem.delay(1000);
                await testSystem.testCallMonitoringActivation();
                await testSystem.delay(1000);
                await testSystem.testOperatorLogoutAndFallback();
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

module.exports = FlexPBXOperatorTest;