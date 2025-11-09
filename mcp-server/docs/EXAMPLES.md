# Usage Examples

Complete examples of using the FlexPBX VoIP MCP server with Claude.

## Basic Setup

First, configure Claude Desktop to use the server:

```json
{
  "mcpServers": {
    "flexpbx-voip": {
      "command": "node",
      "args": ["/home/devinecr/apps/mcp-servers/flexpbx-voip-mcp/src/index.js"],
      "env": {
        "AMI_HOST": "127.0.0.1",
        "AMI_PORT": "5038",
        "AMI_USERNAME": "your_username",
        "AMI_SECRET": "your_secret"
      }
    }
  }
}
```

## Example 1: Monitor System Status

**User**: "Check the Asterisk system status"

**Claude**: *Uses asterisk_status tool*

```
The Asterisk system is running normally:
- Started: 2025-11-08 at 10:30:15
- Current active calls: 3
- System uptime: 2 hours 15 minutes
```

## Example 2: Manage Conference Calls

**User**: "I need to manage the conference rooms. Show me what's active."

**Claude**: *Uses list_conferences tool*

```
Active conference rooms:
1. Conference 1000: 5 participants, unlocked
2. Conference 2000: 2 participants, locked

Would you like me to show participants in any of these rooms?
```

**User**: "Yes, show me who's in conference 1000"

**Claude**: *Uses get_conference_participants tool*

```
Conference 1000 participants:
1. John Doe (2001) - PJSIP/2001-00000001
2. Jane Smith (2002) - PJSIP/2002-00000002
3. Bob Johnson (2003) - PJSIP/2003-00000003
4. Alice Williams (2004) - PJSIP/2004-00000004
5. Charlie Brown (2005) - PJSIP/2005-00000005
```

**User**: "Lock the conference so no one else can join"

**Claude**: *Uses lock_conference tool*

```
✓ Conference 1000 has been locked. No new participants can join.
```

**User**: "Mute participant 2003"

**Claude**: *Uses mute_participant tool*

```
✓ Participant Bob Johnson (2003) has been muted in conference 1000.
```

## Example 3: Extension Management

**User**: "Is extension 2006 registered?"

**Claude**: *Uses get_extension_status tool*

```
Extension 2006 status:
- Registered: Yes
- Device state: Not in use
- Contact: sip:2006@192.168.1.105:5060
- Last registration: 2025-11-08 10:15:30
```

**User**: "Show me all registered extensions"

**Claude**: *Uses list_extensions tool*

```
Registered extensions (8 total):

Active (in use):
- 2001: In use (1 active call)
- 2003: In use (1 active call)

Available (registered but idle):
- 2002: Not in use
- 2004: Not in use
- 2005: Not in use
- 2006: Not in use
- 2007: Not in use
- 2008: Not in use

Offline:
- 2009: Unavailable
- 2010: Unavailable
```

## Example 4: Call Management

**User**: "Make extension 2001 call extension 2002"

**Claude**: *Uses originate_call tool*

```
✓ Call initiated from extension 2001 to 2002
Channel: PJSIP/2001
Destination: 2002
Status: Successfully queued

The call should start ringing momentarily.
```

**User**: "Show me all active calls"

**Claude**: *Uses list_channels tool*

```
Active calls (3):

1. PJSIP/2001-00000001
   - From: John Doe (2001)
   - To: Jane Smith (2002)
   - Duration: 00:02:45
   - State: Up (connected)

2. PJSIP/2003-00000002
   - From: Bob Johnson (2003)
   - To: External (18005551234)
   - Duration: 00:05:12
   - State: Up (connected)

3. PJSIP/2005-00000003
   - From: Charlie Brown (2005)
   - To: Conference 1000
   - Duration: 00:15:30
   - State: Up (connected)
```

**User**: "Hangup the call between 2001 and 2002"

**Claude**: *Uses hangup_channel tool*

```
✓ Call PJSIP/2001-00000001 has been terminated.
```

## Example 5: Dial Plan Configuration

**User**: "I'm setting up a new Groundwire softphone. What dial plan rules should I use?"

**Claude**: *Uses get_dial_rules tool with format='groundwire'*

```
For Groundwire, use this dial plan pattern:
(2xxx|*xx|1[2-9]xxxxxxxxx|011xxxxxxxxxxx)

This allows:
- Internal extensions: 2000-2999
- Feature codes: *00-*99
- US/Canada calls: 1 + 10 digits
- International calls: 011 + country code + number

To configure in Groundwire:
1. Go to Settings → Account → Advanced
2. Set "Dial Plan" to the pattern above
3. Set "Inter-digit Timeout" to 3 seconds
```

**User**: "What feature codes are available?"

**Claude**: *Uses get_feature_codes tool*

```
Available feature codes:

Voicemail:
- *97: Check voicemail

Call Queue:
- *45: Login to queue as agent
- *46: Logout from queue

Call Features:
- *65: Enable call recording
- *66: Disable call recording
- *78: Enable Do Not Disturb
- *79: Disable Do Not Disturb
- *72: Enable call forwarding
- *73: Disable call forwarding
```

**User**: "Validate this number for me: 18005551234"

**Claude**: *Uses validate_number tool*

```
✓ Valid number: 18005551234

Match details:
- Rule: US/Canada long distance
- Type: Outbound call
- Pattern: 1[2-9]xxxxxxxxx
- Description: 11-digit US/Canada number format

This number will dial correctly.
```

## Example 6: Call Analytics

**User**: "Show me call statistics for the past week"

**Claude**: *Uses get_call_stats tool*

```
Call Statistics (Nov 1-8, 2025):

Total calls: 1,247
- Answered: 1,089 (87.3%)
- Missed: 98 (7.9%)
- Busy: 45 (3.6%)
- Failed: 15 (1.2%)

Duration:
- Total talk time: 125 hours 32 minutes
- Average call duration: 6 minutes 12 seconds

Note: Database connection required for detailed statistics.
Configure DB_HOST, DB_NAME, DB_USER, and DB_PASSWORD in .env file.
```

**User**: "Show me the call summary for extension 2001"

**Claude**: *Uses get_extension_summary tool*

```
Extension 2001 Call Summary (Past 30 days):

Outbound calls: 156
Inbound calls: 203
Total calls: 359

Call disposition:
- Answered: 312 (86.9%)
- No answer: 32 (8.9%)
- Busy: 15 (4.2%)

Duration:
- Total talk time: 42 hours 18 minutes
- Average call: 7 minutes 5 seconds

Top destinations:
1. Extension 2002: 45 calls
2. Extension 2003: 32 calls
3. 18005551234: 15 calls
```

## Example 7: Troubleshooting

**User**: "Extension 2009 can't make calls. Help me troubleshoot."

**Claude**: *Uses multiple tools*

```
Let me check extension 2009:

Step 1: Check registration status
*Uses get_extension_status*
✗ Extension 2009 is not registered
Device state: Unavailable

Step 2: Check registration details
*Uses get_extension_registration*
No active contacts found for extension 2009

Diagnosis:
The extension is not registered to the PBX. This could be because:
1. The softphone/device is offline
2. Network connectivity issues
3. Incorrect credentials configured
4. Firewall blocking SIP traffic (port 5060/5061)

Recommended actions:
1. Verify the device is powered on and connected to network
2. Check SIP credentials (username: 2009, password)
3. Verify network allows UDP port 5060 (or TCP 5061 for TLS)
4. Check Asterisk logs: /var/log/asterisk/full
5. Try re-registering the device

Would you like me to check the Asterisk logs for registration attempts?
```

## Example 8: Conference Room Management

**User**: "We're having a board meeting in conference 3000. Set it up for me."

**Claude**: *Uses multiple conference tools*

```
Setting up conference 3000 for your board meeting:

Step 1: Checking current status
*Uses list_conferences*
Conference 3000 is not currently active. It will activate when the first participant joins.

Step 2: Monitoring for participants
Once participants start joining, I can:
- Lock the conference when all expected attendees have joined
- Mute/unmute participants
- Remove unauthorized participants
- Monitor participant count

To join conference 3000:
- From any extension, dial 3000
- From outside, call your DID and enter 3000 when prompted

Ready to monitor. Let me know when I should lock the conference or if you need any participant management.

Tip: You can dial *3000 to join as a marked user (moderator).
```

**User**: "5 people have joined. Lock it now and mute everyone except the moderator."

**Claude**: *Uses lock_conference and mute_participant tools*

```
✓ Conference 3000 locked

Checking participants:
*Uses get_conference_participants*

1. Moderator (2001) - Marked user ✓
2. Participant (2002) - Muted ✓
3. Participant (2003) - Muted ✓
4. Participant (2004) - Muted ✓
5. Participant (2005) - Muted ✓

Conference is ready:
- 5 participants present
- Conference locked (no new joins)
- All non-moderators muted
- Moderator (2001) can speak

To unmute someone, just let me know which participant.
```

## Example 9: Advanced Call Routing

**User**: "Can I dial 911 from this system?"

**Claude**: *Checks emergency number configuration*

```
⚠️ Emergency Calling Status:

911 calling is currently DISABLED on this system.

Before enabling 911:
1. Configure E911 service with your VoIP provider
2. Register physical addresses for all locations
3. Update dial plan to allow 911
4. Test with non-emergency number (933 in some areas)
5. Post clear signage about VoIP 911 limitations

Current emergency numbers configuration:
- 911 (US/Canada): Blocked - Requires E911 setup
- 112 (International): Blocked

Important: VoIP 911 has limitations compared to traditional phone service.
Always have a backup method to contact emergency services.

Would you like help configuring E911 service?
```

## Example 10: System Monitoring

**User**: "Give me a complete status report of the phone system"

**Claude**: *Uses multiple tools to generate comprehensive report*

```
FlexPBX System Status Report
Generated: 2025-11-08 10:30:15

SYSTEM STATUS:
*Uses asterisk_status*
✓ Asterisk running normally
- Version: 20.5.0
- Uptime: 2 hours 15 minutes
- Started: 2025-11-08 08:15:00

ACTIVE CALLS:
*Uses list_channels*
Current calls: 3
- Internal calls: 2
- External calls: 1
- Conference calls: 0

EXTENSIONS:
*Uses list_extensions*
Total extensions: 10
- Registered: 8 (80%)
- In use: 3 (30%)
- Available: 5 (50%)
- Offline: 2 (20%)

CONFERENCES:
*Uses list_conferences*
Active conferences: 1
- Conference 1000: 5 participants, unlocked

SYSTEM HEALTH: ✓ GOOD
All critical systems operational.
No issues detected.

Recommendations:
- 2 extensions offline (2009, 2010) - Check devices
- Consider enabling call recording for quality assurance
- Review CDR logs for call patterns

Would you like detailed information on any specific area?
```

## Integration Patterns

### With Other MCP Servers

Combine with other MCP servers for enhanced functionality:

```javascript
// Example: VoIP + Email MCP
"When extension 2001 receives a voicemail, send email notification"

// Example: VoIP + Calendar MCP
"Schedule conference 1000 for tomorrow at 2pm with 5 participants"

// Example: VoIP + Database MCP
"Generate monthly call report for billing department"
```

### Automation Examples

```javascript
// Auto-lock conferences after hours
"Lock all active conferences after 6pm daily"

// Monitor specific extensions
"Alert me if extension 2001 goes offline"

// Call queue management
"Add extension 2006 to support queue when they dial *45"
```

## Best Practices

1. **Always check status before actions**: Verify extension is registered before originating calls
2. **Use validation**: Validate numbers before dialing with validate_number
3. **Monitor conferences**: Regular participant checks during important meetings
4. **Handle errors gracefully**: AMI operations can fail - always check success
5. **Security first**: Never share AMI credentials, use read-only for analytics

## Testing Your Setup

Run the included test scripts:

```bash
# Test AMI connection
node examples/test-connection.js

# Test conference features
node examples/test-conference.js

# Demo dial plan rules
node examples/dialplan-demo.js
```
