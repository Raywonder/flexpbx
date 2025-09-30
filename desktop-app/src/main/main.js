const { app, BrowserWindow, Menu, ipcMain, dialog, shell } = require('electron');
const path = require('path');
const fs = require('fs-extra');
const Store = require('electron-store');
const { spawn, exec } = require('child_process');
const os = require('os');

// Import services
const DockerService = require('./services/dockerService');
const DeploymentService = require('./services/deploymentService');
const NginxService = require('./services/nginxService');
const SSHService = require('./services/sshService');
const DNSService = require('./services/dnsService');

class FlexPBXDesktop {
    constructor() {
        this.mainWindow = null;
        this.store = new Store();
        this.dockerService = new DockerService();
        this.deploymentService = new DeploymentService();
        this.nginxService = new NginxService();
        this.sshService = new SSHService();
        this.dnsService = new DNSService();

        this.setupApp();
    }

    setupApp() {
        app.whenReady().then(() => {
            this.createMainWindow();
            this.setupMenu();
            this.setupIPC();

            app.on('activate', () => {
                if (BrowserWindow.getAllWindows().length === 0) {
                    this.createMainWindow();
                }
            });
        });

        app.on('window-all-closed', () => {
            if (process.platform !== 'darwin') {
                app.quit();
            }
        });

        // Handle deep links
        app.setAsDefaultProtocolClient('flexpbx');
    }

    createMainWindow() {
        this.mainWindow = new BrowserWindow({
            width: 1200,
            height: 800,
            minWidth: 800,
            minHeight: 600,
            webPreferences: {
                nodeIntegration: false,
                contextIsolation: true,
                enableRemoteModule: false,
                preload: path.join(__dirname, '../renderer/preload.js')
            },
            icon: path.join(__dirname, '../../assets/icon.png'),
            title: 'FlexPBX Desktop Manager',
            titleBarStyle: 'hiddenInset',
            show: false
        });

        // Load the main interface
        this.mainWindow.loadFile(path.join(__dirname, '../renderer/index.html'));

        // Show window when ready
        this.mainWindow.once('ready-to-show', () => {
            this.mainWindow.show();

            // Check for updates and system requirements
            this.checkSystemRequirements();
        });

        // Handle external links
        this.mainWindow.webContents.setWindowOpenHandler(({ url }) => {
            shell.openExternal(url);
            return { action: 'deny' };
        });

        // Development tools
        if (process.env.NODE_ENV === 'development') {
            this.mainWindow.webContents.openDevTools();
        }
    }

    setupMenu() {
        const template = [
            {
                label: 'FlexPBX',
                submenu: [
                    { label: 'About FlexPBX Desktop', role: 'about' },
                    { type: 'separator' },
                    { label: 'Preferences...', accelerator: 'Cmd+,', click: () => this.openPreferences() },
                    { type: 'separator' },
                    { label: 'Services', submenu: [] },
                    { type: 'separator' },
                    { label: 'Hide FlexPBX', accelerator: 'Cmd+H', role: 'hide' },
                    { label: 'Hide Others', accelerator: 'Cmd+Alt+H', role: 'hideothers' },
                    { label: 'Show All', role: 'unhide' },
                    { type: 'separator' },
                    { label: 'Quit FlexPBX', accelerator: 'Cmd+Q', role: 'quit' }
                ]
            },
            {
                label: 'Server',
                submenu: [
                    { label: 'New Local Installation...', accelerator: 'Cmd+N', click: () => this.newLocalInstall() },
                    { label: 'Deploy to Remote Server...', accelerator: 'Cmd+D', click: () => this.deployRemote() },
                    { type: 'separator' },
                    { label: 'Connect to Existing Server...', accelerator: 'Cmd+O', click: () => this.connectServer() },
                    { type: 'separator' },
                    { label: 'Server Status', click: () => this.showServerStatus() },
                    { label: 'View Logs', click: () => this.viewLogs() }
                ]
            },
            {
                label: 'Configuration',
                submenu: [
                    { label: 'Nginx Configuration...', click: () => this.configureNginx() },
                    { label: 'SSL Certificates...', click: () => this.manageSSL() },
                    { label: 'Firewall Settings...', click: () => this.configureFirewall() },
                    { type: 'separator' },
                    { label: 'Backup & Restore...', click: () => this.backupRestore() }
                ]
            },
            {
                label: 'View',
                submenu: [
                    { label: 'Reload', accelerator: 'Cmd+R', role: 'reload' },
                    { label: 'Force Reload', accelerator: 'Cmd+Shift+R', role: 'forceReload' },
                    { label: 'Toggle Developer Tools', accelerator: 'F12', role: 'toggleDevTools' },
                    { type: 'separator' },
                    { label: 'Actual Size', accelerator: 'Cmd+0', role: 'resetZoom' },
                    { label: 'Zoom In', accelerator: 'Cmd+Plus', role: 'zoomIn' },
                    { label: 'Zoom Out', accelerator: 'Cmd+-', role: 'zoomOut' },
                    { type: 'separator' },
                    { label: 'Toggle Fullscreen', accelerator: 'Ctrl+Cmd+F', role: 'togglefullscreen' }
                ]
            },
            {
                label: 'Window',
                submenu: [
                    { label: 'Minimize', accelerator: 'Cmd+M', role: 'minimize' },
                    { label: 'Close', accelerator: 'Cmd+W', role: 'close' },
                    { type: 'separator' },
                    { label: 'Bring All to Front', role: 'front' }
                ]
            },
            {
                label: 'Help',
                submenu: [
                    { label: 'FlexPBX Documentation', click: () => shell.openExternal('https://github.com/Raywonder/flexpbx') },
                    { label: 'Report Issue', click: () => shell.openExternal('https://github.com/Raywonder/flexpbx/issues') },
                    { type: 'separator' },
                    { label: 'Check for Updates', click: () => this.checkForUpdates() }
                ]
            }
        ];

        const menu = Menu.buildFromTemplate(template);
        Menu.setApplicationMenu(menu);
    }

    setupIPC() {
        // System information
        ipcMain.handle('get-system-info', () => {
            return {
                platform: process.platform,
                arch: process.arch,
                nodeVersion: process.version,
                electronVersion: process.versions.electron,
                homeDir: os.homedir(),
                hostname: os.hostname()
            };
        });

        // Store operations
        ipcMain.handle('store-get', (event, key) => {
            return this.store.get(key);
        });

        ipcMain.handle('store-set', (event, key, value) => {
            this.store.set(key, value);
        });

        // Docker operations
        ipcMain.handle('docker-check', () => {
            return this.dockerService.checkDockerInstallation();
        });

        ipcMain.handle('docker-install-local', (event, config) => {
            return this.dockerService.installLocal(config);
        });

        ipcMain.handle('docker-status', (event, installPath) => {
            return this.dockerService.getStatus(installPath);
        });

        ipcMain.handle('docker-start', (event, installPath) => {
            return this.dockerService.start(installPath);
        });

        ipcMain.handle('docker-stop', (event, installPath) => {
            return this.dockerService.stop(installPath);
        });

        ipcMain.handle('docker-logs', (event, installPath) => {
            return this.dockerService.getLogs(installPath);
        });

        // Remote deployment
        ipcMain.handle('deploy-remote', (event, config) => {
            return this.deploymentService.deployToRemote(config);
        });

        ipcMain.handle('test-connection', (event, connectionConfig) => {
            return this.sshService.testConnection(connectionConfig);
        });

        // Nginx configuration
        ipcMain.handle('nginx-configure', (event, config) => {
            return this.nginxService.configure(config);
        });

        ipcMain.handle('nginx-test', (event, configPath) => {
            return this.nginxService.testConfiguration(configPath);
        });

        ipcMain.handle('nginx-reload', () => {
            return this.nginxService.reload();
        });

        // DNS operations
        ipcMain.handle('dns-create-record', (event, config) => {
            return this.dnsService.createARecord(config);
        });

        ipcMain.handle('dns-verify-record', (event, hostname, expectedIp) => {
            return this.dnsService.verifyRecord(hostname, expectedIp);
        });

        ipcMain.handle('dns-get-public-ip', () => {
            return this.dnsService.getCurrentPublicIP();
        });

        ipcMain.handle('dns-test-resolution', (event, hostname) => {
            return this.dnsService.testDNSResolution(hostname);
        });

        ipcMain.handle('dns-get-providers', () => {
            return this.dnsService.getSupportedProviders();
        });

        ipcMain.handle('dns-get-provider-schema', (event, provider) => {
            return this.dnsService.getProviderCredentialsSchema(provider);
        });

        // File operations
        ipcMain.handle('select-directory', async () => {
            const result = await dialog.showOpenDialog(this.mainWindow, {
                properties: ['openDirectory', 'createDirectory'],
                title: 'Select Installation Directory'
            });

            return result.canceled ? null : result.filePaths[0];
        });

        ipcMain.handle('select-file', async (event, options) => {
            const result = await dialog.showOpenDialog(this.mainWindow, {
                properties: ['openFile'],
                filters: options.filters || [],
                title: options.title || 'Select File'
            });

            return result.canceled ? null : result.filePaths[0];
        });

        // External URL handling
        ipcMain.handle('open-external', (event, url) => {
            shell.openExternal(url);
        });

        // Show message boxes
        ipcMain.handle('show-message', (event, options) => {
            return dialog.showMessageBox(this.mainWindow, options);
        });
    }

    async checkSystemRequirements() {
        const requirements = {
            docker: await this.dockerService.checkDockerInstallation(),
            diskSpace: await this.checkDiskSpace(),
            permissions: await this.checkPermissions()
        };

        this.mainWindow.webContents.send('system-requirements', requirements);
    }

    async checkDiskSpace() {
        try {
            const stats = await fs.stat(os.homedir());
            // Simplified disk space check - in production would use proper disk usage library
            return { available: true, space: '> 1GB' };
        } catch (error) {
            return { available: false, error: error.message };
        }
    }

    async checkPermissions() {
        try {
            const testDir = path.join(os.tmpdir(), 'flexpbx-test');
            await fs.ensureDir(testDir);
            await fs.remove(testDir);
            return { writable: true };
        } catch (error) {
            return { writable: false, error: error.message };
        }
    }

    // Menu handlers
    openPreferences() {
        this.mainWindow.webContents.send('open-preferences');
    }

    newLocalInstall() {
        this.mainWindow.webContents.send('new-local-install');
    }

    deployRemote() {
        this.mainWindow.webContents.send('deploy-remote');
    }

    connectServer() {
        this.mainWindow.webContents.send('connect-server');
    }

    showServerStatus() {
        this.mainWindow.webContents.send('show-server-status');
    }

    viewLogs() {
        this.mainWindow.webContents.send('view-logs');
    }

    configureNginx() {
        this.mainWindow.webContents.send('configure-nginx');
    }

    manageSSL() {
        this.mainWindow.webContents.send('manage-ssl');
    }

    configureFirewall() {
        this.mainWindow.webContents.send('configure-firewall');
    }

    backupRestore() {
        this.mainWindow.webContents.send('backup-restore');
    }

    checkForUpdates() {
        // Implement update checking logic
        this.mainWindow.webContents.send('check-updates');
    }
}

// Create and start the application
new FlexPBXDesktop();

// Handle unhandled exceptions
process.on('uncaughtException', (error) => {
    console.error('Uncaught Exception:', error);
});

process.on('unhandledRejection', (reason, promise) => {
    console.error('Unhandled Rejection at:', promise, 'reason:', reason);
});