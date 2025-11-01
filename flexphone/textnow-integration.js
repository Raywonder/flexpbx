/**
 * TextNow Calling Integration for Flexphone WebUI
 * Provides SIP calling capabilities through TextNow trunk
 * Integrated with FlexPBX extension 2000 and authorized extensions
 */

class TextNowCalling {
    constructor(extension = '2000') {
        this.extension = extension;
        this.textnowNumber = '8326786610';
        this.enabled = false;
        this.registered = false;
        this.apiEndpoint = '/api/textnow-calling.php';
        this.apiKey = window.FLEXPBX_API_KEY || '';
        this.activeCall = null;
        this.callHistory = [];
    }

    /**
     * Initialize TextNow integration
     */
    async init() {
        console.log('[TextNow] Initializing for extension:', this.extension);

        try {
            const available = await this.checkAvailability();
            if (available) {
                this.addToFlexphoneUI();
                this.setupEventListeners();
                console.log('[TextNow] Integration enabled');
            } else {
                console.log('[TextNow] Not available for this extension');
            }
        } catch (error) {
            console.error('[TextNow] Initialization failed:', error);
        }

        return this.enabled;
    }

    /**
     * Check if TextNow is available for this user
     */
    async checkAvailability() {
        try {
            const response = await fetch(`${this.apiEndpoint}?action=check_sip_status`, {
                method: 'GET',
                headers: {
                    'X-API-Key': this.apiKey,
                    'X-Extension': this.extension,
                    'Content-Type': 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();
            console.log('[TextNow] Status check:', data);

            this.enabled = data.sip_enabled && data.has_permission;
            this.registered = data.sip_enabled;
            this.providerEnabled = data.provider_enabled;

            return this.enabled;
        } catch (error) {
            console.error('[TextNow] Availability check failed:', error);
            return false;
        }
    }

    /**
     * Make outbound call via TextNow
     */
    async makeCall(phoneNumber) {
        if (!this.enabled) {
            throw new Error('TextNow calling not enabled for this extension');
        }

        // Clean and validate number
        const cleanNumber = phoneNumber.replace(/[^0-9]/g, '');
        if (cleanNumber.length < 10) {
            throw new Error('Invalid phone number - must be at least 10 digits');
        }

        console.log('[TextNow] Initiating call to:', cleanNumber);

        try {
            const response = await fetch(`${this.apiEndpoint}?action=make_call`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-API-Key': this.apiKey,
                    'X-Extension': this.extension
                },
                body: JSON.stringify({
                    extension: this.extension,
                    destination: cleanNumber,
                    textnow_number: this.textnowNumber
                })
            });

            const result = await response.json();

            if (result.success) {
                console.log('[TextNow] Call initiated successfully:', result);
                this.activeCall = {
                    destination: cleanNumber,
                    startTime: Date.now(),
                    status: 'ringing'
                };
                this.updateCallStatus('Calling via TextNow...');
            } else {
                console.error('[TextNow] Call failed:', result);
                throw new Error(result.error || 'Call initiation failed');
            }

            return result;
        } catch (error) {
            console.error('[TextNow] Call error:', error);
            throw error;
        }
    }

    /**
     * Get call history
     */
    async getCallHistory(limit = 50, offset = 0) {
        try {
            const response = await fetch(
                `${this.apiEndpoint}?action=get_call_history&limit=${limit}&offset=${offset}`,
                {
                    method: 'GET',
                    headers: {
                        'X-API-Key': this.apiKey,
                        'X-Extension': this.extension
                    }
                }
            );

            const result = await response.json();

            if (result.success) {
                this.callHistory = result.calls;
                return result;
            }

            throw new Error('Failed to fetch call history');
        } catch (error) {
            console.error('[TextNow] Failed to get call history:', error);
            return { success: false, calls: [], error: error.message };
        }
    }

    /**
     * Check registration status
     */
    async checkRegistration() {
        try {
            const response = await fetch(
                `${this.apiEndpoint}?action=check_registration`,
                {
                    method: 'GET',
                    headers: {
                        'X-API-Key': this.apiKey
                    }
                }
            );

            const result = await response.json();
            this.registered = result.registered;

            return result;
        } catch (error) {
            console.error('[TextNow] Registration check failed:', error);
            return { registered: false, error: error.message };
        }
    }

    /**
     * Update UI to show TextNow calling option
     */
    addToFlexphoneUI() {
        // Check if dialpad exists
        const dialpad = document.querySelector('.flexphone-dialpad') ||
                       document.querySelector('.dial-pad') ||
                       document.querySelector('#dialpad');

        if (!dialpad) {
            console.warn('[TextNow] Dialpad not found in UI');
            return;
        }

        // Create TextNow call button
        const textnowButton = document.createElement('button');
        textnowButton.id = 'textnow-call-btn';
        textnowButton.className = 'btn btn-textnow';
        textnowButton.innerHTML = `
            <svg width="16" height="16" viewBox="0 0 16 16" style="margin-right: 6px; vertical-align: middle;">
                <circle cx="8" cy="8" r="6" fill="#00A8E8"/>
                <path d="M6 5 L10 8 L6 11 Z" fill="white"/>
            </svg>
            Call via TextNow (832) 678-6610
        `;
        textnowButton.style.cssText = `
            background: linear-gradient(135deg, #00A8E8 0%, #0077B5 100%);
            color: white;
            border: none;
            padding: 10px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            margin-top: 10px;
            width: 100%;
            transition: all 0.3s ease;
        `;

        textnowButton.onmouseover = () => {
            textnowButton.style.transform = 'translateY(-2px)';
            textnowButton.style.boxShadow = '0 4px 12px rgba(0, 168, 232, 0.3)';
        };

        textnowButton.onmouseout = () => {
            textnowButton.style.transform = 'translateY(0)';
            textnowButton.style.boxShadow = 'none';
        };

        textnowButton.onclick = () => this.handleTextNowCall();

        // Add button to dialpad
        dialpad.appendChild(textnowButton);

        // Add status indicator
        this.createStatusIndicator(dialpad);

        console.log('[TextNow] UI elements added to dialpad');
    }

    /**
     * Create status indicator
     */
    createStatusIndicator(container) {
        const statusDiv = document.createElement('div');
        statusDiv.id = 'textnow-status';
        statusDiv.style.cssText = `
            margin-top: 8px;
            padding: 8px;
            border-radius: 4px;
            font-size: 12px;
            text-align: center;
            background: #f0f0f0;
            color: #666;
        `;

        this.statusElement = statusDiv;
        container.appendChild(statusDiv);

        this.updateStatusIndicator();
    }

    /**
     * Update status indicator
     */
    updateStatusIndicator() {
        if (!this.statusElement) return;

        if (this.registered) {
            this.statusElement.innerHTML = `
                <span style="color: #22c55e;">●</span> TextNow Connected
            `;
            this.statusElement.style.background = '#f0fdf4';
        } else {
            this.statusElement.innerHTML = `
                <span style="color: #ef4444;">●</span> TextNow Not Configured
            `;
            this.statusElement.style.background = '#fef2f2';
        }
    }

    /**
     * Handle TextNow call button click
     */
    async handleTextNowCall() {
        // Get number from dial input
        const dialInput = document.querySelector('.dial-input') ||
                         document.querySelector('#phone-number') ||
                         document.querySelector('input[type="tel"]');

        if (!dialInput) {
            alert('Could not find phone number input field');
            return;
        }

        const number = dialInput.value.trim();
        if (!number) {
            alert('Please enter a phone number');
            return;
        }

        try {
            const result = await this.makeCall(number);

            if (result.success) {
                this.showNotification('Call initiated via TextNow', 'success');
                // Clear input after successful call
                dialInput.value = '';
            } else {
                this.showNotification('Call failed: ' + (result.error || 'Unknown error'), 'error');
            }
        } catch (error) {
            console.error('[TextNow] Call error:', error);
            this.showNotification('Failed to place call: ' + error.message, 'error');
        }
    }

    /**
     * Update call status display
     */
    updateCallStatus(message) {
        if (this.statusElement) {
            this.statusElement.innerHTML = `
                <span style="color: #3b82f6;">●</span> ${message}
            `;
            this.statusElement.style.background = '#eff6ff';
        }

        // Also log to console
        console.log('[TextNow] Status:', message);
    }

    /**
     * Show notification
     */
    showNotification(message, type = 'info') {
        // Try to use existing notification system
        if (typeof window.showNotification === 'function') {
            window.showNotification(message, type);
            return;
        }

        // Fallback to simple alert or console
        if (type === 'error') {
            alert(message);
        } else {
            console.log('[TextNow]', message);
        }

        // Create simple toast notification
        const toast = document.createElement('div');
        toast.textContent = message;
        toast.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${type === 'error' ? '#ef4444' : type === 'success' ? '#22c55e' : '#3b82f6'};
            color: white;
            padding: 12px 20px;
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 10000;
            animation: slideIn 0.3s ease;
        `;

        document.body.appendChild(toast);

        setTimeout(() => {
            toast.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    /**
     * Setup event listeners
     */
    setupEventListeners() {
        // Listen for Asterisk events if available
        if (window.AsteriskEvents) {
            window.AsteriskEvents.on('call-started', (event) => {
                if (event.extension === this.extension && event.trunk === 'textnow') {
                    this.updateCallStatus('Call connected');
                }
            });

            window.AsteriskEvents.on('call-ended', (event) => {
                if (event.extension === this.extension) {
                    this.activeCall = null;
                    this.updateStatusIndicator();
                }
            });
        }

        // Periodic registration check (every 60 seconds)
        setInterval(() => {
            this.checkRegistration().then(result => {
                this.updateStatusIndicator();
            });
        }, 60000);
    }
}

// Auto-initialize for authorized extensions
document.addEventListener('DOMContentLoaded', function() {
    // Get extension from global scope or data attribute
    const userExtension = window.USER_EXTENSION ||
                         document.body.dataset.extension ||
                         '2000';

    console.log('[TextNow] DOM loaded, user extension:', userExtension);

    // Initialize TextNow calling
    if (window.USER_EXTENSION === '2000' || window.USER_HAS_TEXTNOW_ACCESS) {
        const textnowCalling = new TextNowCalling(userExtension);
        textnowCalling.init();

        // Make globally available
        window.TextNowCalling = textnowCalling;
    }
});

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = TextNowCalling;
}
