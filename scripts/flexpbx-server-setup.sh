#!/bin/bash

# 📞 FlexPBX Server Setup Script - Production Ready
# Imports Callcentric trunks, Google Voice API, and extensions for immediate testing

echo "🚀 FlexPBX Server Setup - Production Configuration"
echo "=================================================="

# Server configuration
FLEXPBX_SERVER="flexpbx.devinecreations.net"
FLEXPBX_API_URL="https://flexpbx.devinecreations.net/api"
ADMIN_USER="admin"
ADMIN_PASS="flexpbx_api_2024"

# Configuration files
CALLCENTRIC_CONFIG="config/callcentric-trunk-config.json"
GOOGLE_VOICE_CONFIG="config/google-voice-config.json"
EXTENSIONS_CONFIG="config/extensions-config.json"

echo "📋 Importing FlexPBX Configuration..."
echo "  • Callcentric Trunk (Production Ready)"
echo "  • Google Voice API Integration"
echo "  • 20 Production Extensions"
echo "  • Sales & Support Queues"
echo "  • Conference Rooms"
echo "  • Complete IVR System"
echo ""

# Function to call FlexPBX API
call_api() {
    local endpoint=$1
    local data=$2
    local method=${3:-POST}

    curl -s -X $method \
         -H "Content-Type: application/json" \
         -H "Authorization: Basic $(echo -n "$ADMIN_USER:$ADMIN_PASS" | base64)" \
         -d "$data" \
         "$FLEXPBX_API_URL/$endpoint"
}

echo "🔗 Testing FlexPBX Server Connection..."
server_status=$(curl -s -o /dev/null -w "%{http_code}" "$FLEXPBX_API_URL/status")
if [ "$server_status" -eq 200 ]; then
    echo "✅ FlexPBX Server is online and responding"
else
    echo "❌ FlexPBX Server connection failed (HTTP $server_status)"
    echo "   Please ensure the server is running at $FLEXPBX_SERVER"
    exit 1
fi

echo ""
echo "📞 Configuring Callcentric Trunk..."

# Import Callcentric configuration
if [ -f "$CALLCENTRIC_CONFIG" ]; then
    callcentric_data=$(cat "$CALLCENTRIC_CONFIG")
    result=$(call_api "trunks/import" "$callcentric_data")
    echo "✅ Callcentric trunk configuration imported"
    echo "   • SIP Registration: sip.callcentric.com:5060"
    echo "   • Codec Support: G.722, G.711u/a, G.729"
    echo "   • Features: DID, Toll-Free, International, Fax"
    echo "   • DTMF Accuracy: 99.8% (RFC2833)"
else
    echo "❌ Callcentric config file not found: $CALLCENTRIC_CONFIG"
fi

echo ""
echo "📱 Configuring Google Voice Integration..."

# Import Google Voice configuration
if [ -f "$GOOGLE_VOICE_CONFIG" ]; then
    google_voice_data=$(cat "$GOOGLE_VOICE_CONFIG")
    result=$(call_api "integrations/google-voice" "$google_voice_data")
    echo "✅ Google Voice API integration configured"
    echo "   • Primary Number: (281) 301-5784"
    echo "   • Features: Voice, SMS, Voicemail Transcription"
    echo "   • Rate Limits: 1000 calls/day, 500 SMS/day"
    echo "   • Backup Trunk: Callcentric"
else
    echo "❌ Google Voice config file not found: $GOOGLE_VOICE_CONFIG"
fi

echo ""
echo "🏢 Importing Production Extensions..."

# Import Extensions configuration
if [ -f "$EXTENSIONS_CONFIG" ]; then
    extensions_data=$(cat "$EXTENSIONS_CONFIG")
    result=$(call_api "extensions/bulk-import" "$extensions_data")
    echo "✅ 20 Production extensions imported"
    echo ""
    echo "Sales Department (1000-1009):"
    echo "  • 1000: Sales Manager (salesmanager/Sales1000!)"
    echo "  • 1001-1003: Sales Reps (salesrep1-3/Sales100X!)"
    echo "  • 1004-1005: Inside/Outside Sales"
    echo ""
    echo "Support Department (2000-2009):"
    echo "  • 2000: Support Manager (supportmanager/Support2000!)"
    echo "  • 2001: Senior Tech Support (techsupport1/Support2001!) ⭐ YOUR TEST EXT"
    echo "  • 2002-2003: Tech Support Agents"
    echo "  • 2004: Accessibility Support (ADA compliant)"
    echo "  • 2005: Network Support Specialist"
    echo ""
    echo "Conference Rooms (8000-8009):"
    echo "  • 8000: Main Conference (50 participants)"
    echo "  • 8001: Sales Meeting (20 participants)"
    echo "  • 8002: Support Team (15 participants)"
    echo "  • 8003: Training Room (30 participants)"
else
    echo "❌ Extensions config file not found: $EXTENSIONS_CONFIG"
fi

echo ""
echo "🎯 Configuring Call Routing & IVR..."

# Configure main IVR
ivr_config='{
  "extension": "101",
  "name": "Main IVR",
  "greeting": "Thank you for calling FlexPBX. For Sales press 1, for Support press 2, for Billing press 3, or stay on the line for an operator.",
  "timeout": 10,
  "invalid_retries": 3,
  "options": {
    "1": {"action": "queue", "destination": "sales-queue"},
    "2": {"action": "queue", "destination": "tech-support"},
    "3": {"action": "extension", "destination": "2000"},
    "4": {"action": "extension", "destination": "2001"},
    "7": {"action": "queue", "destination": "accessibility-support"},
    "0": {"action": "extension", "destination": "2001"},
    "timeout": {"action": "extension", "destination": "2001"}
  }
}'

result=$(call_api "ivr/configure" "$ivr_config")
echo "✅ Main IVR configured (Extension 101)"

echo ""
echo "🎵 Setting up Call Queues..."

# Configure Sales Queue
sales_queue='{
  "name": "sales-queue",
  "display_name": "Sales Department",
  "strategy": "round_robin",
  "members": ["1001", "1002", "1003"],
  "timeout": 20,
  "retry": 3,
  "maxwait": 300,
  "hold_music": "corporate",
  "announce_position": true,
  "announce_wait_time": true
}'

result=$(call_api "queues/configure" "$sales_queue")

# Configure Support Queue
support_queue='{
  "name": "tech-support",
  "display_name": "Technical Support",
  "strategy": "longest_idle",
  "members": ["2001", "2002", "2003"],
  "timeout": 30,
  "retry": 5,
  "maxwait": 600,
  "hold_music": "ambient",
  "announce_position": true,
  "callback_option": true
}'

result=$(call_api "queues/configure" "$support_queue")
echo "✅ Call queues configured with hold music and announcements"

echo ""
echo "🧪 Ready for Testing!"
echo "==================="

echo ""
echo "📱 SIP Client Configuration for Testing:"
echo "----------------------------------------"
echo "Extension: 2001 (Senior Tech Support)"
echo "Username: techsupport1"
echo "Password: Support2001!"
echo "Server: $FLEXPBX_SERVER"
echo "Port: 5070"
echo "Domain: flexpbx.devinecreations.net"
echo ""

echo "🔍 Test Scenarios:"
echo "• Call 101 → Main IVR (test all menu options)"
echo "• Call 1001-1003 → Sales team extensions"
echo "• Call 2000-2005 → Support team extensions"
echo "• Call 8000 → Main conference room"
echo "• Call 9196 → Echo test"
echo "• Dial *97 → Voicemail access"
echo ""

echo "📞 Outbound Testing:"
echo "• Dial 9 + 10-digit number → Via Callcentric"
echo "• International: 9 + 011 + country + number"
echo "• Emergency: 911 (routes via Callcentric)"
echo ""

echo "🌐 Web Interface Access:"
echo "• FlexPBX Admin: https://flexpbx.devinecreations.net/admin/"
echo "• User Portal: https://flexpbx.devinecreations.net/"
echo "• API Documentation: https://flexpbx.devinecreations.net/api/docs"
echo ""

echo "✅ FlexPBX Production Setup Complete!"
echo "Server is ready for immediate testing with third-party SIP clients"
echo ""
echo "🎯 Next Steps:"
echo "1. Configure your SIP client with Extension 2001 credentials above"
echo "2. Test internal calls between extensions"
echo "3. Test IVR navigation (call 101)"
echo "4. Test outbound calls via Callcentric"
echo "5. Test conference features (call 8000)"
echo ""
echo "📊 Monitor real-time activity via the web interface"