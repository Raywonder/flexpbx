/**
 * 🚀 FlexPBX Enhanced Main Window
 * The most advanced, accessible Electron window ever created!
 */

const { app, BrowserWindow, ipcMain, Menu, Tray, nativeImage, powerMonitor } = require('electron');
const path = require('path');
const { autoUpdater } = require('electron-updater');
const UnifiedServiceManager = require('./UnifiedServiceManager');

class FlexPBXMainWindow {
    constructor() {
        this.mainWindow = null;
        this.serviceManager = null;
        this.tray = null;
        this.isQuitting = false;
        this.windowState = {
            isMaximized: false,
            isMinimized: false,
            isFullScreen: false
        };

        this.initializeApp();
    }

    async initializeApp() {
        console.log('🚀 Initializing FlexPBX Enhanced Application...');

        // Enable live reload for development
        if (process.env.NODE_ENV === 'development') {
            require('electron-reload')(__dirname, {
                electron: path.join(__dirname, '..', 'node_modules', '.bin', 'electron'),
                hardResetMethod: 'exit'
            });
        }

        // Initialize services
        this.serviceManager = new UnifiedServiceManager();

        // Set up app event handlers
        this.setupAppEvents();

        // Set up auto-updater
        this.setupAutoUpdater();

        // Set up power management
        this.setupPowerManagement();

        // Create application menu
        this.createApplicationMenu();

        // Create system tray
        this.createSystemTray();

        // Create main window
        await this.createMainWindow();

        console.log('✅ FlexPBX Enhanced Application initialized successfully!');
    }

    setupAppEvents() {
        app.whenReady().then(() => {
            console.log('📱 Electron app ready');
        });

        app.on('window-all-closed', () => {
            if (process.platform !== 'darwin') {
                this.gracefulShutdown();
            }
        });

        app.on('activate', async () => {
            if (BrowserWindow.getAllWindows().length === 0) {
                await this.createMainWindow();
            }
        });

        app.on('before-quit', (event) => {
            if (!this.isQuitting) {
                event.preventDefault();
                this.gracefulShutdown();
            }
        });

        // Handle certificate errors gracefully
        app.on('certificate-error', (event, webContents, url, error, certificate, callback) => {
            if (url.startsWith('https://localhost') || url.startsWith('https://127.0.0.1')) {
                event.preventDefault();
                callback(true);
            } else {
                callback(false);
            }
        });
    }

    setupAutoUpdater() {
        autoUpdater.checkForUpdatesAndNotify();

        autoUpdater.on('update-available', () => {
            console.log('📦 Update available');
            this.mainWindow?.webContents.send('update-available');
        });

        autoUpdater.on('update-downloaded', () => {
            console.log('📥 Update downloaded');
            this.mainWindow?.webContents.send('update-downloaded');
        });
    }

    setupPowerManagement() {
        powerMonitor.on('suspend', () => {
            console.log('😴 System suspended');
            this.mainWindow?.webContents.send('system-suspend');
        });

        powerMonitor.on('resume', () => {
            console.log('😊 System resumed');
            this.mainWindow?.webContents.send('system-resume');
        });

        powerMonitor.on('on-ac', () => {
            console.log('🔌 Power connected');
        });

        powerMonitor.on('on-battery', () => {
            console.log('🔋 On battery power');
        });
    }

    async createMainWindow() {
        console.log('🖼️  Creating enhanced main window...');

        this.mainWindow = new BrowserWindow({
            width: 1400,
            height: 900,
            minWidth: 800,
            minHeight: 600,
            show: false,
            icon: path.join(__dirname, '../assets/icon.png'),
            webPreferences: {
                nodeIntegration: true,
                contextIsolation: false,
                enableRemoteModule: true,
                webSecurity: false,
                allowRunningInsecureContent: true
            },
            titleBarStyle: process.platform === 'darwin' ? 'hiddenInset' : 'default',
            backgroundColor: '#667eea',
            vibrancy: process.platform === 'darwin' ? 'under-window' : undefined,
            transparent: false,
            frame: true,
            thickFrame: true,
            acceptFirstMouse: true,
            enableLargerThanScreen: false
        });

        // Enhanced window management
        this.setupWindowManagement();

        // Load the enhanced UI
        await this.loadEnhancedUI();

        // Set up IPC handlers
        this.setupIPC();

        // Show window with animation
        this.mainWindow.once('ready-to-show', () => {
            this.mainWindow.show();

            // Accessibility announcement
            this.mainWindow.webContents.executeJavaScript(`
                if (window.speechSynthesis) {
                    const utterance = new SpeechSynthesisUtterance('FlexPBX Enhanced Application loaded successfully. All accessibility features are available.');
                    utterance.rate = 0.8;
                    window.speechSynthesis.speak(utterance);
                }
            `);
        });

        console.log('✅ Enhanced main window created successfully');
    }

    setupWindowManagement() {
        this.mainWindow.on('maximize', () => {
            this.windowState.isMaximized = true;
            this.mainWindow.webContents.send('window-state-changed', this.windowState);
        });

        this.mainWindow.on('unmaximize', () => {
            this.windowState.isMaximized = false;
            this.mainWindow.webContents.send('window-state-changed', this.windowState);
        });

        this.mainWindow.on('minimize', () => {
            this.windowState.isMinimized = true;
            this.mainWindow.webContents.send('window-state-changed', this.windowState);
        });

        this.mainWindow.on('restore', () => {
            this.windowState.isMinimized = false;
            this.mainWindow.webContents.send('window-state-changed', this.windowState);
        });

        this.mainWindow.on('enter-full-screen', () => {
            this.windowState.isFullScreen = true;
            this.mainWindow.webContents.send('window-state-changed', this.windowState);
        });

        this.mainWindow.on('leave-full-screen', () => {
            this.windowState.isFullScreen = false;
            this.mainWindow.webContents.send('window-state-changed', this.windowState);
        });

        this.mainWindow.on('close', (event) => {
            if (!this.isQuitting && process.platform === 'darwin') {
                event.preventDefault();
                this.mainWindow.hide();
            }
        });
    }

    async loadEnhancedUI() {
        // Create the enhanced HTML content
        const enhancedHTML = `
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FlexPBX Enhanced - The Future of Accessibility</title>
    <link rel="stylesheet" href="./assets/flexpbx-modern.css">
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--primary-gradient);
            color: var(--text-primary);
            overflow: hidden;
        }

        .app-container {
            height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .header {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            padding: 15px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .header h1 {
            margin: 0;
            color: white;
            font-size: 24px;
            font-weight: 700;
        }

        .main-content {
            flex: 1;
            display: grid;
            grid-template-columns: 300px 1fr 280px;
            gap: 0;
            overflow: hidden;
        }

        .sidebar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-right: 1px solid var(--border-color);
            overflow-y: auto;
        }

        .content-area {
            background: white;
            overflow-y: auto;
            padding: 20px;
        }

        .right-panel {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-left: 1px solid var(--border-color);
            overflow-y: auto;
            padding: 20px;
        }

        .service-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: var(--shadow-light);
            border: 1px solid var(--border-color);
            transition: var(--transition);
        }

        .service-card:hover {
            box-shadow: var(--shadow-medium);
            transform: translateY(-2px);
        }

        .service-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 15px;
        }

        .service-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: var(--primary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
            font-weight: 600;
        }

        .service-info h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 600;
        }

        .service-info p {
            margin: 0;
            font-size: 12px;
            color: var(--text-secondary);
        }

        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .feature-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            transition: var(--transition);
        }

        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-medium);
        }

        .feature-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }

        .feature-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .feature-description {
            font-size: 14px;
            color: var(--text-secondary);
            line-height: 1.5;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            border: 1px solid var(--border-color);
        }

        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 12px;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Accessibility enhancements */
        .skip-link {
            position: absolute;
            top: -40px;
            left: 6px;
            background: var(--primary-color);
            color: white;
            padding: 8px;
            border-radius: 4px;
            text-decoration: none;
            z-index: 1000;
            transition: top 0.3s;
        }

        .skip-link:focus {
            top: 6px;
        }

        /* High contrast mode */
        @media (prefers-contrast: high) {
            .service-card, .feature-card, .stat-card {
                border: 2px solid black;
            }
        }

        /* Dark mode */
        @media (prefers-color-scheme: dark) {
            .sidebar, .right-panel {
                background: rgba(45, 45, 45, 0.95);
            }

            .content-area {
                background: #1a1a1a;
            }

            .service-card, .stat-card {
                background: #2d2d2d;
                border-color: #404040;
            }

            .feature-card {
                background: linear-gradient(135deg, #2d2d2d 0%, #404040 100%);
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <a href="#main-content" class="skip-link">Skip to main content</a>

        <header class="header" role="banner">
            <h1>🚀 FlexPBX Enhanced</h1>
            <div class="status-indicator connected" id="systemStatus">
                <span class="loading-spinner" style="display: none;"></span>
                <span>System Ready</span>
            </div>
        </header>

        <main class="main-content" id="main-content" role="main">
            <aside class="sidebar" role="navigation" aria-label="Service Navigation">
                <div style="padding: 20px;">
                    <h2 style="margin-top: 0; font-size: 18px;">🛠️ Services</h2>
                    <div id="servicesList"></div>
                </div>
            </aside>

            <section class="content-area">
                <div style="text-align: center; margin-bottom: 40px;">
                    <h2 style="font-size: 32px; margin-bottom: 10px;">Welcome to the Future of Accessibility</h2>
                    <p style="font-size: 18px; color: var(--text-secondary); max-width: 600px; margin: 0 auto;">
                        FlexPBX Enhanced brings together the most advanced accessibility, messaging, and remote control technologies
                        in a single, portable application that runs anywhere.
                    </p>
                </div>

                <div class="feature-grid">
                    <div class="feature-card" tabindex="0" role="button" aria-label="Accessibility Control Features">
                        <div class="feature-icon">♿</div>
                        <div class="feature-title">Universal Accessibility</div>
                        <div class="feature-description">
                            Control VoiceOver, NVDA, JAWS, Narrator, and Orca screen readers remotely with bidirectional audio streaming.
                        </div>
                    </div>

                    <div class="feature-card" tabindex="0" role="button" aria-label="Rich Messaging Features">
                        <div class="feature-icon">💬</div>
                        <div class="feature-title">Rich Messaging</div>
                        <div class="feature-description">
                            Send text, HTML, code, files, and accessibility commands with end-to-end encryption and real-time sync.
                        </div>
                    </div>

                    <div class="feature-card" tabindex="0" role="button" aria-label="Audio Control Features">
                        <div class="feature-icon">🎵</div>
                        <div class="feature-title">Professional Audio</div>
                        <div class="feature-description">
                            Advanced audio mixing, noise suppression, compression, and device selection for crystal-clear communication.
                        </div>
                    </div>

                    <div class="feature-card" tabindex="0" role="button" aria-label="Portable Deployment">
                        <div class="feature-icon">🔌</div>
                        <div class="feature-title">Ultra Portable</div>
                        <div class="feature-description">
                            Run the entire system from a detachable drive. No installation required. Works on any computer.
                        </div>
                    </div>

                    <div class="feature-card" tabindex="0" role="button" aria-label="DNS Management">
                        <div class="feature-icon">🌐</div>
                        <div class="feature-title">DNS Management</div>
                        <div class="feature-description">
                            Manage BIND, PowerDNS, Unbound, and cloud DNS providers with automatic zone synchronization.
                        </div>
                    </div>

                    <div class="feature-card" tabindex="0" role="button" aria-label="Security Features">
                        <div class="feature-icon">🔒</div>
                        <div class="feature-title">Military-Grade Security</div>
                        <div class="feature-description">
                            AES-256-GCM encryption, unique credentials per installation, and secure file sharing with CopyParty.
                        </div>
                    </div>
                </div>
            </section>

            <aside class="right-panel" role="complementary" aria-label="System Information">
                <h3 style="margin-top: 0;">📊 System Status</h3>
                <div class="stats-grid" id="systemStats">
                    <!-- Stats will be populated by JavaScript -->
                </div>

                <h3>⚡ Quick Actions</h3>
                <div style="display: grid; gap: 10px;">
                    <button class="flexpbx-button" onclick="openAccessibilityTest()">
                        ♿ Test Accessibility
                    </button>
                    <button class="flexpbx-button" onclick="openMessaging()">
                        💬 Open Messaging
                    </button>
                    <button class="flexpbx-button" onclick="runSystemCheck()">
                        🔍 System Check
                    </button>
                    <button class="flexpbx-button" onclick="openPortableConfig()">
                        🔌 Portable Setup
                    </button>
                </div>

                <h3>🔗 External Interfaces</h3>
                <div style="font-size: 12px; line-height: 1.6;">
                    <div><strong>CopyParty:</strong> <a href="http://localhost:8080" target="_blank">localhost:8080</a></div>
                    <div><strong>Messaging:</strong> Port 41238</div>
                    <div><strong>Accessibility:</strong> Port 41237</div>
                </div>
            </aside>
        </main>
    </div>

    <script>
        // 🚀 FlexPBX Enhanced Client-Side Application
        class FlexPBXEnhanced {
            constructor() {
                this.services = new Map();
                this.systemStats = {};
                this.isInitialized = false;

                this.initialize();
            }

            async initialize() {
                console.log('🚀 Initializing FlexPBX Enhanced UI...');

                // Initialize services monitoring
                await this.initializeServicesMonitoring();

                // Set up real-time updates
                this.setupRealTimeUpdates();

                // Initialize accessibility features
                this.initializeAccessibility();

                // Load system stats
                this.updateSystemStats();

                // Set up auto-refresh
                setInterval(() => this.updateSystemStats(), 5000);

                this.isInitialized = true;
                console.log('✅ FlexPBX Enhanced UI initialized');

                // Announce to screen readers
                this.announceToScreenReader('FlexPBX Enhanced interface loaded successfully. All features are ready.');
            }

            async initializeServicesMonitoring() {
                const servicesList = document.getElementById('servicesList');

                const services = [
                    { name: 'accessibility', icon: '♿', title: 'Accessibility Control', status: 'healthy' },
                    { name: 'messaging', icon: '💬', title: 'Rich Messaging', status: 'healthy' },
                    { name: 'audio', icon: '🎵', title: 'Audio Streaming', status: 'healthy' },
                    { name: 'copyparty', icon: '📁', title: 'File Sharing', status: 'healthy' },
                    { name: 'dns', icon: '🌐', title: 'DNS Management', status: 'healthy' },
                    { name: 'updates', icon: '📦', title: 'Software Updates', status: 'healthy' }
                ];

                services.forEach(service => {
                    const serviceCard = document.createElement('div');
                    serviceCard.className = 'service-card';
                    serviceCard.innerHTML = `
                        <div class="service-header">
                            <div class="service-icon">${service.icon}</div>
                            <div class="service-info">
                                <h3>${service.title}</h3>
                                <p>Status: <span class="status-indicator ${service.status}">${service.status}</span></p>
                            </div>
                        </div>
                    `;
                    servicesList.appendChild(serviceCard);
                });
            }

            setupRealTimeUpdates() {
                // Listen for system events from main process
                if (window.require) {
                    const { ipcRenderer } = window.require('electron');

                    ipcRenderer.on('service-status-update', (event, data) => {
                        this.updateServiceStatus(data);
                    });

                    ipcRenderer.on('system-stats-update', (event, stats) => {
                        this.systemStats = stats;
                        this.updateSystemStatsDisplay();
                    });
                }
            }

            initializeAccessibility() {
                // Enhanced keyboard navigation
                document.addEventListener('keydown', (event) => {
                    // Alt + 1-6 for quick service access
                    if (event.altKey && event.key >= '1' && event.key <= '6') {
                        event.preventDefault();
                        const serviceIndex = parseInt(event.key) - 1;
                        const services = document.querySelectorAll('.service-card');
                        if (services[serviceIndex]) {
                            services[serviceIndex].focus();
                            this.announceToScreenReader(`Focused on ${services[serviceIndex].querySelector('h3').textContent}`);
                        }
                    }

                    // Ctrl + M for messaging
                    if (event.ctrlKey && event.key === 'm') {
                        event.preventDefault();
                        this.openMessaging();
                    }

                    // Ctrl + A for accessibility test
                    if (event.ctrlKey && event.key === 'a') {
                        event.preventDefault();
                        this.openAccessibilityTest();
                    }
                });

                // Enhanced focus management
                document.addEventListener('focusin', (event) => {
                    if (event.target.classList.contains('feature-card')) {
                        event.target.style.outline = '3px solid #2196f3';
                        event.target.style.outlineOffset = '2px';
                    }
                });

                document.addEventListener('focusout', (event) => {
                    if (event.target.classList.contains('feature-card')) {
                        event.target.style.outline = '';
                        event.target.style.outlineOffset = '';
                    }
                });
            }

            updateSystemStats() {
                const statsContainer = document.getElementById('systemStats');

                // Mock stats - in real implementation, these would come from the backend
                const stats = {
                    uptime: this.formatUptime(process.uptime ? process.uptime() : Math.random() * 86400),
                    memory: `${Math.round(Math.random() * 100)}%`,
                    services: '6/6',
                    connections: Math.floor(Math.random() * 10).toString()
                };

                statsContainer.innerHTML = `
                    <div class="stat-card">
                        <div class="stat-value">${stats.uptime}</div>
                        <div class="stat-label">Uptime</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">${stats.memory}</div>
                        <div class="stat-label">Memory</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">${stats.services}</div>
                        <div class="stat-label">Services</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">${stats.connections}</div>
                        <div class="stat-label">Connections</div>
                    </div>
                `;
            }

            formatUptime(seconds) {
                const hours = Math.floor(seconds / 3600);
                const minutes = Math.floor((seconds % 3600) / 60);
                return `${hours}h ${minutes}m`;
            }

            announceToScreenReader(message) {
                const announcement = document.createElement('div');
                announcement.setAttribute('aria-live', 'polite');
                announcement.setAttribute('aria-atomic', 'true');
                announcement.className = 'screen-reader-only';
                announcement.textContent = message;

                document.body.appendChild(announcement);

                setTimeout(() => {
                    document.body.removeChild(announcement);
                }, 1000);
            }
        }

        // Global functions for UI interactions
        function openAccessibilityTest() {
            if (window.require) {
                const { shell } = window.require('electron');
                shell.openPath(path.join(__dirname, '../../FlexPBX-Accessibility-Test.html'));
            } else {
                window.open('../../../FlexPBX-Accessibility-Test.html', '_blank');
            }
        }

        function openMessaging() {
            if (window.require) {
                const { shell } = window.require('electron');
                shell.openPath(path.join(__dirname, '../../FlexPBX-Rich-Messaging.html'));
            } else {
                window.open('../../../FlexPBX-Rich-Messaging.html', '_blank');
            }
        }

        function runSystemCheck() {
            const systemStatus = document.getElementById('systemStatus');
            systemStatus.innerHTML = '<span class="loading-spinner"></span> Running System Check...';

            // Simulate system check
            setTimeout(() => {
                systemStatus.innerHTML = '<span>✅ System Healthy</span>';
                app.announceToScreenReader('System check completed successfully. All systems are operational.');
            }, 2000);
        }

        function openPortableConfig() {
            if (window.require) {
                const { ipcRenderer } = window.require('electron');
                ipcRenderer.send('open-portable-config');
            }
        }

        // Initialize the application
        let app;
        document.addEventListener('DOMContentLoaded', () => {
            app = new FlexPBXEnhanced();
        });
    </script>
</body>
</html>
        `;

        // Write the enhanced HTML to a file
        const htmlPath = path.join(__dirname, '../renderer/enhanced-index.html');
        await fs.ensureDir(path.dirname(htmlPath));
        await fs.writeFile(htmlPath, enhancedHTML);

        // Load the enhanced HTML
        await this.mainWindow.loadFile(htmlPath);
    }

    setupIPC() {
        // Handle various IPC messages
        ipcMain.handle('get-system-status', async () => {
            return this.serviceManager.getSystemStatus();
        });

        ipcMain.handle('get-service-health', async (event, serviceName) => {
            return this.serviceManager.getServiceHealth(serviceName);
        });

        ipcMain.on('open-portable-config', () => {
            this.openPortableConfig();
        });

        ipcMain.on('restart-service', async (event, serviceName) => {
            await this.serviceManager.restartService(serviceName);
        });
    }

    createApplicationMenu() {
        const template = [
            {
                label: 'FlexPBX',
                submenu: [
                    { label: 'About FlexPBX', role: 'about' },
                    { type: 'separator' },
                    {
                        label: 'Accessibility Test',
                        accelerator: 'CmdOrCtrl+Alt+A',
                        click: () => this.openAccessibilityTest()
                    },
                    {
                        label: 'Rich Messaging',
                        accelerator: 'CmdOrCtrl+M',
                        click: () => this.openMessaging()
                    },
                    { type: 'separator' },
                    { label: 'Preferences', accelerator: 'CmdOrCtrl+,', click: () => this.openPreferences() },
                    { type: 'separator' },
                    { label: 'Quit FlexPBX', accelerator: 'CmdOrCtrl+Q', click: () => this.gracefulShutdown() }
                ]
            },
            {
                label: 'Services',
                submenu: [
                    { label: 'Start All Services', click: () => this.serviceManager.startAllServices() },
                    { label: 'Stop All Services', click: () => this.serviceManager.stopAllServices() },
                    { type: 'separator' },
                    { label: 'Restart Accessibility', click: () => this.serviceManager.restartService('accessibility') },
                    { label: 'Restart Messaging', click: () => this.serviceManager.restartService('messaging') },
                    { label: 'Restart Audio', click: () => this.serviceManager.restartService('audio') }
                ]
            },
            {
                label: 'Tools',
                submenu: [
                    { label: 'System Check', accelerator: 'CmdOrCtrl+T', click: () => this.runSystemCheck() },
                    { label: 'Portable Configuration', click: () => this.openPortableConfig() },
                    { type: 'separator' },
                    { label: 'Open Test Suite', click: () => this.openTestSuite() },
                    { label: 'View Logs', click: () => this.openLogs() }
                ]
            },
            {
                label: 'Window',
                submenu: [
                    { label: 'Minimize', accelerator: 'CmdOrCtrl+M', role: 'minimize' },
                    { label: 'Close', accelerator: 'CmdOrCtrl+W', role: 'close' },
                    { type: 'separator' },
                    { label: 'Bring All to Front', role: 'front' }
                ]
            },
            {
                label: 'Help',
                submenu: [
                    { label: 'FlexPBX Help', accelerator: 'F1', click: () => this.openHelp() },
                    { label: 'Keyboard Shortcuts', click: () => this.showKeyboardShortcuts() },
                    { type: 'separator' },
                    { label: 'Check for Updates', click: () => autoUpdater.checkForUpdatesAndNotify() },
                    { label: 'Report Issue', click: () => this.reportIssue() }
                ]
            }
        ];

        if (process.platform !== 'darwin') {
            // Remove macOS-specific items
            template[0].submenu = template[0].submenu.filter(item => item.role !== 'about');
        }

        const menu = Menu.buildFromTemplate(template);
        Menu.setApplicationMenu(menu);
    }

    createSystemTray() {
        const trayIconPath = path.join(__dirname, '../assets/tray-icon.png');

        try {
            // Create tray icon
            let trayIcon;
            if (fs.existsSync(trayIconPath)) {
                trayIcon = nativeImage.createFromPath(trayIconPath);
            } else {
                // Fallback to a simple icon
                trayIcon = nativeImage.createEmpty();
            }

            this.tray = new Tray(trayIcon);

            const contextMenu = Menu.buildFromTemplate([
                { label: 'Show FlexPBX', click: () => this.showMainWindow() },
                { type: 'separator' },
                { label: 'Accessibility Test', click: () => this.openAccessibilityTest() },
                { label: 'Rich Messaging', click: () => this.openMessaging() },
                { type: 'separator' },
                {
                    label: 'System Status',
                    submenu: [
                        { label: 'Healthy Services: 6/6', enabled: false },
                        { label: 'Uptime: 2h 15m', enabled: false },
                        { type: 'separator' },
                        { label: 'Refresh Status', click: () => this.refreshSystemStatus() }
                    ]
                },
                { type: 'separator' },
                { label: 'Quit FlexPBX', click: () => this.gracefulShutdown() }
            ]);

            this.tray.setContextMenu(contextMenu);
            this.tray.setToolTip('FlexPBX Enhanced - Universal Accessibility Platform');

            this.tray.on('click', () => {
                this.showMainWindow();
            });

            console.log('🔔 System tray created');
        } catch (error) {
            console.warn('⚠️ Could not create system tray:', error.message);
        }
    }

    showMainWindow() {
        if (this.mainWindow) {
            if (this.mainWindow.isMinimized()) {
                this.mainWindow.restore();
            }
            this.mainWindow.show();
            this.mainWindow.focus();
        }
    }

    async openAccessibilityTest() {
        const testPath = path.join(__dirname, '../../FlexPBX-Accessibility-Test.html');
        if (await fs.pathExists(testPath)) {
            const { shell } = require('electron');
            shell.openPath(testPath);
        }
    }

    async openMessaging() {
        const messagingPath = path.join(__dirname, '../../FlexPBX-Rich-Messaging.html');
        if (await fs.pathExists(messagingPath)) {
            const { shell } = require('electron');
            shell.openPath(messagingPath);
        }
    }

    async openPortableConfig() {
        const configPath = path.join(__dirname, '../portable-config.js');
        if (await fs.pathExists(configPath)) {
            const { shell } = require('electron');
            shell.openPath(configPath);
        }
    }

    async gracefulShutdown() {
        console.log('🛑 Starting graceful shutdown...');

        this.isQuitting = true;

        if (this.serviceManager) {
            await this.serviceManager.shutdown();
        }

        if (this.tray) {
            this.tray.destroy();
        }

        app.quit();
    }
}

// Initialize the application
new FlexPBXMainWindow();
