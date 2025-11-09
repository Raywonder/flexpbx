<?php
/**
 * Mastodon Account Linking - User Portal
 * Allows users to link their Mastodon profile
 */

session_start();
require_once __DIR__ . '/../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: /user-portal/login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'User';

// Get current linked accounts
$stmt = $pdo->prepare("
    SELECT * FROM mastodon_linked_accounts
    WHERE user_id = ?
");
$stmt->execute([$userId]);
$linkedAccounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get available instances
$instancesStmt = $pdo->query("
    SELECT instance_url, is_default
    FROM mastodon_instances
    ORDER BY is_default DESC, instance_url ASC
");
$instances = $instancesStmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Link Mastodon Account - FlexPBX</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .mastodon-icon { color: #6364FF; }
        .linked-account { border-left: 4px solid #28a745; }
        .primary-badge { background: #6364FF; }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <!-- Header -->
                <div class="d-flex align-items-center mb-4">
                    <i class="fab fa-mastodon fa-3x mastodon-icon me-3"></i>
                    <div>
                        <h2 class="mb-0">Mastodon Authentication</h2>
                        <p class="text-muted mb-0">Link your Mastodon account for easier login</p>
                    </div>
                </div>

                <!-- Current User Info -->
                <div class="alert alert-info">
                    <i class="fas fa-user me-2"></i>
                    Logged in as: <strong><?= htmlspecialchars($username) ?></strong>
                </div>

                <!-- Linked Accounts -->
                <?php if (!empty($linkedAccounts)): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-link me-2"></i>Linked Accounts</h5>
                        </div>
                        <div class="card-body">
                            <?php foreach ($linkedAccounts as $account): ?>
                                <div class="card linked-account mb-3">
                                    <div class="card-body">
                                        <div class="row align-items-center">
                                            <div class="col-md-2">
                                                <?php if ($account['avatar_url']): ?>
                                                    <img src="<?= htmlspecialchars($account['avatar_url']) ?>"
                                                         class="img-fluid rounded-circle"
                                                         alt="Avatar">
                                                <?php else: ?>
                                                    <i class="fas fa-user-circle fa-4x text-muted"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-7">
                                                <h6 class="mb-1">
                                                    <?= htmlspecialchars($account['display_name'] ?: $account['username']) ?>
                                                    <?php if ($account['is_primary']): ?>
                                                        <span class="badge primary-badge">Primary</span>
                                                    <?php endif; ?>
                                                </h6>
                                                <p class="text-muted mb-1">
                                                    <small>@<?= htmlspecialchars($account['username']) ?></small>
                                                </p>
                                                <p class="text-muted mb-0">
                                                    <small><i class="fas fa-server me-1"></i><?= htmlspecialchars($account['instance_url']) ?></small>
                                                </p>
                                                <?php if ($account['last_sync']): ?>
                                                    <p class="text-muted mb-0">
                                                        <small><i class="fas fa-sync me-1"></i>Last sync: <?= date('M j, Y g:i A', strtotime($account['last_sync'])) ?></small>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-3 text-end">
                                                <button class="btn btn-sm btn-danger" onclick="unlinkAccount('<?= htmlspecialchars($account['instance_url']) ?>')">
                                                    <i class="fas fa-unlink me-1"></i>Unlink
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Link New Account -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Link Mastodon Account</h5>
                    </div>
                    <div class="card-body">
                        <form id="linkForm">
                            <div class="mb-3">
                                <label for="instance" class="form-label">Choose Mastodon Instance</label>
                                <select class="form-select" id="instance" name="instance" required>
                                    <?php foreach ($instances as $instance): ?>
                                        <option value="<?= htmlspecialchars($instance['instance_url']) ?>"
                                                <?= $instance['is_default'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($instance['instance_url']) ?>
                                            <?= $instance['is_default'] ? ' (Default)' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <option value="custom">Custom instance...</option>
                                </select>
                            </div>

                            <div class="mb-3" id="customInstanceDiv" style="display: none;">
                                <label for="customInstance" class="form-label">Custom Instance URL</label>
                                <input type="url" class="form-control" id="customInstance"
                                       placeholder="https://mastodon.example.com">
                                <small class="form-text text-muted">
                                    ⚠️ Use at your own risk. Only trusted instances are recommended.
                                </small>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="fab fa-mastodon me-2"></i>Link with Mastodon
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Authentication Info -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>How It Works</h5>
                    </div>
                    <div class="card-body">
                        <h6>Primary & Fallback Authentication</h6>
                        <ul>
                            <li><strong>Primary:</strong> If linked, you can login using your Mastodon account</li>
                            <li><strong>Fallback:</strong> If Mastodon is unreachable, automatic fallback to FlexPBX login</li>
                            <li><strong>Sync:</strong> Profile data automatically syncs from Mastodon</li>
                            <li><strong>Extension:</strong> Get your extension number (e.g., 1234@flexpbx.devinecreations.net)</li>
                        </ul>

                        <h6 class="mt-3">Benefits</h6>
                        <ul>
                            <li>Single sign-on with your Mastodon account</li>
                            <li>No need to remember separate passwords</li>
                            <li>Profile automatically updated from Mastodon</li>
                            <li>Secure OAuth2 authentication</li>
                        </ul>
                    </div>
                </div>

                <div class="text-center mt-4">
                    <a href="/user-portal/" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Portal
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Show custom instance field
        document.getElementById('instance').addEventListener('change', function() {
            const customDiv = document.getElementById('customInstanceDiv');
            if (this.value === 'custom') {
                customDiv.style.display = 'block';
            } else {
                customDiv.style.display = 'none';
            }
        });

        // Handle form submission
        document.getElementById('linkForm').addEventListener('submit', function(e) {
            e.preventDefault();

            let instance = document.getElementById('instance').value;
            if (instance === 'custom') {
                instance = document.getElementById('customInstance').value;
                if (!instance) {
                    alert('Please enter a custom instance URL');
                    return;
                }
            }

            // Redirect to Mastodon OAuth
            window.location.href = '/api/mastodon-auth.php?action=login&instance=' + encodeURIComponent(instance);
        });

        // Unlink account
        function unlinkAccount(instance) {
            if (!confirm('Are you sure you want to unlink this Mastodon account?')) {
                return;
            }

            fetch('/api/mastodon-auth.php?action=unlink', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ instance: instance })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Account unlinked successfully');
                    location.reload();
                } else {
                    alert('Error: ' + data.error);
                }
            });
        }
    </script>
</body>
</html>
