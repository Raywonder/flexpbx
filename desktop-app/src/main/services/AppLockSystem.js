/**
 * FlexPBX App Lock System
 * Manages client app permissions with remote server synchronization
 */

const { BrowserWindow } = require('electron');
const crypto = require('crypto');
const Store = require('electron-store');

class AppLockSystem {
    constructor(crossPlatformSpeech) {
        this.speech = crossPlatformSpeech;
        this.store = new Store({ name: 'app-lock-config' });
        this.isLocked = false;
        this.lockType = 'none'; // 'none', 'local', 'remote', 'full'
        this.permissions = new Map();
        this.remoteServerConfig = null;
        this.flexPhoneSync = true;
        this.lockWindow = null;
        this.unlockAttempts = 0;
        this.maxAttempts = 5;
        this.lockTimeout = null;

        this.defaultPermissions = {
            // Core permissions - Admin app should have full access by default
            canAccessSettings: true,
            canModifyPBXConfig: true,
            canViewReports: true,
            canManageExtensions: true,
            canAccessSupport: true,
            canViewCalls: true,
            canRecordCalls: true,
            canTransferCalls: true,
            canAccessContacts: true,
            canModifyContacts: true,

            // Advanced permissions - Enable admin features
            canManageUsers: true,
            canViewBilling: true,
            canModifyBilling: true,
            canAccessDiagnostics: true,
            canManageBackups: true,
            canUpdateSystem: true,
            canAccessDeveloperTools: true,

            // Department-specific permissions - Full admin access
            supportTickets: {
                canView: true,
                canCreate: true,
                canAssign: true,
                canClose: true,
                canViewAll: true
            },

            // Integration permissions - Full admin access
            canUseGoogleVoice: true,
            canManageDIDs: true,
            canConfigureProviders: true,
            canAccessHoldMusic: true,
            canModifyHoldMusic: true,

            // Remote server permissions - Admin app should have full remote access
            remoteServerAccess: 'admin', // 'none', 'read', 'write', 'admin'
            canSyncWithRemote: true,
            canReceiveRemoteUpdates: true,
            canModifyRemoteSettings: true
        };

        // Temporarily disable setupLockSystem to fix app startup
        // this.setupLockSystem();
    }

    setupLockSystem() {
        // Load saved lock configuration
        this.loadLockConfiguration();

        // Setup auto-lock timeout
        this.setupAutoLock();

        // Setup remote server synchronization
        this.setupRemoteSync();

        console.log('🔒 App Lock System initialized');
    }

    async init() {
        // Additional async initialization if needed
        try {
            // Sync with remote server if configured
            if (this.remoteServerConfig) {
                await this.syncWithRemoteServer();
            }

            console.log('✅ App Lock System ready');
            return true;
        } catch (error) {
            console.error('❌ Failed to initialize App Lock System:', error);
            return false;
        }
    }

    loadLockConfiguration() {
        try {
            const config = this.store.get('lockConfig', {});
            this.lockType = config.lockType || 'none';
            this.flexPhoneSync = config.flexPhoneSync !== false;
            this.remoteServerConfig = config.remoteServerConfig || null;

            // Merge saved permissions with defaults
            this.permissions = new Map(Object.entries({
                ...this.defaultPermissions,
                ...config.permissions
            }));

            if (Object.keys(config).length === 0) {
                this.permissions = new Map(Object.entries(this.defaultPermissions));
            }
        } catch (error) {
            console.error('Failed to load lock configuration:', error);
            this.permissions = new Map(Object.entries(this.defaultPermissions));
        }
    }

    saveLockConfiguration() {
        const config = {
            lockType: this.lockType,
            flexPhoneSync: this.flexPhoneSync,
            remoteServerConfig: this.remoteServerConfig,
            permissions: Object.fromEntries(this.permissions)
        };

        this.store.set('lockConfig', config);
        console.log('💾 App lock configuration saved');
    }

    setupAutoLock() {
        // Auto-lock after inactivity (if configured)
        let lastActivity = Date.now();

        const checkInactivity = () => {
            const inactiveTime = Date.now() - lastActivity;
            const autoLockDelay = this.getAutoLockDelay();

            if (autoLockDelay > 0 && inactiveTime > autoLockDelay && !this.isLocked) {
                this.lockApp('auto-lock due to inactivity');
            }
        };

        // Track user activity
        const updateActivity = () => {
            lastActivity = Date.now();
        };

        // In main process, we'll monitor via the renderer process
        // Activity monitoring needs to be handled via IPC

        // Check inactivity every minute
        setInterval(checkInactivity, 60000);
    }

    setupRemoteSync() {
        // Check for remote server connection periodically
        setInterval(() => {
            this.syncWithRemoteServer();
        }, 30000); // Check every 30 seconds
    }

    async syncWithRemoteServer() {
        if (!this.remoteServerConfig || !this.permissions.get('canSyncWithRemote')) {
            return;
        }

        try {
            const response = await fetch(`${this.remoteServerConfig.url}/api/client-permissions`, {
                method: 'GET',
                headers: {
                    'Authorization': `Bearer ${this.remoteServerConfig.token}`,
                    'Client-ID': this.getClientId()
                }
            });

            if (response.ok) {
                const remotePermissions = await response.json();
                this.updatePermissionsFromRemote(remotePermissions);
                console.log('🔄 Synced permissions with remote server');
            }
        } catch (error) {
            console.log('⚠️ Failed to sync with remote server:', error.message);
        }
    }

    updatePermissionsFromRemote(remotePermissions) {
        // Only update if remote has higher authority
        if (remotePermissions.authority === 'admin' || remotePermissions.authority === 'server') {
            Object.entries(remotePermissions.permissions).forEach(([key, value]) => {
                this.permissions.set(key, value);
            });

            // Apply remote lock state if specified
            if (remotePermissions.lockState) {
                this.applyRemoteLockState(remotePermissions.lockState);
            }

            this.saveLockConfiguration();
            this.broadcastPermissionUpdate();

            this.speech.speak(null, 'App permissions updated from remote server');
        }
    }

    applyRemoteLockState(remoteLockState) {
        if (remoteLockState.forcelock && !this.isLocked) {
            this.lockApp('remote server lock enforcement');
        } else if (remoteLockState.forceUnlock && this.isLocked && this.lockType === 'remote') {
            this.unlockApp('remote server unlock');
        }

        if (remoteLockState.newLockType) {
            this.lockType = remoteLockState.newLockType;
        }
    }

    lockApp(reason = 'manual lock') {
        if (this.isLocked) return;

        this.isLocked = true;
        this.unlockAttempts = 0;
        console.log(`🔒 App locked: ${reason}`);

        this.showLockScreen();
        this.speech.speak(null, `Application locked due to ${reason}`);

        // Notify FlexPhone if sync is enabled
        if (this.flexPhoneSync) {
            this.notifyFlexPhone('lock');
        }

        // Send lock notification to remote server
        if (this.remoteServerConfig) {
            this.notifyRemoteServer('locked', reason);
        }
    }

    showLockScreen() {
        // Create lock screen window
        this.lockWindow = new BrowserWindow({
            width: 400,
            height: 500,
            resizable: false,
            minimizable: false,
            maximizable: false,
            alwaysOnTop: true,
            frame: false,
            webPreferences: {
                nodeIntegration: false,
                contextIsolation: true
            }
        });

        const lockScreenHTML = this.generateLockScreenHTML();
        this.lockWindow.loadURL(`data:text/html;charset=utf-8,${encodeURIComponent(lockScreenHTML)}`);

        // Handle unlock attempts
        this.lockWindow.webContents.on('ipc-message', (event, channel, data) => {
            if (channel === 'unlock-attempt') {
                this.handleUnlockAttempt(data.password, data.pin);
            }
        });

        this.lockWindow.on('closed', () => {
            if (this.isLocked) {
                // Recreate lock window if still locked
                setTimeout(() => this.showLockScreen(), 100);
            }
        });
    }

    generateLockScreenHTML() {
        return `
<!DOCTYPE html>
<html>
<head>
    <title>FlexPBX - App Locked</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, sans-serif;
            margin: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            height: 100vh;
            color: white;
        }
        .lock-container {
            text-align: center;
            background: rgba(255, 255, 255, 0.1);
            padding: 40px;
            border-radius: 20px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .lock-icon {
            font-size: 4em;
            margin-bottom: 20px;
        }
        .lock-title {
            font-size: 1.5em;
            margin-bottom: 10px;
            font-weight: 600;
        }
        .lock-message {
            margin-bottom: 30px;
            opacity: 0.9;
        }
        .unlock-form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .unlock-input {
            padding: 12px 16px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            text-align: center;
            background: rgba(255, 255, 255, 0.9);
        }
        .unlock-btn {
            padding: 12px 24px;
            background: #48bb78;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .unlock-btn:hover {
            background: #38a169;
        }
        .attempts-warning {
            margin-top: 15px;
            color: #fed7d7;
            font-size: 0.9em;
        }
        .lock-info {
            margin-top: 20px;
            font-size: 0.8em;
            opacity: 0.7;
        }
    </style>
</head>
<body>
    <div class="lock-container">
        <div class="lock-icon">🔒</div>
        <div class="lock-title">FlexPBX Locked</div>
        <div class="lock-message">Enter your unlock credentials to continue</div>

        <form class="unlock-form" id="unlock-form">
            <input type="password" class="unlock-input" placeholder="Enter password or PIN" id="unlock-password" required>
            <button type="submit" class="unlock-btn">Unlock</button>
        </form>

        <div class="attempts-warning" id="attempts-warning" style="display: none;">
            Warning: Too many failed attempts may result in additional security measures.
        </div>

        <div class="lock-info">
            Lock Type: ${this.lockType}<br>
            ${this.remoteServerConfig ? 'Connected to remote server' : 'Local mode'}
        </div>
    </div>

    <script>
        const { ipcRenderer } = require('electron');

        document.getElementById('unlock-form').addEventListener('submit', (e) => {
            e.preventDefault();
            const password = document.getElementById('unlock-password').value;
            ipcRenderer.send('unlock-attempt', { password, pin: password });
        });

        // Focus password input
        document.getElementById('unlock-password').focus();
    </script>
</body>
</html>`;
    }

    async handleUnlockAttempt(password, pin) {
        this.unlockAttempts++;

        const isValid = await this.validateUnlockCredentials(password, pin);

        if (isValid) {
            this.unlockApp('successful authentication');
        } else {
            console.log(`❌ Failed unlock attempt ${this.unlockAttempts}/${this.maxAttempts}`);

            if (this.unlockAttempts >= this.maxAttempts) {
                this.handleMaxAttemptsReached();
            } else {
                // Show warning in lock screen
                this.lockWindow?.webContents.executeJavaScript(`
                    document.getElementById('attempts-warning').style.display = 'block';
                    document.getElementById('unlock-password').value = '';
                    document.getElementById('unlock-password').style.borderColor = '#f56565';
                `);
            }
        }
    }

    async validateUnlockCredentials(password, pin) {
        // Check local PIN/password first
        const localHash = this.store.get('app-lock-hash');
        if (localHash) {
            const inputHash = crypto.createHash('sha256').update(password).digest('hex');
            if (inputHash === localHash) {
                return true;
            }
        }

        // Check remote server authentication if configured
        if (this.remoteServerConfig) {
            try {
                const response = await fetch(`${this.remoteServerConfig.url}/api/authenticate`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        clientId: this.getClientId(),
                        password: password,
                        pin: pin
                    })
                });

                return response.ok;
            } catch (error) {
                console.error('Remote authentication failed:', error);
            }
        }

        // Default PIN for demo purposes
        return password === '1234' || pin === '1234';
    }

    unlockApp(reason = 'manual unlock') {
        if (!this.isLocked) return;

        this.isLocked = false;
        this.unlockAttempts = 0;
        console.log(`🔓 App unlocked: ${reason}`);

        if (this.lockWindow) {
            this.lockWindow.close();
            this.lockWindow = null;
        }

        this.speech.speak(null, `Application unlocked due to ${reason}`);

        // Notify FlexPhone if sync is enabled
        if (this.flexPhoneSync) {
            this.notifyFlexPhone('unlock');
        }

        // Send unlock notification to remote server
        if (this.remoteServerConfig) {
            this.notifyRemoteServer('unlocked', reason);
        }
    }

    handleMaxAttemptsReached() {
        console.log('🚨 Maximum unlock attempts reached');
        this.speech.speak(null, 'Maximum unlock attempts reached. Additional security measures activated.');

        // Extended lock period
        if (this.lockTimeout) {
            clearTimeout(this.lockTimeout);
        }

        this.lockTimeout = setTimeout(() => {
            this.unlockAttempts = 0;
        }, 5 * 60 * 1000); // 5 minute timeout

        // Notify remote server of security event
        if (this.remoteServerConfig) {
            this.notifyRemoteServer('security-event', 'max-unlock-attempts-reached');
        }
    }

    async notifyFlexPhone(action) {
        try {
            // Send lock/unlock state to FlexPhone via IPC or network
            const message = {
                type: 'app-lock-state',
                action: action,
                permissions: Object.fromEntries(this.permissions),
                timestamp: new Date().toISOString()
            };

            // This would integrate with FlexPhone's communication channel
            console.log(`📱 FlexPhone notification: ${action}`, message);
        } catch (error) {
            console.error('Failed to notify FlexPhone:', error);
        }
    }

    async notifyRemoteServer(state, reason) {
        if (!this.remoteServerConfig) return;

        try {
            await fetch(`${this.remoteServerConfig.url}/api/client-state`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${this.remoteServerConfig.token}`
                },
                body: JSON.stringify({
                    clientId: this.getClientId(),
                    state: state,
                    reason: reason,
                    timestamp: new Date().toISOString(),
                    permissions: Object.fromEntries(this.permissions)
                })
            });
        } catch (error) {
            console.error('Failed to notify remote server:', error);
        }
    }

    // Permission checking methods
    hasPermission(permission) {
        return this.permissions.get(permission) === true;
    }

    getSupportTicketPermissions() {
        return this.permissions.get('supportTickets') || {};
    }

    canAccessFeature(feature) {
        const featurePermissions = {
            'settings': 'canAccessSettings',
            'pbx-config': 'canModifyPBXConfig',
            'reports': 'canViewReports',
            'extensions': 'canManageExtensions',
            'support': 'canAccessSupport',
            'calls': 'canViewCalls',
            'recording': 'canRecordCalls',
            'transfer': 'canTransferCalls',
            'contacts': 'canAccessContacts',
            'users': 'canManageUsers',
            'billing': 'canViewBilling',
            'diagnostics': 'canAccessDiagnostics',
            'backups': 'canManageBackups',
            'updates': 'canUpdateSystem',
            'developer': 'canAccessDeveloperTools',
            'google-voice': 'canUseGoogleVoice',
            'dids': 'canManageDIDs',
            'providers': 'canConfigureProviders',
            'hold-music': 'canAccessHoldMusic'
        };

        const permission = featurePermissions[feature];
        return permission ? this.hasPermission(permission) : false;
    }

    // Configuration methods
    setRemoteServer(serverConfig) {
        this.remoteServerConfig = serverConfig;
        this.saveLockConfiguration();
        this.syncWithRemoteServer();
        console.log('🌐 Remote server configured:', serverConfig.url);
    }

    setLockType(type) {
        this.lockType = type;
        this.saveLockConfiguration();
        console.log(`🔒 Lock type changed to: ${type}`);
    }

    setFlexPhoneSync(enabled) {
        this.flexPhoneSync = enabled;
        this.saveLockConfiguration();
        console.log(`📱 FlexPhone sync ${enabled ? 'enabled' : 'disabled'}`);
    }

    updatePermission(permission, value) {
        this.permissions.set(permission, value);
        this.saveLockConfiguration();
        this.broadcastPermissionUpdate();
        console.log(`🔧 Permission updated: ${permission} = ${value}`);
    }

    broadcastPermissionUpdate() {
        // Notify other parts of the application about permission changes
        if (typeof window !== 'undefined' && window.electronAPI) {
            window.electronAPI.send('permissions-updated', Object.fromEntries(this.permissions));
        }
    }

    getAutoLockDelay() {
        // Get auto-lock delay from settings (in milliseconds)
        const setting = this.store.get('auto-lock-delay', 0);
        return typeof setting === 'number' ? setting : parseInt(setting) || 0; // 0 = disabled
    }

    getClientId() {
        let clientId = this.store.get('client-id');
        if (!clientId) {
            clientId = crypto.randomBytes(16).toString('hex');
            this.store.set('client-id', clientId);
        }
        return clientId;
    }

    // Public API methods
    getSystemInfo() {
        return {
            isLocked: this.isLocked,
            lockType: this.lockType,
            flexPhoneSync: this.flexPhoneSync,
            remoteServerConnected: !!this.remoteServerConfig,
            permissionsCount: this.permissions.size,
            unlockAttempts: this.unlockAttempts,
            maxAttempts: this.maxAttempts
        };
    }

    getAllPermissions() {
        return Object.fromEntries(this.permissions);
    }

    exportConfiguration() {
        return {
            lockType: this.lockType,
            flexPhoneSync: this.flexPhoneSync,
            permissions: Object.fromEntries(this.permissions),
            clientId: this.getClientId()
        };
    }
}

module.exports = AppLockSystem;