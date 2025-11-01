#!/bin/bash

# FlexPBX Shared Hosting Installation Script
# Install FlexPBX on shared hosting environments with cPanel/DirectAdmin
# Version: 1.0.0

set -euo pipefail

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
FLEXPBX_VERSION="1.0.0"
USER_HOME="$HOME"
APPS_DIR="$USER_HOME/apps"
WEB_DIR="$USER_HOME/public_html/flexpbx"
CONFIG_DIR="$APPS_DIR/flexpbx/config"
LOG_DIR="$APPS_DIR/flexpbx/logs"
BACKUP_DIR="$APPS_DIR/flexpbx/backup"

# Shared hosting specific
SUBDIRECTORY="flexpbx"
CUSTOM_PORT=3000
NODE_APP_NAME="flexpbx"

# Function to print colored output
print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Function to detect hosting environment
detect_hosting_environment() {
    print_status "Detecting hosting environment..."

    if [ -d "$USER_HOME/public_html" ]; then
        print_success "Detected shared hosting with public_html directory"
        HOSTING_TYPE="shared"
    elif [ -d "$USER_HOME/www" ]; then
        print_success "Detected hosting with www directory"
        WEB_DIR="$USER_HOME/www/$SUBDIRECTORY"
        HOSTING_TYPE="shared"
    elif [ -d "$USER_HOME/domains" ]; then
        print_success "Detected DirectAdmin-style hosting"
        WEB_DIR="$USER_HOME/domains/$(ls $USER_HOME/domains | head -n1)/public_html/$SUBDIRECTORY"
        HOSTING_TYPE="directadmin"
    else
        print_warning "Could not detect hosting type, using default structure"
        HOSTING_TYPE="generic"
    fi

    # Check for cPanel
    if [ -f "$USER_HOME/.cpanel" ] || [ -d "$USER_HOME/cpanel3-logs" ]; then
        print_success "Detected cPanel hosting environment"
        HOSTING_PANEL="cpanel"
    elif [ -d "$USER_HOME/.directadmin" ]; then
        print_success "Detected DirectAdmin hosting environment"
        HOSTING_PANEL="directadmin"
    else
        print_status "Generic hosting environment detected"
        HOSTING_PANEL="generic"
    fi
}

# Function to check hosting capabilities
check_hosting_capabilities() {
    print_status "Checking hosting capabilities..."

    # Check for Node.js
    if command -v node &> /dev/null; then
        NODE_VERSION=$(node --version)
        print_success "Node.js available: $NODE_VERSION"
        NODE_AVAILABLE=true
    else
        print_warning "Node.js not available - API features will be limited"
        NODE_AVAILABLE=false
    fi

    # Check for npm
    if command -v npm &> /dev/null; then
        print_success "npm available"
        NPM_AVAILABLE=true
    else
        print_warning "npm not available"
        NPM_AVAILABLE=false
    fi

    # Check for PHP
    if command -v php &> /dev/null; then
        PHP_VERSION=$(php --version | head -n1)
        print_success "PHP available: $PHP_VERSION"
        PHP_AVAILABLE=true
    else
        print_error "PHP not available - FlexPBX requires PHP"
        exit 1
    fi

    # Check for MySQL
    if command -v mysql &> /dev/null; then
        print_success "MySQL client available"
        MYSQL_AVAILABLE=true
    else
        print_warning "MySQL client not available - will use web-based setup"
        MYSQL_AVAILABLE=false
    fi

    # Check disk space
    DISK_USAGE=$(df -h "$USER_HOME" | tail -1 | awk '{print $5}' | sed 's/%//')
    if [ "$DISK_USAGE" -lt 80 ]; then
        print_success "Sufficient disk space available"
    else
        print_warning "Disk space usage is $DISK_USAGE% - consider cleaning up"
    fi
}

# Function to create directory structure
create_directories() {
    print_status "Creating directory structure for shared hosting..."

    # Create apps directory
    mkdir -p "$APPS_DIR/flexpbx"
    mkdir -p "$CONFIG_DIR"
    mkdir -p "$LOG_DIR"
    mkdir -p "$BACKUP_DIR"

    # Create web directory structure
    mkdir -p "$WEB_DIR"
    mkdir -p "$WEB_DIR/api"
    mkdir -p "$WEB_DIR/admin"
    mkdir -p "$WEB_DIR/phone"
    mkdir -p "$WEB_DIR/assets"
    mkdir -p "$WEB_DIR/css"
    mkdir -p "$WEB_DIR/js"
    mkdir -p "$WEB_DIR/images"

    print_success "Directory structure created"
}

# Function to deploy web files
deploy_web_files() {
    print_status "Deploying web files to public_html..."

    # Deploy main web files
    if [ -d "public_html" ]; then
        cp -r public_html/* "$WEB_DIR/"
        print_success "Web files deployed to $WEB_DIR"
    fi

    # Deploy PHP API files if they exist
    if [ -d "api" ]; then
        cp -r api/* "$WEB_DIR/api/"
        print_success "API files deployed"
    fi

    # Set proper permissions for web files
    find "$WEB_DIR" -type f -name "*.php" -exec chmod 644 {} \;
    find "$WEB_DIR" -type f -name "*.html" -exec chmod 644 {} \;
    find "$WEB_DIR" -type f -name "*.css" -exec chmod 644 {} \;
    find "$WEB_DIR" -type f -name "*.js" -exec chmod 644 {} \;
    find "$WEB_DIR" -type d -exec chmod 755 {} \;

    print_success "File permissions set"
}

# Function to create shared hosting configuration
create_shared_config() {
    print_status "Creating shared hosting configuration..."

    # Create main configuration file
    cat > "$CONFIG_DIR/config.php" << EOF
<?php
/**
 * FlexPBX Shared Hosting Configuration
 * Version: $FLEXPBX_VERSION
 */

// Installation settings
define('FLEXPBX_VERSION', '$FLEXPBX_VERSION');
define('INSTALL_TYPE', 'shared_hosting');
define('HOSTING_TYPE', '$HOSTING_TYPE');
define('HOSTING_PANEL', '$HOSTING_PANEL');

// Directory paths
define('USER_HOME', '$USER_HOME');
define('APPS_DIR', '$APPS_DIR/flexpbx');
define('WEB_DIR', '$WEB_DIR');
define('CONFIG_DIR', '$CONFIG_DIR');
define('LOG_DIR', '$LOG_DIR');
define('BACKUP_DIR', '$BACKUP_DIR');

// Web configuration
define('WEB_BASE_URL', 'https://' . \$_SERVER['HTTP_HOST'] . '/$SUBDIRECTORY');
define('API_BASE_URL', WEB_BASE_URL . '/api');
define('SUBDIRECTORY', '$SUBDIRECTORY');

// Node.js configuration
define('NODE_AVAILABLE', $NODE_AVAILABLE ? 'true' : 'false');
define('NODE_PORT', $CUSTOM_PORT);

// Database configuration (edit these values)
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');
define('DB_PREFIX', 'flexpbx_');

// Security settings
define('JWT_SECRET', '$(openssl rand -base64 32 2>/dev/null || echo "change_this_secret")');
define('ENCRYPTION_KEY', '$(openssl rand -base64 32 2>/dev/null || echo "change_this_key")');

// Multi-domain support
\$allowed_domains = [
    \$_SERVER['HTTP_HOST'],
    'api.' . \$_SERVER['HTTP_HOST']
];

// Email configuration (for notifications)
define('SMTP_HOST', '');
define('SMTP_PORT', 587);
define('SMTP_USER', '');
define('SMTP_PASS', '');
define('FROM_EMAIL', 'noreply@' . \$_SERVER['HTTP_HOST']);

// Feature flags
define('ENABLE_NODE_API', $NODE_AVAILABLE ? 'true' : 'false');
define('ENABLE_PHP_FALLBACK', 'true');
define('ENABLE_FILE_MANAGER', 'true');
define('ENABLE_BACKUP_SYSTEM', 'true');

// Logging
define('LOG_LEVEL', 'info');
define('LOG_FILE', LOG_DIR . '/flexpbx.log');
define('ERROR_LOG', LOG_DIR . '/error.log');

?>
EOF

    print_success "Configuration file created"
}

# Function to create .htaccess for subdirectory
create_htaccess() {
    print_status "Creating .htaccess for subdirectory access..."

    cat > "$WEB_DIR/.htaccess" << 'EOF'
# FlexPBX Shared Hosting .htaccess Configuration

RewriteEngine On
RewriteBase /flexpbx/

# Security - Deny access to sensitive files
<FilesMatch "\.(env|log|config|sql|sh)$">
    Require all denied
</FilesMatch>

<FilesMatch "^(config|install|setup)\.php$">
    Require all denied
</FilesMatch>

# Deny access to directories
RedirectMatch 404 /\..*$
RedirectMatch 404 /config/
RedirectMatch 404 /logs/
RedirectMatch 404 /backup/

# Handle API requests
RewriteRule ^api/(.*)$ api/index.php?request=$1 [QSA,L]

# Handle admin requests
RewriteRule ^admin/(.*)$ admin/index.php?page=$1 [QSA,L]

# Handle phone app requests
RewriteRule ^phone/(.*)$ phone/index.php?route=$1 [QSA,L]

# Handle client-side routing for SPA
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_URI} !^/flexpbx/(api|admin|phone)/
RewriteRule . /flexpbx/index.html [L]

# Security headers
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options DENY
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
</IfModule>

# Enable compression
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/plain
    AddOutputFilterByType DEFLATE text/html
    AddOutputFilterByType DEFLATE text/xml
    AddOutputFilterByType DEFLATE text/css
    AddOutputFilterByType DEFLATE application/xml
    AddOutputFilterByType DEFLATE application/xhtml+xml
    AddOutputFilterByType DEFLATE application/rss+xml
    AddOutputFilterByType DEFLATE application/javascript
    AddOutputFilterByType DEFLATE application/x-javascript
    AddOutputFilterByType DEFLATE application/json
</IfModule>

# Caching for static assets
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType image/jpg "access plus 1 year"
    ExpiresByType image/jpeg "access plus 1 year"
    ExpiresByType image/gif "access plus 1 year"
    ExpiresByType image/svg+xml "access plus 1 month"
</IfModule>

# PHP settings
<IfModule mod_php.c>
    php_value upload_max_filesize 10M
    php_value post_max_size 10M
    php_value max_execution_time 120
    php_value memory_limit 128M
</IfModule>
EOF

    print_success ".htaccess created for subdirectory access"
}

# Function to create PHP-based API fallback
create_php_api() {
    print_status "Creating PHP-based API fallback..."

    mkdir -p "$WEB_DIR/api"

    # Create main API router
    cat > "$WEB_DIR/api/index.php" << 'EOF'
<?php
/**
 * FlexPBX Shared Hosting API Router
 * Provides basic API functionality when Node.js is not available
 */

require_once '../config/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Get request path
$request = $_GET['request'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// Basic routing
switch ($request) {
    case 'info':
        handleServerInfo();
        break;

    case 'health':
        handleHealthCheck();
        break;

    case 'auth/device/register':
        handleDeviceRegistration();
        break;

    case 'auth/device/authorize':
        handleDeviceAuthorization();
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found']);
        break;
}

function handleServerInfo() {
    $info = [
        'success' => true,
        'name' => 'FlexPBX Server',
        'version' => FLEXPBX_VERSION,
        'type' => 'shared_hosting',
        'node_available' => ENABLE_NODE_API === 'true',
        'supported_domains' => [
            $_SERVER['HTTP_HOST']
        ],
        'endpoints' => [
            'info' => '/api/info',
            'health' => '/api/health',
            'auth' => '/api/auth/*'
        ]
    ];

    echo json_encode($info);
}

function handleHealthCheck() {
    $health = [
        'status' => 'ok',
        'timestamp' => date('c'),
        'server' => $_SERVER['HTTP_HOST'],
        'php_version' => PHP_VERSION,
        'memory_usage' => memory_get_usage(true),
        'disk_free' => disk_free_space('.')
    ];

    echo json_encode($health);
}

function handleDeviceRegistration() {
    // Basic device registration (extend as needed)
    $input = json_decode(file_get_contents('php://input'), true);

    $response = [
        'success' => true,
        'message' => 'Device registration received',
        'device_id' => $input['device_id'] ?? uniqid(),
        'status' => 'pending_approval'
    ];

    echo json_encode($response);
}

function handleDeviceAuthorization() {
    // Basic device authorization (extend as needed)
    $input = json_decode(file_get_contents('php://input'), true);

    $response = [
        'success' => true,
        'message' => 'Authorization processed',
        'authorized' => false,
        'requires_admin_approval' => true
    ];

    echo json_encode($response);
}
?>
EOF

    print_success "PHP API fallback created"
}

# Function to create database setup for shared hosting
create_database_setup() {
    print_status "Creating database setup for shared hosting..."

    cat > "$APPS_DIR/flexpbx/database-setup-shared.sql" << 'EOF'
-- FlexPBX Shared Hosting Database Setup
-- Run this in your hosting control panel's phpMyAdmin or MySQL interface

-- Create users table
CREATE TABLE IF NOT EXISTS flexpbx_users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user', 'operator') DEFAULT 'user',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create extensions table
CREATE TABLE IF NOT EXISTS flexpbx_extensions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    extension VARCHAR(20) UNIQUE NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    secret VARCHAR(100) NOT NULL,
    user_id INT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES flexpbx_users(id)
);

-- Create devices table
CREATE TABLE IF NOT EXISTS flexpbx_devices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    device_id VARCHAR(100) UNIQUE NOT NULL,
    device_name VARCHAR(100) NOT NULL,
    device_type VARCHAR(50) NOT NULL,
    user_agent TEXT,
    ip_address VARCHAR(45),
    location VARCHAR(200),
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approved_at TIMESTAMP NULL,
    approved_by INT NULL,
    FOREIGN KEY (approved_by) REFERENCES flexpbx_users(id)
);

-- Create API keys table
CREATE TABLE IF NOT EXISTS flexpbx_api_keys (
    id INT PRIMARY KEY AUTO_INCREMENT,
    device_id VARCHAR(100) NOT NULL,
    api_key VARCHAR(100) UNIQUE NOT NULL,
    expires_at TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (device_id) REFERENCES flexpbx_devices(device_id)
);

-- Create pincodes table
CREATE TABLE IF NOT EXISTS flexpbx_pincodes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    device_id VARCHAR(100) NOT NULL,
    pincode VARCHAR(6) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (device_id) REFERENCES flexpbx_devices(device_id)
);

-- Create settings table
CREATE TABLE IF NOT EXISTS flexpbx_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default admin user (password: admin123 - CHANGE THIS!)
INSERT IGNORE INTO flexpbx_users (username, email, password_hash, role)
VALUES ('admin', 'admin@localhost', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Insert default settings
INSERT IGNORE INTO flexpbx_settings (setting_key, setting_value) VALUES
('system_name', 'FlexPBX'),
('system_version', '1.0.0'),
('auto_approve_devices', 'false'),
('max_pincode_attempts', '3'),
('pincode_expiry_minutes', '60');
EOF

    print_success "Database setup file created"
}

# Function to create installation instructions
create_installation_instructions() {
    print_status "Creating installation instructions..."

    cat > "$APPS_DIR/flexpbx/SHARED-HOSTING-SETUP.md" << EOF
# FlexPBX Shared Hosting Setup Instructions

## 🎉 Installation Complete!

Your FlexPBX files have been deployed to your shared hosting account.

### 📁 **File Locations**
- **Web Files**: $WEB_DIR
- **App Files**: $APPS_DIR/flexpbx
- **Configuration**: $CONFIG_DIR
- **Logs**: $LOG_DIR

### 🌐 **Web Access**
- **Main Interface**: https://yourdomain.com/$SUBDIRECTORY/
- **Admin Panel**: https://yourdomain.com/$SUBDIRECTORY/admin/
- **Phone App**: https://yourdomain.com/$SUBDIRECTORY/phone/
- **API**: https://yourdomain.com/$SUBDIRECTORY/api/

### 🔧 **Next Steps**

#### 1. **Database Setup**
1. Access your hosting control panel (cPanel/DirectAdmin)
2. Go to MySQL Databases or Database section
3. Create a new database named: \`your_username_flexpbx\`
4. Create a database user with full privileges
5. Import the database schema:
   - Upload \`database-setup-shared.sql\` via phpMyAdmin
   - Or copy/paste the SQL content

#### 2. **Configure Database Connection**
Edit the configuration file:
\`\`\`
$CONFIG_DIR/config.php
\`\`\`

Update these values:
\`\`\`php
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_username_flexpbx');
define('DB_USER', 'your_username_dbuser');
define('DB_PASS', 'your_database_password');
\`\`\`

#### 3. **Set File Permissions**
Ensure proper permissions via File Manager:
- Directories: 755
- PHP files: 644
- Log directory: 777 (or 755 if possible)

#### 4. **Test Installation**
Visit: https://yourdomain.com/$SUBDIRECTORY/

#### 5. **Default Login**
- **Username**: admin
- **Password**: admin123
- **⚠️ CHANGE THIS IMMEDIATELY!**

### 🔌 **Node.js Setup (Optional)**

If your hosting supports Node.js:

1. **Check Node.js availability**:
   \`\`\`bash
   node --version
   npm --version
   \`\`\`

2. **Install dependencies**:
   \`\`\`bash
   cd $APPS_DIR/flexpbx
   npm install --production
   \`\`\`

3. **Start Node.js app**:
   \`\`\`bash
   cd $APPS_DIR/flexpbx
   node app.js
   \`\`\`

### 🛠️ **cPanel-Specific Instructions**

#### **File Manager**
1. Upload FlexPBX files to \`public_html/$SUBDIRECTORY/\`
2. Extract files directly in File Manager
3. Set permissions via File Manager

#### **MySQL Databases**
1. Create database: \`cpanel_user_flexpbx\`
2. Create user: \`cpanel_user_flexpbx\`
3. Add user to database with ALL PRIVILEGES
4. Import SQL via phpMyAdmin

#### **Subdomains (Optional)**
1. Create subdomain: \`flexpbx.yourdomain.com\`
2. Point to \`public_html/flexpbx\`
3. Update configuration accordingly

### 🔧 **DirectAdmin-Specific Instructions**

#### **File Manager**
1. Upload to \`domains/yourdomain.com/public_html/$SUBDIRECTORY/\`
2. Extract files
3. Set permissions

#### **MySQL Management**
1. Create database via MySQL Management
2. Import SQL file
3. Update config.php with connection details

### 📞 **Support**

#### **Troubleshooting**
- Check error logs in \`$LOG_DIR/error.log\`
- Verify file permissions
- Test database connection
- Check .htaccess rules

#### **Common Issues**
1. **500 Internal Server Error**: Check .htaccess syntax
2. **Database Connection Failed**: Verify credentials
3. **Permission Denied**: Check file/directory permissions
4. **API Not Working**: Ensure mod_rewrite is enabled

#### **Getting Help**
- Check hosting documentation
- Contact hosting support for Node.js setup
- Review FlexPBX documentation

---

## 🎉 **Your FlexPBX is Ready!**

Access your FlexPBX installation at:
**https://yourdomain.com/$SUBDIRECTORY/**

Remember to:
1. Change the default admin password
2. Configure your database connection
3. Set up proper file permissions
4. Test all functionality

EOF

    print_success "Installation instructions created"
}

# Function to create management scripts
create_management_scripts() {
    print_status "Creating management scripts..."

    # Create backup script
    cat > "$APPS_DIR/flexpbx/backup-shared.sh" << EOF
#!/bin/bash
# FlexPBX Shared Hosting Backup Script

BACKUP_DIR="$BACKUP_DIR"
DATE=\$(date +%Y%m%d-%H%M%S)

# Create backup directory
mkdir -p "\$BACKUP_DIR"

# Backup web files
echo "Backing up web files..."
tar -czf "\$BACKUP_DIR/web-files-\$DATE.tar.gz" -C "$WEB_DIR" .

# Backup app files
echo "Backing up app files..."
tar -czf "\$BACKUP_DIR/app-files-\$DATE.tar.gz" -C "$APPS_DIR/flexpbx" .

# Backup database (if mysql available)
if command -v mysqldump &> /dev/null; then
    echo "Backing up database..."
    mysqldump -h DB_HOST -u DB_USER -pDB_PASS DB_NAME > "\$BACKUP_DIR/database-\$DATE.sql"
fi

echo "Backup completed: \$BACKUP_DIR"
ls -la "\$BACKUP_DIR"
EOF

    # Create log viewer script
    cat > "$APPS_DIR/flexpbx/view-logs.sh" << EOF
#!/bin/bash
# FlexPBX Log Viewer

echo "=== FlexPBX Logs ==="
echo

if [ -f "$LOG_DIR/flexpbx.log" ]; then
    echo "Application Log:"
    tail -20 "$LOG_DIR/flexpbx.log"
    echo
fi

if [ -f "$LOG_DIR/error.log" ]; then
    echo "Error Log:"
    tail -20 "$LOG_DIR/error.log"
    echo
fi

echo "Log files location: $LOG_DIR"
EOF

    # Make scripts executable
    chmod +x "$APPS_DIR/flexpbx"/*.sh

    print_success "Management scripts created"
}

# Function to display installation summary
display_summary() {
    print_success "FlexPBX Shared Hosting Installation completed!"
    echo
    echo "=== Installation Summary ==="
    echo "Version: $FLEXPBX_VERSION"
    echo "Install Type: Shared Hosting"
    echo "Hosting Type: $HOSTING_TYPE"
    echo "Hosting Panel: $HOSTING_PANEL"
    echo "Web Directory: $WEB_DIR"
    echo "Apps Directory: $APPS_DIR/flexpbx"
    echo
    echo "=== Web Access ==="
    echo "Main Site: https://$(hostname)/$(basename $WEB_DIR)/"
    echo "Admin Panel: https://$(hostname)/$(basename $WEB_DIR)/admin/"
    echo "Phone App: https://$(hostname)/$(basename $WEB_DIR)/phone/"
    echo "API: https://$(hostname)/$(basename $WEB_DIR)/api/"
    echo
    echo "=== Important Files ==="
    echo "Configuration: $CONFIG_DIR/config.php"
    echo "Database Setup: $APPS_DIR/flexpbx/database-setup-shared.sql"
    echo "Setup Guide: $APPS_DIR/flexpbx/SHARED-HOSTING-SETUP.md"
    echo
    echo "=== Next Steps ==="
    echo "1. Read the setup guide: $APPS_DIR/flexpbx/SHARED-HOSTING-SETUP.md"
    echo "2. Create your database via hosting control panel"
    echo "3. Import the database schema"
    echo "4. Update database configuration in config.php"
    echo "5. Visit your FlexPBX installation and login"
    echo
    echo "=== Default Login ==="
    echo "Username: admin"
    echo "Password: admin123"
    print_warning "CHANGE THE DEFAULT PASSWORD IMMEDIATELY!"
    echo
    print_success "Installation complete! Check the setup guide for detailed instructions."
}

# Main installation function
main() {
    echo "========================================"
    echo "FlexPBX Shared Hosting Installer v$FLEXPBX_VERSION"
    echo "========================================"
    echo

    # Parse command line arguments
    while [[ $# -gt 0 ]]; do
        case $1 in
            --subdirectory=*)
                SUBDIRECTORY="${1#*=}"
                WEB_DIR="$USER_HOME/public_html/$SUBDIRECTORY"
                shift
                ;;
            --port=*)
                CUSTOM_PORT="${1#*=}"
                shift
                ;;
            --help)
                echo "Usage: $0 [OPTIONS]"
                echo "Options:"
                echo "  --subdirectory=DIR  Subdirectory name (default: flexpbx)"
                echo "  --port=PORT         Node.js port (default: 3000)"
                echo "  --help              Show this help"
                exit 0
                ;;
            *)
                print_error "Unknown option: $1"
                exit 1
                ;;
        esac
    done

    # Update paths based on subdirectory
    CONFIG_DIR="$APPS_DIR/flexpbx/config"
    LOG_DIR="$APPS_DIR/flexpbx/logs"
    BACKUP_DIR="$APPS_DIR/flexpbx/backup"

    # Run installation steps
    detect_hosting_environment
    check_hosting_capabilities
    create_directories
    deploy_web_files
    create_shared_config
    create_htaccess
    create_php_api
    create_database_setup
    create_installation_instructions
    create_management_scripts
    display_summary
}

# Run main function
main "$@"