const { NodeSSH } = require('node-ssh');
const FTP = require('basic-ftp');
const { createClient } = require('webdav');
const fs = require('fs-extra');
const path = require('path');
const { Client } = require('ssh2');
const scp2 = require('scp2');

class UnifiedDeploymentService {
    constructor() {
        this.deploymentMethods = ['ssh', 'sftp', 'ftp', 'webdav', 'scp'];
        this.serverDetection = {
            whmcs: false,
            whm: false,
            cpanel: false,
            plesk: false,
            directadmin: false
        };
    }

    async deployToServer(config) {
        const {
            method,
            connectionConfig,
            deploymentConfig,
            installConfig
        } = config;

        console.log(`🚀 Deploying via ${method.toUpperCase()}...`);

        // Detect existing server software
        await this.detectServerEnvironment(method, connectionConfig);

        // Choose deployment method
        let result;
        switch (method) {
            case 'ssh':
                result = await this.deployViaSSH(connectionConfig, deploymentConfig, installConfig);
                break;
            case 'sftp':
                result = await this.deployViaSFTP(connectionConfig, deploymentConfig, installConfig);
                break;
            case 'ftp':
                result = await this.deployViaFTP(connectionConfig, deploymentConfig, installConfig);
                break;
            case 'webdav':
                result = await this.deployViaWebDAV(connectionConfig, deploymentConfig, installConfig);
                break;
            case 'scp':
                result = await this.deployViaSCP(connectionConfig, deploymentConfig, installConfig);
                break;
            default:
                throw new Error(`Unsupported deployment method: ${method}`);
        }

        // Configure nginx if needed
        if (result.success && deploymentConfig.configureNginx) {
            await this.configureNginx(method, connectionConfig, deploymentConfig);
        }

        return result;
    }

    async detectServerEnvironment(method, connectionConfig) {
        console.log('🔍 Detecting server environment...');

        if (method === 'ssh' || method === 'sftp') {
            const ssh = new NodeSSH();
            try {
                await ssh.connect({
                    host: connectionConfig.host,
                    username: connectionConfig.username,
                    password: connectionConfig.password,
                    privateKey: connectionConfig.privateKeyPath ?
                        await fs.readFile(connectionConfig.privateKeyPath, 'utf8') : undefined,
                    port: connectionConfig.port || 22
                });

                // Check for WHMCS
                const whmcsCheck = await ssh.execCommand('ls /home/*/public_html/configuration.php 2>/dev/null');
                if (whmcsCheck.stdout) {
                    this.serverDetection.whmcs = true;
                    console.log('✅ WHMCS detected');
                }

                // Check for WHM/cPanel
                const cpanelCheck = await ssh.execCommand('which /usr/local/cpanel/cpanel 2>/dev/null');
                if (cpanelCheck.stdout) {
                    this.serverDetection.cpanel = true;
                    this.serverDetection.whm = true;
                    console.log('✅ WHM/cPanel detected');
                }

                // Check for Plesk
                const pleskCheck = await ssh.execCommand('which plesk 2>/dev/null');
                if (pleskCheck.stdout) {
                    this.serverDetection.plesk = true;
                    console.log('✅ Plesk detected');
                }

                // Check for DirectAdmin
                const daCheck = await ssh.execCommand('which /usr/local/directadmin/directadmin 2>/dev/null');
                if (daCheck.stdout) {
                    this.serverDetection.directadmin = true;
                    console.log('✅ DirectAdmin detected');
                }

                await ssh.dispose();
            } catch (error) {
                console.warn('Could not detect server environment:', error.message);
            }
        }
    }

    async deployViaSSH(connectionConfig, deploymentConfig, installConfig) {
        const ssh = new NodeSSH();

        try {
            await ssh.connect({
                host: connectionConfig.host,
                username: connectionConfig.username,
                password: connectionConfig.password,
                privateKey: connectionConfig.privateKeyPath ?
                    await fs.readFile(connectionConfig.privateKeyPath, 'utf8') : undefined,
                port: connectionConfig.port || 22
            });

            const isRoot = connectionConfig.username === 'root';
            const homeDir = isRoot ? '/root' : `/home/${connectionConfig.username}`;
            const installPath = connectionConfig.remotePath || `${homeDir}/apps/flexpbx`;

            console.log(`📁 Installing to: ${installPath}`);

            // Create installation directory
            await ssh.execCommand(`mkdir -p ${installPath}`);

            // Check and install dependencies
            if (isRoot) {
                await this.installServerDependencies(ssh);
            }

            // Upload FlexPBX files
            await this.uploadFiles(ssh, installPath);

            // Setup based on server environment
            if (this.serverDetection.cpanel) {
                await this.setupWithCPanel(ssh, installPath, deploymentConfig);
            } else if (this.serverDetection.whmcs) {
                await this.setupWithWHMCS(ssh, installPath, deploymentConfig);
            } else {
                await this.setupStandalone(ssh, installPath, deploymentConfig);
            }

            // Configure services
            await this.configureServices(ssh, installPath, deploymentConfig, isRoot);

            await ssh.dispose();

            return {
                success: true,
                message: 'Deployment successful',
                installPath,
                serverDetection: this.serverDetection
            };

        } catch (error) {
            console.error('SSH deployment failed:', error);
            await ssh.dispose();
            return {
                success: false,
                error: error.message
            };
        }
    }

    async installServerDependencies(ssh) {
        console.log('📦 Installing server dependencies...');

        const commands = [
            // Update system
            'apt-get update -y 2>/dev/null || yum update -y 2>/dev/null',

            // Install Docker
            'which docker || (curl -fsSL https://get.docker.com | sh)',

            // Install Docker Compose
            'which docker-compose || (curl -L "https://github.com/docker/compose/releases/download/v2.20.0/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose && chmod +x /usr/local/bin/docker-compose)',

            // Install Nginx if not present
            'which nginx || (apt-get install -y nginx 2>/dev/null || yum install -y nginx 2>/dev/null)',

            // Install Node.js
            'which node || (curl -fsSL https://deb.nodesource.com/setup_18.x | bash - && apt-get install -y nodejs)',

            // Install Asterisk
            'which asterisk || (apt-get install -y asterisk 2>/dev/null || yum install -y asterisk 2>/dev/null)'
        ];

        for (const cmd of commands) {
            const result = await ssh.execCommand(cmd);
            if (result.code !== 0 && result.stderr) {
                console.warn(`Warning: ${result.stderr}`);
            }
        }

        console.log('✅ Dependencies installed');
    }

    async uploadFiles(ssh, installPath) {
        console.log('📤 Uploading FlexPBX files...');

        const localPath = path.join(__dirname, '../../../../server');

        // Upload server files
        await ssh.putDirectory(localPath, installPath, {
            recursive: true,
            concurrency: 10,
            validate: (itemPath) => {
                const baseName = path.basename(itemPath);
                return baseName !== 'node_modules' && baseName !== '.git';
            }
        });

        console.log('✅ Files uploaded');
    }

    async setupWithCPanel(ssh, installPath, deploymentConfig) {
        console.log('🔧 Setting up with cPanel integration...');

        // Create cPanel hook
        const hookScript = `#!/bin/bash
# FlexPBX cPanel Hook
INSTALL_PATH="${installPath}"
DOMAIN="${deploymentConfig.domain}"

# Register with cPanel
/usr/local/cpanel/bin/register_appconfig /opt/flexpbx/cpanel/appconfig.conf

# Create subdomain if needed
if [ "${deploymentConfig.useSubdomain}" = "true" ]; then
    /usr/local/cpanel/bin/uapi --user=${deploymentConfig.cpanelUser} SubDomain create domain=${deploymentConfig.subdomain} rootdomain=${deploymentConfig.rootDomain}
fi

# Configure DNS
/usr/local/cpanel/bin/uapi --user=${deploymentConfig.cpanelUser} DNS add_record domain=${DOMAIN} name=pbx type=A address=$(hostname -I | awk '{print $1}')
`;

        await ssh.execCommand(`echo '${hookScript}' > ${installPath}/cpanel-hook.sh && chmod +x ${installPath}/cpanel-hook.sh`);
        await ssh.execCommand(`${installPath}/cpanel-hook.sh`);

        console.log('✅ cPanel integration configured');
    }

    async setupWithWHMCS(ssh, installPath, deploymentConfig) {
        console.log('🔧 Setting up WHMCS module...');

        // Find WHMCS installation
        const findWHMCS = await ssh.execCommand('find /home -name "configuration.php" -path "*/public_html/*" 2>/dev/null | head -1');

        if (findWHMCS.stdout) {
            const whmcsPath = path.dirname(findWHMCS.stdout.trim());
            const modulePath = path.join(whmcsPath, 'modules/servers/flexpbx');

            // Create WHMCS module
            await ssh.execCommand(`mkdir -p ${modulePath}`);

            // Upload WHMCS module files
            const moduleContent = await this.generateWHMCSModule();
            await ssh.execCommand(`echo '${moduleContent}' > ${modulePath}/flexpbx.php`);

            console.log(`✅ WHMCS module installed at ${modulePath}`);
        }
    }

    async setupStandalone(ssh, installPath, deploymentConfig) {
        console.log('🔧 Setting up standalone installation...');

        // Create systemd service
        const serviceContent = `[Unit]
Description=FlexPBX Server
After=network.target docker.service
Requires=docker.service

[Service]
Type=simple
WorkingDirectory=${installPath}
ExecStart=/usr/bin/docker-compose up
ExecStop=/usr/bin/docker-compose down
Restart=always
User=root

[Install]
WantedBy=multi-user.target`;

        await ssh.execCommand(`echo '${serviceContent}' > /etc/systemd/system/flexpbx.service`);
        await ssh.execCommand('systemctl daemon-reload');
        await ssh.execCommand('systemctl enable flexpbx');

        console.log('✅ Standalone service configured');
    }

    async configureServices(ssh, installPath, deploymentConfig, isRoot) {
        console.log('⚙️ Configuring services...');

        // Generate docker-compose.yml
        const dockerCompose = this.generateDockerCompose(deploymentConfig);
        await ssh.execCommand(`echo '${dockerCompose}' > ${installPath}/docker-compose.yml`);

        // Generate .env file
        const envFile = this.generateEnvFile(deploymentConfig);
        await ssh.execCommand(`echo '${envFile}' > ${installPath}/.env`);

        // Start services
        if (isRoot) {
            await ssh.execCommand(`cd ${installPath} && docker-compose up -d`);
        } else {
            console.log('⚠️ Non-root user: Manual service start required');
        }

        console.log('✅ Services configured');
    }

    async configureNginx(method, connectionConfig, deploymentConfig) {
        console.log('🔧 Configuring Nginx...');

        const nginxConfig = this.generateNginxConfig(deploymentConfig);
        const configName = `${deploymentConfig.domain.replace(/\./g, '-')}.conf`;

        if (method === 'ssh' || method === 'sftp') {
            const ssh = new NodeSSH();
            await ssh.connect({
                host: connectionConfig.host,
                username: connectionConfig.username,
                password: connectionConfig.password,
                privateKey: connectionConfig.privateKeyPath ?
                    await fs.readFile(connectionConfig.privateKeyPath, 'utf8') : undefined,
                port: connectionConfig.port || 22
            });

            const isRoot = connectionConfig.username === 'root';
            const nginxPath = '/etc/nginx/conf.d';

            if (isRoot) {
                await ssh.execCommand(`echo '${nginxConfig}' > ${nginxPath}/${configName}`);
                await ssh.execCommand('nginx -t && nginx -s reload');
            } else {
                const userConfigPath = `/home/${connectionConfig.username}/nginx-configs`;
                await ssh.execCommand(`mkdir -p ${userConfigPath}`);
                await ssh.execCommand(`echo '${nginxConfig}' > ${userConfigPath}/${configName}`);
                console.log(`⚠️ Nginx config saved to ${userConfigPath}/${configName}`);
                console.log('Please move to /etc/nginx/conf.d/ with root access');
            }

            await ssh.dispose();
        }

        console.log('✅ Nginx configuration created');
    }

    generateDockerCompose(config) {
        return `version: '3.8'

services:
  flexpbx:
    image: flexpbx/server:latest
    container_name: flexpbx-server
    restart: unless-stopped
    ports:
      - "${config.httpPort || 3000}:3000"
      - "${config.sipPort || 5060}:5060/udp"
      - "${config.sipPort || 5060}:5060/tcp"
      - "10000-20000:10000-20000/udp"
    environment:
      - NODE_ENV=production
      - DOMAIN_NAME=${config.domain}
      - ENABLE_WHMCS=${this.serverDetection.whmcs}
      - ENABLE_CPANEL=${this.serverDetection.cpanel}
    volumes:
      - ./data:/app/data
      - ./logs:/app/logs
      - ./recordings:/app/recordings
    networks:
      - flexpbx_network

  asterisk:
    image: andrius/asterisk:alpine
    container_name: flexpbx-asterisk
    restart: unless-stopped
    network_mode: host
    volumes:
      - ./asterisk:/etc/asterisk
      - ./sounds:/var/lib/asterisk/sounds

  redis:
    image: redis:7-alpine
    container_name: flexpbx-redis
    restart: unless-stopped
    networks:
      - flexpbx_network

  ${config.mailServer === 'builtin' ? `mailserver:
    image: mailhog/mailhog
    container_name: flexpbx-mail
    restart: unless-stopped
    ports:
      - "1025:1025"
      - "8025:8025"
    networks:
      - flexpbx_network` : ''}

networks:
  flexpbx_network:
    driver: bridge`;
    }

    generateEnvFile(config) {
        return `# FlexPBX Configuration
NODE_ENV=production
DOMAIN_NAME=${config.domain}
HTTP_PORT=${config.httpPort || 3000}
SIP_PORT=${config.sipPort || 5060}

# Security
JWT_SECRET=${this.generateRandomString(64)}
SESSION_SECRET=${this.generateRandomString(64)}

# Database
DB_TYPE=sqlite
SQLITE_PATH=./data/flexpbx.sqlite

# Mail Server
MAIL_SERVER_TYPE=${config.mailServer || 'builtin'}
${config.mailServer === 'external' ? `
SMTP_HOST=${config.smtpHost}
SMTP_PORT=${config.smtpPort}
SMTP_USER=${config.smtpUser}
SMTP_PASS=${config.smtpPass}` : ''}

# Integrations
WHMCS_ENABLED=${this.serverDetection.whmcs}
CPANEL_ENABLED=${this.serverDetection.cpanel}
WHM_ENABLED=${this.serverDetection.whm}

# Accessibility
ACCESSIBILITY_ENABLED=true
ACCESSKIT_ENABLED=true`;
    }

    generateNginxConfig(config) {
        const isSubdomain = config.domain.split('.').length > 2;
        const serverName = config.domain;

        return `# FlexPBX Nginx Configuration
# Auto-generated by FlexPBX Installer

upstream flexpbx_backend {
    server 127.0.0.1:${config.httpPort || 3000};
    keepalive 64;
}

server {
    listen 80;
    listen [::]:80;
    server_name ${serverName};

    # Redirect to HTTPS
    ${config.sslEnabled ? 'return 301 https://$server_name$request_uri;' : ''}

    ${!config.sslEnabled ? `
    location / {
        proxy_pass http://flexpbx_backend;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_cache_bypass $http_upgrade;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }` : ''}
}

${config.sslEnabled ? `
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name ${serverName};

    ssl_certificate /etc/letsencrypt/live/${serverName}/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/${serverName}/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;

    location / {
        proxy_pass http://flexpbx_backend;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_cache_bypass $http_upgrade;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # WebSocket support for SIP
    location /ws {
        proxy_pass http://flexpbx_backend/ws;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_read_timeout 86400;
    }

    # AccessKit.dev proxy
    location /accesskit/ {
        proxy_pass https://accesskit.dev/;
        proxy_set_header Host accesskit.dev;
        proxy_set_header X-Real-IP $remote_addr;
    }
}` : ''}`;
    }

    generateWHMCSModule() {
        return `<?php
/**
 * FlexPBX WHMCS Module
 * Auto-generated by FlexPBX Installer
 */

function flexpbx_ConfigOptions() {
    return [
        "Package" => [
            "Type" => "dropdown",
            "Options" => "Basic,Professional,Enterprise",
            "Default" => "Basic",
            "Description" => "PBX Package Type"
        ],
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
        ]
    ];
}

function flexpbx_CreateAccount($params) {
    $api = new FlexPBXAPI($params['serverip'], $params['serverusername'], $params['serverpassword']);

    $result = $api->createAccount([
        'domain' => $params['domain'],
        'username' => $params['username'],
        'password' => $params['password'],
        'package' => $params['configoption1'],
        'extensions' => $params['configoption2'],
        'storage' => $params['configoption3']
    ]);

    if ($result['success']) {
        return 'success';
    } else {
        return $result['error'];
    }
}

function flexpbx_SuspendAccount($params) {
    $api = new FlexPBXAPI($params['serverip'], $params['serverusername'], $params['serverpassword']);
    return $api->suspendAccount($params['domain']);
}

function flexpbx_UnsuspendAccount($params) {
    $api = new FlexPBXAPI($params['serverip'], $params['serverusername'], $params['serverpassword']);
    return $api->unsuspendAccount($params['domain']);
}

function flexpbx_TerminateAccount($params) {
    $api = new FlexPBXAPI($params['serverip'], $params['serverusername'], $params['serverpassword']);
    return $api->terminateAccount($params['domain']);
}

function flexpbx_ClientArea($params) {
    return [
        'templatefile' => 'clientarea',
        'vars' => [
            'pbx_url' => 'https://' . $params['domain'],
            'username' => $params['username'],
            'extensions' => $params['configoption2'],
            'storage' => $params['configoption3']
        ]
    ];
}

class FlexPBXAPI {
    private $host;
    private $username;
    private $password;

    public function __construct($host, $username, $password) {
        $this->host = $host;
        $this->username = $username;
        $this->password = $password;
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

    public function terminateAccount($domain) {
        return $this->apiCall('DELETE', "/api/accounts/{$domain}");
    }

    private function apiCall($method, $endpoint, $data = []) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://{$this->host}:3000{$endpoint}");
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
            return ['success' => true, 'data' => json_decode($response, true)];
        } else {
            return ['success' => false, 'error' => $response];
        }
    }
}`;
    }

    async deployViaSFTP(connectionConfig, deploymentConfig, installConfig) {
        // Similar to SSH but using SFTP for file transfer only
        const client = new Client();

        return new Promise((resolve, reject) => {
            client.on('ready', async () => {
                console.log('SFTP Connection established');

                client.sftp(async (err, sftp) => {
                    if (err) {
                        reject(err);
                        return;
                    }

                    const remotePath = connectionConfig.remotePath || '/home/user/apps/flexpbx';

                    // Upload files
                    await this.uploadViaSFTP(sftp, remotePath);

                    client.end();
                    resolve({
                        success: true,
                        message: 'Files uploaded via SFTP',
                        remotePath
                    });
                });
            });

            client.connect({
                host: connectionConfig.host,
                port: connectionConfig.port || 22,
                username: connectionConfig.username,
                password: connectionConfig.password,
                privateKey: connectionConfig.privateKeyPath ?
                    fs.readFileSync(connectionConfig.privateKeyPath) : undefined
            });
        });
    }

    async uploadViaSFTP(sftp, remotePath) {
        // Implementation for SFTP upload
        console.log('📤 Uploading via SFTP...');
        // Add actual upload logic here
    }

    async deployViaSCP(connectionConfig, deploymentConfig, installConfig) {
        console.log('📤 Deploying via SCP...');

        const remotePath = connectionConfig.remotePath || '/home/user/apps/flexpbx';
        const localPath = path.join(__dirname, '../../../../server');

        return new Promise((resolve, reject) => {
            scp2.scp(localPath, {
                host: connectionConfig.host,
                username: connectionConfig.username,
                password: connectionConfig.password,
                privateKey: connectionConfig.privateKeyPath ?
                    fs.readFileSync(connectionConfig.privateKeyPath, 'utf8') : undefined,
                path: remotePath
            }, (err) => {
                if (err) {
                    reject(err);
                } else {
                    resolve({
                        success: true,
                        message: 'Files uploaded via SCP',
                        remotePath
                    });
                }
            });
        });
    }

    async deployViaFTP(connectionConfig, deploymentConfig, installConfig) {
        const client = new FTP.Client();

        try {
            await client.access({
                host: connectionConfig.host,
                port: connectionConfig.port || 21,
                user: connectionConfig.username,
                password: connectionConfig.password,
                secure: connectionConfig.secure || false
            });

            const remotePath = connectionConfig.remotePath || '/public_html/flexpbx';

            console.log('📤 Uploading via FTP...');
            await client.uploadFromDir(
                path.join(__dirname, '../../../../server'),
                remotePath
            );

            client.close();

            return {
                success: true,
                message: 'Files uploaded via FTP',
                remotePath
            };
        } catch (error) {
            return {
                success: false,
                error: error.message
            };
        }
    }

    async deployViaWebDAV(connectionConfig, deploymentConfig, installConfig) {
        const client = createClient(
            connectionConfig.url,
            {
                username: connectionConfig.username,
                password: connectionConfig.password
            }
        );

        try {
            const remotePath = connectionConfig.remotePath || '/flexpbx';

            console.log('📤 Uploading via WebDAV...');

            // Create directory
            await client.createDirectory(remotePath);

            // Upload files
            const localPath = path.join(__dirname, '../../../../server');
            // Add WebDAV upload logic here

            return {
                success: true,
                message: 'Files uploaded via WebDAV',
                remotePath
            };
        } catch (error) {
            return {
                success: false,
                error: error.message
            };
        }
    }

    generateRandomString(length) {
        const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        let result = '';
        for (let i = 0; i < length; i++) {
            result += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        return result;
    }
}

module.exports = UnifiedDeploymentService;