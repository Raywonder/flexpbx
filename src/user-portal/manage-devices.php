<?php
/**
 * FlexPBX User Portal - Manage Devices
 * View and revoke remembered devices
 */

session_start();

// Check authentication
if (!isset($_SESSION['user_logged_in']) || $_SESSION['user_logged_in'] !== true) {
    header('Location: /user-portal/login.php');
    exit;
}

$extension = $_SESSION['user_extension'] ?? 'Unknown';
$username = $_SESSION['user_username'] ?? $extension;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Devices - FlexPBX User Portal</title>
    <link rel="stylesheet" href="/css/style.css">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f5f7fa;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .header {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .header h1 {
            margin: 0 0 0.5rem 0;
            color: #2c3e50;
        }

        .header .subtitle {
            color: #666;
            font-size: 0.95rem;
        }

        .card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .card h2 {
            margin: 0 0 1rem 0;
            color: #2c3e50;
            font-size: 1.3rem;
        }

        .device {
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            padding: 1rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .device:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .device.current {
            border-color: #4CAF50;
            background: #f1f8f4;
        }

        .device-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
        }

        .device-title {
            font-weight: 600;
            color: #2c3e50;
        }

        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-current {
            background: #4CAF50;
            color: white;
        }

        .device-info {
            font-size: 0.9rem;
            color: #666;
            margin: 0.25rem 0;
        }

        .device-info strong {
            color: #2c3e50;
        }

        .btn {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .btn-danger {
            background: #f44336;
            color: white;
        }

        .btn-danger:hover {
            background: #d32f2f;
        }

        .btn-warning {
            background: #ff9800;
            color: white;
        }

        .btn-warning:hover {
            background: #f57c00;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #666;
        }

        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }

        .actions {
            margin-top: 1.5rem;
            text-align: center;
        }

        .alert {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
        }

        .alert-info {
            background: #e3f2fd;
            border: 1px solid #2196f3;
            color: #1565c0;
        }

        .alert-warning {
            background: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔐 Manage Remembered Devices</h1>
            <p class="subtitle">Extension <?= htmlspecialchars($extension) ?> • <?= htmlspecialchars($username) ?></p>
        </div>

        <div class="card">
            <h2>Your Remembered Devices</h2>

            <div class="alert alert-info">
                ℹ️ These are devices where you've selected "Remember me" during login. You'll stay logged in on these devices for 30 days.
            </div>

            <div id="devices-list">
                <p style="text-align: center; color: #666;">Loading devices...</p>
            </div>

            <div class="actions">
                <button onclick="revokeAllDevices()" class="btn btn-warning" id="revoke-all-btn" style="display: none;">
                    🚫 Forget All Devices
                </button>
                <a href="/user-portal/" class="btn btn-secondary" style="margin-left: 1rem;">
                    ← Back to Dashboard
                </a>
            </div>
        </div>
    </div>

    <script>
        let devices = [];

        // Load devices on page load
        document.addEventListener('DOMContentLoaded', () => {
            loadDevices();
        });

        // Load devices from API
        async function loadDevices() {
            try {
                const response = await fetch('/api/device-management.php?action=list');
                const data = await response.json();

                if (data.success) {
                    devices = data.devices;
                    renderDevices();
                } else {
                    document.getElementById('devices-list').innerHTML = `
                        <div class="empty-state">
                            <div class="empty-state-icon">⚠️</div>
                            <p>Error loading devices: ${data.error}</p>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Failed to load devices:', error);
                document.getElementById('devices-list').innerHTML = `
                    <div class="empty-state">
                        <div class="empty-state-icon">⚠️</div>
                        <p>Failed to load devices. Please try again.</p>
                    </div>
                `;
            }
        }

        // Render devices list
        function renderDevices() {
            const container = document.getElementById('devices-list');

            if (devices.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-state-icon">📱</div>
                        <p><strong>No remembered devices</strong></p>
                        <p>When you check "Remember me" during login, devices will appear here.</p>
                    </div>
                `;
                document.getElementById('revoke-all-btn').style.display = 'none';
                return;
            }

            let html = '';
            devices.forEach((device, index) => {
                const browserInfo = parseBrowser(device.user_agent);

                html += `
                    <div class="device ${device.is_current ? 'current' : ''}">
                        <div class="device-header">
                            <div class="device-title">
                                ${browserInfo.icon} ${browserInfo.name}
                                ${device.is_current ? '<span class="badge badge-current">Current Device</span>' : ''}
                            </div>
                            <button onclick="revokeDevice(${index})" class="btn btn-danger btn-sm" ${device.is_current ? 'title="This will log you out"' : ''}>
                                Forget Device
                            </button>
                        </div>
                        <div class="device-info">
                            <strong>IP Address:</strong> ${device.ip}
                        </div>
                        <div class="device-info">
                            <strong>Login Date:</strong> ${device.created}
                        </div>
                        <div class="device-info">
                            <strong>Expires:</strong> ${device.expires}
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;
            document.getElementById('revoke-all-btn').style.display = 'inline-block';
        }

        // Parse browser from user agent
        function parseBrowser(userAgent) {
            if (!userAgent) return { name: 'Unknown Browser', icon: '🌐' };

            if (userAgent.includes('Firefox')) return { name: 'Firefox', icon: '🦊' };
            if (userAgent.includes('Chrome') && !userAgent.includes('Edg')) return { name: 'Chrome', icon: '🔵' };
            if (userAgent.includes('Safari') && !userAgent.includes('Chrome')) return { name: 'Safari', icon: '🧭' };
            if (userAgent.includes('Edg')) return { name: 'Edge', icon: '🌊' };
            if (userAgent.includes('Opera') || userAgent.includes('OPR')) return { name: 'Opera', icon: '🎭' };

            return { name: 'Unknown Browser', icon: '🌐' };
        }

        // Revoke a specific device
        async function revokeDevice(index) {
            const device = devices[index];

            let message = `Are you sure you want to forget this device?\n\nIP: ${device.ip}\nBrowser: ${parseBrowser(device.user_agent).name}`;

            if (device.is_current) {
                message += '\n\n⚠️ This is your current device. You will be logged out.';
            }

            if (!confirm(message)) {
                return;
            }

            try {
                const response = await fetch('/api/device-management.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=revoke&ip=${encodeURIComponent(device.ip)}&user_agent=${encodeURIComponent(device.user_agent)}`
                });

                const data = await response.json();

                if (data.success) {
                    if (device.is_current) {
                        alert('✓ Device forgotten. You will be logged out.');
                        window.location.href = '/user-portal/login.php';
                    } else {
                        alert('✓ Device forgotten successfully');
                        loadDevices();
                    }
                } else {
                    alert('⚠️ Error: ' + data.error);
                }
            } catch (error) {
                console.error('Failed to revoke device:', error);
                alert('⚠️ Failed to revoke device. Please try again.');
            }
        }

        // Revoke all devices
        async function revokeAllDevices() {
            if (!confirm('⚠️ Are you sure you want to forget ALL devices?\n\nYou will be logged out and will need to login again on all devices.')) {
                return;
            }

            try {
                const response = await fetch('/api/device-management.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=revoke_all'
                });

                const data = await response.json();

                if (data.success) {
                    alert('✓ All devices forgotten. You will be logged out.');
                    window.location.href = '/user-portal/login.php';
                } else {
                    alert('⚠️ Error: ' + data.error);
                }
            } catch (error) {
                console.error('Failed to revoke all devices:', error);
                alert('⚠️ Failed to revoke devices. Please try again.');
            }
        }
    </script>
</body>
</html>
