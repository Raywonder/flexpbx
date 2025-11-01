-- FlexPBX Email Configuration and Notification System
-- Database Schema
-- Created: 2025-10-17

-- ============================================
-- System-Wide Email Configuration
-- ============================================

CREATE TABLE IF NOT EXISTS `email_system_config` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `smtp_host` VARCHAR(255) NOT NULL,
  `smtp_port` INT UNSIGNED NOT NULL DEFAULT 587,
  `smtp_security` ENUM('none', 'tls', 'ssl') NOT NULL DEFAULT 'tls',
  `smtp_username` VARCHAR(255) NOT NULL,
  `smtp_password` TEXT NOT NULL COMMENT 'Encrypted password',
  `default_from_email` VARCHAR(255) NOT NULL DEFAULT 'services@devine-creations.com',
  `default_from_name` VARCHAR(255) NOT NULL DEFAULT 'FlexPBX Services',
  `default_reply_to` VARCHAR(255) NOT NULL DEFAULT 'support@devine-creations.com',
  `max_retry_attempts` INT UNSIGNED NOT NULL DEFAULT 3,
  `send_timeout` INT UNSIGNED NOT NULL DEFAULT 30 COMMENT 'Timeout in seconds',
  `rate_limit_per_hour` INT UNSIGNED NOT NULL DEFAULT 100 COMMENT 'Max emails per hour',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default configuration
INSERT INTO `email_system_config` (
  `smtp_host`,
  `smtp_port`,
  `smtp_security`,
  `smtp_username`,
  `smtp_password`,
  `default_from_email`,
  `default_from_name`,
  `default_reply_to`
) VALUES (
  'localhost',
  587,
  'tls',
  'services@devine-creations.com',
  '', -- Will be encrypted when set via admin interface
  'services@devine-creations.com',
  'FlexPBX Services',
  'support@devine-creations.com'
) ON DUPLICATE KEY UPDATE `id`=`id`;

-- ============================================
-- Email Templates
-- ============================================

CREATE TABLE IF NOT EXISTS `email_templates` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `template_key` VARCHAR(100) NOT NULL UNIQUE COMMENT 'Unique template identifier',
  `template_name` VARCHAR(255) NOT NULL,
  `subject` VARCHAR(500) NOT NULL,
  `body_html` TEXT NOT NULL,
  `body_text` TEXT NOT NULL,
  `available_variables` TEXT COMMENT 'JSON array of available variables',
  `category` VARCHAR(50) NOT NULL DEFAULT 'general',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_template_key` (`template_key`),
  INDEX `idx_category` (`category`),
  INDEX `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default templates
INSERT INTO `email_templates` (
  `template_key`,
  `template_name`,
  `subject`,
  `body_html`,
  `body_text`,
  `available_variables`,
  `category`
) VALUES
(
  'welcome_email',
  'Welcome to FlexPBX',
  'Welcome to FlexPBX - Your Account Details',
  '<html><body><h1>Welcome to FlexPBX!</h1><p>Hello {{username}},</p><p>Your FlexPBX account has been successfully created.</p><p><strong>Account Details:</strong></p><ul><li>Username: {{username}}</li><li>Extension: {{extension}}</li><li>Temporary Password: {{password}}</li></ul><p>Please log in and change your password immediately.</p><p>{{custom_message}}</p><p>Best regards,<br>FlexPBX Team</p></body></html>',
  'Welcome to FlexPBX!\n\nHello {{username}},\n\nYour FlexPBX account has been successfully created.\n\nAccount Details:\n- Username: {{username}}\n- Extension: {{extension}}\n- Temporary Password: {{password}}\n\nPlease log in and change your password immediately.\n\n{{custom_message}}\n\nBest regards,\nFlexPBX Team',
  '["username", "extension", "password", "custom_message"]',
  'account'
),
(
  'password_reset',
  'Password Reset Request',
  'FlexPBX Password Reset',
  '<html><body><h1>Password Reset Request</h1><p>Hello {{username}},</p><p>We received a request to reset your password. Click the link below to reset it:</p><p><a href="{{reset_link}}">Reset Password</a></p><p>This link will expire in {{expiry_hours}} hours.</p><p>If you did not request this, please ignore this email.</p><p>Best regards,<br>FlexPBX Team</p></body></html>',
  'Password Reset Request\n\nHello {{username}},\n\nWe received a request to reset your password. Use the link below to reset it:\n\n{{reset_link}}\n\nThis link will expire in {{expiry_hours}} hours.\n\nIf you did not request this, please ignore this email.\n\nBest regards,\nFlexPBX Team',
  '["username", "reset_link", "expiry_hours"]',
  'account'
),
(
  'voicemail_notification',
  'New Voicemail',
  'New Voicemail from {{caller_id}}',
  '<html><body><h1>New Voicemail</h1><p>Hello {{username}},</p><p>You have a new voicemail message:</p><ul><li>From: {{caller_id}}</li><li>Date: {{date_time}}</li><li>Duration: {{duration}}</li><li>Mailbox: {{mailbox}}</li></ul><p>{{custom_message}}</p><p>Best regards,<br>FlexPBX Team</p></body></html>',
  'New Voicemail\n\nHello {{username}},\n\nYou have a new voicemail message:\n\n- From: {{caller_id}}\n- Date: {{date_time}}\n- Duration: {{duration}}\n- Mailbox: {{mailbox}}\n\n{{custom_message}}\n\nBest regards,\nFlexPBX Team',
  '["username", "caller_id", "date_time", "duration", "mailbox", "custom_message"]',
  'notification'
),
(
  'missed_call',
  'Missed Call Notification',
  'Missed Call from {{caller_id}}',
  '<html><body><h1>Missed Call</h1><p>Hello {{username}},</p><p>You missed a call:</p><ul><li>From: {{caller_id}}</li><li>Date: {{date_time}}</li><li>Extension: {{extension}}</li></ul><p>{{custom_message}}</p><p>Best regards,<br>FlexPBX Team</p></body></html>',
  'Missed Call\n\nHello {{username}},\n\nYou missed a call:\n\n- From: {{caller_id}}\n- Date: {{date_time}}\n- Extension: {{extension}}\n\n{{custom_message}}\n\nBest regards,\nFlexPBX Team',
  '["username", "caller_id", "date_time", "extension", "custom_message"]',
  'notification'
),
(
  'extension_changed',
  'Extension Number Changed',
  'Your Extension Number Has Changed',
  '<html><body><h1>Extension Number Changed</h1><p>Hello {{username}},</p><p>Your extension number has been changed:</p><ul><li>Old Extension: {{old_extension}}</li><li>New Extension: {{new_extension}}</li><li>Changed By: {{changed_by}}</li><li>Date: {{date_time}}</li></ul><p>Please update your phone configuration accordingly.</p><p>{{custom_message}}</p><p>Best regards,<br>FlexPBX Team</p></body></html>',
  'Extension Number Changed\n\nHello {{username}},\n\nYour extension number has been changed:\n\n- Old Extension: {{old_extension}}\n- New Extension: {{new_extension}}\n- Changed By: {{changed_by}}\n- Date: {{date_time}}\n\nPlease update your phone configuration accordingly.\n\n{{custom_message}}\n\nBest regards,\nFlexPBX Team',
  '["username", "old_extension", "new_extension", "changed_by", "date_time", "custom_message"]',
  'account'
),
(
  'security_alert',
  'Security Alert',
  'FlexPBX Security Alert - {{alert_type}}',
  '<html><body><h1>Security Alert</h1><p>Hello {{username}},</p><p>A security event has been detected on your account:</p><ul><li>Alert Type: {{alert_type}}</li><li>Date: {{date_time}}</li><li>IP Address: {{ip_address}}</li><li>Details: {{details}}</li></ul><p>If this was not you, please contact support immediately.</p><p>{{custom_message}}</p><p>Best regards,<br>FlexPBX Team</p></body></html>',
  'Security Alert\n\nHello {{username}},\n\nA security event has been detected on your account:\n\n- Alert Type: {{alert_type}}\n- Date: {{date_time}}\n- IP Address: {{ip_address}}\n- Details: {{details}}\n\nIf this was not you, please contact support immediately.\n\n{{custom_message}}\n\nBest regards,\nFlexPBX Team',
  '["username", "alert_type", "date_time", "ip_address", "details", "custom_message"]',
  'security'
),
(
  'call_recording_available',
  'Call Recording Available',
  'Call Recording Available',
  '<html><body><h1>Call Recording Available</h1><p>Hello {{username}},</p><p>A new call recording is available:</p><ul><li>Call Date: {{date_time}}</li><li>Duration: {{duration}}</li><li>Participants: {{participants}}</li><li>Recording ID: {{recording_id}}</li></ul><p>{{custom_message}}</p><p>Best regards,<br>FlexPBX Team</p></body></html>',
  'Call Recording Available\n\nHello {{username}},\n\nA new call recording is available:\n\n- Call Date: {{date_time}}\n- Duration: {{duration}}\n- Participants: {{participants}}\n- Recording ID: {{recording_id}}\n\n{{custom_message}}\n\nBest regards,\nFlexPBX Team',
  '["username", "date_time", "duration", "participants", "recording_id", "custom_message"]',
  'notification'
),
(
  'test_email',
  'Test Email',
  'FlexPBX Test Email',
  '<html><body><h1>Test Email</h1><p>This is a test email from your FlexPBX system.</p><p>If you received this, your email configuration is working correctly!</p><p>Timestamp: {{timestamp}}</p><p>Best regards,<br>FlexPBX Team</p></body></html>',
  'Test Email\n\nThis is a test email from your FlexPBX system.\n\nIf you received this, your email configuration is working correctly!\n\nTimestamp: {{timestamp}}\n\nBest regards,\nFlexPBX Team',
  '["timestamp"]',
  'system'
) ON DUPLICATE KEY UPDATE `template_name`=VALUES(`template_name`);

-- ============================================
-- Email Queue
-- ============================================

CREATE TABLE IF NOT EXISTS `email_queue` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `to_email` VARCHAR(255) NOT NULL,
  `to_name` VARCHAR(255) DEFAULT NULL,
  `subject` VARCHAR(500) NOT NULL,
  `body_html` TEXT NOT NULL,
  `body_text` TEXT NOT NULL,
  `reply_to` VARCHAR(255) DEFAULT NULL,
  `template_id` INT UNSIGNED DEFAULT NULL,
  `template_variables` TEXT COMMENT 'JSON encoded variables',
  `priority` TINYINT UNSIGNED NOT NULL DEFAULT 5 COMMENT '1=highest, 10=lowest',
  `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
  `max_attempts` INT UNSIGNED NOT NULL DEFAULT 3,
  `status` ENUM('pending', 'sending', 'sent', 'failed', 'cancelled') NOT NULL DEFAULT 'pending',
  `last_error` TEXT DEFAULT NULL,
  `scheduled_at` TIMESTAMP NULL DEFAULT NULL,
  `sent_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_status` (`status`),
  INDEX `idx_priority` (`priority`),
  INDEX `idx_scheduled_at` (`scheduled_at`),
  INDEX `idx_created_at` (`created_at`),
  INDEX `idx_to_email` (`to_email`),
  FOREIGN KEY (`template_id`) REFERENCES `email_templates`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Email Sending Log
-- ============================================

CREATE TABLE IF NOT EXISTS `email_log` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `queue_id` INT UNSIGNED DEFAULT NULL,
  `to_email` VARCHAR(255) NOT NULL,
  `to_name` VARCHAR(255) DEFAULT NULL,
  `from_email` VARCHAR(255) NOT NULL,
  `from_name` VARCHAR(255) DEFAULT NULL,
  `reply_to` VARCHAR(255) DEFAULT NULL,
  `subject` VARCHAR(500) NOT NULL,
  `template_id` INT UNSIGNED DEFAULT NULL,
  `template_key` VARCHAR(100) DEFAULT NULL,
  `status` ENUM('sent', 'failed', 'bounced') NOT NULL,
  `error_message` TEXT DEFAULT NULL,
  `smtp_response` TEXT DEFAULT NULL,
  `attempts` INT UNSIGNED NOT NULL DEFAULT 1,
  `sent_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  INDEX `idx_queue_id` (`queue_id`),
  INDEX `idx_to_email` (`to_email`),
  INDEX `idx_status` (`status`),
  INDEX `idx_sent_at` (`sent_at`),
  INDEX `idx_template_key` (`template_key`),
  INDEX `idx_user_id` (`user_id`),
  FOREIGN KEY (`queue_id`) REFERENCES `email_queue`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`template_id`) REFERENCES `email_templates`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- User Notification Preferences
-- ============================================

CREATE TABLE IF NOT EXISTS `user_notification_preferences` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `extension` VARCHAR(50) DEFAULT NULL,
  `email_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `notify_voicemail` TINYINT(1) NOT NULL DEFAULT 1,
  `notify_missed_call` TINYINT(1) NOT NULL DEFAULT 1,
  `notify_extension_change` TINYINT(1) NOT NULL DEFAULT 1,
  `notify_security_alert` TINYINT(1) NOT NULL DEFAULT 1,
  `notify_call_recording` TINYINT(1) NOT NULL DEFAULT 0,
  `notify_fax` TINYINT(1) NOT NULL DEFAULT 1,
  `notify_conference` TINYINT(1) NOT NULL DEFAULT 0,
  `digest_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `digest_frequency` ENUM('immediate', 'hourly', 'daily') NOT NULL DEFAULT 'immediate',
  `digest_time` TIME DEFAULT '09:00:00' COMMENT 'Time for daily digest',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_user_id` (`user_id`),
  INDEX `idx_extension` (`extension`),
  INDEX `idx_email_enabled` (`email_enabled`),
  INDEX `idx_digest_enabled` (`digest_enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Email Rate Limiting
-- ============================================

CREATE TABLE IF NOT EXISTS `email_rate_limit` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `identifier` VARCHAR(255) NOT NULL COMMENT 'Email address or IP',
  `identifier_type` ENUM('email', 'ip', 'user') NOT NULL DEFAULT 'email',
  `count` INT UNSIGNED NOT NULL DEFAULT 1,
  `window_start` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `window_end` TIMESTAMP NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_identifier_window` (`identifier`, `identifier_type`, `window_start`),
  INDEX `idx_window_end` (`window_end`),
  INDEX `idx_identifier` (`identifier`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Email Bounce Tracking
-- ============================================

CREATE TABLE IF NOT EXISTS `email_bounces` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(255) NOT NULL,
  `bounce_type` ENUM('hard', 'soft', 'complaint') NOT NULL,
  `bounce_reason` TEXT DEFAULT NULL,
  `bounce_count` INT UNSIGNED NOT NULL DEFAULT 1,
  `last_bounce_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_suppressed` TINYINT(1) NOT NULL DEFAULT 0,
  `suppressed_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_email` (`email`),
  INDEX `idx_is_suppressed` (`is_suppressed`),
  INDEX `idx_bounce_type` (`bounce_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Email Digest Queue
-- ============================================

CREATE TABLE IF NOT EXISTS `email_digest_queue` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `notification_type` VARCHAR(100) NOT NULL,
  `notification_data` TEXT NOT NULL COMMENT 'JSON encoded notification data',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_notification_type` (`notification_type`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Views for Reporting
-- ============================================

CREATE OR REPLACE VIEW `email_statistics` AS
SELECT
  DATE(sent_at) as date,
  template_key,
  status,
  COUNT(*) as count,
  AVG(attempts) as avg_attempts
FROM email_log
GROUP BY DATE(sent_at), template_key, status;

CREATE OR REPLACE VIEW `email_queue_summary` AS
SELECT
  status,
  priority,
  COUNT(*) as count,
  MIN(created_at) as oldest,
  MAX(created_at) as newest
FROM email_queue
GROUP BY status, priority;
