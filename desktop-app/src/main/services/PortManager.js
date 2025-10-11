/**
 * FlexPBX Port Management System
 * Automatically detects and manages port availability
 */

const net = require('net');
const { exec } = require('child_process');

class PortManager {
    constructor() {
        this.defaultPorts = {
            flexpbx: 8080,
            holdMusic: 8081,
            supportTickets: 8082,
            localTTS: 8085,
            discovery: 41235,
            copyparty: 3923,
            webInterface: 8083,
            realTimeMonitor: 8084
        };

        this.assignedPorts = {};
        this.portRange = { min: 8080, max: 8999 };
    }

    async init() {
        console.log('🔍 Initializing Port Manager...');
        await this.checkExistingFlexPBXProcesses();
        return this;
    }

    async checkExistingFlexPBXProcesses() {
        // Check for existing FlexPBX processes and kill them if needed
        return new Promise((resolve) => {
            exec("lsof -ti :8080,8081,8082,8083,3923,41235", (error, stdout) => {
                if (stdout) {
                    const pids = stdout.trim().split('\n').filter(pid => pid);
                    console.log(`🔄 Found existing processes on FlexPBX ports: ${pids.join(', ')}`);

                    // Kill existing FlexPBX processes
                    pids.forEach(pid => {
                        try {
                            process.kill(parseInt(pid), 'SIGTERM');
                            console.log(`✅ Killed process ${pid}`);
                        } catch (err) {
                            console.log(`⚠️ Could not kill process ${pid}: ${err.message}`);
                        }
                    });

                    // Wait a moment for processes to close
                    setTimeout(resolve, 2000);
                } else {
                    resolve();
                }
            });
        });
    }

    async getAvailablePort(serviceName) {
        const preferredPort = this.defaultPorts[serviceName];

        // Try preferred port first
        if (await this.isPortAvailable(preferredPort)) {
            this.assignedPorts[serviceName] = preferredPort;
            console.log(`✅ ${serviceName} assigned to preferred port ${preferredPort}`);
            return preferredPort;
        }

        // Find alternative port
        for (let port = this.portRange.min; port <= this.portRange.max; port++) {
            if (await this.isPortAvailable(port)) {
                this.assignedPorts[serviceName] = port;
                console.log(`✅ ${serviceName} assigned to alternative port ${port}`);
                return port;
            }
        }

        throw new Error(`No available ports found for ${serviceName}`);
    }

    async isPortAvailable(port) {
        return new Promise((resolve) => {
            const server = net.createServer();

            server.listen(port, () => {
                server.once('close', () => resolve(true));
                server.close();
            });

            server.on('error', () => resolve(false));
        });
    }

    getAssignedPort(serviceName) {
        return this.assignedPorts[serviceName] || this.defaultPorts[serviceName];
    }

    getAllAssignedPorts() {
        return { ...this.assignedPorts };
    }

    async allocatePortsForServices() {
        const services = Object.keys(this.defaultPorts);
        const allocations = {};

        for (const service of services) {
            try {
                allocations[service] = await this.getAvailablePort(service);
            } catch (error) {
                console.error(`❌ Failed to allocate port for ${service}: ${error.message}`);
                throw error;
            }
        }

        return allocations;
    }

    // Monitor port usage in real-time
    startPortMonitoring() {
        setInterval(() => {
            this.checkPortHealth();
        }, 30000); // Check every 30 seconds
    }

    async checkPortHealth() {
        for (const [service, port] of Object.entries(this.assignedPorts)) {
            const available = await this.isPortAvailable(port);
            if (available) {
                console.log(`⚠️ Port ${port} for ${service} appears to be free - service may have stopped`);
            }
        }
    }
}

module.exports = PortManager;