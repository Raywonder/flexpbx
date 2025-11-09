<?php
/**
 * FlexPBX Maintenance Mode Control Panel
 * Admin interface for managing global site maintenance mode
 *
 * @version 1.0.0
 * @date 2025-11-05
 */

require_once __DIR__ . '/admin_auth_check.php';

// Database connection
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/maintenance_check.php';

// Set page title for header
$page_title = 'Maintenance Mode Control - FlexPBX Admin';

// Initialize maintenance mode handler
try {
    $maintenance = new MaintenanceMode($pdo);
} catch (Exception $e) {
    die("Error initializing maintenance system: " . $e->getMessage());
}

// Handle AJAX requests
if (isset($_POST['action']) && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');

    $response = ['success' => false, 'message' => ''];

    switch ($_POST['action']) {
        case 'toggle_maintenance':
            $new_state = isset($_POST['enable']) && $_POST['enable'] === 'true';
            if ($new_state) {
                $maintenance->enable($_SESSION['admin_username'] ?? 'admin');
                $response = ['success' => true, 'message' => 'Maintenance mode enabled', 'state' => true];
            } else {
                $maintenance->disable();
                $response = ['success' => true, 'message' => 'Maintenance mode disabled', 'state' => false];
            }
            break;

        case 'update_settings':
            $settings = [
                'maintenance_message' => $_POST['maintenance_message'] ?? '',
                'maintenance_title' => $_POST['maintenance_title'] ?? '',
                'allow_api_access' => isset($_POST['allow_api_access']) ? 1 : 0,
                'allow_user_portal_limited' => isset($_POST['allow_user_portal_limited']) ? 1 : 0
            ];

            if ($maintenance->updateSettings($settings)) {
                $response = ['success' => true, 'message' => 'Settings updated successfully'];
            } else {
                $response = ['success' => false, 'message' => 'Failed to update settings'];
            }
            break;

        case 'get_status':
            $response = [
                'success' => true,
                'is_active' => $maintenance->isActive(),
                'allow_api' => $maintenance->allowApiAccess(),
                'allow_portal' => $maintenance->allowUserPortalAccess(),
                'message' => $maintenance->getMaintenanceMessage(),
                'title' => $maintenance->getMaintenanceTitle()
            ];
            break;
    }

    echo json_encode($response);
    exit;
}

// Get current status
$is_active = $maintenance->isActive();
$current_message = $maintenance->getMaintenanceMessage();
$current_title = $maintenance->getMaintenanceTitle();
$allow_api = $maintenance->allowApiAccess();
$allow_portal = $maintenance->allowUserPortalAccess();

// Include the admin header
require_once __DIR__ . '/includes/admin_header.php';
?>

<style>
    .maintenance-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
    }

    .maintenance-card {
        background: white;
        border-radius: 12px;
        padding: 30px;
        margin-bottom: 20px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.1);
    }

    .status-banner {
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 30px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        transition: all 0.3s ease;
    }

    .status-banner.active {
        background: #ff6b6b;
        color: white;
    }

    .status-banner.inactive {
        background: #4caf50;
        color: white;
    }

    .status-indicator {
        display: inline-block;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        margin-right: 10px;
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }

    .toggle-btn {
        padding: 12px 30px;
        border: none;
        border-radius: 25px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 16px;
    }

    .toggle-btn.enable {
        background: #ff6b6b;
        color: white;
    }

    .toggle-btn.disable {
        background: #4caf50;
        color: white;
    }

    .toggle-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #333;
    }

    .form-group input[type="text"],
    .form-group textarea {
        width: 100%;
        padding: 12px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-size: 14px;
        transition: border-color 0.3s ease;
    }

    .form-group input[type="text"]:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #667eea;
    }

    .form-group textarea {
        min-height: 100px;
        resize: vertical;
    }

    .checkbox-group {
        display: flex;
        align-items: center;
        padding: 15px;
        background: #f7f9fc;
        border-radius: 8px;
        margin-bottom: 15px;
    }

    .checkbox-group input[type="checkbox"] {
        width: 20px;
        height: 20px;
        margin-right: 10px;
        cursor: pointer;
    }

    .checkbox-group label {
        margin: 0;
        cursor: pointer;
        flex: 1;
    }

    .checkbox-group .description {
        font-size: 12px;
        color: #666;
        margin-top: 5px;
    }

    .save-btn {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 12px 40px;
        border: none;
        border-radius: 25px;
        font-weight: 600;
        cursor: pointer;
        font-size: 16px;
        transition: all 0.3s ease;
    }

    .save-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
    }

    .alert {
        padding: 15px 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        display: none;
    }

    .alert.success {
        background: #d4edda;
        border: 1px solid #c3e6cb;
        color: #155724;
    }

    .alert.error {
        background: #f8d7da;
        border: 1px solid #f5c6cb;
        color: #721c24;
    }

    .alert.show {
        display: block;
        animation: slideDown 0.3s ease;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .info-box {
        background: #e3f2fd;
        border-left: 4px solid #2196f3;
        padding: 15px;
        border-radius: 5px;
        margin-top: 20px;
    }

    .info-box h4 {
        color: #1976d2;
        margin-bottom: 10px;
    }

    .info-box ul {
        margin-left: 20px;
        color: #555;
    }

    .info-box li {
        margin-bottom: 5px;
    }
</style>

<div class="maintenance-container">
    <!-- Status Banner -->
    <div class="status-banner <?php echo $is_active ? 'active' : 'inactive'; ?>" id="statusBanner">
        <div>
            <span class="status-indicator"></span>
            <strong>Maintenance Mode Status:</strong>
            <span id="statusText"><?php echo $is_active ? 'ACTIVE' : 'INACTIVE'; ?></span>
        </div>
        <button class="toggle-btn <?php echo $is_active ? 'disable' : 'enable'; ?>" id="toggleBtn" onclick="toggleMaintenance()">
            <?php echo $is_active ? 'Disable Maintenance Mode' : 'Enable Maintenance Mode'; ?>
        </button>
    </div>

    <!-- Alert Messages -->
    <div class="alert" id="alertBox"></div>

    <!-- Settings Card -->
    <div class="maintenance-card">
        <h2>Maintenance Mode Settings</h2>
        <p style="color: #666; margin-bottom: 30px;">Configure global site maintenance mode. Admins always have full access.</p>

        <form id="maintenanceForm" onsubmit="return saveSettings(event)">
            <div class="form-group">
                <label for="maintenanceTitle">Maintenance Page Title</label>
                <input type="text" id="maintenanceTitle" name="maintenance_title" value="<?php echo htmlspecialchars($current_title); ?>" required>
            </div>

            <div class="form-group">
                <label for="maintenanceMessage">Maintenance Message</label>
                <textarea id="maintenanceMessage" name="maintenance_message" required><?php echo htmlspecialchars($current_message); ?></textarea>
                <small style="color: #666;">This message will be displayed to users when maintenance mode is active.</small>
            </div>

            <h3 style="margin-top: 30px; margin-bottom: 15px;">Access Permissions</h3>

            <div class="checkbox-group">
                <input type="checkbox" id="allowApi" name="allow_api_access" <?php echo $allow_api ? 'checked' : ''; ?>>
                <label for="allowApi">
                    <strong>Allow API Access</strong>
                    <div class="description">External installations can still communicate via API during maintenance</div>
                </label>
            </div>

            <div class="checkbox-group">
                <input type="checkbox" id="allowPortal" name="allow_user_portal_limited" <?php echo $allow_portal ? 'checked' : ''; ?>>
                <label for="allowPortal">
                    <strong>Allow Limited User Portal Access</strong>
                    <div class="description">Logged-in users can access limited features in the user portal</div>
                </label>
            </div>

            <button type="submit" class="save-btn">Save Settings</button>
        </form>

        <div class="info-box">
            <h4>Access Rules During Maintenance:</h4>
            <ul>
                <li><strong>Administrators:</strong> Full access to all features</li>
                <li><strong>Users:</strong> No access (unless limited portal is enabled)</li>
                <li><strong>Guests:</strong> No access</li>
                <li><strong>API Clients:</strong> Access based on settings (default: allowed)</li>
            </ul>
        </div>
    </div>
</div>

<script>
function toggleMaintenance() {
    const isCurrentlyActive = document.getElementById('statusBanner').classList.contains('active');
    const enable = !isCurrentlyActive;

    if (!confirm(enable ? 'Are you sure you want to enable maintenance mode?' : 'Are you sure you want to disable maintenance mode?')) {
        return;
    }

    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: new URLSearchParams({
            action: 'toggle_maintenance',
            enable: enable
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update UI
            const banner = document.getElementById('statusBanner');
            const toggleBtn = document.getElementById('toggleBtn');
            const statusText = document.getElementById('statusText');

            if (data.state) {
                banner.classList.remove('inactive');
                banner.classList.add('active');
                toggleBtn.classList.remove('enable');
                toggleBtn.classList.add('disable');
                toggleBtn.textContent = 'Disable Maintenance Mode';
                statusText.textContent = 'ACTIVE';
            } else {
                banner.classList.remove('active');
                banner.classList.add('inactive');
                toggleBtn.classList.remove('disable');
                toggleBtn.classList.add('enable');
                toggleBtn.textContent = 'Enable Maintenance Mode';
                statusText.textContent = 'INACTIVE';
            }

            showAlert(data.message, 'success');
        } else {
            showAlert(data.message, 'error');
        }
    })
    .catch(error => {
        showAlert('Error: ' + error.message, 'error');
    });
}

function saveSettings(event) {
    event.preventDefault();

    const formData = new FormData(event.target);
    formData.append('action', 'update_settings');

    fetch('', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert(data.message, 'success');
        } else {
            showAlert(data.message, 'error');
        }
    })
    .catch(error => {
        showAlert('Error: ' + error.message, 'error');
    });

    return false;
}

function showAlert(message, type) {
    const alertBox = document.getElementById('alertBox');
    alertBox.textContent = message;
    alertBox.className = 'alert ' + type + ' show';

    setTimeout(() => {
        alertBox.classList.remove('show');
    }, 5000);
}
</script>

<?php
// Include the admin footer
require_once __DIR__ . '/includes/admin_footer.php';
?>
