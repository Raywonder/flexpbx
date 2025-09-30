# FlexPBX Desktop

A cross-platform desktop application for managing FlexPBX installations and deployments.

## Features

### 🚀 Local Installations
- **Docker Integration**: Seamlessly install FlexPBX locally using Docker
- **Auto Configuration**: Automatically generates docker-compose.yml and environment files
- **Service Management**: Start, stop, and monitor Docker services directly from the app

### 🌐 Remote Deployments
- **Multiple Protocols**: Deploy via SSH, FTP, or WebDAV
- **Server Compatibility**: Supports standard Linux servers and cPanel/WHM environments
- **Automated Setup**: Handles file uploads, configuration generation, and service startup

### ⚙️ Nginx Configuration
- **Visual Config Builder**: Generate Nginx configurations through an intuitive interface
- **Multiple Deployment Types**: Support for root domain, subdomain, and subdirectory installations
- **SSL Integration**: Built-in Let's Encrypt SSL certificate management
- **cPanel Compatibility**: Detects and configures for cPanel/WHM environments

### 🌍 DNS Management
- **Multi-Provider Support**: Cloudflare, DigitalOcean, Namecheap, GoDaddy
- **A Record Creation**: Automatically create DNS A records for your installations
- **Verification**: Built-in DNS propagation verification
- **Public IP Detection**: Automatically detects your public IP address

### 📊 Server Monitoring
- **Real-time Status**: Monitor server health and service status
- **Log Viewing**: Access Docker container logs directly from the app
- **Installation Management**: Centralized view of all your FlexPBX installations

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

## Architecture

### Main Process (`src/main/`)
- **main.js**: Entry point and window management
- **services/**: Backend services for Docker, SSH, DNS, etc.

### Renderer Process (`src/renderer/`)
- **index.html**: Main UI layout
- **styles.css**: Application styling
- **app.js**: Frontend application logic
- **preload.js**: Secure API bridge

### Key Services
- **DockerService**: Local Docker installation management
- **SSHService**: Remote server connections and deployments
- **NginxService**: Web server configuration
- **DNSService**: DNS provider integrations
- **DeploymentService**: Multi-protocol file deployment

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

## Changelog

### Version 1.0.0 (Initial Release)
- ✨ Local Docker installation support
- ✨ Remote deployment via SSH, FTP, WebDAV
- ✨ Nginx configuration generator
- ✨ DNS A record management
- ✨ cPanel/WHM compatibility
- ✨ Cross-platform support (macOS, Windows, Linux)
- ✨ Comprehensive server monitoring
- ✨ Accessibility features and WCAG 2.1 AA compliance