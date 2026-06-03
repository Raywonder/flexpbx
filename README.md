# FlexPBX - Complete Open Source PBX System

FlexPBX is a complete, feature-rich PBX system built on Asterisk with a modern web interface, user migration system, documentation center, and free remote streaming music on hold.

**Version**: 1.4
**Release Date**: November 9, 2025
**License**: Open Source

---

## 🎉 What's New in v1.4

- **User Migration System**: Move users between extensions and departments with automatic queue updates
- **Complete Documentation Center**: 14 comprehensive guides (5000+ lines)
- **Remote Streaming MOH**: Free TappedIn Radio Network integration
- **Department Management**: Enhanced with automatic queue management
- **Migration History**: Complete audit trail for all user moves
- **Complete Database Schema**: All 17 tables in single SQL file

---

## 📦 Download & Installation

### Quick Installation (Recommended)

Download pre-built installers:

**Master Server** (Full features, host your own):
```bash
wget https://flexpbx.devinecreations.net/downloads/FlexPBX-Master-Server-v1.4.tar.gz
tar -xzf FlexPBX-Master-Server-v1.4.tar.gz
cd FlexPBX-Master-Server-v1.4
sudo bash install.sh
```

**Client Installation** (Uses remote streaming):
```bash
wget https://flexpbx.devinecreations.net/downloads/FlexPBX-Client-v1.4.tar.gz
tar -xzf FlexPBX-Client-v1.4.tar.gz
cd FlexPBX-Client-v1.4
sudo bash install.sh
```

### Install from Source

Clone this repository and copy source files manually:

```bash
# Clone repository
git clone https://github.com/Raywonder/flexpbx.git
cd flexpbx

# Copy source files
cp -r src/admin/* /path/to/webroot/admin/
cp -r src/api/* /path/to/webroot/api/
cp -r src/user-portal/* /path/to/webroot/user-portal/
cp -r src/includes/* /path/to/webroot/includes/

# Import database
mysql -u username -p database_name < sql/complete-schema.sql

# Configure Asterisk
sudo cp config/musiconhold.conf /etc/asterisk/
sudo asterisk -rx "moh reload"
```

See [`src/README.md`](src/README.md) for detailed installation instructions.

---

## 📁 Repository Structure

```
flexpbx/
├── src/                        Source code (275 files)
│   ├── admin/                  Admin panel (81 PHP files)
│   ├── api/                    API endpoints (123 PHP files)
│   ├── user-portal/            User portal (38 PHP files)
│   ├── includes/               Shared libraries (17 PHP files)
│   ├── scripts/                Utility scripts (11 files)
│   ├── cron/                   Scheduled tasks (5 PHP files)
│   └── README.md               Source installation guide
│
├── docs/                       Documentation (14 files, 5000+ lines)
│   ├── AZURACAST_INTEGRATION.md
│   ├── COMPLETE_SYSTEM_STATUS_NOV9_2025.md
│   ├── DOCUMENTATION_INDEX.md
│   ├── USER_MIGRATION_COMPLETE_GUIDE.md
│   └── ... (10 more)
│
├── sql/                        Database schemas
│   ├── complete-schema.sql     All 17 tables (360 lines)
│   └── migration_history_table.sql
│
├── config/                     Configuration files
│   ├── musiconhold.conf        MOH with remote streaming
│   ├── asterisk-dialplan-defaults.conf
│   └── ivr-templates.json
│
├── scripts/                    Asterisk automation
│   ├── auto-configure-feature-codes.php
│   └── configure-asterisk-dialplan.sh
│
├── mcp-server/                 AI Integration (MCP Server)
│   ├── src/                    MCP server source (20 tools)
│   ├── docs/                   MCP documentation
│   ├── examples/               Usage examples
│   └── README.md               MCP server guide
│
└── README.md                   This file
```

---

## ✨ Features

### User Management
- ✅ User invitation system with email notifications
- ✅ **User migration** (change extensions, move departments)
- ✅ Extension auto-assignment (2000-2999 range)
- ✅ Flex Phone account recovery API with email confirmation for username recovery, password reset, and optional current-password delivery; password changes update Asterisk and FlexPBX extension records only, never trunk settings
- ✅ Department management with automatic queue updates
- ✅ Bulk user operations
- ✅ Complete migration history audit trail

### Communication
- ✅ PJSIP SIP endpoints
- ✅ Voicemail with email notifications
- ✅ Call queues with statistics
- ✅ Conference rooms (ConfBridge)
- ✅ Call parking (700-702)
- ✅ Ring groups
- ✅ IVR builder with templates

### Music on Hold
- ✅ **Free remote streaming** (TappedIn Radio Network)
- ✅ Multiple radio stations (TappedIn, Raywonder, SoulFood, ChrisMix)
- ✅ Local file support
- ✅ Jellyfin integration (optional)
- ✅ Remote MOH server connection (optional)

### Documentation & Help
- ✅ **Documentation center** with 14 comprehensive guides
- ✅ Searchable documentation interface
- ✅ Markdown to HTML viewer
- ✅ Print and download capabilities
- ✅ Help system with articles
- ✅ Quick reference cards

### Admin Features
- ✅ Modern admin dashboard
- ✅ Extension management
- ✅ Department management
- ✅ Queue management
- ✅ Call logs and analytics
- ✅ Mattermost chat integration
- ✅ Notification system
- ✅ Security settings
- ✅ Role management

### Integrations
- ✅ Mattermost chat embedding
- ✅ TextNow SMS/calling
- ✅ Google Voice OAuth 2.0
- ✅ Twilio API
- ✅ XMPP messaging framework
- ✅ Mastodon OAuth authentication

### AI Integration (MCP Server)
- ✅ **20 production-ready tools** for AI assistants
- ✅ Conference management (7 tools)
- ✅ Extension monitoring (3 tools)
- ✅ Dial plan rules (3 tools)
- ✅ Call analytics (3 tools)
- ✅ Claude Desktop integration
- ✅ Full AMI integration

See [`mcp-server/README.md`](mcp-server/README.md) for MCP server details.

---

## 🗄️ Database Schema

### 17 Tables Included

**Core Tables**:
- `extensions` - User extensions with status tracking
- `departments` - Department organization
- `department_queues` - Department-queue relationships

**Migration System**:
- `migration_history` - Complete audit trail

**XMPP/Messaging**:
- `xmpp_users`, `xmpp_messages`, `xmpp_roster`

**Notifications**:
- `notifications`, `announcements`

**IVR & Queues**:
- `ivr_templates`, `queue_members`, `queue_stats`

**Voicemail**:
- `voicemail`

**PJSIP**:
- `ps_endpoints`, `ps_auths`, `ps_aors`

**Support**:
- `help_articles`, `log_files`

**Views**:
- `extension_summary` - Complete extension overview

See [`sql/complete-schema.sql`](sql/complete-schema.sql) for full schema.

---

## 🎵 Music on Hold

### Free Remote Streaming (NEW in v1.4)

FlexPBX v1.4 includes **free remote streaming** for all installations:

1. **TappedIn Radio** - Soundscapes, meditation music, podcasts
   - URL: https://stream.tappedin.fm/radio/8000/tappedin-radio
   - Status: ✅ Free for all FlexPBX users

2. **Raywonder Radio** - Audio described content, educational
   - URL: https://stream.raywonderis.me/radio/8000/raywonder-radio
   - Status: ✅ Free for all FlexPBX users

3. **External Streams** - SoulFood Radio, ChrisMix Radio

4. **Optional** - Jellyfin, Remote MOH server (ready to enable)

**No music licensing needed!** Works immediately out of the box.

See [`config/musiconhold.conf`](config/musiconhold.conf) for configuration.

---

## 🚀 Quick Start

### 1. System Requirements

**Minimum**:
- Rocky Linux 8/9, AlmaLinux 8/9, CentOS 8+, or Ubuntu 20.04+
- 2GB RAM (master), 1GB RAM (client)
- 20GB disk (master), 10GB disk (client)
- PHP 8.0+ (8.1 or 8.2 recommended)
- MariaDB 10.5+ or MySQL 8.0+
- Asterisk 18.12.1+
- Apache 2.4+ with mod_rewrite

**Recommended**:
- 4GB RAM
- 40GB disk
- PHP 8.2
- MariaDB 10.11
- Internet connection (for remote streaming MOH)

### 2. Post-Installation

After installation:

1. **Access Admin Portal**:
   ```
   https://your-server/admin/
   ```

2. **Default Admin**:
   - Create admin account during installation
   - Or set role to 'admin' in database

3. **Configure System**:
   - System Settings → Configure PBX
   - Department Management → Create departments
   - User Management → Invite users
   - MOH Settings → Test remote streaming

4. **Test Features**:
   - Dial *43 for echo test
   - Dial 9991 to test MOH
   - Dial 8000 for conference room

### 3. User Migration

**Move users between extensions or departments**:

1. Go to **Admin Dashboard** → **User & Department Management** → **User Migration**

2. Choose migration type:
   - Complete migration (extension + department)
   - Quick extension change
   - Department transfer only
   - Bulk migration

3. Preview changes before executing

4. System automatically updates:
   - PJSIP configuration
   - Queue memberships
   - Voicemail location
   - User portal display

See [`docs/USER_MIGRATION_COMPLETE_GUIDE.md`](docs/USER_MIGRATION_COMPLETE_GUIDE.md) for detailed guide.

---

## 📚 Documentation

### Complete Guides (14 Files)

All documentation is included in the `docs/` directory:

1. **System Status & Implementation**:
   - COMPLETE_SYSTEM_STATUS_NOV9_2025.md
   - FLEXPBX_COMPLETE_FEATURE_INTEGRATION_NOV9_2025.md
   - SESSION_COMPLETE_NOV9_2025.md
   - FINAL_SESSION_SUMMARY_NOV9_2025.md

2. **Setup & Configuration**:
   - AZURACAST_INTEGRATION.md
   - INSTALLER_DIALPLAN_INTEGRATION.md
   - FREEPBX_COMPATIBILITY_GUIDE.md

3. **User Management**:
   - USER_MIGRATION_COMPLETE_GUIDE.md (500+ lines)
   - USER_MIGRATION_SUMMARY_NOV9_2025.md
   - USER_INVITATION_QUICK_START.md

4. **Quick Reference**:
   - FLEXPBX_QUICK_REFERENCE_CARD.md
   - DOCUMENTATION_INDEX.md
   - DOCUMENTATION_LINKING_COMPLETE_NOV9_2025.md
   - FLEXPBX_XMPP_INTEGRATION.md

**Total**: 5000+ lines of comprehensive documentation

### Access Documentation

**Via Admin Panel**:
```
Admin Dashboard → Documentation Center
```

**Via Files**:
```
See docs/ directory in repository or installation
```

---

## 🤖 AI Integration (MCP Server)

FlexPBX includes a **Model Context Protocol (MCP) server** for AI integration.

### Features

**20 Production Tools**:
- AMI Core (4 tools): status, channels, originate, hangup
- Conference Management (7 tools): list, participants, kick, mute, lock, etc.
- Extension Management (3 tools): list, status, registration
- Dial Plan (3 tools): rules, feature codes, validation
- Call Analytics (3 tools): CDR, statistics, summaries

### Quick Start

```bash
# Install MCP server
cd mcp-server
npm install

# Configure
cp .env.example .env
nano .env  # Add your AMI credentials

# Start server
npm start
```

### Claude Desktop Integration

Add to Claude Desktop config:
```json
{
  "mcpServers": {
    "flexpbx-voip": {
      "command": "node",
      "args": ["/path/to/flexpbx/mcp-server/src/index.js"],
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

See [`mcp-server/README.md`](mcp-server/README.md) for complete guide.

---

## 🔧 Development

### File Counts

- **PHP Files**: 259
- **JavaScript Files**: 10+
- **CSS Files**: 5+
- **SQL Files**: 2
- **Documentation**: 14 files
- **Total Lines**: 50,000+ (source) + 5,000+ (docs)

### Contributing

1. Fork the repository
2. Create feature branch (`git checkout -b feature/amazing-feature`)
3. Commit changes (`git commit -m 'Add amazing feature'`)
4. Push to branch (`git push origin feature/amazing-feature`)
5. Open Pull Request

### Code Structure

**Admin Panel** (`src/admin/`):
- MVC-like structure
- Session-based authentication
- Role-based access control

**API** (`src/api/`):
- RESTful endpoints
- JSON responses
- Database abstraction

**User Portal** (`src/user-portal/`):
- User-facing features
- Extension management
- Profile settings

---

## 🔐 Security

### Best Practices

- ✅ Use strong database passwords
- ✅ Enable HTTPS/SSL
- ✅ Configure firewall (ports 5060, 5061, 10000-20000)
- ✅ Restrict AMI access
- ✅ Enable fail2ban
- ✅ Regular security updates
- ✅ Change default admin credentials

### Default Credentials

**Admin Account**:
- Created during installation
- Set strong password
- Enable 2FA (if available)

**Database**:
- Configure unique credentials
- Restrict localhost-only access
- Use strong passwords

---

## 📞 Support & Contact

### Resources

- **Website**: https://flexpbx.devinecreations.net
- **Downloads**: https://flexpbx.devinecreations.net/downloads/
- **GitHub**: https://github.com/Raywonder/flexpbx
- **Documentation**: See `docs/` directory

### Contact

- **Email**: support@devine-creations.com
- **Phone**: (302) 313-9555
- **Issues**: GitHub Issues

---

## 📊 Statistics

### v1.4 Release

- **Source Files**: 275
- **Lines of Code**: 50,000+
- **Documentation**: 5,000+ lines (14 files)
- **Database Tables**: 17
- **API Endpoints**: 123
- **Admin Pages**: 81
- **User Portal Pages**: 38
- **MCP Tools**: 20

### Features

- **User Migration System**: ✅
- **Documentation Center**: ✅
- **Remote Streaming MOH**: ✅ (Free)
- **Department Management**: ✅
- **XMPP Messaging**: ✅
- **Mattermost Integration**: ✅
- **AI Integration (MCP)**: ✅

---

## 📜 License

FlexPBX is open source software. See LICENSE file for details.

---

## 🎉 Credits

**Developed by**: Devine Creations
**Website**: https://devine-creations.com | https://devinecreations.net
**Version**: 1.4
**Release Date**: November 9, 2025

**Built with**:
- Asterisk PBX
- PHP 8.x
- MariaDB
- JavaScript/jQuery
- Model Context Protocol (MCP)

**Free Streaming Provided by**:
- TappedIn Radio Network
- Raywonder Radio

---

## 🔗 Quick Links

- [Download Installers](https://flexpbx.devinecreations.net/downloads/)
- [Installation Guide](src/README.md)
- [User Migration Guide](docs/USER_MIGRATION_COMPLETE_GUIDE.md)
- [Documentation Index](docs/DOCUMENTATION_INDEX.md)
- [MCP Server Guide](mcp-server/README.md)
- [Database Schema](sql/complete-schema.sql)
- [Configuration Files](config/)

---

**⭐ Star this repository if you find FlexPBX useful!**

**🤝 Contributions welcome!**
