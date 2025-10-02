const { exec } = require('child_process');
const fs = require('fs-extra');
const path = require('path');
const os = require('os');

class NginxService {
    constructor() {
        this.configCache = new Map();
    }

    async configure(config) {
        const {
            serverName,
            backendHost = 'localhost',
            backendPort = 3000,
            locationType = 'root',
            subdirectory = '/pbx',
            sslEnabled = false,
            outputPath
        } = config;

        try {
            let locationPath = '/';
            let proxyPath = '';

            if (locationType === 'subdirectory') {
                locationPath = subdirectory;
                proxyPath = subdirectory;
            }

            const nginxConfig = this.generateNginxConfig({
                serverName,
                backendHost,
                backendPort,
                locationPath,
                proxyPath,
                sslEnabled
            });

            if (outputPath) {
                await fs.writeFile(outputPath, nginxConfig);
            }

            return {
                success: true,
                config: nginxConfig,
                outputPath
            };

        } catch (error) {
            return {
                success: false,
                error: error.message
            };
        }
    }

    generateNginxConfig({ serverName, backendHost, backendPort, locationPath, proxyPath, sslEnabled }) {
        const baseConfig = `server {
    listen 80;
    server_name ${serverName};

    location ${locationPath} {
        proxy_pass http://${backendHost}:${backendPort}${proxyPath};
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_cache_bypass $http_upgrade;

        # Additional headers for FlexPBX
        proxy_set_header X-Forwarded-Host $host;
        proxy_set_header X-Forwarded-Port $server_port;
    }

    # WebSocket support for real-time features
    location ${proxyPath}/socket.io/ {
        proxy_pass http://${backendHost}:${backendPort};
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    }

    # SIP over WebSocket support
    location ${proxyPath}/sip/ {
        proxy_pass http://${backendHost}:${backendPort};
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    }

    # Static assets with caching
    location ${proxyPath}/static/ {
        proxy_pass http://${backendHost}:${backendPort};
        proxy_cache_valid 200 302 1h;
        proxy_cache_valid 404 1m;
        add_header Cache-Control "public, immutable";
    }

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
}`;

        if (sslEnabled) {
            return baseConfig + `

# HTTPS redirect
server {
    listen 80;
    server_name ${serverName};
    return 301 https://$server_name$request_uri;
}

# HTTPS server
server {
    listen 443 ssl http2;
    server_name ${serverName};

    # SSL configuration
    ssl_certificate /etc/letsencrypt/live/${serverName}/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/${serverName}/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES128-GCM-SHA256:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-RSA-AES128-SHA256:ECDHE-RSA-AES256-SHA384;
    ssl_prefer_server_ciphers off;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 1d;
    ssl_session_tickets off;

    # OCSP stapling
    ssl_stapling on;
    ssl_stapling_verify on;

    location ${locationPath} {
        proxy_pass http://${backendHost}:${backendPort}${proxyPath};
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto https;
        proxy_cache_bypass $http_upgrade;

        # Additional headers for FlexPBX
        proxy_set_header X-Forwarded-Host $host;
        proxy_set_header X-Forwarded-Port $server_port;
    }

    # WebSocket support for real-time features
    location ${proxyPath}/socket.io/ {
        proxy_pass http://${backendHost}:${backendPort};
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto https;
    }

    # SIP over WebSocket support
    location ${proxyPath}/sip/ {
        proxy_pass http://${backendHost}:${backendPort};
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto https;
    }

    # Static assets with caching
    location ${proxyPath}/static/ {
        proxy_pass http://${backendHost}:${backendPort};
        proxy_cache_valid 200 302 1h;
        proxy_cache_valid 404 1m;
        add_header Cache-Control "public, immutable";
    }

    # Security headers for HTTPS
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
}`;
        }

        return baseConfig;
    }

    async testConfiguration(configPath) {
        try {
            // Check if nginx is installed
            const nginxCheck = await this.executeCommand('nginx -v');
            if (!nginxCheck.success) {
                return {
                    success: false,
                    error: 'Nginx is not installed'
                };
            }

            // Test the configuration file
            const testResult = await this.executeCommand(`nginx -t -c ${configPath}`);

            return {
                success: testResult.success,
                output: testResult.success ? testResult.stdout : testResult.stderr,
                valid: testResult.success
            };

        } catch (error) {
            return {
                success: false,
                error: error.message
            };
        }
    }

    async reload() {
        try {
            const result = await this.executeCommand('nginx -s reload');

            return {
                success: result.success,
                output: result.success ? result.stdout : result.stderr
            };

        } catch (error) {
            return {
                success: false,
                error: error.message
            };
        }
    }

    async checkStatus() {
        try {
            const statusResult = await this.executeCommand('systemctl status nginx');
            const configTestResult = await this.executeCommand('nginx -t');

            return {
                running: statusResult.success,
                configValid: configTestResult.success,
                status: statusResult.stdout,
                configTest: configTestResult.stdout || configTestResult.stderr
            };

        } catch (error) {
            return {
                running: false,
                configValid: false,
                error: error.message
            };
        }
    }

    async deployCertbot(domain, email) {
        try {
            // Check if certbot is installed
            const certbotCheck = await this.executeCommand('certbot --version');
            if (!certbotCheck.success) {
                return {
                    success: false,
                    error: 'Certbot is not installed. Please install it first.'
                };
            }

            // Run certbot for nginx
            const certbotCommand = `certbot --nginx -d ${domain} --non-interactive --agree-tos --email ${email}`;
            const result = await this.executeCommand(certbotCommand);

            return {
                success: result.success,
                output: result.stdout,
                error: result.success ? null : result.stderr
            };

        } catch (error) {
            return {
                success: false,
                error: error.message
            };
        }
    }

    async listCertificates() {
        try {
            const result = await this.executeCommand('certbot certificates');

            return {
                success: result.success,
                certificates: result.stdout,
                error: result.success ? null : result.stderr
            };

        } catch (error) {
            return {
                success: false,
                error: error.message
            };
        }
    }

    async renewCertificates() {
        try {
            const result = await this.executeCommand('certbot renew');

            return {
                success: result.success,
                output: result.stdout,
                error: result.success ? null : result.stderr
            };

        } catch (error) {
            return {
                success: false,
                error: error.message
            };
        }
    }

    async detectNginxInstallation() {
        try {
            const nginxVersion = await this.executeCommand('nginx -v');
            const configPath = await this.executeCommand('nginx -T 2>&1 | grep "configuration file" | head -1');

            let sitesPath = '/etc/nginx/sites-available';
            let enabledPath = '/etc/nginx/sites-enabled';

            // Check for different nginx installations
            if (await fs.pathExists('/etc/nginx/conf.d')) {
                sitesPath = '/etc/nginx/conf.d';
                enabledPath = '/etc/nginx/conf.d';
            }

            // Check for cPanel installation
            const cpanelNginx = await fs.pathExists('/etc/nginx/conf.d/cpanel_proxy.conf');

            return {
                installed: nginxVersion.success,
                version: nginxVersion.stdout || nginxVersion.stderr,
                configPath: configPath.stdout,
                sitesPath,
                enabledPath,
                cpanelDetected: cpanelNginx
            };

        } catch (error) {
            return {
                installed: false,
                error: error.message
            };
        }
    }

    async backupConfiguration(backupPath) {
        try {
            const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
            const backupDir = path.join(backupPath, `nginx-backup-${timestamp}`);

            await fs.ensureDir(backupDir);

            // Backup main configuration
            await fs.copy('/etc/nginx/nginx.conf', path.join(backupDir, 'nginx.conf'));

            // Backup sites if they exist
            if (await fs.pathExists('/etc/nginx/sites-available')) {
                await fs.copy('/etc/nginx/sites-available', path.join(backupDir, 'sites-available'));
            }

            if (await fs.pathExists('/etc/nginx/sites-enabled')) {
                await fs.copy('/etc/nginx/sites-enabled', path.join(backupDir, 'sites-enabled'));
            }

            // Backup conf.d if it exists
            if (await fs.pathExists('/etc/nginx/conf.d')) {
                await fs.copy('/etc/nginx/conf.d', path.join(backupDir, 'conf.d'));
            }

            return {
                success: true,
                backupPath: backupDir,
                timestamp
            };

        } catch (error) {
            return {
                success: false,
                error: error.message
            };
        }
    }

    async restoreConfiguration(backupPath) {
        try {
            // Validate backup path
            if (!await fs.pathExists(backupPath)) {
                return {
                    success: false,
                    error: 'Backup path does not exist'
                };
            }

            // Create current backup before restore
            const currentBackup = await this.backupConfiguration(os.tmpdir());
            if (!currentBackup.success) {
                return {
                    success: false,
                    error: 'Failed to create safety backup: ' + currentBackup.error
                };
            }

            // Restore configurations
            if (await fs.pathExists(path.join(backupPath, 'nginx.conf'))) {
                await fs.copy(path.join(backupPath, 'nginx.conf'), '/etc/nginx/nginx.conf');
            }

            if (await fs.pathExists(path.join(backupPath, 'sites-available'))) {
                await fs.copy(path.join(backupPath, 'sites-available'), '/etc/nginx/sites-available');
            }

            if (await fs.pathExists(path.join(backupPath, 'sites-enabled'))) {
                await fs.copy(path.join(backupPath, 'sites-enabled'), '/etc/nginx/sites-enabled');
            }

            if (await fs.pathExists(path.join(backupPath, 'conf.d'))) {
                await fs.copy(path.join(backupPath, 'conf.d'), '/etc/nginx/conf.d');
            }

            // Test configuration
            const testResult = await this.testConfiguration('/etc/nginx/nginx.conf');
            if (!testResult.success) {
                // Restore from safety backup
                await this.restoreConfiguration(currentBackup.backupPath);
                return {
                    success: false,
                    error: 'Restored configuration is invalid. Reverted to previous configuration.'
                };
            }

            return {
                success: true,
                message: 'Configuration restored successfully',
                safetyBackup: currentBackup.backupPath
            };

        } catch (error) {
            return {
                success: false,
                error: error.message
            };
        }
    }

    async executeCommand(command) {
        return new Promise((resolve) => {
            exec(command, (error, stdout, stderr) => {
                resolve({
                    success: !error,
                    stdout: stdout.trim(),
                    stderr: stderr.trim(),
                    error
                });
            });
        });
    }
}

module.exports = NginxService;