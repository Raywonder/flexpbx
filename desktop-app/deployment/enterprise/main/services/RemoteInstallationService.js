const { EventEmitter } = require('events');
const path = require('path');
const os = require('os');

class RemoteInstallationService extends EventEmitter {
    constructor() {
        super();
        this.installedServices = new Map();
        this.installationTemplates = this.getInstallationTemplates();
    }

    /**
     * Get installation templates for different services
     */
    getInstallationTemplates() {
        return {
            nginx: {
                name: 'Nginx Web Server',
                description: 'High-performance web server and reverse proxy',
                platforms: ['linux', 'darwin', 'win32'],
                dependencies: [],
                ports: [80, 443],
                configPath: {
                    linux: '/etc/nginx',
                    darwin: '/usr/local/etc/nginx',
                    win32: 'C:/nginx/conf'
                },
                serviceName: {
                    linux: 'nginx',
                    darwin: 'nginx',
                    win32: 'nginx'
                }
            },
            nodejs: {
                name: 'Node.js Runtime',
                description: 'JavaScript runtime for server applications',
                platforms: ['linux', 'darwin', 'win32'],
                dependencies: [],
                ports: [],
                version: '20'
            },
            docker: {
                name: 'Docker Engine',
                description: 'Container platform for application deployment',
                platforms: ['linux', 'darwin', 'win32'],
                dependencies: [],
                ports: [2375, 2376]
            },
            postgresql: {
                name: 'PostgreSQL Database',
                description: 'Advanced open source relational database',
                platforms: ['linux', 'darwin', 'win32'],
                dependencies: [],
                ports: [5432],
                configPath: {
                    linux: '/etc/postgresql',
                    darwin: '/usr/local/var/postgres',
                    win32: 'C:/Program Files/PostgreSQL'
                }
            },
            redis: {
                name: 'Redis Cache',
                description: 'In-memory data structure store',
                platforms: ['linux', 'darwin', 'win32'],
                dependencies: [],
                ports: [6379]
            },
            ffmpeg: {
                name: 'FFmpeg Media Processing',
                description: 'Complete solution for audio and video processing',
                platforms: ['linux', 'darwin', 'win32'],
                dependencies: [],
                ports: []
            },
            certbot: {
                name: 'Let\'s Encrypt SSL',
                description: 'Automatic SSL certificate management',
                platforms: ['linux', 'darwin'],
                dependencies: ['nginx'],
                ports: []
            },
            fail2ban: {
                name: 'Fail2Ban Security',
                description: 'Intrusion prevention system',
                platforms: ['linux'],
                dependencies: [],
                ports: []
            },
            htop: {
                name: 'System Monitor',
                description: 'Interactive process viewer',
                platforms: ['linux', 'darwin'],
                dependencies: [],
                ports: []
            },
            git: {
                name: 'Git Version Control',
                description: 'Distributed version control system',
                platforms: ['linux', 'darwin', 'win32'],
                dependencies: [],
                ports: []
            },
            vim: {
                name: 'Vim Text Editor',
                description: 'Advanced text editor',
                platforms: ['linux', 'darwin'],
                dependencies: [],
                ports: []
            },
            flexpbx: {
                name: 'FlexPBX Server',
                description: 'Complete PBX server installation',
                platforms: ['linux', 'darwin', 'win32'],
                dependencies: ['nodejs', 'nginx', 'postgresql'],
                ports: [8080, 3000, 5060, 5061]
            }
        };
    }

    /**
     * Detect system information and available package managers
     */
    async detectSystemInfo(connection) {
        try {
            const systemInfo = await this.executeCommand(connection, 'GET_SYSTEM_INFO');
            const platform = systemInfo.systemInfo.platform;

            // Detect package manager
            const packageManager = await this.detectPackageManager(connection, platform);

            // Check installed services
            const installedServices = await this.scanInstalledServices(connection, platform);

            return {
                success: true,
                platform,
                packageManager,
                installedServices,
                systemInfo: systemInfo.systemInfo
            };

        } catch (error) {
            return {
                success: false,
                error: error.message
            };
        }
    }

    /**
     * Detect available package manager
     */
    async detectPackageManager(connection, platform) {
        const packageManagers = {
            linux: [
                { name: 'apt', command: 'which apt-get', install: 'apt-get install -y' },
                { name: 'yum', command: 'which yum', install: 'yum install -y' },
                { name: 'dnf', command: 'which dnf', install: 'dnf install -y' },
                { name: 'pacman', command: 'which pacman', install: 'pacman -S --noconfirm' },
                { name: 'zypper', command: 'which zypper', install: 'zypper install -y' }
            ],
            darwin: [
                { name: 'brew', command: 'which brew', install: 'brew install' },
                { name: 'port', command: 'which port', install: 'port install' }
            ],
            win32: [
                { name: 'choco', command: 'choco --version', install: 'choco install -y' },
                { name: 'winget', command: 'winget --version', install: 'winget install' }
            ]
        };

        const managers = packageManagers[platform] || [];

        for (const manager of managers) {
            try {
                const result = await this.executeRemoteCommand(connection, manager.command);
                if (result.success && !result.stderr) {
                    return {
                        name: manager.name,
                        installCommand: manager.install,
                        available: true
                    };
                }
            } catch (error) {
                continue;
            }
        }

        return {
            name: 'none',
            available: false,
            message: 'No supported package manager found'
        };
    }

    /**
     * Scan for installed services
     */
    async scanInstalledServices(connection, platform) {
        const installed = {};

        for (const [serviceId, service] of Object.entries(this.installationTemplates)) {
            if (!service.platforms.includes(platform)) {
                continue;
            }

            const isInstalled = await this.checkServiceInstalled(connection, serviceId, platform);
            installed[serviceId] = {
                ...service,
                installed: isInstalled.installed,
                version: isInstalled.version,
                status: isInstalled.status
            };
        }

        return installed;
    }

    /**
     * Check if specific service is installed
     */
    async checkServiceInstalled(connection, serviceId, platform) {
        const checkCommands = {
            nginx: {
                linux: 'nginx -v',
                darwin: 'nginx -v',
                win32: 'nginx -v'
            },
            nodejs: {
                linux: 'node --version',
                darwin: 'node --version',
                win32: 'node --version'
            },
            docker: {
                linux: 'docker --version',
                darwin: 'docker --version',
                win32: 'docker --version'
            },
            postgresql: {
                linux: 'psql --version',
                darwin: 'psql --version',
                win32: 'psql --version'
            },
            redis: {
                linux: 'redis-server --version',
                darwin: 'redis-server --version',
                win32: 'redis-server --version'
            },
            ffmpeg: {
                linux: 'ffmpeg -version',
                darwin: 'ffmpeg -version',
                win32: 'ffmpeg -version'
            },
            git: {
                linux: 'git --version',
                darwin: 'git --version',
                win32: 'git --version'
            }
        };

        const command = checkCommands[serviceId]?.[platform];
        if (!command) {
            return { installed: false, version: null, status: 'unsupported' };
        }

        try {
            const result = await this.executeRemoteCommand(connection, command);

            if (result.success && result.stdout) {
                // Extract version from output
                const versionMatch = result.stdout.match(/(\d+\.[\d.]+)/);
                const version = versionMatch ? versionMatch[1] : 'unknown';

                return {
                    installed: true,
                    version,
                    status: 'installed'
                };
            }

            return { installed: false, version: null, status: 'not-installed' };

        } catch (error) {
            return { installed: false, version: null, status: 'error', error: error.message };
        }
    }

    /**
     * Install service on remote server
     */
    async installService(connection, serviceId, options = {}) {
        const {
            autoStart = true,
            configureFirewall = true,
            createConfig = true
        } = options;

        console.log(`📦 Installing ${serviceId}...`);
        this.emit('installationStarted', { service: serviceId });

        try {
            const systemInfo = await this.detectSystemInfo(connection);
            if (!systemInfo.success) {
                throw new Error('Failed to detect system information');
            }

            const service = this.installationTemplates[serviceId];
            if (!service) {
                throw new Error(`Unknown service: ${serviceId}`);
            }

            if (!service.platforms.includes(systemInfo.platform)) {
                throw new Error(`${service.name} not supported on ${systemInfo.platform}`);
            }

            if (!systemInfo.packageManager.available) {
                throw new Error('No package manager available for installation');
            }

            // Check dependencies
            for (const dep of service.dependencies || []) {
                const depStatus = await this.checkServiceInstalled(connection, dep, systemInfo.platform);
                if (!depStatus.installed) {
                    console.log(`📦 Installing dependency: ${dep}`);
                    const depResult = await this.installService(connection, dep, { autoStart: false });
                    if (!depResult.success) {
                        throw new Error(`Failed to install dependency ${dep}: ${depResult.error}`);
                    }
                }
            }

            // Install the service
            const installResult = await this.performServiceInstallation(
                connection,
                serviceId,
                systemInfo.platform,
                systemInfo.packageManager
            );

            if (!installResult.success) {
                throw new Error(installResult.error);
            }

            // Configure service if needed
            if (createConfig) {
                await this.configureService(connection, serviceId, systemInfo.platform);
            }

            // Configure firewall if needed
            if (configureFirewall && service.ports?.length > 0) {
                await this.configureFirewall(connection, service.ports, systemInfo.platform);
            }

            // Start service if requested
            if (autoStart) {
                await this.startService(connection, serviceId, systemInfo.platform);
            }

            // Verify installation
            const verifyResult = await this.checkServiceInstalled(connection, serviceId, systemInfo.platform);

            this.emit('installationCompleted', {
                service: serviceId,
                success: true,
                version: verifyResult.version
            });

            console.log(`✅ Successfully installed ${service.name} ${verifyResult.version || ''}`);

            return {
                success: true,
                service: serviceId,
                version: verifyResult.version,
                status: verifyResult.status
            };

        } catch (error) {
            this.emit('installationFailed', {
                service: serviceId,
                error: error.message
            });

            console.error(`❌ Failed to install ${serviceId}: ${error.message}`);

            return {
                success: false,
                service: serviceId,
                error: error.message
            };
        }
    }

    /**
     * Perform actual service installation
     */
    async performServiceInstallation(connection, serviceId, platform, packageManager) {
        const installCommands = {
            nginx: {
                apt: 'nginx',
                yum: 'nginx',
                dnf: 'nginx',
                brew: 'nginx',
                choco: 'nginx'
            },
            nodejs: {
                apt: 'nodejs npm',
                yum: 'nodejs npm',
                dnf: 'nodejs npm',
                brew: 'node',
                choco: 'nodejs'
            },
            docker: {
                apt: 'docker.io docker-compose',
                yum: 'docker docker-compose',
                dnf: 'docker docker-compose',
                brew: 'docker docker-compose',
                choco: 'docker-desktop'
            },
            postgresql: {
                apt: 'postgresql postgresql-contrib',
                yum: 'postgresql-server postgresql-contrib',
                dnf: 'postgresql-server postgresql-contrib',
                brew: 'postgresql',
                choco: 'postgresql'
            },
            redis: {
                apt: 'redis-server',
                yum: 'redis',
                dnf: 'redis',
                brew: 'redis',
                choco: 'redis-64'
            },
            ffmpeg: {
                apt: 'ffmpeg',
                yum: 'ffmpeg',
                dnf: 'ffmpeg',
                brew: 'ffmpeg',
                choco: 'ffmpeg'
            },
            certbot: {
                apt: 'certbot python3-certbot-nginx',
                yum: 'certbot python3-certbot-nginx',
                dnf: 'certbot python3-certbot-nginx',
                brew: 'certbot'
            },
            fail2ban: {
                apt: 'fail2ban',
                yum: 'fail2ban',
                dnf: 'fail2ban'
            },
            htop: {
                apt: 'htop',
                yum: 'htop',
                dnf: 'htop',
                brew: 'htop'
            },
            git: {
                apt: 'git',
                yum: 'git',
                dnf: 'git',
                brew: 'git',
                choco: 'git'
            },
            vim: {
                apt: 'vim',
                yum: 'vim',
                dnf: 'vim',
                brew: 'vim'
            }
        };

        const packages = installCommands[serviceId]?.[packageManager.name];
        if (!packages) {
            throw new Error(`No installation package defined for ${serviceId} with ${packageManager.name}`);
        }

        // Update package list first (for apt/yum/dnf)
        if (['apt', 'yum', 'dnf'].includes(packageManager.name)) {
            const updateCommands = {
                apt: 'apt-get update',
                yum: 'yum check-update || true',
                dnf: 'dnf check-update || true'
            };

            const updateCmd = updateCommands[packageManager.name];
            if (updateCmd) {
                console.log(`🔄 Updating package lists...`);
                await this.executeRemoteCommand(connection, updateCmd);
            }
        }

        // Install packages
        const installCmd = `${packageManager.installCommand} ${packages}`;
        console.log(`📥 Installing packages: ${packages}`);

        const result = await this.executeRemoteCommand(connection, installCmd);

        if (!result.success) {
            throw new Error(`Installation failed: ${result.stderr || result.error}`);
        }

        // Special post-install steps
        await this.performPostInstallSteps(connection, serviceId, platform, packageManager);

        return { success: true };
    }

    /**
     * Perform post-installation steps
     */
    async performPostInstallSteps(connection, serviceId, platform, packageManager) {
        const postInstallSteps = {
            postgresql: async () => {
                if (platform === 'linux' && ['yum', 'dnf'].includes(packageManager.name)) {
                    // Initialize PostgreSQL database on RHEL/CentOS
                    await this.executeRemoteCommand(connection, 'postgresql-setup initdb');
                }
            },
            docker: async () => {
                if (platform === 'linux') {
                    // Add user to docker group and start service
                    await this.executeRemoteCommand(connection, 'usermod -aG docker $USER || true');
                    await this.executeRemoteCommand(connection, 'systemctl enable docker');
                }
            },
            nginx: async () => {
                // Create basic nginx configuration
                await this.createNginxConfig(connection, platform);
            }
        };

        const postInstall = postInstallSteps[serviceId];
        if (postInstall) {
            await postInstall();
        }
    }

    /**
     * Create basic nginx configuration
     */
    async createNginxConfig(connection, platform) {
        const config = `
# FlexPBX Nginx Configuration
server {
    listen 80;
    server_name _;

    # FlexPBX Web Interface
    location / {
        proxy_pass http://localhost:3000;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # FlexPBX API
    location /api/ {
        proxy_pass http://localhost:8080/api/;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    }

    # Static files
    location /static/ {
        alias /var/www/flexpbx/static/;
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
`;

        const configPaths = {
            linux: '/etc/nginx/sites-available/flexpbx',
            darwin: '/usr/local/etc/nginx/servers/flexpbx.conf',
            win32: 'C:/nginx/conf/flexpbx.conf'
        };

        const configPath = configPaths[platform];
        if (configPath) {
            await this.createRemoteFile(connection, configPath, config);

            // Enable site on Debian/Ubuntu
            if (platform === 'linux') {
                await this.executeRemoteCommand(connection, `ln -sf /etc/nginx/sites-available/flexpbx /etc/nginx/sites-enabled/`);
            }
        }
    }

    /**
     * Configure firewall for service
     */
    async configureFirewall(connection, ports, platform) {
        if (platform !== 'linux') {
            return; // Only configure firewall on Linux for now
        }

        try {
            // Check if UFW is available
            const ufwCheck = await this.executeRemoteCommand(connection, 'which ufw');
            if (ufwCheck.success) {
                for (const port of ports) {
                    await this.executeRemoteCommand(connection, `ufw allow ${port}`);
                }
                return;
            }

            // Check if firewalld is available
            const firewalldCheck = await this.executeRemoteCommand(connection, 'which firewall-cmd');
            if (firewalldCheck.success) {
                for (const port of ports) {
                    await this.executeRemoteCommand(connection, `firewall-cmd --permanent --add-port=${port}/tcp`);
                }
                await this.executeRemoteCommand(connection, 'firewall-cmd --reload');
                return;
            }

            // Check if iptables is available
            const iptablesCheck = await this.executeRemoteCommand(connection, 'which iptables');
            if (iptablesCheck.success) {
                for (const port of ports) {
                    await this.executeRemoteCommand(connection, `iptables -A INPUT -p tcp --dport ${port} -j ACCEPT`);
                }
                return;
            }

        } catch (error) {
            console.warn(`Failed to configure firewall: ${error.message}`);
        }
    }

    /**
     * Start service
     */
    async startService(connection, serviceId, platform) {
        const service = this.installationTemplates[serviceId];
        const serviceName = service.serviceName?.[platform] || serviceId;

        if (platform === 'linux') {
            // Try systemctl first
            try {
                await this.executeRemoteCommand(connection, `systemctl enable ${serviceName}`);
                await this.executeRemoteCommand(connection, `systemctl start ${serviceName}`);
                return { success: true, method: 'systemctl' };
            } catch (error) {
                // Try service command
                try {
                    await this.executeRemoteCommand(connection, `service ${serviceName} start`);
                    return { success: true, method: 'service' };
                } catch (error2) {
                    throw new Error(`Failed to start service: ${error2.message}`);
                }
            }
        } else if (platform === 'darwin') {
            // Try brew services
            try {
                await this.executeRemoteCommand(connection, `brew services start ${serviceName}`);
                return { success: true, method: 'brew' };
            } catch (error) {
                throw new Error(`Failed to start service: ${error.message}`);
            }
        } else if (platform === 'win32') {
            // Try Windows service manager
            try {
                await this.executeRemoteCommand(connection, `net start ${serviceName}`);
                return { success: true, method: 'net' };
            } catch (error) {
                throw new Error(`Failed to start service: ${error.message}`);
            }
        }
    }

    /**
     * Configure service
     */
    async configureService(connection, serviceId, platform) {
        // Service-specific configuration
        if (serviceId === 'flexpbx') {
            return await this.configureFlexPBX(connection, platform);
        }

        return { success: true };
    }

    /**
     * Configure FlexPBX server
     */
    async configureFlexPBX(connection, platform) {
        // Create FlexPBX configuration
        const config = {
            server: {
                port: 3000,
                host: '0.0.0.0'
            },
            database: {
                type: 'postgresql',
                host: 'localhost',
                port: 5432,
                database: 'flexpbx',
                username: 'flexpbx',
                password: 'flexpbx_' + Math.random().toString(36).substring(7)
            },
            pbx: {
                sipPort: 5060,
                rtpPortRange: '10000-20000'
            },
            security: {
                jwtSecret: require('crypto').randomBytes(32).toString('hex'),
                encryptionKey: require('crypto').randomBytes(32).toString('hex')
            }
        };

        const configPath = '/etc/flexpbx/config.json';
        await this.createRemoteFile(connection, configPath, JSON.stringify(config, null, 2));

        // Create systemd service file
        const serviceFile = `
[Unit]
Description=FlexPBX Server
After=network.target postgresql.service

[Service]
Type=simple
User=flexpbx
WorkingDirectory=/opt/flexpbx
ExecStart=/usr/bin/node server.js
Restart=always
RestartSec=10
Environment=NODE_ENV=production

[Install]
WantedBy=multi-user.target
`;

        if (platform === 'linux') {
            await this.createRemoteFile(connection, '/etc/systemd/system/flexpbx.service', serviceFile);
            await this.executeRemoteCommand(connection, 'systemctl daemon-reload');
        }

        return { success: true };
    }

    /**
     * Execute remote command
     */
    async executeRemoteCommand(connection, command) {
        try {
            const ConnectionManager = require('./ConnectionManager');
            const connectionManager = new ConnectionManager();

            return await connectionManager.sendCommand(connection, 'EXECUTE_COMMAND', {
                command: command,
                options: { timeout: 30000 }
            });

        } catch (error) {
            return {
                success: false,
                error: error.message
            };
        }
    }

    /**
     * Execute command via connection manager
     */
    async executeCommand(connection, commandType, data = {}) {
        try {
            const ConnectionManager = require('./ConnectionManager');
            const connectionManager = new ConnectionManager();

            return await connectionManager.sendCommand(connection, commandType, data);

        } catch (error) {
            throw new Error(`Command execution failed: ${error.message}`);
        }
    }

    /**
     * Create file on remote server
     */
    async createRemoteFile(connection, filePath, content) {
        try {
            const base64Content = Buffer.from(content).toString('base64');

            await this.executeCommand(connection, 'UPLOAD_FILE', {
                filename: path.basename(filePath),
                content: base64Content,
                destination: filePath
            });

            return { success: true };

        } catch (error) {
            throw new Error(`Failed to create file ${filePath}: ${error.message}`);
        }
    }

    /**
     * Get installation recommendations
     */
    getInstallationRecommendations(platform, useCase = 'production') {
        const recommendations = {
            development: ['nodejs', 'git', 'vim', 'htop'],
            testing: ['nodejs', 'docker', 'git', 'nginx'],
            production: ['nginx', 'nodejs', 'postgresql', 'redis', 'certbot', 'fail2ban', 'htop', 'git'],
            pbx: ['flexpbx', 'nginx', 'postgresql', 'redis', 'ffmpeg', 'certbot']
        };

        const suggested = recommendations[useCase] || recommendations.production;

        return suggested.map(serviceId => ({
            serviceId,
            ...this.installationTemplates[serviceId],
            recommended: true,
            priority: suggested.indexOf(serviceId) + 1
        }));
    }

    /**
     * Bulk install services
     */
    async bulkInstall(connection, serviceIds, options = {}) {
        const results = [];

        for (const serviceId of serviceIds) {
            this.emit('bulkInstallProgress', {
                current: serviceId,
                completed: results.length,
                total: serviceIds.length
            });

            const result = await this.installService(connection, serviceId, options);
            results.push({ serviceId, ...result });

            if (!result.success && options.stopOnError) {
                break;
            }
        }

        return {
            success: results.every(r => r.success),
            results,
            installed: results.filter(r => r.success).length,
            failed: results.filter(r => !r.success).length
        };
    }
}

module.exports = RemoteInstallationService;