#!/bin/bash
#
# FlexPBX Minimal Server Module Installer
# Supports: Nginx, Apache, Docker, FreePBX
#

set -e

# Configuration
INSTALL_DIR="${1:-/opt/flexpbx}"
WEB_SERVER="${2:-auto}"  # auto, nginx, apache, both
INSTALL_MODE="${3:-docker}"  # docker, native
ENABLE_FREEPBX="${4:-true}"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${GREEN}FlexPBX Server Module Installer${NC}"
echo "================================="

# Detect OS
detect_os() {
    if [ -f /etc/os-release ]; then
        . /etc/os-release
        OS=$ID
        VER=$VERSION_ID
    elif [ -f /etc/redhat-release ]; then
        OS="rhel"
    elif [ -f /etc/debian_version ]; then
        OS="debian"
    else
        OS=$(uname -s)
    fi
    echo -e "${GREEN}Detected OS: $OS${NC}"
}

# Detect web server
detect_webserver() {
    NGINX_INSTALLED=false
    APACHE_INSTALLED=false

    if command -v nginx &> /dev/null; then
        NGINX_INSTALLED=true
        echo -e "${GREEN}✓ Nginx detected${NC}"
    fi

    if command -v apache2 &> /dev/null || command -v httpd &> /dev/null; then
        APACHE_INSTALLED=true
        echo -e "${GREEN}✓ Apache detected${NC}"
    fi

    if [ "$WEB_SERVER" == "auto" ]; then
        if [ "$NGINX_INSTALLED" == true ]; then
            WEB_SERVER="nginx"
        elif [ "$APACHE_INSTALLED" == true ]; then
            WEB_SERVER="apache"
        else
            WEB_SERVER="nginx"  # Default to nginx
            echo -e "${YELLOW}No web server detected, will install Nginx${NC}"
        fi
    fi
}

# Install dependencies
install_dependencies() {
    echo -e "${GREEN}Installing dependencies...${NC}"

    if [ "$OS" == "ubuntu" ] || [ "$OS" == "debian" ]; then
        apt-get update
        apt-get install -y curl wget git sudo build-essential
    elif [ "$OS" == "centos" ] || [ "$OS" == "rhel" ] || [ "$OS" == "fedora" ]; then
        yum update -y
        yum install -y curl wget git sudo gcc make
    fi
}

# Install Docker if needed
install_docker() {
    if [ "$INSTALL_MODE" == "docker" ]; then
        if ! command -v docker &> /dev/null; then
            echo -e "${GREEN}Installing Docker...${NC}"
            curl -fsSL https://get.docker.com | sh
            systemctl start docker
            systemctl enable docker
        else
            echo -e "${GREEN}✓ Docker already installed${NC}"
        fi

        if ! command -v docker-compose &> /dev/null; then
            echo -e "${GREEN}Installing Docker Compose...${NC}"
            curl -L "https://github.com/docker/compose/releases/download/v2.20.0/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
            chmod +x /usr/local/bin/docker-compose
        else
            echo -e "${GREEN}✓ Docker Compose already installed${NC}"
        fi
    fi
}

# Install Nginx
install_nginx() {
    if [ "$WEB_SERVER" == "nginx" ] || [ "$WEB_SERVER" == "both" ]; then
        if ! command -v nginx &> /dev/null; then
            echo -e "${GREEN}Installing Nginx...${NC}"
            if [ "$OS" == "ubuntu" ] || [ "$OS" == "debian" ]; then
                apt-get install -y nginx
            else
                yum install -y nginx
            fi
        fi

        # Configure Nginx
        configure_nginx
    fi
}

# Install Apache
install_apache() {
    if [ "$WEB_SERVER" == "apache" ] || [ "$WEB_SERVER" == "both" ]; then
        if ! command -v apache2 &> /dev/null && ! command -v httpd &> /dev/null; then
            echo -e "${GREEN}Installing Apache...${NC}"
            if [ "$OS" == "ubuntu" ] || [ "$OS" == "debian" ]; then
                apt-get install -y apache2
                systemctl start apache2
                systemctl enable apache2
            else
                yum install -y httpd
                systemctl start httpd
                systemctl enable httpd
            fi
        fi

        # Configure Apache
        configure_apache
    fi
}

# Configure Nginx
configure_nginx() {
    echo -e "${GREEN}Configuring Nginx for FlexPBX...${NC}"

    cat > /etc/nginx/conf.d/flexpbx.conf << 'EOF'
# FlexPBX Nginx Configuration
upstream flexpbx_backend {
    server 127.0.0.1:3000;
    keepalive 64;
}

server {
    listen 80;
    server_name _;

    location / {
        proxy_pass http://flexpbx_backend;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_cache_bypass $http_upgrade;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # WebSocket support for SIP
    location /ws {
        proxy_pass http://flexpbx_backend/ws;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_read_timeout 86400;
    }

    # Static files
    location /static {
        alias /opt/flexpbx/public;
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    # FreePBX Admin Interface
    location /admin {
        alias /var/www/html/admin;
        index index.php index.html;

        location ~ \.php$ {
            fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
            fastcgi_index index.php;
            include fastcgi_params;
        }
    }
}
EOF

    nginx -t && nginx -s reload
}

# Configure Apache
configure_apache() {
    echo -e "${GREEN}Configuring Apache for FlexPBX...${NC}"

    if [ "$OS" == "ubuntu" ] || [ "$OS" == "debian" ]; then
        APACHE_CONF="/etc/apache2/sites-available/flexpbx.conf"
        a2enmod proxy proxy_http proxy_wstunnel rewrite
    else
        APACHE_CONF="/etc/httpd/conf.d/flexpbx.conf"
    fi

    cat > "$APACHE_CONF" << 'EOF'
<VirtualHost *:80>
    ServerName localhost
    DocumentRoot /opt/flexpbx/public

    ProxyPreserveHost On
    ProxyRequests Off

    # Main application
    ProxyPass / http://127.0.0.1:3000/
    ProxyPassReverse / http://127.0.0.1:3000/

    # WebSocket
    RewriteEngine On
    RewriteCond %{HTTP:Upgrade} websocket [NC]
    RewriteCond %{HTTP:Connection} upgrade [NC]
    RewriteRule ^/?(.*) "ws://127.0.0.1:3000/$1" [P,L]

    # FreePBX Admin
    Alias /admin /var/www/html/admin
    <Directory /var/www/html/admin>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/flexpbx_error.log
    CustomLog ${APACHE_LOG_DIR}/flexpbx_access.log combined
</VirtualHost>
EOF

    if [ "$OS" == "ubuntu" ] || [ "$OS" == "debian" ]; then
        a2ensite flexpbx
        systemctl reload apache2
    else
        systemctl reload httpd
    fi
}

# Install FreePBX components
install_freepbx() {
    if [ "$ENABLE_FREEPBX" == "true" ]; then
        echo -e "${GREEN}Installing FreePBX components...${NC}"

        # Install Asterisk
        if ! command -v asterisk &> /dev/null; then
            if [ "$OS" == "ubuntu" ] || [ "$OS" == "debian" ]; then
                apt-get install -y asterisk asterisk-config asterisk-modules
            else
                yum install -y asterisk asterisk-config
            fi
        fi

        # Install PHP and dependencies for FreePBX
        if [ "$OS" == "ubuntu" ] || [ "$OS" == "debian" ]; then
            apt-get install -y php7.4 php7.4-{mysql,cli,common,json,opcache,readline,bcmath,curl,fpm,gd,mbstring,xml,zip}
        else
            yum install -y php php-{mysql,cli,common,json,opcache,readline,bcmath,curl,fpm,gd,mbstring,xml,zip}
        fi

        # Download and install FreePBX (if not using Docker)
        if [ "$INSTALL_MODE" == "native" ]; then
            cd /usr/src
            wget http://mirror.freepbx.org/modules/packages/freepbx/freepbx-16.0-latest.tgz
            tar xfz freepbx-16.0-latest.tgz
            cd freepbx
            ./start_asterisk start
            ./install -n --webroot=/var/www/html
        fi
    fi
}

# Create minimal FlexPBX server module
create_server_module() {
    echo -e "${GREEN}Creating minimal FlexPBX server module...${NC}"

    mkdir -p "$INSTALL_DIR"/{app,config,data,logs,public,scripts}

    # Create main server file
    cat > "$INSTALL_DIR/app/server.js" << 'EOF'
const express = require('express');
const http = require('http');
const WebSocket = require('ws');
const path = require('path');

const app = express();
const server = http.createServer(app);
const wss = new WebSocket.Server({ server });

// Middleware
app.use(express.json());
app.use(express.static(path.join(__dirname, '../public')));

// Health check endpoint
app.get('/health', (req, res) => {
    res.json({
        status: 'healthy',
        version: '1.0.0',
        freepbx: process.env.ENABLE_FREEPBX === 'true',
        docker: process.env.INSTALL_MODE === 'docker'
    });
});

// API endpoints
app.use('/api', require('./routes/api'));

// WebSocket handling for SIP
wss.on('connection', (ws) => {
    console.log('New WebSocket connection');

    ws.on('message', (message) => {
        // Handle SIP messages
        console.log('Received:', message);
    });

    ws.on('close', () => {
        console.log('WebSocket connection closed');
    });
});

const PORT = process.env.PORT || 3000;
server.listen(PORT, () => {
    console.log(`FlexPBX Server running on port ${PORT}`);
});
EOF

    # Create package.json
    cat > "$INSTALL_DIR/package.json" << 'EOF'
{
  "name": "flexpbx-server",
  "version": "1.0.0",
  "description": "FlexPBX Minimal Server Module",
  "main": "app/server.js",
  "scripts": {
    "start": "node app/server.js",
    "dev": "nodemon app/server.js"
  },
  "dependencies": {
    "express": "^4.18.2",
    "ws": "^8.14.2",
    "dotenv": "^16.3.1",
    "sqlite3": "^5.1.6"
  },
  "devDependencies": {
    "nodemon": "^3.0.1"
  }
}
EOF

    # Create Docker setup if using Docker mode
    if [ "$INSTALL_MODE" == "docker" ]; then
        create_docker_setup
    fi

    # Install Node.js dependencies
    cd "$INSTALL_DIR"
    if command -v npm &> /dev/null; then
        npm install
    fi
}

# Create Docker setup
create_docker_setup() {
    echo -e "${GREEN}Creating Docker configuration...${NC}"

    # Create Dockerfile
    cat > "$INSTALL_DIR/Dockerfile" << 'EOF'
FROM node:18-alpine

WORKDIR /app

# Install system dependencies
RUN apk add --no-cache \
    asterisk \
    asterisk-sounds-en \
    asterisk-sounds-moh \
    nginx \
    supervisor

# Copy application files
COPY package*.json ./
RUN npm ci --only=production

COPY . .

# Expose ports
EXPOSE 3000 5060/udp 5060/tcp 8088 10000-20000/udp

# Start services
CMD ["npm", "start"]
EOF

    # Create docker-compose.yml
    cat > "$INSTALL_DIR/docker-compose.yml" << 'EOF'
version: '3.8'

services:
  flexpbx:
    build: .
    container_name: flexpbx-server
    restart: unless-stopped
    ports:
      - "3000:3000"
      - "5060:5060/udp"
      - "5060:5060/tcp"
      - "8088:8088"
      - "10000-20000:10000-20000/udp"
    environment:
      - NODE_ENV=production
      - ENABLE_FREEPBX=${ENABLE_FREEPBX:-true}
      - INSTALL_MODE=docker
    volumes:
      - ./data:/app/data
      - ./logs:/app/logs
      - ./config:/app/config
    networks:
      - flexpbx_network

  asterisk:
    image: andrius/asterisk:alpine-18
    container_name: flexpbx-asterisk
    restart: unless-stopped
    network_mode: host
    volumes:
      - ./config/asterisk:/etc/asterisk
      - ./data/sounds:/var/lib/asterisk/sounds
      - ./logs/asterisk:/var/log/asterisk

  freepbx:
    image: tiredofit/freepbx:latest
    container_name: flexpbx-freepbx
    restart: unless-stopped
    ports:
      - "80:80"
      - "443:443"
      - "5061:5061"
      - "5161:5161"
      - "8001:8001"
      - "8003:8003"
      - "8089:8089"
    volumes:
      - ./data/freepbx:/data
      - ./logs/freepbx:/var/log
      - ./config/freepbx:/etc/asterisk
    environment:
      - DB_EMBEDDED=TRUE
      - ENABLE_FAIL2BAN=TRUE
    networks:
      - flexpbx_network

  redis:
    image: redis:7-alpine
    container_name: flexpbx-redis
    restart: unless-stopped
    volumes:
      - redis_data:/data
    networks:
      - flexpbx_network

volumes:
  redis_data:

networks:
  flexpbx_network:
    driver: bridge
EOF
}

# Create systemd service
create_systemd_service() {
    if [ "$INSTALL_MODE" == "native" ]; then
        echo -e "${GREEN}Creating systemd service...${NC}"

        cat > /etc/systemd/system/flexpbx.service << EOF
[Unit]
Description=FlexPBX Server
After=network.target

[Service]
Type=simple
User=root
WorkingDirectory=$INSTALL_DIR
ExecStart=/usr/bin/node $INSTALL_DIR/app/server.js
Restart=always

[Install]
WantedBy=multi-user.target
EOF

        systemctl daemon-reload
        systemctl enable flexpbx
        systemctl start flexpbx
    else
        echo -e "${GREEN}Creating Docker systemd service...${NC}"

        cat > /etc/systemd/system/flexpbx-docker.service << EOF
[Unit]
Description=FlexPBX Docker Services
After=docker.service
Requires=docker.service

[Service]
Type=simple
WorkingDirectory=$INSTALL_DIR
ExecStart=/usr/local/bin/docker-compose up
ExecStop=/usr/local/bin/docker-compose down
Restart=always

[Install]
WantedBy=multi-user.target
EOF

        systemctl daemon-reload
        systemctl enable flexpbx-docker
    fi
}

# Create FreePBX integration script
create_freepbx_integration() {
    echo -e "${GREEN}Creating FreePBX integration...${NC}"

    cat > "$INSTALL_DIR/scripts/freepbx-integrate.sh" << 'EOF'
#!/bin/bash

# FreePBX Integration Script
FREEPBX_PATH="/var/www/html/admin"
ASTERISK_PATH="/etc/asterisk"

# Create custom context for FlexPBX
cat >> "$ASTERISK_PATH/extensions_custom.conf" << 'EOL'
[flexpbx-context]
; FlexPBX Custom Context
exten => _X.,1,NoOp(FlexPBX Call: ${EXTEN})
same => n,Dial(PJSIP/${EXTEN},30)
same => n,Voicemail(${EXTEN}@default,u)
same => n,Hangup()

[flexpbx-features]
; Custom features
exten => *97,1,VoiceMailMain(${CALLERID(num)}@default)
exten => *98,1,VoiceMailMain()
EOL

# Reload Asterisk
asterisk -rx "core reload"

echo "FreePBX integration complete"
EOF

    chmod +x "$INSTALL_DIR/scripts/freepbx-integrate.sh"
}

# Main installation flow
main() {
    echo -e "${GREEN}Starting FlexPBX installation...${NC}"
    echo "Install directory: $INSTALL_DIR"
    echo "Web server: $WEB_SERVER"
    echo "Install mode: $INSTALL_MODE"
    echo "FreePBX enabled: $ENABLE_FREEPBX"
    echo ""

    # Check if running as root
    if [ "$EUID" -ne 0 ]; then
        echo -e "${RED}Please run as root or with sudo${NC}"
        exit 1
    fi

    # Run installation steps
    detect_os
    detect_webserver
    install_dependencies
    install_docker
    install_nginx
    install_apache
    install_freepbx
    create_server_module
    create_systemd_service
    create_freepbx_integration

    echo ""
    echo -e "${GREEN}✓ Installation complete!${NC}"
    echo ""
    echo "FlexPBX is installed at: $INSTALL_DIR"
    echo ""
    echo "Next steps:"
    if [ "$INSTALL_MODE" == "docker" ]; then
        echo "  cd $INSTALL_DIR"
        echo "  docker-compose up -d"
    else
        echo "  systemctl start flexpbx"
        echo "  systemctl status flexpbx"
    fi
    echo ""
    echo "Access FlexPBX at: http://localhost:3000"
    if [ "$ENABLE_FREEPBX" == "true" ]; then
        echo "Access FreePBX Admin at: http://localhost/admin"
    fi
    echo ""
}

# Run main installation
main "$@"