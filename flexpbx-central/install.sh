#!/bin/bash

set -e

INSTALL_DIR="/opt/flexpbx"
REPO_URL="https://github.com/raywonder/flexpbx.git"
SERVICE_NAME="flexpbx"

echo "========================================="
echo "     FlexPBX Installation Script"
echo "========================================="

if [ "$EUID" -ne 0 ]; then
  echo "Please run as root (use sudo)"
  exit 1
fi

echo "Checking prerequisites..."

command -v docker >/dev/null 2>&1 || {
  echo "Docker is not installed. Installing Docker..."
  curl -fsSL https://get.docker.com -o get-docker.sh
  sh get-docker.sh
  rm get-docker.sh
}

command -v docker-compose >/dev/null 2>&1 || {
  echo "Docker Compose is not installed. Installing Docker Compose..."
  curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
  chmod +x /usr/local/bin/docker-compose
}

echo "Creating installation directory..."
mkdir -p $INSTALL_DIR

echo "Cloning FlexPBX repository..."
if [ -d "$INSTALL_DIR/.git" ]; then
  cd $INSTALL_DIR
  git pull
else
  git clone $REPO_URL $INSTALL_DIR
  cd $INSTALL_DIR
fi

echo "Creating environment configuration..."
if [ ! -f ".env" ]; then
  cp .env.example .env

  JWT_SECRET=$(openssl rand -hex 32)
  SESSION_SECRET=$(openssl rand -hex 32)
  DB_PASSWORD=$(openssl rand -hex 16)
  MYSQL_ROOT_PASSWORD=$(openssl rand -hex 16)
  AMI_PASSWORD=$(openssl rand -hex 16)

  sed -i "s/changeme_jwt_secret_key/$JWT_SECRET/g" .env
  sed -i "s/changeme_session_secret/$SESSION_SECRET/g" .env
  sed -i "s/changeme/$DB_PASSWORD/g" .env
  sed -i "s/changeme_ami_password/$AMI_PASSWORD/g" .env

  echo "Generated secure passwords and secrets"
fi

echo "Creating data directories..."
mkdir -p data logs recordings voicemail ssl

echo "Setting up systemd service..."
cat > /etc/systemd/system/$SERVICE_NAME.service <<EOF
[Unit]
Description=FlexPBX Service
After=docker.service
Requires=docker.service

[Service]
Type=simple
WorkingDirectory=$INSTALL_DIR
ExecStart=/usr/local/bin/docker-compose up
ExecStop=/usr/local/bin/docker-compose down
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable $SERVICE_NAME

echo "Starting FlexPBX..."
docker-compose up -d

echo "Waiting for services to start..."
sleep 10

echo "Running database initialization..."
docker-compose exec -T flexpbx npm run setup:database 2>/dev/null || true

echo "========================================="
echo "     FlexPBX Installation Complete!"
echo "========================================="
echo ""
echo "Access the web interface at:"
echo "  http://$(hostname -I | awk '{print $1}')"
echo ""
echo "Default credentials:"
echo "  Extension: 1000"
echo "  Password: Check .env file"
echo ""
echo "SSH public key for GitHub:"
echo "  Add the following key to your GitHub repository:"
cat ~/.ssh/flexpbx_ed25519.pub 2>/dev/null || echo "  No SSH key found"
echo ""
echo "To manage the service:"
echo "  systemctl start $SERVICE_NAME"
echo "  systemctl stop $SERVICE_NAME"
echo "  systemctl restart $SERVICE_NAME"
echo "  systemctl status $SERVICE_NAME"
echo ""
echo "To view logs:"
echo "  docker-compose logs -f"
echo ""
echo "For more information, visit:"
echo "  https://github.com/raywonder/flexpbx"