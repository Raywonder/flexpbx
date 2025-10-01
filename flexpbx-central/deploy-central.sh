#!/bin/bash

set -e

echo "================================================"
echo "     FlexPBX Central Server Deployment"
echo "================================================"

DEPLOY_DIR=$(pwd)
CENTRAL_DOMAIN=${1:-central.flexpbx.local}
CLUSTER_SIZE=${2:-3}

echo "Deployment directory: $DEPLOY_DIR"
echo "Central domain: $CENTRAL_DOMAIN"
echo "Cluster size: $CLUSTER_SIZE"

echo "Setting up environment..."
if [ ! -f ".env" ]; then
    cp .env.example .env

    sed -i "s/DOMAIN_NAME=.*/DOMAIN_NAME=$CENTRAL_DOMAIN/g" .env
    sed -i "s/SIP_MODE=.*/SIP_MODE=custom/g" .env
    sed -i "s/DB_TYPE=.*/DB_TYPE=mysql/g" .env

    echo "CENTRAL_MODE=true" >> .env
    echo "CLUSTER_NODES=$CLUSTER_SIZE" >> .env
    echo "COPYPARTY_ENABLED=true" >> .env
    echo "COPYPARTY_PORT=3923" >> .env
fi

echo "Creating necessary directories..."
mkdir -p config data logs sql/central monitoring/grafana/dashboards monitoring/grafana/datasources
mkdir -p monitoring/prometheus haproxy nginx/sites-enabled ssl scripts

echo "Generating SSL certificates for central server..."
if [ ! -f "ssl/cert.pem" ]; then
    openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
        -keyout ssl/key.pem \
        -out ssl/cert.pem \
        -subj "/C=US/ST=State/L=City/O=FlexPBX/CN=$CENTRAL_DOMAIN"
    echo "SSL certificates generated"
fi

echo "Creating HAProxy configuration..."
cat > haproxy/haproxy.cfg <<EOF
global
    maxconn 4096
    log stdout local0

defaults
    mode tcp
    timeout connect 5s
    timeout client 30s
    timeout server 30s
    option tcplog

frontend sip_frontend
    bind *:5070
    default_backend sip_servers

backend sip_servers
    balance roundrobin
    server pbx1 flexpbx-central:5060 check
    server pbx2 flexpbx-central:5061 check

frontend web_frontend
    bind *:8080
    mode http
    default_backend web_servers

backend web_servers
    mode http
    balance roundrobin
    server web1 flexpbx-central:3000 check
    server web2 nginx-central:80 check
EOF

echo "Creating Nginx configuration..."
cat > nginx/central.conf <<'EOF'
user nginx;
worker_processes auto;
error_log /var/log/nginx/error.log warn;
pid /var/run/nginx.pid;

events {
    worker_connections 1024;
}

http {
    include /etc/nginx/mime.types;
    default_type application/octet-stream;

    log_format main '$remote_addr - $remote_user [$time_local] "$request" '
                    '$status $body_bytes_sent "$http_referer" '
                    '"$http_user_agent" "$http_x_forwarded_for"';

    access_log /var/log/nginx/access.log main;

    sendfile on;
    tcp_nopush on;
    keepalive_timeout 65;
    gzip on;

    upstream flexpbx {
        server flexpbx-central:3000;
    }

    upstream copyparty {
        server copyparty:3923;
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
            proxy_set_header X-Real-IP $remote_addr;
            proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
            proxy_set_header X-Forwarded-Proto $scheme;
        }

        location /files {
            proxy_pass http://copyparty;
            proxy_http_version 1.1;
            proxy_set_header Host $host;
            proxy_set_header X-Real-IP $remote_addr;
            proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
            proxy_set_header X-Forwarded-Proto $scheme;
        }

        location /ws {
            proxy_pass http://flexpbx;
            proxy_http_version 1.1;
            proxy_set_header Upgrade $http_upgrade;
            proxy_set_header Connection "upgrade";
            proxy_set_header Host $host;
            proxy_set_header X-Real-IP $remote_addr;
            proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        }
    }

    server {
        listen 443 ssl http2;
        server_name _;

        ssl_certificate /etc/nginx/ssl/cert.pem;
        ssl_certificate_key /etc/nginx/ssl/key.pem;
        ssl_protocols TLSv1.2 TLSv1.3;
        ssl_ciphers HIGH:!aNULL:!MD5;

        location / {
            proxy_pass http://flexpbx;
            proxy_http_version 1.1;
            proxy_set_header Upgrade $http_upgrade;
            proxy_set_header Connection 'upgrade';
            proxy_set_header Host $host;
            proxy_cache_bypass $http_upgrade;
            proxy_set_header X-Real-IP $remote_addr;
            proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
            proxy_set_header X-Forwarded-Proto https;
        }
    }
}
EOF

echo "Creating backup script..."
cat > scripts/backup-central.sh <<'EOF'
#!/bin/sh
while true; do
    DATE=$(date +%Y%m%d_%H%M%S)

    # Backup MySQL
    mysqldump -h mysql-central -u root -pCentralRootFLexPBX2024! flexpbx_central > /backups/mysql_$DATE.sql

    # Backup Redis
    cp /redis_data/dump.rdb /backups/redis_$DATE.rdb

    # Backup config files
    tar -czf /backups/config_$DATE.tar.gz /data

    # Clean old backups (keep last 30 days)
    find /backups -type f -mtime +30 -delete

    # Sleep for 24 hours
    sleep 86400
done
EOF

chmod +x scripts/backup-central.sh

echo "Creating Prometheus configuration..."
cat > monitoring/prometheus/central.yml <<EOF
global:
  scrape_interval: 15s
  evaluation_interval: 15s

scrape_configs:
  - job_name: 'flexpbx-central'
    static_configs:
      - targets: ['flexpbx-central:3000']

  - job_name: 'mysql'
    static_configs:
      - targets: ['mysql-central:3306']

  - job_name: 'redis'
    static_configs:
      - targets: ['redis-central:6379']

  - job_name: 'haproxy'
    static_configs:
      - targets: ['haproxy:8080']
EOF

echo "Creating Grafana datasources..."
cat > monitoring/grafana/datasources/datasources.yml <<EOF
apiVersion: 1

datasources:
  - name: Prometheus
    type: prometheus
    access: proxy
    url: http://prometheus-central:9090
    isDefault: true

  - name: MySQL
    type: mysql
    url: mysql-central:3306
    database: flexpbx_central
    user: flexpbx_admin
    secureJsonData:
      password: CentralFLexPBX2024!
EOF

echo "Starting central server deployment..."
docker-compose -f docker-compose.central.yml pull
docker-compose -f docker-compose.central.yml up -d

echo "Waiting for services to initialize..."
sleep 20

echo "Running database initialization..."
docker-compose -f docker-compose.central.yml exec -T flexpbx-central npm run setup:database || true

echo "================================================"
echo "   FlexPBX Central Server Deployment Complete!"
echo "================================================"
echo ""
echo "Access points:"
echo "  Web Interface: http://$CENTRAL_DOMAIN"
echo "  Secure Web: https://$CENTRAL_DOMAIN"
echo "  CopyParty Files: http://$CENTRAL_DOMAIN:3923"
echo "  Grafana: http://$CENTRAL_DOMAIN:3001"
echo "  Prometheus: http://$CENTRAL_DOMAIN:9090"
echo "  HAProxy Stats: http://$CENTRAL_DOMAIN:8080/stats"
echo ""
echo "Default credentials:"
echo "  Admin: admin / FLexPBXAdmin2024!"
echo "  Operator: operator / Operator2024!"
echo "  Grafana: admin / GrafanaCentral2024!"
echo ""
echo "SIP Configuration:"
echo "  Primary: $CENTRAL_DOMAIN:5070"
echo "  WebRTC: wss://$CENTRAL_DOMAIN:8089"
echo ""
echo "To manage the deployment:"
echo "  docker-compose -f docker-compose.central.yml ps"
echo "  docker-compose -f docker-compose.central.yml logs -f"
echo "  docker-compose -f docker-compose.central.yml down"
echo ""