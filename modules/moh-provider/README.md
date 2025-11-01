# FlexPBX MOH Provider Service

## Public Music On Hold Streaming for Asterisk-Based Systems

**Version:** 1.0.0
**Provider:** Devine Creations
**Service URL:** https://flexpbx.devinecreations.net/api/moh-provider.php
**Public Page:** https://flexpbx.devinecreations.net/modules/moh-provider/

---

## 🎵 About This Service

FlexPBX MOH Provider is a **free, public music on hold streaming service** designed for Asterisk, FreePBX, and FlexPBX installations worldwide. Our mission is to provide high-quality, accessibility-focused hold music to any PBX system.

### Key Features

- ✨ **Audio Described Content** - Includes TV shows and movies with audio descriptions
- 🆓 **Completely Free** - No registration, no fees, no limits
- 🔊 **High Quality** - Up to 192kbps streaming
- 🌐 **HTTPS Secure** - Encrypted streaming for privacy
- ♿ **Accessibility First** - Designed for inclusive experiences
- 📡 **99.9% Uptime** - Reliable streaming infrastructure
- 🔌 **Easy Integration** - Simple API and configuration

---

## 📡 Available Streams

### 1. Raywonder Radio (Featured)
**Class Name:** `raywonder-radio`
**Stream URL:** https://stream.raywonderis.me/jellyfin-radio
**Type:** Audio Described Content

**Content:**
- 2,424 TV Episodes (Audio Described)
- 538 Movies (Audio Described)
- 201 Doctor Who Audiobooks
- 87 Music Tracks

**Schedule:**
- Even Hours: TV Episodes
- Odd Hours: Movies
- Music: Between programs

**Accessibility:**
- Audio description for visual content
- Screen reader optimized
- Visual impairment friendly

### 2. TappedIn Radio
**Class Name:** `tappedin-radio`
**Stream URL:** https://stream.tappedin.fm/tappedin-radio
**Type:** Meditation & Wellness

**Content:**
- Relaxing soundscapes
- Meditation music
- Calming podcasts
- Ambient sounds

### 3. ChrisMix Radio
**Class Name:** `chrismix-radio`
**Stream URL:** http://s23.myradiostream.com:9372/
**Type:** Music Variety

**Content:**
- Curated music playlists
- Various genres
- Continuous streaming

### 4. SoulFood Radio
**Class Name:** `soulfood-radio`
**Stream URL:** http://s38.myradiostream.com:15874
**Type:** Soul & R&B

**Content:**
- Soul music
- R&B classics
- Contemporary hits

---

## 🚀 Installation

### Quick Install (1 Command)

```bash
curl -s "https://flexpbx.devinecreations.net/api/moh-provider.php?action=config&format=asterisk" >> /etc/asterisk/musiconhold.conf && asterisk -rx "moh reload"
```

### Manual Installation

1. **Download Configuration**
```bash
curl -o /tmp/flexpbx-moh.conf "https://flexpbx.devinecreations.net/api/moh-provider.php?action=config&format=asterisk"
```

2. **Review Configuration**
```bash
cat /tmp/flexpbx-moh.conf
```

3. **Append to Asterisk Config**
```bash
cat /tmp/flexpbx-moh.conf >> /etc/asterisk/musiconhold.conf
```

4. **Reload MOH Module**
```bash
asterisk -rx "moh reload"
```

5. **Verify Installation**
```bash
asterisk -rx "moh show classes"
```

---

## 🔧 Configuration Examples

### Use as Default MOH

```ini
[default]
mode=custom
application=/usr/bin/ffmpeg -i https://stream.raywonderis.me/jellyfin-radio -f s16le -ar 8000 -ac 1 -
format=slin
```

### Set Per-Extension

In `pjsip.conf`:
```ini
[extension_number]
type=endpoint
...
moh_suggest=raywonder-radio
```

### Use in Dialplan

```
exten => 100,1,Set(CHANNEL(musicclass)=tappedin-radio)
exten => 100,2,Dial(PJSIP/2000,30)
```

### Use in Queue

```ini
[support-queue]
musicclass=raywonder-radio
```

---

## 📚 API Documentation

### Base URL
```
https://flexpbx.devinecreations.net/api/moh-provider.php
```

### Endpoints

#### 1. Provider Information
```
GET ?action=info
```

**Response:**
```json
{
  "provider": {
    "name": "FlexPBX MOH Provider",
    "version": "1.0.0",
    "streams_available": 4
  },
  "features": {
    "audio_described_content": true,
    "free_tier": true
  }
}
```

#### 2. List All Streams
```
GET ?action=list
```

**Response:**
```json
{
  "success": true,
  "total_streams": 4,
  "streams": [...]
}
```

#### 3. Search Streams
```
GET ?action=search&q=meditation
GET ?action=search&category=music
```

#### 4. Get Asterisk Configuration
```
GET ?action=config&format=asterisk
GET ?action=config&format=json
GET ?action=config&stream=raywonder-radio
```

#### 5. Installation Instructions
```
GET ?action=install
```

#### 6. Module Manifest
```
GET ?action=module-manifest
```

#### 7. Health Check
```
GET ?action=health
```

---

## 💻 Integration Examples

### Bash Script
```bash
#!/bin/bash
# Install FlexPBX MOH streams

echo "Installing FlexPBX MOH Provider streams..."

# Download configuration
curl -s "https://flexpbx.devinecreations.net/api/moh-provider.php?action=config&format=asterisk" >> /etc/asterisk/musiconhold.conf

# Reload Asterisk
asterisk -rx "moh reload"

# Verify
asterisk -rx "moh show classes" | grep -E "(raywonder|tappedin|chrismix|soulfood)"

echo "Installation complete!"
```

### PHP Integration
```php
<?php
// Fetch available streams
$api_url = 'https://flexpbx.devinecreations.net/api/moh-provider.php?action=list';
$response = file_get_contents($api_url);
$data = json_decode($response, true);

foreach ($data['streams'] as $stream) {
    echo "Stream: {$stream['display_name']}\n";
    echo "URL: {$stream['url']}\n";
    echo "Description: {$stream['description']}\n\n";
}
?>
```

### Python Integration
```python
import requests

# Get available streams
api_url = 'https://flexpbx.devinecreations.net/api/moh-provider.php'
response = requests.get(api_url, params={'action': 'list'})
data = response.json()

for stream in data['streams']:
    print(f"Stream: {stream['display_name']}")
    print(f"URL: {stream['url']}")
    print(f"Tags: {', '.join(stream['tags'])}\n")
```

---

## 🔍 Module Discovery

### For Module Managers

If you're building a module manager or extension marketplace, you can discover our service:

**Manifest Endpoint:**
```
GET https://flexpbx.devinecreations.net/api/moh-provider.php?action=module-manifest
```

**Response includes:**
- Module metadata
- Compatibility information
- Dependencies
- Installation instructions
- API endpoints

### Search Integration

Allow users to search for MOH providers:

```bash
# Search for audio-described content
curl "https://flexpbx.devinecreations.net/api/moh-provider.php?action=search&q=audio-described"

# Search by category
curl "https://flexpbx.devinecreations.net/api/moh-provider.php?action=search&category=meditation"
```

---

## 📋 Requirements

### System Requirements
- Asterisk 16.x, 18.x, or 20.x
- Linux-based operating system
- Internet connection with HTTPS outbound access

### Software Dependencies
- **ffmpeg** - Required for HTTPS streams (raywonder-radio, tappedin-radio)
- **mpg123** - Required for HTTP streams (chrismix-radio, soulfood-radio)
- **res_musiconhold.so** - Asterisk MOH module

### Install Dependencies

**Debian/Ubuntu:**
```bash
apt-get update
apt-get install -y ffmpeg mpg123
```

**RHEL/CentOS/AlmaLinux:**
```bash
dnf install -y ffmpeg mpg123
```

---

## 🧪 Testing

### Test Stream Connectivity
```bash
# Test Raywonder Radio
ffmpeg -i https://stream.raywonderis.me/jellyfin-radio -t 10 -f null -

# Test TappedIn Radio
ffmpeg -i https://stream.tappedin.fm/tappedin-radio -t 10 -f null -

# Test ChrisMix Radio
mpg123 -t 10 http://s23.myradiostream.com:9372/
```

### Test in Asterisk
```bash
# Show all MOH classes
asterisk -rx "moh show classes"

# Test specific class
asterisk -rx "moh show classes" | grep raywonder-radio
```

### API Health Check
```bash
curl "https://flexpbx.devinecreations.net/api/moh-provider.php?action=health"
```

---

## 🆘 Support

### Documentation
- Public Page: https://flexpbx.devinecreations.net/modules/moh-provider/
- API Docs: https://flexpbx.devinecreations.net/api/moh-provider.php?action=info

### Contact
- Email: admin@devinecreations.net
- Website: https://devinecreations.net

### Common Issues

**Q: Streams not loading after installation?**
A: Ensure ffmpeg and mpg123 are installed, and check firewall rules allow outbound HTTPS.

**Q: Audio quality issues?**
A: All streams are transcoded to 8kHz mono for phone compatibility. This is normal for MOH.

**Q: Can I use this commercially?**
A: Yes! The service is free for all use cases, commercial and non-commercial.

**Q: How do I change the default MOH?**
A: Edit the `[default]` class in musiconhold.conf to point to your preferred stream.

---

## 📜 License

This service is provided **free of charge** for all Asterisk-based PBX installations.

**Terms:**
- Free for commercial and non-commercial use
- No registration required
- No usage limits
- No SLA guarantees (best effort 99.9% uptime)
- Stream content may change without notice
- Service availability not guaranteed

---

## 🎯 Use Cases

- **Call Centers** - Reduce on-hold frustration with quality music
- **Accessibility Services** - Audio described content for visually impaired callers
- **Medical Offices** - Calming meditation music for patient comfort
- **Business Lines** - Professional music for corporate image
- **Educational Institutions** - Safe, appropriate content
- **Non-Profits** - Free MOH solution for budget-conscious organizations

---

## 🔄 Updates

**Version 1.0.0 - October 2025**
- Initial public release
- 4 streams available
- RESTful API
- Module manifest support
- Accessibility-focused content

---

## 🙏 Acknowledgments

Powered by:
- Icecast streaming server
- FFmpeg transcoding
- Jellyfin media server
- Asterisk PBX platform

---

**Made with ❤️ by Devine Creations**
Providing accessible, high-quality audio experiences for everyone.
