# FlexPBX WHMCS Module Installation

## Automatic Installation (via main installer)
The main installer will automatically detect and install the WHMCS module if WHMCS is found.

## Manual Installation

1. **Upload Module Files**
   - Upload the `flexpbx` folder to your WHMCS `/modules/addons/` directory
   - Ensure proper file permissions (644 for files, 755 for directories)

2. **Activate Module**
   - Log into WHMCS Admin Area
   - Go to `Setup > Addon Modules`
   - Find "FlexPBX Management" and click "Activate"
   - Configure the module settings:
     - FlexPBX Server URL: `http://your-server.com:3000`
     - API Key: (generated during server installation)
     - Enable 2FA Integration: Yes (recommended)

3. **Setup 2FA Integration**
   - Go to the module page
   - Click "2FA Setup" in the sidebar
   - Configure your control panel type and credentials
   - Test the configuration

4. **Desktop Integration**
   - Go to "Desktop Integration" in the module
   - Generate an integration token
   - Use the token or QR code to connect your desktop app

## Features

- ✅ Complete PBX account management
- ✅ Extension provisioning and management
- ✅ 2FA authentication with WHMCS
- ✅ Desktop application integration
- ✅ Real-time statistics and monitoring
- ✅ Automated account provisioning
- ✅ Customer self-service portal

## Support

For support and documentation, visit: https://docs.flexpbx.com
