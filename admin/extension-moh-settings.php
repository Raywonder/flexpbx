<?php
/**
 * FlexPBX Admin - Extension Music On Hold Settings
 * Manage MOH settings for all extensions
 */

session_start();
require_once __DIR__ . '/admin_auth_check.php';

// Admin authentication check
if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    header('Location: /admin/login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Extension MOH Settings - FlexPBX Admin</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f7fa;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        h1 {
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }

        .subtitle {
            color: #666;
            margin-bottom: 2rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: #f8f9fa;
        }

        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }

        th {
            font-weight: 600;
            color: #2c3e50;
        }

        select {
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            width: 100%;
            max-width: 300px;
        }

        .btn {
            padding: 0.5rem 1rem;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
        }

        .btn:hover {
            background: #5568d3;
        }

        .btn-sm {
            padding: 0.4rem 0.8rem;
            font-size: 0.85rem;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            display: none;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
        }

        .loading {
            text-align: center;
            padding: 2rem;
            color: #666;
        }

        .back-link {
            display: inline-block;
            color: #667eea;
            text-decoration: none;
            margin-bottom: 1rem;
            font-weight: 500;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        .status-badge {
            display: inline-block;
            padding: 0.3rem 0.6rem;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-online {
            background: #d4edda;
            color: #155724;
        }

        .status-offline {
            background: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" class="back-link">← Back to Admin Dashboard</a>

        <div class="card">
            <h1>🎵 Extension Music On Hold Settings</h1>
            <p class="subtitle">Manage hold music preferences for all extensions</p>

            <div id="alert" class="alert"></div>

            <div id="loading" class="loading">
                Loading extensions...
            </div>

            <table id="extensions-table" style="display: none;">
                <thead>
                    <tr>
                        <th>Extension</th>
                        <th>Name</th>
                        <th>Status</th>
                        <th>Music On Hold</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="extensions-body">
                </tbody>
            </table>
        </div>
    </div>

    <script>
        let mohClasses = [];
        let extensions = [];

        // Load MOH classes
        async function loadMOHClasses() {
            try {
                const response = await fetch('/api/extensions.php?path=moh-classes');
                const data = await response.json();

                if (data.success) {
                    mohClasses = data.moh_classes;
                }
            } catch (error) {
                console.error('Failed to load MOH classes:', error);
            }
        }

        // Load extensions
        async function loadExtensions() {
            try {
                const response = await fetch('/api/extensions.php?path=list');
                const data = await response.json();

                if (data.success) {
                    extensions = data.extensions;
                    displayExtensions();
                }
            } catch (error) {
                showAlert('Failed to load extensions', 'error');
            }
        }

        // Display extensions table
        function displayExtensions() {
            const tbody = document.getElementById('extensions-body');
            tbody.innerHTML = '';

            extensions.forEach(ext => {
                const row = document.createElement('tr');

                const statusClass = ext.status === 'Avail' ? 'status-online' : 'status-offline';
                const statusText = ext.status === 'Avail' ? 'Online' : 'Offline';

                // Create MOH select options
                let mohOptions = '';
                mohClasses.forEach(moh => {
                    const selected = moh.name === 'default' ? 'selected' : '';
                    mohOptions += `<option value="${moh.name}" ${selected}>${moh.display_name}</option>`;
                });

                row.innerHTML = `
                    <td><strong>${ext.extension}</strong></td>
                    <td>${ext.callerid || 'N/A'}</td>
                    <td><span class="status-badge ${statusClass}">${statusText}</span></td>
                    <td>
                        <select id="moh-${ext.extension}" data-extension="${ext.extension}">
                            ${mohOptions}
                        </select>
                    </td>
                    <td>
                        <button class="btn btn-sm" onclick="saveMOH('${ext.extension}')">
                            Save
                        </button>
                    </td>
                `;

                tbody.appendChild(row);
            });

            document.getElementById('loading').style.display = 'none';
            document.getElementById('extensions-table').style.display = 'table';
        }

        // Save MOH for extension
        async function saveMOH(extension) {
            const select = document.getElementById(`moh-${extension}`);
            const mohClass = select.value;

            try {
                const response = await fetch(`/api/extensions.php?path=update-moh&id=${extension}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        moh_class: mohClass
                    })
                });

                const data = await response.json();

                if (data.success) {
                    showAlert(`MOH updated for extension ${extension}`, 'success');
                } else {
                    showAlert(data.message || 'Failed to update MOH', 'error');
                }
            } catch (error) {
                showAlert('Error updating MOH: ' + error.message, 'error');
            }
        }

        // Show alert
        function showAlert(message, type) {
            const alert = document.getElementById('alert');
            alert.textContent = message;
            alert.className = `alert alert-${type}`;
            alert.style.display = 'block';

            setTimeout(() => {
                alert.style.display = 'none';
            }, 5000);
        }

        // Initialize
        async function init() {
            await loadMOHClasses();
            await loadExtensions();
        }

        init();
    </script>
</body>
</html>
