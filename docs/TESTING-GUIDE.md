# FlexPBX Testing Guide - Callcentric Integration

## Test Extension Configuration

### Extension 2001 - Senior Tech Support (PRIMARY TEST EXTENSION)
```
Extension: 2001
Username: techsupport1
Password: Support2001!
Server: flexpbx.devinecreations.net
Port: 5070
Domain: flexpbx.devinecreations.net
Transport: UDP
Codec: G.722, ulaw, alaw
DTMF: RFC2833
```

### SIP Client Setup (Zoiper, Linphone, etc.)
1. Open your SIP client
2. Create new account with settings above
3. Enable registration
4. Test registration status

## Callcentric Trunk Configuration

### Current Setup
- Provider: CallCentric
- SIP Server: sip.callcentric.com:5060
- Username: [YOUR_CALLCENTRIC_DID]102
- Auth Name: raywonder
- Status: Active (as of config)

### Inbound Routing
When someone calls your Callcentric DID:
1. Call arrives at FlexPBX
2. Routes to IVR Extension 101 (Main Menu)
3. Caller hears main greeting
4. Options available via DTMF

## IVR System Testing

### Main IVR (Extension 101)
**Internal Dial:** Call 101 from extension 2001

**IVR Menu Options:**
- Press 1 → Sales Queue (extensions 1001-1003)
- Press 2 → Tech Support Queue (extensions 2001-2003)
- Press 3 → Billing (extension 2000)
- Press 4 → Direct to extension 2001
- Press 7 → Accessibility Support (extension 2004)
- Press 0 → Operator (extension 2001)
- Timeout → Routes to extension 2001

### Required Media Files for IVR
Upload these to **Media Manager** (`/admin/media-manager.html`):

1. **main-greeting.wav**
   - "Thank you for calling FlexPBX. For Sales press 1, for Support press 2..."

2. **sales-greeting.wav**
   - "Thank you for contacting sales. Please hold..."

3. **support-greeting.wav**
   - "Technical support. Your call is important to us..."

4. **accessibility-greeting.wav**
   - "Accessibility support. Connecting you now..."

## Music on Hold Testing

### MOH Classes Configured
1. **corporate** - Sales queue
2. **ambient** - Tech support queue

### Upload MOH Files
Go to: `https://flexpbx.devinecreations.net/admin/media-manager.html`
1. Switch to "Music on Hold" tab
2. Upload WAV/MP3 files
3. Name them: `corporate.wav`, `ambient.wav`

### Test MOH
1. Call extension 2001 from another extension
2. Ask to be put on hold
3. Verify music plays

## Extension-to-Extension Testing

### Internal Calling
From Extension 2001, dial:
- **1000-1009** - Sales department
- **2000-2009** - Support department
- **8000-8003** - Conference rooms

### Conference Room Testing
1. Dial 8000 (Main Conference - 50 capacity)
2. Dial 8001 (Sales Meeting - 20 capacity)
3. Dial 8002 (Support Team - 15 capacity)
4. Dial 8003 (Training Room - 30 capacity)

## Outbound Calling via Callcentric

### Dial Patterns
From Extension 2001:
- **9 + 10-digit number** - US/Canada call
  - Example: 9-555-123-4567
- **9 + 1 + 10-digit** - Long distance
  - Example: 9-1-555-123-4567
- **9 + 011 + country + number** - International
  - Example: 9-011-44-20-1234-5678
- **911** - Emergency (routes via Callcentric)

### Test Call Flow
1. Register extension 2001 on SIP client
2. Dial: 9-YOUR_MOBILE_NUMBER
3. Call should route through Callcentric trunk
4. Your mobile should ring with Caller ID from Callcentric DID

## Special Feature Codes

### Voicemail
- **\*97** - Access voicemail
- Dial from extension 2001 to check messages

### System Tests
- **9196** - Echo test (dial and hear yourself back)
- **\*60** - Time/Date announcement
- **\*78** - Do Not Disturb ON
- **\*79** - Do Not Disturb OFF
- **\*72** - Call Forwarding ON
- **\*73** - Call Forwarding OFF

## Testing Checklist

### Phase 1: Registration
- [ ] Extension 2001 registers successfully
- [ ] Check SIP registration status
- [ ] Verify bidirectional audio path

### Phase 2: Internal Calls
- [ ] Call another extension (e.g., 2002)
- [ ] Test call transfer
- [ ] Test call hold
- [ ] Test 3-way calling

### Phase 3: IVR System
- [ ] Call extension 101
- [ ] Hear main greeting
- [ ] Test all DTMF menu options
- [ ] Verify proper routing

### Phase 4: Music on Hold
- [ ] Upload MOH files via media manager
- [ ] Place call on hold
- [ ] Verify music plays
- [ ] Test MOH class switching

### Phase 5: Outbound via Callcentric
- [ ] Dial out using 9 prefix
- [ ] Verify call connects
- [ ] Check audio quality (both ways)
- [ ] Verify Caller ID display

### Phase 6: Inbound via Callcentric
- [ ] Call your Callcentric DID from mobile
- [ ] Verify IVR answers
- [ ] Navigate menu to reach extension
- [ ] Complete the call

### Phase 7: Queue Testing
- [ ] Join sales queue (press 1 from IVR)
- [ ] Verify position announcement
- [ ] Verify hold music
- [ ] Test callback option

## Quick Links

- **Media Manager:** https://flexpbx.devinecreations.net/admin/media-manager.html
- **Admin Panel:** https://flexpbx.devinecreations.net/admin/
- **Extensions Manager:** https://flexpbx.devinecreations.net/admin/admin-extensions-management.html
- **Trunks Manager:** https://flexpbx.devinecreations.net/admin/admin-trunks-management.html

## Troubleshooting

### No Audio
- Check firewall: UDP ports 10000-20000 (RTP)
- Check NAT settings in extension config
- Verify codec compatibility (G.722, ulaw)

### Registration Fails
- Verify server: flexpbx.devinecreations.net
- Verify port: 5070
- Check username/password
- Check network connectivity

### IVR Not Working
- Verify media files uploaded to /media/sounds/
- Check file names match config
- Verify file format: 16-bit PCM WAV, 8kHz mono

### Outbound Calls Fail
- Verify Callcentric credentials configured
- Check trunk registration status
- Verify dial pattern (must start with 9)
- Check Callcentric account balance

## Support

For issues:
1. Check error_log in /home/flexpbxuser/public_html/
2. Run self-check: `php api/admin-self-check.php`
3. Check server status: https://flexpbx.devinecreations.net/api/status

---

**Last Updated:** 2025-10-13
**Test Extension:** 2001 (techsupport1)
**Trunk Provider:** Callcentric
**Status:** Ready for Testing
