<?php
/**
 * Mattermost Installation Verification Script
 * Tests all components and database tables
 */

header('Content-Type: text/html; charset=utf-8');

$results = [
    'files' => [],
    'database' => [],
    'permissions' => [],
    'overall' => true
];

// Test 1: Check required files exist
echo "<h2>1. File Existence Check</h2>";
$required_files = [
    '/home/flexpbxuser/public_html/api/mattermost_schema.sql' => 'Database Schema',
    '/home/flexpbxuser/public_html/api/mattermost-integration.php' => 'API Integration',
    '/home/flexpbxuser/public_html/admin/mattermost-channels.php' => 'Admin Page',
    '/home/flexpbxuser/public_html/admin/mattermost-setup-helper.php' => 'Setup Helper',
    '/home/flexpbxuser/public_html/includes/mattermost-widget.php' => 'Widget Component',
    '/home/flexpbxuser/public_html/user-portal/chat.php' => 'User Chat Page'
];

echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>File</th><th>Status</th><th>Size</th></tr>";

foreach ($required_files as $file => $description) {
    $exists = file_exists($file);
    $size = $exists ? filesize($file) : 0;
    $status = $exists ? '✓ EXISTS' : '✗ MISSING';
    $color = $exists ? 'green' : 'red';

    echo "<tr>";
    echo "<td>{$description}<br><small>{$file}</small></td>";
    echo "<td style='color: {$color}; font-weight: bold;'>{$status}</td>";
    echo "<td>" . ($exists ? number_format($size) . ' bytes' : 'N/A') . "</td>";
    echo "</tr>";

    $results['files'][$description] = $exists;
    if (!$exists) $results['overall'] = false;
}

echo "</table>";

// Test 2: Check database tables
echo "<h2>2. Database Tables Check</h2>";

try {
    $config = require_once('/home/flexpbxuser/public_html/api/config.php');
    $db = new PDO(
        "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4",
        $config['db_user'],
        $config['db_password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $required_tables = [
        'mattermost_config',
        'mattermost_channels',
        'mattermost_user_mapping',
        'mattermost_message_cache',
        'mattermost_notifications',
        'mattermost_activity_log'
    ];

    echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Table Name</th><th>Status</th><th>Rows</th></tr>";

    foreach ($required_tables as $table) {
        try {
            $stmt = $db->query("SELECT COUNT(*) as count FROM {$table}");
            $count = $stmt->fetch()['count'];
            $status = '✓ EXISTS';
            $color = 'green';
            $results['database'][$table] = true;
        } catch (PDOException $e) {
            $count = 'N/A';
            $status = '✗ MISSING';
            $color = 'red';
            $results['database'][$table] = false;
            $results['overall'] = false;
        }

        echo "<tr>";
        echo "<td>{$table}</td>";
        echo "<td style='color: {$color}; font-weight: bold;'>{$status}</td>";
        echo "<td>{$count}</td>";
        echo "</tr>";
    }

    echo "</table>";

    // Check configuration
    echo "<h2>3. Configuration Check</h2>";
    $stmt = $db->query("SELECT * FROM mattermost_config ORDER BY id DESC LIMIT 1");
    $config_row = $stmt->fetch();

    if ($config_row) {
        echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Setting</th><th>Value</th></tr>";
        echo "<tr><td>Server URL</td><td>{$config_row['server_url']}</td></tr>";
        echo "<tr><td>Access Token</td><td>" . ($config_row['access_token'] ? '✓ Set (hidden)' : '✗ Not set') . "</td></tr>";
        echo "<tr><td>Poll Interval</td><td>{$config_row['poll_interval']} seconds</td></tr>";
        echo "<tr><td>Notifications</td><td>" . ($config_row['enable_notifications'] ? 'Enabled' : 'Disabled') . "</td></tr>";
        echo "<tr><td>Last Updated</td><td>{$config_row['updated_at']}</td></tr>";
        echo "</table>";
    } else {
        echo "<p style='color: orange;'>⚠ No configuration found. Complete setup using the admin page.</p>";
    }

} catch (PDOException $e) {
    echo "<p style='color: red;'>✗ Database connection failed: " . htmlspecialchars($e->getMessage()) . "</p>";
    $results['overall'] = false;
}

// Test 3: File permissions
echo "<h2>4. File Permissions Check</h2>";
echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>File</th><th>Readable</th><th>Writable</th><th>Permissions</th></tr>";

foreach (array_keys($required_files) as $file) {
    if (file_exists($file)) {
        $readable = is_readable($file) ? '✓' : '✗';
        $writable = is_writable($file) ? '✓' : '✗';
        $perms = substr(sprintf('%o', fileperms($file)), -4);

        $read_color = is_readable($file) ? 'green' : 'red';
        $write_color = is_writable($file) ? 'green' : 'orange';

        echo "<tr>";
        echo "<td>" . basename($file) . "</td>";
        echo "<td style='color: {$read_color};'>{$readable}</td>";
        echo "<td style='color: {$write_color};'>{$writable}</td>";
        echo "<td>{$perms}</td>";
        echo "</tr>";

        $results['permissions'][basename($file)] = is_readable($file);
    }
}

echo "</table>";

// Overall Status
echo "<h2>5. Overall Status</h2>";

$total_checks = count($results['files']) + count($results['database']) + count($results['permissions']);
$passed_checks = count(array_filter($results['files'])) +
                 count(array_filter($results['database'])) +
                 count(array_filter($results['permissions']));

$percentage = round(($passed_checks / $total_checks) * 100);

echo "<div style='padding: 20px; background: " . ($results['overall'] ? '#d4edda' : '#f8d7da') . "; border-radius: 8px; margin: 20px 0;'>";
echo "<h3 style='margin: 0 0 10px 0;'>" . ($results['overall'] ? '✓ Installation Complete' : '⚠ Installation Incomplete') . "</h3>";
echo "<p>Passed {$passed_checks}/{$total_checks} checks ({$percentage}%)</p>";

if ($results['overall']) {
    echo "<p><strong>Next Steps:</strong></p>";
    echo "<ol>";
    echo "<li>Visit the <a href='/admin/mattermost-setup-helper.php'>Setup Helper</a></li>";
    echo "<li>Create a Personal Access Token in Mattermost</li>";
    echo "<li>Configure connection in <a href='/admin/mattermost-channels.php'>Admin Panel</a></li>";
    echo "<li>Import channels</li>";
    echo "<li>Test at <a href='/user-portal/chat.php'>User Chat</a></li>";
    echo "</ol>";
} else {
    echo "<p style='color: red;'><strong>Issues detected!</strong> Please review the failed checks above.</p>";
}

echo "</div>";

// Access Links
echo "<h2>6. Quick Links</h2>";
echo "<ul>";
echo "<li><a href='/admin/mattermost-setup-helper.php' target='_blank'>Setup Helper (Wizard)</a></li>";
echo "<li><a href='/admin/mattermost-channels.php' target='_blank'>Admin Configuration</a></li>";
echo "<li><a href='/user-portal/chat.php' target='_blank'>User Chat Interface</a></li>";
echo "<li><a href='https://chat.tappedin.fm' target='_blank'>Mattermost Server</a></li>";
echo "<li><a href='/documentation/MATTERMOST_INTEGRATION_COMPLETE.md' target='_blank'>Full Documentation</a></li>";
echo "</ul>";

?>
<!DOCTYPE html>
<html>
<head>
    <title>Mattermost Installation Test - FlexPBX</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #667eea;
            padding-bottom: 10px;
        }
        h2 {
            color: #667eea;
            margin-top: 30px;
        }
        table {
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        a {
            color: #667eea;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
        small {
            color: #666;
            font-size: 12px;
        }
        .timestamp {
            text-align: right;
            color: #999;
            font-size: 14px;
            margin-top: 30px;
        }
    </style>
</head>
<body>
    <h1>Mattermost Installation Verification</h1>
    <p>This page verifies all components of the Mattermost integration are properly installed.</p>

    <?php
    // All the tests above are executed here
    ?>

    <div class="timestamp">
        Test completed at: <?php echo date('Y-m-d H:i:s'); ?>
    </div>
</body>
</html>
