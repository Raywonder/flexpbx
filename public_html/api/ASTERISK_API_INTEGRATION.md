# Asterisk API Integration Guide
## FlexPBX Complete Feature Hookup Reference

**Date:** October 14, 2025
**Asterisk Version:** 18.12.1
**Purpose:** Comprehensive guide for integrating all Asterisk features via API

---

## 🔌 Connection Methods

### 1. Asterisk Manager Interface (AMI)
**Connection:** TCP Socket on port 5038
**Authentication:** Username/Password
**Protocol:** Action/Response based

```php
// PHP AMI Connection
$socket = fsockopen('127.0.0.1', 5038, $errno, $errstr, 10);
fwrite($socket, "Action: Login\r\nUsername: admin\r\nSecret: password\r\n\r\n");
```

### 2. Asterisk Gateway Interface (AGI)
**Connection:** STDIN/STDOUT or TCP socket
**Use:** Call control from dialplan
**Protocol:** Command/Response

```php
// FastAGI Server
$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_bind($socket, '0.0.0.0', 4573);
socket_listen($socket);
```

### 3. Asterisk REST Interface (ARI)
**Connection:** WebSocket + HTTP REST API
**Port:** 8088 (HTTP), 8089 (HTTPS)
**Authentication:** Basic Auth

```bash
curl -u username:password http://localhost:8088/ari/channels
```

### 4. CLI Interface
**Connection:** Shell command execution
**Method:** `asterisk -rx "command"`

```bash
asterisk -rx "core show channels"
```

---

## 📞 Core PBX Features

### Call Control

#### Originate Call
```php
// AMI Action
Action: Originate
Channel: PJSIP/2001
Exten: 2002
Context: flexpbx-internal
Priority: 1
CallerID: FlexPBX <2001>
Timeout: 30000
```

#### Hangup Call
```php
Action: Hangup
Channel: PJSIP/2001-00000001
Cause: 16
```

#### Transfer Call
```php
// Attended Transfer
Action: Atxfer
Channel: PJSIP/2001-00000001
Exten: 2002
Context: flexpbx-internal

// Blind Transfer
Action: Redirect
Channel: PJSIP/2001-00000001
Exten: 2002
Context: flexpbx-internal
Priority: 1
```

#### Park Call
```php
Action: Park
Channel: PJSIP/2001-00000001
Channel2: PJSIP/2002-00000002
Timeout: 45000
```

---

## 🎵 Music on Hold (MOH)

### Configuration File
**Location:** `/etc/asterisk/musiconhold.conf`

### MOH Classes

#### Local Files
```ini
[default]
mode=files
directory=/var/lib/asterisk/moh
sort=random
```

#### Streaming (Icecast/Shoutcast)
```ini
[icecast-stream]
mode=custom
application=/usr/bin/mpg123 -q -r 8000 --mono -s -@
format=slin
```

#### With Volume Control
```ini
[stream-quiet]
mode=custom
application=/usr/bin/bash -c 'mpg123 -q -r 8000 --mono -s -@ | sox -t raw -r 8000 -c 1 -e signed -b 16 - -t raw -r 8000 -c 1 -e signed -b 16 - vol 0.5'
format=slin
```

### CLI Commands
```bash
# Show MOH classes
asterisk -rx "moh show classes"

# Show MOH files
asterisk -rx "moh show files"

# Reload MOH
asterisk -rx "module reload res_musiconhold.so"
```

### AMI Control
```php
// Set MOH class for channel
Action: Setvar
Channel: PJSIP/2001-00000001
Variable: CHANNEL(musicclass)
Value: icecast-stream

// Start MOH
Action: MusicOnHold
Channel: PJSIP/2001-00000001
Class: default
```

---

## 📋 Queue Management

### Add Member
```php
Action: QueueAdd
Queue: support
Interface: PJSIP/2001
MemberName: Agent 2001
Penalty: 0
Paused: 0
```

### Remove Member
```php
Action: QueueRemove
Queue: support
Interface: PJSIP/2001
```

### Pause/Unpause
```php
Action: QueuePause
Queue: support
Interface: PJSIP/2001
Paused: true
Reason: Break
```

### Queue Status
```php
Action: QueueStatus
Queue: support
```

### Queue Summary
```php
Action: QueueSummary
Queue: support
```

### Queue Member Penalty
```php
Action: QueuePenalty
Interface: PJSIP/2001
Penalty: 5
Queue: support
```

---

## 📬 Voicemail

### Check Voicemail
```php
// Dial *97 for voicemail main
Action: Originate
Channel: Local/2001@flexpbx-internal
Application: VoiceMailMain
Data: 2001@flexpbx
```

### Voicemail Counts
```bash
asterisk -rx "voicemail show users"
```

### Leave Voicemail
```php
Action: Originate
Channel: PJSIP/2001
Application: Voicemail
Data: 2002@flexpbx,u
```

---

## 👥 Extension/Endpoint Management

### Show Endpoints
```bash
asterisk -rx "pjsip show endpoints"
asterisk -rx "pjsip show endpoint 2001"
```

### Show Contacts (Registration)
```bash
asterisk -rx "pjsip show contacts"
```

### Endpoint Status via AMI
```php
Action: PJSIPShowEndpoint
Endpoint: 2001
```

### Qualify Endpoint
```php
Action: PJSIPQualify
Endpoint: 2001
```

---

## 🌐 Trunk Management

### Show Trunks
```bash
asterisk -rx "pjsip show registrations"
asterisk -rx "pjsip show registration callcentric"
```

### Trunk Status
```php
Action: PJSIPShowRegistrationInboundContactStatuses
```

---

## 📊 Call Detail Records (CDR)

### Database Table
```sql
SELECT * FROM cdr
WHERE calldate >= CURDATE()
ORDER BY calldate DESC
LIMIT 100;
```

### CDR Fields
- calldate
- clid (Caller ID)
- src (Source)
- dst (Destination)
- dcontext
- channel
- dstchannel
- lastapp
- lastdata
- duration
- billsec
- disposition
- amaflags
- accountcode
- uniqueid

### AMI CDR Events
Subscribe to `cdr` events in AMI to get real-time CDR data.

---

## 📞 Call Monitoring & Recording

### Start Recording
```php
Action: Monitor
Channel: PJSIP/2001-00000001
File: /var/spool/asterisk/monitor/2001-recording
Format: wav
Mix: true
```

### Stop Recording
```php
Action: StopMonitor
Channel: PJSIP/2001-00000001
```

### MixMonitor (Preferred)
```bash
# In dialplan
exten => _X.,n,MixMonitor(/var/spool/asterisk/monitor/${UNIQUEID}.wav,b)
```

---

## 🔊 Audio Playback

### Play Audio File
```php
Action: Originate
Channel: PJSIP/2001
Application: Playback
Data: custom/greeting
```

### Say Number
```php
Application: SayNumber
Data: 1234
```

### Say Digits
```php
Application: SayDigits
Data: 1234
```

### Say Date/Time
```php
Application: SayUnixTime
Data: ${EPOCH}
```

---

## 🎙️ Conference Bridges

### Confbridge List
```bash
asterisk -rx "confbridge list"
```

### Kick Participant
```php
Action: ConfbridgeKick
Conference: 8000
Channel: PJSIP/2001-00000001
```

### Mute/Unmute
```php
Action: ConfbridgeMute
Conference: 8000
Channel: PJSIP/2001-00000001
```

### Conference Admin
```php
Action: ConfbridgeSetSingleVideoSrc
Conference: 8000
Channel: PJSIP/2001-00000001
```

---

## 📡 Real-Time Events (AMI Events)

### Subscribe to Events
```php
Action: Events
EventMask: call,system,user
```

### Key Events

#### NewChannel
Fired when a channel is created
```
Event: Newchannel
Channel: PJSIP/2001-00000001
ChannelState: 0
ChannelStateDesc: Down
CallerIDNum: 2001
CallerIDName: Agent 2001
```

#### Hangup
```
Event: Hangup
Channel: PJSIP/2001-00000001
Cause: 16
Cause-txt: Normal Clearing
```

#### QueueMemberAdded
```
Event: QueueMemberAdded
Queue: support
Interface: PJSIP/2001
MemberName: Agent 2001
StateInterface: PJSIP/2001
```

#### QueueCallerJoin
```
Event: QueueCallerJoin
Queue: support
Position: 1
CallerIDNum: 5551234567
```

#### AgentConnect
```
Event: AgentConnect
Queue: support
Interface: PJSIP/2001
Channel: PJSIP/2001-00000001
Member: PJSIP/2001
```

---

## 📞 Call Features & Codes

### Feature Codes
```ini
; features.conf
[featuremap]
blindxfer => *2
atxfer => *3
disconnect => *0
automon => *1
automixmon => *4
parkcall => #72
```

### Pickup Groups
```bash
# Pickup extension in same group
**2001

# Directed pickup
*8*2001
```

---

## 🔐 Security & Authentication

### SIP Authentication
```bash
# Check auth attempts
asterisk -rx "pjsip show auth callcentric"
```

### Fail2Ban Integration
Monitor: `/var/log/asterisk/security.log`

---

## 💾 Database Integration

### AstDB (Internal)
```bash
# Store value
asterisk -rx "database put family key value"

# Get value
asterisk -rx "database get family key"

# Show tree
asterisk -rx "database show"
```

### Realtime (External DB)
Configure in `extconfig.conf` to use MySQL/PostgreSQL for:
- Extensions
- Voicemail
- Queue members
- SIP peers

---

## 🧪 Testing & Diagnostics

### SIP Debug
```bash
asterisk -rx "pjsip set logger on"
asterisk -rx "pjsip set logger off"
```

### Core Debug
```bash
asterisk -rx "core set debug 5"
asterisk -rx "core set verbose 5"
```

### Network Capture
```bash
tcpdump -i any -s 0 -w /tmp/sip.pcap port 5060
```

### Channel Status
```bash
asterisk -rx "core show channels"
asterisk -rx "core show channels verbose"
asterisk -rx "core show channel PJSIP/2001-00000001"
```

---

## 📱 WebRTC Support

### WebRTC Configuration
```ini
; pjsip.conf
[transport-wss]
type=transport
protocol=wss
bind=0.0.0.0:8089

[2001]
type=endpoint
webrtc=yes
dtls_auto_generate_cert=yes
```

### ARI WebRTC Channel
```javascript
ws = new WebSocket('wss://server:8089/ari/events');
```

---

## 🔄 System Control

### Reload Modules
```bash
asterisk -rx "module reload"
asterisk -rx "module reload res_pjsip.so"
asterisk -rx "module reload chan_pjsip.so"
```

### Restart Asterisk
```bash
asterisk -rx "core restart now"
asterisk -rx "core restart gracefully"
```

### System Status
```bash
asterisk -rx "core show version"
asterisk -rx "core show uptime"
asterisk -rx "core show settings"
```

---

## 🌐 HTTP/HTTPS API (ARI)

### Base URL
```
http://localhost:8088/ari
https://localhost:8089/ari
```

### Authentication
```bash
curl -u username:password \
  http://localhost:8088/ari/channels
```

### Common Endpoints

#### GET /channels
List all active channels

#### POST /channels
Originate a new channel

#### GET /bridges
List all bridges

#### POST /bridges
Create a new bridge

#### GET /endpoints
List all endpoints

#### GET /recordings/stored
List stored recordings

#### POST /channels/{channelId}/answer
Answer a channel

#### POST /channels/{channelId}/ring
Ring a channel

#### DELETE /channels/{channelId}
Hangup a channel

---

## 📊 Prometheus Metrics

### Enable Metrics Module
```bash
module load res_prometheus.so
```

### Metrics Endpoint
```
http://localhost:8088/ari/metrics
```

---

## 🔧 Custom Applications

### AGI Script
```php
#!/usr/bin/php
<?php
// Read AGI environment
while (!feof(STDIN)) {
    $line = fgets(STDIN);
    if (trim($line) === '') break;
}

// Send command
echo "ANSWER\n";
fflush(STDOUT);

// Get response
$response = fgets(STDIN);
?>
```

### Dialplan Integration
```ini
exten => 100,1,AGI(custom-script.php)
```

---

## 📝 Configuration Files Reference

### Critical Files
- `/etc/asterisk/asterisk.conf` - Main config
- `/etc/asterisk/extensions.conf` - Dialplan
- `/etc/asterisk/pjsip.conf` - SIP endpoints/trunks
- `/etc/asterisk/queues.conf` - Call queues
- `/etc/asterisk/voicemail.conf` - Voicemail config
- `/etc/asterisk/musiconhold.conf` - MOH classes
- `/etc/asterisk/manager.conf` - AMI users
- `/etc/asterisk/http.conf` - HTTP/ARI server
- `/etc/asterisk/ari.conf` - ARI users
- `/etc/asterisk/rtp.conf` - RTP configuration
- `/etc/asterisk/features.conf` - Call features
- `/etc/asterisk/confbridge.conf` - Conference bridges

---

## 🚀 API Integration Examples

### PHP AMI Manager Class
```php
class AsteriskManager {
    private $socket;

    public function __construct($host, $port, $user, $pass) {
        $this->socket = fsockopen($host, $port);
        $this->login($user, $pass);
    }

    public function send($action, $params = []) {
        $message = "Action: $action\r\n";
        foreach ($params as $key => $value) {
            $message .= "$key: $value\r\n";
        }
        $message .= "\r\n";

        fwrite($this->socket, $message);
        return $this->readResponse();
    }
}
```

### JavaScript ARI Client
```javascript
const ari = require('ari-client');

ari.connect('http://localhost:8088', 'username', 'password')
  .then(client => {
    client.on('StasisStart', (event, channel) => {
      channel.answer(() => {
        channel.play({media: 'sound:hello-world'});
      });
    });

    client.start('my-app');
  });
```

---

## 📖 Resources

- **Asterisk Documentation:** https://docs.asterisk.org/
- **AMI Reference:** https://docs.asterisk.org/Asterisk_18_Documentation/API_Documentation/AMI_Actions/
- **ARI Reference:** https://docs.asterisk.org/Asterisk_18_Documentation/API_Documentation/Asterisk_REST_Interface/
- **Dialplan Reference:** https://docs.asterisk.org/Asterisk_18_Documentation/Configuration/Dialplan/

---

**Last Updated:** October 14, 2025
**FlexPBX Version:** 1.0
**Maintained by:** FlexPBX Development Team
