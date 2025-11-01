# Push Notifications & Real-Time Status System

## Overview

FlexPBX now features a comprehensive push notification system with real-time SIP registration status monitoring. Users and admins can receive notifications via push (on their devices) or email, with full control over preferences.

## 🔔 Features

### Push Notifications
- ✅ **Browser Push**: Receive notifications even when browser is closed
- ✅ **Service Worker**: Background notifications support
- ✅ **Multi-Device**: Each device can subscribe independently
- ✅ **Customizable**: Choose which events trigger notifications
- ✅ **Fallback Support**: Email notifications if push is disabled

### Real-Time Status Monitoring
- ✅ **SIP Registration**: Live status of extension registration
- ✅ **Device Count**: See how many devices are connected
- ✅ **Call Status**: Know when extension is on a call
- ✅ **Auto-Refresh**: Status updates every 30 seconds
- ✅ **Dashboard Integration**: Status visible on main dashboard

### Notification Preferences
- ✅ **Granular Control**: Enable/disable specific notification types
- ✅ **Per-User Settings**: Each user controls their own preferences
- ✅ **Persistent Storage**: Preferences saved in user account
- ✅ **Email or Push**: Use one or both notification methods

## 📂 File Structure

```
/home/flexpbxuser/
├── public_html/
│   ├── service-worker.js                      # Push notification service worker
│   ├── api/
│   │   ├── notification-subscribe.php         # Subscription management API
│   │   └── sip-status.php                     # SIP status checker API
│   ├── user-portal/
│   │   ├── notification-settings.php          # User notification settings
│   │   └── index.php                          # Updated with real-time status
│   └── admin/
│       └── (similar notification settings)
├── push_subscriptions/                        # Push subscription storage
│   └── user_2006_*.json
└── cache/
    └── sip_status/                            # Cached SIP status (5 sec TTL)
        └── ext_2006.json
```

## 🔐 Notification Types

Users and admins can choose which events trigger notifications:

| Notification Type | Description | Default |
|-------------------|-------------|---------|
| **Voicemail** | New voicemail received | ✓ Enabled |
| **Missed Calls** | Calls that went unanswered | ✓ Enabled |
| **SIP Status** | Extension registered/unregistered | ✓ Enabled |
| **System Alerts** | System maintenance, updates | Admin only |

## 🚀 How It Works

### Push Notification Flow

```
1. User visits notification settings
   ↓
2. Enables push notifications
   ↓
3. Browser requests permission
   ↓
4. Service worker registers
   ↓
5. Push subscription created
   ↓
6. Subscription sent to API
   ↓
7. Server stores subscription
   ↓
8. Events trigger notifications
   ↓
9. Service worker displays notification
```

### SIP Status Flow

```
1. Dashboard loads
   ↓
2. JavaScript calls /api/sip-status.php
   ↓
3. API queries Asterisk via CLI
   ↓
4. PJSIP endpoint status retrieved
   ↓
5. Contact information parsed
   ↓
6. Status cached for 5 seconds
   ↓
7. Response sent to dashboard
   ↓
8. Status displays with color indicator
   ↓
9. Auto-refresh every 30 seconds
```

## 📱 Service Worker

**Location**: `/service-worker.js`

**Features**:
- Push notification handling
- Notification click handling
- Background sync
- Offline caching
- Auto-update mechanism

**Events Handled**:
- `push`: Receive and display notifications
- `notificationclick`: Open app when notification clicked
- `notificationclose`: Track notification dismissals
- `sync`: Background data synchronization

## 🔌 API Endpoints

### 1. Notification Subscription API

**Endpoint**: `/api/notification-subscribe.php`

**Actions**:

#### Subscribe to Push Notifications
```bash
POST /api/notification-subscribe.php
{
  "action": "subscribe",
  "account_type": "user",
  "identifier": "2006",
  "subscription": {
    "endpoint": "https://...",
    "keys": { ... }
  }
}
```

**Response**:
```json
{
  "success": true,
  "message": "Push notifications enabled",
  "subscription_id": "user_2006_abc123"
}
```

#### Unsubscribe
```bash
POST /api/notification-subscribe.php
{
  "action": "unsubscribe",
  "account_type": "user",
  "identifier": "2006"
}
```

#### Get Preferences
```bash
GET /api/notification-subscribe.php?action=get_preferences&identifier=2006&account_type=user
```

**Response**:
```json
{
  "success": true,
  "preferences": {
    "push_notifications_enabled": true,
    "email_notifications_enabled": true,
    "notify_voicemail": true,
    "notify_missed_calls": true,
    "notify_sip_status": true,
    "notify_system_alerts": false
  }
}
```

#### Update Preferences
```bash
POST /api/notification-subscribe.php
{
  "action": "update_preferences",
  "account_type": "user",
  "identifier": "2006",
  "email_notifications_enabled": false,
  "notify_voicemail": true
}
```

### 2. SIP Status API

**Endpoint**: `/api/sip-status.php`

**Request**:
```bash
GET /api/sip-status.php?extension=2006
```

**Response**:
```json
{
  "success": true,
  "extension": "2006",
  "status": "online",
  "registered": true,
  "device_state": "idle",
  "on_call": false,
  "call_count": 0,
  "device_count": 2,
  "devices": [
    {
      "contact": "2006/sip:...",
      "uri": "192.168.1.100:5060",
      "status": "reachable",
      "rtt": "0.5ms"
    },
    {
      "contact": "2006/sip:...",
      "uri": "10.0.0.50:5060",
      "status": "reachable",
      "rtt": "1.2ms"
    }
  ],
  "last_checked": "2025-10-14 12:30:50",
  "timestamp": 1760445050
}
```

**Status Values**:
- `online`: Extension registered with at least one device
- `offline`: No devices registered
- `on_call`: Extension currently on a call

**Device States**:
- `idle`: Available
- `inuse`: On call
- `ringing`: Incoming call
- `unavailable`: Not registered

## 💻 User Interface

### Notification Settings Page

**Location**: `/user-portal/notification-settings.php`

**Sections**:
1. **SIP Registration Status**
   - Real-time status indicator (online/offline)
   - Device count
   - Last check time
   - Manual refresh button

2. **Push Notifications**
   - Enable/disable toggle
   - Browser compatibility check
   - Permission status

3. **Email Notifications**
   - Enable/disable toggle
   - Voicemail notifications
   - Missed call notifications
   - SIP status change notifications

### Dashboard Status Display

**Location**: User/Admin dashboard

**Features**:
- Live SIP status badge (🟢 Online / 🔴 Offline / 📞 On Call)
- Connected device count
- Auto-refresh every 30 seconds
- Link to notification settings

## 🔒 Security

### Subscription Storage
- **Location**: `/home/flexpbxuser/push_subscriptions/`
- **Permissions**: 750 (owner/group read only)
- **Format**: JSON with device info, IP, timestamp
- **Cleanup**: Automated for expired subscriptions

### SIP Status Caching
- **Duration**: 5 seconds
- **Purpose**: Prevent Asterisk overload
- **Location**: `/home/flexpbxuser/cache/sip_status/`
- **Auto-cleanup**: Cached files expire automatically

### API Security
- **Authentication**: Session-based
- **Rate Limiting**: Cache prevents excessive queries
- **Asterisk Integration**: Uses sudo with restricted commands
- **Input Validation**: Extension/identifier sanitization

## 📊 Status Indicators

### Visual Indicators

| Indicator | Meaning | Dashboard Display |
|-----------|---------|-------------------|
| 🟢 Online | Extension registered | Green badge |
| 🔴 Offline | No registration | Red badge |
| 📞 On Call | Active call in progress | Blue badge |
| ⚪ Checking | Status being retrieved | Gray badge |

### Device Count
- Shows number of simultaneously registered devices
- Example: "2 devices connected" = SIP client on phone + computer

### Call Status
- Automatically detects active calls
- Updates status to "On Call" when in use
- Returns to "Online" when call ends

## ⚙️ Configuration

### Service Worker Registration

Add to any page that needs push notifications:

```html
<script>
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/service-worker.js')
        .then(registration => {
            console.log('Service Worker registered:', registration);
        })
        .catch(error => {
            console.error('Service Worker registration failed:', error);
        });
}
</script>
```

### Status Polling

```javascript
// Check status every 30 seconds
const statusInterval = setInterval(async () => {
    const response = await fetch(`/api/sip-status.php?extension=2006`);
    const data = await response.json();

    if (data.registered) {
        // Update UI to show online
    } else {
        // Update UI to show offline
    }
}, 30000);
```

## 🧪 Testing

### Test Push Notifications

1. Visit notification settings page
2. Enable push notifications
3. Grant browser permission
4. Check browser console for subscription success
5. Verify subscription file created in `/push_subscriptions/`

### Test SIP Status

```bash
# Check extension 2006 status
curl "https://flexpbx.devinecreations.net/api/sip-status.php?extension=2006"

# Should return JSON with registration status
```

### Test Preferences

```bash
# Get current preferences
curl "https://flexpbx.devinecreations.net/api/notification-subscribe.php?action=get_preferences&identifier=2006&account_type=user"

# Update preferences
curl -X POST https://flexpbx.devinecreations.net/api/notification-subscribe.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "update_preferences",
    "account_type": "user",
    "identifier": "2006",
    "email_notifications_enabled": false
  }'
```

## 🔧 Troubleshooting

### Push Notifications Not Working

**Symptoms**: Subscription fails or notifications not received

**Causes**:
- Browser doesn't support push notifications
- Notification permission denied
- Service worker not registered
- VAPID keys not configured

**Solutions**:
1. Check browser compatibility (Chrome, Firefox, Edge supported)
2. Grant notification permission in browser settings
3. Verify service worker registered: Check Developer Tools → Application → Service Workers
4. Check browser console for errors

### SIP Status Shows Offline When Device is On

**Symptoms**: Status shows offline despite SIP client being connected

**Causes**:
- Asterisk not responding
- PJSIP endpoint misconfigured
- Cache issue
- Sudo permissions missing

**Solutions**:
```bash
# Manually check Asterisk
sudo -u asterisk /usr/sbin/asterisk -rx "pjsip show endpoint 2006"

# Check sudo permissions
sudo -l

# Clear cache
rm -f /home/flexpbxuser/cache/sip_status/ext_2006.json

# Check Asterisk logs
tail -f /var/log/asterisk/full
```

### Status Not Auto-Refreshing

**Symptoms**: Status doesn't update automatically

**Causes**:
- JavaScript interval not running
- Page backgrounded (browser throttling)
- API errors

**Solutions**:
- Check browser console for JavaScript errors
- Refresh page manually
- Keep browser tab active
- Check API endpoint directly

## 📈 Performance

### Caching Strategy
- **SIP Status**: 5-second cache per extension
- **Purpose**: Reduce Asterisk CLI load
- **Trade-off**: 5-second max delay for status updates

### Polling Frequency
- **Dashboard**: Every 30 seconds
- **Settings Page**: Every 30 seconds
- **Can be customized**: Adjust `setInterval` value

### Resource Usage
- **Memory**: Minimal (cached JSON files)
- **CPU**: Low (Asterisk CLI only)
- **Network**: ~1 KB per status check
- **Asterisk Load**: Negligible with caching

## 🔮 Future Enhancements

- [ ] WebSocket support for instant updates
- [ ] Push notification for incoming calls
- [ ] Call history in notifications
- [ ] Notification sound customization
- [ ] Do Not Disturb mode
- [ ] Notification scheduling (quiet hours)
- [ ] Rich notifications with actions (answer/reject)
- [ ] Integration with mobile apps
- [ ] SMS fallback for critical alerts
- [ ] Notification analytics/history

## 📚 Related Documentation

- [System-Wide Temp Passwords](SYSTEM_WIDE_TEMP_PASSWORDS.md)
- [Email Setup System](EMAIL_SETUP_SYSTEM.md)
- [Password Reset System](PASSWORD_RESET_SYSTEM.md)
- [Permissions & Integration](PERMISSIONS_AND_ASTERISK_INTEGRATION.md)

---

**Last Updated**: October 14, 2025
**System Version**: FlexPBX v1.0
**Status**: Production-Ready
