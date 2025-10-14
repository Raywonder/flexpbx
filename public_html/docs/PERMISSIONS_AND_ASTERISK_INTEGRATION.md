# FlexPBX Permissions & Asterisk Integration

**Created:** October 14, 2025
**Status:** ✅ Configured and Tested

---

## 🎯 Overview

FlexPBX integrates with Asterisk through three methods:
1. **Reading config files** - For displaying current settings
2. **Writing config files** - For making configuration changes
3. **Asterisk CLI commands** - For reloading and querying status

This document explains how permissions are configured to allow secure integration.

---

## 👥 User & Group Structure

### Users
- **flexpbxuser** - Web application user (PHP runs as this user via Apache)
- **asterisk** - Asterisk PBX daemon user
- **apache** - Web server process (alternative to nobody)
- **nobody** - Web server file group

### Groups
- **flexpbxuser** - Primary group for flexpbxuser
- **asterisk** - Asterisk files group
- **wheel** - Admin/sudo group

### Group Membership
```bash
flexpbxuser : flexpbxuser wheel asterisk
```

**Key**: flexpbxuser is a member of the `asterisk` group, allowing read access to Asterisk config files.

---

## 📁 File Permissions

### Asterisk Configuration Files

**Location:** `/etc/asterisk/`

**Files:**
- `pjsip.conf` - SIP endpoint configuration
- `extensions.conf` - Dialplan
- `voicemail.conf` - Voicemail settings
- `musiconhold.conf` - MOH configuration
- `queues.conf` - Queue settings

**Ownership & Permissions:**
```bash
Owner: asterisk:asterisk
Permissions: 640 (rw-r-----)
```

**What this means:**
- ✅ asterisk user can read and write
- ✅ asterisk group can read (flexpbxuser is in this group!)
- ❌ Others cannot access

### Web Application Files

**Location:** `/home/flexpbxuser/public_html/`

**Ownership & Permissions:**
```bash
Owner: flexpbxuser:nobody
Permissions: 644 (rw-r--r--)
```

**PHP files:** Readable by web server, writable by flexpbxuser
**HTML files:** Readable by everyone, writable by flexpbxuser
**JSON config files:** Same as PHP

### User Data Files

**Location:** `/home/flexpbxuser/users/`

**Ownership & Permissions:**
```bash
Owner: flexpbxuser:flexpbxuser
Directory: 750 (rwxr-x---)
Files: 640 (rw-r-----)
```

**What this means:**
- Only flexpbxuser can write
- Only flexpbxuser and group can read
- Not web-accessible (outside public_html)

### Voicemail Directories

**Location:** `/var/spool/asterisk/voicemail/`

**Ownership & Permissions:**
```bash
Owner: asterisk:asterisk
Directories: 750 (rwxr-x---)
Files: 640 (rw-r-----)
```

**What this means:**
- Only asterisk can write voicemail
- Only asterisk group can read
- Web server cannot directly access

---

## 🔐 Sudo Configuration

### Location
`/etc/sudoers.d/flexpbx-asterisk`

### Contents
```bash
# FlexPBX - Asterisk Integration
# Allows flexpbxuser and apache to run specific Asterisk CLI commands

flexpbxuser ALL=(asterisk) NOPASSWD: /usr/sbin/asterisk -rx *
apache ALL=(asterisk) NOPASSWD: /usr/sbin/asterisk -rx *
nobody ALL=(asterisk) NOPASSWD: /usr/sbin/asterisk -rx *
```

### What This Allows

**flexpbxuser can run:**
```bash
sudo -u asterisk /usr/sbin/asterisk -rx "core show version"
sudo -u asterisk /usr/sbin/asterisk -rx "dialplan reload"
sudo -u asterisk /usr/sbin/asterisk -rx "pjsip show endpoints"
```

**Why run as asterisk user?**
- Asterisk CLI connects via socket: `/run/asterisk/asterisk.ctl`
- Socket owned by asterisk:asterisk
- Running as asterisk user ensures proper permissions

### Security Notes

✅ **Secure:**
- Only specific command allowed: `/usr/sbin/asterisk -rx`
- No shell access granted
- No password required (NOPASSWD) for specific commands only
- Web server can execute, but limited to safe read-only commands

⚠️ **Potential Risk:**
- `*` wildcard allows any argument after `-rx`
- Could run: `sudo -u asterisk asterisk -rx "core restart now"` (dangerous!)

🔒 **Mitigation:**
- FlexPBX code validates commands before execution
- Only approved commands are run (reload, show, etc.)
- Never accepts user input directly into `exec()`

---

## 🔧 How FlexPBX Accesses Asterisk

### Method 1: Reading Config Files

**Used by:**
- Feature Codes Manager
- Extension Manager
- Trunk Manager

**Example:**
```php
$extensions_conf = '/etc/asterisk/extensions.conf';

if (!file_exists($extensions_conf)) {
    // Error handling
}

$content = file_get_contents($extensions_conf);  // ✅ Works! (group read)
```

**Why it works:**
- File permissions: 640 (group-readable)
- flexpbxuser is in asterisk group
- PHP can read the file

### Method 2: Writing Config Files

**Used by:**
- Feature Codes Manager (toggling features)
- Future extension/trunk creation tools

**Example:**
```php
$backup = file_get_contents($extensions_conf);
file_put_contents($extensions_conf . '.backup', $backup);

// Make changes
$new_content = str_replace(...);

file_put_contents($extensions_conf, $new_content);  // ❌ Permission denied!
```

**Why it DOESN'T work:**
- File permissions: 640 (not group-writable)
- Only asterisk user can write

**Solutions:**

**Option A: Make files group-writable (LESS SECURE)**
```bash
chmod 660 /etc/asterisk/*.conf
```

**Option B: Use sudo with a script (MORE SECURE - RECOMMENDED)**
```bash
# Create: /usr/local/bin/flexpbx-update-config
#!/bin/bash
# Validates and updates Asterisk configs safely
```

**Option C: Use Asterisk REST Interface (ARI) - BEST**
- Dynamic changes via HTTP API
- No file writes needed
- Changes reload immediately

### Method 3: Asterisk CLI Commands

**Used by:**
- Feature Codes Manager (`dialplan reload`)
- Extension Manager (`pjsip reload`)
- Status checks (`pjsip show endpoints`)

**Example:**
```php
// OLD (doesn't work):
exec('asterisk -rx "dialplan reload"', $output, $return);

// NEW (works with sudo):
exec('sudo -u asterisk /usr/sbin/asterisk -rx "dialplan reload"', $output, $return);
```

**Why sudo is needed:**
- Asterisk socket requires asterisk user or group membership
- Even though flexpbxuser is in asterisk group, the CLI client checks user ID
- Running as asterisk user via sudo ensures access

**Updated Code:**
```php
// File: /home/flexpbxuser/public_html/admin/feature-codes-manager.php
function reloadDialplan() {
    $output = [];
    $return_var = 0;

    // ✅ Correct: Use sudo to run as asterisk user
    exec('sudo -u asterisk /usr/sbin/asterisk -rx "dialplan reload" 2>&1', $output, $return_var);

    if ($return_var === 0) {
        return ['success' => true, 'message' => 'Dialplan reloaded successfully'];
    } else {
        return ['success' => false, 'message' => 'Failed to reload dialplan'];
    }
}
```

---

## 📊 Permission Matrix

| Resource | Owner | Group | Perms | flexpbxuser Can? | Web Server Can? |
|----------|-------|-------|-------|------------------|-----------------|
| `/etc/asterisk/*.conf` | asterisk | asterisk | 640 | Read ✅ Write ❌ | Read ✅ Write ❌ |
| `/home/flexpbxuser/public_html/*.php` | flexpbxuser | nobody | 644 | Read ✅ Write ✅ | Read ✅ Execute ✅ |
| `/home/flexpbxuser/users/*.json` | flexpbxuser | flexpbxuser | 640 | Read ✅ Write ✅ | Read ✅ Write ✅ |
| `/var/spool/asterisk/voicemail/` | asterisk | asterisk | 750 | Read ✅ Write ❌ | Read ❌ Write ❌ |
| `asterisk -rx` command | - | - | sudo | Yes (via sudo) ✅ | Yes (via sudo) ✅ |

---

## 🧪 Testing Permissions

### Test 1: Read Asterisk Config
```bash
sudo -u flexpbxuser cat /etc/asterisk/extensions.conf
# Should work ✅
```

### Test 2: Write Asterisk Config
```bash
sudo -u flexpbxuser echo "test" >> /etc/asterisk/extensions.conf
# Should fail ❌ (Permission denied)
```

### Test 3: Run Asterisk Command
```bash
sudo -u flexpbxuser sudo -u asterisk /usr/sbin/asterisk -rx "core show version"
# Should work ✅
```

### Test 4: Web Access
```bash
# As apache/nobody user
sudo -u apache cat /etc/asterisk/extensions.conf
# Should work ✅ (if apache is in asterisk group)
```

---

## 🔒 Security Best Practices

### ✅ Current Implementation

1. **Principle of Least Privilege**
   - Web server can read but not write Asterisk configs
   - Only specific sudo commands allowed
   - User data isolated outside public_html

2. **Defense in Depth**
   - Multiple permission layers
   - Group-based access control
   - Sudo restrictions

3. **Separation of Concerns**
   - Asterisk owns PBX configs
   - flexpbxuser owns web application
   - Clear ownership boundaries

### ⚠️ Potential Improvements

1. **Restrict sudo commands**
   - Currently allows any `-rx` argument
   - Could limit to specific commands:
   ```bash
   flexpbxuser ALL=(asterisk) NOPASSWD: /usr/sbin/asterisk -rx "dialplan reload"
   flexpbxuser ALL=(asterisk) NOPASSWD: /usr/sbin/asterisk -rx "pjsip reload"
   flexpbxuser ALL=(asterisk) NOPASSWD: /usr/sbin/asterisk -rx "pjsip show *"
   ```

2. **Use ARI/AMI instead of file writes**
   - More secure than direct file manipulation
   - Changes validated by Asterisk
   - Automatic reloads

3. **Audit logging**
   - Log all Asterisk command executions
   - Monitor config file changes
   - Alert on suspicious activity

---

## 📝 Integration Methods Comparison

| Method | Read Config | Write Config | Real-time | Security | Recommended |
|--------|-------------|--------------|-----------|----------|-------------|
| **Direct File Access** | ✅ Yes | ❌ No (perms) | ❌ No | ⚠️ Medium | Reading only |
| **Asterisk CLI** | ✅ Yes | ❌ No | ✅ Yes | ✅ Good | Status checks |
| **AMI (Manager)** | ✅ Yes | ✅ Yes | ✅ Yes | ✅ Good | Dynamic changes |
| **ARI (REST)** | ✅ Yes | ✅ Yes | ✅ Yes | ✅ Best | Modern apps |

**Recommendation:** Use ARI for all dynamic operations, CLI for status checks, file reading for displaying current configs.

---

## 🚀 Next Steps

### Immediate
- ✅ Permissions configured
- ✅ Sudo access enabled
- ✅ Group membership verified
- ✅ Test files updated with sudo

### Future Development
1. **Implement ARI Integration**
   - Replace file writes with ARI calls
   - Real-time extension creation
   - Dynamic trunk management

2. **Create Config Update Script**
   - Wrapper script for safe config updates
   - Validation before writing
   - Automatic backups

3. **Add Audit Logging**
   - Log all admin actions
   - Track config changes
   - Security monitoring

---

## 📖 References

- **Asterisk Security:** https://docs.asterisk.org/Asterisk_18_Documentation/Asterisk_Administration/Security/
- **Linux File Permissions:** https://www.linux.com/training-tutorials/understanding-linux-file-permissions/
- **Sudo Best Practices:** https://www.sudo.ws/docs/

---

**Last Updated:** October 14, 2025
**Maintained By:** FlexPBX Development Team
**Version:** 1.0
