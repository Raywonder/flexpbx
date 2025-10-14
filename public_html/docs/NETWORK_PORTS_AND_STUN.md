# FlexPBX Network Configuration
**Server:** 64.20.46.178
**Tailscale IP:** 100.64.0.2
**Date:** October 14, 2025

---

## 🔌 SIP & RTP Ports

### SIP Signaling Ports
| Port | Protocol | Purpose | Bind Address |
|------|----------|---------|--------------|
| **5060** | UDP | SIP Signaling (Public) | 64.20.46.178 |
| **5060** | TCP | SIP Signaling (Public) | 64.20.46.178 |
| **5060** | UDP | SIP Signaling (Tailscale) | 100.64.0.2 |
| **5160** | UDP | SIP Alternate Port 1 | 0.0.0.0 |
| **5260** | UDP | SIP Alternate Port 2 | 0.0.0.0 |
| **5061** | TLS | SIP Secure (disabled - no cert) | 0.0.0.0 |

### RTP Media Ports
| Port Range | Protocol | Purpose |
|------------|----------|---------|
| **10000-20000** | UDP | Real-time media (voice/video) |

**Note:** Firewall must allow UDP ports 10000-20000 for audio to work

---

## 🌐 STUN Servers (for NAT Traversal)

### Primary STUN Server
```
Server: stun.l.google.com
Port: 19302
Protocol: UDP
```

### Alternative STUN Servers
If you need to configure your SIP phone/softphone with STUN:

```
stun.l.google.com:19302           (Google - Primary)
stun1.l.google.com:19302          (Google - Backup 1)
stun2.l.google.com:19302          (Google - Backup 2)
stun3.l.google.com:19302          (Google - Backup 3)
stun4.l.google.com:19302          (Google - Backup 4)

stun.voip.blackberry.com:3478     (Blackberry)
stun.ekiga.net:3478                (Ekiga)
stun.ideasip.com:3478              (IdeaSIP)
stun.voipbuster.com:3478           (VoIPBuster)
stun.voipstunt.com:3478            (VoIPStunt)
```

---

## 📱 SIP Phone Configuration

### For Public Internet Connection
```
Server/Proxy: 64.20.46.178
Port: 5060
Transport: UDP
Username: 2000 (or 2001, 2002, 2003)
Password: [Your extension password]
```

### For Tailscale VPN Connection
```
Server/Proxy: 100.64.0.2
Port: 5060
Transport: UDP
Username: 2000 (or 2001, 2002, 2003)
Password: [Your extension password]
```

### Recommended Phone Settings
```
STUN Server: stun.l.google.com:19302
Enable ICE: Yes
RTP Symmetric: Yes
NAT Traversal: Enabled
Codec Priority:
  1. ulaw (G.711 μ-law)
  2. alaw (G.711 A-law)
  3. gsm
```

---

## 🔐 Firewall Rules

### Incoming Traffic (Allow)
```bash
# SIP Signaling
-A INPUT -p udp --dport 5060 -j ACCEPT
-A INPUT -p tcp --dport 5060 -j ACCEPT
-A INPUT -p udp --dport 5160 -j ACCEPT
-A INPUT -p udp --dport 5260 -j ACCEPT

# RTP Media
-A INPUT -p udp --dport 10000:20000 -j ACCEPT

# STUN (if needed)
-A OUTPUT -p udp --dport 19302 -j ACCEPT
-A OUTPUT -p udp --dport 3478 -j ACCEPT
```

### CSF Firewall (Current System)
```bash
# Add to /etc/csf/csf.conf
TCP_IN = "5060,5061,22,80,443,..."
TCP_OUT = "5060,5061,22,80,443,..."
UDP_IN = "5060,5160,5260,10000:20000"
UDP_OUT = "5060,5160,5260,10000:20000,3478,19302"

# Then restart CSF
csf -r
```

---

## 🧪 Test Extensions

All test extensions now working after dialplan reload:

### Queue Audio Tests
- **\*451** - Test "callcueue-login" (login success)
- **\*452** - Test "callcueue-loged-in-to-out-prompt" (already logged in)
- **\*453** - Test "callcueue-logout" (logout success)
- **\*454** - Test "callcueue-loged-out-to-in-prompt" (already logged out)
- **\*455** - Test "agent-loginok" (system file - confirms audio path works)

### Queue Functions
- **\*45** - Login to support queue
- **\*46** - Logout from support queue
- **\*48** - Check queue status

### Other Features
- **\*97** - Voicemail main
- **9196** - Echo test

---

## 🐛 Troubleshooting No Audio

### Step 1: Verify Basic Connectivity
```bash
# From your computer, test SIP port
nc -zv 64.20.46.178 5060

# Test RTP port range
nc -zvu 64.20.46.178 10000
```

### Step 2: Test Echo Extension
Dial **9196** - you should hear yourself with a slight delay
- If you hear yourself → Audio path is working
- If silent → Check firewall/NAT

### Step 3: Test System Audio File
Dial **\*455** - should hear "Agent Login OK"
- If you hear it → Queue audio files may have issue
- If silent → RTP issue

### Step 4: Check Phone Settings
Ensure your softphone has:
- STUN enabled: `stun.l.google.com:19302`
- NAT traversal enabled
- Correct codecs: ulaw, alaw, gsm

### Step 5: Check Firewall
```bash
# On server, check if ports are open
netstat -tulpn | grep asterisk

# Test STUN connectivity
nc -zvu stun.l.google.com 19302
```

---

## 📊 Current Asterisk NAT Settings

All endpoints configured with optimal NAT settings:

```ini
rtp_symmetric=yes          # Send RTP to source of incoming RTP
force_rport=yes            # Force use of rport in Via header
rewrite_contact=yes        # Rewrite Contact header with source IP
direct_media=no            # Keep RTP flowing through Asterisk
ice_support=yes            # Enable ICE for NAT traversal
```

---

## 🔄 Configuration Files

### /etc/asterisk/rtp.conf
```ini
[general]
rtpstart=10000
rtpend=20000
strictrtp=no
stunaddr=stun.l.google.com:19302
```

### /etc/asterisk/pjsip.conf (Transport)
```ini
[transport-udp]
type=transport
protocol=udp
bind=64.20.46.178:5060
external_media_address=64.20.46.178
external_signaling_address=64.20.46.178

[transport-tailscale]
type=transport
protocol=udp
bind=100.64.0.2:5060
local_net=100.64.0.0/10
```

### /etc/asterisk/pjsip.conf (Endpoint Example)
```ini
[2001]
type=endpoint
context=flexpbx-internal
disallow=all
allow=ulaw,alaw,gsm
transport=transport-udp
direct_media=no
rtp_symmetric=yes
force_rport=yes
rewrite_contact=yes
ice_support=yes
```

---

## 📞 Recommended SIP Apps

### Desktop
- **Zoiper** (Windows/Mac/Linux) - Best overall
- **MicroSIP** (Windows) - Lightweight
- **Linphone** (All platforms) - Open source

### Mobile
- **Zoiper** (iOS/Android) - Professional
- **Linphone** (iOS/Android) - Free & open source
- **Bria** (iOS/Android) - Enterprise grade

### Configuration Tips for Apps
1. Set STUN server in app settings
2. Enable "Push notifications" for incoming calls
3. Set "Keep alive" to 60 seconds
4. Enable "Echo cancellation"
5. Set audio codec to "ulaw" as first choice

---

## 🎯 Quick Test Sequence

Once connected, test in this order:

1. **Dial 9196** (Echo test)
   - You should hear yourself → Audio works!

2. **Dial \*455** (System audio)
   - You should hear "Agent Login OK" → File playback works!

3. **Dial \*451** (Queue login audio)
   - You should hear queue login message → Queue files work!

4. **Dial \*45** (Actually login to queue)
   - Should add you to queue and play message

5. **Dial \*46** (Logout from queue)
   - Should remove you and play message

---

## 📝 Notes

- **Strict RTP** is currently set to "no" to help with NAT issues
- **ICE support** is enabled for WebRTC compatibility
- **STUN** is configured but Asterisk may not show it in CLI (this is normal)
- **Tailscale transport** is working on 100.64.0.2:5060
- All **test extensions** reload after each Asterisk restart

---

**Last Updated:** October 14, 2025 01:35 AM
**Status:** All configurations active, dialplan loaded, ready for testing
