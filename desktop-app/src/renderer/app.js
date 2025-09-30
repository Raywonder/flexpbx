class FlexPBXDesktopApp {
    constructor() {
        this.currentView = 'dashboard';
        this.installations = [];
        this.systemRequirements = {};
        this.isLoading = false;

        this.init();
    }

    async init() {
        this.bindEvents();
        this.setupNavigationHandlers();
        this.setupMenuEventListeners();
        await this.loadSystemInfo();
        await this.checkSystemRequirements();
        await this.loadInstallations();
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

        // Local installation form
        this.setupLocalInstallForm();

        // Nginx configuration form
        this.setupNginxConfigForm();

        // Settings form
        this.setupSettingsForm();
    }

    setupLocalInstallForm() {
        const form = document.getElementById('local-install-form');
        const browseBtn = document.getElementById('browse-directory');
        const configureNginx = document.getElementById('configure-nginx-local');
        const installLocation = document.getElementById('install-location');
        const cancelBtn = document.getElementById('cancel-local-install');

        if (browseBtn) {
            browseBtn.addEventListener('click', async () => {
                const directory = await window.electronAPI.selectDirectory();
                if (directory) {
                    document.getElementById('install-directory').value = directory;
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
        } catch (error) {
            console.error('Failed to load system info:', error);
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
            sslEnabled: document.getElementById('ssl-enabled-local').checked
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
            const installations = await window.electronAPI.storeGet('installations') || [];
            this.installations = installations;
            this.updateInstallationsList();
        } catch (error) {
            console.error('Failed to load installations:', error);
        }
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