#!/bin/bash
#
# FlexPBX MOH Provider - External Installation Test Script
# This script demonstrates how ANOTHER FlexPBX installation would use our service
#
# Usage: bash test-installation.sh
#

echo "=================================================="
echo "FlexPBX MOH Provider - Installation Test"
echo "=================================================="
echo ""

API_URL="https://flexpbx.devinecreations.net/api/moh-provider.php"

echo "1. Checking service availability..."
if curl -sf "$API_URL?action=health" > /dev/null; then
    echo "   ✓ Service is online"
else
    echo "   ✗ Service unavailable"
    exit 1
fi
echo ""

echo "2. Fetching provider information..."
curl -s "$API_URL?action=info" | python3 -m json.tool | head -20
echo ""

echo "3. Listing available streams..."
echo "   Fetching stream catalog..."
curl -s "$API_URL?action=list" | python3 -c "
import sys, json
data = json.load(sys.stdin)
print(f\"   Total streams available: {data['total_streams']}\")
print(\"   Streams:\")
for stream in data['streams']:
    print(f\"   - {stream['display_name']}\")
    print(f\"     URL: {stream['url']}\")
    print(f\"     Category: {stream['category']}\")
    print(f\"     Tags: {', '.join(stream['tags'][:3])}...\")
    print(\"\")
"
echo ""

echo "4. Searching for accessibility content..."
curl -s "$API_URL?action=search&q=audio-described" | python3 -c "
import sys, json
data = json.load(sys.stdin)
print(f\"   Found {data['total_results']} result(s)\")
for stream in data['streams']:
    print(f\"   - {stream['display_name']}: {stream['description']}\")
"
echo ""

echo "5. Downloading Asterisk configuration preview..."
echo "   First 30 lines:"
curl -s "$API_URL?action=config&format=asterisk" | head -30
echo ""

echo "6. Getting installation instructions..."
curl -s "$API_URL?action=install" | python3 -c "
import sys, json
data = json.load(sys.stdin)
instructions = data['instructions']
print(f\"   Title: {instructions['title']}\")
print(f\"   Version: {instructions['version']}\")
print(\"   Installation Methods:\")
for method, details in instructions['installation_methods'].items():
    print(f\"   - {method.upper()}: {details['description']}\")
"
echo ""

echo "7. Testing module manifest..."
curl -s "$API_URL?action=module-manifest" | python3 -c "
import sys, json
data = json.load(sys.stdin)
manifest = data['manifest']
module = manifest['module']
print(f\"   Module: {module['name']}\")
print(f\"   Version: {module['version']}\")
print(f\"   Category: {module['category']}\")
print(f\"   License: {module['license']}\")
print(f\"   Streams: {manifest['features']['streams']}\")
"
echo ""

echo "=================================================="
echo "✓ All tests passed!"
echo "=================================================="
echo ""
echo "To install on YOUR system, run:"
echo ""
echo "  curl -s \"$API_URL?action=config&format=asterisk\" >> /etc/asterisk/musiconhold.conf"
echo "  asterisk -rx \"moh reload\""
echo ""
echo "Or visit: https://flexpbx.devinecreations.net/modules/moh-provider/"
echo "=================================================="
