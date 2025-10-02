const net = require('net');
const { EventEmitter } = require('events');
const os = require('os');

class NetworkScanService extends EventEmitter {
    constructor() {
        super();
        this.isScanning = false;
        this.foundDevices = new Map();
        this.commonPorts = [22, 3389, 5900, 41235]; // SSH, RDP, VNC, FlexPBX
    }

    /**
     * Get local network IP ranges
     */
    getLocalNetworkRanges() {
        const interfaces = os.networkInterfaces();
        const ranges = [];

        for (const [name, configs] of Object.entries(interfaces)) {
            for (const config of configs) {
                if (!config.internal && config.family === 'IPv4') {
                    const ip = config.address;
                    const netmask = config.netmask;

                    // Calculate network range
                    const range = this.calculateNetworkRange(ip, netmask);
                    if (range) {
                        ranges.push({
                            interface: name,
                            ip: ip,
                            netmask: netmask,
                            range: range,
                            description: this.getInterfaceDescription(name)
                        });
                    }
                }
            }
        }

        return ranges;
    }

    /**
     * Calculate network range from IP and netmask
     */
    calculateNetworkRange(ip, netmask) {
        const ipParts = ip.split('.').map(Number);
        const maskParts = netmask.split('.').map(Number);

        // Calculate network address
        const network = ipParts.map((part, i) => part & maskParts[i]);

        // Calculate broadcast address
        const broadcast = ipParts.map((part, i) => part | (255 - maskParts[i]));

        // Only scan common private networks
        if (network[0] === 192 && network[1] === 168) {
            return {
                start: network.join('.'),
                end: broadcast.join('.'),
                cidr: this.getCIDR(netmask),
                type: 'private'
            };
        } else if (network[0] === 10) {
            // Limit 10.x networks to /24 for performance
            return {
                start: network.slice(0, 3).join('.') + '.1',
                end: network.slice(0, 3).join('.') + '.254',
                cidr: 24,
                type: 'private'
            };
        } else if (network[0] === 172 && network[1] >= 16 && network[1] <= 31) {
            return {
                start: network.join('.'),
                end: broadcast.join('.'),
                cidr: this.getCIDR(netmask),
                type: 'private'
            };
        }

        return null;
    }

    /**
     * Get CIDR notation from netmask
     */
    getCIDR(netmask) {
        const binary = netmask.split('.')
            .map(octet => parseInt(octet).toString(2).padStart(8, '0'))
            .join('');
        return binary.indexOf('0') === -1 ? 32 : binary.indexOf('0');
    }

    /**
     * Get interface description
     */
    getInterfaceDescription(name) {
        const descriptions = {
            'en0': 'Ethernet/WiFi',
            'en1': 'WiFi/Ethernet',
            'eth0': 'Ethernet',
            'wlan0': 'WiFi',
            'Wi-Fi': 'WiFi',
            'Ethernet': 'Ethernet'
        };
        return descriptions[name] || name;
    }

    /**
     * Scan network for FlexPBX devices
     */
    async scanNetwork(options = {}) {
        const {
            timeout = 2000,
            maxConcurrent = 50,
            includePorts = this.commonPorts,
            quickScan = true
        } = options;

        if (this.isScanning) {
            return { success: false, error: 'Scan already in progress' };
        }

        this.isScanning = true;
        this.foundDevices.clear();

        console.log('🔍 Starting network scan...');
        this.emit('scanStarted');

        try {
            const ranges = this.getLocalNetworkRanges();
            console.log(`📡 Scanning ${ranges.length} network ranges`);

            const allPromises = [];

            for (const range of ranges) {
                const ips = this.generateIPList(range.range, quickScan);
                console.log(`🌐 Scanning ${ips.length} IPs in range ${range.range.start}-${range.range.end}`);

                // Limit concurrent connections
                for (let i = 0; i < ips.length; i += maxConcurrent) {
                    const batch = ips.slice(i, i + maxConcurrent);

                    const batchPromises = batch.map(ip =>
                        this.scanHost(ip, includePorts, timeout)
                    );

                    allPromises.push(...batchPromises);

                    // Process batch and emit progress
                    await Promise.allSettled(batchPromises);

                    this.emit('scanProgress', {
                        total: ips.length,
                        completed: Math.min(i + maxConcurrent, ips.length),
                        found: this.foundDevices.size
                    });
                }
            }

            await Promise.allSettled(allPromises);

            const devices = Array.from(this.foundDevices.values());
            this.isScanning = false;

            console.log(`✅ Scan completed. Found ${devices.length} devices`);
            this.emit('scanCompleted', { devices });

            return {
                success: true,
                devices,
                stats: {
                    scanned: allPromises.length,
                    found: devices.length,
                    ranges: ranges.length
                }
            };

        } catch (error) {
            this.isScanning = false;
            this.emit('scanError', error);
            return {
                success: false,
                error: error.message
            };
        }
    }

    /**
     * Generate list of IPs to scan
     */
    generateIPList(range, quickScan = true) {
        const startParts = range.start.split('.').map(Number);
        const endParts = range.end.split('.').map(Number);

        const ips = [];

        // Quick scan: only scan common device IPs
        if (quickScan) {
            const commonLastOctets = [1, 2, 10, 20, 50, 100, 101, 254];
            const base = startParts.slice(0, 3).join('.');

            for (const lastOctet of commonLastOctets) {
                if (lastOctet >= startParts[3] && lastOctet <= endParts[3]) {
                    ips.push(`${base}.${lastOctet}`);
                }
            }
        } else {
            // Full scan
            for (let i = startParts[3]; i <= endParts[3]; i++) {
                ips.push(`${startParts.slice(0, 3).join('.')}.${i}`);
            }
        }

        return ips;
    }

    /**
     * Scan individual host
     */
    async scanHost(ip, ports, timeout) {
        const deviceInfo = {
            ip,
            hostname: null,
            ports: [],
            services: [],
            isFlexPBX: false,
            responseTime: null
        };

        const startTime = Date.now();

        try {
            // Try to resolve hostname
            try {
                const dns = require('dns');
                const { promisify } = require('util');
                const reverse = promisify(dns.reverse);
                const hostnames = await reverse(ip);
                deviceInfo.hostname = hostnames[0];
            } catch (e) {
                // Hostname resolution failed - not critical
            }

            // Scan ports
            const portPromises = ports.map(port => this.scanPort(ip, port, timeout));
            const portResults = await Promise.allSettled(portPromises);

            for (let i = 0; i < ports.length; i++) {
                const result = portResults[i];
                if (result.status === 'fulfilled' && result.value.open) {
                    deviceInfo.ports.push(ports[i]);
                    deviceInfo.services.push(result.value.service);

                    // Check if it's a FlexPBX service
                    if (ports[i] === 41235 || result.value.service === 'flexpbx') {
                        deviceInfo.isFlexPBX = true;
                    }
                }
            }

            deviceInfo.responseTime = Date.now() - startTime;

            // Only add devices that responded on at least one port
            if (deviceInfo.ports.length > 0) {
                this.foundDevices.set(ip, deviceInfo);
                this.emit('deviceFound', deviceInfo);

                console.log(`📱 Found device: ${ip} (${deviceInfo.hostname || 'unknown'}) - ports: ${deviceInfo.ports.join(', ')}`);
            }

        } catch (error) {
            // Host scan failed - ignore
        }

        return deviceInfo;
    }

    /**
     * Scan individual port
     */
    async scanPort(ip, port, timeout) {
        return new Promise((resolve) => {
            const socket = new net.Socket();
            const startTime = Date.now();

            const cleanup = () => {
                socket.destroy();
            };

            socket.setTimeout(timeout);

            socket.on('connect', () => {
                const responseTime = Date.now() - startTime;
                cleanup();
                resolve({
                    open: true,
                    port,
                    responseTime,
                    service: this.identifyService(port)
                });
            });

            socket.on('timeout', () => {
                cleanup();
                resolve({ open: false, port });
            });

            socket.on('error', () => {
                cleanup();
                resolve({ open: false, port });
            });

            try {
                socket.connect(port, ip);
            } catch (e) {
                cleanup();
                resolve({ open: false, port });
            }
        });
    }

    /**
     * Identify service by port
     */
    identifyService(port) {
        const services = {
            22: 'ssh',
            80: 'http',
            443: 'https',
            3389: 'rdp',
            5900: 'vnc',
            5901: 'vnc',
            5902: 'vnc',
            41235: 'flexpbx'
        };

        return services[port] || 'unknown';
    }

    /**
     * Test FlexPBX connection
     */
    async testFlexPBXConnection(ip, port = 41235) {
        return new Promise((resolve) => {
            const socket = new net.Socket();
            let response = '';

            socket.setTimeout(5000);

            socket.on('connect', () => {
                // Send ping command
                socket.write(JSON.stringify({ type: 'PING' }) + '\n');
            });

            socket.on('data', (data) => {
                response += data.toString();

                try {
                    const lines = response.split('\n');
                    for (const line of lines) {
                        if (line.trim()) {
                            const msg = JSON.parse(line.trim());

                            if (msg.type === 'WELCOME' || msg.type === 'PONG') {
                                socket.destroy();
                                resolve({
                                    success: true,
                                    isFlexPBX: true,
                                    deviceInfo: msg.device || null,
                                    version: msg.device?.version || 'unknown'
                                });
                                return;
                            }
                        }
                    }
                } catch (e) {
                    // Invalid JSON - not FlexPBX
                }
            });

            socket.on('timeout', () => {
                socket.destroy();
                resolve({
                    success: false,
                    error: 'Connection timeout'
                });
            });

            socket.on('error', (err) => {
                socket.destroy();
                resolve({
                    success: false,
                    error: err.message
                });
            });

            try {
                socket.connect(port, ip);
            } catch (e) {
                resolve({
                    success: false,
                    error: e.message
                });
            }
        });
    }

    /**
     * Get device capabilities
     */
    async getDeviceCapabilities(ip, port = 41235) {
        try {
            const DiscoveryService = require('./DiscoveryService');
            const discovery = new DiscoveryService();

            const connection = await discovery.connectToDevice(ip, port);
            if (!connection.success) {
                throw new Error('Failed to connect');
            }

            // Get system info
            const systemInfo = await discovery.sendCommand(connection.socket, 'GET_SYSTEM_INFO');

            // Get device info
            const deviceInfo = await discovery.sendCommand(connection.socket, 'GET_DEVICE_INFO');

            connection.socket.destroy();

            return {
                success: true,
                systemInfo: systemInfo.systemInfo,
                deviceInfo: deviceInfo.device,
                capabilities: deviceInfo.device?.capabilities || []
            };

        } catch (error) {
            return {
                success: false,
                error: error.message
            };
        }
    }

    /**
     * Quick ping to check if device is online
     */
    async pingDevice(ip, timeout = 3000) {
        return new Promise((resolve) => {
            const { exec } = require('child_process');

            const pingCmd = process.platform === 'win32'
                ? `ping -n 1 -w ${timeout} ${ip}`
                : `ping -c 1 -W ${timeout / 1000} ${ip}`;

            exec(pingCmd, (error, stdout) => {
                if (error) {
                    resolve({
                        success: false,
                        ip,
                        responseTime: null
                    });
                } else {
                    // Extract response time from ping output
                    const timeMatch = stdout.match(/time[<=](\d+(?:\.\d+)?)/i);
                    const responseTime = timeMatch ? parseFloat(timeMatch[1]) : null;

                    resolve({
                        success: true,
                        ip,
                        responseTime
                    });
                }
            });
        });
    }

    /**
     * Scan specific IP address
     */
    async scanSingleIP(ip, options = {}) {
        const {
            ports = this.commonPorts,
            timeout = 3000,
            includeCapabilities = false
        } = options;

        console.log(`🔍 Scanning ${ip}...`);

        // First, ping the device
        const pingResult = await this.pingDevice(ip, timeout);
        if (!pingResult.success) {
            return {
                success: false,
                ip,
                error: 'Host unreachable'
            };
        }

        // Scan ports
        const deviceInfo = await this.scanHost(ip, ports, timeout);

        // If FlexPBX port is open, get additional info
        if (deviceInfo.isFlexPBX && includeCapabilities) {
            const capabilities = await this.getDeviceCapabilities(ip);
            if (capabilities.success) {
                deviceInfo.systemInfo = capabilities.systemInfo;
                deviceInfo.deviceInfo = capabilities.deviceInfo;
                deviceInfo.capabilities = capabilities.capabilities;
            }
        }

        return {
            success: true,
            device: deviceInfo
        };
    }

    /**
     * Stop current scan
     */
    stopScan() {
        if (this.isScanning) {
            this.isScanning = false;
            this.emit('scanStopped');
            console.log('🛑 Network scan stopped');
        }
    }

    /**
     * Get scan status
     */
    getScanStatus() {
        return {
            isScanning: this.isScanning,
            foundDevices: this.foundDevices.size,
            devices: Array.from(this.foundDevices.values())
        };
    }
}

module.exports = NetworkScanService;