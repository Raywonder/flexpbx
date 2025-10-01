# CopyParty Upload Guide for FlexPBX Desktop v1.0.0

## 📁 Manual Upload Instructions

Since the automated CopyParty upload API has changed, here's how to manually upload the FlexPBX Desktop installers:

### 🎯 Target Locations

**User Apps Folder**:
- `https://files.raywonderis.me/tappedin/apps/FlexPBX/`
- `https://files.raywonderis.me/devinecr/apps/FlexPBX/`

**Public Downloads**:
- `https://files.raywonderis.me/public/downloads/FlexPBX/`

### 📦 Files to Upload

All installers are located in `/Users/administrator/dev/apps/FlexPBX/desktop-app/dist/`:

1. **FlexPBX Desktop-1.0.0.dmg** (97MB) - macOS Intel
2. **FlexPBX Desktop-1.0.0-arm64.dmg** (93MB) - macOS Apple Silicon
3. **FlexPBX Desktop Setup 1.0.0.exe** (79MB) - Windows x64 Installer
4. **FlexPBX Desktop-1.0.0-win.zip** (102MB) - Windows Portable
5. **FlexPBX Desktop-1.0.0.AppImage** (103MB) - Linux x64

### 🔧 Manual Upload Steps

1. **Access CopyParty Web Interface**:
   - Go to `https://files.raywonderis.me`
   - Login with credentials:
     - Username: `tappedin` or `devinecr`
     - Password: `hub-node-api-2024`

2. **Create FlexPBX Folder**:
   - Navigate to `apps/` directory
   - Create new folder named `FlexPBX`

3. **Upload Installers**:
   - Enter the `FlexPBX` folder
   - Upload all 5 installer files
   - Optionally also upload to `/public/downloads/FlexPBX/` for public access

### 🌐 Final Download URLs

Once uploaded, the installers will be available at:

**User Apps (tappedin)**:
- https://files.raywonderis.me/tappedin/apps/FlexPBX/FlexPBX%20Desktop-1.0.0.dmg
- https://files.raywonderis.me/tappedin/apps/FlexPBX/FlexPBX%20Desktop-1.0.0-arm64.dmg
- https://files.raywonderis.me/tappedin/apps/FlexPBX/FlexPBX%20Desktop%20Setup%201.0.0.exe
- https://files.raywonderis.me/tappedin/apps/FlexPBX/FlexPBX%20Desktop-1.0.0-win.zip
- https://files.raywonderis.me/tappedin/apps/FlexPBX/FlexPBX%20Desktop-1.0.0.AppImage

**Public Downloads**:
- https://files.raywonderis.me/public/downloads/FlexPBX/FlexPBX%20Desktop-1.0.0.dmg
- https://files.raywonderis.me/public/downloads/FlexPBX/FlexPBX%20Desktop-1.0.0-arm64.dmg
- https://files.raywonderis.me/public/downloads/FlexPBX/FlexPBX%20Desktop%20Setup%201.0.0.exe
- https://files.raywonderis.me/public/downloads/FlexPBX/FlexPBX%20Desktop-1.0.0-win.zip
- https://files.raywonderis.me/public/downloads/FlexPBX/FlexPBX%20Desktop-1.0.0.AppImage

### 💡 API Issue Notes

The automated upload failed because:
- CopyParty API expects an "act" field but rejects common values like "up", "upload", "put"
- The API may have changed or requires specific configuration
- The `/FlexPBX/` folder may need to exist before uploading

### 🔍 For Future Automation

To fix the automated upload:
1. Check current CopyParty documentation for correct API actions
2. Verify folder permissions and existence
3. Test with CopyParty admin interface to see what actions are accepted
4. Consider using different upload method (WebDAV, SFTP, etc.)

---

**Total Size**: ~473MB for all installers
**Platform Support**: macOS (Intel & Apple Silicon), Windows x64, Linux x64