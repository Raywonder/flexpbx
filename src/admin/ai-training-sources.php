<?php
require_once __DIR__ . '/admin_auth_check.php';

// Set page title for header
$page_title = 'AI Training Data Sources';

// Include the admin header
require_once __DIR__ . '/includes/admin_header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h2><i class="fas fa-brain"></i> AI Training Data Sources</h2>
            <p class="text-muted">Granular control over what data AI models can access and train on</p>
        </div>
    </div>

    <!-- Privacy Notice -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="alert alert-info">
                <h5><i class="fas fa-shield-alt"></i> Privacy & Responsibility Notice</h5>
                <p class="mb-0">
                    <strong>All data is processed locally on your system.</strong> You are responsible for compliance with privacy laws
                    (GDPR, CCPA, HIPAA, etc.) and your terms of service when enabling AI training on user data.
                    Consider the privacy implications of each data source before enabling it.
                </p>
            </div>
        </div>
    </div>

    <!-- Training Status Card -->
    <div class="row mb-4">
        <div class="col-lg-4 col-md-6">
            <div class="card border-primary">
                <div class="card-header bg-primary text-white">
                    <i class="fas fa-chart-pie"></i> Training Sources Status
                </div>
                <div class="card-body">
                    <h3 class="mb-3"><span id="enabledCount">0</span> / <span id="totalCount">0</span> Enabled</h3>
                    <div class="progress mb-2" style="height: 25px;">
                        <div id="progressBar" class="progress-bar bg-success" role="progressbar" style="width: 0%">
                            <span id="progressText">0%</span>
                        </div>
                    </div>
                    <button class="btn btn-success btn-sm mt-2" onclick="enableRecommended()">
                        <i class="fas fa-check-double"></i> Enable Recommended
                    </button>
                    <button class="btn btn-warning btn-sm mt-2" onclick="disableAll()">
                        <i class="fas fa-times"></i> Disable All
                    </button>
                </div>
            </div>
        </div>

        <div class="col-lg-4 col-md-6">
            <div class="card border-warning">
                <div class="card-header bg-warning">
                    <i class="fas fa-exclamation-triangle"></i> Privacy Levels
                </div>
                <div class="card-body">
                    <div class="mb-2">
                        <span class="badge badge-success">Low</span> - Non-sensitive system data
                    </div>
                    <div class="mb-2">
                        <span class="badge badge-info">Medium</span> - System logs and patterns
                    </div>
                    <div class="mb-2">
                        <span class="badge badge-warning">High</span> - Configuration data
                    </div>
                    <div class="mb-2">
                        <span class="badge badge-danger">Very High</span> - Personal/private data
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4 col-md-6">
            <div class="card border-info">
                <div class="card-header bg-info text-white">
                    <i class="fas fa-info-circle"></i> Configuration
                </div>
                <div class="card-body">
                    <p><strong>Config File:</strong></p>
                    <code id="configPath" style="font-size: 0.85rem; word-break: break-all;"></code>
                    <hr>
                    <button class="btn btn-primary btn-sm" onclick="exportConfig()">
                        <i class="fas fa-download"></i> Export Config
                    </button>
                    <button class="btn btn-secondary btn-sm" onclick="location.reload()">
                        <i class="fas fa-sync"></i> Refresh
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Sources Accordion -->
    <div class="row">
        <div class="col-12">
            <div class="accordion" id="dataSourcesAccordion">
                <!-- Loading spinner -->
                <div class="text-center py-5" id="loadingSpinner">
                    <div class="spinner-border text-primary" role="status">
                        <span class="sr-only">Loading data sources...</span>
                    </div>
                    <p class="mt-3 text-muted">Loading AI training data sources...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="row mt-4">
        <div class="col-12">
            <button class="btn btn-primary btn-lg" onclick="saveAllChanges()">
                <i class="fas fa-save"></i> Save All Changes
            </button>
            <button class="btn btn-secondary btn-lg" onclick="location.href='log-management.php'">
                <i class="fas fa-arrow-left"></i> Back to Log Management
            </button>
        </div>
    </div>
</div>

<style>
.source-card {
    border-left: 4px solid #ccc;
    margin-bottom: 0.5rem;
    transition: all 0.3s;
}
.source-card:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.source-card.enabled {
    border-left-color: #28a745;
    background-color: #f8fff9;
}
.privacy-low { border-left-color: #28a745 !important; }
.privacy-medium { border-left-color: #17a2b8 !important; }
.privacy-high { border-left-color: #ffc107 !important; }
.privacy-very_high { border-left-color: #dc3545 !important; }
</style>

<script>
let dataSources = {};
let changedSources = new Set();

// Load data sources
function loadDataSources() {
    fetch('../api/ai-training-manager.php?action=get_sources')
        .then(r => r.json())
        .then(result => {
            if (result.success) {
                dataSources = result.data;
                document.getElementById('configPath').textContent = result.config_file || 'Not yet created';
                renderDataSources();
                updateStats();
                document.getElementById('loadingSpinner').style.display = 'none';
            } else {
                alert('Error loading data sources: ' + result.message);
            }
        })
        .catch(err => {
            console.error('Error:', err);
            alert('Failed to load data sources. Check console for details.');
        });
}

function renderDataSources() {
    const accordion = document.getElementById('dataSourcesAccordion');
    let html = '';
    let categoryIndex = 0;

    for (const [categoryKey, category] of Object.entries(dataSources)) {
        if (categoryKey === 'privacy_notice' || categoryKey === 'config_file') continue;

        const collapseId = 'collapse' + categoryIndex;
        const headingId = 'heading' + categoryIndex;

        // Count enabled sources in category
        let enabledInCategory = 0;
        let totalInCategory = 0;
        for (const [sourceKey, source] of Object.entries(category.sources)) {
            totalInCategory++;
            if (source.enabled) enabledInCategory++;
        }

        html += `
            <div class="card mb-2">
                <div class="card-header" id="${headingId}">
                    <h5 class="mb-0">
                        <button class="btn btn-link w-100 text-left d-flex justify-content-between align-items-center"
                                type="button" data-toggle="collapse" data-target="#${collapseId}">
                            <span>
                                <i class="fas fa-folder"></i> ${category.name}
                                <span class="badge badge-secondary ml-2">${enabledInCategory}/${totalInCategory}</span>
                            </span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                    </h5>
                </div>
                <div id="${collapseId}" class="collapse ${categoryIndex === 0 ? 'show' : ''}" data-parent="#dataSourcesAccordion">
                    <div class="card-body">
        `;

        // Render sources in category
        for (const [sourceKey, source] of Object.entries(category.sources)) {
            const privacyBadgeClass = getPrivacyBadgeClass(source.privacy_level);
            const privacyClass = 'privacy-' + source.privacy_level;
            const enabledClass = source.enabled ? 'enabled' : '';
            const fullSourceKey = categoryKey + '.' + sourceKey;

            html += `
                <div class="card source-card ${privacyClass} ${enabledClass}" data-source="${fullSourceKey}">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-lg-6">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input"
                                           id="toggle_${fullSourceKey.replace(/\./g, '_')}"
                                           ${source.enabled ? 'checked' : ''}
                                           onchange="toggleSource('${categoryKey}', '${sourceKey}')">
                                    <label class="custom-control-label" for="toggle_${fullSourceKey.replace(/\./g, '_')}">
                                        <strong>${source.name}</strong>
                                    </label>
                                </div>
                                <p class="text-muted mb-1 mt-1" style="font-size: 0.9rem;">${source.description}</p>
                            </div>
                            <div class="col-lg-6 text-right">
                                <div class="mb-1">
                                    <span class="badge ${privacyBadgeClass}">${formatPrivacyLevel(source.privacy_level)}</span>
                                    <span class="badge badge-secondary">${source.estimated_size}</span>
                                </div>
                                <div>
                                    ${source.data_types.map(type =>
                                        `<span class="badge badge-light">${type}</span>`
                                    ).join(' ')}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }

        html += `
                    </div>
                </div>
            </div>
        `;
        categoryIndex++;
    }

    accordion.innerHTML = html;
}

function toggleSource(category, source) {
    dataSources[category].sources[source].enabled = !dataSources[category].sources[source].enabled;
    changedSources.add(category + '.' + source);

    // Update visual state
    const fullKey = category + '.' + source;
    const card = document.querySelector(`[data-source="${fullKey}"]`);
    if (dataSources[category].sources[source].enabled) {
        card.classList.add('enabled');
    } else {
        card.classList.remove('enabled');
    }

    updateStats();
}

function updateStats() {
    let enabled = 0;
    let total = 0;

    for (const [categoryKey, category] of Object.entries(dataSources)) {
        if (categoryKey === 'privacy_notice' || categoryKey === 'config_file') continue;

        for (const [sourceKey, source] of Object.entries(category.sources)) {
            total++;
            if (source.enabled) enabled++;
        }
    }

    document.getElementById('enabledCount').textContent = enabled;
    document.getElementById('totalCount').textContent = total;

    const percentage = total > 0 ? Math.round((enabled / total) * 100) : 0;
    document.getElementById('progressBar').style.width = percentage + '%';
    document.getElementById('progressText').textContent = percentage + '%';
}

function saveAllChanges() {
    if (changedSources.size === 0) {
        alert('No changes to save');
        return;
    }

    const promises = [];
    changedSources.forEach(fullKey => {
        const [category, source] = fullKey.split('.');
        const enabled = dataSources[category].sources[source].enabled;

        const data = new FormData();
        data.append('action', 'update_source');
        data.append('category', category);
        data.append('source', source);
        data.append('enabled', enabled);

        promises.push(
            fetch('../api/ai-training-manager.php', {
                method: 'POST',
                body: data
            }).then(r => r.json())
        );
    });

    Promise.all(promises)
        .then(results => {
            const allSuccess = results.every(r => r.success);
            if (allSuccess) {
                alert('All changes saved successfully!');
                changedSources.clear();
                loadDataSources(); // Reload to confirm
            } else {
                alert('Some changes failed to save. Check console for details.');
                console.error('Save errors:', results.filter(r => !r.success));
            }
        })
        .catch(err => {
            alert('Error saving changes: ' + err);
            console.error(err);
        });
}

function enableRecommended() {
    // Enable only low and medium privacy sources
    for (const [categoryKey, category] of Object.entries(dataSources)) {
        if (categoryKey === 'privacy_notice' || categoryKey === 'config_file') continue;

        for (const [sourceKey, source] of Object.entries(category.sources)) {
            if (source.privacy_level === 'low' || source.privacy_level === 'medium') {
                if (!source.enabled) {
                    dataSources[categoryKey].sources[sourceKey].enabled = true;
                    changedSources.add(categoryKey + '.' + sourceKey);
                }
            }
        }
    }

    renderDataSources();
    updateStats();
    alert('Recommended sources enabled (Low and Medium privacy levels). Click "Save All Changes" to persist.');
}

function disableAll() {
    if (!confirm('Disable all AI training data sources?')) return;

    for (const [categoryKey, category] of Object.entries(dataSources)) {
        if (categoryKey === 'privacy_notice' || categoryKey === 'config_file') continue;

        for (const [sourceKey, source] of Object.entries(category.sources)) {
            if (source.enabled) {
                dataSources[categoryKey].sources[sourceKey].enabled = false;
                changedSources.add(categoryKey + '.' + sourceKey);
            }
        }
    }

    renderDataSources();
    updateStats();
    alert('All sources disabled. Click "Save All Changes" to persist.');
}

function exportConfig() {
    fetch('../api/ai-training-manager.php?action=get_sources')
        .then(r => r.json())
        .then(result => {
            const blob = new Blob([JSON.stringify(result, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'ai-training-sources-config.json';
            a.click();
        });
}

function getPrivacyBadgeClass(level) {
    const classes = {
        'low': 'badge-success',
        'medium': 'badge-info',
        'high': 'badge-warning',
        'very_high': 'badge-danger'
    };
    return classes[level] || 'badge-secondary';
}

function formatPrivacyLevel(level) {
    return level.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
}

// Load on page load
loadDataSources();
</script>

<?php include 'footer.php'; ?>
