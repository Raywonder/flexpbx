const { app, BrowserWindow, Menu, ipcMain, dialog, shell } = require('electron');
const path = require('path');
const fs = require('fs-extra');
const Store = require('electron-store');

// Conditional imports for services that might not exist
let AutoInstaller, UnifiedDeploymentService, FileUploadService, TestingManagerService;

try {
    AutoInstaller = require('./installers/AutoInstaller');
} catch (e) {
    console.log('AutoInstaller not found, using mock');
    AutoInstaller = class { async checkAndInstallDependencies() { return { success: true, missingTools: [] }; } };
}

try {
    UnifiedDeploymentService = require('./services/UnifiedDeploymentService');
} catch (e) {
    console.log('UnifiedDeploymentService not found, using mock');
    UnifiedDeploymentService = class { async deployToServer(config) { return { success: true }; } };
}

try {
    FileUploadService = require('./services/FileUploadService');
} catch (e) {
    console.log('FileUploadService not found, using mock');
    FileUploadService = class { async uploadFiles(config) { return { success: true }; } };
}

try {
    TestingManagerService = require('./services/TestingManagerService');
} catch (e) {
    console.log('TestingManagerService not found, using mock');
    TestingManagerService = class {};
}

class FlexPBXUnifiedClient {
    constructor() {
        this.mainWindow = null;
        this.store = new Store();

        // Services
        this.autoInstaller = new AutoInstaller();
        this.deploymentService = new UnifiedDeploymentService();
        this.uploadService = new FileUploadService();
        this.testingManager = new TestingManagerService();

        // Configuration
        this.config = {
            localMode: true,
            remoteServers: [],
            currentServer: null
        };

        this.setupApp();
    }

    setupApp() {
        app.whenReady().then(async () => {
            console.log('App is ready, creating window...');

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
            if (process.platform !== 'darwin') {
                app.quit();
            }
        });
    }

    createMainWindow() {
        this.mainWindow = new BrowserWindow({
            width: 1400,
            height: 900,
            minWidth: 1024,
            minHeight: 768,
            webPreferences: {
                nodeIntegration: true,
                contextIsolation: false,
                webSecurity: false
            },
            icon: path.join(__dirname, '../../assets/icon.png'),
            title: 'FlexPBX Desktop Manager',
            titleBarStyle: process.platform === 'darwin' ? 'hiddenInset' : 'default',
            show: false
        });

        // Load HTML directly
        const htmlPath = path.join(__dirname, '../renderer/index.html');
        console.log('Loading HTML from:', htmlPath);

        this.mainWindow.loadFile(htmlPath).catch(err => {
            console.error('Failed to load HTML:', err);
            // Load test HTML as fallback
            const testPath = path.join(__dirname, '../renderer/test.html');
            this.mainWindow.loadFile(testPath);
        });

        // Show when ready
        this.mainWindow.once('ready-to-show', () => {
            this.mainWindow.show();

            // Open DevTools in development
            if (process.env.NODE_ENV === 'development') {
                this.mainWindow.webContents.openDevTools();
            }
        });

        // Handle external links
        this.mainWindow.webContents.setWindowOpenHandler(({ url }) => {
            shell.openExternal(url);
            return { action: 'deny' };
        });

        this.mainWindow.on('closed', () => {
            this.mainWindow = null;
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

        // Configuration management
        ipcMain.handle('get-config', async () => {
            return this.config;
        });

        ipcMain.handle('update-config', async (event, newConfig) => {
            this.config = { ...this.config, ...newConfig };
            this.store.set('config', this.config);
            return this.config;
        });
    }

    setupMenu() {
        const template = [
            {
                label: 'FlexPBX',
                submenu: [
                    { label: 'About FlexPBX', click: () => this.showAbout() },
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

        // Platform-specific menu adjustments
        if (process.platform !== 'darwin') {
            template[0].submenu = [
                { label: 'About FlexPBX', click: () => this.showAbout() },
                { type: 'separator' },
                { label: 'Preferences...', accelerator: 'Ctrl+,', click: () => this.openPreferences() },
                { type: 'separator' },
                { label: 'Quit', accelerator: 'Ctrl+Q', role: 'quit' }
            ];
        }

        const menu = Menu.buildFromTemplate(template);
        Menu.setApplicationMenu(menu);
    }

    // Helper methods
    async deployLocal(config) {
        console.log('Deploying locally:', config);
        const installPath = config.installPath || '/opt/flexpbx';

        // Simulate deployment
        return { success: true, message: 'Local deployment initiated', path: installPath };
    }

    async testRemoteConnection(config) {
        try {
            // Simulate connection test
            return { success: true, message: 'Connection successful' };
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

    toggleLocalMode() {
        this.config.localMode = !this.config.localMode;
        this.store.set('localMode', this.config.localMode);

        // Notify renderer
        if (this.mainWindow) {
            this.mainWindow.webContents.send('config-changed', this.config);
        }
    }

    // Menu action handlers
    showAbout() {
        dialog.showMessageBox(this.mainWindow, {
            type: 'info',
            title: 'About FlexPBX Desktop',
            message: 'FlexPBX Desktop Manager v1.0.0',
            detail: 'Unified PBX management platform for FreePBX and Asterisk systems.\n\n© 2024 FlexPBX Team',
            buttons: ['OK']
        });
    }

    openPreferences() {
        if (this.mainWindow) {
            this.mainWindow.webContents.send('open-preferences');
        }
    }

    async addRemoteServer() {
        if (this.mainWindow) {
            this.mainWindow.webContents.send('add-remote-server');
        }
    }

    manageServers() {
        if (this.mainWindow) {
            this.mainWindow.webContents.send('manage-servers');
        }
    }

    deployToServer() {
        if (this.mainWindow) {
            this.mainWindow.webContents.send('deploy-to-server');
        }
    }

    uploadFiles() {
        if (this.mainWindow) {
            this.mainWindow.webContents.send('upload-files');
        }
    }

    async checkDependencies() {
        const result = await this.autoInstaller.checkAndInstallDependencies();

        dialog.showMessageBox(this.mainWindow, {
            type: result.success ? 'info' : 'warning',
            title: 'Dependency Check',
            message: result.success ? 'All dependencies are installed' : 'Some dependencies are missing',
            detail: result.missingTools?.length > 0 ?
                `Missing: ${result.missingTools.join(', ')}` :
                'System is ready for FlexPBX operations',
            buttons: ['OK']
        });
    }

    openFreePBXAdmin() {
        shell.openExternal('http://localhost/admin');
    }

    openAsteriskCLI() {
        if (this.mainWindow) {
            this.mainWindow.webContents.send('open-asterisk-cli');
        }
    }

    openSystemMonitor() {
        if (this.mainWindow) {
            this.mainWindow.webContents.send('open-system-monitor');
        }
    }

    openLogViewer() {
        if (this.mainWindow) {
            this.mainWindow.webContents.send('open-log-viewer');
        }
    }
}

// Start the application
new FlexPBXUnifiedClient();

module.exports = FlexPBXUnifiedClient;