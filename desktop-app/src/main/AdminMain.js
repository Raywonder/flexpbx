// FlexPBX Admin Desktop Client - Main Process
// Enhanced admin client with management controls over other desktop clients
// Connection hierarchy: Remote Server → Admin Client → Desktop Clients

const { app, BrowserWindow, Menu, Tray, ipcMain, dialog, shell, powerMonitor, nativeImage } = require('electron');
const path = require('path');
const fs = require('fs-extra');
const Store = require('electron-store');
const AdminClientManager = require('./services/AdminClientManager');
const DesktopClientManager = require('./services/DesktopClientManager');

// Initialize stores
const store = new Store();
const adminStore = new Store({ name: 'admin-config' });

class FlexPBXAdminMain {
    constructor() {
        this.mainWindow = null;
        this.adminWindow = null;
        this.isAdminMode = true;
        this.clientType = store.get('clientType', 'admin'); // 'admin' or 'desktop'

        // Initialize appropriate client manager
        if (this.clientType === 'admin') {
            this.clientManager = new AdminClientManager();
            console.log('🔧 Initialized as Admin Client');
        } else {
            this.clientManager = new DesktopClientManager();
            console.log('📱 Initialized as Desktop Client');
        }

        this.setupClientManagerEvents();
        this.setupIPCHandlers();

        console.log(`🚀 FlexPBX ${this.clientType === 'admin' ? 'Admin' : 'Desktop'} Client v1.0 initialized`);
    }

    setupClientManagerEvents() {
        // Common events for both admin and desktop clients
        this.clientManager.on('remote-server-connected', (data) => {
            this.notifyWindows('remote-server-connected', data);
            this.updateTrayStatus('connected');
        });

        this.clientManager.on('remote-server-disconnected', (data) => {
            this.notifyWindows('remote-server-disconnected', data);
            this.updateTrayStatus('disconnected');
        });

        this.clientManager.on('remote-server-error', (error) => {
            this.notifyWindows('remote-server-error', error);
        });

        // Admin-specific events
        if (this.clientType === 'admin') {
            this.clientManager.on('client-connected', (data) => {
                this.notifyWindows('client-connected', data);
                console.log(`👤 Client connected: ${data.clientId}`);
            });

            this.clientManager.on('client-disconnected', (data) => {
                this.notifyWindows('client-disconnected', data);
                console.log(`👤 Client disconnected: ${data.clientId}`);
            });

            this.clientManager.on('client-authenticated', (data) => {
                this.notifyWindows('client-authenticated', data);
                console.log(`✅ Client authenticated: ${data.clientId}`);
            });

            this.clientManager.on('client-server-started', (data) => {
                this.notifyWindows('client-server-started', data);
                console.log(`🖥️ Client server started on port ${data.port}`);
            });

            this.clientManager.on('client-server-stopped', () => {
                this.notifyWindows('client-server-stopped', {});
                console.log('🛑 Client server stopped');
            });
        }

        // Desktop client specific events
        if (this.clientType === 'desktop') {
            this.clientManager.on('admin-client-connected', (data) => {
                this.notifyWindows('admin-client-connected', data);
                this.updateTrayStatus('admin-connected');
            });

            this.clientManager.on('admin-client-disconnected', (data) => {
                this.notifyWindows('admin-client-disconnected', data);
                this.updateTrayStatus('admin-disconnected');
            });

            this.clientManager.on('connection-established', (data) => {
                this.notifyWindows('connection-established', data);
            });

            this.clientManager.on('connection-lost', (data) => {
                this.notifyWindows('connection-lost', data);
            });
        }
    }

    setupIPCHandlers() {
        // Client type management
        ipcMain.handle('get-client-type', () => {
            return this.clientType;
        });

        ipcMain.handle('set-client-type', async (event, type) => {
            if (type !== this.clientType) {
                await this.switchClientType(type);
            }
            return this.clientType;
        });

        // Connection management
        ipcMain.handle('connect-to-remote-server', async (event, serverUrl, authCredentials) => {
            try {
                if (this.clientType === 'admin') {
                    return await this.clientManager.connectToRemoteServer(serverUrl, authCredentials);
                } else {
                    return await this.clientManager.connect(serverUrl, authCredentials);
                }
            } catch (error) {
                console.error('❌ Connection failed:', error);
                return { success: false, error: error.message };
            }
        });

        ipcMain.handle('disconnect-from-remote-server', async () => {
            try {
                return await this.clientManager.disconnectFromRemoteServer();
            } catch (error) {
                console.error('❌ Disconnection failed:', error);
                return { success: false, error: error.message };
            }
        });

        ipcMain.handle('auto-discover-and-connect', async () => {
            try {
                if (this.clientType === 'admin') {
                    return await this.clientManager.autoDiscoverAndConnect();
                } else {
                    return await this.clientManager.autoConnect();
                }
            } catch (error) {
                console.error('❌ Auto-discovery failed:', error);
                return { success: false, error: error.message };
            }
        });

        // Admin-specific handlers
        if (this.clientType === 'admin') {
            ipcMain.handle('start-client-server', async (event, port) => {
                try {
                    return await this.clientManager.startClientServer(port);
                } catch (error) {
                    return { success: false, error: error.message };
                }
            });

            ipcMain.handle('stop-client-server', async () => {
                try {
                    return await this.clientManager.stopClientServer();
                } catch (error) {
                    return { success: false, error: error.message };
                }
            });

            ipcMain.handle('get-connected-clients', () => {
                return this.clientManager.getConnectedClients();
            });

            ipcMain.handle('disconnect-client', (event, clientId, reason) => {
                return this.clientManager.disconnectClient(clientId, reason);
            });

            ipcMain.handle('get-admin-status', () => {
                return this.clientManager.getAdminStatus();
            });

            ipcMain.handle('update-fallback-config', async (event, config) => {
                return await this.clientManager.updateFallbackConfig(config);
            });

            ipcMain.handle('broadcast-to-clients', (event, type, data) => {
                return this.clientManager.broadcastToClients(type, data);
            });
        }

        // Desktop client specific handlers
        if (this.clientType === 'desktop') {
            ipcMain.handle('connect-to-admin-client', async (event, host, port) => {
                try {
                    return await this.clientManager.connectToAdminClient(host, port);
                } catch (error) {
                    return { success: false, error: error.message };
                }
            });

            ipcMain.handle('get-connection-status', () => {
                return this.clientManager.getConnectionStatus();
            });

            ipcMain.handle('update-connection-config', async (event, config) => {
                return await this.clientManager.updateConnectionConfig(config);
            });

            ipcMain.handle('ping-admin-client', () => {
                this.clientManager.ping();
                return { success: true };
            });
        }

        // Common API handlers
        ipcMain.handle('api-call', async (event, endpoint, method, data) => {
            try {
                return await this.clientManager.apiCall(endpoint, method, data);
            } catch (error) {
                return { success: false, error: error.message };
            }
        });

        // Window management
        ipcMain.handle('open-admin-window', () => {
            this.createAdminWindow();
            return { success: true };
        });

        ipcMain.handle('close-admin-window', () => {
            if (this.adminWindow) {
                this.adminWindow.close();
            }
            return { success: true };
        });

        // Configuration management
        ipcMain.handle('save-config', (event, key, value) => {
            adminStore.set(key, value);
            return { success: true };
        });

        ipcMain.handle('load-config', (event, key, defaultValue) => {
            return adminStore.get(key, defaultValue);
        });

        ipcMain.handle('get-all-config', () => {
            return adminStore.store;
        });

        console.log(`📡 IPC handlers registered for ${this.clientType} client`);
    }

    async switchClientType(newType) {
        console.log(`🔄 Switching client type from ${this.clientType} to ${newType}`);

        // Shutdown current client manager
        if (this.clientManager) {
            await this.clientManager.shutdown();
        }

        // Update client type
        this.clientType = newType;
        store.set('clientType', newType);

        // Initialize new client manager
        if (this.clientType === 'admin') {
            this.clientManager = new AdminClientManager();
        } else {
            this.clientManager = new DesktopClientManager();
        }

        this.setupClientManagerEvents();

        // Notify windows of the change
        this.notifyWindows('client-type-changed', { type: newType });

        console.log(`✅ Switched to ${newType} client`);
    }

    createMainWindow() {
        if (this.mainWindow && !this.mainWindow.isDestroyed()) {
            this.mainWindow.show();
            this.mainWindow.focus();
            return this.mainWindow;
        }

        this.mainWindow = new BrowserWindow({
            width: this.clientType === 'admin' ? 1200 : 800,
            height: this.clientType === 'admin' ? 800 : 600,
            minWidth: 600,
            minHeight: 400,
            webPreferences: {
                nodeIntegration: true,
                contextIsolation: false,
                enableRemoteModule: false,
                webSecurity: false
            },
            title: `FlexPBX ${this.clientType === 'admin' ? 'Admin' : 'Desktop'} Client`,
            icon: path.join(__dirname, '../assets/icon.png'),
            show: false
        });

        // Load the appropriate interface
        const htmlFile = this.clientType === 'admin' ? 'admin-interface.html' : 'desktop-interface.html';
        const htmlPath = path.join(__dirname, '../renderer', htmlFile);

        if (fs.existsSync(htmlPath)) {
            this.mainWindow.loadFile(htmlPath);
        } else {
            // Fallback HTML
            this.mainWindow.loadURL(`data:text/html,${encodeURIComponent(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>FlexPBX ${this.clientType === 'admin' ? 'Admin' : 'Desktop'} Client</title>
                    <style>
                        body {
                            font-family: system-ui;
                            background: #1e1e1e;
                            color: white;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            height: 100vh;
                            margin: 0;
                        }
                        .container { text-align: center; }
                        .status { margin: 20px 0; }
                        button {
                            background: #007acc;
                            color: white;
                            border: none;
                            padding: 10px 20px;
                            border-radius: 5px;
                            cursor: pointer;
                            margin: 5px;
                        }
                        button:hover { background: #005999; }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <h1>🔧 FlexPBX ${this.clientType === 'admin' ? 'Admin' : 'Desktop'} Client</h1>
                        <div class="status">Status: Ready</div>
                        <button onclick="connect()">Connect</button>
                        <button onclick="disconnect()">Disconnect</button>
                        ${this.clientType === 'admin' ? '<button onclick="startServer()">Start Client Server</button>' : ''}
                    </div>
                    <script>
                        const { ipcRenderer } = require('electron');
                        async function connect() {
                            const result = await ipcRenderer.invoke('auto-discover-and-connect');
                            console.log('Connection result:', result);
                        }
                        async function disconnect() {
                            const result = await ipcRenderer.invoke('disconnect-from-remote-server');
                            console.log('Disconnect result:', result);
                        }
                        ${this.clientType === 'admin' ? `
                        async function startServer() {
                            const result = await ipcRenderer.invoke('start-client-server');
                            console.log('Start server result:', result);
                        }
                        ` : ''}
                    </script>
                </body>
                </html>
            `)}`);
        }

        this.mainWindow.once('ready-to-show', () => {
            this.mainWindow.show();
            this.mainWindow.focus();
        });

        this.mainWindow.on('closed', () => {
            this.mainWindow = null;
        });

        return this.mainWindow;
    }

    createAdminWindow() {
        if (this.adminWindow && !this.adminWindow.isDestroyed()) {
            this.adminWindow.show();
            this.adminWindow.focus();
            return this.adminWindow;
        }

        this.adminWindow = new BrowserWindow({
            width: 1000,
            height: 700,
            webPreferences: {
                nodeIntegration: true,
                contextIsolation: false,
                enableRemoteModule: false,
                webSecurity: false
            },
            title: 'FlexPBX Admin Dashboard',
            parent: this.mainWindow,
            modal: false,
            show: false
        });

        // Load admin dashboard
        this.adminWindow.loadURL(`data:text/html,${encodeURIComponent(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>FlexPBX Admin Dashboard</title>
                <style>
                    body {
                        font-family: system-ui;
                        background: #1e1e1e;
                        color: white;
                        margin: 0;
                        padding: 20px;
                    }
                    .dashboard { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
                    .panel {
                        background: #2d2d2d;
                        border-radius: 8px;
                        padding: 20px;
                        border: 1px solid #404040;
                    }
                    .panel h2 { margin-top: 0; color: #007acc; }
                    .status-indicator {
                        display: inline-block;
                        width: 12px;
                        height: 12px;
                        border-radius: 50%;
                        margin-right: 8px;
                    }
                    .status-connected { background: #4caf50; }
                    .status-disconnected { background: #f44336; }
                    .client-list { max-height: 200px; overflow-y: auto; }
                    .client-item {
                        background: #3d3d3d;
                        padding: 10px;
                        margin: 5px 0;
                        border-radius: 4px;
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                    }
                    button {
                        background: #007acc;
                        color: white;
                        border: none;
                        padding: 8px 16px;
                        border-radius: 4px;
                        cursor: pointer;
                        margin: 2px;
                    }
                    button:hover { background: #005999; }
                    button.danger { background: #f44336; }
                    button.danger:hover { background: #d32f2f; }
                </style>
            </head>
            <body>
                <h1>🔧 FlexPBX Admin Dashboard</h1>

                <div class="dashboard">
                    <div class="panel">
                        <h2>Remote Server Status</h2>
                        <div id="remote-status">
                            <span class="status-indicator status-disconnected"></span>
                            <span>Disconnected</span>
                        </div>
                        <div style="margin-top: 15px;">
                            <button onclick="connectToRemoteServer()">Connect to Remote Server</button>
                            <button onclick="disconnectFromRemoteServer()">Disconnect</button>
                        </div>
                    </div>

                    <div class="panel">
                        <h2>Client Server Status</h2>
                        <div id="client-server-status">
                            <span class="status-indicator status-disconnected"></span>
                            <span>Stopped</span>
                        </div>
                        <div style="margin-top: 15px;">
                            <button onclick="startClientServer()">Start Client Server</button>
                            <button onclick="stopClientServer()">Stop Client Server</button>
                        </div>
                    </div>

                    <div class="panel">
                        <h2>Connected Clients (<span id="client-count">0</span>)</h2>
                        <div id="client-list" class="client-list">
                            <div style="color: #888; text-align: center; padding: 20px;">No clients connected</div>
                        </div>
                        <div style="margin-top: 15px;">
                            <button onclick="refreshClients()">Refresh</button>
                            <button onclick="broadcastMessage()">Broadcast Message</button>
                        </div>
                    </div>

                    <div class="panel">
                        <h2>Admin Status</h2>
                        <div id="admin-status">Loading...</div>
                        <div style="margin-top: 15px;">
                            <button onclick="refreshStatus()">Refresh Status</button>
                            <button onclick="exportLogs()">Export Logs</button>
                        </div>
                    </div>
                </div>

                <script>
                    const { ipcRenderer } = require('electron');

                    // Connection management
                    async function connectToRemoteServer() {
                        const result = await ipcRenderer.invoke('auto-discover-and-connect');
                        console.log('Connect result:', result);
                        await refreshStatus();
                    }

                    async function disconnectFromRemoteServer() {
                        const result = await ipcRenderer.invoke('disconnect-from-remote-server');
                        console.log('Disconnect result:', result);
                        await refreshStatus();
                    }

                    async function startClientServer() {
                        const result = await ipcRenderer.invoke('start-client-server');
                        console.log('Start server result:', result);
                        await refreshStatus();
                    }

                    async function stopClientServer() {
                        const result = await ipcRenderer.invoke('stop-client-server');
                        console.log('Stop server result:', result);
                        await refreshStatus();
                    }

                    // Client management
                    async function refreshClients() {
                        const clients = await ipcRenderer.invoke('get-connected-clients');
                        updateClientList(clients);
                    }

                    async function disconnectClient(clientId) {
                        const result = await ipcRenderer.invoke('disconnect-client', clientId, 'Disconnected by admin');
                        console.log('Disconnect client result:', result);
                        await refreshClients();
                    }

                    async function broadcastMessage() {
                        const message = prompt('Enter message to broadcast to all clients:');
                        if (message) {
                            const result = await ipcRenderer.invoke('broadcast-to-clients', 'admin-message', { message });
                            console.log('Broadcast result:', result);
                        }
                    }

                    // Status management
                    async function refreshStatus() {
                        const status = await ipcRenderer.invoke('get-admin-status');
                        updateAdminStatus(status);
                        await refreshClients();
                    }

                    function updateClientList(clients) {
                        const clientList = document.getElementById('client-list');
                        const clientCount = document.getElementById('client-count');

                        clientCount.textContent = clients.length;

                        if (clients.length === 0) {
                            clientList.innerHTML = '<div style="color: #888; text-align: center; padding: 20px;">No clients connected</div>';
                        } else {
                            clientList.innerHTML = clients.map(client => \`
                                <div class="client-item">
                                    <div>
                                        <strong>\${client.metadata.clientName || client.id}</strong><br>
                                        <small>\${client.ip} • Connected: \${new Date(client.connectedAt).toLocaleTimeString()}</small>
                                    </div>
                                    <button class="danger" onclick="disconnectClient('\${client.id}')">Disconnect</button>
                                </div>
                            \`).join('');
                        }
                    }

                    function updateAdminStatus(status) {
                        const remoteStatus = document.getElementById('remote-status');
                        const clientServerStatus = document.getElementById('client-server-status');
                        const adminStatusDiv = document.getElementById('admin-status');

                        // Update remote server status
                        const remoteConnected = status.connectionStatus.remoteServer === 'connected';
                        remoteStatus.innerHTML = \`
                            <span class="status-indicator \${remoteConnected ? 'status-connected' : 'status-disconnected'}"></span>
                            <span>\${remoteConnected ? 'Connected' : 'Disconnected'}</span>
                        \`;

                        // Update client server status
                        const serverRunning = status.connectionStatus.clientServer === 'running';
                        clientServerStatus.innerHTML = \`
                            <span class="status-indicator \${serverRunning ? 'status-connected' : 'status-disconnected'}"></span>
                            <span>\${serverRunning ? 'Running' : 'Stopped'}</span>
                        \`;

                        // Update admin status
                        adminStatusDiv.innerHTML = \`
                            <strong>Admin ID:</strong> \${status.adminId}<br>
                            <strong>Uptime:</strong> \${Math.floor(status.uptime / 60)} minutes<br>
                            <strong>Total Clients:</strong> \${status.connectionStatus.totalClients}
                        \`;
                    }

                    async function exportLogs() {
                        // TODO: Implement log export functionality
                        alert('Log export functionality coming soon!');
                    }

                    // Initialize dashboard
                    refreshStatus();

                    // Auto-refresh every 30 seconds
                    setInterval(refreshStatus, 30000);

                    // Listen for real-time updates
                    ipcRenderer.on('client-connected', () => refreshClients());
                    ipcRenderer.on('client-disconnected', () => refreshClients());
                    ipcRenderer.on('remote-server-connected', () => refreshStatus());
                    ipcRenderer.on('remote-server-disconnected', () => refreshStatus());
                    ipcRenderer.on('client-server-started', () => refreshStatus());
                    ipcRenderer.on('client-server-stopped', () => refreshStatus());
                </script>
            </body>
            </html>
        `)}`);

        this.adminWindow.once('ready-to-show', () => {
            this.adminWindow.show();
        });

        this.adminWindow.on('closed', () => {
            this.adminWindow = null;
        });

        return this.adminWindow;
    }

    createMenu() {
        const template = [
            {
                label: 'FlexPBX Admin',
                submenu: [
                    {
                        label: `About FlexPBX ${this.clientType === 'admin' ? 'Admin' : 'Desktop'} Client`,
                        click: () => {
                            dialog.showMessageBox(this.mainWindow, {
                                type: 'info',
                                title: 'About FlexPBX Admin Client',
                                message: `FlexPBX ${this.clientType === 'admin' ? 'Admin' : 'Desktop'} Client v1.0`,
                                detail: `${this.clientType === 'admin' ? 'Admin management client with control over other desktop clients' : 'Desktop client with admin fallback support'}\n\n© 2025 FlexPBX Team`
                            });
                        }
                    },
                    { type: 'separator' },
                    {
                        label: 'Open Admin Dashboard',
                        accelerator: 'CmdOrCtrl+D',
                        enabled: this.clientType === 'admin',
                        click: () => {
                            this.createAdminWindow();
                        }
                    },
                    {
                        label: 'Switch to Desktop Client',
                        enabled: this.clientType === 'admin',
                        click: async () => {
                            await this.switchClientType('desktop');
                            app.relaunch();
                            app.exit();
                        }
                    },
                    {
                        label: 'Switch to Admin Client',
                        enabled: this.clientType === 'desktop',
                        click: async () => {
                            await this.switchClientType('admin');
                            app.relaunch();
                            app.exit();
                        }
                    },
                    { type: 'separator' },
                    { role: 'quit' }
                ]
            },
            {
                label: 'Connection',
                submenu: [
                    {
                        label: 'Auto Connect',
                        accelerator: 'CmdOrCtrl+N',
                        click: async () => {
                            try {
                                const result = await this.clientManager.autoDiscoverAndConnect();
                                this.notifyWindows('auto-connect-result', result);
                            } catch (error) {
                                this.notifyWindows('auto-connect-error', { error: error.message });
                            }
                        }
                    },
                    {
                        label: 'Disconnect',
                        accelerator: 'CmdOrCtrl+Shift+D',
                        click: async () => {
                            await this.clientManager.disconnect();
                        }
                    },
                    { type: 'separator' },
                    {
                        label: 'Start Client Server',
                        enabled: this.clientType === 'admin',
                        click: async () => {
                            try {
                                await this.clientManager.startClientServer();
                            } catch (error) {
                                console.error('Failed to start client server:', error);
                            }
                        }
                    },
                    {
                        label: 'Stop Client Server',
                        enabled: this.clientType === 'admin',
                        click: async () => {
                            try {
                                await this.clientManager.stopClientServer();
                            } catch (error) {
                                console.error('Failed to stop client server:', error);
                            }
                        }
                    }
                ]
            },
            {
                label: 'View',
                submenu: [
                    { role: 'reload' },
                    { role: 'forceReload' },
                    { role: 'toggleDevTools' },
                    { type: 'separator' },
                    { role: 'resetZoom' },
                    { role: 'zoomIn' },
                    { role: 'zoomOut' },
                    { type: 'separator' },
                    { role: 'togglefullscreen' }
                ]
            },
            {
                label: 'Window',
                submenu: [
                    { role: 'minimize' },
                    { role: 'close' }
                ]
            }
        ];

        const menu = Menu.buildFromTemplate(template);
        Menu.setApplicationMenu(menu);
    }

    notifyWindows(event, data) {
        if (this.mainWindow && !this.mainWindow.isDestroyed()) {
            this.mainWindow.webContents.send(event, data);
        }
        if (this.adminWindow && !this.adminWindow.isDestroyed()) {
            this.adminWindow.webContents.send(event, data);
        }
    }

    updateTrayStatus(status) {
        // TODO: Implement system tray with status indicators
        console.log(`📊 Tray status: ${status}`);
    }

    async initialize() {
        console.log('🚀 Initializing FlexPBX Admin Main...');

        // Auto-connect on startup if configured
        const autoConnect = adminStore.get('autoConnect', false);
        if (autoConnect) {
            try {
                if (this.clientType === 'admin') {
                    await this.clientManager.autoDiscoverAndConnect();
                } else {
                    await this.clientManager.autoConnect();
                }
            } catch (error) {
                console.log('Auto-connect failed on startup:', error.message);
            }
        }

        console.log('✅ FlexPBX Admin Main initialized');
    }

    async shutdown() {
        console.log('🛑 FlexPBX Admin Main shutting down...');

        if (this.clientManager) {
            await this.clientManager.shutdown();
        }

        console.log('✅ FlexPBX Admin Main shutdown complete');
    }
}

// Application lifecycle
const flexPBXAdmin = new FlexPBXAdminMain();

app.whenReady().then(async () => {
    console.log('📱 FlexPBX Admin Client ready');

    await flexPBXAdmin.initialize();
    flexPBXAdmin.createMainWindow();
    flexPBXAdmin.createMenu();

    console.log('✅ FlexPBX Admin Client started successfully');
});

app.on('window-all-closed', () => {
    if (process.platform !== 'darwin') {
        app.quit();
    }
});

app.on('activate', () => {
    if (BrowserWindow.getAllWindows().length === 0) {
        flexPBXAdmin.createMainWindow();
    }
});

app.on('before-quit', async (event) => {
    event.preventDefault();
    await flexPBXAdmin.shutdown();
    process.exit(0);
});

module.exports = FlexPBXAdminMain;