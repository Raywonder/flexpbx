const { NodeSSH } = require('node-ssh');
const fs = require('fs-extra');
const path = require('path');
const os = require('os');

class SSHService {
    constructor() {
        this.connections = new Map();
    }

    async testConnection(config) {
        const { host, port = 22, username, password, privateKey, passphrase } = config;
        const ssh = new NodeSSH();

        try {
            const connectionConfig = {
                host,
                port,
                username
            };

            if (privateKey) {
                connectionConfig.privateKey = privateKey;
                if (passphrase) {
                    connectionConfig.passphrase = passphrase;
                }
            } else if (password) {
                connectionConfig.password = password;
            }

            await ssh.connect(connectionConfig);

            // Test basic commands
            const whoami = await ssh.execCommand('whoami');
            const uname = await ssh.execCommand('uname -a');

            await ssh.dispose();

            return {
                success: true,
                user: whoami.stdout.trim(),
                system: uname.stdout.trim(),
                message: 'Connection successful'
            };
        } catch (error) {
            return {
                success: false,
                error: error.message
            };
        }
    }

    async connect(connectionId, config) {
        const ssh = new NodeSSH();

        try {
            await ssh.connect(config);
            this.connections.set(connectionId, ssh);
            return { success: true };
        } catch (error) {
            return { success: false, error: error.message };
        }
    }

    async disconnect(connectionId) {
        const ssh = this.connections.get(connectionId);
        if (ssh) {
            await ssh.dispose();
            this.connections.delete(connectionId);
        }
    }

    async executeCommand(connectionId, command, options = {}) {
        const ssh = this.connections.get(connectionId);
        if (!ssh) {
            return { success: false, error: 'No active connection' };
        }

        try {
            const result = await ssh.execCommand(command, options);
            return {
                success: result.code === 0,
                stdout: result.stdout,
                stderr: result.stderr,
                code: result.code
            };
        } catch (error) {
            return {
                success: false,
                error: error.message
            };
        }
    }

    async deployToRemote(config) {
        const {
            connection,
            deploymentPath,
            installationName,
            domain,
            httpPort,
            sipPort,
            configureNginx,
            nginxDomain,
            installLocation,
            appPath,
            sslEnabled,
            databaseType,
            dbConfig
        } = config;

        const connectionId = `deploy-${Date.now()}`;

        try {
            // Connect to remote server
            const connectResult = await this.connect(connectionId, connection);
            if (!connectResult.success) {
                return connectResult;
            }

            const ssh = this.connections.get(connectionId);

            // Check if user has Docker permissions
            const dockerCheck = await this.checkDockerPermissions(connectionId);
            if (!dockerCheck.success) {
                await this.disconnect(connectionId);
                return dockerCheck;
            }

            // Create deployment directory
            await this.executeCommand(connectionId, `mkdir -p ${deploymentPath}`);

            // Copy FlexPBX files to remote server
            const projectRoot = path.join(__dirname, '../../../../..');
            await this.uploadProjectFiles(ssh, projectRoot, deploymentPath);

            // Generate and upload docker-compose.yml
            const dockerCompose = this.generateRemoteDockerCompose({
                httpPort,
                sipPort,
                installationName,
                domain,
                databaseType,
                dbConfig
            });

            await ssh.putContent(dockerCompose, `${deploymentPath}/docker-compose.yml`);

            // Generate and upload .env file
            const envContent = this.generateRemoteEnvFile({
                domain,
                httpPort,
                sipPort,
                installationName,
                databaseType,
                dbConfig
            });

            await ssh.putContent(envContent, `${deploymentPath}/.env`);

            // Configure Nginx if requested
            if (configureNginx) {
                const nginxResult = await this.configureRemoteNginx(connectionId, {
                    deploymentPath,
                    nginxDomain,
                    installLocation,
                    appPath,
                    httpPort,
                    sslEnabled
                });

                if (!nginxResult.success) {
                    await this.disconnect(connectionId);
                    return nginxResult;
                }
            }

            // Start Docker services
            const startResult = await this.startRemoteServices(connectionId, deploymentPath);

            await this.disconnect(connectionId);

            return {
                success: true,
                deploymentPath,
                message: 'FlexPBX deployed successfully to remote server',
                services: startResult
            };

        } catch (error) {
            await this.disconnect(connectionId);
            return {
                success: false,
                error: error.message
            };
        }
    }

    async checkDockerPermissions(connectionId) {
        // Check if Docker is installed
        const dockerVersion = await this.executeCommand(connectionId, 'docker --version');
        if (!dockerVersion.success) {
            return {
                success: false,
                error: 'Docker is not installed on the remote server'
            };
        }

        // Check if user can run Docker commands
        const dockerTest = await this.executeCommand(connectionId, 'docker ps');
        if (!dockerTest.success) {
            // Try with sudo
            const sudoTest = await this.executeCommand(connectionId, 'sudo docker ps');
            if (sudoTest.success) {
                return {
                    success: true,
                    requiresSudo: true,
                    message: 'Docker access requires sudo privileges'
                };
            } else {
                return {
                    success: false,
                    error: 'User does not have Docker permissions. Please add user to docker group or ensure sudo access.'
                };
            }
        }

        return {
            success: true,
            requiresSudo: false,
            message: 'Docker access available'
        };
    }

    async uploadProjectFiles(ssh, sourceDir, targetDir) {
        const filesToUpload = [
            'src',
            'public',
            'scripts',
            'package.json',
            'Dockerfile',
            '.env.example'
        ];

        for (const file of filesToUpload) {
            const sourcePath = path.join(sourceDir, file);
            const targetPath = `${targetDir}/${file}`;

            if (await fs.pathExists(sourcePath)) {
                const stats = await fs.stat(sourcePath);

                if (stats.isDirectory()) {
                    await ssh.putDirectory(sourcePath, targetPath, {
                        recursive: true,
                        concurrency: 5
                    });
                } else {
                    await ssh.putFile(sourcePath, targetPath);
                }
            }
        }
    }

    generateRemoteDockerCompose({ httpPort, sipPort, installationName, domain, databaseType, dbConfig }) {
        const containerName = installationName.toLowerCase().replace(/[^a-z0-9]/g, '-');
        let services = '';
        let volumes = '';
        let environment = '';

        // Database configuration
        if (databaseType === 'mysql' || databaseType === 'mariadb') {
            services += `
  database:
    image: ${databaseType === 'mysql' ? 'mysql:8.0' : 'mariadb:10.11'}
    container_name: ${containerName}-db
    restart: unless-stopped
    environment:
      - MYSQL_ROOT_PASSWORD=${dbConfig.rootPassword}
      - MYSQL_DATABASE=${dbConfig.database}
      - MYSQL_USER=${dbConfig.username}
      - MYSQL_PASSWORD=${dbConfig.password}
    volumes:
      - db_data:/var/lib/mysql
    networks:
      - flexpbx_network

`;
            environment = `
      - DB_TYPE=${databaseType}
      - DB_HOST=database
      - DB_PORT=3306
      - DB_NAME=${dbConfig.database}
      - DB_USER=${dbConfig.username}
      - DB_PASSWORD=${dbConfig.password}`;
            volumes += `
  db_data:`;
        } else if (databaseType === 'postgresql') {
            services += `
  database:
    image: postgres:15-alpine
    container_name: ${containerName}-db
    restart: unless-stopped
    environment:
      - POSTGRES_DB=${dbConfig.database}
      - POSTGRES_USER=${dbConfig.username}
      - POSTGRES_PASSWORD=${dbConfig.password}
    volumes:
      - db_data:/var/lib/postgresql/data
    networks:
      - flexpbx_network

`;
            environment = `
      - DB_TYPE=postgresql
      - DB_HOST=database
      - DB_PORT=5432
      - DB_NAME=${dbConfig.database}
      - DB_USER=${dbConfig.username}
      - DB_PASSWORD=${dbConfig.password}`;
            volumes += `
  db_data:`;
        } else {
            environment = `
      - DB_TYPE=sqlite
      - SQLITE_PATH=./data/flexpbx.sqlite`;
        }

        return `version: '3.8'

services:
  flexpbx:
    build: .
    container_name: ${containerName}
    restart: unless-stopped
    ports:
      - "${httpPort}:3000"
      - "${sipPort}:5060/udp"
      - "${sipPort}:5060/tcp"
      - "5061:5061/tcp"
      - "8088:8088"
      - "8089:8089"
      - "10000-20000:10000-20000/udp"
    environment:
      - NODE_ENV=production
      - PORT=3000
      - DOMAIN_NAME=${domain || 'localhost'}${environment}
      - ACCESSIBILITY_ENABLED=true
      - SCREEN_READER_SUPPORT=true
    volumes:
      - ./data:/app/data
      - ./logs:/app/logs
      - ./recordings:/app/recordings
      - ./voicemail:/app/voicemail
    networks:
      - flexpbx_network
${databaseType !== 'sqlite' ? '    depends_on:\n      - database' : ''}
${services}
  redis:
    image: redis:7-alpine
    container_name: ${containerName}-redis
    restart: unless-stopped
    command: redis-server --appendonly yes
    volumes:
      - redis_data:/data
    networks:
      - flexpbx_network

volumes:
  redis_data:${volumes}

networks:
  flexpbx_network:
    driver: bridge`;
    }

    generateRemoteEnvFile({ domain, httpPort, sipPort, installationName, databaseType, dbConfig }) {
        const jwtSecret = this.generateRandomString(64);
        const sessionSecret = this.generateRandomString(64);
        const adminPassword = this.generateRandomString(16);

        let dbEnvVars = '';
        if (databaseType === 'mysql' || databaseType === 'mariadb') {
            dbEnvVars = `
# Database Configuration (MySQL/MariaDB)
DB_TYPE=${databaseType}
DB_HOST=database
DB_PORT=3306
DB_NAME=${dbConfig.database}
DB_USER=${dbConfig.username}
DB_PASSWORD=${dbConfig.password}`;
        } else if (databaseType === 'postgresql') {
            dbEnvVars = `
# Database Configuration (PostgreSQL)
DB_TYPE=postgresql
DB_HOST=database
DB_PORT=5432
DB_NAME=${dbConfig.database}
DB_USER=${dbConfig.username}
DB_PASSWORD=${dbConfig.password}`;
        } else {
            dbEnvVars = `
# Database Configuration (SQLite)
DB_TYPE=sqlite
SQLITE_PATH=./data/flexpbx.sqlite`;
        }

        return `# FlexPBX Remote Installation Configuration
# Generated: ${new Date().toISOString()}

# Server Configuration
NODE_ENV=production
PORT=3000
DOMAIN_NAME=${domain || 'localhost'}
SSL_ENABLED=false
${dbEnvVars}

# Security
JWT_SECRET=${jwtSecret}
SESSION_SECRET=${sessionSecret}
DEFAULT_ADMIN_PASSWORD=${adminPassword}

# Network Configuration
SIP_UDP_PORT=${sipPort}
SIP_TCP_PORT=${sipPort}
HTTP_PORT=${httpPort}

# Accessibility Features
ACCESSIBILITY_ENABLED=true
SCREEN_READER_SUPPORT=true
AUDIO_FEEDBACK_ENABLED=true
VOICE_ANNOUNCEMENTS_ENABLED=true

# Installation Info
INSTALLATION_NAME=${installationName}
INSTALLATION_TYPE=remote
INSTALLATION_DATE=${new Date().toISOString()}

# Default Admin Credentials
DEFAULT_ADMIN_EXTENSION=1000
DEFAULT_ADMIN_PIN=9876`;
    }

    async configureRemoteNginx(connectionId, { deploymentPath, nginxDomain, installLocation, appPath, httpPort, sslEnabled }) {
        try {
            // Check if Nginx is installed
            const nginxCheck = await this.executeCommand(connectionId, 'nginx -v');
            if (!nginxCheck.success) {
                return {
                    success: false,
                    error: 'Nginx is not installed on the remote server'
                };
            }

            // Check for cPanel/WHM
            const cpanelCheck = await this.detectCPanel(connectionId);

            let locationPath = '/';
            let proxyPath = '';

            switch (installLocation) {
                case 'subdirectory':
                    locationPath = appPath;
                    proxyPath = appPath;
                    break;
                case 'subdomain':
                    locationPath = '/';
                    proxyPath = '';
                    break;
                default: // root
                    locationPath = '/';
                    proxyPath = '';
            }

            const nginxConfig = this.generateNginxConfig({
                serverName: nginxDomain,
                locationPath,
                proxyPath,
                backendPort: httpPort,
                sslEnabled,
                cpanelDetected: cpanelCheck.detected
            });

            // Create nginx directory in deployment path
            await this.executeCommand(connectionId, `mkdir -p ${deploymentPath}/nginx`);

            // Upload nginx configuration
            const ssh = this.connections.get(connectionId);
            await ssh.putContent(nginxConfig, `${deploymentPath}/nginx/flexpbx.conf`);

            // Generate deployment script
            const deployScript = this.generateNginxDeployScript({
                configPath: `${deploymentPath}/nginx/flexpbx.conf`,
                sslEnabled,
                domain: nginxDomain,
                cpanelDetected: cpanelCheck.detected
            });

            await ssh.putContent(deployScript, `${deploymentPath}/nginx/deploy.sh`);
            await this.executeCommand(connectionId, `chmod +x ${deploymentPath}/nginx/deploy.sh`);

            return {
                success: true,
                configPath: `${deploymentPath}/nginx/flexpbx.conf`,
                deployScript: `${deploymentPath}/nginx/deploy.sh`,
                cpanelDetected: cpanelCheck.detected,
                message: cpanelCheck.detected
                    ? 'Nginx configuration generated with cPanel compatibility'
                    : 'Nginx configuration generated'
            };

        } catch (error) {
            return {
                success: false,
                error: error.message
            };
        }
    }

    async detectCPanel(connectionId) {
        // Check for cPanel installation
        const cpanelCheck = await this.executeCommand(connectionId, 'ls /usr/local/cpanel');
        const whmCheck = await this.executeCommand(connectionId, 'ls /usr/local/cpanel/whostmgr');

        if (cpanelCheck.success) {
            return {
                detected: true,
                type: 'cpanel',
                hasWHM: whmCheck.success,
                message: 'cPanel detected'
            };
        }

        return {
            detected: false,
            type: null,
            message: 'No control panel detected'
        };
    }

    generateNginxConfig({ serverName, locationPath, proxyPath, backendPort, sslEnabled, cpanelDetected }) {
        const baseConfig = cpanelDetected ?
            this.generateCPanelNginxConfig({ serverName, locationPath, proxyPath, backendPort }) :
            this.generateStandardNginxConfig({ serverName, locationPath, proxyPath, backendPort });

        if (sslEnabled && !cpanelDetected) {
            return baseConfig + this.generateSSLConfig({ serverName, locationPath, proxyPath, backendPort });
        }

        return baseConfig;
    }

    generateStandardNginxConfig({ serverName, locationPath, proxyPath, backendPort }) {
        return `server {
    listen 80;
    server_name ${serverName};

    location ${locationPath} {
        proxy_pass http://localhost:${backendPort}${proxyPath};
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_cache_bypass $http_upgrade;
    }

    # WebSocket support
    location ${proxyPath}/socket.io/ {
        proxy_pass http://localhost:${backendPort};
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    }
}`;
    }

    generateCPanelNginxConfig({ serverName, locationPath, proxyPath, backendPort }) {
        return `# FlexPBX Nginx Configuration for cPanel
# This configuration is compatible with cPanel's Nginx setup

server {
    listen 80;
    server_name ${serverName};

    # cPanel compatibility - respect existing configurations
    include /etc/nginx/conf.d/cpanel_proxy.conf;

    location ${locationPath} {
        proxy_pass http://127.0.0.1:${backendPort}${proxyPath};
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_cache_bypass $http_upgrade;

        # cPanel specific headers
        proxy_set_header X-Forwarded-Port $server_port;
        proxy_set_header X-Forwarded-Host $host;
    }

    # WebSocket support with cPanel compatibility
    location ${proxyPath}/socket.io/ {
        proxy_pass http://127.0.0.1:${backendPort};
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Port $server_port;
        proxy_set_header X-Forwarded-Host $host;
    }
}`;
    }

    generateSSLConfig({ serverName, locationPath, proxyPath, backendPort }) {
        return `

server {
    listen 443 ssl http2;
    server_name ${serverName};

    ssl_certificate /etc/letsencrypt/live/${serverName}/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/${serverName}/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;

    location ${locationPath} {
        proxy_pass http://localhost:${backendPort}${proxyPath};
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto https;
        proxy_cache_bypass $http_upgrade;
    }

    location ${proxyPath}/socket.io/ {
        proxy_pass http://localhost:${backendPort};
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto https;
    }
}`;
    }

    generateNginxDeployScript({ configPath, sslEnabled, domain, cpanelDetected }) {
        const nginxSitesPath = cpanelDetected ? '/etc/nginx/conf.d' : '/etc/nginx/sites-available';
        const enableCommand = cpanelDetected ? '' : 'ln -sf /etc/nginx/sites-available/flexpbx /etc/nginx/sites-enabled/';

        return `#!/bin/bash

# FlexPBX Nginx Deployment Script
set -e

echo "Deploying FlexPBX Nginx configuration..."

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo "Please run with sudo"
    exit 1
fi

${cpanelDetected ? `
# cPanel/WHM detected - using cPanel nginx structure
echo "cPanel/WHM detected - configuring for cPanel compatibility"
cp "${configPath}" ${nginxSitesPath}/flexpbx.conf
` : `
# Standard nginx installation
cp "${configPath}" ${nginxSitesPath}/flexpbx

# Enable the site
${enableCommand}
`}

# Test nginx configuration
if nginx -t; then
    echo "Nginx configuration is valid"
else
    echo "Nginx configuration is invalid"
    exit 1
fi

${sslEnabled && !cpanelDetected ? `
# Install SSL certificate with Let's Encrypt
if command -v certbot &> /dev/null; then
    certbot --nginx -d ${domain} --non-interactive --agree-tos --email admin@${domain}
else
    echo "Certbot not found. Please install Let's Encrypt certbot for SSL"
fi
` : ''}

${cpanelDetected ? `
# Restart nginx for cPanel
systemctl restart nginx
echo "Note: You may need to configure SSL through cPanel if desired"
` : `
# Reload nginx
systemctl reload nginx
`}

echo "FlexPBX Nginx configuration deployed successfully!"
echo "Access your FlexPBX installation at: http${sslEnabled ? 's' : ''}://${domain}"`;
    }

    async startRemoteServices(connectionId, deploymentPath) {
        // Check if sudo is required
        const dockerPermissions = await this.checkDockerPermissions(connectionId);
        const dockerCommand = dockerPermissions.requiresSudo ? 'sudo docker-compose' : 'docker-compose';

        const result = await this.executeCommand(connectionId, `${dockerCommand} up -d`, {
            cwd: deploymentPath
        });

        if (result.success) {
            // Wait a moment for services to start
            await new Promise(resolve => setTimeout(resolve, 5000));

            // Check service status
            const statusResult = await this.executeCommand(connectionId, `${dockerCommand} ps`, {
                cwd: deploymentPath
            });

            return {
                success: true,
                output: result.stdout,
                status: statusResult.stdout
            };
        } else {
            return {
                success: false,
                error: result.stderr
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

module.exports = SSHService;