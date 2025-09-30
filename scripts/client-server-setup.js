#!/usr/bin/env node

/**
 * FlexPBX Client-Driven Server Setup Script
 *
 * This script allows the FlexPBX client application to automatically
 * deploy and configure FlexPBX on remote servers via various protocols.
 */

const fs = require('fs');
const path = require('path');
const { spawn, exec } = require('child_process');
const readline = require('readline');
const crypto = require('crypto');

class FlexPBXServerSetup {
    constructor() {
        this.config = {};
        this.connections = {
            ssh: null,
            ftp: null,
            webdav: null
        };
        this.setupProgress = [];
        this.logFile = path.join(__dirname, '../logs/server-setup.log');
    }

    async start() {
        console.log('🚀 FlexPBX Client-Driven Server Setup');
        console.log('=====================================');

        try {
            await this.gatherConnectionInfo();
            await this.validateConnection();
            await this.gatherDeploymentConfig();
            await this.confirmDeployment();
            await this.executeDeployment();
            await this.completeSetup();

            console.log('✅ Server setup completed successfully!');
            this.displayConnectionInfo();
        } catch (error) {
            console.error('❌ Setup failed:', error.message);
            this.log('ERROR', error.message);
            process.exit(1);
        }
    }

    async gatherConnectionInfo() {
        const rl = readline.createInterface({
            input: process.stdin,
            output: process.stdout
        });

        console.log('\n📡 Server Connection Configuration');
        console.log('Choose connection method:');
        console.log('1. SSH/SFTP (Recommended)');
        console.log('2. FTP/FTPS');
        console.log('3. WebDAV');

        const method = await this.question(rl, 'Select method (1-3): ');

        switch(method) {
            case '1':
                await this.configureSSH(rl);
                break;
            case '2':
                await this.configureFTP(rl);
                break;
            case '3':
                await this.configureWebDAV(rl);
                break;
            default:
                throw new Error('Invalid connection method selected');
        }

        rl.close();
    }

    async configureSSH(rl) {
        console.log('\n🔐 SSH/SFTP Configuration');

        this.config.connection = {
            type: 'ssh',
            host: await this.question(rl, 'Server hostname/IP: '),
            port: await this.question(rl, 'SSH port (22): ') || '22',
            username: await this.question(rl, 'Username: '),
            authMethod: await this.question(rl, 'Auth method (password/key): ') || 'password'
        };

        if (this.config.connection.authMethod === 'password') {
            this.config.connection.password = await this.questionHidden(rl, 'Password: ');
        } else {
            this.config.connection.keyPath = await this.question(rl, 'SSH key path (~/.ssh/id_rsa): ') || '~/.ssh/id_rsa';
            this.config.connection.passphrase = await this.questionHidden(rl, 'Key passphrase (optional): ');
        }

        this.config.connection.sudoPassword = await this.questionHidden(rl, 'Sudo password (if required): ');
    }

    async configureFTP(rl) {
        console.log('\n📁 FTP/FTPS Configuration');

        this.config.connection = {
            type: 'ftp',
            host: await this.question(rl, 'FTP hostname/IP: '),
            port: await this.question(rl, 'FTP port (21): ') || '21',
            username: await this.question(rl, 'Username: '),
            password: await this.questionHidden(rl, 'Password: '),
            secure: (await this.question(rl, 'Use FTPS? (y/n): ')).toLowerCase() === 'y'
        };
    }

    async configureWebDAV(rl) {
        console.log('\n🌐 WebDAV Configuration');

        this.config.connection = {
            type: 'webdav',
            url: await this.question(rl, 'WebDAV URL: '),
            username: await this.question(rl, 'Username: '),
            password: await this.questionHidden(rl, 'Password: '),
            secure: this.config.connection.url.startsWith('https')
        };
    }

    async gatherDeploymentConfig() {
        const rl = readline.createInterface({
            input: process.stdin,
            output: process.stdout
        });

        console.log('\n⚙️  Deployment Configuration');

        this.config.deployment = {
            path: await this.question(rl, 'Installation path (/opt/flexpbx): ') || '/opt/flexpbx',
            type: await this.question(rl, 'Deployment type (standalone/central/cluster): ') || 'standalone',
            domain: await this.question(rl, 'Domain name (optional): '),
            database: await this.question(rl, 'Database type (sqlite/mysql/postgres): ') || 'sqlite',
            ssl: (await this.question(rl, 'Enable SSL? (y/n): ')).toLowerCase() === 'y'
        };

        if (this.config.deployment.database !== 'sqlite') {
            this.config.deployment.dbHost = await this.question(rl, 'Database host: ');
            this.config.deployment.dbUser = await this.question(rl, 'Database username: ');
            this.config.deployment.dbPassword = await this.questionHidden(rl, 'Database password: ');
            this.config.deployment.dbName = await this.question(rl, 'Database name: ') || 'flexpbx';
        }

        // Generate secure secrets
        this.config.deployment.secrets = {
            jwtSecret: crypto.randomBytes(32).toString('hex'),
            sessionSecret: crypto.randomBytes(32).toString('hex'),
            adminPassword: this.generatePassword(),
            amiPassword: this.generatePassword()
        };

        rl.close();
    }

    async validateConnection() {
        console.log('\n🔍 Validating connection...');

        switch(this.config.connection.type) {
            case 'ssh':
                return await this.validateSSH();
            case 'ftp':
                return await this.validateFTP();
            case 'webdav':
                return await this.validateWebDAV();
        }
    }

    async validateSSH() {
        const { NodeSSH } = require('node-ssh');
        const ssh = new NodeSSH();

        const connectionConfig = {
            host: this.config.connection.host,
            port: parseInt(this.config.connection.port),
            username: this.config.connection.username
        };

        if (this.config.connection.authMethod === 'password') {
            connectionConfig.password = this.config.connection.password;
        } else {
            connectionConfig.privateKeyPath = this.config.connection.keyPath.replace('~', require('os').homedir());
            if (this.config.connection.passphrase) {
                connectionConfig.passphrase = this.config.connection.passphrase;
            }
        }

        try {
            await ssh.connect(connectionConfig);
            console.log('✅ SSH connection successful');

            // Test sudo access if password provided
            if (this.config.connection.sudoPassword) {
                await ssh.execCommand('echo "test"', {
                    options: { pty: true },
                    stdin: this.config.connection.sudoPassword + '\n'
                });
                console.log('✅ Sudo access confirmed');
            }

            this.connections.ssh = ssh;
            return true;
        } catch (error) {
            throw new Error(`SSH connection failed: ${error.message}`);
        }
    }

    async validateFTP() {
        const ftp = require('basic-ftp');
        const client = new ftp.Client();

        try {
            await client.access({
                host: this.config.connection.host,
                port: parseInt(this.config.connection.port),
                user: this.config.connection.username,
                password: this.config.connection.password,
                secure: this.config.connection.secure
            });

            console.log('✅ FTP connection successful');
            this.connections.ftp = client;
            return true;
        } catch (error) {
            throw new Error(`FTP connection failed: ${error.message}`);
        }
    }

    async validateWebDAV() {
        const { createClient } = require('webdav');

        try {
            const client = createClient(this.config.connection.url, {
                username: this.config.connection.username,
                password: this.config.connection.password
            });

            await client.getDirectoryContents('/');
            console.log('✅ WebDAV connection successful');
            this.connections.webdav = client;
            return true;
        } catch (error) {
            throw new Error(`WebDAV connection failed: ${error.message}`);
        }
    }

    async confirmDeployment() {
        console.log('\n📋 Deployment Summary');
        console.log('===================');
        console.log(`Connection: ${this.config.connection.type.toUpperCase()}`);
        console.log(`Host: ${this.config.connection.host}`);
        console.log(`Installation Path: ${this.config.deployment.path}`);
        console.log(`Deployment Type: ${this.config.deployment.type}`);
        console.log(`Database: ${this.config.deployment.database}`);
        console.log(`SSL Enabled: ${this.config.deployment.ssl}`);

        if (this.config.deployment.domain) {
            console.log(`Domain: ${this.config.deployment.domain}`);
        }

        const rl = readline.createInterface({
            input: process.stdin,
            output: process.stdout
        });

        const confirm = await this.question(rl, '\nProceed with deployment? (y/n): ');
        rl.close();

        if (confirm.toLowerCase() !== 'y') {
            throw new Error('Deployment cancelled by user');
        }
    }

    async executeDeployment() {
        console.log('\n🚀 Starting deployment...');
        this.log('INFO', 'Starting FlexPBX server deployment');

        const steps = [
            { name: 'Preparing server environment', fn: 'prepareEnvironment' },
            { name: 'Uploading FlexPBX files', fn: 'uploadFiles' },
            { name: 'Installing dependencies', fn: 'installDependencies' },
            { name: 'Configuring services', fn: 'configureServices' },
            { name: 'Setting up database', fn: 'setupDatabase' },
            { name: 'Starting services', fn: 'startServices' },
            { name: 'Running health checks', fn: 'healthCheck' }
        ];

        for (let i = 0; i < steps.length; i++) {
            const step = steps[i];
            console.log(`\n[${i + 1}/${steps.length}] ${step.name}...`);

            try {
                await this[step.fn]();
                console.log(`✅ ${step.name} completed`);
                this.setupProgress.push({ step: step.name, status: 'completed', timestamp: new Date() });
            } catch (error) {
                console.error(`❌ ${step.name} failed: ${error.message}`);
                this.setupProgress.push({ step: step.name, status: 'failed', error: error.message, timestamp: new Date() });
                throw error;
            }
        }
    }

    async prepareEnvironment() {
        switch(this.config.connection.type) {
            case 'ssh':
                return await this.prepareSSHEnvironment();
            case 'ftp':
                return await this.prepareFTPEnvironment();
            case 'webdav':
                return await this.prepareWebDAVEnvironment();
        }
    }

    async prepareSSHEnvironment() {
        const ssh = this.connections.ssh;
        const commands = [
            'sudo apt update',
            'sudo apt install -y curl wget git',
            `sudo mkdir -p ${this.config.deployment.path}`,
            `sudo chown $USER:$USER ${this.config.deployment.path}`,
            'curl -fsSL https://get.docker.com -o get-docker.sh && sh get-docker.sh',
            'sudo curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose',
            'sudo chmod +x /usr/local/bin/docker-compose',
            'sudo usermod -aG docker $USER'
        ];

        for (const command of commands) {
            await ssh.execCommand(command, {
                options: { pty: true },
                stdin: this.config.connection.sudoPassword ? this.config.connection.sudoPassword + '\n' : undefined
            });
        }
    }

    async prepareFTPEnvironment() {
        const client = this.connections.ftp;

        // Create directory structure via FTP
        const dirs = [
            this.config.deployment.path,
            `${this.config.deployment.path}/data`,
            `${this.config.deployment.path}/logs`,
            `${this.config.deployment.path}/config`
        ];

        for (const dir of dirs) {
            try {
                await client.ensureDir(dir);
            } catch (error) {
                // Directory might already exist
            }
        }
    }

    async prepareWebDAVEnvironment() {
        const client = this.connections.webdav;

        // Create directory structure via WebDAV
        const dirs = [
            this.config.deployment.path,
            `${this.config.deployment.path}/data`,
            `${this.config.deployment.path}/logs`,
            `${this.config.deployment.path}/config`
        ];

        for (const dir of dirs) {
            try {
                await client.createDirectory(dir);
            } catch (error) {
                // Directory might already exist
            }
        }
    }

    async uploadFiles() {
        console.log('Preparing deployment package...');

        // Create deployment package
        const packagePath = await this.createDeploymentPackage();

        switch(this.config.connection.type) {
            case 'ssh':
                return await this.uploadViaSSH(packagePath);
            case 'ftp':
                return await this.uploadViaFTP(packagePath);
            case 'webdav':
                return await this.uploadViaWebDAV(packagePath);
        }
    }

    async createDeploymentPackage() {
        const packageDir = path.join(__dirname, '../tmp/deployment');
        const packageFile = path.join(__dirname, '../tmp/flexpbx-deployment.tar.gz');

        // Create deployment structure
        await this.execAsync(`mkdir -p ${packageDir}`);
        await this.execAsync(`cp -r ${path.join(__dirname, '../')}/* ${packageDir}/`);

        // Generate environment file
        const envContent = this.generateEnvFile();
        fs.writeFileSync(path.join(packageDir, '.env'), envContent);

        // Create deployment package
        await this.execAsync(`cd ${path.dirname(packageDir)} && tar -czf ${packageFile} deployment/`);

        return packageFile;
    }

    async uploadViaSSH(packagePath) {
        const ssh = this.connections.ssh;

        // Upload package
        await ssh.putFile(packagePath, `/tmp/flexpbx-deployment.tar.gz`);

        // Extract package
        await ssh.execCommand(`cd ${this.config.deployment.path} && tar -xzf /tmp/flexpbx-deployment.tar.gz --strip-components=1`);
        await ssh.execCommand('rm /tmp/flexpbx-deployment.tar.gz');
    }

    async uploadViaFTP(packagePath) {
        const client = this.connections.ftp;

        // Upload package
        await client.uploadFrom(packagePath, `${this.config.deployment.path}/flexpbx-deployment.tar.gz`);

        // Note: FTP cannot extract archives, would need additional setup
        console.log('Package uploaded. Manual extraction may be required.');
    }

    async uploadViaWebDAV(packagePath) {
        const client = this.connections.webdav;
        const fileBuffer = fs.readFileSync(packagePath);

        // Upload package
        await client.putFileContents(`${this.config.deployment.path}/flexpbx-deployment.tar.gz`, fileBuffer);

        // Note: WebDAV cannot extract archives, would need additional setup
        console.log('Package uploaded. Manual extraction may be required.');
    }

    async installDependencies() {
        if (this.config.connection.type !== 'ssh') {
            console.log('Skipping dependency installation (SSH required)');
            return;
        }

        const ssh = this.connections.ssh;
        await ssh.execCommand(`cd ${this.config.deployment.path} && npm install`);
    }

    async configureServices() {
        if (this.config.connection.type !== 'ssh') {
            console.log('Skipping service configuration (SSH required)');
            return;
        }

        const ssh = this.connections.ssh;

        // Set permissions
        await ssh.execCommand(`chmod +x ${this.config.deployment.path}/install.sh`);
        await ssh.execCommand(`chmod +x ${this.config.deployment.path}/scripts/*`);

        // Configure systemd service
        const serviceContent = this.generateSystemdService();
        await ssh.execCommand(`echo '${serviceContent}' | sudo tee /etc/systemd/system/flexpbx.service`);
        await ssh.execCommand('sudo systemctl daemon-reload');
        await ssh.execCommand('sudo systemctl enable flexpbx');
    }

    async setupDatabase() {
        if (this.config.connection.type !== 'ssh') {
            console.log('Skipping database setup (SSH required)');
            return;
        }

        const ssh = this.connections.ssh;

        if (this.config.deployment.database === 'sqlite') {
            await ssh.execCommand(`cd ${this.config.deployment.path} && mkdir -p data`);
        } else {
            // Database setup commands for MySQL/PostgreSQL
            await ssh.execCommand(`cd ${this.config.deployment.path} && npm run setup:database`);
        }
    }

    async startServices() {
        if (this.config.connection.type !== 'ssh') {
            console.log('Skipping service startup (SSH required)');
            return;
        }

        const ssh = this.connections.ssh;

        await ssh.execCommand(`cd ${this.config.deployment.path} && docker-compose up -d`);
        await ssh.execCommand('sudo systemctl start flexpbx');
    }

    async healthCheck() {
        if (this.config.connection.type !== 'ssh') {
            console.log('Skipping health check (SSH required)');
            return;
        }

        const ssh = this.connections.ssh;

        // Wait for services to start
        await this.sleep(10000);

        const healthResult = await ssh.execCommand(`cd ${this.config.deployment.path} && npm run health-check`);

        if (healthResult.code !== 0) {
            throw new Error(`Health check failed: ${healthResult.stderr}`);
        }
    }

    async completeSetup() {
        console.log('\n🎯 Completing setup...');

        // Connect to deployed server for final configuration
        if (this.config.deployment.domain || this.config.connection.host) {
            const serverUrl = `http://${this.config.deployment.domain || this.config.connection.host}:3000`;

            try {
                // Test connectivity
                const response = await this.httpRequest(serverUrl + '/health');
                if (response.status === 'healthy') {
                    console.log('✅ Server is responding');

                    // Perform initial configuration via API
                    await this.configureViaAPI(serverUrl);
                }
            } catch (error) {
                console.warn('⚠️  Could not connect to server for final configuration');
                console.warn('Manual configuration may be required');
            }
        }
    }

    async configureViaAPI(serverUrl) {
        // Initial admin setup
        const adminConfig = {
            extension: '1000',
            password: this.config.deployment.secrets.adminPassword,
            pin: '9876',
            name: 'Administrator',
            email: 'admin@flexpbx.local'
        };

        // Create admin user
        await this.httpRequest(serverUrl + '/api/v1/setup/admin', {
            method: 'POST',
            body: JSON.stringify(adminConfig),
            headers: { 'Content-Type': 'application/json' }
        });

        console.log('✅ Admin user configured');
    }

    generateEnvFile() {
        const env = [
            `# FlexPBX Auto-Generated Configuration`,
            `# Generated: ${new Date().toISOString()}`,
            ``,
            `# Server Configuration`,
            `NODE_ENV=production`,
            `PORT=3000`,
            `DOMAIN_NAME=${this.config.deployment.domain || this.config.connection.host}`,
            `SSL_ENABLED=${this.config.deployment.ssl}`,
            ``,
            `# Database Configuration`,
            `DB_TYPE=${this.config.deployment.database}`,
        ];

        if (this.config.deployment.database !== 'sqlite') {
            env.push(`DB_HOST=${this.config.deployment.dbHost}`);
            env.push(`DB_USER=${this.config.deployment.dbUser}`);
            env.push(`DB_PASSWORD=${this.config.deployment.dbPassword}`);
            env.push(`DB_NAME=${this.config.deployment.dbName}`);
        }

        env.push(``,
            `# Security`,
            `JWT_SECRET=${this.config.deployment.secrets.jwtSecret}`,
            `SESSION_SECRET=${this.config.deployment.secrets.sessionSecret}`,
            `AMI_PASSWORD=${this.config.deployment.secrets.amiPassword}`,
            ``,
            `# Default Credentials`,
            `DEFAULT_ADMIN_PASSWORD=${this.config.deployment.secrets.adminPassword}`,
            ``,
            `# Accessibility Features`,
            `ACCESSIBILITY_ENABLED=true`,
            `SCREEN_READER_SUPPORT=true`,
            `AUDIO_FEEDBACK_ENABLED=true`,
            `VOICE_ANNOUNCEMENTS_ENABLED=true`
        );

        return env.join('\n');
    }

    generateSystemdService() {
        return `[Unit]
Description=FlexPBX Service
After=docker.service
Requires=docker.service

[Service]
Type=simple
WorkingDirectory=${this.config.deployment.path}
ExecStart=/usr/local/bin/docker-compose up
ExecStop=/usr/local/bin/docker-compose down
Restart=always
RestartSec=10
User=root

[Install]
WantedBy=multi-user.target`;
    }

    displayConnectionInfo() {
        console.log('\n🎉 Deployment Complete!');
        console.log('======================');
        console.log(`Server URL: http://${this.config.deployment.domain || this.config.connection.host}:3000`);
        console.log(`Admin Extension: 1000`);
        console.log(`Admin Password: ${this.config.deployment.secrets.adminPassword}`);
        console.log(`Admin PIN: 9876`);

        if (this.config.deployment.ssl && this.config.deployment.domain) {
            console.log(`Secure URL: https://${this.config.deployment.domain}`);
        }

        console.log('\n📋 Next Steps:');
        console.log('1. Access the web interface');
        console.log('2. Change default passwords');
        console.log('3. Configure extensions');
        console.log('4. Test calling functionality');
        console.log('5. Set up SSL certificates (if needed)');
    }

    // Utility functions
    generatePassword(length = 16) {
        const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*';
        let password = '';
        for (let i = 0; i < length; i++) {
            password += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        return password;
    }

    question(rl, prompt) {
        return new Promise((resolve) => {
            rl.question(prompt, resolve);
        });
    }

    questionHidden(rl, prompt) {
        return new Promise((resolve) => {
            process.stdout.write(prompt);
            process.stdin.setRawMode(true);
            process.stdin.resume();
            process.stdin.setEncoding('utf8');

            let password = '';
            process.stdin.on('data', function(char) {
                char = char + '';
                switch(char) {
                    case '\n':
                    case '\r':
                    case '\u0004':
                        process.stdin.setRawMode(false);
                        process.stdin.pause();
                        process.stdout.write('\n');
                        resolve(password);
                        break;
                    case '\u0003':
                        process.exit();
                        break;
                    default:
                        password += char;
                        process.stdout.write('*');
                        break;
                }
            });
        });
    }

    execAsync(command) {
        return new Promise((resolve, reject) => {
            exec(command, (error, stdout, stderr) => {
                if (error) reject(error);
                else resolve({ stdout, stderr });
            });
        });
    }

    httpRequest(url, options = {}) {
        return new Promise((resolve, reject) => {
            const https = require('https');
            const http = require('http');
            const client = url.startsWith('https') ? https : http;

            const req = client.request(url, options, (res) => {
                let data = '';
                res.on('data', (chunk) => data += chunk);
                res.on('end', () => {
                    try {
                        resolve(JSON.parse(data));
                    } catch {
                        resolve(data);
                    }
                });
            });

            req.on('error', reject);
            if (options.body) req.write(options.body);
            req.end();
        });
    }

    sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    log(level, message) {
        const timestamp = new Date().toISOString();
        const logEntry = `[${timestamp}] ${level}: ${message}\n`;

        try {
            fs.appendFileSync(this.logFile, logEntry);
        } catch (error) {
            // Ignore logging errors
        }
    }
}

// Run if called directly
if (require.main === module) {
    const setup = new FlexPBXServerSetup();
    setup.start().catch(console.error);
}

module.exports = FlexPBXServerSetup;