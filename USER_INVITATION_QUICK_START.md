# FlexPBX User Invitation & Department Management - Quick Start Guide

## 📍 Where to Find It

The user invitation and department management features are now available in your **Admin Dashboard**.

### Quick Access Links

1. **Admin Dashboard** → **User & Department Management** section
2. Direct URLs:
   - **Invite Users:** https://flexpbx.devinecreations.net/admin/send-invite.php
   - **Department Management:** https://flexpbx.devinecreations.net/admin/department-management.php
   - **Extensions Management:** https://flexpbx.devinecreations.net/admin/admin-extensions-management.php

---

## ✉️ How to Invite New Users

### Step 1: Access Send Invitation Page
1. Log in to the **Admin Dashboard**
2. Scroll to **"User & Department Management"** section
3. Click **"Invite Users"** card

### Step 2: Fill Out Invitation Form
The invitation form will allow you to:
- Enter user's **email address**
- Enter user's **name**
- Assign an **extension number** (auto-assigns next available)
- Assign to a **department** (optional)
- Set **user role** (user, admin, manager)
- Add a **welcome message**

### Step 3: Send Invitation
- Click **"Send Invitation"**
- User receives email with:
  - Unique invitation link
  - Extension number assigned
  - Setup instructions
  - Temporary password

### Step 4: User Accepts Invitation
- User clicks link in email
- Sets up their extension credentials
- Configures voicemail PIN
- Downloads softphone app (optional)

---

## 🏢 How to Manage Departments

### Creating a New Department

1. Go to **Department Management** page
2. Click **"Create New Department"**
3. Enter department details:
   - **Department Name** (e.g., "Sales", "Support", "IT")
   - **Description**
   - **Manager** (assign an existing user)
   - **Department Number** (optional - for direct dial)

4. Click **"Save Department"**

### Assigning Extensions to Departments

**Method 1: During User Invitation**
- When inviting a user, select department from dropdown
- Extension is automatically assigned to that department

**Method 2: From Department Management**
- Open department
- Click **"Add Extension"**
- Select extension from list
- Click **"Assign"**

**Method 3: From Extensions Management**
- Go to **Extensions Management**
- Click on an extension
- Select department from dropdown
- Click **"Save"**

### Department Features

Departments allow you to:
- **Group extensions** by team or function
- **Route calls** to entire departments
- **Set department hours** (business hours)
- **Assign managers** who can manage department settings
- **Create department queues** (all calls go to queue)
- **Department voicemail** (shared voicemail box)

---

## 👥 Extension Auto-Assignment

### How Auto-Assignment Works

When you invite a new user:

1. **System checks** for next available extension
2. **Default range:** 2000-2999 (1000 extensions)
3. **Skips used extensions** automatically
4. **Assigns next free number**

### Manual Extension Assignment

You can also manually assign specific extensions:

1. In invitation form, check **"Manually assign extension"**
2. Enter desired extension number (e.g., 2050)
3. System validates:
   - Extension is available
   - Extension is in valid range
   - No conflicts with existing extensions

---

## 📋 Complete Workflow Example

### Scenario: Invite 3 Sales Team Members

**Step 1: Create Sales Department**
1. Go to **Department Management**
2. Click **"Create Department"**
3. Name: "Sales Team"
4. Description: "Customer sales and lead conversion"
5. Department Number: 5000 (callers can dial 5000 to reach sales)
6. Click **"Save"**

**Step 2: Invite First Sales Rep**
1. Go to **Send Invitation**
2. Email: john@company.com
3. Name: John Smith
4. Department: **Sales Team** (select from dropdown)
5. Role: User
6. Extension: *Auto-assigned (e.g., 2007)*
7. Click **"Send Invitation"**

**Step 3: Invite Remaining Sales Reps**
- Repeat for Jane Doe → Auto-assigned 2008
- Repeat for Bob Johnson → Auto-assigned 2009

**Step 4: Configure Department Routing**
1. Go to **Department Management** → **Sales Team**
2. Click **"Configure Call Routing"**
3. Options:
   - **Ring All** - All 3 extensions ring simultaneously
   - **Round Robin** - Rotate calls among team members
   - **Longest Idle** - Call goes to person idle longest
   - **Queue** - Calls enter queue, answered in order
4. Save routing configuration

**Result:**
- Callers dial **5000** → Reaches entire sales team
- All 3 sales reps have their own extensions (2007-2009)
- Each has their own voicemail
- Department manager can view team stats

---

## 🔧 Advanced Features

### Bulk Invite
*Coming soon* - Upload CSV to invite multiple users at once

### Department Hierarchies
Create parent/child relationships:
- **Sales** (parent)
  - Inside Sales (child)
  - Field Sales (child)

### Department Analytics
View department statistics:
- Total calls handled
- Average response time
- Missed calls
- Voicemail count

### Department Hours
Set different business hours per department:
- Sales: 9 AM - 9 PM
- Support: 24/7
- Billing: 9 AM - 5 PM

---

## 📞 Extension Number Ranges

### Default Ranges
- **User Extensions:** 2000-2999 (1000 available)
- **Conference Rooms:** 8000-8999
- **Feature Codes:** *XX (e.g., *97, *43)
- **Department Direct Dial:** 5000-5999
- **IVR Menus:** 6000-6999
- **Call Queues:** 7000-7999

### Custom Ranges
Admins can configure custom ranges in:
**System Tools** → **Extension Ranges Configuration**

---

## ❓ Common Questions

### Q: Can I change an extension number after assignment?
**A:** Yes! Go to **Extensions Management**, select the extension, click **"Change Number"**, and assign a new one.

### Q: What happens if a user doesn't accept invitation?
**A:** Invitations expire after 7 days. You can resend invitations from **Send Invitation** page.

### Q: Can users be in multiple departments?
**A:** Each user has one primary department, but can be added to multiple **ring groups** spanning departments.

### Q: How do I remove a user?
**A:** Go to **Extensions Management**, select user, click **"Deactivate"** (preserves data) or **"Delete"** (permanent).

### Q: Can department managers invite users?
**A:** Yes! Assign **"Manager"** role to user. They can invite users to their department only.

---

## 🎯 Quick Tips

✅ **Use descriptive department names** - Makes routing easier
✅ **Assign managers early** - Delegate department management
✅ **Set department hours** - Automatic after-hours routing
✅ **Use department queues** - Better call distribution
✅ **Review department analytics** - Identify bottlenecks

---

## 📚 Related Features

- **Ring Groups** - Group extensions across departments
- **Call Queues** - Advanced queueing with priority
- **IVR Builder** - Route by department in phone menu
- **Call Routing Rules** - Time-based, caller-based routing

---

## 🆘 Need Help?

- **Documentation:** https://flexpbx.devinecreations.net/docs/
- **Support:** (302) 313-9555
- **Email:** support@devine-creations.com

---

**Version:** 1.0  
**Last Updated:** November 9, 2025  
**FlexPBX Version:** 1.3+
