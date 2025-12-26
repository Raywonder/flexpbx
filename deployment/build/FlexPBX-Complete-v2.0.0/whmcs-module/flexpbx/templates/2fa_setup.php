<?php
if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

// Process 2FA setup form
if ($_POST['action'] == 'setup_2fa') {
    $panel_type = $_POST['panel_type'];
    $server_url = $_POST['server_url'];
    $username = $_POST['username'];
    $password = $_POST['password'];
    $tfa_secret = $_POST['tfa_secret'];

    // Test configuration
    $test_result = test_2fa_configuration($_POST);

    if ($test_result['success']) {
        // Save 2FA configuration
        $config_data = json_encode([
            'panel_type' => $panel_type,
            'server_url' => $server_url,
            'username' => $username,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'tfa_secret' => $tfa_secret,
            'enabled' => true,
            'setup_date' => date('Y-m-d H:i:s')
        ]);

        Capsule::table('tbladdonmodules')->updateOrInsert(
            ['module' => 'flexpbx', 'setting' => '2fa_config'],
            ['value' => $config_data]
        );

        $success_message = "2FA configuration saved successfully!";
    } else {
        $error_message = $test_result['message'];
    }
}

// Get current 2FA configuration
$current_config = Capsule::table('tbladdonmodules')
    ->where('module', 'flexpbx')
    ->where('setting', '2fa_config')
    ->first();

$config_data = $current_config ? json_decode($current_config->value, true) : [];
?>

<div class="row">
    <div class="col-md-12">
        <h2><i class="fa fa-shield"></i> Two-Factor Authentication Setup</h2>
        <p>Configure 2FA integration with your control panel for enhanced security.</p>
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
                <h3 class="panel-title">2FA Configuration</h3>
            </div>
            <div class="panel-body">
                <form method="post" id="2fa-setup-form">
                    <input type="hidden" name="action" value="setup_2fa">

                    <div class="form-group">
                        <label for="panel_type">Control Panel Type</label>
                        <select name="panel_type" id="panel_type" class="form-control" required>
                            <option value="">Select Panel Type</option>
                            <option value="whmcs" <?php echo ($config_data['panel_type'] ?? '') == 'whmcs' ? 'selected' : ''; ?>>WHMCS</option>
                            <option value="cpanel" <?php echo ($config_data['panel_type'] ?? '') == 'cpanel' ? 'selected' : ''; ?>>cPanel</option>
                            <option value="whm" <?php echo ($config_data['panel_type'] ?? '') == 'whm' ? 'selected' : ''; ?>>WHM</option>
                            <option value="directadmin" <?php echo ($config_data['panel_type'] ?? '') == 'directadmin' ? 'selected' : ''; ?>>DirectAdmin</option>
                            <option value="plesk" <?php echo ($config_data['panel_type'] ?? '') == 'plesk' ? 'selected' : ''; ?>>Plesk</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="server_url">Server URL</label>
                        <input type="url" name="server_url" id="server_url" class="form-control"
                               value="<?php echo htmlspecialchars($config_data['server_url'] ?? ''); ?>"
                               placeholder="https://your-server.com" required>
                        <small class="help-block">Include protocol (http/https) and port if non-standard</small>
                    </div>

                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" name="username" id="username" class="form-control"
                               value="<?php echo htmlspecialchars($config_data['username'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" name="password" id="password" class="form-control" required>
                        <small class="help-block">Password will be encrypted before storage</small>
                    </div>

                    <div class="form-group">
                        <label for="tfa_secret">2FA Secret Key</label>
                        <input type="text" name="tfa_secret" id="tfa_secret" class="form-control"
                               value="<?php echo htmlspecialchars($config_data['tfa_secret'] ?? ''); ?>"
                               placeholder="Base32 encoded secret from your authenticator app">
                        <small class="help-block">Optional: For automatic TOTP generation</small>
                    </div>

                    <div class="form-group">
                        <button type="button" class="btn btn-info" onclick="test2FAConfig()">
                            <i class="fa fa-check"></i> Test Configuration
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-save"></i> Save Configuration
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="panel panel-info">
            <div class="panel-heading">
                <h3 class="panel-title">Setup Instructions</h3>
            </div>
            <div class="panel-body">
                <h4>1. Enable 2FA in Control Panel</h4>
                <p>First, enable two-factor authentication in your control panel and note down the secret key.</p>

                <h4>2. Configure FlexPBX</h4>
                <p>Enter your control panel details and 2FA secret in the form.</p>

                <h4>3. Test & Save</h4>
                <p>Use the "Test Configuration" button to verify your settings before saving.</p>

                <h4>Supported Panels</h4>
                <ul>
                    <li><strong>WHMCS:</strong> Admin area with 2FA</li>
                    <li><strong>cPanel:</strong> User/Reseller accounts</li>
                    <li><strong>WHM:</strong> Root/Reseller access</li>
                    <li><strong>DirectAdmin:</strong> Admin/Reseller/User</li>
                    <li><strong>Plesk:</strong> Administrator access</li>
                </ul>
            </div>
        </div>

        <?php if (!empty($config_data) && $config_data['enabled']): ?>
        <div class="panel panel-success">
            <div class="panel-heading">
                <h3 class="panel-title">Current Configuration</h3>
            </div>
            <div class="panel-body">
                <p><strong>Panel:</strong> <?php echo ucfirst($config_data['panel_type']); ?></p>
                <p><strong>Server:</strong> <?php echo htmlspecialchars($config_data['server_url']); ?></p>
                <p><strong>Username:</strong> <?php echo htmlspecialchars($config_data['username']); ?></p>
                <p><strong>Setup Date:</strong> <?php echo date('M j, Y', strtotime($config_data['setup_date'])); ?></p>
                <p><span class="label label-success">Configured</span></p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function test2FAConfig() {
    const form = document.getElementById('2fa-setup-form');
    const formData = new FormData(form);
    formData.append('ajax_action', 'test_2fa');

    const button = event.target;
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Testing...';
    button.disabled = true;

    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        button.innerHTML = originalText;
        button.disabled = false;

        if (data.success) {
            showAlert('success', 'Configuration test successful! ' + data.message);
        } else {
            showAlert('danger', 'Configuration test failed: ' + data.message);
        }
    })
    .catch(error => {
        button.innerHTML = originalText;
        button.disabled = false;
        showAlert('danger', 'Test failed: ' + error.message);
    });
}

function showAlert(type, message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible`;
    alertDiv.innerHTML = `
        <button type="button" class="close" data-dismiss="alert">&times;</button>
        <i class="fa fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'}"></i> ${message}
    `;

    const container = document.querySelector('.col-md-12');
    container.appendChild(alertDiv);

    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}
</script>

<style>
.panel-info .panel-heading {
    background-color: #d9edf7;
    border-color: #bce8f1;
}

.help-block {
    color: #737373;
    font-size: 12px;
}

.form-group {
    margin-bottom: 20px;
}

.alert {
    margin-bottom: 20px;
}
</style>