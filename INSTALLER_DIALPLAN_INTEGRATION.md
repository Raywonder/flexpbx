# FlexPBX Installer - Asterisk Dialplan Integration

## Overview

The FlexPBX installer now includes automatic configuration of Asterisk dialplan with voicemail feature codes and testing extensions. This ensures all new installations have working voicemail access from day one.

---

## Files Added to Repository

### 1. Default Dialplan Template
**File**: `config/asterisk-dialplan-defaults.conf`
**Purpose**: Template for new Asterisk installations
**Contains**:
- `[flexpbx-internal]` context configuration
- Voicemail feature codes (*97, *98)
- Echo test (*43)
- Optional call forwarding codes (commented out)

### 2. Configuration Script
**File**: `scripts/configure-asterisk-dialplan.sh`
**Purpose**: Automated dialplan setup
**Features**:
- Automatic backup of existing configuration
- Smart merge (adds feature codes to existing dialplan)
- Full replacement (if no existing dialplan)
- Syntax validation
- Asterisk reload
- Color-coded output with status messages

---

## Integration with Installer

### Option 1: Add to install.php (Recommended)

Add this function after the `initializeServices()` method:

```php
private function configureAsteriskDialplan() {
    $scriptPath = dirname(__DIR__) . '/scripts/configure-asterisk-dialplan.sh';

    if (!file_exists($scriptPath)) {
        $this->logProgress("⚠ Asterisk dialplan script not found - skipping");
        return;
    }

    $this->logProgress("📞 Configuring Asterisk dialplan...");

    // Run the configuration script
    $output = [];
    $returnVar = 0;
    exec("sudo bash {$scriptPath} 2>&1", $output, $returnVar);

    if ($returnVar === 0) {
        $this->logProgress("✅ Asterisk dialplan configured with voicemail feature codes");
        $this->logProgress("   • *97 - Access your voicemail");
        $this->logProgress("   • *98 - Access any voicemail box");
        $this->logProgress("   • *43 - Echo test");
    } else {
        $this->logProgress("⚠ Asterisk dialplan configuration completed with warnings");
        $this->logProgress("   Feature codes will be active after Asterisk starts");
    }
}
```

Then call it in `performInstallation()` after Step 8:

```php
// Step 8: Create directory structure and initialize services
$this->logProgress("📁 Step 8/9: Setting up directory structure and services...");
$this->createDirectoryStructure();
$this->initializeServices($dbConfig);

// Step 9: Configure Asterisk dialplan (NEW)
$this->logProgress("📞 Step 9/9: Configuring Asterisk dialplan...");
$this->configureAsteriskDialplan();
```

### Option 2: Manual Script Execution

If installer integration is not desired, administrators can run the script manually:

```bash
cd /home/flexpbxuser/apps/flexpbx
sudo bash scripts/configure-asterisk-dialplan.sh
```

---

## Feature Codes Included

### Voicemail Access
- **\*97** - Access your own voicemail mailbox
  - Uses caller ID to determine which mailbox to access
  - Prompts for password
  - Standard voicemail menu

- **\*98** - Access any voicemail mailbox
  - Prompts for mailbox number
  - Prompts for password
  - Useful for administrators or shared mailboxes

### Testing
- **\*43** - Echo test
  - Answers call
  - Plays back your voice in real-time
  - Verifies audio path is working
  - Press # to exit

### Optional (Commented Out)
- **\*72** - Enable call forwarding
- **\*73** - Disable call forwarding
- **\*XX** - Direct dial to any extension's voicemail

---

## Script Behavior

### Scenario 1: New Installation (No Existing Dialplan)
1. Creates new `extensions.conf` from template
2. Sets proper permissions (asterisk:asterisk, 640)
3. Reloads Asterisk dialplan
4. Verifies feature codes are active

### Scenario 2: Existing Dialplan Found
1. Backs up existing configuration to `/etc/asterisk/backups/`
2. Checks if feature codes already exist
3. If missing, adds feature codes to `[flexpbx-internal]` context
4. Preserves all existing extensions and custom configuration
5. Reloads Asterisk dialplan

### Scenario 3: Asterisk Not Running
1. Creates/updates configuration files
2. Sets permissions
3. Shows warning that dialplan will load on Asterisk start
4. Script completes successfully

---

## Configuration Files

### /etc/asterisk/extensions.conf (After Installation)

```
[general]
static=yes
writeprotect=no
clearglobalvars=no

[globals]

[flexpbx-internal]
; Feature Codes - Voicemail
exten => *97,1,NoOp(VoiceMail Access - Own Mailbox)
 same => n,Answer()
 same => n,Wait(1)
 same => n,VoiceMailMain(${CALLERID(num)}@flexpbx)
 same => n,Hangup()

exten => *98,1,NoOp(VoiceMail Access - Any Mailbox)
 same => n,Answer()
 same => n,Wait(1)
 same => n,VoiceMailMain(@flexpbx)
 same => n,Hangup()

; Feature Codes - Testing
exten => *43,1,NoOp(Echo Test)
 same => n,Answer()
 same => n,Wait(1)
 same => n,Echo()
 same => n,Hangup()

; Extension-to-Extension Dialing (added by extension management)
exten => 2000,1,NoOp(Call to 2000)
 same => n,Dial(PJSIP/2000,20)
 same => n,Hangup()

; ... more extensions ...
```

---

## Testing After Installation

### 1. Verify Dialplan Loaded
```bash
asterisk -rx "dialplan show flexpbx-internal" | grep "*97"
```

Expected output:
```
'*97' =>          1. NoOp(VoiceMail Access - Own Mailbox)
```

### 2. Test Feature Codes
From any registered extension:
1. Dial **\*43** - You should hear yourself (echo test)
2. Dial **\*97** - Should prompt for voicemail password
3. Dial **\*98** - Should ask for mailbox number

### 3. Verify File Permissions
```bash
ls -l /etc/asterisk/extensions.conf
```

Expected:
```
-rw-r----- 1 asterisk asterisk 1234 Nov 8 12:00 /etc/asterisk/extensions.conf
```

---

## Troubleshooting

### Feature Codes Don't Work

**Symptom**: Dialing *97 shows "Address Not Found"

**Diagnosis**:
```bash
# Check if dialplan is loaded
asterisk -rx "dialplan show flexpbx-internal"

# Check if extensions are in correct context
asterisk -rx "pjsip show endpoint 2000" | grep context
```

**Solution**:
```bash
# Reload dialplan
asterisk -rx "dialplan reload"

# Or rerun configuration script
sudo bash /home/flexpbxuser/apps/flexpbx/scripts/configure-asterisk-dialplan.sh
```

### Permission Denied Errors

**Symptom**: Script fails with permission errors

**Diagnosis**:
```bash
ls -l /etc/asterisk/extensions.conf
```

**Solution**:
```bash
# Run script with sudo
sudo bash /home/flexpbxuser/apps/flexpbx/scripts/configure-asterisk-dialplan.sh

# Or fix permissions manually
sudo chown asterisk:asterisk /etc/asterisk/extensions.conf
sudo chmod 640 /etc/asterisk/extensions.conf
```

### Script Not Found

**Symptom**: Installer can't find configuration script

**Diagnosis**:
```bash
ls -l /home/flexpbxuser/apps/flexpbx/scripts/configure-asterisk-dialplan.sh
```

**Solution**:
```bash
# Ensure file exists and is executable
chmod +x /home/flexpbxuser/apps/flexpbx/scripts/configure-asterisk-dialplan.sh

# Pull latest from git
cd /home/flexpbxuser/apps/flexpbx
git pull origin main
```

---

## Backup and Restore

### Automatic Backups
The script automatically creates backups before making changes:
```
/etc/asterisk/backups/extensions.conf.backup.YYYYMMDD_HHMMSS
```

### Manual Backup
```bash
sudo cp /etc/asterisk/extensions.conf /etc/asterisk/extensions.conf.backup
```

### Restore from Backup
```bash
# List available backups
ls -lt /etc/asterisk/backups/

# Restore specific backup
sudo cp /etc/asterisk/backups/extensions.conf.backup.20251108_120000 /etc/asterisk/extensions.conf

# Reload dialplan
sudo asterisk -rx "dialplan reload"
```

---

## Adding Custom Extensions

To add extensions after installation:

### Method 1: Edit Configuration File
```bash
sudo nano /etc/asterisk/extensions.conf
```

Add under `[flexpbx-internal]`:
```
exten => 2001,1,NoOp(Call to Extension 2001)
 same => n,Dial(PJSIP/2001,20)
 same => n,Hangup()
```

Reload:
```bash
sudo asterisk -rx "dialplan reload"
```

### Method 2: Use FlexPBX Admin Panel
1. Navigate to Extensions management
2. Click "Add Extension"
3. Fill in extension details
4. System automatically updates dialplan

---

## Security Considerations

### File Permissions
- extensions.conf: 640 (asterisk:asterisk)
- Only asterisk user can read/write
- Other users cannot access

### Feature Code Security
- *97: Requires voicemail password (user's mailbox)
- *98: Requires mailbox number AND password
- No anonymous voicemail access

### Audit Logging
All calls to feature codes are logged in:
```
/var/log/asterisk/full
```

Monitor with:
```bash
tail -f /var/log/asterisk/full | grep VoiceMail
```

---

## Future Enhancements

Planned for future versions:
1. Web-based dialplan editor
2. Visual dialplan builder
3. More feature codes (*67 caller ID block, *69 callback, etc.)
4. Time-based routing
5. IVR menu builder
6. Call recording controls (*1 to record)

---

## Support

### Documentation
- FlexPBX Documentation: `/home/flexpbxuser/documentation/`
- Asterisk Dialplan Guide: https://www.asterisk.org/
- VoiceMail Documentation: https://wiki.asterisk.org/wiki/display/AST/Voicemail

### Getting Help
- GitHub Issues: https://github.com/devinecreations/flexpbx
- Email: info@devinecreations.net
- Community Forum: (coming soon)

---

**Version**: 1.0
**Last Updated**: November 8, 2025
**Status**: Production Ready
**Tested With**: FlexPBX v1.3+, Asterisk 18+
