# FlexPBX Feature Codes Reference
**Last Updated:** October 14, 2025 02:00 AM
**System:** Asterisk 18.12.1

---

## 📞 Quick Reference Card

| Code | Feature | Description |
|------|---------|-------------|
| **\*43** | Echo Test | Test audio (hear yourself) |
| **\*44** | Time/Clock | Hear current date and time |
| **\*45** | Queue Login | Login to support queue |
| **\*46** | Queue Logout | Logout from support queue |
| **\*48** | Queue Status | Hear queue call count and agents |
| **\*77** | MOH + Stats | Hear queue stats then music on hold |
| **\*78** | Music on Hold | Preview what callers hear on hold |
| **\*97** | Voicemail | Access your voicemail box |

---

## 🧪 Diagnostic & Testing

### \*43 - Echo Test
**Purpose:** Test audio quality and verify two-way audio is working

**What happens:**
1. Call answers
2. Everything you say is played back to you with a slight delay
3. Used to test microphone, speaker, and network quality
4. Press hangup when done

**Use when:**
- Setting up a new phone
- Troubleshooting one-way audio
- Testing after network changes
- Verifying codec quality

**Expected result:** Should hear yourself clearly with ~0.5 second delay

---

### \*44 - Time/Clock Announcement
**Purpose:** Hear the current date and time spoken aloud

**What happens:**
1. Call answers
2. System announces: "Today is [Day], [Month] [Date], [Year]. The time is [Hour]:[Minute] [AM/PM]"
3. Automatically hangs up after announcement

**Use when:**
- Testing text-to-speech functionality
- Verifying system time is correct
- Quick audio test with known output
- Training users on feature codes

**Example output:** "Today is Sunday, October 14th, 2025. The time is 2:00 AM"

---

## 🎧 Queue Management (Agents)

### \*45 - Queue Login
**Purpose:** Login to the support queue to receive calls

**What happens:**

**If NOT already logged in:**
1. Call answers
2. System adds you to queue
3. Plays: "You are now logged into the support queue"
4. You'll now receive queue calls
5. Automatically hangs up

**If ALREADY logged in:**
1. Call answers
2. Plays: "You are already logged into the queue"
3. No action taken
4. Automatically hangs up

**Technical details:**
- Adds `PJSIP/[extension]` to support queue
- Uses dynamic membership (not in queues.conf)
- Membership persists until logout or restart
- Uses custom prompt: `callcueue-login`

---

### \*46 - Queue Logout
**Purpose:** Logout from the support queue to stop receiving calls

**What happens:**

**If currently logged in:**
1. Call answers
2. System removes you from queue
3. Plays: "You are now logged out of the support queue"
4. You'll stop receiving queue calls
5. Automatically hangs up

**If NOT logged in:**
1. Call answers
2. Plays: "You are not currently logged into the queue"
3. No action taken
4. Automatically hangs up

**Technical details:**
- Removes `PJSIP/[extension]` from support queue
- Immediate effect - no more calls routed to you
- Uses custom prompt: `callcueue-logout`

---

### \*48 - Queue Status
**Purpose:** Hear how many calls are waiting and how many agents are logged in

**What happens:**
1. Call answers
2. Announces: "There are [X] calls waiting"
3. Then: "[Y] agents logged in" (uses agent-loginok prompt)
4. Automatically hangs up

**Use when:**
- Checking if anyone needs help
- Verifying you're logged in
- Checking queue load before logging in
- Supervisors checking queue health

**Example output:**
- "There are 3 calls waiting. 2 agents logged in"
- "There are 0 calls waiting. 1 agent logged in"

**Technical details:**
- Reads `QUEUE_WAITING_COUNT(support)`
- Reads `QUEUE_MEMBER_COUNT(support)`
- Real-time data from queue

---

## 🎵 Music on Hold Features

### \*77 - MOH Preview + Queue Stats
**Purpose:** Hear queue statistics followed by music on hold preview

**What happens:**
1. Call answers
2. Announces call count (number only)
3. Waits 1 second
4. Plays music on hold for 120 seconds (2 minutes)
5. Automatically hangs up after 2 minutes or if you hang up first

**Use when:**
- Testing music on hold configuration
- Previewing what callers hear while waiting
- Checking queue status while hearing MOH
- Quick queue check without hanging up immediately

**Technical details:**
- Uses MOH class: `default`
- Timeout: 120 seconds
- Shows real-time queue count

---

### \*78 - Music on Hold Preview
**Purpose:** Hear exactly what callers hear when placed on hold

**What happens:**
1. Call answers
2. Plays music on hold for 120 seconds (2 minutes)
3. Automatically hangs up after 2 minutes or if you hang up first

**Use when:**
- Testing new MOH audio files
- Verifying MOH is working
- Checking audio quality of hold music
- Previewing before deploying to production

**Technical details:**
- Uses MOH class: `default`
- Timeout: 120 seconds
- Can be interrupted by hangup

**Configure MOH:**
- Files location: `/var/lib/asterisk/moh/`
- Config file: `/etc/asterisk/musiconhold.conf`
- Supports: Local files, Icecast, Shoutcast
- Formats: ulaw, gsm, wav

---

## 📬 Voicemail

### \*97 - Voicemail Main
**Purpose:** Access your voicemail box to listen to messages

**What happens:**
1. Call answers
2. Prompts for password (if not set, may skip)
3. Main voicemail menu:
   - Press **1** - Listen to new messages
   - Press **2** - Change folders
   - Press **3** - Advanced options
   - Press **0** - Mailbox options
   - Press **\*** - Exit

**Voicemail Options:**
- **During playback:**
  - **3** - Delete message
  - **5** - Repeat message
  - **6** - Next message
  - **7** - Previous message
  - **9** - Save to folder

**Technical details:**
- Uses `VoicemailMain(${CALLERID(num)}@flexpbx)`
- Automatically detects your extension
- Messages stored in: `/var/spool/asterisk/voicemail/flexpbx/[extension]/`
- Supports greetings, PIN, folders

**First time setup:**
- System may ask you to set a PIN
- Follow prompts to record your name
- Record unavailable and busy greetings

---

## 📊 Feature Code Usage Guide

### For Regular Users
**Daily use codes:**
- **\*97** - Check voicemail daily
- **\*44** - Quick time check

**When troubleshooting:**
- **\*43** - Test audio first
- **\*78** - Verify you can hear audio

---

### For Queue Agents
**At start of shift:**
1. Dial **\*43** - Test audio is working
2. Dial **\*48** - Check queue status
3. Dial **\*45** - Login to queue
4. Wait for calls!

**During shift:**
- Dial **\*48** - Check how many calls waiting
- Dial **\*97** - Check voicemail between calls

**At end of shift:**
1. Dial **\*46** - Logout from queue
2. Dial **\*48** - Verify you're logged out

---

### For Supervisors/Admins
**Monitoring:**
- **\*48** - Real-time queue status
- **\*77** - Queue status + MOH test
- **\*78** - Test hold music quality

**Testing:**
- **\*43** - Test audio path
- **\*44** - Verify system time
- **\*78** - Verify MOH working

---

## 🔧 Technical Details

### Extension Context
All feature codes are in context: `flexpbx-internal`

### Audio Files Used

**Queue prompts (custom):**
- `callcueue-login` - Login success (5.8 sec)
- `callcueue-logout` - Logout success (7.5 sec)
- `callcueue-loged-in-to-out-prompt` - Already logged in (8.3 sec)
- `callcueue-loged-out-to-in-prompt` - Already logged out (11.0 sec)

**System prompts (Asterisk default):**
- `agent-loginok` - Agent confirmation
- `queue-thereare` - "There are"
- `queue-callswaiting` - "calls waiting"
- Plus numbers 0-9 for SayNumber()

### Queue Configuration
- **Queue name:** `support`
- **Strategy:** ringall
- **Timeout:** 15 seconds per agent
- **Retry:** 5 seconds
- **Music on hold:** default class

### Voicemail Configuration
- **Context:** `flexpbx`
- **Mailboxes:** 2000-2003
- **Format:** wav
- **Max message:** 180 seconds
- **Max messages:** 100

---

## 🎯 Quick Troubleshooting

### Feature code not working?
1. Check dialplan loaded: `asterisk -rx "dialplan reload"`
2. Verify in context: `asterisk -rx "dialplan show flexpbx-internal"`
3. Check logs: `tail -f /var/log/asterisk/messages`

### Audio not playing?
1. Test with **\*43** first (echo test)
2. If echo works, test **\*44** (time announcement)
3. Check sound files: `ls /usr/share/asterisk/sounds/`
4. Verify permissions: Files owned by asterisk:asterisk

### Queue not working?
1. Check queue exists: `asterisk -rx "queue show support"`
2. Test login: Dial **\*45**
3. Check status: Dial **\*48**
4. Verify membership: `asterisk -rx "queue show support"`

### Voicemail not working?
1. Check module: `asterisk -rx "module show like voicemail"`
2. Should see: `app_voicemail_imap.so`
3. If not: `asterisk -rx "module load app_voicemail_imap.so"`
4. Test: Dial **\*97**

---

## 📝 Configuration Files

### Dialplan
**File:** `/etc/asterisk/extensions.conf`
**Section:** `[flexpbx-internal]`
**Lines:** 930-1011

### Music on Hold
**File:** `/etc/asterisk/musiconhold.conf`
**Classes configured:**
- default - Main MOH class

### Voicemail
**File:** `/etc/asterisk/voicemail.conf`
**Context:** `[flexpbx]`
**Mailboxes:** 2000, 2001, 2002, 2003

### Queue
**File:** `/etc/asterisk/queues.conf`
**Queues:**
- support - Main support queue
- sales - Sales queue

---

## 🔄 Reload Commands

**Reload dialplan (after changing feature codes):**
```bash
asterisk -rx "dialplan reload"
```

**Reload voicemail (after changing mailbox settings):**
```bash
asterisk -rx "module reload app_voicemail_imap.so"
```

**Reload MOH (after changing music files):**
```bash
asterisk -rx "module reload res_musiconhold.so"
```

**Reload queues (after changing queue settings):**
```bash
asterisk -rx "module reload app_queue.so"
```

---

## 🎓 User Training Guide

### Print and Post This:

```
╔════════════════════════════════════╗
║   FLEXPBX FEATURE CODES CHEAT SHEET   ║
╠════════════════════════════════════╣
║  *43  │ Echo Test (Test Audio)     ║
║  *44  │ Time/Date Announcement     ║
║  *45  │ Queue Login                ║
║  *46  │ Queue Logout               ║
║  *48  │ Queue Status               ║
║  *77  │ Queue Stats + Hold Music   ║
║  *78  │ Hold Music Preview         ║
║  *97  │ Voicemail                  ║
╚════════════════════════════════════╝
```

**Agent Quick Start:**
1. **\*45** to login (start of shift)
2. **\*48** to check queue anytime
3. **\*46** to logout (end of shift)
4. **\*97** to check voicemail

**If problems:**
- **\*43** to test audio
- Call supervisor for help

---

**System:** FlexPBX v1.0
**Support:** Check `/var/log/asterisk/messages` for errors
**Documentation:** This file + `/home/flexpbxuser/public_html/api/ASTERISK_API_INTEGRATION.md`
