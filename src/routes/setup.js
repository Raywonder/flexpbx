const express = require('express');
const router = express.Router();
const { NodeSSH } = require('node-ssh');
const ftp = require('basic-ftp');
const { createClient } = require('webdav');
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const { spawn } = require('child_process');
const logger = require('../utils/logger');

// Test server connection
router.post('/test-connection', async (req, res) => {
    try {
        const { type, ...connectionData } = req.body;

        let result = { success: false, error: null };

        switch (type) {
            case 'ssh':
                result = await testSSHConnection(connectionData);
                break;
            case 'ftp':
                result = await testFTPConnection(connectionData);
                break;
            case 'webdav':
                result = await testWebDAVConnection(connectionData);
                break;
            default:
                result.error = 'Invalid connection type';
        }

        res.json(result);
    } catch (error) {
        logger.error('Connection test error:', error);
        res.json({ success: false, error: error.message });
    }
});

// Start deployment process
router.post('/deploy', async (req, res) => {
    try {
        const { connection, deployment } = req.body;
        const deploymentId = crypto.randomUUID();

        // Store deployment configuration
        const deploymentConfig = {
            id: deploymentId,
            connection,
            deployment,
            status: 'starting',
            startTime: new Date(),
            steps: []
        };

        // Start deployment in background
        startDeploymentProcess(deploymentConfig, req.io);

        res.json({
            success: true,
            deploymentId,
            message: 'Deployment started'
        });

    } catch (error) {
        logger.error('Deployment start error:', error);
        res.json({ success: false, error: error.message });
    }
});

// Get deployment status
router.get('/deployment/:id/status', async (req, res) => {
    try {
        const { id } = req.params;

        // In a real implementation, this would fetch from database
        const status = getDeploymentStatus(id);

        res.json({ success: true, status });
    } catch (error) {
        logger.error('Deployment status error:', error);
        res.json({ success: false, error: error.message });
    }
});

async function testSSHConnection(connectionData) {
    const ssh = new NodeSSH();

    try {
        const config = {
            host: connectionData.host,
            port: connectionData.port || 22,
            username: connectionData.username
        };

        if (connectionData.authMethod === 'password') {
            config.password = connectionData.password;
        } else {
            config.privateKeyPath = connectionData.keyPath;
            if (connectionData.keyPassphrase) {
                config.passphrase = connectionData.keyPassphrase;
            }
        }

        await ssh.connect(config);

        // Test basic commands
        const result = await ssh.execCommand('echo "test"');
        if (result.code !== 0) {
            throw new Error('Command execution failed');
        }

        // Test sudo if password provided
        if (connectionData.sudoPassword) {
            const sudoResult = await ssh.execCommand('sudo echo "sudo test"', {
                options: { pty: true },
                stdin: connectionData.sudoPassword + '\n'
            });

            if (sudoResult.code !== 0) {
                throw new Error('Sudo access verification failed');
            }
        }

        await ssh.dispose();
        return { success: true, message: 'SSH connection successful' };

    } catch (error) {
        return { success: false, error: error.message };
    }
}

async function testFTPConnection(connectionData) {
    const client = new ftp.Client();

    try {
        await client.access({
            host: connectionData.host,
            port: connectionData.port || 21,
            user: connectionData.username,
            password: connectionData.password,
            secure: connectionData.secure || false
        });

        // Test directory listing
        await client.list();

        client.close();
        return { success: true, message: 'FTP connection successful' };

    } catch (error) {
        return { success: false, error: error.message };
    }
}

async function testWebDAVConnection(connectionData) {
    try {
        const client = createClient(connectionData.url, {
            username: connectionData.username,
            password: connectionData.password
        });

        // Test directory listing
        await client.getDirectoryContents('/');

        return { success: true, message: 'WebDAV connection successful' };

    } catch (error) {
        return { success: false, error: error.message };
    }
}

async function startDeploymentProcess(config, io) {
    const { connection, deployment } = config;
    const socket = io.of('/deployment');

    try {
        logger.info(`Starting deployment ${config.id}`);

        const steps = [
            { id: 'prepare', name: 'Preparing server environment', fn: prepareEnvironment },
            { id: 'upload', name: 'Uploading FlexPBX files', fn: uploadFiles },
            { id: 'install', name: 'Installing dependencies', fn: installDependencies },
            { id: 'configure', name: 'Configuring services', fn: configureServices },
            { id: 'database', name: 'Setting up database', fn: setupDatabase },
            { id: 'start', name: 'Starting services', fn: startServices },
            { id: 'verify', name: 'Verifying deployment', fn: verifyDeployment }
        ];

        for (const step of steps) {
            try {
                socket.emit('step-start', {
                    step: step.id,
                    description: step.name
                });

                await step.fn(config, socket);

                socket.emit('step-complete', {
                    step: step.id,
                    description: step.name
                });

                config.steps.push({
                    id: step.id,
                    name: step.name,
                    status: 'completed',
                    completedAt: new Date()
                });

            } catch (error) {
                logger.error(`Step ${step.id} failed:`, error);

                socket.emit('step-error', {
                    step: step.id,
                    description: step.name,
                    error: error.message
                });

                config.steps.push({
                    id: step.id,
                    name: step.name,
                    status: 'failed',
                    error: error.message,
                    failedAt: new Date()
                });

                throw error;
            }
        }

        // Generate connection information
        const serverUrl = `http://${deployment.domainName || connection.host}:3000`;
        const adminPassword = generatePassword();

        const deploymentResult = {
            serverUrl,
            adminExtension: '1000',
            adminPassword,
            adminPin: '9876',
            sslUrl: deployment.sslEnabled !== 'false' ?
                `https://${deployment.domainName || connection.host}` : null
        };

        config.status = 'completed';
        config.endTime = new Date();
        config.result = deploymentResult;

        socket.emit('deployment-complete', deploymentResult);
        logger.info(`Deployment ${config.id} completed successfully`);

    } catch (error) {
        config.status = 'failed';
        config.endTime = new Date();
        config.error = error.message;

        socket.emit('deployment-error', { error: error.message });
        logger.error(`Deployment ${config.id} failed:`, error);
    }
}

async function prepareEnvironment(config, socket) {
    const { connection, deployment } = config;

    if (connection.type !== 'ssh') {
        socket.emit('log', { message: 'Skipping environment preparation (SSH required)' });
        return;
    }

    const ssh = new NodeSSH();
    await connectSSH(ssh, connection);

    socket.emit('log', { message: 'Updating package lists...' });
    await ssh.execCommand('sudo apt update');

    socket.emit('log', { message: 'Installing required packages...' });
    await ssh.execCommand('sudo apt install -y curl wget git docker.io docker-compose');

    socket.emit('log', { message: 'Creating installation directory...' });
    await ssh.execCommand(`sudo mkdir -p ${deployment.installPath}`);
    await ssh.execCommand(`sudo chown $USER:$USER ${deployment.installPath}`);

    socket.emit('log', { message: 'Configuring Docker...' });
    await ssh.execCommand('sudo usermod -aG docker $USER');

    await ssh.dispose();
}

async function uploadFiles(config, socket) {
    const { connection, deployment } = config;

    socket.emit('log', { message: 'Creating deployment package...' });
    const packagePath = await createDeploymentPackage(config);

    switch (connection.type) {
        case 'ssh':
            await uploadViaSSH(config, packagePath, socket);
            break;
        case 'ftp':
            await uploadViaFTP(config, packagePath, socket);
            break;
        case 'webdav':
            await uploadViaWebDAV(config, packagePath, socket);
            break;
    }

    // Clean up local package
    fs.unlinkSync(packagePath);
}

async function uploadViaSSH(config, packagePath, socket) {
    const { connection, deployment } = config;
    const ssh = new NodeSSH();
    await connectSSH(ssh, connection);

    socket.emit('log', { message: 'Uploading deployment package...' });
    await ssh.putFile(packagePath, '/tmp/flexpbx-deployment.tar.gz');

    socket.emit('log', { message: 'Extracting files...' });
    await ssh.execCommand(`cd ${deployment.installPath} && tar -xzf /tmp/flexpbx-deployment.tar.gz --strip-components=1`);
    await ssh.execCommand('rm /tmp/flexpbx-deployment.tar.gz');

    await ssh.dispose();
}

async function uploadViaFTP(config, packagePath, socket) {
    const { connection, deployment } = config;
    const client = new ftp.Client();

    await client.access({
        host: connection.host,
        port: connection.port || 21,
        user: connection.username,
        password: connection.password,
        secure: connection.secure || false
    });

    socket.emit('log', { message: 'Uploading via FTP...' });
    await client.uploadFrom(packagePath, `${deployment.installPath}/flexpbx-deployment.tar.gz`);

    client.close();
    socket.emit('log', { message: 'Upload complete. Manual extraction may be required.' });
}

async function uploadViaWebDAV(config, packagePath, socket) {
    const { connection, deployment } = config;
    const client = createClient(connection.url, {
        username: connection.username,
        password: connection.password
    });

    const fileBuffer = fs.readFileSync(packagePath);

    socket.emit('log', { message: 'Uploading via WebDAV...' });
    await client.putFileContents(`${deployment.installPath}/flexpbx-deployment.tar.gz`, fileBuffer);

    socket.emit('log', { message: 'Upload complete. Manual extraction may be required.' });
}

async function installDependencies(config, socket) {
    if (config.connection.type !== 'ssh') {
        socket.emit('log', { message: 'Skipping dependency installation (SSH required)' });
        return;
    }

    const ssh = new NodeSSH();
    await connectSSH(ssh, config.connection);

    socket.emit('log', { message: 'Installing Node.js dependencies...' });
    await ssh.execCommand(`cd ${config.deployment.installPath} && npm install`);

    await ssh.dispose();
}

async function configureServices(config, socket) {
    if (config.connection.type !== 'ssh') {
        socket.emit('log', { message: 'Skipping service configuration (SSH required)' });
        return;
    }

    const ssh = new NodeSSH();
    await connectSSH(ssh, config.connection);

    socket.emit('log', { message: 'Setting file permissions...' });
    await ssh.execCommand(`chmod +x ${config.deployment.installPath}/install.sh`);
    await ssh.execCommand(`chmod +x ${config.deployment.installPath}/scripts/*`);

    socket.emit('log', { message: 'Creating systemd service...' });
    const serviceContent = generateSystemdService(config.deployment.installPath);
    await ssh.execCommand(`echo '${serviceContent}' | sudo tee /etc/systemd/system/flexpbx.service`);
    await ssh.execCommand('sudo systemctl daemon-reload');
    await ssh.execCommand('sudo systemctl enable flexpbx');

    await ssh.dispose();
}

async function setupDatabase(config, socket) {
    if (config.connection.type !== 'ssh') {
        socket.emit('log', { message: 'Skipping database setup (SSH required)' });
        return;
    }

    const ssh = new NodeSSH();
    await connectSSH(ssh, config.connection);

    if (config.deployment.databaseType === 'sqlite') {
        socket.emit('log', { message: 'Creating SQLite database directory...' });
        await ssh.execCommand(`cd ${config.deployment.installPath} && mkdir -p data`);
    } else {
        socket.emit('log', { message: 'Initializing external database...' });
        await ssh.execCommand(`cd ${config.deployment.installPath} && npm run setup:database`);
    }

    await ssh.dispose();
}

async function startServices(config, socket) {
    if (config.connection.type !== 'ssh') {
        socket.emit('log', { message: 'Skipping service startup (SSH required)' });
        return;
    }

    const ssh = new NodeSSH();
    await connectSSH(ssh, config.connection);

    socket.emit('log', { message: 'Starting Docker services...' });
    await ssh.execCommand(`cd ${config.deployment.installPath} && docker-compose up -d`);

    socket.emit('log', { message: 'Starting FlexPBX system service...' });
    await ssh.execCommand('sudo systemctl start flexpbx');

    await ssh.dispose();
}

async function verifyDeployment(config, socket) {
    if (config.connection.type !== 'ssh') {
        socket.emit('log', { message: 'Skipping deployment verification (SSH required)' });
        return;
    }

    const ssh = new NodeSSH();
    await connectSSH(ssh, config.connection);

    socket.emit('log', { message: 'Waiting for services to start...' });
    await new Promise(resolve => setTimeout(resolve, 10000));

    socket.emit('log', { message: 'Running health check...' });
    const healthResult = await ssh.execCommand(`cd ${config.deployment.installPath} && npm run health-check`);

    if (healthResult.code !== 0) {
        throw new Error(`Health check failed: ${healthResult.stderr}`);
    }

    socket.emit('log', { message: 'Deployment verification successful!' });

    await ssh.dispose();
}

async function createDeploymentPackage(config) {
    const packageDir = path.join(__dirname, '../../tmp/deployment');
    const packageFile = path.join(__dirname, '../../tmp/flexpbx-deployment.tar.gz');

    // Ensure tmp directory exists
    const tmpDir = path.dirname(packageDir);
    if (!fs.existsSync(tmpDir)) {
        fs.mkdirSync(tmpDir, { recursive: true });
    }

    // Copy project files
    const projectRoot = path.join(__dirname, '../..');
    await execAsync(`cp -r ${projectRoot}/* ${packageDir}/`);

    // Generate environment file
    const envContent = generateEnvFile(config);
    fs.writeFileSync(path.join(packageDir, '.env'), envContent);

    // Create package
    await execAsync(`cd ${path.dirname(packageDir)} && tar -czf ${packageFile} deployment/`);

    return packageFile;
}

function generateEnvFile(config) {
    const { deployment } = config;

    const secrets = {
        jwtSecret: crypto.randomBytes(32).toString('hex'),
        sessionSecret: crypto.randomBytes(32).toString('hex'),
        adminPassword: generatePassword(),
        amiPassword: generatePassword()
    };

    const env = [
        '# FlexPBX Auto-Generated Configuration',
        `# Generated: ${new Date().toISOString()}`,
        '',
        '# Server Configuration',
        'NODE_ENV=production',
        'PORT=3000',
        `DOMAIN_NAME=${deployment.domainName || config.connection.host}`,
        `SSL_ENABLED=${deployment.sslEnabled !== 'false'}`,
        '',
        '# Database Configuration',
        `DB_TYPE=${deployment.databaseType}`,
    ];

    if (deployment.databaseType !== 'sqlite') {
        env.push(`DB_HOST=${deployment.dbHost}`);
        env.push(`DB_USER=${deployment.dbUsername}`);
        env.push(`DB_PASSWORD=${deployment.dbPassword}`);
        env.push(`DB_NAME=${deployment.dbName}`);
    }

    env.push('',
        '# Security',
        `JWT_SECRET=${secrets.jwtSecret}`,
        `SESSION_SECRET=${secrets.sessionSecret}`,
        `AMI_PASSWORD=${secrets.amiPassword}`,
        '',
        '# Default Credentials',
        `DEFAULT_ADMIN_PASSWORD=${secrets.adminPassword}`,
        '',
        '# Accessibility Features',
        'ACCESSIBILITY_ENABLED=true',
        'SCREEN_READER_SUPPORT=true',
        'AUDIO_FEEDBACK_ENABLED=true',
        'VOICE_ANNOUNCEMENTS_ENABLED=true'
    );

    return env.join('\n');
}

function generateSystemdService(installPath) {
    return `[Unit]
Description=FlexPBX Service
After=docker.service
Requires=docker.service

[Service]
Type=simple
WorkingDirectory=${installPath}
ExecStart=/usr/bin/docker-compose up
ExecStop=/usr/bin/docker-compose down
Restart=always
RestartSec=10
User=root

[Install]
WantedBy=multi-user.target`;
}

async function connectSSH(ssh, connectionData) {
    const config = {
        host: connectionData.host,
        port: connectionData.port || 22,
        username: connectionData.username
    };

    if (connectionData.authMethod === 'password') {
        config.password = connectionData.password;
    } else {
        config.privateKeyPath = connectionData.keyPath;
        if (connectionData.keyPassphrase) {
            config.passphrase = connectionData.keyPassphrase;
        }
    }

    await ssh.connect(config);
}

function generatePassword(length = 16) {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*';
    let password = '';
    for (let i = 0; i < length; i++) {
        password += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    return password;
}

function execAsync(command) {
    return new Promise((resolve, reject) => {
        const child = spawn('sh', ['-c', command]);

        let stdout = '';
        let stderr = '';

        child.stdout.on('data', (data) => {
            stdout += data;
        });

        child.stderr.on('data', (data) => {
            stderr += data;
        });

        child.on('close', (code) => {
            if (code === 0) {
                resolve({ stdout, stderr });
            } else {
                reject(new Error(`Command failed with code ${code}: ${stderr}`));
            }
        });
    });
}

function getDeploymentStatus(id) {
    // In a real implementation, this would fetch from database
    return {
        id,
        status: 'unknown',
        message: 'Status tracking not implemented'
    };
}

module.exports = router;