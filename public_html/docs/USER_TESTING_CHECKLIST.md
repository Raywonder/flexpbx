# FlexPBX User Testing Checklist

**Version:** 1.0
**Date:** October 14, 2025
**Purpose:** Complete testing checklist for FlexPBX users

---

## 🎯 User Role

**Who Should Use This:**
- Extension users (employees, agents)
- Call center agents
- Office staff
- Anyone making/receiving calls

**What You'll Test:**
- Extension registration
- Making/receiving calls
- Voicemail features
- Call transfers
- Feature codes
- User portal
- Accessibility

---

## 📋 Getting Started

### 1. Sign Up for an Account

**Step 1: Request Extension**
1. Go to: https://flexpbx.devinecreations.net/user-portal/signup.php
2. Fill out sign-up form:
   - Your full name
   - Your email address
   - Requested 4-digit extension (e.g., 2004)
   - Reason for account (optional)
3. Submit form
4. Wait for admin approval
5. Check email for credentials

**Step 2: Receive Your Credentials**
You'll receive:
```
Extension: [Your 4-digit number]
Password: [Your password]
Voicemail PIN: [Usually same as extension]
Server: flexpbx.devinecreations.net
Port: 5060
```

### 2. Set Up Your Softphone

**Download a Softphone:**
- **Windows:** Zoiper, MicroSIP
- **Mac:** Zoiper, Telephone
- **Linux:** Zoiper, Linphone
- **iOS:** Zoiper, Linphone
- **Android:** Zoiper, Linphone

**Configure Softphone:**
1. Open your softphone
2. Add new account/SIP account
3. Enter your details:
   ```
   Username: [Your extension, e.g., 2001]
   Password: [Your password]
   Domain/Server: flexpbx.devinecreations.net
   Port: 5060
   ```
4. Save and connect
5. Check for "Registered" or "Available" status

---

## 📞 Basic Call Testing

### Making Your First Call

**Test 1: Echo Test (Internal)**
1. [ ] Dial: *43
2. [ ] Wait for connection
3. [ ] Speak into your phone
4. [ ] You should hear your voice echoed back
5. [ ] Hang up when done

**Test 2: Time Announcement**
1. [ ] Dial: *44
2. [ ] Listen to date/time announcement
3. [ ] Verify it's correct
4. [ ] Hang up

**Test 3: Call Another Extension**
1. [ ] Dial another user's extension (e.g., 2000 or 2001)
2. [ ] Wait for them to answer
3. [ ] Have a short conversation
4. [ ] Check audio quality
5. [ ] Hang up

**Test 4: Make External Call** (If enabled)
1. [ ] Dial: 1 + area code + phone number
2. [ ] Call should go through
3. [ ] Talk normally
4. [ ] Check audio quality
5. [ ] Hang up

### Receiving Calls

**Test 5: Receive Internal Call**
1. [ ] Have another user call your extension
2. [ ] Phone should ring
3. [ ] Answer the call
4. [ ] Talk and check audio
5. [ ] Hang up

**Test 6: Receive External Call** (If applicable)
1. [ ] Have someone call your DID
2. [ ] Phone should ring
3. [ ] Answer and talk
4. [ ] Check audio quality
5. [ ] Hang up

---

## 📬 Voicemail Testing

### Leaving Voicemail

**Test 7: Leave Voicemail for Someone**
1. [ ] Call another extension
2. [ ] Don't let them answer (wait for voicemail)
3. [ ] Listen to greeting
4. [ ] Leave a message after beep
5. [ ] Press # when done
6. [ ] Hang up

### Accessing Your Voicemail

**Test 8: Check Your Voicemail**
1. [ ] Dial: *97
2. [ ] System recognizes your extension
3. [ ] Enter your PIN when prompted
4. [ ] Press 1 for new messages
5. [ ] Listen to message
6. [ ] Try these options:
   - [ ] Press 1 to repeat message
   - [ ] Press 3 for advanced options
   - [ ] Press 7 to delete message
   - [ ] Press 9 to save message
7. [ ] Hang up or press * to return to main menu

### Voicemail Features

**Test 9: Explore Voicemail Menu**
1. [ ] Dial *97 and login
2. [ ] Press 0 for mailbox options
3. [ ] Try these (don't actually change unless you want to):
   - [ ] Press 1 to record unavailable greeting
   - [ ] Press 2 to record busy greeting
   - [ ] Press 3 to record your name
   - [ ] Press 5 to change password
4. [ ] Press * to return to main menu
5. [ ] Hang up

---

## 🔄 Call Transfer Testing

### Blind Transfer (Quick Transfer)

**Test 10: Blind Transfer**
1. [ ] Call another extension (e.g., 2000)
2. [ ] Wait for answer
3. [ ] While on call, press #
4. [ ] Listen for "Transfer" prompt
5. [ ] Dial the extension to transfer to (e.g., 2002)
6. [ ] Press # to complete transfer
7. [ ] You are disconnected
8. [ ] Verify the two parties are connected

### Attended Transfer (Announced Transfer)

**Test 11: Attended Transfer**
1. [ ] Call another extension (e.g., 2000)
2. [ ] Wait for answer
3. [ ] While on call, press *2
4. [ ] Listen for "Transfer" prompt
5. [ ] Dial the extension to transfer to (e.g., 2002)
6. [ ] Wait for 2002 to answer
7. [ ] Announce the call to 2002
8. [ ] Press # to complete transfer
9. [ ] Verify original caller now connected to 2002

---

## 🎛️ Feature Code Testing

### Queue Management (If Applicable)

**Test 12: Queue Login**
1. [ ] Dial: *45
2. [ ] Listen to confirmation message
3. [ ] You are now logged into the queue
4. [ ] Dial *45 again
5. [ ] Should hear "already logged in" message

**Test 13: Queue Logout**
1. [ ] Dial: *46
2. [ ] Listen to confirmation message
3. [ ] You are now logged out of queue
4. [ ] Dial *46 again
5. [ ] Should hear "already logged out" message

**Test 14: Queue Status**
1. [ ] Dial: *48
2. [ ] Listen to queue status information
3. [ ] Hang up

### Music on Hold

**Test 15: MOH Preview**
1. [ ] Dial: *78
2. [ ] Listen to music on hold
3. [ ] Let it play for 30 seconds
4. [ ] Verify quality
5. [ ] Hang up

---

## 🖥️ User Portal Testing

### Login and Navigation

**Test 16: User Portal Login**
1. [ ] Go to: https://flexpbx.devinecreations.net/user-portal/
2. [ ] Enter your extension
3. [ ] Enter your password
4. [ ] Click "Login"
5. [ ] Dashboard should load

**Test 17: Explore Dashboard**
1. [ ] View your extension status
2. [ ] Check SIP settings card
3. [ ] View queue management card
4. [ ] Check voicemail card
5. [ ] View call statistics
6. [ ] Check quick dial codes

### Voicemail Settings

**Test 18: Voicemail Settings Page**
1. [ ] From user portal dashboard
2. [ ] Click "Manage Voicemail"
3. [ ] View your voicemail status
4. [ ] Check message counts
5. [ ] Review all feature toggles
6. [ ] Try toggling a feature on/off (optional)
7. [ ] Review help instructions
8. [ ] Navigate back to dashboard

### Recording Management

**Test 19: My Recordings**
1. [ ] Click "Manage Recordings"
2. [ ] View available recordings
3. [ ] Upload a greeting (optional)
4. [ ] Navigate back

### Logout

**Test 20: Logout**
1. [ ] Click "Logout" button
2. [ ] Verify you're logged out
3. [ ] Try to go back (should redirect to login)

---

## ♿ Accessibility Testing

### Keyboard Navigation

**Test 21: Keyboard-Only Navigation**
1. [ ] Open user portal
2. [ ] Use only Tab key to navigate
3. [ ] Fill out login form
4. [ ] Press Enter to submit
5. [ ] Navigate dashboard with Tab
6. [ ] Use Shift+Tab to go backwards
7. [ ] Verify no keyboard traps
8. [ ] All buttons/links reachable

### Screen Reader (If You Use One)

**Test 22: Screen Reader Experience**
1. [ ] Enable your screen reader (NVDA, JAWS, VoiceOver)
2. [ ] Navigate to user portal
3. [ ] Listen to page title
4. [ ] Navigate login form
5. [ ] Verify labels are announced
6. [ ] Submit form
7. [ ] Navigate dashboard
8. [ ] Check that all cards are announced
9. [ ] Navigate voicemail settings
10. [ ] Report any issues

### Visual Accessibility

**Test 23: Zoom Test**
1. [ ] Zoom browser to 200% (Ctrl/Cmd +)
2. [ ] Check layout doesn't break
3. [ ] Verify text is readable
4. [ ] All buttons still clickable
5. [ ] Zoom back to 100%

---

## 🧪 Stress Testing

### Multiple Calls

**Test 24: Sequential Calls**
1. [ ] Make 5 calls in a row
2. [ ] Check audio quality each time
3. [ ] Verify no degradation
4. [ ] Check for any errors

### Long Call

**Test 25: Extended Call**
1. [ ] Make a 15-30 minute call
2. [ ] Check audio quality throughout
3. [ ] Verify no disconnections
4. [ ] Check for audio problems

### Feature Usage

**Test 26: Use All Features**
1. [ ] Make a call
2. [ ] Transfer it
3. [ ] Check voicemail
4. [ ] Login to queue
5. [ ] Logout of queue
6. [ ] Make another call
7. [ ] Verify everything still works

---

## 📊 Call Quality Assessment

**For Each Call, Rate:**

```
Test Call #___
Date/Time: _______________
Called: _______________
Duration: _______________

Audio Quality:
○ Excellent - Crystal clear
○ Good - Minor issues
○ Fair - Noticeable problems
○ Poor - Hard to understand

Latency (Delay):
○ None - Real-time conversation
○ Slight - Barely noticeable
○ Noticeable - Sometimes talk over each other
○ Bad - Significant delay

Issues:
○ Echo
○ Static
○ Choppy audio
○ One-way audio
○ Disconnections
○ Other: _______________

Overall Rating: ___/10

Notes:
```

---

## 🐛 Reporting Problems

### If Something Doesn't Work

**Document These Details:**
1. **What were you trying to do?**
2. **What actually happened?**
3. **What should have happened?**
4. **Steps to reproduce:**
   - Step 1
   - Step 2
   - Step 3
5. **Your extension:** ____
6. **Device/softphone:** ____
7. **Time it happened:** ____
8. **Any error messages:** ____

**Where to Report:**
- Bug Tracker: https://flexpbx.devinecreations.net/admin/bug-tracker.html
- Or tell your administrator

---

## ✅ User Testing Checklist Summary

### Basic Functionality
- [ ] Extension registered
- [ ] Echo test works (*43)
- [ ] Time test works (*44)
- [ ] Internal calls work
- [ ] External calls work (if applicable)
- [ ] Receive calls works
- [ ] Audio quality good

### Voicemail
- [ ] Can leave voicemail
- [ ] Can check voicemail (*97)
- [ ] Can navigate menus
- [ ] Can delete messages
- [ ] Can change settings
- [ ] Email notifications work (if enabled)

### Transfers
- [ ] Blind transfer works (#)
- [ ] Attended transfer works (*2)
- [ ] Transfer to voicemail works

### Feature Codes
- [ ] Queue login works (*45)
- [ ] Queue logout works (*46)
- [ ] Queue status works (*48)
- [ ] MOH preview works (*78)

### User Portal
- [ ] Can login
- [ ] Dashboard works
- [ ] Voicemail settings accessible
- [ ] Can navigate all sections
- [ ] Can logout

### Accessibility
- [ ] Keyboard navigation works
- [ ] Screen reader compatible (if applicable)
- [ ] Zoom to 200% works
- [ ] No major barriers found

### Overall
- [ ] System stable
- [ ] Call quality acceptable
- [ ] Easy to use
- [ ] Would recommend
- [ ] Ready for daily use

---

## 📝 User Test Results Template

```
## FlexPBX User Testing - Results

**Name:**
**Extension:**
**Date:**
**Softphone:**
**Device/OS:**

### Basic Calling
- Make calls: PASS / FAIL
- Receive calls: PASS / FAIL
- Audio quality: Excellent / Good / Fair / Poor
- Notes:

### Voicemail
- Leave message: PASS / FAIL
- Check message: PASS / FAIL
- Navigate menus: PASS / FAIL
- Notes:

### Transfers
- Blind transfer: PASS / FAIL
- Attended transfer: PASS / FAIL
- Notes:

### Feature Codes
- Echo test (*43): PASS / FAIL
- Voicemail (*97): PASS / FAIL
- Queue codes: PASS / FAIL / NOT TESTED
- Notes:

### User Portal
- Login: PASS / FAIL
- Navigate: PASS / FAIL
- Settings: PASS / FAIL
- Notes:

### Accessibility (If Applicable)
- Keyboard only: PASS / FAIL / NOT TESTED
- Screen reader: PASS / FAIL / NOT TESTED
- Zoom test: PASS / FAIL / NOT TESTED
- Notes:

### User Experience
- Easy to set up: YES / NO
- Easy to use: YES / NO
- Would use daily: YES / NO
- Would recommend: YES / NO
- Overall rating: __/10

### Problems Encountered
1. [Problem description]
2. [Problem description]
3. [Problem description]

### Suggestions
[Your ideas for improvements]

### Additional Comments
[Your feedback here]
```

---

**User Testing Status:** Ready to Begin
**Estimated Time:** 2-3 hours for thorough testing
**Support:** Contact admin for help or questions
**Bug Tracker:** https://flexpbx.devinecreations.net/admin/bug-tracker.html
