/**
 * 💳 FlexPBX Payment Service
 * Handles payment processing, subscriptions, and billing
 */

const EventEmitter = require('events');
const crypto = require('crypto');
const fs = require('fs').promises;
const path = require('path');

class PaymentService extends EventEmitter {
    constructor() {
        super();

        this.paymentProviders = {
            STRIPE: {
                name: 'Stripe',
                currencies: ['USD', 'EUR', 'GBP', 'CAD'],
                methods: ['card', 'bank_transfer', 'apple_pay', 'google_pay'],
                enabled: true
            },
            PAYPAL: {
                name: 'PayPal',
                currencies: ['USD', 'EUR', 'GBP', 'CAD', 'AUD'],
                methods: ['paypal', 'credit_card'],
                enabled: true
            },
            SQUARE: {
                name: 'Square',
                currencies: ['USD', 'CAD', 'GBP', 'AUD'],
                methods: ['card', 'cash_app'],
                enabled: true
            },
            CRYPTO: {
                name: 'Cryptocurrency',
                currencies: ['BTC', 'ETH', 'USDC'],
                methods: ['bitcoin', 'ethereum', 'usdc'],
                enabled: true
            }
        };

        this.subscriptionPlans = {
            BASIC_MONTHLY: {
                id: 'basic_monthly',
                name: 'Basic Monthly',
                licenseType: 'BASIC',
                price: 4.99,
                currency: 'USD',
                interval: 'month',
                intervalCount: 1,
                features: 'Up to 20 extensions, 10 concurrent calls, email support'
            },
            BASIC_YEARLY: {
                id: 'basic_yearly',
                name: 'Basic Yearly',
                licenseType: 'BASIC',
                price: 49.99,
                currency: 'USD',
                interval: 'year',
                intervalCount: 1,
                discount: '17% off monthly rate',
                features: 'Up to 20 extensions, 10 concurrent calls, email support'
            },
            PROFESSIONAL_MONTHLY: {
                id: 'professional_monthly',
                name: 'Professional Monthly',
                licenseType: 'PROFESSIONAL',
                price: 19.99,
                currency: 'USD',
                interval: 'month',
                intervalCount: 1,
                features: 'Up to 100 extensions, 50 concurrent calls, API access, priority support'
            },
            PROFESSIONAL_YEARLY: {
                id: 'professional_yearly',
                name: 'Professional Yearly',
                licenseType: 'PROFESSIONAL',
                price: 199.99,
                currency: 'USD',
                interval: 'year',
                intervalCount: 1,
                discount: '17% off monthly rate',
                features: 'Up to 100 extensions, 50 concurrent calls, API access, priority support'
            },
            ENTERPRISE_MONTHLY: {
                id: 'enterprise_monthly',
                name: 'Enterprise Monthly',
                licenseType: 'ENTERPRISE',
                price: 99.99,
                currency: 'USD',
                interval: 'month',
                intervalCount: 1,
                features: 'Unlimited extensions and calls, 24/7 support, white label, custom integrations'
            },
            ENTERPRISE_YEARLY: {
                id: 'enterprise_yearly',
                name: 'Enterprise Yearly',
                licenseType: 'ENTERPRISE',
                price: 999.99,
                currency: 'USD',
                interval: 'year',
                intervalCount: 1,
                discount: '17% off monthly rate',
                features: 'Unlimited extensions and calls, 24/7 support, white label, custom integrations'
            },
            LIFETIME: {
                id: 'lifetime',
                name: 'Lifetime License',
                licenseType: 'LIFETIME',
                price: 2999.99,
                currency: 'USD',
                interval: 'lifetime',
                intervalCount: 1,
                features: 'One-time payment, unlimited everything, source code access, lifetime updates'
            }
        };

        this.paymentHistory = [];
        this.subscriptions = new Map();

        console.log('💳 FlexPBX Payment Service initialized');
    }

    async initialize() {
        try {
            // Load payment configuration
            await this.loadPaymentConfig();

            // Load payment history
            await this.loadPaymentHistory();

            // Load active subscriptions
            await this.loadSubscriptions();

            console.log('✅ Payment service ready');
            return true;

        } catch (error) {
            console.error('❌ Payment service initialization failed:', error);
            return false;
        }
    }

    async createPaymentIntent(planId, customerInfo, paymentMethod = 'STRIPE') {
        try {
            const plan = this.subscriptionPlans[planId];
            if (!plan) {
                throw new Error(`Plan ${planId} not found`);
            }

            const provider = this.paymentProviders[paymentMethod];
            if (!provider || !provider.enabled) {
                throw new Error(`Payment provider ${paymentMethod} not available`);
            }

            const paymentIntent = {
                id: this.generatePaymentId(),
                planId: planId,
                customerId: customerInfo.customerId || this.generateCustomerId(customerInfo.email),
                customerEmail: customerInfo.email,
                customerName: customerInfo.name,
                amount: plan.price,
                currency: plan.currency,
                paymentMethod: paymentMethod,
                status: 'pending',
                createdAt: new Date().toISOString(),
                expiresAt: new Date(Date.now() + (30 * 60 * 1000)).toISOString(), // 30 minutes
                metadata: {
                    licenseType: plan.licenseType,
                    planName: plan.name,
                    interval: plan.interval
                }
            };

            console.log(`💳 Payment intent created: ${paymentIntent.id}`);
            console.log(`   Plan: ${plan.name} ($${plan.price})`);
            console.log(`   Customer: ${customerInfo.name} (${customerInfo.email})`);
            console.log(`   Method: ${paymentMethod}`);

            // In a real implementation, this would integrate with actual payment providers
            await this.simulatePaymentProvider(paymentIntent, paymentMethod);

            this.emit('paymentIntentCreated', paymentIntent);

            return {
                success: true,
                paymentIntent: paymentIntent,
                nextSteps: this.getPaymentInstructions(paymentMethod, paymentIntent)
            };

        } catch (error) {
            console.error('❌ Payment intent creation failed:', error);
            return {
                success: false,
                error: error.message
            };
        }
    }

    async simulatePaymentProvider(paymentIntent, provider) {
        console.log(`🔗 Connecting to ${provider} payment gateway...`);

        switch (provider) {
            case 'STRIPE':
                paymentIntent.providerData = {
                    publishableKey: 'pk_test_flexpbx_' + crypto.randomBytes(16).toString('hex'),
                    clientSecret: 'pi_' + crypto.randomBytes(16).toString('hex'),
                    paymentUrl: `https://checkout.stripe.com/pay/${paymentIntent.id}`,
                    webhookEndpoint: '/api/payments/stripe/webhook'
                };
                break;

            case 'PAYPAL':
                paymentIntent.providerData = {
                    paypalOrderId: 'PAYPAL-' + crypto.randomBytes(8).toString('hex').toUpperCase(),
                    paymentUrl: `https://www.paypal.com/checkoutnow?token=${paymentIntent.id}`,
                    webhookEndpoint: '/api/payments/paypal/webhook'
                };
                break;

            case 'SQUARE':
                paymentIntent.providerData = {
                    applicationId: 'sq0idp-' + crypto.randomBytes(16).toString('hex'),
                    locationId: 'square-' + crypto.randomBytes(8).toString('hex'),
                    paymentUrl: `https://squareup.com/pay/${paymentIntent.id}`,
                    webhookEndpoint: '/api/payments/square/webhook'
                };
                break;

            case 'CRYPTO':
                paymentIntent.providerData = {
                    bitcoinAddress: '3FlexPBX' + crypto.randomBytes(16).toString('base58'),
                    ethereumAddress: '0x' + crypto.randomBytes(20).toString('hex'),
                    usdcAddress: '0x' + crypto.randomBytes(20).toString('hex'),
                    qrCode: `bitcoin:3FlexPBX${crypto.randomBytes(16).toString('base58')}?amount=${paymentIntent.amount}`,
                    webhookEndpoint: '/api/payments/crypto/webhook'
                };
                break;
        }

        console.log(`✅ ${provider} payment session created`);
    }

    getPaymentInstructions(provider, paymentIntent) {
        switch (provider) {
            case 'STRIPE':
                return {
                    type: 'redirect',
                    message: 'You will be redirected to Stripe for secure payment',
                    url: paymentIntent.providerData.paymentUrl,
                    instructions: [
                        'Click the payment link below',
                        'Enter your credit card information securely',
                        'Complete the payment to activate your license'
                    ]
                };

            case 'PAYPAL':
                return {
                    type: 'redirect',
                    message: 'You will be redirected to PayPal for payment',
                    url: paymentIntent.providerData.paymentUrl,
                    instructions: [
                        'Log into your PayPal account',
                        'Review and confirm the payment',
                        'Return to FlexPBX to complete activation'
                    ]
                };

            case 'CRYPTO':
                return {
                    type: 'crypto_transfer',
                    message: 'Send cryptocurrency to the address below',
                    addresses: {
                        bitcoin: paymentIntent.providerData.bitcoinAddress,
                        ethereum: paymentIntent.providerData.ethereumAddress,
                        usdc: paymentIntent.providerData.usdcAddress
                    },
                    amount: paymentIntent.amount,
                    instructions: [
                        'Send the exact amount to one of the addresses above',
                        'Payment will be confirmed automatically',
                        'License will be activated within 1 hour of payment confirmation'
                    ]
                };

            default:
                return {
                    type: 'manual',
                    message: 'Manual payment processing required',
                    instructions: [
                        'Contact support with your payment intent ID',
                        'Support will provide payment instructions',
                        'License will be activated after payment verification'
                    ]
                };
        }
    }

    async processPayment(paymentIntentId, paymentDetails) {
        try {
            console.log(`💳 Processing payment: ${paymentIntentId}`);

            // In a real implementation, verify payment with provider
            const verification = await this.simulatePaymentVerification(paymentIntentId, paymentDetails);

            if (!verification.success) {
                throw new Error(`Payment verification failed: ${verification.reason}`);
            }

            // Record successful payment
            const payment = {
                id: paymentIntentId,
                status: 'completed',
                processedAt: new Date().toISOString(),
                transactionId: verification.transactionId,
                amount: verification.amount,
                currency: verification.currency,
                paymentMethod: verification.paymentMethod,
                customer: verification.customer
            };

            this.paymentHistory.push(payment);
            await this.savePaymentHistory();

            // Create subscription if applicable
            if (verification.planInterval !== 'lifetime') {
                await this.createSubscription(payment);
            }

            console.log(`✅ Payment processed successfully: ${payment.transactionId}`);

            this.emit('paymentCompleted', payment);

            return {
                success: true,
                payment: payment,
                licenseKey: verification.licenseKey
            };

        } catch (error) {
            console.error('❌ Payment processing failed:', error);
            return {
                success: false,
                error: error.message
            };
        }
    }

    async simulatePaymentVerification(paymentIntentId, paymentDetails) {
        // Simulate payment provider verification
        console.log('🔍 Verifying payment with provider...');

        // Simulate processing delay
        await new Promise(resolve => setTimeout(resolve, 1000));

        // Generate transaction details
        const transactionId = 'txn_' + crypto.randomBytes(16).toString('hex');
        const licenseKey = this.generateLicenseKey(paymentDetails.licenseType);

        return {
            success: true,
            transactionId: transactionId,
            amount: paymentDetails.amount,
            currency: paymentDetails.currency,
            paymentMethod: paymentDetails.paymentMethod,
            customer: paymentDetails.customer,
            licenseKey: licenseKey,
            planInterval: paymentDetails.planInterval
        };
    }

    async createSubscription(payment) {
        const subscription = {
            id: 'sub_' + crypto.randomBytes(16).toString('hex'),
            customerId: payment.customer.id,
            customerEmail: payment.customer.email,
            planId: payment.planId,
            status: 'active',
            currentPeriodStart: new Date().toISOString(),
            currentPeriodEnd: this.calculateNextBillingDate(payment.planInterval),
            createdAt: new Date().toISOString(),
            lastPaymentId: payment.id,
            nextPaymentDate: this.calculateNextBillingDate(payment.planInterval)
        };

        this.subscriptions.set(subscription.id, subscription);
        await this.saveSubscriptions();

        console.log(`📅 Subscription created: ${subscription.id}`);
        console.log(`   Next billing: ${new Date(subscription.nextPaymentDate).toLocaleDateString()}`);

        this.emit('subscriptionCreated', subscription);

        return subscription;
    }

    calculateNextBillingDate(interval) {
        const now = new Date();

        switch (interval) {
            case 'month':
                return new Date(now.setMonth(now.getMonth() + 1)).toISOString();
            case 'year':
                return new Date(now.setFullYear(now.getFullYear() + 1)).toISOString();
            default:
                return null; // Lifetime or one-time
        }
    }

    async cancelSubscription(subscriptionId, reason = 'customer_request') {
        const subscription = this.subscriptions.get(subscriptionId);
        if (!subscription) {
            throw new Error('Subscription not found');
        }

        subscription.status = 'cancelled';
        subscription.cancelledAt = new Date().toISOString();
        subscription.cancellationReason = reason;

        await this.saveSubscriptions();

        console.log(`❌ Subscription cancelled: ${subscriptionId}`);
        console.log(`   Reason: ${reason}`);

        this.emit('subscriptionCancelled', subscription);

        return subscription;
    }

    async processRefund(paymentId, amount, reason) {
        try {
            const refund = {
                id: 'rf_' + crypto.randomBytes(16).toString('hex'),
                paymentId: paymentId,
                amount: amount,
                reason: reason,
                status: 'pending',
                requestedAt: new Date().toISOString()
            };

            // Simulate refund processing
            console.log(`💰 Processing refund: ${refund.id}`);
            console.log(`   Amount: $${amount}`);
            console.log(`   Reason: ${reason}`);

            // In real implementation, process with payment provider
            await new Promise(resolve => setTimeout(resolve, 2000));

            refund.status = 'completed';
            refund.processedAt = new Date().toISOString();

            console.log(`✅ Refund processed: ${refund.id}`);

            this.emit('refundProcessed', refund);

            return {
                success: true,
                refund: refund
            };

        } catch (error) {
            console.error('❌ Refund processing failed:', error);
            return {
                success: false,
                error: error.message
            };
        }
    }

    generatePaymentId() {
        return 'pi_' + crypto.randomBytes(16).toString('hex');
    }

    generateCustomerId(email) {
        return 'cus_' + crypto.createHash('md5').update(email).digest('hex').substring(0, 16);
    }

    generateLicenseKey(licenseType) {
        const prefix = licenseType.substring(0, 3).toUpperCase();
        const segments = [];

        for (let i = 0; i < 4; i++) {
            segments.push(crypto.randomBytes(2).toString('hex').toUpperCase());
        }

        return `${prefix}-${segments.join('-')}`;
    }

    async loadPaymentConfig() {
        // Load payment provider configurations
        console.log('📋 Loading payment configuration...');
    }

    async loadPaymentHistory() {
        try {
            const historyPath = path.join(process.cwd(), 'data', 'payment-history.json');
            const data = await fs.readFile(historyPath, 'utf8');
            this.paymentHistory = JSON.parse(data);
        } catch (error) {
            this.paymentHistory = [];
        }
    }

    async savePaymentHistory() {
        try {
            const historyPath = path.join(process.cwd(), 'data', 'payment-history.json');
            const dataDir = path.dirname(historyPath);

            try {
                await fs.access(dataDir);
            } catch {
                await fs.mkdir(dataDir, { recursive: true });
            }

            await fs.writeFile(historyPath, JSON.stringify(this.paymentHistory, null, 2));
        } catch (error) {
            console.error('❌ Failed to save payment history:', error);
        }
    }

    async loadSubscriptions() {
        try {
            const subscriptionsPath = path.join(process.cwd(), 'data', 'subscriptions.json');
            const data = await fs.readFile(subscriptionsPath, 'utf8');
            const subscriptionsArray = JSON.parse(data);

            this.subscriptions = new Map();
            subscriptionsArray.forEach(sub => {
                this.subscriptions.set(sub.id, sub);
            });
        } catch (error) {
            this.subscriptions = new Map();
        }
    }

    async saveSubscriptions() {
        try {
            const subscriptionsPath = path.join(process.cwd(), 'data', 'subscriptions.json');
            const dataDir = path.dirname(subscriptionsPath);

            try {
                await fs.access(dataDir);
            } catch {
                await fs.mkdir(dataDir, { recursive: true });
            }

            const subscriptionsArray = Array.from(this.subscriptions.values());
            await fs.writeFile(subscriptionsPath, JSON.stringify(subscriptionsArray, null, 2));
        } catch (error) {
            console.error('❌ Failed to save subscriptions:', error);
        }
    }

    getSubscriptionPlans() {
        return Object.values(this.subscriptionPlans);
    }

    getPaymentProviders() {
        return Object.entries(this.paymentProviders)
            .filter(([_, provider]) => provider.enabled)
            .map(([key, provider]) => ({
                id: key,
                name: provider.name,
                methods: provider.methods,
                currencies: provider.currencies
            }));
    }

    getPaymentHistory(customerId = null) {
        if (customerId) {
            return this.paymentHistory.filter(payment => payment.customer.id === customerId);
        }
        return this.paymentHistory;
    }

    getActiveSubscriptions(customerId = null) {
        const activeSubscriptions = Array.from(this.subscriptions.values())
            .filter(sub => sub.status === 'active');

        if (customerId) {
            return activeSubscriptions.filter(sub => sub.customerId === customerId);
        }

        return activeSubscriptions;
    }

    async generateInvoice(subscriptionId) {
        const subscription = this.subscriptions.get(subscriptionId);
        if (!subscription) {
            throw new Error('Subscription not found');
        }

        const plan = this.subscriptionPlans[subscription.planId];
        const invoice = {
            id: 'inv_' + crypto.randomBytes(16).toString('hex'),
            subscriptionId: subscriptionId,
            customerId: subscription.customerId,
            customerEmail: subscription.customerEmail,
            amount: plan.price,
            currency: plan.currency,
            description: `${plan.name} - ${new Date().toLocaleDateString()}`,
            status: 'pending',
            dueDate: subscription.nextPaymentDate,
            createdAt: new Date().toISOString(),
            lineItems: [
                {
                    description: plan.name,
                    quantity: 1,
                    unitPrice: plan.price,
                    total: plan.price
                }
            ]
        };

        console.log(`📄 Invoice generated: ${invoice.id}`);
        console.log(`   Amount: $${invoice.amount}`);
        console.log(`   Due: ${new Date(invoice.dueDate).toLocaleDateString()}`);

        return invoice;
    }
}

module.exports = PaymentService;