const { EventEmitter } = require('events');
const { spawn, exec } = require('child_process');
const path = require('path');
const fs = require('fs-extra');
const os = require('os');

class BackgroundServiceManager extends EventEmitter {
    constructor() {
        super();
        this.services = new Map();
        this.isRunning = false;
        this.serviceConfig = {
            flexpbx: {
                name: 'FlexPBX Core',
                port: 8080,
                process: null,
                autoRestart: true,
                startCommand: null
            },
            discovery: {
                name: 'Device Discovery',
                port: 41235,
                process: null,
                autoRestart: true,
                startCommand: null
            },
            copyparty: {
                name: 'CopyParty File Server',
                port: 3923,
                process: null,
                autoRestart: true,
                startCommand: null
            },
            ssh: {
                name: 'SSH Connection Manager',
                port: null,
                process: null,
                autoRestart: true,
                startCommand: null
            }
        };

        this.setupServiceCommands();
    }

    setupServiceCommands() {
        const platform = os.platform();
        const homeDir = os.homedir();
        const flexpbxDir = path.join(homeDir, '.flexpbx');

        // Create FlexPBX directory if it doesn't exist
        fs.ensureDirSync(flexpbxDir);

        // Platform-specific service commands
        if (platform === 'darwin') {
            this.serviceConfig.flexpbx.startCommand = this.getFlexPBXStartCommand();
            this.serviceConfig.discovery.startCommand = this.getDiscoveryStartCommand();
            this.serviceConfig.copyparty.startCommand = this.getCopyPartyStartCommand();
            this.serviceConfig.ssh.startCommand = this.getSSHManagerStartCommand();
        } else if (platform === 'linux') {
            // Linux service commands
            this.setupLinuxServices();
        } else if (platform === 'win32') {
            // Windows service commands
            this.setupWindowsServices();
        }
    }

    getFlexPBXStartCommand() {
        // Simple HTTP server for FlexPBX admin interface
        return {
            command: 'node',
            args: ['-e', `
                const http = require('http');
                const fs = require('fs');
                const path = require('path');

                const server = http.createServer((req, res) => {
                    if (req.url === '/') {
                        res.writeHead(200, { 'Content-Type': 'text/html' });
                        res.end('<h1>FlexPBX Admin Interface</h1><p>Service running on port 8080</p>');
                    } else if (req.url === '/status') {
                        res.writeHead(200, { 'Content-Type': 'application/json' });
                        res.end(JSON.stringify({ status: 'running', services: ['flexpbx', 'discovery', 'copyparty'] }));
                    } else {
                        res.writeHead(404);
                        res.end('Not Found');
                    }
                });

                server.listen(8080, () => {
                    console.log('FlexPBX Admin interface running on http://localhost:8080');
                });

                process.on('SIGTERM', () => {
                    server.close(() => {
                        process.exit(0);
                    });
                });
            `]
        };
    }

    getDiscoveryStartCommand() {
        // UDP discovery service
        return {
            command: 'node',
            args: ['-e', `
                const dgram = require('dgram');
                const server = dgram.createSocket('udp4');

                server.on('message', (msg, rinfo) => {
                    console.log('Discovery request from:', rinfo.address);
                    try {
                        const request = JSON.parse(msg.toString());
                        if (request.type === 'DISCOVER_FLEXPBX') {
                            const response = {
                                type: 'FLEXPBX_ANNOUNCEMENT',
                                device: {
                                    name: require('os').hostname(),
                                    ip: getLocalIP(),
                                    port: 8080,
                                    version: '2.0.0',
                                    capabilities: ['admin', 'pbx', 'recording', 'copyparty']
                                }
                            };

                            server.send(JSON.stringify(response), rinfo.port, rinfo.address);
                        }
                    } catch (e) {
                        console.log('Invalid discovery request');
                    }
                });

                server.on('listening', () => {
                    console.log('FlexPBX Discovery service listening on port 41235');
                });

                server.bind(41235);

                function getLocalIP() {
                    const interfaces = require('os').networkInterfaces();
                    for (const name of Object.keys(interfaces)) {
                        for (const iface of interfaces[name]) {
                            if (iface.family === 'IPv4' && !iface.internal) {
                                return iface.address;
                            }
                        }
                    }
                    return '127.0.0.1';
                }

                process.on('SIGTERM', () => {
                    server.close(() => {
                        process.exit(0);
                    });
                });
            `]
        };
    }

    getCopyPartyStartCommand() {
        // CopyParty file server command
        const homeDir = os.homedir();
        const shareDir = path.join(homeDir, 'FlexPBX-Share');

        // Ensure share directory exists
        fs.ensureDirSync(shareDir);

        return {
            command: 'python3',
            args: ['-m', 'copyparty', '--port', '3923', '--host', '0.0.0.0', shareDir],
            fallback: this.getCopyPartyFallbackCommand()
        };
    }

    getCopyPartyFallbackCommand() {
        // Fallback simple file server if copyparty not available
        const homeDir = os.homedir();
        const shareDir = path.join(homeDir, 'FlexPBX-Share');

        return {
            command: 'node',
            args: ['-e', `
                const http = require('http');
                const fs = require('fs');
                const path = require('path');
                const url = require('url');

                const shareDir = '${shareDir}';

                const server = http.createServer((req, res) => {
                    const parsedUrl = url.parse(req.url, true);
                    const pathname = parsedUrl.pathname;

                    if (pathname === '/') {
                        res.writeHead(200, { 'Content-Type': 'text/html' });
                        res.end('<h1>FlexPBX File Server</h1><p>Upload and download files</p><p>Share directory: ${shareDir}</p>');
                    } else if (pathname === '/upload' && req.method === 'POST') {
                        // Handle file upload
                        let body = '';
                        req.on('data', chunk => body += chunk);
                        req.on('end', () => {
                            res.writeHead(200, { 'Content-Type': 'application/json' });
                            res.end(JSON.stringify({ success: true, message: 'Upload endpoint ready' }));
                        });
                    } else if (pathname.startsWith('/files/')) {
                        // Serve files
                        const filePath = path.join(shareDir, pathname.substring(7));
                        if (fs.existsSync(filePath)) {
                            const stat = fs.statSync(filePath);
                            if (stat.isFile()) {
                                res.writeHead(200);
                                fs.createReadStream(filePath).pipe(res);
                            } else {
                                res.writeHead(404);
                                res.end('Not Found');
                            }
                        } else {
                            res.writeHead(404);
                            res.end('Not Found');
                        }
                    } else {
                        res.writeHead(404);
                        res.end('Not Found');
                    }
                });

                server.listen(3923, () => {
                    console.log('FlexPBX File Server running on http://localhost:3923');
                    console.log('Share directory:', shareDir);
                });

                process.on('SIGTERM', () => {
                    server.close(() => {
                        process.exit(0);
                    });
                });
            `]
        };
    }

    getSSHManagerStartCommand() {
        // SSH connection manager service
        return {
            command: 'node',
            args: ['-e', `
                console.log('SSH Connection Manager service started');

                // Keep process alive
                setInterval(() => {
                    // Heartbeat
                }, 30000);

                process.on('SIGTERM', () => {
                    console.log('SSH Connection Manager stopping');
                    process.exit(0);
                });
            `]
        };
    }

    setupLinuxServices() {
        // Linux-specific service setup
        console.log('Setting up Linux services...');
    }

    setupWindowsServices() {
        // Windows-specific service setup
        console.log('Setting up Windows services...');
    }

    async startAll() {
        console.log('🚀 Starting all background services...');
        this.isRunning = true;

        const results = {};

        for (const [serviceName, config] of Object.entries(this.serviceConfig)) {
            try {
                const result = await this.startService(serviceName);
                results[serviceName] = result;
            } catch (error) {
                console.error(`Failed to start ${serviceName}:`, error);
                results[serviceName] = { success: false, error: error.message };
            }
        }

        this.emit('all-services-started', results);
        return { success: true, services: results };
    }

    async stopAll() {
        console.log('🛑 Stopping all background services...');
        this.isRunning = false;

        const results = {};

        for (const [serviceName, config] of Object.entries(this.serviceConfig)) {
            try {
                const result = await this.stopService(serviceName);
                results[serviceName] = result;
            } catch (error) {
                console.error(`Failed to stop ${serviceName}:`, error);
                results[serviceName] = { success: false, error: error.message };
            }
        }

        this.emit('all-services-stopped', results);
        return { success: true, services: results };
    }

    async startService(serviceName) {
        const config = this.serviceConfig[serviceName];
        if (!config) {
            throw new Error(`Service ${serviceName} not found`);
        }

        if (config.process && !config.process.killed) {
            return { success: true, message: `${config.name} already running`, pid: config.process.pid };
        }

        console.log(`Starting ${config.name}...`);

        const startCommand = config.startCommand;
        if (!startCommand) {
            throw new Error(`No start command defined for ${serviceName}`);
        }

        try {
            // Try primary command
            config.process = spawn(startCommand.command, startCommand.args, {
                detached: false,
                stdio: ['ignore', 'pipe', 'pipe']
            });

            config.process.stdout.on('data', (data) => {
                console.log(`[${serviceName}] ${data.toString().trim()}`);
            });

            config.process.stderr.on('data', (data) => {
                console.error(`[${serviceName}] ${data.toString().trim()}`);
            });

            config.process.on('error', (error) => {
                console.error(`[${serviceName}] Process error:`, error);

                // Try fallback command if available
                if (startCommand.fallback) {
                    console.log(`Trying fallback for ${serviceName}...`);
                    this.startServiceFallback(serviceName, startCommand.fallback);
                } else if (config.autoRestart && this.isRunning) {
                    console.log(`Auto-restarting ${serviceName} in 5 seconds...`);
                    setTimeout(() => {
                        if (this.isRunning) {
                            this.startService(serviceName);
                        }
                    }, 5000);
                }
            });

            config.process.on('exit', (code, signal) => {
                console.log(`[${serviceName}] Process exited with code ${code}, signal ${signal}`);

                if (config.autoRestart && this.isRunning && code !== 0) {
                    console.log(`Auto-restarting ${serviceName} in 5 seconds...`);
                    setTimeout(() => {
                        if (this.isRunning) {
                            this.startService(serviceName);
                        }
                    }, 5000);
                }
            });

            // Wait a bit to see if process starts successfully
            await new Promise(resolve => setTimeout(resolve, 1000));

            if (config.process.killed) {
                throw new Error(`Failed to start ${config.name}`);
            }

            this.services.set(serviceName, config);
            this.emit('service-started', { name: serviceName, pid: config.process.pid });

            return {
                success: true,
                message: `${config.name} started successfully`,
                pid: config.process.pid,
                port: config.port
            };

        } catch (error) {
            console.error(`Failed to start ${serviceName}:`, error);

            // Try fallback if available
            if (startCommand.fallback) {
                return this.startServiceFallback(serviceName, startCommand.fallback);
            }

            throw error;
        }
    }

    async startServiceFallback(serviceName, fallbackCommand) {
        const config = this.serviceConfig[serviceName];
        console.log(`Starting ${config.name} with fallback command...`);

        config.process = spawn(fallbackCommand.command, fallbackCommand.args, {
            detached: false,
            stdio: ['ignore', 'pipe', 'pipe']
        });

        config.process.stdout.on('data', (data) => {
            console.log(`[${serviceName}-fallback] ${data.toString().trim()}`);
        });

        config.process.stderr.on('data', (data) => {
            console.error(`[${serviceName}-fallback] ${data.toString().trim()}`);
        });

        config.process.on('error', (error) => {
            console.error(`[${serviceName}-fallback] Process error:`, error);
        });

        config.process.on('exit', (code, signal) => {
            console.log(`[${serviceName}-fallback] Process exited with code ${code}, signal ${signal}`);
        });

        await new Promise(resolve => setTimeout(resolve, 1000));

        return {
            success: true,
            message: `${config.name} started with fallback`,
            pid: config.process.pid,
            port: config.port,
            fallback: true
        };
    }

    async stopService(serviceName) {
        const config = this.serviceConfig[serviceName];
        if (!config) {
            throw new Error(`Service ${serviceName} not found`);
        }

        if (!config.process || config.process.killed) {
            return { success: true, message: `${config.name} not running` };
        }

        console.log(`Stopping ${config.name}...`);

        // Graceful shutdown
        config.process.kill('SIGTERM');

        // Wait for graceful shutdown
        await new Promise(resolve => setTimeout(resolve, 2000));

        // Force kill if still running
        if (!config.process.killed) {
            config.process.kill('SIGKILL');
        }

        config.process = null;
        this.services.delete(serviceName);
        this.emit('service-stopped', { name: serviceName });

        return {
            success: true,
            message: `${config.name} stopped successfully`
        };
    }

    async restartService(serviceName) {
        await this.stopService(serviceName);
        await new Promise(resolve => setTimeout(resolve, 1000));
        return await this.startService(serviceName);
    }

    getStatus() {
        const services = {};

        for (const [serviceName, config] of Object.entries(this.serviceConfig)) {
            services[serviceName] = {
                name: config.name,
                running: config.process && !config.process.killed,
                pid: config.process?.pid || null,
                port: config.port,
                autoRestart: config.autoRestart
            };
        }

        return {
            running: this.isRunning,
            services
        };
    }

    async installSystemServices() {
        const platform = os.platform();

        if (platform === 'darwin') {
            return this.installMacOSServices();
        } else if (platform === 'linux') {
            return this.installLinuxServices();
        } else if (platform === 'win32') {
            return this.installWindowsServices();
        } else {
            throw new Error(`Platform ${platform} not supported for system service installation`);
        }
    }

    async installMacOSServices() {
        // Create LaunchAgent plist files for macOS
        const homeDir = os.homedir();
        const launchAgentsDir = path.join(homeDir, 'Library', 'LaunchAgents');

        await fs.ensureDir(launchAgentsDir);

        const plistContent = `<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>Label</key>
    <string>com.flexpbx.background-services</string>
    <key>ProgramArguments</key>
    <array>
        <string>node</string>
        <string>${__filename}</string>
        <string>--daemon</string>
    </array>
    <key>RunAtLoad</key>
    <true/>
    <key>KeepAlive</key>
    <true/>
    <key>StandardOutPath</key>
    <string>${homeDir}/.flexpbx/logs/background-services.log</string>
    <key>StandardErrorPath</key>
    <string>${homeDir}/.flexpbx/logs/background-services-error.log</string>
</dict>
</plist>`;

        const plistPath = path.join(launchAgentsDir, 'com.flexpbx.background-services.plist');
        await fs.writeFile(plistPath, plistContent);

        // Load the service
        return new Promise((resolve, reject) => {
            exec(`launchctl load ${plistPath}`, (error, stdout, stderr) => {
                if (error) {
                    reject(error);
                } else {
                    resolve({
                        success: true,
                        message: 'Background services installed and loaded',
                        plistPath
                    });
                }
            });
        });
    }

    async installLinuxServices() {
        // Create systemd service files for Linux
        const serviceContent = `[Unit]
Description=FlexPBX Background Services
After=network.target

[Service]
Type=simple
User=${os.userInfo().username}
WorkingDirectory=${process.cwd()}
ExecStart=node ${__filename} --daemon
Restart=always
RestartSec=5
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target`;

        const servicePath = '/etc/systemd/system/flexpbx-background.service';

        try {
            await fs.writeFile(servicePath, serviceContent);

            return new Promise((resolve, reject) => {
                exec('systemctl daemon-reload && systemctl enable flexpbx-background.service', (error) => {
                    if (error) {
                        reject(error);
                    } else {
                        resolve({
                            success: true,
                            message: 'Background services installed for systemd',
                            servicePath
                        });
                    }
                });
            });
        } catch (error) {
            throw new Error(`Failed to install Linux service: ${error.message}`);
        }
    }

    async installWindowsServices() {
        // Windows service installation would require additional tools
        throw new Error('Windows service installation not yet implemented');
    }
}

// Allow running as daemon
if (require.main === module && process.argv.includes('--daemon')) {
    console.log('Starting FlexPBX Background Services in daemon mode...');

    const manager = new BackgroundServiceManager();

    manager.startAll().then(() => {
        console.log('All background services started in daemon mode');
    }).catch(error => {
        console.error('Failed to start background services:', error);
        process.exit(1);
    });

    process.on('SIGTERM', async () => {
        console.log('Received SIGTERM, stopping services...');
        await manager.stopAll();
        process.exit(0);
    });

    process.on('SIGINT', async () => {
        console.log('Received SIGINT, stopping services...');
        await manager.stopAll();
        process.exit(0);
    });
}

module.exports = BackgroundServiceManager;