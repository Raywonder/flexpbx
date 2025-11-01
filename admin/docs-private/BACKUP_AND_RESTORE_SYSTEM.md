# FlexPBX Backup & Restore System

## Overview

FlexPBX v1.0 includes a comprehensive backup and restore system that allows you to:

- **Backup** your complete FlexPBX installation (system files, configurations, templates)
- **Restore** backups to the same or different user accounts
- **Migrate** FlexPBX between user accounts on the same server
- **Deploy** fresh installations from backup files

The system uses two backup formats:
- **`.flx`** - System configuration backup (recommended for most users)
- **`.flxx`** - Extended backup with additional templates and schemas

## 🔒 Privacy & Security

### What Gets Backed Up ✓

- System PHP files (API endpoints, portals, helpers)
- Service worker configuration
- Documentation files
- Asterisk configuration templates
- Sudo configuration
- System scripts
- Default settings and templates

### What NEVER Gets Backed Up ✗

- **Personal user data** (names, emails, phone numbers)
- **Password hashes** (real passwords)
- **Session tokens**
- **Push notification subscriptions** (device-specific)
- **Temporary passwords**
- **Cache files**
- **Log files**
- **Voicemail recordings**
- **Call recordings**
- **CDR (Call Detail Records)**

All backups are **sanitized** to remove personal information, making them safe to share or distribute for fresh installations.

---

## 📦 Backup Tools

### 1. flexpbx-backup

Create system backups in `.flx` or `.flxx` format.

#### Usage

```bash
flexpbx-backup [OPTIONS]

Options:
  -t, --type <flx|flxx>    Backup type (default: flx)
  -o, --output <path>      Output directory (default: ~/backup/)
  -c, --compress           Compress backup with gzip
  -v, --verbose            Verbose output
  -h, --help               Show help
```

#### Examples

```bash
# Create basic system backup
flexpbx-backup

# Create compressed extended backup
flexpbx-backup -t flxx -c

# Verbose mode with custom output directory
flexpbx-backup -o /tmp/backups -v

# Full backup with all options
flexpbx-backup -t flxx -c -v -o ~/my-backups
```

#### Output

Backups are saved as:
- `flexpbx-YYYY-MM-DD_HH-MM-SS.flx` (uncompressed)
- `flexpbx-YYYY-MM-DD_HH-MM-SS.flx.gz` (compressed)

A symlink `latest.flx` always points to the most recent backup.

---

### 2. flexpbx-restore

Restore FlexPBX from `.flx` or `.flxx` backup files.

#### Usage

```bash
flexpbx-restore [OPTIONS] <backup_file>

Options:
  -m, --mode <fresh|merge|migrate>  Restore mode (default: merge)
      fresh    - Fresh installation (overwrites everything)
      merge    - Merge with existing system (keeps user data)
      migrate  - Migrate to new user account
  -u, --user <username>              Target username for migration
  -v, --verbose                      Verbose output
  -y, --yes                          Skip confirmation prompts
  -h, --help                         Show help
```

#### Restore Modes

##### Fresh Installation
Complete fresh install, overwrites all existing files.

```bash
flexpbx-restore -m fresh backup.flx
```

**Use cases:**
- New server setup
- Complete system reset
- Installing FlexPBX for first time

**Warning:** This will delete all existing FlexPBX data!

##### Merge Mode (Default)
Merges system files with existing installation, preserves user data.

```bash
flexpbx-restore backup.flx
# or explicitly:
flexpbx-restore -m merge backup.flx
```

**Use cases:**
- Upgrading system files
- Restoring after accidental file deletion
- Applying updates from backup

**Safety:** Creates backup of existing user data before merging.

##### Migration Mode
Install FlexPBX to a different user account.

```bash
flexpbx-restore -m migrate -u newuser backup.flx
```

**Use cases:**
- Moving FlexPBX to different cPanel account
- Setting up multiple FlexPBX instances
- Server migration

**Requirements:**
- Target user must exist
- Current user needs sudo access (if different from target)

#### Examples

```bash
# Restore to current user (merge mode)
flexpbx-restore ~/backup/flexpbx-2025-10-14.flx

# Fresh installation with auto-confirm
flexpbx-restore -m fresh -y backup.flx

# Migrate to different user
flexpbx-restore -m migrate -u flexpbx2 backup.flx

# Restore compressed backup
flexpbx-restore ~/backup/latest.flx.gz

# Verbose migration
flexpbx-restore -m migrate -u newuser -v backup.flx
```

---

### 3. flexpbx-migrate

Simplified tool for migrating FlexPBX between user accounts.

#### Usage

```bash
flexpbx-migrate [OPTIONS] <target_username>

Options:
  -s, --source <username>   Source username (default: current user)
  -b, --backup-dir <path>   Custom backup directory
  -k, --keep-backup         Keep backup after migration
  -v, --verbose             Verbose output
  -y, --yes                 Skip confirmation prompts
  -h, --help                Show help
```

#### Examples

```bash
# Migrate from current user to newuser
flexpbx-migrate newuser

# Migrate from olduser to newuser
flexpbx-migrate -s olduser newuser

# Verbose migration with backup retention
flexpbx-migrate -k -v newuser

# Auto-confirm migration
flexpbx-migrate -y targetuser
```

#### What It Does

1. Creates backup from source user
2. Restores to target user
3. Updates file ownership
4. Updates sudoers configuration
5. Tests permissions
6. Optionally cleans up backup

---

## 📁 File Structure

### Backup Directory Layout

```
/home/flexpbxuser/backup/
├── latest.flx                       # Symlink to latest backup
├── latest.flxx                      # Symlink to latest extended backup
├── flexpbx-2025-10-14_12-00-00.flx
├── flexpbx-2025-10-14_12-00-00.flxx
└── backups/                         # Historical backups
    ├── flexpbx-2025-10-13.flx
    └── flexpbx-2025-10-12.flx
```

### Template Directory

```
/home/flexpbxuser/templates/
├── user_template.json               # Default user account template
├── admin_template.json              # Default admin account template
└── settings_template.json           # Default system settings
```

---

## 🔍 Backup Format Details

### .flx Format (System Configuration)

JSON structure containing:

```json
{
  "version": "1.0",
  "backup_type": "system_config",
  "created": "2025-10-14 12:00:00",
  "flexpbx_version": "1.0",
  "includes": {
    "system_files": true,
    "api_endpoints": true,
    "service_worker": true,
    "notification_system": true,
    "temp_password_system": true,
    "email_validation": true,
    "sip_status_monitoring": true,
    "documentation": true,
    "asterisk_templates": true,
    "sudo_config": true
  },
  "file_count": 25,
  "files": [
    {
      "path": "public_html/service-worker.js",
      "hash": "sha256:abc123...",
      "size": 1234,
      "content": "base64_encoded_content"
    }
  ],
  "default_settings": { ... }
}
```

### .flxx Format (Extended)

Includes everything in `.flx` plus:

- User account templates
- Admin account templates
- Database schemas (if used)
- Extended configuration examples

---

## 🚀 Common Use Cases

### 1. Regular Backups

Create automated daily backups:

```bash
# Add to crontab
0 2 * * * /usr/local/bin/flexpbx-backup -c -o ~/backup/
```

### 2. Pre-Upgrade Backup

Before making system changes:

```bash
# Create backup with timestamp
flexpbx-backup -c -v
```

### 3. Server Migration

Move FlexPBX to new server:

```bash
# On old server
flexpbx-backup -t flxx -c

# Transfer backup.flx.gz to new server

# On new server (as target user)
flexpbx-restore -m fresh new-server-backup.flx.gz
```

### 4. Multi-User Setup

Deploy FlexPBX to multiple users:

```bash
# Create master backup
flexpbx-backup -t flxx -c

# Deploy to user1
flexpbx-restore -m migrate -u user1 backup.flxx

# Deploy to user2
flexpbx-restore -m migrate -u user2 backup.flxx
```

### 5. Development/Production Split

Separate environments:

```bash
# Backup production
su - flexpbx-prod
flexpbx-backup -c

# Restore to development
flexpbx-restore -m migrate -u flexpbx-dev ~/backup/latest.flx.gz
```

---

## 🔧 Troubleshooting

### Backup Fails

**Issue:** `Permission denied`

**Solution:**
```bash
# Ensure you're running as the correct user
whoami

# Check directory permissions
ls -la ~/backup/

# Create backup directory if missing
mkdir -p ~/backup/
chmod 750 ~/backup/
```

### Restore Fails - File Not Found

**Issue:** `Backup file not found`

**Solution:**
```bash
# Verify file exists
ls -lh /path/to/backup.flx

# Check if compressed
file /path/to/backup.flx

# Use absolute path
flexpbx-restore /home/flexpbxuser/backup/backup.flx
```

### Hash Mismatch Warnings

**Issue:** `Hash mismatch for file.php`

**Explanation:** File was modified during backup or corrupted during transfer.

**Solution:**
```bash
# Re-create backup
flexpbx-backup -v

# Verify compressed archives
gunzip -t backup.flx.gz

# Re-download if transferred over network
```

### Migration - Sudo Permission Errors

**Issue:** `sudo: a password is required`

**Solution:**
```bash
# Run as root or with sudo
sudo flexpbx-migrate targetuser

# Or grant current user sudo access
visudo
# Add: youruser ALL=(ALL) NOPASSWD: /usr/local/bin/flexpbx-migrate
```

### Service Worker Not Working After Restore

**Issue:** Push notifications broken after restore

**Solution:**
```bash
# Check service worker file exists
ls -la ~/public_html/service-worker.js

# Verify web server can access it
curl https://yourdomain.com/service-worker.js

# Clear browser cache
# Browser DevTools → Application → Service Workers → Unregister
# Then refresh page
```

---

## 📊 Backup Best Practices

### Frequency

- **Daily:** Automated backups via cron
- **Before Changes:** Manual backup before system modifications
- **Weekly:** Extended `.flxx` backups for archival
- **Before Updates:** Always backup before upgrading FlexPBX

### Retention

- Keep last 7 daily backups
- Keep last 4 weekly backups
- Keep monthly backups for 12 months
- Archive major version backups indefinitely

### Storage

- **Local:** `~/backup/` for quick access
- **Remote:** Copy to remote server via rsync/scp
- **Cloud:** S3, Google Drive, Dropbox for offsite backup
- **Encryption:** Use GPG for sensitive deployments

### Verification

Periodically test restores:

```bash
# Test restore to temporary user
sudo useradd -m testuser
flexpbx-restore -m migrate -u testuser -y backup.flx

# Verify functionality
# Then remove test user
sudo userdel -r testuser
```

---

## 🔐 Security Considerations

### Sanitization

All backups are automatically sanitized:

1. ✓ Real emails → placeholder emails
2. ✓ Real names → generic values
3. ✓ Password hashes → placeholder hashes
4. ✓ Session tokens → removed
5. ✓ Push subscriptions → removed
6. ✓ Temporary files → excluded
7. ✓ Cache → excluded
8. ✓ Logs → excluded

### Safe Sharing

`.flx` and `.flxx` backups are safe to:

- Share with developers for debugging
- Distribute for fresh installations
- Post publicly (if needed for support)
- Store in public repositories

**Never contains:**
- Real user passwords
- Personal information
- Active session data
- Device-specific subscriptions

### Production Backups

For production environments with real user data:

1. DO NOT distribute backups containing user directories
2. Use `.flx` format (excludes user data)
3. Encrypt backups if storing offsite
4. Implement access controls on backup directory

---

## 🛠️ Advanced Usage

### Custom Backup Scripts

Create specialized backups:

```bash
#!/bin/bash
# weekly-backup.sh

# Create compressed extended backup
/usr/local/bin/flexpbx-backup -t flxx -c -o ~/backup/weekly/

# Rotate old backups (keep last 4)
cd ~/backup/weekly/
ls -t flexpbx-*.flxx.gz | tail -n +5 | xargs rm -f

# Copy to remote server
rsync -avz flexpbx-*.flxx.gz backup@remote:/backups/flexpbx/
```

### Automated Testing

Test backups automatically:

```bash
#!/bin/bash
# test-backup.sh

# Create test user
sudo useradd -m flexpbx-test

# Restore to test user
/usr/local/bin/flexpbx-restore -m migrate -u flexpbx-test -y latest.flx

# Test API endpoints
curl -s https://domain.com/api/sip-status.php?extension=2006

# Cleanup
sudo userdel -r flexpbx-test

echo "Backup test complete"
```

---

## 📚 Related Documentation

- [Future Enhancements](/home/flexpbxuser/FUTURE_ENHANCEMENTS.md) - Planned backup system improvements
- [System-Wide Temp Passwords](SYSTEM_WIDE_TEMP_PASSWORDS.md) - Password system included in backups
- [Push Notifications & Status](PUSH_NOTIFICATIONS_AND_STATUS.md) - Notification system configuration
- [Permissions & Integration](PERMISSIONS_AND_ASTERISK_INTEGRATION.md) - Sudo configuration backed up

---

## 🆘 Support

### Getting Help

If you encounter issues:

1. Check troubleshooting section above
2. Run backup/restore with `-v` (verbose) flag
3. Check logs in `~/logs/`
4. Verify file permissions
5. Test with minimal `.flx` backup first

### Reporting Issues

Include in bug reports:

- FlexPBX version
- Backup tool command used
- Error messages (from verbose mode)
- Operating system and version
- User account information (without personal data)

---

**Last Updated:** October 14, 2025
**System Version:** FlexPBX v1.0
**Status:** Production-Ready
