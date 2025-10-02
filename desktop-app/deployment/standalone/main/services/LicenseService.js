/**
 * 🔑 FlexPBX License Service
 * Manages licensing, activation, and validation for FlexPBX
 */

const crypto = require('crypto');
const fs = require('fs').promises;
const path = require('path');
const EventEmitter = require('events');
const os = require('os');

class LicenseService extends EventEmitter {
    constructor() {
        super();

        this.licenseTypes = {
            TRIAL: {
                name: 'Trial',
                duration: 30, // days
                features: {
                    maxExtensions: 5,
                    maxConcurrentCalls: 2,
                    callRecording: false,
                    advancedRouting: false,
                    apiAccess: false,
                    support: 'community',
                    googleVoice: true,
                    callCentric: true
                },
                price: 0
            },
            BASIC: {
                name: 'Basic',
                duration: 365, // days
                features: {
                    maxExtensions: 20,
                    maxConcurrentCalls: 10,
                    callRecording: true,
                    advancedRouting: true,
                    apiAccess: false,
                    support: 'email',
                    googleVoice: true,
                    callCentric: true
                },
                price: 49.99
            },
            PROFESSIONAL: {
                name: 'Professional',
                duration: 365,
                features: {
                    maxExtensions: 100,
                    maxConcurrentCalls: 50,
                    callRecording: true,
                    advancedRouting: true,
                    apiAccess: true,
                    support: 'priority',
                    googleVoice: true,
                    callCentric: true,
                    multiTenant: true
                },
                price: 199.99
            },
            ENTERPRISE: {
                name: 'Enterprise',
                duration: 365,
                features: {
                    maxExtensions: -1, // unlimited
                    maxConcurrentCalls: -1, // unlimited
                    callRecording: true,
                    advancedRouting: true,
                    apiAccess: true,
                    support: '24/7',
                    googleVoice: true,
                    callCentric: true,
                    multiTenant: true,
                    whiteLabel: true,
                    customIntegrations: true
                },
                price: 999.99
            },
            LIFETIME: {
                name: 'Lifetime',
                duration: -1, // never expires
                features: {
                    maxExtensions: -1,
                    maxConcurrentCalls: -1,
                    callRecording: true,
                    advancedRouting: true,
                    apiAccess: true,
                    support: 'priority',
                    googleVoice: true,
                    callCentric: true,
                    multiTenant: true,
                    whiteLabel: true,
                    customIntegrations: true,
                    sourceCodeAccess: true
                },
                price: 2999.99
            }
        };

        this.licensePath = path.join(process.cwd(), 'license', 'flexpbx.license');
        this.currentLicense = null;
        this.hardwareId = this.generateHardwareId();

        console.log('🔑 FlexPBX License Service initialized');
    }

    async initialize() {
        try {
            // Ensure license directory exists
            const licenseDir = path.dirname(this.licensePath);
            try {
                await fs.access(licenseDir);
            } catch {
                await fs.mkdir(licenseDir, { recursive: true });
            }

            // Load existing license
            const hasLicense = await this.loadLicense();

            if (!hasLicense) {
                console.log('⚠️ No license found - starting trial mode');
                await this.startTrial();
            } else {
                const validation = await this.validateLicense();
                if (!validation.valid) {
                    console.log(`❌ License validation failed: ${validation.reason}`);
                    await this.startTrial();
                } else {
                    console.log(`✅ License validated: ${this.currentLicense.type} (${this.getDaysRemaining()} days remaining)`);
                }
            }

            // Start license monitoring
            this.startLicenseMonitoring();

            return true;
        } catch (error) {
            console.error('❌ License initialization failed:', error);
            return false;
        }
    }

    generateHardwareId() {
        // Generate unique hardware ID based on system info
        const cpus = os.cpus();
        const networkInterfaces = os.networkInterfaces();

        const data = {
            platform: os.platform(),
            arch: os.arch(),
            hostname: os.hostname(),
            cpuModel: cpus[0]?.model || 'unknown',
            totalMemory: os.totalmem(),
            macAddress: this.getMacAddress(networkInterfaces)
        };

        return crypto
            .createHash('sha256')
            .update(JSON.stringify(data))
            .digest('hex')
            .substring(0, 16);
    }

    getMacAddress(networkInterfaces) {
        for (const interfaceName in networkInterfaces) {
            const interfaces = networkInterfaces[interfaceName];
            for (const iface of interfaces) {
                if (!iface.internal && iface.mac && iface.mac !== '00:00:00:00:00:00') {
                    return iface.mac;
                }
            }
        }
        return '00:00:00:00:00:00';
    }

    async startTrial() {
        const trialLicense = {
            id: this.generateLicenseKey('TRIAL'),
            type: 'TRIAL',
            customerId: 'trial-' + crypto.randomBytes(8).toString('hex'),
            customerEmail: 'trial@flexpbx.local',
            issuedDate: new Date().toISOString(),
            expiryDate: new Date(Date.now() + (30 * 24 * 60 * 60 * 1000)).toISOString(),
            hardwareId: this.hardwareId,
            features: this.licenseTypes.TRIAL.features,
            signature: null
        };

        // Sign the license
        trialLicense.signature = this.signLicense(trialLicense);

        // Save trial license
        await this.saveLicense(trialLicense);
        this.currentLicense = trialLicense;

        console.log('🎁 30-day trial started');
        console.log(`   License Key: ${trialLicense.id}`);
        console.log(`   Expires: ${new Date(trialLicense.expiryDate).toLocaleDateString()}`);

        this.emit('trialStarted', trialLicense);

        return trialLicense;
    }

    generateLicenseKey(type = 'BASIC') {
        const prefix = type.substring(0, 3).toUpperCase();
        const segments = [];

        for (let i = 0; i < 4; i++) {
            segments.push(crypto.randomBytes(2).toString('hex').toUpperCase());
        }

        return `${prefix}-${segments.join('-')}`;
    }

    signLicense(licenseData) {
        // Create signature for license validation
        const dataToSign = {
            id: licenseData.id,
            type: licenseData.type,
            customerId: licenseData.customerId,
            expiryDate: licenseData.expiryDate,
            hardwareId: licenseData.hardwareId
        };

        const secret = 'FlexPBX-Secret-2024'; // In production, use secure key management

        return crypto
            .createHmac('sha256', secret)
            .update(JSON.stringify(dataToSign))
            .digest('hex');
    }

    async activateLicense(licenseKey, customerEmail) {
        try {
            console.log(`🔑 Activating license: ${licenseKey}`);

            // Parse license type from key
            const typePrefix = licenseKey.substring(0, 3);
            let licenseType = 'BASIC';

            switch (typePrefix) {
                case 'TRI': licenseType = 'TRIAL'; break;
                case 'BAS': licenseType = 'BASIC'; break;
                case 'PRO': licenseType = 'PROFESSIONAL'; break;
                case 'ENT': licenseType = 'ENTERPRISE'; break;
                case 'LIF': licenseType = 'LIFETIME'; break;
            }

            const licenseInfo = this.licenseTypes[licenseType];

            // Create license object
            const license = {
                id: licenseKey,
                type: licenseType,
                customerId: crypto.createHash('md5').update(customerEmail).digest('hex'),
                customerEmail: customerEmail,
                issuedDate: new Date().toISOString(),
                expiryDate: licenseInfo.duration === -1
                    ? null
                    : new Date(Date.now() + (licenseInfo.duration * 24 * 60 * 60 * 1000)).toISOString(),
                hardwareId: this.hardwareId,
                features: licenseInfo.features,
                signature: null
            };

            // Sign the license
            license.signature = this.signLicense(license);

            // Save the license
            await this.saveLicense(license);
            this.currentLicense = license;

            console.log(`✅ License activated: ${licenseType}`);
            console.log(`   Customer: ${customerEmail}`);
            console.log(`   Features: ${Object.keys(licenseInfo.features).filter(f => licenseInfo.features[f]).join(', ')}`);

            this.emit('licenseActivated', license);

            return {
                success: true,
                license: license,
                message: `${licenseType} license activated successfully`
            };

        } catch (error) {
            console.error('❌ License activation failed:', error);
            return {
                success: false,
                error: error.message
            };
        }
    }

    async validateLicense() {
        if (!this.currentLicense) {
            return {
                valid: false,
                reason: 'No license found'
            };
        }

        // Check signature
        const expectedSignature = this.signLicense(this.currentLicense);
        if (this.currentLicense.signature !== expectedSignature) {
            return {
                valid: false,
                reason: 'Invalid license signature'
            };
        }

        // Check hardware ID
        if (this.currentLicense.hardwareId !== this.hardwareId) {
            return {
                valid: false,
                reason: 'License not valid for this hardware'
            };
        }

        // Check expiry
        if (this.currentLicense.expiryDate) {
            const expiryDate = new Date(this.currentLicense.expiryDate);
            if (expiryDate < new Date()) {
                return {
                    valid: false,
                    reason: 'License expired'
                };
            }
        }

        return {
            valid: true,
            license: this.currentLicense
        };
    }

    async saveLicense(license) {
        try {
            await fs.writeFile(
                this.licensePath,
                JSON.stringify(license, null, 2),
                'utf8'
            );
            console.log(`💾 License saved: ${this.licensePath}`);
            return true;
        } catch (error) {
            console.error('❌ Failed to save license:', error);
            return false;
        }
    }

    async loadLicense() {
        try {
            const data = await fs.readFile(this.licensePath, 'utf8');
            this.currentLicense = JSON.parse(data);
            return true;
        } catch (error) {
            return false;
        }
    }

    getDaysRemaining() {
        if (!this.currentLicense || !this.currentLicense.expiryDate) {
            return -1; // Lifetime license
        }

        const expiryDate = new Date(this.currentLicense.expiryDate);
        const now = new Date();
        const daysRemaining = Math.ceil((expiryDate - now) / (1000 * 60 * 60 * 24));

        return Math.max(0, daysRemaining);
    }

    checkFeature(featureName) {
        if (!this.currentLicense || !this.currentLicense.features) {
            return false;
        }

        const feature = this.currentLicense.features[featureName];

        // For numeric limits
        if (typeof feature === 'number') {
            return feature;
        }

        // For boolean features
        return !!feature;
    }

    async checkExtensionLimit(currentExtensions) {
        const maxExtensions = this.checkFeature('maxExtensions');

        if (maxExtensions === -1) {
            return { allowed: true }; // Unlimited
        }

        if (currentExtensions >= maxExtensions) {
            return {
                allowed: false,
                reason: `Extension limit reached (${maxExtensions} max)`,
                suggestion: 'Upgrade your license for more extensions'
            };
        }

        return { allowed: true };
    }

    async checkCallLimit(currentCalls) {
        const maxCalls = this.checkFeature('maxConcurrentCalls');

        if (maxCalls === -1) {
            return { allowed: true }; // Unlimited
        }

        if (currentCalls >= maxCalls) {
            return {
                allowed: false,
                reason: `Concurrent call limit reached (${maxCalls} max)`,
                suggestion: 'Upgrade your license for more concurrent calls'
            };
        }

        return { allowed: true };
    }

    startLicenseMonitoring() {
        // Check license daily
        setInterval(async () => {
            const validation = await this.validateLicense();

            if (!validation.valid) {
                console.log(`⚠️ License validation failed: ${validation.reason}`);
                this.emit('licenseInvalid', validation);
            }

            // Warn about expiry
            const daysRemaining = this.getDaysRemaining();
            if (daysRemaining > 0 && daysRemaining <= 7) {
                console.log(`⚠️ License expires in ${daysRemaining} days`);
                this.emit('licenseExpiringSoon', { daysRemaining });
            }

        }, 24 * 60 * 60 * 1000); // Check every 24 hours
    }

    async revokeLicense() {
        try {
            await fs.unlink(this.licensePath);
            this.currentLicense = null;
            console.log('🗑️ License revoked');
            this.emit('licenseRevoked');
            return true;
        } catch (error) {
            console.error('❌ Failed to revoke license:', error);
            return false;
        }
    }

    getLicenseInfo() {
        if (!this.currentLicense) {
            return {
                status: 'No License',
                type: 'None',
                features: {}
            };
        }

        return {
            status: 'Active',
            type: this.currentLicense.type,
            licenseKey: this.currentLicense.id,
            customer: this.currentLicense.customerEmail,
            issuedDate: this.currentLicense.issuedDate,
            expiryDate: this.currentLicense.expiryDate,
            daysRemaining: this.getDaysRemaining(),
            features: this.currentLicense.features,
            hardwareId: this.hardwareId
        };
    }

    async exportLicense() {
        if (!this.currentLicense) {
            throw new Error('No license to export');
        }

        const exportData = {
            ...this.currentLicense,
            exportDate: new Date().toISOString(),
            systemInfo: {
                platform: os.platform(),
                hostname: os.hostname(),
                hardwareId: this.hardwareId
            }
        };

        const exportPath = path.join(process.cwd(), 'license', `flexpbx-license-backup-${Date.now()}.json`);
        await fs.writeFile(exportPath, JSON.stringify(exportData, null, 2));

        console.log(`📤 License exported: ${exportPath}`);
        return exportPath;
    }

    async importLicense(licensePath) {
        try {
            const data = await fs.readFile(licensePath, 'utf8');
            const importedLicense = JSON.parse(data);

            // Validate imported license
            const validation = await this.validateLicense();
            if (!validation.valid) {
                throw new Error(`Invalid license: ${validation.reason}`);
            }

            // Save as current license
            await this.saveLicense(importedLicense);
            this.currentLicense = importedLicense;

            console.log('📥 License imported successfully');
            return true;

        } catch (error) {
            console.error('❌ License import failed:', error);
            return false;
        }
    }
}

module.exports = LicenseService;