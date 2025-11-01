<?php
/**
 * FlexPBX User Portal - My Phone Numbers
 * View and manage assigned phone numbers
 */

// Require authentication
require_once __DIR__ . '/user_auth_check.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Phone Numbers - FlexPBX</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            background: white;
            padding: 20px 30px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .header h1 {
            color: #2c3e50;
            font-size: 28px;
            margin-bottom: 5px;
        }

        .header p {
            color: #7f8c8d;
            font-size: 14px;
        }

        .card {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .card h2 {
            color: #2c3e50;
            font-size: 22px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #ecf0f1;
        }

        .did-list {
            margin-top: 20px;
        }

        .did-item {
            padding: 20px;
            background: #f8f9fa;
            border-left: 4px solid #3498db;
            border-radius: 5px;
            margin-bottom: 15px;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .did-item:hover {
            transform: translateX(5px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .did-item.shared {
            border-left-color: #f39c12;
        }

        .did-item.personal {
            border-left-color: #2ecc71;
        }

        .did-number {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .did-type {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 10px;
        }

        .did-type.shared {
            background: #f39c12;
            color: white;
        }

        .did-type.personal {
            background: #2ecc71;
            color: white;
        }

        .did-details {
            color: #7f8c8d;
            font-size: 14px;
            margin-top: 10px;
        }

        .did-details p {
            margin: 5px 0;
        }

        .no-dids {
            text-align: center;
            padding: 40px 20px;
            color: #7f8c8d;
        }

        .no-dids i {
            font-size: 64px;
            color: #ecf0f1;
            margin-bottom: 20px;
        }

        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: background 0.3s;
        }

        .btn:hover {
            background: #2980b9;
        }

        .btn-success {
            background: #2ecc71;
        }

        .btn-success:hover {
            background: #27ae60;
        }

        .btn-warning {
            background: #f39c12;
        }

        .btn-warning:hover {
            background: #e67e22;
        }

        .info-box {
            background: #e8f4f8;
            border-left: 4px solid #3498db;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .info-box h3 {
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .info-box p {
            color: #34495e;
            line-height: 1.6;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }

        .stat-value {
            font-size: 36px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 14px;
            opacity: 0.9;
        }

        .loading {
            text-align: center;
            padding: 40px;
            color: #7f8c8d;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            max-width: 500px;
            width: 90%;
        }

        .modal-content h2 {
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #2c3e50;
            font-weight: 600;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>My Phone Numbers (DIDs)</h1>
            <p>Manage your Direct Inward Dialing numbers and request new numbers</p>
        </div>

        <div id="loading" class="card loading">
            <p>Loading your phone numbers...</p>
        </div>

        <div id="content" style="display: none;">
            <!-- Info Box -->
            <div class="info-box">
                <h3>About Your Phone Numbers</h3>
                <p>
                    <strong>Shared DID:</strong> A phone number shared with other users. When someone calls this number, all users assigned to it will ring.<br>
                    <strong>Personal DID:</strong> Your own dedicated phone number. Only you receive calls to this number.
                </p>
            </div>

            <!-- Stats -->
            <div class="stats">
                <div class="stat-card">
                    <div class="stat-value" id="total-dids">0</div>
                    <div class="stat-label">Total DIDs</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="shared-dids">0</div>
                    <div class="stat-label">Shared DIDs</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="personal-dids">0</div>
                    <div class="stat-label">Personal DIDs</div>
                </div>
            </div>

            <!-- DID List -->
            <div class="card">
                <h2>My Phone Numbers</h2>
                <div id="did-list" class="did-list">
                    <!-- DIDs will be loaded here -->
                </div>
                <div style="margin-top: 20px; text-align: center;">
                    <button class="btn btn-success" onclick="openRequestModal()">
                        Request Your Own DID
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Request DID Modal -->
    <div id="requestModal" class="modal">
        <div class="modal-content">
            <h2>Request New Phone Number</h2>
            <form id="requestForm">
                <div class="form-group">
                    <label for="area-code">Preferred Area Code (Optional)</label>
                    <input type="text" id="area-code" placeholder="e.g., 303, 720">
                </div>
                <div class="form-group">
                    <label for="request-type">Request Type</label>
                    <select id="request-type">
                        <option value="new">New Number</option>
                        <option value="port">Port Existing Number</option>
                        <option value="toll_free">Toll-Free Number</option>
                    </select>
                </div>
                <div class="btn-group">
                    <button type="submit" class="btn btn-success">Submit Request</button>
                    <button type="button" class="btn" onclick="closeRequestModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Get user ID from session/URL (in production, use proper session)
        const urlParams = new URLSearchParams(window.location.search);
        const userId = urlParams.get('user_id') || 1; // Default to 1 for testing

        // Load DIDs on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadDIDs();
        });

        // Load user's DIDs
        async function loadDIDs() {
            try {
                const response = await fetch(`/api/user-info.php?path=my_dids&user_id=${userId}`);
                const data = await response.json();

                if (data.success) {
                    displayDIDs(data);
                } else {
                    showError('Failed to load DIDs: ' + (data.error || 'Unknown error'));
                }
            } catch (error) {
                showError('Error loading DIDs: ' + error.message);
            }
        }

        // Display DIDs
        function displayDIDs(data) {
            const didList = document.getElementById('did-list');
            const loading = document.getElementById('loading');
            const content = document.getElementById('content');

            // Hide loading, show content
            loading.style.display = 'none';
            content.style.display = 'block';

            // Update stats
            document.getElementById('total-dids').textContent = data.dids.all.length;
            document.getElementById('shared-dids').textContent = data.dids.shared.length;
            document.getElementById('personal-dids').textContent = data.dids.personal.length;

            // Display DIDs
            if (data.dids.all.length === 0) {
                didList.innerHTML = `
                    <div class="no-dids">
                        <p style="font-size: 18px; margin-bottom: 10px;">No phone numbers assigned yet</p>
                        <p>Contact your administrator or request a new DID below</p>
                    </div>
                `;
            } else {
                didList.innerHTML = '';
                data.dids.all.forEach(did => {
                    const didElement = createDIDElement(did);
                    didList.appendChild(didElement);
                });
            }
        }

        // Create DID element
        function createDIDElement(did) {
            const div = document.createElement('div');
            div.className = `did-item ${did.is_shared == 1 ? 'shared' : 'personal'}`;

            const typeLabel = did.is_shared == 1 ? 'Shared DID' : 'Personal DID';
            const typeClass = did.is_shared == 1 ? 'shared' : 'personal';

            div.innerHTML = `
                <div class="did-number">${formatPhoneNumber(did.did_number)}</div>
                <span class="did-type ${typeClass}">${typeLabel}</span>
                ${did.is_primary == 1 ? '<span class="did-type personal" style="background: #3498db; margin-left: 10px;">PRIMARY</span>' : ''}
                <div class="did-details">
                    <p><strong>Extension:</strong> ${did.extension}</p>
                    <p><strong>Type:</strong> ${did.did_type}</p>
                    <p><strong>Assigned:</strong> ${formatDate(did.assigned_date)}</p>
                    ${did.notes ? `<p><strong>Notes:</strong> ${did.notes}</p>` : ''}
                </div>
            `;

            return div;
        }

        // Format phone number
        function formatPhoneNumber(number) {
            // Format as (XXX) XXX-XXXX
            const cleaned = number.replace(/\D/g, '');
            if (cleaned.length === 10) {
                return `(${cleaned.substr(0,3)}) ${cleaned.substr(3,3)}-${cleaned.substr(6,4)}`;
            } else if (cleaned.length === 11 && cleaned[0] === '1') {
                return `+1 (${cleaned.substr(1,3)}) ${cleaned.substr(4,3)}-${cleaned.substr(7,4)}`;
            }
            return number;
        }

        // Format date
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        }

        // Show error
        function showError(message) {
            const loading = document.getElementById('loading');
            loading.innerHTML = `<p style="color: #e74c3c;">${message}</p>`;
        }

        // Modal functions
        function openRequestModal() {
            document.getElementById('requestModal').classList.add('active');
        }

        function closeRequestModal() {
            document.getElementById('requestModal').classList.remove('active');
        }

        // Handle form submission
        document.getElementById('requestForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const areaCode = document.getElementById('area-code').value;
            const requestType = document.getElementById('request-type').value;

            try {
                const response = await fetch('/api/user-info.php?path=request_did', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        user_id: userId,
                        area_code: areaCode,
                        type: requestType
                    })
                });

                const data = await response.json();

                if (data.success) {
                    alert('DID request submitted successfully! An administrator will review your request.');
                    closeRequestModal();
                } else {
                    alert('Failed to submit request: ' + (data.error || 'Unknown error'));
                }
            } catch (error) {
                alert('Error submitting request: ' + error.message);
            }
        });
    </script>
</body>
</html>
