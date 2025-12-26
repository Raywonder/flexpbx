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
