const fs = require('fs-extra');
const path = require('path');
const { NodeSSH } = require('node-ssh');

class HookIntegrationService {
    constructor() {
        this.supportedPanels = [
            'cpanel',
            'whm',
            'whmcs',
            'plesk',
            'directadmin',
            'cyberpanel',
            'aapanel',
            'vestacp',
            'hestiacp',
            'ispconfig'
        ];

        this.hookTypes = {
            account_create: 'Account Creation',
            account_suspend: 'Account Suspension',
            account_unsuspend: 'Account Unsuspension',
            account_terminate: 'Account Termination',
            domain_create: 'Domain Creation',
            subdomain_create: 'Subdomain Creation',
            ssl_install: 'SSL Installation',
            email_create: 'Email Account Creation',
            database_create: 'Database Creation'
        };
    }

    /**
     * Detect control panels and their hook capabilities
     */
    async detectControlPanels(ssh) {
        const detection = {
            cpanel: false,
            whm: false,
            whmcs: false,
            plesk: false,
            directadmin: false,
            cyberpanel: false,
            aapanel: false,
            vestacp: false,
            hestiacp: false,
            ispconfig: false,
            custom: false
        };

        try {
            // cPanel/WHM detection
            const cpanelCheck = await ssh.execCommand('[ -d /usr/local/cpanel ] && echo "found"');
            if (cpanelCheck.stdout.includes('found')) {
                detection.cpanel = true;
                detection.whm = true;
            }

            // WHMCS detection
            const whmcsCheck = await ssh.execCommand('find /home -name "configuration.php" -path "*/whmcs/*" 2>/dev/null | head -1');
            if (whmcsCheck.stdout.trim()) {
                detection.whmcs = true;
            }

            // Plesk detection
            const pleskCheck = await ssh.execCommand('[ -f /usr/local/psa/version ] && echo "found"');
            if (pleskCheck.stdout.includes('found')) {
                detection.plesk = true;
            }

            // DirectAdmin detection
            const daCheck = await ssh.execCommand('[ -d /usr/local/directadmin ] && echo "found"');
            if (daCheck.stdout.includes('found')) {
                detection.directadmin = true;
            }

            // CyberPanel detection
            const cyberCheck = await ssh.execCommand('[ -f /usr/local/CyberCP/version.txt ] && echo "found"');
            if (cyberCheck.stdout.includes('found')) {
                detection.cyberpanel = true;
            }

            // aaPanel detection
            const aaCheck = await ssh.execCommand('[ -f /www/server/panel/BTPanel/__init__.py ] && echo "found"');
            if (aaCheck.stdout.includes('found')) {
                detection.aapanel = true;
            }

            // VestaCP detection
            const vestaCheck = await ssh.execCommand('[ -f /usr/local/vesta/conf/vesta.conf ] && echo "found"');
            if (vestaCheck.stdout.includes('found')) {
                detection.vestacp = true;
            }

            // HestiaCP detection
            const hestiaCheck = await ssh.execCommand('[ -f /usr/local/hestia/conf/hestia.conf ] && echo "found"');
            if (hestiaCheck.stdout.includes('found')) {
                detection.hestiacp = true;
            }

            // ISPConfig detection
            const ispconfigCheck = await ssh.execCommand('[ -f /usr/local/ispconfig/interface/web/index.php ] && echo "found"');
            if (ispconfigCheck.stdout.includes('found')) {
                detection.ispconfig = true;
            }

        } catch (error) {
            console.warn('Error detecting control panels:', error.message);
        }

        return detection;
    }

    /**
     * Install FlexPBX hooks for cPanel/WHM (non-intrusive)
     */
    async installCPanelHooks(ssh, config = {}) {
        const hookDir = '/usr/local/cpanel/3rdparty/flexpbx';

        // Create hook directory
        await ssh.execCommand(`mkdir -p ${hookDir}`);

        // Create API integration script (doesn't modify cPanel core)
        const apiScript = this.generateCPanelAPIScript();
        await ssh.putFile(
            Buffer.from(apiScript),
            `${hookDir}/api.php`
        );

        // Create hook scripts for various events
        const hooks = {
            'AccountCreate': this.generateCPanelAccountCreateHook(),
            'DomainAdd': this.generateCPanelDomainHook(),
            'SubdomainAdd': this.generateCPanelSubdomainHook(),
            'EmailAdd': this.generateCPanelEmailHook()
        };

        for (const [hookName, hookContent] of Object.entries(hooks)) {
            await ssh.putFile(
                Buffer.from(hookContent),
                `${hookDir}/${hookName}.pl`
            );
            await ssh.execCommand(`chmod +x ${hookDir}/${hookName}.pl`);
        }

        // Register hooks with cPanel (lightweight registration)
        const registrationScript = `#!/bin/bash
# Register FlexPBX hooks with cPanel
echo "Registering FlexPBX hooks..."

# Create hook registration
cat > /var/cpanel/hooks/whostmgr/AccountCreate.json << 'EOF'
{
    "script": "/usr/local/cpanel/3rdparty/flexpbx/AccountCreate.pl",
    "stage": "post",
    "category": "Whostmgr"
}
EOF

cat > /var/cpanel/hooks/cpanel/DomainAdd.json << 'EOF'
{
    "script": "/usr/local/cpanel/3rdparty/flexpbx/DomainAdd.pl",
    "stage": "post",
    "category": "Cpanel"
}
EOF

echo "FlexPBX hooks registered successfully"
`;

        await ssh.putFile(
            Buffer.from(registrationScript),
            `${hookDir}/register.sh`
        );
        await ssh.execCommand(`chmod +x ${hookDir}/register.sh`);
        await ssh.execCommand(`${hookDir}/register.sh`);

        return { success: true, hookDir, message: 'cPanel hooks installed' };
    }

    /**
     * Install FlexPBX integration for WHMCS (as addon module)
     */
    async installWHMCSIntegration(ssh, whmcsPath, config = {}) {
        const moduleDir = path.join(whmcsPath, 'modules/addons/flexpbx');

        // Create addon module (doesn't modify WHMCS core)
        await ssh.execCommand(`mkdir -p ${moduleDir}`);

        // Create addon module files
        const moduleFiles = {
            'flexpbx.php': this.generateWHMCSAddonModule(),
            'hooks.php': this.generateWHMCSHooks(),
            'admin.php': this.generateWHMCSAdminArea(),
            'clientarea.php': this.generateWHMCSClientArea()
        };

        for (const [filename, content] of Object.entries(moduleFiles)) {
            await ssh.putFile(
                Buffer.from(content),
                path.join(moduleDir, filename)
            );
        }

        // Create database schema for FlexPBX data (separate tables)
        const schemaScript = this.generateWHMCSSchema();
        await ssh.putFile(
            Buffer.from(schemaScript),
            path.join(moduleDir, 'schema.sql')
        );

        return { success: true, moduleDir, message: 'WHMCS integration installed' };
    }

    /**
     * Install hooks for Plesk (using event system)
     */
    async installPleskHooks(ssh, config = {}) {
        const hookDir = '/usr/local/psa/var/modules/flexpbx';

        await ssh.execCommand(`mkdir -p ${hookDir}`);

        // Create Plesk event handlers
        const eventHandlers = {
            'domain-create': this.generatePleskDomainCreateHandler(),
            'subdomain-create': this.generatePleskSubdomainCreateHandler(),
            'email-create': this.generatePleskEmailCreateHandler()
        };

        for (const [event, handler] of Object.entries(eventHandlers)) {
            await ssh.putFile(
                Buffer.from(handler),
                `${hookDir}/${event}.php`
            );
            await ssh.execCommand(`chmod +x ${hookDir}/${event}.php`);
        }

        // Register with Plesk event manager
        const eventRegistration = `#!/bin/bash
# Register FlexPBX event handlers with Plesk
/usr/local/psa/bin/event_handler --create domain-create ${hookDir}/domain-create.php
/usr/local/psa/bin/event_handler --create subdomain-create ${hookDir}/subdomain-create.php
/usr/local/psa/bin/event_handler --create email-create ${hookDir}/email-create.php
echo "FlexPBX event handlers registered"
`;

        await ssh.putFile(
            Buffer.from(eventRegistration),
            `${hookDir}/register.sh`
        );
        await ssh.execCommand(`chmod +x ${hookDir}/register.sh`);
        await ssh.execCommand(`${hookDir}/register.sh`);

        return { success: true, hookDir, message: 'Plesk hooks installed' };
    }

    /**
     * Install lightweight API endpoints (no control panel interference)
     */
    async installStandaloneHooks(ssh, installPath, config = {}) {
        const hooksDir = path.join(installPath, 'hooks');

        await ssh.execCommand(`mkdir -p ${hooksDir}`);

        // Create lightweight hook system
        const hookSystem = {
            'webhook.php': this.generateWebhookHandler(),
            'api.js': this.generateNodeJSHookAPI(),
            'config.json': JSON.stringify({
                enabled: true,
                endpoints: {
                    account_create: '/hooks/account/create',
                    domain_add: '/hooks/domain/add',
                    email_create: '/hooks/email/create'
                },
                security: {
                    api_key: this.generateAPIKey(),
                    allowed_ips: ['127.0.0.1']
                }
            }, null, 2)
        };

        for (const [filename, content] of Object.entries(hookSystem)) {
            await ssh.putFile(
                Buffer.from(content),
                path.join(hooksDir, filename)
            );
        }

        // Create systemd service for hook API
        const serviceContent = `[Unit]
Description=FlexPBX Hook API
After=network.target

[Service]
Type=simple
User=root
WorkingDirectory=${hooksDir}
ExecStart=/usr/bin/node api.js
Restart=always

[Install]
WantedBy=multi-user.target
`;

        await ssh.putFile(
            Buffer.from(serviceContent),
            '/etc/systemd/system/flexpbx-hooks.service'
        );

        await ssh.execCommand('systemctl daemon-reload');
        await ssh.execCommand('systemctl enable flexpbx-hooks');
        await ssh.execCommand('systemctl start flexpbx-hooks');

        return { success: true, hooksDir, message: 'Standalone hooks installed' };
    }

    /**
     * Create nginx configuration for domain without interfering with existing setup
     */
    async createNonIntrusiveNginxConfig(ssh, domain, config = {}) {
        const {
            subdomain = null,
            port = 3000,
            sslEnabled = false,
            userAccount = null,
            existingConfig = true
        } = config;

        const serverName = subdomain ? `${subdomain}.${domain}` : domain;
        const configName = `flexpbx-${serverName.replace(/\./g, '-')}.conf`;

        // Check if nginx config directory exists
        const nginxDirs = [
            '/etc/nginx/conf.d',
            '/etc/nginx/sites-available',
            '/usr/local/nginx/conf/conf.d'
        ];

        let nginxDir = null;
        for (const dir of nginxDirs) {
            const check = await ssh.execCommand(`[ -d "${dir}" ] && echo "found"`);
            if (check.stdout.includes('found')) {
                nginxDir = dir;
                break;
            }
        }

        if (!nginxDir) {
            throw new Error('Nginx configuration directory not found');
        }

        // Generate non-conflicting configuration
        const nginxConfig = `# FlexPBX configuration for ${serverName}
# This config is standalone and won't interfere with existing setups

upstream flexpbx_${domain.replace(/\./g, '_')} {
    server 127.0.0.1:${port};
    keepalive 32;
}

server {
    listen 80;
    server_name ${serverName};

    # FlexPBX specific location
    location /flexpbx {
        proxy_pass http://flexpbx_${domain.replace(/\./g, '_')};
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_cache_bypass $http_upgrade;
    }

    # WebSocket support
    location /flexpbx/ws {
        proxy_pass http://flexpbx_${domain.replace(/\./g, '_')}/ws;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_read_timeout 86400;
    }

    # API endpoints
    location /flexpbx/api {
        proxy_pass http://flexpbx_${domain.replace(/\./g, '_')}/api;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
`;

        // Write configuration
        await ssh.putFile(
            Buffer.from(nginxConfig),
            path.join(nginxDir, configName)
        );

        // Test configuration
        const testResult = await ssh.execCommand('nginx -t');
        if (testResult.code !== 0) {
            throw new Error(`Nginx configuration test failed: ${testResult.stderr}`);
        }

        // Reload nginx
        await ssh.execCommand('systemctl reload nginx 2>/dev/null || service nginx reload');

        return {
            success: true,
            configFile: path.join(nginxDir, configName),
            accessUrl: `http://${serverName}/flexpbx`,
            message: 'Non-intrusive nginx configuration created'
        };
    }

    // Helper methods for generating hook scripts
    generateCPanelAPIScript() {
        return `<?php
/**
 * FlexPBX cPanel API Integration
 * Non-intrusive API hooks for cPanel
 */

class FlexPBXCPanelAPI {
    private $flexpbxUrl;
    private $apiKey;

    public function __construct() {
        $this->flexpbxUrl = 'http://localhost:3000';
        $this->apiKey = file_get_contents('/opt/flexpbx/api.key');
    }

    public function notifyAccountCreate($accountData) {
        return $this->apiCall('POST', '/api/hooks/account/create', $accountData);
    }

    public function notifyDomainAdd($domainData) {
        return $this->apiCall('POST', '/api/hooks/domain/add', $domainData);
    }

    private function apiCall($method, $endpoint, $data = []) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->flexpbxUrl . $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-API-Key: ' . trim($this->apiKey)
        ]);

        if (!empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
    }
}
`;
    }

    generateCPanelAccountCreateHook() {
        return `#!/usr/bin/perl
# FlexPBX cPanel Account Create Hook
use strict;
use warnings;

my $user = $ARGV[0];
my $domain = $ARGV[1];

# Load FlexPBX API
require '/usr/local/cpanel/3rdparty/flexpbx/api.php';

# Notify FlexPBX
my $api = FlexPBXCPanelAPI->new();
$api->notifyAccountCreate({
    'user' => $user,
    'domain' => $domain,
    'timestamp' => time()
});

exit 0;
`;
    }

    generateNodeJSHookAPI() {
        return `const express = require('express');
const fs = require('fs');
const app = express();

app.use(express.json());

// Load configuration
const config = JSON.parse(fs.readFileSync('config.json', 'utf8'));

// API Key middleware
app.use((req, res, next) => {
    const apiKey = req.headers['x-api-key'];
    if (apiKey !== config.security.api_key) {
        return res.status(401).json({ error: 'Invalid API key' });
    }
    next();
});

// Hook endpoints
app.post('/hooks/account/create', (req, res) => {
    console.log('Account created:', req.body);
    // Process account creation
    res.json({ success: true });
});

app.post('/hooks/domain/add', (req, res) => {
    console.log('Domain added:', req.body);
    // Process domain addition
    res.json({ success: true });
});

app.post('/hooks/email/create', (req, res) => {
    console.log('Email created:', req.body);
    // Process email creation
    res.json({ success: true });
});

app.listen(3001, () => {
    console.log('FlexPBX Hook API listening on port 3001');
});
`;
    }

    generateAPIKey() {
        const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        let result = '';
        for (let i = 0; i < 64; i++) {
            result += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        return result;
    }

    generateWHMCSAddonModule() {
        return `<?php
/**
 * FlexPBX WHMCS Addon Module
 * Non-intrusive integration with WHMCS
 */

function flexpbx_config() {
    return [
        'name' => 'FlexPBX Integration',
        'description' => 'Integrate FlexPBX with WHMCS for automated PBX provisioning',
        'version' => '2.0.0',
        'author' => 'FlexPBX Team',
        'fields' => [
            'server_url' => [
                'FriendlyName' => 'FlexPBX Server URL',
                'Type' => 'text',
                'Size' => '50',
                'Default' => 'http://localhost:3000',
                'Description' => 'URL of your FlexPBX server'
            ],
            'api_key' => [
                'FriendlyName' => 'API Key',
                'Type' => 'password',
                'Size' => '50',
                'Description' => 'FlexPBX API key for authentication'
            ]
        ]
    ];
}

function flexpbx_activate() {
    // Create FlexPBX tables without modifying WHMCS core
    return [
        'status' => 'success',
        'description' => 'FlexPBX addon activated successfully'
    ];
}

function flexpbx_output($vars) {
    // Admin area output
    return '<h2>FlexPBX Management</h2><p>Manage PBX accounts and settings.</p>';
}

function flexpbx_clientarea($vars) {
    // Client area output
    return [
        'pagetitle' => 'FlexPBX',
        'breadcrumb' => ['index.php?m=flexpbx' => 'FlexPBX'],
        'templatefile' => 'clientarea',
        'vars' => [
            'pbx_url' => $vars['server_url'] . '/client'
        ]
    ];
}
`;
    }

    // Additional generator methods...
    generateWHMCSHooks() {
        return `<?php
add_hook('AfterModuleCreate', 1, function($vars) {
    if ($vars['producttype'] == 'flexpbx') {
        // Auto-provision PBX account
        flexpbx_provision_account($vars);
    }
});

function flexpbx_provision_account($vars) {
    // Provision account without modifying WHMCS core
}
`;
    }

    generatePleskDomainCreateHandler() {
        return `<?php
/**
 * FlexPBX Plesk Domain Create Handler
 */
$domain = $argv[1];
$ip = $argv[2];

// Notify FlexPBX
$api = new FlexPBXPleskAPI();
$api->notifyDomainCreate([
    'domain' => $domain,
    'ip' => $ip,
    'timestamp' => time()
]);

class FlexPBXPleskAPI {
    public function notifyDomainCreate($data) {
        // API call to FlexPBX
    }
}
`;
    }

    generateWebhookHandler() {
        return `<?php
/**
 * FlexPBX Webhook Handler
 * Lightweight webhook system for any control panel
 */

header('Content-Type: application/json');

$config = json_decode(file_get_contents('config.json'), true);

// Verify API key
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($apiKey !== $config['security']['api_key']) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid API key']);
    exit;
}

// Handle webhook
$input = json_decode(file_get_contents('php://input'), true);
$action = $_GET['action'] ?? '';

switch ($action) {
    case 'account_create':
        handleAccountCreate($input);
        break;
    case 'domain_add':
        handleDomainAdd($input);
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action']);
        exit;
}

function handleAccountCreate($data) {
    // Log and process account creation
    file_put_contents('logs/account_create.log', date('Y-m-d H:i:s') . ' - ' . json_encode($data) . "\n", FILE_APPEND);
    echo json_encode(['success' => true]);
}

function handleDomainAdd($data) {
    // Log and process domain addition
    file_put_contents('logs/domain_add.log', date('Y-m-d H:i:s') . ' - ' . json_encode($data) . "\n", FILE_APPEND);
    echo json_encode(['success' => true]);
}
`;
    }
}

module.exports = HookIntegrationService;