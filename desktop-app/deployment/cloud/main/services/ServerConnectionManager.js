const { NodeSSH } = require('node-ssh');
const axios = require('axios');
const WebSocket = require('ws');
const fs = require('fs-extra');
const path = require('path');
const { EventEmitter } = require('events');

class ServerConnectionManager extends EventEmitter {
    constructor() {
        super();
        this.connections = new Map();
        this.activeServer = null;
        this.websocketConnection = null;
        this.apiClient = null;
    }

    async connectToServer(serverConfig) {
        const {
            id,
            host,
            port = 22,
            username,
            password,
            privateKeyPath,
            apiPort = 3000,
            apiProtocol = 'http',
            webSocketPort = 3000
        } = serverConfig;

        try {
            // Test SSH connection
            const ssh = new NodeSSH();
            await ssh.connect({
                host,
                port,
                username,
                password,
                privateKey: privateKeyPath ? await fs.readFile(privateKeyPath, 'utf8') : undefined
            });

            // Detect server environment
            const serverInfo = await this.detectServerEnvironment(ssh);

            // Test API connection
            const apiUrl = `${apiProtocol}://${host}:${apiPort}`;
            const apiClient = axios.create({
                baseURL: apiUrl,
                timeout: 10000
            });

            // Test API health
            await apiClient.get('/health');

            // Establish WebSocket connection
            const wsUrl = `ws://${host}:${webSocketPort}/ws`;
            const ws = new WebSocket(wsUrl);

            const connection = {
                id,
                ssh,
                apiClient,
                websocket: ws,
                serverInfo,
                config: serverConfig,
                status: 'connected',
                lastConnected: new Date()
            };

            this.connections.set(id, connection);
            this.activeServer = connection;

            // Setup WebSocket event handlers
            this.setupWebSocketHandlers(ws, id);

            this.emit('server-connected', { serverId: id, serverInfo });

            return {
                success: true,
                serverId: id,
                serverInfo
            };

        } catch (error) {
            this.emit('connection-error', { serverId: id, error: error.message });
            throw error;
        }
    }

    async detectServerEnvironment(ssh) {
        const info = {
            os: null,
            distro: null,
            controlPanels: {
                cpanel: false,
                whm: false,
                whmcs: false,
                plesk: false,
                directadmin: false
            },
            services: {
                asterisk: false,
                freepbx: false,
                nginx: false,
                apache: false,
                docker: false,
                mysql: false,
                postgresql: false
            },
            flexpbx: {
                installed: false,
                version: null,
                installPath: null
            }
        };

        try {
            // Detect OS
            const osInfo = await ssh.execCommand('cat /etc/os-release');
            if (osInfo.stdout) {
                const lines = osInfo.stdout.split('\n');
                for (const line of lines) {
                    if (line.startsWith('ID=')) {
                        info.distro = line.split('=')[1].replace(/"/g, '');
                    }
                    if (line.startsWith('PRETTY_NAME=')) {
                        info.os = line.split('=')[1].replace(/"/g, '');
                    }
                }
            }

            // Check control panels
            const controlPanelChecks = [
                { name: 'cpanel', command: 'which /usr/local/cpanel/cpanel' },
                { name: 'whm', command: 'which /usr/local/cpanel/whostmgr' },
                { name: 'whmcs', command: 'find /home -name "configuration.php" -path "*/whmcs/*" 2>/dev/null | head -1' },
                { name: 'plesk', command: 'which plesk' },
                { name: 'directadmin', command: 'which /usr/local/directadmin/directadmin' }
            ];

            for (const check of controlPanelChecks) {
                const result = await ssh.execCommand(check.command);
                info.controlPanels[check.name] = !!result.stdout.trim();
            }

            // Check services
            const serviceChecks = [
                { name: 'asterisk', command: 'systemctl is-active asterisk 2>/dev/null || service asterisk status 2>/dev/null' },
                { name: 'freepbx', command: 'ls /var/www/html/admin/config.php 2>/dev/null' },
                { name: 'nginx', command: 'systemctl is-active nginx 2>/dev/null || service nginx status 2>/dev/null' },
                { name: 'apache', command: 'systemctl is-active apache2 2>/dev/null || systemctl is-active httpd 2>/dev/null' },
                { name: 'docker', command: 'which docker' },
                { name: 'mysql', command: 'systemctl is-active mysql 2>/dev/null || systemctl is-active mariadb 2>/dev/null' },
                { name: 'postgresql', command: 'systemctl is-active postgresql 2>/dev/null' }
            ];

            for (const check of serviceChecks) {
                const result = await ssh.execCommand(check.command);
                info.services[check.name] = result.code === 0;
            }

            // Check FlexPBX installation
            const flexpbxCheck = await ssh.execCommand('ls /opt/flexpbx/package.json 2>/dev/null');
            if (flexpbxCheck.code === 0) {
                info.flexpbx.installed = true;
                info.flexpbx.installPath = '/opt/flexpbx';

                const versionCheck = await ssh.execCommand('cd /opt/flexpbx && node -p "require(\\"./package.json\\").version" 2>/dev/null');
                if (versionCheck.stdout) {
                    info.flexpbx.version = versionCheck.stdout.trim();
                }
            }

        } catch (error) {
            console.warn('Error detecting server environment:', error.message);
        }

        return info;
    }

    setupWebSocketHandlers(ws, serverId) {
        ws.on('open', () => {
            console.log(`WebSocket connected to server ${serverId}`);
            this.emit('websocket-connected', { serverId });
        });

        ws.on('message', (data) => {
            try {
                const message = JSON.parse(data);
                this.handleWebSocketMessage(serverId, message);
            } catch (error) {
                console.error('Error parsing WebSocket message:', error);
            }
        });

        ws.on('close', () => {
            console.log(`WebSocket disconnected from server ${serverId}`);
            this.emit('websocket-disconnected', { serverId });
        });

        ws.on('error', (error) => {
            console.error(`WebSocket error for server ${serverId}:`, error);
            this.emit('websocket-error', { serverId, error: error.message });
        });
    }

    handleWebSocketMessage(serverId, message) {
        switch (message.type) {
            case 'asterisk_event':
                this.emit('asterisk-event', { serverId, event: message.event });
                break;
            case 'system_status':
                this.emit('system-status', { serverId, status: message.status });
                break;
            case 'call_event':
                this.emit('call-event', { serverId, call: message.call });
                break;
            case 'plugin_update':
                this.emit('plugin-update', { serverId, plugin: message.plugin });
                break;
            default:
                this.emit('server-message', { serverId, message });
        }
    }

    // PBX Management Methods
    async getExtensions(serverId = null) {
        const server = this.getServerConnection(serverId);
        if (!server) throw new Error('No server connection');

        const response = await server.apiClient.get('/api/extensions');
        return response.data;
    }

    async createExtension(extensionData, serverId = null) {
        const server = this.getServerConnection(serverId);
        if (!server) throw new Error('No server connection');

        const response = await server.apiClient.post('/api/extensions', extensionData);
        return response.data;
    }

    async updateExtension(extensionId, updateData, serverId = null) {
        const server = this.getServerConnection(serverId);
        if (!server) throw new Error('No server connection');

        const response = await server.apiClient.put(`/api/extensions/${extensionId}`, updateData);
        return response.data;
    }

    async deleteExtension(extensionId, serverId = null) {
        const server = this.getServerConnection(serverId);
        if (!server) throw new Error('No server connection');

        const response = await server.apiClient.delete(`/api/extensions/${extensionId}`);
        return response.data;
    }

    // IVR Management
    async getIVRs(serverId = null) {
        const server = this.getServerConnection(serverId);
        if (!server) throw new Error('No server connection');

        const response = await server.apiClient.get('/api/ivr');
        return response.data;
    }

    async createIVR(ivrData, serverId = null) {
        const server = this.getServerConnection(serverId);
        if (!server) throw new Error('No server connection');

        const response = await server.apiClient.post('/api/ivr', ivrData);
        return response.data;
    }

    async updateIVR(ivrId, updateData, serverId = null) {
        const server = this.getServerConnection(serverId);
        if (!server) throw new Error('No server connection');

        const response = await server.apiClient.put(`/api/ivr/${ivrId}`, updateData);
        return response.data;
    }

    // Music on Hold Management
    async getMOHClasses(serverId = null) {
        const server = this.getServerConnection(serverId);
        if (!server) throw new Error('No server connection');

        const response = await server.apiClient.get('/api/moh');
        return response.data;
    }

    async createMOHClass(mohData, serverId = null) {
        const server = this.getServerConnection(serverId);
        if (!server) throw new Error('No server connection');

        const response = await server.apiClient.post('/api/moh', mohData);
        return response.data;
    }

    async uploadMOHFile(classId, audioFile, serverId = null) {
        const server = this.getServerConnection(serverId);
        if (!server) throw new Error('No server connection');

        // Upload audio file via SSH
        const remotePath = `/var/lib/asterisk/moh/${classId}/`;
        await server.ssh.execCommand(`mkdir -p ${remotePath}`);
        await server.ssh.putFile(audioFile, `${remotePath}${path.basename(audioFile)}`);

        return { success: true, file: path.basename(audioFile) };
    }

    // Plugin and Module Management
    async getAvailablePlugins(serverId = null) {
        const server = this.getServerConnection(serverId);
        if (!server) throw new Error('No server connection');

        const response = await server.apiClient.get('/api/plugins/available');
        return response.data;
    }

    async getInstalledPlugins(serverId = null) {
        const server = this.getServerConnection(serverId);
        if (!server) throw new Error('No server connection');

        const response = await server.apiClient.get('/api/plugins/installed');
        return response.data;
    }

    async installPlugin(pluginId, serverId = null) {
        const server = this.getServerConnection(serverId);
        if (!server) throw new Error('No server connection');

        const response = await server.apiClient.post(`/api/plugins/${pluginId}/install`);
        return response.data;
    }

    async uninstallPlugin(pluginId, serverId = null) {
        const server = this.getServerConnection(serverId);
        if (!server) throw new Error('No server connection');

        const response = await server.apiClient.delete(`/api/plugins/${pluginId}`);
        return response.data;
    }

    async configurePlugin(pluginId, config, serverId = null) {
        const server = this.getServerConnection(serverId);
        if (!server) throw new Error('No server connection');

        const response = await server.apiClient.put(`/api/plugins/${pluginId}/config`, config);
        return response.data;
    }

    // Control Panel Integration
    async setupCPanelIntegration(config, serverId = null) {
        const server = this.getServerConnection(serverId);
        if (!server) throw new Error('No server connection');

        if (!server.serverInfo.controlPanels.cpanel) {
            throw new Error('cPanel not detected on server');
        }

        // Create cPanel integration script
        const integrationScript = this.generateCPanelIntegrationScript(config);

        await server.ssh.execCommand('mkdir -p /opt/flexpbx/integrations/cpanel');
        await server.ssh.putFile(
            Buffer.from(integrationScript),
            '/opt/flexpbx/integrations/cpanel/hook.sh'
        );
        await server.ssh.execCommand('chmod +x /opt/flexpbx/integrations/cpanel/hook.sh');

        // Register with cPanel
        await server.ssh.execCommand('/opt/flexpbx/integrations/cpanel/hook.sh install');

        return { success: true, message: 'cPanel integration configured' };
    }

    async setupWHMCSIntegration(config, serverId = null) {
        const server = this.getServerConnection(serverId);
        if (!server) throw new Error('No server connection');

        if (!server.serverInfo.controlPanels.whmcs) {
            throw new Error('WHMCS not detected on server');
        }

        // Find WHMCS installation path
        const whmcsPath = await server.ssh.execCommand(
            'find /home -name "configuration.php" -path "*/whmcs/*" 2>/dev/null | head -1'
        );

        if (!whmcsPath.stdout) {
            throw new Error('WHMCS installation not found');
        }

        const whmcsDir = path.dirname(whmcsPath.stdout.trim());
        const moduleDir = path.join(whmcsDir, 'modules/servers/flexpbx');

        // Create WHMCS module
        await server.ssh.execCommand(`mkdir -p ${moduleDir}`);

        const moduleContent = this.generateWHMCSModule();
        await server.ssh.putFile(
            Buffer.from(moduleContent),
            path.join(moduleDir, 'flexpbx.php')
        );

        // Create hooks
        const hooksDir = path.join(whmcsDir, 'includes/hooks');
        const hookContent = this.generateWHMCSHooks();
        await server.ssh.putFile(
            Buffer.from(hookContent),
            path.join(hooksDir, 'flexpbx_hooks.php')
        );

        return {
            success: true,
            message: 'WHMCS integration configured',
            modulePath: moduleDir
        };
    }

    // Server App Building and Deployment
    async deployServerApp(appConfig, serverId = null) {
        const server = this.getServerConnection(serverId);
        if (!server) throw new Error('No server connection');

        const { appName, appType, files, config } = appConfig;

        // Create app directory
        const appPath = `/opt/flexpbx/apps/${appName}`;
        await server.ssh.execCommand(`mkdir -p ${appPath}`);

        // Upload app files
        for (const file of files) {
            const remotePath = path.join(appPath, path.basename(file));
            await server.ssh.putFile(file, remotePath);
        }

        // Create app configuration
        await server.ssh.putFile(
            Buffer.from(JSON.stringify(config, null, 2)),
            path.join(appPath, 'config.json')
        );

        // Register app with FlexPBX
        const response = await server.apiClient.post('/api/apps/register', {
            name: appName,
            type: appType,
            path: appPath,
            config
        });

        return response.data;
    }

    // Nginx Configuration Management
    async configureNginxForDomain(domainConfig, serverId = null) {
        const server = this.getServerConnection(serverId);
        if (!server) throw new Error('No server connection');

        const { domain, subdomain, path: sitePath, sslEnabled, userAccount } = domainConfig;

        let nginxConfig;

        if (server.serverInfo.controlPanels.cpanel) {
            nginxConfig = this.generateCPanelNginxConfig(domainConfig);
        } else {
            nginxConfig = this.generateStandaloneNginxConfig(domainConfig);
        }

        const configName = `${domain.replace(/\./g, '-')}.conf`;
        const configPath = `/etc/nginx/conf.d/${configName}`;

        // Create nginx configuration
        await server.ssh.putFile(
            Buffer.from(nginxConfig),
            configPath
        );

        // Test and reload nginx
        const testResult = await server.ssh.execCommand('nginx -t');
        if (testResult.code !== 0) {
            throw new Error(`Nginx configuration test failed: ${testResult.stderr}`);
        }

        await server.ssh.execCommand('systemctl reload nginx');

        return {
            success: true,
            domain,
            configPath,
            message: 'Nginx configuration updated'
        };
    }

    // Helper methods
    generateCPanelIntegrationScript(config) {
        return `#!/bin/bash
# FlexPBX cPanel Integration Script

CPANEL_USER="${config.cpanelUser}"
DOMAIN="${config.domain}"
FLEXPBX_PATH="/opt/flexpbx"

case "$1" in
    install)
        echo "Installing FlexPBX cPanel integration..."

        # Create cPanel app configuration
        cat > /var/cpanel/apps/flexpbx.conf << EOF
name=FlexPBX
version=1.0.0
vendor=FlexPBX Team
url=http://localhost:3000
icon=flexpbx.png
EOF

        # Register with WHM
        /usr/local/cpanel/bin/register_appconfig /var/cpanel/apps/flexpbx.conf

        echo "Integration installed successfully"
        ;;
    uninstall)
        echo "Removing FlexPBX cPanel integration..."
        rm -f /var/cpanel/apps/flexpbx.conf
        echo "Integration removed"
        ;;
    *)
        echo "Usage: $0 {install|uninstall}"
        exit 1
        ;;
esac`;
    }

    generateWHMCSModule() {
        return `<?php
/**
 * FlexPBX WHMCS Server Module
 */

function flexpbx_ConfigOptions() {
    return [
        "Extensions" => [
            "Type" => "text",
            "Size" => "5",
            "Default" => "10",
            "Description" => "Number of Extensions"
        ],
        "Storage" => [
            "Type" => "text",
            "Size" => "5",
            "Default" => "100",
            "Description" => "Storage Space (GB)"
        ],
        "Features" => [
            "Type" => "dropdown",
            "Options" => "Basic,Professional,Enterprise",
            "Default" => "Basic"
        ]
    ];
}

function flexpbx_CreateAccount($params) {
    $api = new FlexPBXAPI($params);
    return $api->createAccount([
        'domain' => $params['domain'],
        'username' => $params['username'],
        'password' => $params['password'],
        'extensions' => $params['configoption1'],
        'storage' => $params['configoption2'],
        'features' => $params['configoption3']
    ]);
}

function flexpbx_SuspendAccount($params) {
    $api = new FlexPBXAPI($params);
    return $api->suspendAccount($params['domain']);
}

function flexpbx_UnsuspendAccount($params) {
    $api = new FlexPBXAPI($params);
    return $api->unsuspendAccount($params['domain']);
}

function flexpbx_TerminateAccount($params) {
    $api = new FlexPBXAPI($params);
    return $api->deleteAccount($params['domain']);
}

function flexpbx_ClientArea($params) {
    return [
        'templatefile' => 'clientarea',
        'vars' => [
            'pbx_url' => 'https://' . $params['domain'],
            'username' => $params['username'],
            'extensions' => $params['configoption1']
        ]
    ];
}

class FlexPBXAPI {
    private $serverip;
    private $username;
    private $password;

    public function __construct($params) {
        $this->serverip = $params['serverip'];
        $this->username = $params['serverusername'];
        $this->password = $params['serverpassword'];
    }

    public function createAccount($data) {
        return $this->apiCall('POST', '/api/accounts', $data);
    }

    public function suspendAccount($domain) {
        return $this->apiCall('POST', "/api/accounts/{$domain}/suspend");
    }

    public function unsuspendAccount($domain) {
        return $this->apiCall('POST', "/api/accounts/{$domain}/unsuspend");
    }

    public function deleteAccount($domain) {
        return $this->apiCall('DELETE', "/api/accounts/{$domain}");
    }

    private function apiCall($method, $endpoint, $data = []) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "http://{$this->serverip}:3000{$endpoint}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode("{$this->username}:{$this->password}")
        ]);

        if (!empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            return 'success';
        } else {
            return "Error: " . $response;
        }
    }
}`;
    }

    generateWHMCSHooks() {
        return `<?php
/**
 * FlexPBX WHMCS Hooks
 */

add_hook('ClientAdd', 1, function($vars) {
    // Auto-create PBX account when client is added
    logActivity("FlexPBX: New client registered - " . $vars['email']);
});

add_hook('AfterModuleCreate', 1, function($vars) {
    if ($vars['producttype'] == 'flexpbx') {
        // Send welcome email with PBX details
        logActivity("FlexPBX: Account created for " . $vars['domain']);
    }
});

add_hook('AfterModuleSuspend', 1, function($vars) {
    if ($vars['producttype'] == 'flexpbx') {
        logActivity("FlexPBX: Account suspended for " . $vars['domain']);
    }
});

add_hook('AfterModuleTerminate', 1, function($vars) {
    if ($vars['producttype'] == 'flexpbx') {
        logActivity("FlexPBX: Account terminated for " . $vars['domain']);
    }
});`;
    }

    generateCPanelNginxConfig(config) {
        const { domain, subdomain, path, sslEnabled, userAccount } = config;
        const serverName = subdomain ? `${subdomain}.${domain}` : domain;

        return `# FlexPBX cPanel Configuration for ${serverName}
upstream flexpbx_${userAccount} {
    server 127.0.0.1:3000;
}

server {
    listen 80;
    ${sslEnabled ? 'listen 443 ssl http2;' : ''}
    server_name ${serverName};

    ${sslEnabled ? `
    ssl_certificate /var/cpanel/ssl/apache_tls/${domain}/combined;
    ssl_certificate_key /var/cpanel/ssl/apache_tls/${domain}/combined;
    ` : ''}

    location / {
        proxy_pass http://flexpbx_${userAccount};
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    location /ws {
        proxy_pass http://flexpbx_${userAccount}/ws;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
    }

    # cPanel integration
    location /cpanel {
        proxy_pass https://127.0.0.1:2083;
        proxy_ssl_verify off;
    }
}`;
    }

    generateStandaloneNginxConfig(config) {
        const { domain, subdomain, path, sslEnabled } = config;
        const serverName = subdomain ? `${subdomain}.${domain}` : domain;

        return `# FlexPBX Standalone Configuration for ${serverName}
upstream flexpbx_backend {
    server 127.0.0.1:3000;
}

server {
    listen 80;
    ${sslEnabled ? 'listen 443 ssl http2;' : ''}
    server_name ${serverName};

    ${sslEnabled ? `
    ssl_certificate /etc/letsencrypt/live/${serverName}/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/${serverName}/privkey.pem;
    ` : ''}

    location / {
        proxy_pass http://flexpbx_backend;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_cache_bypass $http_upgrade;
    }

    location /ws {
        proxy_pass http://flexpbx_backend/ws;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
    }
}`;
    }

    getServerConnection(serverId = null) {
        if (serverId) {
            return this.connections.get(serverId);
        }
        return this.activeServer;
    }

    async disconnectFromServer(serverId) {
        const connection = this.connections.get(serverId);
        if (connection) {
            if (connection.ssh) {
                connection.ssh.dispose();
            }
            if (connection.websocket) {
                connection.websocket.close();
            }
            this.connections.delete(serverId);

            if (this.activeServer && this.activeServer.id === serverId) {
                this.activeServer = null;
            }

            this.emit('server-disconnected', { serverId });
        }
    }

    async disconnectAll() {
        for (const [serverId] of this.connections) {
            await this.disconnectFromServer(serverId);
        }
    }

    getConnectedServers() {
        return Array.from(this.connections.values()).map(conn => ({
            id: conn.id,
            config: conn.config,
            serverInfo: conn.serverInfo,
            status: conn.status,
            lastConnected: conn.lastConnected
        }));
    }

    isConnected(serverId = null) {
        const server = this.getServerConnection(serverId);
        return server && server.status === 'connected';
    }
}

module.exports = ServerConnectionManager;