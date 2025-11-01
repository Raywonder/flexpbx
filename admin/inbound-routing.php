<?php require_once __DIR__ . '/admin_auth_check.php'; ?>
<!DOCTYPE html>
<html lang="en">
<!--
    FlexPBX Inbound Routing UI
    Updated: October 16, 2025
    API: Uses comprehensive /api/inbound-routing.php with query parameter format
    Features:
    - IVR routing
    - Call queue routing
    - Conference bridge routing
    - Extension routing
    - Voicemail routing
    - Announcement routing
    - Time-based routing
    - DID management
-->
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FlexPBX Admin - Inbound Routing</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .sidebar a {
            color: white;
            text-decoration: none;
            transition: all 0.3s;
        }
        .sidebar a:hover, .sidebar .active {
            background: rgba(255,255,255,0.2);
            border-radius: 8px;
        }
        .route-card {
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        .route-card:hover {
            border-color: #667eea;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.15);
        }
        .route-card.active {
            border-color: #28a745;
            background: #f8fff9;
        }
        .route-type-selector {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .route-type {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        .route-type:hover {
            border-color: #667eea;
            transform: translateY(-3px);
        }
        .route-type.selected {
            border-color: #667eea;
            background: #f0f4ff;
        }
        .route-type i {
            font-size: 2rem;
            color: #667eea;
            margin-bottom: 10px;
        }
        .config-section {
            display: none;
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }
        .config-section.active {
            display: block;
        }
        .status-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar p-3">
                <div class="position-sticky">
                    <div class="mb-4 text-center">
                        <h4 class="text-white">FlexPBX Admin</h4>
                        <p class="text-white-50 small">Inbound Routing</p>
                    </div>
                    <ul class="nav flex-column">
                        <li class="nav-item mb-2">
                            <a class="nav-link p-3" href="dashboard.html">
                                <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item mb-2">
                            <a class="nav-link p-3" href="admin-extensions-management.html">
                                <i class="fas fa-users me-2"></i> Extensions
                            </a>
                        </li>
                        <li class="nav-item mb-2">
                            <a class="nav-link p-3" href="admin-trunks-management.html">
                                <i class="fas fa-network-wired me-2"></i> Trunks
                            </a>
                        </li>
                        <li class="nav-item mb-2">
                            <a class="nav-link p-3" href="voicemail-manager.html">
                                <i class="fas fa-voicemail me-2"></i> Voicemail
                            </a>
                        </li>
                        <li class="nav-item mb-2">
                            <a class="nav-link p-3" href="call-logs.html">
                                <i class="fas fa-phone-alt me-2"></i> Call Logs
                            </a>
                        </li>
                        <li class="nav-item mb-2">
                            <a class="nav-link p-3 active" href="inbound-routing.html">
                                <i class="fas fa-route me-2"></i> Inbound Routing
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1><i class="fas fa-route me-2"></i> Inbound Call Routing</h1>
                        <p class="text-muted">Configure where incoming calls from your trunks are routed</p>
                    </div>
                    <div>
                        <button class="btn btn-outline-primary" onclick="addNewRoute()">
                            <i class="fas fa-plus me-2"></i> Add Route
                        </button>
                        <button class="btn btn-outline-success" onclick="reloadDialplan()">
                            <i class="fas fa-sync me-2"></i> Reload Dialplan
                        </button>
                    </div>
                </div>

                <!-- Alert Messages -->
                <div id="alertContainer"></div>

                <!-- Current Routes -->
                <div class="row" id="routesContainer">
                    <!-- Routes will be loaded here -->
                    <div class="col-12 text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 text-muted">Loading routes...</p>
                    </div>
                </div>

                <!-- Route Editor Modal -->
                <div class="modal fade" id="routeEditorModal" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Edit Inbound Route</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <input type="hidden" id="edit-trunk-id">

                                <!-- Route Type Selector -->
                                <div class="mb-4">
                                    <h6>Select Route Type</h6>
                                    <div class="route-type-selector" id="routeTypeSelector">
                                        <div class="route-type" data-type="ivr" onclick="selectRouteType('ivr')">
                                            <i class="fas fa-list"></i>
                                            <div><strong>IVR Menu</strong></div>
                                            <small>Auto-attendant</small>
                                        </div>
                                        <div class="route-type" data-type="queue" onclick="selectRouteType('queue')">
                                            <i class="fas fa-users-cog"></i>
                                            <div><strong>Call Queue</strong></div>
                                            <small>Ring group</small>
                                        </div>
                                        <div class="route-type" data-type="conference" onclick="selectRouteType('conference')">
                                            <i class="fas fa-users"></i>
                                            <div><strong>Conference</strong></div>
                                            <small>Meeting room</small>
                                        </div>
                                        <div class="route-type" data-type="extension" onclick="selectRouteType('extension')">
                                            <i class="fas fa-phone"></i>
                                            <div><strong>Extension</strong></div>
                                            <small>Direct dial</small>
                                        </div>
                                        <div class="route-type" data-type="voicemail" onclick="selectRouteType('voicemail')">
                                            <i class="fas fa-voicemail"></i>
                                            <div><strong>Voicemail</strong></div>
                                            <small>Leave message</small>
                                        </div>
                                        <div class="route-type" data-type="announcement" onclick="selectRouteType('announcement')">
                                            <i class="fas fa-volume-up"></i>
                                            <div><strong>Announcement</strong></div>
                                            <small>Play message</small>
                                        </div>
                                        <div class="route-type" data-type="time_condition" onclick="selectRouteType('time_condition')">
                                            <i class="fas fa-clock"></i>
                                            <div><strong>Time-Based</strong></div>
                                            <small>Hours routing</small>
                                        </div>
                                    </div>
                                </div>

                                <!-- IVR Configuration -->
                                <div class="config-section" id="config-ivr">
                                    <h6><i class="fas fa-list me-2"></i> IVR Menu Configuration</h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <label class="form-label">Select IVR Menu</label>
                                            <select class="form-select" id="ivr-menu">
                                                <option value="101">Main IVR (Extension 101)</option>
                                                <option value="102">After Hours IVR</option>
                                                <option value="103">Sales IVR</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Greeting File</label>
                                            <select class="form-select" id="ivr-greeting">
                                                <option value="main-greeting.wav">Main Greeting</option>
                                                <option value="business-hours.wav">Business Hours</option>
                                                <option value="after-hours.wav">After Hours</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- Queue Configuration -->
                                <div class="config-section" id="config-queue">
                                    <h6><i class="fas fa-users-cog me-2"></i> Call Queue Configuration</h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <label class="form-label">Select Queue</label>
                                            <select class="form-select" id="queue-select">
                                                <option value="sales-queue">Sales Department</option>
                                                <option value="tech-support">Technical Support</option>
                                                <option value="accessibility-support">Accessibility Support</option>
                                                <option value="general">General Queue</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Music on Hold</label>
                                            <select class="form-select" id="queue-moh">
                                                <option value="corporate">Corporate</option>
                                                <option value="ambient">Ambient</option>
                                                <option value="jazz">Jazz</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-check mt-3">
                                        <input class="form-check-input" type="checkbox" id="queue-announce">
                                        <label class="form-check-label" for="queue-announce">
                                            Announce position in queue
                                        </label>
                                    </div>
                                </div>

                                <!-- Conference Configuration -->
                                <div class="config-section" id="config-conference">
                                    <h6><i class="fas fa-users me-2"></i> Conference Bridge Configuration</h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <label class="form-label">Select Conference Room</label>
                                            <select class="form-select" id="conference-select">
                                                <option value="6000">Main Conference (6000)</option>
                                                <option value="6001">Sales Meeting (6001)</option>
                                                <option value="6002">Support Team (6002)</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Entry Announcement</label>
                                            <select class="form-select" id="conference-announce">
                                                <option value="beep">Beep</option>
                                                <option value="name">Name Announcement</option>
                                                <option value="none">None</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-check mt-3">
                                        <input class="form-check-input" type="checkbox" id="conference-muted">
                                        <label class="form-check-label" for="conference-muted">
                                            Join muted by default
                                        </label>
                                    </div>
                                </div>

                                <!-- Extension Configuration -->
                                <div class="config-section" id="config-extension">
                                    <h6><i class="fas fa-phone me-2"></i> Extension Configuration</h6>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <label class="form-label">Select Extension</label>
                                            <select class="form-select" id="ext-select">
                                                <option value="2000">2000 - Support Manager</option>
                                                <option value="2001">2001 - Senior Tech Support</option>
                                                <option value="1000">1000 - Sales Manager</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Ring Time (seconds)</label>
                                            <input type="number" class="form-control" id="ext-ringtime" value="30" min="10" max="60">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">If No Answer</label>
                                            <select class="form-select" id="ext-failover">
                                                <option value="voicemail">Go to Voicemail</option>
                                                <option value="queue">Send to Queue</option>
                                                <option value="ivr">Return to IVR</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- Voicemail Configuration -->
                                <div class="config-section" id="config-voicemail">
                                    <h6><i class="fas fa-voicemail me-2"></i> Voicemail Configuration</h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <label class="form-label">Voicemail Box</label>
                                            <select class="form-select" id="vm-box">
                                                <option value="general">General Mailbox</option>
                                                <option value="2000">Extension 2000</option>
                                                <option value="2001">Extension 2001</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Email Address</label>
                                            <input type="email" class="form-control" id="vm-email" placeholder="voicemail@example.com">
                                        </div>
                                    </div>
                                    <div class="form-check mt-3">
                                        <input class="form-check-input" type="checkbox" id="vm-email-notify" checked>
                                        <label class="form-check-label" for="vm-email-notify">
                                            Send email notifications
                                        </label>
                                    </div>
                                </div>

                                <!-- Announcement Configuration -->
                                <div class="config-section" id="config-announcement">
                                    <h6><i class="fas fa-volume-up me-2"></i> Announcement Configuration</h6>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <label class="form-label">Audio File</label>
                                            <select class="form-select" id="announce-file">
                                                <option value="closed.wav">Business Closed</option>
                                                <option value="holiday.wav">Holiday Hours</option>
                                                <option value="emergency.wav">Emergency Message</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">After Announcement</label>
                                            <select class="form-select" id="announce-action">
                                                <option value="hangup">Hang Up</option>
                                                <option value="voicemail">Send to Voicemail</option>
                                                <option value="ivr">Go to IVR</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- Time Condition Configuration -->
                                <div class="config-section" id="config-time_condition">
                                    <h6><i class="fas fa-clock me-2"></i> Time-Based Routing Configuration</h6>
                                    <div class="row">
                                        <div class="col-md-12 mb-3">
                                            <label class="form-label">Business Hours Destination</label>
                                            <input type="text" class="form-control" id="time-bh-dest" placeholder="e.g., ivr:101 or extension:2000">
                                        </div>
                                        <div class="col-md-12">
                                            <label class="form-label">After Hours Destination</label>
                                            <input type="text" class="form-control" id="time-ah-dest" placeholder="e.g., voicemail:general or announcement:closed.wav">
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <h6>Business Hours Schedule</h6>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <label class="form-label">Weekdays</label>
                                                <div class="input-group">
                                                    <input type="time" class="form-control" id="time-weekday-start" value="09:00">
                                                    <span class="input-group-text">to</span>
                                                    <input type="time" class="form-control" id="time-weekday-end" value="17:00">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Timezone</label>
                                                <select class="form-select" id="time-timezone">
                                                    <option value="America/New_York">Eastern (ET)</option>
                                                    <option value="America/Chicago">Central (CT)</option>
                                                    <option value="America/Denver">Mountain (MT)</option>
                                                    <option value="America/Los_Angeles">Pacific (PT)</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="button" class="btn btn-primary" onclick="saveRoute()">
                                    <i class="fas fa-save me-2"></i> Save Route
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentTrunkId = null;
        let selectedType = null;
        let routeConfig = {};
        let routeEditorModal = null;

        // Load routes on page load
        document.addEventListener('DOMContentLoaded', function() {
            routeEditorModal = new bootstrap.Modal(document.getElementById('routeEditorModal'));
            loadRoutes();
        });

        // Load current routes
        function loadRoutes() {
            fetch('/api/inbound-routing.php?path=list')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        routeConfig = data.config;
                        displayRoutes(data.config.routes);
                    } else {
                        showAlert('error', 'Failed to load routes: ' + data.message);
                    }
                })
                .catch(error => {
                    showAlert('error', 'Error loading routes: ' + error.message);
                });
        }

        // Display routes
        function displayRoutes(routes) {
            const container = document.getElementById('routesContainer');

            if (!routes || Object.keys(routes).length === 0) {
                container.innerHTML = `
                    <div class="col-12 text-center py-5">
                        <i class="fas fa-route fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No inbound routes configured</p>
                        <button class="btn btn-primary" onclick="addNewRoute()">
                            <i class="fas fa-plus me-2"></i> Add First Route
                        </button>
                    </div>
                `;
                return;
            }

            container.innerHTML = Object.entries(routes).map(([key, route]) => {
                const statusClass = route.active ? 'active' : '';
                const statusBadge = route.active ?
                    '<span class="status-badge bg-success">Active</span>' :
                    '<span class="status-badge bg-secondary">Inactive</span>';

                return `
                    <div class="col-md-6 col-lg-4">
                        <div class="route-card ${statusClass}">
                            <h5>${key.toUpperCase()} Trunk</h5>
                            <p class="text-muted mb-2"><strong>DID:</strong> ${route.did}</p>
                            <p class="mb-2"><strong>Type:</strong> ${route.type.replace('_', ' ').toUpperCase()}</p>
                            <p class="mb-3">${statusBadge}</p>
                            <div class="d-flex gap-2">
                                <button class="btn btn-sm btn-primary" onclick="editRoute('${key}')">
                                    <i class="fas fa-edit me-1"></i> Edit
                                </button>
                                <button class="btn btn-sm btn-outline-primary" onclick="testRoute('${key}')">
                                    <i class="fas fa-vial me-1"></i> Test
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        }

        // Edit route
        function editRoute(trunkId) {
            currentTrunkId = trunkId;
            const route = routeConfig.routes[trunkId];

            if (!route) {
                showAlert('error', 'Route not found');
                return;
            }

            document.getElementById('edit-trunk-id').value = trunkId;
            selectRouteType(route.type);

            // Load current settings
            loadRouteSettings(route.type, route.settings);

            routeEditorModal.show();
        }

        // Select route type
        function selectRouteType(type) {
            selectedType = type;

            // Clear all selections
            document.querySelectorAll('.route-type').forEach(el => el.classList.remove('selected'));
            document.querySelectorAll('.config-section').forEach(el => el.classList.remove('active'));

            // Select current type
            document.querySelector(`[data-type="${type}"]`)?.classList.add('selected');
            document.getElementById(`config-${type}`)?.classList.add('active');
        }

        // Load route settings
        function loadRouteSettings(type, settings) {
            switch(type) {
                case 'ivr':
                    document.getElementById('ivr-menu').value = settings.menu || '101';
                    document.getElementById('ivr-greeting').value = settings.greeting || 'main-greeting.wav';
                    break;
                case 'queue':
                    document.getElementById('queue-select').value = settings.queue || 'general';
                    document.getElementById('queue-moh').value = settings.moh || 'corporate';
                    document.getElementById('queue-announce').checked = settings.announce || false;
                    break;
                case 'conference':
                    document.getElementById('conference-select').value = settings.conference || '6000';
                    document.getElementById('conference-announce').value = settings.announce_type || 'beep';
                    document.getElementById('conference-muted').checked = settings.muted || false;
                    break;
                case 'extension':
                    document.getElementById('ext-select').value = settings.extension || '2000';
                    document.getElementById('ext-ringtime').value = settings.ringtime || 30;
                    document.getElementById('ext-failover').value = settings.failover || 'voicemail';
                    break;
                case 'voicemail':
                    document.getElementById('vm-box').value = settings.mailbox || 'general';
                    document.getElementById('vm-email').value = settings.email_addr || '';
                    document.getElementById('vm-email-notify').checked = settings.email || true;
                    break;
                case 'announcement':
                    document.getElementById('announce-file').value = settings.file || 'closed.wav';
                    document.getElementById('announce-action').value = settings.action || 'hangup';
                    break;
                case 'time_condition':
                    document.getElementById('time-bh-dest').value = settings.businessHours || '';
                    document.getElementById('time-ah-dest').value = settings.afterHours || '';
                    break;
            }
        }

        // Get route settings
        function getRouteSettings() {
            const settings = {};

            switch(selectedType) {
                case 'ivr':
                    settings.menu = document.getElementById('ivr-menu').value;
                    settings.greeting = document.getElementById('ivr-greeting').value;
                    break;
                case 'queue':
                    settings.queue = document.getElementById('queue-select').value;
                    settings.moh = document.getElementById('queue-moh').value;
                    settings.announce = document.getElementById('queue-announce').checked;
                    break;
                case 'conference':
                    settings.conference = document.getElementById('conference-select').value;
                    settings.announce_type = document.getElementById('conference-announce').value;
                    settings.muted = document.getElementById('conference-muted').checked;
                    break;
                case 'extension':
                    settings.extension = document.getElementById('ext-select').value;
                    settings.ringtime = document.getElementById('ext-ringtime').value;
                    settings.failover = document.getElementById('ext-failover').value;
                    break;
                case 'voicemail':
                    settings.mailbox = document.getElementById('vm-box').value;
                    settings.email_addr = document.getElementById('vm-email').value;
                    settings.email = document.getElementById('vm-email-notify').checked;
                    break;
                case 'announcement':
                    settings.file = document.getElementById('announce-file').value;
                    settings.action = document.getElementById('announce-action').value;
                    break;
                case 'time_condition':
                    settings.businessHours = document.getElementById('time-bh-dest').value;
                    settings.afterHours = document.getElementById('time-ah-dest').value;
                    break;
            }

            return settings;
        }

        // Save route
        function saveRoute() {
            if (!currentTrunkId || !selectedType) {
                showAlert('error', 'Please select a trunk and route type');
                return;
            }

            const config = {
                trunk: currentTrunkId,
                type: selectedType,
                settings: getRouteSettings()
            };

            fetch('/api/inbound-routing.php?path=save', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ config: config })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', 'Route saved successfully!');
                    routeEditorModal.hide();
                    loadRoutes();
                } else {
                    showAlert('error', 'Failed to save route: ' + data.message);
                }
            })
            .catch(error => {
                showAlert('error', 'Error saving route: ' + error.message);
            });
        }

        // Test route
        function testRoute(trunkId) {
            fetch(`/api/inbound-routing.php?path=test&trunk=${trunkId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const result = data.test_result;
                        showAlert('info', `Test Result: ${result.message}`);
                    } else {
                        showAlert('error', 'Test failed: ' + data.message);
                    }
                })
                .catch(error => {
                    showAlert('error', 'Test error: ' + error.message);
                });
        }

        // Add new route
        function addNewRoute() {
            showAlert('info', 'Add new route functionality will be available soon. Please configure existing trunks for now.');
        }

        // Reload Asterisk dialplan
        function reloadDialplan() {
            if (!confirm('Reload Asterisk dialplan? This will apply all routing changes.')) {
                return;
            }

            fetch('/api/inbound-routing.php?path=reload', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', 'Dialplan reloaded successfully!');
                } else {
                    showAlert('error', 'Failed to reload dialplan: ' + data.message);
                }
            })
            .catch(error => {
                showAlert('error', 'Reload error: ' + error.message);
            });
        }

        // Show alert
        function showAlert(type, message) {
            const alertContainer = document.getElementById('alertContainer');
            const alertClass = type === 'error' ? 'danger' : type;

            const alert = document.createElement('div');
            alert.className = `alert alert-${alertClass} alert-dismissible fade show`;
            alert.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;

            alertContainer.appendChild(alert);

            setTimeout(() => {
                alert.remove();
            }, 5000);
        }
    </script>
</body>
</html>
