const { app, BrowserWindow, Menu, ipcMain, dialog, shell } = require('electron');
const path = require('path');
const fs = require('fs-extra');
const Store = require('electron-store');
const express = require('express');
const http = require('http');
const WebSocket = require('ws');

// Import services
const AutoInstaller = require('./installers/AutoInstaller');
const UnifiedDeploymentService = require('./services/UnifiedDeploymentService');
const FileUploadService = require('./services/FileUploadService');
const TestingManagerService = require('./services/TestingManagerService');

class FlexPBXUnifiedClient {
    constructor() {
        this.mainWindow = null;
        this.store = new Store();
        this.webServer = null;
        this.wsServer = null;

        // Services
        this.autoInstaller = new AutoInstaller();
        this.deploymentService = new UnifiedDeploymentService();
        this.uploadService = new FileUploadService();
        this.testingManager = new TestingManagerService();

        // Configuration
        this.config = {
            localMode: true,
            remoteServers: [],
            currentServer: null,
            webUIPort: 8080
        };

        this.setupApp();
    }

    setupApp() {
        app.whenReady().then(async () => {
            // Check and install dependencies
            // await this.checkDependencies();

            // Start web UI server - disabled for now
            // await this.startWebUIServer();

            // Create main window
            this.createMainWindow();

            // Setup IPC handlers
            this.setupIPC();

            // Setup menu
            this.setupMenu();

            app.on('activate', () => {
                if (BrowserWindow.getAllWindows().length === 0) {
                    this.createMainWindow();
                }
            });
        });

        app.on('window-all-closed', () => {
            if (this.webServer) {
                this.webServer.close();
            }
            if (process.platform !== 'darwin') {
                app.quit();
            }
        });
    }

    async checkDependencies() {
        console.log('Checking system dependencies...');
        const result = await this.autoInstaller.checkAndInstallDependencies();

        if (!result.success && result.missingTools.length > 0) {
            dialog.showMessageBoxSync(this.mainWindow, {
                type: 'warning',
                title: 'Missing Dependencies',
                message: `Some tools are missing: ${result.missingTools.join(', ')}`,
                buttons: ['Continue Anyway', 'Exit']
            });
        }
    }

    async startWebUIServer() {
        const expressApp = express();
        this.webServer = http.createServer(expressApp);
        this.wsServer = new WebSocket.Server({ server: this.webServer });

        // Serve static files
        expressApp.use(express.static(path.join(__dirname, '../../renderer/web')));
        expressApp.use(express.json());
        expressApp.use(express.urlencoded({ extended: true }));

        // API Routes
        this.setupAPIRoutes(expressApp);

        // WebSocket handling
        this.setupWebSocket();

        // Start server
        this.webServer.listen(this.config.webUIPort, () => {
            console.log(`Web UI server running at http://localhost:${this.config.webUIPort}`);
        });
    }

    setupAPIRoutes(app) {
        // Server management
        app.get('/api/servers', (req, res) => {
            res.json({
                localMode: this.config.localMode,
                servers: this.config.remoteServers,
                currentServer: this.config.currentServer
            });
        });

        app.post('/api/servers/add', async (req, res) => {
            const server = req.body;
            this.config.remoteServers.push(server);
            this.store.set('remoteServers', this.config.remoteServers);
            res.json({ success: true, server });
        });

        app.post('/api/servers/connect', async (req, res) => {
            const { serverId } = req.body;
            const server = this.config.remoteServers.find(s => s.id === serverId);

            if (server) {
                this.config.currentServer = server;
                this.config.localMode = false;
                res.json({ success: true, server });
            } else {
                res.status(404).json({ error: 'Server not found' });
            }
        });

        // Deployment
        app.post('/api/deploy', async (req, res) => {
            const deploymentConfig = req.body;

            try {
                const result = await this.deploymentService.deployToServer(deploymentConfig);
                res.json(result);
            } catch (error) {
                res.status(500).json({ error: error.message });
            }
        });

        // File upload
        app.post('/api/upload', async (req, res) => {
            const { files, destination, server } = req.body;

            try {
                const result = await this.uploadService.uploadFiles({
                    files,
                    destination,
                    server: server || this.config.currentServer,
                    localMode: this.config.localMode
                });
                res.json(result);
            } catch (error) {
                res.status(500).json({ error: error.message });
            }
        });

        // System status
        app.get('/api/status', async (req, res) => {
            const status = {
                localMode: this.config.localMode,
                currentServer: this.config.currentServer,
                services: await this.getServicesStatus(),
                system: await this.getSystemInfo()
            };
            res.json(status);
        });

        // PBX Management
        app.get('/api/pbx/extensions', async (req, res) => {
            // Get extensions from current server
            const extensions = await this.getPBXExtensions();
            res.json(extensions);
        });

        app.post('/api/pbx/extensions', async (req, res) => {
            // Create new extension
            const extension = req.body;
            const result = await this.createPBXExtension(extension);
            res.json(result);
        });

        // FreePBX Integration
        app.get('/api/freepbx/status', async (req, res) => {
            const status = await this.getFreePBXStatus();
            res.json(status);
        });

        app.post('/api/freepbx/modules', async (req, res) => {
            const { module, action } = req.body;
            const result = await this.manageFreePBXModule(module, action);
            res.json(result);
        });
    }

    setupWebSocket() {
        this.wsServer.on('connection', (ws, req) => {
            console.log('New WebSocket connection from web UI');

            ws.on('message', async (message) => {
                try {
                    const data = JSON.parse(message);

                    switch (data.type) {
                        case 'command':
                            await this.handleCommand(ws, data);
                            break;
                        case 'upload':
                            await this.handleUpload(ws, data);
                            break;
                        case 'monitor':
                            await this.startMonitoring(ws, data);
                            break;
                        default:
                            ws.send(JSON.stringify({ error: 'Unknown message type' }));
                    }
                } catch (error) {
                    ws.send(JSON.stringify({ error: error.message }));
                }
            });

            ws.on('close', () => {
                console.log('WebSocket connection closed');
            });

            // Send initial status
            ws.send(JSON.stringify({
                type: 'connected',
                config: this.config
            }));
        });
    }

    createMainWindow() {
        this.mainWindow = new BrowserWindow({
            width: 1400,
            height: 900,
            minWidth: 1024,
            minHeight: 768,
            webPreferences: {
                nodeIntegration: false,
                contextIsolation: true,
                preload: path.join(__dirname, '../renderer/preload.js')
            },
            icon: path.join(__dirname, '../../assets/icon.png'),
            title: 'FlexPBX Desktop Manager',
            titleBarStyle: process.platform === 'darwin' ? 'hiddenInset' : 'default'
        });

        // Load HTML directly instead of web server
        const htmlPath = path.join(__dirname, '../renderer/index.html');
        this.mainWindow.loadFile(htmlPath);

        // Show when ready
        this.mainWindow.once('ready-to-show', () => {
            this.mainWindow.show();
        });

        // Handle external links
        this.mainWindow.webContents.setWindowOpenHandler(({ url }) => {
            shell.openExternal(url);
            return { action: 'deny' };
        });
    }

    setupIPC() {
        // File selection
        ipcMain.handle('select-directory', async () => {
            const result = await dialog.showOpenDialog(this.mainWindow, {
                properties: ['openDirectory']
            });
            return result.filePaths[0];
        });

        ipcMain.handle('select-files', async () => {
            const result = await dialog.showOpenDialog(this.mainWindow, {
                properties: ['openFile', 'multiSelections']
            });
            return result.filePaths;
        });

        // Server deployment
        ipcMain.handle('deploy-local', async (event, config) => {
            return await this.deployLocal(config);
        });

        ipcMain.handle('deploy-remote', async (event, config) => {
            return await this.deploymentService.deployToServer(config);
        });

        // File upload
        ipcMain.handle('upload-files', async (event, config) => {
            return await this.uploadService.uploadFiles(config);
        });

        // System commands
        ipcMain.handle('check-dependencies', async () => {
            return await this.autoInstaller.checkAndInstallDependencies();
        });

        ipcMain.handle('get-system-info', async () => {
            return await this.getSystemInfo();
        });

        // Remote server management
        ipcMain.handle('test-connection', async (event, config) => {
            return await this.testRemoteConnection(config);
        });
    }

    setupMenu() {
        const template = [
            {
                label: 'FlexPBX',
                submenu: [
                    { label: 'About FlexPBX', role: 'about' },
                    { type: 'separator' },
                    { label: 'Preferences...', accelerator: 'Cmd+,', click: () => this.openPreferences() },
                    { type: 'separator' },
                    { label: 'Quit', accelerator: 'Cmd+Q', role: 'quit' }
                ]
            },
            {
                label: 'Server',
                submenu: [
                    {
                        label: 'Local Mode',
                        type: 'checkbox',
                        checked: this.config.localMode,
                        click: () => this.toggleLocalMode()
                    },
                    { type: 'separator' },
                    { label: 'Add Remote Server...', click: () => this.addRemoteServer() },
                    { label: 'Manage Servers...', click: () => this.manageServers() },
                    { type: 'separator' },
                    { label: 'Deploy to Server...', click: () => this.deployToServer() },
                    { label: 'Upload Files...', click: () => this.uploadFiles() }
                ]
            },
            {
                label: 'Tools',
                submenu: [
                    { label: 'Check Dependencies', click: () => this.checkDependencies() },
                    { label: 'FreePBX Admin', click: () => this.openFreePBXAdmin() },
                    { label: 'Asterisk CLI', click: () => this.openAsteriskCLI() },
                    { type: 'separator' },
                    { label: 'System Monitor', click: () => this.openSystemMonitor() },
                    { label: 'Log Viewer', click: () => this.openLogViewer() }
                ]
            },
            {
                label: 'View',
                submenu: [
                    { label: 'Reload', accelerator: 'Cmd+R', role: 'reload' },
                    { label: 'Toggle DevTools', accelerator: 'F12', role: 'toggleDevTools' },
                    { type: 'separator' },
                    { label: 'Zoom In', accelerator: 'Cmd+Plus', role: 'zoomIn' },
                    { label: 'Zoom Out', accelerator: 'Cmd+-', role: 'zoomOut' },
                    { label: 'Reset Zoom', accelerator: 'Cmd+0', role: 'resetZoom' }
                ]
            },
            {
                label: 'Help',
                submenu: [
                    { label: 'Documentation', click: () => shell.openExternal('https://flexpbx.docs') },
                    { label: 'Support', click: () => shell.openExternal('https://flexpbx.support') }
                ]
            }
        ];

        const menu = Menu.buildFromTemplate(template);
        Menu.setApplicationMenu(menu);
    }

    // Helper methods
    async deployLocal(config) {
        // Deploy to local machine
        console.log('Deploying locally:', config);

        const installPath = config.installPath || '/opt/flexpbx';

        // Run minimal server installer
        const installerPath = path.join(__dirname, '../../../server/installer/minimal-server.sh');

        const { exec } = require('child_process');

        return new Promise((resolve, reject) => {
            exec(`bash ${installerPath} ${installPath} auto docker true`, (error, stdout, stderr) => {
                if (error) {
                    reject(error);
                } else {
                    resolve({ success: true, output: stdout });
                }
            });
        });
    }

    async testRemoteConnection(config) {
        try {
            const NodeSSH = require('node-ssh');
            const ssh = new NodeSSH();

            await ssh.connect({
                host: config.host,
                username: config.username,
                password: config.password,
                port: config.port || 22
            });

            const result = await ssh.execCommand('echo "Connection successful"');
            await ssh.dispose();

            return { success: true, message: result.stdout };
        } catch (error) {
            return { success: false, error: error.message };
        }
    }

    async getSystemInfo() {
        const os = require('os');

        return {
            platform: os.platform(),
            arch: os.arch(),
            cpus: os.cpus().length,
            memory: Math.round(os.totalmem() / (1024 * 1024 * 1024)) + ' GB',
            uptime: Math.round(os.uptime() / 3600) + ' hours',
            hostname: os.hostname(),
            homeDir: os.homedir()
        };
    }

    async getServicesStatus() {
        const services = {
            docker: false,
            nginx: false,
            apache: false,
            asterisk: false,
            freepbx: false,
            flexpbx: false
        };

        // Check each service
        const { exec } = require('child_process');
        const checkService = (command) => {
            return new Promise((resolve) => {
                exec(command, (error) => {
                    resolve(!error);
                });
            });
        };

        services.docker = await checkService('docker --version');
        services.nginx = await checkService('nginx -v');
        services.apache = await checkService('apache2 -v || httpd -v');
        services.asterisk = await checkService('asterisk -V');
        services.flexpbx = await checkService('systemctl status flexpbx');

        return services;
    }

    async getPBXExtensions() {
        // Get extensions from Asterisk/FreePBX
        if (this.config.localMode) {
            // Local query
            return this.queryLocalExtensions();
        } else {
            // Remote query
            return this.queryRemoteExtensions(this.config.currentServer);
        }
    }

    async queryLocalExtensions() {
        const { exec } = require('child_process');

        return new Promise((resolve, reject) => {
            exec('asterisk -rx "pjsip show endpoints"', (error, stdout) => {
                if (error) {
                    reject(error);
                } else {
                    // Parse output
                    const extensions = this.parseAsteriskOutput(stdout);
                    resolve(extensions);
                }
            });
        });
    }

    async queryRemoteExtensions(server) {
        const NodeSSH = require('node-ssh');
        const ssh = new NodeSSH();

        await ssh.connect({
            host: server.host,
            username: server.username,
            password: server.password,
            port: server.port || 22
        });

        const result = await ssh.execCommand('asterisk -rx "pjsip show endpoints"');
        await ssh.dispose();

        return this.parseAsteriskOutput(result.stdout);
    }

    parseAsteriskOutput(output) {
        // Parse Asterisk CLI output
        const lines = output.split('\n');
        const extensions = [];

        for (const line of lines) {
            if (line.includes('/')) {
                const parts = line.split(/\s+/);
                if (parts[0]) {
                    extensions.push({
                        extension: parts[0],
                        status: parts[1] || 'Unknown'
                    });
                }
            }
        }

        return extensions;
    }

    toggleLocalMode() {
        this.config.localMode = !this.config.localMode;
        this.store.set('localMode', this.config.localMode);

        // Notify web UI
        this.broadcastToWebUI({
            type: 'config-changed',
            config: this.config
        });
    }

    broadcastToWebUI(data) {
        if (this.wsServer) {
            this.wsServer.clients.forEach((client) => {
                if (client.readyState === WebSocket.OPEN) {
                    client.send(JSON.stringify(data));
                }
            });
        }
    }

    async handleCommand(ws, data) {
        const { command, params } = data;

        let result;
        switch (command) {
            case 'deploy':
                result = await this.deploymentService.deployToServer(params);
                break;
            case 'upload':
                result = await this.uploadService.uploadFiles(params);
                break;
            case 'status':
                result = await this.getServicesStatus();
                break;
            default:
                result = { error: 'Unknown command' };
        }

        ws.send(JSON.stringify({
            type: 'command-result',
            command,
            result
        }));
    }

    async handleUpload(ws, data) {
        const { files, destination } = data;

        const result = await this.uploadService.uploadFiles({
            files,
            destination,
            server: this.config.currentServer,
            localMode: this.config.localMode,
            onProgress: (progress) => {
                ws.send(JSON.stringify({
                    type: 'upload-progress',
                    progress
                }));
            }
        });

        ws.send(JSON.stringify({
            type: 'upload-complete',
            result
        }));
    }

    async startMonitoring(ws, data) {
        // Start real-time monitoring
        const interval = setInterval(async () => {
            const status = await this.getServicesStatus();
            const system = await this.getSystemInfo();

            ws.send(JSON.stringify({
                type: 'monitor-update',
                status,
                system,
                timestamp: Date.now()
            }));
        }, 5000);

        ws.on('close', () => {
            clearInterval(interval);
        });
    }
}

// Start the application
new FlexPBXUnifiedClient();

module.exports = FlexPBXUnifiedClient;