# FlexPBX Quick Reference Card

## 📞 Feature Codes (Dial from any extension)

| Code | Feature | Description |
|------|---------|-------------|
| **\*97** | Your Voicemail | Access your own voicemail mailbox |
| **\*98** | Any Voicemail | Access any mailbox (enter mailbox#) |
| **\*43** | Echo Test | Test your audio connection |
| **\*411** | Directory | Dial-by-name company directory |
| **700** | Park Call | Park current call |
| **701** | Retrieve 701 | Pickup parked call from slot 701 |
| **702** | Retrieve 702 | Pickup parked call from slot 702 |
| **8000** | Main Conference | Join main conference room |
| **8001** | Team Conference | Join team conference room |

---

## 💬 Admin Pages

| Page | URL | Purpose |
|------|-----|---------|
| Dashboard | `/admin/dashboard.php` | Main control panel |
| Extensions | `/admin/extensions.php` | Manage phone extensions |
| IVR Builder | `/admin/ivr-builder.php` | Create/edit IVR menus |
| Messaging Center | `/admin/messaging-center.php` | SMS + XMPP messaging |
| XMPP Config | `/admin/xmpp-configuration.php` | XMPP server settings |

---

## 📋 IVR Templates

### Quick Deploy Templates

1. **Simple Business** - 3 options (Sales, Support, Operator)
2. **Voicemail Access** - Voicemail menu
3. **Conference Access** - Conference room access
4. **Professional Business** - 5 options (full dept menu)
5. **After Hours** - After-hours routing
6. **Medical Office** - Healthcare practice
7. **IT Help Desk** - Technical support
8. **E-commerce Store** - Online store support

**To Use**: Go to IVR Builder → Templates → Select template → Apply

---

## 🔧 Quick Commands

### Asterisk CLI
```bash
# Check dialplan
asterisk -rx "dialplan show flexpbx-internal"

# Reload dialplan
asterisk -rx "dialplan reload"

# Check extensions
asterisk -rx "pjsip show endpoints"

# Check voicemail
asterisk -rx "voicemail show users"

# Check conferences
asterisk -rx "confbridge list"
```

### Feature Code Configuration
```bash
# Auto-configure all feature codes
php /home/flexpbxuser/apps/flexpbx/scripts/auto-configure-feature-codes.php --apply

# Check configuration
php /home/flexpbxuser/apps/flexpbx/scripts/auto-configure-feature-codes.php
```

### XMPP Administration
```bash
# Check Prosody status
systemctl status prosody

# Create XMPP account
prosodyctl register username flexpbx.local password

# Auto-provision all extensions
php /home/flexpbxuser/apps/flexpbx/scripts/auto-provision-xmpp.php

# View logs
tail -f /var/log/prosody/prosody.log
```

---

## 🎯 Common Tasks

### Add New Extension with Feature Codes

1. Go to `/admin/extensions.php`
2. Click "Add Extension"
3. Fill in extension details
4. Save
5. Feature codes automatically available!

### Create IVR Menu from Template

1. Go to `/admin/ivr-builder.php`
2. Click "Templates" tab
3. Select template (e.g., "Simple Business")
4. Click "Use Template"
5. Enter IVR number (e.g., 6000)
6. Customize if needed
7. Save and apply to Asterisk

### Send SMS Message

1. Go to `/admin/messaging-center.php`
2. Click "Compose" tab
3. Select SMS provider
4. Enter recipient number
5. Type message
6. Click "Send"

### Enable XMPP Chat

1. Go to `/admin/xmpp-configuration.php`
2. Click "Auto-Provision All Extensions"
3. Go to `/admin/messaging-center.php`
4. Click "XMPP Chat" tab
5. Click "Connect"
6. Start chatting!

---

## 🚨 Troubleshooting

### Feature Code Doesn't Work

**Problem**: Dialing *97 shows "Address Not Found"

**Solution**:
```bash
# Check dialplan
asterisk -rx "dialplan show flexpbx-internal" | grep "*97"

# If not found, reload
asterisk -rx "dialplan reload"

# If still not working, re-apply config
php /home/flexpbxuser/apps/flexpbx/scripts/auto-configure-feature-codes.php --apply
```

### Extension Can't Call Other Extensions

**Problem**: Calls between extensions fail

**Solution**:
```bash
# Verify extension exists
asterisk -rx "pjsip show endpoint 2000"

# Check if in correct context
grep "context.*flexpbx-internal" /etc/asterisk/pjsip.conf

# Reload PJSIP
asterisk -rx "pjsip reload"
```

### XMPP Won't Connect

**Problem**: XMPP server offline

**Solution**:
```bash
# Check Prosody
systemctl status prosody

# If not running
systemctl start prosody

# Check logs
tail -f /var/log/prosody/prosody.log

# Test connection
telnet localhost 5222
```

---

## 📊 System Health Check

Run this quick health check:

```bash
# 1. Check Asterisk
systemctl status asterisk

# 2. Check feature codes (should show 9)
asterisk -rx "dialplan show flexpbx-internal" | grep "=>" | wc -l

# 3. Check extensions
asterisk -rx "pjsip show endpoints" | grep "Aor:" | wc -l

# 4. Check Prosody (optional, for XMPP)
systemctl status prosody

# 5. Check database
mysql -u root -p flexpbx -e "SELECT COUNT(*) FROM extensions"
```

---

## 🎓 Training Videos (Coming Soon)

- Using Feature Codes
- Creating IVR Menus
- SMS Integration Setup
- XMPP Chat Configuration
- Admin Panel Overview

---

## 📞 Support

- **Documentation**: `/home/flexpbxuser/apps/flexpbx/`
- **GitHub**: https://github.com/devinecreations/flexpbx
- **Email**: info@devinecreations.net

---

**Version**: 1.3
**Updated**: November 9, 2025
**Print This**: Save for quick reference!
