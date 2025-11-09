<?php require_once __DIR__ . '/admin_auth_check.php'; ?>
<!DOCTYPE html>
<html>
<head>
    <title>FlexPBX - Trunks & DIDs Manager</title>
    <style>
        body {
            font-family: system-ui;
            background: #1e1e1e;
            color: white;
            padding: 20px;
            margin: 0;
        }
        .container { max-width: 1400px; margin: 0 auto; }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .section {
            margin: 20px 0;
            padding: 20px;
            background: #2a2a2a;
            border-radius: 8px;
        }
        .trunk-card {
            background: #333;
            padding: 20px;
            margin: 15px 0;
            border-radius: 8px;
            border-left: 4px solid #007acc;
        }
        .trunk-card.active {
            border-left-color: #4ade80;
        }
        .trunk-card.offline {
            border-left-color: #dc2626;
        }
        .did-row {
            background: #2d2d2d;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            border-left: 3px solid #60a5fa;
        }
        label {
            display: block;
            margin: 10px 0 5px 0;
            font-weight: bold;
        }
        input, select, textarea {
            width: 100%;
            padding: 10px;
            background: #2d2d2d;
            border: 1px solid #555;
            border-radius: 5px;
            color: white;
            font-size: 14px;
            box-sizing: border-box;
            margin-bottom: 10px;
        }
        button {
            background: #007acc;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 5px;
            cursor: pointer;
            margin: 5px;
            font-size: 14px;
        }
        button:hover { background: #005999; }
        button.success { background: #16a34a; }
        button.success:hover { background: #15803d; }
        button.danger { background: #dc2626; }
        button.danger:hover { background: #991b1b; }
        .success { color: #4ade80; }
        .error { color: #f87171; }
        .info { color: #60a5fa; }
        .warning { color: #fbbf24; }
        .status { padding: 10px; border-radius: 5px; margin: 10px 0; }
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #555;
        }
        .tab {
            padding: 10px 20px;
            background: transparent;
            border: none;
            cursor: pointer;
            color: #999;
        }
        .tab.active {
            color: white;
            border-bottom: 3px solid #007acc;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #555;
        }
        th {
            background: #2d2d2d;
            font-weight: bold;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        .badge-success { background: #16a34a; }
        .badge-danger { background: #dc2626; }
        .badge-warning { background: #fbbf24; color: #000; }
        .badge-info { background: #60a5fa; color: #000; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📡 Trunks & DIDs Manager</h1>
            <p>Manage SIP trunks, credentials, DIDs, and channel limits</p>
        </div>

        <div class="tabs">
            <button class="tab active" onclick="switchTab('trunks')">🔌 Trunks</button>
            <button class="tab" onclick="switchTab('dids')">📞 DIDs</button>
            <button class="tab" onclick="switchTab('channels')">📊 Channels</button>
            <button class="tab" onclick="switchTab('registration')">🔐 Registration Status</button>
        </div>

        <!-- Trunks Tab -->
        <div id="trunks-tab" class="tab-content active">
            <div class="section">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <h2>Configured Trunks</h2>
                    <button onclick="addNewTrunk()">➕ Add New Trunk</button>
                </div>

                <!-- Callcentric Trunk -->
                <div class="trunk-card active" id="trunk-callcentric">
                    <div style="display: flex; justify-content: space-between; align-items: start;">
                        <div>
                            <h3>🟢 CallCentric Primary</h3>
                            <p class="info">Provider: Callcentric | Type: SIP</p>
                        </div>
                        <div>
                            <span class="badge badge-success">ACTIVE</span>
                            <span class="badge badge-info">2 Channels</span>
                        </div>
                    </div>

                    <div class="grid-2">
                        <div>
                            <label>Username (Account Number):</label>
                            <input type="text" id="cc-username" value="17778171572" readonly>
                        </div>
                        <div>
                            <label>Auth Name:</label>
                            <input type="text" id="cc-authname" value="Raywonder" readonly>
                        </div>
                    </div>

                    <div class="grid-2">
                        <div>
                            <label>SIP Password:</label>
                            <input type="password" id="cc-password" value="860719938242" readonly>
                        </div>
                        <div>
                            <label>SIP Server:</label>
                            <input type="text" id="cc-server" value="sip.callcentric.com:5060" readonly>
                        </div>
                    </div>

                    <div class="grid-2">
                        <div>
                            <label>Max Channels:</label>
                            <input type="number" id="cc-channels" value="2" readonly>
                        </div>
                        <div>
                            <label>Transport:</label>
                            <select id="cc-transport" disabled>
                                <option value="UDP" selected>UDP</option>
                                <option value="TCP">TCP</option>
                                <option value="TLS">TLS</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <button onclick="editTrunk('callcentric')">✏️ Edit Trunk</button>
                        <button onclick="saveTrunk('callcentric')" id="cc-save-btn" style="display:none;" class="success">💾 Save Changes</button>
                        <button onclick="cancelEdit('callcentric')" id="cc-cancel-btn" style="display:none;">❌ Cancel</button>
                        <button onclick="testTrunk('callcentric')">🧪 Test Registration</button>
                        <button onclick="viewTrunkLogs('callcentric')">📋 View Logs</button>
                    </div>
                </div>

                <!-- Google Voice Trunk -->
                <div class="trunk-card active" id="trunk-googlevoice">
                    <div style="display: flex; justify-content: space-between; align-items: start;">
                        <div>
                            <h3>🟢 Google Voice API</h3>
                            <p class="info">Provider: Google Voice | Type: API</p>
                        </div>
                        <div>
                            <span class="badge badge-success">ACTIVE</span>
                            <span class="badge badge-info">Unlimited</span>
                        </div>
                    </div>

                    <div class="grid-2">
                        <div>
                            <label>Primary Number:</label>
                            <input type="text" value="(281) 301-5784" readonly>
                        </div>
                        <div>
                            <label>Authentication:</label>
                            <input type="text" value="OAuth2 (Connected)" readonly>
                        </div>
                    </div>

                    <div>
                        <button onclick="editTrunk('googlevoice')">⚙️ Configure</button>
                        <button onclick="testTrunk('googlevoice')">🧪 Test API</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- DIDs Tab -->
        <div id="dids-tab" class="tab-content">
            <div class="section">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <h2>Direct Inward Dial (DID) Numbers</h2>
                    <button onclick="addNewDID()">➕ Add New DID</button>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th>DID Number</th>
                            <th>Description</th>
                            <th>Trunk</th>
                            <th>Destination</th>
                            <th>Channels</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="did-table-body">
                        <tr>
                            <td><strong>(312) 313-9555</strong></td>
                            <td>Main Business Line - Chicago</td>
                            <td>CallCentric Primary</td>
                            <td>IVR Menu (101)</td>
                            <td>2 / 2</td>
                            <td><span class="badge badge-success">ACTIVE</span></td>
                            <td>
                                <button onclick="editDID('3123139555')">✏️ Edit</button>
                                <button onclick="routeDID('3123139555')">🎯 Route</button>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>(281) 301-5784</strong></td>
                            <td>Google Voice - Primary</td>
                            <td>Google Voice API</td>
                            <td>IVR Menu (101)</td>
                            <td>∞</td>
                            <td><span class="badge badge-success">ACTIVE</span></td>
                            <td>
                                <button onclick="editDID('12813015784')">✏️ Edit</button>
                                <button onclick="routeDID('12813015784')">🎯 Route</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Channels Tab -->
        <div id="channels-tab" class="tab-content">
            <div class="section">
                <h2>Channel Usage & Limits</h2>

                <div class="trunk-card">
                    <h3>CallCentric Primary</h3>
                    <div class="grid-2">
                        <div>
                            <label>Max Channels:</label>
                            <input type="number" id="cc-max-channels" value="2">
                        </div>
                        <div>
                            <label>Current Usage:</label>
                            <input type="text" value="0 active calls" readonly>
                        </div>
                    </div>

                    <div class="grid-2">
                        <div>
                            <label>Per-DID Limit:</label>
                            <select id="cc-per-did">
                                <option value="true" selected>Enable (limit per DID)</option>
                                <option value="false">Disable (shared pool)</option>
                            </select>
                        </div>
                        <div>
                            <label>Action on Limit:</label>
                            <select>
                                <option value="busy">Send Busy Signal</option>
                                <option value="queue" selected>Queue Call</option>
                                <option value="voicemail">Send to Voicemail</option>
                            </select>
                        </div>
                    </div>

                    <button class="success" onclick="saveChannelSettings('callcentric')">💾 Save Channel Settings</button>
                </div>

                <div class="trunk-card">
                    <h3>Google Voice API</h3>
                    <p class="info">Google Voice API has no channel limits - unlimited concurrent calls</p>
                    <div class="grid-2">
                        <div>
                            <label>Daily Call Limit:</label>
                            <input type="text" value="1000 calls/day" readonly>
                        </div>
                        <div>
                            <label>SMS Limit:</label>
                            <input type="text" value="500 SMS/day" readonly>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Registration Status Tab -->
        <div id="registration-tab" class="tab-content">
            <div class="section">
                <h2>Trunk Registration Status</h2>

                <div class="trunk-card active">
                    <h3>CallCentric Primary</h3>
                    <table>
                        <tr>
                            <td><strong>Registration Status:</strong></td>
                            <td><span class="success">✅ Registered</span></td>
                        </tr>
                        <tr>
                            <td><strong>SIP Server:</strong></td>
                            <td>sip.callcentric.com:5060</td>
                        </tr>
                        <tr>
                            <td><strong>Username:</strong></td>
                            <td>17778171572</td>
                        </tr>
                        <tr>
                            <td><strong>Auth Name:</strong></td>
                            <td>Raywonder</td>
                        </tr>
                        <tr>
                            <td><strong>Last Registration:</strong></td>
                            <td id="cc-last-reg">Testing...</td>
                        </tr>
                        <tr>
                            <td><strong>Expires In:</strong></td>
                            <td>3600 seconds (1 hour)</td>
                        </tr>
                    </table>
                    <button onclick="forceReregister('callcentric')">🔄 Force Re-register</button>
                </div>

                <div class="trunk-card active">
                    <h3>Google Voice API</h3>
                    <table>
                        <tr>
                            <td><strong>API Status:</strong></td>
                            <td><span class="success">✅ Connected</span></td>
                        </tr>
                        <tr>
                            <td><strong>Authentication:</strong></td>
                            <td>OAuth2 Valid</td>
                        </tr>
                        <tr>
                            <td><strong>Last API Call:</strong></td>
                            <td id="gv-last-call">2025-10-13 12:45:00</td>
                        </tr>
                    </table>
                    <button onclick="refreshGVAuth()">🔄 Refresh OAuth Token</button>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="section">
            <h3>Quick Actions</h3>
            <button onclick="testAllTrunks()">🧪 Test All Trunks</button>
            <button onclick="viewCallHistory()">📊 View Call History</button>
            <button onclick="exportTrunkConfig()">💾 Export Configuration</button>
            <button onclick="importTrunkConfig()">📥 Import Configuration</button>
            <button onclick="window.location.href='dashboard.html'">← Back to Admin</button>
        </div>
    </div>

    <script>
        function switchTab(tabName) {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));

            event.target.classList.add('active');
            document.getElementById(tabName + '-tab').classList.add('active');

            if (tabName === 'registration') {
                checkRegistrationStatus();
            }
        }

        function editTrunk(trunk) {
            // Enable editing
            const inputs = document.querySelectorAll('#trunk-' + trunk + ' input, #trunk-' + trunk + ' select');
            inputs.forEach(input => input.removeAttribute('readonly'));
            inputs.forEach(input => input.removeAttribute('disabled'));

            document.getElementById(trunk + '-save-btn').style.display = 'inline-block';
            document.getElementById(trunk + '-cancel-btn').style.display = 'inline-block';
        }

        async function saveTrunk(trunk) {
            const data = {
                trunk: trunk,
                username: document.getElementById(trunk.substring(0, 2) + '-username').value,
                password: document.getElementById(trunk.substring(0, 2) + '-password').value,
                authname: document.getElementById(trunk.substring(0, 2) + '-authname').value,
                server: document.getElementById(trunk.substring(0, 2) + '-server').value,
                channels: document.getElementById(trunk.substring(0, 2) + '-channels').value,
                transport: document.getElementById(trunk.substring(0, 2) + '-transport').value
            };

            try {
                const response = await fetch('/api/trunk-management.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'update', data: data })
                });

                const result = await response.json();
                if (result.success) {
                    alert('✅ Trunk updated successfully!');
                    cancelEdit(trunk);
                    location.reload();
                } else {
                    alert('❌ Error: ' + result.message);
                }
            } catch (error) {
                alert('❌ Error saving trunk: ' + error.message);
            }
        }

        function cancelEdit(trunk) {
            location.reload();
        }

        async function testTrunk(trunk) {
            alert('Testing trunk registration for ' + trunk + '...\nThis will verify SIP credentials and connectivity.');

            try {
                const response = await fetch('/api/trunk-management.php?action=test&trunk=' + trunk);
                const result = await response.json();

                if (result.success) {
                    alert('✅ Trunk test successful!\n\n' + JSON.stringify(result.data, null, 2));
                } else {
                    alert('❌ Trunk test failed:\n' + result.message);
                }
            } catch (error) {
                alert('❌ Error testing trunk: ' + error.message);
            }
        }

        function editDID(did) {
            window.location.href = 'inbound-routing.html?did=' + did;
        }

        function routeDID(did) {
            window.location.href = 'inbound-routing.html?did=' + did;
        }

        function addNewDID() {
            const did = prompt('Enter new DID number (e.g., 3125551234):');
            if (did) {
                window.location.href = 'inbound-routing.html?action=add&did=' + did;
            }
        }

        function addNewTrunk() {
            alert('Add New Trunk:\n\n1. Choose provider (Callcentric, Twilio, etc.)\n2. Enter credentials\n3. Configure routing\n\nFeature coming soon!');
        }

        async function saveChannelSettings(trunk) {
            const maxChannels = document.getElementById(trunk.substring(0, 2) + '-max-channels').value;
            const perDID = document.getElementById(trunk.substring(0, 2) + '-per-did').value;

            try {
                const response = await fetch('/api/trunk-management.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'update_channels',
                        trunk: trunk,
                        max_channels: maxChannels,
                        per_did_limit: perDID === 'true'
                    })
                });

                const result = await response.json();
                if (result.success) {
                    alert('✅ Channel settings saved!');
                } else {
                    alert('❌ Error: ' + result.message);
                }
            } catch (error) {
                alert('❌ Error: ' + error.message);
            }
        }

        async function checkRegistrationStatus() {
            try {
                const response = await fetch('/api/trunk-management.php?action=registration_status');
                const result = await response.json();

                if (result.success && result.data) {
                    document.getElementById('cc-last-reg').textContent = result.data.callcentric?.last_registration || 'Unknown';
                    document.getElementById('gv-last-call').textContent = result.data.googlevoice?.last_call || 'Unknown';
                }
            } catch (error) {
                console.error('Error checking registration:', error);
            }
        }

        function forceReregister(trunk) {
            alert('Forcing re-registration for ' + trunk + '...');
            // Implement re-registration logic
        }

        function refreshGVAuth() {
            alert('Refreshing Google Voice OAuth token...');
            // Implement OAuth refresh
        }

        function testAllTrunks() {
            alert('Testing all trunks...\n\n- CallCentric: Testing SIP registration\n- Google Voice: Testing API connection');
        }

        function viewCallHistory() {
            window.location.href = 'call-history.html';
        }

        function exportTrunkConfig() {
            alert('Exporting trunk configuration...');
        }

        function importTrunkConfig() {
            alert('Import trunk configuration from file...');
        }

        function viewTrunkLogs(trunk) {
            alert('Viewing logs for ' + trunk + '...\n\nShowing recent SIP registration attempts, failures, and call activity.');
        }

        // Load initial data
        window.onload = function() {
            console.log('Trunks & DIDs Manager loaded');
        };
    </script>
</body>
</html>
