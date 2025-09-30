const { spawn } = require('child_process');
const path = require('path');
const fs = require('fs-extra');
const chokidar = require('chokidar');

class DevServer {
    constructor() {
        this.electronProcess = null;
        this.rendererWatcher = null;
        this.mainWatcher = null;
        this.isRestarting = false;
    }

    async start() {
        console.log('🚀 Starting FlexPBX Desktop in development mode...');

        // Set development environment
        process.env.NODE_ENV = 'development';

        // Start file watchers
        this.startFileWatchers();

        // Start Electron
        await this.startElectron();

        console.log('✅ Development server started');
        console.log('📁 Main process files: src/main/**/*.js');
        console.log('📁 Renderer process files: src/renderer/**/*');
        console.log('🔄 Auto-restart enabled');
    }

    startFileWatchers() {
        // Watch main process files
        this.mainWatcher = chokidar.watch('src/main/**/*.js', {
            ignored: /node_modules/,
            persistent: true
        });

        this.mainWatcher.on('change', (filePath) => {
            console.log(`📝 Main process file changed: ${filePath}`);
            this.restartElectron();
        });

        // Watch renderer process files
        this.rendererWatcher = chokidar.watch('src/renderer/**/*', {
            ignored: /node_modules/,
            persistent: true
        });

        this.rendererWatcher.on('change', (filePath) => {
            console.log(`📝 Renderer file changed: ${filePath}`);
            // For renderer changes, we can just reload the window
            if (this.electronProcess) {
                this.electronProcess.send('reload-renderer');
            }
        });
    }

    async startElectron() {
        const electronPath = require('electron');
        const mainScript = path.join(__dirname, '..', 'src', 'main', 'main.js');

        this.electronProcess = spawn(electronPath, [mainScript], {
            stdio: ['inherit', 'inherit', 'inherit', 'ipc'],
            env: {
                ...process.env,
                NODE_ENV: 'development',
                ELECTRON_IS_DEV: '1'
            }
        });

        this.electronProcess.on('close', (code) => {
            if (!this.isRestarting) {
                console.log(`🔚 Electron process exited with code ${code}`);
                process.exit(code);
            }
        });

        this.electronProcess.on('error', (error) => {
            console.error('❌ Failed to start Electron:', error);
            process.exit(1);
        });

        // Handle renderer reload requests
        this.electronProcess.on('message', (message) => {
            if (message === 'reload-renderer-complete') {
                console.log('🔄 Renderer reloaded');
            }
        });
    }

    async restartElectron() {
        if (this.isRestarting) {
            return;
        }

        this.isRestarting = true;
        console.log('🔄 Restarting Electron...');

        if (this.electronProcess) {
            this.electronProcess.kill();
            this.electronProcess = null;
        }

        // Wait a moment before restarting
        await new Promise(resolve => setTimeout(resolve, 1000));

        try {
            await this.startElectron();
            console.log('✅ Electron restarted');
        } catch (error) {
            console.error('❌ Failed to restart Electron:', error);
        } finally {
            this.isRestarting = false;
        }
    }

    async stop() {
        console.log('🛑 Stopping development server...');

        if (this.mainWatcher) {
            await this.mainWatcher.close();
        }

        if (this.rendererWatcher) {
            await this.rendererWatcher.close();
        }

        if (this.electronProcess) {
            this.electronProcess.kill();
            this.electronProcess = null;
        }

        console.log('✅ Development server stopped');
    }
}

// Handle graceful shutdown
process.on('SIGINT', async () => {
    if (global.devServer) {
        await global.devServer.stop();
    }
    process.exit(0);
});

process.on('SIGTERM', async () => {
    if (global.devServer) {
        await global.devServer.stop();
    }
    process.exit(0);
});

// Start dev server if called directly
if (require.main === module) {
    global.devServer = new DevServer();
    global.devServer.start().catch(error => {
        console.error('❌ Failed to start development server:', error);
        process.exit(1);
    });
}

module.exports = DevServer;