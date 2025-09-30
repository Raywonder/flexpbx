/**
 * FlexPBX Server Setup Wizard - Client Side
 */

class ServerSetupWizard {
    constructor() {
        this.currentStep = 1;
        this.maxStep = 9;
        this.config = {};
        this.connectionTested = false;
        this.deploymentSocket = null;

        this.initializeEventListeners();
        this.initializeAccessibility();
    }

    initializeEventListeners() {
        // Connection type selection
        document.querySelectorAll('.connection-type').forEach(type => {
            type.addEventListener('click', () => {
                this.selectConnectionType(type.dataset.type);
            });
        });

        // Authentication method change
        document.getElementById('auth-method').addEventListener('change', (e) => {
            this.toggleAuthMethod(e.target.value);
        });

        // Database type change
        document.getElementById('database-type').addEventListener('change', (e) => {
            this.toggleDatabaseConfig();
        });

        // SSL provider change
        document.getElementById('ssl-provider').addEventListener('change', (e) => {
            this.toggleSSLConfig(e.target.value);
        });

        // Form validation
        document.querySelectorAll('input, select').forEach(input => {
            input.addEventListener('change', () => {
                this.validateCurrentStep();
            });
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if (e.altKey) {
                switch(e.key) {
                    case 'n':
                        e.preventDefault();
                        this.nextStep();
                        break;
                    case 'p':
                        e.preventDefault();
                        this.prevStep();
                        break;
                    case 't':
                        e.preventDefault();
                        if (this.currentStep === 1) this.testConnection();
                        break;
                }
            }
        });
    }

    initializeAccessibility() {
        // Add ARIA labels and descriptions
        this.updateProgressAria();

        // Voice announcements for screen readers
        this.announceStep();
    }

    selectConnectionType(type) {
        // Update radio button
        document.querySelector(`input[value="${type}"]`).checked = true;

        // Update visual selection
        document.querySelectorAll('.connection-type').forEach(t => {
            t.classList.remove('selected');
        });
        document.querySelector(`[data-type="${type}"]`).classList.add('selected');

        // Show appropriate configuration
        document.querySelectorAll('.connection-config').forEach(config => {
            config.style.display = 'none';
        });
        document.getElementById(`${type}-config`).style.display = 'block';

        // Reset connection test status
        this.connectionTested = false;
        document.getElementById('next-step-1').disabled = true;

        this.announceToScreenReader(`Selected ${type.toUpperCase()} connection method`);
    }

    toggleAuthMethod(method) {
        const passwordAuth = document.getElementById('password-auth');
        const keyAuth = document.getElementById('key-auth');

        if (method === 'password') {
            passwordAuth.style.display = 'block';
            keyAuth.style.display = 'none';
        } else {
            passwordAuth.style.display = 'none';
            keyAuth.style.display = 'block';
        }

        this.connectionTested = false;
        this.announceToScreenReader(`Switched to ${method} authentication`);
    }

    toggleDatabaseConfig() {
        const dbType = document.getElementById('database-type').value;
        const externalConfig = document.getElementById('external-db-config');

        if (dbType === 'sqlite') {
            externalConfig.style.display = 'none';
        } else {
            externalConfig.style.display = 'block';

            // Set default ports
            const portField = document.getElementById('db-port');
            if (dbType === 'mysql') {
                portField.value = '3306';
            } else if (dbType === 'postgres') {
                portField.value = '5432';
            }
        }

        this.announceToScreenReader(`Selected ${dbType} database`);
    }

    toggleSSLConfig(provider) {
        const emailGroup = document.getElementById('letsencrypt-email-group');

        if (provider === 'letsencrypt') {
            emailGroup.style.display = 'block';
        } else {
            emailGroup.style.display = 'none';
        }

        this.announceToScreenReader(`Selected ${provider} SSL provider`);
    }

    async testConnection() {
        const connectionType = document.querySelector('input[name="connectionType"]:checked').value;
        const statusDiv = document.getElementById('connection-status');

        statusDiv.innerHTML = '<div class="loading">Testing connection...</div>';
        this.announceToScreenReader('Testing server connection');

        try {
            const connectionData = this.gatherConnectionData(connectionType);

            // Call backend API to test connection
            const response = await fetch('/api/v1/setup/test-connection', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(connectionData)
            });

            const result = await response.json();

            if (result.success) {
                statusDiv.innerHTML = '<div class="success-message">✅ Connection successful!</div>';
                this.connectionTested = true;
                document.getElementById('next-step-1').disabled = false;
                this.announceToScreenReader('Connection test successful');
            } else {
                statusDiv.innerHTML = `<div class="error-message">❌ Connection failed: ${result.error}</div>`;
                this.connectionTested = false;
                this.announceToScreenReader(`Connection test failed: ${result.error}`);
            }
        } catch (error) {
            statusDiv.innerHTML = `<div class="error-message">❌ Connection test failed: ${error.message}</div>`;
            this.connectionTested = false;
            this.announceToScreenReader(`Connection test failed: ${error.message}`);
        }
    }

    gatherConnectionData(type) {
        const data = { type };

        switch(type) {
            case 'ssh':
                data.host = document.getElementById('ssh-host').value;
                data.port = parseInt(document.getElementById('ssh-port').value);
                data.username = document.getElementById('ssh-username').value;
                data.authMethod = document.getElementById('auth-method').value;

                if (data.authMethod === 'password') {
                    data.password = document.getElementById('ssh-password').value;
                    data.sudoPassword = document.getElementById('sudo-password').value;
                } else {
                    data.keyPath = document.getElementById('ssh-key').value;
                    data.keyPassphrase = document.getElementById('key-passphrase').value;
                }
                break;

            case 'ftp':
                data.host = document.getElementById('ftp-host').value;
                data.port = parseInt(document.getElementById('ftp-port').value);
                data.username = document.getElementById('ftp-username').value;
                data.password = document.getElementById('ftp-password').value;
                data.secure = document.getElementById('ftp-secure').checked;
                break;

            case 'webdav':
                data.url = document.getElementById('webdav-url').value;
                data.username = document.getElementById('webdav-username').value;
                data.password = document.getElementById('webdav-password').value;
                break;
        }

        return data;
    }

    validateCurrentStep() {
        let isValid = true;

        switch(this.currentStep) {
            case 1:
                isValid = this.connectionTested;
                break;
            case 2:
                isValid = document.getElementById('install-path').value.trim() !== '';
                break;
            case 3:
                const dbType = document.getElementById('database-type').value;
                if (dbType !== 'sqlite') {
                    isValid = document.getElementById('db-host').value.trim() !== '' &&
                             document.getElementById('db-username').value.trim() !== '' &&
                             document.getElementById('db-password').value.trim() !== '';
                }
                break;
        }

        return isValid;
    }

    nextStep() {
        if (this.currentStep < this.maxStep && this.validateCurrentStep()) {
            this.currentStep++;
            this.showStep(this.currentStep);

            if (this.currentStep === 6) {
                this.generateDeploymentSummary();
            }
        }
    }

    prevStep() {
        if (this.currentStep > 1) {
            this.currentStep--;
            this.showStep(this.currentStep);
        }
    }

    showStep(stepNumber) {
        // Hide all steps
        document.querySelectorAll('.wizard-step').forEach(step => {
            step.classList.remove('active');
        });

        // Show current step
        document.getElementById(`step${stepNumber}`).classList.add('active');

        // Update progress indicators
        document.querySelectorAll('.progress-step').forEach((step, index) => {
            step.classList.remove('active', 'completed');
            if (index + 1 === stepNumber) {
                step.classList.add('active');
            } else if (index + 1 < stepNumber) {
                step.classList.add('completed');
            }
        });

        this.updateProgressAria();
        this.announceStep();

        // Focus management
        const stepElement = document.getElementById(`step${stepNumber}`);
        const firstInput = stepElement.querySelector('input, select, button');
        if (firstInput) {
            firstInput.focus();
        }
    }

    generateDeploymentSummary() {
        const connectionType = document.querySelector('input[name="connectionType"]:checked').value;
        const summaryDiv = document.getElementById('deployment-summary');

        const connectionData = this.gatherConnectionData(connectionType);
        const deploymentConfig = {
            installPath: document.getElementById('install-path').value,
            deploymentType: document.getElementById('deployment-type').value,
            domainName: document.getElementById('domain-name').value,
            sslEnabled: document.getElementById('ssl-enabled').value,
            databaseType: document.getElementById('database-type').value
        };

        if (deploymentConfig.databaseType !== 'sqlite') {
            deploymentConfig.dbHost = document.getElementById('db-host').value;
            deploymentConfig.dbName = document.getElementById('db-name').value;
            deploymentConfig.dbUsername = document.getElementById('db-username').value;
        }

        this.config = {
            connection: connectionData,
            deployment: deploymentConfig
        };

        summaryDiv.innerHTML = `
            <div class="summary-section">
                <h3>Connection Details</h3>
                <p><strong>Type:</strong> ${connectionType.toUpperCase()}</p>
                <p><strong>Host:</strong> ${connectionData.host || connectionData.url}</p>
                <p><strong>Username:</strong> ${connectionData.username}</p>
            </div>

            <div class="summary-section">
                <h3>Deployment Configuration</h3>
                <p><strong>Installation Path:</strong> ${deploymentConfig.installPath}</p>
                <p><strong>Deployment Type:</strong> ${deploymentConfig.deploymentType}</p>
                <p><strong>Database:</strong> ${deploymentConfig.databaseType}</p>
                <p><strong>SSL:</strong> ${deploymentConfig.sslEnabled}</p>
                ${deploymentConfig.domainName ? `<p><strong>Domain:</strong> ${deploymentConfig.domainName}</p>` : ''}
            </div>

            <div class="warning-message">
                <p>⚠️ This will install FlexPBX on your server. Ensure you have proper backups before proceeding.</p>
            </div>
        `;
    }

    async startDeployment() {
        this.currentStep = 8;
        this.showStep(8);

        try {
            // Initialize WebSocket connection for real-time updates
            this.initializeDeploymentSocket();

            // Start deployment process
            const response = await fetch('/api/v1/setup/deploy', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(this.config)
            });

            const result = await response.json();

            if (!result.success) {
                throw new Error(result.error);
            }

            this.announceToScreenReader('Deployment started successfully');

        } catch (error) {
            this.showDeploymentError(error.message);
        }
    }

    initializeDeploymentSocket() {
        this.deploymentSocket = io('/deployment');

        this.deploymentSocket.on('step-start', (data) => {
            this.updateDeploymentStep(data.step, 'running');
            this.logMessage(`Starting: ${data.description}`);
        });

        this.deploymentSocket.on('step-complete', (data) => {
            this.updateDeploymentStep(data.step, 'completed');
            this.logMessage(`✅ Completed: ${data.description}`);
        });

        this.deploymentSocket.on('step-error', (data) => {
            this.updateDeploymentStep(data.step, 'failed');
            this.logMessage(`❌ Failed: ${data.description} - ${data.error}`);
        });

        this.deploymentSocket.on('log', (data) => {
            this.logMessage(data.message);
        });

        this.deploymentSocket.on('deployment-complete', (data) => {
            this.completeDeployment(data);
        });

        this.deploymentSocket.on('deployment-error', (data) => {
            this.showDeploymentError(data.error);
        });
    }

    updateDeploymentStep(stepId, status) {
        const stepElement = document.getElementById(`status-${stepId}`);
        if (stepElement) {
            stepElement.className = `status-indicator status-${status}`;
        }
    }

    logMessage(message) {
        const logOutput = document.getElementById('deployment-log');
        const timestamp = new Date().toLocaleTimeString();
        logOutput.textContent += `[${timestamp}] ${message}\n`;
        logOutput.scrollTop = logOutput.scrollHeight;

        // Announce important messages to screen readers
        if (message.includes('✅') || message.includes('❌')) {
            this.announceToScreenReader(message);
        }
    }

    completeDeployment(data) {
        this.currentStep = 9;
        this.showStep(9);

        const connectionInfo = document.getElementById('connection-info');
        connectionInfo.innerHTML = `
            <div class="success-section">
                <h3>🎉 Your FlexPBX Server is Ready!</h3>

                <div class="connection-details">
                    <h4>Access Information</h4>
                    <p><strong>Web Interface:</strong> <a href="${data.serverUrl}" target="_blank">${data.serverUrl}</a></p>
                    <p><strong>Admin Extension:</strong> ${data.adminExtension}</p>
                    <p><strong>Admin Password:</strong> <code>${data.adminPassword}</code></p>
                    <p><strong>Admin PIN:</strong> ${data.adminPin}</p>
                </div>

                ${data.sslUrl ? `
                <div class="ssl-info">
                    <h4>Secure Access</h4>
                    <p><strong>HTTPS URL:</strong> <a href="${data.sslUrl}" target="_blank">${data.sslUrl}</a></p>
                </div>
                ` : ''}

                <div class="next-steps">
                    <h4>Next Steps</h4>
                    <ol>
                        <li>Access the web interface using the link above</li>
                        <li>Log in with the admin credentials</li>
                        <li>Change the default passwords</li>
                        <li>Configure your extensions and settings</li>
                        <li>Test calling functionality</li>
                    </ol>
                </div>
            </div>
        `;

        this.announceToScreenReader('FlexPBX deployment completed successfully!');

        // Store connection info for later use
        this.deploymentResult = data;
    }

    showDeploymentError(error) {
        const logOutput = document.getElementById('deployment-log');
        logOutput.textContent += `\n❌ DEPLOYMENT FAILED: ${error}\n`;
        logOutput.textContent += `\nPlease check the error above and try again.\n`;

        this.announceToScreenReader(`Deployment failed: ${error}`);
    }

    openServer() {
        if (this.deploymentResult && this.deploymentResult.serverUrl) {
            window.open(this.deploymentResult.serverUrl, '_blank');
        }
    }

    downloadConfig() {
        const configData = {
            connection: this.deploymentResult,
            timestamp: new Date().toISOString(),
            config: this.config
        };

        const blob = new Blob([JSON.stringify(configData, null, 2)], {
            type: 'application/json'
        });

        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'flexpbx-deployment-config.json';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);

        this.announceToScreenReader('Configuration file downloaded');
    }

    restartWizard() {
        this.currentStep = 1;
        this.config = {};
        this.connectionTested = false;
        this.deploymentResult = null;

        // Reset all forms
        document.querySelectorAll('input').forEach(input => {
            if (input.type === 'checkbox' || input.type === 'radio') {
                input.checked = false;
            } else {
                input.value = '';
            }
        });

        // Reset defaults
        document.getElementById('ssh').checked = true;
        document.getElementById('ssh-port').value = '22';
        document.getElementById('ftp-port').value = '21';
        document.getElementById('install-path').value = '/opt/flexpbx';
        document.getElementById('database-type').value = 'sqlite';

        this.selectConnectionType('ssh');
        this.showStep(1);

        this.announceToScreenReader('Setup wizard restarted');
    }

    updateProgressAria() {
        const progressBar = document.querySelector('.wizard-progress');
        progressBar.setAttribute('aria-valuenow', this.currentStep);
    }

    announceStep() {
        const stepTitles = {
            1: 'Step 1: Server Connection Configuration',
            2: 'Step 2: Deployment Configuration',
            3: 'Step 3: Database Configuration',
            4: 'Step 4: Deployment Summary',
            5: 'Step 5: Deployment in Progress',
            6: 'Step 6: Deployment Complete'
        };

        this.announceToScreenReader(stepTitles[this.currentStep]);
    }

    announceToScreenReader(message) {
        const ariaRegion = document.getElementById('aria-live-region');
        ariaRegion.textContent = message;

        // Clear after announcement
        setTimeout(() => {
            ariaRegion.textContent = '';
        }, 100);
    }
}

// Initialize wizard when page loads
document.addEventListener('DOMContentLoaded', () => {
    const wizard = new ServerSetupWizard();

    // Make wizard available globally for button onclick handlers
    window.wizard = wizard;
    window.nextStep = () => wizard.nextStep();
    window.prevStep = () => wizard.prevStep();
    window.testConnection = () => wizard.testConnection();
    window.toggleDatabaseConfig = () => wizard.toggleDatabaseConfig();
    window.startDeployment = () => wizard.startDeployment();
    window.openServer = () => wizard.openServer();
    window.downloadConfig = () => wizard.downloadConfig();
    window.restartWizard = () => wizard.restartWizard();
});