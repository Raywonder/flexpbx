/**
 * Extension System
 * Extension management and configuration
 */

const path = require('path');
const fs = require('fs');

class ExtensionsModule {
  constructor(config = {}) {
    this.config = config;
    this.initialized = false;
    this.services = new Map();
  }

  async initialize() {
    if (this.initialized) return;

    console.log(`Initializing Extension System...`);

    // Load module services
    
    const ExtensionService = require('./src/ExtensionService');
    this.services.set('ExtensionService', new ExtensionService(this.config));
    const ExtensionDatabase = require('./src/ExtensionDatabase');
    this.services.set('ExtensionDatabase', new ExtensionDatabase(this.config));

    this.initialized = true;
    console.log(`✅ Extension System initialized`);
  }

  getService(serviceName) {
    return this.services.get(serviceName);
  }

  async shutdown() {
    console.log(`Shutting down Extension System...`);
    for (const [name, service] of this.services) {
      if (service.shutdown) {
        await service.shutdown();
      }
    }
    this.initialized = false;
  }
}

module.exports = ExtensionsModule;
