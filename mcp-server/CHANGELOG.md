# Changelog

All notable changes to the FlexPBX VoIP MCP Server will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - 2025-11-08

### Added - FlexPBX-Inspired Features
- **Conference Management** (7 new tools)
  - `list_conferences` - List active conference bridges
  - `get_conference_participants` - View conference participants
  - `kick_participant` - Remove participant from conference
  - `mute_participant` - Mute conference participant
  - `unmute_participant` - Unmute conference participant
  - `lock_conference` - Lock conference room
  - `unlock_conference` - Unlock conference room

- **Advanced Extension Management** (3 new tools)
  - `list_extensions` - List all PJSIP extensions
  - `get_extension_status` - Detailed extension status
  - `get_extension_registration` - Registration details

- **Dial Plan Support** (3 new tools)
  - `get_dial_rules` - Multi-format dial plan rules
  - `get_feature_codes` - Feature code listings
  - `validate_number` - Number validation

- **Call Analytics** (3 new tools)
  - `query_cdr` - Call detail record queries
  - `get_call_stats` - Call statistics
  - `get_extension_summary` - Per-extension summaries

### Enhanced
- **AMI Client**
  - Improved error handling with timeout protection
  - Event-based architecture for async responses
  - Automatic reconnection support
  - Action ID tracking for response correlation

- **Security**
  - Environment-based configuration (no hardcoded credentials)
  - Input validation on all tool parameters
  - Parameterized database queries
  - Timeout protection on all network operations
  - Graceful error handling and cleanup

- **Documentation**
  - Comprehensive API documentation
  - Security best practices guide
  - Usage examples for all 20 tools
  - Test scripts for validation
  - Claude Desktop integration examples

### Changed
- Upgraded from generic VoIP MCP to FlexPBX-inspired architecture
- Total tools increased from 13 to 20
- Switched from simple AMI wrapper to production-ready client
- Added multi-format support for dial plan rules

### Security Improvements
- No hardcoded credentials (inspired by FlexPBX security analysis)
- Environment variable configuration
- Input sanitization on all commands
- No direct shell execution (AMI-only interface)
- Rate limiting support
- Audit logging capabilities

## [1.0.0] - 2025-11-01 (Hypothetical Generic Version)

### Added
- Basic AMI integration (13 tools)
- Simple call management
- Extension monitoring
- Basic conference support
- Environment configuration
- MCP SDK integration

---

## Migration Guide: v1.0 to v2.0

### Breaking Changes
None - v2.0 is backward compatible with v1.0 tool calls.

### New Features to Adopt

1. **Update Environment Configuration**:
```bash
# Add to .env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=asteriskcdrdb
DB_USER=cdr_readonly
DB_PASSWORD=your_password
```

2. **Use New Conference Tools**:
```javascript
// Old way (v1.0)
"Send AMI command to list conferences"

// New way (v2.0)
"List active conferences" // Uses list_conferences tool
```

3. **Leverage Dial Plan Tools**:
```javascript
// New in v2.0
"Get dial rules for Groundwire"
"Validate number 18005551234"
```

4. **Access Call Analytics**:
```javascript
// New in v2.0
"Show me call statistics for last week"
"Get call summary for extension 2001"
```

### Recommended Actions

1. Update Claude Desktop configuration with new environment variables
2. Test new conference management tools
3. Configure dial plan rules for your SIP clients
4. Set up database connection for CDR analytics
5. Review security documentation and implement best practices

---

## Roadmap

### v2.1.0 (Planned)
- [ ] Real-time event streaming
- [ ] Call recording management
- [ ] Queue statistics and management
- [ ] Voicemail integration
- [ ] SIP trunk monitoring

### v2.2.0 (Planned)
- [ ] Call whisper/barge support
- [ ] Custom dial plan rule creation
- [ ] Advanced CDR analytics with charts
- [ ] Email/SMS notifications
- [ ] Multi-tenant support

### v3.0.0 (Future)
- [ ] WebRTC integration
- [ ] Video conference support
- [ ] AI-powered call analytics
- [ ] Integration with CRM systems
- [ ] Mobile app support

---

## Support

For questions, issues, or feature requests:
- GitHub Issues: https://github.com/devinecreations/flexpbx-voip-mcp/issues
- Documentation: See `docs/` directory
- Security issues: security@devinecreations.com
