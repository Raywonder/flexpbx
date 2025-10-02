/**
 * SIP Trunk Management
 * SIP trunk configuration and management
 */

const path = require('path');
const fs = require('fs');

class SipTrunkModule {
  constructor(config = {}) {
    this.config = config;
    this.initialized = false;
    this.services = new Map();
  }

  async initialize() {
    if (this.initialized) return;

    console.log(`Initializing SIP Trunk Management...`);

    // Load module services
    
    const SIPTrunkService = require('./src/SIPTrunkService');
    this.services.set('SIPTrunkService', new SIPTrunkService(this.config));
    const ProviderManager = require('./src/ProviderManager');
    this.services.set('ProviderManager', new ProviderManager(this.config));

    this.initialized = true;
    console.log(`✅ SIP Trunk Management initialized`);
  }

  getService(serviceName) {
    return this.services.get(serviceName);
  }

  async shutdown() {
    console.log(`Shutting down SIP Trunk Management...`);
    for (const [name, service] of this.services) {
      if (service.shutdown) {
        await service.shutdown();
      }
    }
    this.initialized = false;
  }
}

module.exports = SipTrunkModule;
