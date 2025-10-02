const { BrowserWindow, shell, session } = require('electron');
const { EventEmitter } = require('events');
const path = require('path');
const url = require('url');

class WebUIService extends EventEmitter {
    constructor() {
        super();
        this.webUIWindows = new Map();
        this.authService = null;
        this.serverConfig = null;
    }

    // Initialize WebUI service
    async initialize(authService, serverConfig) {
        this.authService = authService;
        this.serverConfig = serverConfig;

        // Setup session for WebUI
        await this.setupWebUISession();

        this.emit('initialized');
    }

    // Setup WebUI session with proper security and 2FA support
    async setupWebUISession() {
        const webUISession = session.fromPartition('webui');

        // Configure security settings
        webUISession.setPermissionRequestHandler((webContents, permission, callback) => {
            const allowedPermissions = ['notifications', 'microphone', 'camera'];
            callback(allowedPermissions.includes(permission));
        });

        // Handle certificate errors for self-signed certificates
        webUISession.setCertificateVerifyProc((request, callback) => {
            // Allow self-signed certificates for local servers
            if (request.hostname === 'localhost' || request.hostname === '127.0.0.1') {
                callback(0); // Accept
            } else {
                callback(-2); // Use default verification
            }
        });

        // Inject 2FA authentication scripts
        webUISession.webRequest.onBeforeRequest({ urls: ['*://*/*'] }, (details, callback) => {
            // Allow all requests but track them for authentication
            this.emit('webui-request', { url: details.url, method: details.method });
            callback({});
        });

        // Handle authentication headers for panel integration
        webUISession.webRequest.onBeforeSendHeaders({ urls: ['*://*/*'] }, (details, callback) => {
            const headers = { ...details.requestHeaders };

            // Add authentication headers for control panel requests
            if (this.authService && this.isControlPanelRequest(details.url)) {
                const sessionInfo = this.getSessionForUrl(details.url);
                if (sessionInfo && sessionInfo.token) {
                    headers['Authorization'] = `Bearer ${sessionInfo.token}`;
                    headers['X-FlexPBX-Session'] = sessionInfo.sessionId;
                }
            }

            callback({ requestHeaders: headers });
        });
    }

    // Create WebUI window
    async createWebUIWindow(options = {}) {
        const {
            url: targetUrl,
            title = 'FlexPBX WebUI',
            width = 1200,
            height = 800,
            enableSSO = true,
            panelType = null
        } = options;

        const windowId = `webui-${Date.now()}`;

        // Create window with proper settings for macOS and Windows
        const webUIWindow = new BrowserWindow({
            width,
            height,
            title,
            icon: this.getAppIcon(),
            webPreferences: {
                nodeIntegration: false,
                contextIsolation: true,
                enableRemoteModule: false,
                webSecurity: true,
                partition: 'webui',
                preload: path.join(__dirname, '../preload/webui-preload.js')
            },
            titleBarStyle: process.platform === 'darwin' ? 'hiddenInset' : 'default',
            show: false,
            backgroundColor: '#ffffff'
        });

        // macOS specific settings
        if (process.platform === 'darwin') {
            webUIWindow.setVibrancy('under-window');
        }

        // Windows specific settings
        if (process.platform === 'win32') {
            webUIWindow.setMenuBarVisibility(false);
        }

        // Handle window ready-to-show
        webUIWindow.once('ready-to-show', () => {
            webUIWindow.show();

            // Focus window appropriately by platform
            if (process.platform === 'darwin') {
                webUIWindow.moveTop();
            } else {
                webUIWindow.focus();
            }

            this.emit('webui-window-ready', { windowId, url: targetUrl });
        });

        // Handle window closed
        webUIWindow.on('closed', () => {
            this.webUIWindows.delete(windowId);
            this.emit('webui-window-closed', { windowId });
        });

        // Handle navigation
        webUIWindow.webContents.on('will-navigate', (event, navigationUrl) => {
            if (!this.isAllowedUrl(navigationUrl)) {
                event.preventDefault();
                shell.openExternal(navigationUrl);
            }
        });

        // Handle new window requests
        webUIWindow.webContents.setWindowOpenHandler(({ url }) => {
            if (this.isAllowedUrl(url)) {
                return {
                    action: 'allow',
                    overrideBrowserWindowOptions: {
                        webPreferences: {
                            nodeIntegration: false,
                            contextIsolation: true,
                            partition: 'webui'
                        }
                    }
                };
            } else {
                shell.openExternal(url);
                return { action: 'deny' };
            }
        });

        // Handle authentication challenges
        webUIWindow.webContents.on('login', async (event, authenticationResponseDetails, authInfo, callback) => {
            event.preventDefault();

            if (panelType && this.authService) {
                try {
                    const authResult = await this.authService.authenticateWith2FA(panelType, {
                        skipToken: false
                    });

                    if (authResult.success) {
                        callback(authResult.session.username, ''); // Password handled by 2FA
                    } else {
                        callback('', ''); // Cancel authentication
                    }
                } catch (error) {
                    callback('', ''); // Cancel on error
                }
            } else {
                callback('', ''); // Cancel if no auth service
            }
        });

        // Inject custom CSS and JavaScript for better integration
        webUIWindow.webContents.on('dom-ready', () => {
            this.injectCustomStyles(webUIWindow.webContents);
            this.injectIntegrationScripts(webUIWindow.webContents, { panelType, enableSSO });
        });

        // Store window reference
        this.webUIWindows.set(windowId, {
            window: webUIWindow,
            url: targetUrl,
            panelType,
            enableSSO,
            created: new Date()
        });

        // Load the URL with proper error handling
        try {
            await webUIWindow.loadURL(targetUrl, {
                userAgent: this.getUserAgent()
            });
        } catch (error) {
            this.emit('webui-load-error', { windowId, error: error.message });
            webUIWindow.close();
            return null;
        }

        return {
            windowId,
            window: webUIWindow
        };
    }

    // Get app icon based on platform
    getAppIcon() {
        if (process.platform === 'win32') {
            return path.join(__dirname, '../../assets/icon.ico');
        } else if (process.platform === 'darwin') {
            return path.join(__dirname, '../../assets/icon.icns');
        } else {
            return path.join(__dirname, '../../assets/icon.png');
        }
    }

    // Get user agent for WebUI requests
    getUserAgent() {
        const version = require('../../../../package.json').version;
        return `FlexPBX-Desktop/${version} (${process.platform}; ${process.arch}) Electron/${process.versions.electron}`;
    }

    // Check if URL is allowed for navigation
    isAllowedUrl(targetUrl) {
        try {
            const parsedUrl = new URL(targetUrl);
            const hostname = parsedUrl.hostname;

            // Allow local addresses
            if (hostname === 'localhost' || hostname === '127.0.0.1' || hostname.startsWith('192.168.') || hostname.startsWith('10.')) {
                return true;
            }

            // Allow configured server URLs
            if (this.serverConfig && this.serverConfig.allowedHosts) {
                return this.serverConfig.allowedHosts.includes(hostname);
            }

            // Allow HTTPS URLs for control panels
            if (parsedUrl.protocol === 'https:' && this.isControlPanelUrl(targetUrl)) {
                return true;
            }

            return false;
        } catch (error) {
            return false;
        }
    }

    // Check if URL is a control panel request
    isControlPanelRequest(targetUrl) {
        const controlPanelPaths = [
            '/cpanel', '/whm', '/whmcs', '/directadmin', '/plesk',
            ':2083', ':2087', ':2222', ':8443'
        ];

        return controlPanelPaths.some(path => targetUrl.includes(path));
    }

    // Check if URL is a control panel URL
    isControlPanelUrl(targetUrl) {
        const controlPanelDomains = [
            'cpanel', 'whm', 'whmcs', 'directadmin', 'plesk'
        ];

        return controlPanelDomains.some(domain => targetUrl.includes(domain));
    }

    // Get session for URL
    getSessionForUrl(targetUrl) {
        if (!this.authService) return null;

        // Try to match URL to configured panel
        for (const provider of this.authService.get2FAProviders()) {
            if (targetUrl.includes(provider.serverUrl)) {
                const sessions = this.authService.getAuthStats().sessions;
                const activeSession = sessions.find(s => s.panelType === provider.type);
                return activeSession;
            }
        }

        return null;
    }

    // Inject custom styles for better integration
    injectCustomStyles(webContents) {
        const css = `
            /* FlexPBX Desktop Integration Styles */
            body {
                -webkit-app-region: no-drag;
            }

            /* macOS specific styles */
            @media screen and (-webkit-min-device-pixel-ratio: 2) {
                body {
                    -webkit-font-smoothing: antialiased;
                }
            }

            /* Windows specific styles */
            @media screen and (-ms-high-contrast: active) {
                * {
                    forced-color-adjust: none;
                }
            }

            /* Desktop app integration indicator */
            .flexpbx-desktop-indicator {
                position: fixed;
                top: 10px;
                right: 10px;
                background: #28a745;
                color: white;
                padding: 5px 10px;
                border-radius: 15px;
                font-size: 12px;
                z-index: 10000;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            }

            /* 2FA integration styles */
            .flexpbx-2fa-overlay {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.8);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 10001;
            }

            .flexpbx-2fa-dialog {
                background: white;
                padding: 30px;
                border-radius: 10px;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
                max-width: 400px;
                width: 90%;
            }
        `;

        webContents.insertCSS(css);
    }

    // Inject integration scripts
    injectIntegrationScripts(webContents, options) {
        const { panelType, enableSSO } = options;

        const script = `
            (function() {
                // Add desktop integration indicator
                const indicator = document.createElement('div');
                indicator.className = 'flexpbx-desktop-indicator';
                indicator.textContent = 'FlexPBX Desktop Connected';
                document.body.appendChild(indicator);

                // Setup communication with desktop app
                window.flexpbxDesktop = {
                    version: '2.0.0',
                    platform: '${process.platform}',
                    panelType: '${panelType}',
                    ssoEnabled: ${enableSSO},

                    // Request 2FA token
                    request2FA: function(callback) {
                        window.electronAPI.request2FA().then(callback);
                    },

                    // Get authentication status
                    getAuthStatus: function(callback) {
                        window.electronAPI.getAuthStatus().then(callback);
                    },

                    // Open external URL
                    openExternal: function(url) {
                        window.electronAPI.openExternal(url);
                    }
                };

                // Auto-fill 2FA fields if available
                if (${enableSSO} && '${panelType}') {
                    window.electronAPI.get2FAToken('${panelType}').then(function(token) {
                        if (token) {
                            // Try to find and fill 2FA input fields
                            const twoFAFields = document.querySelectorAll(
                                'input[name*="2fa"], input[name*="tfa"], input[name*="token"], input[name*="code"]'
                            );

                            twoFAFields.forEach(function(field) {
                                if (field.type === 'text' || field.type === 'number') {
                                    field.value = token;
                                    field.dispatchEvent(new Event('input', { bubbles: true }));
                                }
                            });
                        }
                    });
                }

                // Handle form submissions with 2FA
                document.addEventListener('submit', function(e) {
                    const form = e.target;
                    const twoFAField = form.querySelector('input[name*="2fa"], input[name*="tfa"], input[name*="token"]');

                    if (twoFAField && !twoFAField.value && ${enableSSO}) {
                        e.preventDefault();

                        window.electronAPI.get2FAToken('${panelType}').then(function(token) {
                            if (token) {
                                twoFAField.value = token;
                                form.submit();
                            }
                        });
                    }
                });

                console.log('FlexPBX Desktop integration loaded');
            })();
        `;

        webContents.executeJavaScript(script);
    }

    // Open WebUI for specific panel
    async openPanelWebUI(panelType, options = {}) {
        if (!this.authService) {
            throw new Error('Authentication service not available');
        }

        const providers = this.authService.get2FAProviders();
        const provider = providers.find(p => p.type === panelType);

        if (!provider) {
            throw new Error(`No configuration found for panel type: ${panelType}`);
        }

        return await this.createWebUIWindow({
            url: provider.serverUrl,
            title: `${panelType.toUpperCase()} - FlexPBX`,
            panelType,
            enableSSO: true,
            ...options
        });
    }

    // Open FlexPBX server WebUI
    async openFlexPBXWebUI(serverUrl, options = {}) {
        return await this.createWebUIWindow({
            url: serverUrl,
            title: 'FlexPBX Server Management',
            enableSSO: false,
            ...options
        });
    }

    // Get all open WebUI windows
    getWebUIWindows() {
        const windows = [];
        for (const [windowId, info] of this.webUIWindows) {
            windows.push({
                windowId,
                url: info.url,
                panelType: info.panelType,
                created: info.created,
                isVisible: info.window.isVisible(),
                isFocused: info.window.isFocused()
            });
        }
        return windows;
    }

    // Close WebUI window
    closeWebUIWindow(windowId) {
        const windowInfo = this.webUIWindows.get(windowId);
        if (windowInfo) {
            windowInfo.window.close();
            return true;
        }
        return false;
    }

    // Focus WebUI window
    focusWebUIWindow(windowId) {
        const windowInfo = this.webUIWindows.get(windowId);
        if (windowInfo) {
            windowInfo.window.focus();
            return true;
        }
        return false;
    }

    // Reload WebUI window
    reloadWebUIWindow(windowId) {
        const windowInfo = this.webUIWindows.get(windowId);
        if (windowInfo) {
            windowInfo.window.reload();
            return true;
        }
        return false;
    }

    // Close all WebUI windows
    closeAllWebUIWindows() {
        for (const [windowId, info] of this.webUIWindows) {
            info.window.close();
        }
        this.webUIWindows.clear();
    }

    // Get WebUI statistics
    getWebUIStats() {
        return {
            openWindows: this.webUIWindows.size,
            windows: this.getWebUIWindows()
        };
    }
}

module.exports = WebUIService;