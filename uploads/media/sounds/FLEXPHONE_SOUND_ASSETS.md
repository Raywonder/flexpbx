# Flex Phone Sound Assets

These bundled assets came from `E:\Downloads\FlexPhoneSounds` for the next Flex Phone/Flex PBX release.

## Ringtones

Short clips for desktop-client ringtone choices and PBX ringback/ringtone use:

- `ringtones/ringtone-incoming-call.wav` from `Incoming Call.wav`, 6.00 seconds.
- `ringtones/ringtone-incoming-call-alt.wav` from `Incoming Call (1).wav`, 7.12 seconds.
- `ringtones/ringtone-ring-ring-flitch.wav` from `Ring Ring Flitch.wav`, 16.12 seconds.
- `ringtones/ringtone-are-you-gonna-answer.wav` from `Are you gonna answer.wav`, 20.28 seconds.

## IVR Cue

- `ivr/flex-phone-softphone.wav` from `Flex Phone Softphone.wav`, 2.00 seconds.

## Hold Music

Longer clips for the Flex PBX music-on-hold module live under `uploads/moh/flexphone-hold/`:

- `flexphone-hold-lofi-01.wav` from `Incoming Call Lofi beat.wav`, 108.72 seconds.
- `flexphone-hold-lofi-02.wav` from `Incoming Call Lofi beat (1).wav`, 116.28 seconds.
- `flexphone-hold-lofi-03.wav` from `Incoming Call Lofi beat (2).wav`, 121.40 seconds.
- `flexphone-hold-lofi-04.wav` from `Incoming Call Lofi beat (3).wav`, 112.32 seconds.
- `flexphone-hold-lofi-05.wav` from `Incoming Call Lofi beat (4).wav`, 136.00 seconds.
- `flexphone-hold-lofi-06.wav` from `Incoming Call Lofi beat (5).wav`, 124.80 seconds.

All listed files are stereo 48 kHz 16-bit PCM WAV. Production Asterisk deployments should convert IVR prompt and MOH copies to the site's preferred telephony format when needed, such as mono 8 kHz WAV, ulaw, or sln16, before assigning them in dialplans.
