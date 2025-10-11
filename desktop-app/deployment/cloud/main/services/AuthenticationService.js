const { EventEmitter } = require('events');
const crypto = require('crypto');
const axios = require('axios');
const Store = require('electron-store');

class AuthenticationService extends EventEmitter {
    constructor() {
        super();
        this.store = new Store({ name: 'flexpbx-auth' });
        this.sessions = new Map();
        this.twoFactorProviders = new Map();
        this.supportedPanels = ['cpanel', 'whm', 'whmcs', 'directadmin', 'plesk'];
    }

    // Initialize authentication service
    async initialize() {
        // Load saved 2FA configurations
        const savedConfigs = this.store.get('twoFactorConfigs', {});

        for (const [provider, config] of Object.entries(savedConfigs)) {
            this.twoFactorProviders.set(provider, config);
        }

        this.emit('initialized');
    }

    // Auto-detect control panels on server
    async detectControlPanels(serverUrl) {
        const detectedPanels = [];
        const testUrls = {
            cpanel: [`${serverUrl}:2083/login`, `${serverUrl}/cpanel`],
            whm: [`${serverUrl}:2087/login`, `${serverUrl}/whm`],
            whmcs: [`${serverUrl}/whmcs`, `${serverUrl}/admin`],
            directadmin: [`${serverUrl}:2222/login`, `${serverUrl}/directadmin`],
            plesk: [`${serverUrl}:8443/login`, `${serverUrl}/plesk`]
        };

        for (const [panel, urls] of Object.entries(testUrls)) {
            for (const url of urls) {
                try {
                    const response = await axios.get(url, {
                        timeout: 5000,
                        validateStatus: (status) => status < 500 // Accept redirects and auth required
                    });

                    if (response.status < 500) {
                        detectedPanels.push({
                            panel,
                            url,
                            detected: true,
                            status: response.status
                        });
                        break; // Found this panel, try next
                    }
                } catch (error) {
                    // Continue to next URL
                }
            }
        }

        return detectedPanels;
    }

    // Configure 2FA for control panel
    async configure2FA(panelType, config) {
        const {
            serverUrl,
            username,
            password,
            apiKey,
            twoFactorSecret,
            authUrl,
            customEndpoint
        } = config;

        const twoFactorConfig = {
            panelType,
            serverUrl,
            username,
            authUrl: authUrl || this.getDefaultAuthUrl(panelType, serverUrl),
            customEndpoint,
            enabled: true,
            createdAt: new Date().toISOString()
        };

        // Encrypt sensitive data
        if (password) {
            twoFactorConfig.passwordHash = this.encryptData(password);
        }
        if (apiKey) {
            twoFactorConfig.apiKeyHash = this.encryptData(apiKey);
        }
        if (twoFactorSecret) {
            twoFactorConfig.secretHash = this.encryptData(twoFactorSecret);
        }

        // Test configuration
        const testResult = await this.test2FAConfiguration(twoFactorConfig);
        if (!testResult.success) {
            throw new Error(`2FA configuration test failed: ${testResult.error}`);
        }

        // Save configuration
        this.twoFactorProviders.set(panelType, twoFactorConfig);
        this.save2FAConfigurations();

        this.emit('2fa-configured', { panelType, config: twoFactorConfig });
        return { success: true, config: twoFactorConfig };
    }

    // Get default auth URL for panel type
    getDefaultAuthUrl(panelType, serverUrl) {
        const defaults = {
            cpanel: `${serverUrl}:2083/login/?login_only=1`,
            whm: `${serverUrl}:2087/login/?login_only=1`,
            whmcs: `${serverUrl}/whmcs/admin/login.php`,
            directadmin: `${serverUrl}:2222/CMD_LOGIN`,
            plesk: `${serverUrl}:8443/login_up.php`
        };

        return defaults[panelType] || `${serverUrl}/login`;
    }

    // Generate 2FA token
    generate2FAToken(secret) {
        const time = Math.floor(Date.now() / 30000);
        const buffer = Buffer.allocUnsafe(8);
        buffer.writeUInt32BE(0, 0);
        buffer.writeUInt32BE(time, 4);

        const hmac = crypto.createHmac('sha1', Buffer.from(secret, 'base32'));
        hmac.update(buffer);
        const digest = hmac.digest();

        const offset = digest[digest.length - 1] & 0xf;
        const code = (digest.readUInt32BE(offset) & 0x7fffffff) % 1000000;

        return code.toString().padStart(6, '0');
    }

    // Authenticate with 2FA
    async authenticateWith2FA(panelType, options = {}) {
        const config = this.twoFactorProviders.get(panelType);
        if (!config) {
            throw new Error(`No 2FA configuration found for ${panelType}`);
        }

        const { manualToken, skipToken } = options;

        try {
            let authData = {
                username: config.username,
                timestamp: Date.now()
            };

            // Add password if available
            if (config.passwordHash) {
                authData.password = this.decryptData(config.passwordHash);
            }

            // Add API key if available
            if (config.apiKeyHash) {
                authData.api_key = this.decryptData(config.apiKeyHash);
            }

            // Generate or use provided 2FA token
            if (!skipToken) {
                if (manualToken) {
                    authData.twofa_token = manualToken;
                } else if (config.secretHash) {
                    const secret = this.decryptData(config.secretHash);
                    authData.twofa_token = this.generate2FAToken(secret);
                }
            }

            // Perform authentication based on panel type
            const authResult = await this.performPanelAuthentication(panelType, config, authData);

            if (authResult.success) {
                // Create session
                const sessionId = crypto.randomBytes(32).toString('hex');
                const session = {
                    id: sessionId,
                    panelType,
                    username: config.username,
                    serverUrl: config.serverUrl,
                    authenticated: true,
                    createdAt: new Date(),
                    expiresAt: new Date(Date.now() + 24 * 60 * 60 * 1000), // 24 hours
                    token: authResult.token,
                    permissions: authResult.permissions || []
                };

                this.sessions.set(sessionId, session);
                this.emit('authenticated', { panelType, session });

                return {
                    success: true,
                    sessionId,
                    session,
                    message: `Successfully authenticated with ${panelType}`
                };
            } else {
                throw new Error(authResult.error || 'Authentication failed');
            }

        } catch (error) {
            this.emit('auth-error', { panelType, error: error.message });
            return {
                success: false,
                error: error.message
            };
        }
    }

    // Perform panel-specific authentication
    async performPanelAuthentication(panelType, config, authData) {
        switch (panelType) {
            case 'cpanel':
                return await this.authenticateCPanel(config, authData);
            case 'whm':
                return await this.authenticateWHM(config, authData);
            case 'whmcs':
                return await this.authenticateWHMCS(config, authData);
            case 'directadmin':
                return await this.authenticateDirectAdmin(config, authData);
            case 'plesk':
                return await this.authenticatePlesk(config, authData);
            default:
                throw new Error(`Unsupported panel type: ${panelType}`);
        }
    }

    // cPanel authentication
    async authenticateCPanel(config, authData) {
        try {
            const loginData = new URLSearchParams({
                user: authData.username,
                pass: authData.password,
                tfa_token: authData.twofa_token || '',
                goto_uri: '/cpsess/reseller',
                domain: config.serverUrl.replace(/https?:\/\//, '')
            });

            const response = await axios.post(config.authUrl, loginData, {
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'User-Agent': 'FlexPBX-Desktop/1.0.0'
                },
                timeout: 15000,
                maxRedirects: 5
            });

            // Check for successful authentication
            if (response.data.includes('security_token') || response.status === 200) {
                const sessionMatch = response.data.match(/session['"]\s*:\s*['"]([^'"]+)['"]/);
                const token = sessionMatch ? sessionMatch[1] : null;

                return {
                    success: true,
                    token,
                    permissions: ['cpanel_access'],
                    response: response.data
                };
            } else {
                return {
                    success: false,
                    error: 'Invalid credentials or 2FA token'
                };
            }
        } catch (error) {
            return {
                success: false,
                error: `cPanel authentication failed: ${error.message}`
            };
        }
    }

    // WHM authentication
    async authenticateWHM(config, authData) {
        try {
            const apiUrl = `${config.serverUrl}:2087/json-api/login`;
            const loginData = {
                user: authData.username,
                pass: authData.password,
                tfa_token: authData.twofa_token || ''
            };

            const response = await axios.post(apiUrl, loginData, {
                headers: {
                    'Content-Type': 'application/json'
                },
                timeout: 15000
            });

            if (response.data && response.data.metadata && response.data.metadata.result === 1) {
                return {
                    success: true,
                    token: response.data.data.session,
                    permissions: ['whm_access', 'root_access']
                };
            } else {
                return {
                    success: false,
                    error: response.data?.metadata?.reason || 'WHM authentication failed'
                };
            }
        } catch (error) {
            return {
                success: false,
                error: `WHM authentication failed: ${error.message}`
            };
        }
    }

    // WHMCS authentication
    async authenticateWHMCS(config, authData) {
        try {
            const apiUrl = `${config.serverUrl}/whmcs/includes/api.php`;
            const apiData = {
                action: 'ValidateLogin',
                username: authData.username,
                password2: authData.password,
                tfa_token: authData.twofa_token || '',
                responsetype: 'json'
            };

            if (authData.api_key) {
                apiData.identifier = authData.api_key;
                apiData.secret = authData.password;
                delete apiData.password2;
            }

            const response = await axios.post(apiUrl, apiData, {
                timeout: 15000
            });

            if (response.data && response.data.result === 'success') {
                return {
                    success: true,
                    token: response.data.session_token || response.data.token,
                    permissions: ['whmcs_access', 'admin_access']
                };
            } else {
                return {
                    success: false,
                    error: response.data?.message || 'WHMCS authentication failed'
                };
            }
        } catch (error) {
            return {
                success: false,
                error: `WHMCS authentication failed: ${error.message}`
            };
        }
    }

    // DirectAdmin authentication
    async authenticateDirectAdmin(config, authData) {
        try {
            const loginData = new URLSearchParams({
                username: authData.username,
                password: authData.password,
                tfa_code: authData.twofa_token || ''
            });

            const response = await axios.post(config.authUrl, loginData, {
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                timeout: 15000,
                maxRedirects: 5
            });

            // DirectAdmin returns 200 on success, check for error indicators
            if (response.status === 200 && !response.data.includes('error=') && !response.data.includes('Invalid')) {
                const sessionMatch = response.headers['set-cookie']?.find(cookie => cookie.includes('session'));
                const sessionId = sessionMatch ? sessionMatch.split('=')[1].split(';')[0] : null;

                return {
                    success: true,
                    token: sessionId,
                    permissions: ['directadmin_access']
                };
            } else {
                return {
                    success: false,
                    error: 'DirectAdmin authentication failed - invalid credentials or 2FA'
                };
            }
        } catch (error) {
            return {
                success: false,
                error: `DirectAdmin authentication failed: ${error.message}`
            };
        }
    }

    // Plesk authentication
    async authenticatePlesk(config, authData) {
        try {
            const apiUrl = `${config.serverUrl}:8443/api/v2/auth/login`;
            const loginData = {
                login: authData.username,
                password: authData.password,
                tfa_code: authData.twofa_token || ''
            };

            const response = await axios.post(apiUrl, loginData, {
                headers: {
                    'Content-Type': 'application/json'
                },
                timeout: 15000
            });

            if (response.data && response.data.token) {
                return {
                    success: true,
                    token: response.data.token,
                    permissions: ['plesk_access']
                };
            } else {
                return {
                    success: false,
                    error: 'Plesk authentication failed'
                };
            }
        } catch (error) {
            return {
                success: false,
                error: `Plesk authentication failed: ${error.message}`
            };
        }
    }

    // Test 2FA configuration
    async test2FAConfiguration(config) {
        try {
            const testAuth = await this.performPanelAuthentication(
                config.panelType,
                config,
                {
                    username: config.username,
                    password: config.passwordHash ? this.decryptData(config.passwordHash) : '',
                    api_key: config.apiKeyHash ? this.decryptData(config.apiKeyHash) : '',
                    twofa_token: config.secretHash ? this.generate2FAToken(this.decryptData(config.secretHash)) : ''
                }
            );

            return {
                success: testAuth.success,
                error: testAuth.error,
                message: testAuth.success ? '2FA configuration test successful' : testAuth.error
            };
        } catch (error) {
            return {
                success: false,
                error: error.message
            };
        }
    }

    // Encrypt sensitive data
    encryptData(data) {
        const algorithm = 'aes-256-gcm';
        const key = crypto.scryptSync('flexpbx-auth-key', 'salt', 32);
        const iv = crypto.randomBytes(16);
        const cipher = crypto.createCipheriv(algorithm, key, iv);

        let encrypted = cipher.update(data, 'utf8', 'hex');
        encrypted += cipher.final('hex');

        return {
            encrypted,
            iv: iv.toString('hex'),
            tag: cipher.getAuthTag().toString('hex')
        };
    }

    // Decrypt sensitive data
    decryptData(encryptedData) {
        const algorithm = 'aes-256-gcm';
        const key = crypto.scryptSync('flexpbx-auth-key', 'salt', 32);
        const decipher = crypto.createDecipher(algorithm, key);

        if (encryptedData.tag) {
            decipher.setAuthTag(Buffer.from(encryptedData.tag, 'hex'));
        }

        let decrypted = decipher.update(encryptedData.encrypted, 'hex', 'utf8');
        decrypted += decipher.final('utf8');

        return decrypted;
    }

    // Save 2FA configurations
    save2FAConfigurations() {
        const configs = {};
        for (const [provider, config] of this.twoFactorProviders) {
            // Remove sensitive data before saving
            const safeConfig = { ...config };
            delete safeConfig.password;
            delete safeConfig.apiKey;
            delete safeConfig.secret;
            configs[provider] = safeConfig;
        }

        this.store.set('twoFactorConfigs', configs);
    }

    // Get session by ID
    getSession(sessionId) {
        const session = this.sessions.get(sessionId);
        if (session && session.expiresAt > new Date()) {
            return session;
        } else if (session) {
            this.sessions.delete(sessionId);
        }
        return null;
    }

    // Get all configured 2FA providers
    get2FAProviders() {
        const providers = [];
        for (const [type, config] of this.twoFactorProviders) {
            providers.push({
                type,
                serverUrl: config.serverUrl,
                username: config.username,
                enabled: config.enabled,
                createdAt: config.createdAt
            });
        }
        return providers;
    }

    // Remove 2FA configuration
    remove2FAConfiguration(panelType) {
        this.twoFactorProviders.delete(panelType);
        this.save2FAConfigurations();
        this.emit('2fa-removed', { panelType });
    }

    // Logout from session
    logout(sessionId) {
        const session = this.sessions.get(sessionId);
        if (session) {
            this.sessions.delete(sessionId);
            this.emit('logged-out', { sessionId, panelType: session.panelType });
            return true;
        }
        return false;
    }

    // Cleanup expired sessions
    cleanupSessions() {
        const now = new Date();
        for (const [sessionId, session] of this.sessions) {
            if (session.expiresAt <= now) {
                this.sessions.delete(sessionId);
                this.emit('session-expired', { sessionId, panelType: session.panelType });
            }
        }
    }

    // Get authentication statistics
    getAuthStats() {
        return {
            activeSessions: this.sessions.size,
            configured2FA: this.twoFactorProviders.size,
            supportedPanels: this.supportedPanels,
            sessions: Array.from(this.sessions.values()).map(session => ({
                id: session.id,
                panelType: session.panelType,
                username: session.username,
                createdAt: session.createdAt,
                expiresAt: session.expiresAt
            }))
        };
    }
}

module.exports = AuthenticationService;