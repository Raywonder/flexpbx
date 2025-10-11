/**
 * FlexPBX Enhanced Button Handler
 * Based on Audio Portrait's robust button handling pattern
 */

class ButtonHandler {
    constructor() {
        this.buttons = new Map();
        this.isInitialized = false;
        this.debugMode = true;
    }

    init() {
        if (this.isInitialized) {
            console.warn('ButtonHandler already initialized');
            return;
        }

        console.log('Initializing Enhanced Button Handler...');

        // Wait for DOM to be ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.setupButtons());
        } else {
            this.setupButtons();
        }

        this.isInitialized = true;
    }

    setupButtons() {
        console.log('Setting up button event listeners...');

        // Method 1: Direct onclick attributes (like Audio Portrait)
        this.setupOnclickButtons();

        // Method 2: addEventListener with error handling
        this.setupEventListenerButtons();

        // Method 3: Delegated event handling for dynamic buttons
        this.setupDelegatedEvents();

        // Setup accessibility features
        this.setupAccessibility();

        console.log(`ButtonHandler: ${this.buttons.size} buttons registered`);
    }

    setupOnclickButtons() {
        // Dashboard buttons with onclick attributes
        const buttonConfigs = [
            {
                id: 'new-local-btn',
                action: () => this.handleNewLocal(),
                label: 'New Local Installation'
            },
            {
                id: 'deploy-remote-btn',
                action: () => this.handleDeployRemote(),
                label: 'Deploy to Remote Server'
            },
            {
                id: 'connect-existing-btn',
                action: () => this.handleConnectExisting(),
                label: 'Connect to Existing Server'
            },
            {
                id: 'import-backup-btn',
                action: () => this.handleImportBackup(),
                label: 'Import Backup'
            },
            {
                id: 'export-backup-btn',
                action: () => this.handleExportBackup(),
                label: 'Export Backup'
            },
            {
                id: 'test-connection',
                action: () => this.handleTestConnection(),
                label: 'Test Connection'
            },
            {
                id: 'browse-directory',
                action: () => this.handleBrowseDirectory(),
                label: 'Browse Directory'
            },
            {
                id: 'browse-ssh-key',
                action: () => this.handleBrowseSshKey(),
                label: 'Browse SSH Key'
            },
            {
                id: 'browse-default-path',
                action: () => this.handleBrowseDefaultPath(),
                label: 'Browse Default Path'
            }
        ];

        buttonConfigs.forEach(config => {
            const button = document.getElementById(config.id);
            if (button) {
                // Remove any existing listeners
                const newButton = button.cloneNode(true);
                button.parentNode.replaceChild(newButton, button);

                // Set onclick directly (Audio Portrait method)
                newButton.onclick = (e) => {
                    e.preventDefault();
                    e.stopPropagation();

                    if (this.debugMode) {
                        console.log(`Button clicked: ${config.id} - ${config.label}`);
                    }

                    try {
                        config.action();
                    } catch (error) {
                        console.error(`Error handling ${config.id}:`, error);
                        this.showErrorToast(`Failed to ${config.label}`);
                    }
                };

                // Also add addEventListener as backup
                newButton.addEventListener('click', newButton.onclick, { capture: true });

                // Store reference
                this.buttons.set(config.id, {
                    element: newButton,
                    action: config.action,
                    label: config.label
                });

                console.log(`✓ Button registered: ${config.id}`);
            } else {
                console.warn(`Button not found: ${config.id}`);
            }
        });
    }

    setupEventListenerButtons() {
        // Additional buttons that may be dynamically added
        const dynamicButtons = [
            { selector: '.action-btn', handler: 'handleActionButton' },
            { selector: '.browse-btn', handler: 'handleBrowseButton' },
            { selector: '.submit-btn', handler: 'handleSubmitButton' },
            { selector: '.cancel-btn', handler: 'handleCancelButton' }
        ];

        dynamicButtons.forEach(config => {
            document.querySelectorAll(config.selector).forEach(button => {
                if (!button.hasAttribute('data-handler-attached')) {
                    button.setAttribute('data-handler-attached', 'true');

                    button.addEventListener('click', (e) => {
                        e.preventDefault();

                        if (this.debugMode) {
                            console.log(`Dynamic button clicked: ${config.selector}`, button);
                        }

                        // Call the appropriate handler
                        if (this[config.handler]) {
                            this[config.handler](e, button);
                        }
                    }, { capture: false });
                }
            });
        });
    }

    setupDelegatedEvents() {
        // Set up event delegation for dynamically added buttons
        document.body.addEventListener('click', (e) => {
            // Check if clicked element or its parent is a button
            const button = e.target.closest('button');
            if (!button) return;

            // Handle specific button types
            if (button.classList.contains('media-import-btn')) {
                e.preventDefault();
                this.handleMediaImport(button);
            } else if (button.classList.contains('media-export-btn')) {
                e.preventDefault();
                this.handleMediaExport(button);
            } else if (button.classList.contains('transport-btn')) {
                e.preventDefault();
                this.handleTransportButton(button);
            }
        }, true); // Use capture phase
    }

    setupAccessibility() {
        // Add keyboard support for all buttons
        this.buttons.forEach((config, id) => {
            const button = config.element;
            if (button) {
                // Ensure button has proper ARIA attributes
                if (!button.hasAttribute('role')) {
                    button.setAttribute('role', 'button');
                }
                if (!button.hasAttribute('aria-label')) {
                    button.setAttribute('aria-label', config.label);
                }

                // Add keyboard support
                button.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        button.click();
                    }
                });
            }
        });
    }

    // Button action handlers
    handleNewLocal() {
        console.log('Handling new local installation');
        if (window.app && window.app.switchView) {
            window.app.switchView('local-install');
        } else {
            console.error('App not initialized or switchView not available');
            // Fallback: manually switch view
            this.manualSwitchView('local-install');
        }
    }

    handleDeployRemote() {
        console.log('Handling deploy to remote');
        if (window.app && window.app.switchView) {
            window.app.switchView('remote-deploy');
        } else {
            this.manualSwitchView('remote-deploy');
        }
    }

    handleConnectExisting() {
        console.log('Handling connect to existing');
        if (window.app && window.app.showConnectDialog) {
            window.app.showConnectDialog();
        } else {
            this.showToast('Connect dialog will open here', 'info');
        }
    }

    handleImportBackup() {
        console.log('Handling import backup');
        if (window.app && window.app.importBackup) {
            window.app.importBackup();
        } else {
            this.handleFileImport('backup');
        }
    }

    handleExportBackup() {
        console.log('Handling export backup');
        if (window.app && window.app.exportBackup) {
            window.app.exportBackup();
        } else {
            this.handleFileExport('backup');
        }
    }

    handleTestConnection() {
        console.log('Testing connection...');
        this.showToast('Testing connection...', 'info');
        // Implement connection test
    }

    handleBrowseDirectory() {
        console.log('Browse directory clicked');
        this.handleFileBrowse('directory');
    }

    handleBrowseSshKey() {
        console.log('Browse SSH key clicked');
        this.handleFileBrowse('ssh-key');
    }

    handleBrowseDefaultPath() {
        console.log('Browse default path clicked');
        this.handleFileBrowse('default-path');
    }

    // Helper methods
    handleFileBrowse(type) {
        console.log(`Opening file browser for: ${type}`);

        // Check if Electron API is available
        if (window.electronAPI && window.electronAPI.selectDirectory) {
            window.electronAPI.selectDirectory().then(path => {
                if (path) {
                    this.updatePathInput(type, path);
                }
            }).catch(err => {
                console.error('Browse failed:', err);
                this.enableManualInput(type);
            });
        } else {
            // Fallback: Enable manual input
            this.enableManualInput(type);
        }
    }

    updatePathInput(type, path) {
        let inputId;
        switch (type) {
            case 'directory':
                inputId = 'install-directory';
                break;
            case 'ssh-key':
                inputId = 'ssh-key-path';
                break;
            case 'default-path':
                inputId = 'default-install-path';
                break;
            default:
                return;
        }

        const input = document.getElementById(inputId);
        if (input) {
            input.value = path;
            input.dispatchEvent(new Event('change'));
        }

        // Also update fallback input if exists
        const fallbackInput = document.getElementById(`${inputId}-fallback`);
        if (fallbackInput) {
            fallbackInput.value = path;
        }
    }

    enableManualInput(type) {
        const inputs = {
            'directory': 'install-directory',
            'ssh-key': 'ssh-key-path',
            'default-path': 'default-install-path'
        };

        const inputId = inputs[type];
        if (inputId) {
            const input = document.getElementById(inputId);
            if (input) {
                input.removeAttribute('readonly');
                input.placeholder = 'Type or paste path here';
                input.focus();
                this.showToast('Browse not available. Please enter path manually.', 'info');
            }
        }
    }

    manualSwitchView(viewName) {
        console.log(`Manually switching to view: ${viewName}`);

        // Hide all views
        document.querySelectorAll('.view').forEach(view => {
            view.classList.remove('active');
        });

        // Show target view
        const targetView = document.getElementById(`${viewName}-view`);
        if (targetView) {
            targetView.classList.add('active');
            console.log(`Switched to view: ${viewName}`);
        } else {
            console.error(`View not found: ${viewName}-view`);
        }

        // Update nav
        document.querySelectorAll('.nav-item').forEach(nav => {
            nav.classList.remove('active');
        });

        const navItem = document.querySelector(`[data-view="${viewName}"]`);
        if (navItem) {
            navItem.classList.add('active');
        }
    }

    handleFileImport(type) {
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = type === 'backup' ? '.flx,.flxx,.tar,.tar.gz,.zip' : '*';

        input.onchange = (e) => {
            const file = e.target.files[0];
            if (file) {
                console.log(`Selected file for import: ${file.name}`);
                this.processImport(file, type);
            }
        };

        input.click();
    }

    handleFileExport(type) {
        console.log(`Exporting ${type}...`);
        // Implement export logic
        this.showToast(`Preparing ${type} export...`, 'info');
    }

    processImport(file, type) {
        console.log(`Processing import: ${file.name} (${type})`);
        this.showToast(`Importing ${file.name}...`, 'info');
        // Implement import processing
    }

    handleMediaImport(button) {
        const category = button.dataset.category;
        console.log(`Importing media for category: ${category}`);
        if (window.mediaManager) {
            window.mediaManager.handleImport(category);
        }
    }

    handleMediaExport(button) {
        const category = button.dataset.category;
        console.log(`Exporting media for category: ${category}`);
        if (window.mediaManager) {
            window.mediaManager.handleExport(category);
        }
    }

    handleTransportButton(button) {
        const action = button.dataset.action || button.id;
        console.log(`Transport action: ${action}`);
        // Handle play/pause/stop actions
    }

    showToast(message, type = 'info') {
        // Reuse the toast implementation from app or create one
        if (window.app && window.app.showToast) {
            window.app.showToast(message, type);
        } else {
            // Fallback toast
            console.log(`[${type.toUpperCase()}] ${message}`);

            let container = document.getElementById('toast-container');
            if (!container) {
                container = document.createElement('div');
                container.id = 'toast-container';
                container.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;';
                document.body.appendChild(container);
            }

            const toast = document.createElement('div');
            toast.style.cssText = `
                background: ${type === 'error' ? '#f44336' : type === 'success' ? '#4caf50' : '#2196f3'};
                color: white;
                padding: 12px 20px;
                border-radius: 4px;
                margin-bottom: 10px;
                box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            `;
            toast.textContent = message;
            container.appendChild(toast);

            setTimeout(() => toast.remove(), 5000);
        }
    }

    showErrorToast(message) {
        this.showToast(message, 'error');
    }

    // Debug helper
    listRegisteredButtons() {
        console.log('=== Registered Buttons ===');
        this.buttons.forEach((config, id) => {
            console.log(`- ${id}: ${config.label}`);
        });
    }
}

// Create and initialize button handler
const buttonHandler = new ButtonHandler();

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        buttonHandler.init();
        window.buttonHandler = buttonHandler;
    });
} else {
    buttonHandler.init();
    window.buttonHandler = buttonHandler;
}

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ButtonHandler;
}