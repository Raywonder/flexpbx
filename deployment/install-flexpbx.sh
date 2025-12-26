#!/bin/bash
#
# FlexPBX Universal Installer Script v1.2
# Downloads and installs FlexPBX automatically
# Works for any user on any system
#
# Usage:
#   wget https://flexpbx.devinecreations.net/downloads/install-flexpbx.sh
#   chmod +x install-flexpbx.sh
#   ./install-flexpbx.sh [--license-key YOUR-LICENSE-KEY]
#
# Options:
#   --license-key KEY    Optional license key for activation during install
#

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
FLEXPBX_VERSION="1.5"
DOWNLOAD_URL="https://flexpbx.devinecreations.net/downloads/FlexPBX-Master-Server-v${FLEXPBX_VERSION}.tar.gz"
CHECKSUM_URL="https://flexpbx.devinecreations.net/downloads/checksums-v${FLEXPBX_VERSION}.md5"
LICENSE_API_URL="https://flexpbx.devinecreations.net/api/license-validation.php"
LICENSE_KEY=""
LICENSE_TIMEOUT=5

# Functions
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_header() {
    echo ""
    echo "============================================"
    echo "  FlexPBX Universal Installer v${FLEXPBX_VERSION}"
    echo "============================================"
    echo ""
}

parse_arguments() {
    while [[ $# -gt 0 ]]; do
        case $1 in
            --license-key)
                LICENSE_KEY="$2"
                shift 2
                ;;
            --license-key=*)
                LICENSE_KEY="${1#*=}"
                shift
                ;;
            -h|--help)
                echo "FlexPBX Universal Installer v${FLEXPBX_VERSION}"
                echo ""
                echo "Usage: $0 [OPTIONS]"
                echo ""
                echo "Options:"
                echo "  --license-key KEY    Optional license key for activation"
                echo "  -h, --help          Show this help message"
                echo ""
                exit 0
                ;;
            *)
                log_warning "Unknown option: $1"
                shift
                ;;
        esac
    done
}

check_requirements() {
    log_info "Checking requirements..."

    # Check for required commands
    local missing=""

    for cmd in wget tar md5sum; do
        if ! command -v $cmd &> /dev/null; then
            missing="$missing $cmd"
        fi
    done

    if [ -n "$missing" ]; then
        log_error "Missing required commands:$missing"
        log_info "Please install them first. Example:"
        log_info "  yum install wget tar coreutils"
        exit 1
    fi

    log_success "All requirements met"
}

detect_environment() {
    log_info "Detecting environment..."

    # Get current user
    CURRENT_USER=$(whoami)

    # Determine if we're root
    if [ "$CURRENT_USER" = "root" ]; then
        IS_ROOT=true
        INSTALL_USER="flexpbxuser"
        INSTALL_DIR="/home/$INSTALL_USER"
    else
        IS_ROOT=false
        INSTALL_USER="$CURRENT_USER"
        INSTALL_DIR="$HOME"
    fi

    # Detect web root
    if [ -d "/home/$INSTALL_USER/public_html" ]; then
        WEB_ROOT="/home/$INSTALL_USER/public_html"
    elif [ -d "$INSTALL_DIR/public_html" ]; then
        WEB_ROOT="$INSTALL_DIR/public_html"
    elif [ -d "/var/www/html" ]; then
        WEB_ROOT="/var/www/html"
    else
        WEB_ROOT="$INSTALL_DIR/public_html"
        mkdir -p "$WEB_ROOT"
    fi

    # Create install directory if needed
    INSTALL_PATH="$INSTALL_DIR/flexpbx-install"

    log_success "Environment detected:"
    log_info "  Current User: $CURRENT_USER"
    log_info "  Install User: $INSTALL_USER"
    log_info "  Install Dir: $INSTALL_PATH"
    log_info "  Web Root: $WEB_ROOT"
}

download_package() {
    log_info "Downloading FlexPBX v${FLEXPBX_VERSION}..."

    mkdir -p "$INSTALL_PATH"
    cd "$INSTALL_PATH"

    # Download package
    if ! wget -q --show-progress "$DOWNLOAD_URL" -O "FlexPBX-Master-Server-v${FLEXPBX_VERSION}.tar.gz"; then
        log_error "Failed to download FlexPBX package"
        exit 1
    fi

    log_success "Download complete"
}

verify_checksum() {
    log_info "Verifying package integrity..."

    cd "$INSTALL_PATH"

    # Download checksum file
    if wget -q "$CHECKSUM_URL" -O checksums.md5; then
        # Extract checksum for our package
        EXPECTED_CHECKSUM=$(grep "FlexPBX-Master-Server-v${FLEXPBX_VERSION}.tar.gz" checksums.md5 | awk '{print $1}')

        if [ -n "$EXPECTED_CHECKSUM" ]; then
            # Calculate actual checksum
            ACTUAL_CHECKSUM=$(md5sum "FlexPBX-Master-Server-v${FLEXPBX_VERSION}.tar.gz" | awk '{print $1}')

            if [ "$EXPECTED_CHECKSUM" = "$ACTUAL_CHECKSUM" ]; then
                log_success "Checksum verified"
            else
                log_error "Checksum mismatch!"
                log_error "Expected: $EXPECTED_CHECKSUM"
                log_error "Got: $ACTUAL_CHECKSUM"
                exit 1
            fi
        else
            log_warning "Checksum not found in checksums file, skipping verification"
        fi
    else
        log_warning "Could not download checksums, skipping verification"
    fi
}

extract_package() {
    log_info "Extracting package..."

    cd "$INSTALL_PATH"

    if ! tar -xzf "FlexPBX-Master-Server-v${FLEXPBX_VERSION}.tar.gz"; then
        log_error "Failed to extract package"
        exit 1
    fi

    log_success "Package extracted"
}

install_files() {
    log_info "Installing files..."

    cd "$INSTALL_PATH"

    # Find extracted directory
    EXTRACT_DIR=$(find . -maxdepth 1 -type d -name "flexpbx-*" | head -1)

    if [ -z "$EXTRACT_DIR" ]; then
        log_error "Could not find extracted directory"
        exit 1
    fi

    log_info "Found extracted directory: $EXTRACT_DIR"

    # Copy files to web root
    log_info "Copying files to web root: $WEB_ROOT"

    if [ "$IS_ROOT" = true ]; then
        # Running as root - use sudo/chown
        cp -r "$EXTRACT_DIR"/* "$WEB_ROOT/"
        chown -R "$INSTALL_USER:$INSTALL_USER" "$WEB_ROOT"
        chmod -R 755 "$WEB_ROOT"
    else
        # Running as regular user
        cp -r "$EXTRACT_DIR"/* "$WEB_ROOT/"
        chmod -R 755 "$WEB_ROOT"
    fi

    log_success "Files installed"
}

setup_permissions() {
    log_info "Setting up permissions..."

    # Make scripts executable
    find "$WEB_ROOT" -name "*.sh" -exec chmod +x {} \; 2>/dev/null || true

    # Create necessary directories
    mkdir -p "$WEB_ROOT/backups" 2>/dev/null || true
    mkdir -p "$WEB_ROOT/uploads" 2>/dev/null || true
    mkdir -p "$INSTALL_DIR/logs" 2>/dev/null || true

    if [ "$IS_ROOT" = true ]; then
        chown -R "$INSTALL_USER:$INSTALL_USER" "$WEB_ROOT/backups" 2>/dev/null || true
        chown -R "$INSTALL_USER:$INSTALL_USER" "$WEB_ROOT/uploads" 2>/dev/null || true
        chown -R "$INSTALL_USER:$INSTALL_USER" "$INSTALL_DIR/logs" 2>/dev/null || true
    fi

    log_success "Permissions configured"
}

validate_license() {
    if [ -z "$LICENSE_KEY" ]; then
        log_info "No license key provided - installation will continue"
        log_info "You can activate your license later via the Admin UI"
        return 0
    fi

    log_info "Validating license key..."

    # Check if curl is available
    if ! command -v curl &> /dev/null; then
        log_warning "curl not available - license validation skipped"
        log_warning "License will be activated during web installer"
        return 0
    fi

    # Attempt license validation with timeout
    local validation_response
    validation_response=$(curl -s --max-time $LICENSE_TIMEOUT -X POST "$LICENSE_API_URL" \
        -d "action=validate" \
        -d "license_key=$LICENSE_KEY" \
        -d "domain=$DOMAIN" \
        -d "ip=$SERVER_IP" 2>/dev/null || echo "")

    if [ -z "$validation_response" ]; then
        log_warning "License server unreachable - installation will continue"
        log_warning "License will be validated automatically when server is available"
        return 0
    fi

    # Parse response (basic check for success)
    if echo "$validation_response" | grep -q '"success":true'; then
        log_success "License validated successfully!"
    elif echo "$validation_response" | grep -q '"status":"unreachable"'; then
        log_warning "License server unreachable - will retry later"
    else
        log_warning "License validation failed - installation will continue"
        log_warning "You can activate your license via the Admin UI"
    fi

    # Log validation attempt
    echo "$validation_response" > "$INSTALL_PATH/license_validation.log" 2>/dev/null || true

    return 0
}

detect_web_server() {
    log_info "Detecting web server..."

    # Detect domain
    if [ -d "/etc/nginx/conf.d" ]; then
        DOMAIN=$(grep -r "server_name" /etc/nginx/conf.d/ 2>/dev/null | grep -v "#" | head -1 | awk '{print $2}' | tr -d ';' || echo "localhost")
    elif [ -d "/etc/httpd/conf.d" ]; then
        DOMAIN=$(grep -r "ServerName" /etc/httpd/conf.d/ 2>/dev/null | grep -v "#" | head -1 | awk '{print $2}' || echo "localhost")
    else
        DOMAIN="localhost"
    fi

    # Get server IP
    SERVER_IP=$(hostname -I | awk '{print $1}' || echo "127.0.0.1")

    log_success "Web server detected"
    log_info "  Domain: $DOMAIN"
    log_info "  IP: $SERVER_IP"
}

display_completion() {
    echo ""
    echo "============================================"
    echo "  ✅ FlexPBX Installation Complete!"
    echo "============================================"
    echo ""
    log_success "Next Steps:"
    echo ""
    echo "1. Open your web browser and navigate to:"
    echo "   https://$DOMAIN/install.php"
    echo "   OR"
    echo "   http://$SERVER_IP/install.php"
    echo ""
    echo "2. Follow the installation wizard:"
    echo "   - Enter database credentials"
    echo "   - Set admin password"
    echo "   - Configure Asterisk settings"
    echo ""
    echo "3. After installation, access FlexPBX:"
    echo "   https://$DOMAIN/admin/"
    echo ""
    echo "Documentation:"
    echo "  - Installation Guide: $WEB_ROOT/docs/INSTALLATION.md"
    echo "  - Quick Start: $WEB_ROOT/docs/QUICK_START.md"
    echo ""
    echo "Support:"
    echo "  - Website: https://flexpbx.devinecreations.net"
    echo "  - Email: support@devinecreations.net"
    echo ""

    if [ -n "$LICENSE_KEY" ]; then
        echo "License:"
        echo "  - License key provided during installation"
        echo "  - Complete activation in Admin UI if needed"
        echo ""
    else
        echo "License:"
        echo "  - No license key provided"
        echo "  - Activate via Admin UI: https://$DOMAIN/admin/license-activation.php"
        echo "  - Purchase: https://devine-creations.com"
        echo ""
    fi

    log_info "Installation files located at: $INSTALL_PATH"
    log_info "Web root: $WEB_ROOT"
    echo ""
    echo "============================================"
}

cleanup() {
    log_info "Cleaning up..."

    # Keep the installation directory for reference
    # Users can manually remove it later if desired

    log_success "Cleanup complete"
}

# Main execution
main() {
    print_header
    parse_arguments "$@"
    check_requirements
    detect_environment
    download_package
    verify_checksum
    extract_package
    install_files
    setup_permissions
    detect_web_server
    validate_license
    cleanup
    display_completion
}

# Run main function
main "$@"

exit 0
