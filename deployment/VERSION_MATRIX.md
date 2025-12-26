# FlexPBX Version Matrix - Complete Release History

**Last Updated:** November 30, 2025

## Quick Version Comparison

| Version | Release Date | Size | Status | Use Case |
|---------|-------------|------|--------|----------|
| **1.4.2** | Nov 9, 2025 | 29 MB | **RECOMMENDED** | Production (All Features + Bug Fixes) |
| 1.4 | Nov 9, 2025 | 52 KB | Superseded | Replaced by 1.4.2 |
| 1.3 | Nov 6, 2025 | 29 MB | Stable | Enterprise Features |
| 1.2 | Nov 4, 2025 | 29 MB | Stable | SMS/Voice Providers |
| 1.1 | Oct 24, 2025 | 998 KB | Legacy | Early Release |
| 1.0-FULL | Oct 29, 2025 | 1.0 MB | Legacy | Complete DB Schema |
| 1.0 | Oct 24, 2025 | 910 KB | Legacy | Initial Release |

## Recommended Download

**For New Installations:** [FlexPBX v1.4.2](#version-142-recommended) - All features + critical bug fixes

**For Existing v1.4.0/v1.4.1 Users:** [Upgrade to v1.4.2](#version-142-recommended) - Simple file replacement, no DB changes

## Version 1.4.2 (RECOMMENDED)

### Release Information
- **Date:** November 9, 2025
- **Type:** Bug Fix / Stability Release
- **Size:** 29 MB
- **Status:** Production Ready
- **MD5:** `674025c4d54060b6742c5a79e2b6037f`
- **SHA256:** `a5845550277199a6cc1d5c400690609c2834dccab6bb7088b372c2a72f3ff17c`

### What's Included
- All features from v1.4, v1.3, v1.2, v1.1, v1.0
- Critical bug fixes for recordings page
- Complete CSS framework (400+ lines)
- Complete header/footer system (5 files, 1,046 lines)
- Helper functions library (133 lines)
- Secure recordings streaming system
- 100% test pass rate
- 0 errors remaining

### Critical Fixes
1. Recordings page error - FIXED
2. Missing CSS framework - FIXED
3. Missing header/footer files - FIXED
4. Missing helper functions - FIXED
5. Recordings streaming - ADDED

### Download
```bash
wget https://flexpbx.devinecreations.net/downloads/FlexPBX-Master-Server-v1.4.2.tar.gz
md5sum -c FlexPBX-Master-Server-v1.4.2.tar.gz.md5
```

### Documentation
- [README v1.4.2](README_v1.4.2.md)
- [CHANGELOG v1.4.2](CHANGELOG_v1.4.2.md) (included in package)
- [VERSION.txt](VERSION.txt) (included in package)

---

## Version 1.4

### Release Information
- **Date:** November 9, 2025
- **Type:** Feature Release
- **Size:** 52 KB
- **Status:** Superseded by v1.4.2
- **MD5:** `da6e248ce6de632b9c21325bb31f4224`
- **SHA256:** `d543e38168adf2760d6e8ad18dfe86ef5880648243a1b260ece0c08bc233ccee`

### New Features
- User Migration System
- Complete Documentation Center (14 documents)
- Remote Streaming MOH (TappedIn Radio Network)
- Department Management
- Migration History Tracking

### Known Issues
- Recordings page load errors (fixed in v1.4.2)
- Missing CSS framework (fixed in v1.4.2)
- Missing header/footer files (fixed in v1.4.2)

### Upgrade Path
**Recommended:** Upgrade directly to v1.4.2

---

## Version 1.3

### Release Information
- **Date:** November 6, 2025
- **Type:** Major Feature Release
- **Size:** 29 MB
- **Status:** Stable
- **MD5:** `495b824974f066de3e04b245113f2ebc`
- **SHA256:** `6ae673f64317feef74f4a1d48df1afb8533ce4b713951d74976585a1294c323c`

### Major Features
1. **Mattermost Channel Embedding** (6 tables, 6 files)
   - Real-time chat integration
   - User mapping system
   - Message caching
   - Notification tracking

2. **Announcements System** (3 tables, 3 files)
   - Template-based announcements
   - Role-based targeting
   - View tracking
   - 10 default templates

3. **Notifications System** (5 tables, 13 files)
   - Role-based notifications
   - Multi-channel delivery
   - User preferences
   - Template system
   - Analytics

4. **Help & Documentation System** (2 tables, 5 files)
   - ARIA-compliant tooltips
   - Context-sensitive help
   - Keyboard shortcuts
   - Search functionality
   - 10 default articles

5. **Legal Pages System** (1 table, 2 files)
   - Version-controlled documents
   - WYSIWYG editor
   - 3 default documents

6. **AI Training Manager**
   - Data source categorization
   - Training mode configuration

### Database Schema
- **New Tables:** 17 tables
- **Total Tables:** 50+ tables

### Download
```bash
wget https://flexpbx.devinecreations.net/downloads/FlexPBX-Master-Server-v1.3.tar.gz
md5sum -c FlexPBX-Master-Server-v1.3.tar.gz.md5
```

### Documentation
- [README v1.3](README_v1.3.md)
- [CHANGELOG v1.3](CHANGELOG_v1.3.md)
- [UPGRADE from v1.2 to v1.3](UPGRADE_v1.2_to_v1.3.md)

---

## Version 1.2

### Release Information
- **Date:** November 4, 2025
- **Type:** SMS & Voice Integration Release
- **Size:** 29 MB
- **Status:** Stable
- **MD5:** `b25afaaeb83eb8165d620f6f3f41b55d`
- **SHA256:** `99c6fcb6108bf3cd22d66d74ab999c46517d6c39cb722d356b461810398720fb`

### Major Features
1. **TextNow Full Integration**
   - Complete API implementation
   - Send/receive SMS
   - Voice calling
   - Number management

2. **Google Voice OAuth 2.0**
   - Secure authentication
   - SMS messaging
   - Call management

3. **Twilio Complete API**
   - Full API integration
   - SMS and voice
   - Number provisioning

4. **SMS Provider Manager**
   - Centralized management
   - AES-256 encryption
   - Multi-provider support

### Database Schema
- **New Tables:** 8 tables for SMS/Voice
- SMS providers, messages, templates, campaigns
- Voice providers, calls, credentials, logs

### Download
```bash
wget https://flexpbx.devinecreations.net/downloads/FlexPBX-Master-Server-v1.2.tar.gz
md5sum -c FlexPBX-Master-Server-v1.2.tar.gz.md5
```

### Documentation
- [README v1.2](README_v1.2.md)

---

## Version 1.1

### Release Information
- **Date:** October 24, 2025
- **Type:** Early Feature Release
- **Size:** 998 KB (Master), 15 KB (Client)
- **Status:** Legacy
- **MD5 (Master):** `873758e4b73f8e0256a83c89fff574c4`
- **MD5 (Client):** `636fc135e544bf49a2720c249ba81e99`

### Features
- Backup Queue Processor
- Module Repository API
- TextNow Integration (early)
- Network-aware Authentication
- Stay Logged In Feature

### Download
```bash
# Master Server
wget https://flexpbx.devinecreations.net/downloads/FlexPBX-Master-Server-v1.1.tar.gz

# Client
wget https://flexpbx.devinecreations.net/downloads/FlexPBX-Client-v1.1.tar.gz
```

---

## Version 1.0-FULL

### Release Information
- **Date:** October 29, 2025
- **Type:** Complete Database Version
- **Size:** 1.0 MB
- **Status:** Legacy
- **MD5:** `9c8f72a951d3f63e9aca8e41be3c3e05`

### Features
- **Complete 50-table database schema**
- Full FlexPBX functionality
- All modules included
- Production-ready deployment

### Use Case
Recommended for users who want the complete database structure without incremental upgrades.

### Download
```bash
wget https://flexpbx.devinecreations.net/downloads/FlexPBX-Master-Server-v1.0-FULL.tar.gz
```

---

## Version 1.0

### Release Information
- **Date:** October 24, 2025
- **Type:** Initial Release
- **Size:** 910 KB (Master), 14 KB (Client)
- **Status:** Legacy
- **MD5 (Master):** `f208207c297439b1efe3bbdda9d2168e`
- **MD5 (Client):** `692aed5853cc0257a393b0dccff7a881`

### Features
- Basic PBX functionality
- 8 core database tables
- Admin interface
- Extension management

---

## Feature Comparison Matrix

| Feature | v1.0 | v1.1 | v1.2 | v1.3 | v1.4 | v1.4.2 |
|---------|------|------|------|------|------|--------|
| Core PBX | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Admin Panel | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Extensions | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Call Records | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Module Repository | ❌ | ✅ | ✅ | ✅ | ✅ | ✅ |
| TextNow SMS | ❌ | Partial | ✅ | ✅ | ✅ | ✅ |
| Google Voice | ❌ | ❌ | ✅ | ✅ | ✅ | ✅ |
| Twilio | ❌ | ❌ | ✅ | ✅ | ✅ | ✅ |
| SMS Provider Manager | ❌ | ❌ | ✅ | ✅ | ✅ | ✅ |
| Mattermost Chat | ❌ | ❌ | ❌ | ✅ | ✅ | ✅ |
| Announcements | ❌ | ❌ | ❌ | ✅ | ✅ | ✅ |
| Notifications | ❌ | ❌ | ❌ | ✅ | ✅ | ✅ |
| Help System | ❌ | ❌ | ❌ | ✅ | ✅ | ✅ |
| Legal Pages | ❌ | ❌ | ❌ | ✅ | ✅ | ✅ |
| AI Training | ❌ | ❌ | ❌ | ✅ | ✅ | ✅ |
| User Migration | ❌ | ❌ | ❌ | ❌ | ✅ | ✅ |
| Documentation Center | ❌ | ❌ | ❌ | ❌ | ✅ | ✅ |
| Remote MOH | ❌ | ❌ | ❌ | ❌ | ✅ | ✅ |
| Department Mgmt | ❌ | ❌ | ❌ | ❌ | ✅ | ✅ |
| **Bug Fixes** | - | - | - | - | ❌ | ✅ |
| **CSS Framework** | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ |
| **Complete Headers** | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ |
| **Helper Functions** | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ |
| **Recordings Streaming** | ❌ | ❌ | ❌ | ❌ | ❌ | ✅ |

---

## Database Table Count

| Version | Core Tables | SMS/Voice | Chat | Notifications | Help | Legal | Other | **Total** |
|---------|------------|-----------|------|---------------|------|-------|-------|-----------|
| v1.0 | 8 | 0 | 0 | 0 | 0 | 0 | 0 | **8** |
| v1.1 | 8 | 0 | 0 | 0 | 0 | 0 | 2 | **10** |
| v1.2 | 8 | 8 | 0 | 0 | 0 | 0 | 2 | **18** |
| v1.3 | 8 | 8 | 6 | 5 | 2 | 1 | 5 | **35** |
| v1.4 | 8 | 8 | 6 | 5 | 2 | 1 | 12 | **42** |
| v1.4.2 | 8 | 8 | 6 | 5 | 2 | 1 | 12 | **42** |

---

## Upgrade Paths

### From v1.0 → v1.4.2
1. Upgrade to v1.1 (database changes)
2. Upgrade to v1.2 (SMS schema)
3. Upgrade to v1.3 (major schema changes)
4. Upgrade to v1.4.2 (file replacement only)

**OR**

1. Fresh install v1.4.2
2. Migrate data using User Migration System

### From v1.1 → v1.4.2
1. Upgrade to v1.2 (SMS schema)
2. Upgrade to v1.3 (major schema changes)
3. Upgrade to v1.4.2 (file replacement only)

### From v1.2 → v1.4.2
1. Upgrade to v1.3 (see UPGRADE_v1.2_to_v1.3.md)
2. Upgrade to v1.4.2 (file replacement only)

### From v1.3 → v1.4.2
1. Upgrade to v1.4 (database changes)
2. Upgrade to v1.4.2 (file replacement only)

### From v1.4.0/v1.4.1 → v1.4.2
**Simple file replacement - no database changes required**
```bash
tar -xzf FlexPBX-Master-Server-v1.4.2.tar.gz
service php-fpm reload
```

---

## Download Summary

### Current Recommended Versions

**Production:** v1.4.2 (29 MB)
- Download: https://flexpbx.devinecreations.net/downloads/FlexPBX-Master-Server-v1.4.2.tar.gz
- Checksums: [MD5](FlexPBX-Master-Server-v1.4.2.tar.gz.md5) | [SHA256](FlexPBX-Master-Server-v1.4.2.tar.gz.sha256)

**Client (if needed):** v1.4 Client (51 KB)
- Download: https://flexpbx.devinecreations.net/downloads/FlexPBX-Client-v1.4.tar.gz
- Checksums: [MD5](FlexPBX-Client-v1.4.tar.gz.md5) | [SHA256](FlexPBX-Client-v1.4.tar.gz.sha256)

### All Versions Available

| Package | Size | Download URL |
|---------|------|--------------|
| v1.4.2 Master | 29 MB | [Download](https://flexpbx.devinecreations.net/downloads/FlexPBX-Master-Server-v1.4.2.tar.gz) |
| v1.4 Master | 52 KB | [Download](https://flexpbx.devinecreations.net/downloads/FlexPBX-Master-Server-v1.4.tar.gz) |
| v1.4 Client | 51 KB | [Download](https://flexpbx.devinecreations.net/downloads/FlexPBX-Client-v1.4.tar.gz) |
| v1.3 Master | 29 MB | [Download](https://flexpbx.devinecreations.net/downloads/FlexPBX-Master-Server-v1.3.tar.gz) |
| v1.2 Master | 29 MB | [Download](https://flexpbx.devinecreations.net/downloads/FlexPBX-Master-Server-v1.2.tar.gz) |
| v1.1 Master | 998 KB | [Download](https://flexpbx.devinecreations.net/downloads/FlexPBX-Master-Server-v1.1.tar.gz) |
| v1.1 Client | 15 KB | [Download](https://flexpbx.devinecreations.net/downloads/FlexPBX-Client-v1.1.tar.gz) |
| v1.0-FULL | 1.0 MB | [Download](https://flexpbx.devinecreations.net/downloads/FlexPBX-Master-Server-v1.0-FULL.tar.gz) |
| v1.0 Master | 910 KB | [Download](https://flexpbx.devinecreations.net/downloads/FlexPBX-Master-Server-v1.0.tar.gz) |
| v1.0 Client | 14 KB | [Download](https://flexpbx.devinecreations.net/downloads/FlexPBX-Client-v1.0.tar.gz) |

---

## Support & Documentation

- **Main Site:** https://flexpbx.devinecreations.net
- **Documentation:** https://docs.flexpbx.com
- **Email Support:** support@devine-creations.com
- **Bug Tracker:** https://flexpbx.devinecreations.net/admin/bug-tracker.php
- **GitHub:** https://github.com/flexpbx

---

**FlexPBX** - Professional Communication Platform
Copyright 2025 Devine Creations
