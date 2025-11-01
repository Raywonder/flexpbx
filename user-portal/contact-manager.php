<?php
/**
 * FlexPBX Contact Manager
 * Full-featured contact management system with caller ID integration
 */

// Require authentication
require_once __DIR__ . '/user_auth_check.php';

// Get extension from authenticated session
$extension = $user_extension;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Manager - FlexPBX</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 28px;
            font-weight: 600;
        }

        .header-actions {
            display: flex;
            gap: 15px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: white;
            color: #667eea;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
        }

        .tabs {
            display: flex;
            background: #f8f9fa;
            border-bottom: 2px solid #e5e7eb;
        }

        .tab {
            padding: 15px 25px;
            cursor: pointer;
            border: none;
            background: none;
            font-size: 15px;
            font-weight: 500;
            color: #6b7280;
            transition: all 0.3s ease;
            border-bottom: 3px solid transparent;
        }

        .tab:hover {
            color: #667eea;
            background: #f3f4f6;
        }

        .tab.active {
            color: #667eea;
            border-bottom-color: #667eea;
            background: white;
        }

        .tab-content {
            display: none;
            padding: 30px;
        }

        .tab-content.active {
            display: block;
        }

        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            gap: 15px;
            flex-wrap: wrap;
        }

        .search-box {
            flex: 1;
            min-width: 300px;
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 12px 45px 12px 15px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }

        .search-box input:focus {
            outline: none;
            border-color: #667eea;
        }

        .search-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
        }

        .filter-group {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .filter-group select {
            padding: 10px 15px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
        }

        .contacts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
        }

        .contact-card {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .contact-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            border-color: #667eea;
        }

        .contact-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }

        .contact-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .contact-name {
            font-size: 18px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 5px;
        }

        .contact-company {
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 10px;
        }

        .contact-info {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin: 15px 0;
        }

        .contact-info-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            color: #4b5563;
        }

        .contact-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e5e7eb;
        }

        .icon-btn {
            padding: 8px 12px;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            background: white;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 14px;
        }

        .icon-btn:hover {
            background: #f3f4f6;
            border-color: #667eea;
            color: #667eea;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 15px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 25px 50px rgba(0,0,0,0.3);
        }

        .modal-header {
            padding: 25px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            font-size: 22px;
            font-weight: 600;
        }

        .close-modal {
            background: none;
            border: none;
            color: white;
            font-size: 28px;
            cursor: pointer;
            line-height: 1;
        }

        .modal-body {
            padding: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #374151;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .stat-value {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 14px;
            opacity: 0.9;
        }

        .call-history-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            margin-bottom: 10px;
            transition: all 0.2s ease;
        }

        .call-history-item:hover {
            border-color: #667eea;
            background: #f9fafb;
        }

        .call-type-badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .call-inbound {
            background: #d1fae5;
            color: #065f46;
        }

        .call-outbound {
            background: #dbeafe;
            color: #1e40af;
        }

        .call-missed {
            background: #fee2e2;
            color: #991b1b;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #9ca3af;
        }

        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            .contacts-grid {
                grid-template-columns: 1fr;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .toolbar {
                flex-direction: column;
            }

            .search-box {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📇 Contact Manager</h1>
            <div class="header-actions">
                <button class="btn btn-primary" onclick="showNewContactModal()">
                    ➕ New Contact
                </button>
                <a href="/user-portal/dashboard.php?extension=<?php echo $extension; ?>" class="btn btn-secondary">
                    🏠 Dashboard
                </a>
            </div>
        </div>

        <div class="tabs">
            <button class="tab active" onclick="switchTab('contacts')">📇 Contacts</button>
            <button class="tab" onclick="switchTab('recent')">📞 Recent Calls</button>
            <button class="tab" onclick="switchTab('favorites')">⭐ Favorites</button>
            <button class="tab" onclick="switchTab('blacklist')">🚫 Blacklist</button>
            <button class="tab" onclick="switchTab('stats')">📊 Statistics</button>
        </div>

        <!-- Contacts Tab -->
        <div id="contacts-tab" class="tab-content active">
            <div class="toolbar">
                <div class="search-box">
                    <input type="text" id="search-input" placeholder="Search contacts..." onkeyup="searchContacts()">
                    <span class="search-icon">🔍</span>
                </div>
                <div class="filter-group">
                    <select id="contact-type-filter" onchange="filterContacts()">
                        <option value="">All Types</option>
                        <option value="personal">Personal</option>
                        <option value="business">Business</option>
                        <option value="emergency">Emergency</option>
                    </select>
                    <button class="btn btn-secondary" onclick="showImportModal()">📥 Import</button>
                    <button class="btn btn-secondary" onclick="exportContacts()">📤 Export</button>
                </div>
            </div>

            <div id="contacts-container" class="contacts-grid">
                <!-- Contacts will be loaded here -->
            </div>
        </div>

        <!-- Recent Calls Tab -->
        <div id="recent-tab" class="tab-content">
            <div class="toolbar">
                <select id="call-type-filter" onchange="loadRecentCalls()">
                    <option value="">All Calls</option>
                    <option value="inbound">Inbound</option>
                    <option value="outbound">Outbound</option>
                    <option value="missed">Missed</option>
                </select>
            </div>
            <div id="recent-calls-container">
                <!-- Recent calls will be loaded here -->
            </div>
        </div>

        <!-- Favorites Tab -->
        <div id="favorites-tab" class="tab-content">
            <div id="favorites-container" class="contacts-grid">
                <!-- Favorite contacts will be loaded here -->
            </div>
        </div>

        <!-- Blacklist Tab -->
        <div id="blacklist-tab" class="tab-content">
            <div class="toolbar">
                <button class="btn btn-danger" onclick="showAddBlacklistModal()">🚫 Add to Blacklist</button>
            </div>
            <div id="blacklist-container">
                <!-- Blacklist will be loaded here -->
            </div>
        </div>

        <!-- Statistics Tab -->
        <div id="stats-tab" class="tab-content">
            <div class="stats-grid" id="stats-grid">
                <!-- Stats will be loaded here -->
            </div>
        </div>
    </div>

    <!-- New/Edit Contact Modal -->
    <div id="contact-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="contact-modal-title">New Contact</h2>
                <button class="close-modal" onclick="closeModal('contact-modal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="contact-form" onsubmit="saveContact(event)">
                    <input type="hidden" id="contact-id" name="id">

                    <div class="form-row">
                        <div class="form-group">
                            <label>First Name *</label>
                            <input type="text" id="first-name" name="first_name" required>
                        </div>
                        <div class="form-group">
                            <label>Last Name *</label>
                            <input type="text" id="last-name" name="last_name" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Company</label>
                            <input type="text" id="company" name="company">
                        </div>
                        <div class="form-group">
                            <label>Title</label>
                            <input type="text" id="title" name="title">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Primary Phone *</label>
                            <input type="tel" id="primary-phone" name="primary_phone" required>
                        </div>
                        <div class="form-group">
                            <label>Mobile Phone</label>
                            <input type="tel" id="mobile-phone" name="mobile_phone">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Work Phone</label>
                            <input type="tel" id="work-phone" name="work_phone">
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" id="email" name="email">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Type</label>
                        <select id="contact-type" name="contact_type">
                            <option value="personal">Personal</option>
                            <option value="business">Business</option>
                            <option value="emergency">Emergency</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Notes</label>
                        <textarea id="notes" name="notes" rows="3"></textarea>
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="favorite" name="favorite">
                            Add to Favorites
                        </label>
                    </div>

                    <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('contact-modal')">Cancel</button>
                        <button type="submit" class="btn btn-success">Save Contact</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const extension = '<?php echo $extension; ?>';
        let allContacts = [];

        // Load contacts on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadContacts();
            loadRecentCalls();
            loadStats();
        });

        // Switch between tabs
        function switchTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });

            // Show selected tab
            document.getElementById(tabName + '-tab').classList.add('active');
            event.target.classList.add('active');

            // Load data for specific tabs
            if (tabName === 'favorites') {
                loadFavorites();
            } else if (tabName === 'blacklist') {
                loadBlacklist();
            } else if (tabName === 'stats') {
                loadStats();
            }
        }

        // Load contacts from API
        async function loadContacts() {
            try {
                const response = await fetch(`/api/contacts.php?path=contacts&extension=${extension}&limit=100`);
                const data = await response.json();

                if (data.success) {
                    allContacts = data.contacts;
                    displayContacts(allContacts);
                }
            } catch (error) {
                console.error('Error loading contacts:', error);
            }
        }

        // Display contacts
        function displayContacts(contacts) {
            const container = document.getElementById('contacts-container');

            if (contacts.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-state-icon">📇</div>
                        <h3>No contacts found</h3>
                        <p>Create your first contact to get started</p>
                    </div>
                `;
                return;
            }

            container.innerHTML = contacts.map(contact => {
                const initials = (contact.first_name[0] || '') + (contact.last_name[0] || '');
                return `
                    <div class="contact-card" onclick="viewContact(${contact.id})">
                        <div class="contact-avatar">${initials}</div>
                        <div class="contact-name">${contact.first_name} ${contact.last_name}</div>
                        ${contact.company ? `<div class="contact-company">${contact.company}</div>` : ''}
                        <div class="contact-info">
                            ${contact.primary_phone ? `
                                <div class="contact-info-item">
                                    📞 ${formatPhone(contact.primary_phone)}
                                </div>
                            ` : ''}
                            ${contact.email ? `
                                <div class="contact-info-item">
                                    ✉️ ${contact.email}
                                </div>
                            ` : ''}
                            ${contact.total_calls > 0 ? `
                                <div class="contact-info-item">
                                    📊 ${contact.total_calls} calls
                                </div>
                            ` : ''}
                        </div>
                        <div class="contact-actions" onclick="event.stopPropagation()">
                            <button class="icon-btn" onclick="callContact('${contact.primary_phone}')" title="Call">📞</button>
                            <button class="icon-btn" onclick="editContact(${contact.id})" title="Edit">✏️</button>
                            <button class="icon-btn" onclick="toggleFavorite(${contact.id}, ${contact.favorite})" title="Favorite">
                                ${contact.favorite ? '⭐' : '☆'}
                            </button>
                            <button class="icon-btn" onclick="deleteContact(${contact.id})" title="Delete">🗑️</button>
                        </div>
                    </div>
                `;
            }).join('');
        }

        // Search contacts
        function searchContacts() {
            const query = document.getElementById('search-input').value.toLowerCase();
            const filtered = allContacts.filter(contact =>
                contact.first_name.toLowerCase().includes(query) ||
                contact.last_name.toLowerCase().includes(query) ||
                (contact.company && contact.company.toLowerCase().includes(query)) ||
                (contact.primary_phone && contact.primary_phone.includes(query))
            );
            displayContacts(filtered);
        }

        // Filter contacts by type
        function filterContacts() {
            const type = document.getElementById('contact-type-filter').value;
            const filtered = type ? allContacts.filter(c => c.contact_type === type) : allContacts;
            displayContacts(filtered);
        }

        // Show new contact modal
        function showNewContactModal() {
            document.getElementById('contact-modal-title').textContent = 'New Contact';
            document.getElementById('contact-form').reset();
            document.getElementById('contact-id').value = '';
            document.getElementById('contact-modal').classList.add('active');
        }

        // Close modal
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        // Save contact
        async function saveContact(event) {
            event.preventDefault();

            const formData = new FormData(event.target);
            const contactData = {
                owner_extension: extension
            };

            formData.forEach((value, key) => {
                if (key === 'favorite') {
                    contactData[key] = value ? 1 : 0;
                } else {
                    contactData[key] = value;
                }
            });

            const contactId = document.getElementById('contact-id').value;
            const path = contactId ? 'update' : 'create';

            try {
                const response = await fetch(`/api/contacts.php?path=${path}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(contactData)
                });

                const data = await response.json();

                if (data.success) {
                    alert('Contact saved successfully!');
                    closeModal('contact-modal');
                    loadContacts();
                } else {
                    alert('Error saving contact: ' + (data.error || 'Unknown error'));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error saving contact');
            }
        }

        // Format phone number
        function formatPhone(phone) {
            const cleaned = phone.replace(/\D/g, '');
            if (cleaned.length === 10) {
                return `(${cleaned.substr(0,3)}) ${cleaned.substr(3,3)}-${cleaned.substr(6)}`;
            }
            return phone;
        }

        // Load recent calls
        async function loadRecentCalls() {
            const type = document.getElementById('call-type-filter')?.value || '';
            try {
                const response = await fetch(`/api/contacts.php?path=recent-calls&extension=${extension}&type=${type}&limit=50`);
                const data = await response.json();

                if (data.success) {
                    displayRecentCalls(data.calls);
                }
            } catch (error) {
                console.error('Error loading recent calls:', error);
            }
        }

        // Display recent calls
        function displayRecentCalls(calls) {
            const container = document.getElementById('recent-calls-container');

            if (calls.length === 0) {
                container.innerHTML = '<div class="empty-state"><div class="empty-state-icon">📞</div><h3>No recent calls</h3></div>';
                return;
            }

            container.innerHTML = calls.map(call => {
                const date = new Date(call.call_date);
                const contactName = call.first_name && call.last_name ?
                    `${call.first_name} ${call.last_name}` :
                    call.caller_name || call.caller_number;

                return `
                    <div class="call-history-item">
                        <div>
                            <div style="font-weight: 600; margin-bottom: 5px;">${contactName}</div>
                            <div style="font-size: 14px; color: #6b7280;">${call.caller_number}</div>
                        </div>
                        <div style="text-align: right;">
                            <span class="call-type-badge call-${call.call_type}">${call.call_type}</span>
                            <div style="font-size: 12px; color: #9ca3af; margin-top: 5px;">
                                ${date.toLocaleString()}
                            </div>
                            ${call.duration > 0 ? `<div style="font-size: 12px; color: #6b7280;">Duration: ${Math.floor(call.duration / 60)}:${(call.duration % 60).toString().padStart(2, '0')}</div>` : ''}
                        </div>
                    </div>
                `;
            }).join('');
        }

        // Load statistics
        async function loadStats() {
            try {
                const response = await fetch(`/api/contacts.php?path=stats&extension=${extension}`);
                const data = await response.json();

                if (data.success) {
                    displayStats(data);
                }
            } catch (error) {
                console.error('Error loading stats:', error);
            }
        }

        // Display statistics
        function displayStats(stats) {
            const container = document.getElementById('stats-grid');
            container.innerHTML = `
                <div class="stat-card">
                    <div class="stat-value">${stats.contacts.total_contacts}</div>
                    <div class="stat-label">Total Contacts</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">${stats.contacts.business_contacts}</div>
                    <div class="stat-label">Business Contacts</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">${stats.contacts.favorite_contacts}</div>
                    <div class="stat-label">Favorites</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">${stats.calls.total_calls}</div>
                    <div class="stat-label">Total Calls</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">${stats.calls.inbound_calls}</div>
                    <div class="stat-label">Inbound Calls</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">${stats.calls.outbound_calls}</div>
                    <div class="stat-label">Outbound Calls</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">${stats.calls.missed_calls}</div>
                    <div class="stat-label">Missed Calls</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">${stats.contacts.blocked_contacts}</div>
                    <div class="stat-label">Blocked Numbers</div>
                </div>
            `;
        }

        // Load favorites
        async function loadFavorites() {
            try {
                const response = await fetch(`/api/contacts.php?path=contacts&extension=${extension}&favorites=1`);
                const data = await response.json();

                if (data.success) {
                    const container = document.getElementById('favorites-container');
                    if (data.contacts.length === 0) {
                        container.innerHTML = '<div class="empty-state"><div class="empty-state-icon">⭐</div><h3>No favorites yet</h3><p>Star contacts to add them to your favorites</p></div>';
                    } else {
                        displayContacts(data.contacts);
                    }
                }
            } catch (error) {
                console.error('Error loading favorites:', error);
            }
        }

        // Toggle favorite
        async function toggleFavorite(contactId, currentStatus) {
            try {
                const response = await fetch(`/api/contacts.php?path=update`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        id: contactId,
                        favorite: currentStatus ? 0 : 1
                    })
                });

                const data = await response.json();
                if (data.success) {
                    loadContacts();
                }
            } catch (error) {
                console.error('Error toggling favorite:', error);
            }
        }

        // Delete contact
        async function deleteContact(contactId) {
            if (!confirm('Are you sure you want to delete this contact?')) {
                return;
            }

            try {
                const response = await fetch(`/api/contacts.php?path=delete&id=${contactId}`, {
                    method: 'DELETE'
                });

                const data = await response.json();
                if (data.success) {
                    alert('Contact deleted successfully');
                    loadContacts();
                } else {
                    alert('Error deleting contact');
                }
            } catch (error) {
                console.error('Error deleting contact:', error);
                alert('Error deleting contact');
            }
        }

        // Export contacts
        function exportContacts() {
            window.location.href = `/api/contacts.php?path=export&extension=${extension}`;
        }
    </script>
</body>
</html>
