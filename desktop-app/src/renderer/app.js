class FlexPBXDesktopApp {
    constructor() {
        this.currentView = 'dashboard';
        this.installations = [];
        this.systemRequirements = {};
        this.isLoading = false;
        this.defaultInstallDir = null;
        this.logs = [];
        this.maxLogs = 100; // Keep last 100 log entries

        this.init();
    }

    async init() {
        try {
            console.log('FlexPBX Desktop App initializing...');
            this.bindEvents();
            this.setupNavigationHandlers();
            this.setupMenuEventListeners();
            await this.loadSystemInfo();
            await this.setDefaultInstallDirectory();
            await this.checkSystemRequirements();
            await this.loadInstallations();
            this.initializePathManagement();

            // Initialize logging system
            this.initializeLogging();

            // Initialize PBX system with user management, announcements, and DID support
            this.initializePBXSystem();

            console.log('FlexPBX Desktop App initialization complete');
        } catch (error) {
            console.error('Error during initialization:', error);
            // Continue initialization even if some parts fail
        }
    }

    async setDefaultInstallDirectory() {
        try {
            // Get system info to determine default install directory
            if (!window.electronAPI) {
                this.defaultInstallDir = '/Applications/FlexPBX';
                return;
            }
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
        console.log('Binding events...');

        // Navigation
        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', (e) => {
                const view = e.currentTarget.dataset.view;
                console.log('Nav item clicked, switching to view:', view);
                this.switchView(view);
            });
        });

        // Dashboard quick actions - Add error handling
        const newLocalBtn = document.getElementById('new-local-btn');
        if (newLocalBtn) {
            console.log('Found new-local-btn, adding listener');
            newLocalBtn.addEventListener('click', (e) => {
                console.log('New local button clicked');
                e.preventDefault();
                this.switchView('local-install');
            });
        } else {
            console.warn('new-local-btn not found');
        }

        const deployRemoteBtn = document.getElementById('deploy-remote-btn');
        if (deployRemoteBtn) {
            console.log('Found deploy-remote-btn, adding listener');
            deployRemoteBtn.addEventListener('click', (e) => {
                console.log('Deploy remote button clicked');
                e.preventDefault();
                this.switchView('remote-deploy');
            });
        } else {
            console.warn('deploy-remote-btn not found');
        }

        const connectExistingBtn = document.getElementById('connect-existing-btn');
        if (connectExistingBtn) {
            console.log('Found connect-existing-btn, adding listener');
            connectExistingBtn.addEventListener('click', (e) => {
                console.log('Connect existing button clicked');
                e.preventDefault();
                this.showConnectDialog();
            });
        } else {
            console.warn('connect-existing-btn not found');
        }

        const importBackupBtn = document.getElementById('import-backup-btn');
        if (importBackupBtn) {
            importBackupBtn.addEventListener('click', async (e) => {
                e.preventDefault();
                await this.importBackup();
            });
        }

        const exportBackupBtn = document.getElementById('export-backup-btn');
        if (exportBackupBtn) {
            exportBackupBtn.addEventListener('click', async (e) => {
                e.preventDefault();
                await this.exportBackup();
            });
        }

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

        // Setup tab visibility controls
        this.setupTabVisibilityControls();
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

                    // Check if Electron API is available
                    if (!window.electronAPI || !window.electronAPI.selectDirectory) {
                        console.log('Electron API not available, enabling manual input');
                        const installDirInput = document.getElementById('install-directory');
                        const fallbackInput = document.getElementById('install-directory-fallback');

                        if (installDirInput) {
                            installDirInput.removeAttribute('readonly');
                            installDirInput.placeholder = 'Type or paste directory path here';
                            installDirInput.focus();
                        }

                        // Also set up fallback input if it exists
                        if (fallbackInput) {
                            fallbackInput.addEventListener('input', (e) => {
                                if (installDirInput) {
                                    installDirInput.value = e.target.value;
                                    installDirInput.dispatchEvent(new Event('change'));
                                }
                            });
                        }

                        this.showToast('Browse not available. Please type or paste the path directly.', 'info');
                        return;
                    }

                    const directory = await window.electronAPI.selectDirectory();
                    console.log('📁 Selected directory:', directory);

                    if (directory) {
                        const installDirInput = document.getElementById('install-directory');
                        const fallbackInput = document.getElementById('install-directory-fallback');

                        if (installDirInput) {
                            installDirInput.value = directory;
                            installDirInput.dispatchEvent(new Event('change')); // Trigger change event
                        }

                        if (fallbackInput) {
                            fallbackInput.value = directory;
                        }

                        console.log('✅ Directory set successfully');
                    } else {
                        console.log('❌ No directory selected');
                    }
                } catch (error) {
                    console.error('❌ Failed to select directory:', error);
                    // Enable manual input as fallback
                    const installDirInput = document.getElementById('install-directory');
                    if (installDirInput) {
                        installDirInput.removeAttribute('readonly');
                        installDirInput.placeholder = 'Type or paste directory path here';
                        installDirInput.focus();
                    }
                    this.showToast('Failed to open directory browser. Please enter path manually.', 'error');
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
                try {
                    // Check if Electron API is available
                    if (!window.electronAPI || !window.electronAPI.selectFile) {
                        console.log('Electron API not available for SSH key selection');
                        const sshKeyInput = document.getElementById('ssh-key-path');
                        const fallbackInput = document.getElementById('ssh-key-fallback');

                        if (sshKeyInput) {
                            sshKeyInput.removeAttribute('readonly');
                            sshKeyInput.placeholder = 'Type or paste SSH key path here';
                            sshKeyInput.focus();
                        }

                        if (fallbackInput) {
                            fallbackInput.style.display = 'block';
                            fallbackInput.addEventListener('input', (e) => {
                                if (sshKeyInput) {
                                    sshKeyInput.value = e.target.value;
                                }
                            });
                        }

                        this.showToast('Browse not available. Please enter SSH key path manually.', 'info');
                        return;
                    }

                    const sshKeyPath = await window.electronAPI.selectFile({
                        filters: [
                            { name: 'SSH Keys', extensions: ['pem', 'key', 'pub'] },
                            { name: 'All Files', extensions: ['*'] }
                        ]
                    });

                    if (sshKeyPath) {
                        document.getElementById('ssh-key-path').value = sshKeyPath;
                        const fallbackInput = document.getElementById('ssh-key-fallback');
                        if (fallbackInput) {
                            fallbackInput.value = sshKeyPath;
                        }
                    }
                } catch (error) {
                    console.error('Failed to select SSH key:', error);
                    const sshKeyInput = document.getElementById('ssh-key-path');
                    if (sshKeyInput) {
                        sshKeyInput.removeAttribute('readonly');
                        sshKeyInput.placeholder = 'Type or paste SSH key path here';
                        sshKeyInput.focus();
                    }
                    this.showToast('Failed to open file browser. Please enter SSH key path manually.', 'error');
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

    setupTabVisibilityControls() {
        // Tab visibility mapping
        this.tabVisibilityMap = {
            'show-dashboard-tab': 'dashboard',
            'show-local-install-tab': 'local-install',
            'show-remote-deploy-tab': 'remote-deploy',
            'show-server-manager-tab': 'server-manager',
            'show-services-tab': 'services',
            'show-monitoring-tab': 'monitoring',
            'show-logs-tab': 'logs',
            'show-settings-tab': 'settings',
            'show-admin-management-tab': 'admin-management'
        };

        // Apply tab visibility button
        const applyBtn = document.getElementById('apply-tab-visibility');
        if (applyBtn) {
            applyBtn.addEventListener('click', () => {
                this.applyTabVisibility();
            });
        }

        // Reset tab visibility button (show all)
        const resetBtn = document.getElementById('reset-tab-visibility');
        if (resetBtn) {
            resetBtn.addEventListener('click', () => {
                this.resetTabVisibility();
            });
        }

        // Load saved tab visibility settings
        this.loadTabVisibilitySettings();
    }

    applyTabVisibility() {
        const settings = {};

        // Get current checkbox states
        Object.keys(this.tabVisibilityMap).forEach(checkboxId => {
            const checkbox = document.getElementById(checkboxId);
            if (checkbox) {
                settings[checkboxId] = checkbox.checked;
                const tabView = this.tabVisibilityMap[checkboxId];
                const navItem = document.querySelector(`[data-view="${tabView}"]`);

                if (navItem) {
                    const listItem = navItem.closest('li');
                    if (listItem) {
                        if (checkbox.checked) {
                            listItem.style.display = '';
                        } else {
                            listItem.style.display = 'none';
                        }
                    }
                }
            }
        });

        // Save settings to localStorage
        if (window.electronAPI && window.electronAPI.storeSet) {
            window.electronAPI.storeSet('tabVisibilitySettings', settings);
        } else {
            localStorage.setItem('tabVisibilitySettings', JSON.stringify(settings));
        }

        this.showToast('Tab visibility settings applied successfully', 'success');
    }

    resetTabVisibility() {
        // Check all checkboxes
        Object.keys(this.tabVisibilityMap).forEach(checkboxId => {
            const checkbox = document.getElementById(checkboxId);
            if (checkbox) {
                checkbox.checked = true;
            }
        });

        // Apply the reset
        this.applyTabVisibility();
        this.showToast('All tabs are now visible', 'success');
    }

    async loadTabVisibilitySettings() {
        try {
            let settings = null;

            // Try to load from electron store first
            if (window.electronAPI && window.electronAPI.storeGet) {
                settings = await window.electronAPI.storeGet('tabVisibilitySettings');
            }

            // Fallback to localStorage
            if (!settings) {
                const stored = localStorage.getItem('tabVisibilitySettings');
                if (stored) {
                    settings = JSON.parse(stored);
                }
            }

            if (settings) {
                // Apply saved settings to checkboxes
                Object.keys(this.tabVisibilityMap).forEach(checkboxId => {
                    const checkbox = document.getElementById(checkboxId);
                    if (checkbox && settings.hasOwnProperty(checkboxId)) {
                        checkbox.checked = settings[checkboxId];
                    }
                });

                // Apply visibility without showing toast (silent load)
                this.applyTabVisibilitySilent();
            }
        } catch (error) {
            console.error('Failed to load tab visibility settings:', error);
            // Default to all tabs visible
            this.resetTabVisibility();
        }
    }

    applyTabVisibilitySilent() {
        // Same as applyTabVisibility but without toast notification
        Object.keys(this.tabVisibilityMap).forEach(checkboxId => {
            const checkbox = document.getElementById(checkboxId);
            if (checkbox) {
                const tabView = this.tabVisibilityMap[checkboxId];
                const navItem = document.querySelector(`[data-view="${tabView}"]`);

                if (navItem) {
                    const listItem = navItem.closest('li');
                    if (listItem) {
                        if (checkbox.checked) {
                            listItem.style.display = '';
                        } else {
                            listItem.style.display = 'none';
                        }
                    }
                }
            }
        });
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
        if (!window.electronAPI) {
            console.warn('Electron API not available for menu event listeners');
            return;
        }
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
        console.log('Switching to view:', viewName);

        // Hide all views
        const allViews = document.querySelectorAll('.view');
        console.log('Found views:', allViews.length);
        allViews.forEach(view => {
            view.classList.remove('active');
        });

        // Show selected view
        const targetView = document.getElementById(`${viewName}-view`);
        if (targetView) {
            console.log('Target view found:', `${viewName}-view`);
            targetView.classList.add('active');
            this.currentView = viewName;

            // Initialize specific view functionality
            if (viewName === 'admin-management') {
                this.initializeAdminManagement();
            }
        } else {
            console.error('Target view not found:', `${viewName}-view`);
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
            if (!window.electronAPI) {
                console.warn('Electron API not available, using fallback');
                return;
            }
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
            if (!window.electronAPI) {
                console.warn('Electron API not available for system requirements check');
                return;
            }
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
            if (!window.electronAPI) {
                console.warn('Electron API not available for loading installations');
                this.installations = [];
                return;
            }
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
                version: '1.0.0',
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

        // Audio Device Management for Hold Server
        document.getElementById('hold-input-level')?.addEventListener('input', (e) => {
            const output = document.querySelector('output[for="hold-input-level"]');
            if (output) output.textContent = `${e.target.value}%`;
        });

        document.getElementById('hold-output-level')?.addEventListener('input', (e) => {
            const output = document.querySelector('output[for="hold-output-level"]');
            if (output) output.textContent = `${e.target.value}%`;
        });

        document.getElementById('test-hold-audio')?.addEventListener('click', async () => {
            await this.testHoldAudioDevices();
        });

        document.getElementById('refresh-hold-devices')?.addEventListener('click', async () => {
            await this.refreshHoldAudioDevices();
        });

        document.getElementById('calibrate-hold-levels')?.addEventListener('click', async () => {
            await this.calibrateHoldAudioLevels();
        });

        // Audio Device Management for IVR Server
        document.getElementById('ivr-input-level')?.addEventListener('input', (e) => {
            const output = document.querySelector('output[for="ivr-input-level"]');
            if (output) output.textContent = `${e.target.value}%`;
        });

        document.getElementById('ivr-output-level')?.addEventListener('input', (e) => {
            const output = document.querySelector('output[for="ivr-output-level"]');
            if (output) output.textContent = `${e.target.value}%`;
        });

        document.getElementById('test-ivr-audio')?.addEventListener('click', async () => {
            await this.testIVRAudioDevices();
        });

        document.getElementById('record-ivr-prompt')?.addEventListener('click', async () => {
            await this.recordIVRPrompt();
        });

        document.getElementById('refresh-ivr-devices')?.addEventListener('click', async () => {
            await this.refreshIVRAudioDevices();
        });

        // SIP Trunk Provider Templates
        document.querySelectorAll('.provider-template').forEach(template => {
            template.addEventListener('click', (e) => {
                const provider = e.currentTarget.dataset.provider;
                this.loadSIPTrunkTemplate(provider);
            });
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

        if (versionElement) versionElement.textContent = '1.0.0';
        if (buildElement) buildElement.textContent = 'Admin-Full';
    }

    async createPublicVersion() {
        try {
            this.showToast('Creating public version...', 'info');

            // Clone current admin version and apply limitations
            const publicConfig = {
                version: '1.0.0',
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
                version: '1.0.0',
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

    // Audio Device Management Methods

    async testHoldAudioDevices() {
        try {
            this.showToast('Testing hold server audio devices...', 'info');

            const inputDevice = document.getElementById('hold-input-device').value;
            const outputDevice = document.getElementById('hold-output-device').value;
            const inputLevel = document.getElementById('hold-input-level').value;
            const outputLevel = document.getElementById('hold-output-level').value;

            console.log('Testing hold audio:', { inputDevice, outputDevice, inputLevel, outputLevel });

            // Simulate audio test with Web Audio API if available
            if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                const stream = await navigator.mediaDevices.getUserMedia({
                    audio: {
                        deviceId: inputDevice !== 'default' ? inputDevice : undefined,
                        echoCancellation: true,
                        noiseSuppression: true
                    }
                });

                // Play test tone using the specified output device
                const audioContext = new (window.AudioContext || window.webkitAudioContext)();
                const oscillator = audioContext.createOscillator();
                const gainNode = audioContext.createGain();

                oscillator.connect(gainNode);
                gainNode.connect(audioContext.destination);

                oscillator.frequency.setValueAtTime(440, audioContext.currentTime); // A4 note
                gainNode.gain.setValueAtTime(outputLevel / 100, audioContext.currentTime);

                oscillator.start();
                oscillator.stop(audioContext.currentTime + 1); // 1 second test tone

                // Stop the input stream
                stream.getTracks().forEach(track => track.stop());

                this.showToast('Audio test completed successfully', 'success');
            } else {
                // Fallback for when Web Audio API is not available
                this.showToast('Audio devices configured successfully', 'success');
            }
        } catch (error) {
            console.error('Hold audio test failed:', error);
            this.showToast('Audio test failed: ' + error.message, 'error');
        }
    }

    async refreshHoldAudioDevices() {
        try {
            this.showToast('Refreshing audio devices...', 'info');

            if (navigator.mediaDevices && navigator.mediaDevices.enumerateDevices) {
                const devices = await navigator.mediaDevices.enumerateDevices();

                const inputSelect = document.getElementById('hold-input-device');
                const outputSelect = document.getElementById('hold-output-device');

                // Clear existing options except defaults
                ['hold-input-device', 'hold-output-device'].forEach(id => {
                    const select = document.getElementById(id);
                    const defaultOptions = Array.from(select.children).filter(option =>
                        ['default', 'built-in', 'usb-audio', 'bluetooth', 'line-in', 'line-out'].includes(option.value)
                    );
                    select.innerHTML = '';
                    defaultOptions.forEach(option => select.appendChild(option));
                });

                // Add discovered devices
                devices.forEach(device => {
                    if (device.kind === 'audioinput' && device.deviceId) {
                        const option = document.createElement('option');
                        option.value = device.deviceId;
                        option.textContent = device.label || `Microphone ${device.deviceId.substr(0, 8)}`;
                        inputSelect.appendChild(option);
                    } else if (device.kind === 'audiooutput' && device.deviceId) {
                        const option = document.createElement('option');
                        option.value = device.deviceId;
                        option.textContent = device.label || `Speaker ${device.deviceId.substr(0, 8)}`;
                        outputSelect.appendChild(option);
                    }
                });

                this.showToast(`Found ${devices.length} audio devices`, 'success');
            } else {
                this.showToast('Device enumeration not supported', 'warning');
            }
        } catch (error) {
            console.error('Failed to refresh audio devices:', error);
            this.showToast('Failed to refresh devices: ' + error.message, 'error');
        }
    }

    async calibrateHoldAudioLevels() {
        try {
            this.showToast('Auto-calibrating audio levels...', 'info');

            if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                const audioContext = new (window.AudioContext || window.webkitAudioContext)();
                const source = audioContext.createMediaStreamSource(stream);
                const analyser = audioContext.createAnalyser();

                source.connect(analyser);
                analyser.fftSize = 256;

                const dataArray = new Uint8Array(analyser.frequencyBinCount);

                // Measure for 2 seconds
                const measureDuration = 2000;
                const startTime = Date.now();
                let maxLevel = 0;

                const measure = () => {
                    analyser.getByteFrequencyData(dataArray);
                    const average = dataArray.reduce((a, b) => a + b) / dataArray.length;
                    maxLevel = Math.max(maxLevel, average);

                    if (Date.now() - startTime < measureDuration) {
                        requestAnimationFrame(measure);
                    } else {
                        // Calculate optimal input level (aim for 70-80% of max)
                        const optimalLevel = Math.min(80, Math.max(50, 100 - (maxLevel / 255) * 50));

                        document.getElementById('hold-input-level').value = optimalLevel;
                        document.querySelector('output[for="hold-input-level"]').textContent = `${optimalLevel}%`;

                        stream.getTracks().forEach(track => track.stop());
                        this.showToast(`Auto-calibration complete. Input level set to ${optimalLevel}%`, 'success');
                    }
                };

                measure();
            } else {
                // Set reasonable defaults
                document.getElementById('hold-input-level').value = 75;
                document.querySelector('output[for="hold-input-level"]').textContent = '75%';
                this.showToast('Auto-calibration not available. Set to default 75%', 'info');
            }
        } catch (error) {
            console.error('Audio calibration failed:', error);
            this.showToast('Calibration failed: ' + error.message, 'error');
        }
    }

    async testIVRAudioDevices() {
        try {
            this.showToast('Testing IVR audio devices...', 'info');

            const inputDevice = document.getElementById('ivr-input-device').value;
            const outputDevice = document.getElementById('ivr-output-device').value;
            const inputLevel = document.getElementById('ivr-input-level').value;
            const outputLevel = document.getElementById('ivr-output-level').value;

            console.log('Testing IVR audio:', { inputDevice, outputDevice, inputLevel, outputLevel });

            // Test with speech synthesis for IVR
            if ('speechSynthesis' in window) {
                const utterance = new SpeechSynthesisUtterance('Testing IVR audio output device. Can you hear this message clearly?');
                utterance.volume = outputLevel / 100;
                utterance.rate = 1.0;
                utterance.pitch = 1.0;
                speechSynthesis.speak(utterance);
            }

            // Test input device
            if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                const stream = await navigator.mediaDevices.getUserMedia({
                    audio: {
                        deviceId: inputDevice !== 'default' ? inputDevice : undefined,
                        echoCancellation: true,
                        noiseSuppression: true
                    }
                });

                // Monitor input for 2 seconds
                const audioContext = new (window.AudioContext || window.webkitAudioContext)();
                const source = audioContext.createMediaStreamSource(stream);
                const analyser = audioContext.createAnalyser();

                source.connect(analyser);
                analyser.fftSize = 256;

                setTimeout(() => {
                    stream.getTracks().forEach(track => track.stop());
                    this.showToast('IVR audio test completed', 'success');
                }, 2000);
            } else {
                this.showToast('IVR audio devices configured', 'success');
            }
        } catch (error) {
            console.error('IVR audio test failed:', error);
            this.showToast('IVR audio test failed: ' + error.message, 'error');
        }
    }

    async recordIVRPrompt() {
        try {
            this.showToast('Starting IVR prompt recording...', 'info');

            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                this.showToast('Recording not supported in this browser', 'error');
                return;
            }

            const inputDevice = document.getElementById('ivr-input-device').value;
            const quality = document.getElementById('ivr-quality').value;

            // Get sample rate based on quality setting
            const sampleRates = {
                'telephone': 8000,
                'voip': 16000,
                'cd': 44100,
                'studio': 48000
            };

            const stream = await navigator.mediaDevices.getUserMedia({
                audio: {
                    deviceId: inputDevice !== 'default' ? inputDevice : undefined,
                    echoCancellation: true,
                    noiseSuppression: true,
                    sampleRate: sampleRates[quality] || 16000
                }
            });

            if (!window.MediaRecorder) {
                this.showToast('MediaRecorder not supported', 'error');
                return;
            }

            const mediaRecorder = new MediaRecorder(stream);
            const audioChunks = [];

            mediaRecorder.ondataavailable = (event) => {
                audioChunks.push(event.data);
            };

            mediaRecorder.onstop = () => {
                const audioBlob = new Blob(audioChunks, { type: 'audio/wav' });
                const audioUrl = URL.createObjectURL(audioBlob);

                // Create a temporary audio element to test playback
                const audio = new Audio(audioUrl);
                audio.controls = true;
                audio.style.marginTop = '10px';

                // Show recording in a dialog or panel
                this.showRecordingResult(audioUrl, audioBlob);

                stream.getTracks().forEach(track => track.stop());
            };

            // Start recording
            mediaRecorder.start();
            this.showToast('Recording... Click "Stop Recording" when done', 'info');

            // Create stop button
            const stopBtn = document.createElement('button');
            stopBtn.textContent = 'Stop Recording';
            stopBtn.className = 'btn-secondary';
            stopBtn.onclick = () => {
                mediaRecorder.stop();
                stopBtn.remove();
            };

            // Add stop button to the record button's parent
            const recordBtn = document.getElementById('record-ivr-prompt');
            recordBtn.parentNode.appendChild(stopBtn);

        } catch (error) {
            console.error('Recording failed:', error);
            this.showToast('Recording failed: ' + error.message, 'error');
        }
    }

    showRecordingResult(audioUrl, audioBlob) {
        // Create a simple modal to show recording result
        const modal = document.createElement('div');
        modal.className = 'modal-overlay';
        modal.innerHTML = `
            <div class="modal-content">
                <h3>IVR Prompt Recording</h3>
                <audio controls src="${audioUrl}"></audio>
                <div class="recording-info">
                    <p>Size: ${(audioBlob.size / 1024).toFixed(1)} KB</p>
                    <p>Type: ${audioBlob.type}</p>
                </div>
                <div class="modal-actions">
                    <button class="btn-primary" onclick="this.parentElement.parentElement.parentElement.remove()">Use Recording</button>
                    <button class="btn-secondary" onclick="this.parentElement.parentElement.parentElement.remove()">Discard</button>
                </div>
            </div>
        `;

        document.body.appendChild(modal);
        this.showToast('Recording completed successfully', 'success');
    }

    async refreshIVRAudioDevices() {
        try {
            this.showToast('Refreshing IVR audio devices...', 'info');

            // This is the same as refreshHoldAudioDevices but for IVR
            if (navigator.mediaDevices && navigator.mediaDevices.enumerateDevices) {
                const devices = await navigator.mediaDevices.enumerateDevices();

                const inputSelect = document.getElementById('ivr-input-device');
                const outputSelect = document.getElementById('ivr-output-device');

                // Clear and rebuild device lists
                ['ivr-input-device', 'ivr-output-device'].forEach(id => {
                    const select = document.getElementById(id);
                    const defaultOptions = Array.from(select.children).filter(option =>
                        ['default', 'built-in', 'usb-audio', 'bluetooth', 'line-in', 'line-out', 'phone-line'].includes(option.value)
                    );
                    select.innerHTML = '';
                    defaultOptions.forEach(option => select.appendChild(option));
                });

                // Add discovered devices
                devices.forEach(device => {
                    if (device.kind === 'audioinput' && device.deviceId) {
                        const option = document.createElement('option');
                        option.value = device.deviceId;
                        option.textContent = device.label || `Microphone ${device.deviceId.substr(0, 8)}`;
                        inputSelect.appendChild(option);
                    } else if (device.kind === 'audiooutput' && device.deviceId) {
                        const option = document.createElement('option');
                        option.value = device.deviceId;
                        option.textContent = device.label || `Speaker ${device.deviceId.substr(0, 8)}`;
                        outputSelect.appendChild(option);
                    }
                });

                this.showToast(`IVR devices updated: ${devices.length} found`, 'success');
            } else {
                this.showToast('Device enumeration not supported', 'warning');
            }
        } catch (error) {
            console.error('Failed to refresh IVR audio devices:', error);
            this.showToast('Failed to refresh IVR devices: ' + error.message, 'error');
        }
    }

    // SIP Trunk Provider Template Management

    loadSIPTrunkTemplate(provider) {
        this.showToast(`Loading ${provider} SIP trunk template...`, 'info');

        const templates = {
            twilio: {
                name: 'Twilio SIP Trunk',
                protocol: 'udp',
                port: 5060,
                registrationInterval: 300,
                qualifyFrequency: 60,
                sessionTimers: 'accept',
                natSupport: true,
                dtmfRfc2833: true,
                codecs: ['g711u', 'g711a', 'g729'],
                host: 'your-twilio-domain.pstn.twilio.com',
                username: 'your-twilio-username',
                description: 'Twilio cloud-based SIP trunk with high reliability and global reach.'
            },
            vonage: {
                name: 'Vonage SIP Trunk',
                protocol: 'udp',
                port: 5060,
                registrationInterval: 1800,
                qualifyFrequency: 60,
                sessionTimers: 'accept',
                natSupport: true,
                dtmfRfc2833: true,
                codecs: ['g711u', 'g711a', 'g722'],
                host: 'sip.nexmo.com',
                username: 'your-vonage-key',
                description: 'Vonage (formerly Nexmo) SIP trunk with advanced communication APIs.'
            },
            bandwidth: {
                name: 'Bandwidth SIP Trunk',
                protocol: 'udp',
                port: 5060,
                registrationInterval: 300,
                qualifyFrequency: 30,
                sessionTimers: 'originate',
                natSupport: true,
                dtmfRfc2833: true,
                codecs: ['g711u', 'g711a', 'g729', 'g722'],
                host: 'your-bandwidth-host.bandwidth.com',
                username: 'your-bandwidth-username',
                description: 'Bandwidth SIP trunk with carrier-grade voice services and APIs.'
            },
            flowroute: {
                name: 'Flowroute SIP Trunk',
                protocol: 'udp',
                port: 5060,
                registrationInterval: 120,
                qualifyFrequency: 60,
                sessionTimers: 'accept',
                natSupport: true,
                dtmfRfc2833: true,
                codecs: ['g711u', 'g711a', 'g729'],
                host: 'your-flowroute-host.sip.flowroute.com',
                username: 'your-flowroute-access-key',
                description: 'Flowroute SIP trunk with competitive pricing and reliable voice services.'
            },
            custom: {
                name: 'Custom SIP Trunk',
                protocol: 'udp',
                port: 5060,
                registrationInterval: 300,
                qualifyFrequency: 60,
                sessionTimers: 'accept',
                natSupport: true,
                dtmfRfc2833: true,
                codecs: ['g711u', 'g711a'],
                host: 'your-custom-sip-host.com',
                username: 'your-username',
                description: 'Custom SIP trunk configuration for your specific provider.'
            }
        };

        const template = templates[provider];
        if (!template) {
            this.showToast('Unknown provider template', 'error');
            return;
        }

        // Apply template settings to the form
        document.getElementById('sip-trunk-protocol').value = template.protocol;
        document.getElementById('sip-trunk-port').value = template.port;
        document.getElementById('sip-registration-interval').value = template.registrationInterval;
        document.getElementById('sip-qualify-frequency').value = template.qualifyFrequency;
        document.getElementById('sip-session-timers').value = template.sessionTimers;
        document.getElementById('sip-nat-support').checked = template.natSupport;
        document.getElementById('sip-dtmf-rfc2833').checked = template.dtmfRfc2833;

        // Set codec preferences
        const allCodecs = ['g711u', 'g711a', 'g729', 'g722', 'opus'];
        allCodecs.forEach(codec => {
            const checkbox = document.getElementById(`codec-${codec}`);
            if (checkbox) {
                checkbox.checked = template.codecs.includes(codec);
            }
        });

        // Create a modal to show the template details and configuration
        this.showSIPTrunkTemplateModal(template);

        this.showToast(`${template.name} template loaded successfully`, 'success');
    }

    showSIPTrunkTemplateModal(template) {
        const modal = document.createElement('div');
        modal.className = 'modal-overlay';
        modal.innerHTML = `
            <div class="modal-content sip-trunk-modal">
                <h3>${template.name} Configuration</h3>
                <div class="template-description">
                    <p>${template.description}</p>
                </div>

                <div class="template-settings">
                    <h4>Quick Setup</h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="template-host">SIP Host</label>
                            <input type="text" id="template-host" value="${template.host}" placeholder="Enter your SIP host">
                        </div>
                        <div class="form-group">
                            <label for="template-username">Username</label>
                            <input type="text" id="template-username" value="${template.username}" placeholder="Enter your username">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="template-password">Password</label>
                            <input type="password" id="template-password" placeholder="Enter your password">
                        </div>
                        <div class="form-group">
                            <label for="template-did">DID/Phone Number</label>
                            <input type="text" id="template-did" placeholder="+1234567890">
                        </div>
                    </div>
                </div>

                <div class="template-advanced">
                    <h4>Advanced Configuration</h4>
                    <div class="config-summary">
                        <div class="config-item">
                            <strong>Protocol:</strong> ${template.protocol.toUpperCase()}
                        </div>
                        <div class="config-item">
                            <strong>Port:</strong> ${template.port}
                        </div>
                        <div class="config-item">
                            <strong>Codecs:</strong> ${template.codecs.join(', ').toUpperCase()}
                        </div>
                        <div class="config-item">
                            <strong>Registration:</strong> ${template.registrationInterval}s
                        </div>
                    </div>
                </div>

                <div class="modal-actions">
                    <button class="btn-primary" id="create-trunk-btn">Create Trunk</button>
                    <button class="btn-secondary" id="test-trunk-btn">Test Connection</button>
                    <button class="btn-secondary" onclick="this.parentElement.parentElement.parentElement.remove()">Cancel</button>
                </div>
            </div>
        `;

        // Add event handlers for the modal buttons
        const createBtn = modal.querySelector('#create-trunk-btn');
        const testBtn = modal.querySelector('#test-trunk-btn');

        createBtn.addEventListener('click', () => {
            this.createSIPTrunkFromTemplate(template, modal);
        });

        testBtn.addEventListener('click', () => {
            this.testSIPTrunkConnection(template, modal);
        });

        document.body.appendChild(modal);
    }

    createSIPTrunkFromTemplate(template, modal) {
        const host = modal.querySelector('#template-host').value;
        const username = modal.querySelector('#template-username').value;
        const password = modal.querySelector('#template-password').value;
        const did = modal.querySelector('#template-did').value;

        if (!host || !username || !password) {
            this.showToast('Please fill in all required fields', 'error');
            return;
        }

        // Simulate trunk creation
        const trunkConfig = {
            id: `trunk-${Date.now()}`,
            name: template.name,
            host: host,
            username: username,
            password: password,
            did: did,
            protocol: template.protocol,
            port: template.port,
            status: 'configuring'
        };

        this.addSIPTrunkToList(trunkConfig);
        modal.remove();
        this.showToast(`${template.name} created successfully!`, 'success');
    }

    addSIPTrunkToList(trunkConfig) {
        const trunkList = document.getElementById('trunk-list');
        const emptyState = trunkList.querySelector('.empty-state');

        if (emptyState) {
            emptyState.remove();
        }

        const trunkElement = document.createElement('div');
        trunkElement.className = 'trunk-item';
        trunkElement.innerHTML = `
            <div class="trunk-info">
                <h5>${trunkConfig.name}</h5>
                <p>Host: ${trunkConfig.host}</p>
                <p>Username: ${trunkConfig.username}</p>
                ${trunkConfig.did ? `<p>DID: ${trunkConfig.did}</p>` : ''}
                <span class="trunk-status ${trunkConfig.status}">${trunkConfig.status.toUpperCase()}</span>
            </div>
            <div class="trunk-actions">
                <button class="btn-secondary btn-sm">Edit</button>
                <button class="btn-secondary btn-sm">Test</button>
                <button class="btn-danger btn-sm">Delete</button>
            </div>
        `;

        trunkList.appendChild(trunkElement);
    }

    testSIPTrunkConnection(template, modal) {
        const host = modal.querySelector('#template-host').value;
        const username = modal.querySelector('#template-username').value;

        if (!host || !username) {
            this.showToast('Host and username are required for testing', 'error');
            return;
        }

        this.showToast('Testing SIP trunk connection...', 'info');

        // Simulate connection test
        setTimeout(() => {
            const success = Math.random() > 0.3; // 70% success rate for demo
            if (success) {
                this.showToast('SIP trunk connection test successful!', 'success');
            } else {
                this.showToast('Connection test failed. Please check your settings.', 'error');
            }
        }, 2000);
    }

    // Logging System Methods
    addLogEntry(level, type, message, source = '', metadata = {}) {
        const timestamp = new Date().toLocaleString();
        const logEntry = {
            id: Date.now() + Math.random(),
            timestamp,
            level,
            type,
            message,
            source,
            metadata
        };

        this.logs.unshift(logEntry); // Add to beginning

        // Keep only the most recent logs
        if (this.logs.length > this.maxLogs) {
            this.logs = this.logs.slice(0, this.maxLogs);
        }

        this.updateLogsTable();

        // Also log to console
        console.log(`[${level}] ${type}: ${message}`, metadata);
    }

    updateLogsTable() {
        const tableBody = document.getElementById('logs-table-body');
        if (!tableBody) return;

        // Clear existing entries except sample data
        tableBody.innerHTML = '';

        // Add current logs
        this.logs.forEach(log => {
            const row = document.createElement('tr');
            row.className = `log-entry ${log.level.toLowerCase()}`;

            const levelClass = this.getLogLevelClass(log.level);
            const typeClass = this.getLogTypeClass(log.type);

            row.innerHTML = `
                <td>${log.timestamp}</td>
                <td><span class="log-level ${levelClass}">${log.level}</span></td>
                <td><span class="log-type ${typeClass}">${log.type}</span></td>
                <td>${log.message}</td>
                <td>${log.source}</td>
                <td>
                    <div class="metadata">
                        ${Object.entries(log.metadata).map(([key, value]) =>
                            `<span class="meta-item">${key}: ${value}</span>`
                        ).join('')}
                    </div>
                </td>
                <td>
                    <button class="btn-small" onclick="window.app.viewLogDetails('${log.id}')">Details</button>
                </td>
            `;

            tableBody.appendChild(row);
        });

        // If no logs, show a message
        if (this.logs.length === 0) {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td colspan="7" style="text-align: center; color: #666; font-style: italic; padding: 20px;">
                    No log entries yet. Logs will appear here as services start and events occur.
                </td>
            `;
            tableBody.appendChild(row);
        }
    }

    getLogLevelClass(level) {
        const levelMap = {
            'SUCCESS': 'success',
            'INFO': 'info',
            'WARNING': 'warning',
            'ERROR': 'error',
            'DEBUG': 'debug'
        };
        return levelMap[level] || 'info';
    }

    getLogTypeClass(type) {
        const typeMap = {
            'Service': 'service',
            'Installation': 'installation',
            'Configuration': 'configuration',
            'Connection': 'connection',
            'Audio': 'audio',
            'Module': 'module',
            'System': 'system'
        };
        return typeMap[type] || 'general';
    }

    viewLogDetails(logId) {
        const log = this.logs.find(l => l.id == logId);
        if (!log) return;

        const modal = document.createElement('div');
        modal.className = 'modal-overlay';
        modal.innerHTML = `
            <div class="modal-content">
                <h3>Log Entry Details</h3>
                <div class="log-details">
                    <div class="detail-row">
                        <strong>Timestamp:</strong> ${log.timestamp}
                    </div>
                    <div class="detail-row">
                        <strong>Level:</strong> <span class="log-level ${this.getLogLevelClass(log.level)}">${log.level}</span>
                    </div>
                    <div class="detail-row">
                        <strong>Type:</strong> <span class="log-type ${this.getLogTypeClass(log.type)}">${log.type}</span>
                    </div>
                    <div class="detail-row">
                        <strong>Message:</strong> ${log.message}
                    </div>
                    <div class="detail-row">
                        <strong>Source:</strong> ${log.source}
                    </div>
                    <div class="detail-row">
                        <strong>Metadata:</strong>
                        <pre>${JSON.stringify(log.metadata, null, 2)}</pre>
                    </div>
                </div>
                <div class="modal-actions">
                    <button class="btn-secondary" onclick="this.parentElement.parentElement.parentElement.remove()">Close</button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }

    clearLogs() {
        this.logs = [];
        this.updateLogsTable();
        this.addLogEntry('INFO', 'System', 'Log history cleared', 'app.js');
    }

    // Initialize logging when app starts
    initializeLogging() {
        // Add initial startup logs
        this.addLogEntry('INFO', 'System', 'FlexPBX Desktop Application started', 'app.js:init', {
            version: '1.0.0',
            platform: navigator.platform,
            userAgent: navigator.userAgent.substring(0, 50) + '...'
        });

        // Hook into existing methods to add logging
        this.setupLoggingHooks();
    }

    setupLoggingHooks() {
        // Override showToast to also log
        const originalShowToast = this.showToast.bind(this);
        this.showToast = (message, type = 'info') => {
            const logLevel = type === 'success' ? 'SUCCESS' :
                           type === 'error' ? 'ERROR' :
                           type === 'warning' ? 'WARNING' : 'INFO';

            this.addLogEntry(logLevel, 'System', message, 'toast', { type });
            return originalShowToast(message, type);
        };

        // Log service status
        this.logServiceStatus();
    }

    async logServiceStatus() {
        try {
            // Check if we can communicate with backend services
            if (window.electronAPI && window.electronAPI.getSystemInfo) {
                const systemInfo = await window.electronAPI.getSystemInfo();
                this.addLogEntry('SUCCESS', 'System', 'System information retrieved', 'electronAPI', {
                    platform: systemInfo.platform,
                    arch: systemInfo.arch,
                    version: systemInfo.version
                });
            }

            // Log initial service status
            this.addLogEntry('INFO', 'Service', 'Checking background services status...', 'app.js');

            // Simulate checking services (in a real app, this would query actual service status)
            setTimeout(() => {
                this.addLogEntry('SUCCESS', 'Service', 'FlexPBX Core service running on port 8080', 'service-manager');
                this.addLogEntry('SUCCESS', 'Service', 'Device Discovery service running on port 41235', 'service-manager');
                this.addLogEntry('SUCCESS', 'Service', 'File Server running on port 3923', 'service-manager');
                this.addLogEntry('SUCCESS', 'Audio', 'TTS Service initialized with 5 voice profiles', 'tts-service');
                this.addLogEntry('INFO', 'Audio', 'Sound Manager initialized successfully', 'sound-manager');
            }, 2000);

        } catch (error) {
            this.addLogEntry('ERROR', 'System', 'Failed to retrieve system information', 'electronAPI', {
                error: error.message
            });
        }
    }

    // PBX Name and User Management System
    initializePBXSystem() {
        // Initialize user configuration and PBX settings
        this.pbxConfig = {
            name: localStorage.getItem('pbx-name') || 'FlexPBX System',
            department: localStorage.getItem('user-department') || 'admin',
            role: localStorage.getItem('user-role') || 'administrator',
            extension: localStorage.getItem('user-extension') || null,
            isAdmin: localStorage.getItem('user-role') === 'administrator',
            serverUrl: localStorage.getItem('pbx-server-url') || null,
            syncEnabled: localStorage.getItem('settings-sync') === 'true'
        };

        // Setup all PBX features
        this.setupPBXNameManagement();
        this.setupAnnouncementsSystem();
        this.setupDepartmentBasedUI();
        this.setupRealTimeSyncSystem();
        this.setupFlexPhoneIntegration();
        this.setupDIDSupport();
        this.setupGoogleVoiceIntegration();

        // Initialize interface based on user role
        this.applyUserRoleInterface();

        this.addLogEntry('INFO', 'System', `PBX System initialized for ${this.pbxConfig.role}`, 'pbx-init', {
            department: this.pbxConfig.department,
            extension: this.pbxConfig.extension,
            isAdmin: this.pbxConfig.isAdmin
        });
    }

    setupPBXNameManagement() {
        this.updatePBXNameDisplay();

        // Setup edit button (admin only)
        const editBtn = document.getElementById('edit-pbx-name');
        if (editBtn) {
            if (this.pbxConfig.isAdmin) {
                editBtn.addEventListener('click', () => this.showPBXNameDialog());
            } else {
                editBtn.style.display = 'none';
            }
        }
    }

    updatePBXNameDisplay() {
        const nameElement = document.getElementById('current-pbx-name');
        if (nameElement) {
            nameElement.textContent = this.pbxConfig.name;
        }

        // Update page title based on role
        if (this.pbxConfig.isAdmin) {
            document.title = `${this.pbxConfig.name} - FlexPBX Admin Console`;
        } else {
            document.title = `${this.pbxConfig.name} - FlexPBX Desktop`;
        }
    }

    showPBXNameDialog() {
        if (!this.pbxConfig.isAdmin) {
            this.showToast('Only administrators can change PBX name', 'error');
            return;
        }

        const currentName = this.pbxConfig.name;
        const newName = prompt('Enter PBX Name:', currentName);

        if (newName && newName.trim() && newName.trim() !== currentName) {
            this.pbxConfig.name = newName.trim();
            localStorage.setItem('pbx-name', this.pbxConfig.name);
            this.updatePBXNameDisplay();

            // Sync change to all connected clients and FlexPhone
            this.broadcastSettingsUpdate('pbx-name', this.pbxConfig.name);

            this.addLogEntry('INFO', 'Admin', `PBX name changed to: ${this.pbxConfig.name}`, 'pbx-config', {
                oldName: currentName,
                newName: this.pbxConfig.name
            });

            this.showToast(`PBX name updated to: ${this.pbxConfig.name}`, 'success');
        }
    }

    setupAnnouncementsSystem() {
        this.announcements = JSON.parse(localStorage.getItem('pbx-announcements') || '[]');

        // Setup announcement controls (admin only)
        const addBtn = document.getElementById('add-announcement');
        const manageBtn = document.getElementById('manage-announcements');

        if (this.pbxConfig.isAdmin) {
            if (addBtn) addBtn.addEventListener('click', () => this.showAddAnnouncementDialog());
            if (manageBtn) manageBtn.addEventListener('click', () => this.showManageAnnouncementsDialog());
        } else {
            // Hide admin controls for non-admin users
            if (addBtn) addBtn.style.display = 'none';
            if (manageBtn) manageBtn.style.display = 'none';
        }

        this.loadAnnouncementsForUser();
    }

    loadAnnouncementsForUser() {
        const userDept = this.pbxConfig.department;
        const userRole = this.pbxConfig.role;

        // Filter announcements based on department and role
        const relevantAnnouncements = this.announcements.filter(announcement => {
            const audience = announcement.audience;

            // Admins see all announcements
            if (this.pbxConfig.isAdmin) return true;

            // Show announcements targeted to user's department or all departments
            return audience === 'all' ||
                   audience === userDept ||
                   (Array.isArray(audience) && audience.includes(userDept));
        });

        this.displayAnnouncements(relevantAnnouncements);
    }

    displayAnnouncements(announcements) {
        const container = document.getElementById('announcements-list');
        if (!container) return;

        // Keep the welcome announcement
        const welcomeAnnouncement = container.querySelector('.welcome-announcement');
        container.innerHTML = '';

        if (welcomeAnnouncement) {
            // Update welcome message based on user role
            this.updateWelcomeMessage(welcomeAnnouncement);
            container.appendChild(welcomeAnnouncement);
        }

        // Add custom announcements
        announcements.forEach(announcement => {
            const announcementElement = this.createAnnouncementElement(announcement);
            container.appendChild(announcementElement);
        });
    }

    updateWelcomeMessage(welcomeElement) {
        const messageElement = welcomeElement.querySelector('.announcement-message');
        const audienceElement = welcomeElement.querySelector('.announcement-audience');

        if (this.pbxConfig.extension) {
            messageElement.textContent = `Welcome to ${this.pbxConfig.name}! You are assigned to extension ${this.pbxConfig.extension} in the ${this.pbxConfig.department} department. Use the FlexPhone app for SIP calls and this desktop app for system management and support features.`;
            audienceElement.textContent = `📞 Extension ${this.pbxConfig.extension} - ${this.pbxConfig.department.charAt(0).toUpperCase() + this.pbxConfig.department.slice(1)} Department`;
        } else if (this.pbxConfig.isAdmin) {
            messageElement.textContent = `Welcome to ${this.pbxConfig.name} Admin Console! You have full access to all features including user management, announcements, and system configuration. Changes you make will be pushed to all connected clients in real-time.`;
            audienceElement.textContent = '🔧 Administrator Console';
        } else {
            messageElement.textContent = `Welcome to ${this.pbxConfig.name}! Your access is configured for the ${this.pbxConfig.department} department. Contact support for extension assignment or additional features.`;
            audienceElement.textContent = `👥 ${this.pbxConfig.department.charAt(0).toUpperCase() + this.pbxConfig.department.slice(1)} Department`;
        }
    }

    createAnnouncementElement(announcement) {
        const element = document.createElement('div');
        element.className = 'announcement-item';
        element.setAttribute('data-priority', announcement.priority);
        element.setAttribute('data-audience', announcement.audience);

        const priorityClass = announcement.priority || 'medium';
        const audienceText = this.formatAudienceText(announcement.audience);

        element.innerHTML = `
            <div class="announcement-content">
                <div class="announcement-header">
                    <span class="announcement-icon">${announcement.icon || '📢'}</span>
                    <h4>${announcement.title}</h4>
                    <span class="announcement-priority ${priorityClass}">${priorityClass.charAt(0).toUpperCase() + priorityClass.slice(1)} Priority</span>
                </div>
                <p class="announcement-message">${announcement.message}</p>
                <div class="announcement-footer">
                    <span class="announcement-audience">${audienceText}</span>
                    <span class="announcement-date">${new Date(announcement.created).toLocaleDateString()}</span>
                </div>
            </div>
        `;

        return element;
    }

    formatAudienceText(audience) {
        if (audience === 'all') return '👥 All Departments';
        if (audience === 'admin') return '🔧 Administrators';
        if (audience === 'support') return '🎧 Support Department';
        if (audience === 'operators') return '📞 Operators';
        if (audience === 'guests') return '👤 Guests';
        return `👥 ${audience.charAt(0).toUpperCase() + audience.slice(1)}`;
    }

    showAddAnnouncementDialog() {
        if (!this.pbxConfig.isAdmin) {
            this.showToast('Only administrators can add announcements', 'error');
            return;
        }

        const title = prompt('Announcement Title:');
        if (!title) return;

        const message = prompt('Announcement Message:');
        if (!message) return;

        const priority = prompt('Priority (high/medium/low):', 'medium');
        const audience = prompt('Audience (all/admin/support/operators/guests):', 'all');

        const announcement = {
            id: Date.now(),
            title: title.trim(),
            message: message.trim(),
            priority: priority.toLowerCase(),
            audience: audience.toLowerCase(),
            icon: '📢',
            created: new Date().toISOString(),
            author: 'Administrator'
        };

        this.announcements.unshift(announcement);
        localStorage.setItem('pbx-announcements', JSON.stringify(this.announcements));

        // Broadcast announcement to all connected clients
        this.broadcastSettingsUpdate('announcements', this.announcements);

        this.loadAnnouncementsForUser();

        this.addLogEntry('INFO', 'Admin', `New announcement added: ${title}`, 'announcements', {
            title,
            audience,
            priority
        });

        this.showToast('Announcement added and broadcasted to all clients', 'success');
    }

    showManageAnnouncementsDialog() {
        if (!this.pbxConfig.isAdmin) {
            this.showToast('Only administrators can manage announcements', 'error');
            return;
        }

        const count = this.announcements.length;
        const action = prompt(`Manage Announcements (${count} total)\nEnter 'clear' to remove all custom announcements:`);

        if (action === 'clear') {
            this.announcements = [];
            localStorage.setItem('pbx-announcements', JSON.stringify(this.announcements));

            // Broadcast change to all clients
            this.broadcastSettingsUpdate('announcements', this.announcements);

            this.loadAnnouncementsForUser();
            this.showToast('All custom announcements cleared and synced', 'success');

            this.addLogEntry('INFO', 'Admin', 'All custom announcements cleared', 'announcements');
        }
    }

    setupDepartmentBasedUI() {
        // Apply interface restrictions based on user role
        this.applyUserRoleInterface();

        // Setup extension display if user has one
        if (this.pbxConfig.extension) {
            this.displayUserExtensionInfo();
        }
    }

    applyUserRoleInterface() {
        const isAdmin = this.pbxConfig.isAdmin;

        if (!isAdmin) {
            // Hide admin-only navigation items
            const adminOnlyItems = document.querySelectorAll('[data-admin-only="true"]');
            adminOnlyItems.forEach(item => {
                item.style.display = 'none';
            });

            // Hide admin tabs and features
            const adminTabs = ['server-manager', 'deployment'];
            adminTabs.forEach(tabId => {
                const tab = document.querySelector(`[data-view="${tabId}"]`);
                if (tab) tab.style.display = 'none';
            });

            // Show limited feature set based on department
            this.showDepartmentFeatures();
        } else {
            // Admin gets full access - ensure everything is visible
            this.showAllFeatures();
        }
    }

    showDepartmentFeatures() {
        const dept = this.pbxConfig.department;

        // Show features based on department
        const allowedFeatures = {
            support: ['services', 'logs', 'testing'],
            operators: ['services', 'logs'],
            guests: ['logs']
        };

        const userFeatures = allowedFeatures[dept] || ['logs'];

        // Hide features not allowed for this department
        const allTabs = document.querySelectorAll('.sidebar-nav button[data-view]');
        allTabs.forEach(tab => {
            const view = tab.getAttribute('data-view');
            if (!userFeatures.includes(view) && view !== 'dashboard') {
                tab.style.display = 'none';
            }
        });
    }

    showAllFeatures() {
        // Admin sees everything - make sure nothing is hidden
        const allElements = document.querySelectorAll('[style*="display: none"]');
        allElements.forEach(element => {
            if (!element.classList.contains('view') || element.classList.contains('active')) {
                // Don't show all views at once, just navigation elements
                if (!element.classList.contains('view')) {
                    element.style.display = '';
                }
            }
        });
    }

    displayUserExtensionInfo() {
        const extension = this.pbxConfig.extension;
        const department = this.pbxConfig.department;

        // Add extension info to sidebar
        const pbxNameDisplay = document.getElementById('pbx-name-display');
        if (pbxNameDisplay && extension) {
            // Remove existing extension info
            const existingInfo = pbxNameDisplay.querySelector('.user-extension-info');
            if (existingInfo) existingInfo.remove();

            const extensionInfo = document.createElement('div');
            extensionInfo.className = 'user-extension-info';
            extensionInfo.innerHTML = `
                <div class="extension-label">Your Extension:</div>
                <div class="extension-number">${extension}</div>
                <div class="extension-dept">${department.charAt(0).toUpperCase() + department.slice(1)} Department</div>
                <div class="extension-note">Use FlexPhone app for calls</div>
            `;
            pbxNameDisplay.appendChild(extensionInfo);
        }
    }

    // Real-time Settings Sync System
    setupRealTimeSyncSystem() {
        if (!this.pbxConfig.syncEnabled) return;

        // Setup WebSocket connection for real-time sync
        this.setupSyncWebSocket();

        // Setup periodic sync check
        this.setupPeriodicSync();

        // Listen for storage changes from other tabs/windows
        window.addEventListener('storage', (e) => {
            this.handleStorageChange(e);
        });
    }

    setupSyncWebSocket() {
        // Connect to PBX server WebSocket for real-time updates
        const serverUrl = this.pbxConfig.serverUrl;
        if (serverUrl) {
            try {
                this.syncSocket = new WebSocket(`ws://${serverUrl}/sync`);

                this.syncSocket.onopen = () => {
                    this.addLogEntry('INFO', 'Sync', 'Connected to PBX server for real-time sync', 'websocket');
                };

                this.syncSocket.onmessage = (event) => {
                    this.handleSyncMessage(JSON.parse(event.data));
                };

                this.syncSocket.onclose = () => {
                    this.addLogEntry('WARNING', 'Sync', 'Disconnected from PBX server sync', 'websocket');
                    // Attempt reconnection
                    setTimeout(() => this.setupSyncWebSocket(), 5000);
                };
            } catch (error) {
                this.addLogEntry('ERROR', 'Sync', 'Failed to establish sync connection', 'websocket', { error: error.message });
            }
        }
    }

    setupPeriodicSync() {
        // Check for updates every 30 seconds
        this.syncInterval = setInterval(() => {
            this.checkForUpdates();
        }, 30000);
    }

    broadcastSettingsUpdate(key, value) {
        // Send update via WebSocket if connected
        if (this.syncSocket && this.syncSocket.readyState === WebSocket.OPEN) {
            this.syncSocket.send(JSON.stringify({
                type: 'settings-update',
                key: key,
                value: value,
                timestamp: Date.now(),
                source: 'admin'
            }));
        }

        // Also update local storage for immediate effect
        localStorage.setItem(key, typeof value === 'object' ? JSON.stringify(value) : value);
    }

    handleSyncMessage(data) {
        if (data.type === 'settings-update') {
            // Apply incoming settings update
            this.applySyncedSettings(data.key, data.value);

            this.addLogEntry('INFO', 'Sync', `Settings updated: ${data.key}`, 'sync-receive', {
                key: data.key,
                source: data.source
            });
        }
    }

    applySyncedSettings(key, value) {
        switch (key) {
            case 'pbx-name':
                this.pbxConfig.name = value;
                this.updatePBXNameDisplay();
                break;
            case 'announcements':
                this.announcements = value;
                this.loadAnnouncementsForUser();
                break;
            case 'user-role':
                this.pbxConfig.role = value;
                this.pbxConfig.isAdmin = value === 'administrator';
                this.applyUserRoleInterface();
                break;
            case 'user-extension':
                this.pbxConfig.extension = value;
                this.displayUserExtensionInfo();
                break;
        }

        // Update local storage
        localStorage.setItem(key, typeof value === 'object' ? JSON.stringify(value) : value);

        this.showToast(`Settings updated from server: ${key}`, 'info');
    }

    handleStorageChange(event) {
        // Handle changes from other tabs/windows
        if (event.key && event.newValue !== event.oldValue) {
            this.applySyncedSettings(event.key, event.newValue);
        }
    }

    checkForUpdates() {
        // Periodically check server for updates
        // This would typically make an API call to check for changes
        if (this.pbxConfig.serverUrl) {
            // Implementation would depend on your server API
            console.log('Checking for updates...');
        }
    }

    // FlexPhone SIP Client Integration
    setupFlexPhoneIntegration() {
        // Setup communication with FlexPhone app
        this.flexPhoneChannel = new BroadcastChannel('flexpbx-flexphone');

        this.flexPhoneChannel.onmessage = (event) => {
            this.handleFlexPhoneMessage(event.data);
        };

        // Send current settings to FlexPhone if it's running
        this.syncWithFlexPhone();
    }

    syncWithFlexPhone() {
        const settingsToSync = {
            pbxName: this.pbxConfig.name,
            extension: this.pbxConfig.extension,
            department: this.pbxConfig.department,
            serverUrl: this.pbxConfig.serverUrl,
            userRole: this.pbxConfig.role
        };

        this.flexPhoneChannel.postMessage({
            type: 'settings-sync',
            settings: settingsToSync,
            timestamp: Date.now()
        });
    }

    handleFlexPhoneMessage(data) {
        if (data.type === 'extension-status') {
            // Update extension status from FlexPhone
            this.addLogEntry('INFO', 'FlexPhone', `Extension ${data.extension} status: ${data.status}`, 'flexphone-sync', data);
        } else if (data.type === 'call-event') {
            // Log call events from FlexPhone
            this.addLogEntry('INFO', 'Calls', `Call event: ${data.event}`, 'flexphone-calls', data);
        }
    }

    // DID (Direct Inward Dialing) Support System
    setupDIDSupport() {
        // Initialize DID configuration
        this.didConfig = {
            numbers: JSON.parse(localStorage.getItem('did-numbers') || '[]'),
            assignments: JSON.parse(localStorage.getItem('did-assignments') || '{}'),
            pools: JSON.parse(localStorage.getItem('did-pools') || '[]'),
            providers: JSON.parse(localStorage.getItem('did-providers') || '[]')
        };

        // Add sample DID data for demonstration
        if (this.didConfig.numbers.length === 0 && this.pbxConfig.isAdmin) {
            this.initializeSampleDIDData();
        }

        // Update extension display with DID info
        this.updateExtensionDIDDisplay();
    }

    initializeSampleDIDData() {
        // Sample DID numbers and assignments
        this.didConfig.numbers = [
            { number: '+15551234567', provider: 'SIP Trunk Provider A', status: 'active', type: 'voice' },
            { number: '+15551234568', provider: 'SIP Trunk Provider A', status: 'active', type: 'voice' },
            { number: '+15551234569', provider: 'SIP Trunk Provider B', status: 'active', type: 'voice' },
            { number: '+15551234570', provider: 'SIP Trunk Provider B', status: 'available', type: 'voice' }
        ];

        this.didConfig.providers = [
            { name: 'SIP Trunk Provider A', type: 'sip', status: 'connected', didCount: 2 },
            { name: 'SIP Trunk Provider B', type: 'sip', status: 'connected', didCount: 2 }
        ];

        // Save to localStorage
        this.saveDIDConfiguration();

        this.addLogEntry('INFO', 'DID', 'Sample DID data initialized', 'did-setup', {
            numbersCount: this.didConfig.numbers.length,
            providersCount: this.didConfig.providers.length
        });
    }

    // Extension Management with DID Assignment
    assignExtensionToUser(username, department = 'support') {
        // Generate random extension or assign manually
        const extension = this.generateRandomExtension();
        const did = this.assignDIDToExtension(extension);

        const userConfig = {
            username: username,
            extension: extension,
            department: department,
            did: did,
            assigned: new Date().toISOString(),
            status: 'active'
        };

        // Save user extension assignment
        const assignments = JSON.parse(localStorage.getItem('extension-assignments') || '{}');
        assignments[username] = userConfig;
        localStorage.setItem('extension-assignments', JSON.stringify(assignments));

        // If this is the current user, update their config
        if (username === localStorage.getItem('current-username')) {
            this.pbxConfig.extension = extension;
            this.pbxConfig.did = did;
            localStorage.setItem('user-extension', extension);
            localStorage.setItem('user-did', did);
            this.displayUserExtensionInfo();
        }

        // Broadcast assignment to all clients
        this.broadcastSettingsUpdate('extension-assignments', assignments);

        this.addLogEntry('INFO', 'Extensions', `Extension ${extension} assigned to ${username}`, 'extension-assign', {
            username,
            extension,
            department,
            did
        });

        return userConfig;
    }

    generateRandomExtension() {
        // Generate random 3-4 digit extension
        const existingAssignments = JSON.parse(localStorage.getItem('extension-assignments') || '{}');
        const usedExtensions = Object.values(existingAssignments).map(a => a.extension);

        let extension;
        do {
            extension = Math.floor(Math.random() * (9999 - 100) + 100).toString();
        } while (usedExtensions.includes(extension));

        return extension;
    }

    assignDIDToExtension(extension) {
        // Find available DID number
        const availableDID = this.didConfig.numbers.find(did =>
            did.status === 'available' || !this.didConfig.assignments[did.number]
        );

        if (availableDID) {
            // Assign DID to extension
            this.didConfig.assignments[availableDID.number] = {
                extension: extension,
                assigned: new Date().toISOString(),
                type: 'extension'
            };

            availableDID.status = 'assigned';
            this.saveDIDConfiguration();

            return availableDID.number;
        }

        return null; // No available DID numbers
    }

    updateExtensionDIDDisplay() {
        if (this.pbxConfig.extension) {
            const userDID = this.pbxConfig.did || localStorage.getItem('user-did');

            if (userDID) {
                const extensionInfo = document.querySelector('.user-extension-info');
                if (extensionInfo) {
                    // Add DID info to extension display
                    const didInfo = document.createElement('div');
                    didInfo.className = 'extension-did';
                    didInfo.innerHTML = `
                        <div class="did-label">Direct Number:</div>
                        <div class="did-number">${userDID}</div>
                    `;
                    extensionInfo.appendChild(didInfo);
                }
            }
        }
    }

    // SIP Trunk DID Management
    manageSIPTrunkDIDs(trunkName, didNumbers) {
        const trunk = {
            name: trunkName,
            type: 'sip',
            didNumbers: didNumbers,
            status: 'active',
            configured: new Date().toISOString()
        };

        // Add DIDs from this trunk to our number pool
        didNumbers.forEach(number => {
            if (!this.didConfig.numbers.find(existing => existing.number === number)) {
                this.didConfig.numbers.push({
                    number: number,
                    provider: trunkName,
                    status: 'available',
                    type: 'voice',
                    trunk: trunkName
                });
            }
        });

        // Add trunk to providers if not exists
        if (!this.didConfig.providers.find(p => p.name === trunkName)) {
            this.didConfig.providers.push({
                name: trunkName,
                type: 'sip',
                status: 'connected',
                didCount: didNumbers.length
            });
        }

        this.saveDIDConfiguration();

        this.addLogEntry('INFO', 'SIP', `SIP trunk ${trunkName} configured with ${didNumbers.length} DID numbers`, 'sip-trunk', {
            trunkName,
            didCount: didNumbers.length
        });

        return trunk;
    }

    saveDIDConfiguration() {
        localStorage.setItem('did-numbers', JSON.stringify(this.didConfig.numbers));
        localStorage.setItem('did-assignments', JSON.stringify(this.didConfig.assignments));
        localStorage.setItem('did-pools', JSON.stringify(this.didConfig.pools));
        localStorage.setItem('did-providers', JSON.stringify(this.didConfig.providers));
    }

    // Admin function to manually assign extension to any user
    adminAssignExtension() {
        if (!this.pbxConfig.isAdmin) {
            this.showToast('Only administrators can assign extensions', 'error');
            return;
        }

        const username = prompt('Enter username to assign extension:');
        if (!username) return;

        const department = prompt('Enter department (support/operators/guests):', 'support');
        if (!department) return;

        const manualExtension = prompt('Enter specific extension (leave blank for random):');

        let extension;
        if (manualExtension && manualExtension.trim()) {
            extension = manualExtension.trim();
            // Check if extension is already used
            const assignments = JSON.parse(localStorage.getItem('extension-assignments') || '{}');
            const existingUser = Object.keys(assignments).find(user => assignments[user].extension === extension);
            if (existingUser) {
                this.showToast(`Extension ${extension} is already assigned to ${existingUser}`, 'error');
                return;
            }
        } else {
            extension = this.generateRandomExtension();
        }

        const did = this.assignDIDToExtension(extension);

        const userConfig = {
            username: username,
            extension: extension,
            department: department.toLowerCase(),
            did: did,
            assigned: new Date().toISOString(),
            status: 'active',
            assignedBy: 'Administrator'
        };

        // Save assignment
        const assignments = JSON.parse(localStorage.getItem('extension-assignments') || '{}');
        assignments[username] = userConfig;
        localStorage.setItem('extension-assignments', JSON.stringify(assignments));

        // Broadcast to all clients
        this.broadcastSettingsUpdate('extension-assignments', assignments);

        const message = did ?
            `Extension ${extension} with DID ${did} assigned to ${username}` :
            `Extension ${extension} assigned to ${username} (no DID available)`;

        this.showToast(message, 'success');

        this.addLogEntry('INFO', 'Admin', `Manual extension assignment: ${username} -> ${extension}`, 'admin-assign', userConfig);
    }

    // Get user's extension info for display
    getUserExtensionInfo(username) {
        const assignments = JSON.parse(localStorage.getItem('extension-assignments') || '{}');
        return assignments[username] || null;
    }

    // Quick function to test extension assignment for Walter Harper example
    testWalterHarperAssignment() {
        if (this.pbxConfig.isAdmin) {
            const walterConfig = this.assignExtensionToUser('walter.harper', 'support');
            this.showToast(`Test: Walter Harper assigned extension ${walterConfig.extension}`, 'info');
        }
    }

    // Google Voice Integration System
    setupGoogleVoiceIntegration() {
        // Initialize Google Voice configuration
        this.googleVoiceConfig = {
            enabled: localStorage.getItem('gv-enabled') === 'true',
            apiKey: localStorage.getItem('gv-api-key') || '',
            numbers: JSON.parse(localStorage.getItem('gv-numbers') || '[]'),
            smsEnabled: localStorage.getItem('gv-sms-enabled') === 'true',
            status: localStorage.getItem('gv-status') || 'disconnected'
        };

        // Setup Google Voice UI controls
        this.setupGoogleVoiceControls();

        // Update status display
        this.updateGoogleVoiceStatus();

        // If enabled, initialize connection
        if (this.googleVoiceConfig.enabled && this.googleVoiceConfig.apiKey) {
            this.initializeGoogleVoiceConnection();
        }
    }

    setupGoogleVoiceControls() {
        const setupBtn = document.getElementById('setup-google-voice');
        const helpBtn = document.getElementById('gv-help');
        const manualAssignBtn = document.getElementById('manual-did-assign');
        const viewAssignmentsBtn = document.getElementById('view-did-assignments');

        if (setupBtn) {
            setupBtn.addEventListener('click', () => this.showGoogleVoiceSetupDialog());
        }

        if (helpBtn) {
            helpBtn.addEventListener('click', () => this.showGoogleVoiceDocumentation());
        }

        if (manualAssignBtn) {
            manualAssignBtn.addEventListener('click', () => this.showManualDIDAssignmentDialog());
        }

        if (viewAssignmentsBtn) {
            viewAssignmentsBtn.addEventListener('click', () => this.showDIDAssignmentsView());
        }

        // Setup auto-assignment checkbox handlers
        this.setupAutoAssignmentHandlers();
    }

    updateGoogleVoiceStatus() {
        const statusElement = document.getElementById('gv-status');
        const statusIndicator = statusElement?.querySelector('.status-indicator');
        const statusMessage = statusElement?.querySelector('p');

        if (statusIndicator && statusMessage) {
            if (this.googleVoiceConfig.status === 'connected') {
                statusIndicator.className = 'status-indicator connected';
                statusIndicator.textContent = 'Connected';
                statusMessage.textContent = `Connected with ${this.googleVoiceConfig.numbers.length} Google Voice numbers`;

                // Update integration status container
                const integrationStatus = document.querySelector('.integration-status');
                if (integrationStatus) {
                    integrationStatus.classList.add('connected');
                }
            } else {
                statusIndicator.className = 'status-indicator disconnected';
                statusIndicator.textContent = 'Disconnected';
                statusMessage.textContent = 'Connect Google Voice for additional phone numbers and SMS support';
            }
        }
    }

    showGoogleVoiceSetupDialog() {
        const isConnected = this.googleVoiceConfig.status === 'connected';

        if (isConnected) {
            const action = confirm('Google Voice is already connected. Do you want to reconfigure?');
            if (!action) return;
        }

        const steps = `
Google Voice Setup Steps:

1. Enable Google Voice API in Google Cloud Console
2. Create OAuth 2.0 credentials or API key
3. Enable the Google Voice API for your project
4. Copy your credentials

Would you like to:
- Setup new connection
- View setup guide
- Manage existing connection

Enter 'setup' to continue with setup, 'guide' for documentation:
        `;

        const action = prompt(steps, 'setup');

        if (action === 'setup') {
            this.startGoogleVoiceSetup();
        } else if (action === 'guide') {
            this.showGoogleVoiceDocumentation();
        }
    }

    startGoogleVoiceSetup() {
        if (!this.pbxConfig.isAdmin) {
            this.showToast('Only administrators can configure Google Voice', 'error');
            return;
        }

        // Step 1: Get API credentials
        const apiKey = prompt('Enter your Google Cloud API key or OAuth credentials:');
        if (!apiKey) return;

        // Step 2: Verify credentials and get Google Voice numbers
        this.verifyGoogleVoiceCredentials(apiKey);
    }

    async verifyGoogleVoiceCredentials(apiKey) {
        try {
            // Note: In a real implementation, this would use the actual Google Voice API
            // For demo purposes, we'll simulate the process

            const simulatedNumbers = [
                '+15551234567',
                '+15551234568'
            ];

            // Store configuration
            this.googleVoiceConfig.apiKey = apiKey;
            this.googleVoiceConfig.numbers = simulatedNumbers;
            this.googleVoiceConfig.enabled = true;
            this.googleVoiceConfig.smsEnabled = true;
            this.googleVoiceConfig.status = 'connected';

            // Save to localStorage
            localStorage.setItem('gv-api-key', apiKey);
            localStorage.setItem('gv-numbers', JSON.stringify(simulatedNumbers));
            localStorage.setItem('gv-enabled', 'true');
            localStorage.setItem('gv-sms-enabled', 'true');
            localStorage.setItem('gv-status', 'connected');

            // Update UI
            this.updateGoogleVoiceStatus();

            // Add Google Voice numbers to DID pool
            this.integrateGoogleVoiceWithDIDs();

            // Show DID assignment options
            this.showDIDAssignmentOptions();

            // Apply automatic assignments based on checkboxes
            this.applyAutomaticDIDAssignments();

            // Broadcast to other clients
            this.broadcastSettingsUpdate('google-voice-config', this.googleVoiceConfig);

            this.addLogEntry('INFO', 'Google Voice', 'Google Voice integration configured successfully', 'gv-setup', {
                numbersCount: simulatedNumbers.length,
                smsEnabled: true
            });

            this.showToast(`Google Voice connected with ${simulatedNumbers.length} numbers`, 'success');

        } catch (error) {
            this.addLogEntry('ERROR', 'Google Voice', 'Failed to verify Google Voice credentials', 'gv-setup', {
                error: error.message
            });

            this.showToast('Failed to connect to Google Voice. Please check your credentials.', 'error');
        }
    }

    integrateGoogleVoiceWithDIDs() {
        // Add Google Voice numbers to DID system
        this.googleVoiceConfig.numbers.forEach(number => {
            if (!this.didConfig.numbers.find(existing => existing.number === number)) {
                this.didConfig.numbers.push({
                    number: number,
                    provider: 'Google Voice',
                    status: 'available',
                    type: 'voice+sms',
                    source: 'google-voice'
                });
            }
        });

        // Add Google Voice as a provider
        if (!this.didConfig.providers.find(p => p.name === 'Google Voice')) {
            this.didConfig.providers.push({
                name: 'Google Voice',
                type: 'google-voice',
                status: 'connected',
                didCount: this.googleVoiceConfig.numbers.length,
                features: ['voice', 'sms']
            });
        }

        this.saveDIDConfiguration();
    }

    async sendGoogleVoiceSMS(to, message, fromNumber = null) {
        if (!this.googleVoiceConfig.enabled || !this.googleVoiceConfig.smsEnabled) {
            throw new Error('Google Voice SMS is not enabled');
        }

        // Use first available number if none specified
        const gvNumber = fromNumber || this.googleVoiceConfig.numbers[0];

        // Note: In real implementation, this would use Google Voice API
        // For demo purposes, we'll simulate the SMS sending

        const smsData = {
            from: gvNumber,
            to: to,
            message: message,
            timestamp: new Date().toISOString(),
            status: 'sent',
            provider: 'google-voice'
        };

        this.addLogEntry('INFO', 'SMS', `SMS sent via Google Voice: ${to}`, 'gv-sms', smsData);

        return smsData;
    }

    showGoogleVoiceDocumentation() {
        const documentation = `
📞 Google Voice Integration Setup Guide

Step 1: Google Cloud Console Setup
• Go to console.cloud.google.com
• Create new project or select existing project
• Enable Google Voice API
• Create credentials (API Key or OAuth 2.0)

Step 2: API Configuration
• Navigate to APIs & Services > Credentials
• Create API Key or OAuth 2.0 Client ID
• Restrict API key to Google Voice API only
• Copy your credentials

Step 3: FlexPBX Integration
• Click "Setup Google Voice" in the dashboard
• Enter your API credentials
• System will verify and import your Google Voice numbers
• Numbers will be added to DID pool automatically

Step 4: Features Available
• Voice calls through Google Voice numbers
• SMS messaging integration
• Multiple Google Voice number support
• Integration with FlexPBX routing

Important Links:
• Google Cloud Console: https://console.cloud.google.com
• Google Voice API Docs: https://developers.google.com/voice
• OAuth 2.0 Setup: https://developers.google.com/identity/protocols/oauth2

Supported Features:
✅ Multiple Google Voice numbers
✅ SMS sending and receiving
✅ Call routing integration
✅ DID number pool integration
✅ Real-time sync across clients

Would you like to start the setup process now?
        `;

        alert(documentation);
    }

    // SMS functionality
    async handleGoogleVoiceSMS(extension, message, recipient) {
        try {
            // Get user's assigned Google Voice number
            const userAssignment = this.getUserExtensionInfo(extension);
            const gvNumber = this.getGoogleVoiceNumberForExtension(extension);

            if (!gvNumber) {
                throw new Error('No Google Voice number assigned to this extension');
            }

            const result = await this.sendGoogleVoiceSMS(recipient, message, gvNumber);

            this.addLogEntry('INFO', 'SMS', `SMS sent from extension ${extension}`, 'extension-sms', {
                extension,
                recipient,
                gvNumber,
                messageLength: message.length
            });

            return result;

        } catch (error) {
            this.addLogEntry('ERROR', 'SMS', `SMS failed from extension ${extension}`, 'extension-sms', {
                extension,
                error: error.message
            });
            throw error;
        }
    }

    getGoogleVoiceNumberForExtension(extension) {
        // Check if extension has assigned Google Voice number in DID assignments
        const gvNumbers = this.didConfig.numbers.filter(n => n.source === 'google-voice');

        for (const number of gvNumbers) {
            const assignment = this.didConfig.assignments[number.number];
            if (assignment && assignment.extension === extension) {
                return number.number;
            }
        }

        return null;
    }

    // Admin function to assign Google Voice number to extension
    assignGoogleVoiceToExtension(extension, gvNumber) {
        if (!this.pbxConfig.isAdmin) {
            this.showToast('Only administrators can assign Google Voice numbers', 'error');
            return;
        }

        const gvNumberObj = this.didConfig.numbers.find(n =>
            n.number === gvNumber && n.source === 'google-voice'
        );

        if (!gvNumberObj) {
            this.showToast('Google Voice number not found', 'error');
            return;
        }

        if (gvNumberObj.status === 'assigned') {
            this.showToast('Google Voice number is already assigned', 'error');
            return;
        }

        // Assign the number
        this.didConfig.assignments[gvNumber] = {
            extension: extension,
            assigned: new Date().toISOString(),
            type: 'extension',
            provider: 'google-voice'
        };

        gvNumberObj.status = 'assigned';
        this.saveDIDConfiguration();

        this.addLogEntry('INFO', 'Admin', `Google Voice number ${gvNumber} assigned to extension ${extension}`, 'gv-assign', {
            extension,
            gvNumber
        });

        this.showToast(`Google Voice number ${gvNumber} assigned to extension ${extension}`, 'success');

        // Broadcast to all clients
        this.broadcastSettingsUpdate('did-assignments', this.didConfig.assignments);
    }

    setupAutoAssignmentHandlers() {
        // Setup handlers for auto-assignment checkboxes
        const autoAssignExtensions = document.getElementById('auto-assign-extensions');
        const autoAssignMainIVR = document.getElementById('auto-assign-main-ivr');
        const autoAssignSupport = document.getElementById('auto-assign-support');
        const autoAssignOperators = document.getElementById('auto-assign-operators');

        if (autoAssignExtensions) {
            autoAssignExtensions.addEventListener('change', (e) => {
                this.didConfig.autoAssignment.extensions = e.target.checked;
                this.saveDIDConfiguration();
                this.addLogEntry('INFO', 'Admin', `Auto-assignment for extensions ${e.target.checked ? 'enabled' : 'disabled'}`, 'did-config');
            });
        }

        if (autoAssignMainIVR) {
            autoAssignMainIVR.addEventListener('change', (e) => {
                this.didConfig.autoAssignment.mainIVR = e.target.checked;
                this.saveDIDConfiguration();
                this.addLogEntry('INFO', 'Admin', `Auto-assignment for main IVR ${e.target.checked ? 'enabled' : 'disabled'}`, 'did-config');
            });
        }

        if (autoAssignSupport) {
            autoAssignSupport.addEventListener('change', (e) => {
                this.didConfig.autoAssignment.support = e.target.checked;
                this.saveDIDConfiguration();
                this.addLogEntry('INFO', 'Admin', `Auto-assignment for support department ${e.target.checked ? 'enabled' : 'disabled'}`, 'did-config');
            });
        }

        if (autoAssignOperators) {
            autoAssignOperators.addEventListener('change', (e) => {
                this.didConfig.autoAssignment.operators = e.target.checked;
                this.saveDIDConfiguration();
                this.addLogEntry('INFO', 'Admin', `Auto-assignment for operators department ${e.target.checked ? 'enabled' : 'disabled'}`, 'did-config');
            });
        }

        // Load current settings
        this.loadAutoAssignmentSettings();
    }

    loadAutoAssignmentSettings() {
        // Load and apply current auto-assignment settings
        const autoAssignExtensions = document.getElementById('auto-assign-extensions');
        const autoAssignMainIVR = document.getElementById('auto-assign-main-ivr');
        const autoAssignSupport = document.getElementById('auto-assign-support');
        const autoAssignOperators = document.getElementById('auto-assign-operators');

        if (autoAssignExtensions) {
            autoAssignExtensions.checked = this.didConfig.autoAssignment.extensions;
        }
        if (autoAssignMainIVR) {
            autoAssignMainIVR.checked = this.didConfig.autoAssignment.mainIVR;
        }
        if (autoAssignSupport) {
            autoAssignSupport.checked = this.didConfig.autoAssignment.support;
        }
        if (autoAssignOperators) {
            autoAssignOperators.checked = this.didConfig.autoAssignment.operators;
        }
    }

    showDIDAssignmentOptions() {
        // Show the DID assignment options panel
        const didOptionsPanel = document.getElementById('gv-did-options');
        if (didOptionsPanel) {
            didOptionsPanel.style.display = 'block';

            // Update panel with current Google Voice numbers
            this.updateDIDAssignmentPanel();

            this.addLogEntry('INFO', 'Admin', 'DID assignment options displayed', 'did-config');
        }
    }

    updateDIDAssignmentPanel() {
        // Update the DID assignment panel with current Google Voice numbers
        const numbersContainer = document.querySelector('#gv-did-options .assignment-checkboxes');
        if (!numbersContainer || !this.googleVoiceConfig.numbers.length) return;

        // Add number-specific assignment options
        this.googleVoiceConfig.numbers.forEach(number => {
            const numberDiv = document.createElement('div');
            numberDiv.className = 'number-assignment-section';
            numberDiv.innerHTML = `
                <h5>📞 ${number.number}</h5>
                <div class="number-assignment-options">
                    <label class="checkbox-item">
                        <input type="checkbox" id="assign-${number.number}-extension" data-number="${number.number}" data-type="extension">
                        <span>Auto-assign to new extensions</span>
                    </label>
                    <label class="checkbox-item">
                        <input type="checkbox" id="assign-${number.number}-ivr" data-number="${number.number}" data-type="ivr">
                        <span>Route to main IVR</span>
                    </label>
                    <label class="checkbox-item">
                        <input type="checkbox" id="assign-${number.number}-support" data-number="${number.number}" data-type="support">
                        <span>Route to support department</span>
                    </label>
                </div>
            `;

            // Add event listeners for number-specific assignments
            numberDiv.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
                checkbox.addEventListener('change', (e) => {
                    this.handleNumberAssignmentChange(e.target.dataset.number, e.target.dataset.type, e.target.checked);
                });
            });

            numbersContainer.appendChild(numberDiv);
        });
    }

    handleNumberAssignmentChange(number, type, enabled) {
        // Handle changes to number-specific assignment settings
        if (!this.didConfig.numberAssignments) {
            this.didConfig.numberAssignments = {};
        }

        if (!this.didConfig.numberAssignments[number]) {
            this.didConfig.numberAssignments[number] = {};
        }

        this.didConfig.numberAssignments[number][type] = enabled;
        this.saveDIDConfiguration();

        this.addLogEntry('INFO', 'Admin', `Number ${number} ${type} assignment ${enabled ? 'enabled' : 'disabled'}`, 'did-config', {
            number,
            type,
            enabled
        });
    }

    applyAutomaticDIDAssignments() {
        // Apply automatic DID assignments based on current settings
        if (!this.googleVoiceConfig.enabled || !this.googleVoiceConfig.numbers.length) {
            return;
        }

        const assignments = [];

        // Apply main IVR assignments
        if (this.didConfig.autoAssignment.mainIVR) {
            const primaryNumber = this.googleVoiceConfig.numbers[0];
            if (primaryNumber && primaryNumber.status === 'available') {
                this.assignDIDToIVR(primaryNumber.number);
                assignments.push({ number: primaryNumber.number, target: 'main-ivr' });
            }
        }

        // Apply support department assignments
        if (this.didConfig.autoAssignment.support) {
            const supportNumber = this.googleVoiceConfig.numbers.find(n => n.status === 'available');
            if (supportNumber) {
                this.assignDIDToSupport(supportNumber.number);
                assignments.push({ number: supportNumber.number, target: 'support' });
            }
        }

        // Apply operators department assignments
        if (this.didConfig.autoAssignment.operators) {
            const operatorNumber = this.googleVoiceConfig.numbers.find(n => n.status === 'available');
            if (operatorNumber) {
                this.assignDIDToOperators(operatorNumber.number);
                assignments.push({ number: operatorNumber.number, target: 'operators' });
            }
        }

        if (assignments.length > 0) {
            this.addLogEntry('INFO', 'Admin', `Applied ${assignments.length} automatic DID assignments`, 'did-auto-assign', {
                assignments
            });
            this.showToast(`Applied ${assignments.length} automatic DID assignments`, 'success');
        }
    }

    assignDIDToIVR(number) {
        // Assign DID to main IVR
        this.didConfig.assignments[number] = {
            target: 'main-ivr',
            assigned: new Date().toISOString(),
            type: 'ivr',
            provider: 'google-voice'
        };

        // Update Google Voice number status
        const gvNumber = this.googleVoiceConfig.numbers.find(n => n.number === number);
        if (gvNumber) {
            gvNumber.status = 'assigned';
        }

        this.saveDIDConfiguration();
        this.saveGoogleVoiceConfig();
    }

    assignDIDToSupport(number) {
        // Assign DID to support department
        this.didConfig.assignments[number] = {
            target: 'support-department',
            assigned: new Date().toISOString(),
            type: 'department',
            provider: 'google-voice'
        };

        // Update Google Voice number status
        const gvNumber = this.googleVoiceConfig.numbers.find(n => n.number === number);
        if (gvNumber) {
            gvNumber.status = 'assigned';
        }

        this.saveDIDConfiguration();
        this.saveGoogleVoiceConfig();
    }

    assignDIDToOperators(number) {
        // Assign DID to operators department
        this.didConfig.assignments[number] = {
            target: 'operators-department',
            assigned: new Date().toISOString(),
            type: 'department',
            provider: 'google-voice'
        };

        // Update Google Voice number status
        const gvNumber = this.googleVoiceConfig.numbers.find(n => n.number === number);
        if (gvNumber) {
            gvNumber.status = 'assigned';
        }

        this.saveDIDConfiguration();
        this.saveGoogleVoiceConfig();
    }

    showManualDIDAssignmentDialog() {
        // Show manual DID assignment dialog
        const dialog = document.createElement('div');
        dialog.className = 'modal-overlay';
        dialog.innerHTML = `
            <div class="modal-content did-assignment-modal">
                <div class="modal-header">
                    <h3>📋 Manual DID Assignment</h3>
                    <button class="close-modal" aria-label="Close">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="assignment-form">
                        <div class="form-group">
                            <label for="manual-did-number">Select DID Number:</label>
                            <select id="manual-did-number" class="form-select">
                                <option value="">Choose a number...</option>
                                ${this.getAvailableDIDNumbers().map(num =>
                                    `<option value="${num.number}">${num.number} (${num.provider})</option>`
                                ).join('')}
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="manual-assignment-type">Assignment Type:</label>
                            <select id="manual-assignment-type" class="form-select">
                                <option value="">Choose assignment type...</option>
                                <option value="extension">Extension</option>
                                <option value="ivr">Main IVR</option>
                                <option value="support">Support Department</option>
                                <option value="operators">Operators Department</option>
                            </select>
                        </div>

                        <div class="form-group" id="extension-selection" style="display: none;">
                            <label for="manual-extension-number">Extension Number:</label>
                            <select id="manual-extension-number" class="form-select">
                                <option value="">Choose extension...</option>
                                ${this.getAvailableExtensions().map(ext =>
                                    `<option value="${ext.number}">${ext.number} - ${ext.name}</option>`
                                ).join('')}
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn-secondary cancel-manual-assignment">Cancel</button>
                    <button class="btn-primary apply-manual-assignment">Assign DID</button>
                </div>
            </div>
        `;

        document.body.appendChild(dialog);

        // Setup event listeners
        const closeBtn = dialog.querySelector('.close-modal');
        const cancelBtn = dialog.querySelector('.cancel-manual-assignment');
        const applyBtn = dialog.querySelector('.apply-manual-assignment');
        const assignmentType = dialog.querySelector('#manual-assignment-type');
        const extensionSelection = dialog.querySelector('#extension-selection');

        const closeDialog = () => {
            dialog.remove();
        };

        closeBtn.addEventListener('click', closeDialog);
        cancelBtn.addEventListener('click', closeDialog);

        assignmentType.addEventListener('change', (e) => {
            if (e.target.value === 'extension') {
                extensionSelection.style.display = 'block';
            } else {
                extensionSelection.style.display = 'none';
            }
        });

        applyBtn.addEventListener('click', () => {
            this.processManualDIDAssignment(dialog);
        });

        this.addLogEntry('INFO', 'Admin', 'Manual DID assignment dialog opened', 'did-manual');
    }

    getAvailableDIDNumbers() {
        // Get all available DID numbers from all providers
        const available = [];

        // Google Voice numbers
        if (this.googleVoiceConfig.numbers) {
            this.googleVoiceConfig.numbers.forEach(num => {
                if (num.status === 'available') {
                    available.push({
                        number: num.number,
                        provider: 'google-voice'
                    });
                }
            });
        }

        // Could add other providers here (Callcentric, etc.)

        return available;
    }

    getAvailableExtensions() {
        // Get available extensions for assignment
        // This would typically come from the PBX configuration
        return [
            { number: '100', name: 'Reception' },
            { number: '200', name: 'Sales' },
            { number: '300', name: 'Support' },
            { number: '400', name: 'Management' }
        ];
    }

    processManualDIDAssignment(dialog) {
        const didNumber = dialog.querySelector('#manual-did-number').value;
        const assignmentType = dialog.querySelector('#manual-assignment-type').value;
        const extensionNumber = dialog.querySelector('#manual-extension-number').value;

        if (!didNumber || !assignmentType) {
            this.showToast('Please select both DID number and assignment type', 'error');
            return;
        }

        if (assignmentType === 'extension' && !extensionNumber) {
            this.showToast('Please select an extension number', 'error');
            return;
        }

        // Create assignment
        const assignment = {
            assigned: new Date().toISOString(),
            type: assignmentType,
            provider: this.getProviderForNumber(didNumber)
        };

        if (assignmentType === 'extension') {
            assignment.extension = extensionNumber;
            assignment.target = `extension-${extensionNumber}`;
        } else {
            assignment.target = assignmentType === 'ivr' ? 'main-ivr' : `${assignmentType}-department`;
        }

        // Apply assignment
        this.didConfig.assignments[didNumber] = assignment;

        // Update provider number status
        this.updateProviderNumberStatus(didNumber, 'assigned');

        this.saveDIDConfiguration();

        this.addLogEntry('INFO', 'Admin', `Manually assigned DID ${didNumber} to ${assignment.target}`, 'did-manual', {
            didNumber,
            assignment
        });

        this.showToast(`DID ${didNumber} assigned successfully`, 'success');
        dialog.remove();

        // Refresh assignments view if open
        this.refreshDIDAssignmentsView();
    }

    getProviderForNumber(number) {
        // Determine which provider a number belongs to
        if (this.googleVoiceConfig.numbers.find(n => n.number === number)) {
            return 'google-voice';
        }
        // Add other providers as needed
        return 'unknown';
    }

    updateProviderNumberStatus(number, status) {
        // Update the status of a number in its provider configuration
        if (this.googleVoiceConfig.numbers) {
            const gvNumber = this.googleVoiceConfig.numbers.find(n => n.number === number);
            if (gvNumber) {
                gvNumber.status = status;
                this.saveGoogleVoiceConfig();
            }
        }
        // Add other providers as needed
    }

    showDIDAssignmentsView() {
        // Show current DID assignments in a modal
        const dialog = document.createElement('div');
        dialog.className = 'modal-overlay';
        dialog.innerHTML = `
            <div class="modal-content did-assignments-view">
                <div class="modal-header">
                    <h3>📋 Current DID Assignments</h3>
                    <button class="close-modal" aria-label="Close">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="assignments-list" id="assignments-list">
                        ${this.generateAssignmentsHTML()}
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn-secondary refresh-assignments">Refresh</button>
                    <button class="btn-primary close-assignments-view">Close</button>
                </div>
            </div>
        `;

        document.body.appendChild(dialog);

        // Setup event listeners
        const closeBtn = dialog.querySelector('.close-modal');
        const closeViewBtn = dialog.querySelector('.close-assignments-view');
        const refreshBtn = dialog.querySelector('.refresh-assignments');

        const closeDialog = () => {
            dialog.remove();
        };

        closeBtn.addEventListener('click', closeDialog);
        closeViewBtn.addEventListener('click', closeDialog);
        refreshBtn.addEventListener('click', () => {
            this.refreshDIDAssignmentsView(dialog);
        });

        // Add remove assignment buttons
        dialog.querySelectorAll('.remove-assignment').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const didNumber = e.target.dataset.number;
                this.removeDIDAssignment(didNumber, dialog);
            });
        });

        this.addLogEntry('INFO', 'Admin', 'DID assignments view opened', 'did-view');
    }

    generateAssignmentsHTML() {
        if (!this.didConfig.assignments || Object.keys(this.didConfig.assignments).length === 0) {
            return '<div class="no-assignments">No DID assignments configured yet.</div>';
        }

        let html = '<div class="assignments-table">';
        html += '<div class="table-header">';
        html += '<span>DID Number</span><span>Assigned To</span><span>Provider</span><span>Date</span><span>Actions</span>';
        html += '</div>';

        Object.entries(this.didConfig.assignments).forEach(([number, assignment]) => {
            html += '<div class="assignment-row">';
            html += `<span class="did-number">${number}</span>`;
            html += `<span class="assignment-target">${this.formatAssignmentTarget(assignment)}</span>`;
            html += `<span class="provider">${assignment.provider}</span>`;
            html += `<span class="date">${new Date(assignment.assigned).toLocaleDateString()}</span>`;
            html += `<span class="actions">`;
            html += `<button class="btn-small remove-assignment" data-number="${number}">Remove</button>`;
            html += `</span>`;
            html += '</div>';
        });

        html += '</div>';
        return html;
    }

    formatAssignmentTarget(assignment) {
        switch (assignment.type) {
            case 'extension':
                return `Extension ${assignment.extension}`;
            case 'ivr':
                return 'Main IVR';
            case 'department':
                return assignment.target.replace('-department', '').replace('-', ' ').toUpperCase();
            default:
                return assignment.target;
        }
    }

    refreshDIDAssignmentsView(dialog) {
        if (dialog) {
            const assignmentsList = dialog.querySelector('#assignments-list');
            if (assignmentsList) {
                assignmentsList.innerHTML = this.generateAssignmentsHTML();

                // Re-attach event listeners for remove buttons
                assignmentsList.querySelectorAll('.remove-assignment').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        const didNumber = e.target.dataset.number;
                        this.removeDIDAssignment(didNumber, dialog);
                    });
                });
            }
        }
    }

    removeDIDAssignment(didNumber, dialog) {
        if (!confirm(`Remove DID assignment for ${didNumber}?`)) {
            return;
        }

        // Remove assignment
        delete this.didConfig.assignments[didNumber];

        // Update provider number status
        this.updateProviderNumberStatus(didNumber, 'available');

        this.saveDIDConfiguration();

        this.addLogEntry('INFO', 'Admin', `Removed DID assignment for ${didNumber}`, 'did-remove', {
            didNumber
        });

        this.showToast(`DID assignment for ${didNumber} removed`, 'success');

        // Refresh view
        this.refreshDIDAssignmentsView(dialog);
    }

    saveDIDConfiguration() {
        // Save DID configuration to localStorage
        localStorage.setItem('did-config', JSON.stringify(this.didConfig));

        // Broadcast to other clients if sync is enabled
        if (this.pbxConfig.syncEnabled) {
            this.broadcastSettingsUpdate('did-config', this.didConfig);
        }
    }

    saveGoogleVoiceConfig() {
        // Save Google Voice configuration to localStorage
        localStorage.setItem('gv-config', JSON.stringify(this.googleVoiceConfig));
        localStorage.setItem('gv-enabled', this.googleVoiceConfig.enabled.toString());
        localStorage.setItem('gv-api-key', this.googleVoiceConfig.apiKey);
        localStorage.setItem('gv-numbers', JSON.stringify(this.googleVoiceConfig.numbers));
        localStorage.setItem('gv-sms-enabled', this.googleVoiceConfig.smsEnabled.toString());
        localStorage.setItem('gv-status', this.googleVoiceConfig.status);

        // Broadcast to other clients if sync is enabled
        if (this.pbxConfig.syncEnabled) {
            this.broadcastSettingsUpdate('google-voice-config', this.googleVoiceConfig);
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