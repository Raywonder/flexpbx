const dgram = require('dgram');
const net = require('net');
const fs = require('fs-extra');
const path = require('path');
const os = require('os');
const crypto = require('crypto');
const { EventEmitter } = require('events');

class DiscoveryService extends EventEmitter {
    constructor() {
        super();
        this.discoveryPort = 41234; // UDP port for discovery
        this.servicePort = 41235;   // TCP port for service
        this.broadcastInterval = 5000; // 5 seconds
        this.isRunning = false;
        this.discoveredDevices = new Map();
        this.authToken = crypto.randomBytes(32).toString('hex');

        this.udpServer = null;
        this.tcpServer = null;
        this.broadcastTimer = null;

        this.deviceInfo = {
            id: this.generateDeviceId(),
            name: os.hostname(),
            platform: os.platform(),
            type: 'flexpbx-desktop',
            version: '1.0.0',
            capabilities: ['remote-install', 'file-transfer', 'monitoring'],
            timestamp: Date.now()
        };
    }

    /**
     * Generate unique device ID
     */
    generateDeviceId() {
        const interfaces = os.networkInterfaces();
        let macAddress = '';

        for (const iface of Object.values(interfaces)) {
            for (const config of iface) {
                if (!config.internal && config.mac && config.mac !== '00:00:00:00:00:00') {
                    macAddress = config.mac;
                    break;
                }
            }
            if (macAddress) break;
        }

        return crypto.createHash('sha256')
            .update(macAddress + os.hostname())
            .digest('hex')
            .substring(0, 16);
    }

    /**
     * Start discovery service
     */
    async startService() {
        if (this.isRunning) {
            return { success: true, message: 'Service already running' };
        }

        try {
            await this.startUDPServer();
            await this.startTCPServer();
            this.startBroadcasting();

            this.isRunning = true;

            console.log(`🔍 Discovery service started on UDP:${this.discoveryPort}, TCP:${this.servicePort}`);
            console.log(`📱 Device ID: ${this.deviceInfo.id}`);
            console.log(`🏷️  Device Name: ${this.deviceInfo.name}`);

            return {
                success: true,
                deviceInfo: this.deviceInfo,
                ports: {
                    discovery: this.discoveryPort,
                    service: this.servicePort
                }
            };
        } catch (error) {
            return {
                success: false,
                error: error.message
            };
        }
    }

    /**
     * Stop discovery service
     */
    async stopService() {
        this.isRunning = false;

        if (this.broadcastTimer) {
            clearInterval(this.broadcastTimer);
            this.broadcastTimer = null;
        }

        if (this.udpServer) {
            this.udpServer.close();
            this.udpServer = null;
        }

        if (this.tcpServer) {
            this.tcpServer.close();
            this.tcpServer = null;
        }

        console.log('🛑 Discovery service stopped');
    }

    /**
     * Start UDP server for device discovery
     */
    async startUDPServer() {
        return new Promise((resolve, reject) => {
            this.udpServer = dgram.createSocket('udp4');

            this.udpServer.on('message', (msg, rinfo) => {
                this.handleDiscoveryMessage(msg, rinfo);
            });

            this.udpServer.on('error', (err) => {
                reject(err);
            });

            this.udpServer.bind(this.discoveryPort, () => {
                this.udpServer.setBroadcast(true);
                resolve();
            });
        });
    }

    /**
     * Start TCP server for file transfer and commands
     */
    async startTCPServer() {
        return new Promise((resolve, reject) => {
            this.tcpServer = net.createServer((socket) => {
                this.handleClientConnection(socket);
            });

            this.tcpServer.on('error', (err) => {
                reject(err);
            });

            this.tcpServer.listen(this.servicePort, () => {
                resolve();
            });
        });
    }

    /**
     * Start broadcasting device info
     */
    startBroadcasting() {
        this.broadcastTimer = setInterval(() => {
            this.broadcastDeviceInfo();
        }, this.broadcastInterval);

        // Immediate first broadcast
        this.broadcastDeviceInfo();
    }

    /**
     * Broadcast device information
     */
    broadcastDeviceInfo() {
        if (!this.udpServer) return;

        const message = JSON.stringify({
            type: 'DEVICE_ANNOUNCE',
            device: {
                ...this.deviceInfo,
                timestamp: Date.now(),
                servicePort: this.servicePort
            }
        });

        const buffer = Buffer.from(message);

        // Broadcast to local network
        this.udpServer.send(buffer, 0, buffer.length, this.discoveryPort, '255.255.255.255', (err) => {
            if (err) {
                console.warn('Broadcast error:', err.message);
            }
        });
    }

    /**
     * Handle incoming discovery messages
     */
    handleDiscoveryMessage(msg, rinfo) {
        try {
            const message = JSON.parse(msg.toString());

            if (message.type === 'DEVICE_ANNOUNCE' && message.device) {
                const device = message.device;

                // Don't process our own announcements
                if (device.id === this.deviceInfo.id) {
                    return;
                }

                // Only interested in FlexPBX devices
                if (device.type !== 'flexpbx-desktop') {
                    return;
                }

                const deviceKey = `${device.id}@${rinfo.address}`;
                const existingDevice = this.discoveredDevices.get(deviceKey);

                // Update device info
                this.discoveredDevices.set(deviceKey, {
                    ...device,
                    address: rinfo.address,
                    lastSeen: Date.now(),
                    isOnline: true
                });

                // Emit event for new device or status change
                if (!existingDevice) {
                    this.emit('deviceDiscovered', {
                        ...device,
                        address: rinfo.address
                    });
                } else if (!existingDevice.isOnline) {
                    this.emit('deviceOnline', {
                        ...device,
                        address: rinfo.address
                    });
                }

                console.log(`📱 Discovered: ${device.name} (${device.platform}) at ${rinfo.address}:${device.servicePort}`);
            }

        } catch (error) {
            console.warn('Invalid discovery message:', error.message);
        }
    }

    /**
     * Handle client TCP connections
     */
    handleClientConnection(socket) {
        console.log(`🔗 Client connected from ${socket.remoteAddress}`);

        let buffer = '';

        socket.on('data', (data) => {
            buffer += data.toString();

            // Process complete messages (assuming newline-delimited JSON)
            const lines = buffer.split('\n');
            buffer = lines.pop() || ''; // Keep incomplete line in buffer

            for (const line of lines) {
                if (line.trim()) {
                    this.handleClientMessage(socket, line.trim());
                }
            }
        });

        socket.on('error', (err) => {
            console.error('Client connection error:', err.message);
        });

        socket.on('close', () => {
            console.log(`🔌 Client disconnected from ${socket.remoteAddress}`);
        });

        // Send welcome message
        this.sendToClient(socket, {
            type: 'WELCOME',
            device: this.deviceInfo,
            timestamp: Date.now()
        });
    }

    /**
     * Handle client messages
     */
    handleClientMessage(socket, message) {
        try {
            const msg = JSON.parse(message);

            switch (msg.type) {
                case 'PING':
                    this.sendToClient(socket, {
                        type: 'PONG',
                        timestamp: Date.now()
                    });
                    break;

                case 'GET_DEVICE_INFO':
                    this.sendToClient(socket, {
                        type: 'DEVICE_INFO',
                        device: this.deviceInfo
                    });
                    break;

                case 'GET_SYSTEM_INFO':
                    this.handleSystemInfoRequest(socket);
                    break;

                case 'UPLOAD_FILE':
                    this.handleFileUpload(socket, msg);
                    break;

                case 'DOWNLOAD_FILE':
                    this.handleFileDownload(socket, msg);
                    break;

                case 'EXECUTE_COMMAND':
                    this.handleCommandExecution(socket, msg);
                    break;

                case 'GET_INSTALLATION_PATHS':
                    this.handlePathDetection(socket, msg);
                    break;

                default:
                    this.sendToClient(socket, {
                        type: 'ERROR',
                        message: `Unknown message type: ${msg.type}`
                    });
            }

        } catch (error) {
            this.sendToClient(socket, {
                type: 'ERROR',
                message: `Invalid message format: ${error.message}`
            });
        }
    }

    /**
     * Send message to client
     */
    sendToClient(socket, message) {
        const data = JSON.stringify(message) + '\n';
        socket.write(data);
    }

    /**
     * Handle system info request
     */
    handleSystemInfoRequest(socket) {
        const systemInfo = {
            hostname: os.hostname(),
            platform: os.platform(),
            arch: os.arch(),
            release: os.release(),
            cpus: os.cpus().length,
            memory: {
                total: os.totalmem(),
                free: os.freemem()
            },
            networkInterfaces: this.getNetworkInterfaces(),
            uptime: os.uptime()
        };

        this.sendToClient(socket, {
            type: 'SYSTEM_INFO',
            systemInfo
        });
    }

    /**
     * Get network interfaces info
     */
    getNetworkInterfaces() {
        const interfaces = os.networkInterfaces();
        const result = {};

        for (const [name, configs] of Object.entries(interfaces)) {
            result[name] = configs
                .filter(config => !config.internal)
                .map(config => ({
                    address: config.address,
                    family: config.family,
                    mac: config.mac
                }));
        }

        return result;
    }

    /**
     * Handle file upload
     */
    async handleFileUpload(socket, msg) {
        try {
            const { filename, content, destination } = msg;

            if (!filename || !content) {
                throw new Error('Missing filename or content');
            }

            // Create safe destination path
            const uploadDir = path.join(os.homedir(), '.flexpbx', 'uploads');
            await fs.ensureDir(uploadDir);

            const safePath = path.join(uploadDir, path.basename(filename));

            // Decode and save file
            const fileData = Buffer.from(content, 'base64');
            await fs.writeFile(safePath, fileData);

            this.sendToClient(socket, {
                type: 'UPLOAD_SUCCESS',
                filename,
                path: safePath,
                size: fileData.length
            });

            console.log(`📁 File uploaded: ${filename} (${fileData.length} bytes)`);

        } catch (error) {
            this.sendToClient(socket, {
                type: 'UPLOAD_ERROR',
                error: error.message
            });
        }
    }

    /**
     * Handle file download
     */
    async handleFileDownload(socket, msg) {
        try {
            const { filepath } = msg;

            if (!filepath || !await fs.pathExists(filepath)) {
                throw new Error('File not found');
            }

            const stats = await fs.stat(filepath);
            const content = await fs.readFile(filepath);

            this.sendToClient(socket, {
                type: 'DOWNLOAD_SUCCESS',
                filename: path.basename(filepath),
                content: content.toString('base64'),
                size: stats.size
            });

            console.log(`📤 File downloaded: ${filepath} (${stats.size} bytes)`);

        } catch (error) {
            this.sendToClient(socket, {
                type: 'DOWNLOAD_ERROR',
                error: error.message
            });
        }
    }

    /**
     * Handle command execution
     */
    async handleCommandExecution(socket, msg) {
        try {
            const { command, args, options } = msg;

            if (!command) {
                throw new Error('No command specified');
            }

            const { exec } = require('child_process');
            const { promisify } = require('util');
            const execAsync = promisify(exec);

            // Only allow safe commands
            const allowedCommands = [
                'ls', 'dir', 'pwd', 'whoami', 'uname', 'hostname',
                'docker', 'npm', 'node', 'git', 'which', 'where'
            ];

            const baseCommand = command.split(' ')[0];
            if (!allowedCommands.includes(baseCommand)) {
                throw new Error(`Command not allowed: ${baseCommand}`);
            }

            const result = await execAsync(command, options);

            this.sendToClient(socket, {
                type: 'COMMAND_SUCCESS',
                command,
                stdout: result.stdout,
                stderr: result.stderr
            });

            console.log(`⚡ Command executed: ${command}`);

        } catch (error) {
            this.sendToClient(socket, {
                type: 'COMMAND_ERROR',
                command: msg.command,
                error: error.message
            });
        }
    }

    /**
     * Handle path detection request
     */
    async handlePathDetection(socket, msg) {
        try {
            const SafePathService = require('./SafePathService');
            const pathService = new SafePathService();

            const { installType, webAccessible, useCase } = msg;

            // Get local path recommendations
            const localPath = await pathService.detectLocalPath(installType);
            const suggestions = await pathService.generatePathSuggestions(null, {
                installType,
                webAccessible,
                useCase
            });

            this.sendToClient(socket, {
                type: 'INSTALLATION_PATHS',
                localPath,
                suggestions,
                platform: os.platform()
            });

            console.log(`📂 Path detection completed for ${installType} installation`);

        } catch (error) {
            this.sendToClient(socket, {
                type: 'PATH_DETECTION_ERROR',
                error: error.message
            });
        }
    }

    /**
     * Get list of discovered devices
     */
    getDiscoveredDevices() {
        const devices = [];
        const now = Date.now();

        for (const [key, device] of this.discoveredDevices) {
            // Consider device offline if not seen for 30 seconds
            const isOnline = (now - device.lastSeen) < 30000;

            if (device.isOnline !== isOnline) {
                device.isOnline = isOnline;
                this.discoveredDevices.set(key, device);

                if (isOnline) {
                    this.emit('deviceOnline', device);
                } else {
                    this.emit('deviceOffline', device);
                }
            }

            devices.push(device);
        }

        return devices;
    }

    /**
     * Connect to remote device
     */
    async connectToDevice(deviceAddress, devicePort) {
        return new Promise((resolve, reject) => {
            const socket = net.createConnection({
                host: deviceAddress,
                port: devicePort
            });

            let buffer = '';
            let welcomeReceived = false;

            socket.on('connect', () => {
                console.log(`🔗 Connected to ${deviceAddress}:${devicePort}`);
            });

            socket.on('data', (data) => {
                buffer += data.toString();

                const lines = buffer.split('\n');
                buffer = lines.pop() || '';

                for (const line of lines) {
                    if (line.trim()) {
                        try {
                            const msg = JSON.parse(line.trim());

                            if (msg.type === 'WELCOME' && !welcomeReceived) {
                                welcomeReceived = true;
                                resolve({
                                    success: true,
                                    socket,
                                    deviceInfo: msg.device
                                });
                            }
                        } catch (error) {
                            // Ignore malformed messages
                        }
                    }
                }
            });

            socket.on('error', (err) => {
                reject(err);
            });

            socket.on('close', () => {
                console.log(`🔌 Disconnected from ${deviceAddress}:${devicePort}`);
            });

            // Timeout after 10 seconds
            setTimeout(() => {
                if (!welcomeReceived) {
                    socket.destroy();
                    reject(new Error('Connection timeout'));
                }
            }, 10000);
        });
    }

    /**
     * Send command to remote device
     */
    async sendCommand(socket, command, data = {}) {
        return new Promise((resolve, reject) => {
            const message = JSON.stringify({
                type: command,
                ...data,
                timestamp: Date.now()
            });

            let responseReceived = false;

            const responseHandler = (responseData) => {
                let buffer = responseData.toString();
                const lines = buffer.split('\n');

                for (const line of lines) {
                    if (line.trim()) {
                        try {
                            const response = JSON.parse(line.trim());

                            if (response.type === command + '_SUCCESS' ||
                                response.type === command + '_ERROR' ||
                                response.type === 'ERROR') {

                                responseReceived = true;
                                socket.removeListener('data', responseHandler);

                                if (response.type.includes('ERROR')) {
                                    reject(new Error(response.error || response.message));
                                } else {
                                    resolve(response);
                                }
                                return;
                            }
                        } catch (error) {
                            // Ignore malformed responses
                        }
                    }
                }
            };

            socket.on('data', responseHandler);
            socket.write(message + '\n');

            // Timeout after 30 seconds
            setTimeout(() => {
                if (!responseReceived) {
                    socket.removeListener('data', responseHandler);
                    reject(new Error('Command timeout'));
                }
            }, 30000);
        });
    }

    /**
     * Create connection file for easy sharing
     */
    async createConnectionFile(outputPath) {
        const connectionInfo = {
            deviceId: this.deviceInfo.id,
            deviceName: this.deviceInfo.name,
            platform: this.deviceInfo.platform,
            version: this.deviceInfo.version,
            discoveryPort: this.discoveryPort,
            servicePort: this.servicePort,
            authToken: this.authToken,
            created: new Date().toISOString(),
            instructions: {
                en: "Save this file and import it in FlexPBX Desktop to automatically connect to this device",
                usage: "File -> Import Connection File"
            }
        };

        await fs.writeFile(outputPath, JSON.stringify(connectionInfo, null, 2));

        return {
            success: true,
            filePath: outputPath,
            deviceInfo: this.deviceInfo
        };
    }
}

module.exports = DiscoveryService;