<?php require_once __DIR__ . '/admin_auth_check.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Management - FlexPBX Admin</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            margin-bottom: 30px;
        }

        .header h1 {
            color: #333;
            font-size: 32px;
            margin-bottom: 10px;
        }

        .header .tagline {
            color: #666;
            font-size: 16px;
            font-style: italic;
        }

        .support-callout {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            margin-top: 20px;
            border-radius: 6px;
        }

        .support-callout .phone {
            font-size: 24px;
            font-weight: bold;
            color: #1976d2;
            margin: 10px 0;
        }

        .support-callout a {
            color: #1976d2;
            text-decoration: none;
        }

        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .tab-button {
            background: white;
            border: none;
            padding: 15px 30px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            color: #666;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }

        .tab-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        }

        .tab-button.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .tab-content {
            display: none;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .tab-content.active {
            display: block;
        }

        .section-title {
            font-size: 24px;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }

        .card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .stat-card h3 {
            font-size: 18px;
            margin-bottom: 10px;
            opacity: 0.9;
        }

        .stat-card .value {
            font-size: 36px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .gateway-card {
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 20px;
            transition: all 0.3s;
        }

        .gateway-card:hover {
            border-color: #667eea;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.2);
        }

        .gateway-card.enabled {
            border-color: #4caf50;
            background: #f1f8f4;
        }

        .gateway-card.disabled {
            opacity: 0.6;
        }

        .gateway-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .gateway-name {
            font-size: 20px;
            font-weight: 600;
            color: #333;
        }

        .status-badge {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-badge.enabled {
            background: #4caf50;
            color: white;
        }

        .status-badge.disabled {
            background: #f44336;
            color: white;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        .button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }

        .button-secondary {
            background: #6c757d;
        }

        .button-success {
            background: #4caf50;
        }

        .button-danger {
            background: #f44336;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        table thead {
            background: #f5f5f5;
        }

        table th,
        table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }

        table th {
            font-weight: 600;
            color: #333;
        }

        table tr:hover {
            background: #f9f9f9;
        }

        .transaction-status {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .transaction-status.completed {
            background: #4caf50;
            color: white;
        }

        .transaction-status.pending {
            background: #ff9800;
            color: white;
        }

        .transaction-status.failed {
            background: #f44336;
            color: white;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .alert-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .alert-info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }

        .hidden {
            display: none !important;
        }

        @media (max-width: 768px) {
            .card-grid {
                grid-template-columns: 1fr;
            }

            .tabs {
                flex-direction: column;
            }

            .tab-button {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>💳 Payment Management</h1>
            <p class="tagline">A system you can help build to be the best it can be. Accessible by default.</p>

            <div class="support-callout">
                <div style="font-weight: 600; color: #1976d2;">📞 Support Hotline</div>
                <div class="phone">
                    <a href="tel:+13023139555">(302) 313-9555</a>
                </div>
                <div style="font-size: 14px; color: #666;">
                    Call our support team for immediate assistance
                </div>
            </div>
        </div>

        <div class="tabs">
            <button class="tab-button active" onclick="switchTab(0)">Dashboard</button>
            <button class="tab-button" onclick="switchTab(1)">Transactions</button>
            <button class="tab-button" onclick="switchTab(2)">Gateway Settings</button>
            <button class="tab-button" onclick="switchTab(3)">Create Order</button>
        </div>

        <!-- Dashboard Tab -->
        <div class="tab-content active" id="tab-dashboard">
            <h2 class="section-title">Payment Overview</h2>

            <div id="stats-loading" class="alert alert-info">Loading statistics...</div>

            <div id="stats-grid" class="card-grid hidden">
                <div class="stat-card">
                    <h3>Total Transactions</h3>
                    <div class="value" id="stat-total">0</div>
                </div>
                <div class="stat-card">
                    <h3>Completed Payments</h3>
                    <div class="value" id="stat-completed">0</div>
                </div>
                <div class="stat-card">
                    <h3>Pending Payments</h3>
                    <div class="value" id="stat-pending">0</div>
                </div>
                <div class="stat-card">
                    <h3>Total Revenue</h3>
                    <div class="value" id="stat-revenue">$0.00</div>
                </div>
            </div>

            <h2 class="section-title" style="margin-top: 40px;">Active Payment Gateways</h2>
            <div id="active-gateways" class="card-grid"></div>
        </div>

        <!-- Transactions Tab -->
        <div class="tab-content" id="tab-transactions">
            <h2 class="section-title">Transaction History</h2>

            <div id="transactions-alert" class="hidden"></div>

            <div style="overflow-x: auto;">
                <table id="transactions-table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>License Type</th>
                            <th>Amount</th>
                            <th>Gateway</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="transactions-body">
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 40px; color: #999;">
                                Loading transactions...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Gateway Settings Tab -->
        <div class="tab-content" id="tab-settings">
            <h2 class="section-title">Payment Gateway Configuration</h2>

            <div id="settings-alert" class="hidden"></div>

            <div id="gateways-grid" class="card-grid"></div>
        </div>

        <!-- Create Order Tab -->
        <div class="tab-content" id="tab-create">
            <h2 class="section-title">Create New Order</h2>

            <div id="create-alert" class="hidden"></div>

            <form id="create-order-form" onsubmit="createOrder(event)">
                <div class="form-group">
                    <label for="license-type">License Type *</label>
                    <select id="license-type" name="license_type" required>
                        <option value="">Select License Type</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="customer-name">Customer Name *</label>
                    <input type="text" id="customer-name" name="customer_name" required>
                </div>

                <div class="form-group">
                    <label for="customer-email">Customer Email *</label>
                    <input type="email" id="customer-email" name="customer_email" required>
                </div>

                <div class="form-group">
                    <label for="installation-id">Installation ID (Optional)</label>
                    <input type="text" id="installation-id" name="installation_id">
                </div>

                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="button">Create Order</button>
                    <button type="reset" class="button button-secondary">Reset</button>
                </div>
            </form>

            <div id="order-result" class="hidden" style="margin-top: 30px;">
                <h3 class="section-title">Order Created Successfully</h3>
                <div style="background: #f5f5f5; padding: 20px; border-radius: 8px;">
                    <p><strong>Order ID:</strong> <span id="result-order-id"></span></p>
                    <p><strong>Total:</strong> $<span id="result-total"></span></p>
                    <p><strong>Status:</strong> <span id="result-status"></span></p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // API Base URL
        const API_BASE = '/api';

        // Switch between tabs
        function switchTab(index) {
            const buttons = document.querySelectorAll('.tab-button');
            const contents = document.querySelectorAll('.tab-content');

            buttons.forEach((btn, i) => {
                if (i === index) {
                    btn.classList.add('active');
                    contents[i].classList.add('active');
                } else {
                    btn.classList.remove('active');
                    contents[i].classList.remove('active');
                }
            });

            // Load data for specific tabs
            if (index === 0) {
                loadDashboard();
            } else if (index === 1) {
                loadTransactions();
            } else if (index === 2) {
                loadGatewaySettings();
            } else if (index === 3) {
                loadLicensePlans();
            }
        }

        // Load dashboard statistics
        async function loadDashboard() {
            try {
                // Load transactions
                const txResponse = await fetch(`${API_BASE}/payments.php?action=list_transactions`);
                const txData = await txResponse.json();

                if (txData.success) {
                    const transactions = txData.transactions;

                    // Calculate stats
                    const total = transactions.length;
                    const completed = transactions.filter(t => t.status === 'completed').length;
                    const pending = transactions.filter(t => t.status === 'pending' || t.status === 'processing').length;
                    const revenue = transactions
                        .filter(t => t.status === 'completed')
                        .reduce((sum, t) => sum + parseFloat(t.total || 0), 0);

                    document.getElementById('stat-total').textContent = total;
                    document.getElementById('stat-completed').textContent = completed;
                    document.getElementById('stat-pending').textContent = pending;
                    document.getElementById('stat-revenue').textContent = '$' + revenue.toFixed(2);

                    document.getElementById('stats-loading').classList.add('hidden');
                    document.getElementById('stats-grid').classList.remove('hidden');
                }

                // Load active gateways
                const gwResponse = await fetch(`${API_BASE}/payments.php?action=get_gateways`);
                const gwData = await gwResponse.json();

                if (gwData.success) {
                    const container = document.getElementById('active-gateways');
                    container.innerHTML = '';

                    if (Object.keys(gwData.gateways).length === 0) {
                        container.innerHTML = '<p style="grid-column: 1/-1; text-align: center; color: #999;">No payment gateways enabled</p>';
                    } else {
                        Object.entries(gwData.gateways).forEach(([key, gw]) => {
                            container.innerHTML += `
                                <div class="gateway-card enabled">
                                    <div class="gateway-header">
                                        <span class="gateway-name">${gw.name}</span>
                                        <span class="status-badge enabled">Active</span>
                                    </div>
                                    <p style="color: #666; margin-top: 10px;">Gateway: ${key}</p>
                                </div>
                            `;
                        });
                    }
                }
            } catch (error) {
                console.error('Error loading dashboard:', error);
                document.getElementById('stats-loading').textContent = 'Error loading statistics';
                document.getElementById('stats-loading').classList.remove('alert-info');
                document.getElementById('stats-loading').classList.add('alert-error');
            }
        }

        // Load transactions
        async function loadTransactions() {
            try {
                const response = await fetch(`${API_BASE}/payments.php?action=list_transactions`);
                const data = await response.json();

                const tbody = document.getElementById('transactions-body');

                if (data.success && data.transactions.length > 0) {
                    tbody.innerHTML = '';
                    data.transactions.forEach(tx => {
                        const date = new Date(tx.created_at * 1000).toLocaleString();
                        tbody.innerHTML += `
                            <tr>
                                <td>${tx.order_id}</td>
                                <td>${tx.customer_name || tx.customer_email}</td>
                                <td>${tx.license_name}</td>
                                <td>$${parseFloat(tx.total).toFixed(2)}</td>
                                <td>${tx.payment_gateway || 'N/A'}</td>
                                <td><span class="transaction-status ${tx.status}">${tx.status}</span></td>
                                <td>${date}</td>
                                <td>
                                    <button class="button" style="padding: 6px 12px; font-size: 12px;" onclick="viewTransaction('${tx.order_id}')">View</button>
                                </td>
                            </tr>
                        `;
                    });
                } else {
                    tbody.innerHTML = '<tr><td colspan="8" style="text-align: center; padding: 40px; color: #999;">No transactions found</td></tr>';
                }
            } catch (error) {
                console.error('Error loading transactions:', error);
            }
        }

        // View transaction details
        function viewTransaction(orderId) {
            alert('Transaction details for: ' + orderId + '\n\nDetailed view coming soon!');
        }

        // Load gateway settings
        async function loadGatewaySettings() {
            const container = document.getElementById('gateways-grid');
            container.innerHTML = '<p style="grid-column: 1/-1; text-align: center; color: #999;">Gateway configuration UI coming soon. Please edit payment_config.json directly for now.</p>';
        }

        // Load license plans
        async function loadLicensePlans() {
            try {
                const response = await fetch(`${API_BASE}/licensing.php?action=get_plans`);
                const data = await response.json();

                if (data.success) {
                    const select = document.getElementById('license-type');
                    select.innerHTML = '<option value="">Select License Type</option>';

                    Object.entries(data.plans).forEach(([key, plan]) => {
                        select.innerHTML += `<option value="${key}">${plan.name} - $${plan.price}</option>`;
                    });
                }
            } catch (error) {
                console.error('Error loading plans:', error);
            }
        }

        // Create order
        async function createOrder(event) {
            event.preventDefault();

            const form = event.target;
            const formData = new FormData(form);
            formData.append('action', 'create_order');

            const alertDiv = document.getElementById('create-alert');
            alertDiv.className = 'alert alert-info';
            alertDiv.textContent = 'Creating order...';
            alertDiv.classList.remove('hidden');

            try {
                const response = await fetch(`${API_BASE}/payments.php`, {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.success) {
                    alertDiv.className = 'alert alert-success';
                    alertDiv.textContent = 'Order created successfully!';

                    // Show result
                    document.getElementById('result-order-id').textContent = data.order.order_id;
                    document.getElementById('result-total').textContent = data.order.total.toFixed(2);
                    document.getElementById('result-status').textContent = data.order.status;
                    document.getElementById('order-result').classList.remove('hidden');

                    form.reset();
                } else {
                    alertDiv.className = 'alert alert-error';
                    alertDiv.textContent = 'Error: ' + data.error;
                }
            } catch (error) {
                alertDiv.className = 'alert alert-error';
                alertDiv.textContent = 'Error creating order: ' + error.message;
            }
        }

        // Load dashboard on page load
        document.addEventListener('DOMContentLoaded', () => {
            loadDashboard();
        });
    </script>
</body>
</html>
