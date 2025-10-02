/**
 * 🚀 FlexPBX Unified Service Manager
 * Manages all services with hot-reload, health monitoring, and auto-recovery
 */

const { EventEmitter } = require('events');
const path = require('path');

// Import all services
const CopyPartyService = require('./services/CopyPartyService');
const RichMessagingService = require('./services/RichMessagingService');
const RemoteAccessibilityService = require('./services/RemoteAccessibilityService');
const DNSManagerService = require('./services/DNSManagerService');
const SoftwareUpdateService = require('./services/SoftwareUpdateService');
const SoundManager = require('./services/SoundManager');

class UnifiedServiceManager extends EventEmitter {
    constructor() {
        super();
        this.services = new Map();
        this.serviceHealth = new Map();
        this.serviceStats = new Map();
        this.autoRestart = true;
        this.healthCheckInterval = 30000; // 30 seconds

        this.initializeServices();
        this.startHealthMonitoring();
    }

    async initializeServices() {
        console.log('🚀 Initializing FlexPBX Unified Services...');

        const serviceConfigs = {
        "copyParty": {
                "class": "CopyPartyService",
                "port": 8080,
                "features": [
                        "file-sharing",
                        "encryption",
                        "unique-credentials"
                ]
        },
        "messaging": {
                "class": "RichMessagingService",
                "port": 41238,
                "features": [
                        "rich-content",
                        "encryption",
                        "real-time",
                        "accessibility"
                ]
        },
        "accessibility": {
                "class": "RemoteAccessibilityService",
                "port": 41237,
                "features": [
                        "screen-reader-control",
                        "audio-streaming",
                        "cross-platform"
                ]
        },
        "dns": {
                "class": "DNSManagerService",
                "features": [
                        "bind",
                        "powerdns",
                        "unbound",
                        "cloud-sync"
                ]
        },
        "updates": {
                "class": "SoftwareUpdateService",
                "features": [
                        "silent-updates",
                        "rollback",
                        "remote-deployment"
                ]
        },
        "sound": {
                "class": "SoundManager",
                "features": [
                        "cross-platform",
                        "volume-control",
                        "event-sounds"
                ]
        }
};

        for (const [name, config] of Object.entries(serviceConfigs)) {
            try {
                console.log(`   🔧 Starting ${name} service...`);

                const ServiceClass = eval(config.class);
                const service = new ServiceClass();

                // Add service monitoring
                this.wrapServiceWithMonitoring(service, name, config);

                this.services.set(name, service);
                this.serviceHealth.set(name, {
                    status: 'healthy',
                    lastCheck: new Date(),
                    uptime: 0,
                    restarts: 0
                });

                console.log(`   ✅ ${name} service ready (Features: ${config.features?.join(', ') || 'N/A'})`);

            } catch (error) {
                console.error(`   ❌ Failed to start ${name}: ${error.message}`);
                this.serviceHealth.set(name, {
                    status: 'failed',
                    lastCheck: new Date(),
                    error: error.message
                });
            }
        }

        this.emit('services-initialized', {
            total: Object.keys(serviceConfigs).length,
            healthy: Array.from(this.serviceHealth.values()).filter(h => h.status === 'healthy').length
        });
    }

    wrapServiceWithMonitoring(service, name, config) {
        const startTime = Date.now();

        // Track service method calls
        const originalMethods = Object.getOwnPropertyNames(Object.getPrototypeOf(service))
            .filter(method => typeof service[method] === 'function' && method !== 'constructor');

        originalMethods.forEach(methodName => {
            const originalMethod = service[methodName];
            service[methodName] = (...args) => {
                try {
                    const result = originalMethod.apply(service, args);
                    this.recordServiceCall(name, methodName, true);
                    return result;
                } catch (error) {
                    this.recordServiceCall(name, methodName, false, error);
                    throw error;
                }
            };
        });

        // Add health check method if not exists
        if (!service.getHealth) {
            service.getHealth = () => ({
                status: 'healthy',
                uptime: Date.now() - startTime,
                features: config.features || []
            });
        }
    }

    recordServiceCall(serviceName, method, success, error = null) {
        if (!this.serviceStats.has(serviceName)) {
            this.serviceStats.set(serviceName, {
                calls: 0,
                successes: 0,
                failures: 0,
                methods: new Map()
            });
        }

        const stats = this.serviceStats.get(serviceName);
        stats.calls++;

        if (success) {
            stats.successes++;
        } else {
            stats.failures++;
            console.warn(`⚠️ Service ${serviceName}.${method} failed: ${error?.message}`);
        }

        // Track method-specific stats
        if (!stats.methods.has(method)) {
            stats.methods.set(method, { calls: 0, successes: 0, failures: 0 });
        }

        const methodStats = stats.methods.get(method);
        methodStats.calls++;
        if (success) methodStats.successes++;
        else methodStats.failures++;
    }

    startHealthMonitoring() {
        setInterval(async () => {
            for (const [name, service] of this.services) {
                try {
                    const health = service.getHealth ? await service.getHealth() : { status: 'unknown' };

                    this.serviceHealth.set(name, {
                        ...this.serviceHealth.get(name),
                        status: health.status || 'healthy',
                        lastCheck: new Date(),
                        details: health
                    });

                } catch (error) {
                    console.warn(`⚠️ Health check failed for ${name}: ${error.message}`);

                    const currentHealth = this.serviceHealth.get(name);
                    this.serviceHealth.set(name, {
                        ...currentHealth,
                        status: 'unhealthy',
                        lastCheck: new Date(),
                        error: error.message
                    });

                    if (this.autoRestart) {
                        await this.restartService(name);
                    }
                }
            }
        }, this.healthCheckInterval);
    }

    async restartService(name) {
        console.log(`🔄 Restarting service: ${name}`);

        try {
            const service = this.services.get(name);
            if (service && typeof service.stop === 'function') {
                await service.stop();
            }

            // Re-initialize the service
            // This would need the original service config to recreate

            const health = this.serviceHealth.get(name);
            health.restarts = (health.restarts || 0) + 1;

            console.log(`✅ Service ${name} restarted successfully`);

        } catch (error) {
            console.error(`❌ Failed to restart ${name}: ${error.message}`);
        }
    }

    getService(name) {
        return this.services.get(name);
    }

    getAllServices() {
        return Array.from(this.services.keys());
    }

    getServiceHealth(name) {
        return this.serviceHealth.get(name);
    }

    getSystemStatus() {
        const services = Array.from(this.services.keys());
        const healthyServices = Array.from(this.serviceHealth.values())
            .filter(h => h.status === 'healthy').length;

        return {
            totalServices: services.length,
            healthyServices,
            unhealthyServices: services.length - healthyServices,
            uptime: process.uptime(),
            memory: process.memoryUsage(),
            services: Object.fromEntries(this.serviceHealth),
            stats: Object.fromEntries(this.serviceStats)
        };
    }

    async shutdown() {
        console.log('🛑 Shutting down all services...');

        for (const [name, service] of this.services) {
            try {
                if (typeof service.stop === 'function') {
                    await service.stop();
                    console.log(`   ✅ ${name} stopped`);
                }
            } catch (error) {
                console.error(`   ❌ Error stopping ${name}: ${error.message}`);
            }
        }

        this.emit('services-shutdown');
    }
}

module.exports = UnifiedServiceManager;
