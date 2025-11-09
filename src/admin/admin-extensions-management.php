<?php require_once __DIR__ . '/admin_auth_check.php'; ?>
<!DOCTYPE html>
<html lang="en">
<!--
    FlexPBX Extensions Management UI
    Updated: October 16, 2025
    API: Uses new comprehensive /api/extensions.php with query parameter format
    Changes:
    - Migrated from RESTful pattern (/api/extensions/{id}) to query params (?path=details&id={id})
    - All CRUD operations now use /api/extensions.php?path=...
    - Real-time status updates integrated
    - Bulk operations supported
-->
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FlexPBX Admin - Extension Management</title>
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
        .extension-card {
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            transition: all 0.3s ease;
            margin-bottom: 15px;
        }
        .extension-card:hover {
            border-color: #667eea;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.15);
        }
        .status-online {
            color: #28a745;
            animation: pulse 2s infinite;
        }
        .status-offline {
            color: #dc3545;
        }
        .status-busy {
            color: #ffc107;
        }
        .status-away {
            color: #6c757d;
        }
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        .department-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        .call-stats {
            font-size: 0.875rem;
            color: #6c757d;
        }
        .quick-actions {
            display: none;
        }
        .extension-card:hover .quick-actions {
            display: block;
        }
        .bulk-actions {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .extension-form {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .form-section {
            border-bottom: 1px solid #e0e0e0;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        .form-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        .password-strength {
            height: 4px;
            border-radius: 2px;
            margin-top: 5px;
            transition: all 0.3s;
        }
        .strength-weak { background: #dc3545; width: 25%; }
        .strength-fair { background: #ffc107; width: 50%; }
        .strength-good { background: #17a2b8; width: 75%; }
        .strength-strong { background: #28a745; width: 100%; }
        .voicemail-preview {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-top: 10px;
        }
        .call-history-item {
            border-bottom: 1px solid #e0e0e0;
            padding: 10px 0;
        }
        .call-history-item:last-child {
            border-bottom: none;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-2 d-none d-md-block sidebar p-3">
                <div class="sidebar-sticky">
                    <div class="text-center mb-4">
                        <h4 class="text-white">FlexPBX Admin</h4>
                        <small class="text-light opacity-75">Extensions</small>
                    </div>

                    <ul class="nav flex-column">
                        <li class="nav-item mb-2">
                            <a class="nav-link p-3" href="dashboard.html">
                                <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item mb-2">
                            <a class="nav-link p-3" href="admin-trunks-management.html">
                                <i class="fas fa-network-wired me-2"></i> Trunk Management
                            </a>
                        </li>
                        <li class="nav-item mb-2">
                            <a class="nav-link p-3 active" href="admin-extensions-management.html">
                                <i class="fas fa-phone me-2"></i> Extensions
                            </a>
                        </li>
                        <li class="nav-item mb-2">
                            <a class="nav-link p-3" href="admin-google-voice.html">
                                <i class="fab fa-google me-2"></i> Google Voice
                            </a>
                        </li>
                        <li class="nav-item mb-2">
                            <a class="nav-link p-3" href="admin-queues.html">
                                <i class="fas fa-users me-2"></i> Call Queues
                            </a>
                        </li>
                        <li class="nav-item mb-2">
                            <a class="nav-link p-3" href="admin-ivr.html">
                                <i class="fas fa-sitemap me-2"></i> IVR System
                            </a>
                        </li>
                        <li class="nav-item mb-2">
                            <a class="nav-link p-3" href="admin-call-logs.html">
                                <i class="fas fa-history me-2"></i> Call Logs
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main Content -->
            <main class="col-md-10 ml-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-phone me-2 text-primary"></i>
                        Extension Management
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button class="btn btn-success" onclick="addNewExtension()">
                            <i class="fas fa-plus me-1"></i> Add Extension
                        </button>
                        <button class="btn btn-outline-primary ms-2" onclick="bulkImport()">
                            <i class="fas fa-upload me-1"></i> Bulk Import
                        </button>
                        <button class="btn btn-outline-secondary ms-2" onclick="exportExtensions()">
                            <i class="fas fa-download me-1"></i> Export
                        </button>
                    </div>
                </div>

                <!-- Quick Stats -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card border-success">
                            <div class="card-body text-center">
                                <h3 class="text-success" id="onlineExtensions">17</h3>
                                <p class="mb-0">Online Extensions</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-warning">
                            <div class="card-body text-center">
                                <h3 class="text-warning" id="busyExtensions">3</h3>
                                <p class="mb-0">Busy Extensions</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-info">
                            <div class="card-body text-center">
                                <h3 class="text-info" id="totalExtensions">20</h3>
                                <p class="mb-0">Total Extensions</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-primary">
                            <div class="card-body text-center">
                                <h3 class="text-primary" id="activeCalls">8</h3>
                                <p class="mb-0">Active Calls</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bulk Actions -->
                <div class="bulk-actions" id="bulkActions" style="display: none;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong><span id="selectedCount">0</span> extensions selected</strong>
                        </div>
                        <div class="btn-group">
                            <button class="btn btn-outline-primary" onclick="bulkEdit()">
                                <i class="fas fa-edit me-1"></i> Bulk Edit
                            </button>
                            <button class="btn btn-outline-warning" onclick="bulkDisable()">
                                <i class="fas fa-pause me-1"></i> Disable
                            </button>
                            <button class="btn btn-outline-success" onclick="bulkEnable()">
                                <i class="fas fa-play me-1"></i> Enable
                            </button>
                            <button class="btn btn-outline-danger" onclick="bulkDelete()">
                                <i class="fas fa-trash me-1"></i> Delete
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Filter and Search -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" class="form-control" id="searchExtensions" placeholder="Search extensions..." onkeyup="filterExtensions()">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" id="filterDepartment" onchange="filterExtensions()">
                            <option value="">All Departments</option>
                            <option value="sales">Sales</option>
                            <option value="support">Support</option>
                            <option value="conference">Conference</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" id="filterStatus" onchange="filterExtensions()">
                            <option value="">All Status</option>
                            <option value="online">Online</option>
                            <option value="offline">Offline</option>
                            <option value="busy">Busy</option>
                            <option value="away">Away</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                            <label class="form-check-label" for="selectAll">Select All</label>
                        </div>
                    </div>
                </div>

                <!-- Extensions Grid -->
                <div class="row" id="extensionsGrid">
                    <!-- Sales Department -->
                    <div class="col-md-6">
                        <div class="extension-card" data-department="sales" data-status="online" data-extension="1000">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center">
                                    <input type="checkbox" class="form-check-input me-3 extension-checkbox" value="1000">
                                    <div>
                                        <h5 class="mb-0">
                                            <i class="fas fa-circle status-online me-2"></i>
                                            1000 - Sales Manager
                                        </h5>
                                        <small class="text-muted">salesmanager</small>
                                    </div>
                                </div>
                                <div>
                                    <span class="badge department-badge bg-primary">Sales</span>
                                    <div class="btn-group btn-group-sm quick-actions ms-2">
                                        <button class="btn btn-outline-success" onclick="callExtension('1000')" title="Call">
                                            <i class="fas fa-phone"></i>
                                        </button>
                                        <button class="btn btn-outline-primary" onclick="editExtension('1000')" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-outline-info" onclick="viewDetails('1000')" title="Details">
                                            <i class="fas fa-info"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-6">
                                        <div class="call-stats">
                                            <i class="fas fa-phone-alt me-1"></i> Calls Today: 23<br>
                                            <i class="fas fa-clock me-1"></i> Talk Time: 2h 34m<br>
                                            <i class="fas fa-voicemail me-1"></i> Voicemails: 2
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="call-stats">
                                            <i class="fas fa-signal me-1"></i> Status: Available<br>
                                            <i class="fas fa-headset me-1"></i> Device: Desk Phone<br>
                                            <i class="fas fa-user-tie me-1"></i> Role: Manager
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <small class="text-success"><i class="fas fa-check me-1"></i> Last registered: 2 minutes ago</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="extension-card" data-department="support" data-status="busy" data-extension="2001">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center">
                                    <input type="checkbox" class="form-check-input me-3 extension-checkbox" value="2001">
                                    <div>
                                        <h5 class="mb-0">
                                            <i class="fas fa-circle status-busy me-2"></i>
                                            2001 - Senior Tech Support ⭐
                                        </h5>
                                        <small class="text-muted">techsupport1</small>
                                    </div>
                                </div>
                                <div>
                                    <span class="badge department-badge bg-success">Support</span>
                                    <div class="btn-group btn-group-sm quick-actions ms-2">
                                        <button class="btn btn-outline-success" onclick="callExtension('2001')" title="Call">
                                            <i class="fas fa-phone"></i>
                                        </button>
                                        <button class="btn btn-outline-primary" onclick="editExtension('2001')" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-outline-info" onclick="viewDetails('2001')" title="Details">
                                            <i class="fas fa-info"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-6">
                                        <div class="call-stats">
                                            <i class="fas fa-phone-alt me-1"></i> Calls Today: 41<br>
                                            <i class="fas fa-clock me-1"></i> Talk Time: 4h 12m<br>
                                            <i class="fas fa-voicemail me-1"></i> Voicemails: 0
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="call-stats">
                                            <i class="fas fa-signal me-1"></i> Status: On Call<br>
                                            <i class="fas fa-headset me-1"></i> Device: SIP Client<br>
                                            <i class="fas fa-star me-1"></i> Role: Senior
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <small class="text-warning"><i class="fas fa-phone-alt me-1"></i> Currently on call with +1 (555) 123-4567</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="extension-card" data-department="sales" data-status="online" data-extension="1001">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center">
                                    <input type="checkbox" class="form-check-input me-3 extension-checkbox" value="1001">
                                    <div>
                                        <h5 class="mb-0">
                                            <i class="fas fa-circle status-online me-2"></i>
                                            1001 - Sales Rep 1
                                        </h5>
                                        <small class="text-muted">salesrep1</small>
                                    </div>
                                </div>
                                <div>
                                    <span class="badge department-badge bg-primary">Sales</span>
                                    <div class="btn-group btn-group-sm quick-actions ms-2">
                                        <button class="btn btn-outline-success" onclick="callExtension('1001')" title="Call">
                                            <i class="fas fa-phone"></i>
                                        </button>
                                        <button class="btn btn-outline-primary" onclick="editExtension('1001')" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-outline-info" onclick="viewDetails('1001')" title="Details">
                                            <i class="fas fa-info"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-6">
                                        <div class="call-stats">
                                            <i class="fas fa-phone-alt me-1"></i> Calls Today: 18<br>
                                            <i class="fas fa-clock me-1"></i> Talk Time: 1h 47m<br>
                                            <i class="fas fa-voicemail me-1"></i> Voicemails: 1
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="call-stats">
                                            <i class="fas fa-signal me-1"></i> Status: Available<br>
                                            <i class="fas fa-headset me-1"></i> Device: Mobile App<br>
                                            <i class="fas fa-user me-1"></i> Role: Representative
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <small class="text-success"><i class="fas fa-check me-1"></i> Last registered: 5 minutes ago</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="extension-card" data-department="conference" data-status="online" data-extension="8000">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center">
                                    <input type="checkbox" class="form-check-input me-3 extension-checkbox" value="8000">
                                    <div>
                                        <h5 class="mb-0">
                                            <i class="fas fa-circle status-online me-2"></i>
                                            8000 - Main Conference
                                        </h5>
                                        <small class="text-muted">conference_main</small>
                                    </div>
                                </div>
                                <div>
                                    <span class="badge department-badge bg-info">Conference</span>
                                    <div class="btn-group btn-group-sm quick-actions ms-2">
                                        <button class="btn btn-outline-success" onclick="callExtension('8000')" title="Join">
                                            <i class="fas fa-users"></i>
                                        </button>
                                        <button class="btn btn-outline-primary" onclick="editExtension('8000')" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-outline-info" onclick="viewDetails('8000')" title="Details">
                                            <i class="fas fa-info"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-6">
                                        <div class="call-stats">
                                            <i class="fas fa-users me-1"></i> Participants: 4/50<br>
                                            <i class="fas fa-clock me-1"></i> Duration: 47 minutes<br>
                                            <i class="fas fa-microphone me-1"></i> Recording: Yes
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="call-stats">
                                            <i class="fas fa-signal me-1"></i> Status: Active<br>
                                            <i class="fas fa-lock me-1"></i> Security: PIN Protected<br>
                                            <i class="fas fa-cogs me-1"></i> Type: Conference Room
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <small class="text-info"><i class="fas fa-users me-1"></i> Active conference in progress</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Load More Button -->
                <div class="text-center mt-4">
                    <button class="btn btn-outline-primary" onclick="loadMoreExtensions()">
                        <i class="fas fa-chevron-down me-1"></i> Load More Extensions
                    </button>
                </div>

                <!-- Extension Modal -->
                <div class="modal fade" id="extensionModal" tabindex="-1">
                    <div class="modal-dialog modal-xl">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    <i class="fas fa-phone me-2"></i>
                                    <span id="modalTitle">Add New Extension</span>
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="extension-form">
                                    <form id="extensionForm">
                                        <!-- Basic Information -->
                                        <div class="form-section">
                                            <h6><i class="fas fa-info-circle me-2"></i>Basic Information</h6>
                                            <div class="row">
                                                <div class="col-md-3">
                                                    <label class="form-label">Extension Number *</label>
                                                    <input type="number" class="form-control" id="extensionNumber" min="1000" max="9999" required>
                                                    <div class="form-text">4-digit extension number</div>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">Display Name *</label>
                                                    <input type="text" class="form-control" id="displayName" placeholder="e.g., John Smith" required>
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label">Username *</label>
                                                    <input type="text" class="form-control" id="username" placeholder="e.g., jsmith" required>
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label">Department</label>
                                                    <select class="form-select" id="department">
                                                        <option value="sales">Sales</option>
                                                        <option value="support">Support</option>
                                                        <option value="admin">Administration</option>
                                                        <option value="conference">Conference</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Authentication -->
                                        <div class="form-section">
                                            <h6><i class="fas fa-lock me-2"></i>Authentication</h6>
                                            <div class="row">
                                                <div class="col-md-4">
                                                    <label class="form-label">Password *</label>
                                                    <div class="input-group">
                                                        <input type="password" class="form-control" id="password" required onkeyup="checkPasswordStrength()">
                                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('password')">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <button class="btn btn-outline-primary" type="button" onclick="generatePassword()">
                                                            <i class="fas fa-key"></i>
                                                        </button>
                                                    </div>
                                                    <div class="password-strength" id="passwordStrength"></div>
                                                    <small class="form-text" id="passwordHelp">Password strength: Weak</small>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">Email Address</label>
                                                    <input type="email" class="form-control" id="emailAddress" placeholder="user@company.com">
                                                    <small class="form-text text-muted">For voicemail notifications</small>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">Mobile Number</label>
                                                    <input type="tel" class="form-control" id="mobileNumber" placeholder="+1 (555) 123-4567">
                                                    <small class="form-text text-muted">For call forwarding</small>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Device Settings -->
                                        <div class="form-section">
                                            <h6><i class="fas fa-phone-alt me-2"></i>Device & Audio Settings</h6>
                                            <div class="row">
                                                <div class="col-md-4">
                                                    <label class="form-label">Device Type</label>
                                                    <select class="form-select" id="deviceType">
                                                        <option value="sip_client">SIP Client</option>
                                                        <option value="desk_phone">Desk Phone</option>
                                                        <option value="mobile_app">Mobile App</option>
                                                        <option value="softphone">Softphone</option>
                                                        <option value="conference">Conference Room</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">Preferred Codec</label>
                                                    <select class="form-select" id="preferredCodec">
                                                        <option value="g722">G.722 (HD Audio)</option>
                                                        <option value="ulaw">G.711u (PCMU)</option>
                                                        <option value="alaw">G.711a (PCMA)</option>
                                                        <option value="g729">G.729 (Low Bandwidth)</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">DTMF Method</label>
                                                    <select class="form-select" id="dtmfMethod">
                                                        <option value="rfc2833">RFC2833 (Recommended)</option>
                                                        <option value="info">SIP INFO</option>
                                                        <option value="inband">In-band</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Features & Permissions -->
                                        <div class="form-section">
                                            <h6><i class="fas fa-cogs me-2"></i>Features & Permissions</h6>
                                            <div class="row">
                                                <div class="col-md-4">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="allowOutbound" checked>
                                                        <label class="form-check-label" for="allowOutbound">Allow Outbound Calls</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="allowInternational">
                                                        <label class="form-check-label" for="allowInternational">Allow International Calls</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="allowConference" checked>
                                                        <label class="form-check-label" for="allowConference">Allow Conference Calls</label>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="enableVoicemail" checked>
                                                        <label class="form-check-label" for="enableVoicemail">Enable Voicemail</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="enableCallForwarding">
                                                        <label class="form-check-label" for="enableCallForwarding">Enable Call Forwarding</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="enableCallTransfer" checked>
                                                        <label class="form-check-label" for="enableCallTransfer">Enable Call Transfer</label>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="recordCalls">
                                                        <label class="form-check-label" for="recordCalls">Record All Calls</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="adminAccess">
                                                        <label class="form-check-label" for="adminAccess">Administrative Access</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="extensionEnabled" checked>
                                                        <label class="form-check-label" for="extensionEnabled">Extension Enabled</label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Voicemail Settings -->
                                        <div class="form-section">
                                            <h6><i class="fas fa-voicemail me-2"></i>Voicemail Settings</h6>
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <label class="form-label">Voicemail PIN</label>
                                                    <input type="password" class="form-control" id="voicemailPin" maxlength="6" placeholder="4-6 digit PIN">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Email Voicemail to</label>
                                                    <input type="email" class="form-control" id="voicemailEmail" placeholder="Same as extension email">
                                                </div>
                                            </div>
                                            <div class="row mt-3">
                                                <div class="col-md-12">
                                                    <label class="form-label">Voicemail Greeting</label>
                                                    <textarea class="form-control" id="voicemailGreeting" rows="2" placeholder="Custom voicemail greeting (leave blank for default)"></textarea>
                                                </div>
                                            </div>
                                            <div class="voicemail-preview mt-3" style="display: none;">
                                                <strong>Preview:</strong>
                                                <p class="mb-0" id="greetingPreview"></p>
                                            </div>
                                        </div>

                                        <!-- Call Forwarding Rules -->
                                        <div class="form-section">
                                            <h6><i class="fas fa-phone-square-alt me-2"></i>Call Forwarding Rules</h6>
                                            <div class="row">
                                                <div class="col-md-4">
                                                    <label class="form-label">Forward When Busy</label>
                                                    <select class="form-select" id="forwardBusy">
                                                        <option value="">No forwarding</option>
                                                        <option value="voicemail">Voicemail</option>
                                                        <option value="mobile">Mobile Number</option>
                                                        <option value="extension">Another Extension</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">Forward When No Answer</label>
                                                    <select class="form-select" id="forwardNoAnswer">
                                                        <option value="voicemail">Voicemail</option>
                                                        <option value="mobile">Mobile Number</option>
                                                        <option value="extension">Another Extension</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">Forward Destination</label>
                                                    <input type="text" class="form-control" id="forwardDestination" placeholder="Extension or phone number">
                                                </div>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="button" class="btn btn-warning" onclick="testExtension()">
                                    <i class="fas fa-stethoscope me-1"></i> Test Configuration
                                </button>
                                <button type="button" class="btn btn-primary" onclick="saveExtension()">
                                    <i class="fas fa-save me-1"></i> Save Extension
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Extension Details Modal -->
                <div class="modal fade" id="extensionDetailsModal" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Extension Details - <span id="detailsExtensionNumber">2001</span>
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <ul class="nav nav-tabs" id="detailsTabs">
                                    <li class="nav-item">
                                        <a class="nav-link active" data-bs-toggle="tab" href="#detailsOverview">Overview</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" data-bs-toggle="tab" href="#detailsCallHistory">Call History</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" data-bs-toggle="tab" href="#detailsVoicemail">Voicemail</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" data-bs-toggle="tab" href="#detailsStatistics">Statistics</a>
                                    </li>
                                </ul>

                                <div class="tab-content mt-3">
                                    <div class="tab-pane fade show active" id="detailsOverview">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <h6>Extension Information</h6>
                                                <table class="table table-sm">
                                                    <tr><td><strong>Extension:</strong></td><td>2001</td></tr>
                                                    <tr><td><strong>Name:</strong></td><td>Senior Tech Support</td></tr>
                                                    <tr><td><strong>Username:</strong></td><td>techsupport1</td></tr>
                                                    <tr><td><strong>Department:</strong></td><td>Support</td></tr>
                                                    <tr><td><strong>Device Type:</strong></td><td>SIP Client</td></tr>
                                                    <tr><td><strong>Status:</strong></td><td><span class="badge bg-warning">Busy</span></td></tr>
                                                </table>
                                            </div>
                                            <div class="col-md-6">
                                                <h6>Current Status</h6>
                                                <div class="alert alert-warning">
                                                    <i class="fas fa-phone-alt me-2"></i>
                                                    <strong>Currently on call</strong><br>
                                                    <small>Call started: 14:23:15<br>Duration: 12 minutes 34 seconds<br>Caller: +1 (555) 123-4567</small>
                                                </div>
                                                <div class="d-grid gap-2">
                                                    <button class="btn btn-outline-danger" onclick="hangupCall('2001')">
                                                        <i class="fas fa-phone-slash me-1"></i> Hang Up Call
                                                    </button>
                                                    <button class="btn btn-outline-info" onclick="monitorCall('2001')">
                                                        <i class="fas fa-headphones me-1"></i> Monitor Call
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="tab-pane fade" id="detailsCallHistory">
                                        <h6>Recent Call History</h6>
                                        <div id="callHistoryList">
                                            <div class="call-history-item">
                                                <div class="d-flex justify-content-between">
                                                    <div>
                                                        <strong>+1 (555) 123-4567</strong>
                                                        <span class="badge bg-success ms-2">Inbound</span>
                                                    </div>
                                                    <small class="text-muted">Oct 13, 2:23 PM</small>
                                                </div>
                                                <div class="text-muted">Duration: 12m 34s • Status: In Progress</div>
                                            </div>
                                            <div class="call-history-item">
                                                <div class="d-flex justify-content-between">
                                                    <div>
                                                        <strong>+1 (555) 987-6543</strong>
                                                        <span class="badge bg-primary ms-2">Outbound</span>
                                                    </div>
                                                    <small class="text-muted">Oct 13, 1:45 PM</small>
                                                </div>
                                                <div class="text-muted">Duration: 8m 22s • Status: Completed</div>
                                            </div>
                                            <div class="call-history-item">
                                                <div class="d-flex justify-content-between">
                                                    <div>
                                                        <strong>1003 (Sales Rep 3)</strong>
                                                        <span class="badge bg-info ms-2">Internal</span>
                                                    </div>
                                                    <small class="text-muted">Oct 13, 1:20 PM</small>
                                                </div>
                                                <div class="text-muted">Duration: 3m 45s • Status: Completed</div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="tab-pane fade" id="detailsVoicemail">
                                        <h6>Voicemail Messages</h6>
                                        <div class="alert alert-success">
                                            <i class="fas fa-check me-2"></i>
                                            No new voicemail messages
                                        </div>
                                    </div>

                                    <div class="tab-pane fade" id="detailsStatistics">
                                        <h6>Call Statistics (Last 30 Days)</h6>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <table class="table table-sm">
                                                    <tr><td>Total Calls:</td><td><strong>847</strong></td></tr>
                                                    <tr><td>Inbound Calls:</td><td><strong>623</strong></td></tr>
                                                    <tr><td>Outbound Calls:</td><td><strong>224</strong></td></tr>
                                                    <tr><td>Missed Calls:</td><td><strong>12</strong></td></tr>
                                                </table>
                                            </div>
                                            <div class="col-md-6">
                                                <table class="table table-sm">
                                                    <tr><td>Total Talk Time:</td><td><strong>127h 34m</strong></td></tr>
                                                    <tr><td>Average Call Length:</td><td><strong>9m 12s</strong></td></tr>
                                                    <tr><td>Answer Rate:</td><td><strong>98.6%</strong></td></tr>
                                                    <tr><td>Rating:</td><td><strong>4.8/5.0 ⭐</strong></td></tr>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="flexpbx-dynamic-ui.js"></script>
    <script>
        // Extension management functions
        let selectedExtensions = [];

        function addNewExtension() {
            document.getElementById('modalTitle').textContent = 'Add New Extension';
            document.getElementById('extensionForm').reset();
            new bootstrap.Modal(document.getElementById('extensionModal')).show();
        }

        function editExtension(extensionId) {
            document.getElementById('modalTitle').textContent = 'Edit Extension ' + extensionId;

            // Load extension data using new comprehensive API
            fetch(`/api/extensions.php?path=details&id=${extensionId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        populateExtensionForm(data.extension);
                        new bootstrap.Modal(document.getElementById('extensionModal')).show();
                    }
                });
        }

        function viewDetails(extensionId) {
            document.getElementById('detailsExtensionNumber').textContent = extensionId;

            // Load detailed extension information using new comprehensive API
            fetch(`/api/extensions.php?path=details&id=${extensionId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        populateDetailsModal(data);
                        new bootstrap.Modal(document.getElementById('extensionDetailsModal')).show();
                    }
                });
        }

        function callExtension(extensionId) {
            // Initiate call to extension
            fetch('/api/extensions/call', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ extension: extensionId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`Calling extension ${extensionId}...`);
                } else {
                    alert('Failed to initiate call: ' + data.error);
                }
            });
        }

        function saveExtension() {
            const formData = new FormData(document.getElementById('extensionForm'));
            const extensionData = Object.fromEntries(formData.entries());

            // Collect checkbox values
            extensionData.permissions = {
                allowOutbound: document.getElementById('allowOutbound').checked,
                allowInternational: document.getElementById('allowInternational').checked,
                allowConference: document.getElementById('allowConference').checked,
                enableVoicemail: document.getElementById('enableVoicemail').checked,
                enableCallForwarding: document.getElementById('enableCallForwarding').checked,
                enableCallTransfer: document.getElementById('enableCallTransfer').checked,
                recordCalls: document.getElementById('recordCalls').checked,
                adminAccess: document.getElementById('adminAccess').checked,
                extensionEnabled: document.getElementById('extensionEnabled').checked
            };

            // Use new comprehensive API
            const extensionId = document.getElementById('extensionNumber').value;
            const isUpdate = document.getElementById('modalTitle').textContent.includes('Edit');
            const apiPath = isUpdate ? `update&id=${extensionId}` : 'create';

            fetch(`/api/extensions.php?path=${apiPath}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(extensionData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('extensionModal')).hide();
                    location.reload();
                } else {
                    alert('Error saving extension: ' + data.error);
                }
            });
        }

        function testExtension() {
            const extensionNumber = document.getElementById('extensionNumber').value;
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;

            if (!extensionNumber || !username || !password) {
                alert('Please fill in extension number, username, and password for testing.');
                return;
            }

            // Test extension configuration
            fetch('/api/extensions/test', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    extension: extensionNumber,
                    username: username,
                    password: password
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Extension configuration test passed!');
                } else {
                    alert('Extension test failed: ' + data.error);
                }
            });
        }

        function generatePassword() {
            const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%';
            let password = '';
            for (let i = 0; i < 12; i++) {
                password += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            document.getElementById('password').value = password;
            checkPasswordStrength();
        }

        function checkPasswordStrength() {
            const password = document.getElementById('password').value;
            const strengthBar = document.getElementById('passwordStrength');
            const strengthText = document.getElementById('passwordHelp');

            let strength = 0;
            if (password.length >= 8) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[a-z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;

            const levels = ['weak', 'fair', 'good', 'strong'];
            const colors = ['#dc3545', '#ffc107', '#17a2b8', '#28a745'];
            const level = Math.min(strength - 1, 3);

            if (level >= 0) {
                strengthBar.className = `password-strength strength-${levels[level]}`;
                strengthText.textContent = `Password strength: ${levels[level].charAt(0).toUpperCase() + levels[level].slice(1)}`;
            }
        }

        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = event.target.closest('button').querySelector('i');

            if (field.type === 'password') {
                field.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                field.type = 'password';
                icon.className = 'fas fa-eye';
            }
        }

        function filterExtensions() {
            const search = document.getElementById('searchExtensions').value.toLowerCase();
            const department = document.getElementById('filterDepartment').value;
            const status = document.getElementById('filterStatus').value;

            const cards = document.querySelectorAll('.extension-card');
            cards.forEach(card => {
                const cardText = card.textContent.toLowerCase();
                const cardDepartment = card.dataset.department;
                const cardStatus = card.dataset.status;

                const matchesSearch = cardText.includes(search);
                const matchesDepartment = !department || cardDepartment === department;
                const matchesStatus = !status || cardStatus === status;

                card.style.display = matchesSearch && matchesDepartment && matchesStatus ? 'block' : 'none';
            });
        }

        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAll').checked;
            const checkboxes = document.querySelectorAll('.extension-checkbox');

            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll;
            });

            updateBulkActions();
        }

        function updateBulkActions() {
            const checkedBoxes = document.querySelectorAll('.extension-checkbox:checked');
            const bulkActions = document.getElementById('bulkActions');
            const selectedCount = document.getElementById('selectedCount');

            selectedExtensions = Array.from(checkedBoxes).map(cb => cb.value);
            selectedCount.textContent = selectedExtensions.length;

            bulkActions.style.display = selectedExtensions.length > 0 ? 'block' : 'none';
        }

        // Add event listeners to checkboxes
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('extension-checkbox')) {
                updateBulkActions();
            }
        });

        function bulkEdit() {
            if (selectedExtensions.length === 0) return;

            // Show bulk edit modal with selected extensions
            alert(`Bulk editing ${selectedExtensions.length} extensions: ${selectedExtensions.join(', ')}`);
        }

        function bulkEnable() {
            if (selectedExtensions.length === 0) return;

            fetch('/api/extensions.php?path=bulk_enable', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ extensions: selectedExtensions })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Bulk enable failed: ' + data.error);
                }
            });
        }

        function bulkDisable() {
            if (selectedExtensions.length === 0) return;

            fetch('/api/extensions.php?path=bulk_disable', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ extensions: selectedExtensions })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Bulk disable failed: ' + data.error);
                }
            });
        }

        function bulkDelete() {
            if (selectedExtensions.length === 0) return;

            if (confirm(`Are you sure you want to delete ${selectedExtensions.length} extensions? This action cannot be undone.`)) {
                fetch('/api/extensions.php?path=bulk_delete', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ extensions: selectedExtensions })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Bulk delete failed: ' + data.error);
                    }
                });
            }
        }

        function loadMoreExtensions() {
            // Load additional extensions using new comprehensive API
            const offset = document.querySelectorAll('.extension-card').length;
            fetch(`/api/extensions.php?path=list&offset=${offset}`)
                .then(response => response.json())
                .then(data => {
                    if (data.extensions && data.extensions.length > 0) {
                        // Add new extension cards to the grid
                        // Implementation would go here
                    }
                });
        }

        // Real-time updates every 10 seconds
        setInterval(function() {
            fetch('/api/extensions.php?path=status')
                .then(response => response.json())
                .then(data => {
                    // Update extension status indicators
                    if (data.updates) {
                        data.updates.forEach(update => {
                            updateExtensionStatus(update.extension, update.status);
                        });
                    }
                });
        }, 10000);

        function updateExtensionStatus(extensionId, status) {
            const card = document.querySelector(`[data-extension="${extensionId}"]`);
            if (card) {
                const statusIcon = card.querySelector('.fas.fa-circle');
                statusIcon.className = `fas fa-circle status-${status}`;
                card.dataset.status = status;
            }
        }
    </script>
</body>
</html>