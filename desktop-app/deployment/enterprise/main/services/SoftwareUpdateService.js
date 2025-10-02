const { EventEmitter } = require('events');
const { spawn, exec, execSync } = require('child_process');
const path = require('path');
const fs = require('fs-extra');
const os = require('os');
const crypto = require('crypto');
const { pipeline } = require('stream');
const { promisify } = require('util');

class SoftwareUpdateService extends EventEmitter {
    constructor() {
        super();
        this.configDir = path.join(os.homedir(), '.flexpbx', 'updates');
        this.downloadDir = path.join(this.configDir, 'downloads');
        this.backupDir = path.join(this.configDir, 'backups');
        this.updateChannels = {
            stable: 'https://updates.flexpbx.com/stable',
            beta: 'https://updates.flexpbx.com/beta',
            nightly: 'https://updates.flexpbx.com/nightly'
        };

        this.updateTypes = {
            client: {
                name: 'FlexPBX Desktop Client',
                platforms: ['darwin', 'linux', 'win32'],
                silent: true,
                restart: true
            },
            server: {
                name: 'FlexPBX Server Modules',
                platforms: ['darwin', 'linux', 'win32'],
                silent: true,
                restart: false
            },
            copyparty: {
                name: 'CopyParty Service',
                platforms: ['darwin', 'linux', 'win32'],
                silent: true,
                restart: true
            },
            dns: {
                name: 'DNS Service Components',
                platforms: ['darwin', 'linux', 'win32'],
                silent: true,
                restart: true
            },
            hooks: {
                name: 'CMS Hooks & Integrations',
                platforms: ['all'],
                silent: true,
                restart: false
            }
        };

        this.remoteClients = new Map();
        this.updateQueue = new Map();
        this.updateHistory = new Map();

        this.setupDirectories();
        this.loadUpdateHistory();
    }

    async setupDirectories() {
        await fs.ensureDir(this.configDir);
        await fs.ensureDir(this.downloadDir);
        await fs.ensureDir(this.backupDir);
        await fs.ensureDir(path.join(this.configDir, 'logs'));
        await fs.ensureDir(path.join(this.configDir, 'manifests'));
        await fs.ensureDir(path.join(this.configDir, 'temp'));

        console.log('📁 Software update directories initialized');
    }

    async loadUpdateHistory() {
        const historyPath = path.join(this.configDir, 'update_history.json');
        try {
            if (await fs.pathExists(historyPath)) {
                const history = await fs.readJSON(historyPath);
                this.updateHistory = new Map(Object.entries(history));
                console.log(`📚 Loaded ${this.updateHistory.size} update history entries`);
            }
        } catch (error) {
            console.error('Failed to load update history:', error);
        }
    }

    async saveUpdateHistory() {
        const historyPath = path.join(this.configDir, 'update_history.json');
        const history = Object.fromEntries(this.updateHistory);
        await fs.writeJSON(historyPath, history, { spaces: 2 });
    }

    async checkForUpdates(updateType = 'all', channel = 'stable') {
        console.log(`🔍 Checking for updates: ${updateType} (${channel})`);

        const availableUpdates = new Map();

        try {
            const updateTypesToCheck = updateType === 'all'
                ? Object.keys(this.updateTypes)
                : [updateType];

            for (const type of updateTypesToCheck) {
                const updateInfo = await this.checkUpdateForType(type, channel);
                if (updateInfo.available) {
                    availableUpdates.set(type, updateInfo);
                }
            }

            this.emit('updates-checked', {
                channel,
                available: availableUpdates.size,
                updates: Object.fromEntries(availableUpdates)
            });

            return {
                success: true,
                channel,
                available: availableUpdates.size,
                updates: Object.fromEntries(availableUpdates),
                message: availableUpdates.size > 0
                    ? `Found ${availableUpdates.size} updates available`
                    : 'No updates available'
            };

        } catch (error) {
            return {
                success: false,
                error: error.message
            };
        }
    }

    async checkUpdateForType(type, channel) {
        const updateConfig = this.updateTypes[type];
        if (!updateConfig) {
            throw new Error(`Unknown update type: ${type}`);
        }

        // Check current version
        const currentVersion = await this.getCurrentVersion(type);

        // Fetch latest version info from update server
        const latestVersion = await this.fetchLatestVersion(type, channel);

        const isAvailable = this.compareVersions(latestVersion.version, currentVersion) > 0;

        return {
            type,
            name: updateConfig.name,
            available: isAvailable,
            currentVersion,
            latestVersion: latestVersion.version,
            downloadUrl: latestVersion.downloadUrl,
            checksum: latestVersion.checksum,
            releaseNotes: latestVersion.releaseNotes,
            size: latestVersion.size,
            silent: updateConfig.silent,
            restart: updateConfig.restart
        };
    }

    async getCurrentVersion(type) {
        switch (type) {
            case 'client':
                return this.getClientVersion();
            case 'server':
                return this.getServerVersion();
            case 'copyparty':
                return this.getCopyPartyVersion();
            case 'dns':
                return this.getDNSVersion();
            case 'hooks':
                return this.getHooksVersion();
            default:
                return '0.0.0';
        }
    }

    async getClientVersion() {
        try {
            const packagePath = path.join(__dirname, '../../../package.json');
            const packageInfo = await fs.readJSON(packagePath);
            return packageInfo.version || '0.0.0';
        } catch (error) {
            return '0.0.0';
        }
    }

    async getServerVersion() {
        // Check for FlexPBX server version
        try {
            // This would check actual server installation
            return '2.0.0';
        } catch (error) {
            return '0.0.0';
        }
    }

    async getCopyPartyVersion() {
        try {
            const output = execSync('python3 -m copyparty --version', {
                encoding: 'utf8',
                stdio: ['ignore', 'pipe', 'ignore']
            });
            const match = output.match(/copyparty\s+([\d\.]+)/i);
            return match ? match[1] : '0.0.0';
        } catch (error) {
            return '0.0.0';
        }
    }

    async getDNSVersion() {
        // DNS service version (internal)
        return '2.0.0';
    }

    async getHooksVersion() {
        // Hooks version (internal)
        return '2.0.0';
    }

    async fetchLatestVersion(type, channel) {
        const platform = os.platform();
        const arch = os.arch();

        // Mock update server response - in production this would be real API calls
        const mockVersions = {
            client: {
                version: '2.1.0',
                downloadUrl: `https://releases.flexpbx.com/${channel}/flexpbx-desktop-2.1.0-${platform}-${arch}.dmg`,
                checksum: 'sha256:' + crypto.randomBytes(32).toString('hex'),
                releaseNotes: 'Performance improvements, new DNS management features, enhanced security',
                size: 108000000
            },
            server: {
                version: '2.1.0',
                downloadUrl: `https://releases.flexpbx.com/${channel}/flexpbx-server-2.1.0.tar.gz`,
                checksum: 'sha256:' + crypto.randomBytes(32).toString('hex'),
                releaseNotes: 'Bug fixes, improved call quality, new codec support',
                size: 50000000
            },
            copyparty: {
                version: '1.8.8',
                downloadUrl: `https://releases.flexpbx.com/${channel}/copyparty-1.8.8.tar.gz`,
                checksum: 'sha256:' + crypto.randomBytes(32).toString('hex'),
                releaseNotes: 'Security updates, performance improvements',
                size: 2000000
            },
            dns: {
                version: '2.1.0',
                downloadUrl: `https://releases.flexpbx.com/${channel}/flexpbx-dns-2.1.0.tar.gz`,
                checksum: 'sha256:' + crypto.randomBytes(32).toString('hex'),
                releaseNotes: 'New DNS server support, improved zone management',
                size: 5000000
            },
            hooks: {
                version: '2.1.0',
                downloadUrl: `https://releases.flexpbx.com/${channel}/flexpbx-hooks-2.1.0.tar.gz`,
                checksum: 'sha256:' + crypto.randomBytes(32).toString('hex'),
                releaseNotes: 'New CMS integrations, improved compatibility',
                size: 1000000
            }
        };

        return mockVersions[type] || {
            version: '0.0.0',
            downloadUrl: null,
            checksum: null,
            releaseNotes: 'No updates available',
            size: 0
        };
    }

    compareVersions(version1, version2) {
        const v1parts = version1.split('.').map(Number);
        const v2parts = version2.split('.').map(Number);

        for (let i = 0; i < Math.max(v1parts.length, v2parts.length); i++) {
            const v1part = v1parts[i] || 0;
            const v2part = v2parts[i] || 0;

            if (v1part > v2part) return 1;
            if (v1part < v2part) return -1;
        }

        return 0;
    }

    async downloadUpdate(updateInfo, options = {}) {
        const {
            background = true,
            verifyChecksum = true,
            retries = 3
        } = options;

        console.log(`📥 Downloading update: ${updateInfo.name} v${updateInfo.latestVersion}`);

        const updateId = crypto.randomUUID();
        const fileName = path.basename(updateInfo.downloadUrl);
        const downloadPath = path.join(this.downloadDir, updateId, fileName);

        await fs.ensureDir(path.dirname(downloadPath));

        try {
            // Download with progress tracking
            const downloadResult = await this.downloadFile(
                updateInfo.downloadUrl,
                downloadPath,
                {
                    expectedSize: updateInfo.size,
                    onProgress: (progress) => {
                        this.emit('download-progress', {
                            updateId,
                            type: updateInfo.type,
                            progress,
                            downloaded: progress.downloaded,
                            total: progress.total
                        });
                    }
                }
            );

            if (!downloadResult.success) {
                throw new Error(`Download failed: ${downloadResult.error}`);
            }

            // Verify checksum if provided
            if (verifyChecksum && updateInfo.checksum) {
                const isValid = await this.verifyChecksum(downloadPath, updateInfo.checksum);
                if (!isValid) {
                    await fs.remove(downloadPath);
                    throw new Error('Checksum verification failed');
                }
            }

            this.emit('download-completed', {
                updateId,
                type: updateInfo.type,
                downloadPath,
                verified: verifyChecksum
            });

            return {
                success: true,
                updateId,
                downloadPath,
                message: `Update downloaded successfully: ${fileName}`
            };

        } catch (error) {
            this.emit('download-failed', {
                updateId,
                type: updateInfo.type,
                error: error.message
            });

            return {
                success: false,
                error: error.message
            };
        }
    }

    async downloadFile(url, destinationPath, options = {}) {
        const { expectedSize, onProgress } = options;

        return new Promise((resolve, reject) => {
            const https = require('https');
            const http = require('http');

            const client = url.startsWith('https:') ? https : http;

            const request = client.get(url, (response) => {
                if (response.statusCode !== 200) {
                    reject(new Error(`HTTP ${response.statusCode}: ${response.statusMessage}`));
                    return;
                }

                const totalSize = parseInt(response.headers['content-length']) || expectedSize || 0;
                let downloadedSize = 0;

                const writeStream = fs.createWriteStream(destinationPath);

                response.on('data', (chunk) => {
                    downloadedSize += chunk.length;

                    if (onProgress) {
                        onProgress({
                            downloaded: downloadedSize,
                            total: totalSize,
                            percentage: totalSize > 0 ? (downloadedSize / totalSize) * 100 : 0
                        });
                    }
                });

                response.pipe(writeStream);

                writeStream.on('finish', () => {
                    writeStream.close();
                    resolve({
                        success: true,
                        downloadedSize,
                        expectedSize: totalSize
                    });
                });

                writeStream.on('error', (error) => {
                    fs.unlink(destinationPath, () => {});
                    reject(error);
                });
            });

            request.on('error', (error) => {
                reject(error);
            });

            request.setTimeout(30000, () => {
                request.destroy();
                reject(new Error('Download timeout'));
            });
        });
    }

    async verifyChecksum(filePath, expectedChecksum) {
        try {
            const [algorithm, expectedHash] = expectedChecksum.split(':');
            const fileBuffer = await fs.readFile(filePath);
            const hash = crypto.createHash(algorithm).update(fileBuffer).digest('hex');

            return hash === expectedHash;
        } catch (error) {
            console.error('Checksum verification error:', error);
            return false;
        }
    }

    async installUpdate(updateId, options = {}) {
        const {
            silent = true,
            createBackup = true,
            restartAfter = null // auto-detect from update type
        } = options;

        console.log(`🔧 Installing update: ${updateId}`);

        try {
            // Find downloaded update
            const updateDir = path.join(this.downloadDir, updateId);
            if (!await fs.pathExists(updateDir)) {
                throw new Error(`Update ${updateId} not found`);
            }

            const files = await fs.readdir(updateDir);
            if (files.length === 0) {
                throw new Error(`No files found in update ${updateId}`);
            }

            const updateFile = path.join(updateDir, files[0]);
            const updateInfo = await this.getUpdateInfo(updateId);

            // Create backup if requested
            if (createBackup) {
                await this.createBackup(updateInfo.type);
            }

            // Install based on update type and platform
            const installResult = await this.performInstallation(updateInfo.type, updateFile, { silent });

            if (!installResult.success) {
                throw new Error(`Installation failed: ${installResult.error}`);
            }

            // Record update in history
            await this.recordUpdate(updateId, updateInfo, installResult);

            // Schedule restart if needed
            const shouldRestart = restartAfter !== null ? restartAfter : updateInfo.restart;
            if (shouldRestart) {
                await this.scheduleRestart(updateInfo.type);
            }

            this.emit('update-installed', {
                updateId,
                type: updateInfo.type,
                version: updateInfo.latestVersion,
                restart: shouldRestart
            });

            return {
                success: true,
                updateId,
                type: updateInfo.type,
                version: updateInfo.latestVersion,
                restart: shouldRestart,
                message: 'Update installed successfully'
            };

        } catch (error) {
            this.emit('update-failed', {
                updateId,
                error: error.message
            });

            return {
                success: false,
                error: error.message
            };
        }
    }

    async performInstallation(type, updateFile, options = {}) {
        const { silent = true } = options;

        switch (type) {
            case 'client':
                return await this.installClientUpdate(updateFile, { silent });
            case 'server':
                return await this.installServerUpdate(updateFile, { silent });
            case 'copyparty':
                return await this.installCopyPartyUpdate(updateFile, { silent });
            case 'dns':
                return await this.installDNSUpdate(updateFile, { silent });
            case 'hooks':
                return await this.installHooksUpdate(updateFile, { silent });
            default:
                throw new Error(`Unknown update type: ${type}`);
        }
    }

    async installClientUpdate(updateFile, options = {}) {
        const { silent = true } = options;
        const platform = os.platform();

        try {
            if (platform === 'darwin') {
                // Install DMG on macOS
                const mountResult = await this.execCommand(`hdiutil attach "${updateFile}" -nobrowse -quiet`);
                if (mountResult.code !== 0) {
                    throw new Error('Failed to mount DMG');
                }

                // Find mounted volume and copy app
                const volumeName = 'FlexPBX Desktop';
                const copyCommand = `cp -R "/Volumes/${volumeName}/FlexPBX Desktop.app" "/Applications/"`;
                const copyResult = await this.execCommand(copyCommand);

                // Unmount
                await this.execCommand(`hdiutil detach "/Volumes/${volumeName}" -quiet`);

                if (copyResult.code !== 0) {
                    throw new Error('Failed to copy application');
                }

            } else if (platform === 'linux') {
                // Install AppImage on Linux
                const installPath = '/opt/flexpbx/FlexPBX-Desktop.AppImage';
                await fs.ensureDir(path.dirname(installPath));
                await fs.copy(updateFile, installPath);
                await fs.chmod(installPath, 0o755);

            } else if (platform === 'win32') {
                // Install on Windows (would need NSIS installer handling)
                const installResult = await this.execCommand(`"${updateFile}" /S`);
                if (installResult.code !== 0) {
                    throw new Error('Failed to install Windows update');
                }
            }

            return {
                success: true,
                message: 'Client update installed successfully'
            };

        } catch (error) {
            return {
                success: false,
                error: error.message
            };
        }
    }

    async installServerUpdate(updateFile, options = {}) {
        // Extract and install server components
        const extractDir = path.join(this.configDir, 'temp', 'server-extract');
        await fs.ensureDir(extractDir);

        try {
            // Extract tar.gz
            await this.execCommand(`tar -xzf "${updateFile}" -C "${extractDir}"`);

            // Run installation script if present
            const installScript = path.join(extractDir, 'install.sh');
            if (await fs.pathExists(installScript)) {
                await fs.chmod(installScript, 0o755);
                const result = await this.execCommand(`cd "${extractDir}" && ./install.sh`);
                if (result.code !== 0) {
                    throw new Error('Server installation script failed');
                }
            }

            return {
                success: true,
                message: 'Server update installed successfully'
            };

        } catch (error) {
            return {
                success: false,
                error: error.message
            };
        } finally {
            await fs.remove(extractDir);
        }
    }

    async installCopyPartyUpdate(updateFile, options = {}) {
        try {
            // Update CopyParty via pip
            const result = await this.execCommand(`pip3 install --upgrade "${updateFile}"`);

            if (result.code !== 0) {
                throw new Error('CopyParty update failed');
            }

            return {
                success: true,
                message: 'CopyParty update installed successfully'
            };

        } catch (error) {
            return {
                success: false,
                error: error.message
            };
        }
    }

    async installDNSUpdate(updateFile, options = {}) {
        // Update DNS service components
        const extractDir = path.join(this.configDir, 'temp', 'dns-extract');
        await fs.ensureDir(extractDir);

        try {
            await this.execCommand(`tar -xzf "${updateFile}" -C "${extractDir}"`);

            // Copy DNS service files
            const sourceDir = path.join(extractDir, 'dns');
            const targetDir = path.join(__dirname);

            if (await fs.pathExists(sourceDir)) {
                await fs.copy(sourceDir, targetDir, { overwrite: true });
            }

            return {
                success: true,
                message: 'DNS update installed successfully'
            };

        } catch (error) {
            return {
                success: false,
                error: error.message
            };
        } finally {
            await fs.remove(extractDir);
        }
    }

    async installHooksUpdate(updateFile, options = {}) {
        // Update CMS hooks
        const extractDir = path.join(this.configDir, 'temp', 'hooks-extract');
        await fs.ensureDir(extractDir);

        try {
            await this.execCommand(`tar -xzf "${updateFile}" -C "${extractDir}"`);

            // Update hooks in services directory
            const hooksSource = path.join(extractDir, 'hooks');
            const hooksTarget = path.join(__dirname, 'hooks');

            if (await fs.pathExists(hooksSource)) {
                await fs.copy(hooksSource, hooksTarget, { overwrite: true });
            }

            return {
                success: true,
                message: 'Hooks update installed successfully'
            };

        } catch (error) {
            return {
                success: false,
                error: error.message
            };
        } finally {
            await fs.remove(extractDir);
        }
    }

    async execCommand(command) {
        return new Promise((resolve) => {
            exec(command, (error, stdout, stderr) => {
                resolve({
                    code: error ? error.code || 1 : 0,
                    stdout,
                    stderr,
                    error: error?.message
                });
            });
        });
    }

    async createBackup(type) {
        const backupId = `backup_${type}_${Date.now()}`;
        const backupPath = path.join(this.backupDir, backupId);

        await fs.ensureDir(backupPath);

        switch (type) {
            case 'client':
                // Backup current application
                if (os.platform() === 'darwin') {
                    await fs.copy('/Applications/FlexPBX Desktop.app', path.join(backupPath, 'FlexPBX Desktop.app'));
                }
                break;
            case 'server':
                // Backup server configuration
                await fs.copy('/etc/flexpbx', path.join(backupPath, 'config'));
                break;
            // Add other backup types as needed
        }

        console.log(`💾 Created backup: ${backupId}`);
        return backupId;
    }

    async scheduleRestart(type) {
        console.log(`🔄 Scheduling restart for ${type}...`);

        // Different restart strategies based on type
        switch (type) {
            case 'client':
                // Schedule app restart
                setTimeout(() => {
                    console.log('🔄 Restarting FlexPBX Desktop...');
                    // In production, this would restart the Electron app
                    this.emit('restart-required', { type: 'client' });
                }, 5000);
                break;
            case 'server':
                // Restart server services
                await this.execCommand('sudo systemctl restart flexpbx-server');
                break;
            case 'copyparty':
                // Restart CopyParty service
                this.emit('restart-required', { type: 'copyparty' });
                break;
        }
    }

    async recordUpdate(updateId, updateInfo, installResult) {
        const record = {
            updateId,
            type: updateInfo.type,
            name: updateInfo.name,
            version: updateInfo.latestVersion,
            previousVersion: updateInfo.currentVersion,
            installedAt: new Date().toISOString(),
            success: installResult.success,
            platform: os.platform(),
            arch: os.arch()
        };

        this.updateHistory.set(updateId, record);
        await this.saveUpdateHistory();
    }

    async getUpdateInfo(updateId) {
        // In production, this would store update metadata
        return {
            type: 'client',
            name: 'FlexPBX Desktop',
            latestVersion: '2.1.0',
            currentVersion: '2.0.0',
            restart: true
        };
    }

    async updateRemoteClients(clientIds = [], updateType = 'client', options = {}) {
        const {
            channel = 'stable',
            silent = true,
            staggered = true,
            maxConcurrent = 3
        } = options;

        console.log(`🌐 Starting remote update for ${clientIds.length} clients`);

        const results = [];
        const batches = staggered ? this.createBatches(clientIds, maxConcurrent) : [clientIds];

        for (const batch of batches) {
            const batchPromises = batch.map(clientId =>
                this.updateRemoteClient(clientId, updateType, { channel, silent })
            );

            const batchResults = await Promise.allSettled(batchPromises);
            results.push(...batchResults.map((result, index) => ({
                clientId: batch[index],
                success: result.status === 'fulfilled',
                result: result.status === 'fulfilled' ? result.value : result.reason
            })));

            // Wait between batches if staggered
            if (staggered && batches.indexOf(batch) < batches.length - 1) {
                await new Promise(resolve => setTimeout(resolve, 30000)); // 30 second delay
            }
        }

        return {
            success: true,
            updated: results.filter(r => r.success).length,
            failed: results.filter(r => !r.success).length,
            results
        };
    }

    async updateRemoteClient(clientId, updateType, options = {}) {
        const { channel = 'stable', silent = true } = options;

        // This would use SSH or other remote communication to trigger updates
        console.log(`🔄 Updating remote client ${clientId}: ${updateType}`);

        // Mock remote update - in production would use SSH/API
        return new Promise((resolve) => {
            setTimeout(() => {
                resolve({
                    success: true,
                    clientId,
                    updateType,
                    message: 'Remote update completed successfully'
                });
            }, Math.random() * 10000 + 5000); // 5-15 second mock update time
        });
    }

    createBatches(items, batchSize) {
        const batches = [];
        for (let i = 0; i < items.length; i += batchSize) {
            batches.push(items.slice(i, i + batchSize));
        }
        return batches;
    }

    getStatus() {
        return {
            updateTypes: Object.keys(this.updateTypes),
            channels: Object.keys(this.updateChannels),
            updateHistory: Array.from(this.updateHistory.values()),
            remoteClients: this.remoteClients.size,
            queuedUpdates: this.updateQueue.size,
            features: {
                silentUpdates: true,
                remoteUpdates: true,
                checksumVerification: true,
                backgroundDownloads: true,
                staggeredRollouts: true,
                automaticBackups: true
            }
        };
    }
}

module.exports = SoftwareUpdateService;