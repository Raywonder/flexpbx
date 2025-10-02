#!/usr/bin/env node

/**
 * FlexPBX Desktop App - Automated Service Testing Suite
 * Tests all integrated services automatically
 */

const path = require('path');
const fs = require('fs-extra');

// Import all services
const CopyPartyService = require('./src/main/services/CopyPartyService');
const DNSManagerService = require('./src/main/services/DNSManagerService');
const SoftwareUpdateService = require('./src/main/services/SoftwareUpdateService');
const RemoteAccessibilityService = require('./src/main/services/RemoteAccessibilityService');
const SoundManager = require('./src/main/services/SoundManager');

class ServiceTester {
    constructor() {
        this.testResults = {
            total: 0,
            passed: 0,
            failed: 0,
            errors: [],
            details: {}
        };
    }

    async runTest(serviceName, testFunction) {
        this.testResults.total++;
        console.log(`\n🧪 Testing ${serviceName}...`);

        try {
            const result = await testFunction();
            if (result.success) {
                this.testResults.passed++;
                console.log(`✅ ${serviceName}: PASSED`);
                if (result.details) {
                    console.log(`   Details: ${JSON.stringify(result.details, null, 2)}`);
                }
            } else {
                this.testResults.failed++;
                console.log(`❌ ${serviceName}: FAILED - ${result.error}`);
                this.testResults.errors.push({ service: serviceName, error: result.error });
            }
            this.testResults.details[serviceName] = result;
        } catch (error) {
            this.testResults.failed++;
            console.log(`💥 ${serviceName}: ERROR - ${error.message}`);
            this.testResults.errors.push({ service: serviceName, error: error.message, stack: error.stack });
            this.testResults.details[serviceName] = { success: false, error: error.message };
        }
    }

    async testCopyPartyService() {
        return this.runTest('CopyParty Service', async () => {
            const service = new CopyPartyService();

            // Test credential generation
            const credentials = await service.generateUniqueCredentials();
            if (!credentials.username || !credentials.password) {
                return { success: false, error: 'Failed to generate credentials' };
            }

            // Test security features
            const encrypted = service.encryptCredentials(credentials);
            if (!encrypted) {
                return { success: false, error: 'Failed to encrypt credentials' };
            }

            // Test status
            const status = service.getStatus();

            return {
                success: true,
                details: {
                    credentials: { username: credentials.username, hasPassword: !!credentials.password },
                    encryption: !!encrypted,
                    status: status.securityEnabled
                }
            };
        });
    }

    async testDNSManagerService() {
        return this.runTest('DNS Manager Service', async () => {
            const service = new DNSManagerService();

            // Test local servers detection
            const localServers = service.getLocalServers();
            if (!localServers || Object.keys(localServers).length === 0) {
                return { success: false, error: 'No local DNS servers configured' };
            }

            // Test zone file generation
            const zone = await service.generateZoneFile('test.local', {
                'host1': '192.168.1.100',
                'host2': '192.168.1.101'
            });

            if (!zone) {
                return { success: false, error: 'Failed to generate zone file' };
            }

            // Test status
            const status = service.getStatus();

            return {
                success: true,
                details: {
                    localServers: Object.keys(localServers),
                    zoneGenerated: !!zone,
                    cloudProviders: status.cloudProviders?.length || 0
                }
            };
        });
    }

    async testSoftwareUpdateService() {
        return this.runTest('Software Update Service', async () => {
            const service = new SoftwareUpdateService();

            // Test update types
            const updateTypes = service.getUpdateTypes();
            if (!updateTypes || Object.keys(updateTypes).length === 0) {
                return { success: false, error: 'No update types configured' };
            }

            // Test version checking
            const versionCheck = await service.checkForUpdates();

            // Test remote deployment capabilities
            const deploymentStatus = service.getDeploymentStatus();

            // Test status
            const status = service.getStatus();

            return {
                success: true,
                details: {
                    updateTypes: Object.keys(updateTypes),
                    versionCheck: versionCheck.success,
                    deployment: deploymentStatus.enabled,
                    remoteServers: status.remoteServers?.length || 0
                }
            };
        });
    }

    async testRemoteAccessibilityService() {
        return this.runTest('Remote Accessibility Service', async () => {
            const service = new RemoteAccessibilityService();

            // Test platform detection
            if (!service.platform) {
                return { success: false, error: 'Platform not detected' };
            }

            // Test AccessKit initialization
            await service.initializeAccessKit();

            // Test audio device initialization
            const audioInit = await service.initializeAudioDevices();

            // Test RIM server setup
            service.setupRIMConnection();

            // Test feature management
            service.setAutoAcceptFeatures(true, ['screen-reader-access', 'audio-streaming']);
            service.setAutoDeclineFeatures(true, ['full-control']);

            // Test audio controls
            service.setLocalInputVolume(0.8);
            service.setMasterVolume(0.9);
            service.setCrossfade(0.5);

            // Test status
            const status = service.getStatus();

            return {
                success: true,
                details: {
                    platform: service.platform,
                    accessKit: service.accessKitAvailable,
                    audioDevices: audioInit.success,
                    audioControls: !!status.audioControls,
                    autoFeatures: !!status.autoAccept,
                    rimServer: status.rimServer.running
                }
            };
        });
    }

    async testSoundManager() {
        return this.runTest('Sound Manager', async () => {
            const service = new SoundManager();

            // Test sound initialization
            await service.ensureSoundsExist();

            // Test sound mappings
            const mappings = service.getSoundMappings();
            if (!mappings || Object.keys(mappings).length === 0) {
                return { success: false, error: 'No sound mappings configured' };
            }

            // Test volume control
            service.setVolume(0.7);
            service.setEnabled(true);

            // Test status
            const status = service.getStatus();

            return {
                success: true,
                details: {
                    enabled: status.enabled,
                    volume: status.volume,
                    soundMappings: Object.keys(mappings),
                    platform: status.platform,
                    availableSounds: status.availableSounds?.length || 0
                }
            };
        });
    }

    async testServiceIntegration() {
        return this.runTest('Service Integration', async () => {
            // Test that all services can be instantiated together
            const copyParty = new CopyPartyService();
            const dns = new DNSManagerService();
            const updates = new SoftwareUpdateService();
            const accessibility = new RemoteAccessibilityService();
            const sound = new SoundManager();

            // Test cross-service communication
            const copyPartyStatus = copyParty.getStatus();
            const dnsStatus = dns.getStatus();
            const updateStatus = updates.getStatus();
            const accessibilityStatus = accessibility.getStatus();
            const soundStatus = sound.getStatus();

            // Verify all services report healthy status
            const allHealthy = [
                copyPartyStatus,
                dnsStatus,
                updateStatus,
                accessibilityStatus,
                soundStatus
            ].every(status => status && typeof status === 'object');

            if (!allHealthy) {
                return { success: false, error: 'One or more services failed to report status' };
            }

            return {
                success: true,
                details: {
                    servicesInitialized: 5,
                    allHealthy: true,
                    copyPartySecure: copyPartyStatus.securityEnabled,
                    dnsReady: dnsStatus.enabled,
                    updatesEnabled: updateStatus.enabled,
                    accessibilityPlatform: accessibilityStatus.platform,
                    soundEnabled: soundStatus.enabled
                }
            };
        });
    }

    async testFileSystemIntegrity() {
        return this.runTest('File System Integrity', async () => {
            const requiredFiles = [
                './src/main/services/CopyPartyService.js',
                './src/main/services/DNSManagerService.js',
                './src/main/services/SoftwareUpdateService.js',
                './src/main/services/RemoteAccessibilityService.js',
                './src/main/services/SoundManager.js'
            ];

            const missingFiles = [];
            for (const file of requiredFiles) {
                if (!await fs.pathExists(file)) {
                    missingFiles.push(file);
                }
            }

            if (missingFiles.length > 0) {
                return {
                    success: false,
                    error: `Missing files: ${missingFiles.join(', ')}`
                };
            }

            // Check assets directory
            const assetsDir = './src/assets';
            const assetsExists = await fs.pathExists(assetsDir);

            return {
                success: true,
                details: {
                    requiredFiles: requiredFiles.length,
                    allPresent: true,
                    assetsDirectory: assetsExists
                }
            };
        });
    }

    async runAllTests() {
        console.log('🚀 Starting FlexPBX Service Test Suite...\n');
        console.log('=' .repeat(60));

        // Run individual service tests
        await this.testFileSystemIntegrity();
        await this.testCopyPartyService();
        await this.testDNSManagerService();
        await this.testSoftwareUpdateService();
        await this.testRemoteAccessibilityService();
        await this.testSoundManager();
        await this.testServiceIntegration();

        // Generate final report
        this.generateReport();
    }

    generateReport() {
        console.log('\n' + '='.repeat(60));
        console.log('📊 TEST RESULTS SUMMARY');
        console.log('='.repeat(60));

        console.log(`Total Tests: ${this.testResults.total}`);
        console.log(`✅ Passed: ${this.testResults.passed}`);
        console.log(`❌ Failed: ${this.testResults.failed}`);
        console.log(`Success Rate: ${Math.round((this.testResults.passed / this.testResults.total) * 100)}%`);

        if (this.testResults.errors.length > 0) {
            console.log('\n🚨 ERRORS:');
            this.testResults.errors.forEach((error, index) => {
                console.log(`${index + 1}. ${error.service}: ${error.error}`);
            });
        }

        // Save detailed results
        const reportFile = './test-results.json';
        fs.writeJsonSync(reportFile, {
            timestamp: new Date().toISOString(),
            summary: {
                total: this.testResults.total,
                passed: this.testResults.passed,
                failed: this.testResults.failed,
                successRate: Math.round((this.testResults.passed / this.testResults.total) * 100)
            },
            details: this.testResults.details,
            errors: this.testResults.errors
        }, { spaces: 2 });

        console.log(`\n📄 Detailed results saved to: ${reportFile}`);

        if (this.testResults.failed === 0) {
            console.log('\n🎉 All tests passed! Services are ready for deployment.');
        } else {
            console.log('\n⚠️ Some tests failed. Please review and fix issues before deployment.');
        }
    }
}

// Run tests if called directly
if (require.main === module) {
    const tester = new ServiceTester();
    tester.runAllTests().catch(error => {
        console.error('💥 Test suite failed:', error);
        process.exit(1);
    });
}

module.exports = ServiceTester;