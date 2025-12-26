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
