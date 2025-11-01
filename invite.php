<?php
/**
 * FlexPBX - Public Invitation Acceptance Page
 * Allows invitees to accept invitations and create accounts
 */

// Get invitation token and ID from URL
$token = $_GET['token'] ?? '';
$invite_id = $_GET['id'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accept Invitation - FlexPBX</title>
    <link rel="stylesheet" href="/css/style.css">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .container {
            max-width: 500px;
            width: 90%;
            margin: 2rem auto;
        }

        .card {
            background: white;
            border-radius: 12px;
            padding: 2.5rem;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }

        .logo {
            text-align: center;
            margin-bottom: 2rem;
        }

        .logo h1 {
            margin: 0;
            color: #2c3e50;
            font-size: 2rem;
        }

        .logo .subtitle {
            color: #666;
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }

        .invite-info {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }

        .invite-info h2 {
            margin: 0 0 1rem 0;
            color: #2c3e50;
            font-size: 1.2rem;
        }

        .invite-detail {
            margin-bottom: 0.75rem;
            font-size: 0.95rem;
            color: #666;
        }

        .invite-detail strong {
            color: #2c3e50;
        }

        .role-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 600;
            background: #2196f3;
            color: white;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #2c3e50;
            font-weight: 600;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            font-size: 0.95rem;
            font-family: inherit;
            transition: border-color 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: #2196f3;
        }

        .password-strength {
            margin-top: 0.5rem;
            font-size: 0.85rem;
        }

        .strength-bar {
            height: 4px;
            background: #e0e0e0;
            border-radius: 2px;
            overflow: hidden;
            margin-bottom: 0.25rem;
        }

        .strength-fill {
            height: 100%;
            width: 0%;
            transition: all 0.3s;
        }

        .strength-weak .strength-fill {
            width: 33%;
            background: #f44336;
        }

        .strength-medium .strength-fill {
            width: 66%;
            background: #ff9800;
        }

        .strength-strong .strength-fill {
            width: 100%;
            background: #4CAF50;
        }

        .btn {
            display: inline-block;
            width: 100%;
            padding: 0.875rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: all 0.3s;
            text-align: center;
        }

        .btn-primary {
            background: #2196f3;
            color: white;
        }

        .btn-primary:hover:not(:disabled) {
            background: #1976d2;
        }

        .btn-primary:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        .alert {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .alert-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .alert-info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }

        .loading {
            text-align: center;
            padding: 3rem;
        }

        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #2196f3;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .success-message {
            text-align: center;
            padding: 2rem;
        }

        .success-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }

        .redirect-info {
            margin-top: 1rem;
            font-size: 0.9rem;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="logo">
                <h1>📞 FlexPBX</h1>
                <p class="subtitle">Accept Your Invitation</p>
            </div>

            <!-- Loading State -->
            <div id="loading-state" class="loading">
                <div class="spinner"></div>
                <p>Verifying invitation...</p>
            </div>

            <!-- Error State -->
            <div id="error-state" style="display: none;">
                <div class="alert alert-error" id="error-message"></div>
                <a href="/" class="btn btn-primary">Go to Homepage</a>
            </div>

            <!-- Invitation Details -->
            <div id="invite-details" style="display: none;">
                <div class="invite-info">
                    <h2>You've Been Invited!</h2>
                    <div class="invite-detail">
                        <strong>Email:</strong> <span id="invite-email"></span>
                    </div>
                    <div class="invite-detail">
                        <strong>Role:</strong> <span id="invite-role" class="role-badge"></span>
                    </div>
                    <div class="invite-detail" id="invite-extension-info" style="display: none;">
                        <strong>Extension:</strong> <span id="invite-extension"></span>
                    </div>
                    <div class="invite-detail">
                        <strong>Invited by:</strong> <span id="invite-creator"></span>
                    </div>
                    <div class="invite-detail" id="invite-message-container" style="display: none;">
                        <strong>Message:</strong>
                        <div style="margin-top: 0.5rem; padding: 0.75rem; background: white; border-radius: 4px; white-space: pre-wrap;" id="invite-message"></div>
                    </div>
                </div>

                <form id="accept-form" onsubmit="acceptInvite(event)">
                    <div class="form-group">
                        <label for="username">Choose Username *</label>
                        <input
                            type="text"
                            id="username"
                            class="form-control"
                            placeholder="username"
                            pattern="[a-zA-Z0-9_-]{3,20}"
                            title="3-20 characters, letters, numbers, underscore and hyphen only"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="full_name">Full Name *</label>
                        <input
                            type="text"
                            id="full_name"
                            class="form-control"
                            placeholder="John Doe"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="password">Choose Password *</label>
                        <input
                            type="password"
                            id="password"
                            class="form-control"
                            placeholder="Choose a strong password"
                            required
                            oninput="checkPasswordStrength()"
                        >
                        <div class="password-strength">
                            <div class="strength-bar">
                                <div class="strength-fill" id="strength-fill"></div>
                            </div>
                            <span id="strength-text"></span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="password_confirm">Confirm Password *</label>
                        <input
                            type="password"
                            id="password_confirm"
                            class="form-control"
                            placeholder="Confirm your password"
                            required
                        >
                    </div>

                    <button type="submit" class="btn btn-primary" id="accept-btn">
                        ✓ Create Account
                    </button>
                </form>
            </div>

            <!-- Success State -->
            <div id="success-state" style="display: none;">
                <div class="success-message">
                    <div class="success-icon">✓</div>
                    <h2 style="color: #4CAF50; margin: 0 0 1rem 0;">Account Created!</h2>
                    <p style="color: #666;">Your FlexPBX account has been created successfully.</p>
                    <div class="redirect-info">
                        Redirecting to login page in <span id="countdown">5</span> seconds...
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const token = '<?= htmlspecialchars($token, ENT_QUOTES) ?>';
        const inviteId = '<?= htmlspecialchars($invite_id, ENT_QUOTES) ?>';
        let inviteData = null;

        // Verify invitation on page load
        document.addEventListener('DOMContentLoaded', () => {
            verifyInvitation();
        });

        // Verify invitation
        async function verifyInvitation() {
            if (!token || !inviteId) {
                showError('Invalid invitation link');
                return;
            }

            try {
                const response = await fetch(`/api/invites.php?action=verify_invite&token=${encodeURIComponent(token)}&id=${encodeURIComponent(inviteId)}`);
                const data = await response.json();

                if (data.success) {
                    inviteData = data.invite;
                    showInviteDetails();
                } else {
                    showError(data.error || 'Invalid or expired invitation');
                }
            } catch (error) {
                console.error('Verification error:', error);
                showError('Failed to verify invitation. Please try again.');
            }
        }

        // Show invite details
        function showInviteDetails() {
            document.getElementById('loading-state').style.display = 'none';
            document.getElementById('invite-details').style.display = 'block';

            document.getElementById('invite-email').textContent = inviteData.email;
            document.getElementById('invite-role').textContent = inviteData.role.toUpperCase();
            document.getElementById('invite-creator').textContent = inviteData.created_by;

            // Pre-fill full name if provided
            if (inviteData.full_name) {
                document.getElementById('full_name').value = inviteData.full_name;
            }

            // Show extension if provided
            if (inviteData.extension) {
                document.getElementById('invite-extension').textContent = inviteData.extension;
                document.getElementById('invite-extension-info').style.display = 'block';
            }

            // Show message if provided
            if (inviteData.message) {
                document.getElementById('invite-message').textContent = inviteData.message;
                document.getElementById('invite-message-container').style.display = 'block';
            }
        }

        // Show error
        function showError(message) {
            document.getElementById('loading-state').style.display = 'none';
            document.getElementById('error-state').style.display = 'block';
            document.getElementById('error-message').textContent = message;
        }

        // Check password strength
        function checkPasswordStrength() {
            const password = document.getElementById('password').value;
            const strengthFill = document.getElementById('strength-fill');
            const strengthText = document.getElementById('strength-text');
            const container = strengthFill.parentElement.parentElement;

            if (password.length === 0) {
                container.className = 'password-strength';
                strengthText.textContent = '';
                return;
            }

            let strength = 0;

            // Length
            if (password.length >= 8) strength++;
            if (password.length >= 12) strength++;

            // Character variety
            if (/[a-z]/.test(password)) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^a-zA-Z0-9]/.test(password)) strength++;

            if (strength <= 2) {
                container.className = 'password-strength strength-weak';
                strengthText.textContent = 'Weak password';
            } else if (strength <= 4) {
                container.className = 'password-strength strength-medium';
                strengthText.textContent = 'Medium strength';
            } else {
                container.className = 'password-strength strength-strong';
                strengthText.textContent = 'Strong password';
            }
        }

        // Accept invitation
        async function acceptInvite(event) {
            event.preventDefault();

            const username = document.getElementById('username').value.trim();
            const full_name = document.getElementById('full_name').value.trim();
            const password = document.getElementById('password').value;
            const password_confirm = document.getElementById('password_confirm').value;

            // Validate passwords match
            if (password !== password_confirm) {
                alert('Passwords do not match');
                return;
            }

            // Validate password strength
            if (password.length < 8) {
                alert('Password must be at least 8 characters long');
                return;
            }

            const acceptBtn = document.getElementById('accept-btn');
            acceptBtn.disabled = true;
            acceptBtn.textContent = 'Creating Account...';

            try {
                const response = await fetch('/api/invites.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'accept_invite',
                        token: token,
                        invite_id: inviteId,
                        username: username,
                        full_name: full_name,
                        password: password
                    })
                });

                const data = await response.json();

                if (data.success) {
                    showSuccess(data.role);
                } else {
                    alert('Failed to create account: ' + data.error);
                    acceptBtn.disabled = false;
                    acceptBtn.textContent = '✓ Create Account';
                }
            } catch (error) {
                console.error('Accept error:', error);
                alert('Failed to create account. Please try again.');
                acceptBtn.disabled = false;
                acceptBtn.textContent = '✓ Create Account';
            }
        }

        // Show success and redirect
        function showSuccess(role) {
            document.getElementById('invite-details').style.display = 'none';
            document.getElementById('success-state').style.display = 'block';

            const loginUrl = role === 'admin' ? '/admin/login.php' : '/user-portal/login.php';

            let countdown = 5;
            const countdownEl = document.getElementById('countdown');

            const interval = setInterval(() => {
                countdown--;
                countdownEl.textContent = countdown;

                if (countdown <= 0) {
                    clearInterval(interval);
                    window.location.href = loginUrl;
                }
            }, 1000);
        }
    </script>
</body>
</html>
