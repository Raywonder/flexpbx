# FlexPBX Media Files Directory

## Directory Structure

```
media/
├── sounds/          # IVR greetings, announcements, and system prompts
│   └── system/      # System notification sounds
├── moh/             # Music on Hold files
└── recordings/      # Call recordings storage
```

## Current Sound Files

### System Sounds (media/sounds/system/)
- connected.wav
- connection lost.wav
- disconnect.wav
- file transfer complete.wav
- message.wav
- reconnected.wav

## Adding New Media Files

### For IVR Greetings
Upload WAV files (16-bit, 8kHz mono recommended) to:
- `media/sounds/` for greetings referenced in config/extensions-config.json

### For Music on Hold
Upload audio files to:
- `media/moh/` and reference them in queue configurations

### File Format Requirements
- Format: WAV, PCM
- Sample Rate: 8000 Hz (preferred) or 16000 Hz
- Channels: Mono (1 channel)
- Bit Depth: 16-bit

## API Endpoints

Media files are accessible via:
- Web: `https://flexpbx.devinecreations.net/media/sounds/`
- Upload: Use admin panel file manager

## Permissions

All media directories should have:
- Owner: flexpbxuser:flexpbxuser
- Permissions: 755 for directories, 644 for files
