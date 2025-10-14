# 🗂️ FlexPBX Server File Organization Plan

Generated on: 2025-10-13

## Current Issues to Address
1. ❌ Unnecessary files in api-upload folder need cleanup
2. 📁 Empty monitoring folder needs content
3. 🗂️ Files not organized into proper public_html structure
4. 🧹 Old backup files and system files need removal

## Proposed Server Directory Structure

### public_html/api/ (API Backend)
```
api/
├── index.php                    ✅ (main API endpoint)
├── config.php                   ✅ (database config)
├── auth.php                     ✅ (authentication)
├── enhanced-file-manager.php    ✅ (file management)
├── install.php                  ✅ (installation script)
└── modules/
    └── google-voice.php         ✅ (Google Voice integration)
```

### public_html/admin/ (Admin Dashboard)
```
admin/
├── index.html                          (main admin dashboard)
├── trunks-management.html             ✅ (SIP trunk management)
├── extensions-management.html         ✅ (extension management)
├── google-voice.html                  ✅ (Google Voice integration)
├── queues.html                        (call queues management)
├── ivr.html                           (IVR system management)
└── call-logs.html                     (call history & logs)
```

### public_html/modules/ (Feature Modules)
```
modules/
├── google-voice.php               ✅ (Google Voice PHP module)
├── trunk-manager.php              (trunk management module)
├── extension-manager.php          (extension management module)
└── call-routing.php               (call routing logic)
```

### public_html/monitoring/ (System Monitoring)
```
monitoring/
├── index.html                     (monitoring dashboard)
├── dashboard.html                 ✅ (system monitoring)
├── real-time.html                 (real-time call monitoring)
├── analytics.html                 (call analytics)
└── assets/
    └── flexpbx-dynamic-ui.js      ✅ (dynamic UI components)
```

### public_html/config/ (Configuration Management)
```
config/
├── callcentric-trunk-config.json     ✅ (Callcentric setup)
├── google-voice-config.json          ✅ (Google Voice setup)
├── extensions-config.json            ✅ (extension definitions)
├── config-validator.js               ✅ (configuration validator)
├── file-manager-import.js             ✅ (import utility)
└── flexpbx-server-setup.sh            ✅ (setup script)
```

### public_html/docs/ (Documentation)
```
docs/
├── deployment-summary.md              ✅ (deployment guide)
├── development-archive.md             ✅ (development history)
├── api-documentation.md               (API reference)
├── admin-guide.md                     (admin user guide)
└── installation-guide.md              (installation instructions)
```

### public_html/downloads/ (Client Downloads)
```
downloads/
├── index.html                         ✅ (download page)
├── FlexPhone/                         (FlexPhone clients)
├── FlexPBX-Desktop/                   (desktop apps)
├── SIP-Clients/                       (third-party SIP clients)
└── Documentation/                     (user manuals)
```

### public_html/scripts/ (Management Scripts)
```
scripts/
├── update-manager.php                 ✅ (system updates)
├── update-scheduler.php               ✅ (scheduled updates)
├── connection-manager.php             ✅ (connection management)
├── auto-link-manager.php              ✅ (link management)
└── backup-manager.php                 (backup utilities)
```

### public_html/ (Root Web Files)
```
public_html/
├── index.html                         ✅ (main landing page)
├── .htaccess                          ✅ (Apache configuration)
├── favicon.ico                        (site icon)
└── robots.txt                         (search engine rules)
```

## Files to Remove (Cleanup)
```
❌ FlexPBX-API-Complete-Backup.flxx    (old backup file)
❌ README-UPLOAD.txt                   (temporary readme)
❌ server-fixes/                       (one-time fixes folder)
❌ .DS_Store                           (macOS system file)
❌ downloads-package.tar.gz            (redundant download package)
```

## Access Points After Organization
- **Main Site**: https://flexpbx.devinecreations.net/
- **Admin Panel**: https://flexpbx.devinecreations.net/admin/
- **API Endpoints**: https://flexpbx.devinecreations.net/api/
- **Google Voice Module**: https://flexpbx.devinecreations.net/modules/google-voice.php
- **System Monitoring**: https://flexpbx.devinecreations.net/monitoring/
- **Documentation**: https://flexpbx.devinecreations.net/docs/
- **Client Downloads**: https://flexpbx.devinecreations.net/downloads/

## Deployment Commands

### 1. Create Directory Structure
```bash
# Create all required directories
mkdir -p public_html/{api,admin,modules,monitoring,config,docs,downloads,scripts}
mkdir -p public_html/monitoring/assets
mkdir -p public_html/downloads/{FlexPhone,FlexPBX-Desktop,SIP-Clients,Documentation}
```

### 2. Move Files to Proper Locations
```bash
# API files
mv index.php config.php auth.php enhanced-file-manager.php install.php public_html/api/

# Admin dashboard files
mv admin-*.html public_html/admin/

# Module files
mv modules-google-voice.php public_html/modules/google-voice.php

# Monitoring files
mv monitoring-dashboard.html public_html/monitoring/index.html
mv flexpbx-dynamic-ui.js public_html/monitoring/assets/

# Configuration files
mv *-config.json config-validator.js file-manager-import.js flexpbx-server-setup.sh public_html/config/

# Documentation files
mv FLEXPBX-*.md CLAUDE-HANDOFF.md public_html/docs/

# Downloads
mv downloads/* public_html/downloads/
mv downloads-index.html public_html/downloads/index.html

# Management scripts
mv update-*.php connection-manager.php auto-link-manager.php upload-enhanced-manager.php public_html/scripts/

# Root files
mv index.html .htaccess public_html/
```

### 3. Cleanup Unnecessary Files
```bash
# Remove temporary and unnecessary files
rm -f FlexPBX-API-Complete-Backup.flxx
rm -f README-UPLOAD.txt
rm -f .DS_Store
rm -f downloads-package.tar.gz
rm -rf server-fixes/
```

### 4. Set Proper Permissions
```bash
# Set proper file permissions
chmod 644 public_html/api/*.php
chmod 644 public_html/modules/*.php
chmod 644 public_html/scripts/*.php
chmod 755 public_html/config/*.sh
chmod 644 public_html/**/*.html
chmod 644 public_html/**/*.js
chmod 644 public_html/**/*.json
```

## Testing After Deployment
1. ✅ **Main Site**: Verify landing page loads
2. ✅ **Admin Panel**: Test trunk/extension management
3. ✅ **API**: Test authentication and file management
4. ✅ **Google Voice**: Test OAuth integration
5. ✅ **Monitoring**: Verify real-time dashboard
6. ✅ **Downloads**: Test client download links
7. ✅ **Documentation**: Verify all docs are accessible

## Benefits After Organization
- 🗂️ **Organized Structure**: Clear separation of concerns
- 🔒 **Security**: API and admin files properly segregated
- 📊 **Monitoring**: Dedicated monitoring dashboard
- 📚 **Documentation**: Centralized documentation access
- 💾 **Downloads**: Clean download center for clients
- 🧹 **Clean**: No unnecessary files cluttering the server
- 🔧 **Maintainable**: Easy to find and update specific components

---

**Next Steps:**
1. Review this organization plan
2. Execute the deployment commands
3. Test all endpoints and functionality
4. Update any hard-coded paths in the code
5. Document the new structure for future maintenance