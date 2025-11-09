<?php
/**
 * FlexPBX Setup Wizard
 * Guides administrators through initial setup
 */

session_start();

// Check admin authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$page_title = "Setup Wizard";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../api/config.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> - FlexPBX</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .setup-container {
            max-width: 900px;
            margin: 50px auto;
        }
        .checklist-item {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }
        .checklist-item:hover {
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .checklist-item.completed {
            background-color: #d4edda;
            border-color: #28a745;
        }
        .checklist-item.required {
            border-left: 4px solid #dc3545;
        }
        .progress-section {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        .btn-complete {
            transition: all 0.3s;
        }
        .maintenance-alert {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
    </style>
</head>
<body>
    <div class="container setup-container">
        <div class="text-center mb-4">
            <i class="fas fa-clipboard-check fa-4x text-primary mb-3"></i>
            <h1>FlexPBX Setup Wizard</h1>
            <p class="lead">Complete the required steps to activate your PBX system</p>
        </div>

        <!-- Progress Section -->
        <div class="progress-section">
            <h3 class="mb-3">Setup Progress</h3>
            <div class="row">
                <div class="col-md-6">
                    <h5>Overall Progress</h5>
                    <div class="progress mb-2" style="height: 30px;">
                        <div id="overall-progress" class="progress-bar bg-info" role="progressbar" style="width: 0%">
                            <span id="overall-percent">0%</span>
                        </div>
                    </div>
                    <small class="text-muted"><span id="completed-count">0</span> of <span id="total-count">0</span> items completed</small>
                </div>
                <div class="col-md-6">
                    <h5>Required Items</h5>
                    <div class="progress mb-2" style="height: 30px;">
                        <div id="required-progress" class="progress-bar bg-success" role="progressbar" style="width: 0%">
                            <span id="required-percent">0%</span>
                        </div>
                    </div>
                    <small class="text-muted"><span id="required-completed-count">0</span> of <span id="required-count">0</span> required items</small>
                </div>
            </div>

            <div id="maintenance-alert" class="alert alert-warning maintenance-alert mt-3" style="display: none;">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Maintenance Mode Active:</strong> Your FlexPBX is in maintenance mode until all required setup steps are completed.
            </div>

            <div id="setup-complete-alert" class="alert alert-success mt-3" style="display: none;">
                <i class="fas fa-check-circle"></i>
                <strong>Setup Complete!</strong> All required steps are finished. Your FlexPBX is now fully operational.
            </div>
        </div>

        <!-- Checklist Items -->
        <div id="checklist-container">
            <h3 class="mb-3">Setup Checklist</h3>
            <div id="checklist-items">
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading checklist...</p>
                </div>
            </div>
        </div>

        <div class="text-center mt-4">
            <button id="finish-setup" class="btn btn-success btn-lg" style="display: none;" onclick="finishSetup()">
                <i class="fas fa-rocket"></i> Finish Setup & Go to Dashboard
            </button>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const API_KEY = '<?= $config['api_key'] ?>';

        async function loadChecklist() {
            try {
                const response = await fetch(`/api/setup-checklist.php?action=status&api_key=${API_KEY}`);
                const result = await response.json();

                if (result.success) {
                    renderChecklist(result.data);
                    updateProgress(result.data);
                }
            } catch (error) {
                console.error('Error loading checklist:', error);
            }
        }

        function renderChecklist(data) {
            const container = document.getElementById('checklist-items');
            container.innerHTML = '';

            data.items.forEach(item => {
                const itemDiv = document.createElement('div');
                itemDiv.className = `checklist-item ${item.is_completed ? 'completed' : ''} ${item.is_required ? 'required' : ''}`;

                itemDiv.innerHTML = `
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <h5>
                                <i class="fas fa-${item.is_completed ? 'check-circle text-success' : 'circle text-secondary'}"></i>
                                ${item.check_name}
                                ${item.is_required ? '<span class="badge bg-danger ms-2">Required</span>' : '<span class="badge bg-secondary ms-2">Optional</span>'}
                            </h5>
                            <p class="text-muted mb-2">${item.check_description}</p>
                            ${item.is_completed ? `
                                <small class="text-success">
                                    <i class="fas fa-check"></i> Completed ${item.completed_at ? 'on ' + new Date(item.completed_at).toLocaleString() : ''}
                                    ${item.completed_by ? ' by ' + item.completed_by : ''}
                                </small>
                            ` : ''}
                        </div>
                        <div class="ms-3">
                            <button class="btn btn-${item.is_completed ? 'outline-secondary' : 'primary'} btn-complete"
                                    onclick="toggleCheckItem('${item.check_key}', ${item.is_completed ? 0 : 1})">
                                <i class="fas fa-${item.is_completed ? 'undo' : 'check'}"></i>
                                ${item.is_completed ? 'Undo' : 'Mark Complete'}
                            </button>
                        </div>
                    </div>
                `;

                container.appendChild(itemDiv);
            });
        }

        function updateProgress(data) {
            // Overall progress
            document.getElementById('overall-progress').style.width = data.progress_percent + '%';
            document.getElementById('overall-percent').textContent = data.progress_percent + '%';
            document.getElementById('completed-count').textContent = data.completed;
            document.getElementById('total-count').textContent = data.total;

            // Required progress
            document.getElementById('required-progress').style.width = data.required_progress_percent + '%';
            document.getElementById('required-percent').textContent = data.required_progress_percent + '%';
            document.getElementById('required-completed-count').textContent = data.required_completed;
            document.getElementById('required-count').textContent = data.required;

            // Maintenance alert
            const maintenanceAlert = document.getElementById('maintenance-alert');
            const completeAlert = document.getElementById('setup-complete-alert');
            const finishButton = document.getElementById('finish-setup');

            if (data.setup_complete) {
                maintenanceAlert.style.display = 'none';
                completeAlert.style.display = 'block';
                finishButton.style.display = 'inline-block';
            } else {
                maintenanceAlert.style.display = 'block';
                completeAlert.style.display = 'none';
                finishButton.style.display = 'none';
            }
        }

        async function toggleCheckItem(checkKey, complete) {
            try {
                const action = complete ? 'complete' : 'uncomplete';
                const formData = new FormData();
                formData.append('api_key', API_KEY);
                formData.append('check_key', checkKey);
                formData.append('completed_by', '<?= $_SESSION['admin_username'] ?? 'admin' ?>');

                const response = await fetch(`/api/setup-checklist.php?action=${action}`, {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    // Reload checklist
                    await loadChecklist();

                    // Show notification
                    if (result.setup_complete) {
                        alert('Setup complete! Maintenance mode has been disabled.');
                    }
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                console.error('Error toggling check item:', error);
                alert('An error occurred');
            }
        }

        function finishSetup() {
            window.location.href = 'dashboard.php';
        }

        // Load checklist on page load
        document.addEventListener('DOMContentLoaded', loadChecklist);

        // Auto-refresh every 30 seconds
        setInterval(loadChecklist, 30000);
    </script>
</body>
</html>
