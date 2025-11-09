# FlexPBX - FreePBX Compatibility Guide

## Overview

FlexPBX is designed to be **100% compatible with FreePBX voice prompts** by default. This ensures that FlexPBX can leverage the extensive library of FreePBX audio files, IVR templates, and configurations without requiring custom recordings.

---

## Voice Prompts Compatibility

### Default Prompt Locations

FlexPBX uses standard FreePBX prompt locations:
- **System Prompts**: `/var/lib/asterisk/sounds/en/`
- **Custom Prompts**: `/var/lib/asterisk/sounds/custom/`
- **Music on Hold**: `/var/lib/asterisk/moh/`

### Supported Audio Formats

1. **WAV** (16-bit, 8kHz) - Asterisk native
2. **GSM** - Compressed, smaller file size
3. **G722** - Wideband audio, better quality
4. **ULAW/ALAW** - Standard telephony codecs

### FreePBX Prompts Used by FlexPBX

#### Voicemail Prompts
- `vm-login` - "Comedian Mail. Mailbox?"
- `vm-password` - "Password"
- `vm-incorrect` - "Login incorrect"
- `vm-intro` - Main voicemail menu
- `vm-youhave` - "You have"
- `vm-messages` - "messages"
- `vm-INBOX` - "in your INBOX"

#### IVR Prompts
- `ivr-enter_ext` - "Please enter the extension"
- `ivr-invalid` - "Invalid extension"
- `ivr-thank_you_for_calling` - Standard greeting
- `pbx-transfer` - "Please hold while I try that extension"
- `pbx-invalid` - "I'm sorry, that's not a valid extension"

#### Testing Prompts
- `echo-test` - "You are now entering the echo test..."
- `demo-echotest` - Alternative echo test message

#### System Prompts
- `welcome` - Generic welcome message
- `goodbye` - Call termination message
- `please-hold` - Hold music announcement
- `transfer` - Transfer notification

---

## Default IVR Templates

### Auto-Attendant Template

```
[flexpbx-ivr-main]
exten => s,1,NoOp(FlexPBX Main IVR)
 same => n,Answer()
 same => n,Wait(1)
 same => n,Background(ivr-thank_you_for_calling)
 same => n,Background(ivr-enter_ext)
 same => n,WaitExten(10)

exten => _XXXX,1,NoOp(Extension ${EXTEN} dialed)
 same => n,Playback(pbx-transfer)
 same => n,Goto(flexpbx-internal,${EXTEN},1)

exten => i,1,NoOp(Invalid entry)
 same => n,Playback(pbx-invalid)
 same => n,Goto(s,1)

exten => t,1,NoOp(Timeout)
 same => n,Playback(goodbye)
 same => n,Hangup()
```

### Voicemail IVR Template

```
[flexpbx-ivr-vm]
exten => s,1,NoOp(Voicemail IVR)
 same => n,Answer()
 same => n,Wait(1)
 same => n,Background(vm-intro)
 same => n,WaitExten(5)

exten => 1,1,NoOp(Check Messages)
 same => n,VoiceMailMain(@flexpbx)
 same => n,Hangup()

exten => 2,1,NoOp(Leave Message)
 same => n,Directory(flexpbx,by-extension)
 same => n,Hangup()

exten => 0,1,NoOp(Operator)
 same => n,Goto(flexpbx-internal,operator,1)

exten => #,1,NoOp(Repeat Menu)
 same => n,Goto(s,1)
```

### Directory Assistance Template

```
[flexpbx-directory]
exten => s,1,NoOp(Directory)
 same => n,Answer()
 same => n,Wait(1)
 same => n,Directory(flexpbx,by-extension)
 same => n,Hangup()
```

---

## Custom FlexPBX Prompts

While FlexPBX uses FreePBX prompts by default, you can add custom prompts for:

### Branding
- Company name announcements
- Custom greetings
- Hold music with branding

### Special Features
- Multi-language support
- Custom IVR menus
- Specialized prompts for industry-specific features

### Prompt Storage

Custom prompts go in:
```
/var/lib/asterisk/sounds/custom/flexpbx/
```

Structure:
```
/var/lib/asterisk/sounds/custom/flexpbx/
├── en/              # English prompts
│   ├── welcome.wav
│   ├── goodbye.wav
│   └── ...
├── es/              # Spanish prompts
│   ├── welcome.wav
│   └── ...
└── fr/              # French prompts
    └── ...
```

---

## Dialplan Templates

### Complete Default Template

This template includes all FreePBX-compatible prompts:

```
[general]
static=yes
writeprotect=no
clearglobalvars=no

[globals]
FLEXPBX_VERSION=1.3
COMPANY_NAME=FlexPBX

[flexpbx-internal]
; Voicemail Feature Codes
exten => *97,1,NoOp(Own Voicemail)
 same => n,Answer()
 same => n,VoiceMailMain(${CALLERID(num)}@flexpbx)
 same => n,Hangup()

exten => *98,1,NoOp(Any Voicemail)
 same => n,Answer()
 same => n,VoiceMailMain(@flexpbx)
 same => n,Hangup()

; Echo Test with Prompt
exten => *43,1,NoOp(Echo Test)
 same => n,Answer()
 same => n,Playback(demo-echotest)
 same => n,Echo()
 same => n,Hangup()

; Directory
exten => *411,1,NoOp(Directory)
 same => n,Answer()
 same => n,Directory(flexpbx,by-extension)
 same => n,Hangup()

; Call Parking
exten => 700,1,NoOp(Park Call)
 same => n,Park()

exten => 701,1,NoOp(Retrieve Parked Call 701)
 same => n,ParkedCall(701)

; Conference Rooms
exten => 8000,1,NoOp(Conference Room 8000)
 same => n,Answer()
 same => n,Playback(conf-now-entering)
 same => n,ConfBridge(8000)
 same => n,Hangup()

; Extension-to-Extension (template)
; exten => XXXX,1,Dial(PJSIP/XXXX,20)
```

---

## FreePBX Module Compatibility

### Modules That Work with FlexPBX

1. **Voicemail (app_voicemail)**
   - Full compatibility
   - Uses same mailbox format
   - Same feature codes (*97, *98)

2. **IVR (app_ivr)**
   - Compatible menu structure
   - Same prompt library
   - Digit handling works identically

3. **Call Parking (features.conf)**
   - Standard parking lots (700-720)
   - Same park/retrieve codes

4. **Conference Bridge (app_confbridge)**
   - ConfBridge module compatible
   - Same room numbers (8000+)
   - Admin/user pin support

5. **Follow Me (app_followme)**
   - Compatible configuration
   - Ring groups work the same

6. **Time Conditions**
   - GotoIfTime() fully supported
   - Same syntax

7. **Directory (app_directory)**
   - by-extension and by-name
   - Same navigation

---

## Audio File Installation

### FreePBX Prompts Package

Install FreePBX sound files:

```bash
cd /tmp
wget http://www.freepbx.org/v2/sounds/FreePBX-Sounds-en-ulaw-1.0.tgz
tar -xzf FreePBX-Sounds-en-ulaw-1.0.tgz -C /var/lib/asterisk/sounds/en/
chown -R asterisk:asterisk /var/lib/asterisk/sounds/
```

### Core English Prompts

Asterisk includes core prompts by default:
```bash
apt-get install asterisk-prompt-en
# or
yum install asterisk-sounds-core-en-ulaw
```

---

## Migration from FreePBX

### Import Existing Dialplan

If migrating from FreePBX:

```bash
# Backup FlexPBX dialplan
cp /etc/asterisk/extensions.conf /etc/asterisk/extensions.conf.flexpbx

# Copy FreePBX dialplan
cp /path/to/freepbx/extensions.conf /etc/asterisk/extensions.conf

# Merge custom contexts
cat /etc/asterisk/extensions.conf.flexpbx >> /etc/asterisk/extensions.conf

# Reload
asterisk -rx "dialplan reload"
```

### Import Custom Prompts

```bash
# Copy custom prompts
cp -r /path/to/freepbx/sounds/custom/* /var/lib/asterisk/sounds/custom/

# Fix permissions
chown -R asterisk:asterisk /var/lib/asterisk/sounds/custom/
```

---

## Troubleshooting

### Prompt Not Found

**Error**: "file.gsm not found"

**Solution**:
```bash
# Check if prompt exists
ls -l /var/lib/asterisk/sounds/en/file.*

# Install missing prompts
apt-get install asterisk-sounds-core-en-ulaw asterisk-sounds-core-en-gsm

# Or use FreePBX sounds package
```

### Wrong Language

**Error**: Prompts play in wrong language

**Solution**:
```bash
# Set channel language in dialplan
Set(CHANNEL(language)=en)

# Or globally in sip.conf/pjsip.conf
language=en
```

### Playback Issues

**Error**: Prompt plays garbled or distorted

**Solution**:
```bash
# Check audio format
file /var/lib/asterisk/sounds/en/welcome.wav

# Convert if needed
sox input.wav -r 8000 -c 1 -b 16 output.wav

# Or use Asterisk
asterisk -rx "file convert /path/to/input.wav /path/to/output.wav"
```

---

## Best Practices

### Prompt Naming

Use FreePBX-compatible naming:
- `company-welcome.wav` - Company welcome
- `company-hours.wav` - Business hours
- `company-closed.wav` - After hours message

### Audio Quality

- **Sample Rate**: 8kHz (telephony standard)
- **Channels**: Mono (1 channel)
- **Bit Depth**: 16-bit
- **Format**: WAV or GSM

### Testing

Always test prompts:
```bash
# Play a prompt
asterisk -rx "originate PJSIP/2000 extension 123@test-prompts"

# In test-prompts context:
[test-prompts]
exten => 123,1,Answer()
 same => n,Playback(your-prompt)
 same => n,Hangup()
```

---

## Resources

- FreePBX Sound Files: http://www.freepbx.org/v2/sounds/
- Asterisk Sounds: https://www.asterisk.org/downloads/sounds
- FreePBX Documentation: https://wiki.freepbx.org/
- FlexPBX Prompts: `/home/flexpbxuser/documentation/`

---

**Version**: 1.0  
**Last Updated**: November 8, 2025  
**Compatibility**: FreePBX 13+, Asterisk 13+  
**Status**: Production Ready
