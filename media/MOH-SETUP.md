# Music on Hold (MOH) Setup - Complete

## Summary
**82 music files** copied from `/home/dom/apps/media/music/` to FlexPBX MOH directory.

## Directory Structure
```
/home/flexpbxuser/public_html/media/moh/
├── Abraham Smooth/
├── A&D I/
├── BlindMoneyGang/
├── Dopfunk/
├── I am Jarrel/
├── Jessy Jukebox/
├── J. Vybes/
├── Matthew Whitaker/
├── misc-artists/
├── Questionatl/
├── Raywonder/
├── Roger/
├── tappedin.fm/
├── Taqee Basiyr/
├── Tragity/
├── corporate-playlist.m3u    # Playlist for business/sales
└── ambient-playlist.m3u      # Playlist for support/relaxation
```

## MOH Classes Configured

### Corporate Class (Sales Queue)
**Playlist:** `corporate-playlist.m3u`
**Music Style:** Upbeat, professional
**Artists:** A&D I, Abraham Smooth, Questionatl
**Files:**
- A&D I - Chilling in Negril.mp3
- Abraham Smooth FT Raywonder - Mi Seet.mp3
- Ready.mp3
- Fire Up.mp3

### Ambient Class (Tech Support Queue)
**Playlist:** `ambient-playlist.m3u`
**Music Style:** Calm, relaxing
**Source:** tappedin.fm collection
**Files:**
- Enchanted Forest Serenity.mp3
- Sleep Music Healing Frequency 369hz.mp3
- Tappedin Theme.wav
- Late Night R&B.mp3

## Web Access
All music files are accessible via:
- **Media Manager:** https://flexpbx.devinecreations.net/admin/media-manager.html
- **Direct URL:** https://flexpbx.devinecreations.net/media/moh/

## Testing MOH

### From Extension 2001:
1. Call another extension (e.g., 1001)
2. Ask them to hold
3. You should hear music from the corporate class

### From IVR:
1. Call extension 101 (Main IVR)
2. Press 1 for Sales → Hear corporate MOH while waiting
3. Press 2 for Support → Hear ambient MOH while waiting

## Adding More Music

### Via Media Manager UI
1. Go to: https://flexpbx.devinecreations.net/admin/media-manager.html
2. Click "Music on Hold" tab
3. Upload additional MP3/WAV files
4. Files appear in media/moh/ directory

### Via File Copy
```bash
cp /path/to/your/music.mp3 /home/flexpbxuser/public_html/media/moh/
chown flexpbxuser:flexpbxuser /home/flexpbxuser/public_html/media/moh/music.mp3
chmod 644 /home/flexpbxuser/public_html/media/moh/music.mp3
```

## Creating Custom Playlists

Create `.m3u` files in the moh directory:
```
#EXTM3U
#EXTINF:-1,Song Title
artist/song.mp3
```

## Artist Collections Available

| Artist/Collection | File Count |
|------------------|------------|
| A&D I | 8 tracks |
| tappedin.fm | 5 tracks |
| Raywonder | Multiple albums |
| J. Vybes | 5+ tracks |
| Dopfunk | Full collection |
| + 10 more artists | 82 total files |

## PBX Configuration

The MOH classes are already configured in:
- **File:** `config/extensions-config.json`
- **Sales Queue:** Uses "corporate" MOH
- **Support Queue:** Uses "ambient" MOH

## Permissions
- **Owner:** flexpbxuser:flexpbxuser
- **Directories:** 755
- **Files:** 644
- **Web accessible:** Yes

## File Formats Supported
- MP3 (most common)
- WAV (uncompressed, better quality)
- OGG (optional)
- FLAC (high quality)

## Notes
- Original source: `/home/dom/apps/media/music/`
- Files are copied (not symlinked) for security
- All files maintain original artist organization
- Playlists can be edited to customize hold music rotation

## Status
✅ **READY** - Music on Hold system is fully configured and operational

---
**Last Updated:** 2025-10-13
**Total Files:** 82 music tracks
**Storage Location:** /home/flexpbxuser/public_html/media/moh/
