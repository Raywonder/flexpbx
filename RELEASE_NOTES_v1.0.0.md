# 🎉 FlexPBX v1.0.0 - Initial Release

Welcome to FlexPBX, a flexible and accessible PBX server solution with comprehensive desktop client management!

## 🌟 What's New

### Core PBX Server
- **🏗️ Complete PBX Solution**: Full-featured PBX server with modern web interface
- **♿ Accessibility First**: WCAG 2.1 AA compliant with screen reader support
- **📱 WebRTC Integration**: Browser-based calling with SIP.js
- **🔄 Real-time Features**: Live call monitoring and notifications via Socket.IO
- **🗄️ Multi-Database Support**: SQLite, MySQL/MariaDB, PostgreSQL
- **🔌 REST API**: Comprehensive API for integrations
- **🏢 Multi-Tenant**: Central server deployment with load balancing

### 🖥️ Desktop Client (NEW!)
- **💻 Local Installation**: One-click Docker installation with auto-configuration
- **🌐 Remote Deployment**: Deploy to servers via SSH, FTP, or WebDAV
- **⚙️ Nginx Configuration**: Visual configuration generator with SSL support
- **🌍 DNS Management**: A record creation for Cloudflare, DigitalOcean, Namecheap, GoDaddy
- **🔧 cPanel/WHM Support**: Automatic detection and compatibility
- **📊 Server Monitoring**: Real-time status and log viewing
- **🔐 Secure**: Context isolation, encrypted credential storage

## 📦 Download

### Desktop Applications
- **macOS**: FlexPBX-Desktop-1.0.0.dmg (Universal - Intel & Apple Silicon)
- **Windows**: FlexPBX-Desktop-Setup-1.0.0.exe (x64 & x86)
- **Linux**: FlexPBX-Desktop-1.0.0.AppImage (x64)

### Source Code
- **Source**: Source code (zip)
- **Source**: Source code (tar.gz)

## 🚀 Quick Start

### Using Desktop Client
1. Download the installer for your platform
2. Install and launch FlexPBX Desktop
3. Click "New Local Installation" or "Deploy to Remote Server"
4. Follow the setup wizard

### Using Web Interface
1. Clone the repository: `git clone https://github.com/raywonder/flexpbx.git`
2. Navigate to the project: `cd flexpbx`
3. Install dependencies: `npm install`
4. Configure environment: `cp .env.example .env`
5. Start the server: `npm start`
6. Visit the setup wizard: `http://localhost:3000/server-setup.html`

### Using Docker
1. Clone the repository
2. Run: `docker-compose up -d`
3. Access at: `http://localhost:3000`

## 📋 System Requirements

### Desktop Client
- **macOS**: 10.15+ (Intel or Apple Silicon)
- **Windows**: 10 (1903) or later (x64/x86)
- **Linux**: Ubuntu 18.04+ / Debian 10+ / CentOS 8+ / Fedora 32+
- **Disk Space**: 100 MB
- **Docker**: Required for local installations

### PBX Server
- **Node.js**: 18.0 or later
- **Database**: SQLite (included) or MySQL/MariaDB/PostgreSQL
- **Memory**: 512 MB minimum, 2 GB recommended
- **Storage**: 1 GB minimum for recordings and logs

## 🔧 Key Features

### Accessibility & Compliance
- ♿ WCAG 2.1 AA compliant interface
- 🗣️ Screen reader support with ARIA labels
- 🎵 Audio feedback and voice announcements
- ⌨️ Full keyboard navigation
- 🎨 High contrast mode support

### Communication Features
- 📞 SIP calling with WebRTC in browser
- 📹 Video calling support
- 📧 Voicemail with email notifications
- 🔊 Conference calling
- 📱 Mobile app integration ready

### Management & Monitoring
- 📊 Real-time dashboard
- 📈 Call analytics and reporting
- 🔍 Live call monitoring
- 📋 Extension management
- 🔐 Role-based access control

### Deployment Options
- 🐳 Docker containerization
- ☁️ Cloud deployment ready
- 🏢 On-premises installation
- 🔄 Load balancing with HAProxy
- 📊 Monitoring with Prometheus & Grafana

## 🛠️ Technical Stack

- **Backend**: Node.js, Express.js, Socket.IO
- **Frontend**: HTML5, CSS3, JavaScript (ES6+)
- **Communication**: SIP.js, WebRTC, FreeSWITCH compatible
- **Database**: SQLite, MySQL, MariaDB, PostgreSQL
- **Desktop**: Electron 27 with security hardening
- **Containerization**: Docker & Docker Compose

## 📖 Documentation

- 📚 **Setup Guide**: /public/server-setup.html
- 🖥️ **Desktop Client**: /desktop-app/README.md
- 🔌 **API Documentation**: Available in codebase
- 📋 **Configuration**: Comprehensive .env.example included

## 🤝 Contributing

We welcome contributions! Please:
1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests if applicable
5. Submit a pull request

## 📝 License

MIT License - see LICENSE file for details.

## 🆘 Support

- 🐛 **Issues**: [GitHub Issues](https://github.com/raywonder/flexpbx/issues)
- 💬 **Discussions**: [GitHub Discussions](https://github.com/raywonder/flexpbx/discussions)
- 📚 **Wiki**: [GitHub Wiki](https://github.com/raywonder/flexpbx/wiki)

## 🎯 What's Next

- 📱 Mobile applications (iOS & Android)
- 🌐 Additional deployment integrations
- 📊 Enhanced analytics and reporting
- 🔐 Advanced security features
- 🎵 More audio codec support

---

**Note**: Desktop application installers will be available shortly after release creation. This is the initial release focusing on the core server and desktop client codebase.

Thank you for using FlexPBX! 🚀

## 📋 Manual Release Creation Instructions

To create this release on GitHub:

1. Go to [GitHub Releases](https://github.com/raywonder/flexpbx/releases)
2. Click "Create a new release"
3. Set tag version: `v1.0.0`
4. Set release title: `FlexPBX v1.0.0 - Initial Release`
5. Copy the release notes above into the description
6. Check "Set as a pre-release" if needed for testing
7. Click "Publish release"

Binary installers for the desktop application can be built using:
```bash
cd desktop-app
npm install
npm run build-mac    # For macOS
npm run build-win    # For Windows
npm run build-linux  # For Linux
```

Then upload the generated installers from the `dist/` directory to the GitHub release.