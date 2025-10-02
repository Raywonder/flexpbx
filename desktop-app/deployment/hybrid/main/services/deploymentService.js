const { NodeSSH } = require('node-ssh');
const FTP = require('basic-ftp');
const { createClient } = require('webdav');
const fs = require('fs-extra');
const path = require('path');
const SSHService = require('./sshService');

class DeploymentService {
    constructor() {
        this.sshService = new SSHService();
        this.activeDeployments = new Map();
    }

    async deployToRemote(config) {
        const {
            method, // 'ssh', 'ftp', 'webdav'
            connectionConfig,
            deploymentConfig
        } = config;

        const deploymentId = `deploy-${Date.now()}`;

        try {
            let result;

            switch (method) {
                case 'ssh':
                    result = await this.deployViaSSH(deploymentId, connectionConfig, deploymentConfig);
                    break;
                case 'ftp':
                    result = await this.deployViaFTP(deploymentId, connectionConfig, deploymentConfig);
                    break;
                case 'webdav':
                    result = await this.deployViaWebDAV(deploymentId, connectionConfig, deploymentConfig);
                    break;
                default:
                    throw new Error(`Unsupported deployment method: ${method}`);
            }

            return {
                ...result,
                deploymentId,
                method
            };

        } catch (error) {
            return {
                success: false,
                error: error.message,
                deploymentId,
                method
            };
        } finally {
            this.activeDeployments.delete(deploymentId);
        }
    }

    async deployViaSSH(deploymentId, connectionConfig, deploymentConfig) {
        this.activeDeployments.set(deploymentId, { method: 'ssh', status: 'connecting' });

        try {
            // Use SSHService for deployment
            const result = await this.sshService.deployToRemote({
                connection: connectionConfig,
                ...deploymentConfig
            });

            this.activeDeployments.set(deploymentId, {
                method: 'ssh',
                status: result.success ? 'completed' : 'failed'
            });

            return result;

        } catch (error) {
            this.activeDeployments.set(deploymentId, { method: 'ssh', status: 'failed' });
            throw error;
        }
    }

    async deployViaFTP(deploymentId, connectionConfig, deploymentConfig) {
        this.activeDeployments.set(deploymentId, { method: 'ftp', status: 'connecting' });

        const client = new FTP.Client();

        try {
            // Connect to FTP server
            await client.access({
                host: connectionConfig.host,
                port: connectionConfig.port || 21,
                user: connectionConfig.username,
                password: connectionConfig.password,
                secure: connectionConfig.secure || false
            });

            this.activeDeployments.set(deploymentId, { method: 'ftp', status: 'uploading' });

            // Create deployment directory
            const { deploymentPath } = deploymentConfig;
            await this.ensureFTPDirectory(client, deploymentPath);

            // Upload project files
            const projectRoot = path.join(__dirname, '../../../../..');
            await this.uploadProjectViaFTP(client, projectRoot, deploymentPath);

            // Generate and upload configuration files
            await this.generateAndUploadConfigs(client, deploymentConfig);

            this.activeDeployments.set(deploymentId, { method: 'ftp', status: 'completed' });

            return {
                success: true,
                message: 'Files uploaded successfully via FTP',
                deploymentPath,
                note: 'Please run docker-compose up -d manually on the server to start services'
            };

        } catch (error) {
            this.activeDeployments.set(deploymentId, { method: 'ftp', status: 'failed' });
            throw error;
        } finally {
            client.close();
        }
    }

    async deployViaWebDAV(deploymentId, connectionConfig, deploymentConfig) {
        this.activeDeployments.set(deploymentId, { method: 'webdav', status: 'connecting' });

        const client = createClient(connectionConfig.url, {
            username: connectionConfig.username,
            password: connectionConfig.password
        });

        try {
            // Test connection
            await client.getDirectoryContents('/');

            this.activeDeployments.set(deploymentId, { method: 'webdav', status: 'uploading' });

            // Create deployment directory
            const { deploymentPath } = deploymentConfig;
            await this.ensureWebDAVDirectory(client, deploymentPath);

            // Upload project files
            const projectRoot = path.join(__dirname, '../../../../..');
            await this.uploadProjectViaWebDAV(client, projectRoot, deploymentPath);

            // Generate and upload configuration files
            await this.generateAndUploadConfigsWebDAV(client, deploymentConfig);

            this.activeDeployments.set(deploymentId, { method: 'webdav', status: 'completed' });

            return {
                success: true,
                message: 'Files uploaded successfully via WebDAV',
                deploymentPath,
                note: 'Please run docker-compose up -d manually on the server to start services'
            };

        } catch (error) {
            this.activeDeployments.set(deploymentId, { method: 'webdav', status: 'failed' });
            throw error;
        }
    }

    async ensureFTPDirectory(client, dirPath) {
        const dirs = dirPath.split('/').filter(d => d);
        let currentPath = '';

        for (const dir of dirs) {
            currentPath += '/' + dir;
            try {
                await client.cd(currentPath);
            } catch (error) {
                await client.ensureDir(currentPath);
            }
        }
    }

    async ensureWebDAVDirectory(client, dirPath) {
        try {
            await client.createDirectory(dirPath, { recursive: true });
        } catch (error) {
            // Directory might already exist
            if (!error.message.includes('exists')) {
                throw error;
            }
        }
    }

    async uploadProjectViaFTP(client, sourceDir, targetDir) {
        const filesToUpload = [
            'src',
            'public',
            'scripts',
            'package.json',
            'Dockerfile',
            '.env.example'
        ];

        for (const file of filesToUpload) {
            const sourcePath = path.join(sourceDir, file);

            if (await fs.pathExists(sourcePath)) {
                const stats = await fs.stat(sourcePath);
                const targetPath = `${targetDir}/${file}`;

                if (stats.isDirectory()) {
                    await this.uploadDirectoryViaFTP(client, sourcePath, targetPath);
                } else {
                    await client.uploadFrom(sourcePath, targetPath);
                }
            }
        }
    }

    async uploadDirectoryViaFTP(client, localDir, remoteDir) {
        await client.ensureDir(remoteDir);

        const items = await fs.readdir(localDir);

        for (const item of items) {
            const localPath = path.join(localDir, item);
            const remotePath = `${remoteDir}/${item}`;
            const stats = await fs.stat(localPath);

            if (stats.isDirectory()) {
                await this.uploadDirectoryViaFTP(client, localPath, remotePath);
            } else {
                await client.uploadFrom(localPath, remotePath);
            }
        }
    }

    async uploadProjectViaWebDAV(client, sourceDir, targetDir) {
        const filesToUpload = [
            'src',
            'public',
            'scripts',
            'package.json',
            'Dockerfile',
            '.env.example'
        ];

        for (const file of filesToUpload) {
            const sourcePath = path.join(sourceDir, file);

            if (await fs.pathExists(sourcePath)) {
                const stats = await fs.stat(sourcePath);
                const targetPath = `${targetDir}/${file}`;

                if (stats.isDirectory()) {
                    await this.uploadDirectoryViaWebDAV(client, sourcePath, targetPath);
                } else {
                    const content = await fs.readFile(sourcePath);
                    await client.putFileContents(targetPath, content);
                }
            }
        }
    }

    async uploadDirectoryViaWebDAV(client, localDir, remoteDir) {
        await client.createDirectory(remoteDir, { recursive: true });

        const items = await fs.readdir(localDir);

        for (const item of items) {
            const localPath = path.join(localDir, item);
            const remotePath = `${remoteDir}/${item}`;
            const stats = await fs.stat(localPath);

            if (stats.isDirectory()) {
                await this.uploadDirectoryViaWebDAV(client, localPath, remotePath);
            } else {
                const content = await fs.readFile(localPath);
                await client.putFileContents(remotePath, content);
            }
        }
    }

    async generateAndUploadConfigs(client, config) {
        const { deploymentPath, installationName, domain, httpPort, sipPort, databaseType, dbConfig } = config;

        // Generate docker-compose.yml
        const dockerCompose = this.generateDockerCompose({
            httpPort,
            sipPort,
            installationName,
            domain,
            databaseType,
            dbConfig
        });

        // Generate .env file
        const envContent = this.generateEnvFile({
            domain,
            httpPort,
            sipPort,
            installationName,
            databaseType,
            dbConfig
        });

        // Upload via FTP
        await client.uploadFrom(
            Buffer.from(dockerCompose),
            `${deploymentPath}/docker-compose.yml`
        );

        await client.uploadFrom(
            Buffer.from(envContent),
            `${deploymentPath}/.env`
        );
    }

    async generateAndUploadConfigsWebDAV(client, config) {
        const { deploymentPath, installationName, domain, httpPort, sipPort, databaseType, dbConfig } = config;

        // Generate docker-compose.yml
        const dockerCompose = this.generateDockerCompose({
            httpPort,
            sipPort,
            installationName,
            domain,
            databaseType,
            dbConfig
        });

        // Generate .env file
        const envContent = this.generateEnvFile({
            domain,
            httpPort,
            sipPort,
            installationName,
            databaseType,
            dbConfig
        });

        // Upload via WebDAV
        await client.putFileContents(
            `${deploymentPath}/docker-compose.yml`,
            dockerCompose
        );

        await client.putFileContents(
            `${deploymentPath}/.env`,
            envContent
        );
    }

    generateDockerCompose({ httpPort, sipPort, installationName, domain, databaseType, dbConfig }) {
        const containerName = installationName.toLowerCase().replace(/[^a-z0-9]/g, '-');
        let services = '';
        let volumes = '';
        let environment = '';

        // Database configuration
        if (databaseType === 'mysql' || databaseType === 'mariadb') {
            services += `
  database:
    image: ${databaseType === 'mysql' ? 'mysql:8.0' : 'mariadb:10.11'}
    container_name: ${containerName}-db
    restart: unless-stopped
    environment:
      - MYSQL_ROOT_PASSWORD=${dbConfig.rootPassword}
      - MYSQL_DATABASE=${dbConfig.database}
      - MYSQL_USER=${dbConfig.username}
      - MYSQL_PASSWORD=${dbConfig.password}
    volumes:
      - db_data:/var/lib/mysql
    networks:
      - flexpbx_network

`;
            environment = `
      - DB_TYPE=${databaseType}
      - DB_HOST=database
      - DB_PORT=3306
      - DB_NAME=${dbConfig.database}
      - DB_USER=${dbConfig.username}
      - DB_PASSWORD=${dbConfig.password}`;
            volumes += `
  db_data:`;
        } else if (databaseType === 'postgresql') {
            services += `
  database:
    image: postgres:15-alpine
    container_name: ${containerName}-db
    restart: unless-stopped
    environment:
      - POSTGRES_DB=${dbConfig.database}
      - POSTGRES_USER=${dbConfig.username}
      - POSTGRES_PASSWORD=${dbConfig.password}
    volumes:
      - db_data:/var/lib/postgresql/data
    networks:
      - flexpbx_network

`;
            environment = `
      - DB_TYPE=postgresql
      - DB_HOST=database
      - DB_PORT=5432
      - DB_NAME=${dbConfig.database}
      - DB_USER=${dbConfig.username}
      - DB_PASSWORD=${dbConfig.password}`;
            volumes += `
  db_data:`;
        } else {
            environment = `
      - DB_TYPE=sqlite
      - SQLITE_PATH=./data/flexpbx.sqlite`;
        }

        return `version: '3.8'

services:
  flexpbx:
    build: .
    container_name: ${containerName}
    restart: unless-stopped
    ports:
      - "${httpPort}:3000"
      - "${sipPort}:5060/udp"
      - "${sipPort}:5060/tcp"
      - "5061:5061/tcp"
      - "8088:8088"
      - "8089:8089"
      - "10000-20000:10000-20000/udp"
    environment:
      - NODE_ENV=production
      - PORT=3000
      - DOMAIN_NAME=${domain || 'localhost'}${environment}
      - ACCESSIBILITY_ENABLED=true
      - SCREEN_READER_SUPPORT=true
    volumes:
      - ./data:/app/data
      - ./logs:/app/logs
      - ./recordings:/app/recordings
      - ./voicemail:/app/voicemail
    networks:
      - flexpbx_network
${databaseType !== 'sqlite' ? '    depends_on:\n      - database' : ''}
${services}
  redis:
    image: redis:7-alpine
    container_name: ${containerName}-redis
    restart: unless-stopped
    command: redis-server --appendonly yes
    volumes:
      - redis_data:/data
    networks:
      - flexpbx_network

volumes:
  redis_data:${volumes}

networks:
  flexpbx_network:
    driver: bridge`;
    }

    generateEnvFile({ domain, httpPort, sipPort, installationName, databaseType, dbConfig }) {
        const jwtSecret = this.generateRandomString(64);
        const sessionSecret = this.generateRandomString(64);
        const adminPassword = this.generateRandomString(16);

        let dbEnvVars = '';
        if (databaseType === 'mysql' || databaseType === 'mariadb') {
            dbEnvVars = `
# Database Configuration (MySQL/MariaDB)
DB_TYPE=${databaseType}
DB_HOST=database
DB_PORT=3306
DB_NAME=${dbConfig.database}
DB_USER=${dbConfig.username}
DB_PASSWORD=${dbConfig.password}`;
        } else if (databaseType === 'postgresql') {
            dbEnvVars = `
# Database Configuration (PostgreSQL)
DB_TYPE=postgresql
DB_HOST=database
DB_PORT=5432
DB_NAME=${dbConfig.database}
DB_USER=${dbConfig.username}
DB_PASSWORD=${dbConfig.password}`;
        } else {
            dbEnvVars = `
# Database Configuration (SQLite)
DB_TYPE=sqlite
SQLITE_PATH=./data/flexpbx.sqlite`;
        }

        return `# FlexPBX Remote Installation Configuration
# Generated: ${new Date().toISOString()}

# Server Configuration
NODE_ENV=production
PORT=3000
DOMAIN_NAME=${domain || 'localhost'}
SSL_ENABLED=false
${dbEnvVars}

# Security
JWT_SECRET=${jwtSecret}
SESSION_SECRET=${sessionSecret}
DEFAULT_ADMIN_PASSWORD=${adminPassword}

# Network Configuration
SIP_UDP_PORT=${sipPort}
SIP_TCP_PORT=${sipPort}
HTTP_PORT=${httpPort}

# Accessibility Features
ACCESSIBILITY_ENABLED=true
SCREEN_READER_SUPPORT=true
AUDIO_FEEDBACK_ENABLED=true
VOICE_ANNOUNCEMENTS_ENABLED=true

# Installation Info
INSTALLATION_NAME=${installationName}
INSTALLATION_TYPE=remote
INSTALLATION_DATE=${new Date().toISOString()}

# Default Admin Credentials
DEFAULT_ADMIN_EXTENSION=1000
DEFAULT_ADMIN_PIN=9876`;
    }

    async getDeploymentStatus(deploymentId) {
        return this.activeDeployments.get(deploymentId) || { status: 'not_found' };
    }

    async cancelDeployment(deploymentId) {
        const deployment = this.activeDeployments.get(deploymentId);
        if (deployment) {
            deployment.status = 'cancelled';
            this.activeDeployments.set(deploymentId, deployment);
            return { success: true, message: 'Deployment cancelled' };
        }
        return { success: false, message: 'Deployment not found' };
    }

    generateRandomString(length) {
        const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        let result = '';
        for (let i = 0; i < length; i++) {
            result += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        return result;
    }
}

module.exports = DeploymentService;