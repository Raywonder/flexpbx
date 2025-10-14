# FlexPBX Installer - Quick Start Guide for Next Session

## 🎯 Current Status: 40% Complete

The enhanced installer foundation is built with Asterisk detection complete. Next session will add extension management, trunk configuration, and routing.

---

## 📍 Where We Left Off

### ✅ Completed (Phase 1):
1. **Enhanced Installer Created:** `/home/flexpbxuser/public_html/api/install-enhanced.php`
2. **Backup Created:** Original installer backed up with timestamp
3. **Asterisk Detection Working:** Detects version 18.12.1, shows status, can install if missing
4. **Welcome & Requirements:** Professional UI with all new features listed

### 🚧 Next To Build (Phase 2):
1. Extension Management Interface
2. SIP Trunk Configuration
3. Google Voice Setup
4. Inbound Routing
5. Enhanced Database Tables
6. Final Installation & Testing

---

## 🚀 Quick Resume Commands

### To Test Current Progress:
```bash
# View the enhanced installer in browser
https://flexpbx.devinecreations.net/api/install-enhanced.php

# Check Asterisk status
systemctl status asterisk

# Check database
/usr/bin/mariadb -u root -p flexpbxuser_flexpbx -e "SHOW TABLES;"
```

### To Continue Development:
```bash
# Edit the enhanced installer
nano /home/flexpbxuser/public_html/api/install-enhanced.php

# View progress documentation
cat /home/flexpbxuser/public_html/api/INSTALLER_PROGRESS.md
```

---

## 📝 Immediate Next Tasks (Priority Order)

### Task 1: Add Extension Management Method (30 min)
**Location:** `install-enhanced.php` - Add method `configureExtensions()`

**What to build:**
- HTML form with extension fields (2000-2005)
- JavaScript to add more extensions dynamically
- AJAX handler to save extensions to database
- Create database tables: `ps_endpoints`, `ps_auths`, `ps_aors`, `extensions`
- Generate `/etc/asterisk/pjsip_realtime.conf`
- Reload Asterisk PJSIP module

**Code Template:**
```php
private function configureExtensions() {
    $this->renderHeader('FlexPBX Installation - Extension Management');

    // Check if form submitted
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_extensions'])) {
        $this->createExtensions($_POST['extensions']);
        header('Location: ?step=trunks');
        exit;
    }

    // Show extension creation form
    ?>
    <div class="extensions-section">
        <h2>👤 Create Extensions</h2>
        <p>Create your SIP extensions for users to register softphones</p>

        <form method="POST" id="extension-form">
            <div id="extension-list">
                <!-- JavaScript will populate this -->
            </div>
            <button type="button" onclick="addExtension()">+ Add Extension</button>
            <div class="action-buttons">
                <a href="?step=database" class="btn btn-secondary">← Back</a>
                <button type="submit" name="create_extensions" class="btn btn-primary">Create Extensions →</button>
            </div>
        </form>
    </div>
    <script>
    // Add extension row logic here
    </script>
    <?php
    $this->renderFooter();
}
```

### Task 2: Add Trunk Configuration Method (25 min)
**Location:** `install-enhanced.php` - Add method `configureTrunks()`

**What to build:**
- Trunk provider selection (CallCentric, Google Voice, Custom)
- Form fields that auto-fill based on provider
- Test registration button (AJAX)
- Save to database and JSON config file
- Link to Google Voice step if selected

**Pre-fill Data:**
- **CallCentric:**
  - Host: `sip.callcentric.com`
  - Port: `5060`
  - Transport: `UDP`
  - User enters: username (1777XXXXXXX), password, channels

### Task 3: Add Google Voice Method (20 min)
**Location:** `install-enhanced.php` - Add method `configureGoogleVoice()`

**What to build:**
- OAuth2 credential upload form
- File upload for service account JSON
- Test API connection button
- Save to `/home/flexpbxuser/public_html/credentials/google-voice-credentials.json`
- **Make this step optional** with prominent "Skip" button

### Task 4: Add Inbound Routing Method (25 min)
**Location:** `install-enhanced.php` - Add method `configureInboundRouting()`

**What to build:**
- DID entry form
- Trunk selection dropdown (from previous step)
- Destination type selector (Extension, IVR, Voicemail, Conference)
- Conditional destination value field
- Business hours toggle
- Generate dialplan entries for `/etc/asterisk/extensions.conf`

### Task 5: Update Installation Method (30 min)
**Location:** `install-enhanced.php` - Update method `performInstallation()`

**What to build:**
- Create all new database tables (see INSTALLER_PROGRESS.md for SQL)
- Write Asterisk configuration files:
  - `/etc/asterisk/extconfig.conf` - Database mappings
  - `/etc/asterisk/pjsip_realtime.conf` - Enable realtime
  - Update `/etc/asterisk/extensions.conf` - Add dialplan
- Set file permissions (chown asterisk:asterisk)
- Reload Asterisk modules:
  - `asterisk -rx "module reload res_config_mysql.so"`
  - `asterisk -rx "pjsip reload"`
  - `asterisk -rx "dialplan reload"`
- Test database connection
- Create success/error report

### Task 6: Test Complete Flow (30 min)
- Run installer start to finish
- Register a softphone to created extension
- Make test call between extensions
- Verify voicemail works
- Test trunk registration
- Test inbound DID routing
- Access dashboard

---

## 🗂️ Files You'll Need to Reference

### Current Work Files:
- `/home/flexpbxuser/public_html/api/install-enhanced.php` - Main file to edit
- `/home/flexpbxuser/public_html/api/INSTALLER_PROGRESS.md` - Full documentation
- `/home/flexpbxuser/public_html/api/trunk-management.php` - Reference for trunk API
- `/home/flexpbxuser/public_html/api/services/GoogleVoiceService.js` - Google Voice integration

### Configuration References:
- `/etc/asterisk/manager.conf` - AMI already configured
- `/etc/asterisk/res_config_mysql.conf` - Database connection already configured
- `/home/flexpbxuser/apps/repo/flexpbx/desktop-app/pbx-data/sip-configs/extensions.conf` - Example dialplan

### Database Info:
```
Host: localhost
Port: 3306
Database: flexpbxuser_flexpbx
User: flexpbxuser_flexpbxserver
Password: DomDomRW93!
```

---

## 📋 Database Tables to Create

Copy this SQL when building the installation method:

```sql
-- PJSIP Endpoints
CREATE TABLE IF NOT EXISTS ps_endpoints (
    id VARCHAR(40) PRIMARY KEY,
    transport VARCHAR(40) DEFAULT 'transport-udp',
    aors VARCHAR(200),
    auth VARCHAR(40),
    context VARCHAR(40) DEFAULT 'flexpbx-internal',
    disallow VARCHAR(200) DEFAULT 'all',
    allow VARCHAR(200) DEFAULT 'ulaw,alaw,gsm,g729',
    direct_media VARCHAR(3) DEFAULT 'no',
    mailboxes VARCHAR(100),
    callerid VARCHAR(100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- PJSIP Authentication
CREATE TABLE IF NOT EXISTS ps_auths (
    id VARCHAR(40) PRIMARY KEY,
    auth_type VARCHAR(20) DEFAULT 'userpass',
    password VARCHAR(80),
    username VARCHAR(40)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- PJSIP AORs
CREATE TABLE IF NOT EXISTS ps_aors (
    id VARCHAR(40) PRIMARY KEY,
    max_contacts INT DEFAULT 1,
    remove_existing VARCHAR(3) DEFAULT 'yes',
    qualify_frequency INT DEFAULT 60
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Extension Metadata
CREATE TABLE IF NOT EXISTS extensions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    extension VARCHAR(20) UNIQUE NOT NULL,
    display_name VARCHAR(100),
    email VARCHAR(100),
    voicemail_enabled BOOLEAN DEFAULT TRUE,
    voicemail_password VARCHAR(10),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Trunks
CREATE TABLE IF NOT EXISTS trunks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) UNIQUE NOT NULL,
    type ENUM('sip', 'pjsip', 'googlevoice') NOT NULL,
    host VARCHAR(255),
    port INT DEFAULT 5060,
    username VARCHAR(100),
    password VARCHAR(255),
    context VARCHAR(40) DEFAULT 'from-trunk',
    max_channels INT DEFAULT 2,
    is_active BOOLEAN DEFAULT TRUE,
    config JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Inbound Routes
CREATE TABLE IF NOT EXISTS inbound_routes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    did VARCHAR(20) NOT NULL,
    trunk_id INT,
    destination_type ENUM('extension', 'ivr', 'voicemail', 'conference') NOT NULL,
    destination_value VARCHAR(100),
    description VARCHAR(255),
    business_hours_enabled BOOLEAN DEFAULT FALSE,
    after_hours_destination VARCHAR(100),
    priority INT DEFAULT 1,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (trunk_id) REFERENCES trunks(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## 🧪 Testing Steps

After completing all tasks, test in this order:

1. **Access Installer:**
   - Go to: `https://flexpbx.devinecreations.net/api/install-enhanced.php`
   - Verify welcome page loads

2. **Follow Installation Flow:**
   - Click through requirements check
   - Verify Asterisk detection shows v18.12.1
   - Enter database credentials (use existing config)
   - Create extensions 2000-2005
   - Configure CallCentric trunk (or skip)
   - Skip Google Voice (optional)
   - Add a test DID routing to extension 2001
   - Complete installation

3. **Verify Database:**
   ```bash
   /usr/bin/mariadb -u flexpbxuser_flexpbxserver -p'DomDomRW93!' flexpbxuser_flexpbx -e "SHOW TABLES;"
   # Should show new tables: ps_endpoints, ps_auths, ps_aors, extensions, trunks, inbound_routes

   /usr/bin/mariadb -u flexpbxuser_flexpbxserver -p'DomDomRW93!' flexpbxuser_flexpbx -e "SELECT * FROM extensions;"
   # Should show created extensions
   ```

4. **Verify Asterisk:**
   ```bash
   asterisk -rx "pjsip show endpoints"
   # Should show registered endpoints

   asterisk -rx "dialplan show flexpbx-internal"
   # Should show extension dialplan
   ```

5. **Test Extension Registration:**
   - Download a softphone (Zoiper, Linphone, etc.)
   - Configure with:
     - Server: flexpbx.devinecreations.net
     - Extension: 2001
     - Password: [from installer]
     - Port: 5060
   - Verify registration successful

6. **Test Call:**
   - From extension 2001, dial 2002
   - Should ring and connect
   - Test voicemail by dialing *97

---

## 💾 Backup Commands

Before making major changes:

```bash
# Backup current enhanced installer
cp /home/flexpbxuser/public_html/api/install-enhanced.php \
   /home/flexpbxuser/public_html/api/install-enhanced.php.backup-$(date +%Y%m%d-%H%M%S)

# Backup Asterisk configs before modification
tar -czf /home/flexpbxuser/asterisk-configs-backup-$(date +%Y%m%d-%H%M%S).tar.gz /etc/asterisk/

# Backup database
mysqldump -u root -p flexpbxuser_flexpbx > /home/flexpbxuser/flexpbx-db-backup-$(date +%Y%m%d-%H%M%S).sql
```

---

## 🐛 Common Issues & Solutions

### Issue 1: Database Permission Denied
**Error:** `CREATE TABLE permission denied`

**Solution:**
```sql
GRANT CREATE, ALTER, DROP ON flexpbxuser_flexpbx.* TO 'flexpbxuser_flexpbxserver'@'localhost';
FLUSH PRIVILEGES;
```

### Issue 2: Can't Write to /etc/asterisk/
**Error:** `Permission denied` when writing config files

**Solution:** Run installer with sudo or pre-create files:
```bash
sudo touch /etc/asterisk/pjsip_realtime.conf
sudo chown asterisk:asterisk /etc/asterisk/pjsip_realtime.conf
sudo chmod 644 /etc/asterisk/pjsip_realtime.conf
```

### Issue 3: Extensions Don't Register
**Error:** Softphone can't register

**Solution:** Check Asterisk realtime is working:
```bash
asterisk -rx "pjsip show endpoints"
asterisk -rx "database show"
mysql -e "SELECT * FROM ps_endpoints" flexpbxuser_flexpbx
```

Verify `/etc/asterisk/extconfig.conf` has:
```
[settings]
ps_endpoints => mysql,flexpbx,ps_endpoints
ps_auths => mysql,flexpbx,ps_auths
ps_aors => mysql,flexpbx,ps_aors
```

---

## 📞 Support Resources

- **Asterisk Docs:** https://docs.asterisk.org/
- **PJSIP Config:** https://docs.asterisk.org/Configuration/Channel-Drivers/SIP/Configuring-res_pjsip/
- **FlexPBX Repo:** https://github.com/raywonder/flexpbx

---

**Estimated Time to Complete:** 2-3 hours
**Complexity:** Medium (mostly UI and database work)
**Dependencies:** Asterisk 18.12.1 (already installed ✅)

Good luck! 🚀
