# Two-Factor Authentication Setup Guide

FlexPBX supports 2FA integration with popular control panels for enhanced security.

## Supported Control Panels

- ✅ WHMCS - Admin area 2FA
- ✅ cPanel - User account 2FA
- ✅ WHM - Root/Reseller 2FA
- ✅ DirectAdmin - All user levels
- ✅ Plesk - Administrator 2FA

## Setup Process

### 1. Enable 2FA in Control Panel

#### WHMCS
1. Login to WHMCS Admin Area
2. Go to `Setup > Staff Management > Administrators`
3. Edit your admin account
4. Enable "Two-Factor Authentication"
5. Scan QR code with authenticator app
6. Save the secret key for FlexPBX

#### cPanel
1. Login to cPanel
2. Go to `Security > Two-Factor Authentication`
3. Click "Set Up Two-Factor Authentication"
4. Scan QR code with authenticator app
5. Note the secret key for FlexPBX

#### WHM
1. Login to WHM
2. Go to `Home > Clusters > Remote Access Key`
3. Enable "Two-Factor Authentication"
4. Configure with authenticator app
5. Save secret key for FlexPBX

#### DirectAdmin
1. Login to DirectAdmin
2. Go to `Account Manager > Two-Factor Authentication`
3. Enable 2FA
4. Configure with authenticator app
5. Note secret key for FlexPBX

#### Plesk
1. Login to Plesk
2. Go to `Account > Interface Language`
3. Enable "Two-step verification"
4. Configure with authenticator app
5. Save secret key for FlexPBX

### 2. Configure FlexPBX Integration

#### Via WHMCS Module
1. Access WHMCS Admin Area
2. Go to `Setup > Addon Modules > FlexPBX`
3. Click "2FA Setup" in sidebar
4. Select control panel type
5. Enter server URL and credentials
6. Add 2FA secret key
7. Test configuration
8. Save settings

#### Via FlexPBX Web Interface
1. Access FlexPBX at http://your-server:3000
2. Login as admin
3. Go to `Settings > Authentication`
4. Add new 2FA provider
5. Configure panel type and credentials
6. Test and save

#### Via Desktop Application
1. Open FlexPBX Desktop
2. Go to `Settings > Authentication`
3. Add 2FA Provider
4. Configure connection details
5. Test authentication
6. Save configuration

### 3. Test 2FA Authentication

#### Automatic Token Generation
If secret key is configured, FlexPBX will automatically generate TOTP tokens.

#### Manual Token Entry
For testing or troubleshooting, you can manually enter tokens from your authenticator app.

#### Verification Process
1. FlexPBX detects login attempt
2. Retrieves 2FA token (auto or manual)
3. Submits credentials + token to control panel
4. Receives authentication response
5. Creates secure session

## Configuration Examples

### WHMCS Configuration
```json
{
  "panel_type": "whmcs",
  "server_url": "https://your-whmcs.com",
  "username": "admin",
  "password": "your_password",
  "tfa_secret": "JBSWY3DPEHPK3PXP",
  "auth_endpoint": "/admin/login.php"
}
```

### cPanel Configuration
```json
{
  "panel_type": "cpanel",
  "server_url": "https://your-server.com:2083",
  "username": "cpanel_user",
  "password": "cpanel_password",
  "tfa_secret": "JBSWY3DPEHPK3PXP",
  "auth_endpoint": "/login/?login_only=1"
}
```

### DirectAdmin Configuration
```json
{
  "panel_type": "directadmin",
  "server_url": "https://your-server.com:2222",
  "username": "admin",
  "password": "admin_password",
  "tfa_secret": "JBSWY3DPEHPK3PXP",
  "auth_endpoint": "/CMD_LOGIN"
}
```

## Security Best Practices

### 1. Secret Key Management
- Store secret keys securely
- Use different secrets for each service
- Rotate secrets periodically
- Never share secret keys

### 2. Password Security
- Use strong, unique passwords
- Enable password encryption in FlexPBX
- Consider using API keys where supported
- Implement password rotation

### 3. Access Control
- Limit 2FA configuration to administrators
- Use role-based access control
- Monitor authentication logs
- Set session timeouts

### 4. Network Security
- Use HTTPS for all connections
- Implement IP restrictions where possible
- Use VPN for administrative access
- Monitor failed authentication attempts

## Troubleshooting

### Common Issues

#### "Invalid 2FA Token"
- Check time synchronization on server
- Verify secret key is correct
- Ensure control panel 2FA is working
- Try manual token entry

#### "Authentication Failed"
- Verify username/password
- Check server URL and ports
- Test control panel login manually
- Review authentication logs

#### "Connection Timeout"
- Check network connectivity
- Verify firewall settings
- Test DNS resolution
- Check SSL certificate validity

### Debug Mode
Enable debug logging in FlexPBX settings:
```bash
# Enable debug logging
echo "DEBUG=true" >> /opt/flexpbx/.env

# Restart FlexPBX
sudo systemctl restart flexpbx

# Check logs
tail -f /opt/flexpbx/logs/auth.log
```

### Testing Tools
```bash
# Test 2FA token generation
node -e "console.log(require('crypto').createHmac('sha1', Buffer.from('JBSWY3DPEHPK3PXP', 'base32')).update(Buffer.from([0,0,0,0,Math.floor(Date.now()/30000)])).digest().slice(-4).readUInt32BE(0) % 1000000)"

# Test control panel connectivity
curl -v https://your-panel.com/login

# Test FlexPBX 2FA endpoint
curl -X POST http://localhost:3000/api/auth/2fa/test \
  -H "Content-Type: application/json" \
  -d '{"panel_type":"whmcs","token":"123456"}'
```

## Support

For 2FA setup assistance:
- 📚 Documentation: https://docs.flexpbx.com/2fa
- 🎫 Support: https://support.flexpbx.com
- 💬 Community: https://community.flexpbx.com/2fa
