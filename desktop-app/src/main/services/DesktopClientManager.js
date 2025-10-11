// FlexPBX Desktop Client Manager
// Connects to remote server directly or to admin client as fallback
// Connection hierarchy: Remote Server → Admin Client → Desktop Client

const { EventEmitter } = require('events');
const WebSocket = require('ws');
const crypto = require('crypto');
const RemoteServerManager = require('./remote-server-manager');

class DesktopClientManager extends EventEmitter {
    constructor() {
        super();

        this.clientId = this.generateClientId();
        this.isAdminClient = false;

        // Connection managers
        this.remoteServerManager = new RemoteServerManager();
        this.adminClientConnection = null;

        // Connection status
        this.connectionStatus = {
            remoteServer: 'disconnected',
            adminClient: 'disconnected',
            activeConnection: null // 'remote-server' or 'admin-client'
        };

        // Connection configuration
        this.connectionConfig = {
            preferRemoteServer: true,
            enableAdminFallback: true,
            adminClientPorts: [9001, 9002, 9003], // Common admin client ports
            adminClientHosts: ['localhost', '127.0.0.1'], // Admin client discovery
            retryInterval: 30000,
            maxRetries: 3
        };

        this.setupEventHandlers();
        console.log(`📱 DesktopClientManager initialized with ID: ${this.clientId}`);
    }

    generateClientId() {
        return `desktop_${crypto.randomBytes(6).toString('hex')}_${Date.now()}`;
    }

    setupEventHandlers() {
        // Remote server events
        this.remoteServerManager.on('connected', (data) => {
            this.connectionStatus.remoteServer = 'connected';
            this.connectionStatus.activeConnection = 'remote-server';
            this.emit('connection-established', { type: 'remote-server', data });
        });

        this.remoteServerManager.on('disconnected', (data) => {
            this.connectionStatus.remoteServer = 'disconnected';
            if (this.connectionStatus.activeConnection === 'remote-server') {
                this.connectionStatus.activeConnection = null;
                this.emit('connection-lost', { type: 'remote-server', data });

                // Try to connect to admin client as fallback
                if (this.connectionConfig.enableAdminFallback) {
                    this.connectToAdminClientFallback();
                }
            }
        });

        this.remoteServerManager.on('error', (error) => {
            this.emit('remote-server-error', error);

            // Try admin client fallback on error
            if (this.connectionConfig.enableAdminFallback &&
                this.connectionStatus.activeConnection !== 'admin-client') {
                this.connectToAdminClientFallback();
            }
        });
    }

    // Connection Management
    async connect(serverUrl = null, authCredentials = null) {
        try {
            if (this.connectionConfig.preferRemoteServer && serverUrl) {
                // Try remote server first
                return await this.connectToRemoteServer(serverUrl, authCredentials);
            } else {
                // Try auto-discovery or admin client fallback
                return await this.autoConnect();
            }
        } catch (error) {
            console.error('❌ Connection failed:', error);

            // Try admin client fallback
            if (this.connectionConfig.enableAdminFallback) {
                return await this.connectToAdminClientFallback();
            }

            throw error;
        }
    }

    async connectToRemoteServer(serverUrl, authCredentials) {
        try {
            console.log(`🌐 Desktop client connecting to remote server: ${serverUrl}`);

            // First discover the server
            const serverInfo = await this.remoteServerManager.discoverServer(serverUrl);
            if (!serverInfo) {
                throw new Error('Remote server not found or not responding');
            }

            // Register device
            const registrationResult = await this.remoteServerManager.registerDevice();
            if (!registrationResult.success) {
                throw new Error('Failed to register device with remote server');
            }

            // Request authentication
            const authInfo = await this.remoteServerManager.requestAuthentication(authCredentials);
            if (!authInfo) {
                throw new Error('Failed to request authentication from remote server');
            }

            // Authorize device
            const authResult = await this.remoteServerManager.authorizeDevice(authCredentials);
            if (!authResult.success) {
                throw new Error('Failed to authorize device with remote server');
            }

            console.log(`✅ Desktop client connected to remote server: ${serverUrl}`);
            this.emit('remote-server-connected', { serverUrl, authResult });

            return { success: true, type: 'remote-server', serverInfo, authResult };

        } catch (error) {
            console.error('❌ Remote server connection failed:', error);
            throw error;
        }
    }

    async connectToAdminClientFallback() {
        console.log('🔄 Attempting to connect to admin client as fallback...');

        for (const host of this.connectionConfig.adminClientHosts) {
            for (const port of this.connectionConfig.adminClientPorts) {
                try {
                    const success = await this.connectToAdminClient(host, port);
                    if (success) {
                        return { success: true, type: 'admin-client', host, port };
                    }
                } catch (error) {
                    console.log(`Failed to connect to admin client at ${host}:${port}`);
                    continue;
                }
            }
        }

        throw new Error('No admin client found for fallback connection');
    }

    async connectToAdminClient(host, port) {
        return new Promise((resolve, reject) => {
            try {
                const wsUrl = `ws://${host}:${port}`;
                console.log(`🔗 Connecting to admin client: ${wsUrl}`);

                this.adminClientConnection = new WebSocket(wsUrl);

                this.adminClientConnection.on('open', () => {
                    console.log(`✅ Connected to admin client: ${host}:${port}`);

                    this.connectionStatus.adminClient = 'connected';
                    this.connectionStatus.activeConnection = 'admin-client';

                    // Authenticate with admin client
                    this.adminClientConnection.send(JSON.stringify({
                        type: 'authenticate',
                        credentials: {
                            type: 'desktop-client',
                            clientId: this.clientId,
                            clientName: `FlexPBX Desktop Client ${this.clientId}`
                        }
                    }));

                    this.emit('admin-client-connected', { host, port });
                    resolve(true);
                });

                this.adminClientConnection.on('message', (data) => {
                    try {
                        const message = JSON.parse(data.toString());
                        this.handleAdminClientMessage(message);
                    } catch (error) {
                        console.error('❌ Invalid message from admin client:', error);
                    }
                });

                this.adminClientConnection.on('close', () => {
                    console.log('🔌 Admin client connection closed');
                    this.connectionStatus.adminClient = 'disconnected';
                    if (this.connectionStatus.activeConnection === 'admin-client') {
                        this.connectionStatus.activeConnection = null;
                        this.emit('admin-client-disconnected');

                        // Try to reconnect to remote server
                        this.attemptRemoteServerReconnection();
                    }
                });

                this.adminClientConnection.on('error', (error) => {
                    console.error(`❌ Admin client connection error:`, error);
                    reject(error);
                });

                // Timeout for connection
                setTimeout(() => {
                    if (this.adminClientConnection.readyState === WebSocket.CONNECTING) {
                        this.adminClientConnection.close();
                        reject(new Error('Connection timeout'));
                    }
                }, 5000);

            } catch (error) {
                reject(error);
            }
        });
    }

    handleAdminClientMessage(message) {
        console.log(`📨 Message from admin client:`, message.type);

        switch (message.type) {
            case 'welcome':
                console.log(`🎉 Welcome from admin client: ${message.adminId}`);
                this.emit('admin-client-welcome', message);
                break;

            case 'authentication-success':
                console.log('✅ Authenticated with admin client');
                this.emit('admin-client-authenticated', message);
                break;

            case 'authentication-failed':
                console.error('❌ Admin client authentication failed:', message.error);
                this.emit('admin-client-auth-failed', message);
                break;

            case 'remote-server-connected':
                this.emit('remote-server-status-update', { status: 'connected', data: message.data });
                break;

            case 'remote-server-disconnected':
                this.emit('remote-server-status-update', { status: 'disconnected', data: message.data });
                break;

            case 'proxy-response':
                this.emit('proxy-response', message);
                break;

            case 'proxy-error':
                this.emit('proxy-error', message);
                break;

            case 'pong':
                this.emit('ping-response', message);
                break;

            case 'disconnect-notice':
                console.log(`📢 Disconnect notice from admin client: ${message.reason}`);
                this.emit('admin-client-disconnect-notice', message);
                break;

            default:
                console.log(`📬 Unknown message type from admin client: ${message.type}`);
        }
    }

    // API Methods
    async apiCall(endpoint, method = 'GET', data = null) {
        if (this.connectionStatus.activeConnection === 'remote-server') {
            // Direct call to remote server
            return await this.remoteServerManager.apiCall(endpoint, method, data);
        } else if (this.connectionStatus.activeConnection === 'admin-client') {
            // Proxy call through admin client
            return await this.proxyApiCallThroughAdmin(endpoint, method, data);
        } else {
            throw new Error('No active connection available');
        }
    }

    async proxyApiCallThroughAdmin(endpoint, method, data) {
        return new Promise((resolve, reject) => {
            if (!this.adminClientConnection ||
                this.adminClientConnection.readyState !== WebSocket.OPEN) {
                reject(new Error('Admin client not connected'));
                return;
            }

            const requestId = crypto.randomBytes(8).toString('hex');

            // Set up response handler
            const responseHandler = (message) => {
                if (message.requestId === requestId) {
                    this.removeListener('proxy-response', responseHandler);
                    this.removeListener('proxy-error', errorHandler);
                    resolve(message.result);
                }
            };

            const errorHandler = (message) => {
                if (message.requestId === requestId) {
                    this.removeListener('proxy-response', responseHandler);
                    this.removeListener('proxy-error', errorHandler);
                    reject(new Error(message.error));
                }
            };

            this.on('proxy-response', responseHandler);
            this.on('proxy-error', errorHandler);

            // Send proxy request
            this.adminClientConnection.send(JSON.stringify({
                type: 'proxy-to-remote-server',
                payload: {
                    requestId,
                    endpoint,
                    method,
                    data
                }
            }));

            // Timeout
            setTimeout(() => {
                this.removeListener('proxy-response', responseHandler);
                this.removeListener('proxy-error', errorHandler);
                reject(new Error('Proxy request timeout'));
            }, 30000);
        });
    }

    // Auto-connection methods
    async autoConnect() {
        try {
            console.log('🔍 Auto-discovering connections...');

            // Try remote server discovery first
            const servers = await this.remoteServerManager.autoDiscoverMainServer();
            if (servers && servers.length > 0) {
                try {
                    const serverUrl = `https://${servers[0]}`;
                    return await this.connectToRemoteServer(serverUrl, { method: 'pincode' });
                } catch (remoteError) {
                    console.log('Remote server connection failed, trying admin client fallback');
                }
            }

            // Try admin client fallback
            if (this.connectionConfig.enableAdminFallback) {
                return await this.connectToAdminClientFallback();
            }

            throw new Error('No connections available');

        } catch (error) {
            console.error('❌ Auto-connection failed:', error);
            throw error;
        }
    }

    async attemptRemoteServerReconnection() {
        if (this.connectionStatus.remoteServer === 'connected') {
            return; // Already connected
        }

        console.log('🔄 Attempting to reconnect to remote server...');

        try {
            const servers = await this.remoteServerManager.autoDiscoverMainServer();
            if (servers && servers.length > 0) {
                const serverUrl = `https://${servers[0]}`;
                await this.connectToRemoteServer(serverUrl, { method: 'pincode' });
            }
        } catch (error) {
            console.log('❌ Remote server reconnection failed:', error);
        }
    }

    // Utility methods
    ping() {
        if (this.connectionStatus.activeConnection === 'admin-client' &&
            this.adminClientConnection &&
            this.adminClientConnection.readyState === WebSocket.OPEN) {

            this.adminClientConnection.send(JSON.stringify({
                type: 'ping',
                timestamp: Date.now()
            }));
        }
    }

    getConnectionStatus() {
        return {
            clientId: this.clientId,
            isAdminClient: this.isAdminClient,
            connectionStatus: this.connectionStatus,
            activeConnection: this.connectionStatus.activeConnection,
            remoteServerInfo: this.remoteServerManager.getConnectionStatus(),
            config: this.connectionConfig
        };
    }

    async updateConnectionConfig(newConfig) {
        this.connectionConfig = { ...this.connectionConfig, ...newConfig };
        this.emit('connection-config-updated', this.connectionConfig);
        return this.connectionConfig;
    }

    async disconnect() {
        console.log('🔌 DesktopClientManager disconnecting...');

        try {
            // Disconnect from remote server
            await this.remoteServerManager.disconnect();
        } catch (error) {
            console.error('Error disconnecting from remote server:', error);
        }

        try {
            // Disconnect from admin client
            if (this.adminClientConnection) {
                this.adminClientConnection.close();
                this.adminClientConnection = null;
            }
        } catch (error) {
            console.error('Error disconnecting from admin client:', error);
        }

        this.connectionStatus = {
            remoteServer: 'disconnected',
            adminClient: 'disconnected',
            activeConnection: null
        };

        this.emit('disconnected');
        console.log('✅ DesktopClientManager disconnected');
    }

    async shutdown() {
        console.log('🛑 DesktopClientManager shutting down...');
        await this.disconnect();
        this.removeAllListeners();
        console.log('✅ DesktopClientManager shutdown complete');
    }
}

module.exports = DesktopClientManager;