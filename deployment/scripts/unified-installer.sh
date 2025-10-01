#!/bin/bash
#
# FlexPBX Unified Docker Installer
# Supports minimal and full installations
#

set -e

# Default values
INSTALL_TYPE="${1:-minimal}"
INSTALL_PATH="${2:-/opt/flexpbx}"
AUTO_START="${3:-true}"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Banner
echo -e "${BLUE}"
echo "╔══════════════════════════════════════════╗"
echo "║       FlexPBX Docker Installer          ║"
echo "║         Unified Installation             ║"
echo "╚══════════════════════════════════════════╝"
echo -e "${NC}"

# Check if running as root
check_root() {
    if [ "$EUID" -ne 0 ]; then
        echo -e "${RED}Error: This script must be run as root${NC}"
        echo "Please run: sudo $0 $@"
        exit 1
    fi
}

# Detect OS
detect_os() {
    if [ -f /etc/os-release ]; then
        . /etc/os-release
        OS=$ID
        VER=$VERSION_ID
    else
        echo -e "${RED}Cannot detect OS${NC}"
        exit 1
    fi
    echo -e "${GREEN}✓ Detected OS: $OS $VER${NC}"
}

# Install Docker
install_docker() {
    if command -v docker &> /dev/null; then
        echo -e "${GREEN}✓ Docker is already installed${NC}"
        docker --version
    else
        echo -e "${YELLOW}Installing Docker...${NC}"
        curl -fsSL https://get.docker.com | sh
        systemctl start docker
        systemctl enable docker
        echo -e "${GREEN}✓ Docker installed successfully${NC}"
    fi

    # Install Docker Compose
    if command -v docker-compose &> /dev/null; then
        echo -e "${GREEN}✓ Docker Compose is already installed${NC}"
    else
        echo -e "${YELLOW}Installing Docker Compose...${NC}"
        curl -L "https://github.com/docker/compose/releases/download/v2.20.0/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
        chmod +x /usr/local/bin/docker-compose
        echo -e "${GREEN}✓ Docker Compose installed successfully${NC}"
    fi
}

# Create directory structure
create_directories() {
    echo -e "${YELLOW}Creating directory structure...${NC}"

    mkdir -p "$INSTALL_PATH"/{server,data,logs,config,backups}
    mkdir -p "$INSTALL_PATH"/config/{nginx,asterisk,freepbx,mariadb,prometheus,grafana}
    mkdir -p "$INSTALL_PATH"/data/{www,sounds,voicemail,freepbx}
    mkdir -p "$INSTALL_PATH"/logs/{nginx,asterisk,freepbx}

    echo -e "${GREEN}✓ Directory structure created${NC}"
}

# Generate configuration files
generate_configs() {
    echo -e "${YELLOW}Generating configuration files...${NC}"

    # Generate .env file
    cat > "$INSTALL_PATH/.env" << EOF
# FlexPBX Environment Configuration
COMPOSE_PROJECT_NAME=flexpbx
INSTALL_TYPE=$INSTALL_TYPE
INSTALL_PATH=$INSTALL_PATH

# Database
DB_ROOT_PASSWORD=$(openssl rand -base64 32)
DB_PASSWORD=$(openssl rand -base64 24)
REDIS_PASSWORD=$(openssl rand -base64 24)

# Security
JWT_SECRET=$(openssl rand -base64 64)
SESSION_SECRET=$(openssl rand -base64 64)
ADMIN_PASSWORD=$(openssl rand -base64 16)

# Network
HTTP_PORT=3000
HTTPS_PORT=3443
SIP_PORT=5060
RTP_START=10000
RTP_END=20000

# Features
ENABLE_FREEPBX=$([ "$INSTALL_TYPE" = "full" ] && echo "true" || echo "false")
ENABLE_MONITORING=$([ "$INSTALL_TYPE" = "full" ] && echo "true" || echo "false")
ENABLE_BACKUP=$([ "$INSTALL_TYPE" = "full" ] && echo "true" || echo "false")
EOF

    # Generate Nginx config
    cat > "$INSTALL_PATH/config/nginx/default.conf" << 'EOF'
upstream flexpbx {
    server flexpbx-server:3000;
}

upstream freepbx {
    server freepbx:80;
}

server {
    listen 80;
    server_name _;

    location / {
        proxy_pass http://flexpbx;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_cache_bypass $http_upgrade;
    }

    location /admin {
        proxy_pass http://freepbx;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }

    location /ws {
        proxy_pass http://flexpbx/ws;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
    }
}
EOF

    # Generate Prometheus config
    if [ "$INSTALL_TYPE" = "full" ]; then
        cat > "$INSTALL_PATH/config/prometheus/prometheus.yml" << 'EOF'
global:
  scrape_interval: 15s
  evaluation_interval: 15s

scrape_configs:
  - job_name: 'flexpbx'
    static_configs:
      - targets: ['flexpbx-server:3000']

  - job_name: 'asterisk'
    static_configs:
      - targets: ['asterisk:8088']

  - job_name: 'node-exporter'
    static_configs:
      - targets: ['localhost:9100']
EOF
    fi

    echo -e "${GREEN}✓ Configuration files generated${NC}"
}

# Create server application
create_server_app() {
    echo -e "${YELLOW}Creating server application...${NC}"

    # Create package.json
    cat > "$INSTALL_PATH/server/package.json" << 'EOF'
{
  "name": "flexpbx-server",
  "version": "2.0.0",
  "description": "FlexPBX Server - Docker Edition",
  "main": "index.js",
  "scripts": {
    "start": "node index.js",
    "dev": "nodemon index.js"
  },
  "dependencies": {
    "express": "^4.18.2",
    "ws": "^8.14.2",
    "dotenv": "^16.3.1",
    "redis": "^4.6.10",
    "mysql2": "^3.6.3",
    "axios": "^1.6.2"
  }
}
EOF

    # Create main server file
    cat > "$INSTALL_PATH/server/index.js" << 'EOF'
const express = require('express');
const http = require('http');
const WebSocket = require('ws');
const path = require('path');
require('dotenv').config();

const app = express();
const server = http.createServer(app);
const wss = new WebSocket.Server({ server });

// Middleware
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// Health check
app.get('/health', (req, res) => {
    res.json({
        status: 'healthy',
        version: '2.0.0',
        mode: process.env.INSTALL_TYPE,
        timestamp: new Date().toISOString()
    });
});

// API routes
app.get('/api/status', (req, res) => {
    res.json({
        server: 'running',
        mode: process.env.INSTALL_TYPE,
        features: {
            freepbx: process.env.ENABLE_FREEPBX === 'true',
            monitoring: process.env.ENABLE_MONITORING === 'true',
            backup: process.env.ENABLE_BACKUP === 'true'
        }
    });
});

// WebSocket handling
wss.on('connection', (ws) => {
    console.log('New WebSocket connection');

    ws.on('message', (message) => {
        console.log('Received:', message.toString());
        ws.send(JSON.stringify({ echo: message.toString() }));
    });

    ws.send(JSON.stringify({
        type: 'connected',
        server: 'FlexPBX',
        version: '2.0.0'
    }));
});

const PORT = process.env.PORT || 3000;
server.listen(PORT, () => {
    console.log(`FlexPBX Server running on port ${PORT}`);
    console.log(`Mode: ${process.env.INSTALL_TYPE}`);
});
EOF

    # Create Dockerfile for full installation
    if [ "$INSTALL_TYPE" = "full" ]; then
        cat > "$INSTALL_PATH/server/Dockerfile" << 'EOF'
FROM node:18-alpine

RUN apk add --no-cache \
    asterisk \
    asterisk-sounds-en \
    asterisk-sounds-moh \
    nginx \
    supervisor \
    mysql-client \
    redis

WORKDIR /app

COPY package*.json ./
RUN npm ci --only=production

COPY . .

EXPOSE 3000 5060/udp 5060/tcp 8088 10000-20000/udp

CMD ["npm", "start"]
EOF
    fi

    # Install dependencies
    cd "$INSTALL_PATH/server"
    npm install

    echo -e "${GREEN}✓ Server application created${NC}"
}

# Copy Docker Compose file
setup_docker_compose() {
    echo -e "${YELLOW}Setting up Docker Compose...${NC}"

    if [ "$INSTALL_TYPE" = "minimal" ]; then
        cp /dev/stdin "$INSTALL_PATH/docker-compose.yml" << 'EOF'
version: '3.8'

services:
  flexpbx-server:
    image: node:18-alpine
    container_name: flexpbx-server
    restart: unless-stopped
    ports:
      - "3000:3000"
      - "5060:5060/udp"
      - "5060:5060/tcp"
    environment:
      - NODE_ENV=production
      - INSTALL_TYPE=minimal
    volumes:
      - ./server:/app
      - ./data:/app/data
      - ./logs:/app/logs
    working_dir: /app
    command: npm start
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
    else
        # Use the full docker-compose.yml for full installation
        cp "$(dirname "$0")/../docker/docker-compose-full.yml" "$INSTALL_PATH/docker-compose.yml"
    fi

    echo -e "${GREEN}✓ Docker Compose configured${NC}"
}

# Start services
start_services() {
    if [ "$AUTO_START" = "true" ]; then
        echo -e "${YELLOW}Starting FlexPBX services...${NC}"

        cd "$INSTALL_PATH"
        docker-compose up -d

        echo -e "${GREEN}✓ Services started${NC}"
        echo ""
        echo -e "${BLUE}Waiting for services to be ready...${NC}"
        sleep 10

        # Check service health
        docker-compose ps
    else
        echo -e "${YELLOW}Auto-start disabled. To start services manually:${NC}"
        echo "  cd $INSTALL_PATH"
        echo "  docker-compose up -d"
    fi
}

# Create management script
create_management_script() {
    echo -e "${YELLOW}Creating management script...${NC}"

    cat > "$INSTALL_PATH/flexpbx" << 'EOF'
#!/bin/bash

INSTALL_PATH="/opt/flexpbx"
cd "$INSTALL_PATH"

case "$1" in
    start)
        docker-compose up -d
        ;;
    stop)
        docker-compose down
        ;;
    restart)
        docker-compose restart
        ;;
    status)
        docker-compose ps
        ;;
    logs)
        docker-compose logs -f ${2:-}
        ;;
    shell)
        docker-compose exec ${2:-flexpbx-server} sh
        ;;
    upgrade)
        docker-compose pull
        docker-compose up -d
        ;;
    backup)
        tar -czf "backup-$(date +%Y%m%d-%H%M%S).tar.gz" data config
        ;;
    *)
        echo "Usage: $0 {start|stop|restart|status|logs|shell|upgrade|backup}"
        exit 1
        ;;
esac
EOF

    chmod +x "$INSTALL_PATH/flexpbx"
    ln -sf "$INSTALL_PATH/flexpbx" /usr/local/bin/flexpbx

    echo -e "${GREEN}✓ Management script created${NC}"
}

# Display summary
display_summary() {
    echo ""
    echo -e "${GREEN}═══════════════════════════════════════════${NC}"
    echo -e "${GREEN}    FlexPBX Installation Complete!${NC}"
    echo -e "${GREEN}═══════════════════════════════════════════${NC}"
    echo ""
    echo -e "${BLUE}Installation Details:${NC}"
    echo -e "  Type: ${YELLOW}$INSTALL_TYPE${NC}"
    echo -e "  Path: ${YELLOW}$INSTALL_PATH${NC}"
    echo ""
    echo -e "${BLUE}Access Points:${NC}"
    echo -e "  Web UI: ${YELLOW}http://localhost:3000${NC}"

    if [ "$INSTALL_TYPE" = "full" ]; then
        echo -e "  FreePBX Admin: ${YELLOW}http://localhost/admin${NC}"
        echo -e "  Mail Server: ${YELLOW}http://localhost:8025${NC}"
        echo -e "  Grafana: ${YELLOW}http://localhost:3001${NC}"
        echo -e "  Prometheus: ${YELLOW}http://localhost:9090${NC}"
    fi

    echo ""
    echo -e "${BLUE}Management Commands:${NC}"
    echo -e "  Start:   ${YELLOW}flexpbx start${NC}"
    echo -e "  Stop:    ${YELLOW}flexpbx stop${NC}"
    echo -e "  Status:  ${YELLOW}flexpbx status${NC}"
    echo -e "  Logs:    ${YELLOW}flexpbx logs${NC}"
    echo ""

    # Save credentials
    echo -e "${BLUE}Credentials saved to:${NC} ${YELLOW}$INSTALL_PATH/credentials.txt${NC}"

    cat > "$INSTALL_PATH/credentials.txt" << EOF
FlexPBX Credentials
==================
Generated: $(date)

Admin Password: $(grep ADMIN_PASSWORD "$INSTALL_PATH/.env" | cut -d= -f2)
DB Root Password: $(grep DB_ROOT_PASSWORD "$INSTALL_PATH/.env" | cut -d= -f2)
DB Password: $(grep DB_PASSWORD "$INSTALL_PATH/.env" | cut -d= -f2)
Redis Password: $(grep REDIS_PASSWORD "$INSTALL_PATH/.env" | cut -d= -f2)

Keep this file secure!
EOF

    chmod 600 "$INSTALL_PATH/credentials.txt"
}

# Main installation flow
main() {
    echo -e "${BLUE}Installation Type: $INSTALL_TYPE${NC}"
    echo -e "${BLUE}Installation Path: $INSTALL_PATH${NC}"
    echo ""

    check_root
    detect_os
    install_docker
    create_directories
    generate_configs
    create_server_app
    setup_docker_compose
    start_services
    create_management_script
    display_summary
}

# Handle script arguments
case "$1" in
    minimal|full)
        INSTALL_TYPE="$1"
        ;;
    --help|-h)
        echo "Usage: $0 [minimal|full] [install_path] [auto_start]"
        echo ""
        echo "Arguments:"
        echo "  minimal|full  - Installation type (default: minimal)"
        echo "  install_path  - Installation directory (default: /opt/flexpbx)"
        echo "  auto_start    - Start services after install (default: true)"
        echo ""
        echo "Examples:"
        echo "  $0 minimal"
        echo "  $0 full /opt/flexpbx true"
        exit 0
        ;;
    "")
        # Use defaults
        ;;
    *)
        echo -e "${RED}Invalid installation type: $1${NC}"
        echo "Use 'minimal' or 'full'"
        exit 1
        ;;
esac

# Run installation
main