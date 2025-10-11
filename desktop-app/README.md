# FlexPBX Desktop

Enhanced cross-platform desktop application for comprehensive FlexPBX installations, deployments, and management with advanced accessibility features.

## ✨ **Major Enhancements**

### 🎯 **Accessibility First**
- **Full VoiceOver Support**: Complete screen reader compatibility with ARIA attributes and role definitions
- **Keyboard Navigation**: Full keyboard accessibility for all interface elements
- **Screen Reader Classes**: Dedicated classes for assistive technologies
- **High Contrast Themes**: Support for accessibility color schemes
- **WCAG 2.1 AA Compliance**: Meets accessibility standards

### 🔧 **Advanced PBX Management**
- **Single Instance Enforcement**: Prevents multiple app instances using `app.requestSingleInstanceLock()`
- **Services Tab**: Comprehensive module management with 15 organized categories
- **Feature Codes**: Complete *number command system for SIP client access (40+ codes)
- **Per-Extension Settings**: Advanced configuration with mini-tabs for each extension
- **Archive System**: Support for .flx, .flxx, .mod formats with file integrity preservation

### 🔐 **Enhanced Security Features**
- **PIN Codes**: 4-64 digit PIN support for account protection and unlocking
- **Vacation Mode**: Automated settings for temporary unavailability
- **Account Recovery**: Multiple recovery methods for lost credentials
- **2FA Support**: Two-factor authentication integration
- **Account Protection**: Advanced security settings per extension

### 🌐 **API Integration & External Services**
- **Built-in API Support**: Integration for api.tappedin.fm, api.devinecreations.net, api.devine-creations.com
- **Real-time Testing**: Connection testing and validation with comprehensive error handling
- **Authentication Management**: Secure credential storage and token management
- **Request/Response Logging**: Complete API interaction tracking

### 📁 **Smart Path Management**
- **PBX Name-Based Paths**: Automatic folder structure: `~/servers/[PBX-NAME]/modules/type/modulename`
- **Real-time Previews**: Live path validation and domain preview systems
- **Manual Path Input**: Text input support for custom file path locations
- **Auto-Path Checking**: Validates paths before creation and usage

### 📊 **Comprehensive Logging System**
- **Multiple Log Types**: Success, failure, and metadata tracking
- **Logs Tab**: Dedicated interface for viewing and filtering logs
- **Real-time Updates**: Live log streaming during operations
- **Search & Filter**: Advanced log searching and categorization
- **Troubleshooting Support**: Detailed error tracking for installation and runtime problems

### 🚀 **Original Features Enhanced**
- **Docker Integration**: Seamlessly install FlexPBX locally using Docker
- **Auto Configuration**: Automatically generates docker-compose.yml and environment files
- **Service Management**: Start, stop, and monitor Docker services directly from the app
- **Multiple Protocols**: Deploy via SSH, FTP, or WebDAV
- **Server Compatibility**: Supports standard Linux servers and cPanel/WHM environments
- **Nginx Configuration**: Visual config builder with SSL integration
- **DNS Management**: Multi-provider support (Cloudflare, DigitalOcean, Namecheap, GoDaddy)
- **Real-time Monitoring**: Server health and service status tracking

## System Requirements

### macOS
- macOS 10.15 (Catalina) or later
- Apple Silicon (M1/M2) or Intel x64 processor
- 100 MB free disk space
- Docker Desktop (for local installations)

### Windows
- Windows 10 (version 1903) or later
- x64 or x86 processor
- 100 MB free disk space
- Docker Desktop (for local installations)

### Linux
- Ubuntu 18.04+ / Debian 10+ / CentOS 8+ / Fedora 32+
- x64 processor
- 100 MB free disk space
- Docker (for local installations)

## Installation

### Download
Download the latest release from the [GitHub Releases](https://github.com/raywonder/flexpbx/releases) page:

- **macOS**: `FlexPBX-Desktop-1.0.0.dmg`
- **Windows**: `FlexPBX-Desktop-Setup-1.0.0.exe`
- **Linux**: `FlexPBX-Desktop-1.0.0.AppImage`

### macOS Installation
1. Download the `.dmg` file
2. Open the downloaded file
3. Drag FlexPBX Desktop to your Applications folder
4. Launch from Applications or Spotlight

### Windows Installation
1. Download the `.exe` installer
2. Run the installer as Administrator
3. Follow the installation wizard
4. Launch from Start Menu or Desktop shortcut

### Linux Installation
1. Download the `.AppImage` file
2. Make it executable: `chmod +x FlexPBX-Desktop-1.0.0.AppImage`
3. Run: `./FlexPBX-Desktop-1.0.0.AppImage`

## Quick Start

### Local Installation
1. Open FlexPBX Desktop
2. Click "New Local Installation" or use `Cmd+N` (macOS) / `Ctrl+N` (Windows/Linux)
3. Choose installation directory
4. Configure settings (ports, domain, etc.)
5. Optionally configure Nginx reverse proxy
6. Click "Start Installation"

### Remote Deployment
1. Navigate to "Remote Deploy" tab
2. Choose deployment method (SSH, FTP, or WebDAV)
3. Enter server connection details
4. Configure installation settings
5. Click "Deploy to Server"

### DNS Configuration
1. Go to Settings and configure your DNS provider credentials
2. During installation, enable "Create DNS A Record"
3. The app will automatically create the DNS record pointing to your server

## Configuration

### DNS Providers
Configure your DNS provider credentials in Settings:

#### Cloudflare
- API Token: Generate from Cloudflare Dashboard > My Profile > API Tokens

#### DigitalOcean
- API Token: Create from DigitalOcean Control Panel > API

#### Namecheap
- API User, API Key, Client IP: Enable in Account > Profile > Tools > API Access

#### GoDaddy
- API Key and Secret: Generate from GoDaddy Developer Portal

### Server Connections
For remote deployments, you can save connection profiles for quick access:

#### SSH
- Host, Port, Username
- Password or SSH Private Key
- Optional: Jump host configuration

#### FTP/SFTP
- Host, Port, Username, Password
- Support for explicit and implicit SSL/TLS

#### WebDAV
- Server URL, Username, Password
- Compatible with most cloud storage providers

## Development

### Prerequisites
- Node.js 18+ and npm
- Git
- Electron development tools

### Setup
```bash
git clone https://github.com/raywonder/flexpbx.git
cd flexpbx/desktop-app
npm install
```

### Development Mode
```bash
npm run dev
```

### Building
```bash
# Build for current platform
npm run build

# Build for specific platforms
npm run build-mac
npm run build-win
npm run build-linux
```

## 📋 **Module Categories**

The Services tab organizes modules into 15 comprehensive categories:

1. **Core**: Essential PBX functionality and base system components
2. **Extensions**: Extension management, configuration, and advanced settings
3. **Trunks**: SIP trunk configuration, ownership, and management
4. **IVR**: Interactive Voice Response systems and menu configuration
5. **Voicemail**: Voicemail handling, notifications, and configuration
6. **Recording**: Call recording functionality and playback systems
7. **Conferencing**: Conference room management and bridge configuration
8. **Fax**: Fax server integration and T.38 support
9. **CRM**: Customer Relationship Management integration
10. **Reporting**: Analytics, CDR analysis, and reporting tools
11. **Security**: Security modules, firewall, and access control
12. **Custom**: User-defined custom modules and integrations
13. **Themes**: UI themes and visual customization
14. **Plugins**: Third-party plugin support and API extensions
15. **Addons**: Additional feature extensions and enhancements

## 📞 **Feature Codes**

Comprehensive *number command system for SIP client access:

### **Voicemail & Messages**
- **\*97** - Check Voicemail
- **\*98** - Voicemail Login
- **\*99** - Voicemail Admin

### **Call Forwarding**
- **\*67** - Call Forwarding Enable
- **\*87** - Call Forwarding Disable
- **\*72** - Call Forwarding All
- **\*73** - Call Forwarding Cancel
- **\*21** - Forward All Calls
- **\*61** - Forward No Answer
- **\*62** - Forward Busy

### **Do Not Disturb**
- **\*78** - Do Not Disturb Enable
- **\*79** - Do Not Disturb Disable

### **Call Management**
- **\*69** - Call Return (Last Caller)
- **\*66** - Call Busy Retry
- **\*82** - Call Unblock
- **\*65** - Call Block
- **\*57** - Call Trace
- **\*77** - Anonymous Call Block

### **Conference & Transfer**
- **\*0** - Operator
- **\*43** - Conference Join
- **\*3** - Three-Way Calling
- **\*90** - Busy Override

### **System Features**
- **\*70** - Call Waiting Disable
- **\*71** - Call Waiting Enable
- **\*60** - Call Screening
- **\*80** - Call Pickup
- **\*8** - Direct Call Pickup

## 🏗️ **Enhanced Architecture**

### Main Process (`src/main/`)
- **main.js**: Enhanced entry point with single instance lock and backup operations
- **services/**: Extended backend services for Docker, SSH, DNS, API integration

### Renderer Process (`src/renderer/`)
- **index.html**: Completely reorganized UI with Services, Admin Management, and Logs tabs
- **styles.css**: Comprehensive styling for all new components and accessibility features
- **app.js**: Extended frontend logic with archive support, path management, and API integration
- **preload.js**: Enhanced secure API bridge with backup file operations

### Key Services Enhanced
- **DockerService**: Local Docker installation management with logging
- **SSHService**: Remote server connections and deployments with retry logic
- **NginxService**: Web server configuration with domain previews
- **DNSService**: DNS provider integrations with real-time testing
- **DeploymentService**: Multi-protocol file deployment with progress tracking
- **ArchiveService**: New .flx/.flxx/.mod archive format support
- **APIService**: External API integration and authentication management
- **LoggingService**: Comprehensive logging system with filtering and search

## Security

### Code Signing
- macOS: Apple Developer ID signing and notarization
- Windows: Authenticode signing (planned)

### Sandboxing
- Context isolation enabled
- Node.js integration disabled in renderer
- Secure IPC communication between processes

### Network Security
- All external connections use HTTPS/SSH
- Credentials stored securely using Electron Store
- Support for SSH key-based authentication

## Troubleshooting

### Common Issues

#### Docker Not Found
- Ensure Docker Desktop is installed and running
- Add Docker to your system PATH
- Restart the application after installing Docker

#### SSH Connection Failed
- Verify server credentials and network connectivity
- Check if SSH key permissions are correct (600)
- Ensure server allows SSH connections on specified port

#### DNS Record Creation Failed
- Verify DNS provider credentials
- Check if domain exists in your DNS provider account
- Ensure API rate limits haven't been exceeded

### Logs
Application logs are stored in:
- **macOS**: `~/Library/Logs/FlexPBX Desktop/`
- **Windows**: `%USERPROFILE%\\AppData\\Roaming\\FlexPBX Desktop\\logs\\`
- **Linux**: `~/.config/FlexPBX Desktop/logs/`

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests if applicable
5. Submit a pull request

## License

MIT License - see [LICENSE](../LICENSE) file for details.

## Support

- 📚 Documentation: [GitHub Wiki](https://github.com/raywonder/flexpbx/wiki)
- 🐛 Issues: [GitHub Issues](https://github.com/raywonder/flexpbx/issues)
- 💬 Discussions: [GitHub Discussions](https://github.com/raywonder/flexpbx/discussions)

## 📋 **Archive Formats**

### .flx (FlexPBX Configuration)
Basic configuration archives containing:
- Extension settings and basic configuration
- User preferences and permissions
- Core PBX settings

### .flxx (Extended FlexPBX)
Extended archives including:
- Complete system configuration
- Module settings and customizations
- User data and preferences
- Security settings and credentials

### .mod (Module Archive)
Module-specific archives containing:
- Module code and assets
- Configuration files and dependencies
- Documentation and installation scripts
- Version information and metadata

## 🔗 **Path Structure**

### Default Local Structure
```
~/servers/
├── [PBX-NAME]/
│   ├── modules/
│   │   ├── core/
│   │   ├── extensions/
│   │   ├── trunks/
│   │   ├── ivr/
│   │   ├── voicemail/
│   │   ├── recording/
│   │   ├── conferencing/
│   │   ├── fax/
│   │   ├── crm/
│   │   ├── reporting/
│   │   ├── security/
│   │   ├── custom/
│   │   ├── themes/
│   │   ├── plugins/
│   │   └── addons/
│   ├── backups/
│   ├── config/
│   └── logs/
```

### Remote Server Structure
```
/home/user/app-install-path/server/
├── [PBX-NAME]/
│   ├── modules/[type]/[module-name]/
│   ├── backups/
│   ├── config/
│   └── logs/
```

## 📝 **Changelog**

### Version 1.0.0 (Enhanced Release)
- 🎯 **Full VoiceOver accessibility support** with ARIA attributes and screen reader compatibility
- 🔧 **Single instance enforcement** using `app.requestSingleInstanceLock()`
- 📋 **Complete UI reorganization** with Services, Admin Management, and Logs tabs
- 🔐 **Advanced security features** including 4-64 digit PIN codes and vacation mode
- 📞 **Feature Codes system** with 40+ *number commands for SIP client access
- 📁 **Archive format support** for .flx, .flxx, .mod with file integrity preservation
- 🌐 **API integration** for api.tappedin.fm, api.devinecreations.net, api.devine-creations.com
- 📊 **Comprehensive logging system** with success/failure tracking and metadata
- 🗂️ **WordPress-style module organization** with 15 categories
- 📍 **PBX name-based path management** with real-time previews
- ⚙️ **Per-extension advanced settings** with mini-tabs and configuration options
- 🎨 **Enhanced UI components** with improved styling and navigation

### Version 1.0.0 (Initial Release)
- ✨ Local Docker installation support
- ✨ Remote deployment via SSH, FTP, WebDAV
- ✨ Nginx configuration generator
- ✨ DNS A record management
- ✨ cPanel/WHM compatibility
- ✨ Cross-platform support (macOS, Windows, Linux)
- ✨ Comprehensive server monitoring
- ✨ Basic accessibility features