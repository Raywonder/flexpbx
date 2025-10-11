// FlexPBX Admin Client Manager
// Manages connections to remote servers and other desktop clients with fallback hierarchy
// Similar to Tailscale architecture: Remote Server → Admin Client → Desktop Clients

const { EventEmitter } = require('events');
const WebSocket = require('ws');
const crypto = require('crypto');
const RemoteServerManager = require('./remote-server-manager');

class AdminClientManager extends EventEmitter {
    constructor() {
        super();

        // Connection hierarchy
        this.remoteServerManager = new RemoteServerManager();
        this.isAdminClient = true;
        this.adminId = this.generateAdminId();

        // Client management
        this.connectedClients = new Map();
        this.clientServer = null;
        this.clientServerPort = null;

        // Connection status
        this.connectionStatus = {
            remoteServer: 'disconnected',
            clientServer: 'stopped',
            totalClients: 0
        };

        // Fallback configuration
        this.fallbackConfig = {
            enableFallback: true,
            clientServerPort: 9001,
            maxClients: 50,
            heartbeatInterval: 30000
        };

        this.setupEventHandlers();
        console.log(`🔧 AdminClientManager initialized with ID: ${this.adminId}`);
    }

    generateAdminId() {
        return `admin_${crypto.randomBytes(8).toString('hex')}_${Date.now()}`;
    }

    setupEventHandlers() {
        // Remote server events
        this.remoteServerManager.on('connected', (data) => {
            this.connectionStatus.remoteServer = 'connected';
            this.broadcastToClients('remote-server-connected', data);
            this.emit('remote-server-connected', data);
        });

        this.remoteServerManager.on('disconnected', (data) => {
            this.connectionStatus.remoteServer = 'disconnected';
            this.broadcastToClients('remote-server-disconnected', data);
            this.emit('remote-server-disconnected', data);

            // If fallback is enabled, ensure client server is running
            if (this.fallbackConfig.enableFallback && !this.clientServer) {
                this.startClientServer();
            }
        });

        this.remoteServerManager.on('error', (error) => {
            this.emit('remote-server-error', error);
            this.broadcastToClients('remote-server-error', error);
        });
    }

    // Remote Server Connection Management
    async connectToRemoteServer(serverUrl, authCredentials) {
        try {
            console.log(`🌐 Admin client connecting to remote server: ${serverUrl}`);

            // First discover the server
            const serverInfo = await this.remoteServerManager.discoverServer(serverUrl);
            if (!serverInfo) {
                throw new Error('Remote server not found or not responding');
            }

            // Register as admin device
            const registrationResult = await this.remoteServerManager.registerDevice();
            if (!registrationResult.success) {
                throw new Error('Failed to register admin device with remote server');
            }

            // Request authentication
            const authInfo = await this.remoteServerManager.requestAuthentication(authCredentials);
            if (!authInfo) {
                throw new Error('Failed to request authentication from remote server');
            }

            // Authorize device
            const authResult = await this.remoteServerManager.authorizeDevice(authCredentials);
            if (!authResult.success) {
                throw new Error('Failed to authorize admin device with remote server');
            }

            console.log(`✅ Admin client connected to remote server: ${serverUrl}`);
            this.emit('admin-connected-to-remote', { serverUrl, authResult });

            return { success: true, serverInfo, authResult };

        } catch (error) {
            console.error('❌ Admin client connection to remote server failed:', error);
            this.emit('admin-connection-failed', { serverUrl, error: error.message });
            throw error;
        }
    }

    async disconnectFromRemoteServer() {
        try {
            await this.remoteServerManager.disconnect();
            console.log('🔌 Admin client disconnected from remote server');
            this.emit('admin-disconnected-from-remote');
            return { success: true };
        } catch (error) {
            console.error('❌ Failed to disconnect from remote server:', error);
            throw error;
        }
    }

    // Client Server Management (for other desktop clients to connect)
    async startClientServer(port = null) {
        try {
            this.clientServerPort = port || this.fallbackConfig.clientServerPort;

            this.clientServer = new WebSocket.Server({
                port: this.clientServerPort,
                perMessageDeflate: false
            });

            this.clientServer.on('connection', (ws, request) => {
                this.handleClientConnection(ws, request);
            });

            this.clientServer.on('error', (error) => {
                console.error('❌ Client server error:', error);
                this.emit('client-server-error', error);
            });

            this.connectionStatus.clientServer = 'running';
            console.log(`🖥️ Client server started on port ${this.clientServerPort}`);
            this.emit('client-server-started', { port: this.clientServerPort });

            return { success: true, port: this.clientServerPort };

        } catch (error) {
            console.error('❌ Failed to start client server:', error);
            this.emit('client-server-start-failed', error);
            throw error;
        }
    }

    async stopClientServer() {
        try {
            if (this.clientServer) {
                // Notify all connected clients
                this.broadcastToClients('admin-server-shutting-down', {
                    message: 'Admin client server is shutting down',
                    timestamp: new Date().toISOString()
                });

                // Close all client connections
                this.clientServer.clients.forEach((client) => {
                    if (client.readyState === WebSocket.OPEN) {
                        client.close();
                    }
                });

                this.clientServer.close();
                this.clientServer = null;
                this.connectedClients.clear();
            }

            this.connectionStatus.clientServer = 'stopped';
            this.connectionStatus.totalClients = 0;

            console.log('🛑 Client server stopped');
            this.emit('client-server-stopped');

            return { success: true };

        } catch (error) {
            console.error('❌ Failed to stop client server:', error);
            throw error;
        }
    }

    handleClientConnection(ws, request) {
        const clientId = this.generateClientId();
        const clientInfo = {
            id: clientId,
            ws: ws,
            ip: request.socket.remoteAddress,
            userAgent: request.headers['user-agent'],
            connectedAt: new Date(),
            authenticated: false,
            metadata: {}
        };

        console.log(`📱 New client connected: ${clientId} from ${clientInfo.ip}`);

        ws.on('message', (data) => {
            try {
                const message = JSON.parse(data.toString());
                this.handleClientMessage(clientId, message);
            } catch (error) {
                console.error(`❌ Invalid message from client ${clientId}:`, error);
            }
        });

        ws.on('close', () => {
            this.handleClientDisconnect(clientId);
        });

        ws.on('error', (error) => {
            console.error(`❌ Client ${clientId} error:`, error);
            this.handleClientDisconnect(clientId);
        });

        // Store client connection
        this.connectedClients.set(clientId, clientInfo);
        this.connectionStatus.totalClients = this.connectedClients.size;

        // Send welcome message
        this.sendToClient(clientId, {
            type: 'welcome',
            adminId: this.adminId,
            clientId: clientId,
            remoteServerStatus: this.connectionStatus.remoteServer,
            message: 'Connected to FlexPBX Admin Client'
        });

        this.emit('client-connected', { clientId, clientInfo });
    }

    handleClientMessage(clientId, message) {
        const client = this.connectedClients.get(clientId);
        if (!client) return;

        console.log(`📨 Message from client ${clientId}:`, message.type);

        switch (message.type) {
            case 'authenticate':
                this.authenticateClient(clientId, message.credentials);
                break;

            case 'ping':
                this.sendToClient(clientId, { type: 'pong', timestamp: Date.now() });
                break;

            case 'request-remote-server-status':
                this.sendToClient(clientId, {
                    type: 'remote-server-status',
                    status: this.connectionStatus.remoteServer,
                    serverInfo: this.remoteServerManager.getConnectionStatus()
                });
                break;

            case 'proxy-to-remote-server':
                this.proxyToRemoteServer(clientId, message.payload);
                break;

            case 'update-metadata':
                client.metadata = { ...client.metadata, ...message.metadata };
                this.emit('client-metadata-updated', { clientId, metadata: client.metadata });
                break;

            default:
                console.warn(`⚠️ Unknown message type from client ${clientId}: ${message.type}`);
        }
    }

    async authenticateClient(clientId, credentials) {
        const client = this.connectedClients.get(clientId);
        if (!client) return;

        try {
            // Implement authentication logic here
            // For now, simple authentication
            if (credentials && credentials.type === 'desktop-client') {
                client.authenticated = true;
                client.metadata.authType = credentials.type;
                client.metadata.clientName = credentials.clientName || 'Unknown Client';

                this.sendToClient(clientId, {
                    type: 'authentication-success',
                    adminId: this.adminId,
                    permissions: ['basic-access', 'remote-server-proxy']
                });

                console.log(`✅ Client ${clientId} authenticated as ${client.metadata.clientName}`);
                this.emit('client-authenticated', { clientId, client });
            } else {
                throw new Error('Invalid credentials');
            }
        } catch (error) {
            this.sendToClient(clientId, {
                type: 'authentication-failed',
                error: error.message
            });
            console.error(`❌ Authentication failed for client ${clientId}:`, error);
        }
    }

    async proxyToRemoteServer(clientId, payload) {
        const client = this.connectedClients.get(clientId);
        if (!client || !client.authenticated) {
            this.sendToClient(clientId, {
                type: 'proxy-error',
                error: 'Client not authenticated'
            });
            return;
        }

        if (this.connectionStatus.remoteServer !== 'connected') {
            this.sendToClient(clientId, {
                type: 'proxy-error',
                error: 'Remote server not connected'
            });
            return;
        }

        try {
            // Proxy the request to the remote server
            const result = await this.remoteServerManager.apiCall(payload.endpoint, payload.method, payload.data);

            this.sendToClient(clientId, {
                type: 'proxy-response',
                requestId: payload.requestId,
                result: result
            });
        } catch (error) {
            this.sendToClient(clientId, {
                type: 'proxy-error',
                requestId: payload.requestId,
                error: error.message
            });
        }
    }

    handleClientDisconnect(clientId) {
        const client = this.connectedClients.get(clientId);
        if (client) {
            console.log(`📱 Client disconnected: ${clientId}`);
            this.connectedClients.delete(clientId);
            this.connectionStatus.totalClients = this.connectedClients.size;
            this.emit('client-disconnected', { clientId, client });
        }
    }

    generateClientId() {
        return `client_${crypto.randomBytes(6).toString('hex')}_${Date.now()}`;
    }

    // Client Management Methods
    sendToClient(clientId, message) {
        const client = this.connectedClients.get(clientId);
        if (client && client.ws.readyState === WebSocket.OPEN) {
            try {
                client.ws.send(JSON.stringify(message));
                return true;
            } catch (error) {
                console.error(`❌ Failed to send message to client ${clientId}:`, error);
                return false;
            }
        }
        return false;
    }

    broadcastToClients(type, data) {
        const message = JSON.stringify({ type, data, timestamp: Date.now() });
        let successCount = 0;

        this.connectedClients.forEach((client, clientId) => {
            if (client.ws.readyState === WebSocket.OPEN) {
                try {
                    client.ws.send(message);
                    successCount++;
                } catch (error) {
                    console.error(`❌ Failed to broadcast to client ${clientId}:`, error);
                }
            }
        });

        console.log(`📡 Broadcasted ${type} to ${successCount}/${this.connectedClients.size} clients`);
        return successCount;
    }

    getConnectedClients() {
        return Array.from(this.connectedClients.entries()).map(([id, client]) => ({
            id,
            ip: client.ip,
            userAgent: client.userAgent,
            connectedAt: client.connectedAt,
            authenticated: client.authenticated,
            metadata: client.metadata
        }));
    }

    disconnectClient(clientId, reason = 'Disconnected by admin') {
        const client = this.connectedClients.get(clientId);
        if (client) {
            this.sendToClient(clientId, {
                type: 'disconnect-notice',
                reason: reason,
                timestamp: Date.now()
            });

            setTimeout(() => {
                if (client.ws.readyState === WebSocket.OPEN) {
                    client.ws.close();
                }
            }, 1000);

            return true;
        }
        return false;
    }

    // Admin Management Interface
    getAdminStatus() {
        return {
            adminId: this.adminId,
            isAdminClient: this.isAdminClient,
            connectionStatus: this.connectionStatus,
            connectedClients: this.getConnectedClients(),
            remoteServerInfo: this.remoteServerManager.getConnectionStatus(),
            fallbackConfig: this.fallbackConfig,
            uptime: process.uptime()
        };
    }

    async updateFallbackConfig(newConfig) {
        this.fallbackConfig = { ...this.fallbackConfig, ...newConfig };

        // If client server port changed and server is running, restart it
        if (newConfig.clientServerPort &&
            this.connectionStatus.clientServer === 'running' &&
            newConfig.clientServerPort !== this.clientServerPort) {

            await this.stopClientServer();
            await this.startClientServer(newConfig.clientServerPort);
        }

        this.emit('fallback-config-updated', this.fallbackConfig);
        return this.fallbackConfig;
    }

    // Auto-discovery and management
    async autoDiscoverAndConnect() {
        try {
            console.log('🔍 Auto-discovering remote servers...');
            const servers = await this.remoteServerManager.autoDiscoverMainServer();

            if (servers && servers.length > 0) {
                // Try to connect to the first discovered server with pincode auth
                const serverUrl = `https://${servers[0]}`;
                return await this.connectToRemoteServer(serverUrl, { method: 'pincode' });
            } else {
                console.log('No remote servers discovered, running in standalone mode');

                // Start client server for fallback mode
                if (this.fallbackConfig.enableFallback) {
                    await this.startClientServer();
                }

                return { success: false, message: 'No remote servers found' };
            }
        } catch (error) {
            console.error('❌ Auto-discovery failed:', error);

            // Start client server for fallback mode
            if (this.fallbackConfig.enableFallback) {
                await this.startClientServer();
            }

            throw error;
        }
    }

    async shutdown() {
        console.log('🛑 AdminClientManager shutting down...');

        try {
            // Disconnect from remote server
            await this.disconnectFromRemoteServer();
        } catch (error) {
            console.error('Error disconnecting from remote server:', error);
        }

        try {
            // Stop client server
            await this.stopClientServer();
        } catch (error) {
            console.error('Error stopping client server:', error);
        }

        this.removeAllListeners();
        console.log('✅ AdminClientManager shutdown complete');
    }
}

module.exports = AdminClientManager;