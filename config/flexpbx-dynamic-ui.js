/**
 * 🎛️ FlexPBX Dynamic UI Manager
 * Provides real-time validation, auto-creation, and user-friendly configuration management
 */

class FlexPBXDynamicUI {
    constructor() {
        this.config = {
            apiUrl: '/api/v1',
            validationEndpoint: '/validate',
            autoCreateEndpoint: '/auto-create'
        };

        this.validators = new Map();
        this.autoCreateHandlers = new Map();
        this.uiElements = new Map();

        this.initializeValidators();
        this.setupAutoCreateHandlers();
        this.initializeUI();
    }

    initializeValidators() {
        // Callcentric trunk validation
        this.validators.set('callcentric', {
            username: (value) => {
                if (!value) return { valid: false, message: 'Username is required' };
                if (!/^\[?[A-Z_]*\]?\d+$/.test(value)) {
                    return { valid: false, message: 'Username should match Callcentric format (e.g., [YOUR_DID]102)' };
                }
                return { valid: true, message: 'Username format is valid' };
            },
            password: (value) => {
                if (!value) return { valid: false, message: 'Password is required' };
                if (value.length < 8) return { valid: false, message: 'Password must be at least 8 characters' };
                return { valid: true, message: 'Password meets requirements' };
            },
            proxy: (value) => {
                if (!value) return { valid: false, message: 'SIP proxy is required' };
                if (!value.includes('callcentric.com')) {
                    return { valid: false, message: 'Must use CallCentric proxy (sip.callcentric.com)' };
                }
                return { valid: true, message: 'Proxy server is valid' };
            },
            port: (value) => {
                const port = parseInt(value);
                if (!port || port < 1024 || port > 65535) {
                    return { valid: false, message: 'Port must be between 1024-65535' };
                }
                if (port !== 5060) {
                    return { valid: true, message: 'Non-standard port (5060 is recommended)', warning: true };
                }
                return { valid: true, message: 'Standard SIP port' };
            }
        });

        // Extension validation
        this.validators.set('extension', {
            number: (value) => {
                if (!value) return { valid: false, message: 'Extension number is required' };
                if (!/^\d{4}$/.test(value)) {
                    return { valid: false, message: 'Extension must be 4 digits (e.g., 2001)' };
                }
                const num = parseInt(value);
                if (num < 1000) {
                    return { valid: false, message: 'Extension must be 1000 or higher' };
                }
                return { valid: true, message: 'Extension number is valid' };
            },
            username: (value) => {
                if (!value) return { valid: false, message: 'Username is required' };
                if (!/^[a-z][a-z0-9_]{2,15}$/.test(value)) {
                    return { valid: false, message: 'Username: 3-16 chars, lowercase letters/numbers/underscore, start with letter' };
                }
                return { valid: true, message: 'Username format is valid' };
            },
            password: (value) => {
                if (!value) return { valid: false, message: 'Password is required' };
                const strongPassword = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*])[A-Za-z\d!@#$%^&*]{8,}$/;
                if (!strongPassword.test(value)) {
                    return {
                        valid: false,
                        message: 'Password must have 8+ chars, upper/lower case, number, and symbol (!@#$%^&*)'
                    };
                }
                return { valid: true, message: 'Password meets security requirements' };
            },
            displayName: (value) => {
                if (!value) return { valid: false, message: 'Display name is required' };
                if (value.length < 2 || value.length > 50) {
                    return { valid: false, message: 'Display name must be 2-50 characters' };
                }
                return { valid: true, message: 'Display name is valid' };
            }
        });

        // Phone number validation
        this.validators.set('phone', {
            googleVoice: (value) => {
                if (!value) return { valid: false, message: 'Phone number is required' };
                // Remove formatting
                const clean = value.replace(/\D/g, '');
                if (!/^1\d{10}$/.test(clean)) {
                    return { valid: false, message: 'Must be 11-digit US number (1NXXNXXXXXX)' };
                }
                return {
                    valid: true,
                    message: `Valid US number: ${this.formatPhone(clean)}`,
                    formatted: this.formatPhone(clean)
                };
            }
        });
    }

    setupAutoCreateHandlers() {
        // Auto-create extension handler
        this.autoCreateHandlers.set('extension', async (userData) => {
            const suggestions = {
                departments: {
                    'sales': { range: '1000-1099', next: await this.findNextExtension(1000, 1099) },
                    'support': { range: '2000-2099', next: await this.findNextExtension(2000, 2099) },
                    'admin': { range: '3000-3099', next: await this.findNextExtension(3000, 3099) },
                    'conference': { range: '8000-8099', next: await this.findNextExtension(8000, 8099) }
                }
            };

            return {
                suggestions,
                autoCreate: true,
                message: 'Select department for automatic extension assignment'
            };
        });

        // Auto-create trunk handler
        this.autoCreateHandlers.set('trunk', async (providerData) => {
            const templates = {
                'callcentric': {
                    proxy: 'sip.callcentric.com',
                    port: 5060,
                    transport: 'UDP',
                    codecs: ['g722', 'ulaw', 'alaw', 'g729'],
                    dtmfmode: 'rfc2833'
                },
                'voipms': {
                    proxy: 'toronto.voip.ms',
                    port: 5060,
                    transport: 'UDP',
                    codecs: ['g722', 'ulaw', 'alaw', 'g729', 'gsm']
                },
                'twilio': {
                    type: 'api',
                    endpoint: 'https://api.twilio.com/2010-04-01',
                    features: ['voice', 'sms', 'video']
                }
            };

            return {
                templates,
                autoCreate: true,
                message: 'Select provider template for automatic configuration'
            };
        });
    }

    initializeUI() {
        // Create validation UI components
        this.createValidationStyles();
        this.setupRealTimeValidation();
        this.createAutoCreateDialogs();
        this.setupSmartSuggestions();
    }

    createValidationStyles() {
        const styles = `
            <style id="flexpbx-validation-styles">
                .validation-container {
                    position: relative;
                    margin-bottom: 15px;
                }

                .validation-input {
                    width: 100%;
                    padding: 12px;
                    border: 2px solid #ddd;
                    border-radius: 6px;
                    font-size: 14px;
                    transition: all 0.3s ease;
                }

                .validation-input.valid {
                    border-color: #28a745;
                    background: #f8fff8;
                }

                .validation-input.invalid {
                    border-color: #dc3545;
                    background: #fff8f8;
                    animation: shake 0.3s ease-in-out;
                }

                .validation-input.warning {
                    border-color: #ffc107;
                    background: #fffef8;
                }

                .validation-message {
                    position: absolute;
                    top: 100%;
                    left: 0;
                    right: 0;
                    padding: 5px 8px;
                    font-size: 12px;
                    border-radius: 0 0 6px 6px;
                    z-index: 10;
                }

                .validation-message.valid {
                    background: #d4edda;
                    color: #155724;
                    border: 1px solid #c3e6cb;
                }

                .validation-message.invalid {
                    background: #f8d7da;
                    color: #721c24;
                    border: 1px solid #f5c6cb;
                }

                .validation-message.warning {
                    background: #fff3cd;
                    color: #856404;
                    border: 1px solid #ffeaa7;
                }

                @keyframes shake {
                    0%, 100% { transform: translateX(0); }
                    25% { transform: translateX(-5px); }
                    75% { transform: translateX(5px); }
                }

                .auto-create-btn {
                    background: #007bff;
                    color: white;
                    border: none;
                    padding: 8px 16px;
                    border-radius: 4px;
                    cursor: pointer;
                    font-size: 12px;
                    margin-left: 10px;
                }

                .auto-create-btn:hover {
                    background: #0056b3;
                }

                .suggestion-popup {
                    position: absolute;
                    top: 100%;
                    left: 0;
                    right: 0;
                    background: white;
                    border: 1px solid #ccc;
                    border-radius: 6px;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                    z-index: 1000;
                    max-height: 200px;
                    overflow-y: auto;
                }

                .suggestion-item {
                    padding: 10px;
                    cursor: pointer;
                    border-bottom: 1px solid #eee;
                }

                .suggestion-item:hover {
                    background: #f8f9fa;
                }

                .suggestion-item:last-child {
                    border-bottom: none;
                }

                .department-selector {
                    display: flex;
                    gap: 10px;
                    margin: 15px 0;
                }

                .department-btn {
                    padding: 10px 15px;
                    border: 2px solid #ddd;
                    background: white;
                    border-radius: 6px;
                    cursor: pointer;
                    transition: all 0.3s ease;
                }

                .department-btn:hover,
                .department-btn.selected {
                    border-color: #007bff;
                    background: #e3f2fd;
                    color: #0056b3;
                }

                .extension-preview {
                    background: #f8f9fa;
                    border: 1px solid #ddd;
                    border-radius: 6px;
                    padding: 15px;
                    margin: 10px 0;
                }

                .extension-preview h4 {
                    margin: 0 0 10px 0;
                    color: #333;
                }

                .extension-preview .detail {
                    margin: 5px 0;
                    font-size: 14px;
                }

                .extension-preview .detail strong {
                    color: #666;
                }
            </style>
        `;

        if (!document.getElementById('flexpbx-validation-styles')) {
            document.head.insertAdjacentHTML('beforeend', styles);
        }
    }

    setupRealTimeValidation() {
        // Add real-time validation to existing form inputs
        document.querySelectorAll('input[data-validate]').forEach(input => {
            const container = this.wrapInValidationContainer(input);

            input.addEventListener('input', (e) => {
                this.validateField(e.target);
            });

            input.addEventListener('blur', (e) => {
                this.validateField(e.target, true);
            });
        });
    }

    wrapInValidationContainer(input) {
        if (input.closest('.validation-container')) {
            return input.closest('.validation-container');
        }

        const container = document.createElement('div');
        container.className = 'validation-container';

        input.parentNode.insertBefore(container, input);
        container.appendChild(input);

        input.className += ' validation-input';

        // Add auto-create button if applicable
        if (input.dataset.autoCreate) {
            const autoBtn = document.createElement('button');
            autoBtn.className = 'auto-create-btn';
            autoBtn.textContent = `Auto-Create ${input.dataset.autoCreate}`;
            autoBtn.type = 'button';
            autoBtn.onclick = () => this.showAutoCreateDialog(input.dataset.autoCreate, input);
            container.appendChild(autoBtn);
        }

        return container;
    }

    async validateField(input, showSuccess = false) {
        const validationType = input.dataset.validate;
        const fieldType = input.dataset.field;

        if (!validationType || !fieldType) return;

        const validator = this.validators.get(validationType)?.[fieldType];
        if (!validator) return;

        const result = await this.runValidator(validator, input.value, input);
        this.displayValidationResult(input, result, showSuccess);

        return result;
    }

    async runValidator(validator, value, input) {
        try {
            // Check if it's an async validator
            const result = typeof validator === 'function' ? validator(value, input) : validator;
            return await Promise.resolve(result);
        } catch (error) {
            return { valid: false, message: `Validation error: ${error.message}` };
        }
    }

    displayValidationResult(input, result, showSuccess = false) {
        const container = input.closest('.validation-container');
        if (!container) return;

        // Remove existing validation message
        const existingMessage = container.querySelector('.validation-message');
        if (existingMessage) {
            existingMessage.remove();
        }

        // Update input styling
        input.classList.remove('valid', 'invalid', 'warning');

        if (!result.valid) {
            input.classList.add('invalid');
        } else if (result.warning) {
            input.classList.add('warning');
        } else if (showSuccess || !result.valid) {
            input.classList.add('valid');
        }

        // Show validation message
        if (!result.valid || result.warning || showSuccess) {
            const message = document.createElement('div');
            message.className = `validation-message ${result.valid ? (result.warning ? 'warning' : 'valid') : 'invalid'}`;
            message.textContent = result.message;

            container.appendChild(message);

            // Auto-hide success messages after 3 seconds
            if (result.valid && !result.warning) {
                setTimeout(() => {
                    if (message.parentNode) {
                        message.remove();
                        input.classList.remove('valid');
                    }
                }, 3000);
            }
        }

        // Update formatted value if provided
        if (result.formatted && result.formatted !== input.value) {
            input.value = result.formatted;
        }
    }

    async showAutoCreateDialog(type, triggerInput) {
        const handler = this.autoCreateHandlers.get(type);
        if (!handler) return;

        const suggestions = await handler(this.gatherUserData(triggerInput));

        if (type === 'extension') {
            this.showExtensionAutoCreateDialog(suggestions, triggerInput);
        } else if (type === 'trunk') {
            this.showTrunkAutoCreateDialog(suggestions, triggerInput);
        }
    }

    showExtensionAutoCreateDialog(suggestions, triggerInput) {
        const dialog = this.createDialog('Auto-Create Extension', `
            <p>Select a department to automatically assign the next available extension:</p>

            <div class="department-selector">
                ${Object.entries(suggestions.suggestions.departments).map(([dept, info]) => `
                    <button class="department-btn" data-dept="${dept}" data-ext="${info.next}">
                        <strong>${dept.toUpperCase()}</strong><br>
                        <small>${info.range}</small><br>
                        <em>Next: ${info.next}</em>
                    </button>
                `).join('')}
            </div>

            <div class="extension-preview" id="extension-preview" style="display: none;">
                <h4>Extension Preview</h4>
                <div class="detail"><strong>Extension:</strong> <span id="preview-ext"></span></div>
                <div class="detail"><strong>Department:</strong> <span id="preview-dept"></span></div>
                <div class="detail"><strong>Username:</strong> <span id="preview-username"></span></div>
                <div class="detail"><strong>Display Name:</strong> <input type="text" id="preview-displayname" placeholder="Enter display name"></div>
            </div>

            <div style="margin-top: 20px; text-align: right;">
                <button onclick="this.closest('.dialog-overlay').remove()" style="margin-right: 10px; padding: 8px 16px; border: 1px solid #ccc; background: white; border-radius: 4px; cursor: pointer;">Cancel</button>
                <button id="create-extension-btn" onclick="flexpbxUI.createExtension()" style="padding: 8px 16px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer;" disabled>Create Extension</button>
            </div>
        `);

        // Add department selection logic
        dialog.querySelectorAll('.department-btn').forEach(btn => {
            btn.onclick = () => {
                // Remove previous selection
                dialog.querySelectorAll('.department-btn').forEach(b => b.classList.remove('selected'));
                btn.classList.add('selected');

                // Show preview
                const dept = btn.dataset.dept;
                const ext = btn.dataset.ext;
                const username = dept.toLowerCase() + ext.slice(-2);

                document.getElementById('preview-ext').textContent = ext;
                document.getElementById('preview-dept').textContent = dept.toUpperCase();
                document.getElementById('preview-username').textContent = username;
                document.getElementById('extension-preview').style.display = 'block';
                document.getElementById('create-extension-btn').disabled = false;

                // Store data for creation
                dialog.dataset.selectedDept = dept;
                dialog.dataset.selectedExt = ext;
                dialog.dataset.selectedUsername = username;
            };
        });

        document.body.appendChild(dialog);
    }

    showTrunkAutoCreateDialog(suggestions, triggerInput) {
        const dialog = this.createDialog('Auto-Create Trunk', `
            <p>Select a provider template for automatic trunk configuration:</p>

            <div class="provider-selector">
                ${Object.entries(suggestions.templates).map(([provider, config]) => `
                    <div class="provider-option" data-provider="${provider}" style="border: 1px solid #ddd; margin: 10px 0; padding: 15px; border-radius: 6px; cursor: pointer;">
                        <h4>${provider.toUpperCase()}</h4>
                        <div><strong>Proxy:</strong> ${config.proxy || config.endpoint || 'API-based'}</div>
                        <div><strong>Port:</strong> ${config.port || 'N/A'}</div>
                        <div><strong>Transport:</strong> ${config.transport || config.type || 'N/A'}</div>
                        ${config.codecs ? `<div><strong>Codecs:</strong> ${config.codecs.join(', ')}</div>` : ''}
                    </div>
                `).join('')}
            </div>

            <div style="margin-top: 20px; text-align: right;">
                <button onclick="this.closest('.dialog-overlay').remove()" style="margin-right: 10px; padding: 8px 16px; border: 1px solid #ccc; background: white; border-radius: 4px; cursor: pointer;">Cancel</button>
                <button id="create-trunk-btn" onclick="flexpbxUI.createTrunk()" style="padding: 8px 16px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer;" disabled>Use Template</button>
            </div>
        `);

        // Add provider selection logic
        dialog.querySelectorAll('.provider-option').forEach(option => {
            option.onclick = () => {
                dialog.querySelectorAll('.provider-option').forEach(o => o.style.backgroundColor = '');
                option.style.backgroundColor = '#e3f2fd';

                dialog.dataset.selectedProvider = option.dataset.provider;
                document.getElementById('create-trunk-btn').disabled = false;
            };
        });

        document.body.appendChild(dialog);
    }

    createDialog(title, content) {
        const overlay = document.createElement('div');
        overlay.className = 'dialog-overlay';
        overlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2000;
        `;

        const dialog = document.createElement('div');
        dialog.className = 'dialog';
        dialog.style.cssText = `
            background: white;
            border-radius: 8px;
            padding: 20px;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        `;

        dialog.innerHTML = `
            <h3 style="margin: 0 0 15px 0;">${title}</h3>
            ${content}
        `;

        overlay.appendChild(dialog);
        return overlay;
    }

    async createExtension() {
        const dialog = document.querySelector('.dialog-overlay');
        const displayName = document.getElementById('preview-displayname').value;

        if (!displayName.trim()) {
            alert('Please enter a display name');
            return;
        }

        const extensionData = {
            number: dialog.dataset.selectedExt,
            username: dialog.dataset.selectedUsername,
            displayName: displayName.trim(),
            department: dialog.dataset.selectedDept,
            password: this.generateSecurePassword()
        };

        try {
            const response = await fetch(`${this.config.apiUrl}/extensions`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(extensionData)
            });

            if (response.ok) {
                const result = await response.json();
                alert(`Extension created successfully!\n\nExtension: ${result.number}\nUsername: ${result.username}\nPassword: ${result.password}\n\nPlease save these credentials.`);

                // Update the original form if it exists
                this.populateFormWithExtension(result);
                dialog.remove();
            } else {
                const error = await response.json();
                alert(`Error creating extension: ${error.message}`);
            }
        } catch (error) {
            alert(`Network error: ${error.message}`);
        }
    }

    async createTrunk() {
        const dialog = document.querySelector('.dialog-overlay');
        const provider = dialog.dataset.selectedProvider;

        // This would populate the trunk configuration form with the template
        alert(`Trunk template for ${provider.toUpperCase()} will be applied to the form.`);

        // In a real implementation, this would populate the trunk configuration form
        dialog.remove();
    }

    generateSecurePassword() {
        const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*';
        let password = '';

        // Ensure at least one of each required character type
        password += 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'[Math.floor(Math.random() * 26)]; // Uppercase
        password += 'abcdefghijklmnopqrstuvwxyz'[Math.floor(Math.random() * 26)]; // Lowercase
        password += '0123456789'[Math.floor(Math.random() * 10)]; // Number
        password += '!@#$%^&*'[Math.floor(Math.random() * 8)]; // Symbol

        // Fill the rest randomly
        for (let i = 4; i < 12; i++) {
            password += chars[Math.floor(Math.random() * chars.length)];
        }

        // Shuffle the password
        return password.split('').sort(() => 0.5 - Math.random()).join('');
    }

    async findNextExtension(start, end) {
        try {
            const response = await fetch(`${this.config.apiUrl}/extensions/next-available?start=${start}&end=${end}`);
            const result = await response.json();
            return result.next || start;
        } catch (error) {
            console.error('Error finding next extension:', error);
            return start;
        }
    }

    formatPhone(number) {
        const cleaned = number.replace(/\D/g, '');
        if (cleaned.length === 11) {
            return `+${cleaned[0]} (${cleaned.slice(1,4)}) ${cleaned.slice(4,7)}-${cleaned.slice(7)}`;
        }
        return number;
    }

    gatherUserData(triggerInput) {
        // Gather context data from the current form
        const form = triggerInput.closest('form') || document;
        return {
            formData: new FormData(form),
            triggerField: triggerInput.name || triggerInput.id
        };
    }

    populateFormWithExtension(extensionData) {
        // Populate form fields with the created extension data
        const fields = {
            'extension_number': extensionData.number,
            'extension_username': extensionData.username,
            'extension_display_name': extensionData.displayName,
            'extension_password': extensionData.password
        };

        Object.entries(fields).forEach(([fieldName, value]) => {
            const field = document.querySelector(`[name="${fieldName}"], #${fieldName}`);
            if (field) {
                field.value = value;
                this.validateField(field, true);
            }
        });
    }

    setupSmartSuggestions() {
        // Add smart suggestions for common fields
        this.addSmartSuggestions('input[data-suggestions="extensions"]', this.getExtensionSuggestions.bind(this));
        this.addSmartSuggestions('input[data-suggestions="departments"]', this.getDepartmentSuggestions.bind(this));
        this.addSmartSuggestions('input[data-suggestions="codecs"]', this.getCodecSuggestions.bind(this));
    }

    addSmartSuggestions(selector, suggestionProvider) {
        document.querySelectorAll(selector).forEach(input => {
            let suggestionPopup = null;

            input.addEventListener('input', async (e) => {
                const suggestions = await suggestionProvider(e.target.value);
                this.showSuggestions(input, suggestions);
            });

            input.addEventListener('blur', () => {
                setTimeout(() => this.hideSuggestions(input), 200);
            });
        });
    }

    showSuggestions(input, suggestions) {
        this.hideSuggestions(input);

        if (!suggestions || suggestions.length === 0) return;

        const container = input.closest('.validation-container') || input.parentNode;
        const popup = document.createElement('div');
        popup.className = 'suggestion-popup';
        popup.dataset.for = input.id || input.name;

        suggestions.forEach(suggestion => {
            const item = document.createElement('div');
            item.className = 'suggestion-item';
            item.innerHTML = `
                <div style="font-weight: bold;">${suggestion.label}</div>
                ${suggestion.description ? `<div style="font-size: 12px; color: #666;">${suggestion.description}</div>` : ''}
            `;
            item.onclick = () => {
                input.value = suggestion.value;
                input.dispatchEvent(new Event('input'));
                this.hideSuggestions(input);
            };
            popup.appendChild(item);
        });

        container.appendChild(popup);
    }

    hideSuggestions(input) {
        const container = input.closest('.validation-container') || input.parentNode;
        const popup = container.querySelector('.suggestion-popup');
        if (popup) popup.remove();
    }

    getExtensionSuggestions(query) {
        // This would typically fetch from the API
        const suggestions = [
            { label: '2001 - Senior Tech Support', value: '2001', description: 'Available' },
            { label: '2002 - Tech Support 2', value: '2002', description: 'Available' },
            { label: '1001 - Sales Rep 1', value: '1001', description: 'Available' }
        ];

        return suggestions.filter(s =>
            s.label.toLowerCase().includes(query.toLowerCase()) ||
            s.value.includes(query)
        );
    }

    getDepartmentSuggestions(query) {
        const departments = [
            { label: 'Sales', value: 'sales', description: 'Extensions 1000-1099' },
            { label: 'Support', value: 'support', description: 'Extensions 2000-2099' },
            { label: 'Administration', value: 'admin', description: 'Extensions 3000-3099' }
        ];

        return departments.filter(d =>
            d.label.toLowerCase().includes(query.toLowerCase())
        );
    }

    getCodecSuggestions(query) {
        const codecs = [
            { label: 'G.722', value: 'g722', description: 'HD Audio (recommended)' },
            { label: 'G.711u (PCMU)', value: 'ulaw', description: 'Standard quality' },
            { label: 'G.711a (PCMA)', value: 'alaw', description: 'Standard quality' },
            { label: 'G.729', value: 'g729', description: 'Low bandwidth' }
        ];

        return codecs.filter(c =>
            c.label.toLowerCase().includes(query.toLowerCase()) ||
            c.value.includes(query)
        );
    }
}

// Initialize the dynamic UI system
const flexpbxUI = new FlexPBXDynamicUI();

// Export for global access
window.flexpbxUI = flexpbxUI;