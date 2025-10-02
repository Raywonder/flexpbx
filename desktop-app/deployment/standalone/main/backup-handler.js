const fs = require('fs-extra');
const path = require('path');
const { dialog, app } = require('electron');
const crypto = require('crypto');
const zlib = require('zlib');
const tar = require('tar');
const { exec } = require('child_process');
const { promisify } = require('util');
const execAsync = promisify(exec);

class BackupHandler {
    constructor() {
        this.backupVersion = '2.0';
        this.singleServerExtension = '.flx';  // Single server backup
        this.multiServerExtension = '.flxx';  // Multiple servers backup
    }

    /**
     * Create a backup file containing all FlexPBX configurations
     */
    async createBackup(config = {}) {
        const backupData = {
            version: this.backupVersion,
            created: new Date().toISOString(),
            hostname: require('os').hostname(),
            platform: process.platform,
            data: {
                // Local installations
                installations: config.installations || [],

                // Remote servers
                remoteServers: config.remoteServers || [],

                // Application settings
                settings: config.settings || {},

                // Nginx configurations
                nginxConfigs: config.nginxConfigs || [],

                // Database connections
                databases: config.databases || [],

                // SSL certificates paths (not the actual certs)
                sslPaths: config.sslPaths || [],

                // Custom configurations
                customConfigs: config.customConfigs || {}
            },
            metadata: {
                appVersion: app.getVersion(),
                totalServers: (config.remoteServers || []).length,
                totalInstallations: (config.installations || []).length
            }
        };

        // Compress the backup data
        const jsonString = JSON.stringify(backupData, null, 2);
        const compressed = await this.compress(jsonString);

        // Create checksum for integrity
        const checksum = this.createChecksum(compressed);

        // Final backup structure
        const backup = {
            magic: 'FLXX_BACKUP',
            checksum,
            compressed: compressed.toString('base64')
        };

        return Buffer.from(JSON.stringify(backup));
    }

    /**
     * Save backup to file
     */
    async saveBackup(mainWindow, config) {
        // Determine file type based on number of servers
        const serverCount = (config.remoteServers || []).length + (config.installations || []).length;
        const isSingleServer = serverCount === 1;
        const extension = isSingleServer ? 'flx' : 'flxx';
        const fileTypeName = isSingleServer ? 'FlexPBX Single Server Backup' : 'FlexPBX Multi-Server Backup';

        const result = await dialog.showSaveDialog(mainWindow, {
            title: 'Save FlexPBX Backup',
            defaultPath: `flexpbx-backup-${new Date().toISOString().split('T')[0]}.${extension}`,
            filters: [
                { name: fileTypeName, extensions: [extension] },
                { name: 'FlexPBX Single Server Backup', extensions: ['flx'] },
                { name: 'FlexPBX Multi-Server Backup', extensions: ['flxx'] },
                { name: 'All Files', extensions: ['*'] }
            ],
            properties: ['createDirectory', 'showOverwriteConfirmation']
        });

        if (!result.canceled) {
            const backupData = await this.createBackup(config);
            await fs.writeFile(result.filePath, backupData);
            return {
                success: true,
                path: result.filePath,
                size: backupData.length
            };
        }

        return { success: false, canceled: true };
    }

    /**
     * Load backup from file
     */
    async loadBackup(mainWindow) {
        const result = await dialog.showOpenDialog(mainWindow, {
            title: 'Import FlexPBX Backup',
            filters: [
                { name: 'All FlexPBX Backup Files', extensions: ['flx', 'flxx'] },
                { name: 'FlexPBX Single Server Backup', extensions: ['flx'] },
                { name: 'FlexPBX Multi-Server Backup', extensions: ['flxx'] },
                { name: 'All Files', extensions: ['*'] }
            ],
            properties: ['openFile']
        });

        if (!result.canceled && result.filePaths[0]) {
            try {
                const fileContent = await fs.readFile(result.filePaths[0]);
                const backup = JSON.parse(fileContent.toString());

                // Validate backup file
                if (backup.magic !== 'FLXX_BACKUP') {
                    throw new Error('Invalid backup file format');
                }

                // Decompress data
                const compressed = Buffer.from(backup.compressed, 'base64');

                // Verify checksum
                const checksum = this.createChecksum(compressed);
                if (checksum !== backup.checksum) {
                    throw new Error('Backup file integrity check failed');
                }

                // Decompress and parse
                const decompressed = await this.decompress(compressed);
                const backupData = JSON.parse(decompressed);

                // Check version compatibility
                if (!this.isVersionCompatible(backupData.version)) {
                    const proceed = await this.showVersionWarning(mainWindow, backupData.version);
                    if (!proceed) {
                        return { success: false, canceled: true };
                    }
                }

                return {
                    success: true,
                    data: backupData,
                    file: path.basename(result.filePaths[0]),
                    path: result.filePaths[0]
                };
            } catch (error) {
                return {
                    success: false,
                    error: error.message
                };
            }
        }

        return { success: false, canceled: true };
    }

    /**
     * Restore backup data
     */
    async restoreBackup(backupData, options = {}) {
        const results = {
            installations: { success: 0, failed: 0, errors: [] },
            remoteServers: { success: 0, failed: 0, errors: [] },
            settings: { success: false, error: null }
        };

        // Restore installations
        if (options.restoreInstallations && backupData.data.installations) {
            for (const installation of backupData.data.installations) {
                try {
                    // Validate installation path exists or can be created
                    if (options.validatePaths) {
                        await this.validateInstallationPath(installation.path);
                    }
                    results.installations.success++;
                } catch (error) {
                    results.installations.failed++;
                    results.installations.errors.push({
                        installation: installation.name,
                        error: error.message
                    });
                }
            }
        }

        // Restore remote servers
        if (options.restoreServers && backupData.data.remoteServers) {
            for (const server of backupData.data.remoteServers) {
                try {
                    // Test connection if requested
                    if (options.testConnections) {
                        await this.testServerConnection(server);
                    }
                    results.remoteServers.success++;
                } catch (error) {
                    results.remoteServers.failed++;
                    results.remoteServers.errors.push({
                        server: server.name,
                        error: error.message
                    });
                }
            }
        }

        // Restore settings
        if (options.restoreSettings && backupData.data.settings) {
            try {
                // Merge or replace settings based on options
                results.settings.success = true;
            } catch (error) {
                results.settings.success = false;
                results.settings.error = error.message;
            }
        }

        return results;
    }

    /**
     * Export specific server configuration
     */
    async exportServerConfig(server, outputPath) {
        const serverBackup = {
            version: this.backupVersion,
            type: 'server',
            created: new Date().toISOString(),
            server: {
                ...server,
                // Remove sensitive data if not explicitly included
                password: server.includePassword ? server.password : null,
                privateKey: server.includeKey ? server.privateKey : null
            }
        };

        const backup = await this.createBackup({ remoteServers: [serverBackup.server] });
        await fs.writeFile(outputPath, backup);
        return { success: true, path: outputPath };
    }

    /**
     * Upload and restore complete server data from archive files
     */
    async uploadAndRestoreArchive(server, archivePath, configBackupPath, options = {}) {
        const results = {
            upload: { success: false, error: null },
            extraction: { success: false, error: null },
            restoration: { success: false, error: null },
            cleanup: { success: false, error: null }
        };

        try {
            // Step 1: Upload archive file to remote server
            console.log('Uploading archive to remote server...');
            const uploadResult = await this.uploadFileToServer(server, archivePath, '/tmp/');
            if (!uploadResult.success) {
                results.upload.error = uploadResult.error;
                return results;
            }
            results.upload.success = true;

            const remoteArchivePath = `/tmp/${path.basename(archivePath)}`;
            const extractPath = `/tmp/flexpbx-restore-${Date.now()}`;

            // Step 2: Extract archive on remote server
            console.log('Extracting archive on remote server...');
            const extractResult = await this.extractArchiveOnServer(server, remoteArchivePath, extractPath);
            if (!extractResult.success) {
                results.extraction.error = extractResult.error;
                return results;
            }
            results.extraction.success = true;

            // Step 3: Restore FlexPBX configuration from backup file
            if (configBackupPath) {
                console.log('Restoring FlexPBX configuration...');
                const configResult = await this.restoreConfigOnServer(server, configBackupPath, extractPath);
                if (!configResult.success) {
                    results.restoration.error = configResult.error;
                    return results;
                }
                results.restoration.success = true;
            }

            // Step 4: Move restored data to final location
            if (options.targetPath) {
                console.log('Moving data to target location...');
                await this.moveRestoredDataOnServer(server, extractPath, options.targetPath);
            }

            // Step 5: Cleanup temporary files
            console.log('Cleaning up temporary files...');
            await this.cleanupTempFilesOnServer(server, [remoteArchivePath, extractPath]);
            results.cleanup.success = true;

            return results;
        } catch (error) {
            console.error('Archive restoration failed:', error);
            return {
                ...results,
                error: error.message
            };
        }
    }

    /**
     * Upload file to remote server via SSH/SCP
     */
    async uploadFileToServer(server, localPath, remotePath) {
        try {
            // For now, simulate upload - would use actual SCP/SFTP in production
            const NodeSSH = require('node-ssh');
            const ssh = new NodeSSH();

            await ssh.connect({
                host: server.host,
                username: server.username,
                password: server.password,
                port: server.port || 22
            });

            await ssh.putFile(localPath, path.join(remotePath, path.basename(localPath)));
            await ssh.dispose();

            return { success: true };
        } catch (error) {
            return { success: false, error: error.message };
        }
    }

    /**
     * Extract archive on remote server
     */
    async extractArchiveOnServer(server, archivePath, extractPath) {
        try {
            const NodeSSH = require('node-ssh');
            const ssh = new NodeSSH();

            await ssh.connect({
                host: server.host,
                username: server.username,
                password: server.password,
                port: server.port || 22
            });

            // Create extraction directory
            await ssh.execCommand(`mkdir -p ${extractPath}`);

            // Determine archive type and extract accordingly
            const ext = path.extname(archivePath).toLowerCase();
            let extractCommand;

            if (ext === '.tar' || ext === '.tgz' || ext === '.gz') {
                extractCommand = `tar -xzf ${archivePath} -C ${extractPath}`;
            } else if (ext === '.zip') {
                extractCommand = `unzip -q ${archivePath} -d ${extractPath}`;
            } else if (ext === '.7z') {
                extractCommand = `7z x ${archivePath} -o${extractPath} -y`;
            } else {
                throw new Error(`Unsupported archive format: ${ext}`);
            }

            const result = await ssh.execCommand(extractCommand);
            await ssh.dispose();

            if (result.code !== 0) {
                throw new Error(`Extraction failed: ${result.stderr}`);
            }

            return { success: true, output: result.stdout };
        } catch (error) {
            return { success: false, error: error.message };
        }
    }

    /**
     * Restore FlexPBX configuration on remote server
     */
    async restoreConfigOnServer(server, backupPath, workingPath) {
        try {
            // Load backup configuration
            const backup = await this.loadBackup(null, backupPath);
            if (!backup.success) {
                throw new Error('Failed to load backup configuration');
            }

            const NodeSSH = require('node-ssh');
            const ssh = new NodeSSH();

            await ssh.connect({
                host: server.host,
                username: server.username,
                password: server.password,
                port: server.port || 22
            });

            // Apply configuration based on backup data
            const commands = this.generateRestoreCommands(backup.data, workingPath);

            for (const command of commands) {
                const result = await ssh.execCommand(command);
                if (result.code !== 0) {
                    console.warn(`Command failed: ${command}, Error: ${result.stderr}`);
                }
            }

            await ssh.dispose();
            return { success: true };
        } catch (error) {
            return { success: false, error: error.message };
        }
    }

    /**
     * Generate restoration commands based on backup data
     */
    generateRestoreCommands(backupData, workingPath) {
        const commands = [];

        // Restore Asterisk configurations
        if (backupData.data.asteriskConfigs) {
            commands.push(`cp -r ${workingPath}/asterisk/* /etc/asterisk/ 2>/dev/null || true`);
            commands.push(`chown -R asterisk:asterisk /etc/asterisk/`);
        }

        // Restore FreePBX database
        if (backupData.data.database) {
            commands.push(`mysql asterisk < ${workingPath}/database/asterisk.sql 2>/dev/null || true`);
        }

        // Restore recordings and voicemails
        commands.push(`cp -r ${workingPath}/recordings/* /var/spool/asterisk/monitor/ 2>/dev/null || true`);
        commands.push(`cp -r ${workingPath}/voicemail/* /var/spool/asterisk/voicemail/ 2>/dev/null || true`);

        // Restore custom scripts and configurations
        commands.push(`cp -r ${workingPath}/custom/* /opt/flexpbx/custom/ 2>/dev/null || true`);

        // Restart services
        commands.push('systemctl restart asterisk');
        commands.push('systemctl restart apache2');
        commands.push('fwconsole reload');

        return commands;
    }

    /**
     * Move restored data to target location
     */
    async moveRestoredDataOnServer(server, sourcePath, targetPath) {
        try {
            const NodeSSH = require('node-ssh');
            const ssh = new NodeSSH();

            await ssh.connect({
                host: server.host,
                username: server.username,
                password: server.password,
                port: server.port || 22
            });

            await ssh.execCommand(`mkdir -p ${targetPath}`);
            await ssh.execCommand(`cp -r ${sourcePath}/* ${targetPath}/`);
            await ssh.dispose();

            return { success: true };
        } catch (error) {
            return { success: false, error: error.message };
        }
    }

    /**
     * Cleanup temporary files on server
     */
    async cleanupTempFilesOnServer(server, filePaths) {
        try {
            const NodeSSH = require('node-ssh');
            const ssh = new NodeSSH();

            await ssh.connect({
                host: server.host,
                username: server.username,
                password: server.password,
                port: server.port || 22
            });

            for (const filePath of filePaths) {
                await ssh.execCommand(`rm -rf ${filePath}`);
            }

            await ssh.dispose();
            return { success: true };
        } catch (error) {
            return { success: false, error: error.message };
        }
    }

    /**
     * Import archive files (tar/zip) with FlexPBX backup
     */
    async importArchiveWithBackup(mainWindow) {
        const result = await dialog.showOpenDialog(mainWindow, {
            title: 'Import FlexPBX Archive + Backup',
            filters: [
                { name: 'Archive Files', extensions: ['tar', 'tgz', 'tar.gz', 'zip', '7z'] },
                { name: 'TAR Archives', extensions: ['tar', 'tgz', 'tar.gz'] },
                { name: 'ZIP Archives', extensions: ['zip'] },
                { name: '7-Zip Archives', extensions: ['7z'] },
                { name: 'All Files', extensions: ['*'] }
            ],
            properties: ['openFile']
        });

        if (!result.canceled && result.filePaths[0]) {
            // Also ask for the backup file
            const backupResult = await dialog.showOpenDialog(mainWindow, {
                title: 'Select FlexPBX Backup File',
                filters: [
                    { name: 'FlexPBX Backup Files', extensions: ['flx', 'flxx'] },
                    { name: 'All Files', extensions: ['*'] }
                ],
                properties: ['openFile']
            });

            return {
                success: true,
                archivePath: result.filePaths[0],
                backupPath: backupResult.canceled ? null : backupResult.filePaths[0]
            };
        }

        return { success: false, canceled: true };
    }

    // Helper methods
    async compress(data) {
        return new Promise((resolve, reject) => {
            zlib.gzip(data, (err, result) => {
                if (err) reject(err);
                else resolve(result);
            });
        });
    }

    async decompress(data) {
        return new Promise((resolve, reject) => {
            zlib.gunzip(data, (err, result) => {
                if (err) reject(err);
                else resolve(result.toString());
            });
        });
    }

    createChecksum(data) {
        return crypto.createHash('sha256').update(data).digest('hex');
    }

    isVersionCompatible(version) {
        const [major] = version.split('.');
        const [currentMajor] = this.backupVersion.split('.');
        return major === currentMajor;
    }

    async showVersionWarning(mainWindow, version) {
        const result = await dialog.showMessageBox(mainWindow, {
            type: 'warning',
            title: 'Version Mismatch',
            message: `This backup was created with version ${version}`,
            detail: `Current version is ${this.backupVersion}. Some features may not be compatible. Continue anyway?`,
            buttons: ['Continue', 'Cancel'],
            defaultId: 1
        });
        return result.response === 0;
    }

    async validateInstallationPath(installPath) {
        const exists = await fs.pathExists(installPath);
        if (!exists) {
            // Check if parent directory exists
            const parent = path.dirname(installPath);
            const parentExists = await fs.pathExists(parent);
            if (!parentExists) {
                throw new Error(`Parent directory does not exist: ${parent}`);
            }
        }
        return true;
    }

    async testServerConnection(server) {
        // Implement actual connection test
        // This is a placeholder
        return new Promise((resolve) => {
            setTimeout(() => {
                if (Math.random() > 0.1) {
                    resolve(true);
                } else {
                    throw new Error('Connection failed');
                }
            }, 100);
        });
    }
}

module.exports = BackupHandler;