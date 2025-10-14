#!/bin/bash
# FlexPBX Server Installation Script
# Deploys FlexPBX server components and database setup

set -e

echo "🚀 FlexPBX Server Installation Starting..."

# Configuration
DB_NAME="flexpbx"
DB_USER="flexpbx_user"
DB_PASS="FlexPBX2024!"
WEB_ROOT="/var/www/html"
LOG_DIR="/var/log/flexpbx"
CONFIG_DIR="/etc/flexpbx"

# Create directories
echo "📁 Creating directories..."
sudo mkdir -p "$WEB_ROOT/api"
sudo mkdir -p "$LOG_DIR"
sudo mkdir -p "$CONFIG_DIR/ssh"

# Install dependencies
echo "📦 Installing dependencies..."
sudo apt update
sudo apt install -y nginx mysql-server php-fpm php-mysql php-json php-curl

# Setup database
echo "🗄️ Setting up database..."
sudo mysql -e "CREATE DATABASE IF NOT EXISTS $DB_NAME;"
sudo mysql -e "CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';"
sudo mysql -e "GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost';"
sudo mysql -e "FLUSH PRIVILEGES;"

# Copy PHP files
echo "📄 Copying server files..."
sudo cp connection-manager.php "$WEB_ROOT/api/"
sudo cp config.php "$WEB_ROOT/api/"

# Set permissions
echo "🔒 Setting permissions..."
sudo chown -R www-data:www-data "$WEB_ROOT/api"
sudo chmod 644 "$WEB_ROOT/api"/*.php
sudo chown -R www-data:www-data "$LOG_DIR"
sudo chmod 755 "$LOG_DIR"

# Setup nginx
echo "🌐 Configuring nginx..."
sudo tee /etc/nginx/sites-available/flexpbx > /dev/null <<EOF
server {
    listen 80;
    listen 443 ssl;
    server_name flexpbx.devinecreations.net;

    root $WEB_ROOT;
    index index.php index.html;

    # SSL configuration (if certificates available)
    # ssl_certificate /path/to/cert.pem;
    # ssl_certificate_key /path/to/key.pem;

    location /api/ {
        try_files \$uri \$uri/ /api/connection-manager.php?\$query_string;

        location ~ \.php$ {
            fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
            fastcgi_index index.php;
            fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
            include fastcgi_params;
        }
    }

    # Rate limiting
    limit_req_zone \$binary_remote_addr zone=api:10m rate=10r/s;

    location /api/auth/ {
        limit_req zone=api burst=5 nodelay;
    }

    # Security headers
    add_header X-Frame-Options DENY;
    add_header X-Content-Type-Options nosniff;
    add_header X-XSS-Protection "1; mode=block";
}
EOF

# Enable site
sudo ln -sf /etc/nginx/sites-available/flexpbx /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx

# Setup systemd service for FlexPBX API
echo "⚙️ Creating systemd service..."
sudo tee /etc/systemd/system/flexpbx-api.service > /dev/null <<EOF
[Unit]
Description=FlexPBX API Service
After=network.target mysql.service

[Service]
Type=forking
ExecStart=/usr/sbin/php-fpm8.1 --fpm-config /etc/php/8.1/fpm/php-fpm.conf
ExecReload=/bin/kill -USR2 \$MAINPID
KillMode=mixed
KillSignal=SIGINT
TimeoutStopSec=5
PrivateTmp=true
Restart=on-failure

[Install]
WantedBy=multi-user.target
EOF

sudo systemctl daemon-reload
sudo systemctl enable flexpbx-api
sudo systemctl start flexpbx-api

# Create log rotation
echo "🔄 Setting up log rotation..."
sudo tee /etc/logrotate.d/flexpbx > /dev/null <<EOF
$LOG_DIR/*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    copytruncate
    su www-data www-data
}
EOF

# Generate SSH keys for deployment
echo "🔑 Generating SSH keys..."
sudo ssh-keygen -t rsa -b 4096 -f "$CONFIG_DIR/ssh/deployment_key" -N "" -C "flexpbx-deployment"
sudo chown www-data:www-data "$CONFIG_DIR/ssh/deployment_key"*
sudo chmod 600 "$CONFIG_DIR/ssh/deployment_key"

echo "✅ FlexPBX Server Installation Complete!"
echo ""
echo "🔗 API Endpoints:"
echo "  - Registration: https://flexpbx.devinecreations.net/api/register"
echo "  - Authorization: https://flexpbx.devinecreations.net/api/authorize"
echo "  - Status: https://flexpbx.devinecreations.net/api/status"
echo "  - Updates: https://flexpbx.devinecreations.net/api/update-check"
echo ""
echo "📋 Next Steps:"
echo "  1. Configure SSL certificates"
echo "  2. Set up firewall rules"
echo "  3. Test client connections"
echo "  4. Configure backup procedures"
echo ""
echo "🔐 Database Details:"
echo "  Database: $DB_NAME"
echo "  User: $DB_USER"
echo "  Host: localhost"