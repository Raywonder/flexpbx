# FlexPBX Testing Guide

## Quick Testing Options

### 🍎 macOS Local Testing
Test the complete FlexPBX system on your Mac with Docker containers.

### 🖥️ VPS Remote Testing
Test on a remote VPS with real server deployment.

---

## 🍎 macOS Local Testing

### Prerequisites
```bash
# Install required tools
brew install docker docker-compose node npm
brew install --cask docker
```

### Option 1: Quick Docker Test
```bash
# Navigate to project
cd "/Users/administrator/dev/apps/flex pbx/flexpbx"

# Start minimal Docker environment
docker-compose -f deployment/docker/docker-compose-minimal.yml up -d

# Check services
docker-compose ps
```

### Option 2: Full Local Installation
```bash
# Extract the complete package
cd deployment/build
unzip FlexPBX-Complete-v1.0.0.zip
cd FlexPBX-Complete-v1.0.0

# Run local installation
sudo ./install.sh local
```

### Option 3: Desktop App Testing
```bash
# Install desktop app from package
cd desktop-apps/mac
open "FlexPBX Desktop.app"

# Or install from dmg
open "FlexPBX Desktop-1.0.0-arm64.dmg"
```

### Testing URLs (Local)
- **FlexPBX Server:** http://localhost:3000
- **Admin Panel:** http://localhost:3000/admin
- **Audio Streaming:** http://localhost:8000
- **Jellyfin Media:** http://localhost:8096
- **WebUI Test:** http://localhost:3000/webui

---

## 🖥️ VPS Remote Testing

### Recommended VPS Providers
- **DigitalOcean:** $6/month droplet (2GB RAM)
- **Vultr:** $6/month instance (2GB RAM)
- **Linode:** $12/month nanode (2GB RAM)
- **AWS:** t3.small instance (2GB RAM)

### VPS Requirements
```
Minimum: 1GB RAM, 1 CPU, 20GB storage
Recommended: 2GB RAM, 2 CPU, 40GB storage
OS: Ubuntu 20.04/22.04, CentOS 8+, AlmaLinux 8+
```

### Quick VPS Setup Commands

#### 1. Create VPS and Connect
```bash
# Replace with your VPS IP
export VPS_IP="your.vps.ip.address"
ssh root@$VPS_IP
```

#### 2. Upload Installation Package
```bash
# From your Mac, upload the package
scp -r "deployment/build/FlexPBX-Complete-v1.0.0.zip" root@$VPS_IP:/tmp/

# On VPS, extract and install
ssh root@$VPS_IP "
cd /tmp
unzip FlexPBX-Complete-v1.0.0.zip
cd FlexPBX-Complete-v1.0.0
chmod +x install.sh
./install.sh local
"
```

#### 3. Remote Installation from Mac
```bash
# Use the remote installer
cd "/Users/administrator/dev/apps/flex pbx/flexpbx/deployment/build/FlexPBX-Complete-v1.0.0"
./install.sh remote $VPS_IP
```

### Testing URLs (VPS)
- **FlexPBX Server:** http://your.vps.ip:3000
- **Admin Panel:** http://your.vps.ip:3000/admin
- **Audio Streaming:** http://your.vps.ip:8000
- **Jellyfin Media:** http://your.vps.ip:8096

---

## 🧪 Automated Testing

### Run Test Suite
```bash
# Run comprehensive tests
cd "/Users/administrator/dev/apps/flex pbx/flexpbx"
./test-suite.sh local    # Test locally
./test-suite.sh vps $VPS_IP  # Test on VPS
```

### Manual Test Checklist

#### ✅ Core Services
- [ ] FlexPBX server starts on port 3000
- [ ] Admin panel accessible
- [ ] Database connections working
- [ ] Asterisk PBX running
- [ ] Audio streaming active

#### ✅ Desktop Application
- [ ] App launches on macOS
- [ ] Connects to server
- [ ] WebUI loads properly
- [ ] 2FA authentication works
- [ ] Real-time updates function

#### ✅ Control Panel Integration
- [ ] WHMCS module uploads successfully
- [ ] 2FA setup completes
- [ ] Desktop integration token generates
- [ ] Account provisioning works

#### ✅ Audio & Media
- [ ] Icecast streaming works
- [ ] Jellyfin media server accessible
- [ ] On-hold music plays
- [ ] Audio quality acceptable

---

## 🚨 Troubleshooting

### Common macOS Issues
```bash
# Docker not starting
sudo killall Docker && open /Applications/Docker.app

# Permission denied
sudo chown -R $(whoami) "/Users/administrator/dev/apps/flex pbx/flexpbx"

# Port conflicts
sudo lsof -i :3000
sudo kill -9 PID
```

### Common VPS Issues
```bash
# Firewall blocking ports
sudo ufw allow 3000
sudo ufw allow 8000
sudo ufw allow 8096

# Memory issues
free -h
sudo systemctl restart flexpbx

# Service status
sudo systemctl status flexpbx
sudo journalctl -u flexpbx -f
```

### Log Locations
- **macOS Logs:** `~/Library/Logs/FlexPBX/`
- **VPS Logs:** `/var/log/flexpbx/`
- **Docker Logs:** `docker logs flexpbx-server`

---

## 📊 Performance Testing

### Load Testing Commands
```bash
# Test concurrent connections
ab -n 1000 -c 10 http://localhost:3000/

# Test WebSocket connections
wscat -c ws://localhost:3000/ws

# Test SIP registrations
sipp -sn uac localhost:5060
```

### Monitor Resources
```bash
# macOS monitoring
top -pid $(pgrep FlexPBX)
docker stats

# VPS monitoring
htop
iotop
netstat -tulpn
```

---

## 🎯 Quick Start Commands

### macOS Testing (5 minutes)
```bash
cd "/Users/administrator/dev/apps/flex pbx/flexpbx"
docker-compose up -d
open http://localhost:3000
```

### VPS Testing (10 minutes)
```bash
# Replace with your VPS IP
VPS_IP="your.vps.ip"
cd "/Users/administrator/dev/apps/flex pbx/flexpbx/deployment/build/FlexPBX-Complete-v1.0.0"
./install.sh remote $VPS_IP
open http://$VPS_IP:3000
```

Both environments will give you a complete FlexPBX system for testing all features including 2FA, control panel integration, and desktop application connectivity.