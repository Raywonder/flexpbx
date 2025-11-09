<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: index.php');
    exit;
}

$page_title = "Log Management";
include 'header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h2><i class="fas fa-file-alt"></i> Log Management & AI Training</h2>
            <p class="text-muted">Monitor and manage system logs with AI training mode support</p>
        </div>
    </div>

    <!-- AI Training Mode Toggle -->
    <div class="row mb-4">
        <div class="col-lg-6">
            <div class="card border-info">
                <div class="card-header bg-info text-white">
                    <i class="fas fa-brain"></i> AI Training Mode
                </div>
                <div class="card-body">
                    <p>When enabled, logs are retained longer for AI model training. After training, unneeded data is automatically cleaned up.</p>

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="aiTrainingToggle">
                        <label class="form-check-label" for="aiTrainingToggle">
                            Enable AI Training Mode
                        </label>
                    </div>

                    <div id="aiTrainingSettings" style="display:none;">
                        <div class="mb-3">
                            <label>Services to Train On:</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="trainAsterisk" value="asterisk" checked>
                                <label class="form-check-label" for="trainAsterisk">Asterisk PBX Logs</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="trainCoturn" value="coturn" checked>
                                <label class="form-check-label" for="trainCoturn">STUN/TURN Server Logs</label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="retentionDays">Retention Period (days):</label>
                            <input type="number" class="form-control" id="retentionDays" value="30" min="7" max="90">
                            <small class="text-muted">Logs older than this will be cleaned after training</small>
                        </div>

                        <button class="btn btn-primary" onclick="saveAITrainingSettings()">
                            <i class="fas fa-save"></i> Save AI Training Settings
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-warning">
                <div class="card-header bg-warning">
                    <i class="fas fa-exclamation-triangle"></i> Active Alerts
                </div>
                <div class="card-body" id="logAlerts">
                    <p class="text-muted">Loading alerts...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Log Status Cards -->
    <div class="row" id="logStatusCards">
        <div class="col-12">
            <div class="spinner-border" role="status">
                <span class="sr-only">Loading log status...</span>
            </div>
        </div>
    </div>

    <!-- Log Configuration -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-cog"></i> Log Rotation Configuration
                </div>
                <div class="card-body">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Service</th>
                                <th>Rotation</th>
                                <th>Retention</th>
                                <th>Max Size</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>Asterisk</strong></td>
                                <td>Daily</td>
                                <td><span id="asteriskRetention">7 days</span></td>
                                <td>200 MB</td>
                                <td>
                                    <button class="btn btn-sm btn-primary" onclick="rotateLog('asterisk')">
                                        <i class="fas fa-sync"></i> Rotate Now
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="cleanupLog('asterisk')">
                                        <i class="fas fa-trash"></i> Cleanup
                                    </button>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Coturn (STUN/TURN)</strong></td>
                                <td>Daily</td>
                                <td><span id="coturnRetention">3 days</span></td>
                                <td>100 MB</td>
                                <td>
                                    <button class="btn btn-sm btn-primary" onclick="rotateLog('coturn')">
                                        <i class="fas fa-sync"></i> Rotate Now
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="cleanupLog('coturn')">
                                        <i class="fas fa-trash"></i> Cleanup
                                    </button>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>MySQL/MariaDB</strong></td>
                                <td>Monthly</td>
                                <td>6 months</td>
                                <td>500 MB</td>
                                <td>
                                    <button class="btn btn-sm btn-secondary" disabled>
                                        Auto-managed
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <button class="btn btn-danger" onclick="cleanupLog('all')">
                        <i class="fas fa-broom"></i> Cleanup All Logs Now
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Load log status
function loadLogStatus() {
    fetch('api/log-management.php?action=status')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                displayLogStatus(data.data.logs);
                displayAlerts(data.data.logs);

                // Update AI training toggle
                document.getElementById('aiTrainingToggle').checked = data.data.ai_training_mode;
                toggleAISettings();
            }
        })
        .catch(err => console.error('Error loading log status:', err));
}

function displayLogStatus(logs) {
    const container = document.getElementById('logStatusCards');
    let html = '';

    for (const [key, log] of Object.entries(logs)) {
        let cardClass = 'border-success';
        let badgeClass = 'badge-success';
        let iconClass = 'check-circle';

        if (log.level === 'warning') {
            cardClass = 'border-warning';
            badgeClass = 'badge-warning';
            iconClass = 'exclamation-triangle';
        } else if (log.level === 'critical') {
            cardClass = 'border-danger';
            badgeClass = 'badge-danger';
            iconClass = 'exclamation-circle';
        }

        html += `
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card ${cardClass}">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="fas fa-${iconClass}"></i> ${log.name}
                        </h5>
                        <h2>${log.size_display}</h2>
                        <span class="badge ${badgeClass}">${log.level.toUpperCase()}</span>
                        <small class="text-muted d-block mt-2">${log.path}</small>
                    </div>
                </div>
            </div>
        `;
    }

    container.innerHTML = html;
}

function displayAlerts(logs) {
    const container = document.getElementById('logAlerts');
    let alerts = [];

    for (const [key, log] of Object.entries(logs)) {
        if (log.level !== 'ok') {
            alerts.push(log);
        }
    }

    if (alerts.length === 0) {
        container.innerHTML = '<p class="text-success"><i class="fas fa-check"></i> All logs are within normal limits</p>';
    } else {
        let html = '<ul class="list-unstyled">';
        alerts.forEach(log => {
            html += `<li class="mb-2">
                <i class="fas fa-exclamation-triangle text-warning"></i>
                <strong>${log.name}:</strong> ${log.size_display} (${log.level})
            </li>`;
        });
        html += '</ul>';
        container.innerHTML = html;
    }
}

function toggleAISettings() {
    const toggle = document.getElementById('aiTrainingToggle');
    const settings = document.getElementById('aiTrainingSettings');
    settings.style.display = toggle.checked ? 'block' : 'none';
}

function saveAITrainingSettings() {
    const enabled = document.getElementById('aiTrainingToggle').checked;
    const services = [];

    if (document.getElementById('trainAsterisk').checked) services.push('asterisk');
    if (document.getElementById('trainCoturn').checked) services.push('coturn');

    const retention_days = document.getElementById('retentionDays').value;

    const data = new FormData();
    data.append('action', 'toggle_ai_training');
    data.append('enabled', enabled);
    data.append('services', JSON.stringify(services));
    data.append('retention_days', retention_days);

    fetch('api/log-management.php', {
        method: 'POST',
        body: data
    })
    .then(r => r.json())
    .then(result => {
        if (result.success) {
            alert('AI Training settings saved successfully!');
            loadLogStatus();
        } else {
            alert('Error: ' + result.message);
        }
    })
    .catch(err => alert('Error saving settings: ' + err));
}

function rotateLog(service) {
    if (!confirm(`Rotate ${service} logs now?`)) return;

    const data = new FormData();
    data.append('action', 'rotate');
    data.append('service', service);

    fetch('api/log-management.php', {
        method: 'POST',
        body: data
    })
    .then(r => r.json())
    .then(result => {
        alert(result.success ? 'Log rotation completed!' : 'Error: ' + result.message);
        loadLogStatus();
    })
    .catch(err => alert('Error: ' + err));
}

function cleanupLog(service) {
    if (!confirm(`Cleanup ${service} logs? This will delete old log files.`)) return;

    const data = new FormData();
    data.append('action', 'cleanup');
    data.append('service', service);

    fetch('api/log-management.php', {
        method: 'POST',
        body: data
    })
    .then(r => r.json())
    .then(result => {
        alert(result.success ? 'Log cleanup completed!' : 'Error: ' + result.message);
        loadLogStatus();
    })
    .catch(err => alert('Error: ' + err));
}

// Event listeners
document.getElementById('aiTrainingToggle').addEventListener('change', toggleAISettings);

// Load status on page load
loadLogStatus();

// Refresh every 5 minutes
setInterval(loadLogStatus, 300000);
</script>

<?php include 'footer.php'; ?>
