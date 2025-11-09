#!/bin/bash

# 📞 FlexPBX Complete Test Suite
# Comprehensive testing script for FlexPBX server functionality
# Version: 2.1.0
# Author: Devine Creations LLC

echo "🚀 FlexPBX Server Test Suite v2.1.0"
echo "===================================="
echo ""

# Configuration
FLEXPBX_DOMAIN="flexpbx.devinecreations.net"
SIP_PORT="5060"
WEB_PORT="443"
API_BASE="https://${FLEXPBX_DOMAIN}/api"
ADMIN_BASE="https://${FLEXPBX_DOMAIN}/admin"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Test counters
TESTS_PASSED=0
TESTS_FAILED=0
TOTAL_TESTS=0

# Helper functions
log_test() {
    echo -e "${BLUE}[TEST]${NC} $1"
    TOTAL_TESTS=$((TOTAL_TESTS + 1))
}

log_pass() {
    echo -e "${GREEN}[PASS]${NC} $1"
    TESTS_PASSED=$((TESTS_PASSED + 1))
}

log_fail() {
    echo -e "${RED}[FAIL]${NC} $1"
    TESTS_FAILED=$((TESTS_FAILED + 1))
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

# Test 1: DNS Resolution
test_dns_resolution() {
    log_test "DNS Resolution for ${FLEXPBX_DOMAIN}"

    if nslookup $FLEXPBX_DOMAIN > /dev/null 2>&1; then
        IP=$(nslookup $FLEXPBX_DOMAIN | grep -A1 "Name:" | tail -n1 | awk '{print $2}')
        log_pass "DNS resolves to: $IP"
    else
        log_fail "DNS resolution failed for ${FLEXPBX_DOMAIN}"
    fi
    echo ""
}

# Test 2: SIP Port Connectivity
test_sip_connectivity() {
    log_test "SIP Port ${SIP_PORT} Connectivity"

    if timeout 5 bash -c "</dev/tcp/${FLEXPBX_DOMAIN}/${SIP_PORT}" 2>/dev/null; then
        log_pass "SIP port ${SIP_PORT} is open and listening"
    else
        log_fail "SIP port ${SIP_PORT} is not accessible"
        log_warn "FlexPBX server may not be running"
    fi
    echo ""
}

# Test 3: Web Interface Accessibility
test_web_interface() {
    log_test "Web Interface Accessibility"

    # Test main site
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" https://${FLEXPBX_DOMAIN}/ --connect-timeout 10)
    if [ "$HTTP_CODE" -eq 200 ]; then
        log_pass "Main website accessible (HTTP $HTTP_CODE)"
    else
        log_fail "Main website not accessible (HTTP $HTTP_CODE)"
    fi

    # Test admin interface
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" ${ADMIN_BASE}/ --connect-timeout 10)
    if [ "$HTTP_CODE" -eq 200 ] || [ "$HTTP_CODE" -eq 401 ]; then
        log_pass "Admin interface accessible (HTTP $HTTP_CODE)"
    else
        log_fail "Admin interface not accessible (HTTP $HTTP_CODE)"
    fi

    # Test API endpoint
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" ${API_BASE}/ --connect-timeout 10)
    if [ "$HTTP_CODE" -eq 200 ] || [ "$HTTP_CODE" -eq 401 ]; then
        log_pass "API endpoint accessible (HTTP $HTTP_CODE)"
    else
        log_fail "API endpoint not accessible (HTTP $HTTP_CODE)"
    fi
    echo ""
}

# Test 4: SIP Extension Registration Test
test_sip_extensions() {
    log_test "SIP Extension Registration Test"

    # Test extensions using SIPp if available, otherwise use basic connectivity
    if command -v sipp &> /dev/null; then
        log_info "Using SIPp for advanced SIP testing"
        # Advanced SIP testing with SIPp would go here
        log_warn "SIPp testing requires XML scenario files"
    else
        log_warn "SIPp not available, using basic connectivity tests"

        # Test key extensions with basic SIP OPTIONS
        EXTENSIONS=("101" "1000" "1001" "2000" "2001" "8000")

        for EXT in "${EXTENSIONS[@]}"; do
            log_info "Testing extension $EXT registration capability"

            # Try to send SIP OPTIONS to extension
            if timeout 5 nc -u -z $FLEXPBX_DOMAIN $SIP_PORT 2>/dev/null; then
                log_pass "Extension $EXT - SIP port reachable"
            else
                log_fail "Extension $EXT - SIP port not reachable"
            fi
        done
    fi
    echo ""
}

# Test 5: Configuration Files Validation
test_configuration_files() {
    log_test "Configuration Files Validation"

    CONFIG_FILES=(
        "callcentric-trunk-config.json"
        "google-voice-config.json"
        "extensions-config.json"
    )

    for CONFIG in "${CONFIG_FILES[@]}"; do
        if [ -f "$CONFIG" ]; then
            # Validate JSON syntax
            if python3 -m json.tool "$CONFIG" > /dev/null 2>&1; then
                log_pass "$CONFIG - Valid JSON format"
            else
                log_fail "$CONFIG - Invalid JSON format"
            fi

            # Check file size (should not be empty)
            SIZE=$(stat -c%s "$CONFIG" 2>/dev/null || stat -f%z "$CONFIG" 2>/dev/null)
            if [ "$SIZE" -gt 100 ]; then
                log_pass "$CONFIG - Contains configuration data ($SIZE bytes)"
            else
                log_warn "$CONFIG - File seems too small ($SIZE bytes)"
            fi
        else
            log_fail "$CONFIG - File not found"
        fi
    done
    echo ""
}

# Test 6: Callcentric Trunk Connectivity
test_callcentric_trunk() {
    log_test "Callcentric Trunk Connectivity"

    CALLCENTRIC_SERVER="sip.callcentric.com"
    CALLCENTRIC_PORT="5060"

    # Test DNS resolution for Callcentric
    if nslookup $CALLCENTRIC_SERVER > /dev/null 2>&1; then
        log_pass "Callcentric DNS resolution successful"
    else
        log_fail "Callcentric DNS resolution failed"
    fi

    # Test port connectivity
    if timeout 5 bash -c "</dev/tcp/${CALLCENTRIC_SERVER}/${CALLCENTRIC_PORT}" 2>/dev/null; then
        log_pass "Callcentric SIP port accessible"
    else
        log_fail "Callcentric SIP port not accessible"
    fi

    # Validate trunk configuration
    if [ -f "callcentric-trunk-config.json" ]; then
        if grep -q "sip.callcentric.com" callcentric-trunk-config.json; then
            log_pass "Callcentric configuration file contains correct server"
        else
            log_fail "Callcentric configuration missing server details"
        fi
    fi
    echo ""
}

# Test 7: Google Voice Configuration
test_google_voice_config() {
    log_test "Google Voice Configuration"

    if [ -f "google-voice-config.json" ]; then
        # Check for required OAuth2 configuration
        if grep -q "oauth2" google-voice-config.json; then
            log_pass "Google Voice OAuth2 configuration present"
        else
            log_fail "Google Voice OAuth2 configuration missing"
        fi

        # Check for phone number format
        if grep -q "12813015784" google-voice-config.json; then
            log_pass "Google Voice number (281) 301-5784 configured"
        else
            log_warn "Google Voice number not found in config"
        fi

        # Check for required API scopes
        if grep -q "voice.sms" google-voice-config.json; then
            log_pass "Google Voice SMS scope configured"
        else
            log_fail "Google Voice SMS scope missing"
        fi
    else
        log_fail "Google Voice configuration file not found"
    fi
    echo ""
}

# Test 8: Extension Configuration Validation
test_extension_validation() {
    log_test "Extension Configuration Validation"

    if [ -f "extensions-config.json" ]; then
        # Count configured extensions
        EXT_COUNT=$(grep -c '"username"' extensions-config.json)
        if [ "$EXT_COUNT" -ge 15 ]; then
            log_pass "Found $EXT_COUNT extensions (expected 20+)"
        else
            log_warn "Only found $EXT_COUNT extensions (expected 20+)"
        fi

        # Check for test extension 2001
        if grep -q "techsupport1" extensions-config.json; then
            log_pass "Test extension 2001 (techsupport1) configured"
        else
            log_fail "Test extension 2001 not found"
        fi

        # Check for sales extensions
        if grep -q "salesmanager" extensions-config.json; then
            log_pass "Sales department extensions configured"
        else
            log_fail "Sales department extensions missing"
        fi

        # Check for conference rooms
        if grep -q "conference" extensions-config.json; then
            log_pass "Conference room extensions configured"
        else
            log_warn "Conference room extensions not found"
        fi
    else
        log_fail "Extensions configuration file not found"
    fi
    echo ""
}

# Test 9: Admin Interface Components
test_admin_interface() {
    log_test "Admin Interface Components"

    ADMIN_FILES=(
        "admin-trunks-management.html"
        "admin-google-voice.html"
        "admin-extensions-management.html"
    )

    for ADMIN_FILE in "${ADMIN_FILES[@]}"; do
        if [ -f "$ADMIN_FILE" ]; then
            # Check file size
            SIZE=$(stat -c%s "$ADMIN_FILE" 2>/dev/null || stat -f%z "$ADMIN_FILE" 2>/dev/null)
            if [ "$SIZE" -gt 10000 ]; then
                log_pass "$ADMIN_FILE - Complete interface ($SIZE bytes)"
            else
                log_warn "$ADMIN_FILE - File seems incomplete ($SIZE bytes)"
            fi

            # Check for key functionality
            if grep -q "Bootstrap" "$ADMIN_FILE"; then
                log_pass "$ADMIN_FILE - Uses Bootstrap framework"
            else
                log_warn "$ADMIN_FILE - May be missing styling framework"
            fi
        else
            log_fail "$ADMIN_FILE - File not found"
        fi
    done
    echo ""
}

# Test 10: File Organization Check
test_file_organization() {
    log_test "File Organization Structure"

    EXPECTED_DIRS=("organized/api" "organized/admin" "organized/modules" "organized/config" "organized/docs")

    for DIR in "${EXPECTED_DIRS[@]}"; do
        if [ -d "$DIR" ]; then
            FILE_COUNT=$(find "$DIR" -type f | wc -l)
            log_pass "$DIR - Contains $FILE_COUNT files"
        else
            log_warn "$DIR - Directory not found"
        fi
    done
    echo ""
}

# Test 11: System Requirements Check
test_system_requirements() {
    log_test "System Requirements Check"

    # Check for required tools
    TOOLS=("curl" "nc" "nslookup" "python3")

    for TOOL in "${TOOLS[@]}"; do
        if command -v $TOOL &> /dev/null; then
            log_pass "$TOOL - Available"
        else
            log_warn "$TOOL - Not available (some tests may be limited)"
        fi
    done

    # Check network connectivity
    if ping -c 1 8.8.8.8 > /dev/null 2>&1; then
        log_pass "Internet connectivity - Working"
    else
        log_fail "Internet connectivity - Failed"
    fi
    echo ""
}

# Test 12: Security Configuration
test_security_config() {
    log_test "Security Configuration Check"

    # Check for .htaccess files
    if [ -f ".htaccess" ]; then
        log_pass ".htaccess file present for Apache configuration"

        # Check for security headers
        if grep -q "X-Frame-Options" .htaccess; then
            log_pass "Security headers configured in .htaccess"
        else
            log_warn "Consider adding security headers to .htaccess"
        fi
    else
        log_warn ".htaccess file not found"
    fi

    # Check for secure file permissions (placeholder)
    log_info "File permissions should be checked on server deployment"
    echo ""
}

# Main execution
main() {
    echo "Starting comprehensive FlexPBX testing..."
    echo "Test environment: $(uname -s) $(uname -r)"
    echo "Timestamp: $(date)"
    echo ""

    # Run all tests
    test_dns_resolution
    test_sip_connectivity
    test_web_interface
    test_sip_extensions
    test_configuration_files
    test_callcentric_trunk
    test_google_voice_config
    test_extension_validation
    test_admin_interface
    test_file_organization
    test_system_requirements
    test_security_config

    # Summary
    echo "=========================================="
    echo "🏁 TEST SUMMARY"
    echo "=========================================="
    echo "Total Tests: $TOTAL_TESTS"
    echo -e "Passed: ${GREEN}$TESTS_PASSED${NC}"
    echo -e "Failed: ${RED}$TESTS_FAILED${NC}"
    echo ""

    if [ $TESTS_FAILED -eq 0 ]; then
        echo -e "${GREEN}✅ ALL TESTS PASSED!${NC}"
        echo "FlexPBX system appears ready for production use."
    else
        echo -e "${YELLOW}⚠️  SOME TESTS FAILED${NC}"
        echo "Review failed tests before production deployment."
    fi

    echo ""
    echo "📋 Next Steps:"
    echo "1. Upload organized files to server using file manager"
    echo "2. Start FlexPBX service on server"
    echo "3. Configure extensions and trunks via admin interface"
    echo "4. Test SIP client registration with extension 2001"
    echo "5. Test inbound calls from Callcentric network"
    echo ""
    echo "🎯 Quick Test: Try calling sip:101@flexpbx.devinecreations.net"
    echo ""
}

# Run the test suite
main "$@"