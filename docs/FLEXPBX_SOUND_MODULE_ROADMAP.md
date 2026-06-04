# FlexPBX Sound Module Roadmap

This plan keeps PBX/server audio assets and FlexPhone client sound packs updateable without hardcoding future sounds into each install.

## Goals

- Keep default ringtone, IVR, and music-on-hold assets in git for reproducible installs.
- Split reusable sound packs into a dedicated FlexPBX sounds module repository when the asset catalog grows.
- Let the FlexPBX web admin download, preview, install, and roll back sound packs.
- Let FlexPhone clients discover and download client-side sound packs from a signed update catalog.
- Preserve Asterisk-compatible converted copies while keeping original source-quality files in the repo.

## Repository Layout

Current bundled assets stay in `uploads/media/sounds/` and `uploads/moh/`:

- `uploads/media/sounds/ringtones/`: server-provided ringtone assets.
- `uploads/media/sounds/ivr/`: IVR and prompt assets.
- `uploads/media/sounds/system/`: shared system sounds.
- `uploads/moh/flexphone-hold/`: default music-on-hold tracks.
- `scripts/install-flexphone-sound-assets.sh`: normalizes WAV files for Asterisk and reloads MOH/dialplan.

Future module repository:

- `catalog/sound-packs.json`: signed manifest of available packs.
- `packs/<pack-id>/manifest.json`: pack metadata, checksums, category, license, compatible apps.
- `packs/<pack-id>/source/`: original quality assets.
- `packs/<pack-id>/asterisk/`: optional pre-normalized Asterisk assets.
- `packs/<pack-id>/client/`: optional FlexPhone desktop/mobile assets.

## Sound Pack Manifest

Each pack should include:

- `id`, `name`, `version`, `description`
- `categories`: `ivr`, `moh`, `ringtones`, `system`, `client-voice-pack`
- `author`, `license`, `sourceUrl`
- `files[]` with path, SHA-256, duration, sample rate, channels, and intended use
- `asteriskProfile`: target format for PBX install, normally mono 8 kHz 16-bit WAV unless a PBX supports wider audio
- `clientProfile`: preferred client format for FlexPhone, normally WAV or compressed assets optimized for fast playback
- `minimumFlexPBXVersion` and `minimumFlexPhoneVersion`

## FlexPBX Admin UI

Add a Sound Library page under the admin settings area:

- Show installed packs and available remote packs.
- Preview sounds in the browser before install.
- Install or update a pack from the catalog.
- Assign IVR prompts, queue sounds, ringback, and MOH class from installed packs.
- Validate SHA-256 checksums before install.
- Convert source files with ffmpeg when needed.
- Keep backups of previous active sounds before replacing them.
- Show clear success/error messages and Asterisk reload status.

## FlexPhone Client UI

Add a Sound Packs settings tab or window:

- Show bundled local sounds.
- Check the FlexPBX sound catalog for client-compatible packs.
- Download packs into the user profile, not Program Files.
- Let users select default ringtone, alternate ringtone, alert, connected, disconnected, and keypad sound pack.
- Cache the catalog and verify checksums.
- Keep the current built-in sounds as fallback when no server pack is available.

## Update Flow

1. Repo publishes a new sound pack and catalog manifest.
2. FlexPBX admin sees an available update.
3. Admin installs it; server verifies checksums and converts PBX copies.
4. FlexPBX exposes the client-compatible catalog URL.
5. FlexPhone checks the catalog from the configured PBX and offers new sound packs.
6. Users can install packs locally without needing a full client update.

## Release Rules

- Do not delete local/custom sounds during updates.
- Keep source-quality assets in git or the dedicated sound module repo.
- Keep generated Asterisk-normalized output out of git unless intentionally published as a prebuilt pack.
- Include checksums for every downloadable pack.
- Document asset licensing before publishing a pack.
- Preserve `pbx.tappedin.fm` as the default FlexPhone PBX endpoint.
