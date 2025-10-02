const { EventEmitter } = require('events');
const { spawn, exec, execSync } = require('child_process');
const path = require('path');
const fs = require('fs-extra');
const os = require('os');
const crypto = require('crypto');

// Import existing cloud DNS service
const DNSService = require('./dnsService');

class DNSManagerService extends EventEmitter {
    constructor() {
        super();
        this.configDir = path.join(os.homedir(), '.flexpbx', 'dns');
        this.cloudDNSService = new DNSService();

        // Local DNS servers supported
        this.localServers = {
            bind: {
                name: 'BIND DNS',
                configFile: 'named.conf',
                zoneDir: 'zones',
                serviceCommand: 'named',
                ports: [53],
                protocols: ['udp', 'tcp'],
                homeServerFriendly: true
            },
            powerdns: {
                name: 'PowerDNS',
                configFile: 'pdns.conf',
                serviceCommand: 'pdns_server',
                ports: [53],
                protocols: ['udp', 'tcp'],
                apiPort: 8081,
                homeServerFriendly: true
            },
            unbound: {
                name: 'Unbound DNS',
                configFile: 'unbound.conf',
                serviceCommand: 'unbound',
                ports: [53],
                protocols: ['udp', 'tcp'],
                homeServerFriendly: true
            },
            dnsmasq: {
                name: 'DNSmasq',
                configFile: 'dnsmasq.conf',
                serviceCommand: 'dnsmasq',
                ports: [53],
                protocols: ['udp', 'tcp'],
                homeServerFriendly: true,
                lightweight: true
            },
            coredns: {
                name: 'CoreDNS',
                configFile: 'Corefile',
                serviceCommand: 'coredns',
                ports: [53],
                protocols: ['udp', 'tcp'],
                homeServerFriendly: true,
                modern: true
            }
        };

        this.installedLocalServers = new Map();
        this.runningLocalServers = new Map();
        this.flexpbxZones = new Map();
        this.remoteServers = new Map();
        this.syncConfig = new Map();

        this.setupDirectories();
        this.detectInstalledServers();
    }

    async setupDirectories() {
        await fs.ensureDir(this.configDir);
        await fs.ensureDir(path.join(this.configDir, 'zones'));
        await fs.ensureDir(path.join(this.configDir, 'logs'));
        await fs.ensureDir(path.join(this.configDir, 'backups'));
        await fs.ensureDir(path.join(this.configDir, 'remote'));
        await fs.ensureDir(path.join(this.configDir, 'sync'));

        // Create directories for each local DNS server type
        for (const serverType of Object.keys(this.localServers)) {
            await fs.ensureDir(path.join(this.configDir, serverType));
            await fs.ensureDir(path.join(this.configDir, serverType, 'zones'));
        }

        console.log('📁 DNS service directories initialized');
    }

    async detectInstalledServers() {
        console.log('🔍 Detecting installed local DNS servers...');

        for (const [serverType, config] of Object.entries(this.localServers)) {
            try {
                const detected = await this.detectLocalServer(serverType, config);
                if (detected.installed) {
                    this.installedLocalServers.set(serverType, detected);
                    console.log(`✅ ${config.name} detected: ${detected.version}`);
                }
            } catch (error) {
                console.log(`⚠️ ${config.name} not found`);
            }
        }

        console.log(`📊 Found ${this.installedLocalServers.size} local DNS servers installed`);
    }

    async detectLocalServer(serverType, config) {
        const commands = {
            bind: ['named', '-v'],
            powerdns: ['pdns_server', '--version'],
            unbound: ['unbound', '-V'],
            dnsmasq: ['dnsmasq', '--version'],
            coredns: ['coredns', '-version']
        };

        const command = commands[serverType];
        if (!command) {
            throw new Error(`Unknown server type: ${serverType}`);
        }

        try {
            const output = execSync(command.join(' '), {
                encoding: 'utf8',
                timeout: 5000,
                stdio: ['ignore', 'pipe', 'pipe']
            });

            const version = this.parseVersion(serverType, output);
            const configPath = await this.findConfigPath(serverType, config);

            return {
                installed: true,
                version,
                configPath,
                serverType,
                config
            };
        } catch (error) {
            return {
                installed: false,
                serverType,
                config
            };
        }
    }

    parseVersion(serverType, output) {
        const versionPatterns = {
            bind: /BIND\s+([\d\.]+)/i,
            powerdns: /PowerDNS\s+([\d\.]+)/i,
            unbound: /Version\s+([\d\.]+)/i,
            dnsmasq: /Dnsmasq version\s+([\d\.]+)/i,
            coredns: /CoreDNS-([\d\.]+)/i
        };

        const pattern = versionPatterns[serverType];
        if (pattern) {
            const match = output.match(pattern);
            return match ? match[1] : 'unknown';
        }
        return 'unknown';
    }

    async findConfigPath(serverType, config) {
        const commonPaths = {
            bind: [
                '/etc/bind/named.conf',
                '/etc/named.conf',
                '/usr/local/etc/named.conf',
                '/opt/homebrew/etc/bind/named.conf'
            ],
            powerdns: [
                '/etc/powerdns/pdns.conf',
                '/etc/pdns/pdns.conf',
                '/usr/local/etc/pdns.conf'
            ],
            unbound: [
                '/etc/unbound/unbound.conf',
                '/usr/local/etc/unbound/unbound.conf',
                '/opt/homebrew/etc/unbound/unbound.conf'
            ],
            dnsmasq: [
                '/etc/dnsmasq.conf',
                '/usr/local/etc/dnsmasq.conf',
                '/opt/homebrew/etc/dnsmasq.conf'
            ],
            coredns: [
                '/etc/coredns/Corefile',
                './Corefile'
            ]
        };

        const paths = commonPaths[serverType] || [];
        for (const configPath of paths) {
            if (await fs.pathExists(configPath)) {
                return configPath;
            }
        }

        return null;
    }

    async createFlexPBXZone(domain, options = {}) {
        const {
            localDNS = null,
            cloudDNS = null,
            ip = this.getLocalIP(),
            ttl = 300,
            homeServer = false,
            syncToRemote = false,
            remoteServers = []
        } = options;

        console.log(`🌐 Creating comprehensive FlexPBX DNS zone for ${domain}`);

        const results = {
            local: null,
            cloud: null,
            remote: [],
            domain,
            ip
        };

        // Create local DNS zone if requested
        if (localDNS && this.installedLocalServers.has(localDNS)) {
            try {
                results.local = await this.createLocalZone(localDNS, domain, { ip, ttl, homeServer });
                console.log(`✅ Local zone created with ${this.localServers[localDNS].name}`);
            } catch (error) {
                console.error(`❌ Failed to create local zone:`, error);
                results.local = { success: false, error: error.message };
            }
        }

        // Create cloud DNS records if requested
        if (cloudDNS) {
            try {
                results.cloud = await this.cloudDNSService.createARecord({
                    provider: cloudDNS.provider,
                    domain: cloudDNS.domain,
                    subdomain: cloudDNS.subdomain,
                    ipAddress: ip,
                    ttl,
                    credentials: cloudDNS.credentials
                });
                console.log(`✅ Cloud DNS record created with ${cloudDNS.provider}`);
            } catch (error) {
                console.error(`❌ Failed to create cloud DNS record:`, error);
                results.cloud = { success: false, error: error.message };
            }
        }

        // Sync to remote servers if requested
        if (syncToRemote && remoteServers.length > 0) {
            results.remote = await this.syncZoneToRemoteServers(domain, remoteServers, { ip, ttl, homeServer });
        }

        // Store zone configuration
        this.flexpbxZones.set(domain, {
            localDNS,
            cloudDNS,
            ip,
            ttl,
            homeServer,
            syncToRemote,
            remoteServers,
            createdAt: new Date().toISOString(),
            results
        });

        await this.saveZoneConfig();

        return {
            success: true,
            domain,
            ip,
            results,
            message: `FlexPBX zone ${domain} created successfully`
        };
    }

    async createLocalZone(serverType, domain, options) {
        const { ip, ttl, homeServer } = options;
        const serial = Math.floor(Date.now() / 1000);

        const zoneConfig = this.generateLocalZoneConfig(serverType, domain, {
            ip, ttl, serial, homeServer
        });

        const zonePath = path.join(this.configDir, serverType, 'zones', `${domain}.zone`);
        await fs.writeFile(zonePath, zoneConfig);

        // Update main DNS server configuration
        await this.updateLocalDNSConfig(serverType, domain, zonePath);

        return {
            success: true,
            serverType,
            zonePath,
            message: `Local zone created with ${this.localServers[serverType].name}`
        };
    }

    generateLocalZoneConfig(serverType, domain, options) {
        const { ip, ttl, serial, homeServer } = options;
        const refresh = 3600;
        const retry = 1800;
        const expire = 604800;
        const minimum = 86400;

        switch (serverType) {
            case 'bind':
                return this.generateBINDZone(domain, { ip, ttl, serial, refresh, retry, expire, minimum, homeServer });
            case 'powerdns':
                return this.generatePowerDNSZone(domain, { ip, ttl, serial, homeServer });
            case 'unbound':
                return this.generateUnboundZone(domain, { ip, ttl, homeServer });
            case 'dnsmasq':
                return this.generateDNSmasqZone(domain, { ip, homeServer });
            case 'coredns':
                return this.generateCoreDNSZone(domain, { ip, ttl, homeServer });
            default:
                throw new Error(`Unsupported server type: ${serverType}`);
        }
    }

    generateBINDZone(domain, options) {
        const { ip, ttl, serial, refresh, retry, expire, minimum, homeServer } = options;

        const homeServerEntries = homeServer ? `
; Home Server Services
homeassistant IN A     ${ip}
nas         IN  A       ${ip}
media       IN  A       ${ip}
backup      IN  A       ${ip}
plex        IN  A       ${ip}
jellyfin    IN  A       ${ip}
nextcloud   IN  A       ${ip}
pihole      IN  A       ${ip}
router      IN  A       ${ip}
camera      IN  A       ${ip}
iot         IN  A       ${ip}
monitoring  IN  A       ${ip}` : '';

        return `; FlexPBX Zone file for ${domain}
; Generated on ${new Date().toISOString()}
; ${homeServer ? 'Home Server Configuration' : 'Standard Configuration'}
$TTL ${ttl}
$ORIGIN ${domain}.

@       IN  SOA     ns1.${domain}. admin.${domain}. (
                    ${serial}     ; Serial
                    ${refresh}    ; Refresh
                    ${retry}      ; Retry
                    ${expire}     ; Expire
                    ${minimum}    ; Minimum TTL
)

; Name servers
@       IN  NS      ns1.${domain}.

; A records
@       IN  A       ${ip}
ns1     IN  A       ${ip}
www     IN  A       ${ip}

; FlexPBX services
flexpbx     IN  A       ${ip}
pbx         IN  A       ${ip}
admin       IN  A       ${ip}
voip        IN  A       ${ip}
sip         IN  A       ${ip}

; CopyParty file server (perfect for remote management)
files       IN  A       ${ip}
share       IN  A       ${ip}
upload      IN  A       ${ip}
deploy      IN  A       ${ip}
sync        IN  A       ${ip}
${homeServerEntries}

; SRV records for SIP
_sip._udp   IN  SRV     10 5 5060 sip.${domain}.
_sip._tcp   IN  SRV     10 5 5060 sip.${domain}.
_sips._tcp  IN  SRV     10 5 5061 sip.${domain}.

; TXT records
@           IN  TXT     "v=spf1 a mx ip4:${ip} ~all"
@           IN  TXT     "FlexPBX-Server-v2.0"
${homeServer ? '@           IN  TXT     "home-server-enabled"' : ''}
`;
    }

    generatePowerDNSZone(domain, options) {
        const { ip, ttl, serial, homeServer } = options;

        const homeServerEntries = homeServer ? `
homeassistant.${domain}. ${ttl} IN  A       ${ip}
nas.${domain}.          ${ttl}  IN  A       ${ip}
media.${domain}.        ${ttl}  IN  A       ${ip}
backup.${domain}.       ${ttl}  IN  A       ${ip}
plex.${domain}.         ${ttl}  IN  A       ${ip}
jellyfin.${domain}.     ${ttl}  IN  A       ${ip}
nextcloud.${domain}.    ${ttl}  IN  A       ${ip}
pihole.${domain}.       ${ttl}  IN  A       ${ip}
router.${domain}.       ${ttl}  IN  A       ${ip}
camera.${domain}.       ${ttl}  IN  A       ${ip}
iot.${domain}.          ${ttl}  IN  A       ${ip}
monitoring.${domain}.   ${ttl}  IN  A       ${ip}` : '';

        return `; PowerDNS Zone for ${domain}
; FlexPBX ${homeServer ? 'Home Server' : 'Standard'} Configuration
${domain}.              ${ttl}  IN  SOA     ns1.${domain}. admin.${domain}. ${serial} 3600 1800 604800 86400
${domain}.              ${ttl}  IN  NS      ns1.${domain}.
${domain}.              ${ttl}  IN  A       ${ip}
ns1.${domain}.          ${ttl}  IN  A       ${ip}
www.${domain}.          ${ttl}  IN  A       ${ip}
flexpbx.${domain}.      ${ttl}  IN  A       ${ip}
pbx.${domain}.          ${ttl}  IN  A       ${ip}
admin.${domain}.        ${ttl}  IN  A       ${ip}
voip.${domain}.         ${ttl}  IN  A       ${ip}
sip.${domain}.          ${ttl}  IN  A       ${ip}
files.${domain}.        ${ttl}  IN  A       ${ip}
share.${domain}.        ${ttl}  IN  A       ${ip}
upload.${domain}.       ${ttl}  IN  A       ${ip}
deploy.${domain}.       ${ttl}  IN  A       ${ip}
sync.${domain}.         ${ttl}  IN  A       ${ip}
${homeServerEntries}
_sip._udp.${domain}.    ${ttl}  IN  SRV     10 5 5060 sip.${domain}.
_sip._tcp.${domain}.    ${ttl}  IN  SRV     10 5 5060 sip.${domain}.
_sips._tcp.${domain}.   ${ttl}  IN  SRV     10 5 5061 sip.${domain}.
`;
    }

    generateUnboundZone(domain, options) {
        const { ip, ttl, homeServer } = options;

        const homeServerEntries = homeServer ? `
local-data: "homeassistant.${domain}. ${ttl} IN A ${ip}"
local-data: "nas.${domain}. ${ttl} IN A ${ip}"
local-data: "media.${domain}. ${ttl} IN A ${ip}"
local-data: "backup.${domain}. ${ttl} IN A ${ip}"
local-data: "plex.${domain}. ${ttl} IN A ${ip}"
local-data: "jellyfin.${domain}. ${ttl} IN A ${ip}"
local-data: "nextcloud.${domain}. ${ttl} IN A ${ip}"
local-data: "pihole.${domain}. ${ttl} IN A ${ip}"
local-data: "router.${domain}. ${ttl} IN A ${ip}"
local-data: "camera.${domain}. ${ttl} IN A ${ip}"
local-data: "iot.${domain}. ${ttl} IN A ${ip}"
local-data: "monitoring.${domain}. ${ttl} IN A ${ip}"` : '';

        return `# Unbound local zone for ${domain}
# FlexPBX ${homeServer ? 'Home Server' : 'Standard'} Configuration
local-zone: "${domain}" static

local-data: "${domain}. ${ttl} IN A ${ip}"
local-data: "www.${domain}. ${ttl} IN A ${ip}"
local-data: "flexpbx.${domain}. ${ttl} IN A ${ip}"
local-data: "pbx.${domain}. ${ttl} IN A ${ip}"
local-data: "admin.${domain}. ${ttl} IN A ${ip}"
local-data: "voip.${domain}. ${ttl} IN A ${ip}"
local-data: "sip.${domain}. ${ttl} IN A ${ip}"
local-data: "files.${domain}. ${ttl} IN A ${ip}"
local-data: "share.${domain}. ${ttl} IN A ${ip}"
local-data: "upload.${domain}. ${ttl} IN A ${ip}"
local-data: "deploy.${domain}. ${ttl} IN A ${ip}"
local-data: "sync.${domain}. ${ttl} IN A ${ip}"
${homeServerEntries}
`;
    }

    generateDNSmasqZone(domain, options) {
        const { ip, homeServer } = options;

        const homeServerEntries = homeServer ? `
address=/homeassistant.${domain}/${ip}
address=/nas.${domain}/${ip}
address=/media.${domain}/${ip}
address=/backup.${domain}/${ip}
address=/plex.${domain}/${ip}
address=/jellyfin.${domain}/${ip}
address=/nextcloud.${domain}/${ip}
address=/pihole.${domain}/${ip}
address=/router.${domain}/${ip}
address=/camera.${domain}/${ip}
address=/iot.${domain}/${ip}
address=/monitoring.${domain}/${ip}` : '';

        return `# DNSmasq configuration for ${domain}
# FlexPBX ${homeServer ? 'Home Server' : 'Standard'} Configuration
address=/${domain}/${ip}
address=/www.${domain}/${ip}
address=/flexpbx.${domain}/${ip}
address=/pbx.${domain}/${ip}
address=/admin.${domain}/${ip}
address=/voip.${domain}/${ip}
address=/sip.${domain}/${ip}
address=/files.${domain}/${ip}
address=/share.${domain}/${ip}
address=/upload.${domain}/${ip}
address=/deploy.${domain}/${ip}
address=/sync.${domain}/${ip}
${homeServerEntries}

# SRV records for SIP
srv-host=_sip._udp.${domain},sip.${domain},5060,10,5
srv-host=_sip._tcp.${domain},sip.${domain},5060,10,5
srv-host=_sips._tcp.${domain},sip.${domain},5061,10,5
`;
    }

    generateCoreDNSZone(domain, options) {
        const { ip, ttl, homeServer } = options;

        const homeServerEntries = homeServer ? `
homeassistant.${domain}. ${ttl} IN  A       ${ip}
nas.${domain}.          ${ttl}  IN  A       ${ip}
media.${domain}.        ${ttl}  IN  A       ${ip}
backup.${domain}.       ${ttl}  IN  A       ${ip}
plex.${domain}.         ${ttl}  IN  A       ${ip}
jellyfin.${domain}.     ${ttl}  IN  A       ${ip}
nextcloud.${domain}.    ${ttl}  IN  A       ${ip}
pihole.${domain}.       ${ttl}  IN  A       ${ip}
router.${domain}.       ${ttl}  IN  A       ${ip}
camera.${domain}.       ${ttl}  IN  A       ${ip}
iot.${domain}.          ${ttl}  IN  A       ${ip}
monitoring.${domain}.   ${ttl}  IN  A       ${ip}` : '';

        return `${domain}:53 {
    file ${domain}.zone
    log
}

; Zone file content for ${domain}
; FlexPBX ${homeServer ? 'Home Server' : 'Standard'} Configuration
${domain}.              ${ttl}  IN  SOA     ns1.${domain}. admin.${domain}. 1 3600 1800 604800 86400
${domain}.              ${ttl}  IN  NS      ns1.${domain}.
${domain}.              ${ttl}  IN  A       ${ip}
ns1.${domain}.          ${ttl}  IN  A       ${ip}
www.${domain}.          ${ttl}  IN  A       ${ip}
flexpbx.${domain}.      ${ttl}  IN  A       ${ip}
pbx.${domain}.          ${ttl}  IN  A       ${ip}
admin.${domain}.        ${ttl}  IN  A       ${ip}
voip.${domain}.         ${ttl}  IN  A       ${ip}
sip.${domain}.          ${ttl}  IN  A       ${ip}
files.${domain}.        ${ttl}  IN  A       ${ip}
share.${domain}.        ${ttl}  IN  A       ${ip}
upload.${domain}.       ${ttl}  IN  A       ${ip}
deploy.${domain}.       ${ttl}  IN  A       ${ip}
sync.${domain}.         ${ttl}  IN  A       ${ip}
${homeServerEntries}
`;
    }

    async updateLocalDNSConfig(serverType, domain, zonePath) {
        const serverInfo = this.installedLocalServers.get(serverType);
        if (!serverInfo || !serverInfo.configPath) {
            console.log(`No config path found for ${serverType}`);
            return;
        }

        switch (serverType) {
            case 'bind':
                await this.updateBINDConfig(serverInfo.configPath, domain, zonePath);
                break;
            case 'powerdns':
                await this.updatePowerDNSConfig(serverInfo.configPath, domain, zonePath);
                break;
            case 'unbound':
                await this.updateUnboundConfig(serverInfo.configPath, domain, zonePath);
                break;
            case 'dnsmasq':
                await this.updateDNSmasqConfig(serverInfo.configPath, domain, zonePath);
                break;
            case 'coredns':
                await this.updateCoreDNSConfig(serverInfo.configPath, domain, zonePath);
                break;
        }
    }

    async updateBINDConfig(configPath, domain, zonePath) {
        const zoneEntry = `zone "${domain}" {
    type master;
    file "${zonePath}";
    allow-query { any; };
    allow-transfer { none; };
};`;

        await this.appendToConfig(configPath, zoneEntry, '// FlexPBX zones');
    }

    async updatePowerDNSConfig(configPath, domain, zonePath) {
        const config = `bind-config=${zonePath}
bind-check-interval=5`;
        await this.appendToConfig(configPath, config, '# FlexPBX zones');
    }

    async updateUnboundConfig(configPath, domain, zonePath) {
        const config = `include: "${zonePath}"`;
        await this.appendToConfig(configPath, config, '# FlexPBX zones');
    }

    async updateDNSmasqConfig(configPath, domain, zonePath) {
        const config = `conf-file=${zonePath}`;
        await this.appendToConfig(configPath, config, '# FlexPBX zones');
    }

    async updateCoreDNSConfig(configPath, domain, zonePath) {
        const config = `${domain}:53 {
    file ${zonePath}
    log
}`;
        await this.appendToConfig(configPath, config, '# FlexPBX zones');
    }

    async appendToConfig(configPath, content, marker) {
        try {
            const backupPath = `${configPath}.flexpbx.backup.${Date.now()}`;
            await fs.copy(configPath, backupPath);

            let existingConfig = '';
            if (await fs.pathExists(configPath)) {
                existingConfig = await fs.readFile(configPath, 'utf8');
            }

            if (!existingConfig.includes(marker)) {
                const newConfig = existingConfig + `\n\n${marker}\n${content}\n`;
                await fs.writeFile(configPath, newConfig);
                console.log(`✅ Updated ${configPath} with FlexPBX zone`);
            } else {
                console.log(`ℹ️ FlexPBX zones already configured in ${configPath}`);
            }
        } catch (error) {
            console.error(`Failed to update config ${configPath}:`, error);
            throw error;
        }
    }

    async syncZoneToRemoteServers(domain, remoteServers, options) {
        console.log(`🔄 Syncing zone ${domain} to ${remoteServers.length} remote servers...`);

        const syncResults = [];

        for (const remoteServer of remoteServers) {
            try {
                const result = await this.syncZoneToRemoteServer(domain, remoteServer, options);
                syncResults.push({ server: remoteServer, success: true, result });
                console.log(`✅ Synced ${domain} to ${remoteServer.host}`);
            } catch (error) {
                syncResults.push({ server: remoteServer, success: false, error: error.message });
                console.error(`❌ Failed to sync ${domain} to ${remoteServer.host}:`, error);
            }
        }

        return syncResults;
    }

    async syncZoneToRemoteServer(domain, remoteServer, options) {
        const { host, username, sshKey, dnsType = 'bind', remotePath } = remoteServer;
        const { ip, ttl, homeServer } = options;

        // Generate zone config for remote server
        const zoneConfig = this.generateLocalZoneConfig(dnsType, domain, {
            ip, ttl, serial: Math.floor(Date.now() / 1000), homeServer
        });

        // Use SSH to deploy zone file to remote server
        const SSHKeyService = require('./SSHKeyService');
        const sshService = new SSHKeyService();

        const connection = await sshService.connectToServer({
            host,
            username,
            privateKey: sshKey
        });

        if (!connection.success) {
            throw new Error(`Failed to connect to ${host}: ${connection.error}`);
        }

        // Upload zone file
        const remoteZonePath = remotePath || `/etc/${dnsType}/zones/${domain}.zone`;
        const tempLocalPath = path.join(this.configDir, 'temp', `${domain}.zone`);

        await fs.ensureDir(path.dirname(tempLocalPath));
        await fs.writeFile(tempLocalPath, zoneConfig);

        const uploadResult = await sshService.uploadFile(connection.connection, tempLocalPath, remoteZonePath);

        if (!uploadResult.success) {
            throw new Error(`Failed to upload zone file: ${uploadResult.error}`);
        }

        // Restart remote DNS service
        const restartCommand = this.getRestartCommand(dnsType);
        const restartResult = await sshService.executeCommand(connection.connection, restartCommand);

        // Cleanup
        await fs.remove(tempLocalPath);
        connection.connection.dispose();

        return {
            uploaded: uploadResult.success,
            restarted: restartResult.success,
            remoteZonePath
        };
    }

    getRestartCommand(dnsType) {
        const restartCommands = {
            bind: 'sudo systemctl reload bind9 || sudo service bind9 reload',
            powerdns: 'sudo systemctl reload pdns || sudo service pdns reload',
            unbound: 'sudo systemctl reload unbound || sudo service unbound reload',
            dnsmasq: 'sudo systemctl reload dnsmasq || sudo service dnsmasq reload',
            coredns: 'sudo systemctl reload coredns || sudo killall -USR1 coredns'
        };

        return restartCommands[dnsType] || `sudo systemctl reload ${dnsType}`;
    }

    async saveZoneConfig() {
        const configPath = path.join(this.configDir, 'zones.json');
        const zonesConfig = Object.fromEntries(this.flexpbxZones);
        await fs.writeJSON(configPath, zonesConfig, { spaces: 2 });
    }

    getLocalIP() {
        const interfaces = os.networkInterfaces();
        for (const [name, configs] of Object.entries(interfaces)) {
            for (const config of configs) {
                if (!config.internal && config.family === 'IPv4') {
                    return config.address;
                }
            }
        }
        return '127.0.0.1';
    }

    getStatus() {
        const installedLocal = Array.from(this.installedLocalServers.entries()).map(([type, info]) => ({
            type,
            name: this.localServers[type].name,
            version: info.version,
            configPath: info.configPath,
            running: this.runningLocalServers.has(type),
            homeServerFriendly: this.localServers[type].homeServerFriendly
        }));

        const cloudProviders = this.cloudDNSService.getSupportedProviders();

        const zones = Array.from(this.flexpbxZones.entries()).map(([domain, info]) => ({
            domain,
            localDNS: info.localDNS,
            cloudDNS: info.cloudDNS?.provider || null,
            ip: info.ip,
            homeServer: info.homeServer,
            syncToRemote: info.syncToRemote,
            remoteServers: info.remoteServers?.length || 0,
            createdAt: info.createdAt
        }));

        return {
            localServers: {
                installed: installedLocal,
                supported: Object.keys(this.localServers)
            },
            cloudProviders,
            zones,
            homeServerReady: this.installedLocalServers.size > 0,
            cloudDNSAvailable: true,
            remoteSyncEnabled: this.remoteServers.size > 0
        };
    }

    // Cloud DNS passthrough methods
    async createCloudDNSRecord(config) {
        return await this.cloudDNSService.createARecord(config);
    }

    async verifyDNSRecord(hostname, expectedIp) {
        return await this.cloudDNSService.verifyRecord(hostname, expectedIp);
    }

    async getCurrentPublicIP() {
        return await this.cloudDNSService.getCurrentPublicIP();
    }

    getSupportedCloudProviders() {
        return this.cloudDNSService.getSupportedProviders();
    }

    getCloudProviderSchema(provider) {
        return this.cloudDNSService.getProviderCredentialsSchema(provider);
    }
}

module.exports = DNSManagerService;