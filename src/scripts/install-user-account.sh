#!/bin/bash

# FlexPBX User Account Installation Script
# Install FlexPBX in user's home directory without sudo privileges
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
APPS_DIR="$USER_HOME/apps/flexpbx"
WEB_DIR="$USER_HOME/public_html"
CONFIG_DIR="$APPS_DIR/config"
LOG_DIR="$APPS_DIR/logs"
BACKUP_DIR="$APPS_DIR/backup"

# Installation options
CUSTOM_PORT=3000
CUSTOM_SSL_PORT=8443
INSTALL_TYPE="user"

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

# Function to check prerequisites
check_prerequisites() {
    print_status "Checking prerequisites..."

    # Check if we're NOT running as root
    if [[ $EUID -eq 0 ]]; then
        print_error "This script should NOT be run as root. Run as your regular user account."
        exit 1
    fi

    # Check for Node.js
    if ! command -v node &> /dev/null; then
        print_warning "Node.js not found. Installing via NVM..."
        install_nodejs
    else
        NODE_VERSION=$(node --version)
        print_success "Node.js found: $NODE_VERSION"
    fi

    # Check for npm
    if ! command -v npm &> /dev/null; then
        print_error "npm not found. Please install Node.js first."
        exit 1
    fi

    print_success "Prerequisites check completed"
}

# Function to install Node.js via NVM
install_nodejs() {
    print_status "Installing Node.js via NVM..."

    # Download and install NVM
    curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.0/install.sh | bash

    # Source NVM
    export NVM_DIR="$HOME/.nvm"
    [ -s "$NVM_DIR/nvm.sh" ] && \. "$NVM_DIR/nvm.sh"
    [ -s "$NVM_DIR/bash_completion" ] && \. "$NVM_DIR/bash_completion"

    # Install Node.js 18 LTS
    nvm install 18
    nvm use 18
    nvm alias default 18

    print_success "Node.js 18 LTS installed via NVM"
}

# Function to create directory structure
create_directories() {
    print_status "Creating directory structure..."

    # Create main directories
    mkdir -p "$APPS_DIR"
    mkdir -p "$WEB_DIR"
    mkdir -p "$CONFIG_DIR"
    mkdir -p "$LOG_DIR"
    mkdir -p "$BACKUP_DIR"

    # Create web subdirectories
    mkdir -p "$WEB_DIR/api"
    mkdir -p "$WEB_DIR/admin"
    mkdir -p "$WEB_DIR/phone"
    mkdir -p "$WEB_DIR/assets"

    print_success "Directory structure created"
}

# Function to deploy application files
deploy_files() {
    print_status "Deploying application files..."

    # Deploy web files
    if [ -d "public_html" ]; then
        cp -r public_html/* "$WEB_DIR/"
        print_success "Web files deployed to $WEB_DIR"
    fi

    # Deploy application files
    if [ -d "scripts" ]; then
        cp -r scripts/* "$APPS_DIR/"
    fi

    # Copy additional files
    if [ -f "package.json" ]; then
        cp package.json "$APPS_DIR/"
    fi

    if [ -f "README.md" ]; then
        cp README.md "$APPS_DIR/"
    fi

    print_success "Application files deployed"
}

# Function to install Node.js dependencies
install_dependencies() {
    print_status "Installing Node.js dependencies..."

    cd "$APPS_DIR"

    # Create package.json if it doesn't exist
    if [ ! -f "package.json" ]; then
        cat > package.json << 'EOF'
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
  }
}
EOF
    fi

    # Install dependencies
    npm install --production

    print_success "Dependencies installed"
}

# Function to configure environment
configure_environment() {
    print_status "Configuring environment..."

    cd "$APPS_DIR"

    # Create .env file
    cat > .env << EOF
# === FlexPBX User Installation Configuration ===

# Server Configuration
NODE_ENV=production
PORT=$CUSTOM_PORT
SSL_PORT=$CUSTOM_SSL_PORT
HOST=0.0.0.0

# Installation paths
USER_HOME=$USER_HOME
APPS_DIR=$APPS_DIR
WEB_DIR=$WEB_DIR
LOG_DIR=$LOG_DIR
BACKUP_DIR=$BACKUP_DIR

# Database Configuration (edit these)
DB_HOST=localhost
DB_PORT=3306
DB_NAME=flexpbx
DB_USER=flexpbx
DB_PASSWORD=change_this_password

# Web Configuration
API_BASE_URL=http://localhost:$CUSTOM_PORT
WEBSOCKET_URL=ws://localhost:$CUSTOM_PORT/ws

# Security Configuration (generate secure keys)
JWT_SECRET=$(openssl rand -base64 32)
ENCRYPTION_KEY=$(openssl rand -base64 32)
SESSION_SECRET=$(openssl rand -base64 32)

# Multi-Domain API Support
ALLOWED_DOMAINS=localhost,127.0.0.1
CORS_ORIGINS=http://localhost:$CUSTOM_PORT

# Email Configuration (optional)
SMTP_HOST=
SMTP_PORT=587
SMTP_USER=
SMTP_PASS=

# Logging
LOG_LEVEL=info
LOG_FILE=$LOG_DIR/flexpbx.log

# User installation specific
INSTALL_TYPE=user
USER_INSTALL=true
REQUIRE_SUDO=false
EOF

    chmod 600 .env

    print_success "Environment configured"
}

# Function to create PM2 configuration
create_pm2_config() {
    print_status "Creating PM2 configuration..."

    cd "$APPS_DIR"

    cat > ecosystem.config.js << 'EOF'
module.exports = {
  apps: [{
    name: 'flexpbx-user',
    script: './app.js',
    instances: 1,
    exec_mode: 'fork',
    env: {
      NODE_ENV: 'production'
    },
    error_file: './logs/err.log',
    out_file: './logs/out.log',
    log_file: './logs/combined.log',
    time: true,
    max_memory_restart: '512M',
    watch: false,
    ignore_watch: ['node_modules', 'logs', 'backup'],
    restart_delay: 5000
  }]
};
EOF

    print_success "PM2 configuration created"
}

# Function to create startup scripts
create_startup_scripts() {
    print_status "Creating startup scripts..."

    # Create start script
    cat > "$APPS_DIR/start-flexpbx.sh" << EOF
#!/bin/bash
cd "$APPS_DIR"
source ~/.bashrc

# Source NVM if available
export NVM_DIR="\$HOME/.nvm"
[ -s "\$NVM_DIR/nvm.sh" ] && \. "\$NVM_DIR/nvm.sh"

# Start FlexPBX
echo "Starting FlexPBX on port $CUSTOM_PORT..."
npm start
EOF

    # Create PM2 start script
    cat > "$APPS_DIR/start-pm2.sh" << EOF
#!/bin/bash
cd "$APPS_DIR"
source ~/.bashrc

# Source NVM if available
export NVM_DIR="\$HOME/.nvm"
[ -s "\$NVM_DIR/nvm.sh" ] && \. "\$NVM_DIR/nvm.sh"

# Install PM2 if not available
if ! command -v pm2 &> /dev/null; then
    echo "Installing PM2..."
    npm install -g pm2
fi

# Start with PM2
echo "Starting FlexPBX with PM2..."
pm2 start ecosystem.config.js
EOF

    # Create stop script
    cat > "$APPS_DIR/stop-flexpbx.sh" << EOF
#!/bin/bash
cd "$APPS_DIR"
source ~/.bashrc

# Source NVM if available
export NVM_DIR="\$HOME/.nvm"
[ -s "\$NVM_DIR/nvm.sh" ] && \. "\$NVM_DIR/nvm.sh"

if command -v pm2 &> /dev/null; then
    echo "Stopping FlexPBX PM2 processes..."
    pm2 stop ecosystem.config.js
    pm2 delete ecosystem.config.js
else
    echo "Stopping FlexPBX processes..."
    pkill -f "node.*app.js"
fi
EOF

    # Make scripts executable
    chmod +x "$APPS_DIR"/*.sh

    print_success "Startup scripts created"
}

# Function to create .htaccess for Apache user directories
create_htaccess() {
    print_status "Creating .htaccess for user directory..."

    cat > "$WEB_DIR/.htaccess" << 'EOF'
# FlexPBX User Directory Configuration

RewriteEngine On

# Handle API requests (proxy to Node.js if mod_proxy available)
RewriteCond %{HTTP:X-Requested-With} ^XMLHttpRequest$ [OR]
RewriteCond %{REQUEST_URI} ^/api/
RewriteRule ^api/(.*)$ http://localhost:3000/api/$1 [P,L]

# Handle client-side routing
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . index.html [L]

# Security headers
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options DENY
    Header always set X-XSS-Protection "1; mode=block"
</IfModule>

# Deny access to sensitive files
<FilesMatch "\.(env|log|config)$">
    Require all denied
</FilesMatch>

# Enable compression if available
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
</IfModule>
EOF

    print_success ".htaccess created for user directory"
}

# Function to test installation
test_installation() {
    print_status "Testing installation..."

    cd "$APPS_DIR"

    # Test Node.js syntax
    if node -c app.js 2>/dev/null; then
        print_success "Node.js application syntax is valid"
    else
        print_warning "Node.js application syntax check failed (app.js may not exist yet)"
    fi

    # Test npm dependencies
    if npm list --depth=0 >/dev/null 2>&1; then
        print_success "NPM dependencies are properly installed"
    else
        print_warning "Some NPM dependencies may be missing"
    fi

    print_success "Installation test completed"
}

# Function to display installation summary
display_summary() {
    print_success "FlexPBX User Installation completed!"
    echo
    echo "=== Installation Summary ==="
    echo "Version: $FLEXPBX_VERSION"
    echo "Install Type: User Account (no sudo)"
    echo "Install Directory: $APPS_DIR"
    echo "Web Directory: $WEB_DIR"
    echo "Port: $CUSTOM_PORT"
    echo "SSL Port: $CUSTOM_SSL_PORT"
    echo
    echo "=== Quick Start ==="
    echo "1. Configure database connection:"
    echo "   nano $APPS_DIR/.env"
    echo
    echo "2. Start FlexPBX:"
    echo "   cd $APPS_DIR"
    echo "   ./start-flexpbx.sh"
    echo "   # OR with PM2:"
    echo "   ./start-pm2.sh"
    echo
    echo "3. Access FlexPBX:"
    echo "   http://localhost:$CUSTOM_PORT"
    echo "   http://$(hostname -I | awk '{print $1}'):$CUSTOM_PORT"
    echo
    echo "=== User Directory Access ==="
    if [ -d "$WEB_DIR" ]; then
        USERNAME=$(whoami)
        echo "Apache UserDir: http://yourdomain.com/~$USERNAME/"
        echo "Direct files: http://yourdomain.com/~$USERNAME/index.html"
    fi
    echo
    echo "=== Management Commands ==="
    echo "Start: $APPS_DIR/start-flexpbx.sh"
    echo "Stop: $APPS_DIR/stop-flexpbx.sh"
    echo "Logs: tail -f $LOG_DIR/flexpbx.log"
    echo "Status: ps aux | grep node"
    echo
    echo "=== Next Steps ==="
    echo "1. Edit the database configuration in .env"
    echo "2. Create the FlexPBX database (see database-setup.sql)"
    echo "3. Start the FlexPBX server"
    echo "4. Access the web interface to complete setup"
    echo
    print_warning "Remember to configure your database connection before starting!"
}

# Main installation function
main() {
    echo "========================================"
    echo "FlexPBX User Account Installer v$FLEXPBX_VERSION"
    echo "========================================"
    echo

    # Parse command line arguments
    while [[ $# -gt 0 ]]; do
        case $1 in
            --port=*)
                CUSTOM_PORT="${1#*=}"
                shift
                ;;
            --ssl-port=*)
                CUSTOM_SSL_PORT="${1#*=}"
                shift
                ;;
            --apps-dir=*)
                APPS_DIR="${1#*=}"
                shift
                ;;
            --web-dir=*)
                WEB_DIR="${1#*=}"
                shift
                ;;
            --help)
                echo "Usage: $0 [OPTIONS]"
                echo "Options:"
                echo "  --port=PORT        Custom port (default: 3000)"
                echo "  --ssl-port=PORT    Custom SSL port (default: 8443)"
                echo "  --apps-dir=DIR     Custom apps directory"
                echo "  --web-dir=DIR      Custom web directory"
                echo "  --help             Show this help"
                exit 0
                ;;
            *)
                print_error "Unknown option: $1"
                exit 1
                ;;
        esac
    done

    # Update derived paths
    CONFIG_DIR="$APPS_DIR/config"
    LOG_DIR="$APPS_DIR/logs"
    BACKUP_DIR="$APPS_DIR/backup"

    # Run installation steps
    check_prerequisites
    create_directories
    deploy_files
    install_dependencies
    configure_environment
    create_pm2_config
    create_startup_scripts
    create_htaccess
    test_installation
    display_summary
}

# Run main function
main "$@"