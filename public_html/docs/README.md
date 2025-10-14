# FlexPBX Documentation Center

**Version:** 1.0
**Last Updated:** October 14, 2025
**Location:** `/docs/`

---

## 📚 About This Documentation

This is the complete documentation center for FlexPBX - a modern, accessible alternative to FreePBX. All documentation is available in two formats:

- **HTML** - For viewing in web browsers
- **Markdown (.md)** - For downloading and offline viewing

---

## 🚀 Quick Start

**Access Documentation Portal:**
[https://flexpbx.devinecreations.net/docs/](https://flexpbx.devinecreations.net/docs/)

**Most Important Documents:**
1. [Final Deployment README](FINAL-DEPLOYMENT-README.html) - Start here!
2. [Feature Codes Reference](FEATURE_CODES.html) - Quick reference
3. [Voicemail & Transfers Complete](VOICEMAIL_AND_TRANSFERS_COMPLETE.html) - New features
4. [Audio Troubleshooting](AUDIO_TROUBLESHOOTING.html) - Common issues

---

## 📂 Documentation Categories

### 🚀 Getting Started
- **Final Deployment README** - Complete system overview
- **System Status** - Current configuration
- **Testing Guide** - Step-by-step testing procedures

### ⚙️ Features & Configuration
- **Feature Codes Reference** - All *codes (*43, *45, *97, etc.)
- **Feature Codes Manager Guide** - Web-based management
- **Voicemail & Transfers Complete** - Complete setup guide
- **Feature Summary October 2025** - Monthly summary
- **Dialplan Guide** - Understanding the dialplan

### 🔧 Troubleshooting & Support
- **Audio Troubleshooting** - Fix audio issues
- **Audio Fix Summary** - Applied fixes
- **PBX Server Status** - Server monitoring

### 🔒 Network & Security
- **Network Ports & STUN** - Network configuration
- **Security & Routing Summary** - Security setup

### 🔌 Integration & Setup
- **Callcentric Setup Complete** - SIP trunk integration
- **Extension Testing Guide** - Extension testing
- **Walter Harper Extension Setup** - Example setup

### 📦 Development & Archives
- **FlexPBX Deployment Summary** - Architecture overview
- **FlexPBX Development Archive** - Historical notes
- **Claude Handoff** - AI assistant context

---

## 🔍 How to Use This Documentation

### For Administrators

1. **Start with** [Final Deployment README](FINAL-DEPLOYMENT-README.html)
2. **Reference** [Feature Codes Manager Guide](FEATURE_CODES_MANAGER_GUIDE.html) for daily tasks
3. **Troubleshoot with** [Audio Troubleshooting](AUDIO_TROUBLESHOOTING.html)
4. **Configure with** [Voicemail Manager](../admin/voicemail-manager.html)

### For Support Staff

1. **Learn codes from** [Feature Codes Reference](FEATURE_CODES.html)
2. **Help users with** [Voicemail & Transfers Complete](VOICEMAIL_AND_TRANSFERS_COMPLETE.html)
3. **Fix issues with** [Audio Troubleshooting](AUDIO_TROUBLESHOOTING.html)
4. **Check network** [Network Ports & STUN](NETWORK_PORTS_AND_STUN.html)

### For End Users

1. **Access** [User Portal](../user-portal/)
2. **Manage voicemail** [Voicemail Settings](../user-portal/voicemail-settings.php)
3. **Learn features** [Feature Codes Reference](FEATURE_CODES.html)

---

## 🎯 FlexPBX Features Documented

### ✅ Voice & Communication
- **Extensions** - SIP endpoints (2000-2003)
- **Voicemail** - Complete voicemail system (*97)
- **Call Transfers** - Blind (#) and Attended (*2)
- **Queue Management** - Agent login/logout (*45/*46)
- **Feature Codes** - 8+ active codes

### ✅ Web Interfaces
- **Admin Dashboard** - Complete system management
- **User Portal** - Self-service for users
- **Voicemail Manager** - System-wide voicemail control
- **Feature Codes Manager** - Enable/disable features
- **MOH Manager** - Music on hold configuration
- **Media Manager** - File management
- **Audio Upload** - IVR and greeting uploads

### ✅ System Features
- **2FA Support** - Two-factor authentication
- **Desktop Apps** - Windows, Mac, Linux clients
- **Mobile Support** - iOS and Android apps
- **Streaming MOH** - Icecast/Shoutcast integration
- **Email Integration** - Voicemail to email
- **WHMCS Integration** - Billing system module

### ✅ Network & Security
- **SIP over UDP/TCP** - Multiple transports
- **Tailscale VPN** - Secure remote access
- **STUN Support** - NAT traversal
- **Firewall Rules** - CSF configuration
- **TLS/SRTP** - Encrypted communication

---

## 📊 Documentation Statistics

- **Total Documents:** 20
- **HTML Files:** 20
- **Markdown Files:** 20
- **Categories:** 6
- **Quick Access Links:** 4
- **Search Function:** Yes

---

## 🔄 Updating Documentation

### Converting Markdown to HTML

A PHP script is available to convert .md files to HTML:

```bash
php /home/flexpbxuser/public_html/docs/markdown-to-html.php
```

This will:
1. Find all .md files in `/docs/`
2. Convert them to HTML with proper styling
3. Create downloadable links
4. Update the documentation portal

### Adding New Documentation

1. Create your `.md` file in `/home/flexpbxuser/public_html/docs/`
2. Run the conversion script (above)
3. Add entry to `index.html` if needed
4. Set permissions: `chmod 644 filename.md filename.html`

---

## 🌐 Access URLs

**Documentation Portal:**
https://flexpbx.devinecreations.net/docs/

**Admin Dashboard:**
https://flexpbx.devinecreations.net/admin/dashboard.html

**User Portal:**
https://flexpbx.devinecreations.net/user-portal/

**Voicemail Manager:**
https://flexpbx.devinecreations.net/admin/voicemail-manager.html

**Feature Codes Manager:**
https://flexpbx.devinecreations.net/admin/feature-codes-manager.html

---

## 🆘 Getting Help

### Documentation Portal
Browse all documentation at: [https://flexpbx.devinecreations.net/docs/](https://flexpbx.devinecreations.net/docs/)

### Search Function
Use the search box at the top of the documentation portal to find specific topics.

### Quick Access
From Admin Dashboard → Documentation section → Links to key guides

### Download Offline
Each document has a "Download MD" button for offline access

---

## 📝 Documentation Format

### HTML Features
- ✅ Responsive design for all devices
- ✅ Syntax highlighting for code blocks
- ✅ Table formatting
- ✅ Navigation breadcrumbs
- ✅ Download links for Markdown
- ✅ Print-friendly styling

### Markdown Features
- ✅ Standard GitHub-flavored Markdown
- ✅ Code blocks with language highlighting
- ✅ Tables and lists
- ✅ Links and images
- ✅ Checkboxes and task lists

---

## 🔒 Permissions

All documentation files have proper permissions:
- **Directory:** 755 (readable by all)
- **HTML files:** 644 (readable by all)
- **Markdown files:** 644 (readable by all)
- **Owner:** flexpbxuser:nobody

---

## 📅 Maintenance

### Regular Updates
- Update documentation when features change
- Add new guides as features are added
- Archive outdated documentation
- Keep README current

### Backup
Documentation is automatically backed up with:
- cPanel backups
- Git repository (if configured)
- Manual backups available in `/backup/`

---

## ✅ Checklist for Admins

**Before Making Changes:**
- [ ] Read relevant documentation
- [ ] Backup current configuration
- [ ] Test in non-production if possible

**After Making Changes:**
- [ ] Update documentation if needed
- [ ] Test the changes
- [ ] Notify users of changes
- [ ] Document in changelog

**For Support:**
- [ ] Check documentation first
- [ ] Use troubleshooting guides
- [ ] Document solutions for future reference
- [ ] Update FAQs as needed

---

## 🎓 Training Resources

### For New Admins
1. Start with [Final Deployment README](FINAL-DEPLOYMENT-README.html)
2. Review [Feature Summary](FEATURE_SUMMARY_OCTOBER_2025.html)
3. Practice with [Testing Guide](TESTING-GUIDE.html)
4. Learn [Feature Codes Manager](FEATURE_CODES_MANAGER_GUIDE.html)

### For Support Staff
1. Memorize [Feature Codes](FEATURE_CODES.html)
2. Study [Voicemail & Transfers](VOICEMAIL_AND_TRANSFERS_COMPLETE.html)
3. Master [Audio Troubleshooting](AUDIO_TROUBLESHOOTING.html)
4. Understand [Network Configuration](NETWORK_PORTS_AND_STUN.html)

### For Users
1. Access [User Portal](../user-portal/)
2. Configure [Voicemail Settings](../user-portal/voicemail-settings.php)
3. Learn [Feature Codes](FEATURE_CODES.html)

---

## 🚀 What's New (October 2025)

### Latest Additions
- ✅ Complete voicemail system with all features enabled
- ✅ Call transfer support (blind and attended)
- ✅ User portal voicemail settings page
- ✅ Admin voicemail manager
- ✅ Documentation center with 20+ guides
- ✅ HTML versions of all documentation
- ✅ Search functionality
- ✅ Mobile-responsive design

### Recent Updates
- ✅ Feature codes reorganized (*43, *44, *45, etc.)
- ✅ Audio troubleshooting guide created
- ✅ Network configuration documented
- ✅ Security settings documented
- ✅ Integration guides added

---

## 📞 Support Contacts

**System Administrator:**
Email: admin@flexpbx.devinecreations.net

**Technical Support:**
Portal: https://flexpbx.devinecreations.net/admin/

**Documentation Feedback:**
Submit issues or suggestions through admin portal

---

**FlexPBX Documentation Center**
*Making FreePBX features accessible to everyone*

Version 1.0 | October 2025
