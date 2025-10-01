const { exec, spawn } = require('child_process');
const fs = require('fs-extra');
const path = require('path');
const os = require('os');
const which = require('which');
const sudo = require('sudo-prompt');
const axios = require('axios');

class AutoInstaller {
    constructor() {
        this.platform = os.platform();
        this.missingTools = [];
        this.installedTools = [];
    }

    async checkAndInstallDependencies() {
        console.log('🔍 Checking system dependencies...');

        const dependencies = [
            { name: 'docker', check: 'docker --version', installer: this.installDocker.bind(this) },
            { name: 'docker-compose', check: 'docker-compose --version', installer: this.installDockerCompose.bind(this) },
            { name: 'asterisk', check: 'asterisk -V', installer: this.installAsterisk.bind(this) },
            { name: 'nginx', check: 'nginx -v', installer: this.installNginx.bind(this) },
            { name: 'git', check: 'git --version', installer: this.installGit.bind(this) },
            { name: 'node', check: 'node --version', installer: this.installNode.bind(this) },
            { name: 'npm', check: 'npm --version', installer: this.installNpm.bind(this) }
        ];

        for (const dep of dependencies) {
            const isInstalled = await this.checkTool(dep.check);
            if (!isInstalled) {
                this.missingTools.push(dep.name);
                console.log(`❌ ${dep.name} not found`);

                const shouldInstall = await this.promptInstall(dep.name);
                if (shouldInstall) {
                    await dep.installer();
                    this.installedTools.push(dep.name);
                }
            } else {
                console.log(`✅ ${dep.name} is installed`);
            }
        }

        return {
            missingTools: this.missingTools,
            installedTools: this.installedTools,
            success: this.missingTools.length === 0 || this.installedTools.length === this.missingTools.length
        };
    }

    async checkTool(command) {
        return new Promise((resolve) => {
            exec(command, (error) => {
                resolve(!error);
            });
        });
    }

    async promptInstall(toolName) {
        // In production, this would show a dialog to the user
        // For now, return true to auto-install
        return true;
    }

    async installDocker() {
        console.log('📦 Installing Docker...');

        switch (this.platform) {
            case 'darwin':
                return this.installDockerMac();
            case 'linux':
                return this.installDockerLinux();
            case 'win32':
                return this.installDockerWindows();
            default:
                throw new Error(`Unsupported platform: ${this.platform}`);
        }
    }

    async installDockerMac() {
        const script = `
            if ! command -v brew &> /dev/null; then
                /bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"
            fi
            brew install --cask docker
            open /Applications/Docker.app
        `;

        return this.executeSudo(script, 'Installing Docker for macOS');
    }

    async installDockerLinux() {
        const script = `
            # Update package index
            apt-get update -y || yum update -y

            # Install Docker
            if command -v apt-get &> /dev/null; then
                # Debian/Ubuntu
                apt-get install -y docker.io docker-compose
                systemctl start docker
                systemctl enable docker
            elif command -v yum &> /dev/null; then
                # RHEL/CentOS
                yum install -y docker docker-compose
                systemctl start docker
                systemctl enable docker
            fi

            # Add current user to docker group
            usermod -aG docker $USER
        `;

        return this.executeSudo(script, 'Installing Docker for Linux');
    }

    async installDockerWindows() {
        console.log('Please download Docker Desktop from https://www.docker.com/products/docker-desktop');
        // Could automate with PowerShell script
        return false;
    }

    async installDockerCompose() {
        console.log('📦 Installing Docker Compose...');

        if (this.platform === 'linux') {
            const script = `
                curl -L "https://github.com/docker/compose/releases/download/v2.20.0/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
                chmod +x /usr/local/bin/docker-compose
            `;
            return this.executeSudo(script, 'Installing Docker Compose');
        }

        // Docker Desktop includes docker-compose
        return true;
    }

    async installAsterisk() {
        console.log('📦 Installing Asterisk...');

        if (this.platform === 'linux') {
            const script = `
                if command -v apt-get &> /dev/null; then
                    apt-get install -y asterisk asterisk-config
                elif command -v yum &> /dev/null; then
                    yum install -y asterisk asterisk-config
                fi
            `;
            return this.executeSudo(script, 'Installing Asterisk');
        }

        // For Mac/Windows, we'll use Docker container instead
        console.log('Asterisk will be installed via Docker container');
        return true;
    }

    async installNginx() {
        console.log('📦 Installing Nginx...');

        switch (this.platform) {
            case 'darwin':
                return this.executeSudo('brew install nginx', 'Installing Nginx');
            case 'linux':
                const script = `
                    if command -v apt-get &> /dev/null; then
                        apt-get install -y nginx
                    elif command -v yum &> /dev/null; then
                        yum install -y nginx
                    fi
                `;
                return this.executeSudo(script, 'Installing Nginx');
            default:
                console.log('Nginx will be installed via Docker container');
                return true;
        }
    }

    async installGit() {
        console.log('📦 Installing Git...');

        switch (this.platform) {
            case 'darwin':
                return this.executeSudo('brew install git', 'Installing Git');
            case 'linux':
                const script = `
                    if command -v apt-get &> /dev/null; then
                        apt-get install -y git
                    elif command -v yum &> /dev/null; then
                        yum install -y git
                    fi
                `;
                return this.executeSudo(script, 'Installing Git');
            default:
                console.log('Please install Git from https://git-scm.com/');
                return false;
        }
    }

    async installNode() {
        console.log('📦 Installing Node.js...');

        const nodeVersion = 'v18.18.0';
        const script = `
            curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.0/install.sh | bash
            export NVM_DIR="$HOME/.nvm"
            [ -s "$NVM_DIR/nvm.sh" ] && . "$NVM_DIR/nvm.sh"
            nvm install ${nodeVersion}
            nvm use ${nodeVersion}
            nvm alias default ${nodeVersion}
        `;

        return this.executeCommand(script, 'Installing Node.js');
    }

    async installNpm() {
        console.log('📦 NPM comes with Node.js');
        return true;
    }

    async executeSudo(command, name) {
        return new Promise((resolve, reject) => {
            const options = {
                name: name || 'FlexPBX Installer'
            };

            sudo.exec(command, options, (error, stdout, stderr) => {
                if (error) {
                    console.error(`Error: ${error}`);
                    reject(error);
                } else {
                    console.log(stdout);
                    resolve(true);
                }
            });
        });
    }

    async executeCommand(command, name) {
        return new Promise((resolve, reject) => {
            exec(command, (error, stdout, stderr) => {
                if (error) {
                    console.error(`Error executing ${name}: ${error}`);
                    reject(error);
                } else {
                    console.log(stdout);
                    resolve(true);
                }
            });
        });
    }

    async checkPBXComponents() {
        const components = {
            asterisk: await this.checkTool('asterisk -V'),
            freepbx: await this.checkFreePBX(),
            database: await this.checkDatabase(),
            webserver: await this.checkWebServer(),
            sipServer: await this.checkSIPServer()
        };

        return components;
    }

    async checkFreePBX() {
        // Check if FreePBX is installed
        const freepbxPath = '/var/www/html/freepbx';
        return fs.pathExists(freepbxPath);
    }

    async checkDatabase() {
        // Check for MySQL/MariaDB or PostgreSQL
        const mysql = await this.checkTool('mysql --version');
        const postgres = await this.checkTool('psql --version');
        return mysql || postgres;
    }

    async checkWebServer() {
        // Check for Apache or Nginx
        const apache = await this.checkTool('apache2 -v || httpd -v');
        const nginx = await this.checkTool('nginx -v');
        return apache || nginx;
    }

    async checkSIPServer() {
        // Check for SIP server components
        const kamailio = await this.checkTool('kamailio -v');
        const opensips = await this.checkTool('opensips -V');
        return kamailio || opensips;
    }

    async installPBXComponents() {
        console.log('🎯 Installing PBX components...');

        const components = await this.checkPBXComponents();

        if (!components.asterisk) {
            await this.installAsterisk();
        }

        if (!components.database) {
            await this.installMariaDB();
        }

        if (!components.webserver) {
            await this.installNginx();
        }

        if (!components.freepbx) {
            await this.installFreePBX();
        }

        return true;
    }

    async installMariaDB() {
        console.log('📦 Installing MariaDB...');

        const script = `
            if command -v apt-get &> /dev/null; then
                apt-get install -y mariadb-server mariadb-client
                systemctl start mariadb
                systemctl enable mariadb
            elif command -v yum &> /dev/null; then
                yum install -y mariadb-server mariadb
                systemctl start mariadb
                systemctl enable mariadb
            fi

            # Secure MariaDB installation
            mysql_secure_installation <<EOF
Y
password123
password123
Y
Y
Y
Y
EOF
        `;

        return this.executeSudo(script, 'Installing MariaDB');
    }

    async installFreePBX() {
        console.log('📦 Installing FreePBX...');

        const script = `
            cd /usr/src
            wget http://mirror.freepbx.org/modules/packages/freepbx/freepbx-16.0-latest.tgz
            tar xfz freepbx-16.0-latest.tgz
            cd freepbx
            ./start_asterisk start
            ./install -n
        `;

        return this.executeSudo(script, 'Installing FreePBX');
    }
}

module.exports = AutoInstaller;