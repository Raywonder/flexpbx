const { contextBridge, ipcRenderer } = require('electron');

// Expose protected methods that allow the renderer process to use
// the ipcRenderer without exposing the entire object
contextBridge.exposeInMainWorld('electronAPI', {
    // System information
    getSystemInfo: () => ipcRenderer.invoke('get-system-info'),

    // Store operations
    storeGet: (key) => ipcRenderer.invoke('store-get', key),
    storeSet: (key, value) => ipcRenderer.invoke('store-set', key, value),

    // Docker operations
    dockerCheck: () => ipcRenderer.invoke('docker-check'),
    dockerInstallLocal: (config) => ipcRenderer.invoke('docker-install-local', config),
    dockerStatus: (installPath) => ipcRenderer.invoke('docker-status', installPath),
    dockerStart: (installPath) => ipcRenderer.invoke('docker-start', installPath),
    dockerStop: (installPath) => ipcRenderer.invoke('docker-stop', installPath),
    dockerLogs: (installPath) => ipcRenderer.invoke('docker-logs', installPath),

    // Remote deployment
    deployRemote: (config) => ipcRenderer.invoke('deploy-remote', config),
    testConnection: (connectionConfig) => ipcRenderer.invoke('test-connection', connectionConfig),

    // Nginx operations
    nginxConfigure: (config) => ipcRenderer.invoke('nginx-configure', config),
    nginxTest: (configPath) => ipcRenderer.invoke('nginx-test', configPath),
    nginxReload: () => ipcRenderer.invoke('nginx-reload'),

    // DNS operations
    dnsCreateRecord: (config) => ipcRenderer.invoke('dns-create-record', config),
    dnsVerifyRecord: (hostname, expectedIp) => ipcRenderer.invoke('dns-verify-record', hostname, expectedIp),
    dnsGetPublicIP: () => ipcRenderer.invoke('dns-get-public-ip'),
    dnsTestResolution: (hostname) => ipcRenderer.invoke('dns-test-resolution', hostname),
    dnsGetProviders: () => ipcRenderer.invoke('dns-get-providers'),
    dnsGetProviderSchema: (provider) => ipcRenderer.invoke('dns-get-provider-schema', provider),

    // File operations
    selectDirectory: () => ipcRenderer.invoke('select-directory'),
    selectFile: (options) => ipcRenderer.invoke('select-file', options),

    // External operations
    openExternal: (url) => ipcRenderer.invoke('open-external', url),
    showMessage: (options) => ipcRenderer.invoke('show-message', options),

    // Event listeners
    onSystemRequirements: (callback) => ipcRenderer.on('system-requirements', callback),
    onOpenPreferences: (callback) => ipcRenderer.on('open-preferences', callback),
    onNewLocalInstall: (callback) => ipcRenderer.on('new-local-install', callback),
    onDeployRemote: (callback) => ipcRenderer.on('deploy-remote', callback),
    onConnectServer: (callback) => ipcRenderer.on('connect-server', callback),
    onShowServerStatus: (callback) => ipcRenderer.on('show-server-status', callback),
    onViewLogs: (callback) => ipcRenderer.on('view-logs', callback),
    onConfigureNginx: (callback) => ipcRenderer.on('configure-nginx', callback),
    onManageSSL: (callback) => ipcRenderer.on('manage-ssl', callback),
    onConfigureFirewall: (callback) => ipcRenderer.on('configure-firewall', callback),
    onBackupRestore: (callback) => ipcRenderer.on('backup-restore', callback),
    onCheckUpdates: (callback) => ipcRenderer.on('check-updates', callback),

    // Remove listeners
    removeAllListeners: (channel) => ipcRenderer.removeAllListeners(channel)
});