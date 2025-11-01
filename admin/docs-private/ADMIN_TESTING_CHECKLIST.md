# FlexPBX Admin Testing Checklist

**Version:** 1.0
**Date:** October 14, 2025
**Purpose:** Complete testing checklist for PBX administrators

---

## 🎯 Admin Role & Responsibilities

**Who Should Use This:**
- PBX Administrators
- System Administrators
- Support Staff Supervisors
- Technical Managers

**What You'll Test:**
- System configuration
- User management
- Trunk/DID setup
- Feature code management
- Voicemail system administration
- Web interface functionality
- Documentation accessibility

---

## 📋 Pre-Testing Setup

### 1. Get Admin Access

**Option A: Sign Up for Admin Account**
1. Go to: https://flexpbx.devinecreations.net/admin/signup.php
2. Fill out the form:
   - Your full name
   - Your email address
   - Requested role (Admin, Support, etc.)
   - Reason for access
3. Submit and wait for approval
4. Check email for credentials

**Option B: Use Test Admin Credentials** (If provided)
```
Username: [Provided by system owner]
Password: [Provided by system owner]
```

### 2. Verify System Access

**Test URLs:**
- [ ] Admin Dashboard: https://flexpbx.devinecreations.net/admin/dashboard.html
- [ ] Voicemail Manager: https://flexpbx.devinecreations.net/admin/voicemail-manager.html
- [ ] Feature Codes Manager: https://flexpbx.devinecreations.net/admin/feature-codes-manager.html
- [ ] Documentation Center: https://flexpbx.devinecreations.net/docs/
- [ ] User Portal: https://flexpbx.devinecreations.net/user-portal/

### 3. Install Testing Tools

**Required:**
- [ ] Web browser (Chrome, Firefox, Safari, Edge)
- [ ] SIP softphone (Zoiper, MicroSIP, Bria)
- [ ] Text editor (for config viewing)
- [ ] SSH client (if server access provided)

**Optional:**
- [ ] Screen reader (NVDA, JAWS, VoiceOver)
- [ ] Browser dev tools knowledge
- [ ] Basic Linux commands

---

## 🔧 System Configuration Testing

### Extension Management

**Test Creating New Extension:**
1. [ ] Access Asterisk configuration (if permitted)
2. [ ] Add new extension in pjsip.conf
3. [ ] Add extension to dialplan
4. [ ] Reload Asterisk
5. [ ] Test registration with softphone
6. [ ] Verify in admin dashboard
7. [ ] Test making calls

**Document Results:**
```
Extension created: ____
Registration: Success / Failed
Call test: Success / Failed
Issues:
```

### Trunk Configuration

**Test Current Trunk:**
1. [ ] Access admin dashboard
2. [ ] Check Callcentric trunk status
3. [ ] Test outbound call: Dial 1 + area code + number
4. [ ] Test inbound call: Have someone call your DID
5. [ ] Document call quality
6. [ ] Check for any errors

**Callcentric Trunk Info:**
```
DID: 1-777-817-1572
Status: Check in dashboard
Outbound: Test with real call
Inbound: Test with real call
```

**Add New DID/Trunk (If Needed):**
1. [ ] Get SIP credentials from provider
2. [ ] Add to pjsip.conf
3. [ ] Configure outbound routing
4. [ ] Configure inbound routing
5. [ ] Test both directions
6. [ ] Document configuration

### Feature Code Configuration

**Test Feature Codes Manager:**
1. [ ] Access: https://flexpbx.devinecreations.net/admin/feature-codes-manager.html
2. [ ] Review all feature codes listed
3. [ ] Toggle a feature code off
4. [ ] Click "Reload Dialplan"
5. [ ] Verify code is disabled (dial it - should not work)
6. [ ] Toggle back on
7. [ ] Reload dialplan again
8. [ ] Verify code works again

**Feature Codes to Verify:**
- [ ] *43 - Echo test
- [ ] *44 - Time announcement
- [ ] *45 - Queue login
- [ ] *46 - Queue logout
- [ ] *48 - Queue status
- [ ] *77 - MOH + stats
- [ ] *78 - MOH preview
- [ ] *97 - Voicemail

### Voicemail Administration

**Test Voicemail Manager:**
1. [ ] Access: https://flexpbx.devinecreations.net/admin/voicemail-manager.html
2. [ ] Check "Mailboxes" tab
   - [ ] View all mailboxes
   - [ ] Check message counts
   - [ ] Try "Edit" button
3. [ ] Check "Global Features" tab
   - [ ] View all features
   - [ ] Toggle a feature off/on
   - [ ] Click "Save All Features"
4. [ ] Check "System Settings" tab
   - [ ] Review settings
   - [ ] Note any that need changing
5. [ ] Check "Email Templates" tab
   - [ ] Review current template
   - [ ] Test format

**Test Adding New Mailbox:**
1. [ ] Click "Add New Mailbox"
2. [ ] Note: Manual process required
3. [ ] Add to /etc/asterisk/voicemail.conf
4. [ ] Reload voicemail module
5. [ ] Verify in admin panel
6. [ ] Test with user

---

## 👥 User Management Testing

### User Account Creation

**Via User Portal Sign-Up:**
1. [ ] Have test user go to: https://flexpbx.devinecreations.net/user-portal/signup.php
2. [ ] User fills out sign-up form
3. [ ] Check for sign-up file in /home/flexpbxuser/signups/
4. [ ] Review sign-up request
5. [ ] Create extension for user
6. [ ] Email credentials
7. [ ] Verify user can login

**Via Admin Creation:**
1. [ ] Add extension in pjsip.conf
2. [ ] Add voicemail box
3. [ ] Add to user portal credentials
4. [ ] Test login
5. [ ] Verify all features work

### User Support Testing

**Test Helping User With Issue:**
1. [ ] User reports problem (simulate)
2. [ ] Check documentation for solution
3. [ ] Check system logs if needed
4. [ ] Apply fix
5. [ ] Verify with user
6. [ ] Document solution

**Common Issues to Test:**
- [ ] Can't register extension
- [ ] No audio on calls
- [ ] Voicemail not working
- [ ] Transfer not working
- [ ] Feature code not responding

---

## 🌐 Web Interface Testing

### Admin Dashboard

**Navigation Test:**
1. [ ] Open: https://flexpbx.devinecreations.net/admin/dashboard.html
2. [ ] Click each management tool card
3. [ ] Verify all links work
4. [ ] Check documentation section
5. [ ] Test on mobile device
6. [ ] Test with keyboard only (Tab key)

**Functionality Test:**
1. [ ] Check system status displays
2. [ ] Click "Manage Voicemail"
3. [ ] Click "Manage Feature Codes"
4. [ ] Click "Open Documentation"
5. [ ] Navigate back each time
6. [ ] Check for broken links

### Documentation Center

**Content Test:**
1. [ ] Access: https://flexpbx.devinecreations.net/docs/
2. [ ] Use search bar - search for "voicemail"
3. [ ] Click a document
4. [ ] Read for accuracy
5. [ ] Download Markdown version
6. [ ] Check on mobile device
7. [ ] Test all quick access links

**Key Documents to Review:**
- [ ] FEATURE_CODES.html
- [ ] VOICEMAIL_AND_TRANSFERS_COMPLETE.html
- [ ] AUDIO_TROUBLESHOOTING.html
- [ ] FLEXPBX_STANDALONE_ARCHITECTURE.html
- [ ] STABLE_RELEASE_v1.0_TESTING.html

### User Portal (Admin View)

**Test User Experience:**
1. [ ] Login as test user
2. [ ] Navigate dashboard
3. [ ] Access voicemail settings
4. [ ] Try changing password
5. [ ] Toggle voicemail features
6. [ ] Logout
7. [ ] Verify experience is smooth

---

## ♿ Accessibility Testing

### Keyboard Navigation

**Admin Dashboard Test:**
1. [ ] Open admin dashboard
2. [ ] Press Tab repeatedly
3. [ ] Verify logical order
4. [ ] Try Shift+Tab (backwards)
5. [ ] Press Enter on links
6. [ ] Verify no keyboard traps
7. [ ] Check focus indicators visible

**Form Testing:**
1. [ ] Navigate form with Tab
2. [ ] Fill out fields
3. [ ] Submit with Enter key
4. [ ] Verify accessible error messages
5. [ ] Test all forms (login, sign-up, etc.)

### Screen Reader Testing (If Available)

**Enable Screen Reader:**
- Windows: NVDA or JAWS
- Mac: VoiceOver (Cmd+F5)
- Linux: Orca

**Test Tasks:**
1. [ ] Navigate admin dashboard
2. [ ] Listen to card descriptions
3. [ ] Fill out a form
4. [ ] Navigate documentation
5. [ ] Search for content
6. [ ] Click links
7. [ ] Report any issues with announcements

**What Should Be Announced:**
- Page titles
- Form labels
- Button purposes
- Link destinations
- Error messages
- Status updates

### Visual Accessibility

**Zoom Test:**
1. [ ] Zoom browser to 200% (Ctrl/Cmd +)
2. [ ] Check layout doesn't break
3. [ ] Verify text readable
4. [ ] Check buttons still clickable
5. [ ] Test on all main pages

**High Contrast Test:**
1. [ ] Enable OS high contrast mode
2. [ ] Check text visibility
3. [ ] Check focus indicators
4. [ ] Check button visibility
5. [ ] Report any issues

---

## 🧪 System Stability Testing

### Load Testing

**Sequential Calls:**
1. [ ] Make 10 calls in a row
2. [ ] Note any performance degradation
3. [ ] Check audio quality each time
4. [ ] Monitor system resources (if possible)

**Concurrent Calls:**
1. [ ] Have 2+ users make calls simultaneously
2. [ ] Check audio quality on both
3. [ ] Try transfers during concurrent calls
4. [ ] Monitor for any issues

### Long-Duration Tests

**Extended Call Test:**
1. [ ] Make a 30-minute call
2. [ ] Check audio quality throughout
3. [ ] Verify no disconnections
4. [ ] Check for audio degradation

**System Uptime Test:**
1. [ ] Note system start time
2. [ ] Use system for 24+ hours
3. [ ] Check for memory leaks
4. [ ] Verify no performance loss
5. [ ] Check logs for errors

### Feature Stress Test

**Rapid Feature Use:**
1. [ ] Dial all feature codes quickly
2. [ ] Login/logout of queue 10 times
3. [ ] Access voicemail repeatedly
4. [ ] Transfer calls multiple times
5. [ ] Check system remains stable

---

## 📊 Performance Monitoring

### Call Quality Metrics

**For Each Call Test:**
```
Date/Time: _______________
From Ext: _______________
To Number: _______________
Duration: _______________
Audio Quality: Excellent / Good / Fair / Poor
Latency: None / Slight / Noticeable / Bad
Packet Loss: None / Occasional / Frequent
Overall: 1-10 rating
Notes:
```

### System Resource Monitoring (If Access)

**Check Resources:**
1. [ ] CPU usage
2. [ ] Memory usage
3. [ ] Disk space
4. [ ] Network bandwidth
5. [ ] Asterisk process health

**Commands (If SSH Access):**
```bash
# CPU and memory
top

# Asterisk status
asterisk -rx "core show version"
asterisk -rx "pjsip show endpoints"
asterisk -rx "core show channels"

# Check logs
tail -f /var/log/asterisk/messages
```

---

## 🐛 Bug Reporting

### If You Find an Issue

**Document These Details:**
1. **Issue Title:** Brief description
2. **Severity:** Critical / High / Medium / Low
3. **Steps to Reproduce:**
   - Step 1
   - Step 2
   - Step 3
4. **Expected Behavior:** What should happen
5. **Actual Behavior:** What actually happened
6. **Environment:**
   - Role: Admin
   - Browser: Chrome/Firefox/Safari/Edge
   - OS: Windows/Mac/Linux
   - Screen Reader: If applicable
7. **Screenshots/Logs:** If available
8. **Workaround:** If you found one

**Where to Report:**
- Save to file: `/home/flexpbxuser/bug-reports/`
- Or email to system owner

---

## ✅ Final Admin Checklist

### System Configuration
- [ ] All extensions working
- [ ] Trunks registered
- [ ] DIDs routing correctly
- [ ] Feature codes operational
- [ ] Voicemail system configured
- [ ] Queue management working

### User Management
- [ ] Can create users
- [ ] Can manage extensions
- [ ] Can reset passwords
- [ ] Can handle support requests
- [ ] Sign-up system works

### Web Interfaces
- [ ] Admin dashboard functional
- [ ] All management tools accessible
- [ ] Documentation complete
- [ ] User portal operational
- [ ] All links working

### Accessibility
- [ ] Keyboard navigation works
- [ ] Screen reader compatible
- [ ] Zoom to 200% functional
- [ ] High contrast readable
- [ ] No critical barriers found

### System Health
- [ ] No critical bugs
- [ ] Performance acceptable
- [ ] Stability verified
- [ ] Call quality good
- [ ] Ready for production

---

## 📝 Admin Test Results Template

```
## FlexPBX Admin Testing - Results

**Admin Name:**
**Date:**
**Duration:**
**Role:** Super Admin / Admin / Support

### System Configuration
- Extension management: PASS / FAIL
- Trunk configuration: PASS / FAIL
- DID routing: PASS / FAIL
- Feature codes: PASS / FAIL
- Voicemail admin: PASS / FAIL
- Notes:

### User Management
- Create users: PASS / FAIL
- Manage extensions: PASS / FAIL
- Handle support: PASS / FAIL
- Sign-up system: PASS / FAIL
- Notes:

### Web Interfaces
- Admin dashboard: PASS / FAIL
- Management tools: PASS / FAIL
- Documentation: PASS / FAIL
- User portal: PASS / FAIL
- Notes:

### Accessibility
- Keyboard navigation: PASS / FAIL / NOT TESTED
- Screen reader: PASS / FAIL / NOT TESTED
- Visual (zoom/contrast): PASS / FAIL / NOT TESTED
- Notes:

### System Stability
- Call quality: Excellent / Good / Fair / Poor
- Performance: Fast / Acceptable / Slow
- Stability: Stable / Some Issues / Unstable
- Ready for production: YES / NO / NEEDS WORK
- Notes:

### Bugs Found
1. [Issue description]
2. [Issue description]
3. [Issue description]

### Recommendations
[Your suggestions for improvements]

### Overall Assessment
- System readiness: Ready / Needs Work / Not Ready
- Would recommend: YES / NO
- Admin experience: Easy / Moderate / Difficult
- Documentation quality: Excellent / Good / Fair / Poor
- Overall rating: __/10

### Additional Comments
[Your feedback here]
```

---

## 🚀 Next Steps After Testing

### If Tests Pass
1. Approve system for production
2. Train additional admins
3. Set up user accounts
4. Configure additional features
5. Plan for scaling

### If Issues Found
1. Document all bugs
2. Prioritize by severity
3. Work with developer (when usage resets)
4. Retest after fixes
5. Approve when stable

---

**Admin Testing Status:** Ready to Begin
**Estimated Time:** 4-6 hours for thorough testing
**Contact:** System owner for questions or issues
**Next Session:** Complete SMS and desktop apps (when usage resets)
