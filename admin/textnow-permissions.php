<?php
/**
 * TextNow Permissions Management Admin Interface
 * Manage which extensions have access to TextNow calling
 */

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /admin/login.php');
    exit;
}

// Update activity
$_SESSION['last_activity'] = time();

require_once __DIR__ . '/../api/config.php';
$config = require __DIR__ . '/../api/config.php';

// Database connection
try {
    $pdo = new PDO(
        "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4",
        $config['db_user'],
        $config['db_password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'grant_permission':
            $extension = $_POST['extension'] ?? '';
            $grantedBy = $_SESSION['username'] ?? 'admin';

            if ($extension) {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO extension_permissions (extension, permission, granted_by)
                        VALUES (?, 'textnow_calling', ?)
                        ON DUPLICATE KEY UPDATE granted_at = NOW(), granted_by = ?
                    ");
                    $stmt->execute([$extension, $grantedBy, $grantedBy]);
                    $message = "TextNow access granted to extension $extension";
                    $messageType = 'success';
                } catch (PDOException $e) {
                    $message = "Error: " . $e->getMessage();
                    $messageType = 'error';
                }
            }
            break;

        case 'revoke_permission':
            $extension = $_POST['extension'] ?? '';

            if ($extension && $extension !== '2000') { // Don't allow revoking from 2000
                try {
                    $stmt = $pdo->prepare("
                        DELETE FROM extension_permissions
                        WHERE extension = ? AND permission = 'textnow_calling'
                    ");
                    $stmt->execute([$extension]);
                    $message = "TextNow access revoked from extension $extension";
                    $messageType = 'success';
                } catch (PDOException $e) {
                    $message = "Error: " . $e->getMessage();
                    $messageType = 'error';
                }
            } else {
                $message = "Cannot revoke access from extension 2000";
                $messageType = 'error';
            }
            break;
    }
}

// Get all extensions with permissions
$stmt = $pdo->query("
    SELECT * FROM extension_permissions
    WHERE permission = 'textnow_calling'
    ORDER BY extension
");
$permittedExtensions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all extensions
$stmt = $pdo->query("
    SELECT * FROM extensions
    ORDER BY extension_number
");
$allExtensions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get TextNow provider status
$stmt = $pdo->query("
    SELECT * FROM sms_providers
    WHERE provider_type = 'textnow'
    LIMIT 1
");
$textnowProvider = $stmt->fetch(PDO::FETCH_ASSOC);

// Get TextNow call statistics
$stmt = $pdo->query("
    SELECT
        COUNT(*) as total_calls,
        SUM(CASE WHEN direction = 'outbound' THEN 1 ELSE 0 END) as outbound_calls,
        SUM(CASE WHEN direction = 'inbound' THEN 1 ELSE 0 END) as inbound_calls,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_calls,
        SUM(duration) as total_duration
    FROM call_logs
    WHERE provider_type = 'textnow'
");
$callStats = $stmt->fetch(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TextNow Permissions - FlexPBX Admin</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        h1 {
            color: #333;
            margin-bottom: 10px;
        }

        .subtitle {
            color: #666;
            margin-bottom: 30px;
        }

        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .stat-card h3 {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
            text-transform: uppercase;
        }

        .stat-card .value {
            font-size: 32px;
            font-weight: bold;
            color: #333;
        }

        .stat-card.textnow {
            border-left: 4px solid #00A8E8;
        }

        .status-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 8px;
        }

        .status-indicator.active {
            background: #22c55e;
        }

        .status-indicator.inactive {
            background: #ef4444;
        }

        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            overflow: hidden;
        }

        .card-header {
            background: linear-gradient(135deg, #00A8E8 0%, #0077B5 100%);
            color: white;
            padding: 20px;
        }

        .card-header h2 {
            margin: 0;
        }

        .card-body {
            padding: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e5e5e5;
        }

        th {
            background: #f9f9f9;
            font-weight: 600;
            color: #555;
        }

        tr:hover {
            background: #f9f9f9;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #555;
        }

        select, input[type="text"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, #00A8E8 0%, #0077B5 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 168, 232, 0.3);
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .btn-small {
            padding: 6px 12px;
            font-size: 13px;
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-success {
            background: #d4edda;
            color: #155724;
        }

        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #00A8E8;
            text-decoration: none;
        }

        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="/admin/" class="back-link">← Back to Admin Dashboard</a>

        <h1>TextNow Permissions Management</h1>
        <p class="subtitle">Manage which extensions can make calls through TextNow trunk</p>

        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card textnow">
                <h3>Provider Status</h3>
                <div class="value">
                    <span class="status-indicator <?php echo $textnowProvider && $textnowProvider['enabled'] ? 'active' : 'inactive'; ?>"></span>
                    <?php echo $textnowProvider && $textnowProvider['enabled'] ? 'Active' : 'Inactive'; ?>
                </div>
            </div>

            <div class="stat-card">
                <h3>Authorized Extensions</h3>
                <div class="value"><?php echo count($permittedExtensions); ?></div>
            </div>

            <div class="stat-card">
                <h3>Total Calls</h3>
                <div class="value"><?php echo $callStats['total_calls'] ?? 0; ?></div>
            </div>

            <div class="stat-card">
                <h3>Call Duration</h3>
                <div class="value"><?php echo gmdate("H:i:s", $callStats['total_duration'] ?? 0); ?></div>
            </div>
        </div>

        <!-- Grant Permission Form -->
        <div class="card">
            <div class="card-header">
                <h2>Grant TextNow Access</h2>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="grant_permission">

                    <div class="form-group">
                        <label for="extension">Select Extension</label>
                        <select name="extension" id="extension" required>
                            <option value="">-- Select Extension --</option>
                            <?php foreach ($allExtensions as $ext): ?>
                                <option value="<?php echo htmlspecialchars($ext['extension_number']); ?>">
                                    Extension <?php echo htmlspecialchars($ext['extension_number']); ?>
                                    - <?php echo htmlspecialchars($ext['name'] ?? 'No Name'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary">Grant Access</button>
                </form>
            </div>
        </div>

        <!-- Permitted Extensions List -->
        <div class="card">
            <div class="card-header">
                <h2>Extensions with TextNow Access</h2>
            </div>
            <div class="card-body">
                <?php if (count($permittedExtensions) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Extension</th>
                                <th>Permission</th>
                                <th>Granted By</th>
                                <th>Granted At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($permittedExtensions as $perm): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($perm['extension']); ?></strong></td>
                                    <td>
                                        <span class="badge badge-success">TextNow Calling</span>
                                    </td>
                                    <td><?php echo htmlspecialchars($perm['granted_by'] ?? 'system'); ?></td>
                                    <td><?php echo date('M j, Y g:i A', strtotime($perm['granted_at'])); ?></td>
                                    <td>
                                        <?php if ($perm['extension'] !== '2000'): ?>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Revoke TextNow access from extension <?php echo $perm['extension']; ?>?');">
                                                <input type="hidden" name="action" value="revoke_permission">
                                                <input type="hidden" name="extension" value="<?php echo htmlspecialchars($perm['extension']); ?>">
                                                <button type="submit" class="btn btn-danger btn-small">Revoke</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="badge badge-warning">Default - Cannot Revoke</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="color: #666;">No extensions have been granted TextNow access yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
