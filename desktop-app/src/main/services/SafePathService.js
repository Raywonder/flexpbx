const path = require('path');
const fs = require('fs-extra');
const os = require('os');

class SafePathService {
    constructor() {
        this.localPaths = this.getLocalSafePaths();
        this.remotePaths = this.getRemoteSafePaths();
    }

    /**
     * Get safe installation paths for local computer
     */
    getLocalSafePaths() {
        const homeDir = os.homedir();
        const platform = os.platform();

        const paths = {
            recommended: null,
            alternatives: [],
            docker: {
                recommended: null,
                alternatives: []
            }
        };

        if (platform === 'darwin') { // macOS
            paths.recommended = path.join(homeDir, 'Applications', 'FlexPBX');
            paths.alternatives = [
                path.join(homeDir, 'Documents', 'FlexPBX'),
                path.join(homeDir, 'Desktop', 'FlexPBX'),
                '/Applications/FlexPBX' // System-wide (requires admin)
            ];
            paths.docker.recommended = path.join(homeDir, 'Docker', 'FlexPBX');
            paths.docker.alternatives = [
                path.join(homeDir, 'Documents', 'Docker', 'FlexPBX'),
                '/opt/flexpbx' // System-wide Docker
            ];
        } else if (platform === 'win32') { // Windows
            const programFiles = process.env.PROGRAMFILES || 'C:\\Program Files';
            const localAppData = process.env.LOCALAPPDATA || path.join(homeDir, 'AppData', 'Local');

            paths.recommended = path.join(localAppData, 'FlexPBX');
            paths.alternatives = [
                path.join(homeDir, 'Documents', 'FlexPBX'),
                path.join(homeDir, 'Desktop', 'FlexPBX'),
                path.join(programFiles, 'FlexPBX') // System-wide (requires admin)
            ];
            paths.docker.recommended = path.join(localAppData, 'Docker', 'FlexPBX');
            paths.docker.alternatives = [
                'C:\\Docker\\FlexPBX',
                path.join(homeDir, 'Documents', 'Docker', 'FlexPBX')
            ];
        } else { // Linux
            paths.recommended = path.join(homeDir, '.local', 'share', 'flexpbx');
            paths.alternatives = [
                path.join(homeDir, 'flexpbx'),
                path.join(homeDir, 'Documents', 'flexpbx'),
                '/opt/flexpbx' // System-wide (requires sudo)
            ];
            paths.docker.recommended = path.join(homeDir, '.local', 'share', 'docker', 'flexpbx');
            paths.docker.alternatives = [
                path.join(homeDir, 'docker', 'flexpbx'),
                '/opt/docker/flexpbx'
            ];
        }

        return paths;
    }

    /**
     * Get safe installation paths for remote servers
     */
    getRemoteSafePaths() {
        return {
            linux: {
                webAccessible: [
                    'public_html/flexpbx',
                    'www/flexpbx',
                    'htdocs/flexpbx',
                    'web/flexpbx',
                    'domains/{domain}/public_html/flexpbx'
                ],
                applications: [
                    'apps/flexpbx',
                    'applications/flexpbx',
                    '.local/share/flexpbx',
                    'software/flexpbx'
                ],
                docker: [
                    'docker/flexpbx',
                    'containers/flexpbx',
                    '.local/share/docker/flexpbx'
                ],
                system: [
                    '/opt/flexpbx',
                    '/usr/local/flexpbx',
                    '/var/www/flexpbx'
                ]
            },
            darwin: { // macOS
                webAccessible: [
                    'Sites/flexpbx',
                    'public_html/flexpbx',
                    'www/flexpbx'
                ],
                applications: [
                    'Applications/FlexPBX',
                    'Documents/FlexPBX',
                    '.local/share/flexpbx',
                    'Development/flexpbx'
                ],
                docker: [
                    'Docker/flexpbx',
                    'containers/flexpbx',
                    'Documents/Docker/flexpbx'
                ],
                system: [
                    '/Applications/FlexPBX',
                    '/usr/local/flexpbx',
                    '/opt/homebrew/flexpbx'
                ]
            },
            win32: { // Windows
                webAccessible: [
                    'inetpub/wwwroot/flexpbx',
                    'htdocs/flexpbx',
                    'www/flexpbx',
                    'public_html/flexpbx'
                ],
                applications: [
                    'Documents/FlexPBX',
                    'AppData/Local/FlexPBX',
                    'Desktop/FlexPBX'
                ],
                docker: [
                    'Documents/Docker/FlexPBX',
                    'AppData/Local/Docker/FlexPBX'
                ],
                system: [
                    'C:/Program Files/FlexPBX',
                    'C:/Program Files (x86)/FlexPBX',
                    'C:/FlexPBX'
                ]
            }
        };
    }

    /**
     * Auto-detect best installation path for local computer
     */
    async detectLocalPath(installType = 'standard') {
        const paths = installType === 'docker' ? this.localPaths.docker : this.localPaths;

        // Check if recommended path is writable
        try {
            await fs.ensureDir(path.dirname(paths.recommended));
            await fs.access(path.dirname(paths.recommended), fs.constants.W_OK);
            return {
                path: paths.recommended,
                type: 'recommended',
                writable: true,
                reason: 'User directory with write permissions'
            };
        } catch (error) {
            // Try alternatives
            for (const altPath of paths.alternatives) {
                try {
                    await fs.ensureDir(path.dirname(altPath));
                    await fs.access(path.dirname(altPath), fs.constants.W_OK);
                    return {
                        path: altPath,
                        type: 'alternative',
                        writable: true,
                        reason: 'Alternative location with write permissions'
                    };
                } catch (err) {
                    continue;
                }
            }
        }

        // Fallback to home directory
        const fallback = path.join(os.homedir(), 'FlexPBX');
        return {
            path: fallback,
            type: 'fallback',
            writable: true,
            reason: 'Home directory fallback'
        };
    }

    /**
     * Detect custom paths on remote server
     */
    async detectCustomRemotePaths(server) {
        const sshService = require('./sshService');
        const customPaths = [];

        try {
            const ssh = await sshService.connect(server);
            const homeResult = await ssh.execCommand('echo $HOME');
            const homeDir = homeResult.stdout.trim();

            // Common custom directory patterns to scan
            const scanPatterns = [
                // Web-accessible patterns
                'public_html/*', 'www/*', 'htdocs/*', 'web/*',
                // Application patterns
                'apps/*', 'applications/*', 'software/*', 'projects/*',
                // Custom patterns
                'sites/*', 'domains/*', 'clients/*', 'work/*',
                // Development patterns
                'dev/*', 'development/*', 'staging/*', 'test/*'
            ];

            for (const pattern of scanPatterns) {
                try {
                    // Look for existing directories that match pattern
                    const searchCmd = `find ${homeDir} -maxdepth 2 -type d -path "*/${pattern.replace('*', '')}" 2>/dev/null | head -10`;
                    const result = await ssh.execCommand(searchCmd);

                    if (result.stdout.trim()) {
                        const foundPaths = result.stdout.trim().split('\n');
                        for (const foundPath of foundPaths) {
                            // Test if directory is writable
                            const testResult = await ssh.execCommand(`test -w "${foundPath}" && echo "writable"`);
                            if (testResult.stdout.includes('writable')) {
                                customPaths.push({
                                    path: foundPath,
                                    type: this.categorizeCustomPath(foundPath),
                                    pattern: pattern,
                                    description: this.getCustomPathDescription(foundPath)
                                });
                            }
                        }
                    }
                } catch (err) {
                    // Continue with next pattern
                    continue;
                }
            }

            // Also check for existing PHP/web applications to suggest subdirectories
            await this.detectExistingApplications(ssh, homeDir, customPaths);

            await ssh.dispose();
            return customPaths;

        } catch (error) {
            return [];
        }
    }

    /**
     * Detect existing applications on server
     */
    async detectExistingApplications(ssh, homeDir, customPaths) {
        const appPatterns = [
            { name: 'WordPress', files: ['wp-config.php', 'wp-admin'], subdir: 'flexpbx' },
            { name: 'Joomla', files: ['configuration.php', 'administrator'], subdir: 'pbx' },
            { name: 'Drupal', files: ['sites/default', 'core'], subdir: 'flexpbx' },
            { name: 'Laravel', files: ['artisan', 'app/Http'], subdir: 'flexpbx' },
            { name: 'CodeIgniter', files: ['application', 'system'], subdir: 'flexpbx' },
            { name: 'cPanel', files: ['public_html/.cpanel'], subdir: 'apps/flexpbx' },
            { name: 'WHMCS', files: ['configuration.php', 'whmcs'], subdir: 'flexpbx' },
            { name: 'Composer', files: ['composer.json', 'vendor'], subdir: 'flexpbx' }
        ];

        for (const app of appPatterns) {
            try {
                // Search for application indicators
                const searchCmd = `find ${homeDir} -maxdepth 3 -name "${app.files[0]}" 2>/dev/null | head -5`;
                const result = await ssh.execCommand(searchCmd);

                if (result.stdout.trim()) {
                    const foundPaths = result.stdout.trim().split('\n');
                    for (const filePath of foundPaths) {
                        const appDir = path.posix.dirname(filePath);
                        const suggestedPath = path.posix.join(appDir, app.subdir);

                        // Check if we can create subdirectory
                        const testResult = await ssh.execCommand(`test -w "${appDir}" && echo "writable"`);
                        if (testResult.stdout.includes('writable')) {
                            customPaths.push({
                                path: suggestedPath,
                                type: 'application-subdir',
                                parentApp: app.name,
                                description: `Subdirectory within existing ${app.name} installation`
                            });
                        }
                    }
                }
            } catch (err) {
                continue;
            }
        }

        // Detect control panel APIs and suggest appropriate paths
        await this.detectControlPanelAPIs(ssh, homeDir, customPaths);
    }

    /**
     * Detect control panel APIs and mail server configurations
     */
    async detectControlPanelAPIs(ssh, homeDir, customPaths) {
        const controlPanels = [
            {
                name: 'cPanel',
                indicators: ['/usr/local/cpanel', '~/.cpanel', 'public_html/.cpanel'],
                apiPath: '/usr/local/cpanel/bin/uapi',
                suggestedPaths: ['public_html/flexpbx', 'apps/flexpbx']
            },
            {
                name: 'WHM',
                indicators: ['/usr/local/cpanel/whostmgr', '/var/cpanel/users'],
                apiPath: '/usr/local/cpanel/bin/whmapi1',
                suggestedPaths: ['/usr/local/flexpbx', 'apps/flexpbx']
            },
            {
                name: 'WHMCS',
                indicators: ['whmcs/configuration.php', 'billing/configuration.php'],
                apiPath: 'includes/api.php',
                suggestedPaths: ['whmcs/modules/flexpbx', 'billing/modules/flexpbx']
            },
            {
                name: 'Plesk',
                indicators: ['/opt/psa', '/usr/local/psa'],
                apiPath: '/opt/psa/bin/extension',
                suggestedPaths: ['/var/www/vhosts/system/flexpbx', 'httpdocs/flexpbx']
            },
            {
                name: 'DirectAdmin',
                indicators: ['/usr/local/directadmin', '/home/admin/admin.conf'],
                apiPath: '/usr/local/directadmin/plugins',
                suggestedPaths: ['public_html/flexpbx', 'domains/*/public_html/flexpbx']
            }
        ];

        for (const panel of controlPanels) {
            let detected = false;

            for (const indicator of panel.indicators) {
                try {
                    const checkPath = indicator.startsWith('/') ? indicator : path.posix.join(homeDir, indicator);
                    const testResult = await ssh.execCommand(`test -e "${checkPath}" && echo "exists"`);

                    if (testResult.stdout.includes('exists')) {
                        detected = true;
                        break;
                    }
                } catch (err) {
                    continue;
                }
            }

            if (detected) {
                // Add suggested paths for this control panel
                for (const suggestedPath of panel.suggestedPaths) {
                    const fullPath = suggestedPath.startsWith('/') ? suggestedPath :
                                   path.posix.join(homeDir, suggestedPath.replace('*', 'default'));

                    // Test if parent directory is writable
                    const parentDir = path.posix.dirname(fullPath);
                    try {
                        const testResult = await ssh.execCommand(`test -w "${parentDir}" && echo "writable"`);
                        if (testResult.stdout.includes('writable')) {
                            customPaths.push({
                                path: fullPath,
                                type: 'control-panel',
                                controlPanel: panel.name,
                                apiPath: panel.apiPath,
                                description: `${panel.name} integrated installation path`
                            });
                        }
                    } catch (err) {
                        continue;
                    }
                }
            }
        }

        // Detect mail server configurations for piping capabilities
        await this.detectMailServerConfig(ssh, customPaths);
    }

    /**
     * Detect mail server configurations for piping to other programs
     */
    async detectMailServerConfig(ssh, customPaths) {
        const mailServerChecks = [
            {
                name: 'Postfix',
                configFile: '/etc/postfix/main.cf',
                pipePath: '/etc/aliases',
                description: 'Postfix mail server with pipe transport support'
            },
            {
                name: 'Sendmail',
                configFile: '/etc/mail/sendmail.cf',
                pipePath: '/etc/aliases',
                description: 'Sendmail with program delivery support'
            },
            {
                name: 'Exim',
                configFile: '/etc/exim4/exim4.conf',
                pipePath: '/etc/aliases',
                description: 'Exim mail server with pipe transport'
            },
            {
                name: 'qmail',
                configFile: '/var/qmail/control',
                pipePath: '/var/qmail/alias',
                description: 'qmail with program delivery'
            }
        ];

        for (const mailServer of mailServerChecks) {
            try {
                const testResult = await ssh.execCommand(`test -f "${mailServer.configFile}" && echo "exists"`);

                if (testResult.stdout.includes('exists')) {
                    // Check if we can modify mail configuration
                    const aliasTest = await ssh.execCommand(`test -w "${path.posix.dirname(mailServer.pipePath)}" && echo "writable"`);

                    if (aliasTest.stdout.includes('writable')) {
                        customPaths.push({
                            path: '/usr/local/bin/flexpbx',
                            type: 'mail-integration',
                            mailServer: mailServer.name,
                            pipePath: mailServer.pipePath,
                            description: `${mailServer.description} - suitable for mail piping integration`
                        });
                    }
                }
            } catch (err) {
                continue;
            }
        }
    }

    /**
     * Categorize custom path type
     */
    categorizeCustomPath(pathStr) {
        if (pathStr.includes('public_html') || pathStr.includes('www') || pathStr.includes('htdocs') || pathStr.includes('web')) {
            return 'web-accessible';
        } else if (pathStr.includes('apps') || pathStr.includes('applications') || pathStr.includes('software')) {
            return 'application';
        } else if (pathStr.includes('dev') || pathStr.includes('development') || pathStr.includes('staging') || pathStr.includes('test')) {
            return 'development';
        } else if (pathStr.includes('sites') || pathStr.includes('domains') || pathStr.includes('clients')) {
            return 'multi-site';
        } else {
            return 'custom';
        }
    }

    /**
     * Get description for custom path
     */
    getCustomPathDescription(pathStr) {
        const type = this.categorizeCustomPath(pathStr);
        switch (type) {
            case 'web-accessible':
                return 'Custom web-accessible directory for browser access';
            case 'application':
                return 'Custom application directory for software installations';
            case 'development':
                return 'Development/testing environment directory';
            case 'multi-site':
                return 'Multi-site or client-specific directory structure';
            default:
                return 'Custom user-defined directory';
        }
    }

    /**
     * Detect best installation path for remote server
     */
    async detectRemotePath(server, installType = 'standard', webAccessible = false) {
        const sshService = require('./sshService');

        try {
            const ssh = await sshService.connect(server);

            // Detect remote OS
            const osResult = await ssh.execCommand('uname -s');
            const remoteOS = osResult.stdout.trim().toLowerCase();
            let remotePlatform = 'linux'; // default

            if (remoteOS.includes('darwin')) {
                remotePlatform = 'darwin';
            } else if (remoteOS.includes('cygwin') || remoteOS.includes('mingw')) {
                remotePlatform = 'win32';
            }

            // Get user home directory (platform-specific)
            let homeDir;
            if (remotePlatform === 'win32') {
                const homeDirResult = await ssh.execCommand('echo %USERPROFILE%');
                homeDir = homeDirResult.stdout.trim().replace(/\\/g, '/');
            } else {
                const homeDirResult = await ssh.execCommand('echo $HOME');
                homeDir = homeDirResult.stdout.trim();
            }

            // First, detect custom paths
            const customPaths = await this.detectCustomRemotePaths(server);

            // Filter custom paths based on requirements
            const suitableCustomPaths = customPaths.filter(cp => {
                if (webAccessible && cp.type === 'web-accessible') return true;
                if (!webAccessible && cp.type !== 'web-accessible') return true;
                return false;
            });

            // If we found suitable custom paths, prioritize them
            if (suitableCustomPaths.length > 0) {
                const bestCustom = suitableCustomPaths[0];
                await ssh.dispose();
                return {
                    path: bestCustom.path,
                    type: 'custom-detected',
                    writable: true,
                    reason: bestCustom.description,
                    customPaths: suitableCustomPaths,
                    platform: remotePlatform
                };
            }

            // Get platform-specific paths
            const remotePaths = this.remotePaths[remotePlatform] || this.remotePaths.linux;
            let pathsToCheck = [];

            if (webAccessible) {
                pathsToCheck = remotePaths.webAccessible.map(p =>
                    p.replace('{domain}', server.domain || 'default')
                );
            } else if (installType === 'docker') {
                pathsToCheck = remotePaths.docker;
            } else {
                pathsToCheck = remotePaths.applications;
            }

            // Convert relative paths to absolute
            pathsToCheck = pathsToCheck.map(p =>
                p.startsWith('/') ? p : path.posix.join(homeDir, p)
            );

            // Test each path for writability
            for (const testPath of pathsToCheck) {
                try {
                    const testResult = await ssh.execCommand(`mkdir -p ${path.posix.dirname(testPath)} && test -w ${path.posix.dirname(testPath)} && echo "writable"`);
                    if (testResult.stdout.includes('writable')) {
                        await ssh.dispose();
                        return {
                            path: testPath,
                            type: webAccessible ? 'web-accessible' : 'application',
                            writable: true,
                            reason: this.getPathReason(testPath, webAccessible, installType),
                            customPaths: customPaths
                        };
                    }
                } catch (err) {
                    continue;
                }
            }

            // Fallback to user home directory
            const fallback = path.posix.join(homeDir, 'flexpbx');
            await ssh.dispose();

            return {
                path: fallback,
                type: 'fallback',
                writable: true,
                reason: 'User home directory fallback',
                customPaths: customPaths
            };

        } catch (error) {
            throw new Error(`Failed to detect remote path: ${error.message}`);
        }
    }

    /**
     * Get reason for path selection
     */
    getPathReason(pathStr, webAccessible, installType) {
        if (pathStr.includes('public_html') || pathStr.includes('www') || pathStr.includes('htdocs')) {
            return 'Web accessible directory for browser access';
        } else if (pathStr.includes('apps') || pathStr.includes('applications')) {
            return 'Application directory for software installations';
        } else if (pathStr.includes('docker') || pathStr.includes('containers')) {
            return 'Container directory for Docker deployments';
        } else if (pathStr.startsWith('/opt') || pathStr.startsWith('/usr/local')) {
            return 'System directory for server-wide installations';
        } else {
            return 'User directory for personal installations';
        }
    }

    /**
     * Validate installation path safety
     */
    async validatePath(pathStr, isRemote = false) {
        const issues = [];
        const warnings = [];

        // Check for dangerous system paths
        const dangerousPaths = [
            '/bin', '/sbin', '/usr/bin', '/usr/sbin',
            '/etc', '/var/log', '/tmp', '/dev',
            'C:\\Windows', 'C:\\Program Files\\Windows',
            '/System', '/Library/System'
        ];

        for (const dangerous of dangerousPaths) {
            if (pathStr.startsWith(dangerous)) {
                issues.push(`Path is in system directory: ${dangerous}`);
            }
        }

        // Check for existing important files
        const importantFiles = [
            'index.php', 'wp-config.php', 'database.php',
            '.htaccess', 'web.config', 'composer.json'
        ];

        if (!isRemote) {
            try {
                const exists = await fs.pathExists(pathStr);
                if (exists) {
                    const files = await fs.readdir(pathStr);
                    for (const file of files) {
                        if (importantFiles.includes(file)) {
                            warnings.push(`Directory contains important file: ${file}`);
                        }
                    }
                }
            } catch (error) {
                // Path doesn't exist or isn't accessible - that's fine
            }
        }

        // Check path length (Windows limitation)
        if (os.platform() === 'win32' && pathStr.length > 260) {
            issues.push('Path too long for Windows (>260 characters)');
        }

        // Check for special characters
        const invalidChars = /[<>:"|?*]/;
        if (invalidChars.test(pathStr)) {
            issues.push('Path contains invalid characters');
        }

        return {
            valid: issues.length === 0,
            issues,
            warnings,
            recommended: issues.length === 0 && warnings.length === 0
        };
    }

    /**
     * Get installation recommendations based on use case
     */
    getInstallationRecommendations(useCase) {
        const recommendations = {
            'development': {
                local: 'Use Docker installation in user directory for isolation',
                remote: 'Install in apps/ directory with development settings',
                webAccess: false
            },
            'production': {
                local: 'Use system-wide installation with proper security',
                remote: 'Install in web-accessible directory with SSL',
                webAccess: true
            },
            'testing': {
                local: 'Use Docker for easy cleanup and testing',
                remote: 'Install in isolated subdirectory',
                webAccess: false
            },
            'demo': {
                local: 'Use standard installation with demo data',
                remote: 'Install in public_html subdirectory for easy access',
                webAccess: true
            }
        };

        return recommendations[useCase] || recommendations['development'];
    }

    /**
     * Detect Docker availability and recommend installation type
     */
    async detectDockerAvailability(server = null) {
        if (server) {
            // Check Docker on remote server
            const sshService = require('./sshService');
            try {
                const ssh = await sshService.connect(server);
                const dockerCheck = await ssh.execCommand('docker --version && docker info');
                await ssh.dispose();

                return {
                    available: dockerCheck.stdout.includes('Docker version'),
                    recommended: true,
                    reason: 'Docker available - recommended for isolated, portable installation'
                };
            } catch (error) {
                return {
                    available: false,
                    recommended: false,
                    reason: 'Docker not available - standard installation required'
                };
            }
        } else {
            // Check Docker on local machine
            try {
                const { exec } = require('child_process');
                const { promisify } = require('util');
                const execAsync = promisify(exec);

                const dockerCheck = await execAsync('docker --version');
                return {
                    available: dockerCheck.stdout.includes('Docker version'),
                    recommended: true,
                    reason: 'Docker available - recommended for development and testing'
                };
            } catch (error) {
                return {
                    available: false,
                    recommended: false,
                    reason: 'Docker not available - standard installation will be used'
                };
            }
        }
    }

    /**
     * Get installation type recommendations based on environment
     */
    getInstallationTypeRecommendations(server = null, useCase = 'development') {
        const recommendations = {
            docker: {
                pros: [
                    'Isolated environment prevents conflicts',
                    'Easy backup and migration',
                    'Consistent across different servers',
                    'Simple cleanup and removal',
                    'Built-in security isolation'
                ],
                cons: [
                    'Requires Docker installation',
                    'Slightly more resource usage',
                    'Additional container management'
                ],
                bestFor: ['development', 'testing', 'staging', 'production-isolation']
            },
            standard: {
                pros: [
                    'Direct system integration',
                    'Lower resource overhead',
                    'Easier mail server integration',
                    'Native system service management'
                ],
                cons: [
                    'Potential conflicts with existing software',
                    'More complex cleanup',
                    'System-dependent configuration'
                ],
                bestFor: ['production', 'dedicated-server', 'mail-integration']
            }
        };

        // Determine recommended type based on use case
        let recommendedType = 'standard';
        if (['development', 'testing', 'staging'].includes(useCase)) {
            recommendedType = 'docker';
        } else if (server && server.mailIntegration) {
            recommendedType = 'standard';
        }

        return {
            recommended: recommendedType,
            options: recommendations
        };
    }

    /**
     * Generate installation path suggestions with context
     */
    async generatePathSuggestions(server = null, options = {}) {
        const {
            installType = 'standard', // 'standard', 'docker'
            webAccessible = false,
            useCase = 'development'
        } = options;

        const suggestions = [];

        // Check Docker availability and add recommendations
        const dockerInfo = await this.detectDockerAvailability(server);
        const typeRecommendations = this.getInstallationTypeRecommendations(server, useCase);

        if (server) {
            // Remote installation
            try {
                const detected = await this.detectRemotePath(server, installType, webAccessible);
                suggestions.push({
                    ...detected,
                    priority: 1,
                    description: `Recommended ${installType} installation path`
                });

                // Add alternative suggestions
                const paths = webAccessible ? this.remotePaths.webAccessible :
                             installType === 'docker' ? this.remotePaths.docker :
                             this.remotePaths.applications;

                for (const pathTemplate of paths.slice(1, 3)) {
                    const fullPath = pathTemplate.startsWith('/') ? pathTemplate :
                                   `~/${pathTemplate.replace('{domain}', server.domain || 'default')}`;

                    suggestions.push({
                        path: fullPath,
                        type: 'alternative',
                        priority: 2,
                        description: this.getPathReason(fullPath, webAccessible, installType)
                    });
                }
            } catch (error) {
                // Add fallback suggestions if detection fails
                suggestions.push({
                    path: '~/flexpbx',
                    type: 'fallback',
                    priority: 3,
                    description: 'Safe fallback in user home directory'
                });
            }
        } else {
            // Local installation
            const detected = await this.detectLocalPath(installType);
            suggestions.push({
                ...detected,
                priority: 1,
                description: `Recommended ${installType} installation path`
            });

            // Add alternatives
            const paths = installType === 'docker' ? this.localPaths.docker : this.localPaths;
            for (const altPath of paths.alternatives.slice(0, 2)) {
                suggestions.push({
                    path: altPath,
                    type: 'alternative',
                    priority: 2,
                    writable: true, // We'll validate later
                    description: this.getPathReason(altPath, webAccessible, installType)
                });
            }
        }

        // Add use case specific suggestions
        const recommendations = this.getInstallationRecommendations(useCase);

        return {
            suggestions: suggestions.sort((a, b) => a.priority - b.priority),
            recommendations,
            useCase,
            dockerInfo,
            typeRecommendations,
            installationOptions: {
                docker: dockerInfo.available,
                standard: true,
                recommended: dockerInfo.available ? typeRecommendations.recommended : 'standard'
            }
        };
    }
}

module.exports = SafePathService;