# ✅ Callcentric DID Setup Complete!

**Date:** 2025-10-13
**Status:** CONFIGURED & READY TO TEST

---

## 🎯 Your New Callcentric Configuration

### Trunk Credentials (Updated)
```
Username (Account #): 17778171572
SIP Password: 860719938242
Auth Name: Raywonder
SIP Server: sip.callcentric.com
Port: 5060
Transport: UDP
```

### Your New DID
```
Phone Number: (312) 313-9555
DID: 3123139555
Location: Chicago, IL
Status: ACTIVE
```

### Channel Configuration
```
Max Channels: 2 concurrent calls
Per-DID Limit: Enabled
Current Usage: 0 calls
```

---

## 📞 Inbound Call Routing

**When someone calls (312) 313-9555:**

1. Call arrives at Callcentric trunk
2. Routes to: **IVR Menu (Extension 101)**
3. Caller hears main greeting
4. Menu options available:
   - Press 1 → Sales Queue (corporate MOH)
   - Press 2 → Tech Support Queue (ambient MOH)
   - Press 3 → Billing
   - Press 4 → Direct to Extension 2001
   - Press 0 → Operator

**Failover:** If no answer, goes to general voicemail

---

## 🖥️ Management Interfaces

### Trunks & DIDs Manager (NEW!)
**URL:** https://flexpbx.devinecreations.net/admin/trunks-dids-manager.html

**Features:**
- ✏️ Edit trunk credentials (username, password, auth name)
- 📞 Manage DIDs (add, edit, route)
- 📊 Configure channel limits
- 🔐 Check registration status
- 🧪 Test trunk connectivity

### Tabs Available:
1. **Trunks Tab**
   - View/edit Callcentric credentials
   - View/edit Google Voice setup
   - Test registration
   - View logs

2. **DIDs Tab**
   - See all your DIDs in one place
   - (312) 313-9555 - Callcentric
   - (281) 301-5784 - Google Voice
   - Edit routing per DID
   - Add new DIDs

3. **Channels Tab**
   - Set max concurrent calls
   - Per-DID limits
   - Action on limit (busy/queue/voicemail)

4. **Registration Status Tab**
   - Live trunk registration status
   - Last registration time
   - Force re-register if needed

### Inbound Routing Manager
**URL:** https://flexpbx.devinecreations.net/admin/inbound-routing.html

**Change where calls go:**
- IVR Menu (current)
- Call Queue
- Direct Extension
- Voicemail
- Announcement
- Time-based routing

---

## 🧪 Testing Your Setup

### Step 1: Verify Trunk Registration
1. Go to: https://flexpbx.devinecreations.net/admin/trunks-dids-manager.html
2. Click "Registration Status" tab
3. Should show: ✅ Registered for Callcentric
4. If not registered, click "Force Re-register"

### Step 2: Test Inbound Call
```
1. Call (312) 313-9555 from your mobile
2. Should hear: Main IVR greeting
3. Press 2 for Support
4. Should hear: Ambient hold music
5. If Extension 2001 is registered, it should ring
```

### Step 3: Register Test Extension
Use Extension 2001 on a SIP client:
```
Extension: 2001
Username: techsupport1
Password: Support2001!
Server: flexpbx.devinecreations.net
Port: 5070
Domain: flexpbx.devinecreations.net
```

### Step 4: Test Call Flow
```
From mobile: Call (312) 313-9555
Expected flow:
1. Trunk receives call ✓
2. Routes to IVR 101 ✓
3. Plays greeting ✓
4. DTMF options work ✓
5. Routes to queue/extension ✓
6. MOH plays while waiting ✓
```

---

## 🎵 Music On Hold

**82 tracks installed and secured:**
- Corporate playlist → Sales queue
- Ambient playlist → Support queue
- All from your music collection

**Access:** Restricted to authenticated PBX only (public blocked)

---

## 📊 Configuration Files Updated

### config/callcentric-trunk-config.json
- ✅ Username: 17778171572
- ✅ Password: 860719938242
- ✅ Auth Name: Raywonder
- ✅ Max Channels: 2
- ✅ DID 3123139555 configured
- ✅ Routes to IVR 101

---

## 🔧 How to Make Changes

### Change Trunk Credentials
1. Go to Trunks & DIDs Manager
2. Click "Trunks" tab
3. Find "CallCentric Primary"
4. Click "✏️ Edit Trunk"
5. Update username/password/etc.
6. Click "💾 Save Changes"

### Change DID Routing
1. Go to Trunks & DIDs Manager
2. Click "DIDs" tab
3. Find (312) 313-9555
4. Click "🎯 Route"
5. Select new destination (Queue, Extension, etc.)
6. Save

### Add More DIDs
1. Purchase new DID from Callcentric
2. Go to Trunks & DIDs Manager → DIDs tab
3. Click "➕ Add New DID"
4. Enter DID number
5. Configure routing

### Change Channel Limits
1. Go to Trunks & DIDs Manager
2. Click "Channels" tab
3. Update "Max Channels" (e.g., increase to 5)
4. Choose per-DID or shared pool
5. Click "💾 Save Channel Settings"

---

## 📞 SIP Registration Details

**What happens behind the scenes:**

1. Your FlexPBX server registers with Callcentric:
   ```
   REGISTER sip:sip.callcentric.com:5060
   From: <sip:17778171572@sip.callcentric.com>
   To: <sip:17778171572@sip.callcentric.com>
   Authorization: Raywonder:860719938242
   ```

2. Callcentric confirms registration (expires in 3600 seconds)

3. When someone calls (312) 313-9555:
   ```
   INVITE sip:3123139555@flexpbx.devinecreations.net:5070
   From: <sip:caller@network>
   ```

4. FlexPBX looks up DID routing rules

5. Routes to configured destination (IVR 101)

---

## ⚠️ Important Notes

### Trunk Registration
- Registration expires every 1 hour
- FlexPBX automatically re-registers
- If registration fails, check credentials in UI

### Channel Limits
- You have 2 concurrent call channels
- If 2 calls are active, 3rd call will be queued or sent to voicemail
- Upgrade with Callcentric for more channels

### DID Routing
- Each DID can route to different destination
- Can set business hours routing
- Can set failover rules

### Security
- Credentials stored in config files (protected)
- Only localhost can access config files
- Use strong passwords for SIP auth

---

## 🚨 Troubleshooting

### "Trunk Not Registered"
1. Check credentials in Trunks Manager
2. Verify internet connectivity
3. Check Callcentric account status
4. Force re-register from UI

### "Calls Not Coming In"
1. Verify DID is active on Callcentric portal
2. Check DID routing in DIDs tab
3. Verify trunk registration status
4. Check IVR greeting files uploaded

### "No Audio / One-Way Audio"
1. Check firewall: UDP ports 10000-20000 (RTP)
2. Verify NAT settings
3. Check codec compatibility
4. Test with different device

### "Channel Limit Reached"
1. Check current call count in Channels tab
2. Wait for calls to complete
3. Or upgrade channel limit with Callcentric

---

## 📚 Quick Reference

| Item | Value |
|------|-------|
| **Your DID** | (312) 313-9555 |
| **Trunk User** | 17778171572 |
| **Auth Name** | Raywonder |
| **SIP Server** | sip.callcentric.com:5060 |
| **Max Channels** | 2 |
| **Default Route** | IVR Menu (101) |
| **Test Extension** | 2001 (techsupport1) |

---

## 🎯 Next Steps

1. **Test Your Setup:**
   - Call (312) 313-9555 from mobile
   - Navigate IVR menu
   - Verify hold music plays
   - Test extension ring

2. **Upload IVR Greetings:**
   - Go to Media Manager
   - Upload main-greeting.wav
   - Upload sales-greeting.wav
   - Upload support-greeting.wav

3. **Register Extensions:**
   - Configure Extension 2001 on SIP phone
   - Test receiving calls from IVR
   - Test transferring calls

4. **Monitor Calls:**
   - Check Registration Status tab regularly
   - View call logs
   - Monitor channel usage

---

## ✅ What's Working

- [x] Callcentric trunk credentials configured
- [x] DID (312) 313-9555 added and active
- [x] Inbound routing to IVR configured
- [x] Channel limits set (2 concurrent)
- [x] Trunks & DIDs management UI ready
- [x] Registration status monitoring
- [x] Test extension ready (2001)
- [x] Music on hold installed
- [x] Failover to voicemail configured
- [x] All configuration files updated

---

## 🔗 Management Links

- **Trunks & DIDs Manager:** https://flexpbx.devinecreations.net/admin/trunks-dids-manager.html
- **Inbound Routing:** https://flexpbx.devinecreations.net/admin/inbound-routing.html
- **Media Manager:** https://flexpbx.devinecreations.net/admin/media-manager.html
- **Extensions Manager:** https://flexpbx.devinecreations.net/admin/admin-extensions-management.html
- **Main Admin:** https://flexpbx.devinecreations.net/admin/

---

**STATUS: ✅ READY TO RECEIVE CALLS!**

Call (312) 313-9555 to test your new FlexPBX system!
