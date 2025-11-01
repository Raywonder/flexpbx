/**
 * FlexPBX Backup Queue Processor - Browser Fallback
 * For systems without cron access
 * Polls backup queue and processes requests via browser
 * Created: 2025-10-24
 */

class BackupQueueProcessor {
    constructor() {
        this.enabled = false;
        this.interval = null;
        this.pollIntervalMs = 30000; // 30 seconds (less aggressive than cron)
        this.processingLockKey = 'flexpbx_backup_processing';
        this.lastProcessKey = 'flexpbx_backup_last_process';
        this.statusElement = null;
        this.init();
    }

    init() {
        // Check if cron is available
        this.checkCronStatus().then(hasCron => {
            if (!hasCron) {
                console.log('[BackupProcessor] Cron not detected, enabling fallback processor');
                this.enable();
            } else {
                console.log('[BackupProcessor] Cron detected, fallback not needed');
            }
        });

        // Add status indicator to UI if we're on backup page
        if (window.location.pathname.includes('backup-restore')) {
            this.addStatusIndicator();
        }
    }

    async checkCronStatus() {
        try {
            // Check when last cron run happened
            const response = await fetch('/api/system.php?path=backup_status&request_id=cron_check', {
                method: 'GET'
            });

            const data = await response.json();

            // If we have a recent cron log file, cron is working
            const cronLogResponse = await fetch('/logs/backup-queue-cron.log', {
                method: 'HEAD'
            });

            if (cronLogResponse.ok) {
                // Check if log is recent (< 10 minutes old)
                const lastModified = new Date(cronLogResponse.headers.get('Last-Modified'));
                const now = new Date();
                const minutesAgo = (now - lastModified) / 1000 / 60;

                return minutesAgo < 10;
            }

            return false;
        } catch (error) {
            console.error('[BackupProcessor] Error checking cron status:', error);
            return false; // Assume no cron if we can't check
        }
    }

    addStatusIndicator() {
        const indicator = document.createElement('div');
        indicator.id = 'backup-processor-status';
        indicator.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: rgba(0, 123, 255, 0.9);
            color: white;
            padding: 10px 15px;
            border-radius: 8px;
            font-size: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            z-index: 9999;
            display: none;
        `;
        indicator.innerHTML = `
            <i class="fas fa-sync fa-spin"></i>
            <span id="backup-processor-text">Backup Processor Active</span>
        `;
        document.body.appendChild(indicator);
        this.statusElement = indicator;
    }

    enable() {
        if (this.enabled) return;

        this.enabled = true;
        console.log('[BackupProcessor] Fallback processor enabled');

        // Show status if on backup page
        if (this.statusElement) {
            this.statusElement.style.display = 'block';
        }

        // Start polling
        this.startPolling();

        // Process immediately
        this.processQueue();
    }

    disable() {
        if (!this.enabled) return;

        this.enabled = false;
        console.log('[BackupProcessor] Fallback processor disabled');

        // Hide status
        if (this.statusElement) {
            this.statusElement.style.display = 'none';
        }

        // Stop polling
        this.stopPolling();
    }

    startPolling() {
        this.stopPolling(); // Clear any existing interval

        this.interval = setInterval(() => {
            this.processQueue();
        }, this.pollIntervalMs);

        console.log(`[BackupProcessor] Polling started (every ${this.pollIntervalMs/1000}s)`);
    }

    stopPolling() {
        if (this.interval) {
            clearInterval(this.interval);
            this.interval = null;
        }
    }

    async processQueue() {
        // Check if another tab is already processing
        const processingLock = localStorage.getItem(this.processingLockKey);
        if (processingLock) {
            const lockTime = parseInt(processingLock);
            const now = Date.now();

            // If lock is less than 5 minutes old, skip
            if (now - lockTime < 5 * 60 * 1000) {
                console.log('[BackupProcessor] Another instance is processing, skipping');
                return;
            }
        }

        // Set processing lock
        localStorage.setItem(this.processingLockKey, Date.now().toString());

        try {
            // Call the PHP processor via API endpoint
            const response = await fetch('/api/system.php?path=process_backup_queue', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    source: 'browser_fallback'
                })
            });

            const data = await response.json();

            if (data.success) {
                if (data.processed > 0) {
                    console.log(`[BackupProcessor] Processed ${data.processed} backup(s)`);
                    this.updateStatus(`Processed ${data.processed} backup(s)`, 'success');

                    // Notify user if on backup page
                    if (window.location.pathname.includes('backup-restore') && typeof showSuccess === 'function') {
                        showSuccess(`Backup queue processed: ${data.processed} backup(s) completed`);
                    }
                }
            } else {
                console.error('[BackupProcessor] Processing failed:', data.error);
                this.updateStatus('Processing failed', 'error');
            }

            // Update last process time
            localStorage.setItem(this.lastProcessKey, Date.now().toString());
        } catch (error) {
            console.error('[BackupProcessor] Error processing queue:', error);
            this.updateStatus('Error processing', 'error');
        } finally {
            // Release lock
            localStorage.removeItem(this.processingLockKey);
        }
    }

    updateStatus(message, type = 'info') {
        if (!this.statusElement) return;

        const text = this.statusElement.querySelector('#backup-processor-text');
        if (text) {
            text.textContent = message;
        }

        // Update color based on type
        const colors = {
            'info': 'rgba(0, 123, 255, 0.9)',
            'success': 'rgba(40, 167, 69, 0.9)',
            'error': 'rgba(220, 53, 69, 0.9)'
        };

        this.statusElement.style.backgroundColor = colors[type] || colors.info;

        // Reset to default after 3 seconds
        setTimeout(() => {
            if (text) {
                text.textContent = 'Backup Processor Active';
            }
            this.statusElement.style.backgroundColor = colors.info;
        }, 3000);
    }

    // Manual trigger from UI
    async triggerManualProcess() {
        console.log('[BackupProcessor] Manual process triggered');
        this.updateStatus('Processing...', 'info');
        await this.processQueue();
    }
}

// Initialize on page load
if (typeof window !== 'undefined') {
    window.backupQueueProcessor = new BackupQueueProcessor();

    // Expose manual trigger for UI buttons
    window.triggerBackupProcess = () => {
        window.backupQueueProcessor.triggerManualProcess();
    };
}
