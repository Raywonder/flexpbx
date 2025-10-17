# FlexPBX Admin UI Architecture - Complete Asterisk Integration

**Created:** October 16, 2025
**Status:** Master UI Design Document
**Goal:** 100% Asterisk Feature Coverage via FlexPBX UI

---

## Executive Summary

This document defines the complete admin UI structure for FlexPBX, ensuring **NOTHING is left out** of Asterisk management. Every feature accessible via CLI or config files will have a UI equivalent.

---

## UI Organization Structure

### Navigation Categories

```
FlexPBX Admin Dashboard
├── 📞 PBX Core
│   ├── Extensions Management
│   ├── Trunks Management
│   ├── Inbound Routing
│   ├── Outbound Routing
│   └── Dialplan Editor
├── 🔊 Call Features
│   ├── Voicemail Management
│   ├── Feature Codes
│   ├── Call Recording
│   ├── Music on Hold
│   └── Call Parking
├── 📋 Advanced PBX
│   ├── Call Queues (ACD)
│   ├── Conference Bridges
│   ├── IVR Builder
│   ├── Ring Groups
│   ├── Follow Me / Find Me
│   └── Time Conditions
├── 📊 Monitoring & Logs
│   ├── Live Dashboard
│   ├── Call Logs (CDR)
│   ├── Active Calls
│   ├── Extension Status
│   └── System Health
├── 🔧 System & Config
│   ├── System Settings
│   ├── Network & SIP
│   ├── Security & Firewall
│   ├── Backup & Restore
│   ├── API Keys
│   └── Asterisk CLI
├── 📁 Media & Audio
│   ├── Audio Upload Manager
│   ├── Media Manager
│   ├── Sound Prompts
│   └── MOH Streaming
└── 👥 Users & Access
    ├── User Roles
    ├── Account Settings
    ├── Notification Settings
    └── Bug Tracker
```

---

## Detailed Feature Mapping

### 1. PBX Core Section

#### 1.1 Extensions Management
**File:** `/admin/admin-extensions-management.html` (UPDATE EXISTING)
**API:** `/api/extensions.php`
**Features:**
- ✅ List all PJSIP endpoints
- ✅ Create new extensions
- ✅ Edit extension settings
- ✅ Delete extensions
- ✅ View registration status
- ✅ Configure voicemail
- ✅ Set call forwarding
- ✅ Configure call waiting
- ✅ Set DND (Do Not Disturb)
- 🆕 Bulk extension creation (CSV import)
- 🆕 Extension templates
- 🆕 Password generator
- 🆕 Extension cloning

**Current Status:** Exists but uses old API
**Action Required:** Update to use `/api/extensions.php?path=...`

#### 1.2 Trunks Management
**File:** `/admin/admin-trunks-management.html` (UPDATE EXISTING)
**API:** `/api/trunks.php`
**Features:**
- ✅ List all SIP trunks
- ✅ Create SIP trunk
- ✅ Edit trunk settings
- ✅ Delete trunk
- ✅ Test trunk registration
- ✅ View trunk status
- 🆕 Trunk failover configuration
- 🆕 Trunk load balancing
- 🆕 Trunk monitoring graphs
- 🆕 Automatic trunk testing

**Current Status:** Exists but uses old API
**Action Required:** Update to use `/api/trunks.php?path=...`

#### 1.3 Inbound Routing
**File:** `/admin/inbound-routing.html` (UPDATE EXISTING)
**API:** `/api/inbound-routing.php` (NEW - TO BE CREATED)
**Features:**
- ✅ List DID routes
- ✅ Create inbound route
- ✅ Edit route
- ✅ Delete route
- ✅ Route to extension
- ✅ Route to IVR
- ✅ Route to queue
- ✅ Route to conference
- ✅ Route to voicemail
- 🆕 Time-based routing
- 🆕 Caller ID-based routing
- 🆕 Route priority/ordering
- 🆕 Route templates

**Current Status:** Exists but limited functionality
**Action Required:** Create comprehensive API, update UI

#### 1.4 Outbound Routing
**File:** `/admin/outbound-routing.html` (NEW - TO BE CREATED)
**API:** `/api/outbound-routing.php` (NEW - TO BE CREATED)
**Features:**
- 🆕 Route patterns (dial plan rules)
- 🆕 Trunk selection (primary/failover)
- 🆕 Caller ID manipulation
- 🆕 Prefix/strip digits
- 🆕 Emergency routing (911, etc.)
- 🆕 International dialing rules
- 🆕 Least cost routing
- 🆕 Route testing tool

**Current Status:** Does not exist
**Action Required:** Create API and UI from scratch

#### 1.5 Dialplan Editor
**File:** `/admin/dialplan-editor.html` (NEW - TO BE CREATED)
**API:** `/api/dialplan.php` (NEW - TO BE CREATED)
**Features:**
- 🆕 Visual dialplan editor
- 🆕 Code editor mode (extensions.conf)
- 🆕 Syntax highlighting
- 🆕 Syntax validation
- 🆕 Context management
- 🆕 Include files management
- 🆕 Variables editor
- 🆕 Live reload
- 🆕 Dialplan backup/restore
- 🆕 Search/replace
- 🆕 Comments management

**Current Status:** Does not exist
**Action Required:** Build from scratch - HIGH PRIORITY

---

### 2. Call Features Section

#### 2.1 Voicemail Management
**File:** `/admin/voicemail-manager.html` (UPDATE EXISTING)
**API:** `/api/voicemail.php` (ALREADY CREATED)
**Features:**
- ✅ List all mailboxes
- ✅ Create mailbox
- ✅ Edit mailbox settings
- ✅ Delete mailbox
- ✅ Set PIN
- ✅ Configure email notifications
- ✅ Greeting management
- ✅ Message playback/download
- 🆕 Bulk mailbox creation
- 🆕 Voicemail transcription (future)
- 🆕 Voicemail to email (MP3)
- 🆕 Message forwarding

**Current Status:** Exists, API already created
**Action Required:** Update UI to use new `/api/voicemail.php`

#### 2.2 Feature Codes
**File:** `/admin/feature-codes-manager.html` (UPDATE EXISTING)
**API:** `/api/feature-codes.php` (NEW - TO BE CREATED)
**Features:**
- ✅ List all feature codes
- ✅ Enable/disable codes
- ✅ Customize code numbers
- 🆕 Code categories (transfer, parking, DND, etc.)
- 🆕 Custom code creation
- 🆕 Code conflict detection
- 🆕 Usage statistics per code

**Feature Codes to Manage:**
- *43 - Echo test
- *45 - Speaking clock
- *46 - Speaking extension number
- *65 - Call park
- *66 - Park pickup
- *67 - Phone intercom
- *68 - Phone intercom (prefix)
- *97 - Voicemail
- *98 - Voicemail (login)
- *70 - Call waiting on
- *71 - Call waiting off
- *72 - Call forward all
- *73 - Call forward cancel
- *74 - Call forward busy
- *75 - Call forward no answer
- *76 - DND activate
- *77 - DND deactivate
- *90 - Call recording on
- *91 - Call recording off

**Current Status:** Exists but basic
**Action Required:** Create API, enhance UI

#### 2.3 Call Recording
**File:** `/admin/call-recording.html` (NEW - TO BE CREATED)
**API:** `/api/recordings.php` (NEW - TO BE CREATED)
**Features:**
- 🆕 List all recordings
- 🆕 Search recordings (date, extension, caller ID)
- 🆕 Play recordings (in-browser)
- 🆕 Download recordings
- 🆕 Delete recordings
- 🆕 Recording policies (always/on-demand/never)
- 🆕 Per-extension recording settings
- 🆕 Per-trunk recording settings
- 🆕 Automatic recording cleanup
- 🆕 Storage usage monitoring
- 🆕 Recording format selection (WAV/MP3)
- 🆕 Recording encryption

**Current Status:** Does not exist
**Action Required:** Build from scratch - MEDIUM PRIORITY

#### 2.4 Music on Hold (MOH)
**File:** `/admin/moh-manager.html` (UPDATE EXISTING)
**API:** `/api/moh.php` (NEW - TO BE CREATED)
**Features:**
- ✅ List MOH classes
- ✅ Streaming configuration (Icecast/Shoutcast)
- ✅ File-based MOH
- 🆕 Create MOH class
- 🆕 Upload MOH files
- 🆕 Volume control per class
- 🆕 Random vs sequential playback
- 🆕 MOH testing/preview
- 🆕 Default class assignment

**Current Status:** Exists but needs API
**Action Required:** Create API, update UI

#### 2.5 Call Parking
**File:** `/admin/call-parking.html` (NEW - TO BE CREATED)
**API:** `/api/parking.php` (NEW - TO BE CREATED)
**Features:**
- 🆕 Configure parking lots
- 🆕 Set parking extensions (700-720, etc.)
- 🆕 Parking timeout settings
- 🆕 Return to parker vs destination
- 🆕 Parking lot monitoring
- 🆕 Active parked calls view
- 🆕 Parking announcement settings

**Current Status:** Does not exist
**Action Required:** Build from scratch - LOW PRIORITY

---

### 3. Advanced PBX Section

#### 3.1 Call Queues (ACD)
**File:** `/admin/queues-manager.html` (NEW - TO BE CREATED)
**API:** `/api/queues.php` (NEW - TO BE CREATED)
**Features:**
- 🆕 List all queues
- 🆕 Create queue
- 🆕 Edit queue settings
- 🆕 Delete queue
- 🆕 Add/remove queue members
- 🆕 Agent management (login/logout)
- 🆕 Queue strategy (ringall, leastrecent, fewestcalls, random, rrmemory)
- 🆕 Max wait time
- 🆕 Queue announcement
- 🆕 Music on hold for queue
- 🆕 Caller position announcements
- 🆕 Queue statistics (real-time)
- 🆕 Agent performance metrics
- 🆕 Call overflow handling
- 🆕 Queue penalties

**Current Status:** Does not exist
**Action Required:** Build from scratch - HIGH PRIORITY

#### 3.2 Conference Bridges
**File:** `/admin/conferences-manager.html` (NEW - TO BE CREATED)
**API:** `/api/conferences.php` (NEW - TO BE CREATED)
**Features:**
- 🆕 List all conference rooms
- 🆕 Create conference
- 🆕 Edit conference settings
- 🆕 Delete conference
- 🆕 PIN protection
- 🆕 Admin PIN (moderator access)
- 🆕 Max participants
- 🆕 Conference recording
- 🆕 Mute on entry
- 🆕 Entry/exit announcements
- 🆕 Conference monitoring (active participants)
- 🆕 Kick participant
- 🆕 Mute/unmute participants
- 🆕 Conference templates

**Current Status:** Does not exist
**Action Required:** Build from scratch - HIGH PRIORITY

#### 3.3 IVR Builder
**File:** `/admin/ivr-builder.html` (NEW - TO BE CREATED)
**API:** `/api/ivr.php` (NEW - TO BE CREATED)
**Features:**
- 🆕 Visual IVR flow designer (drag-drop)
- 🆕 IVR menu creation
- 🆕 Multi-level IVR support
- 🆕 DTMF option mapping (1-9, 0, *, #)
- 🆕 Timeout handling
- 🆕 Invalid entry handling
- 🆕 Audio prompt assignment
- 🆕 Directory lookup (dial by name)
- 🆕 Business hours integration
- 🆕 IVR statistics (option selection)
- 🆕 IVR testing mode
- 🆕 Export/import IVR flows

**Current Status:** Does not exist
**Action Required:** Build from scratch - HIGH PRIORITY

#### 3.4 Ring Groups
**File:** `/admin/ring-groups.html` (NEW - TO BE CREATED)
**API:** `/api/ring-groups.php` (NEW - TO BE CREATED)
**Features:**
- 🆕 List ring groups
- 🆕 Create ring group
- 🆕 Edit ring group
- 🆕 Delete ring group
- 🆕 Add/remove members
- 🆕 Ring strategy (simultaneous, sequential, round-robin)
- 🆕 Ring timeout per member
- 🆕 Caller ID name prefix
- 🆕 Destination if no answer
- 🆕 Ring group announcement

**Current Status:** Does not exist
**Action Required:** Build from scratch - MEDIUM PRIORITY

#### 3.5 Follow Me / Find Me
**File:** `/admin/follow-me.html` (NEW - TO BE CREATED)
**API:** `/api/follow-me.php` (NEW - TO BE CREATED)
**Features:**
- 🆕 Per-extension follow-me rules
- 🆕 Multiple destination numbers
- 🆕 Sequential or simultaneous ring
- 🆕 Ring duration per destination
- 🆕 External number support (mobile, etc.)
- 🆕 Caller screening (accept/reject)
- 🆕 Announcement before connect
- 🆕 Schedule-based follow-me

**Current Status:** Does not exist
**Action Required:** Build from scratch - MEDIUM PRIORITY

#### 3.6 Time Conditions
**File:** `/admin/time-conditions.html` (NEW - TO BE CREATED)
**API:** `/api/time-conditions.php` (NEW - TO BE CREATED)
**Features:**
- 🆕 Business hours configuration
- 🆕 Holiday schedules
- 🆕 Time-based routing rules
- 🆕 Multiple time groups
- 🆕 Override controls (force open/closed)
- 🆕 Timezone support
- 🆕 Time condition testing
- 🆕 Calendar import (holidays)
- 🆕 Destination during business hours
- 🆕 Destination outside business hours

**Current Status:** Does not exist
**Action Required:** Build from scratch - HIGH PRIORITY

---

### 4. Monitoring & Logs Section

#### 4.1 Live Dashboard
**File:** `/admin/dashboard-live.php` (ALREADY CREATED)
**API:** Multiple APIs (system.php, call-logs.php, extensions.php)
**Features:**
- ✅ Active calls counter
- ✅ Total extensions
- ✅ Today's call stats
- ✅ System health
- ✅ CPU/Memory/Disk usage
- ✅ Recent calls
- ✅ Extension status
- ✅ API keys tracking
- 🆕 Active queue calls
- 🆕 Active conference rooms
- 🆕 Trunk status
- 🆕 WebSocket real-time updates

**Current Status:** Complete
**Action Required:** Add missing real-time features

#### 4.2 Call Logs (CDR)
**File:** `/admin/call-logs.html` (NEW - TO BE CREATED)
**API:** `/api/call-logs.php` (ALREADY CREATED)
**Features:**
- ✅ Recent calls
- ✅ Call statistics
- ✅ Search/filter
- ✅ Export to CSV
- 🆕 Date range selection
- 🆕 Advanced filters (extension, trunk, disposition)
- 🆕 Call details popup
- 🆕 Recording playback integration
- 🆕 Billing reports
- 🆕 Traffic analysis

**Current Status:** API exists, need UI page
**Action Required:** Create dedicated UI page

#### 4.3 Active Calls
**File:** `/admin/active-calls.html` (NEW - TO BE CREATED)
**API:** `/api/active-calls.php` (NEW - TO BE CREATED)
**Features:**
- 🆕 Real-time active calls view
- 🆕 Call details (from, to, duration, channel)
- 🆕 Call control (hangup)
- 🆕 Call transfer (blind/attended)
- 🆕 Call recording start/stop
- 🆕 Listen/whisper/barge features
- 🆕 Channel spy
- 🆕 Auto-refresh

**Current Status:** Does not exist
**Action Required:** Build from scratch - MEDIUM PRIORITY

#### 4.4 Extension Status
**File:** Part of live dashboard, could be standalone
**API:** `/api/extensions.php?path=status`
**Features:**
- ✅ All extensions list
- ✅ Registration status
- ✅ IP addresses
- 🆕 User agent info
- 🆕 Last registration time
- 🆕 Call history per extension
- 🆕 Extension diagnostics

**Current Status:** Partially in dashboard
**Action Required:** Enhance or create dedicated page

#### 4.5 System Health
**File:** Part of live dashboard
**API:** `/api/system.php?path=health`
**Features:**
- ✅ Overall system status
- ✅ Service status (Asterisk, coturn, fail2ban)
- ✅ Resource usage
- 🆕 Database health
- 🆕 Disk space alerts
- 🆕 Network connectivity checks
- 🆕 Certificate expiration warnings
- 🆕 Asterisk module status

**Current Status:** In dashboard
**Action Required:** Expand monitoring capabilities

---

### 5. System & Config Section

#### 5.1 System Settings
**File:** `/admin/system-settings.php` (EXISTS)
**API:** `/api/system.php`
**Features:**
- ✅ Asterisk integration mode
- 🆕 General settings (system name, timezone)
- 🆕 Email server settings (SMTP)
- 🆕 Default language/locale
- 🆕 Date/time format
- 🆕 CDR retention policy
- 🆕 Recording retention policy
- 🆕 Voicemail storage settings
- 🆕 API rate limiting
- 🆕 Debug/logging levels

**Current Status:** Basic settings exist
**Action Required:** Expand with comprehensive settings

#### 5.2 Network & SIP
**File:** `/admin/network-sip-settings.html` (NEW - TO BE CREATED)
**API:** `/api/network.php` (NEW - TO BE CREATED)
**Features:**
- 🆕 Local network configuration
- 🆕 External IP/hostname
- 🆕 STUN server configuration
- 🆕 RTP port range
- 🆕 SIP ports (5060, 5061)
- 🆕 TLS certificate management
- 🆕 NAT settings
- 🆕 ICE support
- 🆕 PJSIP global settings
- 🆕 Transport configuration
- 🆕 Codec preferences (global)
- 🆕 Network diagnostics

**Current Status:** Does not exist (configs in files)
**Action Required:** Build from scratch - HIGH PRIORITY

#### 5.3 Security & Firewall
**File:** `/admin/security-settings.html` (NEW - TO BE CREATED)
**API:** `/api/security.php` (NEW - TO BE CREATED)
**Features:**
- 🆕 Fail2ban status and configuration
- 🆕 Banned IPs list
- 🆕 Unban IP tool
- 🆕 Whitelist management
- 🆕 Firewall rules (CSF/iptables)
- 🆕 Port management
- 🆕 SIP security settings (permit/deny)
- 🆕 Extension security (strong passwords)
- 🆕 Login attempt monitoring
- 🆕 Security alerts/notifications
- 🆕 Intrusion detection logs

**Current Status:** Does not exist
**Action Required:** Build from scratch - HIGH PRIORITY

#### 5.4 Backup & Restore
**File:** Part of system API, needs UI
**API:** `/api/system.php?path=backup` (EXISTS)
**Features:**
- ✅ Create backup (.flx format)
- ✅ Restore from backup
- 🆕 Scheduled backups
- 🆕 Backup to remote location (FTP, S3)
- 🆕 Backup encryption
- 🆕 Selective backup (configs only, voicemail, recordings)
- 🆕 Backup verification
- 🆕 Restore preview
- 🆕 Backup retention policy
- 🆕 One-click full system backup

**Current Status:** API exists, need UI
**Action Required:** Create comprehensive UI

#### 5.5 API Keys
**File:** Part of live dashboard, needs dedicated page
**API:** `/api/api-keys.php` (NEW - TO BE CREATED)
**Features:**
- ✅ List all API keys (in dashboard)
- 🆕 Generate new API key
- 🆕 Revoke API key
- 🆕 Edit key permissions
- 🆕 Set key expiration
- 🆕 Set rate limits per key
- 🆕 Usage statistics per key
- 🆕 Key source tracking (HubNode vs FlexPBX)
- 🆕 Per-user key management
- 🆕 API documentation link

**Current Status:** Partially in dashboard
**Action Required:** Create dedicated management page

#### 5.6 Asterisk CLI
**File:** `/admin/asterisk-cli.html` (NEW - TO BE CREATED)
**API:** `/api/asterisk-cli.php` (NEW - TO BE CREATED)
**Features:**
- 🆕 Web-based terminal emulator
- 🆕 Execute Asterisk commands
- 🆕 Real-time output
- 🆕 Command history
- 🆕 Auto-complete
- 🆕 Favorite commands
- 🆕 Multi-line commands
- 🆕 Output export
- 🆕 Permission-based access (superadmin only)

**Current Status:** Does not exist
**Action Required:** Build from scratch - MEDIUM PRIORITY

---

### 6. Media & Audio Section

#### 6.1 Audio Upload Manager
**File:** `/admin/audio-upload.php` (EXISTS)
**API:** Built-in upload handling
**Features:**
- ✅ Upload audio files
- ✅ Format conversion
- 🆕 Bulk upload
- 🆕 URL import
- 🆕 Audio preview
- 🆕 Waveform visualization
- 🆕 Audio editing (trim, volume)

**Current Status:** Basic upload exists
**Action Required:** Enhance with advanced features

#### 6.2 Media Manager
**File:** `/admin/media-manager.html` (EXISTS)
**API:** File browser API
**Features:**
- ✅ Browse media files
- ✅ Organize files
- 🆕 Folder structure
- 🆕 Search files
- 🆕 Preview audio
- 🆕 Delete files
- 🆕 Rename files
- 🆕 File metadata

**Current Status:** Exists
**Action Required:** Minor enhancements

#### 6.3 Sound Prompts
**File:** `/admin/sound-prompts.html` (NEW - TO BE CREATED)
**API:** `/api/prompts.php` (NEW - TO BE CREATED)
**Features:**
- 🆕 System prompts library
- 🆕 Custom prompts
- 🆕 TTS (text-to-speech) generation
- 🆕 Multi-language support
- 🆕 Prompt categories
- 🆕 Prompt usage tracking (where used)
- 🆕 Replace system prompts

**Current Status:** Does not exist
**Action Required:** Build from scratch - LOW PRIORITY

#### 6.4 MOH Streaming
**File:** Part of MOH manager
**API:** `/api/moh.php`
**Features:**
- ✅ Configure streaming sources
- ✅ Icecast/Shoutcast URLs
- ✅ Volume control
- 🆕 Stream health monitoring
- 🆕 Fallback to files
- 🆕 Multiple stream sources
- 🆕 Stream testing

**Current Status:** Exists in MOH manager
**Action Required:** Integrate with new API

---

### 7. Users & Access Section

#### 7.1 User Roles
**File:** `/admin/user-roles.html` (NEW - TO BE CREATED)
**API:** `/api/roles.php` (ALREADY CREATED)
**Features:**
- 🆕 List all roles
- 🆕 Create custom role
- 🆕 Edit role permissions
- 🆕 Delete role
- 🆕 Permission matrix
- 🆕 Assign users to roles
- 🆕 Role templates
- 🆕 Platform sync status (WordPress, Composr, WHMCS)

**Roles:**
- Superadmin (full access)
- Admin (most access, no system changes)
- Manager (PBX management, no system)
- Support (view only, limited changes)
- Developer (API access, testing)
- User (own extension only)
- Guest (read-only, limited)

**Current Status:** API exists, no UI
**Action Required:** Create UI for role management

#### 7.2 Account Settings
**File:** `/admin/change-password.php`, `/admin/setup-email.php`, `/admin/link-extension.php` (EXIST)
**API:** `/api/account.php` (NEW - TO BE CREATED)
**Features:**
- ✅ Change password
- ✅ Update email
- ✅ Link extension
- 🆕 Profile picture
- 🆕 Display name
- 🆕 Timezone preference
- 🆕 Language preference
- 🆕 Two-factor authentication
- 🆕 API key generation (user level)

**Current Status:** Basic account management exists
**Action Required:** Consolidate and enhance

#### 7.3 Notification Settings
**File:** `/admin/notification-settings.php` (EXISTS)
**API:** `/api/notifications.php` (NEW - TO BE CREATED)
**Features:**
- ✅ Push notification settings
- ✅ Email alert settings
- 🆕 SMS notifications
- 🆕 Notification preferences per event type
- 🆕 Quiet hours
- 🆕 Notification history
- 🆕 Test notifications

**Current Status:** Exists
**Action Required:** Create API backend

#### 7.4 Bug Tracker
**File:** `/admin/bug-tracker.php` (EXISTS)
**API:** `/api/bugs.php` (NEW - TO BE CREATED)
**Features:**
- ✅ Submit bug reports
- ✅ View bugs
- ✅ Role-based access
- 🆕 Bug status updates
- 🆕 Assign bugs
- 🆕 Bug priorities
- 🆕 Attachments
- 🆕 Comments/discussion

**Current Status:** Basic tracker exists
**Action Required:** Create API and enhance UI

---

## Missing APIs to Create

### High Priority APIs

1. **`/api/inbound-routing.php`** - Comprehensive inbound route management
2. **`/api/outbound-routing.php`** - Outbound dial plans
3. **`/api/dialplan.php`** - Dialplan editor backend
4. **`/api/queues.php`** - Call queue management
5. **`/api/conferences.php`** - Conference bridge management
6. **`/api/ivr.php`** - IVR builder backend
7. **`/api/time-conditions.php`** - Business hours and schedules
8. **`/api/network.php`** - Network and SIP settings
9. **`/api/security.php`** - Security and firewall management

### Medium Priority APIs

10. **`/api/feature-codes.php`** - Feature code management
11. **`/api/recordings.php`** - Call recording management
12. **`/api/moh.php`** - Music on hold API
13. **`/api/ring-groups.php`** - Ring group management
14. **`/api/follow-me.php`** - Follow-me routing
15. **`/api/parking.php`** - Call parking
16. **`/api/active-calls.php`** - Real-time call monitoring
17. **`/api/asterisk-cli.php`** - CLI command execution
18. **`/api/api-keys.php`** - API key CRUD operations

### Low Priority APIs

19. **`/api/prompts.php`** - Sound prompt management
20. **`/api/notifications.php`** - Notification backend
21. **`/api/bugs.php`** - Bug tracker backend
22. **`/api/account.php`** - User account management

---

## Missing UI Pages to Create

### High Priority UIs

1. **`/admin/outbound-routing.html`** - Outbound dial plan configuration
2. **`/admin/dialplan-editor.html`** - Visual + code dialplan editor
3. **`/admin/queues-manager.html`** - Call queue management
4. **`/admin/conferences-manager.html`** - Conference bridge management
5. **`/admin/ivr-builder.html`** - Visual IVR builder
6. **`/admin/time-conditions.html`** - Business hours and schedules
7. **`/admin/network-sip-settings.html`** - Network and SIP configuration
8. **`/admin/security-settings.html`** - Security and firewall UI
9. **`/admin/call-logs.html`** - Dedicated call logs page

### Medium Priority UIs

10. **`/admin/call-recording.html`** - Recording management
11. **`/admin/ring-groups.html`** - Ring group configuration
12. **`/admin/follow-me.html`** - Follow-me routing
13. **`/admin/active-calls.html`** - Real-time call monitoring
14. **`/admin/asterisk-cli.html`** - Web-based Asterisk CLI
15. **`/admin/user-roles.html`** - Role management UI
16. **`/admin/backup-restore.html`** - Backup and restore UI
17. **`/admin/api-keys-manager.html`** - API key management

### Low Priority UIs

18. **`/admin/call-parking.html`** - Call parking configuration
19. **`/admin/sound-prompts.html`** - Sound prompt library

---

## UI Updates Required for Existing Pages

### Update API Integration (Old → New)

1. **`/admin/admin-extensions-management.html`**
   - Change from: `/api/extensions/${id}`
   - Change to: `/api/extensions.php?path=details&id=${id}`

2. **`/admin/admin-trunks-management.html`**
   - Change from: Old trunk API
   - Change to: `/api/trunks.php?path=...`

3. **`/admin/voicemail-manager.html`**
   - Change from: Unknown API
   - Change to: `/api/voicemail.php?path=...`

4. **`/admin/inbound-routing.html`**
   - Enhance with new API features
   - Add time conditions, IVR routing, queue routing

---

## Implementation Roadmap

### Phase 1: Connect Existing UIs (1-2 weeks)
**Goal:** Update all existing UIs to use new comprehensive APIs

1. Update Extensions Management → `/api/extensions.php`
2. Update Trunks Management → `/api/trunks.php`
3. Update Voicemail Manager → `/api/voicemail.php`
4. Create Call Logs UI → `/api/call-logs.php`
5. Add URL rewriting for compatibility (if needed)

**Testing:**
- Verify all CRUD operations work
- Check registration status updates
- Test voicemail features
- Validate call log queries

### Phase 2: Core PBX Features (2-3 weeks)
**Goal:** Build essential missing PBX features

**APIs to Create:**
1. `/api/queues.php` - Call queues
2. `/api/conferences.php` - Conference bridges
3. `/api/ivr.php` - IVR builder
4. `/api/dialplan.php` - Dialplan editor
5. `/api/time-conditions.php` - Business hours

**UIs to Create:**
1. Queues Manager
2. Conferences Manager
3. IVR Builder
4. Dialplan Editor
5. Time Conditions Manager

**Testing:**
- Create test queue with agents
- Create test conference room
- Build sample IVR flow
- Edit dialplan and verify reload
- Set business hours and test routing

### Phase 3: Routing & Advanced Features (2-3 weeks)
**Goal:** Complete call routing capabilities

**APIs to Create:**
1. `/api/inbound-routing.php` - Enhanced inbound routing
2. `/api/outbound-routing.php` - Outbound routing
3. `/api/ring-groups.php` - Ring groups
4. `/api/follow-me.php` - Follow-me routing
5. `/api/recordings.php` - Call recording management

**UIs to Create:**
1. Enhanced Inbound Routing
2. Outbound Routing
3. Ring Groups Manager
4. Follow-me Configuration
5. Call Recording Manager

**Testing:**
- Test inbound DID routing
- Verify outbound trunk selection
- Test ring group strategies
- Configure follow-me and test
- Record calls and verify playback

### Phase 4: System & Security (1-2 weeks)
**Goal:** Complete system management and security

**APIs to Create:**
1. `/api/network.php` - Network and SIP settings
2. `/api/security.php` - Security and firewall
3. `/api/active-calls.php` - Real-time monitoring
4. `/api/asterisk-cli.php` - CLI access
5. `/api/api-keys.php` - API key management

**UIs to Create:**
1. Network & SIP Settings
2. Security & Firewall Dashboard
3. Active Calls Monitor
4. Asterisk CLI
5. API Keys Manager

**Testing:**
- Update network settings and verify
- Configure firewall rules
- Monitor active calls
- Execute CLI commands
- Manage API keys

### Phase 5: Polish & Enhancement (1-2 weeks)
**Goal:** Additional features and refinements

**APIs to Create:**
1. `/api/feature-codes.php` - Feature code management
2. `/api/moh.php` - Music on hold
3. `/api/parking.php` - Call parking
4. `/api/prompts.php` - Sound prompts

**UIs to Create:**
1. Feature Codes Manager (enhance existing)
2. MOH Manager (enhance existing)
3. Call Parking
4. Sound Prompts Library

**Additional Enhancements:**
- User role management UI
- Backup/restore UI
- Enhanced monitoring dashboards
- WebSocket real-time updates
- Mobile responsive views

**Testing:**
- Test all feature codes
- Configure MOH streaming
- Test call parking
- Upload and manage prompts

### Phase 6: Integration & Packaging (1 week)
**Goal:** Prepare for distribution

1. Update installer (`install.php`)
2. Create `.flxx` packaging system
3. Include Asterisk modules
4. Platform integration (WordPress, Composr, WHMCS)
5. Final testing and documentation

---

## Technical Implementation Details

### URL Compatibility

Two approaches for bridging old/new API patterns:

#### Option 1: Apache .htaccess Rewriting
```apache
# /home/flexpbxuser/public_html/api/.htaccess
RewriteEngine On

# Extensions API
RewriteRule ^extensions/([0-9]+)$ extensions.php?path=details&id=$1 [L,QSA]
RewriteRule ^extensions$ extensions.php?path=list [L,QSA]

# Trunks API
RewriteRule ^trunks/([0-9]+)$ trunks.php?path=details&id=$1 [L,QSA]
RewriteRule ^trunks$ trunks.php?path=list [L,QSA]

# Add more as needed...
```

#### Option 2: Update UI JavaScript
```javascript
// Old pattern
fetch(`/api/extensions/${id}`)

// New pattern
fetch(`/api/extensions.php?path=details&id=${id}`)
```

**Recommendation:** Use Option 2 (update UIs) for clean, explicit API calls

### API Standards

All APIs should follow this structure:

```php
<?php
require_once 'auth.php';

// Authentication
$auth = checkAuth();
if (!$auth['authenticated']) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

// Get path parameter
$path = $_GET['path'] ?? 'list';

// Route to handler
switch ($path) {
    case 'list':
        handleList($method);
        break;
    case 'details':
        handleDetails($method);
        break;
    case 'create':
        handleCreate($method);
        break;
    case 'update':
        handleUpdate($method);
        break;
    case 'delete':
        handleDelete($method);
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid path']);
}

function handleList($method) {
    // Implementation
    echo json_encode(['success' => true, 'data' => $result]);
}
?>
```

### UI Template

All admin UIs should use this structure:

```php
<?php
session_start();

// Authentication check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Role-based access
$allowed_roles = ['superadmin', 'admin', 'manager'];
if (!in_array($_SESSION['admin_role'] ?? 'guest', $allowed_roles)) {
    header('Location: login.php?error=insufficient_permissions');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feature Name - FlexPBX Admin</title>
    <style>
        /* Modern UI styles */
    </style>
</head>
<body>
    <div class="container">
        <!-- UI content -->
    </div>

    <script>
        // API integration JavaScript
        async function loadData() {
            const response = await fetch('/api/endpoint.php?path=list');
            const data = await response.json();
            // Render data
        }
    </script>
</body>
</html>
```

---

## Testing Strategy

### Unit Testing
- Test each API endpoint individually
- Verify all CRUD operations
- Check error handling
- Validate authentication

### Integration Testing
- Test UI → API communication
- Verify Asterisk configuration updates
- Check file permissions after changes
- Test service reloads

### User Acceptance Testing
- Test workflows (create extension → add to queue → route call)
- Verify all UI features work as expected
- Check role-based access controls
- Test on different browsers

### Load Testing
- Stress test APIs
- Monitor system resources
- Test concurrent operations
- Verify database performance

---

## Security Considerations

### API Security
- ✅ Session-based authentication
- ✅ Role-based access control
- 🆕 API key authentication
- 🆕 Rate limiting
- 🆕 Input validation
- 🆕 SQL injection prevention
- 🆕 XSS protection
- 🆕 CSRF tokens

### File Security
- Proper file permissions (640 for configs)
- Owner verification (asterisk:asterisk)
- Input sanitization for file operations
- Restricted file upload types
- Path traversal prevention

### Asterisk Security
- Strong extension passwords
- SIP endpoint security (permit/deny)
- Fail2ban integration
- Trunk authentication
- Encrypted transports (TLS/SRTP)

---

## Documentation Requirements

### User Documentation
1. **Admin Guide** - Complete feature documentation
2. **Quick Start Guide** - Getting started with FlexPBX
3. **Feature Guides** - Detailed guides for each feature
4. **Troubleshooting** - Common issues and solutions

### Developer Documentation
1. **API Reference** - Complete API documentation (OpenAPI/Swagger)
2. **Architecture Overview** - System design and structure
3. **Development Guide** - How to extend FlexPBX
4. **Security Best Practices** - Security guidelines

### Installation Documentation
1. **Installation Guide** - Step-by-step installation
2. **Upgrade Guide** - Upgrading from previous versions
3. **Migration Guide** - Migrating from other systems
4. **Platform Integration** - WordPress, Composr, WHMCS setup

---

## Success Criteria

### Feature Completeness
- ✅ 100% of Asterisk features accessible via UI
- ✅ All existing UIs connected to new APIs
- ✅ All missing features implemented
- ✅ No feature left behind

### Usability
- ✅ Intuitive navigation
- ✅ Consistent UI/UX across all pages
- ✅ Mobile responsive
- ✅ Accessibility compliant

### Performance
- ✅ Fast page loads (<2s)
- ✅ Real-time updates where needed
- ✅ Efficient API responses
- ✅ Optimized database queries

### Reliability
- ✅ Error handling for all scenarios
- ✅ Graceful degradation
- ✅ Backup and recovery
- ✅ Logging and monitoring

---

## Summary: What Goes Where

### 📞 PBX Core (5 pages)
- Extensions Management (update existing)
- Trunks Management (update existing)
- Inbound Routing (update existing)
- **Outbound Routing (NEW)**
- **Dialplan Editor (NEW)**

### 🔊 Call Features (5 pages)
- Voicemail Management (update existing)
- Feature Codes (update existing)
- **Call Recording (NEW)**
- Music on Hold (update existing)
- **Call Parking (NEW)**

### 📋 Advanced PBX (6 pages)
- **Call Queues (NEW)**
- **Conference Bridges (NEW)**
- **IVR Builder (NEW)**
- **Ring Groups (NEW)**
- **Follow Me (NEW)**
- **Time Conditions (NEW)**

### 📊 Monitoring & Logs (5 pages)
- Live Dashboard (complete)
- **Call Logs UI (NEW - API exists)**
- **Active Calls (NEW)**
- Extension Status (in dashboard, could be standalone)
- System Health (in dashboard)

### 🔧 System & Config (6 pages)
- System Settings (update existing)
- **Network & SIP (NEW)**
- **Security & Firewall (NEW)**
- **Backup & Restore UI (NEW - API exists)**
- **API Keys Manager (NEW)**
- **Asterisk CLI (NEW)**

### 📁 Media & Audio (4 pages)
- Audio Upload (exists)
- Media Manager (exists)
- **Sound Prompts (NEW)**
- MOH Streaming (part of MOH manager)

### 👥 Users & Access (4 pages)
- **User Roles UI (NEW - API exists)**
- Account Settings (exists, consolidate)
- Notification Settings (exists)
- Bug Tracker (exists)

**Total Pages:**
- **Existing to Update:** 10
- **New to Create:** 25
- **Total Admin UI Pages:** 35

**Total APIs:**
- **Already Created:** 5 (extensions, trunks, call-logs, voicemail, system)
- **To Create:** 22
- **Total APIs:** 27

---

## Conclusion

This architecture provides **100% Asterisk feature coverage** with **nothing left out**. Every feature accessible via Asterisk CLI or configuration files will have a corresponding UI page and API endpoint.

The implementation can be completed in **6 phases over approximately 10-13 weeks** with proper testing and documentation.

**Next Immediate Steps:**
1. Review and approve this architecture
2. Begin Phase 1: Update existing UIs to use new APIs
3. Create missing high-priority APIs and UIs
4. Test thoroughly at each phase
5. Document everything

---

**Document Version:** 1.0
**Created:** October 16, 2025
**Status:** Awaiting Approval
**Priority:** Critical - Complete Asterisk Integration
