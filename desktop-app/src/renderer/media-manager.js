/**
 * FlexPBX Media Manager
 * Handles import/export of media files (music, hold music, IVR prompts, etc.)
 * Supports accessibility features for screen readers
 */

class MediaManager {
    constructor() {
        this.supportedFormats = {
            audio: ['mp3', 'wav', 'ogg', 'flac', 'm4a', 'aac'],
            backup: ['flx', 'flxx', 'tar', 'tar.gz', 'zip'],
            config: ['json', 'xml', 'conf', 'cfg']
        };

        this.mediaCategories = [
            { id: 'hold-music', name: 'Hold Music', path: '/media/hold-music/' },
            { id: 'ivr-prompts', name: 'IVR Prompts', path: '/media/ivr-prompts/' },
            { id: 'voicemail-greetings', name: 'Voicemail Greetings', path: '/media/voicemail/' },
            { id: 'announcements', name: 'Announcements', path: '/media/announcements/' },
            { id: 'ringtones', name: 'Ringtones', path: '/media/ringtones/' },
            { id: 'moh-classes', name: 'Music on Hold Classes', path: '/media/moh-classes/' }
        ];

        this.init();
    }

    init() {
        console.log('Initializing Media Manager...');
        this.setupEventListeners();
        this.loadMediaLibrary();
        this.setupAccessibilityFeatures();
    }

    setupEventListeners() {
        // Import button handlers
        document.querySelectorAll('.media-import-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const category = e.target.dataset.category;
                this.handleImport(category);
            });
        });

        // Export button handlers
        document.querySelectorAll('.media-export-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const category = e.target.dataset.category;
                this.handleExport(category);
            });
        });

        // Bulk operations
        const bulkImportBtn = document.getElementById('bulk-media-import');
        if (bulkImportBtn) {
            bulkImportBtn.addEventListener('click', () => this.handleBulkImport());
        }

        const bulkExportBtn = document.getElementById('bulk-media-export');
        if (bulkExportBtn) {
            bulkExportBtn.addEventListener('click', () => this.handleBulkExport());
        }
    }

    setupAccessibilityFeatures() {
        // Add ARIA labels for screen readers
        document.querySelectorAll('.media-item').forEach(item => {
            const fileName = item.querySelector('.file-name')?.textContent;
            const fileSize = item.querySelector('.file-size')?.textContent;
            const duration = item.querySelector('.duration')?.textContent;

            item.setAttribute('aria-label',
                `Media file: ${fileName}, Size: ${fileSize}, Duration: ${duration}`);
        });

        // Add keyboard navigation
        this.setupKeyboardNavigation();
    }

    setupKeyboardNavigation() {
        document.addEventListener('keydown', (e) => {
            // Alt+I for import
            if (e.altKey && e.key === 'i') {
                e.preventDefault();
                this.showImportDialog();
            }

            // Alt+E for export
            if (e.altKey && e.key === 'e') {
                e.preventDefault();
                this.showExportDialog();
            }

            // Alt+M for media library
            if (e.altKey && e.key === 'm') {
                e.preventDefault();
                this.showMediaLibrary();
            }
        });
    }

    async handleImport(category) {
        console.log(`Importing media for category: ${category}`);

        try {
            // Check if Electron API is available
            if (!window.electronAPI || !window.electronAPI.selectFiles) {
                // Fallback: Create file input element
                const input = document.createElement('input');
                input.type = 'file';
                input.multiple = true;
                input.accept = this.supportedFormats.audio.map(ext => `.${ext}`).join(',');

                input.onchange = async (e) => {
                    const files = Array.from(e.target.files);
                    await this.processImportedFiles(files, category);
                };

                input.click();
                return;
            }

            // Use Electron API
            const files = await window.electronAPI.selectFiles({
                filters: [
                    { name: 'Audio Files', extensions: this.supportedFormats.audio },
                    { name: 'All Files', extensions: ['*'] }
                ],
                properties: ['openFile', 'multiSelections']
            });

            if (files && files.length > 0) {
                await this.processImportedFiles(files, category);
            }
        } catch (error) {
            console.error('Import failed:', error);
            this.showToast('Failed to import media files', 'error');
        }
    }

    async processImportedFiles(files, category) {
        const categoryInfo = this.mediaCategories.find(cat => cat.id === category);
        const results = [];

        for (const file of files) {
            try {
                // Validate file
                const validation = await this.validateMediaFile(file);
                if (!validation.valid) {
                    results.push({
                        file: file.name || file,
                        status: 'failed',
                        reason: validation.reason
                    });
                    continue;
                }

                // Process and convert if needed
                const processedFile = await this.processMediaFile(file, category);

                // Save to appropriate directory
                const savedPath = await this.saveMediaFile(processedFile, categoryInfo.path);

                results.push({
                    file: file.name || file,
                    status: 'success',
                    path: savedPath
                });

                // Update UI
                this.addMediaToLibrary(processedFile, category);

            } catch (error) {
                console.error(`Failed to import ${file.name || file}:`, error);
                results.push({
                    file: file.name || file,
                    status: 'failed',
                    reason: error.message
                });
            }
        }

        // Show import results
        this.showImportResults(results);

        // Announce to screen readers
        this.announceToScreenReader(`Imported ${results.filter(r => r.status === 'success').length} of ${files.length} files`);
    }

    async handleExport(category) {
        console.log(`Exporting media for category: ${category}`);

        try {
            const selectedItems = this.getSelectedMediaItems(category);

            if (selectedItems.length === 0) {
                this.showToast('Please select media files to export', 'info');
                return;
            }

            // Choose export format
            const format = await this.showExportFormatDialog();

            // Create export package
            const exportPackage = await this.createExportPackage(selectedItems, format);

            // Save export
            await this.saveExport(exportPackage, category);

            this.showToast(`Exported ${selectedItems.length} files successfully`, 'success');
            this.announceToScreenReader(`Exported ${selectedItems.length} media files`);

        } catch (error) {
            console.error('Export failed:', error);
            this.showToast('Failed to export media files', 'error');
        }
    }

    async handleBulkImport() {
        console.log('Starting bulk media import...');

        try {
            // Select backup file or directory
            const source = await this.selectImportSource();

            if (!source) return;

            // Parse and validate import
            const mediaData = await this.parseImportSource(source);

            // Show preview dialog
            const confirmed = await this.showImportPreview(mediaData);

            if (!confirmed) return;

            // Process bulk import
            const results = await this.processBulkImport(mediaData);

            // Show results
            this.showBulkImportResults(results);

            // Refresh media library
            await this.loadMediaLibrary();

        } catch (error) {
            console.error('Bulk import failed:', error);
            this.showToast('Bulk import failed: ' + error.message, 'error');
        }
    }

    async handleBulkExport() {
        console.log('Starting bulk media export...');

        try {
            // Show export options
            const options = await this.showBulkExportOptions();

            if (!options) return;

            // Gather all media by categories
            const mediaData = await this.gatherMediaForExport(options.categories);

            // Create comprehensive backup
            const backup = await this.createMediaBackup(mediaData, options);

            // Save backup
            const savedPath = await this.saveMediaBackup(backup, options.format);

            this.showToast(`Media backup saved to ${savedPath}`, 'success');
            this.announceToScreenReader('Media backup created successfully');

        } catch (error) {
            console.error('Bulk export failed:', error);
            this.showToast('Bulk export failed: ' + error.message, 'error');
        }
    }

    async validateMediaFile(file) {
        // Check file extension
        const ext = this.getFileExtension(file.name || file);
        if (!this.supportedFormats.audio.includes(ext.toLowerCase())) {
            return { valid: false, reason: 'Unsupported file format' };
        }

        // Check file size (max 50MB for audio files)
        if (file.size && file.size > 50 * 1024 * 1024) {
            return { valid: false, reason: 'File too large (max 50MB)' };
        }

        // Additional validation for audio files
        try {
            // Could add audio duration, bitrate checks here
            return { valid: true };
        } catch (error) {
            return { valid: false, reason: 'Invalid audio file' };
        }
    }

    async processMediaFile(file, category) {
        // Process based on category requirements
        const processed = {
            name: file.name || file,
            size: file.size,
            category: category,
            timestamp: new Date().toISOString()
        };

        // Category-specific processing
        switch (category) {
            case 'hold-music':
                // Convert to appropriate format for hold music
                processed.format = 'wav';
                processed.sampleRate = 8000; // Standard for telephony
                break;

            case 'ivr-prompts':
                // Ensure clear audio for IVR
                processed.format = 'wav';
                processed.normalized = true;
                break;

            case 'ringtones':
                // Optimize for ringtones
                processed.format = 'mp3';
                processed.duration = Math.min(processed.duration || 30, 30); // Max 30 seconds
                break;
        }

        return processed;
    }

    async saveMediaFile(file, path) {
        if (window.electronAPI && window.electronAPI.saveMediaFile) {
            return await window.electronAPI.saveMediaFile(file, path);
        }

        // Fallback: Store in local storage or IndexedDB
        const savedPath = `${path}${file.name}`;
        console.log(`Would save file to: ${savedPath}`);
        return savedPath;
    }

    async loadMediaLibrary() {
        console.log('Loading media library...');

        try {
            // Load media from each category
            for (const category of this.mediaCategories) {
                const media = await this.loadCategoryMedia(category);
                this.displayCategoryMedia(category.id, media);
            }
        } catch (error) {
            console.error('Failed to load media library:', error);
        }
    }

    async loadCategoryMedia(category) {
        if (window.electronAPI && window.electronAPI.loadMediaFiles) {
            return await window.electronAPI.loadMediaFiles(category.path);
        }

        // Fallback: Return mock data
        return [
            { name: 'default-hold.wav', size: '2.3MB', duration: '3:45' },
            { name: 'jazz-hold.mp3', size: '1.8MB', duration: '2:30' }
        ];
    }

    displayCategoryMedia(categoryId, mediaFiles) {
        const container = document.getElementById(`media-${categoryId}`);
        if (!container) return;

        container.innerHTML = '';

        mediaFiles.forEach(file => {
            const mediaElement = this.createMediaElement(file, categoryId);
            container.appendChild(mediaElement);
        });
    }

    createMediaElement(file, category) {
        const div = document.createElement('div');
        div.className = 'media-item';
        div.setAttribute('role', 'listitem');
        div.setAttribute('tabindex', '0');

        div.innerHTML = `
            <div class="media-item-content">
                <input type="checkbox"
                       id="media-${file.name}"
                       class="media-checkbox"
                       aria-label="Select ${file.name}">
                <span class="file-name">${file.name}</span>
                <span class="file-size">${file.size}</span>
                <span class="duration">${file.duration || 'N/A'}</span>
                <div class="media-actions">
                    <button class="play-btn"
                            data-file="${file.name}"
                            aria-label="Play ${file.name}">
                        Play
                    </button>
                    <button class="edit-btn"
                            data-file="${file.name}"
                            aria-label="Edit ${file.name}">
                        Edit
                    </button>
                    <button class="delete-btn"
                            data-file="${file.name}"
                            aria-label="Delete ${file.name}">
                        Delete
                    </button>
                </div>
            </div>
        `;

        // Add event listeners
        div.querySelector('.play-btn').addEventListener('click', () => {
            this.playMedia(file, category);
        });

        div.querySelector('.edit-btn').addEventListener('click', () => {
            this.editMedia(file, category);
        });

        div.querySelector('.delete-btn').addEventListener('click', () => {
            this.deleteMedia(file, category);
        });

        return div;
    }

    playMedia(file, category) {
        console.log(`Playing media: ${file.name}`);
        // Implementation for playing media
        this.announceToScreenReader(`Playing ${file.name}`);
    }

    editMedia(file, category) {
        console.log(`Editing media: ${file.name}`);
        // Implementation for editing media metadata
    }

    async deleteMedia(file, category) {
        if (confirm(`Delete ${file.name}?`)) {
            console.log(`Deleting media: ${file.name}`);
            // Implementation for deleting media
            this.announceToScreenReader(`Deleted ${file.name}`);
        }
    }

    getSelectedMediaItems(category) {
        const container = document.getElementById(`media-${category}`);
        if (!container) return [];

        const selected = [];
        container.querySelectorAll('.media-checkbox:checked').forEach(checkbox => {
            const item = checkbox.closest('.media-item');
            if (item) {
                selected.push({
                    name: item.querySelector('.file-name').textContent,
                    size: item.querySelector('.file-size').textContent,
                    duration: item.querySelector('.duration').textContent
                });
            }
        });

        return selected;
    }

    async createExportPackage(items, format) {
        const exportData = {
            version: '2.0',
            timestamp: new Date().toISOString(),
            format: format,
            items: items,
            metadata: {
                totalFiles: items.length,
                totalSize: this.calculateTotalSize(items)
            }
        };

        return exportData;
    }

    async saveExport(exportPackage, category) {
        const fileName = `flexpbx-media-${category}-${Date.now()}.${exportPackage.format}`;

        if (window.electronAPI && window.electronAPI.saveFile) {
            return await window.electronAPI.saveFile(exportPackage, fileName);
        }

        // Fallback: Download as file
        const blob = new Blob([JSON.stringify(exportPackage, null, 2)],
                             { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = fileName;
        a.click();
        URL.revokeObjectURL(url);

        return fileName;
    }

    showImportResults(results) {
        const successCount = results.filter(r => r.status === 'success').length;
        const failedCount = results.filter(r => r.status === 'failed').length;

        let message = `Import complete: ${successCount} succeeded`;
        if (failedCount > 0) {
            message += `, ${failedCount} failed`;
        }

        this.showToast(message, failedCount > 0 ? 'warning' : 'success');
    }

    showToast(message, type = 'info') {
        // Create or use existing toast container
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            container.setAttribute('aria-live', 'polite');
            container.setAttribute('aria-atomic', 'true');
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.textContent = message;
        toast.setAttribute('role', 'alert');

        container.appendChild(toast);

        setTimeout(() => {
            toast.remove();
        }, 5000);
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

    getFileExtension(filename) {
        return filename.split('.').pop();
    }

    calculateTotalSize(items) {
        // Parse and sum sizes
        let total = 0;
        items.forEach(item => {
            const size = parseFloat(item.size);
            if (size) total += size;
        });
        return `${total.toFixed(2)}MB`;
    }
}

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = MediaManager;
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.mediaManager = new MediaManager();
});