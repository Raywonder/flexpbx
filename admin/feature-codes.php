<?php
require_once __DIR__ . '/admin_auth_check.php';
/**
 * FlexPBX Admin - Feature Codes Management
 * Enable/disable/reset feature codes
 */

// Simple auth check (enhance with proper session management)
session_start();
$is_admin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feature Codes Management - FlexPBX Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
        }
        .admin-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            margin-bottom: 20px;
        }
        .feature-code-row {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            transition: all 0.3s;
        }
        .feature-code-row:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .feature-code-row.disabled {
            background: #f8f9fa;
            opacity: 0.7;
        }
        .code-badge {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 1.1rem;
            min-width: 80px;
            text-align: center;
        }
        .category-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        .category-testing { background: #e3f2fd; color: #1976d2; }
        .category-info { background: #f3e5f5; color: #7b1fa2; }
        .category-queue { background: #fff3e0; color: #f57c00; }
        .category-voicemail { background: #e8f5e9; color: #388e3c; }
        .btn-toggle {
            min-width: 100px;
        }
        .status-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }
        .status-enabled {
            background: #d4edda;
            color: #155724;
        }
        .status-disabled {
            background: #f8d7da;
            color: #721c24;
        }
        .header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="admin-card">
            <div class="header-actions">
                <div>
                    <h1><i class="fas fa-code me-2"></i>Feature Codes Management</h1>
                    <p class="text-muted mb-0">Enable, disable, or reset dial codes to default configuration</p>
                </div>
                <div>
                    <button class="btn btn-success" onclick="applyChanges()">
                        <i class="fas fa-check me-2"></i>Apply & Reload
                    </button>
                    <a href="/admin/" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back
                    </a>
                </div>
            </div>

            <div class="alert alert-info" role="alert">
                <i class="fas fa-info-circle me-2"></i>
                Changes take effect immediately after clicking "Apply & Reload". Disabled feature codes will not be accessible to users.
            </div>

            <div id="featureCodesList">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading feature codes...</p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let featureCodes = {};

        // Load feature codes on page load
        document.addEventListener('DOMContentLoaded', () => {
            loadFeatureCodes();
        });

        async function loadFeatureCodes() {
            try {
                const response = await fetch('/api/feature-codes.php?path=list');
                const data = await response.json();

                if (data.success) {
                    featureCodes = data.feature_codes;
                    renderFeatureCodes(data.feature_codes, data.categories);
                } else {
                    showError('Failed to load feature codes: ' + data.message);
                }
            } catch (error) {
                showError('Error loading feature codes: ' + error.message);
            }
        }

        function renderFeatureCodes(codes, categories) {
            const container = document.getElementById('featureCodesList');

            // Group by category
            const grouped = {};
            for (const [code, details] of Object.entries(codes)) {
                const cat = details.category || 'other';
                if (!grouped[cat]) grouped[cat] = [];
                grouped[cat].push({ code, ...details });
            }

            let html = '';

            for (const [catKey, catCodes] of Object.entries(grouped)) {
                const catName = categories[catKey] || 'Other';

                html += `
                    <div class="mb-4">
                        <h4 class="mb-3">
                            <i class="fas fa-folder me-2"></i>${catName}
                            <span class="badge bg-secondary">${catCodes.length}</span>
                        </h4>
                `;

                catCodes.forEach(fc => {
                    const statusClass = fc.enabled ? 'status-enabled' : 'status-disabled';
                    const statusText = fc.enabled ? 'Enabled' : 'Disabled';
                    const toggleBtnClass = fc.enabled ? 'btn-warning' : 'btn-success';
                    const toggleBtnText = fc.enabled ? 'Disable' : 'Enable';
                    const rowClass = fc.enabled ? '' : 'disabled';

                    html += `
                        <div class="feature-code-row ${rowClass}" id="row-${fc.code}">
                            <div class="row align-items-center">
                                <div class="col-md-2">
                                    <span class="code-badge">${fc.code}</span>
                                </div>
                                <div class="col-md-4">
                                    <h5 class="mb-1">${fc.name}</h5>
                                    <p class="text-muted mb-0 small">${fc.description}</p>
                                </div>
                                <div class="col-md-2">
                                    <span class="status-badge ${statusClass}">${statusText}</span>
                                </div>
                                <div class="col-md-4 text-end">
                                    <button class="btn ${toggleBtnClass} btn-sm btn-toggle me-2" onclick="toggleFeatureCode('${fc.code}')">
                                        <i class="fas fa-power-off me-1"></i>${toggleBtnText}
                                    </button>
                                    <button class="btn btn-info btn-sm" onclick="resetFeatureCode('${fc.code}')">
                                        <i class="fas fa-undo me-1"></i>Reset
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;
                });

                html += '</div>';
            }

            container.innerHTML = html;
        }

        async function toggleFeatureCode(code) {
            if (!confirm(`Are you sure you want to ${featureCodes[code].enabled ? 'disable' : 'enable'} ${code}?`)) {
                return;
            }

            try {
                const response = await fetch('/api/feature-codes.php?path=toggle', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `code=${encodeURIComponent(code)}`
                });

                const data = await response.json();

                if (data.success) {
                    showSuccess(`Feature code ${code} ${data.enabled ? 'enabled' : 'disabled'}`);
                    loadFeatureCodes(); // Reload to reflect changes
                } else {
                    showError('Failed to toggle feature code: ' + data.message);
                }
            } catch (error) {
                showError('Error toggling feature code: ' + error.message);
            }
        }

        async function resetFeatureCode(code) {
            if (!confirm(`Reset ${code} to default configuration? This will restore the standard FreePBX/Asterisk configuration.`)) {
                return;
            }

            try {
                const response = await fetch('/api/feature-codes.php?path=reset', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `code=${encodeURIComponent(code)}`
                });

                const data = await response.json();

                if (data.success) {
                    showSuccess(`Feature code ${code} reset to default`);
                } else {
                    showError('Failed to reset feature code: ' + data.message);
                }
            } catch (error) {
                showError('Error resetting feature code: ' + error.message);
            }
        }

        async function applyChanges() {
            if (!confirm('Apply changes and reload Asterisk dialplan? This will make all changes active immediately.')) {
                return;
            }

            const btn = event.target;
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Applying...';

            try {
                const response = await fetch('/api/feature-codes.php?path=apply', {
                    method: 'POST'
                });

                const data = await response.json();

                if (data.success) {
                    showSuccess('Feature codes applied and dialplan reloaded successfully!');
                } else {
                    showError('Failed to apply changes: ' + data.message);
                }
            } catch (error) {
                showError('Error applying changes: ' + error.message);
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        }

        function showSuccess(message) {
            const alert = document.createElement('div');
            alert.className = 'alert alert-success alert-dismissible fade show';
            alert.innerHTML = `
                <i class="fas fa-check-circle me-2"></i>${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.querySelector('.container').insertBefore(alert, document.querySelector('.admin-card'));
            setTimeout(() => alert.remove(), 5000);
        }

        function showError(message) {
            const alert = document.createElement('div');
            alert.className = 'alert alert-danger alert-dismissible fade show';
            alert.innerHTML = `
                <i class="fas fa-exclamation-circle me-2"></i>${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.querySelector('.container').insertBefore(alert, document.querySelector('.admin-card'));
            setTimeout(() => alert.remove(), 5000);
        }
    </script>
</body>
</html>
