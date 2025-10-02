/**
 * License Management
 * License validation and feature management
 */

const path = require('path');
const fs = require('fs');

class LicensingModule {
  constructor(config = {}) {
    this.config = config;
    this.initialized = false;
    this.services = new Map();
  }

  async initialize() {
    if (this.initialized) return;

    console.log(`Initializing License Management...`);

    // Load module services
    
    const LicensingService = require('./src/LicensingService');
    this.services.set('LicensingService', new LicensingService(this.config));
    const LicenseValidator = require('./src/LicenseValidator');
    this.services.set('LicenseValidator', new LicenseValidator(this.config));

    this.initialized = true;
    console.log(`✅ License Management initialized`);
  }

  getService(serviceName) {
    return this.services.get(serviceName);
  }

  async shutdown() {
    console.log(`Shutting down License Management...`);
    for (const [name, service] of this.services) {
      if (service.shutdown) {
        await service.shutdown();
      }
    }
    this.initialized = false;
  }
}

module.exports = LicensingModule;
