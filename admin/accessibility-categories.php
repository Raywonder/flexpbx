<?php
require_once __DIR__ . '/admin_auth_check.php';
/**
 * FlexPBX Accessibility Categories Management
 * Admin interface for managing accessibility categories, features, and user assignments
 */

session_start();

// Check authentication (simplified for demo)
$is_authenticated = isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true;
$username = $_SESSION['username'] ?? 'admin';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accessibility Categories - FlexPBX</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
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
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .header h1 {
            color: #2c3e50;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .header p {
            color: #666;
        }

        .tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .tab-button {
            padding: 1rem 2rem;
            background: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .tab-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .tab-button.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .tab-content {
            display: none;
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .tab-content.active {
            display: block;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .section-header h2 {
            color: #2c3e50;
        }

        .btn {
            padding: 0.8rem 1.5rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }

        .btn-secondary {
            background: #6c757d;
        }

        .btn-success {
            background: #28a745;
        }

        .btn-danger {
            background: #dc3545;
        }

        .btn-small {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }

        .card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            border-left: 4px solid #667eea;
        }

        .card h3 {
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }

        .card p {
            color: #666;
            margin-bottom: 0.5rem;
        }

        .badge {
            display: inline-block;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            margin: 0.2rem;
        }

        .badge-wcag {
            background: #28a745;
            color: white;
        }

        .badge-active {
            background: #007bff;
            color: white;
        }

        .badge-inactive {
            background: #6c757d;
            color: white;
        }

        .badge-pending {
            background: #ffc107;
            color: #333;
        }

        .badge-critical {
            background: #dc3545;
            color: white;
        }

        .badge-high {
            background: #fd7e14;
            color: white;
        }

        .badge-medium {
            background: #17a2b8;
            color: white;
        }

        .badge-low {
            background: #6c757d;
            color: white;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }

        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #2c3e50;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
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
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .modal-header h3 {
            color: #2c3e50;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #6c757d;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 8px;
            padding: 1.5rem;
            text-align: center;
        }

        .stat-card h4 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .stat-card p {
            opacity: 0.9;
        }

        .feature-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .feature-item {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 1rem;
            transition: all 0.3s ease;
        }

        .feature-item:hover {
            border-color: #667eea;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
        }

        .feature-item h4 {
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }

        .feature-item p {
            color: #666;
            font-size: 0.9rem;
        }

        .checkbox-group {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 0.5rem;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .checkbox-item input[type="checkbox"] {
            width: auto;
        }

        .loading {
            text-align: center;
            padding: 2rem;
            color: #666;
        }

        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        @media (max-width: 768px) {
            .header h1 {
                font-size: 1.5rem;
            }

            .tabs {
                flex-direction: column;
            }

            .tab-button {
                width: 100%;
            }

            .section-header {
                flex-direction: column;
                align-items: flex-start;
            }

            table {
                font-size: 0.9rem;
            }

            th, td {
                padding: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>
                <span role="img" aria-label="Accessibility">♿</span>
                Accessibility Categories Management
            </h1>
            <p>Manage accessibility features, categories, and user assignments for WCAG compliance</p>
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <button class="tab-button active" onclick="showTab('categories')">Categories</button>
            <button class="tab-button" onclick="showTab('features')">Features</button>
            <button class="tab-button" onclick="showTab('assignments')">Assignments</button>
            <button class="tab-button" onclick="showTab('requests')">Requests</button>
            <button class="tab-button" onclick="showTab('compliance')">Compliance</button>
            <button class="tab-button" onclick="showTab('settings')">Settings</button>
        </div>

        <!-- Categories Tab -->
        <div id="tab-categories" class="tab-content active">
            <div class="section-header">
                <h2>Accessibility Categories</h2>
                <button class="btn" onclick="showCreateCategoryModal()">
                    + Add Category
                </button>
            </div>

            <div id="categories-loading" class="loading">
                <div class="spinner"></div>
                <p>Loading categories...</p>
            </div>

            <div id="categories-list" style="display: none;"></div>
        </div>

        <!-- Features Tab -->
        <div id="tab-features" class="tab-content">
            <div class="section-header">
                <h2>Accessibility Features</h2>
                <button class="btn" onclick="showCreateFeatureModal()">
                    + Add Feature
                </button>
            </div>

            <div class="form-group">
                <label for="filter-category">Filter by Category:</label>
                <select id="filter-category" onchange="loadFeatures()">
                    <option value="">All Categories</option>
                </select>
            </div>

            <div id="features-loading" class="loading">
                <div class="spinner"></div>
                <p>Loading features...</p>
            </div>

            <div id="features-list" style="display: none;"></div>
        </div>

        <!-- Assignments Tab -->
        <div id="tab-assignments" class="tab-content">
            <div class="section-header">
                <h2>User Accessibility Assignments</h2>
                <button class="btn" onclick="showCreateAssignmentModal()">
                    + New Assignment
                </button>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="filter-user">Filter by User ID:</label>
                    <input type="text" id="filter-user" placeholder="Enter user ID">
                </div>
                <div class="form-group">
                    <label for="filter-extension">Filter by Extension:</label>
                    <input type="text" id="filter-extension" placeholder="Enter extension">
                </div>
                <div class="form-group">
                    <label>&nbsp;</label>
                    <button class="btn" onclick="loadAssignments()">Apply Filters</button>
                </div>
            </div>

            <div id="assignments-loading" class="loading">
                <div class="spinner"></div>
                <p>Loading assignments...</p>
            </div>

            <div id="assignments-list" style="display: none;"></div>
        </div>

        <!-- Requests Tab -->
        <div id="tab-requests" class="tab-content">
            <div class="section-header">
                <h2>Accessibility Requests</h2>
            </div>

            <div class="form-group">
                <label for="filter-status">Filter by Status:</label>
                <select id="filter-status" onchange="loadRequests()">
                    <option value="">All Requests</option>
                    <option value="pending" selected>Pending</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                    <option value="implemented">Implemented</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>

            <div id="requests-loading" class="loading">
                <div class="spinner"></div>
                <p>Loading requests...</p>
            </div>

            <div id="requests-list" style="display: none;"></div>
        </div>

        <!-- Compliance Tab -->
        <div id="tab-compliance" class="tab-content">
            <div class="section-header">
                <h2>Compliance Dashboard</h2>
                <button class="btn" onclick="generateComplianceReport()">
                    Generate Report
                </button>
            </div>

            <div id="compliance-stats" class="stats-grid"></div>
            <div id="compliance-summary"></div>
        </div>

        <!-- Settings Tab -->
        <div id="tab-settings" class="tab-content">
            <div class="section-header">
                <h2>Global Accessibility Settings</h2>
            </div>

            <div id="settings-loading" class="loading">
                <div class="spinner"></div>
                <p>Loading settings...</p>
            </div>

            <div id="settings-list" style="display: none;"></div>
        </div>
    </div>

    <!-- Create Category Modal -->
    <div id="create-category-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Category</h3>
                <button class="close-btn" onclick="closeModal('create-category-modal')">&times;</button>
            </div>
            <form id="create-category-form">
                <div class="form-group">
                    <label for="category-id">Category ID *</label>
                    <input type="text" id="category-id" required placeholder="e.g., visual_impairment">
                </div>
                <div class="form-group">
                    <label for="category-name">Category Name *</label>
                    <input type="text" id="category-name" required placeholder="e.g., Visual Impairment Support">
                </div>
                <div class="form-group">
                    <label for="category-description">Description</label>
                    <textarea id="category-description" placeholder="Describe the accessibility category..."></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="category-icon">Icon</label>
                        <input type="text" id="category-icon" value="accessibility" placeholder="Material icon name">
                    </div>
                    <div class="form-group">
                        <label for="category-compliance">Compliance Level</label>
                        <select id="category-compliance">
                            <option value="WCAG_2.1_A">WCAG 2.1 Level A</option>
                            <option value="WCAG_2.1_AA" selected>WCAG 2.1 Level AA</option>
                            <option value="WCAG_2.1_AAA">WCAG 2.1 Level AAA</option>
                        </select>
                    </div>
                </div>
                <div class="actions">
                    <button type="submit" class="btn">Create Category</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('create-category-modal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Create Assignment Modal -->
    <div id="create-assignment-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Create Accessibility Assignment</h3>
                <button class="close-btn" onclick="closeModal('create-assignment-modal')">&times;</button>
            </div>
            <form id="create-assignment-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="assign-user-id">User ID</label>
                        <input type="number" id="assign-user-id" placeholder="Optional">
                    </div>
                    <div class="form-group">
                        <label for="assign-extension">Extension Number</label>
                        <input type="text" id="assign-extension" placeholder="Optional">
                    </div>
                </div>
                <div class="form-group">
                    <label for="assign-category">Category *</label>
                    <select id="assign-category" required></select>
                </div>
                <div class="form-group">
                    <label for="assign-reason">Reason for Assignment</label>
                    <textarea id="assign-reason" placeholder="Why is this accessibility feature needed?"></textarea>
                </div>
                <div class="form-group">
                    <label for="assign-priority">Priority</label>
                    <select id="assign-priority">
                        <option value="0">Normal</option>
                        <option value="1">High</option>
                        <option value="2">Critical</option>
                    </select>
                </div>
                <div class="actions">
                    <button type="submit" class="btn">Create Assignment</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal('create-assignment-modal')">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // API Configuration
        const API_BASE = '../api/accessibility-categories.php';

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            loadCategories();
            loadRequests();
            loadComplianceSummary();
            loadSettings();
        });

        // Tab Management
        function showTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });

            // Deactivate all buttons
            document.querySelectorAll('.tab-button').forEach(btn => {
                btn.classList.remove('active');
            });

            // Show selected tab
            document.getElementById('tab-' + tabName).classList.add('active');
            event.target.classList.add('active');

            // Load data for the tab
            switch(tabName) {
                case 'categories':
                    loadCategories();
                    break;
                case 'features':
                    loadFeatures();
                    break;
                case 'assignments':
                    loadAssignments();
                    break;
                case 'requests':
                    loadRequests();
                    break;
                case 'compliance':
                    loadComplianceSummary();
                    break;
                case 'settings':
                    loadSettings();
                    break;
            }
        }

        // Load Categories
        async function loadCategories() {
            document.getElementById('categories-loading').style.display = 'block';
            document.getElementById('categories-list').style.display = 'none';

            try {
                const response = await fetch(API_BASE + '?path=categories');
                const data = await response.json();

                if (data.success) {
                    displayCategories(data.categories);
                    populateCategorySelects(data.categories);
                }
            } catch (error) {
                console.error('Error loading categories:', error);
                showError('Failed to load categories');
            } finally {
                document.getElementById('categories-loading').style.display = 'none';
                document.getElementById('categories-list').style.display = 'block';
            }
        }

        // Display Categories
        function displayCategories(categories) {
            const container = document.getElementById('categories-list');
            container.innerHTML = '';

            categories.forEach(cat => {
                const card = document.createElement('div');
                card.className = 'card';
                card.innerHTML = `
                    <div style="display: flex; justify-content: space-between; align-items: start;">
                        <div style="flex: 1;">
                            <h3>${cat.category_name}</h3>
                            <p>${cat.description || 'No description'}</p>
                            <div style="margin-top: 0.5rem;">
                                <span class="badge badge-wcag">${cat.compliance_level}</span>
                                <span class="badge ${cat.is_active ? 'badge-active' : 'badge-inactive'}">
                                    ${cat.is_active ? 'Active' : 'Inactive'}
                                </span>
                            </div>
                            <p style="margin-top: 0.5rem; font-size: 0.9rem;">
                                <strong>Stats:</strong> ${cat.assigned_users} users, ${cat.assigned_extensions} extensions,
                                ${cat.enabled_features}/${cat.total_features} features enabled
                            </p>
                        </div>
                        <div class="actions">
                            <button class="btn btn-small" onclick="viewCategoryDetails('${cat.category_id}')">View</button>
                            <button class="btn btn-small btn-danger" onclick="deleteCategory('${cat.category_id}')">Delete</button>
                        </div>
                    </div>
                `;
                container.appendChild(card);
            });
        }

        // Populate category selects
        function populateCategorySelects(categories) {
            const selects = [
                document.getElementById('filter-category'),
                document.getElementById('assign-category')
            ];

            selects.forEach(select => {
                if (!select) return;

                // Clear existing options (except first one for filter)
                if (select.id === 'filter-category') {
                    select.innerHTML = '<option value="">All Categories</option>';
                } else {
                    select.innerHTML = '<option value="">Select Category</option>';
                }

                categories.forEach(cat => {
                    const option = document.createElement('option');
                    option.value = cat.category_id;
                    option.textContent = cat.category_name;
                    select.appendChild(option);
                });
            });
        }

        // Load Features
        async function loadFeatures() {
            const categoryFilter = document.getElementById('filter-category')?.value || '';
            document.getElementById('features-loading').style.display = 'block';
            document.getElementById('features-list').style.display = 'none';

            try {
                let url = API_BASE + '?path=features';
                if (categoryFilter) {
                    url += '&category_id=' + categoryFilter;
                }

                const response = await fetch(url);
                const data = await response.json();

                if (data.success) {
                    displayFeatures(data.features);
                }
            } catch (error) {
                console.error('Error loading features:', error);
                showError('Failed to load features');
            } finally {
                document.getElementById('features-loading').style.display = 'none';
                document.getElementById('features-list').style.display = 'block';
            }
        }

        // Display Features
        function displayFeatures(features) {
            const container = document.getElementById('features-list');
            container.innerHTML = '<div class="feature-list"></div>';
            const grid = container.querySelector('.feature-list');

            features.forEach(feat => {
                const item = document.createElement('div');
                item.className = 'feature-item';
                item.innerHTML = `
                    <h4>${feat.feature_name}</h4>
                    <p>${feat.description || 'No description'}</p>
                    <p style="font-size: 0.85rem; color: #999; margin-top: 0.5rem;">
                        <strong>Category:</strong> ${feat.category_name}<br>
                        <strong>Code:</strong> ${feat.feature_code}
                    </p>
                    <div style="margin-top: 0.5rem;">
                        <span class="badge ${feat.is_enabled ? 'badge-active' : 'badge-inactive'}">
                            ${feat.is_enabled ? 'Enabled' : 'Disabled'}
                        </span>
                    </div>
                `;
                grid.appendChild(item);
            });

            if (features.length === 0) {
                container.innerHTML = '<p style="text-align: center; color: #666;">No features found</p>';
            }
        }

        // Load Assignments
        async function loadAssignments() {
            const userId = document.getElementById('filter-user')?.value || '';
            const extension = document.getElementById('filter-extension')?.value || '';

            document.getElementById('assignments-loading').style.display = 'block';
            document.getElementById('assignments-list').style.display = 'none';

            try {
                let url = API_BASE + '?path=assignments';
                const params = [];
                if (userId) params.push('user_id=' + userId);
                if (extension) params.push('extension=' + extension);
                if (params.length > 0) url += '&' + params.join('&');

                const response = await fetch(url);
                const data = await response.json();

                if (data.success) {
                    displayAssignments(data.assignments);
                }
            } catch (error) {
                console.error('Error loading assignments:', error);
                showError('Failed to load assignments');
            } finally {
                document.getElementById('assignments-loading').style.display = 'none';
                document.getElementById('assignments-list').style.display = 'block';
            }
        }

        // Display Assignments
        function displayAssignments(assignments) {
            const container = document.getElementById('assignments-list');

            if (assignments.length === 0) {
                container.innerHTML = '<p style="text-align: center; color: #666;">No assignments found</p>';
                return;
            }

            let html = '<table><thead><tr>';
            html += '<th>User/Extension</th>';
            html += '<th>Category</th>';
            html += '<th>Priority</th>';
            html += '<th>Assigned By</th>';
            html += '<th>Date</th>';
            html += '<th>Actions</th>';
            html += '</tr></thead><tbody>';

            assignments.forEach(assign => {
                const userInfo = assign.user_id ? `User #${assign.user_id}` :
                                assign.extension_number ? `Ext ${assign.extension_number}` : 'Unknown';
                const priority = assign.priority == 2 ? 'Critical' : assign.priority == 1 ? 'High' : 'Normal';
                const priorityClass = assign.priority == 2 ? 'badge-critical' : assign.priority == 1 ? 'badge-high' : 'badge-low';

                html += '<tr>';
                html += `<td>${userInfo}</td>`;
                html += `<td>${assign.category_name}</td>`;
                html += `<td><span class="badge ${priorityClass}">${priority}</span></td>`;
                html += `<td>${assign.assigned_by || 'System'}</td>`;
                html += `<td>${new Date(assign.created_at).toLocaleDateString()}</td>`;
                html += `<td><button class="btn btn-small btn-danger" onclick="deleteAssignment(${assign.assignment_id})">Remove</button></td>`;
                html += '</tr>';
            });

            html += '</tbody></table>';
            container.innerHTML = html;
        }

        // Load Requests
        async function loadRequests() {
            const status = document.getElementById('filter-status')?.value || '';
            document.getElementById('requests-loading').style.display = 'block';
            document.getElementById('requests-list').style.display = 'none';

            try {
                let url = API_BASE + '?path=requests';
                if (status) url += '&status=' + status;

                const response = await fetch(url);
                const data = await response.json();

                if (data.success) {
                    displayRequests(data.requests);
                }
            } catch (error) {
                console.error('Error loading requests:', error);
                showError('Failed to load requests');
            } finally {
                document.getElementById('requests-loading').style.display = 'none';
                document.getElementById('requests-list').style.display = 'block';
            }
        }

        // Display Requests
        function displayRequests(requests) {
            const container = document.getElementById('requests-list');

            if (requests.length === 0) {
                container.innerHTML = '<p style="text-align: center; color: #666;">No requests found</p>';
                return;
            }

            container.innerHTML = '';

            requests.forEach(req => {
                const categories = Array.isArray(req.requested_categories) ? req.requested_categories :
                                  JSON.parse(req.requested_categories || '[]');

                const urgencyClass = {
                    'critical': 'badge-critical',
                    'high': 'badge-high',
                    'medium': 'badge-medium',
                    'low': 'badge-low'
                }[req.urgency] || 'badge-low';

                const card = document.createElement('div');
                card.className = 'card';
                card.innerHTML = `
                    <div style="display: flex; justify-content: space-between; align-items: start;">
                        <div style="flex: 1;">
                            <h3>Request #${req.request_id}</h3>
                            <p><strong>User/Extension:</strong> ${req.user_id ? 'User #' + req.user_id : req.extension_number ? 'Ext ' + req.extension_number : 'Unknown'}</p>
                            <p><strong>Email:</strong> ${req.email || 'Not provided'}</p>
                            <p><strong>Categories:</strong> ${categories.join(', ')}</p>
                            ${req.special_requirements ? `<p><strong>Special Requirements:</strong> ${req.special_requirements}</p>` : ''}
                            <div style="margin-top: 0.5rem;">
                                <span class="badge ${urgencyClass}">${req.urgency.toUpperCase()}</span>
                                <span class="badge badge-pending">${req.status.toUpperCase()}</span>
                                ${req.pending_hours ? `<span class="badge badge-low">${req.pending_hours}h pending</span>` : ''}
                            </div>
                        </div>
                        ${req.status === 'pending' ? `
                        <div class="actions" style="flex-direction: column;">
                            <button class="btn btn-small btn-success" onclick="approveRequest(${req.request_id})">Approve</button>
                            <button class="btn btn-small btn-danger" onclick="rejectRequest(${req.request_id})">Reject</button>
                        </div>
                        ` : ''}
                    </div>
                `;
                container.appendChild(card);
            });
        }

        // Load Compliance Summary
        async function loadComplianceSummary() {
            try {
                const response = await fetch(API_BASE + '?path=compliance-summary');
                const data = await response.json();

                if (data.success) {
                    displayComplianceSummary(data.summary);
                }
            } catch (error) {
                console.error('Error loading compliance summary:', error);
            }
        }

        // Display Compliance Summary
        function displayComplianceSummary(summary) {
            const statsContainer = document.getElementById('compliance-stats');
            const summaryContainer = document.getElementById('compliance-summary');

            // Stats
            const totalUsers = summary.reduce((sum, cat) => sum + parseInt(cat.users_with_category), 0);
            const totalExtensions = summary.reduce((sum, cat) => sum + parseInt(cat.extensions_with_category), 0);
            const totalCategories = summary.filter(cat => cat.is_active).length;

            statsContainer.innerHTML = `
                <div class="stat-card">
                    <h4>${totalCategories}</h4>
                    <p>Active Categories</p>
                </div>
                <div class="stat-card">
                    <h4>${totalUsers}</h4>
                    <p>Users with Accessibility</p>
                </div>
                <div class="stat-card">
                    <h4>${totalExtensions}</h4>
                    <p>Extensions with Accessibility</p>
                </div>
            `;

            // Summary table
            let html = '<h3 style="margin-top: 2rem; margin-bottom: 1rem;">Category Breakdown</h3>';
            html += '<table><thead><tr>';
            html += '<th>Category</th>';
            html += '<th>Compliance</th>';
            html += '<th>Users</th>';
            html += '<th>Extensions</th>';
            html += '<th>Features</th>';
            html += '<th>Status</th>';
            html += '</tr></thead><tbody>';

            summary.forEach(cat => {
                html += '<tr>';
                html += `<td>${cat.category_name}</td>`;
                html += `<td><span class="badge badge-wcag">${cat.compliance_level}</span></td>`;
                html += `<td>${cat.users_with_category}</td>`;
                html += `<td>${cat.extensions_with_category}</td>`;
                html += `<td>${cat.enabled_features}/${cat.total_features}</td>`;
                html += `<td><span class="badge ${cat.is_active ? 'badge-active' : 'badge-inactive'}">${cat.is_active ? 'Active' : 'Inactive'}</span></td>`;
                html += '</tr>';
            });

            html += '</tbody></table>';
            summaryContainer.innerHTML = html;
        }

        // Load Settings
        async function loadSettings() {
            document.getElementById('settings-loading').style.display = 'block';
            document.getElementById('settings-list').style.display = 'none';

            try {
                const response = await fetch(API_BASE + '?path=settings');
                const data = await response.json();

                if (data.success) {
                    displaySettings(data.settings);
                }
            } catch (error) {
                console.error('Error loading settings:', error);
            } finally {
                document.getElementById('settings-loading').style.display = 'none';
                document.getElementById('settings-list').style.display = 'block';
            }
        }

        // Display Settings
        function displaySettings(settings) {
            const container = document.getElementById('settings-list');
            container.innerHTML = '';

            settings.forEach(setting => {
                const card = document.createElement('div');
                card.className = 'card';
                card.innerHTML = `
                    <h3>${setting.setting_key.replace(/_/g, ' ').toUpperCase()}</h3>
                    <p>${setting.description || 'No description'}</p>
                    <pre style="background: #f8f9fa; padding: 1rem; border-radius: 4px; overflow-x: auto; margin-top: 0.5rem;">${JSON.stringify(setting.setting_value, null, 2)}</pre>
                    <p style="font-size: 0.85rem; color: #999; margin-top: 0.5rem;">
                        Last updated: ${new Date(setting.updated_at).toLocaleString()}
                        ${setting.updated_by ? ` by ${setting.updated_by}` : ''}
                    </p>
                `;
                container.appendChild(card);
            });
        }

        // Modal Management
        function showCreateCategoryModal() {
            document.getElementById('create-category-modal').classList.add('active');
        }

        function showCreateAssignmentModal() {
            document.getElementById('create-assignment-modal').classList.add('active');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        // Form Handlers
        document.getElementById('create-category-form')?.addEventListener('submit', async function(e) {
            e.preventDefault();

            const data = {
                category_id: document.getElementById('category-id').value,
                category_name: document.getElementById('category-name').value,
                description: document.getElementById('category-description').value,
                icon: document.getElementById('category-icon').value,
                compliance_level: document.getElementById('category-compliance').value
            };

            try {
                const response = await fetch(API_BASE + '?path=category', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.success) {
                    showSuccess('Category created successfully');
                    closeModal('create-category-modal');
                    loadCategories();
                    e.target.reset();
                } else {
                    showError(result.error || 'Failed to create category');
                }
            } catch (error) {
                showError('Network error');
            }
        });

        document.getElementById('create-assignment-form')?.addEventListener('submit', async function(e) {
            e.preventDefault();

            const data = {
                user_id: document.getElementById('assign-user-id').value || null,
                extension_number: document.getElementById('assign-extension').value || null,
                category_id: document.getElementById('assign-category').value,
                assignment_reason: document.getElementById('assign-reason').value,
                priority: parseInt(document.getElementById('assign-priority').value)
            };

            if (!data.user_id && !data.extension_number) {
                showError('Please provide either User ID or Extension Number');
                return;
            }

            try {
                const response = await fetch(API_BASE + '?path=assignment', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.success) {
                    showSuccess('Assignment created successfully');
                    closeModal('create-assignment-modal');
                    loadAssignments();
                    e.target.reset();
                } else {
                    showError(result.error || 'Failed to create assignment');
                }
            } catch (error) {
                showError('Network error');
            }
        });

        // Action Handlers
        async function deleteCategory(categoryId) {
            if (!confirm('Are you sure you want to delete this category? This will remove all related assignments.')) {
                return;
            }

            try {
                const response = await fetch(API_BASE + `?path=category&id=${categoryId}`, {
                    method: 'DELETE'
                });

                const result = await response.json();

                if (result.success) {
                    showSuccess('Category deleted successfully');
                    loadCategories();
                } else {
                    showError(result.error || 'Failed to delete category');
                }
            } catch (error) {
                showError('Network error');
            }
        }

        async function deleteAssignment(assignmentId) {
            if (!confirm('Are you sure you want to remove this assignment?')) {
                return;
            }

            try {
                const response = await fetch(API_BASE + `?path=assignment&id=${assignmentId}`, {
                    method: 'DELETE'
                });

                const result = await response.json();

                if (result.success) {
                    showSuccess('Assignment removed successfully');
                    loadAssignments();
                } else {
                    showError(result.error || 'Failed to remove assignment');
                }
            } catch (error) {
                showError('Network error');
            }
        }

        async function approveRequest(requestId) {
            const notes = prompt('Review notes (optional):');
            if (notes === null) return; // Cancelled

            try {
                const response = await fetch(API_BASE + `?path=approve-request&id=${requestId}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ review_notes: notes })
                });

                const result = await response.json();

                if (result.success) {
                    showSuccess('Request approved and assignments created');
                    loadRequests();
                    loadAssignments();
                } else {
                    showError(result.error || 'Failed to approve request');
                }
            } catch (error) {
                showError('Network error');
            }
        }

        async function rejectRequest(requestId) {
            const notes = prompt('Rejection reason:');
            if (!notes) return;

            try {
                const response = await fetch(API_BASE + `?path=reject-request&id=${requestId}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ review_notes: notes })
                });

                const result = await response.json();

                if (result.success) {
                    showSuccess('Request rejected');
                    loadRequests();
                } else {
                    showError(result.error || 'Failed to reject request');
                }
            } catch (error) {
                showError('Network error');
            }
        }

        async function generateComplianceReport() {
            try {
                const response = await fetch(API_BASE + '?path=compliance-report');
                const data = await response.json();

                if (data.success) {
                    const blob = new Blob([JSON.stringify(data.report, null, 2)], { type: 'application/json' });
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `accessibility-compliance-report-${new Date().toISOString().split('T')[0]}.json`;
                    a.click();
                    showSuccess('Report generated and downloaded');
                }
            } catch (error) {
                showError('Failed to generate report');
            }
        }

        // Notification Helpers
        function showSuccess(message) {
            const alert = document.createElement('div');
            alert.className = 'alert alert-success';
            alert.textContent = message;
            alert.style.position = 'fixed';
            alert.style.top = '20px';
            alert.style.right = '20px';
            alert.style.zIndex = '9999';
            document.body.appendChild(alert);
            setTimeout(() => alert.remove(), 3000);
        }

        function showError(message) {
            const alert = document.createElement('div');
            alert.className = 'alert alert-error';
            alert.textContent = message;
            alert.style.position = 'fixed';
            alert.style.top = '20px';
            alert.style.right = '20px';
            alert.style.zIndex = '9999';
            document.body.appendChild(alert);
            setTimeout(() => alert.remove(), 5000);
        }
    </script>
</body>
</html>
