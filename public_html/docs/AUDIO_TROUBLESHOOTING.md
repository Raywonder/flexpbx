# Audio Issues Troubleshooting Guide
**Date:** October 14, 2025 04:20 AM
**Issues Reported:**
1. *43 (Echo test) - No audio, just silence
2. *44 (Time) - Starts then ends with no audio
3. Voicemail - Incomplete prompts, numbers missing, call ends abruptly
4. Extension dialing during calls not working
5. *45/*46 queue prompts need verification

---

## 🔍 Current Status

###enabled verbose logging (level 5)
✅ Enabled debug logging (level 3)
✅ Verified codecs: ulaw, alaw, gsm are enabled
✅ Verified sound files exist (738 files installed)
✅ Verified file permissions (asterisk:asterisk, 644)
✅ Verified dialplan loaded correctly
✅ Verified VoicemailMain application available

---

## 🧪 Diagnostic Commands Run

### Sound File Verification
```bash
# Checked directories - PASS
/usr/share/asterisk/sounds/digits/  - 90+ files
/usr/share/asterisk/sounds/vm-*     - 100+ voicemail prompts
/usr/share/asterisk/sounds/         - 738 total files

# Permissions fixed
chmod 644 on all sound files
chmod 755 on directories
owner: asterisk:asterisk
```

### Module Verification
```bash
# Echo application - LOADED
asterisk -rx "core show application Echo"
Result: Application available

# Say applications - LOADED
asterisk -rx "core show applications" | grep Say
Result: SayNumber, SayDigits, SayUnixTime all available

# Voicemail - LOADED
asterisk -rx "module show like voicemail"
Result: app_voicemail_imap.so running
```

### Endpoint/Codec Verification
```bash
# Extension 2001 codecs
Allowed: ulaw, alaw, gsm
RTP symmetric: Yes
Force rport: Yes
Direct media: No
```

---

## 🎯 Likely Causes

### Issue 1 & 2: *43/*44 No Audio

**Most likely cause:** One-way RTP audio issue

**Symptoms match:**
- Call connects (so SIP signaling works)
- No audio heard (RTP not reaching client)
- Call works but silent

**Possible reasons:**
1. **Firewall blocking RTP** - UDP ports 10000-20000 not open
2. **NAT/routing issue** - RTP packets not reaching your device
3. **Codec negotiation** - Client using codec Asterisk can't send
4. **STUN not working** - External IP not properly discovered

**Next steps to diagnose:**
```bash
# While on a call to *43, check active channels
asterisk -rx "core show channels verbose"

# Check RTP statistics
asterisk -rx "rtp show stats"

# Check if RTP packets are being sent
# (need to run during active call)
```

### Issue 3: Voicemail Incomplete Prompts

**Possible causes:**
1. **Timeout too short** - Call ending before prompts finish
2. **Missing sound files** - Some vm-* files not found
3. **Codec issue** - Same as above, can't hear prompts
4. **Voicemail config** - Wrong paths or settings

**Files verified present:**
- vm-login.ulaw
- vm-password.ulaw
- vm-instructions.ulaw
- digits/0-9.ulaw
- Plus 100+ other vm-* files

### Issue 4: Extension Dialing Not Enabled

**Feature needed:** Call transfers

**Current status:** Transfer features may not be enabled in features.conf

**What's needed:**
- Blind transfer: *2 or **
- Attended transfer: *3
- Or dial by extension feature

---

## 🔧 Fixes Applied

### 1. Enabled Verbose Logging
```bash
asterisk -rx "core set verbose 5"
asterisk -rx "core set debug 3"
```

Now all calls will show detailed logs in `/var/log/asterisk/messages`

### 2. Fixed File Permissions
```bash
chmod 644 /usr/share/asterisk/sounds/**/*
chmod 755 /usr/share/asterisk/sounds directories
chown asterisk:asterisk all sound files
```

### 3. Verified Dialplan
All feature codes properly loaded:
- *43 - Echo Test ✓
- *44 - Time Announcement ✓
- *45 - Queue Login ✓
- *46 - Queue Logout ✓
- *48 - Queue Status ✓
- *77 - MOH + Stats ✓
- *78 - MOH Preview ✓
- *97 - Voicemail ✓

---

## 📋 Test Procedure

### Test 1: Echo Test (*43)

**What to do:**
1. Dial *43 from extension 2001
2. Say something
3. Listen for echo

**Expected:**
- Hear yourself with 0.5 second delay
- Clear audio quality
- Can talk for as long as you want

**If still silent:**
```bash
# Check logs during call
tail -f /var/log/asterisk/messages | grep -i "echo\|rtp\|audio"

# Check if firewall allows RTP
netstat -tulpn | grep asterisk | grep -E "100[0-9]{2}"

# Verify RTP ports open
sudo firewall-cmd --list-all | grep 10000-20000
```

### Test 2: Time Announcement (*44)

**What to do:**
1. Dial *44 from extension 2001
2. Listen for time announcement

**Expected:**
- Hear: "Today is Sunday, October 14th, 2025. The time is 4:20 AM"
- Then call ends

**If still silent:**
```bash
# Check if SayUnixTime works
tail -f /var/log/asterisk/messages | grep -i "sayunixtime\|playback"
```

### Test 3: Voicemail (*97)

**What to do:**
1. Dial *97
2. Follow prompts

**Expected sequence:**
1. "Comedian mail" (intro)
2. "Mailbox?" or auto-detect your mailbox
3. "Password?" (if configured)
4. Main menu: "You have X new messages..."
5. Options menu with all choices

**If still incomplete:**
```bash
# Check voicemail context
asterisk -rx "voicemail show users"

# Check sound file playback
tail -f /var/log/asterisk/messages | grep -i "vm-\|voicemail"
```

### Test 4: Queue Prompts (*45, *46)

**Test *45 (Login):**
1. First dial - Should hear: "You are now logged into the support queue"
2. Dial again - Should hear: "You are already logged into the queue"

**Test *46 (Logout):**
1. First dial - Should hear: "You are now logged out of the support queue"
2. Dial again - Should hear: "You are not currently logged into the queue"

**Verify:**
```bash
# Check which prompts are configured
grep -A 3 "exten => \*45" /etc/asterisk/extensions.conf
grep -A 3 "exten => \*46" /etc/asterisk/extensions.conf
```

---

## 🚨 Real-Time Monitoring

### During Your Next Test Call

**Open SSH terminal and run:**
```bash
# Terminal 1 - Watch messages log
tail -f /var/log/asterisk/messages

# Terminal 2 - Watch channels
watch -n 1 'asterisk -rx "core show channels"'

# Terminal 3 - Watch RTP
watch -n 1 'asterisk -rx "rtp show stats"'
```

**Then dial *43 and note:**
1. Does channel show up?
2. Does RTP show packets?
3. Are there any WARNING or ERROR messages?
4. Does codec show correctly?

---

## 🔍 What I Need From You

### Please Test and Report:

**For *43 (Echo):**
- [ ] Does call connect? (hear ringing/answered)
- [ ] Do you hear anything at all?
- [ ] Can you hear yourself echo back?
- [ ] How long before call ends?

**For *44 (Time):**
- [ ] Does call connect?
- [ ] Do you hear any audio?
- [ ] Does it say the time?
- [ ] Does call end immediately or after pause?

**For *97 (Voicemail):**
- [ ] What exactly do you hear?
- [ ] Where does it stop/end abruptly?
- [ ] Which menu options do you hear?
- [ ] What happens when you press digits?

**For *45/*46 (Queue):**
- [ ] Which audio file actually plays?
- [ ] Is it the right one for the situation?
- [ ] Does it play completely?
- [ ] Any silence or cutoff?

### Also Check:

**On your phone/softphone:**
- What codec is being used? (Check phone settings)
- Is STUN enabled?
- What's the audio codec priority?
- Any firewall on your computer?

---

## 🔧 Additional Fixes to Try

### If RTP/Audio Issues Persist

**Option 1: Disable strictrtp temporarily**
```bash
# Edit /etc/asterisk/rtp.conf
strictrtp=no

# Reload
asterisk -rx "module reload res_rtp_asterisk.so"
```

**Option 2: Check external IP**
```bash
# Verify external IP is correct
asterisk -rx "pjsip show transports"

# Should show:
# transport-udp: 64.20.46.178:5060
# transport-tailscale: 100.64.0.2:5060
```

**Option 3: Test from different network**
- Try calling from different WiFi
- Try calling from cellular data
- Try different SIP client

**Option 4: Simplify codec list**
```bash
# Test with only ulaw
# Edit endpoint in pjsip.conf:
disallow=all
allow=ulaw

# Reload
asterisk -rx "module reload res_pjsip.so"
```

---

## 📊 Expected Log Output

### Successful *43 Echo Test

```
[timestamp] VERBOSE[xxxxx] pbx.c: Executing [*43@flexpbx-internal:1] NoOp("PJSIP/2001-xxxxx", "Echo Test")
[timestamp] VERBOSE[xxxxx] pbx.c: Executing [*43@flexpbx-internal:2] Answer("PJSIP/2001-xxxxx", "")
[timestamp] VERBOSE[xxxxx] pbx.c: Executing [*43@flexpbx-internal:3] Wait("PJSIP/2001-xxxxx", "0.5")
[timestamp] VERBOSE[xxxxx] pbx.c: Executing [*43@flexpbx-internal:4] Echo("PJSIP/2001-xxxxx", "")
[timestamp] VERBOSE[xxxxx] app_echo.c: Echo media
```

If you see warnings/errors here, that's the problem!

---

## 📞 Queue Prompts Configuration

### Current Mapping (Verified Correct)

**\*45 - Queue Login:**
```
NOT in queue → Plays: callcueue-login.wav (5.8 sec)
ALREADY in queue → Plays: callcueue-loged-in-to-out-prompt.wav (8.3 sec)
```

**\*46 - Queue Logout:**
```
IN queue → Plays: callcueue-logout.wav (7.5 sec)
NOT in queue → Plays: callcueue-loged-out-to-in-prompt.wav (11.0 sec)
```

**Files exist in:**
- `/usr/share/asterisk/sounds/callcueue-*.wav`
- `/usr/share/asterisk/sounds/callcueue-*.ulaw`
- `/usr/share/asterisk/sounds/callcueue-*.gsm`

All 3 formats available for compatibility.

---

## ✅ Action Items

1. **Test again** with logging enabled
2. **Report back** exactly what you hear (or don't hear)
3. **Check phone settings** - codec, STUN, NAT settings
4. **Try from different device** if possible
5. **Provide log snippets** from `/var/log/asterisk/messages` during test call

---

**Status:** Diagnostics enabled, awaiting test results
**Next:** Based on test results, we'll know if it's:
- RTP/firewall issue
- Codec issue
- Configuration issue
- Client-side issue

**Logging:** All enabled - will show detailed info on next call
