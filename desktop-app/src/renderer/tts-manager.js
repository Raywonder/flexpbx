/**
 * FlexPBX TTS Manager
 * Manages macOS Say text-to-speech for PBX event announcements
 */

class TTSManager {
    constructor() {
        this.isEnabled = false;
        this.voice = 'Alex';
        this.rate = 200; // words per minute
        this.currentProcess = null;
        this.eventListeners = new Map();
        this.messageTemplates = {
            incomingCall: 'Incoming call from {caller_id}',
            callAnswered: 'Call answered on extension {extension}',
            callEnded: 'Call ended. Duration {duration}',
            newVoicemail: 'New voicemail from {caller_id} for extension {extension}',
            registration: 'Extension {extension} registered',
            unregistration: 'Extension {extension} unregistered',
            systemEvent: 'System event: {message}'
        };

        this.init();
    }

    init() {
        console.log('Initializing TTS Manager...');
        this.setupEventListeners();
        this.loadSettings();
        this.setupPBXEventListeners();
    }

    setupEventListeners() {
        // Test TTS button
        const testButton = document.getElementById('test-tts-voice');
        if (testButton) {
            testButton.addEventListener('click', () => this.testVoice());
        }

        // Speak test message button
        const speakTestButton = document.getElementById('speak-test-message');
        if (speakTestButton) {
            speakTestButton.addEventListener('click', () => this.speakTestMessage());
        }

        // Stop speaking button
        const stopButton = document.getElementById('stop-speaking');
        if (stopButton) {
            stopButton.addEventListener('click', () => this.stopSpeaking());
        }

        // Voice selection
        const voiceSelect = document.getElementById('tts-voice');
        if (voiceSelect) {
            voiceSelect.addEventListener('change', (e) => {
                this.voice = e.target.value;
                this.saveSettings();
            });
        }

        // Speech rate slider
        const rateSlider = document.getElementById('tts-rate');
        const rateValue = document.getElementById('tts-rate-value');
        if (rateSlider) {
            rateSlider.addEventListener('input', (e) => {
                this.rate = parseInt(e.target.value);
                if (rateValue) {
                    rateValue.textContent = `${this.rate} WPM`;
                }
                this.saveSettings();
            });
        }

        // Enable/disable TTS
        const enableCheckbox = document.getElementById('tts-enabled');
        if (enableCheckbox) {
            enableCheckbox.addEventListener('change', (e) => {
                this.isEnabled = e.target.checked;
                this.saveSettings();
                if (this.isEnabled) {
                    this.speak('Text to speech announcements enabled');
                }
            });
        }

        // Event-specific checkboxes
        const eventCheckboxes = [
            'announce-incoming-calls',
            'announce-call-status',
            'announce-registrations',
            'announce-system-events',
            'announce-voicemail'
        ];

        eventCheckboxes.forEach(id => {
            const checkbox = document.getElementById(id);
            if (checkbox) {
                checkbox.addEventListener('change', () => this.saveSettings());
            }
        });

        // Template inputs
        const templateInputs = [
            'incoming-call-template',
            'call-answered-template',
            'call-ended-template',
            'voicemail-template'
        ];

        templateInputs.forEach(id => {
            const input = document.getElementById(id);
            if (input) {
                input.addEventListener('blur', () => {
                    const templateName = id.replace('-template', '').replace('-', '');
                    this.messageTemplates[templateName] = input.value;
                    this.saveSettings();
                });
            }
        });
    }

    setupPBXEventListeners() {
        // Listen for PBX events if available
        if (window.electronAPI && window.electronAPI.onPBXEvent) {
            window.electronAPI.onPBXEvent((event, data) => {
                this.handlePBXEvent(event, data);
            });
        }

        // Mock PBX events for testing
        this.simulatePBXEvents();
    }

    simulatePBXEvents() {
        // Simulate some PBX events for testing
        setTimeout(() => {
            if (this.isEnabled) {
                this.handlePBXEvent('extension_registered', { extension: '1001' });
            }
        }, 5000);

        setTimeout(() => {
            if (this.isEnabled) {
                this.handlePBXEvent('incoming_call', {
                    caller_id: '555-1234',
                    extension: '1001'
                });
            }
        }, 10000);
    }

    handlePBXEvent(event, data) {
        if (!this.isEnabled) return;

        console.log('PBX Event:', event, data);

        switch (event) {
            case 'incoming_call':
                if (this.shouldAnnounceEvent('announce-incoming-calls')) {
                    const message = this.formatMessage('incomingCall', data);
                    this.speak(message);
                }
                break;

            case 'call_answered':
                if (this.shouldAnnounceEvent('announce-call-status')) {
                    const message = this.formatMessage('callAnswered', data);
                    this.speak(message);
                }
                break;

            case 'call_ended':
                if (this.shouldAnnounceEvent('announce-call-status')) {
                    const message = this.formatMessage('callEnded', data);
                    this.speak(message);
                }
                break;

            case 'new_voicemail':
                if (this.shouldAnnounceEvent('announce-voicemail')) {
                    const message = this.formatMessage('newVoicemail', data);
                    this.speak(message);
                }
                break;

            case 'extension_registered':
            case 'extension_unregistered':
                if (this.shouldAnnounceEvent('announce-registrations')) {
                    const action = event.includes('unregistered') ? 'unregistered' : 'registered';
                    const message = `Extension ${data.extension} ${action}`;
                    this.speak(message);
                }
                break;

            case 'system_event':
                if (this.shouldAnnounceEvent('announce-system-events')) {
                    const message = this.formatMessage('systemEvent', data);
                    this.speak(message);
                }
                break;
        }
    }

    shouldAnnounceEvent(eventType) {
        const checkbox = document.getElementById(eventType);
        return checkbox ? checkbox.checked : false;
    }

    formatMessage(templateName, data) {
        let template = this.messageTemplates[templateName];
        if (!template) return 'Unknown event';

        // Replace placeholders with actual data
        Object.keys(data).forEach(key => {
            const placeholder = `{${key}}`;
            template = template.replace(new RegExp(placeholder, 'g'), data[key] || 'unknown');
        });

        return template;
    }

    async speak(text, options = {}) {
        if (!this.isEnabled && !options.force) {
            console.log('TTS disabled, not speaking:', text);
            return;
        }

        console.log('Speaking:', text);

        try {
            // Stop any current speech
            await this.stopSpeaking();

            // Use Electron API if available
            if (window.electronAPI && window.electronAPI.speak) {
                await window.electronAPI.speak(text, {
                    voice: this.voice,
                    rate: this.rate
                });
            } else {
                // Fallback: Use Web Speech API or log
                this.useFallbackTTS(text);
            }

            // Announce to screen readers too
            this.announceToScreenReader(text);

        } catch (error) {
            console.error('TTS Error:', error);
        }
    }

    useFallbackTTS(text) {
        // Try Web Speech API as fallback
        if ('speechSynthesis' in window) {
            const utterance = new SpeechSynthesisUtterance(text);
            utterance.voice = speechSynthesis.getVoices().find(v =>
                v.name.includes(this.voice)) || null;
            utterance.rate = this.rate / 200; // Normalize rate
            speechSynthesis.speak(utterance);
        } else {
            console.log('TTS Fallback:', text);
        }
    }

    async stopSpeaking() {
        try {
            if (window.electronAPI && window.electronAPI.stopSpeaking) {
                await window.electronAPI.stopSpeaking();
            } else if ('speechSynthesis' in window) {
                speechSynthesis.cancel();
            }
        } catch (error) {
            console.error('Stop TTS Error:', error);
        }
    }

    testVoice() {
        const testText = `This is a test of the ${this.voice} voice at ${this.rate} words per minute.`;
        this.speak(testText, { force: true });
    }

    speakTestMessage() {
        const messageInput = document.getElementById('test-message');
        if (messageInput && messageInput.value.trim()) {
            this.speak(messageInput.value.trim(), { force: true });
        } else {
            this.speak('No test message provided', { force: true });
        }
    }

    announceToScreenReader(text) {
        // Create an announcement for screen readers
        const announcement = document.createElement('div');
        announcement.setAttribute('aria-live', 'polite');
        announcement.setAttribute('aria-atomic', 'true');
        announcement.className = 'sr-only';
        announcement.textContent = `TTS: ${text}`;

        document.body.appendChild(announcement);

        setTimeout(() => {
            announcement.remove();
        }, 1000);
    }

    loadSettings() {
        try {
            const settings = JSON.parse(localStorage.getItem('flexpbx-tts-settings') || '{}');

            this.isEnabled = settings.enabled !== false; // Default to true
            this.voice = settings.voice || 'Alex';
            this.rate = settings.rate || 200;

            if (settings.templates) {
                Object.assign(this.messageTemplates, settings.templates);
            }

            // Update UI
            this.updateUI();

        } catch (error) {
            console.error('Failed to load TTS settings:', error);
        }
    }

    saveSettings() {
        try {
            const settings = {
                enabled: this.isEnabled,
                voice: this.voice,
                rate: this.rate,
                templates: this.messageTemplates,
                events: this.getEventSettings()
            };

            localStorage.setItem('flexpbx-tts-settings', JSON.stringify(settings));
            console.log('TTS settings saved');

        } catch (error) {
            console.error('Failed to save TTS settings:', error);
        }
    }

    getEventSettings() {
        const events = {};
        const checkboxes = [
            'announce-incoming-calls',
            'announce-call-status',
            'announce-registrations',
            'announce-system-events',
            'announce-voicemail'
        ];

        checkboxes.forEach(id => {
            const checkbox = document.getElementById(id);
            if (checkbox) {
                events[id] = checkbox.checked;
            }
        });

        return events;
    }

    updateUI() {
        // Update voice selection
        const voiceSelect = document.getElementById('tts-voice');
        if (voiceSelect) {
            voiceSelect.value = this.voice;
        }

        // Update rate slider
        const rateSlider = document.getElementById('tts-rate');
        const rateValue = document.getElementById('tts-rate-value');
        if (rateSlider) {
            rateSlider.value = this.rate;
        }
        if (rateValue) {
            rateValue.textContent = `${this.rate} WPM`;
        }

        // Update enabled checkbox
        const enableCheckbox = document.getElementById('tts-enabled');
        if (enableCheckbox) {
            enableCheckbox.checked = this.isEnabled;
        }

        // Update template inputs
        const templates = {
            'incoming-call-template': 'incomingCall',
            'call-answered-template': 'callAnswered',
            'call-ended-template': 'callEnded',
            'voicemail-template': 'newVoicemail'
        };

        Object.entries(templates).forEach(([inputId, templateKey]) => {
            const input = document.getElementById(inputId);
            if (input && this.messageTemplates[templateKey]) {
                input.value = this.messageTemplates[templateKey];
            }
        });
    }

    // Public API
    enable() {
        this.isEnabled = true;
        this.saveSettings();
        this.speak('Text to speech enabled');
    }

    disable() {
        this.isEnabled = false;
        this.saveSettings();
        this.stopSpeaking();
    }

    setVoice(voice) {
        this.voice = voice;
        this.saveSettings();
    }

    setRate(rate) {
        this.rate = Math.max(50, Math.min(400, rate));
        this.saveSettings();
    }

    announceSystemStatus(message) {
        if (this.shouldAnnounceEvent('announce-system-events')) {
            this.speak(`System status: ${message}`);
        }
    }
}

// Create and initialize TTS manager
let ttsManager;

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    ttsManager = new TTSManager();
    window.ttsManager = ttsManager;
    console.log('TTS Manager initialized');
});

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = TTSManager;
}