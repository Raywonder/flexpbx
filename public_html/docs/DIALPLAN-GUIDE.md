# FlexPBX Dialplan Guide

**Updated:** 2025-10-13
**Outbound Prefix:** 09

---

## 📞 Outbound Dialing

### Standard Outbound Calls (Using 09 Prefix)

**Format:** `09 + Phone Number`

| Example | What You Dial | What Gets Called | Description |
|---------|--------------|------------------|-------------|
| **US Local** | `093364626141` | `+1-336-462-6141` | 09 + 10-digit number |
| **US/Canada** | `095551234567` | `+1-555-123-4567` | 09 + 10-digit number |
| **With 1 prefix** | `0915551234567` | `+1-555-123-4567` | 09 + 1 + 10-digit |
| **Toll-free** | `098001234567` | `+1-800-123-4567` | 09 + 10-digit toll-free |

### International Dialing

**Format:** `09 + 011 + Country Code + Number`

| Destination | Example | Description |
|-------------|---------|-------------|
| **UK** | `09011442012345678` | 09 + 011 + 44 + number |
| **Mexico** | `0901152555123456` | 09 + 011 + 52 + number |
| **Japan** | `0901181312345678` | 09 + 011 + 81 + number |
| **Australia** | `09011612345678` | 09 + 011 + 61 + number |

### Service Codes

**Format:** `09 + Service Code`

| Service | What You Dial | Description |
|---------|--------------|-------------|
| **Directory** | `09411` | Directory assistance |
| **Operator** | `090` | Operator assistance |
| **Information** | `09611` | General information |

### Emergency Calls

**Format:** `911` (NO PREFIX NEEDED!)

| Emergency | What You Dial | Description |
|-----------|--------------|-------------|
| **Emergency** | `911` | Direct 911 (bypasses prefix) |
| **Police** | `911` | Emergency services |
| **Fire** | `911` | Emergency services |

**⚠️ IMPORTANT:** Emergency calls (911) work WITHOUT the 09 prefix for safety!

---

## 📱 Internal Dialing (NO PREFIX)

### Extensions
**Format:** Just dial the extension number

| Range | Description | Example |
|-------|-------------|---------|
| **1000-1009** | Sales Department | Dial `1001` |
| **2000-2009** | Support Department | Dial `2001` |
| **8000-8009** | Conference Rooms | Dial `8000` |
| **101-199** | IVR Menus | Dial `101` |

### Special Codes

| Code | Function | Description |
|------|----------|-------------|
| `*97` | Voicemail | Access your voicemail |
| `9196` | Echo Test | Hear yourself (audio test) |
| `*60` | Time/Date | Hear current time |
| `*78` | DND On | Enable Do Not Disturb |
| `*79` | DND Off | Disable Do Not Disturb |
| `*72` | Call Forward On | Enable call forwarding |
| `*73` | Call Forward Off | Disable call forwarding |

---

## 🔧 Dialplan Configuration

### How It Works

1. **You dial:** `093364626141`
2. **PBX strips:** `09` prefix
3. **PBX adds:** `1` for North America
4. **Sends to trunk:** `13364626141`
5. **Callcentric dials:** `+1-336-462-6141`

### Pattern Matching (Asterisk Format)

```
_09NXXNXXXXXX    = 09 + 10-digit (3364626141)
_091NXXNXXXXXX   = 09 + 1 + 10-digit (13364626141)
_09011X.         = 09 + 011 + international
_09[2-9]11       = 09 + service codes (411, 611)
_911             = Emergency (no prefix)
```

**Legend:**
- `_` = Pattern start
- `N` = Digit 2-9
- `X` = Any digit 0-9
- `.` = One or more digits
- `[2-9]` = Range of digits

---

## 💡 Quick Examples

### Example 1: Call Mobile Phone
```
Your mobile: (336) 462-6141
From extension 2001, dial: 093364626141
✓ Call goes through Callcentric
✓ Your mobile rings
✓ Caller ID shows your Callcentric DID
```

### Example 2: Call Business
```
Business: (555) 123-4567
From extension 2001, dial: 095551234567
✓ Routes through Callcentric
✓ Business phone rings
```

### Example 3: International Call to UK
```
UK Number: +44 20 1234 5678
From extension 2001, dial: 09011442012345678
✓ Routes via Callcentric international
✓ Charges apply per Callcentric rates
```

### Example 4: Internal Call
```
Call support agent
From extension 1001, dial: 2001
✓ Direct internal call (no charges)
✓ No prefix needed
```

### Example 5: Join Conference
```
Join main conference
From any extension, dial: 8000
✓ Enters conference room
✓ No prefix needed
```

---

## 🚨 Common Mistakes

### ❌ Wrong: `93364626141`
**Missing leading 0** - won't dial out

### ❌ Wrong: `0093364626141`
**Too many zeros** - won't match pattern

### ❌ Wrong: `913364626141`
**Missing the 0** - won't route

### ✅ Correct: `093364626141`
**Perfect!** - will dial out

### ✅ Also Correct: `0913364626141`
If you prefer including the 1 - also works!

---

## 🎯 Testing Your Dialplan

### Test 1: Echo Test (Internal)
```
From extension 2001:
Dial: 9196
Expected: Hear yourself back
Result: Confirms audio path working
```

### Test 2: Outbound to Your Mobile
```
From extension 2001:
Dial: 09 + your mobile number
Expected: Your mobile rings
Result: Confirms outbound calling works
```

### Test 3: Internal Extension
```
From extension 2001:
Dial: 1001
Expected: Extension 1001 rings
Result: Confirms internal routing
```

---

## ⚙️ Modifying the Dialplan

### To Change Outbound Prefix

1. Go to: https://flexpbx.devinecreations.net/admin/trunks-dids-manager.html
2. Click "Trunks" tab
3. Edit CallCentric trunk
4. Modify dialpatterns
5. Save

### Common Prefix Options

| Prefix | Use Case |
|--------|----------|
| `09` | Current (your preference) |
| `9` | Traditional PBX style |
| `00` | International style |
| `8` | Alternative |
| None | Direct dial (10 digits) |

### To Add New Dial Pattern

Edit: `config/callcentric-trunk-config.json`

Add to `dialpatterns.outbound`:
```json
{
  "pattern": "_09XXXXXXX",
  "strip": "09",
  "prepend": "",
  "description": "Your custom pattern"
}
```

---

## 📊 Dialplan Flowchart

```
User Dials Number
        |
        v
   Starts with 09?
      /    \
    YES    NO
     |      |
     v      v
  Outbound  Internal?
   Call      /  \
     |     YES  NO
     v      |    |
  Strip 09  Route Error
     |      Internally
     v         |
  Add 1 if    Ring
   needed   Extension
     |
     v
  Send to
 Callcentric
     |
     v
   Call
  Connects!
```

---

## 🔐 Security & Fraud Prevention

### Restrictions

1. **09 prefix required** - prevents accidental international dialing
2. **Emergency always works** - 911 doesn't need prefix
3. **Internal calls free** - no charges for extension-to-extension
4. **Channel limits** - max 2 concurrent calls

### Best Practices

- Don't share extension passwords
- Monitor call logs regularly
- Review channel usage
- Set per-extension outbound permissions (coming soon)

---

## 📞 Quick Dial Reference Card

**Print this for your desk!**

```
┌─────────────────────────────────────┐
│     FLEXPBX QUICK DIAL GUIDE        │
├─────────────────────────────────────┤
│ OUTBOUND (via Callcentric):         │
│ • US/Canada: 09 + 10-digit          │
│   Example: 093364626141             │
│                                     │
│ • International: 09 + 011 + number  │
│   Example: 09011442012345678        │
│                                     │
│ • Emergency: 911 (no prefix!)       │
│                                     │
│ INTERNAL (free):                    │
│ • Extensions: Just dial number      │
│   Sales: 1000-1009                  │
│   Support: 2000-2009                │
│   Conferences: 8000-8009            │
│                                     │
│ • IVR Menu: 101                     │
│ • Voicemail: *97                    │
│ • Echo Test: 9196                   │
│                                     │
│ Your Extension: ___________         │
│ Password: ___________               │
└─────────────────────────────────────┘
```

---

## 🛠️ Troubleshooting

### "Cannot Complete Call"
**Check:**
1. Did you use 09 prefix? ✓
2. Is number valid? ✓
3. Trunk registered? Check Trunks Manager
4. Channels available? Check Channels tab

### "Fast Busy Signal"
**Possible causes:**
1. Invalid dialplan pattern
2. Trunk not registered
3. All channels in use
4. Invalid destination number

### "No Audio"
**Check:**
1. Firewall: UDP 10000-20000
2. NAT settings
3. Codec compatibility
4. Run echo test (9196)

---

## 📚 Additional Resources

- **Trunks Manager:** https://flexpbx.devinecreations.net/admin/trunks-dids-manager.html
- **Call Logs:** Check for dial pattern issues
- **Asterisk Patterns:** [Asterisk Dialplan Documentation]

---

**Summary:** Dial `09` + phone number for outbound calls via Callcentric!

**Your Chicago DID:** (312) 313-9555
**Test Extension:** 2001 (techsupport1 / Support2001!)
