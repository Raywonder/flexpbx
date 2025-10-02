const { NodeSSH } = require('node-ssh');
const FTP = require('basic-ftp');
const { createClient } = require('webdav');
const fs = require('fs-extra');
const path = require('path');
const { EventEmitter } = require('events');

class FileUploadService extends EventEmitter {
    constructor() {
        super();
        this.activeUploads = new Map();
    }

    async uploadFiles(config) {
        const {
            files,
            destination,
            server,
            localMode,
            method = 'ssh',
            onProgress
        } = config;

        const uploadId = `upload-${Date.now()}`;

        try {
            if (localMode) {
                return await this.uploadToLocal(files, destination, uploadId, onProgress);
            } else {
                return await this.uploadToRemote(files, destination, server, method, uploadId, onProgress);
            }
        } catch (error) {
            this.emit('upload-error', { uploadId, error: error.message });
            return {
                success: false,
                error: error.message,
                uploadId
            };
        }
    }

    async uploadToLocal(files, destination, uploadId, onProgress) {
        let uploadedCount = 0;
        const totalFiles = files.length;

        for (const file of files) {
            const fileName = path.basename(file);
            const destPath = path.join(destination, fileName);

            try {
                await fs.copy(file, destPath);
                uploadedCount++;

                if (onProgress) {
                    onProgress({
                        uploadId,
                        fileName,
                        progress: (uploadedCount / totalFiles) * 100,
                        status: 'completed'
                    });
                }

                this.emit('file-uploaded', {
                    uploadId,
                    fileName,
                    localPath: file,
                    remotePath: destPath
                });

            } catch (error) {
                this.emit('file-error', {
                    uploadId,
                    fileName,
                    error: error.message
                });
            }
        }

        return {
            success: true,
            uploadId,
            filesUploaded: uploadedCount,
            totalFiles,
            destination
        };
    }

    async uploadToRemote(files, destination, server, method, uploadId, onProgress) {
        switch (method) {
            case 'ssh':
            case 'sftp':
                return await this.uploadViaSSH(files, destination, server, uploadId, onProgress);
            case 'ftp':
                return await this.uploadViaFTP(files, destination, server, uploadId, onProgress);
            case 'webdav':
                return await this.uploadViaWebDAV(files, destination, server, uploadId, onProgress);
            default:
                throw new Error(`Unsupported upload method: ${method}`);
        }
    }

    async uploadViaSSH(files, destination, server, uploadId, onProgress) {
        const ssh = new NodeSSH();

        try {
            await ssh.connect({
                host: server.host,
                username: server.username,
                password: server.password,
                privateKey: server.privateKeyPath ?
                    await fs.readFile(server.privateKeyPath, 'utf8') : undefined,
                port: server.port || 22
            });

            // Ensure destination directory exists
            await ssh.execCommand(`mkdir -p ${destination}`);

            let uploadedCount = 0;
            const totalFiles = files.length;

            for (const file of files) {
                const fileName = path.basename(file);
                const remotePath = path.posix.join(destination, fileName);

                try {
                    await ssh.putFile(file, remotePath);
                    uploadedCount++;

                    if (onProgress) {
                        onProgress({
                            uploadId,
                            fileName,
                            progress: (uploadedCount / totalFiles) * 100,
                            status: 'completed'
                        });
                    }

                    this.emit('file-uploaded', {
                        uploadId,
                        fileName,
                        localPath: file,
                        remotePath
                    });

                } catch (error) {
                    this.emit('file-error', {
                        uploadId,
                        fileName,
                        error: error.message
                    });
                }
            }

            await ssh.dispose();

            return {
                success: true,
                uploadId,
                filesUploaded: uploadedCount,
                totalFiles,
                destination,
                method: 'ssh'
            };

        } catch (error) {
            await ssh.dispose();
            throw error;
        }
    }

    async uploadViaFTP(files, destination, server, uploadId, onProgress) {
        const client = new FTP.Client();

        try {
            await client.access({
                host: server.host,
                port: server.port || 21,
                user: server.username,
                password: server.password,
                secure: server.secure || false
            });

            // Ensure destination directory exists
            await client.ensureDir(destination);

            let uploadedCount = 0;
            const totalFiles = files.length;

            for (const file of files) {
                const fileName = path.basename(file);
                const remotePath = path.posix.join(destination, fileName);

                try {
                    await client.uploadFrom(file, remotePath);
                    uploadedCount++;

                    if (onProgress) {
                        onProgress({
                            uploadId,
                            fileName,
                            progress: (uploadedCount / totalFiles) * 100,
                            status: 'completed'
                        });
                    }

                    this.emit('file-uploaded', {
                        uploadId,
                        fileName,
                        localPath: file,
                        remotePath
                    });

                } catch (error) {
                    this.emit('file-error', {
                        uploadId,
                        fileName,
                        error: error.message
                    });
                }
            }

            client.close();

            return {
                success: true,
                uploadId,
                filesUploaded: uploadedCount,
                totalFiles,
                destination,
                method: 'ftp'
            };

        } catch (error) {
            client.close();
            throw error;
        }
    }

    async uploadViaWebDAV(files, destination, server, uploadId, onProgress) {
        const client = createClient(
            server.url,
            {
                username: server.username,
                password: server.password
            }
        );

        try {
            // Ensure destination directory exists
            await client.createDirectory(destination, { recursive: true });

            let uploadedCount = 0;
            const totalFiles = files.length;

            for (const file of files) {
                const fileName = path.basename(file);
                const remotePath = path.posix.join(destination, fileName);

                try {
                    const fileStream = fs.createReadStream(file);
                    await client.putFileContents(remotePath, fileStream);
                    uploadedCount++;

                    if (onProgress) {
                        onProgress({
                            uploadId,
                            fileName,
                            progress: (uploadedCount / totalFiles) * 100,
                            status: 'completed'
                        });
                    }

                    this.emit('file-uploaded', {
                        uploadId,
                        fileName,
                        localPath: file,
                        remotePath
                    });

                } catch (error) {
                    this.emit('file-error', {
                        uploadId,
                        fileName,
                        error: error.message
                    });
                }
            }

            return {
                success: true,
                uploadId,
                filesUploaded: uploadedCount,
                totalFiles,
                destination,
                method: 'webdav'
            };

        } catch (error) {
            throw error;
        }
    }

    // Batch upload multiple sets of files
    async batchUpload(uploadConfigs) {
        const results = [];

        for (const config of uploadConfigs) {
            try {
                const result = await this.uploadFiles(config);
                results.push(result);
            } catch (error) {
                results.push({
                    success: false,
                    error: error.message,
                    config
                });
            }
        }

        return results;
    }

    // Upload directory recursively
    async uploadDirectory(localDir, remoteDir, server, method = 'ssh') {
        const files = await this.getDirectoryFiles(localDir);

        const uploadPromises = files.map(async (file) => {
            const relativePath = path.relative(localDir, file);
            const remoteFilePath = path.posix.join(remoteDir, relativePath);
            const remoteFileDir = path.dirname(remoteFilePath);

            return this.uploadFiles({
                files: [file],
                destination: remoteFileDir,
                server,
                method,
                localMode: false
            });
        });

        const results = await Promise.allSettled(uploadPromises);

        return {
            success: results.every(r => r.status === 'fulfilled' && r.value.success),
            results: results.map(r => r.status === 'fulfilled' ? r.value : { error: r.reason }),
            totalFiles: files.length
        };
    }

    async getDirectoryFiles(dirPath) {
        const files = [];

        const items = await fs.readdir(dirPath);

        for (const item of items) {
            const itemPath = path.join(dirPath, item);
            const stats = await fs.stat(itemPath);

            if (stats.isFile()) {
                files.push(itemPath);
            } else if (stats.isDirectory()) {
                const subFiles = await this.getDirectoryFiles(itemPath);
                files.push(...subFiles);
            }
        }

        return files;
    }

    // Download files from remote server
    async downloadFiles(config) {
        const {
            files,
            localDestination,
            server,
            method = 'ssh'
        } = config;

        switch (method) {
            case 'ssh':
            case 'sftp':
                return await this.downloadViaSSH(files, localDestination, server);
            case 'ftp':
                return await this.downloadViaFTP(files, localDestination, server);
            case 'webdav':
                return await this.downloadViaWebDAV(files, localDestination, server);
            default:
                throw new Error(`Unsupported download method: ${method}`);
        }
    }

    async downloadViaSSH(files, localDestination, server) {
        const ssh = new NodeSSH();

        try {
            await ssh.connect({
                host: server.host,
                username: server.username,
                password: server.password,
                privateKey: server.privateKeyPath ?
                    await fs.readFile(server.privateKeyPath, 'utf8') : undefined,
                port: server.port || 22
            });

            await fs.ensureDir(localDestination);

            let downloadedCount = 0;

            for (const remoteFile of files) {
                const fileName = path.basename(remoteFile);
                const localPath = path.join(localDestination, fileName);

                try {
                    await ssh.getFile(localPath, remoteFile);
                    downloadedCount++;

                    this.emit('file-downloaded', {
                        fileName,
                        remotePath: remoteFile,
                        localPath
                    });

                } catch (error) {
                    this.emit('download-error', {
                        fileName,
                        error: error.message
                    });
                }
            }

            await ssh.dispose();

            return {
                success: true,
                filesDownloaded: downloadedCount,
                totalFiles: files.length,
                localDestination
            };

        } catch (error) {
            await ssh.dispose();
            throw error;
        }
    }

    async downloadViaFTP(files, localDestination, server) {
        const client = new FTP.Client();

        try {
            await client.access({
                host: server.host,
                port: server.port || 21,
                user: server.username,
                password: server.password,
                secure: server.secure || false
            });

            await fs.ensureDir(localDestination);

            let downloadedCount = 0;

            for (const remoteFile of files) {
                const fileName = path.basename(remoteFile);
                const localPath = path.join(localDestination, fileName);

                try {
                    await client.downloadTo(localPath, remoteFile);
                    downloadedCount++;

                    this.emit('file-downloaded', {
                        fileName,
                        remotePath: remoteFile,
                        localPath
                    });

                } catch (error) {
                    this.emit('download-error', {
                        fileName,
                        error: error.message
                    });
                }
            }

            client.close();

            return {
                success: true,
                filesDownloaded: downloadedCount,
                totalFiles: files.length,
                localDestination
            };

        } catch (error) {
            client.close();
            throw error;
        }
    }

    async downloadViaWebDAV(files, localDestination, server) {
        const client = createClient(
            server.url,
            {
                username: server.username,
                password: server.password
            }
        );

        try {
            await fs.ensureDir(localDestination);

            let downloadedCount = 0;

            for (const remoteFile of files) {
                const fileName = path.basename(remoteFile);
                const localPath = path.join(localDestination, fileName);

                try {
                    const fileContents = await client.getFileContents(remoteFile);
                    await fs.writeFile(localPath, fileContents);
                    downloadedCount++;

                    this.emit('file-downloaded', {
                        fileName,
                        remotePath: remoteFile,
                        localPath
                    });

                } catch (error) {
                    this.emit('download-error', {
                        fileName,
                        error: error.message
                    });
                }
            }

            return {
                success: true,
                filesDownloaded: downloadedCount,
                totalFiles: files.length,
                localDestination
            };

        } catch (error) {
            throw error;
        }
    }

    // Get upload progress
    getUploadProgress(uploadId) {
        return this.activeUploads.get(uploadId) || null;
    }

    // Cancel upload
    cancelUpload(uploadId) {
        if (this.activeUploads.has(uploadId)) {
            this.activeUploads.delete(uploadId);
            this.emit('upload-cancelled', { uploadId });
            return true;
        }
        return false;
    }

    // Cleanup old uploads
    cleanup() {
        this.activeUploads.clear();
        this.removeAllListeners();
    }
}

module.exports = FileUploadService;