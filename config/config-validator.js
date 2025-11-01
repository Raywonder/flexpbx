#!/usr/bin/env node

/**
 * 🔍 FlexPBX Configuration Validator
 * Validates configurations before import to ensure only working data reaches the central PBX server
 */

const fs = require('fs');
const https = require('https');
const dgram = require('dgram');

class FlexPBXConfigValidator {
    constructor() {
        this.results = {
            valid: [],
            invalid: [],
            warnings: []
        };

        this.validationRules = {
            callcentric: {
                required: ['username', 'password', 'proxy', 'port'],
                proxy_test: true,
                sip_registration_test: false, // Skip actual SIP test for security
                format_validation: true
            },
            google_voice: {
                required: ['numbers.primary.number', 'authentication.type'],
                api_test: false, // Skip actual API test for security
                format_validation: true,
                phone_format_test: true
            },
            extensions: {
                required: ['extensions', 'server_settings'],
                extension_format_test: true,
                password_strength_test: true,
                conflict_detection: true
            }
        };
    }

    async validateAllConfigurations() {
        console.log('🔍 FlexPBX Configuration Validator');
        console.log('===================================');
        console.log('');

        const configs = [
            { file: 'callcentric-trunk-config.json', type: 'callcentric' },
            { file: 'google-voice-config.json', type: 'google_voice' },
            { file: 'extensions-config.json', type: 'extensions' }
        ];

        for (const config of configs) {
            if (fs.existsSync(config.file)) {
                await this.validateConfig(config.file, config.type);
            } else {
                this.results.warnings.push(`Config file not found: ${config.file}`);
            }
        }

        this.generateValidationReport();
        return this.results.invalid.length === 0;
    }

    async validateConfig(filename, type) {
        console.log(`🔍 Validating ${filename}...`);

        try {
            const configData = JSON.parse(fs.readFileSync(filename, 'utf8'));
            const rules = this.validationRules[type];

            if (!rules) {
                throw new Error(`No validation rules for type: ${type}`);
            }

            // Test required fields
            const missingFields = this.checkRequiredFields(configData, rules.required);
            if (missingFields.length > 0) {
                throw new Error(`Missing required fields: ${missingFields.join(', ')}`);
            }

            // Run type-specific validations
            switch (type) {
                case 'callcentric':
                    await this.validateCallcentricConfig(configData);
                    break;
                case 'google_voice':
                    await this.validateGoogleVoiceConfig(configData);
                    break;
                case 'extensions':
                    await this.validateExtensionsConfig(configData);
                    break;
            }

            this.results.valid.push({
                file: filename,
                type: type,
                status: 'valid',
                config: configData
            });

            console.log(`   ✅ ${filename} validation passed`);

        } catch (error) {
            this.results.invalid.push({
                file: filename,
                type: type,
                error: error.message,
                status: 'invalid'
            });

            console.log(`   ❌ ${filename} validation failed: ${error.message}`);
        }
    }

    checkRequiredFields(config, requiredFields) {
        const missing = [];

        for (const field of requiredFields) {
            if (field.includes('.')) {
                // Nested field check
                const parts = field.split('.');
                let current = config;
                for (const part of parts) {
                    if (!current || typeof current !== 'object' || !current.hasOwnProperty(part)) {
                        missing.push(field);
                        break;
                    }
                    current = current[part];
                }
            } else {
                // Direct field check
                if (!config.hasOwnProperty(field)) {
                    missing.push(field);
                }
            }
        }

        return missing;
    }

    async validateCallcentricConfig(config) {
        const conf = config.configuration;

        // Validate SIP proxy format
        if (!conf.registration.proxy.includes('callcentric.com')) {
            throw new Error('Invalid CallCentric proxy server');
        }

        // Validate port
        if (conf.registration.port !== 5060) {
            this.results.warnings.push('CallCentric: Non-standard port detected');
        }

        // Validate username format (should contain numbers)
        if (!/^\[?[A-Z_]*\]?\d+$/.test(conf.general.username)) {
            this.results.warnings.push('CallCentric: Username format may not match standard pattern');
        }

        // Test proxy connectivity (DNS resolution)
        try {
            await this.testDNSResolution(conf.registration.proxy);
            console.log(`     ✅ DNS resolution for ${conf.registration.proxy} successful`);
        } catch (error) {
            throw new Error(`CallCentric proxy DNS resolution failed: ${error.message}`);
        }

        // Validate codecs
        const validCodecs = ['g722', 'ulaw', 'alaw', 'g729', 'gsm'];
        const invalidCodecs = conf.audio.codecs.filter(codec => !validCodecs.includes(codec));
        if (invalidCodecs.length > 0) {
            this.results.warnings.push(`CallCentric: Unsupported codecs detected: ${invalidCodecs.join(', ')}`);
        }

        console.log('     ✅ CallCentric configuration format is valid');
    }

    async validateGoogleVoiceConfig(config) {
        // Validate phone number format
        const primaryNumber = config.configuration.numbers.primary.number;
        if (!/^1\d{10}$/.test(primaryNumber)) {
            throw new Error('Google Voice: Invalid primary phone number format');
        }

        // Validate authentication type
        const authType = config.configuration.authentication.type;
        if (authType !== 'oauth2') {
            throw new Error('Google Voice: Only OAuth2 authentication is supported');
        }

        // Validate required scopes
        const requiredScopes = [
            'https://www.googleapis.com/auth/voice',
            'https://www.googleapis.com/auth/voice.sms'
        ];

        const configScopes = config.configuration.authentication.scopes;
        const missingScopes = requiredScopes.filter(scope => !configScopes.includes(scope));
        if (missingScopes.length > 0) {
            throw new Error(`Google Voice: Missing required scopes: ${missingScopes.join(', ')}`);
        }

        // Validate rate limits
        const rateLimits = config.configuration.api_settings.rate_limits;
        if (rateLimits.calls_per_day > 10000 || rateLimits.sms_per_day > 5000) {
            this.results.warnings.push('Google Voice: Rate limits are very high, may exceed API quotas');
        }

        console.log('     ✅ Google Voice configuration format is valid');
    }

    async validateExtensionsConfig(config) {
        const extensions = config.extensions;
        const usedExtensions = new Set();
        const usedUsernames = new Set();

        // Validate each extension
        for (const [extNum, extConfig] of Object.entries(extensions)) {
            // Check extension number format
            if (!/^\d{4}$/.test(extNum)) {
                throw new Error(`Extension ${extNum}: Invalid extension number format`);
            }

            // Check for duplicates
            if (usedExtensions.has(extNum)) {
                throw new Error(`Extension ${extNum}: Duplicate extension number`);
            }
            usedExtensions.add(extNum);

            // Check username uniqueness
            if (usedUsernames.has(extConfig.username)) {
                throw new Error(`Extension ${extNum}: Duplicate username '${extConfig.username}'`);
            }
            usedUsernames.add(extConfig.username);

            // Validate password strength
            if (!this.validatePasswordStrength(extConfig.password)) {
                throw new Error(`Extension ${extNum}: Password does not meet security requirements`);
            }

            // Validate display name
            if (!extConfig.display_name || extConfig.display_name.trim().length === 0) {
                throw new Error(`Extension ${extNum}: Display name is required`);
            }
        }

        // Validate server settings
        const serverSettings = config.server_settings;
        if (!serverSettings.sip_domain) {
            throw new Error('Server settings: SIP domain is required');
        }

        // Validate port range
        if (serverSettings.sip_port < 1024 || serverSettings.sip_port > 65535) {
            throw new Error('Server settings: SIP port must be between 1024 and 65535');
        }

        // Validate RTP port range
        if (serverSettings.rtp_start >= serverSettings.rtp_end) {
            throw new Error('Server settings: RTP start port must be less than end port');
        }

        console.log(`     ✅ Extensions configuration is valid (${Object.keys(extensions).length} extensions)`);
    }

    validatePasswordStrength(password) {
        // Minimum 8 characters, at least one letter, one number, one special character
        const strongPassword = /^(?=.*[A-Za-z])(?=.*\d)(?=.*[!@#$%^&*])[A-Za-z\d!@#$%^&*]{8,}$/;
        return strongPassword.test(password);
    }

    async testDNSResolution(hostname) {
        return new Promise((resolve, reject) => {
            const dns = require('dns');
            dns.lookup(hostname, (err, address) => {
                if (err) {
                    reject(err);
                } else {
                    resolve(address);
                }
            });
        });
    }

    generateValidationReport() {
        console.log('');
        console.log('📊 Validation Report');
        console.log('====================');
        console.log('');

        console.log(`✅ Valid Configurations: ${this.results.valid.length}`);
        this.results.valid.forEach(item => {
            console.log(`   • ${item.file} (${item.type})`);
        });

        if (this.results.invalid.length > 0) {
            console.log('');
            console.log(`❌ Invalid Configurations: ${this.results.invalid.length}`);
            this.results.invalid.forEach(item => {
                console.log(`   • ${item.file}: ${item.error}`);
            });
        }

        if (this.results.warnings.length > 0) {
            console.log('');
            console.log(`⚠️  Warnings: ${this.results.warnings.length}`);
            this.results.warnings.forEach(warning => {
                console.log(`   • ${warning}`);
            });
        }

        console.log('');
        if (this.results.invalid.length === 0) {
            console.log('🎯 All configurations are valid and ready for import!');
            console.log('');
            console.log('Next steps:');
            console.log('1. Run: chmod +x flexpbx-server-setup.sh');
            console.log('2. Execute: ./flexpbx-server-setup.sh');
            console.log('3. Or use: node file-manager-import.js');
        } else {
            console.log('❌ Fix invalid configurations before importing to FlexPBX server');
        }

        // Save validation results
        fs.writeFileSync('validation-report.json', JSON.stringify(this.results, null, 2));
        console.log('');
        console.log('📋 Detailed report saved to: validation-report.json');
    }

    exportValidConfigurations() {
        // Create a production-ready package with only valid configurations
        const validConfigs = {};

        this.results.valid.forEach(item => {
            validConfigs[item.type] = item.config;
        });

        if (Object.keys(validConfigs).length > 0) {
            fs.writeFileSync('production-ready-configs.json', JSON.stringify(validConfigs, null, 2));
            console.log('✅ Production-ready configurations exported to: production-ready-configs.json');
        }

        return validConfigs;
    }
}

// Run validation if called directly
if (require.main === module) {
    const validator = new FlexPBXConfigValidator();
    validator.validateAllConfigurations().then(isValid => {
        if (isValid) {
            validator.exportValidConfigurations();
            console.log('');
            console.log('🚀 Ready to import to FlexPBX central server!');
            process.exit(0);
        } else {
            console.log('');
            console.log('❌ Configuration validation failed');
            process.exit(1);
        }
    }).catch(error => {
        console.error('Validation error:', error);
        process.exit(1);
    });
}

module.exports = FlexPBXConfigValidator;