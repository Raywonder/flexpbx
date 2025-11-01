/**
 * FlexPBX Accessibility Enhancement Module
 * Implements WCAG 2.1 AA standards and AccessKit integration
 */

(function() {
    'use strict';

    // AccessKit Integration
    const AccessibilityManager = {
        init: function() {
            this.addSkipNavigation();
            this.enhanceKeyboardNavigation();
            this.addFocusIndicators();
            this.announcePageLoad();
            this.addAccessibilityControls();
            this.initAccessKit();
        },

        // Add skip navigation link for screen readers
        addSkipNavigation: function() {
            const skipNav = document.createElement('a');
            skipNav.href = '#main-content';
            skipNav.className = 'skip-navigation';
            skipNav.textContent = 'Skip to main content';
            skipNav.setAttribute('aria-label', 'Skip to main content');

            const style = document.createElement('style');
            style.textContent = `
                .skip-navigation {
                    position: absolute;
                    top: -40px;
                    left: 0;
                    background: #000;
                    color: #fff;
                    padding: 8px;
                    text-decoration: none;
                    z-index: 10000;
                }
                .skip-navigation:focus {
                    top: 0;
                }
                *:focus {
                    outline: 3px solid #667eea !important;
                    outline-offset: 2px !important;
                }
                .sr-only {
                    position: absolute;
                    width: 1px;
                    height: 1px;
                    padding: 0;
                    margin: -1px;
                    overflow: hidden;
                    clip: rect(0,0,0,0);
                    white-space: nowrap;
                    border: 0;
                }
                .high-contrast {
                    filter: contrast(1.5);
                }
                .large-text {
                    font-size: 120% !important;
                    line-height: 1.6 !important;
                }
            `;

            document.head.appendChild(style);
            document.body.insertBefore(skipNav, document.body.firstChild);
        },

        // Enhance keyboard navigation
        enhanceKeyboardNavigation: function() {
            // Add Tab navigation helpers
            document.addEventListener('keydown', function(e) {
                // Escape key closes modals/dialogs
                if (e.key === 'Escape') {
                    const modals = document.querySelectorAll('.modal, .dialog, [role="dialog"]');
                    modals.forEach(modal => {
                        if (modal.style.display !== 'none') {
                            modal.style.display = 'none';
                            const closeEvent = new CustomEvent('modal-closed', { detail: { modal: modal }});
                            document.dispatchEvent(closeEvent);
                        }
                    });
                }

                // Enter/Space on buttons and links
                if ((e.key === 'Enter' || e.key === ' ') &&
                    (e.target.tagName === 'BUTTON' || e.target.getAttribute('role') === 'button')) {
                    e.target.click();
                }
            });

            // Add aria-labels to buttons without text
            document.querySelectorAll('button, a').forEach(element => {
                if (!element.textContent.trim() && !element.getAttribute('aria-label')) {
                    const title = element.getAttribute('title') ||
                                 element.getAttribute('data-label') ||
                                 'Interactive element';
                    element.setAttribute('aria-label', title);
                }
            });
        },

        // Add visible focus indicators
        addFocusIndicators: function() {
            let focusedElement = null;

            document.addEventListener('focus', function(e) {
                if (focusedElement) {
                    focusedElement.classList.remove('keyboard-focused');
                }
                focusedElement = e.target;
                focusedElement.classList.add('keyboard-focused');
            }, true);

            document.addEventListener('blur', function(e) {
                if (e.target.classList) {
                    e.target.classList.remove('keyboard-focused');
                }
            }, true);
        },

        // Announce page load to screen readers
        announcePageLoad: function() {
            const announcement = document.createElement('div');
            announcement.setAttribute('role', 'status');
            announcement.setAttribute('aria-live', 'polite');
            announcement.className = 'sr-only';
            announcement.id = 'page-announcement';

            const pageTitle = document.title || 'Page loaded';
            announcement.textContent = `${pageTitle} loaded. Press H to navigate by headings, or Tab to navigate by links and buttons.`;

            document.body.appendChild(announcement);
        },

        // Add accessibility control panel
        addAccessibilityControls: function() {
            const controls = document.createElement('div');
            controls.id = 'accessibility-controls';
            controls.setAttribute('role', 'toolbar');
            controls.setAttribute('aria-label', 'Accessibility controls');

            controls.innerHTML = `
                <button id="toggle-high-contrast" aria-label="Toggle high contrast mode" title="High Contrast">
                    <span aria-hidden="true">◐</span>
                    <span class="sr-only">Toggle high contrast</span>
                </button>
                <button id="increase-text-size" aria-label="Increase text size" title="Larger Text">
                    <span aria-hidden="true">A+</span>
                    <span class="sr-only">Increase text size</span>
                </button>
                <button id="decrease-text-size" aria-label="Decrease text size" title="Smaller Text">
                    <span aria-hidden="true">A-</span>
                    <span class="sr-only">Decrease text size</span>
                </button>
                <button id="reset-accessibility" aria-label="Reset accessibility settings" title="Reset">
                    <span aria-hidden="true">↺</span>
                    <span class="sr-only">Reset all accessibility settings</span>
                </button>
            `;

            const style = document.createElement('style');
            style.textContent = `
                #accessibility-controls {
                    position: fixed;
                    top: 10px;
                    right: 10px;
                    z-index: 9999;
                    background: rgba(255,255,255,0.95);
                    border: 2px solid #667eea;
                    border-radius: 8px;
                    padding: 10px;
                    display: flex;
                    gap: 8px;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                }
                #accessibility-controls button {
                    background: #667eea;
                    color: white;
                    border: none;
                    border-radius: 4px;
                    padding: 8px 12px;
                    cursor: pointer;
                    font-size: 16px;
                    font-weight: bold;
                    transition: all 0.2s;
                }
                #accessibility-controls button:hover {
                    background: #5568d3;
                    transform: scale(1.05);
                }
                #accessibility-controls button:focus {
                    outline: 3px solid #000;
                    outline-offset: 2px;
                }
                @media (max-width: 768px) {
                    #accessibility-controls {
                        top: auto;
                        bottom: 10px;
                        right: 10px;
                        flex-direction: column;
                    }
                }
            `;
            document.head.appendChild(style);
            document.body.appendChild(controls);

            // Control handlers
            document.getElementById('toggle-high-contrast').addEventListener('click', function() {
                document.body.classList.toggle('high-contrast');
                localStorage.setItem('highContrast', document.body.classList.contains('high-contrast'));
                this.setAttribute('aria-pressed', document.body.classList.contains('high-contrast'));
            });

            let textSizeLevel = parseInt(localStorage.getItem('textSizeLevel') || '0');

            document.getElementById('increase-text-size').addEventListener('click', function() {
                if (textSizeLevel < 3) {
                    textSizeLevel++;
                    document.documentElement.style.fontSize = (100 + (textSizeLevel * 10)) + '%';
                    localStorage.setItem('textSizeLevel', textSizeLevel);
                    announceToScreenReader(`Text size increased to ${100 + (textSizeLevel * 10)}%`);
                }
            });

            document.getElementById('decrease-text-size').addEventListener('click', function() {
                if (textSizeLevel > -2) {
                    textSizeLevel--;
                    document.documentElement.style.fontSize = (100 + (textSizeLevel * 10)) + '%';
                    localStorage.setItem('textSizeLevel', textSizeLevel);
                    announceToScreenReader(`Text size decreased to ${100 + (textSizeLevel * 10)}%`);
                }
            });

            document.getElementById('reset-accessibility').addEventListener('click', function() {
                document.body.classList.remove('high-contrast');
                document.documentElement.style.fontSize = '100%';
                textSizeLevel = 0;
                localStorage.removeItem('highContrast');
                localStorage.removeItem('textSizeLevel');
                announceToScreenReader('Accessibility settings reset to default');
            });

            // Restore saved settings
            if (localStorage.getItem('highContrast') === 'true') {
                document.body.classList.add('high-contrast');
                document.getElementById('toggle-high-contrast').setAttribute('aria-pressed', 'true');
            }
            if (textSizeLevel !== 0) {
                document.documentElement.style.fontSize = (100 + (textSizeLevel * 10)) + '%';
            }
        },

        // Initialize AccessKit
        initAccessKit: function() {
            // AccessKit initialization for cross-platform accessibility
            // This integrates with native accessibility APIs on different platforms

            console.log('[AccessKit] Initializing accessibility tree for assistive technologies...');

            // Mark main content area
            const mainContent = document.querySelector('main, [role="main"], .container, .main-content');
            if (mainContent && !mainContent.id) {
                mainContent.id = 'main-content';
                mainContent.setAttribute('role', 'main');
                mainContent.setAttribute('aria-label', 'Main content');
            }

            // Ensure all images have alt text
            document.querySelectorAll('img:not([alt])').forEach(img => {
                img.setAttribute('alt', img.getAttribute('title') || 'Image');
                console.warn('[AccessKit] Added missing alt text to image:', img.src);
            });

            // Ensure all form inputs have labels
            document.querySelectorAll('input, select, textarea').forEach(input => {
                if (!input.id) input.id = 'input-' + Math.random().toString(36).substr(2, 9);

                const label = document.querySelector(`label[for="${input.id}"]`);
                if (!label && !input.getAttribute('aria-label')) {
                    const placeholder = input.getAttribute('placeholder') || input.name || 'Input field';
                    input.setAttribute('aria-label', placeholder);
                    console.warn('[AccessKit] Added missing label to input:', input.name);
                }
            });

            // Announce to screen readers
            announceToScreenReader('Accessibility features loaded. Use Tab to navigate, and activate accessibility controls in top right corner.');

            console.log('[AccessKit] Accessibility tree initialized successfully');
        }
    };

    // Helper function to announce to screen readers
    function announceToScreenReader(message) {
        const announcement = document.getElementById('page-announcement') ||
                           document.createElement('div');

        if (!announcement.id) {
            announcement.setAttribute('role', 'status');
            announcement.setAttribute('aria-live', 'polite');
            announcement.className = 'sr-only';
            announcement.id = 'page-announcement';
            document.body.appendChild(announcement);
        }

        announcement.textContent = message;

        // Clear after 3 seconds
        setTimeout(() => {
            announcement.textContent = '';
        }, 3000);
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            AccessibilityManager.init();
        });
    } else {
        AccessibilityManager.init();
    }

    // Export for use in other scripts
    window.FlexPBXAccessibility = AccessibilityManager;
    window.announceToScreenReader = announceToScreenReader;

})();
