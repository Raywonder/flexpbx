const path = require('path');
const fs = require('fs-extra');
const os = require('os');
const { exec } = require('child_process');
const { promisify } = require('util');
const execAsync = promisify(exec);

class SSHKeyService {
    constructor() {
        this.sshDir = path.join(os.homedir(), '.ssh');
        this.configFile = path.join(this.sshDir, 'config');
        this.flexpbxConfigDir = path.join(os.homedir(), '.flexpbx');
        this.connectionsFile = path.join(this.flexpbxConfigDir, 'connections.json');
        this.init();
    }

    async init() {
        // Ensure SSH and FlexPBX directories exist
        await fs.ensureDir(this.sshDir);
        await fs.ensureDir(this.flexpbxConfigDir);

        // Set proper permissions for SSH directory
        await this.setSSHPermissions();
    }

    /**
     * Set proper SSH directory permissions
     */
    async setSSHPermissions() {
        try {
            // SSH directory should be 700 (rwx------)
            await fs.chmod(this.sshDir, 0o700);

            // Check for existing keys and set proper permissions
            const keyFiles = await fs.readdir(this.sshDir).catch(() => []);
            for (const file of keyFiles) {
                const filePath = path.join(this.sshDir, file);
                const stat = await fs.stat(filePath);

                if (stat.isFile()) {
                    if (file.endsWith('.pub')) {
                        // Public keys should be 644 (rw-r--r--)
                        await fs.chmod(filePath, 0o644);
                    } else if (!file.includes('.')) {
                        // Private keys should be 600 (rw-------)
                        await fs.chmod(filePath, 0o600);
                    }
                }
            }
        } catch (error) {
            console.warn('Could not set SSH permissions:', error.message);
        }
    }

    /**
     * Generate new SSH key pair
     */
    async generateSSHKey(options = {}) {
        const {
            keyName = 'flexpbx_id_rsa',
            keyType = 'rsa',
            keySize = 4096,
            comment = `FlexPBX-${os.hostname()}-${Date.now()}`,
            passphrase = ''
        } = options;

        const privateKeyPath = path.join(this.sshDir, keyName);
        const publicKeyPath = `${privateKeyPath}.pub`;

        // Check if key already exists
        if (await fs.pathExists(privateKeyPath)) {
            throw new Error(`SSH key already exists: ${keyName}`);
        }

        try {
            let keyGenCmd;
            if (keyType === 'ed25519') {
                keyGenCmd = `ssh-keygen -t ed25519 -C "${comment}" -f "${privateKeyPath}"`;
            } else {
                keyGenCmd = `ssh-keygen -t ${keyType} -b ${keySize} -C "${comment}" -f "${privateKeyPath}"`;
            }

            // Add passphrase if provided
            if (passphrase) {
                keyGenCmd += ` -N "${passphrase}"`;
            } else {
                keyGenCmd += ' -N ""';
            }

            await execAsync(keyGenCmd);

            // Set proper permissions
            await fs.chmod(privateKeyPath, 0o600);
            await fs.chmod(publicKeyPath, 0o644);

            // Read the generated public key
            const publicKey = await fs.readFile(publicKeyPath, 'utf8');

            return {
                privateKeyPath,
                publicKeyPath,
                publicKey: publicKey.trim(),
                keyName,
                keyType,
                comment
            };
        } catch (error) {
            throw new Error(`Failed to generate SSH key: ${error.message}`);
        }
    }

    /**
     * List available SSH keys
     */
    async listSSHKeys() {
        try {
            const keys = [];
            const files = await fs.readdir(this.sshDir).catch(() => []);

            for (const file of files) {
                if (!file.endsWith('.pub') && !file.includes('.')) {
                    const privateKeyPath = path.join(this.sshDir, file);
                    const publicKeyPath = `${privateKeyPath}.pub`;

                    // Check if both private and public key exist
                    if (await fs.pathExists(publicKeyPath)) {
                        const publicKey = await fs.readFile(publicKeyPath, 'utf8');
                        const keyInfo = this.parsePublicKey(publicKey);

                        keys.push({
                            keyName: file,
                            privateKeyPath,
                            publicKeyPath,
                            publicKey: publicKey.trim(),
                            ...keyInfo
                        });
                    }
                }
            }

            return keys;
        } catch (error) {
            return [];
        }
    }

    /**
     * Parse public key to extract type and comment
     */
    parsePublicKey(publicKey) {
        const parts = publicKey.trim().split(' ');
        return {
            keyType: parts[0] || 'unknown',
            comment: parts[2] || '',
            fingerprint: this.getKeyFingerprint(publicKey)
        };
    }

    /**
     * Get SSH key fingerprint
     */
    async getKeyFingerprint(publicKeyPath) {
        try {
            const result = await execAsync(`ssh-keygen -lf "${publicKeyPath}"`);
            return result.stdout.trim();
        } catch (error) {
            return 'Unknown fingerprint';
        }
    }

    /**
     * Add key to SSH agent
     */
    async addKeyToAgent(privateKeyPath, passphrase = '') {
        try {
            // Start ssh-agent if not running
            await execAsync('ssh-add -l').catch(async () => {
                await execAsync('eval "$(ssh-agent -s)"');
            });

            // Add key to agent
            if (passphrase) {
                // This would require expect or similar for interactive passphrase
                await execAsync(`ssh-add "${privateKeyPath}"`);
            } else {
                await execAsync(`ssh-add "${privateKeyPath}"`);
            }

            return true;
        } catch (error) {
            throw new Error(`Failed to add key to SSH agent: ${error.message}`);
        }
    }

    /**
     * Test SSH connection
     */
    async testSSHConnection(server, keyPath = null) {
        try {
            let sshCmd = `ssh -o BatchMode=yes -o ConnectTimeout=10 -o StrictHostKeyChecking=no`;

            if (keyPath) {
                sshCmd += ` -i "${keyPath}"`;
            }

            if (server.port && server.port !== 22) {
                sshCmd += ` -p ${server.port}`;
            }

            sshCmd += ` ${server.username}@${server.host} "echo 'SSH connection successful'"`;

            const result = await execAsync(sshCmd);
            return {
                success: true,
                message: result.stdout.trim()
            };
        } catch (error) {
            return {
                success: false,
                message: error.message
            };
        }
    }

    /**
     * Save server connection details
     */
    async saveConnection(connectionData) {
        try {
            let connections = {};

            // Load existing connections
            if (await fs.pathExists(this.connectionsFile)) {
                const data = await fs.readFile(this.connectionsFile, 'utf8');
                connections = JSON.parse(data);
            }

            // Generate connection ID
            const connectionId = `${connectionData.host}_${connectionData.username}_${Date.now()}`;

            // Encrypt sensitive data (simple example - in production use proper encryption)
            const connectionInfo = {
                id: connectionId,
                name: connectionData.name || `${connectionData.username}@${connectionData.host}`,
                host: connectionData.host,
                port: connectionData.port || 22,
                username: connectionData.username,
                authMethod: connectionData.authMethod || 'key', // 'key' or 'password'
                keyPath: connectionData.keyPath || null,
                lastConnected: null,
                created: new Date().toISOString(),
                tags: connectionData.tags || [],
                notes: connectionData.notes || ''
            };

            connections[connectionId] = connectionInfo;

            // Save connections file
            await fs.writeFile(this.connectionsFile, JSON.stringify(connections, null, 2));
            await fs.chmod(this.connectionsFile, 0o600); // Secure permissions

            return connectionInfo;
        } catch (error) {
            throw new Error(`Failed to save connection: ${error.message}`);
        }
    }

    /**
     * Load saved connections
     */
    async loadConnections() {
        try {
            if (await fs.pathExists(this.connectionsFile)) {
                const data = await fs.readFile(this.connectionsFile, 'utf8');
                return JSON.parse(data);
            }
            return {};
        } catch (error) {
            return {};
        }
    }

    /**
     * Update connection last connected time
     */
    async updateConnectionLastUsed(connectionId) {
        try {
            const connections = await this.loadConnections();
            if (connections[connectionId]) {
                connections[connectionId].lastConnected = new Date().toISOString();
                await fs.writeFile(this.connectionsFile, JSON.stringify(connections, null, 2));
            }
        } catch (error) {
            console.warn('Could not update connection last used:', error.message);
        }
    }

    /**
     * Delete connection
     */
    async deleteConnection(connectionId) {
        try {
            const connections = await this.loadConnections();
            if (connections[connectionId]) {
                delete connections[connectionId];
                await fs.writeFile(this.connectionsFile, JSON.stringify(connections, null, 2));
                return true;
            }
            return false;
        } catch (error) {
            throw new Error(`Failed to delete connection: ${error.message}`);
        }
    }

    /**
     * Add SSH config entry for server
     */
    async addSSHConfigEntry(server, keyPath) {
        try {
            const configEntry = `
# FlexPBX Server: ${server.name || server.host}
Host ${server.host}-flexpbx
    HostName ${server.host}
    User ${server.username}
    Port ${server.port || 22}
    IdentityFile ${keyPath}
    IdentitiesOnly yes
    StrictHostKeyChecking no
    UserKnownHostsFile /dev/null
    LogLevel ERROR

`;

            // Read existing config or create new
            let configContent = '';
            if (await fs.pathExists(this.configFile)) {
                configContent = await fs.readFile(this.configFile, 'utf8');
            }

            // Check if entry already exists
            if (!configContent.includes(`Host ${server.host}-flexpbx`)) {
                configContent += configEntry;
                await fs.writeFile(this.configFile, configContent);
                await fs.chmod(this.configFile, 0o600);
            }

            return `${server.host}-flexpbx`;
        } catch (error) {
            throw new Error(`Failed to add SSH config entry: ${error.message}`);
        }
    }

    /**
     * Install public key on remote server
     */
    async installPublicKey(server, publicKey, authMethod = 'password') {
        try {
            let sshCmd;

            if (authMethod === 'password') {
                // Use ssh-copy-id for password authentication
                sshCmd = `ssh-copy-id -o StrictHostKeyChecking=no`;
                if (server.port && server.port !== 22) {
                    sshCmd += ` -p ${server.port}`;
                }
                sshCmd += ` ${server.username}@${server.host}`;
            } else {
                // Manual installation via SSH
                const escapedKey = publicKey.replace(/"/g, '\\"');
                sshCmd = `ssh -o StrictHostKeyChecking=no`;
                if (server.port && server.port !== 22) {
                    sshCmd += ` -p ${server.port}`;
                }
                sshCmd += ` ${server.username}@${server.host} "mkdir -p ~/.ssh && echo '${escapedKey}' >> ~/.ssh/authorized_keys && chmod 700 ~/.ssh && chmod 600 ~/.ssh/authorized_keys"`;
            }

            await execAsync(sshCmd);
            return true;
        } catch (error) {
            throw new Error(`Failed to install public key: ${error.message}`);
        }
    }

    /**
     * Get connection recommendations
     */
    getConnectionRecommendations(server) {
        const recommendations = {
            keyType: 'ed25519', // Modern, secure, fast
            keySize: null, // ed25519 has fixed size
            authMethod: 'key',
            suggestions: [
                'Use ed25519 keys for better security and performance',
                'Disable password authentication after key setup',
                'Use non-standard SSH port (not 22) for security',
                'Set up fail2ban for brute force protection',
                'Use SSH key passphrases for additional security'
            ]
        };

        // Adjust recommendations based on server type
        if (server.serverType === 'shared') {
            recommendations.suggestions.push('Check with hosting provider about SSH key restrictions');
        } else if (server.serverType === 'vps' || server.serverType === 'dedicated') {
            recommendations.suggestions.push('Consider setting up SSH certificate authority');
        }

        return recommendations;
    }
}

module.exports = SSHKeyService;