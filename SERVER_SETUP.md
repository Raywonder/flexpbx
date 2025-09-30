# FlexPBX Server Setup Guide

## 📋 Overview

This guide provides multiple methods to deploy FlexPBX on your server infrastructure, including automated client-driven setup for remote server deployment.

## 🖥️ Server Requirements

### Minimum Requirements
- **CPU**: 2 cores (2.0 GHz)
- **RAM**: 4 GB
- **Storage**: 20 GB SSD
- **Network**: 1 Gbps connection
- **OS**: Ubuntu 20.04+, CentOS 8+, Debian 11+, or Docker-compatible system

### Recommended Requirements
- **CPU**: 4+ cores (2.5 GHz)
- **RAM**: 8+ GB
- **Storage**: 50+ GB SSD
- **Network**: 1 Gbps connection with public IP
- **Load Balancer**: For high availability setups

### Required Ports
```
80/tcp    - HTTP (Web Interface)
443/tcp   - HTTPS (Secure Web Interface)
3000/tcp  - FlexPBX Application
5060/udp  - SIP (UDP)
5060/tcp  - SIP (TCP)
5061/tcp  - SIP TLS
8088/tcp  - WebRTC WebSocket
8089/tcp  - WebRTC Secure WebSocket
10000-20000/udp - RTP Media
3306/tcp  - MySQL (if using external DB)
6379/tcp  - Redis (if using external cache)
22/tcp    - SSH (for management)
```

## 🚀 Quick Setup Methods

### Method 1: One-Command Installation (Recommended)

```bash
curl -sSL https://raw.githubusercontent.com/Raywonder/flexpbx/main/install.sh | sudo bash
```

### Method 2: Manual Installation

1. **Clone Repository**
```bash
git clone git@github.com:Raywonder/flexpbx.git
cd flexpbx
```

2. **Configure Environment**
```bash
cp .env.example .env
nano .env  # Edit configuration
```

3. **Start Services**
```bash
docker-compose up -d
```

### Method 3: Client-Driven Remote Setup

Use the built-in server setup wizard in the FlexPBX client application to deploy to remote servers via SSH, SFTP, FTP, or WebDAV.

## 🔧 Client-Driven Server Setup

### Prerequisites
- FlexPBX client application installed locally
- Server with SSH/SFTP/FTP/WebDAV access
- Server meets minimum requirements
- Administrative access to target server

### Supported Connection Methods

#### SSH/SFTP (Recommended)
```
Protocol: SSH/SFTP
Port: 22 (default)
Authentication: Password or SSH Key
Permissions: sudo access required
```

#### FTP/FTPS
```
Protocol: FTP or FTPS
Port: 21 (FTP) or 990 (FTPS)
Authentication: Username/Password
Permissions: Write access to web directory
```

#### WebDAV
```
Protocol: WebDAV/WebDAVS
Port: 80 (HTTP) or 443 (HTTPS)
Authentication: Username/Password
Permissions: Write access to target directory
```

### Setup Process

1. **Launch Client Setup Wizard**
   - Open FlexPBX client application
   - Navigate to: `Settings > Server Setup > Remote Deployment`
   - Select "Deploy to Remote Server"

2. **Choose Connection Method**
   - Select preferred protocol (SSH/SFTP recommended)
   - Enter server connection details
   - Test connection

3. **Configure Deployment**
   - Choose installation directory (default: `/opt/flexpbx`)
   - Select deployment type (Standalone, Central, Cluster)
   - Configure database settings
   - Set domain/IP configuration

4. **Execute Deployment**
   - Client uploads installation files
   - Executes setup scripts remotely
   - Configures services and dependencies
   - Performs initial system setup

5. **Complete Configuration**
   - Client connects to deployed server
   - Completes database initialization
   - Sets up default extensions
   - Configures accessibility features

## 📁 Directory Structure

### Standard Installation
```
/opt/flexpbx/
├── app/                    # Application files
│   ├── src/               # Source code
│   ├── public/            # Web assets
│   ├── config/            # Configuration files
│   └── scripts/           # Utility scripts
├── data/                  # Database files (SQLite)
├── logs/                  # Application logs
├── recordings/            # Call recordings
├── voicemail/            # Voicemail files
├── backups/              # System backups
├── ssl/                  # SSL certificates
└── docker-compose.yml    # Docker configuration
```

### Central Server Installation
```
/opt/flexpbx-central/
├── cluster/              # Cluster configuration
├── tenants/             # Multi-tenant data
├── load-balancer/       # HAProxy configuration
├── monitoring/          # Prometheus/Grafana
└── shared/             # Shared resources
```

## 🗄️ Database Configuration

### SQLite (Default)
- **File**: `/opt/flexpbx/data/flexpbx.sqlite`
- **Backup**: Automatic daily backups
- **Best for**: Single server, up to 100 users

### MySQL/MariaDB
```bash
# Install MySQL/MariaDB
sudo apt install mariadb-server

# Configure database
sudo mysql_secure_installation

# Create database and user
mysql -u root -p
CREATE DATABASE flexpbx;
CREATE USER 'flexpbx_admin'@'localhost' IDENTIFIED BY 'SecurePassword123!';
GRANT ALL PRIVILEGES ON flexpbx.* TO 'flexpbx_admin'@'localhost';
FLUSH PRIVILEGES;
```

### PostgreSQL
```bash
# Install PostgreSQL
sudo apt install postgresql postgresql-contrib

# Configure database
sudo -u postgres psql
CREATE DATABASE flexpbx;
CREATE USER flexpbx_admin WITH PASSWORD 'SecurePassword123!';
GRANT ALL PRIVILEGES ON DATABASE flexpbx TO flexpbx_admin;
```

## 🔐 Security Configuration

### SSL/TLS Setup

#### Let's Encrypt (Recommended)
```bash
# Install Certbot
sudo apt install certbot

# Generate certificate
sudo certbot certonly --standalone -d your-domain.com

# Update .env file
SSL_ENABLED=true
LETSENCRYPT_EMAIL=admin@your-domain.com
DOMAIN_NAME=your-domain.com
```

#### Self-Signed Certificate
```bash
# Generate self-signed certificate
openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
  -keyout /opt/flexpbx/ssl/private.key \
  -out /opt/flexpbx/ssl/certificate.crt
```

### Firewall Configuration

#### UFW (Ubuntu)
```bash
sudo ufw allow 22/tcp      # SSH
sudo ufw allow 80/tcp      # HTTP
sudo ufw allow 443/tcp     # HTTPS
sudo ufw allow 5060/udp    # SIP UDP
sudo ufw allow 5060/tcp    # SIP TCP
sudo ufw allow 5061/tcp    # SIP TLS
sudo ufw allow 8088:8089/tcp # WebRTC
sudo ufw allow 10000:20000/udp # RTP
sudo ufw enable
```

#### iptables
```bash
# Basic iptables rules
iptables -A INPUT -p tcp --dport 22 -j ACCEPT
iptables -A INPUT -p tcp --dport 80 -j ACCEPT
iptables -A INPUT -p tcp --dport 443 -j ACCEPT
iptables -A INPUT -p udp --dport 5060 -j ACCEPT
iptables -A INPUT -p tcp --dport 5060 -j ACCEPT
iptables -A INPUT -p tcp --dport 5061 -j ACCEPT
iptables -A INPUT -p tcp --dport 8088:8089 -j ACCEPT
iptables -A INPUT -p udp --dport 10000:20000 -j ACCEPT
```

## 📊 Monitoring Setup

### Prometheus + Grafana
```bash
# Enable monitoring stack
docker-compose --profile monitoring up -d

# Access Grafana
http://your-server:3001
Username: admin
Password: (check .env file)
```

### Log Management
```bash
# Enable logging stack
docker-compose --profile logging up -d

# Access Kibana
http://your-server:5601
```

## 🔄 Backup Configuration

### Automated Backups
```bash
# Configure backup retention
BACKUP_ENABLED=true
BACKUP_RETENTION_DAYS=30
BACKUP_SCHEDULE="0 2 * * *"  # Daily at 2 AM

# Manual backup
docker-compose exec flexpbx npm run backup
```

### S3 Backup Integration
```bash
# Configure S3 backup
S3_BACKUP_ENABLED=true
AWS_ACCESS_KEY_ID=your_access_key
AWS_SECRET_ACCESS_KEY=your_secret_key
S3_BACKUP_BUCKET=flexpbx-backups
```

## 🌐 Domain and DNS Configuration

### DNS Records
```
A     your-domain.com        -> SERVER_IP
A     pbx.your-domain.com    -> SERVER_IP
CNAME sip.your-domain.com    -> your-domain.com
SRV   _sip._udp.your-domain.com -> 0 5 5060 sip.your-domain.com
SRV   _sip._tcp.your-domain.com -> 0 5 5060 sip.your-domain.com
SRV   _sips._tcp.your-domain.com -> 0 5 5061 sip.your-domain.com
```

### Nginx Reverse Proxy (Optional)
```nginx
server {
    listen 80;
    server_name your-domain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name your-domain.com;

    ssl_certificate /path/to/certificate.crt;
    ssl_certificate_key /path/to/private.key;

    location / {
        proxy_pass http://localhost:3000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_cache_bypass $http_upgrade;
    }
}
```

## 🎛️ Service Management

### Systemd Service
```bash
# Start/Stop/Restart
sudo systemctl start flexpbx
sudo systemctl stop flexpbx
sudo systemctl restart flexpbx

# Enable/Disable auto-start
sudo systemctl enable flexpbx
sudo systemctl disable flexpbx

# Check status
sudo systemctl status flexpbx

# View logs
sudo journalctl -u flexpbx -f
```

### Docker Management
```bash
# View running containers
docker-compose ps

# View logs
docker-compose logs -f

# Restart services
docker-compose restart

# Update images
docker-compose pull
docker-compose up -d
```

## 🔧 Troubleshooting

### Common Issues

#### Port Conflicts
```bash
# Check port usage
sudo netstat -tulpn | grep :5060
sudo lsof -i :5060

# Kill conflicting processes
sudo pkill -f asterisk
sudo pkill -f freeswitch
```

#### Database Connection Issues
```bash
# Check database status
docker-compose logs mysql
docker-compose exec mysql mysql -u root -p

# Reset database
docker-compose down
docker volume rm flexpbx_mysql_data
docker-compose up -d
```

#### SSL Certificate Issues
```bash
# Check certificate
openssl x509 -in /path/to/certificate.crt -text -noout

# Renew Let's Encrypt
sudo certbot renew
docker-compose restart
```

### Performance Tuning

#### For High Call Volume
```bash
# Increase file descriptors
echo "* soft nofile 65536" >> /etc/security/limits.conf
echo "* hard nofile 65536" >> /etc/security/limits.conf

# Optimize kernel parameters
echo "net.core.rmem_max = 134217728" >> /etc/sysctl.conf
echo "net.core.wmem_max = 134217728" >> /etc/sysctl.conf
sysctl -p
```

## 📞 Initial Configuration

### Default Access
- **Web Interface**: `http://your-server-ip:3000`
- **Admin Extension**: 1000
- **Admin Password**: Welcome2024!
- **Admin PIN**: 9876

### First Steps
1. Change default passwords
2. Configure your domain
3. Add extensions
4. Test calling functionality
5. Configure accessibility features
6. Set up monitoring and backups

## 🆘 Support

### Documentation
- **Repository**: https://github.com/Raywonder/flexpbx
- **Issues**: Report bugs and feature requests on GitHub
- **Wiki**: Detailed configuration guides

### Community
- **Discussions**: GitHub Discussions
- **Discord**: Join our community server
- **Email**: support@flexpbx.local

---

For more detailed configuration options, refer to the `.env.example` file and individual service documentation.