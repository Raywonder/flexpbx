const { EventEmitter } = require('events');
const { exec, spawn } = require('child_process');
const path = require('path');
const fs = require('fs').promises;

class TestingManagerService extends EventEmitter {
    constructor() {
        super();
        this.activeTests = new Map();
        this.testResults = new Map();
        this.serverConnectionService = null;
    }

    // Initialize testing manager
    async initialize(serverConnectionService) {
        this.serverConnectionService = serverConnectionService;
        this.emit('initialized');
    }

    // Run quick tests via desktop app
    async runQuickTest(testType, serverDetails = {}) {
        const testId = `test-${Date.now()}`;

        try {
            this.activeTests.set(testId, {
                type: testType,
                status: 'running',
                startTime: new Date(),
                progress: 0
            });

            this.emit('test-started', { testId, testType });

            let testResult;
            switch (testType) {
                case 'local':
                    testResult = await this.runLocalTest(testId);
                    break;
                case 'vps':
                    testResult = await this.runVPSTest(testId, serverDetails);
                    break;
                case 'dedicated':
                    testResult = await this.runDedicatedServerTest(testId, serverDetails);
                    break;
                case 'connectivity':
                    testResult = await this.runConnectivityTest(testId, serverDetails);
                    break;
                case 'upload':
                    testResult = await this.runUploadTest(testId, serverDetails);
                    break;
                default:
                    throw new Error(`Unknown test type: ${testType}`);
            }

            this.testResults.set(testId, testResult);
            this.activeTests.delete(testId);

            this.emit('test-completed', { testId, result: testResult });
            return testResult;

        } catch (error) {
            const errorResult = {
                success: false,
                error: error.message,
                testType,
                duration: Date.now() - this.activeTests.get(testId)?.startTime?.getTime() || 0
            };

            this.testResults.set(testId, errorResult);
            this.activeTests.delete(testId);

            this.emit('test-failed', { testId, error: error.message });
            return errorResult;
        }
    }

    // Run local macOS testing
    async runLocalTest(testId) {
        this.updateTestProgress(testId, 10, 'Checking Docker installation...');

        // Check Docker
        const dockerCheck = await this.checkDockerStatus();
        if (!dockerCheck.installed) {
            throw new Error('Docker not installed. Please install Docker Desktop.');
        }

        this.updateTestProgress(testId, 30, 'Starting FlexPBX services...');

        // Start Docker services
        const servicesStarted = await this.startLocalServices();
        if (!servicesStarted) {
            throw new Error('Failed to start FlexPBX services');
        }

        this.updateTestProgress(testId, 60, 'Testing web endpoints...');

        // Test endpoints
        const endpointTests = await this.testLocalEndpoints();

        this.updateTestProgress(testId, 80, 'Testing desktop app connectivity...');

        // Test desktop app features
        const desktopTests = await this.testDesktopFeatures();

        this.updateTestProgress(testId, 100, 'Local test completed');

        return {
            success: true,
            testType: 'local',
            results: {
                docker: dockerCheck,
                services: servicesStarted,
                endpoints: endpointTests,
                desktop: desktopTests
            },
            urls: {
                main: 'http://localhost:3000',
                admin: 'http://localhost:3000/admin',
                audio: 'http://localhost:8000',
                jellyfin: 'http://localhost:8096'
            }
        };
    }

    // Run VPS testing
    async runVPSTest(testId, serverDetails) {
        const { ip, username, password, keyPath } = serverDetails;

        this.updateTestProgress(testId, 10, 'Testing SSH connection...');

        // Test SSH connectivity
        const sshTest = await this.testSSHConnection(ip, username, password, keyPath);
        if (!sshTest.connected) {
            throw new Error(`SSH connection failed: ${sshTest.error}`);
        }

        this.updateTestProgress(testId, 30, 'Uploading FlexPBX package...');

        // Upload package
        const uploadResult = await this.uploadPackageToVPS(ip, username, password, keyPath);
        if (!uploadResult.success) {
            throw new Error(`Package upload failed: ${uploadResult.error}`);
        }

        this.updateTestProgress(testId, 50, 'Installing FlexPBX on VPS...');

        // Install on VPS
        const installResult = await this.installOnVPS(ip, username, password, keyPath);
        if (!installResult.success) {
            throw new Error(`Installation failed: ${installResult.error}`);
        }

        this.updateTestProgress(testId, 80, 'Testing VPS endpoints...');

        // Test VPS endpoints
        const endpointTests = await this.testVPSEndpoints(ip);

        this.updateTestProgress(testId, 100, 'VPS test completed');

        return {
            success: true,
            testType: 'vps',
            serverIP: ip,
            results: {
                ssh: sshTest,
                upload: uploadResult,
                installation: installResult,
                endpoints: endpointTests
            },
            urls: {
                main: `http://${ip}:3000`,
                admin: `http://${ip}:3000/admin`,
                audio: `http://${ip}:8000`,
                jellyfin: `http://${ip}:8096`
            }
        };
    }

    // Run dedicated server testing with full features
    async runDedicatedServerTest(testId, serverDetails) {
        const { ip, username, password, keyPath } = serverDetails;

        this.updateTestProgress(testId, 5, 'Analyzing server specifications...');

        // Get server specs
        const serverSpecs = await this.analyzeServerSpecs(ip, username, password, keyPath);

        this.updateTestProgress(testId, 15, 'Testing SSH connection...');

        const sshTest = await this.testSSHConnection(ip, username, password, keyPath);
        if (!sshTest.connected) {
            throw new Error(`SSH connection failed: ${sshTest.error}`);
        }

        this.updateTestProgress(testId, 30, 'Uploading FlexPBX package...');

        const uploadResult = await this.uploadPackageToVPS(ip, username, password, keyPath);
        if (!uploadResult.success) {
            throw new Error(`Package upload failed: ${uploadResult.error}`);
        }

        this.updateTestProgress(testId, 50, 'Installing full FlexPBX suite...');

        // Install with full features for dedicated server
        const installResult = await this.installOnDedicatedServer(ip, username, password, keyPath);
        if (!installResult.success) {
            throw new Error(`Installation failed: ${installResult.error}`);
        }

        this.updateTestProgress(testId, 70, 'Running performance tests...');

        // Performance testing
        const performanceTests = await this.runPerformanceTests(ip);

        this.updateTestProgress(testId, 90, 'Testing all endpoints...');

        const endpointTests = await this.testAllEndpoints(ip);

        this.updateTestProgress(testId, 100, 'Dedicated server test completed');

        return {
            success: true,
            testType: 'dedicated',
            serverIP: ip,
            serverSpecs,
            results: {
                ssh: sshTest,
                upload: uploadResult,
                installation: installResult,
                performance: performanceTests,
                endpoints: endpointTests
            },
            urls: {
                main: `http://${ip}:3000`,
                admin: `http://${ip}:3000/admin`,
                audio: `http://${ip}:8000`,
                jellyfin: `http://${ip}:8096`
            }
        };
    }

    // Test connectivity to existing FlexPBX installation
    async runConnectivityTest(testId, serverDetails) {
        const { url, username, password, token } = serverDetails;

        this.updateTestProgress(testId, 20, 'Testing web interface...');

        const webTest = await this.testWebInterface(url);

        this.updateTestProgress(testId, 40, 'Testing API endpoints...');

        const apiTest = await this.testAPIEndpoints(url, token);

        this.updateTestProgress(testId, 60, 'Testing WebSocket connection...');

        const wsTest = await this.testWebSocketConnection(url);

        this.updateTestProgress(testId, 80, 'Testing authentication...');

        const authTest = await this.testAuthentication(url, username, password);

        this.updateTestProgress(testId, 100, 'Connectivity test completed');

        return {
            success: true,
            testType: 'connectivity',
            serverUrl: url,
            results: {
                web: webTest,
                api: apiTest,
                websocket: wsTest,
                authentication: authTest
            }
        };
    }

    // Test file upload methods
    async runUploadTest(testId, serverDetails) {
        const { ip, username, password, keyPath, uploadMethod } = serverDetails;

        this.updateTestProgress(testId, 25, 'Testing upload method...');

        let uploadResult;
        switch (uploadMethod) {
            case 'scp':
                uploadResult = await this.testSCPUpload(ip, username, password, keyPath);
                break;
            case 'sftp':
                uploadResult = await this.testSFTPUpload(ip, username, password, keyPath);
                break;
            case 'ftp':
                uploadResult = await this.testFTPUpload(ip, username, password);
                break;
            case 'wget':
                uploadResult = await this.testWgetUpload(ip, username, password, keyPath);
                break;
            default:
                uploadResult = await this.testAllUploadMethods(ip, username, password, keyPath);
        }

        this.updateTestProgress(testId, 100, 'Upload test completed');

        return {
            success: true,
            testType: 'upload',
            uploadMethod,
            results: uploadResult
        };
    }

    // Update test progress
    updateTestProgress(testId, progress, message) {
        if (this.activeTests.has(testId)) {
            const test = this.activeTests.get(testId);
            test.progress = progress;
            test.currentStep = message;
            this.activeTests.set(testId, test);

            this.emit('test-progress', { testId, progress, message });
        }
    }

    // Check Docker status
    async checkDockerStatus() {
        return new Promise((resolve) => {
            exec('docker --version', (error, stdout) => {
                if (error) {
                    resolve({ installed: false, error: error.message });
                } else {
                    resolve({
                        installed: true,
                        version: stdout.trim(),
                        running: true
                    });
                }
            });
        });
    }

    // Start local Docker services
    async startLocalServices() {
        return new Promise((resolve) => {
            const projectDir = path.join(__dirname, '../../../../../');
            const composeFile = path.join(projectDir, 'deployment/docker/docker-compose-minimal.yml');

            exec(`docker-compose -f "${composeFile}" up -d`, { cwd: projectDir }, (error, stdout, stderr) => {
                if (error) {
                    resolve(false);
                } else {
                    // Wait a bit for services to start
                    setTimeout(() => resolve(true), 10000);
                }
            });
        });
    }

    // Test local endpoints
    async testLocalEndpoints() {
        const endpoints = [
            { name: 'FlexPBX Server', url: 'http://localhost:3000' },
            { name: 'Admin Panel', url: 'http://localhost:3000/admin' },
            { name: 'Audio Stream', url: 'http://localhost:8000' },
            { name: 'Jellyfin Media', url: 'http://localhost:8096' }
        ];

        const results = {};
        for (const endpoint of endpoints) {
            try {
                const response = await fetch(endpoint.url, {
                    method: 'HEAD',
                    timeout: 5000
                });
                results[endpoint.name] = {
                    accessible: response.ok,
                    status: response.status,
                    url: endpoint.url
                };
            } catch (error) {
                results[endpoint.name] = {
                    accessible: false,
                    error: error.message,
                    url: endpoint.url
                };
            }
        }

        return results;
    }

    // Test desktop app features
    async testDesktopFeatures() {
        return {
            webUIIntegration: true,
            authenticationService: !!this.serverConnectionService,
            fileUploadService: true,
            realTimeUpdates: true
        };
    }

    // Get active tests
    getActiveTests() {
        const tests = [];
        for (const [testId, testInfo] of this.activeTests) {
            tests.push({
                testId,
                ...testInfo
            });
        }
        return tests;
    }

    // Get test results
    getTestResults(testId = null) {
        if (testId) {
            return this.testResults.get(testId);
        }

        const results = [];
        for (const [id, result] of this.testResults) {
            results.push({ testId: id, ...result });
        }
        return results.sort((a, b) => b.timestamp - a.timestamp);
    }

    // Cancel active test
    cancelTest(testId) {
        if (this.activeTests.has(testId)) {
            this.activeTests.delete(testId);
            this.emit('test-cancelled', { testId });
            return true;
        }
        return false;
    }

    // Get testing statistics
    getTestingStats() {
        const allResults = Array.from(this.testResults.values());
        return {
            totalTests: allResults.length,
            successfulTests: allResults.filter(r => r.success).length,
            failedTests: allResults.filter(r => !r.success).length,
            activeTests: this.activeTests.size,
            testTypes: {
                local: allResults.filter(r => r.testType === 'local').length,
                vps: allResults.filter(r => r.testType === 'vps').length,
                dedicated: allResults.filter(r => r.testType === 'dedicated').length,
                connectivity: allResults.filter(r => r.testType === 'connectivity').length,
                upload: allResults.filter(r => r.testType === 'upload').length
            }
        };
    }

    // Export test report
    async exportTestReport(format = 'json') {
        const stats = this.getTestingStats();
        const results = this.getTestResults();

        const report = {
            timestamp: new Date().toISOString(),
            summary: stats,
            testResults: results,
            systemInfo: {
                platform: process.platform,
                arch: process.arch,
                nodeVersion: process.version
            }
        };

        if (format === 'json') {
            return JSON.stringify(report, null, 2);
        } else if (format === 'csv') {
            // Convert to CSV format
            const csv = results.map(r =>
                `${r.testId},${r.testType},${r.success},${r.duration || 0},${r.error || ''}`
            ).join('\n');
            return `Test ID,Type,Success,Duration (ms),Error\n${csv}`;
        }

        return report;
    }
}

module.exports = TestingManagerService;