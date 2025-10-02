const { EventEmitter } = require('events');
const QRCode = require('qrcode');
const fs = require('fs-extra');
const path = require('path');
const os = require('os');

class ConnectionManager extends EventEmitter {
    constructor() {
        super();
        this.discoveryService = null;
        this.networkScanService = null;
        this.activeConnections = new Map();
        this.savedServers = new Map();
        this.isDiscovering = false;

        this.initializeServices();
        this.loadSavedServers();
    }

    /**
     * Initialize discovery services
     */
    async initializeServices() {
        try {
            const DiscoveryService = require('./DiscoveryService');
            const NetworkScanService = require('./NetworkScanService');

            this.discoveryService = new DiscoveryService();
            this.networkScanService = new NetworkScanService();

            // Forward events
            this.discoveryService.on('deviceDiscovered', (device) => {
                this.handleDeviceDiscovered(device, 'auto-discovery');
            });

            this.networkScanService.on('deviceFound', (device) => {
                this.handleDeviceDiscovered(device, 'network-scan');
            });

            console.log('🔧 Connection services initialized');
        } catch (error) {
            console.error('Failed to initialize connection services:', error);
        }
    }

    /**
     * Start all discovery methods (Jellyfin-style)
     */
    async startDiscovery(options = {}) {
        const {
            autoDiscovery = true,
            networkScan = true,
            quickScan = true,
            timeout = 5000
        } = options;

        if (this.isDiscovering) {
            return { success: false, error: 'Discovery already in progress' };
        }

        this.isDiscovering = true;
        console.log('🔍 Starting FlexPBX server discovery...');
        this.emit('discoveryStarted');

        const results = {
            autoDiscovery: null,
            networkScan: null,
            totalFound: 0
        };

        try {
            const promises = [];

            // Method 1: Auto-discovery via UDP broadcast (like Jellyfin)
            if (autoDiscovery && this.discoveryService) {
                promises.push(this.startAutoDiscovery().then(result => {
                    results.autoDiscovery = result;
                    return result;
                }));
            }

            // Method 2: Network scanning
            if (networkScan && this.networkScanService) {
                promises.push(this.networkScanService.scanNetwork({
                    timeout,
                    quickScan
                }).then(result => {
                    results.networkScan = result;
                    return result;
                }));
            }

            // Wait for all discovery methods
            await Promise.allSettled(promises);

            // Combine results
            const allDevices = this.getCombinedDeviceList();
            results.totalFound = allDevices.length;

            this.isDiscovering = false;
            this.emit('discoveryCompleted', {
                devices: allDevices,
                methods: results
            });

            console.log(`✅ Discovery completed. Found ${results.totalFound} FlexPBX servers`);

            return {
                success: true,
                devices: allDevices,
                results
            };

        } catch (error) {
            this.isDiscovering = false;
            this.emit('discoveryError', error);
            return {
                success: false,
                error: error.message
            };
        }
    }

    /**
     * Start auto-discovery service
     */
    async startAutoDiscovery() {
        if (!this.discoveryService) {
            return { success: false, error: 'Discovery service not available' };
        }

        try {
            const result = await this.discoveryService.startService();

            // Wait a bit for devices to announce themselves
            await new Promise(resolve => setTimeout(resolve, 3000));

            return {
                success: true,
                method: 'auto-discovery',
                devices: this.discoveryService.getDiscoveredDevices()
            };
        } catch (error) {
            return {
                success: false,
                error: error.message
            };
        }
    }

    /**
     * Handle discovered device
     */
    handleDeviceDiscovered(device, method) {
        const deviceKey = `${device.ip || device.address}:${device.servicePort || device.port}`;

        // Enhance device info
        const enhancedDevice = {
            ...device,
            discoveryMethod: method,
            lastSeen: Date.now(),
            connectionKey: deviceKey,
            isOnline: true
        };

        // Add to discovered devices
        this.emit('serverFound', enhancedDevice);

        console.log(`📱 Found FlexPBX server: ${device.name || device.hostname || 'Unknown'} via ${method}`);
    }

    /**
     * Connect to server by IP (manual connection)
     */
    async connectByIP(ip, options = {}) {
        const {
            port = 41235,
            timeout = 10000,
            autoSave = true
        } = options;

        console.log(`🔗 Attempting manual connection to ${ip}:${port}`);

        try {
            // First, test if it's a FlexPBX server
            const testResult = await this.networkScanService.testFlexPBXConnection(ip, port);

            if (!testResult.success) {
                throw new Error(`Not a FlexPBX server: ${testResult.error}`);
            }

            // Get detailed device info
            const deviceInfo = await this.networkScanService.getDeviceCapabilities(ip, port);

            if (!deviceInfo.success) {
                throw new Error(`Failed to get device info: ${deviceInfo.error}`);
            }

            // Create connection
            const connection = await this.discoveryService.connectToDevice(ip, port);

            if (!connection.success) {
                throw new Error('Failed to establish connection');
            }

            const serverInfo = {
                id: deviceInfo.deviceInfo?.id || `manual_${ip}`,
                name: deviceInfo.deviceInfo?.name || `FlexPBX@${ip}`,
                ip: ip,
                port: port,
                platform: deviceInfo.systemInfo?.platform || 'unknown',
                version: deviceInfo.deviceInfo?.version || 'unknown',
                capabilities: deviceInfo.capabilities || [],
                discoveryMethod: 'manual',
                connection: connection.socket,
                connected: true,
                connectedAt: Date.now()
            };

            // Store active connection
            this.activeConnections.set(serverInfo.id, serverInfo);

            // Auto-save if requested
            if (autoSave) {
                await this.saveServer(serverInfo);
            }

            this.emit('serverConnected', serverInfo);
            console.log(`✅ Connected to FlexPBX server: ${serverInfo.name}`);

            return {
                success: true,
                server: serverInfo
            };

        } catch (error) {
            console.error(`❌ Connection failed to ${ip}:${port} - ${error.message}`);
            return {
                success: false,
                error: error.message
            };
        }
    }

    /**
     * Connect using connection file or QR code data
     */
    async connectFromConnectionData(connectionData) {
        try {
            let serverData;

            if (typeof connectionData === 'string') {
                // Try to parse as JSON (from file or QR code)
                try {
                    serverData = JSON.parse(connectionData);
                } catch (e) {
                    // Maybe it's a simple IP:PORT format
                    const [ip, port] = connectionData.split(':');
                    return await this.connectByIP(ip, { port: parseInt(port) || 41235 });
                }
            } else {
                serverData = connectionData;
            }

            // Validate connection data
            if (!serverData.deviceId || !serverData.servicePort) {
                throw new Error('Invalid connection data format');
            }

            // Try to discover the device on the network first
            const discoveredDevices = this.getCombinedDeviceList();
            const matchingDevice = discoveredDevices.find(d =>
                d.id === serverData.deviceId ||
                d.name === serverData.deviceName
            );

            if (matchingDevice) {
                console.log('📡 Found device via discovery, connecting...');
                return await this.connectToDiscoveredDevice(matchingDevice);
            }

            // If not found via discovery, try direct connection
            // We'll need to scan for the device or use stored IP
            console.log('🔍 Device not found via discovery, attempting direct connection...');

            // For now, we'll need an IP address in the connection data
            if (!serverData.lastKnownIP) {
                throw new Error('Device not found on network and no IP address available');
            }

            return await this.connectByIP(serverData.lastKnownIP, {
                port: serverData.servicePort,
                autoSave: false // Don't auto-save since we're importing
            });

        } catch (error) {
            return {
                success: false,
                error: error.message
            };
        }
    }

    /**
     * Connect to discovered device
     */
    async connectToDiscoveredDevice(device) {
        const ip = device.ip || device.address;
        const port = device.servicePort || device.port || 41235;

        return await this.connectByIP(ip, {
            port,
            autoSave: true
        });
    }

    /**
     * Generate connection file for sharing
     */
    async generateConnectionFile(outputPath, includeQR = true) {
        if (!this.discoveryService) {
            throw new Error('Discovery service not available');
        }

        const deviceInfo = this.discoveryService.deviceInfo;
        const networkInterfaces = os.networkInterfaces();
        const ipAddresses = [];

        // Get all IP addresses
        for (const [name, configs] of Object.entries(networkInterfaces)) {
            for (const config of configs) {
                if (!config.internal && config.family === 'IPv4') {
                    ipAddresses.push({
                        interface: name,
                        ip: config.address
                    });
                }
            }
        }

        const connectionData = {
            version: "1.0",
            type: "flexpbx-connection",
            deviceId: deviceInfo.id,
            deviceName: deviceInfo.name,
            platform: deviceInfo.platform,
            version: deviceInfo.version,
            discoveryPort: this.discoveryService.discoveryPort,
            servicePort: this.discoveryService.servicePort,
            ipAddresses: ipAddresses,
            lastKnownIP: ipAddresses[0]?.ip,
            capabilities: deviceInfo.capabilities,
            created: new Date().toISOString(),
            instructions: {
                en: "Import this file in FlexPBX Desktop to connect to this server",
                steps: [
                    "1. Open FlexPBX Desktop",
                    "2. Go to Server Manager",
                    "3. Click 'Import Connection File'",
                    "4. Select this file"
                ]
            }
        };

        // Write connection file
        await fs.writeFile(outputPath, JSON.stringify(connectionData, null, 2));

        let qrCodePath = null;
        if (includeQR) {
            // Generate QR code
            qrCodePath = outputPath.replace(/\.[^.]+$/, '_qr.png');
            await QRCode.toFile(qrCodePath, JSON.stringify(connectionData));
        }

        return {
            success: true,
            connectionFile: outputPath,
            qrCode: qrCodePath,
            deviceInfo: deviceInfo,
            connectionData: connectionData
        };
    }

    /**
     * Import connection file
     */
    async importConnectionFile(filePath) {
        try {
            const content = await fs.readFile(filePath, 'utf8');
            const connectionData = JSON.parse(content);

            // Validate format
            if (connectionData.type !== 'flexpbx-connection') {
                throw new Error('Invalid connection file format');
            }

            // Save to servers list
            const serverInfo = {
                id: connectionData.deviceId,
                name: connectionData.deviceName,
                platform: connectionData.platform,
                version: connectionData.version,
                lastKnownIP: connectionData.lastKnownIP,
                ipAddresses: connectionData.ipAddresses,
                servicePort: connectionData.servicePort,
                discoveryPort: connectionData.discoveryPort,
                capabilities: connectionData.capabilities || [],
                imported: true,
                importedAt: Date.now(),
                source: 'connection-file'
            };

            await this.saveServer(serverInfo);

            this.emit('serverImported', serverInfo);

            return {
                success: true,
                server: serverInfo,
                message: `Successfully imported ${serverInfo.name}`
            };

        } catch (error) {
            return {
                success: false,
                error: error.message
            };
        }
    }

    /**
     * Save server to persistent storage
     */
    async saveServer(serverInfo) {
        try {
            const configDir = path.join(os.homedir(), '.flexpbx');
            await fs.ensureDir(configDir);

            const serversFile = path.join(configDir, 'servers.json');

            // Load existing servers
            let servers = {};
            if (await fs.pathExists(serversFile)) {
                const content = await fs.readFile(serversFile, 'utf8');
                servers = JSON.parse(content);
            }

            // Add/update server
            servers[serverInfo.id] = {
                ...serverInfo,
                connection: undefined, // Don't save active connection
                savedAt: Date.now()
            };

            // Save to file
            await fs.writeFile(serversFile, JSON.stringify(servers, null, 2));
            await fs.chmod(serversFile, 0o600); // Secure permissions

            // Update in-memory cache
            this.savedServers.set(serverInfo.id, servers[serverInfo.id]);

            this.emit('serverSaved', serverInfo);
            console.log(`💾 Saved server: ${serverInfo.name}`);

            return { success: true };

        } catch (error) {
            return {
                success: false,
                error: error.message
            };
        }
    }

    /**
     * Load saved servers
     */
    async loadSavedServers() {
        try {
            const serversFile = path.join(os.homedir(), '.flexpbx', 'servers.json');

            if (await fs.pathExists(serversFile)) {
                const content = await fs.readFile(serversFile, 'utf8');
                const servers = JSON.parse(content);

                this.savedServers.clear();
                for (const [id, server] of Object.entries(servers)) {
                    this.savedServers.set(id, server);
                }

                console.log(`📚 Loaded ${this.savedServers.size} saved servers`);
            }

        } catch (error) {
            console.warn('Failed to load saved servers:', error.message);
        }
    }

    /**
     * Get combined device list from all discovery methods
     */
    getCombinedDeviceList() {
        const devices = [];
        const seenDevices = new Set();

        // Add auto-discovered devices
        if (this.discoveryService) {
            const autoDiscovered = this.discoveryService.getDiscoveredDevices();
            for (const device of autoDiscovered) {
                const key = `${device.address}:${device.servicePort}`;
                if (!seenDevices.has(key)) {
                    seenDevices.add(key);
                    devices.push({
                        ...device,
                        discoveryMethod: 'auto-discovery'
                    });
                }
            }
        }

        // Add network scan results
        if (this.networkScanService) {
            const scanResults = this.networkScanService.getScanStatus().devices;
            for (const device of scanResults) {
                const key = `${device.ip}:${device.ports.includes(41235) ? 41235 : device.ports[0]}`;
                if (!seenDevices.has(key) && device.isFlexPBX) {
                    seenDevices.add(key);
                    devices.push({
                        ...device,
                        address: device.ip,
                        servicePort: 41235,
                        discoveryMethod: 'network-scan'
                    });
                }
            }
        }

        return devices;
    }

    /**
     * Get server status (like Jellyfin's server list)
     */
    getServerStatus() {
        const savedServers = Array.from(this.savedServers.values());
        const discoveredDevices = this.getCombinedDeviceList();
        const activeConnections = Array.from(this.activeConnections.values());

        // Merge and deduplicate
        const allServers = [];
        const seenIds = new Set();

        // Add active connections first
        for (const connection of activeConnections) {
            if (!seenIds.has(connection.id)) {
                seenIds.add(connection.id);
                allServers.push({
                    ...connection,
                    status: 'connected',
                    lastSeen: Date.now()
                });
            }
        }

        // Add discovered devices
        for (const device of discoveredDevices) {
            if (!seenIds.has(device.id)) {
                seenIds.add(device.id);
                allServers.push({
                    ...device,
                    status: 'discovered',
                    lastSeen: device.lastSeen || Date.now()
                });
            }
        }

        // Add saved servers that aren't currently active/discovered
        for (const server of savedServers) {
            if (!seenIds.has(server.id)) {
                seenIds.add(server.id);
                allServers.push({
                    ...server,
                    status: 'offline',
                    lastSeen: server.savedAt
                });
            }
        }

        return allServers;
    }

    /**
     * Stop discovery
     */
    async stopDiscovery() {
        this.isDiscovering = false;

        if (this.discoveryService) {
            await this.discoveryService.stopService();
        }

        if (this.networkScanService) {
            this.networkScanService.stopScan();
        }

        this.emit('discoveryStopped');
        console.log('🛑 Discovery stopped');
    }

    /**
     * Disconnect from server
     */
    async disconnect(serverId) {
        const connection = this.activeConnections.get(serverId);

        if (connection && connection.connection) {
            connection.connection.destroy();
            this.activeConnections.delete(serverId);
            this.emit('serverDisconnected', connection);
            console.log(`🔌 Disconnected from ${connection.name}`);
        }
    }

    /**
     * Remove saved server
     */
    async removeServer(serverId) {
        try {
            // Disconnect if active
            await this.disconnect(serverId);

            // Remove from saved servers
            this.savedServers.delete(serverId);

            // Update file
            const serversFile = path.join(os.homedir(), '.flexpbx', 'servers.json');
            const servers = {};

            for (const [id, server] of this.savedServers) {
                servers[id] = server;
            }

            await fs.writeFile(serversFile, JSON.stringify(servers, null, 2));

            this.emit('serverRemoved', serverId);
            return { success: true };

        } catch (error) {
            return {
                success: false,
                error: error.message
            };
        }
    }
}

module.exports = ConnectionManager;