/**
 * FlexPBX Idle Timeout Warning System
 * Shows countdown modal 5 minutes before session expires
 * Provides "Continue Session" button to reset timer
 * Auto-logout when timer reaches 0
 */

class IdleTimeoutWarning {
    constructor(options = {}) {
        this.warningTime = options.warningTime || 300; // Show warning 5 minutes before timeout (300 seconds)
        this.checkInterval = options.checkInterval || 5000; // Check every 5 seconds
        this.redirectUrl = options.redirectUrl || '/admin/login.php?timeout=1';
        this.sessionInfoUrl = options.sessionInfoUrl || '/api/session-info.php';
        this.extendSessionUrl = options.extendSessionUrl || '/api/extend-session.php';

        this.modal = null;
        this.intervalId = null;
        this.warningShown = false;

        this.init();
    }

    init() {
        // Check session info on load
        this.checkSession();

        // Set up periodic checks
        this.intervalId = setInterval(() => {
            this.checkSession();
        }, this.checkInterval);

        // Create modal
        this.createModal();

        // Listen for user activity to potentially extend session
        this.setupActivityListeners();
    }

    async checkSession() {
        try {
            const response = await fetch(this.sessionInfoUrl);
            const data = await response.json();

            if (!data.logged_in) {
                // Not logged in - redirect
                this.logout();
                return;
            }

            const timeRemaining = data.time_remaining;
            const sessionType = data.session_type;

            // Only show warnings for idle_timeout sessions
            if (sessionType === 'idle_timeout') {
                if (timeRemaining <= this.warningTime && timeRemaining > 0) {
                    // Show warning
                    if (!this.warningShown) {
                        this.showWarning();
                    }
                    this.updateCountdown(timeRemaining);
                } else if (timeRemaining <= 0) {
                    // Time's up - logout
                    this.logout();
                } else {
                    // Still plenty of time - hide warning if shown
                    if (this.warningShown) {
                        this.hideWarning();
                    }
                }

                // Update session badge
                this.updateSessionBadge(data);
            } else {
                // Extended session - no warnings needed
                this.updateSessionBadge(data);
            }
        } catch (error) {
            console.error('Failed to check session:', error);
        }
    }

    createModal() {
        // Create modal HTML
        const modalHtml = `
            <div id="idle-timeout-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 10000; justify-content: center; align-items: center;">
                <div style="background: white; border-radius: 12px; padding: 2rem; max-width: 500px; width: 90%; box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
                    <div style="text-align: center; margin-bottom: 1.5rem;">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">⏱️</div>
                        <h2 style="color: #2c3e50; margin: 0 0 0.5rem 0; font-size: 1.5rem;">Session Expiring Soon</h2>
                        <p style="color: #666; margin: 0; font-size: 0.95rem;">You will be automatically logged out due to inactivity</p>
                    </div>

                    <div style="background: #fff3cd; border: 2px solid #ffc107; border-radius: 8px; padding: 1.5rem; text-align: center; margin-bottom: 1.5rem;">
                        <div style="font-size: 3rem; font-weight: bold; color: #856404; margin-bottom: 0.5rem;" id="countdown-timer">5:00</div>
                        <div style="color: #856404; font-size: 0.9rem;">Time remaining</div>
                    </div>

                    <div style="background: #e3f2fd; border-left: 4px solid #2196f3; padding: 1rem; border-radius: 4px; margin-bottom: 1.5rem; font-size: 0.85rem; color: #1565c0;">
                        <strong>Why am I seeing this?</strong><br>
                        You chose not to stay logged in, so your session will expire after 30 minutes of inactivity. Click "Continue Session" to stay logged in.
                    </div>

                    <div style="display: flex; gap: 1rem;">
                        <button id="continue-session-btn" style="flex: 1; background: linear-gradient(135deg, #4ade80 0%, #22c55e 100%); color: white; border: none; padding: 1rem; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: transform 0.2s;">
                            Continue Session
                        </button>
                        <button id="logout-now-btn" style="flex: 1; background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white; border: none; padding: 1rem; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: transform 0.2s;">
                            Logout Now
                        </button>
                    </div>
                </div>
            </div>
        `;

        // Insert modal into page
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        this.modal = document.getElementById('idle-timeout-modal');

        // Add event listeners
        document.getElementById('continue-session-btn').addEventListener('click', () => {
            this.extendSession();
        });

        document.getElementById('logout-now-btn').addEventListener('click', () => {
            this.logout();
        });

        // Add hover effects
        const buttons = [document.getElementById('continue-session-btn'), document.getElementById('logout-now-btn')];
        buttons.forEach(btn => {
            btn.addEventListener('mouseenter', () => {
                btn.style.transform = 'translateY(-2px)';
            });
            btn.addEventListener('mouseleave', () => {
                btn.style.transform = 'translateY(0)';
            });
        });
    }

    showWarning() {
        if (this.modal) {
            this.modal.style.display = 'flex';
            this.warningShown = true;
        }
    }

    hideWarning() {
        if (this.modal) {
            this.modal.style.display = 'none';
            this.warningShown = false;
        }
    }

    updateCountdown(seconds) {
        const minutes = Math.floor(seconds / 60);
        const secs = seconds % 60;
        const display = `${minutes}:${secs.toString().padStart(2, '0')}`;

        const countdownElement = document.getElementById('countdown-timer');
        if (countdownElement) {
            countdownElement.textContent = display;

            // Change color when under 1 minute
            if (seconds < 60) {
                countdownElement.style.color = '#dc2626';
            } else {
                countdownElement.style.color = '#856404';
            }
        }
    }

    async extendSession() {
        try {
            const response = await fetch(this.extendSessionUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                }
            });

            const data = await response.json();

            if (data.success) {
                this.hideWarning();
                // Show success message
                this.showNotification('Session extended successfully!', 'success');
            } else {
                this.showNotification('Failed to extend session', 'error');
            }
        } catch (error) {
            console.error('Failed to extend session:', error);
            this.showNotification('Failed to extend session', 'error');
        }
    }

    logout() {
        // Clear interval
        if (this.intervalId) {
            clearInterval(this.intervalId);
        }

        // Redirect to login
        window.location.href = this.redirectUrl;
    }

    updateSessionBadge(sessionData) {
        // Check if badge exists, if not create it
        let badge = document.getElementById('session-type-badge');

        if (!badge) {
            // Create badge in header (assuming there's a header element)
            const header = document.querySelector('header') || document.querySelector('.header') || document.body;
            badge = document.createElement('div');
            badge.id = 'session-type-badge';
            badge.style.cssText = `
                position: fixed;
                top: 10px;
                right: 10px;
                padding: 0.5rem 1rem;
                border-radius: 20px;
                font-size: 0.75rem;
                font-weight: 700;
                color: white;
                z-index: 1000;
                box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            `;
            document.body.appendChild(badge);
        }

        // Update badge content and color
        const color = sessionData.session_type === 'idle_timeout' ? '#fbbf24' : '#4ade80';
        badge.style.background = color;
        badge.textContent = sessionData.session_type_label;

        // Add countdown for idle_timeout
        if (sessionData.session_type === 'idle_timeout') {
            const minutes = Math.floor(sessionData.time_remaining / 60);
            badge.textContent = `${sessionData.session_type_label} (${minutes}m left)`;
        }
    }

    showNotification(message, type = 'info') {
        const colors = {
            success: '#4ade80',
            error: '#ef4444',
            info: '#3b82f6'
        };

        const notification = document.createElement('div');
        notification.style.cssText = `
            position: fixed;
            top: 70px;
            right: 10px;
            background: ${colors[type]};
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            z-index: 10001;
            animation: slideIn 0.3s ease;
        `;
        notification.textContent = message;
        document.body.appendChild(notification);

        // Remove after 3 seconds
        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => {
                notification.remove();
            }, 300);
        }, 3000);
    }

    setupActivityListeners() {
        // Don't auto-extend on activity, only when user clicks "Continue"
        // But we can track activity for analytics if needed
        const activityEvents = ['mousedown', 'keydown', 'scroll', 'touchstart'];
        activityEvents.forEach(event => {
            document.addEventListener(event, () => {
                // Could track last activity time here
            }, { passive: true });
        });
    }

    destroy() {
        if (this.intervalId) {
            clearInterval(this.intervalId);
        }
        if (this.modal) {
            this.modal.remove();
        }
    }
}

// Auto-initialize on page load if not manually initialized
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.idleTimeoutWarning = new IdleTimeoutWarning();
    });
} else {
    window.idleTimeoutWarning = new IdleTimeoutWarning();
}
