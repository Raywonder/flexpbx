# FlexPBX Upload Methods Guide

## 📁 Multiple Ways to Upload FlexPBX Package

This guide covers various methods to upload the FlexPBX-Complete-v2.0.0.zip package to your server using different tools and platforms.

---

## 🖥️ GUI Applications (Visual Upload)

### 🍎 macOS Applications

#### Transmit (Premium)
1. **Open Transmit**
2. **Create New Connection:**
   - Protocol: SFTP
   - Server: your-server.com
   - Username: your-username
   - Password: your-password
3. **Navigate to Local File:**
   - Browse to: `/Users/administrator/dev/apps/flex pbx/flexpbx/deployment/build/`
   - Select: `FlexPBX-Complete-v2.0.0.zip`
4. **Upload Destinations:**
   - Home: `/home/username/`
   - Web: `/home/username/public_html/`
   - Custom: `/home/username/flexpbx/`
5. **Drag and Drop** the ZIP file to upload

#### Cyberduck (Free)
1. **Open Cyberduck**
2. **New Connection:**
   - Choose: SFTP (SSH File Transfer Protocol)
   - Server: your-server.com
   - Username: your-username
   - Password: your-password
3. **Connect and Navigate:**
   - Double-click folders to navigate
   - Go to desired upload location
4. **Upload File:**
   - Drag FlexPBX-Complete-v2.0.0.zip from Finder
   - Or use Upload button

#### Mountain Duck (Premium)
1. **Install and Open Mountain Duck**
2. **Add Bookmark:**
   - Protocol: SFTP
   - Server: your-server.com
   - Username/Password: your-credentials
3. **Mount as Drive:**
   - Bookmark appears in Finder sidebar
   - Navigate like local folder
4. **Copy File:**
   - Simply copy/paste ZIP file to desired location
   - Works like regular file operations

### 🪟 Windows Applications

#### WinSCP (Free)
1. **Open WinSCP**
2. **Session Configuration:**
   - File protocol: SFTP
   - Host name: your-server.com
   - User name: your-username
   - Password: your-password
3. **Connect and Upload:**
   - Left panel: Local computer
   - Right panel: Remote server
   - Navigate to ZIP file location
   - Drag from left to right panel

#### FileZilla (Free)
1. **Open FileZilla**
2. **Quick Connect:**
   - Host: sftp://your-server.com
   - Username: your-username
   - Password: your-password
   - Port: 22
3. **File Transfer:**
   - Left side: Local files
   - Right side: Remote server
   - Navigate and drag ZIP file

#### Bitvise SSH Client (Free)
1. **Open Bitvise SSH Client**
2. **Connection Setup:**
   - Host: your-server.com
   - Username: your-username
   - Authentication: Password
3. **SFTP Browser:**
   - Built-in file browser opens
   - Navigate to upload destination
   - Upload ZIP file

---

## 🌐 Web-Based Upload Methods

### Method 1: Direct wget from GitHub (Recommended)
```bash
# SSH to your server first
ssh username@your-server.com

# Download directly to server
cd /home/username
wget https://github.com/Raywonder/flexpbx/releases/download/v2.0.0/FlexPBX-Complete-v2.0.0.zip

# Or to public_html
cd /home/username/public_html
wget https://github.com/Raywonder/flexpbx/releases/download/v2.0.0/FlexPBX-Complete-v2.0.0.zip
```

### Method 2: Upload via cPanel File Manager
1. **Login to cPanel**
2. **Open File Manager**
3. **Navigate to Directory:**
   - Home Directory: `/home/username/`
   - Public HTML: `public_html/`
4. **Upload File:**
   - Click "Upload" button
   - Select FlexPBX-Complete-v2.0.0.zip
   - Wait for upload to complete
5. **Extract (Optional):**
   - Right-click ZIP file
   - Select "Extract"

### Method 3: Upload via Web Terminal
```bash
# Using cPanel Terminal or Web SSH
cd /home/username

# Upload from URL (if package is hosted)
curl -O https://your-domain.com/downloads/FlexPBX-Complete-v2.0.0.zip

# Or upload via wget with progress
wget --progress=bar https://your-domain.com/downloads/FlexPBX-Complete-v2.0.0.zip
```

### Method 4: FTP Web Interface
Many hosting providers offer web-based FTP:
1. **Access Web FTP** (usually in hosting control panel)
2. **Navigate to Upload Directory**
3. **Use Upload Button** to select ZIP file
4. **Monitor Upload Progress**

---

## 📱 Mobile Upload Methods

### iOS (iPhone/iPad)
#### Using Termius
1. **Install Termius** from App Store
2. **Add Host:**
   - Address: your-server.com
   - Username: your-username
   - Password: your-password
3. **Upload via SFTP:**
   - Connect to host
   - Use built-in file browser
   - Upload from Files app

#### Using Working Copy + SSH
1. **Upload ZIP to iCloud Drive** first
2. **Use SSH app** to connect to server
3. **Download from iCloud URL**:
```bash
wget "https://your-icloud-share-url/FlexPBX-Complete-v2.0.0.zip"
```

### Android
#### Using JuiceSSH
1. **Install JuiceSSH** from Play Store
2. **Create Connection**
3. **Use SFTP Plugin** for file uploads
4. **Upload from device storage**

---

## ☁️ Cloud Storage Upload Methods

### Google Drive Upload
1. **Upload ZIP to Google Drive**
2. **Get Shareable Link**
3. **Download on Server:**
```bash
# SSH to server
ssh username@your-server.com

# Install gdown if needed
pip install gdown

# Download from Google Drive
gdown https://drive.google.com/uc?id=YOUR_FILE_ID
```

### Dropbox Upload
1. **Upload to Dropbox**
2. **Get Direct Link**
3. **Download on Server:**
```bash
# SSH to server
cd /home/username

# Download from Dropbox
wget -O FlexPBX-Complete-v2.0.0.zip "https://www.dropbox.com/s/your-link/FlexPBX-Complete-v2.0.0.zip?dl=1"
```

### OneDrive Upload
1. **Upload to OneDrive**
2. **Generate Download Link**
3. **Download via wget/curl**

---

## 🚀 Automated Upload Scripts

### macOS Upload Script
```bash
#!/bin/bash
# upload-flexpbx.sh

SERVER="your-server.com"
USERNAME="your-username"
UPLOAD_PATH="/home/$USERNAME/"
LOCAL_FILE="/Users/administrator/dev/apps/flex pbx/flexpbx/deployment/build/FlexPBX-Complete-v2.0.0.zip"

echo "Uploading FlexPBX package to $SERVER..."

# Method 1: SCP upload
scp "$LOCAL_FILE" "$USERNAME@$SERVER:$UPLOAD_PATH"

# Method 2: rsync with progress
# rsync -avP "$LOCAL_FILE" "$USERNAME@$SERVER:$UPLOAD_PATH"

echo "Upload complete!"
echo "SSH to server and run:"
echo "cd $UPLOAD_PATH && unzip FlexPBX-Complete-v2.0.0.zip"
```

### Windows Upload Script (PowerShell)
```powershell
# upload-flexpbx.ps1

$server = "your-server.com"
$username = "your-username"
$localFile = "C:\path\to\FlexPBX-Complete-v2.0.0.zip"
$remotePath = "/home/$username/"

# Using WinSCP PowerShell module
Import-Module WinSCP

$sessionOptions = New-WinSCPSessionOption -Protocol Sftp -HostName $server -UserName $username

$session = New-WinSCPSession -SessionOption $sessionOptions
$transferResult = Send-WinSCPItem -WinSCPSession $session -Path $localFile -Destination $remotePath
Remove-WinSCPSession -WinSCPSession $session

Write-Host "Upload completed to $server"
```

---

## 🔧 Upload Destinations by Server Type

### Shared Hosting
```bash
# Typical shared hosting paths
/home/username/                    # Home directory
/home/username/public_html/        # Web accessible
/home/username/public_html/files/  # Downloads folder
/home/username/tmp/                # Temporary files
```

### VPS/Dedicated Server
```bash
# Full server access paths
/root/                            # Root user home
/home/username/                   # Regular user home
/opt/                            # Optional software
/tmp/                            # Temporary storage
/var/www/html/                   # Web root (if applicable)
```

### cPanel Hosting
```bash
# cPanel specific paths
/home/username/                   # Account home
/home/username/public_html/       # Main domain
/home/username/public_html/subdomain/ # Subdomain folder
/home/username/mail/              # Email storage
```

---

## 📊 Upload Progress Monitoring

### Command Line Progress
```bash
# rsync with progress bar
rsync -avP FlexPBX-Complete-v2.0.0.zip username@server.com:/home/username/

# wget with progress
wget --progress=bar:force https://your-url.com/FlexPBX-Complete-v2.0.0.zip

# curl with progress
curl -o FlexPBX-Complete-v2.0.0.zip -# https://your-url.com/package.zip
```

### Check Upload Status
```bash
# Check if file exists on server
ssh username@server.com "ls -la /home/username/FlexPBX-Complete-v2.0.0.zip"

# Verify file size
ssh username@server.com "du -h /home/username/FlexPBX-Complete-v2.0.0.zip"

# Check file integrity
ssh username@server.com "unzip -t /home/username/FlexPBX-Complete-v2.0.0.zip"
```

---

## ⚡ Quick Upload Commands

### One-Line Upload Solutions

#### SCP Upload
```bash
scp /Users/administrator/dev/apps/flex\ pbx/flexpbx/deployment/build/FlexPBX-Complete-v2.0.0.zip username@server.com:/home/username/
```

#### Direct wget (if hosted)
```bash
ssh username@server.com "cd /home/username && wget https://releases.flexpbx.com/v2.0.0/FlexPBX-Complete-v2.0.0.zip"
```

#### Compressed Transfer
```bash
# Compress and upload simultaneously
tar czf - -C "/Users/administrator/dev/apps/flex pbx/flexpbx/deployment/build" FlexPBX-Complete-v2.0.0.zip | ssh username@server.com "cd /home/username && tar xzf -"
```

---

## 📋 Upload Verification Checklist

### ✅ Pre-Upload
- [ ] File exists locally
- [ ] File size is ~667MB
- [ ] Server connection tested
- [ ] Upload destination confirmed

### ✅ During Upload
- [ ] Progress monitoring active
- [ ] Connection stable
- [ ] No error messages
- [ ] Sufficient disk space

### ✅ Post-Upload
- [ ] File exists on server
- [ ] File size matches original
- [ ] ZIP file is not corrupted
- [ ] Extract test successful

### ✅ Ready for Installation
- [ ] File extracted successfully
- [ ] install.sh is executable
- [ ] All required files present
- [ ] Installation ready to begin

This comprehensive guide ensures you can upload FlexPBX using any method that works best for your setup and preferences!