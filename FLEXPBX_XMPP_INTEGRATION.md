# FlexPBX XMPP/Jabber Integration
## Real-time Messaging & Presence for VoIP

## Overview

FlexPBX includes built-in XMPP (Extensible Messaging and Presence Protocol) support, similar to FreePBX's XMPP module, but optimized specifically for FlexPBX architecture. This allows real-time messaging, presence information, and chat functionality integrated with your phone system.

---

## Features

### Core Capabilities
- **Instant Messaging**: Send/receive messages between extensions
- **Presence Status**: See when users are available, busy, away, or offline
- **Chat History**: Store and retrieve conversation history
- **Group Chat**: Conference rooms with persistent chat
- **File Transfer**: Share files through XMPP
- **VoIP Integration**: Click-to-call from chat interface
- **Screen Pop**: Display caller information with chat history
- **Voicemail Notifications**: Get instant alerts for new voicemail

### FreePBX Compatibility
- Uses same XMPP protocol (RFC 6120, 6121, 6122)
- Compatible with standard XMPP clients (Pidgin, Gajim, Conversations, etc.)
- Supports SIP-to-XMPP mapping for presence
- Asterisk XMPP integration via res_xmpp module

---

## Architecture

```
┌─────────────────────────────────────────────────┐
│              FlexPBX XMPP Stack                 │
├─────────────────────────────────────────────────┤
│                                                 │
│  ┌──────────────┐      ┌──────────────┐        │
│  │   Prosody    │◄────►│   Asterisk   │        │
│  │ XMPP Server  │      │  res_xmpp    │        │
│  └──────────────┘      └──────────────┘        │
│         │                     │                 │
│         │                     │                 │
│         ▼                     ▼                 │
│  ┌──────────────┐      ┌──────────────┐        │
│  │   FlexPBX    │◄────►│   MariaDB    │        │
│  │  Web Client  │      │   Storage    │        │
│  └──────────────┘      └──────────────┘        │
│                                                 │
└─────────────────────────────────────────────────┘
```

---

## Installation

### Step 1: Install Prosody XMPP Server

```bash
# RHEL/CentOS/AlmaLinux
yum install epel-release
yum install prosody

# Debian/Ubuntu
apt-get update
apt-get install prosody
```

### Step 2: Configure Prosody for FlexPBX

Create `/etc/prosody/conf.d/flexpbx.cfg.lua`:

```lua
-- FlexPBX XMPP Configuration
VirtualHost "flexpbx.local"
    enabled = true

    -- Authentication
    authentication = "internal_hashed"

    -- Storage (SQL backend)
    storage = "sql"
    sql = {
        driver = "MySQL",
        database = "flexpbx",
        username = "flexpbx_user",
        password = "your_password",
        host = "localhost"
    }

    -- Enable modules
    modules_enabled = {
        -- Core modules
        "roster";              -- Allow users to have contact lists
        "saslauth";            -- Authentication
        "tls";                 -- TLS encryption
        "dialback";            -- Server-to-server auth
        "disco";               -- Service discovery

        -- Nice to have
        "carbons";             -- Message sync across devices
        "mam";                 -- Message Archive Management
        "blocklist";           -- User blocking
        "vcard4";              -- User profiles
        "vcard_legacy";        -- vCard compatibility

        -- Admin
        "admin_adhoc";         -- Admin commands
        "admin_telnet";        -- Telnet console

        -- HTTP modules
        "bosh";                -- Browser connections
        "websocket";           -- WebSocket support
        "http_files";          -- File serving

        -- Presence
        "presence";            -- Presence tracking
        "pep";                 -- Personal Eventing Protocol

        -- MUC (Multi-User Chat)
        "muc";                 -- Group chat
        "muc_mam";             -- Group chat history

        -- VoIP Integration
        "sipauth";             -- SIP authentication (if available)
    };

    -- SSL/TLS
    ssl = {
        key = "/etc/prosody/certs/flexpbx.key";
        certificate = "/etc/prosody/certs/flexpbx.crt";
    }

    -- Message Archive Management
    archive_expires_after = "1w"; -- Keep messages for 1 week

-- Multi-User Chat (Conference Rooms)
Component "conference.flexpbx.local" "muc"
    modules_enabled = {
        "muc_mam";             -- Archive group chats
        "vcard_muc";           -- Room profiles
    }

    muc_room_default_persistent = true;
    muc_room_default_public = true;
    muc_room_default_members_only = false;
    muc_room_default_allow_member_invites = true;

-- File Transfer Proxy
Component "proxy.flexpbx.local" "proxy65"
    proxy65_address = "flexpbx.local"
    proxy65_acl = { "flexpbx.local" }
    proxy65_ports = { 5000 }

-- HTTP Upload (modern file sharing)
Component "upload.flexpbx.local" "http_upload"
    http_upload_file_size_limit = 10485760 -- 10 MB
    http_upload_expire_after = 60 * 60 * 24 * 7 -- 7 days

-- Admin console
admins = { "admin@flexpbx.local" }
```

### Step 3: Enable Asterisk XMPP Module

Edit `/etc/asterisk/modules.conf`:

```ini
[modules]
load = res_xmpp.so
```

Create `/etc/asterisk/xmpp.conf`:

```ini
[general]
; Global XMPP settings
debug=no
autoregister=yes

[flexpbx]
; Connect to local Prosody server
type=client
serverhost=localhost
username=asterisk
secret=asterisk_xmpp_password
port=5222
usetls=yes
usesasl=yes
status=available
statusmessage="FlexPBX XMPP Integration"
timeout=5
```

### Step 4: Create Asterisk XMPP User in Prosody

```bash
# Create XMPP account for Asterisk
prosodyctl register asterisk flexpbx.local asterisk_xmpp_password

# Create admin account
prosodyctl register admin flexpbx.local admin_password
```

### Step 5: Start Services

```bash
# Start Prosody
systemctl enable prosody
systemctl start prosody

# Restart Asterisk to load XMPP module
systemctl restart asterisk

# Verify XMPP module is loaded
asterisk -rx "xmpp show connections"
```

---

## FlexPBX XMPP Configuration

### Database Schema

Create tables for XMPP integration:

```sql
-- XMPP user mapping (extension to XMPP JID)
CREATE TABLE xmpp_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    extension_number VARCHAR(10) NOT NULL,
    xmpp_jid VARCHAR(255) NOT NULL,
    xmpp_password VARCHAR(255),
    presence_status VARCHAR(50) DEFAULT 'offline',
    status_message TEXT,
    last_seen TIMESTAMP NULL,
    enabled TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY (extension_number),
    UNIQUE KEY (xmpp_jid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- XMPP chat history (for web UI display)
CREATE TABLE xmpp_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    from_jid VARCHAR(255) NOT NULL,
    to_jid VARCHAR(255) NOT NULL,
    message_body TEXT,
    message_type ENUM('chat', 'groupchat', 'headline') DEFAULT 'chat',
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_status TINYINT(1) DEFAULT 0,
    archived TINYINT(1) DEFAULT 0,
    INDEX idx_from_jid (from_jid),
    INDEX idx_to_jid (to_jid),
    INDEX idx_timestamp (timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- XMPP presence subscriptions
CREATE TABLE xmpp_roster (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_jid VARCHAR(255) NOT NULL,
    contact_jid VARCHAR(255) NOT NULL,
    subscription_status ENUM('none', 'to', 'from', 'both') DEFAULT 'none',
    contact_name VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (user_jid, contact_jid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Auto-Provision XMPP Accounts for Extensions

Create script `/home/flexpbxuser/apps/flexpbx/scripts/auto-provision-xmpp.php`:

```php
<?php
/**
 * Auto-provision XMPP accounts for all FlexPBX extensions
 */

require_once __DIR__ . '/../config/database.php';

$config = include __DIR__ . '/../config/database.php';
$pdo = new PDO(
    "mysql:host={$config['host']};dbname={$config['database']}",
    $config['username'],
    $config['password']
);

// Get all enabled extensions
$stmt = $pdo->query("SELECT extension_number, extension_name FROM extensions WHERE enabled = 1");
$extensions = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($extensions as $ext) {
    $extNumber = $ext['extension_number'];
    $jid = "{$extNumber}@flexpbx.local";
    $password = bin2hex(random_bytes(8));

    // Create Prosody account
    exec("prosodyctl register {$extNumber} flexpbx.local {$password}", $output, $returnCode);

    if ($returnCode === 0) {
        // Save to FlexPBX database
        $stmt = $pdo->prepare("
            INSERT INTO xmpp_users (extension_number, xmpp_jid, xmpp_password, enabled)
            VALUES (?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE xmpp_jid = ?, xmpp_password = ?
        ");
        $stmt->execute([$extNumber, $jid, $password, $jid, $password]);

        echo "✓ Created XMPP account for extension {$extNumber} ({$jid})\n";
    } else {
        echo "✗ Failed to create XMPP account for extension {$extNumber}\n";
    }
}

echo "\nXMPP provisioning complete!\n";
```

Run provisioning:

```bash
php /home/flexpbxuser/apps/flexpbx/scripts/auto-provision-xmpp.php
```

---

## Web Client Integration

### JavaScript XMPP Client (Strophe.js)

FlexPBX includes a web-based XMPP client using Strophe.js for browser connectivity.

Install dependencies:

```bash
cd /home/flexpbxuser/public_html
npm install strophe.js
```

Create `/home/flexpbxuser/public_html/includes/xmpp-client.js`:

```javascript
/**
 * FlexPBX XMPP Web Client
 * Uses Strophe.js for browser-based XMPP connectivity
 */

class FlexPBXXMPP {
    constructor(config) {
        this.config = {
            boshUrl: config.boshUrl || 'http://localhost:5280/http-bind',
            wsUrl: config.wsUrl || 'ws://localhost:5280/xmpp-websocket',
            domain: config.domain || 'flexpbx.local',
            ...config
        };

        this.connection = null;
        this.connected = false;
        this.roster = new Map();
        this.messageHandlers = [];
        this.presenceHandlers = [];
    }

    connect(jid, password) {
        return new Promise((resolve, reject) => {
            // Prefer WebSocket, fallback to BOSH
            const endpoint = this.config.wsUrl || this.config.boshUrl;

            this.connection = new Strophe.Connection(endpoint);

            this.connection.connect(jid, password, (status) => {
                if (status === Strophe.Status.CONNECTED) {
                    this.connected = true;
                    this.setupHandlers();
                    this.sendPresence('available', 'Online');
                    resolve();
                } else if (status === Strophe.Status.DISCONNECTED) {
                    this.connected = false;
                    reject(new Error('Disconnected'));
                } else if (status === Strophe.Status.AUTHFAIL) {
                    reject(new Error('Authentication failed'));
                }
            });
        });
    }

    setupHandlers() {
        // Message handler
        this.connection.addHandler(
            (msg) => this.onMessage(msg),
            null,
            'message',
            'chat'
        );

        // Presence handler
        this.connection.addHandler(
            (pres) => this.onPresence(pres),
            null,
            'presence'
        );

        // Request roster
        this.requestRoster();
    }

    requestRoster() {
        const iq = $iq({ type: 'get', id: 'roster_1' })
            .c('query', { xmlns: Strophe.NS.ROSTER });

        this.connection.sendIQ(iq, (iq) => {
            $(iq).find('item').each((_, item) => {
                const jid = $(item).attr('jid');
                const name = $(item).attr('name') || jid;
                const subscription = $(item).attr('subscription');

                this.roster.set(jid, {
                    jid,
                    name,
                    subscription,
                    presence: 'offline'
                });
            });

            this.onRosterUpdate();
        });
    }

    sendMessage(to, body) {
        if (!this.connected) {
            throw new Error('Not connected to XMPP server');
        }

        const msg = $msg({
            to: to,
            type: 'chat'
        }).c('body').t(body);

        this.connection.send(msg);

        // Store in local history
        this.storeMessage({
            from: this.connection.jid,
            to,
            body,
            timestamp: new Date()
        });
    }

    sendPresence(show, status) {
        const pres = $pres();

        if (show && show !== 'available') {
            pres.c('show').t(show).up();
        }

        if (status) {
            pres.c('status').t(status);
        }

        this.connection.send(pres);
    }

    onMessage(msg) {
        const from = $(msg).attr('from');
        const body = $(msg).find('body').text();
        const type = $(msg).attr('type');

        if (!body) return true;

        const message = {
            from,
            body,
            type,
            timestamp: new Date()
        };

        // Store message
        this.storeMessage(message);

        // Notify handlers
        this.messageHandlers.forEach(handler => handler(message));

        // Display notification
        this.showNotification('New message from ' + from, body);

        return true;
    }

    onPresence(pres) {
        const from = $(pres).attr('from');
        const type = $(pres).attr('type');
        const show = $(pres).find('show').text() || 'available';
        const status = $(pres).find('status').text();

        const bareJid = Strophe.getBareJidFromJid(from);

        if (this.roster.has(bareJid)) {
            const contact = this.roster.get(bareJid);
            contact.presence = type === 'unavailable' ? 'offline' : show;
            contact.status = status;

            this.onRosterUpdate();
        }

        this.presenceHandlers.forEach(handler => handler({
            from: bareJid,
            presence: type === 'unavailable' ? 'offline' : show,
            status
        }));

        return true;
    }

    storeMessage(message) {
        // Send to backend for storage
        fetch('/api/xmpp.php?path=store-message', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(message)
        });
    }

    showNotification(title, body) {
        if ('Notification' in window && Notification.permission === 'granted') {
            new Notification(title, { body });
        }
    }

    onRosterUpdate() {
        // Override this method to handle roster updates
    }

    addMessageHandler(handler) {
        this.messageHandlers.push(handler);
    }

    addPresenceHandler(handler) {
        this.presenceHandlers.push(handler);
    }

    disconnect() {
        if (this.connection) {
            this.connection.disconnect();
        }
    }
}

// Export for use in FlexPBX
window.FlexPBXXMPP = FlexPBXXMPP;
```

---

## Usage Examples

### Extension-to-Extension Messaging

```javascript
// Initialize XMPP client
const xmpp = new FlexPBXXMPP({
    wsUrl: 'wss://flexpbx.devinecreations.net:5281/xmpp-websocket',
    domain: 'flexpbx.local'
});

// Connect as extension 2000
await xmpp.connect('2000@flexpbx.local', 'password');

// Send message to extension 2001
xmpp.sendMessage('2001@flexpbx.local', 'Hello from extension 2000!');

// Listen for messages
xmpp.addMessageHandler((message) => {
    console.log('Message from', message.from, ':', message.body);

    // Display in chat UI
    displayChatMessage(message);
});

// Set presence
xmpp.sendPresence('away', 'At lunch');
```

### VoIP Integration (Click-to-Call)

```javascript
// Click-to-call from chat
document.getElementById('call-button').addEventListener('click', async () => {
    const contactJid = '2001@flexpbx.local';
    const extension = contactJid.split('@')[0];

    // Initiate call via FlexPBX API
    const response = await fetch('/api/calls.php?path=originate', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            from: '2000',
            to: extension
        })
    });

    if (response.ok) {
        // Notify via XMPP
        xmpp.sendMessage(contactJid, 'Calling you now...');
    }
});
```

### Voicemail Notification via XMPP

In `/etc/asterisk/extensions.conf`:

```ini
; Voicemail with XMPP notification
exten => *97,1,NoOp(VoiceMail Access)
 same => n,Answer()
 same => n,VoiceMailMain(${CALLERID(num)}@flexpbx)
 same => n,Hangup()

; Send XMPP notification when voicemail is left
exten => s,1,NoOp(Voicemail Notification)
 same => n,System(php /home/flexpbxuser/scripts/send-xmpp-notification.php "${VM_CALLERID}" "${VM_MAILBOX}")
 same => n,Hangup()
```

---

## Security Considerations

### TLS/SSL Encryption

Always use TLS for XMPP connections:

```lua
-- In prosody.cfg.lua
c2s_require_encryption = true
s2s_require_encryption = true
s2s_secure_auth = true

ssl = {
    protocol = "tlsv1_2+";
    ciphers = "HIGH+kEDH:HIGH+kEECDH:HIGH:!PSK:!SRP:!3DES:!aNULL";
    dhparam = "/etc/prosody/certs/dh-2048.pem";
}
```

### Authentication

- Use strong passwords for XMPP accounts
- Enable SASL authentication
- Consider external authentication (LDAP/Active Directory)

### Firewall Rules

```bash
# Allow XMPP client connections
firewall-cmd --permanent --add-port=5222/tcp

# Allow XMPP server-to-server
firewall-cmd --permanent --add-port=5269/tcp

# Allow BOSH/WebSocket
firewall-cmd --permanent --add-port=5280/tcp
firewall-cmd --permanent --add-port=5281/tcp

# Reload
firewall-cmd --reload
```

---

## Troubleshooting

### XMPP Connection Issues

```bash
# Check Prosody status
systemctl status prosody
tail -f /var/log/prosody/prosody.log

# Test XMPP connection
telnet localhost 5222

# Check Asterisk XMPP
asterisk -rx "xmpp show connections"
asterisk -rx "xmpp reload"
```

### Message Delivery Problems

```bash
# Enable debug logging in Prosody
prosodyctl shell
> c2s:show()
> module:load("admin_telnet")

# Check message routing
prosodyctl about
```

### Authentication Failures

```bash
# Verify user exists
prosodyctl check config
prosodyctl check dns

# Reset password
prosodyctl passwd user@flexpbx.local
```

---

## Next Steps

1. **Install and configure Prosody XMPP server**
2. **Enable Asterisk XMPP module**
3. **Provision XMPP accounts for extensions**
4. **Integrate XMPP client in FlexPBX web UI**
5. **Test messaging and presence**
6. **Configure voicemail notifications**
7. **Implement click-to-call from chat**

---

**Version**: 1.0
**Last Updated**: November 9, 2025
**Compatibility**: FlexPBX 1.3+, Prosody 0.11+, Asterisk 18+
**Status**: Production Ready

For support: https://github.com/devinecreations/flexpbx
