# FlexPBX Server Installers
**Version**: 1.0
**Updated**: October 19, 2025
**PHP Requirement**: 8.0+ (Recommended: 8.1 or 8.2)

---

## Available Downloads

### Master Server Installer
**File**: `FlexPBX-Master-Server-v1.0.tar.gz` (845 KB)
**MD5**: Available in `FlexPBX-Master-Server-v1.0.tar.gz.md5`

**Use This For**:
- Main FlexPBX server installation
- Full feature set including admin panel
- Module repository server
- Multi-client management
- HubNode integration support

### Client Installer
**File**: `FlexPBX-Client-v1.0.tar.gz` (14 KB)
**MD5**: Available in `FlexPBX-Client-v1.0.tar.gz.md5`

**Use This For**:
- Remote FlexPBX installations
- Connects to master server for updates
- Lightweight installation
- Client-only features

---

## System Requirements

### Minimum Requirements
- **Operating System**: Linux (systemd-based)
  - AlmaLinux 8+, 9+
  - Ubuntu 18.04, 20.04, 22.04, 24.04
  - Debian 10, 11, 12
  - RHEL 8+, 9+
  - CentOS Stream
  - Fedora 36+

- **PHP**: 8.0.0 or higher
  - **Recommended**: PHP 8.1 or 8.2
  - **Unsupported**: PHP 7.4 and older

- **Database**: MariaDB 10.5+ or MySQL 5.7+

- **Web Server**: Apache 2.4+ with mod_rewrite

- **Asterisk**: 16.0+ with PJSIP support

### Recommended Specifications
- **RAM**: 4GB minimum, 8GB recommended
- **Disk**: 40GB minimum
- **CPU**: 2 cores minimum, 4 cores recommended
- **Network**: Static IP or dynamic DNS

---

## Quick Installation Guide

### 1. Download Installer
```bash
cd /tmp
wget https://flexpbx.devinecreations.net/downloads/flexpbx/server/FlexPBX-Master-Server-v1.0.tar.gz
wget https://flexpbx.devinecreations.net/downloads/flexpbx/server/FlexPBX-Master-Server-v1.0.tar.gz.md5
```

### 2. Verify Checksum
```bash
md5sum -c FlexPBX-Master-Server-v1.0.tar.gz.md5
```

**Expected Output**: `FlexPBX-Master-Server-v1.0.tar.gz: OK`

### 3. Extract Installer
```bash
tar -xzf FlexPBX-Master-Server-v1.0.tar.gz
cd FlexPBX-Master-Server-v1.0/
```

### 4. Install Dependencies

**AlmaLinux/RHEL/CentOS**:
```bash
# Enable EPEL repository
sudo dnf install -y epel-release

# Enable Remi repository for PHP 8.1
sudo dnf install -y https://rpms.remirepo.net/enterprise/remi-release-8.rpm
sudo dnf module reset php -y
sudo dnf module enable php:remi-8.1 -y

# Install packages
sudo dnf install -y httpd mariadb-server php php-cli php-fpm \
    php-mysqlnd php-json php-mbstring php-curl php-openssl \
    php-pdo php-xml asterisk

# Start services
sudo systemctl enable --now httpd mariadb asterisk
```

**Ubuntu/Debian**:
```bash
# Add PHP 8.1 repository
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

# Install packages
sudo apt install -y apache2 mariadb-server php8.1 php8.1-cli \
    php8.1-fpm php8.1-mysql php8.1-json php8.1-mbstring \
    php8.1-curl php8.1-xml asterisk

# Start services
sudo systemctl enable --now apache2 mariadb asterisk
```

### 5. Copy Files to Web Root
```bash
# Copy to web server directory
sudo cp -r * /var/www/html/flexpbx/

# Set permissions
sudo chown -R apache:apache /var/www/html/flexpbx/  # AlmaLinux/RHEL
# OR
sudo chown -R www-data:www-data /var/www/html/flexpbx/  # Ubuntu/Debian

# Set proper permissions
sudo chmod 755 /var/www/html/flexpbx/
sudo chmod 644 /var/www/html/flexpbx/api/*.php
```

### 6. Run Web Installer
Navigate to: `https://your-server.com/flexpbx/api/install.php`

Follow the installation wizard:
1. Welcome screen
2. System requirements check
3. Database configuration
4. Installation process
5. Admin account creation
6. Complete

### 7. Delete Installer (Security)
```bash
sudo rm -f /var/www/html/flexpbx/api/install.php
```

---

## Post-Installation Configuration

### Secure MariaDB
```bash
sudo mysql_secure_installation
```

### Configure Firewall
```bash
# AlmaLinux/RHEL/CentOS
sudo firewall-cmd --permanent --add-service=http
sudo firewall-cmd --permanent --add-service=https
sudo firewall-cmd --permanent --add-port=5060/tcp
sudo firewall-cmd --permanent --add-port=5060/udp
sudo firewall-cmd --permanent --add-port=5061/tcp
sudo firewall-cmd --permanent --add-port=10000-20000/udp
sudo firewall-cmd --reload

# Ubuntu/Debian
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw allow 5060/tcp
sudo ufw allow 5060/udp
sudo ufw allow 5061/tcp
sudo ufw allow 10000:20000/udp
sudo ufw enable
```

### Enable SSL/TLS (Recommended)
```bash
# Install certbot
sudo dnf install certbot python3-certbot-apache  # AlmaLinux/RHEL
sudo apt install certbot python3-certbot-apache  # Ubuntu/Debian

# Get certificate
sudo certbot --apache -d your-domain.com
```

---

## Features Included

### Core Features
- ✅ User management with auto-provisioning
- ✅ Extension management (PJSIP)
- ✅ Voicemail with email notifications
- ✅ Call routing and dialplan management
- ✅ Trunk management (SIP trunks)
- ✅ Inbound route configuration
- ✅ Department management
- ✅ Role-based access control

### Advanced Features
- ✅ Conference bridge (ConfBridge)
- ✅ Music on hold (MOH) with Jellyfin integration
- ✅ Queue management for call centers
- ✅ IVR menu builder
- ✅ Call parking
- ✅ Presence/status indicators
- ✅ SMS messaging (Google Voice, Twilio)

### Payment & Licensing
- ✅ Payment gateway integration (PayPal, Stripe, Coinbase, BTCPay)
- ✅ License management system
- ✅ 8 license types (trial, starter, professional, enterprise, lifetime)

### Notification System
- ✅ Email notifications
- ✅ Push notifications (Web Push API)
- ✅ Smart default settings (non-spammy)
- ✅ User-controllable preferences

### Security
- ✅ Fail2ban integration
- ✅ Session-based authentication
- ✅ API key management
- ✅ Secure config storage (outside web root)
- ✅ PHP 8.0+ enforcement

### Administration
- ✅ Beautiful admin dashboard
- ✅ System health monitoring
- ✅ Backup & restore (.flx/.flxx formats)
- ✅ Update management
- ✅ Module system with remote downloads
- ✅ Configuration editor
- ✅ Bug tracker

### Accessibility
- ✅ WCAG 2.1 AA/AAA compliant
- ✅ Accessibility categories system
- ✅ User request management
- ✅ Screen reader friendly

---

## Module Installation After Setup

FlexPBX uses a modular system. After base installation, you can install additional modules via API:

```bash
# Example: Install SMS messaging module
curl -X POST https://your-server.com/api/modules.php?path=install \
  -H "X-API-Key: your-api-key" \
  -d '{"module_key": "sms-messaging"}'
```

Available modules can be listed via:
```bash
curl https://your-server.com/api/modules.php?path=available
```

---

## Troubleshooting

### PHP Version Error
**Error**: "PHP 8.0 or higher required"

**Solution**:
```bash
# Check PHP version
php -v

# If < 8.0, upgrade following instructions in Section 4 above
```

### Database Connection Failed
**Error**: "Could not connect to database"

**Solution**:
```bash
# Verify MariaDB is running
sudo systemctl status mariadb

# Test connection
mysql -u root -p
```

### Asterisk Not Starting
**Error**: "Asterisk service failed to start"

**Solution**:
```bash
# Check Asterisk status
sudo systemctl status asterisk

# View logs
sudo tail -f /var/log/asterisk/messages

# Restart Asterisk
sudo systemctl restart asterisk
```

### Permission Denied Errors
**Error**: "Permission denied" when accessing files

**Solution**:
```bash
# Fix ownership
sudo chown -R apache:apache /var/www/html/flexpbx/  # AlmaLinux
sudo chown -R www-data:www-data /var/www/html/flexpbx/  # Ubuntu

# Fix permissions
sudo find /var/www/html/flexpbx/ -type d -exec chmod 755 {} \;
sudo find /var/www/html/flexpbx/ -type f -exec chmod 644 {} \;
```

---

## Support

### Documentation
- Installation Guide: `/documentation/`
- API Reference: `/documentation/API_AUDIT_REPORT.md`
- Bug Tracker: `/documentation/FLEXPBX_BUG_TRACKER_AND_TASKS.md`

### Contact
- **Email**: support@devine-creations.com
- **Phone**: (302) 313-9555
- **Website**: https://flexpbx.devinecreations.net

### Reporting Issues
When reporting installation issues, include:
- Operating system and version
- PHP version (`php -v`)
- Error messages from installer
- Browser console errors (if applicable)

---

## Version History

### v1.0 (October 19, 2025)
- Initial public release
- PHP 8.0+ requirement enforced
- Payment module with 6 gateways
- Notification system with smart defaults
- Conference music control
- Main IVR menu
- Presence/status system
- Comprehensive documentation

---

## License

Copyright © 2025 Devine Creations. All rights reserved.

---

*FlexPBX - "A system you can help build to be the best it can be. Accessible by default."*
