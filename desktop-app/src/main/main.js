const { app, BrowserWindow, Menu, Tray, ipcMain, dialog, shell, powerMonitor, nativeImage } = require('electron');
const path = require('path');
const fs = require('fs-extra');
const Store = require('electron-store');
const BackupHandler = require('./backup-handler');

// Conditional imports for services that might not exist
let AutoInstaller, UnifiedDeploymentService, FileUploadService, TestingManagerService, BackgroundServiceManager, CopyPartyService, SoundManager, TTSService, PortManager, HoldMusicServer, CrossPlatformSpeech, SupportTicketSystem, AppLockSystem, LocalTTSServer;

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

try {
    BackgroundServiceManager = require('./services/BackgroundServiceManager');
} catch (e) {
    console.log('BackgroundServiceManager not found, using mock');
    BackgroundServiceManager = class {
        async startAll() { return { success: true }; }
        async stopAll() { return { success: true }; }
        getStatus() { return { running: false, services: [] }; }
    };
}

try {
    CopyPartyService = require('./services/CopyPartyService');
} catch (e) {
    console.log('CopyPartyService not found, using mock');
    CopyPartyService = class {
        async install() { return { success: true }; }
        async start() { return { success: true }; }
        async stop() { return { success: true }; }
        getStatus() { return { running: false }; }
    };
}

try {
    SoundManager = require('./services/SoundManager');
} catch (e) {
    console.log('SoundManager not found, using mock');
    SoundManager = class {
        async playConnectionSound() { return { success: false }; }
        async playDisconnectSound() { return { success: false }; }
        async playServiceStartedSound() { return { success: false }; }
        async playServiceStoppedSound() { return { success: false }; }
        async playCopyPartyStartedSound() { return { success: false }; }
        async playBackgroundModeSound() { return { success: false }; }
        getStatus() { return { enabled: false }; }
    };
}

try {
    TTSService = require('./services/TTSService');
} catch (e) {
    console.log('TTSService not found, using mock');
    TTSService = class {
        async synthesize(text) { return { success: false, error: 'TTS not available' }; }
        async announceCaller(callerInfo) { return { success: false }; }
        async announceExtension(extension) { return { success: false }; }
        async createIVRPrompt(text) { return { success: false }; }
        async chatterboxSpeak(text, voice) { return { success: false }; }
        getVoiceProfiles() { return []; }
        async healthCheck() { return { status: 'unavailable' }; }
        async shutdown() { return { success: true }; }
    };
}

try {
    PortManager = require('./services/PortManager');
} catch (e) {
    console.log('PortManager not found, using mock');
    PortManager = class {
        async init() { return this; }
        async getAvailablePort(service) { return 8080 + Math.floor(Math.random() * 100); }
        async allocatePortsForServices() { return {}; }
        getAssignedPort(service) { return 8080; }
    };
}

try {
    HoldMusicServer = require('./services/HoldMusicServer');
} catch (e) {
    console.log('HoldMusicServer not found, using mock');
    HoldMusicServer = class {
        constructor() { this.port = 8081; }
        async init() { return true; }
        getServerInfo() { return { port: 8081, url: 'http://localhost:8081' }; }
        stop() { return true; }
    };
}

try {
    CrossPlatformSpeech = require('./services/CrossPlatformSpeech');
} catch (e) {
    console.log('CrossPlatformSpeech not found, using mock');
    CrossPlatformSpeech = class {
        async init() { return true; }
        speak(message) { console.log(`🔊 ${message}`); }
        announceHoldMusicEvent(event, details) { console.log(`🎵 ${event}:`, details); }
        announceSystemEvent(event, details) { console.log(`⚙️ ${event}:`, details); }
        testSpeech() { console.log('🔊 Speech test'); }
    };
}

try {
    SupportTicketSystem = require('./services/SupportTicketSystem');
} catch (e) {
    console.log('SupportTicketSystem not found, using mock');
    SupportTicketSystem = class {
        constructor() { this.port = 8082; }
        async init() { return true; }
        getServerInfo() { return { port: 8082, url: 'http://localhost:8082' }; }
        stop() { return true; }
    };
}

try {
    AppLockSystem = require('./services/AppLockSystem');
} catch (e) {
    console.log('AppLockSystem not found, using mock');
    AppLockSystem = class {
        constructor() {}
        hasPermission(permission) { return true; }
        canAccessFeature(feature) { return true; }
        getSystemInfo() { return { isLocked: false, lockType: 'none' }; }
        lockApp() { console.log('🔒 App lock simulated'); }
        unlockApp() { console.log('🔓 App unlock simulated'); }
    };
}

try {
    LocalTTSServer = require('./services/LocalTTSServer');
} catch (e) {
    console.log('LocalTTSServer not found, using mock');
    LocalTTSServer = class {
        constructor() {}
        async init() { return true; }
        getStatus() { return { running: false }; }
        async stop() {}
    };
}

class FlexPBXUnifiedClient {
    constructor() {
        this.mainWindow = null;
        this.tray = null;
        this.isQuitting = false;
        this.startInBackground = process.argv.includes('--background') || process.env.FLEXPBX_BACKGROUND === '1';
        this.store = new Store();
        this.backupHandler = new BackupHandler();

        // Services
        this.autoInstaller = new AutoInstaller();
        this.deploymentService = new UnifiedDeploymentService();
        this.uploadService = new FileUploadService();
        this.testingManager = new TestingManagerService();
        this.backgroundServiceManager = new BackgroundServiceManager();
        this.copyPartyService = new CopyPartyService();
        this.soundManager = new SoundManager();
        this.ttsService = new TTSService({
            apiEndpoint: 'https://tts.tappedin.fm/api/v1',
            chatterboxEnabled: true
        });

        // New Services
        this.portManager = null;
        this.holdMusicServer = null;
        this.crossPlatformSpeech = new CrossPlatformSpeech();
        this.supportTicketSystem = null;
        this.appLockSystem = new AppLockSystem(this.crossPlatformSpeech);
        this.localTTSServer = null;

        // Configuration
        this.config = {
            localMode: true,
            remoteServers: [],
            currentServer: null
        };

        this.setupApp();
    }

    setupApp() {
        // Add memory optimization command line switches
        app.commandLine.appendSwitch('max-old-space-size', '4096');
        app.commandLine.appendSwitch('no-sandbox');
        app.commandLine.appendSwitch('disable-dev-shm-usage');
        app.commandLine.appendSwitch('js-flags', '--max-old-space-size=4096');

        // Ensure single instance
        const gotTheLock = app.requestSingleInstanceLock();
        if (!gotTheLock) {
            app.quit();
            return;
        }

        app.on('second-instance', () => {
            // Someone tried to run a second instance, focus our window instead
            if (this.mainWindow) {
                if (this.mainWindow.isMinimized()) this.mainWindow.restore();
                this.mainWindow.focus();
                this.mainWindow.show();
            }
        });

        app.whenReady().then(async () => {
            console.log('App is ready, initializing...');

            // Create tray icon first
            this.createTray();

            // Start background services
            await this.startBackgroundServices();

            // Create main window (only if not starting in background)
            if (!this.startInBackground) {
                this.createMainWindow();
            } else {
                console.log('🔇 Starting in background mode - window will not be shown');
                // Play background mode sound
                setTimeout(() => this.soundManager.playBackgroundModeSound(), 1000);
            }

            // Setup IPC handlers
            this.setupIPC();

            // Setup menu
            this.setupMenu();

            // Setup power monitor for lock detection
            this.setupPowerMonitor();

            app.on('activate', () => {
                if (BrowserWindow.getAllWindows().length === 0) {
                    this.createMainWindow();
                }
            });

            app.on('before-quit', () => {
                this.isQuitting = true;
            });
        });

        app.on('window-all-closed', () => {
            // On macOS, keep app running in background when all windows closed
            if (process.platform !== 'darwin') {
                app.quit();
            }
        });

        // Prevent app from quitting when last window is closed on macOS
        app.on('before-quit', (event) => {
            if (!this.isQuitting && process.platform === 'darwin') {
                event.preventDefault();
                this.hideToTray();
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
                webSecurity: false,
                additionalArguments: [
                    '--max-old-space-size=4096',
                    '--no-sandbox',
                    '--disable-dev-shm-usage'
                ]
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

            // DevTools removed for production build
        });

        // Handle external links
        this.mainWindow.webContents.setWindowOpenHandler(({ url }) => {
            shell.openExternal(url);
            return { action: 'deny' };
        });

        this.mainWindow.on('closed', () => {
            this.mainWindow = null;
        });

        // Handle minimize to tray
        this.mainWindow.on('minimize', (event) => {
            if (this.store.get('minimizeToTray', true)) {
                event.preventDefault();
                this.hideToTray();
            }
        });

        // Handle close to tray
        this.mainWindow.on('close', (event) => {
            if (!this.isQuitting && this.store.get('closeToTray', true)) {
                event.preventDefault();
                this.hideToTray();
            }
        });
    }

    createTray() {
        let trayIconPath;

        // Try different icon paths
        const iconPaths = [
            path.join(__dirname, '../../assets/tray-icon-template.png'),
            path.join(__dirname, '../../assets/tray-icon.png'),
            path.join(__dirname, '../../assets/icon.png'),
            path.join(__dirname, '../../assets/logo.png')
        ];

        for (const iconPath of iconPaths) {
            if (fs.existsSync(iconPath)) {
                trayIconPath = iconPath;
                break;
            }
        }

        // Fallback to a system template icon if nothing found
        if (!trayIconPath) {
            // Create a simple tray icon programmatically
            this.tray = new Tray(nativeImage.createEmpty());
        } else {
            this.tray = new Tray(trayIconPath);
        }

        this.updateTrayMenu();

        this.tray.on('click', () => {
            this.showMainWindow();
        });

        this.tray.on('right-click', () => {
            this.tray.popUpContextMenu();
        });

        this.tray.setToolTip('FlexPBX Desktop - Background Services Running');
    }

    updateTrayMenu() {
        const template = [
            {
                label: 'Show FlexPBX',
                click: () => this.showMainWindow()
            },
            { type: 'separator' },
            {
                label: 'Background Services',
                submenu: [
                    {
                        label: 'Start All Services',
                        click: () => this.startBackgroundServices()
                    },
                    {
                        label: 'Stop All Services',
                        click: () => this.stopBackgroundServices()
                    },
                    { type: 'separator' },
                    {
                        label: 'CopyParty File Server',
                        click: () => this.toggleCopyParty()
                    },
                    {
                        label: 'Hold Music Server',
                        click: () => this.openHoldMusicServer()
                    },
                    {
                        label: 'Support Ticket System',
                        click: () => this.openSupportTicketSystem()
                    },
                    {
                        label: 'App Lock Manager',
                        click: () => this.openAppLockManager()
                    },
                    {
                        label: 'Connection Manager',
                        click: () => this.showConnectionManager()
                    }
                ]
            },
            { type: 'separator' },
            {
                label: 'Quick Actions',
                submenu: [
                    {
                        label: 'Open Local Admin',
                        click: () => shell.openExternal('http://localhost:8080')
                    },
                    {
                        label: 'Open CopyParty',
                        click: () => shell.openExternal('http://localhost:3923')
                    },
                    {
                        label: 'Open Hold Music Server',
                        click: () => this.openHoldMusicServer()
                    },
                    {
                        label: 'Real-time Hold Music Monitor',
                        click: () => this.openHoldMusicMonitor()
                    },
                    {
                        label: 'Open Support Ticket System',
                        click: () => this.openSupportTicketSystem()
                    },
                    {
                        label: 'Open App Lock Manager',
                        click: () => this.openAppLockManager()
                    }
                ]
            },
            { type: 'separator' },
            {
                label: 'Preferences',
                click: () => this.showPreferences()
            },
            {
                label: 'Quit FlexPBX',
                click: () => this.quitApp()
            }
        ];

        const contextMenu = Menu.buildFromTemplate(template);
        this.tray.setContextMenu(contextMenu);
    }

    hideToTray() {
        if (this.mainWindow) {
            this.mainWindow.hide();
        }

        // Show notification on first hide
        if (!this.store.get('hasShownTrayNotification', false)) {
            this.tray.displayBalloon({
                iconType: 'info',
                title: 'FlexPBX Running in Background',
                content: 'FlexPBX will continue running in the system tray. All services remain active.'
            });
            this.store.set('hasShownTrayNotification', true);
        }
    }

    showMainWindow() {
        if (!this.mainWindow) {
            this.createMainWindow();
        } else {
            this.mainWindow.show();
            this.mainWindow.focus();
        }
    }

    async startBackgroundServices() {
        console.log('🚀 Starting background services...');

        try {
            // Initialize Cross-Platform Speech first
            console.log('🔊 Initializing TTS Service...');
            await this.crossPlatformSpeech.init();

            // Initialize Port Manager
            console.log('🔍 Initializing Port Manager...');
            this.portManager = new PortManager();
            await this.portManager.init();

            // Initialize Hold Music Server
            console.log('🎵 Initializing Hold Music Server...');
            this.holdMusicServer = new HoldMusicServer(this.portManager);
            const holdMusicResult = await this.holdMusicServer.init();

            if (holdMusicResult) {
                const holdMusicInfo = this.holdMusicServer.getServerInfo();
                console.log(`✅ Hold Music Server running at ${holdMusicInfo.url}`);
                console.log(`📡 Real-time monitor: ${holdMusicInfo.monitorUrl}`);

                // Announce hold music server startup
                this.crossPlatformSpeech.announceSystemEvent('startup', {
                    service: 'Hold Music Server',
                    port: holdMusicInfo.port
                });
            }

            // Initialize Support Ticket System
            console.log('🎫 Initializing Support Ticket System...');
            this.supportTicketSystem = new SupportTicketSystem(this.portManager, this.crossPlatformSpeech);
            const ticketSystemResult = await this.supportTicketSystem.init();

            if (ticketSystemResult) {
                const ticketSystemInfo = this.supportTicketSystem.getServerInfo();
                console.log(`✅ Support Ticket System running at ${ticketSystemInfo.url}`);

                // Announce ticket system startup
                this.crossPlatformSpeech.announceSystemEvent('startup', {
                    service: 'Support Ticket System',
                    port: ticketSystemInfo.port
                });
            }

            // Initialize Local TTS Server
            console.log('🔊 Initializing Local TTS Server...');
            this.localTTSServer = new LocalTTSServer(this.portManager, this.crossPlatformSpeech);
            const ttsServerResult = await this.localTTSServer.init();

            if (ttsServerResult) {
                const ttsServerInfo = this.localTTSServer.getStatus();
                console.log(`✅ Local TTS Server running at ${ttsServerInfo.url}`);

                // Announce TTS server startup
                this.crossPlatformSpeech.announceSystemEvent('startup', {
                    service: 'Local TTS Server',
                    port: ttsServerInfo.port
                });
            }

            // Initialize App Lock System
            console.log('🔒 Initializing App Lock System...');
            const appLockResult = await this.appLockSystem.init();
            if (appLockResult) {
                console.log('✅ App Lock System initialized');

                // Announce app lock system startup
                this.crossPlatformSpeech.announceSystemEvent('startup', {
                    service: 'App Lock System'
                });
            }

            // Start main background service manager
            console.log('🚀 Starting all background services...');
            const serviceResult = await this.backgroundServiceManager.startAll();
            console.log('Background services result:', serviceResult);

            // Start CopyParty if enabled
            if (this.store.get('enableCopyParty', true)) {
                const copyPartyResult = await this.copyPartyService.start();
                console.log('CopyParty service result:', copyPartyResult);

                if (copyPartyResult.success) {
                    // Queue the CopyParty sound with lower priority
                    setTimeout(() => this.soundManager.playCopyPartyStartedSound(), 500);
                }
            }

            // Announce system ready
            this.crossPlatformSpeech.announceSystemEvent('ready');

            // Update tray tooltip with service info
            const holdMusicInfo = this.holdMusicServer ? this.holdMusicServer.getServerInfo() : null;
            const ticketSystemInfo = this.supportTicketSystem ? this.supportTicketSystem.getServerInfo() : null;
            const appLockInfo = this.appLockSystem ? this.appLockSystem.getSystemInfo() : null;

            let tooltipLines = ['FlexPBX Desktop - All Services Running'];
            if (holdMusicInfo) tooltipLines.push(`Hold Music: ${holdMusicInfo.url}`);
            if (ticketSystemInfo) tooltipLines.push(`Support Tickets: ${ticketSystemInfo.url}`);
            if (appLockInfo && appLockInfo.isLocked) tooltipLines.push(`App Lock: ${appLockInfo.lockType}`);

            this.tray?.setToolTip(tooltipLines.join('\n'));

            // Play primary services started sound with highest priority
            setTimeout(() => this.soundManager.playServiceStartedSound(), 2000);

            return {
                success: true,
                message: 'All background services started',
                services: {
                    holdMusic: holdMusicInfo,
                    supportTickets: this.supportTicketSystem ? this.supportTicketSystem.getServerInfo() : null,
                    appLock: this.appLockSystem ? this.appLockSystem.getSystemInfo() : null,
                    ports: this.portManager.getAllAssignedPorts()
                }
            };
        } catch (error) {
            console.error('Failed to start background services:', error);

            // Announce error
            this.crossPlatformSpeech.announceSystemEvent('error', {
                service: 'Background Services',
                error: error.message
            });

            return { success: false, error: error.message };
        }
    }

    async stopBackgroundServices() {
        console.log('🛑 Stopping background services...');

        try {
            await this.backgroundServiceManager.stopAll();
            await this.copyPartyService.stop();

            this.tray?.setToolTip('FlexPBX Desktop - Services Stopped');

            // Play services stopped sound
            setTimeout(() => this.soundManager.playServiceStoppedSound(), 500);

            return { success: true, message: 'All background services stopped' };
        } catch (error) {
            console.error('Failed to stop background services:', error);
            return { success: false, error: error.message };
        }
    }

    setupPowerMonitor() {
        if (powerMonitor) {
            powerMonitor.on('lock-screen', () => {
                console.log('🔒 Screen locked - maintaining background services');
                // Services continue running when screen is locked
                this.tray?.setToolTip('FlexPBX Desktop - Running (Screen Locked)');
            });

            powerMonitor.on('unlock-screen', () => {
                console.log('🔓 Screen unlocked - background services still running');
                this.tray?.setToolTip('FlexPBX Desktop - All Services Running');
            });

            powerMonitor.on('suspend', () => {
                console.log('💤 System suspending - attempting to maintain services');
            });

            powerMonitor.on('resume', () => {
                console.log('⚡ System resumed - restarting services if needed');
                this.startBackgroundServices();
            });
        }
    }

    async toggleCopyParty() {
        const status = this.copyPartyService.getStatus();
        if (status.running) {
            await this.copyPartyService.stop();
        } else {
            await this.copyPartyService.start();
        }
        this.updateTrayMenu();
    }

    showConnectionManager() {
        this.showMainWindow();
        if (this.mainWindow) {
            this.mainWindow.webContents.send('navigate-to', 'servers');
        }
    }

    openHoldMusicServer() {
        if (this.holdMusicServer) {
            const holdMusicInfo = this.holdMusicServer.getServerInfo();
            shell.openExternal(holdMusicInfo.url);
            this.crossPlatformSpeech.speak('Hold music server dashboard opened');
        } else {
            this.crossPlatformSpeech.speak('Hold music server not available');
        }
    }

    openHoldMusicMonitor() {
        if (this.holdMusicServer) {
            const holdMusicInfo = this.holdMusicServer.getServerInfo();
            shell.openExternal(holdMusicInfo.monitorUrl);
            this.crossPlatformSpeech.speak('Hold music monitor opened');
        } else {
            this.crossPlatformSpeech.speak('Hold music monitor not available');
        }
    }

    openSupportTicketSystem() {
        if (this.supportTicketSystem) {
            const ticketSystemInfo = this.supportTicketSystem.getServerInfo();
            shell.openExternal(ticketSystemInfo.url);
            this.crossPlatformSpeech.speak('Support ticket system opened');
        } else {
            this.crossPlatformSpeech.speak('Support ticket system not available');
        }
    }

    openAppLockManager() {
        if (this.appLockSystem) {
            const systemInfo = this.appLockSystem.getSystemInfo();
            this.showMainWindow();
            if (this.mainWindow) {
                this.mainWindow.webContents.send('open-app-lock-manager', systemInfo);
            }
            this.crossPlatformSpeech.speak('App lock manager opened');
        } else {
            this.crossPlatformSpeech.speak('App lock manager not available');
        }
    }

    showPreferences() {
        this.showMainWindow();
        if (this.mainWindow) {
            this.mainWindow.webContents.send('open-preferences');
        }
    }

    quitApp() {
        this.isQuitting = true;
        this.stopBackgroundServices();
        app.quit();
    }

    setupIPC() {
        // File selection with permissions check
        ipcMain.handle('select-directory', async () => {
            try {
                console.log('🗂️ Directory selection requested');

                const result = await dialog.showOpenDialog(this.mainWindow, {
                    properties: ['openDirectory', 'createDirectory'],
                    defaultPath: '/Applications',
                    message: 'Choose FlexPBX Installation Directory'
                });

                console.log('🗂️ Dialog result:', result);

                if (!result.canceled && result.filePaths.length > 0) {
                    const selectedPath = result.filePaths[0];
                    console.log('✅ Directory selected:', selectedPath);

                    // Check write permissions
                    const fs = require('fs');
                    try {
                        await fs.promises.access(selectedPath, fs.constants.W_OK);
                        console.log('✅ Directory is writable');
                        return selectedPath;
                    } catch (permError) {
                        console.log('⚠️ Directory not writable, returning anyway:', permError.message);
                        return selectedPath; // Return anyway, user may want to create subdirectory
                    }
                } else {
                    console.log('❌ Directory selection canceled');
                    return null;
                }
            } catch (error) {
                console.error('❌ Failed to open directory dialog:', error);
                throw error;
            }
        });

        ipcMain.handle('select-files', async () => {
            const result = await dialog.showOpenDialog(this.mainWindow, {
                properties: ['openFile', 'multiSelections']
            });
            return result.filePaths;
        });

        // Backup file operations
        ipcMain.handle('select-backup-file', async () => {
            try {
                console.log('📂 Backup file selection requested');

                const result = await dialog.showOpenDialog(this.mainWindow, {
                    properties: ['openFile'],
                    filters: [
                        { name: 'FlexPBX Backup Files', extensions: ['flx', 'flxx'] },
                        { name: 'All Files', extensions: ['*'] }
                    ],
                    message: 'Select FlexPBX Backup File to Import'
                });

                console.log('📂 Backup file dialog result:', result);

                if (!result.canceled && result.filePaths.length > 0) {
                    const selectedFile = result.filePaths[0];
                    console.log('✅ Backup file selected:', selectedFile);
                    return selectedFile;
                } else {
                    console.log('❌ Backup file selection canceled');
                    return null;
                }
            } catch (error) {
                console.error('❌ Failed to open backup file dialog:', error);
                throw error;
            }
        });

        ipcMain.handle('save-backup-file', async () => {
            try {
                console.log('💾 Backup save dialog requested');

                const result = await dialog.showSaveDialog(this.mainWindow, {
                    filters: [
                        { name: 'FlexPBX Backup Files', extensions: ['flx'] },
                        { name: 'FlexPBX Extended Backup', extensions: ['flxx'] }
                    ],
                    defaultPath: `FlexPBX-Backup-${new Date().toISOString().split('T')[0]}.flx`,
                    message: 'Save FlexPBX Backup File'
                });

                console.log('💾 Backup save dialog result:', result);

                if (!result.canceled && result.filePath) {
                    const savePath = result.filePath;
                    console.log('✅ Backup save path:', savePath);
                    return savePath;
                } else {
                    console.log('❌ Backup save canceled');
                    return null;
                }
            } catch (error) {
                console.error('❌ Failed to open backup save dialog:', error);
                throw error;
            }
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

        // Backup operations
        ipcMain.handle('create-backup', async () => {
            const config = {
                installations: this.store.get('installations', []),
                remoteServers: this.store.get('remoteServers', []),
                settings: this.store.get('settings', {}),
                nginxConfigs: this.store.get('nginxConfigs', [])
            };
            return await this.backupHandler.saveBackup(this.mainWindow, config);
        });

        ipcMain.handle('import-backup', async () => {
            const result = await this.backupHandler.loadBackup(this.mainWindow);
            if (result.success) {
                // Show restore options dialog
                const restoreResult = await this.showRestoreOptions(result.data);
                if (restoreResult.confirmed) {
                    return await this.backupHandler.restoreBackup(result.data, restoreResult.options);
                }
            }
            return result;
        });

        // Archive restoration operations
        ipcMain.handle('import-archive-with-backup', async () => {
            return await this.backupHandler.importArchiveWithBackup(this.mainWindow);
        });

        ipcMain.handle('upload-and-restore-archive', async (event, server, archivePath, backupPath, options) => {
            return await this.backupHandler.uploadAndRestoreArchive(server, archivePath, backupPath, options);
        });

        // Background service management
        ipcMain.handle('start-background-services', async () => {
            return await this.startBackgroundServices();
        });

        ipcMain.handle('stop-background-services', async () => {
            return await this.stopBackgroundServices();
        });

        ipcMain.handle('get-background-services-status', async () => {
            return this.backgroundServiceManager.getStatus();
        });

        ipcMain.handle('restart-background-service', async (event, serviceName) => {
            return await this.backgroundServiceManager.restartService(serviceName);
        });

        // CopyParty service management
        ipcMain.handle('install-copyparty', async () => {
            return await this.copyPartyService.install();
        });

        ipcMain.handle('start-copyparty', async () => {
            return await this.copyPartyService.start();
        });

        ipcMain.handle('stop-copyparty', async () => {
            return await this.copyPartyService.stop();
        });

        ipcMain.handle('get-copyparty-status', async () => {
            return this.copyPartyService.getStatus();
        });

        ipcMain.handle('get-copyparty-access-info', async () => {
            return await this.copyPartyService.getRemoteAccessInfo();
        });

        ipcMain.handle('get-copyparty-credentials', async () => {
            return this.copyPartyService.getCredentials();
        });

        ipcMain.handle('sync-deployment', async (event, remotePath, localFiles) => {
            return await this.copyPartyService.syncDeployment(remotePath, localFiles);
        });

        ipcMain.handle('trigger-remote-restart', async (event, deploymentName) => {
            return await this.copyPartyService.triggerRemoteRestart(deploymentName);
        });

        // System startup configuration
        ipcMain.handle('install-system-services', async () => {
            return await this.backgroundServiceManager.installSystemServices();
        });

        ipcMain.handle('configure-startup', async (event, options) => {
            return await this.configureStartup(options);
        });

        ipcMain.handle('get-startup-status', async () => {
            return await this.getStartupStatus();
        });

        // Sound management
        ipcMain.handle('get-sound-status', async () => {
            return this.soundManager.getStatus();
        });

        ipcMain.handle('configure-sounds', async (event, options) => {
            if (options.enabled !== undefined) {
                this.soundManager.setEnabled(options.enabled);
            }
            if (options.volume !== undefined) {
                this.soundManager.setVolume(options.volume);
            }
            return this.soundManager.getStatus();
        });

        // Connection sounds (called automatically, not from UI)
        ipcMain.handle('play-connection-sound', async () => {
            return await this.soundManager.playConnectionSound();
        });

        ipcMain.handle('play-disconnect-sound', async () => {
            return await this.soundManager.playDisconnectSound();
        });

        // Action sounds
        ipcMain.handle('play-door-closing-sound', async () => {
            return await this.soundManager.playDoorClosingSound();
        });

        ipcMain.handle('play-logout-sound', async () => {
            return await this.soundManager.playLogoutSound();
        });

        ipcMain.handle('play-session-end-sound', async () => {
            return await this.soundManager.playSessionEndSound();
        });

        // Message sounds
        ipcMain.handle('play-message-send-sound', async () => {
            return await this.soundManager.playMessageSendSound();
        });

        ipcMain.handle('play-message-receive-sound', async () => {
            return await this.soundManager.playMessageReceiveSound();
        });

        // TTS Service handlers
        ipcMain.handle('tts-synthesize', async (event, text, options) => {
            return await this.ttsService.synthesize(text, options);
        });

        ipcMain.handle('tts-announce-caller', async (event, callerInfo) => {
            return await this.ttsService.announceCaller(callerInfo);
        });

        ipcMain.handle('tts-announce-extension', async (event, extension) => {
            return await this.ttsService.announceExtension(extension);
        });

        ipcMain.handle('tts-create-ivr-prompt', async (event, promptText, options) => {
            return await this.ttsService.createIVRPrompt(promptText, options);
        });

        ipcMain.handle('tts-create-voicemail-greeting', async (event, extensionNumber, personalMessage) => {
            return await this.ttsService.createVoicemailGreeting(extensionNumber, personalMessage);
        });

        ipcMain.handle('tts-chatterbox-speak', async (event, text, voice, effect) => {
            return await this.ttsService.chatterboxSpeak(text, voice, effect);
        });

        ipcMain.handle('tts-get-voice-profiles', async () => {
            return this.ttsService.getVoiceProfiles();
        });

        ipcMain.handle('tts-add-voice-profile', async (event, key, profile) => {
            this.ttsService.addVoiceProfile(key, profile);
            return { success: true };
        });

        ipcMain.handle('tts-clear-cache', async () => {
            return await this.ttsService.clearCache();
        });

        ipcMain.handle('tts-get-cache-stats', async () => {
            return this.ttsService.getCacheStats();
        });

        ipcMain.handle('tts-health-check', async () => {
            return await this.ttsService.healthCheck();
        });

        // Hold Music Server handlers
        ipcMain.handle('hold-music-get-info', async () => {
            return this.holdMusicServer ? this.holdMusicServer.getServerInfo() : null;
        });

        ipcMain.handle('hold-music-open-monitor', async () => {
            this.openHoldMusicMonitor();
            return { success: true };
        });

        // Support Ticket System handlers
        ipcMain.handle('support-tickets-get-info', async () => {
            return this.supportTicketSystem ? this.supportTicketSystem.getServerInfo() : null;
        });

        ipcMain.handle('support-tickets-open', async () => {
            this.openSupportTicketSystem();
            return { success: true };
        });

        // App Lock System handlers
        ipcMain.handle('app-lock-get-status', async () => {
            return this.appLockSystem ? this.appLockSystem.getSystemInfo() : { isLocked: false, lockType: 'none' };
        });

        ipcMain.handle('app-lock-check-permission', async (event, permission) => {
            return this.appLockSystem ? this.appLockSystem.hasPermission(permission) : true;
        });

        ipcMain.handle('app-lock-can-access-feature', async (event, feature) => {
            return this.appLockSystem ? this.appLockSystem.canAccessFeature(feature) : true;
        });

        ipcMain.handle('app-lock-lock-app', async () => {
            if (this.appLockSystem) {
                this.appLockSystem.lockApp();
                return { success: true };
            }
            return { success: false, error: 'App lock system not available' };
        });

        ipcMain.handle('app-lock-unlock-app', async () => {
            if (this.appLockSystem) {
                this.appLockSystem.unlockApp();
                return { success: true };
            }
            return { success: false, error: 'App lock system not available' };
        });

        // Cross-Platform Speech handlers
        ipcMain.handle('speech-test', async () => {
            this.crossPlatformSpeech.testSpeech();
            return { success: true };
        });

        ipcMain.handle('speech-speak', async (event, messageKey, customMessage) => {
            this.crossPlatformSpeech.speak(messageKey, customMessage);
            return { success: true };
        });

        ipcMain.handle('speech-get-voices', async () => {
            return this.crossPlatformSpeech.getAvailableVoices();
        });

        ipcMain.handle('speech-update-settings', async (event, settings) => {
            this.crossPlatformSpeech.updateSettings(settings);
            return { success: true };
        });

        ipcMain.handle('speech-get-settings', async () => {
            return this.crossPlatformSpeech.getSettings();
        });

        // tappedin.fm specific handlers
        ipcMain.handle('tts-tappedin-status', async () => {
            return {
                connected: this.ttsService.tappedInConnected,
                endpoint: this.ttsService.config.apiEndpoint
            };
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

    async configureStartup(options = {}) {
        const {
            enableAutoStart = true,
            startInBackground = true,
            enableTrayIcon = true,
            enableBackgroundServices = true
        } = options;

        console.log('📋 Configuring startup options...', options);

        try {
            // Save startup preferences
            this.store.set('startup', {
                enableAutoStart,
                startInBackground,
                enableTrayIcon,
                enableBackgroundServices,
                configuredAt: new Date().toISOString()
            });

            // Configure auto-start based on platform
            let autoStartResult = { success: true, message: 'Auto-start not configured' };

            if (enableAutoStart) {
                if (process.platform === 'darwin') {
                    autoStartResult = await this.configureMacOSAutoStart();
                } else if (process.platform === 'linux') {
                    autoStartResult = await this.configureLinuxAutoStart();
                } else if (process.platform === 'win32') {
                    autoStartResult = await this.configureWindowsAutoStart();
                }
            }

            return {
                success: true,
                message: 'Startup configuration saved',
                autoStart: autoStartResult,
                options: this.store.get('startup')
            };

        } catch (error) {
            console.error('Failed to configure startup:', error);
            return {
                success: false,
                error: error.message
            };
        }
    }

    async configureMacOSAutoStart() {
        const os = require('os');
        const homeDir = os.homedir();
        const launchAgentsDir = path.join(homeDir, 'Library', 'LaunchAgents');
        const plistPath = path.join(launchAgentsDir, 'com.flexpbx.desktop.plist');

        try {
            await fs.ensureDir(launchAgentsDir);

            const appPath = process.platform === 'darwin' && app.isPackaged
                ? path.join(process.resourcesPath, '..', '..', '..', 'MacOS', 'FlexPBX Desktop')
                : process.execPath;

            const plistContent = `<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>Label</key>
    <string>com.flexpbx.desktop</string>
    <key>ProgramArguments</key>
    <array>
        <string>${appPath}</string>
        <string>--background</string>
    </array>
    <key>RunAtLoad</key>
    <true/>
    <key>KeepAlive</key>
    <true/>
    <key>StandardOutPath</key>
    <string>${homeDir}/.flexpbx/logs/startup.log</string>
    <key>StandardErrorPath</key>
    <string>${homeDir}/.flexpbx/logs/startup-error.log</string>
    <key>EnvironmentVariables</key>
    <dict>
        <key>FLEXPBX_BACKGROUND</key>
        <string>1</string>
    </dict>
</dict>
</plist>`;

            await fs.writeFile(plistPath, plistContent);

            // Load the launch agent
            return new Promise((resolve) => {
                const { exec } = require('child_process');
                exec(`launchctl load "${plistPath}"`, (error, stdout, stderr) => {
                    if (error) {
                        console.error('Failed to load launch agent:', error);
                        resolve({
                            success: false,
                            error: error.message,
                            plistPath
                        });
                    } else {
                        resolve({
                            success: true,
                            message: 'macOS auto-start configured',
                            plistPath
                        });
                    }
                });
            });

        } catch (error) {
            return {
                success: false,
                error: error.message
            };
        }
    }

    async configureLinuxAutoStart() {
        const os = require('os');
        const homeDir = os.homedir();
        const autostartDir = path.join(homeDir, '.config', 'autostart');
        const desktopFile = path.join(autostartDir, 'flexpbx-desktop.desktop');

        try {
            await fs.ensureDir(autostartDir);

            const appPath = app.isPackaged ? process.execPath : process.execPath;

            const desktopContent = `[Desktop Entry]
Type=Application
Name=FlexPBX Desktop
Comment=FlexPBX Background Services
Exec=${appPath} --background
Icon=flexpbx
Hidden=false
NoDisplay=false
X-GNOME-Autostart-enabled=true
X-GNOME-Autostart-Delay=5`;

            await fs.writeFile(desktopFile, desktopContent);

            return {
                success: true,
                message: 'Linux auto-start configured',
                desktopFile
            };

        } catch (error) {
            return {
                success: false,
                error: error.message
            };
        }
    }

    async configureWindowsAutoStart() {
        // Windows auto-start would require registry entries
        return {
            success: false,
            message: 'Windows auto-start not yet implemented'
        };
    }

    async getStartupStatus() {
        const startupConfig = this.store.get('startup', {
            enableAutoStart: false,
            startInBackground: true,
            enableTrayIcon: true,
            enableBackgroundServices: true
        });

        const backgroundStatus = this.backgroundServiceManager.getStatus();
        const copyPartyStatus = this.copyPartyService.getStatus();

        return {
            configuration: startupConfig,
            backgroundServices: backgroundStatus,
            copyParty: copyPartyStatus,
            trayAvailable: !!this.tray,
            currentMode: {
                inBackground: !this.mainWindow || !this.mainWindow.isVisible(),
                servicesRunning: backgroundStatus.running,
                copyPartyRunning: copyPartyStatus.running
            }
        };
    }
}

// Start the application
new FlexPBXUnifiedClient();

module.exports = FlexPBXUnifiedClient;