<?php
/**
 * FlexPBX Module Manager
 * Admin interface for installing and managing modules
 */

session_start();
require_once __DIR__ . '/../config/database.php';

// Check admin authentication
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: /admin/login.php');
    exit;
}

$admin_username = $_SESSION['admin_username'] ?? 'Admin';

// Get module list from master server
$masterServer = 'https://flexpbx.devinecreations.net';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Module Manager - FlexPBX</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background: #f5f7fa; padding: 20px 0; }
        .module-card { border-left: 4px solid #007bff; transition: transform 0.2s; }
        .module-card:hover { transform: translateY(-5px); box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        .required-badge { background: #dc3545; }
        .optional-badge { background: #28a745; }
        .installed-badge { background: #6c757d; }
        .update-badge { background: #ffc107; color: #000; }
        .progress-container { display: none; }
        .log-output { background: #2d3748; color: #fff; font-family: monospace; font-size: 12px; max-height: 400px; overflow-y: auto; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row mb-4">
            <div class="col-md-12">
                <h1 class="mb-3">
                    <i class="fas fa-puzzle-piece text-primary me-2"></i>
                    Module Manager
                </h1>
                <p class="text-muted">Install and manage FlexPBX modules from the master server</p>
            </div>
        </div>

        <!-- Actions Bar -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <button class="btn btn-primary" onclick="refreshModules()">
                                    <i class="fas fa-sync me-2"></i>Check for Updates
                                </button>
                                <button class="btn btn-success" onclick="installSelected()" id="installSelectedBtn" disabled>
                                    <i class="fas fa-download me-2"></i>Install Selected (<span id="selectedCount">0</span>)
                                </button>
                                <button class="btn btn-warning" onclick="updateAll()" id="updateAllBtn" style="display: none;">
                                    <i class="fas fa-arrow-up me-2"></i>Update All
                                </button>
                            </div>
                            <div class="col-md-6 text-end">
                                <small class="text-muted">
                                    Master Server: <strong><?= htmlspecialchars($masterServer) ?></strong>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Progress Indicator -->
        <div class="row mb-4 progress-container" id="progressContainer">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-spinner fa-spin me-2"></i>
                            <span id="progressTitle">Installing Modules...</span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="progress mb-3">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar"
                                 id="progressBar" style="width: 0%">0%</div>
                        </div>
                        <div class="log-output p-3" id="logOutput"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Module List -->
        <div class="row mb-4">
            <div class="col-md-12">
                <h3>Available Modules</h3>
            </div>
        </div>

        <div id="moduleList">
            <div class="text-center py-5">
                <i class="fas fa-spinner fa-spin fa-3x text-primary mb-3"></i>
                <p>Loading modules from master server...</p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const MASTER_SERVER = '<?= $masterServer ?>';
        const API_KEY = 'flexpbx_api_8603f84b113de94f6876b99bd7003adf';
        let selectedModules = new Set();

        // Load modules on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadModules();
        });

        async function loadModules() {
            try {
                const response = await fetch(`/api/module-installer.php?action=list&api_key=${API_KEY}`);
                const data = await response.json();

                if (data.success) {
                    displayModules(data.data);
                } else {
                    showError('Failed to load modules: ' + data.message);
                }
            } catch (error) {
                showError('Error connecting to master server: ' + error.message);
            }
        }

        function displayModules(modules) {
            const container = document.getElementById('moduleList');
            container.innerHTML = '';

            // Group by category
            const required = modules.filter(m => m.category === 'required');
            const optional = modules.filter(m => m.category === 'optional');

            if (required.length > 0) {
                container.innerHTML += '<h4 class="mb-3"><span class="badge required-badge">REQUIRED</span> Core Modules</h4>';
                container.innerHTML += '<div class="row mb-4">' + required.map(renderModuleCard).join('') + '</div>';
            }

            if (optional.length > 0) {
                container.innerHTML += '<h4 class="mb-3"><span class="badge optional-badge">OPTIONAL</span> Extension Modules</h4>';
                container.innerHTML += '<div class="row">' + optional.map(renderModuleCard).join('') + '</div>';
            }
        }

        function renderModuleCard(module) {
            const isInstalled = module.installed || false;
            const hasUpdate = module.update_available || false;
            const badgeClass = module.category === 'required' ? 'required-badge' : 'optional-badge';

            return `
                <div class="col-md-4 mb-3">
                    <div class="card module-card h-100">
                        <div class="card-body">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox"
                                       id="module_${module.key}"
                                       value="${module.key}"
                                       onchange="toggleModule('${module.key}')"
                                       ${isInstalled && !hasUpdate ? 'disabled' : ''}>
                                <label class="form-check-label" for="module_${module.key}">
                                    <h5 class="card-title mb-1">
                                        <i class="fas fa-cube text-primary me-2"></i>
                                        ${module.name}
                                    </h5>
                                </label>
                            </div>

                            <div class="mb-2">
                                <span class="badge ${badgeClass}">${module.category.toUpperCase()}</span>
                                ${isInstalled ? '<span class="badge installed-badge ms-1">INSTALLED</span>' : ''}
                                ${hasUpdate ? '<span class="badge update-badge ms-1">UPDATE AVAILABLE</span>' : ''}
                            </div>

                            <p class="card-text small">
                                <strong>Version:</strong> ${module.version}<br>
                                <strong>Size:</strong> ${module.size || 'Unknown'}<br>
                                ${module.current_version ? `<strong>Installed:</strong> ${module.current_version}<br>` : ''}
                            </p>

                            <div class="d-grid gap-2">
                                ${!isInstalled || hasUpdate ? `
                                    <button class="btn btn-sm btn-primary" onclick="installModule('${module.key}')">
                                        <i class="fas fa-download me-2"></i>
                                        ${hasUpdate ? 'Update' : 'Install'}
                                    </button>
                                ` : `
                                    <button class="btn btn-sm btn-secondary" disabled>
                                        <i class="fas fa-check me-2"></i>Installed
                                    </button>
                                `}
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }

        function toggleModule(moduleKey) {
            const checkbox = document.getElementById(`module_${moduleKey}`);
            if (checkbox.checked) {
                selectedModules.add(moduleKey);
            } else {
                selectedModules.delete(moduleKey);
            }
            updateSelectedCount();
        }

        function updateSelectedCount() {
            const count = selectedModules.size;
            document.getElementById('selectedCount').textContent = count;
            document.getElementById('installSelectedBtn').disabled = count === 0;
        }

        async function installModule(moduleKey) {
            showProgress(`Installing ${moduleKey}...`);

            try {
                const response = await fetch(`/api/module-installer.php?action=install&module=${moduleKey}&api_key=${API_KEY}`, {
                    method: 'POST'
                });
                const data = await response.json();

                if (data.success) {
                    logMessage(`✓ ${moduleKey} installed successfully`);
                    setTimeout(() => {
                        hideProgress();
                        loadModules();
                    }, 2000);
                } else {
                    logMessage(`✗ Failed to install ${moduleKey}: ${data.message}`, true);
                }
            } catch (error) {
                logMessage(`✗ Error: ${error.message}`, true);
            }
        }

        async function installSelected() {
            if (selectedModules.size === 0) return;

            showProgress(`Installing ${selectedModules.size} module(s)...`);

            let completed = 0;
            const total = selectedModules.size;

            for (const moduleKey of selectedModules) {
                logMessage(`Installing ${moduleKey}...`);

                try {
                    const response = await fetch(`/api/module-installer.php?action=install&module=${moduleKey}&api_key=${API_KEY}`, {
                        method: 'POST'
                    });
                    const data = await response.json();

                    if (data.success) {
                        logMessage(`✓ ${moduleKey} installed successfully`);
                    } else {
                        logMessage(`✗ Failed: ${data.message}`, true);
                    }
                } catch (error) {
                    logMessage(`✗ Error installing ${moduleKey}: ${error.message}`, true);
                }

                completed++;
                updateProgress((completed / total) * 100);
            }

            selectedModules.clear();
            updateSelectedCount();

            setTimeout(() => {
                hideProgress();
                loadModules();
            }, 2000);
        }

        function refreshModules() {
            loadModules();
        }

        function showProgress(title) {
            document.getElementById('progressTitle').textContent = title;
            document.getElementById('progressContainer').style.display = 'block';
            document.getElementById('logOutput').innerHTML = '';
            updateProgress(0);
        }

        function hideProgress() {
            document.getElementById('progressContainer').style.display = 'none';
        }

        function updateProgress(percent) {
            const bar = document.getElementById('progressBar');
            bar.style.width = percent + '%';
            bar.textContent = Math.round(percent) + '%';
        }

        function logMessage(message, isError = false) {
            const log = document.getElementById('logOutput');
            const color = isError ? '#ff6b6b' : '#51cf66';
            log.innerHTML += `<div style="color: ${color}">[${new Date().toLocaleTimeString()}] ${message}</div>`;
            log.scrollTop = log.scrollHeight;
        }

        function showError(message) {
            document.getElementById('moduleList').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    ${message}
                </div>
            `;
        }
    </script>
</body>
</html>
