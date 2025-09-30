const { spawn, exec } = require('child_process');
const fs = require('fs-extra');
const path = require('path');
const which = require('which');
const Dockerode = require('dockerode');

class DockerService {
    constructor() {
        this.docker = null;
        this.initializeDocker();
    }

    async initializeDocker() {
        try {
            this.docker = new Dockerode();
            await this.docker.ping();
        } catch (error) {
            this.docker = null;
        }
    }

    async checkDockerInstallation() {
        try {
            // Check if Docker is installed
            const dockerPath = await which('docker');
            if (!dockerPath) {
                return {
                    installed: false,
                    running: false,
                    message: 'Docker not found in PATH'
                };
            }

            // Check if Docker is running
            const result = await this.executeCommand('docker version --format "{{.Server.Version}}"');

            return {
                installed: true,
                running: result.success,
                version: result.stdout?.trim() || 'Unknown',
                message: result.success ? 'Docker is running' : 'Docker daemon not running'
            };
        } catch (error) {
            return {
                installed: false,
                running: false,
                message: error.message
            };
        }
    }

    async installLocal(config) {
        const {
            installDirectory,
            installationName,
            domain,
            httpPort,
            sipPort,
            configureNginx,
            nginxDomain,
            installLocation,
            appPath,
            sslEnabled
        } = config;

        try {
            // Create installation directory
            await fs.ensureDir(installDirectory);

            // Copy FlexPBX files
            const projectRoot = path.join(__dirname, '../../../../..');
            await this.copyProjectFiles(projectRoot, installDirectory);

            // Generate docker-compose.yml for local installation
            const dockerCompose = this.generateLocalDockerCompose({
                httpPort,
                sipPort,
                installationName,
                domain
            });

            await fs.writeFile(
                path.join(installDirectory, 'docker-compose.yml'),
                dockerCompose
            );

            // Generate .env file
            const envContent = this.generateEnvFile({
                domain,
                httpPort,
                sipPort,
                installationName
            });

            await fs.writeFile(
                path.join(installDirectory, '.env'),
                envContent
            );

            // Configure Nginx if requested
            if (configureNginx) {
                await this.configureNginxForInstall({
                    installDirectory,
                    nginxDomain,
                    installLocation,
                    appPath,
                    httpPort,
                    sslEnabled
                });
            }

            // Start Docker services
            const startResult = await this.startServices(installDirectory);

            return {
                success: true,
                installPath: installDirectory,
                message: 'FlexPBX installed successfully',
                services: startResult
            };

        } catch (error) {
            return {
                success: false,
                error: error.message
            };
        }
    }

    async copyProjectFiles(sourceDir, targetDir) {
        const filesToCopy = [
            'src',
            'public',
            'scripts',
            'package.json',
            'Dockerfile',
            '.env.example'
        ];

        for (const file of filesToCopy) {
            const srcPath = path.join(sourceDir, file);
            const destPath = path.join(targetDir, file);

            if (await fs.pathExists(srcPath)) {
                await fs.copy(srcPath, destPath);
            }
        }
    }

    generateLocalDockerCompose({ httpPort, sipPort, installationName, domain }) {
        return `version: '3.8'

services:
  flexpbx:
    build: .
    container_name: ${installationName.toLowerCase().replace(/[^a-z0-9]/g, '-')}
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
      - DOMAIN_NAME=${domain || 'localhost'}
      - DB_TYPE=sqlite
      - SQLITE_PATH=./data/flexpbx.sqlite
      - ACCESSIBILITY_ENABLED=true
      - SCREEN_READER_SUPPORT=true
    volumes:
      - ./data:/app/data
      - ./logs:/app/logs
      - ./recordings:/app/recordings
      - ./voicemail:/app/voicemail
    networks:
      - flexpbx_network

  redis:
    image: redis:7-alpine
    container_name: ${installationName.toLowerCase().replace(/[^a-z0-9]/g, '-')}-redis
    restart: unless-stopped
    command: redis-server --appendonly yes
    volumes:
      - redis_data:/data
    networks:
      - flexpbx_network

volumes:
  redis_data:

networks:
  flexpbx_network:
    driver: bridge`;
    }

    generateEnvFile({ domain, httpPort, sipPort, installationName }) {
        const jwtSecret = this.generateRandomString(64);
        const sessionSecret = this.generateRandomString(64);
        const adminPassword = this.generateRandomString(16);

        return `# FlexPBX Local Installation Configuration
# Generated: ${new Date().toISOString()}

# Server Configuration
NODE_ENV=production
PORT=3000
DOMAIN_NAME=${domain || 'localhost'}
SSL_ENABLED=false

# Database Configuration (SQLite for local install)
DB_TYPE=sqlite
SQLITE_PATH=./data/flexpbx.sqlite

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
INSTALLATION_TYPE=local
INSTALLATION_DATE=${new Date().toISOString()}

# Default Admin Credentials
DEFAULT_ADMIN_EXTENSION=1000
DEFAULT_ADMIN_PIN=9876`;
    }

    async configureNginxForInstall({ installDirectory, nginxDomain, installLocation, appPath, httpPort, sslEnabled }) {
        const nginxConfigDir = path.join(installDirectory, 'nginx');
        await fs.ensureDir(nginxConfigDir);

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
            sslEnabled
        });

        await fs.writeFile(
            path.join(nginxConfigDir, 'flexpbx.conf'),
            nginxConfig
        );

        // Generate deployment script
        const deployScript = this.generateNginxDeployScript({
            configPath: path.join(nginxConfigDir, 'flexpbx.conf'),
            sslEnabled,
            domain: nginxDomain
        });

        await fs.writeFile(
            path.join(nginxConfigDir, 'deploy.sh'),
            deployScript
        );

        await fs.chmod(path.join(nginxConfigDir, 'deploy.sh'), '755');
    }

    generateNginxConfig({ serverName, locationPath, proxyPath, backendPort, sslEnabled }) {
        const baseConfig = `server {
    listen 80;
    server_name ${serverName};

    ${locationPath} {
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

        if (sslEnabled) {
            return baseConfig + `

server {
    listen 443 ssl http2;
    server_name ${serverName};

    ssl_certificate /etc/letsencrypt/live/${serverName}/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/${serverName}/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;

    ${locationPath} {
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

        return baseConfig;
    }

    generateNginxDeployScript({ configPath, sslEnabled, domain }) {
        return `#!/bin/bash

# FlexPBX Nginx Deployment Script
set -e

echo "Deploying FlexPBX Nginx configuration..."

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo "Please run with sudo"
    exit 1
fi

# Copy configuration to nginx sites-available
cp "${configPath}" /etc/nginx/sites-available/flexpbx

# Enable the site
ln -sf /etc/nginx/sites-available/flexpbx /etc/nginx/sites-enabled/

# Test nginx configuration
if nginx -t; then
    echo "Nginx configuration is valid"
else
    echo "Nginx configuration is invalid"
    exit 1
fi

${sslEnabled ? `
# Install SSL certificate with Let's Encrypt
if command -v certbot &> /dev/null; then
    certbot --nginx -d ${domain} --non-interactive --agree-tos --email admin@${domain}
else
    echo "Certbot not found. Please install Let's Encrypt certbot for SSL"
fi
` : ''}

# Reload nginx
systemctl reload nginx

echo "FlexPBX Nginx configuration deployed successfully!"
echo "Access your FlexPBX installation at: http${sslEnabled ? 's' : ''}://${domain}"`;
    }

    async startServices(installPath) {
        const result = await this.executeCommand('docker-compose up -d', {
            cwd: installPath
        });

        if (result.success) {
            // Wait a moment for services to start
            await new Promise(resolve => setTimeout(resolve, 5000));

            // Check service status
            const statusResult = await this.executeCommand('docker-compose ps', {
                cwd: installPath
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

    async stopServices(installPath) {
        const result = await this.executeCommand('docker-compose down', {
            cwd: installPath
        });

        return {
            success: result.success,
            output: result.success ? result.stdout : result.stderr
        };
    }

    async getStatus(installPath) {
        try {
            const result = await this.executeCommand('docker-compose ps --format json', {
                cwd: installPath
            });

            if (result.success) {
                const services = result.stdout.split('\n')
                    .filter(line => line.trim())
                    .map(line => {
                        try {
                            return JSON.parse(line);
                        } catch (e) {
                            return null;
                        }
                    })
                    .filter(service => service !== null);

                return {
                    running: services.some(s => s.State === 'running'),
                    services
                };
            } else {
                return {
                    running: false,
                    error: result.stderr
                };
            }
        } catch (error) {
            return {
                running: false,
                error: error.message
            };
        }
    }

    async getLogs(installPath, serviceName = null) {
        const command = serviceName
            ? `docker-compose logs --tail=100 ${serviceName}`
            : 'docker-compose logs --tail=100';

        const result = await this.executeCommand(command, {
            cwd: installPath
        });

        return {
            success: result.success,
            logs: result.success ? result.stdout : result.stderr
        };
    }

    async executeCommand(command, options = {}) {
        return new Promise((resolve) => {
            exec(command, options, (error, stdout, stderr) => {
                resolve({
                    success: !error,
                    stdout: stdout.trim(),
                    stderr: stderr.trim(),
                    error
                });
            });
        });
    }

    generateRandomString(length) {
        const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        let result = '';
        for (let i = 0; i < length; i++) {
            result += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        return result;
    }

    async start(installPath) {
        return await this.startServices(installPath);
    }

    async stop(installPath) {
        return await this.stopServices(installPath);
    }
}

module.exports = DockerService;