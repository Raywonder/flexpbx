/**
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
      console.log(`Module ${moduleName} already loaded`);
      return this.modules.get(moduleName);
    }

    const modulePath = path.join(this.modulePath, moduleName);
    const manifestPath = path.join(modulePath, 'module.json');

    if (!fs.existsSync(manifestPath)) {
      throw new Error(`Module ${moduleName} not found`);
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

    console.log(`✅ Module ${manifest.name} loaded successfully`);
    return moduleInstance;
  }

  async loadDependency(depName) {
    if (this.dependencies.has(depName)) return;

    try {
      require.resolve(depName);
      this.dependencies.set(depName, { loaded: true });
    } catch (error) {
      console.log(`Installing dependency ${depName}...`);
      const { execSync } = require('child_process');
      execSync(`npm install ${depName}`, { stdio: 'inherit' });
      this.dependencies.set(depName, { loaded: true, installed: true });
    }
  }

  async verifyModuleIntegrity(modulePath, manifest) {
    const indexPath = path.join(modulePath, 'index.js');
    const actualChecksum = this.calculateChecksum(indexPath);

    if (actualChecksum !== manifest.checksum) {
      throw new Error(`Module integrity check failed for ${manifest.name}`);
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
        console.error(`Failed to load module ${moduleName}:`, error.message);
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
