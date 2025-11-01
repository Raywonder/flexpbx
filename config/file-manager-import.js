#!/usr/bin/env node

/**
 * 📁 FlexPBX File Manager Import Tool
 * Imports configuration files directly through FlexPBX file manager
 */

const fs = require('fs');
const path = require('path');
const https = require('https');
const FormData = require('form-data');

class FlexPBXFileManagerImport {
    constructor() {
        this.config = {
            server: 'flexpbx.devinecreations.net',
            apiUrl: 'https://flexpbx.devinecreations.net/api/filemanager',
            credentials: {
                username: 'admin',
                password: 'flexpbx_api_2024'
            }
        };

        this.importFiles = [
            {
                local: 'callcentric-trunk-config.json',
                remote: '/etc/flexpbx/trunks/callcentric-primary.json',
                type: 'trunk',
                description: 'Callcentric Trunk Configuration'
            },
            {
                local: 'google-voice-config.json',
                remote: '/etc/flexpbx/integrations/google-voice.json',
                type: 'integration',
                description: 'Google Voice API Configuration'
            },
            {
                local: 'extensions-config.json',
                remote: '/etc/flexpbx/extensions/production-extensions.json',
                type: 'extensions',
                description: 'Production Extensions & Queues'
            }
        ];
    }

    async importAllConfigurations() {
        console.log('🚀 FlexPBX File Manager Import Tool');
        console.log('=====================================');
        console.log(`📍 Target Server: ${this.config.server}`);
        console.log('');

        try {
            // Test server connection
            await this.testConnection();

            // Import each configuration file
            for (const fileConfig of this.importFiles) {
                await this.importFile(fileConfig);
            }

            // Reload FlexPBX configuration
            await this.reloadConfiguration();

            console.log('');
            console.log('✅ All configurations imported successfully!');
            console.log('🎯 FlexPBX server is ready for testing');

            this.showTestingInstructions();

        } catch (error) {
            console.error('❌ Import failed:', error.message);
            console.log('');
            console.log('🔧 Troubleshooting:');
            console.log('  • Ensure FlexPBX server is running');
            console.log('  • Check network connectivity');
            console.log('  • Verify API credentials');
            process.exit(1);
        }
    }

    async testConnection() {
        console.log('🔗 Testing FlexPBX server connection...');

        return new Promise((resolve, reject) => {
            const options = {
                hostname: this.config.server,
                port: 443,
                path: '/api/status',
                method: 'GET',
                timeout: 10000
            };

            const req = https.request(options, (res) => {
                if (res.statusCode === 200) {
                    console.log('✅ Server connection successful');
                    resolve();
                } else {
                    reject(new Error(`Server returned ${res.statusCode}`));
                }
            });

            req.on('timeout', () => {
                req.destroy();
                reject(new Error('Connection timeout'));
            });

            req.on('error', reject);
            req.end();
        });
    }

    async importFile(fileConfig) {
        console.log(`📤 Importing ${fileConfig.description}...`);

        if (!fs.existsSync(fileConfig.local)) {
            throw new Error(`Local file not found: ${fileConfig.local}`);
        }

        const fileContent = fs.readFileSync(fileConfig.local);

        return new Promise((resolve, reject) => {
            const form = new FormData();
            form.append('file', fileContent, {
                filename: path.basename(fileConfig.local),
                contentType: 'application/json'
            });
            form.append('destination', fileConfig.remote);
            form.append('type', fileConfig.type);
            form.append('overwrite', 'true');

            const options = {
                hostname: this.config.server,
                port: 443,
                path: '/api/filemanager/upload',
                method: 'POST',
                headers: {
                    ...form.getHeaders(),
                    'Authorization': `Basic ${Buffer.from(
                        this.config.credentials.username + ':' + this.config.credentials.password
                    ).toString('base64')}`
                }
            };

            const req = https.request(options, (res) => {
                let data = '';
                res.on('data', chunk => data += chunk);
                res.on('end', () => {
                    if (res.statusCode === 200 || res.statusCode === 201) {
                        console.log(`   ✅ ${fileConfig.description} imported to ${fileConfig.remote}`);
                        resolve();
                    } else {
                        reject(new Error(`Upload failed: ${res.statusCode} - ${data}`));
                    }
                });
            });

            req.on('error', reject);
            form.pipe(req);
        });
    }

    async reloadConfiguration() {
        console.log('🔄 Reloading FlexPBX configuration...');

        return new Promise((resolve, reject) => {
            const postData = JSON.stringify({
                action: 'reload',
                components: ['trunks', 'extensions', 'queues', 'ivr', 'integrations']
            });

            const options = {
                hostname: this.config.server,
                port: 443,
                path: '/api/system/reload',
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Content-Length': postData.length,
                    'Authorization': `Basic ${Buffer.from(
                        this.config.credentials.username + ':' + this.config.credentials.password
                    ).toString('base64')}`
                }
            };

            const req = https.request(options, (res) => {
                if (res.statusCode === 200) {
                    console.log('   ✅ Configuration reloaded successfully');
                    resolve();
                } else {
                    reject(new Error(`Reload failed: ${res.statusCode}`));
                }
            });

            req.on('error', reject);
            req.write(postData);
            req.end();
        });
    }

    showTestingInstructions() {
        console.log('');
        console.log('🧪 Testing Instructions');
        console.log('========================');
        console.log('');

        console.log('📱 SIP Client Setup (Use Extension 2001):');
        console.log('  • Username: techsupport1');
        console.log('  • Password: Support2001!');
        console.log('  • Server: flexpbx.devinecreations.net');
        console.log('  • Port: 5070');
        console.log('  • Domain: flexpbx.local');
        console.log('');

        console.log('📞 Test Call Scenarios:');
        console.log('  • Call 101 → Main IVR (test menu navigation)');
        console.log('  • Call 1001 → Sales Rep 1');
        console.log('  • Call 2002 → Tech Support 2');
        console.log('  • Call 8000 → Main Conference Room');
        console.log('  • Call 9196 → Echo Test');
        console.log('  • Dial *97 → Voicemail Access');
        console.log('');

        console.log('🌍 Outbound Testing (via Callcentric):');
        console.log('  • Dial 9 + 10-digit US number');
        console.log('  • International: 9 + 011 + country + number');
        console.log('');

        console.log('🎵 IVR Menu Testing (Call 101):');
        console.log('  • Press 1 → Sales Queue');
        console.log('  • Press 2 → Tech Support Queue');
        console.log('  • Press 4 → Direct to your extension (2001)');
        console.log('  • Press 7 → Accessibility Support');
        console.log('  • Press 0 → Operator');
        console.log('');

        console.log('🔧 Advanced Features to Test:');
        console.log('  • Call Transfer (*2)');
        console.log('  • Call Hold (Flash + Hold)');
        console.log('  • Conference Join (8000)');
        console.log('  • DTMF Recognition (all digits, *, #)');
        console.log('  • Voice Quality (HD G.722 codec)');
        console.log('');

        console.log('📊 Monitor Activity:');
        console.log('  • Web Interface: https://flexpbx.devinecreations.net/admin/');
        console.log('  • Real-time Dashboard: https://flexpbx.devinecreations.net/dashboard/');
        console.log('  • Call Logs: https://flexpbx.devinecreations.net/logs/');
    }
}

// Run the import if called directly
if (require.main === module) {
    const importer = new FlexPBXFileManagerImport();
    importer.importAllConfigurations().catch(error => {
        console.error('Fatal error:', error);
        process.exit(1);
    });
}

module.exports = FlexPBXFileManagerImport;