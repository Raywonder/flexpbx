/**
 * 📅 FlexPBX Subscription Service
 * Manages recurring subscriptions, billing cycles, and license renewals
 */

const EventEmitter = require('events');
const crypto = require('crypto');
const fs = require('fs').promises;
const path = require('path');

class SubscriptionService extends EventEmitter {
    constructor(licenseService, paymentService) {
        super();

        this.licenseService = licenseService;
        this.paymentService = paymentService;

        this.billingCycles = {
            MONTHLY: {
                name: 'Monthly',
                intervalDays: 30,
                renewalWarningDays: 7,
                gracePeriodDays: 3
            },
            QUARTERLY: {
                name: 'Quarterly',
                intervalDays: 90,
                renewalWarningDays: 14,
                gracePeriodDays: 7
            },
            YEARLY: {
                name: 'Yearly',
                intervalDays: 365,
                renewalWarningDays: 30,
                gracePeriodDays: 15
            },
            LIFETIME: {
                name: 'Lifetime',
                intervalDays: -1,
                renewalWarningDays: 0,
                gracePeriodDays: 0
            }
        };

        this.subscriptions = new Map();
        this.renewalQueue = [];
        this.billingHistory = [];

        // Auto-renewal monitoring
        this.renewalCheckInterval = null;

        console.log('📅 FlexPBX Subscription Service initialized');
    }

    async initialize() {
        try {
            // Load existing subscriptions
            await this.loadSubscriptions();

            // Start renewal monitoring
            this.startRenewalMonitoring();

            // Check for overdue renewals
            await this.checkOverdueRenewals();

            console.log('✅ Subscription service ready');
            console.log(`   Active subscriptions: ${this.getActiveSubscriptionCount()}`);

            return true;

        } catch (error) {
            console.error('❌ Subscription service initialization failed:', error);
            return false;
        }
    }

    async createSubscription(customerId, planId, billingCycle = 'YEARLY', autoRenewal = true) {
        try {
            const cycle = this.billingCycles[billingCycle];
            if (!cycle) {
                throw new Error(`Invalid billing cycle: ${billingCycle}`);
            }

            const startDate = new Date();
            const nextRenewalDate = cycle.intervalDays === -1
                ? null
                : new Date(startDate.getTime() + (cycle.intervalDays * 24 * 60 * 60 * 1000));

            const subscription = {
                id: this.generateSubscriptionId(),
                customerId: customerId,
                planId: planId,
                billingCycle: billingCycle,
                status: 'active',
                autoRenewal: autoRenewal,
                startDate: startDate.toISOString(),
                nextRenewalDate: nextRenewalDate ? nextRenewalDate.toISOString() : null,
                currentPeriodStart: startDate.toISOString(),
                currentPeriodEnd: nextRenewalDate ? nextRenewalDate.toISOString() : null,
                renewalCount: 0,
                totalPaid: 0,
                createdAt: new Date().toISOString(),
                updatedAt: new Date().toISOString(),
                metadata: {
                    source: 'subscription_service',
                    autoCreated: false
                }
            };

            this.subscriptions.set(subscription.id, subscription);
            await this.saveSubscriptions();

            console.log(`📅 Subscription created: ${subscription.id}`);
            console.log(`   Customer: ${customerId}`);
            console.log(`   Plan: ${planId}`);
            console.log(`   Billing: ${billingCycle}`);
            console.log(`   Next renewal: ${nextRenewalDate ? nextRenewalDate.toLocaleDateString() : 'Never (Lifetime)'}`);

            this.emit('subscriptionCreated', subscription);

            return {
                success: true,
                subscription: subscription
            };

        } catch (error) {
            console.error('❌ Subscription creation failed:', error);
            return {
                success: false,
                error: error.message
            };
        }
    }

    async renewSubscription(subscriptionId, paymentDetails = null) {
        try {
            const subscription = this.subscriptions.get(subscriptionId);
            if (!subscription) {
                throw new Error('Subscription not found');
            }

            if (subscription.status !== 'active') {
                throw new Error(`Cannot renew subscription with status: ${subscription.status}`);
            }

            const cycle = this.billingCycles[subscription.billingCycle];
            if (cycle.intervalDays === -1) {
                throw new Error('Lifetime subscriptions do not require renewal');
            }

            console.log(`🔄 Renewing subscription: ${subscriptionId}`);

            // Process payment if auto-renewal is enabled
            let paymentResult = { success: true };
            if (subscription.autoRenewal && !paymentDetails) {
                // Get stored payment method and process renewal payment
                paymentResult = await this.processRenewalPayment(subscription);
            } else if (paymentDetails) {
                // Process provided payment details
                paymentResult = await this.paymentService.processPayment(paymentDetails.paymentIntentId, paymentDetails);
            }

            if (!paymentResult.success) {
                // Mark subscription as overdue
                subscription.status = 'past_due';
                subscription.overdueDate = new Date().toISOString();
                await this.saveSubscriptions();

                console.log(`⚠️ Subscription renewal failed: ${subscriptionId}`);
                this.emit('subscriptionRenewalFailed', subscription, paymentResult.error);

                return {
                    success: false,
                    error: 'Payment failed',
                    subscription: subscription
                };
            }

            // Update subscription for new period
            const currentPeriodEnd = new Date(subscription.currentPeriodEnd);
            const newPeriodEnd = new Date(currentPeriodEnd.getTime() + (cycle.intervalDays * 24 * 60 * 60 * 1000));

            subscription.currentPeriodStart = subscription.currentPeriodEnd;
            subscription.currentPeriodEnd = newPeriodEnd.toISOString();
            subscription.nextRenewalDate = newPeriodEnd.toISOString();
            subscription.renewalCount += 1;
            subscription.lastRenewalDate = new Date().toISOString();
            subscription.updatedAt = new Date().toISOString();

            // Clear overdue status if applicable
            if (subscription.status === 'past_due') {
                subscription.status = 'active';
                delete subscription.overdueDate;
            }

            // Generate new license for the renewed period
            const licenseResult = await this.generateRenewalLicense(subscription);
            if (licenseResult.success) {
                subscription.currentLicenseKey = licenseResult.licenseKey;
            }

            await this.saveSubscriptions();

            // Record billing event
            await this.recordBillingEvent(subscription, 'renewal', paymentResult);

            console.log(`✅ Subscription renewed: ${subscriptionId}`);
            console.log(`   New period: ${new Date(subscription.currentPeriodStart).toLocaleDateString()} - ${new Date(subscription.currentPeriodEnd).toLocaleDateString()}`);
            console.log(`   Renewal count: ${subscription.renewalCount}`);

            this.emit('subscriptionRenewed', subscription);

            return {
                success: true,
                subscription: subscription,
                licenseKey: licenseResult.licenseKey
            };

        } catch (error) {
            console.error('❌ Subscription renewal failed:', error);
            return {
                success: false,
                error: error.message
            };
        }
    }

    async processRenewalPayment(subscription) {
        // Simulate processing stored payment method
        console.log(`💳 Processing renewal payment for subscription: ${subscription.id}`);

        try {
            // Get plan pricing
            const plan = this.paymentService.subscriptionPlans[subscription.planId];
            if (!plan) {
                throw new Error('Plan not found');
            }

            // Simulate payment processing
            await new Promise(resolve => setTimeout(resolve, 1000));

            const paymentResult = {
                success: true,
                transactionId: 'txn_renewal_' + crypto.randomBytes(8).toString('hex'),
                amount: plan.price,
                currency: plan.currency,
                processedAt: new Date().toISOString()
            };

            subscription.totalPaid += plan.price;

            console.log(`✅ Renewal payment processed: ${paymentResult.transactionId}`);
            console.log(`   Amount: $${paymentResult.amount}`);

            return paymentResult;

        } catch (error) {
            console.error('❌ Renewal payment failed:', error);
            return {
                success: false,
                error: error.message
            };
        }
    }

    async generateRenewalLicense(subscription) {
        try {
            // Get plan information to determine license type
            const plan = this.paymentService.subscriptionPlans[subscription.planId];
            if (!plan) {
                throw new Error('Plan not found');
            }

            // Generate new license key
            const licenseKey = this.generateLicenseKey(plan.licenseType);

            // Get customer info
            const customerEmail = subscription.customerEmail || `customer-${subscription.customerId}@flexpbx.local`;

            // Activate license for the renewed period
            const activationResult = await this.licenseService.activateLicense(licenseKey, customerEmail);

            if (activationResult.success) {
                console.log(`🔑 Renewal license generated: ${licenseKey}`);
                return {
                    success: true,
                    licenseKey: licenseKey
                };
            } else {
                throw new Error('License activation failed');
            }

        } catch (error) {
            console.error('❌ Renewal license generation failed:', error);
            return {
                success: false,
                error: error.message
            };
        }
    }

    async cancelSubscription(subscriptionId, reason = 'customer_request', effectiveDate = null) {
        try {
            const subscription = this.subscriptions.get(subscriptionId);
            if (!subscription) {
                throw new Error('Subscription not found');
            }

            const cancellationDate = effectiveDate ? new Date(effectiveDate) : new Date();

            subscription.status = 'cancelled';
            subscription.cancelledAt = new Date().toISOString();
            subscription.cancellationReason = reason;
            subscription.effectiveCancellationDate = cancellationDate.toISOString();
            subscription.autoRenewal = false;
            subscription.updatedAt = new Date().toISOString();

            // If cancelling immediately, update current period end
            if (!effectiveDate || cancellationDate <= new Date()) {
                subscription.currentPeriodEnd = new Date().toISOString();
            }

            await this.saveSubscriptions();

            console.log(`❌ Subscription cancelled: ${subscriptionId}`);
            console.log(`   Reason: ${reason}`);
            console.log(`   Effective: ${cancellationDate.toLocaleDateString()}`);

            this.emit('subscriptionCancelled', subscription);

            return {
                success: true,
                subscription: subscription
            };

        } catch (error) {
            console.error('❌ Subscription cancellation failed:', error);
            return {
                success: false,
                error: error.message
            };
        }
    }

    async pauseSubscription(subscriptionId, pauseDuration) {
        try {
            const subscription = this.subscriptions.get(subscriptionId);
            if (!subscription) {
                throw new Error('Subscription not found');
            }

            if (subscription.status !== 'active') {
                throw new Error(`Cannot pause subscription with status: ${subscription.status}`);
            }

            const pauseEndDate = new Date(Date.now() + pauseDuration);

            subscription.status = 'paused';
            subscription.pausedAt = new Date().toISOString();
            subscription.pauseEndDate = pauseEndDate.toISOString();
            subscription.autoRenewal = false; // Disable auto-renewal while paused
            subscription.updatedAt = new Date().toISOString();

            // Extend next renewal date by pause duration
            if (subscription.nextRenewalDate) {
                const nextRenewal = new Date(subscription.nextRenewalDate);
                nextRenewal.setTime(nextRenewal.getTime() + pauseDuration);
                subscription.nextRenewalDate = nextRenewal.toISOString();
            }

            await this.saveSubscriptions();

            console.log(`⏸️ Subscription paused: ${subscriptionId}`);
            console.log(`   Resume date: ${pauseEndDate.toLocaleDateString()}`);

            this.emit('subscriptionPaused', subscription);

            return {
                success: true,
                subscription: subscription
            };

        } catch (error) {
            console.error('❌ Subscription pause failed:', error);
            return {
                success: false,
                error: error.message
            };
        }
    }

    async resumeSubscription(subscriptionId) {
        try {
            const subscription = this.subscriptions.get(subscriptionId);
            if (!subscription) {
                throw new Error('Subscription not found');
            }

            if (subscription.status !== 'paused') {
                throw new Error(`Cannot resume subscription with status: ${subscription.status}`);
            }

            subscription.status = 'active';
            subscription.resumedAt = new Date().toISOString();
            subscription.autoRenewal = true; // Re-enable auto-renewal
            subscription.updatedAt = new Date().toISOString();

            // Clear pause-related fields
            delete subscription.pausedAt;
            delete subscription.pauseEndDate;

            await this.saveSubscriptions();

            console.log(`▶️ Subscription resumed: ${subscriptionId}`);

            this.emit('subscriptionResumed', subscription);

            return {
                success: true,
                subscription: subscription
            };

        } catch (error) {
            console.error('❌ Subscription resume failed:', error);
            return {
                success: false,
                error: error.message
            };
        }
    }

    startRenewalMonitoring() {
        // Check for renewals every hour
        this.renewalCheckInterval = setInterval(async () => {
            await this.checkUpcomingRenewals();
            await this.checkOverdueRenewals();
            await this.processPausedSubscriptions();
        }, 60 * 60 * 1000); // Every hour

        console.log('⏰ Renewal monitoring started');
    }

    async checkUpcomingRenewals() {
        const now = new Date();
        const upcoming = [];

        for (const subscription of this.subscriptions.values()) {
            if (subscription.status !== 'active' || !subscription.nextRenewalDate) {
                continue;
            }

            const renewalDate = new Date(subscription.nextRenewalDate);
            const daysUntilRenewal = Math.ceil((renewalDate - now) / (1000 * 60 * 60 * 24));

            const cycle = this.billingCycles[subscription.billingCycle];
            if (daysUntilRenewal <= cycle.renewalWarningDays && daysUntilRenewal > 0) {
                upcoming.push({
                    subscription,
                    daysUntilRenewal
                });
            }

            // Auto-renew if due
            if (daysUntilRenewal <= 0 && subscription.autoRenewal) {
                console.log(`🔄 Auto-renewing subscription: ${subscription.id}`);
                await this.renewSubscription(subscription.id);
            }
        }

        if (upcoming.length > 0) {
            console.log(`⚠️ ${upcoming.length} subscriptions need renewal soon`);
            this.emit('upcomingRenewals', upcoming);
        }

        return upcoming;
    }

    async checkOverdueRenewals() {
        const now = new Date();
        const overdue = [];

        for (const subscription of this.subscriptions.values()) {
            if (subscription.status === 'active' && subscription.nextRenewalDate) {
                const renewalDate = new Date(subscription.nextRenewalDate);
                const daysOverdue = Math.ceil((now - renewalDate) / (1000 * 60 * 60 * 24));

                const cycle = this.billingCycles[subscription.billingCycle];
                if (daysOverdue > 0) {
                    if (daysOverdue <= cycle.gracePeriodDays) {
                        // Still in grace period
                        subscription.status = 'past_due';
                        overdue.push({
                            subscription,
                            daysOverdue,
                            inGracePeriod: true
                        });
                    } else {
                        // Grace period expired - suspend subscription
                        subscription.status = 'suspended';
                        subscription.suspendedAt = new Date().toISOString();
                        subscription.suspensionReason = 'payment_overdue';
                        overdue.push({
                            subscription,
                            daysOverdue,
                            inGracePeriod: false
                        });
                    }
                }
            }
        }

        if (overdue.length > 0) {
            await this.saveSubscriptions();
            console.log(`❌ ${overdue.length} subscriptions overdue`);
            this.emit('overdueRenewals', overdue);
        }

        return overdue;
    }

    async processPausedSubscriptions() {
        const now = new Date();
        let resumed = 0;

        for (const subscription of this.subscriptions.values()) {
            if (subscription.status === 'paused' && subscription.pauseEndDate) {
                const pauseEndDate = new Date(subscription.pauseEndDate);
                if (now >= pauseEndDate) {
                    await this.resumeSubscription(subscription.id);
                    resumed++;
                }
            }
        }

        if (resumed > 0) {
            console.log(`▶️ ${resumed} subscriptions auto-resumed`);
        }
    }

    async recordBillingEvent(subscription, eventType, paymentResult) {
        const billingEvent = {
            id: 'billing_' + crypto.randomBytes(8).toString('hex'),
            subscriptionId: subscription.id,
            customerId: subscription.customerId,
            eventType: eventType, // 'renewal', 'cancellation', 'pause', 'resume'
            amount: paymentResult.amount || 0,
            currency: paymentResult.currency || 'USD',
            transactionId: paymentResult.transactionId,
            timestamp: new Date().toISOString(),
            periodStart: subscription.currentPeriodStart,
            periodEnd: subscription.currentPeriodEnd,
            metadata: {
                renewalCount: subscription.renewalCount,
                billingCycle: subscription.billingCycle
            }
        };

        this.billingHistory.push(billingEvent);
        await this.saveBillingHistory();

        console.log(`📋 Billing event recorded: ${eventType} for ${subscription.id}`);
    }

    generateSubscriptionId() {
        return 'sub_' + crypto.randomBytes(16).toString('hex');
    }

    generateLicenseKey(licenseType) {
        const prefix = licenseType.substring(0, 3).toUpperCase();
        const segments = [];

        for (let i = 0; i < 4; i++) {
            segments.push(crypto.randomBytes(2).toString('hex').toUpperCase());
        }

        return `${prefix}-${segments.join('-')}`;
    }

    // Data persistence methods
    async loadSubscriptions() {
        try {
            const subscriptionsPath = path.join(process.cwd(), 'data', 'subscriptions.json');
            const data = await fs.readFile(subscriptionsPath, 'utf8');
            const subscriptionsArray = JSON.parse(data);

            this.subscriptions = new Map();
            subscriptionsArray.forEach(sub => {
                this.subscriptions.set(sub.id, sub);
            });

            console.log(`📥 Loaded ${subscriptionsArray.length} subscriptions`);
        } catch (error) {
            this.subscriptions = new Map();
            console.log('📄 No existing subscriptions found');
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

    async saveBillingHistory() {
        try {
            const historyPath = path.join(process.cwd(), 'data', 'billing-history.json');
            const dataDir = path.dirname(historyPath);

            try {
                await fs.access(dataDir);
            } catch {
                await fs.mkdir(dataDir, { recursive: true });
            }

            await fs.writeFile(historyPath, JSON.stringify(this.billingHistory, null, 2));
        } catch (error) {
            console.error('❌ Failed to save billing history:', error);
        }
    }

    // Query methods
    getActiveSubscriptionCount() {
        return Array.from(this.subscriptions.values())
            .filter(sub => sub.status === 'active').length;
    }

    getSubscriptionsByCustomer(customerId) {
        return Array.from(this.subscriptions.values())
            .filter(sub => sub.customerId === customerId);
    }

    getSubscriptionsByStatus(status) {
        return Array.from(this.subscriptions.values())
            .filter(sub => sub.status === status);
    }

    getSubscriptionStats() {
        const subscriptions = Array.from(this.subscriptions.values());
        const stats = {
            total: subscriptions.length,
            active: 0,
            paused: 0,
            cancelled: 0,
            past_due: 0,
            suspended: 0,
            totalRevenue: 0
        };

        subscriptions.forEach(sub => {
            stats[sub.status] = (stats[sub.status] || 0) + 1;
            stats.totalRevenue += sub.totalPaid || 0;
        });

        return stats;
    }

    cleanup() {
        if (this.renewalCheckInterval) {
            clearInterval(this.renewalCheckInterval);
            this.renewalCheckInterval = null;
            console.log('⏰ Renewal monitoring stopped');
        }
    }
}

module.exports = SubscriptionService;