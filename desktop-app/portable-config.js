#!/usr/bin/env node

/**
 * FlexPBX Portable Configuration Manager
 * Enables the entire system to run from a detachable drive
 */

const path = require('path');
const fs = require('fs-extra');
const os = require('os');

class PortableConfigManager {
    constructor() {
        this.portableMode = false;
        this.basePath = this.detectBasePath();
        this.configDir = null;
        this.dataDir = null;
        this.tempDir = null;

        this.initializePortableMode();
    }

    detectBasePath() {
        // Check if running from a detachable drive
        const currentDir = process.cwd();
        const possiblePortablePaths = [
            // macOS external drives
            '/Volumes/',
            // Windows external drives
            'D:\\', 'E:\\', 'F:\\', 'G:\\', 'H:\\',
            // Linux external mounts
            '/media/', '/mnt/', '/run/media/'
        ];

        for (const portablePath of possiblePortablePaths) {
            if (currentDir.startsWith(portablePath)) {
                console.log(`🔌 Detected portable drive: ${portablePath}`);
                this.portableMode = true;
                return this.findDriveRoot(currentDir);
            }
        }

        // Check for portable marker file
        let checkDir = currentDir;
        for (let i = 0; i < 10; i++) { // Check up to 10 levels up
            const markerFile = path.join(checkDir, '.flexpbx-portable');
            if (fs.existsSync(markerFile)) {
                console.log(`🔌 Portable marker found: ${markerFile}`);
                this.portableMode = true;
                return checkDir;
            }
            const parentDir = path.dirname(checkDir);
            if (parentDir === checkDir) break; // Reached root
            checkDir = parentDir;
        }

        return currentDir;
    }

    findDriveRoot(currentPath) {
        // Find the root of the external drive
        const parts = currentPath.split(path.sep);

        if (process.platform === 'darwin' && currentPath.startsWith('/Volumes/')) {
            return path.join('/', 'Volumes', parts[2]);
        }

        if (process.platform === 'win32' && /^[A-Z]:\\/.test(currentPath)) {
            return parts[0] + path.sep;
        }

        if (process.platform === 'linux') {
            if (currentPath.startsWith('/media/')) {
                return path.join('/', 'media', parts[2], parts[3] || '');
            }
            if (currentPath.startsWith('/mnt/')) {
                return path.join('/', 'mnt', parts[2]);
            }
        }

        return currentPath;
    }

    async initializePortableMode() {
        if (this.portableMode) {
            console.log(`🚀 Initializing FlexPBX in portable mode from: ${this.basePath}`);

            // Set up portable directories
            this.configDir = path.join(this.basePath, 'FlexPBX', 'config');
            this.dataDir = path.join(this.basePath, 'FlexPBX', 'data');
            this.tempDir = path.join(this.basePath, 'FlexPBX', 'temp');

            // Create portable marker
            const markerFile = path.join(this.basePath, '.flexpbx-portable');
            await fs.writeFile(markerFile, JSON.stringify({
                version: '2.0.0',
                created: new Date().toISOString(),
                platform: process.platform,
                arch: process.arch
            }, null, 2));

        } else {
            console.log(`🏠 Running FlexPBX in standard mode`);

            // Use standard system directories
            this.configDir = path.join(os.homedir(), '.flexpbx');
            this.dataDir = path.join(os.homedir(), '.flexpbx', 'data');
            this.tempDir = os.tmpdir();
        }

        // Ensure all directories exist
        await fs.ensureDir(this.configDir);
        await fs.ensureDir(this.dataDir);
        await fs.ensureDir(this.tempDir);

        console.log(`📁 Config directory: ${this.configDir}`);
        console.log(`📁 Data directory: ${this.dataDir}`);
        console.log(`📁 Temp directory: ${this.tempDir}`);

        // Create portable configuration
        await this.createPortableConfig();
    }

    async createPortableConfig() {
        const configFile = path.join(this.configDir, 'portable-config.json');

        const config = {
            portable: this.portableMode,
            basePath: this.basePath,
            directories: {
                config: this.configDir,
                data: this.dataDir,
                temp: this.tempDir,
                logs: path.join(this.dataDir, 'logs'),
                cache: path.join(this.dataDir, 'cache'),
                messages: path.join(this.dataDir, 'messages'),
                copyparty: path.join(this.dataDir, 'copyparty'),
                dns: path.join(this.dataDir, 'dns'),
                accessibility: path.join(this.dataDir, 'accessibility'),
                updates: path.join(this.dataDir, 'updates')
            },
            services: {
                copyparty: {
                    port: 8080,
                    dataPath: path.join(this.dataDir, 'copyparty'),
                    configPath: path.join(this.configDir, 'copyparty')
                },
                messaging: {
                    port: 41238,
                    dataPath: path.join(this.dataDir, 'messages'),
                    encryption: true
                },
                accessibility: {
                    port: 41237,
                    dataPath: path.join(this.dataDir, 'accessibility'),
                    audioEnabled: true
                },
                dns: {
                    configPath: path.join(this.configDir, 'dns'),
                    zonesPath: path.join(this.dataDir, 'dns', 'zones')
                },
                updates: {
                    cachePath: path.join(this.dataDir, 'updates'),
                    autoUpdate: !this.portableMode // Disable auto-updates in portable mode
                }
            },
            platform: {
                os: process.platform,
                arch: process.arch,
                version: process.version
            },
            created: new Date().toISOString(),
            lastUpdated: new Date().toISOString()
        };

        await fs.writeJson(configFile, config, { spaces: 2 });

        // Create all required directories
        for (const [name, dirPath] of Object.entries(config.directories)) {
            await fs.ensureDir(dirPath);
            console.log(`📁 Created directory: ${name} -> ${dirPath}`);
        }

        return config;
    }

    async createPortableLauncher() {
        const launcherContent = this.portableMode ? this.createDetachableLauncher() : this.createStandardLauncher();

        // Create platform-specific launchers
        if (process.platform === 'win32') {
            const batchFile = path.join(this.basePath, 'FlexPBX-Portable.bat');
            await fs.writeFile(batchFile, this.createWindowsBatchLauncher());
            console.log(`🚀 Windows launcher created: ${batchFile}`);
        }

        if (process.platform === 'darwin') {
            const scriptFile = path.join(this.basePath, 'FlexPBX-Portable.command');
            await fs.writeFile(scriptFile, this.createMacOSLauncher());
            await fs.chmod(scriptFile, 0o755);
            console.log(`🚀 macOS launcher created: ${scriptFile}`);
        }

        if (process.platform === 'linux') {
            const scriptFile = path.join(this.basePath, 'FlexPBX-Portable.sh');
            await fs.writeFile(scriptFile, this.createLinuxLauncher());
            await fs.chmod(scriptFile, 0o755);
            console.log(`🚀 Linux launcher created: ${scriptFile}`);
        }

        // Create universal Node.js launcher
        const nodeLauncher = path.join(this.basePath, 'launch-portable.js');
        await fs.writeFile(nodeLauncher, this.createNodeLauncher());
        console.log(`🚀 Node.js launcher created: ${nodeLauncher}`);
    }

    createWindowsBatchLauncher() {
        return `@echo off
title FlexPBX Portable - Windows
echo 🚀 Starting FlexPBX Portable Edition...
echo 🔌 Running from: %~dp0

cd /d "%~dp0"
if exist "node_modules" (
    echo ✅ Dependencies found
) else (
    echo 📦 Installing dependencies...
    npm install --production
)

echo 🖥️  Starting FlexPBX Desktop Application...
npm start

pause
`;
    }

    createMacOSLauncher() {
        return `#!/bin/bash
echo "🚀 Starting FlexPBX Portable Edition for macOS..."
echo "🔌 Running from: $(dirname "$0")"

cd "$(dirname "$0")"

if [ -d "node_modules" ]; then
    echo "✅ Dependencies found"
else
    echo "📦 Installing dependencies..."
    npm install --production
fi

echo "🖥️  Starting FlexPBX Desktop Application..."
npm start

read -p "Press any key to continue..."
`;
    }

    createLinuxLauncher() {
        return `#!/bin/bash
echo "🚀 Starting FlexPBX Portable Edition for Linux..."
echo "🔌 Running from: $(dirname "$0")"

cd "$(dirname "$0")"

if [ -d "node_modules" ]; then
    echo "✅ Dependencies found"
else
    echo "📦 Installing dependencies..."
    npm install --production
fi

echo "🖥️  Starting FlexPBX Desktop Application..."
npm start

read -p "Press any key to continue..."
`;
    }

    createNodeLauncher() {
        return `#!/usr/bin/env node

/**
 * FlexPBX Portable Universal Launcher
 * Cross-platform launcher for detachable drive deployments
 */

const { spawn } = require('child_process');
const path = require('path');
const fs = require('fs');

console.log('🚀 FlexPBX Portable Universal Launcher');
console.log('🔌 Platform:', process.platform, process.arch);
console.log('📁 Working directory:', __dirname);

// Change to script directory
process.chdir(__dirname);

// Check for Node.js
const nodeVersion = process.version;
console.log('📦 Node.js version:', nodeVersion);

// Check for dependencies
if (!fs.existsSync('node_modules')) {
    console.log('📦 Installing dependencies...');
    const npm = spawn('npm', ['install', '--production'], {
        stdio: 'inherit',
        shell: true
    });

    npm.on('close', (code) => {
        if (code === 0) {
            startApplication();
        } else {
            console.error('❌ Failed to install dependencies');
            process.exit(1);
        }
    });
} else {
    console.log('✅ Dependencies found');
    startApplication();
}

function startApplication() {
    console.log('🖥️  Starting FlexPBX Desktop Application...');

    const app = spawn('npm', ['start'], {
        stdio: 'inherit',
        shell: true
    });

    app.on('close', (code) => {
        console.log(\`Application exited with code \${code}\`);
    });

    app.on('error', (error) => {
        console.error('❌ Failed to start application:', error);
    });
}
`;
    }

    async createAutorunConfig() {
        if (!this.portableMode) return;

        // Create Windows autorun.inf for USB drives
        const autorunContent = `[AutoRun]
label=FlexPBX Portable
icon=FlexPBX\\assets\\icon.ico
open=FlexPBX-Portable.bat
action=Start FlexPBX Portable Edition
`;

        const autorunFile = path.join(this.basePath, 'autorun.inf');
        await fs.writeFile(autorunFile, autorunContent);
        console.log(`🔄 Windows autorun.inf created: ${autorunFile}`);

        // Create macOS .DS_Store for custom folder icon (if desired)
        // This would require binary data for the .DS_Store file
    }

    async createReadme() {
        const readmeContent = `# FlexPBX Portable Edition

${this.portableMode ? '🔌 **Running in Portable Mode**' : '🏠 **Running in Standard Mode**'}

## System Information
- **Platform**: ${process.platform}
- **Architecture**: ${process.arch}
- **Node.js**: ${process.version}
- **Base Path**: ${this.basePath}

## Directory Structure
\`\`\`
${this.portableMode ? 'Detachable Drive' : 'System'}/
├── FlexPBX/
│   ├── config/          # Configuration files
│   ├── data/            # Application data
│   │   ├── messages/    # Rich messaging data
│   │   ├── accessibility/ # Accessibility data
│   │   ├── copyparty/   # File sharing data
│   │   ├── dns/         # DNS configuration
│   │   └── updates/     # Software updates cache
│   └── temp/            # Temporary files
${this.portableMode ? `├── launch-portable.js   # Universal launcher
├── FlexPBX-Portable.*   # Platform-specific launchers
└── .flexpbx-portable    # Portable mode marker` : ''}
\`\`\`

## Features Available

### 🔧 Core Services
- ✅ Rich Messaging with encryption
- ✅ Remote Accessibility Control (VoiceOver, NVDA, JAWS, etc.)
- ✅ Bidirectional Audio Streaming
- ✅ File Sharing with CopyParty
- ✅ DNS Management (BIND, PowerDNS, etc.)
- ✅ Software Update Management
- ✅ Cross-platform compatibility

### ♿ Accessibility Features
- ✅ Screen reader control (macOS VoiceOver, Windows NVDA/JAWS/Narrator, Linux Orca)
- ✅ Audio streaming for remote assistance
- ✅ AccessKit.dev integration (when available)
- ✅ RIM-like remote control functionality
- ✅ Auto-accept/decline feature permissions

### 🎵 Audio Controls
- ✅ Local and remote audio volume control
- ✅ Input/output mute functionality
- ✅ Audio crossfade mixer
- ✅ Audio device selection
- ✅ Noise suppression and compression
- ✅ Audio monitoring

### 💬 Rich Messaging
- ✅ Text, HTML, Markdown, and Code messages
- ✅ File sharing and media support
- ✅ Accessibility command integration
- ✅ Typing indicators and presence
- ✅ End-to-end encryption
- ✅ Offline message queue

## Quick Start

### On This System
${this.portableMode ? `
1. **Double-click the launcher**: \`FlexPBX-Portable.*\`
2. **Or run manually**: \`node launch-portable.js\`
3. **Or use npm**: \`npm start\`
` : `
1. **Run the application**: \`npm start\`
2. **Or use the system launcher**
`}

### On Another System
${this.portableMode ? `
1. **Plug in the detachable drive**
2. **Navigate to the FlexPBX folder**
3. **Run the appropriate launcher for your platform**:
   - Windows: \`FlexPBX-Portable.bat\`
   - macOS: \`FlexPBX-Portable.command\`
   - Linux: \`FlexPBX-Portable.sh\`
   - Universal: \`node launch-portable.js\`

**No installation required!** The entire system runs from the detachable drive.
` : `
The current configuration is not portable. To enable portable mode:
1. Copy the FlexPBX folder to a detachable drive
2. Create a \`.flexpbx-portable\` marker file
3. Run from the detachable drive
`}

## Web Interfaces

Once running, these interfaces will be available:

- **Accessibility Test Suite**: \`FlexPBX-Accessibility-Test.html\`
- **Rich Messaging Interface**: \`FlexPBX-Rich-Messaging.html\`
- **CopyParty File Sharing**: \`http://localhost:8080\`

## Configuration

Configuration files are stored in:
\`${this.configDir}\`

Data files are stored in:
\`${this.dataDir}\`

## Troubleshooting

### Port Conflicts
If you encounter port conflicts:
- Messaging: Port 41238
- Accessibility: Port 41237
- File Sharing: Port 8080

### Permissions
${process.platform === 'darwin' ? `
On macOS, ensure:
- VoiceOver scripting is enabled
- Accessibility permissions are granted
- Audio input permissions are granted
` : process.platform === 'win32' ? `
On Windows, ensure:
- Screen reader access is enabled
- Audio permissions are granted
- Windows Defender allows the application
` : `
On Linux, ensure:
- Audio permissions are granted
- AT-SPI accessibility is enabled
- Required audio packages are installed
`}

## Support

For support and documentation:
- Check the console output for detailed logs
- Review the test results in \`test-results.json\`
- Use the built-in accessibility test interfaces

---

*FlexPBX ${this.portableMode ? 'Portable' : 'Desktop'} Edition - Version 2.0.0*
*Generated: ${new Date().toISOString()}*
`;

        const readmeFile = path.join(this.basePath, 'README.md');
        await fs.writeFile(readmeFile, readmeContent);
        console.log(`📖 README created: ${readmeFile}`);
    }

    getConfig() {
        return {
            portable: this.portableMode,
            basePath: this.basePath,
            configDir: this.configDir,
            dataDir: this.dataDir,
            tempDir: this.tempDir
        };
    }

    async setupComplete() {
        await this.createPortableLauncher();
        await this.createAutorunConfig();
        await this.createReadme();

        console.log('\n🎉 FlexPBX Portable Configuration Complete!');
        console.log('='.repeat(50));
        console.log(`Mode: ${this.portableMode ? '🔌 Portable' : '🏠 Standard'}`);
        console.log(`Base: ${this.basePath}`);
        console.log(`Ready for ${this.portableMode ? 'detachable drive deployment' : 'local use'}!`);

        return this.getConfig();
    }
}

// Run if called directly
if (require.main === module) {
    const manager = new PortableConfigManager();
    manager.setupComplete().catch(console.error);
}

module.exports = PortableConfigManager;