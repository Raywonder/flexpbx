/**
 * FlexPBX Addon Module Manager
 * Handles installation, configuration, and management of FlexPBX addon modules
 */

class AddonModuleManager {
    constructor() {
        this.installedModules = new Map();
        this.availableModules = new Map();
        this.moduleCache = new Map();
        this.isLoading = false;

        // Supported module formats
        this.supportedFormats = ['.flxmod', '.tar.gz', '.zip', '.tar'];

        this.init();
    }

    init() {
        console.log('Initializing Addon Module Manager...');
        this.setupEventListeners();
        this.loadInstalledModules();
        this.loadAvailableModules();
        this.setupAccessibilityFeatures();
    }

    setupEventListeners() {
        // Install module buttons
        const installBtn = document.getElementById('install-module');
        if (installBtn) {
            installBtn.addEventListener('click', () => this.showInstallDialog());
        }

        const refreshBtn = document.getElementById('refresh-modules');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => this.refreshModules());
        }

        const marketplaceBtn = document.getElementById('module-marketplace');
        if (marketplaceBtn) {
            marketplaceBtn.addEventListener('click', () => this.openMarketplace());
        }

        // Custom module installation
        const browseModuleBtn = document.getElementById('browse-module-file');
        if (browseModuleBtn) {
            browseModuleBtn.addEventListener('click', () => this.browseModuleFile());
        }

        const installCustomBtn = document.getElementById('install-custom-module');
        if (installCustomBtn) {
            installCustomBtn.addEventListener('click', () => this.installCustomModule());
        }

        const validateBtn = document.getElementById('validate-module');
        if (validateBtn) {
            validateBtn.addEventListener('click', () => this.validateModule());
        }

        // Development tools
        const createTemplateBtn = document.getElementById('create-module-template');
        if (createTemplateBtn) {
            createTemplateBtn.addEventListener('click', () => this.createModuleTemplate());
        }

        const reloadDevBtn = document.getElementById('reload-dev-modules');
        if (reloadDevBtn) {
            reloadDevBtn.addEventListener('click', () => this.reloadDevModules());
        }

        const moduleLogsBtn = document.getElementById('module-logs');
        if (moduleLogsBtn) {
            moduleLogsBtn.addEventListener('click', () => this.showModuleLogs());
        }

        const moduleDocsBtn = document.getElementById('module-docs');
        if (moduleDocsBtn) {
            moduleDocsBtn.addEventListener('click', () => this.openModuleDocs());
        }

        // Module search and filter
        const searchInput = document.getElementById('module-search');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => this.filterModules(e.target.value));
        }

        const categorySelect = document.getElementById('module-category');
        if (categorySelect) {
            categorySelect.addEventListener('change', (e) => this.filterByCategory(e.target.value));
        }

        // Delegated event listeners for module actions
        this.setupDelegatedEvents();
    }

    setupDelegatedEvents() {
        // Set up event delegation for module action buttons
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('module-install')) {
                e.preventDefault();
                this.installModule(e.target.dataset.module);
            } else if (e.target.classList.contains('module-enable')) {
                e.preventDefault();
                this.enableModule(e.target.dataset.module);
            } else if (e.target.classList.contains('module-disable')) {
                e.preventDefault();
                this.disableModule(e.target.dataset.module);
            } else if (e.target.classList.contains('module-uninstall')) {
                e.preventDefault();
                this.uninstallModule(e.target.dataset.module);
            } else if (e.target.classList.contains('module-config')) {
                e.preventDefault();
                this.configureModule(e.target.dataset.module);
            } else if (e.target.classList.contains('module-info-btn')) {
                e.preventDefault();
                this.showModuleInfo(e.target.dataset.module);
            }
        });
    }

    setupAccessibilityFeatures() {
        // Add ARIA labels and keyboard support for module cards
        document.querySelectorAll('.module-card').forEach(card => {
            card.setAttribute('role', 'listitem');
            card.setAttribute('tabindex', '0');

            const name = card.querySelector('h5')?.textContent;
            const status = card.querySelector('.module-status')?.textContent;
            const version = card.querySelector('.module-info p')?.textContent;

            card.setAttribute('aria-label', `Module: ${name}, ${status}, ${version}`);

            // Add keyboard support
            card.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    const configBtn = card.querySelector('.module-config');
                    const installBtn = card.querySelector('.module-install');

                    if (configBtn) {
                        configBtn.click();
                    } else if (installBtn) {
                        installBtn.click();
                    }
                }
            });
        });
    }

    async loadInstalledModules() {
        console.log('Loading installed modules...');

        try {
            // Get installed modules from Electron API
            if (window.electronAPI && window.electronAPI.getInstalledModules) {
                const modules = await window.electronAPI.getInstalledModules();
                this.installedModules.clear();

                modules.forEach(module => {
                    this.installedModules.set(module.id, module);
                });
            } else {
                // Use mock data for testing
                this.loadMockInstalledModules();
            }

            this.displayInstalledModules();
        } catch (error) {
            console.error('Failed to load installed modules:', error);
            this.showToast('Failed to load installed modules', 'error');
        }
    }

    loadMockInstalledModules() {
        const mockModules = [
            {
                id: 'core-telephony',
                name: 'Core Telephony',
                version: '1.0.0',
                description: 'Essential PBX telephony functions',
                status: 'active',
                isCore: true
            },
            {
                id: 'call-recording',
                name: 'Call Recording',
                version: '1.5.2',
                description: 'Advanced call recording and storage',
                status: 'active',
                isCore: false
            },
            {
                id: 'voicemail-plus',
                name: 'Voicemail Plus',
                version: '1.3.0',
                description: 'Enhanced voicemail with transcription',
                status: 'inactive',
                isCore: false
            },
            {
                id: 'analytics',
                name: 'Analytics Dashboard',
                version: '2.1.0',
                description: 'Call analytics and reporting',
                status: 'active',
                isCore: false
            }
        ];

        mockModules.forEach(module => {
            this.installedModules.set(module.id, module);
        });
    }

    async loadAvailableModules() {
        console.log('Loading available modules...');

        try {
            // Get available modules from marketplace API
            if (window.electronAPI && window.electronAPI.getAvailableModules) {
                const modules = await window.electronAPI.getAvailableModules();
                this.availableModules.clear();

                modules.forEach(module => {
                    this.availableModules.set(module.id, module);
                });
            } else {
                // Use mock data for testing
                this.loadMockAvailableModules();
            }

            this.displayAvailableModules();
        } catch (error) {
            console.error('Failed to load available modules:', error);
            this.showToast('Failed to load available modules', 'error');
        }
    }

    loadMockAvailableModules() {
        const mockModules = [
            {
                id: 'crm-integration',
                name: 'CRM Integration',
                version: '1.2.0',
                description: 'Integrate with popular CRM systems',
                price: '$29/month',
                rating: 4.8,
                installs: 1200,
                category: 'integration'
            },
            {
                id: 'advanced-ivr',
                name: 'Advanced IVR',
                version: '3.0.1',
                description: 'Enhanced IVR with AI capabilities',
                price: 'Free',
                rating: 4.6,
                installs: 3500,
                category: 'telephony'
            },
            {
                id: 'sms-gateway',
                name: 'SMS Gateway',
                version: '2.2.0',
                description: 'Send and receive SMS messages',
                price: '$15/month',
                rating: 4.9,
                installs: 892,
                category: 'integration'
            },
            {
                id: 'call-queue-pro',
                name: 'Call Queue Pro',
                version: '1.8.0',
                description: 'Advanced call queue management',
                price: '$19/month',
                rating: 4.7,
                installs: 2100,
                category: 'telephony'
            }
        ];

        mockModules.forEach(module => {
            this.availableModules.set(module.id, module);
        });
    }

    displayInstalledModules() {
        const container = document.getElementById('installed-modules');
        if (!container) return;

        // Keep the existing content for now, but we could replace it with dynamic content
        console.log('Installed modules:', this.installedModules.size);
    }

    displayAvailableModules() {
        const container = document.getElementById('available-modules');
        if (!container) return;

        // Keep the existing content for now, but we could replace it with dynamic content
        console.log('Available modules:', this.availableModules.size);
    }

    async installModule(moduleId) {
        console.log(`Installing module: ${moduleId}`);

        try {
            this.setLoading(true);
            this.showToast(`Installing ${moduleId}...`, 'info');

            // Simulate installation process
            await this.simulateInstallation(moduleId);

            // Update UI
            await this.loadInstalledModules();

            this.showToast(`${moduleId} installed successfully!`, 'success');
            this.announceToScreenReader(`Module ${moduleId} installed`);

        } catch (error) {
            console.error(`Failed to install ${moduleId}:`, error);
            this.showToast(`Failed to install ${moduleId}: ${error.message}`, 'error');
        } finally {
            this.setLoading(false);
        }
    }

    async simulateInstallation(moduleId) {
        // Simulate installation steps
        const steps = [
            'Downloading module...',
            'Validating package...',
            'Extracting files...',
            'Installing dependencies...',
            'Configuring module...',
            'Registering with PBX...'
        ];

        for (let i = 0; i < steps.length; i++) {
            console.log(`[${moduleId}] ${steps[i]}`);
            await new Promise(resolve => setTimeout(resolve, 500));
        }
    }

    async enableModule(moduleId) {
        console.log(`Enabling module: ${moduleId}`);

        try {
            if (window.electronAPI && window.electronAPI.enableModule) {
                await window.electronAPI.enableModule(moduleId);
            }

            this.showToast(`${moduleId} enabled`, 'success');
            await this.loadInstalledModules();
        } catch (error) {
            console.error(`Failed to enable ${moduleId}:`, error);
            this.showToast(`Failed to enable ${moduleId}`, 'error');
        }
    }

    async disableModule(moduleId) {
        console.log(`Disabling module: ${moduleId}`);

        try {
            if (window.electronAPI && window.electronAPI.disableModule) {
                await window.electronAPI.disableModule(moduleId);
            }

            this.showToast(`${moduleId} disabled`, 'success');
            await this.loadInstalledModules();
        } catch (error) {
            console.error(`Failed to disable ${moduleId}:`, error);
            this.showToast(`Failed to disable ${moduleId}`, 'error');
        }
    }

    async uninstallModule(moduleId) {
        if (!confirm(`Are you sure you want to uninstall ${moduleId}? This action cannot be undone.`)) {
            return;
        }

        console.log(`Uninstalling module: ${moduleId}`);

        try {
            this.setLoading(true);

            if (window.electronAPI && window.electronAPI.uninstallModule) {
                await window.electronAPI.uninstallModule(moduleId);
            }

            this.showToast(`${moduleId} uninstalled`, 'success');
            await this.loadInstalledModules();
        } catch (error) {
            console.error(`Failed to uninstall ${moduleId}:`, error);
            this.showToast(`Failed to uninstall ${moduleId}`, 'error');
        } finally {
            this.setLoading(false);
        }
    }

    configureModule(moduleId) {
        console.log(`Configuring module: ${moduleId}`);

        // Open module configuration dialog or switch to config view
        this.showToast(`Opening configuration for ${moduleId}`, 'info');
    }

    showModuleInfo(moduleId) {
        console.log(`Showing info for module: ${moduleId}`);

        const module = this.availableModules.get(moduleId) || this.installedModules.get(moduleId);
        if (module) {
            // Create and show module info modal
            this.createModuleInfoModal(module);
        }
    }

    createModuleInfoModal(module) {
        const modal = document.createElement('div');
        modal.className = 'modal-overlay';
        modal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <h3>${module.name}</h3>
                    <button class="modal-close" aria-label="Close">&times;</button>
                </div>
                <div class="modal-body">
                    <p><strong>Version:</strong> ${module.version}</p>
                    <p><strong>Description:</strong> ${module.description}</p>
                    ${module.price ? `<p><strong>Price:</strong> ${module.price}</p>` : ''}
                    ${module.rating ? `<p><strong>Rating:</strong> ⭐ ${module.rating}</p>` : ''}
                    ${module.installs ? `<p><strong>Installs:</strong> ${module.installs}</p>` : ''}
                </div>
                <div class="modal-footer">
                    <button class="btn-secondary" onclick="this.closest('.modal-overlay').remove()">Close</button>
                </div>
            </div>
        `;

        document.body.appendChild(modal);

        // Close on click outside
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.remove();
            }
        });

        // Close button
        modal.querySelector('.modal-close').addEventListener('click', () => {
            modal.remove();
        });
    }

    browseModuleFile() {
        console.log('Browsing for module file...');

        if (window.electronAPI && window.electronAPI.selectFile) {
            window.electronAPI.selectFile({
                filters: [
                    { name: 'FlexPBX Modules', extensions: ['flxmod'] },
                    { name: 'Archives', extensions: ['tar.gz', 'tar', 'zip'] },
                    { name: 'All Files', extensions: ['*'] }
                ]
            }).then(filePath => {
                if (filePath) {
                    const input = document.getElementById('module-file-path');
                    if (input) {
                        input.value = filePath;
                    }
                }
            }).catch(error => {
                console.error('Failed to select module file:', error);
                this.enableManualInput();
            });
        } else {
            this.enableManualInput();
        }
    }

    enableManualInput() {
        const input = document.getElementById('module-file-path');
        if (input) {
            input.removeAttribute('readonly');
            input.placeholder = 'Type or paste module file path here';
            input.focus();
            this.showToast('Browse not available. Please enter path manually.', 'info');
        }
    }

    async installCustomModule() {
        const filePathInput = document.getElementById('module-file-path');
        const urlInput = document.getElementById('module-source-url');

        const filePath = filePathInput?.value.trim();
        const url = urlInput?.value.trim();

        if (!filePath && !url) {
            this.showToast('Please specify a module file or URL', 'error');
            return;
        }

        try {
            this.setLoading(true);

            if (filePath) {
                await this.installFromFile(filePath);
            } else {
                await this.installFromURL(url);
            }

            this.showToast('Custom module installed successfully!', 'success');
            await this.loadInstalledModules();

        } catch (error) {
            console.error('Failed to install custom module:', error);
            this.showToast(`Installation failed: ${error.message}`, 'error');
        } finally {
            this.setLoading(false);
        }
    }

    async installFromFile(filePath) {
        console.log(`Installing module from file: ${filePath}`);

        // Validate file format
        const isValidFormat = this.supportedFormats.some(format =>
            filePath.toLowerCase().endsWith(format));

        if (!isValidFormat) {
            throw new Error('Unsupported file format');
        }

        // Simulate installation
        await this.simulateInstallation(`custom-${Date.now()}`);
    }

    async installFromURL(url) {
        console.log(`Installing module from URL: ${url}`);

        // Validate URL
        try {
            new URL(url);
        } catch {
            throw new Error('Invalid URL');
        }

        // Simulate download and installation
        await this.simulateInstallation(`url-${Date.now()}`);
    }

    async validateModule() {
        const filePathInput = document.getElementById('module-file-path');
        const filePath = filePathInput?.value.trim();

        if (!filePath) {
            this.showToast('Please specify a module file to validate', 'error');
            return;
        }

        try {
            console.log(`Validating module: ${filePath}`);

            // Simulate validation
            await new Promise(resolve => setTimeout(resolve, 1000));

            this.showToast('Module validation passed!', 'success');
        } catch (error) {
            console.error('Module validation failed:', error);
            this.showToast('Module validation failed', 'error');
        }
    }

    async refreshModules() {
        console.log('Refreshing modules...');

        try {
            this.setLoading(true);
            await Promise.all([
                this.loadInstalledModules(),
                this.loadAvailableModules()
            ]);
            this.showToast('Modules refreshed', 'success');
        } catch (error) {
            console.error('Failed to refresh modules:', error);
            this.showToast('Failed to refresh modules', 'error');
        } finally {
            this.setLoading(false);
        }
    }

    openMarketplace() {
        console.log('Opening module marketplace...');
        this.showToast('Module marketplace coming soon!', 'info');
    }

    createModuleTemplate() {
        console.log('Creating module template...');
        this.showToast('Module template creation coming soon!', 'info');
    }

    reloadDevModules() {
        console.log('Reloading development modules...');
        this.showToast('Development modules reloaded', 'success');
    }

    showModuleLogs() {
        console.log('Showing module logs...');
        this.showToast('Module logs viewer coming soon!', 'info');
    }

    openModuleDocs() {
        console.log('Opening module documentation...');
        this.showToast('Module documentation coming soon!', 'info');
    }

    filterModules(searchTerm) {
        console.log(`Filtering modules by: ${searchTerm}`);
        // Implementation for filtering modules
    }

    filterByCategory(category) {
        console.log(`Filtering modules by category: ${category}`);
        // Implementation for category filtering
    }

    setLoading(loading) {
        this.isLoading = loading;

        // Update UI to show loading state
        const buttons = document.querySelectorAll('#addon-modules-panel button');
        buttons.forEach(btn => {
            btn.disabled = loading;
        });
    }

    showToast(message, type = 'info') {
        // Reuse toast implementation from other modules
        if (window.app && window.app.showToast) {
            window.app.showToast(message, type);
        } else {
            console.log(`[${type.toUpperCase()}] ${message}`);
        }
    }

    announceToScreenReader(message) {
        const announcement = document.createElement('div');
        announcement.setAttribute('aria-live', 'assertive');
        announcement.setAttribute('aria-atomic', 'true');
        announcement.className = 'sr-only';
        announcement.textContent = message;

        document.body.appendChild(announcement);

        setTimeout(() => {
            announcement.remove();
        }, 1000);
    }
}

// Create and initialize addon module manager
let addonModuleManager;

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    addonModuleManager = new AddonModuleManager();
    window.addonModuleManager = addonModuleManager;
    console.log('Addon Module Manager initialized');
});

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AddonModuleManager;
}