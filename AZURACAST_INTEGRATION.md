# FlexPBX AzuraCast Integration
## Music on Hold & Conference Room Audio with AzuraCast Media Libraries

## Overview

FlexPBX integrates with AzuraCast radio automation platform to provide:
- Dynamic music on hold from curated media libraries
- Conference room background music
- Multiple station options (TappedIn Radio, Raywonder Radio)
- Real-time now playing information
- Schedule-based content delivery

---

## AzuraCast Stations Available

### 1. TappedIn Radio
- **Stream URL**: https://stream.tappedin.fm/radio/8000/tappedin-radio
- **API Base**: https://stream.tappedin.fm/api
- **Station ID**: tappedin-radio
- **Format**: MP3 192kbps
- **Content**: Soundscapes, Meditation Music, Podcasts
- **Schedule**: 24/7 automated programming

### 2. Raywonder Radio (Dom)
- **Stream URL**: https://stream.raywonderis.me/radio/8000/raywonder-radio
- **API Base**: https://stream.raywonderis.me/api
- **Station ID**: raywonder-radio
- **Format**: MP3 192kbps
- **Content**: Audio Described Content, Music, Educational
- **Schedule**: 24/7 automated programming

### 3. ChrisMix Radio (Christmas)
- **Stream URL**: http://s23.myradiostream.com:9372/
- **Format**: MP3 128kbps
- **Content**: Christmas Music Mix
- **Note**: External stream (non-AzuraCast)

---

## Music on Hold Configuration

### Updated Configuration

File: `/etc/asterisk/musiconhold.conf`

```ini
;
; FlexPBX Music on Hold Configuration
; Integrated with AzuraCast Media Libraries
;

[general]
cachertclasses=yes
preferchannelclass=yes

; Default MOH - TappedIn Radio (Soundscapes & Meditation)
[default]
mode=custom
application=/usr/bin/ffmpeg -i https://stream.tappedin.fm/radio/8000/tappedin-radio -f s16le -ar 8000 -ac 1 -
format=slin
; TappedIn Radio - Soundscapes, Meditation Music & Podcasts

; TappedIn Radio - Explicit Class
[tappedin-radio]
mode=custom
application=/usr/bin/ffmpeg -i https://stream.tappedin.fm/radio/8000/tappedin-radio -f s16le -ar 8000 -ac 1 -
format=slin
; TappedIn Radio via AzuraCast

; Raywonder Radio - Audio Described Content (Dom)
[raywonder-radio]
mode=custom
application=/usr/bin/ffmpeg -i https://stream.raywonderis.me/radio/8000/raywonder-radio -f s16le -ar 8000 -ac 1 -
format=slin
; Raywonder Radio - Audio Described Content, Music, Educational

; ChrisMix Radio - Christmas Music
[christmas]
mode=custom
application=/usr/bin/mpg123 -q -r 8000 --mono -s http://s23.myradiostream.com:9372/
; ChrisMix Radio - Christmas Music Mix

; Local Files - Fallback if streams unavailable
[files]
mode=files
directory=/var/lib/asterisk/moh/default
sort=random
format=wav

; Quiet - Minimal hold music for professional environments
[quiet]
mode=files
directory=/var/lib/asterisk/moh/default
sort=alpha
format=wav
volume=70
```

---

## Dialplan Integration

### Extension 00 - Music on Hold Test
Updated to use TappedIn Radio by default:

```ini
; Music On Hold Test - Dial 00
exten => _00,1,NoOp(Music On Hold - TappedIn Radio)
 same => n,Answer()
 same => n,Playback(please-hold)
 same => n,MusicOnHold(default)
 same => n,Hangup()
```

### Conference Rooms with Pre-Conference Music

```ini
; Conference Room 8000 with TappedIn Radio hold music
exten => 8000,1,NoOp(Conference Room 8000 - Main)
 same => n,Answer()
 same => n,Set(CHANNEL(musicclass)=tappedin-radio)
 same => n,Playback(conf-now-entering)
 same => n,ConfBridge(8000)
 same => n,Hangup()

; Conference Room 8001 with Raywonder Radio hold music
exten => 8001,1,NoOp(Conference Room 8001 - Raywonder Audio)
 same => n,Answer()
 same => n,Set(CHANNEL(musicclass)=raywonder-radio)
 same => n,Playback(conf-now-entering)
 same => n,ConfBridge(8001)
 same => n,Hangup()

; Conference Room 8002 - Christmas Music
exten => 8002,1,NoOp(Conference Room 8002 - Christmas Mix)
 same => n,Answer()
 same => n,Set(CHANNEL(musicclass)=christmas)
 same => n,Playback(conf-now-entering)
 same => n,ConfBridge(8002)
 same => n,Hangup()
```

---

## Conference Bridge Configuration

File: `/etc/asterisk/confbridge.conf`

```ini
[general]

; Default User Profile - TappedIn Radio while waiting
[default_user]
type=user
music_on_hold_when_empty=yes
music_on_hold_class=tappedin-radio
quiet=no
announce_user_count=yes
announce_join_leave=yes
dtmf_passthrough=yes

; Admin User Profile
[admin_user]
type=user
admin=yes
marked=yes
music_on_hold_when_empty=yes
music_on_hold_class=tappedin-radio
announce_user_count_all=yes
announce_join_leave=yes

; Default Bridge Profile
[default_bridge]
type=bridge
max_members=50
record_conference=no
video_mode=follow_talker

; TappedIn Radio Conference Bridge
[tappedin_bridge]
type=bridge
max_members=50
music_on_hold_class=tappedin-radio
music_on_hold_when_empty=yes

; Raywonder Radio Conference Bridge
[raywonder_bridge]
type=bridge
max_members=50
music_on_hold_class=raywonder-radio
music_on_hold_when_empty=yes

; Christmas Conference Bridge
[christmas_bridge]
type=bridge
max_members=50
music_on_hold_class=christmas
music_on_hold_when_empty=yes
```

---

## AzuraCast API Integration

### Now Playing Information

FlexPBX can retrieve real-time information from AzuraCast:

```bash
# Get current track on TappedIn Radio
curl https://stream.tappedin.fm/api/nowplaying/tappedin-radio

# Get current track on Raywonder Radio
curl https://stream.raywonderis.me/api/nowplaying/raywonder-radio
```

### API Response Structure
```json
{
  "station": {
    "id": 1,
    "name": "TappedIn Radio",
    "shortcode": "tappedin-radio",
    "description": "Soundscapes, Meditation Music & Podcasts"
  },
  "now_playing": {
    "song": {
      "id": "abc123",
      "text": "Artist - Song Title",
      "artist": "Artist Name",
      "title": "Song Title",
      "album": "Album Name",
      "genre": "Genre"
    },
    "playlist": "Default Playlist",
    "is_live": false
  },
  "listeners": {
    "total": 5,
    "unique": 5
  }
}
```

---

## Admin UI Integration

### Music on Hold Settings Page

Create: `/home/flexpbxuser/public_html/admin/music-on-hold.php`

Features:
- Select default MOH class (TappedIn, Raywonder, Christmas, Files)
- View now playing from AzuraCast streams
- Test MOH by dialing extension
- Configure per-conference room MOH
- Upload custom audio files
- Schedule MOH changes (business hours vs. after hours)

### API Endpoint for MOH Control

Create: `/home/flexpbxuser/public_html/api/moh.php`

Endpoints:
- `GET ?path=classes` - List available MOH classes
- `GET ?path=nowplaying&station={id}` - Get AzuraCast now playing
- `POST ?path=set-default&class={name}` - Set default MOH class
- `POST ?path=test&class={name}` - Test MOH by calling extension
- `GET ?path=stats` - Get MOH usage statistics

---

## Media Library Organization

### TappedIn Radio Library Structure
```
/azuracast/stations/tappedin-radio/media/
├── soundscapes/
│   ├── nature/
│   ├── ambient/
│   └── meditation/
├── podcasts/
│   ├── mindfulness/
│   └── wellness/
└── music/
    ├── relaxation/
    └── instrumental/
```

### Raywonder Radio Library Structure
```
/azuracast/stations/raywonder-radio/media/
├── audio-described/
│   ├── documentaries/
│   ├── books/
│   └── articles/
├── educational/
│   ├── science/
│   ├── history/
│   └── technology/
└── music/
    ├── classical/
    └── jazz/
```

---

## Playlist Configuration

### TappedIn Radio - Default Playlist
- **Name**: Soundscapes & Meditation Mix
- **Type**: Advanced
- **Scheduling**: 24/7
- **Content**:
  - 40% Soundscapes
  - 30% Meditation Music
  - 20% Podcasts
  - 10% Relaxation Music
- **Crossfade**: 3 seconds
- **Normalization**: Yes (-14 LUFS)

### Raywonder Radio - Default Playlist
- **Name**: Audio Described Content
- **Type**: Advanced
- **Scheduling**: 24/7
- **Content**:
  - 50% Audio Described Educational
  - 30% Music
  - 20% Podcasts
- **Crossfade**: 5 seconds
- **Normalization**: Yes (-14 LUFS)

---

## Quality & Performance

### Stream Quality Settings

**TappedIn Radio**:
- Format: MP3
- Bitrate: 192 kbps
- Sample Rate: 44.1 kHz
- Channels: Stereo
- Asterisk converts to 8kHz mono for telephony

**Raywonder Radio**:
- Format: MP3
- Bitrate: 192 kbps
- Sample Rate: 44.1 kHz
- Channels: Stereo
- Asterisk converts to 8kHz mono for telephony

### Bandwidth Usage

Per concurrent caller:
- 8 kHz mono stream: ~8-10 KB/s
- 100 callers on hold: ~1 Mbps
- Negligible with local caching

---

## Fallback & Reliability

### Stream Failure Handling

If AzuraCast stream is unavailable:

1. **Automatic Fallback**: Falls back to local files MOH class
2. **Retry Logic**: Attempts reconnect every 30 seconds
3. **Notification**: Admin alert if stream down > 5 minutes
4. **Manual Override**: Admin can force local files via UI

### Local File Backup

Maintain local copies in `/var/lib/asterisk/moh/`:
- `default/` - Generic hold music
- `tappedin/` - Downloaded TappedIn tracks
- `raywonder/` - Downloaded Raywonder tracks
- `christmas/` - Christmas music

---

## Scheduling & Time-Based MOH

### Business Hours Configuration

```ini
; Use TappedIn during business hours, Raywonder after hours
[business-hours-moh]
exten => s,1,NoOp(Time-Based MOH Selection)
 same => n,GotoIfTime(9:00-17:00,mon-fri,*,*?business,s,1)
 same => n,Set(CHANNEL(musicclass)=raywonder-radio)
 same => n,Return()

exten => business,1,Set(CHANNEL(musicclass)=tappedin-radio)
 same => n,Return()
```

Apply in dialplan:
```ini
exten => 2000,1,NoOp(Extension 2000)
 same => n,GoSub(business-hours-moh,s,1)
 same => n,Dial(PJSIP/2000,20)
 same => n,Hangup()
```

---

## Conference Room Enhancements

### Conference 8000 - TappedIn Radio
**Use Case**: General meetings, meditation sessions
**Music**: Soundscapes and ambient

### Conference 8001 - Raywonder Radio
**Use Case**: Educational discussions, accessibility-focused
**Music**: Audio described content

### Conference 8002 - Christmas Music
**Use Case**: Holiday events, seasonal meetings
**Music**: Christmas mix

### Conference 8003 - Silent (No Music)
**Use Case**: Professional meetings requiring silence

```ini
exten => 8003,1,NoOp(Conference Room 8003 - Silent)
 same => n,Answer()
 same => n,Set(CHANNEL(musicclass)=)
 same => n,ConfBridge(8003,silent_bridge)
 same => n,Hangup()

[silent_bridge]
type=bridge
max_members=50
music_on_hold_when_empty=no
```

---

## Admin UI - Now Playing Display

### Dashboard Widget

Display current track on hold music:

```javascript
// Fetch now playing from TappedIn Radio
fetch('https://stream.tappedin.fm/api/nowplaying/tappedin-radio')
    .then(r => r.json())
    .then(data => {
        const song = data.now_playing.song;
        document.getElementById('now-playing').innerHTML = `
            <strong>Now Playing:</strong><br>
            ${song.artist} - ${song.title}<br>
            <small>${song.album}</small>
        `;
    });
```

---

## Testing

### Test Music on Hold

```bash
# Test default MOH (TappedIn)
asterisk -rx "originate PJSIP/2000 extension 00@flexpbx-internal"

# Test specific MOH class
asterisk -rx "originate PJSIP/2000 application MusicOnHold tappedin-radio"

# Test conference with music
asterisk -rx "originate PJSIP/2000 extension 8000@flexpbx-internal"

# Check MOH status
asterisk -rx "moh show classes"
```

### Verify Streams

```bash
# Test TappedIn stream
ffmpeg -i https://stream.tappedin.fm/radio/8000/tappedin-radio -t 10 test.mp3

# Test Raywonder stream
ffmpeg -i https://stream.raywonderis.me/radio/8000/raywonder-radio -t 10 test2.mp3

# Check stream bitrate
ffprobe https://stream.tappedin.fm/radio/8000/tappedin-radio
```

---

## Troubleshooting

### Stream Not Playing

**Problem**: No music on hold

**Diagnosis**:
```bash
# Check MOH configuration
asterisk -rx "moh show classes"

# Test stream manually
ffmpeg -i https://stream.tappedin.fm/radio/8000/tappedin-radio -t 5 test.mp3

# Check Asterisk logs
tail -f /var/log/asterisk/full | grep -i moh
```

**Solution**:
```bash
# Reload MOH configuration
asterisk -rx "moh reload"

# Restart Asterisk if needed
systemctl restart asterisk
```

### Poor Audio Quality

**Problem**: Choppy or distorted audio

**Diagnosis**:
- Check network bandwidth
- Monitor CPU usage
- Verify stream bitrate

**Solution**:
```bash
# Use lower quality stream (if available)
# Or increase buffer size
application=/usr/bin/ffmpeg -i URL -f s16le -ar 8000 -ac 1 -bufsize 64k -
```

---

## Best Practices

1. **Always have local file fallback** - In case streams are unavailable
2. **Monitor stream health** - Set up alerts for stream failures
3. **Test after changes** - Always test MOH after configuration changes
4. **Use appropriate content** - Match music to business environment
5. **Respect copyright** - Ensure proper licensing for streamed content
6. **Cache popular tracks** - Reduce bandwidth by caching locally
7. **Update regularly** - Keep playlists fresh and engaging

---

## Integration with Other Systems

### Mattermost Notifications

Send now playing to Mattermost channel:

```bash
#!/bin/bash
# /home/flexpbxuser/scripts/mattermost-nowplaying.sh

NOWPLAYING=$(curl -s https://stream.tappedin.fm/api/nowplaying/tappedin-radio)
SONG=$(echo $NOWPLAYING | jq -r '.now_playing.song.text')

curl -X POST https://mattermost.tappedin.fm/hooks/xxx \
  -H 'Content-Type: application/json' \
  -d "{\"text\": \"🎵 Now playing on hold music: $SONG\"}"
```

### Web Portal Display

Show now playing on user portal:

```php
// /home/flexpbxuser/public_html/user-portal/nowplaying.php
<?php
$json = file_get_contents('https://stream.tappedin.fm/api/nowplaying/tappedin-radio');
$data = json_decode($json, true);
$song = $data['now_playing']['song'];

echo "<div class='now-playing'>";
echo "<h3>On Hold Music</h3>";
echo "<p><strong>" . htmlspecialchars($song['artist']) . "</strong></p>";
echo "<p>" . htmlspecialchars($song['title']) . "</p>";
echo "<p><small>" . htmlspecialchars($song['album']) . "</small></p>";
echo "</div>";
```

---

## Future Enhancements

1. **User-selectable MOH** - Let callers choose their hold music
2. **Voice prompts between tracks** - Informational messages
3. **Sponsor messages** - Monetization opportunity
4. **Mood-based selection** - AI selects music based on call queue length
5. **Multi-language support** - Different streams per language
6. **Analytics dashboard** - Track MOH preferences and usage

---

## Support & Resources

- **AzuraCast Documentation**: https://docs.azuracast.com
- **TappedIn Radio**: https://tappedin.fm
- **Raywonder Radio**: https://raywonderis.me
- **Asterisk MOH Guide**: https://wiki.asterisk.org/wiki/display/AST/Music+On+Hold

---

**Version**: 1.0
**Last Updated**: November 9, 2025
**Status**: Production Ready
**Tested With**: FlexPBX 1.3, Asterisk 18+, AzuraCast 0.19+
