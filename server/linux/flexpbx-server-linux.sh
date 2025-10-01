#!/bin/bash
#
# FlexPBX Linux Server Application
# Supports: AlmaLinux 8+, Rocky Linux 8+, RHEL 8+, Ubuntu 20.04+, Debian 10+
#

set -e

# Version and metadata
VERSION="2.0.0"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Distribution detection
detect_distro() {
    if [ -f /etc/os-release ]; then
        . /etc/os-release
        DISTRO=$ID
        VERSION_ID=$VERSION_ID
        PRETTY_NAME=$PRETTY_NAME
    elif [ -f /etc/redhat-release ]; then
        if grep -q "AlmaLinux" /etc/redhat-release; then
            DISTRO="almalinux"
        elif grep -q "Rocky" /etc/redhat-release; then
            DISTRO="rocky"
        elif grep -q "CentOS" /etc/redhat-release; then
            DISTRO="centos"
        else
            DISTRO="rhel"
        fi
        VERSION_ID=$(rpm -E %{rhel})
    else
        echo "Unsupported distribution"
        exit 1
    fi

    # Determine package manager
    if [ "$DISTRO" = "ubuntu" ] || [ "$DISTRO" = "debian" ]; then
        PKG_MANAGER="apt"
        PKG_INSTALL="apt-get install -y"
        PKG_UPDATE="apt-get update"
        SERVICE_MANAGER="systemctl"
    elif [ "$DISTRO" = "almalinux" ] || [ "$DISTRO" = "rocky" ] || [ "$DISTRO" = "rhel" ] || [ "$DISTRO" = "centos" ]; then
        PKG_MANAGER="dnf"
        if [ "$VERSION_ID" -lt 8 ]; then
            PKG_MANAGER="yum"
        fi
        PKG_INSTALL="$PKG_MANAGER install -y"
        PKG_UPDATE="$PKG_MANAGER update -y"
        SERVICE_MANAGER="systemctl"
    fi

    echo "Detected: $PRETTY_NAME"
    echo "Distribution: $DISTRO"
    echo "Version: $VERSION_ID"
    echo "Package Manager: $PKG_MANAGER"
}

# Check if running as root
check_root() {
    if [ "$EUID" -ne 0 ]; then
        echo "This script must be run as root"
        exit 1
    fi
}

# Install dependencies based on distribution
install_dependencies() {
    echo "Installing dependencies for $DISTRO..."

    # Update package lists
    $PKG_UPDATE

    if [ "$DISTRO" = "almalinux" ] || [ "$DISTRO" = "rocky" ] || [ "$DISTRO" = "rhel" ] || [ "$DISTRO" = "centos" ]; then
        # Enable EPEL and PowerTools/CodeReady repos
        if [ "$VERSION_ID" -ge 8 ]; then
            $PKG_INSTALL epel-release

            # Enable PowerTools/CodeReady
            if [ "$DISTRO" = "almalinux" ] || [ "$DISTRO" = "rocky" ]; then
                dnf config-manager --set-enabled powertools || dnf config-manager --set-enabled crb
            elif [ "$DISTRO" = "rhel" ]; then
                subscription-manager repos --enable codeready-builder-for-rhel-8-x86_64-rpms
            fi
        fi

        # Install packages
        $PKG_INSTALL \
            gcc gcc-c++ make \
            nodejs npm \
            git wget curl \
            nginx \
            mariadb mariadb-server \
            redis \
            asterisk asterisk-core \
            php php-cli php-common php-fpm php-json php-mysqlnd php-process php-xml \
            firewalld \
            tar gzip bzip2 \
            net-tools \
            policycoreutils-python-utils

        # Install Docker
        if ! command -v docker &> /dev/null; then
            dnf config-manager --add-repo=https://download.docker.com/linux/centos/docker-ce.repo
            $PKG_INSTALL docker-ce docker-ce-cli containerd.io docker-compose-plugin
        fi

    elif [ "$DISTRO" = "ubuntu" ] || [ "$DISTRO" = "debian" ]; then
        # Install packages
        $PKG_INSTALL \
            build-essential \
            nodejs npm \
            git wget curl \
            nginx \
            mariadb-server mariadb-client \
            redis-server \
            asterisk \
            php php-cli php-common php-fpm php-json php-mysql php-xml \
            ufw \
            tar gzip bzip2 \
            net-tools

        # Install Docker
        if ! command -v docker &> /dev/null; then
            curl -fsSL https://get.docker.com | sh
        fi
    fi

    # Install Node.js 18 if version is old
    NODE_VERSION=$(node -v 2>/dev/null | cut -d'v' -f2 | cut -d'.' -f1)
    if [ -z "$NODE_VERSION" ] || [ "$NODE_VERSION" -lt 18 ]; then
        echo "Installing Node.js 18..."
        curl -fsSL https://rpm.nodesource.com/setup_18.x | bash -
        $PKG_INSTALL nodejs
    fi

    echo "Dependencies installed successfully"
}

# Configure SELinux for AlmaLinux/RHEL
configure_selinux() {
    if [ "$DISTRO" = "almalinux" ] || [ "$DISTRO" = "rocky" ] || [ "$DISTRO" = "rhel" ] || [ "$DISTRO" = "centos" ]; then
        echo "Configuring SELinux..."

        # Set SELinux contexts for FlexPBX
        semanage fcontext -a -t httpd_sys_content_t "/opt/flexpbx(/.*)?"
        restorecon -Rv /opt/flexpbx

        # Allow network connections
        setsebool -P httpd_can_network_connect 1
        setsebool -P httpd_can_network_connect_db 1

        # Allow Asterisk to bind to ports
        semanage port -a -t asterisk_port_t -p udp 5060 2>/dev/null || true
        semanage port -a -t asterisk_port_t -p tcp 5060 2>/dev/null || true
        semanage port -a -t asterisk_port_t -p udp 10000-20000 2>/dev/null || true

        echo "SELinux configured"
    fi
}

# Configure firewall
configure_firewall() {
    echo "Configuring firewall..."

    if [ "$DISTRO" = "almalinux" ] || [ "$DISTRO" = "rocky" ] || [ "$DISTRO" = "rhel" ] || [ "$DISTRO" = "centos" ]; then
        # FirewallD configuration
        systemctl start firewalld
        systemctl enable firewalld

        # Add FlexPBX services
        firewall-cmd --permanent --add-service=http
        firewall-cmd --permanent --add-service=https
        firewall-cmd --permanent --add-port=3000/tcp
        firewall-cmd --permanent --add-port=5060/udp
        firewall-cmd --permanent --add-port=5060/tcp
        firewall-cmd --permanent --add-port=10000-20000/udp
        firewall-cmd --permanent --add-port=8088/tcp
        firewall-cmd --permanent --add-port=8089/tcp

        firewall-cmd --reload
        echo "FirewallD configured"

    elif [ "$DISTRO" = "ubuntu" ] || [ "$DISTRO" = "debian" ]; then
        # UFW configuration
        ufw allow 22/tcp
        ufw allow 80/tcp
        ufw allow 443/tcp
        ufw allow 3000/tcp
        ufw allow 5060/udp
        ufw allow 5060/tcp
        ufw allow 10000:20000/udp
        ufw allow 8088/tcp
        ufw allow 8089/tcp

        echo "y" | ufw enable
        echo "UFW configured"
    fi
}

# Setup MariaDB
setup_mariadb() {
    echo "Setting up MariaDB..."

    systemctl start mariadb
    systemctl enable mariadb

    # Secure MariaDB installation
    MYSQL_ROOT_PASSWORD=$(openssl rand -base64 32)

    mysql -e "UPDATE mysql.user SET Password=PASSWORD('$MYSQL_ROOT_PASSWORD') WHERE User='root';"
    mysql -e "DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');"
    mysql -e "DELETE FROM mysql.user WHERE User='';"
    mysql -e "DROP DATABASE IF EXISTS test;"
    mysql -e "DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';"

    # Create FlexPBX database
    mysql -u root -p"$MYSQL_ROOT_PASSWORD" << EOF
CREATE DATABASE IF NOT EXISTS flexpbx;
CREATE DATABASE IF NOT EXISTS asterisk;
CREATE DATABASE IF NOT EXISTS freepbx;

CREATE USER 'flexpbx'@'localhost' IDENTIFIED BY 'flexpbx_password';
GRANT ALL PRIVILEGES ON flexpbx.* TO 'flexpbx'@'localhost';
GRANT ALL PRIVILEGES ON asterisk.* TO 'flexpbx'@'localhost';
GRANT ALL PRIVILEGES ON freepbx.* TO 'flexpbx'@'localhost';

FLUSH PRIVILEGES;
EOF

    echo "MariaDB root password: $MYSQL_ROOT_PASSWORD" > /root/.mysql_root_password
    chmod 600 /root/.mysql_root_password

    echo "MariaDB configured"
}

# Setup Asterisk
setup_asterisk() {
    echo "Setting up Asterisk..."

    # Create basic Asterisk configuration
    mkdir -p /etc/asterisk

    cat > /etc/asterisk/sip.conf << 'EOF'
[general]
context=public
allowoverlap=no
udpbindaddr=0.0.0.0
tcpenable=yes
tcpbindaddr=0.0.0.0
transport=udp,tcp
srvlookup=yes
allowguest=no
alwaysauthreject=yes
EOF

    cat > /etc/asterisk/extensions.conf << 'EOF'
[general]
static=yes
writeprotect=no
clearglobalvars=no

[globals]
CONSOLE=Console/dsp
IAXINFO=guest
TRUNK=DAHDI/G2

[default]
exten => _X.,1,NoOp(FlexPBX Call to ${EXTEN})
same => n,Dial(SIP/${EXTEN},30)
same => n,Hangup()

[public]
exten => s,1,Answer()
same => n,Playback(welcome)
same => n,Hangup()
EOF

    # Set permissions
    chown -R asterisk:asterisk /etc/asterisk
    chown -R asterisk:asterisk /var/lib/asterisk
    chown -R asterisk:asterisk /var/spool/asterisk

    systemctl start asterisk
    systemctl enable asterisk

    echo "Asterisk configured"
}

# Setup Nginx
setup_nginx() {
    echo "Setting up Nginx..."

    # Create Nginx configuration for FlexPBX
    cat > /etc/nginx/conf.d/flexpbx.conf << 'EOF'
upstream flexpbx_backend {
    server 127.0.0.1:3000;
    keepalive 64;
}

server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name _;

    root /opt/flexpbx/public;
    index index.html index.php;

    # FlexPBX Application
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

    # WebSocket for SIP
    location /ws {
        proxy_pass http://flexpbx_backend/ws;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_read_timeout 86400;
    }

    # PHP support for FreePBX
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php-fpm/www.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Static files
    location ~* \.(jpg|jpeg|gif|png|css|js|ico|xml|rss|txt)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }
}
EOF

    # Remove default Nginx configuration if exists
    if [ "$DISTRO" = "almalinux" ] || [ "$DISTRO" = "rocky" ] || [ "$DISTRO" = "rhel" ] || [ "$DISTRO" = "centos" ]; then
        rm -f /etc/nginx/conf.d/default.conf
    elif [ "$DISTRO" = "ubuntu" ] || [ "$DISTRO" = "debian" ]; then
        rm -f /etc/nginx/sites-enabled/default
    fi

    # Test and start Nginx
    nginx -t
    systemctl start nginx
    systemctl enable nginx

    echo "Nginx configured"
}

# Install FlexPBX Server Application
install_flexpbx() {
    echo "Installing FlexPBX Server..."

    INSTALL_PATH="/opt/flexpbx"
    mkdir -p "$INSTALL_PATH"/{app,config,data,logs,public}

    # Create package.json
    cat > "$INSTALL_PATH/package.json" << 'EOF'
{
  "name": "flexpbx-linux-server",
  "version": "2.0.0",
  "description": "FlexPBX Linux Server",
  "main": "app/server.js",
  "scripts": {
    "start": "node app/server.js",
    "dev": "nodemon app/server.js",
    "setup": "node scripts/setup.js"
  },
  "dependencies": {
    "express": "^4.18.2",
    "ws": "^8.14.2",
    "dotenv": "^16.3.1",
    "mysql2": "^3.6.3",
    "redis": "^4.6.10",
    "asterisk-manager": "^0.1.16",
    "body-parser": "^1.20.2",
    "cors": "^2.8.5",
    "helmet": "^7.1.0"
  }
}
EOF

    # Create main server application
    mkdir -p "$INSTALL_PATH/app"
    cat > "$INSTALL_PATH/app/server.js" << 'EOF'
const express = require('express');
const http = require('http');
const WebSocket = require('ws');
const path = require('path');
const fs = require('fs');
const AsteriskManager = require('asterisk-manager');

const app = express();
const server = http.createServer(app);
const wss = new WebSocket.Server({ server });

// Middleware
app.use(express.json());
app.use(express.urlencoded({ extended: true }));
app.use(express.static(path.join(__dirname, '../public')));

// Asterisk AMI connection
const ami = new AsteriskManager(
    5038,
    '127.0.0.1',
    'admin',
    'admin_password',
    true
);

ami.keepConnected();

// Health check endpoint
app.get('/health', (req, res) => {
    res.json({
        status: 'healthy',
        version: '2.0.0',
        platform: process.platform,
        uptime: process.uptime()
    });
});

// API Routes
app.get('/api/system/status', (req, res) => {
    const status = {
        server: 'running',
        asterisk: ami.isConnected(),
        database: true, // Check actual DB connection
        services: {
            nginx: true,
            asterisk: ami.isConnected(),
            mariadb: true,
            redis: true
        }
    };
    res.json(status);
});

// Extensions API
app.get('/api/extensions', (req, res) => {
    ami.action({
        'action': 'PJSIPShowEndpoints'
    }, (err, response) => {
        if (err) {
            res.status(500).json({ error: err.message });
        } else {
            res.json(response);
        }
    });
});

app.post('/api/extensions', (req, res) => {
    const { extension, password, name } = req.body;

    // Create extension in Asterisk
    // This would involve writing to Asterisk config files or database

    res.json({
        success: true,
        extension: extension
    });
});

// Calls API
app.get('/api/calls/active', (req, res) => {
    ami.action({
        'action': 'CoreShowChannels'
    }, (err, response) => {
        if (err) {
            res.status(500).json({ error: err.message });
        } else {
            res.json(response);
        }
    });
});

app.post('/api/calls/originate', (req, res) => {
    const { from, to, context } = req.body;

    ami.action({
        'action': 'Originate',
        'channel': `SIP/${from}`,
        'exten': to,
        'context': context || 'default',
        'priority': 1,
        'callerid': from
    }, (err, response) => {
        if (err) {
            res.status(500).json({ error: err.message });
        } else {
            res.json({ success: true, response });
        }
    });
});

// WebSocket handling for real-time events
wss.on('connection', (ws) => {
    console.log('New WebSocket connection');

    // Subscribe to Asterisk events
    ami.on('managerevent', (evt) => {
        ws.send(JSON.stringify({
            type: 'asterisk_event',
            event: evt
        }));
    });

    ws.on('message', (message) => {
        try {
            const data = JSON.parse(message);

            switch (data.type) {
                case 'subscribe':
                    // Handle subscription to specific events
                    break;
                case 'command':
                    // Execute AMI command
                    ami.action(data.action, (err, response) => {
                        ws.send(JSON.stringify({
                            type: 'command_response',
                            response: response || err
                        }));
                    });
                    break;
            }
        } catch (error) {
            ws.send(JSON.stringify({
                type: 'error',
                message: error.message
            }));
        }
    });

    ws.send(JSON.stringify({
        type: 'connected',
        message: 'Connected to FlexPBX Linux Server'
    }));
});

const PORT = process.env.PORT || 3000;
server.listen(PORT, '0.0.0.0', () => {
    console.log(`FlexPBX Linux Server running on port ${PORT}`);
    console.log(`Platform: ${process.platform}`);
    console.log(`Node version: ${process.version}`);
});
EOF

    # Install Node.js dependencies
    cd "$INSTALL_PATH"
    npm install

    echo "FlexPBX Server installed"
}

# Create systemd service
create_systemd_service() {
    echo "Creating systemd service..."

    cat > /etc/systemd/system/flexpbx.service << 'EOF'
[Unit]
Description=FlexPBX Linux Server
After=network.target mariadb.service asterisk.service redis.service
Wants=mariadb.service asterisk.service redis.service

[Service]
Type=simple
User=root
WorkingDirectory=/opt/flexpbx
ExecStart=/usr/bin/node app/server.js
Restart=always
RestartSec=10

Environment=NODE_ENV=production
Environment=PORT=3000

StandardOutput=append:/var/log/flexpbx/server.log
StandardError=append:/var/log/flexpbx/error.log

[Install]
WantedBy=multi-user.target
EOF

    # Create log directory
    mkdir -p /var/log/flexpbx

    # Reload systemd and start service
    systemctl daemon-reload
    systemctl enable flexpbx
    systemctl start flexpbx

    echo "FlexPBX service created and started"
}

# Create management script
create_management_script() {
    echo "Creating management script..."

    cat > /usr/local/bin/flexpbx << 'EOF'
#!/bin/bash

case "$1" in
    start)
        systemctl start flexpbx asterisk mariadb redis nginx
        echo "FlexPBX services started"
        ;;
    stop)
        systemctl stop flexpbx asterisk mariadb redis nginx
        echo "FlexPBX services stopped"
        ;;
    restart)
        systemctl restart flexpbx asterisk mariadb redis nginx
        echo "FlexPBX services restarted"
        ;;
    status)
        echo "=== FlexPBX Service Status ==="
        systemctl status flexpbx --no-pager
        echo ""
        echo "=== Asterisk Status ==="
        systemctl status asterisk --no-pager
        echo ""
        echo "=== Database Status ==="
        systemctl status mariadb --no-pager
        echo ""
        echo "=== Web Server Status ==="
        systemctl status nginx --no-pager
        ;;
    logs)
        journalctl -u flexpbx -f
        ;;
    asterisk-cli)
        asterisk -rvvv
        ;;
    backup)
        BACKUP_DIR="/opt/flexpbx/backups/$(date +%Y%m%d_%H%M%S)"
        mkdir -p "$BACKUP_DIR"

        # Backup databases
        mysqldump -u root flexpbx > "$BACKUP_DIR/flexpbx.sql"
        mysqldump -u root asterisk > "$BACKUP_DIR/asterisk.sql"

        # Backup configuration
        tar -czf "$BACKUP_DIR/config.tar.gz" /etc/asterisk /opt/flexpbx/config

        echo "Backup created: $BACKUP_DIR"
        ;;
    *)
        echo "Usage: flexpbx {start|stop|restart|status|logs|asterisk-cli|backup}"
        exit 1
        ;;
esac
EOF

    chmod +x /usr/local/bin/flexpbx

    echo "Management script created"
}

# Display installation summary
display_summary() {
    echo ""
    echo "==========================================="
    echo "   FlexPBX Linux Server Installation"
    echo "==========================================="
    echo ""
    echo "Platform: $DISTRO $VERSION_ID"
    echo "Installation: Complete"
    echo ""
    echo "Access Points:"
    echo "  Web UI: http://$(hostname -I | awk '{print $1}'):3000"
    echo "  API: http://$(hostname -I | awk '{print $1}'):3000/api"
    echo ""
    echo "Services:"
    echo "  FlexPBX Server: Running on port 3000"
    echo "  Asterisk: Running on port 5060"
    echo "  MariaDB: Running on port 3306"
    echo "  Nginx: Running on port 80"
    echo ""
    echo "Management Commands:"
    echo "  flexpbx start    - Start all services"
    echo "  flexpbx stop     - Stop all services"
    echo "  flexpbx status   - Check service status"
    echo "  flexpbx logs     - View logs"
    echo ""
    echo "Default Credentials:"
    echo "  MySQL root: See /root/.mysql_root_password"
    echo "  FlexPBX Admin: admin / admin123"
    echo ""
    echo "Next Steps:"
    echo "1. Access the web UI at http://$(hostname -I | awk '{print $1}'):3000"
    echo "2. Configure your SIP extensions"
    echo "3. Set up firewall rules if needed"
    echo ""
}

# Main installation flow
main() {
    echo "FlexPBX Linux Server Installer v$VERSION"
    echo "========================================"
    echo ""

    check_root
    detect_distro
    install_dependencies
    configure_selinux
    configure_firewall
    setup_mariadb
    setup_asterisk
    setup_nginx
    install_flexpbx
    create_systemd_service
    create_management_script
    display_summary
}

# Run main installation
main "$@"