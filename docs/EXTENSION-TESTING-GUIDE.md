# 📞 FlexPBX Extension Testing Guide

## 🚀 **Ready to Test Extensions**

### **1. First - Run Admin Self-Check:**
Upload `admin-self-check.php` to `/api/` and run:
```bash
# Via web browser:
https://flexpbx.devinecreations.net/api/admin-self-check.php

# Via SSH:
cd /home/flexpbxuser/public_html
php api/admin-self-check.php
```

This will:
- ✅ Automatically `chmod 755` all scripts
- ✅ Verify all configuration files
- ✅ Check admin interface readiness
- ✅ Confirm test extension setup

### **2. Test Extension 2001 Configuration:**
```
Extension: 2001
Username: techsupport1
Password: Support2001!
Server: flexpbx.devinecreations.net
Port: 5070
Domain: flexpbx.local
```

### **3. SIP Client Setup:**
**Recommended Clients:**
- **Desktop:** X-Lite, Zoiper, 3CX Phone
- **Mobile:** Zoiper, Linphone
- **Web:** WebRTC SIP client

**Settings:**
- **Account Name:** FlexPBX Test
- **User ID:** techsupport1
- **Password:** Support2001!
- **Domain:** flexpbx.devinecreations.net
- **Port:** 5070
- **Transport:** UDP
- **STUN Server:** stun.l.google.com:19302 (optional)

### **4. Test Call Scenarios:**

#### **Internal Calls:**
- **Call 101** → Main IVR (test menu navigation)
- **Call 1000** → Sales Manager
- **Call 1001** → Sales Rep 1
- **Call 2000** → Support Manager
- **Call 8000** → Main Conference Room

#### **Queue Testing:**
- **Call 101, Press 1** → Sales Queue (corporate hold music)
- **Call 101, Press 2** → Support Queue (ambient hold music)
- **Call 101, Press 4** → Direct to extension 2001

#### **Audio Quality Tests:**
- **Call 9196** → Echo test (verify audio path)
- **Dial *97** → Voicemail access
- **Test DTMF:** Press all digits 0-9, *, # during calls

#### **Conference Testing:**
- **Call 8000** → Join main conference (up to 50 participants)
- **Call 8001** → Sales meeting room
- **Call 8002** → Support team room

### **5. External SIP Testing:**
From Callcentric or other SIP provider:
```
sip:101@flexpbx.devinecreations.net    (Main IVR)
sip:2001@flexpbx.devinecreations.net   (Your test extension)
sip:8000@flexpbx.devinecreations.net   (Conference room)
```

### **6. Outbound Testing:**
From extension 2001:
- **US/Canada:** Dial `9 + 1 + 10-digit number`
- **International:** Dial `9 + 011 + country + number`
- **Emergency:** Dial `911` (routes via Callcentric)

### **7. Admin Panel Testing:**
**Trunk Management:**
- https://flexpbx.devinecreations.net/admin/admin-trunks-management.html
- Test trunk status, edit passwords, verify connectivity

**Extension Management:**
- https://flexpbx.devinecreations.net/admin/admin-extensions-management.html
- View extension status, edit passwords, manage permissions

**Google Voice Integration:**
- https://flexpbx.devinecreations.net/admin/admin-google-voice.html
- Test OAuth setup, SMS management, voicemail

### **8. Expected Results:**
- ✅ **Registration:** Extension registers within 5 seconds
- ✅ **Audio Quality:** Clear HD audio with G.722 codec
- ✅ **DTMF Recognition:** Immediate response to button presses
- ✅ **Hold Music:** Starts within 3 seconds when placed on hold
- ✅ **Queue Announcements:** Position announcements every 30 seconds
- ✅ **Conference Audio:** Clear multi-party audio mixing

### **9. Troubleshooting:**
**Can't Register:**
- Check firewall allows port 5070
- Verify extension 2001 is enabled in config
- Try different SIP client

**No Audio:**
- Check NAT/firewall for RTP ports 10000-20000
- Enable STUN in SIP client
- Try different codec (G.711 instead of G.722)

**Can't Make Calls:**
- Verify FlexPBX service is running
- Check extension has outbound permissions
- Test with internal calls first (101, 8000)

## 🎯 **Ready to Test!**

Everything is configured and ready. Start with extension 2001 registration, then test internal calls to 101 (IVR) and 8000 (conference room).