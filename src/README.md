# FlexPBX Source Code

This directory contains the complete source code for FlexPBX v1.4.

## Directory Structure

```
src/
├── admin/          Admin panel PHP files (81 files)
├── api/            API endpoints (123 files)
├── user-portal/    User portal pages (38 files)
├── includes/       Shared PHP classes and functions (17 files)
├── scripts/        Utility and maintenance scripts (11 files)
└── cron/           Scheduled task scripts (5 files)
```

## Installation from Source

### Option 1: Use Installer (Recommended)

Download and use our pre-built installers:
- Master Server: https://flexpbx.devinecreations.net/downloads/FlexPBX-Master-Server-v1.4.tar.gz
- Client: https://flexpbx.devinecreations.net/downloads/FlexPBX-Client-v1.4.tar.gz

### Option 2: Manual Installation from Source

1. **Copy source files to your web directory**:
   ```bash
   # For cPanel/shared hosting
   cp -r src/admin/* ~/public_html/admin/
   cp -r src/api/* ~/public_html/api/
   cp -r src/user-portal/* ~/public_html/user-portal/
   cp -r src/includes/* ~/public_html/includes/
   cp -r src/scripts/* ~/public_html/scripts/
   cp -r src/cron/* ~/public_html/cron/

   # For VPS/dedicated (Apache)
   cp -r src/admin/* /var/www/html/admin/
   cp -r src/api/* /var/www/html/api/
   cp -r src/user-portal/* /var/www/html/user-portal/
   cp -r src/includes/* /var/www/html/includes/
   cp -r src/scripts/* /var/www/html/scripts/
   cp -r src/cron/* /var/www/html/cron/
   ```

2. **Set permissions**:
   ```bash
   chmod 755 ~/public_html/scripts/*
   chmod 755 ~/public_html/cron/*
   chmod 644 ~/public_html/admin/*.php
   chmod 644 ~/public_html/api/*.php
   ```

3. **Import database schema**:
   ```bash
   mysql -u username -p database_name < sql/complete-schema.sql
   ```

4. **Configure Asterisk**:
   ```bash
   # Copy MOH configuration
   sudo cp config/musiconhold.conf /etc/asterisk/

   # Copy dialplan configuration
   sudo cp config/asterisk-dialplan-defaults.conf /etc/asterisk/

   # Reload Asterisk
   sudo asterisk -rx "core reload"
   sudo asterisk -rx "moh reload"
   ```

5. **Configure web server permissions**:
   ```bash
   # Set ownership (adjust user/group as needed)
   chown -R www-data:www-data /var/www/html/

   # Or for cPanel
   chown -R username:username ~/public_html/
   ```

## File Counts

- **Admin Files**: 81 PHP files
- **API Files**: 123 PHP files
- **User Portal**: 38 PHP files
- **Includes**: 17 PHP files
- **Scripts**: 11 files
- **Cron Jobs**: 5 PHP files
- **Total Source Files**: 275 files

## Key Files

### Admin Panel (`admin/`)
- `dashboard.php` - Main admin dashboard
- `user-migration.php` - User migration system (NEW in v1.4)
- `documentation-center.php` - Documentation viewer (NEW in v1.4)
- `department-management.php` - Department management
- `send-invite.php` - User invitation system
- `login.php` - Admin authentication

### API Endpoints (`api/`)
- `user-management.php` - User migration API (NEW in v1.4)
- `extensions.php` - Extension management
- `departments.php` - Department management
- `notifications-manager.php` - Notification system
- `mattermost-integration.php` - Mattermost chat integration
- `config.php` - Database configuration

### User Portal (`user-portal/`)
- `index.php` - User dashboard
- `signup.php` - User registration
- `notifications.php` - User notifications
- `chat.php` - Mattermost chat integration

### Shared Includes (`includes/`)
- `admin_header.php` - Admin panel header
- `FlexBot.php` - AI chatbot integration
- `MastodonAuth.php` - Mastodon OAuth
- `notifications-dropdown.php` - Notification widget

## Dependencies

### PHP Requirements
- PHP 8.0+ (8.1 or 8.2 recommended)
- Extensions: mysqli, pdo_mysql, json, mbstring, curl

### Database
- MariaDB 10.5+ or MySQL 8.0+

### Asterisk
- Asterisk 18.12.1+
- PJSIP enabled

### Web Server
- Apache 2.4+ with mod_rewrite
- Nginx 1.18+ (alternative)

## Configuration

After installation, configure:

1. **Database Connection** (`api/config.php`):
   ```php
   $db_host = 'localhost';
   $db_name = 'flexpbx';
   $db_user = 'flexpbx_user';
   $db_pass = 'your_password';
   ```

2. **Asterisk Integration**:
   - Configure PJSIP endpoints
   - Set up queue management
   - Configure voicemail

3. **Admin Account**:
   - Create admin user via signup
   - Set role to 'admin' in database

## Features by Directory

### Admin Panel Features
- User management (invitations, migrations)
- Department management
- Extension management
- IVR builder
- Call queue management
- MOH management
- Mattermost chat integration
- Notification system
- Documentation center
- Help system
- Security settings

### API Features
- RESTful API endpoints
- User authentication
- Extension CRUD operations
- Department management
- Queue management
- Notification delivery
- Mattermost webhooks
- SMS/voice integration (TextNow, Twilio, Google Voice)

### User Portal Features
- User dashboard
- Extension settings
- Voicemail access
- Call history
- Mattermost chat
- Notifications
- Profile management

## Security Notes

- Change default database credentials
- Use strong admin passwords
- Enable HTTPS/SSL
- Configure firewall rules
- Restrict API access
- Enable rate limiting
- Regular security updates

## Support

- **Documentation**: See `/docs` directory (14 comprehensive guides)
- **Email**: support@devine-creations.com
- **Phone**: (302) 313-9555
- **Website**: https://flexpbx.devinecreations.net

## Version

**Current Version**: 1.4
**Release Date**: November 9, 2025
**Total Source Files**: 275
**Total Lines of Code**: 50,000+ lines

## License

See LICENSE file for details.
