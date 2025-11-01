# FlexPBX SIP & Trunk Configuration Status

## 🚧 Current Status: Database Configuration Issue

**Date:** October 13, 2025 15:23 EDT
**Issue:** res_config_mysql not loading [flexpbx] database configuration
**Impact:** PJSIP cannot load endpoints from database

---

## ✅ Completed Work

### 1. Database Tables Created
All required tables exist in `flexpbxuser_flexpbx` database:
- ✅ `ps_endpoints` - PJSIP endpoint configuration
- ✅ `ps_auths` - Authentication credentials
- ✅ `ps_aors` - Address of Records
- ✅ `extensions` - Extension metadata
- ✅ `trunks` - Trunk definitions

### 2. Test Extensions Created in Database
4 extensions ready to use (see EXTENSION_CREDENTIALS.md):
- 2000 - Admin Extension (FlexPBX2000!)
- 2001 - Test User (FlexPBX2001!)
- 2002 - Demo Extension (FlexPBX2002!)
- 2003 - Support Extension (FlexPBX2003!)

### 3. Configuration Files Updated
- ✅ `/etc/asterisk/extconfig.conf` - PJSIP realtime mappings added
- ✅ `/etc/asterisk/sorcery.conf` - PJSIP realtime enabled
- ✅ `/etc/asterisk/res_config_mysql.conf` - [flexpbx] database connection configured

### 4. CallCentric Trunk Credentials Saved
- Username: 17778171
- Password: 860719938242
- Server: sip.callcentric.com:5060

---

## ❌ Current Problem

### Asterisk Log Errors:
```
WARNING res_config_mysql.c: MySQL RealTime: No database user found, using 'asterisk' as default.
WARNING res_config_mysql.c: MySQL RealTime: No database password found, using 'asterisk' as default.
ERROR sorcery.c: Wizard 'realtime' failed to open mapping for object type 'endpoint'
ERROR res_pjsip.c: Failed to initialize SIP 'system' configuration section
ERROR loader.c: res_pjsip declined to load.
```

### Root Cause:
The `res_config_mysql` module is not properly loading the `[flexpbx]` database connection configuration from `/etc/asterisk/res_config_mysql.conf`. It's falling back to default values (database: asterisk, user: asterisk) which don't exist.

---

## 🔧 Solution Options

### Option 1: Use Default Database Connection (Recommended)
Create a `[general]` section in `res_config_mysql.conf` with FlexPBX credentials:

```ini
[general]
dbhost = localhost
dbname = flexpbxuser_flexpbx
dbuser = flexpbxuser_flexpbxserver
dbpass = DomDomRW93!
dbport = 3306
dbsock = /var/lib/mysql/mysql.sock
dbcharset = utf8mb4
```

Then update `extconfig.conf` to use default connection:
```ini
ps_endpoints => mysql,ps_endpoints
ps_auths => mysql,ps_auths
ps_aors => mysql,ps_aors
```

### Option 2: Debug res_config_mysql Loading
Enable verbose logging to see why [flexpbx] section isn't loading:
```bash
asterisk -cvvvvv
```

### Option 3: Use Static PJSIP Configuration
Instead of realtime, create static endpoints in `/etc/asterisk/pjsip.conf`:
```ini
[2000]
type=endpoint
context=flexpbx-internal
disallow=all
allow=ulaw,alaw
auth=2000
aors=2000

[2000]
type=auth
auth_type=userpass
password=FlexPBX2000!
username=2000

[2000]
type=aor
max_contacts=1
```

---

## 📝 Next Steps (Priority Order)

### Immediate Fix:
1. **Add [general] section to res_config_mysql.conf**
   - Copy [flexpbx] settings to [general]
   - Restart Asterisk
   - Verify PJSIP loads: `asterisk -rx "pjsip show endpoints"`

2. **If still failing, use static PJSIP config**
   - Manually create endpoint definitions in pjsip.conf
   - Test SIP registration
   - Move to realtime later

### After PJSIP Works:
3. **Test SIP Registration**
   - Configure Zoiper/Linphone with extension 2001
   - Server: flexpbx.devinecreations.net:5060
   - Verify registration: `asterisk -rx "pjsip show endpoints"`

4. **Create CallCentric Trunk**
   - Add trunk to database or pjsip.conf
   - Configure registration
   - Test outbound calling

5. **Configure Dialplan**
   - Update `/etc/asterisk/extensions.conf`
   - Add extension-to-extension calling
   - Add trunk outbound routing
   - Reload dialplan: `asterisk -rx "dialplan reload"`

---

## 📊 Configuration Summary

### Database Connection:
```
Host: localhost
Port: 3306
Database: flexpbxuser_flexpbx
User: flexpbxuser_flexpbxserver
Password: DomDomRW93!
Socket: /var/lib/mysql/mysql.sock
```

### Asterisk Status:
- Version: 18.12.1
- Service: Running
- AMI: Configured (port 5038, localhost only)
- Transports: UDP & TCP on port 5060
- **PJSIP Module:** ❌ Failed to load (database connection issue)

### Files Modified:
```
/etc/asterisk/extconfig.conf        - PJSIP realtime mappings
/etc/asterisk/sorcery.conf          - Realtime wizard configuration
/etc/asterisk/res_config_mysql.conf - Database connection
/etc/asterisk/pjsip_sorcery.conf    - Created (not needed)
```

### Files Created:
```
/home/flexpbxuser/public_html/api/EXTENSION_CREDENTIALS.md
/home/flexpbxuser/public_html/api/install-enhanced.php
/home/flexpbxuser/public_html/api/INSTALLER_PROGRESS.md
/home/flexpbxuser/public_html/api/NEXT_STEPS.md
```

---

## 🐛 Debugging Commands

```bash
# Check Asterisk status
systemctl status asterisk

# View real-time Asterisk console
asterisk -rvvv

# Check database connectivity from Asterisk
asterisk -rx "realtime load ps_endpoints id 2000"

# View PJSIP endpoints (when working)
asterisk -rx "pjsip show endpoints"

# Check loaded modules
asterisk -rx "module show like res_config_mysql"
asterisk -rx "module show like res_pjsip"

# Test database connection via PHP
php -r "
\$pdo = new PDO('mysql:host=localhost;dbname=flexpbxuser_flexpbx', 'flexpbxuser_flexpbxserver', 'DomDomRW93!');
\$stmt = \$pdo->query('SELECT COUNT(*) FROM ps_endpoints');
echo 'Endpoints in DB: ' . \$stmt->fetchColumn() . \"\n\";
"

# View Asterisk logs
tail -f /var/log/asterisk/messages
```

---

## 💡 Key Insights

1. **Database tables are ready** - All PJSIP tables exist with test data
2. **Configuration files are correct** - Settings match database credentials
3. **Module loading issue** - res_config_mysql not reading configuration properly
4. **Quick workaround available** - Can use [general] section or static config

---

## 📞 When SIP is Working

### Test Extension Registration:
1. Open Zoiper or Linphone
2. Configure account:
   - Username: 2001
   - Password: FlexPBX2001!
   - Domain: flexpbx.devinecreations.net
   - Port: 5060
3. Register
4. Check Asterisk: `asterisk -rx "pjsip show endpoints"`
5. Call from 2001 to 2002

### Test CallCentric Trunk:
1. Dial 9 + 10-digit number from extension
2. Should route through CallCentric
3. Check call status: `asterisk -rx "core show channels"`

---

**Last Updated:** October 13, 2025 15:25 EDT
**Next Action:** Add [general] section to res_config_mysql.conf and restart Asterisk
**Estimated Time to Fix:** 10-15 minutes
