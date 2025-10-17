# FlexPBX Feature Contribution System

**Created:** October 16, 2025
**Purpose:** Community-Driven Feature Development and Distribution
**Status:** Architecture Design

---

## Overview

The FlexPBX Feature Contribution System enables developers to create, submit, and distribute features to the FlexPBX ecosystem through a centralized master server and HubNode API gateway.

---

## Architecture

### Master Server (This Server)
```
flexpbx.devinecreations.net (Master)
├── FlexPBX Core
├── HubNode API Gateway (:5018)
├── Feature Repository
├── Update Distribution
└── Contribution Management
```

### Remote FlexPBX Servers
```
client-server-1.example.com
├── FlexPBX Installation
├── Connects to: HubNode Master API
├── Receives: Updates, Features
└── Submits: Contributions (optional)
```

### Desktop Clients
```
FlexPBX Desktop App
├── Connects to: HubNode Master API
├── Receives: Updates, Data
└── Submits: Feature Ideas (optional)
```

---

## HubNode API Gateway Roles

### 1. Master Server Gateway (PRIMARY)
**Purpose:** Centralized access point for all FlexPBX installations

**Functions:**
- Authenticate remote servers/clients
- Route API requests to FlexPBX
- Distribute updates and features
- Manage feature submissions
- Track connected installations
- Monitor usage statistics

### 2. Update Distribution
**Purpose:** Push updates to connected FlexPBX servers

**Functions:**
- Check for available updates
- Download update packages
- Verify package integrity
- Install updates remotely
- Rollback if needed
- Update notifications

### 3. Feature Marketplace
**Purpose:** Community-driven feature ecosystem

**Functions:**
- Browse available features
- Install optional features
- Submit new features
- Review contributions
- Approve/reject features
- Version management

---

## Feature Contribution Workflow

### Developer Workflow

```
Developer Creates Feature
         ↓
Tests Locally
         ↓
Submits via Admin Portal
         ↓
Master Server Receives Submission
         ↓
Admin Reviews Submission
         ↓
Approve or Reject
         ↓
If Approved → Add to Repository
         ↓
Distribute to FlexPBX Servers
```

### Submission Process

#### 1. Developer Creates Feature
```bash
/contributed-features/
├── developer-name/
│   └── feature-name/
│       ├── feature.json          # Metadata
│       ├── install.php           # Installation script
│       ├── uninstall.php         # Removal script
│       ├── README.md             # Documentation
│       ├── screenshots/          # UI previews
│       ├── api/                  # API files
│       │   └── feature-api.php
│       ├── admin/                # Admin UI files
│       │   └── feature-ui.html
│       └── tests/                # Test cases
│           └── feature-test.php
```

#### 2. Feature Metadata (`feature.json`)
```json
{
  "name": "Advanced Call Analytics",
  "version": "1.0.0",
  "author": {
    "name": "John Doe",
    "email": "john@example.com",
    "website": "https://example.com"
  },
  "description": "Provides advanced call analytics with AI insights",
  "category": "analytics",
  "type": "optional",
  "license": "MIT",
  "asterisk_version_min": "18.0",
  "flexpbx_version_min": "1.0",
  "dependencies": {
    "php_extensions": ["pdo", "curl"],
    "asterisk_modules": ["app_queue", "res_ari"],
    "external_services": []
  },
  "permissions_required": [
    "call_logs.read",
    "system.statistics"
  ],
  "installation": {
    "files": ["api/", "admin/"],
    "database": "migrations/",
    "config": "config/"
  },
  "screenshots": [
    "screenshots/dashboard.png",
    "screenshots/analytics.png"
  ],
  "changelog": [
    {
      "version": "1.0.0",
      "date": "2025-10-16",
      "changes": ["Initial release"]
    }
  ]
}
```

#### 3. Developer Submits
**Via Admin Portal:**
- Upload feature package (.zip)
- Fill out submission form
- Provide documentation
- Add screenshots
- Submit for review

**API Endpoint:**
```
POST https://api.devine-creations.com/flexpbx/contribute
Authorization: Bearer {api_key}
Content-Type: multipart/form-data

{
  "feature_package": <file>,
  "feature_metadata": <json>,
  "test_results": <file>
}
```

#### 4. Admin Reviews Submission

**Review Criteria:**
- ✅ Code quality and security
- ✅ Accessibility compliance
- ✅ Documentation completeness
- ✅ Test coverage
- ✅ No malicious code
- ✅ Follows FlexPBX standards
- ✅ Works with Asterisk
- ✅ Doesn't break existing features

**Admin Portal Actions:**
- View submission details
- Test feature in sandbox
- Review code
- Check security
- Approve or reject
- Request changes

#### 5. Feature Repository

**Approved Features Added To:**
```
/home/devinecr/apps/hubnode/feature-repository/
├── analytics/
│   └── advanced-call-analytics/
│       └── versions/
│           ├── 1.0.0/
│           └── 1.1.0/
├── security/
│   └── two-factor-auth/
├── integrations/
│   └── salesforce-crm/
└── ui-themes/
    └── dark-mode-pro/
```

---

## Feature Types

### 1. Core Features (Included by Default)
- Extensions Management
- Trunk Management
- Call Routing
- Voicemail
- Basic Security

**Distributed:** Automatically with all installations

### 2. Optional Features (User Installs)
- Advanced Analytics
- CRM Integrations
- Custom Themes
- Additional Codecs
- Third-party Service Integrations

**Distributed:** Available in marketplace, user chooses to install

### 3. Contributed Features (Community)
- Developer submissions
- Reviewed and approved by admins
- Added to optional features repository
- Can be promoted to core if widely used

**Distributed:** After approval, added to marketplace

---

## Update Distribution System

### Update Types

#### 1. Security Updates (Auto-Install)
- Critical security patches
- Automatically pushed to all servers
- Mandatory installation
- Cannot be deferred

#### 2. Feature Updates (Optional)
- New features available
- User chooses when to install
- Can be scheduled
- Tested before deployment

#### 3. Bug Fixes (Recommended)
- Bug fix patches
- Recommended to install
- Can be deferred
- Notification sent

### Update Process

#### Remote Server Checks for Updates:
```php
// Remote server calls master
GET https://api.devine-creations.com/flexpbx/updates/check
Authorization: Bearer {server_api_key}

Response:
{
  "updates_available": true,
  "updates": [
    {
      "type": "security",
      "version": "1.0.1",
      "priority": "critical",
      "auto_install": true,
      "size": "5.2 MB",
      "description": "Security patch for SIP authentication"
    },
    {
      "type": "feature",
      "version": "1.1.0",
      "priority": "optional",
      "auto_install": false,
      "size": "12.5 MB",
      "description": "New call queuing features"
    }
  ]
}
```

#### Remote Server Downloads Update:
```php
GET https://api.devine-creations.com/flexpbx/updates/download/{version}
Authorization: Bearer {server_api_key}

Response:
{
  "download_url": "https://updates.flexpbx.net/packages/1.0.1.flxx",
  "checksum": "sha256:abc123...",
  "signature": "gpg:def456..."
}
```

#### Remote Server Installs Update:
```php
POST https://api.devine-creations.com/flexpbx/updates/install
Authorization: Bearer {server_api_key}

{
  "version": "1.0.1",
  "package_checksum": "abc123..."
}

Response:
{
  "success": true,
  "installed_version": "1.0.1",
  "rollback_available": true,
  "changes_applied": [...]
}
```

#### Master Server Tracks Installation:
```php
// Master keeps record
{
  "server_id": "remote-server-1",
  "hostname": "pbx.client.com",
  "current_version": "1.0.1",
  "last_update": "2025-10-16T14:30:00Z",
  "installed_features": ["core", "call-analytics"],
  "pending_updates": []
}
```

---

## Admin Portal - Contribution Management

### UI Section: `/admin/feature-contributions.html` (NEW)

#### Dashboard View
```
┌─────────────────────────────────────────────┐
│  Feature Contributions                       │
├─────────────────────────────────────────────┤
│                                             │
│  Pending Review: 5                          │
│  Approved (This Month): 12                  │
│  Rejected (This Month): 3                   │
│  Total Features: 247                        │
│                                             │
├─────────────────────────────────────────────┤
│  Pending Submissions:                       │
│                                             │
│  ┌───────────────────────────────────────┐ │
│  │ 📊 Advanced Call Analytics            │ │
│  │ By: John Doe                          │ │
│  │ Submitted: 2 days ago                 │ │
│  │ [Review] [Approve] [Reject]           │ │
│  └───────────────────────────────────────┘ │
│                                             │
│  ┌───────────────────────────────────────┐ │
│  │ 🔐 Two-Factor Authentication          │ │
│  │ By: Jane Smith                        │ │
│  │ Submitted: 1 week ago                 │ │
│  │ [Review] [Approve] [Reject]           │ │
│  └───────────────────────────────────────┘ │
│                                             │
└─────────────────────────────────────────────┘
```

#### Review Interface
```
┌─────────────────────────────────────────────┐
│  Review: Advanced Call Analytics            │
├─────────────────────────────────────────────┤
│                                             │
│  Author: John Doe (john@example.com)        │
│  Version: 1.0.0                             │
│  Category: Analytics                        │
│  License: MIT                               │
│                                             │
│  Description:                               │
│  Provides advanced call analytics with      │
│  AI-powered insights...                     │
│                                             │
│  Files Included:                            │
│  ✓ api/analytics.php (15KB)                 │
│  ✓ admin/analytics-dashboard.html (8KB)     │
│  ✓ install.php (2KB)                        │
│  ✓ README.md (5KB)                          │
│                                             │
│  Security Scan:                             │
│  ✓ No malicious code detected               │
│  ✓ SQL injection safe                       │
│  ✓ XSS protection implemented               │
│                                             │
│  Accessibility Check:                       │
│  ✓ WCAG 2.1 AA compliant                    │
│  ✓ Screen reader tested                     │
│  ✓ Keyboard navigation working              │
│                                             │
│  Test Results:                              │
│  ✓ 47/47 tests passed                       │
│  ✓ Code coverage: 92%                       │
│                                             │
│  [Test in Sandbox] [View Code]              │
│  [Approve & Publish] [Request Changes]      │
│  [Reject with Reason]                       │
│                                             │
└─────────────────────────────────────────────┘
```

---

## API Endpoints for Contribution System

### For Developers (Submitting Features)

#### 1. Submit Feature
```
POST /flexpbx/contribute/submit
Authorization: Bearer {developer_api_key}

Request:
{
  "feature_package": <base64_encoded_zip>,
  "metadata": {
    "name": "Advanced Call Analytics",
    "version": "1.0.0",
    "description": "...",
    ...
  }
}

Response:
{
  "submission_id": "SUB-12345",
  "status": "pending_review",
  "estimated_review_time": "3-5 days"
}
```

#### 2. Check Submission Status
```
GET /flexpbx/contribute/status/{submission_id}
Authorization: Bearer {developer_api_key}

Response:
{
  "submission_id": "SUB-12345",
  "status": "approved",
  "review_notes": "Great feature! Added to marketplace.",
  "published_date": "2025-10-20",
  "download_count": 0
}
```

#### 3. Update Existing Feature
```
PUT /flexpbx/contribute/update/{feature_id}
Authorization: Bearer {developer_api_key}

Request:
{
  "version": "1.1.0",
  "changes": ["Bug fixes", "New dashboard"],
  "package": <base64_encoded_zip>
}
```

### For Admins (Reviewing Features)

#### 4. List Pending Submissions
```
GET /flexpbx/admin/contributions/pending
Authorization: Bearer {admin_api_key}

Response:
{
  "pending_count": 5,
  "submissions": [
    {
      "submission_id": "SUB-12345",
      "feature_name": "Advanced Call Analytics",
      "author": "John Doe",
      "submitted_date": "2025-10-14",
      "status": "pending_review"
    },
    ...
  ]
}
```

#### 5. Approve Feature
```
POST /flexpbx/admin/contributions/approve/{submission_id}
Authorization: Bearer {admin_api_key}

Request:
{
  "category": "optional",
  "publish_immediately": true,
  "review_notes": "Excellent feature, approved for marketplace"
}

Response:
{
  "success": true,
  "feature_id": "FEAT-789",
  "published": true,
  "marketplace_url": "https://features.flexpbx.net/analytics/advanced-call-analytics"
}
```

#### 6. Reject Feature
```
POST /flexpbx/admin/contributions/reject/{submission_id}
Authorization: Bearer {admin_api_key}

Request:
{
  "reason": "Security concerns with database queries",
  "suggestions": "Please use prepared statements for all SQL queries"
}

Response:
{
  "success": true,
  "notification_sent": true
}
```

### For Remote Servers (Installing Features)

#### 7. Browse Available Features
```
GET /flexpbx/features/browse
Authorization: Bearer {server_api_key}

Query Parameters:
  ?category=analytics
  &type=optional
  &search=call

Response:
{
  "total": 47,
  "features": [
    {
      "feature_id": "FEAT-789",
      "name": "Advanced Call Analytics",
      "description": "...",
      "version": "1.0.0",
      "author": "John Doe",
      "rating": 4.8,
      "downloads": 1523,
      "price": "free",
      "screenshots": [...]
    },
    ...
  ]
}
```

#### 8. Install Feature
```
POST /flexpbx/features/install
Authorization: Bearer {server_api_key}

Request:
{
  "feature_id": "FEAT-789",
  "version": "1.0.0",
  "agree_to_license": true
}

Response:
{
  "success": true,
  "download_url": "https://cdn.flexpbx.net/features/FEAT-789-1.0.0.zip",
  "install_script": "install.php",
  "estimated_time": "30 seconds"
}
```

#### 9. Report Feature Issue
```
POST /flexpbx/features/report-issue
Authorization: Bearer {server_api_key}

Request:
{
  "feature_id": "FEAT-789",
  "issue_type": "bug",
  "description": "Dashboard doesn't load on mobile",
  "steps_to_reproduce": "...",
  "system_info": {...}
}
```

---

## Feature Categories

### Core Features (Always Included)
- **PBX Core:** Extensions, Trunks, Routing
- **Call Features:** Voicemail, Feature Codes
- **System:** Monitoring, Backups, Security

### Optional Features (User Choice)
- **Analytics:** Call statistics, reporting, AI insights
- **Integrations:** CRM, Helpdesk, Chat platforms
- **Advanced Routing:** Complex IVR, Time conditions
- **Security:** 2FA, Advanced firewall, Intrusion detection
- **UI Themes:** Custom themes, branding
- **Automation:** Call flows, Auto-attendants

### Contributed Features (Community)
- Everything submitted and approved by community
- Can be promoted to "Optional" if popular
- Can be integrated into "Core" if essential

---

## Desktop Client Integration

### FlexPBX Desktop App
**Connects via HubNode API to Master Server**

#### Features:
1. **Server Management**
   - Connect to FlexPBX server
   - View system status
   - Manage extensions
   - Monitor calls

2. **Update Management**
   - Check for updates
   - Install updates locally
   - View changelog
   - Rollback if needed

3. **Feature Marketplace**
   - Browse available features
   - Install to connected server
   - Manage installed features
   - Submit feature ideas

4. **Multi-Server Support**
   - Connect to multiple FlexPBX servers
   - Switch between servers
   - Sync configurations
   - Central management dashboard

---

## Security Considerations

### Code Review Process
1. **Automated Security Scan**
   - SQL injection detection
   - XSS vulnerability check
   - File upload validation
   - Permission escalation check

2. **Manual Code Review**
   - Admin reviews source code
   - Check for backdoors
   - Verify best practices
   - Ensure compliance

3. **Sandbox Testing**
   - Test in isolated environment
   - Monitor system calls
   - Check network activity
   - Verify file operations

### Feature Signing
- All approved features digitally signed
- Verify signature before installation
- Prevent tampering
- Trust chain established

### Permission System
- Features request specific permissions
- User must approve permissions
- Limit what features can access
- Audit permission usage

---

## Monetization (Future)

### Free Features
- Community contributions (MIT/GPL)
- Core features
- Basic integrations

### Premium Features (Optional)
- Developed by core team
- Advanced features
- Priority support
- Commercial license

### Developer Revenue Share
- Developers can set price for features
- Revenue split: 70% Developer / 30% FlexPBX
- Monthly payouts
- Transaction tracking

---

## Implementation Tasks

### HubNode API Extensions

#### New Endpoints to Add:
1. `/flexpbx/contribute/*` - Contribution system
2. `/flexpbx/features/*` - Feature marketplace
3. `/flexpbx/updates/*` - Update distribution
4. `/flexpbx/servers/*` - Connected server management

#### Database Tables:
```sql
-- Feature submissions
CREATE TABLE feature_submissions (
  id VARCHAR PRIMARY KEY,
  feature_name VARCHAR,
  author_id VARCHAR,
  version VARCHAR,
  package_url VARCHAR,
  metadata JSON,
  status ENUM('pending', 'approved', 'rejected'),
  submitted_at DATETIME,
  reviewed_at DATETIME,
  reviewed_by VARCHAR
);

-- Published features
CREATE TABLE features (
  id VARCHAR PRIMARY KEY,
  name VARCHAR,
  category VARCHAR,
  type ENUM('core', 'optional', 'contributed'),
  version VARCHAR,
  author_id VARCHAR,
  downloads INT DEFAULT 0,
  rating DECIMAL(2,1),
  published_at DATETIME
);

-- Connected servers
CREATE TABLE connected_servers (
  server_id VARCHAR PRIMARY KEY,
  hostname VARCHAR,
  version VARCHAR,
  last_seen DATETIME,
  installed_features JSON
);

-- Update history
CREATE TABLE update_history (
  id INT PRIMARY KEY AUTO_INCREMENT,
  server_id VARCHAR,
  from_version VARCHAR,
  to_version VARCHAR,
  installed_at DATETIME,
  success BOOLEAN
);
```

### Admin Portal Pages

#### New Pages to Create:
1. `/admin/feature-contributions.html` - Review submissions
2. `/admin/feature-marketplace-admin.html` - Manage marketplace
3. `/admin/connected-servers.html` - View connected installations
4. `/admin/update-distribution.html` - Manage update rollouts

---

## Testing Strategy

### Feature Submission Testing
1. Developer submits test feature
2. Automated security scan runs
3. Admin reviews in sandbox
4. Approval process tested
5. Distribution verified

### Update Distribution Testing
1. Create test update package
2. Push to test server
3. Verify installation
4. Test rollback
5. Monitor for issues

### Desktop Client Testing
1. Connect to master server
2. Browse features
3. Install feature
4. Check for updates
5. Verify synchronization

---

## Documentation

### For Developers
- **Contribution Guide:** How to create and submit features
- **API Reference:** Contribution API documentation
- **Best Practices:** Coding standards and guidelines
- **Example Features:** Sample code and templates

### For Admins
- **Review Guide:** How to review submissions
- **Security Checklist:** What to look for
- **Approval Process:** Steps to approve/reject
- **Marketplace Management:** How to organize features

### For Users
- **Feature Installation:** How to browse and install
- **Update Management:** How to update FlexPBX
- **Troubleshooting:** Common issues and solutions

---

## Summary

The FlexPBX Feature Contribution System enables:

1. ✅ **Centralized Master Server** - Single source of truth
2. ✅ **Community Contributions** - Developers can submit features
3. ✅ **Update Distribution** - Push updates to all installations
4. ✅ **Feature Marketplace** - Browse and install features
5. ✅ **Desktop Client Support** - Manage via desktop app
6. ✅ **Quality Control** - Review and approval process
7. ✅ **Security** - Code scanning and signing
8. ✅ **Accessibility** - All features must be accessible

**Next Steps:**
1. Extend HubNode API with contribution endpoints
2. Create admin portal pages for review
3. Build feature submission process
4. Implement update distribution
5. Test with sample contributions

---

**Document Version:** 1.0
**Created:** October 16, 2025
**Status:** Architecture Design Complete
