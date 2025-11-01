<?php
/**
 * FlexPBX User Profile Settings
 * Allows users to update display name, request DIDs, and manage profile
 */

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_extension'])) {
    header('Location: index.php');
    exit;
}

$extension = $_SESSION['user_extension'];
$username = $_SESSION['username'] ?? 'User';

$success_message = '';
$error_message = '';

// Load user data from pjsip.conf
$pjsip_file = '/etc/asterisk/pjsip.conf';
$current_display_name = '';
$current_email = '';

if (file_exists($pjsip_file)) {
    $content = file_get_contents($pjsip_file);
    // Find the endpoint section for this extension
    $pattern = '/\[' . preg_quote($extension) . '\][^\[]*callerid="([^"]+)"/s';
    if (preg_match($pattern, $content, $matches)) {
        $current_display_name = $matches[1];
    }
}

// Load user email from voicemail.conf
$voicemail_file = '/etc/asterisk/voicemail.conf';
if (file_exists($voicemail_file)) {
    $vm_content = file_get_contents($voicemail_file);
    $pattern = '/^' . preg_quote($extension) . '\s*=>[^,]+,([^,]+),([^\n]+)/m';
    if (preg_match($pattern, $vm_content, $matches)) {
        $current_email = trim($matches[2]);
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $new_display_name = $_POST['display_name'] ?? '';
        $new_email = $_POST['email'] ?? '';

        if (empty($new_display_name)) {
            $error_message = "Display name cannot be empty";
        } elseif (!empty($new_email) && !filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Invalid email address";
        } else {
            // Save update request to pending updates directory
            $update_data = [
                'timestamp' => date('Y-m-d H:i:s'),
                'extension' => $extension,
                'username' => $username,
                'display_name' => htmlspecialchars($new_display_name),
                'email' => htmlspecialchars($new_email),
                'status' => 'pending'
            ];

            $updates_dir = '/home/flexpbxuser/profile_updates';
            @mkdir($updates_dir, 0755, true);
            $update_file = $updates_dir . '/profile_' . $extension . '_' . time() . '.json';
            file_put_contents($update_file, json_encode($update_data, JSON_PRETTY_PRINT));

            $success_message = "Profile update request submitted! An administrator will review it shortly.";
        }
    } elseif ($action === 'request_did') {
        $did_type = $_POST['did_type'] ?? '';
        $port_number = $_POST['port_number'] ?? '';
        $did_reason = $_POST['did_reason'] ?? '';

        if (empty($did_type)) {
            $error_message = "Please select a DID type";
        } else {
            // Save DID request
            $did_data = [
                'timestamp' => date('Y-m-d H:i:s'),
                'extension' => $extension,
                'username' => $username,
                'did_type' => htmlspecialchars($did_type),
                'port_number' => htmlspecialchars($port_number),
                'reason' => htmlspecialchars($did_reason),
                'status' => 'pending'
            ];

            $did_dir = '/home/flexpbxuser/did_requests';
            @mkdir($did_dir, 0755, true);
            $did_file = $did_dir . '/did_' . $extension . '_' . time() . '.json';
            file_put_contents($did_file, json_encode($did_data, JSON_PRETTY_PRINT));

            $success_message = "DID request submitted! We'll contact you shortly to complete the setup.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Settings - FlexPBX</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
        }
        .header {
            text-align: center;
            color: white;
            margin-bottom: 2rem;
        }
        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }
        .settings-box {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            margin-bottom: 1.5rem;
        }
        .settings-box h2 {
            color: #2c3e50;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #667eea;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #2c3e50;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
        }
        .form-group textarea {
            min-height: 80px;
            resize: vertical;
        }
        .form-group small {
            display: block;
            margin-top: 0.3rem;
            color: #666;
            font-size: 0.9rem;
        }
        .btn {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }
        .alert {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 4px;
        }
        .back-link {
            text-align: center;
            margin-top: 1rem;
        }
        .back-link a {
            color: white;
            text-decoration: none;
            font-weight: 600;
            background: rgba(255,255,255,0.2);
            padding: 0.5rem 1rem;
            border-radius: 6px;
            display: inline-block;
        }
        .back-link a:hover {
            background: rgba(255,255,255,0.3);
        }
        .current-value {
            background: #f8f9fa;
            padding: 0.5rem;
            border-radius: 4px;
            margin-top: 0.5rem;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⚙️ Profile Settings</h1>
            <p>Extension <?= htmlspecialchars($extension) ?> - <?= htmlspecialchars($username) ?></p>
        </div>

        <?php if ($success_message): ?>
        <div class="alert alert-success">
            ✓ <?= $success_message ?>
        </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
        <div class="alert alert-error">
            ⚠️ <?= $error_message ?>
        </div>
        <?php endif; ?>

        <!-- Profile Settings -->
        <div class="settings-box">
            <h2>📱 Profile Settings</h2>

            <div class="info-box">
                <strong>Current Display Name:</strong>
                <div class="current-value"><?= htmlspecialchars($current_display_name ?: 'Not set') ?></div>
            </div>

            <div class="info-box">
                <strong>Current Email:</strong>
                <div class="current-value"><?= htmlspecialchars($current_email ?: 'Not set') ?></div>
            </div>

            <form method="POST">
                <input type="hidden" name="action" value="update_profile">

                <div class="form-group">
                    <label for="display_name">New Display Name *</label>
                    <input type="text" id="display_name" name="display_name" value="<?= htmlspecialchars($current_display_name) ?>" required>
                    <small>Name shown on caller ID when you make calls</small>
                </div>

                <div class="form-group">
                    <label for="email">Email Address *</label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($current_email) ?>" required>
                    <small>For voicemail notifications and account updates</small>
                </div>

                <button type="submit" class="btn">Update Profile</button>
            </form>
        </div>

        <!-- Assigned DIDs Display -->
        <?php
        // Check for assigned DIDs in extensions.conf
        $extensions_file = '/etc/asterisk/extensions.conf';
        $assigned_dids = [];

        if (file_exists($extensions_file)) {
            $ext_content = file_get_contents($extensions_file);
            $pattern = '/exten\s*=>\s*(\d{10,}),.*Dial\(PJSIP\/' . preg_quote($extension) . '[,\)]/m';
            if (preg_match_all($pattern, $ext_content, $matches)) {
                $assigned_dids = $matches[1];
            }
        }

        // Load DID metadata
        $did_metadata_file = '/home/flexpbxuser/did_metadata.json';
        $did_metadata = [];
        if (file_exists($did_metadata_file)) {
            $did_metadata = json_decode(file_get_contents($did_metadata_file), true) ?: [];
        }
        ?>

        <!-- Assigned DIDs or No DID Message -->
        <div class="settings-box">
            <h2>📞 Your Assigned Phone Numbers</h2>

            <?php if (!empty($assigned_dids)): ?>
                <?php foreach ($assigned_dids as $did):
                    $metadata = $did_metadata[$did] ?? null;
                    $provider = $metadata['provider'] ?? 'Unknown Provider';
                    $type = $metadata['type'] ?? 'direct';
                    $cost = $metadata['monthly_cost'] ?? null;
                    $notes = $metadata['notes'] ?? '';
                ?>
                <div class="info-box" style="background: #d4edda; border-left-color: #28a745;">
                    <div style="display: flex; justify-content: space-between; align-items: start;">
                        <div style="flex: 1;">
                            <strong>✓ Active DID:</strong>
                            <div class="current-value" style="font-size: 1.2rem; color: #155724; margin: 0.3rem 0;">
                                <?= htmlspecialchars($did) ?>
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <span style="background: #28a745; color: white; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.85rem; font-weight: 600;">
                                <?= htmlspecialchars($provider) ?>
                            </span>
                        </div>
                    </div>
                    <div style="margin-top: 0.5rem; padding-top: 0.5rem; border-top: 1px solid #c3e6cb;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; font-size: 0.9rem;">
                            <div>
                                <strong>Type:</strong> <?= $type === 'forwarded' ? '📞 Forwarded' : '📱 Direct' ?>
                            </div>
                            <div>
                                <strong>Assigned to:</strong> Extension <?= htmlspecialchars($extension) ?>
                            </div>
                            <?php if ($cost !== null): ?>
                            <div>
                                <strong>Monthly Cost:</strong> $<?= number_format($cost, 2) ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php if ($notes): ?>
                        <div style="margin-top: 0.5rem; color: #666; font-size: 0.85rem;">
                            <em><?= htmlspecialchars($notes) ?></em>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="info-box" style="background: #f8f9fa; border-left-color: #6c757d;">
                    <strong>ℹ️ No phone numbers assigned</strong>
                    <p style="margin: 0.5rem 0 0 0; color: #666;">
                        Your account does not have any dedicated phone numbers (DIDs) assigned yet.
                        You can request one using the form below.
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <!-- DID Request -->
        <div class="settings-box">
            <h2>📞 Request Direct Inward Dial (DID) Number</h2>

            <div class="info-box">
                <strong>What is a DID?</strong><br>
                A DID (Direct Inward Dial) number is a dedicated phone number that routes directly to your extension. This allows external callers to reach you without going through a receptionist or menu.
            </div>

            <div class="info-box" style="background: #fff3cd; border-left-color: #ffc107;">
                <strong>⚠️ Important Pricing Information:</strong><br>
                • DID numbers typically have monthly fees (usually $1-5/month)<br>
                • Porting existing numbers may have one-time setup fees<br>
                • Some promotional or free DIDs may be available - contact admin<br>
                • Final pricing will be confirmed before activation
            </div>

            <form method="POST">
                <input type="hidden" name="action" value="request_did">

                <div class="form-group">
                    <label for="did_type">DID Type *</label>
                    <select id="did_type" name="did_type" required>
                        <option value="">Select an option</option>
                        <option value="request_new">Request new phone number</option>
                        <option value="port_existing">Port existing number I own</option>
                    </select>
                    <small>Choose whether you want a new number or to port an existing one</small>
                </div>

                <div class="form-group" id="port_number_group" style="display: none;">
                    <label for="port_number">Number to Port</label>
                    <input type="tel" id="port_number" name="port_number" placeholder="e.g., (555) 123-4567">
                    <small>Enter the phone number you want to port (10 digits)</small>
                </div>

                <div class="form-group">
                    <label for="did_reason">Reason for DID Request</label>
                    <textarea id="did_reason" name="did_reason" placeholder="Explain why you need a dedicated phone number..."></textarea>
                    <small>Helps us prioritize and process your request</small>
                </div>

                <button type="submit" class="btn">Submit DID Request</button>
            </form>
        </div>

        <div class="back-link">
            <a href="dashboard.php">← Back to Dashboard</a>
        </div>
    </div>

    <script>
        // Show/hide port number field based on selection
        document.getElementById('did_type').addEventListener('change', function() {
            const portGroup = document.getElementById('port_number_group');
            if (this.value === 'port_existing') {
                portGroup.style.display = 'block';
                document.getElementById('port_number').required = true;
            } else {
                portGroup.style.display = 'none';
                document.getElementById('port_number').required = false;
            }
        });
    </script>
</body>
</html>
