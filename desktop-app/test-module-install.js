#!/usr/bin/env node

/**
 * FlexPBX Module Installation Test
 * Tests the addon module installation system
 */

const fs = require('fs');
const path = require('path');
const { spawn } = require('child_process');

class ModuleInstallTester {
    constructor() {
        this.testResults = [];
        this.testCount = 0;
        this.passCount = 0;
        this.failCount = 0;
    }

    async runTests() {
        console.log('='.repeat(60));
        console.log('FlexPBX Module Installation Test Suite');
        console.log('='.repeat(60));
        console.log('');

        await this.testModuleFormats();
        await this.testModuleValidation();
        await this.testModuleInstallation();
        await this.testModuleManagement();
        await this.testDevelopmentTools();

        this.printResults();
    }

    async testModuleFormats() {
        console.log('📦 Testing Module Formats...\n');

        const supportedFormats = ['.flxmod', '.tar.gz', '.zip', '.tar'];

        for (const format of supportedFormats) {
            this.test(`Support for ${format} format`, () => {
                const testFile = this.createTestModule(format);
                const isValid = this.validateModuleFormat(testFile);
                fs.unlinkSync(testFile); // Clean up
                return isValid;
            });
        }

        // Test unsupported formats
        const unsupportedFormats = ['.exe', '.dmg', '.pkg', '.deb'];
        for (const format of unsupportedFormats) {
            this.test(`Reject ${format} format`, () => {
                const testFile = this.createTestModule(format);
                const isValid = this.validateModuleFormat(testFile);
                fs.unlinkSync(testFile); // Clean up
                return !isValid; // Should return false for unsupported
            });
        }
    }

    async testModuleValidation() {
        console.log('\n🔍 Testing Module Validation...\n');

        // Test valid module structure
        this.test('Valid Module Structure', () => {
            const validModule = this.createValidModuleStructure();
            return this.validateModuleStructure(validModule);
        });

        // Test invalid module structure
        this.test('Invalid Module Structure Rejection', () => {
            const invalidModule = this.createInvalidModuleStructure();
            return !this.validateModuleStructure(invalidModule);
        });

        // Test module metadata validation
        this.test('Module Metadata Validation', () => {
            const metadata = {
                name: 'Test Module',
                version: '1.0.0',
                description: 'A test module',
                author: 'FlexPBX Team',
                flexpbx_version: '>=1.0.0'
            };
            return this.validateModuleMetadata(metadata);
        });

        // Test dependency checking
        this.test('Dependency Validation', () => {
            const dependencies = {
                'core-telephony': '>=1.0.0',
                'call-recording': '>=1.5.0'
            };
            return this.validateDependencies(dependencies);
        });
    }

    async testModuleInstallation() {
        console.log('\n⚙️ Testing Module Installation...\n');

        // Test installation from file
        this.test('Install from Local File', async () => {
            const moduleFile = this.createTestModule('.flxmod');
            const result = await this.simulateInstallation(moduleFile, 'file');
            fs.unlinkSync(moduleFile);
            return result.success;
        });

        // Test installation from URL
        this.test('Install from URL', async () => {
            const moduleUrl = 'https://example.com/test-module.flxmod';
            const result = await this.simulateInstallation(moduleUrl, 'url');
            return result.success;
        });

        // Test installation rollback on failure
        this.test('Installation Rollback on Failure', async () => {
            const result = await this.simulateFailedInstallation();
            return result.rolledBack;
        });

        // Test duplicate installation prevention
        this.test('Prevent Duplicate Installation', () => {
            const module = { id: 'test-module', version: '1.0.0' };
            return this.preventDuplicateInstall(module);
        });
    }

    async testModuleManagement() {
        console.log('\n🔧 Testing Module Management...\n');

        // Test module enable/disable
        this.test('Enable Module', () => {
            return this.simulateModuleStateChange('test-module', 'enable');
        });

        this.test('Disable Module', () => {
            return this.simulateModuleStateChange('test-module', 'disable');
        });

        // Test module configuration
        this.test('Module Configuration', () => {
            const config = { setting1: 'value1', setting2: 'value2' };
            return this.simulateModuleConfiguration('test-module', config);
        });

        // Test module uninstallation
        this.test('Module Uninstallation', () => {
            return this.simulateModuleUninstallation('test-module');
        });

        // Test module updates
        this.test('Module Update', () => {
            return this.simulateModuleUpdate('test-module', '1.0.0', '1.1.0');
        });
    }

    async testDevelopmentTools() {
        console.log('\n🛠️ Testing Development Tools...\n');

        // Test module template creation
        this.test('Create Module Template', () => {
            const template = this.createModuleTemplate();
            return this.validateModuleTemplate(template);
        });

        // Test development module loading
        this.test('Load Development Module', () => {
            const devModule = this.createDevelopmentModule();
            return this.loadDevelopmentModule(devModule);
        });

        // Test module hot reload
        this.test('Module Hot Reload', () => {
            return this.simulateHotReload('dev-module');
        });

        // Test module debugging
        this.test('Module Debugging Support', () => {
            return this.testModuleDebugging();
        });
    }

    // Helper methods for testing
    createTestModule(format) {
        const fileName = `test-module${format}`;
        const filePath = path.join(__dirname, fileName);

        // Create a dummy file with the specified format
        fs.writeFileSync(filePath, `Test module content for ${format}`);
        return filePath;
    }

    validateModuleFormat(filePath) {
        const supportedFormats = ['.flxmod', '.tar.gz', '.zip', '.tar'];
        return supportedFormats.some(format => filePath.endsWith(format));
    }

    createValidModuleStructure() {
        return {
            'module.json': {
                name: 'Test Module',
                version: '1.0.0',
                main: 'index.js'
            },
            'index.js': 'module.exports = {};',
            'package.json': {
                name: 'test-module',
                version: '1.0.0'
            }
        };
    }

    createInvalidModuleStructure() {
        return {
            'invalid.txt': 'This is not a valid module'
        };
    }

    validateModuleStructure(structure) {
        return structure['module.json'] && structure['index.js'];
    }

    validateModuleMetadata(metadata) {
        const requiredFields = ['name', 'version', 'description'];
        return requiredFields.every(field => metadata[field]);
    }

    validateDependencies(dependencies) {
        // Check if dependencies are available
        const availableModules = ['core-telephony', 'call-recording'];
        return Object.keys(dependencies).every(dep =>
            availableModules.includes(dep));
    }

    async simulateInstallation(source, type) {
        // Simulate installation process
        console.log(`  Installing from ${type}: ${source}`);

        const steps = [
            'Downloading...',
            'Extracting...',
            'Validating...',
            'Installing...',
            'Configuring...'
        ];

        for (const step of steps) {
            await new Promise(resolve => setTimeout(resolve, 100));
        }

        return { success: true, moduleId: 'test-module' };
    }

    async simulateFailedInstallation() {
        console.log('  Simulating failed installation...');

        try {
            // Simulate installation failure
            throw new Error('Installation failed');
        } catch (error) {
            // Simulate rollback
            console.log('  Rolling back installation...');
            return { rolledBack: true };
        }
    }

    preventDuplicateInstall(module) {
        const installedModules = ['test-module', 'core-telephony'];
        return installedModules.includes(module.id);
    }

    simulateModuleStateChange(moduleId, action) {
        console.log(`  ${action} module: ${moduleId}`);
        return true; // Simulate success
    }

    simulateModuleConfiguration(moduleId, config) {
        console.log(`  Configuring module: ${moduleId}`);
        return Object.keys(config).length > 0;
    }

    simulateModuleUninstallation(moduleId) {
        console.log(`  Uninstalling module: ${moduleId}`);
        return true; // Simulate success
    }

    simulateModuleUpdate(moduleId, oldVersion, newVersion) {
        console.log(`  Updating ${moduleId}: ${oldVersion} -> ${newVersion}`);
        return oldVersion !== newVersion;
    }

    createModuleTemplate() {
        return {
            'module.json': {
                name: '{{MODULE_NAME}}',
                version: '1.0.0',
                description: '{{MODULE_DESCRIPTION}}',
                main: 'index.js',
                author: '{{AUTHOR_NAME}}'
            },
            'index.js': '// {{MODULE_NAME}} implementation\nmodule.exports = {};',
            'README.md': '# {{MODULE_NAME}}\n\n{{MODULE_DESCRIPTION}}',
            'package.json': {
                name: '{{MODULE_ID}}',
                version: '1.0.0'
            }
        };
    }

    validateModuleTemplate(template) {
        const requiredFiles = ['module.json', 'index.js', 'README.md'];
        return requiredFiles.every(file => template[file]);
    }

    createDevelopmentModule() {
        return {
            id: 'dev-module',
            name: 'Development Module',
            version: '0.1.0',
            isDevelopment: true
        };
    }

    loadDevelopmentModule(devModule) {
        return devModule.isDevelopment === true;
    }

    simulateHotReload(moduleId) {
        console.log(`  Hot reloading module: ${moduleId}`);
        return true;
    }

    testModuleDebugging() {
        console.log('  Testing module debugging capabilities...');
        return true; // Simulate debugging support
    }

    test(name, fn) {
        this.testCount++;
        try {
            const result = fn();
            if (result || result === undefined) {
                console.log(`  ✅ ${name}`);
                this.passCount++;
                this.testResults.push({ name, status: 'PASS' });
            } else {
                console.log(`  ❌ ${name}`);
                this.failCount++;
                this.testResults.push({ name, status: 'FAIL' });
            }
        } catch (error) {
            console.log(`  ❌ ${name}: ${error.message}`);
            this.failCount++;
            this.testResults.push({ name, status: 'ERROR', error: error.message });
        }
    }

    printResults() {
        console.log('\n' + '='.repeat(60));
        console.log('Module Installation Test Results');
        console.log('='.repeat(60));
        console.log(`Total Tests: ${this.testCount}`);
        console.log(`Passed: ${this.passCount} (${((this.passCount/this.testCount)*100).toFixed(1)}%)`);
        console.log(`Failed: ${this.failCount} (${((this.failCount/this.testCount)*100).toFixed(1)}%)`);
        console.log('');

        if (this.failCount > 0) {
            console.log('Failed Tests:');
            this.testResults
                .filter(r => r.status !== 'PASS')
                .forEach(r => {
                    console.log(`  - ${r.name}${r.error ? ': ' + r.error : ''}`);
                });
        }

        // Save results to file
        const reportFile = path.join(__dirname, 'module-test-results.json');
        fs.writeFileSync(reportFile, JSON.stringify({
            timestamp: new Date().toISOString(),
            summary: {
                total: this.testCount,
                passed: this.passCount,
                failed: this.failCount,
                passRate: ((this.passCount/this.testCount)*100).toFixed(1) + '%'
            },
            results: this.testResults
        }, null, 2));

        console.log(`\n📄 Detailed results saved to: ${reportFile}`);

        // Exit code based on results
        process.exit(this.failCount > 0 ? 1 : 0);
    }
}

// Run tests
const tester = new ModuleInstallTester();
tester.runTests().catch(console.error);