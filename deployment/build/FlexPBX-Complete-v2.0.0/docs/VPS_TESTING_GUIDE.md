# FlexPBX VPS Testing Guide

## 🖥️ VPS/Server Testing Options

### Server Types Supported
- **VPS (Virtual Private Server)** - Dedicated resources, dedicated IP
- **Dedicated Servers** - Physical hardware, dedicated IP
- **Shared Hosting** - Shared resources, shared IP (limited features)
- **Cloud Instances** - AWS, Google Cloud, Azure

---

## 📋 VPS Provider Testing Matrix

### Recommended VPS Providers

#### 🥇 Premium Providers (Dedicated IP)
| Provider | Plan | RAM | Storage | Price | Best For |
|----------|------|-----|---------|-------|----------|
| **DigitalOcean** | Basic Droplet | 2GB | 50GB | $12/month | Development |
| **Vultr** | Regular Performance | 2GB | 55GB | $12/month | Production |
| **Linode** | Nanode | 2GB | 50GB | $12/month | Stable Testing |
| **AWS** | t3.small | 2GB | 20GB | ~$15/month | Enterprise |
| **Hetzner** | CX21 | 8GB | 40GB | €4.90/month | Budget Production |

#### 🥈 Budget Providers (Dedicated IP)
| Provider | Plan | RAM | Storage | Price | Best For |
|----------|------|-----|---------|-------|----------|
| **Contabo** | VPS S | 8GB | 200GB | €4.99/month | High Storage |
| **OVH** | VPS Value | 2GB | 40GB | $6/month | European Testing |
| **IONOS** | VPS Linux M | 2GB | 80GB | $10/month | German Quality |
| **RackNerd** | Annual Special | 2GB | 35GB | $25/year | Long-term Testing |

#### 🏢 Dedicated Server Options
| Provider | CPU | RAM | Storage | Price | Best For |
|----------|-----|-----|---------|-------|----------|
| **Hetzner** | 4 cores | 32GB | 2x1TB | €39/month | Production PBX |
| **OVH** | 4 cores | 32GB | 2x1TB | $59/month | High Performance |
| **SoYouStart** | 4 cores | 16GB | 1TB | $49/month | Cost-effective |

---

## 🌐 IP Configuration Types

### 1. Dedicated IP (Recommended)
```bash
# Full control over all ports
# Direct access to services
# Best for production use

# Test with dedicated IP
./test-suite.sh vps 192.168.1.100

# Services accessible at:
# FlexPBX: http://192.168.1.100:3000
# Audio: http://192.168.1.100:8000
# Jellyfin: http://192.168.1.100:8096
```

### 2. Shared IP (Limited)
```bash
# Limited port access
# May require proxy configuration
# Good for testing only

# Install with custom ports
./install.sh local --port-offset 1000
# Services will use ports 4000, 9000, 9096
```

### 3. Cloud Instance with Elastic IP
```bash
# AWS/GCP/Azure with elastic/static IP
# Highly scalable
# Pay-per-use billing

# AWS example
aws ec2 allocate-address --domain vpc
aws ec2 associate-address --instance-id i-1234567890abcdef0 --allocation-id eipalloc-12345678
```

---

## 🚀 Quick VPS Setup Scripts

### Auto-Detect Server Type
```bash
#!/bin/bash
# Auto-detection script

detect_server_type() {
    # Check if it's a VPS
    if [ -f /proc/user_beancounters ]; then
        echo "OpenVZ VPS detected"
    elif [ -d /proc/vz ]; then
        echo "Virtuozzo VPS detected"
    elif dmesg | grep -i "vmware\|virtualbox\|kvm\|xen" &>/dev/null; then
        echo "Virtual Machine detected"
    elif [ -f /sys/class/dmi/id/product_name ] && grep -q "Droplet\|VPS\|Virtual" /sys/class/dmi/id/product_name; then
        echo "Cloud VPS detected"
    else
        echo "Dedicated server or unknown"
    fi
}

# Run detection
detect_server_type
```

### VPS Provider-Specific Setup

#### DigitalOcean Droplet
```bash
# Create and setup DigitalOcean droplet
doctl compute droplet create flexpbx-test \
  --image ubuntu-22-04-x64 \
  --size s-2vcpu-2gb \
  --region nyc1 \
  --ssh-keys your-ssh-key-id

# Get IP and test
DROPLET_IP=$(doctl compute droplet get flexpbx-test --format PublicIPv4 --no-header)
./test-suite.sh vps $DROPLET_IP
```

#### AWS EC2 Instance
```bash
# Launch EC2 instance
aws ec2 run-instances \
  --image-id ami-0c02fb55956c7d316 \
  --count 1 \
  --instance-type t3.small \
  --key-name your-key-pair \
  --security-group-ids sg-12345678

# Get IP and test
INSTANCE_IP=$(aws ec2 describe-instances --query 'Reservations[0].Instances[0].PublicIpAddress' --output text)
./test-suite.sh vps $INSTANCE_IP
```

#### Vultr Instance
```bash
# Create Vultr instance using API
curl -X POST "https://api.vultr.com/v2/instances" \
  -H "Authorization: Bearer $VULTR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "region": "ewr",
    "plan": "vc2-2c-4gb",
    "os_id": 387,
    "label": "flexpbx-test"
  }'
```

---

## 🧪 Testing Commands by Server Type

### VPS Testing (2GB RAM)
```bash
# Standard VPS test
cd "/Users/administrator/dev/apps/flex pbx/flexpbx"
export VPS_IP="your.vps.ip.address"

# Quick remote installation
./deployment/build/FlexPBX-Complete-v1.0.0/install.sh remote $VPS_IP

# Run comprehensive tests
./test-suite.sh vps $VPS_IP

# Monitor during test
ssh $VPS_IP "htop"
```

### Dedicated Server Testing (8GB+ RAM)
```bash
# Full installation on dedicated server
export SERVER_IP="your.dedicated.ip"

# Install with full features
scp -r deployment/build/FlexPBX-Complete-v1.0.0.zip root@$SERVER_IP:/tmp/
ssh root@$SERVER_IP "
  cd /tmp
  unzip FlexPBX-Complete-v1.0.0.zip
  cd FlexPBX-Complete-v1.0.0
  ./install.sh local --mode full --enable-all
"

# Run stress tests
./test-suite.sh vps $SERVER_IP --stress-test
```

### Shared Hosting Testing (Limited)
```bash
# Limited installation for shared hosting
export SHARED_IP="your.shared.ip"

# Install minimal version
./deployment/build/FlexPBX-Complete-v1.0.0/install.sh local --mode minimal --shared-hosting

# Test basic functionality only
curl http://$SHARED_IP:3000/health
```

---

## 🔧 Provider-Specific Configurations

### DigitalOcean Optimizations
```bash
# Enable DO monitoring
curl -X POST "https://api.digitalocean.com/v2/droplets/$DROPLET_ID/actions" \
  -H "Authorization: Bearer $DO_TOKEN" \
  -d '{"type":"enable_monitoring"}'

# Setup DO load balancer for multiple instances
doctl compute load-balancer create \
  --name flexpbx-lb \
  --forwarding-rules entry_protocol:http,entry_port:80,target_protocol:http,target_port:3000
```

### AWS Optimizations
```bash
# Create security group for FlexPBX
aws ec2 create-security-group \
  --group-name flexpbx-sg \
  --description "FlexPBX Security Group"

# Add required port rules
aws ec2 authorize-security-group-ingress \
  --group-name flexpbx-sg \
  --protocol tcp \
  --port 3000 \
  --cidr 0.0.0.0/0

aws ec2 authorize-security-group-ingress \
  --group-name flexpbx-sg \
  --protocol tcp \
  --port 8000 \
  --cidr 0.0.0.0/0
```

### Vultr Optimizations
```bash
# Enable Vultr firewall
curl -X POST "https://api.vultr.com/v2/firewalls" \
  -H "Authorization: Bearer $VULTR_API_KEY" \
  -d '{
    "group_description": "FlexPBX Firewall",
    "inbound_rules": [
      {"protocol": "tcp", "port": "3000", "source": "0.0.0.0/0"},
      {"protocol": "tcp", "port": "8000", "source": "0.0.0.0/0"}
    ]
  }'
```

---

## 📊 Performance Testing by Server Type

### VPS Performance Test
```bash
#!/bin/bash
# VPS-specific performance test

test_vps_performance() {
    local vps_ip=$1

    echo "Testing VPS performance on $vps_ip..."

    # CPU test
    ssh $vps_ip "sysbench cpu --cpu-max-prime=20000 run"

    # Memory test
    ssh $vps_ip "sysbench memory --memory-total-size=1G run"

    # Network test
    iperf3 -c $vps_ip -t 30

    # FlexPBX load test
    ab -n 1000 -c 10 http://$vps_ip:3000/
}

test_vps_performance $VPS_IP
```

### Dedicated Server Performance Test
```bash
#!/bin/bash
# Dedicated server stress test

test_dedicated_performance() {
    local server_ip=$1

    echo "Testing dedicated server performance on $server_ip..."

    # Multi-core CPU test
    ssh $server_ip "sysbench cpu --cpu-max-prime=20000 --threads=8 run"

    # Large memory test
    ssh $server_ip "sysbench memory --memory-total-size=10G --threads=4 run"

    # Disk I/O test
    ssh $server_ip "sysbench fileio --file-total-size=10G prepare && sysbench fileio --file-total-size=10G --file-test-mode=rndrw run"

    # High concurrent connections
    ab -n 10000 -c 100 http://$server_ip:3000/
}

test_dedicated_performance $SERVER_IP
```

---

## ⚡ Quick Start Commands

### 5-Minute VPS Test
```bash
# Replace with your VPS IP
export VPS_IP="192.168.1.100"

# One-line VPS setup and test
curl -sSL https://raw.githubusercontent.com/Raywonder/flexpbx/main/quick-vps-test.sh | bash -s $VPS_IP
```

### 10-Minute Dedicated Server Test
```bash
# Replace with your server IP
export SERVER_IP="192.168.1.200"

# Full dedicated server setup
curl -sSL https://raw.githubusercontent.com/Raywonder/flexpbx/main/quick-dedicated-test.sh | bash -s $SERVER_IP
```

### Shared Hosting Test
```bash
# Limited shared hosting test
export SHARED_IP="shared.hosting.com"

# Minimal installation test
curl -sSL https://raw.githubusercontent.com/Raywonder/flexpbx/main/quick-shared-test.sh | bash -s $SHARED_IP
```

---

## 🎯 Expected Test Results

### VPS (2GB RAM)
- ✅ Basic PBX functionality
- ✅ 10-50 concurrent SIP registrations
- ✅ Audio streaming up to 720p
- ✅ Desktop app connectivity
- ⚠️ Limited Jellyfin transcoding

### Dedicated Server (8GB+ RAM)
- ✅ Full PBX functionality
- ✅ 100+ concurrent SIP registrations
- ✅ 4K audio/video streaming
- ✅ Full Jellyfin media server
- ✅ Multiple control panel integrations

### Shared Hosting
- ⚠️ Basic web interface only
- ❌ No SIP/RTP (port restrictions)
- ⚠️ Limited audio streaming
- ✅ WHMCS module works
- ❌ No desktop app server mode

All testing options provide comprehensive validation of FlexPBX functionality across different hosting environments.