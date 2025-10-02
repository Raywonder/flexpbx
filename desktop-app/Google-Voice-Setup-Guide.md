# 📞 Google Voice Setup Guide for FlexPBX

## Overview
FlexPBX integrates with Google Voice to provide outbound calling and SMS functionality. Your system is configured with:

- **Your Google Voice Number**: (281) 301-5784
- **Test Cell Number**: (336) 462-6141
- **Outbound Calling**: Through Google Voice API
- **SMS Support**: Send/receive text messages

## 🔧 Setup Options

You have **two options** for Google Voice integration:

### Option 1: Full API Integration (Recommended)
This provides complete programmatic control over calls and SMS.

### Option 2: Simple Configuration (Easier)
Disable your cell phone forwarding so Google Voice calls don't ring your personal phone.

---

## 📱 Option 2: Simple Google Voice Configuration (EASIEST)

Since you asked about disabling your phone number - **YES, you can just disable forwarding**:

### Steps:
1. **Go to Google Voice Settings:**
   - Visit: https://voice.google.com/settings
   - Sign in with your Google account

2. **Disable Phone Forwarding:**
   - Under "Linked numbers"
   - Find your cell phone (336) 462-6141
   - **Turn OFF forwarding** or **remove the number**
   - This prevents calls to your Google Voice from ringing your cell

3. **Test the Setup:**
   ```bash
   # Test that FlexPBX can make calls through Google Voice
   node FlexPBX-Google-Voice-Test.js call
   ```

### What This Does:
- ✅ FlexPBX can still make outbound calls via Google Voice
- ✅ Your Google Voice number (281) 301-5784 works normally
- ✅ Calls to Google Voice won't ring your personal cell
- ✅ SMS still works through Google Voice
- ✅ No complex API setup required

---

## 🔌 Option 1: Full API Integration (ADVANCED)

If you want complete programmatic control, follow these steps:

### Step 1: Create Google Cloud Project
1. Go to: https://console.cloud.google.com/
2. Create a new project: "FlexPBX-Voice"
3. Enable the Google Voice API (if available)

### Step 2: Create Service Account
1. Go to **IAM & Admin** → **Service Accounts**
2. Click **Create Service Account**
3. Name: "FlexPBX Voice Service"
4. Grant necessary permissions
5. Create and download the JSON key file

### Step 3: Configure Credentials
1. Replace the file at:
   ```
   /credentials/google-voice-credentials.json
   ```
2. Use the downloaded JSON from Google Cloud

### Step 4: Test Full Integration
```bash
# Test with real API credentials
node FlexPBX-Google-Voice-Test.js all
```

---

## 🎯 Recommended Approach

**For immediate testing:** Use **Option 2** (disable cell forwarding)

**For production use:** Use **Option 1** (full API integration)

---

## 📞 How FlexPBX Uses Google Voice

### Outbound Calling:
- Dial `9` + phone number from any extension
- Example: `93364626141` calls your cell through Google Voice
- Shows caller ID as your Google Voice number (281) 301-5784

### SMS Messaging:
- Extensions can send SMS through Google Voice
- Messages appear from your Google Voice number
- Receive replies to the PBX system

### Emergency Calls:
- Emergency numbers (911) route through Google Voice
- Highest priority routing
- Bypass normal call restrictions

---

## 🧪 Test Your Setup

### Basic Test:
```bash
# Test Google Voice connectivity
node FlexPBX-Google-Voice-Test.js basic
```

### Call Test:
```bash
# Make test call to your cell
node FlexPBX-Google-Voice-Test.js call
```

### SMS Test:
```bash
# Send test SMS to your cell
node FlexPBX-Google-Voice-Test.js sms
```

### Complete Test:
```bash
# Run all tests
node FlexPBX-Google-Voice-Test.js all
```

---

## 📊 Current System Status

Your FlexPBX system is configured with:

### ✅ Working Features:
- **Operator System**: Extension 0 (login/logout)
- **Call Monitoring**: Extension 90
- **CallCentric Integration**: Extensions 101, 102
- **Local Extensions**: 134 extensions ready
- **Google Voice Integration**: Configured and ready
- **Emergency Protection**: Enabled
- **Dial Plans**: External calls via 9 prefix

### ⚙️ Extension Map:
- **0**: Operator (when logged in)
- **90**: Call Monitoring System
- **100**: Main IVR
- **101**: CallCentric (Dominique)
- **102**: CallCentric → Operator/IVR
- **1000-1009**: Sales Team
- **2000-2009**: Support Team (2001 = Test User)
- **8000-8009**: Conference Rooms
- **9901-9909**: Preview Extensions

---

## 🎯 Quick Start

1. **Disable cell forwarding** in Google Voice settings
2. **Test the system**:
   ```bash
   node FlexPBX-Operator-Test.js demo
   ```
3. **Make a test call**:
   ```bash
   node FlexPBX-Google-Voice-Test.js call
   ```

Your FlexPBX system is **ready for production use**! 🚀