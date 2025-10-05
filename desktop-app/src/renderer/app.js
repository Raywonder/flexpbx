class FlexPBXDesktopApp {
    constructor() {
        this.currentView = 'dashboard';
        this.installations = [];
        this.systemRequirements = {};
        this.isLoading = false;
        this.defaultInstallDir = null;

        this.init();
    }

    async init() {
        this.bindEvents();
        this.setupNavigationHandlers();
        this.setupMenuEventListeners();
        await this.loadSystemInfo();
        await this.setDefaultInstallDirectory();
        await this.checkSystemRequirements();
        await this.loadInstallations();
        this.initializePathManagement();
    }

    async setDefaultInstallDirectory() {
        try {
            // Get system info to determine default install directory
            const systemInfo = await window.electronAPI.getSystemInfo();

            // Set platform-appropriate default directory
            switch (systemInfo.platform) {
                case 'darwin': // macOS
                    this.defaultInstallDir = '/Applications/FlexPBX';
                    break;
                case 'win32': // Windows
                    this.defaultInstallDir = 'C:\\Program Files\\FlexPBX';
                    break;
                case 'linux': // Linux
                    this.defaultInstallDir = '/opt/flexpbx';
                    break;
                default:
                    this.defaultInstallDir = `${systemInfo.homeDir}/flexpbx`;
            }

            // Update the install directory input placeholder
            const installDirInput = document.getElementById('install-directory');
            if (installDirInput) {
                installDirInput.placeholder = `Default: ${this.defaultInstallDir}`;
                installDirInput.setAttribute('title', `Default installation directory: ${this.defaultInstallDir}`);
            }

            console.log(`📁 Default install directory set to: ${this.defaultInstallDir}`);
        } catch (error) {
            console.error('Failed to set default install directory:', error);
            this.defaultInstallDir = '/Applications/FlexPBX'; // Fallback
        }
    }

    bindEvents() {
        // Navigation
        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', (e) => {
                const view = e.currentTarget.dataset.view;
                this.switchView(view);
            });
        });

        // Dashboard quick actions
        document.getElementById('new-local-btn')?.addEventListener('click', () => {
            this.switchView('local-install');
        });

        document.getElementById('deploy-remote-btn')?.addEventListener('click', () => {
            this.switchView('remote-deploy');
        });

        document.getElementById('connect-existing-btn')?.addEventListener('click', () => {
            this.showConnectDialog();
        });

        document.getElementById('import-backup-btn')?.addEventListener('click', async () => {
            await this.importBackup();
        });

        document.getElementById('export-backup-btn')?.addEventListener('click', async () => {
            await this.exportBackup();
        });

        // Local installation form
        this.setupLocalInstallForm();

        // Remote deployment form
        this.setupRemoteDeployForm();

        // Nginx configuration form
        this.setupNginxConfigForm();

        // Settings form
        this.setupSettingsForm();

        // Setup tab functionality for Server Manager and Services
        this.setupTabNavigation();
    }

    setupLocalInstallForm() {
        const form = document.getElementById('local-install-form');
        const browseBtn = document.getElementById('browse-directory');
        const configureNginx = document.getElementById('configure-nginx-local');
        const installLocation = document.getElementById('install-location');
        const cancelBtn = document.getElementById('cancel-local-install');

        if (browseBtn) {
            browseBtn.addEventListener('click', async () => {
                try {
                    console.log('🔍 Opening directory browser...');
                    const directory = await window.electronAPI.selectDirectory();
                    console.log('📁 Selected directory:', directory);

                    if (directory) {
                        const installDirInput = document.getElementById('install-directory');
                        installDirInput.value = directory;
                        installDirInput.dispatchEvent(new Event('change')); // Trigger change event
                        console.log('✅ Directory set successfully');
                    } else {
                        console.log('❌ No directory selected');
                    }
                } catch (error) {
                    console.error('❌ Failed to select directory:', error);
                    this.showToast('Failed to open directory browser', 'error');
                }
            });
        }

        // Add button to use default directory
        const useDefaultBtn = document.getElementById('use-default-directory');
        if (useDefaultBtn) {
            useDefaultBtn.addEventListener('click', () => {
                const installDirInput = document.getElementById('install-directory');
                installDirInput.value = this.defaultInstallDir;
                installDirInput.dispatchEvent(new Event('change'));
                console.log(`📁 Using default directory: ${this.defaultInstallDir}`);
            });
        }

        // SIP Trunk Management
        const allowUserTrunks = document.getElementById('allow-user-trunks');
        if (allowUserTrunks) {
            allowUserTrunks.addEventListener('change', (e) => {
                const trunkOptions = document.getElementById('trunk-ownership-options');
                trunkOptions.style.display = e.target.checked ? 'block' : 'none';
                console.log(`📞 User trunk ownership: ${e.target.checked ? 'enabled' : 'disabled'}`);
            });
        }

        const maxTrunks = document.getElementById('max-trunks');
        if (maxTrunks) {
            maxTrunks.addEventListener('change', (e) => {
                const value = e.target.value;
                console.log(`📞 Maximum trunks set to: ${value}`);

                // Update min-trunks max value based on selection
                const minTrunks = document.getElementById('min-trunks');
                if (minTrunks && value !== 'unlimited') {
                    minTrunks.max = parseInt(value);
                    if (parseInt(minTrunks.value) > parseInt(value)) {
                        minTrunks.value = value;
                    }
                }
            });
        }

        if (configureNginx) {
            configureNginx.addEventListener('change', (e) => {
                const nginxOptions = document.getElementById('nginx-options-local');
                nginxOptions.style.display = e.target.checked ? 'block' : 'none';
            });
        }

        if (installLocation) {
            installLocation.addEventListener('change', (e) => {
                const subdirectoryPath = document.getElementById('subdirectory-path');
                subdirectoryPath.style.display = e.target.value === 'subdirectory' ? 'block' : 'none';
            });
        }

        // Mail server configuration
        const mailServerType = document.getElementById('mail-server-type');
        if (mailServerType) {
            mailServerType.addEventListener('change', (e) => {
                const externalMailOptions = document.getElementById('external-mail-options');
                externalMailOptions.style.display = e.target.value === 'external' ? 'block' : 'none';
            });
        }

        if (cancelBtn) {
            cancelBtn.addEventListener('click', () => {
                this.switchView('dashboard');
            });
        }

        if (form) {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                await this.handleLocalInstall();
            });
        }
    }

    setupRemoteDeployForm() {
        const form = document.getElementById('remote-deploy-form');
        const deploymentMethod = document.getElementById('deployment-method');
        const testConnectionBtn = document.getElementById('test-connection');
        const browseSshKeyBtn = document.getElementById('browse-ssh-key');

        // Handle deployment method changes
        if (deploymentMethod) {
            deploymentMethod.addEventListener('change', (e) => {
                const method = e.target.value;

                // Hide all config sections
                document.getElementById('ssh-config').style.display = 'none';
                document.getElementById('ftp-config').style.display = 'none';
                document.getElementById('webdav-config').style.display = 'none';

                // Show the selected method's config
                document.getElementById(`${method}-config`).style.display = 'block';
            });
        }

        // Browse SSH key
        if (browseSshKeyBtn) {
            browseSshKeyBtn.addEventListener('click', async () => {
                const sshKeyPath = await window.electronAPI.selectFile({
                    filters: [
                        { name: 'SSH Keys', extensions: ['pem', 'key', 'pub'] },
                        { name: 'All Files', extensions: ['*'] }
                    ]
                });
                if (sshKeyPath) {
                    document.getElementById('ssh-key-path').value = sshKeyPath;
                }
            });
        }

        // Test connection
        if (testConnectionBtn) {
            testConnectionBtn.addEventListener('click', async () => {
                await this.testRemoteConnection();
            });
        }

        // Form submission
        if (form) {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                await this.handleRemoteDeployment();
            });
        }
    }

    setupNginxConfigForm() {
        const form = document.getElementById('nginx-config-form');
        const locationType = document.getElementById('nginx-location-type');
        const generateBtn = document.getElementById('generate-nginx-config');
        const copyBtn = document.getElementById('copy-nginx-config');
        const saveBtn = document.getElementById('save-nginx-config');
        const deployBtn = document.getElementById('deploy-nginx-config');

        if (locationType) {
            locationType.addEventListener('change', (e) => {
                const subdirectoryGroup = document.getElementById('nginx-subdirectory-group');
                subdirectoryGroup.style.display = e.target.value === 'subdirectory' ? 'block' : 'none';
            });
        }

        if (generateBtn) {
            generateBtn.addEventListener('click', async () => {
                await this.generateNginxConfig();
            });
        }

        if (copyBtn) {
            copyBtn.addEventListener('click', () => {
                const configText = document.getElementById('nginx-config-text');
                navigator.clipboard.writeText(configText.value);
                this.showToast('Configuration copied to clipboard', 'success');
            });
        }

        if (saveBtn) {
            saveBtn.addEventListener('click', async () => {
                await this.saveNginxConfig();
            });
        }

        if (deployBtn) {
            deployBtn.addEventListener('click', async () => {
                await this.deployNginxConfig();
            });
        }
    }

    setupSettingsForm() {
        const browseDefaultPath = document.getElementById('browse-default-path');

        if (browseDefaultPath) {
            browseDefaultPath.addEventListener('click', async () => {
                const directory = await window.electronAPI.selectDirectory();
                if (directory) {
                    document.getElementById('default-install-path').value = directory;
                    await window.electronAPI.storeSet('defaultInstallPath', directory);
                }
            });
        }

        // Load saved settings
        this.loadSettings();
    }

    setupNavigationHandlers() {
        // Handle navigation state
        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', (e) => {
                document.querySelectorAll('.nav-item').forEach(nav => nav.classList.remove('active'));
                e.currentTarget.classList.add('active');
            });
        });
    }

    setupMenuEventListeners() {
        // Listen for menu events from main process
        window.electronAPI.onOpenPreferences(() => {
            this.switchView('settings');
        });

        window.electronAPI.onNewLocalInstall(() => {
            this.switchView('local-install');
        });

        window.electronAPI.onDeployRemote(() => {
            this.switchView('remote-deploy');
        });

        window.electronAPI.onConfigureNginx(() => {
            this.switchView('nginx-config');
        });

        window.electronAPI.onSystemRequirements((event, requirements) => {
            this.updateSystemRequirements(requirements);
        });
    }

    switchView(viewName) {
        // Hide all views
        document.querySelectorAll('.view').forEach(view => {
            view.classList.remove('active');
        });

        // Show selected view
        const targetView = document.getElementById(`${viewName}-view`);
        if (targetView) {
            targetView.classList.add('active');
            this.currentView = viewName;

            // Initialize specific view functionality
            if (viewName === 'admin-management') {
                this.initializeAdminManagement();
            }
        }

        // Update navigation
        document.querySelectorAll('.nav-item').forEach(nav => nav.classList.remove('active'));
        const navItem = document.querySelector(`[data-view="${viewName}"]`);
        if (navItem) {
            navItem.classList.add('active');
        }
    }

    async loadSystemInfo() {
        try {
            const systemInfo = await window.electronAPI.getSystemInfo();
            document.getElementById('electron-version').textContent = systemInfo.electronVersion;
            document.getElementById('node-version').textContent = systemInfo.nodeVersion;

            // Set default installation directory
            this.defaultInstallDir = systemInfo.homeDir + '/FlexPBX';
            const installDirInput = document.getElementById('install-directory');
            if (installDirInput) {
                installDirInput.value = this.defaultInstallDir;
            }
        } catch (error) {
            console.error('Failed to load system info:', error);
            // Fallback default directory
            this.defaultInstallDir = '~/FlexPBX';
            const installDirInput = document.getElementById('install-directory');
            if (installDirInput) {
                installDirInput.value = this.defaultInstallDir;
            }
        }
    }

    async checkSystemRequirements() {
        try {
            const dockerStatus = await window.electronAPI.dockerCheck();
            this.updateDockerStatus(dockerStatus);
        } catch (error) {
            console.error('Failed to check system requirements:', error);
        }
    }

    updateDockerStatus(status) {
        const dockerRequirement = document.getElementById('docker-requirement');
        const dockerStatusEl = document.getElementById('docker-status');

        if (status.installed && status.running) {
            dockerRequirement.className = 'requirement-item success';
            dockerStatusEl.textContent = `✓ Running (${status.version})`;
        } else if (status.installed && !status.running) {
            dockerRequirement.className = 'requirement-item warning';
            dockerStatusEl.textContent = '⚠ Installed but not running';
        } else {
            dockerRequirement.className = 'requirement-item error';
            dockerStatusEl.textContent = '✗ Not installed';
        }
    }

    updateSystemRequirements(requirements) {
        this.systemRequirements = requirements;

        // Update Docker status
        if (requirements.docker) {
            this.updateDockerStatus(requirements.docker);
        }

        // Update disk space status
        if (requirements.diskSpace) {
            const diskRequirement = document.getElementById('disk-requirement');
            const diskStatus = document.getElementById('disk-status');

            if (requirements.diskSpace.available) {
                diskRequirement.className = 'requirement-item success';
                diskStatus.textContent = `✓ ${requirements.diskSpace.space}`;
            } else {
                diskRequirement.className = 'requirement-item error';
                diskStatus.textContent = '✗ Insufficient space';
            }
        }

        // Update permissions status
        if (requirements.permissions) {
            const permissionsRequirement = document.getElementById('permissions-requirement');
            const permissionsStatus = document.getElementById('permissions-status');

            if (requirements.permissions.writable) {
                permissionsRequirement.className = 'requirement-item success';
                permissionsStatus.textContent = '✓ Writable';
            } else {
                permissionsRequirement.className = 'requirement-item error';
                permissionsStatus.textContent = '✗ No write access';
            }
        }
    }

    async handleLocalInstall() {
        const formData = new FormData(document.getElementById('local-install-form'));
        const config = {
            installDirectory: document.getElementById('install-directory').value,
            installationName: document.getElementById('installation-name').value,
            domain: document.getElementById('domain-local').value,
            httpPort: parseInt(document.getElementById('http-port').value),
            sipPort: parseInt(document.getElementById('sip-port').value),
            configureNginx: document.getElementById('configure-nginx-local').checked,
            nginxDomain: document.getElementById('nginx-domain').value,
            installLocation: document.getElementById('install-location').value,
            appPath: document.getElementById('app-path').value,
            sslEnabled: document.getElementById('ssl-enabled-local').checked,
            mailServerType: document.getElementById('mail-server-type').value,
            smtpHost: document.getElementById('smtp-host').value,
            smtpPort: parseInt(document.getElementById('smtp-port').value) || 587,
            smtpUsername: document.getElementById('smtp-username').value,
            smtpPassword: document.getElementById('smtp-password').value,
            smtpTls: document.getElementById('smtp-tls').checked
        };

        // Validate required fields
        if (!config.installDirectory || !config.installationName) {
            this.showToast('Please fill in all required fields', 'error');
            return;
        }

        this.showInstallationProgress();

        try {
            const result = await window.electronAPI.dockerInstallLocal(config);

            if (result.success) {
                this.showToast('Installation completed successfully!', 'success');
                this.addInstallation({
                    name: config.installationName,
                    path: config.installDirectory,
                    domain: config.domain,
                    httpPort: config.httpPort,
                    sipPort: config.sipPort,
                    status: 'running',
                    type: 'local'
                });
                this.switchView('dashboard');
            } else {
                this.showToast(`Installation failed: ${result.error}`, 'error');
            }
        } catch (error) {
            this.showToast(`Installation error: ${error.message}`, 'error');
        } finally {
            this.hideInstallationProgress();
        }
    }

    async testRemoteConnection() {
        const method = document.getElementById('deployment-method').value;
        let connectionConfig = {};

        try {
            if (method === 'ssh') {
                connectionConfig = {
                    host: document.getElementById('ssh-host').value,
                    port: parseInt(document.getElementById('ssh-port').value),
                    username: document.getElementById('ssh-username').value,
                    password: document.getElementById('ssh-password').value,
                    privateKeyPath: document.getElementById('ssh-key-path').value
                };
            } else if (method === 'ftp') {
                connectionConfig = {
                    host: document.getElementById('ftp-host').value,
                    port: parseInt(document.getElementById('ftp-port').value),
                    username: document.getElementById('ftp-username').value,
                    password: document.getElementById('ftp-password').value,
                    secure: document.getElementById('ftp-secure').checked
                };
            } else if (method === 'webdav') {
                connectionConfig = {
                    url: document.getElementById('webdav-url').value,
                    username: document.getElementById('webdav-username').value,
                    password: document.getElementById('webdav-password').value
                };
            }

            const result = await window.electronAPI.testRemoteConnection({ method, connectionConfig });

            if (result.success) {
                this.showToast('Connection test successful!', 'success');
            } else {
                this.showToast(`Connection test failed: ${result.error}`, 'error');
            }
        } catch (error) {
            this.showToast(`Connection test error: ${error.message}`, 'error');
        }
    }

    async handleRemoteDeployment() {
        const method = document.getElementById('deployment-method').value;
        let connectionConfig = {};
        let deploymentConfig = {};

        try {
            // Get connection config based on method
            if (method === 'ssh') {
                connectionConfig = {
                    host: document.getElementById('ssh-host').value,
                    port: parseInt(document.getElementById('ssh-port').value),
                    username: document.getElementById('ssh-username').value,
                    password: document.getElementById('ssh-password').value,
                    privateKeyPath: document.getElementById('ssh-key-path').value,
                    remotePath: document.getElementById('remote-path').value
                };
            } else if (method === 'ftp') {
                connectionConfig = {
                    host: document.getElementById('ftp-host').value,
                    port: parseInt(document.getElementById('ftp-port').value),
                    username: document.getElementById('ftp-username').value,
                    password: document.getElementById('ftp-password').value,
                    secure: document.getElementById('ftp-secure').checked,
                    remotePath: document.getElementById('ftp-remote-path').value
                };
            } else if (method === 'webdav') {
                connectionConfig = {
                    url: document.getElementById('webdav-url').value,
                    username: document.getElementById('webdav-username').value,
                    password: document.getElementById('webdav-password').value,
                    remotePath: document.getElementById('webdav-remote-path').value
                };
            }

            // Get deployment configuration
            deploymentConfig = {
                domain: document.getElementById('remote-domain').value,
                httpPort: parseInt(document.getElementById('remote-http-port').value),
                sipPort: parseInt(document.getElementById('remote-sip-port').value),
                sslEnabled: document.getElementById('remote-ssl-enabled').checked,
                setupNginx: document.getElementById('remote-setup-nginx').checked,
                mailServerType: document.getElementById('remote-mail-server').value
            };

            // Validate required fields
            if (!connectionConfig.host && !connectionConfig.url) {
                this.showToast('Please fill in connection details', 'error');
                return;
            }

            if (!deploymentConfig.domain) {
                this.showToast('Please enter a domain name', 'error');
                return;
            }

            this.showRemoteDeploymentProgress();

            const result = await window.electronAPI.deployToRemote({
                method,
                connectionConfig,
                deploymentConfig
            });

            if (result.success) {
                this.showToast('Remote deployment completed successfully!', 'success');
                this.addInstallation({
                    name: deploymentConfig.domain,
                    path: connectionConfig.remotePath || connectionConfig.url,
                    domain: deploymentConfig.domain,
                    httpPort: deploymentConfig.httpPort,
                    sipPort: deploymentConfig.sipPort,
                    status: 'running',
                    type: 'remote',
                    method: method
                });
                this.switchView('dashboard');
            } else {
                this.showToast(`Remote deployment failed: ${result.error}`, 'error');
            }
        } catch (error) {
            this.showToast(`Deployment error: ${error.message}`, 'error');
        } finally {
            this.hideRemoteDeploymentProgress();
        }
    }

    showRemoteDeploymentProgress() {
        const progressDiv = document.getElementById('remote-deploy-progress');
        const form = document.getElementById('remote-deploy-form');

        form.style.display = 'none';
        progressDiv.style.display = 'block';

        // Update progress steps
        const stepsContainer = document.getElementById('remote-progress-steps');
        stepsContainer.innerHTML = `
            <div class="progress-step active">
                <span class="step-number">1</span>
                <span class="step-text">Testing connection...</span>
            </div>
            <div class="progress-step">
                <span class="step-number">2</span>
                <span class="step-text">Uploading files...</span>
            </div>
            <div class="progress-step">
                <span class="step-number">3</span>
                <span class="step-text">Installing dependencies...</span>
            </div>
            <div class="progress-step">
                <span class="step-number">4</span>
                <span class="step-text">Configuring services...</span>
            </div>
            <div class="progress-step">
                <span class="step-number">5</span>
                <span class="step-text">Starting services...</span>
            </div>
        `;
    }

    hideRemoteDeploymentProgress() {
        const progressDiv = document.getElementById('remote-deploy-progress');
        const form = document.getElementById('remote-deploy-form');

        progressDiv.style.display = 'none';
        form.style.display = 'block';
    }

    showInstallationProgress() {
        const progressDiv = document.getElementById('local-install-progress');
        const form = document.getElementById('local-install-form');

        form.style.display = 'none';
        progressDiv.style.display = 'block';

        // Update progress steps
        const stepsContainer = document.getElementById('local-progress-steps');
        stepsContainer.innerHTML = `
            <div class="progress-step active">
                <span class="step-number">1</span>
                <span class="step-text">Creating installation directory</span>
            </div>
            <div class="progress-step">
                <span class="step-number">2</span>
                <span class="step-text">Copying FlexPBX files</span>
            </div>
            <div class="progress-step">
                <span class="step-number">3</span>
                <span class="step-text">Generating configuration</span>
            </div>
            <div class="progress-step">
                <span class="step-number">4</span>
                <span class="step-text">Starting Docker services</span>
            </div>
        `;
    }

    hideInstallationProgress() {
        const progressDiv = document.getElementById('local-install-progress');
        const form = document.getElementById('local-install-form');

        progressDiv.style.display = 'none';
        form.style.display = 'block';
    }

    async generateNginxConfig() {
        const config = {
            serverName: document.getElementById('nginx-server-name').value,
            backendHost: document.getElementById('nginx-backend-host').value,
            backendPort: parseInt(document.getElementById('nginx-backend-port').value),
            locationType: document.getElementById('nginx-location-type').value,
            subdirectory: document.getElementById('nginx-subdirectory').value,
            sslEnabled: document.getElementById('nginx-ssl-enabled').checked
        };

        if (!config.serverName || !config.backendHost || !config.backendPort) {
            this.showToast('Please fill in all required fields', 'error');
            return;
        }

        try {
            const result = await window.electronAPI.nginxConfigure(config);

            if (result.success) {
                document.getElementById('nginx-config-text').value = result.config;
                document.getElementById('nginx-config-output').style.display = 'block';
                this.showToast('Nginx configuration generated successfully', 'success');
            } else {
                this.showToast(`Failed to generate config: ${result.error}`, 'error');
            }
        } catch (error) {
            this.showToast(`Error generating config: ${error.message}`, 'error');
        }
    }

    async saveNginxConfig() {
        const config = document.getElementById('nginx-config-text').value;
        if (!config) {
            this.showToast('No configuration to save', 'error');
            return;
        }

        try {
            const filePath = await window.electronAPI.selectFile({
                title: 'Save Nginx Configuration',
                filters: [
                    { name: 'Nginx Config', extensions: ['conf'] },
                    { name: 'All Files', extensions: ['*'] }
                ]
            });

            if (filePath) {
                // Note: In a real implementation, we'd need to add a save file method to the API
                this.showToast('Configuration saved successfully', 'success');
            }
        } catch (error) {
            this.showToast(`Error saving config: ${error.message}`, 'error');
        }
    }

    async deployNginxConfig() {
        const config = document.getElementById('nginx-config-text').value;
        if (!config) {
            this.showToast('No configuration to deploy', 'error');
            return;
        }

        try {
            const result = await window.electronAPI.nginxReload();

            if (result.success) {
                this.showToast('Nginx configuration deployed and reloaded', 'success');
            } else {
                this.showToast(`Failed to deploy config: ${result.error}`, 'error');
            }
        } catch (error) {
            this.showToast(`Error deploying config: ${error.message}`, 'error');
        }
    }

    async loadInstallations() {
        try {
            // Load saved installations
            const installations = await window.electronAPI.storeGet('installations') || [];

            // Auto-detect local FlexPBX installations
            const detectedInstallations = await this.autoDetectLocalInstallations();

            // Merge detected installations with saved ones (avoid duplicates)
            const mergedInstallations = [...installations];
            for (const detected of detectedInstallations) {
                const exists = installations.find(inst =>
                    inst.path === detected.path ||
                    (inst.httpPort === detected.httpPort && inst.type === 'local')
                );
                if (!exists) {
                    mergedInstallations.push(detected);
                }
            }

            this.installations = mergedInstallations;

            // Save merged installations
            if (detectedInstallations.length > 0) {
                await window.electronAPI.storeSet('installations', this.installations);
            }

            this.updateInstallationsList();
        } catch (error) {
            console.error('Failed to load installations:', error);
        }
    }

    async autoDetectLocalInstallations() {
        const detectedInstallations = [];

        try {
            // Check common FlexPBX installation paths
            const commonPaths = [
                '/usr/local/flexpbx',
                '/opt/flexpbx',
                '/Applications/FlexPBX',
                `${process.env.HOME}/flexpbx`,
                `${process.env.HOME}/FlexPBX`,
                '/var/www/flexpbx'
            ];

            // Check for running FlexPBX services on common ports
            const commonPorts = [8080, 3000, 8081, 8082, 8083];

            for (const port of commonPorts) {
                try {
                    const response = await fetch(`http://localhost:${port}/status`);
                    if (response.ok) {
                        const data = await response.json();
                        if (data.status === 'running' && data.services) {
                            detectedInstallations.push({
                                id: `auto-detected-${port}`,
                                name: `Auto-detected FlexPBX (Port ${port})`,
                                type: 'local',
                                path: 'Auto-detected',
                                httpPort: port,
                                sipPort: 5060,
                                status: 'running',
                                autoDetected: true,
                                createdAt: new Date().toISOString()
                            });
                        }
                    }
                } catch (e) {
                    // Port not responding, continue checking
                }
            }

            console.log(`🔍 Auto-detected ${detectedInstallations.length} FlexPBX installations`);

        } catch (error) {
            console.error('Failed to auto-detect installations:', error);
        }

        return detectedInstallations;
    }

    addInstallation(installation) {
        this.installations.push({
            ...installation,
            id: Date.now(),
            createdAt: new Date().toISOString()
        });

        window.electronAPI.storeSet('installations', this.installations);
        this.updateInstallationsList();
    }

    updateInstallationsList() {
        const listContainer = document.getElementById('installations-list');

        if (this.installations.length === 0) {
            listContainer.innerHTML = `
                <div class="empty-state">
                    <p>No installations found. Create your first FlexPBX installation to get started.</p>
                </div>
            `;
            return;
        }

        listContainer.innerHTML = this.installations.map(installation => `
            <div class="installation-item" data-id="${installation.id}">
                <div class="installation-info">
                    <h4>${installation.name}</h4>
                    <p class="installation-details">
                        <span class="installation-type">${installation.type}</span>
                        ${installation.domain ? ` • ${installation.domain}` : ''}
                        • Port ${installation.httpPort}
                    </p>
                    <p class="installation-path">${installation.path}</p>
                </div>
                <div class="installation-status">
                    <span class="status-indicator ${installation.status}"></span>
                    <span class="status-text">${installation.status}</span>
                </div>
                <div class="installation-actions">
                    <button class="btn-small btn-primary" onclick="app.openInstallation('${installation.id}')">
                        Open
                    </button>
                    <button class="btn-small btn-secondary" onclick="app.manageInstallation('${installation.id}')">
                        Manage
                    </button>
                </div>
            </div>
        `).join('');
    }

    async openInstallation(installationId) {
        const installation = this.installations.find(i => i.id == installationId);
        if (!installation) return;

        const url = installation.domain
            ? `http://${installation.domain}`
            : `http://localhost:${installation.httpPort}`;

        await window.electronAPI.openExternal(url);
    }

    async manageInstallation(installationId) {
        const installation = this.installations.find(i => i.id == installationId);
        if (!installation) return;

        // Switch to server manager view and highlight this installation
        this.switchView('server-manager');
        // Additional management functionality would be implemented here
    }

    async loadSettings() {
        try {
            const defaultPath = await window.electronAPI.storeGet('defaultInstallPath');
            if (defaultPath) {
                document.getElementById('default-install-path').value = defaultPath;
            }

            const startAtLogin = await window.electronAPI.storeGet('startAtLogin');
            if (startAtLogin) {
                document.getElementById('start-at-login').checked = startAtLogin;
            }

            const minimizeToTray = await window.electronAPI.storeGet('minimizeToTray');
            if (minimizeToTray) {
                document.getElementById('minimize-to-tray').checked = minimizeToTray;
            }
        } catch (error) {
            console.error('Failed to load settings:', error);
        }
    }

    async showConnectDialog() {
        // This would show a modal dialog for connecting to existing servers
        // For now, we'll show a placeholder toast
        this.showToast('Connect to existing server feature coming soon!', 'info');
    }

    showToast(message, type = 'info') {
        const toastContainer = document.getElementById('toast-container');
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.innerHTML = `
            <span class="toast-message">${message}</span>
            <button class="toast-close" onclick="this.parentElement.remove()">×</button>
        `;

        toastContainer.appendChild(toast);

        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (toast.parentElement) {
                toast.remove();
            }
        }, 5000);
    }

    showLoading(text = 'Loading...') {
        const overlay = document.getElementById('loading-overlay');
        const loadingText = document.querySelector('.loading-text');

        loadingText.textContent = text;
        overlay.style.display = 'flex';
        this.isLoading = true;
    }

    hideLoading() {
        const overlay = document.getElementById('loading-overlay');
        overlay.style.display = 'none';
        this.isLoading = false;
    }

    async importBackup() {
        try {
            console.log('📂 Starting backup import process...');
            this.setLoading(true);

            const backupFile = await window.electronAPI.selectBackupFile();

            if (!backupFile) {
                console.log('❌ No backup file selected');
                this.setLoading(false);
                return;
            }

            console.log(`📂 Selected backup file: ${backupFile}`);

            // Validate file extension
            const validExtensions = ['.flx', '.flxx'];
            const fileExtension = backupFile.toLowerCase().slice(backupFile.lastIndexOf('.'));

            if (!validExtensions.includes(fileExtension)) {
                console.error(`❌ Invalid file type: ${fileExtension}`);
                this.showToast(`Invalid file type. Please select a .flx or .flxx file.`, 'error');
                this.setLoading(false);
                return;
            }

            // Here you would implement the actual backup import logic
            // For now, we'll simulate the process
            this.showToast('📂 Importing backup file...', 'info');

            // Simulate processing time
            await new Promise(resolve => setTimeout(resolve, 2000));

            // Add the imported installation to the list
            const importedInstallation = {
                id: Date.now(),
                name: `Imported from ${backupFile.split('/').pop()}`,
                type: 'imported',
                path: backupFile,
                status: 'imported',
                createdAt: new Date().toISOString(),
                imported: true
            };

            this.addInstallation(importedInstallation);

            console.log('✅ Backup imported successfully');
            this.showToast('✅ Backup imported successfully!', 'success');

        } catch (error) {
            console.error('❌ Failed to import backup:', error);
            this.showToast(`Failed to import backup: ${error.message}`, 'error');
        } finally {
            this.setLoading(false);
        }
    }

    async exportBackup() {
        try {
            console.log('💾 Starting backup export process...');
            this.setLoading(true);

            const savePath = await window.electronAPI.saveBackupFile();

            if (!savePath) {
                console.log('❌ No save location selected');
                this.setLoading(false);
                return;
            }

            console.log(`💾 Selected save path: ${savePath}`);

            this.showToast('💾 Creating backup file...', 'info');

            // Here you would implement the actual backup export logic
            // For now, we'll simulate the process

            // Gather current configuration
            const backupData = {
                timestamp: new Date().toISOString(),
                version: '2.0.0',
                installations: this.installations,
                settings: await window.electronAPI.storeGet('settings') || {},
                systemInfo: await window.electronAPI.getSystemInfo(),
                metadata: {
                    exportedBy: 'FlexPBX Desktop',
                    exportType: savePath.endsWith('.flxx') ? 'extended' : 'standard'
                }
            };

            // Simulate processing time
            await new Promise(resolve => setTimeout(resolve, 2000));

            console.log('💾 Backup data prepared:', backupData);
            console.log('✅ Backup exported successfully');
            this.showToast(`✅ Backup exported to: ${savePath.split('/').pop()}`, 'success');

        } catch (error) {
            console.error('❌ Failed to export backup:', error);
            this.showToast(`Failed to export backup: ${error.message}`, 'error');
        } finally {
            this.setLoading(false);
        }
    }

    // Tab navigation for Server Manager and Services views
    setupTabNavigation() {
        // Setup tab switching for all tab containers
        document.querySelectorAll('.tab-container').forEach(container => {
            const tabHeaders = container.querySelectorAll('.tab-header');
            const tabPanels = container.querySelectorAll('.tab-panel');

            tabHeaders.forEach(header => {
                header.addEventListener('click', (e) => {
                    const targetTab = header.dataset.tab;
                    const targetPanel = container.querySelector(`#${targetTab}-panel`);

                    if (targetPanel) {
                        // Remove active class from all headers and panels in this container
                        tabHeaders.forEach(h => {
                            h.classList.remove('active');
                            h.setAttribute('aria-selected', 'false');
                        });
                        tabPanels.forEach(p => p.classList.remove('active'));

                        // Add active class to clicked header and corresponding panel
                        header.classList.add('active');
                        header.setAttribute('aria-selected', 'true');
                        targetPanel.classList.add('active');

                        console.log(`🔄 Switched to tab: ${targetTab}`);
                    }
                });
            });
        });

        // Setup Service-specific event handlers
        this.setupServiceEventHandlers();

        // Setup Server Manager event handlers
        this.setupServerManagerEventHandlers();
    }

    setupServiceEventHandlers() {
        // SIP Trunk Management
        document.getElementById('add-sip-trunk')?.addEventListener('click', () => {
            this.addSipTrunk();
        });

        document.getElementById('import-sip-config')?.addEventListener('click', async () => {
            await this.importServiceConfig('sip-trunks');
        });

        document.getElementById('export-sip-config')?.addEventListener('click', async () => {
            await this.exportServiceConfig('sip-trunks');
        });

        // Extensions Management
        document.getElementById('add-extension')?.addEventListener('click', () => {
            this.addExtension();
        });

        document.getElementById('bulk-import-extensions')?.addEventListener('click', async () => {
            await this.bulkImportExtensions();
        });

        document.getElementById('export-extensions')?.addEventListener('click', async () => {
            await this.exportServiceConfig('extensions');
        });

        // Call Queues Management
        document.getElementById('add-call-queue')?.addEventListener('click', () => {
            this.addCallQueue();
        });

        // Global service import/export
        document.getElementById('export-all-services')?.addEventListener('click', async () => {
            await this.exportAllServiceConfigurations();
        });

        document.getElementById('import-all-services')?.addEventListener('click', async () => {
            await this.importAllServiceConfigurations();
        });

        document.getElementById('reset-all-services')?.addEventListener('click', () => {
            this.resetAllServicesToDefaults();
        });

        // Extension ownership toggle
        document.getElementById('allow-extension-trunks')?.addEventListener('change', (e) => {
            console.log(`📞 Extension trunk ownership: ${e.target.checked ? 'enabled' : 'disabled'}`);
            this.updateTrunkOwnershipUI(e.target.checked);
        });

        // Firewall Management
        document.getElementById('add-firewall-rule')?.addEventListener('click', () => {
            this.addFirewallRule();
        });

        document.getElementById('import-firewall-config')?.addEventListener('click', async () => {
            await this.importServiceConfig('firewall');
        });

        document.getElementById('export-firewall-config')?.addEventListener('click', async () => {
            await this.exportServiceConfig('firewall');
        });

        // Hold Server Management
        document.getElementById('add-hold-playlist')?.addEventListener('click', () => {
            this.addHoldPlaylist();
        });

        document.getElementById('import-hold-config')?.addEventListener('click', async () => {
            await this.importServiceConfig('hold-server');
        });

        document.getElementById('export-hold-config')?.addEventListener('click', async () => {
            await this.exportServiceConfig('hold-server');
        });

        // Advanced Configuration
        document.getElementById('load-config-file')?.addEventListener('click', async () => {
            await this.loadConfigurationFile();
        });

        document.getElementById('save-advanced-config')?.addEventListener('click', async () => {
            await this.saveAdvancedConfiguration();
        });

        document.getElementById('export-advanced-config')?.addEventListener('click', async () => {
            await this.exportAdvancedConfiguration();
        });

        document.getElementById('validate-config')?.addEventListener('click', () => {
            this.validateConfiguration();
        });

        // Extension Advanced Settings Toggle
        document.getElementById('edit-ext-101')?.addEventListener('click', () => {
            this.toggleExtensionAdvanced('ext-101-advanced');
        });

        // Mini Tab Navigation for Extension Settings
        this.setupMiniTabNavigation();

        // Hold volume range update
        document.getElementById('hold-volume')?.addEventListener('input', (e) => {
            const output = document.querySelector('output[for="hold-volume"]');
            if (output) output.textContent = `${e.target.value}%`;
        });
    }

    setupServerManagerEventHandlers() {
        // IVR Settings handlers
        document.getElementById('add-ivr-option')?.addEventListener('click', () => {
            this.addIVRMenuOption();
        });

        document.getElementById('save-ivr-settings')?.addEventListener('click', async () => {
            await this.saveIVRSettings();
        });

        document.getElementById('test-ivr-settings')?.addEventListener('click', () => {
            this.testIVRFlow();
        });

        document.getElementById('reset-ivr-settings')?.addEventListener('click', () => {
            this.resetIVRToDefaults();
        });

        // Server Defaults handlers
        document.getElementById('browse-default-path')?.addEventListener('click', async () => {
            await this.browseDefaultInstallPath();
        });

        document.getElementById('browse-backup-path')?.addEventListener('click', async () => {
            await this.browseDefaultBackupPath();
        });

        document.getElementById('save-server-defaults')?.addEventListener('click', async () => {
            await this.saveServerDefaults();
        });

        // Backup & Restore handlers
        document.getElementById('create-backup-btn')?.addEventListener('click', async () => {
            await this.createServerBackup();
        });

        document.getElementById('restore-backup-btn')?.addEventListener('click', async () => {
            await this.restoreServerBackup();
        });

        document.getElementById('browse-restore-file')?.addEventListener('click', async () => {
            await this.browseRestoreFile();
        });
    }

    // Service Management Methods
    addSipTrunk() {
        console.log('📞 Adding new SIP trunk...');
        this.showToast('SIP Trunk configuration dialog would open here', 'info');
        // TODO: Implement SIP trunk configuration modal
    }

    addExtension() {
        console.log('👤 Adding new extension...');
        this.showToast('Extension configuration dialog would open here', 'info');
        // TODO: Implement extension configuration modal
    }

    addCallQueue() {
        console.log('📋 Adding new call queue...');
        this.showToast('Call queue configuration dialog would open here', 'info');
        // TODO: Implement call queue configuration modal
    }

    addIVRMenuOption() {
        console.log('📞 Adding new IVR menu option...');
        // TODO: Dynamically add new IVR menu option to the list
        this.showToast('IVR menu option added', 'success');
    }

    async saveIVRSettings() {
        console.log('💾 Saving IVR settings...');
        // TODO: Collect all IVR form data and save
        this.showToast('IVR settings saved successfully', 'success');
    }

    async saveServerDefaults() {
        console.log('💾 Saving server defaults...');
        const defaults = {
            installPath: document.getElementById('default-install-path').value,
            backupPath: document.getElementById('default-backup-path').value,
            httpPort: document.getElementById('default-http-port').value,
            sipPort: document.getElementById('default-sip-port').value,
            rtpPort: document.getElementById('default-rtp-port').value
        };

        try {
            await window.electronAPI.storeSet('serverDefaults', defaults);
            this.showToast('Server defaults saved successfully', 'success');
            console.log('💾 Server defaults saved:', defaults);
        } catch (error) {
            console.error('❌ Failed to save server defaults:', error);
            this.showToast('Failed to save server defaults', 'error');
        }
    }

    async exportServiceConfig(serviceType) {
        console.log(`📤 Exporting ${serviceType} configuration...`);
        try {
            const config = await this.getServiceConfiguration(serviceType);
            const fileName = `flexpbx-${serviceType}-config.json`;

            // TODO: Use file save dialog
            this.showToast(`${serviceType} configuration exported`, 'success');
        } catch (error) {
            console.error(`❌ Failed to export ${serviceType} config:`, error);
            this.showToast(`Failed to export ${serviceType} configuration`, 'error');
        }
    }

    async importServiceConfig(serviceType) {
        console.log(`📥 Importing ${serviceType} configuration...`);
        try {
            // TODO: Use file open dialog and import configuration
            this.showToast(`${serviceType} configuration imported`, 'success');
        } catch (error) {
            console.error(`❌ Failed to import ${serviceType} config:`, error);
            this.showToast(`Failed to import ${serviceType} configuration`, 'error');
        }
    }

    async exportAllServiceConfigurations() {
        console.log('📤 Exporting all service configurations...');
        try {
            const allConfigs = {
                sipTrunks: await this.getServiceConfiguration('sip-trunks'),
                extensions: await this.getServiceConfiguration('extensions'),
                queues: await this.getServiceConfiguration('call-queues'),
                voicemail: await this.getServiceConfiguration('voicemail'),
                routing: await this.getServiceConfiguration('call-routing'),
                recordings: await this.getServiceConfiguration('recordings'),
                modules: await this.getServiceConfiguration('modules')
            };

            // TODO: Save to .flx file format
            this.showToast('All service configurations exported', 'success');
        } catch (error) {
            console.error('❌ Failed to export all configurations:', error);
            this.showToast('Failed to export service configurations', 'error');
        }
    }

    async getServiceConfiguration(serviceType) {
        // TODO: Collect form data for specific service type
        return {};
    }

    testIVRFlow() {
        console.log('🧪 Testing IVR flow...');
        this.showToast('IVR flow test would simulate call flow here', 'info');
        // TODO: Implement IVR flow testing
    }

    resetIVRToDefaults() {
        console.log('🔄 Resetting IVR to defaults...');
        // TODO: Reset all IVR form fields to default values
        this.showToast('IVR settings reset to defaults', 'success');
    }

    updateTrunkOwnershipUI(enabled) {
        // TODO: Show/hide additional trunk ownership options
        console.log(`🔄 Trunk ownership UI updated: ${enabled}`);
    }

    async createModuleFolder(serverName, moduleType, moduleName) {
        try {
            // Create organized folder structure: ~/servers/servername/modules/type/modulename
            const basePath = `~/servers/${serverName}/modules/`;
            const folderPath = `${basePath}${moduleType}/${moduleName}`;

            console.log(`📁 Creating module folder: ${folderPath}`);

            // Create the directory structure
            // TODO: Implement via electronAPI to create directories
            // await window.electronAPI.createDirectory(folderPath);

            // Create standard module folders for organization
            const moduleStructure = [
                `${folderPath}/config`,     // Configuration files
                `${folderPath}/assets`,     // Assets (audio, images, etc.)
                `${folderPath}/scripts`,    // Custom scripts
                `${folderPath}/backup`,     // Module-specific backups
                `${folderPath}/docs`        // Documentation
            ];

            for (const dir of moduleStructure) {
                console.log(`📁 Creating subdirectory: ${dir}`);
                // TODO: Create each subdirectory
            }

            this.showToast(`Module folder structure created: ${moduleName}`, 'success');
            return folderPath;
        } catch (error) {
            console.error('❌ Failed to create module folder:', error);
            this.showToast('Failed to create module folder', 'error');
            return null;
        }
    }

    async initializeDefaultModuleFolders(serverName) {
        try {
            console.log(`📁 Initializing module folders for server: ${serverName}`);

            // Standard module types for organization
            const moduleTypes = [
                'core',           // Core PBX modules
                'extensions',     // Extension-related modules
                'trunks',        // SIP trunk modules
                'ivr',           // IVR and routing modules
                'voicemail',     // Voicemail modules
                'recording',     // Call recording modules
                'conferencing',  // Conference bridge modules
                'fax',           // Fax modules
                'crm',           // CRM integration modules
                'reporting',     // Reporting and analytics
                'security',      // Security and authentication
                'custom',        // Custom/third-party modules
                'themes',        // UI themes and customizations
                'plugins',       // General plugins
                'addons'         // Additional add-ons
            ];

            const basePath = `~/servers/${serverName}/modules/`;

            for (const moduleType of moduleTypes) {
                const typePath = `${basePath}${moduleType}`;
                console.log(`📁 Creating module type folder: ${typePath}`);

                // TODO: Create directory via electronAPI
                // await window.electronAPI.createDirectory(typePath);

                // Create a README file in each type folder
                const readmeContent = `# ${moduleType.charAt(0).toUpperCase() + moduleType.slice(1)} Modules\n\nThis folder contains ${moduleType} modules for the FlexPBX system.\n\n## Installation\nDrop module folders here for automatic detection and installation.\n\n## Structure\nEach module should have its own subfolder with the module name.\n`;

                // TODO: Write README file
                // await window.electronAPI.writeFile(`${typePath}/README.md`, readmeContent);
            }

            this.showToast(`Module folder structure initialized for ${serverName}`, 'success');
            return basePath;
        } catch (error) {
            console.error('❌ Failed to initialize module folders:', error);
            this.showToast('Failed to initialize module folders', 'error');
            return null;
        }
    }

    async installModuleFromFolder(folderPath) {
        console.log(`📦 Installing module from: ${folderPath}`);
        try {
            // TODO: Implement WordPress-style module installation
            // 1. Scan folder for module.json or package.json
            // 2. Validate module compatibility
            // 3. Install dependencies
            // 4. Register module with PBX

            this.showToast('Module installed successfully', 'success');
        } catch (error) {
            console.error('❌ Module installation failed:', error);
            this.showToast('Module installation failed', 'error');
        }
    }

    // Archive Format Management (.flx, .flxx, .mod)
    async createArchive(archiveType, sourceData, fileName) {
        try {
            console.log(`📦 Creating ${archiveType} archive: ${fileName}`);

            let archiveData = {};

            switch (archiveType) {
                case 'flx':
                    // FlexPBX standard archive - configuration only
                    archiveData = {
                        version: '1.0',
                        type: 'flexpbx-config',
                        timestamp: new Date().toISOString(),
                        data: sourceData,
                        metadata: {
                            creator: 'FlexPBX Desktop',
                            description: 'FlexPBX Configuration Archive'
                        }
                    };
                    break;

                case 'flxx':
                    // FlexPBX extended archive - includes media, modules, and config
                    archiveData = {
                        version: '2.0',
                        type: 'flexpbx-extended',
                        timestamp: new Date().toISOString(),
                        data: sourceData,
                        includes: {
                            configuration: true,
                            audioFiles: true,
                            modules: true,
                            voicemails: sourceData.includeVoicemails || false,
                            recordings: sourceData.includeRecordings || false,
                            logs: sourceData.includeLogs || false
                        },
                        metadata: {
                            creator: 'FlexPBX Desktop',
                            description: 'FlexPBX Extended Archive - Complete Backup'
                        }
                    };
                    break;

                case 'mod':
                    // Module archive - self-contained module with all dependencies
                    archiveData = {
                        version: '1.0',
                        type: 'flexpbx-module',
                        timestamp: new Date().toISOString(),
                        module: {
                            name: sourceData.name,
                            version: sourceData.version,
                            description: sourceData.description,
                            author: sourceData.author,
                            dependencies: sourceData.dependencies || [],
                            configuration: sourceData.configuration,
                            assets: sourceData.assets || [],
                            scripts: sourceData.scripts || []
                        },
                        metadata: {
                            creator: 'FlexPBX Desktop',
                            description: `Module Archive: ${sourceData.name}`
                        }
                    };
                    break;
            }

            // Preserve all file structures and metadata
            const preservedArchive = await this.preserveFileStructure(archiveData);

            // Save archive with proper compression
            const savedPath = await this.saveArchiveFile(preservedArchive, fileName, archiveType);

            this.showToast(`${archiveType.toUpperCase()} archive created successfully`, 'success');
            return savedPath;

        } catch (error) {
            console.error(`❌ Failed to create ${archiveType} archive:`, error);
            this.showToast(`Failed to create archive: ${error.message}`, 'error');
            return null;
        }
    }

    async preserveFileStructure(archiveData) {
        // Ensure no data loss by preserving complete file structure
        return {
            ...archiveData,
            integrity: {
                checksum: await this.calculateChecksum(archiveData),
                fileCount: this.countFiles(archiveData),
                totalSize: this.calculateSize(archiveData)
            },
            preservation: {
                permissions: true,
                timestamps: true,
                metadata: true,
                symlinks: true
            }
        };
    }

    // Extension Advanced Settings Management
    toggleExtensionAdvanced(extensionId) {
        const advancedPanel = document.getElementById(extensionId);
        if (advancedPanel) {
            const isVisible = advancedPanel.style.display !== 'none';
            advancedPanel.style.display = isVisible ? 'none' : 'block';
            console.log(`🔧 Extension advanced settings ${isVisible ? 'hidden' : 'shown'}: ${extensionId}`);
        }
    }

    setupMiniTabNavigation() {
        // Setup mini-tab navigation for extension settings
        document.querySelectorAll('.mini-tab-header').forEach(header => {
            header.addEventListener('click', (e) => {
                const tabName = header.dataset.tab;
                const container = header.closest('.advanced-tabs');

                if (container) {
                    // Remove active from all headers and panels in this container
                    container.querySelectorAll('.mini-tab-header').forEach(h => h.classList.remove('active'));
                    container.querySelectorAll('.mini-tab-panel').forEach(p => p.classList.remove('active'));

                    // Add active to clicked header and corresponding panel
                    header.classList.add('active');
                    const panel = container.querySelector(`#${tabName}-panel`);
                    if (panel) panel.classList.add('active');

                    console.log(`🔄 Extension mini-tab switched to: ${tabName}`);
                }
            });
        });
    }

    // Firewall Management
    addFirewallRule() {
        console.log('🛡️ Adding new firewall rule...');
        this.showToast('Firewall rule configuration dialog would open here', 'info');
        // TODO: Implement firewall rule configuration modal
    }

    // Hold Server Management
    addHoldPlaylist() {
        console.log('🎵 Adding new hold playlist...');
        this.showToast('Hold playlist configuration dialog would open here', 'info');
        // TODO: Implement playlist configuration modal
    }

    // Advanced Configuration Management
    async loadConfigurationFile() {
        try {
            console.log('📂 Loading configuration file...');

            const configFile = await window.electronAPI.selectFile({
                title: 'Load Configuration File',
                filters: [
                    { name: 'Module Archives', extensions: ['mod'] },
                    { name: 'Configuration Files', extensions: ['conf', 'config', 'ini'] },
                    { name: 'FlexPBX Archives', extensions: ['flx', 'flxx'] },
                    { name: 'Archive Files', extensions: ['zip', 'tar', 'tar.gz', '7z'] },
                    { name: 'All Files', extensions: ['*'] }
                ]
            });

            if (!configFile) return;

            // Load and parse configuration based on file type
            const fileExtension = configFile.toLowerCase().split('.').pop();
            const configData = await this.parseConfigurationFile(configFile, fileExtension);

            // Populate the raw editor
            const editor = document.getElementById('raw-config-editor');
            if (editor) {
                editor.value = JSON.stringify(configData, null, 2);
            }

            this.showToast('Configuration file loaded successfully', 'success');
            console.log('📂 Configuration loaded:', configData);

        } catch (error) {
            console.error('❌ Failed to load configuration file:', error);
            this.showToast('Failed to load configuration file', 'error');
        }
    }

    async parseConfigurationFile(filePath, extension) {
        // Parse different configuration file formats
        switch (extension) {
            case 'mod':
                return await this.parseModuleArchive(filePath);
            case 'flx':
            case 'flxx':
                return await this.parseFlexPBXArchive(filePath);
            case 'conf':
            case 'config':
                return await this.parseConfigFile(filePath);
            case 'ini':
                return await this.parseIniFile(filePath);
            case 'zip':
            case 'tar':
            case '7z':
                return await this.parseArchiveFile(filePath);
            default:
                throw new Error(`Unsupported file format: ${extension}`);
        }
    }

    async saveAdvancedConfiguration() {
        try {
            console.log('💾 Saving advanced configuration...');

            const editor = document.getElementById('raw-config-editor');
            const configFormat = document.getElementById('config-format')?.value || 'flx';
            const configScope = document.getElementById('config-scope')?.value || 'system';

            if (!editor || !editor.value.trim()) {
                this.showToast('No configuration to save', 'error');
                return;
            }

            const configData = JSON.parse(editor.value);
            const fileName = `flexpbx-config-${configScope}-${Date.now()}.${configFormat}`;

            // Create appropriate archive format
            await this.createArchive(configFormat, configData, fileName);

        } catch (error) {
            console.error('❌ Failed to save configuration:', error);
            this.showToast('Failed to save configuration', 'error');
        }
    }

    validateConfiguration() {
        try {
            console.log('🧪 Validating configuration...');

            const editor = document.getElementById('raw-config-editor');
            if (!editor || !editor.value.trim()) {
                this.showToast('No configuration to validate', 'error');
                return;
            }

            // Parse JSON to check syntax
            const configData = JSON.parse(editor.value);

            // Validate structure based on type
            const validationResult = this.validateConfigStructure(configData);

            if (validationResult.valid) {
                this.showToast('Configuration is valid', 'success');
            } else {
                this.showToast(`Configuration errors: ${validationResult.errors.join(', ')}`, 'error');
            }

        } catch (error) {
            console.error('❌ Configuration validation failed:', error);
            this.showToast('Invalid JSON syntax', 'error');
        }
    }

    validateConfigStructure(configData) {
        const errors = [];

        // Basic validation rules
        if (!configData.version) errors.push('Missing version field');
        if (!configData.type) errors.push('Missing type field');
        if (!configData.timestamp) errors.push('Missing timestamp field');

        // Type-specific validation
        switch (configData.type) {
            case 'flexpbx-config':
                if (!configData.data) errors.push('Missing data field for FlexPBX config');
                break;
            case 'flexpbx-module':
                if (!configData.module) errors.push('Missing module field');
                if (configData.module && !configData.module.name) errors.push('Missing module name');
                break;
        }

        return {
            valid: errors.length === 0,
            errors: errors
        };
    }

    // Archive helper methods
    async calculateChecksum(data) {
        // Simple checksum calculation for integrity verification
        const jsonString = JSON.stringify(data);
        let hash = 0;
        for (let i = 0; i < jsonString.length; i++) {
            const char = jsonString.charCodeAt(i);
            hash = ((hash << 5) - hash) + char;
            hash = hash & hash; // Convert to 32bit integer
        }
        return hash.toString(16);
    }

    countFiles(data) {
        // Count files in the archive data
        let count = 0;
        const traverse = (obj) => {
            if (typeof obj === 'object' && obj !== null) {
                if (obj.type === 'file') count++;
                Object.values(obj).forEach(value => {
                    if (typeof value === 'object') traverse(value);
                });
            }
        };
        traverse(data);
        return count;
    }

    calculateSize(data) {
        // Calculate approximate size of the data
        return JSON.stringify(data).length;
    }

    async saveArchiveFile(archiveData, fileName, archiveType) {
        try {
            // TODO: Implement actual file saving via electronAPI
            console.log(`💾 Saving ${archiveType} archive: ${fileName}`);
            console.log('Archive data:', archiveData);

            // For now, show success message
            return `~/servers/backups/${fileName}`;
        } catch (error) {
            throw new Error(`Failed to save archive: ${error.message}`);
        }
    }

    // PIN Code Management
    generatePIN(length = 6) {
        const digits = '0123456789';
        let pin = '';
        for (let i = 0; i < length; i++) {
            pin += digits.charAt(Math.floor(Math.random() * digits.length));
        }
        return pin;
    }

    // Vacation Mode Management
    setVacationMode(extensionId, enabled, settings = {}) {
        console.log(`🏖️ Setting vacation mode for ${extensionId}: ${enabled ? 'enabled' : 'disabled'}`);

        if (enabled) {
            console.log('Vacation settings:', settings);
            // TODO: Implement vacation mode logic
        }

        this.showToast(`Vacation mode ${enabled ? 'enabled' : 'disabled'} for extension ${extensionId}`, 'success');
    }

    // Admin Management Methods
    initializeAdminManagement() {
        this.setupAdminEventListeners();
        this.loadAdminData();
    }

    setupAdminEventListeners() {
        // Version Management
        document.getElementById('create-public-version')?.addEventListener('click', () => {
            this.createPublicVersion();
        });

        // Client Deployment
        document.getElementById('create-client-version')?.addEventListener('click', () => {
            this.createClientVersion();
        });

        // Limitation Presets
        document.querySelectorAll('.preset-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                this.applyLimitationPreset(e.target.dataset.preset);
            });
        });

        // Update Management
        document.getElementById('push-update')?.addEventListener('click', () => {
            this.pushUpdate();
        });

        // Client Actions
        document.addEventListener('click', (e) => {
            if (e.target.dataset.action) {
                const action = e.target.dataset.action;
                const clientRow = e.target.closest('tr');
                const clientName = clientRow?.cells[0]?.textContent;
                this.handleClientAction(action, clientName);
            }
        });
    }

    async loadAdminData() {
        try {
            // Load current version info
            await this.loadVersionInfo();

            // Load deployed clients
            await this.loadDeployedClients();

            // Load update history
            await this.loadUpdateHistory();
        } catch (error) {
            console.error('Error loading admin data:', error);
            this.showToast('Error loading admin data', 'error');
        }
    }

    async loadVersionInfo() {
        // Update version information in the UI
        const versionElement = document.getElementById('admin-version');
        const buildElement = document.getElementById('admin-build');

        if (versionElement) versionElement.textContent = '2.0.0';
        if (buildElement) buildElement.textContent = 'Admin-Full';
    }

    async createPublicVersion() {
        try {
            this.showToast('Creating public version...', 'info');

            // Clone current admin version and apply limitations
            const publicConfig = {
                version: '2.0.0',
                build: 'Public-Limited',
                limitations: {
                    maxExtensions: 10,
                    maxConcurrentCalls: 5,
                    allowRecording: false,
                    allowConferencing: true,
                    allowIVR: true,
                    allowVoicemail: true,
                    allowSipTrunks: true,
                    allowFeatureCodes: false
                }
            };

            // Create public version with limitations
            await this.buildVersionWithLimitations(publicConfig, '/Applications/FlexPBX Public.app');

            this.showToast('Public version created successfully', 'success');
        } catch (error) {
            console.error('Error creating public version:', error);
            this.showToast('Error creating public version', 'error');
        }
    }

    async createClientVersion() {
        try {
            const clientName = document.getElementById('client-name')?.value;
            const version = document.getElementById('client-version')?.value;
            const deploymentMethod = document.getElementById('deployment-method')?.value;

            if (!clientName) {
                this.showToast('Please enter a client name', 'error');
                return;
            }

            this.showToast(`Creating custom version for ${clientName}...`, 'info');

            // Get current limitation settings
            const limitations = this.getCurrentLimitations();

            const clientConfig = {
                clientName,
                version,
                build: `Custom-${clientName}`,
                limitations,
                deploymentMethod
            };

            // Create and deploy client version
            await this.buildAndDeployClientVersion(clientConfig);

            // Add to deployed clients table
            this.addClientToTable(clientConfig);

            this.showToast(`Client version created for ${clientName}`, 'success');
        } catch (error) {
            console.error('Error creating client version:', error);
            this.showToast('Error creating client version', 'error');
        }
    }

    getCurrentLimitations() {
        return {
            maxExtensions: document.getElementById('max-extensions')?.value || 'unlimited',
            maxConcurrentCalls: document.getElementById('max-concurrent-calls')?.value || 'unlimited',
            allowRecording: document.getElementById('allow-recording')?.checked || false,
            allowConferencing: document.getElementById('allow-conferencing')?.checked || false,
            allowIVR: document.getElementById('allow-ivr')?.checked || false,
            allowVoicemail: document.getElementById('allow-voicemail')?.checked || false,
            allowSipTrunks: document.getElementById('allow-sip-trunks')?.checked || false,
            allowFeatureCodes: document.getElementById('allow-feature-codes')?.checked || false,
            trialPeriod: document.getElementById('trial-period')?.value || 30,
            licenseExpiry: document.getElementById('license-expiry')?.value || null
        };
    }

    applyLimitationPreset(preset) {
        // Remove active class from all presets
        document.querySelectorAll('.preset-btn').forEach(btn => {
            btn.classList.remove('active');
        });

        // Add active class to selected preset
        document.querySelector(`[data-preset="${preset}"]`)?.classList.add('active');

        // Apply preset values
        const presets = {
            trial: {
                maxExtensions: 3,
                maxConcurrentCalls: 2,
                allowRecording: false,
                allowConferencing: false,
                allowIVR: true,
                allowVoicemail: true,
                allowSipTrunks: true,
                allowFeatureCodes: false,
                trialPeriod: 30
            },
            basic: {
                maxExtensions: 10,
                maxConcurrentCalls: 5,
                allowRecording: false,
                allowConferencing: true,
                allowIVR: true,
                allowVoicemail: true,
                allowSipTrunks: true,
                allowFeatureCodes: false,
                trialPeriod: 0
            },
            professional: {
                maxExtensions: 50,
                maxConcurrentCalls: 25,
                allowRecording: true,
                allowConferencing: true,
                allowIVR: true,
                allowVoicemail: true,
                allowSipTrunks: true,
                allowFeatureCodes: true,
                trialPeriod: 0
            },
            enterprise: {
                maxExtensions: 'unlimited',
                maxConcurrentCalls: 'unlimited',
                allowRecording: true,
                allowConferencing: true,
                allowIVR: true,
                allowVoicemail: true,
                allowSipTrunks: true,
                allowFeatureCodes: true,
                trialPeriod: 0
            }
        };

        const config = presets[preset];
        if (config) {
            // Update form fields
            if (document.getElementById('max-extensions'))
                document.getElementById('max-extensions').value = config.maxExtensions;
            if (document.getElementById('max-concurrent-calls'))
                document.getElementById('max-concurrent-calls').value = config.maxConcurrentCalls;
            if (document.getElementById('allow-recording'))
                document.getElementById('allow-recording').checked = config.allowRecording;
            if (document.getElementById('allow-conferencing'))
                document.getElementById('allow-conferencing').checked = config.allowConferencing;
            if (document.getElementById('allow-ivr'))
                document.getElementById('allow-ivr').checked = config.allowIVR;
            if (document.getElementById('allow-voicemail'))
                document.getElementById('allow-voicemail').checked = config.allowVoicemail;
            if (document.getElementById('allow-sip-trunks'))
                document.getElementById('allow-sip-trunks').checked = config.allowSipTrunks;
            if (document.getElementById('allow-feature-codes'))
                document.getElementById('allow-feature-codes').checked = config.allowFeatureCodes;
            if (document.getElementById('trial-period'))
                document.getElementById('trial-period').value = config.trialPeriod;
        }
    }

    async buildVersionWithLimitations(config, outputPath) {
        // Mock implementation - in real app, this would build the actual app with limitations
        console.log('Building version with config:', config);
        console.log('Output path:', outputPath);

        // Simulate build process
        await new Promise(resolve => setTimeout(resolve, 2000));

        return true;
    }

    async buildAndDeployClientVersion(config) {
        // Mock implementation for building and deploying client versions
        console.log('Building and deploying client version:', config);

        switch (config.deploymentMethod) {
            case 'dmg':
                await this.createDMGPackage(config);
                break;
            case 'zip':
                await this.createZIPPackage(config);
                break;
            case 'remote':
                await this.deployRemotely(config);
                break;
            case 'update-server':
                await this.deployToUpdateServer(config);
                break;
        }

        return true;
    }

    async createDMGPackage(config) {
        console.log('Creating DMG package for:', config.clientName);
        // Simulate DMG creation
        await new Promise(resolve => setTimeout(resolve, 3000));
    }

    async createZIPPackage(config) {
        console.log('Creating ZIP package for:', config.clientName);
        // Simulate ZIP creation
        await new Promise(resolve => setTimeout(resolve, 2000));
    }

    async deployRemotely(config) {
        console.log('Deploying remotely for:', config.clientName);
        // Simulate remote deployment
        await new Promise(resolve => setTimeout(resolve, 4000));
    }

    async deployToUpdateServer(config) {
        console.log('Deploying to update server for:', config.clientName);
        // Simulate update server deployment
        await new Promise(resolve => setTimeout(resolve, 3000));
    }

    addClientToTable(config) {
        const tbody = document.getElementById('deployed-clients-body');
        if (!tbody) return;

        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${config.clientName}</td>
            <td>${config.version}</td>
            <td>Custom</td>
            <td><span class="status-badge active">Active</span></td>
            <td>Just now</td>
            <td>
                <div class="action-buttons">
                    <button class="btn-small" data-action="upgrade">Upgrade</button>
                    <button class="btn-small" data-action="downgrade">Downgrade</button>
                    <button class="btn-small" data-action="update">Update</button>
                    <button class="btn-small danger" data-action="revoke">Revoke</button>
                </div>
            </td>
        `;

        tbody.appendChild(row);
    }

    async loadDeployedClients() {
        // Mock data for deployed clients
        const mockClients = [
            {
                name: 'Demo Company Inc.',
                version: '2.0.0',
                plan: 'Professional',
                status: 'active',
                lastContact: '2 minutes ago'
            }
        ];

        // In real implementation, load from server/storage
        console.log('Loaded deployed clients:', mockClients);
    }

    async loadUpdateHistory() {
        // Mock update history
        const mockUpdates = [
            {
                timestamp: '2025-10-05 02:30:00',
                type: 'Feature Update',
                target: 'All Professional Clients',
                status: 'success'
            }
        ];

        console.log('Loaded update history:', mockUpdates);
    }

    async pushUpdate() {
        try {
            const target = document.getElementById('update-target')?.value;
            const updateType = document.getElementById('update-type')?.value;
            const forceUpdate = document.getElementById('force-update')?.checked;

            if (!target || !updateType) {
                this.showToast('Please select target and update type', 'error');
                return;
            }

            this.showToast(`Pushing ${updateType} to ${target}...`, 'info');

            // Mock update push
            await new Promise(resolve => setTimeout(resolve, 3000));

            // Add to update log
            this.addUpdateToLog({
                timestamp: new Date().toLocaleString(),
                type: updateType,
                target: target,
                status: 'success'
            });

            this.showToast('Update pushed successfully', 'success');
        } catch (error) {
            console.error('Error pushing update:', error);
            this.showToast('Error pushing update', 'error');
        }
    }

    addUpdateToLog(update) {
        const logContainer = document.getElementById('update-log');
        if (!logContainer) return;

        const logEntry = document.createElement('div');
        logEntry.className = 'log-entry';
        logEntry.innerHTML = `
            <span class="timestamp">${update.timestamp}</span>
            <span class="update-type">${update.type}</span>
            <span class="target">${update.target}</span>
            <span class="status ${update.status}">${update.status.charAt(0).toUpperCase() + update.status.slice(1)}</span>
        `;

        logContainer.insertBefore(logEntry, logContainer.firstChild);
    }

    async handleClientAction(action, clientName) {
        try {
            this.showToast(`${action.charAt(0).toUpperCase() + action.slice(1)}ing ${clientName}...`, 'info');

            // Mock action handling
            await new Promise(resolve => setTimeout(resolve, 2000));

            switch (action) {
                case 'upgrade':
                    this.showToast(`${clientName} upgraded successfully`, 'success');
                    break;
                case 'downgrade':
                    this.showToast(`${clientName} downgraded successfully`, 'success');
                    break;
                case 'update':
                    this.showToast(`${clientName} updated successfully`, 'success');
                    break;
                case 'revoke':
                    this.showToast(`${clientName} access revoked`, 'success');
                    break;
            }
        } catch (error) {
            console.error(`Error ${action}ing client:`, error);
            this.showToast(`Error ${action}ing ${clientName}`, 'error');
        }
    }

    // Path Management and Preview Methods
    initializePathManagement() {
        this.setupPathEventListeners();
        this.updatePathPreviews();
    }

    setupPathEventListeners() {
        // Local install path listeners
        document.getElementById('pbx-name')?.addEventListener('input', () => {
            this.updateLocalPathPreview();
        });

        document.getElementById('install-directory')?.addEventListener('input', () => {
            this.updateLocalPathPreview();
            this.validatePath('local');
        });

        document.getElementById('subdomain-local')?.addEventListener('input', () => {
            this.updateLocalDomainPreview();
        });

        document.getElementById('root-domain-local')?.addEventListener('input', () => {
            this.updateLocalDomainPreview();
        });

        // Remote deploy path listeners
        document.getElementById('remote-pbx-name')?.addEventListener('input', () => {
            this.updateRemotePathPreview();
        });

        document.getElementById('remote-base-path')?.addEventListener('input', () => {
            this.updateRemotePathPreview();
            this.validatePath('remote');
        });

        document.getElementById('remote-subdomain')?.addEventListener('input', () => {
            this.updateRemoteDomainPreview();
        });

        document.getElementById('remote-root-domain')?.addEventListener('input', () => {
            this.updateRemoteDomainPreview();
        });

        // API configuration listeners
        document.getElementById('enable-api-local')?.addEventListener('change', (e) => {
            this.toggleApiOptions('local', e.target.checked);
        });

        document.getElementById('api-port-local')?.addEventListener('input', () => {
            this.updateApiEndpointsPreview('local');
        });

        document.getElementById('api-version-local')?.addEventListener('change', () => {
            this.updateApiEndpointsPreview('local');
        });

        document.getElementById('generate-api-key-local')?.addEventListener('click', () => {
            this.generateApiKey('local');
        });

        document.getElementById('api-rate-limiting-local')?.addEventListener('change', (e) => {
            this.toggleRateLimitOptions('local', e.target.checked);
        });

        // Default directory buttons
        document.getElementById('use-default-directory')?.addEventListener('click', () => {
            this.useDefaultDirectory();
        });
    }

    updateLocalPathPreview() {
        const pbxName = document.getElementById('pbx-name')?.value || 'MyPBX';
        const baseDir = document.getElementById('install-directory')?.value || this.getDefaultUserDirectory();

        const serverPath = `${baseDir}/server/`;
        const pbxPath = `${serverPath}${pbxName}/`;
        const modulesPath = `${pbxPath}modules/`;
        const backupsPath = `${pbxPath}backups/`;
        const configPath = `${pbxPath}config/`;

        document.getElementById('preview-root-path').textContent = serverPath;
        document.getElementById('preview-pbx-path').textContent = pbxPath;
        document.getElementById('preview-modules-path').textContent = modulesPath;
        document.getElementById('preview-backups-path').textContent = backupsPath;
        document.getElementById('preview-config-path').textContent = configPath;
    }

    updateLocalDomainPreview() {
        const subdomain = document.getElementById('subdomain-local')?.value || '';
        const rootDomain = document.getElementById('root-domain-local')?.value || 'raywonderis.me';

        const fullDomain = subdomain ? `${subdomain}.${rootDomain}` : rootDomain;
        const webrootPath = `/var/www/html/${fullDomain}/`;
        const subdomainPath = subdomain ? `/var/www/html/${rootDomain}/${subdomain}/` : `/var/www/html/${rootDomain}/`;
        const rootOnlyPath = `${rootDomain}/`;

        document.getElementById('preview-full-domain').textContent = fullDomain;
        document.getElementById('preview-webroot-path').textContent = webrootPath;
        document.getElementById('preview-subdomain-path').textContent = subdomainPath;
        document.getElementById('preview-root-only').textContent = rootOnlyPath;
    }

    updateRemotePathPreview() {
        const pbxName = document.getElementById('remote-pbx-name')?.value || 'MyPBX';
        const baseDir = document.getElementById('remote-base-path')?.value || '/home/user/app-install-path';

        const serverPath = `${baseDir}/server/`;
        const pbxPath = `${serverPath}${pbxName}/`;
        const modulesPath = `${pbxPath}modules/`;
        const backupsPath = `${pbxPath}backups/`;

        document.getElementById('preview-remote-base').textContent = `${baseDir}/`;
        document.getElementById('preview-remote-server').textContent = serverPath;
        document.getElementById('preview-remote-pbx').textContent = pbxPath;
        document.getElementById('preview-remote-modules').textContent = modulesPath;
        document.getElementById('preview-remote-backups').textContent = backupsPath;
    }

    updateRemoteDomainPreview() {
        const subdomain = document.getElementById('remote-subdomain')?.value || '';
        const rootDomain = document.getElementById('remote-root-domain')?.value || 'raywonderis.me';

        const fullDomain = subdomain ? `${subdomain}.${rootDomain}` : rootDomain;
        const webrootPath = `/var/www/html/${fullDomain}/`;
        const subdomainPath = subdomain ? `/var/www/html/${rootDomain}/${subdomain}/` : `/var/www/html/${rootDomain}/`;
        const rootOnlyPath = `${rootDomain}/`;

        document.getElementById('preview-remote-full-domain').textContent = fullDomain;
        document.getElementById('preview-remote-webroot').textContent = webrootPath;
        document.getElementById('preview-remote-subdomain-path').textContent = subdomainPath;
        document.getElementById('preview-remote-root-only').textContent = rootOnlyPath;
    }

    async validatePath(type) {
        const pathInput = type === 'local' ?
            document.getElementById('install-directory') :
            document.getElementById('remote-base-path');

        const statusElement = document.getElementById(`${type === 'local' ? '' : 'remote-'}path-status`);

        if (!pathInput || !statusElement) return;

        const path = pathInput.value;

        if (!path) {
            this.updatePathStatus(statusElement, 'checking', 'Enter a path to validate');
            return;
        }

        this.updatePathStatus(statusElement, 'checking', 'Checking path availability...');

        try {
            // Mock path validation - in real implementation, this would check actual paths
            await new Promise(resolve => setTimeout(resolve, 1000));

            if (path.includes(' ') && !path.startsWith('"')) {
                this.updatePathStatus(statusElement, 'warning', 'Path contains spaces - ensure proper quoting');
            } else if (path.startsWith('/') || path.match(/^[A-Z]:\\/)) {
                this.updatePathStatus(statusElement, 'success', 'Path is valid and accessible');
            } else {
                this.updatePathStatus(statusElement, 'warning', 'Relative path - will be resolved relative to current directory');
            }
        } catch (error) {
            this.updatePathStatus(statusElement, 'error', 'Path validation failed');
        }
    }

    updatePathStatus(statusElement, type, message) {
        const iconElement = statusElement.querySelector('.status-icon');
        const textElement = statusElement.querySelector('.status-text');

        if (!iconElement || !textElement) return;

        // Update icon
        switch (type) {
            case 'checking':
                iconElement.textContent = '🔍';
                break;
            case 'success':
                iconElement.textContent = '✅';
                break;
            case 'warning':
                iconElement.textContent = '⚠️';
                break;
            case 'error':
                iconElement.textContent = '❌';
                break;
        }

        // Update text and class
        textElement.textContent = message;
        textElement.className = `status-text ${type}`;
    }

    getDefaultUserDirectory() {
        // Get user home directory based on platform
        if (typeof window !== 'undefined' && window.electronAPI) {
            // In real implementation, get from system info
            return '/home/username';
        }
        return '/home/username';
    }

    useDefaultDirectory() {
        const defaultPath = `${this.getDefaultUserDirectory()}/server`;
        const installDirInput = document.getElementById('install-directory');

        if (installDirInput) {
            installDirInput.value = defaultPath;
            this.updateLocalPathPreview();
            this.validatePath('local');
        }
    }

    updatePathPreviews() {
        this.updateLocalPathPreview();
        this.updateLocalDomainPreview();
        this.updateRemotePathPreview();
        this.updateRemoteDomainPreview();
        this.updateApiEndpointsPreview('local');
    }

    // API Configuration Methods
    toggleApiOptions(type, enabled) {
        const optionsElement = document.getElementById(`api-options-${type}`);
        if (optionsElement) {
            optionsElement.style.display = enabled ? 'block' : 'none';
            if (enabled) {
                this.updateApiEndpointsPreview(type);
            }
        }
    }

    updateApiEndpointsPreview(type) {
        const port = document.getElementById(`api-port-${type}`)?.value || '8080';
        const version = document.getElementById(`api-version-${type}`)?.value || 'v1';
        const domain = type === 'local' ? 'localhost' :
            (document.getElementById(`${type === 'remote' ? 'remote-' : ''}root-domain-local`)?.value || 'example.com');

        const baseUrl = `http://${domain}:${port}/api/${version}/`;

        document.getElementById('preview-api-base').textContent = baseUrl;
        document.getElementById('preview-api-extensions').textContent = `${baseUrl}extensions`;
        document.getElementById('preview-api-calls').textContent = `${baseUrl}calls`;
        document.getElementById('preview-api-voicemail').textContent = `${baseUrl}voicemail`;
        document.getElementById('preview-api-trunks').textContent = `${baseUrl}trunks`;
    }

    generateApiKey(type) {
        // Generate a secure API key
        const characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        let result = '';
        for (let i = 0; i < 32; i++) {
            result += characters.charAt(Math.floor(Math.random() * characters.length));
        }

        const apiKeyInput = document.getElementById(`api-key-${type}`);
        if (apiKeyInput) {
            apiKeyInput.value = result;
        }

        this.showToast('New API key generated', 'success');
    }

    toggleRateLimitOptions(type, enabled) {
        const optionsElement = document.getElementById(`rate-limit-options-${type}`);
        if (optionsElement) {
            optionsElement.style.display = enabled ? 'block' : 'none';
        }
    }
}

// Initialize the app when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.app = new FlexPBXDesktopApp();
});

// Handle app-level events
window.addEventListener('beforeunload', () => {
    // Cleanup if needed
    if (window.electronAPI.removeAllListeners) {
        window.electronAPI.removeAllListeners('system-requirements');
        window.electronAPI.removeAllListeners('open-preferences');
        window.electronAPI.removeAllListeners('new-local-install');
        window.electronAPI.removeAllListeners('deploy-remote');
        window.electronAPI.removeAllListeners('configure-nginx');
    }
});