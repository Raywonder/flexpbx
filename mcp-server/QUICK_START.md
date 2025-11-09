# Quick Start Guide

Get up and running with FlexPBX VoIP MCP Server in 5 minutes.

## Prerequisites

- Node.js 18+ installed
- Asterisk PBX with AMI enabled
- AMI username and password

## Installation

```bash
# Navigate to directory
cd /home/devinecr/apps/mcp-servers/flexpbx-voip-mcp

# Install dependencies
npm install

# Takes ~30 seconds
```

## Configuration

```bash
# Copy environment template
cp .env.example .env

# Edit with your settings
nano .env
```

Minimum required settings:
```env
AMI_HOST=127.0.0.1
AMI_PORT=5038
AMI_USERNAME=your_ami_user
AMI_SECRET=your_ami_password
```

## Test Connection

```bash
# Run connection test
node examples/test-connection.js

# Expected output:
# ✓ Connected successfully
# ✓ Status retrieved
# ✓ Disconnected
# All tests passed! ✓
```

## Use with Claude Desktop

Add to Claude Desktop config:

**macOS**: `~/Library/Application Support/Claude/claude_desktop_config.json`
**Linux**: `~/.config/Claude/claude_desktop_config.json`

```json
{
  "mcpServers": {
    "flexpbx-voip": {
      "command": "node",
      "args": [
        "/home/devinecr/apps/mcp-servers/flexpbx-voip-mcp/src/index.js"
      ],
      "env": {
        "AMI_HOST": "127.0.0.1",
        "AMI_PORT": "5038",
        "AMI_USERNAME": "your_username",
        "AMI_SECRET": "your_secret"
      }
    }
  }
}
```

Restart Claude Desktop.

## Try It Out

Ask Claude:

```
"Check the Asterisk system status"

"List all extensions and their registration status"

"Show me active conference rooms"

"Get dial plan rules for Groundwire"
```

## 20 Available Tools

### Core (4)
- asterisk_status
- list_channels
- originate_call
- hangup_channel

### Conference (7)
- list_conferences
- get_conference_participants
- kick_participant
- mute_participant
- unmute_participant
- lock_conference
- unlock_conference

### Extensions (3)
- list_extensions
- get_extension_status
- get_extension_registration

### Dial Plan (3)
- get_dial_rules
- get_feature_codes
- validate_number

### Analytics (3)
- query_cdr
- get_call_stats
- get_extension_summary

## Next Steps

1. **Run All Tests**:
   ```bash
   node examples/test-connection.js
   node examples/test-conference.js
   node examples/dialplan-demo.js
   ```

2. **Read Documentation**:
   - `README.md` - Complete overview
   - `docs/API.md` - API reference
   - `docs/EXAMPLES.md` - Usage examples
   - `docs/SECURITY.md` - Security guide

3. **Configure Database** (optional for CDR):
   ```env
   DB_HOST=127.0.0.1
   DB_NAME=asteriskcdrdb
   DB_USER=cdr_readonly
   DB_PASSWORD=your_password
   ```

4. **Try Advanced Features**:
   - Conference management
   - Extension monitoring
   - Call analytics
   - Multi-format dial plans

## Troubleshooting

### Connection Failed
```bash
# Check AMI is enabled
grep enabled /etc/asterisk/manager.conf

# Should show: enabled = yes

# Check port is listening
netstat -tlnp | grep 5038

# Should show: tcp 0.0.0.0:5038 LISTEN
```

### Authentication Failed
```bash
# Verify credentials in Asterisk
asterisk -rx "manager show users"

# Check your AMI user permissions
```

### Tools Not Showing in Claude
```bash
# Restart Claude Desktop completely
# Check Claude Desktop logs for errors
# Verify config file JSON is valid
```

## Common Use Cases

### Monitor System
```
"Give me a complete status report of the phone system"
```

### Manage Conference
```
"Show participants in conference 1000"
"Lock conference 1000"
"Mute participant with channel PJSIP/2001-00000001"
```

### Check Extensions
```
"Is extension 2006 registered?"
"Show me all offline extensions"
```

### Configure SIP Client
```
"Get dial rules for Groundwire"
"What feature codes are available?"
```

## Support

- Documentation: `docs/` directory
- Examples: `examples/` directory
- Issues: GitHub (when published)
- Security: See `docs/SECURITY.md`

## Quick Reference

| Task | Command |
|------|---------|
| Install | `npm install` |
| Test | `node examples/test-connection.js` |
| Run | `npm start` |
| Dev mode | `npm run dev` |
| Check config | `cat .env` |

---

**Ready in 5 minutes!** 🚀

For complete documentation, see `README.md`.
