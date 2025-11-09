# FlexPBX VoIP MCP Server

An enhanced Model Context Protocol (MCP) server for VoIP and Asterisk management, inspired by FlexPBX architecture with production-ready features.

## Features

### Core Capabilities
- **AMI Integration**: Full Asterisk Manager Interface connectivity with robust error handling
- **Conference Management**: Complete conference bridge control (join, leave, mute, lock)
- **Extension Management**: Monitor and manage PJSIP/SIP extensions
- **Dial Plan Rules**: Auto-configuration for SIP clients (Groundwire, Linphone, Zoiper)
- **Call Detail Records**: Query and analyze call history (requires database setup)
- **Security First**: Environment-based configuration, no hardcoded credentials

### 20 Production Tools

#### AMI Core (4 tools)
1. `asterisk_status` - Get system status and uptime
2. `list_channels` - List active calls
3. `originate_call` - Start new calls
4. `hangup_channel` - End active calls

#### Conference Management (7 tools)
5. `list_conferences` - List active conference rooms
6. `get_conference_participants` - View conference participants
7. `kick_participant` - Remove participant from conference
8. `mute_participant` - Mute conference participant
9. `unmute_participant` - Unmute conference participant
10. `lock_conference` - Lock conference to prevent new joins
11. `unlock_conference` - Unlock conference

#### Extension Management (3 tools)
12. `list_extensions` - List all extensions and status
13. `get_extension_status` - Get detailed extension status
14. `get_extension_registration` - View registration details

#### Dial Plan (3 tools)
15. `get_dial_rules` - Get dial plan rules for SIP clients
16. `get_feature_codes` - List feature codes (*97, *45, etc.)
17. `validate_number` - Validate number against dial plan

#### Call Analytics (3 tools)
18. `query_cdr` - Query call detail records
19. `get_call_stats` - Get call statistics
20. `get_extension_summary` - Get per-extension call summary

## Installation

### Prerequisites
- Node.js 18+
- Asterisk PBX with AMI enabled
- Access to Asterisk AMI credentials

### Setup

```bash
# Navigate to directory
cd /home/devinecr/apps/mcp-servers/flexpbx-voip-mcp

# Install dependencies
npm install

# Configure environment
cp .env.example .env
nano .env  # Edit with your settings
```

### Environment Configuration

Required variables:
```env
AMI_HOST=127.0.0.1
AMI_PORT=5038
AMI_USERNAME=your_ami_user
AMI_SECRET=your_ami_password
```

Optional (for CDR features):
```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=asteriskcdrdb
DB_USER=cdr_user
DB_PASSWORD=cdr_password
```

## Usage

### Claude Desktop Integration

Add to your Claude Desktop config (`~/Library/Application Support/Claude/claude_desktop_config.json` on macOS):

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

### Standalone Usage

```bash
# Start the server
npm start

# Development mode (auto-restart)
npm run dev
```

## Example Use Cases

### 1. Conference Management
```javascript
// List active conferences
claude> Use the list_conferences tool

// Join conference 1000
claude> Get participants in conference 1000

// Mute a participant
claude> Mute participant with channel PJSIP/2001-00000001 in conference 1000

// Lock the conference
claude> Lock conference 1000
```

### 2. Extension Monitoring
```javascript
// Check if extension 2001 is registered
claude> Get status for extension 2001

// List all extensions
claude> List all extensions and their registration status
```

### 3. Call Management
```javascript
// Originate a call from extension 2001 to 2002
claude> Originate call from PJSIP/2001 to extension 2002

// List active calls
claude> Show me all active channels

// Hangup a specific call
claude> Hangup channel PJSIP/2001-00000001
```

### 4. Dial Plan Configuration
```javascript
// Get dial rules for Groundwire
claude> Get dial rules in Groundwire format

// Validate a phone number
claude> Validate number 18005551234

// List feature codes
claude> Show me all feature codes
```

### 5. Call Analytics
```javascript
// Query recent calls
claude> Show me CDR records from 2025-01-01 to 2025-01-31

// Get call statistics
claude> Get call statistics for this week

// Extension call summary
claude> Get call summary for extension 2001
```

## Architecture

### Inspired by FlexPBX Patterns

This MCP server is inspired by the following FlexPBX architectural patterns:

1. **AMI Connection Management** (from `ami_connector.php`)
   - Robust socket handling with timeouts
   - Automatic reconnection logic
   - Action ID tracking for async responses

2. **Conference Bridge Control** (from `conference.php`)
   - Direct ConfBridge command interface
   - Participant tracking and management
   - Lock/unlock controls

3. **Dial Plan Rules** (from `dialplan.php`)
   - Multi-format support (Groundwire, Linphone, Zoiper)
   - Regex-based validation
   - Feature code mapping

4. **Extension Management** (from `AsteriskManager.php`)
   - PJSIP endpoint monitoring
   - Registration status tracking
   - AOR (Address of Record) details

### Security Improvements

- Environment-based configuration (no hardcoded credentials)
- Input validation on all tool calls
- Timeout protection on AMI operations
- Graceful error handling and cleanup
- No SQL injection vulnerabilities (parameterized queries)

## Development

### Project Structure
```
flexpbx-voip-mcp/
├── src/
│   ├── index.js              # Main MCP server
│   ├── ami-client.js         # Enhanced AMI client
│   ├── conference-manager.js # Conference management
│   ├── dialplan-manager.js   # Dial plan rules
│   ├── cdr-manager.js        # Call detail records
│   └── extension-manager.js  # Extension management
├── docs/
│   ├── API.md               # API documentation
│   ├── SECURITY.md          # Security guidelines
│   └── EXAMPLES.md          # Usage examples
├── examples/
│   ├── claude-config.json   # Claude Desktop config
│   └── test-calls.js        # Test scripts
├── .env.example             # Environment template
├── package.json
└── README.md
```

### Testing

```bash
# Run tests
npm test

# Test AMI connection
node examples/test-connection.js

# Test conference management
node examples/test-conference.js
```

## Differences from Generic VoIP MCP

This enhanced version adds:

1. **Conference Management** (7 tools) - Complete ConfBridge control
2. **Advanced Extension Management** (3 tools) - PJSIP/SIP monitoring
3. **Dial Plan Support** (3 tools) - SIP client auto-configuration
4. **Call Analytics** (3 tools) - CDR querying and statistics
5. **Better Error Handling** - Production-ready error messages
6. **Security Hardening** - Environment-based config, validation
7. **Multi-format Support** - Groundwire, Linphone, Zoiper formats

## Contributing

This is a sanitized, open-source implementation inspired by FlexPBX patterns. No proprietary code is included.

To contribute:
1. Fork the repository
2. Create a feature branch
3. Add tests for new features
4. Submit a pull request

## License

MIT License - See LICENSE file for details

## Credits

- Inspired by FlexPBX architecture patterns
- Built with Model Context Protocol SDK
- Asterisk AMI integration patterns

## Support

For issues and questions:
- GitHub Issues: https://github.com/devinecreations/flexpbx-voip-mcp/issues
- Documentation: See `docs/` directory
- Examples: See `examples/` directory

## Version History

### v2.0.0 (2025-11-08)
- Enhanced FlexPBX-inspired implementation
- Added conference management (7 tools)
- Added dial plan support (3 tools)
- Added CDR analytics (3 tools)
- Improved security and error handling
- Multi-format dial rule support

### v1.0.0
- Initial generic VoIP MCP server
- Basic AMI integration (13 tools)
