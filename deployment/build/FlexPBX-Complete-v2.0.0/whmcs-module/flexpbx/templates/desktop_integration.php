<?php
if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

// Process desktop integration request
if ($_POST['action'] == 'generate_token') {
    $result = generate_desktop_integration_token([
        'userid' => $_SESSION['adminid'],
        'panel_type' => 'whmcs',
        'server_url' => $_SERVER['HTTP_HOST'],
        'username' => $_SESSION['adminusername']
    ]);

    if ($result['success']) {
        $integration_token = $result['token'];
        $integration_url = $result['integration_url'];
        $expires_at = $result['expires_at'];
        $success_message = "Desktop integration token generated successfully!";
    } else {
        $error_message = $result['error'];
    }
}

// Get current admin user info
$admin_user = Capsule::table('tbladmins')->where('id', $_SESSION['adminid'])->first();
?>

<div class="row">
    <div class="col-md-12">
        <h2><i class="fa fa-desktop"></i> Desktop Application Integration</h2>
        <p>Connect your FlexPBX Desktop Application with this WHMCS installation for seamless management.</p>
    </div>
</div>

<?php if (isset($success_message)): ?>
<div class="alert alert-success">
    <i class="fa fa-check-circle"></i> <?php echo $success_message; ?>
</div>
<?php endif; ?>

<?php if (isset($error_message)): ?>
<div class="alert alert-danger">
    <i class="fa fa-exclamation-triangle"></i> <?php echo $error_message; ?>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-md-8">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title">Desktop App Connection</h3>
            </div>
            <div class="panel-body">
                <?php if (isset($integration_token)): ?>
                    <div class="well">
                        <h4><i class="fa fa-key"></i> Integration Token Generated</h4>
                        <p>Your integration token has been generated. Use one of the methods below to connect your desktop app:</p>

                        <div class="form-group">
                            <label>Integration Token:</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="integration-token"
                                       value="<?php echo htmlspecialchars($integration_token); ?>" readonly>
                                <span class="input-group-btn">
                                    <button class="btn btn-default" type="button" onclick="copyToken()">
                                        <i class="fa fa-copy"></i> Copy
                                    </button>
                                </span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Quick Connect URL:</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="integration-url"
                                       value="<?php echo htmlspecialchars($integration_url); ?>" readonly>
                                <span class="input-group-btn">
                                    <button class="btn btn-primary" type="button" onclick="openDesktopApp()">
                                        <i class="fa fa-external-link"></i> Open in Desktop App
                                    </button>
                                </span>
                            </div>
                        </div>

                        <div class="alert alert-info">
                            <i class="fa fa-info-circle"></i>
                            <strong>Note:</strong> This token expires on <?php echo date('M j, Y g:i A', strtotime($expires_at)); ?>
                        </div>

                        <div class="text-center">
                            <img id="qr-code" src="" alt="QR Code" style="display: none; max-width: 200px; margin: 20px auto;">
                            <br>
                            <button class="btn btn-info" onclick="generateQRCode()">
                                <i class="fa fa-qrcode"></i> Generate QR Code
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <form method="post">
                        <input type="hidden" name="action" value="generate_token">

                        <div class="text-center">
                            <h4>Connect FlexPBX Desktop Application</h4>
                            <p>Generate a secure token to connect your desktop application with this WHMCS installation.</p>

                            <div class="well text-left">
                                <h5><i class="fa fa-info-circle"></i> Connection Details</h5>
                                <p><strong>Server:</strong> <?php echo $_SERVER['HTTP_HOST']; ?></p>
                                <p><strong>Admin User:</strong> <?php echo htmlspecialchars($admin_user->username); ?></p>
                                <p><strong>2FA Status:</strong>
                                    <?php if (get_module_option('enable_2fa') == 'on'): ?>
                                        <span class="label label-success">Enabled</span>
                                    <?php else: ?>
                                        <span class="label label-warning">Disabled</span>
                                    <?php endif; ?>
                                </p>
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fa fa-key"></i> Generate Integration Token
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="panel panel-info">
            <div class="panel-heading">
                <h3 class="panel-title">WebUI Integration</h3>
            </div>
            <div class="panel-body">
                <h4>Embedded Web Interface</h4>
                <p>Access FlexPBX management directly within your desktop application:</p>

                <div class="form-group">
                    <label>FlexPBX Server WebUI URL:</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="webui-url"
                               value="<?php echo get_module_option('server_url'); ?>" readonly>
                        <span class="input-group-btn">
                            <button class="btn btn-success" type="button" onclick="openWebUI()">
                                <i class="fa fa-external-link"></i> Open WebUI
                            </button>
                        </span>
                    </div>
                </div>

                <div class="checkbox">
                    <label>
                        <input type="checkbox" id="enable-sso"> Enable Single Sign-On (SSO)
                    </label>
                </div>

                <div class="checkbox">
                    <label>
                        <input type="checkbox" id="embed-webui" checked> Embed WebUI in Desktop App
                    </label>
                </div>

                <button class="btn btn-default" onclick="testWebUIConnection()">
                    <i class="fa fa-check"></i> Test WebUI Connection
                </button>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="panel panel-success">
            <div class="panel-heading">
                <h3 class="panel-title">Download Desktop App</h3>
            </div>
            <div class="panel-body text-center">
                <h4><i class="fa fa-download"></i> FlexPBX Desktop</h4>
                <p>Download the FlexPBX Desktop Application for your operating system:</p>

                <div class="btn-group-vertical" style="width: 100%;">
                    <a href="#" class="btn btn-primary" onclick="downloadDesktopApp('windows')">
                        <i class="fa fa-windows"></i> Windows (x64)
                    </a>
                    <a href="#" class="btn btn-primary" onclick="downloadDesktopApp('macos')">
                        <i class="fa fa-apple"></i> macOS (Universal)
                    </a>
                    <a href="#" class="btn btn-primary" onclick="downloadDesktopApp('linux')">
                        <i class="fa fa-linux"></i> Linux (AppImage)
                    </a>
                </div>

                <hr>

                <h5>System Requirements</h5>
                <ul class="list-unstyled text-left">
                    <li><strong>Windows:</strong> 10/11 (x64)</li>
                    <li><strong>macOS:</strong> 10.14+ (Intel/Apple Silicon)</li>
                    <li><strong>Linux:</strong> Ubuntu 18.04+ or equivalent</li>
                </ul>
            </div>
        </div>

        <div class="panel panel-info">
            <div class="panel-heading">
                <h3 class="panel-title">Setup Instructions</h3>
            </div>
            <div class="panel-body">
                <h4>1. Download & Install</h4>
                <p>Download and install the FlexPBX Desktop Application for your operating system.</p>

                <h4>2. Generate Token</h4>
                <p>Click "Generate Integration Token" to create a secure connection token.</p>

                <h4>3. Connect App</h4>
                <p>Use the generated token or QR code to connect your desktop app to this WHMCS installation.</p>

                <h4>4. Verify Connection</h4>
                <p>Test the connection and ensure all features are working properly.</p>

                <hr>

                <h5>Features</h5>
                <ul class="list-unstyled">
                    <li><i class="fa fa-check text-success"></i> 2FA Integration</li>
                    <li><i class="fa fa-check text-success"></i> Account Management</li>
                    <li><i class="fa fa-check text-success"></i> Extension Provisioning</li>
                    <li><i class="fa fa-check text-success"></i> Real-time Statistics</li>
                    <li><i class="fa fa-check text-success"></i> Audio Streaming</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
function copyToken() {
    const tokenInput = document.getElementById('integration-token');
    tokenInput.select();
    document.execCommand('copy');

    showAlert('success', 'Integration token copied to clipboard!');
}

function openDesktopApp() {
    const url = document.getElementById('integration-url').value;
    window.location.href = url;
}

function generateQRCode() {
    const url = document.getElementById('integration-url').value;
    const qrCodeImg = document.getElementById('qr-code');

    // Use a QR code generation service
    const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(url)}`;
    qrCodeImg.src = qrUrl;
    qrCodeImg.style.display = 'block';
}

function openWebUI() {
    const url = document.getElementById('webui-url').value;
    window.open(url, '_blank');
}

function testWebUIConnection() {
    const url = document.getElementById('webui-url').value;
    const button = event.target;
    const originalText = button.innerHTML;

    button.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Testing...';
    button.disabled = true;

    // Test connection to WebUI
    fetch(url + '/health', { mode: 'no-cors' })
        .then(() => {
            button.innerHTML = originalText;
            button.disabled = false;
            showAlert('success', 'WebUI connection successful!');
        })
        .catch(() => {
            button.innerHTML = originalText;
            button.disabled = false;
            showAlert('warning', 'WebUI connection test completed (CORS limitations may prevent detailed results)');
        });
}

function downloadDesktopApp(platform) {
    // In a real implementation, these would link to actual download URLs
    const downloadUrls = {
        'windows': '/downloads/FlexPBX-Desktop-Windows.exe',
        'macos': '/downloads/FlexPBX-Desktop-macOS.dmg',
        'linux': '/downloads/FlexPBX-Desktop-Linux.AppImage'
    };

    const url = downloadUrls[platform];
    if (url) {
        window.open(url, '_blank');
    } else {
        showAlert('info', 'Download link will be available soon for ' + platform);
    }
}

function showAlert(type, message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible`;
    alertDiv.innerHTML = `
        <button type="button" class="close" data-dismiss="alert">&times;</button>
        <i class="fa fa-${type === 'success' ? 'check-circle' : (type === 'warning' ? 'exclamation-triangle' : 'info-circle')}"></i> ${message}
    `;

    const container = document.querySelector('.col-md-12');
    container.appendChild(alertDiv);

    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}

// Auto-generate QR code if token exists
document.addEventListener('DOMContentLoaded', function() {
    const tokenInput = document.getElementById('integration-token');
    if (tokenInput && tokenInput.value) {
        // Auto-generate QR code after a short delay
        setTimeout(generateQRCode, 1000);
    }
});
</script>

<style>
.btn-group-vertical .btn {
    margin-bottom: 10px;
}

.well {
    background-color: #f5f5f5;
    border: 1px solid #e3e3e3;
    border-radius: 4px;
    padding: 19px;
    margin-bottom: 20px;
}

.input-group {
    margin-bottom: 15px;
}

#qr-code {
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 10px;
    background: white;
}

.panel-success .panel-heading {
    background-color: #dff0d8;
    border-color: #d6e9c6;
}

.text-success {
    color: #3c763d;
}
</style>