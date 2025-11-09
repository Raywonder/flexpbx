<?php
/**
 * FlexPBX User Sign-Up
 * Allow new users to request extension accounts
 */

session_start();

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $extension_requested = $_POST['extension'] ?? '';
    $display_name = $_POST['display_name'] ?? '';
    $did_requested = $_POST['did_requested'] ?? '';
    $did_own_number = $_POST['did_own_number'] ?? '';
    $extension_category = $_POST['extension_category'] ?? '';
    $extension_purpose = $_POST['extension_purpose'] ?? '';
    $reason = $_POST['reason'] ?? '';

    // Validate input
    if (empty($username) || empty($email) || empty($extension_requested)) {
        $error_message = "Please fill in all required fields";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email address";
    } elseif (!preg_match('/^[0-9]{4}$/', $extension_requested)) {
        $error_message = "Extension must be 4 digits";
    } else {
        // Save signup request to file
        $signup_data = [
            'timestamp' => date('Y-m-d H:i:s'),
            'username' => htmlspecialchars($username),
            'display_name' => htmlspecialchars($display_name ?: $username),
            'email' => htmlspecialchars($email),
            'extension' => htmlspecialchars($extension_requested),
            'extension_category' => htmlspecialchars($extension_category),
            'extension_purpose' => htmlspecialchars($extension_purpose),
            'did_requested' => htmlspecialchars($did_requested),
            'did_own_number' => htmlspecialchars($did_own_number),
            'reason' => htmlspecialchars($reason),
            'status' => 'pending'
        ];

        $signup_file = '/home/flexpbxuser/signups/user_' . $extension_requested . '_' . time() . '.json';
        @mkdir('/home/flexpbxuser/signups', 0755, true);
        file_put_contents($signup_file, json_encode($signup_data, JSON_PRETTY_PRINT));

        $success_message = "Sign-up request submitted! An administrator will review and contact you at $email";

        // Clear form
        $username = '';
        $email = '';
        $extension_requested = '';
        $reason = '';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Sign-Up - FlexPBX</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 600px;
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
        .signup-box {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
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
        .form-group textarea {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
        }
        .form-group textarea {
            min-height: 100px;
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
        .back-link {
            text-align: center;
            margin-top: 1rem;
        }
        .back-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        .back-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📱 Sign Up for FlexPBX</h1>
            <p>Request a user extension account</p>
        </div>

        <div class="signup-box">
            <h2 style="margin-bottom: 1.5rem; color: #2c3e50;">Create Your Account</h2>

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

            <form method="POST">
                <div class="form-group">
                    <label for="username">Full Name *</label>
                    <input type="text" id="username" name="username" value="<?= htmlspecialchars($username ?? '') ?>" required autofocus aria-required="true">
                    <small>Your full name for the account</small>
                </div>

                <div class="form-group">
                    <label for="email">Email Address *</label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($email ?? '') ?>" required aria-required="true">
                    <small>We'll contact you at this address</small>
                </div>

                <div class="form-group">
                    <label for="display_name">Display Name</label>
                    <input type="text" id="display_name" name="display_name" value="<?= htmlspecialchars($display_name ?? '') ?>" placeholder="e.g., John Smith">
                    <small>Name displayed on caller ID (defaults to your full name)</small>
                </div>

                <div class="form-group">
                    <label for="extension">Requested Extension *</label>
                    <div style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem;">
                        <button type="button" onclick="suggestExtension('sequential')" class="btn" style="width: auto; padding: 0.5rem 1rem; font-size: 0.9rem;">Next Available</button>
                        <button type="button" onclick="suggestExtension('random')" class="btn" style="width: auto; padding: 0.5rem 1rem; font-size: 0.9rem; background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);">🎲 Random</button>
                        <span id="availability-status" style="padding: 0.5rem; flex: 1; text-align: center; border-radius: 4px; font-weight: 600;"></span>
                    </div>
                    <input type="text" id="extension" name="extension" pattern="[0-9]{3,4}" maxlength="4" value="<?= htmlspecialchars($extension_requested ?? '') ?>" placeholder="e.g., 100, 2000" required aria-required="true" onblur="checkAvailability()">
                    <small>3-4 digit extension number (100-9999). Click buttons for suggestions, or enter your choice.</small>
                </div>

                <div class="form-group">
                    <label for="extension_category">Extension Category *</label>
                    <select id="extension_category" name="extension_category" required>
                        <option value="">Select category...</option>
                        <option value="personal">Personal</option>
                        <option value="business">Business</option>
                        <option value="department">Department</option>
                        <option value="support">Customer Support</option>
                        <option value="sales">Sales</option>
                        <option value="technical">Technical</option>
                        <option value="administrative">Administrative</option>
                        <option value="other">Other</option>
                    </select>
                    <small>Choose the category that best describes this extension's use</small>
                </div>

                <div class="form-group">
                    <label for="extension_purpose">Purpose / How You'll Use It *</label>
                    <textarea id="extension_purpose" name="extension_purpose" required placeholder="Describe how you'll use this extension..."></textarea>
                    <small>Examples: Customer service line, Personal mobile forwarding, Team conference calls</small>
                </div>

                <div class="form-group">
                    <label for="did_requested">Request Direct Inward Dial (DID) Number</label>
                    <select id="did_requested" name="did_requested" onchange="updateDidHelp()">
                        <option value="">No DID needed</option>
                        <option value="request_new">Request new phone number</option>
                        <option value="port_existing">Port existing number</option>
                    </select>
                    <small>Optional: Get a dedicated phone number for your extension</small>

                    <div id="did-help" style="margin-top: 1rem; padding: 1rem; background: #f8f9fa; border-left: 4px solid #667eea; border-radius: 4px; display: none;">
                        <h4 style="margin: 0 0 0.5rem 0; color: #667eea; font-size: 1rem;">📞 What is a DID?</h4>
                        <p style="margin: 0 0 0.8rem 0; font-size: 0.95rem; line-height: 1.5;">
                            A <strong>Direct Inward Dial (DID)</strong> is a dedicated phone number that rings directly to your extension,
                            allowing people outside the PBX system to call you.
                        </p>

                        <div style="margin-bottom: 0.8rem;">
                            <strong style="color: #28a745;">✓ You NEED a DID if you want to:</strong>
                            <ul style="margin: 0.3rem 0 0 1.2rem; font-size: 0.9rem;">
                                <li>Receive calls from external phone numbers (mobile, landline)</li>
                                <li>Give clients/customers a direct number to reach you</li>
                                <li>Have your own business line separate from the main company number</li>
                                <li>Receive calls when you're the only person being contacted</li>
                            </ul>
                        </div>

                        <div>
                            <strong style="color: #666;">✗ You DON'T need a DID if you only:</strong>
                            <ul style="margin: 0.3rem 0 0 1.2rem; font-size: 0.9rem;">
                                <li>Call other extensions internally (office to office)</li>
                                <li>Make outbound calls (your extension can still dial out)</li>
                                <li>Answer calls transferred from a receptionist or main line</li>
                                <li>Only communicate within the PBX system</li>
                            </ul>
                        </div>

                        <p style="margin: 0.8rem 0 0 0; padding-top: 0.8rem; border-top: 1px solid #ddd; font-size: 0.9rem; color: #666;">
                            <strong>Example:</strong> If you're a sales rep who needs clients to call you directly, choose "Request new phone number".
                            If you only take internal calls or calls transferred by reception, choose "No DID needed".
                        </p>
                    </div>
                </div>

                <div class="form-group">
                    <label for="reason">Reason for Account (Optional)</label>
                    <textarea id="reason" name="reason" placeholder="Tell us why you need access..."><?= htmlspecialchars($reason ?? '') ?></textarea>
                    <small>Helps us process your request faster</small>
                </div>

                <button type="submit" class="btn" aria-label="Submit sign-up request for new extension">Submit Sign-Up Request</button>
            </form>

            <div class="back-link">
                <a href="index.php" aria-label="Return to login page">← Back to Login</a>
            </div>
        </div>
    </div>

    <script>
        let extensionAvailable = false;

        function updateDidHelp() {
            const didSelect = document.getElementById('did_requested');
            const didHelp = document.getElementById('did-help');

            // Show help box when user interacts with DID selection
            if (didSelect.value !== '' || didSelect === document.activeElement) {
                didHelp.style.display = 'block';
            }
        }

        // Show DID help on focus
        document.addEventListener('DOMContentLoaded', function() {
            const didSelect = document.getElementById('did_requested');
            didSelect.addEventListener('focus', function() {
                document.getElementById('did-help').style.display = 'block';
            });
        });

        async function suggestExtension(type = 'sequential') {
            const statusEl = document.getElementById('availability-status');
            const extInput = document.getElementById('extension');

            statusEl.textContent = 'Loading...';
            statusEl.style.background = '#f0f0f0';
            statusEl.style.color = '#666';

            try {
                // Use 'suggest' for sequential, 'random' for random
                const action = type === 'random' ? 'random' : 'suggest';
                const response = await fetch('/api/extension-availability.php?action=' + action);
                const data = await response.json();

                if (data.available && data.suggested) {
                    extInput.value = data.suggested;
                    statusEl.textContent = '✓ ' + data.message;
                    statusEl.style.background = '#d4edda';
                    statusEl.style.color = '#155724';
                    extensionAvailable = true;
                } else {
                    statusEl.textContent = '⚠️ ' + data.message;
                    statusEl.style.background = '#fff3cd';
                    statusEl.style.color = '#856404';
                    extensionAvailable = false;
                }
            } catch (error) {
                statusEl.textContent = '⚠️ Error checking availability';
                statusEl.style.background = '#f8d7da';
                statusEl.style.color = '#721c24';
                extensionAvailable = false;
            }
        }

        async function checkAvailability() {
            const extInput = document.getElementById('extension');
            const statusEl = document.getElementById('availability-status');
            const extension = extInput.value.trim();

            if (extension.length !== 4 || !/^\d{4}$/.test(extension)) {
                statusEl.textContent = '⚠️ Extension must be 4 digits';
                statusEl.style.background = '#fff3cd';
                statusEl.style.color = '#856404';
                extensionAvailable = false;
                return;
            }

            try {
                const response = await fetch('/api/extension-availability.php?action=check&extension=' + extension);
                const data = await response.json();

                if (data.available) {
                    statusEl.textContent = '✓ Extension available';
                    statusEl.style.background = '#d4edda';
                    statusEl.style.color = '#155724';
                    extensionAvailable = true;
                } else {
                    statusEl.textContent = '✗ ' + data.message;
                    statusEl.style.background = '#f8d7da';
                    statusEl.style.color = '#721c24';
                    extensionAvailable = false;
                }
            } catch (error) {
                statusEl.textContent = '⚠️ Error checking availability';
                statusEl.style.background = '#f8d7da';
                statusEl.style.color = '#721c24';
                extensionAvailable = false;
            }
        }

        // Validate on form submit
        document.querySelector('form').addEventListener('submit', function(e) {
            if (!extensionAvailable) {
                e.preventDefault();
                alert('Please select an available extension or use Auto-Suggest.');
                document.getElementById('extension').focus();
            }
        });

        // Auto-suggest on page load
        window.addEventListener('DOMContentLoaded', function() {
            suggestExtension();
        });
    </script>

    <!-- Accessibility Tools -->
    <script src="/js/accessibility.js"></script>

    <!-- Footer with Support Links -->
    <div style="text-align: center; margin-top: 40px; padding: 20px;">
        <p style="color: white; opacity: 0.9; margin-bottom: 15px;">
            <a href="/admin/bug-tracker.php" style="color: white; text-decoration: underline; margin: 0 10px;">🐛 Report a Bug</a> |
            <a href="mailto:support@devine-creations.com" style="color: white; text-decoration: underline; margin: 0 10px;">📧 Support</a> |
            <a href="login.php" style="color: white; text-decoration: underline; margin: 0 10px;">← Back to Login</a>
        </p>
        <p style="color: white; opacity: 0.7; font-size: 0.9em;">
            Powered by <a href="https://devine-creations.com" target="_blank" style="color: white; text-decoration: underline;">Devine Creations</a> |
            <a href="https://devinecreations.net" target="_blank" style="color: white; text-decoration: underline;">devinecreations.net</a>
        </p>
    </div>
</body>
</html>
