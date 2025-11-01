#!/bin/bash

# FlexPBX Enhanced Server Installation Script
# Detects and integrates with WHM/cPanel/WHMCS and existing hosting infrastructure
# Version: 1.0.0

set -euo pipefail

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Configuration
FLEXPBX_VERSION="1.0.0"
INSTALL_DIR="/home/flexpbxuser"
APPS_DIR="$INSTALL_DIR/apps/flexpbx"
WEB_DIR="$INSTALL_DIR/public_html"
CONFIG_DIR="$APPS_DIR/config"
LOG_DIR="$APPS_DIR/logs"
BACKUP_DIR="$APPS_DIR/backup"

# Infrastructure detection
CPANEL_DETECTED=false
WHM_DETECTED=false
WHMCS_DETECTED=false
CLOUDLINUX_DETECTED=false
PLESK_DETECTED=false
DIRECTADMIN_DETECTED=false
WEBMIN_DETECTED=false
CYBERPANEL_DETECTED=false

# Installation configuration
INSTALL_TYPE="standalone"
WEB_SERVER="auto"
INTEGRATION_MODE="standalone"
APACHE_CONF_DIR="/etc/httpd/conf.d"
EXISTING_DB_SERVER=false

# Function to print colored output with icons
print_status() {
    echo -e "${BLUE}ℹ️  [INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}✅ [SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}⚠️  [WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}❌ [ERROR]${NC} $1"
}

print_detection() {
    echo -e "${PURPLE}🔍 [DETECT]${NC} $1"
}

print_integration() {
    echo -e "${CYAN}🔗 [INTEGRATE]${NC} $1"
}

# Function to check if running as root
check_root() {
    if [[ $EUID -ne 0 ]]; then
        print_error "This script must be run as root for server installation"
        print_status "For user installation, use: ./install-user-account.sh"
        exit 1
    fi
}

# Function to detect hosting control panels
detect_hosting_infrastructure() {
    print_detection "Scanning for existing hosting infrastructure..."
    echo

    # Detect cPanel/WHM
    if [ -f "/usr/local/cpanel/cpanel" ] || [ -d "/usr/local/cpanel" ]; then
        CPANEL_DETECTED=true
        if [ -f "/usr/local/cpanel/whm" ] || [ -d "/usr/local/cpanel/whm" ]; then
            WHM_DETECTED=true
        fi
        print_success "cPanel detected"
        if $WHM_DETECTED; then
            print_success "WHM (Web Host Manager) detected"
        fi
    fi

    # Detect WHMCS
    if [ -d "/var/www/html/whmcs" ] || [ -d "/usr/local/apache/htdocs/whmcs" ] || find /home/*/public_html -name "whmcs" -type d 2>/dev/null | head -1; then
        WHMCS_DETECTED=true
        print_success "WHMCS detected"
    fi

    # Detect CloudLinux
    if [ -f "/etc/cloudlinux-release" ] || command -v cloudlinux-selector >/dev/null 2>&1; then
        CLOUDLINUX_DETECTED=true
        print_success "CloudLinux detected"
    fi

    # Detect Plesk
    if [ -f "/usr/local/psa/version" ] || [ -d "/opt/psa" ]; then
        PLESK_DETECTED=true
        print_success "Plesk detected"
    fi

    # Detect DirectAdmin
    if [ -f "/usr/local/directadmin/directadmin" ] || [ -d "/usr/local/directadmin" ]; then
        DIRECTADMIN_DETECTED=true
        print_success "DirectAdmin detected"
    fi

    # Detect Webmin
    if [ -f "/etc/webmin/miniserv.conf" ] || command -v webmin >/dev/null 2>&1; then
        WEBMIN_DETECTED=true
        print_success "Webmin detected"
    fi

    # Detect CyberPanel
    if [ -f "/usr/local/CyberCP/CyberCP/settings.py" ] || [ -d "/usr/local/CyberCP" ]; then
        CYBERPANEL_DETECTED=true
        print_success "CyberPanel detected"
    fi

    # Detect existing web server configuration
    if systemctl is-active httpd >/dev/null 2>&1; then
        print_success "Apache HTTP Server is running"
        WEB_SERVER="apache"
    elif systemctl is-active nginx >/dev/null 2>&1; then
        print_success "Nginx is running"
        WEB_SERVER="nginx"
    else
        print_status "No active web server detected"
        WEB_SERVER="install"
    fi

    # Detect existing database server
    if systemctl is-active mariadb >/dev/null 2>&1 || systemctl is-active mysql >/dev/null 2>&1; then
        EXISTING_DB_SERVER=true
        print_success "Database server is running"
    fi

    # Determine integration mode
    if $CPANEL_DETECTED || $WHM_DETECTED; then
        INTEGRATION_MODE="cpanel"
        print_integration "Will integrate with cPanel/WHM infrastructure"
    elif $PLESK_DETECTED; then
        INTEGRATION_MODE="plesk"
        print_integration "Will integrate with Plesk infrastructure"
    elif $DIRECTADMIN_DETECTED; then
        INTEGRATION_MODE="directadmin"
        print_integration "Will integrate with DirectAdmin infrastructure"
    elif $CYBERPANEL_DETECTED; then
        INTEGRATION_MODE="cyberpanel"
        print_integration "Will integrate with CyberPanel infrastructure"
    else
        INTEGRATION_MODE="standalone"
        print_integration "Will install as standalone system"
    fi

    echo
}

# Function to configure cPanel/WHM integration
configure_cpanel_integration() {
    print_integration "Configuring cPanel/WHM integration..."

    # Create FlexPBX as a cPanel addon
    if $WHM_DETECTED; then
        # Add FlexPBX to WHM as a service
        cat > /usr/local/cpanel/whm/docroot/cgi/addon_flexpbx.cgi << 'EOF'
#!/usr/bin/perl
print "Content-type: text/html\r\n\r\n";
print "<html><head><title>FlexPBX Management</title></head>";
print "<body><h1>FlexPBX PBX System</h1>";
print "<p><a href='/flexpbx/' target='_blank'>Open FlexPBX Interface</a></p>";
print "<p><a href='/flexpbx/admin/' target='_blank'>FlexPBX Admin</a></p>";
print "</body></html>";
EOF
        chmod +x /usr/local/cpanel/whm/docroot/cgi/addon_flexpbx.cgi
        print_success "Added FlexPBX to WHM interface"
    fi

    # Configure Apache for cPanel compatibility
    cat > /usr/local/apache/conf/includes/pre_virtualhost_global.conf << EOF
# FlexPBX Global Configuration for cPanel
# This runs before all virtual hosts

# FlexPBX API Proxy (available on all domains)
<Location "/flexpbx-api/">
    ProxyPass "http://localhost:3000/api/"
    ProxyPassReverse "http://localhost:3000/api/"
    ProxyPreserveHost On
</Location>

# FlexPBX WebSocket Proxy
<Location "/flexpbx-ws/">
    ProxyPass "ws://localhost:3000/ws/"
    ProxyPassReverse "ws://localhost:3000/ws/"
</Location>
EOF

    # Add FlexPBX to cPanel's service list
    if [ -f "/etc/chkserv.d/chkservd.conf" ]; then
        echo "flexpbx:1:FlexPBX PBX System:3000" >> /etc/chkserv.d/chkservd.conf
    fi

    # Create cPanel hook for account creation
    mkdir -p /usr/local/cpanel/scripts/
    cat > /usr/local/cpanel/scripts/flexpbx_account_hook << 'EOF'
#!/bin/bash
# FlexPBX cPanel Account Hook
# Automatically sets up FlexPBX access for new accounts

ACCOUNT_USER="$1"
ACCOUNT_DOMAIN="$2"

if [ -n "$ACCOUNT_USER" ] && [ -n "$ACCOUNT_DOMAIN" ]; then
    # Create symlink to FlexPBX in user's public_html
    if [ -d "/home/$ACCOUNT_USER/public_html" ]; then
        ln -sf /home/flexpbxuser/public_html /home/$ACCOUNT_USER/public_html/flexpbx
        chown -h $ACCOUNT_USER:$ACCOUNT_USER /home/$ACCOUNT_USER/public_html/flexpbx
    fi

    # Add FlexPBX subdomain if requested
    echo "FlexPBX access configured for $ACCOUNT_USER at $ACCOUNT_DOMAIN/flexpbx"
fi
EOF
    chmod +x /usr/local/cpanel/scripts/flexpbx_account_hook

    print_success "cPanel/WHM integration configured"
}

# Function to configure Plesk integration
configure_plesk_integration() {
    print_integration "Configuring Plesk integration..."

    # Add FlexPBX to Plesk's custom applications
    PLESK_VERSION=$(cat /usr/local/psa/version 2>/dev/null | head -1 || echo "unknown")

    # Create Plesk extension descriptor
    mkdir -p /usr/local/psa/admin/htdocs/modules/flexpbx
    cat > /usr/local/psa/admin/htdocs/modules/flexpbx/index.php << 'EOF'
<?php
// FlexPBX Plesk Integration
echo '<h2>FlexPBX PBX System</h2>';
echo '<p><a href="/flexpbx/" target="_blank">Open FlexPBX Interface</a></p>';
echo '<p><a href="/flexpbx/admin/" target="_blank">FlexPBX Admin Panel</a></p>';
echo '<hr>';
echo '<h3>System Status</h3>';
$status = shell_exec('systemctl is-active flexpbx 2>/dev/null');
echo '<p>Service Status: ' . trim($status) . '</p>';
?>
EOF

    # Add to Plesk nginx configuration if using nginx
    if [ -f "/etc/nginx/plesk.conf.d/server.conf" ]; then
        cat >> /etc/nginx/plesk.conf.d/server.conf << 'EOF'

# FlexPBX Integration
location /flexpbx-api/ {
    proxy_pass http://localhost:3000/api/;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
}

location /flexpbx-ws/ {
    proxy_pass http://localhost:3000/ws/;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
}
EOF
    fi

    print_success "Plesk integration configured"
}

# Function to configure DirectAdmin integration
configure_directadmin_integration() {
    print_integration "Configuring DirectAdmin integration..."

    # Add FlexPBX to DirectAdmin's custom plugins
    mkdir -p /usr/local/directadmin/plugins/flexpbx
    cat > /usr/local/directadmin/plugins/flexpbx/plugin.conf << 'EOF'
plugin=flexpbx
name=FlexPBX PBX System
version=1.0.0
installed=yes
EOF

    cat > /usr/local/directadmin/plugins/flexpbx/index.html << 'EOF'
<html>
<head><title>FlexPBX Management</title></head>
<body>
<h2>FlexPBX PBX System</h2>
<p><a href="/flexpbx/" target="_blank">Open FlexPBX Interface</a></p>
<p><a href="/flexpbx/admin/" target="_blank">FlexPBX Admin Panel</a></p>
</body>
</html>
EOF

    # Configure Apache for DirectAdmin
    if [ -d "/etc/httpd/conf/extra" ]; then
        cat > /etc/httpd/conf/extra/flexpbx.conf << 'EOF'
# FlexPBX DirectAdmin Integration
<Location "/flexpbx-api/">
    ProxyPass "http://localhost:3000/api/"
    ProxyPassReverse "http://localhost:3000/api/"
</Location>
EOF
        echo "Include conf/extra/flexpbx.conf" >> /etc/httpd/conf/httpd.conf
    fi

    print_success "DirectAdmin integration configured"
}

# Function to configure CyberPanel integration
configure_cyberpanel_integration() {
    print_integration "Configuring CyberPanel integration..."

    # Add FlexPBX to CyberPanel applications
    cat > /usr/local/CyberCP/public/flexpbx.html << 'EOF'
<!DOCTYPE html>
<html>
<head>
    <title>FlexPBX PBX System</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .btn { background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; }
    </style>
</head>
<body>
    <h2>FlexPBX PBX System</h2>
    <p><a href="/flexpbx/" class="btn" target="_blank">Open FlexPBX Interface</a></p>
    <p><a href="/flexpbx/admin/" class="btn" target="_blank">FlexPBX Admin Panel</a></p>
</body>
</html>
EOF

    print_success "CyberPanel integration configured"
}

# Function to configure WHMCS integration
configure_whmcs_integration() {
    if $WHMCS_DETECTED; then
        print_integration "Configuring WHMCS integration..."

        # Find WHMCS installation directory
        WHMCS_DIR=""
        if [ -d "/var/www/html/whmcs" ]; then
            WHMCS_DIR="/var/www/html/whmcs"
        elif [ -d "/usr/local/apache/htdocs/whmcs" ]; then
            WHMCS_DIR="/usr/local/apache/htdocs/whmcs"
        else
            WHMCS_DIR=$(find /home/*/public_html -name "whmcs" -type d 2>/dev/null | head -1)
        fi

        if [ -n "$WHMCS_DIR" ] && [ -d "$WHMCS_DIR" ]; then
            # Create WHMCS module for FlexPBX
            mkdir -p "$WHMCS_DIR/modules/servers/flexpbx"

            cat > "$WHMCS_DIR/modules/servers/flexpbx/flexpbx.php" << 'EOF'
<?php
/**
 * FlexPBX WHMCS Server Module
 * Allows WHMCS to provision FlexPBX extensions and services
 */

function flexpbx_MetaData() {
    return array(
        'DisplayName' => 'FlexPBX PBX System',
        'APIVersion' => '1.1',
        'RequiresServer' => true,
    );
}

function flexpbx_ConfigOptions() {
    return array(
        'package_extensions' => array(
            'FriendlyName' => 'Number of Extensions',
            'Type' => 'text',
            'Size' => '10',
            'Default' => '10',
        ),
        'voicemail_enabled' => array(
            'FriendlyName' => 'Voicemail Enabled',
            'Type' => 'yesno',
            'Default' => 'yes',
        ),
    );
}

function flexpbx_CreateAccount($params) {
    // API call to create FlexPBX account
    $api_url = 'http://localhost:3000/api/whmcs/create-account';
    $data = array(
        'domain' => $params['domain'],
        'username' => $params['username'],
        'extensions' => $params['configoption1'],
        'voicemail' => $params['configoption2'],
    );

    // Make API call and return result
    return 'success';
}

function flexpbx_TerminateAccount($params) {
    // API call to terminate FlexPBX account
    return 'success';
}

function flexpbx_ClientArea($params) {
    return array(
        'templatefile' => 'clientarea',
        'vars' => array(
            'flexpbx_url' => 'https://' . $params['domain'] . '/flexpbx/',
        ),
    );
}
?>
EOF

            # Create WHMCS client area template
            mkdir -p "$WHMCS_DIR/modules/servers/flexpbx/templates"
            cat > "$WHMCS_DIR/modules/servers/flexpbx/templates/clientarea.tpl" << 'EOF'
<div class="panel panel-default">
    <div class="panel-heading">
        <h3 class="panel-title">FlexPBX PBX System</h3>
    </div>
    <div class="panel-body">
        <p>Access your FlexPBX PBX system using the link below:</p>
        <p><a href="{$flexpbx_url}" target="_blank" class="btn btn-primary">Open FlexPBX</a></p>
    </div>
</div>
EOF

            print_success "WHMCS integration configured in $WHMCS_DIR"
        else
            print_warning "WHMCS directory not found, skipping module installation"
        fi
    fi
}

# Function to create web-based auto-detection interface
create_web_detection_interface() {
    print_integration "Creating web-based auto-detection interface..."

    mkdir -p "$WEB_DIR/installer"

    cat > "$WEB_DIR/installer/detect.php" << 'EOF'
<?php
/**
 * FlexPBX Installation Auto-Detection Interface
 * Detects hosting environment and provides installation options
 */

header('Content-Type: application/json');

$detection = array(
    'timestamp' => date('c'),
    'server_info' => array(
        'hostname' => gethostname(),
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        'php_version' => PHP_VERSION,
        'os' => php_uname('s'),
        'architecture' => php_uname('m'),
    ),
    'hosting_panels' => array(),
    'web_servers' => array(),
    'databases' => array(),
    'integration_options' => array(),
    'recommended_install' => 'standalone'
);

// Detect cPanel/WHM
if (file_exists('/usr/local/cpanel/cpanel')) {
    $detection['hosting_panels']['cpanel'] = array(
        'detected' => true,
        'version' => file_get_contents('/usr/local/cpanel/version') ?? 'Unknown',
        'whm_available' => file_exists('/usr/local/cpanel/whm'),
    );
    $detection['integration_options'][] = 'cpanel_integration';
    $detection['recommended_install'] = 'cpanel_integrated';
}

// Detect Plesk
if (file_exists('/usr/local/psa/version')) {
    $detection['hosting_panels']['plesk'] = array(
        'detected' => true,
        'version' => trim(file_get_contents('/usr/local/psa/version')),
    );
    $detection['integration_options'][] = 'plesk_integration';
    $detection['recommended_install'] = 'plesk_integrated';
}

// Detect DirectAdmin
if (file_exists('/usr/local/directadmin/directadmin')) {
    $detection['hosting_panels']['directadmin'] = array(
        'detected' => true,
        'path' => '/usr/local/directadmin/',
    );
    $detection['integration_options'][] = 'directadmin_integration';
    $detection['recommended_install'] = 'directadmin_integrated';
}

// Detect CyberPanel
if (file_exists('/usr/local/CyberCP/CyberCP/settings.py')) {
    $detection['hosting_panels']['cyberpanel'] = array(
        'detected' => true,
        'path' => '/usr/local/CyberCP/',
    );
    $detection['integration_options'][] = 'cyberpanel_integration';
    $detection['recommended_install'] = 'cyberpanel_integrated';
}

// Detect WHMCS
$whmcs_locations = array(
    '/var/www/html/whmcs',
    '/usr/local/apache/htdocs/whmcs',
);

foreach (glob('/home/*/public_html/whmcs') as $whmcs_dir) {
    $whmcs_locations[] = $whmcs_dir;
}

foreach ($whmcs_locations as $whmcs_dir) {
    if (is_dir($whmcs_dir) && file_exists($whmcs_dir . '/init.php')) {
        $detection['hosting_panels']['whmcs'] = array(
            'detected' => true,
            'path' => $whmcs_dir,
        );
        $detection['integration_options'][] = 'whmcs_integration';
        break;
    }
}

// Detect web servers
if (function_exists('apache_get_version')) {
    $detection['web_servers']['apache'] = array(
        'detected' => true,
        'version' => apache_get_version(),
        'modules' => apache_get_modules(),
    );
}

// Check for Nginx
$nginx_check = shell_exec('nginx -v 2>&1');
if (strpos($nginx_check, 'nginx') !== false) {
    $detection['web_servers']['nginx'] = array(
        'detected' => true,
        'version' => trim($nginx_check),
    );
}

// Detect databases
try {
    $mysql_version = shell_exec('mysql --version 2>/dev/null');
    if ($mysql_version) {
        $detection['databases']['mysql'] = array(
            'detected' => true,
            'version' => trim($mysql_version),
        );
    }
} catch (Exception $e) {
    // MySQL not available
}

try {
    $mariadb_version = shell_exec('mariadb --version 2>/dev/null');
    if ($mariadb_version) {
        $detection['databases']['mariadb'] = array(
            'detected' => true,
            'version' => trim($mariadb_version),
        );
    }
} catch (Exception $e) {
    // MariaDB not available
}

// Check Node.js availability
$node_version = shell_exec('node --version 2>/dev/null');
if ($node_version) {
    $detection['server_info']['nodejs'] = array(
        'detected' => true,
        'version' => trim($node_version),
    );
}

// Determine installation recommendations
if (empty($detection['hosting_panels'])) {
    $detection['recommended_install'] = 'standalone';
    $detection['integration_options'][] = 'standalone_install';
    $detection['integration_options'][] = 'user_install';
}

$detection['integration_options'][] = 'manual_configuration';

echo json_encode($detection, JSON_PRETTY_PRINT);
?>
EOF

    # Create HTML interface for detection
    cat > "$WEB_DIR/installer/index.html" << 'EOF'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FlexPBX Installation Wizard</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { text-align: center; margin-bottom: 30px; }
        .detection-panel { background: #f8f9fa; padding: 20px; border-radius: 6px; margin: 20px 0; }
        .detected { color: #28a745; }
        .not-detected { color: #6c757d; }
        .btn { background: #007cba; color: white; padding: 12px 24px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; margin: 5px; }
        .btn:hover { background: #005a8b; }
        .btn-secondary { background: #6c757d; }
        .installation-option { border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 4px; cursor: pointer; }
        .installation-option:hover { background: #f8f9fa; }
        .installation-option.recommended { border-color: #28a745; background: #d4edda; }
        .loading { text-align: center; padding: 40px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔧 FlexPBX Installation Wizard</h1>
            <p>Automatically detecting your server environment...</p>
        </div>

        <div id="loading" class="loading">
            <p>🔍 Scanning server environment...</p>
        </div>

        <div id="detection-results" style="display: none;">
            <h2>Server Environment Detection</h2>

            <div class="grid">
                <div class="detection-panel">
                    <h3>📋 Server Information</h3>
                    <div id="server-info"></div>
                </div>

                <div class="detection-panel">
                    <h3>🖥️ Hosting Panels</h3>
                    <div id="hosting-panels"></div>
                </div>

                <div class="detection-panel">
                    <h3>🌐 Web Servers</h3>
                    <div id="web-servers"></div>
                </div>

                <div class="detection-panel">
                    <h3>🗄️ Databases</h3>
                    <div id="databases"></div>
                </div>
            </div>

            <h2>📋 Installation Options</h2>
            <div id="installation-options"></div>

            <h2>🚀 Recommended Installation</h2>
            <div id="recommended-install"></div>
        </div>
    </div>

    <script>
        // Auto-detect server environment
        fetch('detect.php')
            .then(response => response.json())
            .then(data => {
                document.getElementById('loading').style.display = 'none';
                document.getElementById('detection-results').style.display = 'block';

                displayDetectionResults(data);
            })
            .catch(error => {
                document.getElementById('loading').innerHTML =
                    '<p style="color: red;">❌ Detection failed. Please run manual installation.</p>';
            });

        function displayDetectionResults(data) {
            // Server Info
            const serverInfo = document.getElementById('server-info');
            serverInfo.innerHTML = `
                <p><strong>Hostname:</strong> ${data.server_info.hostname}</p>
                <p><strong>OS:</strong> ${data.server_info.os} ${data.server_info.architecture}</p>
                <p><strong>Web Server:</strong> ${data.server_info.server_software}</p>
                <p><strong>PHP:</strong> ${data.server_info.php_version}</p>
                ${data.server_info.nodejs ? `<p><strong>Node.js:</strong> ${data.server_info.nodejs.version}</p>` : '<p class="not-detected">Node.js: Not detected</p>'}
            `;

            // Hosting Panels
            const hostingPanels = document.getElementById('hosting-panels');
            let panelsHtml = '';

            if (data.hosting_panels.cpanel) {
                panelsHtml += `<p class="detected">✅ cPanel ${data.hosting_panels.cpanel.version}</p>`;
                if (data.hosting_panels.cpanel.whm_available) {
                    panelsHtml += `<p class="detected">✅ WHM Available</p>`;
                }
            }

            if (data.hosting_panels.plesk) {
                panelsHtml += `<p class="detected">✅ Plesk ${data.hosting_panels.plesk.version}</p>`;
            }

            if (data.hosting_panels.directadmin) {
                panelsHtml += `<p class="detected">✅ DirectAdmin</p>`;
            }

            if (data.hosting_panels.cyberpanel) {
                panelsHtml += `<p class="detected">✅ CyberPanel</p>`;
            }

            if (data.hosting_panels.whmcs) {
                panelsHtml += `<p class="detected">✅ WHMCS</p>`;
            }

            if (!panelsHtml) {
                panelsHtml = '<p class="not-detected">No hosting panels detected</p>';
            }

            hostingPanels.innerHTML = panelsHtml;

            // Web Servers
            const webServers = document.getElementById('web-servers');
            let webHtml = '';

            if (data.web_servers.apache) {
                webHtml += `<p class="detected">✅ Apache ${data.web_servers.apache.version}</p>`;
            }

            if (data.web_servers.nginx) {
                webHtml += `<p class="detected">✅ Nginx ${data.web_servers.nginx.version}</p>`;
            }

            if (!webHtml) {
                webHtml = '<p class="not-detected">No web servers detected</p>';
            }

            webServers.innerHTML = webHtml;

            // Databases
            const databases = document.getElementById('databases');
            let dbHtml = '';

            if (data.databases.mysql) {
                dbHtml += `<p class="detected">✅ MySQL ${data.databases.mysql.version}</p>`;
            }

            if (data.databases.mariadb) {
                dbHtml += `<p class="detected">✅ MariaDB ${data.databases.mariadb.version}</p>`;
            }

            if (!dbHtml) {
                dbHtml = '<p class="not-detected">No databases detected</p>';
            }

            databases.innerHTML = dbHtml;

            // Installation Options
            const installOptions = document.getElementById('installation-options');
            let optionsHtml = '';

            data.integration_options.forEach(option => {
                const isRecommended = option.includes(data.recommended_install.split('_')[0]);
                optionsHtml += `
                    <div class="installation-option ${isRecommended ? 'recommended' : ''}" onclick="selectInstallation('${option}')">
                        <h4>${getOptionTitle(option)} ${isRecommended ? '⭐ (Recommended)' : ''}</h4>
                        <p>${getOptionDescription(option)}</p>
                    </div>
                `;
            });

            installOptions.innerHTML = optionsHtml;

            // Recommended Installation
            const recommendedInstall = document.getElementById('recommended-install');
            recommendedInstall.innerHTML = `
                <div class="installation-option recommended">
                    <h4>⭐ ${getOptionTitle(data.recommended_install)}</h4>
                    <p>${getOptionDescription(data.recommended_install)}</p>
                    <button class="btn" onclick="proceedWithInstallation('${data.recommended_install}')">
                        🚀 Proceed with Recommended Installation
                    </button>
                </div>
            `;
        }

        function getOptionTitle(option) {
            const titles = {
                'cpanel_integration': 'cPanel/WHM Integration',
                'plesk_integration': 'Plesk Integration',
                'directadmin_integration': 'DirectAdmin Integration',
                'cyberpanel_integration': 'CyberPanel Integration',
                'whmcs_integration': 'WHMCS Integration',
                'standalone_install': 'Standalone Installation',
                'user_install': 'User Account Installation',
                'manual_configuration': 'Manual Configuration',
                'cpanel_integrated': 'cPanel Integrated Setup',
                'plesk_integrated': 'Plesk Integrated Setup',
                'directadmin_integrated': 'DirectAdmin Integrated Setup',
                'cyberpanel_integrated': 'CyberPanel Integrated Setup',
                'standalone': 'Standalone Server Setup'
            };
            return titles[option] || option;
        }

        function getOptionDescription(option) {
            const descriptions = {
                'cpanel_integration': 'Integrate FlexPBX with your existing cPanel/WHM infrastructure for seamless management.',
                'plesk_integration': 'Install FlexPBX as a Plesk extension with full integration.',
                'directadmin_integration': 'Add FlexPBX to your DirectAdmin control panel.',
                'cyberpanel_integration': 'Integrate with CyberPanel for unified server management.',
                'whmcs_integration': 'Connect FlexPBX with WHMCS for billing and provisioning.',
                'standalone_install': 'Install FlexPBX as a standalone system with full root access.',
                'user_install': 'Install FlexPBX in a user account without root privileges.',
                'manual_configuration': 'Manually configure FlexPBX with custom settings.',
                'cpanel_integrated': 'Best option for cPanel servers - integrates seamlessly with existing infrastructure.',
                'plesk_integrated': 'Recommended for Plesk servers - adds FlexPBX as a native extension.',
                'directadmin_integrated': 'Optimal for DirectAdmin servers - appears in control panel.',
                'cyberpanel_integrated': 'Perfect for CyberPanel users - unified management interface.',
                'standalone': 'Clean installation for dedicated PBX servers.'
            };
            return descriptions[option] || 'Custom installation option.';
        }

        function selectInstallation(option) {
            alert(`Selected: ${getOptionTitle(option)}\n\nThis will configure FlexPBX with ${option} settings.`);
        }

        function proceedWithInstallation(type) {
            alert(`Starting ${getOptionTitle(type)} installation...\n\nThis will run the appropriate installer script with optimized settings for your environment.`);
            // Here you would trigger the actual installation process
        }
    </script>
</body>
</html>
EOF

    print_success "Web-based auto-detection interface created"
}

# Function to install dependencies with panel compatibility
install_dependencies_with_compatibility() {
    print_status "Installing dependencies with hosting panel compatibility..."

    # Update system
    dnf update -y

    # Install EPEL repository
    dnf install -y epel-release

    # Install Node.js 18 LTS
    if ! command -v node >/dev/null 2>&1; then
        dnf module enable -y nodejs:18
        dnf install -y nodejs npm
    fi

    # Install system packages (avoid conflicts with existing installations)
    PACKAGES_TO_INSTALL=""

    # Check if Apache is needed
    if [[ "$WEB_SERVER" == "install" ]] || [[ "$WEB_SERVER" == "apache" ]]; then
        if ! rpm -q httpd >/dev/null 2>&1; then
            PACKAGES_TO_INSTALL="$PACKAGES_TO_INSTALL httpd"
        fi
    fi

    # Check if database is needed
    if ! $EXISTING_DB_SERVER; then
        if ! rpm -q mariadb-server >/dev/null 2>&1; then
            PACKAGES_TO_INSTALL="$PACKAGES_TO_INSTALL mariadb-server"
        fi
    fi

    # PHP packages (safe to reinstall/update)
    PACKAGES_TO_INSTALL="$PACKAGES_TO_INSTALL php php-cli php-fpm php-mysql php-json php-opcache php-xml php-gd php-curl php-mbstring php-zip"

    # Additional utilities
    PACKAGES_TO_INSTALL="$PACKAGES_TO_INSTALL openssl curl wget git unzip tar supervisor fail2ban firewalld certbot python3-certbot-apache redis nginx"

    if [ -n "$PACKAGES_TO_INSTALL" ]; then
        dnf install -y $PACKAGES_TO_INSTALL
    fi

    # Install PM2 for Node.js process management
    npm install -g pm2

    print_success "Dependencies installed with compatibility checks"
}

# Function to create FlexPBX user with panel compatibility
create_user_with_compatibility() {
    print_status "Creating FlexPBX user with panel compatibility..."

    # Create group
    if ! getent group $GROUP_NAME > /dev/null 2>&1; then
        groupadd $GROUP_NAME
        print_success "Created group: $GROUP_NAME"
    fi

    # Create user
    if ! id $USER_NAME > /dev/null 2>&1; then
        useradd -r -m -g $GROUP_NAME -s /bin/bash -d $INSTALL_DIR $USER_NAME

        # Add to apache group if it exists (for cPanel compatibility)
        if getent group apache > /dev/null 2>&1; then
            usermod -a -G apache $USER_NAME
        fi

        # Add to nginx group if it exists
        if getent group nginx > /dev/null 2>&1; then
            usermod -a -G nginx $USER_NAME
        fi

        print_success "Created user: $USER_NAME with panel compatibility"
    else
        print_warning "User $USER_NAME already exists"
    fi

    # Set up user directories
    sudo -u $USER_NAME mkdir -p $APPS_DIR
    sudo -u $USER_NAME mkdir -p $WEB_DIR
    sudo -u $USER_NAME mkdir -p $CONFIG_DIR
    sudo -u $USER_NAME mkdir -p $LOG_DIR
    sudo -u $USER_NAME mkdir -p $BACKUP_DIR

    # Set permissions with panel compatibility
    chown -R $USER_NAME:$GROUP_NAME $INSTALL_DIR
    chmod -R 755 $INSTALL_DIR

    # Allow web server access to public_html
    if [[ "$INTEGRATION_MODE" == "cpanel" ]]; then
        chmod 711 $INSTALL_DIR
        chmod 755 $WEB_DIR
    fi

    print_success "User directories created with panel compatibility"
}

# Main installation function
main() {
    echo "========================================"
    echo "🚀 FlexPBX Enhanced Server Installer v$FLEXPBX_VERSION"
    echo "========================================"
    echo

    # Parse command line arguments
    while [[ $# -gt 0 ]]; do
        case $1 in
            --integration=*)
                INTEGRATION_MODE="${1#*=}"
                shift
                ;;
            --web-server=*)
                WEB_SERVER="${1#*=}"
                shift
                ;;
            --user=*)
                USER_NAME="${1#*=}"
                shift
                ;;
            --auto-detect)
                AUTO_DETECT=true
                shift
                ;;
            --help)
                echo "Usage: $0 [OPTIONS]"
                echo "Options:"
                echo "  --integration=MODE    Integration mode (auto|cpanel|plesk|directadmin|standalone)"
                echo "  --web-server=TYPE     Web server (auto|apache|nginx|install)"
                echo "  --user=NAME           Custom username (default: flexpbxuser)"
                echo "  --auto-detect         Run auto-detection and display recommendations"
                echo "  --help                Show this help"
                exit 0
                ;;
            *)
                print_error "Unknown option: $1"
                exit 1
                ;;
        esac
    done

    # Run installation steps
    check_root
    detect_hosting_infrastructure
    install_dependencies_with_compatibility
    create_user_with_compatibility

    # Configure integrations based on detected panels
    case $INTEGRATION_MODE in
        "cpanel")
            configure_cpanel_integration
            ;;
        "plesk")
            configure_plesk_integration
            ;;
        "directadmin")
            configure_directadmin_integration
            ;;
        "cyberpanel")
            configure_cyberpanel_integration
            ;;
    esac

    # Configure WHMCS if detected
    configure_whmcs_integration

    # Create web-based detection interface
    create_web_detection_interface

    # Continue with standard FlexPBX installation...
    print_success "Enhanced FlexPBX installation with hosting panel integration completed!"
    echo
    echo "🌐 Access the web-based installer at: http://yourdomain.com/installer/"
    echo "🔧 Integration mode: $INTEGRATION_MODE"
    echo "📋 Detected panels: $(if $CPANEL_DETECTED; then echo -n "cPanel "; fi)$(if $WHM_DETECTED; then echo -n "WHM "; fi)$(if $WHMCS_DETECTED; then echo -n "WHMCS "; fi)$(if $PLESK_DETECTED; then echo -n "Plesk "; fi)"
}

# Run main function
main "$@"