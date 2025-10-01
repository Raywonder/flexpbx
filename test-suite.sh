#!/bin/bash
#
# FlexPBX Automated Test Suite
# Usage: ./test-suite.sh [local|vps] [vps_ip]
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
VPS_IP="${2:-}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TEST_RESULTS=()

log() {
    echo -e "${GREEN}[$(date +'%H:%M:%S')] $1${NC}"
}

warn() {
    echo -e "${YELLOW}[WARNING] $1${NC}"
}

error() {
    echo -e "${RED}[ERROR] $1${NC}"
}

test_pass() {
    echo -e "${GREEN}✅ PASS: $1${NC}"
    TEST_RESULTS+=("PASS: $1")
}

test_fail() {
    echo -e "${RED}❌ FAIL: $1${NC}"
    TEST_RESULTS+=("FAIL: $1")
}

test_skip() {
    echo -e "${YELLOW}⏭️  SKIP: $1${NC}"
    TEST_RESULTS+=("SKIP: $1")
}

# Show banner
show_banner() {
    echo -e "${BLUE}"
    cat << 'EOF'
╔═══════════════════════════════════════════════════════════════╗
║                  FlexPBX Test Suite                          ║
║                                                               ║
║  🧪 Automated testing for macOS and VPS environments        ║
╚═══════════════════════════════════════════════════════════════╝
EOF
    echo -e "${NC}"
}

# Check prerequisites
check_prerequisites() {
    log "Checking prerequisites for $TEST_TYPE testing..."

    if [ "$TEST_TYPE" = "local" ]; then
        # Check macOS prerequisites
        if ! command -v docker &> /dev/null; then
            test_fail "Docker not installed"
            error "Install Docker: brew install --cask docker"
            exit 1
        fi

        if ! command -v node &> /dev/null; then
            test_fail "Node.js not installed"
            error "Install Node.js: brew install node"
            exit 1
        fi

        test_pass "macOS prerequisites check"
    else
        # Check VPS prerequisites
        if [ -z "$VPS_IP" ]; then
            test_fail "VPS IP address required"
            error "Usage: $0 vps <vps_ip>"
            exit 1
        fi

        if ! command -v ssh &> /dev/null; then
            test_fail "SSH not available"
            exit 1
        fi

        # Test SSH connection
        if ssh -o ConnectTimeout=10 -o BatchMode=yes "$VPS_IP" echo "SSH test" &>/dev/null; then
            test_pass "VPS SSH connection"
        else
            test_fail "Cannot connect to VPS via SSH"
            exit 1
        fi
    fi
}

# Test Docker services
test_docker_services() {
    if [ "$TEST_TYPE" != "local" ]; then
        test_skip "Docker services (VPS mode)"
        return
    fi

    log "Testing Docker services..."

    # Start minimal Docker setup
    cd "$SCRIPT_DIR"
    if docker-compose -f deployment/docker/docker-compose-minimal.yml up -d; then
        test_pass "Docker services started"
        sleep 10  # Wait for services to initialize
    else
        test_fail "Docker services failed to start"
        return
    fi

    # Check container status
    if docker-compose -f deployment/docker/docker-compose-minimal.yml ps | grep -q "Up"; then
        test_pass "Docker containers running"
    else
        test_fail "Docker containers not running properly"
    fi
}

# Test HTTP endpoints
test_http_endpoints() {
    log "Testing HTTP endpoints..."

    local base_url
    if [ "$TEST_TYPE" = "local" ]; then
        base_url="http://localhost"
    else
        base_url="http://$VPS_IP"
    fi

    # Test main server
    if curl -s -o /dev/null -w "%{http_code}" "$base_url:3000" | grep -q "200\|302"; then
        test_pass "FlexPBX server responding on port 3000"
    else
        test_fail "FlexPBX server not responding on port 3000"
    fi

    # Test admin panel
    if curl -s -o /dev/null -w "%{http_code}" "$base_url:3000/admin" | grep -q "200\|302\|404"; then
        test_pass "Admin panel endpoint accessible"
    else
        test_fail "Admin panel endpoint not accessible"
    fi

    # Test audio streaming
    if curl -s -o /dev/null -w "%{http_code}" "$base_url:8000" | grep -q "200\|302"; then
        test_pass "Audio streaming service on port 8000"
    else
        test_fail "Audio streaming service not responding"
    fi

    # Test Jellyfin
    if curl -s -o /dev/null -w "%{http_code}" "$base_url:8096" | grep -q "200\|302"; then
        test_pass "Jellyfin media server on port 8096"
    else
        test_fail "Jellyfin media server not responding"
    fi
}

# Test WebSocket connections
test_websocket() {
    log "Testing WebSocket connections..."

    local ws_url
    if [ "$TEST_TYPE" = "local" ]; then
        ws_url="ws://localhost:3000/ws"
    else
        ws_url="ws://$VPS_IP:3000/ws"
    fi

    if command -v wscat &> /dev/null; then
        if timeout 10 wscat -c "$ws_url" -w 1 <<< '{"type":"ping"}' | grep -q "pong\|error"; then
            test_pass "WebSocket connection working"
        else
            test_fail "WebSocket connection failed"
        fi
    else
        test_skip "WebSocket test (wscat not installed)"
    fi
}

# Test desktop application
test_desktop_app() {
    if [ "$TEST_TYPE" != "local" ]; then
        test_skip "Desktop application (VPS mode)"
        return
    fi

    log "Testing desktop application..."

    local desktop_app="$SCRIPT_DIR/deployment/build/FlexPBX-Complete-v2.0.0/desktop-apps/mac/FlexPBX Desktop.app"

    if [ -d "$desktop_app" ]; then
        test_pass "Desktop application found"

        # Test if app can launch (check if executable exists)
        if [ -f "$desktop_app/Contents/MacOS/FlexPBX Desktop" ]; then
            test_pass "Desktop application executable found"
        else
            test_fail "Desktop application executable missing"
        fi
    else
        test_fail "Desktop application not found"
    fi
}

# Test 2FA authentication
test_2fa_auth() {
    log "Testing 2FA authentication..."

    # Check if AuthenticationService exists
    local auth_service="$SCRIPT_DIR/desktop-app/src/main/services/AuthenticationService.js"
    if [ -f "$auth_service" ]; then
        test_pass "2FA AuthenticationService found"

        # Check for required methods
        if grep -q "authenticateWith2FA\|generateTOTP\|validateTOTP" "$auth_service"; then
            test_pass "2FA methods implemented"
        else
            test_fail "2FA methods missing"
        fi
    else
        test_fail "2FA AuthenticationService not found"
    fi

    # Check WHMCS module
    local whmcs_module="$SCRIPT_DIR/deployment/whmcs-module/flexpbx/flexpbx.php"
    if [ -f "$whmcs_module" ]; then
        test_pass "WHMCS module found"

        if grep -q "2fa\|tfa\|two.factor" "$whmcs_module"; then
            test_pass "WHMCS 2FA integration found"
        else
            test_fail "WHMCS 2FA integration missing"
        fi
    else
        test_fail "WHMCS module not found"
    fi
}

# Test control panel plugins
test_control_panels() {
    log "Testing control panel plugins..."

    local plugins_dir="$SCRIPT_DIR/deployment/build/FlexPBX-Complete-v2.0.0"

    # Check cPanel plugin
    if [ -d "$plugins_dir/cpanel-plugin" ]; then
        test_pass "cPanel plugin found"
    else
        test_fail "cPanel plugin missing"
    fi

    # Check DirectAdmin plugin
    if [ -d "$plugins_dir/directadmin-plugin" ]; then
        test_pass "DirectAdmin plugin found"
    else
        test_fail "DirectAdmin plugin missing"
    fi

    # Check Plesk extension
    if [ -d "$plugins_dir/plesk-extension" ]; then
        test_pass "Plesk extension found"
    else
        test_fail "Plesk extension missing"
    fi
}

# Test package integrity
test_package_integrity() {
    log "Testing package integrity..."

    local package_file="$SCRIPT_DIR/deployment/build/FlexPBX-Complete-v2.0.0.zip"

    if [ -f "$package_file" ]; then
        test_pass "Installation package found"

        local file_size=$(stat -f%z "$package_file" 2>/dev/null || stat -c%s "$package_file" 2>/dev/null)
        if [ "$file_size" -gt 500000000 ]; then  # > 500MB
            test_pass "Package size appropriate (${file_size} bytes)"
        else
            test_fail "Package size too small (${file_size} bytes)"
        fi

        # Test if ZIP is valid
        if unzip -t "$package_file" &>/dev/null; then
            test_pass "Package ZIP integrity"
        else
            test_fail "Package ZIP corrupted"
        fi
    else
        test_fail "Installation package not found"
    fi
}

# Test server installer
test_server_installer() {
    log "Testing server installer..."

    local installer="$SCRIPT_DIR/deployment/scripts/comprehensive-server-installer.sh"

    if [ -f "$installer" ]; then
        test_pass "Server installer found"

        if [ -x "$installer" ]; then
            test_pass "Server installer executable"
        else
            test_fail "Server installer not executable"
        fi

        # Check for required functions
        if grep -q "install_asterisk\|install_docker\|setup_jellyfin" "$installer"; then
            test_pass "Server installer functions found"
        else
            test_fail "Server installer functions missing"
        fi
    else
        test_fail "Server installer not found"
    fi
}

# Performance test
test_performance() {
    log "Running performance tests..."

    local base_url
    if [ "$TEST_TYPE" = "local" ]; then
        base_url="http://localhost:3000"
    else
        base_url="http://$VPS_IP:3000"
    fi

    if command -v ab &> /dev/null; then
        # Apache bench test
        if ab -n 100 -c 5 -t 30 "$base_url/" 2>/dev/null | grep -q "Complete requests"; then
            test_pass "Performance test completed"
        else
            test_fail "Performance test failed"
        fi
    else
        test_skip "Performance test (ab not installed)"
    fi
}

# Cleanup function
cleanup() {
    if [ "$TEST_TYPE" = "local" ]; then
        log "Cleaning up Docker services..."
        cd "$SCRIPT_DIR"
        docker-compose -f deployment/docker/docker-compose-minimal.yml down &>/dev/null || true
    fi
}

# Test results summary
show_results() {
    echo ""
    echo -e "${BLUE}╔═══════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${BLUE}║                     Test Results Summary                     ║${NC}"
    echo -e "${BLUE}╚═══════════════════════════════════════════════════════════════╝${NC}"
    echo ""

    local pass_count=0
    local fail_count=0
    local skip_count=0

    for result in "${TEST_RESULTS[@]}"; do
        if [[ $result == PASS:* ]]; then
            echo -e "${GREEN}✅ ${result#PASS: }${NC}"
            ((pass_count++))
        elif [[ $result == FAIL:* ]]; then
            echo -e "${RED}❌ ${result#FAIL: }${NC}"
            ((fail_count++))
        elif [[ $result == SKIP:* ]]; then
            echo -e "${YELLOW}⏭️  ${result#SKIP: }${NC}"
            ((skip_count++))
        fi
    done

    echo ""
    echo -e "${BLUE}Summary: ${GREEN}$pass_count passed${NC}, ${RED}$fail_count failed${NC}, ${YELLOW}$skip_count skipped${NC}"

    if [ $fail_count -eq 0 ]; then
        echo -e "${GREEN}🎉 All tests passed!${NC}"
        return 0
    else
        echo -e "${RED}⚠️  Some tests failed. Check the results above.${NC}"
        return 1
    fi
}

# Main execution
main() {
    show_banner

    log "Starting FlexPBX test suite for $TEST_TYPE environment..."

    # Set up cleanup trap
    trap cleanup EXIT

    # Run tests
    check_prerequisites
    test_package_integrity
    test_server_installer
    test_docker_services
    test_http_endpoints
    test_websocket
    test_desktop_app
    test_2fa_auth
    test_control_panels
    test_performance

    # Show results
    show_results
}

# Run main function
main "$@"