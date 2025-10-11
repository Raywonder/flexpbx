// FlexPBX Mac Client - Remote Server Manager
// Handles connection to remote FlexPBX servers with dynamic authentication methods

const { ipcRenderer } = require('electron');
const crypto = require('crypto');
const os = require('os');

class RemoteServerManager {
    constructor() {
        this.serverUrl = null;
        this.apiKey = null;
        this.deviceId = this.generateDeviceId();
        this.websocket = null;
        this.connectionStatus = 'disconnected';
        this.serverInfo = null;
        this.autoReconnect = true;
        this.authMethods = [];
        this.selectedAuthMethod = null;
        this.fallbackAuthMethods = [];

        this.setupEventHandlers();
    }

    generateDeviceId() {
        const hostname = os.hostname();
        const platform = os.platform();
        const arch = os.arch();
        const userInfo = os.userInfo();

        const deviceString = `${hostname}-${platform}-${arch}-${userInfo.username}`;
        return crypto.createHash('sha256').update(deviceString).digest('hex').substring(0, 16);
    }

    setupEventHandlers() {
        // Handle connection status updates
        ipcRenderer.on('connection-status-changed', (event, status) => {
            this.connectionStatus = status;
            this.notifyUI('connection-status', status);
        });

        // Handle server responses
        ipcRenderer.on('server-response', (event, response) => {
            this.handleServerResponse(response);
        });
    }

    // Discover and connect to remote FlexPBX server
    async discoverServer(serverUrl) {
        try {
            this.notifyUI('discovery-status', 'discovering');

            // Clean up URL
            if (!serverUrl.startsWith('http')) {
                serverUrl = `https://${serverUrl}`;
            }

            // Try to connect to server info endpoint
            const response = await fetch(`${serverUrl}/api/info`, {
                method: 'GET',
                headers: {
                    'User-Agent': 'FlexPBX-Desktop-Mac/1.0.0',
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error(`Server responded with ${response.status}`);
            }

            const serverInfo = await response.json();

            if (!serverInfo.success) {
                throw new Error('Invalid FlexPBX server response');
            }

            this.serverInfo = {
                url: serverUrl,
                name: serverInfo.name || 'FlexPBX Remote Server',
                version: serverInfo.version,
                domains: serverInfo.supported_domains || [],
                ssl_enabled: serverUrl.startsWith('https://'),
                features: serverInfo.endpoints || {},
                auth_methods: serverInfo.auth_methods || ['pincode'],
                hosting_panels: serverInfo.hosting_panels || {},
                installation_paths: serverInfo.installation_paths || {}
            };

            // Detect available authentication methods
            await this.detectAuthMethods();

            this.notifyUI('discovery-status', 'found');
            this.notifyUI('server-info', this.serverInfo);
            this.notifyUI('auth-methods-detected', this.authMethods);

            return this.serverInfo;

        } catch (error) {
            console.error('Server discovery failed:', error);
            this.notifyUI('discovery-status', 'failed');
            this.notifyUI('error', `Server discovery failed: ${error.message}`);
            throw error;
        }
    }

    // Detect available authentication methods from server
    async detectAuthMethods() {
        if (!this.serverInfo) {
            throw new Error('No server discovered. Run discoverServer() first.');
        }

        try {
            const response = await fetch(`${this.serverInfo.url}/api/auth/methods`, {
                method: 'GET',
                headers: {
                    'User-Agent': 'FlexPBX-Desktop-Mac/1.0.0',
                    'Accept': 'application/json'
                }
            });

            if (response.ok) {
                const result = await response.json();
                if (result.success) {
                    this.authMethods = result.data.methods || [];
                    this.fallbackAuthMethods = result.data.fallback_methods || [];

                    // Set default auth method (prefer pincode for desktop)
                    if (this.authMethods.includes('pincode')) {
                        this.selectedAuthMethod = 'pincode';
                    } else if (this.authMethods.length > 0) {
                        this.selectedAuthMethod = this.authMethods[0];
                    }

                    console.log('Detected authentication methods:', this.authMethods);
                    return this.authMethods;
                }
            }

            // Fallback to default methods if detection fails
            this.authMethods = this.serverInfo.auth_methods || ['pincode'];
            this.selectedAuthMethod = 'pincode';

            return this.authMethods;

        } catch (error) {
            console.warn('Failed to detect auth methods, using defaults:', error);
            this.authMethods = ['pincode'];
            this.selectedAuthMethod = 'pincode';
            return this.authMethods;
        }
    }

    // Set authentication method
    setAuthMethod(method) {
        if (this.authMethods.includes(method)) {
            this.selectedAuthMethod = method;
            this.notifyUI('auth-method-changed', method);
            return true;
        }
        return false;
    }

    // Get available authentication methods
    getAuthMethods() {
        return {
            available: this.authMethods,
            selected: this.selectedAuthMethod,
            fallback: this.fallbackAuthMethods
        };
    }

    // Validate installation path and provide guidance
    async validateInstallationPath(path) {
        try {
            const response = await fetch(`${this.serverInfo.url}/api/install/validate-path`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'User-Agent': 'FlexPBX-Desktop-Mac/1.0.0'
                },
                body: JSON.stringify({ path: path })
            });

            const result = await response.json();

            if (result.success) {
                return {
                    valid: true,
                    warnings: result.data.warnings || [],
                    recommendations: result.data.recommendations || [],
                    detected_services: result.data.detected_services || {},
                    suggested_subfolder: result.data.suggested_subfolder || null
                };
            } else {
                return {
                    valid: false,
                    error: result.error,
                    recommendations: []
                };
            }

        } catch (error) {
            console.error('Path validation failed:', error);
            return {
                valid: false,
                error: error.message,
                recommendations: []
            };
        }
    }

    // Register device with remote server
    async registerDevice() {
        if (!this.serverInfo) {
            throw new Error('No server discovered. Run discoverServer() first.');
        }

        try {
            this.notifyUI('registration-status', 'registering');

            const deviceInfo = {
                device_name: `${os.hostname()} (FlexPBX Mac Client)`,
                device_type: 'desktop_mac',
                device_identifier: this.deviceId,
                system_info: {
                    hostname: os.hostname(),
                    platform: os.platform(),
                    arch: os.arch(),
                    release: os.release(),
                    user: os.userInfo().username,
                    app_version: '1.0.0'
                }
            };

            const response = await fetch(`${this.serverInfo.url}/api/auth/device/register`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'User-Agent': 'FlexPBX-Desktop-Mac/1.0.0'
                },
                body: JSON.stringify(deviceInfo)
            });

            const result = await response.json();

            if (!result.success) {
                throw new Error(result.error || 'Device registration failed');
            }

            this.deviceRegistrationId = result.data.device_id;
            this.notifyUI('registration-status', 'registered');
            this.notifyUI('registration-info', result.data);

            return result.data;

        } catch (error) {
            console.error('Device registration failed:', error);
            this.notifyUI('registration-status', 'failed');
            this.notifyUI('error', `Registration failed: ${error.message}`);
            throw error;
        }
    }

    // Request authentication token based on selected method
    async requestAuthentication(authData = {}) {
        if (!this.deviceRegistrationId && !this.deviceId) {
            throw new Error('Device not registered. Run registerDevice() first.');
        }

        const method = authData.method || this.selectedAuthMethod || 'pincode';

        try {
            this.notifyUI('auth-status', 'requesting');

            // Prepare request data based on authentication method
            const requestData = {
                method: method,
                device_id: this.deviceRegistrationId || this.deviceId,
                device_identifier: this.deviceId,
                ...authData
            };

            const response = await fetch(`${this.serverInfo.url}/api/auth/request`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'User-Agent': 'FlexPBX-Desktop-Mac/1.0.0'
                },
                body: JSON.stringify(requestData)
            });

            const result = await response.json();

            if (!result.success) {
                // Try fallback authentication methods
                if (this.fallbackAuthMethods.length > 0) {
                    return await this.tryFallbackAuth(result.error);
                }
                throw new Error(result.error || `${method} authentication request failed`);
            }

            this.authInfo = result.data;
            this.notifyUI('auth-status', 'received');
            this.notifyUI('auth-info', result.data);

            // Start timer for time-sensitive authentication methods
            if (result.data.expires_at) {
                this.startAuthTimer(result.data.expires_at);
            }

            return result.data;

        } catch (error) {
            console.error(`${method} authentication request failed:`, error);
            this.notifyUI('auth-status', 'failed');
            this.notifyUI('error', `${method} authentication request failed: ${error.message}`);
            throw error;
        }
    }

    // Try fallback authentication methods
    async tryFallbackAuth(primaryError) {
        console.log('Trying fallback authentication methods...');
        this.notifyUI('auth-status', 'trying-fallback');

        for (const fallbackMethod of this.fallbackAuthMethods) {
            try {
                console.log(`Attempting fallback authentication with: ${fallbackMethod}`);

                if (fallbackMethod === 'whmcs_license') {
                    return await this.requestWHMCSLicenseAuth();
                } else {
                    // Set fallback method and retry
                    this.selectedAuthMethod = fallbackMethod;
                    return await this.requestAuthentication({ method: fallbackMethod });
                }
            } catch (fallbackError) {
                console.warn(`Fallback method ${fallbackMethod} failed:`, fallbackError);
                continue;
            }
        }

        throw new Error(`All authentication methods failed. Primary error: ${primaryError}`);
    }

    // Request WHMCS license-based authentication
    async requestWHMCSLicenseAuth() {
        try {
            this.notifyUI('auth-status', 'checking-whmcs-license');

            const response = await fetch(`${this.serverInfo.url}/api/auth/whmcs/license`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'User-Agent': 'FlexPBX-Desktop-Mac/1.0.0'
                },
                body: JSON.stringify({
                    device_identifier: this.deviceId,
                    check_license: true
                })
            });

            const result = await response.json();

            if (!result.success) {
                throw new Error(result.error || 'WHMCS license validation failed');
            }

            this.authInfo = result.data;
            this.notifyUI('auth-status', 'whmcs-license-valid');
            this.notifyUI('auth-info', result.data);

            return result.data;

        } catch (error) {
            console.error('WHMCS license authentication failed:', error);
            throw error;
        }
    }

    // Request pincode from server admin (legacy method, now part of flexible auth)
    async requestPincode() {
        return await this.requestAuthentication({ method: 'pincode' });
    }

    // Authorize device with authentication credentials
    async authorizeDevice(authCredentials) {
        const method = authCredentials.method || this.selectedAuthMethod || 'pincode';

        // Validate credentials based on method
        if (method === 'pincode') {
            const pincode = authCredentials.pincode || authCredentials.value;
            if (!pincode || pincode.length !== 6) {
                throw new Error('Invalid pincode format. Must be 6 digits.');
            }
        }

        try {
            this.notifyUI('authorization-status', 'authorizing');

            const requestData = {
                method: method,
                device_identifier: this.deviceId,
                ...authCredentials
            };

            const response = await fetch(`${this.serverInfo.url}/api/auth/device/authorize`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'User-Agent': 'FlexPBX-Desktop-Mac/1.0.0'
                },
                body: JSON.stringify(requestData)
            });

            const result = await response.json();

            if (!result.success) {
                // Try fallback authentication if primary method fails
                if (this.fallbackAuthMethods.length > 0 && method !== 'whmcs_license') {
                    console.log('Primary authorization failed, trying fallback methods...');
                    return await this.tryFallbackAuthorization(result.error, authCredentials);
                }
                throw new Error(result.error || 'Authorization failed');
            }

            // Store API key and server info
            this.apiKey = result.data.api_key;
            this.serverUrl = this.serverInfo.url;
            this.authorizedMethod = method;

            // Save credentials securely
            await this.saveCredentials();

            this.notifyUI('authorization-status', 'authorized');
            this.notifyUI('authorization-info', result.data);
            this.notifyUI('auth-method-used', method);

            // Establish WebSocket connection for real-time updates
            await this.connectWebSocket();

            return result.data;

        } catch (error) {
            console.error(`Authorization failed with ${method}:`, error);
            this.notifyUI('authorization-status', 'failed');
            this.notifyUI('error', `Authorization failed: ${error.message}`);
            throw error;
        }
    }

    // Try fallback authorization methods
    async tryFallbackAuthorization(primaryError, originalCredentials) {
        this.notifyUI('authorization-status', 'trying-fallback');

        for (const fallbackMethod of this.fallbackAuthMethods) {
            try {
                console.log(`Attempting fallback authorization with: ${fallbackMethod}`);

                if (fallbackMethod === 'whmcs_license') {
                    // WHMCS license validation doesn't require user credentials
                    return await this.authorizeDevice({ method: 'whmcs_license' });
                } else {
                    // Ask user for credentials for other fallback methods
                    this.notifyUI('request-fallback-credentials', {
                        method: fallbackMethod,
                        primary_error: primaryError
                    });

                    // This would be handled by the UI - for now, skip automatic fallback
                    // that requires user input
                    continue;
                }
            } catch (fallbackError) {
                console.warn(`Fallback authorization ${fallbackMethod} failed:`, fallbackError);
                continue;
            }
        }

        throw new Error(`All authorization methods failed. Primary error: ${primaryError}`);
    }

    // Authorize device with pincode (legacy method for backward compatibility)
    async authorizeWithPincode(pincode) {
        return await this.authorizeDevice({ method: 'pincode', pincode: pincode });
    }

    // Authorize with WHM credentials
    async authorizeWithWHM(username, password) {
        return await this.authorizeDevice({
            method: 'whm',
            username: username,
            password: password
        });
    }

    // Authorize with cPanel credentials
    async authorizeWithCPanel(username, password) {
        return await this.authorizeDevice({
            method: 'cpanel',
            username: username,
            password: password
        });
    }

    // Authorize with WHMCS client credentials
    async authorizeWithWHMCS(email, password) {
        return await this.authorizeDevice({
            method: 'whmcs_client',
            email: email,
            password: password
        });
    }

    // Establish WebSocket connection for real-time communication
    async connectWebSocket() {
        if (!this.apiKey || !this.serverUrl) {
            throw new Error('Not authorized. Complete authorization first.');
        }

        try {
            const wsUrl = this.serverUrl.replace('https://', 'wss://').replace('http://', 'ws://') + '/ws/';

            this.websocket = new WebSocket(wsUrl);

            this.websocket.onopen = () => {
                console.log('WebSocket connected to FlexPBX server');
                this.connectionStatus = 'connected';

                // Authenticate WebSocket connection
                this.websocket.send(JSON.stringify({
                    type: 'authenticate',
                    api_key: this.apiKey,
                    device_id: this.deviceId
                }));

                this.notifyUI('websocket-status', 'connected');
            };

            this.websocket.onmessage = (event) => {
                try {
                    const message = JSON.parse(event.data);
                    this.handleWebSocketMessage(message);
                } catch (error) {
                    console.error('Failed to parse WebSocket message:', error);
                }
            };

            this.websocket.onclose = (event) => {
                console.log('WebSocket disconnected:', event.code, event.reason);
                this.connectionStatus = 'disconnected';
                this.notifyUI('websocket-status', 'disconnected');

                // Auto-reconnect after 5 seconds if enabled
                if (this.autoReconnect) {
                    setTimeout(() => {
                        this.connectWebSocket();
                    }, 5000);
                }
            };

            this.websocket.onerror = (error) => {
                console.error('WebSocket error:', error);
                this.notifyUI('websocket-error', error);
            };

        } catch (error) {
            console.error('WebSocket connection failed:', error);
            throw error;
        }
    }

    // Handle incoming WebSocket messages
    handleWebSocketMessage(message) {
        switch (message.type) {
            case 'authenticated':
                if (message.success) {
                    console.log('WebSocket authenticated successfully');
                    this.notifyUI('websocket-authenticated', true);
                } else {
                    console.error('WebSocket authentication failed:', message.error);
                    this.notifyUI('websocket-authenticated', false);
                }
                break;

            case 'server_status':
                this.notifyUI('server-status-update', message.data);
                break;

            case 'extension_update':
                this.notifyUI('extension-update', message.data);
                break;

            case 'call_event':
                this.notifyUI('call-event', message.data);
                break;

            case 'system_notification':
                this.notifyUI('system-notification', message.data);
                break;

            default:
                console.log('Unknown WebSocket message type:', message.type);
        }
    }

    // Make API calls to remote server
    async apiCall(endpoint, method = 'GET', data = null) {
        if (!this.apiKey || !this.serverUrl) {
            throw new Error('Not connected to server. Complete authorization first.');
        }

        try {
            const options = {
                method: method,
                headers: {
                    'X-API-Key': this.apiKey,
                    'Content-Type': 'application/json',
                    'User-Agent': 'FlexPBX-Desktop-Mac/1.0.0'
                }
            };

            if (data && (method === 'POST' || method === 'PUT')) {
                options.body = JSON.stringify(data);
            }

            const response = await fetch(`${this.serverUrl}/api/${endpoint}`, options);
            const result = await response.json();

            if (!response.ok) {
                throw new Error(result.error || `API call failed with status ${response.status}`);
            }

            return result;

        } catch (error) {
            console.error(`API call to ${endpoint} failed:`, error);
            throw error;
        }
    }

    // Get server status
    async getServerStatus() {
        return await this.apiCall('status');
    }

    // Get extensions list
    async getExtensions() {
        return await this.apiCall('extensions');
    }

    // Create new extension
    async createExtension(extensionData) {
        return await this.apiCall('extensions', 'POST', extensionData);
    }

    // Control server services
    async controlServer(action) {
        return await this.apiCall(`system/${action}`, 'POST');
    }

    // Create backup
    async createBackup() {
        return await this.apiCall('backup/create', 'POST');
    }

    // Start authentication expiry timer (generic for all auth methods)
    startAuthTimer(expiresAt) {
        const expiryTime = new Date(expiresAt);
        const now = new Date();
        const timeRemaining = expiryTime - now;

        if (timeRemaining > 0) {
            this.authTimer = setTimeout(() => {
                this.notifyUI('auth-expired', true);
            }, timeRemaining);

            // Update countdown every second
            this.authCountdown = setInterval(() => {
                const remaining = expiryTime - new Date();
                if (remaining > 0) {
                    this.notifyUI('auth-countdown', Math.ceil(remaining / 1000));
                } else {
                    clearInterval(this.authCountdown);
                }
            }, 1000);
        }
    }

    // Start pincode expiry timer (legacy method for backward compatibility)
    startPincodeTimer(expiresAt) {
        return this.startAuthTimer(expiresAt);
    }

    // Auto-discover main server from predefined domains
    async autoDiscoverMainServer() {
        const discoveryDomains = [
            'flexpbx.devinecreations.net',
            'api.devinecreations.net',
            'flexpbx.devinecreations.com',
            'api.tappedin.fm',
            'api.devine-creations.com',
            'api.raywonderis.me'
        ];

        this.notifyUI('auto-discovery-status', 'searching');

        for (const domain of discoveryDomains) {
            try {
                console.log(`Attempting auto-discovery on: ${domain}`);
                const serverInfo = await this.discoverServer(domain);

                if (serverInfo) {
                    console.log(`Successfully discovered FlexPBX server at: ${domain}`);
                    this.notifyUI('auto-discovery-status', 'found');
                    this.notifyUI('auto-discovered-server', { domain, serverInfo });
                    return serverInfo;
                }
            } catch (error) {
                console.log(`Auto-discovery failed for ${domain}:`, error.message);
                continue;
            }
        }

        this.notifyUI('auto-discovery-status', 'not-found');
        console.log('No FlexPBX servers found during auto-discovery');
        return null;
    }

    // Get server setup instructions based on detected configuration
    async getServerSetupInstructions() {
        if (!this.serverInfo) {
            throw new Error('No server discovered. Run discoverServer() first.');
        }

        try {
            const response = await fetch(`${this.serverInfo.url}/api/install/instructions`, {
                method: 'GET',
                headers: {
                    'User-Agent': 'FlexPBX-Desktop-Mac/1.0.0',
                    'Accept': 'application/json'
                }
            });

            if (response.ok) {
                const result = await response.json();
                if (result.success) {
                    return result.data;
                }
            }

            return {
                instructions: 'No specific setup instructions available.',
                detected_environment: {},
                recommendations: []
            };

        } catch (error) {
            console.error('Failed to get setup instructions:', error);
            return {
                instructions: 'Failed to retrieve setup instructions.',
                error: error.message
            };
        }
    }

    // Save credentials securely using keychain
    async saveCredentials() {
        try {
            const credentials = {
                serverUrl: this.serverUrl,
                apiKey: this.apiKey,
                deviceId: this.deviceId,
                serverInfo: this.serverInfo,
                authMethods: this.authMethods,
                selectedAuthMethod: this.selectedAuthMethod,
                authorizedMethod: this.authorizedMethod,
                fallbackAuthMethods: this.fallbackAuthMethods
            };

            // Use Electron's safeStorage or keytar for secure storage
            ipcRenderer.invoke('save-server-credentials', credentials);
        } catch (error) {
            console.error('Failed to save credentials:', error);
        }
    }

    // Load saved credentials
    async loadCredentials() {
        try {
            const credentials = await ipcRenderer.invoke('load-server-credentials');
            if (credentials) {
                this.serverUrl = credentials.serverUrl;
                this.apiKey = credentials.apiKey;
                this.deviceId = credentials.deviceId || this.deviceId;
                this.serverInfo = credentials.serverInfo;
                this.authMethods = credentials.authMethods || ['pincode'];
                this.selectedAuthMethod = credentials.selectedAuthMethod || 'pincode';
                this.authorizedMethod = credentials.authorizedMethod;
                this.fallbackAuthMethods = credentials.fallbackAuthMethods || [];
                return credentials;
            }
        } catch (error) {
            console.error('Failed to load credentials:', error);
        }
        return null;
    }

    // Disconnect from server
    async disconnect() {
        this.autoReconnect = false;

        if (this.websocket) {
            this.websocket.close();
            this.websocket = null;
        }

        // Clear authentication timers
        if (this.authTimer) {
            clearTimeout(this.authTimer);
        }

        if (this.authCountdown) {
            clearInterval(this.authCountdown);
        }

        // Legacy timer cleanup
        if (this.pincodeTimer) {
            clearTimeout(this.pincodeTimer);
        }

        if (this.pincodeCountdown) {
            clearInterval(this.pincodeCountdown);
        }

        this.connectionStatus = 'disconnected';
        this.notifyUI('connection-status', 'disconnected');
    }

    // Notify UI components of updates
    notifyUI(event, data) {
        ipcRenderer.send('remote-server-event', { event, data });
    }

    // Get connection status
    getConnectionStatus() {
        return {
            status: this.connectionStatus,
            serverInfo: this.serverInfo,
            hasApiKey: !!this.apiKey,
            deviceId: this.deviceId,
            authMethods: this.authMethods,
            selectedAuthMethod: this.selectedAuthMethod,
            authorizedMethod: this.authorizedMethod,
            fallbackAuthMethods: this.fallbackAuthMethods
        };
    }
}

module.exports = RemoteServerManager;