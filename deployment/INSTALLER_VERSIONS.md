# FlexPBX Installer Versions

## Current Installers (October 29, 2025)

### Master Server Installers

**FlexPBX-Master-Server-v1.0-FULL.tar.gz** ⭐ **RECOMMENDED**
- **Size:** 1.0MB
- **Database:** 50 tables (complete schema)
- **Features:** Full FlexPBX with all modules
- **Date:** October 29, 2025
- **Use For:** Production deployments, Ubuntu VPS testing
- **Tables Include:** users, extensions, SMS, contacts, API, modules, etc.
- **✅ Full restore support**
- **✅ Complete database schema included**

**FlexPBX-Master-Server-v1.1.tar.gz** (Legacy)
- **Size:** 998KB
- **Database:** 8 tables only (incomplete)
- **Date:** October 24, 2025
- **Status:** Superseded by v1.0-FULL
- **⚠️ Limited functionality**

**FlexPBX-Master-Server-v1.0.tar.gz** (Legacy)
- **Size:** 910KB
- **Database:** 8 tables only (incomplete)
- **Date:** October 24, 2025
- **Status:** Superseded by v1.0-FULL
- **⚠️ Limited functionality**

### Client Server Installers

**FlexPBX-Client-v1.1.tar.gz**
- **Size:** 15KB
- **Type:** Lightweight sync client
- **Date:** October 24, 2025
- **Use For:** Remote installations that sync with master

**FlexPBX-Client-v1.0.tar.gz**
- **Size:** 14KB
- **Type:** Lightweight sync client (older)
- **Date:** October 24, 2025

---

## Which Installer To Use?

### For New Installations:
**Use:** FlexPBX-Master-Server-v1.0-FULL.tar.gz
- Complete 50-table database
- All features included
- Production-ready

### For Client Servers:
**Use:** FlexPBX-Client-v1.1.tar.gz
- Connects to master for updates
- Minimal footprint

### For Remote Deployment Module:
**Use:** FlexPBX-Master-Server-v1.0-FULL.tar.gz
- The Remote Deployment Module automatically uses this
- Uploads all 247+ files
- Creates complete database structure

---

## Version Differences

| Feature | v1.0 (old) | v1.1 | v1.0-FULL ⭐ |
|---------|-----------|------|--------------|
| Database Tables | 8 | 8 | 50 |
| Admin Login | ❌ Incomplete | ❌ Incomplete | ✅ Full |
| SMS APIs | ❌ Missing | ❌ Missing | ✅ Included |
| Contacts | ❌ Missing | ❌ Missing | ✅ Included |
| Call Queues | ❌ Missing | ❌ Missing | ✅ Included |
| Module Repository | ❌ Missing | ✅ Included | ✅ Included |
| Full Restore | ❌ No | ❌ No | ✅ Yes |
| Size | 910KB | 998KB | 1.0MB |

---

## Download URLs

```
# Recommended (Full Version)
https://flexpbx.devinecreations.net/downloads/FlexPBX-Master-Server-v1.0-FULL.tar.gz

# Client Version
https://flexpbx.devinecreations.net/downloads/FlexPBX-Client-v1.1.tar.gz
```

---

## Database Schema Comparison

### v1.0 and v1.1 (8 tables):
1. users
2. extensions
3. call_records
4. sip_trunks
5. system_config
6. system_logs
7. api_keys
8. backups

### v1.0-FULL (50 tables):
All 8 above PLUS:
- User management (user_dids, user_notification_preferences, etc.)
- Extensions (extension_features, extension_phone_numbers)
- Call handling (call_queues, call_history, queue_members, etc.)
- SMS (sms_messages, sms_templates)
- Contacts (contacts, contact_groups, contact_phone_numbers)
- Devices (devices, desktop_clients)
- API security (authorized_links, connection_limits, active_connections)
- FlexPBX clients (flexpbx_clients, flexpbx_client_activity, etc.)
- Parking (parking_lots, parking_history)
- Ring groups (ring_groups, ring_group_members)
- Conferences
- Calendar events
- And 28 more tables...

---

## Installation Instructions

### Master Server (Full):

```bash
# Download
cd ~/public_html
wget https://flexpbx.devinecreations.net/downloads/FlexPBX-Master-Server-v1.0-FULL.tar.gz

# Extract
tar -xzf FlexPBX-Master-Server-v1.0-FULL.tar.gz
mv test-extract flexpbx

# Navigate to installer
https://your-domain.com/flexpbx/api/install.php
```

### Client Server:

```bash
# Download
wget https://flexpbx.devinecreations.net/downloads/FlexPBX-Client-v1.1.tar.gz

# Extract
tar -xzf FlexPBX-Client-v1.1.tar.gz

# Configure
nano config.php
# Set master_server_url and api_key
```

---

## Checksums

All installer packages include:
- `.md5` - MD5 checksum file
- `.sha256` - SHA256 checksum file

Verify integrity:
```bash
md5sum -c FlexPBX-Master-Server-v1.0-FULL.tar.gz.md5
sha256sum -c FlexPBX-Master-Server-v1.0-FULL.tar.gz.sha256
```

---

## Remote Deployment Module

The **Remote Deployment Module** (v1.0.0) automatically uses the correct installer:

- For master deployments: FlexPBX-Master-Server-v1.0-FULL.tar.gz
- For client deployments: FlexPBX-Client-v1.1.tar.gz

No manual selection needed - the module picks the right one based on installation type.

---

**Last Updated:** October 29, 2025
**Recommended:** FlexPBX-Master-Server-v1.0-FULL.tar.gz (1.0MB, 50 tables)
