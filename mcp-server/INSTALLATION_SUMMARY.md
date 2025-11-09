# FlexPBX VoIP MCP Server - Installation Summary

**Installation Date:** November 8, 2025
**Status:** ✓ Complete and Ready for Production

---

## What Was Installed

### 1. MCP Server Directory
**Location:** `/home/devinecr/apps/mcp-servers/voip-asterisk-mcp`
**Symlink:** `/home/devinecr/apps/mcp-servers/flexpbx-voip-mcp` → `voip-asterisk-mcp`

### 2. Configuration Files Created

#### `.env` - Environment Configuration
```bash
AMI_HOST=localhost
AMI_PORT=5038
AMI_USERNAME=flexpbx
AMI_PASSWORD=FlexPBX_AMI_2024!
```

#### `flexpbx-mcp.service` - Systemd Service
- Service file ready to install to `/etc/systemd/system/`
- Configured to run as user `devinecr`
- Auto-restarts on failure
- Depends on `asterisk.service`

### 3. Test Scripts Created

#### `test-ami-simple.js` - Connection Test
✓ Tests AMI connection to FlexPBX
✓ Verifies extension 2000 exists
✓ Checks system status

### 4. Documentation Created

#### `FLEXPBX_INTEGRATION_GUIDE.md` - Complete Integration Guide
- Setup instructions
- Configuration details
- All 13 MCP tools documented
- Troubleshooting guide
- Security best practices

---

## Connection Test Results

### Test Output
```
✓ Connected to Asterisk AMI

Test 1: Getting Core Status...
✓ PASS - System Info:
  Current Calls: 0

Test 2: Listing PJSIP Endpoints...
✓ PASS - Found endpoints

Test 3: Checking Extension 2000...
✓ PASS - Extension 2000 found
  DeviceState: N/A
  ActiveChannels: 0

Test 4: Listing Active Channels...
✓ PASS
  Active Channels: 0

✓ FlexPBX MCP server is ready to use!
```

---

## FlexPBX Integration Details

### AMI Credentials (from `/etc/asterisk/manager.conf`)
- **Username:** flexpbx
- **Password:** FlexPBX_AMI_2024!
- **Port:** 5038
- **Bind Address:** 127.0.0.1 (localhost only)
- **Permissions:** Full read/write access to all AMI functions

### Database Connection (Optional)
- **Host:** localhost
- **Database:** flexpbxuser_flexpbx
- **User:** flexpbxuser_flexpbxserver
- **Password:** DomDomRW93!

---

## Available MCP Tools (13 Total)

### Call Management (5 tools)
1. **originate_call** - Start a new call
2. **hangup_call** - End an active call
3. **park_call** - Park a call
4. **send_dtmf** - Send DTMF tones
5. **get_active_channels** - List all active calls

### Extension & Peer Management (2 tools)
6. **get_sip_peer_status** - Check SIP/PJSIP registration
7. **get_extension_status** - Get extension state

### Queue Management (3 tools)
8. **get_queue_status** - View queue status
9. **add_queue_member** - Add agent to queue
10. **remove_queue_member** - Remove agent from queue

### Conference Management (2 tools)
11. **get_conference_list** - List conference participants
12. **kick_conference_user** - Remove user from conference

### Voicemail (1 tool)
13. **get_voicemail_list** - List voicemail boxes

---

## Quick Start Commands

### Run Connection Test
```bash
cd /home/devinecr/apps/mcp-servers/flexpbx-voip-mcp
node test-ami-simple.js
```

### Install Systemd Service
```bash
sudo cp flexpbx-mcp.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable flexpbx-mcp
sudo systemctl start flexpbx-mcp
sudo systemctl status flexpbx-mcp
```

### View Service Logs
```bash
sudo journalctl -u flexpbx-mcp -f
```

### Manual Start (for testing)
```bash
cd /home/devinecr/apps/mcp-servers/flexpbx-voip-mcp
node src/index.js
```

---

## MCP Client Configuration

### For Claude Desktop

Edit: `~/Library/Application Support/Claude/claude_desktop_config.json`

```json
{
  "mcpServers": {
    "flexpbx-voip": {
      "command": "node",
      "args": ["/home/devinecr/apps/mcp-servers/flexpbx-voip-mcp/src/index.js"],
      "env": {
        "AMI_HOST": "localhost",
        "AMI_PORT": "5038",
        "AMI_USERNAME": "flexpbx",
        "AMI_PASSWORD": "FlexPBX_AMI_2024!"
      }
    }
  }
}
```

### For Cline (VS Code)

Edit: `.cline/mcp_settings.json`

```json
{
  "mcpServers": {
    "flexpbx-voip": {
      "command": "node",
      "args": ["/home/devinecr/apps/mcp-servers/flexpbx-voip-mcp/src/index.js"],
      "env": {
        "AMI_HOST": "localhost",
        "AMI_PORT": "5038",
        "AMI_USERNAME": "flexpbx",
        "AMI_PASSWORD": "FlexPBX_AMI_2024!"
      }
    }
  }
}
```

---

## Integration with FlexPBX Installer

### Option 1: Add to Installer API

Add to `/home/flexpbxuser/public_html/api/install.php`:

```php
// Install MCP Server module
case 'install_mcp_server':
    $result = installMCPServer();
    break;

function installMCPServer() {
    // Check Node.js
    exec('node --version', $output, $return);
    if ($return !== 0) {
        return ['error' => 'Node.js 18+ required'];
    }

    // Install dependencies
    exec('cd /home/devinecr/apps/mcp-servers/flexpbx-voip-mcp && npm install');

    // Install systemd service
    exec('sudo cp /home/devinecr/apps/mcp-servers/flexpbx-voip-mcp/flexpbx-mcp.service /etc/systemd/system/');
    exec('sudo systemctl daemon-reload');
    exec('sudo systemctl enable flexpbx-mcp');
    exec('sudo systemctl start flexpbx-mcp');

    return ['success' => true];
}
```

### Option 2: Add to Setup Wizard Checklist

Add to setup wizard JSON:

```json
{
  "id": "mcp_server",
  "category": "Optional Features",
  "title": "AI Agent Integration (MCP Server)",
  "description": "Enable AI agents to control FlexPBX via natural language",
  "check_command": "systemctl is-active flexpbx-mcp",
  "install_command": "install_mcp_server",
  "documentation": "/home/devinecr/apps/mcp-servers/flexpbx-voip-mcp/FLEXPBX_INTEGRATION_GUIDE.md"
}
```

---

## System Requirements

### Software Requirements
- ✓ Node.js 18.0.0 or higher
- ✓ Asterisk with AMI enabled
- ✓ FlexPBX installed

### System Resources
- CPU: Minimal impact (Node.js process)
- Memory: ~50-100 MB
- Disk: ~20 MB (node_modules)
- Network: Localhost connection to AMI port 5038

---

## Security Notes

### What's Secure
✓ AMI bound to localhost only (127.0.0.1)
✓ Strong password authentication
✓ Service runs as non-root user (devinecr)
✓ Environment variables for sensitive data
✓ IP-based access control in manager.conf

### Security Checklist
```bash
# Verify .env permissions
chmod 600 /home/devinecr/apps/mcp-servers/flexpbx-voip-mcp/.env

# Check AMI is localhost only
grep "bindaddr = 127.0.0.1" /etc/asterisk/manager.conf

# Monitor AMI connections
tail -f /var/log/asterisk/full | grep Manager
```

---

## File Locations

### Primary Files
```
/home/devinecr/apps/mcp-servers/flexpbx-voip-mcp/
├── .env                            # Configuration
├── src/index.js                    # Main server
├── flexpbx-mcp.service            # Systemd service
├── test-ami-simple.js             # Test script
├── FLEXPBX_INTEGRATION_GUIDE.md   # Full documentation
└── INSTALLATION_SUMMARY.md        # This file
```

### FlexPBX Files
```
/etc/asterisk/manager.conf         # AMI configuration
/home/flexpbxuser/                 # FlexPBX root
```

### Systemd Service (when installed)
```
/etc/systemd/system/flexpbx-mcp.service
```

---

## Verification Checklist

- [x] Asterisk running with AMI enabled
- [x] AMI credentials configured (flexpbx / FlexPBX_AMI_2024!)
- [x] Node.js 18+ installed
- [x] npm dependencies installed
- [x] .env file created with correct credentials
- [x] AMI connection tested successfully
- [x] Extension 2000 verified accessible
- [x] All 13 MCP tools documented
- [x] Systemd service file created
- [x] Integration guide completed

---

## Next Steps

### 1. Install Systemd Service (Optional)
```bash
sudo cp flexpbx-mcp.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable flexpbx-mcp
sudo systemctl start flexpbx-mcp
```

### 2. Configure MCP Client
Add the server to Claude Desktop or Cline using the configurations above.

### 3. Test Natural Language Control
Try commands like:
- "Show me all active calls"
- "Check if extension 2000 is registered"
- "What's the status of all call queues?"
- "Add extension 2001 to the support queue"

### 4. Monitor Performance
```bash
# Watch service logs
sudo journalctl -u flexpbx-mcp -f

# Check resource usage
ps aux | grep "node.*index.js"

# Monitor AMI connections
asterisk -rx "manager show connected"
```

---

## Support

### Documentation
- **Full Guide:** FLEXPBX_INTEGRATION_GUIDE.md
- **FlexPBX Docs:** /home/flexpbxuser/documentation/
- **Asterisk AMI:** /home/flexpbxuser/public_html/api/ASTERISK_API_INTEGRATION.md

### Contact
- **Email:** info@devinecreations.net
- **GitHub:** https://github.com/devinecreations/voip-asterisk-mcp

---

## License

MIT License - See LICENSE file for details

---

**Installation Complete!**
FlexPBX VoIP MCP Server is ready for production use.

Last Updated: November 8, 2025
