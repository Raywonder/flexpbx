# FlexPBX User Migration & Re-Assignment - Complete Guide

## 🎯 Overview

The User Migration system allows you to seamlessly move users between extensions and departments while preserving all their data, automatically updating queues, and maintaining system integrity.

---

## 🔄 What Gets Updated Automatically

### When You Change Extension Number

✅ **Automatically Updated:**
- Extension number in database
- PJSIP SIP configuration
- Queue memberships (all occurrences)
- Voicemail box location and configuration
- User portal display
- FlexPhone web client configuration
- Call history associations
- Department roster

⚠️ **Requires Manual Update:**
- Third-party SIP clients (softphones, desk phones)
- Physical phone configurations
- User must update their devices with new extension number

### When You Move Department

✅ **Automatically Updated:**
- Department assignment in database
- Queue memberships (removed from old, added to new)
- Department-specific permissions
- Manager assignments
- Department analytics
- User portal department display

✅ **No Changes Required:**
- Extension number stays the same
- SIP clients continue working normally
- Voicemail stays at same extension
- No user action needed

---

## 📍 Where to Find It

**Admin Dashboard** → **User & Department Management** → **User Migration**

Direct URL: `/admin/user-migration.php`

---

## 🚀 Migration Types

### 1. Complete User Migration
Move a user with options for both extension change AND department move.

**Use Cases:**
- User promoted to new role in different department
- Reorganization requiring both department and extension changes
- Replacing an existing user's extension

**Steps:**
1. Select user to migrate
2. Check "Change Extension Number" (optional)
3. Check "Move to Different Department" (optional)
4. Fill in new extension/department
5. Choose whether to notify user
6. Preview migration impact
7. Execute migration

### 2. Quick Extension Change
Change extension number only, department stays same.

**Use Cases:**
- User wants a specific extension number
- Consolidating extension ranges
- Replacing lost/compromised credentials

**Steps:**
1. Select user
2. Enter new extension (or leave blank for auto-assign)
3. Choose to preserve voicemail
4. Execute change

### 3. Department Transfer
Move to different department, extension stays same.

**Use Cases:**
- Department reorganization
- User transferred to new team
- Role change within company

**Steps:**
1. Select user
2. Select new department
3. Choose to update queue memberships
4. Execute transfer

### 4. Bulk Migration
Move multiple users at once.

**Use Cases:**
- Department merger
- Mass reorganization
- Office relocation

**Steps:**
1. Select multiple users (click cards)
2. Choose bulk action
3. Set parameters
4. Preview bulk changes
5. Execute bulk migration

---

## 📋 Step-by-Step Examples

### Example 1: Promote User to Manager (New Extension + New Department)

**Scenario:** John Smith (Ext 2015, Sales) promoted to Sales Manager

**Steps:**
1. Go to **User Migration** page
2. Select **"Migrate User"** tab
3. Select user: **John Smith (2015)**
4. ✅ Check **"Change Extension Number"**
5. Enter new extension: **2100** (manager range)
6. ✅ Check **"Move to Different Department"**
7. Select department: **Sales Management**
8. ✅ Check **"Update queue memberships"**
9. Reason: "Promoted to Sales Manager"
10. ✅ Check **"Send notification email"**
11. Click **"Preview Migration"**
12. Review impact:
    - Extension: 2015 → 2100 ⚠️ SIP clients need update
    - Department: Sales → Sales Management
    - Removed from: Sales Queue
    - Added to: Sales Manager Queue
    - Voicemail preserved
13. Click **"Execute Migration"**

**Result:**
- John now has extension 2100
- Moved to Sales Management department
- Added to manager queue
- Removed from regular sales queue
- Email sent with new extension info
- FlexPhone auto-updated
- User portal updated
- Must update desk phone/softphone to extension 2100

---

### Example 2: Department Transfer (Extension Stays Same)

**Scenario:** Jane Doe (Ext 2020, Support) transferred to Sales team

**Steps:**
1. Go to **User Migration** → **"Move Department"** tab
2. Select user: **Jane Doe (2020)**
3. Select department: **Sales**
4. ✅ Check **"Update queue memberships"**
5. Reason: "Transferred to sales team"
6. Click **"Transfer Department"**

**Result:**
- Jane keeps extension 2020
- Department changed to Sales
- Removed from Support Queue
- Added to Sales Queue
- All SIP clients continue working (no changes needed)
- User portal shows new department

---

### Example 3: Extension Number Swap

**Scenario:** Bob wants extension 2005 (currently used by Alice), Alice gets 2025

**Steps:**
1. **First:** Move Alice temporarily
   - Select Alice (2005)
   - Change extension to 2025
   - Execute
2. **Then:** Move Bob
   - Select Bob (current extension)
   - Change extension to 2005
   - Execute

**Result:**
- Alice now has 2025
- Bob now has 2005
- Both keep their departments
- Both must update SIP clients

---

### Example 4: Bulk Department Move

**Scenario:** Merge Support Team A and Support Team B into unified Support

**Steps:**
1. Go to **Bulk Migration** tab
2. Select all users from "Support Team A" (click cards)
3. Select all users from "Support Team B"
4. Bulk Action: **"Move to Different Department"**
5. Select department: **Support (Unified)**
6. ✅ **"Update queue memberships"**
7. Click **"Preview Bulk Migration"**
8. Review all changes
9. Click **"Execute Bulk Migration"**

**Result:**
- All selected users moved to unified Support department
- All added to new Support queue
- Old queues emptied
- Extensions unchanged (SIP clients work normally)

---

## 🔍 Understanding Migration Impact

### Extension Change Impact

**Green (Auto-Updated):**
- ✅ FlexPhone web client
- ✅ User portal
- ✅ Queue memberships
- ✅ Voicemail location
- ✅ Call logs association
- ✅ PJSIP configuration

**Yellow (User Action Required):**
- ⚠️ Third-party SIP softphones (Zoiper, Linphone, etc.)
- ⚠️ Physical desk phones
- ⚠️ Mobile SIP apps

**What User Must Do:**
1. Open their SIP client settings
2. Change username/extension to new number
3. Keep password the same (unless specified)
4. Save and re-register

### Department Change Impact

**Green (Auto-Updated):**
- ✅ Department roster
- ✅ Queue memberships (if enabled)
- ✅ Department permissions
- ✅ Manager visibility
- ✅ Department analytics

**No User Action Required:**
- Extension stays same
- SIP clients continue working
- Phone configuration unchanged

---

## 📧 User Notifications

### Extension Change Email

```
Subject: FlexPBX Extension Update - Action Required

Hello John Smith,

Your FlexPBX account has been updated:
Extension: 2015 → 2100
Department: Sales → Sales Management

IMPORTANT: Your extension number has changed from 2015 to 2100

Action Required:
1. Update your third-party SIP clients (softphones, desk phones) with new extension: 2100
2. Your FlexPhone web client has been automatically updated - no action needed
3. Your user portal now shows your new extension - no action needed

If you have any questions, please contact support.

Best regards,
FlexPBX Admin Team
```

### Department Move Email

```
Subject: FlexPBX Department Update

Hello Jane Doe,

Your FlexPBX account has been updated:
Department: Support → Sales

Your extension number (2020) remains unchanged.
Your SIP clients will continue to work normally.

If you have any questions, please contact support.

Best regards,
FlexPBX Admin Team
```

---

## 🗄️ Data Preservation

### What Gets Preserved

✅ **Always Preserved:**
- Voicemail messages (moved with extension)
- Call history (re-associated)
- User settings and preferences
- Contact lists
- Custom greetings
- Blocked caller lists
- Follow-me settings
- Do Not Disturb settings

### Voicemail Migration

When extension changes from 2015 to 2100:

**Automatic Process:**
1. `/var/spool/asterisk/voicemail/flexpbx/2015/` → moved to → `/var/spool/asterisk/voicemail/flexpbx/2100/`
2. voicemail.conf updated: `2015 =>` changed to `2100 =>`
3. All messages, greetings, and settings preserved
4. New voicemails go to new extension

**User Experience:**
- No messages lost
- Greetings carry over
- PIN stays same
- Call history intact

---

## 📊 Queue Management

### Automatic Queue Updates

When user changes extension or department, queues are automatically updated:

**Extension Change (2015 → 2100):**
```sql
-- Before
queue_members: PJSIP/2015 in Sales Queue

-- After (automatic)
queue_members: PJSIP/2100 in Sales Queue
```

**Department Change (Sales → Support):**
```sql
-- Before
queue_members: PJSIP/2020 in Sales Queue

-- After (automatic)
queue_members: PJSIP/2020 in Support Queue
(removed from Sales Queue)
```

### Manual Queue Override

If you uncheck "Update queue memberships":
- User stays in current queues
- Useful for special cases (user in multiple departments)
- Can manually adjust queues later

---

## 📜 Migration History

All migrations are logged with complete audit trail.

**Recorded Information:**
- User ID and name
- Old extension → New extension
- Old department → New department
- Migration reason
- Admin user who performed migration
- Timestamp
- Detailed change log

**Viewing History:**
1. Go to **User Migration** → **"Migration History"** tab
2. Filter by:
   - All migrations
   - Extension changes only
   - Department moves only
   - Last 30 days
3. Click entry to see full details

**History Entry Example:**
```
User: John Smith (ID: 45)
Date: November 9, 2025 10:30 AM
Admin: admin_admin
Changes:
  - Extension: 2015 → 2100
  - Department: Sales → Sales Management
  - Removed from queue: Sales
  - Added to queue: Sales Management
Reason: Promoted to Sales Manager
```

---

## ⚠️ Important Considerations

### Before Migration

☑️ **Checklist:**
- [ ] Verify new extension is available
- [ ] Confirm department exists
- [ ] Check user has no active calls
- [ ] Notify user of upcoming change
- [ ] Schedule during low-call-volume time
- [ ] Backup voicemail if critical

### During Migration

The system automatically:
- Locks user record during migration
- Updates all related tables atomically
- Reloads Asterisk configuration
- Sends notification emails

**Typical Migration Time:**
- Extension change only: 2-3 seconds
- Department move only: 1-2 seconds
- Full migration (both): 3-5 seconds

### After Migration

**Verify:**
- [ ] User can register with new extension (if changed)
- [ ] Voicemail accessible at new extension
- [ ] User appears in correct queues
- [ ] Department shows correctly in user portal
- [ ] Call history intact

**User Follow-Up:**
- Email confirmation sent
- User updates third-party SIP clients (if extension changed)
- Test inbound/outbound calls
- Verify voicemail access

---

## 🔧 Troubleshooting

### Issue: User Can't Register After Extension Change

**Cause:** SIP client still using old extension

**Solution:**
1. Open SIP client settings
2. Change username/auth username to new extension
3. Keep password same
4. Save and restart client

### Issue: Voicemail Not Accessible

**Cause:** Voicemail migration incomplete

**Solution:**
```bash
# Check voicemail directory
ls -la /var/spool/asterisk/voicemail/flexpbx/{new_extension}/

# Check voicemail.conf
grep {new_extension} /etc/asterisk/voicemail.conf

# Reload voicemail
asterisk -rx "voicemail reload"
```

### Issue: User Missing from Queue

**Cause:** Queue membership update failed

**Solution:**
```bash
# Check queue members
asterisk -rx "queue show {queue_name}"

# Manually add
asterisk -rx "queue add member PJSIP/{extension} to {queue_name}"

# Or update via admin UI: Queue Management page
```

### Issue: Old Extension Still Shows in Portal

**Cause:** Browser cache

**Solution:**
1. Hard refresh browser (Ctrl+F5)
2. Clear cache
3. Log out and log back in
4. Verify backend shows new extension

---

## 🎓 Best Practices

### Extension Number Strategy

**Recommended Ranges:**
- 2000-2099: Regular users
- 2100-2199: Managers
- 2200-2299: Executives
- 2300-2999: General pool

**Benefits:**
- Easy to identify user level by extension
- Organized call routing
- Simplified queue management

### Department Organization

**Hierarchy:**
- Sales (parent)
  - Inside Sales (child)
  - Field Sales (child)
- Support (parent)
  - L1 Support (child)
  - L2 Support (child)

**Queue Assignment:**
- Auto-assign users to department queue
- Use queue skills for advanced routing
- Monitor queue performance

### Migration Scheduling

**Best Times:**
- Outside business hours
- During low-call-volume periods
- Avoid Friday afternoons (harder to fix issues)
- Allow 1-2 hours for testing

**Communication:**
- Email users 24 hours in advance
- Provide clear instructions
- Have support available during change
- Follow up within 24 hours

---

## 📞 Support

**Issues or Questions:**
- **Phone:** (302) 313-9555
- **Email:** support@devine-creations.com
- **Documentation:** https://flexpbx.devinecreations.net/docs/

---

**Version:** 1.0  
**Last Updated:** November 9, 2025  
**FlexPBX Version:** 1.3+
