# FlexPBX Project Structure

## Unified Architecture

```
flexpbx/
├── client/                    # Desktop Client Application
│   ├── src/
│   │   ├── main/             # Electron main process
│   │   │   ├── services/     # Core services
│   │   │   ├── installers/   # Auto-installers
│   │   │   └── integrations/ # WHMCS/cPanel integrations
│   │   └── renderer/         # UI components
│   │       ├── admin/        # Admin interface
│   │       └── user/         # User interface
│   └── dist/                 # Built applications
│
├── server/                   # Server Backend
│   ├── core/                 # Core PBX functionality
│   │   ├── asterisk/        # Asterisk configuration
│   │   ├── sip/             # SIP server
│   │   └── voicemail/       # Voicemail system
│   ├── api/                 # REST API
│   ├── integrations/        # Third-party integrations
│   │   ├── whmcs/          # WHMCS modules
│   │   ├── cpanel/         # cPanel API
│   │   └── whm/            # WHM integration
│   └── installer/           # Server installation scripts
│
├── shared/                  # Shared utilities
│   ├── config/             # Configuration templates
│   ├── protocols/          # Protocol implementations
│   └── security/           # Security utilities
│
└── deployment/             # Deployment configurations
    ├── nginx/              # Nginx configurations
    ├── docker/             # Docker configurations
    └── scripts/            # Installation scripts
```

## Key Features

1. **Auto-Installation System**: Detects and installs missing dependencies
2. **Multi-Protocol Support**: SSH, SFTP, WebDAV, SCP
3. **Platform Integration**: WHMCS, WHM, cPanel
4. **Flexible Deployment**: Local Docker or remote server
5. **Accessibility**: Full AccessKit.dev integration