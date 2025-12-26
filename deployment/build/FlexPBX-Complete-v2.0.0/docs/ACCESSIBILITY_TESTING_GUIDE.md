# FlexPBX Accessibility Testing Guide

## 🎯 Screen Reader Compatible Installation

This guide provides simple, step-by-step instructions for screen reader users to test FlexPBX on various server types.

---

## 📁 Simple File Upload Method

### Step 1: Get the Installation Package
The FlexPBX installation package is a single ZIP file located at:
```
/Users/administrator/dev/apps/flex pbx/flexpbx/deployment/build/FlexPBX-Complete-v1.0.0.zip
```

File size: Approximately 667 megabytes

### Step 2: Upload to Your Server Account

#### Option A: Home Directory Upload
```bash
# Upload to user's home directory
scp FlexPBX-Complete-v1.0.0.zip username@your-server.com:/home/username/

# Alternative using rsync (with progress)
rsync -avP FlexPBX-Complete-v1.0.0.zip username@your-server.com:/home/username/
```

#### Option B: Public Web Directory Upload
```bash
# Upload to web accessible directory
scp FlexPBX-Complete-v1.0.0.zip username@your-server.com:/home/username/public_html/

# For cPanel accounts
scp FlexPBX-Complete-v1.0.0.zip username@your-server.com:/home/username/public_html/downloads/
```

#### Option C: Custom Directory Upload
```bash
# Upload to any directory you prefer
scp FlexPBX-Complete-v1.0.0.zip username@your-server.com:/home/username/flexpbx/
```

### Step 3: Connect to Your Server
```bash
# SSH connection with screen reader friendly output
ssh username@your-server.com

# After connecting, you'll see:
# Welcome message
# Current directory: /home/username
```

---

## 🔧 Screen Reader Friendly Installation

### Method 1: Simple One-Command Installation

#### In Home Directory
```bash
# Navigate to home directory
cd /home/username

# Extract the package
unzip FlexPBX-Complete-v1.0.0.zip

# Navigate to extracted folder
cd FlexPBX-Complete-v1.0.0

# Make installation script executable
chmod +x install.sh

# Run installation (will announce progress)
./install.sh local --accessible
```

#### In Public Web Directory
```bash
# Navigate to web directory
cd /home/username/public_html

# Extract the package
unzip FlexPBX-Complete-v1.0.0.zip

# Navigate and install
cd FlexPBX-Complete-v1.0.0
chmod +x install.sh
./install.sh local --accessible --web-root /home/username/public_html
```

### Method 2: Step-by-Step Installation

#### Step 2.1: Extract Files
```bash
# Check if file exists
ls -la FlexPBX-Complete-v1.0.0.zip

# Output will announce:
# File found: FlexPBX-Complete-v1.0.0.zip (667MB)

# Extract with progress
unzip -v FlexPBX-Complete-v1.0.0.zip
```

#### Step 2.2: Review Contents
```bash
# List extracted contents
ls -la FlexPBX-Complete-v1.0.0/

# You will hear:
# Directory listing with 10 items:
# - server/ (server installation files)
# - desktop-apps/ (desktop applications)
# - whmcs-module/ (WHMCS integration)
# - docs/ (documentation)
# - install.sh (main installer)
```

#### Step 2.3: Run Installation
```bash
# Navigate to installation directory
cd FlexPBX-Complete-v1.0.0

# Check installer help
./install.sh --help

# Screen reader will announce:
# FlexPBX Installation Options:
# local - Install on current server
# remote - Install on remote server
# --accessible - Enable screen reader friendly output
```

---

## 🎙️ Screen Reader Friendly Commands

### Installation with Audio Feedback
```bash
# Installation with detailed announcements
./install.sh local --accessible --verbose

# This will announce each step:
# "Step 1 of 8: Checking system requirements"
# "Step 2 of 8: Installing Docker containers"
# "Step 3 of 8: Setting up database"
# And so on...
```

### Progress Monitoring
```bash
# Check installation progress
tail -f /var/log/flexpbx-install.log

# Monitor service status
systemctl status flexpbx --no-pager

# Check running services
ss -tlnp | grep -E "3000|8000|8096"
```

### Testing Commands
```bash
# Test web interface accessibility
curl -s http://localhost:3000 | grep -i "title\|heading\|FlexPBX"

# Test if services are responding
curl -I http://localhost:3000
curl -I http://localhost:8000
curl -I http://localhost:8096

# Check service health
curl http://localhost:3000/health
```

---

## 🌐 Accessible Web Interface Testing

### Navigate to Web Interface
After installation, the web interface will be available at:

**Local Access:**
- Main Interface: `http://localhost:3000`
- Admin Panel: `http://localhost:3000/admin`

**Remote Access (replace with your server IP):**
- Main Interface: `http://your-server-ip:3000`
- Admin Panel: `http://your-server-ip:3000/admin`

### Screen Reader Navigation
The FlexPBX web interface includes:

1. **Proper ARIA Labels**: All buttons and forms are labeled
2. **Heading Structure**: Logical H1, H2, H3 hierarchy
3. **Skip Links**: Jump to main content
4. **Keyboard Navigation**: Full keyboard accessibility
5. **Form Labels**: All inputs properly labeled

### Keyboard Shortcuts
- `Tab`: Navigate through interface elements
- `Enter`: Activate buttons and links
- `Space`: Check/uncheck checkboxes
- `Arrow Keys`: Navigate menus and lists
- `Escape`: Close modals and popups

---

## 📱 Control Panel Integration (Accessible)

### WHMCS Module Upload (cPanel/WHM)

#### Step 1: Locate WHMCS Module
```bash
# Find the WHMCS module
find /home/username -name "flexpbx" -type d

# Output: /home/username/FlexPBX-Complete-v1.0.0/whmcs-module/flexpbx
```

#### Step 2: Upload to WHMCS
```bash
# Copy to WHMCS modules directory
cp -r /home/username/FlexPBX-Complete-v1.0.0/whmcs-module/flexpbx /home/username/public_html/whmcs/modules/addons/

# Set proper permissions
chown -R username:username /home/username/public_html/whmcs/modules/addons/flexpbx
chmod -R 755 /home/username/public_html/whmcs/modules/addons/flexpbx
```

#### Step 3: Activate in WHMCS
1. Login to WHMCS admin area
2. Navigate to: Setup > Addon Modules
3. Find "FlexPBX Management" in the list
4. Click "Activate"
5. Configure access permissions

### 2FA Setup (Screen Reader Friendly)
The 2FA setup interface includes:
- Clear form labels for all inputs
- Step-by-step instructions
- Audio feedback for QR code generation
- Text alternatives for visual elements

---

## 🖥️ Desktop Application (Accessibility)

### macOS Desktop App
```bash
# Launch desktop app
open "/home/username/FlexPBX-Complete-v1.0.0/desktop-apps/mac/FlexPBX Desktop.app"

# Or from command line with accessibility
./FlexPBX\ Desktop --enable-accessibility
```

### Windows Desktop App
```bash
# On Windows server
cd "C:\Users\username\FlexPBX-Complete-v1.0.0\desktop-apps\win-unpacked"
"FlexPBX Desktop.exe" --enable-accessibility
```

### Linux Desktop App
```bash
# On Linux server
cd /home/username/FlexPBX-Complete-v1.0.0/desktop-apps/linux
chmod +x FlexPBX-Desktop.AppImage
./FlexPBX-Desktop.AppImage --enable-accessibility
```

---

## 🔊 Audio Testing

### Test Audio Streaming
```bash
# Check if audio service is running
systemctl status icecast2

# Test audio stream
curl -I http://localhost:8000/stream

# Check audio quality
wget -O - http://localhost:8000/stream | file -
```

### Media Server Testing
```bash
# Test Jellyfin media server
curl http://localhost:8096/health

# Check available media
curl http://localhost:8096/api/items | jq '.Items[].Name'
```

---

## 📋 Accessibility Checklist

### ✅ Installation Accessibility
- [ ] Installation script provides audio feedback
- [ ] Progress indicators are announced
- [ ] Error messages are descriptive
- [ ] Commands work with screen readers

### ✅ Web Interface Accessibility
- [ ] Proper heading structure (H1, H2, H3)
- [ ] All images have alt text
- [ ] Forms have proper labels
- [ ] Keyboard navigation works
- [ ] Color contrast meets WCAG standards

### ✅ Desktop App Accessibility
- [ ] Screen reader compatible
- [ ] Keyboard shortcuts work
- [ ] Focus indicators visible
- [ ] Audio feedback available

### ✅ Control Panel Integration
- [ ] WHMCS module is accessible
- [ ] 2FA setup works with screen readers
- [ ] Configuration forms are labeled
- [ ] Help text is available

---

## 🚨 Troubleshooting (Accessible)

### Common Issues with Audio Feedback
```bash
# Check if installation completed
grep "Installation complete" /var/log/flexpbx-install.log

# Verify services are running
systemctl list-units --state=active | grep flexpbx

# Test web interface accessibility
curl -s http://localhost:3000 | grep -E "aria-|role=|alt="
```

### Get Help Commands
```bash
# View installation log
less /var/log/flexpbx-install.log

# Check system status
./FlexPBX-Complete-v1.0.0/server/status.sh --accessible

# Get support information
cat /home/username/FlexPBX-Complete-v1.0.0/docs/SUPPORT.md
```

---

## 🎯 Quick Start for Screen Reader Users

### 30-Second Setup
```bash
# 1. Upload file to your preferred directory
scp FlexPBX-Complete-v1.0.0.zip username@server.com:/home/username/

# 2. Connect and extract
ssh username@server.com
cd /home/username
unzip FlexPBX-Complete-v1.0.0.zip

# 3. Install with accessibility
cd FlexPBX-Complete-v1.0.0
chmod +x install.sh
./install.sh local --accessible

# 4. Test (after 5 minutes)
curl http://localhost:3000
```

### What You'll Hear During Installation
1. **"Welcome to FlexPBX installer"**
2. **"Checking system requirements - Please wait"**
3. **"Installing Docker services - Step 2 of 8"**
4. **"Setting up database - Step 3 of 8"**
5. **"Installing PBX components - Step 4 of 8"**
6. **"Configuring audio services - Step 5 of 8"**
7. **"Setting up web interface - Step 6 of 8"**
8. **"Configuring 2FA system - Step 7 of 8"**
9. **"Installation complete - FlexPBX is ready"**

This accessible approach ensures that screen reader users can successfully install and test FlexPBX on any server type with clear audio feedback and simple commands.