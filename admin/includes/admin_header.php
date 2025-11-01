<?php
/**
 * FlexPBX Admin Header
 * Standard header for all admin pages with navigation, network status, and logout
 * Include this file after admin_auth_check.php
 */

// Ensure auth check was included
if (!isset($admin_username)) {
    die('Security Error: Include admin_auth_check.php before admin_header.php');
}

// Get current page for active nav highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'FlexPBX Admin'; ?></title>
    <style>
        .admin-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .admin-header-container {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .admin-header-left {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .admin-header-logo {
            font-size: 1.5rem;
            font-weight: 700;
            text-decoration: none;
            color: white;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .admin-header-nav {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .admin-header-nav a {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            transition: background 0.2s;
            font-size: 0.9rem;
        }

        .admin-header-nav a:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .admin-header-nav a.active {
            background: rgba(255, 255, 255, 0.3);
            font-weight: 600;
        }

        .admin-header-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .network-status {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255, 255, 255, 0.2);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
        }

        .network-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .ip-display {
            background: rgba(0, 0, 0, 0.2);
            padding: 0.4rem 0.8rem;
            border-radius: 6px;
            font-size: 0.8rem;
            font-family: monospace;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255, 255, 255, 0.2);
            padding: 0.5rem 1rem;
            border-radius: 20px;
        }

        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }

        .user-name {
            font-size: 0.9rem;
            font-weight: 600;
        }

        .logout-btn {
            background: rgba(255, 107, 107, 0.9);
            color: white;
            border: none;
            padding: 0.6rem 1.2rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .logout-btn:hover {
            background: rgba(255, 107, 107, 1);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }

        .network-warning {
            background: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
            padding: 1rem;
            margin: 1rem 2rem;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .network-safe {
            background: #d4edda;
            border: 1px solid #4ade80;
            color: #155724;
            padding: 1rem;
            margin: 1rem 2rem;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        @media (max-width: 768px) {
            .admin-header-container {
                flex-direction: column;
                align-items: flex-start;
            }

            .admin-header-nav {
                flex-wrap: wrap;
            }

            .admin-header-right {
                width: 100%;
                justify-content: space-between;
            }
        }

        /* Tooltip for IP details */
        .ip-tooltip {
            position: relative;
            cursor: help;
        }

        .ip-tooltip:hover::after {
            content: attr(data-tooltip);
            position: absolute;
            bottom: -40px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.9);
            color: white;
            padding: 0.5rem;
            border-radius: 6px;
            font-size: 0.75rem;
            white-space: nowrap;
            z-index: 1001;
        }
    </style>

    <!-- Idle Timeout Warning System -->
    <script src="/admin/js/idle-timeout-warning.js"></script>
</head>
<body>
    <header class="admin-header">
        <div class="admin-header-container">
            <div class="admin-header-left">
                <a href="/admin/dashboard.php" class="admin-header-logo">
                    <span>🔧</span>
                    <span>FlexPBX Admin</span>
                </a>

                <nav class="admin-header-nav">
                    <a href="/admin/dashboard.php" class="<?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">
                        Dashboard
                    </a>
                    <a href="/admin/admin-extensions-management.html">
                        Extensions
                    </a>
                    <a href="/admin/call-logs.html">
                        Call Logs
                    </a>
                    <a href="/admin/system-settings.php">
                        Settings
                    </a>
                </nav>
            </div>

            <div class="admin-header-right">
                <!-- Network Status -->
                <div class="network-status">
                    <div class="network-indicator" style="background: <?php echo htmlspecialchars($_SESSION['network_color'] ?? '#ff6b6b'); ?>;"></div>
                    <span><?php echo htmlspecialchars($_SESSION['network_name'] ?? 'Unknown Network'); ?></span>
                </div>

                <!-- IP Display -->
                <div class="ip-display ip-tooltip"
                     data-tooltip="<?php
                        echo 'Local: ' . htmlspecialchars($_SESSION['client_ip'] ?? 'Unknown');
                        if (!empty($_SESSION['public_ip'])) {
                            echo ' | Public: ' . htmlspecialchars($_SESSION['public_ip']);
                        }
                     ?>">
                    <?php echo htmlspecialchars($_SESSION['client_ip'] ?? 'Unknown IP'); ?>
                </div>

                <!-- User Info -->
                <div class="user-info">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($admin_username, 0, 1)); ?>
                    </div>
                    <div class="user-name">
                        <?php echo htmlspecialchars($admin_username); ?>
                    </div>
                </div>

                <!-- Logout Button -->
                <a href="/admin/logout.php" class="logout-btn" onclick="return confirm('Are you sure you want to logout?');">
                    Logout
                </a>
            </div>
        </div>
    </header>

    <?php
    // Show network warning if on public IP
    if (isset($_SESSION['network_trusted']) && !$_SESSION['network_trusted']):
    ?>
    <div class="network-warning">
        <span style="font-size: 1.5rem;">⚠️</span>
        <div>
            <strong>Public Network Detected</strong><br>
            <small>You are accessing the admin panel from a public IP address. Your session will expire in 24 hours. For extended sessions (30 days), connect via Tailscale, WireGuard, or local network.</small>
        </div>
    </div>
    <?php
    elseif (isset($_SESSION['network_trusted']) && $_SESSION['network_trusted']):
    ?>
    <div class="network-safe">
        <span style="font-size: 1.5rem;">✓</span>
        <div>
            <strong>Secure Network Detected</strong><br>
            <small>You are on a trusted network (<?php echo htmlspecialchars($_SESSION['network_name']); ?>). Your session will remain active for 30 days.</small>
        </div>
    </div>
    <?php endif; ?>

    <!-- Main Content Container -->
    <main style="padding: 2rem;">
