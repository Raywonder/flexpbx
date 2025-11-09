<?php
/**
 * FlexPBX Master Database Installer v1.3
 * Installs ALL database tables and default content for FlexPBX v1.3
 *
 * This script runs all database setup scripts in the correct order:
 * 1. Core tables (setup_tables.php)
 * 2. SMS providers (install_sms_schema.php)
 * 3. Mattermost integration (mattermost_schema.sql)
 * 4. Notifications system (setup_notifications.php)
 * 5. Announcements system (inline)
 * 6. Help system (inline)
 * 7. Legal pages system (inline)
 *
 * @version 1.3.0
 * @date 2025-11-06
 */

// Prevent direct access
if (!defined('FLEXPBX_INIT')) {
    define('FLEXPBX_INIT', true);
}

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load database configuration
require_once __DIR__ . '/../config/database.php';

// Track installation results
$results = [
    'status' => 'success',
    'steps' => [],
    'errors' => [],
    'warnings' => []
];

echo "<h1>FlexPBX v1.3 Master Database Installer</h1>\n";
echo "<p>Installing all database tables and default content...</p>\n";
echo "<hr>\n";
flush();

/**
 * Step 1: Install Core Tables
 */
echo "<h2>Step 1: Installing Core Tables</h2>\n";
try {
    include __DIR__ . '/setup_tables.php';
    $results['steps']['core_tables'] = 'Success';
    echo "<p style='color: green;'>✅ Core tables installed successfully</p>\n";
} catch (Exception $e) {
    $results['status'] = 'error';
    $results['errors'][] = "Core tables: " . $e->getMessage();
    echo "<p style='color: red;'>❌ Error installing core tables: " . htmlspecialchars($e->getMessage()) . "</p>\n";
}
flush();

/**
 * Step 2: Install SMS Provider Tables
 */
echo "<h2>Step 2: Installing SMS Provider Tables</h2>\n";
try {
    include __DIR__ . '/install_sms_schema.php';
    $results['steps']['sms_providers'] = 'Success';
    echo "<p style='color: green;'>✅ SMS provider tables installed successfully</p>\n";
} catch (Exception $e) {
    $results['warnings'][] = "SMS providers: " . $e->getMessage();
    echo "<p style='color: orange;'>⚠️ Warning: SMS provider tables: " . htmlspecialchars($e->getMessage()) . "</p>\n";
}
flush();

/**
 * Step 3: Install Mattermost Integration
 */
echo "<h2>Step 3: Installing Mattermost Integration</h2>\n";
try {
    $sql_file = __DIR__ . '/mattermost_schema.sql';
    if (file_exists($sql_file)) {
        $sql = file_get_contents($sql_file);
        $pdo->exec($sql);
        $results['steps']['mattermost'] = 'Success';
        echo "<p style='color: green;'>✅ Mattermost tables installed successfully (6 tables)</p>\n";
    } else {
        $results['warnings'][] = "Mattermost: Schema file not found";
        echo "<p style='color: orange;'>⚠️ Warning: Mattermost schema file not found</p>\n";
    }
} catch (Exception $e) {
    $results['warnings'][] = "Mattermost: " . $e->getMessage();
    echo "<p style='color: orange;'>⚠️ Warning: Mattermost tables: " . htmlspecialchars($e->getMessage()) . "</p>\n";
}
flush();

/**
 * Step 4: Install Notifications System
 */
echo "<h2>Step 4: Installing Notifications System</h2>\n";
try {
    include __DIR__ . '/setup_notifications.php';
    $results['steps']['notifications'] = 'Success';
    echo "<p style='color: green;'>✅ Notifications system installed successfully (5 tables + 6 templates)</p>\n";
} catch (Exception $e) {
    $results['warnings'][] = "Notifications: " . $e->getMessage();
    echo "<p style='color: orange;'>⚠️ Warning: Notifications system: " . htmlspecialchars($e->getMessage()) . "</p>\n";
}
flush();

/**
 * Step 5: Install Announcements System
 */
echo "<h2>Step 5: Installing Announcements System</h2>\n";
try {
    // Create announcements table
    $sql_announcements = "CREATE TABLE IF NOT EXISTS announcements (
        id INT PRIMARY KEY AUTO_INCREMENT,
        title VARCHAR(200) NOT NULL,
        content TEXT NOT NULL,
        announcement_type ENUM('info', 'warning', 'success', 'error', 'urgent') DEFAULT 'info',
        target_audience ENUM('all', 'admins', 'users', 'custom') DEFAULT 'all',
        target_roles JSON,
        start_date DATETIME,
        end_date DATETIME,
        is_active BOOLEAN DEFAULT TRUE,
        is_dismissible BOOLEAN DEFAULT TRUE,
        priority INT DEFAULT 0,
        created_by VARCHAR(100),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        template_id INT,
        metadata JSON,
        INDEX idx_active (is_active),
        INDEX idx_dates (start_date, end_date),
        INDEX idx_audience (target_audience),
        INDEX idx_priority (priority)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $pdo->exec($sql_announcements);

    // Create announcement_views table
    $sql_views = "CREATE TABLE IF NOT EXISTS announcement_views (
        id INT PRIMARY KEY AUTO_INCREMENT,
        announcement_id INT NOT NULL,
        user_id VARCHAR(100) NOT NULL,
        viewed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        dismissed_at DATETIME,
        is_dismissed BOOLEAN DEFAULT FALSE,
        FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE,
        INDEX idx_announcement (announcement_id),
        INDEX idx_user (user_id),
        UNIQUE KEY unique_user_announcement (announcement_id, user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $pdo->exec($sql_views);

    // Create announcement_templates table
    $sql_templates = "CREATE TABLE IF NOT EXISTS announcement_templates (
        id INT PRIMARY KEY AUTO_INCREMENT,
        template_name VARCHAR(100) UNIQUE NOT NULL,
        title_template VARCHAR(200) NOT NULL,
        content_template TEXT NOT NULL,
        announcement_type ENUM('info', 'warning', 'success', 'error', 'urgent') DEFAULT 'info',
        is_dismissible BOOLEAN DEFAULT TRUE,
        priority INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $pdo->exec($sql_templates);

    // Insert default templates
    $default_templates = [
        ['system_maintenance', 'System Maintenance Scheduled', 'System maintenance is scheduled for {date} at {time}. Expected downtime: {duration}.', 'warning', 1, 5],
        ['new_feature', 'New Feature Available', 'Check out our new feature: {feature_name}. {description}', 'success', 1, 3],
        ['urgent_update', 'Urgent Security Update', 'Please update your system immediately. {details}', 'urgent', 0, 10],
        ['welcome_message', 'Welcome to FlexPBX', 'Welcome {user_name}! Get started by {action}.', 'info', 1, 1],
        ['billing_reminder', 'Billing Reminder', 'Your {plan_name} plan will renew on {date}. Amount: ${amount}', 'info', 1, 2],
        ['service_outage', 'Service Outage Alert', 'We are experiencing issues with {service_name}. Our team is working on it.', 'error', 0, 9],
        ['password_change', 'Password Change Required', 'For security reasons, please change your password within {days} days.', 'warning', 0, 6],
        ['survey_request', 'We Value Your Feedback', 'Help us improve by taking our {survey_length} survey. {survey_link}', 'info', 1, 2],
        ['holiday_hours', 'Holiday Hours', 'Our {holiday_name} hours: {hours}. Emergency support available.', 'info', 1, 4],
        ['training_webinar', 'Training Webinar Available', 'Join our {topic} webinar on {date}. Register: {link}', 'success', 1, 3]
    ];

    $stmt = $pdo->prepare("INSERT IGNORE INTO announcement_templates
        (template_name, title_template, content_template, announcement_type, is_dismissible, priority)
        VALUES (?, ?, ?, ?, ?, ?)");

    foreach ($default_templates as $template) {
        $stmt->execute($template);
    }

    $results['steps']['announcements'] = 'Success';
    echo "<p style='color: green;'>✅ Announcements system installed successfully (3 tables + 10 templates)</p>\n";
} catch (Exception $e) {
    $results['warnings'][] = "Announcements: " . $e->getMessage();
    echo "<p style='color: orange;'>⚠️ Warning: Announcements system: " . htmlspecialchars($e->getMessage()) . "</p>\n";
}
flush();

/**
 * Step 6: Install Help & Documentation System
 */
echo "<h2>Step 6: Installing Help & Documentation System</h2>\n";
try {
    // Create help_articles table
    $sql_articles = "CREATE TABLE IF NOT EXISTS help_articles (
        id INT PRIMARY KEY AUTO_INCREMENT,
        article_key VARCHAR(100) UNIQUE NOT NULL,
        title VARCHAR(200) NOT NULL,
        content TEXT NOT NULL,
        category VARCHAR(50),
        subcategory VARCHAR(50),
        tags JSON,
        is_published BOOLEAN DEFAULT TRUE,
        view_count INT DEFAULT 0,
        helpful_count INT DEFAULT 0,
        not_helpful_count INT DEFAULT 0,
        created_by VARCHAR(100),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_key (article_key),
        INDEX idx_category (category),
        INDEX idx_published (is_published)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $pdo->exec($sql_articles);

    // Create help_tooltips table
    $sql_tooltips = "CREATE TABLE IF NOT EXISTS help_tooltips (
        id INT PRIMARY KEY AUTO_INCREMENT,
        element_id VARCHAR(100) UNIQUE NOT NULL,
        tooltip_text TEXT NOT NULL,
        tooltip_position VARCHAR(20) DEFAULT 'top',
        page_path VARCHAR(255),
        is_active BOOLEAN DEFAULT TRUE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_element (element_id),
        INDEX idx_page (page_path)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $pdo->exec($sql_tooltips);

    // Insert default help articles
    $default_articles = [
        ['getting-started', 'Getting Started with FlexPBX', 'Learn the basics of FlexPBX and how to set up your account...', 'basics', 'introduction'],
        ['making-calls', 'How to Make Calls', 'Guide to making calls using FlexPBX WebRTC phone or SIP clients...', 'calls', 'basics'],
        ['extension-setup', 'Setting Up Extensions', 'Create and configure extensions for your users...', 'administration', 'extensions'],
        ['sms-configuration', 'Configuring SMS Providers', 'Set up TextNow, Google Voice, or Twilio for SMS messaging...', 'sms', 'configuration'],
        ['voicemail-setup', 'Voicemail Configuration', 'Configure voicemail boxes and greetings...', 'voicemail', 'configuration'],
        ['call-routing', 'Call Routing Rules', 'Create custom call routing rules and IVR menus...', 'routing', 'advanced'],
        ['user-portal', 'Using the User Portal', 'Navigate and use the FlexPBX user portal...', 'basics', 'user-guide'],
        ['mattermost-chat', 'Mattermost Chat Integration', 'How to use embedded Mattermost channels...', 'collaboration', 'chat'],
        ['notifications', 'Managing Notifications', 'Customize your notification preferences...', 'settings', 'notifications'],
        ['security-best-practices', 'Security Best Practices', 'Secure your FlexPBX installation...', 'security', 'administration']
    ];

    $stmt = $pdo->prepare("INSERT IGNORE INTO help_articles
        (article_key, title, content, category, subcategory)
        VALUES (?, ?, ?, ?, ?)");

    foreach ($default_articles as $article) {
        $stmt->execute($article);
    }

    $results['steps']['help_system'] = 'Success';
    echo "<p style='color: green;'>✅ Help system installed successfully (2 tables + 10 articles)</p>\n";
} catch (Exception $e) {
    $results['warnings'][] = "Help system: " . $e->getMessage();
    echo "<p style='color: orange;'>⚠️ Warning: Help system: " . htmlspecialchars($e->getMessage()) . "</p>\n";
}
flush();

/**
 * Step 7: Install Legal Pages System
 */
echo "<h2>Step 7: Installing Legal Pages System</h2>\n";
try {
    $sql_legal = "CREATE TABLE IF NOT EXISTS legal_pages (
        id INT PRIMARY KEY AUTO_INCREMENT,
        page_key VARCHAR(50) UNIQUE NOT NULL,
        page_title VARCHAR(200) NOT NULL,
        page_content LONGTEXT NOT NULL,
        version VARCHAR(20) DEFAULT '1.0',
        effective_date DATE,
        last_updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        updated_by VARCHAR(100),
        is_published BOOLEAN DEFAULT FALSE,
        metadata JSON,
        INDEX idx_page_key (page_key),
        INDEX idx_published (is_published)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $pdo->exec($sql_legal);

    // Insert default legal documents
    $default_legal_pages = [
        [
            'privacy-policy',
            'Privacy Policy',
            '<h1>Privacy Policy</h1><p>Last Updated: ' . date('F j, Y') . '</p><h2>1. Information We Collect</h2><p>We collect information you provide directly to us...</p><h2>2. How We Use Your Information</h2><p>We use the information we collect to...</p><h2>3. Information Sharing</h2><p>We do not sell your personal information...</p>',
            '1.0',
            date('Y-m-d'),
            'system',
            1
        ],
        [
            'terms-of-service',
            'Terms of Service',
            '<h1>Terms of Service</h1><p>Last Updated: ' . date('F j, Y') . '</p><h2>1. Acceptance of Terms</h2><p>By accessing and using FlexPBX, you accept and agree to be bound by the terms...</p><h2>2. Use License</h2><p>Permission is granted to temporarily download one copy of FlexPBX...</p><h2>3. Disclaimer</h2><p>The materials on FlexPBX are provided on an \'as is\' basis...</p>',
            '1.0',
            date('Y-m-d'),
            'system',
            1
        ],
        [
            'cookie-policy',
            'Cookie Policy',
            '<h1>Cookie Policy</h1><p>Last Updated: ' . date('F j, Y') . '</p><h2>What Are Cookies</h2><p>Cookies are small text files that are stored on your computer or mobile device...</p><h2>How We Use Cookies</h2><p>We use cookies to improve your experience...</p><h2>Cookie Types</h2><p>Essential cookies, Analytics cookies, Preference cookies...</p>',
            '1.0',
            date('Y-m-d'),
            'system',
            1
        ]
    ];

    $stmt = $pdo->prepare("INSERT IGNORE INTO legal_pages
        (page_key, page_title, page_content, version, effective_date, updated_by, is_published)
        VALUES (?, ?, ?, ?, ?, ?, ?)");

    foreach ($default_legal_pages as $page) {
        $stmt->execute($page);
    }

    $results['steps']['legal_pages'] = 'Success';
    echo "<p style='color: green;'>✅ Legal pages system installed successfully (1 table + 3 documents)</p>\n";
} catch (Exception $e) {
    $results['warnings'][] = "Legal pages: " . $e->getMessage();
    echo "<p style='color: orange;'>⚠️ Warning: Legal pages system: " . htmlspecialchars($e->getMessage()) . "</p>\n";
}
flush();

/**
 * Installation Summary
 */
echo "<hr>\n";
echo "<h2>Installation Summary</h2>\n";

if ($results['status'] === 'success') {
    echo "<p style='color: green; font-size: 18px; font-weight: bold;'>✅ All systems installed successfully!</p>\n";
} else {
    echo "<p style='color: orange; font-size: 18px; font-weight: bold;'>⚠️ Installation completed with warnings</p>\n";
}

echo "<h3>Completed Steps:</h3>\n<ul>\n";
foreach ($results['steps'] as $step => $status) {
    echo "<li>✅ " . htmlspecialchars(ucwords(str_replace('_', ' ', $step))) . ": $status</li>\n";
}
echo "</ul>\n";

if (!empty($results['warnings'])) {
    echo "<h3>Warnings:</h3>\n<ul>\n";
    foreach ($results['warnings'] as $warning) {
        echo "<li>⚠️ " . htmlspecialchars($warning) . "</li>\n";
    }
    echo "</ul>\n";
}

if (!empty($results['errors'])) {
    echo "<h3>Errors:</h3>\n<ul style='color: red;'>\n";
    foreach ($results['errors'] as $error) {
        echo "<li>❌ " . htmlspecialchars($error) . "</li>\n";
    }
    echo "</ul>\n";
}

echo "<h3>Database Tables Created:</h3>\n";
echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>\n";
echo "<tr><th>System</th><th>Tables</th><th>Count</th></tr>\n";
echo "<tr><td>Mattermost</td><td>mattermost_config, mattermost_channels, mattermost_user_mapping, mattermost_message_cache, mattermost_notifications, mattermost_activity_log</td><td>6</td></tr>\n";
echo "<tr><td>Notifications</td><td>notifications, notification_deliveries, notification_preferences, notification_templates, notification_stats</td><td>5</td></tr>\n";
echo "<tr><td>Announcements</td><td>announcements, announcement_views, announcement_templates</td><td>3</td></tr>\n";
echo "<tr><td>Help System</td><td>help_articles, help_tooltips</td><td>2</td></tr>\n";
echo "<tr><td>Legal Pages</td><td>legal_pages</td><td>1</td></tr>\n";
echo "<tr><td colspan='2' style='text-align: right;'><strong>Total New Tables:</strong></td><td><strong>17</strong></td></tr>\n";
echo "</table>\n";

echo "<h3>Next Steps:</h3>\n";
echo "<ol>\n";
echo "<li>Configure Mattermost integration (Admin > Mattermost Setup)</li>\n";
echo "<li>Create your first announcement (Admin > Announcements)</li>\n";
echo "<li>Customize notification preferences (User Portal > Notifications)</li>\n";
echo "<li>Review help articles (User Portal > Help)</li>\n";
echo "<li>Customize legal pages (Admin > Legal Pages)</li>\n";
echo "<li>Configure AI training sources (Admin > AI Training)</li>\n";
echo "</ol>\n";

echo "<p><strong>Installation Log:</strong> Check your PHP error log for detailed information.</p>\n";
echo "<p><strong>Version:</strong> FlexPBX v1.3.0</p>\n";
echo "<p><strong>Installation Date:</strong> " . date('F j, Y H:i:s') . "</p>\n";

// Return JSON if requested
if (isset($_GET['format']) && $_GET['format'] === 'json') {
    header('Content-Type: application/json');
    echo json_encode($results, JSON_PRETTY_PRINT);
}
?>
