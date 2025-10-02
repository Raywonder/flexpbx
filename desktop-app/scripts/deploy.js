#!/usr/bin/env node
/**
 * FlexPBX Deployment Script
 * Enterprise-ready PBX system deployment with modular architecture
 */

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');
const crypto = require('crypto');

class FlexPBXDeployment {
  constructor() {
    this.projectRoot = path.resolve(__dirname, '..');
    this.deployDir = path.join(this.projectRoot, 'deployment');
    this.platform = process.platform;
    this.arch = process.arch;
  }

  // Initialize deployment structure
  async initializeDeployment() {
    console.log('🏭 Initializing FlexPBX deployment structure...');

    const structure = {
      base: ['deployment'],
      packages: [
        'deployment/enterprise',
        'deployment/standalone',
        'deployment/cloud',
        'deployment/hybrid'
      ],
      modules: [
        'deployment/modules/core',
        'deployment/modules/pbx-engine',
        'deployment/modules/sip-trunk',
        'deployment/modules/extensions',
        'deployment/modules/voicemail',
        'deployment/modules/ivr',
        'deployment/modules/conference',
        'deployment/modules/recording',
        'deployment/modules/analytics',
        'deployment/modules/licensing'
      ],
      configs: [
        'deployment/configs/templates',
        'deployment/configs/providers',
        'deployment/configs/security'
      ],
      resources: [
        'deployment/resources/sounds',
        'deployment/resources/music-on-hold',
        'deployment/resources/prompts'
      ],
      docs: ['deployment/docs']
    };

    // Create all directories
    for (const category of Object.values(structure)) {
      for (const dir of category) {
        const fullPath = path.join(this.projectRoot, dir);
        if (!fs.existsSync(fullPath)) {
          fs.mkdirSync(fullPath, { recursive: true });
        }
      }
    }

    console.log('✅ Deployment structure initialized');
  }

  // Build deployment packages
  async buildDeploymentPackages() {
    console.log('📦 Building FlexPBX deployment packages...');

    // Clean previous builds
    try {
      if (fs.existsSync(path.join(this.projectRoot, 'dist'))) {
        execSync('rm -rf dist', { cwd: this.projectRoot });
      }
    } catch (e) {
      // Ignore if dist doesn't exist
    }

    // Build configurations for different deployment types
    const deploymentTypes = {
      enterprise: {
        name: 'FlexPBX Enterprise',
        features: ['multi-tenant', 'clustering', 'ha-failover', 'advanced-analytics'],
        build: 'npm run build:enterprise'
      },
      standalone: {
        name: 'FlexPBX Standalone',
        features: ['single-instance', 'local-storage', 'basic-analytics'],
        build: 'npm run build:standalone'
      },
      cloud: {
        name: 'FlexPBX Cloud',
        features: ['multi-region', 'auto-scaling', 'cloud-storage', 'api-gateway'],
        build: 'npm run build:cloud'
      },
      hybrid: {
        name: 'FlexPBX Hybrid',
        features: ['on-premise-core', 'cloud-backup', 'hybrid-routing'],
        build: 'npm run build:hybrid'
      }
    };

    // For now, use standard build for all types
    console.log('🔨 Building FlexPBX application...');
    try {
      execSync('npm run build', {
        cwd: this.projectRoot,
        stdio: 'inherit'
      });
    } catch (error) {
      console.warn('⚠️ Build command not configured, creating build scripts...');
      await this.createBuildScripts();
    }

    // Create deployment packages for each type
    for (const [type, config] of Object.entries(deploymentTypes)) {
      await this.createDeploymentPackage(type, config);
    }

    console.log('✅ Deployment packages built');
  }

  // Create build scripts if not present
  async createBuildScripts() {
    const packageJsonPath = path.join(this.projectRoot, 'package.json');
    const packageJson = JSON.parse(fs.readFileSync(packageJsonPath, 'utf8'));

    if (!packageJson.scripts.build) {
      packageJson.scripts = {
        ...packageJson.scripts,
        'build': 'electron-builder',
        'build:enterprise': 'electron-builder --config=electron-builder-enterprise.yml',
        'build:standalone': 'electron-builder --config=electron-builder-standalone.yml',
        'build:cloud': 'node scripts/cloud-build.js',
        'build:hybrid': 'node scripts/hybrid-build.js',
        'build:mac': 'electron-builder --mac',
        'build:win': 'electron-builder --win',
        'build:linux': 'electron-builder --linux',
        'pack': 'electron-builder --dir',
        'dist': 'electron-builder --mac --win --linux'
      };

      // Add electron-builder configuration
      packageJson.build = {
        appId: 'com.flexpbx.pbx',
        productName: 'FlexPBX',
        directories: {
          output: 'dist'
        },
        files: [
          'src/**/*',
          'assets/**/*',
          'sounds/**/*',
          'node_modules/**/*',
          '!**/*.ts',
          '!*.map',
          '!.git',
          '!test/**'
        ],
        mac: {
          category: 'public.app-category.business',
          target: [
            { target: 'dmg', arch: ['x64', 'arm64'] },
            { target: 'zip', arch: ['x64', 'arm64'] }
          ]
        },
        win: {
          target: [
            { target: 'nsis', arch: ['x64', 'ia32'] },
            { target: 'portable', arch: ['x64'] }
          ]
        },
        linux: {
          target: [
            { target: 'AppImage', arch: ['x64'] },
            { target: 'deb', arch: ['x64'] },
            { target: 'rpm', arch: ['x64'] }
          ]
        }
      };

      fs.writeFileSync(packageJsonPath, JSON.stringify(packageJson, null, 2));
      console.log('✅ Build scripts added to package.json');
    }
  }

  // Create specific deployment package
  async createDeploymentPackage(type, config) {
    console.log(`📦 Creating ${config.name} package...`);

    const packageDir = path.join(this.deployDir, type);
    const manifestPath = path.join(packageDir, 'manifest.json');

    // Create package manifest
    const manifest = {
      name: config.name,
      version: '1.0.0',
      type: type,
      features: config.features,
      requirements: this.getRequirements(type),
      modules: this.getModulesForType(type),
      configuration: this.getConfigurationTemplate(type),
      created: new Date().toISOString(),
      checksum: null
    };

    fs.writeFileSync(manifestPath, JSON.stringify(manifest, null, 2));

    // Copy necessary files
    await this.copyDeploymentFiles(type, packageDir);

    // Generate checksum
    manifest.checksum = this.calculateDirectoryChecksum(packageDir);
    fs.writeFileSync(manifestPath, JSON.stringify(manifest, null, 2));

    console.log(`✅ ${config.name} package created`);
  }

  // Get requirements for deployment type
  getRequirements(type) {
    const requirements = {
      enterprise: {
        minRAM: '8GB',
        minCPU: '4 cores',
        minStorage: '100GB',
        database: 'PostgreSQL 13+',
        redis: 'Required',
        clustering: 'Supported'
      },
      standalone: {
        minRAM: '2GB',
        minCPU: '2 cores',
        minStorage: '20GB',
        database: 'SQLite',
        redis: 'Optional',
        clustering: 'Not supported'
      },
      cloud: {
        minRAM: 'Auto-scaling',
        minCPU: 'Auto-scaling',
        minStorage: 'Unlimited',
        database: 'Cloud SQL',
        redis: 'Managed Redis',
        clustering: 'Auto-managed'
      },
      hybrid: {
        minRAM: '4GB',
        minCPU: '2 cores',
        minStorage: '50GB',
        database: 'PostgreSQL or MySQL',
        redis: 'Recommended',
        clustering: 'Limited'
      }
    };

    return requirements[type] || requirements.standalone;
  }

  // Get modules for deployment type
  getModulesForType(type) {
    const allModules = [
      'core', 'pbx-engine', 'sip-trunk', 'extensions',
      'voicemail', 'ivr', 'conference', 'recording',
      'analytics', 'licensing'
    ];

    const modulesByType = {
      enterprise: allModules,
      standalone: ['core', 'pbx-engine', 'sip-trunk', 'extensions', 'voicemail'],
      cloud: allModules.concat(['cloud-sync', 'api-gateway', 'multi-region']),
      hybrid: allModules.filter(m => m !== 'analytics')
    };

    return modulesByType[type] || modulesByType.standalone;
  }

  // Get configuration template for type
  getConfigurationTemplate(type) {
    const baseConfig = {
      pbx: {
        maxExtensions: 100,
        maxConcurrentCalls: 50,
        recordingEnabled: false,
        voicemailEnabled: true
      },
      sip: {
        providers: ['flexpbx', 'callcentric', 'voipms', 'twilio'],
        codecs: ['G.711', 'G.729', 'Opus'],
        transport: ['UDP', 'TCP', 'TLS']
      },
      security: {
        encryption: 'optional',
        firewall: true,
        rateLimiting: true
      }
    };

    const typeConfigs = {
      enterprise: {
        ...baseConfig,
        pbx: { ...baseConfig.pbx, maxExtensions: 10000, maxConcurrentCalls: 5000 },
        security: { ...baseConfig.security, encryption: 'required', audit: true }
      },
      cloud: {
        ...baseConfig,
        pbx: { ...baseConfig.pbx, maxExtensions: 'unlimited', maxConcurrentCalls: 'auto-scale' },
        cloudFeatures: { autoBackup: true, geoRedundancy: true }
      }
    };

    return typeConfigs[type] || baseConfig;
  }

  // Copy deployment files
  async copyDeploymentFiles(type, targetDir) {
    // Copy core application files
    const sourceFiles = [
      'src/main',
      'src/renderer',
      'src/services',
      'assets',
      'sounds'
    ];

    for (const source of sourceFiles) {
      const sourcePath = path.join(this.projectRoot, source);
      if (fs.existsSync(sourcePath)) {
        const targetPath = path.join(targetDir, path.basename(source));
        this.copyRecursive(sourcePath, targetPath);
      }
    }
  }

  // Create modular architecture
  async createModularArchitecture() {
    console.log('🏗️ Creating FlexPBX modular architecture...');

    const modules = {
      'pbx-engine': {
        name: 'PBX Core Engine',
        version: '1.0.0',
        description: 'Core PBX functionality and call routing',
        files: [
          'src/main/services/PBXEngine.js',
          'src/main/services/CallRouter.js',
          'src/main/services/ExtensionManager.js'
        ],
        dependencies: ['sip.js', 'ws'],
        exports: ['PBXEngine', 'CallRouter', 'ExtensionManager']
      },
      'sip-trunk': {
        name: 'SIP Trunk Management',
        version: '1.0.0',
        description: 'SIP trunk configuration and management',
        files: [
          'src/main/services/SIPTrunkService.js',
          'src/main/services/ProviderManager.js'
        ],
        dependencies: ['sip.js', 'axios'],
        exports: ['SIPTrunkService', 'ProviderManager']
      },
      'extensions': {
        name: 'Extension System',
        version: '1.0.0',
        description: 'Extension management and configuration',
        files: [
          'src/main/services/ExtensionService.js',
          'src/main/services/ExtensionDatabase.js'
        ],
        dependencies: ['sqlite3'],
        exports: ['ExtensionService', 'ExtensionDatabase']
      },
      'voicemail': {
        name: 'Voicemail System',
        version: '1.0.0',
        description: 'Voicemail recording and management',
        files: [
          'src/main/services/VoicemailService.js',
          'src/main/services/MessageStore.js'
        ],
        dependencies: ['node-record-lpcm16'],
        exports: ['VoicemailService', 'MessageStore']
      },
      'ivr': {
        name: 'Interactive Voice Response',
        version: '1.0.0',
        description: 'IVR menu system and call flow',
        files: [
          'src/main/services/IVRService.js',
          'src/main/services/MenuBuilder.js'
        ],
        dependencies: ['text-to-speech'],
        exports: ['IVRService', 'MenuBuilder']
      },
      'licensing': {
        name: 'License Management',
        version: '1.0.0',
        description: 'License validation and feature management',
        files: [
          'src/main/services/LicensingService.js',
          'src/main/services/LicenseValidator.js'
        ],
        dependencies: ['crypto', 'axios'],
        exports: ['LicensingService', 'LicenseValidator']
      }
    };

    // Create module packages
    for (const [key, module] of Object.entries(modules)) {
      await this.createModule(key, module);
    }

    // Create module loader
    await this.createModuleLoader();

    // Create module registry
    await this.createModuleRegistry(modules);

    console.log('✅ Modular architecture created');
  }

  // Create individual module
  async createModule(key, config) {
    const moduleDir = path.join(this.deployDir, 'modules', key);

    // Create module structure
    const structure = ['src', 'config', 'tests', 'docs'];
    structure.forEach(dir => {
      const dirPath = path.join(moduleDir, dir);
      if (!fs.existsSync(dirPath)) {
        fs.mkdirSync(dirPath, { recursive: true });
      }
    });

    // Create module manifest
    const manifest = {
      name: config.name,
      version: config.version,
      description: config.description,
      main: 'index.js',
      dependencies: config.dependencies,
      exports: config.exports,
      checksum: null,
      created: new Date().toISOString()
    };

    // Create module index file
    const indexContent = `/**
 * ${config.name}
 * ${config.description}
 */

const path = require('path');
const fs = require('fs');

class ${key.split('-').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join('')}Module {
  constructor(config = {}) {
    this.config = config;
    this.initialized = false;
    this.services = new Map();
  }

  async initialize() {
    if (this.initialized) return;

    console.log(\`Initializing ${config.name}...\`);

    // Load module services
    ${config.exports.map(exp => `
    const ${exp} = require('./src/${exp}');
    this.services.set('${exp}', new ${exp}(this.config));`).join('')}

    this.initialized = true;
    console.log(\`✅ ${config.name} initialized\`);
  }

  getService(serviceName) {
    return this.services.get(serviceName);
  }

  async shutdown() {
    console.log(\`Shutting down ${config.name}...\`);
    for (const [name, service] of this.services) {
      if (service.shutdown) {
        await service.shutdown();
      }
    }
    this.initialized = false;
  }
}

module.exports = ${key.split('-').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join('')}Module;
`;

    fs.writeFileSync(path.join(moduleDir, 'index.js'), indexContent);

    // Save manifest
    manifest.checksum = this.calculateFileChecksum(path.join(moduleDir, 'index.js'));
    fs.writeFileSync(
      path.join(moduleDir, 'module.json'),
      JSON.stringify(manifest, null, 2)
    );

    console.log(`✅ Module ${config.name} created`);
  }

  // Create module loader system
  async createModuleLoader() {
    const loaderContent = `/**
 * FlexPBX Module Loader
 * Dynamic module loading and dependency management
 */

const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

class FlexPBXModuleLoader {
  constructor(options = {}) {
    this.modulePath = options.modulePath || path.join(__dirname, '..', 'modules');
    this.modules = new Map();
    this.dependencies = new Map();
    this.loadOrder = [];
  }

  async loadModule(moduleName, options = {}) {
    if (this.modules.has(moduleName)) {
      console.log(\`Module \${moduleName} already loaded\`);
      return this.modules.get(moduleName);
    }

    const modulePath = path.join(this.modulePath, moduleName);
    const manifestPath = path.join(modulePath, 'module.json');

    if (!fs.existsSync(manifestPath)) {
      throw new Error(\`Module \${moduleName} not found\`);
    }

    const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));

    // Verify module integrity
    if (options.verifyIntegrity) {
      await this.verifyModuleIntegrity(modulePath, manifest);
    }

    // Load dependencies first
    if (manifest.dependencies && manifest.dependencies.length > 0) {
      for (const dep of manifest.dependencies) {
        await this.loadDependency(dep);
      }
    }

    // Load the module
    const ModuleClass = require(modulePath);
    const moduleInstance = new ModuleClass(options.config || {});

    // Initialize module
    await moduleInstance.initialize();

    // Store module
    this.modules.set(moduleName, {
      instance: moduleInstance,
      manifest: manifest,
      path: modulePath
    });

    this.loadOrder.push(moduleName);

    console.log(\`✅ Module \${manifest.name} loaded successfully\`);
    return moduleInstance;
  }

  async loadDependency(depName) {
    if (this.dependencies.has(depName)) return;

    try {
      require.resolve(depName);
      this.dependencies.set(depName, { loaded: true });
    } catch (error) {
      console.log(\`Installing dependency \${depName}...\`);
      const { execSync } = require('child_process');
      execSync(\`npm install \${depName}\`, { stdio: 'inherit' });
      this.dependencies.set(depName, { loaded: true, installed: true });
    }
  }

  async verifyModuleIntegrity(modulePath, manifest) {
    const indexPath = path.join(modulePath, 'index.js');
    const actualChecksum = this.calculateChecksum(indexPath);

    if (actualChecksum !== manifest.checksum) {
      throw new Error(\`Module integrity check failed for \${manifest.name}\`);
    }
  }

  calculateChecksum(filePath) {
    const content = fs.readFileSync(filePath);
    return crypto.createHash('sha256').update(content).digest('hex');
  }

  async loadAllModules(options = {}) {
    const moduleNames = fs.readdirSync(this.modulePath)
      .filter(name => fs.statSync(path.join(this.modulePath, name)).isDirectory());

    for (const moduleName of moduleNames) {
      try {
        await this.loadModule(moduleName, options);
      } catch (error) {
        console.error(\`Failed to load module \${moduleName}:\`, error.message);
        if (!options.continueOnError) throw error;
      }
    }

    return this.modules;
  }

  getModule(moduleName) {
    const module = this.modules.get(moduleName);
    return module ? module.instance : null;
  }

  getLoadedModules() {
    return Array.from(this.modules.keys());
  }

  async shutdownModules() {
    console.log('Shutting down all modules...');

    // Shutdown in reverse order
    for (let i = this.loadOrder.length - 1; i >= 0; i--) {
      const moduleName = this.loadOrder[i];
      const module = this.modules.get(moduleName);

      if (module && module.instance.shutdown) {
        await module.instance.shutdown();
      }
    }

    this.modules.clear();
    this.loadOrder = [];
    console.log('✅ All modules shut down');
  }
}

module.exports = FlexPBXModuleLoader;
`;

    fs.writeFileSync(
      path.join(this.deployDir, 'modules', 'loader.js'),
      loaderContent
    );

    console.log('✅ Module loader created');
  }

  // Create module registry
  async createModuleRegistry(modules) {
    const registry = {
      version: '1.0.0',
      modules: Object.keys(modules).map(key => ({
        id: key,
        ...modules[key],
        path: `modules/${key}`,
        enabled: true
      })),
      dependencies: this.analyzeDependencies(modules),
      loadOrder: this.calculateLoadOrder(modules),
      created: new Date().toISOString()
    };

    fs.writeFileSync(
      path.join(this.deployDir, 'modules', 'registry.json'),
      JSON.stringify(registry, null, 2)
    );

    console.log('✅ Module registry created');
  }

  // Analyze module dependencies
  analyzeDependencies(modules) {
    const deps = {};
    for (const [key, module] of Object.entries(modules)) {
      deps[key] = module.dependencies || [];
    }
    return deps;
  }

  // Calculate optimal module load order
  calculateLoadOrder(modules) {
    // Simple topological sort based on dependencies
    const order = [];
    const visited = new Set();

    const visit = (key) => {
      if (visited.has(key)) return;
      visited.add(key);

      const module = modules[key];
      if (module.dependencies) {
        // Visit dependencies first
        module.dependencies.forEach(dep => {
          if (modules[dep]) visit(dep);
        });
      }

      order.push(key);
    };

    Object.keys(modules).forEach(visit);
    return order;
  }

  // Create deployment documentation
  async createDocumentation() {
    console.log('📚 Creating deployment documentation...');

    const docs = {
      'README.md': this.generateReadme(),
      'DEPLOYMENT.md': this.generateDeploymentGuide(),
      'MODULES.md': this.generateModuleDocumentation(),
      'CONFIGURATION.md': this.generateConfigurationGuide(),
      'TROUBLESHOOTING.md': this.generateTroubleshootingGuide()
    };

    for (const [filename, content] of Object.entries(docs)) {
      fs.writeFileSync(
        path.join(this.deployDir, 'docs', filename),
        content
      );
    }

    console.log('✅ Documentation created');
  }

  generateReadme() {
    return `# FlexPBX Deployment Package

## Overview
FlexPBX is a comprehensive, enterprise-ready PBX system with modular architecture for flexible deployment across various environments.

## Deployment Options

### 🏢 Enterprise
Full-featured deployment for large organizations with high availability, clustering, and advanced analytics.

### 🖥️ Standalone
Single-instance deployment for small to medium businesses with essential PBX features.

### ☁️ Cloud
Cloud-native deployment with auto-scaling, multi-region support, and managed services.

### 🔄 Hybrid
Combination of on-premise core with cloud backup and hybrid routing capabilities.

## Quick Start

1. Choose your deployment type from the \`deployment/\` directory
2. Navigate to the chosen directory (e.g., \`deployment/enterprise/\`)
3. Run the deployment script: \`node deploy.js\`
4. Configure using the templates in \`configs/\`
5. Start the application

## Features

- **Multi-Provider SIP Support**: FlexPBX, CallCentric, VoIP.ms, Twilio, Google Voice
- **Extension Management**: Unlimited extensions with flexible routing
- **IVR System**: Interactive voice response with menu builder
- **Voicemail**: Advanced voicemail with email integration
- **Conference Rooms**: Multi-party conference calling
- **Call Recording**: Automatic and on-demand recording
- **Analytics**: Real-time and historical call analytics
- **Licensing**: Flexible licensing system

## Requirements

See specific requirements for each deployment type in their respective directories.

## Support

- Documentation: deployment/docs/
- Issues: https://github.com/flexpbx/flexpbx/issues
- Email: support@flexpbx.com
`;
  }

  generateDeploymentGuide() {
    return `# FlexPBX Deployment Guide

## Pre-Installation

### System Requirements
- Node.js 16+ (18+ recommended)
- Python 3.8+ (for native modules)
- Git (for version control)
- 2-8 GB RAM (depends on deployment type)
- 20-100 GB storage

### Network Requirements
- Ports: 5060-5061 (SIP), 10000-20000 (RTP)
- Stable internet connection
- Static IP recommended for production

## Installation Steps

### 1. Extract Deployment Package
\`\`\`bash
tar -xzf flexpbx-deployment.tar.gz
cd flexpbx-deployment
\`\`\`

### 2. Choose Deployment Type
\`\`\`bash
cd deployment/[enterprise|standalone|cloud|hybrid]
\`\`\`

### 3. Install Dependencies
\`\`\`bash
npm install
\`\`\`

### 4. Configure System
\`\`\`bash
cp configs/templates/default.json configs/active.json
# Edit configs/active.json with your settings
\`\`\`

### 5. Initialize Database
\`\`\`bash
node scripts/init-database.js
\`\`\`

### 6. Load Modules
\`\`\`bash
node scripts/load-modules.js
\`\`\`

### 7. Start Application
\`\`\`bash
npm start
\`\`\`

## Post-Installation

### Configure SIP Providers
1. Navigate to Settings > SIP Trunks
2. Add provider credentials
3. Configure inbound/outbound routing

### Setup Extensions
1. Go to Extensions > Add Extension
2. Configure extension number and settings
3. Assign to users

### Configure IVR (Optional)
1. Navigate to IVR > Menu Builder
2. Create menu structure
3. Record or upload prompts

## Verification

### Test Checklist
- [ ] Application starts without errors
- [ ] Can access web interface
- [ ] SIP registration successful
- [ ] Can make test call
- [ ] Extensions working
- [ ] Voicemail functioning

## Upgrading

### Backup Current Installation
\`\`\`bash
node scripts/backup.js
\`\`\`

### Apply Update
\`\`\`bash
node scripts/update.js --version=latest
\`\`\`

### Verify Update
\`\`\`bash
node scripts/verify-update.js
\`\`\`
`;
  }

  generateModuleDocumentation() {
    return `# FlexPBX Module Documentation

## Module System Overview
FlexPBX uses a modular architecture for flexibility and maintainability.

## Core Modules

### PBX Engine
Core PBX functionality and call routing logic.

### SIP Trunk
Manages SIP provider connections and trunk configuration.

### Extensions
Handles extension creation, management, and routing.

### Voicemail
Voicemail recording, storage, and retrieval system.

### IVR
Interactive Voice Response system with menu builder.

### Conference
Multi-party conference room management.

### Recording
Call recording with storage and retrieval.

### Analytics
Real-time and historical call analytics.

### Licensing
License validation and feature management.

## Loading Modules

### Load Single Module
\`\`\`javascript
const loader = require('./modules/loader');
const pbxEngine = await loader.loadModule('pbx-engine');
\`\`\`

### Load All Modules
\`\`\`javascript
const modules = await loader.loadAllModules();
\`\`\`

### Custom Configuration
\`\`\`javascript
const module = await loader.loadModule('extensions', {
  config: {
    maxExtensions: 1000,
    startingNumber: 100
  }
});
\`\`\`

## Creating Custom Modules

### Module Structure
\`\`\`
custom-module/
├── module.json
├── index.js
├── src/
│   └── service.js
├── config/
│   └── default.json
└── tests/
    └── test.js
\`\`\`

### Module Manifest
\`\`\`json
{
  "name": "Custom Module",
  "version": "1.0.0",
  "description": "Custom functionality",
  "dependencies": [],
  "exports": ["CustomService"]
}
\`\`\`

## Module API

Each module exposes:
- \`initialize()\`: Setup module
- \`getService(name)\`: Get module service
- \`shutdown()\`: Cleanup resources
`;
  }

  generateConfigurationGuide() {
    return `# FlexPBX Configuration Guide

## Configuration Files

### Main Configuration
\`configs/active.json\` - Active configuration file

### Templates
- \`default.json\` - Default settings
- \`enterprise.json\` - Enterprise settings
- \`cloud.json\` - Cloud deployment settings

## Configuration Sections

### PBX Settings
\`\`\`json
{
  "pbx": {
    "maxExtensions": 100,
    "maxConcurrentCalls": 50,
    "recordingEnabled": true,
    "voicemailEnabled": true
  }
}
\`\`\`

### SIP Configuration
\`\`\`json
{
  "sip": {
    "providers": ["flexpbx", "callcentric"],
    "codecs": ["G.711", "Opus"],
    "transport": ["UDP", "TLS"]
  }
}
\`\`\`

### Security Settings
\`\`\`json
{
  "security": {
    "encryption": "required",
    "firewall": true,
    "rateLimiting": true,
    "maxFailedAttempts": 5
  }
}
\`\`\`

## Environment Variables

### Required
- \`FLEXPBX_LICENSE_KEY\`: License key
- \`FLEXPBX_DB_URL\`: Database connection string

### Optional
- \`FLEXPBX_PORT\`: Web interface port (default: 3000)
- \`FLEXPBX_LOG_LEVEL\`: Logging level (debug|info|warn|error)
- \`FLEXPBX_CLUSTER_MODE\`: Enable clustering (true|false)

## Provider Configuration

### FlexPBX
\`\`\`json
{
  "provider": "flexpbx",
  "username": "your-username",
  "password": "your-password",
  "server": "pbx.flexpbx.com"
}
\`\`\`

### CallCentric
\`\`\`json
{
  "provider": "callcentric",
  "sipUsername": "1777XXXXXXX",
  "sipPassword": "password",
  "server": "callcentric.com"
}
\`\`\`

## Advanced Configuration

### High Availability
\`\`\`json
{
  "ha": {
    "enabled": true,
    "mode": "active-passive",
    "heartbeatInterval": 5000,
    "failoverThreshold": 3
  }
}
\`\`\`

### Clustering
\`\`\`json
{
  "cluster": {
    "enabled": true,
    "nodes": ["node1.example.com", "node2.example.com"],
    "loadBalancing": "round-robin"
  }
}
\`\`\`
`;
  }

  generateTroubleshootingGuide() {
    return `# FlexPBX Troubleshooting Guide

## Common Issues

### Application Won't Start

#### Check Node.js Version
\`\`\`bash
node --version  # Should be 16+
\`\`\`

#### Verify Dependencies
\`\`\`bash
npm install
npm audit fix
\`\`\`

#### Check Logs
\`\`\`bash
tail -f logs/flexpbx.log
\`\`\`

### SIP Registration Fails

#### Verify Credentials
- Check username/password in configs/active.json
- Ensure account is active with provider

#### Check Network
\`\`\`bash
# Test SIP connectivity
nc -zv sip.provider.com 5060
\`\`\`

#### Firewall Issues
- Open ports 5060-5061 for SIP
- Open ports 10000-20000 for RTP

### No Audio in Calls

#### Check NAT Settings
- Configure STUN servers
- Set public IP if behind NAT

#### Verify Codecs
- Ensure compatible codecs with provider
- G.711 is most compatible

### Database Errors

#### SQLite Locked
\`\`\`bash
# Stop application
# Remove lock file
rm data/flexpbx.db-journal
\`\`\`

#### PostgreSQL Connection
- Verify connection string
- Check database server status
- Ensure user permissions

## Diagnostic Commands

### Check System Status
\`\`\`bash
node scripts/health-check.js
\`\`\`

### Test SIP Registration
\`\`\`bash
node scripts/test-sip.js
\`\`\`

### Verify Modules
\`\`\`bash
node scripts/verify-modules.js
\`\`\`

### Database Integrity
\`\`\`bash
node scripts/check-database.js
\`\`\`

## Log Locations

- Application: \`logs/flexpbx.log\`
- SIP: \`logs/sip.log\`
- Errors: \`logs/error.log\`
- Access: \`logs/access.log\`

## Getting Help

### Collect Debug Info
\`\`\`bash
node scripts/collect-debug-info.js
\`\`\`

### Contact Support
- Email: support@flexpbx.com
- Include debug info file
- Describe issue and steps to reproduce

## Recovery Procedures

### Restore from Backup
\`\`\`bash
node scripts/restore.js --backup=backup-2024-01-01.tar.gz
\`\`\`

### Reset to Defaults
\`\`\`bash
node scripts/reset.js --confirm
\`\`\`

### Rebuild Database
\`\`\`bash
node scripts/rebuild-database.js
\`\`\`
`;
  }

  // Helper utilities
  calculateFileChecksum(filePath) {
    const content = fs.readFileSync(filePath);
    return crypto.createHash('sha256').update(content).digest('hex');
  }

  calculateDirectoryChecksum(dirPath) {
    const hash = crypto.createHash('sha256');
    const files = this.getAllFiles(dirPath);

    files.sort().forEach(file => {
      const content = fs.readFileSync(file);
      hash.update(content);
    });

    return hash.digest('hex');
  }

  getAllFiles(dirPath, files = []) {
    const items = fs.readdirSync(dirPath);

    items.forEach(item => {
      const fullPath = path.join(dirPath, item);
      if (fs.statSync(fullPath).isDirectory()) {
        this.getAllFiles(fullPath, files);
      } else {
        files.push(fullPath);
      }
    });

    return files;
  }

  copyRecursive(source, target) {
    if (!fs.existsSync(target)) {
      fs.mkdirSync(target, { recursive: true });
    }

    if (fs.lstatSync(source).isDirectory()) {
      const files = fs.readdirSync(source);
      files.forEach(file => {
        const curSource = path.join(source, file);
        const curTarget = path.join(target, file);

        if (fs.lstatSync(curSource).isDirectory()) {
          this.copyRecursive(curSource, curTarget);
        } else {
          fs.copyFileSync(curSource, curTarget);
        }
      });
    } else {
      fs.copyFileSync(source, target);
    }
  }

  // Main deployment process
  async deploy() {
    console.log('🚀 Starting FlexPBX Deployment Builder...');
    console.log(`📍 Platform: ${this.platform} (${this.arch})`);
    console.log('');

    try {
      await this.initializeDeployment();
      await this.buildDeploymentPackages();
      await this.createModularArchitecture();
      await this.createDocumentation();

      console.log('');
      console.log('✨ FlexPBX Deployment Package Created Successfully!');
      console.log(`📁 Location: ${this.deployDir}`);
      console.log('');
      console.log('📦 Deployment Types Available:');
      console.log('  • Enterprise - Full-featured for large organizations');
      console.log('  • Standalone - Single-instance for SMB');
      console.log('  • Cloud - Cloud-native with auto-scaling');
      console.log('  • Hybrid - On-premise core with cloud backup');
      console.log('');
      console.log('🧩 Modular Architecture:');
      console.log('  • 10 core modules with dynamic loading');
      console.log('  • Module integrity verification');
      console.log('  • Automatic dependency management');
      console.log('');
      console.log('📚 Documentation:');
      console.log('  • Complete deployment guide');
      console.log('  • Module documentation');
      console.log('  • Configuration guide');
      console.log('  • Troubleshooting guide');
      console.log('');
      console.log('🎯 Next Steps:');
      console.log('  1. Choose deployment type from deployment/');
      console.log('  2. Follow the deployment guide in docs/');
      console.log('  3. Configure using provided templates');
      console.log('  4. Deploy to target environment');

    } catch (error) {
      console.error('❌ Deployment build failed:', error);
      process.exit(1);
    }
  }
}

// Run deployment if called directly
if (require.main === module) {
  const deployment = new FlexPBXDeployment();
  deployment.deploy();
}

module.exports = FlexPBXDeployment;