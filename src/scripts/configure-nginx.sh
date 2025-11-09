#!/bin/bash

# FlexPBX Nginx Configuration Script
# Configures Nginx with PHP-FPM, SSL, and custom ports
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
DOMAIN_NAME=""
WEB_ROOT=""
USER_NAME=""
CUSTOM_PORT=80
CUSTOM_SSL_PORT=443
NODE_PORT=3000
INSTALL_TYPE="system"

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

# Function to show usage
show_usage() {
    echo "Usage: $0 [OPTIONS]"
    echo
    echo "Options:"
    echo "  --domain=DOMAIN         Domain name (required)"
    echo "  --webroot=PATH          Web root directory (required)"
    echo "  --user=USERNAME         FlexPBX user name (default: flexpbxuser)"
    echo "  --port=PORT             HTTP port (default: 80)"
    echo "  --ssl-port=PORT         HTTPS port (default: 443)"
    echo "  --node-port=PORT        Node.js API port (default: 3000)"
    echo "  --user-install          Configure for user installation"
    echo "  --shared-hosting        Configure for shared hosting"
    echo "  --help                  Show this help"
    echo
    echo "Examples:"
    echo "  $0 --domain=flexpbx.example.com --webroot=/var/www/html"
    echo "  $0 --domain=flexpbx.example.com --webroot=/home/user/public_html --user=user --user-install"
    echo "  $0 --domain=example.com --webroot=/home/user/public_html --port=8080 --ssl-port=8443"
}

# Function to check if running with appropriate privileges
check_privileges() {
    if [[ "$INSTALL_TYPE" == "system" ]] && [[ $EUID -ne 0 ]]; then
        print_error "System installation requires root privileges. Use sudo or run as root."
        print_status "For user installation, use --user-install flag"
        exit 1
    fi

    if [[ "$INSTALL_TYPE" == "user" ]] && [[ $EUID -eq 0 ]]; then
        print_warning "Running user installation as root. This may cause permission issues."
    fi
}

# Function to detect Nginx installation
detect_nginx() {
    print_status "Detecting Nginx installation..."

    if command -v nginx &> /dev/null; then
        NGINX_VERSION=$(nginx -v 2>&1 | grep -o '[0-9.]*')
        print_success "Nginx found: version $NGINX_VERSION"
    else
        print_error "Nginx not found. Please install Nginx first."
        exit 1
    fi

    # Detect configuration directory
    if [ -d "/etc/nginx/sites-available" ]; then
        NGINX_SITES_DIR="/etc/nginx/sites-available"
        NGINX_ENABLED_DIR="/etc/nginx/sites-enabled"
        NGINX_STYLE="debian"
    elif [ -d "/etc/nginx/conf.d" ]; then
        NGINX_SITES_DIR="/etc/nginx/conf.d"
        NGINX_ENABLED_DIR="/etc/nginx/conf.d"
        NGINX_STYLE="rhel"
    else
        print_error "Could not detect Nginx configuration directory"
        exit 1
    fi

    print_success "Nginx configuration style: $NGINX_STYLE"
}

# Function to install PHP-FPM if needed
install_php_fpm() {
    print_status "Checking PHP-FPM installation..."

    if command -v php-fpm &> /dev/null; then
        PHP_VERSION=$(php --version | head -n1 | cut -d' ' -f2 | cut -d'.' -f1,2)
        print_success "PHP-FPM found: version $PHP_VERSION"
    else
        if [[ "$INSTALL_TYPE" == "system" ]]; then
            print_status "Installing PHP-FPM..."
            if command -v dnf &> /dev/null; then
                dnf install -y php-fpm php-cli php-mysql php-json php-opcache php-xml php-gd php-curl php-mbstring php-zip
            elif command -v yum &> /dev/null; then
                yum install -y php-fpm php-cli php-mysql php-json php-opcache php-xml php-gd php-curl php-mbstring php-zip
            elif command -v apt &> /dev/null; then
                apt update && apt install -y php-fpm php-cli php-mysql php-json php-opcache php-xml php-gd php-curl php-mbstring php-zip
            else
                print_error "Could not install PHP-FPM. Package manager not found."
                exit 1
            fi
            print_success "PHP-FPM installed"
        else
            print_error "PHP-FPM not found and cannot install without root privileges"
            exit 1
        fi
    fi

    # Detect PHP-FPM socket/port
    if [ -S "/var/run/php-fpm/www.sock" ]; then
        PHP_FPM_SOCKET="/var/run/php-fpm/www.sock"
    elif [ -S "/run/php/php$PHP_VERSION-fpm.sock" ]; then
        PHP_FPM_SOCKET="/run/php/php$PHP_VERSION-fpm.sock"
    else
        PHP_FPM_SOCKET="127.0.0.1:9000"
    fi

    print_success "PHP-FPM socket/port: $PHP_FPM_SOCKET"
}

# Function to create SSL certificate
create_ssl_certificate() {
    print_status "Setting up SSL certificate..."

    SSL_DIR="/etc/ssl/flexpbx"

    if [[ "$INSTALL_TYPE" == "system" ]]; then
        # Try Let's Encrypt first
        if command -v certbot &> /dev/null; then
            print_status "Attempting Let's Encrypt certificate..."
            if certbot --nginx -d "$DOMAIN_NAME" --non-interactive --agree-tos --email "admin@$DOMAIN_NAME" 2>/dev/null; then
                print_success "Let's Encrypt certificate obtained"
                SSL_CERT="/etc/letsencrypt/live/$DOMAIN_NAME/fullchain.pem"
                SSL_KEY="/etc/letsencrypt/live/$DOMAIN_NAME/privkey.pem"
                return
            else
                print_warning "Let's Encrypt failed, creating self-signed certificate"
            fi
        fi

        # Create self-signed certificate
        mkdir -p "$SSL_DIR"
        openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
            -keyout "$SSL_DIR/flexpbx.key" \
            -out "$SSL_DIR/flexpbx.crt" \
            -subj "/C=US/ST=State/L=City/O=FlexPBX/CN=$DOMAIN_NAME"

        SSL_CERT="$SSL_DIR/flexpbx.crt"
        SSL_KEY="$SSL_DIR/flexpbx.key"
        print_success "Self-signed certificate created"
    else
        print_warning "SSL certificate setup skipped for user installation"
        SSL_CERT=""
        SSL_KEY=""
    fi
}

# Function to create Nginx configuration
create_nginx_config() {
    print_status "Creating Nginx configuration..."

    # Determine configuration file name
    if [[ "$NGINX_STYLE" == "debian" ]]; then
        CONFIG_FILE="$NGINX_SITES_DIR/flexpbx"
    else
        CONFIG_FILE="$NGINX_SITES_DIR/flexpbx.conf"
    fi

    # Create main configuration
    cat > "$CONFIG_FILE" << EOF
# FlexPBX Nginx Configuration
# Version: $FLEXPBX_VERSION
# Domain: $DOMAIN_NAME
# Install Type: $INSTALL_TYPE

# HTTP Configuration (redirect to HTTPS or serve directly)
server {
    listen $CUSTOM_PORT;
    server_name $DOMAIN_NAME;

EOF

    # Add SSL redirect or serve HTTP
    if [[ -n "$SSL_CERT" ]] && [[ -n "$SSL_KEY" ]]; then
        cat >> "$CONFIG_FILE" << EOF
    # Redirect to HTTPS
    return 301 https://\$server_name:\$request_uri;
}

# HTTPS Configuration
server {
    listen $CUSTOM_SSL_PORT ssl http2;
    server_name $DOMAIN_NAME;

    # SSL Configuration
    ssl_certificate $SSL_CERT;
    ssl_certificate_key $SSL_KEY;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512:ECDHE-RSA-AES256-GCM-SHA384:DHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers off;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 10m;

EOF
    fi

    # Add common server configuration
    cat >> "$CONFIG_FILE" << EOF
    # Document root and index
    root $WEB_ROOT;
    index index.php index.html index.htm;

    # Security headers
    add_header X-Content-Type-Options nosniff;
    add_header X-Frame-Options DENY;
    add_header X-XSS-Protection "1; mode=block";
    add_header Referrer-Policy "strict-origin-when-cross-origin";
EOF

    # Add SSL-specific headers if SSL is enabled
    if [[ -n "$SSL_CERT" ]]; then
        cat >> "$CONFIG_FILE" << EOF
    add_header Strict-Transport-Security "max-age=63072000; includeSubDomains; preload";
EOF
    fi

    cat >> "$CONFIG_FILE" << EOF

    # Logging
    access_log /var/log/nginx/flexpbx_access.log;
    error_log /var/log/nginx/flexpbx_error.log;

    # Handle PHP files
    location ~ \.php$ {
        try_files \$uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
EOF

    # Add FastCGI configuration based on socket type
    if [[ "$PHP_FPM_SOCKET" == *":"* ]]; then
        cat >> "$CONFIG_FILE" << EOF
        fastcgi_pass $PHP_FPM_SOCKET;
EOF
    else
        cat >> "$CONFIG_FILE" << EOF
        fastcgi_pass unix:$PHP_FPM_SOCKET;
EOF
    fi

    cat >> "$CONFIG_FILE" << EOF
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    # Proxy API requests to Node.js backend
    location /api/ {
        proxy_pass http://127.0.0.1:$NODE_PORT;
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_cache_bypass \$http_upgrade;
        proxy_read_timeout 300;
        proxy_connect_timeout 300;
        proxy_send_timeout 300;
    }

    # WebSocket support for real-time features
    location /ws/ {
        proxy_pass http://127.0.0.1:$NODE_PORT;
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_read_timeout 86400;
    }

    # Static file caching
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        add_header Vary Accept-Encoding;
    }

    # Deny access to sensitive files
    location ~ /\. {
        deny all;
    }

    location ~ ~$ {
        deny all;
    }

    location ~ \.(log|sql|conf|config)$ {
        deny all;
    }

    # Handle client-side routing (SPA)
    location / {
        try_files \$uri \$uri/ /index.html;
    }

    # Specific locations for FlexPBX
    location /admin/ {
        try_files \$uri \$uri/ /admin/index.php?args;
    }

    location /phone/ {
        try_files \$uri \$uri/ /phone/index.html;
    }
}
EOF

    print_success "Nginx configuration created: $CONFIG_FILE"
}

# Function to create user-specific Nginx configuration
create_user_nginx_config() {
    print_status "Creating user-specific Nginx configuration..."

    USER_CONFIG_DIR="$HOME/.nginx"
    mkdir -p "$USER_CONFIG_DIR"

    CONFIG_FILE="$USER_CONFIG_DIR/flexpbx.conf"

    cat > "$CONFIG_FILE" << EOF
# FlexPBX User Nginx Configuration
# Version: $FLEXPBX_VERSION
# User: $USER_NAME
# Ports: $CUSTOM_PORT (HTTP), $CUSTOM_SSL_PORT (HTTPS)

server {
    listen $CUSTOM_PORT;
    server_name $DOMAIN_NAME localhost;

    root $WEB_ROOT;
    index index.php index.html;

    # Security headers
    add_header X-Content-Type-Options nosniff;
    add_header X-Frame-Options DENY;
    add_header X-XSS-Protection "1; mode=block";

    # Logging
    access_log $HOME/logs/nginx_access.log;
    error_log $HOME/logs/nginx_error.log;

    # PHP handling (if available)
    location ~ \.php$ {
        try_files \$uri =404;
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    # API proxy to Node.js
    location /api/ {
        proxy_pass http://127.0.0.1:$NODE_PORT;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
    }

    # Static files
    location / {
        try_files \$uri \$uri/ /index.html;
    }
}
EOF

    # Create startup script for user Nginx
    cat > "$USER_CONFIG_DIR/start-nginx.sh" << EOF
#!/bin/bash
# FlexPBX User Nginx Startup Script

USER_NGINX_CONFIG="$CONFIG_FILE"
NGINX_PID_FILE="$HOME/.nginx/nginx.pid"

# Check if nginx is available
if ! command -v nginx &> /dev/null; then
    echo "Error: Nginx not found"
    exit 1
fi

# Start nginx with user config
echo "Starting user Nginx for FlexPBX..."
nginx -c "\$USER_NGINX_CONFIG" -p "$HOME/.nginx/"

echo "Nginx started with PID file: \$NGINX_PID_FILE"
echo "Access FlexPBX at: http://localhost:$CUSTOM_PORT"
EOF

    chmod +x "$USER_CONFIG_DIR/start-nginx.sh"

    print_success "User Nginx configuration created: $CONFIG_FILE"
    print_status "Start with: $USER_CONFIG_DIR/start-nginx.sh"
}

# Function to enable site (Debian-style)
enable_site() {
    if [[ "$NGINX_STYLE" == "debian" ]] && [[ "$INSTALL_TYPE" == "system" ]]; then
        print_status "Enabling FlexPBX site..."

        ln -sf "$NGINX_SITES_DIR/flexpbx" "$NGINX_ENABLED_DIR/flexpbx"
        print_success "Site enabled"
    fi
}

# Function to test and reload Nginx
test_and_reload_nginx() {
    print_status "Testing Nginx configuration..."

    if nginx -t; then
        print_success "Nginx configuration test passed"

        if [[ "$INSTALL_TYPE" == "system" ]]; then
            print_status "Reloading Nginx..."
            systemctl reload nginx
            print_success "Nginx reloaded"
        else
            print_status "User installation - manual Nginx restart required"
        fi
    else
        print_error "Nginx configuration test failed"
        exit 1
    fi
}

# Function to configure firewall
configure_firewall() {
    if [[ "$INSTALL_TYPE" == "system" ]]; then
        print_status "Configuring firewall..."

        # Configure firewalld (RHEL/CentOS/AlmaLinux)
        if command -v firewall-cmd &> /dev/null; then
            if [[ "$CUSTOM_PORT" != "80" ]]; then
                firewall-cmd --permanent --add-port=$CUSTOM_PORT/tcp
            else
                firewall-cmd --permanent --add-service=http
            fi

            if [[ "$CUSTOM_SSL_PORT" != "443" ]]; then
                firewall-cmd --permanent --add-port=$CUSTOM_SSL_PORT/tcp
            else
                firewall-cmd --permanent --add-service=https
            fi

            firewall-cmd --reload
            print_success "Firewall configured (firewalld)"

        # Configure ufw (Ubuntu/Debian)
        elif command -v ufw &> /dev/null; then
            ufw allow $CUSTOM_PORT/tcp
            ufw allow $CUSTOM_SSL_PORT/tcp
            print_success "Firewall configured (ufw)"

        else
            print_warning "No firewall management tool found"
        fi
    else
        print_warning "Firewall configuration skipped for user installation"
    fi
}

# Function to display summary
display_summary() {
    print_success "Nginx configuration for FlexPBX completed!"
    echo
    echo "=== Configuration Summary ==="
    echo "Domain: $DOMAIN_NAME"
    echo "Web Root: $WEB_ROOT"
    echo "HTTP Port: $CUSTOM_PORT"
    echo "HTTPS Port: $CUSTOM_SSL_PORT"
    echo "Node.js Port: $NODE_PORT"
    echo "Install Type: $INSTALL_TYPE"
    echo "Nginx Style: $NGINX_STYLE"
    echo
    echo "=== Access URLs ==="
    echo "HTTP: http://$DOMAIN_NAME:$CUSTOM_PORT"
    if [[ -n "$SSL_CERT" ]]; then
        echo "HTTPS: https://$DOMAIN_NAME:$CUSTOM_SSL_PORT"
    fi
    echo "API: http://$DOMAIN_NAME:$CUSTOM_PORT/api/"
    echo "Admin: http://$DOMAIN_NAME:$CUSTOM_PORT/admin/"
    echo "Phone: http://$DOMAIN_NAME:$CUSTOM_PORT/phone/"
    echo
    echo "=== Configuration Files ==="
    if [[ "$INSTALL_TYPE" == "system" ]]; then
        echo "Nginx Config: $NGINX_SITES_DIR/flexpbx"
        echo "SSL Certificate: $SSL_CERT"
        echo "SSL Key: $SSL_KEY"
    else
        echo "User Config: $HOME/.nginx/flexpbx.conf"
        echo "Start Script: $HOME/.nginx/start-nginx.sh"
    fi
    echo
    echo "=== Next Steps ==="
    echo "1. Ensure FlexPBX Node.js backend is running on port $NODE_PORT"
    echo "2. Test the configuration by visiting the URLs above"
    echo "3. Check Nginx logs if there are any issues"
    echo
    if [[ "$INSTALL_TYPE" == "user" ]]; then
        echo "=== User Installation Notes ==="
        echo "- Run the start script to launch user Nginx"
        echo "- Ensure no port conflicts with system services"
        echo "- PHP-FPM may need separate configuration"
    fi
}

# Main function
main() {
    echo "========================================"
    echo "FlexPBX Nginx Configuration v$FLEXPBX_VERSION"
    echo "========================================"
    echo

    # Parse command line arguments
    while [[ $# -gt 0 ]]; do
        case $1 in
            --domain=*)
                DOMAIN_NAME="${1#*=}"
                shift
                ;;
            --webroot=*)
                WEB_ROOT="${1#*=}"
                shift
                ;;
            --user=*)
                USER_NAME="${1#*=}"
                shift
                ;;
            --port=*)
                CUSTOM_PORT="${1#*=}"
                shift
                ;;
            --ssl-port=*)
                CUSTOM_SSL_PORT="${1#*=}"
                shift
                ;;
            --node-port=*)
                NODE_PORT="${1#*=}"
                shift
                ;;
            --user-install)
                INSTALL_TYPE="user"
                USER_NAME="${USER_NAME:-$(whoami)}"
                shift
                ;;
            --shared-hosting)
                INSTALL_TYPE="shared"
                USER_NAME="${USER_NAME:-$(whoami)}"
                shift
                ;;
            --help)
                show_usage
                exit 0
                ;;
            *)
                print_error "Unknown option: $1"
                show_usage
                exit 1
                ;;
        esac
    done

    # Validate required parameters
    if [[ -z "$DOMAIN_NAME" ]]; then
        print_error "Domain name is required"
        show_usage
        exit 1
    fi

    if [[ -z "$WEB_ROOT" ]]; then
        print_error "Web root directory is required"
        show_usage
        exit 1
    fi

    # Set default user name
    USER_NAME="${USER_NAME:-flexpbxuser}"

    # Run configuration steps
    check_privileges
    detect_nginx
    install_php_fpm

    if [[ "$INSTALL_TYPE" == "system" ]]; then
        create_ssl_certificate
        create_nginx_config
        enable_site
        test_and_reload_nginx
        configure_firewall
    else
        create_user_nginx_config
    fi

    display_summary
}

# Run main function
main "$@"