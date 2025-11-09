<?php
/**
 * FlexPBX Notifications System - Database Setup
 * Creates tables for comprehensive role-based notification system
 *
 * @version 1.0.0
 * @date 2025-11-06
 */

// Prevent direct access
define('FLEXPBX_INIT', true);

// Load database configuration
require_once __DIR__ . '/../config/database.php';

$results = [];

try {
    // Create notifications table
    $sql_notifications = "CREATE TABLE IF NOT EXISTS notifications (
        id INT PRIMARY KEY AUTO_INCREMENT,
        notification_type ENUM('system', 'call', 'voicemail', 'sms', 'alert', 'message', 'task', 'announcement') NOT NULL,
        title VARCHAR(200) NOT NULL,
        message TEXT,
        icon VARCHAR(50),
        link_url VARCHAR(255),
        priority ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
        target_user_id VARCHAR(100),
        target_role VARCHAR(50),
        target_group VARCHAR(100),
        created_by VARCHAR(100),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        expires_at DATETIME,
        is_scheduled BOOLEAN DEFAULT FALSE,
        scheduled_for DATETIME,
        metadata JSON,
        INDEX idx_target_user (target_user_id),
        INDEX idx_target_role (target_role),
        INDEX idx_type (notification_type),
        INDEX idx_created_at (created_at),
        INDEX idx_scheduled (is_scheduled, scheduled_for)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $pdo->exec($sql_notifications);
    $results['notifications'] = 'Created successfully';

    // Create notification_deliveries table
    $sql_deliveries = "CREATE TABLE IF NOT EXISTS notification_deliveries (
        id INT PRIMARY KEY AUTO_INCREMENT,
        notification_id INT NOT NULL,
        user_id VARCHAR(100) NOT NULL,
        is_read BOOLEAN DEFAULT FALSE,
        read_at DATETIME,
        is_dismissed BOOLEAN DEFAULT FALSE,
        dismissed_at DATETIME,
        delivered_via ENUM('web', 'email', 'sms', 'push') DEFAULT 'web',
        delivery_status ENUM('pending', 'delivered', 'failed') DEFAULT 'pending',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE CASCADE,
        INDEX idx_user_id (user_id),
        INDEX idx_notification_id (notification_id),
        INDEX idx_read_status (is_read),
        INDEX idx_user_read (user_id, is_read)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $pdo->exec($sql_deliveries);
    $results['notification_deliveries'] = 'Created successfully';

    // Create notification_preferences table
    $sql_preferences = "CREATE TABLE IF NOT EXISTS notification_preferences (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id VARCHAR(100) UNIQUE NOT NULL,
        notification_types JSON,
        delivery_methods JSON,
        quiet_hours_enabled BOOLEAN DEFAULT FALSE,
        quiet_hours_start TIME,
        quiet_hours_end TIME,
        sound_enabled BOOLEAN DEFAULT TRUE,
        desktop_enabled BOOLEAN DEFAULT FALSE,
        email_enabled BOOLEAN DEFAULT TRUE,
        sms_enabled BOOLEAN DEFAULT FALSE,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $pdo->exec($sql_preferences);
    $results['notification_preferences'] = 'Created successfully';

    // Create notification_templates table
    $sql_templates = "CREATE TABLE IF NOT EXISTS notification_templates (
        id INT PRIMARY KEY AUTO_INCREMENT,
        template_name VARCHAR(100) UNIQUE NOT NULL,
        notification_type ENUM('system', 'call', 'voicemail', 'sms', 'alert', 'message', 'task', 'announcement') NOT NULL,
        title_template VARCHAR(200) NOT NULL,
        message_template TEXT,
        icon VARCHAR(50),
        priority ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $pdo->exec($sql_templates);
    $results['notification_templates'] = 'Created successfully';

    // Create notification_stats table for analytics
    $sql_stats = "CREATE TABLE IF NOT EXISTS notification_stats (
        id INT PRIMARY KEY AUTO_INCREMENT,
        notification_id INT NOT NULL,
        total_recipients INT DEFAULT 0,
        total_delivered INT DEFAULT 0,
        total_read INT DEFAULT 0,
        total_dismissed INT DEFAULT 0,
        avg_read_time INT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE CASCADE,
        INDEX idx_notification_id (notification_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $pdo->exec($sql_stats);
    $results['notification_stats'] = 'Created successfully';

    // Insert default notification templates
    $default_templates = [
        [
            'template_name' => 'missed_call',
            'notification_type' => 'call',
            'title_template' => 'Missed Call',
            'message_template' => 'You missed a call from {caller_id} at {time}',
            'icon' => 'phone-missed',
            'priority' => 'normal'
        ],
        [
            'template_name' => 'new_voicemail',
            'notification_type' => 'voicemail',
            'title_template' => 'New Voicemail',
            'message_template' => 'You have a new voicemail from {caller_id}',
            'icon' => 'voicemail',
            'priority' => 'high'
        ],
        [
            'template_name' => 'new_sms',
            'notification_type' => 'sms',
            'title_template' => 'New SMS Message',
            'message_template' => 'SMS from {sender}: {preview}',
            'icon' => 'message',
            'priority' => 'normal'
        ],
        [
            'template_name' => 'system_alert',
            'notification_type' => 'alert',
            'title_template' => 'System Alert',
            'message_template' => '{message}',
            'icon' => 'alert-triangle',
            'priority' => 'urgent'
        ],
        [
            'template_name' => 'system_announcement',
            'notification_type' => 'announcement',
            'title_template' => 'System Announcement',
            'message_template' => '{message}',
            'icon' => 'megaphone',
            'priority' => 'normal'
        ],
        [
            'template_name' => 'task_assigned',
            'notification_type' => 'task',
            'title_template' => 'Task Assigned',
            'message_template' => 'You have been assigned a new task: {task_name}',
            'icon' => 'clipboard',
            'priority' => 'normal'
        ]
    ];

    $stmt = $pdo->prepare("INSERT IGNORE INTO notification_templates
        (template_name, notification_type, title_template, message_template, icon, priority)
        VALUES (?, ?, ?, ?, ?, ?)");

    foreach ($default_templates as $template) {
        $stmt->execute([
            $template['template_name'],
            $template['notification_type'],
            $template['title_template'],
            $template['message_template'],
            $template['icon'],
            $template['priority']
        ]);
    }

    $results['default_templates'] = 'Inserted ' . count($default_templates) . ' templates';

    // Success
    $results['status'] = 'success';
    $results['message'] = 'Notifications system database setup completed successfully';

} catch (PDOException $e) {
    $results['status'] = 'error';
    $results['message'] = $e->getMessage();
    error_log("Notifications Setup Error: " . $e->getMessage());
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($results, JSON_PRETTY_PRINT);
?>
