# FlexPBX v1.0

**A modern, accessible, standalone PBX system built on Asterisk**

FlexPBX is a complete PBX solution featuring dual-mode Asterisk integration, push notifications, real-time SIP status monitoring, and comprehensive backup/restore capabilities.

## 🌟 Features

### Core System
- **Standalone Architecture** - Runs independently, no external dependencies
- **Dual-Mode Asterisk Integration** - Secure mode (read-only) or Power User mode (write access)
- **Push Notifications** - Browser-based push notifications with service worker
- **Real-Time SIP Status** - Live extension registration monitoring
- **Auto-Generated Passwords** - Time-limited temporary passwords (15-minute expiry)
- **Email Validation** - Prevents placeholder/invalid emails
- **Account Linking** - Link admin accounts to user extensions

### User Portal
- Dashboard with real-time SIP status
- Change password
- Email setup with validation
- Notification settings (push & email)
- Voicemail settings
- Call forwarding

### Admin Portal
- Complete PBX management
- Extension management
- Trunk & DID configuration
- Inbound routing
- Feature codes manager
- Voicemail manager
- Audio upload manager
- Music on Hold (MOH) manager (Icecast/Shoutcast streaming)
- Google Voice integration
- System self-check
- Bug tracker
- Link extensions to admin accounts
- Notification settings

### Backup & Restore System
- **.flx** format - System configuration backups
- **.flxx** format - Extended backups with templates
- Optional user data inclusion (`-d` flag)
- Optional voicemail recordings (`--include-voicemail`)
- Remote backup support (rsync/scp)
- Second drive backup support
- User self-service backups with quota limits
- Migration tools for moving between user accounts

### Notification System
- Push notifications (browser-based)
- Email notifications
- Granular controls (voicemail, missed calls, SIP status, system alerts)
- Per-user preferences
- Admin and user support

### Security
- Password hashing (bcrypt)
- Session-based authentication
- Temporary password system
- Email validation
- Sudo integration for Asterisk commands
- Secure/Power User mode separation

## 📁 Directory Structure

```
/home/flexpbxuser/apps/flexpbx/
├── public_html/
│   ├── api/                      # REST API endpoints
│   │   ├── notification-subscribe.php
│   │   ├── sip-status.php
│   │   ├── generate-temp-password.php
│   │   ├── login.php
│   │   ├── flexpbx-config-helper.php
│   │   └── user-backup.php
│   ├── user-portal/              # User-facing portal
│   ├── admin/                    # Admin dashboard
│   ├── docs/                     # Documentation
│   └── service-worker.js         # Push notification worker
├── system-scripts/
│   ├── flexpbx-backup            # Backup creation tool
│   ├── flexpbx-restore           # Restore tool
│   ├── flexpbx-migrate           # Migration tool
│   └── flexpbx-toggle-permissions # Permission switcher
├── templates/
│   ├── user_template.json
│   ├── admin_template.json
│   └── settings_template.json
└── etc/
    ├── sudoers.d/                # Sudo configuration
    └── asterisk-templates/        # Asterisk config templates
```

## 🚀 Installation

### Prerequisites
- Asterisk 18+ with PJSIP
- PHP 7.4+ with JSON extension
- Apache/Nginx web server
- Linux (CentOS/RHEL 8+, Ubuntu 20.04+)
- sudo access

### Quick Install

```bash
# 1. Clone repository
git clone https://github.com/raywonder/flexpbx.git
cd flexpbx

# 2. Install system scripts
sudo cp system-scripts/flexpbx-* /usr/local/bin/
sudo chmod +x /usr/local/bin/flexpbx-*

# 3. Install sudoers configuration
sudo cp etc/sudoers.d/flexpbx-asterisk /etc/sudoers.d/
sudo chmod 440 /etc/sudoers.d/flexpbx-asterisk

# 4. Copy files to web directory
cp -r public_html/* /home/flexpbxuser/public_html/

# 5. Copy templates
mkdir -p /home/flexpbxuser/templates
cp templates/*.json /home/flexpbxuser/templates/

# 6. Create directory structure
mkdir -p /home/flexpbxuser/{users,admins,temp_passwords,push_subscriptions,cache/sip_status,logs,backup,user_backups}

# 7. Set permissions
chown -R flexpbxuser:nobody /home/flexpbxuser/{users,admins,templates,cache,logs,backup}
chmod 750 /home/flexpbxuser/{users,admins,cache,logs,backup}

# 8. Create flexpbx-config.json
cp flexpbx-config.json.example /home/flexpbxuser/flexpbx-config.json
# Edit with your settings

# 9. Create first admin account
# Visit: https://yourdomain.com/admin/login.php
# Temporary password will be auto-generated
```

### Configuration

Edit `/home/flexpbxuser/flexpbx-config.json`:

```json
{
  "asterisk_mode": "secure",
  "allow_config_writes": false,
  "api_mode": "enabled",
  "notification_defaults": {
    "push_notifications_enabled": false,
    "email_notifications_enabled": true
  },
  "temp_password_expiry": 900,
  "backup": {
    "enabled": true,
    "remote_path": "user@backup-server:/backups/flexpbx/",
    "second_drive_path": "/mnt/backup/"
  }
}
```

## 📖 Documentation

Comprehensive documentation is available in the `/public_html/docs/` directory:

- [System Architecture](public_html/docs/FLEXPBX_STANDALONE_ARCHITECTURE.md)
- [Backup & Restore System](public_html/docs/BACKUP_AND_RESTORE_SYSTEM.md)
- [Push Notifications & Status](public_html/docs/PUSH_NOTIFICATIONS_AND_STATUS.md)
- [System-Wide Temp Passwords](public_html/docs/SYSTEM_WIDE_TEMP_PASSWORDS.md)
- [Email Setup System](public_html/docs/EMAIL_SETUP_SYSTEM.md)
- [Password Reset System](public_html/docs/PASSWORD_RESET_SYSTEM.md)
- [Permissions & Asterisk Integration](public_html/docs/PERMISSIONS_AND_ASTERISK_INTEGRATION.md)
- [Voicemail & Transfers](public_html/docs/VOICEMAIL_AND_TRANSFERS_COMPLETE.md)
- [Feature Codes](public_html/docs/FEATURE_CODES.md)
- [Network Configuration](public_html/docs/NETWORK_PORTS_AND_STUN.md)

## 🔧 Usage

### Backup Commands

```bash
# Create system backup
flexpbx-backup

# Create backup with user data (SENSITIVE!)
flexpbx-backup -d

# Create backup with user data + voicemail
flexpbx-backup -d --include-voicemail

# Create compressed extended backup
flexpbx-backup -t flxx -c

# Backup to custom location
flexpbx-backup -o /mnt/backup/
```

### Restore Commands

```bash
# Restore system files (merge mode)
flexpbx-restore backup.flx

# Fresh installation
flexpbx-restore -m fresh backup.flx

# Migrate to different user
flexpbx-restore -m migrate -u newuser backup.flx
```

### Migration

```bash
# Migrate FlexPBX from current user to newuser
flexpbx-migrate newuser

# Migrate from specific user
flexpbx-migrate -s olduser newuser
```

### Toggle Asterisk Permissions

```bash
# Switch to Power User mode (write access)
sudo flexpbx-toggle-permissions power

# Switch to Secure mode (read-only)
sudo flexpbx-toggle-permissions secure
```

## 🔐 Security

### Password System
- Admin passwords: `Admin####` (4-digit random)
- User passwords: `Ext####` (extension number)
- 15-minute expiry
- Auto-generated on login page
- Security logging

### Backup Security
- System backups (`.flx`) contain NO personal data
- User data backups (`-d` flag) contain:
  - Real emails, names, passwords
  - Push subscriptions
  - Voicemail recordings (if `--include-voicemail`)
- **Store user data backups SECURELY**
- Do NOT share user data backups publicly

### User Backup Quotas
- Default: 5 backups per user
- Default: 100MB total storage per user
- Admin can increase quotas in user JSON files

## 🛠️ Troubleshooting

### Temp Passwords Not Showing
```bash
# Check API endpoint
curl https://yourdomain.com/api/generate-temp-password.php?username=admin&account_type=admin

# Check logs
tail -f /home/flexpbxuser/logs/temp_password.log
```

### SIP Status Shows Offline
```bash
# Check Asterisk manually
sudo -u asterisk /usr/sbin/asterisk -rx "pjsip show endpoint 2006"

# Check sudo permissions
sudo -l

# Clear cache
rm -f /home/flexpbxuser/cache/sip_status/*.json
```

### Push Notifications Not Working
- Check browser supports push (Chrome, Firefox, Edge)
- Grant notification permission in browser
- Verify service worker registered (DevTools → Application → Service Workers)
- Configure VAPID keys (see documentation)

## 📊 System Requirements

- **CPU**: 1+ cores
- **RAM**: 1GB minimum, 2GB recommended
- **Disk**: 10GB minimum, 50GB+ for call recordings
- **Network**: Public IP or proper NAT configuration
- **OS**: CentOS/RHEL 8+, Ubuntu 20.04+

## 🤝 Contributing

This is a private repository for Devine Creations LLC. External contributions are not accepted at this time.

## 📝 License

Proprietary - Devine Creations LLC
All rights reserved.

## 🆘 Support

For support, contact:
- **Email**: support@devinecreations.net
- **Website**: https://devinecreations.net

## 📚 Version History

### v1.0 (October 2025)
- Initial stable release
- Complete standalone architecture
- Push notification system
- Real-time SIP status monitoring
- Backup/restore system
- User self-service backups
- Auto-generated passwords
- Email validation
- Account linking
- Comprehensive documentation

---

**Created by**: Dominique / Devine Creations LLC
**Last Updated**: October 14, 2025
**Repository**: https://github.com/raywonder/flexpbx
