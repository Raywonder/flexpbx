# Feature Codes Manager - Admin Guide
**Created:** October 14, 2025 02:15 AM
**Interface:** Web-based admin panel

---

## 🎯 Overview

The Feature Codes Manager is a web-based admin interface that allows you to:
- ✅ **Enable/Disable** feature codes with a single click
- 🔄 **Reload** Asterisk dialplan without SSH access
- 💾 **Backup** configurations before making changes
- 📊 **View** all feature codes in one place
- ⚡ **Apply** changes instantly

---

## 🌐 Access the Interface

**URL:** `https://flexpbx.devinecreations.net/admin/feature-codes-manager.html`

**Or from Admin Dashboard:**
1. Go to Admin Dashboard
2. Look for "📞 Feature Codes Manager" card
3. Click "Manage Feature Codes"

---

## 📋 Features

### 1. Feature Code Table
Shows all available feature codes with:
- **Status Toggle** - Enable/disable switch
- **Code** - The dial code (*43, *45, etc.)
- **Name** - Feature name (Echo Test, Queue Login, etc.)
- **Description** - What the feature does
- **Category** - Type of feature (Diagnostic, Queue, etc.)
- **Current Status** - Enabled or Disabled

### 2. Categories
Feature codes are organized by category:

**🔍 Diagnostic**
- *43 - Echo Test
- *44 - Time/Clock

**📞 Queue**
- *45 - Queue Login
- *46 - Queue Logout
- *48 - Queue Status

**🎵 Music on Hold**
- *77 - MOH + Queue Stats
- *78 - Music on Hold Preview

**📬 Voicemail**
- *97 - Voicemail Access

### 3. Action Buttons

**🔄 Reload Dialplan**
- Applies all changes to Asterisk
- Must click after making changes
- Shows success/error message
- Takes 1-2 seconds

**💾 Backup**
- Creates timestamped backup of extensions.conf
- Stored as: `/etc/asterisk/extensions.conf.backup.YYYY-MM-DD_HH-mm-ss`
- Always backup before major changes

---

## 🎮 How to Use

### Enable a Feature Code

1. Find the feature code in the table
2. Click the toggle switch to **ON** position (green)
3. You'll see: "Feature code *XX enabled successfully"
4. Click **"Reload Dialplan"** button at top
5. Wait for success message: "Dialplan reloaded successfully!"
6. Feature code is now active

### Disable a Feature Code

1. Find the feature code in the table
2. Click the toggle switch to **OFF** position (gray)
3. You'll see: "Feature code *XX disabled successfully"
4. Click **"Reload Dialplan"** button at top
5. Wait for success message
6. Feature code is now inactive (users can't dial it)

### Backup Configuration

1. Click **"💾 Backup"** button at top right
2. Confirm the backup
3. You'll see: "Configuration backed up to: /etc/asterisk/extensions.conf.backup.2025-10-14_02-15-30"
4. Backup file is stored on server

---

## 🔧 Technical Details

### How It Works

**Behind the scenes:**

1. **Enable/Disable:**
   - PHP reads `/etc/asterisk/extensions.conf`
   - Finds the feature code section
   - Comments out (`; exten =>`) or uncomments the lines
   - Saves changes back to file

2. **Reload:**
   - Executes: `asterisk -rx "dialplan reload"`
   - Asterisk re-reads extensions.conf
   - Changes take effect immediately

3. **Backup:**
   - Copies current extensions.conf
   - Adds timestamp to filename
   - Stores in same directory

### Files Modified

**Configuration:**
- `/etc/asterisk/extensions.conf` - Main dialplan

**Application:**
- `/home/flexpbxuser/public_html/admin/feature-codes-manager.html` - Frontend UI
- `/home/flexpbxuser/public_html/admin/feature-codes-manager.php` - Backend API

### Permissions Required

The system must have permission to:
- Read `/etc/asterisk/extensions.conf`
- Write to `/etc/asterisk/extensions.conf`
- Execute `asterisk -rx` commands
- Create backup files

---

## ⚠️ Important Notes

### Before Making Changes

1. **Always backup first** - Click the Backup button
2. **Test in non-peak hours** - Don't disable codes during busy times
3. **Communicate with users** - Let them know if you're disabling features
4. **Document changes** - Keep notes on why you disabled something

### After Making Changes

1. **Always reload** - Click "Reload Dialplan" button
2. **Test immediately** - Dial the feature code from a phone
3. **Check logs** - If issues, check `/var/log/asterisk/messages`
4. **Verify status** - Make sure toggle reflects actual state

### Safety Features

- **Automatic backups** available before changes
- **Changes don't apply** until you click Reload
- **Config validation** before saving
- **Error messages** if something fails
- **Original file preserved** in backups

---

## 🐛 Troubleshooting

### Issue: Toggle doesn't stay in position

**Solution:**
- Reload the page
- Check server logs
- Verify file permissions on extensions.conf
- Try backing up first

### Issue: Reload button shows error

**Possible causes:**
1. Asterisk not running
   - Check: `asterisk -rx "core show version"`
   - Restart: `systemctl restart asterisk`

2. Permission denied
   - Check web server can run asterisk commands
   - May need to add to sudoers

3. Syntax error in extensions.conf
   - Check Asterisk logs
   - Restore from backup

### Issue: Feature code not working after enable

**Steps:**
1. Verify toggle is green (enabled)
2. Click "Reload Dialplan" again
3. Test from phone
4. Check Asterisk CLI: `asterisk -rx "dialplan show flexpbx-internal"`
5. Look for your feature code in output

### Issue: Can't access the interface

**Check:**
1. URL is correct
2. Web server is running
3. File permissions: `ls -l /home/flexpbxuser/public_html/admin/feature-codes-manager.*`
4. PHP is installed and working

---

## 📊 Status Indicators

### Toggle Colors
- 🟢 **Green** - Feature enabled
- ⚪ **Gray** - Feature disabled

### Status Text
- ✓ **Enabled** - Feature is active
- ✗ **Disabled** - Feature is inactive

### Alert Messages
- 🟢 **Green box** - Success
- 🔴 **Red box** - Error
- 🔵 **Blue box** - Information

---

## 🔄 Workflow Examples

### Example 1: Disable Echo Test During Maintenance

```
1. Open Feature Codes Manager
2. Click "💾 Backup" button
3. Find "*43 - Echo Test" in table
4. Click toggle to OFF (gray)
5. Click "🔄 Reload Dialplan"
6. Wait for success message
7. Test: Dial *43 → Should hear "invalid extension"
8. When done, toggle back ON and reload
```

### Example 2: Enable New Feature Code

```
1. Open Feature Codes Manager
2. Click "💾 Backup" button
3. Find new feature code in table
4. Click toggle to ON (green)
5. Click "🔄 Reload Dialplan"
6. Wait for success message
7. Test: Dial the code → Should work
```

### Example 3: Disable All Queue Features

```
1. Backup configuration
2. Disable *45 (Queue Login)
3. Disable *46 (Queue Logout)
4. Disable *48 (Queue Status)
5. Click "Reload Dialplan" once
6. All queue features now disabled
7. Users can't access queue functions
```

---

## 🎓 Best Practices

### Daily Operations

1. **Check status** before making changes
2. **Backup** before bulk changes
3. **Reload** after all changes (not after each one)
4. **Test** immediately after changes
5. **Document** what you changed and why

### Security

1. **Restrict access** to authorized admins only
2. **Keep backups** for at least 30 days
3. **Log changes** (who changed what when)
4. **Review logs** regularly for issues
5. **Test in staging** if available

### Maintenance

1. **Review feature codes** monthly
2. **Clean up** unused codes
3. **Update descriptions** as needed
4. **Verify backups** are being created
5. **Test all codes** quarterly

---

## 📞 Support

### Need Help?

**Check logs:**
```bash
tail -f /var/log/asterisk/messages
```

**View current dialplan:**
```bash
asterisk -rx "dialplan show flexpbx-internal"
```

**Restore from backup:**
```bash
cp /etc/asterisk/extensions.conf.backup.YYYY-MM-DD_HH-mm-ss /etc/asterisk/extensions.conf
asterisk -rx "dialplan reload"
```

---

## 📝 Quick Reference

| Action | Steps | Time |
|--------|-------|------|
| Enable code | Toggle ON → Reload | 5 sec |
| Disable code | Toggle OFF → Reload | 5 sec |
| Backup config | Click Backup button | 2 sec |
| Reload dialplan | Click Reload button | 2 sec |
| Test feature | Dial from phone | 10 sec |

---

## 🚀 Advanced Usage

### Bulk Operations

To enable/disable multiple codes:
1. Click each toggle (don't reload yet)
2. After all toggles changed
3. Click "Reload Dialplan" once
4. All changes apply together

### Scheduling Changes

For scheduled enable/disable:
1. Use cron job to call PHP script
2. Or manually change at scheduled time
3. Best for maintenance windows

### Monitoring

Check if feature is actually disabled:
```bash
asterisk -rx "dialplan show flexpbx-internal" | grep -A 2 "*43"
```

If commented, you'll see lines starting with `;`

---

## ✅ Checklist

**Before Using:**
- [ ] Access URL works
- [ ] Can see feature code table
- [ ] Backup button works
- [ ] Reload button works

**When Making Changes:**
- [ ] Backup created
- [ ] Changes tested
- [ ] Dialplan reloaded
- [ ] Users notified if needed

**After Changes:**
- [ ] Feature codes tested
- [ ] Logs checked for errors
- [ ] Documentation updated
- [ ] Backup retained

---

**Interface Version:** 1.0
**Last Updated:** October 14, 2025
**Location:** `/home/flexpbxuser/public_html/admin/feature-codes-manager.html`
**Documentation:** `/home/flexpbxuser/public_html/FEATURE_CODES.md`
