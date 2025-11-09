# API Documentation

## FlexPBX VoIP MCP Server API

Complete reference for all 20 tools provided by the FlexPBX VoIP MCP server.

## Core AMI Tools

### asterisk_status

Get Asterisk system status and uptime.

**Parameters**: None

**Response**:
```json
{
  "success": true,
  "status": {
    "Response": "Success",
    "CoreStartupDate": "2025-11-08",
    "CoreStartupTime": "10:30:15",
    "CoreReloadDate": "2025-11-08",
    "CoreReloadTime": "10:30:15",
    "CoreCurrentCalls": "3"
  }
}
```

**Example**:
```
Get Asterisk system status
```

---

### list_channels

List all active channels (calls in progress).

**Parameters**: None

**Response**:
```json
{
  "success": true,
  "channels": [
    {
      "Channel": "PJSIP/2001-00000001",
      "ChannelState": "6",
      "ChannelStateDesc": "Up",
      "CallerIDNum": "2001",
      "CallerIDName": "John Doe",
      "ConnectedLineNum": "2002",
      "ConnectedLineName": "Jane Smith",
      "Context": "from-internal",
      "Extension": "2002",
      "Duration": "00:02:15"
    }
  ]
}
```

**Example**:
```
Show me all active calls
```

---

### originate_call

Originate a new call from one extension to another.

**Parameters**:
- `channel` (required): Channel to originate (e.g., "PJSIP/2001")
- `extension` (required): Extension to dial
- `context` (optional): Dialplan context (default: "from-internal")
- `callerId` (optional): Caller ID to display

**Response**:
```json
{
  "success": true,
  "result": {
    "Response": "Success",
    "Message": "Originate successfully queued"
  }
}
```

**Example**:
```
Originate a call from PJSIP/2001 to extension 2002
```

---

### hangup_channel

Hangup an active channel.

**Parameters**:
- `channel` (required): Channel to hangup
- `cause` (optional): Hangup cause code

**Response**:
```json
{
  "success": true,
  "result": {
    "Response": "Success",
    "Message": "Channel PJSIP/2001-00000001 hung up"
  }
}
```

**Example**:
```
Hangup channel PJSIP/2001-00000001
```

---

## Conference Management Tools

### list_conferences

List all active conference bridges.

**Parameters**: None

**Response**:
```json
{
  "success": true,
  "conferences": [
    {
      "conference": "1000",
      "participants": 5,
      "marked": 1,
      "locked": false,
      "muted": false
    },
    {
      "conference": "2000",
      "participants": 2,
      "marked": 0,
      "locked": true,
      "muted": false
    }
  ]
}
```

**Example**:
```
List all active conference rooms
```

---

### get_conference_participants

Get participants in a specific conference.

**Parameters**:
- `conference` (required): Conference room number

**Response**:
```json
{
  "success": true,
  "conference": "1000",
  "participants": [
    {
      "channel": "PJSIP/2001-00000001",
      "userProfile": "default",
      "bridgeProfile": "default_bridge",
      "menu": "default_menu",
      "callerId": "John Doe <2001>"
    },
    {
      "channel": "PJSIP/2002-00000002",
      "userProfile": "default",
      "bridgeProfile": "default_bridge",
      "menu": "default_menu",
      "callerId": "Jane Smith <2002>"
    }
  ]
}
```

**Example**:
```
Show me participants in conference 1000
```

---

### kick_participant

Remove a participant from conference.

**Parameters**:
- `conference` (required): Conference room number
- `channel` (required): Participant channel to kick

**Response**:
```json
{
  "success": true,
  "message": "Participant kicked from conference"
}
```

**Example**:
```
Kick participant with channel PJSIP/2001-00000001 from conference 1000
```

---

### mute_participant

Mute a conference participant.

**Parameters**:
- `conference` (required): Conference room number
- `channel` (required): Participant channel to mute

**Response**:
```json
{
  "success": true,
  "message": "Participant muted"
}
```

**Example**:
```
Mute participant PJSIP/2001-00000001 in conference 1000
```

---

### unmute_participant

Unmute a conference participant.

**Parameters**:
- `conference` (required): Conference room number
- `channel` (required): Participant channel to unmute

**Response**:
```json
{
  "success": true,
  "message": "Participant unmuted"
}
```

**Example**:
```
Unmute participant PJSIP/2001-00000001 in conference 1000
```

---

### lock_conference

Lock a conference to prevent new participants.

**Parameters**:
- `conference` (required): Conference room number

**Response**:
```json
{
  "success": true,
  "message": "Conference locked"
}
```

**Example**:
```
Lock conference 1000
```

---

### unlock_conference

Unlock a conference to allow new participants.

**Parameters**:
- `conference` (required): Conference room number

**Response**:
```json
{
  "success": true,
  "message": "Conference unlocked"
}
```

**Example**:
```
Unlock conference 1000
```

---

## Extension Management Tools

### list_extensions

List all PJSIP extensions and their status.

**Parameters**: None

**Response**:
```json
{
  "success": true,
  "extensions": [
    {
      "endpoint": "2001",
      "deviceState": "Not in use",
      "activeChannels": 0,
      "maxContacts": 1
    },
    {
      "endpoint": "2002",
      "deviceState": "In use",
      "activeChannels": 1,
      "maxContacts": 1
    }
  ]
}
```

**Example**:
```
List all extensions and their registration status
```

---

### get_extension_status

Get detailed status for a specific extension.

**Parameters**:
- `extension` (required): Extension number

**Response**:
```json
{
  "extension": "2001",
  "status": {
    "registered": true,
    "deviceState": "Not in use",
    "contacts": [
      "sip:2001@192.168.1.100:5060"
    ]
  },
  "timestamp": "2025-11-08T10:30:15.000Z"
}
```

**Example**:
```
Get status for extension 2001
```

---

### get_extension_registration

Get registration details for an extension.

**Parameters**:
- `extension` (required): Extension number

**Response**:
```json
{
  "extension": "2001",
  "aorDetails": {
    "contacts": [
      {
        "uri": "sip:2001@192.168.1.100:5060",
        "status": "Avail"
      }
    ],
    "maxContacts": 1,
    "authenticateQualify": false
  },
  "timestamp": "2025-11-08T10:30:15.000Z"
}
```

**Example**:
```
Get registration details for extension 2001
```

---

## Dial Plan Tools

### get_dial_rules

Get dial plan rules for SIP client configuration.

**Parameters**:
- `format` (optional): Output format - "json", "groundwire", "linphone", or "zoiper"

**Response (JSON format)**:
```json
{
  "success": true,
  "dialRules": {
    "extensions": {
      "pattern": "2xxx",
      "description": "4-digit extensions (2000-2999)",
      "regex": "^2[0-9]{3}$",
      "minLength": 4,
      "maxLength": 4,
      "type": "extension"
    },
    "featureCodes": {
      "pattern": "*xx",
      "description": "Feature codes",
      "regex": "^\\*[0-9]{2}$",
      "minLength": 3,
      "maxLength": 3,
      "type": "feature",
      "codes": {
        "*97": "Voicemail Access",
        "*45": "Queue Agent Login"
      }
    }
  },
  "combinedPattern": "(2xxx|*xx|1[2-9]xxxxxxxxx|011xxxxxxxxxxx)",
  "interDigitTimeout": 3
}
```

**Response (Groundwire format)**:
```json
{
  "dialPlan": "(2xxx|*xx|1[2-9]xxxxxxxxx|011xxxxxxxxxxx)",
  "rules": { ... },
  "format": "groundwire"
}
```

**Example**:
```
Get dial rules in Groundwire format
```

---

### get_feature_codes

Get available feature codes (*97, *45, etc.).

**Parameters**: None

**Response**:
```json
{
  "success": true,
  "featureCodes": {
    "*97": "Voicemail Access",
    "*45": "Queue Agent Login",
    "*46": "Queue Agent Logout",
    "*65": "Call Recording On",
    "*66": "Call Recording Off",
    "*78": "Do Not Disturb On",
    "*79": "Do Not Disturb Off",
    "*72": "Call Forward Enable",
    "*73": "Call Forward Disable"
  },
  "pattern": "*xx",
  "description": "Feature codes (*97 voicemail, *45 queue login, etc.)"
}
```

**Example**:
```
List all feature codes
```

---

### validate_number

Validate a dialed number against dial plan rules.

**Parameters**:
- `number` (required): Number to validate

**Response (valid)**:
```json
{
  "success": true,
  "number": "2001",
  "valid": true,
  "rule": "extensions",
  "type": "extension",
  "description": "4-digit extensions (2000-2999)"
}
```

**Response (invalid)**:
```json
{
  "success": true,
  "number": "999",
  "valid": false,
  "error": "Number does not match any dial plan rule"
}
```

**Example**:
```
Validate number 2001
```

---

## Call Analytics Tools

### query_cdr

Query call detail records (requires database setup).

**Parameters**:
- `startDate` (optional): Start date (YYYY-MM-DD)
- `endDate` (optional): End date (YYYY-MM-DD)
- `src` (optional): Source extension
- `dst` (optional): Destination number
- `limit` (optional): Maximum records to return

**Response**:
```json
{
  "success": true,
  "message": "CDR query pattern - requires database implementation",
  "query": {
    "sql": "SELECT * FROM cdr WHERE calldate >= ? AND calldate <= ? ORDER BY calldate DESC LIMIT ?",
    "params": ["2025-11-01", "2025-11-08", 100]
  },
  "filters": {
    "startDate": "2025-11-01",
    "endDate": "2025-11-08",
    "limit": 100
  }
}
```

**Example**:
```
Query CDR from 2025-11-01 to 2025-11-08 limited to 100 records
```

---

### get_call_stats

Get call statistics (requires database setup).

**Parameters**:
- `startDate` (optional): Start date (YYYY-MM-DD)
- `endDate` (optional): End date (YYYY-MM-DD)

**Response**:
```json
{
  "success": true,
  "message": "Call statistics pattern - requires database implementation",
  "query": "SELECT COUNT(*) as total_calls, SUM(CASE WHEN disposition = 'ANSWERED' THEN 1 ELSE 0 END) as answered_calls...",
  "filters": {
    "startDate": "2025-11-01",
    "endDate": "2025-11-08"
  }
}
```

**Example**:
```
Get call statistics for November 2025
```

---

### get_extension_summary

Get call summary for a specific extension.

**Parameters**:
- `extension` (required): Extension number
- `startDate` (optional): Start date (YYYY-MM-DD)
- `endDate` (optional): End date (YYYY-MM-DD)

**Response**:
```json
{
  "success": true,
  "message": "Extension summary pattern - requires database implementation",
  "query": "SELECT COUNT(*) as total_calls, SUM(CASE WHEN src = '2001' THEN 1 ELSE 0 END) as outbound_calls...",
  "extension": "2001"
}
```

**Example**:
```
Get call summary for extension 2001 in November
```

---

## Error Responses

All tools return errors in this format:

```json
{
  "success": false,
  "error": "Error message description",
  "tool": "tool_name"
}
```

Common errors:
- **Not connected to AMI**: AMI connection failed or not initialized
- **Invalid parameters**: Missing or invalid required parameters
- **Timeout**: Operation exceeded timeout limit
- **Permission denied**: AMI user lacks required permissions
- **Not found**: Resource (extension, conference, etc.) not found

## Rate Limiting

The server implements rate limiting:
- Default: 60 requests per minute
- Configure via `MAX_REQUESTS_PER_MINUTE` environment variable
- Exceeding limit returns HTTP 429 error

## Authentication

Currently uses environment-based AMI credentials. For production:
- Set `REQUIRE_AUTH=true`
- Provide `API_KEY` in environment
- Include API key in requests (implementation-specific)
