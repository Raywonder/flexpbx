<?php
/**
 * FlexPBX User Portal - Header Include
 * Common header with user info, network status, and logout button
 *
 * Usage:
 * require_once __DIR__ . '/user_auth_check.php'; // Must include auth check first
 * include __DIR__ . '/user_header.php';
 */

// Ensure auth check was included first
if (!isset($user_extension) || !isset($user_username)) {
    die('Error: user_auth_check.php must be included before user_header.php');
}
?>
<style>
    .user-portal-header {
        background: white;
        padding: 1rem 1.5rem;
        border-radius: 12px;
        margin-bottom: 1.5rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .user-portal-header .user-info {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .user-portal-header .user-avatar {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.3rem;
        font-weight: bold;
    }

    .user-portal-header .user-details h3 {
        color: #2c3e50;
        margin: 0 0 0.2rem 0;
        font-size: 1.1rem;
    }

    .user-portal-header .user-details p {
        color: #666;
        margin: 0;
        font-size: 0.9rem;
    }

    .user-portal-header .status-section {
        display: flex;
        align-items: center;
        gap: 1.5rem;
    }

    .user-portal-header .network-status {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        background: #f8f9fa;
        border-radius: 8px;
        font-size: 0.9rem;
    }

    .user-portal-header .status-indicator {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: #6c757d;
        animation: pulse 2s infinite;
    }

    .user-portal-header .status-indicator.online {
        background: #28a745;
    }

    .user-portal-header .status-indicator.offline {
        background: #dc3545;
    }

    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }

    .user-portal-header .header-actions {
        display: flex;
        gap: 0.5rem;
    }

    .user-portal-header .btn {
        padding: 0.6rem 1.2rem;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.2s ease;
        font-size: 0.9rem;
    }

    .user-portal-header .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }

    .user-portal-header .btn-secondary {
        background: #6c757d;
        color: white;
    }

    .user-portal-header .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    }

    @media (max-width: 768px) {
        .user-portal-header {
            flex-direction: column;
            align-items: flex-start;
        }

        .user-portal-header .status-section {
            width: 100%;
            flex-direction: column;
            align-items: flex-start;
        }

        .user-portal-header .header-actions {
            width: 100%;
        }

        .user-portal-header .btn {
            flex: 1;
            justify-content: center;
        }
    }
</style>

<div class="user-portal-header">
    <div class="user-info">
        <div class="user-avatar">
            <?php echo substr($user_extension, -2); ?>
        </div>
        <div class="user-details">
            <h3><?php echo htmlspecialchars($user_username); ?></h3>
            <p>Extension <?php echo htmlspecialchars($user_extension); ?></p>
        </div>
    </div>

    <div class="status-section">
        <div class="network-status">
            <span class="status-indicator" id="user-status-indicator"></span>
            <span id="user-status-text">Checking...</span>
        </div>

        <div class="header-actions">
            <a href="/user-portal/" class="btn btn-primary" aria-label="Go to user portal dashboard">
                Dashboard
            </a>
            <a href="/user-portal/logout.php" class="btn btn-secondary" aria-label="Logout from user portal">
                Logout
            </a>
        </div>
    </div>
</div>

<script>
    // Check user registration status
    (async function checkUserStatus() {
        const extension = '<?php echo addslashes($user_extension); ?>';
        const indicator = document.getElementById('user-status-indicator');
        const statusText = document.getElementById('user-status-text');

        try {
            const response = await fetch(`/api/user-status.php?path=registration&extension=${extension}`);
            const data = await response.json();

            if (data.success && data.registered) {
                indicator.className = 'status-indicator online';
                statusText.textContent = 'Registered';
            } else {
                indicator.className = 'status-indicator offline';
                statusText.textContent = 'Offline';
            }
        } catch (error) {
            console.error('Failed to check status:', error);
            indicator.className = 'status-indicator';
            statusText.textContent = 'Unknown';
        }

        // Refresh every 30 seconds
        setTimeout(checkUserStatus, 30000);
    })();
</script>
