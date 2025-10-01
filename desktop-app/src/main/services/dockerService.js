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
            sslEnabled,
            mailServerType,
            smtpHost,
            smtpPort,
            smtpUsername,
            smtpPassword,
            smtpTls
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
                domain,
                mailServerType
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
                installationName,
                mailServerType,
                smtpHost,
                smtpPort,
                smtpUsername,
                smtpPassword,
                smtpTls
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

            // Setup AccessKit.dev accessibility tools
            await this.setupAccessKitTools(installDirectory);

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

    generateLocalDockerCompose({ httpPort, sipPort, installationName, domain, mailServerType }) {
        const containerName = installationName.toLowerCase().replace(/[^a-z0-9]/g, '-');

        let mailServerService = '';
        if (mailServerType === 'builtin') {
            mailServerService = `
  mailserver:
    image: mailhog/mailhog:latest
    container_name: ${containerName}-mailserver
    restart: unless-stopped
    ports:
      - "1025:1025"
      - "8025:8025"
    networks:
      - flexpbx_network
`;
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
      - DOMAIN_NAME=${domain || 'localhost'}
      - DB_TYPE=sqlite
      - SQLITE_PATH=./data/flexpbx.sqlite
      - ACCESSIBILITY_ENABLED=true
      - SCREEN_READER_SUPPORT=true
      - VOICE_ANNOUNCEMENTS_ENABLED=true
      - AUDIO_FEEDBACK_ENABLED=true
      - ACCESSKIT_ENABLED=true
    volumes:
      - ./data:/app/data
      - ./logs:/app/logs
      - ./recordings:/app/recordings
      - ./voicemail:/app/voicemail
    networks:
      - flexpbx_network
    depends_on:
      - redis${mailServerType === 'builtin' ? '\n      - mailserver' : ''}

  redis:
    image: redis:7-alpine
    container_name: ${containerName}-redis
    restart: unless-stopped
    command: redis-server --appendonly yes
    volumes:
      - redis_data:/data
    networks:
      - flexpbx_network

  # AccessKit.dev accessibility tools proxy
  accesskit-proxy:
    image: nginx:alpine
    container_name: ${containerName}-accesskit
    restart: unless-stopped
    ports:
      - "8080:80"
    volumes:
      - ./accesskit:/usr/share/nginx/html:ro
      - ./nginx/accesskit.conf:/etc/nginx/conf.d/default.conf:ro
    networks:
      - flexpbx_network
    depends_on:
      - flexpbx${mailServerService}

volumes:
  redis_data:

networks:
  flexpbx_network:
    driver: bridge`;
    }

    generateEnvFile({ domain, httpPort, sipPort, installationName, mailServerType, smtpHost, smtpPort, smtpUsername, smtpPassword, smtpTls }) {
        const jwtSecret = this.generateRandomString(64);
        const sessionSecret = this.generateRandomString(64);
        const adminPassword = this.generateRandomString(16);

        let mailConfig = '';
        if (mailServerType === 'builtin') {
            mailConfig = `
# Built-in Mail Server Configuration
MAIL_SERVER_TYPE=builtin
MAIL_SERVER_ENABLED=true
MAIL_SERVER_PORT=587
MAIL_SERVER_TLS=true`;
        } else if (mailServerType === 'external') {
            mailConfig = `
# External SMTP Server Configuration
MAIL_SERVER_TYPE=external
MAIL_SERVER_ENABLED=true
SMTP_HOST=${smtpHost || ''}
SMTP_PORT=${smtpPort || 587}
SMTP_USERNAME=${smtpUsername || ''}
SMTP_PASSWORD=${smtpPassword || ''}
SMTP_TLS=${smtpTls ? 'true' : 'false'}`;
        } else {
            mailConfig = `
# No Mail Server
MAIL_SERVER_TYPE=none
MAIL_SERVER_ENABLED=false`;
        }

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
${mailConfig}

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

    async setupAccessKitTools(installDirectory) {
        try {
            // Create accesskit directory
            const accesskitDir = path.join(installDirectory, 'accesskit');
            await fs.ensureDir(accesskitDir);

            // Create nginx config for accesskit
            const nginxDir = path.join(installDirectory, 'nginx');
            await fs.ensureDir(nginxDir);

            const accesskitNginxConfig = `server {
    listen 80;
    server_name localhost;
    root /usr/share/nginx/html;
    index index.html;

    # AccessKit.dev proxy configuration
    location /accesskit/ {
        proxy_pass http://accesskit.dev/;
        proxy_set_header Host accesskit.dev;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;

        # Add accessibility headers
        add_header X-Accessibility-Tools "AccessKit.dev" always;
    }

    # Serve local accessibility tools
    location / {
        try_files $uri $uri/ /index.html;

        # Enable accessibility features
        add_header X-Frame-Options "SAMEORIGIN" always;
        add_header X-Content-Type-Options "nosniff" always;
        add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    }
}`;

            await fs.writeFile(
                path.join(nginxDir, 'accesskit.conf'),
                accesskitNginxConfig
            );

            // Create AccessKit.dev integration HTML
            const accesskitHtml = `<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FlexPBX Accessibility Tools</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f5f5f5;
            color: #333;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2c3e50;
            margin-bottom: 20px;
        }
        .tool-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        .tool-card {
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            padding: 20px;
            background: #fafafa;
        }
        .tool-card h3 {
            margin-top: 0;
            color: #34495e;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 10px;
        }
        .btn:hover {
            background: #2980b9;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>FlexPBX Accessibility Tools</h1>
        <p>AccessKit.dev integration provides comprehensive accessibility testing and enhancement tools for your FlexPBX installation.</p>

        <div class="tool-grid">
            <div class="tool-card">
                <h3>Screen Reader Testing</h3>
                <p>Test your PBX interface with various screen readers and assistive technologies.</p>
                <a href="/accesskit/screen-reader-test" class="btn">Launch Tool</a>
            </div>

            <div class="tool-card">
                <h3>Color Contrast Analyzer</h3>
                <p>Verify color combinations meet WCAG accessibility standards.</p>
                <a href="/accesskit/contrast-analyzer" class="btn">Launch Tool</a>
            </div>

            <div class="tool-card">
                <h3>Keyboard Navigation Tester</h3>
                <p>Ensure all PBX functions are accessible via keyboard navigation.</p>
                <a href="/accesskit/keyboard-test" class="btn">Launch Tool</a>
            </div>

            <div class="tool-card">
                <h3>Voice Control Interface</h3>
                <p>Test voice command functionality for hands-free PBX operation.</p>
                <a href="/accesskit/voice-control" class="btn">Launch Tool</a>
            </div>
        </div>

        <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #e0e0e0;">
            <p><strong>Note:</strong> These tools are powered by <a href="https://accesskit.dev" target="_blank">AccessKit.dev</a> and help ensure your FlexPBX installation meets accessibility standards.</p>
        </div>
    </div>
</body>
</html>`;

            await fs.writeFile(
                path.join(accesskitDir, 'index.html'),
                accesskitHtml
            );

        } catch (error) {
            console.warn('Failed to setup AccessKit tools:', error.message);
            // Don't fail the installation if AccessKit setup fails
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

    async start(installPath) {
        return await this.startServices(installPath);
    }

    async stop(installPath) {
        return await this.stopServices(installPath);
    }
}

module.exports = DockerService;