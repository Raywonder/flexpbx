/**
 * Interactive Voice Response
 * IVR menu system and call flow
 */

const path = require('path');
const fs = require('fs');

class IvrModule {
  constructor(config = {}) {
    this.config = config;
    this.initialized = false;
    this.services = new Map();
  }

  async initialize() {
    if (this.initialized) return;

    console.log(`Initializing Interactive Voice Response...`);

    // Load module services
    
    const IVRService = require('./src/IVRService');
    this.services.set('IVRService', new IVRService(this.config));
    const MenuBuilder = require('./src/MenuBuilder');
    this.services.set('MenuBuilder', new MenuBuilder(this.config));

    this.initialized = true;
    console.log(`✅ Interactive Voice Response initialized`);
  }

  getService(serviceName) {
    return this.services.get(serviceName);
  }

  async shutdown() {
    console.log(`Shutting down Interactive Voice Response...`);
    for (const [name, service] of this.services) {
      if (service.shutdown) {
        await service.shutdown();
      }
    }
    this.initialized = false;
  }
}

module.exports = IvrModule;
