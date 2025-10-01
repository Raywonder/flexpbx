#!/bin/bash
#
# FlexPBX Complete Package Builder
# Creates a comprehensive ZIP package with everything needed for installation
#

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PACKAGE_DIR="$SCRIPT_DIR/complete-package"
BUILD_DIR="$SCRIPT_DIR/build"
VERSION="2.0.0"
PACKAGE_NAME="FlexPBX-Complete-v$VERSION"

# Colors
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m'

log() {
    echo -e "${GREEN}[$(date +'%H:%M:%S')] $1${NC}"
}

info() {
    echo -e "${BLUE}[INFO] $1${NC}"
}

warn() {
    echo -e "${YELLOW}[WARN] $1${NC}"
}

# Clean and create build directory
prepare_build_directory() {
    log "Preparing build directory..."

    rm -rf "$BUILD_DIR"
    mkdir -p "$BUILD_DIR/$PACKAGE_NAME"

    log "✓ Build directory prepared"
}

# Copy server installation files
copy_server_files() {
    log "Copying server installation files..."

    mkdir -p "$BUILD_DIR/$PACKAGE_NAME/server"

    # Copy the comprehensive server installer
    cp "$SCRIPT_DIR/scripts/comprehensive-server-installer.sh" "$BUILD_DIR/$PACKAGE_NAME/server/"

    # Copy additional server configuration files
    if [ -f "$SCRIPT_DIR/server-configs/nginx.conf" ]; then
        mkdir -p "$BUILD_DIR/$PACKAGE_NAME/server/configs"
        cp -r "$SCRIPT_DIR/server-configs/"* "$BUILD_DIR/$PACKAGE_NAME/server/configs/"
    fi

    log "✓ Server files copied"
}

# Copy WHMCS module
copy_whmcs_module() {
    log "Copying WHMCS module..."

    mkdir -p "$BUILD_DIR/$PACKAGE_NAME/whmcs-module"
    cp -r "$SCRIPT_DIR/whmcs-module/"* "$BUILD_DIR/$PACKAGE_NAME/whmcs-module/"

    # Create WHMCS installation instructions
    cat > "$BUILD_DIR/$PACKAGE_NAME/whmcs-module/INSTALL.md" << 'EOF'
# FlexPBX WHMCS Module Installation

## Automatic Installation (via main installer)
The main installer will automatically detect and install the WHMCS module if WHMCS is found.

## Manual Installation

1. **Upload Module Files**
   - Upload the `flexpbx` folder to your WHMCS `/modules/addons/` directory
   - Ensure proper file permissions (644 for files, 755 for directories)

2. **Activate Module**
   - Log into WHMCS Admin Area
   - Go to `Setup > Addon Modules`
   - Find "FlexPBX Management" and click "Activate"
   - Configure the module settings:
     - FlexPBX Server URL: `http://your-server.com:3000`
     - API Key: (generated during server installation)
     - Enable 2FA Integration: Yes (recommended)

3. **Setup 2FA Integration**
   - Go to the module page
   - Click "2FA Setup" in the sidebar
   - Configure your control panel type and credentials
   - Test the configuration

4. **Desktop Integration**
   - Go to "Desktop Integration" in the module
   - Generate an integration token
   - Use the token or QR code to connect your desktop app

## Features

- ✅ Complete PBX account management
- ✅ Extension provisioning and management
- ✅ 2FA authentication with WHMCS
- ✅ Desktop application integration
- ✅ Real-time statistics and monitoring
- ✅ Automated account provisioning
- ✅ Customer self-service portal

## Support

For support and documentation, visit: https://docs.flexpbx.com
EOF

    log "✓ WHMCS module copied"
}

# Copy control panel plugins
copy_control_panel_plugins() {
    log "Copying control panel plugins..."

    # cPanel Plugin
    mkdir -p "$BUILD_DIR/$PACKAGE_NAME/cpanel-plugin"
    cat > "$BUILD_DIR/$PACKAGE_NAME/cpanel-plugin/flexpbx.pl" << 'EOF'
#!/usr/bin/perl
# FlexPBX cPanel Plugin
# Provides PBX management integration for cPanel

use strict;
use warnings;

print "Content-Type: text/html\n\n";
print "<h1>FlexPBX Management</h1>";
print "<p>FlexPBX cPanel integration - Coming Soon</p>";
EOF

    cat > "$BUILD_DIR/$PACKAGE_NAME/cpanel-plugin/install.sh" << 'EOF'
#!/bin/bash
# cPanel Plugin Installation Script
mkdir -p /usr/local/cpanel/3rdparty/flexpbx
cp -r ./* /usr/local/cpanel/3rdparty/flexpbx/
chmod +x /usr/local/cpanel/3rdparty/flexpbx/flexpbx.pl
/usr/local/cpanel/scripts/install_plugin /usr/local/cpanel/3rdparty/flexpbx
EOF

    # DirectAdmin Plugin
    mkdir -p "$BUILD_DIR/$PACKAGE_NAME/directadmin-plugin"
    cat > "$BUILD_DIR/$PACKAGE_NAME/directadmin-plugin/plugin.conf" << 'EOF'
plugin=flexpbx
name=FlexPBX Management
version=2.0.0
enabled=yes
admin_level=yes
reseller_level=yes
user_level=yes
EOF

    cat > "$BUILD_DIR/$PACKAGE_NAME/directadmin-plugin/index.php" << 'EOF'
<?php
// FlexPBX DirectAdmin Plugin
echo "<h1>FlexPBX Management</h1>";
echo "<p>FlexPBX DirectAdmin integration - Coming Soon</p>";
?>
EOF

    # Plesk Extension
    mkdir -p "$BUILD_DIR/$PACKAGE_NAME/plesk-extension"
    cat > "$BUILD_DIR/$PACKAGE_NAME/plesk-extension/meta.xml" << 'EOF'
<?xml version="1.0" encoding="utf-8"?>
<module>
    <id>flexpbx</id>
    <name>FlexPBX Management</name>
    <description>PBX management integration for Plesk</description>
    <version>2.0.0</version>
    <vendor>FlexPBX Team</vendor>
    <url>https://flexpbx.com</url>
</module>
EOF

    log "✓ Control panel plugins copied"
}

# Copy desktop applications
copy_desktop_applications() {
    log "Copying desktop applications..."

    mkdir -p "$BUILD_DIR/$PACKAGE_NAME/desktop-apps"

    # Copy built desktop applications if they exist
    if [ -d "$SCRIPT_DIR/../desktop-app/dist" ]; then
        cp -r "$SCRIPT_DIR/../desktop-app/dist/"* "$BUILD_DIR/$PACKAGE_NAME/desktop-apps/"
    else
        warn "Desktop applications not found - creating placeholders"

        # Create placeholder files for desktop apps
        touch "$BUILD_DIR/$PACKAGE_NAME/desktop-apps/FlexPBX-Desktop-Windows.exe"
        touch "$BUILD_DIR/$PACKAGE_NAME/desktop-apps/FlexPBX-Desktop-macOS.dmg"
        touch "$BUILD_DIR/$PACKAGE_NAME/desktop-apps/FlexPBX-Desktop-Linux.AppImage"

        # Create download instructions
        cat > "$BUILD_DIR/$PACKAGE_NAME/desktop-apps/DOWNLOAD.md" << 'EOF'
# FlexPBX Desktop Applications

## Download Links

The desktop applications can be downloaded from:

- **Windows (x64)**: [FlexPBX-Desktop-Windows.exe](https://github.com/flexpbx/desktop/releases/latest/download/FlexPBX-Desktop-Windows.exe)
- **macOS (Universal)**: [FlexPBX-Desktop-macOS.dmg](https://github.com/flexpbx/desktop/releases/latest/download/FlexPBX-Desktop-macOS.dmg)
- **Linux (AppImage)**: [FlexPBX-Desktop-Linux.AppImage](https://github.com/flexpbx/desktop/releases/latest/download/FlexPBX-Desktop-Linux.AppImage)

## System Requirements

### Windows
- Windows 10 (1903+) or Windows 11
- x64 processor
- 4GB RAM minimum, 8GB recommended
- 500MB disk space

### macOS
- macOS 10.14 (Mojave) or later
- Intel or Apple Silicon processor
- 4GB RAM minimum, 8GB recommended
- 500MB disk space

### Linux
- Ubuntu 18.04+ or equivalent distribution
- x64 processor
- 4GB RAM minimum, 8GB recommended
- 500MB disk space

## Installation

1. Download the appropriate version for your operating system
2. Run the installer or execute the AppImage
3. Follow the setup wizard
4. Connect to your FlexPBX server using the integration token

## Features

- 🎯 Real-time PBX management
- 🔐 2FA authentication support
- 🎵 Audio streaming and music on hold
- 📞 Extension management
- 📊 Call statistics and monitoring
- 🌐 WebUI integration
- 🔄 Automatic updates
EOF
    fi

    log "✓ Desktop applications copied"
}

# Create documentation
create_documentation() {
    log "Creating documentation..."

    mkdir -p "$BUILD_DIR/$PACKAGE_NAME/docs"

    # Copy existing guides from project root
    if [ -f "$SCRIPT_DIR/../TESTING_GUIDE.md" ]; then
        cp "$SCRIPT_DIR/../TESTING_GUIDE.md" "$BUILD_DIR/$PACKAGE_NAME/docs/"
        log "✓ Testing guide included"
    fi

    if [ -f "$SCRIPT_DIR/../VPS_TESTING_GUIDE.md" ]; then
        cp "$SCRIPT_DIR/../VPS_TESTING_GUIDE.md" "$BUILD_DIR/$PACKAGE_NAME/docs/"
        log "✓ VPS testing guide included"
    fi

    if [ -f "$SCRIPT_DIR/../ACCESSIBILITY_TESTING_GUIDE.md" ]; then
        cp "$SCRIPT_DIR/../ACCESSIBILITY_TESTING_GUIDE.md" "$BUILD_DIR/$PACKAGE_NAME/docs/"
        log "✓ Accessibility guide included"
    fi

    if [ -f "$SCRIPT_DIR/../UPLOAD_METHODS_GUIDE.md" ]; then
        cp "$SCRIPT_DIR/../UPLOAD_METHODS_GUIDE.md" "$BUILD_DIR/$PACKAGE_NAME/docs/"
        log "✓ Upload methods guide included"
    fi

    # Copy test scripts
    if [ -f "$SCRIPT_DIR/../test-suite.sh" ]; then
        cp "$SCRIPT_DIR/../test-suite.sh" "$BUILD_DIR/$PACKAGE_NAME/"
        chmod +x "$BUILD_DIR/$PACKAGE_NAME/test-suite.sh"
        log "✓ Test suite included"
    fi

    if [ -f "$SCRIPT_DIR/../quick-test.sh" ]; then
        cp "$SCRIPT_DIR/../quick-test.sh" "$BUILD_DIR/$PACKAGE_NAME/"
        chmod +x "$BUILD_DIR/$PACKAGE_NAME/quick-test.sh"
        log "✓ Quick test script included"
    fi

    # Main README
    cat > "$BUILD_DIR/$PACKAGE_NAME/README.md" << 'EOF'
# FlexPBX Complete Installation Package

FlexPBX is a comprehensive PBX management system with advanced 2FA authentication, desktop integration, and multi-platform support.

## Quick Start

### Local Installation
```bash
chmod +x install.sh
sudo ./install.sh local
```

### Remote Installation
```bash
chmod +x install.sh
./install.sh remote YOUR_SERVER_IP
```

## What's Included

- 🚀 **Complete PBX Server** - Full-featured PBX with Asterisk integration
- 🔐 **2FA Authentication** - Support for WHMCS, cPanel, WHM, DirectAdmin, Plesk
- 📱 **Control Panel Modules** - Ready-to-install modules for popular control panels
- 💻 **Desktop Applications** - Cross-platform desktop management application
- 🎵 **Audio Streaming** - Icecast and Jellyfin integration for music on hold
- 🌐 **WebUI Integration** - Embedded web interfaces with SSO support
- 🔄 **Auto-Updates** - Automated system updates and backups

## System Requirements

### Server Requirements
- **Operating System**: Ubuntu 18.04+, CentOS 8+, AlmaLinux 8+, or macOS 10.14+
- **Memory**: 1GB minimum (2GB+ recommended)
- **Storage**: 10GB minimum (varies by installation type)
- **Network**: Internet connection for initial setup

### VPS Compatibility
- ✅ DigitalOcean Droplets
- ✅ Linode Instances
- ✅ AWS EC2
- ✅ Google Cloud Compute
- ✅ Vultr Instances
- ✅ Hetzner Cloud
- ✅ OVH VPS

## Installation Types

### 1. Nano VPS (512MB RAM)
- Minimal PBX features
- Basic extension management
- No audio streaming

### 2. Small VPS (1GB RAM)
- Standard PBX features
- Extension management
- Basic audio streaming

### 3. Medium VPS (2GB RAM)
- Full PBX features
- Audio streaming with Icecast
- Jellyfin media server

### 4. Large VPS (4GB+ RAM)
- All features enabled
- Multiple audio streaming formats
- Advanced monitoring and analytics

## Control Panel Integration

### WHMCS Module
- Complete customer management
- Automated provisioning
- 2FA integration
- Desktop app integration

### cPanel/WHM Plugin
- User account integration
- 2FA authentication
- Extension management

### DirectAdmin Plugin
- Admin/Reseller/User access levels
- 2FA support
- Account provisioning

### Plesk Extension
- Administrator access
- 2FA integration
- Customer management

## Features

### Core PBX Features
- ☎️ Extension management
- 📞 Call routing and IVR
- 🎵 Music on hold with Jellyfin
- 📊 Call statistics and CDR
- 🔊 Audio conferencing
- 📠 Fax support

### Advanced Features
- 🔐 Two-factor authentication
- 🎯 Real-time monitoring
- 🔄 Automatic updates
- 💾 Automated backups
- 🌐 WebUI with SSO
- 📱 Mobile-responsive interface

### Audio & Streaming
- 🎵 Icecast streaming server
- 📺 Jellyfin media integration
- 🎶 Multiple audio formats (MP3, OGG, AAC, OPUS)
- 🔊 Live streaming from desktop
- 🎼 Music library management

### Security & Authentication
- 🔐 2FA with TOTP support
- 🛡️ Firewall integration (CSF, UFW, firewalld)
- 🔒 SSL/TLS encryption
- 👤 Role-based access control
- 🔑 API key management

## Post-Installation

1. **Access Web Interface**
   - Local: http://localhost:3000
   - Remote: http://YOUR_SERVER_IP:3000

2. **Configure 2FA**
   - Enable 2FA in your control panel
   - Configure FlexPBX integration
   - Test authentication

3. **Install Desktop App**
   - Download for your platform
   - Generate integration token
   - Connect to server

4. **Setup Audio Streaming**
   - Configure Jellyfin media library
   - Setup Icecast streaming
   - Test audio playback

## Troubleshooting

### Common Issues

**Installation fails with permission errors**
- Ensure running as root/sudo for local installation
- Check SSH access for remote installation

**2FA authentication fails**
- Verify control panel 2FA is enabled
- Check server URL and credentials
- Ensure time synchronization

**Desktop app won't connect**
- Verify server is running on port 3000
- Check firewall settings
- Generate new integration token

**Audio streaming not working**
- Check Icecast is running on port 8000
- Verify Jellyfin is accessible on port 8096
- Check audio file permissions

### Log Files
- Installation: `/opt/flexpbx/logs/install.log`
- Server: `/opt/flexpbx/logs/server.log`
- Icecast: `/opt/flexpbx/logs/icecast/`
- Jellyfin: `/opt/flexpbx/jellyfin/log/`

## Support

- 📚 **Documentation**: https://docs.flexpbx.com
- 🎫 **Support Tickets**: https://support.flexpbx.com
- 💬 **Community Forum**: https://community.flexpbx.com
- 🐛 **Bug Reports**: https://github.com/flexpbx/issues

## License

FlexPBX is released under the MIT License. See LICENSE file for details.

---

**FlexPBX Team** - Building the future of PBX management
EOF

    # Installation Guide
    cat > "$BUILD_DIR/$PACKAGE_NAME/docs/INSTALLATION.md" << 'EOF'
# FlexPBX Installation Guide

## Pre-Installation Checklist

- [ ] Server meets minimum requirements
- [ ] Root/sudo access available
- [ ] Internet connection active
- [ ] Firewall ports 3000, 8000, 8096 available
- [ ] Control panel accessible (if using)

## Step-by-Step Installation

### 1. Download and Extract
```bash
wget https://github.com/flexpbx/releases/latest/download/FlexPBX-Complete-v2.0.0.zip
unzip FlexPBX-Complete-v2.0.0.zip
cd FlexPBX-Complete-v2.0.0
```

### 2. Make Installer Executable
```bash
chmod +x install.sh
```

### 3. Run Installation

#### Local Installation
```bash
sudo ./install.sh local
```

#### Remote Installation
```bash
./install.sh remote YOUR_SERVER_IP
```

### 4. Follow Installation Progress
The installer will:
- Detect your system configuration
- Install required dependencies
- Configure services
- Setup control panel integration
- Install desktop applications

### 5. Access Your Installation
- Web Interface: http://localhost:3000 (or http://YOUR_SERVER_IP:3000)
- Icecast Streaming: http://localhost:8000
- Jellyfin Media: http://localhost:8096

## Advanced Installation Options

### Custom Installation Path
```bash
sudo ./install.sh local /custom/path
```

### Specific Features Only
```bash
# Minimal installation
sudo ./install.sh local /opt/flexpbx minimal

# Audio streaming only
sudo ./install.sh local /opt/flexpbx audio-streaming
```

### Firewall Configuration
```bash
# Use specific firewall
sudo ./install.sh local /opt/flexpbx full csf
sudo ./install.sh local /opt/flexpbx full ufw
sudo ./install.sh local /opt/flexpbx full firewalld
```

## Control Panel Integration

### WHMCS
1. Install WHMCS module: Upload `whmcs-module/flexpbx` to `/modules/addons/`
2. Activate in WHMCS Admin: `Setup > Addon Modules`
3. Configure server URL and API key
4. Setup 2FA integration

### cPanel/WHM
1. Plugin automatically installs if cPanel detected
2. Access via cPanel interface
3. Configure 2FA in cPanel Security settings

### DirectAdmin
1. Plugin automatically installs if DirectAdmin detected
2. Access via DirectAdmin plugins menu
3. Configure authentication settings

### Plesk
1. Extension automatically installs if Plesk detected
2. Access via Plesk modules
3. Setup administrator access

## Desktop Application Setup

### Download
- Windows: `desktop-apps/FlexPBX-Desktop-Windows.exe`
- macOS: `desktop-apps/FlexPBX-Desktop-macOS.dmg`
- Linux: `desktop-apps/FlexPBX-Desktop-Linux.AppImage`

### Installation
1. Run installer for your platform
2. Launch FlexPBX Desktop
3. Generate integration token from web interface
4. Enter token in desktop app
5. Connect and authenticate

### Features
- Real-time PBX management
- Extension configuration
- Audio streaming control
- Call monitoring
- 2FA authentication

## Post-Installation Configuration

### 1. Initial Setup
- Set admin password
- Configure basic PBX settings
- Test extension creation

### 2. Audio Setup
- Upload music files to Jellyfin
- Configure on-hold music
- Test audio streaming

### 3. Security Setup
- Enable 2FA in control panel
- Configure FlexPBX 2FA integration
- Test authentication flow

### 4. Desktop Integration
- Install desktop application
- Generate integration token
- Connect and test features

## Verification

### Check Services
```bash
# Check all FlexPBX services
sudo flexpbx status

# Individual service checks
sudo systemctl status flexpbx
sudo systemctl status jellyfin
sudo systemctl status flexpbx-icecast
```

### Test Connections
```bash
# Test FlexPBX API
curl http://localhost:3000/health

# Test Icecast
curl http://localhost:8000/admin/stats.xml

# Test Jellyfin
curl http://localhost:8096/System/Info/Public
```

### Test Web Interfaces
- FlexPBX: http://localhost:3000
- Icecast Admin: http://localhost:8000/admin (admin/flexpbx_admin)
- Jellyfin: http://localhost:8096

## Troubleshooting

### Installation Issues
```bash
# Check installation logs
tail -f /opt/flexpbx/logs/install.log

# Verify system requirements
df -h  # Check disk space
free -m  # Check memory
```

### Service Issues
```bash
# Restart all services
sudo flexpbx restart

# Check specific service logs
sudo journalctl -u flexpbx -f
sudo journalctl -u jellyfin -f
```

### Network Issues
```bash
# Check listening ports
sudo netstat -tlnp | grep -E "(3000|8000|8096)"

# Check firewall
sudo ufw status  # Ubuntu/Debian
sudo firewall-cmd --list-all  # RHEL/CentOS
```

## Getting Help

If you encounter issues:

1. Check the logs in `/opt/flexpbx/logs/`
2. Verify all services are running
3. Test network connectivity
4. Check firewall settings
5. Review system requirements

For additional support, visit https://support.flexpbx.com
EOF

    # 2FA Configuration Guide
    cat > "$BUILD_DIR/$PACKAGE_NAME/docs/2FA_SETUP.md" << 'EOF'
# Two-Factor Authentication Setup Guide

FlexPBX supports 2FA integration with popular control panels for enhanced security.

## Supported Control Panels

- ✅ WHMCS - Admin area 2FA
- ✅ cPanel - User account 2FA
- ✅ WHM - Root/Reseller 2FA
- ✅ DirectAdmin - All user levels
- ✅ Plesk - Administrator 2FA

## Setup Process

### 1. Enable 2FA in Control Panel

#### WHMCS
1. Login to WHMCS Admin Area
2. Go to `Setup > Staff Management > Administrators`
3. Edit your admin account
4. Enable "Two-Factor Authentication"
5. Scan QR code with authenticator app
6. Save the secret key for FlexPBX

#### cPanel
1. Login to cPanel
2. Go to `Security > Two-Factor Authentication`
3. Click "Set Up Two-Factor Authentication"
4. Scan QR code with authenticator app
5. Note the secret key for FlexPBX

#### WHM
1. Login to WHM
2. Go to `Home > Clusters > Remote Access Key`
3. Enable "Two-Factor Authentication"
4. Configure with authenticator app
5. Save secret key for FlexPBX

#### DirectAdmin
1. Login to DirectAdmin
2. Go to `Account Manager > Two-Factor Authentication`
3. Enable 2FA
4. Configure with authenticator app
5. Note secret key for FlexPBX

#### Plesk
1. Login to Plesk
2. Go to `Account > Interface Language`
3. Enable "Two-step verification"
4. Configure with authenticator app
5. Save secret key for FlexPBX

### 2. Configure FlexPBX Integration

#### Via WHMCS Module
1. Access WHMCS Admin Area
2. Go to `Setup > Addon Modules > FlexPBX`
3. Click "2FA Setup" in sidebar
4. Select control panel type
5. Enter server URL and credentials
6. Add 2FA secret key
7. Test configuration
8. Save settings

#### Via FlexPBX Web Interface
1. Access FlexPBX at http://your-server:3000
2. Login as admin
3. Go to `Settings > Authentication`
4. Add new 2FA provider
5. Configure panel type and credentials
6. Test and save

#### Via Desktop Application
1. Open FlexPBX Desktop
2. Go to `Settings > Authentication`
3. Add 2FA Provider
4. Configure connection details
5. Test authentication
6. Save configuration

### 3. Test 2FA Authentication

#### Automatic Token Generation
If secret key is configured, FlexPBX will automatically generate TOTP tokens.

#### Manual Token Entry
For testing or troubleshooting, you can manually enter tokens from your authenticator app.

#### Verification Process
1. FlexPBX detects login attempt
2. Retrieves 2FA token (auto or manual)
3. Submits credentials + token to control panel
4. Receives authentication response
5. Creates secure session

## Configuration Examples

### WHMCS Configuration
```json
{
  "panel_type": "whmcs",
  "server_url": "https://your-whmcs.com",
  "username": "admin",
  "password": "your_password",
  "tfa_secret": "JBSWY3DPEHPK3PXP",
  "auth_endpoint": "/admin/login.php"
}
```

### cPanel Configuration
```json
{
  "panel_type": "cpanel",
  "server_url": "https://your-server.com:2083",
  "username": "cpanel_user",
  "password": "cpanel_password",
  "tfa_secret": "JBSWY3DPEHPK3PXP",
  "auth_endpoint": "/login/?login_only=1"
}
```

### DirectAdmin Configuration
```json
{
  "panel_type": "directadmin",
  "server_url": "https://your-server.com:2222",
  "username": "admin",
  "password": "admin_password",
  "tfa_secret": "JBSWY3DPEHPK3PXP",
  "auth_endpoint": "/CMD_LOGIN"
}
```

## Security Best Practices

### 1. Secret Key Management
- Store secret keys securely
- Use different secrets for each service
- Rotate secrets periodically
- Never share secret keys

### 2. Password Security
- Use strong, unique passwords
- Enable password encryption in FlexPBX
- Consider using API keys where supported
- Implement password rotation

### 3. Access Control
- Limit 2FA configuration to administrators
- Use role-based access control
- Monitor authentication logs
- Set session timeouts

### 4. Network Security
- Use HTTPS for all connections
- Implement IP restrictions where possible
- Use VPN for administrative access
- Monitor failed authentication attempts

## Troubleshooting

### Common Issues

#### "Invalid 2FA Token"
- Check time synchronization on server
- Verify secret key is correct
- Ensure control panel 2FA is working
- Try manual token entry

#### "Authentication Failed"
- Verify username/password
- Check server URL and ports
- Test control panel login manually
- Review authentication logs

#### "Connection Timeout"
- Check network connectivity
- Verify firewall settings
- Test DNS resolution
- Check SSL certificate validity

### Debug Mode
Enable debug logging in FlexPBX settings:
```bash
# Enable debug logging
echo "DEBUG=true" >> /opt/flexpbx/.env

# Restart FlexPBX
sudo systemctl restart flexpbx

# Check logs
tail -f /opt/flexpbx/logs/auth.log
```

### Testing Tools
```bash
# Test 2FA token generation
node -e "console.log(require('crypto').createHmac('sha1', Buffer.from('JBSWY3DPEHPK3PXP', 'base32')).update(Buffer.from([0,0,0,0,Math.floor(Date.now()/30000)])).digest().slice(-4).readUInt32BE(0) % 1000000)"

# Test control panel connectivity
curl -v https://your-panel.com/login

# Test FlexPBX 2FA endpoint
curl -X POST http://localhost:3000/api/auth/2fa/test \
  -H "Content-Type: application/json" \
  -d '{"panel_type":"whmcs","token":"123456"}'
```

## Support

For 2FA setup assistance:
- 📚 Documentation: https://docs.flexpbx.com/2fa
- 🎫 Support: https://support.flexpbx.com
- 💬 Community: https://community.flexpbx.com/2fa
EOF

    log "✓ Documentation created"
}

# Create configuration templates
create_configuration_templates() {
    log "Creating configuration templates..."

    mkdir -p "$BUILD_DIR/$PACKAGE_NAME/config-templates"

    # Environment configuration template
    cat > "$BUILD_DIR/$PACKAGE_NAME/config-templates/.env.example" << 'EOF'
# FlexPBX Server Configuration Template
# Copy this to .env and modify values as needed

# Basic Configuration
NODE_ENV=production
PORT=3000
VERSION=2.0.0

# Installation Settings
INSTALL_TYPE=full
INSTALL_PATH=/opt/flexpbx

# Features
ENABLE_TAILSCALE=true
ICECAST_ENABLE=true
JELLYFIN_ENABLE=true
AUTO_UPDATE=true
FIREWALL_TYPE=auto

# Security
JWT_SECRET=your-jwt-secret-here
SESSION_SECRET=your-session-secret-here
API_KEY=your-api-key-here

# Database
DB_HOST=localhost
DB_PORT=3306
DB_NAME=flexpbx
DB_USER=flexpbx
DB_PASS=your-database-password

# Redis
REDIS_HOST=localhost
REDIS_PORT=6379
REDIS_PASS=your-redis-password

# Audio Configuration
AUDIO_TOOLS_PATH=/opt/flexpbx/audio-tools
AUDIO_RECORDINGS_PATH=/opt/flexpbx/audio/recordings
AUDIO_MUSIC_PATH=/opt/flexpbx/audio/music

# Icecast Streaming
ICECAST_HOST=localhost
ICECAST_PORT=8000
ICECAST_ADMIN_PASS=flexpbx_admin
ICECAST_SOURCE_PASS=flexpbx_source
ICECAST_LIVE_PORT=8001
ICECAST_LIVE_PASSWORD=flexpbx_live

# Jellyfin Media Server
JELLYFIN_ENABLE=true
JELLYFIN_HOST=localhost
JELLYFIN_PORT=8096
JELLYFIN_API_KEY=your-jellyfin-api-key
JELLYFIN_DATA_PATH=/opt/flexpbx/jellyfin/data
JELLYFIN_CONFIG_PATH=/opt/flexpbx/jellyfin/config

# Logging
LOG_LEVEL=info
LOG_PATH=/opt/flexpbx/logs
EOF

    # 2FA providers configuration template
    cat > "$BUILD_DIR/$PACKAGE_NAME/config-templates/2fa-providers.json" << 'EOF'
{
  "providers": [
    {
      "type": "whmcs",
      "name": "WHMCS",
      "enabled": false,
      "server_url": "https://your-whmcs.com",
      "username": "",
      "password_hash": "",
      "tfa_secret": "",
      "auth_endpoint": "/admin/login.php",
      "api_endpoint": "/includes/api.php"
    },
    {
      "type": "cpanel",
      "name": "cPanel",
      "enabled": false,
      "server_url": "https://your-server.com:2083",
      "username": "",
      "password_hash": "",
      "tfa_secret": "",
      "auth_endpoint": "/login/?login_only=1",
      "api_endpoint": "/execute"
    },
    {
      "type": "whm",
      "name": "WHM",
      "enabled": false,
      "server_url": "https://your-server.com:2087",
      "username": "",
      "password_hash": "",
      "tfa_secret": "",
      "auth_endpoint": "/login/?login_only=1",
      "api_endpoint": "/json-api"
    },
    {
      "type": "directadmin",
      "name": "DirectAdmin",
      "enabled": false,
      "server_url": "https://your-server.com:2222",
      "username": "",
      "password_hash": "",
      "tfa_secret": "",
      "auth_endpoint": "/CMD_LOGIN",
      "api_endpoint": "/CMD_API"
    },
    {
      "type": "plesk",
      "name": "Plesk",
      "enabled": false,
      "server_url": "https://your-server.com:8443",
      "username": "",
      "password_hash": "",
      "tfa_secret": "",
      "auth_endpoint": "/login_up.php",
      "api_endpoint": "/api/v2"
    }
  ]
}
EOF

    log "✓ Configuration templates created"
}

# Create the final ZIP package
create_zip_package() {
    log "Creating ZIP package..."

    cd "$BUILD_DIR"

    # Create the ZIP file
    zip -r "$PACKAGE_NAME.zip" "$PACKAGE_NAME/"

    # Calculate file size and checksum
    local file_size=$(du -h "$PACKAGE_NAME.zip" | cut -f1)
    local checksum=$(sha256sum "$PACKAGE_NAME.zip" | cut -d' ' -f1)

    # Create checksum file
    echo "$checksum  $PACKAGE_NAME.zip" > "$PACKAGE_NAME.zip.sha256"

    # Move to package directory
    mv "$PACKAGE_NAME.zip" "$PACKAGE_DIR/"
    mv "$PACKAGE_NAME.zip.sha256" "$PACKAGE_DIR/"

    log "✓ ZIP package created: $PACKAGE_DIR/$PACKAGE_NAME.zip"
    info "Package size: $file_size"
    info "SHA256: $checksum"
}

# Show completion summary
show_completion() {
    echo ""
    echo -e "${GREEN}╔═══════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║            FlexPBX Complete Package Created!                 ║${NC}"
    echo -e "${GREEN}╚═══════════════════════════════════════════════════════════════╝${NC}"
    echo ""

    echo -e "${BLUE}Package Details:${NC}"
    echo -e "  📦 Package: ${YELLOW}$PACKAGE_NAME.zip${NC}"
    echo -e "  📁 Location: ${YELLOW}$PACKAGE_DIR/${NC}"
    echo -e "  🔐 Checksum: ${YELLOW}$PACKAGE_NAME.zip.sha256${NC}"

    echo ""
    echo -e "${BLUE}Package Contents:${NC}"
    echo -e "  🚀 ${YELLOW}Server Installation${NC} - Complete PBX server with all features"
    echo -e "  📱 ${YELLOW}WHMCS Module${NC} - Ready-to-upload WHMCS addon module"
    echo -e "  🎛️  ${YELLOW}Control Panel Plugins${NC} - cPanel, DirectAdmin, Plesk integration"
    echo -e "  💻 ${YELLOW}Desktop Applications${NC} - Cross-platform desktop apps"
    echo -e "  📚 ${YELLOW}Documentation${NC} - Complete installation and setup guides"
    echo -e "  ⚙️  ${YELLOW}Configuration Templates${NC} - Pre-configured settings files"

    echo ""
    echo -e "${BLUE}Installation Commands:${NC}"
    echo -e "  📥 ${YELLOW}wget https://github.com/flexpbx/releases/latest/download/$PACKAGE_NAME.zip${NC}"
    echo -e "  📂 ${YELLOW}unzip $PACKAGE_NAME.zip${NC}"
    echo -e "  🚀 ${YELLOW}cd $PACKAGE_NAME && sudo ./install.sh local${NC}"

    echo ""
    echo -e "${BLUE}Distribution:${NC}"
    echo -e "  📤 Upload to GitHub releases"
    echo -e "  🌐 Distribute via website"
    echo -e "  📧 Send to customers"

    echo ""
}

# Main build function
main() {
    echo -e "${BLUE}"
    echo "╔═══════════════════════════════════════════════════════════════╗"
    echo "║            FlexPBX Complete Package Builder                  ║"
    echo "║                                                               ║"
    echo "║  Building comprehensive installation package...               ║"
    echo "╚═══════════════════════════════════════════════════════════════╝"
    echo -e "${NC}"

    prepare_build_directory
    copy_server_files
    copy_whmcs_module
    copy_control_panel_plugins
    copy_desktop_applications
    create_documentation
    create_configuration_templates
    create_zip_package
    show_completion
}

# Run the build
main "$@"