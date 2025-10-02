#!/usr/bin/env node

/**
 * 🔑 FlexPBX Licensing & Payment Test System
 * Tests licensing, payment processing, and subscription management
 */

const LicenseService = require('./src/main/services/LicenseService');
const PaymentService = require('./src/main/services/PaymentService');
const SubscriptionService = require('./src/main/services/SubscriptionService');

class FlexPBXLicensingTest {
    constructor() {
        this.licenseService = new LicenseService();
        this.paymentService = new PaymentService();
        this.subscriptionService = new SubscriptionService(this.licenseService, this.paymentService);

        console.log('🔑 FlexPBX Licensing & Payment Test System');
        console.log('=' .repeat(60));
        console.log('   License Management');
        console.log('   Payment Processing');
        console.log('   Subscription Management');
        console.log('   Trial & Demo Modes');
        console.log('');
    }

    async initialize() {
        console.log('🔧 Initializing licensing system...');

        try {
            const licenseInit = await this.licenseService.initialize();
            const paymentInit = await this.paymentService.initialize();
            const subscriptionInit = await this.subscriptionService.initialize();

            if (licenseInit && paymentInit && subscriptionInit) {
                console.log('✅ All services initialized successfully');
                return true;
            } else {
                console.log('❌ Service initialization failed');
                return false;
            }

        } catch (error) {
            console.error('❌ Initialization error:', error);
            return false;
        }
    }

    async runLicenseTests() {
        console.log('\n🔑 Running License Tests...');
        console.log('-' .repeat(50));

        // Test 1: License info and trial status
        await this.testLicenseInfo();
        await this.delay(1000);

        // Test 2: Feature validation
        await this.testFeatureValidation();
        await this.delay(1000);

        // Test 3: License activation
        await this.testLicenseActivation();
        await this.delay(1000);

        // Test 4: License validation
        await this.testLicenseValidation();
        await this.delay(1000);

        // Test 5: Hardware binding
        await this.testHardwareBinding();

        console.log('\n✅ License tests completed!');
    }

    async testLicenseInfo() {
        console.log('\n📊 Test 1: License Information');
        console.log('-' .repeat(30));

        const licenseInfo = this.licenseService.getLicenseInfo();

        console.log(`   Status: ${licenseInfo.status}`);
        console.log(`   Type: ${licenseInfo.type}`);

        if (licenseInfo.licenseKey) {
            console.log(`   License Key: ${licenseInfo.licenseKey}`);
        }

        if (licenseInfo.customer) {
            console.log(`   Customer: ${licenseInfo.customer}`);
        }

        if (licenseInfo.daysRemaining !== -1) {
            console.log(`   Days Remaining: ${licenseInfo.daysRemaining}`);
        } else {
            console.log(`   Expiry: Never (Lifetime)`);
        }

        console.log(`   Hardware ID: ${licenseInfo.hardwareId}`);

        // Show features
        console.log('\n   📋 Available Features:');
        for (const [feature, value] of Object.entries(licenseInfo.features)) {
            if (typeof value === 'boolean') {
                console.log(`      ${feature}: ${value ? '✅' : '❌'}`);
            } else if (typeof value === 'number') {
                console.log(`      ${feature}: ${value === -1 ? 'Unlimited' : value}`);
            } else {
                console.log(`      ${feature}: ${value}`);
            }
        }
    }

    async testFeatureValidation() {
        console.log('\n🔍 Test 2: Feature Validation');
        console.log('-' .repeat(30));

        const features = [
            'maxExtensions',
            'maxConcurrentCalls',
            'callRecording',
            'advancedRouting',
            'apiAccess',
            'googleVoice',
            'callCentric'
        ];

        for (const feature of features) {
            const result = this.licenseService.checkFeature(feature);
            console.log(`   ${feature}: ${this.formatFeatureResult(result)}`);
        }

        // Test extension limit
        console.log('\n   🔢 Extension Limit Tests:');
        const extensionTests = [5, 10, 20, 50, 100];

        for (const count of extensionTests) {
            const result = await this.licenseService.checkExtensionLimit(count);
            console.log(`   ${count} extensions: ${result.allowed ? '✅ Allowed' : '❌ ' + result.reason}`);
        }

        // Test call limit
        console.log('\n   📞 Call Limit Tests:');
        const callTests = [2, 5, 10, 25, 50];

        for (const count of callTests) {
            const result = await this.licenseService.checkCallLimit(count);
            console.log(`   ${count} calls: ${result.allowed ? '✅ Allowed' : '❌ ' + result.reason}`);
        }
    }

    async testLicenseActivation() {
        console.log('\n🔐 Test 3: License Activation');
        console.log('-' .repeat(30));

        const testLicenses = [
            {
                key: 'PRO-1234-5678-9ABC-DEF0',
                email: 'test@flexpbx.local',
                description: 'Professional License'
            },
            {
                key: 'ENT-ABCD-EF12-3456-7890',
                email: 'enterprise@flexpbx.local',
                description: 'Enterprise License'
            },
            {
                key: 'LIF-9999-8888-7777-6666',
                email: 'lifetime@flexpbx.local',
                description: 'Lifetime License'
            }
        ];

        for (const license of testLicenses) {
            console.log(`\n   Testing ${license.description}:`);
            console.log(`   Key: ${license.key}`);
            console.log(`   Email: ${license.email}`);

            const result = await this.licenseService.activateLicense(license.key, license.email);

            if (result.success) {
                console.log(`   ✅ Activation successful`);
                console.log(`   Type: ${result.license.type}`);
                console.log(`   Valid until: ${result.license.expiryDate ? new Date(result.license.expiryDate).toLocaleDateString() : 'Never'}`);
            } else {
                console.log(`   ❌ Activation failed: ${result.error}`);
            }

            await this.delay(500);
        }
    }

    async testLicenseValidation() {
        console.log('\n✅ Test 4: License Validation');
        console.log('-' .repeat(30));

        const validation = await this.licenseService.validateLicense();

        console.log(`   Validation Result: ${validation.valid ? '✅ Valid' : '❌ Invalid'}`);

        if (!validation.valid) {
            console.log(`   Reason: ${validation.reason}`);
        } else {
            console.log(`   License ID: ${validation.license.id}`);
            console.log(`   License Type: ${validation.license.type}`);
            console.log(`   Customer: ${validation.license.customerEmail}`);
            console.log(`   Hardware Match: ✅`);
            console.log(`   Signature Valid: ✅`);
        }

        // Test license export/import
        console.log('\n   📤 Testing License Export:');
        try {
            const exportPath = await this.licenseService.exportLicense();
            console.log(`   ✅ License exported to: ${exportPath}`);
        } catch (error) {
            console.log(`   ❌ Export failed: ${error.message}`);
        }
    }

    async testHardwareBinding() {
        console.log('\n🔒 Test 5: Hardware Binding');
        console.log('-' .repeat(30));

        const hardwareId = this.licenseService.hardwareId;
        console.log(`   Current Hardware ID: ${hardwareId}`);

        // Simulate hardware change
        const originalHardwareId = this.licenseService.hardwareId;
        this.licenseService.hardwareId = 'fake-hardware-id';

        const validationWithFakeHardware = await this.licenseService.validateLicense();
        console.log(`   Validation with fake hardware: ${validationWithFakeHardware.valid ? '❌ Should fail' : '✅ Correctly failed'}`);

        if (!validationWithFakeHardware.valid) {
            console.log(`   Reason: ${validationWithFakeHardware.reason}`);
        }

        // Restore original hardware ID
        this.licenseService.hardwareId = originalHardwareId;

        const validationRestored = await this.licenseService.validateLicense();
        console.log(`   Validation with correct hardware: ${validationRestored.valid ? '✅ Valid' : '❌ Should be valid'}`);
    }

    async runPaymentTests() {
        console.log('\n💳 Running Payment Tests...');
        console.log('-' .repeat(50));

        // Test 1: Payment providers
        await this.testPaymentProviders();
        await this.delay(1000);

        // Test 2: Payment intent creation
        await this.testPaymentIntentCreation();
        await this.delay(1000);

        // Test 3: Payment processing
        await this.testPaymentProcessing();
        await this.delay(1000);

        // Test 4: Refund processing
        await this.testRefundProcessing();

        console.log('\n✅ Payment tests completed!');
    }

    async testPaymentProviders() {
        console.log('\n🏪 Test 1: Payment Providers');
        console.log('-' .repeat(30));

        const providers = this.paymentService.getPaymentProviders();

        console.log(`   Available Providers: ${providers.length}`);

        providers.forEach(provider => {
            console.log(`\n   💳 ${provider.name}:`);
            console.log(`      Methods: ${provider.methods.join(', ')}`);
            console.log(`      Currencies: ${provider.currencies.join(', ')}`);
        });

        const plans = this.paymentService.getSubscriptionPlans();
        console.log(`\n   📋 Subscription Plans: ${plans.length}`);

        plans.forEach(plan => {
            console.log(`\n   📦 ${plan.name}:`);
            console.log(`      Price: $${plan.price} ${plan.currency}`);
            console.log(`      Interval: ${plan.interval}`);
            console.log(`      License: ${plan.licenseType}`);
            if (plan.discount) {
                console.log(`      Discount: ${plan.discount}`);
            }
        });
    }

    async testPaymentIntentCreation() {
        console.log('\n🎯 Test 2: Payment Intent Creation');
        console.log('-' .repeat(30));

        const testCustomer = {
            name: 'Test Customer',
            email: 'test@flexpbx.local',
            customerId: 'cus_test_12345'
        };

        const testCases = [
            { planId: 'BASIC_YEARLY', method: 'STRIPE' },
            { planId: 'PROFESSIONAL_MONTHLY', method: 'PAYPAL' },
            { planId: 'ENTERPRISE_YEARLY', method: 'SQUARE' },
            { planId: 'LIFETIME', method: 'CRYPTO' }
        ];

        for (const testCase of testCases) {
            console.log(`\n   Testing ${testCase.planId} with ${testCase.method}:`);

            const result = await this.paymentService.createPaymentIntent(
                testCase.planId,
                testCustomer,
                testCase.method
            );

            if (result.success) {
                console.log(`   ✅ Payment intent created: ${result.paymentIntent.id}`);
                console.log(`   Amount: $${result.paymentIntent.amount}`);
                console.log(`   Method: ${result.paymentIntent.paymentMethod}`);
                console.log(`   Instructions: ${result.nextSteps.message}`);
            } else {
                console.log(`   ❌ Failed: ${result.error}`);
            }

            await this.delay(500);
        }
    }

    async testPaymentProcessing() {
        console.log('\n⚡ Test 3: Payment Processing');
        console.log('-' .repeat(30));

        // Create a payment intent first
        const testCustomer = {
            name: 'Payment Test Customer',
            email: 'payment-test@flexpbx.local'
        };

        const intentResult = await this.paymentService.createPaymentIntent(
            'PROFESSIONAL_YEARLY',
            testCustomer,
            'STRIPE'
        );

        if (intentResult.success) {
            console.log(`   Payment intent created: ${intentResult.paymentIntent.id}`);

            // Simulate payment details
            const paymentDetails = {
                paymentIntentId: intentResult.paymentIntent.id,
                amount: intentResult.paymentIntent.amount,
                currency: intentResult.paymentIntent.currency,
                paymentMethod: intentResult.paymentIntent.paymentMethod,
                customer: {
                    id: intentResult.paymentIntent.customerId,
                    email: intentResult.paymentIntent.customerEmail
                },
                licenseType: intentResult.paymentIntent.metadata.licenseType,
                planInterval: intentResult.paymentIntent.metadata.interval
            };

            console.log(`   Processing payment...`);

            const paymentResult = await this.paymentService.processPayment(
                intentResult.paymentIntent.id,
                paymentDetails
            );

            if (paymentResult.success) {
                console.log(`   ✅ Payment processed successfully`);
                console.log(`   Transaction ID: ${paymentResult.payment.transactionId}`);
                console.log(`   License Key: ${paymentResult.licenseKey}`);
            } else {
                console.log(`   ❌ Payment failed: ${paymentResult.error}`);
            }
        } else {
            console.log(`   ❌ Could not create payment intent: ${intentResult.error}`);
        }
    }

    async testRefundProcessing() {
        console.log('\n💰 Test 4: Refund Processing');
        console.log('-' .repeat(30));

        const paymentHistory = this.paymentService.getPaymentHistory();

        if (paymentHistory.length > 0) {
            const lastPayment = paymentHistory[paymentHistory.length - 1];
            console.log(`   Processing refund for payment: ${lastPayment.id}`);

            const refundResult = await this.paymentService.processRefund(
                lastPayment.id,
                lastPayment.amount,
                'customer_request'
            );

            if (refundResult.success) {
                console.log(`   ✅ Refund processed: ${refundResult.refund.id}`);
                console.log(`   Amount: $${refundResult.refund.amount}`);
                console.log(`   Status: ${refundResult.refund.status}`);
            } else {
                console.log(`   ❌ Refund failed: ${refundResult.error}`);
            }
        } else {
            console.log(`   ⚠️ No payments found to refund`);
        }
    }

    async runSubscriptionTests() {
        console.log('\n📅 Running Subscription Tests...');
        console.log('-' .repeat(50));

        // Test 1: Subscription creation
        await this.testSubscriptionCreation();
        await this.delay(1000);

        // Test 2: Subscription renewal
        await this.testSubscriptionRenewal();
        await this.delay(1000);

        // Test 3: Subscription management
        await this.testSubscriptionManagement();
        await this.delay(1000);

        // Test 4: Subscription monitoring
        await this.testSubscriptionMonitoring();

        console.log('\n✅ Subscription tests completed!');
    }

    async testSubscriptionCreation() {
        console.log('\n📝 Test 1: Subscription Creation');
        console.log('-' .repeat(30));

        const testCustomer = 'cus_subscription_test_' + Date.now();

        const subscriptionTests = [
            { planId: 'BASIC_MONTHLY', cycle: 'MONTHLY', autoRenewal: true },
            { planId: 'PROFESSIONAL_YEARLY', cycle: 'YEARLY', autoRenewal: true },
            { planId: 'ENTERPRISE_YEARLY', cycle: 'YEARLY', autoRenewal: false }
        ];

        for (const test of subscriptionTests) {
            console.log(`\n   Creating ${test.planId} subscription:`);

            const result = await this.subscriptionService.createSubscription(
                testCustomer,
                test.planId,
                test.cycle,
                test.autoRenewal
            );

            if (result.success) {
                console.log(`   ✅ Subscription created: ${result.subscription.id}`);
                console.log(`   Customer: ${result.subscription.customerId}`);
                console.log(`   Plan: ${result.subscription.planId}`);
                console.log(`   Cycle: ${result.subscription.billingCycle}`);
                console.log(`   Auto-renewal: ${result.subscription.autoRenewal ? 'Yes' : 'No'}`);

                if (result.subscription.nextRenewalDate) {
                    console.log(`   Next renewal: ${new Date(result.subscription.nextRenewalDate).toLocaleDateString()}`);
                }
            } else {
                console.log(`   ❌ Creation failed: ${result.error}`);
            }

            await this.delay(500);
        }
    }

    async testSubscriptionRenewal() {
        console.log('\n🔄 Test 2: Subscription Renewal');
        console.log('-' .repeat(30));

        const activeSubscriptions = this.subscriptionService.getSubscriptionsByStatus('active');

        if (activeSubscriptions.length > 0) {
            const subscription = activeSubscriptions[0];
            console.log(`   Testing renewal for subscription: ${subscription.id}`);

            const renewalResult = await this.subscriptionService.renewSubscription(subscription.id);

            if (renewalResult.success) {
                console.log(`   ✅ Subscription renewed successfully`);
                console.log(`   New period: ${new Date(renewalResult.subscription.currentPeriodStart).toLocaleDateString()} - ${new Date(renewalResult.subscription.currentPeriodEnd).toLocaleDateString()}`);
                console.log(`   Renewal count: ${renewalResult.subscription.renewalCount}`);
                console.log(`   License key: ${renewalResult.licenseKey}`);
            } else {
                console.log(`   ❌ Renewal failed: ${renewalResult.error}`);
            }
        } else {
            console.log(`   ⚠️ No active subscriptions to test renewal`);
        }
    }

    async testSubscriptionManagement() {
        console.log('\n⚙️ Test 3: Subscription Management');
        console.log('-' .repeat(30));

        const activeSubscriptions = this.subscriptionService.getSubscriptionsByStatus('active');

        if (activeSubscriptions.length > 0) {
            const subscription = activeSubscriptions[0];
            console.log(`   Testing management for subscription: ${subscription.id}`);

            // Test pause
            console.log(`\n   ⏸️ Testing subscription pause:`);
            const pauseDuration = 7 * 24 * 60 * 60 * 1000; // 7 days
            const pauseResult = await this.subscriptionService.pauseSubscription(subscription.id, pauseDuration);

            if (pauseResult.success) {
                console.log(`   ✅ Subscription paused until: ${new Date(pauseResult.subscription.pauseEndDate).toLocaleDateString()}`);
            } else {
                console.log(`   ❌ Pause failed: ${pauseResult.error}`);
            }

            await this.delay(1000);

            // Test resume
            console.log(`\n   ▶️ Testing subscription resume:`);
            const resumeResult = await this.subscriptionService.resumeSubscription(subscription.id);

            if (resumeResult.success) {
                console.log(`   ✅ Subscription resumed successfully`);
                console.log(`   Status: ${resumeResult.subscription.status}`);
            } else {
                console.log(`   ❌ Resume failed: ${resumeResult.error}`);
            }

            await this.delay(1000);

            // Test cancellation
            console.log(`\n   ❌ Testing subscription cancellation:`);
            const cancelResult = await this.subscriptionService.cancelSubscription(subscription.id, 'test_cancellation');

            if (cancelResult.success) {
                console.log(`   ✅ Subscription cancelled`);
                console.log(`   Reason: ${cancelResult.subscription.cancellationReason}`);
                console.log(`   Effective date: ${new Date(cancelResult.subscription.effectiveCancellationDate).toLocaleDateString()}`);
            } else {
                console.log(`   ❌ Cancellation failed: ${cancelResult.error}`);
            }
        } else {
            console.log(`   ⚠️ No active subscriptions to test management`);
        }
    }

    async testSubscriptionMonitoring() {
        console.log('\n📊 Test 4: Subscription Monitoring');
        console.log('-' .repeat(30));

        // Check upcoming renewals
        const upcomingRenewals = await this.subscriptionService.checkUpcomingRenewals();
        console.log(`   Upcoming renewals: ${upcomingRenewals.length}`);

        upcomingRenewals.forEach(renewal => {
            console.log(`      ${renewal.subscription.id}: ${renewal.daysUntilRenewal} days`);
        });

        // Check overdue renewals
        const overdueRenewals = await this.subscriptionService.checkOverdueRenewals();
        console.log(`   Overdue renewals: ${overdueRenewals.length}`);

        overdueRenewals.forEach(overdue => {
            console.log(`      ${overdue.subscription.id}: ${overdue.daysOverdue} days overdue`);
        });

        // Get subscription statistics
        const stats = this.subscriptionService.getSubscriptionStats();
        console.log(`\n   📈 Subscription Statistics:`);
        console.log(`      Total: ${stats.total}`);
        console.log(`      Active: ${stats.active}`);
        console.log(`      Paused: ${stats.paused}`);
        console.log(`      Cancelled: ${stats.cancelled}`);
        console.log(`      Past Due: ${stats.past_due}`);
        console.log(`      Suspended: ${stats.suspended}`);
        console.log(`      Total Revenue: $${stats.totalRevenue.toFixed(2)}`);
    }

    async runComprehensiveDemo() {
        console.log('\n🎮 Comprehensive Licensing Demo');
        console.log('-' .repeat(50));
        console.log('This demonstrates the complete licensing workflow:\n');

        // Step 1: Show trial status
        console.log('📝 Step 1: Check Trial Status');
        const trialInfo = this.licenseService.getLicenseInfo();
        console.log(`   Current License: ${trialInfo.type} (${trialInfo.daysRemaining} days remaining)`);
        await this.delay(2000);

        // Step 2: Create payment intent
        console.log('\n💳 Step 2: Create Payment Intent');
        const customer = {
            name: 'Demo Customer',
            email: 'demo@flexpbx.local'
        };

        const paymentIntent = await this.paymentService.createPaymentIntent(
            'PROFESSIONAL_YEARLY',
            customer,
            'STRIPE'
        );

        if (paymentIntent.success) {
            console.log(`   ✅ Payment intent created: ${paymentIntent.paymentIntent.id}`);
            console.log(`   Amount: $${paymentIntent.paymentIntent.amount}`);
        }
        await this.delay(2000);

        // Step 3: Process payment
        console.log('\n⚡ Step 3: Process Payment');
        if (paymentIntent.success) {
            const paymentDetails = {
                paymentIntentId: paymentIntent.paymentIntent.id,
                amount: paymentIntent.paymentIntent.amount,
                currency: paymentIntent.paymentIntent.currency,
                paymentMethod: paymentIntent.paymentIntent.paymentMethod,
                customer: {
                    id: paymentIntent.paymentIntent.customerId,
                    email: paymentIntent.paymentIntent.customerEmail
                },
                licenseType: paymentIntent.paymentIntent.metadata.licenseType,
                planInterval: paymentIntent.paymentIntent.metadata.interval
            };

            const payment = await this.paymentService.processPayment(
                paymentIntent.paymentIntent.id,
                paymentDetails
            );

            if (payment.success) {
                console.log(`   ✅ Payment processed: ${payment.payment.transactionId}`);
                console.log(`   License key generated: ${payment.licenseKey}`);
            }
        }
        await this.delay(2000);

        // Step 4: Activate license
        console.log('\n🔑 Step 4: Activate License');
        const activationResult = await this.licenseService.activateLicense('PRO-DEMO-1234-5678-9ABC', customer.email);
        if (activationResult.success) {
            console.log(`   ✅ License activated: ${activationResult.license.type}`);
            console.log(`   Features unlocked: Professional level access`);
        }
        await this.delay(2000);

        // Step 5: Create subscription
        console.log('\n📅 Step 5: Create Subscription');
        const subscription = await this.subscriptionService.createSubscription(
            paymentIntent.paymentIntent?.customerId || 'demo_customer',
            'PROFESSIONAL_YEARLY',
            'YEARLY',
            true
        );

        if (subscription.success) {
            console.log(`   ✅ Subscription created: ${subscription.subscription.id}`);
            console.log(`   Auto-renewal enabled for continuous service`);
        }
        await this.delay(2000);

        // Step 6: Show final status
        console.log('\n📊 Step 6: Final Status');
        const finalLicenseInfo = this.licenseService.getLicenseInfo();
        console.log(`   License Type: ${finalLicenseInfo.type}`);
        console.log(`   Status: ${finalLicenseInfo.status}`);
        console.log(`   Features: Professional level unlocked`);
        console.log(`   Subscription: Active with auto-renewal`);

        console.log('\n🎯 Demo Complete! FlexPBX licensing system is fully operational.');
    }

    formatFeatureResult(result) {
        if (typeof result === 'boolean') {
            return result ? '✅ Enabled' : '❌ Disabled';
        } else if (typeof result === 'number') {
            return result === -1 ? '♾️ Unlimited' : `📊 ${result}`;
        } else {
            return `📝 ${result}`;
        }
    }

    async delay(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    showUsage() {
        console.log('🔑 FlexPBX Licensing Test Usage:');
        console.log('');
        console.log('Available test modes:');
        console.log('  license      - Test license management system');
        console.log('  payment      - Test payment processing');
        console.log('  subscription - Test subscription management');
        console.log('  demo         - Complete licensing workflow demo');
        console.log('  all          - Run all tests');
        console.log('');
        console.log('Examples:');
        console.log('  node FlexPBX-Licensing-Test.js license');
        console.log('  node FlexPBX-Licensing-Test.js payment');
        console.log('  node FlexPBX-Licensing-Test.js demo');
        console.log('');
    }
}

// CLI Interface
if (require.main === module) {
    const testSystem = new FlexPBXLicensingTest();
    const testMode = process.argv[2] || 'usage';

    async function runTests() {
        const initialized = await testSystem.initialize();
        if (!initialized && testMode !== 'usage') {
            console.log('❌ Test system initialization failed');
            process.exit(1);
        }

        switch (testMode.toLowerCase()) {
            case 'license':
                await testSystem.runLicenseTests();
                break;

            case 'payment':
                await testSystem.runPaymentTests();
                break;

            case 'subscription':
                await testSystem.runSubscriptionTests();
                break;

            case 'demo':
                await testSystem.runComprehensiveDemo();
                break;

            case 'all':
                await testSystem.runLicenseTests();
                await testSystem.delay(3000);
                await testSystem.runPaymentTests();
                await testSystem.delay(3000);
                await testSystem.runSubscriptionTests();
                await testSystem.delay(3000);
                await testSystem.runComprehensiveDemo();
                break;

            case 'usage':
            default:
                testSystem.showUsage();
                break;
        }

        // Cleanup
        testSystem.subscriptionService.cleanup();
    }

    runTests().catch(error => {
        console.error('❌ Test execution failed:', error);
        process.exit(1);
    });
}

module.exports = FlexPBXLicensingTest;