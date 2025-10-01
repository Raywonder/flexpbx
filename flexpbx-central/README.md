# FlexPBX

A flexible, cross-platform, open-source PBX server with built-in accessibility features. Compatible with FreePBX, Asterisk, and various SIP clients while providing a custom, modern API infrastructure.

## ♿ Accessibility Features

- **WCAG 2.1 AA Compliant**: Full compliance with web accessibility guidelines
- **Screen Reader Optimized**: Tested with NVDA, JAWS, VoiceOver, and Orca
- **Voice Announcements**: Real-time audio feedback for system events
- **Keyboard Navigation**: Complete functionality without a mouse
- **High Contrast Themes**: Multiple visual themes for low vision users
- **Audio DTMF Feedback**: Audible keypad tones and confirmations
- **Accessible Call Controls**: Voice-guided call management
- **Braille Display Support**: Compatible with refreshable braille displays

## 🚀 Quick Start

### Prerequisites

- Docker and Docker Compose installed
- Minimum 2GB RAM, 20GB disk space
- Network ports 80, 443, 5060-5061, 10000-20000 available
- Modern browser with WebRTC support

### One-Command Installation

```bash
curl -sSL https://raw.githubusercontent.com/raywonder/flexpbx/main/install.sh | sudo bash
```

### Manual Installation

1. **Clone the repository:**
```bash
git clone git@github.com:raywonder/flexpbx.git
cd flexpbx
```

2. **Configure environment variables:**
```bash
cp .env.example .env
nano .env  # Edit configuration
```

3. **Start the services:**
```bash
docker-compose up -d
```

4. **Initialize the database:**
```bash
docker-compose exec accessible-pbx npm run setup:database
```

5. **Access the web interface:**
```bash
# HTTP (development)
http://your-server-ip

# HTTPS (production)
https://your-domain.com
```

## 🛠️ Configuration

### Environment Variables

```bash
# Basic Configuration
DOMAIN_NAME=pbx.example.com
SSL_ENABLED=true
LOG_LEVEL=info

# Database
DB_PASSWORD=your_secure_password
MYSQL_ROOT_PASSWORD=your_root_password

# Security
JWT_SECRET=your_jwt_secret_key
AMI_PASSWORD=your_ami_password

# Accessibility
ACCESSIBILITY_ENABLED=true
SCREEN_READER_SUPPORT=true
AUDIO_FEEDBACK_ENABLED=true
VOICE_ANNOUNCEMENTS_ENABLED=true
ACCESSIBILITY_VOICE_SPEED=150
ACCESSIBILITY_ANNOUNCEMENT_DELAY=2000

# External Services
LETSENCRYPT_EMAIL=admin@example.com
AWS_ACCESS_KEY_ID=your_aws_key
AWS_SECRET_ACCESS_KEY=your_aws_secret
S3_BACKUP_BUCKET=your_backup_bucket

# Monitoring
GRAFANA_PASSWORD=admin
BACKUP_RETENTION_DAYS=30
```

### Asterisk Configuration

The system automatically configures Asterisk with accessibility features:

- **Enhanced Audio Prompts**: Multi-language support with clear pronunciation
- **DTMF Detection**: Improved keypad recognition for assistive technology
- **Call Recording**: Automatic transcription capabilities
- **Voice Mail**: Text-to-speech and speech-to-text integration
- **Conference Bridge**: Accessible conference room management

### SIP Client Configuration

For the web softphone or external SIP clients:

```
SIP Server: your-domain.com (or IP address)
Port: 5060 (UDP/TCP) or 5061 (TLS)
Username: Extension number (e.g., 1001)
Password: Set in admin panel
Transport: UDP, TCP, or TLS
```

## 📱 Client Applications

### Web-Based Softphone

Access the built-in accessible softphone at:
```
https://your-domain.com/softphone
```

Features:
- Full keyboard navigation
- Screen reader announcements
- Voice feedback for all actions
- High contrast themes
- Large text options
- DTMF tone feedback

### Mobile Applications

Compatible with standard SIP clients:
- **iOS**: Blink, Linphone, Zoiper
- **Android**: Linphone, CSipSimple, Zoiper
- **Desktop**: Jami, Linphone, Zoiper

Configuration tips for accessibility:
- Enable audio feedback in client settings
- Use large text mode where available
- Configure voice announcements
- Set appropriate ring tones

## 🔧 Administration

### Web Admin Panel

Access the administration interface at:
```
https://your-domain.com/admin
```

### Command Line Tools

```bash
# System status
docker-compose exec accessible-pbx npm run health-check

# View logs
docker-compose logs -f accessible-pbx

# Database backup
docker-compose exec accessible-pbx npm run backup

# Update system
docker-compose exec accessible-pbx npm run update

# Restart services
docker-compose restart

# Accessibility test
docker-compose --profile testing run accessibility-test
```

### API Documentation

RESTful API available at:
```
https://your-domain.com/api/docs
```

Key endpoints:
- `GET /api/v1/system/status` - System health
- `POST /api/v1/auth/login` - Authentication
- `GET /api/v1/extensions` - Extension management
- `GET /api/v1/cdr` - Call detail records
- `GET /api/v1/accessibility/preferences` - User preferences

## 🎯 Features

### Core PBX Features

- **Extension Management**: Create and manage SIP extensions
- **Call Routing**: Advanced IVR with accessibility enhancements
- **Queue Management**: ACD with screen reader support
- **Conference Bridging**: Accessible conference rooms
- **Voice Mail**: Enhanced with transcription
- **Call Recording**: Searchable audio recordings
- **Fax Support**: T.38 and audio fax with OCR
- **Mobile Integration**: Mobile app support

### Modern Integrations

- **WhatsApp Business API**: Two-way messaging
- **SMS/MMS Gateway**: Multi-provider support
- **WebRTC**: Browser-based calling
- **REST API**: Full system integration
- **Webhooks**: Real-time event notifications
- **SSO Support**: OpenID Connect integration
- **Multi-tenant**: Reseller and hosted solutions

### Accessibility Enhancements

- **Audio Descriptions**: Spoken system status
- **Voice Commands**: Hands-free operation
- **Custom Prompts**: User-recordable messages
- **Keyboard Shortcuts**: Comprehensive hotkey system
- **Braille Support**: Direct braille display integration
- **High Contrast**: Multiple theme options
- **Text Scaling**: Adjustable font sizes
- **Screen Reader API**: Direct screen reader communication

## 🔒 Security

### Built-in Security Features

- **SSL/TLS Encryption**: End-to-end encryption
- **SIP Security**: SRTP and TLS support
- **Rate Limiting**: DDoS protection
- **Firewall Integration**: iptables, UFW, firewalld
- **Audit Logging**: Comprehensive security logs
- **Role-Based Access**: Granular permissions
- **API Security**: JWT tokens and API keys
- **Intrusion Detection**: Fail2ban integration

### Security Best Practices

1. **Change default passwords** immediately
2. **Enable SSL/TLS** for all connections
3. **Use strong passwords** and API keys
4. **Configure firewalls** properly
5. **Regular security updates**
6. **Monitor system logs**
7. **Backup encryption keys**
8. **Network segmentation**

## 📊 Monitoring

### Built-in Monitoring

- **Real-time Dashboard**: System health metrics
- **Call Quality Monitoring**: RTP statistics
- **Performance Metrics**: CPU, memory, network
- **Accessibility Analytics**: Feature usage tracking
- **Alert System**: Email and SMS notifications

### External Monitoring (Optional)

```bash
# Enable monitoring stack
docker-compose --profile monitoring up -d

# Access Grafana dashboard
http://your-domain.com:3001

# Access Prometheus
http://your-domain.com:9090
```

### Log Management (Optional)

```bash
# Enable logging stack
docker-compose --profile logging up -d

# Access Kibana
http://your-domain.com:5601
```

## 🧪 Testing

### Automated Testing

```bash
# Run all tests
npm test

# Accessibility tests
npm run test:accessibility

# Load testing
docker-compose --profile testing run load-test

# Security audit
npm run security:audit
```

### Manual Testing Checklist

- [ ] Screen reader navigation (NVDA, JAWS, VoiceOver)
- [ ] Keyboard-only operation
- [ ] Voice announcement functionality
- [ ] High contrast mode
- [ ] Large text scaling
- [ ] Audio feedback systems
- [ ] Braille display compatibility
- [ ] Mobile accessibility

## 📈 Scaling

### Single Server Deployment

Suitable for:
- Up to 100 concurrent calls
- 500 extensions
- Small to medium organizations

### Multi-Server Deployment

For larger installations:
- Load balancer (HAProxy or Nginx)
- Database clustering (MariaDB Galera)
- Redis clustering
- Multiple Asterisk servers
- CDN for media files

### Cloud Deployment

Supported platforms:
- AWS (CloudFormation templates included)
- Google Cloud Platform
- Microsoft Azure
- DigitalOcean
- Private cloud (OpenStack)

## 🤝 Contributing

We welcome contributions, especially from the accessibility community!

### Areas for Contribution

- **Accessibility Testing**: Screen reader compatibility
- **Voice Prompts**: Multi-language audio files
- **Documentation**: User guides and tutorials
- **Code Contributions**: Bug fixes and features
- **UI/UX**: Accessibility improvements
- **Testing**: Automated and manual testing

### Development Setup

```bash
# Clone repository
git clone git@github.com:raywonder/flexpbx.git
cd flexpbx

# Install dependencies
npm install

# Start development environment
npm run dev

# Run tests
npm test

# Accessibility audit
npm run audit:accessibility
```

### Contribution Guidelines

1. **Follow WCAG 2.1 AA** guidelines for all UI changes
2. **Test with screen readers