/**
 * PBX Core Engine
 * Core PBX functionality and call routing
 */

const path = require('path');
const fs = require('fs');

class PbxEngineModule {
  constructor(config = {}) {
    this.config = config;
    this.initialized = false;
    this.services = new Map();
  }

  async initialize() {
    if (this.initialized) return;

    console.log(`Initializing PBX Core Engine...`);

    // Load module services
    
    const PBXEngine = require('./src/PBXEngine');
    this.services.set('PBXEngine', new PBXEngine(this.config));
    const CallRouter = require('./src/CallRouter');
    this.services.set('CallRouter', new CallRouter(this.config));
    const ExtensionManager = require('./src/ExtensionManager');
    this.services.set('ExtensionManager', new ExtensionManager(this.config));

    this.initialized = true;
    console.log(`✅ PBX Core Engine initialized`);
  }

  getService(serviceName) {
    return this.services.get(serviceName);
  }

  async shutdown() {
    console.log(`Shutting down PBX Core Engine...`);
    for (const [name, service] of this.services) {
      if (service.shutdown) {
        await service.shutdown();
      }
    }
    this.initialized = false;
  }
}

module.exports = PbxEngineModule;
