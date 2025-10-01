#!/bin/bash
#
# FlexPBX Complete Installation Package
# Supports local and remote server installation with 2FA integration
#
# Usage: ./install.sh [local|remote] [server_ip] [options...]
#

set -e

# Version and configuration
VERSION="2.0.0"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
INSTALL_TYPE="${1:-local}"
REMOTE_SERVER="${2:-}"

# Colors and logging
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
NC='\033[0m'

log() {
    echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')] $1${NC}"
}

warn() {
    echo -e "${YELLOW}[WARNING] $1${NC}"
}

error() {
    echo -e "${RED}[ERROR] $1${NC}"
    exit 1
}

# Show banner
show_banner() {
    echo -e "${BLUE}"
    cat << 'EOF'
╔═══════════════════════════════════════════════════════════════╗
║           FlexPBX Complete Installation Package              ║
║                                                               ║
║  🚀 Full PBX System with 2FA & Desktop Integration          ║
║  📱 Supports: WHMCS, cPanel, WHM, DirectAdmin, Plesk        ║
║  🔐 Advanced 2FA Authentication                              ║
║  💻 Cross-platform Desktop Application                       ║
╚═══════════════════════════════════════════════════════════════╝
EOF
    echo -e "${NC}"
}

# Check requirements
check_requirements() {
    log "Checking installation requirements..."

    # Check if running as root for local installation
    if [ "$INSTALL_TYPE" = "local" ] && [ "$EUID" -ne 0 ]; then
        error "Local installation must be run as root. Use: sudo $0 local"
    fi

    # Check for required tools
    local required_tools=("curl" "wget" "unzip")

    for tool in "${required_tools[@]}"; do
        if ! command -v "$tool" &> /dev/null; then
            error "Required tool '$tool' is not installed"
        fi
    done

    # Check for SSH if remote installation
    if [ "$INSTALL_TYPE" = "remote" ]; then
        if [ -z "$REMOTE_SERVER" ]; then
            error "Remote server IP/hostname is required for remote installation"
        fi

        if ! command -v ssh &> /dev/null; then
            error "SSH is required for remote installation"
        fi
    fi

    log "✓ Requirements check passed"
}

# Install locally
install_local() {
    log "Starting local FlexPBX installation..."

    # Copy and run the comprehensive server installer
    cp "$SCRIPT_DIR/server/comprehensive-server-installer.sh" /tmp/
    chmod +x /tmp/comprehensive-server-installer.sh

    # Run installation with auto-detected settings
    /tmp/comprehensive-server-installer.sh full /opt/flexpbx auto true true true true

    # Install WHMCS module if detected
    if [ -d "/var/www/html/whmcs" ] || [ -d "/usr/local/directadmin/data/users/admin/domains" ]; then
        install_control_panel_modules
    fi

    # Install desktop applications
    install_desktop_applications

    log "✓ Local installation completed successfully"
}

# Install on remote server
install_remote() {
    log "Starting remote FlexPBX installation on $REMOTE_SERVER..."

    # Test SSH connection
    if ! ssh -o ConnectTimeout=10 -o BatchMode=yes "$REMOTE_SERVER" echo "SSH connection test" &>/dev/null; then
        error "Cannot connect to remote server $REMOTE_SERVER via SSH"
    fi

    # Copy installation files to remote server
    log "Copying installation files to remote server..."
    scp -r "$SCRIPT_DIR/server" "$REMOTE_SERVER:/tmp/flexpbx-install/"

    # Run remote installation
    log "Running installation on remote server..."
    ssh "$REMOTE_SERVER" "
        cd /tmp/flexpbx-install/server
        chmod +x comprehensive-server-installer.sh
        sudo ./comprehensive-server-installer.sh full /opt/flexpbx auto true true true true
    "

    # Copy control panel modules
    log "Installing control panel modules..."
    ssh "$REMOTE_SERVER" "
        if [ -d '/var/www/html/whmcs' ]; then
            sudo mkdir -p /var/www/html/whmcs/modules/addons/
            sudo chown -R www-data:www-data /var/www/html/whmcs/modules/addons/ 2>/dev/null || sudo chown -R apache:apache /var/www/html/whmcs/modules/addons/ 2>/dev/null || true
        fi
    "

    scp -r "$SCRIPT_DIR/whmcs-module/flexpbx" "$REMOTE_SERVER:/tmp/"
    ssh "$REMOTE_SERVER" "
        if [ -d '/var/www/html/whmcs' ]; then
            sudo cp -r /tmp/flexpbx /var/www/html/whmcs/modules/addons/
            sudo chown -R www-data:www-data /var/www/html/whmcs/modules/addons/flexpbx 2>/dev/null || sudo chown -R apache:apache /var/www/html/whmcs/modules/addons/flexpbx 2>/dev/null || true
        fi
    "

    # Install desktop applications locally
    install_desktop_applications

    log "✓ Remote installation completed successfully"
    log "Server URL: http://$REMOTE_SERVER:3000"
}

# Install control panel modules
install_control_panel_modules() {
    log "Installing control panel modules..."

    # WHMCS Module
    if [ -d "/var/www/html/whmcs" ]; then
        log "Installing WHMCS module..."
        cp -r "$SCRIPT_DIR/whmcs-module/flexpbx" /var/www/html/whmcs/modules/addons/
        chown -R www-data:www-data /var/www/html/whmcs/modules/addons/flexpbx 2>/dev/null || \
        chown -R apache:apache /var/www/html/whmcs/modules/addons/flexpbx 2>/dev/null || true
        log "✓ WHMCS module installed"
    fi

    # cPanel Plugin
    if [ -d "/usr/local/cpanel" ]; then
        log "Installing cPanel plugin..."
        mkdir -p /usr/local/cpanel/3rdparty/flexpbx
        cp -r "$SCRIPT_DIR/cpanel-plugin/"* /usr/local/cpanel/3rdparty/flexpbx/
        /usr/local/cpanel/scripts/install_plugin /usr/local/cpanel/3rdparty/flexpbx
        log "✓ cPanel plugin installed"
    fi

    # DirectAdmin Plugin
    if [ -d "/usr/local/directadmin" ]; then
        log "Installing DirectAdmin plugin..."
        mkdir -p /usr/local/directadmin/plugins/flexpbx
        cp -r "$SCRIPT_DIR/directadmin-plugin/"* /usr/local/directadmin/plugins/flexpbx/
        chown -R diradmin:diradmin /usr/local/directadmin/plugins/flexpbx
        log "✓ DirectAdmin plugin installed"
    fi

    # Plesk Extension
    if [ -d "/usr/local/psa" ]; then
        log "Installing Plesk extension..."
        mkdir -p /usr/local/psa/admin/htdocs/modules/flexpbx
        cp -r "$SCRIPT_DIR/plesk-extension/"* /usr/local/psa/admin/htdocs/modules/flexpbx/
        chown -R psaadm:psaadm /usr/local/psa/admin/htdocs/modules/flexpbx
        log "✓ Plesk extension installed"
    fi
}

# Install desktop applications
install_desktop_applications() {
    log "Installing FlexPBX Desktop Applications..."

    local install_dir="/opt/flexpbx-desktop"
    mkdir -p "$install_dir"

    # Copy desktop application files
    if [ -d "$SCRIPT_DIR/desktop-apps" ]; then
        cp -r "$SCRIPT_DIR/desktop-apps/"* "$install_dir/"

        # Create desktop shortcuts for different platforms
        create_desktop_shortcuts "$install_dir"

        # Set proper permissions
        chmod +x "$install_dir/"*

        log "✓ Desktop applications installed in $install_dir"
    else
        warn "Desktop application files not found in package"
    fi
}

# Create desktop shortcuts
create_desktop_shortcuts() {
    local install_dir="$1"

    # Linux desktop shortcut
    if command -v desktop-file-install &> /dev/null; then
        cat > /tmp/flexpbx-desktop.desktop << EOF
[Desktop Entry]
Version=1.0
Type=Application
Name=FlexPBX Desktop
Comment=FlexPBX PBX Management System
Exec=$install_dir/FlexPBX-Desktop
Icon=$install_dir/assets/icon.png
Terminal=false
Categories=Network;Telephony;
EOF
        desktop-file-install /tmp/flexpbx-desktop.desktop
        log "✓ Linux desktop shortcut created"
    fi

    # macOS application bundle (if on macOS)
    if [ "$(uname)" = "Darwin" ]; then
        mkdir -p "/Applications/FlexPBX Desktop.app/Contents/MacOS"
        mkdir -p "/Applications/FlexPBX Desktop.app/Contents/Resources"

        cat > "/Applications/FlexPBX Desktop.app/Contents/Info.plist" << EOF
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>CFBundleExecutable</key>
    <string>FlexPBX Desktop</string>
    <key>CFBundleIdentifier</key>
    <string>com.flexpbx.desktop</string>
    <key>CFBundleName</key>
    <string>FlexPBX Desktop</string>
    <key>CFBundleVersion</key>
    <string>2.0.0</string>
    <key>CFBundleShortVersionString</key>
    <string>2.0.0</string>
</dict>
</plist>
EOF
        cp "$install_dir/FlexPBX-Desktop-macOS" "/Applications/FlexPBX Desktop.app/Contents/MacOS/FlexPBX Desktop"
        chmod +x "/Applications/FlexPBX Desktop.app/Contents/MacOS/FlexPBX Desktop"
        log "✓ macOS application bundle created"
    fi
}

# Configure 2FA integration
configure_2fa_integration() {
    log "Configuring 2FA integration..."

    # Create 2FA configuration template
    cat > /opt/flexpbx/config/2fa-setup.json << 'EOF'
{
    "supported_panels": [
        {
            "type": "whmcs",
            "name": "WHMCS",
            "default_port": 443,
            "auth_endpoint": "/admin/login.php",
            "api_endpoint": "/includes/api.php"
        },
        {
            "type": "cpanel",
            "name": "cPanel",
            "default_port": 2083,
            "auth_endpoint": "/login/?login_only=1",
            "api_endpoint": "/execute"
        },
        {
            "type": "whm",
            "name": "WHM",
            "default_port": 2087,
            "auth_endpoint": "/login/?login_only=1",
            "api_endpoint": "/json-api"
        },
        {
            "type": "directadmin",
            "name": "DirectAdmin",
            "default_port": 2222,
            "auth_endpoint": "/CMD_LOGIN",
            "api_endpoint": "/CMD_API"
        },
        {
            "type": "plesk",
            "name": "Plesk",
            "default_port": 8443,
            "auth_endpoint": "/login_up.php",
            "api_endpoint": "/api/v2"
        }
    ],
    "setup_instructions": {
        "whmcs": "Enable 2FA in WHMCS admin area under Setup > Staff Management > Two-Factor Authentication",
        "cpanel": "Enable 2FA in cPanel under Security > Two-Factor Authentication",
        "whm": "Enable 2FA in WHM under Home > Clusters > Remote Access Key",
        "directadmin": "Enable 2FA in DirectAdmin under Account Manager > Two-Factor Authentication",
        "plesk": "Enable 2FA in Plesk under Account > Interface Language"
    }
}
EOF

    log "✓ 2FA integration configuration created"
}

# Show completion message
show_completion() {
    echo ""
    echo -e "${GREEN}╔═══════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║              FlexPBX Installation Complete!                  ║${NC}"
    echo -e "${GREEN}╚═══════════════════════════════════════════════════════════════╝${NC}"
    echo ""

    if [ "$INSTALL_TYPE" = "local" ]; then
        echo -e "${BLUE}Local Installation Details:${NC}"
        echo -e "  🌐 Web Interface: ${YELLOW}http://localhost:3000${NC}"
        echo -e "  📱 Admin Panel: ${YELLOW}http://localhost:3000/admin${NC}"
        echo -e "  🎵 Audio Streaming: ${YELLOW}http://localhost:8000${NC}"
        echo -e "  📺 Jellyfin Media: ${YELLOW}http://localhost:8096${NC}"
    else
        echo -e "${BLUE}Remote Installation Details:${NC}"
        echo -e "  🌐 Web Interface: ${YELLOW}http://$REMOTE_SERVER:3000${NC}"
        echo -e "  📱 Admin Panel: ${YELLOW}http://$REMOTE_SERVER:3000/admin${NC}"
        echo -e "  🎵 Audio Streaming: ${YELLOW}http://$REMOTE_SERVER:8000${NC}"
        echo -e "  📺 Jellyfin Media: ${YELLOW}http://$REMOTE_SERVER:8096${NC}"
    fi

    echo ""
    echo -e "${BLUE}Control Panel Modules:${NC}"
    echo -e "  📋 WHMCS: ${YELLOW}Setup > Addon Modules > FlexPBX${NC}"
    echo -e "  🎛️  cPanel: ${YELLOW}Available in cPanel interface${NC}"
    echo -e "  ⚙️  WHM: ${YELLOW}Available in WHM interface${NC}"
    echo -e "  🔧 DirectAdmin: ${YELLOW}Available in DirectAdmin interface${NC}"
    echo -e "  🛠️  Plesk: ${YELLOW}Available in Plesk interface${NC}"

    echo ""
    echo -e "${BLUE}Desktop Applications:${NC}"
    echo -e "  💻 Installation Directory: ${YELLOW}/opt/flexpbx-desktop${NC}"
    echo -e "  🍎 macOS: ${YELLOW}Applications/FlexPBX Desktop.app${NC}"
    echo -e "  🐧 Linux: ${YELLOW}Desktop shortcut created${NC}"
    echo -e "  🪟 Windows: ${YELLOW}Run FlexPBX-Desktop.exe${NC}"

    echo ""
    echo -e "${BLUE}Next Steps:${NC}"
    echo -e "1. ${YELLOW}Access the web interface to complete initial setup${NC}"
    echo -e "2. ${YELLOW}Configure 2FA in your control panel${NC}"
    echo -e "3. ${YELLOW}Download and install the desktop application${NC}"
    echo -e "4. ${YELLOW}Connect the desktop app using the integration token${NC}"

    echo ""
    echo -e "${PURPLE}Documentation: ${YELLOW}https://docs.flexpbx.com${NC}"
    echo -e "${PURPLE}Support: ${YELLOW}https://support.flexpbx.com${NC}"
    echo ""
}

# Main installation function
main() {
    show_banner

    case "$INSTALL_TYPE" in
        "local")
            log "Selected: Local installation"
            check_requirements
            install_local
            configure_2fa_integration
            show_completion
            ;;
        "remote")
            log "Selected: Remote installation to $REMOTE_SERVER"
            check_requirements
            install_remote
            configure_2fa_integration
            show_completion
            ;;
        "help"|"-h"|"--help")
            echo "FlexPBX Complete Installation Package"
            echo ""
            echo "Usage: $0 [local|remote] [server_ip] [options...]"
            echo ""
            echo "Installation Types:"
            echo "  local          Install FlexPBX on the current machine"
            echo "  remote <ip>    Install FlexPBX on a remote server via SSH"
            echo ""
            echo "Examples:"
            echo "  $0 local                    # Install locally"
            echo "  $0 remote 192.168.1.100     # Install on remote server"
            echo ""
            exit 0
            ;;
        *)
            error "Unknown installation type: $INSTALL_TYPE. Use 'local', 'remote', or 'help'"
            ;;
    esac
}

# Run main function
main "$@"