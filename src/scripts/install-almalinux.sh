#!/bin/bash

# FlexPBX Remote Server - AlmaLinux Installation Script
# Supports: AlmaLinux 8/9, Rocky Linux 8/9, CentOS Stream 8/9
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
INSTALL_DIR="/home/flexpbxuser"
APPS_DIR="$INSTALL_DIR/apps/flexpbx"
WEB_DIR="$INSTALL_DIR/public_html"
CONFIG_DIR="$APPS_DIR/config"
LOG_DIR="$APPS_DIR/logs"
BACKUP_DIR="$APPS_DIR/backup"

# Installation type
INSTALL_TYPE="full"
USER_NAME="flexpbxuser"
GROUP_NAME="flexpbx"

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

# Function to check if running as root
check_root() {
    if [[ $EUID -ne 0 ]]; then
        print_error "This script must be run as root"
        exit 1
    fi
}

# Function to detect OS
detect_os() {
    if [[ -f /etc/almalinux-release ]]; then
        OS="almalinux"
        VERSION=$(cat /etc/almalinux-release | grep -oP '(?<=release )\d+')
    elif [[ -f /etc/rocky-release ]]; then
        OS="rocky"
        VERSION=$(cat /etc/rocky-release | grep -oP '(?<=release )\d+')
    elif [[ -f /etc/centos-release ]]; then
        OS="centos"
        VERSION=$(cat /etc/centos-release | grep -oP '(?<=release )\d+')
    else
        print_error "Unsupported operating system. This script supports AlmaLinux, Rocky Linux, and CentOS Stream."
        exit 1
    fi

    print_status "Detected OS: $OS $VERSION"
}

# Function to install system dependencies
install_dependencies() {
    print_status "Installing system dependencies..."

    # Update system
    dnf update -y

    # Install EPEL repository
    dnf install -y epel-release

    # Install development tools
    dnf groupinstall -y "Development Tools"

    # Install Node.js 18 LTS
    dnf module enable -y nodejs:18
    dnf install -y nodejs npm

    # Install system packages
    dnf install -y \
        httpd \
        mariadb-server \
        php \
        php-cli \
        php-fpm \
        php-mysql \
        php-json \
        php-opcache \
        php-xml \
        php-gd \
        php-curl \
        php-mbstring \
        php-zip \
        openssl \
        openssl-devel \
        curl \
        wget \
        git \
        unzip \
        tar \
        supervisor \
        fail2ban \
        firewalld \
        certbot \
        python3-certbot-apache \
        redis \
        nginx

    # Install PM2 for Node.js process management
    npm install -g pm2

    print_success "System dependencies installed"
}

# Function to create system user
create_user() {
    print_status "Creating FlexPBX system user..."

    # Create group
    if ! getent group $GROUP_NAME > /dev/null 2>&1; then
        groupadd $GROUP_NAME
        print_success "Created group: $GROUP_NAME"
    fi

    # Create user
    if ! id $USER_NAME > /dev/null 2>&1; then
        useradd -r -m -g $GROUP_NAME -s /bin/bash -d $INSTALL_DIR $USER_NAME
        print_success "Created user: $USER_NAME"
    else
        print_warning "User $USER_NAME already exists"
    fi

    # Set up user directories
    sudo -u $USER_NAME mkdir -p $APPS_DIR
    sudo -u $USER_NAME mkdir -p $WEB_DIR
    sudo -u $USER_NAME mkdir -p $CONFIG_DIR
    sudo -u $USER_NAME mkdir -p $LOG_DIR
    sudo -u $USER_NAME mkdir -p $BACKUP_DIR

    # Set permissions
    chown -R $USER_NAME:$GROUP_NAME $INSTALL_DIR
    chmod -R 755 $INSTALL_DIR

    print_success "User directories created and configured"
}

# Function to configure Apache
configure_apache() {
    print_status "Configuring Apache web server..."

    # Enable and start Apache
    systemctl enable httpd
    systemctl start httpd

    # Create virtual host configuration
    cat > /etc/httpd/conf.d/flexpbx.conf << EOF
# FlexPBX Virtual Host Configuration

<VirtualHost *:80>
    ServerName flexpbx.devinecreations.net
    ServerAlias api.devinecreations.net
    ServerAlias api.tappedin.fm
    ServerAlias api.devine-creations.com
    ServerAlias api.raywonderis.me

    DocumentRoot $WEB_DIR
    DirectoryIndex index.php index.html

    <Directory "$WEB_DIR">
        AllowOverride All
        Require all granted
        Options Indexes FollowSymLinks
    </Directory>

    # API Proxy Configuration
    ProxyPreserveHost On
    ProxyRequests Off

    # Route API requests to Node.js backend
    ProxyPass /api/ http://localhost:3000/api/
    ProxyPassReverse /api/ http://localhost:3000/api/

    # WebSocket support for real-time features
    ProxyPass /ws/ ws://localhost:3000/ws/
    ProxyPassReverse /ws/ ws://localhost:3000/ws/

    # Security headers
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options DENY
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Strict-Transport-Security "max-age=63072000; includeSubDomains; preload"

    # Logging
    ErrorLog $LOG_DIR/apache_error.log
    CustomLog $LOG_DIR/apache_access.log combined
</VirtualHost>

# HTTPS Virtual Host (will be configured after SSL setup)
<VirtualHost *:443>
    ServerName flexpbx.devinecreations.net
    ServerAlias api.devinecreations.net
    ServerAlias api.tappedin.fm
    ServerAlias api.devine-creations.com
    ServerAlias api.raywonderis.me

    DocumentRoot $WEB_DIR
    DirectoryIndex index.php index.html

    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/flexpbx.devinecreations.net/cert.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/flexpbx.devinecreations.net/privkey.pem
    SSLCertificateChainFile /etc/letsencrypt/live/flexpbx.devinecreations.net/chain.pem

    <Directory "$WEB_DIR">
        AllowOverride All
        Require all granted
        Options Indexes FollowSymLinks
    </Directory>

    # API Proxy Configuration
    ProxyPreserveHost On
    ProxyRequests Off

    ProxyPass /api/ http://localhost:3000/api/
    ProxyPassReverse /api/ http://localhost:3000/api/

    ProxyPass /ws/ ws://localhost:3000/ws/
    ProxyPassReverse /ws/ ws://localhost:3000/ws/

    # Security headers
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options DENY
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Strict-Transport-Security "max-age=63072000; includeSubDomains; preload"

    ErrorLog $LOG_DIR/apache_ssl_error.log
    CustomLog $LOG_DIR/apache_ssl_access.log combined
</VirtualHost>
EOF

    # Enable required Apache modules
    systemctl restart httpd

    print_success "Apache configured for FlexPBX"
}

# Function to configure MariaDB
configure_database() {
    print_status "Configuring MariaDB database..."

    # Enable and start MariaDB
    systemctl enable mariadb
    systemctl start mariadb

    # Generate secure passwords
    DB_ROOT_PASSWORD=$(openssl rand -base64 32)
    DB_FLEXPBX_PASSWORD=$(openssl rand -base64 32)

    # Secure MariaDB installation
    mysql -e "UPDATE mysql.user SET Password = PASSWORD('$DB_ROOT_PASSWORD') WHERE User = 'root'"
    mysql -e "DELETE FROM mysql.user WHERE User=''"
    mysql -e "DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1')"
    mysql -e "DROP DATABASE IF EXISTS test"
    mysql -e "DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%'"
    mysql -e "FLUSH PRIVILEGES"

    # Create FlexPBX database and user
    mysql -u root -p$DB_ROOT_PASSWORD -e "CREATE DATABASE flexpbx CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    mysql -u root -p$DB_ROOT_PASSWORD -e "CREATE USER 'flexpbx'@'localhost' IDENTIFIED BY '$DB_FLEXPBX_PASSWORD';"
    mysql -u root -p$DB_ROOT_PASSWORD -e "GRANT ALL PRIVILEGES ON flexpbx.* TO 'flexpbx'@'localhost';"
    mysql -u root -p$DB_ROOT_PASSWORD -e "FLUSH PRIVILEGES;"

    # Save database credentials
    cat > $CONFIG_DIR/database.conf << EOF
# FlexPBX Database Configuration
DB_HOST=localhost
DB_PORT=3306
DB_NAME=flexpbx
DB_USER=flexpbx
DB_PASSWORD=$DB_FLEXPBX_PASSWORD
DB_ROOT_PASSWORD=$DB_ROOT_PASSWORD
EOF

    chmod 600 $CONFIG_DIR/database.conf
    chown $USER_NAME:$GROUP_NAME $CONFIG_DIR/database.conf

    print_success "MariaDB configured with FlexPBX database"
}

# Function to configure firewall
configure_firewall() {
    print_status "Configuring firewall..."

    # Enable and start firewalld
    systemctl enable firewalld
    systemctl start firewalld

    # Open required ports
    firewall-cmd --permanent --add-service=http
    firewall-cmd --permanent --add-service=https
    firewall-cmd --permanent --add-service=ssh

    # SIP ports
    firewall-cmd --permanent --add-port=5060/udp
    firewall-cmd --permanent --add-port=5061/tcp
    firewall-cmd --permanent --add-port=5061/udp

    # RTP ports range
    firewall-cmd --permanent --add-port=10000-20000/udp

    # API and WebSocket ports
    firewall-cmd --permanent --add-port=3000/tcp
    firewall-cmd --permanent --add-port=8080/tcp

    # Reload firewall
    firewall-cmd --reload

    print_success "Firewall configured"
}

# Function to install FlexPBX application
install_flexpbx() {
    print_status "Installing FlexPBX application..."

    # Switch to flexpbx user for application installation
    sudo -u $USER_NAME bash << 'EOFUSER'
    cd /home/flexpbxuser/apps/flexpbx

    # Create package.json for Node.js backend
    cat > package.json << 'EOFPACKAGE'
{
  "name": "flexpbx-server",
  "version": "1.0.0",
  "description": "FlexPBX Remote Server System",
  "main": "app.js",
  "scripts": {
    "start": "node app.js",
    "dev": "nodemon app.js",
    "pm2:start": "pm2 start ecosystem.config.js",
    "pm2:stop": "pm2 stop ecosystem.config.js",
    "pm2:restart": "pm2 restart ecosystem.config.js"
  },
  "dependencies": {
    "express": "^4.18.2",
    "socket.io": "^4.7.2",
    "mysql2": "^3.6.0",
    "bcryptjs": "^2.4.3",
    "jsonwebtoken": "^9.0.2",
    "cors": "^2.8.5",
    "helmet": "^7.0.0",
    "express-rate-limit": "^6.10.0",
    "multer": "^1.4.5",
    "nodemailer": "^6.9.4",
    "winston": "^3.10.0",
    "dotenv": "^16.3.1",
    "node-cron": "^3.0.2",
    "compression": "^1.7.4",
    "body-parser": "^1.20.2",
    "uuid": "^9.0.0"
  },
  "devDependencies": {
    "nodemon": "^3.0.1"
  },
  "keywords": ["pbx", "sip", "voip", "telecommunications"],
  "author": "FlexPBX Team",
  "license": "MIT"
}
EOFPACKAGE

    # Install Node.js dependencies
    npm install

    # Create PM2 ecosystem configuration
    cat > ecosystem.config.js << 'EOFECO'
module.exports = {
  apps: [{
    name: 'flexpbx-server',
    script: './app.js',
    instances: 'max',
    exec_mode: 'cluster',
    env: {
      NODE_ENV: 'production',
      PORT: 3000
    },
    error_file: './logs/err.log',
    out_file: './logs/out.log',
    log_file: './logs/combined.log',
    time: true,
    max_memory_restart: '1G',
    node_args: '--max_old_space_size=1024'
  }]
};
EOFECO

EOFUSER

    print_success "FlexPBX application installed"
}

# Function to setup SSL certificates
setup_ssl() {
    print_status "Setting up SSL certificates..."

    # Request SSL certificate for primary domain
    certbot --apache -d flexpbx.devinecreations.net --non-interactive --agree-tos --email admin@devinecreations.net

    # Set up auto-renewal
    echo "0 12 * * * /usr/bin/certbot renew --quiet" | crontab -

    print_success "SSL certificates configured"
}

# Function to create systemd services
create_services() {
    print_status "Creating systemd services..."

    # FlexPBX service
    cat > /etc/systemd/system/flexpbx.service << EOF
[Unit]
Description=FlexPBX Server
After=network.target mariadb.service

[Service]
Type=simple
User=$USER_NAME
Group=$GROUP_NAME
WorkingDirectory=$APPS_DIR
ExecStart=/usr/bin/node app.js
Restart=always
RestartSec=10
Environment=NODE_ENV=production
Environment=PORT=3000

[Install]
WantedBy=multi-user.target
EOF

    # Enable and start service
    systemctl daemon-reload
    systemctl enable flexpbx.service

    print_success "Systemd services created"
}

# Function to display installation summary
display_summary() {
    print_success "FlexPBX Remote Server installation completed!"
    echo
    echo "=== Installation Summary ==="
    echo "Version: $FLEXPBX_VERSION"
    echo "OS: $OS $VERSION"
    echo "Install Type: $INSTALL_TYPE"
    echo "Install Directory: $INSTALL_DIR"
    echo "Web Directory: $WEB_DIR"
    echo "User: $USER_NAME"
    echo "Group: $GROUP_NAME"
    echo
    echo "=== Access Information ==="
    echo "Web Interface: https://flexpbx.devinecreations.net"
    echo "Admin Portal: https://flexpbx.devinecreations.net/admin"
    echo "API Endpoints:"
    echo "  - https://api.devinecreations.net"
    echo "  - https://api.tappedin.fm"
    echo "  - https://api.devine-creations.com"
    echo "  - https://api.raywonderis.me"
    echo
    echo "=== Next Steps ==="
    echo "1. Complete the web-based setup wizard"
    echo "2. Configure SIP extensions and trunks"
    echo "3. Set up desktop client connections"
    echo "4. Test FlexPhone connectivity"
    echo
    echo "=== Service Management ==="
    echo "Start FlexPBX: systemctl start flexpbx"
    echo "Stop FlexPBX: systemctl stop flexpbx"
    echo "Status: systemctl status flexpbx"
    echo "Logs: journalctl -u flexpbx -f"
}

# Main installation function
main() {
    echo "========================================"
    echo "FlexPBX Remote Server Installer v$FLEXPBX_VERSION"
    echo "========================================"

    # Parse command line arguments
    while [[ $# -gt 0 ]]; do
        case $1 in
            --full)
                INSTALL_TYPE="full"
                shift
                ;;
            --minimal)
                INSTALL_TYPE="minimal"
                shift
                ;;
            --dev)
                INSTALL_TYPE="development"
                shift
                ;;
            --user=*)
                USER_NAME="${1#*=}"
                shift
                ;;
            --help)
                echo "Usage: $0 [OPTIONS]"
                echo "Options:"
                echo "  --full      Full installation (default)"
                echo "  --minimal   Minimal installation"
                echo "  --dev       Development installation"
                echo "  --user=NAME Custom username (default: flexpbxuser)"
                echo "  --help      Show this help"
                exit 0
                ;;
            *)
                print_error "Unknown option: $1"
                exit 1
                ;;
        esac
    done

    # Run installation steps
    check_root
    detect_os
    install_dependencies
    create_user
    configure_apache
    configure_database
    configure_firewall
    install_flexpbx
    setup_ssl
    create_services
    display_summary
}

# Run main function
main "$@"