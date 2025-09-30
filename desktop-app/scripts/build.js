const { execSync } = require('child_process');
const fs = require('fs-extra');
const path = require('path');

class AppBuilder {
    constructor() {
        this.projectRoot = path.join(__dirname, '..');
        this.distDir = path.join(this.projectRoot, 'dist');
        this.buildDir = path.join(this.projectRoot, 'build');
    }

    async build() {
        console.log('🚀 Starting FlexPBX Desktop build process...');

        try {
            await this.cleanDirs();
            await this.prepareBuild();
            await this.buildElectron();
            await this.createInstaller();
            await this.signApp();
            await this.notarizeApp();

            console.log('✅ Build completed successfully!');
            console.log(`📦 Distribution files available in: ${this.distDir}`);

        } catch (error) {
            console.error('❌ Build failed:', error.message);
            process.exit(1);
        }
    }

    async cleanDirs() {
        console.log('🧹 Cleaning build directories...');
        await fs.remove(this.distDir);
        await fs.remove(this.buildDir);
        await fs.ensureDir(this.distDir);
        await fs.ensureDir(this.buildDir);
    }

    async prepareBuild() {
        console.log('📋 Preparing build environment...');

        // Copy source files to build directory
        const srcDir = path.join(this.projectRoot, 'src');
        const buildSrcDir = path.join(this.buildDir, 'src');
        await fs.copy(srcDir, buildSrcDir);

        // Copy assets
        const assetsDir = path.join(this.projectRoot, 'assets');
        if (await fs.pathExists(assetsDir)) {
            const buildAssetsDir = path.join(this.buildDir, 'assets');
            await fs.copy(assetsDir, buildAssetsDir);
        }

        // Copy package.json
        const packageJson = await fs.readJson(path.join(this.projectRoot, 'package.json'));
        await fs.writeJson(path.join(this.buildDir, 'package.json'), packageJson, { spaces: 2 });

        // Install production dependencies
        console.log('📦 Installing production dependencies...');
        execSync('npm ci --production', {
            cwd: this.buildDir,
            stdio: 'inherit'
        });
    }

    async buildElectron() {
        console.log('⚙️ Building Electron application...');

        const electronBuilder = require('electron-builder');

        const config = {
            appId: 'com.flexpbx.desktop',
            productName: 'FlexPBX Desktop',
            directories: {
                app: this.buildDir,
                output: this.distDir
            },
            files: [
                'src/**/*',
                'node_modules/**/*',
                'package.json'
            ],
            mac: {
                category: 'public.app-category.developer-tools',
                icon: 'assets/icon.icns',
                target: [
                    {
                        target: 'dmg',
                        arch: ['x64', 'arm64']
                    },
                    {
                        target: 'zip',
                        arch: ['x64', 'arm64']
                    }
                ],
                entitlements: 'build/entitlements.mac.plist',
                entitlementsInherit: 'build/entitlements.mac.plist',
                hardenedRuntime: true,
                gatekeeperAssess: false,
                extendInfo: {
                    NSCameraUsageDescription: 'FlexPBX Desktop may access the camera for video calls.',
                    NSMicrophoneUsageDescription: 'FlexPBX Desktop needs microphone access for voice calls.',
                    NSNetworkVolumesUsageDescription: 'FlexPBX Desktop may access network volumes for remote deployments.'
                }
            },
            dmg: {
                title: 'FlexPBX Desktop ${version}',
                icon: 'assets/icon.icns',
                background: 'assets/dmg-background.png',
                contents: [
                    {
                        x: 130,
                        y: 220
                    },
                    {
                        x: 410,
                        y: 220,
                        type: 'link',
                        path: '/Applications'
                    }
                ],
                window: {
                    width: 540,
                    height: 380
                }
            },
            publish: {
                provider: 'github',
                owner: 'raywonder',
                repo: 'flexpbx'
            },
            afterSign: 'scripts/notarize.js'
        };

        await electronBuilder.build({
            config,
            publish: 'never'
        });
    }

    async createInstaller() {
        console.log('📦 Creating installer packages...');
        // Installer creation is handled by electron-builder
    }

    async signApp() {
        console.log('✍️ Code signing application...');

        const { APPLE_ID, APPLE_ID_PASSWORD, CSC_LINK, CSC_KEY_PASSWORD } = process.env;

        if (!APPLE_ID || !APPLE_ID_PASSWORD || !CSC_LINK || !CSC_KEY_PASSWORD) {
            console.log('⚠️ Code signing credentials not found. Skipping signing.');
            console.log('Set APPLE_ID, APPLE_ID_PASSWORD, CSC_LINK, and CSC_KEY_PASSWORD environment variables for code signing.');
            return;
        }

        console.log('✅ Code signing will be handled by electron-builder during build.');
    }

    async notarizeApp() {
        console.log('🔐 Notarization will be handled by afterSign hook if credentials are available.');
    }
}

// Run build if called directly
if (require.main === module) {
    const builder = new AppBuilder();
    builder.build();
}

module.exports = AppBuilder;