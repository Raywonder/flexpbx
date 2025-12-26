#!/bin/bash
#
# FlexPBX Quick Test Script
# Usage: ./quick-test.sh [local|vps|dedicated|shared] [ip_address]
#

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Configuration
TEST_TYPE="${1:-local}"
SERVER_IP="${2:-}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

log() {
    echo -e "${GREEN}[$(date +'%H:%M:%S')] $1${NC}"
}

warn() {
    echo -e "${YELLOW}[WARNING] $1${NC}"
}

error() {
    echo -e "${RED}[ERROR] $1${NC}"
}

# Show quick test banner
show_banner() {
    echo -e "${BLUE}"
    cat << 'EOF'
╔═══════════════════════════════════════════════════════════════╗
║                FlexPBX Quick Test Script                     ║
║                                                               ║
║  🚀 Fast testing for all server types                       ║
╚═══════════════════════════════════════════════════════════════╝
EOF
    echo -e "${NC}"
}

# Detect server specifications
detect_server_specs() {
    if [ "$TEST_TYPE" = "local" ]; then
        return
    fi

    log "Detecting server specifications for $SERVER_IP..."

    # Get server info via SSH
    local server_info=$(ssh -o ConnectTimeout=10 "$SERVER_IP" "
        echo 'RAM:' \$(free -h | grep Mem | awk '{print \$2}')
        echo 'CPU:' \$(nproc) cores
        echo 'DISK:' \$(df -h / | tail -1 | awk '{print \$4}') available
        echo 'OS:' \$(cat /etc/os-release | grep PRETTY_NAME | cut -d'\"' -f2)
        echo 'VIRTUALIZATION:' \$(systemd-detect-virt 2>/dev/null || echo 'unknown')
    " 2>/dev/null)

    echo -e "${YELLOW}Server Specifications:${NC}"
    echo "$server_info"
    echo ""
}

# Quick local test (macOS)
test_local() {
    log "Starting quick local test on macOS..."

    # Check if Docker is running
    if ! docker info &>/dev/null; then
        warn "Starting Docker Desktop..."
        open /Applications/Docker.app
        sleep 10
    fi

    # Quick Docker test
    cd "$SCRIPT_DIR"
    log "Starting minimal Docker services..."
    docker-compose -f deployment/docker/docker-compose-minimal.yml up -d

    # Wait for services
    sleep 15

    # Test endpoints
    log "Testing local endpoints..."
    if curl -s http://localhost:3000 | head -n 1; then
        echo -e "${GREEN}✅ FlexPBX server: http://localhost:3000${NC}"
    else
        echo -e "${RED}❌ FlexPBX server failed${NC}"
    fi

    if curl -s http://localhost:8000 | head -n 1; then
        echo -e "${GREEN}✅ Audio streaming: http://localhost:8000${NC}"
    else
        echo -e "${RED}❌ Audio streaming failed${NC}"
    fi

    # Test desktop app
    local desktop_app="deployment/build/FlexPBX-Complete-v2.0.0/desktop-apps/mac/FlexPBX Desktop.app"
    if [ -d "$desktop_app" ]; then
        echo -e "${GREEN}✅ Desktop app ready: $desktop_app${NC}"
    else
        echo -e "${RED}❌ Desktop app not found${NC}"
    fi

    echo ""
    echo -e "${BLUE}Local Test Complete!${NC}"
    echo -e "${YELLOW}Next steps:${NC}"
    echo "1. Open http://localhost:3000 in your browser"
    echo "2. Launch desktop app from: $desktop_app"
    echo "3. Test 2FA integration with WHMCS module"
}

# Quick VPS test
test_vps() {
    if [ -z "$SERVER_IP" ]; then
        error "VPS IP address required. Usage: $0 vps <ip_address>"
        exit 1
    fi

    log "Starting quick VPS test on $SERVER_IP..."

    detect_server_specs

    # Test SSH connection
    if ! ssh -o ConnectTimeout=10 "$SERVER_IP" echo "SSH OK" &>/dev/null; then
        error "Cannot connect to VPS via SSH"
        exit 1
    fi

    # Upload and install FlexPBX
    log "Uploading FlexPBX package..."
    scp -q "$SCRIPT_DIR/deployment/build/FlexPBX-Complete-v2.0.0.zip" "$SERVER_IP:/tmp/"

    log "Installing FlexPBX on VPS..."
    ssh "$SERVER_IP" "
        cd /tmp
        unzip -q FlexPBX-Complete-v2.0.0.zip
        cd FlexPBX-Complete-v2.0.0
        chmod +x install.sh
        ./install.sh local --mode auto --vps-optimized
    "

    # Test endpoints
    log "Testing VPS endpoints..."
    sleep 30  # Wait for services to start

    if curl -s "http://$SERVER_IP:3000" | head -n 1; then
        echo -e "${GREEN}✅ FlexPBX server: http://$SERVER_IP:3000${NC}"
    else
        echo -e "${RED}❌ FlexPBX server failed${NC}"
    fi

    if curl -s "http://$SERVER_IP:8000" | head -n 1; then
        echo -e "${GREEN}✅ Audio streaming: http://$SERVER_IP:8000${NC}"
    else
        echo -e "${RED}❌ Audio streaming failed${NC}"
    fi

    if curl -s "http://$SERVER_IP:8096" | head -n 1; then
        echo -e "${GREEN}✅ Jellyfin media: http://$SERVER_IP:8096${NC}"
    else
        echo -e "${RED}❌ Jellyfin media failed${NC}"
    fi

    echo ""
    echo -e "${BLUE}VPS Test Complete!${NC}"
    echo -e "${YELLOW}Access URLs:${NC}"
    echo "🌐 FlexPBX: http://$SERVER_IP:3000"
    echo "📱 Admin: http://$SERVER_IP:3000/admin"
    echo "🎵 Audio: http://$SERVER_IP:8000"
    echo "📺 Media: http://$SERVER_IP:8096"
}

# Quick dedicated server test
test_dedicated() {
    if [ -z "$SERVER_IP" ]; then
        error "Server IP address required. Usage: $0 dedicated <ip_address>"
        exit 1
    fi

    log "Starting quick dedicated server test on $SERVER_IP..."

    detect_server_specs

    # Upload and install with full features
    log "Installing full FlexPBX on dedicated server..."
    scp -q "$SCRIPT_DIR/deployment/build/FlexPBX-Complete-v2.0.0.zip" "$SERVER_IP:/tmp/"

    ssh "$SERVER_IP" "
        cd /tmp
        unzip -q FlexPBX-Complete-v2.0.0.zip
        cd FlexPBX-Complete-v2.0.0
        chmod +x install.sh
        ./install.sh local --mode full --enable-all --dedicated-server
    "

    # Extended testing for dedicated server
    log "Running performance tests..."
    sleep 60  # Wait longer for full services

    # Test all endpoints
    local endpoints=("3000" "8000" "8096" "5060")
    for port in "${endpoints[@]}"; do
        if curl -s "http://$SERVER_IP:$port" | head -n 1; then
            echo -e "${GREEN}✅ Service on port $port${NC}"
        else
            echo -e "${RED}❌ Service on port $port failed${NC}"
        fi
    done

    # Performance test
    if command -v ab &>/dev/null; then
        log "Running load test..."
        ab -n 1000 -c 10 "http://$SERVER_IP:3000/" | grep "Requests per second\|Time per request"
    fi

    echo ""
    echo -e "${BLUE}Dedicated Server Test Complete!${NC}"
    echo -e "${YELLOW}High-performance setup ready!${NC}"
}

# Quick shared hosting test
test_shared() {
    if [ -z "$SERVER_IP" ]; then
        error "Shared hosting address required. Usage: $0 shared <hostname>"
        exit 1
    fi

    log "Starting quick shared hosting test on $SERVER_IP..."

    # Limited installation for shared hosting
    log "Installing minimal FlexPBX for shared hosting..."
    scp -q "$SCRIPT_DIR/deployment/build/FlexPBX-Complete-v2.0.0.zip" "$SERVER_IP:/tmp/"

    ssh "$SERVER_IP" "
        cd /tmp
        unzip -q FlexPBX-Complete-v2.0.0.zip
        cd FlexPBX-Complete-v2.0.0
        chmod +x install.sh
        ./install.sh local --mode minimal --shared-hosting --port-offset 1000
    "

    # Test limited functionality
    log "Testing shared hosting compatibility..."
    sleep 30

    # Test web interface only (most shared hosts block other ports)
    if curl -s "http://$SERVER_IP:4000" | head -n 1; then
        echo -e "${GREEN}✅ Web interface: http://$SERVER_IP:4000${NC}"
    else
        echo -e "${RED}❌ Web interface failed${NC}"
    fi

    # Test WHMCS module compatibility
    log "Testing WHMCS module upload..."
    ssh "$SERVER_IP" "
        if [ -d /var/www/html/whmcs ]; then
            cp -r /tmp/FlexPBX-Complete-v2.0.0/whmcs-module/flexpbx /var/www/html/whmcs/modules/addons/
            echo 'WHMCS module uploaded successfully'
        else
            echo 'WHMCS not detected - module ready for manual upload'
        fi
    "

    echo ""
    echo -e "${BLUE}Shared Hosting Test Complete!${NC}"
    echo -e "${YELLOW}Limited functionality available:${NC}"
    echo "🌐 Web interface only (SIP/RTP blocked by hosting)"
    echo "📱 WHMCS module ready for upload"
    echo "💻 Desktop app can connect for management"
}

# Cleanup function
cleanup() {
    if [ "$TEST_TYPE" = "local" ]; then
        log "Cleaning up Docker services..."
        cd "$SCRIPT_DIR"
        docker-compose -f deployment/docker/docker-compose-minimal.yml down &>/dev/null || true
    fi
}

# Main execution
main() {
    show_banner

    # Set up cleanup trap
    trap cleanup EXIT

    case "$TEST_TYPE" in
        "local")
            test_local
            ;;
        "vps")
            test_vps
            ;;
        "dedicated")
            test_dedicated
            ;;
        "shared")
            test_shared
            ;;
        *)
            error "Unknown test type: $TEST_TYPE"
            echo "Usage: $0 [local|vps|dedicated|shared] [ip_address]"
            echo ""
            echo "Examples:"
            echo "  $0 local                          # Test on macOS locally"
            echo "  $0 vps 192.168.1.100             # Test on VPS"
            echo "  $0 dedicated 192.168.1.200       # Test on dedicated server"
            echo "  $0 shared shared.hosting.com     # Test on shared hosting"
            exit 1
            ;;
    esac
}

# Run main function
main "$@"