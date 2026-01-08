# AzuraCast Integration for FlexPBX Hold Music

## Overview
AzuraCast provides streaming radio stations that can be used for Music On Hold in FlexPBX.

## TappedIn Radio Stream URLs
- Primary: https://stream.tappedin.fm/listen/tappedin_radio/radio.mp3
- Backup: http://64.20.46.178:8000/listen/tappedin_radio/radio.mp3

## Setting Up AzuraCast

### Quick Install (Docker)
```bash
mkdir -p /var/azuracast
cd /var/azuracast
curl -fsSL https://raw.githubusercontent.com/AzuraCast/AzuraCast/main/docker.sh > docker.sh
chmod a+x docker.sh
./docker.sh install
```

### Admin Access
- URL: https://stream.tappedin.fm/admin
- Default port: 80/443 for web, 8000+ for streams

### FlexPBX Integration
Add to musiconhold.conf:
```
[tappedin-stream]
mode=custom
application=/usr/bin/ffmpeg -i https://stream.tappedin.fm/listen/tappedin_radio/radio.mp3 -f s16le -ar 8000 -ac 1 -
format=slin
```

### Requirements
- ffmpeg installed: yum install ffmpeg
- Network access to stream URL

## Fallback to Local Files
If streaming fails, FlexPBX uses local files in /var/lib/asterisk/moh/

## Contact
- Support: support@devine-creations.com
- AzuraCast: https://www.azuracast.com/
